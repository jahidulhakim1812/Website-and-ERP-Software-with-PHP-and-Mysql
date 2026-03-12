<?php
/**
 * export_salary_ui.php
 * Salary Export UI - Choose format and options
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

// Get filter parameters from URL
$filter = $_GET['range'] ?? 'month';
$customStart = $_GET['start_date'] ?? '';
$customEnd = $_GET['end_date'] ?? '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $format = $_POST['format'] ?? 'csv';
    $include_summary = isset($_POST['include_summary']) ? 1 : 0;
    $group_by = $_POST['group_by'] ?? 'none';
    
    // Build export URL
    $exportUrl = "export_salary.php?range=" . urlencode($filter);
    
    if ($filter === 'custom') {
        $exportUrl .= "&start_date=" . urlencode($customStart) . "&end_date=" . urlencode($customEnd);
    }
    
    $exportUrl .= "&format=" . urlencode($format);
    $exportUrl .= "&summary=" . $include_summary;
    $exportUrl .= "&group=" . urlencode($group_by);
    
    // Redirect to export script
    header("Location: " . $exportUrl);
    exit;
}

// Get record count for the current filter
try {
    $startDate = new DateTime();
    $endDate = new DateTime();
    
    switch ($filter) {
        case 'today':
            $startDate->modify('today');
            $endDate = new DateTime('today');
            break;
        case 'week':
            $startDate->modify('-7 days');
            $endDate = new DateTime();
            break;
        case 'month':
            $startDate->modify('first day of this month');
            $endDate->modify('last day of this month');
            break;
        case 'year':
            $startDate->modify('first day of January this year');
            $endDate->modify('last day of December this year');
            break;
        case 'custom':
            if ($customStart && $customEnd) {
                $startDate = new DateTime($customStart);
                $endDate = new DateTime($customEnd);
            }
            break;
        case 'all':
            $startDate->setDate(2000, 1, 1);
            $endDate = new DateTime();
            break;
    }
    
    $startStr = $startDate->format('Y-m-d 00:00:00');
    $endStr = $endDate->format('Y-m-d 23:59:59');
    
    $sqlCount = "SELECT COUNT(*) as count FROM salary_payments WHERE payment_date >= ? AND payment_date <= ?";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute([$startStr, $endStr]);
    $countData = $stmtCount->fetch();
    $recordCount = $countData['count'];
    
    // Get total amount
    $sqlAmount = "SELECT COALESCE(SUM(amount), 0) as total FROM salary_payments WHERE payment_date >= ? AND payment_date <= ?";
    $stmtAmount = $pdo->prepare($sqlAmount);
    $stmtAmount->execute([$startStr, $endStr]);
    $amountData = $stmtAmount->fetch();
    $totalAmount = $amountData['total'];
    
} catch (Exception $e) {
    $recordCount = 0;
    $totalAmount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Salary Data | Nexus</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338ca;
            --bg-body: #F3F4F6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --radius: 12px;
            --success-bg: #D1FAE5;
            --success-text: #065F46;
        }
        
        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --primary: #6366f1;
        }
        
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', sans-serif; 
        }
        
        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .export-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 40px;
            border: 1px solid var(--border);
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        
        .export-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .export-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            color: white;
        }
        
        .export-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
        }
        
        .export-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        
        .stats-card {
            background: var(--bg-body);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        
        .stats-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        
        .stats-row:last-child {
            margin-bottom: 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .stat-value {
            font-weight: 600;
            color: var(--text-main);
        }
        
        .stat-value.amount {
            color: #10B981;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text-main);
        }
        
        .select, .checkbox-group {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            background: var(--bg-body);
            border-radius: 8px;
            border: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .checkbox-container:hover {
            border-color: var(--primary);
        }
        
        .checkbox-container input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-label {
            font-size: 0.9rem;
            color: var(--text-main);
            cursor: pointer;
        }
        
        .format-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .format-option {
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-body);
        }
        
        .format-option:hover {
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .format-option.selected {
            border-color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
        }
        
        .format-icon {
            font-size: 1.5rem;
            margin-bottom: 8px;
            color: var(--primary);
        }
        
        .format-name {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-main);
        }
        
        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 32px;
        }
        
        .btn {
            flex: 1;
            padding: 14px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            font-size: 0.95rem;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-card);
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--primary);
            text-decoration: none;
            font-size: 0.9rem;
            margin-top: 20px;
            transition: all 0.2s ease;
        }
        
        .back-link:hover {
            gap: 10px;
        }
        
        @media (max-width: 768px) {
            .export-container {
                padding: 30px 20px;
            }
            
            .format-options {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .buttons {
                flex-direction: column;
            }
        }
        
        @media (max-width: 480px) {
            .export-container {
                padding: 24px 16px;
            }
            
            .export-title {
                font-size: 1.5rem;
            }
            
            .format-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="export-container">
        <div class="export-header">
            <div class="export-icon">
                <i class="ph ph-export"></i>
            </div>
            <h1 class="export-title">Export Salary Data</h1>
            <p class="export-subtitle">Download salary payment records in your preferred format</p>
        </div>
        
        <div class="stats-card">
            <div class="stats-row">
                <span class="stat-label">Filter Applied:</span>
                <span class="stat-value">
                    <?php 
                    $filterNames = [
                        'today' => 'Today',
                        'week' => 'Last 7 Days',
                        'month' => 'This Month',
                        'year' => 'This Year',
                        'all' => 'All Time',
                        'custom' => 'Custom Range'
                    ];
                    echo $filterNames[$filter] ?? 'This Month';
                    ?>
                </span>
            </div>
            <div class="stats-row">
                <span class="stat-label">Records Found:</span>
                <span class="stat-value"><?php echo number_format($recordCount); ?></span>
            </div>
            <div class="stats-row">
                <span class="stat-label">Total Amount:</span>
                <span class="stat-value amount">$<?php echo number_format($totalAmount, 2); ?></span>
            </div>
        </div>
        
        <form method="POST" id="exportForm">
            <div class="form-group">
                <label class="label">Export Format</label>
                <div class="format-options">
                    <div class="format-option selected" data-format="csv">
                        <div class="format-icon">
                            <i class="ph ph-file-csv"></i>
                        </div>
                        <div class="format-name">CSV</div>
                        <input type="radio" name="format" value="csv" checked style="display: none;">
                    </div>
                    <div class="format-option" data-format="excel">
                        <div class="format-icon">
                            <i class="ph ph-file-xls"></i>
                        </div>
                        <div class="format-name">Excel</div>
                        <input type="radio" name="format" value="excel" style="display: none;">
                    </div>
                    <div class="format-option" data-format="pdf">
                        <div class="format-icon">
                            <i class="ph ph-file-pdf"></i>
                        </div>
                        <div class="format-name">PDF</div>
                        <input type="radio" name="format" value="pdf" style="display: none;">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="label">Group By</label>
                <select name="group_by" class="select">
                    <option value="none">No Grouping</option>
                    <option value="employee">Employee</option>
                    <option value="month">Month</option>
                    <option value="method">Payment Method</option>
                </select>
            </div>
            
            <div class="form-group">
                <div class="checkbox-container">
                    <input type="checkbox" name="include_summary" id="include_summary" checked>
                    <label for="include_summary" class="checkbox-label">Include summary statistics</label>
                </div>
            </div>
            
            <div class="buttons">
                <button type="submit" class="btn btn-primary">
                    <i class="ph ph-download"></i>
                    <span>Download Export</span>
                </button>
                <a href="salary_manager.php?range=<?php echo urlencode($filter); ?>&start_date=<?php echo urlencode($customStart); ?>&end_date=<?php echo urlencode($customEnd); ?>" class="btn btn-secondary">
                    <i class="ph ph-arrow-left"></i>
                    <span>Back to Manager</span>
                </a>
            </div>
        </form>
        
        <div style="text-align: center; margin-top: 20px;">
            <a href="salary_manager.php" class="back-link">
                <i class="ph ph-arrow-left"></i>
                <span>Back to Salary Manager</span>
            </a>
        </div>
    </div>

    <script>
        // Format selection
        document.querySelectorAll('.format-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.format-option').forEach(opt => {
                    opt.classList.remove('selected');
                    opt.querySelector('input[type="radio"]').checked = false;
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                this.querySelector('input[type="radio"]').checked = true;
            });
        });
        
        // Form validation
        document.getElementById('exportForm').addEventListener('submit', function(e) {
            const recordCount = <?php echo $recordCount; ?>;
            
            if (recordCount === 0) {
                e.preventDefault();
                alert('No records found to export. Please adjust your filter settings.');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Preparing Export...';
            submitBtn.disabled = true;
            
            // Re-enable after 2 seconds if something goes wrong
            setTimeout(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }, 2000);
            
            return true;
        });
        
        // Theme detection
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        const savedTheme = localStorage.getItem('theme');
        
        if (savedTheme === 'dark' || (!savedTheme && prefersDarkScheme.matches)) {
            document.documentElement.setAttribute('data-theme', 'dark');
        }
    </script>
</body>
</html>