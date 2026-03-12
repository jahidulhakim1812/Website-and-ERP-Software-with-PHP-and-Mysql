<?php
/**
 * subcontractor_pdf.php
 * PDF Generation for Subcontractor List
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
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
    die("Database Connection Error: " . $ex->getMessage());
}

// Apply same filters as main page
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$specialization_filter = $_GET['specialization'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'newest';

// Build query
$query = "SELECT * FROM subcontractors WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (company_name LIKE ? OR contact_person LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term]);
}

if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if ($specialization_filter !== 'all' && $specialization_filter !== '') {
    $query .= " AND specialization = ?";
    $params[] = $specialization_filter;
}

// Add sorting
switch ($sort_by) {
    case 'name_asc':
        $query .= " ORDER BY company_name ASC";
        break;
    case 'name_desc':
        $query .= " ORDER BY company_name DESC";
        break;
    case 'rate_high':
        $query .= " ORDER BY project_rate DESC";
        break;
    case 'rate_low':
        $query .= " ORDER BY project_rate ASC";
        break;
    case 'oldest':
        $query .= " ORDER BY created_at ASC";
        break;
    case 'newest':
    default:
        $query .= " ORDER BY created_at DESC";
        break;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $subcontractors = $stmt->fetchAll();
    
    // Get statistics
    $total_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractors")->fetch()['count'];
    $active_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractors WHERE status = 'Active'")->fetch()['count'];
    $pending_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractors WHERE status = 'Pending'")->fetch()['count'];
    $inactive_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractors WHERE status = 'Inactive'")->fetch()['count'];
    
} catch (Exception $e) {
    die("Error fetching data: " . $e->getMessage());
}

/**
 * SIMPLE HTML TO PDF USING BUILT-IN PHP
 * This works on ANY shared hosting without external libraries
 */
