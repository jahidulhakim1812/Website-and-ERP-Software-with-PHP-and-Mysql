<?php
session_start();

// --- 1. AUTHENTICATION & SECURITY ---
// Security headers to prevent caching
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page
    header("Location: login.php");
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
    
    // Redirect to login page
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Location: login.php");
    exit();
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
    die("Database connection failed: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
function formatCurrency($amount) { 
    $amount = floatval($amount);
    return '$' . number_format($amount, 2); 
}
function formatDate($date, $format = 'F j, Y') { 
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return 'N/A';
    return date($format, strtotime($date)); 
}

function numberToWords($num) {
    $num = floatval($num);
    if ($num == 0) return "Zero Dollars";
    
    $ones = array(
        0 => "Zero",1 => "One",2 => "Two",3 => "Three",4 => "Four",5 => "Five",6 => "Six",7 => "Seven",8 => "Eight",9 => "Nine",
        10 => "Ten",11 => "Eleven",12 => "Twelve",13 => "Thirteen",14 => "Fourteen",15 => "Fifteen",16 => "Sixteen",
        17 => "Seventeen",18 => "Eighteen",19 => "Nineteen"
    );
    $tens = array( 
        0 => "Zero",1 => "Ten",2 => "Twenty",3 => "Thirty",4 => "Forty",5 => "Fifty",6 => "Sixty",7 => "Seventy",8 => "Eighty",9 => "Ninety" 
    ); 
    
    if ($num == 0) return "Zero Dollars";
    
    $dollars = floor($num);
    $cents = round(($num - $dollars) * 100);
    
    // Convert dollars
    $words = "";
    if ($dollars < 20) {
        $words .= $ones[$dollars];
    } elseif ($dollars < 100) {
        $words .= $tens[floor($dollars / 10)];
        if ($dollars % 10 > 0) {
            $words .= " " . $ones[$dollars % 10];
        }
    } elseif ($dollars < 1000) {
        $hundreds = floor($dollars / 100);
        $words .= $ones[$hundreds] . " Hundred";
        $remainder = $dollars % 100;
        if ($remainder > 0) {
            $words .= " and " . numberToWords($remainder);
        }
    } else {
        $thousands = floor($dollars / 1000);
        $words .= numberToWords($thousands) . " Thousand";
        $remainder = $dollars % 1000;
        if ($remainder > 0) {
            $words .= " " . numberToWords($remainder);
        }
    }
    
    $words .= " Dollar" . ($dollars != 1 ? "s" : "");
    
    // Add cents if any
    if ($cents > 0) {
        $words .= " and ";
        if ($cents < 20) {
            $words .= $ones[$cents];
        } else {
            $words .= $tens[floor($cents / 10)];
            if ($cents % 10 > 0) {
                $words .= " " . $ones[$cents % 10];
            }
        }
        $words .= " Cent" . ($cents != 1 ? "s" : "");
    }
    
    return $words;
}

// --- 4. GET PAYMENT DATA ---
$payment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// If no payment ID is provided, redirect to payments list with error
if (!$payment_id) {
    $_SESSION['error_message'] = "No payment ID was provided. Please select a valid payment.";
    header("Location: work_order_payments.php");
    exit();
}

try {
    // First check if the payments table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'work_order_payments'")->rowCount();
    
    if (!$tableExists) {
        die("<div style='padding: 20px; text-align: center;'>
                <h2>Error: Database Table Missing</h2>
                <p>The payments table does not exist in the database.</p>
                <button onclick='window.location.href=\"work_order_payments.php\"' style='padding: 10px 20px; background: #4F46E5; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px;'>
                    ← Go to Payments
                </button>
             </div>");
    }
    
    // Get payment details with all related information
    $query = "SELECT 
                p.*,
                wo.work_order_number, 
                wo.project_name,
                wo.start_date,
                wo.end_date,
                wo.estimated_cost,
                wo.actual_cost,
                wo.total_paid,
                wo.payment_status,
                s.company_name as subcontractor_name,
                s.address as subcontractor_address,
                s.phone as subcontractor_phone,
                s.email as subcontractor_email,
                s.tax_id,
                u.username as recorded_by,
                u.email as recorded_by_email
              FROM work_order_payments p
              LEFT JOIN subcontractor_work_orders wo ON p.work_order_id = wo.id
              LEFT JOIN subcontractors s ON wo.subcontractor_id = s.id
              LEFT JOIN users u ON p.created_by = u.id
              WHERE p.id = ?";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    
    if (!$payment) {
        die("<div style='padding: 20px; text-align: center;'>
                <h2>Error: Payment Not Found</h2>
                <p>The requested payment record does not exist or has been deleted.</p>
                <button onclick='window.location.href=\"work_order_payments.php\"' style='padding: 10px 20px; background: #4F46E5; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px;'>
                    ← Go to Payments
                </button>
             </div>");
    }
    
    // Get company information
    $company_info = [
        'name' => 'Nexus Manufacturing Inc.',
        'address' => '123 Industrial Park, Suite 100',
        'city_state_zip' => 'San Francisco, CA 94107',
        'phone' => '(555) 123-4567',
        'email' => 'accounts@nexusmanufacturing.com',
        'website' => 'www.nexusmanufacturing.com',
        'tax_id' => 'TAX-789-456-123',
        'logo_url' => 'https://via.placeholder.com/150x80?text=Nexus+Manufacturing'
    ];
    
    // Calculate payment number
    $receipt_number = 'REC-' . str_pad($payment_id, 6, '0', STR_PAD_LEFT);
    
} catch (Exception $e) {
    die("<div style='padding: 20px; text-align: center;'>
            <h2>Error Loading Payment Data</h2>
            <p>" . e($e->getMessage()) . "</p>
            <button onclick='window.location.href=\"work_order_payments.php\"' style='padding: 10px 20px; background: #4F46E5; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px;'>
                ← Go to Payments
            </button>
         </div>");
}

// --- 5. SETUP FOR PRINTING ---
if (isset($_GET['print'])) {
    // Add headers to trigger print dialog
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo $receipt_number; ?></title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    
    <style>
        /* --- CSS Variables --- */
        :root {
            --primary: #1a56db;
            --secondary: #6b7280;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --light: #f9fafb;
            --dark: #111827;
            --border: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --radius: 8px;
        }
        
        /* --- Reset & Base --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        body {
            background: #f3f4f6;
            min-height: 100vh;
            padding: 40px 20px;
        }
        
        .receipt-container {
            width: 100%;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* --- Receipt Card --- */
        .receipt-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            position: relative;
        }
        
        /* --- Header --- */
        .receipt-header {
            background: linear-gradient(135deg, var(--primary) 0%, #1e3a8a 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .header-pattern {
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(30%, -30%);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            position: relative;
            z-index: 2;
        }
        
        .company-info h1 {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: -0.5px;
        }
        
        .company-info .tagline {
            font-size: 0.9rem;
            opacity: 0.9;
            font-weight: 400;
        }
        
        .receipt-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            border-radius: 50px;
            text-align: center;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .receipt-badge .title {
            font-size: 0.8rem;
            opacity: 0.9;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .receipt-badge .number {
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        /* --- Receipt Body --- */
        .receipt-body {
            padding: 40px;
        }
        
        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 2px dashed var(--border);
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
        
        .info-section h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--secondary);
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-section h3 i {
            color: var(--primary);
        }
        
        .info-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
        }
        
        .info-label {
            font-weight: 500;
            color: var(--secondary);
            font-size: 0.9rem;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
            text-align: right;
        }
        
        /* Payment Details Card */
        .payment-card {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: var(--radius);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .payment-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark);
        }
        
        .payment-status {
            padding: 6px 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-completed {
            background: rgba(16, 185, 129, 0.15);
            color: var(--success);
            border: 2px solid rgba(16, 185, 129, 0.3);
        }
        
        .amount-display {
            text-align: center;
            margin: 25px 0;
            padding: 25px;
            background: white;
            border-radius: var(--radius);
            border: 2px solid var(--border);
        }
        
        .amount-label {
            font-size: 1.1rem;
            color: var(--secondary);
            margin-bottom: 10px;
        }
        
        .amount-value {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            line-height: 1;
        }
        
        .amount-words {
            font-size: 1rem;
            color: var(--secondary);
            margin-top: 15px;
            font-style: italic;
            line-height: 1.4;
        }
        
        /* Details Table */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .details-table th {
            text-align: left;
            padding: 12px;
            background: var(--light);
            color: var(--secondary);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }
        
        .details-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            color: var(--dark);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .details-table tr:last-child td {
            border-bottom: none;
        }
        
        /* Notes Section */
        .notes-section {
            background: var(--light);
            border-radius: var(--radius);
            padding: 20px;
            margin-top: 30px;
            border-left: 4px solid var(--primary);
        }
        
        .notes-section h4 {
            font-size: 0.9rem;
            color: var(--primary);
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .notes-content {
            color: var(--secondary);
            line-height: 1.6;
            font-size: 0.9rem;
        }
        
        /* --- Receipt Footer --- */
        .receipt-footer {
            padding: 30px 40px;
            background: var(--light);
            border-top: 2px solid var(--border);
        }
        
        .signatures {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .signatures {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
        
        .signature-box {
            text-align: center;
            padding: 15px;
        }
        
        .signature-line {
            width: 100%;
            height: 2px;
            background: var(--border);
            margin: 30px 0 10px;
        }
        
        .signature-label {
            font-size: 0.85rem;
            color: var(--secondary);
            margin-top: 10px;
        }
        
        .signature-name {
            font-weight: 600;
            color: var(--dark);
            margin-top: 5px;
            font-size: 0.9rem;
        }
        
        .footer-notes {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            color: var(--secondary);
            font-size: 0.85rem;
            line-height: 1.6;
        }
        
        /* --- Action Buttons --- */
        .action-buttons {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            gap: 15px;
            z-index: 100;
        }
        
        @media (max-width: 768px) {
            .action-buttons {
                position: static;
                display: flex;
                justify-content: center;
                margin-top: 30px;
                gap: 10px;
            }
        }
        
        .action-btn {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-print {
            background: var(--primary);
            color: white;
        }
        
        .btn-download {
            background: var(--success);
            color: white;
        }
        
        .btn-back {
            background: var(--secondary);
            color: white;
        }
        
        /* --- Print Styles --- */
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .receipt-container {
                max-width: 100%;
                margin: 0;
            }
            
            .receipt-card {
                box-shadow: none;
                border-radius: 0;
            }
            
            .action-buttons {
                display: none;
            }
            
            .no-print {
                display: none;
            }
            
            @page {
                margin: 20mm;
                size: A4 portrait;
            }
        }
        
        /* --- Watermark --- */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            font-weight: 800;
            color: rgba(0, 0, 0, 0.05);
            pointer-events: none;
            white-space: nowrap;
            z-index: 1;
            display: none;
        }
        
        /* --- Notification --- */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            box-shadow: var(--shadow);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideInRight 0.3s ease;
            backdrop-filter: blur(10px);
            max-width: 350px;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .notification.success {
            background: rgba(16, 185, 129, 0.9);
            color: white;
            border-left: 4px solid #059669;
        }
        
        .notification.error {
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border-left: 4px solid #DC2626;
        }
        
        .notification.info {
            background: rgba(59, 130, 246, 0.9);
            color: white;
            border-left: 4px solid #1D4ED8;
        }
    </style>
</head>
<body>
    <div class="receipt-container">
        <div class="receipt-card" id="receiptContent">
            <!-- Watermark -->
            <div class="watermark" id="watermark">PAID</div>
            
            <!-- Header -->
            <div class="receipt-header">
                <div class="header-pattern"></div>
                <div class="header-content">
                    <div class="company-info">
                        <h1><?php echo e($company_info['name']); ?></h1>
                        <p class="tagline">Professional Manufacturing Services</p>
                        <div style="margin-top: 10px; font-size: 0.85rem; opacity: 0.9;">
                            <div><?php echo e($company_info['address']); ?></div>
                            <div><?php echo e($company_info['city_state_zip']); ?></div>
                            <div><?php echo e($company_info['phone']); ?> | <?php echo e($company_info['email']); ?></div>
                        </div>
                    </div>
                    <div class="receipt-badge">
                        <div class="title">Payment Receipt</div>
                        <div class="number" id="receiptNumber"><?php echo $receipt_number; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Body -->
            <div class="receipt-body">
                <!-- Information Grid -->
                <div class="info-grid">
                    <div class="info-section">
                        <h3><i class="fas fa-building"></i> Payment From</h3>
                        <div class="info-details">
                            <div class="info-row">
                                <span class="info-label">Company</span>
                                <span class="info-value"><?php echo e($company_info['name']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tax ID</span>
                                <span class="info-value"><?php echo e($company_info['tax_id']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Date Issued</span>
                                <span class="info-value"><?php echo formatDate(date('Y-m-d')); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="info-section">
                        <h3><i class="fas fa-user-tie"></i> Payment To</h3>
                        <div class="info-details">
                            <div class="info-row">
                                <span class="info-label">Subcontractor</span>
                                <span class="info-value"><?php echo e($payment['subcontractor_name'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Tax ID</span>
                                <span class="info-value"><?php echo e($payment['tax_id'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Contact</span>
                                <span class="info-value"><?php echo e($payment['subcontractor_phone'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Payment Details Card -->
                <div class="payment-card">
                    <div class="payment-header">
                        <div class="payment-title">Payment Details</div>
                        <div class="payment-status status-completed">
                            Completed
                        </div>
                    </div>
                    
                    <!-- Amount Display -->
                    <div class="amount-display">
                        <div class="amount-label">Amount Paid</div>
                        <div class="amount-value"><?php echo formatCurrency($payment['amount']); ?></div>
                        <div class="amount-words">
                            <strong>In Words:</strong> <?php echo numberToWords($payment['amount']); ?>
                        </div>
                    </div>
                    
                    <!-- Details Table -->
                    <table class="details-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th>Reference</th>
                                <th>Work Order</th>
                                <th>Payment Date</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Payment for Work Order <?php echo e($payment['work_order_number'] ?? 'N/A'); ?></td>
                                <td><?php echo e($payment['reference_number'] ?: 'N/A'); ?></td>
                                <td><?php echo e($payment['work_order_number'] ?? 'N/A'); ?></td>
                                <td><?php echo formatDate($payment['payment_date']); ?></td>
                                <td><?php echo e($payment['payment_method'] ?? 'N/A'); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <!-- Project Information -->
                    <div style="margin-top: 20px; padding: 15px; background: white; border-radius: var(--radius); border: 1px solid var(--border);">
                        <h4 style="color: var(--primary); margin-bottom: 10px; font-weight: 600;">
                            <i class="fas fa-project-diagram"></i> Project Information
                        </h4>
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                            <div>
                                <div style="font-size: 0.85rem; color: var(--secondary);">Project Name</div>
                                <div style="font-weight: 600; margin-top: 5px; font-size: 0.9rem;"><?php echo e($payment['project_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: var(--secondary);">Work Order</div>
                                <div style="font-weight: 600; margin-top: 5px; font-size: 0.9rem;"><?php echo e($payment['work_order_number'] ?? 'N/A'); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Notes -->
                <?php if(!empty($payment['notes'])): ?>
                    <div class="notes-section">
                        <h4><i class="fas fa-sticky-note"></i> Payment Notes</h4>
                        <div class="notes-content"><?php echo nl2br(e($payment['notes'])); ?></div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Footer -->
            <div class="receipt-footer">
                <div style="text-align: center; margin-bottom: 20px;">
                    <h3 style="color: var(--primary); font-size: 1.1rem; margin-bottom: 15px;">
                        <i class="fas fa-file-signature"></i> Authorized Signatures
                    </h3>
                </div>
                
                <div class="signatures">
                    <div class="signature-box">
                        <div style="font-weight: 600; margin-bottom: 10px; font-size: 0.9rem;">Prepared By</div>
                        <div class="signature-line"></div>
                        <div class="signature-name"><?php echo e($payment['recorded_by'] ?? 'Accounts Dept'); ?></div>
                        <div class="signature-label">Accounts Department</div>
                    </div>
                    
                    <div class="signature-box">
                        <div style="font-weight: 600; margin-bottom: 10px; font-size: 0.9rem;">Received By</div>
                        <div class="signature-line"></div>
                        <div class="signature-name"><?php echo e($payment['subcontractor_name'] ?? 'Subcontractor'); ?></div>
                        <div class="signature-label">Authorized Representative</div>
                    </div>
                    
                    <div class="signature-box">
                        <div style="font-weight: 600; margin-bottom: 10px; font-size: 0.9rem;">Approved By</div>
                        <div class="signature-line"></div>
                        <div class="signature-name">Finance Manager</div>
                        <div class="signature-label">Nexus Manufacturing Inc.</div>
                    </div>
                </div>
                
                <div class="footer-notes">
                    <p>
                        <strong>Important:</strong> This document serves as official receipt of payment. 
                        Please retain this receipt for your records.
                    </p>
                    <p style="margin-top: 10px; font-size: 0.8rem;">
                        Generated electronically on <?php echo date('F j, Y \a\t g:i A'); ?> | 
                        Transaction ID: PAY-<?php echo str_pad($payment_id, 8, '0', STR_PAD_LEFT); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons no-print">
        <button class="action-btn btn-back" onclick="goBack()">
            <i class="fas fa-arrow-left"></i> Back to Payments
        </button>
        <button class="action-btn btn-print" onclick="printReceipt()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <button class="action-btn btn-download" onclick="downloadAsPDF()">
            <i class="fas fa-download"></i> Save as PDF
        </button>
    </div>

    <script>
        // --- 1. Back Function ---
        function goBack() {
            // Always go to payments page instead of using history
            window.location.href = 'work_order_payments.php';
        }
        
        // --- 2. Print Function ---
        function printReceipt() {
            try {
                // Show watermark
                document.getElementById('watermark').style.display = 'block';
                
                // Trigger print
                window.print();
                
                // Hide watermark after print
                setTimeout(() => {
                    document.getElementById('watermark').style.display = 'none';
                }, 1000);
                
                showNotification('Print dialog opened', 'info');
            } catch (error) {
                showNotification('Error opening print dialog: ' + error.message, 'error');
            }
        }
        
        // --- 3. PDF Download ---
        async function downloadAsPDF() {
            try {
                // Show watermark for PDF
                document.getElementById('watermark').style.display = 'block';
                
                // Wait for fonts to load
                await document.fonts.ready;
                
                // Import jsPDF
                const { jsPDF } = window.jspdf;
                
                // Capture receipt content
                const receipt = document.getElementById('receiptContent');
                
                // Create canvas
                const canvas = await html2canvas(receipt, {
                    scale: 2,
                    useCORS: true,
                    logging: false,
                    backgroundColor: '#ffffff'
                });
                
                // Create PDF
                const pdf = new jsPDF({
                    orientation: 'portrait',
                    unit: 'mm',
                    format: 'a4'
                });
                
                // Calculate dimensions
                const imgWidth = 210; // A4 width in mm
                const pageHeight = 297; // A4 height in mm
                const imgHeight = (canvas.height * imgWidth) / canvas.width;
                
                // Add image to PDF
                pdf.addImage(canvas, 'PNG', 0, 0, imgWidth, imgHeight);
                
                // Generate filename
                const fileName = `Payment_Receipt_<?php echo $receipt_number; ?>_<?php echo date('Ymd_His'); ?>.pdf`;
                
                // Save PDF
                pdf.save(fileName);
                
                showNotification('PDF downloaded successfully!', 'success');
                
            } catch (error) {
                console.error('PDF generation error:', error);
                showNotification('Error generating PDF: ' + error.message, 'error');
            } finally {
                // Hide watermark
                document.getElementById('watermark').style.display = 'none';
            }
        }
        
        // --- 4. Utility Functions ---
        function showNotification(message, type = 'info') {
            // Remove existing notification
            const existing = document.querySelector('.notification');
            if (existing) existing.remove();
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.innerHTML = `
                <i class="fas ${getNotificationIcon(type)}"></i>
                <span>${message}</span>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateX(100%)';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }
        
        function getNotificationIcon(type) {
            switch(type) {
                case 'success': return 'fa-check-circle';
                case 'error': return 'fa-exclamation-circle';
                case 'warning': return 'fa-exclamation-triangle';
                default: return 'fa-info-circle';
            }
        }
        
        // --- 5. Keyboard Shortcuts ---
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + P to print
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                printReceipt();
            }
            
            // Ctrl/Cmd + S to save PDF
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                downloadAsPDF();
            }
            
            // Escape to go back
            if (e.key === 'Escape') {
                goBack();
            }
        });
        
        // --- 6. Initialize on Load ---
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-print if requested
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('print') === '1') {
                setTimeout(() => {
                    printReceipt();
                }, 1000);
            }
            
            // Prevent print dialog blocking
            window.addEventListener('beforeprint', function() {
                document.getElementById('watermark').style.display = 'block';
            });
            
            window.addEventListener('afterprint', function() {
                setTimeout(() => {
                    document.getElementById('watermark').style.display = 'none';
                }, 100);
            });
        });
    </script>
</body>
</html>