<?php
/**
 * mirage_make_payment.php
 * MIRAGE LOD‑3 – Make Payment for a Specific Batch.
 * 
 * UPDATED: Added robust error handling, table structure checks, and logging.
 */

// --- Enable detailed error reporting (REMOVE in production) ---
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/payment_errors.log'); // Log to file in same directory

session_start();

// --- Authentication (temporary) ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexander Pierce';
    $_SESSION['role'] = 'admin';
    $_SESSION['email'] = 'alex@example.com';
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
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
    error_log("MIRAGE DB Connection error: " . $ex->getMessage());
    die("Database connection failed. Please try again later.");
}

// --- Helper: check if a table exists ---
function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SHOW TABLES LIKE '{$table}'");
        return $result->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// --- Helper: check if a column exists in a table ---
function columnExists($pdo, $table, $column) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// --- Validate required tables and columns ---
$requiredTables = ['batch_payments', 'payment_wig_pieces', 'inventory', 'wigs_batches', 'subcontractors', 'wigs_batch_costs', 'wigs_batch_items'];
foreach ($requiredTables as $table) {
    if (!tableExists($pdo, $table)) {
        die("Required table '{$table}' does not exist. Please contact administrator.");
    }
}

// Check columns in batch_payments
$requiredBatchPaymentCols = ['id', 'batch_id', 'amount', 'payment_date'];
foreach ($requiredBatchPaymentCols as $col) {
    if (!columnExists($pdo, 'batch_payments', $col)) {
        die("Column '{$col}' missing in batch_payments table.");
    }
}

// Check columns in payment_wig_pieces
$requiredWigCols = ['id', 'payment_id', 'inventory_id', 'quantity', 'value'];
foreach ($requiredWigCols as $col) {
    if (!columnExists($pdo, 'payment_wig_pieces', $col)) {
        die("Column '{$col}' missing in payment_wig_pieces table.");
    }
}

// --- Initialize status variables ---
$invalid_batch = false;
$fully_paid = false;

// --- Get batch ID from URL ---
$batch_id = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

if ($batch_id <= 0) {
    header("Location: mirage_due_simple.php");
    exit;
}

