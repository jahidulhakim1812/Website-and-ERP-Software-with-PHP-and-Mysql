<?php
/**
 * mirage_due_report.php
 * MIRAGE LOD‑3 – Due Payment Report.
 * Lists ONLY batches with due amount > 0, including items (hair quantity), additional costs, paid amount, and due amount.
 * Total cost = sum of additional costs only.
 * Filter by subcontractor. Includes Pay Now and Delete buttons for each batch, plus a button to view all due batches for the selected subcontractor.
 */

session_start();

// --- Authentication ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexander Pierce';
    $_SESSION['role'] = 'admin';
    $_SESSION['email'] = 'alex@example.com';
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: logout.php");
    exit;
}

// --- Database connection ---
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
    die("Database connection failed: " . $ex->getMessage());
}

// --- Get all subcontractors for dropdown ---
$subs = $pdo->query("SELECT id, company_name FROM subcontractors ORDER BY company_name")->fetchAll();

// --- Handle filter ---
$selected_sub_id = isset($_GET['subcontractor_id']) ? (int)$_GET['subcontractor_id'] : 0;
$selected_sub_name = '';
$batches = [];
$grand_total_due = 0;

if ($selected_sub_id > 0) {
    // Fetch subcontractor name
    $stmt = $pdo->prepare("SELECT company_name FROM subcontractors WHERE id = ?");
    $stmt->execute([$selected_sub_id]);
    $sub = $stmt->fetch();
    $selected_sub_name = $sub ? $sub['company_name'] : '';
}

// Base query for batches (optionally filtered)
$sql = "
    SELECT 
        b.id,
        b.lod_name,
        b.production_date,
        s.company_name AS subcontractor_name
    FROM wigs_batches b
    LEFT JOIN subcontractors s ON b.subcontractor_id = s.id
";
if ($selected_sub_id > 0) {
    $sql .= " WHERE b.subcontractor_id = :sub_id";
}
$sql .= " ORDER BY b.production_date DESC, b.id DESC";

$stmt = $pdo->prepare($sql);
if ($selected_sub_id > 0) {
    $stmt->execute(['sub_id' => $selected_sub_id]);
} else {
    $stmt->execute();
}
$all_batches = $stmt->fetchAll();

