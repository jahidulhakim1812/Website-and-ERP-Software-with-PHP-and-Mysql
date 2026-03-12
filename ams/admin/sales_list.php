<?php
/**
 * sales_list.php
 * -----------------------------------------
 * Feature: Sales List Management with Partial Payment
 * Design: NexusAdmin (Perfect Version)
 * Version: 3.0 - Enhanced Payment History with Date Display
 * -----------------------------------------
 */

session_start();

// --- 1. SECURITY: CSRF Token Generation ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- 2. AUTHENTICATION & SESSION HANDLING ---
$userId   = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Guest User';
$role     = $_SESSION['role'] ?? 'guest';

// Redirect to login if not authenticated
if (!$userId) {
    header("Location: login.php");
    exit;
}

// Logout Logic
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit;
}

// --- 3. DATABASE CONFIGURATION ---
$DB_HOST = $_ENV['DB_HOST'] ?? 'localhost';
$DB_NAME = $_ENV['DB_NAME'] ?? 'alihairw_alisoft';
$DB_USER = $_ENV['DB_USER'] ?? 'alihairw_ali';
$DB_PASS = $_ENV['DB_PASS'] ?? 'x5.H(8xkh3H7EY';

try {
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $ex) {
    error_log("Database connection failed: " . $ex->getMessage());
    die("
        <div style='font-family:system-ui,-apple-system,sans-serif; text-align:center; padding:50px; background:#f8fafc;'>
            <div style='max-width:500px; margin:0 auto; background:white; padding:40px; border-radius:12px; box-shadow:0 4px 6px rgba(0,0,0,0.1);'>
                <svg style='width:64px; height:64px; color:#ef4444; margin:0 auto 20px;' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                    <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z'/>
                </svg>
                <h2 style='color:#1f2937; font-size:24px; font-weight:600; margin-bottom:12px;'>Database Connection Failed</h2>
                <p style='color:#6b7280; font-size:16px; line-height:1.5;'>Unable to connect to the database. Please contact your system administrator.</p>
            </div>
        </div>
    ");
}

// --- 4. HELPER FUNCTIONS ---
function e($val) { 
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); 
}

function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        return $result !== false;
    } catch (Exception $e) { 
        return false; 
    }
}

function verifyCsrfToken() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        return false;
    }
    return true;
}

// Fetch User Photo
$userPhoto = null;
if (tableExists($pdo, 'users') && $userId) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $userPhoto = $stmt->fetchColumn();
    } catch (Exception $e) {
        error_log("Error fetching user photo: " . $e->getMessage());
    }
}

// --- 5. BACKEND LOGIC: HANDLE PAYMENTS & DELETE ---
$msg = "";
$msgType = "";

// Handle Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!verifyCsrfToken()) {
        $msg = "Security validation failed. Please try again.";
        $msgType = "error";
    } else {
        // Handle Delete Sale
        if (isset($_POST['action']) && $_POST['action'] === 'delete_sale') {
            try {
                $pdo->beginTransaction();
                
                $sale_id = filter_input(INPUT_POST, 'sale_id', FILTER_VALIDATE_INT);
                
                if (!$sale_id) {
                    throw new Exception("Invalid sale ID.");
                }
                
                // First, delete payment history if exists
                if (tableExists($pdo, 'payment_history')) {
                    $deleteHistory = $pdo->prepare("DELETE FROM payment_history WHERE purchase_id = ?");
                    $deleteHistory->execute([$sale_id]);
                }
                
                // Then delete the sale
                $deleteSale = $pdo->prepare("DELETE FROM purchase_orders WHERE id = ?");
                $deleteSale->execute([$sale_id]);
                
                $pdo->commit();
                
                $msg = "Sale record deleted successfully!";
                $msgType = "success";
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $msg = $e->getMessage();
                $msgType = "error";
                error_log("Delete error: " . $e->getMessage());
            }
        }
        // Handle Payment
        elseif (isset($_POST['action']) && $_POST['action'] === 'add_payment') {
            try {
                $pdo->beginTransaction();
                
                $p_id = filter_input(INPUT_POST, 'purchase_id', FILTER_VALIDATE_INT);
                $amount_paying = filter_input(INPUT_POST, 'payment_amount', FILTER_VALIDATE_FLOAT);
                $paid_by = trim($_POST['paid_by'] ?? '');

                if (!$p_id || $amount_paying === false || $amount_paying <= 0) {
                    throw new Exception("Invalid payment amount or order ID.");
                }

                // Fetch current order details with row lock
                $stmt = $pdo->prepare("SELECT total_amount, paid_amount FROM purchase_orders WHERE id = ? FOR UPDATE");
                $stmt->execute([$p_id]);
                $order = $stmt->fetch();

                if (!$order) {
                    throw new Exception("Order not found.");
                }

                $current_paid = floatval($order['paid_amount']);
                $total = floatval($order['total_amount']);
                
                // Prevent overpayment
                if (($current_paid + $amount_paying) > $total + 0.01) { 
                    $amount_paying = $total - $current_paid;
                    if ($amount_paying <= 0) {
                        throw new Exception("This order is already fully paid.");
                    }
                }

                $new_paid = $current_paid + $amount_paying;

                // Determine new status
                $new_status = 'Unpaid';
                if ($new_paid >= ($total - 0.001)) {
                    $new_status = 'Paid';
                    $new_paid = $total; // Clean up floating point precision
                } elseif ($new_paid > 0) {
                    $new_status = 'Partial';
                }

                // FIXED: Remove updated_at field from the query
                $update = $pdo->prepare("UPDATE purchase_orders SET paid_amount = ?, payment_status = ? WHERE id = ?");
                $update->execute([$new_paid, $new_status, $p_id]);
                
                // Insert payment history
                if (tableExists($pdo, 'payment_history')) {
                    $logStmt = $pdo->prepare("INSERT INTO payment_history (purchase_id, amount, paid_by, paid_at) VALUES (?, ?, ?, NOW())");
                    $logStmt->execute([$p_id, $amount_paying, $paid_by ?: null]);
                }
                
                $pdo->commit();
                
                $balance = $total - $new_paid;
                $msg = sprintf(
                    "Payment of $%s recorded successfully! Remaining balance: $%s",
                    number_format($amount_paying, 2),
                    number_format($balance, 2)
                );
                $msgType = "success";
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $msg = $e->getMessage();
                $msgType = "error";
                error_log("Payment error: " . $e->getMessage());
            }
        }
    }
}

