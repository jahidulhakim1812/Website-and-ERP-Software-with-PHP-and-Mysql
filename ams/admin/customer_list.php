<?php
/**
 * customer_list.php
 * Features: Full DB View, Exact Profile Dropdown Match, Dark Mode, Search, Pagination.
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

// Helper to format full address from DB columns
function formatAddress($row) {
    $parts = [];
    if (!empty($row['address'])) $parts[] = $row['address'];
    if (!empty($row['city'])) $parts[] = $row['city'];
    if (!empty($row['state'])) $parts[] = $row['state'];
    if (!empty($row['zip_code'])) $parts[] = $row['zip_code'];
    if (!empty($row['country'])) $parts[] = $row['country'];
    return implode(', ', $parts);
}

// --- 4. DELETE LOGIC ---
if (isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: customer_list.php?msg=deleted");
    exit;
}

// --- 5. SEARCH & PAGINATION ---
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

$where = "";
$params = [];

if ($search) {
    // Extended search to cover phone and city
    $where = "WHERE full_name LIKE ? OR email LIKE ? OR company_name LIKE ? OR phone LIKE ? OR city LIKE ?";
    $term = "%$search%";
    $params = [$term, $term, $term, $term, $term];
}

// Total Count
$countSql = "SELECT COUNT(*) FROM customers $where";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$totalRows = $stmt->fetchColumn();
$totalPages = ceil($totalRows / $perPage);

// Fetch Data (Select * to get all columns as requested)
$sql = "SELECT * FROM customers $where ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$i = 1;
foreach($params as $val) $stmt->bindValue($i++, $val);
$stmt->bindValue($i++, $perPage, PDO::PARAM_INT);
$stmt->bindValue($i++, $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer List | NexusAdmin</title>
    
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

        /* --- TABLE SECTION --- */
        .content-scroll { flex: 1; overflow-y: auto; padding: 24px; }
        .card { background: var(--bg-card); border-radius: 0.75rem; border: 1px solid var(--border); box-shadow: var(--shadow); }

        .toolbar { padding: 16px 20px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
        
        .search-wrap { position: relative; width: 300px; }
        .search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-input { width: 100%; padding: 10px 12px 10px 36px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text-main); outline: none; }
        .search-input:focus { border-color: var(--primary); }
        
        .btn-primary { background: var(--primary); color: white; padding: 10px 20px; border-radius: 6px; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; }

        /* Table Responsive */
        .table-responsive { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1200px; /* Wide min-width for full data */ }
        th, td { padding: 14px 20px; text-align: left; border-bottom: 1px solid var(--border); font-size: 0.85rem; vertical-align: middle; white-space: nowrap; }
        th { background: var(--bg-body); color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.75rem; }
        
        .customer-identity { display: flex; align-items: center; gap: 12px; }
        .customer-img { width: 36px; height: 36px; border-radius: 50%; object-fit: cover; background: #eee; }
        .customer-initials { width: 36px; height: 36px; border-radius: 50%; background: #e0e7ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 0.8rem; }

        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge.active { background: #d1fae5; color: #065f46; }
        .badge.inactive { background: #fee2e2; color: #991b1b; }

        /* Actions */
        .actions { display: flex; gap: 8px; justify-content: flex-end; }
        .btn-icon { width: 32px; height: 32px; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: var(--text-muted); transition: 0.2s; }
        .btn-icon:hover { background: var(--bg-body); color: var(--primary); }
        .btn-icon.delete:hover { color: #ef4444; background: #fef2f2; }

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
        }
        
        /* Overlay for mobile */
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
            <span style="font-size: 0.9rem; color: var(--text-muted);">Customer List</span>
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
            
            <?php if (isset($_GET['msg'])): ?>
            <div style="background:var(--bg-card); color:#065f46; padding:12px; border-radius:8px; border:1px solid #10b981; margin-bottom:20px; display:flex; align-items:center; gap:8px;">
                <i class="ph ph-check-circle" style="color:#10b981;"></i> Operation successful.
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="toolbar">
                    <form method="GET" style="display:flex; width:100%; gap:16px; justify-content:space-between; flex-wrap:wrap;">
                        <div class="search-wrap">
                            <i class="ph ph-magnifying-glass"></i>
                            <input type="text" name="search" class="search-input" placeholder="Search..." value="<?php echo e($search); ?>">
                        </div>
                        <a href="add_customers.php" class="btn-primary">
                            <i class="ph ph-plus"></i> Add Customer
                        </a>
                    </form>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 50px;">ID</th>
                                <th>Customer Profile</th>
                                <th>Contact Info</th>
                                <th>Company</th>
                                <th>Full Address</th>
                                <th>Status</th>
                                <th>Dates (Joined / Updated)</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($customers) > 0): ?>
                                <?php foreach ($customers as $row): ?>
                                <tr>
                                    <td>#<?php echo $row['id']; ?></td>
                                    
                                    <td>
                                        <div class="customer-identity">
                                            <?php if(!empty($row['photo_url'])): ?>
                                                <img src="<?php echo e($row['photo_url']); ?>" alt="img" class="customer-img">
                                            <?php else: ?>
                                                <div class="customer-initials">
                                                    <?php echo strtoupper(substr($row['full_name'], 0, 2)); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight:600; color:var(--text-main);"><?php echo e($row['full_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <div style="display:flex; flex-direction:column;">
                                            <span style="font-size:0.85rem; color:var(--primary);"><?php echo e($row['email']); ?></span>
                                            <span style="font-size:0.8rem; color:var(--text-muted);"><?php echo e($row['phone'] ?? '-'); ?></span>
                                        </div>
                                    </td>

                                    <td>
                                        <div style="font-weight:500;"><?php echo e($row['company_name'] ?: '-'); ?></div>
                                    </td>

                                    <td style="max-width: 250px; white-space: normal;">
                                        <span style="font-size:0.85rem; color:var(--text-muted);">
                                            <?php echo e(formatAddress($row)); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge <?php echo strtolower($row['status']); ?>">
                                            <?php echo e($row['status']); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div style="font-size:0.8rem;">
                                            <div><span style="color:var(--text-muted);">In:</span> <?php echo date('Y-m-d', strtotime($row['created_at'])); ?></div>
                                            <?php if($row['updated_at']): ?>
                                                <div><span style="color:var(--text-muted);">Up:</span> <?php echo date('Y-m-d', strtotime($row['updated_at'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <td>
                                        <div class="actions">
                                            <a href="edit_customer.php?id=<?php echo $row['id']; ?>" class="btn-icon" title="Edit">
                                                <i class="ph ph-pencil-simple"></i>
                                            </a>
                                            <a href="?delete_id=<?php echo $row['id']; ?>" class="btn-icon delete" title="Delete" onclick="return confirm('Delete this customer?');">
                                                <i class="ph ph-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align:center; padding: 40px; color: var(--text-muted);">
                                        No customers found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div style="padding:14px 20px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border);">
                    <span style="font-size:0.85rem; color:var(--text-muted);">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <div style="display:flex; gap:4px;">
                        <a href="?page=<?php echo $page - 1; ?>&search=<?php echo e($search); ?>" style="padding:6px 12px; border:1px solid var(--border); border-radius:6px; font-size:0.85rem;" class="<?php echo ($page <= 1) ? 'disabled' : ''; ?>">Prev</a>
                        <a href="?page=<?php echo $page + 1; ?>&search=<?php echo e($search); ?>" style="padding:6px 12px; border:1px solid var(--border); border-radius:6px; font-size:0.85rem;" class="<?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">Next</a>
                    </div>
                </div>
                <?php endif; ?>
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
    </script>
</body>
</html> 