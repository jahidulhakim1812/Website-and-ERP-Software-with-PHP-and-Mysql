<?php
/**
 * attendance_report.php
 * NexusAdmin V2 - Employee Attendance Report
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
    die("Database Connection Error: " . $ex->getMessage());
}

// --- 3. HANDLE FORM SUBMISSIONS ---
$message = '';
$message_type = '';

// Get employee statistics for selected employee
$employee_id = $_GET['employee_id'] ?? 0;
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month
$search_term = $_GET['search'] ?? '';

// Fetch employee details if employee_id is provided
$employee = null;
$attendance_records = [];
$attendance_stats = [];

if ($employee_id) {
    // Get employee details
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ? AND status = 'Active'");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch();

    if ($employee) {
        // Get attendance records for the date range
        $stmt = $pdo->prepare("
            SELECT * FROM attendance 
            WHERE employee_id = ? AND date BETWEEN ? AND ?
            ORDER BY date DESC
        ");
        $stmt->execute([$employee_id, $start_date, $end_date]);
        $attendance_records = $stmt->fetchAll();

        // Calculate statistics
        $stats_stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_days,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_days,
                COUNT(CASE WHEN status = 'leave' THEN 1 END) as leave_days,
                COUNT(CASE WHEN status = 'weekend' THEN 1 END) as weekend_days,
                COUNT(CASE WHEN status = 'holiday' THEN 1 END) as holiday_days,
                SUM(hours_worked) as total_hours,
                AVG(hours_worked) as avg_hours,
                MIN(date) as first_date,
                MAX(date) as last_date
            FROM attendance 
            WHERE employee_id = ? AND date BETWEEN ? AND ?
        ");
        $stats_stmt->execute([$employee_id, $start_date, $end_date]);
        $attendance_stats = $stats_stmt->fetch();

        // Get monthly summary
        $monthly_stmt = $pdo->prepare("
            SELECT 
                DATE_FORMAT(date, '%Y-%m') as month,
                COUNT(*) as total_days,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                SUM(hours_worked) as total_hours
            FROM attendance 
            WHERE employee_id = ?
            GROUP BY DATE_FORMAT(date, '%Y-%m')
            ORDER BY month DESC
            LIMIT 12
        ");
        $monthly_stmt->execute([$employee_id]);
        $monthly_summary = $monthly_stmt->fetchAll();

        // Get attendance pattern (days of week)
        $pattern_stmt = $pdo->prepare("
            SELECT 
                DAYNAME(date) as day_name,
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late
            FROM attendance 
            WHERE employee_id = ? AND date BETWEEN ? AND ?
            GROUP BY DAYOFWEEK(date), DAYNAME(date)
            ORDER BY DAYOFWEEK(date)
        ");
        $pattern_stmt->execute([$employee_id, $start_date, $end_date]);
        $attendance_pattern = $pattern_stmt->fetchAll();
    }
}

// --- 4. FETCH EMPLOYEES FOR SEARCH ---
$employees = [];
if ($search_term) {
    $sql = "SELECT * FROM employees 
            WHERE (full_name LIKE ? OR email LIKE ? OR phone LIKE ?) 
            AND status = 'Active'
            ORDER BY full_name 
            LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $search = "%$search_term%";
    $stmt->execute([$search, $search, $search]);
    $employees = $stmt->fetchAll();
}

// Helper functions
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
function formatTime($time) { return $time ? date('h:i A', strtotime($time)) : '-'; }
function getStatusBadge($status) {
    $badges = [
        'present' => '<span class="badge badge-success"><i class="ph ph-check-circle"></i> Present</span>',
        'absent' => '<span class="badge badge-danger"><i class="ph ph-prohibit"></i> Absent</span>',
        'late' => '<span class="badge badge-warning"><i class="ph ph-clock"></i> Late</span>',
        'half_day' => '<span class="badge badge-info"><i class="ph ph-hourglass"></i> Half Day</span>',
        'leave' => '<span class="badge badge-purple"><i class="ph ph-calendar"></i> Leave</span>',
        'weekend' => '<span class="badge badge-secondary"><i class="ph ph-calendar-blank"></i> Weekend</span>',
        'holiday' => '<span class="badge badge-secondary"><i class="ph ph-palm-tree"></i> Holiday</span>'
    ];
    return $badges[$status] ?? '<span class="badge">Unknown</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* --- CSS Variables & Reset --- */
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
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
            --secondary: #6B7280;
        }

        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --sidebar-bg: #020617;
            --primary: #6366f1;
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
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            padding: 12px 24px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
        }
        
        .btn-secondary:hover {
            background: var(--border);
        }

        /* Message Alert */
        .alert {
            padding: 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }

        /* Search Box */
        .search-box {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 24px;
            display: flex;
            gap: 12px;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            background: var(--bg-body);
            color: var(--text-main);
            outline: none;
            transition: 0.2s;
        }
        
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .btn-search {
            background: var(--primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-search:hover {
            background: var(--primary-hover);
        }
        
        .btn-clear {
            background: var(--bg-body);
            color: var(--text-muted);
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-clear:hover {
            background: var(--border);
            color: var(--text-main);
        }

        /* Employee Profile Card */
        .employee-profile {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 24px;
            margin-bottom: 24px;
            display: flex;
            gap: 24px;
            align-items: center;
        }
        
        .employee-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 16px;
            object-fit: cover;
            border: 3px solid var(--border);
        }
        
        .employee-details {
            flex: 1;
        }
        
        .employee-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .employee-meta {
            display: flex;
            gap: 16px;
            margin-bottom: 12px;
            flex-wrap: wrap;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .employee-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        /* Date Range Selector */
        .date-range-selector {
            background: var(--bg-card);
            padding: 16px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            align-items: center;
        }
        
        .date-input {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            background: var(--bg-body);
            color: var(--text-main);
            outline: none;
        }
        
        .date-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            white-space: nowrap;
        }

        /* Statistics Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--border);
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .stat-card-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 12px;
            font-size: 1.2rem;
        }
        
        .stat-card-total .stat-card-icon {
            background: rgba(79, 70, 229, 0.1);
            color: var(--primary);
        }
        
        .stat-card-present .stat-card-icon {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .stat-card-absent .stat-card-icon {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .stat-card-hours .stat-card-icon {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
        }

        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .chart-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 20px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
        }
        
        .chart-container {
            position: relative;
            height: 250px;
        }

        /* Table */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: var(--bg-body);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            color: var(--text-main);
            border-bottom: 2px solid var(--border);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        .data-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 0.95rem;
        }
        
        .data-table tbody tr:hover {
            background: var(--bg-body);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 4px;
            white-space: nowrap;
        }
        
        .badge-success {
            background: #D1FAE5;
            color: #065F46;
        }
        
        .badge-warning {
            background: #FEF3C7;
            color: #92400E;
        }
        
        .badge-danger {
            background: #FEE2E2;
            color: #991B1B;
        }
        
        .badge-info {
            background: #DBEAFE;
            color: #1E40AF;
        }
        
        .badge-purple {
            background: #EDE9FE;
            color: #5B21B6;
        }
        
        .badge-secondary {
            background: #F3F4F6;
            color: #4B5563;
        }
        
        [data-theme="dark"] .badge-success {
            background: #064E3B;
            color: #A7F3D0;
        }
        
        [data-theme="dark"] .badge-warning {
            background: #78350F;
            color: #FDE68A;
        }
        
        [data-theme="dark"] .badge-danger {
            background: #7F1D1D;
            color: #FECACA;
        }
        
        [data-theme="dark"] .badge-info {
            background: #1E3A8A;
            color: #93C5FD;
        }

        /* Search Results */
        .search-results {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .result-item {
            padding: 16px;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .result-item:hover {
            background: var(--bg-body);
        }
        
        .result-item:last-child {
            border-bottom: none;
        }
        
        .result-avatar {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            object-fit: cover;
        }
        
        .result-info {
            flex: 1;
        }
        
        .result-name {
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.95rem;
        }
        
        .result-details {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            flex-direction: column;
        }

        /* Export Options */
        .export-options {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        .export-btn {
            padding: 10px 20px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .export-btn:hover {
            background: var(--bg-body);
            transform: translateY(-1px);
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

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: var(--text-muted);
            opacity: 0.5;
            margin-bottom: 16px;
        }
        
        .empty-state-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 8px;
        }
        
        .empty-state-description {
            color: var(--text-muted);
            max-width: 400px;
            margin: 0 auto 24px auto;
        }

        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .footer-links {
            display: flex;
            gap: 20px;
        }
        
        .footer-links a {
            color: var(--text-muted);
            font-size: 0.8rem;
        }
        
        .footer-links a:hover {
            color: var(--primary);
        }
        
        .footer-version {
            margin-top: 12px;
            font-size: 0.75rem;
            opacity: 0.7;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .charts-section {
                grid-template-columns: 1fr;
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
                padding: 20px; 
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .employee-profile {
                flex-direction: column;
                text-align: center;
            }
            
            .employee-meta {
                justify-content: center;
            }
            
            .date-range-selector {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .search-box {
                flex-direction: column;
                align-items: stretch;
            }
            
            .footer-content {
                flex-direction: column;
                text-align: center;
            }
            
            .footer-links {
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .stat-card-value {
                font-size: 1.5rem;
            }
            
            .export-options {
                flex-direction: column;
            }
        }

        /* Theme Toggle */
        #themeToggle {
            background: var(--bg-card);
            border: 1px solid var(--border);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        #themeToggle:hover {
            transform: rotate(15deg);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Scrollbar Styling */
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
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- SIDEBAR -->
     <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        
        <!-- HEADER -->
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Attendance Reports</span>
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
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Attendance Reports</h1>
                    <p class="page-subtitle">Search employees and view detailed attendance records</p>
                </div>
                <div>
                    <?php if($employee): ?>
                        <a href="attendance_report.php" class="btn-secondary">
                            <i class="ph ph-arrow-left"></i>
                            Back to Search
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Search Box -->
            <?php if(!$employee): ?>
            <div class="search-box">
                <form method="GET" style="display: flex; gap: 12px; width: 100%;">
                    <input type="text" name="search" class="search-input" placeholder="Search employees by name, email, or phone..." value="<?php echo e($search_term); ?>">
                    <button type="submit" class="btn-search">
                        <i class="ph ph-magnifying-glass"></i>
                        Search
                    </button>
                </form>
            </div>

            <!-- Search Results -->
            <?php if($search_term && $employees): ?>
                <div class="search-results">
                    <?php if(count($employees) > 0): ?>
                        <div style="padding: 16px; border-bottom: 1px solid var(--border); background: var(--bg-body);">
                            <span style="font-size: 0.9rem; color: var(--text-muted);">
                                Found <?php echo count($employees); ?> employee(s)
                            </span>
                        </div>
                        <?php foreach($employees as $emp): ?>
                            <?php
                                $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($emp['full_name']) . "&background=random&color=fff";
                                if (!empty($emp['photo'])) {
                                    $dbVal = $emp['photo'];
                                    if (file_exists('uploads/' . $dbVal)) {
                                        $avatarUrl = 'uploads/' . $dbVal;
                                    } elseif (filter_var($dbVal, FILTER_VALIDATE_URL)) {
                                        $avatarUrl = $dbVal;
                                    }
                                }
                            ?>
                            <a href="?employee_id=<?php echo $emp['id']; ?>" class="result-item">
                                <img src="<?php echo e($avatarUrl); ?>" class="result-avatar" alt="Avatar">
                                <div class="result-info">
                                    <div class="result-name"><?php echo e($emp['full_name']); ?></div>
                                    <div class="result-details">
                                        <span>ID: <?php echo $emp['id']; ?></span>
                                        <span><?php echo e($emp['department']); ?> • <?php echo e($emp['designation']); ?></span>
                                    </div>
                                </div>
                                <i class="ph ph-arrow-right" style="color: var(--text-muted);"></i>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-muted);">
                            <i class="ph ph-users" style="font-size: 3rem; opacity: 0.3; margin-bottom: 16px; display: block;"></i>
                            No employees found matching "<?php echo e($search_term); ?>"
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif($search_term): ?>
                <div style="background: var(--bg-card); padding: 40px; border-radius: var(--radius); text-align: center;">
                    <div class="empty-state-icon">
                        <i class="ph ph-users"></i>
                    </div>
                    <div class="empty-state-title">No employees found</div>
                    <div class="empty-state-description">
                        No employees match your search criteria. Try a different search term.
                    </div>
                </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Employee Profile & Reports -->
            <?php if($employee): ?>
                <?php
                    $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($employee['full_name']) . "&background=random&color=fff";
                    if (!empty($employee['photo'])) {
                        $dbVal = $employee['photo'];
                        if (file_exists('uploads/' . $dbVal)) {
                            $avatarUrl = 'uploads/' . $dbVal;
                        } elseif (filter_var($dbVal, FILTER_VALIDATE_URL)) {
                            $avatarUrl = $dbVal;
                        }
                    }
                ?>

                <!-- Employee Profile Card -->
                <div class="employee-profile">
                    <img src="<?php echo e($avatarUrl); ?>" class="employee-avatar-large" alt="Avatar">
                    <div class="employee-details">
                        <div class="employee-name"><?php echo e($employee['full_name']); ?></div>
                        <div class="employee-meta">
                            <div class="meta-item">
                                <i class="ph ph-identification-card"></i>
                                Employee ID: <?php echo $employee['id']; ?>
                            </div>
                            <div class="meta-item">
                                <i class="ph ph-briefcase"></i>
                                <?php echo e($employee['department']); ?>
                            </div>
                            <div class="meta-item">
                                <i class="ph ph-user-circle"></i>
                                <?php echo e($employee['designation']); ?>
                            </div>
                            <div class="meta-item">
                                <i class="ph ph-phone"></i>
                                <?php echo e($employee['phone']); ?>
                            </div>
                            <div class="meta-item">
                                <i class="ph ph-envelope"></i>
                                <?php echo e($employee['email']); ?>
                            </div>
                        </div>
                        <div class="employee-status status-active">
                            <i class="ph ph-check-circle"></i>
                            Active Employee
                        </div>
                    </div>
                </div>

                <!-- Date Range Selector -->
                <form method="GET" class="date-range-selector">
                    <input type="hidden" name="employee_id" value="<?php echo e($employee_id); ?>">
                    <span class="date-label">View report from:</span>
                    <input type="date" name="start_date" class="date-input" value="<?php echo e($start_date); ?>">
                    <span class="date-label">to</span>
                    <input type="date" name="end_date" class="date-input" value="<?php echo e($end_date); ?>">
                    <button type="submit" class="btn-primary" style="margin-left: auto;">
                        <i class="ph ph-calendar-blank"></i>
                        Update Report
                    </button>
                </form>

                <!-- Statistics Cards -->
                <?php
                    $total_days = $attendance_stats['total_days'] ?? 0;
                    $present_days = $attendance_stats['present_days'] ?? 0;
                    $absent_days = $attendance_stats['absent_days'] ?? 0;
                    $total_hours = $attendance_stats['total_hours'] ?? 0;
                    $late_days = $attendance_stats['late_days'] ?? 0;
                    $half_days = $attendance_stats['half_days'] ?? 0;
                    $leave_days = $attendance_stats['leave_days'] ?? 0;
                    $attendance_rate = $total_days > 0 ? round(($present_days / $total_days) * 100, 1) : 0;
                ?>
                <div class="stats-cards">
                    <div class="stat-card stat-card-total">
                        <div class="stat-card-icon">
                            <i class="ph ph-calendar-days"></i>
                        </div>
                        <div class="stat-card-label">Total Days</div>
                        <div class="stat-card-value"><?php echo $total_days; ?></div>
                    </div>
                    <div class="stat-card stat-card-present">
                        <div class="stat-card-icon">
                            <i class="ph ph-check-circle"></i>
                        </div>
                        <div class="stat-card-label">Present Days</div>
                        <div class="stat-card-value"><?php echo $present_days; ?></div>
                    </div>
                    <div class="stat-card stat-card-absent">
                        <div class="stat-card-icon">
                            <i class="ph ph-prohibit"></i>
                        </div>
                        <div class="stat-card-label">Absent Days</div>
                        <div class="stat-card-value"><?php echo $absent_days; ?></div>
                    </div>
                    <div class="stat-card stat-card-hours">
                        <div class="stat-card-icon">
                            <i class="ph ph-clock-clockwise"></i>
                        </div>
                        <div class="stat-card-label">Total Hours</div>
                        <div class="stat-card-value"><?php echo number_format($total_hours, 1); ?></div>
                    </div>
                </div>

                <!-- Charts Section -->
                <div class="charts-section">
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Attendance Distribution</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="attendanceChart"></canvas>
                        </div>
                    </div>
                    
                    <div class="chart-card">
                        <div class="chart-header">
                            <div class="chart-title">Attendance by Day of Week</div>
                        </div>
                        <div class="chart-container">
                            <canvas id="dayPatternChart"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Attendance Records Table -->
                <div class="table-container">
                    <div class="table-header">
                        <div style="font-weight: 600; color: var(--text-main);">
                            <i class="ph ph-list-checks"></i>
                            Attendance Records (<?php echo date('M j, Y', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>)
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo count($attendance_records); ?> records found
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($attendance_records) > 0): ?>
                                    <?php foreach($attendance_records as $record): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo date('M j, Y', strtotime($record['date'])); ?></div>
                                        </td>
                                        <td>
                                            <?php echo date('l', strtotime($record['date'])); ?>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: var(--success);">
                                                <?php echo formatTime($record['check_in']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500; color: var(--danger);">
                                                <?php echo formatTime($record['check_out']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--primary);">
                                                <?php echo number_format($record['hours_worked'], 1); ?>h
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getStatusBadge($record['status']); ?>
                                        </td>
                                        <td>
                                            <span style="font-size: 0.9rem; color: var(--text-muted);">
                                                <?php echo e($record['notes']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state" style="padding: 40px 20px;">
                                                <div class="empty-state-icon">
                                                    <i class="ph ph-calendar-blank"></i>
                                                </div>
                                                <div class="empty-state-title">No attendance records found</div>
                                                <div class="empty-state-description">
                                                    No attendance records found for this employee in the selected date range.
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Export Options -->
                <div class="export-options">
                    <button class="export-btn" onclick="exportToPDF()">
                        <i class="ph ph-file-pdf"></i>
                        Export to PDF
                    </button>
                    <button class="export-btn" onclick="exportToExcel()">
                        <i class="ph ph-file-xls"></i>
                        Export to Excel
                    </button>
                    <button class="export-btn" onclick="printReport()">
                        <i class="ph ph-printer"></i>
                        Print Report
                    </button>
                </div>
            <?php elseif(!$search_term): ?>
                <!-- Empty State -->
                <div style="background: var(--bg-card); padding: 60px; border-radius: var(--radius); text-align: center;">
                    <div class="empty-state-icon">
                        <i class="ph ph-user-search"></i>
                    </div>
                    <div class="empty-state-title">Search Employee Attendance</div>
                    <div class="empty-state-description">
                        Enter an employee's name, email, or phone number to view their detailed attendance report and statistics.
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Footer -->
            <div class="footer">
                <div class="footer-content">
                    <div>
                        &copy; <?php echo date('Y'); ?> NexusAdmin System. All rights reserved.
                    </div>
                    <div class="footer-links">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">Contact Support</a>
                    </div>
                </div>
                <div class="footer-version">
                    Version 2.0.1 | Last updated: <?php echo date('M d, Y'); ?>
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
        
        if(localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            themeIcon.classList.replace('ph-moon', 'ph-sun');
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
        });

        // --- 5. Charts ---
        <?php if($employee && $attendance_stats): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Attendance Distribution Chart
            const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
            const attendanceChart = new Chart(attendanceCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Present', 'Absent', 'Late', 'Half Day', 'Leave'],
                    datasets: [{
                        data: [
                            <?php echo $present_days; ?>,
                            <?php echo $absent_days; ?>,
                            <?php echo $late_days; ?>,
                            <?php echo $half_days; ?>,
                            <?php echo $leave_days; ?>
                        ],
                        backgroundColor: [
                            '#10B981',
                            '#EF4444',
                            '#F59E0B',
                            '#3B82F6',
                            '#8B5CF6'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });

            // Day Pattern Chart
            <?php
                // Prepare day pattern data
                $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $presentData = array_fill(0, 7, 0);
                $absentData = array_fill(0, 7, 0);
                $lateData = array_fill(0, 7, 0);
                
                if(isset($attendance_pattern) && is_array($attendance_pattern)) {
                    foreach($attendance_pattern as $pattern) {
                        $dayIndex = array_search($pattern['day_name'], $days);
                        if ($dayIndex !== false) {
                            $presentData[$dayIndex] = $pattern['present'];
                            $absentData[$dayIndex] = $pattern['absent'];
                            $lateData[$dayIndex] = $pattern['late'];
                        }
                    }
                }
            ?>
            
            const dayPatternCtx = document.getElementById('dayPatternChart').getContext('2d');
            const dayPatternChart = new Chart(dayPatternCtx, {
                type: 'bar',
                data: {
                    labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                    datasets: [
                        {
                            label: 'Present',
                            data: <?php echo json_encode($presentData); ?>,
                            backgroundColor: '#10B981',
                            borderColor: '#10B981',
                            borderWidth: 1
                        },
                        {
                            label: 'Absent',
                            data: <?php echo json_encode($absentData); ?>,
                            backgroundColor: '#EF4444',
                            borderColor: '#EF4444',
                            borderWidth: 1
                        },
                        {
                            label: 'Late',
                            data: <?php echo json_encode($lateData); ?>,
                            backgroundColor: '#F59E0B',
                            borderColor: '#F59E0B',
                            borderWidth: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                precision: 0
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                usePointStyle: true
                            }
                        }
                    }
                }
            });
        });
        <?php endif; ?>

        // --- 6. Export Functions ---
        function exportToPDF() {
            alert('PDF export feature would be implemented here. This would generate a detailed PDF report.');
        }

        function exportToExcel() {
            // Create Excel data
            const table = document.querySelector('.data-table');
            let csv = [];
            
            // Get headers
            const headers = [];
            table.querySelectorAll('th').forEach(th => {
                headers.push(th.textContent.trim());
            });
            csv.push(headers.join(','));
            
            // Get rows
            table.querySelectorAll('tbody tr').forEach(tr => {
                const row = [];
                tr.querySelectorAll('td').forEach(td => {
                    // Remove HTML tags and clean text
                    let text = td.textContent.trim();
                    text = text.replace(/\s+/g, ' ');
                    row.push(`"${text}"`);
                });
                csv.push(row.join(','));
            });
            
            // Download CSV
            const csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "attendance_report_<?php echo $employee_id ?? 'search'; ?>_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function printReport() {
            window.print();
        }

        // --- 7. Initialize Sidebar State ---
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }

        // --- 8. Auto-focus on search input ---
        <?php if(!$employee): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
            }
        });
        <?php endif; ?>

        // --- 9. Auto-close dropdowns on escape key ---
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });

        // --- 10. Theme-aware Chart Colors ---
        function getChartColors() {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            return {
                grid: isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)',
                text: isDark ? '#94a3b8' : '#6b7280'
            };
        }
    </script>
</body>
</html>