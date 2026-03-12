<?php
/**
 * customer_financials.php
 * Customer Financial Overview with Email Features Only
 */

// Start session once
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers to prevent caching
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check authentication
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
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

// Database connection
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $ex) {
    die("Database connection failed: " . $ex->getMessage());
}

// Helper functions
function e($val) { 
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); 
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function getStatusBadge($status) {
    $status = strtolower($status);
    $colors = [
        'active' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'text' => '#10B981'],
        'inactive' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'text' => '#EF4444'],
        'pending' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'text' => '#F59E0B'],
        'paid' => ['bg' => 'rgba(16, 185, 129, 0.1)', 'text' => '#10B981'],
        'unpaid' => ['bg' => 'rgba(239, 68, 68, 0.1)', 'text' => '#EF4444'],
        'partial' => ['bg' => 'rgba(245, 158, 11, 0.1)', 'text' => '#F59E0B']
    ];
    
    $color = $colors[$status] ?? ['bg' => 'rgba(107, 114, 128, 0.1)', 'text' => '#6B7280'];
    return "<span class='status-badge' style='background: {$color['bg']}; color: {$color['text']}; padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600;'>" . ucfirst($status) . "</span>";
}

// Email Functionality - Fixed with proper headers
if (isset($_POST['send_reminder_email']) && $pdo) {
    $customer_id = intval($_POST['customer_id']);
    $customer_name = trim($_POST['customer_name']);
    $customer_email = trim($_POST['customer_email']);
    $due_amount = floatval($_POST['due_amount']);
    $custom_message = trim($_POST['custom_message'] ?? '');
    
    if ($customer_id > 0 && !empty($customer_email) && filter_var($customer_email, FILTER_VALIDATE_EMAIL) && $due_amount > 0) {
        try {
            // Email content
            $to = $customer_email;
            $subject = "Payment Reminder - Ali Hair Wigs";
            
            $message = "
            <!DOCTYPE html>
            <html>
            <head>
                <title>Payment Reminder - Ali Hair Wigs</title>
                <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: #4F46E5; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #f9f9f9; padding: 30px; }
                    .amount-box { background: #fff; border: 2px solid #4F46E5; padding: 15px; margin: 20px 0; text-align: center; border-radius: 8px; }
                    .due-amount { font-size: 24px; font-weight: bold; color: #EF4444; }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666; text-align: center; }
                    .logo { font-size: 24px; font-weight: bold; color: white; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <div class='logo'>Ali Hair Wigs</div>
                        <h2>Payment Reminder</h2>
                    </div>
                    <div class='content'>
                        <p>Dear <strong>$customer_name</strong>,</p>
                        
                        <p>This is a friendly reminder regarding your outstanding balance with Ali Hair Wigs.</p>
                        
                        <div class='amount-box'>
                            <p style='margin: 0 0 10px 0; font-size: 16px;'>Outstanding Balance:</p>
                            <p class='due-amount' style='margin: 0;'>$" . number_format($due_amount, 2) . "</p>
                        </div>
                        
                        <p>Please make the payment at your earliest convenience to avoid any inconvenience.</p>
                        
                        " . (!empty($custom_message) ? "<p><strong>Note from our team:</strong><br>$custom_message</p>" : "") . "
                        
                        <p>If you have already made the payment, please disregard this email or contact us to update your records.</p>
                        
                        <p>Thank you for your continued business!</p>
                        
                        <p>Sincerely,<br>
                        <strong>Ali Hair Wigs Accounts Department</strong><br>
                        Phone: +123-456-7890<br>
                        Email: contact@alihairwigs.com</p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated email. Please do not reply to this email.</p>
                        <p>&copy; " . date('Y') . " Ali Hair Wigs. All rights reserved.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            // Email headers - Fixed with proper content type and encoding
            $headers = "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "From: Ali Hair Wigs <contact@alihairwigs.com>\r\n";
            $headers .= "Reply-To: contact@alihairwigs.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // Send email
            $mail_sent = mail($to, $subject, $message, $headers);
            
            if ($mail_sent) {
                $email_success = "✓ Payment reminder sent successfully to $customer_name at $customer_email!";
                
                // Log email sent in database
                $log_stmt = $pdo->prepare("
                    INSERT INTO email_logs 
                    (customer_id, customer_name, customer_email, amount, message, sent_by, sent_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $log_stmt->execute([
                    $customer_id,
                    $customer_name,
                    $customer_email,
                    $due_amount,
                    $custom_message,
                    $_SESSION['user_id'] ?? 0
                ]);
                
            } else {
                $email_error = "✗ Email sending failed. Please check server mail configuration.";
            }
            
        } catch (Exception $e) {
            $email_error = "✗ Error: " . $e->getMessage();
        }
    } else {
        $email_error = "✗ Invalid customer information or email address.";
    }
}

// Fetch customers data
$search = $_GET['search'] ?? '';
$filter = $_GET['filter'] ?? 'all';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'name_asc';

try {
    $whereClauses = [];
    $params = [];
    
    // Search filter
    if (!empty($search)) {
        $whereClauses[] = "(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ? OR c.address LIKE ?)";
        $searchTerm = "%$search%";
        array_push($params, $searchTerm, $searchTerm, $searchTerm, $searchTerm);
    }
    
    // Date range filter
    if (!empty($from_date)) {
        $whereClauses[] = "po.order_date >= ?";
        $params[] = $from_date;
    }
    
    if (!empty($to_date)) {
        $whereClauses[] = "po.order_date <= ?";
        $params[] = $to_date;
    }
    
    // Status filter
    if ($filter == 'active') {
        $whereClauses[] = "c.status = 'Active'";
    } elseif ($filter == 'inactive') {
        $whereClauses[] = "c.status = 'Inactive'";
    } elseif ($filter == 'with_due') {
        // Will be handled in HAVING clause
    }
    
    $whereSQL = $whereClauses ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // Build query
    $sql = "
        SELECT 
            c.id,
            c.full_name,
            c.email,
            c.phone,
            c.address,
            c.status,
            c.created_at as customer_since,
            COALESCE(SUM(po.total_amount), 0) as total_amount,
            COALESCE(SUM(po.paid_amount), 0) as paid_amount,
            COALESCE(SUM(po.total_amount - po.paid_amount), 0) as due_amount,
            COUNT(po.id) as total_orders,
            MAX(po.order_date) as last_order_date
        FROM customers c
        LEFT JOIN purchase_orders po ON c.id = po.customer_id
        $whereSQL
        GROUP BY c.id
    ";
    
    // Apply HAVING clause for due filter
    if ($filter == 'with_due') {
        $sql .= " HAVING due_amount > 0";
    } elseif ($filter == 'fully_paid') {
        $sql .= " HAVING due_amount = 0 AND total_amount > 0";
    } elseif ($filter == 'no_orders') {
        $sql .= " HAVING total_amount = 0";
    }
    
    // Sorting
    switch ($sort_by) {
        case 'name_desc': $sql .= " ORDER BY c.full_name DESC"; break;
        case 'due_desc': $sql .= " ORDER BY due_amount DESC"; break;
        case 'due_asc': $sql .= " ORDER BY due_amount ASC"; break;
        case 'total_desc': $sql .= " ORDER BY total_amount DESC"; break;
        case 'last_order': $sql .= " ORDER BY last_order_date DESC"; break;
        case 'newest': $sql .= " ORDER BY c.created_at DESC"; break;
        default: $sql .= " ORDER BY c.full_name ASC";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
    
    // Calculate totals
    $total_customers = count($customers);
    $total_revenue = array_sum(array_column($customers, 'total_amount'));
    $total_paid = array_sum(array_column($customers, 'paid_amount'));
    $total_due = array_sum(array_column($customers, 'due_amount'));
    $active_customers = count(array_filter($customers, fn($c) => $c['status'] == 'Active'));
    $customers_with_due = count(array_filter($customers, fn($c) => $c['due_amount'] > 0));
    
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
    $customers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Due Customer List | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <style>
        /* --- CSS VARIABLES (Light Mode) --- */
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338ca;
            --bg-body: #F3F4F6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --sidebar-bg: #111827;
            --sidebar-text: #E5E7EB;
            --header-height: 64px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-dropdown: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --color-success: #10B981;
            --color-danger: #EF4444;
            --color-warning: #F59E0B;
            --color-info: #0ea5e9;
        }

        /* --- DARK MODE VARIABLES --- */
        [data-theme='dark'] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f3f4f6;
            --text-muted: #94a3b8;
            --border: #334155;
            --sidebar-bg: #020617;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
            --shadow-dropdown: 0 10px 15px -3px rgba(0, 0, 0, 0.7);
            --color-success: #34D399;
            --color-danger: #F87171;
            --color-warning: #FBBF24;
            --color-info: #0ea5e9;
        }

        /* --- RESET --- */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; transition: background 0.3s, color 0.3s; }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }
        ul { list-style: none; }

        /* --- SIDEBAR (From Admin Dashboard) --- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            transition: width var(--transition);
            flex-shrink: 0;
            z-index: 50;
            white-space: nowrap;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        /* Sidebar Header (From Admin Dashboard) */
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
        .sidebar-header i { 
            color: var(--primary); 
            font-size: 1.5rem; 
        }

        /* Collapsed Sidebar Logic (From Admin Dashboard) */
        body.sidebar-collapsed { --sidebar-width: 80px; }
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
        
        .submenu-link:hover, .submenu-link.active { 
            color: #fff; 
            background: rgba(255,255,255,0.1); 
            transform: translateX(2px);
        }

        /* --- MAIN CONTENT --- */
        .main-content { flex: 1; display: flex; flex-direction: column; min-width: 0; }

        /* --- HEADER (From Admin Dashboard) --- */
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

        /* --- PROFILE DROPDOWN (From Admin Dashboard) --- */
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

        /* Dropdown Menu (From Admin Dashboard) */
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

        /* Theme Toggle (From Admin Dashboard) */
        #themeToggle {
            background: var(--bg-card);
            border: 1px solid var(--border);
            width: 40px;
            height: 40px;
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

        /* --- CONTENT AREA --- */
        .content-scroll { flex: 1; overflow-y: auto; padding: 24px; }
        .card { 
            background: var(--bg-card); 
            border-radius: 0.75rem; 
            border: 1px solid var(--border); 
            box-shadow: var(--shadow);
            padding: 28px;
            margin-bottom: 24px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        
        .btn-success {
            background: var(--color-success);
            color: white;
        }
        
        .btn-info {
            background: var(--color-info);
            color: white;
        }
        
        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 6px;
        }
        
        .form-input {
            width: 100%;
            padding: 10px 14px;
            font-size: 0.95rem;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-main);
            transition: all 0.2s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-input[readonly] {
            background: var(--bg-body);
            cursor: not-allowed;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: 0.75rem;
            padding: 24px;
            border: 1px solid var(--border);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            font-size: 1.5rem;
        }
        
        .stat-revenue .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-success);
        }
        
        .stat-paid .stat-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3B82F6;
        }
        
        .stat-due .stat-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--color-danger);
        }
        
        .stat-customers .stat-icon {
            background: rgba(139, 92, 246, 0.1);
            color: #8B5CF6;
        }
        
        .stat-title {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.2;
        }
        
        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-card);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-muted);
            background: var(--bg-body);
            border-bottom: 1px solid var(--border);
        }
        
        td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        
        tr:hover {
            background: var(--bg-body);
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .action-btn.btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .action-btn.btn-info {
            background: var(--color-info);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 0.75rem;
            width: 500px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        /* Alerts */
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: var(--color-success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--color-danger);
        }
        
        /* Toolbar */
        .toolbar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .search-wrap { position: relative; width: 300px; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-input { width: 100%; padding: 10px 12px 10px 36px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text-main); outline: none; }
        .search-input:focus { border-color: var(--primary); }
        
        /* --- MOBILE --- */
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -280px; height: 100%; transition: left 0.3s ease; }
            body.mobile-open .sidebar { left: 0; }
            .logo-text, .link-text, .arrow-icon { 
                display: inline !important; 
                opacity: 1 !important; 
            }
            .sidebar-header { 
                justify-content: flex-start !important; 
                padding: 0 24px !important; 
            }
            .top-header { padding: 0 20px; }
            .content-scroll { padding: 24px 20px; }
            .profile-info { display: none; }
            .stats-grid { grid-template-columns: 1fr; }
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
            z-index: 45; 
            display: none; 
            animation: fadeIn 0.3s ease;
        }
    </style>
