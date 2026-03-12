<?php
/**
 * export_salary.php (Enhanced Version)
 * Export Salary Payments to CSV, Excel, or PDF
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
    die("DB Connection Failed: " . $ex->getMessage());
}

// --- 3. GET EXPORT PARAMETERS ---
$filter = $_GET['range'] ?? 'month';
$customStart = $_GET['start_date'] ?? '';
$customEnd = $_GET['end_date'] ?? '';
$format = $_GET['format'] ?? 'csv';
$includeSummary = isset($_GET['summary']) ? (bool)$_GET['summary'] : true;
$groupBy = $_GET['group'] ?? 'none';

// --- 4. DATE FILTER HANDLING ---
$startDate = new DateTime();
$endDate = new DateTime();

switch ($filter) {
    case 'today':
        $startDate->modify('today');
        $endDate = new DateTime('today');
        $displayRange = 'Today';
        break;
    case 'week':
        $startDate->modify('-7 days');
        $endDate = new DateTime();
        $displayRange = 'Last 7 Days';
        break;
    case 'month':
        $startDate->modify('first day of this month');
        $endDate->modify('last day of this month');
        $displayRange = 'This Month';
        break;
    case 'year':
        $startDate->modify('first day of January this year');
        $endDate->modify('last day of December this year');
        $displayRange = 'This Year';
        break;
    case 'custom':
        if ($customStart && $customEnd) {
            $startDate = new DateTime($customStart);
            $endDate = new DateTime($customEnd);
            $displayRange = 'Custom Range';
        } else {
            $filter = 'month';
            $startDate->modify('first day of this month');
            $endDate->modify('last day of this month');
            $displayRange = 'This Month';
        }
        break;
    case 'all':
        $startDate->setDate(2000, 1, 1);
        $endDate = new DateTime();
        $displayRange = 'All Time';
        break;
    default:
        $filter = 'month';
        $startDate->modify('first day of this month');
        $endDate->modify('last day of this month');
        $displayRange = 'This Month';
        break;
}

$startStr = $startDate->format('Y-m-d 00:00:00');
$endStr = $endDate->format('Y-m-d 23:59:59');

// --- 5. FETCH DATA ---
try {
    // Base query
    $sql = "SELECT 
                sp.id AS payment_id,
                sp.payment_month,
                sp.payment_date,
                sp.amount,
                sp.payment_method,
                sp.note,
                sp.created_at AS payment_recorded_at,
                e.id AS employee_id,
                e.full_name AS employee_name,
                e.designation AS employee_position,
                e.department,
                e.email AS employee_email,
                e.phone AS employee_phone,
                e.joining_date,
                e.salary AS monthly_salary
            FROM salary_payments sp 
            JOIN employees e ON sp.employee_id = e.id 
            WHERE sp.payment_date >= ? AND sp.payment_date <= ?";
    
    // Add ordering based on groupBy
    switch ($groupBy) {
        case 'employee':
            $sql .= " ORDER BY e.full_name ASC, sp.payment_date DESC";
            break;
        case 'month':
            $sql .= " ORDER BY sp.payment_month DESC, e.full_name ASC";
            break;
        case 'method':
            $sql .= " ORDER BY sp.payment_method ASC, sp.payment_date DESC";
            break;
        default:
            $sql .= " ORDER BY sp.payment_date DESC, sp.id DESC";
            break;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$startStr, $endStr]);
    $payments = $stmt->fetchAll();
    
    // Get summary statistics
    $sqlSummary = "SELECT 
                        COUNT(*) as total_payments,
                        COALESCE(SUM(amount), 0) as total_amount,
                        COALESCE(AVG(amount), 0) as average_amount,
                        MIN(payment_date) as first_payment_date,
                        MAX(payment_date) as last_payment_date
                    FROM salary_payments 
                    WHERE payment_date >= ? AND payment_date <= ?";
    
    $stmtSummary = $pdo->prepare($sqlSummary);
    $stmtSummary->execute([$startStr, $endStr]);
    $summary = $stmtSummary->fetch();
    
    // Get statistics by group if needed
    $groupStats = [];
    if ($groupBy !== 'none') {
        switch ($groupBy) {
            case 'employee':
                $sqlGroup = "SELECT 
                                e.full_name as group_name,
                                COUNT(sp.id) as payment_count,
                                SUM(sp.amount) as total_amount,
                                AVG(sp.amount) as average_amount
                            FROM salary_payments sp
                            JOIN employees e ON sp.employee_id = e.id
                            WHERE sp.payment_date >= ? AND sp.payment_date <= ?
                            GROUP BY e.id, e.full_name
                            ORDER BY e.full_name";
                break;
            case 'month':
                $sqlGroup = "SELECT 
                                DATE_FORMAT(payment_month, '%M %Y') as group_name,
                                COUNT(id) as payment_count,
                                SUM(amount) as total_amount,
                                AVG(amount) as average_amount
                            FROM salary_payments
                            WHERE payment_date >= ? AND payment_date <= ?
                            GROUP BY payment_month
                            ORDER BY payment_month DESC";
                break;
            case 'method':
                $sqlGroup = "SELECT 
                                payment_method as group_name,
                                COUNT(id) as payment_count,
                                SUM(amount) as total_amount,
                                AVG(amount) as average_amount
                            FROM salary_payments
                            WHERE payment_date >= ? AND payment_date <= ?
                            GROUP BY payment_method
                            ORDER BY payment_method";
                break;
        }
        
        $stmtGroup = $pdo->prepare($sqlGroup);
        $stmtGroup->execute([$startStr, $endStr]);
        $groupStats = $stmtGroup->fetchAll();
    }
    
} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

// --- 6. HANDLE DIFFERENT EXPORT FORMATS ---
switch ($format) {
    case 'excel':
        exportExcel($payments, $summary, $groupStats, $displayRange, $filter, $startDate, $endDate);
        break;
    case 'pdf':
        exportPdf($payments, $summary, $groupStats, $displayRange, $filter, $startDate, $endDate);
        break;
    case 'csv':
    default:
        exportCsv($payments, $summary, $groupStats, $displayRange, $filter, $startDate, $endDate);
        break;
}

// --- 7. EXPORT FUNCTIONS ---
function exportCsv($payments, $summary, $groupStats, $displayRange, $filter, $startDate, $endDate) {
    // Generate filename
    $filename = generateFilename($filter, $startDate, $endDate, 'csv');
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fputs($output, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
    
    // Write header
    fputcsv($output, ['NEXUS ADMINISTRATION SYSTEM - SALARY PAYMENTS REPORT']);
    fputcsv($output, ['Generated: ' . date('F d, Y h:i A')]);
    fputcsv($output, ['Report Period: ' . $displayRange]);
    
    if ($filter === 'custom') {
        fputcsv($output, ['Date Range: ' . $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y')]);
    }
    
    fputcsv($output, ['']);
    
    // Write summary if enabled
    if (isset($_GET['summary']) && $_GET['summary']) {
        fputcsv($output, ['SUMMARY STATISTICS']);
        fputcsv($output, ['Total Payments:', $summary['total_payments']]);
        fputcsv($output, ['Total Amount:', '$' . number_format($summary['total_amount'], 2)]);
        fputcsv($output, ['Average Payment:', '$' . number_format($summary['average_amount'], 2)]);
        fputcsv($output, ['First Payment:', $summary['first_payment_date'] ? date('M d, Y', strtotime($summary['first_payment_date'])) : 'N/A']);
        fputcsv($output, ['Last Payment:', $summary['last_payment_date'] ? date('M d, Y', strtotime($summary['last_payment_date'])) : 'N/A']);
        fputcsv($output, ['']);
        fputcsv($output, ['']);
    }
    
    // Write group statistics if grouped
    if (!empty($groupStats)) {
        $groupLabel = '';
        switch ($_GET['group'] ?? 'none') {
            case 'employee': $groupLabel = 'Employee'; break;
            case 'month': $groupLabel = 'Month'; break;
            case 'method': $groupLabel = 'Payment Method'; break;
        }
        
        fputcsv($output, ['GROUP STATISTICS (by ' . $groupLabel . ')']);
        fputcsv($output, [$groupLabel, 'Payments', 'Total Amount', 'Average']);
        
        foreach ($groupStats as $stat) {
            fputcsv($output, [
                $stat['group_name'],
                $stat['payment_count'],
                '$' . number_format($stat['total_amount'], 2),
                '$' . number_format($stat['average_amount'], 2)
            ]);
        }
        
        fputcsv($output, ['']);
        fputcsv($output, ['']);
    }
    
    // Write column headers
    $headers = [
        'Payment ID',
        'Employee ID',
        'Employee Name',
        'Designation',
        'Department',
        'Payment Month',
        'Payment Date',
        'Amount',
        'Payment Method',
        'Monthly Salary',
        'Note',
        'Payment Recorded At'
    ];
    fputcsv($output, $headers);
    
    // Write payment data
    foreach ($payments as $payment) {
        $row = [
            $payment['payment_id'],
            $payment['employee_id'],
            $payment['employee_name'],
            $payment['employee_position'],
            $payment['department'] ?? 'N/A',
            date('F Y', strtotime($payment['payment_month'])),
            date('M d, Y', strtotime($payment['payment_date'])),
            '$' . number_format($payment['amount'], 2),
            $payment['payment_method'],
            '$' . number_format($payment['monthly_salary'], 2),
            $payment['note'] ?? '',
            date('M d, Y h:i A', strtotime($payment['payment_recorded_at']))
        ];
        fputcsv($output, $row);
    }
    
    // Write footer
    fputcsv($output, ['']);
    fputcsv($output, ['']);
    fputcsv($output, ['REPORT SUMMARY']);
    fputcsv($output, ['Generated By:', $_SESSION['username']]);
    fputcsv($output, ['User Role:', $_SESSION['role']]);
    fputcsv($output, ['Export Time:', date('F d, Y h:i:s A')]);
    fputcsv($output, ['Total Records Exported:', count($payments)]);
    fputcsv($output, ['Data Source:', 'Nexus Admin System v2.0']);
    
    fclose($output);
    exit;
}

function exportExcel($payments, $summary, $groupStats, $displayRange, $filter, $startDate, $endDate) {
    // For Excel, we'll output HTML that Excel can open
    $filename = generateFilename($filter, $startDate, $endDate, 'xls');
    
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Start HTML output
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th { background-color: #4F46E5; color: white; padding: 8px; text-align: left; }';
    echo 'td { border: 1px solid #ddd; padding: 8px; }';
    echo '.header { background-color: #f2f2f2; font-weight: bold; }';
    echo '.summary { background-color: #e8f4f8; }';
    echo '.total { background-color: #d1fae5; font-weight: bold; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Title
    echo '<h1>NEXUS ADMINISTRATION SYSTEM - SALARY PAYMENTS REPORT</h1>';
    echo '<p><strong>Generated:</strong> ' . date('F d, Y h:i A') . '</p>';
    echo '<p><strong>Report Period:</strong> ' . $displayRange . '</p>';
    
    if ($filter === 'custom') {
        echo '<p><strong>Date Range:</strong> ' . $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y') . '</p>';
    }
    
    echo '<br>';
    
    // Summary
    if (isset($_GET['summary']) && $_GET['summary']) {
        echo '<h2>SUMMARY STATISTICS</h2>';
        echo '<table>';
        echo '<tr class="summary"><td>Total Payments</td><td>' . $summary['total_payments'] . '</td></tr>';
        echo '<tr class="summary"><td>Total Amount</td><td>$' . number_format($summary['total_amount'], 2) . '</td></tr>';
        echo '<tr class="summary"><td>Average Payment</td><td>$' . number_format($summary['average_amount'], 2) . '</td></tr>';
        echo '<tr class="summary"><td>First Payment</td><td>' . ($summary['first_payment_date'] ? date('M d, Y', strtotime($summary['first_payment_date'])) : 'N/A') . '</td></tr>';
        echo '<tr class="summary"><td>Last Payment</td><td>' . ($summary['last_payment_date'] ? date('M d, Y', strtotime($summary['last_payment_date'])) : 'N/A') . '</td></tr>';
        echo '</table>';
        echo '<br><br>';
    }
    
    // Group statistics
    if (!empty($groupStats)) {
        $groupLabel = '';
        switch ($_GET['group'] ?? 'none') {
            case 'employee': $groupLabel = 'Employee'; break;
            case 'month': $groupLabel = 'Month'; break;
            case 'method': $groupLabel = 'Payment Method'; break;
        }
        
        echo '<h2>GROUP STATISTICS (by ' . $groupLabel . ')</h2>';
        echo '<table>';
        echo '<tr class="header">';
        echo '<th>' . $groupLabel . '</th>';
        echo '<th>Payments</th>';
        echo '<th>Total Amount</th>';
        echo '<th>Average</th>';
        echo '</tr>';
        
        foreach ($groupStats as $stat) {
            echo '<tr>';
            echo '<td>' . $stat['group_name'] . '</td>';
            echo '<td>' . $stat['payment_count'] . '</td>';
            echo '<td>$' . number_format($stat['total_amount'], 2) . '</td>';
            echo '<td>$' . number_format($stat['average_amount'], 2) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '<br><br>';
    }
    
    // Main data table
    echo '<h2>SALARY PAYMENT DETAILS</h2>';
    echo '<table>';
    echo '<tr class="header">';
    echo '<th>Payment ID</th>';
    echo '<th>Employee ID</th>';
    echo '<th>Employee Name</th>';
    echo '<th>Designation</th>';
    echo '<th>Department</th>';
    echo '<th>Payment Month</th>';
    echo '<th>Payment Date</th>';
    echo '<th>Amount</th>';
    echo '<th>Payment Method</th>';
    echo '<th>Monthly Salary</th>';
    echo '<th>Note</th>';
    echo '<th>Payment Recorded At</th>';
    echo '</tr>';
    
    $totalAmount = 0;
    foreach ($payments as $payment) {
        $totalAmount += $payment['amount'];
        echo '<tr>';
        echo '<td>' . $payment['payment_id'] . '</td>';
        echo '<td>' . $payment['employee_id'] . '</td>';
        echo '<td>' . htmlspecialchars($payment['employee_name']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['employee_position']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['department'] ?? 'N/A') . '</td>';
        echo '<td>' . date('F Y', strtotime($payment['payment_month'])) . '</td>';
        echo '<td>' . date('M d, Y', strtotime($payment['payment_date'])) . '</td>';
        echo '<td>$' . number_format($payment['amount'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($payment['payment_method']) . '</td>';
        echo '<td>$' . number_format($payment['monthly_salary'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($payment['note'] ?? '') . '</td>';
        echo '<td>' . date('M d, Y h:i A', strtotime($payment['payment_recorded_at'])) . '</td>';
        echo '</tr>';
    }
    
    // Total row
    echo '<tr class="total">';
    echo '<td colspan="7"><strong>TOTAL</strong></td>';
    echo '<td><strong>$' . number_format($totalAmount, 2) . '</strong></td>';
    echo '<td colspan="4"></td>';
    echo '</tr>';
    
    echo '</table>';
    
    // Footer
    echo '<br><br>';
    echo '<h3>REPORT SUMMARY</h3>';
    echo '<table>';
    echo '<tr><td>Generated By:</td><td>' . $_SESSION['username'] . '</td></tr>';
    echo '<tr><td>User Role:</td><td>' . $_SESSION['role'] . '</td></tr>';
    echo '<tr><td>Export Time:</td><td>' . date('F d, Y h:i:s A') . '</td></tr>';
    echo '<tr><td>Total Records Exported:</td><td>' . count($payments) . '</td></tr>';
    echo '<tr><td>Data Source:</td><td>Nexus Admin System v2.0</td></tr>';
    echo '</table>';
    
    echo '</body>';
    echo '</html>';
    exit;
}

function exportPdf($payments, $summary, $groupStats, $displayRange, $filter, $startDate, $endDate) {
    // For PDF, we'll use HTML to PDF conversion
    // Note: In production, you'd want to use a proper PDF library like TCPDF or Dompdf
    // This is a simplified version that outputs HTML that can be printed as PDF
    
    $filename = generateFilename($filter, $startDate, $endDate, 'html');
    
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Salary Payments Report</title>';
    echo '<style>';
    echo '@media print {';
    echo '  body { margin: 0; padding: 20px; font-family: Arial, sans-serif; font-size: 12px; }';
    echo '  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }';
    echo '  th, td { border: 1px solid #000; padding: 6px; text-align: left; }';
    echo '  th { background-color: #f2f2f2; font-weight: bold; }';
    echo '  .header { text-align: center; margin-bottom: 30px; }';
    echo '  .summary { background-color: #e8f4f8; }';
    echo '  .total { background-color: #d1fae5; font-weight: bold; }';
    echo '  .page-break { page-break-after: always; }';
    echo '}';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    
    // Header
    echo '<div class="header">';
    echo '<h1>NEXUS ADMINISTRATION SYSTEM</h1>';
    echo '<h2>SALARY PAYMENTS REPORT</h2>';
    echo '<p><strong>Generated:</strong> ' . date('F d, Y h:i A') . '</p>';
    echo '<p><strong>Report Period:</strong> ' . $displayRange . '</p>';
    
    if ($filter === 'custom') {
        echo '<p><strong>Date Range:</strong> ' . $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y') . '</p>';
    }
    
    echo '<p><strong>Generated By:</strong> ' . $_SESSION['username'] . ' (' . $_SESSION['role'] . ')</p>';
    echo '</div>';
    
    // Summary
    if (isset($_GET['summary']) && $_GET['summary']) {
        echo '<h3>SUMMARY STATISTICS</h3>';
        echo '<table>';
        echo '<tr class="summary"><td>Total Payments</td><td>' . $summary['total_payments'] . '</td></tr>';
        echo '<tr class="summary"><td>Total Amount</td><td>$' . number_format($summary['total_amount'], 2) . '</td></tr>';
        echo '<tr class="summary"><td>Average Payment</td><td>$' . number_format($summary['average_amount'], 2) . '</td></tr>';
        echo '<tr class="summary"><td>First Payment Date</td><td>' . ($summary['first_payment_date'] ? date('M d, Y', strtotime($summary['first_payment_date'])) : 'N/A') . '</td></tr>';
        echo '<tr class="summary"><td>Last Payment Date</td><td>' . ($summary['last_payment_date'] ? date('M d, Y', strtotime($summary['last_payment_date'])) : 'N/A') . '</td></tr>';
        echo '</table>';
    }
    
    // Group statistics
    if (!empty($groupStats)) {
        $groupLabel = '';
        switch ($_GET['group'] ?? 'none') {
            case 'employee': $groupLabel = 'Employee'; break;
            case 'month': $groupLabel = 'Month'; break;
            case 'method': $groupLabel = 'Payment Method'; break;
        }
        
        echo '<h3>GROUP STATISTICS (by ' . $groupLabel . ')</h3>';
        echo '<table>';
        echo '<tr>';
        echo '<th>' . $groupLabel . '</th>';
        echo '<th>Payments</th>';
        echo '<th>Total Amount</th>';
        echo '<th>Average</th>';
        echo '</tr>';
        
        foreach ($groupStats as $stat) {
            echo '<tr>';
            echo '<td>' . $stat['group_name'] . '</td>';
            echo '<td>' . $stat['payment_count'] . '</td>';
            echo '<td>$' . number_format($stat['total_amount'], 2) . '</td>';
            echo '<td>$' . number_format($stat['average_amount'], 2) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    echo '<div class="page-break"></div>';
    
    // Main data table
    echo '<h3>SALARY PAYMENT DETAILS</h3>';
    echo '<table>';
    echo '<tr>';
    echo '<th>Payment ID</th>';
    echo '<th>Employee Name</th>';
    echo '<th>Designation</th>';
    echo '<th>Payment Month</th>';
    echo '<th>Payment Date</th>';
    echo '<th>Amount</th>';
    echo '<th>Payment Method</th>';
    echo '<th>Note</th>';
    echo '</tr>';
    
    $totalAmount = 0;
    $counter = 0;
    foreach ($payments as $payment) {
        $counter++;
        $totalAmount += $payment['amount'];
        
        // Add page break every 30 rows
        if ($counter % 30 === 0) {
            echo '</table>';
            echo '<div class="page-break"></div>';
            echo '<h3>SALARY PAYMENT DETAILS (Continued)</h3>';
            echo '<table>';
            echo '<tr>';
            echo '<th>Payment ID</th>';
            echo '<th>Employee Name</th>';
            echo '<th>Designation</th>';
            echo '<th>Payment Month</th>';
            echo '<th>Payment Date</th>';
            echo '<th>Amount</th>';
            echo '<th>Payment Method</th>';
            echo '<th>Note</th>';
            echo '</tr>';
        }
        
        echo '<tr>';
        echo '<td>' . $payment['payment_id'] . '</td>';
        echo '<td>' . htmlspecialchars($payment['employee_name']) . '</td>';
        echo '<td>' . htmlspecialchars($payment['employee_position']) . '</td>';
        echo '<td>' . date('M Y', strtotime($payment['payment_month'])) . '</td>';
        echo '<td>' . date('M d, Y', strtotime($payment['payment_date'])) . '</td>';
        echo '<td>$' . number_format($payment['amount'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($payment['payment_method']) . '</td>';
        echo '<td>' . htmlspecialchars(substr($payment['note'] ?? '', 0, 50)) . '</td>';
        echo '</tr>';
    }
    
    // Total row
    echo '<tr class="total">';
    echo '<td colspan="5"><strong>TOTAL</strong></td>';
    echo '<td><strong>$' . number_format($totalAmount, 2) . '</strong></td>';
    echo '<td colspan="2"></td>';
    echo '</tr>';
    
    echo '</table>';
    
    // Footer
    echo '<div style="margin-top: 30px; text-align: center; font-size: 10px; color: #666;">';
    echo '<p>Report generated by Nexus Admin System v2.0</p>';
    echo '<p>Export Time: ' . date('F d, Y h:i:s A') . ' | Total Records: ' . count($payments) . '</p>';
    echo '</div>';
    
    // JavaScript to trigger print dialog
    echo '<script>';
    echo 'window.onload = function() {';
    echo '  setTimeout(function() {';
    echo '    window.print();';
    echo '    setTimeout(function() { window.close(); }, 100);';
    echo '  }, 500);';
    echo '}';
    echo '</script>';
    
    echo '</body>';
    echo '</html>';
    exit;
}

function generateFilename($filter, $startDate, $endDate, $extension) {
    $base = "salary_payments_";
    
    switch ($filter) {
        case 'today':
            return $base . $startDate->format('Y-m-d') . '.' . $extension;
        case 'week':
            return $base . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.' . $extension;
        case 'month':
            return $base . $startDate->format('Y-m') . '.' . $extension;
        case 'year':
            return $base . $startDate->format('Y') . '.' . $extension;
        case 'custom':
            return $base . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d') . '.' . $extension;
        case 'all':
            return $base . 'all_records_' . date('Y-m-d') . '.' . $extension;
        default:
            return $base . date('Y-m-d') . '.' . $extension;
    }
}