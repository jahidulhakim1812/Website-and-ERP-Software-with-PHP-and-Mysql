<?php
/**
 * add_sale.php
 * Purpose: Create new Invoice, deduct stock, record payment.
 * Design: NexusAdmin (Perfect Dark Mode + Functional Toggles)
 */

session_start();

// --- 1. AUTHENTICATION & SECURITY ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$timeout = 1800;
if (isset($_SESSION['login_time'])) {
    $session_life = time() - $_SESSION['login_time'];
    if ($session_life > $timeout) {
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }
}
$_SESSION['login_time'] = time();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header_remove();
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Location: login.php");
    exit();
}

// --- 2. DATABASE CONNECTION ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $ex) {
    $pdo = null;
    $dbError = $ex->getMessage();
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 3. DATA FETCHING ---
$customers = [];
$products = [];
$generated_reference = 'INV-' . strtoupper(uniqid());

if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, full_name FROM customers ORDER BY full_name ASC");
        $customers = $stmt->fetchAll();
    } catch (Exception $e) {}

    try {
        $stmt = $pdo->query("SELECT * FROM products WHERE status = 'Active' ORDER BY product_name ASC");
        $products = $stmt->fetchAll();
    } catch (Exception $e) {}

    try {
        $stmt = $pdo->query("SELECT id FROM purchase_orders ORDER BY id DESC LIMIT 1");
        $lastOrder = $stmt->fetch();
        $nextId = ($lastOrder) ? $lastOrder['id'] + 1 : 1;
        $generated_reference = 'INV-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    } catch (Exception $e) {}
}