</head>
<body id="body">

    <!-- Email Modal -->
    <div class="modal" id="emailModal">
        <form method="POST" action="">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 style="font-weight: 600; color: var(--text-main);">
                        <i class="ph ph-envelope" style="margin-right: 10px;"></i>
                        Send Payment Reminder
                    </h3>
                    <button type="button" class="close-modal" onclick="closeModal('emailModal')" style="background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer;">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if(isset($email_success)): ?>
                        <div class="alert alert-success">
                            <i class="ph ph-check-circle"></i> <?php echo e($email_success); ?>
                        </div>
                    <?php elseif(isset($email_error)): ?>
                        <div class="alert alert-error">
                            <i class="ph ph-warning-circle"></i> <?php echo e($email_error); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <div class="form-label">Customer</div>
                        <input type="text" class="form-input" id="email_customer_name" readonly>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Email Address</div>
                        <input type="email" class="form-input" id="email_customer_email" readonly>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Due Amount</div>
                        <input type="text" class="form-input" id="email_due_amount" readonly>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-label">Custom Message (Optional)</div>
                        <textarea name="custom_message" class="form-input" rows="4" placeholder="Add a personal note to the email..."></textarea>
                    </div>
                    
                    <input type="hidden" name="customer_id" id="email_customer_id">
                    <input type="hidden" name="customer_name" id="email_hidden_name">
                    <input type="hidden" name="customer_email" id="email_hidden_email">
                    <input type="hidden" name="due_amount" id="email_hidden_due">
                    <input type="hidden" name="send_reminder_email" value="1">
                    
                    <div class="alert" style="background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2); color: #3B82F6;">
                        <i class="ph ph-info"></i>
                        Email will be sent from: <strong>contact@alihairwigs.com</strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('emailModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-paper-plane-tilt"></i> Send Email
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="overlay" id="overlay"></div>

    <!-- Sidebar -->
    <?php include 'sidenavbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Header -->
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Due Customer List</span>
                </div>
            </div>

            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($_SESSION['username'] ?? 'Admin'); ?></span>
                            <span class="profile-role"><?php echo e(ucfirst($_SESSION['role'] ?? 'Admin')); ?></span>
                        </div>
                        <?php 
                        // Get user photo from database
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
                        
                        if (!empty($userPhoto)): ?>
                            <img src="<?php echo e($userPhoto); ?>" alt="Profile" class="profile-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="profile-placeholder" style="display: none;">
                                <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                            </div>
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <?php echo strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile_settings.php" class="dropdown-item">
                            <i class="ph ph-user-gear" style="font-size: 1.1rem;"></i> 
                            <span>Profile Settings</span>
                        </a>
                        <div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>
                        <a href="logout.php" class="dropdown-item danger">
                            <i class="ph ph-sign-out" style="font-size: 1.1rem;"></i> 
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Content Area -->
        <div class="content-scroll">
            <div style="margin-bottom: 32px;">
                <h1 style="font-size: 1.6rem; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">Customer Financial Overview</h1>
                <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.4;">
                    View total revenue, due amounts, and send payment reminders
                </p>
            </div>

            <?php if(isset($error)): ?>
                <div class="alert alert-error">
                    <i class="ph ph-warning-circle"></i> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-customers">
                    <div class="stat-icon">
                        <i class="ph ph-users"></i>
                    </div>
                    <div class="stat-title">Total Customers</div>
                    <div class="stat-value"><?php echo $total_customers; ?></div>
                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 8px;">
                        <span style="color: var(--color-success);"><?php echo $active_customers; ?> Active</span> • 
                        <span style="color: var(--color-danger);"><?php echo $customers_with_due; ?> With Due</span>
                    </div>
                </div>
                
                <div class="stat-card stat-revenue">
                    <div class="stat-icon">
                        <i class="ph ph-currency-dollar"></i>
                    </div>
                    <div class="stat-title">Total Revenue</div>
                    <div class="stat-value"><?php echo formatCurrency($total_revenue); ?></div>
                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 8px;">
                        <?php echo array_sum(array_column($customers, 'total_orders')); ?> Total Orders
                    </div>
                </div>
                
                <div class="stat-card stat-paid">
                    <div class="stat-icon">
                        <i class="ph ph-wallet"></i>
                    </div>
                    <div class="stat-title">Total Paid</div>
                    <div class="stat-value"><?php echo formatCurrency($total_paid); ?></div>
                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 8px;">
                        <?php echo $total_revenue > 0 ? number_format(($total_paid / $total_revenue) * 100, 1) : '0'; ?>% Collection Rate
                    </div>
                </div>
                
                <div class="stat-card stat-due">
                    <div class="stat-icon">
                        <i class="ph ph-warning-circle"></i>
                    </div>
                    <div class="stat-title">Total Due</div>
                    <div class="stat-value"><?php echo formatCurrency($total_due); ?></div>
                    <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 8px;">
                        <?php echo $total_revenue > 0 ? number_format(($total_due / $total_revenue) * 100, 1) : '0'; ?>% Due Rate
                    </div>
                </div>
            </div>

            <!-- Search & Filter Card -->
            <div class="card">
                <div class="toolbar">
                    <form method="GET" action="" style="display:flex; width:100%; gap:16px; justify-content:space-between; flex-wrap:wrap;">
                        <div class="search-wrap">
                            <i class="ph ph-magnifying-glass"></i>
                            <input type="text" name="search" class="search-input" placeholder="Search customers..." value="<?php echo e($search); ?>">
                        </div>
                        
                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <input type="date" name="from_date" class="search-input" style="min-width: 150px;" value="<?php echo e($from_date); ?>" placeholder="From Date">
                            <span style="color: var(--text-muted);">to</span>
                            <input type="date" name="to_date" class="search-input" style="min-width: 150px;" value="<?php echo e($to_date); ?>" placeholder="To Date">
                            
                            <select name="filter" class="search-input" style="min-width: 200px;">
                                <option value="all" <?php echo $filter == 'all' ? 'selected' : ''; ?>>All Customers</option>
                                <option value="with_due" <?php echo $filter == 'with_due' ? 'selected' : ''; ?>>Customers with Due</option>
                                <option value="fully_paid" <?php echo $filter == 'fully_paid' ? 'selected' : ''; ?>>Fully Paid</option>
                                <option value="active" <?php echo $filter == 'active' ? 'selected' : ''; ?>>Active Only</option>
                                <option value="inactive" <?php echo $filter == 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                            </select>
                            
                            <select name="sort_by" class="search-input" style="min-width: 200px;">
                                <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Sort by: Name A-Z</option>
                                <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Sort by: Name Z-A</option>
                                <option value="due_desc" <?php echo $sort_by == 'due_desc' ? 'selected' : ''; ?>>Sort by: Highest Due</option>
                                <option value="due_asc" <?php echo $sort_by == 'due_asc' ? 'selected' : ''; ?>>Sort by: Lowest Due</option>
                            </select>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="ph ph-funnel"></i> Apply Filters
                            </button>
                            
                            <?php if($search || $filter != 'all' || $from_date || $to_date): ?>
                                <a href="customer_financials.php" class="btn btn-secondary">
                                    <i class="ph ph-arrows-clockwise"></i> Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Customer Table Card -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 16px;">
                    <div style="display: flex; align-items: center;">
                        <i class="ph ph-currency-circle-dollar" style="color: var(--primary); font-size: 1.2rem; margin-right: 10px;"></i>
                        <h3 style="font-weight: 600; color: var(--text-main);">Customer Financial Details</h3>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button class="btn btn-success" onclick="window.print()">
                            <i class="ph ph-printer"></i> Print Report
                        </button>
                        <button class="btn btn-primary" onclick="exportToCSV()">
                            <i class="ph ph-export"></i> Export CSV
                        </button>
                    </div>
                </div>
                
                <div class="table-container">
                    <?php if(empty($customers)): ?>
                        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                            <i class="ph ph-users" style="font-size: 3rem; margin-bottom: 16px; display: block;"></i>
                            <p>No customers found</p>
                            <?php if($search || $filter != 'all' || $from_date || $to_date): ?>
                                <a href="customer_financials.php" class="btn btn-secondary" style="margin-top: 16px;">
                                    <i class="ph ph-arrows-clockwise"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <table id="financialTable">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Contact Info</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Orders</th>
                                    <th style="text-align: right;">Total Amount</th>
                                    <th style="text-align: right;">Paid Amount</th>
                                    <th style="text-align: right;">Due Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($customers as $customer): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 600; color: var(--text-main); margin-bottom: 4px;">
                                                <?php echo e($customer['full_name']); ?>
                                            </div>
                                            <div style="font-size: 0.8rem; color: var(--text-muted);">
                                                ID: <?php echo $customer['id']; ?>
                                                <?php if($customer['customer_since']): ?>
                                                    • Since <?php echo date('M Y', strtotime($customer['customer_since'])); ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="margin-bottom: 4px;">
                                                <i class="ph ph-envelope-simple" style="margin-right: 6px; color: var(--text-muted); font-size: 0.875rem;"></i>
                                                <?php echo e($customer['email']); ?>
                                            </div>
                                            <?php if($customer['phone']): ?>
                                                <div>
                                                    <i class="ph ph-phone" style="margin-right: 6px; color: var(--text-muted); font-size: 0.875rem;"></i>
                                                    <?php echo e($customer['phone']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo getStatusBadge($customer['status']); ?></td>
                                        <td style="text-align: right; font-weight: 600;"><?php echo $customer['total_orders']; ?></td>
                                        <td style="text-align: right; font-weight: 600;"><?php echo formatCurrency($customer['total_amount']); ?></td>
                                        <td style="text-align: right; color: var(--color-success); font-weight: 600;"><?php echo formatCurrency($customer['paid_amount']); ?></td>
                                        <td style="text-align: right; color: var(--color-danger); font-weight: 600;"><?php echo formatCurrency($customer['due_amount']); ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="customer_view.php?id=<?php echo $customer['id']; ?>" class="action-btn btn-secondary" title="View Details">
                                                    <i class="ph ph-eye"></i> View
                                                </a>
                                                <?php if($customer['due_amount'] > 0 && !empty(trim($customer['email']))): ?>
                                                    <button class="action-btn btn-info" onclick="sendReminder(<?php echo $customer['id']; ?>, '<?php echo e($customer['full_name']); ?>', '<?php echo e($customer['email']); ?>', <?php echo $customer['due_amount']; ?>)" title="Send Due Reminder">
                                                        <i class="ph ph-envelope"></i> Email
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: var(--bg-body); font-weight: 600;">
                                    <td colspan="3" style="text-align: right;">Totals:</td>
                                    <td style="text-align: right;"><?php echo array_sum(array_column($customers, 'total_orders')); ?></td>
                                    <td style="text-align: right;"><?php echo formatCurrency($total_revenue); ?></td>
                                    <td style="text-align: right; color: var(--color-success);"><?php echo formatCurrency($total_paid); ?></td>
                                    <td style="text-align: right; color: var(--color-danger);"><?php echo formatCurrency($total_due); ?></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#financialTable').DataTable({
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
                order: [[0, 'asc']],
                dom: '<"top"f>rt<"bottom"lip><"clear">',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search in table...",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ customers",
                    infoEmpty: "Showing 0 to 0 of 0 entries",
                    infoFiltered: "(filtered from _MAX_ total entries)"
                },
                responsive: true
            });
        });

        // --- Sidebar Logic ---
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
        });

        // --- Accordion Logic ---
        const menuItems = document.querySelectorAll('.has-submenu');
        menuItems.forEach(item => {
            const link = item.querySelector('.menu-link');
            link.addEventListener('click', (e) => {
                if(body.classList.contains('sidebar-collapsed')) {
                    body.classList.remove('sidebar-collapsed');
                    setTimeout(() => { item.classList.add('open'); }, 100);
                    return;
                }
                
                e.preventDefault();
                const isOpen = item.classList.contains('open');
                menuItems.forEach(i => i.classList.remove('open')); 
                if (!isOpen) item.classList.add('open');
            });
        });

        // --- Profile Dropdown Logic ---
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

        // --- Dark Mode Logic ---
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        if(localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            themeIcon.classList.replace('ph-moon', 'ph-sun');
        }

        themeBtn.addEventListener('click', () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if(isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                themeIcon.classList.replace('ph-sun', 'ph-moon');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                themeIcon.classList.replace('ph-moon', 'ph-sun');
            }
        });

        // --- Modal Functions ---
        function sendReminder(customerId, customerName, customerEmail, dueAmount) {
            document.getElementById('email_customer_id').value = customerId;
            document.getElementById('email_hidden_name').value = customerName;
            document.getElementById('email_hidden_email').value = customerEmail;
            document.getElementById('email_hidden_due').value = dueAmount;
            
            document.getElementById('email_customer_name').value = customerName;
            document.getElementById('email_customer_email').value = customerEmail;
            document.getElementById('email_due_amount').value = '$' + parseFloat(dueAmount).toFixed(2);
            
            document.getElementById('emailModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Export to CSV
        function exportToCSV() {
            let csv = [];
            // Add headers
            csv.push(['Customer ID', 'Name', 'Email', 'Phone', 'Status', 'Orders', 'Total Amount', 'Paid Amount', 'Due Amount']);
            
            // Add data
            <?php foreach($customers as $customer): ?>
            csv.push([
                '<?php echo $customer['id']; ?>',
                '<?php echo e($customer['full_name']); ?>',
                '<?php echo e($customer['email']); ?>',
                '<?php echo e($customer['phone']); ?>',
                '<?php echo $customer['status']; ?>',
                '<?php echo $customer['total_orders']; ?>',
                '<?php echo $customer['total_amount']; ?>',
                '<?php echo $customer['paid_amount']; ?>',
                '<?php echo $customer['due_amount']; ?>'
            ]);
            <?php endforeach; ?>
            
            // Convert to CSV string
            const csvContent = csv.map(row => row.join(',')).join('\n');
            
            // Create download link
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'customer_financials_<?php echo date('Y-m-d'); ?>.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Auto-close success messages
        <?php if(isset($email_success)): ?>
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        <?php endif; ?>

        // Escape key to close dropdowns
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
                const modals = document.querySelectorAll('.modal');
                modals.forEach(modal => {
                    if (modal.style.display === 'flex') {
                        modal.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>