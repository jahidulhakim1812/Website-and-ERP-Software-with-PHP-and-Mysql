<?php
/**
 * add_customer.php
 * Standalone page to add a new customer.
 * Design: Merged with NexusAdmin Dashboard V2 Layout.
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


// 2. Database Config
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
} catch (PDOException $ex) {
    // In production, log this, don't echo
    $pdo = null;
}

// 3. Helper Functions
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// Fetch Admin Photo for Header (Optional - to match dashboard exactly)
$userPhoto = null;
if ($pdo && isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT photo_url FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userPhoto = $stmt->fetchColumn();
    } catch (Exception $e) {}
}

// 4. Form Handling Logic
$message = '';
$msgType = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A. Collect and sanitize input
    $name       = trim($_POST['full_name']);
    $email      = trim($_POST['email']);
    $phone      = trim($_POST['phone']);
    $company    = trim($_POST['company_name']); 
    $address    = trim($_POST['address']);
    $country    = trim($_POST['country']); // Now from Select
    $state      = trim($_POST['state']);
    $city       = trim($_POST['city']);
    $zip_code   = trim($_POST['zip_code']);
    $status     = $_POST['status'];
    
    // B. File Upload Logic
    $photoUrl = null;
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true); 
        
        $fileExt = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($fileExt, $allowed)) {
            $newFileName = uniqid('cust_', true) . '.' . $fileExt;
            $destPath = $uploadDir . $newFileName;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destPath)) {
                $photoUrl = $destPath;
            } else {
                 $message = "Error uploading file. Check folder permissions."; $msgType = 'error';
            }
        } else {
            $message = "Invalid file type. Only JPG, PNG, and WEBP are allowed."; $msgType = 'error';
        }
    }

    // C. Validation & Database Insertion
    if (empty($name) || empty($email)) {
        $message = "Name and Email are required fields.";
        $msgType = 'error';
    } else if ($msgType !== 'error' && $pdo) {
        try {
            $sql = "INSERT INTO customers (
                        full_name, email, phone, company_name, 
                        address, country, state, city, zip_code, 
                        status, photo_url, created_at, updated_at
                    ) VALUES (
                        ?, ?, ?, ?, 
                        ?, ?, ?, ?, ?, 
                        ?, ?, NOW(), NOW()
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $name, $email, $phone, $company, 
                $address, $country, $state, $city, $zip_code, 
                $status, $photoUrl
            ]);
            
            $message = "Customer <strong>" . e($name) . "</strong> added successfully!";
            $msgType = 'success';
            
            $_POST = []; // Clear form
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { 
                $message = "Error: A customer with this email already exists.";
            } else {
                $message = "Database Error: " . $e->getMessage();
            }
            $msgType = 'error';
        }
    } elseif (!$pdo) {
        $message = "Database connection unavailable.";
        $msgType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- CSS Variables & Reset (From Admin Dashboard V2) --- */
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

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; }
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

        /* --- Dashboard Body --- */
        .scrollable { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }
        
        /* --- Form Specific Styles --- */
        .card { 
            background: var(--bg-card); 
            border-radius: var(--radius); 
            padding: 32px; 
            border: 1px solid var(--border); 
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            max-width: 900px; 
            margin: 0 auto;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        
        .form-grid { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 24px; 
            margin-top: 24px; 
        }
        
        .form-group { 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
        }
        
        .col-span-2 { 
            grid-column: span 2; 
        }
        
        .form-section-title { 
            grid-column: span 2; 
            margin-top: 16px; 
            padding-bottom: 8px; 
            border-bottom: 1px solid var(--border); 
            color: var(--text-main); 
            font-weight: 600; 
            font-size: 1rem; 
        }

        label { 
            font-size: 0.9rem; 
            font-weight: 500; 
            color: var(--text-main); 
        }
        
        input[type="text"], input[type="email"], select, textarea {
            width: 100%; 
            padding: 12px 16px;
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-main);
            font-size: 0.95rem; 
            transition: border-color 0.2s;
        }
        
        input:focus, select:focus, textarea:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); 
        }
        
        .file-upload-box {
            border: 2px dashed var(--border); 
            border-radius: var(--radius); 
            padding: 30px;
            text-align: center; 
            cursor: pointer; 
            transition: all 0.2s; 
            position: relative;
        }
        
        .file-upload-box:hover { 
            border-color: var(--primary); 
            background: rgba(79,70,229,0.02); 
        }
        
        .file-input { 
            position: absolute; 
            width: 100%; 
            height: 100%; 
            top: 0; 
            left: 0; 
            opacity: 0; 
            cursor: pointer; 
        }
        
        .upload-icon { 
            font-size: 2rem; 
            color: var(--text-muted); 
            margin-bottom: 8px; 
        }
        
        .upload-text { 
            color: var(--text-muted); 
            font-size: 0.9rem; 
        }
        
        #preview-container { 
            margin-top: 15px; 
            display: none; 
            align-items: center; 
            gap: 12px; 
        }
        
        #img-preview { 
            width: 60px; 
            height: 60px; 
            border-radius: 50%; 
            object-fit: cover; 
            border: 2px solid var(--border); 
        }

        .btn-primary {
            background-color: var(--primary); 
            color: white; 
            border: none; 
            padding: 12px 24px;
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 1rem; 
            cursor: pointer;
            transition: background-color 0.2s; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px; 
            margin-top: 10px;
        }
        
        .btn-primary:hover { 
            background-color: var(--primary-hover); 
        }

        /* Alerts */
        .alert { 
            padding: 16px; 
            border-radius: 8px; 
            margin-bottom: 24px; 
            font-size: 0.95rem; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        
        .alert-success { 
            background-color: rgba(16, 185, 129, 0.1); 
            color: #065f46; 
            border: 1px solid #10b981; 
        }
        
        .alert-error { 
            background-color: rgba(239, 68, 68, 0.1); 
            color: #991b1b; 
            border: 1px solid #ef4444; 
        }
        
        [data-theme="dark"] .alert-success { 
            color: #34d399; 
        }
        
        [data-theme="dark"] .alert-error { 
            color: #f87171; 
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

        /* --- Responsive Design --- */
        @media (max-width: 768px) {
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
            
            .profile-info { 
                display: none; 
            }
            
            .form-grid { 
                grid-template-columns: 1fr; 
            }
            
            .col-span-2 { 
                grid-column: span 1; 
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
            <span style="font-size: 0.9rem; color: var(--text-muted);">Add New Customers</span>
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
            <div class="card">
                <div style="margin-bottom: 24px; border-bottom: 1px solid var(--border); padding-bottom: 16px;">
                    <h2 style="font-size: 1.5rem; font-weight: 700;">Add New Customer</h2>
                    <p style="color: var(--text-muted); margin-top: 4px;">Create a new profile with location and contact details.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert <?php echo ($msgType == 'success') ? 'alert-success' : 'alert-error'; ?>">
                        <i class="ph <?php echo ($msgType == 'success') ? 'ph-check-circle' : 'ph-warning-circle'; ?>" style="font-size: 1.2rem;"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-grid">
                        
                        <div class="form-section-title">Personal Information</div>

                        <div class="form-group">
                            <label for="full_name">Full Name <span style="color:#ef4444">*</span></label>
                            <input type="text" name="full_name" id="full_name" required placeholder="e.g. Jane Doe" value="<?php echo e($_POST['full_name'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address <span style="color:#ef4444">*</span></label>
                            <input type="email" name="email" id="email" required placeholder="jane@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" name="phone" id="phone" placeholder="+1 (555) 000-0000" value="<?php echo e($_POST['phone'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" name="company_name" id="company_name" placeholder="Acme Corp" value="<?php echo e($_POST['company_name'] ?? ''); ?>">
                        </div>

                        <div class="form-section-title">Address & Location</div>

                        <div class="form-group col-span-2">
                            <label for="address">Street Address</label>
                            <textarea name="address" id="address" rows="2" placeholder="123 Main St, Apt 4B"><?php echo e($_POST['address'] ?? ''); ?></textarea>
                        </div>

                       <div class="form-group">
                            <label for="country">Country</label>
                            <select name="country" id="country">
                                <option value="">Select Country</option>
                                <?php 
                                $countries = [
                                    "Afghanistan", "Albania", "Algeria", "Andorra", "Angola", "Antigua and Barbuda", 
                                    "Argentina", "Armenia", "Australia", "Austria", "Azerbaijan", "Bahamas", "Bahrain", 
                                    "Bangladesh", "Barbados", "Belarus", "Belgium", "Belize", "Benin", "Bhutan", 
                                    "Bolivia", "Bosnia and Herzegovina", "Botswana", "Brazil", "Brunei", "Bulgaria", 
                                    "Burkina Faso", "Burundi", "Cabo Verde", "Cambodia", "Cameroon", "Canada", 
                                    "Central African Republic", "Chad", "Chile", "China", "Colombia", "Comoros", 
                                    "Congo (Congo-Brazzaville)", "Costa Rica", "Croatia", "Cuba", "Cyprus", 
                                    "Czechia (Czech Republic)", "Democratic Republic of the Congo", "Denmark", 
                                    "Djibouti", "Dominica", "Dominican Republic", "East Timor (Timor-Leste)", 
                                    "Ecuador", "Egypt", "El Salvador", "Equatorial Guinea", "Eritrea", "Estonia", 
                                    "Eswatini", "Ethiopia", "Fiji", "Finland", "France", "Gabon", "Gambia", 
                                    "Georgia", "Germany", "Ghana", "Greece", "Grenada", "Guatemala", "Guinea", 
                                    "Guinea-Bissau", "Guyana", "Haiti", "Honduras", "Hungary", "Iceland", "India", 
                                    "Indonesia", "Iran", "Iraq", "Ireland", "Israel", "Italy", "Ivory Coast", 
                                    "Jamaica", "Japan", "Jordan", "Kazakhstan", "Kenya", "Kiribati", "Kosovo", 
                                    "Kuwait", "Kyrgyzstan", "Laos", "Latvia", "Lebanon", "Lesotho", "Liberia", 
                                    "Libya", "Liechtenstein", "Lithuania", "Luxembourg", "Madagascar", "Malawi", 
                                    "Malaysia", "Maldives", "Mali", "Malta", "Marshall Islands", "Mauritania", 
                                    "Mauritius", "Mexico", "Micronesia", "Moldova", "Monaco", "Mongolia", 
                                    "Montenegro", "Morocco", "Mozambique", "Myanmar (formerly Burma)", "Namibia", 
                                    "Nauru", "Nepal", "Netherlands", "New Zealand", "Nicaragua", "Niger", 
                                    "Nigeria", "North Korea", "North Macedonia", "Norway", "Oman", "Pakistan", 
                                    "Palau", "Palestine State", "Panama", "Papua New Guinea", "Paraguay", "Peru", 
                                    "Philippines", "Poland", "Portugal", "Qatar", "Romania", "Russia", "Rwanda", 
                                    "Saint Kitts and Nevis", "Saint Lucia", "Saint Vincent and the Grenadines", 
                                    "Samoa", "San Marino", "Sao Tome and Principe", "Saudi Arabia", "Senegal", 
                                    "Serbia", "Seychelles", "Sierra Leone", "Singapore", "Slovakia", "Slovenia", 
                                    "Solomon Islands", "Somalia", "South Africa", "South Korea", "South Sudan", 
                                    "Spain", "Sri Lanka", "Sudan", "Suriname", "Sweden", "Switzerland", "Syria", 
                                    "Taiwan", "Tajikistan", "Tanzania", "Thailand", "Togo", "Tonga", 
                                    "Trinidad and Tobago", "Tunisia", "Turkey", "Turkmenistan", "Tuvalu", "Uganda", 
                                    "Ukraine", "United Arab Emirates", "United Kingdom", "United States of America", 
                                    "Uruguay", "Uzbekistan", "Vanuatu", "Vatican City", "Venezuela", "Vietnam", 
                                    "Yemen", "Zambia", "Zimbabwe"
                                ];
                                
                                // Auto-select logic
                                $selectedCountry = $_POST['country'] ?? '';
                                
                                foreach($countries as $c) {
                                    $sel = ($c === $selectedCountry) ? 'selected' : '';
                                    echo "<option value=\"$c\" $sel>$c</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="state">State / Province</label>
                            <input type="text" name="state" id="state" placeholder="California" value="<?php echo e($_POST['state'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="city">City</label>
                            <input type="text" name="city" id="city" placeholder="San Francisco" value="<?php echo e($_POST['city'] ?? ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="zip_code">Zip / Postal Code</label>
                            <input type="text" name="zip_code" id="zip_code" placeholder="94105" value="<?php echo e($_POST['zip_code'] ?? ''); ?>">
                        </div>

                        <div class="form-section-title">Account Settings</div>

                        <div class="form-group">
                            <label for="status">Account Status</label>
                            <select name="status" id="status">
                                <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="form-group col-span-2">
                            <label>Profile Photo</label>
                            <div class="file-upload-box" id="dropZone">
                                <input type="file" name="photo" id="photo" class="file-input" accept="image/*" onchange="previewImage(event)">
                                <i class="ph ph-cloud-arrow-up upload-icon"></i>
                                <p class="upload-text">Click to upload or drag image here</p>
                            </div>
                            <div id="preview-container">
                                <img id="img-preview" src="#" alt="Preview">
                                <span id="file-name-display" style="font-size:0.9rem; color:var(--text-main);"></span>
                            </div>
                        </div>

                    </div>

                    <div style="margin-top: 32px; text-align: right;">
                        <button type="submit" class="btn-primary">
                            <i class="ph ph-floppy-disk"></i> Save Customer
                        </button>
                    </div>
                </form>
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

        // --- 5. Image Preview & Drag Drop Logic ---
        function previewImage(event) {
            const reader = new FileReader();
            reader.onload = function(){
                const output = document.getElementById('img-preview');
                output.src = reader.result;
                document.getElementById('preview-container').style.display = 'flex';
                document.getElementById('file-name-display').innerText = event.target.files[0].name;
                document.querySelector('.upload-text').innerText = "Change File";
            };
            if(event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        }
        
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('photo');

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = 'var(--primary)';
            dropZone.style.background = 'rgba(79, 70, 229, 0.05)';
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.style.borderColor = 'var(--border)';
            dropZone.style.background = 'transparent';
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.style.borderColor = 'var(--border)';
            dropZone.style.background = 'transparent';
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                previewImage({ target: fileInput });
            }
        });
    </script>
</body>
</html>