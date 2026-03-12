<?php
/**
 * view_subcontractor.php
 * View Subcontractor Details Page
 */

session_start();

// --- 1. AUTHENTICATION & SECURITY ---
session_start();

// Security headers to prevent caching
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page - NO AUTO-LOGIN!
    header("Location: login.php");  // You need to create this file
    exit();
}

// Session timeout - 30 minutes
$timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['login_time'])) {
    $session_life = time() - $_SESSION['login_time'];
    if ($session_life > $timeout) {
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }
}
$_SESSION['login_time'] = time();

// --- LOGOUT HANDLING ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear any auth headers
    header_remove();
    
    // Redirect to login page or home with cache prevention headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Location: login.php");  // Changed from admin_dashboard.php to login.php
    exit(); // IMPORTANT: exit AFTER redirect header
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
    die("Database connection failed: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

function formatPhone($phone) {
    if (empty($phone)) return 'N/A';
    $phone = preg_replace('/[^\d]/', '', $phone);
    if (strlen($phone) === 10) {
        return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    }
    return $phone;
}

function formatDate($date) {
    if (empty($date) || $date === '0000-00-00') return 'N/A';
    return date('M d, Y', strtotime($date));
}

function formatCurrency($amount) {
    if (empty($amount) || $amount == 0) return 'N/A';
    return '$' . number_format(floatval($amount), 2);
}

function formatRating($rating) {
    if (empty($rating) || $rating == 0) return 'No ratings';
    return number_format($rating, 1) . '/5.0';
}

function getStatusBadge($status) {
    $status = strtolower($status);
    $badges = [
        'active' => '<span class="status-badge status-active"><i class="ph ph-check-circle"></i>Active</span>',
        'inactive' => '<span class="status-badge status-inactive"><i class="ph ph-x-circle"></i>Inactive</span>',
        'pending' => '<span class="status-badge status-pending"><i class="ph ph-clock"></i>Pending</span>'
    ];
    return $badges[$status] ?? $badges['pending'];
}

// --- 4. HANDLE DELETE ACTION ---
$message = '';
$msg_type = '';

if (isset($_POST['delete_subcontractor']) && isset($_POST['subcontractor_id'])) {
    $subcontractor_id_to_delete = intval($_POST['subcontractor_id']);
    
    try {
        // First, check if subcontractor exists
        $stmt = $pdo->prepare("SELECT company_name FROM subcontractors WHERE id = ?");
        $stmt->execute([$subcontractor_id_to_delete]);
        $subcontractor_to_delete = $stmt->fetch();
        
        if ($subcontractor_to_delete) {
            // Delete the subcontractor
            $stmt = $pdo->prepare("DELETE FROM subcontractors WHERE id = ?");
            $stmt->execute([$subcontractor_id_to_delete]);
            
            $message = "Subcontractor '{$subcontractor_to_delete['company_name']}' has been deleted successfully!";
            $msg_type = 'success';
            
            // Redirect to management page after 2 seconds
            header("Refresh: 2; URL=subcontractor_management.php");
        } else {
            $message = "Subcontractor not found!";
            $msg_type = 'error';
        }
    } catch (Exception $e) {
        $message = "Error deleting subcontractor: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// --- 5. GET SUBCONTRACTOR DETAILS ---
$subcontractor_id = null;
$subcontractor = null;
$error = '';

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $subcontractor_id = intval($_GET['id']);
    
    try {
        // Get subcontractor details
        $stmt = $pdo->prepare("SELECT * FROM subcontractors WHERE id = ?");
        $stmt->execute([$subcontractor_id]);
        $subcontractor = $stmt->fetch();
        
        if (!$subcontractor) {
            $error = "Subcontractor not found.";
        }
    } catch (Exception $e) {
        $error = "Error fetching subcontractor: " . $e->getMessage();
    }
} else {
    header("Location: subcontractor_management.php");
    exit;
}

// --- 6. GET RELATED DATA (for stats) ---
$work_order_count = 0;
$total_payments = 0;

if ($subcontractor) {
    try {
        // Get work order count
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM work_orders WHERE subcontractor_id = ?");
        $stmt->execute([$subcontractor_id]);
        $result = $stmt->fetch();
        $work_order_count = $result['count'] ?? 0;
        
        // Get total payments (if payments table exists)
        $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM payments WHERE subcontractor_id = ?");
        $stmt->execute([$subcontractor_id]);
        $result = $stmt->fetch();
        $total_payments = $result['total'] ?? 0;
        
    } catch (Exception $e) {
        // Tables might not exist, that's okay
    }
}

// --- 7. GET USER PHOTO ---
$userPhoto = '';
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
        $userPhoto = $userData['avatar'] ?? '';
    } catch (Exception $e) {
        $userPhoto = '';
    }
}

