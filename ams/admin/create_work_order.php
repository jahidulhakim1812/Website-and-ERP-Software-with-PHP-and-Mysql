<?php
/**
 * create_work_order.php
 * Create Work Order Page
 * Dedicated page for creating new work orders with materials
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
    die("Database connection failed: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 4. HANDLE FORM SUBMISSION ---
$message = '';
$msg_type = '';
$created_work_order_number = '';
$created_work_order_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_work_order') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Validate required fields
        $required = ['subcontractor_id', 'project_name', 'work_description'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(ucfirst(str_replace('_', ' ', $field)) . " is required.");
            }
        }
        
        // Validate subcontractor exists and is active
        $stmt = $pdo->prepare("SELECT id FROM subcontractors WHERE id = ? AND status = 'Active'");
        $stmt->execute([$_POST['subcontractor_id']]);
        if (!$stmt->fetch()) {
            throw new Exception("Selected subcontractor is not active or doesn't exist.");
        }
        
        // Generate work order number if not provided
        $work_order_number = $_POST['work_order_number'] ?? '';
        if (empty($work_order_number)) {
            $prefix = 'WO-' . date('Ymd') . '-';
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE work_order_number LIKE ?");
            $stmt->execute([$prefix . '%']);
            $count = $stmt->fetch()['count'] + 1;
            $work_order_number = $prefix . str_pad($count, 3, '0', STR_PAD_LEFT);
        }
        
        // Calculate materials cost first (this will be the total work order cost)
        $materials_total = 0;
        $materials_data = [];
        
        if (isset($_POST['materials']) && is_array($_POST['materials'])) {
            foreach ($_POST['materials'] as $index => $material) {
                if (!empty($material['product_name']) && !empty($material['quantity'])) {
                    $product_name = trim($material['product_name']);
                    $quantity = floatval($material['quantity']);
                    $unit_price = floatval($material['unit_price'] ?? 0);
                    $total_price = $quantity * $unit_price;
                    
                    $materials_data[] = [
                        'product_name' => $product_name,
                        'quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'total_price' => $total_price,
                        'notes' => $material['notes'] ?? ''
                    ];
                    
                    $materials_total += $total_price;
                }
            }
        }
        
        // Validate at least one material is added
        if (empty($materials_data)) {
            throw new Exception("At least one material is required to create a work order.");
        }
        
        // Create work order with materials total as estimated cost
        $sql = "INSERT INTO subcontractor_work_orders (
            subcontractor_id, work_order_number, project_name, work_description, location,
            start_date, end_date, estimated_cost, work_status, notes
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['subcontractor_id'],
            $work_order_number,
            $_POST['project_name'],
            $_POST['work_description'],
            $_POST['location'] ?? '',
            !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            $materials_total, // Work order cost = materials total
            $_POST['work_status'] ?? 'Assigned',
            $_POST['notes'] ?? ''
        ]);
        
        $work_order_id = $pdo->lastInsertId();
        $created_work_order_id = $work_order_id;
        $created_work_order_number = $work_order_number;
        
        // Add materials
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
        
        // Success message with options
        $message = "Work order created successfully! Work Order #: " . $work_order_number;
        if ($materials_total > 0) {
            $message .= ". Total Cost: $" . number_format($materials_total, 2);
        }
        $msg_type = 'success';
        
        // If user wants to stay and create another
        if (isset($_POST['create_another']) && $_POST['create_another'] == '1') {
            // Reset form but keep success message
            $_POST = [];
        } else {
            // Redirect to view page after 3 seconds
            header("refresh:3;url=edit_work_order.php?id=" . $work_order_id);
        }
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// --- 5. GET USER PHOTO ---
$userPhoto = '';
try {
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    $userPhoto = $userData['avatar'] ?? '';
} catch (Exception $e) {
    $userPhoto = '';
}

// --- 6. FETCH DATA FOR DISPLAY ---
// Get active subcontractors
$subcontractors = [];
try {
    $stmt = $pdo->query("SELECT id, company_name, contact_person, email, phone, specialization 
                         FROM subcontractors 
                         WHERE status = 'Active' 
                         ORDER BY company_name");
    $subcontractors = $stmt->fetchAll();
} catch (Exception $e) {
    // Table might not exist yet
}

// Get today's date for default values
$today = date('Y-m-d');
$next_week = date('Y-m-d', strtotime('+1 week'));

// Check if we need to clear form after successful creation
$clear_form = false;
if ($msg_type === 'success' && !isset($_POST['create_another'])) {
    $clear_form = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Work Order | NexusAdmin</title>
    
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

        /* Success Banner */
        .success-banner {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            border: 1px solid rgba(16, 185, 129, 0.2);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 32px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-content {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .success-icon {
            width: 48px;
            height: 48px;
            background: var(--color-success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }
        
        .success-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
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

        /* Card */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .card-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.05) 0%, rgba(79, 70, 229, 0.02) 100%);
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-title i {
            color: var(--primary);
        }
        
        .card-body {
            padding: 32px;
        }
        
        /* Form */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        label {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
        }
        
        label span.required {
            color: var(--color-danger);
        }
        
        .form-hint {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        input, select, textarea {
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-body);
            color: var(--text-main);
            outline: none;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        
        input:focus, select:focus, textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            flex-wrap: wrap;
        }
        
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
        
        .btn-success {
            background: var(--color-success);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-success:hover {
            background: #0da271;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        /* Material Items */
        .material-item {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 16px;
            position: relative;
            animation: slideIn 0.3s ease;
        }
        
        .material-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .material-index {
            font-weight: 600;
            color: var(--primary);
        }
        
        .remove-material {
            background: var(--color-danger);
            color: white;
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 6px;
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
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.2s ease;
            margin-left: auto;
        }
        
        .add-material-btn:hover {
            background: var(--primary-hover);
            transform: scale(1.05);
        }
        
        .materials-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        /* Cost Summary */
        .cost-summary {
            background: var(--bg-body);
            border-radius: 8px;
            padding: 20px;
            margin-top: 24px;
            border: 1px solid var(--border);
        }
        
        .cost-item {
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

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
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
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
        }
        
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
            
            .card-body {
                padding: 24px;
            }
            
            .success-banner {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .success-actions {
                width: 100%;
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
            
            .card-header {
                padding: 20px;
                flex-direction: column;
                gap: 16px;
            }
            
            .materials-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .add-material-btn {
                align-self: flex-end;
            }
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
            <span style="font-size: 0.9rem; color: var(--text-muted);">Live Dashboard</span>
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
                <div>
                    <h1 class="page-title">Create Work Order</h1>
                    <p class="page-subtitle">
                        Add new work order for subcontractor with product allocation
                    </p>
                </div>
                <div>
                    <a href="work_order.php" class="btn btn-secondary">
                        <i class="ph ph-arrow-left"></i>
                        Back to List
                    </a>
                </div>
            </div>

            <!-- Success Banner -->
            <?php if($msg_type === 'success' && !empty($created_work_order_number)): ?>
                <div class="success-banner">
                    <div class="success-content">
                        <div class="success-icon">
                            <i class="ph ph-check"></i>
                        </div>
                        <div>
                            <h3 style="font-weight: 600; margin-bottom: 4px;">Work Order Created Successfully!</h3>
                            <p style="color: var(--text-muted);">
                                Work Order #: <strong><?php echo e($created_work_order_number); ?></strong> | 
                                Total Cost: <strong>$<?php echo number_format($_POST['estimated_cost'] ?? 0, 2); ?></strong>
                            </p>
                            <p style="font-size: 0.9rem; margin-top: 8px;">
                                <?php if(!isset($_POST['create_another'])): ?>
                                    Redirecting to edit page in 3 seconds...
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <div class="success-actions">
                        <a href="edit_work_order.php?id=<?php echo $created_work_order_id; ?>" class="btn btn-primary">
                            <i class="ph ph-eye"></i> View Work Order
                        </a>
                        <a href="work_order.php" class="btn btn-secondary">
                            <i class="ph ph-list"></i> Back to List
                        </a>
                        <button type="button" class="btn btn-success" onclick="resetForm()">
                            <i class="ph ph-plus"></i> Create Another
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php if(!empty($message) && $msg_type !== 'success'): ?>
                <div class="alert alert-<?php echo $msg_type; ?>">
                    <i class="ph <?php echo $msg_type == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Create Form -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">
                        <i class="ph ph-plus"></i>
                        New Work Order Details
                    </h2>
                    <div style="font-size: 0.9rem; color: var(--text-muted);">
                        <i class="ph ph-info"></i> All fields marked with <span class="required">*</span> are required
                    </div>
                </div>
                
                <form method="POST" action="" id="createWorkOrderForm">
                    <input type="hidden" name="action" value="create_work_order">
                    <input type="hidden" name="create_another" id="createAnother" value="0">
                    
                    <div class="card-body">
                        <div class="form-grid">
                            <!-- Basic Information -->
                            <div class="form-group">
                                <label>Work Order Number</label>
                                <input type="text" name="work_order_number" 
                                       value="<?php echo $clear_form ? '' : e($_POST['work_order_number'] ?? 'WO-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT)); ?>"
                                       placeholder="Auto-generated" id="workOrderNumber">
                                <div class="form-hint">Leave empty for auto-generation</div>
                            </div>
                            
                            <div class="form-group">
                                <label>Subcontractor <span class="required">*</span></label>
                                <select name="subcontractor_id" required id="subcontractorSelect">
                                    <option value="">Select Subcontractor</option>
                                    <?php if (empty($subcontractors)): ?>
                                        <option value="" disabled>No active subcontractors found</option>
                                    <?php else: ?>
                                        <?php foreach ($subcontractors as $sub): ?>
                                            <option value="<?php echo $sub['id']; ?>"
                                                <?php echo (!$clear_form && isset($_POST['subcontractor_id']) && $_POST['subcontractor_id'] == $sub['id']) ? 'selected' : ''; ?>>
                                                <?php echo e($sub['company_name']); ?> - <?php echo e($sub['contact_person']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="form-hint">Only active subcontractors are shown</div>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Project Name <span class="required">*</span></label>
                                <input type="text" name="project_name" 
                                       value="<?php echo $clear_form ? '' : e($_POST['project_name'] ?? ''); ?>"
                                       placeholder="e.g., Office Renovation, Roof Repair"
                                       required id="projectName">
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Work Description <span class="required">*</span></label>
                                <textarea name="work_description" rows="3" required
                                          placeholder="Detailed description of work to be performed..."
                                          id="workDescription"><?php echo $clear_form ? '' : e($_POST['work_description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label>Location</label>
                                <input type="text" name="location"
                                       value="<?php echo $clear_form ? '' : e($_POST['location'] ?? ''); ?>"
                                       placeholder="Site address or building name" id="location">
                            </div>
                            
                            <div class="form-group">
                                <label>Work Start Date</label>
                                <input type="date" name="start_date" 
                                       value="<?php echo $clear_form ? $today : e($_POST['start_date'] ?? $today); ?>"
                                       class="work-date" id="startDate">
                            </div>
                            
                            <div class="form-group">
                                <label>Work End Date</label>
                                <input type="date" name="end_date" 
                                       value="<?php echo $clear_form ? $next_week : e($_POST['end_date'] ?? $next_week); ?>"
                                       class="work-date" id="endDate">
                            </div>
                            
                            <div class="form-group">
                                <label>Work Status</label>
                                <select name="work_status" id="workStatus">
                                    <option value="Assigned" <?php echo (!$clear_form && isset($_POST['work_status']) && $_POST['work_status'] === 'Assigned') ? 'selected' : 'selected'; ?>>Assigned</option>
                                    <option value="In Progress" <?php echo (!$clear_form && isset($_POST['work_status']) && $_POST['work_status'] === 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="On Hold" <?php echo (!$clear_form && isset($_POST['work_status']) && $_POST['work_status'] === 'On Hold') ? 'selected' : ''; ?>>On Hold</option>
                                </select>
                            </div>
                            
                            <div class="form-group full-width">
                                <label>Special Instructions / Notes</label>
                                <textarea name="notes" rows="2" 
                                          placeholder="Any special requirements or additional information..."
                                          id="notes"><?php echo $clear_form ? '' : e($_POST['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Materials Section -->
                        <div style="margin-top: 32px; border-top: 1px solid var(--border); padding-top: 24px;">
                            <div class="materials-header">
                                <h3 style="font-size: 1.1rem; font-weight: 600; color: var(--text-main);">
                                    <i class="ph ph-package"></i> Materials & Products (Work Order Cost) <span class="required">*</span>
                                </h3>
                                <button type="button" class="add-material-btn" onclick="addMaterialRow()">
                                    <i class="ph ph-plus"></i>
                                </button>
                            </div>
                            
                            <div class="form-hint" style="margin-bottom: 20px;">
                                <i class="ph ph-info"></i> Work order cost is calculated from the total materials cost. Add at least one material.
                            </div>
                            
                            <div id="materialsContainer">
                                <!-- Material rows will be added here -->
                                <?php if(!$clear_form && isset($_POST['materials']) && is_array($_POST['materials'])): ?>
                                    <?php foreach($_POST['materials'] as $index => $material): ?>
                                        <?php if(!empty($material['product_name'])): ?>
                                            <div class="material-item" id="material-<?php echo $index; ?>">
                                                <div class="material-header">
                                                    <span class="material-index">Product #<?php echo $index + 1; ?></span>
                                                    <button type="button" class="remove-material" onclick="removeMaterialRow(<?php echo $index; ?>)">
                                                        <i class="ph ph-x"></i>
                                                    </button>
                                                </div>
                                                <div class="form-grid">
                                                    <div class="form-group">
                                                        <label>Product Name <span class="required">*</span></label>
                                                        <input type="text" 
                                                               name="materials[<?php echo $index; ?>][product_name]" 
                                                               value="<?php echo e($material['product_name']); ?>"
                                                               placeholder="Enter product name"
                                                               class="material-product-name"
                                                               oninput="calculateMaterialTotal(<?php echo $index; ?>)"
                                                               required>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Quantity <span class="required">*</span></label>
                                                        <input type="number" 
                                                               name="materials[<?php echo $index; ?>][quantity]" 
                                                               value="<?php echo e($material['quantity']); ?>"
                                                               step="0.01" 
                                                               min="0"
                                                               placeholder="0"
                                                               class="material-quantity"
                                                               oninput="calculateMaterialTotal(<?php echo $index; ?>)"
                                                               data-material-id="<?php echo $index; ?>"
                                                               required>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Unit Price ($) <span class="required">*</span></label>
                                                        <input type="number" 
                                                               name="materials[<?php echo $index; ?>][unit_price]" 
                                                               value="<?php echo e($material['unit_price'] ?? '0'); ?>"
                                                               step="0.01" 
                                                               min="0"
                                                               placeholder="0.00"
                                                               class="material-unit-price"
                                                               oninput="calculateMaterialTotal(<?php echo $index; ?>)"
                                                               data-material-id="<?php echo $index; ?>"
                                                               required>
                                                    </div>
                                                    
                                                    <div class="form-group">
                                                        <label>Total Price ($)</label>
                                                        <input type="number" 
                                                               class="material-total-price" 
                                                               value="<?php echo e($material['quantity'] * ($material['unit_price'] ?? 0)); ?>"
                                                               readonly
                                                               data-material-id="<?php echo $index; ?>">
                                                    </div>
                                                    
                                                    <div class="form-group full-width">
                                                        <label>Product Notes</label>
                                                        <textarea name="materials[<?php echo $index; ?>][notes]" rows="1" 
                                                                  placeholder="Optional product notes..."><?php echo e($material['notes'] ?? ''); ?></textarea>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Cost Summary -->
                            <div class="cost-summary">
                                <h3 style="margin-bottom: 16px; font-size: 1.1rem;">Cost Summary</h3>
                                <div class="cost-item cost-total">
                                    <span>Total Work Order Cost:</span>
                                    <span id="totalCost">$0.00</span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 8px;">
                                    <i class="ph ph-info"></i> The work order cost is calculated from the total materials cost.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="ph ph-check"></i>
                                Create Work Order
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="saveAsDraft()">
                                <i class="ph ph-floppy-disk"></i>
                                Save as Draft
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                <i class="ph ph-arrow-counter-clockwise"></i>
                                Reset Form
                            </button>
                            <a href="work_order.php" class="btn btn-secondary">
                                <i class="ph ph-x"></i>
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

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
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            const link = item.querySelector('.menu-link');
            if (link && !link.querySelector('a')) {
                link.addEventListener('click', (e) => {
                    if (!link.hasAttribute('href')) {
                        if(body.classList.contains('sidebar-collapsed')) {
                            body.classList.remove('sidebar-collapsed');
                            setTimeout(() => { item.classList.add('open'); }, 100);
                            return;
                        }
                        
                        e.preventDefault();
                        const isOpen = item.classList.contains('open');
                        menuItems.forEach(i => i.classList.remove('open')); 
                        if (!isOpen) item.classList.add('open');
                    }
                });
            }
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

        // --- 5. Material Management ---
        let materialCounter = <?php echo !$clear_form && isset($_POST['materials']) ? count($_POST['materials']) : 0; ?>;
        
        // Initialize materials if empty
        document.addEventListener('DOMContentLoaded', function() {
            if (materialCounter === 0) {
                addMaterialRow();
            } else {
                // Calculate totals for existing materials
                for (let i = 0; i < materialCounter; i++) {
                    calculateMaterialTotal(i);
                }
                updateTotalCost();
            }
        });
        
        function addMaterialRow() {
            materialCounter++;
            const container = document.getElementById('materialsContainer');
            
            const materialDiv = document.createElement('div');
            materialDiv.className = 'material-item';
            materialDiv.id = 'material-' + materialCounter;
            
            materialDiv.innerHTML = `
                <div class="material-header">
                    <span class="material-index">Product #${materialCounter}</span>
                    <button type="button" class="remove-material" onclick="removeMaterialRow(${materialCounter})">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Product Name <span class="required">*</span></label>
                        <input type="text" 
                               name="materials[${materialCounter}][product_name]" 
                               placeholder="Enter product name"
                               class="material-product-name"
                               oninput="calculateMaterialTotal(${materialCounter})"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label>Quantity <span class="required">*</span></label>
                        <input type="number" 
                               name="materials[${materialCounter}][quantity]" 
                               step="0.01" 
                               min="0"
                               placeholder="0"
                               class="material-quantity"
                               oninput="calculateMaterialTotal(${materialCounter})"
                               data-material-id="${materialCounter}"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label>Unit Price ($) <span class="required">*</span></label>
                        <input type="number" 
                               name="materials[${materialCounter}][unit_price]" 
                               step="0.01" 
                               min="0"
                               placeholder="0.00"
                               class="material-unit-price"
                               oninput="calculateMaterialTotal(${materialCounter})"
                               data-material-id="${materialCounter}"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label>Total Price ($)</label>
                        <input type="number" 
                               class="material-total-price" 
                               readonly
                               data-material-id="${materialCounter}">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Product Notes</label>
                        <textarea name="materials[${materialCounter}][notes]" rows="1" placeholder="Optional product notes..."></textarea>
                    </div>
                </div>
            `;
            
            container.appendChild(materialDiv);
            updateTotalCost();
            
            // Add animation
            materialDiv.style.opacity = '0';
            materialDiv.style.transform = 'translateY(20px)';
            setTimeout(() => {
                materialDiv.style.transition = 'all 0.3s ease';
                materialDiv.style.opacity = '1';
                materialDiv.style.transform = 'translateY(0)';
            }, 10);
        }
        
        function removeMaterialRow(id) {
            const materialDiv = document.getElementById('material-' + id);
            if (materialDiv) {
                // Add removal animation
                materialDiv.style.transform = 'translateX(-100%)';
                materialDiv.style.opacity = '0';
                
                setTimeout(() => {
                    materialDiv.remove();
                    updateTotalCost();
                }, 300);
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
            
            // Calculate from all material total inputs
            document.querySelectorAll('.material-total-price').forEach(input => {
                const cost = parseFloat(input.value) || 0;
                materialsCost += cost;
            });
            
            // Update display
            document.getElementById('totalCost').textContent = '$' + materialsCost.toFixed(2);
            
            // Update the page title with the calculated cost
            const costTitle = document.querySelector('.cost-summary h3');
            if (costTitle) {
                costTitle.innerHTML = `Cost Summary <small style="font-size: 0.85rem; color: var(--text-muted);">(Work Order Cost: $${materialsCost.toFixed(2)})</small>`;
            }
        }
        
        // --- 6. Form Functions ---
        function resetForm() {
            if (confirm('Are you sure you want to reset the form? All entered data will be lost.')) {
                // Clear all form fields
                document.getElementById('createWorkOrderForm').reset();
                
                // Clear materials container
                document.getElementById('materialsContainer').innerHTML = '';
                materialCounter = 0;
                
                // Reset dates to default
                const today = new Date().toISOString().split('T')[0];
                const nextWeek = new Date();
                nextWeek.setDate(nextWeek.getDate() + 7);
                const nextWeekStr = nextWeek.toISOString().split('T')[0];
                
                document.getElementById('startDate').value = today;
                document.getElementById('endDate').value = nextWeekStr;
                document.getElementById('workStatus').value = 'Assigned';
                
                // Regenerate work order number
                const now = new Date();
                const dateStr = now.getFullYear() + 
                               String(now.getMonth() + 1).padStart(2, '0') + 
                               String(now.getDate()).padStart(2, '0');
                const randomNum = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0');
                document.getElementById('workOrderNumber').value = `WO-${dateStr}-${randomNum}`;
                
                // Add first material row
                addMaterialRow();
                
                // Hide success banner if visible
                const successBanner = document.querySelector('.success-banner');
                if (successBanner) {
                    successBanner.style.display = 'none';
                }
                
                // Reset create another flag
                document.getElementById('createAnother').value = '0';
                
                showToast('Form Reset', 'Form has been reset to default values.', 'success');
            }
        }
        
        function saveAsDraft() {
            // Set status to draft
            document.getElementById('workStatus').value = 'Assigned';
            
            // Show confirmation
            if (confirm('Save this work order as draft? You can complete it later.')) {
                document.getElementById('submitBtn').click();
            }
        }
        
        // --- 7. Form Validation ---
        document.getElementById('createWorkOrderForm').addEventListener('submit', function(e) {
            let hasErrors = false;
            
            // Show loading
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            // Validate required fields
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
            
            // Validate dates
            const startDate = document.getElementById('startDate');
            const endDate = document.getElementById('endDate');
            
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
            
            // Check if at least one material is added with product name
            const materialInputs = document.querySelectorAll('.material-product-name');
            let hasMaterials = false;
            materialInputs.forEach(input => {
                if (input.value.trim()) {
                    hasMaterials = true;
                }
            });
            
            if (!hasMaterials) {
                hasErrors = true;
                showToast('Validation Error', 'At least one material is required.', 'error');
            }
            
            if (hasErrors) {
                e.preventDefault();
                document.getElementById('loadingOverlay').style.display = 'none';
                // Scroll to first error
                const firstError = this.querySelector('[required]:invalid');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
        
        // --- 8. Toast Notifications ---
        function showToast(title, message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastTitle = document.getElementById('toastTitle');
            const toastMessage = document.getElementById('toastMessage');
            const toastIcon = toast.querySelector('i');
            
            // Set content
            toastTitle.textContent = title;
            toastMessage.textContent = message;
            
            // Set style based on type
            toast.className = 'toast';
            if (type === 'error') {
                toast.classList.add('error');
                toastIcon.className = 'ph ph-x-circle';
            } else {
                toastIcon.className = 'ph ph-check-circle';
            }
            
            // Show toast
            toast.style.display = 'flex';
            
            // Auto hide after 5 seconds
            setTimeout(hideToast, 5000);
        }
        
        function hideToast() {
            const toast = document.getElementById('toast');
            toast.style.display = 'none';
        }
        
        // --- 9. Date Picker Initialization ---
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize flatpickr for date inputs
            if (typeof flatpickr !== 'undefined') {
                const dateConfig = {
                    dateFormat: "Y-m-d",
                    allowInput: true,
                    onChange: function(selectedDates, dateStr, instance) {
                        // Auto-adjust end date if start is after end
                        if (instance.input.classList.contains('work-date')) {
                            const startDate = document.getElementById('startDate');
                            const endDate = document.getElementById('endDate');
                            
                            if (instance.input === startDate && startDate.value && endDate.value) {
                                const start = new Date(startDate.value);
                                const end = new Date(endDate.value);
                                
                                if (start > end) {
                                    // Set end date to 7 days after start date
                                    start.setDate(start.getDate() + 7);
                                    endDate.value = start.toISOString().split('T')[0];
                                    
                                    // Update flatpickr instance
                                    const endPicker = endDate._flatpickr;
                                    if (endPicker) {
                                        endPicker.setDate(start, true);
                                    }
                                }
                            }
                        }
                    }
                };
                
                // Apply to date inputs
                document.querySelectorAll('.work-date').forEach(input => {
                    flatpickr(input, dateConfig);
                });
            }
            
            // Generate default work order number if empty
            const woNumberInput = document.getElementById('workOrderNumber');
            if (!woNumberInput.value.trim()) {
                const now = new Date();
                const dateStr = now.getFullYear() + 
                               String(now.getMonth() + 1).padStart(2, '0') + 
                               String(now.getDate()).padStart(2, '0');
                const randomNum = String(Math.floor(Math.random() * 999) + 1).padStart(3, '0');
                woNumberInput.value = `WO-${dateStr}-${randomNum}`;
            }
            
            // Add input event listeners to all material inputs
            document.addEventListener('input', function(e) {
                if (e.target.classList.contains('material-quantity') || 
                    e.target.classList.contains('material-unit-price')) {
                    const materialId = e.target.dataset.materialId;
                    if (materialId) {
                        calculateMaterialTotal(materialId);
                    }
                }
            });
            
            // Initialize total cost calculation
            updateTotalCost();
            
            // Auto-suggest subcontractor based on project name
            const projectNameInput = document.getElementById('projectName');
            const subcontractorSelect = document.getElementById('subcontractorSelect');
            
            projectNameInput.addEventListener('blur', function() {
                const projectName = this.value.toLowerCase();
                
                // Simple auto-suggestion logic
                if (projectName.includes('electr')) {
                    // Try to find electrical subcontractor
                    const options = subcontractorSelect.options;
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].text.toLowerCase().includes('electric') || 
                            options[i].text.toLowerCase().includes('electr')) {
                            options[i].selected = true;
                            break;
                        }
                    }
                } else if (projectName.includes('plumb')) {
                    // Try to find plumbing subcontractor
                    const options = subcontractorSelect.options;
                    for (let i = 0; i < options.length; i++) {
                        if (options[i].text.toLowerCase().includes('plumb') || 
                            options[i].text.toLowerCase().includes('pipe')) {
                            options[i].selected = true;
                            break;
                        }
                    }
                }
            });
        });
        
        // --- 10. Create Another Functionality ---
        function createAnother() {
            document.getElementById('createAnother').value = '1';
            document.getElementById('submitBtn').click();
        }
        
        // --- 11. Keyboard Shortcuts ---
        document.addEventListener('keydown', function(e) {
            // Ctrl + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                document.getElementById('submitBtn').click();
            }
            
            // Ctrl + N to add new material
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                addMaterialRow();
            }
            
            // Escape to cancel
            if (e.key === 'Escape') {
                if (confirm('Cancel creating work order?')) {
                    window.location.href = 'work_order.php';
                }
            }
        });
        
        // --- 12. Auto-calculate on page load ---
        window.addEventListener('load', function() {
            // Calculate any existing material totals
            document.querySelectorAll('.material-quantity').forEach(input => {
                const materialId = input.dataset.materialId;
                if (materialId) {
                    calculateMaterialTotal(materialId);
                }
            });
        });
    </script>
</body>
</html>