<?php
/**
 * work_order_pdf.php
 * PDF Export for Work Orders - Compatible with Shared Hosting
 * Generate PDF reports for work orders with filters applied
 */

session_start();

// --- 1. AUTHENTICATION & CSRF PROTECTION ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Verify CSRF token for security
if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf_token'] ?? '')) {
    die("Invalid security token!");
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
    die("Database Connection Error: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { 
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); 
}

function formatCurrency($amount) { 
    return '$' . number_format(floatval($amount), 2); 
}

function daysRemaining($end_date) {
    if (empty($end_date)) return 'N/A';
    try {
        $today = new DateTime();
        $end = new DateTime($end_date);
        $interval = $today->diff($end);
        return $interval->invert ? 'Overdue' : $interval->days . ' days';
    } catch (Exception $e) {
        return 'Invalid date';
    }
}

// --- 4. FETCH WORK ORDERS WITH FILTERS ---
// Get filter parameters from query string
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$subcontractor_filter = $_GET['subcontractor'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$priority_filter = $_GET['priority'] ?? 'all';
$export_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];

// Validate and sanitize inputs
$allowed_statuses = ['Draft', 'Assigned', 'In Progress', 'On Hold', 'Completed', 'Cancelled'];
$allowed_priorities = ['Low', 'Medium', 'High', 'Urgent'];

if (!in_array($status_filter, array_merge(['all'], $allowed_statuses))) {
    $status_filter = 'all';
}
if (!in_array($priority_filter, array_merge(['all'], $allowed_priorities))) {
    $priority_filter = 'all';
}

// Build query
$query = "SELECT wo.*, 
                 s.company_name, 
                 s.contact_person,
                 s.specialization,
                 s.email,
                 s.phone,
                 (SELECT COUNT(*) FROM work_order_materials WHERE work_order_id = wo.id) as material_count,
                 (SELECT COALESCE(SUM(total_price), 0) FROM work_order_materials WHERE work_order_id = wo.id) as materials_cost,
                 (SELECT COALESCE(SUM(amount), 0) FROM work_order_payments WHERE work_order_id = wo.id) as total_payments
          FROM subcontractor_work_orders wo
          LEFT JOIN subcontractors s ON wo.subcontractor_id = s.id
          WHERE 1=1";

$params = [];

// Apply filters
if (!empty($search)) {
    $query .= " AND (wo.work_order_number LIKE ? OR wo.project_name LIKE ? OR s.company_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if ($status_filter !== 'all') {
    $query .= " AND wo.work_status = ?";
    $params[] = $status_filter;
}

if ($subcontractor_filter !== 'all' && is_numeric($subcontractor_filter)) {
    $query .= " AND wo.subcontractor_id = ?";
    $params[] = intval($subcontractor_filter);
}

if ($priority_filter !== 'all') {
    $query .= " AND wo.priority = ?";
    $params[] = $priority_filter;
}

if (!empty($date_from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)) {
    $query .= " AND wo.start_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)) {
    $query .= " AND wo.end_date <= ?";
    $params[] = $date_to;
}

// If specific IDs were selected for export
if (!empty($export_ids) && is_array($export_ids)) {
    $ids = array_filter(array_map('intval', $export_ids));
    if (!empty($ids)) {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        $query .= " AND wo.id IN ($placeholders)";
        $params = array_merge($params, $ids);
    }
}

$query .= " ORDER BY wo.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $work_orders = $stmt->fetchAll();
} catch (Exception $e) {
    die("Error fetching work orders: " . $e->getMessage());
}

// Get statistics
try {
    $total_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders")->fetch()['count'];
    $total_estimated = $pdo->query("SELECT COALESCE(SUM(estimated_cost), 0) as total FROM subcontractor_work_orders")->fetch()['total'];
    $total_payments = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM work_order_payments")->fetch()['total'];
} catch (Exception $e) {
    $total_count = $total_estimated = $total_payments = 0;
}