// --- 6. FILTER PARAMETERS ---
$filter_status = $_GET['status'] ?? '';
$filter_customer = $_GET['customer'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_reference = $_GET['reference'] ?? '';

// --- 7. FETCH DATA WITH PAGINATION & FILTERS ---
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Build WHERE clause for filters
$whereConditions = [];
$params = [];
$paramTypes = [];

if (!empty($filter_status)) {
    $whereConditions[] = "p.payment_status = ?";
    $params[] = $filter_status;
    $paramTypes[] = PDO::PARAM_STR;
}

if (!empty($filter_customer)) {
    $whereConditions[] = "(c.full_name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
    $params[] = "%{$filter_customer}%";
    $params[] = "%{$filter_customer}%";
    $params[] = "%{$filter_customer}%";
    $paramTypes[] = PDO::PARAM_STR;
    $paramTypes[] = PDO::PARAM_STR;
    $paramTypes[] = PDO::PARAM_STR;
}

if (!empty($filter_date_from)) {
    $whereConditions[] = "DATE(p.created_at) >= ?";
    $params[] = $filter_date_from;
    $paramTypes[] = PDO::PARAM_STR;
}

if (!empty($filter_date_to)) {
    $whereConditions[] = "DATE(p.created_at) <= ?";
    $params[] = $filter_date_to;
    $paramTypes[] = PDO::PARAM_STR;
}

if (!empty($filter_reference)) {
    $whereConditions[] = "p.reference_no LIKE ?";
    $params[] = "%{$filter_reference}%";
    $paramTypes[] = PDO::PARAM_STR;
}

$whereSQL = '';
if (!empty($whereConditions)) {
    $whereSQL = ' WHERE ' . implode(' AND ', $whereConditions);
}

// Count total records with filters
$countSQL = "SELECT COUNT(*) FROM purchase_orders p LEFT JOIN customers c ON p.customer_id = c.id" . $whereSQL;
$countStmt = $pdo->prepare($countSQL);
foreach ($params as $i => $param) {
    $countStmt->bindValue($i + 1, $param, $paramTypes[$i]);
}
$countStmt->execute();
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $perPage);

// Fetch sales data with filters
$sql = "SELECT p.*, 
               c.full_name,
               c.phone,
               c.email,
               c.photo_url as customer_photo,
               DATE(p.created_at) as sale_date
        FROM purchase_orders p 
        LEFT JOIN customers c ON p.customer_id = c.id 
        {$whereSQL}
        ORDER BY p.id DESC 
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);

// Bind filter parameters
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param, $paramTypes[$i]);
}

// Bind pagination parameters
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

