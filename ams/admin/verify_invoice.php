<?php
/**
 * verify_invoice.php
 * Verify invoice authenticity by Reference Number.
 * Standalone page with full Sidebar/Header integration.
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
    // In production, log this, don't show raw error to user
    $pdo = null; 
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
function formatCurrency($amount) { return '$' . number_format($amount, 2); }

// --- 4. HANDLE VERIFICATION LOGIC ---
$result = null;
$error = null;
$search_ref = "";
$payment_history = [];
$total_paid = 0;
$due_amount = 0;

if (isset($_POST['verify_btn']) && $pdo) {
    $search_ref = trim($_POST['invoice_ref']);
    
    if (!empty($search_ref)) {
        try {
            // Let's check what tables exist in the database
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            // Check if we have purchase_orders table (based on existing code)
            if (in_array('purchase_orders', $tables)) {
                // First, check the structure of purchase_orders table
                $columns = $pdo->query("DESCRIBE purchase_orders")->fetchAll(PDO::FETCH_COLUMN);
                
                // Build query based on actual columns
                $sql = "SELECT po.*";
                
                // Check if customers table exists and has necessary columns
                if (in_array('customers', $tables)) {
                    $sql .= ", c.full_name, c.phone, c.email";
                }
                
                $sql .= " FROM purchase_orders po";
                
                if (in_array('customers', $tables)) {
                    $sql .= " LEFT JOIN customers c ON po.customer_id = c.id";
                }
                
                $sql .= " WHERE po.reference_no = ?";
            } else {
                // Try to find the actual table with invoice data
                // Based on the image, we're looking for a table with these columns:
                // id, customer_id, reference_no, order_date, total_amount, product_name, paid_amount, status, payment_status, note, created_at
                
                // Search for tables with reference_no column
                $stmt = $pdo->prepare("
                    SELECT TABLE_NAME 
                    FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = ? 
                    AND COLUMN_NAME IN ('reference_no', 'reference')
                ");
                $stmt->execute([$DB_NAME]);
                $ref_tables = $stmt->fetchAll();
                
                if (count($ref_tables) > 0) {
                    // Use the first table found with reference_no
                    $table_name = $ref_tables[0]['TABLE_NAME'];
                    $sql = "SELECT * FROM $table_name WHERE reference_no = ?";
                } else {
                    // Fallback to searching any table with reference-like column
                    $sql = "SELECT * FROM (
                        SELECT NULL as id, NULL as customer_id, NULL as reference_no, 
                               NULL as order_date, NULL as total_amount, NULL as product_name, 
                               NULL as paid_amount, NULL as status, NULL as payment_status, 
                               NULL as note, NULL as created_at 
                        LIMIT 0
                    ) AS dummy WHERE 1=0";
                }
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$search_ref]);
            $result = $stmt->fetch();

            if (!$result) {
                $error = "Invoice #<strong>" . e($search_ref) . "</strong> not found in the system.";
            } else {
                // Get the paid_amount directly from the invoice table
                $total_paid = $result['paid_amount'] ?? 0;
                
                // Try to get payment history if the table exists
                try {
                    if (in_array('payment_history', $tables)) {
                        $payment_history_sql = "SELECT amount, paid_by, paid_at 
                                                FROM payment_history 
                                                WHERE purchase_id = ? 
                                                ORDER BY paid_at DESC";
                        $payment_stmt = $pdo->prepare($payment_history_sql);
                        $payment_stmt->execute([$result['id']]);
                        $payment_history = $payment_stmt->fetchAll();
                    }
                } catch (Exception $e) {
                    // If payment_history table doesn't exist or query fails, just use empty array
                    $payment_history = [];
                }
                
                // Calculate due amount
                $order_total = $result['total_amount'] ?? 0;
                $due_amount = $order_total - $total_paid;
            }
        } catch (Exception $e) {
            // If query fails, try a direct approach
            try {
                // Direct query to find any table with reference number
                $direct_sql = "SELECT * FROM purchase_orders WHERE reference_no = ?";
                $direct_stmt = $pdo->prepare($direct_sql);
                $direct_stmt->execute([$search_ref]);
                $result = $direct_stmt->fetch();
                
                if (!$result) {
                    $error = "Invoice #<strong>" . e($search_ref) . "</strong> not found in the system.";
                } else {
                    // Get the paid_amount directly from the result
                    $total_paid = $result['paid_amount'] ?? 0;
                    $order_total = $result['total_amount'] ?? 0;
                    $due_amount = $order_total - $total_paid;
                }
            } catch (Exception $ex) {
                $error = "System Error: Could not perform verification. " . $ex->getMessage();
            }
        }
    } else {
        $error = "Please enter an Invoice Reference Number.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Invoice | NexusAdmin</title>
    
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
        }

        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --sidebar-bg: #020617;
            --primary: #6366f1;
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

        /* --- Verify Page Specific Styles --- */
        .scrollable { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: flex-start;
            scroll-behavior: smooth;
        }
        
        .verify-card { 
            background: var(--bg-card); 
            width: 100%; 
            max-width: 900px; 
            border-radius: var(--radius); 
            border: 1px solid var(--border); 
            padding: 40px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); 
            text-align: center;
            margin-top: 20px;
        }

        .input-group { 
            margin: 30px 0; 
            display: flex; 
            gap: 10px; 
        }
        
        .form-control { 
            flex: 1; 
            padding: 12px 16px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            font-size: 1rem; 
            background: var(--bg-body); 
            color: var(--text-main); 
            outline: none; 
            transition: 0.2s; 
        }
        
        .form-control:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); 
        }
        
        .btn-verify { 
            background: var(--primary); 
            color: white; 
            padding: 12px 24px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: 0.2s; 
            font-size: 1rem; 
        }
        
        .btn-verify:hover { 
            background: var(--primary-hover); 
        }

        /* Results */
        .result-box { 
            margin-top: 30px; 
            padding: 24px; 
            border-radius: 12px; 
            border: 1px solid var(--border); 
            text-align: left; 
            animation: fadeIn 0.3s ease-in-out; 
        }
        
        .result-success { 
            background: rgba(16, 185, 129, 0.1); 
            border-color: #10B981; 
        }
        
        .result-error { 
            background: rgba(239, 68, 68, 0.1); 
            border-color: #EF4444; 
            color: #B91C1C; 
            text-align: center; 
        }

        .status-badge { 
            display: inline-flex; 
            align-items: center; 
            gap: 6px; 
            padding: 6px 12px; 
            background: #D1FAE5; 
            color: #065F46; 
            border-radius: 20px; 
            font-size: 0.9rem; 
            font-weight: 600; 
            margin-bottom: 16px; 
        }
        
        [data-theme="dark"] .status-badge { 
            background: rgba(16, 185, 129, 0.2); 
            color: #6EE7B7; 
        }

        .detail-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 12px 0; 
            border-bottom: 1px solid var(--border); 
        }
        
        .detail-row:last-child { 
            border-bottom: none; 
        }
        
        .detail-label { 
            color: var(--text-muted); 
            font-size: 0.9rem; 
        }
        
        .detail-value { 
            font-weight: 600; 
            font-size: 1rem; 
            color: var(--text-main); 
        }

        /* Added styles for amount summary cards */
        .amount-summary { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 12px; 
            margin: 20px 0; 
        }
        
        .amount-card { 
            background: var(--bg-body); 
            padding: 16px; 
            border-radius: 8px; 
            text-align: center; 
            border: 1px solid var(--border); 
        }
        
        .amount-card-label { 
            font-size: 0.85rem; 
            color: var(--text-muted); 
            margin-bottom: 8px; 
        }
        
        .amount-card-value { 
            font-size: 1.4rem; 
            font-weight: 700; 
        }
        
        .amount-card.total .amount-card-value { 
            color: var(--primary); 
        }
        
        .amount-card.paid .amount-card-value { 
            color: #10B981; 
        }
        
        .amount-card.due .amount-card-value { 
            color: #EF4444; 
        }

        /* Payment status indicator */
        .payment-status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 4px;
        }
        
        .payment-status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }
        
        .payment-status-dot.paid {
            background-color: #10B981;
        }
        
        .payment-status-dot.partial {
            background-color: #F59E0B;
        }
        
        .payment-status-dot.pending {
            background-color: #EF4444;
        }

        /* Added styles for payment history section */
        .payment-history-section { 
            margin-top: 24px; 
            padding-top: 20px; 
            border-top: 2px solid var(--border); 
        }
        
        .section-title { 
            font-size: 1.1rem; 
            font-weight: 600; 
            margin-bottom: 16px; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            color: var(--text-main); 
        }
        
        .payment-record { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 12px; 
            background: var(--bg-body); 
            border-radius: 8px; 
            margin-bottom: 8px; 
            border: 1px solid var(--border); 
        }
        
        .payment-record:last-child { 
            margin-bottom: 0; 
        }
        
        .payment-info { 
            display: flex; 
            flex-direction: column; 
            gap: 4px; 
        }
        
        .payment-date { 
            font-size: 0.85rem; 
            color: var(--text-muted); 
        }
        
        .payment-by { 
            font-size: 0.9rem; 
            color: var(--text-main); 
            font-weight: 500; 
        }
        
        .payment-amount { 
            font-size: 1.1rem; 
            font-weight: 700; 
            color: #10B981; 
        }
        
        .no-payments { 
            text-align: center; 
            padding: 20px; 
            color: var(--text-muted); 
            font-style: italic; 
        }

        /* Additional details grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin: 20px 0;
        }
        
        .detail-item {
            background: var(--bg-body);
            padding: 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .detail-item-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        
        .detail-item-value {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
        }

        @keyframes fadeIn { 
            from { 
                opacity: 0; 
                transform: translateY(10px); 
            } 
            to { 
                opacity: 1; 
                transform: translateY(0); 
            } 
        }

        /* --- Overlay --- */
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
        
        /* --- Theme Toggle --- */
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

        /* --- Scrollbar Styling --- */
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

        /* --- Smooth Transitions --- */
        * {
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        /* --- Responsive Design --- */
        @media (max-width: 1200px) {
            .amount-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            body.sidebar-collapsed { --sidebar-width: 280px; } 
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
            
            .top-header { 
                padding: 0 20px; 
            }
            
            .scrollable { 
                padding: 24px 20px; 
            }
            
            .verify-card {
                padding: 24px;
            }
            
            .input-group { 
                flex-direction: column; 
            }
            
            .btn-verify { 
                width: 100%; 
            }
            
            .amount-summary { 
                grid-template-columns: 1fr; 
            }
            
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .verify-card {
                padding: 20px;
            }
            
            .detail-row {
                flex-direction: column;
                gap: 4px;
            }
            
            .payment-record {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- SIDEBAR FROM ADMIN DASHBOARD -->
    
     <?php include 'sidenavbar.php'; ?>
    <main class="main-content">
        
        <!-- HEADER FROM ADMIN DASHBOARD -->
        <header class="top-header">
    <div style="display: flex; align-items: center; gap: 16px;">
        <button class="toggle-btn" id="sidebarToggle">
            <i class="ph ph-list"></i>
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
            <span style="font-size: 0.9rem; color: var(--text-muted);">Verify Invoice</span>
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
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                <?php else: ?>
                    <div class="profile-placeholder">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
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

        <div class="scrollable">
            
            <div class="verify-card">
                <div style="margin-bottom: 20px;">
                    <i class="ph ph-shield-check" style="font-size: 4rem; color: var(--primary);"></i>
                </div>
                <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 8px;">Verify Invoice</h1>
                <p style="color: var(--text-muted);">Enter the Invoice Reference Number to verify details.</p>

                <form method="POST">
                    <div class="input-group">
                        <input type="text" name="invoice_ref" class="form-control" placeholder="e.g. INV-000001" value="<?php echo e($search_ref); ?>" required autocomplete="off">
                        <button type="submit" name="verify_btn" class="btn-verify">Check Now</button>
                    </div>
                </form>

                <?php if ($error): ?>
                    <div class="result-box result-error">
                        <i class="ph ph-warning-circle" style="font-size: 1.5rem; margin-bottom: 8px; display:block;"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($result): ?>
                    <div class="result-box result-success">
                        <div style="text-align: center;">
                            <div class="status-badge"><i class="ph ph-check-fat"></i> Verified Authentic</div>
                        </div>

                        <!-- Basic Information -->
                        <div class="detail-row">
                            <span class="detail-label">Reference No</span>
                            <span class="detail-value"><?php echo e($result['reference_no'] ?? $result['reference'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Customer Name</span>
                            <span class="detail-value"><?php echo e($result['full_name'] ?? $result['customer_name'] ?? 'Customer ID: ' . e($result['customer_id'] ?? 'N/A')); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Order Date</span>
                            <span class="detail-value"><?php echo date('d M, Y', strtotime($result['order_date'] ?? $result['created_at'] ?? date('Y-m-d'))); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Product Name</span>
                            <span class="detail-value"><?php echo e($result['product_name'] ?? 'N/A'); ?></span>
                        </div>

                        <!-- Amount Summary Cards -->
                        <div class="amount-summary">
                            <div class="amount-card total">
                                <div class="amount-card-label">Total Amount</div>
                                <div class="amount-card-value"><?php echo formatCurrency($result['total_amount'] ?? $result['amount'] ?? 0); ?></div>
                            </div>
                            <div class="amount-card paid">
                                <div class="amount-card-label">Paid Amount</div>
                                <div class="amount-card-value"><?php echo formatCurrency($total_paid); ?></div>
                                <?php if ($total_paid > 0): ?>
                                    <div class="payment-status-indicator">
                                        <div class="payment-status-dot <?php 
                                            if ($total_paid >= ($result['total_amount'] ?? 0)) echo 'paid';
                                            elseif ($total_paid > 0) echo 'partial';
                                            else echo 'pending';
                                        ?>"></div>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                                            <?php 
                                            if ($total_paid >= ($result['total_amount'] ?? 0)) echo 'Fully Paid';
                                            elseif ($total_paid > 0) echo 'Partially Paid';
                                            else echo 'Not Paid';
                                            ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="amount-card due">
                                <div class="amount-card-label">Due Amount</div>
                                <div class="amount-card-value"><?php echo formatCurrency($due_amount); ?></div>
                                <?php if ($due_amount > 0): ?>
                                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                                        <?php echo 'Payment Pending'; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 0.85rem; color: #10B981; margin-top: 4px;">
                                        <i class="ph ph-check"></i> No Due
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Additional Details Grid -->
                        <div class="details-grid">
                            <div class="detail-item">
                                <div class="detail-item-label">Status</div>
                                <div class="detail-item-value">
                                    <?php 
                                    $status = $result['status'] ?? 'Unknown';
                                    $status_color = 'var(--text-main)';
                                    if ($status === 'Paid') $status_color = '#10B981';
                                    elseif ($status === 'Partial') $status_color = '#F59E0B';
                                    elseif ($status === 'Pending') $status_color = '#EF4444';
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: 700;"><?php echo e($status); ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Payment Status</div>
                                <div class="detail-item-value"><?php echo e($result['payment_status'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Customer ID</div>
                                <div class="detail-item-value"><?php echo e($result['customer_id'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Created At</div>
                                <div class="detail-item-value"><?php echo date('d M, Y - h:i A', strtotime($result['created_at'] ?? date('Y-m-d H:i:s'))); ?></div>
                            </div>
                        </div>

                        <!-- Payment Details Section -->
                        <div style="margin-top: 20px; padding: 16px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div style="font-size: 1rem; font-weight: 600; color: var(--text-main);">
                                    <i class="ph ph-currency-dollar"></i> Payment Summary
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">
                                    From paid_amount column
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Total Invoice</div>
                                    <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary);"><?php echo formatCurrency($result['total_amount'] ?? 0); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Paid Amount</div>
                                    <div style="font-size: 1.2rem; font-weight: 700; color: #10B981;"><?php echo formatCurrency($total_paid); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Due Amount</div>
                                    <div style="font-size: 1.2rem; font-weight: 700; color: #EF4444;"><?php echo formatCurrency($due_amount); ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Payment Progress</div>
                                    <div style="margin-top: 4px;">
                                        <?php 
                                        $total = $result['total_amount'] ?? 1;
                                        $progress = $total > 0 ? ($total_paid / $total) * 100 : 0;
                                        ?>
                                        <div style="height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;">
                                            <div style="height: 100%; width: <?php echo min($progress, 100); ?>%; background: <?php echo $progress >= 100 ? '#10B981' : ($progress > 0 ? '#F59E0B' : '#EF4444'); ?>; transition: width 0.3s ease;"></div>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                                            <?php echo number_format($progress, 1); ?>% paid
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Note Section -->
                        <?php if (!empty($result['note'])): ?>
                        <div style="margin-top: 20px; padding: 16px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border);">
                            <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 8px;">Note:</div>
                            <div style="color: var(--text-main);"><?php echo e($result['note']); ?></div>
                        </div>
                        <?php endif; ?>

                        <!-- Payment History Section -->
                        <div class="payment-history-section">
                            <div class="section-title">
                                <i class="ph ph-clock-counter-clockwise"></i>
                                Payment History
                            </div>
                            
                            <?php if (count($payment_history) > 0): ?>
                                <?php foreach ($payment_history as $payment): ?>
                                    <div class="payment-record">
                                        <div class="payment-info">
                                            <div class="payment-date">
                                                <i class="ph ph-calendar-blank"></i>
                                                <?php echo date('M d, Y - h:i A', strtotime($payment['paid_at'])); ?>
                                            </div>
                                            <div class="payment-by">
                                                <i class="ph ph-user"></i>
                                                <?php echo e($payment['paid_by'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                        <div class="payment-amount">
                                            <?php echo formatCurrency($payment['amount']); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-payments">
                                    <i class="ph ph-wallet" style="font-size: 2rem; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                                    No payment history records found. Paid amount shown above is from the invoice record.
                                    <div style="margin-top: 8px; font-size: 0.85rem; color: var(--text-muted);">
                                        Paid Amount: <?php echo formatCurrency($total_paid); ?> (from paid_amount column)
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="margin-top: 20px; text-align: center;">
                            <a href="invoice_view.php?id=<?php echo $result['id'] ?? 0; ?>" target="_blank" style="color: var(--primary); font-weight: 600; text-decoration: underline; font-size: 0.9rem;">
                                View / Print Invoice
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

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
        });

        // --- 2. Accordion Logic ---
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

        // --- 4. Dark Mode ---
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

        // --- Initialize Sidebar State ---
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }

        // --- Auto-close dropdowns on escape key ---
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });

        // --- Auto-focus on search input ---
        document.querySelector('input[name="invoice_ref"]')?.focus();
    </script>
</body>
</html>