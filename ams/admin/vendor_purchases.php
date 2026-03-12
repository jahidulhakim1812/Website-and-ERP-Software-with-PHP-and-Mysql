<?php
/**
 * vendor_purchases.php
 * Search and list accessory purchases by vendor name and date filters, with total sum and Excel export.
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

// --- 3. HANDLE SEARCH AND FILTERS ---
$vendor = $_GET['vendor'] ?? '';
$month = isset($_GET['month']) && $_GET['month'] !== '' ? (int)$_GET['month'] : null;
$year = isset($_GET['year']) && $_GET['year'] !== '' ? (int)$_GET['year'] : null;
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$results = [];
$totalSum = 0;

// Build query conditions
$conditions = [];
$params = [];

if (!empty($vendor)) {
    $conditions[] = "v.company_name LIKE :vendor";
    $params['vendor'] = '%' . $vendor . '%';
}

// Month/Year filter (only if both are provided)
if ($month && $year) {
    $conditions[] = "MONTH(ap.purchase_date) = :month AND YEAR(ap.purchase_date) = :year";
    $params['month'] = $month;
    $params['year'] = $year;
}

// Custom date range
if (!empty($date_from) && !empty($date_to)) {
    $conditions[] = "ap.purchase_date BETWEEN :date_from AND :date_to";
    $params['date_from'] = $date_from;
    $params['date_to'] = $date_to;
} elseif (!empty($date_from)) {
    $conditions[] = "ap.purchase_date >= :date_from";
    $params['date_from'] = $date_from;
} elseif (!empty($date_to)) {
    $conditions[] = "ap.purchase_date <= :date_to";
    $params['date_to'] = $date_to;
}

// Only execute query if at least one filter is applied
if (!empty($conditions)) {
    try {
        $sql = "SELECT ap.*, v.company_name AS vendor_name 
                FROM accessory_purchases ap 
                LEFT JOIN vendors v ON ap.vendor_id = v.id ";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        
        $sql .= " ORDER BY ap.purchase_date DESC, ap.id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();
        
        // Calculate total sum
        $totalSum = array_sum(array_column($results, 'total_cost'));
    } catch (Exception $e) {
        // Table might not exist – ignore
        $results = [];
    }
}

// --- 4. HANDLE CSV EXPORT ---
if (isset($_GET['export']) && $_GET['export'] === 'csv' && !empty($results)) {
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="vendor_purchases_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // CSV headers
    fputcsv($output, ['Purchase ID', 'Vendor', 'Date', 'Total Cost', 'Notes']);
    
    // Add data rows
    foreach ($results as $row) {
        fputcsv($output, [
            $row['id'],
            $row['vendor_name'] ?? 'N/A',
            $row['purchase_date'],
            number_format($row['total_cost'], 2),
            $row['notes'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 5. FETCH USER PHOTO FOR PROFILE ---
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

// Generate month names for dropdown
$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$currentYear = date('Y');
$years = range($currentYear - 5, $currentYear + 1); // last 5 years to next year
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendor Purchases | NexusAdmin</title>
    
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

        /* --- Page Specific Styles (Search & Filters) --- */
        .page-container { max-width: 1200px; margin: 0 auto; }

        .filter-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 30px;
            box-shadow: var(--shadow-card);
            margin-bottom: 30px;
        }

        .filter-section h2 {
            margin-bottom: 20px;
            font-size: 1.5rem;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1 1 200px;
            min-width: 150px;
        }

        .filter-group label {
            display: block;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 5px;
            font-weight: 500;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.95rem;
            outline: none;
            transition: 0.2s;
        }
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            text-decoration: none;
        }
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
            text-decoration: none;
        }
        .btn-secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--bg-card);
        }

        .summary-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 15px 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .summary-info {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .summary-item {
            display: flex;
            flex-direction: column;
        }

        .summary-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }

        .export-btn {
            background: var(--success);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
        }
        .export-btn:hover {
            background: #0f9d6e;
            transform: translateY(-1px);
        }

        .results-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: var(--shadow-card);
        }

        .results-header {
            padding: 18px 24px;
            background: var(--bg-body);
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        th {
            text-align: left;
            padding: 16px 20px;
            background: var(--bg-body);
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--border);
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            color: var(--text-main);
        }

        tr:hover td {
            background: var(--bg-body);
        }

        .badge-total {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
        }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-icon {
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-muted);
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            cursor: pointer;
            font-size: 1.1rem;
            text-decoration: none;
        }
        .btn-icon:hover {
            border-color: var(--primary);
            color: var(--primary);
            background: var(--bg-card);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        .empty-state i {
            font-size: 4rem;
            opacity: 0.3;
            margin-bottom: 20px;
        }

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
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-actions {
                flex-direction: column;
            }
            .btn-primary, .btn-secondary {
                width: 100%;
                justify-content: center;
            }
            .summary-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            .summary-info {
                gap: 15px;
            }
            .export-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- SIDEBAR (same as other pages) -->
    <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <div class="header-left">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div class="page-title">
                    <div class="page-dot"></div>
                    <span class="page-text">Vendor Purchases</span>
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
                
                <div class="filter-section">
                    <h2>Search Purchases</h2>
                    
                    <form method="GET" action="">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label>Vendor Name</label>
                                <input type="text" name="vendor" placeholder="Any vendor" value="<?php echo e($vendor); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Month</label>
                                <select name="month">
                                    <option value="">Any month</option>
                                    <?php foreach ($months as $num => $name): ?>
                                        <option value="<?php echo $num; ?>" <?php echo ($month == $num) ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Year</label>
                                <select name="year">
                                    <option value="">Any year</option>
                                    <?php foreach ($years as $y): ?>
                                        <option value="<?php echo $y; ?>" <?php echo ($year == $y) ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="filter-row" style="margin-top: 15px;">
                            <div class="filter-group">
                                <label>Date From</label>
                                <input type="date" name="date_from" value="<?php echo e($date_from); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Date To</label>
                                <input type="date" name="date_to" value="<?php echo e($date_to); ?>">
                            </div>
                            <div class="filter-actions">
                                <button type="submit" class="btn-primary">
                                    <i class="ph ph-magnifying-glass"></i> Apply Filters
                                </button>
                                <a href="vendor_purchases.php" class="btn-secondary">
                                    <i class="ph ph-x-circle"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <?php if (!empty($vendor) || $month || $year || !empty($date_from) || !empty($date_to)): ?>
                    <?php if (count($results) > 0): ?>
                        <div class="summary-bar">
                            <div class="summary-info">
                                <div class="summary-item">
                                    <span class="summary-label">Total Purchases</span>
                                    <span class="summary-value"><?php echo count($results); ?></span>
                                </div>
                                <div class="summary-item">
                                    <span class="summary-label">Total Amount</span>
                                    <span class="summary-value">$<?php echo number_format($totalSum, 2); ?></span>
                                </div>
                            </div>
                            <?php
                            // Build query string for export (same filters)
                            $exportParams = $_GET;
                            $exportParams['export'] = 'csv';
                            $exportQuery = http_build_query($exportParams);
                            ?>
                            <a href="?<?php echo $exportQuery; ?>" class="export-btn">
                                <i class="ph ph-file-csv"></i> Export to Excel
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="results-card">
                        <div class="results-header">
                            <span>
                                Results 
                                <?php if (!empty($vendor)): ?> for vendor "<?php echo e($vendor); ?>"<?php endif; ?>
                                <?php if ($month && $year): ?> in <?php echo $months[$month]; ?> <?php echo $year; ?><?php endif; ?>
                                <?php if (!empty($date_from) || !empty($date_to)): ?> 
                                    from <?php echo e($date_from ?: 'start'); ?> to <?php echo e($date_to ?: 'end'); ?>
                                <?php endif; ?>
                            </span>
                            <span><?php echo count($results); ?> found</span>
                        </div>

                        <?php if (count($results) > 0): ?>
                            <div class="table-responsive">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Vendor</th>
                                            <th>Date</th>
                                            <th>Total Cost</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                        <tr>
                                            <td>#<?php echo $row['id']; ?></td>
                                            <td><?php echo e($row['vendor_name'] ?: 'N/A'); ?></td>
                                            <td><?php echo date('Y-m-d', strtotime($row['purchase_date'])); ?></td>
                                            <td><span class="badge-total">$<?php echo number_format($row['total_cost'], 2); ?></span></td>
                                            <td>
                                                <div class="action-btns">
                                                    <a href="accessory_purchase_view.php?id=<?php echo $row['id']; ?>" class="btn-icon" title="View">
                                                        <i class="ph ph-eye"></i>
                                                    </a>
                                                    <a href="accessory_purchase_edit.php?id=<?php echo $row['id']; ?>" class="btn-icon" title="Edit">
                                                        <i class="ph ph-pencil-simple"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="ph ph-package"></i>
                                <p>No purchases match your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <script>
        // --- Sidebar Toggle Logic ---
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

        // --- Accordion Logic ---
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

        // --- Profile Dropdown Logic ---
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

        // --- Dark Mode ---
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

        // --- Set active menu item ---
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