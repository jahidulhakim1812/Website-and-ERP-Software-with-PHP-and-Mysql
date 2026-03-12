<?php
/**
 * get_work_order_details.php
 * AJAX endpoint to fetch work order details
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


// Database connection
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
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}

// Get work order ID
$work_order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($work_order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid work order ID']);
    exit;
}

try {
    // Fetch work order details
    $query = "
        SELECT 
            wo.*,
            s.company_name,
            s.contact_person,
            s.email,
            s.phone,
            s.specialization,
            (SELECT COUNT(*) FROM work_order_materials WHERE work_order_id = wo.id) as materials_count
        FROM subcontractor_work_orders wo
        LEFT JOIN subcontractors s ON wo.subcontractor_id = s.id
        WHERE wo.id = ?
    ";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$work_order_id]);
    $work_order = $stmt->fetch();
    
    if (!$work_order) {
        echo json_encode(['success' => false, 'message' => 'Work order not found']);
        exit;
    }
    
    // Format dates
    $work_order['start_date'] = $work_order['start_date'] ?: null;
    $work_order['end_date'] = $work_order['end_date'] ?: null;
    $work_order['created_at'] = date('Y-m-d H:i:s', strtotime($work_order['created_at']));
    $work_order['updated_at'] = date('Y-m-d H:i:s', strtotime($work_order['updated_at']));
    
    echo json_encode(['success' => true, ...$work_order]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error fetching work order details: ' . $e->getMessage()]);
}