// --- 4. HANDLE FORM SUBMISSION ---
$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    try {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
            throw new Exception("Security mismatch. Please refresh the page and try again.");
        }

        if (empty($_POST['customer_id'])) throw new Exception("Please select a customer.");
        if (empty($_POST['product_id']) || !is_array($_POST['product_id'])) throw new Exception("No products selected.");

        // --- FIX: Map the submitted status to the exact database value ---
        // Adjust this array to match the actual values in your database 'status' column.
        // Run: SHOW COLUMNS FROM purchase_orders LIKE 'status';
        $db_status_map = [
            'Completed' => 'completed',   // example: if your ENUM uses lowercase
            'Pending'   => 'pending',
            'Shipped'   => 'shipped'
        ];
        $submitted_status = $_POST['status'] ?? 'Pending';
        $status = $db_status_map[$submitted_status] ?? 'pending'; // fallback

        $pdo->beginTransaction();

        $grand_total = 0;
        $product_ids = $_POST['product_id'];
        $quantities  = $_POST['quantity'];
        $unit_costs  = $_POST['unit_cost'];
        $count       = count($product_ids);
        
        $first_product_name = null;

        for ($i = 0; $i < $count; $i++) {
            $pid  = $product_ids[$i];
            $qty  = floatval($quantities[$i]);
            $cost = floatval($unit_costs[$i]);

            if (empty($pid) || $qty <= 0) continue;

            $stmtCheck = $pdo->prepare("SELECT product_name, quantity FROM products WHERE id = ? FOR UPDATE");
            $stmtCheck->execute([$pid]);
            $prodData = $stmtCheck->fetch();

            if (!$prodData) throw new Exception("Product ID $pid not found.");
            
            if ($first_product_name === null) {
                $first_product_name = $prodData['product_name'];
            }
            
            if ($qty > $prodData['quantity']) {
                throw new Exception("Insufficient stock for '{$prodData['product_name']}'. Available: {$prodData['quantity']}, Requested: $qty");
            }
            $grand_total += ($qty * $cost);
        }

        $paid_amount = floatval($_POST['paid_amount']);
        $payment_status = 'Unpaid';
        if ($grand_total > 0) {
            if ($paid_amount >= $grand_total) $payment_status = 'Paid';
            elseif ($paid_amount > 0) $payment_status = 'Partial';
        }

        $sqlHeader = "INSERT INTO purchase_orders (customer_id, product_name, reference_no, order_date, total_amount, paid_amount, status, payment_status, note, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmtHeader = $pdo->prepare($sqlHeader);
        $stmtHeader->execute([$_POST['customer_id'], $first_product_name, $_POST['reference_no'], $_POST['order_date'], $grand_total, $paid_amount, $status, $payment_status, $_POST['note']]);
        $purchase_id = $pdo->lastInsertId();

        $sqlItem  = "INSERT INTO purchase_order_items (purchase_id, product_id, quantity, unit_cost, subtotal) VALUES (?, ?, ?, ?, ?)";
        $stmtItem = $pdo->prepare($sqlItem);
        $sqlDeduct = "UPDATE products SET quantity = quantity - ? WHERE id = ?";
        $stmtDeduct = $pdo->prepare($sqlDeduct);

        for ($i = 0; $i < $count; $i++) {
            $pid = $product_ids[$i];
            $qty = floatval($quantities[$i]);
            $cost = floatval($unit_costs[$i]);
            if (empty($pid) || $qty <= 0) continue;

            $stmtItem->execute([$purchase_id, $pid, $qty, $cost, ($qty * $cost)]);
            $stmtDeduct->execute([$qty, $pid]);
        }

        $pdo->commit();
        $message = '<div class="bg-green-100 border border-green-400 text-green-700 dark:bg-green-900/50 dark:border-green-500 dark:text-green-200 px-4 py-3 rounded relative mb-4 flex items-center justify-between"><div class="flex items-center"><i class="fa-solid fa-circle-check mr-2"></i><span><strong>Success!</strong> Sale saved. Ref: ' . e($_POST['reference_no']) . '</span></div></div>';
        $generated_reference = 'INV-' . str_pad($purchase_id + 1, 6, '0', STR_PAD_LEFT);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Sale insertion failed: " . $e->getMessage());
        $message = '<div class="bg-red-100 border border-red-400 text-red-700 dark:bg-red-900/50 dark:border-red-500 dark:text-red-200 px-4 py-3 rounded relative mb-4"><i class="fa-solid fa-triangle-exclamation mr-2"></i> ' . $e->getMessage() . '</div>';
    }
} elseif (!$pdo && isset($dbError)) {
    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">DB Error: '.$dbError.'</div>';
}