// --- 5. FALLBACK HTML REPORT ---
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Work Order Report - NexusAdmin</title>
    <style>
        @media print {
            body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
            .no-print { display: none !important; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
        }
        
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .report-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 3px solid #4F46E5; padding-bottom: 15px; margin-bottom: 20px; text-align: center; }
        .header-info { display: flex; justify-content: space-between; margin-bottom: 30px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .company-info { text-align: left; }
        .report-info { text-align: right; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background-color: #4F46E5; color: white; padding: 12px; text-align: left; font-weight: 600; }
        td { border: 1px solid #ddd; padding: 10px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .status-completed { color: #10B981; font-weight: bold; }
        .status-progress { color: #3B82F6; font-weight: bold; }
        .status-hold { color: #F59E0B; font-weight: bold; }
        .status-cancelled { color: #EF4444; font-weight: bold; }
        .status-draft { color: #6B7280; font-weight: bold; }
        .status-assigned { color: #8B5CF6; font-weight: bold; }
        .summary { background: #f0f9ff; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4F46E5; }
        .controls { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 10px; justify-content: center; }
        .btn { background: #4F46E5; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #4338ca; }
        .footer { margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd; color: #666; font-size: 0.9em; text-align: center; }
        .badge { padding: 4px 8px; border-radius: 12px; font-size: 0.8em; font-weight: 600; }
        .priority-high { background: #fee2e2; color: #dc2626; }
        .priority-medium { background: #fef3c7; color: #d97706; }
        .priority-low { background: #dcfce7; color: #16a34a; }
        .priority-urgent { background: #fecaca; color: #b91c1c; }
        .cost { font-family: 'Courier New', monospace; font-weight: 600; }
        .overdue { color: #dc2626; font-weight: bold; }
        .logo { font-size: 24px; font-weight: bold; color: #4F46E5; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="report-container">
        <!-- Header -->
        <div class="logo">NexusAdmin</div>
        <h1>WORK ORDER MANAGEMENT REPORT</h1>
        
        <div class="header-info">
            <div class="company-info">
                <strong>Company:</strong> NexusAdmin System<br>
                <strong>Report Type:</strong> Work Order Summary<br>
                <strong>Generated By:</strong> <?php echo e($_SESSION['username'] ?? 'System'); ?>
            </div>
            <div class="report-info">
                <strong>Generated:</strong> <?php echo date('F j, Y h:i A'); ?><br>
                <strong>Total Records:</strong> <?php echo count($work_orders); ?><br>
                <strong>Report ID:</strong> WO-<?php echo date('Ymd-His'); ?>
            </div>
        </div>
        
        <!-- Summary -->
        <div class="summary">
            <h3>SUMMARY STATISTICS</h3>
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px;">
                <div>
                    <strong>Total Work Orders:</strong><br>
                    <span style="font-size: 1.5em; color: #4F46E5;"><?php echo count($work_orders); ?></span>
                </div>
                <div>
                    <strong>System Total:</strong><br>
                    <span style="font-size: 1.5em; color: #3B82F6;"><?php echo $total_count; ?></span>
                </div>
                <div>
                    <strong>Total Estimated:</strong><br>
                    <span style="font-size: 1.5em; color: #10B981;"><?php echo formatCurrency($total_estimated); ?></span>
                </div>
                <div>
                    <strong>Total Payments:</strong><br>
                    <span style="font-size: 1.5em; color: #8B5CF6;"><?php echo formatCurrency($total_payments); ?></span>
                </div>
            </div>
            
            <?php if($search || $status_filter != 'all' || $priority_filter != 'all' || !empty($date_from) || !empty($date_to)): ?>
            <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 5px;">
                <strong>Filters Applied:</strong><br>
                <?php 
                    $filter_text = [];
                    if ($status_filter != 'all') $filter_text[] = "Status: " . $status_filter;
                    if ($priority_filter != 'all') $filter_text[] = "Priority: " . $priority_filter;
                    if (!empty($search)) $filter_text[] = "Search: " . $search;
                    if (!empty($date_from)) $filter_text[] = "From: " . date('M d, Y', strtotime($date_from));
                    if (!empty($date_to)) $filter_text[] = "To: " . date('M d, Y', strtotime($date_to));
                    echo $filter_text ? implode(' • ', $filter_text) : 'None';
                ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Controls -->
        <div class="controls no-print">
            <button class="btn" onclick="window.print()">
                <i class="ph ph-printer"></i> Print Report
            </button>
            <button class="btn" onclick="exportToCSV()">
                <i class="ph ph-file-csv"></i> Export as CSV
            </button>
            <a href="work_order_management.php" class="btn">
                <i class="ph ph-arrow-left"></i> Back to Work Orders
            </a>
        </div>
        
        <!-- Main Table -->
        <table>
            <thead>
                <tr>
                    <th>WO #</th>
                    <th>Project</th>
                    <th>Subcontractor</th>
                    <th>Status</th>
                    <th>Priority</th>
                    <th>Dates</th>
                    <th>Time Left</th>
                    <th>Estimated Cost</th>
                    <th>Payments</th>
                    <th>Materials</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($work_orders) > 0): ?>
                    <?php foreach($work_orders as $wo): 
                        $isOverdue = false;
                        if (!empty($wo['end_date']) && $wo['end_date'] < date('Y-m-d') && 
                            !in_array($wo['work_status'], ['Completed', 'Cancelled'])) {
                            $isOverdue = true;
                        }
                    ?>
                    <tr>
                        <td><strong><?php echo e($wo['work_order_number']); ?></strong></td>
                        <td>
                            <?php echo e($wo['project_name']); ?><br>
                            <small style="color: #666;"><?php echo substr($wo['work_description'] ?? '', 0, 50) . (strlen($wo['work_description'] ?? '') > 50 ? '...' : ''); ?></small>
                        </td>
                        <td>
                            <?php echo e($wo['company_name'] ?? 'N/A'); ?><br>
                            <small style="color: #666;"><?php echo e($wo['specialization'] ?? ''); ?></small>
                        </td>
                        <td class="status-<?php echo strtolower(str_replace(' ', '-', $wo['work_status'])); ?>">
                            <?php echo e($wo['work_status']); ?>
                        </td>
                        <td>
                            <span class="badge priority-<?php echo strtolower($wo['priority']); ?>">
                                <?php echo e($wo['priority']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if(!empty($wo['start_date'])): ?>
                                <?php echo date('M d, Y', strtotime($wo['start_date'])); ?><br>
                                <small>to</small><br>
                                <?php echo date('M d, Y', strtotime($wo['end_date'])); ?>
                            <?php else: ?>
                                Not Set
                            <?php endif; ?>
                        </td>
                        <td class="<?php echo $isOverdue ? 'overdue' : ''; ?>">
                            <?php echo daysRemaining($wo['end_date']); ?>
                        </td>
                        <td class="cost">
                            <?php echo formatCurrency($wo['estimated_cost']); ?><br>
                            <?php if(floatval($wo['actual_cost']) > 0): ?>
                                <small>Actual: <?php echo formatCurrency($wo['actual_cost']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="cost">
                            <?php echo formatCurrency($wo['total_payments']); ?>
                        </td>
                        <td>
                            <?php echo $wo['material_count'] ?? 0; ?> items<br>
                            <small><?php echo formatCurrency($wo['materials_cost']); ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px; color: #666;">
                            <div style="font-size: 1.2em; margin-bottom: 10px;">
                                <i class="ph ph-clipboard-text" style="font-size: 2em; opacity: 0.5;"></i>
                            </div>
                            No work orders found matching the selected criteria.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Financial Summary -->
        <?php if (count($work_orders) > 0): 
            $total_estimated_sum = array_sum(array_column($work_orders, 'estimated_cost'));
            $total_payments_sum = array_sum(array_column($work_orders, 'total_payments'));
            $total_materials_sum = array_sum(array_column($work_orders, 'materials_cost'));
        ?>
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <h3>FINANCIAL SUMMARY</h3>
            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
                <div style="text-align: center;">
                    <div style="font-size: 1.8em; color: #4F46E5; font-weight: bold;">
                        <?php echo formatCurrency($total_estimated_sum); ?>
                    </div>
                    <div style="color: #666;">Total Estimated Value</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.8em; color: #10B981; font-weight: bold;">
                        <?php echo formatCurrency($total_payments_sum); ?>
                    </div>
                    <div style="color: #666;">Total Payments Received</div>
                </div>
                <div style="text-align: center;">
                    <div style="font-size: 1.8em; color: #8B5CF6; font-weight: bold;">
                        <?php echo formatCurrency($total_materials_sum); ?>
                    </div>
                    <div style="color: #666;">Total Materials Cost</div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <p>
                <strong>Confidential - For Internal Use Only</strong><br>
                This report contains proprietary business information. Unauthorized distribution is prohibited.<br>
                Generated by NexusAdmin Subcontract Management System v1.0<br>
                &copy; <?php echo date('Y'); ?> All rights reserved.
            </p>
            <p style="font-size: 0.8em; color: #999; margin-top: 10px;">
                Report ID: WO-<?php echo date('Ymd-His'); ?> | Page 1 of 1
            </p>
        </div>
    </div>

    <script>
        function exportToCSV() {
            let csv = [];
            let rows = document.querySelectorAll("table tr");
            
            // Add header
            let header = [];
            document.querySelectorAll("table thead th").forEach(th => {
                header.push(th.innerText);
            });
            csv.push(header.join(","));
            
            // Add data rows
            for (let i = 0; i < rows.length; i++) {
                let row = [], cols = rows[i].querySelectorAll("td");
                
                for (let j = 0; j < cols.length; j++) {
                    // Clean up the data for CSV
                    let data = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/(\s\s)/gm, " ");
                    data = data.replace(/"/g, '""');
                    row.push('"' + data + '"');
                }
                
                if (row.length > 0) {
                    csv.push(row.join(","));
                }
            }
            
            // Download CSV file
            let csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
            let encodedUri = encodeURI(csvContent);
            let link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "work_orders_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Add print styles
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body { margin: 0; padding: 0; }
                .no-print { display: none !important; }
                .report-container { box-shadow: none; padding: 10px; }
                .controls, .btn { display: none !important; }
                table { font-size: 10px; }
                th, td { padding: 6px; }
            }
        `;
        document.head.appendChild(style);
    </script>
    
    <!-- Add Phosphor Icons for print -->
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
</body>
</html>