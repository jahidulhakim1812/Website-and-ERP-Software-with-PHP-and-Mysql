<?php
/**
 * work_order.php
 * Work Order Management - Share Hosting Compatible
 * List all work orders with filtering, sorting, and management actions
 */

session_start();

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Logout Action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit;
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

// Create tables if they don't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS subcontractor_work_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_order_number VARCHAR(50) UNIQUE NOT NULL,
        subcontractor_id INT NOT NULL,
        project_name VARCHAR(255) NOT NULL,
        work_description TEXT,
        start_date DATE,
        end_date DATE,
        estimated_cost DECIMAL(15,2) DEFAULT 0.00,
        actual_cost DECIMAL(15,2) DEFAULT 0.00,
        work_status ENUM('Draft', 'Assigned', 'In Progress', 'On Hold', 'Completed', 'Cancelled') DEFAULT 'Draft',
        priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
        location VARCHAR(255),
        supervisor VARCHAR(255),
        completed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_status (work_status),
        INDEX idx_subcontractor (subcontractor_id),
        INDEX idx_priority (priority)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS work_order_materials (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_order_id INT NOT NULL,
        material_name VARCHAR(255) NOT NULL,
        quantity DECIMAL(10,2) DEFAULT 1,
        unit_price DECIMAL(10,2) DEFAULT 0.00,
        total_price DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (work_order_id) REFERENCES subcontractor_work_orders(id) ON DELETE CASCADE,
        INDEX idx_work_order (work_order_id)
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS work_order_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        work_order_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        payment_date DATE NOT NULL,
        payment_method ENUM('Cash', 'Check', 'Bank Transfer', 'Credit Card') DEFAULT 'Bank Transfer',
        reference_number VARCHAR(100),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (work_order_id) REFERENCES subcontractor_work_orders(id) ON DELETE CASCADE,
        INDEX idx_work_order (work_order_id)
    )");
    
    // Note: payment_activities table may already exist; we do not create it here.
} catch (PDOException $e) {
    // Tables might already exist, continue
    error_log("Table creation note: " . $e->getMessage());
}

// --- 4. HANDLE ACTIONS WITH CSRF PROTECTION ---
$message = '';
$msg_type = '';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Define all related tables that must be cleared before deleting a work order
$related_tables = ['work_order_materials', 'work_order_payments', 'payment_activities'];

// Handle Delete with CSRF
if (isset($_GET['delete_id']) && is_numeric($_GET['delete_id']) && isset($_GET['csrf_token'])) {
    if (validateCsrfToken($_GET['csrf_token'])) {
        try {
            $pdo->beginTransaction();
            
            $work_order_id = intval($_GET['delete_id']);
            
            // Delete from all related tables
            foreach ($related_tables as $table) {
                $stmt = $pdo->prepare("DELETE FROM {$table} WHERE work_order_id = ?");
                $stmt->execute([$work_order_id]);
            }
            
            // Delete main work order
            $stmt = $pdo->prepare("DELETE FROM subcontractor_work_orders WHERE id = ?");
            $stmt->execute([$work_order_id]);
            
            $pdo->commit();
            $message = "Work order deleted successfully!";
            $msg_type = 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "Error deleting work order: " . $e->getMessage();
            $msg_type = 'error';
        }
    } else {
        $message = "Invalid security token!";
        $msg_type = 'error';
    }
}

// Handle Status Update with CSRF
if (isset($_GET['update_status']) && isset($_GET['csrf_token'])) {
    if (validateCsrfToken($_GET['csrf_token'])) {
        try {
            $data = json_decode(base64_decode($_GET['update_status']), true);
            $work_order_id = intval($data['id'] ?? 0);
            $new_status = $data['status'] ?? '';
            
            $allowed_statuses = ['Draft', 'Assigned', 'In Progress', 'On Hold', 'Completed', 'Cancelled'];
            
            if ($work_order_id && in_array($new_status, $allowed_statuses)) {
                $stmt = $pdo->prepare("UPDATE subcontractor_work_orders SET work_status = ? WHERE id = ?");
                $stmt->execute([$new_status, $work_order_id]);
                
                if ($new_status == 'Completed') {
                    $stmt = $pdo->prepare("UPDATE subcontractor_work_orders SET completed_at = NOW() WHERE id = ?");
                    $stmt->execute([$work_order_id]);
                }
                
                $message = "Work order status updated to " . $new_status;
                $msg_type = 'success';
            }
        } catch (Exception $e) {
            $message = "Error updating status: " . $e->getMessage();
            $msg_type = 'error';
        }
    } else {
        $message = "Invalid security token!";
        $msg_type = 'error';
    }
}

