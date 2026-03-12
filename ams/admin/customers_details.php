<?php
/**
 * customer_details.php
 * Integrated with NexusAdmin V2 Design
 * Features: Detailed Purchase History with Total, Paid, and Due amounts.
 * Update: Dynamic Customer Photo from 'photo_url'
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
    $pdo = null;
    $dbError = $ex->getMessage();
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 4. SEARCH & FETCH LOGIC ---
$customer = null;
$orders = [];
$stats = ['total_spent' => 0, 'order_count' => 0, 'total_due' => 0];
$search = trim($_GET['search'] ?? '');
$error = '';

if ($pdo && $search) {
    // A. FIND CUSTOMER
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? OR full_name LIKE ? OR phone LIKE ? OR email LIKE ? LIMIT 1");
    $term = "%$search%";
    $stmt->execute([$search, $term, $term, $term]);
    $customer = $stmt->fetch();

    if ($customer) {
        // B. FETCH PURCHASE HISTORY
        try {
            $stmtOrders = $pdo->prepare("SELECT id, reference_no, order_date, total_amount, paid_amount, payment_status FROM purchase_orders WHERE customer_id = ? ORDER BY order_date DESC");
            $stmtOrders->execute([$customer['id']]);
            $orders = $stmtOrders->fetchAll();

            // C. CALCULATE STATS
            foreach ($orders as $order) {
                $total = (float)$order['total_amount'];
                $paid = (float)$order['paid_amount'];
                $stats['total_spent'] += $total;
                $stats['total_due'] += ($total - $paid);
                $stats['order_count']++;
            }
        } catch (Exception $e) {
            $orders = [];
        }
    } else {
        $error = "Customer not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Details | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- CORE NEXUSADMIN CSS --- */
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338ca;
            --bg-body: #F3F4F6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --error: #EF4444;
            --success: #059669;
            --sidebar-width: 280px;
            --sidebar-bg: #111827;
            --sidebar-text: #E5E7EB;
            --header-height: 64px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 12px;
            --shadow: 0 2px 8px rgba(0,0,0,0.04);
            --shadow-dropdown: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
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

        /* --- PAGE SPECIFIC STYLES --- */
        .scrollable { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }
        
        .page-header-title { margin-bottom: 24px; }
        .page-header-title h1 { font-size: 1.5rem; font-weight: 700; }
        .page-header-title p { color: var(--text-muted); }
        
        .search-box { 
            background: var(--bg-card); 
            padding: 20px; 
            border-radius: var(--radius); 
            display: flex; 
            gap: 10px; 
            margin-bottom: 30px; 
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .search-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        
        .search-input { 
            flex: 1; 
            padding: 12px 16px; 
            border: 1px solid var(--border); 
            background: var(--bg-body); 
            color: var(--text-main); 
            border-radius: 8px; 
            font-size: 1rem; 
            outline: none; 
            transition: border 0.2s; 
        }
        
        .search-input:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .btn { 
            background: var(--primary); 
            color: white; 
            border: none; 
            padding: 0 24px; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: 600; 
            transition: background 0.2s; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn:hover { 
            background: var(--primary-hover); 
        }
        
        .profile-grid { 
            display: grid; 
            grid-template-columns: 350px 1fr; 
            gap: 24px; 
        }
        
        .card { 
            background: var(--bg-card); 
            border-radius: var(--radius); 
            border: 1px solid var(--border); 
            overflow: hidden; 
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        
        .card-header { 
            padding: 20px; 
            border-bottom: 1px solid var(--border); 
            font-weight: 600; 
            font-size: 1.1rem; 
            background: var(--bg-body); 
            color: var(--text-main); 
        }
        
        .card-body { 
            padding: 24px; 
        }
        
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 15px; 
            border-bottom: 1px dashed var(--border); 
            padding-bottom: 8px; 
        }
        
        .info-label { 
            color: var(--text-muted); 
            font-size: 0.9rem; 
        }
        
        .info-val { 
            font-weight: 500; 
            color: var(--text-main); 
        }
        
        .stats-row { 
            display: grid; 
            grid-template-columns: repeat(3, 1fr); 
            gap: 15px; 
            margin-bottom: 20px; 
        }
        
        .stat-box { 
            background: var(--bg-body); 
            padding: 15px; 
            border-radius: 8px; 
            border: 1px solid var(--border); 
            text-align: center; 
            transition: all 0.2s ease;
        }
        
        .stat-box:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .stat-num { 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: var(--text-main); 
        }

        /* Table Styles */
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-top: 10px; 
        }
        
        th { 
            text-align: left; 
            padding: 12px; 
            background: var(--bg-body); 
            font-size: 0.8rem; 
            font-weight: 600; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            border-bottom: 1px solid var(--border); 
            white-space: nowrap; 
        }
        
        td { 
            padding: 14px 12px; 
            border-bottom: 1px solid var(--border); 
            font-size: 0.95rem; 
            color: var(--text-main); 
            vertical-align: middle; 
        }
        
        .badge { 
            padding: 4px 10px; 
            border-radius: 20px; 
            font-size: 0.75rem; 
            font-weight: 600; 
            white-space: nowrap; 
            display: inline-block;
        }
        
        .paid { 
            background: #d1fae5; 
            color: #065f46; 
        }
        
        .partial { 
            background: #fef3c7; 
            color: #92400e; 
        }
        
        .unpaid { 
            background: #fee2e2; 
            color: #991b1b; 
        }
        
        [data-theme="dark"] .paid { 
            background: #064e3b; 
            color: #a7f3d0; 
        }
        
        [data-theme="dark"] .partial { 
            background: #78350f; 
            color: #fde68a; 
        }
        
        [data-theme="dark"] .unpaid { 
            background: #7f1d1d; 
            color: #fecaca; 
        }

        .text-right { 
            text-align: right; 
        }
        
        .text-muted { 
            color: var(--text-muted); 
        }
        
        .text-danger { 
            color: var(--error); 
        }
        
        .font-bold { 
            font-weight: 600; 
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
        @media (max-width: 1200px) { 
            .profile-grid { 
                grid-template-columns: 1fr; 
            } 
            .stats-row { 
                grid-template-columns: repeat(3, 1fr); 
            } 
        }
        
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
            
            .scrollable { 
                padding: 24px 20px; 
            }
            
            .profile-info { 
                display: none; 
            }
            
            .stats-row { 
                grid-template-columns: 1fr; 
            }
            
            .table-responsive { 
                overflow-x: auto; 
                -webkit-overflow-scrolling: touch; 
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .search-input, .btn {
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
            <span style="font-size: 0.9rem; color: var(--text-muted);">Purchase Details</span>
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
            <div class="page-header-title">
                <h1>Customer Profile</h1>
                <p>View individual customer details and financial history.</p>
            </div>

            <form method="GET" class="search-box">
                <input type="text" name="search" class="search-input" placeholder="Search by Name, Phone, Email or ID..." value="<?php echo e($search); ?>">
                <button type="submit" class="btn">
                    <i class="ph ph-magnifying-glass"></i>
                    Search
                </button>
            </form>

            <?php if ($error): ?>
                <div style="background: rgba(239, 68, 68, 0.1); color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-warning-circle" style="font-size: 1.2rem;"></i>
                    <span><?php echo e($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($customer): ?>
            <div class="profile-grid">
                <div class="card" style="height: fit-content;">
                    <div class="card-header">Customer Identity</div>
                    <div class="card-body">
                        <div style="text-align: center; margin-bottom: 20px;">
                            
                            <?php 
                                $photoSrc = !empty($customer['photo_url']) 
                                    ? htmlspecialchars($customer['photo_url']) 
                                    : "https://ui-avatars.com/api/?name=" . urlencode($customer['full_name']) . "&background=4F46E5&color=fff&size=80";
                            ?>
                            <img src="<?php echo $photoSrc; ?>" 
                                 style="width: 80px; height: 80px; border-radius:50%; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); object-fit: cover; border: 3px solid var(--bg-body);" 
                                 alt="<?php echo e($customer['full_name']); ?>">
                            
                            <h2 style="margin: 15px 0 5px 0; color: var(--text-main); font-size: 1.25rem;"><?php echo e($customer['full_name']); ?></h2>
                            <span style="background:var(--bg-body); color:var(--primary); padding:2px 8px; border-radius:4px; font-size:0.8rem; font-weight: 600; border: 1px solid var(--border);">ID: #<?php echo str_pad($customer['id'], 5, '0', STR_PAD_LEFT); ?></span>
                        </div>
                        <div class="info-row"><span class="info-label">Phone</span><span class="info-val"><?php echo e($customer['phone']); ?></span></div>
                        <div class="info-row"><span class="info-label">Email</span><span class="info-val"><?php echo e($customer['email'] ?? 'N/A'); ?></span></div>
                        <div class="info-row"><span class="info-label">Address</span><span class="info-val"><?php echo e($customer['address'] ?? 'N/A'); ?></span></div>
                        <div class="info-row" style="border:none;"><span class="info-label">Joined</span><span class="info-val"><?php echo date('M Y', strtotime($customer['created_at'])); ?></span></div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">Purchase History & Financials</div>
                    <div class="card-body">
                        <div class="stats-row">
                            <div class="stat-box">
                                <div class="stat-num" style="color: var(--primary);">$<?php echo number_format($stats['total_spent'], 2); ?></div>
                                <div style="font-size:0.85rem; color:var(--text-muted);">Total Spent</div>
                            </div>
                             <div class="stat-box">
                                <div class="stat-num" style="color: var(--success);">$<?php echo number_format($stats['total_spent'] - $stats['total_due'], 2); ?></div>
                                <div style="font-size:0.85rem; color:var(--text-muted);">Total Paid</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-num" style="color: var(--error);">$<?php echo number_format($stats['total_due'], 2); ?></div>
                                <div style="font-size:0.85rem; color:var(--text-muted);">Total Due</div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Order Date</th>
                                        <th>Ref No.</th>
                                        <th>Status</th>
                                        <th class="text-right">Total</th>
                                        <th class="text-right">Paid</th>
                                        <th class="text-right">Due</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($orders) > 0): ?>
                                        <?php foreach ($orders as $po): ?>
                                        <?php
                                            $total = (float)$po['total_amount'];
                                            $paid = (float)$po['paid_amount'];
                                            $due = $total - $paid;
                                            $status = $po['payment_status'] ?? 'Unpaid';

                                            $statusClass = 'unpaid';
                                            if ($status === 'Paid') $statusClass = 'paid';
                                            elseif ($status === 'Partial') $statusClass = 'partial';
                                        ?>
                                        <tr>
                                            <td style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($po['order_date'])); ?></td>
                                            <td style="font-family: monospace; font-weight: 500;"><?php echo e($po['reference_no']); ?></td>
                                            <td><span class="badge <?php echo $statusClass; ?>"><?php echo e(ucfirst($status)); ?></span></td>
                                            <td class="text-right font-bold">$<?php echo number_format($total, 2); ?></td>
                                            <td class="text-right text-muted">$<?php echo number_format($paid, 2); ?></td>
                                            <td class="text-right font-bold <?php echo ($due > 0.01) ? 'text-danger' : 'text-muted'; ?>">
                                                $<?php echo number_format($due, 2); ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="6" style="text-align:center; padding: 30px; color: var(--text-muted);">No purchase history found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php elseif (!$error && !$search): ?>
                <div style="text-align: center; margin-top: 80px; color: var(--text-muted);">
                    <i class="ph ph-magnifying-glass" style="font-size: 4rem; opacity: 0.3; margin-bottom: 15px;"></i>
                    <h3 style="color: var(--text-main); margin-bottom: 8px;">Find a Customer</h3>
                    <p>Enter a name, phone number, email, or ID above to view account details.</p>
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
    </script>
</body>
</html>