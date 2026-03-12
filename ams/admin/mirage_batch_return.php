<?php
/**
 * mirage_batch_return.php
 * MIRAGE LOD‑3 – Record Wig Returns from Subcontractor.
 * 
 * For a selected batch, list all wig pieces with original quantities (displayed in pieces).
 * Input fields for returned (undamaged) and damaged quantities.
 * - Updates inventory for returned undamaged pieces.
 * - Updates wigs_batch_items by subtracting returned+damaged.
 * Records the transaction in batch_returns.
 * 
 * CONFIGURATION: Set your inventory table column names below.
 */

session_start();

// --- Authentication (temporary for development) ---
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

// ========== CONFIGURATION ==========
// Set the actual column names from your inventory table
$inventory_type_column = 'type';      // e.g., 'item_type', 'product_type'
$inventory_size_column = 'size';      // e.g., 'size_name', 'size'
// ====================================

// --- Get all subcontractors for dropdown ---
$subs = $pdo->query("SELECT id, company_name FROM subcontractors ORDER BY company_name")->fetchAll();

// --- Handle filter ---
$selected_sub_id = isset($_GET['subcontractor_id']) ? (int)$_GET['subcontractor_id'] : 0;
$selected_sub_name = '';
$batches = [];

if ($selected_sub_id > 0) {
    $stmt = $pdo->prepare("SELECT company_name FROM subcontractors WHERE id = ?");
    $stmt->execute([$selected_sub_id]);
    $sub = $stmt->fetch();
    $selected_sub_name = $sub ? $sub['company_name'] : '';
}

