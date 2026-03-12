<?php
/**
 * add_product.php
 * NexusAdmin Inventory System
 * Simplified: Only Selling Price shown, all costing details removed
 */

session_start();

// --- 1. AUTHENTICATION & SECURITY ---
// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check admin session
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Session timeout (30 minutes)
$timeout = 1800;
if (isset($_SESSION['login_time'])) {
    $session_life = time() - $_SESSION['login_time'];
    if ($session_life > $timeout) {
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }
}
$_SESSION['login_time'] = time();

// Logout handling
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
    $pdo = null;
}

// --- 3. DATA PREPARATION ---
$categories = ["Wigs & Lace", "Bundles", "Frontals", "Hair Care", "Tools", "Packaging"];
$units = ["pcs", "box", "kg", "bundle", "set"];

$vendors = [];
if($pdo) {
    try {
        // Fetch only active vendors for dropdown
        $stmt_v = $pdo->query("SELECT id, company_name FROM vendors WHERE status = 'Active' ORDER BY company_name ASC");
        $vendors = $stmt_v->fetchAll();
    } catch (Exception $e) {
        // Ignore
    }
}

// --- 4. HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if(!$pdo) throw new Exception("Database connection failed.");

        // Inputs (all costing fields are now set to 0 or null)
        $product_name = trim($_POST['product_name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $category = $_POST['category'];
        $vendor_id = !empty($_POST['vendor_id']) ? $_POST['vendor_id'] : null;
        $description = $_POST['description'];
        
        $quantity = (int)$_POST['quantity'];
        $min_stock = (int)$_POST['min_stock'];
        $max_stock = (int)$_POST['max_stock'];
        $unit_type = $_POST['unit_type'];
        
        // Only selling price is taken from form; others forced to 0
        $selling_price = (float)$_POST['selling_price'];
        $purchase_price = 0.00;      // Not used anymore
        $discount = 0.00;             // Not used
        $tax_rate = 0.00;              // Not used
        $batch_name_str = null;        // No batch selection
        $status = $_POST['status'];

        // Validations
        if (empty($product_name) || empty($sku)) throw new Exception("Product Name and SKU are required.");
        if ($selling_price <= 0) throw new Exception("Selling price must be greater than zero.");

        // Duplicate SKU check
        $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $stmt->execute([$sku]);
        if($stmt->fetch()) throw new Exception("SKU '$sku' already exists.");

        // Image upload
        $image_url = null;
        if (!empty($_FILES['image']['name'])) {
            $targetDir = "uploads/products/";
            if(!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            $fileExt = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $fileName = "prod_" . time() . "_" . uniqid() . "." . $fileExt;
            if(move_uploaded_file($_FILES['image']['tmp_name'], $targetDir . $fileName)) {
                $image_url = $targetDir . $fileName;
            }
        }

        // Insert - all costing fields are set to 0 or null (batch_name)
        $sql = "INSERT INTO products (
            product_name, sku, category, vendor_id, description, image_url,
            quantity, min_stock, max_stock, unit_type,
            purchase_price, selling_price, discount, tax_rate,
            batch_name, 
            status, created_by, created_at
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
        
        $pdo->prepare($sql)->execute([
            $product_name, $sku, $category, $vendor_id, $description, $image_url,
            $quantity, $min_stock, $max_stock, $unit_type,
            $purchase_price, $selling_price, $discount, $tax_rate,
            $batch_name_str,
            $status, $_SESSION['user_id']
        ]);

        $_SESSION['notification'] = ['type' => 'success', 'message' => 'Product successfully added!'];
        header("Location: add_product.php");
        exit;

    } catch (Exception $e) {
        $_SESSION['notification'] = ['type' => 'error', 'message' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- CSS VARIABLES (Light Mode) --- */
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338ca;
            --primary-light: #EEF2FF;
            --primary-soft: #E0E7FF;
            --bg-body: #F9FAFB;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6B7280;
            --text-light: #9CA3AF;
            --border: #E5E7EB;
            --border-light: #F3F4F6;
            --sidebar-width: 280px;
            --sidebar-collapsed-width: 80px;
            --sidebar-bg: #111827;
            --sidebar-text: #E5E7EB;
            --header-height: 64px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 10px;
            --radius-sm: 8px;
            --radius-lg: 12px;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-dropdown: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            --success: #10B981; 
            --success-light: #D1FAE5;
            --error: #EF4444; 
            --error-light: #FEE2E2;
            --warning: #F59E0B;
            --warning-light: #FEF3C7;
        }

        /* --- DARK MODE VARIABLES --- */
        [data-theme='dark'] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --text-light: #64748b;
            --border: #334155;
            --border-light: #1e293b;
            --sidebar-bg: #020617;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.3), 0 1px 2px rgba(0, 0, 0, 0.4);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.5);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.7);
            --success-light: #064E3B;
            --error-light: #7F1D1D;
            --warning-light: #78350F;
        }

        /* --- RESET & BASE --- */
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; transition: all 0.3s ease; }
        a { text-decoration: none; color: inherit; }
        button { cursor: pointer; border: none; background: none; font-family: inherit; }
        ul { list-style: none; }
        input, select, textarea { font-family: 'Inter', sans-serif; }
        ::selection { background: var(--primary-light); color: var(--primary); }

        /* --- SIDEBAR --- */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            transition: width var(--transition);
            flex-shrink: 0;
            z-index: 50;
            white-space: nowrap;
            border-right: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            gap: 12px;
            overflow: hidden;
        }
        .sidebar-header i { 
            color: var(--primary); 
            font-size: 1.5rem; 
        }
        body.sidebar-collapsed { --sidebar-width: 80px; }
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
        .menu-item { margin-bottom: 4px; }
        .menu-link {
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            padding: 12px 16px; 
            border-radius: var(--radius-sm);
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
        .menu-item.open > .submenu { max-height: 500px; }
        .menu-item.open > .menu-link .arrow-icon { transform: rotate(180deg); }
        .menu-item.open > .menu-link { color: #fff; }
        .submenu-link {
            display: block; 
            padding: 10px 16px 10px 42px;
            color: rgba(255,255,255,0.5); 
            font-size: 0.9rem; 
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }
        .submenu-link:hover, .submenu-link.active { 
            color: #fff; 
            background: rgba(255,255,255,0.05); 
            transform: translateX(2px);
        }

        /* --- MAIN CONTENT --- */
        .main-content { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            min-width: 0; 
            background: var(--bg-body);
        }

        /* --- HEADER --- */
        .top-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            padding: 0 28px; 
            flex-shrink: 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 40;
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
            border-radius: var(--radius-sm);
        }
        .toggle-btn:hover { 
            color: var(--primary); 
            background: var(--bg-body);
        }
        .header-right { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
        }
        .profile-container { position: relative; }
        .profile-menu { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            cursor: pointer; 
            padding: 8px 12px; 
            border-radius: var(--radius-lg); 
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
            width: 40px; 
            height: 40px; 
            border-radius: var(--radius-sm); 
            object-fit: cover; 
            border: 2px solid var(--border); 
            transition: all 0.2s ease;
        }
        .profile-placeholder { 
            width: 40px; 
            height: 40px; 
            border-radius: var(--radius-sm); 
            background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%); 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            font-size: 1rem;
            border: 2px solid var(--primary-light);
        }
        .dropdown-menu {
            position: absolute; 
            top: calc(100% + 8px); 
            right: 0; 
            width: 220px;
            background: var(--bg-card); 
            border: 1px solid var(--border);
            border-radius: var(--radius-lg); 
            box-shadow: var(--shadow-dropdown);
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
        .dropdown-menu.show { display: flex; }
        .dropdown-item {
            display: flex; 
            align-items: center; 
            gap: 10px; 
            padding: 12px 16px;
            font-size: 0.9rem; 
            color: var(--text-main); 
            border-radius: var(--radius-sm); 
            transition: all 0.2s ease;
        }
        .dropdown-item:hover { 
            background-color: var(--bg-body); 
            color: var(--primary); 
        }
        .dropdown-item.danger:hover { 
            background-color: var(--error-light); 
            color: var(--error); 
        }
        #themeToggle {
            background: var(--bg-card);
            border: 1px solid var(--border);
            width: 40px;
            height: 40px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: var(--text-muted);
        }
        #themeToggle:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: rotate(15deg);
        }

        /* --- PAGE CONTENT --- */
        .content-scroll { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
        }
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 28px; 
        }
        .page-title { 
            font-size: 1.75rem; 
            font-weight: 700; 
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .page-title i { color: var(--primary); }
        .breadcrumbs { 
            font-size: 0.85rem; 
            color: var(--text-muted);
            margin-top: 6px;
        }
        .btn-primary { 
            background: linear-gradient(135deg, var(--primary) 0%, #6366f1 100%);
            color: white; 
            padding: 12px 24px; 
            border-radius: var(--radius-sm); 
            font-size: 0.95rem; 
            display: inline-flex; 
            align-items: center; 
            gap: 10px; 
            font-weight: 600;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(79, 70, 229, 0.2);
        }
        .btn-primary:hover { 
            background: linear-gradient(135deg, var(--primary-hover) 0%, #4f46e5 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 8px rgba(79, 70, 229, 0.25);
        }
        .btn-secondary { 
            padding: 12px 24px; 
            border: 1px solid var(--border); 
            border-radius: var(--radius-sm); 
            background: var(--bg-card); 
            color: var(--text-muted);
            font-weight: 500;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-secondary:hover { 
            background: var(--bg-body); 
            border-color: var(--primary);
            color: var(--primary);
        }

        /* --- FORM GRID --- */
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 28px; 
        }
        @media (max-width: 1200px) {
            .form-grid { grid-template-columns: 1fr; }
        }

        /* --- CARDS --- */
        .card { 
            background: var(--bg-card); 
            border-radius: var(--radius); 
            border: 1px solid var(--border); 
            padding: 28px; 
            margin-bottom: 24px; 
            box-shadow: var(--shadow);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card:hover {
            box-shadow: var(--shadow-md);
        }
        .card-title { 
            font-size: 1.1rem; 
            font-weight: 600; 
            margin-bottom: 22px; 
            padding-bottom: 16px; 
            border-bottom: 1px solid var(--border-light); 
            color: var(--primary); 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .card-title i { 
            font-size: 1.3rem; 
            background: var(--primary-light); 
            padding: 8px; 
            border-radius: 8px; 
            color: var(--primary);
        }

        /* --- FORM ELEMENTS --- */
        .form-group { 
            margin-bottom: 20px; 
        }
        .form-label { 
            display: block; 
            font-size: 0.9rem; 
            font-weight: 500; 
            margin-bottom: 8px; 
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .form-label i { 
            color: var(--primary); 
            font-size: 0.9rem; 
        }
        .form-control { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1.5px solid var(--border); 
            border-radius: var(--radius-sm); 
            background: var(--bg-body); 
            color: var(--text-main); 
            font-size: 0.95rem; 
            outline: none; 
            transition: all 0.2s ease;
            font-weight: 400;
        }
        .form-control:focus { 
            border-color: var(--primary); 
            background: var(--bg-card);
            box-shadow: 0 0 0 3px var(--primary-light); 
        }
        .form-control[readonly] { 
            opacity: 0.7; 
            cursor: not-allowed; 
            background: var(--border-light);
        }
        textarea.form-control { 
            min-height: 100px; 
            resize: vertical;
            line-height: 1.5;
        }
        .form-row { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
            margin-bottom: 20px;
        }
        .form-row-3 { 
            display: grid; 
            grid-template-columns: 1fr 1fr 1fr; 
            gap: 20px; 
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .form-row, .form-row-3 { grid-template-columns: 1fr; }
        }

        /* --- STATUS BADGES --- */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-badge.active {
            background: var(--success-light);
            color: #065F46;
        }
        .status-badge.draft {
            background: var(--warning-light);
            color: #92400E;
        }
        .status-badge.inactive {
            background: var(--error-light);
            color: #991B1B;
        }

        /* --- IMAGE UPLOAD --- */
        .image-upload {
            border: 2px dashed var(--border);
            border-radius: var(--radius-sm);
            padding: 32px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: var(--bg-body);
        }
        .image-upload:hover {
            border-color: var(--primary);
            background: var(--primary-light);
        }
        .image-upload i {
            font-size: 3rem;
            color: var(--text-light);
            margin-bottom: 16px;
            display: block;
        }
        .image-upload span {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        .image-preview {
            width: 100%;
            height: 180px;
            border-radius: var(--radius-sm);
            object-fit: cover;
            margin-top: 16px;
            border: 1px solid var(--border);
            display: none;
        }

        /* --- TOAST NOTIFICATION --- */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .toast {
            background: var(--bg-card);
            color: var(--text-main);
            padding: 18px 22px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 320px;
            border-left: 4px solid var(--primary);
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            opacity: 0;
        }
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        .toast.success {
            border-left-color: var(--success);
        }
        .toast.error {
            border-left-color: var(--error);
        }
        .toast-icon {
            font-size: 1.6rem;
        }
        .toast.success .toast-icon { color: var(--success); }
        .toast.error .toast-icon { color: var(--error); }
        .toast-content {
            flex: 1;
        }
        .toast-title {
            font-weight: 600;
            font-size: 0.95rem;
            margin-bottom: 2px;
        }
        .toast-message {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        /* --- OVERLAY --- */
        .overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            backdrop-filter: blur(3px);
            z-index: 45; 
            display: none; 
            animation: fadeIn 0.3s ease;
        }

        /* --- MOBILE RESPONSIVE --- */
        @media (max-width: 768px) {
            .sidebar { 
                position: fixed; 
                left: -280px; 
                height: 100%; 
                transition: left 0.3s ease; 
            }
            body.mobile-open .sidebar { left: 0; }
            .logo-text, .link-text, .arrow-icon { 
                display: inline !important; 
                opacity: 1 !important; 
            }
            .sidebar-header { 
                justify-content: flex-start !important; 
                padding: 0 24px !important; 
            }
            .top-header { padding: 0 20px; }
            .content-scroll { padding: 24px 20px; }
            .profile-info { display: none; }
            .form-grid { gap: 20px; }
            .card { padding: 20px; }
            .page-header { flex-direction: column; align-items: flex-start; gap: 16px; }
            .btn-primary, .btn-secondary { width: 100%; justify-content: center; }
        }

        /* --- UTILITY CLASSES --- */
        .text-success { color: var(--success); }
        .text-error { color: var(--error); }
        .text-warning { color: var(--warning); }
        .text-primary { color: var(--primary); }
        .mb-4 { margin-bottom: 24px; }
        .mt-4 { margin-top: 24px; }
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .flex-center { display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body id="body">

    <div class="overlay" id="overlay"></div>

    <div class="toast-container">
        <?php if(isset($_SESSION['notification'])): ?>
            <div class="toast <?php echo $_SESSION['notification']['type']; ?> show" id="toastMessage">
                <i class="ph <?php echo $_SESSION['notification']['type'] == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?> toast-icon"></i>
                <div class="toast-content">
                    <div class="toast-title">
                        <?php echo $_SESSION['notification']['type'] == 'success' ? 'Success!' : 'Error!'; ?>
                    </div>
                    <div class="toast-message"><?php echo $_SESSION['notification']['message']; ?></div>
                </div>
            </div>
            <?php unset($_SESSION['notification']); ?>
        <?php endif; ?>
    </div>

    <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Add Products</span>
                </div>
            </div>

            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo $_SESSION['username']; ?></span>
                            <span class="profile-role"><?php echo ucfirst($_SESSION['role']); ?></span>
                        </div>
                        <?php 
                        $userPhoto = '';
                        if ($pdo && isset($_SESSION['user_id'])) {
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
                            <img src="<?php echo $userPhoto; ?>" alt="Profile" class="profile-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
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

        <div class="content-scroll">
            <div class="page-header">
                <div>
                    <h1 class="page-title">
                        <i class="ph ph-plus-circle"></i> Create New Product
                    </h1>
                    <div class="breadcrumbs">Inventory / Add Product</div>
                </div>
                <a href="inventory_list.php" class="btn-secondary">
                    <i class="ph ph-arrow-left"></i> Back to Inventory
                </a>
            </div>

            <form method="POST" enctype="multipart/form-data" id="productForm">
                <div class="form-grid">
                    
                    <!-- LEFT COLUMN -->
                    <div class="left-col">
                        <!-- General Info Card -->
                        <div class="card">
                            <div class="card-title">
                                <i class="ph ph-tag"></i> General Information
                            </div>
                            <div class="form-group">
                                <label class="form-label"><i class="ph ph-package"></i> Product Name *</label>
                                <input type="text" name="product_name" class="form-control" placeholder="e.g. Silk Wigs" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label"><i class="ph ph-barcode"></i> SKU *</label>
                                    <div style="display:flex; gap:10px;">
                                        <input type="text" name="sku" id="sku" class="form-control" placeholder="e.g. PROD-001" required>
                                        <button type="button" onclick="generateSKU()" class="btn-secondary" style="padding: 0 16px;">
                                            <i class="ph ph-magic-wand"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="ph ph-folders"></i> Category</label>
                                    <select name="category" class="form-control">
                                        <?php foreach($categories as $c): ?>
                                            <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="ph ph-note"></i> Description</label>
                                <textarea name="description" class="form-control" rows="4" placeholder="Enter product description..."></textarea>
                            </div>
                        </div>

                        <!-- Pricing Card (Simplified: only Selling Price) -->
                        <div class="card">
                            <div class="card-title">
                                <i class="ph ph-currency-dollar"></i> Pricing
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="ph ph-currency-dollar"></i> Selling Price *</label>
                                <input type="number" step="0.01" name="selling_price" id="price" class="form-control" required>
                            </div>

                            <!-- Hidden fields for unused costing values (set to 0) -->
                            <input type="hidden" name="purchase_price" value="0">
                            <input type="hidden" name="tax_rate" value="0">
                            <input type="hidden" name="discount" value="0">
                            <!-- batch_ids[] not needed, batch_name will be null in PHP -->
                        </div>
                    </div>

                    <!-- RIGHT COLUMN -->
                    <div class="right-col">
                        <!-- Stock Management Card -->
                        <div class="card">
                            <div class="card-title">
                                <i class="ph ph-archive-box"></i> Stock Management
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="ph ph-stack"></i> Total Quantity *</label>
                                <input type="number" name="quantity" id="quantityInput" class="form-control" value="1" min="1">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="ph ph-ruler"></i> Unit Type</label>
                                <select name="unit_type" class="form-control">
                                    <?php foreach($units as $u): ?>
                                        <option value="<?php echo $u; ?>"><?php echo ucfirst($u); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label"><i class="ph ph-arrow-down"></i> Min Stock</label>
                                    <input type="number" name="min_stock" class="form-control" value="5" min="0">
                                </div>
                                <div class="form-group">
                                    <label class="form-label"><i class="ph ph-arrow-up"></i> Max Stock</label>
                                    <input type="number" name="max_stock" class="form-control" value="100" min="1">
                                </div>
                            </div>
                        </div>

                        <!-- Status & Vendor Card -->
                        <div class="card">
                            <div class="card-title">
                                <i class="ph ph-toggle-left"></i> Status & Vendor
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="ph ph-circle-wavy"></i> Status</label>
                                <select name="status" class="form-control" onchange="updateStatusBadge(this.value)">
                                    <option value="Active">Active</option>
                                    <option value="Draft">Draft</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                                <div id="statusBadge" class="status-badge active" style="margin-top: 10px; display: inline-flex;">
                                    <i class="ph ph-check-circle"></i> Active
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label"><i class="ph ph-storefront"></i> Vendor</label>
                                <select name="vendor_id" class="form-control">
                                    <option value="">Select Vendor</option>
                                    <?php foreach($vendors as $v): ?>
                                        <option value="<?php echo $v['id']; ?>"><?php echo htmlspecialchars($v['company_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Image Upload Card -->
                        <div class="card">
                            <div class="card-title">
                                <i class="ph ph-image"></i> Product Image
                            </div>
                            
                            <div class="image-upload" onclick="document.getElementById('imageInput').click()">
                                <i class="ph ph-cloud-arrow-up"></i>
                                <span>Click to upload product image</span>
                                <input type="file" name="image" id="imageInput" style="display: none;" onchange="previewImage(event)">
                            </div>
                            <img id="imagePreview" class="image-preview" src="" alt="Preview">
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; margin-top: 20px;">
                            <i class="ph ph-check-circle"></i> Create Product
                        </button>
                    </div>

                </div>
            </form>
        </div>
    </main>

    <script>
        // --- Sidebar Logic ---
        const sidebarToggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('overlay');
        const body = document.body;

        function handleSidebarToggle() {
            if (window.innerWidth <= 768) {
                body.classList.toggle('mobile-open');
                body.classList.remove('sidebar-collapsed'); 
                if(body.classList.contains('mobile-open')) {
                    overlay.style.display = 'block';
                } else {
                    overlay.style.display = 'none';
                }
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
            overlay.style.display = 'none';
            closeAllDropdowns();
        });

        // --- Accordion Logic ---
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

        // --- Profile Dropdown Logic ---
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

        // --- Dark Mode Logic ---
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

        // Escape key to close dropdowns
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });

        // --- FORM FUNCTIONALITY (Simplified) ---
        
        // Generate SKU
        function generateSKU() {
            const prefix = 'PROD-';
            const randomNum = Math.floor(10000 + Math.random() * 90000);
            document.getElementById('sku').value = prefix + randomNum;
        }

        // Update Status Badge
        function updateStatusBadge(status) {
            const badge = document.getElementById('statusBadge');
            badge.className = 'status-badge ' + status.toLowerCase();
            badge.innerHTML = `<i class="ph ph-${status === 'Active' ? 'check-circle' : status === 'Draft' ? 'pencil-circle' : 'x-circle'}"></i> ${status}`;
        }

        // Image Preview
        function previewImage(event) {
            const preview = document.getElementById('imagePreview');
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        }

        // No cost calculations needed anymore

        // --- NOTIFICATION AUTO-HIDE ---
        setTimeout(() => {
            const toast = document.getElementById('toastMessage');
            if(toast) {
                toast.classList.remove('show');
                setTimeout(() => {
                    if(toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 400);
            }
        }, 4000);

        // --- FORM VALIDATION ---
        document.getElementById('productForm').addEventListener('submit', function(e) {
            const sku = document.getElementById('sku').value;
            const productName = document.querySelector('input[name="product_name"]').value;
            const sellingPrice = document.getElementById('price').value;
            
            if(!sku || !productName || !sellingPrice) {
                e.preventDefault();
                alert('Please fill in all required fields (Product Name, SKU, and Selling Price)');
            }
            if (sellingPrice <= 0) {
                e.preventDefault();
                alert('Selling price must be greater than zero.');
            }
        });
    </script>
</body>
</html>