function generatePDF($subcontractors, $filters, $stats) {
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Subcontractor Report</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            font-size: 12px; 
            line-height: 1.4;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px;
            border-bottom: 2px solid #4F46E5;
            padding-bottom: 20px;
        }
        .header h1 { 
            color: #4F46E5; 
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .header-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .info-box {
            flex: 1;
            min-width: 200px;
            margin: 5px;
        }
        .info-box h3 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #666;
        }
        .info-box p {
            margin: 0;
            padding: 8px;
            background: #f5f5f5;
            border-radius: 4px;
            border-left: 4px solid #4F46E5;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
        .stat-card {
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #ddd;
        }
        .stat-card.total { border-left: 4px solid #4F46E5; }
        .stat-card.active { border-left: 4px solid #10B981; }
        .stat-card.pending { border-left: 4px solid #F59E0B; }
        .stat-card.inactive { border-left: 4px solid #EF4444; }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
        }
        .stat-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 11px;
        }
        table th {
            background: #4F46E5;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #ddd;
        }
        table td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        table tr:nth-child(even) {
            background: #f9f9f9;
        }
        table tr:hover {
            background: #f0f0f0;
        }
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .status-active { background: #D1FAE5; color: #065F46; }
        .status-inactive { background: #FEE2E2; color: #991B1B; }
        .status-pending { background: #FEF3C7; color: #92400E; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            color: #666;
            font-size: 10px;
        }
        .company-initials {
            display: inline-block;
            width: 30px;
            height: 30px;
            background: #4F46E5;
            color: white;
            border-radius: 6px;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 8px;
        }
        .page-break {
            page-break-before: always;
        }
        @media print {
            body { margin: 0; padding: 15px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Subcontractor Management Report</h1>
        <div class="header-info">
            <div class="info-box">
                <h3>Generated On</h3>
                <p>' . date('F j, Y \a\t g:i A') . '</p>
            </div>
            <div class="info-box">
                <h3>Generated By</h3>
                <p>' . htmlspecialchars($_SESSION['username']) . ' (' . htmlspecialchars($_SESSION['role']) . ')</p>
            </div>
            <div class="info-box">
                <h3>Total Records</h3>
                <p>' . count($subcontractors) . ' subcontractors</p>
            </div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card total">
                <div class="stat-number">' . $stats['total'] . '</div>
                <div class="stat-label">Total Subcontractors</div>
            </div>
            <div class="stat-card active">
                <div class="stat-number">' . $stats['active'] . '</div>
                <div class="stat-label">Active</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-number">' . $stats['pending'] . '</div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="stat-card inactive">
                <div class="stat-number">' . $stats['inactive'] . '</div>
                <div class="stat-label">Inactive</div>
            </div>
        </div>
        
        <div class="filters-info">
            <h3>Filters Applied</h3>
            <p><strong>Search:</strong> ' . ($filters['search'] ?: 'None') . '</p>
            <p><strong>Status:</strong> ' . ($filters['status'] != 'all' ? $filters['status'] : 'All') . '</p>
            <p><strong>Specialization:</strong> ' . ($filters['specialization'] != 'all' ? $filters['specialization'] : 'All') . '</p>
            <p><strong>Sort By:</strong> ' . $filters['sort'] . '</p>
        </div>
    </div>';
    
    if (count($subcontractors) > 0) {
        $html .= '
    <table>
        <thead>
            <tr>
                <th style="width: 25%">Company Details</th>
                <th style="width: 20%">Contact Information</th>
                <th style="width: 15%">Specialization</th>
                <th style="width: 10%">Project Rate</th>
                <th style="width: 10%">Reg. Date</th>
                <th style="width: 10%">Status</th>
                <th style="width: 10%">Tax ID</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($subcontractors as $index => $sub) {
            // Status badge
            $statusClass = '';
            switch ($sub['status']) {
                case 'Active': $statusClass = 'status-active'; break;
                case 'Inactive': $statusClass = 'status-inactive'; break;
                case 'Pending': $statusClass = 'status-pending'; break;
            }
            
            // Format registration date
            $regDate = date('M d, Y', strtotime($sub['registration_date']));
            
            // Company initials
            $initials = strtoupper(substr($sub['company_name'], 0, 2));
            
            // Calculate time since registration
            $regDateTime = new DateTime($sub['registration_date']);
            $today = new DateTime();
            $interval = $today->diff($regDateTime);
            $timeSince = '';
            if ($interval->y > 0) {
                $timeSince = $interval->y . ' year' . ($interval->y > 1 ? 's' : '');
            } elseif ($interval->m > 0) {
                $timeSince = $interval->m . ' month' . ($interval->m > 1 ? 's' : '');
            } else {
                $timeSince = $interval->d . ' day' . ($interval->d > 1 ? 's' : '');
            }
            
            $html .= '
            <tr>
                <td>
                    <div style="display: flex; align-items: center;">
                        <span class="company-initials">' . $initials . '</span>
                        <div>
                            <strong>' . htmlspecialchars($sub['company_name']) . '</strong><br>
                            <small>ID: SC-' . str_pad($sub['id'], 4, '0', STR_PAD_LEFT) . '</small>
                        </div>
                    </div>
                </td>
                <td>
                    <strong>' . htmlspecialchars($sub['contact_person']) . '</strong><br>
                    ' . ($sub['email'] ? '<small>📧 ' . htmlspecialchars($sub['email']) . '</small><br>' : '') . '
                    ' . ($sub['phone'] ? '<small>📞 ' . htmlspecialchars($sub['phone']) . '</small>' : '') . '
                </td>
                <td>' . htmlspecialchars($sub['specialization'] ?: 'N/A') . '</td>
                <td style="color: #10B981; font-weight: bold;">
                    $' . number_format($sub['project_rate'], 2) . '
                </td>
                <td>
                    ' . $regDate . '<br>
                    <small style="color: #666;">(' . $timeSince . ')</small>
                </td>
                <td>
                    <span class="status-badge ' . $statusClass . '">' . $sub['status'] . '</span>
                </td>
                <td>' . htmlspecialchars($sub['tax_id'] ?: 'N/A') . '</td>
            </tr>';
            
            // Add page break after every 20 records
            if (($index + 1) % 20 === 0 && ($index + 1) < count($subcontractors)) {
                $html .= '</tbody></table><div class="page-break"></div><table><thead><tr><th>Company Details</th><th>Contact Information</th><th>Specialization</th><th>Project Rate</th><th>Reg. Date</th><th>Status</th><th>Tax ID</th></tr></thead><tbody>';
            }
        }
        
        $html .= '
        </tbody>
    </table>';
    } else {
        $html .= '
    <div style="text-align: center; padding: 40px; border: 1px dashed #ddd; border-radius: 8px; margin: 20px 0;">
        <h3 style="color: #666;">No Subcontractors Found</h3>
        <p>No subcontractors match the current filter criteria.</p>
    </div>';
    }
    
    $html .= '
    <div class="footer">
        <p>Report generated by NexusAdmin System • Page {PAGENO} of {nbpg}</p>
        <p>© ' . date('Y') . ' All rights reserved. This report is confidential.</p>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * OPTION 1: Use TCPDF if available (better PDF quality)
 * OPTION 2: Use HTML output that can be saved as PDF from browser
 */

// Check if TCPDF is available
$useTCPDF = false;
if (file_exists('tcpdf/tcpdf.php') || class_exists('TCPDF')) {
    $useTCPDF = true;
}

if ($useTCPDF) {
    // OPTION 1: Use TCPDF for better PDF generation
    require_once('tcpdf/tcpdf.php');
    
    // Create new PDF document
    $pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('NexusAdmin');
    $pdf->SetAuthor($_SESSION['username']);
    $pdf->SetTitle('Subcontractor Report');
    $pdf->SetSubject('Subcontractor Management');
    $pdf->SetKeywords('Subcontractor, Report, Management');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Set margins
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Prepare filters and stats
    $filters = [
        'search' => $search,
        'status' => $status_filter,
        'specialization' => $specialization_filter,
        'sort' => $sort_by
    ];
    
    $stats = [
        'total' => $total_count,
        'active' => $active_count,
        'pending' => $pending_count,
        'inactive' => $inactive_count
    ];
    
    // Generate HTML content
    $html = generatePDF($subcontractors, $filters, $stats);
    
    // Convert HTML to PDF
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Close and output PDF document
    $pdf->Output('Subcontractor_Report_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
    
} else {
    // OPTION 2: Use HTML output that users can save as PDF
    // This works on ANY shared hosting without external libraries
    
    // Prepare filters and stats
    $filters = [
        'search' => $search,
        'status' => $status_filter,
        'specialization' => $specialization_filter,
        'sort' => $sort_by
    ];
    
    $stats = [
        'total' => $total_count,
        'active' => $active_count,
        'pending' => $pending_count,
        'inactive' => $inactive_count
    ];
    
    // Generate HTML
    $html = generatePDF($subcontractors, $filters, $stats);
    
    // Output HTML with print/save options
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Subcontractor Report - Print/Save as PDF</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            .print-actions {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 15px;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                z-index: 1000;
            }
            .print-actions button {
                display: block;
                width: 100%;
                padding: 10px 15px;
                margin: 5px 0;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-weight: bold;
                transition: all 0.3s;
            }
            .btn-print {
                background: #4F46E5;
                color: white;
            }
            .btn-print:hover {
                background: #4338ca;
            }
            .btn-download {
                background: #10B981;
                color: white;
            }
            .btn-download:hover {
                background: #0da271;
            }
            .btn-close {
                background: #EF4444;
                color: white;
            }
            .btn-close:hover {
                background: #dc2626;
            }
            @media print {
                .print-actions { display: none !important; }
            }
        </style>
    </head>
    <body>
        <div class="print-actions">
            <button class="btn-print" onclick="window.print()">
                📄 Print / Save as PDF
            </button>
            <button class="btn-download" onclick="downloadAsPDF()">
                ⬇️ Download as PDF
            </button>
            <button class="btn-close" onclick="window.close()">
                ✕ Close
            </button>
        </div>
        
        <?php echo $html; ?>
        
        <script>
            // Auto-trigger print dialog after page load
            window.onload = function() {
                // You can uncomment this line to auto-open print dialog:
                // window.print();
            };
            
            // Function to trigger download (works with browsers that support print to PDF)
            function downloadAsPDF() {
                window.print();
            }
            
            // Add page numbers
            document.addEventListener('DOMContentLoaded', function() {
                const totalPages = Math.ceil(document.body.scrollHeight / window.innerHeight);
                const pageElements = document.querySelectorAll('.footer p');
                pageElements.forEach(el => {
                    el.innerHTML = el.innerHTML.replace('{PAGENO}', '1').replace('{nbpg}', totalPages);
                });
            });
        </script>
    </body>
    </html>
    <?php
}