// Handle Bulk Actions with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action']) && isset($_POST['csrf_token'])) {
    if (validateCsrfToken($_POST['csrf_token'])) {
        try {
            $work_order_ids = array_map('intval', $_POST['selected_work_orders'] ?? []);
            
            if (empty($work_order_ids)) {
                throw new Exception("No work orders selected.");
            }
            
            $placeholders = str_repeat('?,', count($work_order_ids) - 1) . '?';
            
            switch ($_POST['bulk_action']) {
                case 'delete':
                    $pdo->beginTransaction();
                    
                    // Delete from all related tables
                    foreach ($related_tables as $table) {
                        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE work_order_id IN ($placeholders)");
                        $stmt->execute($work_order_ids);
                    }
                    
                    // Delete work orders
                    $stmt = $pdo->prepare("DELETE FROM subcontractor_work_orders WHERE id IN ($placeholders)");
                    $stmt->execute($work_order_ids);
                    
                    $pdo->commit();
                    $message = count($work_order_ids) . " work order(s) deleted successfully!";
                    $msg_type = 'success';
                    break;
                    
                case 'update_status':
                    if (!isset($_POST['bulk_status'])) {
                        throw new Exception("No status selected.");
                    }
                    
                    $allowed_statuses = ['Draft', 'Assigned', 'In Progress', 'On Hold', 'Completed', 'Cancelled'];
                    $new_status = $_POST['bulk_status'];
                    
                    if (!in_array($new_status, $allowed_statuses)) {
                        throw new Exception("Invalid status selected.");
                    }
                    
                    $stmt = $pdo->prepare("UPDATE subcontractor_work_orders SET work_status = ? WHERE id IN ($placeholders)");
                    $stmt->execute(array_merge([$new_status], $work_order_ids));
                    
                    $message = count($work_order_ids) . " work order(s) status updated to " . $new_status;
                    $msg_type = 'success';
                    break;
                    
                case 'export':
                    $ids_param = implode(',', $work_order_ids);
                    header("Location: work_order_pdf.php?ids=" . urlencode($ids_param) . "&csrf_token=" . urlencode($_SESSION['csrf_token']));
                    exit;
                    break;
            }
        } catch (Exception $e) {
            $message = "Bulk action error: " . $e->getMessage();
            $msg_type = 'error';
        }
    } else {
        $message = "Invalid security token!";
        $msg_type = 'error';
    }
}

// --- 5. GET USER PHOTO ---
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

