<?php
/**
 * accessory_purchase_view.php
 * View details of a single accessory purchase with items.
 */

session_start();

// --- 1. AUTH & SETTINGS ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexander Pierce';
    $_SESSION['role'] = 'admin';
    $_SESSION['email'] = 'alex@example.com';
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

// --- 3. GET PURCHASE ID ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: accessory_purchase_list.php');
    exit;
}

// --- 4. FETCH PURCHASE HEADER WITH VENDOR NAME ---
$purchase = null;
try {
    $stmt = $pdo->prepare("SELECT ap.*, v.company_name AS vendor_name 
                           FROM accessory_purchases ap 
                           LEFT JOIN vendors v ON ap.vendor_id = v.id 
                           WHERE ap.id = ?");
    $stmt->execute([$id]);
    $purchase = $stmt->fetch();
    if (!$purchase) {
        header('Location: accessory_purchase_list.php');
        exit;
    }
} catch (Exception $e) {
    die("Error fetching purchase: " . $e->getMessage());
}

// --- 5. FETCH ITEMS ---
$items = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM accessory_items WHERE purchase_id = ? ORDER BY id");
    $stmt->execute([$id]);
    $items = $stmt->fetchAll();
} catch (Exception $e) {
    // Ignore, no items
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 6. FETCH USER PHOTO FOR PROFILE ---
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
    <title>Purchase #<?php echo $purchase['id']; ?> | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- CSS Variables & Reset (same as previous pages) --- */
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

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button { font-family: inherit; cursor: pointer; }

        /* --- Sidebar & Layout Logic (identical to previous) --- */
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

        /* --- Content Scroll Area --- */
        .content-scroll { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }

        /* --- Page Specific Styles (View) --- */
        .page-container { max-width: 1000px; margin: 0 auto; }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
        }

        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--bg-card);
        }
        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        /* Detail Card */
        .detail-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 30px;
            box-shadow: var(--shadow-card);
            margin-bottom: 30px;
        }

        .detail-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }

        .purchase-id {
            font-size: 1.2rem;
            color: var(--text-muted);
        }
        .purchase-id strong {
            font-size: 1.8rem;
            color: var(--primary);
            margin-left: 8px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .detail-item .label {
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .detail-item .value {
            font-size: 1.1rem;
            font-weight: 500;
            word-break: break-word;
        }
        .value.total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        /* Items Table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .items-table th {
            text-align: left;
            padding: 12px;
            background: var(--bg-body);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
        }
        .items-table tbody tr:hover td {
            background: var(--bg-body);
        }
        .items-table .subtotal {
            font-weight: 600;
        }

        /* Print styles */
        @media print {
            .sidebar, .top-header, .page-header .btn-secondary, .btn-primary, #themeToggle, .profile-container {
                display: none !important;
            }
            body, .main-content, .content-scroll {
                padding: 0;
                background: white;
            }
            .detail-card {
                box-shadow: none;
                border: 1px solid #ddd;
                page-break-inside: avoid;
            }
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

        /* --- Responsive --- */
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
            }
            body.mobile-open .overlay { 
                display: block; 
            }
            .profile-info { 
                display: none; 
            }
            .content-scroll { 
                padding: 20px; 
            }
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .detail-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- SIDEBAR (same as before) -->
    <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div class="page-title">
                    <div class="page-dot"></div>
                    <span class="page-text">Purchase Details</span>
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
            <div class="page-container">
                
                <div class="page-header">
                    <h1>Accessory Purchase #<?php echo $purchase['id']; ?></h1>
                    <div style="display: flex; gap: 10px;">
                        <a href="accessory_purchase_list.php" class="btn-secondary">
                            <i class="ph ph-arrow-left"></i> Back to List
                        </a>
                        <a href="accessory_purchase_edit.php?id=<?php echo $purchase['id']; ?>" class="btn-primary">
                            <i class="ph ph-pencil-simple"></i> Edit
                        </a>
                        <button onclick="window.print()" class="btn-secondary">
                            <i class="ph ph-printer"></i> Print
                        </button>
                    </div>
                </div>

                <div class="detail-card">
                    <div class="detail-header">
                        <div>
                            <span class="purchase-id">Purchase ID <strong>#<?php echo str_pad($purchase['id'], 6, '0', STR_PAD_LEFT); ?></strong></span>
                        </div>
                        <div class="value total">$<?php echo number_format($purchase['total_cost'], 2); ?></div>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="label">Vendor</span>
                            <span class="value"><?php echo e($purchase['vendor_name'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Purchase Date</span>
                            <span class="value"><?php echo date('F j, Y', strtotime($purchase['purchase_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="label">Created At</span>
                            <span class="value"><?php echo date('Y-m-d H:i', strtotime($purchase['created_at'])); ?></span>
                        </div>
                        <?php if (!empty($purchase['notes'])): ?>
                        <div class="detail-item" style="grid-column: span 3;">
                            <span class="label">Notes</span>
                            <span class="value"><?php echo nl2br(e($purchase['notes'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <h3 style="margin: 30px 0 15px; font-size: 1.3rem;">Items</h3>
                    
                    <?php if (count($items) > 0): ?>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Accessory Name</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Unit Price ($)</th>
                                <th>Subtotal ($)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo e($item['accessory_name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo e($item['unit']); ?></td>
                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="subtotal">$<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p style="text-align: center; padding: 40px; color: var(--text-muted);">No items recorded for this purchase.</p>
                    <?php endif; ?>
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

        // --- 3. Profile Dropdown ---
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

        // --- 5. Set active menu ---
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.submenu-link').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                    link.closest('.has-submenu')?.classList.add('open');
                }
            });
        });
    </script>
</body>
</html>