<?php
/**
 * attendance.php
 * NexusAdmin V2 - Employee Attendance Management
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

// --- 3. CREATE ATTENDANCE TABLE IF NOT EXISTS ---
try {
    $createTable = "CREATE TABLE IF NOT EXISTS attendance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        employee_id INT NOT NULL,
        date DATE NOT NULL,
        check_in TIME DEFAULT NULL,
        check_out TIME DEFAULT NULL,
        status ENUM('present', 'absent', 'late', 'half_day', 'leave', 'weekend', 'holiday') DEFAULT 'absent',
        hours_worked DECIMAL(5,2) DEFAULT 0.00,
        notes TEXT,
        marked_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_attendance (employee_id, date),
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($createTable);
} catch (PDOException $e) {
    // Table might already exist
}

// --- 4. HANDLE FORM SUBMISSIONS ---
$message = '';
$message_type = '';

// Handle bulk attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_attendance'])) {
    $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
    $marked_by = $_SESSION['user_id'];
    
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST['attendance'] as $employee_id => $status) {
            $check_in = $_POST['check_in'][$employee_id] ?? null;
            $check_out = $_POST['check_out'][$employee_id] ?? null;
            $notes = $_POST['notes'][$employee_id] ?? '';
            
            // Calculate hours worked if both check-in and check-out are provided
            $hours_worked = 0.00;
            if ($check_in && $check_out) {
                $time1 = new DateTime($check_in);
                $time2 = new DateTime($check_out);
                $interval = $time1->diff($time2);
                $hours_worked = $interval->h + ($interval->i / 60);
            }
            
            // Check if attendance already exists for this date
            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
            $stmt->execute([$employee_id, $attendance_date]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update existing record
                $update = $pdo->prepare("
                    UPDATE attendance 
                    SET status = ?, check_in = ?, check_out = ?, hours_worked = ?, notes = ?, marked_by = ?
                    WHERE employee_id = ? AND date = ?
                ");
                $update->execute([$status, $check_in, $check_out, $hours_worked, $notes, $marked_by, $employee_id, $attendance_date]);
            } else {
                // Insert new record
                $insert = $pdo->prepare("
                    INSERT INTO attendance (employee_id, date, status, check_in, check_out, hours_worked, notes, marked_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([$employee_id, $attendance_date, $status, $check_in, $check_out, $hours_worked, $notes, $marked_by]);
            }
        }
        
        $pdo->commit();
        $message = "Attendance marked successfully for " . date('F j, Y', strtotime($attendance_date));
        $message_type = "success";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error saving attendance: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle single employee check-in/check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_action'])) {
    $employee_id = $_POST['employee_id'];
    $action = $_POST['check_action'];
    $current_time = date('H:i:s');
    $today = date('Y-m-d');
    
    try {
        if ($action === 'check_in') {
            // Check if already checked in today
            $stmt = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
            $stmt->execute([$employee_id, $today]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update check-in time
                $update = $pdo->prepare("UPDATE attendance SET check_in = ?, status = 'present' WHERE employee_id = ? AND date = ?");
                $update->execute([$current_time, $employee_id, $today]);
            } else {
                // Insert new record
                $insert = $pdo->prepare("INSERT INTO attendance (employee_id, date, check_in, status, marked_by) VALUES (?, ?, ?, 'present', ?)");
                $insert->execute([$employee_id, $today, $current_time, $_SESSION['user_id']]);
            }
            $message = "Check-in recorded successfully at " . date('h:i A', strtotime($current_time));
        } elseif ($action === 'check_out') {
            // Update check-out time
            $update = $pdo->prepare("UPDATE attendance SET check_out = ? WHERE employee_id = ? AND date = ?");
            $update->execute([$current_time, $employee_id, $today]);
            
            // Calculate hours worked
            $calc = $pdo->prepare("
                UPDATE attendance 
                SET hours_worked = TIMESTAMPDIFF(MINUTE, check_in, ?) / 60.0 
                WHERE employee_id = ? AND date = ?
            ");
            $calc->execute([$current_time, $employee_id, $today]);
            
            $message = "Check-out recorded successfully at " . date('h:i A', strtotime($current_time));
        }
        $message_type = "success";
    } catch (PDOException $e) {
        $message = "Error recording attendance: " . $e->getMessage();
        $message_type = "error";
    }
}

// --- 5. FETCH DATA ---
$search = trim($_GET['search'] ?? '');
$attendance_date = $_GET['date'] ?? date('Y-m-d');
$department_filter = $_GET['department'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Get employees for attendance
$whereConditions = ["e.status = 'Active'"];
$params = [];

if ($search) {
    // FIXED: Using e.id instead of non-existent e.employee_id
    $whereConditions[] = "(e.full_name LIKE ? OR e.email LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($department_filter !== 'all') {
    $whereConditions[] = "e.department = ?";
    $params[] = $department_filter;
}

$whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";

$sql = "SELECT e.* FROM employees e $whereClause ORDER BY e.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$employees = $stmt->fetchAll();

// Get today's attendance for each employee
$attendance_records = [];
if ($employees) {
    $employee_ids = array_column($employees, 'id');
    $placeholders = str_repeat('?,', count($employee_ids) - 1) . '?';
    
    $attendance_sql = "SELECT * FROM attendance WHERE employee_id IN ($placeholders) AND date = ?";
    $attendance_params = array_merge($employee_ids, [$attendance_date]);
    $attendance_stmt = $pdo->prepare($attendance_sql);
    $attendance_stmt->execute($attendance_params);
    
    while ($row = $attendance_stmt->fetch()) {
        $attendance_records[$row['employee_id']] = $row;
    }
}

// Get departments for filter
$departments = $pdo->query("SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll();

// Get attendance statistics
$stats_sql = "
    SELECT 
        COUNT(DISTINCT e.id) as total_employees,
        COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_today,
        COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_today,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_today,
        COUNT(CASE WHEN a.status IN ('half_day', 'leave') THEN 1 END) as leave_today,
        AVG(a.hours_worked) as avg_hours
    FROM employees e
    LEFT JOIN attendance a ON e.id = a.employee_id AND a.date = ?
    WHERE e.status = 'Active'
";

$stats = $pdo->prepare($stats_sql);
$stats->execute([$attendance_date]);
$attendance_stats = $stats->fetch();

// Get recent attendance records
$recent_sql = "
    SELECT a.*, e.full_name, e.department, e.designation 
    FROM attendance a
    JOIN employees e ON a.employee_id = e.id
    ORDER BY a.date DESC, a.check_in DESC
    LIMIT 10
";
$recent_attendance = $pdo->query($recent_sql)->fetchAll();

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
function formatTime($time) { return $time ? date('h:i A', strtotime($time)) : '-'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Management | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

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

        /* Attendance Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
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
        }
        
        .stat-card-value {
            font-size: 1.8rem;
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
        
        .stat-card-late .stat-card-icon {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .stat-card-hours .stat-card-icon {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info);
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

        /* Table */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
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

        /* Employee Cells */
        .employee-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .employee-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid var(--border);
        }
        
        .employee-info {
            display: flex;
            flex-direction: column;
        }
        
        .employee-name {
            font-weight: 600;
            color: var(--text-main);
            font-size: 0.95rem;
        }
        
        .employee-email {
            font-size: 0.85rem;
            color: var(--text-muted);
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

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .action-btn {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-muted);
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
        }
        
        .edit-btn:hover {
            background: #3B82F6;
            color: white;
            border-color: #3B82F6;
        }
        
        .delete-btn:hover {
            background: #EF4444;
            color: white;
            border-color: #EF4444;
        }
        
        .view-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        /* Time Inputs */
        .time-input {
            width: 100px;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.85rem;
            text-align: center;
            background: var(--bg-card);
            color: var(--text-main);
        }
        
        .time-input:disabled {
            background: var(--bg-body);
            opacity: 0.6;
        }

        /* Status Select */
        .status-select {
            width: 120px;
            padding: 8px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.85rem;
            background: var(--bg-card);
            color: var(--text-main);
        }
        
        .status-select:disabled {
            background: var(--bg-body);
            opacity: 0.6;
        }

        /* Check-in/out Buttons */
        .check-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        
        .check-in-btn {
            background: var(--success);
            color: white;
        }
        
        .check-out-btn {
            background: var(--danger);
            color: white;
        }
        
        .check-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Notes Input */
        .notes-input {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 0.85rem;
            min-height: 36px;
            resize: vertical;
            background: var(--bg-card);
            color: var(--text-main);
        }

        /* Recent Activity */
        .recent-activity {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 20px;
            margin-top: 24px;
        }
        
        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px;
            border-bottom: 1px solid var(--border);
            gap: 12px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .activity-in { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .activity-out { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .activity-time {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        /* Bulk Actions */
        .bulk-actions {
            padding: 20px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-body);
        }
        
        .bulk-select {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .bulk-status-select {
            padding: 10px 16px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 0.9rem;
            min-width: 150px;
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

        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(3, 1fr);
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
            
            .stats-cards {
                grid-template-columns: 1fr;
            }
            
            .search-box {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input, .btn-search, .btn-clear {
                width: 100%;
            }
            
            .data-table th, .data-table td {
                padding: 12px;
            }
            
            .action-buttons {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .stat-card {
                padding: 16px;
            }
            
            .stat-card-value {
                font-size: 1.5rem;
            }
            
            .employee-cell {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
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
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Attendance Management</span>
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
            <!-- Message Alert -->
            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
                <i class="ph <?php echo $message_type === 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                <span><?php echo e($message); ?></span>
            </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Attendance Management</h1>
                    <p class="page-subtitle">Track employee attendance, check-ins, and hours worked</p>
                </div>
                <div>
                    <button type="button" class="btn-primary" onclick="markAllPresent()">
                        <i class="ph ph-check-circle"></i>
                        Mark All Present
                    </button>
                </div>
            </div>

            <!-- Statistics Cards -->
            <?php
            // Calculate statistics
            $total_employees = $attendance_stats['total_employees'] ?? 0;
            $present_today = $attendance_stats['present_today'] ?? 0;
            $absent_today = $attendance_stats['absent_today'] ?? 0;
            $late_today = $attendance_stats['late_today'] ?? 0;
            $leave_today = $attendance_stats['leave_today'] ?? 0;
            $avg_hours = $attendance_stats['avg_hours'] ?? 0;
            ?>
            <div class="stats-cards">
                <div class="stat-card stat-card-total">
                    <div class="stat-card-icon">
                        <i class="ph ph-users-three"></i>
                    </div>
                    <div class="stat-card-label">Total Employees</div>
                    <div class="stat-card-value"><?php echo $total_employees; ?></div>
                </div>
                <div class="stat-card stat-card-present">
                    <div class="stat-card-icon">
                        <i class="ph ph-check-circle"></i>
                    </div>
                    <div class="stat-card-label">Present Today</div>
                    <div class="stat-card-value"><?php echo $present_today; ?></div>
                </div>
                <div class="stat-card stat-card-absent">
                    <div class="stat-card-icon">
                        <i class="ph ph-prohibit"></i>
                    </div>
                    <div class="stat-card-label">Absent Today</div>
                    <div class="stat-card-value"><?php echo $absent_today; ?></div>
                </div>
                <div class="stat-card stat-card-late">
                    <div class="stat-card-icon">
                        <i class="ph ph-clock"></i>
                    </div>
                    <div class="stat-card-label">Late Today</div>
                    <div class="stat-card-value"><?php echo $late_today; ?></div>
                </div>
                <div class="stat-card stat-card-hours">
                    <div class="stat-card-icon">
                        <i class="ph ph-hourglass"></i>
                    </div>
                    <div class="stat-card-label">Avg Hours</div>
                    <div class="stat-card-value"><?php echo number_format($avg_hours, 1); ?></div>
                </div>
            </div>

            <!-- Search Box -->
            <form method="GET" class="search-box">
                <input type="text" name="search" class="search-input" placeholder="Search by name, email..." value="<?php echo e($search); ?>">
                <input type="date" name="date" class="search-input" value="<?php echo e($attendance_date); ?>" style="max-width: 200px;">
                <button type="submit" class="btn-search">
                    <i class="ph ph-magnifying-glass"></i>
                    Search
                </button>
                <?php if($search || $attendance_date != date('Y-m-d')): ?>
                    <a href="attendance.php" class="btn-clear">
                        <i class="ph ph-x"></i>
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <!-- Attendance Table Form -->
            <form method="POST" action="attendance.php">
                <input type="hidden" name="attendance_date" value="<?php echo e($attendance_date); ?>">
                
                <div class="table-container">
                    <div class="table-header">
                        <div style="font-weight: 600; color: var(--text-main);">
                            <i class="ph ph-list"></i>
                            Employee Attendance for <?php echo date('F j, Y', strtotime($attendance_date)); ?>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo $total_employees; ?> total employees
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th>Notes</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($employees) > 0): ?>
                                    <?php foreach ($employees as $emp): ?>
                                    <?php 
                                        // Smart image logic
                                        $name = !empty($emp['full_name']) ? $emp['full_name'] : 'Employee';
                                        $avatarUrl = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff";
                                        
                                        if (!empty($emp['photo'])) {
                                            $dbVal = $emp['photo'];
                                            if (file_exists('uploads/' . $dbVal)) {
                                                $avatarUrl = 'uploads/' . $dbVal;
                                            } elseif (file_exists($dbVal)) {
                                                $avatarUrl = $dbVal;
                                            } elseif (filter_var($dbVal, FILTER_VALIDATE_URL)) {
                                                $avatarUrl = $dbVal;
                                            }
                                        }

                                        $attendance = $attendance_records[$emp['id']] ?? null;
                                        $status = $attendance['status'] ?? 'absent';
                                        
                                        // Status badge
                                        $statusClass = 'badge-success';
                                        $icon = 'ph-check-circle';
                                        if ($status === 'absent') { 
                                            $statusClass = 'badge-danger'; 
                                            $icon = 'ph-prohibit'; 
                                        } elseif ($status === 'late') { 
                                            $statusClass = 'badge-warning'; 
                                            $icon = 'ph-clock'; 
                                        } elseif ($status === 'half_day') { 
                                            $statusClass = 'badge-info'; 
                                            $icon = 'ph-hourglass'; 
                                        } elseif ($status === 'leave') { 
                                            $statusClass = 'badge-purple'; 
                                            $icon = 'ph-calendar'; 
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="employee-cell">
                                                <img src="<?php echo e($avatarUrl); ?>" class="employee-avatar" alt="Avatar" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($name); ?>&background=random&color=fff'">
                                                <div class="employee-info">
                                                    <div class="employee-name"><?php echo e($emp['full_name']); ?></div>
                                                    <div class="employee-email"><?php echo e($emp['email']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo e($emp['department']); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);">
                                                <i class="ph ph-briefcase"></i> <?php echo e($emp['designation']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="time" 
                                                   name="check_in[<?php echo $emp['id']; ?>]" 
                                                   value="<?php echo e($attendance['check_in'] ?? '09:00'); ?>"
                                                   class="time-input">
                                        </td>
                                        <td>
                                            <input type="time" 
                                                   name="check_out[<?php echo $emp['id']; ?>]" 
                                                   value="<?php echo e($attendance['check_out'] ?? '17:00'); ?>"
                                                   class="time-input">
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; color: var(--primary);">
                                                <?php echo $attendance ? number_format($attendance['hours_worked'], 1) . 'h' : '0.0h'; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <select name="attendance[<?php echo $emp['id']; ?>]" class="status-select">
                                                <option value="present" <?php echo $status === 'present' ? 'selected' : ''; ?>>Present</option>
                                                <option value="absent" <?php echo $status === 'absent' ? 'selected' : ''; ?>>Absent</option>
                                                <option value="late" <?php echo $status === 'late' ? 'selected' : ''; ?>>Late</option>
                                                <option value="half_day" <?php echo $status === 'half_day' ? 'selected' : ''; ?>>Half Day</option>
                                                <option value="leave" <?php echo $status === 'leave' ? 'selected' : ''; ?>>Leave</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="notes[<?php echo $emp['id']; ?>]" 
                                                   value="<?php echo e($attendance['notes'] ?? ''); ?>"
                                                   placeholder="Add notes..."
                                                   class="notes-input">
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="action-btn edit-btn" onclick="quickCheckIn(<?php echo $emp['id']; ?>)" title="Check In">
                                                    <i class="ph ph-sign-in"></i>
                                                </button>
                                                <button type="button" class="action-btn delete-btn" onclick="quickCheckOut(<?php echo $emp['id']; ?>)" title="Check Out">
                                                    <i class="ph ph-sign-out"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8">
                                            <div class="empty-state">
                                                <div class="empty-state-icon">
                                                    <i class="ph ph-users"></i>
                                                </div>
                                                <div class="empty-state-title">No employees found</div>
                                                <div class="empty-state-description">
                                                    <?php if($search): ?>
                                                        No employees match your search criteria. Try a different search term.
                                                    <?php else: ?>
                                                        You haven't added any employees yet. Add your first employee to get started.
                                                    <?php endif; ?>
                                                </div>
                                                <a href="add_employee.php" class="btn-primary">
                                                    <i class="ph ph-plus"></i>
                                                    Add Your First Employee
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Bulk Actions -->
                    <?php if (count($employees) > 0): ?>
                    <div class="bulk-actions">
                        <div class="bulk-select">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)">
                            <label for="selectAll">Select All</label>
                        </div>
                        
                        <div style="display: flex; gap: 12px;">
                            <select id="bulkStatus" class="bulk-status-select">
                                <option value="">Set Status for Selected</option>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                                <option value="half_day">Half Day</option>
                                <option value="leave">Leave</option>
                            </select>
                            <button type="button" class="btn-primary" onclick="applyBulkStatus()">
                                <i class="ph ph-check"></i>
                                Apply
                            </button>
                            <button type="submit" name="mark_attendance" class="btn-primary">
                                <i class="ph ph-floppy-disk"></i>
                                Save All Attendance
                            </button>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Recent Activity Section -->
            <div class="recent-activity">
                <h3 style="margin-bottom: 20px; font-size: 1.1rem; display: flex; align-items: center; gap: 8px;">
                    <i class="ph ph-clock"></i>
                    Recent Check-ins/outs
                </h3>
                
                <div>
                    <?php if (count($recent_attendance) > 0): ?>
                        <?php foreach ($recent_attendance as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['check_in'] ? 'activity-in' : 'activity-out'; ?>">
                                <i class="ph <?php echo $activity['check_in'] ? 'ph-arrow-right' : 'ph-arrow-left'; ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-name"><?php echo e($activity['full_name']); ?></div>
                                <div class="activity-time">
                                    <?php if ($activity['check_in']): ?>
                                        Checked in at <?php echo formatTime($activity['check_in']); ?>
                                    <?php else: ?>
                                        Checked out at <?php echo formatTime($activity['check_out']); ?>
                                    <?php endif; ?>
                                    • <?php echo date('M d, Y', strtotime($activity['date'])); ?>
                                </div>
                            </div>
                            <div class="<?php echo $statusClass; ?>">
                                <i class="ph <?php echo $icon; ?>"></i> 
                                <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                            <i class="ph ph-clock" style="font-size: 2rem; opacity: 0.3; margin-bottom: 12px; display: block;"></i>
                            No recent attendance records found.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Footer -->
            <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid var(--border); text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div>
                        &copy; <?php echo date('Y'); ?> NexusAdmin System. All rights reserved.
                    </div>
                    <div style="display: flex; gap: 20px;">
                        <a href="#" style="color: var(--text-muted); font-size: 0.8rem;">Privacy Policy</a>
                        <a href="#" style="color: var(--text-muted); font-size: 0.8rem;">Terms of Service</a>
                        <a href="#" style="color: var(--text-muted); font-size: 0.8rem;">Contact Support</a>
                    </div>
                </div>
                <div style="margin-top: 12px; font-size: 0.75rem; opacity: 0.7;">
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

        // --- 5. Quick Check-in/out Functions ---
        function quickCheckIn(employeeId) {
            if (confirm('Mark this employee as checked in?')) {
                const formData = new FormData();
                formData.append('check_action', 'check_in');
                formData.append('employee_id', employeeId);
                
                fetch('attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        function quickCheckOut(employeeId) {
            if (confirm('Mark this employee as checked out?')) {
                const formData = new FormData();
                formData.append('check_action', 'check_out');
                formData.append('employee_id', employeeId);
                
                fetch('attendance.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    location.reload();
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
            }
        }

        // --- 6. Select All Functionality ---
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('input[name^="attendance["]');
            checkboxes.forEach(cb => {
                cb.closest('tr').querySelector('input[type="checkbox"]').checked = checkbox.checked;
            });
        }

        // --- 7. Bulk Actions ---
        function applyBulkStatus() {
            const bulkStatus = document.getElementById('bulkStatus').value;
            if (!bulkStatus) {
                alert('Please select a status');
                return;
            }
            
            const selectedRows = document.querySelectorAll('input[type="checkbox"]:checked');
            selectedRows.forEach(checkbox => {
                const row = checkbox.closest('tr');
                const statusSelect = row.querySelector('.status-select');
                if (statusSelect) {
                    statusSelect.value = bulkStatus;
                }
            });
            
            alert(`Status set to ${bulkStatus} for ${selectedRows.length} employees`);
        }

        // --- 8. Mark All Present ---
        function markAllPresent() {
            if (confirm('Mark all employees as present?')) {
                const statusSelects = document.querySelectorAll('.status-select');
                statusSelects.forEach(select => {
                    select.value = 'present';
                });
                alert('All employees marked as present');
            }
        }

        // --- Initialize Sidebar State ---
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
        }

        // --- Auto-close dropdowns on escape key ---
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });

        // --- Auto-focus on search input ---
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.focus();
            searchInput.select();
        }

        // --- Calculate and display employee stats dynamically ---
        document.addEventListener('DOMContentLoaded', function() {
            // Update stats cards with animations if needed
            const statValues = document.querySelectorAll('.stat-card-value');
            statValues.forEach(stat => {
                const value = stat.textContent;
                if (value.includes(',')) {
                    stat.textContent = value;
                }
            });
            
            // Set default times
            const timeInputs = document.querySelectorAll('.time-input');
            timeInputs.forEach(input => {
                if (!input.value) {
                    if (input.name.includes('check_in')) {
                        input.value = '09:00';
                    } else if (input.name.includes('check_out')) {
                        input.value = '17:00';
                    }
                }
            });
        });
        
        // --- Date picker functionality ---
        document.addEventListener('DOMContentLoaded', function() {
            const dateInput = document.querySelector('input[name="date"]');
            if (dateInput) {
                dateInput.addEventListener('change', function() {
                    this.closest('form').submit();
                });
            }
        });
    </script>
</body>
</html>