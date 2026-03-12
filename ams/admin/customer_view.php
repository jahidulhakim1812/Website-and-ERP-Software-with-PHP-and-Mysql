<?php
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


// --- 1. DATABASE CONNECTION ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

// --- 2. SEARCH LOGIC ---
$view_mode = 'search'; 
$results = [];
$customer = null;
$error = '';
$search_query = $_GET['q'] ?? '';

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $customer = $stmt->fetch();
    if ($customer) $view_mode = 'detail';
    else $error = "Customer ID not found.";
} elseif (!empty($search_query)) {
    $sql = "SELECT * FROM customers WHERE full_name LIKE ? OR phone LIKE ? OR id = ?";
    $stmt = $pdo->prepare($sql);
    $like = "%$search_query%";
    $stmt->execute([$like, $like, $search_query]); 
    $results = $stmt->fetchAll();
    $view_mode = 'list';
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Search & Print | NexusAdmin</title>
    
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
            --shadow-dropdown: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
            --shadow-card: 0 8px 30px rgba(0, 0, 0, 0.08);
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

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; }
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
            z-index: 100;
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
            border: none;
            background: none;
            width: 100%;
        }
        
        .menu-link:hover, 
        .menu-link.active { 
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
        
        .submenu-link:hover,
        .submenu-link.active { 
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
            z-index: 50;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .page-dot {
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: 50%;
            flex-shrink: 0;
        }

        .page-text {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .toggle-btn { 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            cursor: pointer; 
            color: var(--text-muted); 
            display: flex; 
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            padding: 8px;
            border-radius: 8px;
            width: 40px;
            height: 40px;
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
            gap: 20px; 
        }
        
        .profile-container { 
            position: relative; 
        }
        
        .profile-menu { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            cursor: pointer; 
            padding: 6px 12px; 
            border-radius: 12px; 
            transition: all 0.2s ease;
            background: transparent;
            border: none;
            color: inherit;
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
            width: 40px; 
            height: 40px; 
            border-radius: 10px; 
            object-fit: cover; 
            border: 2px solid var(--border); 
            transition: all 0.2s ease;
        }
        
        .profile-placeholder { 
            width: 40px; 
            height: 40px; 
            border-radius: 10px; 
            background: var(--primary); 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            font-size: 1.1rem;
            border: 2px solid var(--border);
        }

        .dropdown-menu {
            position: absolute; 
            top: calc(100% + 8px); 
            right: 0; 
            width: 220px;
            background: var(--bg-card); 
            border: 1px solid var(--border);
            border-radius: 12px; 
            box-shadow: var(--shadow-dropdown);
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
            text-decoration: none;
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
            cursor: pointer;
        }
        
        #themeToggle:hover {
            transform: rotate(15deg);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* --- CONTENT AREA --- */
        .content-scroll { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }
        
        /* CENTERED SEARCH CONTAINER */
        .centered-search-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
            width: 100%;
        }

        .search-container { 
            width: 100%; 
            max-width: 700px; 
            margin: 0 auto;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 50px 40px;
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow-card);
            transition: all 0.3s ease;
        }

        .search-container:hover {
            transform: translateY(-4px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }

        .search-icon {
            font-size: 3.5rem;
            color: var(--primary);
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .search-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 12px;
            background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.5px;
        }

        .search-subtitle {
            color: var(--text-muted);
            margin-bottom: 40px;
            font-size: 1rem;
            max-width: 500px;
            line-height: 1.6;
            text-align: center;
        }

        .search-box { 
            display: flex; 
            gap: 12px; 
            background: var(--bg-body); 
            padding: 6px; 
            border-radius: 50px; 
            border: 1px solid var(--border); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            width: 100%;
        }

        .search-box:focus-within {
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            border-color: var(--primary);
        }

        .search-input { 
            flex: 1; 
            border: none; 
            outline: none; 
            padding: 16px 24px; 
            font-size: 1.05rem; 
            background: transparent; 
            color: var(--text-main); 
            border-radius: 40px;
        }

        .search-input::placeholder {
            color: var(--text-muted);
            opacity: 0.7;
        }

        .search-btn { 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 16px 36px; 
            border-radius: 40px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 1rem; 
            transition: all 0.2s; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            white-space: nowrap;
        }

        .search-btn:hover { 
            background: var(--primary-hover); 
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.3);
        }

        .search-tips {
            margin-top: 25px;
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }

        .search-tip {
            background: var(--bg-body);
            padding: 6px 12px;
            border-radius: 20px;
            border: 1px solid var(--border);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Results Card */
        .results-card { 
            background: var(--bg-card); 
            width: 100%; 
            max-width: 800px; 
            border-radius: 12px; 
            box-shadow: var(--shadow); 
            border: 1px solid var(--border); 
            overflow: hidden; 
            transition: all 0.3s ease;
            margin-top: 40px;
        }
        
        .results-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-card);
        }
        
        .results-header {
            padding: 18px 24px; 
            background: var(--bg-body); 
            border-bottom: 1px solid var(--border); 
            font-weight: 600; 
            font-size: 0.85rem; 
            color: var(--text-muted); 
            letter-spacing: 0.5px; 
            display: flex; 
            align-items: center; 
            justify-content: space-between;
        }
        
        .result-count {
            font-size: 0.8rem; 
            color: var(--primary);
        }
        
        .result-item { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            padding: 18px 24px; 
            border-bottom: 1px solid var(--border); 
            transition: background 0.2s ease; 
        }
        
        .result-item:hover { 
            background: var(--bg-body); 
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .result-info {
            flex: 1;
        }
        
        .result-name {
            font-weight: 600; 
            font-size: 1.05rem; 
            color: var(--text-main); 
            display: flex; 
            align-items: center; 
            gap: 10px;
            margin-bottom: 4px;
        }
        
        .result-avatar {
            width: 36px; 
            height: 36px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid var(--border);
        }
        
        .avatar-placeholder {
            width: 36px; 
            height: 36px; 
            border-radius: 50%; 
            background: var(--primary); 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            font-size: 0.9rem;
        }
        
        .result-details {
            font-size: 0.85rem; 
            color: var(--text-muted); 
            margin-top: 4px; 
            margin-left: 46px;
        }
        
        .btn-action { 
            padding: 10px 18px; 
            border-radius: 8px; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            font-weight: 500; 
            font-size: 0.9rem; 
            border: 1px solid var(--border); 
            background: var(--bg-card); 
            color: var(--text-main); 
            transition: all 0.2s; 
            text-decoration: none;
            white-space: nowrap;
        }
        
        .btn-action:hover { 
            border-color: var(--primary); 
            color: var(--primary); 
            background: var(--bg-body);
            transform: translateY(-1px);
        }
        
        .btn-primary-action { 
            background: var(--primary); 
            color: white; 
            border: none; 
        }
        
        .btn-primary-action:hover { 
            background: var(--primary-hover); 
            color: white; 
            transform: translateY(-1px);
        }

        /* A4 Form (Screen View) */
        .a4-wrapper { 
            background: white; 
            color: #1f2937; 
            width: 210mm; 
            min-height: 297mm; 
            padding: 40px 50px; 
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); 
            margin: 0 auto 50px; 
            text-align: left; 
            border-radius: 8px;
        }
        
        .form-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: flex-start;
            border-bottom: 2px solid #000; 
            padding-bottom: 20px; 
            margin-bottom: 30px; 
        }
        
        .brand-info {
            display: flex;
            flex-direction: column;
        }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 5px;
        }
        
        .brand-name {
            margin: 0; 
            font-size: 24px; 
            text-transform: uppercase; 
            letter-spacing: 1px; 
            color: #111;
        }
        
        .document-info {
            text-align: right;
        }
        
        .document-id {
            margin: 0; 
            color: #333;
            font-size: 20px;
        }
        
        .document-date {
            margin: 0; 
            font-size: 12px; 
            color: #666;
        }
        
        .section-title { 
            background: #f3f4f6; 
            padding: 10px 15px; 
            font-weight: 700; 
            text-transform: uppercase; 
            font-size: 14px; 
            margin-bottom: 20px; 
            border-left: 4px solid var(--primary); 
            color: #333; 
        }
        
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 25px; 
            margin-bottom: 30px; 
        }
        
        .form-group label { 
            display: block; 
            font-size: 12px; 
            color: #666; 
            text-transform: uppercase; 
            margin-bottom: 6px; 
            font-weight: 600; 
        }
        
        .form-group div { 
            font-size: 16px; 
            font-weight: 500; 
            padding: 6px 0; 
            border-bottom: 1px solid #e5e7eb; 
            color: #111; 
        }

        /* Error Message */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
            padding: 14px 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 1px solid rgba(239, 68, 68, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 0.95rem;
            font-weight: 500;
            width: 100%;
            max-width: 600px;
            text-align: center;
        }

        /* No Results */
        .no-results {
            padding: 60px 20px;
            text-align: center;
            color: var(--text-muted);
        }

        .no-results i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 20px;
        }

        .no-results p {
            margin: 0;
        }

        /* Action Buttons Container */
        .action-buttons {
            display: flex;
            justify-content: space-between;
            width: 100%;
            max-width: 210mm;
            margin-bottom: 30px;
            gap: 15px;
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
            z-index: 99; 
            display: none; 
            animation: fadeIn 0.3s ease;
        }

        /* --- Responsive Design --- */
        @media (max-width: 1024px) {
            .a4-wrapper {
                padding: 30px 40px;
            }
        }

        @media (max-width: 768px) {
            .sidebar { 
                position: fixed; 
                left: -280px; 
                height: 100%; 
                top: 0;
                z-index: 100;
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
            
            .header-left {
                gap: 12px;
            }
            
            .content-scroll { 
                padding: 20px; 
            }
            
            .profile-info { 
                display: none; 
            }
            
            .search-container {
                padding: 30px 20px;
                margin: 0;
            }
            
            .search-title {
                font-size: 1.5rem;
            }
            
            .search-icon {
                font-size: 2.5rem;
            }
            
            .search-box {
                flex-direction: column;
                border-radius: 12px;
                padding: 0;
                background: transparent;
                border: none;
                box-shadow: none;
                gap: 12px;
            }
            
            .search-input {
                padding: 16px;
                border-radius: 10px;
                border: 1px solid var(--border);
                background: var(--bg-body);
                width: 100%;
                font-size: 1rem;
            }
            
            .search-btn {
                padding: 16px;
                border-radius: 10px;
                width: 100%;
                font-size: 1rem;
            }
            
            .search-tips {
                flex-direction: column;
                align-items: center;
            }
            
            .a4-wrapper {
                padding: 20px;
                box-shadow: none;
                margin-bottom: 20px;
                width: 100%;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .form-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                align-items: center;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: stretch;
                gap: 10px;
            }
            
            .brand-logo {
                justify-content: center;
            }
            
            .document-info {
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .top-header {
                padding: 0 15px;
            }
            
            .content-scroll {
                padding: 15px;
            }
            
            .search-container {
                padding: 20px 15px;
            }
            
            .search-title {
                font-size: 1.3rem;
            }
            
            .results-header {
                padding: 15px 20px;
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
            
            .result-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
                padding: 15px 20px;
            }
            
            .btn-action {
                width: 100%;
                justify-content: center;
            }
            
            .a4-wrapper {
                padding: 15px;
            }
        }

        /* --- PRINT STYLES --- */
        @media print {
            @page { 
                size: A4; 
                margin: 0; 
            }
            
            body { 
                background: white !important; 
                color: #111 !important; 
                display: block; 
                height: auto; 
                overflow: visible; 
                margin: 0;
                padding: 0;
            }
            
            .sidebar, 
            .top-header, 
            .search-container, 
            .overlay, 
            .btn-action, 
            .centered-search-area, 
            .action-buttons,
            .toggle-btn,
            #themeToggle,
            .profile-container {
                display: none !important; 
            }
            
            .main-content, 
            .content-scroll { 
                display: block; 
                height: auto; 
                overflow: visible; 
                padding: 0; 
                margin: 0;
                width: 100%;
            }
            
            .a4-wrapper { 
                width: 100%; 
                max-width: 100%;
                box-shadow: none; 
                margin: 0; 
                padding: 40px; 
                border: none; 
                background: white !important; 
                color: black !important; 
                min-height: auto;
                page-break-inside: avoid;
            }
            
            .section-title { 
                background: #eee !important; 
                -webkit-print-color-adjust: exact; 
                border-left: 4px solid #000 !important; 
                color: #000 !important; 
            }
            
            .form-group div { 
                border-bottom: 1px solid #ccc !important; 
                color: #000 !important; 
            }
            
            .form-header { 
                border-bottom: 2px solid #000 !important; 
            }
            
            .photo-placeholder {
                background: #eee !important;
                -webkit-print-color-adjust: exact;
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
            <span style="font-size: 0.9rem; color: var(--text-muted);">Customer Form</span>
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
            <?php if ($view_mode === 'search' || $view_mode === 'list'): ?>
                <!-- Centered Search Area -->
                <div class="centered-search-area">
                    <div class="search-container">
                        <i class="ph ph-magnifying-glass search-icon"></i>
                        <h1 class="search-title">Customer Search</h1>
                        <p class="search-subtitle">
                            Search for customers by name, phone number, or customer ID to view and print detailed profiles.
                        </p>
                        
                        <form method="GET" action="" style="width: 100%;">
                            <div class="search-box">
                                <input type="text" name="q" class="search-input" placeholder="Enter customer name, phone, or ID..." value="<?php echo e($search_query); ?>" required autofocus>
                                <button type="submit" class="search-btn">
                                    <i class="ph ph-magnifying-glass"></i>
                                    Search Customer
                                </button>
                            </div>
                        </form>

                        <div class="search-tips">
                            <span class="search-tip">
                                <i class="ph ph-user" style="font-size: 0.8rem;"></i>
                                Search by name
                            </span>
                            <span class="search-tip">
                                <i class="ph ph-phone" style="font-size: 0.8rem;"></i>
                                Search by phone
                            </span>
                            <span class="search-tip">
                                <i class="ph ph-hash" style="font-size: 0.8rem;"></i>
                                Search by ID
                            </span>
                        </div>

                        <?php if($error): ?>
                            <div class="error-message">
                                <i class="ph ph-warning-circle"></i>
                                <span><?php echo e($error); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($view_mode === 'list'): ?>
                        <div class="results-card">
                            <div class="results-header">
                                <span>SEARCH RESULTS</span>
                                <span class="result-count"><?php echo count($results); ?> found</span>
                            </div>
                            <?php if (count($results) > 0): ?>
                                <?php foreach($results as $row): ?>
                                    <div class="result-item">
                                        <div class="result-info">
                                            <div class="result-name">
                                                <?php if(!empty($row['photo_url'])): ?>
                                                    <img src="<?php echo e($row['photo_url']); ?>" alt="<?php echo e($row['full_name']); ?>" class="result-avatar">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder">
                                                        <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span><?php echo e($row['full_name']); ?></span>
                                            </div>
                                            <div class="result-details">
                                                ID: #<?php echo $row['id']; ?> • <?php echo e($row['phone']); ?> • <?php echo e($row['email']); ?>
                                            </div>
                                        </div>
                                        <a href="?id=<?php echo $row['id']; ?>" class="btn-action">
                                            <i class="ph ph-eye"></i>
                                            View & Print
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-results">
                                    <i class="ph ph-users"></i>
                                    <p>No customers found matching "<?php echo e($search_query); ?>"</p>
                                    <p style="font-size: 0.9rem; margin-top: 10px; color: var(--text-muted); opacity: 0.8;">Try a different search term</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($view_mode === 'detail' && $customer): ?>
                <!-- Detail View with Print Options -->
                <div style="width: 100%; display: flex; justify-content: center;">
                    <div style="width: 100%; max-width: 210mm;">
                        <div class="action-buttons">
                            <a href="customer_view.php" class="btn-action">
                                <i class="ph ph-arrow-left"></i> Back to Search
                            </a>
                            <div style="display: flex; gap: 10px;">
                                <button onclick="window.print()" class="btn-action btn-primary-action">
                                    <i class="ph ph-printer"></i> Print PDF
                                </button>
                            </div>
                        </div>

                        <div class="a4-wrapper">
                            <div class="form-header">
                                <div class="brand-info">
                                    <div class="brand-logo">
                                        <i class="ph ph-cube" style="color: #4f46e5; font-size: 24px;"></i>
                                        <h1 class="brand-name">ALI HAIR WIGS</h1>
                                    </div>
                                    <p style="margin:5px 0 0; color:#555; font-size:13px; font-weight: 500;">Customer Information Record</p>
                                </div>
                                <div class="document-info">
                                    <h2 class="document-id">#<?php echo str_pad($customer['id'], 6, '0', STR_PAD_LEFT); ?></h2>
                                    <p class="document-date">Generated: <?php echo date('Y-m-d'); ?></p>
                                </div>
                            </div>

                            <div style="display: flex; gap: 40px; margin-bottom: 30px; align-items: flex-start;">
                                <div style="flex-shrink: 0;">
                                    <?php if(!empty($customer['photo_url'])): ?>
                                        <img src="<?php echo e($customer['photo_url']); ?>" style="width:120px; height:120px; object-fit:cover; border-radius: 6px; border:1px solid #ddd; padding:3px;">
                                    <?php else: ?>
                                        <div style="width:120px; height:120px; background:#f3f4f6; border-radius: 6px; border:1px solid #ddd; display:flex; align-items:center; justify-content:center; flex-direction: column; color:#9ca3af; font-size:0.8rem;">
                                            <i class="ph ph-user" style="font-size: 24px; margin-bottom: 5px;"></i>
                                            <span>NO PHOTO</span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div style="flex: 1;">
                                    <div class="section-title" style="margin-top:0;">01. Personal Identity</div>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label>Full Name</label>
                                            <div><?php echo e($customer['full_name']); ?></div>
                                        </div>
                                        <div class="form-group">
                                            <label>Company</label>
                                            <div><?php echo e($customer['company_name'] ?: 'N/A'); ?></div>
                                        </div>
                                        <div class="form-group">
                                            <label>Status</label>
                                            <div><?php echo e($customer['status']); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="section-title">02. Contact & Location</div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Email</label>
                                    <div><?php echo e($customer['email']); ?></div>
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <div><?php echo e($customer['phone'] ?: 'N/A'); ?></div>
                                </div>
                                <div class="form-group" style="grid-column: span 2;">
                                    <label>Address</label>
                                    <div><?php echo e($customer['address']) . ', ' . e($customer['city']) . ' ' . e($customer['zip_code']); ?></div>
                                </div>
                                <div class="form-group">
                                    <label>Country</label>
                                    <div><?php echo e($customer['country']); ?></div>
                                </div>
                            </div>

                            <div class="section-title">03. Administrative Data</div>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Registered</label>
                                    <div><?php echo date('F j, Y', strtotime($customer['created_at'])); ?></div>
                                </div>
                                <div class="form-group">
                                    <label>Last Updated</label>
                                    <div><?php echo date('F j, Y', strtotime($customer['updated_at'])); ?></div>
                                </div>
                            </div>

                            <div style="margin-top: 80px; display: flex; justify-content: space-between;">
                                <div style="width: 40%;">
                                    <div style="border-bottom: 1px solid #000; height: 40px;"></div>
                                    <div style="padding-top: 8px; font-size: 11px; text-transform: uppercase; color: #555;">Customer Signature</div>
                                </div>
                                <div style="width: 40%;">
                                    <div style="border-bottom: 1px solid #000; height: 40px;"></div>
                                    <div style="padding-top: 8px; font-size: 11px; text-transform: uppercase; color: #555;">Authorized Officer</div>
                                </div>
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

        // --- 5. Auto focus search input on page load ---
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            if (searchInput && searchInput.value === '' && window.location.search.indexOf('?id=') === -1) {
                searchInput.focus();
            }
            
            // Set active submenu item
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.submenu-link').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });

        // --- 6. Prevent form submission on empty search ---
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const searchInput = this.querySelector('input[name="q"]');
            if (!searchInput.value.trim()) {
                e.preventDefault();
                searchInput.focus();
                searchInput.style.borderColor = '#ef4444';
                setTimeout(() => {
                    searchInput.style.borderColor = '';
                }, 2000);
            }
        });

        // --- 7. Responsive sidebar on window resize ---
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && body.classList.contains('mobile-open')) {
                body.classList.remove('mobile-open');
            }
            
            if (window.innerWidth <= 768) {
                body.classList.remove('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>