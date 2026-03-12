<?php
/**
 * purchase_list.php
 * Displays the history of goods purchased from Suppliers (Procurement).
 * Features: Expandable rows to see items, Status badges (Received/Pending).
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


// --- 2. DATABASE ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) { die("DB Connection Failed: " . $ex->getMessage()); }

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 3. FETCH DATA ---
$purchases = [];
$total_spend = 0;
$pending_count = 0;

if ($pdo) {
    try {
        // A. Fetch All Purchases
        // Assumes table 'purchases' exists. If your table is 'procurements', change it here.
        $sql = "SELECT * FROM purchases ORDER BY purchase_date DESC";
        $stmt = $pdo->query($sql);
        $raw_purchases = $stmt->fetchAll();

        // B. Loop to get Items for each Purchase
        foreach ($raw_purchases as $po) {
            $total_spend += $po['total_cost'];
            if(strtolower($po['status']) == 'pending') { $pending_count++; }

            // Get Items for this PO
            // Assumes table 'purchase_items' links purchase_id to products
            $stmtItems = $pdo->prepare("
                SELECT pi.*, p.product_name, p.sku 
                FROM purchase_items pi
                LEFT JOIN products p ON pi.product_id = p.id
                WHERE pi.purchase_id = ?
            ");
            $stmtItems->execute([$po['id']]);
            $po['items'] = $stmtItems->fetchAll();

            $purchases[] = $po;
        }

    } catch (Exception $e) {
        // If tables don't exist yet, we handle gracefully
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase History | NexusAdmin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* --- Shared Styles --- */
        :root { --primary:#4F46E5; --bg-body:#F3F4F6; --bg-card:#fff; --text-main:#111827; --text-muted:#6B7280; --border:#E5E7EB; --success:#10B981; --warning:#F59E0B; --danger:#EF4444; }
        body { margin:0; font-family:'Inter', sans-serif; background:var(--bg-body); color:var(--text-main); display:flex; height:100vh; overflow:hidden; }
        a { text-decoration:none; color:inherit; }
        
        /* Layout */
        .sidebar { width:280px; background:#111827; color:#fff; flex-shrink:0; display:flex; flex-direction:column; }
        .main { flex:1; display:flex; flex-direction:column; overflow:hidden; }
        .top-header { height:64px; background:var(--bg-card); border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; padding:0 32px; flex-shrink:0; }
        .scrollable { flex:1; overflow-y:auto; padding:32px; }

        /* Sidebar Links */
        .menu-link { display:flex; padding:12px 16px; color:rgba(255,255,255,0.7); gap:12px; align-items:center; margin-bottom:4px; border-radius:8px; }
        .menu-link:hover, .menu-link.active { background:rgba(255,255,255,0.1); color:#fff; }

        /* Stats */
        .stats-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:24px; margin-bottom:32px; }
        .stat-card { background:var(--bg-card); padding:20px; border-radius:12px; border:1px solid var(--border); display:flex; gap:16px; align-items:center; }
        .stat-icon { width:48px; height:48px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.5rem; }

        /* Table */
        .card { background:var(--bg-card); border-radius:12px; border:1px solid var(--border); overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
        .card-header { padding:20px 24px; border-bottom:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; }
        .add-btn { background:var(--primary); color:white; padding:8px 16px; border-radius:6px; font-weight:500; font-size:0.9rem; display:flex; align-items:center; gap:8px; }
        
        .table { width:100%; border-collapse:collapse; }
        .table th { background:#F9FAFB; padding:12px 24px; text-align:left; font-size:0.8rem; text-transform:uppercase; color:var(--text-muted); font-weight:600; border-bottom:1px solid var(--border); }
        .table td { padding:16px 24px; border-bottom:1px solid var(--border); vertical-align:middle; font-size:0.95rem; }
        
        /* Status Badges */
        .badge { padding:4px 10px; border-radius:20px; font-size:0.75rem; font-weight:600; text-transform:capitalize; }
        .badge.received { background:#ECFDF5; color:#065F46; }
        .badge.pending { background:#FFFBEB; color:#B45309; }
        .badge.ordered { background:#EEF2FF; color:#4F46E5; }

        /* Expanded Row */
        .details-row { display:none; background:#F8FAFC; }
        .details-row.show { display:table-row; }
        .sub-table { width:100%; font-size:0.9rem; margin-top:8px; }
        .sub-table th { background:none; padding:8px 0; border-bottom:2px solid #E5E7EB; color:var(--text-muted); }
        .sub-table td { padding:8px 0; border-bottom:1px solid #E5E7EB; color:var(--text-muted); }

        .toggle-btn { cursor:pointer; background:none; border:none; color:var(--primary); font-weight:500; font-size:0.9rem; display:flex; align-items:center; gap:4px; }
    </style>
</head>
<body>

   <?php include 'sidenavbar.php'; ?>

    <main class="main">
        <header class="top-header">
            <h3 style="font-weight:600;">Procurement History</h3>
            <div style="display:flex; align-items:center; gap:12px;">
                <span style="font-size:0.9rem; color:var(--text-muted);">Admin View</span>
                <div style="width:32px; height:32px; background:var(--primary); border-radius:50%; color:white; display:flex; align-items:center; justify-content:center; font-weight:600;">A</div>
            </div>
        </header>

        <div class="scrollable">
            
            <?php if(isset($error)): ?>
                <div style="background:#FEF2F2; color:var(--danger); padding:16px; border-radius:8px; margin-bottom:24px; border:1px solid #FECACA;">
                    <strong>Database Error:</strong> <?php echo $error; ?><br>
                    <small>Ensure you have tables 'purchases' and 'purchase_items' created.</small>
                </div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:#EEF2FF; color:var(--primary);"><i class="ph ph-wallet"></i></div>
                    <div>
                        <div style="font-size:1.5rem; font-weight:700;">$<?php echo number_format($total_spend, 2); ?></div>
                        <div style="font-size:0.85rem; color:var(--text-muted);">Total Cost (All Time)</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background:#FFFBEB; color:var(--warning);"><i class="ph ph-clock"></i></div>
                    <div>
                        <div style="font-size:1.5rem; font-weight:700;"><?php echo $pending_count; ?></div>
                        <div style="font-size:0.85rem; color:var(--text-muted);">Pending Arrivals</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <span style="font-weight:600; font-size:1.1rem;">Supplier Orders</span>
                    <a href="add_purchase.php" class="add-btn"><i class="ph ph-plus"></i> New Purchase Order</a>
                </div>

                <div style="overflow-x:auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Reference / Invoice</th>
                                <th>Supplier</th>
                                <th>Status</th>
                                <th>Total Cost</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($purchases) > 0): ?>
                                <?php foreach($purchases as $row): ?>
                                    <?php 
                                        $statusClass = strtolower($row['status']); 
                                        if($statusClass != 'received' && $statusClass != 'pending') $statusClass = 'ordered'; 
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($row['purchase_date'])); ?></td>
                                        <td style="font-family:monospace; color:var(--text-main); font-weight:500;">
                                            #<?php echo e($row['reference_no'] ?? 'PO-'.$row['id']); ?>
                                        </td>
                                        <td><?php echo e($row['supplier_name']); ?></td>
                                        <td><span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                        <td style="font-weight:600;">$<?php echo number_format($row['total_cost'], 2); ?></td>
                                        <td style="text-align:right;">
                                            <button class="toggle-btn" style="float:right;" onclick="toggleDetails(<?php echo $row['id']; ?>)">
                                                <i class="ph ph-list-magnifying-glass"></i> Details
                                            </button>
                                        </td>
                                    </tr>

                                    <tr id="details-<?php echo $row['id']; ?>" class="details-row">
                                        <td colspan="6" style="padding:0 40px 24px 40px;">
                                            <div style="background:white; padding:16px; border:1px solid var(--border); border-radius:8px; margin-top:8px;">
                                                <h5 style="margin:0 0 8px 0; color:var(--primary); font-size:0.9rem;">
                                                    Items in Invoice #<?php echo e($row['reference_no'] ?? 'PO-'.$row['id']); ?>
                                                </h5>
                                                
                                                <table class="sub-table">
                                                    <thead>
                                                        <tr>
                                                            <th width="50%">Product</th>
                                                            <th width="15%">SKU</th>
                                                            <th width="15%">Quantity</th>
                                                            <th width="20%" style="text-align:right;">Unit Cost</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach($row['items'] as $item): ?>
                                                            <tr>
                                                                <td><?php echo e($item['product_name']); ?></td>
                                                                <td><span style="font-family:monospace; font-size:0.85rem;"><?php echo e($item['sku'] ?? '-'); ?></span></td>
                                                                <td style="color:var(--text-main); font-weight:500;"><?php echo $item['quantity']; ?></td>
                                                                <td style="text-align:right;">$<?php echo number_format($item['cost_price'] ?? 0, 2); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" style="text-align:center; padding:40px; color:var(--text-muted);">
                                        No purchase history found.
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
        function toggleDetails(id) {
            const row = document.getElementById('details-' + id);
            row.classList.toggle('show');
        }
    </script>
</body>
</html> 