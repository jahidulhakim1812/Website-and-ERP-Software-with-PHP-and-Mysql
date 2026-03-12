<?php
/**
 * employee_list.php
 * NexusAdmin V2 - Employee Directory
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
    die("Database Connection Error: " . $ex->getMessage());
}

// --- 3. FETCH DATA & SEARCH ---
$search = trim($_GET['search'] ?? '');
$employees = [];

try {
    if ($search) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE full_name LIKE ? OR email LIKE ? OR department LIKE ? ORDER BY id DESC");
        $term = "%$search%";
        $stmt->execute([$term, $term, $term]);
    } else {
        $stmt = $pdo->query("SELECT * FROM employees ORDER BY id DESC");
    }
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Directory | NexusAdmin</title>
    
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

        /* --- Page Content --- */
        .scrollable { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        /* Search Box */
        .search-box {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .search-input {
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
        
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .btn-search {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-search:hover {
            background: var(--primary-hover);
        }
        
        .btn-clear {
            background: var(--bg-body);
            color: var(--text-muted);
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-clear:hover {
            background: var(--border);
            color: var(--text-main);
        }

        /* Table */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: var(--bg-body);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-main);
            border-bottom: 2px solid var(--border);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
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
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Employee Cells */
        .employee-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .employee-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid var(--border);
        }
        
        .employee-info {
            display: flex;
            flex-direction: column;
        }
        
        .employee-name {
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.95rem;
        }
        
        .employee-email {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 4px;
            white-space: nowrap;
        }
        
        .badge-success {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .badge-warning {
            background: #FEF3C7;
            color: #92400E;
        }
        
        .badge-danger {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        [data-theme="dark"] .badge-success {
            background: #064E3B;
            color: #A7F3D0;
        }
        
        [data-theme="dark"] .badge-warning {
            background: #78350F;
            color: #FDE68A;
        }
        
        [data-theme="dark"] .badge-danger {
            background: #7F1D1D;
            color: #FECACA;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-muted);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
        }
        
        .edit-btn:hover {
            background: #3B82F6;
            color: white;
            border-color: #3B82F6;
        }
        
        .delete-btn:hover {
            background: #EF4444;
            color: white;
            border-color: #EF4444;
        }
        
        .view-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--border);
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        
        .stat-card-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .stat-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 1.2rem;
        }
        
        .stat-card-total .stat-card-icon {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }
        
        .stat-card-active .stat-card-icon {
            background: rgba(16, 185, 129, 0.1);
            color: #10B981;
        }
        
        .stat-card-inactive .stat-card-icon {
            background: rgba(239, 68, 68, 0.1);
            color: #EF4444;
        }
        
        .stat-card-leave .stat-card-icon {
            background: rgba(245, 158, 11, 0.1);
            color: #F59E0B;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: var(--text-muted);
            opacity: 0.5;
            margin-bottom: 16px;
        }
        
        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .empty-state-description {
            color: var(--text-muted);
            max-width: 400px;
            margin: 0 auto 24px auto;
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
            .stats-cards {
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
                padding: 20px; 
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .search-box {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input, .btn-search, .btn-clear {
                width: 100%;
            }
            
            .data-table th, .data-table td {
                padding: 12px;
            }
            
            .action-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .stat-card-value {
                font-size: 1.5rem;
            }
            
            .employee-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- SIDEBAR (SAME AS OTHER FILES) -->
     <?php include 'sidenavbar.php'; ?>
    <main class="main-content">
        
        <!-- HEADER (SAME AS OTHER FILES) -->
        <header class="top-header">
    <div style="display: flex; align-items: center; gap: 16px;">
        <button class="toggle-btn" id="sidebarToggle">
            <i class="ph ph-list"></i>
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
            <span style="font-size: 0.9rem; color: var(--text-muted);">Employee Management</span>
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
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Employee Directory</h1>
                    <p class="page-subtitle">Manage access, view details, and track employee status</p>
                </div>
                <a href="add_employee.php" class="btn-primary">
                    <i class="ph ph-plus"></i>
                    Add New Employee
                </a>
            </div>

            <!-- Statistics Cards -->
            <?php
            // Calculate statistics
            $total_employees = count($employees);
            $active_employees = array_reduce($employees, function($carry, $emp) {
                return $carry + ($emp['status'] === 'Active' ? 1 : 0);
            }, 0);
            $inactive_employees = array_reduce($employees, function($carry, $emp) {
                return $carry + ($emp['status'] === 'Inactive' ? 1 : 0);
            }, 0);
            $on_leave_employees = array_reduce($employees, function($carry, $emp) {
                return $carry + ($emp['status'] === 'On Leave' ? 1 : 0);
            }, 0);
            ?>
            <div class="stats-cards">
                <div class="stat-card stat-card-total">
                    <div class="stat-card-icon">
                        <i class="ph ph-users-three"></i>
                    </div>
                    <div class="stat-card-label">Total Employees</div>
                    <div class="stat-card-value"><?php echo $total_employees; ?></div>
                </div>
                <div class="stat-card stat-card-active">
                    <div class="stat-card-icon">
                        <i class="ph ph-check-circle"></i>
                    </div>
                    <div class="stat-card-label">Active</div>
                    <div class="stat-card-value"><?php echo $active_employees; ?></div>
                </div>
                <div class="stat-card stat-card-inactive">
                    <div class="stat-card-icon">
                        <i class="ph ph-prohibit"></i>
                    </div>
                    <div class="stat-card-label">Inactive</div>
                    <div class="stat-card-value"><?php echo $inactive_employees; ?></div>
                </div>
                <div class="stat-card stat-card-leave">
                    <div class="stat-card-icon">
                        <i class="ph ph-clock"></i>
                    </div>
                    <div class="stat-card-label">On Leave</div>
                    <div class="stat-card-value"><?php echo $on_leave_employees; ?></div>
                </div>
            </div>

            <!-- Search Box -->
            <form method="GET" class="search-box">
                <input type="text" name="search" class="search-input" placeholder="Search by name, email or department..." value="<?php echo e($search); ?>">
                <button type="submit" class="btn-search">
                    <i class="ph ph-magnifying-glass"></i>
                    Search
                </button>
                <?php if($search): ?>
                    <a href="employee_list.php" class="btn-clear">
                        <i class="ph ph-x"></i>
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <!-- Employees Table -->
            <div class="table-container">
                <div class="table-header">
                    <div style="font-weight: 600; color: var(--text-main);">
                        <i class="ph ph-list"></i>
                        Employee Records
                    </div>
                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                        <?php echo $total_employees; ?> total employees
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department & Role</th>
                                <th>Contact</th>
                                <th>Joining Date</th>
                                <th>Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($employees) > 0): ?>
                                <?php foreach ($employees as $emp): ?>
                                <?php 
                                    // Smart image logic
                                    $name = !empty($emp['full_name']) ? $emp['full_name'] : 'Employee';
                                    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff";
                                    
                                    if (!empty($emp['photo'])) {
                                        $dbVal = $emp['photo'];
                                        if (file_exists('uploads/' . $dbVal)) {
                                            $avatarUrl = 'uploads/' . $dbVal;
                                        } elseif (file_exists($dbVal)) {
                                            $avatarUrl = $dbVal;
                                        } elseif (filter_var($dbVal, FILTER_VALIDATE_URL)) {
                                            $avatarUrl = $dbVal;
                                        }
                                    }

                                    // Status badge
                                    $statusClass = 'badge-success';
                                    $icon = 'ph-check-circle';
                                    if ($emp['status'] === 'Inactive') { 
                                        $statusClass = 'badge-danger'; 
                                        $icon = 'ph-prohibit'; 
                                    } elseif ($emp['status'] === 'On Leave') { 
                                        $statusClass = 'badge-warning'; 
                                        $icon = 'ph-clock'; 
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <div class="employee-cell">
                                            <img src="<?php echo e($avatarUrl); ?>" class="employee-avatar" alt="Avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($name); ?>&background=random&color=fff'">
                                            <div class="employee-info">
                                                <div class="employee-name"><?php echo e($emp['full_name']); ?></div>
                                                <div class="employee-email"><?php echo e($emp['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo e($emp['department']); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                                            <i class="ph ph-briefcase"></i> <?php echo e($emp['designation']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($emp['phone']): ?>
                                            <div style="font-weight: 500;"><?php echo e($emp['phone']); ?></div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-style: italic;">No phone</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div><?php echo date('M d, Y', strtotime($emp['joining_date'])); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                                            <?php 
                                            $joinDate = new DateTime($emp['joining_date']);
                                            $today = new DateTime();
                                            $interval = $today->diff($joinDate);
                                            echo $interval->y > 0 ? $interval->y . ' years ago' : ($interval->m > 0 ? $interval->m . ' months ago' : $interval->d . ' days ago');
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--primary);">$<?php echo number_format($emp['salary'], 2); ?></div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">Monthly</div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <i class="ph <?php echo $icon; ?>"></i> <?php echo e($emp['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_employee.php?id=<?php echo $emp['id']; ?>" class="action-btn edit-btn" title="Edit">
                                                <i class="ph ph-pencil-simple"></i>
                                            </a>
                                            <a href="view_employee.php?id=<?php echo $emp['id']; ?>" class="action-btn view-btn" title="View Details">
                                                <i class="ph ph-eye"></i>
                                            </a>
                                            <a href="?delete_id=<?php echo $emp['id']; ?>" class="action-btn delete-btn" title="Delete" onclick="return confirm('Are you sure you want to delete this employee?')">
                                                <i class="ph ph-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <i class="ph ph-users"></i>
                                            </div>
                                            <div class="empty-state-title">No employees found</div>
                                            <div class="empty-state-description">
                                                <?php if($search): ?>
                                                    No employees match your search criteria. Try a different search term.
                                                <?php else: ?>
                                                    You haven't added any employees yet. Add your first employee to get started.
                                                <?php endif; ?>
                                            </div>
                                            <a href="add_employee.php" class="btn-primary">
                                                <i class="ph ph-plus"></i>
                                                Add Your First Employee
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_employees > 10): ?>
                <div style="padding: 20px; border-top: 1px solid var(--border); display: flex; justify-content: center; align-items: center; gap: 16px;">
                    <button class="btn-primary" style="padding: 8px 16px; font-size: 0.9rem;">
                        <i class="ph ph-caret-left"></i> Previous
                    </button>
                    <div style="display: flex; gap: 8px;">
                        <button style="background: var(--primary); color: white; width: 32px; height: 32px; border-radius: 6px; border: none; cursor: pointer;">1</button>
                        <button style="background: var(--bg-body); color: var(--text-main); width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border); cursor: pointer;">2</button>
                        <button style="background: var(--bg-body); color: var(--text-main); width: 32px; height: 32px; border-radius: 6px; border: 1px solid var(--border); cursor: pointer;">3</button>
                    </div>
                    <button class="btn-primary" style="padding: 8px 16px; font-size: 0.9rem;">
                        Next <i class="ph ph-caret-right"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 40px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                &copy; <?php echo date('Y'); ?> NexusAdmin System.
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

        // --- Confirmation for delete actions ---
        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });

        // --- Auto-focus on search input ---
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }

        // --- Calculate and display employee stats dynamically ---
        document.addEventListener('DOMContentLoaded', function() {
            // Update stats cards with animations if needed
            const statValues = document.querySelectorAll('.stat-card-value');
            statValues.forEach(stat => {
                const value = parseInt(stat.textContent);
                if (!isNaN(value)) {
                    stat.textContent = value.toLocaleString();
                }
            });
        });
    </script>
</body>
</html>