// --- 8. GET RECENT ACTIVITY ---
$recent_activity = [];
if ($subcontractor) {
    try {
        // Get recent updates (from subcontractors table itself)
        $stmt = $pdo->prepare("
            SELECT 
                'profile_update' as type,
                'Profile Updated' as description,
                updated_at as date
            FROM subcontractors 
            WHERE id = ?
            UNION ALL
            SELECT 
                'registration' as type,
                'Registered' as description,
                created_at as date
            FROM subcontractors 
            WHERE id = ?
            ORDER BY date DESC
            LIMIT 5
        ");
        $stmt->execute([$subcontractor_id, $subcontractor_id]);
        $recent_activity = $stmt->fetchAll();
    } catch (Exception $e) {
        // Ignore error
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($subcontractor ? $subcontractor['company_name'] . ' | Subcontractor Details' : 'Subcontractor Not Found'); ?> | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        /* --- CSS Variables & Reset --- */
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338ca;
            --bg-body: #F3F4F6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --sidebar-width: 280px;
            --sidebar-bg: #111827;
            --sidebar-text: #E5E7EB;
            --header-height: 64px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 12px;
            --shadow: 0 2px 8px rgba(0,0,0,0.04);
            --shadow-hover: 0 8px 25px rgba(0,0,0,0.08);
            --color-success: #10B981;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;
            --color-info: #3B82F6;
        }

        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --sidebar-bg: #020617;
            --primary: #6366f1;
            --primary-hover: #818cf8;
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
            height: 100vh; 
            display: flex; 
            overflow: hidden; 
        }
        
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button { font-family: inherit; cursor: pointer; }

        /* --- Sidebar & Layout Logic --- */
        body.sidebar-collapsed { --sidebar-width: 80px; }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            transition: width var(--transition), transform var(--transition);
            z-index: 50;
            flex-shrink: 0;
            white-space: nowrap;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            gap: 12px;
            overflow: hidden;
        }

        body.sidebar-collapsed .logo-text,
        body.sidebar-collapsed .link-text,
        body.sidebar-collapsed .arrow-icon { display: none; opacity: 0; }
        
        body.sidebar-collapsed .sidebar-header { justify-content: center; padding: 0; }
        body.sidebar-collapsed .menu-link { justify-content: center; padding: 12px 0; }
        body.sidebar-collapsed .link-content { gap: 0; }
        body.sidebar-collapsed .menu-icon { font-size: 1.5rem; margin: 0; }
        body.sidebar-collapsed .submenu { display: none !important; }

        .sidebar-menu { 
            padding: 20px 12px; 
            overflow-y: auto; 
            overflow-x: hidden; 
            flex: 1; 
        }
        
        .menu-item { 
            margin-bottom: 4px; 
        }
        
        .menu-link {
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            padding: 12px 16px; 
            border-radius: 8px;
            color: rgba(255,255,255,0.7); 
            cursor: pointer; 
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        
        .menu-link:hover, .menu-link.active { 
            background-color: rgba(255,255,255,0.1); 
            color: #fff; 
            transform: translateX(2px);
        }
        
        .link-content { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        
        .menu-icon { 
            font-size: 1.2rem; 
            min-width: 24px; 
            text-align: center; 
            transition: transform 0.2s ease;
        }
        
        .arrow-icon { 
            transition: transform 0.3s ease; 
            font-size: 0.8rem; 
            opacity: 0.7; 
        }

        .submenu {
            max-height: 0; 
            overflow: hidden; 
            transition: max-height 0.3s ease-in-out; 
            padding-left: 12px;
        }
        
        .menu-item.open > .submenu { 
            max-height: 500px; 
        }
        
        .menu-item.open > .menu-link .arrow-icon { 
            transform: rotate(180deg); 
        }
        
        .menu-item.open > .menu-link { 
            color: #fff; 
        }
        
        .submenu-link {
            display: block; 
            padding: 10px 16px 10px 42px;
            color: rgba(255,255,255,0.5); 
            font-size: 0.9rem; 
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .submenu-link:hover { 
            color: #fff; 
            background: rgba(255,255,255,0.05); 
            transform: translateX(2px);
        }

        /* --- Main Content --- */
        .main-content { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
            position: relative; 
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
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .toggle-btn { 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            cursor: pointer; 
            color: var(--text-muted); 
            display: flex; 
            align-items: center;
            transition: all 0.2s ease;
            padding: 8px;
            border-radius: 8px;
        }
        
        .toggle-btn:hover { 
            color: var(--primary); 
            background: var(--bg-body);
            transform: translateY(-1px);
        }

        /* --- Profile Dropdown --- */
        .header-right { 
            display: flex; 
            align-items: center; 
            gap: 24px; 
        }
        
        .profile-container { 
            position: relative; 
        }
        
        .profile-menu { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            cursor: pointer; 
            padding: 8px 12px; 
            border-radius: 12px; 
            transition: all 0.2s ease;
        }
        
        .profile-menu:hover { 
            background-color: var(--bg-body); 
        }
        
        .profile-info { 
            text-align: right; 
            line-height: 1.2; 
        }
        
        .profile-name { 
            font-size: 0.9rem; 
            font-weight: 600; 
            display: block; 
        }
        
        .profile-role { 
            font-size: 0.75rem; 
            color: var(--text-muted); 
        }
        
        .profile-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 12px; 
            object-fit: cover; 
            border: 2px solid var(--border); 
            transition: all 0.2s ease;
        }
        
        .profile-placeholder { 
            width: 42px; 
            height: 42px; 
            border-radius: 12px; 
            background: var(--primary); 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            font-size: 1.1rem;
        }

        .dropdown-menu {
            position: absolute; 
            top: calc(100% + 8px); 
            right: 0; 
            width: 220px;
            background: var(--bg-card); 
            border: 1px solid var(--border);
            border-radius: 12px; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
            padding: 8px; 
            z-index: 1000; 
            display: none; 
            flex-direction: column; 
            gap: 4px;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-menu.show { 
            display: flex; 
        }
        
        .dropdown-item {
            display: flex; 
            align-items: center; 
            gap: 10px; 
            padding: 12px 16px;
            font-size: 0.9rem; 
            color: var(--text-main); 
            border-radius: 8px; 
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover { 
            background-color: var(--bg-body); 
            color: var(--primary); 
            transform: translateX(2px);
        }
        
        .dropdown-item.danger:hover { 
            background-color: rgba(239, 68, 68, 0.1); 
            color: #ef4444; 
        }

        /* --- Scrollable Content --- */
        .scrollable { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }
        
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 32px; 
            flex-wrap: wrap; 
            gap: 16px; 
        }
        
        .page-title {
            font-size: 1.6rem; 
            font-weight: 700; 
            margin-bottom: 4px;
        }
        
        .page-subtitle {
            color: var(--text-muted); 
            font-size: 0.9rem; 
            line-height: 1.4;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            color: var(--color-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            color: var(--color-danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert i {
            font-size: 1.2rem;
        }

        /* Error Message */
        .error-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 40px;
            text-align: center;
            border: 1px solid var(--border);
            margin: 20px 0;
        }
        
        .error-icon {
            font-size: 3rem;
            color: var(--color-danger);
            margin-bottom: 16px;
        }
        
        .error-title {
            font-size: 1.5rem;
            margin-bottom: 12px;
            color: var(--text-main);
        }
        
        .error-message {
            color: var(--text-muted);
            margin-bottom: 24px;
        }

        /* Subcontractor Header */
        .subcontractor-header {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .sub-avatar {
            width: 80px;
            height: 80px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--primary), #7c3aed);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .sub-info {
            flex: 1;
        }
        
        .sub-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sub-id {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: normal;
        }
        
        .sub-meta {
            display: flex;
            gap: 16px;
            color: var(--text-muted);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }
        
        .sub-meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .sub-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-success);
        }
        
        .status-inactive {
            background: rgba(107, 114, 128, 0.1);
            color: var(--text-muted);
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--color-warning);
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .stat-content {
            flex: 1;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 4px;
        }

        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .details-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.05) 0%, rgba(79, 70, 229, 0.02) 100%);
        }
        
        .card-title {
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 24px;
            flex: 1;
        }
        
        .info-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        .info-value {
            font-size: 0.95rem;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-value.empty {
            color: var(--text-muted);
            font-style: italic;
        }

        /* Recent Activity */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }
        
        .activity-item:hover {
            background: var(--bg-body);
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .activity-date {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Notes Section */
        .notes-content {
            background: var(--bg-body);
            padding: 16px;
            border-radius: 8px;
            min-height: 120px;
            white-space: pre-wrap;
            font-size: 0.9rem;
            line-height: 1.5;
            color: var(--text-main);
        }
        
        .notes-content.empty {
            color: var(--text-muted);
            font-style: italic;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        
        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-body);
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: var(--color-danger);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }
        
        .btn-success {
            background: var(--color-success);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-success:hover {
            background: #0da271;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius);
            width: 90%;
            max-width: 500px;
            border: 1px solid var(--border);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, rgba(239, 68, 68, 0.05) 0%, rgba(239, 68, 68, 0.02) 100%);
        }
        
        .modal-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--color-danger);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: var(--bg-body);
            color: var(--text-main);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Overlay */
        .overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            backdrop-filter: blur(2px);
            z-index: 45; 
            display: none; 
            animation: fadeIn 0.3s ease;
        }
        
        /* Theme Toggle */
        #themeToggle {
            background: var(--bg-card);
            border: 1px solid var(--border);
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        #themeToggle:hover {
            transform: rotate(15deg);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Scrollbar Styling */
        .scrollable::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollable::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .scrollable::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }
        
        .scrollable::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .top-header {
                padding: 0 20px;
            }
            
            .scrollable {
                padding: 20px;
            }
            
            .sidebar { 
                position: fixed; 
                left: -280px; 
                height: 100%; 
                top: 0;
            }
            
            body.mobile-open .sidebar { 
                transform: translateX(280px); 
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.3);
            }
            
            body.mobile-open .overlay { 
                display: block; 
            }
            
            .logo-text, .link-text, .arrow-icon { 
                display: inline !important; 
                opacity: 1 !important; 
            }
            
            .sidebar-header { 
                justify-content: flex-start !important; 
                padding: 0 24px !important; 
            }
            
            .profile-info { 
                display: none; 
            }
            
            .subcontractor-header {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }
            
            .sub-meta {
                justify-content: center;
            }
            
            .sub-actions {
                width: 100%;
                justify-content: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .card-body {
                padding: 16px;
            }
            
            .modal-content {
                width: 95%;
                margin: 20px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .sub-meta {
                flex-direction: column;
                gap: 8px;
                align-items: flex-start;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-footer .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="ph ph-trash"></i>
                    Confirm Deletion
                </h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteCompanyName"></strong>?</p>
                <p style="color: var(--color-danger); margin-top: 12px; font-size: 0.9rem;">
                    <i class="ph ph-warning"></i> 
                    This action cannot be undone. All subcontractor data will be permanently deleted.
                </p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">
                    <i class="ph ph-x"></i>
                    Cancel
                </button>
                <form method="POST" action="" id="deleteForm" style="display: inline;">
                    <input type="hidden" name="delete_subcontractor" value="1">
                    <input type="hidden" name="subcontractor_id" id="deleteSubcontractorId" value="">
                    <button type="submit" class="btn btn-danger">
                        <i class="ph ph-trash"></i>
                        Delete Subcontractor
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Subcontractor Details</span>
                </div>
            </div>

            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($_SESSION['username']); ?></span>
                            <span class="profile-role"><?php echo ucfirst(e($_SESSION['role'])); ?></span>
                        </div>
                        <?php if (!empty($userPhoto)): ?>
                            <img src="<?php echo e($userPhoto); ?>" alt="Profile" class="profile-img">
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="admin_dashboard.php" class="dropdown-item">
                            <i class="ph ph-house" style="font-size: 1.1rem;"></i> 
                            <span>Dashboard</span>
                        </a>
                        <a href="profile_settings.php" class="dropdown-item">
                            <i class="ph ph-user-gear" style="font-size: 1.1rem;"></i> 
                            <span>Profile Settings</span>
                        </a>
                        <div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>
                        <a href="?action=logout" class="dropdown-item danger">
                            <i class="ph ph-sign-out" style="font-size: 1.1rem;"></i> 
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="scrollable">
            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type; ?>">
                    <i class="ph <?php echo $msg_type == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <?php if($error): ?>
                <!-- Error Message -->
                <div class="error-container">
                    <div class="error-icon">
                        <i class="ph ph-warning-circle"></i>
                    </div>
                    <h2 class="error-title">Subcontractor Not Found</h2>
                    <p class="error-message"><?php echo e($error); ?></p>
                    <a href="subcontractor_management.php" class="btn btn-primary">
                        <i class="ph ph-arrow-left"></i>
                        Back to Subcontractor List
                    </a>
                </div>
            <?php elseif($subcontractor): ?>
                <!-- Subcontractor Header -->
                <div class="subcontractor-header">
                    <div class="sub-avatar">
                        <?php echo strtoupper(substr($subcontractor['company_name'], 0, 1)); ?>
                    </div>
                    <div class="sub-info">
                        <h1 class="sub-name">
                            <?php echo e($subcontractor['company_name']); ?>
                            <span class="sub-id">ID: #<?php echo $subcontractor['id']; ?></span>
                        </h1>
                        <div class="sub-meta">
                            <span class="sub-meta-item">
                                <i class="ph ph-user"></i>
                                <?php echo e($subcontractor['contact_person']); ?>
                            </span>
                            <span class="sub-meta-item">
                                <i class="ph ph-envelope"></i>
                                <?php echo e($subcontractor['email']); ?>
                            </span>
                            <span class="sub-meta-item">
                                <i class="ph ph-phone"></i>
                                <?php echo formatPhone($subcontractor['phone']); ?>
                            </span>
                            <span class="sub-meta-item">
                                <i class="ph ph-calendar"></i>
                                Joined: <?php echo formatDate($subcontractor['registration_date']); ?>
                            </span>
                            <span class="sub-meta-item">
                                <?php echo getStatusBadge($subcontractor['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="sub-actions">
                        <a href="edit_subcontractor.php?id=<?php echo $subcontractor_id; ?>" class="btn btn-primary">
                            <i class="ph ph-pencil-simple"></i>
                            Edit
                        </a>
                        <a href="subcontractor_management.php" class="btn btn-secondary">
                            <i class="ph ph-arrow-left"></i>
                            Back to List
                        </a>
                        <button onclick="openDeleteModal(<?php echo $subcontractor_id; ?>, '<?php echo e($subcontractor['company_name']); ?>')" class="btn btn-danger">
                            <i class="ph ph-trash"></i>
                            Delete
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);">
                            <i class="ph ph-currency-dollar"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatCurrency($subcontractor['project_rate']); ?></div>
                            <div class="stat-label">Project Rate</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: var(--color-success);">
                            <i class="ph ph-clipboard-text"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $work_order_count; ?></div>
                            <div class="stat-label">Work Orders</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: var(--color-warning);">
                            <i class="ph ph-wallet"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatCurrency($total_payments); ?></div>
                            <div class="stat-label">Total Payments</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon" style="background: rgba(59, 130, 246, 0.1); color: var(--color-info);">
                            <i class="ph ph-star"></i>
                        </div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo formatRating($subcontractor['rating']); ?></div>
                            <div class="stat-label">Rating</div>
                        </div>
                    </div>
                </div>

                <!-- Details Grid -->
                <div class="details-grid">
                    <!-- Company Information -->
                    <div class="details-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="ph ph-buildings"></i>
                                Company Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="info-label">Company Name</span>
                                    <span class="info-value">
                                        <i class="ph ph-buildings"></i>
                                        <?php echo e($subcontractor['company_name']); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Contact Person</span>
                                    <span class="info-value">
                                        <i class="ph ph-user"></i>
                                        <?php echo e($subcontractor['contact_person']); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Specialization</span>
                                    <span class="info-value">
                                        <i class="ph ph-wrench"></i>
                                        <?php echo e($subcontractor['specialization'] ?: 'N/A'); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Tax ID / Business Number</span>
                                    <span class="info-value">
                                        <i class="ph ph-identification-card"></i>
                                        <?php echo e($subcontractor['tax_id'] ?: 'N/A'); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Project Rate</span>
                                    <span class="info-value">
                                        <i class="ph ph-currency-dollar"></i>
                                        <?php echo formatCurrency($subcontractor['project_rate']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="details-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="ph ph-address-book"></i>
                                Contact Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="info-label">Email Address</span>
                                    <span class="info-value">
                                        <i class="ph ph-envelope"></i>
                                        <?php echo e($subcontractor['email']); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Phone Number</span>
                                    <span class="info-value">
                                        <i class="ph ph-phone"></i>
                                        <?php echo formatPhone($subcontractor['phone']); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Address</span>
                                    <span class="info-value">
                                        <i class="ph ph-map-pin"></i>
                                        <?php echo e($subcontractor['address'] ?: 'N/A'); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Registration Date</span>
                                    <span class="info-value">
                                        <i class="ph ph-calendar"></i>
                                        <?php echo formatDate($subcontractor['registration_date']); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Status</span>
                                    <span class="info-value">
                                        <?php echo getStatusBadge($subcontractor['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="details-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="ph ph-note"></i>
                                Notes & Additional Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="notes-content <?php echo empty($subcontractor['notes']) ? 'empty' : ''; ?>">
                                <?php if(empty($subcontractor['notes'])): ?>
                                    <i class="ph ph-note"></i> No notes available.
                                <?php else: ?>
                                    <?php echo nl2br(e($subcontractor['notes'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="details-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="ph ph-gear"></i>
                                System Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="info-list">
                                <div class="info-item">
                                    <span class="info-label">Subcontractor ID</span>
                                    <span class="info-value">
                                        <i class="ph ph-hash"></i>
                                        #<?php echo $subcontractor['id']; ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Created On</span>
                                    <span class="info-value">
                                        <i class="ph ph-calendar-plus"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($subcontractor['created_at'])); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Last Updated</span>
                                    <span class="info-value">
                                        <i class="ph ph-calendar-check"></i>
                                        <?php echo date('M d, Y h:i A', strtotime($subcontractor['updated_at'])); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Rating</span>
                                    <span class="info-value">
                                        <i class="ph ph-star"></i>
                                        <?php echo formatRating($subcontractor['rating']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="details-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="ph ph-clock-counter-clockwise"></i>
                                Recent Activity
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="activity-list">
                                <?php if (!empty($recent_activity)): ?>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-icon" style="background: rgba(79, 70, 229, 0.1); color: var(--primary);">
                                                <i class="ph ph-<?php echo $activity['type'] === 'registration' ? 'user-plus' : 'pencil-simple'; ?>"></i>
                                            </div>
                                            <div class="activity-content">
                                                <div class="activity-title"><?php echo e($activity['description']); ?></div>
                                                <div class="activity-date"><?php echo date('M d, Y h:i A', strtotime($activity['date'])); ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="activity-item">
                                        <div class="activity-icon" style="background: rgba(107, 114, 128, 0.1); color: var(--text-muted);">
                                            <i class="ph ph-info"></i>
                                        </div>
                                        <div class="activity-content">
                                            <div class="activity-title">No recent activity</div>
                                            <div class="activity-date">Activity will appear here</div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="details-card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="ph ph-lightning"></i>
                                Quick Actions
                            </h3>
                        </div>
                        <div class="card-body">
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                                <a href="create_work_order.php?subcontractor=<?php echo $subcontractor_id; ?>" class="btn btn-success">
                                    <i class="ph ph-plus-circle"></i>
                                    Create Work Order
                                </a>
                                <button onclick="recordPayment()" class="btn btn-primary">
                                    <i class="ph ph-wallet"></i>
                                    Record Payment
                                </button>
                                <button onclick="sendEmail()" class="btn btn-secondary">
                                    <i class="ph ph-envelope-simple"></i>
                                    Send Email
                                </button>
                                <button onclick="printDetails()" class="btn btn-secondary">
                                    <i class="ph ph-printer"></i>
                                    Print Details
                                </button>
                                <button onclick="openDeleteModal(<?php echo $subcontractor_id; ?>, '<?php echo e($subcontractor['company_name']); ?>')" class="btn btn-danger">
                                    <i class="ph ph-trash"></i>
                                    Delete Subcontractor
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // --- 1. Sidebar Toggle Logic ---
        const sidebarToggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('overlay');
        const body = document.body;

        function handleSidebarToggle() {
            if (window.innerWidth <= 768) {
                body.classList.toggle('mobile-open');
                body.classList.remove('sidebar-collapsed'); 
            } else {
                body.classList.toggle('sidebar-collapsed');
                if(body.classList.contains('sidebar-collapsed')) {
                    document.querySelectorAll('.menu-item.open').forEach(item => item.classList.remove('open'));
                }
            }
        }

        sidebarToggle.addEventListener('click', handleSidebarToggle);
        overlay.addEventListener('click', () => {
            body.classList.remove('mobile-open');
            closeAllDropdowns();
            closeAllModals();
        });

        // --- 2. Accordion Logic ---
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            const link = item.querySelector('.menu-link');
            if (link && !link.querySelector('a')) {
                link.addEventListener('click', (e) => {
                    if (!link.hasAttribute('href')) {
                        if(body.classList.contains('sidebar-collapsed')) {
                            body.classList.remove('sidebar-collapsed');
                            setTimeout(() => { item.classList.add('open'); }, 100);
                            return;
                        }
                        
                        e.preventDefault();
                        const isOpen = item.classList.contains('open');
                        menuItems.forEach(i => i.classList.remove('open')); 
                        if (!isOpen) item.classList.add('open');
                    }
                });
            }
        });

        // --- 3. Profile Dropdown Logic ---
        function toggleProfileMenu() {
            const dropdown = document.getElementById('profileDropdown');
            const isShown = dropdown.classList.contains('show');
            closeAllDropdowns();
            if (!isShown) dropdown.classList.add('show');
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.remove('show'));
        }

        window.addEventListener('click', function(e) {
            if (!document.getElementById('profileContainer').contains(e.target)) {
                closeAllDropdowns();
            }
        });

        // --- 4. DARK MODE FUNCTIONALITY ---
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        // Check for saved theme preference or use preferred color scheme
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        const currentTheme = localStorage.getItem('theme');
        
        // Set initial theme
        if (currentTheme === 'dark' || (!currentTheme && prefersDarkScheme.matches)) {
            enableDarkMode();
        } else {
            disableDarkMode();
        }
        
        function enableDarkMode() {
            document.documentElement.setAttribute('data-theme', 'dark');
            themeIcon.classList.replace('ph-moon', 'ph-sun');
            localStorage.setItem('theme', 'dark');
        }
        
        function disableDarkMode() {
            document.documentElement.removeAttribute('data-theme');
            themeIcon.classList.replace('ph-sun', 'ph-moon');
            localStorage.setItem('theme', 'light');
        }
        
        themeBtn.addEventListener('click', () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (isDark) {
                disableDarkMode();
            } else {
                enableDarkMode();
            }
        });

        // --- 5. DELETE FUNCTIONALITY ---
        function openDeleteModal(subcontractorId, companyName) {
            document.getElementById('deleteCompanyName').textContent = companyName;
            document.getElementById('deleteSubcontractorId').value = subcontractorId;
            document.getElementById('deleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        function closeAllModals() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            });
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                closeAllModals();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeAllModals();
            }
        });

        // Handle delete form submission
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            const companyName = document.getElementById('deleteCompanyName').textContent;
            if (!confirm(`Are you absolutely sure you want to delete "${companyName}"? This action cannot be undone!`)) {
                e.preventDefault();
                return false;
            }
            
            // Show loading state
            const deleteBtn = this.querySelector('button[type="submit"]');
            const originalText = deleteBtn.innerHTML;
            deleteBtn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Deleting...';
            deleteBtn.disabled = true;
            
            // Allow form submission
            return true;
        });

        // --- 6. Quick Action Functions ---
        function recordPayment() {
            alert('Record Payment functionality would open a modal here.\n\nThis would allow you to record payments made to this subcontractor.');
        }

        function sendEmail() {
            const email = '<?php echo e($subcontractor ? $subcontractor["email"] : ""); ?>';
            const subject = 'Regarding your subcontractor account';
            const body = `Dear <?php echo e($subcontractor ? $subcontractor["contact_person"] : ""); ?>,%0D%0A%0D%0A`;
            
            window.location.href = `mailto:${email}?subject=${subject}&body=${body}`;
        }

        function printDetails() {
            window.print();
        }

        // --- 7. Copy Details Function ---
        function copyDetails() {
            const details = {
                company: '<?php echo e($subcontractor ? $subcontractor["company_name"] : ""); ?>',
                contact: '<?php echo e($subcontractor ? $subcontractor["contact_person"] : ""); ?>',
                email: '<?php echo e($subcontractor ? $subcontractor["email"] : ""); ?>',
                phone: '<?php echo $subcontractor ? formatPhone($subcontractor["phone"]) : ""; ?>',
                address: '<?php echo e($subcontractor ? $subcontractor["address"] : ""); ?>'
            };
            
            const text = `Subcontractor Details:
Company: ${details.company}
Contact: ${details.contact}
Email: ${details.email}
Phone: ${details.phone}
Address: ${details.address}`;
            
            navigator.clipboard.writeText(text)
                .then(() => {
                    // Show success message
                    const originalTitle = document.title;
                    document.title = "✓ Copied! - " + originalTitle;
                    setTimeout(() => {
                        document.title = originalTitle;
                    }, 2000);
                    
                    alert('Subcontractor details copied to clipboard!');
                })
                .catch(err => {
                    console.error('Failed to copy: ', err);
                    alert('Failed to copy details. Please try again.');
                });
        }

        // --- 8. Initialize Tooltips ---
        document.addEventListener('DOMContentLoaded', function() {
            // Add tooltips to all status badges
            document.querySelectorAll('.status-badge').forEach(badge => {
                badge.title = 'Current status: ' + badge.textContent.trim();
            });
            
            // Add copy functionality to email and phone
            document.querySelectorAll('.info-value').forEach(item => {
                if (item.textContent.includes('@') || item.textContent.includes('(')) {
                    item.style.cursor = 'pointer';
                    item.addEventListener('click', function(e) {
                        if (e.target.tagName !== 'A') {
                            const text = this.textContent.trim();
                            navigator.clipboard.writeText(text).then(() => {
                                const originalText = this.innerHTML;
                                this.innerHTML = '<i class="ph ph-check"></i> Copied!';
                                setTimeout(() => {
                                    this.innerHTML = originalText;
                                }, 2000);
                            });
                        }
                    });
                }
            });
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });

        // --- 9. Listen for system theme changes ---
        prefersDarkScheme.addEventListener('change', (e) => {
            const currentTheme = localStorage.getItem('theme');
            if (!currentTheme) {
                if (e.matches) {
                    enableDarkMode();
                } else {
                    disableDarkMode();
                }
            }
        });
    </script>
</body>
</html>