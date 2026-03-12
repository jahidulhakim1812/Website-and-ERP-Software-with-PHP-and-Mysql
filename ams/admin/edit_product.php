<?php
/**
 * edit_product.php
 * NexusAdmin - Edit Existing Product
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
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) { die("DB Connection Error: " . $ex->getMessage()); }

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// Initialize variables
$product = null;
$error_msg = null;

// --- 4. DATA SETUP (Dropdowns) ---

// A. CATEGORIES (Hardcoded for demo, or fetch from DB)
$category_list = ["Wigs & Lace", "Bundles", "Frontals", "Hair Care", "Tools", "Packaging"];

// B. VENDORS (Fetch from Database)
try {
    $vStmt = $pdo->query("SELECT * FROM vendors ORDER BY company_name ASC"); 
    $vendors = $vStmt->fetchAll();
} catch (PDOException $e) {
    $vendors = [];
}

// --- 5. LOGIC FLOW ---

// A. Handle Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Collect Input
    $id = $_POST['product_id'];
    $name = $_POST['product_name'];
    $sku = $_POST['sku'];
    $category = $_POST['category']; 
    $vendor_id = $_POST['vendor_id'];
    $desc = $_POST['description'];
    
    // Inventory
    $qty = $_POST['quantity'];
    $min_stock = $_POST['min_stock'];
    $max_stock = $_POST['max_stock'];
    $unit = $_POST['unit_type'];
    
    // Pricing
    $sell_price = $_POST['selling_price'];
    $buy_price = $_POST['purchase_price'];
    $tax = $_POST['tax_rate'];
    $discount = $_POST['discount'];
    $status = $_POST['status'];
    
    // Image Logic: Default to the existing image
    $image_url = $_POST['current_image'];

    // 2. Handle New Image Upload (If provided)
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === 0) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = time() . '_' . basename($_FILES['product_image']['name']);
        $targetPath = $uploadDir . $fileName;
        
        $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        if (in_array($fileType, ['jpg', 'jpeg', 'png', 'webp'])) {
            if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
                $image_url = $targetPath; // Update to new path
            }
        }
    }

    // 3. Update Database
    $sql = "UPDATE products SET 
            product_name=?, sku=?, category=?, vendor_id=?, description=?, 
            quantity=?, min_stock=?, max_stock=?, unit_type=?, 
            selling_price=?, purchase_price=?, tax_rate=?, discount=?, 
            status=?, image_url=?, updated_at=NOW()
            WHERE id=?";

    try {
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute([
            $name, $sku, $category, $vendor_id, $desc,
            $qty, $min_stock, $max_stock, $unit,
            $sell_price, $buy_price, $tax, $discount,
            $status, $image_url, $id
        ]);

        $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Product updated successfully!'];
        header("Location: inventory_list.php"); 
        exit;

    } catch (Exception $e) {
        $error_msg = "Error updating product: " . $e->getMessage();
        // Repopulate array so form doesn't wipe out on error
        $product = $_POST;
        $product['id'] = $id; 
    }
}

// B. Handle Initial Page Load (GET)
if (isset($_GET['id'])) {
    // If we haven't already loaded product via POST error
    if (!$product) {
        $id = $_GET['id'];
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product) {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Product not found.'];
            header("Location: inventory_list.php");
            exit;
        }
    }
} else {
    // No ID provided in URL
    header("Location: inventory_list.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    
    <style>
        :root { --primary: #4F46E5; --primary-hover: #4338ca; --bg-body: #F3F4F6; --bg-card: #ffffff; --text-main: #111827; --text-muted: #6B7280; --border: #E5E7EB; --sidebar-width: 280px; --sidebar-bg: #111827; --sidebar-text: #E5E7EB; --header-height: 64px; --radius: 0.75rem; --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); --success: #10B981; --error: #EF4444; }
        [data-theme="dark"] { --bg-body: #0f172a; --bg-card: #1e293b; --text-main: #f8fafc; --text-muted: #94a3b8; --border: #334155; --sidebar-bg: #020617; --primary: #6366f1; }
        
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        
        /* Sidebar & Layout */
        body.sidebar-collapsed { --sidebar-width: 80px; }
        .sidebar { width: var(--sidebar-width); background: var(--sidebar-bg); color: var(--sidebar-text); display: flex; flex-direction: column; transition: width var(--transition); z-index: 50; flex-shrink: 0; white-space: nowrap; }
        .sidebar-header { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 700; font-size: 1.25rem; gap: 10px; overflow: hidden; color: #fff; }
        body.sidebar-collapsed .logo-text, body.sidebar-collapsed .link-text, body.sidebar-collapsed .arrow-icon { display: none; }
        body.sidebar-collapsed .sidebar-header { justify-content: center; padding: 0; }
        body.sidebar-collapsed .menu-link { justify-content: center; padding: 12px 0; }
        body.sidebar-collapsed .link-content { gap: 0; }
        body.sidebar-collapsed .menu-icon { font-size: 1.5rem; margin: 0; }
        body.sidebar-collapsed .submenu { display: none !important; }
        .sidebar-menu { padding: 16px 12px; overflow-y: auto; flex: 1; }
        .menu-item { margin-bottom: 4px; }
        .menu-link { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 8px; color: rgba(255,255,255,0.7); cursor: pointer; transition: 0.2s; font-size: 0.95rem; }
        .menu-link:hover, .menu-link.active { background: rgba(255,255,255,0.1); color: #fff; }
        .link-content { display: flex; align-items: center; gap: 12px; }
        .menu-icon { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .arrow-icon { transition: transform 0.3s; font-size: 0.8rem; opacity: 0.7; }
        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; padding-left: 12px; }
        .menu-item.open > .submenu { max-height: 500px; }
        .menu-item.open > .menu-link .arrow-icon { transform: rotate(180deg); }
        .submenu-link { display: block; padding: 10px 16px 10px 42px; color: rgba(255,255,255,0.5); font-size: 0.9rem; border-radius: 8px; }
        .submenu-link:hover, .submenu-link.active { color: #fff; background: rgba(255,255,255,0.05); }
        
        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; flex-shrink: 0; }
        .toggle-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; }
        .header-right { display: flex; align-items: center; gap: 24px; }
        
        /* Profile & Dropdown */
        .profile-menu { position: relative; display: flex; align-items: center; gap: 12px; cursor: pointer; }
        .profile-img { width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .profile-dropdown { position: absolute; top: 55px; right: 0; width: 200px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); padding: 8px; display: none; flex-direction: column; z-index: 100; }
        .profile-dropdown.show { display: flex; animation: slideDown 0.2s ease-out; }
        @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; font-size: 0.9rem; color: var(--text-main); border-radius: 8px; transition: 0.2s; }
        .dropdown-item:hover { background: var(--bg-body); }
        .dropdown-divider { height: 1px; background: var(--border); margin: 6px 0; }
        
        /* Forms & Cards */
        .scrollable { flex: 1; overflow-y: auto; padding: 32px; }
        .page-header { margin-bottom: 24px; }
        .page-title { font-size: 1.5rem; font-weight: 700; }
        .breadcrumbs { font-size: 0.85rem; color: var(--text-muted); }
        
        .form-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; }
        .card { background: var(--bg-card); border: 1px solid var(--border); border-radius: var(--radius); padding: 24px; margin-bottom: 24px; }
        .card-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 500; font-size: 0.9rem; color: var(--text-muted); }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 6px; background: var(--bg-body); color: var(--text-main); font-size: 0.95rem; outline: none; transition: 0.2s; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        textarea.form-control { resize: vertical; min-height: 100px; }
        
        /* Image Upload */
        .image-preview-area { text-align: center; padding: 20px; border: 2px dashed var(--border); border-radius: 8px; background: var(--bg-body); transition: border-color 0.2s; }
        .image-preview-area:hover { border-color: var(--primary); }
        .current-img { max-width: 100px; max-height: 100px; border-radius: 6px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto; object-fit: cover; }
        .preview-placeholder { font-size: 2rem; color: var(--text-muted); margin-bottom: 10px; }
        
        /* Buttons */
        .btn-submit { width: 100%; background: var(--primary); color: white; padding: 12px; border: none; border-radius: 8px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .btn-submit:hover { background: var(--primary-hover); }
        .btn-cancel { width: 100%; background: transparent; color: var(--text-muted); padding: 12px; border: 1px solid var(--border); border-radius: 8px; font-weight: 600; cursor: pointer; text-align: center; margin-top: 12px; display: block; box-sizing: border-box; }
        .btn-cancel:hover { background: var(--bg-body); color: var(--text-main); }
        
        /* Mobile */
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; display: none; }
        @media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .sidebar { position: fixed; left: -280px; height: 100%; } body.mobile-open .sidebar { left: 0; } body.mobile-open .overlay { display: block; } .form-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>
    
    <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <button class="toggle-btn" id="sidebarToggle"><i class="ph ph-list"></i></button>
            <div class="header-right">
                <button id="themeToggle" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:var(--text-muted);"><i class="ph ph-moon" id="themeIcon"></i></button>
                <div class="profile-menu" id="profileMenuBtn">
                    <div style="text-align:right; font-size:0.9rem; line-height:1.2;"><span style="display:block; font-weight:600;"><?php echo e($_SESSION['username']); ?></span><span style="color:var(--text-muted); font-size:0.75rem;"><?php echo ucfirst(e($_SESSION['role'])); ?></span></div>
                    <div class="profile-img"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                    <div class="profile-dropdown" id="profileDropdown">
                        <a href="profile_settings.php" class="dropdown-item"><i class="ph ph-user-gear"></i> Profile Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item"><i class="ph ph-sign-out"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="scrollable">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Edit Product</h1>
                    <div class="breadcrumbs">Inventory / Edit Product</div>
                </div>
            </div>

            <?php if(isset($error_msg)): ?>
                <div style="background:rgba(239, 68, 68, 0.1); color:var(--error); padding:12px; border-radius:8px; margin-bottom:20px; border:1px solid var(--error); display:flex; align-items:center; gap:8px;">
                    <i class="ph ph-warning-circle" style="font-size:1.2rem;"></i>
                    <?php echo $error_msg; ?>
                </div>
            <?php endif; ?>
            
            <?php if($product): ?>
            <form method="POST" enctype="multipart/form-data" class="form-grid">
                
                <input type="hidden" name="product_id" value="<?php echo e($product['id']); ?>">

                <div class="col-left">
                    
                    <div class="card">
                        <div class="card-title">General Information</div>
                        
                        <div class="form-group">
                            <label>Product Name</label>
                            <input type="text" name="product_name" class="form-control" value="<?php echo e($product['product_name']); ?>" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>SKU / Barcode</label>
                                <input type="text" name="sku" class="form-control" value="<?php echo e($product['sku']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Category</label>
                                <select name="category" class="form-control" required>
                                    <option value="">Select Category</option>
                                    <?php foreach($category_list as $cat): ?>
                                        <option value="<?php echo $cat; ?>" <?php echo ($product['category'] == $cat) ? 'selected' : ''; ?>>
                                            <?php echo $cat; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Supplier / Vendor</label>
                            <select name="vendor_id" class="form-control" required>
                                <option value="">Select Vendor</option>
                                <?php foreach($vendors as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo ($product['vendor_id'] == $v['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($v['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" class="form-control"><?php echo e($product['description']); ?></textarea>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-title">Pricing & Financials</div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Purchase Price ($)</label>
                                <input type="number" step="0.01" name="purchase_price" class="form-control" value="<?php echo e($product['purchase_price']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Selling Price ($)</label>
                                <input type="number" step="0.01" name="selling_price" class="form-control" value="<?php echo e($product['selling_price']); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Tax Rate (%)</label>
                                <input type="number" step="0.01" name="tax_rate" class="form-control" value="<?php echo e($product['tax_rate']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Discount (%)</label>
                                <input type="number" step="0.01" name="discount" class="form-control" value="<?php echo e($product['discount']); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-right">
                    
                    <div class="card">
                        <div class="card-title">Inventory Management</div>
                        
                        <div class="form-group">
                            <label>Current Quantity</label>
                            <input type="number" name="quantity" class="form-control" value="<?php echo e($product['quantity']); ?>" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Min Stock</label>
                                <input type="number" name="min_stock" class="form-control" value="<?php echo e($product['min_stock']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Max Stock</label>
                                <input type="number" name="max_stock" class="form-control" value="<?php echo e($product['max_stock']); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Unit Type</label>
                            <select name="unit_type" class="form-control">
                                <option value="pcs" <?php echo ($product['unit_type'] == 'pcs') ? 'selected' : ''; ?>>Pieces (pcs)</option>
                                <option value="kg" <?php echo ($product['unit_type'] == 'kg') ? 'selected' : ''; ?>>Kilogram (kg)</option>
                                <option value="box" <?php echo ($product['unit_type'] == 'box') ? 'selected' : ''; ?>>Box</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="Active" <?php echo ($product['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Draft" <?php echo ($product['status'] == 'Draft') ? 'selected' : ''; ?>>Draft</option>
                                <option value="Inactive" <?php echo ($product['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-title">Product Image</div>
                        
                        <input type="hidden" name="current_image" value="<?php echo e($product['image_url']); ?>">
                        
                        <div class="image-preview-area" id="dropZone">
                            <div id="previewContainer">
                                <?php if(!empty($product['image_url'])): ?>
                                    <img src="<?php echo e($product['image_url']); ?>" class="current-img" id="previewImg" alt="Current Image">
                                    <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:10px;" id="previewText">Current Image</p>
                                    <div class="preview-placeholder" id="placeholderIcon" style="display:none;"><i class="ph ph-image"></i></div>
                                <?php else: ?>
                                    <div class="preview-placeholder" id="placeholderIcon"><i class="ph ph-image"></i></div>
                                    <img src="" class="current-img" id="previewImg" style="display:none;" alt="Preview">
                                    <p style="font-size:0.8rem; color:var(--text-muted);" id="previewText">No image uploaded</p>
                                <?php endif; ?>
                            </div>
                            
                            <input type="file" name="product_image" id="imgInp" class="form-control" accept="image/*" style="font-size:0.85rem; margin-top:10px;">
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="ph ph-floppy-disk"></i> Save Changes
                    </button>
                    <a href="inventory_list.php" class="btn-cancel">Cancel</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // --- Sidebar Logic ---
        const toggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('overlay');
        const body = document.body;
        
        toggle.addEventListener('click', () => {
            if (window.innerWidth <= 768) body.classList.toggle('mobile-open');
            else body.classList.toggle('sidebar-collapsed');
        });
        overlay.addEventListener('click', () => body.classList.remove('mobile-open'));

        // --- Theme Logic ---
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

        // --- Sidebar Submenus ---
        const menuItems = document.querySelectorAll('.has-submenu');
        menuItems.forEach(item => {
            const link = item.querySelector('.menu-link');
            link.addEventListener('click', (e) => {
                e.preventDefault();
                item.classList.toggle('open');
            });
        });

        // --- Profile Dropdown ---
        const profileBtn = document.getElementById('profileMenuBtn');
        const profileDropdown = document.getElementById('profileDropdown');
        profileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });
        document.addEventListener('click', (e) => {
            if (!profileBtn.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });

        // --- Image Previewer ---
        const imgInp = document.getElementById('imgInp');
        const previewImg = document.getElementById('previewImg');
        const previewText = document.getElementById('previewText');
        const placeholderIcon = document.getElementById('placeholderIcon');

        imgInp.onchange = evt => {
            const [file] = imgInp.files;
            if (file) {
                if (file.type.startsWith('image/')) {
                    previewImg.src = URL.createObjectURL(file);
                    previewImg.style.display = 'block';
                    previewText.textContent = 'New Image Selected';
                    if(placeholderIcon) placeholderIcon.style.display = 'none';
                } else {
                    alert('Please select a valid image file');
                    imgInp.value = ''; 
                }
            }
        }
    </script>
</body>
</html>