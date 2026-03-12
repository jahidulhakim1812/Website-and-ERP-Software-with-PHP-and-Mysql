<?php
/**
 * add_production_cost.php
 * Record accessory purchases under a vendor (items optional, manual total possible).
 * Unified sidebar and header from customer_view.php.
 */

session_start();

// --- 1. AUTH & SETTINGS (mimic the customer_view.php approach) ---
if (!isset($_SESSION['user_id'])) {
    // For demonstration, set a default user. Replace with your actual auth logic.
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

// --- 3. FETCH VENDORS (Using company_name) ---
$vendors = [];
try {
    $stmt_v = $pdo->query("SELECT id, company_name FROM vendors ORDER BY company_name ASC");
    $vendors = $stmt_v->fetchAll();
} catch (Exception $e) {
    $vendors = []; 
}

// --- 4. HANDLE FORM SUBMISSION ---
$message = "";
$msg_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_accessories'])) {
    
    // Inputs
    $vendor_id  = !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null;
    $date       = $_POST['purchase_date'];
    $notes      = trim($_POST['notes']);
    $manual_total = floatval($_POST['manual_total'] ?? 0);

    // Item Arrays
    $names   = $_POST['accessory_name'] ?? [];
    $qtys    = $_POST['qty'] ?? [];
    $units   = $_POST['unit'] ?? [];
    $prices  = $_POST['unit_price'] ?? [];

    if (empty($vendor_id) || empty($date)) {
        $message = "Please select a Vendor and enter a Purchase Date.";
        $msg_type = "error";
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Insert main purchase record
            $stmt = $pdo->prepare("INSERT INTO accessory_purchases (vendor_id, purchase_date, total_cost, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$vendor_id, $date, 0, $notes]); 
            $purchase_id = $pdo->lastInsertId();

            $calculated_total = 0;
            $has_valid_items = false;

            // 2. Insert items if any valid ones exist
            if (!empty($names)) {
                $sql_item = "INSERT INTO accessory_items (purchase_id, accessory_name, quantity, unit, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_item = $pdo->prepare($sql_item);

                for ($i = 0; $i < count($names); $i++) {
                    $name = trim($names[$i]);
                    $qty = floatval($qtys[$i]);
                    $unit = trim($units[$i]);
                    $price = floatval($prices[$i]);
                    
                    // Only process if name is not empty and quantity > 0
                    if (!empty($name) && $qty > 0 && in_array($unit, ['kg','g','L','fit','meter','pic'])) {
                        $subtotal = $qty * $price;
                        $calculated_total += $subtotal;
                        $has_valid_items = true;
                        $stmt_item->execute([$purchase_id, $name, $qty, $unit, $price, $subtotal]);
                    }
                }
            }

            // 3. Determine final total: if items exist, use their sum; otherwise use manual total
            $final_total = $has_valid_items ? $calculated_total : $manual_total;

            // 4. Update grand total in header
            $stmt_update = $pdo->prepare("UPDATE accessory_purchases SET total_cost = ? WHERE id = ?");
            $stmt_update->execute([$final_total, $purchase_id]);

            $pdo->commit();
            $message = "Accessory purchase saved successfully! Total: $" . number_format($final_total, 2);
            $msg_type = "success";
            
            $_POST = []; // Clear form

        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error: " . $e->getMessage();
            $msg_type = "error";
        }
    }
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 5. Fetch user photo for profile dropdown (if needed) ---
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
    <title>Accessory Purchase | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- CSS Variables & Reset (copied from customer_view.php) --- */
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

        /* --- Content Scroll Area --- */
        .content-scroll { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }

        /* --- Page Specific Styles (adapted from accessory form) --- */
        .page-container { max-width: 1000px; margin: 0 auto; }
        .card { background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border); padding: 32px; box-shadow: var(--shadow-card); }

        /* HEADER GRID */
        .header-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        label { font-size: 0.9rem; font-weight: 500; color: var(--text-muted); }
        input[type="text"], input[type="date"], input[type="number"], textarea, select {
            padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; 
            background: var(--bg-body); color: var(--text-main); font-size: 0.95rem; outline: none; transition: 0.2s;
            width: 100%;
        }
        input:focus, textarea:focus, select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

        /* Dynamic Table */
        .cost-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .cost-table th { text-align: left; padding: 12px; font-size: 0.85rem; color: var(--text-muted); border-bottom: 1px solid var(--border); text-transform: uppercase; letter-spacing: 0.5px; }
        .cost-table td { padding: 10px; border-bottom: 1px solid var(--border); }
        .cost-table input, .cost-table select { width: 100%; }
        
        .btn-remove { color: var(--error); background: rgba(239, 68, 68, 0.1); padding: 8px; border-radius: 6px; border: none; cursor: pointer; transition: 0.2s; }
        .btn-remove:hover { background: var(--error); color: white; }

        .btn-add { background: var(--bg-body); color: var(--text-main); border: 1px solid var(--border); padding: 10px 16px; border-radius: 8px; font-weight: 500; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-add:hover { border-color: var(--primary); color: var(--primary); }

        .footer-summary { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border); }
        .total-box { text-align: right; min-width: 250px; }
        .total-label { font-size: 0.9rem; color: var(--text-muted); }
        .total-amount { font-size: 2rem; font-weight: 700; color: var(--primary); line-height: 1.1; }

        .manual-total-input { margin-top: 15px; }
        .manual-total-input label { font-size: 0.85rem; }

        .btn-save { background: var(--primary); color: white; border: none; padding: 14px 32px; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.2s; margin-top: 16px; width: 100%; }
        .btn-save:hover { background: var(--primary-hover); transform: translateY(-1px); }

        /* Alerts */
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #065F46; border: 1px solid #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #991B1B; border: 1px solid #EF4444; }

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
            .page-container { padding: 0 20px; }
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
            
            .header-grid { grid-template-columns: 1fr; }
            .cost-table th, .cost-table td { display: block; width: 100%; }
            .cost-table tr { margin-bottom: 16px; display: block; border: 1px solid var(--border); border-radius: 8px; padding: 12px; }
            .cost-table thead { display: none; }
            .cost-table td { border: none; padding: 4px 0; }
            .footer-summary { flex-direction: column; gap: 20px; }
            .form-group { width: 100%; max-width: 100%; }
            .total-box { text-align: left; width: 100%; border-top: 1px solid var(--border); padding-top: 20px; }
        }

        @media (max-width: 480px) {
            .top-header {
                padding: 0 15px;
            }
            
            .content-scroll {
                padding: 15px;
            }
            
            .card {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- SIDEBAR (copied from customer_view.php) -->
    <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        <!-- HEADER (copied from customer_view.php) -->
        <header class="top-header">
            <div class="header-left">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div class="page-title">
                    <div class="page-dot"></div>
                    <span class="page-text">Accessory Purchase</span>
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
                
                <div style="margin-bottom: 24px;">
                    <h1 style="font-size: 1.75rem; font-weight: 700;">Add Accessory Purchase</h1>
                    <p style="color: var(--text-muted);">Record accessories bought from a vendor. Only Vendor and Date are required. You can add items now or later, and you may enter a manual total if no items are listed.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert <?php echo ($msg_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
                        <i class="ph <?php echo ($msg_type === 'success') ? 'ph-check-circle' : 'ph-warning-circle'; ?>" style="font-size: 1.25rem;"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" class="card">
                    
                    <div class="header-grid">
                        <div class="form-group">
                            <label>Vendor <span style="color:red">*</span></label>
                            <select name="vendor_id" required>
                                <option value="">Select Vendor</option>
                                <?php foreach($vendors as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo (isset($_POST['vendor_id']) && $_POST['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                        <?php echo e($v['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Purchase Date <span style="color:red">*</span></label>
                            <input type="date" name="purchase_date" required value="<?php echo e($_POST['purchase_date'] ?? date('Y-m-d')); ?>">
                        </div>
                    </div>

                    <table class="cost-table" id="calcTable">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Accessory Name</th>
                                <th style="width: 10%;">Quantity</th>
                                <th style="width: 10%;">Unit</th>
                                <th style="width: 15%;">Unit Price ($)</th>
                                <th style="width: 15%;">Total ($)</th>
                                <th style="width: 5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="tableBody">
                        </tbody>
                    </table>

                    <button type="button" class="btn-add" onclick="addRow()">
                        <i class="ph ph-plus-circle" style="font-size: 1.1rem;"></i> Add Accessory (Optional)
                    </button>

                    <div class="footer-summary">
                        <div class="form-group" style="flex: 1; max-width: 400px; margin-right: 40px;">
                            <label>Notes</label>
                            <textarea name="notes" rows="2" placeholder="Any remarks about this purchase..."><?php echo e($_POST['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="total-box">
                            <div class="total-label">Calculated Total (from items)</div>
                            <div class="total-amount" id="grandTotalDisplay">$0.00</div>
                            
                            <div class="manual-total-input">
                                <label for="manualTotal">Manual Total (optional)</label>
                                <input type="number" name="manual_total" id="manualTotal" step="0.01" min="0" value="0.00" placeholder="Enter amount if no items">
                            </div>
                            
                            <button type="submit" name="save_accessories" class="btn-save">
                                <i class="ph ph-floppy-disk"></i> Save Purchase
                            </button>
                        </div>
                    </div>

                </form>

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

        // --- 5. Calculation Logic (Table with unit dropdown) ---
        function calculateTotal() {
            let grandTotal = 0;
            const rows = document.querySelectorAll('.item-row');

            rows.forEach(row => {
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const total = qty * price;

                row.querySelector('.row-total').value = total.toFixed(2);
                grandTotal += total;
            });

            document.getElementById('grandTotalDisplay').innerText = '$' + grandTotal.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            // Note: manual total field is left unchanged; the user can override it.
        }

        function addRow() {
            const tbody = document.getElementById('tableBody');
            const tr = document.createElement('tr');
            tr.className = 'item-row';
            
            tr.innerHTML = `
                <td>
                    <input type="text" name="accessory_name[]" placeholder="e.g. Zipper, Button">
                </td>
                <td>
                    <input type="number" name="qty[]" class="qty-input" placeholder="0" step="0.01" min="0" oninput="calculateTotal()">
                </td>
                <td>
                    <select name="unit[]" class="unit-select">
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="L">L</option>
                        <option value="fit">fit</option>
                        <option value="meter">meter</option>
                        <option value="pic">pic</option>
                    </select>
                </td>
                <td>
                    <input type="number" name="unit_price[]" class="price-input" placeholder="0.00" step="0.01" min="0" oninput="calculateTotal()">
                </td>
                <td>
                    <input type="text" class="row-total" value="0.00" readonly style="background: var(--bg-body); border:none; font-weight: 600;">
                </td>
                <td style="text-align: center;">
                    <button type="button" class="btn-remove" onclick="removeRow(this)">
                        <i class="ph ph-trash"></i>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
        }

        function removeRow(btn) {
            const row = btn.closest('tr');
            row.remove();
            calculateTotal();
        }

        // Initialize with one empty row
        window.addEventListener('DOMContentLoaded', () => {
            if(document.querySelectorAll('.item-row').length === 0) {
                addRow();
            }

            // Set active submenu item
            const currentPage = window.location.pathname.split('/').pop();
            document.querySelectorAll('.submenu-link').forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>