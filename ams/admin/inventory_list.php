<?php
/**
 * inventory_list.php
 * NexusAdmin Inventory System
 * Features: Multiple Batch Name Filter (Source: Products Table)
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
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) { 
    die("DB Connection Error: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
function formatMoney($amount) { return '$' . number_format((float)$amount, 2); }
function truncate($text, $chars = 25) {
    if (strlen($text) <= $chars) return $text;
    return substr($text, 0, $chars) . "...";
}

// --- 4. HANDLE ACTIONS (DELETE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([$_POST['delete_id']]);
        $_SESSION['toast'] = ['type'=>'success', 'msg'=>'Product deleted successfully.'];
    } catch (Exception $e) {
        $_SESSION['toast'] = ['type'=>'error', 'msg'=>'Error deleting product.'];
    }
    header("Location: inventory_list.php");
    exit;
}

$search = $_GET['search'] ?? '';
$filter_cat = $_GET['category'] ?? '';
$filter_batches = isset($_GET['batch']) && is_array($_GET['batch']) ? $_GET['batch'] : [];

// --- 5. FETCH DATA ---
$sql = "SELECT p.*, 
               v.company_name as vendor_name
        FROM products p 
        LEFT JOIN vendors v ON p.vendor_id = v.id 
        WHERE (p.product_name LIKE ? OR p.sku LIKE ?)";

$params = ["%$search%", "%$search%"];

// Filter by Category
if (!empty($filter_cat)) {
    $sql .= " AND p.category = ?";
    $params[] = $filter_cat;
}

// Filter by Batch Name (using products table column)
if (!empty($filter_batches)) {
    $placeholders = implode(',', array_fill(0, count($filter_batches), '?'));
    $sql .= " AND p.batch_name IN ($placeholders)";
    $params = array_merge($params, $filter_batches);
}

$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Get Categories for Filter
$cats = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

// Get Batch Names from PRODUCTS table
$batch_names = $pdo->query("SELECT DISTINCT batch_name FROM products WHERE batch_name IS NOT NULL AND batch_name != '' ORDER BY batch_name ASC")->fetchAll(PDO::FETCH_COLUMN);

// Toast Handling
$msg = ''; 
$msgType = '';
if (isset($_SESSION['toast'])) {
    $msg = $_SESSION['toast']['msg'];
    $msgType = $_SESSION['toast']['type'];
    unset($_SESSION['toast']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory List | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

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
            
            --success: #10B981; 
            --error: #EF4444; 
            --warning: #F59E0B;
            --badge-success-bg: #ECFDF5; 
            --badge-success-text: #065F46;
            --badge-error-bg: #FEF2F2; 
            --badge-error-text: #991B1B;
            --badge-warn-bg: #FFFBEB; 
            --badge-warn-text: #92400E;
            --badge-gray-bg: #F3F4F6; 
            --badge-gray-text: #374151;
            --badge-purple-bg: #EEF2FF; 
            --badge-purple-text: #4F46E5;
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
            --badge-success-bg: #064E3B; 
            --badge-success-text: #D1FAE5;
            --badge-error-bg: #7F1D1D; 
            --badge-error-text: #FEE2E2;
            --badge-warn-bg: #78350F; 
            --badge-warn-text: #FEF3C7;
            --badge-gray-bg: #1e293b; 
            --badge-gray-text: #9ca3af;
            --badge-purple-bg: #312E81; 
            --badge-purple-text: #C7D2FE;
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
        
        .submenu-link:hover { 
            color: #fff; 
            background: rgba(255,255,255,0.05); 
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

        /* --- PAGE CONTENT --- */
        .content-scroll { flex: 1; overflow-y: auto; padding: 32px; }
        .card { background: var(--bg-card); border-radius: 0.75rem; border: 1px solid var(--border); box-shadow: var(--shadow); }

        /* Page Header */
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 1.5rem; font-weight: 700; }
        .breadcrumbs { font-size: 0.85rem; color: var(--text-muted); }
        
        .btn-primary { background: var(--primary); color: white; padding: 10px 20px; border-radius: 6px; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }

        /* Filter Bar */
        .filter-bar { background: var(--bg-card); padding: 16px; border-radius: 8px; border: 1px solid var(--border); display: flex; gap: 12px; margin-bottom: 24px; align-items: center; flex-wrap: wrap; }
        .search-wrap { position: relative; flex: 1; min-width: 250px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .form-control { width: 100%; padding: 10px 12px 10px 36px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text-main); font-size: 0.9rem; outline: none; }
        .select-input { padding-left: 12px; width: auto; min-width: 180px; }
        .btn-icon { padding: 10px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text-muted); cursor: pointer; }

        /* Multi-select dropdown styles */
        .multi-select-wrapper { position: relative; min-width: 200px; }
        .multi-select-btn { 
            padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; 
            background: var(--bg-body); color: var(--text-main); font-size: 0.9rem; 
            cursor: pointer; display: flex; justify-content: space-between; align-items: center; gap: 8px;
            min-width: 200px; user-select: none;
        }
        .multi-select-btn:hover { border-color: var(--primary); }
        .multi-select-dropdown {
            position: absolute; top: 110%; left: 0; right: 0; background: var(--bg-card);
            border: 1px solid var(--border); border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            max-height: 250px; overflow-y: auto; z-index: 10; display: none;
        }
        .multi-select-dropdown.show { display: block; }
        .multi-select-option {
            padding: 10px 12px; cursor: pointer; display: flex; align-items: center; gap: 8px;
            transition: 0.2s; font-size: 0.85rem; border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        .multi-select-option:last-child { border-bottom: none; }
        .multi-select-option:hover { background: var(--bg-body); }
        .multi-select-option input[type="checkbox"] { cursor: pointer; accent-color: var(--primary); transform: scale(1.1); }
        .multi-select-count { 
            background: var(--primary); color: white; padding: 2px 8px; 
            border-radius: 12px; font-size: 0.75rem; font-weight: 600;
        }

        /* Table */
        .table-card { background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .table-responsive { overflow-x: auto; white-space: nowrap; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        
        th { background: var(--bg-body); padding: 14px 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--text-muted); border-bottom: 1px solid var(--border); }
        td { padding: 12px 20px; border-bottom: 1px solid var(--border); font-size: 0.85rem; vertical-align: middle; }
        tr:hover td { background: var(--bg-body); }

        .product-img { width: 32px; height: 32px; border-radius: 4px; object-fit: cover; background: var(--bg-body); border: 1px solid var(--border); vertical-align: middle; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .badge.success { background: var(--badge-success-bg); color: var(--badge-success-text); }
        .badge.error { background: var(--badge-error-bg); color: var(--badge-error-text); }
        .badge.warn { background: var(--badge-warn-bg); color: var(--badge-warn-text); }
        .badge.gray { background: var(--badge-gray-bg); color: var(--badge-gray-text); border: 1px solid var(--border); }
        .badge.purple { background: var(--badge-purple-bg); color: var(--badge-purple-text); }

        .action-btn { background: none; border: none; cursor: pointer; padding: 6px; border-radius: 4px; color: var(--text-muted); transition: 0.2s; font-size: 1.1rem; }
        .action-btn:hover { background: var(--bg-body); color: var(--primary); }
        .action-btn.delete:hover { color: var(--error); background: #FEF2F2; }

        .toast { position: fixed; top: 20px; right: 20px; background: var(--bg-card); padding: 16px 20px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); border-left: 4px solid var(--primary); display: flex; align-items: center; gap: 10px; transform: translateX(120%); transition: 0.3s; z-index: 100; }
        .toast.show { transform: translateX(0); }
        .toast.error { border-left-color: var(--error); }
        .toast.success { border-left-color: var(--success); }

        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 45; display: none; animation: fadeIn 0.3s ease; }
        
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
            .filter-bar { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body id="body">

    <div class="overlay" id="overlay"></div>

     <?php include 'sidenavbar.php'; ?>


    <main class="main-content">
        
        <header class="top-header">
    <div style="display: flex; align-items: center; gap: 16px;">
        <button class="toggle-btn" id="sidebarToggle">
            <i class="ph ph-list"></i>
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
            <span style="font-size: 0.9rem; color: var(--text-muted);">Inventory List</span>
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


        <div class="content-scroll">
            
            <div id="toast" class="toast <?php echo $msgType ? 'show ' . $msgType : ''; ?>">
                <i class="ph <?php echo $msgType=='error'?'ph-warning-circle':'ph-check-circle'; ?>" style="font-size: 1.5rem;"></i>
                <span><?php echo $msg; ?></span>
            </div>

            <div class="page-header">
                <div>
                    <h1 class="page-title">Inventory List</h1>
                    <div class="breadcrumbs">Inventory / Full Database View</div>
                </div>
                <a href="add_product.php" class="btn-primary">
                    <i class="ph ph-plus"></i> Add Product
                </a>
            </div>

            <form method="GET" class="filter-bar" id="filterForm">
                <div class="search-wrap">
                    <i class="ph ph-magnifying-glass search-icon"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search by name or SKU..." value="<?php echo e($search); ?>">
                </div>
                
                <select name="category" class="form-control select-input" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach($cats as $cat): ?>
                        <option value="<?php echo e($cat); ?>" <?php echo $filter_cat === $cat ? 'selected' : ''; ?>>
                            <?php echo e($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <div class="multi-select-wrapper">
                    <div class="multi-select-btn" id="batchBtn" onclick="toggleBatchDropdown(event)">
                        <span id="batchBtnText">
                            <?php if(empty($filter_batches)): ?>
                                All Batches
                            <?php else: ?>
                                <span class="multi-select-count"><?php echo count($filter_batches); ?></span> Batch<?php echo count($filter_batches) > 1 ? 'es' : ''; ?> Selected
                            <?php endif; ?>
                        </span>
                        <i class="ph ph-caret-down"></i>
                    </div>
                    <div class="multi-select-dropdown" id="batchDropdown">
                        <?php foreach($batch_names as $batch): ?>
                            <label class="multi-select-option">
                                <input 
                                    type="checkbox" 
                                    name="batch[]" 
                                    value="<?php echo e($batch); ?>"
                                    <?php echo in_array($batch, $filter_batches) ? 'checked' : ''; ?>
                                    onchange="updateBatchFilter()"
                                >
                                <span><?php echo e($batch); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <?php if(!empty($search) || !empty($filter_cat) || !empty($filter_batches)): ?>
                    <a href="inventory_list.php" class="btn-icon" title="Reset Filters"><i class="ph ph-x"></i></a>
                <?php endif; ?>
            </form>

            <div class="table-card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Vendor</th>
                                <th>Batch Name</th> <th>Description</th>
                                <th>Qty</th>
                                <th>Min</th>
                                <th>Max</th>
                                <th>Unit</th>
                                <th>Buy Price</th>
                                <th>Sell Price</th>
                                <th>Status</th>
                                <th style="text-align: right; position:sticky; right:0; background:var(--bg-card); z-index:2; border-left:1px solid var(--border);">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($products) > 0): ?>
                                <?php foreach($products as $p): ?>
                                    <tr>
                                        <td>#<?php echo e($p['id']); ?></td>
                                        
                                        <td>
                                            <?php if(!empty($p['image_url'])): ?>
                                                <img src="<?php echo e($p['image_url']); ?>" class="product-img" alt="Img">
                                            <?php else: ?>
                                                <div class="product-img" style="display:flex;align-items:center;justify-content:center;color:#ccc;"><i class="ph ph-image"></i></div>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td style="font-weight: 600; color:var(--text-main);"><?php echo e($p['product_name']); ?></td>
                                        <td style="color:var(--text-muted);"><?php echo e($p['sku']); ?></td>
                                        <td><span class="badge gray"><?php echo e($p['category']); ?></span></td>
                                        <td><?php echo e($p['vendor_name'] ?? '-'); ?></td>
                                        
                                        <td>
                                            <?php if(!empty($p['batch_name'])): ?>
                                                <span class="badge purple"><?php echo e($p['batch_name']); ?></span>
                                            <?php else: ?>
                                                <span style="color:var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        
                                        <td title="<?php echo e($p['description']); ?>" style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?php echo e(truncate($p['description'], 30)); ?>
                                        </td>
                                        
                                        <td>
                                            <?php 
                                            $qty = (int)$p['quantity'];
                                            $min = (int)$p['min_stock'];
                                            if ($qty <= 0) echo '<span style="color:var(--error); font-weight:700;">' . $qty . '</span>';
                                            elseif ($qty <= $min) echo '<span style="color:var(--warning); font-weight:700;">' . $qty . '</span>';
                                            else echo $qty;
                                            ?>
                                        </td>
                                        <td><?php echo e($p['min_stock']); ?></td>
                                        <td><?php echo e($p['max_stock']); ?></td>
                                        <td><?php echo e($p['unit_type']); ?></td>
                                        
                                        <td><?php echo formatMoney($p['purchase_price']); ?></td>
                                        <td style="font-weight:600;"><?php echo formatMoney($p['selling_price']); ?></td>
                                        
                                        <td>
                                            <?php if($p['status'] === 'Active'): ?>
                                                <span class="badge success">Active</span>
                                            <?php elseif($p['status'] === 'Inactive'): ?>
                                                <span class="badge error">Inactive</span>
                                            <?php else: ?>
                                                <span class="badge gray"><?php echo e($p['status']); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <td style="text-align: right; position:sticky; right:0; background:var(--bg-card); z-index:2; border-left:1px solid var(--border);">
                                            <a href="edit_product.php?id=<?php echo $p['id']; ?>" class="action-btn" title="Edit">
                                                <i class="ph ph-pencil-simple"></i>
                                            </a>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete ID #<?php echo $p['id']; ?>?');">
                                                <input type="hidden" name="delete_id" value="<?php echo $p['id']; ?>">
                                                <button type="submit" class="action-btn delete" title="Delete">
                                                    <i class="ph ph-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="16" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        No records found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </main>

    <script>
        // --- Sidebar Logic (From Admin Dashboard) ---
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

        // --- Accordion Logic (From Admin Dashboard) ---
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

        // --- Profile Dropdown Logic (From Admin Dashboard) ---
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

        // --- Dark Mode Logic (From Admin Dashboard) ---
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

        // Escape key to close dropdowns
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });

        // --- Batch Filter Logic (From Original Inventory List) ---
        function toggleBatchDropdown(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('batchDropdown');
            dropdown.classList.toggle('show');
        }

        function updateBatchFilter() {
            document.getElementById('filterForm').submit();
        }

        // Close batch dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('batchDropdown');
            const btn = document.getElementById('batchBtn');
            const options = document.querySelectorAll('.multi-select-option');
            
            let clickedOption = false;
            options.forEach(opt => {
                if(opt.contains(event.target)) clickedOption = true;
            });

            if (!btn.contains(event.target) && !dropdown.contains(event.target) && !clickedOption) {
                dropdown.classList.remove('show');
            }
        });

        // Toast Fade Out
        const toast = document.getElementById('toast');
        if (toast && toast.classList.contains('show')) {
            setTimeout(() => { toast.classList.remove('show'); }, 3000);
        }
    </script>
</body>
</html>