// --- 6. FETCH WORK ORDERS WITH FILTERS ---
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? 'all';
$subcontractor_filter = $_GET['subcontractor'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$priority_filter = $_GET['priority'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'newest';

$allowed_statuses = ['Draft', 'Assigned', 'In Progress', 'On Hold', 'Completed', 'Cancelled'];
$allowed_priorities = ['Low', 'Medium', 'High', 'Urgent'];
$allowed_sorts = ['newest', 'oldest', 'project_asc', 'project_desc', 'number_asc', 'number_desc', 
                  'cost_high', 'cost_low', 'start_date', 'end_date'];

if (!in_array($status_filter, array_merge(['all'], $allowed_statuses))) {
    $status_filter = 'all';
}
if (!in_array($priority_filter, array_merge(['all'], $allowed_priorities))) {
    $priority_filter = 'all';
}
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'newest';
}

$query = "SELECT wo.*, 
                 s.company_name, 
                 s.contact_person,
                 s.specialization,
                 (SELECT COUNT(*) FROM work_order_materials WHERE work_order_id = wo.id) as material_count,
                 (SELECT COALESCE(SUM(total_price), 0) FROM work_order_materials WHERE work_order_id = wo.id) as materials_cost,
                 (SELECT COALESCE(SUM(amount), 0) FROM work_order_payments WHERE work_order_id = wo.id) as total_payments
          FROM subcontractor_work_orders wo
          LEFT JOIN subcontractors s ON wo.subcontractor_id = s.id
          WHERE 1=1";

$params = [];

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

switch ($sort_by) {
    case 'number_asc': $query .= " ORDER BY wo.work_order_number ASC"; break;
    case 'number_desc': $query .= " ORDER BY wo.work_order_number DESC"; break;
    case 'project_asc': $query .= " ORDER BY wo.project_name ASC"; break;
    case 'project_desc': $query .= " ORDER BY wo.project_name DESC"; break;
    case 'cost_high': $query .= " ORDER BY wo.estimated_cost DESC"; break;
    case 'cost_low': $query .= " ORDER BY wo.estimated_cost ASC"; break;
    case 'start_date': $query .= " ORDER BY wo.start_date ASC"; break;
    case 'end_date': $query .= " ORDER BY wo.end_date ASC"; break;
    case 'oldest': $query .= " ORDER BY wo.created_at ASC"; break;
    default: $query .= " ORDER BY wo.created_at DESC"; break;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $work_orders = $stmt->fetchAll();
} catch (Exception $e) {
    $work_orders = [];
    $message = "Error fetching work orders: " . $e->getMessage();
    $msg_type = 'error';
}

// Get stats for filters
try {
    $total_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders")->fetch()['count'];
    $assigned_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE work_status = 'Assigned'")->fetch()['count'];
    $in_progress_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE work_status = 'In Progress'")->fetch()['count'];
    $completed_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE work_status = 'Completed'")->fetch()['count'];
    $on_hold_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE work_status = 'On Hold'")->fetch()['count'];
    $cancelled_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE work_status = 'Cancelled'")->fetch()['count'];
    
    $total_estimated = $pdo->query("SELECT COALESCE(SUM(estimated_cost), 0) as total FROM subcontractor_work_orders")->fetch()['total'];
    $total_actual = $pdo->query("SELECT COALESCE(SUM(actual_cost), 0) as total FROM subcontractor_work_orders")->fetch()['total'];
    $total_materials = $pdo->query("SELECT COALESCE(SUM(total_price), 0) as total FROM work_order_materials")->fetch()['total'];
    $total_payments = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM work_order_payments")->fetch()['total'];
    
    $subcontractors = $pdo->query("SELECT id, company_name FROM subcontractors WHERE status = 'Active' ORDER BY company_name")->fetchAll();
    
    $overdue_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE end_date < CURDATE() AND work_status NOT IN ('Completed', 'Cancelled')")->fetch()['count'];
    
    $recent_activity = $pdo->query("SELECT * FROM subcontractor_work_orders ORDER BY updated_at DESC LIMIT 10")->fetchAll();
    
} catch (Exception $e) {
    $total_count = $assigned_count = $in_progress_count = $completed_count = $on_hold_count = $cancelled_count = 0;
    $total_estimated = $total_actual = $total_materials = $total_payments = 0;
    $subcontractors = [];
    $overdue_count = 0;
    $recent_activity = [];
}

// --- 7. PAGINATION ---
$per_page = 15;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;
$total_pages = ceil(count($work_orders) / $per_page);
$paged_work_orders = array_slice($work_orders, $offset, $per_page);

// Create PDF parameters for download link
$pdf_params = $_GET;
unset($pdf_params['delete_id'], $pdf_params['toggle_status'], $pdf_params['page']);
$pdf_query_string = http_build_query($pdf_params);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Order Management | NexusAdmin</title>
    
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
            --shadow: 0 2px 8px rgba(0,0,0,0.04);
            --shadow-hover: 0 8px 25px rgba(0,0,0,0.08);
            --color-success: #10B981;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;
            --color-info: #3B82F6;
            --color-assigned: #8B5CF6;
            --color-progress: #3B82F6;
            --color-completed: #10B981;
            --color-hold: #F59E0B;
            --color-cancelled: #6B7280;
            --color-draft: #9CA3AF;
            --color-priority-high: #EF4444;
            --color-priority-medium: #F59E0B;
            --color-priority-low: #10B981;
            --color-priority-urgent: #DC2626;
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
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            text-decoration: none;
            border: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        
        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--border);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--color-success);
            color: white;
        }
        
        .btn-success:hover {
            background: #0da271;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: var(--color-danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        .btn-pdf {
            background: #EF4444;
            color: white;
        }
        
        .btn-pdf:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        
        .btn-icon {
            padding: 8px;
            width: 36px;
            height: 36px;
            justify-content: center;
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.95rem;
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
            background: rgba(16, 185, 129, 0.12);
            color: var(--color-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            color: var(--color-danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert i {
            font-size: 1.2rem;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .stat-card.total::before { background: var(--primary); }
        .stat-card.assigned::before { background: var(--color-assigned); }
        .stat-card.progress::before { background: var(--color-progress); }
        .stat-card.completed::before { background: var(--color-success); }
        .stat-card.hold::before { background: var(--color-warning); }
        .stat-card.cancelled::before { background: var(--color-cancelled); }
        .stat-card.overdue::before { background: var(--color-danger); }
        .stat-card.financial::before { background: var(--color-info); }
        .stat-card.payments::before { background: var(--color-success); }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .stat-trend {
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 12px;
            margin-left: 8px;
        }
        
        .trend-up { background: rgba(16, 185, 129, 0.1); color: var(--color-success); }
        .trend-down { background: rgba(239, 68, 68, 0.1); color: var(--color-danger); }

        /* Search Box */
        .search-box {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 24px;
        }
        
        .search-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 12px;
        }
        
        @media (max-width: 1200px) {
            .search-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .search-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        
        .search-input, .filter-select {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.9rem;
            background: var(--bg-body);
            color: var(--text-main);
            outline: none;
            transition: 0.2s;
            width: 100%;
        }
        
        .search-input:focus, .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 8px;
        }

        /* Table */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.05) 0%, rgba(79, 70, 229, 0.02) 100%);
        }
        
        .table-title {
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .table-actions {
            display: flex;
            gap: 8px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
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

        /* Work Order Number */
        .wo-number {
            font-family: 'Monaco', 'Courier New', monospace;
            font-weight: 600;
            color: var(--primary);
            font-size: 0.95rem;
        }
        
        .wo-number a:hover {
            text-decoration: underline;
        }

        /* Project Info */
        .project-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .project-name {
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .project-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            max-height: 2.4em;
        }

        /* Subcontractor Cell */
        .subcontractor-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sub-avatar {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }
        
        .sub-info {
            display: flex;
            flex-direction: column;
        }
        
        .sub-name {
            font-weight: 500;
            margin-bottom: 2px;
        }
        
        .sub-spec {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Dates */
        .date-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .date-value {
            font-size: 0.9rem;
        }
        
        .date-info {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .date-overdue {
            color: var(--color-danger);
            font-weight: 600;
        }
        
        .date-upcoming {
            color: var(--color-success);
            font-weight: 600;
        }

        /* Status Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 4px;
            white-space: nowrap;
            text-transform: uppercase;
        }
        
        .badge-success {
            background: rgba(16, 185, 129, 0.12);
            color: var(--color-success);
        }
        
        .badge-warning {
            background: rgba(245, 158, 11, 0.12);
            color: var(--color-warning);
        }
        
        .badge-danger {
            background: rgba(239, 68, 68, 0.12);
            color: var(--color-danger);
        }
        
        .badge-info {
            background: rgba(59, 130, 246, 0.12);
            color: var(--color-info);
        }
        
        .badge-secondary {
            background: rgba(156, 163, 175, 0.12);
            color: var(--color-cancelled);
        }
        
        .badge-primary {
            background: rgba(139, 92, 246, 0.12);
            color: var(--color-assigned);
        }

        /* Priority Badges */
        .priority-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
        }
        
        .priority-high {
            background: rgba(239, 68, 68, 0.12);
            color: var(--color-priority-high);
        }
        
        .priority-urgent {
            background: rgba(220, 38, 38, 0.12);
            color: var(--color-priority-urgent);
        }
        
        .priority-medium {
            background: rgba(245, 158, 11, 0.12);
            color: var(--color-priority-medium);
        }
        
        .priority-low {
            background: rgba(16, 185, 129, 0.12);
            color: var(--color-priority-low);
        }

        /* Cost Cell */
        .cost-cell {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .estimated-cost {
            font-weight: 600;
            color: var(--text-main);
        }
        
        .actual-cost {
            font-size: 0.85rem;
        }
        
        .cost-over {
            color: var(--color-danger);
        }
        
        .cost-under {
            color: var(--color-success);
        }
        
        .cost-equal {
            color: var(--text-muted);
        }

        /* Materials Info */
        .materials-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .materials-count {
            font-weight: 600;
            color: var(--primary);
        }
        
        .materials-cost {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }
        
        .action-btn {
            padding: 8px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-muted);
            transition: all 0.2s ease;
            cursor: pointer;
            font-size: 0.85rem;
            text-decoration: none;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
        }
        
        .action-btn.view:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .action-btn.edit:hover {
            background: #3B82F6;
            color: white;
            border-color: #3B82F6;
        }
        
        .action-btn.delete:hover {
            background: #EF4444;
            color: white;
            border-color: #EF4444;
        }
        
        .action-btn.clone:hover {
            background: var(--color-info);
            color: white;
            border-color: var(--color-info);
        }
        
        .action-btn.status:hover {
            background: var(--color-success);
            color: white;
            border-color: var(--color-success);
        }

        /* Status Toggle Button */
        .status-toggle {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .status-toggle:hover {
            background: var(--border);
        }

        /* Checkbox Column */
        .select-checkbox {
            width: 40px;
            text-align: center;
        }
        
        .select-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--bg-body);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            display: none;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            border: 1px solid var(--border);
        }
        
        .bulk-actions.show {
            display: flex;
        }
        
        .bulk-selection {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .bulk-controls {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
        }
        
        .pagination-info {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
        }
        
        .page-btn {
            padding: 8px 12px;
            border-radius: 6px;
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-main);
            font-size: 0.85rem;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .page-btn:hover {
            background: var(--bg-card);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-btn.disabled:hover {
            background: var(--bg-body);
            border-color: var(--border);
            color: var(--text-main);
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
            
            .search-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .filter-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .data-table th, .data-table td {
                padding: 12px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .table-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .bulk-controls {
                width: 100%;
            }
            
            .pagination {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
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
            
            .subcontractor-cell {
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
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Work Order Management</span>
                </div>
            </div>

            <div class="header-right">
                <!-- Dark Mode Toggle Button -->
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($_SESSION['username']); ?></span>
                            <span class="profile-role"><?php echo ucfirst(e($_SESSION['role'])); ?></span>
                        </div>
                        <?php if (!empty($userPhoto)): ?>
                            <img src="<?php echo e($userPhoto); ?>" alt="Profile" class="profile-img">
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>                          
                    </div>

                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="admin_dashboard.php" class="dropdown-item">
                            <i class="ph ph-house" style="font-size: 1.1rem;"></i> 
                            <span>Dashboard</span>
                        </a>
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
                    <h1 class="page-title">Work Order Management</h1>
                    <p class="page-subtitle">
                        Manage all work orders, track progress, and monitor costs. 
                        Currently tracking <?php echo $total_count; ?> work orders.
                    </p>
                </div>
                <a href="create_work_order.php" class="btn btn-primary">
                    <i class="ph ph-plus-circle"></i>
                    Create New Work Order
                </a>
            </div>

            <!-- Alert Messages -->
            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type == 'success' ? 'success' : 'error'; ?>">
                    <i class="ph <?php echo $msg_type == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-cards">
                <div class="stat-card total">
                    <div class="stat-number"><?php echo $total_count; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-clipboard-text"></i>
                        Total Work Orders
                    </div>
                </div>
                <div class="stat-card progress">
                    <div class="stat-number"><?php echo $in_progress_count; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-activity"></i>
                        In Progress
                    </div>
                </div>
                <div class="stat-card completed">
                    <div class="stat-number"><?php echo $completed_count; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-check-circle"></i>
                        Completed
                    </div>
                </div>
                <div class="stat-card overdue">
                    <div class="stat-number"><?php echo $overdue_count; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-warning-circle"></i>
                        Overdue
                        <?php if($overdue_count > 0): ?>
                            <span class="stat-trend trend-down"><?php echo $overdue_count; ?> urgent</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card assigned">
                    <div class="stat-number"><?php echo $assigned_count; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-user-plus"></i>
                        Assigned
                    </div>
                </div>
                <div class="stat-card hold">
                    <div class="stat-number"><?php echo $on_hold_count; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-pause-circle"></i>
                        On Hold
                    </div>
                </div>
                <div class="stat-card cancelled">
                    <div class="stat-number"><?php echo $cancelled_count; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-x-circle"></i>
                        Cancelled
                    </div>
                </div>
                <div class="stat-card financial">
                    <div class="stat-number"><?php echo formatCurrency($total_estimated); ?></div>
                    <div class="stat-label">
                        <i class="ph ph-currency-dollar"></i>
                        Total Estimated
                    </div>
                </div>
                <div class="stat-card payments">
                    <div class="stat-number"><?php echo formatCurrency($total_payments); ?></div>
                    <div class="stat-label">
                        <i class="ph ph-credit-card"></i>
                        Total Payments
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <form method="GET" class="search-box">
                <div class="search-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="search-input" 
                               placeholder="Work order #, project, or subcontractor..."
                               value="<?php echo e($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="Draft" <?php echo $status_filter == 'Draft' ? 'selected' : ''; ?>>Draft</option>
                            <option value="Assigned" <?php echo $status_filter == 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                            <option value="In Progress" <?php echo $status_filter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Completed" <?php echo $status_filter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="On Hold" <?php echo $status_filter == 'On Hold' ? 'selected' : ''; ?>>On Hold</option>
                            <option value="Cancelled" <?php echo $status_filter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Subcontractor</label>
                        <select name="subcontractor" class="filter-select">
                            <option value="all" <?php echo $subcontractor_filter == 'all' ? 'selected' : ''; ?>>All Subcontractors</option>
                            <?php foreach ($subcontractors as $sub): ?>
                                <option value="<?php echo $sub['id']; ?>" <?php echo $subcontractor_filter == $sub['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($sub['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Priority</label>
                        <select name="priority" class="filter-select">
                            <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>All Priorities</option>
                            <option value="Low" <?php echo $priority_filter == 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo $priority_filter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo $priority_filter == 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Urgent" <?php echo $priority_filter == 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Start Date From</label>
                        <input type="date" name="date_from" class="search-input" value="<?php echo e($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">End Date To</label>
                        <input type="date" name="date_to" class="search-input" value="<?php echo e($date_to); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Sort By</label>
                        <select name="sort" class="filter-select">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="project_asc" <?php echo $sort_by == 'project_asc' ? 'selected' : ''; ?>>Project A-Z</option>
                            <option value="project_desc" <?php echo $sort_by == 'project_desc' ? 'selected' : ''; ?>>Project Z-A</option>
                            <option value="number_asc" <?php echo $sort_by == 'number_asc' ? 'selected' : ''; ?>>WO# Ascending</option>
                            <option value="number_desc" <?php echo $sort_by == 'number_desc' ? 'selected' : ''; ?>>WO# Descending</option>
                            <option value="cost_high" <?php echo $sort_by == 'cost_high' ? 'selected' : ''; ?>>Highest Cost</option>
                            <option value="cost_low" <?php echo $sort_by == 'cost_low' ? 'selected' : ''; ?>>Lowest Cost</option>
                            <option value="start_date" <?php echo $sort_by == 'start_date' ? 'selected' : ''; ?>>Start Date</option>
                            <option value="end_date" <?php echo $sort_by == 'end_date' ? 'selected' : ''; ?>>End Date</option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="ph ph-magnifying-glass"></i>
                        Apply Filters
                    </button>
                    <a href="work_order.php" class="btn btn-secondary">
                        <i class="ph ph-arrow-clockwise"></i>
                        Reset Filters
                    </a>
                </div>
            </form>

            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <div class="bulk-selection">
                    <input type="checkbox" id="selectAllBulk" onchange="toggleAllSelection()">
                    <span id="selectedCount">0 work orders selected</span>
                </div>
                <form method="POST" action="" class="bulk-controls" id="bulkForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <input type="hidden" name="bulk_action" id="bulkAction">
                    <select name="bulk_status" class="filter-select" style="min-width: 120px;">
                        <option value="">Select Status</option>
                        <option value="Assigned">Assigned</option>
                        <option value="In Progress">In Progress</option>
                        <option value="Completed">Completed</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="bulkUpdateStatus()">
                        <i class="ph ph-arrows-clockwise"></i> Update Status
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="bulkExport()">
                        <i class="ph ph-export"></i> Export Selected
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                        <i class="ph ph-trash"></i> Delete Selected
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                        <i class="ph ph-x"></i> Clear Selection
                    </button>
                </form>
            </div>

            <!-- Work Orders Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        <i class="ph ph-clipboard-text"></i>
                        Work Order Records
                        <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: normal; margin-left: 8px;">
                            (Showing <?php echo count($paged_work_orders); ?> of <?php echo count($work_orders); ?>)
                        </span>
                    </div>
                    <div class="table-actions">
                        <form method="GET" action="work_order_pdf.php" target="_blank" style="display: inline;">
                            <?php 
                            // Pass all current filters to PDF
                            $pdf_params = $_GET;
                            unset($pdf_params['delete_id']);
                            unset($pdf_params['toggle_status']);
                            unset($pdf_params['page']);
                            unset($pdf_params['csrf_token']);
                            
                            foreach($pdf_params as $key => $value): 
                                if (!empty($value)): ?>
                                    <input type="hidden" name="<?php echo e($key); ?>" value="<?php echo e($value); ?>">
                                <?php endif;
                            endforeach; ?>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <button type="submit" class="btn btn-pdf">
                                <i class="ph ph-file-pdf"></i>
                                Generate PDF Report
                            </button>
                        </form>
                        
                        <button class="btn btn-secondary" onclick="window.print()">
                            <i class="ph ph-printer"></i>
                            Print
                        </button>
                        
                        <button class="btn btn-success" onclick="exportToCSV()">
                            <i class="ph ph-file-csv"></i>
                            Export CSV
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="select-checkbox">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>WORK ORDER #</th>
                                <th>PROJECT</th>
                                <th>SUBCONTRACTOR</th>
                                <th>DATES</th>
                                <th>STATUS</th>
                                <th>COST</th>
                                <th>MATERIALS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($paged_work_orders) > 0): ?>
                                <?php foreach ($paged_work_orders as $wo): ?>
                                <?php 
                                    // Status badge class
                                    $statusClass = 'badge-secondary';
                                    $statusIcon = 'ph-clock';
                                    switch($wo['work_status']) {
                                        case 'Assigned': 
                                            $statusClass = 'badge-primary'; 
                                            $statusIcon = 'ph-user-plus'; 
                                            break;
                                        case 'In Progress': 
                                            $statusClass = 'badge-info'; 
                                            $statusIcon = 'ph-activity'; 
                                            break;
                                        case 'Completed': 
                                            $statusClass = 'badge-success'; 
                                            $statusIcon = 'ph-check-circle'; 
                                            break;
                                        case 'On Hold': 
                                            $statusClass = 'badge-warning'; 
                                            $statusIcon = 'ph-pause-circle'; 
                                            break;
                                        case 'Cancelled': 
                                            $statusClass = 'badge-danger'; 
                                            $statusIcon = 'ph-x-circle'; 
                                            break;
                                        default: 
                                            $statusClass = 'badge-secondary'; 
                                            $statusIcon = 'ph-clipboard-text';
                                    }
                                    
                                    // Priority badge class
                                    $priorityClass = 'priority-medium';
                                    switch($wo['priority']) {
                                        case 'Low': $priorityClass = 'priority-low'; break;
                                        case 'Medium': $priorityClass = 'priority-medium'; break;
                                        case 'High': $priorityClass = 'priority-high'; break;
                                        case 'Urgent': $priorityClass = 'priority-urgent'; break;
                                    }
                                    
                                    // Check if overdue
                                    $isOverdue = false;
                                    if (!empty($wo['end_date']) && $wo['end_date'] < date('Y-m-d') && 
                                        !in_array($wo['work_status'], ['Completed', 'Cancelled'])) {
                                        $isOverdue = true;
                                    }
                                    
                                    // Cost calculations
                                    $materials_cost = floatval($wo['materials_cost'] ?? 0);
                                    $estimated_cost = floatval($wo['estimated_cost'] ?? 0);
                                    $actual_cost = floatval($wo['actual_cost'] ?? 0);
                                    $total_payments = floatval($wo['total_payments'] ?? 0);
                                    
                                    $cost_class = 'cost-equal';
                                    if ($actual_cost > 0) {
                                        if ($actual_cost > $estimated_cost) {
                                            $cost_class = 'cost-over';
                                        } elseif ($actual_cost < $estimated_cost) {
                                            $cost_class = 'cost-under';
                                        }
                                    }
                                    
                                    // Time since creation
                                    $created = new DateTime($wo['created_at']);
                                    $today = new DateTime();
                                    $interval = $today->diff($created);
                                    if ($interval->y > 0) {
                                        $timeSince = $interval->y . ' year' . ($interval->y > 1 ? 's' : '') . ' ago';
                                    } elseif ($interval->m > 0) {
                                        $timeSince = $interval->m . ' month' . ($interval->m > 1 ? 's' : '') . ' ago';
                                    } elseif ($interval->d > 0) {
                                        $timeSince = $interval->d . ' day' . ($interval->d > 1 ? 's' : '') . ' ago';
                                    } elseif ($interval->h > 0) {
                                        $timeSince = $interval->h . ' hour' . ($interval->h > 1 ? 's' : '') . ' ago';
                                    } else {
                                        $timeSince = 'Just now';
                                    }
                                ?>
                                <tr>
                                    <td class="select-checkbox">
                                        <input type="checkbox" class="work-order-checkbox" 
                                               name="selected_work_orders[]" 
                                               value="<?php echo $wo['id']; ?>"
                                               onchange="updateBulkActions()">
                                    </td>
                                    <td>
                                        <div class="wo-number">
                                            <a href="edit_work_order.php?id=<?php echo $wo['id']; ?>" title="View Details">
                                                <?php echo e($wo['work_order_number']); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="project-info">
                                            <div class="project-name"><?php echo e($wo['project_name']); ?></div>
                                            <div class="project-desc" title="<?php echo e($wo['work_description']); ?>">
                                                <?php echo strlen($wo['work_description']) > 50 ? substr($wo['work_description'], 0, 50) . '...' : $wo['work_description']; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="subcontractor-cell">
                                            <div class="sub-avatar">
                                                <?php echo strtoupper(substr($wo['company_name'] ?? 'NA', 0, 2)); ?>
                                            </div>
                                            <div class="sub-info">
                                                <div class="sub-name"><?php echo e($wo['company_name'] ?? 'N/A'); ?></div>
                                                <div class="sub-spec"><?php echo e($wo['specialization'] ?? 'N/A'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="date-cell">
                                            <div class="date-value">
                                                <?php echo !empty($wo['start_date']) ? date('M d, Y', strtotime($wo['start_date'])) : 'Not Set'; ?>
                                                →
                                                <?php echo !empty($wo['end_date']) ? date('M d, Y', strtotime($wo['end_date'])) : 'Not Set'; ?>
                                            </div>
                                            <div class="date-info">
                                                <?php if($isOverdue): ?>
                                                    <span class="date-overdue">
                                                        <i class="ph ph-warning-circle"></i> Overdue
                                                    </span>
                                                <?php elseif(!empty($wo['end_date'])): ?>
                                                    <span class="date-upcoming">
                                                        <?php echo daysRemaining($wo['end_date']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $statusClass; ?>">
                                            <i class="ph <?php echo $statusIcon; ?>"></i> <?php echo e($wo['work_status']); ?>
                                        </span>
                                        <div style="margin-top: 4px;">
                                            <span class="priority-badge <?php echo $priorityClass; ?>">
                                                <?php echo e($wo['priority']); ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="cost-cell">
                                            <div class="estimated-cost">
                                                <?php echo formatCurrency($estimated_cost); ?>
                                            </div>
                                            <?php if($actual_cost > 0): ?>
                                                <div class="actual-cost <?php echo $cost_class; ?>">
                                                    Actual: <?php echo formatCurrency($actual_cost); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if($total_payments > 0): ?>
                                                <div class="actual-cost" style="color: var(--color-info);">
                                                    Paid: <?php echo formatCurrency($total_payments); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="materials-info">
                                            <div class="materials-count">
                                                <i class="ph ph-package"></i> <?php echo $wo['material_count'] ?? 0; ?> items
                                            </div>
                                            <div class="materials-cost">
                                                <?php echo formatCurrency($materials_cost); ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit_work_order.php?id=<?php echo $wo['id']; ?>" class="action-btn view" title="View Details">
                                                <i class="ph ph-eye"></i> View
                                            </a>
                                            <a href="edit_work_order.php?id=<?php echo $wo['id']; ?>&edit=true" class="action-btn edit" title="Edit">
                                                <i class="ph ph-pencil-simple"></i> Edit
                                            </a>
                                            <a href="?delete_id=<?php echo $wo['id']; ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>" 
                                               class="action-btn delete" title="Delete" 
                                               onclick="return confirm('Are you sure you want to delete this work order?')">
                                                <i class="ph ph-trash"></i> Delete
                                            </a>
                                        </div>
                                        <a href="?update_status=<?php echo urlencode(base64_encode(json_encode(['id' => $wo['id'], 'status' => 'Completed']))); ?>&csrf_token=<?php echo urlencode($_SESSION['csrf_token']); ?>" 
                                           class="status-toggle" 
                                           onclick="return confirm('Mark this work order as completed?')">
                                            <i class="ph ph-check-circle"></i>
                                            Mark Complete
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9">
                                        <div class="empty-state">
                                            <div class="empty-state-icon">
                                                <i class="ph ph-clipboard-text"></i>
                                            </div>
                                            <div class="empty-state-title">No work orders found</div>
                                            <div class="empty-state-description">
                                                <?php if($search || $status_filter != 'all' || $subcontractor_filter != 'all' || !empty($date_from) || !empty($date_to)): ?>
                                                    No work orders match your search criteria. Try adjusting your filters.
                                                <?php else: ?>
                                                    You haven't created any work orders yet. Create your first work order to get started.
                                                <?php endif; ?>
                                            </div>
                                            <a href="create_work_order.php" class="btn btn-primary">
                                                <i class="ph ph-plus-circle"></i>
                                                Create Your First Work Order
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Page <?php echo $page; ?> of <?php echo $total_pages; ?> • 
                        <?php echo count($work_orders); ?> total work orders
                    </div>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn">
                                <i class="ph ph-caret-double-left"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="page-btn">
                                <i class="ph ph-caret-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="ph ph-caret-double-left"></i>
                            </span>
                            <span class="page-btn disabled">
                                <i class="ph ph-caret-left"></i>
                            </span>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): 
                        ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="page-btn">
                                <i class="ph ph-caret-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn">
                                <i class="ph ph-caret-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="ph ph-caret-right"></i>
                            </span>
                            <span class="page-btn disabled">
                                <i class="ph ph-caret-double-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 40px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                &copy; <?php echo date('Y'); ?> NexusAdmin System.
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

        // --- 5. Bulk Actions ---
        let selectedWorkOrders = new Set();

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.work-order-checkbox:checked');
            selectedWorkOrders.clear();
            
            checkboxes.forEach(cb => {
                selectedWorkOrders.add(cb.value);
            });
            
            const selectedCount = selectedWorkOrders.size;
            const bulkActions = document.getElementById('bulkActions');
            const selectedCountSpan = document.getElementById('selectedCount');
            
            if (selectedCount > 0) {
                bulkActions.classList.add('show');
                selectedCountSpan.textContent = selectedCount + ' work order(s) selected';
            } else {
                bulkActions.classList.remove('show');
                selectedCountSpan.textContent = '0 work orders selected';
            }
            
            // Update select all checkbox
            const totalCheckboxes = document.querySelectorAll('.work-order-checkbox').length;
            const selectAll = document.getElementById('selectAll');
            const selectAllBulk = document.getElementById('selectAllBulk');
            
            if (selectedCount === totalCheckboxes && totalCheckboxes > 0) {
                selectAll.checked = true;
                selectAllBulk.checked = true;
            } else {
                selectAll.checked = false;
                selectAllBulk.checked = false;
            }
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll').checked;
            const checkboxes = document.querySelectorAll('.work-order-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll;
            });
            
            updateBulkActions();
        }

        function toggleAllSelection() {
            const selectAllBulk = document.getElementById('selectAllBulk').checked;
            const checkboxes = document.querySelectorAll('.work-order-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAllBulk;
            });
            
            updateBulkActions();
        }

        function clearSelection() {
            const checkboxes = document.querySelectorAll('.work-order-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            updateBulkActions();
        }

        function bulkUpdateStatus() {
            const statusSelect = document.querySelector('select[name="bulk_status"]');
            if (!statusSelect.value) {
                alert('Please select a status first.');
                return;
            }
            
            if (selectedWorkOrders.size === 0) {
                alert('Please select at least one work order.');
                return;
            }
            
            if (confirm('Update status of ' + selectedWorkOrders.size + ' work order(s) to ' + statusSelect.value + '?')) {
                document.getElementById('bulkAction').value = 'update_status';
                document.getElementById('bulkForm').submit();
            }
        }

        function bulkExport() {
            if (selectedWorkOrders.size === 0) {
                alert('Please select at least one work order.');
                return;
            }
            
            // Create a form to submit
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = 'work_order_pdf.php';
            form.target = '_blank';
            
            // Add CSRF token
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = document.querySelector('input[name="csrf_token"]').value;
            form.appendChild(csrfInput);
            
            // Add selected IDs
            const idsInput = document.createElement('input');
            idsInput.type = 'hidden';
            idsInput.name = 'ids';
            idsInput.value = Array.from(selectedWorkOrders).join(',');
            form.appendChild(idsInput);
            
            // Submit the form
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        function bulkDelete() {
            if (selectedWorkOrders.size === 0) {
                alert('Please select at least one work order.');
                return;
            }
            
            if (confirm('Are you sure you want to delete ' + selectedWorkOrders.size + ' work order(s)? This action cannot be undone.')) {
                document.getElementById('bulkAction').value = 'delete';
                document.getElementById('bulkForm').submit();
            }
        }

        // --- 6. Row Selection ---
        document.querySelectorAll('.work-order-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkActions);
        });

        // --- 7. CSV Export Function ---
        function exportToCSV() {
            let csv = [];
            
            // Create header row
            let header = ['WO Number', 'Project', 'Subcontractor', 'Status', 'Priority', 'Start Date', 'End Date', 'Estimated Cost', 'Actual Cost', 'Payments', 'Materials'];
            csv.push(header.join(','));
            
            // Get data from table rows
            document.querySelectorAll('.data-table tbody tr').forEach(row => {
                if (row.querySelector('.empty-state')) return;
                
                let rowData = [];
                
                // Get work order number
                let woNumber = row.querySelector('.wo-number a')?.textContent.trim() || '';
                rowData.push(`"${woNumber}"`);
                
                // Get project name
                let projectName = row.querySelector('.project-name')?.textContent.trim() || '';
                rowData.push(`"${projectName}"`);
                
                // Get subcontractor
                let subName = row.querySelector('.sub-name')?.textContent.trim() || '';
                rowData.push(`"${subName}"`);
                
                // Get status
                let status = row.querySelector('.badge')?.textContent.trim() || '';
                rowData.push(`"${status}"`);
                
                // Get priority
                let priority = row.querySelector('.priority-badge')?.textContent.trim() || '';
                rowData.push(`"${priority}"`);
                
                // Get dates
                let dates = row.querySelector('.date-value')?.textContent.trim() || '';
                let dateParts = dates.split('→');
                let startDate = dateParts[0]?.trim() || 'Not Set';
                let endDate = dateParts[1]?.trim() || 'Not Set';
                rowData.push(`"${startDate}"`, `"${endDate}"`);
                
                // Get costs
                let estimatedCost = row.querySelector('.estimated-cost')?.textContent.trim() || '$0.00';
                let actualCostElem = row.querySelector('.actual-cost');
                let actualCost = actualCostElem?.textContent.includes('Actual:') 
                    ? actualCostElem.textContent.replace('Actual:', '').trim()
                    : '$0.00';
                let paymentsElem = row.querySelector('.actual-cost[style*="color: var(--color-info)"]');
                let payments = paymentsElem?.textContent.includes('Paid:') 
                    ? paymentsElem.textContent.replace('Paid:', '').trim()
                    : '$0.00';
                
                rowData.push(`"${estimatedCost}"`, `"${actualCost}"`, `"${payments}"`);
                
                // Get materials
                let materialsCount = row.querySelector('.materials-count')?.textContent.trim() || '0 items';
                rowData.push(`"${materialsCount}"`);
                
                csv.push(rowData.join(','));
            });
            
            // Download CSV
            let csvContent = "data:text/csv;charset=utf-8," + csv.join('\n');
            let encodedUri = encodeURI(csvContent);
            let link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "work_orders_<?php echo date('Y-m-d'); ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // --- 8. Simple PDF Fallback ---
        function generateSimplePDF() {
            // Open a new window with a simple report
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>Work Order Report</title>
                    <style>
                        body { font-family: Arial; padding: 20px; }
                        h1 { color: #333; }
                        table { width: 100%; border-collapse: collapse; }
                        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                        th { background-color: #f2f2f2; }
                        @media print {
                            body { margin: 0; padding: 10px; }
                            button { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Work Order Report</h1>
                    <p>Generated: ${new Date().toLocaleString()}</p>
                    <button onclick="window.print()">Print Report</button>
                    <table>
                        <tr>
                            <th>WO #</th>
                            <th>Project</th>
                            <th>Subcontractor</th>
                            <th>Status</th>
                            <th>Estimated Cost</th>
                        </tr>
                        ${Array.from(document.querySelectorAll('.data-table tbody tr'))
                            .map(row => {
                                if (row.querySelector('.empty-state')) return '';
                                const cells = row.querySelectorAll('td');
                                return `
                                <tr>
                                    <td>${cells[1]?.textContent || ''}</td>
                                    <td>${cells[2]?.querySelector('.project-name')?.textContent || ''}</td>
                                    <td>${cells[3]?.querySelector('.sub-name')?.textContent || ''}</td>
                                    <td>${cells[5]?.querySelector('.badge')?.textContent || ''}</td>
                                    <td>${cells[6]?.querySelector('.estimated-cost')?.textContent || ''}</td>
                                </tr>`;
                            })
                            .join('')}
                    </table>
                </body>
                </html>
            `);
            printWindow.document.close();
        }

        // --- 9. Keyboard Shortcuts ---
        document.addEventListener('keydown', function(e) {
            // Ctrl+F for search focus
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
            
            // Ctrl+N for new work order
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = 'create_work_order.php';
            }
            
            // Escape to clear selection
            if (e.key === 'Escape') {
                clearSelection();
            }
            
            // Delete key for bulk delete
            if (e.key === 'Delete' && selectedWorkOrders.size > 0) {
                e.preventDefault();
                bulkDelete();
            }
        });

        // --- 10. Auto-focus on search input ---
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput && !searchInput.value) {
            searchInput.focus();
        }

        // --- 11. Confirmation for delete actions ---
        document.querySelectorAll('.action-btn.delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this work order? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });

        // --- 12. Initialize on load ---
        document.addEventListener('DOMContentLoaded', function() {
            // Format numbers with commas
            const statValues = document.querySelectorAll('.stat-number');
            statValues.forEach(stat => {
                const value = stat.textContent.trim();
                if (!isNaN(value) && value !== '') {
                    stat.textContent = parseInt(value).toLocaleString();
                }
            });
            
            // Add tooltips to truncated descriptions
            document.querySelectorAll('.project-desc').forEach(el => {
                if (el.scrollWidth > el.clientWidth) {
                    el.title = el.textContent;
                }
            });
        });

        // --- 13. Save sidebar state ---
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth > 768) {
                localStorage.setItem('sidebarCollapsed', document.body.classList.contains('sidebar-collapsed'));
            }
        });

        // --- 14. PDF Fallback if file not found ---
        document.querySelector('form[action="work_order_pdf.php"]')?.addEventListener('submit', function(e) {
            // We'll let it submit normally, but we could add validation here
        });
    </script>
</body>
</html>