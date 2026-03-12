<?php
/**
 * mirage_payment_invoice.php
 * MIRAGE LOD‑3 – Professional Payment Invoice.
 * Displays company details, subcontractor info, batch details,
 * payment breakdown (cash + wig pieces), and remaining inventory.
 * 
 * CONFIGURATION: Set your inventory table column names below.
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
    die("Database connection failed: " . $ex->getMessage());
}

// ========== CONFIGURATION ==========
// Set the actual column names from your inventory table
$inventory_type_column = 'type';          // e.g., 'item_type', 'product_type'
$inventory_size_column = 'size';          // e.g., 'size_name', 'size'
$inventory_unit_column = 'unit';          // e.g., 'unit_of_measure'
$inventory_quantity_column = 'quantity';  // e.g., 'stock_qty', 'qty'
// ====================================

// --- Get payment ID from URL ---
$payment_id = isset($_GET['payment_id']) ? (int)$_GET['payment_id'] : 0;
if ($payment_id <= 0) {
    header("Location: mirage_due_simple.php");
    exit;
}

// Fetch payment details with batch and subcontractor
$stmt = $pdo->prepare("
    SELECT 
        p.id AS payment_id,
        p.amount AS total_paid,
        p.payment_date,
        b.id AS batch_id,
        b.lod_name AS batch_name,
        b.production_date,
        s.company_name AS subcontractor_name,
        s.id AS subcontractor_id,
        COALESCE((SELECT SUM(amount) FROM batch_payments WHERE batch_id = b.id), 0) AS total_paid_batch,
        COALESCE((SELECT SUM(quantity * unit_price) FROM wigs_batch_costs WHERE batch_id = b.id), 0) AS total_cost
    FROM batch_payments p
    JOIN wigs_batches b ON p.batch_id = b.id
    LEFT JOIN subcontractors s ON b.subcontractor_id = s.id
    WHERE p.id = ?
");
$stmt->execute([$payment_id]);
$payment = $stmt->fetch();

if (!$payment) {
    die("Payment not found.");
}
$payment['due'] = $payment['total_cost'] - $payment['total_paid_batch'];

// --- Check inventory table columns (to avoid SQL errors) ---
$inventory_columns = [];
try {
    $stmt = $pdo->query("DESCRIBE inventory");
    $inventory_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // inventory table might not exist – ignore
}

// Build inventory column aliases for the query (use actual column names if they exist)
$type_col = in_array($inventory_type_column, $inventory_columns) ? "i.`$inventory_type_column` AS inv_type" : "NULL AS inv_type";
$size_col = in_array($inventory_size_column, $inventory_columns) ? "i.`$inventory_size_column` AS inv_size" : "NULL AS inv_size";
$unit_col = in_array($inventory_unit_column, $inventory_columns) ? "i.`$inventory_unit_column` AS inv_unit" : "NULL AS inv_unit";
$qty_col = in_array($inventory_quantity_column, $inventory_columns) ? "i.`$inventory_quantity_column` AS remaining_stock" : "0 AS remaining_stock";

// Fetch wig pieces given in this payment
$sql = "
    SELECT 
        pwp.quantity AS given_qty,
        pwp.value,
        $type_col,
        $size_col,
        $unit_col,
        $qty_col
    FROM payment_wig_pieces pwp
    JOIN inventory i ON pwp.inventory_id = i.id
    WHERE pwp.payment_id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$payment_id]);
$wig_pieces = $stmt->fetchAll();

// Fetch company details
$stmt = $pdo->query("SELECT * FROM company_settings LIMIT 1");
$company = $stmt->fetch() ?: [
    'company_name' => 'MIRAGE Wigs',
    'address' => '123 Fashion Street, Dhaka',
    'phone' => '+880 1234 567890',
    'email' => 'info@mirage.com',
    'tax_id' => '',
    'logo' => ''
];

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
    <title>Payment Invoice | MIRAGE</title>
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
        body { background-color: var(--bg-body); color: var(--text-main); min-height: 100vh; display: flex; flex-direction: column; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button { font-family: inherit; cursor: pointer; }

        /* Invoice specific styles */
        .invoice-container {
            max-width: 1000px;
            margin: 40px auto;
            background: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 40px;
            border: 1px solid var(--border);
        }
        .invoice-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--primary);
        }
        .company-info h2 {
            color: var(--primary);
            margin-bottom: 5px;
        }
        .company-info p {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin: 2px 0;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            color: var(--primary);
            font-size: 2rem;
        }
        .invoice-title p {
            color: var(--text-muted);
        }
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .bill-to, .invoice-meta {
            background: var(--bg-body);
            padding: 15px 20px;
            border-radius: 8px;
            width: 48%;
        }
        .bill-to h3, .invoice-meta h3 {
            margin-bottom: 10px;
            color: var(--primary);
            font-size: 1.1rem;
        }
        .bill-to p, .invoice-meta p {
            margin: 5px 0;
            color: var(--text-main);
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        .invoice-table th {
            background: var(--sidebar-bg);
            color: white;
            padding: 12px;
            text-align: center;
        }
        .invoice-table td {
            padding: 10px;
            border: 1px solid var(--border);
            text-align: center;
        }
        .invoice-table td:first-child {
            text-align: left;
        }
        .totals {
            text-align: right;
            margin-top: 20px;
            font-size: 1.1rem;
        }
        .totals p {
            margin: 5px 0;
        }
        .totals .grand-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        .print-btn {
            text-align: center;
            margin-top: 30px;
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

        @media print {
            body { background: white; }
            .invoice-container { box-shadow: none; border: none; padding: 20px; }
            .print-btn, .btn { display: none; }
        }

        /* Sidebar and header for consistency (optional) */
        .top-header {
            height: 64px;
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
        }
        .profile-container { position: relative; }
        .profile-menu {
            display: flex; align-items: center; gap: 12px; cursor: pointer;
            padding: 8px 12px; border-radius: 12px;
        }
        .profile-menu:hover { background-color: var(--bg-body); }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-name { font-size: 0.9rem; font-weight: 600; display: block; }
        .profile-role { font-size: 0.75rem; color: var(--text-muted); }
        .profile-img { width: 42px; height: 42px; border-radius: 12px; object-fit: cover; border: 2px solid var(--border); }
        .profile-placeholder { width: 42px; height: 42px; border-radius: 12px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .dropdown-menu { position: absolute; top: calc(100% + 8px); right: 0; width: 220px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); padding: 8px; z-index: 1000; display: none; flex-direction: column; gap: 4px; }
        .dropdown-menu.show { display: flex; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; font-size: 0.9rem; color: var(--text-main); border-radius: 8px; transition: all 0.2s ease; }
        .dropdown-item:hover { background-color: var(--bg-body); color: var(--primary); }
        .dropdown-item.danger:hover { background-color: rgba(239,68,68,0.1); color: #ef4444; }
        #themeToggle { background: var(--bg-card); border: 1px solid var(--border); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body>
    <!-- Optional header with profile -->
    <header class="top-header">
        <div style="display: flex; align-items: center; gap: 16px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                <span style="font-size: 0.9rem; color: var(--text-muted);">Payment Invoice</span>
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

    <div class="invoice-container">
        <div class="invoice-header">
            <div class="company-info">
                <h2><?= e($company['company_name']) ?></h2>
                <p><?= nl2br(e($company['address'])) ?></p>
                <p>Phone: <?= e($company['phone']) ?></p>
                <p>Email: <?= e($company['email']) ?></p>
                <?php if (!empty($company['tax_id'])): ?>
                    <p>Tax ID: <?= e($company['tax_id']) ?></p>
                <?php endif; ?>
            </div>
            <div class="invoice-title">
                <h1>INVOICE</h1>
                <p>#PAY-<?= str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT) ?></p>
                <p>Date: <?= e($payment['payment_date']) ?></p>
            </div>
        </div>

        <div class="invoice-details">
            <div class="bill-to">
                <h3>Bill To:</h3>
                <p><strong><?= e($payment['subcontractor_name']) ?></strong></p>
                <p>Subcontractor ID: <?= e($payment['subcontractor_id']) ?></p>
                <!-- Add more subcontractor details if available -->
            </div>
            <div class="invoice-meta">
                <h3>Batch Details:</h3>
                <p><strong>Batch:</strong> <?= e($payment['batch_name']) ?> (ID: <?= $payment['batch_id'] ?>)</p>
                <p><strong>Production Date:</strong> <?= e($payment['production_date']) ?></p>
                <p><strong>Total Cost:</strong> <?= number_format($payment['total_cost'], 2) ?> BDT</p>
                <p><strong>Paid to Date:</strong> <?= number_format($payment['total_paid_batch'], 2) ?> BDT</p>
                <p><strong>Due After This Payment:</strong> <?= number_format($payment['due'], 2) ?> BDT</p>
            </div>
        </div>

        <?php if (!empty($wig_pieces)): ?>
        <h3>Wig Pieces Given</h3>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Size</th>
                    <th>Quantity Given</th>
                    <th>Unit</th>
                    <th>Value (BDT)</th>
                    <th>Remaining Stock</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($wig_pieces as $wp): ?>
                <tr>
                    <td><?= e($wp['inv_type'] ?? 'N/A') ?></td>
                    <td><?= e($wp['inv_size'] ?? 'N/A') ?></td>
                    <td><?= number_format($wp['given_qty'], 2) ?></td>
                    <td><?= e($wp['inv_unit'] ?? 'pcs') ?></td>
                    <td><?= number_format($wp['value'], 2) ?></td>
                    <td><?= number_format($wp['remaining_stock'] ?? 0, 2) ?> <?= e($wp['inv_unit'] ?? 'pcs') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <div class="totals">
            <p>Cash Amount: <?= number_format($payment['total_paid'] - array_sum(array_column($wig_pieces, 'value')), 2) ?> BDT</p>
            <?php if (!empty($wig_pieces)): ?>
                <p>Wig Pieces Value: <?= number_format(array_sum(array_column($wig_pieces, 'value')), 2) ?> BDT</p>
            <?php endif; ?>
            <p class="grand-total">Total Paid: <?= number_format($payment['total_paid'], 2) ?> BDT</p>
        </div>

        <div class="print-btn">
            <button class="btn" onclick="window.print()"><i class="ph ph-printer"></i> Print Invoice</button>
            <a href="mirage_due_simple.php<?= $payment['subcontractor_id'] ? '?subcontractor_id='.$payment['subcontractor_id'] : '' ?>" class="btn btn-secondary">Back to Due List</a>
        </div>
    </div>

    <!-- JavaScript for dark mode and dropdown (optional) -->
    <script>
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

        // --- Profile Dropdown (optional) ---
        function toggleProfileMenu() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        window.addEventListener('click', function(e) {
            if (!document.getElementById('profileContainer').contains(e.target)) {
                document.getElementById('profileDropdown').classList.remove('show');
            }
        });
    </script>
</body>
</html>