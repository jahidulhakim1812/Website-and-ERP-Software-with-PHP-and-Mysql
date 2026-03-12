<?php
/**
 * invoice_view.php
 * Invoice View with Verify‑style Amount Display + Full Item List
 */

session_start();

// --- SECURITY HEADERS ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// --- AUTHENTICATION & SESSION TIMEOUT ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$timeout = 1800; // 30 minutes
if (isset($_SESSION['login_time'])) {
    $session_life = time() - $_SESSION['login_time'];
    if ($session_life > $timeout) {
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }
}
$_SESSION['login_time'] = time();

// --- DATABASE CONNECTION ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Database connection failed. Please try again later.");
}

// --- HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
function formatCurrency($amount) { return '$' . number_format($amount, 2); }

// --- FETCH INVOICE DATA ---
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid invoice ID.");
}
$id = (int)$_GET['id'];

// Invoice header with customer info
$stmt = $pdo->prepare("
    SELECT po.*, c.full_name, c.phone, c.address, c.email
    FROM purchase_orders po
    LEFT JOIN customers c ON po.customer_id = c.id
    WHERE po.id = ?
");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    die("Invoice not found.");
}

// Invoice items with product details
$stmt = $pdo->prepare("
    SELECT pi.*, p.product_name, p.image_url
    FROM purchase_order_items pi
    LEFT JOIN products p ON pi.product_id = p.id
    WHERE pi.purchase_id = ?
");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Payment history (for display only – not used in amount calculation)
$stmt = $pdo->prepare("SELECT * FROM payment_history WHERE purchase_id = ? ORDER BY paid_at DESC");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

// --- ACCURATE AMOUNT CALCULATIONS (exactly like verify_invoice.php) ---
$totalAmount = (float)($invoice['total_amount'] ?? 0);
// Paid amount is taken directly from the invoice's paid_amount column (primary source)
$paidAmount = (float)($invoice['paid_amount'] ?? 0);
$dueAmount = $totalAmount - $paidAmount;
// Ensure due amount is never negative (in case of overpayment)
if ($dueAmount < 0) $dueAmount = 0;

// Auto-print if requested
$autoPrint = isset($_GET['print']) && $_GET['print'] === 'true';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= e($invoice['reference_no']) ?> | NexusAdmin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* === CSS VARIABLES & RESET (exactly as verify_invoice.php) === */
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
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            height: 100vh;
            display: flex;
            overflow: hidden;
        }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button { font-family: inherit; cursor: pointer; }

        /* === SIDEBAR (same as verify) === */
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
        body.sidebar-collapsed .arrow-icon { display: none; }

        body.sidebar-collapsed .sidebar-header { justify-content: center; padding: 0; }
        body.sidebar-collapsed .menu-link { justify-content: center; padding: 12px 0; }
        body.sidebar-collapsed .link-content { gap: 0; }
        body.sidebar-collapsed .menu-icon { font-size: 1.5rem; margin: 0; }

        .sidebar-menu { padding: 20px 12px; overflow-y: auto; flex: 1; }
        .menu-item { margin-bottom: 4px; }
        .menu-link {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; border-radius: 8px;
            color: rgba(255,255,255,0.7); cursor: pointer; transition: all 0.2s;
            font-size: 0.95rem;
        }
        .menu-link:hover, .menu-link.active { background: rgba(255,255,255,0.1); color: #fff; }
        .link-content { display: flex; align-items: center; gap: 12px; }
        .menu-icon { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .arrow-icon { transition: transform 0.3s; font-size: 0.8rem; opacity: 0.7; }

        .submenu {
            max-height: 0; overflow: hidden; transition: max-height 0.3s;
            padding-left: 12px;
        }
        .menu-item.open > .submenu { max-height: 500px; }
        .menu-item.open > .menu-link .arrow-icon { transform: rotate(180deg); }

        .submenu-link {
            display: block; padding: 10px 16px 10px 42px;
            color: rgba(255,255,255,0.5); font-size: 0.9rem; border-radius: 8px;
            transition: all 0.2s;
        }
        .submenu-link:hover { color: #fff; background: rgba(255,255,255,0.05); }

        /* === MAIN CONTENT === */
        .main-content {
            flex: 1; display: flex; flex-direction: column; overflow: hidden;
        }

        .top-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px; flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .toggle-btn {
            background: none; border: none; font-size: 1.5rem;
            color: var(--text-muted); display: flex; align-items: center;
            padding: 8px; border-radius: 8px; transition: 0.2s;
        }
        .toggle-btn:hover { color: var(--primary); background: var(--bg-body); }

        .header-right { display: flex; align-items: center; gap: 24px; }

        .profile-container { position: relative; }
        .profile-menu {
            display: flex; align-items: center; gap: 12px; cursor: pointer;
            padding: 8px 12px; border-radius: 12px; transition: 0.2s;
        }
        .profile-menu:hover { background: var(--bg-body); }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-name { font-size: 0.9rem; font-weight: 600; display: block; }
        .profile-role { font-size: 0.75rem; color: var(--text-muted); }
        .profile-img { width: 42px; height: 42px; border-radius: 12px; object-fit: cover; border: 2px solid var(--border); }
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
            animation: fadeIn 0.2s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .dropdown-menu.show { display: flex; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px; padding: 12px 16px;
            font-size: 0.9rem; color: var(--text-main); border-radius: 8px;
            transition: 0.2s;
        }
        .dropdown-item:hover { background: var(--bg-body); color: var(--primary); }
        .dropdown-item.danger:hover { background: rgba(239,68,68,0.1); color: #ef4444; }

        #themeToggle {
            background: var(--bg-card); border: 1px solid var(--border);
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            transition: 0.3s;
        }
        #themeToggle:hover { transform: rotate(15deg); border-color: var(--primary); color: var(--primary); }

        /* === SCROLLABLE CONTENT === */
        .scrollable {
            flex: 1; overflow-y: auto; padding: 32px;
            scroll-behavior: smooth;
        }

        /* === INVOICE WRAPPER & ACTION BUTTONS === */
        .invoice-wrapper {
            max-width: 1000px;
            margin: 0 auto;
        }

        .action-buttons {
            display: flex; gap: 12px; margin-bottom: 20px;
        }
        .btn {
            padding: 10px 20px; border-radius: 8px; font-weight: 600; font-size: 14px;
            border: none; cursor: pointer; display: inline-flex; align-items: center;
            gap: 8px; transition: 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-2px); }
        .btn-secondary { background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--bg-body); }

        /* === VERIFY‑STYLE RESULT BOX === */
        .verify-card {
            background: var(--bg-card);
            width: 100%;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 40px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .result-box {
            margin-top: 0; /* because we are inside verify-card */
            padding: 24px;
            border-radius: 12px;
            border: 1px solid var(--border);
            text-align: left;
            animation: fadeIn 0.3s ease-in-out;
        }
        .result-success {
            background: rgba(16, 185, 129, 0.1);
            border-color: #10B981;
        }
        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; background: #D1FAE5; color: #065F46;
            border-radius: 20px; font-size: 0.9rem; font-weight: 600;
            margin-bottom: 16px;
        }
        [data-theme="dark"] .status-badge {
            background: rgba(16, 185, 129, 0.2);
            color: #6EE7B7;
        }
        .detail-row {
            display: flex; justify-content: space-between;
            padding: 12px 0; border-bottom: 1px solid var(--border);
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--text-muted); font-size: 0.9rem; }
        .detail-value { font-weight: 600; font-size: 1rem; color: var(--text-main); }

        /* Amount summary cards (exactly like verify) */
        .amount-summary {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;
            margin: 20px 0;
        }
        .amount-card {
            background: var(--bg-body); padding: 16px; border-radius: 8px;
            text-align: center; border: 1px solid var(--border);
        }
        .amount-card-label { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 8px; }
        .amount-card-value { font-size: 1.4rem; font-weight: 700; }
        .amount-card.total .amount-card-value { color: var(--primary); }
        .amount-card.paid .amount-card-value { color: #10B981; }
        .amount-card.due .amount-card-value { color: #EF4444; }

        .payment-status-indicator {
            display: flex; align-items: center; gap: 8px; margin-top: 4px;
        }
        .payment-status-dot {
            width: 10px; height: 10px; border-radius: 50%;
        }
        .payment-status-dot.paid { background-color: #10B981; }
        .payment-status-dot.partial { background-color: #F59E0B; }
        .payment-status-dot.pending { background-color: #EF4444; }

        /* Details grid */
        .details-grid {
            display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;
            margin: 20px 0;
        }
        .detail-item {
            background: var(--bg-body); padding: 12px; border-radius: 8px;
            border: 1px solid var(--border);
        }
        .detail-item-label { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 4px; }
        .detail-item-value { font-size: 1rem; font-weight: 600; color: var(--text-main); }

        /* Payment summary block */
        .payment-summary-block {
            margin-top: 20px; padding: 16px; background: var(--bg-body);
            border-radius: 8px; border: 1px solid var(--border);
        }
        .progress-bar {
            height: 6px; background: var(--border); border-radius: 3px; overflow: hidden;
        }
        .progress-fill {
            height: 100%; transition: width 0.3s ease;
        }

        /* Items table (new addition, styled to match verify) */
        .items-table {
            width: 100%; border-collapse: collapse; margin: 20px 0;
        }
        .items-table th {
            text-align: left; padding: 12px 10px; border-bottom: 2px solid var(--border);
            font-size: 12px; text-transform: uppercase; color: var(--text-muted); font-weight: 600;
        }
        .items-table td {
            padding: 16px 10px; border-bottom: 1px solid var(--border);
            vertical-align: middle; font-size: 14px;
        }
        .prod-img {
            width: 50px; height: 50px; object-fit: cover; border-radius: 4px;
            border: 1px solid var(--border); margin-right: 15px;
        }
        .img-placeholder {
            width: 50px; height: 50px; background: var(--bg-body); border-radius: 4px;
            display: inline-flex; align-items: center; justify-content: center;
            color: var(--text-muted); font-size: 10px; border: 1px solid var(--border);
            margin-right: 15px;
        }
        .num { text-align: right; }
        .fw-bold { font-weight: 600; }

        /* Payment history (same as verify) */
        .payment-history-section {
            margin-top: 24px; padding-top: 20px; border-top: 2px solid var(--border);
        }
        .section-title {
            font-size: 1.1rem; font-weight: 600; margin-bottom: 16px;
            display: flex; align-items: center; gap: 8px; color: var(--text-main);
        }
        .payment-record {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px; background: var(--bg-body); border-radius: 8px;
            margin-bottom: 8px; border: 1px solid var(--border);
        }
        .payment-record:last-child { margin-bottom: 0; }
        .payment-info { display: flex; flex-direction: column; gap: 4px; }
        .payment-date { font-size: 0.85rem; color: var(--text-muted); }
        .payment-by { font-size: 0.9rem; color: var(--text-main); font-weight: 500; }
        .payment-amount { font-size: 1.1rem; font-weight: 700; color: #10B981; }
        .no-payments {
            text-align: center; padding: 20px; color: var(--text-muted); font-style: italic;
        }

        /* Overlay for mobile */
        .overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);
            z-index: 45; display: none; animation: fadeIn 0.3s;
        }

        /* Print styles */
        @media print {
            body { background: white; display: block; }
            .sidebar, .top-header, .action-buttons, .overlay { display: none !important; }
            .main-content { display: block; }
            .scrollable { padding: 0; overflow: visible; }
            .verify-card { box-shadow: none; padding: 20px; border: none; }
            .amount-summary, .payment-history-section { page-break-inside: avoid; }
        }

        /* Responsive */
        @media (max-width: 768px) {
            body.sidebar-collapsed { --sidebar-width: 280px; }
            .sidebar { position: fixed; left: -280px; height: 100%; top: 0; }
            body.mobile-open .sidebar { transform: translateX(280px); }
            body.mobile-open .overlay { display: block; }
            .amount-summary { grid-template-columns: 1fr; }
            .details-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body onload="<?= $autoPrint ? 'window.print()' : '' ?>">

    <div class="overlay" id="overlay"></div>

    <!-- Sidebar (from verify) -->
    <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Invoice #<?= e($invoice['reference_no']) ?></span>
                </div>
            </div>

            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?= e($_SESSION['username'] ?? 'Admin') ?></span>
                            <span class="profile-role"><?= ucfirst(e($_SESSION['role'] ?? 'admin')) ?></span>
                        </div>
                        <?php
                        // Fetch user avatar if available
                        $userPhoto = '';
                        if (isset($_SESSION['user_id']) && $pdo) {
                            try {
                                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $userData = $stmt->fetch();
                                $userPhoto = $userData['avatar'] ?? '';
                            } catch (Exception $e) {
                                // ignore
                            }
                        }
                        if (!empty($userPhoto)): ?>
                            <img src="<?= e($userPhoto) ?>" alt="Profile" class="profile-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="profile-placeholder" style="display: none;"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
                        <?php else: ?>
                            <div class="profile-placeholder"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile_settings.php" class="dropdown-item">
                            <i class="ph ph-user-gear"></i> Profile Settings
                        </a>
                        <div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>
                        <a href="logout.php" class="dropdown-item danger">
                            <i class="ph ph-sign-out"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="scrollable">
            <div class="invoice-wrapper">
                <div class="action-buttons">
                    <a href="sales_list.php" class="btn btn-secondary">
                        <i class="ph ph-arrow-left"></i> Back to Sales
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="ph ph-printer"></i> Print Invoice
                    </button>
                </div>

                <!-- Main invoice card styled exactly like verify_invoice.php's result box -->
                <div class="verify-card">
                    <div class="result-box result-success">
                        <!-- Status badge -->
                        <div style="text-align: center;">
                            <div class="status-badge">
                                <i class="ph ph-check-fat"></i> 
                                <?= $dueAmount <= 0 ? 'Fully Paid' : ($paidAmount > 0 ? 'Partially Paid' : 'Pending') ?>
                            </div>
                        </div>

                        <!-- Basic information rows -->
                        <div class="detail-row">
                            <span class="detail-label">Reference No</span>
                            <span class="detail-value"><?= e($invoice['reference_no']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Customer Name</span>
                            <span class="detail-value"><?= e($invoice['full_name']) ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Order Date</span>
                            <span class="detail-value"><?= date('d M, Y', strtotime($invoice['order_date'])) ?></span>
                        </div>

                        <!-- Items table (replaces the single product name row) -->
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Item Description</th>
                                    <th class="num">Unit Price</th>
                                    <th class="num">Qty</th>
                                    <th class="num">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): 
                                    $img = $item['image_url'] ?? '';
                                ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <?php if (!empty($img) && file_exists($img)): ?>
                                                <img src="<?= e($img) ?>" class="prod-img" alt="">
                                            <?php else: ?>
                                                <div class="img-placeholder">IMG</div>
                                            <?php endif; ?>
                                            <span class="fw-bold"><?= e($item['product_name']) ?></span>
                                        </div>
                                    </td>
                                    <td class="num"><?= formatCurrency($item['unit_cost']) ?></td>
                                    <td class="num"><?= (int)$item['quantity'] ?></td>
                                    <td class="num fw-bold"><?= formatCurrency($item['subtotal']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Amount summary cards (identical to verify) -->
                        <div class="amount-summary">
                            <div class="amount-card total">
                                <div class="amount-card-label">Total Amount</div>
                                <div class="amount-card-value"><?= formatCurrency($totalAmount) ?></div>
                            </div>
                            <div class="amount-card paid">
                                <div class="amount-card-label">Paid Amount</div>
                                <div class="amount-card-value"><?= formatCurrency($paidAmount) ?></div>
                                <?php if ($paidAmount > 0): ?>
                                    <div class="payment-status-indicator">
                                        <div class="payment-status-dot <?= $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'pending') ?>"></div>
                                        <span style="font-size: 0.85rem; color: var(--text-muted);">
                                            <?= $paidAmount >= $totalAmount ? 'Fully Paid' : ($paidAmount > 0 ? 'Partially Paid' : 'Not Paid') ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="amount-card due">
                                <div class="amount-card-label">Due Amount</div>
                                <div class="amount-card-value"><?= formatCurrency($dueAmount) ?></div>
                                <?php if ($dueAmount > 0): ?>
                                    <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                                        Payment Pending
                                    </div>
                                <?php else: ?>
                                    <div style="font-size: 0.85rem; color: #10B981; margin-top: 4px;">
                                        <i class="ph ph-check"></i> No Due
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Additional details grid -->
                        <div class="details-grid">
                            <div class="detail-item">
                                <div class="detail-item-label">Status</div>
                                <div class="detail-item-value">
                                    <?php 
                                    $status = $invoice['status'] ?? 'Unknown';
                                    $status_color = 'var(--text-main)';
                                    if ($status === 'Paid') $status_color = '#10B981';
                                    elseif ($status === 'Partial') $status_color = '#F59E0B';
                                    elseif ($status === 'Pending') $status_color = '#EF4444';
                                    ?>
                                    <span style="color: <?= $status_color ?>; font-weight: 700;"><?= e($status) ?></span>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Payment Status</div>
                                <div class="detail-item-value"><?= e($invoice['payment_status'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Customer ID</div>
                                <div class="detail-item-value"><?= e($invoice['customer_id'] ?? 'N/A') ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-item-label">Created At</div>
                                <div class="detail-item-value"><?= date('d M, Y - h:i A', strtotime($invoice['created_at'] ?? date('Y-m-d H:i:s'))) ?></div>
                            </div>
                        </div>

                        <!-- Payment summary block (progress bar etc.) -->
                        <div class="payment-summary-block">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div style="font-size: 1rem; font-weight: 600; color: var(--text-main);">
                                    <i class="ph ph-currency-dollar"></i> Payment Summary
                                </div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">
                                    From invoice record (paid_amount column)
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Total Invoice</div>
                                    <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary);"><?= formatCurrency($totalAmount) ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Paid Amount</div>
                                    <div style="font-size: 1.2rem; font-weight: 700; color: #10B981;"><?= formatCurrency($paidAmount) ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Due Amount</div>
                                    <div style="font-size: 1.2rem; font-weight: 700; color: #EF4444;"><?= formatCurrency($dueAmount) ?></div>
                                </div>
                                <div>
                                    <div style="font-size: 0.85rem; color: var(--text-muted);">Payment Progress</div>
                                    <div style="margin-top: 4px;">
                                        <?php 
                                        $progress = $totalAmount > 0 ? ($paidAmount / $totalAmount) * 100 : 0;
                                        ?>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?= min($progress, 100) ?>%; background: <?= $progress >= 100 ? '#10B981' : ($progress > 0 ? '#F59E0B' : '#EF4444') ?>;"></div>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                                            <?= number_format($progress, 1) ?>% paid
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Note if present -->
                        <?php if (!empty($invoice['note'])): ?>
                        <div style="margin-top: 20px; padding: 16px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border);">
                            <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 8px;">Note:</div>
                            <div style="color: var(--text-main);"><?= e($invoice['note']) ?></div>
                        </div>
                        <?php endif; ?>

                        <!-- Payment history section -->
                        <div class="payment-history-section">
                            <div class="section-title">
                                <i class="ph ph-clock-counter-clockwise"></i>
                                Payment History
                            </div>
                            
                            <?php if (count($payments) > 0): ?>
                                <?php foreach ($payments as $payment): ?>
                                    <div class="payment-record">
                                        <div class="payment-info">
                                            <div class="payment-date">
                                                <i class="ph ph-calendar-blank"></i>
                                                <?= date('M d, Y - h:i A', strtotime($payment['paid_at'])) ?>
                                            </div>
                                            <div class="payment-by">
                                                <i class="ph ph-user"></i>
                                                <?= e($payment['paid_by'] ?? 'N/A') ?>
                                            </div>
                                        </div>
                                        <div class="payment-amount">
                                            <?= formatCurrency($payment['amount']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-payments">
                                    <i class="ph ph-wallet" style="font-size: 2rem; display: block; margin-bottom: 8px; opacity: 0.5;"></i>
                                    No payment records found.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Link to view/print (optional, but we already have print button) -->
                        <div style="margin-top: 20px; text-align: center;">
                            <a href="invoice_view.php?id=<?= $id ?>&print=true" target="_blank" style="color: var(--primary); font-weight: 600; text-decoration: underline; font-size: 0.9rem;">
                                <i class="ph ph-printer"></i> Print this invoice
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Sidebar Toggle
        const sidebarToggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('overlay');
        const body = document.body;

        function handleSidebarToggle() {
            if (window.innerWidth <= 768) {
                body.classList.toggle('mobile-open');
                body.classList.remove('sidebar-collapsed');
            } else {
                body.classList.toggle('sidebar-collapsed');
                if (body.classList.contains('sidebar-collapsed')) {
                    document.querySelectorAll('.menu-item.open').forEach(i => i.classList.remove('open'));
                }
            }
        }
        sidebarToggle.addEventListener('click', handleSidebarToggle);
        overlay.addEventListener('click', () => {
            body.classList.remove('mobile-open');
            closeAllDropdowns();
        });

        // Submenu accordion
        document.querySelectorAll('.has-submenu > .menu-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const parent = link.closest('.menu-item');
                if (body.classList.contains('sidebar-collapsed')) {
                    body.classList.remove('sidebar-collapsed');
                    setTimeout(() => parent.classList.add('open'), 100);
                    return;
                }
                parent.classList.toggle('open');
            });
        });

        // Profile dropdown
        function toggleProfileMenu() {
            document.getElementById('profileDropdown').classList.toggle('show');
        }
        function closeAllDropdowns() {
            document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.remove('show'));
        }
        window.addEventListener('click', (e) => {
            if (!document.getElementById('profileContainer').contains(e.target)) {
                closeAllDropdowns();
            }
        });

        // Dark mode
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            themeIcon.classList.replace('ph-moon', 'ph-sun');
        }
        themeBtn.addEventListener('click', () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if (isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                themeIcon.classList.replace('ph-sun', 'ph-moon');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                themeIcon.classList.replace('ph-moon', 'ph-sun');
            }
        });

        // Restore sidebar state
        if (localStorage.getItem('sidebarCollapsed') === 'true' && window.innerWidth > 768) {
            body.classList.add('sidebar-collapsed');
        }
    </script>
</body>
</html>