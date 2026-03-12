<?php
/**
 * low_stock_list.php
 * Displays ONLY products with stock <= 10.
 * Restock button links to edit_product.php.
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
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) { 
    $pdo = null;
    die("Database Connection Failed: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 4. FETCH DATA ---
$products = [];
$total_catalog_count = 0;
$low_stock_count = 0;
$stock_threshold = 10; // "Low Stock" definition

if ($pdo) {
    try {
        // Get user photo from database
        $userPhoto = '';
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
        $userPhoto = $userData['avatar'] ?? '';

        // 1. Get Total Count (for stats)
        $countStmt = $pdo->query("SELECT COUNT(*) FROM products");
        $total_catalog_count = $countStmt->fetchColumn();

        // 2. Get Low Stock Products
        // Fetches products where quantity <= 10, ordered by lowest stock first
        $sql = "SELECT * FROM products WHERE quantity <= $stock_threshold ORDER BY quantity ASC";
        $stmt = $pdo->query($sql);
        $products = $stmt->fetchAll();
        
        $low_stock_count = count($products);

    } catch (Exception $e) {
        // Handle error silently
        $userPhoto = '';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Low Stock Alerts | NexusAdmin</title>
    
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
            --danger: #EF4444; 
            --success: #10B981; 
            --warning: #F59E0B;
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

        /* --- PAGE CONTENT --- */
        .content-scroll { flex: 1; overflow-y: auto; padding: 24px; }
        .card { background: var(--bg-card); border-radius: 0.75rem; border: 1px solid var(--border); box-shadow: var(--shadow); }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 32px; }
        .stat-card { background: var(--bg-card); padding: 24px; border-radius: var(--radius); border: 1px solid var(--border); display: flex; align-items: center; gap: 20px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .stat-icon { width: 56px; height: 56px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; flex-shrink: 0; }
        .stat-info h3 { font-size: 1.75rem; font-weight: 700; color: var(--text-main); line-height: 1.2; }
        .stat-info p { color: var(--text-muted); font-size: 0.9rem; }

        /* Alert Bar */
        .alert-bar { 
            background: #FEF2F2; 
            color: #B91C1C; 
            padding: 16px 20px; 
            margin-bottom: 24px; 
            border-radius: 8px; 
            border: 1px solid #FECACA; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            border-left: 4px solid var(--danger);
        }
        [data-theme='dark'] .alert-bar {
            background: #7F1D1D;
            color: #FEE2E2;
            border: 1px solid #991B1B;
        }

        /* Table */
        .table-responsive { overflow-x: auto; }
        .table { width: 100%; border-collapse: collapse; }
        .table th { text-align: left; padding: 16px 20px; border-bottom: 1px solid var(--border); background-color: var(--bg-body); color: var(--text-muted); font-size: 0.8rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.03em; }
        .table td { padding: 16px 20px; border-bottom: 1px solid var(--border); vertical-align: middle; font-size: 0.95rem; }
        .table tr:last-child td { border-bottom: none; }
        .table tr:hover { background-color: var(--bg-body); }

        /* Product Image & Info */
        .product-cell { display: flex; align-items: center; gap: 16px; }
        .product-img { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border); background-color: #f9f9f9; }
        .product-info div:first-child { font-weight: 600; color: var(--text-main); }
        .product-info div:last-child { font-size: 0.85rem; color: var(--text-muted); }

        /* Buttons */
        .btn-restock { 
            background-color: #EEF2FF; 
            color: var(--primary); 
            font-weight: 600; 
            font-size: 0.9rem; 
            border: 1px solid #C7D2FE; 
            padding: 8px 16px; 
            border-radius: 6px; 
            display: inline-flex; 
            align-items: center; 
            gap: 6px;
            transition: all 0.2s ease;
        }
        .btn-restock:hover { 
            background-color: var(--primary); 
            color: white;
            border-color: var(--primary);
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
            .stats-grid { grid-template-columns: 1fr; }
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
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Low Stocks</span>
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


        <div class="content-scroll">
            
            <div class="alert-bar">
                <i class="ph ph-warning-circle" style="font-size: 1.5rem;"></i>
                <div>
                    <strong>Low Stock Alert:</strong> Showing products with stock level <strong><?php echo $stock_threshold; ?> or less</strong>.
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#FEF2F2; color:var(--danger);">
                        <i class="ph ph-siren"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $low_stock_count; ?></h3>
                        <p>Items Needing Reorder</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#EEF2FF; color:var(--primary);">
                        <i class="ph ph-stack"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_catalog_count; ?></h3>
                        <p>Total Products in Catalog</p>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header" style="padding: 20px; border-bottom: 1px solid var(--border);">
                    <span style="font-size:1.1rem; font-weight:600; color:var(--danger);">
                        <i class="ph ph-warning-circle"></i> Critical Stock List
                    </span>
                </div>
                
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th width="40%">Product Details</th>
                                <th width="20%">Remaining Stock</th>
                                <th width="20%">Selling Price</th>
                                <th width="20%" style="text-align:right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($products) > 0): ?>
                                <?php foreach($products as $row): ?>
                                    <?php 
                                        $imagePath = $row['image_url'] ?? '';
                                        if (!empty($imagePath) && file_exists($imagePath)) {
                                            $imgSrc = $imagePath;
                                        } else {
                                            $imgSrc = 'https://placehold.co/100x100/e0e7ff/4338ca?text=' . substr($row['product_name'] ?? 'N/A', 0, 2);
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="product-cell">
                                                <img src="<?php echo $imgSrc; ?>" alt="Product" class="product-img" onerror="this.src='https://placehold.co/100x100/e0e7ff/4338ca?text=N/A'">
                                                
                                                <div class="product-info">
                                                    <div><?php echo e($row['product_name']); ?></div>
                                                    <div>SKU: <?php echo e($row['sku'] ?? 'PROD-'.$row['id']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="color: var(--danger); font-weight: 700; font-size: 1.1rem;">
                                                <?php echo $row['quantity']; ?>
                                            </span>
                                            <span style="font-size:0.85rem; color:var(--text-muted);">Units</span>
                                        </td>
                                        <td>
                                            $<?php echo number_format($row['selling_price'] ?? $row['price'] ?? 0, 2); ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="edit_product.php?id=<?php echo $row['id']; ?>" title="Edit Stock" class="btn-restock">
                                                <i class="ph ph-pencil-simple"></i> Restock
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" style="text-align:center; padding: 40px; color: var(--success);">
                                        <i class="ph ph-check-circle" style="font-size:2rem; margin-bottom:10px; display:block;"></i>
                                        <strong>Good News!</strong><br>
                                        No low stock items found. All products are above the threshold.
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
    </script>
</body>
</html>