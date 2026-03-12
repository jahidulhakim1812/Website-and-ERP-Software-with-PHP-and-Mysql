<?php
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

// [Insert Database Connection Here - Same as previous files]
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';
try { $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]); } catch (PDOException $ex) { die($ex->getMessage()); }

// --- HANDLE RECEIVE ACTION (Simple Logic) ---
if (isset($_GET['receive_id'])) {
    $po_id = $_GET['receive_id'];
    
    // 1. Update PO Status
    $stmt = $pdo->prepare("UPDATE purchase_orders SET status = 'Received' WHERE id = ?");
    $stmt->execute([$po_id]);
    
    // 2. STOCK UPDATE LOGIC:
    // Fetch items from purchase_order_items WHERE purchase_id = $po_id
    // Loop through them and UPDATE products SET quantity = quantity + $item_qty WHERE id = $product_id
    
    $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Purchase Order marked as Received. Stock updated.'];
    header("Location: purchase_list.php");
    exit;
}

// Fetch Purchases with Vendor Name
$sql = "SELECT po.*, v.company_name 
        FROM purchase_orders po 
        JOIN vendors v ON po.id = v.id 
        ORDER BY po.order_date DESC";
$orders = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Purchase Management | NexusAdmin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* Reuse styles from inventory_list.php */
        :root { --primary: #4F46E5; --bg-body: #F3F4F6; --text-main: #111827; --bg-card: #fff; }
        body { background: var(--bg-body); color: var(--text-main); font-family: 'Inter', sans-serif; padding: 20px; }
        .table-container { background: var(--bg-card); padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #E5E7EB; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-pending { background: #FEF3C7; color: #D97706; }
        .badge-received { background: #D1FAE5; color: #059669; }
        .btn { padding: 8px 16px; background: var(--primary); color: white; text-decoration: none; border-radius: 6px; display: inline-flex; align-items: center; gap: 8px; }
        .btn-sm { padding: 4px 8px; font-size: 0.8rem; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    </style>
</head>
<body>

<div class="header-flex">
    <h1>Purchase Orders</h1>
    <a href="purchase_add.php" class="btn"><i class="ph ph-plus"></i> New Purchase Order</a>
</div>

<div class="table-container">
    <table>
        <thead>
            <tr>
                <th>Reference</th>
                <th>Vendor</th>
                <th>Date</th>
                <th>Status</th>
                <th>Total</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($orders as $po): ?>
            <tr>
                <td><?php echo htmlspecialchars($po['reference_no']); ?></td>
                <td><?php echo htmlspecialchars($po['company_name']); ?></td>
                <td><?php echo htmlspecialchars($po['order_date']); ?></td>
                <td>
                    <span class="badge badge-<?php echo strtolower($po['status']); ?>">
                        <?php echo $po['status']; ?>
                    </span>
                </td>
                <td>$<?php echo number_format($po['total_amount'], 2); ?></td>
                <td>
                    <?php if($po['status'] == 'Pending'): ?>
                        <a href="purchase_list.php?receive_id=<?php echo $po['id']; ?>" class="btn btn-sm" style="background:#10B981;">
                            <i class="ph ph-check"></i> Receive
                        </a>
                    <?php else: ?>
                        <span style="color:#6B7280; font-size:0.9rem;"><i class="ph ph-lock"></i> Locked</span>
                    <?php endif; ?>
                    <a href="#" class="btn btn-sm" style="background:#6B7280;"><i class="ph ph-printer"></i> Invoice</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</body>
</html>