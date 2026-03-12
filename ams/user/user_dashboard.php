<?php
/**
 * user_dashboard.php
 * NexusAdmin - Complete User Dashboard
 */

session_start();

// --- 1. SETTINGS & AUTH ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- 2. DATABASE CONNECTION ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) {
    die("Database Connection Error: " . $ex->getMessage());
}

// --- 3. FETCH USER DATA ---
$user_id = $_SESSION['user_id'];
$user = [];
$stats = [];
$recent_purchases = [];
$notifications = [];
$system_stats = []; 
$todays_orders = [];
$total_orders = 0;

try {
    // Get user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
    
    // Update session with current user data
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    // Get user statistics
    // Total purchases
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_orders, SUM(total_amount) as total_spent 
        FROM orders 
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    // Recent purchases (last 5)
    $stmt = $pdo->prepare("
        SELECT o.*, COUNT(oi.id) as items_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.user_id = ? 
        GROUP BY o.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $recent_purchases = $stmt->fetchAll();
    
    // Get notifications
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $notifications = $stmt->fetchAll();
    
    // Mark notifications as read
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$user_id]);
    
    // ================== SYSTEM STATISTICS ==================
    // Get total vendors
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_vendors FROM vendors WHERE status = 'active'");
    $stmt->execute();
    $system_stats['total_vendors'] = $stmt->fetchColumn();
    
    // Get total employees
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_employees FROM employees WHERE status = 'active'");
    $stmt->execute();
    $system_stats['total_employees'] = $stmt->fetchColumn();
    
    // Get total customers
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_customers FROM users WHERE role = 'customer'");
    $stmt->execute();
    $system_stats['total_customers'] = $stmt->fetchColumn();
    
    // Get total orders (system-wide)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders");
    $stmt->execute();
    $total_orders = $stmt->fetchColumn();
    
    // Get today's orders
    $stmt = $pdo->prepare("
        SELECT o.*, u.username as customer_name 
        FROM orders o 
        LEFT JOIN users u ON o.user_id = u.id 
        WHERE DATE(o.created_at) = CURDATE() 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $todays_orders = $stmt->fetchAll();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as today_orders_count, 
               SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as today_revenue
        FROM orders 
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $today_data = $stmt->fetch();
    $system_stats['today_orders'] = $today_data['today_orders_count'];
    $system_stats['today_revenue'] = $today_data['today_revenue'] ?? 0;
    
    // Get monthly revenue
    $stmt = $pdo->prepare("
        SELECT SUM(total_amount) as monthly_revenue 
        FROM orders 
        WHERE MONTH(created_at) = MONTH(CURDATE()) 
        AND YEAR(created_at) = YEAR(CURDATE())
        AND status = 'completed'
    ");
    $stmt->execute();
    $system_stats['monthly_revenue'] = $stmt->fetchColumn() ?? 0;
    
    // Get active users (users who logged in last 30 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as active_users 
        FROM users 
        WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $system_stats['active_users'] = $stmt->fetchColumn();
    
    // Get vendor statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
        FROM vendors
    ");
    $stmt->execute();
    $system_stats['vendor_stats'] = $stmt->fetch();
    
    // Get employee statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
        FROM employees
    ");
    $stmt->execute();
    $system_stats['employee_stats'] = $stmt->fetch();
    
    // Get customer statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive
        FROM users 
        WHERE role = 'customer'
    ");
    $stmt->execute();
    $system_stats['customer_stats'] = $stmt->fetch();
    
    // Get order statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM orders
    ");
    $stmt->execute();
    $system_stats['order_stats'] = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
}

// Helper functions
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
function formatDate($date) { return date('M d, Y', strtotime($date)); }
function formatDateTime($date) { return date('M d, Y h:i A', strtotime($date)); }
function formatCurrency($amount) { return '$' . number_format($amount, 2); }
function getTimeGreeting() {
    $hour = date('H');
    if ($hour < 12) return "Good Morning";
    if ($hour < 17) return "Good Afternoon";
    return "Good Evening";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?php echo e($user['username'] ?? 'User'); ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            --primary: #4F46E5;
            --primary-light: rgba(79, 70, 229, 0.1);
            --primary-hover: #4338ca;
            --bg-body: #F3F4F6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --sidebar-width: 280px;
            --sidebar-bg: #111827;
            --sidebar-text: #E5E7EB;
            --header-height: 70px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 12px;
            --success: #10B981;
            --success-light: rgba(16, 185, 129, 0.1);
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
            --info-light: rgba(59, 130, 246, 0.1);
            --purple: #8B5CF6;
            --pink: #EC4899;
            --teal: #14B8A6;
            --orange: #F97316;
            --cyan: #06B6D4;
            --gray: #6B7280;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
        }
        
        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }
        
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button { font-family: inherit; cursor: pointer; border: none; background: none; }

        /* --- Sidebar --- */
        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            transition: transform var(--transition);
            z-index: 50;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.05);
            height: 100vh;
            position: sticky;
            top: 0;
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            gap: 12px;
        }

        .logo {
            font-weight: 700;
            font-size: 1.4rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-icon {
            background: var(--primary);
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .sidebar-menu {
            padding: 24px 16px;
            overflow-y: auto;
            flex: 1;
        }

        .menu-item {
            margin-bottom: 8px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 10px;
            color: rgba(255,255,255,0.7);
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .menu-link:hover, .menu-link.active {
            background-color: rgba(255,255,255,0.1);
            color: #fff;
            transform: translateX(4px);
        }

        .menu-icon {
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }

        .menu-badge {
            margin-left: auto;
            background: var(--primary);
            color: white;
            font-size: 0.75rem;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: 600;
        }

        .user-profile {
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.05);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .user-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            object-fit: cover;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
            font-size: 0.95rem;
            font-weight: 600;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-email {
            font-size: 0.8rem;
            color: rgba(255,255,255,0.6);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .logout-btn {
            color: rgba(255,255,255,0.5);
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .logout-btn:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }

        /* --- Main Content --- */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .top-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            z-index: 40;
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-btn {
            position: relative;
            color: var(--text-muted);
            padding: 8px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .notification-btn:hover {
            color: var(--primary);
            background: var(--primary-light);
        }

        .notification-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            width: 8px;
            height: 8px;
            background: var(--danger);
            border-radius: 50%;
            border: 2px solid var(--bg-card);
        }

        .mobile-toggle {
            display: none;
            color: var(--text-muted);
            padding: 8px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .mobile-toggle:hover {
            color: var(--primary);
            background: var(--primary-light);
        }

        /* --- Page Content --- */
        .page-content {
            flex: 1;
            padding: 32px;
            overflow-y: auto;
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%);
            color: white;
            border-radius: var(--radius);
            padding: 32px;
            margin-bottom: 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }

        .welcome-content h1 {
            font-size: 1.8rem;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .welcome-content p {
            color: rgba(255,255,255,0.9);
            font-size: 1rem;
            max-width: 500px;
        }

        .welcome-icon {
            font-size: 4rem;
            opacity: 0.2;
            z-index: 1;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 24px;
            border: 1px solid var(--border);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
        }

        .stat-card.primary::before {
            background: var(--primary);
        }

        .stat-card.success::before {
            background: var(--success);
        }

        .stat-card.info::before {
            background: var(--info);
        }

        .stat-card.purple::before {
            background: var(--purple);
        }

        .stat-card.pink::before {
            background: var(--pink);
        }

        .stat-card.teal::before {
            background: var(--teal);
        }

        .stat-card.orange::before {
            background: var(--orange);
        }

        .stat-card.cyan::before {
            background: var(--cyan);
        }

        .stat-card.gray::before {
            background: var(--gray);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.5rem;
        }

        .stat-card.primary .stat-icon {
            background: var(--primary-light);
            color: var(--primary);
        }

        .stat-card.success .stat-icon {
            background: var(--success-light);
            color: var(--success);
        }

        .stat-card.info .stat-icon {
            background: var(--info-light);
            color: var(--info);
        }

        .stat-card.purple .stat-icon {
            background: rgba(139, 92, 246, 0.1);
            color: var(--purple);
        }

        .stat-card.pink .stat-icon {
            background: rgba(236, 72, 153, 0.1);
            color: var(--pink);
        }

        .stat-card.teal .stat-icon {
            background: rgba(20, 184, 166, 0.1);
            color: var(--teal);
        }

        .stat-card.orange .stat-icon {
            background: rgba(249, 115, 22, 0.1);
            color: var(--orange);
        }

        .stat-card.cyan .stat-icon {
            background: rgba(6, 182, 212, 0.1);
            color: var(--cyan);
        }

        .stat-card.gray .stat-icon {
            background: rgba(107, 114, 128, 0.1);
            color: var(--gray);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
            color: var(--text-main);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }

        .stat-details {
            display: flex;
            justify-content: space-between;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
            font-size: 0.85rem;
        }

        .stat-detail {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }

        .detail-value {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 2px;
        }

        .detail-label {
            color: var(--text-muted);
            font-size: 0.75rem;
        }

        .detail-active {
            color: var(--success);
        }

        .detail-inactive {
            color: var(--danger);
        }

        .detail-pending {
            color: var(--warning);
        }

        .detail-processing {
            color: var(--info);
        }

        .detail-completed {
            color: var(--success);
        }

        .detail-cancelled {
            color: var(--danger);
        }

        .stat-change {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .stat-change.positive {
            color: var(--success);
        }

        .stat-change.negative {
            color: var(--danger);
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Card Styles */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-body {
            padding: 24px;
        }

        .card-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            background: var(--bg-body);
        }

        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            background: var(--bg-body);
            padding: 12px 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-main);
            border-bottom: 2px solid var(--border);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 0.95rem;
        }

        .data-table tbody tr:hover {
            background: var(--bg-body);
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }

        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .status-processing {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .status-completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .btn-view {
            padding: 6px 12px;
            background: var(--primary);
            color: white;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: background 0.2s ease;
        }

        .btn-view:hover {
            background: var(--primary-hover);
        }

        /* Notification List */
        .notification-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .notification-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .notification-item:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            transform: translateX(4px);
        }

        .notification-item.unread {
            background: var(--primary-light);
            border-color: var(--primary);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }

        .notification-icon.info {
            background: var(--info-light);
            color: var(--info);
        }

        .notification-icon.success {
            background: var(--success-light);
            color: var(--success);
        }

        .notification-icon.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .notification-content {
            flex: 1;
            min-width: 0;
        }

        .notification-title {
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .notification-message {
            font-size: 0.9rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .notification-time {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-top: 20px;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            padding: 20px;
            border-radius: 12px;
            background: var(--bg-body);
            border: 1px solid var(--border);
            transition: all 0.2s ease;
            text-align: center;
        }

        .action-btn:hover {
            background: var(--primary-light);
            border-color: var(--primary);
            transform: translateY(-2px);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            background: var(--primary);
            color: white;
        }

        .action-btn:nth-child(2) .action-icon {
            background: var(--success);
        }

        .action-btn:nth-child(3) .action-icon {
            background: var(--info);
        }

        .action-btn:nth-child(4) .action-icon {
            background: var(--purple);
        }

        .action-label {
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.95rem;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Notification Dropdown */
        .notification-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 380px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            z-index: 100;
            display: none;
            max-height: 500px;
            overflow-y: auto;
        }

        .notification-dropdown.show {
            display: block;
            animation: fadeIn 0.2s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* System Stats Cards */
        .system-stats {
            margin-bottom: 32px;
        }

        .stats-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stats-title i {
            color: var(--primary);
        }

        /* Mini Progress Bar */
        .mini-progress {
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            margin: 8px 0;
            overflow: hidden;
        }

        .mini-progress-fill {
            height: 100%;
            border-radius: 2px;
        }

        .progress-green {
            background: var(--success);
        }

        .progress-red {
            background: var(--danger);
        }

        .progress-blue {
            background: var(--info);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }
            
            .sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                height: 100vh;
                z-index: 1000;
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.2);
            }
            
            .sidebar.show {
                transform: translateX(280px);
            }
            
            .mobile-toggle {
                display: block;
            }
            
            .top-header {
                padding: 0 20px;
            }
            
            .page-content {
                padding: 20px;
            }
            
            .welcome-banner {
                flex-direction: column;
                text-align: center;
                gap: 20px;
                padding: 24px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .notification-dropdown {
                position: fixed;
                top: var(--header-height);
                left: 0;
                width: 100%;
                border-radius: 0;
                border-left: none;
                border-right: none;
                max-height: calc(100vh - var(--header-height));
            }
        }

        @media (max-width: 480px) {
            .content-grid {
                gap: 16px;
            }
            
            .card-body {
                padding: 16px;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
            
            .stat-details {
                flex-direction: column;
                gap: 8px;
            }
        }

        /* Animation for stats */
        @keyframes countUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stat-card {
            animation: countUp 0.6s ease-out;
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-icon {
            font-size: 3rem;
            color: var(--text-muted);
            opacity: 0.3;
            margin-bottom: 16px;
        }

        .empty-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .empty-description {
            color: var(--text-muted);
            max-width: 300px;
            margin: 0 auto 20px auto;
        }

        /* Progress Bar */
        .progress-bar {
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            overflow: hidden;
            margin: 16px 0;
        }

        .progress-fill {
            height: 100%;
            background: var(--primary);
            border-radius: 3px;
            transition: width 1s ease;
        }

        /* Profile Progress */
        .profile-progress {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 24px;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .progress-value {
            font-weight: 600;
            color: var(--primary);
        }

        /* Overlay for mobile */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(2px);
            z-index: 999;
            display: none;
        }

        .overlay.show {
            display: block;
        }

        /* Order Status Grid */
        .order-status-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 16px;
        }

        .order-status-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px;
            background: var(--bg-body);
            border-radius: 8px;
        }

        .order-status-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
        }

        .order-status-icon.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .order-status-icon.processing {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        .order-status-icon.completed {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .order-status-icon.cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        .order-status-content {
            flex: 1;
        }

        .order-status-count {
            font-weight: 700;
            font-size: 1.2rem;
        }

        .order-status-label {
            font-size: 0.8rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="overlay" id="overlay" onclick="closeSidebar()"></div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <i class="ph ph-user-circle"></i>
                </div>
                <span>User Portal</span>
            </div>
        </div>

        <div class="sidebar-menu">
            <ul>
                <li class="menu-item">
                    <a href="user_dashboard.php" class="menu-link active">
                        <i class="ph ph-squares-four menu-icon"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="user_profile.php" class="menu-link">
                        <i class="ph ph-user menu-icon"></i>
                        <span>My Profile</span>
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="user_orders.php" class="menu-link">
                        <i class="ph ph-shopping-cart menu-icon"></i>
                        <span>My Orders</span>
                        <?php if ($stats['total_orders'] > 0): ?>
                            <span class="menu-badge"><?php echo $stats['total_orders']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="user_wishlist.php" class="menu-link">
                        <i class="ph ph-heart menu-icon"></i>
                        <span>Wishlist</span>
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="user_addresses.php" class="menu-link">
                        <i class="ph ph-map-pin menu-icon"></i>
                        <span>Addresses</span>
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="user_settings.php" class="menu-link">
                        <i class="ph ph-gear menu-icon"></i>
                        <span>Settings</span>
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="user_support.php" class="menu-link">
                        <i class="ph ph-question menu-icon"></i>
                        <span>Help & Support</span>
                    </a>
                </li>
                
                <!-- Admin Links (only show if user is admin) -->
                <?php if ($user['role'] === 'admin'): ?>
                <li class="menu-item" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <div style="color: rgba(255,255,255,0.5); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; padding-left: 16px; margin-bottom: 10px;">
                        Admin
                    </div>
                </li>
                <li class="menu-item">
                    <a href="admin_vendors.php" class="menu-link">
                        <i class="ph ph-storefront menu-icon"></i>
                        <span>Vendors</span>
                        <?php if ($system_stats['total_vendors'] > 0): ?>
                            <span class="menu-badge"><?php echo $system_stats['total_vendors']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="admin_employees.php" class="menu-link">
                        <i class="ph ph-users menu-icon"></i>
                        <span>Employees</span>
                        <?php if ($system_stats['total_employees'] > 0): ?>
                            <span class="menu-badge"><?php echo $system_stats['total_employees']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="admin_customers.php" class="menu-link">
                        <i class="ph ph-user-circle menu-icon"></i>
                        <span>Customers</span>
                        <?php if ($system_stats['total_customers'] > 0): ?>
                            <span class="menu-badge"><?php echo $system_stats['total_customers']; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li class="menu-item">
                    <a href="admin_orders.php" class="menu-link">
                        <i class="ph ph-shopping-cart menu-icon"></i>
                        <span>All Orders</span>
                        <?php if ($total_orders > 0): ?>
                            <span class="menu-badge"><?php echo $total_orders; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="user-profile">
            <div class="user-avatar">
                <?php 
                $initials = strtoupper(substr($user['username'] ?? 'U', 0, 1));
                if (!empty($user['avatar'])): ?>
                    <img src="<?php echo e($user['avatar']); ?>" alt="<?php echo e($user['username']); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div style="display: none;"><?php echo $initials; ?></div>
                <?php else: ?>
                    <?php echo $initials; ?>
                <?php endif; ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo e($user['username']); ?> 
                    <?php if ($user['role'] === 'admin'): ?>
                        <span style="background: var(--primary); color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; margin-left: 5px;">Admin</span>
                    <?php endif; ?>
                </div>
                <div class="user-email"><?php echo e($user['email']); ?></div>
            </div>
            <a href="logout.php" class="logout-btn" title="Logout">
                <i class="ph ph-sign-out" style="font-size: 1.2rem;"></i>
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <i class="ph ph-list"></i>
                </button>
                <h1 class="page-title">Dashboard</h1>
            </div>

            <div class="header-right">
                <div style="position: relative;">
                    <button class="notification-btn" onclick="toggleNotifications()" id="notificationBtn">
                        <i class="ph ph-bell" style="font-size: 1.3rem;"></i>
                        <?php if (count(array_filter($notifications, fn($n) => !$n['is_read'])) > 0): ?>
                            <span class="notification-badge"></span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="ph ph-bell"></i>
                                Notifications
                            </div>
                            <button class="btn-view" style="padding: 6px 12px;" onclick="markAllAsRead()">
                                Mark all as read
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if (count($notifications) > 0): ?>
                                <div class="notification-list">
                                    <?php foreach ($notifications as $notification): ?>
                                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                        <div class="notification-icon <?php echo $notification['type'] ?? 'info'; ?>">
                                            <?php 
                                            $icon = 'ph ph-info';
                                            if ($notification['type'] === 'success') $icon = 'ph ph-check-circle';
                                            if ($notification['type'] === 'warning') $icon = 'ph ph-warning-circle';
                                            if ($notification['type'] === 'order') $icon = 'ph ph-package';
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?php echo e($notification['title']); ?></div>
                                            <div class="notification-message"><?php echo e($notification['message']); ?></div>
                                            <div class="notification-time">
                                                <?php echo formatDateTime($notification['created_at']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="ph ph-bell-slash"></i>
                                    </div>
                                    <div class="empty-title">No notifications</div>
                                    <div class="empty-description">
                                        You're all caught up! No new notifications.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <a href="user_notifications.php" class="btn-view" style="display: block; text-align: center;">
                                View All Notifications
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <div class="page-content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div class="welcome-content">
                    <h1><?php echo getTimeGreeting(); ?>, <?php echo e($user['username']); ?>! 👋</h1>
                    <p>Welcome back to your dashboard. Here's what's happening with your account today.</p>
                </div>
                <div class="welcome-icon">
                    <i class="ph ph-chart-line-up"></i>
                </div>
            </div>

            <!-- System Statistics Section -->
            <div class="system-stats">
                <h2 class="stats-title">
                    <i class="ph ph-chart-bar"></i>
                    <?php echo $user['role'] === 'admin' ? 'System Overview' : 'Your Overview'; ?>
                </h2>
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <?php if ($user['role'] === 'admin'): ?>
                        <!-- Admin View: System Statistics -->
                        <!-- Total Vendors -->
                        <div class="stat-card purple">
                            <div class="stat-icon">
                                <i class="ph ph-storefront"></i>
                            </div>
                            <div class="stat-value" id="vendorCount"><?php echo e($system_stats['total_vendors'] ?? 0); ?></div>
                            <div class="stat-label">Total Vendors</div>
                            
                            <div class="stat-details">
                                <div class="stat-detail">
                                    <span class="detail-value detail-active"><?php echo $system_stats['vendor_stats']['active'] ?? 0; ?></span>
                                    <span class="detail-label">Active</span>
                                </div>
                                <div class="stat-detail">
                                    <span class="detail-value detail-inactive"><?php echo $system_stats['vendor_stats']['inactive'] ?? 0; ?></span>
                                    <span class="detail-label">Inactive</span>
                                </div>
                            </div>
                            
                            <div class="mini-progress">
                                <div class="mini-progress-fill progress-green" style="width: <?php echo $system_stats['vendor_stats']['total'] > 0 ? (($system_stats['vendor_stats']['active'] / $system_stats['vendor_stats']['total']) * 100) : 0; ?>%"></div>
                            </div>
                            
                            <div class="stat-change positive">
                                <i class="ph ph-arrow-up" style="margin-right: 4px;"></i>
                                <?php echo round(($system_stats['vendor_stats']['active'] / max($system_stats['vendor_stats']['total'], 1)) * 100); ?>% active
                            </div>
                        </div>
                        
                        <!-- Total Employees -->
                        <div class="stat-card teal">
                            <div class="stat-icon">
                                <i class="ph ph-users"></i>
                            </div>
                            <div class="stat-value" id="employeeCount"><?php echo e($system_stats['total_employees'] ?? 0); ?></div>
                            <div class="stat-label">Total Employees</div>
                            
                            <div class="stat-details">
                                <div class="stat-detail">
                                    <span class="detail-value detail-active"><?php echo $system_stats['employee_stats']['active'] ?? 0; ?></span>
                                    <span class="detail-label">Active</span>
                                </div>
                                <div class="stat-detail">
                                    <span class="detail-value detail-inactive"><?php echo $system_stats['employee_stats']['inactive'] ?? 0; ?></span>
                                    <span class="detail-label">Inactive</span>
                                </div>
                            </div>
                            
                            <div class="mini-progress">
                                <div class="mini-progress-fill progress-green" style="width: <?php echo $system_stats['employee_stats']['total'] > 0 ? (($system_stats['employee_stats']['active'] / $system_stats['employee_stats']['total']) * 100) : 0; ?>%"></div>
                            </div>
                            
                            <div class="stat-change positive">
                                <i class="ph ph-arrow-up" style="margin-right: 4px;"></i>
                                <?php echo round(($system_stats['employee_stats']['active'] / max($system_stats['employee_stats']['total'], 1)) * 100); ?>% active
                            </div>
                        </div>
                        
                        <!-- Total Customers -->
                        <div class="stat-card pink">
                            <div class="stat-icon">
                                <i class="ph ph-user-circle"></i>
                            </div>
                            <div class="stat-value" id="customerCount"><?php echo e($system_stats['total_customers'] ?? 0); ?></div>
                            <div class="stat-label">Total Customers</div>
                            
                            <div class="stat-details">
                                <div class="stat-detail">
                                    <span class="detail-value detail-active"><?php echo $system_stats['customer_stats']['active'] ?? 0; ?></span>
                                    <span class="detail-label">Active</span>
                                </div>
                                <div class="stat-detail">
                                    <span class="detail-value detail-inactive"><?php echo $system_stats['customer_stats']['inactive'] ?? 0; ?></span>
                                    <span class="detail-label">Inactive</span>
                                </div>
                            </div>
                            
                            <div class="mini-progress">
                                <div class="mini-progress-fill progress-green" style="width: <?php echo $system_stats['customer_stats']['total'] > 0 ? (($system_stats['customer_stats']['active'] / $system_stats['customer_stats']['total']) * 100) : 0; ?>%"></div>
                            </div>
                            
                            <div class="stat-change positive">
                                <i class="ph ph-arrow-up" style="margin-right: 4px;"></i>
                                <?php echo round(($system_stats['customer_stats']['active'] / max($system_stats['customer_stats']['total'], 1)) * 100); ?>% active
                            </div>
                        </div>
                        
                        <!-- Total Orders -->
                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i class="ph ph-shopping-cart"></i>
                            </div>
                            <div class="stat-value" id="totalOrders"><?php echo e($total_orders); ?></div>
                            <div class="stat-label">Total Orders</div>
                            
                            <div class="order-status-grid">
                                <div class="order-status-item">
                                    <div class="order-status-icon pending">
                                        <i class="ph ph-clock"></i>
                                    </div>
                                    <div class="order-status-content">
                                        <div class="order-status-count"><?php echo $system_stats['order_stats']['pending'] ?? 0; ?></div>
                                        <div class="order-status-label">Pending</div>
                                    </div>
                                </div>
                                <div class="order-status-item">
                                    <div class="order-status-icon processing">
                                        <i class="ph ph-gear"></i>
                                    </div>
                                    <div class="order-status-content">
                                        <div class="order-status-count"><?php echo $system_stats['order_stats']['processing'] ?? 0; ?></div>
                                        <div class="order-status-label">Processing</div>
                                    </div>
                                </div>
                                <div class="order-status-item">
                                    <div class="order-status-icon completed">
                                        <i class="ph ph-check-circle"></i>
                                    </div>
                                    <div class="order-status-content">
                                        <div class="order-status-count"><?php echo $system_stats['order_stats']['completed'] ?? 0; ?></div>
                                        <div class="order-status-label">Completed</div>
                                    </div>
                                </div>
                                <div class="order-status-item">
                                    <div class="order-status-icon cancelled">
                                        <i class="ph ph-x-circle"></i>
                                    </div>
                                    <div class="order-status-content">
                                        <div class="order-status-count"><?php echo $system_stats['order_stats']['cancelled'] ?? 0; ?></div>
                                        <div class="order-status-label">Cancelled</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Today's Orders -->
                        <div class="stat-card orange">
                            <div class="stat-icon">
                                <i class="ph ph-calendar-check"></i>
                            </div>
                            <div class="stat-value" id="todayOrders"><?php echo e($system_stats['today_orders'] ?? 0); ?></div>
                            <div class="stat-label">Today's Orders</div>
                            <div class="stat-details">
                                <div class="stat-detail">
                                    <span class="detail-value"><?php echo formatCurrency($system_stats['today_revenue'] ?? 0); ?></span>
                                    <span class="detail-label">Revenue</span>
                                </div>
                            </div>
                            <div class="stat-change <?php echo ($system_stats['today_orders'] ?? 0) > 0 ? 'positive' : 'negative'; ?>">
                                <?php if (($system_stats['today_orders'] ?? 0) > 0): ?>
                                    <i class="ph ph-arrow-up" style="margin-right: 4px;"></i>
                                    Good day!
                                <?php else: ?>
                                    <i class="ph ph-arrow-down" style="margin-right: 4px;"></i>
                                    No orders yet
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Monthly Revenue -->
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="ph ph-currency-dollar"></i>
                            </div>
                            <div class="stat-value" id="monthlyRevenue"><?php echo formatCurrency($system_stats['monthly_revenue'] ?? 0); ?></div>
                            <div class="stat-label">Monthly Revenue</div>
                            <div class="stat-change positive">
                                <i class="ph ph-arrow-up" style="margin-right: 4px;"></i>
                                15% from last month
                            </div>
                        </div>
                        
                    <?php else: ?>
                        <!-- Regular User View: Personal Statistics -->
                        <!-- Total Spent -->
                        <div class="stat-card primary">
                            <div class="stat-icon">
                                <i class="ph ph-currency-dollar"></i>
                            </div>
                            <div class="stat-value"><?php echo formatCurrency($stats['total_spent'] ?? 0); ?></div>
                            <div class="stat-label">Total Spent</div>
                            <div class="stat-change positive">
                                <i class="ph ph-arrow-up" style="margin-right: 4px;"></i>
                                12% from last month
                            </div>
                        </div>
                        
                        <!-- Total Orders -->
                        <div class="stat-card success">
                            <div class="stat-icon">
                                <i class="ph ph-package"></i>
                            </div>
                            <div class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></div>
                            <div class="stat-label">Total Orders</div>
                            <div class="stat-change positive">
                                <i class="ph ph-arrow-up" style="margin-right: 4px;"></i>
                                3 new this month
                            </div>
                        </div>
                        
                        <!-- Pending Orders -->
                        <div class="stat-card warning">
                            <div class="stat-icon">
                                <i class="ph ph-clock"></i>
                            </div>
                            <?php 
                            // Fetch pending orders for user
                            $stmt = $pdo->prepare("SELECT COUNT(*) as pending_orders FROM orders WHERE user_id = ? AND status = 'pending'");
                            $stmt->execute([$user_id]);
                            $pending_orders = $stmt->fetchColumn();
                            ?>
                            <div class="stat-value"><?php echo $pending_orders; ?></div>
                            <div class="stat-label">Pending Orders</div>
                            <div class="stat-change negative">
                                <i class="ph ph-clock" style="margin-right: 4px;"></i>
                                Needs attention
                            </div>
                        </div>
                        
                        <!-- Account Status -->
                        <div class="stat-card info">
                            <div class="stat-icon">
                                <i class="ph ph-user-circle"></i>
                            </div>
                            <div class="stat-value"><?php echo ucfirst($user['status'] ?? 'Active'); ?></div>
                            <div class="stat-label">Account Status</div>
                            <div class="stat-change positive">
                                <i class="ph ph-check-circle" style="margin-right: 4px;"></i>
                                Verified
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="content-grid">
                <!-- Left Column -->
                <div>
                    <?php if ($user['role'] === 'admin' && count($todays_orders) > 0): ?>
                        <!-- Today's Orders (Admin View) -->
                        <div class="card" style="margin-bottom: 24px;">
                            <div class="card-header">
                                <div class="card-title">
                                    <i class="ph ph-calendar-check"></i>
                                    Today's Orders
                                </div>
                                <a href="admin_orders.php?filter=today" class="btn-view">
                                    View All Today
                                </a>
                            </div>
                            <div class="card-body">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Time</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($todays_orders as $order): ?>
                                        <tr>
                                            <td>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                            <td><?php echo e($order['customer_name'] ?? 'Guest'); ?></td>
                                            <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('h:i A', strtotime($order['created_at'])); ?></td>
                                            <td>
                                                <a href="admin_order_details.php?id=<?php echo $order['id']; ?>" class="btn-view">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Recent Orders Card -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="ph ph-clock-clockwise"></i>
                                Recent <?php echo $user['role'] === 'admin' ? 'Orders' : 'Your Orders'; ?>
                            </div>
                            <a href="<?php echo $user['role'] === 'admin' ? 'admin_orders.php' : 'user_orders.php'; ?>" class="btn-view">
                                View All
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($recent_purchases) > 0): ?>
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Date</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_purchases as $order): ?>
                                        <tr>
                                            <td>#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                            <td><?php echo formatDate($order['created_at']); ?></td>
                                            <td><?php echo $order['items_count'] ?? 0; ?> items</td>
                                            <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                            <td>
                                                <span class="status-badge status-<?php echo $order['status']; ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo $user['role'] === 'admin' ? 'admin_order_details.php' : 'user_order_details.php'; ?>?id=<?php echo $order['id']; ?>" class="btn-view">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="ph ph-shopping-cart"></i>
                                    </div>
                                    <div class="empty-title">No orders yet</div>
                                    <div class="empty-description">
                                        You haven't placed any orders. Start shopping now!
                                    </div>
                                    <a href="shop.php" class="btn-view" style="margin-top: 16px; display: inline-block;">
                                        Start Shopping
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Profile Completion -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="ph ph-user-circle"></i>
                                Profile Completion
                            </div>
                            <span style="font-weight: 600; color: var(--primary);">75%</span>
                        </div>
                        <div class="card-body">
                            <div class="profile-progress">
                                <div class="progress-label">
                                    <span>Profile Information</span>
                                    <span class="progress-value">90%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 90%;"></div>
                                </div>
                                
                                <div class="progress-label" style="margin-top: 20px;">
                                    <span>Shipping Address</span>
                                    <span class="progress-value">60%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 60%;"></div>
                                </div>
                                
                                <div class="progress-label" style="margin-top: 20px;">
                                    <span>Payment Methods</span>
                                    <span class="progress-value">30%</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 30%;"></div>
                                </div>
                            </div>
                            
                            <div style="text-align: center; margin-top: 20px;">
                                <a href="user_profile.php" class="btn-view" style="display: inline-block;">
                                    <i class="ph ph-user-plus" style="margin-right: 8px;"></i>
                                    Complete Your Profile
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- Quick Actions -->
                    <div class="card" style="margin-bottom: 24px;">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="ph ph-lightning"></i>
                                Quick Actions
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <a href="shop.php" class="action-btn">
                                    <div class="action-icon">
                                        <i class="ph ph-shopping-bag"></i>
                                    </div>
                                    <span class="action-label">Shop Now</span>
                                </a>
                                
                                <a href="user_orders.php" class="action-btn">
                                    <div class="action-icon">
                                        <i class="ph ph-package"></i>
                                    </div>
                                    <span class="action-label">Track Order</span>
                                </a>
                                
                                <a href="user_wishlist.php" class="action-btn">
                                    <div class="action-icon">
                                        <i class="ph ph-heart"></i>
                                    </div>
                                    <span class="action-label">Wishlist</span>
                                </a>
                                
                                <a href="user_support.php" class="action-btn">
                                    <div class="action-icon">
                                        <i class="ph ph-headphones"></i>
                                    </div>
                                    <span class="action-label">Get Help</span>
                                </a>
                            </div>
                            
                            <!-- Admin Quick Actions -->
                            <?php if ($user['role'] === 'admin'): ?>
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                                <div style="font-size: 0.9rem; font-weight: 600; color: var(--text-main); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                    <i class="ph ph-wrench" style="color: var(--primary);"></i>
                                    Admin Actions
                                </div>
                                <div class="quick-actions">
                                    <a href="admin_vendors.php" class="action-btn">
                                        <div class="action-icon" style="background: var(--purple);">
                                            <i class="ph ph-storefront"></i>
                                        </div>
                                        <span class="action-label">Manage Vendors</span>
                                    </a>
                                    
                                    <a href="admin_employees.php" class="action-btn">
                                        <div class="action-icon" style="background: var(--teal);">
                                            <i class="ph ph-users"></i>
                                        </div>
                                        <span class="action-label">Manage Employees</span>
                                    </a>
                                    
                                    <a href="admin_customers.php" class="action-btn">
                                        <div class="action-icon" style="background: var(--pink);">
                                            <i class="ph ph-user-circle"></i>
                                        </div>
                                        <span class="action-label">Manage Customers</span>
                                    </a>
                                    
                                    <a href="admin_reports.php" class="action-btn">
                                        <div class="action-icon" style="background: var(--orange);">
                                            <i class="ph ph-chart-bar"></i>
                                        </div>
                                        <span class="action-label">View Reports</span>
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Notifications -->
                    <div class="card">
                        <div class="card-header">
                            <div class="card-title">
                                <i class="ph ph-bell"></i>
                                Recent Notifications
                            </div>
                            <a href="user_notifications.php" class="btn-view">
                                View All
                            </a>
                        </div>
                        <div class="card-body">
                            <?php if (count($notifications) > 0): ?>
                                <div class="notification-list">
                                    <?php 
                                    $recent_notifications = array_slice($notifications, 0, 3);
                                    foreach ($recent_notifications as $notification): 
                                    ?>
                                    <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                                        <div class="notification-icon <?php echo $notification['type'] ?? 'info'; ?>">
                                            <?php 
                                            $icon = 'ph ph-info';
                                            if ($notification['type'] === 'success') $icon = 'ph ph-check-circle';
                                            if ($notification['type'] === 'warning') $icon = 'ph ph-warning-circle';
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title"><?php echo e($notification['title']); ?></div>
                                            <div class="notification-message"><?php echo e($notification['message']); ?></div>
                                            <div class="notification-time">
                                                <?php 
                                                $time = strtotime($notification['created_at']);
                                                $now = time();
                                                $diff = $now - $time;
                                                
                                                if ($diff < 3600) {
                                                    echo floor($diff / 60) . ' minutes ago';
                                                } elseif ($diff < 86400) {
                                                    echo floor($diff / 3600) . ' hours ago';
                                                } else {
                                                    echo date('M d', $time);
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state" style="padding: 20px 0;">
                                    <div class="empty-icon">
                                        <i class="ph ph-bell-slash"></i>
                                    </div>
                                    <div class="empty-title">No notifications</div>
                                    <div class="empty-description">
                                        You're all caught up!
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <div style="margin-bottom: 8px;">
                    &copy; <?php echo date('Y'); ?> User Portal. All rights reserved.
                    <?php if ($user['role'] === 'admin'): ?>
                        <span style="margin-left: 10px; color: var(--primary); font-weight: 500;">
                            <i class="ph ph-shield-check"></i> Admin Mode
                        </span>
                    <?php endif; ?>
                </div>
                <div style="display: flex; justify-content: center; gap: 20px; font-size: 0.85rem;">
                    <a href="privacy.php" style="color: var(--text-muted);">Privacy Policy</a>
                    <a href="terms.php" style="color: var(--text-muted);">Terms of Service</a>
                    <a href="contact.php" style="color: var(--text-muted);">Contact Us</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        // --- Sidebar Toggle for Mobile ---
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('show');
            document.getElementById('overlay').classList.toggle('show');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('show');
            document.getElementById('overlay').classList.remove('show');
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            const mobileToggle = document.querySelector('.mobile-toggle');
            
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('show') && 
                !sidebar.contains(event.target) && 
                !mobileToggle.contains(event.target)) {
                closeSidebar();
            }
        });

        // --- Notification Dropdown ---
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
            
            // Close other dropdowns
            closeAllDropdownsExcept(dropdown);
        }

        function closeAllDropdownsExcept(exceptElement) {
            document.querySelectorAll('.notification-dropdown.show').forEach(dropdown => {
                if (dropdown !== exceptElement) {
                    dropdown.classList.remove('show');
                }
            });
        }

        function markAllAsRead() {
            // In a real app, this would be an AJAX call
            document.querySelectorAll('.notification-item.unread').forEach(item => {
                item.classList.remove('unread');
            });
            
            // Hide notification badge
            const badge = document.querySelector('.notification-badge');
            if (badge) badge.style.display = 'none';
            
            // Show success message
            showToast('All notifications marked as read', 'success');
        }

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const notificationBtn = document.getElementById('notificationBtn');
            const dropdown = document.getElementById('notificationDropdown');
            
            if (!notificationBtn.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.classList.remove('show');
            }
        });

        // --- Toast Notification ---
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                bottom: 20px;
                right: 20px;
                background: ${type === 'success' ? 'var(--success)' : 'var(--primary)'};
                color: white;
                padding: 16px 24px;
                border-radius: 10px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                z-index: 1000;
                display: flex;
                align-items: center;
                gap: 12px;
                animation: slideInRight 0.3s ease;
                max-width: 350px;
            `;
            
            const icon = type === 'success' ? 'ph ph-check-circle' : 'ph ph-info';
            toast.innerHTML = `
                <i class="${icon}" style="font-size: 1.2rem;"></i>
                <div>${message}</div>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // --- Animate Stats ---
        function animateStats() {
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        }

        // --- Animate Counting Numbers ---
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-value');
            
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/[^0-9.-]+/g, ""));
                const suffix = counter.textContent.replace(/[0-9.-]/g, '');
                let current = 0;
                const increment = target / 100;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        clearInterval(timer);
                        counter.textContent = suffix ? '$' + target.toLocaleString() : target.toLocaleString();
                    } else {
                        if (suffix) {
                            counter.textContent = '$' + Math.floor(current).toLocaleString();
                        } else {
                            counter.textContent = Math.floor(current).toLocaleString();
                        }
                    }
                }, 20);
            });
        }

        // --- Initialize on Load ---
        document.addEventListener('DOMContentLoaded', function() {
            animateStats();
            animateCounters();
            
            // Add CSS animations
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideInRight {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
                
                @keyframes slideOutRight {
                    from { transform: translateX(0); opacity: 1; }
                    to { transform: translateX(100%); opacity: 0; }
                }
                
                @keyframes pulse {
                    0% { transform: scale(1); }
                    50% { transform: scale(1.05); }
                    100% { transform: scale(1); }
                }
                
                .stat-card:hover .stat-icon {
                    animation: pulse 0.5s ease;
                }
            `;
            document.head.appendChild(style);
            
            // Auto-refresh notifications every 30 seconds
            setInterval(() => {
                console.log('Checking for new notifications...');
            }, 30000);
        });

        // --- Keyboard Shortcuts ---
        document.addEventListener('keydown', function(e) {
            // Alt + D for dashboard
            if (e.altKey && e.key === 'd') {
                e.preventDefault();
                window.location.href = 'user_dashboard.php';
            }
            
            // Alt + O for orders
            if (e.altKey && e.key === 'o') {
                e.preventDefault();
                window.location.href = 'user_orders.php';
            }
            
            // Alt + P for profile
            if (e.altKey && e.key === 'p') {
                e.preventDefault();
                window.location.href = 'user_profile.php';
            }
            
            // Alt + N for notifications
            if (e.altKey && e.key === 'n') {
                e.preventDefault();
                toggleNotifications();
            }
            
            // Escape to close dropdowns
            if (e.key === 'Escape') {
                document.querySelectorAll('.notification-dropdown.show').forEach(d => {
                    d.classList.remove('show');
                });
                closeSidebar();
            }
            
            // Alt + A for admin (if admin)
            <?php if ($user['role'] === 'admin'): ?>
            if (e.altKey && e.key === 'a') {
                e.preventDefault();
                window.location.href = 'admin_vendors.php';
            }
            <?php endif; ?>
        });

        // --- Responsive Adjustments ---
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                closeSidebar();
            }
        });

        // --- Notification Click Handler ---
        document.addEventListener('click', function(e) {
            const notificationItem = e.target.closest('.notification-item');
            if (notificationItem) {
                notificationItem.classList.remove('unread');
                
                const unreadItems = document.querySelectorAll('.notification-item.unread');
                if (unreadItems.length === 0) {
                    const badge = document.querySelector('.notification-badge');
                    if (badge) badge.style.display = 'none';
                }
            }
        });

        // --- Welcome Message Animation ---
        const welcomeBanner = document.querySelector('.welcome-banner');
        if (welcomeBanner) {
            welcomeBanner.style.opacity = '0';
            welcomeBanner.style.transform = 'translateY(-20px)';
            
            setTimeout(() => {
                welcomeBanner.style.transition = 'all 0.6s ease';
                welcomeBanner.style.opacity = '1';
                welcomeBanner.style.transform = 'translateY(0)';
            }, 300);
        }

        // --- Auto Refresh Stats (Admin Only) ---
        <?php if ($user['role'] === 'admin'): ?>
        function refreshStats() {
            // In a real app, this would be an AJAX call to update stats
            console.log('Refreshing statistics...');
            
            // Simulate stats update
            const todayOrders = document.getElementById('todayOrders');
            if (todayOrders) {
                const current = parseInt(todayOrders.textContent);
                todayOrders.textContent = (current + Math.floor(Math.random() * 2)).toString();
            }
        }
        
        // Refresh stats every 2 minutes
        setInterval(refreshStats, 120000);
        <?php endif; ?>
    </script>
</body>
</html>