// --- The rest of the HTML (unchanged) ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Nexus V2</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

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
            
            /* Financial Colors */
            --color-revenue: #10B981;
            --color-hold: #F59E0B;
            --color-expense: #EF4444;
            --color-profit: #3B82F6;
            --color-salary: #8B5CF6;
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

        /* --- Form Styles --- */
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
        
        .card { 
            background: var(--bg-card); 
            border-radius: var(--radius); 
            padding: 28px; 
            border: 1px solid var(--border); 
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            margin-bottom: 24px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: var(--border);
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
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }
        
        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-card);
            border-color: var(--primary);
        }
        
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-card);
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-muted);
            background: var(--bg-body);
            border-bottom: 1px solid var(--border);
        }
        
        .table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: top;
        }
        
        .table tr:hover {
            background: var(--bg-body);
        }
        
        /* Select2 Customization */
        .select2-container .select2-selection--single {
            height: 42px !important;
            border: 1px solid var(--border) !important;
            background: var(--bg-card) !important;
            border-radius: 8px !important;
        }
        
        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 40px !important;
            padding-left: 14px !important;
            color: var(--text-main) !important;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
        }
        
        .select2-dropdown {
            background: var(--bg-card) !important;
            border: 1px solid var(--border) !important;
            border-radius: 8px !important;
            margin-top: 4px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .select2-results__option {
            padding: 10px 14px !important;
            color: var(--text-main) !important;
        }
        
        .select2-results__option--highlighted {
            background: var(--primary) !important;
            color: white !important;
        }
        
        /* Stock status */
        .stock-ok { color: #10B981; font-size: 0.75rem; font-weight: 600; display: block; margin-top: 4px; } 
        .stock-low { color: #ef4444; font-size: 0.75rem; font-weight: 600; display: block; margin-top: 4px; }
        
        /* Overlay */
        .overlay { 
            position: fixed; 
            inset: 0; 
            background: rgba(0,0,0,0.5); 
            z-index: 40; 
            display: none; 
        }
        
        .overlay.active { 
            display: block; 
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

        /* Responsive */
        @media (max-width: 768px) {
            .scrollable { 
                padding: 24px; 
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
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
            
            .top-header { 
                padding: 0 20px; 
            }
            
            .profile-info { 
                display: none; 
            }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- EXACT SAME SIDEBAR FROM admin_dashboard.php -->
    <?php include 'sidenavbar.php'; ?>
    <main class="main-content">
        <!-- EXACT SAME HEADER FROM admin_dashboard.php -->
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Add Sale</span>
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
            <div class="page-header">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 700; margin-bottom: 4px;">Create Invoice</h1>
                    <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.4;">
                        Enter sale details, select products, and process payment.
                    </p>
                </div>
            </div>

            <?php echo $message; ?>

            <form method="POST" id="saleForm" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="card">
                    <div style="display: flex; align-items: center; margin-bottom: 20px;">
                        <i class="ph ph-user" style="color: var(--primary); font-size: 1.2rem; margin-right: 10px;"></i>
                        <h3 style="font-weight: 600; color: var(--text-main);">Order Details</h3>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(1, 1fr); gap: 20px;">
                        <?php if (isset($_GET['layout']) && $_GET['layout'] === 'horizontal'): ?>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px;">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label class="form-label">Customer <span style="color: #ef4444;">*</span></label>
                            <select name="customer_id" class="form-input select2-init" required style="width: 100%;">
                                <option value="">Select Customer...</option>
                                <?php foreach($customers as $c): ?>
                                    <option value="<?php echo $c['id']; ?>"><?php echo e($c['full_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Reference No.</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);">
                                    <i class="ph ph-hash" style="font-size: 0.9rem;"></i>
                                </span>
                                <input type="text" name="reference_no" class="form-input" style="padding-left: 34px;" value="<?php echo $generated_reference; ?>" readonly>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date</label>
                            <input type="date" name="order_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Order Status</label>
                            <select name="status" class="form-input">
                                <option value="Completed">Completed</option>
                                <option value="Pending">Pending</option>
                                <option value="Shipped">Shipped</option>
                            </select>
                        </div>
                        
                        <?php if (isset($_GET['layout']) && $_GET['layout'] === 'horizontal'): ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <div style="display: flex; align-items: center;">
                            <i class="ph ph-cart" style="color: var(--primary); font-size: 1.2rem; margin-right: 10px;"></i>
                            <h3 style="font-weight: 600; color: var(--text-main);">Order Items</h3>
                        </div>
                        <button type="button" class="btn btn-secondary" onclick="addRow()" style="padding: 8px 16px; font-size: 0.875rem;">
                            <i class="ph ph-plus" style="margin-right: 6px;"></i> Add Row
                        </button>
                    </div>
                    
                    <div class="table-container">
                        <table class="table" id="itemsTable">
                            <thead>
                                <tr>
                                    <th style="width: 45%;">Product</th>
                                    <th style="width: 15%;">Unit Cost</th>
                                    <th style="width: 15%;">Quantity</th>
                                    <th style="width: 15%;">Subtotal</th>
                                    <th style="width: 10%; text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr class="item-row">
                                    <td>
                                        <select name="product_id[]" class="form-input select2-product" onchange="productChanged(this)" required style="width: 100%;">
                                            <option value="" data-price="0" data-stock="0">Search Product...</option>
                                            <?php foreach($products as $p): 
                                                $price = $p['selling_price'] ?? $p['price'] ?? 0;
                                            ?>
                                                <option value="<?php echo $p['id']; ?>" 
                                                        data-price="<?php echo $price; ?>" 
                                                        data-stock="<?php echo $p['quantity']; ?>">
                                                    <?php echo e($p['product_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div style="margin-top: 4px; height: 18px;">
                                            <span class="stock-info"></span>
                                        </div>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="unit_cost[]" class="form-input input-cost" oninput="calculateRow(this)" placeholder="0.00" required style="width: 100%;">
                                    </td>
                                    <td>
                                        <input type="number" name="quantity[]" class="form-input input-qty" value="1" min="1" oninput="calculateRow(this)" required style="width: 100%;">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="subtotal[]" class="form-input input-subtotal" style="background: var(--bg-body); border-color: var(--border); font-weight: 600; color: var(--primary); width: 100%;" placeholder="0.00" readonly>
                                    </td>
                                    <td style="text-align: center;">
                                        <button type="button" class="btn btn-danger" onclick="removeRow(this)" style="padding: 6px 12px; font-size: 0.875rem;">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div style="margin-top: 20px; padding: 20px; border: 2px dashed var(--border); border-radius: 8px; text-align: center;">
                        <button type="button" class="btn btn-secondary" onclick="addRow()" style="background: transparent; border: none; color: var(--text-muted); font-size: 0.95rem; width: 100%; padding: 12px;">
                            <i class="ph ph-plus-circle" style="margin-right: 8px; font-size: 1.1rem;"></i> Click to Add Another Item
                        </button>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                    <div>
                        <div class="card">
                            <label class="form-label">Internal Note / Delivery Instructions</label>
                            <textarea name="note" class="form-input" style="height: 140px; resize: vertical;" placeholder="Enter details here..."></textarea>
                        </div>
                    </div>

                    <div>
                        <div class="card">
                            <div class="form-group">
                                <label class="form-label">Amount Paid</label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted);">$</span>
                                    <input type="number" step="0.01" name="paid_amount" id="paidInput" class="form-input" style="padding-left: 28px;" placeholder="0.00" oninput="updatePaymentStatus()">
                                </div>
                                <div id="paymentStatus" style="text-align: right; font-size: 0.875rem; font-weight: 600; margin-top: 8px; color: #ef4444;">Status: Unpaid</div>
                            </div>

                            <hr style="border-color: var(--border); margin: 20px 0;">

                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                                <span style="font-size: 1.1rem; font-weight: 600; color: var(--text-main);">Grand Total</span>
                                <span style="font-size: 1.8rem; font-weight: 700; color: var(--text-main);">$<span id="grandTotalText">0.00</span></span>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 14px; font-size: 1rem;">
                                <i class="ph ph-check-circle" style="margin-right: 8px;"></i> Complete Sale
                            </button>
                        </div>
                    </div>
                </div>
            </form>
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

        // --- 5. Form JavaScript ---
        $(document).ready(function() {
            $('.select2-init').select2({
                placeholder: "Select Customer...",
                width: '100%'
            });
            
            initProductSelect($('.select2-product'));
            
            calculateGrandTotal();
        });

        function initProductSelect(element) {
            $(element).select2({
                placeholder: "Select Product...",
                width: '100%'
            });
            
            $(element).on('select2:select', function (e) {
                productChanged(this);
            });
        }

        function addRow() {
            const tableBody = $('#itemsTable tbody');
            const firstRow = tableBody.find('tr:first');
            const newRow = firstRow.clone();

            newRow.find('input').val('');
            newRow.find('.input-qty').val('1');
            newRow.find('.stock-info').html('');
            newRow.find('.input-subtotal').val('');

            const selectInfo = newRow.find('select');
            selectInfo.val(null);
            selectInfo.removeClass('select2-hidden-accessible')
                     .removeAttr('data-select2-id')
                     .removeAttr('tabindex')
                     .removeAttr('aria-hidden');

            newRow.find('.select2-container').remove();

            tableBody.append(newRow);
            
            initProductSelect(selectInfo);
        }

        function removeRow(btn) {
            if ($('#itemsTable tbody tr').length > 1) {
                $(btn).closest('tr').remove();
                calculateGrandTotal();
            } else {
                alert("You need at least one item.");
            }
        }

        function productChanged(selectElement) {
            const option = $(selectElement).find(':selected');
            const row = $(selectElement).closest('tr');
            
            const price = parseFloat(option.data('price')) || 0;
            const stock = parseInt(option.data('stock')) || 0;

            row.find('.input-cost').val(price.toFixed(2));
            
            const stockSpan = row.find('.stock-info');
            if (stock > 0) {
                stockSpan.html(`<span class="stock-ok"><i class="ph ph-check"></i> ${stock} In Stock</span>`);
            } else {
                stockSpan.html(`<span class="stock-low"><i class="ph ph-warning"></i> Out of Stock</span>`);
            }

            calculateRow(selectElement);
        }

        function calculateRow(element) {
            const row = $(element).closest('tr');
            const qty = parseFloat(row.find('.input-qty').val()) || 0;
            const cost = parseFloat(row.find('.input-cost').val()) || 0;
            
            const subtotal = qty * cost;
            row.find('.input-subtotal').val(subtotal.toFixed(2));
            
            calculateGrandTotal();
        }

        function calculateGrandTotal() {
            let total = 0;
            $('.input-subtotal').each(function() {
                total += parseFloat($(this).val()) || 0;
            });
            $('#grandTotalText').text(total.toFixed(2));
            updatePaymentStatus();
        }

        function updatePaymentStatus() {
            const total = parseFloat($('#grandTotalText').text()) || 0;
            const paid = parseFloat($('#paidInput').val()) || 0;
            const statusEl = $('#paymentStatus');

            statusEl.removeClass('text-green-500 text-yellow-500 text-red-500');
            
            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                statusEl.removeClass('dark:text-green-400 dark:text-yellow-400 dark:text-red-400 dark:text-gray-400');
            }

            if (total === 0) {
                statusEl.text('Status: N/A');
                if (document.documentElement.getAttribute('data-theme') === 'dark') {
                    statusEl.addClass('dark:text-gray-400');
                } else {
                    statusEl.addClass('text-gray-400');
                }
            } else if (paid >= total) {
                statusEl.text('Status: Paid');
                if (document.documentElement.getAttribute('data-theme') === 'dark') {
                    statusEl.addClass('dark:text-green-400');
                } else {
                    statusEl.addClass('text-green-500');
                }
            } else if (paid > 0) {
                statusEl.text('Status: Partial');
                if (document.documentElement.getAttribute('data-theme') === 'dark') {
                    statusEl.addClass('dark:text-yellow-400');
                } else {
                    statusEl.addClass('text-yellow-500');
                }
            } else {
                statusEl.text('Status: Unpaid');
                if (document.documentElement.getAttribute('data-theme') === 'dark') {
                    statusEl.addClass('dark:text-red-400');
                } else {
                    statusEl.addClass('text-red-500');
                }
            }
        }

        function validateForm() {
            let valid = true;
            $('.select2-product').each(function() {
                if(!$(this).val()) {
                    valid = false;
                    $(this).css('border-color', '#ef4444');
                } else {
                    $(this).css('border-color', '');
                }
            });
            
            if(!valid) {
                alert("Please select a product for all rows.");
                return false;
            }
            
            if(!$('select[name="customer_id"]').val()) {
                alert("Please select a customer.");
                $('select[name="customer_id"]').css('border-color', '#ef4444');
                return false;
            }
            
            return true;
        }
    </script>
</body>
</html>