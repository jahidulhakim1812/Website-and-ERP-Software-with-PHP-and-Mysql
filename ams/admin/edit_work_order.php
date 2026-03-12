<?php
/**
 * edit_work_order.php
 * Edit Work Order Page
 * Allows editing of existing work orders with materials
 */

session_start();

// --- 1. AUTHENTICATION & SECURITY ---
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$timeout = 1800; // 30 minutes
if (isset($_SESSION['login_time'])) {
    $session_life = time() - $_SESSION['login_time'];
    if ($session_life > $timeout) {
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }
}
$_SESSION['login_time'] = time();

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    header_remove();
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

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: work_order.php");
    exit;
}
$work_order_id = intval($_GET['id']);

$work_order = null;
$materials = [];

try {
    $stmt = $pdo->prepare("
        SELECT wo.*, s.company_name, s.contact_person, s.email, s.phone, s.specialization
        FROM subcontractor_work_orders wo
        LEFT JOIN subcontractors s ON wo.subcontractor_id = s.id
        WHERE wo.id = ?
    ");
    $stmt->execute([$work_order_id]);
    $work_order = $stmt->fetch();
    
    if (!$work_order) {
        header("Location: work_order.php?error=Work order not found");
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM work_order_materials WHERE work_order_id = ? ORDER BY id");
    $stmt->execute([$work_order_id]);
    $materials = $stmt->fetchAll();
    
} catch (Exception $e) {
    die("Error fetching work order: " . $e->getMessage());
}

$userPhoto = '';
try {
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    $userPhoto = $userData['avatar'] ?? '';
} catch (Exception $e) {
    $userPhoto = '';
}

$message = '';
$msg_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_work_order') {
    try {
        $pdo->beginTransaction();
        
        $required = ['subcontractor_id', 'project_name', 'work_description'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(ucfirst(str_replace('_', ' ', $field)) . " is required.");
            }
        }
        
        $stmt = $pdo->prepare("SELECT id FROM subcontractors WHERE id = ? AND status = 'Active'");
        $stmt->execute([$_POST['subcontractor_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Selected subcontractor is not active or doesn't exist.");
        }
        
        // ---------- MATERIAL PROCESSING (ALLOWS ZERO QUANTITY) ----------
        $materials_total = 0;
        $materials_data = [];
        
        if (isset($_POST['materials']) && is_array($_POST['materials'])) {
            foreach ($_POST['materials'] as $key => $material) {
                // Skip if product name is empty (required field)
                if (empty($material['product_name'])) {
                    continue;
                }
                
                // Ensure quantity is numeric (allow zero)
                $quantity = isset($material['quantity']) && is_numeric($material['quantity'])
                            ? floatval($material['quantity'])
                            : 0;
                
                // Ensure unit price is numeric (allow zero)
                $unit_price = isset($material['unit_price']) && is_numeric($material['unit_price'])
                              ? floatval($material['unit_price'])
                              : 0;
                
                $total_price = $quantity * $unit_price;
                
                $materials_data[] = [
                    'product_name' => $material['product_name'],
                    'quantity'     => $quantity,
                    'unit_price'   => $unit_price,
                    'total_price'  => $total_price,
                    'notes'        => $material['notes'] ?? '',
                ];
                
                $materials_total += $total_price;
            }
        }
        // ---------- END OF MATERIAL PROCESSING ----------
        
        // Get actual cost from form (if provided, else keep existing)
        $actual_cost = isset($_POST['actual_cost']) && is_numeric($_POST['actual_cost']) 
                       ? floatval($_POST['actual_cost']) 
                       : $work_order['actual_cost'];
        
        // Update work order (including actual_cost)
        $sql = "UPDATE subcontractor_work_orders SET
            subcontractor_id = ?,
            project_name = ?,
            work_description = ?,
            location = ?,
            start_date = ?,
            end_date = ?,
            estimated_cost = ?,
            actual_cost = ?,
            work_status = ?,
            notes = ?,
            updated_at = NOW()
        WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['subcontractor_id'],
            $_POST['project_name'],
            $_POST['work_description'],
            $_POST['location'] ?? '',
            !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            $materials_total, // estimated_cost from materials
            $actual_cost,
            $_POST['work_status'] ?? 'Assigned',
            $_POST['notes'] ?? '',
            $work_order_id
        ]);
        
        // Delete all existing materials
        $stmt = $pdo->prepare("DELETE FROM work_order_materials WHERE work_order_id = ?");
        $stmt->execute([$work_order_id]);
        
        // Insert new materials
        foreach ($materials_data as $material) {
            $stmt = $pdo->prepare("INSERT INTO work_order_materials 
                (work_order_id, product_name, quantity, unit_price, total_price, notes) 
                VALUES (?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $work_order_id,
                $material['product_name'],
                $material['quantity'],
                $material['unit_price'],
                $material['total_price'],
                $material['notes']
            ]);
        }
        
        $pdo->commit();
        
        // Refresh data after update
        $stmt = $pdo->prepare("SELECT * FROM subcontractor_work_orders WHERE id = ?");
        $stmt->execute([$work_order_id]);
        $work_order = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT * FROM work_order_materials WHERE work_order_id = ? ORDER BY id");
        $stmt->execute([$work_order_id]);
        $materials = $stmt->fetchAll();
        
        $message = "Work order updated successfully! Estimated Cost: $" . number_format($materials_total, 2) . 
                   ", Actual Cost: $" . number_format($actual_cost, 2);
        $msg_type = 'success';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Fetch subcontractors for dropdown
$subcontractors = [];
try {
    $stmt = $pdo->query("SELECT id, company_name, contact_person, email, phone, specialization 
                         FROM subcontractors 
                         WHERE status = 'Active' 
                         ORDER BY company_name");
    $subcontractors = $stmt->fetchAll();
} catch (Exception $e) {}

$materials_total = 0;
foreach ($materials as $material) {
    $materials_total += $material['total_price'];
}

$status_options = ['Assigned', 'In Progress', 'Completed', 'On Hold', 'Cancelled', 'Draft'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Work Order | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

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

        /* --- Header Right Section --- */
        .header-right { 
            display: flex; 
            align-items: center; 
            gap: 24px; 
        }
        
        /* --- Theme Toggle Button --- */
        #themeToggle {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.2rem;
            padding: 8px;
            border-radius: 12px;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
        }
        
        #themeToggle:hover {
            background-color: var(--bg-body);
            color: var(--primary);
            transform: translateY(-1px);
        }
        
        /* --- Profile Dropdown --- */
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

        /* --- Scrollable Content --- */
        .scrollable { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }
        
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 32px; 
            flex-wrap: wrap; 
            gap: 16px; 
        }
        
        .page-title {
            font-size: 1.6rem; 
            font-weight: 700; 
            margin-bottom: 4px;
        }
        
        .page-subtitle {
            color: var(--text-muted); 
            font-size: 0.9rem; 
            line-height: 1.4;
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

        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.05) 0%, rgba(79, 70, 229, 0.02) 100%);
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-body {
            padding: 24px;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .required {
            color: var(--color-danger);
        }
        
        .form-input, .form-select, .form-textarea {
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.95rem;
            outline: none;
            transition: all 0.2s ease;
            width: 100%;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .readonly {
            background: var(--bg-body);
            color: var(--text-muted);
            cursor: not-allowed;
        }

        /* Material Items */
        .material-section {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }
        
        .material-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .material-item {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
            position: relative;
        }
        
        .material-index {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
            padding: 4px 12px;
            border-radius: 20px;
        }
        
        .remove-material {
            background: var(--color-danger);
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .remove-material:hover {
            background: #dc2626;
            transform: scale(1.1);
        }
        
        .add-material-btn {
            background: var(--primary);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.2s ease;
        }
        
        .add-material-btn:hover {
            background: var(--primary-hover);
            transform: scale(1.1);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        /* Cost Summary */
        .cost-summary {
            background: var(--bg-body);
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
            border: 1px solid var(--border);
        }
        
        .cost-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .cost-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--color-success);
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        
        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-body);
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: var(--color-danger);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.status-assigned {
            background: rgba(139, 92, 246, 0.12);
            color: var(--color-assigned);
        }
        
        .status-badge.status-in-progress {
            background: rgba(59, 130, 246, 0.12);
            color: var(--color-info);
        }
        
        .status-badge.status-completed {
            background: rgba(16, 185, 129, 0.12);
            color: var(--color-success);
        }
        
        .status-badge.status-on-hold {
            background: rgba(245, 158, 11, 0.12);
            color: var(--color-warning);
        }
        
        .status-badge.status-cancelled {
            background: rgba(107, 114, 128, 0.12);
            color: var(--text-muted);
        }
        
        .status-badge.status-draft {
            background: rgba(156, 163, 175, 0.12);
            color: var(--text-muted);
        }

        /* Info Grid */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            box-shadow: var(--shadow);
        }
        
        .info-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        
        .info-value {
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Toast */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--color-success);
            color: white;
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 9999;
            display: none;
            align-items: center;
            gap: 12px;
            max-width: 400px;
            animation: slideIn 0.3s ease;
        }
        
        .toast.error {
            background: var(--color-danger);
        }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 4px;
        }
        
        .toast-message {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
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

        /* Responsive */
        @media (max-width: 768px) {
            .top-header {
                padding: 0 20px;
            }
            
            .scrollable {
                padding: 20px;
            }
            
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
            
            .profile-info { 
                display: none; 
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-body {
                padding: 16px;
            }
        }

        /* Helper Classes */
        .mb-0 { margin-bottom: 0 !important; }
        .mt-0 { margin-top: 0 !important; }
        .mb-16 { margin-bottom: 16px !important; }
        .mt-16 { margin-top: 16px !important; }
        .mb-24 { margin-bottom: 24px !important; }
        .mt-24 { margin-top: 24px !important; }
        .text-success { color: var(--color-success) !important; }
        .text-danger { color: var(--color-danger) !important; }
        .text-warning { color: var(--color-warning) !important; }
        .text-muted { color: var(--text-muted) !important; }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- SIDEBAR (updated to match work_order.php) -->
    <?php include 'sidenavbar.php'; ?>
    <main class="main-content">
        
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Edit Work Order</span>
                </div>
            </div>

            <div class="header-right">
                <!-- Dark Mode Toggle Button -->
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <!-- User Profile -->
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
                        <a href="?action=logout" class="dropdown-item danger">
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
                    <h1 class="page-title">Edit Work Order</h1>
                    <p class="page-subtitle">
                        Update work order details, materials, and costs. 
                        Work Order #: <strong><?php echo e($work_order['work_order_number']); ?></strong>
                    </p>
                </div>
                <div>
                    <a href="work_order_management.php" class="btn btn-secondary">
                        <i class="ph ph-arrow-left"></i>
                        Back to List
                    </a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type; ?>">
                    <i class="ph <?php echo $msg_type == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Work Order Info Cards (updated to include Actual Cost) -->
            <div class="info-grid mb-24">
                <div class="info-card">
                    <div class="info-label">Current Status</div>
                    <div class="info-value">
                        <?php 
                        $statusClass = 'status-assigned';
                        if ($work_order['work_status'] === 'In Progress') {
                            $statusClass = 'status-in-progress';
                        } elseif ($work_order['work_status'] === 'Completed') {
                            $statusClass = 'status-completed';
                        } elseif ($work_order['work_status'] === 'On Hold') {
                            $statusClass = 'status-on-hold';
                        } elseif ($work_order['work_status'] === 'Cancelled') {
                            $statusClass = 'status-cancelled';
                        } elseif ($work_order['work_status'] === 'Draft') {
                            $statusClass = 'status-draft';
                        }
                        ?>
                        <span class="status-badge <?php echo $statusClass; ?>">
                            <i class="ph ph-circle-fill" style="font-size: 6px;"></i>
                            <?php echo e($work_order['work_status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Estimated Cost</div>
                    <div class="info-value text-success">
                        $<?php echo number_format($work_order['estimated_cost'], 2); ?>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Actual Cost</div>
                    <div class="info-value <?php echo $work_order['actual_cost'] > $work_order['estimated_cost'] ? 'text-danger' : 'text-success'; ?>">
                        $<?php echo number_format($work_order['actual_cost'] ?? 0, 2); ?>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Created On</div>
                    <div class="info-value">
                        <?php echo date('M d, Y', strtotime($work_order['created_at'])); ?>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <form method="POST" action="" id="editWorkOrderForm">
                <input type="hidden" name="action" value="update_work_order">
                
                <!-- Basic Information Card (added Actual Cost field) -->
                <div class="card mb-24">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ph ph-pencil-simple"></i>
                            Work Order Information
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Work Order Number</label>
                                <input type="text" class="form-input readonly" 
                                       value="<?php echo e($work_order['work_order_number']); ?>" readonly>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Subcontractor <span class="required">*</span></label>
                                <select name="subcontractor_id" class="form-select" required>
                                    <option value="">Select Subcontractor</option>
                                    <?php foreach ($subcontractors as $sub): ?>
                                        <option value="<?php echo $sub['id']; ?>" 
                                            <?php echo $work_order['subcontractor_id'] == $sub['id'] ? 'selected' : ''; ?>>
                                            <?php echo e($sub['company_name']); ?> - <?php echo e($sub['contact_person']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">Project Name <span class="required">*</span></label>
                                <input type="text" name="project_name" class="form-input" 
                                       value="<?php echo e($work_order['project_name']); ?>" required
                                       placeholder="e.g., Office Renovation, Roof Repair">
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">Work Description <span class="required">*</span></label>
                                <textarea name="work_description" class="form-textarea" rows="3" required
                                          placeholder="Detailed description of work to be performed..."><?php echo e($work_order['work_description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-input" 
                                       value="<?php echo e($work_order['location']); ?>"
                                       placeholder="Site address or building name">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Work Start Date</label>
                                <input type="date" name="start_date" class="form-input"
                                       value="<?php echo $work_order['start_date'] ? date('Y-m-d', strtotime($work_order['start_date'])) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Work End Date</label>
                                <input type="date" name="end_date" class="form-input"
                                       value="<?php echo $work_order['end_date'] ? date('Y-m-d', strtotime($work_order['end_date'])) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Work Status</label>
                                <select name="work_status" class="form-select">
                                    <?php foreach ($status_options as $status): ?>
                                        <option value="<?php echo $status; ?>" 
                                            <?php echo $work_order['work_status'] === $status ? 'selected' : ''; ?>>
                                            <?php echo $status; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- New field for Actual Cost -->
                            <div class="form-group">
                                <label class="form-label">Actual Cost ($)</label>
                                <input type="number" name="actual_cost" class="form-input" 
                                       value="<?php echo e($work_order['actual_cost'] ?? 0); ?>"
                                       step="0.01" min="0" placeholder="0.00">
                                <small style="color: var(--text-muted);">Manually update actual cost if different from estimated.</small>
                            </div>
                            
                            <div class="form-group full-width">
                                <label class="form-label">Special Instructions / Notes</label>
                                <textarea name="notes" class="form-textarea" rows="2" 
                                          placeholder="Any special requirements or additional information..."><?php echo e($work_order['notes']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Materials & Products Card (unchanged) -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ph ph-package"></i>
                            Materials & Products
                            <small style="font-size: 0.85rem; color: var(--text-muted); margin-left: 8px;">
                                (Work Order Cost: $<span id="currentTotalCost"><?php echo number_format($materials_total, 2); ?></span>)
                            </small>
                        </div>
                        <button type="button" class="add-material-btn" onclick="addMaterialRow()" title="Add New Material">
                            <i class="ph ph-plus"></i>
                        </button>
                    </div>
                    
                    <div class="card-body">
                        <div id="materialsContainer">
                            <?php foreach ($materials as $index => $material): ?>
                                <div class="material-item" id="material-<?php echo $material['id']; ?>">
                                    <div class="material-header">
                                        <span class="material-index">Product #<?php echo $index + 1; ?></span>
                                        <button type="button" class="remove-material" onclick="removeMaterial('<?php echo $material['id']; ?>')">
                                            <i class="ph ph-trash"></i>
                                        </button>
                                    </div>
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label class="form-label">Product Name <span class="required">*</span></label>
                                            <input type="text" 
                                                   name="materials[<?php echo $material['id']; ?>][product_name]" 
                                                   class="form-input material-product-name"
                                                   value="<?php echo e($material['product_name']); ?>"
                                                   placeholder="Enter product name"
                                                   oninput="calculateMaterialTotal('<?php echo $material['id']; ?>')"
                                                   required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Quantity <span class="required">*</span></label>
                                            <input type="number" 
                                                   name="materials[<?php echo $material['id']; ?>][quantity]" 
                                                   class="form-input material-quantity"
                                                   value="<?php echo $material['quantity']; ?>"
                                                   step="0.01" 
                                                   min="0"
                                                   placeholder="0"
                                                   oninput="calculateMaterialTotal('<?php echo $material['id']; ?>')"
                                                   data-material-id="<?php echo $material['id']; ?>"
                                                   required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Unit Price ($) <span class="required">*</span></label>
                                            <input type="number" 
                                                   name="materials[<?php echo $material['id']; ?>][unit_price]" 
                                                   class="form-input material-unit-price"
                                                   value="<?php echo $material['unit_price']; ?>"
                                                   step="0.01" 
                                                   min="0"
                                                   placeholder="0.00"
                                                   oninput="calculateMaterialTotal('<?php echo $material['id']; ?>')"
                                                   data-material-id="<?php echo $material['id']; ?>"
                                                   required>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label class="form-label">Total Price ($)</label>
                                            <input type="number" 
                                                   class="form-input material-total-price"
                                                   value="<?php echo $material['total_price']; ?>"
                                                   data-material-id="<?php echo $material['id']; ?>"
                                                   readonly>
                                        </div>
                                        
                                        <div class="form-group full-width">
                                            <label class="form-label">Product Notes</label>
                                            <textarea name="materials[<?php echo $material['id']; ?>][notes]" 
                                                      class="form-textarea" rows="1"
                                                      placeholder="Optional product notes..."><?php echo e($material['notes']); ?></textarea>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Cost Summary -->
                        <div class="cost-summary">
                            <h3 style="margin-bottom: 16px; font-size: 1.1rem;">Cost Summary</h3>
                            <div class="cost-row">
                                <span>Current Materials Cost:</span>
                                <span>$<span id="currentMaterialsCost"><?php echo number_format($materials_total, 2); ?></span></span>
                            </div>
                            <div class="cost-row cost-total">
                                <span>Updated Work Order Cost:</span>
                                <span id="totalMaterialsCost">$<?php echo number_format($materials_total, 2); ?></span>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 12px;">
                                <i class="ph ph-info"></i> The work order cost is calculated from the total materials cost.
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="ph ph-check"></i>
                                Update Work Order
                            </button>
                            <a href="work_order_management.php" class="btn btn-secondary">
                                <i class="ph ph-x"></i>
                                Cancel
                            </a>
                            <button type="button" class="btn btn-danger" onclick="confirmDeleteWorkOrder()">
                                <i class="ph ph-trash"></i>
                                Delete Work Order
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </main>

    <!-- Toast Notification -->
    <div class="toast" id="toast">
        <i class="ph ph-check-circle" style="font-size: 1.2rem;"></i>
        <div class="toast-content">
            <div class="toast-title" id="toastTitle">Success</div>
            <div class="toast-message" id="toastMessage">Operation completed successfully.</div>
        </div>
        <button class="toast-close" onclick="hideToast()">
            <i class="ph ph-x"></i>
        </button>
    </div>

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

        // --- 4. Dark Mode Toggle ---
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        function getPreferredTheme() {
            const storedTheme = localStorage.getItem('theme');
            if (storedTheme) {
                return storedTheme;
            }
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }
        
        function applyTheme(theme) {
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                themeIcon.classList.remove('ph-moon');
                themeIcon.classList.add('ph-sun');
            } else {
                document.documentElement.removeAttribute('data-theme');
                themeIcon.classList.remove('ph-sun');
                themeIcon.classList.add('ph-moon');
            }
            localStorage.setItem('theme', theme);
        }
        
        applyTheme(getPreferredTheme());
        
        themeBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            applyTheme(newTheme);
            showToast('Theme Changed', `Switched to ${newTheme} mode`, 'success');
        });
        
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });

        // --- 5. Material Management ---
        let materialCounter = <?php echo count($materials); ?>;
        let newMaterialCounter = 0;
        
        function addMaterialRow() {
            newMaterialCounter++;
            const container = document.getElementById('materialsContainer');
            
            const materialDiv = document.createElement('div');
            materialDiv.className = 'material-item';
            materialDiv.id = 'material-new-' + newMaterialCounter;
            
            materialDiv.innerHTML = `
                <div class="material-header">
                    <span class="material-index">New Product</span>
                    <button type="button" class="remove-material" onclick="removeMaterial('new-${newMaterialCounter}')">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Product Name <span class="required">*</span></label>
                        <input type="text" 
                               name="materials[new-${newMaterialCounter}][product_name]" 
                               class="form-input material-product-name"
                               placeholder="Enter product name"
                               oninput="calculateMaterialTotal('new-${newMaterialCounter}')"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Quantity <span class="required">*</span></label>
                        <input type="number" 
                               name="materials[new-${newMaterialCounter}][quantity]" 
                               class="form-input material-quantity"
                               step="0.01" 
                               min="0"
                               placeholder="0"
                               oninput="calculateMaterialTotal('new-${newMaterialCounter}')"
                               data-material-id="new-${newMaterialCounter}"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Unit Price ($) <span class="required">*</span></label>
                        <input type="number" 
                               name="materials[new-${newMaterialCounter}][unit_price]" 
                               class="form-input material-unit-price"
                               step="0.01" 
                               min="0"
                               placeholder="0.00"
                               oninput="calculateMaterialTotal('new-${newMaterialCounter}')"
                               data-material-id="new-${newMaterialCounter}"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Total Price ($)</label>
                        <input type="number" 
                               class="form-input material-total-price"
                               data-material-id="new-${newMaterialCounter}"
                               readonly>
                    </div>
                    
                    <div class="form-group full-width">
                        <label class="form-label">Product Notes</label>
                        <textarea name="materials[new-${newMaterialCounter}][notes]" 
                                  class="form-textarea" rows="1" 
                                  placeholder="Optional product notes..."></textarea>
                    </div>
                </div>
            `;
            
            container.appendChild(materialDiv);
            updateTotalCost();
            showToast('Material Added', 'New material row added successfully', 'success');
        }
        
        function removeMaterial(materialId) {
            const materialDiv = document.getElementById('material-' + materialId);
            if (materialDiv) {
                if (confirm('Are you sure you want to remove this material item?')) {
                    materialDiv.remove();
                    updateTotalCost();
                    showToast('Material Removed', 'Material item removed successfully', 'success');
                }
            }
        }
        
        function calculateMaterialTotal(materialId) {
            const quantityInput = document.querySelector(`.material-quantity[data-material-id="${materialId}"]`);
            const unitPriceInput = document.querySelector(`.material-unit-price[data-material-id="${materialId}"]`);
            const totalInput = document.querySelector(`.material-total-price[data-material-id="${materialId}"]`);
            
            if (!quantityInput || !unitPriceInput || !totalInput) return;
            
            const quantity = parseFloat(quantityInput.value) || 0;
            const unitPrice = parseFloat(unitPriceInput.value) || 0;
            const total = quantity * unitPrice;
            
            totalInput.value = total.toFixed(2);
            updateTotalCost();
        }
        
        function updateTotalCost() {
            let materialsCost = 0;
            
            const totalPriceInputs = document.querySelectorAll('.material-total-price');
            totalPriceInputs.forEach(input => {
                const value = parseFloat(input.value) || 0;
                materialsCost += value;
            });
            
            document.getElementById('currentTotalCost').textContent = materialsCost.toFixed(2);
            document.getElementById('currentMaterialsCost').textContent = materialsCost.toFixed(2);
            document.getElementById('totalMaterialsCost').textContent = '$' + materialsCost.toFixed(2);
        }
        
        // --- 6. Toast Notifications ---
        function showToast(title, message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastTitle = document.getElementById('toastTitle');
            const toastMessage = document.getElementById('toastMessage');
            const toastIcon = toast.querySelector('i');
            
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            toast.className = 'toast';
            toast.classList.add(type);
            
            if (type === 'success') {
                toastIcon.className = 'ph ph-check-circle';
            } else if (type === 'error') {
                toastIcon.className = 'ph ph-x-circle';
            } else if (type === 'warning') {
                toastIcon.className = 'ph ph-warning-circle';
            } else if (type === 'info') {
                toastIcon.className = 'ph ph-info';
            }
            
            toast.style.display = 'flex';
            setTimeout(hideToast, 5000);
        }
        
        function hideToast() {
            const toast = document.getElementById('toast');
            toast.style.display = 'none';
        }
        
        // --- 7. Form Validation ---
        document.getElementById('editWorkOrderForm').addEventListener('submit', function(e) {
            let hasErrors = false;
            
            const requiredFields = this.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    hasErrors = true;
                    field.style.borderColor = 'var(--color-danger)';
                    field.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
                } else {
                    field.style.borderColor = '';
                    field.style.boxShadow = '';
                }
            });
            
            const startDate = document.querySelector('input[name="start_date"]');
            const endDate = document.querySelector('input[name="end_date"]');
            
            if (startDate.value && endDate.value) {
                const start = new Date(startDate.value);
                const end = new Date(endDate.value);
                
                if (start > end) {
                    hasErrors = true;
                    startDate.style.borderColor = 'var(--color-danger)';
                    endDate.style.borderColor = 'var(--color-danger)';
                    showToast('Validation Error', 'End date must be after start date.', 'error');
                }
            }
            
            const materialItems = document.querySelectorAll('.material-item');
            if (materialItems.length === 0) {
                hasErrors = true;
                showToast('Validation Error', 'Please add at least one material item.', 'error');
            }
            
            if (hasErrors) {
                e.preventDefault();
                if (!document.querySelector('.toast:not([style*="display: none"])')) {
                    showToast('Validation Error', 'Please fill in all required fields correctly.', 'error');
                }
            } else {
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="ph ph-circle-notch spin"></i> Updating...';
                submitBtn.disabled = true;
                
                setTimeout(() => {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }, 3000);
            }
        });
        
        // --- 8. Delete Work Order Confirmation ---
        function confirmDeleteWorkOrder() {
            if (confirm('⚠️ Are you sure you want to delete this work order?\n\nThis action cannot be undone and will delete all associated materials and payments.')) {
                showToast('Deleting', 'Work order deletion in progress...', 'warning');
                setTimeout(() => {
                    window.location.href = 'delete_work_order.php?id=<?php echo $work_order_id; ?>';
                }, 1500);
            }
        }
        
        // --- 9. Initialize on load ---
        document.addEventListener('DOMContentLoaded', function() {
            const style = document.createElement('style');
            style.textContent = `
                @keyframes spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }
                .spin {
                    animation: spin 1s linear infinite;
                }
            `;
            document.head.appendChild(style);
            
            if (typeof flatpickr !== 'undefined') {
                flatpickr('.form-input[type="date"]', {
                    dateFormat: "Y-m-d",
                    allowInput: true,
                    theme: document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light'
                });
            }
            
            <?php foreach ($materials as $material): ?>
                calculateMaterialTotal('<?php echo $material['id']; ?>');
            <?php endforeach; ?>
            
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('material-quantity') || e.target.classList.contains('material-unit-price')) {
                    const materialId = e.target.dataset.materialId;
                    if (materialId) {
                        calculateMaterialTotal(materialId);
                    }
                }
            });
            
            updateTotalCost();
            
            let saveTimeout;
            const form = document.getElementById('editWorkOrderForm');
            const formInputs = form.querySelectorAll('input, textarea, select');
            
            formInputs.forEach(input => {
                input.addEventListener('input', () => {
                    clearTimeout(saveTimeout);
                    saveTimeout = setTimeout(() => {
                        console.log('Auto-save triggered');
                    }, 3000);
                });
            });
            
            document.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    document.querySelector('button[type="submit"]').click();
                }
                
                if (e.key === 'Escape') {
                    if (confirm('Cancel editing? Unsaved changes will be lost.')) {
                        window.location.href = 'work_order_management.php';
                    }
                }
            });
            
            setTimeout(() => {
                showToast('Ready to Edit', 'You can now edit the work order details and materials.', 'info');
            }, 1000);
        });
    </script>
</body>
</html>