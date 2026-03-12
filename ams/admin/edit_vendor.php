<?php
/**
 * edit_vendor.php
 * Updated: Matches Dashboard V2 Layout.
 * Features: 
 * - Secure Image Upload (MIME check)
 * - Safe File Deletion (Only deletes old file after DB success)
 * - Bug Fixes & Input Sanitization
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

// 2. Database Connection
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) { die("DB Connection Failed: " . $ex->getMessage()); }

// 3. Helper Functions
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// 4. Pre-defined Lists
$categories = [
    "Raw Virgin Hair", "Processed Human Hair", "Synthetic Wigs", 
    "Lace Systems & Frontals", "Braiding Hair", "Hair Care Chemicals", 
    "Wig Tools & Accessories", "Packaging & Branding", "Shipping Partner"
];

$supply_types = ["Manufacturer", "Wholesaler", "Dropshipper", "Retailer", "Agent"];

// 5. Fetch User Photo (For Header)
$userPhoto = null;
try {
    $stmt = $pdo->prepare("SELECT photo_url FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userPhoto = $stmt->fetchColumn();
} catch (Exception $e) {}

// 6. FETCH VENDOR DATA
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: vendor_list.php");
    exit;
}
$id = $_GET['id'];
$vendor = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM vendors WHERE id = ?");
    $stmt->execute([$id]);
    $vendor = $stmt->fetch();
} catch(Exception $e) { die("Error fetching vendor."); }

if(!$vendor) die("Vendor not found.");

// 7. HANDLE UPDATE
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // ... [Collection of inputs remains the same] ...
        $company_name   = trim($_POST['company_name']);
        $contact_person = trim($_POST['contact_person']);
        $email          = trim($_POST['email']);
        $phone          = trim($_POST['phone']);
        $website        = trim($_POST['website']);
        $category       = trim($_POST['category']);
        $supply_type    = trim($_POST['supply_type']);
        $country        = trim($_POST['country']);
        $city           = trim($_POST['city']);
        $address        = trim($_POST['address']);
        $status         = $_POST['status'];

        // Validation
        if (empty($company_name) || empty($contact_person)) {
            throw new Exception("Company Name and Contact Person are required.");
        }

        // ... [Image Upload Logic remains the same] ...
        $final_logo_path = $vendor['logo_url']; 
        $new_image_uploaded = false;
        
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === 0) {
            // ... (Keep your existing image upload code here) ...
            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
            
            $fileName = $_FILES['logo']['name'];
            $fileTmp  = $_FILES['logo']['tmp_name'];
            $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowedExt)) throw new Exception("Invalid file extension.");
            
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($fileTmp);
            if (!in_array($mime, $allowedMime)) throw new Exception("Invalid file type detected.");

            $newName = 'v_' . uniqid() . '.' . $ext;
            $targetDir = 'uploads/vendors/';
            if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
            
            $dbPath = $targetDir . $newName;

            if (move_uploaded_file($fileTmp, $dbPath)) {
                $final_logo_path = $dbPath;
                $new_image_uploaded = true;
            } else {
                throw new Exception("Failed to move uploaded file.");
            }
        }

        // Update Query
        $sql = "UPDATE vendors SET 
                company_name=?, contact_person=?, email=?, phone=?, website=?, 
                category=?, supply_type=?, country=?, city=?, address=?, 
                status=?, logo_url=? 
                WHERE id=?";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            $company_name, $contact_person, $email, $phone, $website,
            $category, $supply_type, $country, $city, $address,
            $status, $final_logo_path, $id
        ]);

        // --- UPDATED SUCCESS LOGIC ---
        if ($result) {
            // 1. Delete old image if new one was uploaded
            if ($new_image_uploaded && !empty($vendor['logo_url']) && file_exists($vendor['logo_url'])) {
                unlink($vendor['logo_url']);
            }

            // 2. Redirect to Vendor List
            header("Location: vendor_list.php");
            exit; // Important: Stop script execution immediately
        }

    } catch (Exception $e) {
        $msg = $e->getMessage();
        $msgType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Vendor | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- CSS VARIABLES & RESET --- */
        :root {
            --primary: #4F46E5; --primary-hover: #4338ca;
            --bg-body: #F3F4F6; --bg-card: #ffffff;
            --text-main: #111827; --text-muted: #6B7280;
            --border: #E5E7EB;
            --sidebar-width: 280px; --sidebar-bg: #111827; --sidebar-text: #E5E7EB;
            --header-height: 64px; --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 0.75rem;
            --success-bg: #ECFDF5; --success-text: #065F46;
            --error-bg: #FEF2F2; --error-text: #991B1B;
        }

        [data-theme="dark"] {
            --bg-body: #0f172a; --bg-card: #1e293b; --text-main: #f8fafc;
            --text-muted: #94a3b8; --border: #334155; --sidebar-bg: #020617; --primary: #6366f1;
            --success-bg: #064E3B; --success-text: #D1FAE5;
            --error-bg: #7F1D1D; --error-text: #FEE2E2;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button { font-family: inherit; }

        /* --- SIDEBAR --- */
        body.sidebar-collapsed { --sidebar-width: 80px; }
        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-bg); color: var(--sidebar-text); display: flex; flex-direction: column; transition: width var(--transition), transform var(--transition); z-index: 50; flex-shrink: 0; white-space: nowrap; }
        
        .sidebar-header { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 700; font-size: 1.25rem; color: #fff; gap: 10px; overflow: hidden; }
        
        body.sidebar-collapsed .logo-text, body.sidebar-collapsed .link-text, body.sidebar-collapsed .arrow-icon { display: none; opacity: 0; }
        body.sidebar-collapsed .sidebar-header { justify-content: center; padding: 0; }
        body.sidebar-collapsed .menu-link { justify-content: center; padding: 12px 0; }
        body.sidebar-collapsed .link-content { gap: 0; }
        body.sidebar-collapsed .menu-icon { font-size: 1.5rem; margin: 0; }
        body.sidebar-collapsed .submenu { display: none !important; }

        .sidebar-menu { padding: 16px 12px; overflow-y: auto; overflow-x: hidden; flex: 1; }
        .menu-item { margin-bottom: 4px; }
        .menu-link { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 8px; color: rgba(255,255,255,0.7); cursor: pointer; transition: all 0.2s; font-size: 0.95rem; }
        .menu-link:hover, .menu-link.active { background-color: rgba(255,255,255,0.1); color: #fff; }
        .link-content { display: flex; align-items: center; gap: 12px; }
        .menu-icon { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .arrow-icon { transition: transform 0.3s; font-size: 0.8rem; opacity: 0.7; }
        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; padding-left: 12px; }
        .menu-item.open > .submenu { max-height: 500px; }
        .menu-item.open > .menu-link .arrow-icon { transform: rotate(180deg); }
        .submenu-link { display: block; padding: 10px 16px 10px 42px; color: rgba(255,255,255,0.5); font-size: 0.9rem; border-radius: 8px; }
        .submenu-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .submenu-link.active { color: #fff; background: var(--primary); }

        /* --- HEADER & LAYOUT --- */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; flex-shrink: 0; }
        .toggle-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); display: flex; align-items: center;}
        .toggle-btn:hover { color: var(--primary); }
        .header-right { display: flex; align-items: center; gap: 24px; }
        
        .profile-container { position: relative; }
        .profile-menu { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: background 0.2s; }
        .profile-menu:hover { background-color: var(--bg-body); }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-name { font-size: 0.9rem; font-weight: 600; display: block; }
        .profile-role { font-size: 0.75rem; color: var(--text-muted); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border); }
        .profile-placeholder { width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        
        .dropdown-menu { position: absolute; top: 120%; right: 0; width: 200px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); padding: 8px; z-index: 100; display: none; flex-direction: column; gap: 4px; }
        .dropdown-menu.show { display: flex; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; font-size: 0.9rem; color: var(--text-main); border-radius: 6px; transition: background 0.2s; }
        .dropdown-item:hover { background-color: var(--bg-body); color: var(--primary); }
        .dropdown-item.danger:hover { background-color: #fef2f2; color: #ef4444; }

        /* --- FORM CONTENT --- */
        .scrollable { flex: 1; overflow-y: auto; padding: 32px; }
        .card { background: var(--bg-card); border-radius: var(--radius); padding: 24px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); max-width: 900px; margin: 0 auto; }
        
        .form-header { margin-bottom: 24px; }
        .form-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 4px; }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; font-size: 0.95rem; }
        .alert.success { background-color: var(--success-bg); color: var(--success-text); }
        .alert.error { background-color: var(--error-bg); color: var(--error-text); }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; }
        .form-group { margin-bottom: 4px; }
        .form-group.full { grid-column: span 2; }
        .form-label { display: block; margin-bottom: 8px; font-size: 0.9rem; font-weight: 500; color: var(--text-main); }
        .form-control { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-body); color: var(--text-main); font-size: 0.95rem; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: var(--primary); }
        
        .btn-primary { background: var(--primary); color: white; padding: 12px 24px; border-radius: 8px; font-weight: 500; border: none; cursor: pointer; transition: 0.2s; font-size: 0.95rem; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-cancel { margin-left: 16px; padding: 12px 24px; color: var(--text-muted); cursor: pointer; background: none; border:none; font-size: 0.95rem; }
        .btn-cancel:hover { color: var(--text-main); text-decoration: underline; }

        /* Image Upload */
        .img-upload-container { display: flex; align-items: center; gap: 20px; padding: 16px; border: 1px dashed var(--border); border-radius: 8px; background: var(--bg-body); }
        .current-img { width: 80px; height: 80px; object-fit: contain; background: white; border: 1px solid var(--border); border-radius: 6px; }

        /* Mobile */
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; display: none; }
        @media (max-width: 768px) {
            body.sidebar-collapsed { --sidebar-width: 280px; } 
            .sidebar { position: fixed; left: -280px; height: 100%; }
            body.mobile-open .sidebar { transform: translateX(280px); }
            body.mobile-open .overlay { display: block; }
            .logo-text, .link-text, .arrow-icon { display: inline !important; opacity: 1 !important; }
            .sidebar-header { justify-content: flex-start !important; padding: 0 24px !important; }
            .top-header, .scrollable { padding: 16px; }
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full { grid-column: span 1; }
            .profile-info { display: none; }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle"><i class="ph ph-list"></i></button>
            </div>

            <div class="header-right">
                <button id="themeToggle" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:var(--text-muted);">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($_SESSION['username']); ?></span>
                            <span class="profile-role"><?php echo ucfirst(e($_SESSION['role'])); ?></span>
                        </div>
                        <?php if ($userPhoto): ?>
                            <img src="<?php echo e($userPhoto); ?>" alt="Profile" class="profile-img">
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile_settings.php" class="dropdown-item"><i class="ph ph-user-gear"></i> Profile Settings</a>
                        <div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>
                        <a href="logout.php" class="dropdown-item danger"><i class="ph ph-sign-out"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="scrollable">
            <div class="form-header">
                <h1 class="form-title">Edit Vendor</h1>
                <p style="color: var(--text-muted);">Updating details for: <strong><?php echo e($vendor['company_name']); ?></strong></p>
            </div>

            <?php if($msg): ?>
                <div class="alert <?php echo $msgType; ?>">
                    <i class="ph <?php echo $msgType == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>" style="font-size: 1.2rem;"></i>
                    <span><?php echo $msg; ?></span>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        
                        <div class="form-group full">
                            <label class="form-label">Company Logo</label>
                            <div class="img-upload-container">
                                <?php if (!empty($vendor['logo_url'])): ?>
                                    <img src="<?php echo e($vendor['logo_url']); ?>" class="current-img" id="previewImg">
                                <?php else: ?>
                                    <div class="current-img" id="previewImg" style="display:flex; align-items:center; justify-content:center; color:#ccc;">No Img</div>
                                <?php endif; ?>
                                <div style="flex:1">
                                    <input type="file" name="logo" accept="image/png, image/jpeg, image/webp" class="form-control" onchange="previewImage(this)">
                                    <small style="color: var(--text-muted); display:block; margin-top:5px;">Max 2MB. Format: JPG, PNG, WEBP.</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Company Name <span style="color:red">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo e($vendor['company_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Person <span style="color:red">*</span></label>
                            <input type="text" name="contact_person" class="form-control" value="<?php echo e($vendor['contact_person']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo e($vendor['email']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo e($vendor['phone']); ?>">
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Website</label>
                            <input type="text" name="website" class="form-control" value="<?php echo e($vendor['website']); ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Product Category</label>
                            <select name="category" class="form-control">
                                <option value="">Select Category</option>
                                <?php foreach($categories as $cat): ?>
                                    <option value="<?php echo e($cat); ?>" <?php echo ($cat === $vendor['category']) ? 'selected' : ''; ?>>
                                        <?php echo e($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                         <div class="form-group">
                            <label class="form-label">Supply Type</label>
                            <select name="supply_type" class="form-control">
                                <option value="">Select Type</option>
                                <?php foreach($supply_types as $type): ?>
                                    <option value="<?php echo e($type); ?>" <?php echo ($type === $vendor['supply_type']) ? 'selected' : ''; ?>>
                                        <?php echo e($type); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <option value="Active" <?php echo ($vendor['status']=='Active')?'selected':''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($vendor['status']=='Inactive')?'selected':''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="<?php echo e($vendor['country']); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?php echo e($vendor['city']); ?>">
                        </div>
                        <div class="form-group full">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="<?php echo e($vendor['address']); ?>">
                        </div>
                    </div>

                    <div style="margin-top: 30px; display:flex; align-items:center;">
                        <button type="submit" class="btn-primary">Update Vendor</button>
                        <a href="vendor_list.php" class="btn-cancel">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // --- 1. Sidebar Logic ---
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
        overlay.addEventListener('click', () => body.classList.remove('mobile-open'));

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

        // --- 3. Profile Dropdown ---
        function toggleProfileMenu() {
            const dropdown = document.getElementById('profileDropdown');
            dropdown.classList.toggle('show');
        }
        window.addEventListener('click', function(e) {
            if (!document.getElementById('profileContainer').contains(e.target)) {
                document.getElementById('profileDropdown').classList.remove('show');
            }
        });

        // --- 4. Dark Mode ---
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        // Init check
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

        // --- 5. Image Preview Logic ---
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('previewImg');
                    if (img.tagName === 'DIV') {
                        // Replace Div with Img tag if it was a placeholder
                        const newImg = document.createElement('img');
                        newImg.id = 'previewImg';
                        newImg.className = 'current-img';
                        newImg.src = e.target.result;
                        img.parentNode.replaceChild(newImg, img);
                    } else {
                        img.src = e.target.result;
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>