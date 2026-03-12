<?php
/**
 * check_salary.php
 * Search Employee Salary History (Admin Interface)
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

// --- 3. SEARCH LOGIC ---
$searchQuery = $_GET['q'] ?? '';
$employee = null;
$salaryHistory = [];
$error = '';

if ($searchQuery) {
    try {
        // Find Employee
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE (full_name LIKE :q OR phone LIKE :q) LIMIT 1");
        $stmt->execute([':q' => "%$searchQuery%"]);
        $employee = $stmt->fetch();

        if ($employee) {
            // Get Salary History
            $stmtHist = $pdo->prepare("SELECT * FROM salary_payments WHERE employee_id = ? ORDER BY payment_date DESC");
            $stmtHist->execute([$employee['id']]);
            $salaryHistory = $stmtHist->fetchAll();
        } else {
            $error = "No employee found matching '$searchQuery'";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 4. GET USER PHOTO FROM DATABASE ---
$userPhoto = '';
$userInitial = strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1));
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
        if ($userData && !empty($userData['avatar'])) {
            $userPhoto = $userData['avatar'];
        }
    } catch (Exception $e) {
        // Silent fail - use placeholder
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Salary | NexusAdmin</title>
    
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

        /* --- Check Salary Page Specific Styles --- */
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
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            width: 100%;
            max-width: 800px;
        }
        
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
        }
        
        .page-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .search-card { 
            background: var(--bg-card); 
            width: 100%; 
            max-width: 800px; 
            border-radius: var(--radius); 
            border: 1px solid var(--border); 
            padding: 40px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); 
            text-align: center;
            margin-bottom: 30px;
        }

        .search-form { 
            display: flex; 
            gap: 12px; 
            margin-top: 20px;
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
            font-size: 1rem; 
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-search:hover { 
            background: var(--primary-hover); 
        }

        /* Error Message */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            color: #B91C1C;
            border: 1px solid #EF4444;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        /* Results Card */
        .result-card { 
            background: var(--bg-card); 
            width: 100%; 
            max-width: 800px; 
            border-radius: var(--radius); 
            border: 1px solid var(--border); 
            padding: 40px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            animation: fadeIn 0.3s ease-in-out;
        }

        /* Employee Profile Section */
        .employee-profile {
            display: flex;
            align-items: center;
            gap: 24px;
            padding-bottom: 24px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border);
        }
        
        .employee-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border);
        }
        
        .employee-initials {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            font-weight: 700;
            border: 3px solid var(--border);
        }
        
        .employee-info h2 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
        }
        
        .employee-details {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .detail-item {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        /* Salary Summary */
        .salary-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: var(--bg-body);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
            text-align: center;
        }
        
        .summary-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        
        .summary-value {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .summary-card.salary .summary-value {
            color: var(--primary);
        }
        
        .summary-card.payments .summary-value {
            color: #10B981;
        }
        
        .summary-card.last .summary-value {
            color: #F59E0B;
        }

        /* Table Styles */
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
        
        .amount-cell {
            font-weight: 700;
            color: #10B981;
        }
        
        .month-badge {
            background: var(--bg-body);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--text-muted);
            opacity: 0.5;
            margin-bottom: 16px;
        }
        
        .empty-state-text {
            color: var(--text-muted);
            font-size: 1rem;
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

        /* Smooth Transitions */
        * {
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .salary-summary {
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
            
            .search-card, .result-card {
                padding: 24px;
            }
            
            .search-form { 
                flex-direction: column; 
            }
            
            .employee-profile {
                flex-direction: column;
                text-align: center;
                gap: 16px;
            }
            
            .employee-details {
                justify-content: center;
            }
            
            .salary-summary { 
                grid-template-columns: 1fr; 
            }
        }
        
        @media (max-width: 480px) {
            .search-card, .result-card {
                padding: 20px;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
            
            .data-table th, .data-table td {
                padding: 12px;
            }
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
            <span style="font-size: 0.9rem; color: var(--text-muted);">Salary Management</span>
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
                        <?php echo $userInitial; ?>
                    </div>
                <?php else: ?>
                    <div class="profile-placeholder">
                        <?php echo $userInitial; ?>
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
                <h1>Check Employee Salary</h1>
                <p>Search by name or phone number to view salary history</p>
            </div>

            <div class="search-card">
                <div style="margin-bottom: 20px;">
                    <i class="ph ph-money-wavy" style="font-size: 3rem; color: var(--primary);"></i>
                </div>
                
                <form method="GET" class="search-form">
                    <input type="text" name="q" class="search-input" placeholder="Enter employee name or phone number..." value="<?php echo e($searchQuery); ?>" required autocomplete="off">
                    <button type="submit" class="btn-search">
                        <i class="ph ph-magnifying-glass"></i>
                        Search
                    </button>
                </form>

                <?php if($error): ?>
                    <div class="error-message">
                        <i class="ph ph-warning-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if($employee): ?>
                <div class="result-card">
                    <!-- Employee Profile -->
                    <div class="employee-profile">
                        <?php 
                        // Get employee photo from database
                        $employeePhoto = $employee['photo'] ?? '';
                        $employeeInitial = strtoupper(substr($employee['full_name'], 0, 1));
                        
                        if (!empty($employeePhoto)): 
                            // Check if it's a URL or relative path
                            if (filter_var($employeePhoto, FILTER_VALIDATE_URL)) {
                                // It's a full URL
                                $photoSrc = $employeePhoto;
                            } elseif (file_exists('uploads/' . $employeePhoto)) {
                                // It's in uploads directory
                                $photoSrc = 'uploads/' . $employeePhoto;
                            } elseif (file_exists($employeePhoto)) {
                                // It's a relative path
                                $photoSrc = $employeePhoto;
                            } else {
                                // Default to placeholder
                                $photoSrc = '';
                            }
                        else:
                            $photoSrc = '';
                        endif;
                        ?>
                        
                        <?php if (!empty($photoSrc)): ?>
                            <img src="<?php echo e($photoSrc); ?>" class="employee-avatar" alt="Employee Avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="employee-initials" style="display: none;">
                                <?php echo $employeeInitial; ?>
                            </div>
                        <?php else: ?>
                            <div class="employee-initials">
                                <?php echo $employeeInitial; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="employee-info">
                            <h2><?php echo e($employee['full_name']); ?></h2>
                            <div class="employee-details">
                                <div class="detail-item">
                                    <i class="ph ph-briefcase"></i>
                                    <span><?php echo e($employee['designation'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="ph ph-phone"></i>
                                    <span><?php echo e($employee['phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="ph ph-envelope"></i>
                                    <span><?php echo e($employee['email'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="ph ph-buildings"></i>
                                    <span><?php echo e($employee['department'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="detail-item">
                                    <i class="ph ph-calendar"></i>
                                    <span>Joined: <?php echo date('M Y', strtotime($employee['joining_date'] ?? date('Y-m-d'))); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Salary Summary -->
                    <div class="salary-summary">
                        <div class="summary-card salary">
                            <div class="summary-label">Monthly Salary</div>
                            <div class="summary-value">$<?php echo number_format($employee['salary'] ?? 0, 2); ?></div>
                        </div>
                        <div class="summary-card payments">
                            <div class="summary-label">Total Payments</div>
                            <div class="summary-value"><?php echo count($salaryHistory); ?></div>
                        </div>
                        <div class="summary-card last">
                            <div class="summary-label">Last Payment</div>
                            <div class="summary-value">
                                <?php if (count($salaryHistory) > 0): ?>
                                    <?php echo date('M Y', strtotime($salaryHistory[0]['payment_month'])); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Salary History Table -->
                    <div class="table-container">
                        <div class="table-header">
                            <div style="font-weight: 600; color: var(--text-main);">
                                <i class="ph ph-clock-counter-clockwise"></i>
                                Salary Payment History
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-muted);">
                                Showing <?php echo count($salaryHistory); ?> records
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Payment Date</th>
                                        <th>Payment Method</th>
                                        <th>Note</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(count($salaryHistory) > 0): ?>
                                        <?php 
                                        $totalPaid = 0;
                                        foreach($salaryHistory as $row): 
                                            $totalPaid += $row['amount'];
                                        ?>
                                            <tr>
                                                <td>
                                                    <span class="month-badge">
                                                        <?php echo date('M Y', strtotime($row['payment_month'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></div>
                                                    <div style="font-size: 0.85rem; color: var(--text-muted);">
                                                        <?php echo date('h:i A', strtotime($row['payment_date'])); ?>
                                                    </div>
                                                </td>
                                                <td><?php echo e($row['payment_method']); ?></td>
                                                <td>
                                                    <?php if (!empty($row['note'])): ?>
                                                        <div style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo e($row['note']); ?>">
                                                            <?php echo e($row['note']); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span style="color: var(--text-muted); font-style: italic;">No note</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="amount-cell">$<?php echo number_format($row['amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5">
                                                <div class="empty-state">
                                                    <div class="empty-state-icon">
                                                        <i class="ph ph-wallet"></i>
                                                    </div>
                                                    <div class="empty-state-text">
                                                        No salary payment records found for this employee.
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if(count($salaryHistory) > 0): ?>
                        <div style="padding: 20px; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-size: 0.9rem; color: var(--text-muted);">
                                Total Paid: <span style="font-weight: 700; color: #10B981;">$<?php echo number_format($totalPaid, 2); ?></span>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-muted);">
                                Average per payment: <span style="font-weight: 700;">$<?php echo number_format(count($salaryHistory) > 0 ? $totalPaid / count($salaryHistory) : 0, 2); ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
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

        // --- Auto-focus on search input ---
        document.querySelector('input[name="q"]')?.focus();

        // --- Image error handling ---
        document.addEventListener('DOMContentLoaded', function() {
            // Handle employee image errors
            const employeeAvatar = document.querySelector('.employee-avatar');
            if (employeeAvatar) {
                employeeAvatar.addEventListener('error', function() {
                    this.style.display = 'none';
                    const initials = this.nextElementSibling;
                    if (initials && initials.classList.contains('employee-initials')) {
                        initials.style.display = 'flex';
                    }
                });
            }
        });
    </script>
</body>
</html>