<?php
/**
 * vendor_list.php
 * Final Complete Version - ALL DATABASE COLUMNS SHOWN
 * - Features: View Image, Edit, Delete, Search, Export CSV
 * - Columns displayed: ID, Logo+Name, Contact, Email, Phone, Website, Category, Type, Country, City, Address, Status, Created At
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
    die("Database Connection Failed: " . $ex->getMessage());
}

// Helper function
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 3. HANDLE DELETE ACTION ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $deleteId = $_GET['id'];
    
    // Delete Record from Database
    $sql = "DELETE FROM vendors WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $deleteId]);

    // Redirect to refresh page
    header("Location: vendor_list.php");
    exit();
}

// --- 4. LOGIC: SEARCH PARAMETERS ---
$search = $_GET['search'] ?? '';

// --- 5. LOGIC: CSV EXPORT ---
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $sql = "SELECT * FROM vendors WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND (company_name LIKE :s OR contact_person LIKE :s OR email LIKE :s)";
        $params[':s'] = "%$search%";
    }
    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vendors_export_full_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');

    // Dynamically generate CSV headers based on database columns
    if (!empty($rows)) {
        fputcsv($output, array_keys($rows[0]));
    } else {
        // Updated Fallback headers to match all columns
        fputcsv($output, [
            'id', 'company_name', 'contact_person', 'email', 'phone', 
            'website', 'category', 'supply_type', 'country', 'city', 
            'address', 'status', 'logo_url', 'created_at'
        ]);
    }

    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit(); 
}

// --- 6. LOGIC: FETCH VENDORS ---
$vendors = [];
$sql = "SELECT * FROM vendors WHERE 1=1"; 
$params = [];
if ($search) {
    $sql .= " AND (company_name LIKE :s OR contact_person LIKE :s OR email LIKE :s)";
    $params[':s'] = "%$search%";
}
$sql .= " ORDER BY id DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $vendors = $stmt->fetchAll();
} catch (PDOException $e) { 
    // Ignore errors if table doesn't exist yet
}

// Helper function for initials
function getInitials($name) {
    return strtoupper(substr($name ?? '?', 0, 1));
}

// Get user photo for header
$userPhoto = null;
try {
    $stmt = $pdo->prepare("SELECT photo_url FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userPhoto = $stmt->fetchColumn();
} catch (Exception $e) {
    // Ignore if users table doesn't exist
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor List | NexusAdmin</title>
    
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

        /* --- Dashboard Body --- */
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

        /* Toolbar Styles */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
        }
        
        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 300px;
        }
        
        .search-wrap input {
            width: 100%;
            padding: 12px 12px 12px 44px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .search-wrap input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .search-wrap i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.1rem;
        }
        
        .action-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        
        .btn-outline {
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border);
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Card Styles */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 0;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1400px;
        }
        
        th {
            text-align: left;
            padding: 16px 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background: var(--bg-body);
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        
        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            font-size: 0.95rem;
            vertical-align: middle;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background: var(--bg-body);
        }
        
        /* Badge Styles */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }
        
        .badge-active {
            background: rgba(16, 185, 129, 0.12);
            color: #10B981;
        }
        
        .badge-inactive {
            background: rgba(239, 68, 68, 0.12);
            color: #EF4444;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            transition: all 0.2s ease;
            background: var(--bg-body);
        }
        
        .btn-icon:hover {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
            transform: translateY(-1px);
        }
        
        .btn-icon.delete:hover {
            color: #EF4444;
            background: rgba(239, 68, 68, 0.1);
        }
        
        /* Vendor Logo Styles */
        .vendor-logo {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border);
        }
        
        .vendor-logo-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .vendor-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .vendor-name {
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        /* Truncate Text */
        .truncate {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }
        
        .link-url {
            color: var(--primary);
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .link-url:hover {
            text-decoration: underline;
            color: var(--primary-hover);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
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
            .scrollable {
                padding: 24px;
            }
            
            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-wrap {
                min-width: 100%;
            }
            
            .action-group {
                width: 100%;
                justify-content: center;
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
            
            .action-group {
                flex-direction: column;
            }
            
            .btn-primary, .btn-outline {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-wrap {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>

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
            <span style="font-size: 0.9rem; color: var(--text-muted);">All Vendor</span>
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
            <div class="page-header">
                <div>
                    <h1 class="page-title">Vendor List</h1>
                    <p class="page-subtitle">
                        Manage all your vendor relationships • 
                        <span style="color: var(--primary); font-weight: 500;"><?php echo count($vendors); ?> vendors found</span>
                    </p>
                </div>
            </div>

            <div class="toolbar">
                <form method="GET" class="search-wrap">
                    <i class="ph ph-magnifying-glass"></i>
                    <input type="text" name="search" placeholder="Search by company name, contact person, or email..." 
                           value="<?php echo e($search); ?>">
                </form>
                
                <div class="action-group">
                    <a href="?search=<?php echo urlencode($search); ?>&action=export" class="btn-outline">
                        <i class="ph ph-download-simple"></i> Export CSV
                    </a>
                    <a href="add_vendor.php" class="btn-primary">
                        <i class="ph ph-plus"></i> Add New Vendor
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Vendor</th>
                                <th>Contact Person</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Website</th>
                                <th>Category</th>
                                <th>Supply Type</th>
                                <th>Country</th>
                                <th>City</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($vendors)): ?>
                                <?php foreach ($vendors as $v): ?>
                                    <tr>
                                        <td>
                                            <span style="color: var(--text-muted); font-family: monospace; font-size: 0.9rem;">
                                                #<?php echo e($v['id']); ?>
                                            </span>
                                        </td>

                                        <td>
                                            <div class="vendor-info">
                                                <?php if (!empty($v['logo_url'])): ?>
                                                    <img src="<?php echo e($v['logo_url']); ?>" 
                                                         alt="Logo"
                                                         class="vendor-logo">
                                                <?php else: ?>
                                                    <div class="vendor-logo-placeholder">
                                                        <?php echo getInitials($v['company_name']); ?>
                                                    </div>
                                                <?php endif; ?>

                                                <span class="vendor-name"><?php echo e($v['company_name']); ?></span>
                                            </div>
                                        </td>
                                        
                                        <td><?php echo e($v['contact_person']); ?></td>
                                        <td>
                                            <a href="mailto:<?php echo e($v['email']); ?>" class="link-url">
                                                <?php echo e($v['email']); ?>
                                            </a>
                                        </td>
                                        <td style="white-space: nowrap;"><?php echo e($v['phone']); ?></td>
                                        
                                        <td>
                                            <?php if(!empty($v['website'])): ?>
                                                <a href="<?php echo e($v['website']); ?>" target="_blank" class="link-url">
                                                    Visit
                                                </a>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted);">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo e($v['category']); ?></td>
                                        <td><?php echo e($v['supply_type']); ?></td>
                                        <td><?php echo e($v['country']); ?></td>
                                        <td><?php echo e($v['city']); ?></td>
                                        <td>
                                            <span class="truncate" title="<?php echo e($v['address']); ?>">
                                                <?php echo e($v['address']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $v['status'] === 'Active' ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo e($v['status']); ?>
                                            </span>
                                        </td>

                                        <td style="color: var(--text-muted); font-size: 0.9rem; white-space: nowrap;">
                                            <?php echo !empty($v['created_at']) ? date('M d, Y', strtotime($v['created_at'])) : '-'; ?>
                                        </td>

                                        <td style="text-align: right;">
                                            <div class="action-buttons">
                                                <a href="edit_vendor.php?id=<?php echo $v['id']; ?>" class="btn-icon" title="Edit">
                                                    <i class="ph ph-pencil-simple"></i>
                                                </a>
                                                
                                                <a href="vendor_list.php?action=delete&id=<?php echo $v['id']; ?>" 
                                                   class="btn-icon delete" 
                                                   title="Delete"
                                                   onclick="return confirm('Are you sure you want to delete this vendor? This action cannot be undone.');">
                                                    <i class="ph ph-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="14">
                                        <div class="empty-state">
                                            <i class="ph ph-storefront"></i>
                                            <h3 style="margin-bottom: 8px; color: var(--text-main);">No Vendors Found</h3>
                                            <p>No vendors match your search criteria.</p>
                                            <?php if ($search): ?>
                                                <a href="vendor_list.php" style="display: inline-flex; align-items: center; gap: 6px; margin-top: 16px; color: var(--primary); font-weight: 500;">
                                                    <i class="ph ph-arrow-counter-clockwise"></i>
                                                    Clear search
                                                </a>
                                            <?php endif; ?>
                                        </div>
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
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            const link = item.querySelector('.menu-link');
            if (link) {
                link.addEventListener('click', (e) => {
                    if (!link.hasAttribute('href') && !link.querySelector('a')) {
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

        // --- 5. Search Form Submission ---
        const searchInput = document.querySelector('.search-wrap input');
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // --- 6. Auto-focus search on page load ---
        window.addEventListener('load', function() {
            if (searchInput && document.querySelector('input[name="search"]').value) {
                searchInput.focus();
            }
        });

        // --- 7. Confirmation for Delete ---
        document.querySelectorAll('.delete').forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this vendor? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>