// Fetch batches for the selected subcontractor
if ($selected_sub_id > 0) {
    $stmt = $pdo->prepare("
        SELECT id, lod_name, production_date
        FROM wigs_batches
        WHERE subcontractor_id = ?
        ORDER BY production_date DESC
    ");
    $stmt->execute([$selected_sub_id]);
    $batches = $stmt->fetchAll();
}

// --- Get selected batch ID from query string or form ---
$selected_batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : (isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0);
$batch_items = [];
$batch_info = null;

if ($selected_batch_id > 0) {
    // Fetch batch details
    $stmt = $pdo->prepare("
        SELECT b.id, b.lod_name, b.production_date, s.company_name AS subcontractor_name
        FROM wigs_batches b
        LEFT JOIN subcontractors s ON b.subcontractor_id = s.id
        WHERE b.id = ?
    ");
    $stmt->execute([$selected_batch_id]);
    $batch_info = $stmt->fetch();

    // Fetch wig items for this batch
    $stmt = $pdo->prepare("
        SELECT id, type, size, per_piece, unit, quantity
        FROM wigs_batch_items
        WHERE batch_id = ?
        ORDER BY type, size
    ");
    $stmt->execute([$selected_batch_id]);
    $batch_items = $stmt->fetchAll();
}

// --- Check inventory table columns (to avoid SQL errors) ---
$inventory_columns = [];
try {
    $stmt = $pdo->query("DESCRIBE inventory");
    $inventory_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // inventory table might not exist – ignore
}

// --- Handle form submission ---
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_return'])) {
    $batch_id = (int)$_POST['batch_id'];
    $return_date = $_POST['return_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');

    // Validate that we have items
    if (empty($batch_items)) {
        $error = 'No items found for this batch.';
    } else {
        // Begin transaction
        try {
            $pdo->beginTransaction();

            $any_return = false;
            $inventory_warning = '';

            foreach ($batch_items as $item) {
                $returned = (float)($_POST["returned_{$item['id']}"] ?? 0);
                $damaged = (float)($_POST["damaged_{$item['id']}"] ?? 0);

                if ($returned < 0 || $damaged < 0) {
                    throw new Exception("Quantities cannot be negative.");
                }

                // Check that returned + damaged <= original quantity
                if ($returned + $damaged > $item['quantity']) {
                    throw new Exception("Returned + damaged cannot exceed original quantity for {$item['type']} {$item['size']}.");
                }

                if ($returned > 0 || $damaged > 0) {
                    $any_return = true;

                    // 1. Insert into batch_returns table
                    $stmt = $pdo->prepare("
                        INSERT INTO batch_returns (batch_id, type, size, returned_qty, damaged_qty, return_date, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$batch_id, $item['type'], $item['size'], $returned, $damaged, $return_date, $notes]);

                    // 2. Update wigs_batch_items (subtract returned+damaged)
                    $stmt2 = $pdo->prepare("
                        UPDATE wigs_batch_items 
                        SET quantity = quantity - ? 
                        WHERE id = ?
                    ");
                    $stmt2->execute([$returned + $damaged, $item['id']]);

                    // 3. Update inventory for returned undamaged pieces (if any)
                    if ($returned > 0) {
                        // Check if the configured inventory columns exist
                        if (in_array($inventory_type_column, $inventory_columns) && in_array($inventory_size_column, $inventory_columns)) {
                            $stmt3 = $pdo->prepare("
                                UPDATE inventory 
                                SET quantity = quantity + ? 
                                WHERE `$inventory_type_column` = ? AND `$inventory_size_column` = ? 
                                LIMIT 1
                            ");
                            $stmt3->execute([$returned, $item['type'], $item['size']]);
                        } else {
                            $inventory_warning = "Warning: Inventory update skipped – columns '$inventory_type_column' and/or '$inventory_size_column' not found in inventory table. Please update the configuration.";
                        }
                    }
                }
            }

            if (!$any_return) {
                throw new Exception("No return or damage quantities entered.");
            }

            $pdo->commit();
            $message = "Return recorded successfully. Batch items have been updated.";
            if ($inventory_warning) {
                $message .= " " . $inventory_warning;
            }

            // Refresh batch items to show updated quantities
            $stmt = $pdo->prepare("
                SELECT id, type, size, per_piece, unit, quantity
                FROM wigs_batch_items
                WHERE batch_id = ?
                ORDER BY type, size
            ");
            $stmt->execute([$batch_id]);
            $batch_items = $stmt->fetchAll();

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

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
    <title>Batch Return | MIRAGE</title>
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

        .return-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border);
            margin: 20px 0;
        }
        .return-table th {
            background: var(--sidebar-bg);
            color: white;
            font-weight: 600;
            padding: 12px 8px;
            text-align: center;
            border: 1px solid var(--border);
        }
        .return-table td {
            padding: 10px 8px;
            border: 1px solid var(--border);
            text-align: center;
        }
        .return-table input[type="number"] {
            width: 80px;
            padding: 6px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 0.95rem;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
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

    <!-- Sidebar (simplified) -->
    <?php include 'sidenavbar.php'; ?>


    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle"><i class="ph ph-list"></i></button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Batch Return</span>
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
                <h1>📦 Record Batch Return</h1>
                <div class="subtitle">Select a subcontractor and batch, then enter returned and damaged quantities for each wig piece. Returned undamaged pieces will be added back to inventory, and batch item quantities will be reduced.</div>

                <!-- Subcontractor selection form -->
                <div class="search-bar">
                    <label>Select Subcontractor:</label>
                    <form method="get" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <select name="subcontractor_id" style="min-width: 250px;" onchange="this.form.submit()">
                            <option value="0">-- Choose a subcontractor --</option>
                            <?php foreach ($subs as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $selected_sub_id == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit" class="btn">Load</button></noscript>
                        <?php if ($selected_sub_id > 0): ?>
                            <a href="mirage_batch_return.php" class="btn btn-secondary">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if ($selected_sub_id > 0 && !empty($batches)): ?>
                    <!-- Batch selection (if multiple) -->
                    <div style="margin-bottom: 20px;">
                        <form method="get">
                            <input type="hidden" name="subcontractor_id" value="<?= $selected_sub_id ?>">
                            <label for="batch_id" style="margin-right: 10px;">Select Batch:</label>
                            <select name="batch_id" id="batch_id" onchange="this.form.submit()">
                                <option value="">-- Select a batch --</option>
                                <?php foreach ($batches as $b): ?>
                                    <option value="<?= $b['id'] ?>" <?= $selected_batch_id == $b['id'] ? 'selected' : '' ?>>
                                        <?= e($b['lod_name']) ?> (<?= e($b['production_date']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <noscript><button type="submit" class="btn">Load Batch</button></noscript>
                        </form>
                    </div>
                <?php elseif ($selected_sub_id > 0 && empty($batches)): ?>
                    <div class="alert alert-warning">No batches found for this subcontractor.</div>
                <?php endif; ?>

                <?php if ($batch_info && !empty($batch_items)): ?>
                    <h2>Batch: <?= e($batch_info['lod_name']) ?> (<?= e($batch_info['production_date']) ?>)</h2>
                    <p>Subcontractor: <?= e($batch_info['subcontractor_name']) ?></p>

                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= e($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <input type="hidden" name="batch_id" value="<?= $selected_batch_id ?>">

                        <table class="return-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Original Quantity (pcs)</th>
                                    <th>Returned (undamaged)</th>
                                    <th>Damaged</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batch_items as $item): ?>
                                <tr>
                                    <td><?= e($item['type']) ?></td>
                                    <td><?= e($item['size']) ?></td>
                                    <td><?= number_format($item['quantity']) ?> pcs</td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="<?= $item['quantity'] ?>" 
                                               name="returned_<?= $item['id'] ?>" value="0">
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" min="0" max="<?= $item['quantity'] ?>" 
                                               name="damaged_<?= $item['id'] ?>" value="0">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <div class="form-group">
                            <label for="return_date">Return Date</label>
                            <input type="date" name="return_date" id="return_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes (optional)</label>
                            <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                        </div>

                        <div style="margin-top: 20px;">
                            <button type="submit" name="record_return" class="btn btn-success"><i class="ph ph-check"></i> Record Return</button>
                            <a href="mirage_batch_return.php?subcontractor_id=<?= $selected_sub_id ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php elseif ($selected_batch_id > 0 && empty($batch_items)): ?>
                    <div class="alert alert-warning">No wig pieces found for this batch.</div>
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