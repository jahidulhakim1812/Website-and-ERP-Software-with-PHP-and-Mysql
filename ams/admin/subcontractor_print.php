<?php
/**
 * subcontractor_print.php
 * Print-friendly version of subcontractor list
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Database connection (same as above)
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

// Apply same filters as main page (same as above)
// ... [same filter logic as above] ...

// Then output HTML with print-friendly styling
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subcontractor Report - Print Version</title>
    <style>
        @media print {
            body { font-family: Arial, sans-serif; margin: 20px; }
            .no-print { display: none !important; }
            table { width: 100%; border-collapse: collapse; }
            th, td { border: 1px solid #000; padding: 8px; text-align: left; }
            th { background-color: #f2f2f2; }
            h1 { color: #000; }
        }
        body { font-family: Arial, sans-serif; margin: 20px; }
        .no-print { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        h1 { color: #000; }
        .header-info { margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">Print / Save as PDF</button>
        <button onclick="window.close()">Close</button>
    </div>
    
    <h1>Subcontractor List Report</h1>
    <div class="header-info">
        <p><strong>Generated on:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>Total Records:</strong> <?php echo count($subcontractors); ?></p>
        <p><strong>Filters Applied:</strong><br>
            Search: <?php echo $search ?: 'None'; ?><br>
            Status: <?php echo $status_filter != 'all' ? $status_filter : 'All'; ?><br>
            Specialization: <?php echo $specialization_filter != 'all' ? $specialization_filter : 'All'; ?><br>
            Sort: <?php echo $sort_by; ?>
        </p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Company</th>
                <th>Contact Person</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Specialization</th>
                <th>Project Rate</th>
                <th>Status</th>
                <th>Registration Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($subcontractors as $sub): ?>
            <tr>
                <td><?php echo htmlspecialchars($sub['company_name']); ?></td>
                <td><?php echo htmlspecialchars($sub['contact_person']); ?></td>
                <td><?php echo htmlspecialchars($sub['email']); ?></td>
                <td><?php echo htmlspecialchars($sub['phone']); ?></td>
                <td><?php echo htmlspecialchars($sub['specialization'] ?: 'N/A'); ?></td>
                <td>$<?php echo number_format($sub['project_rate'], 2); ?></td>
                <td><?php echo htmlspecialchars($sub['status']); ?></td>
                <td><?php echo date('M d, Y', strtotime($sub['registration_date'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <script>
        window.onload = function() {
            // Auto-trigger print dialog
            // window.print();
        };
    </script>
</body>
</html>