// For each batch, fetch items, costs, and payments
foreach ($all_batches as &$batch) {
    // Items (hair pieces with weight)
    $stmt = $pdo->prepare("
        SELECT 
            type,
            size,
            per_piece,
            unit,
            quantity,
            (per_piece * quantity) AS total_hair
        FROM wigs_batch_items
        WHERE batch_id = ?
        ORDER BY type, size
    ");
    $stmt->execute([$batch['id']]);
    $batch['items'] = $stmt->fetchAll();

    // Additional costs (monetary)
    $stmt = $pdo->prepare("
        SELECT 
            size,
            description,
            quantity,
            unit_price,
            (quantity * unit_price) AS line_total
        FROM wigs_batch_costs
        WHERE batch_id = ?
        ORDER BY id
    ");
    $stmt->execute([$batch['id']]);
    $batch['costs'] = $stmt->fetchAll();

    // Payments total
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS paid FROM batch_payments WHERE batch_id = ?");
    $stmt->execute([$batch['id']]);
    $batch['paid'] = $stmt->fetchColumn();

    // Calculate totals
    $batch['total_pieces'] = array_sum(array_column($batch['items'], 'quantity'));
    $batch['total_hair_sum'] = array_sum(array_column($batch['items'], 'total_hair'));
    $batch['costs_subtotal'] = array_sum(array_column($batch['costs'], 'line_total'));
    $batch['total_cost'] = $batch['costs_subtotal']; // Only additional costs count for payment
    $batch['due'] = $batch['total_cost'] - $batch['paid'];
    if ($batch['due'] < 0) $batch['due'] = 0;
}
unset($batch);

// Filter batches to keep only those with due > 0
$batches = array_filter($all_batches, function($batch) {
    return $batch['due'] > 0;
});

// Re-index array
$batches = array_values($batches);

// Calculate grand total due from filtered batches
$grand_total_due = array_sum(array_column($batches, 'due'));

// --- Get user photo (optional) ---
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

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Due Payment Report | MIRAGE</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* --- CSS Variables & Reset (same as other pages) --- */
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
            --color-success: #10B981;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;
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
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
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

        .sidebar-menu { padding: 20px 12px; overflow-y: auto; overflow-x: hidden; flex: 1; }
        .menu-item { margin-bottom: 4px; }
        .menu-link {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; border-radius: 8px;
            color: rgba(255,255,255,0.7); cursor: pointer; transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        .menu-link:hover, .menu-link.active { background-color: rgba(255,255,255,0.1); color: #fff; transform: translateX(2px); }
        .link-content { display: flex; align-items: center; gap: 12px; }
        .menu-icon { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .arrow-icon { transition: transform 0.3s ease; font-size: 0.8rem; opacity: 0.7; }

        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; padding-left: 12px; }
        .menu-item.open > .submenu { max-height: 500px; }
        .menu-item.open > .menu-link .arrow-icon { transform: rotate(180deg); }
        .menu-item.open > .menu-link { color: #fff; }
        .submenu-link {
            display: block; padding: 10px 16px 10px 42px;
            color: rgba(255,255,255,0.5); font-size: 0.9rem; border-radius: 8px;
            transition: all 0.2s ease;
        }
        .submenu-link:hover { color: #fff; background: rgba(255,255,255,0.05); }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

        .top-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px; flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .toggle-btn {
            background: none; border: none; font-size: 1.5rem; cursor: pointer;
            color: var(--text-muted); display: flex; align-items: center;
            transition: all 0.2s ease; padding: 8px; border-radius: 8px;
        }
        .toggle-btn:hover { color: var(--primary); background: var(--bg-body); }

        .header-right { display: flex; align-items: center; gap: 24px; }
        .profile-container { position: relative; }
        .profile-menu {
            display: flex; align-items: center; gap: 12px; cursor: pointer;
            padding: 8px 12px; border-radius: 12px; transition: all 0.2s ease;
        }
        .profile-menu:hover { background-color: var(--bg-body); }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-name { font-size: 0.9rem; font-weight: 600; display: block; }
        .profile-role { font-size: 0.75rem; color: var(--text-muted); }
        .profile-img {
            width: 42px; height: 42px; border-radius: 12px; object-fit: cover;
            border: 2px solid var(--border);
        }
        .profile-placeholder {
            width: 42px; height: 42px; border-radius: 12px; background: var(--primary);
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 1.1rem;
        }

        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); right: 0; width: 220px;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
            padding: 8px; z-index: 1000; display: none; flex-direction: column; gap: 4px;
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .dropdown-menu.show { display: flex; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px; padding: 12px 16px;
            font-size: 0.9rem; color: var(--text-main); border-radius: 8px;
            transition: all 0.2s ease;
        }
        .dropdown-item:hover { background-color: var(--bg-body); color: var(--primary); }
        .dropdown-item.danger:hover { background-color: rgba(239,68,68,0.1); color: #ef4444; }

        .scrollable { flex: 1; overflow-y: auto; padding: 32px; }

        /* Page specific styles */
        .sheet-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
        }
        .subtitle {
            color: var(--text-muted);
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        .search-bar {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .search-bar label {
            font-weight: 600;
            color: var(--text-main);
        }
        input, select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 0.95rem;
        }
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-secondary {
            background: var(--text-muted);
        }
        .btn-secondary:hover { background: #4b5563; }
        .btn-success {
            background: #10B981;
        }
        .btn-success:hover { background: #059669; }
        .btn-danger {
            background: #EF4444;
        }
        .btn-danger:hover { background: #DC2626; }
        .btn-warning {
            background: #F59E0B;
            color: white;
        }
        .btn-warning:hover { background: #D97706; }

        .batch-card {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .batch-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .batch-header .date {
            color: var(--text-muted);
            font-weight: normal;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            border: 1px solid var(--border);
            margin-bottom: 15px;
        }
        th {
            background: var(--sidebar-bg);
            color: white;
            font-weight: 600;
            padding: 8px 4px;
            text-align: center;
            border: 1px solid var(--border);
        }
        td {
            padding: 6px 4px;
            border: 1px solid var(--border);
            text-align: center;
        }
        td:first-child {
            text-align: left;
        }
        .sub-table {
            margin-left: 20px;
            width: calc(100% - 20px);
        }
        .totals-row {
            background: var(--border);
            font-weight: 600;
        }
        .payment-summary {
            background: #f9fafb;
            border: 1px solid var(--border);
            border-radius: 6px;
            padding: 15px;
            margin: 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .payment-info {
            display: flex;
            gap: 30px;
            font-size: 1rem;
            flex-wrap: wrap;
        }
        .payment-info span {
            font-weight: 600;
        }
        .due-amount {
            color: var(--color-danger);
            font-size: 1.2rem;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .subcontractor-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .grand-total {
            background: var(--primary);
            color: white;
            font-size: 1.3rem;
            font-weight: 700;
            padding: 15px 25px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
        #themeToggle {
            background: var(--bg-card); border: 1px solid var(--border);
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);
            z-index: 45; display: none;
        }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -280px; }
            body.mobile-open .sidebar { transform: translateX(280px); }
            body.mobile-open .overlay { display: block; }
            .profile-info { display: none; }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar (same as other pages) -->
    <?php include 'sidenavbar.php'; ?>


    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle"><i class="ph ph-list"></i></button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Due Report</span>
                </div>
            </div>
            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme"><i class="ph ph-moon" id="themeIcon"></i></button>
                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($_SESSION['username']); ?></span>
                            <span class="profile-role"><?php echo ucfirst(e($_SESSION['role'])); ?></span>
                        </div>
                        <?php if (!empty($userPhoto)): ?>
                            <img src="<?php echo e($userPhoto); ?>" alt="Profile" class="profile-img">
                        <?php else: ?>
                            <div class="profile-placeholder"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="admin_dashboard.php" class="dropdown-item"><i class="ph ph-house"></i> Dashboard</a>
                        <a href="profile_settings.php" class="dropdown-item"><i class="ph ph-user-gear"></i> Profile Settings</a>
                        <div style="border-top:1px solid var(--border); margin:4px 0;"></div>
                        <a href="?action=logout" class="dropdown-item danger"><i class="ph ph-sign-out"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="scrollable">
            <div class="sheet-container">
                <h1>💰 Due Payment Report</h1>
                <div class="subtitle">View batches with outstanding payments – filter by subcontractor. Total cost = additional costs only.</div>

                <!-- Search form -->
                <div class="search-bar">
                    <label>Select Subcontractor:</label>
                    <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select name="subcontractor_id" id="subcontractorSelect" style="min-width: 250px;">
                            <option value="0">-- All Subcontractors --</option>
                            <?php foreach ($subs as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $selected_sub_id == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn"><i class="ph ph-magnifying-glass"></i> Generate Report</button>
                        <?php if ($selected_sub_id > 0): ?>
                            <a href="mirage_due_report.php" class="btn btn-secondary"><i class="ph ph-x-circle"></i> Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ($selected_sub_id > 0 && $selected_sub_name): ?>
                    <div class="subcontractor-header">
                        <h2>Batches for: <?= e($selected_sub_name) ?></h2>
                        <!-- Button now links to the due overview page (lists all batches with wig pieces) -->
                        <a href="mirage_subcontractor_due.php?subcontractor_id=<?= $selected_sub_id ?>" class="btn btn-warning">
                            <i class="ph ph-eye"></i> View Due Batches for <?= e($selected_sub_name) ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if (!empty($batches)): ?>
                    <?php foreach ($batches as $batch): ?>
                        <div class="batch-card">
                            <div class="batch-header">
                                <span><?= e($batch['lod_name']) ?> (Batch #<?= $batch['id'] ?>)</span>
                                <span class="date"><?= e($batch['production_date']) ?></span>
                            </div>
                            <?php if ($selected_sub_id == 0 && $batch['subcontractor_name']): ?>
                                <div style="margin-bottom: 10px; color: var(--text-muted);">Subcontractor: <?= e($batch['subcontractor_name']) ?></div>
                            <?php endif; ?>

                            <!-- Items Table (hair quantities) -->
                            <?php if (!empty($batch['items'])): ?>
                                <h4 style="margin-bottom: 10px;">Wig Pieces (Hair Quantity)</h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Size</th>
                                            <th>Per Piece</th>
                                            <th>Unit</th>
                                            <th>Quantity</th>
                                            <th>Total Hair</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($batch['items'] as $item): ?>
                                            <tr>
                                                <td><?= e($item['type']) ?></td>
                                                <td><?= e($item['size']) ?></td>
                                                <td><?= e($item['per_piece']) ?></td>
                                                <td><?= e($item['unit']) ?></td>
                                                <td><?= e($item['quantity']) ?></td>
                                                <td><?= number_format($item['total_hair'], 3) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="totals-row">
                                        <tr>
                                            <td colspan="5" style="text-align: right;">Total Hair Quantity:</td>
                                            <td><?= number_format($batch['total_hair_sum'], 3) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php endif; ?>

                            <!-- Additional Costs Table (monetary) -->
                            <?php if (!empty($batch['costs'])): ?>
                                <h4 style="margin: 20px 0 10px;">Additional Costs</h4>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Size</th>
                                            <th>Description</th>
                                            <th>Quantity</th>
                                            <th>Unit Price (BDT)</th>
                                            <th>Line Total (BDT)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($batch['costs'] as $cost): ?>
                                            <tr>
                                                <td><?= e($cost['size']) ?></td>
                                                <td><?= e($cost['description']) ?></td>
                                                <td><?= e($cost['quantity']) ?></td>
                                                <td><?= number_format($cost['unit_price'], 2) ?></td>
                                                <td><?= number_format($cost['line_total'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="totals-row">
                                        <tr>
                                            <td colspan="4" style="text-align: right;">Additional Costs Subtotal:</td>
                                            <td><?= number_format($batch['costs_subtotal'], 2) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php endif; ?>

                            <!-- Payment Summary with Action Buttons -->
                            <div class="payment-summary">
                                <div class="payment-info">
                                    <span>Total Cost: <?= number_format($batch['total_cost'], 2) ?> BDT</span>
                                    <span>Paid: <?= number_format($batch['paid'], 2) ?> BDT</span>
                                    <span class="due-amount">Due: <?= number_format($batch['due'], 2) ?> BDT</span>
                                </div>
                                <div class="action-buttons">
                                    <?php if ($batch['due'] > 0): ?>
                                        <a href="mirage_make_payment.php?batch_id=<?= $batch['id'] ?>" class="btn btn-success">Pay Now</a>
                                    <?php endif; ?>
                                    <!-- Delete button with confirmation -->
                                    <a href="mirage_delete_batch.php?id=<?= $batch['id'] ?>" 
                                       class="btn btn-danger" 
                                       onclick="return confirm('Are you sure you want to delete this batch? This action cannot be undone.')">
                                        <i class="ph ph-trash"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Grand Total Due -->
                    <div class="grand-total">
                        <span>GRAND TOTAL DUE</span>
                        <span><?= number_format($grand_total_due, 2) ?> BDT</span>
                    </div>
                <?php else: ?>
                    <div class="no-data">No batches with due amount found.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        // --- Sidebar Toggle ---
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

        // --- Accordion ---
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            const link = item.querySelector('.menu-link');
            if (link && !link.querySelector('a')) {
                link.addEventListener('click', (e) => {
                    if (!link.hasAttribute('href')) {
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

        // --- Profile Dropdown ---
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
    </script>
</body>
</html>