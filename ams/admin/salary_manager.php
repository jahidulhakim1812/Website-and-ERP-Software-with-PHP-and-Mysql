<?php
/**
 * salary_manager.php
 * Manage Monthly Employee Salary Payments - PERFECTED VERSION
 * UPDATED: Now fetches and displays employee 'photo' correctly with consistent dark mode.
 * ADDED: Advanced filter system for monthly, yearly, custom, and all-time views.
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

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// Get User Photo for Header (Admin Profile)
$userPhoto = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();
        $userPhoto = $userData['avatar'] ?? '';
    } catch (Exception $e) { $userPhoto = null; }
}

// --- 4. DATE FILTER HANDLING ---
$filter = $_GET['range'] ?? 'month'; // Default: This Month
$customStart = $_GET['start_date'] ?? '';
$customEnd = $_GET['end_date'] ?? '';

// Initialize date objects
$currentDate = new DateTime();
$startDate = new DateTime();
$endDate = new DateTime();

// Handle different filter ranges
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
            // Default to current month if custom dates not provided
            $filter = 'month';
            $startDate->modify('first day of this month');
            $endDate->modify('last day of this month');
            $displayRange = 'This Month';
        }
        break;
    case 'all':
        // Set start date to a very old date
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

// Format dates for SQL queries
$startStr = $startDate->format('Y-m-d 00:00:00');
$endStr = $endDate->format('Y-m-d 23:59:59');

// Format for display
$dateDisplay = '';
if ($filter === 'custom' && $customStart && $customEnd) {
    $dateDisplay = $startDate->format('M d, Y') . ' to ' . $endDate->format('M d, Y');
} elseif ($filter === 'today') {
    $dateDisplay = $startDate->format('M d, Y');
} elseif ($filter === 'week') {
    $dateDisplay = $startDate->format('M d') . ' - ' . $endDate->format('M d, Y');
} elseif ($filter === 'month') {
    $dateDisplay = $startDate->format('M Y');
} elseif ($filter === 'year') {
    $dateDisplay = $startDate->format('Y');
} elseif ($filter === 'all') {
    $dateDisplay = 'All Records';
}

// --- 5. HANDLE FORM SUBMISSION (ADD PAYMENT) ---
$message = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_salary'])) {
    $emp_id = $_POST['employee_id'];
    $month  = $_POST['month_year']; // Format: YYYY-MM
    $date   = $_POST['payment_date'];
    $amount = $_POST['amount'];
    $method = $_POST['method'];
    $note   = trim($_POST['note']);

    if ($emp_id && $amount && $date) {
        try {
            // Check for duplicates
            $checkStmt = $pdo->prepare("SELECT id FROM salary_payments WHERE employee_id = ? AND payment_month = ?");
            $checkStmt->execute([$emp_id, $month]);
            
            if ($checkStmt->rowCount() > 0) {
                $message = "Warning: This employee has already been paid for the selected month.";
                $msgType = "warning";
            } else {
                $sql = "INSERT INTO salary_payments (employee_id, payment_month, payment_date, amount, payment_method, note) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$emp_id, $month, $date, $amount, $method, $note]);

                $message = "Salary payment recorded successfully!";
                $msgType = "success";
                
                // Refresh to show new payment
                header("Location: salary_manager.php?success=1&range=" . urlencode($filter) . "&start_date=" . urlencode($customStart) . "&end_date=" . urlencode($customEnd));
                exit;
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $msgType = "error";
        }
    } else {
        $message = "Please fill in all required fields.";
        $msgType = "error";
    }
}

// Show success message from redirect
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = "Salary payment recorded successfully!";
    $msgType = "success";
}

// --- 6. FETCH DATA ---
// A. Active Employees (For Dropdown)
$employees = [];
try {
    $stmtEmp = $pdo->query("SELECT id, full_name, designation, salary, photo FROM employees WHERE status = 'Active' ORDER BY full_name ASC");
    $employees = $stmtEmp->fetchAll();
} catch (Exception $e) { 
    try {
        $stmtEmp = $pdo->query("SELECT id, full_name, designation, salary FROM employees WHERE status = 'Active' ORDER BY full_name ASC");
        $employees = $stmtEmp->fetchAll();
    } catch(Exception $ex) {}
}

// B. Payment History (For Table) - WITH FILTER
$history = [];
try {
    $sqlHist = "SELECT 
                    sp.id, sp.payment_month, sp.payment_date, sp.amount, sp.payment_method, sp.note,
                    e.full_name AS emp_name, 
                    e.designation AS emp_position,
                    e.photo AS emp_photo
                FROM salary_payments sp 
                JOIN employees e ON sp.employee_id = e.id 
                WHERE sp.payment_date >= ? AND sp.payment_date <= ?
                ORDER BY sp.payment_date DESC, sp.id DESC LIMIT 100";
    $stmtHist = $pdo->prepare($sqlHist);
    $stmtHist->execute([$startStr, $endStr]);
    $history = $stmtHist->fetchAll();
} catch (Exception $e) { 
    try {
        $sqlHist = "SELECT 
                        sp.id, sp.payment_month, sp.payment_date, sp.amount, sp.payment_method, sp.note,
                        e.full_name AS emp_name, 
                        e.designation AS emp_position
                    FROM salary_payments sp 
                    JOIN employees e ON sp.employee_id = e.id 
                    WHERE sp.payment_date >= ? AND sp.payment_date <= ?
                    ORDER BY sp.payment_date DESC, sp.id DESC LIMIT 100";
        $stmtHist = $pdo->prepare($sqlHist);
        $stmtHist->execute([$startStr, $endStr]);
        $history = $stmtHist->fetchAll();
    } catch(Exception $ex) {}
}

// Get total salary payments for the selected period
$periodTotal = 0;
$paymentCount = count($history);
try {
    $stmtTotal = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM salary_payments WHERE payment_date >= ? AND payment_date <= ?");
    $stmtTotal->execute([$startStr, $endStr]);
    $totalData = $stmtTotal->fetch();
    $periodTotal = $totalData['total'] ?? 0;
} catch (Exception $e) {
    $periodTotal = 0;
}

// Get average salary per employee for the period
$avgSalary = 0;
if ($paymentCount > 0) {
    $avgSalary = $periodTotal / $paymentCount;
}

// Get monthly trend data for line chart (last 6 months)
$monthlyTrend = [];
$trendLabels = [];
$trendData = [];

if ($filter === 'all' || $filter === 'year') {
    // Get data for the last 12 months for trend
    for ($i = 5; $i >= 0; $i--) {
        $month = new DateTime();
        $month->modify("-$i months");
        $monthStart = $month->format('Y-m-01');
        $monthEnd = $month->format('Y-m-t');
        
        $trendLabels[] = $month->format('M Y');
        
        try {
            $stmtTrend = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM salary_payments WHERE payment_date >= ? AND payment_date <= ?");
            $stmtTrend->execute([$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
            $trendResult = $stmtTrend->fetch();
            $trendData[] = (float)$trendResult['total'];
        } catch (Exception $e) {
            $trendData[] = 0;
        }
    }
}

// Prepare data for JSON (for JavaScript charts)
$jsTrendLabels = json_encode($trendLabels);
$jsTrendData = json_encode($trendData);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Manager | Nexus</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* --- PERFECT CSS FROM DASHBOARD --- */
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338ca;
            --bg-body: #F3F4F6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --sidebar-width: 280px;
            --sidebar-bg: #111827;
            --sidebar-text: #E5E7EB;
            --header-height: 64px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 12px;
            
            /* Financial Colors */
            --color-revenue: #10B981;
            --color-hold: #F59E0B;
            --color-expense: #EF4444;
            --color-profit: #3B82F6;
            --color-salary: #8B5CF6;
            --color-chart: #8B5CF6;
            
            /* Alert Colors */
            --success-bg: #D1FAE5; --success-text: #065F46;
            --error-bg: #FEE2E2; --error-text: #991B1B;
            --warning-bg: #FEF3C7; --warning-text: #92400E;
        }

        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --sidebar-bg: #020617;
            --primary: #6366f1;
            --success-bg: #064e3b; --success-text: #ecfdf5;
            --error-bg: #7f1d1d; --error-text: #fecaca;
            --warning-bg: #78350f; --warning-text: #fef3c7;
            --color-chart: #a78bfa;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
        }
        
        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            height: 100vh; 
            display: flex; 
            overflow: hidden; 
        }
        
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button { font-family: inherit; cursor: pointer; }

        /* --- Sidebar & Layout Logic --- */
        body.sidebar-collapsed { --sidebar-width: 80px; }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            transition: width var(--transition), transform var(--transition);
            z-index: 50;
            flex-shrink: 0;
            white-space: nowrap;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            gap: 12px;
            overflow: hidden;
        }

        body.sidebar-collapsed .logo-text,
        body.sidebar-collapsed .link-text,
        body.sidebar-collapsed .arrow-icon { display: none; opacity: 0; }
        
        body.sidebar-collapsed .sidebar-header { justify-content: center; padding: 0; }
        body.sidebar-collapsed .menu-link { justify-content: center; padding: 12px 0; }
        body.sidebar-collapsed .link-content { gap: 0; }
        body.sidebar-collapsed .menu-icon { font-size: 1.5rem; margin: 0; }
        body.sidebar-collapsed .submenu { display: none !important; }

        .sidebar-menu { 
            padding: 20px 12px; 
            overflow-y: auto; 
            overflow-x: hidden; 
            flex: 1; 
        }
        
        .menu-item { 
            margin-bottom: 4px; 
        }
        
        .menu-link {
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            padding: 12px 16px; 
            border-radius: 8px;
            color: rgba(255,255,255,0.7); 
            cursor: pointer; 
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        
        .menu-link:hover, .menu-link.active { 
            background-color: rgba(255,255,255,0.1); 
            color: #fff; 
            transform: translateX(2px);
        }
        
        .link-content { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        
        .menu-icon { 
            font-size: 1.2rem; 
            min-width: 24px; 
            text-align: center; 
            transition: transform 0.2s ease;
        }
        
        .arrow-icon { 
            transition: transform 0.3s ease; 
            font-size: 0.8rem; 
            opacity: 0.7; 
        }

        .submenu {
            max-height: 0; 
            overflow: hidden; 
            transition: max-height 0.3s ease-in-out; 
            padding-left: 12px;
        }
        
        .menu-item.open > .submenu { 
            max-height: 500px; 
        }
        
        .menu-item.open > .menu-link .arrow-icon { 
            transform: rotate(180deg); 
        }
        
        .menu-item.open > .menu-link { 
            color: #fff; 
        }
        
        .submenu-link {
            display: block; 
            padding: 10px 16px 10px 42px;
            color: rgba(255,255,255,0.5); 
            font-size: 0.9rem; 
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .submenu-link:hover { 
            color: #fff; 
            background: rgba(255,255,255,0.05); 
            transform: translateX(2px);
        }

        /* --- Main Content --- */
        .main-content { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
            position: relative; 
        }

        .top-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            padding: 0 32px; 
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .toggle-btn { 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            cursor: pointer; 
            color: var(--text-muted); 
            display: flex; 
            align-items: center;
            transition: all 0.2s ease;
            padding: 8px;
            border-radius: 8px;
        }
        
        .toggle-btn:hover { 
            color: var(--primary); 
            background: var(--bg-body);
            transform: translateY(-1px);
        }

        /* --- Profile Dropdown --- */
        .header-right { 
            display: flex; 
            align-items: center; 
            gap: 24px; 
        }
        
        .profile-container { 
            position: relative; 
        }
        
        .profile-menu { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            cursor: pointer; 
            padding: 8px 12px; 
            border-radius: 12px; 
            transition: all 0.2s ease;
        }
        
        .profile-menu:hover { 
            background-color: var(--bg-body); 
        }
        
        .profile-info { 
            text-align: right; 
            line-height: 1.2; 
        }
        
        .profile-name { 
            font-size: 0.9rem; 
            font-weight: 600; 
            display: block; 
        }
        
        .profile-role { 
            font-size: 0.75rem; 
            color: var(--text-muted); 
        }
        
        .profile-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 12px; 
            object-fit: cover; 
            border: 2px solid var(--border); 
            transition: all 0.2s ease;
        }
        
        .profile-placeholder { 
            width: 42px; 
            height: 42px; 
            border-radius: 12px; 
            background: var(--primary); 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            font-size: 1.1rem;
        }

        .dropdown-menu {
            position: absolute; 
            top: calc(100% + 8px); 
            right: 0; 
            width: 220px;
            background: var(--bg-card); 
            border: 1px solid var(--border);
            border-radius: 12px; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
            padding: 8px; 
            z-index: 1000; 
            display: none; 
            flex-direction: column; 
            gap: 4px;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-menu.show { 
            display: flex; 
        }
        
        .dropdown-item {
            display: flex; 
            align-items: center; 
            gap: 10px; 
            padding: 12px 16px;
            font-size: 0.9rem; 
            color: var(--text-main); 
            border-radius: 8px; 
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover { 
            background-color: var(--bg-body); 
            color: var(--primary); 
            transform: translateX(2px);
        }
        
        .dropdown-item.danger:hover { 
            background-color: rgba(239, 68, 68, 0.1); 
            color: #ef4444; 
        }

        /* --- Page Content --- */
        .scrollable { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }
        
        /* Filter Section */
        .filter-section {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .filter-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-form {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .filter-select {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.9rem;
            outline: none;
            cursor: pointer;
            min-width: 140px;
        }
        
        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .date-range-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .date-input {
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.85rem;
            width: 140px;
        }
        
        .date-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .filter-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .filter-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .reset-btn {
            background: var(--bg-body);
            color: var(--text-muted);
            border: 1px solid var(--border);
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .reset-btn:hover {
            background: var(--bg-card);
            color: var(--text-main);
        }
        
        .current-range {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: rgba(79, 70, 229, 0.1);
            border-radius: 8px;
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 500;
            margin-top: 12px;
        }
        
        /* Stats Summary Cards */
        .stats-summary {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        
        .stat-card-mini {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }
        
        .stat-card-mini:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        
        .stat-icon-mini {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-content-mini {
            flex: 1;
        }
        
        .stat-label-mini {
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .stat-value-mini {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-main);
        }
        
        /* Chart Container */
        .chart-container {
            height: 200px;
            width: 100%;
            position: relative;
            margin-top: 16px;
        }
        
        /* Main Grid Layout */
        .grid-split { 
            display: grid; 
            grid-template-columns: 350px 1fr; 
            gap: 24px; 
        }
        
        .card { 
            background: var(--bg-card); 
            border-radius: var(--radius); 
            padding: 28px; 
            border: 1px solid var(--border); 
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        
        .card-header { 
            font-size: 1.1rem; 
            font-weight: 600; 
            margin-bottom: 24px; 
            padding-bottom: 16px; 
            border-bottom: 1px solid var(--border); 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            color: var(--text-main);
        }
        
        .card-header i {
            color: var(--primary);
        }

        /* Form Styles */
        .form-group { 
            margin-bottom: 20px; 
        }
        
        .label { 
            display: block; 
            font-size: 0.85rem; 
            font-weight: 500; 
            margin-bottom: 8px; 
            color: var(--text-main); 
        }
        
        .input, .select, .textarea { 
            width: 100%; 
            padding: 12px 14px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            background: var(--bg-body); 
            color: var(--text-main); 
            font-size: 0.9rem; 
            outline: none; 
            transition: all 0.2s ease;
        }
        
        .input:focus, .select:focus, .textarea:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); 
        }
        
        .btn { 
            width: 100%; 
            padding: 14px; 
            background: var(--primary); 
            color: #fff; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            gap: 8px; 
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        
        .btn:hover { 
            background: var(--primary-hover); 
            transform: translateY(-1px);
        }
        
        .btn:active {
            transform: translateY(0);
        }

        /* Table Styles */
        .table-responsive { 
            overflow-x: auto; 
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        table { 
            width: 100%; 
            border-collapse: collapse; 
            font-size: 0.9rem; 
            min-width: 600px;
        }
        
        th { 
            text-align: left; 
            padding: 16px 20px; 
            background: var(--bg-body); 
            color: var(--text-muted); 
            font-weight: 600; 
            font-size: 0.8rem; 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--border); 
        }
        
        td { 
            padding: 16px 20px; 
            border-bottom: 1px solid var(--border); 
            vertical-align: middle; 
        }
        
        tr:hover {
            background: var(--bg-body);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        /* User Cell */
        .user-cell { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        
        .user-avatar { 
            width: 40px; 
            height: 40px; 
            border-radius: 50%; 
            background: var(--primary); 
            color: #fff; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            font-size: 0.85rem; 
            object-fit: cover; 
            border: 2px solid var(--border); 
        }
        
        .user-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .user-role {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Alert Messages */
        .alert { 
            padding: 16px 20px; 
            border-radius: 8px; 
            margin-bottom: 24px; 
            font-size: 0.9rem; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert.success { 
            background: var(--success-bg); 
            color: var(--success-text); 
            border: 1px solid rgba(16, 185, 129, 0.3); 
        }
        
        .alert.error { 
            background: var(--error-bg); 
            color: var(--error-text); 
            border: 1px solid rgba(239, 68, 68, 0.3); 
        }
        
        .alert.warning { 
            background: var(--warning-bg); 
            color: var(--warning-text); 
            border: 1px solid rgba(245, 158, 11, 0.3); 
        }

        /* Theme Toggle */
        #themeToggle {
            background: var(--bg-card);
            border: 1px solid var(--border);
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: var(--text-muted);
            font-size: 1.2rem;
        }
        
        #themeToggle:hover {
            transform: rotate(15deg);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Overlay */
        .overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            backdrop-filter: blur(2px);
            z-index: 45; 
            display: none; 
            animation: fadeIn 0.3s ease;
        }
        
        /* Scrollbar */
        .scrollable::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollable::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .scrollable::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }
        
        .scrollable::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
        
        /* Amount Badge */
        .amount-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--color-revenue);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .month-badge {
            background: rgba(139, 92, 246, 0.1);
            color: var(--color-salary);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .method-badge {
            background: var(--bg-body);
            color: var(--text-muted);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
        }
        
        /* Summary Card */
        .summary-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
        }
        
        .summary-title {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .summary-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }
        
        .summary-subtitle {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats-summary {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .grid-split {
                grid-template-columns: 1fr;
            }
            
            .summary-value {
                font-size: 1.7rem;
            }
        }
        
        @media (max-width: 768px) {
            body.sidebar-collapsed { --sidebar-width: 280px; } 
            .sidebar { 
                position: fixed; 
                left: -280px; 
                height: 100%; 
                top: 0;
            }
            
            body.mobile-open .sidebar { 
                transform: translateX(280px); 
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.3);
            }
            
            body.mobile-open .overlay { 
                display: block; 
            }
            
            .logo-text, .link-text, .arrow-icon { 
                display: inline !important; 
                opacity: 1 !important; 
            }
            
            .sidebar-header { 
                justify-content: flex-start !important; 
                padding: 0 24px !important; 
            }
            
            .top-header { 
                padding: 0 20px; 
            }
            
            .scrollable { 
                padding: 24px 20px; 
            }
            
            .stats-summary {
                grid-template-columns: 1fr;
            }
            
            .profile-info { 
                display: none; 
            }
            
            .card {
                padding: 20px;
            }
            
            .stat-value-mini {
                font-size: 1.2rem;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .date-range-container {
                flex-direction: column;
            }
            
            .date-input {
                width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .summary-value {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .scrollable {
                padding: 16px;
            }
            
            .card {
                padding: 16px;
            }
            
            .top-header {
                padding: 0 12px;
            }
            
            th, td {
                padding: 12px 14px;
            }
            
            .summary-card {
                padding: 20px;
            }
            
            .summary-value {
                font-size: 1.3rem;
            }
        }

        /* Loading State */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        /* Smooth Transitions */
        * {
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        
        /* Page Header */
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
        }
        
        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        /* No Data Message */
        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .no-data p {
            font-size: 0.9rem;
        }
        
        /* Filter Actions */
        .filter-actions {
            display: flex;
            gap: 8px;
        }
        
        /* Export Button */
        .export-btn {
            background: var(--color-revenue);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .export-btn:hover {
            background: #0da271;
            transform: translateY(-1px);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        
        /* Quick Filter Buttons */
        .quick-filters {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }
        
        .quick-filter-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-body);
            color: var(--text-muted);
            border: 1px solid var(--border);
        }
        
        .quick-filter-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .quick-filter-btn:hover:not(.active) {
            background: var(--bg-card);
            color: var(--text-main);
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

       <?php include 'sidenavbar.php'; ?>
    <main class="main-content">
        
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Salary Management</span>
                </div>
            </div>

            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($_SESSION['username']); ?></span>
                            <span class="profile-role"><?php echo ucfirst(e($_SESSION['role'])); ?></span>
                        </div>
                        <?php 
                        // Get user photo from database
                        $userPhoto = '';
                        if (isset($_SESSION['user_id'])) {
                            try {
                                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                $userData = $stmt->fetch();
                                $userPhoto = $userData['avatar'] ?? '';
                            } catch (Exception $e) {
                                $userPhoto = '';
                            }
                        }
                        
                        if (!empty($userPhoto)): ?>
                            <img src="<?php echo e($userPhoto); ?>" alt="Profile" class="profile-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="profile-placeholder" style="display: none;">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                            </div>
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>                          
                    </div>

                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile_settings.php" class="dropdown-item">
                            <i class="ph ph-user-gear" style="font-size: 1.1rem;"></i> 
                            <span>Profile Settings</span>
                        </a>
                        <div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>
                        <a href="logout.php" class="dropdown-item danger">
                            <i class="ph ph-sign-out" style="font-size: 1.1rem;"></i> 
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <div class="scrollable">
            <div class="page-header">
                <h1 class="page-title">Salary Manager</h1>
                <p class="page-subtitle">Process monthly payments, track history, and manage employee compensation.</p>
            </div>

            <?php if($message): ?>
                <div class="alert <?php echo $msgType; ?>">
                    <i class="ph ph-<?php echo $msgType === 'success' ? 'check-circle' : ($msgType === 'warning' ? 'warning-circle' : 'x-circle'); ?>" style="font-size:1.2rem;"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Summary Card -->
            <div class="summary-card">
                <div class="summary-title">
                    <i class="ph ph-currency-dollar"></i>
                    <span>Total Salary Payments</span>
                </div>
                <div class="summary-value">$<?php echo number_format($periodTotal, 2); ?></div>
                <div class="summary-subtitle"><?php echo $displayRange; ?> • <?php echo $paymentCount; ?> payments</div>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="filter-header">
                    <div class="filter-title">
                        <i class="ph ph-funnel"></i>
                        <span>Filter Payments</span>
                    </div>
                    <div class="filter-actions">
                        <button class="export-btn" onclick="exportData()">
                            <i class="ph ph-export"></i>
                            <span>Export</span>
                        </button>
                    </div>
                </div>
                
                <form method="GET" class="filter-form" id="filterForm">
                    <!-- Quick Filter Buttons -->
                    <div class="quick-filters">
                        <button type="button" class="quick-filter-btn <?php echo $filter === 'today' ? 'active' : ''; ?>" onclick="setFilter('today')">Today</button>
                        <button type="button" class="quick-filter-btn <?php echo $filter === 'week' ? 'active' : ''; ?>" onclick="setFilter('week')">Last 7 Days</button>
                        <button type="button" class="quick-filter-btn <?php echo $filter === 'month' ? 'active' : ''; ?>" onclick="setFilter('month')">This Month</button>
                        <button type="button" class="quick-filter-btn <?php echo $filter === 'year' ? 'active' : ''; ?>" onclick="setFilter('year')">This Year</button>
                        <button type="button" class="quick-filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>" onclick="setFilter('all')">All Time</button>
                        <button type="button" class="quick-filter-btn <?php echo $filter === 'custom' ? 'active' : ''; ?>" onclick="showCustomDates()">Custom Range</button>
                    </div>
                    
                    <div style="display: flex; gap: 12px; width: 100%;">
                        <select name="range" class="filter-select" id="rangeSelect" onchange="toggleCustomDates()">
                            <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="week" <?php echo $filter === 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                            <option value="month" <?php echo $filter === 'month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="year" <?php echo $filter === 'year' ? 'selected' : ''; ?>>This Year</option>
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                            <option value="custom" <?php echo $filter === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                        </select>
                        
                        <div class="date-range-container" id="customDates" style="display: <?php echo $filter === 'custom' ? 'flex' : 'none'; ?>; flex: 1;">
                            <input type="text" name="start_date" class="date-input" id="startDate" 
                                   placeholder="Start Date" value="<?php echo $customStart ?: $startDate->format('Y-m-d'); ?>">
                            <span style="color: var(--text-muted); align-self: center;">to</span>
                            <input type="text" name="end_date" class="date-input" id="endDate" 
                                   placeholder="End Date" value="<?php echo $customEnd ?: $endDate->format('Y-m-d'); ?>">
                        </div>
                        
                        <button type="submit" class="filter-btn">
                            <i class="ph ph-magnifying-glass"></i>
                            <span>Apply Filter</span>
                        </button>
                        
                        <button type="button" class="reset-btn" onclick="resetFilter()">
                            <i class="ph ph-arrow-clockwise"></i>
                            <span>Reset</span>
                        </button>
                    </div>
                </form>
                
                <?php if($dateDisplay): ?>
                <div class="current-range">
                    <i class="ph ph-calendar"></i>
                    <span>Currently viewing: <?php echo $dateDisplay; ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats Summary -->
            <div class="stats-summary">
                <div class="stat-card-mini">
                    <div class="stat-icon-mini" style="background: rgba(16, 185, 129, 0.12); color: var(--color-revenue);">
                        <i class="ph ph-users"></i>
                    </div>
                    <div class="stat-content-mini">
                        <div class="stat-label-mini">Active Employees</div>
                        <div class="stat-value-mini"><?php echo count($employees); ?></div>
                    </div>
                </div>
                
                <div class="stat-card-mini">
                    <div class="stat-icon-mini" style="background: rgba(139, 92, 246, 0.12); color: var(--color-salary);">
                        <i class="ph ph-currency-dollar"></i>
                    </div>
                    <div class="stat-content-mini">
                        <div class="stat-label-mini">Period Total</div>
                        <div class="stat-value-mini">$<?php echo number_format($periodTotal, 2); ?></div>
                    </div>
                </div>
                
                <div class="stat-card-mini">
                    <div class="stat-icon-mini" style="background: rgba(59, 130, 246, 0.12); color: var(--color-profit);">
                        <i class="ph ph-clock-counter-clockwise"></i>
                    </div>
                    <div class="stat-content-mini">
                        <div class="stat-label-mini">Total Payments</div>
                        <div class="stat-value-mini"><?php echo $paymentCount; ?></div>
                    </div>
                </div>
                
                <div class="stat-card-mini">
                    <div class="stat-icon-mini" style="background: rgba(245, 158, 11, 0.12); color: var(--color-hold);">
                        <i class="ph ph-trend-up"></i>
                    </div>
                    <div class="stat-content-mini">
                        <div class="stat-label-mini">Average Payment</div>
                        <div class="stat-value-mini">$<?php echo number_format($avgSalary, 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Trend Chart (for year/all filters) -->
            <?php if (!empty($trendData) && array_sum($trendData) > 0): ?>
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <i class="ph ph-chart-line"></i>
                    <span>Salary Trend (Last 6 Months)</span>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <?php endif; ?>

            <div class="grid-split">
                
                <!-- Process Payment Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="ph ph-wallet"></i>
                        <span>Process Payment</span>
                    </div>

                    <form method="POST" id="salaryForm">
                        <div class="form-group">
                            <label class="label">Employee *</label>
                            <select name="employee_id" id="employeeSelect" class="select" required onchange="fillSalary()">
                                <option value="">-- Select Employee --</option>
                                <?php foreach($employees as $emp): ?>
                                    <option value="<?php echo $emp['id']; ?>" 
                                            data-salary="<?php echo $emp['salary']; ?>"
                                            data-pos="<?php echo e($emp['designation']); ?>">
                                        <?php echo e($emp['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div style="margin-top: 6px; font-size: 0.8rem; color: var(--text-muted);" id="positionHint">
                                Designation: -
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="label">Salary Month *</label>
                            <input type="month" name="month_year" class="input" value="<?php echo date('Y-m'); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="label">Payment Date *</label>
                            <input type="date" name="payment_date" class="input" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="label">Amount *</label>
                            <div style="position: relative;">
                                <span style="position: absolute; left: 14px; top: 13px; color: var(--text-muted);">$</span>
                                <input type="number" step="0.01" min="0" name="amount" id="salaryAmount" class="input" style="padding-left: 30px;" placeholder="0.00" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="label">Payment Method</label>
                            <select name="method" class="select">
                                <option value="Cash">Cash</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="Check">Check</option>
                                <option value="Mobile Money">Mobile Money</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="label">Note (Optional)</label>
                            <textarea name="note" class="textarea" rows="2" placeholder="Bonus, deductions, or any additional notes..."></textarea>
                        </div>

                        <button type="submit" name="pay_salary" class="btn">
                            <i class="ph ph-check-circle"></i> Confirm Payment
                        </button>
                    </form>
                </div>

                <!-- Recent Payments Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="ph ph-clock-counter-clockwise"></i>
                        <span>Payment History (<?php echo $displayRange; ?>)</span>
                        <span style="margin-left: auto; font-size: 0.8rem; color: var(--text-muted);">
                            <?php echo $paymentCount; ?> records
                        </span>
                    </div>

                    <div class="table-responsive">
                        <?php if (count($history) > 0): ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Month</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($history as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <?php if(!empty($row['emp_photo']) && isset($row['emp_photo'])): ?>
                                                    <img src="uploads/<?php echo e($row['emp_photo']); ?>" alt="<?php echo e($row['emp_name']); ?>" class="user-avatar">
                                                <?php else: ?>
                                                    <div class="user-avatar">
                                                        <?php echo strtoupper(substr($row['emp_name'], 0, 1)); ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="user-info">
                                                    <div class="user-name"><?php echo e($row['emp_name']); ?></div>
                                                    <div class="user-role"><?php echo e($row['emp_position']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="month-badge">
                                                <?php echo date('M Y', strtotime($row['payment_month'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y', strtotime($row['payment_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="amount-badge">
                                                <i class="ph ph-currency-dollar"></i>
                                                $<?php echo number_format($row['amount'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="method-badge">
                                                <?php echo e($row['payment_method']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="ph ph-file-text"></i>
                                <p>No payment records found for <?php echo strtolower($displayRange); ?>.<br>Process a salary payment to get started.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($paymentCount > 10): ?>
                    <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border); text-align: center;">
                        <div style="color: var(--text-muted); font-size: 0.85rem;">
                            Showing <?php echo min(100, $paymentCount); ?> of <?php echo $paymentCount; ?> payments
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <script>
        // --- 1. Sidebar Toggle Logic ---
        const sidebarToggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('overlay');
        const body = document.body;

        function handleSidebarToggle() {
            if (window.innerWidth <= 768) {
                body.classList.toggle('mobile-open');
                body.classList.remove('sidebar-collapsed'); 
            } else {
                body.classList.toggle('sidebar-collapsed');
                if(body.classList.contains('sidebar-collapsed')) {
                    document.querySelectorAll('.menu-item.open').forEach(item => item.classList.remove('open'));
                }
            }
        }

        sidebarToggle.addEventListener('click', handleSidebarToggle);
        overlay.addEventListener('click', () => {
            body.classList.remove('mobile-open');
            closeAllDropdowns();
        });

        // --- 2. Accordion Logic ---
        const menuItems = document.querySelectorAll('.has-submenu');
        menuItems.forEach(item => {
            const link = item.querySelector('.menu-link');
            link.addEventListener('click', (e) => {
                if(body.classList.contains('sidebar-collapsed')) {
                    body.classList.remove('sidebar-collapsed');
                    setTimeout(() => { item.classList.add('open'); }, 100);
                    return;
                }
                
                e.preventDefault();
                const isOpen = item.classList.contains('open');
                menuItems.forEach(i => i.classList.remove('open')); 
                if (!isOpen) item.classList.add('open');
            });
        });

        // --- 3. Profile Dropdown Logic ---
        function toggleProfileMenu() {
            const dropdown = document.getElementById('profileDropdown');
            const isShown = dropdown.classList.contains('show');
            closeAllDropdowns();
            if (!isShown) dropdown.classList.add('show');
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.remove('show'));
        }

        window.addEventListener('click', function(e) {
            if (!document.getElementById('profileContainer').contains(e.target)) {
                closeAllDropdowns();
            }
        });

        // --- 4. Dark Mode ---
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        // Check for saved theme preference or default to dark if system prefers dark
        const prefersDarkScheme = window.matchMedia('(prefers-color-scheme: dark)');
        const savedTheme = localStorage.getItem('theme');
        
        if (savedTheme === 'dark' || (!savedTheme && prefersDarkScheme.matches)) {
            document.documentElement.setAttribute('data-theme', 'dark');
            themeIcon.classList.replace('ph-moon', 'ph-sun');
        } else {
            document.documentElement.removeAttribute('data-theme');
            localStorage.setItem('theme', 'light');
        }

        themeBtn.addEventListener('click', () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if(isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                themeIcon.classList.replace('ph-sun', 'ph-moon');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                themeIcon.classList.replace('ph-moon', 'ph-sun');
            }
            
            // Add animation to icon
            themeIcon.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                themeIcon.style.transform = 'rotate(0deg)';
            }, 300);
        });

        // --- 5. Filter Functions ---
        function toggleCustomDates() {
            const rangeSelect = document.getElementById('rangeSelect');
            const customDates = document.getElementById('customDates');
            
            if (rangeSelect.value === 'custom') {
                customDates.style.display = 'flex';
                // Initialize date pickers
                initDatePickers();
            } else {
                customDates.style.display = 'none';
            }
        }
        
        function showCustomDates() {
            const rangeSelect = document.getElementById('rangeSelect');
            rangeSelect.value = 'custom';
            toggleCustomDates();
            document.getElementById('startDate').focus();
        }
        
        function setFilter(range) {
            const rangeSelect = document.getElementById('rangeSelect');
            rangeSelect.value = range;
            
            if (range === 'custom') {
                showCustomDates();
            } else {
                toggleCustomDates();
                // Submit form for predefined ranges
                document.getElementById('filterForm').submit();
            }
        }
        
        function resetFilter() {
            window.location.href = 'salary_manager.php';
        }
        
        function exportData() {
            const range = document.getElementById('rangeSelect').value;
            const startDate = document.getElementById('startDate')?.value || '';
            const endDate = document.getElementById('endDate')?.value || '';
            
            let url = 'export_salary.php?range=' + encodeURIComponent(range);
            if (range === 'custom' && startDate && endDate) {
                url += '&start_date=' + encodeURIComponent(startDate) + '&end_date=' + encodeURIComponent(endDate);
            }
            
            window.open(url, '_blank');
        }
        
        // --- 6. Date Pickers ---
        function initDatePickers() {
            // Initialize flatpickr for date inputs
            const startDatePicker = flatpickr("#startDate", {
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        endDatePicker.set('minDate', dateStr);
                    }
                }
            });
            
            const endDatePicker = flatpickr("#endDate", {
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        startDatePicker.set('maxDate', dateStr);
                    }
                }
            });
            
            // Set initial date limits
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('startDate').max = today;
            document.getElementById('endDate').max = today;
            
            // If custom dates are set, apply min/max
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (startDate) {
                endDatePicker.set('minDate', startDate);
            }
            if (endDate) {
                startDatePicker.set('maxDate', endDate);
            }
        }
        
        // Initialize date pickers if custom filter is active
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('rangeSelect').value === 'custom') {
                initDatePickers();
            }
        });

        // --- 7. Auto Fill Salary ---
        function fillSalary() {
            const select = document.getElementById('employeeSelect');
            const amountInput = document.getElementById('salaryAmount');
            const hint = document.getElementById('positionHint');
            
            const selectedOption = select.options[select.selectedIndex];
            const baseSalary = selectedOption.getAttribute('data-salary');
            const designation = selectedOption.getAttribute('data-pos');
            
            if(baseSalary) {
                amountInput.value = baseSalary;
                hint.innerHTML = `<span style="color: var(--color-revenue); font-weight: 600;">Designation:</span> ${designation || "Unknown"}`;
            } else {
                amountInput.value = '';
                hint.innerHTML = `<span style="color: var(--text-muted);">Designation:</span> -`;
            }
        }

        // --- 8. Form Validation and Auto-fill ---
        document.addEventListener('DOMContentLoaded', function() {
            // Set default dates
            const today = new Date().toISOString().split('T')[0];
            const paymentDateInput = document.querySelector('input[name="payment_date"]');
            const monthInput = document.querySelector('input[name="month_year"]');
            
            if (paymentDateInput && !paymentDateInput.value) {
                paymentDateInput.value = today;
            }
            
            if (monthInput && !monthInput.value) {
                const currentMonth = new Date().toISOString().slice(0, 7);
                monthInput.value = currentMonth;
            }
            
            // Add form validation
            const form = document.getElementById('salaryForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const amount = document.getElementById('salaryAmount').value;
                    const employee = document.getElementById('employeeSelect').value;
                    
                    if (!employee) {
                        e.preventDefault();
                        alert('Please select an employee.');
                        return false;
                    }
                    
                    if (!amount || parseFloat(amount) <= 0) {
                        e.preventDefault();
                        alert('Please enter a valid amount.');
                        return false;
                    }
                    
                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    submitBtn.innerHTML = '<i class="ph ph-circle-notch ph-spin"></i> Processing...';
                    submitBtn.disabled = true;
                    
                    return true;
                });
            }
            
            // Listen for system theme changes
            prefersDarkScheme.addEventListener('change', function(e) {
                if (!localStorage.getItem('theme')) {
                    if (e.matches) {
                        document.documentElement.setAttribute('data-theme', 'dark');
                        themeIcon.classList.replace('ph-moon', 'ph-sun');
                    } else {
                        document.documentElement.removeAttribute('data-theme');
                        themeIcon.classList.replace('ph-sun', 'ph-moon');
                    }
                }
            });
            
            // Focus first input
            const firstInput = document.querySelector('#employeeSelect');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // --- 9. Auto-hide alert after 5 seconds ---
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.querySelector('.alert');
            if (alert) {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });

        // --- 10. Prevent form resubmission on page refresh ---
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
        
        // --- 11. Trend Chart ---
        <?php if (!empty($trendData) && array_sum($trendData) > 0): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('trendChart').getContext('2d');
            const labels = <?php echo $jsTrendLabels; ?>;
            const data = <?php echo $jsTrendData; ?>;
            
            // Create gradient
            const gradient = ctx.createLinearGradient(0, 0, 0, 200);
            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                gradient.addColorStop(0, 'rgba(139, 92, 246, 0.4)');
                gradient.addColorStop(1, 'rgba(139, 92, 246, 0.05)');
            } else {
                gradient.addColorStop(0, 'rgba(139, 92, 246, 0.3)');
                gradient.addColorStop(1, 'rgba(139, 92, 246, 0.05)');
            }
            
            const trendChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Salary Payments',
                        data: data,
                        borderColor: '#8B5CF6',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#8B5CF6',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: 'var(--bg-card)',
                            titleColor: 'var(--text-main)',
                            bodyColor: 'var(--text-muted)',
                            borderColor: 'var(--border)',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            boxPadding: 6,
                            callbacks: {
                                label: function(context) {
                                    return 'Total: $' + context.parsed.y.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                },
                                color: 'var(--text-muted)',
                                padding: 10
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                borderDash: [5, 5],
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                },
                                color: 'var(--text-muted)',
                                padding: 10,
                                callback: function(value) {
                                    if (value >= 1000) {
                                        return '$' + (value / 1000).toFixed(1) + 'k';
                                    }
                                    return '$' + value;
                                }
                            }
                        }
                    },
                    interaction: {
                        mode: 'nearest',
                        axis: 'x',
                        intersect: false
                    },
                    elements: {
                        line: {
                            tension: 0.4
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>