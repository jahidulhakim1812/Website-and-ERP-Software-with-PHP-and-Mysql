<?php
/**
 * inactive_customers.php
 * Displays a list of customers with 'Inactive' status.
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
    die("DB Connection failed: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}

// --- 4. DATA FETCHING ---
// Fetch only INACTIVE customers
$stmt = $pdo->query("SELECT * FROM customers WHERE status = 'Inactive' ORDER BY created_at DESC");
$inactive_customers = $stmt->fetchAll();
$total_count = count($inactive_customers);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inactive Customers | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- CSS Variables & Reset (From Admin Dashboard V2) --- */
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
            
            /* Status Colors */
            --status-inactive-bg: #fee2e2;
            --status-inactive-text: #991b1b;
        }

        [data-theme='dark'] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --sidebar-bg: #020617;
            --primary: #6366f1;
            --status-inactive-bg: #450a0a;
            --status-inactive-text: #fecaca;
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

        /* --- CONTENT AREA --- */
        .content-scroll { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }
        
        .card { 
            background: var(--bg-card); 
            border-radius: var(--radius); 
            border: 1px solid var(--border); 
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        
        .card-header { 
            padding: 20px 24px; 
            border-bottom: 1px solid var(--border); 
            display: flex; 
            flex-wrap: wrap; 
            gap: 16px; 
            justify-content: space-between; 
            align-items: center; 
            background: var(--bg-card); 
        }
        
        .header-title h3 { 
            font-size: 1.1rem; 
            font-weight: 600; 
            margin-bottom: 4px; 
        }
        
        .header-title span { 
            font-size: 0.85rem; 
            color: var(--text-muted); 
        }
        
        .toolbar { 
            display: flex; 
            gap: 12px; 
            align-items: center; 
        }
        
        .search-box { 
            position: relative; 
        }
        
        .search-box i { 
            position: absolute; 
            left: 12px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: var(--text-muted); 
            font-size: 1.1rem; 
        }
        
        .search-input { 
            padding: 9px 12px 9px 38px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            font-size: 0.9rem; 
            background: var(--bg-body); 
            color: var(--text-main); 
            outline: none; 
            transition: 0.2s; 
            width: 220px; 
        }
        
        .search-input:focus { 
            border-color: var(--primary); 
            width: 260px; 
        }

        .btn-export { 
            padding: 9px 16px; 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            color: var(--text-main); 
            font-size: 0.9rem; 
            font-weight: 500; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            transition: 0.2s; 
        }
        
        .btn-export:hover { 
            background: var(--border); 
            color: var(--primary); 
        }
        
        .table-responsive { 
            overflow-x: auto; 
            width: 100%; 
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left; 
        }
        
        th { 
            padding: 14px 24px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 0.05em; 
            color: var(--text-muted); 
            background: var(--bg-body); 
            border-bottom: 1px solid var(--border); 
            white-space: nowrap; 
        }
        
        td { 
            padding: 16px 24px; 
            border-bottom: 1px solid var(--border); 
            font-size: 0.9rem; 
            color: var(--text-main); 
            vertical-align: middle; 
        }
        
        tr:last-child td { 
            border-bottom: none; 
        }
        
        tr:hover td { 
            background: rgba(0,0,0,0.01); 
        }
        
        [data-theme='dark'] tr:hover td { 
            background: rgba(255,255,255,0.02); 
        }

        .customer-info { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        
        .table-avatar { 
            width: 38px; 
            height: 38px; 
            border-radius: 50%; 
            object-fit: cover; 
            background: var(--primary); 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 0.9rem; 
            font-weight: 600; 
            border: 2px solid var(--bg-card); 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
        }
        
        .customer-text div:first-child { 
            font-weight: 600; 
            color: var(--text-main); 
        }
        
        .customer-text div:last-child { 
            font-size: 0.8rem; 
            color: var(--text-muted); 
            margin-top: 2px; 
        }
        
        /* Status Badge */
        .badge { 
            display: inline-flex; 
            align-items: center; 
            padding: 4px 10px; 
            border-radius: 9999px; 
            font-size: 0.75rem; 
            font-weight: 600; 
        }
        
        .badge-inactive { 
            background: var(--status-inactive-bg); 
            color: var(--status-inactive-text); 
        }
        
        .badge-dot { 
            width: 6px; 
            height: 6px; 
            border-radius: 50%; 
            background: currentColor; 
            margin-right: 6px; 
        }
        
        .action-btn { 
            width: 32px; 
            height: 32px; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            border-radius: 6px; 
            color: var(--text-muted); 
            transition: 0.2s; 
            font-size: 1.1rem; 
        }
        
        .action-btn:hover { 
            background: var(--bg-body); 
            color: var(--primary); 
        }

        .empty-state { 
            padding: 60px 20px; 
            text-align: center; 
            color: var(--text-muted); 
            display: none; 
        }
        
        .empty-state i { 
            font-size: 3.5rem; 
            margin-bottom: 16px; 
            opacity: 0.3; 
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

        /* --- Responsive Design --- */
        @media (max-width: 768px) {
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
            
            .content-scroll { 
                padding: 24px 20px; 
            }
            
            .profile-info { 
                display: none; 
            }
            
            .card-header { 
                flex-direction: column; 
                align-items: flex-start; 
            }
            
            .toolbar { 
                width: 100%; 
                flex-direction: column; 
            }
            
            .search-box, .search-input, .btn-export { 
                width: 100%; 
            }
            
            .search-input:focus { 
                width: 100%; 
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
            <span style="font-size: 0.9rem; color: var(--text-muted);">Inactive Customers</span>
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
            <div class="card">
                
                <div class="card-header">
                    <div class="header-title">
                        <h3>Inactive List</h3>
                        <span id="resultCount">Showing <?php echo $total_count; ?> inactive customer(s)</span>
                    </div>
                    <div class="toolbar">
                        <div class="search-box">
                            <i class="ph ph-magnifying-glass"></i>
                            <input type="text" id="searchInput" class="search-input" placeholder="Search inactive users...">
                        </div>
                        <button onclick="exportTableToCSV('inactive_customers.csv')" class="btn-export">
                            <i class="ph ph-download-simple"></i> Export CSV
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table id="customerTable">
                        <thead>
                            <tr>
                                <th>Customer Profile</th>
                                <th>Company</th>
                                <th>Phone</th>
                                <th>Location</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                            <?php if ($total_count > 0): ?>
                                <?php foreach ($inactive_customers as $cust): ?>
                                <tr>
                                    <td>
                                        <div class="customer-info">
                                            <?php if (!empty($cust['photo_url'])): ?>
                                                <img src="<?php echo e($cust['photo_url']); ?>" class="table-avatar" alt="Ava">
                                            <?php else: ?>
                                                <div class="table-avatar">
                                                    <?php echo strtoupper(substr($cust['full_name'], 0, 1)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="customer-text">
                                                <div class="searchable"><?php echo e($cust['full_name']); ?></div>
                                                <div class="searchable"><?php echo e($cust['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="searchable">
                                        <?php echo $cust['company_name'] ? e($cust['company_name']) : '<span style="color:var(--text-muted);">-</span>'; ?>
                                    </td>
                                    <td class="searchable"><?php echo e($cust['phone']); ?></td>
                                    <td class="searchable">
                                        <?php 
                                            $loc = array_filter([$cust['city'], $cust['country']]);
                                            echo !empty($loc) ? implode(', ', array_map('e', $loc)) : '-';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-inactive">
                                            <span class="badge-dot"></span> Inactive
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <a href="edit_customer.php?id=<?php echo $cust['id']; ?>" class="action-btn" title="Edit Customer">
                                            <i class="ph ph-pencil-simple"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <div class="empty-state" id="emptyState" style="display: <?php echo ($total_count === 0) ? 'block' : 'none'; ?>;">
                        <i class="ph ph-user-minus"></i>
                        <p>No inactive customers found.</p>
                    </div>
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

        // --- 5. SEARCH LOGIC ---
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('tableBody');
        const emptyState = document.getElementById('emptyState');
        const resultCountSpan = document.getElementById('resultCount');
        const rows = tableBody ? tableBody.getElementsByTagName('tr') : [];

        if(searchInput && tableBody) {
            searchInput.addEventListener('keyup', function() {
                const filter = searchInput.value.toLowerCase();
                let visibleCount = 0;

                for (let i = 0; i < rows.length; i++) {
                    const row = rows[i];
                    const searchCells = row.querySelectorAll('.searchable');
                    let textContent = "";
                    searchCells.forEach(cell => textContent += (cell.textContent || cell.innerText));

                    if (textContent.toLowerCase().indexOf(filter) > -1) {
                        row.style.display = "";
                        visibleCount++;
                    } else {
                        row.style.display = "none";
                    }
                }

                if (visibleCount === 0) {
                    emptyState.style.display = "block";
                    tableBody.parentElement.style.display = "none"; 
                } else {
                    emptyState.style.display = "none";
                    tableBody.parentElement.style.display = "table";
                }
                if(resultCountSpan) resultCountSpan.innerText = `Showing ${visibleCount} inactive customer(s)`;
            });
        }

        // --- 6. CSV EXPORT LOGIC ---
        function downloadCSV(csv, filename) {
            const csvFile = new Blob([csv], {type: "text/csv"});
            const downloadLink = document.createElement("a");
            downloadLink.download = filename;
            downloadLink.href = window.URL.createObjectURL(csvFile);
            downloadLink.style.display = "none";
            document.body.appendChild(downloadLink);
            downloadLink.click();
        }

        function exportTableToCSV(filename) {
            const csv = [];
            const rows = document.querySelectorAll("table tr");
            
            for (let i = 0; i < rows.length; i++) {
                if(rows[i].style.display === 'none') continue;
                const row = [], cols = rows[i].querySelectorAll("td, th");
                
                for (let j = 0; j < cols.length; j++) {
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/(\s\s)/gm, " ");
                    data = data.replace(/"/g, '""');
                    if (j < cols.length - 1) row.push('"' + data.trim() + '"');
                }
                csv.push(row.join(","));
            }
            downloadCSV(csv.join("\n"), filename);
        }
    </script>
</body>
</html>