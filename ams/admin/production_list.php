<?php
/**
 * production_list.php
 * View history of production batches and their costs.
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
    die("DB Connection Failed: " . $ex->getMessage());
}

// --- 3. HANDLE DELETE ---
if (isset($_GET['delete_id'])) {
    $id = intval($_GET['delete_id']);
    try {
        // ON DELETE CASCADE handles items automatically
        $stmt = $pdo->prepare("DELETE FROM production_costs WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: production_list.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $error = "Could not delete: " . $e->getMessage();
    }
}

// --- 4. FETCH DATA (UPDATED WITH JOIN) ---
// We select all columns from production_costs (pc.*) and the company_name from vendors (v)
$sql = "
    SELECT pc.*, v.company_name 
    FROM production_costs pc
    LEFT JOIN vendors v ON pc.vendor_id = v.id
    ORDER BY pc.production_date DESC, pc.id DESC
";
$stmt = $pdo->query($sql);
$productions = $stmt->fetchAll();

// Get items for Modal
$stmtItems = $pdo->query("SELECT * FROM production_items");
$all_items = $stmtItems->fetchAll();
$items_grouped = [];
foreach ($all_items as $item) {
    $items_grouped[$item['production_id']][] = $item;
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// Get user photo for profile dropdown
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production History | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- CSS Variables & Shared Styles --- */
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
            --error: #EF4444;
            --success: #10B981;
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

        /* --- Page Specific Styles --- */
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
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .page-header h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }
        
        .page-header p {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid transparent;
            text-decoration: none;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        
        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        /* Card and Table Styles */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            overflow: hidden;
        }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background: var(--bg-body);
            border-bottom: 2px solid var(--border);
        }
        
        th {
            padding: 16px 20px;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            text-align: left;
            white-space: nowrap;
        }
        
        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 0.9rem;
            color: var(--text-main);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background: var(--bg-body);
        }
        
        /* Action Buttons */
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
            margin-left: 4px;
        }
        
        .action-btn:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .action-btn.delete:hover {
            border-color: var(--error);
            color: var(--error);
            background: rgba(239, 68, 68, 0.1);
        }
        
        /* MODAL STYLES */
        .modal-overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            z-index: 1000; 
            display: none; 
            align-items: center; 
            justify-content: center; 
            backdrop-filter: blur(4px);
        }
        
        .modal-overlay.show { 
            display: flex; 
        }
        
        .modal { 
            background: var(--bg-card); 
            width: 100%; 
            max-width: 600px; 
            border-radius: var(--radius); 
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2); 
            border: 1px solid var(--border); 
            overflow: hidden; 
            animation: slideUp 0.3s ease-out; 
        }
        
        @keyframes slideUp { 
            from { transform: translateY(20px); opacity: 0; } 
            to { transform: translateY(0); opacity: 1; } 
        }
        
        .modal-header { 
            padding: 20px 24px; 
            border-bottom: 1px solid var(--border); 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: var(--bg-body); 
        }
        
        .modal-title { 
            font-weight: 600; 
            font-size: 1.1rem; 
            color: var(--text-main); 
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-close { 
            background: none; 
            border: none; 
            font-size: 1.25rem; 
            cursor: pointer; 
            color: var(--text-muted); 
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover { 
            background: var(--bg-body); 
            color: var(--text-main); 
            transform: rotate(90deg);
        }
        
        .modal-body { 
            padding: 0; 
            max-height: 60vh; 
            overflow-y: auto; 
        }
        
        .modal-footer { 
            padding: 16px 24px; 
            border-top: 1px solid var(--border); 
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-body); 
        }
        
        /* Mini table inside modal */
        .detail-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 16px 24px; 
            border-bottom: 1px solid var(--border); 
            align-items: center;
        }
        
        .detail-row:last-child { 
            border-bottom: none; 
        }
        
        .d-label { 
            font-weight: 500; 
            color: var(--text-main); 
            font-size: 0.95rem;
        }
        
        .d-sub { 
            font-size: 0.85rem; 
            color: var(--text-muted); 
            margin-top: 2px;
        }
        
        .d-price { 
            font-weight: 600; 
            color: var(--primary);
            font-size: 1rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
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
        @media (max-width: 1024px) {
            .card {
                border-radius: 0;
                border-left: none;
                border-right: none;
            }
            
            th, td {
                padding: 12px 16px;
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
            
            .profile-info { 
                display: none; 
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .btn-primary {
                width: 100%;
                justify-content: center;
            }
            
            /* Table responsive */
            .table-wrapper {
                margin: 0 -20px;
                width: calc(100% + 40px);
            }
            
            table {
                min-width: 800px;
            }
            
            .modal {
                margin: 0 16px;
                max-height: 85vh;
            }
        }
        
        @media (max-width: 480px) {
            .scrollable {
                padding: 16px;
            }
            
            th, td {
                padding: 10px 12px;
                font-size: 0.85rem;
            }
            
            .modal-header,
            .modal-footer,
            .detail-row {
                padding-left: 16px;
                padding-right: 16px;
            }
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
        
        .modal-body::-webkit-scrollbar {
            width: 6px;
        }
        
        .modal-body::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .modal-body::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="modal-overlay" id="detailsModal">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="ph ph-factory" style="color: var(--primary);"></i>
                    <span id="modalBatchName">Batch Details</span>
                </div>
                <button class="modal-close" onclick="closeModal()">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="modal-body" id="modalContent">
                <!-- Content will be inserted here -->
            </div>
            <div class="modal-footer">
                <div style="font-size: 0.9rem; color: var(--text-muted);">
                    <i class="ph ph-info" style="margin-right: 6px;"></i>
                    Total items: <span id="itemCount">0</span>
                </div>
                <div style="font-size: 1.25rem; font-weight: 700; color: var(--primary);">
                    $<span id="modalTotal">0.00</span>
                </div>
            </div>
        </div>
    </div>

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
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Production History</span>
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
                    <h1>Production Records</h1>
                    <p>History of all manufactured batches and costs.</p>
                </div>
                <a href="add_production_cost.php" class="btn-primary">
                    <i class="ph ph-plus-circle"></i>
                    <span>New Batch</span>
                </a>
            </div>

            <?php if(isset($_GET['msg']) && $_GET['msg']=='deleted'): ?>
                <div class="alert alert-success">
                    <i class="ph ph-check-circle"></i>
                    <span>Record deleted successfully.</span>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Batch Name</th>
                                <th>Vendor</th>
                                <th>Total Cost</th>
                                <th>Notes</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($productions) > 0): ?>
                                <?php foreach ($productions as $row): 
                                    $pid = $row['id'];
                                    $rowItems = isset($items_grouped[$pid]) ? json_encode($items_grouped[$pid]) : '[]';
                                ?>
                                <tr>
                                    <td>
                                        <span style="font-weight: 500; color: var(--text-main); display: block;">
                                            <?php echo date('M d, Y', strtotime($row['production_date'])); ?>
                                        </span>
                                        <span style="font-size: 0.8rem; color: var(--text-muted);">
                                            <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-weight: 600; color: var(--primary);">
                                            <?php echo e($row['batch_name']); ?>
                                        </span>
                                        <div style="font-size: 0.8rem; color: var(--text-muted);">
                                            ID: #<?php echo $row['id']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if(!empty($row['company_name'])): ?>
                                            <span style="font-weight: 500; display:flex; align-items:center; gap:6px;">
                                                <i class="ph ph-storefront" style="color:var(--text-muted); font-size: 0.9rem;"></i>
                                                <?php echo e($row['company_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-weight: 700; color: var(--primary);">
                                            $<?php echo number_format($row['total_cost'], 2); ?>
                                        </span>
                                    </td>
                                    <td style="max-width: 250px;">
                                        <span style="color: var(--text-muted); font-size: 0.9rem;">
                                            <?php 
                                            $notes = e($row['notes']);
                                            if (strlen($notes) > 40) {
                                                echo substr($notes, 0, 40) . '...';
                                            } else {
                                                echo $notes ?: '—';
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;">
                                        <button class="action-btn" 
                                                onclick='openModal(<?php echo json_encode($row["batch_name"]); ?>, <?php echo $row["total_cost"]; ?>, <?php echo $rowItems; ?>)'
                                                title="View Details">
                                            <i class="ph ph-eye"></i>
                                        </button>
                                        
                                        <a href="?delete_id=<?php echo $row['id']; ?>" 
                                           class="action-btn delete" 
                                           onclick="return confirm('Are you sure? This will delete the batch and all its items.');"
                                           title="Delete">
                                            <i class="ph ph-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="empty-state">
                                        <i class="ph ph-factory"></i>
                                        <p style="margin-bottom: 12px;">No production records found.</p>
                                        <a href="add_production_cost.php" style="color: var(--primary); font-weight: 600;">
                                            <i class="ph ph-plus-circle"></i> Create your first batch
                                        </a>
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

        // --- 5. Modal Logic ---
        const modal = document.getElementById('detailsModal');
        const modalTitle = document.getElementById('modalBatchName');
        const modalContent = document.getElementById('modalContent');
        const modalTotal = document.getElementById('modalTotal');
        const itemCount = document.getElementById('itemCount');

        function openModal(batchName, totalCost, items) {
            modalTitle.innerText = batchName;
            modalTotal.innerText = parseFloat(totalCost).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            
            itemCount.innerText = items.length;
            
            let html = '';
            if(items.length > 0) {
                items.forEach((item, index) => {
                    const subtotal = parseFloat(item.subtotal).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    html += `
                        <div class="detail-row">
                            <div>
                                <div class="d-label">${item.item_name}</div>
                                <div class="d-sub">
                                    ${parseFloat(item.quantity).toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })} units @ $${parseFloat(item.unit_cost).toFixed(2)} each
                                </div>
                            </div>
                            <div class="d-price">$${subtotal}</div>
                        </div>
                    `;
                });
            } else {
                html = '<div class="empty-state" style="padding: 40px 24px;"><i class="ph ph-package"></i><p>No items recorded for this batch.</p></div>';
            }
            
            modalContent.innerHTML = html;
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal() {
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        modal.addEventListener('click', (e) => {
            if (e.target === modal) closeModal();
        });

        // Close modal with Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('show')) {
                closeModal();
            }
        });

        // --- 6. Confirm Delete ---
        document.addEventListener('DOMContentLoaded', function() {
            const deleteLinks = document.querySelectorAll('a.delete');
            deleteLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to delete this production record? This action cannot be undone.')) {
                        e.preventDefault();
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>