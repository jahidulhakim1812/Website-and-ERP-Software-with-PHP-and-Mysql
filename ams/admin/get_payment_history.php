<?php
/**
 * get_payment_history.php
 * AJAX endpoint to fetch payment history for a purchase order
 */

session_start();

// Security check
if (!isset($_SESSION['user_id']) || !isset($_GET['purchase_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

$purchase_id = filter_input(INPUT_GET, 'purchase_id', FILTER_VALIDATE_INT);

if (!$purchase_id) {
    echo json_encode([]);
    exit;
}

try {
    // Database connection
    $DB_HOST = 'localhost';
    $DB_NAME = 'alihairw_alisoft';
    $DB_USER = 'alihairw_ali';
    $DB_PASS = 'x5.H(8xkh3H7EY';
    
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Fetch payment history with detailed date formatting
    $sql = "SELECT 
                ph.amount,
                ph.paid_by,
                ph.paid_at,
                DATE(ph.paid_at) as payment_date,
                DATE_FORMAT(ph.paid_at, '%W, %M %d, %Y') as formatted_date,
                DATE_FORMAT(ph.paid_at, '%h:%i %p') as formatted_time,
                CASE 
                    WHEN HOUR(ph.paid_at) BETWEEN 0 AND 11 THEN 'Morning'
                    WHEN HOUR(ph.paid_at) BETWEEN 12 AND 17 THEN 'Afternoon'
                    ELSE 'Evening'
                END as time_period,
                DAYNAME(ph.paid_at) as day_name,
                MONTHNAME(ph.paid_at) as month_name,
                DAY(ph.paid_at) as day_number,
                YEAR(ph.paid_at) as year_number
            FROM payment_history ph
            WHERE ph.purchase_id = ?
            ORDER BY ph.paid_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$purchase_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Also get order info for context
    $orderSql = "SELECT 
                    reference_no, 
                    total_amount, 
                    paid_amount,
                    payment_status,
                    DATE_FORMAT(order_date, '%Y-%m-%d') as order_date
                 FROM purchase_orders 
                 WHERE id = ?";
    $orderStmt = $pdo->prepare($orderSql);
    $orderStmt->execute([$purchase_id]);
    $orderInfo = $orderStmt->fetch(PDO::FETCH_ASSOC);
    
    // Calculate payment statistics
    $total_payments = count($payments);
    $total_amount = 0;
    foreach ($payments as $payment) {
        $total_amount += floatval($payment['amount']);
    }
    
    // Prepare response data
    $response = [
        'order_info' => $orderInfo,
        'payments' => $payments,
        'total_payments' => $total_payments,
        'total_amount' => $total_amount,
        'statistics' => [
            'first_payment' => $payments[0]['paid_at'] ?? null,
            'last_payment' => $payments[count($payments)-1]['paid_at'] ?? null,
            'average_payment' => $total_payments > 0 ? $total_amount / $total_payments : 0
        ]
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}