// --- 8. EXPORT TO CSV ---
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=sales_export_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // CSV Header
    fputcsv($output, [
        'Reference No', 
        'Customer Name', 
        'Customer Phone', 
        'Customer Email', 
        'Total Amount', 
        'Paid Amount', 
        'Due Amount', 
        'Payment Status', 
        'Sale Date', 
        'Order Items'
    ]);
    
    // Export all filtered data (not just current page)
    $exportSQL = "SELECT p.*, 
                         c.full_name,
                         c.phone,
                         c.email,
                         DATE(p.created_at) as sale_date
                  FROM purchase_orders p 
                  LEFT JOIN customers c ON p.customer_id = c.id 
                  {$whereSQL}
                  ORDER BY p.id DESC";
    $exportStmt = $pdo->prepare($exportSQL);
    
    foreach ($params as $i => $param) {
        $exportStmt->bindValue($i + 1, $param, $paramTypes[$i]);
    }
    
    $exportStmt->execute();
    $exportData = $exportStmt->fetchAll();
    
    foreach ($exportData as $row) {
        $total = floatval($row['total_amount']);
        $paid = floatval($row['paid_amount'] ?? 0);
        $due = $total - $paid;
        
        fputcsv($output, [
            $row['reference_no'] ?? 'PO-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT),
            $row['full_name'] ?? 'N/A',
            $row['phone'] ?? 'N/A',
            $row['email'] ?? 'N/A',
            number_format($total, 2),
            number_format($paid, 2),
            number_format($due, 2),
            $row['payment_status'] ?? 'Unpaid',
            $row['sale_date'] ?? 'N/A',
            $row['order_items'] ?? 'N/A'
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sales list management with payment tracking">
    <title>Sales List | NexusAdmin</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            --danger: #ef4444;
            --danger-hover: #dc2626;
        }

        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --sidebar-bg: #020617;
            --primary: #6366f1;
            --danger: #f87171;
            --danger-hover: #ef4444;
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

        /* Theme Toggle */
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

        /* Dashboard Body */
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
        
        /* Alert Messages */
        .alert {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            border: 1px solid;
            animation: slideInDown 0.3s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background-color: #f0fdf4;
            border-color: #86efac;
            color: #166534;
        }
        
        [data-theme="dark"] .alert-success {
            background-color: rgba(34, 197, 94, 0.1);
            border-color: rgba(34, 197, 94, 0.3);
            color: #86efac;
        }
        
        .alert-error {
            background-color: #fef2f2;
            border-color: #fca5a5;
            color: #991b1b;
        }
        
        [data-theme="dark"] .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }
        
        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .alert-message {
            flex: 1;
            font-size: 0.9375rem;
            font-weight: 500;
        }
        
        .alert-close {
            background: none;
            border: none;
            color: currentColor;
            opacity: 0.7;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .alert-close:hover {
            opacity: 1;
            background: rgba(0,0,0,0.05);
        }

        /* Filter Section */
        .filter-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .filter-toggle {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 0.9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        
        .filter-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
        }
        
        .filter-input {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        
        .btn-filter {
            padding: 10px 24px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .btn-filter:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .btn-reset {
            padding: 10px 24px;
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .btn-reset:hover {
            background: var(--border);
        }

        /* Export Button */
        .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9375rem;
            transition: all 0.2s;
            text-decoration: none;
        }
        
        .btn-export:hover {
            background: #059669;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        /* Table Styles */
        .table-card {
            background: var(--bg-card);
            border-radius: 16px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background-color: var(--bg-body);
            border-bottom: 2px solid var(--border);
        }
        
        .data-table th {
            padding: 16px 24px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }
        
        .data-table tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background-color 0.2s;
        }
        
        .data-table tbody tr:hover {
            background-color: var(--bg-body);
        }
        
        .data-table tbody tr:last-child {
            border-bottom: none;
        }
        
        .data-table td {
            padding: 20px 24px;
            font-size: 0.9375rem;
        }
        
        .ref-number {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.875rem;
        }
        
        .customer-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .customer-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 0.875rem;
            flex-shrink: 0;
        }
        
        .customer-name {
            font-weight: 500;
            color: var(--text-main);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid;
        }
        
        .status-paid {
            background-color: #f0fdf4;
            color: #166534;
            border-color: #86efac;
        }
        
        [data-theme="dark"] .status-paid {
            background-color: rgba(34, 197, 94, 0.1);
            color: #86efac;
            border-color: rgba(34, 197, 94, 0.3);
        }
        
        .status-partial {
            background-color: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }
        
        [data-theme="dark"] .status-partial {
            background-color: rgba(251, 191, 36, 0.1);
            color: #fcd34d;
            border-color: rgba(251, 191, 36, 0.3);
        }
        
        .status-unpaid {
            background-color: #fef2f2;
            color: #991b1b;
            border-color: #fca5a5;
        }
        
        [data-theme="dark"] .status-unpaid {
            background-color: rgba(239, 68, 68, 0.1);
            color: #fca5a5;
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .amount-cell {
            font-weight: 700;
            font-variant-numeric: tabular-nums;
        }
        
        .amount-total {
            color: var(--text-main);
        }
        
        .amount-paid {
            color: #059669;
        }
        
        .amount-due {
            color: #dc2626;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .btn-pay {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: transparent;
            color: var(--primary);
            border: 1.5px solid var(--primary);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-pay:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(79, 70, 229, 0.3);
        }
        
        .btn-delete {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: transparent;
            color: var(--danger);
            border: 1.5px solid var(--danger);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-delete:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
        }
        
        .btn-history {
            background: none;
            border: 1px solid var(--border);
            color: var(--text-muted);
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-history:hover {
            background: var(--bg-body);
            color: var(--primary);
            border-color: var(--primary);
            transform: translateY(-1px);
        }
        
        .status-complete {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #059669;
            font-size: 0.875rem;
            font-weight: 700;
        }
        
        /* Empty State */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 80px 24px;
            text-align: center;
        }
        
        .empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--bg-body);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
        }
        
        .empty-icon i {
            font-size: 2.5rem;
            color: var(--text-muted);
        }
        
        .empty-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .empty-description {
            color: var(--text-muted);
            margin-bottom: 24px;
        }
        
        .empty-action {
            color: var(--primary);
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .empty-action:hover {
            text-decoration: underline;
        }

        /* Pagination */
        .pagination {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 32px;
            padding: 24px;
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        
        .pagination-btn {
            padding: 8px 16px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-main);
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .pagination-info {
            font-size: 0.875rem;
            color: var(--text-muted);
            padding: 0 16px;
        }

        /* Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border);
            transform: scale(0.95);
            transition: transform 0.3s;
        }
        
        .modal-overlay.show .modal-content {
            transform: scale(1);
        }
        
        .modal-header {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 24px;
            border-bottom: 1px solid var(--border);
        }
        
        .modal-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.1) 0%, rgba(129, 140, 248, 0.1) 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .modal-icon i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .modal-title-wrapper {
            flex: 1;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        
        .modal-subtitle {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .due-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            color: #991b1b;
            border-radius: 8px;
            font-weight: 700;
            font-size: 0.875rem;
            margin-bottom: 24px;
        }
        
        [data-theme="dark"] .due-badge {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.15) 100%);
            color: #fca5a5;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-prefix {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-weight: 700;
            pointer-events: none;
        }
        
        .form-input {
            width: 100%;
            padding: 14px 16px 14px 36px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            background: var(--bg-body);
            transition: all 0.2s;
            outline: none;
        }
        
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .modal-footer {
            display: flex;
            gap: 12px;
            padding: 20px 24px;
            background: var(--bg-body);
            border-top: 1px solid var(--border);
            border-radius: 0 0 16px 16px;
        }
        
        .btn-cancel {
            flex: 1;
            padding: 12px 24px;
            background: transparent;
            color: var(--text-main);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-cancel:hover {
            background: var(--bg-body);
            border-color: var(--text-muted);
        }
        
        .btn-submit {
            flex: 1;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-hover) 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.4);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }

        /* Delete Confirmation Modal */
        .delete-modal .modal-icon {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.15) 100%);
        }
        
        .delete-modal .modal-icon i {
            color: var(--danger);
        }
        
        .warning-text {
            color: var(--danger);
            font-weight: 600;
            margin-bottom: 16px;
        }

        /* Scrollbar */
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
            
            .profile-info { 
                display: none; 
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .data-table th, .data-table td { 
                padding: 12px 16px; 
            }
            
            .table-wrapper {
                overflow-x: scroll;
                -webkit-overflow-scrolling: touch;
            }
            
            .data-table {
                min-width: 800px;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 4px;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
        
        @media (max-width: 480px) {
            .scrollable { 
                padding: 16px; 
            }
            
            .modal-footer {
                flex-direction: column-reverse;
            }
            
            .btn-cancel, .btn-submit {
                width: 100%;
            }
        }

        /* Buttons */
        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9375rem;
            transition: all 0.2s;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        /* Modal Close */
        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        
        .modal-close:hover {
            background: var(--bg-body);
            color: var(--text-main);
        }
        
        /* Payment History Styles */
        .payment-history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .payment-history-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 16px;
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .payment-history-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .payment-info {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1;
        }
        
        .payment-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .payment-details {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }
        
        .payment-date {
            font-size: 0.875rem;
            color: var(--text-main);
            font-weight: 500;
        }
        
        .payment-by {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .payment-amount {
            font-size: 1.125rem;
            font-weight: 600;
            color: #10b981;
            flex-shrink: 0;
        }
        
        .loading-spinner {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            gap: 12px;
            color: var(--text-muted);
        }
        
        .loading-spinner i {
            font-size: 2rem;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .empty-history {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
        
        .empty-history i {
            font-size: 3rem;
            opacity: 0.3;
            margin-bottom: 12px;
        }
        
        /* Date badges */
        .date-badge-today {
            background: rgba(34, 197, 94, 0.1);
            color: #166534;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .date-badge-yesterday {
            background: rgba(251, 191, 36, 0.1);
            color: #92400e;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .date-badge-recent {
            background: rgba(99, 102, 241, 0.1);
            color: #4f46e5;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        /* Time indicators */
        .time-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .time-morning {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
        }
        
        .time-afternoon {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
        }
        
        .time-evening {
            background: rgba(139, 92, 246, 0.1);
            color: #5b21b6;
        }
        
        /* Extra details */
        .extra-details {
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Download button */
        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-download:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- Sidebar -->
     <?php include 'sidenavbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Sales List</span>
                </div>
            </div>

            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($username); ?></span>
                            <span class="profile-role"><?php echo ucfirst(e($role)); ?></span>
                        </div>
                        <?php if (!empty($userPhoto)): ?>
                            <img src="<?php echo e($userPhoto); ?>" alt="Profile" class="profile-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="profile-placeholder" style="display: none;">
                                <?php echo strtoupper(substr($username, 0, 1)); ?>
                            </div>
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <?php echo strtoupper(substr($username, 0, 1)); ?>
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

        <!-- Page Content -->
        <div class="scrollable">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 700; margin-bottom: 4px; color: var(--text-main);">Sales List</h1>
                    <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.4;">Manage and track all sales orders and payments</p>
                </div>
                <div style="display: flex; gap: 12px;">
                    <a href="add_sale.php" class="btn-primary">
                        <i class="ph ph-plus-circle"></i>
                        <span>New Sale</span>
                    </a>
                    <a href="?export=csv<?php echo !empty($_GET) ? '&' . http_build_query($_GET) : ''; ?>" class="btn-export">
                        <i class="ph ph-download-simple"></i>
                        <span>Export CSV</span>
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if ($msg): 
                $alertClass = ($msgType === 'success') ? 'alert-success' : 'alert-error';
                $icon = ($msgType === 'success') ? 'ph-check-circle' : 'ph-warning-circle';
            ?>
            <div class="alert <?php echo $alertClass; ?>">
                <i class="ph <?php echo $icon; ?> alert-icon"></i>
                <span class="alert-message"><?php echo e($msg); ?></span>
                <button class="alert-close" onclick="this.parentElement.remove()">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <?php endif; ?>

            <!-- Filter Section -->
            <div class="filter-card">
                <div class="filter-header">
                    <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-main);">Filter Sales</h3>
                    <button class="filter-toggle" onclick="toggleFilter()">
                        <i class="ph ph-funnel"></i>
                        <span>Filters</span>
                    </button>
                </div>
                <div class="filter-content" id="filterContent">
                    <div class="filter-group">
                        <label class="filter-label">Reference No</label>
                        <input 
                            type="text" 
                            name="reference" 
                            class="filter-input" 
                            placeholder="Search reference..."
                            value="<?php echo e($filter_reference); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Customer</label>
                        <input 
                            type="text" 
                            name="customer" 
                            class="filter-input" 
                            placeholder="Search customer..."
                            value="<?php echo e($filter_customer); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-input">
                            <option value="">All Status</option>
                            <option value="Paid" <?php echo $filter_status === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="Partial" <?php echo $filter_status === 'Partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="Unpaid" <?php echo $filter_status === 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date From</label>
                        <input 
                            type="date" 
                            name="date_from" 
                            class="filter-input" 
                            value="<?php echo e($filter_date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date To</label>
                        <input 
                            type="date" 
                            name="date_to" 
                            class="filter-input" 
                            value="<?php echo e($filter_date_to); ?>">
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="button" class="btn-filter" onclick="applyFilters()">
                        <i class="ph ph-magnifying-glass"></i>
                        Apply Filters
                    </button>
                    <button type="button" class="btn-reset" onclick="resetFilters()">
                        <i class="ph ph-arrow-clockwise"></i>
                        Reset
                    </button>
                </div>
            </div>

            <!-- Sales Table -->
            <div class="table-card">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ref No.</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Total</th>
                                <th>Paid</th>
                                <th>Due</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="ph ph-receipt"></i>
                                        </div>
                                        <h3 class="empty-title">No sales found</h3>
                                        <p class="empty-description">Start by creating a new sale to see it here.</p>
                                        <a href="add_sale.php" class="empty-action">Create Sale</a>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach($orders as $row): 
                                    $total = floatval($row['total_amount']);
                                    $paid  = floatval($row['paid_amount'] ?? 0);
                                    $due   = $total - $paid;

                                    // Status Badge Logic
                                    $status = $row['payment_status'];
                                    $badgeClass = 'status-unpaid';
                                    if($status == 'Paid') $badgeClass = 'status-paid';
                                    if($status == 'Partial') $badgeClass = 'status-partial';
                                ?>
                                <tr>
                                    <td>
                                        <span class="ref-number">
                                            <?php echo e($row['reference_no'] ?? 'PO-'.str_pad($row['id'], 5, '0', STR_PAD_LEFT)); ?>
                                        </span>
                                        <?php if (!empty($row['sale_date'])): ?>
                                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
                                                <?php echo e($row['sale_date']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="customer-cell">
                                            <?php if (!empty($row['customer_photo'])): ?>
                                                <img src="<?php echo e($row['customer_photo']); ?>" 
                                                     alt="<?php echo e($row['full_name']); ?>" 
                                                     class="customer-avatar"
                                                     style="object-fit: cover; background: none;"
                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                                <div class="customer-avatar" style="display: none;">
                                                    <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                                </div>
                                            <?php else: ?>
                                                <div class="customer-avatar">
                                                    <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <span class="customer-name"><?php echo e($row['full_name']); ?></span>
                                                <?php if (!empty($row['email'])): ?>
                                                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                                        <?php echo e($row['email']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $badgeClass; ?>">
                                            <?php echo e($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="amount-cell amount-total">$<?php echo number_format($total, 2); ?></span>
                                    </td>
                                    <td>
                                        <span class="amount-cell amount-paid">$<?php echo number_format($paid, 2); ?></span>
                                    </td>
                                    <td>
                                        <span class="amount-cell amount-due">$<?php echo number_format($due, 2); ?></span>
                                    </td>
                                    <td style="text-align: right;">
                                        <div class="action-buttons">
                                            <?php if($due > 0.01): ?>
                                                <button 
                                                    onclick="openModal(<?php echo $row['id']; ?>, <?php echo $due; ?>)" 
                                                    class="btn-pay">
                                                    <i class="ph ph-hand-coins"></i>
                                                    <span>Pay</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="status-complete">
                                                    <i class="ph-fill ph-check-circle"></i> 
                                                    <span>Paid</span>
                                                </span>
                                            <?php endif; ?>
                                            <button 
                                                onclick="openHistoryModal(<?php echo $row['id']; ?>)" 
                                                class="btn-history"
                                                title="View Payment History">
                                                <i class="ph ph-clock-counter-clockwise"></i>
                                            </button>
                                            <button 
                                                onclick="confirmDelete(<?php echo $row['id']; ?>, '<?php echo e($row['reference_no'] ?? 'PO-'.str_pad($row['id'], 5, '0', STR_PAD_LEFT)); ?>')" 
                                                class="btn-delete"
                                                title="Delete Sale">
                                                <i class="ph ph-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): 
                $queryParams = $_GET;
                unset($queryParams['page']);
                $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
            ?>
            <div class="pagination">
                <span class="pagination-info">
                    Page <?php echo $page; ?> of <?php echo $totalPages; ?> (<?php echo $totalRecords; ?> records)
                </span>
                <a 
                    href="?page=1<?php echo $queryString; ?>" 
                    class="pagination-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <i class="ph ph-caret-double-left"></i>
                </a>
                <a 
                    href="?page=<?php echo max(1, $page - 1) . $queryString; ?>" 
                    class="pagination-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <i class="ph ph-caret-left"></i> Previous
                </a>
                
                <?php
                $start = max(1, $page - 2);
                $end = min($totalPages, $page + 2);
                for ($i = $start; $i <= $end; $i++):
                ?>
                    <a 
                        href="?page=<?php echo $i . $queryString; ?>" 
                        class="pagination-btn <?php echo ($i == $page) ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <a 
                    href="?page=<?php echo min($totalPages, $page + 1) . $queryString; ?>" 
                    class="pagination-btn <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    Next <i class="ph ph-caret-right"></i>
                </a>
                <a 
                    href="?page=<?php echo $totalPages . $queryString; ?>" 
                    class="pagination-btn <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                    <i class="ph ph-caret-double-right"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Payment Modal -->
    <div class="modal-overlay" id="payModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="ph-fill ph-hand-coins"></i>
                </div>
                <div class="modal-title-wrapper">
                    <h2 class="modal-title">Record Payment</h2>
                    <p class="modal-subtitle">Enter the amount received from customer</p>
                </div>
            </div>

            <div class="modal-body">
                <div class="due-badge">
                    <i class="ph ph-warning-circle"></i>
                    <span>Due Amount: $<span id="modal_due_display">0.00</span></span>
                </div>

                <form method="POST" id="paymentForm">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="add_payment">
                    <input type="hidden" name="purchase_id" id="m_id">
                    
                    <div class="form-group">
                        <label for="payment_amount" class="form-label">Payment Amount</label>
                        <div class="input-wrapper">
                            <span class="input-prefix">$</span>
                            <input 
                                type="number" 
                                name="payment_amount" 
                                id="payment_amount" 
                                class="form-input"
                                step="0.01" 
                                min="0.01" 
                                required 
                                placeholder="0.00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="paid_by" class="form-label">Paid By (Optional)</label>
                        <input 
                            type="text" 
                            name="paid_by" 
                            id="paid_by" 
                            class="form-input"
                            placeholder="Customer name or payment method"
                            maxlength="100">
                    </div>
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-cancel">Cancel</button>
                <button type="submit" form="paymentForm" class="btn-submit">Save Payment</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content delete-modal">
            <div class="modal-header">
                <div class="modal-icon">
                    <i class="ph-fill ph-warning-circle"></i>
                </div>
                <div class="modal-title-wrapper">
                    <h2 class="modal-title">Delete Sale</h2>
                    <p class="modal-subtitle">Are you sure you want to delete this sale?</p>
                </div>
            </div>

            <div class="modal-body">
                <div class="warning-text">
                    <i class="ph ph-warning"></i>
                    <span>This action cannot be undone. All payment history will also be deleted.</span>
                </div>
                
                <div style="background: var(--bg-body); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <div style="font-weight: 600; margin-bottom: 8px;">Sale Details:</div>
                    <div style="font-size: 0.9rem;">
                        Reference: <span id="delete_ref" style="font-weight: 600;"></span>
                    </div>
                </div>

                <form method="POST" id="deleteForm">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="action" value="delete_sale">
                    <input type="hidden" name="sale_id" id="delete_id">
                </form>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeDeleteModal()" class="btn-cancel">Cancel</button>
                <button type="submit" form="deleteForm" class="btn-submit" style="background: linear-gradient(135deg, var(--danger) 0%, var(--danger-hover) 100%);">Delete Sale</button>
            </div>
        </div>
    </div>

    <!-- Payment History Modal -->
    <div class="modal-overlay" id="historyModal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <div class="modal-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <i class="ph-fill ph-clock-counter-clockwise"></i>
                </div>
                <div class="modal-title-wrapper">
                    <h2 class="modal-title">Payment History</h2>
                    <p class="modal-subtitle">View all payment transactions for this order</p>
                </div>
                <button type="button" onclick="closeHistoryModal()" class="modal-close">
                    <i class="ph ph-x"></i>
                </button>
            </div>

            <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
                <div id="historyContent">
                    <div class="loading-spinner">
                        <i class="ph ph-spinner"></i>
                        <span>Loading payment history...</span>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" onclick="closeHistoryModal()" class="btn-cancel">Close</button>
            </div>
        </div>
    </div>

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
            closeModal();
            closeHistoryModal();
            closeDeleteModal();
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

        // --- 5. Filter Functions ---
        function applyFilters() {
            const filterInputs = document.querySelectorAll('.filter-input, .filter-select');
            const params = new URLSearchParams();
            
            filterInputs.forEach(input => {
                if (input.value) {
                    params.append(input.name, input.value);
                }
            });
            
            window.location.href = '?' + params.toString();
        }
        
        function resetFilters() {
            window.location.href = 'sales_list.php';
        }
        
        function toggleFilter() {
            const filterContent = document.getElementById('filterContent');
            filterContent.style.display = filterContent.style.display === 'none' ? 'grid' : 'none';
        }
        
        // Auto-submit filter on enter in text inputs
        document.querySelectorAll('.filter-input[type="text"]').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    applyFilters();
                }
            });
        });

        // --- 6. Payment Modal ---
        let currentOrderId = null;

        function openModal(id, dueAmount) {
            currentOrderId = id;
            
            // Populate form
            document.getElementById('m_id').value = id;
            document.getElementById('modal_due_display').textContent = dueAmount.toFixed(2);
            document.getElementById('payment_amount').value = dueAmount.toFixed(2);
            document.getElementById('payment_amount').setAttribute('max', dueAmount.toFixed(2));
            
            // Show modal
            const modal = document.getElementById('payModal');
            modal.classList.add('show');
            
            // Focus on input after animation
            setTimeout(() => {
                document.getElementById('payment_amount').focus();
                document.getElementById('payment_amount').select();
            }, 300);
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            const modal = document.getElementById('payModal');
            modal.classList.remove('show');
            document.getElementById('paymentForm').reset();
            currentOrderId = null;
            
            // Restore body scroll
            document.body.style.overflow = '';
        }

        // Close modal on backdrop click
        document.getElementById('payModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('payment_amount').value);
            const maxAmount = parseFloat(document.getElementById('payment_amount').getAttribute('max'));
            
            if (amount <= 0) {
                e.preventDefault();
                alert('Payment amount must be greater than zero.');
                return false;
            }
            
            if (amount > maxAmount) {
                e.preventDefault();
                alert(`Payment amount cannot exceed the due amount of $${maxAmount.toFixed(2)}`);
                return false;
            }
        });

        // --- 7. Delete Confirmation Modal ---
        function confirmDelete(saleId, refNo) {
            document.getElementById('delete_id').value = saleId;
            document.getElementById('delete_ref').textContent = refNo;
            
            const modal = document.getElementById('deleteModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('show');
            document.getElementById('deleteForm').reset();
            document.body.style.overflow = '';
        }
        
        // Close delete modal on backdrop click
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });
        
        // Delete form confirmation
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            if (!confirm('Are you absolutely sure you want to delete this sale? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }
        });

        // --- 8. Auto-dismiss alerts ---
        document.querySelectorAll('.alert').forEach(alert => {
            setTimeout(() => {
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-20px)';
                setTimeout(() => alert.remove(), 300);
            }, 8000);
        });

        // --- 9. Payment History Modal ---
        let currentPurchaseId = null;

        function openHistoryModal(purchaseId) {
            currentPurchaseId = purchaseId;
            const modal = document.getElementById('historyModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
            
            // Show loading spinner
            document.getElementById('historyContent').innerHTML = `
                <div class="loading-spinner">
                    <i class="ph ph-spinner"></i>
                    <span>Loading payment history...</span>
                </div>
            `;
            
            // Fetch payment history via AJAX
            fetch(`get_payment_history.php?purchase_id=${purchaseId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    displayPaymentHistory(data);
                })
                .catch(error => {
                    console.error('Error fetching payment history:', error);
                    document.getElementById('historyContent').innerHTML = `
                        <div class="empty-history">
                            <i class="ph ph-warning-circle"></i>
                            <p>Error loading payment history. Please try again.</p>
                            <button onclick="openHistoryModal(${purchaseId})" style="margin-top: 12px; padding: 8px 16px; background: var(--primary); color: white; border: none; border-radius: 8px; cursor: pointer;">
                                Retry
                            </button>
                        </div>
                    `;
                });
        }
        
        function closeHistoryModal() {
            const modal = document.getElementById('historyModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
            currentPurchaseId = null;
        }
        
        function displayPaymentHistory(data) {
            const container = document.getElementById('historyContent');
            
            if (!data || !data.payments || data.payments.length === 0) {
                container.innerHTML = `
                    <div class="empty-history">
                        <i class="ph ph-receipt"></i>
                        <p>No payment history found for this order.</p>
                    </div>
                `;
                return;
            }
            
            const { order_info, payments, total_payments } = data;
            const totalPaid = payments.reduce((sum, payment) => sum + parseFloat(payment.amount), 0);
            const orderTotal = order_info ? parseFloat(order_info.total_amount) : 0;
            const remaining = order_info ? (orderTotal - totalPaid) : 0;
            
            // Sequence colors for payment numbers
            const sequenceColors = [
                'linear-gradient(135deg, #4F46E5 0%, #7C3AED 100%)',
                'linear-gradient(135deg, #10B981 0%, #059669 100%)',
                'linear-gradient(135deg, #F59E0B 0%, #D97706 100%)',
                'linear-gradient(135deg, #EC4899 0%, #DB2777 100%)',
                'linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%)'
            ];
            
            let html = `
                <div style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <div>
                            <div style="font-size: 0.875rem; color: var(--text-muted);">Order Reference</div>
                            <div style="font-size: 1.125rem; font-weight: 700; color: var(--text-main);">${order_info.reference_no || 'N/A'}</div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.875rem; color: var(--text-muted);">Total Payments</div>
                            <div style="font-size: 1.125rem; font-weight: 700; color: #10b981;">$${totalPaid.toFixed(2)}</div>
                        </div>
                    </div>
                    
                    ${remaining > 0 ? `
                        <div style="background: rgba(239, 68, 68, 0.1); padding: 12px 16px; border-radius: 8px; border-left: 4px solid #ef4444; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="ph ph-warning-circle" style="color: #ef4444;"></i>
                                <span style="font-size: 0.875rem; font-weight: 600; color: #991b1b;">
                                    Remaining Balance: $${remaining.toFixed(2)}
                                </span>
                            </div>
                        </div>
                    ` : `
                        <div style="background: rgba(34, 197, 94, 0.1); padding: 12px 16px; border-radius: 8px; border-left: 4px solid #10b981; margin-bottom: 16px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="ph ph-check-circle" style="color: #10b981;"></i>
                                <span style="font-size: 0.875rem; font-weight: 600; color: #166534;">
                                    Fully Paid
                                </span>
                            </div>
                        </div>
                    `}
                </div>
                
                <div style="margin-bottom: 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                        <h3 style="font-size: 1rem; font-weight: 600; color: var(--text-main);">
                            Payment Transactions (${total_payments})
                        </h3>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">
                            <i class="ph ph-clock" style="margin-right: 4px;"></i>
                            Most recent first
                        </div>
                    </div>
                </div>
                
                <div class="payment-history-list">
            `;
            
            payments.forEach((payment, index) => {
                const paymentDate = new Date(payment.paid_at);
                const today = new Date();
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                
                // Check if date is today, yesterday, or within last 7 days
                let dateDisplay = '';
                let dateBadge = '';
                
                if (payment.payment_date === today.toISOString().split('T')[0]) {
                    dateDisplay = 'Today';
                    dateBadge = '<span class="date-badge-today"><i class="ph ph-star"></i> Today</span>';
                } else if (payment.payment_date === yesterday.toISOString().split('T')[0]) {
                    dateDisplay = 'Yesterday';
                    dateBadge = '<span class="date-badge-yesterday"><i class="ph ph-clock"></i> Yesterday</span>';
                } else {
                    const daysDiff = Math.floor((today - paymentDate) / (1000 * 60 * 60 * 24));
                    if (daysDiff <= 7) {
                        dateDisplay = paymentDate.toLocaleDateString('en-US', { weekday: 'long' });
                        dateBadge = `<span class="date-badge-recent"><i class="ph ph-calendar"></i> ${dateDisplay}</span>`;
                    } else {
                        dateDisplay = paymentDate.toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric'
                        });
                    }
                }
                
                // Format time with AM/PM
                const timeDisplay = paymentDate.toLocaleTimeString('en-US', {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
                
                // Determine time period for styling
                const hour = paymentDate.getHours();
                let timePeriod = 'time-morning';
                let timePeriodText = 'Morning';
                
                if (hour >= 12 && hour < 18) {
                    timePeriod = 'time-afternoon';
                    timePeriodText = 'Afternoon';
                } else if (hour >= 18) {
                    timePeriod = 'time-evening';
                    timePeriodText = 'Evening';
                }
                
                // Payment method/paid by display
                const paidByDisplay = payment.paid_by ? 
                    `<div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                        <i class="ph ph-user-circle" style="margin-right: 4px;"></i>
                        ${payment.paid_by}
                    </div>` : '';
                
                // Color index for sequence
                const colorIndex = index % sequenceColors.length;
                
                html += `
                    <div class="payment-history-item">
                        <div class="payment-info">
                            <div class="payment-icon" style="background: ${sequenceColors[colorIndex]}">
                                <span style="font-size: 0.9rem; font-weight: 700;">#${index + 1}</span>
                            </div>
                            <div class="payment-details">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                            <div class="payment-date" style="font-weight: 600; color: var(--text-main);">
                                                ${dateDisplay}
                                            </div>
                                            ${dateBadge}
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                            <span class="time-badge ${timePeriod}">
                                                <i class="ph ph-clock" style="margin-right: 4px;"></i>
                                                ${timeDisplay} • ${timePeriodText}
                                            </span>
                                            ${paidByDisplay}
                                        </div>
                                    </div>
                                    <div class="payment-amount">
                                        $${parseFloat(payment.amount).toFixed(2)}
                                    </div>
                                </div>
                                
                                <!-- Extra details (hidden by default) -->
                                <div style="margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--border); display: none;" class="extra-details">
                                    <div style="display: flex; flex-wrap: wrap; gap: 16px; font-size: 0.75rem;">
                                        <div style="display: flex; align-items: center; gap: 4px; color: var(--text-muted);">
                                            <i class="ph ph-calendar-blank"></i>
                                            <span>${paymentDate.toLocaleDateString('en-US', { 
                                                year: 'numeric', 
                                                month: 'long', 
                                                day: 'numeric',
                                                weekday: 'long' 
                                            })}</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 4px; color: var(--text-muted);">
                                            <i class="ph ph-timer"></i>
                                            <span>${paymentDate.toLocaleTimeString('en-US', { 
                                                hour: '2-digit', 
                                                minute: '2-digit',
                                                second: '2-digit',
                                                hour12: true 
                                            })}</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 4px; color: var(--text-muted);">
                                            <i class="ph ph-calendar-check"></i>
                                            <span>${payment.payment_date}</span>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 4px; color: var(--text-muted);">
                                            <i class="ph ph-hash"></i>
                                            <span>Transaction #${index + 1}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button onclick="togglePaymentDetails(this)" style="background: none; border: none; color: var(--text-muted); cursor: pointer; padding: 4px; border-radius: 4px; flex-shrink: 0;" title="Show more details">
                            <i class="ph ph-caret-down"></i>
                        </button>
                    </div>
                `;
            });
            
            html += `
                </div>
                
                <!-- Summary -->
                <div style="margin-top: 24px; padding: 16px; background: var(--bg-body); border-radius: 12px; border: 1px solid var(--border);">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                        <div style="font-size: 0.875rem; color: var(--text-muted);">Order Total:</div>
                        <div style="font-weight: 700; color: var(--text-main);">$${orderTotal.toFixed(2)}</div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                        <div style="font-size: 0.875rem; color: var(--text-muted);">Total Paid:</div>
                        <div style="font-weight: 700; color: #10b981;">$${totalPaid.toFixed(2)}</div>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
                        <div style="font-size: 0.875rem; color: var(--text-muted);">Remaining:</div>
                        <div style="font-weight: 700; color: ${remaining > 0 ? '#ef4444' : '#10b981'};">$${remaining.toFixed(2)}</div>
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 8px; text-align: center;">
                        <i class="ph ph-info" style="margin-right: 4px;"></i>
                        ${total_payments} payment${total_payments !== 1 ? 's' : ''} recorded • Last updated: ${new Date().toLocaleDateString()}
                    </div>
                </div>
                
                <!-- Download button -->
                <div style="margin-top: 20px; text-align: center;">
                    <button onclick="downloadPaymentHistory(${currentPurchaseId})" class="btn-download">
                        <i class="ph ph-download-simple"></i>
                        <span>Download Report</span>
                    </button>
                </div>
            `;
            
            container.innerHTML = html;
        }

        // Toggle payment details visibility
        function togglePaymentDetails(button) {
            const item = button.closest('.payment-history-item');
            const details = item.querySelector('.extra-details');
            const icon = button.querySelector('i');
            
            if (details.style.display === 'none' || !details.style.display) {
                details.style.display = 'block';
                icon.classList.remove('ph-caret-down');
                icon.classList.add('ph-caret-up');
                button.title = 'Hide details';
            } else {
                details.style.display = 'none';
                icon.classList.remove('ph-caret-up');
                icon.classList.add('ph-caret-down');
                button.title = 'Show more details';
            }
        }

        // Download payment history as CSV
        function downloadPaymentHistory(purchaseId) {
            // Show loading state
            const downloadBtn = document.querySelector('.btn-download');
            const originalHtml = downloadBtn.innerHTML;
            downloadBtn.innerHTML = '<i class="ph ph-spinner"></i> <span>Preparing download...</span>';
            downloadBtn.disabled = true;
            
            // Create and trigger download
            const data = {
                purchase_id: purchaseId,
                timestamp: new Date().toISOString()
            };
            
            // In a real implementation, you would fetch CSV data from server
            // For now, we'll create a simple CSV from visible data
            setTimeout(() => {
                const rows = document.querySelectorAll('.payment-history-item');
                let csvContent = "Date,Time,Amount,Paid By,Status\\n";
                
                rows.forEach(row => {
                    const date = row.querySelector('.payment-date').textContent;
                    const timeElem = row.querySelector('.time-badge');
                    const time = timeElem ? timeElem.textContent.split('•')[0].trim() : '';
                    const amount = row.querySelector('.payment-amount').textContent.replace('$', '');
                    const paidBy = row.querySelector('.payment-by') ? 
                        row.querySelector('.payment-by').textContent.replace('👤', '').trim() : '';
                    
                    csvContent += `"${date}","${time}","${amount}","${paidBy}","Completed"\\n`;
                });
                
                const blob = new Blob([csvContent], { type: 'text/csv' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `payment-history-${purchaseId}-${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                
                // Restore button
                downloadBtn.innerHTML = originalHtml;
                downloadBtn.disabled = false;
            }, 1000);
        }

        // Close history modal on backdrop click
        document.getElementById('historyModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeHistoryModal();
            }
        });

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeHistoryModal();
                closeDeleteModal();
                closeAllDropdowns();
            }
        });
    </script>
</body>
</html>