// Fetch batch details
try {
    $stmt = $pdo->prepare("
        SELECT 
            b.id,
            b.lod_name,
            b.production_date,
            s.company_name AS subcontractor_name,
            s.id AS subcontractor_id,
            COALESCE((SELECT SUM(amount) FROM batch_payments WHERE batch_id = b.id), 0) AS paid,
            COALESCE((SELECT SUM(quantity * unit_price) FROM wigs_batch_costs WHERE batch_id = b.id), 0) AS total_cost,
            COALESCE((SELECT SUM(quantity) FROM wigs_batch_items WHERE batch_id = b.id), 0) AS total_pieces
        FROM wigs_batches b
        LEFT JOIN subcontractors s ON b.subcontractor_id = s.id
        WHERE b.id = ?
    ");
    $stmt->execute([$batch_id]);
    $batch = $stmt->fetch();
} catch (PDOException $ex) {
    error_log("MIRAGE fetch batch error: " . $ex->getMessage());
    die("Error fetching batch details.");
}

if (!$batch) {
    $invalid_batch = true;
} else {
    $batch['due'] = $batch['total_cost'] - $batch['paid'];
    if ($batch['due'] <= 0) {
        $fully_paid = true;
    }
}

// --- Fetch available wig pieces from inventory ---
$inventory_items = [];
if (!$invalid_batch && !$fully_paid) {
    try {
        $stmt = $pdo->query("SELECT id, type, size, quantity, unit FROM inventory WHERE type IN ('Top', 'Skin') AND quantity > 0 ORDER BY type, size");
        $inventory_items = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("MIRAGE inventory fetch error: " . $e->getMessage());
        $inventory_items = [];
    }
}

// --- Handle form submission ---
$message = '';
$error = '';

if (!$invalid_batch && !$fully_paid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $cash_amount = isset($_POST['cash_amount']) ? (float)$_POST['cash_amount'] : 0;
    $payment_date = isset($_POST['payment_date']) ? $_POST['payment_date'] : date('Y-m-d');

    // Validate date
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $payment_date)) {
        $payment_date = date('Y-m-d');
    }

    // Wig pieces
    $wig_pieces = [];
    if (isset($_POST['wig_piece_id']) && is_array($_POST['wig_piece_id'])) {
        for ($i = 0; $i < count($_POST['wig_piece_id']); $i++) {
            $piece_id = (int)$_POST['wig_piece_id'][$i];
            $quantity = (float)$_POST['wig_quantity'][$i];
            $value = (float)$_POST['wig_value'][$i];
            if ($piece_id > 0 && $quantity > 0 && $value > 0) {
                $wig_pieces[] = [
                    'id' => $piece_id,
                    'quantity' => $quantity,
                    'value' => $value
                ];
            }
        }
    }

    // Validation
    if ($cash_amount < 0) {
        $error = 'Cash amount cannot be negative.';
    } elseif ($cash_amount == 0 && empty($wig_pieces)) {
        $error = 'Please enter a cash amount or select wig pieces with value.';
    } else {
        $total_payment = $cash_amount;
        foreach ($wig_pieces as $piece) {
            $total_payment += $piece['value'];
        }

        if ($total_payment > $batch['due']) {
            $error = 'Total payment (' . number_format($total_payment, 2) . ') exceeds due amount (' . number_format($batch['due'], 2) . ').';
        } else {
            // --- Begin transaction ---
            try {
                $pdo->beginTransaction();

                // 1. Get next ID for batch_payments
                $stmt = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM batch_payments");
                $nextPaymentId = (int)$stmt->fetchColumn();

                // 2. Insert into batch_payments
                $stmt = $pdo->prepare("INSERT INTO batch_payments (id, batch_id, amount, payment_date) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nextPaymentId, $batch_id, $total_payment, $payment_date]);
                $payment_id = $nextPaymentId;

                // 3. Process wig pieces
                foreach ($wig_pieces as $piece) {
                    // Check inventory
                    $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
                    $stmt->execute([$piece['id']]);
                    $inv = $stmt->fetch();
                    if (!$inv) {
                        throw new Exception("Wig piece ID {$piece['id']} not found.");
                    }
                    if ($inv['quantity'] < $piece['quantity']) {
                        throw new Exception("Insufficient quantity for piece ID {$piece['id']}.");
                    }

                    // Deduct
                    $stmt = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
                    $stmt->execute([$piece['quantity'], $piece['id']]);

                    // Get next ID for payment_wig_pieces
                    $stmt = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM payment_wig_pieces");
                    $nextWigId = (int)$stmt->fetchColumn();

                    // Insert record
                    $stmt = $pdo->prepare("INSERT INTO payment_wig_pieces (id, payment_id, inventory_id, quantity, value) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$nextWigId, $payment_id, $piece['id'], $piece['quantity'], $piece['value']]);
                }

                $pdo->commit();

                // Redirect to invoice if exists
                if (file_exists('mirage_payment_invoice.php')) {
                    header("Location: mirage_payment_invoice.php?payment_id=" . $payment_id);
                } else {
                    $message = "Payment recorded successfully! (Invoice page not found)";
                }
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = 'Database error: ' . $e->getMessage();
                error_log("MIRAGE payment PDO error: " . $e->getMessage());
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Error: ' . $e->getMessage();
                error_log("MIRAGE payment general error: " . $e->getMessage());
            }
        }
    }
}

// --- Get user photo (optional) ---
$userPhoto = '';
if (isset($_SESSION['user_id'])) {
    try {
        if (tableExists($pdo, 'users') && columnExists($pdo, 'users', 'avatar')) {
            $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $userData = $stmt->fetch();
            $userPhoto = $userData['avatar'] ?? '';
        }
    } catch (Exception $e) {
        error_log("MIRAGE user photo error: " . $e->getMessage());
    }
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Make Payment | MIRAGE</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* --- CSS Variables & Reset (same as original) --- */
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
        .batch-info {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
        }
        .batch-info-item {
            font-size: 1rem;
        }
        .batch-info-item strong {
            font-weight: 600;
            color: var(--text-main);
            margin-right: 5px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-main);
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
        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        /* Box for wig pieces */
        .wig-pieces-box {
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            background-color: var(--bg-card);
        }
        .wig-pieces-box h3 {
            margin-top: 0;
            margin-bottom: 10px;
        }
        .wig-pieces-box .add-row-btn {
            margin-top: 15px;
        }

        .wig-piece-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }
        .wig-piece-row select, .wig-piece-row input {
            flex: 1;
        }
        .wig-piece-row .remove-row {
            background: none;
            border: none;
            color: var(--color-danger);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0 5px;
        }
        .add-row-btn {
            background: none;
            border: 1px dashed var(--border);
            color: var(--primary);
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
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
    <?php include 'sidenavbar.php';?>
    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle"><i class="ph ph-list"></i></button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Make Payment</span>
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
                <h1>💰 Make Payment</h1>
                <div class="subtitle">Record a payment for a batch – you can pay in cash and/or give wig pieces. For wig pieces, enter their monetary value; the total (cash + wig value) will be deducted from the batch's due.</div>

                <?php if ($invalid_batch): ?>
                    <div class="alert alert-danger">
                        <strong>Invalid batch.</strong> The selected batch does not exist.
                        <div style="margin-top: 15px;">
                            <a href="mirage_due_simple.php" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Back to Due List</a>
                        </div>
                    </div>
                <?php elseif ($fully_paid): ?>
                    <div class="alert alert-warning">
                        <strong>Batch fully paid.</strong> This batch has no outstanding due.
                        <div style="margin-top: 15px;">
                            <a href="mirage_due_simple.php<?= $batch['subcontractor_id'] ? '?subcontractor_id='.$batch['subcontractor_id'] : '' ?>" class="btn btn-secondary"><i class="ph ph-arrow-left"></i> Back to Due List</a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Batch Information -->
                    <div class="batch-info">
                        <div class="batch-info-item"><strong>Batch:</strong> <?= e($batch['lod_name']) ?> (ID: <?= $batch['id'] ?>)</div>
                        <div class="batch-info-item"><strong>Subcontractor:</strong> <?= e($batch['subcontractor_name']) ?></div>
                        <div class="batch-info-item"><strong>Production Date:</strong> <?= e($batch['production_date']) ?></div>
                        <div class="batch-info-item"><strong>Total Pieces:</strong> <?= number_format($batch['total_pieces']) ?></div>
                        <div class="batch-info-item"><strong>Total Cost:</strong> <?= number_format($batch['total_cost'], 2) ?> BDT</div>
                        <div class="batch-info-item"><strong>Paid:</strong> <?= number_format($batch['paid'], 2) ?> BDT</div>
                        <div class="batch-info-item"><strong>Due:</strong> <span style="color: var(--color-danger);"><?= number_format($batch['due'], 2) ?> BDT</span></div>
                    </div>

                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= e($message) ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= e($error) ?></div>
                    <?php endif; ?>

                    <form method="post" id="paymentForm">
                        <!-- Cash payment -->
                        <div class="form-group">
                            <label for="cash_amount">Cash Amount (BDT)</label>
                            <input type="number" step="0.01" name="cash_amount" id="cash_amount" class="form-control" value="0" min="0">
                        </div>

                        <!-- Payment date -->
                        <div class="form-group">
                            <label for="payment_date">Payment Date</label>
                            <input type="date" name="payment_date" id="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>

                        <!-- Wig pieces section -->
                        <div class="wig-pieces-box">
                            <h3>Give Wig Pieces (Optional)</h3>
                            <p class="text-muted">Select wig pieces to give. Enter quantity and the monetary value. The value will be added to the payment total.</p>
                            <div id="wig-pieces-container"></div>
                            <button type="button" class="add-row-btn" onclick="addWigPieceRow()">
                                <i class="ph ph-plus-circle"></i> Add Wig Piece
                            </button>
                        </div>

                        <!-- Submit -->
                        <div style="margin-top: 30px;">
                            <button type="submit" class="btn btn-success"><i class="ph ph-check"></i> Record Payment</button>
                            <a href="mirage_due_simple.php<?= $batch['subcontractor_id'] ? '?subcontractor_id='.$batch['subcontractor_id'] : '' ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- JavaScript (unchanged) -->
    <script>
        // --- Sidebar Toggle, Accordion, Profile Dropdown, Dark Mode (same as original) ---
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

        // --- Wig Piece Rows ---
        let inventoryItems = <?= json_encode($inventory_items) ?>;
        let rowCount = 0;

        function addWigPieceRow() {
            const container = document.getElementById('wig-pieces-container');
            const rowDiv = document.createElement('div');
            rowDiv.className = 'wig-piece-row';
            rowDiv.id = 'wig-row-' + rowCount;

            let selectHtml = '<select name="wig_piece_id[]" class="form-control" required>';
            selectHtml += '<option value="">-- Select Wig Piece --</option>';
            inventoryItems.forEach(item => {
                selectHtml += `<option value="${item.id}">${item.type} - ${item.size} (${item.quantity} ${item.unit} available)</option>`;
            });
            selectHtml += '</select>';

            rowDiv.innerHTML = `
                ${selectHtml}
                <input type="number" step="0.01" name="wig_quantity[]" class="form-control" placeholder="Quantity" min="0.01" required>
                <input type="number" step="0.01" name="wig_value[]" class="form-control" placeholder="Value (BDT)" min="0.01" required>
                <button type="button" class="remove-row" onclick="removeWigPieceRow('wig-row-${rowCount}')"><i class="ph ph-x"></i></button>
            `;

            container.appendChild(rowDiv);
            rowCount++;
        }

        function removeWigPieceRow(rowId) {
            const row = document.getElementById(rowId);
            if (row) row.remove();
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (inventoryItems.length > 0) {
                addWigPieceRow();
            }
        });
    </script>
</body>
</html>