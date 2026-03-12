<?php
/**
 * add_employee.php
 * Integrated with NexusAdmin V2 Design
 * Features: Photo Upload + Consistent Header/Sidebar
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

// --- 3. FORM HANDLING ---
$message = '';
$msgType = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $designation = trim($_POST['designation']);
    $department = trim($_POST['department']);
    $salary = floatval($_POST['salary']);
    $joining_date = $_POST['joining_date'];
    $status = $_POST['status'];

    // --- PHOTO UPLOAD LOGIC ---
    $photoPath = ''; // Default empty
    
    // If user didn't upload a photo, generate a default avatar URL
    if (empty($_FILES['photo']['name'])) {
        $photoPath = "https://ui-avatars.com/api/?name=" . urlencode($name) . "&background=random&color=fff";
    } else {
        // Process uploaded file
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true); // Create folder if it doesn't exist
        }

        $fileName = basename($_FILES["photo"]["name"]);
        $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowTypes = array('jpg', 'png', 'jpeg', 'gif', 'webp');

        // Generate unique name to prevent overwriting
        $newFileName = uniqid() . '_' . time() . '.' . $fileType;
        $targetFilePath = $targetDir . $newFileName;

        if (in_array($fileType, $allowTypes)) {
            if (move_uploaded_file($_FILES["photo"]["tmp_name"], $targetFilePath)) {
                $photoPath = $targetFilePath;
            } else {
                $message = "Error uploading file.";
                $msgType = "error";
            }
        } else {
            $message = "Invalid file type. Only JPG, PNG, GIF, & WEBP allowed.";
            $msgType = "error";
        }
    }

    // Insert into DB if no upload errors
    if ($msgType !== "error") {
        if (empty($name) || empty($email) || empty($salary)) {
            $message = "Please fill in all required fields (Name, Email, Salary).";
            $msgType = "error";
        } else {
            try {
                // ADDED 'photo' COLUMN HERE
                $sql = "INSERT INTO employees (full_name, email, phone, designation, department, salary, joining_date, status, photo) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $email, $phone, $designation, $department, $salary, $joining_date, $status, $photoPath]);
                
                $message = "Employee added successfully!";
                $msgType = "success";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { 
                    $message = "Error: Email address already exists.";
                } else {
                    $message = "Database Error: " . $e->getMessage();
                }
                $msgType = "error";
            }
        }
    }
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee | NexusAdmin</title>
    
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

        /* --- Form Page Specific Styles --- */
        .scrollable { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
            justify-content: flex-start;
            scroll-behavior: smooth;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            width: 100%;
            max-width: 800px;
        }
        
        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text-main);
        }
        
        .page-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .form-card { 
            background: var(--bg-card); 
            width: 100%; 
            max-width: 800px; 
            border-radius: var(--radius); 
            border: 1px solid var(--border); 
            padding: 40px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); 
            margin-top: 10px;
        }
        
        .card-header {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        /* Form Grid */
        .form-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 20px; 
            margin-bottom: 24px;
        }
        
        .form-group { 
            margin-bottom: 16px; 
        }
        
        .form-group.full-width { 
            grid-column: span 2; 
        }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            font-weight: 500; 
            color: var(--text-main);
            font-size: 0.9rem;
        }
        
        label span { 
            color: #EF4444; 
        }
        
        input, select { 
            width: 100%; 
            padding: 12px 16px; 
            border: 1px solid var(--border); 
            border-radius: 8px; 
            font-size: 1rem; 
            background: var(--bg-body); 
            color: var(--text-main); 
            outline: none; 
            transition: 0.2s; 
        }
        
        input:focus, select:focus { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); 
        }
        
        .btn-submit { 
            background: var(--primary); 
            color: white; 
            padding: 14px 32px; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: 0.2s; 
            font-size: 1rem; 
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-submit:hover { 
            background: var(--primary-hover); 
            transform: translateY(-1px);
        }

        /* Upload Styles */
        .upload-area { 
            display: flex; 
            align-items: center; 
            gap: 20px; 
            padding: 16px; 
            border: 1px dashed var(--border); 
            border-radius: 8px; 
            background: var(--bg-body);
        }
        
        .preview-box { 
            width: 80px; 
            height: 80px; 
            border-radius: 50%; 
            background: var(--bg-body); 
            overflow: hidden; 
            flex-shrink: 0; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            color: var(--text-muted);
            border: 2px solid var(--border);
        }
        
        .preview-box img { 
            width: 100%; 
            height: 100%; 
            object-fit: cover; 
        }
        
        .upload-input-group { 
            flex: 1; 
        }
        
        input[type="file"] { 
            padding: 8px; 
            background: var(--bg-card); 
        }
        
        .file-info {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 8px;
        }

        /* Alerts */
        .alert { 
            padding: 16px 20px; 
            border-radius: 8px; 
            margin-bottom: 24px; 
            display: flex; 
            align-items: center; 
            gap: 12px;
            max-width: 800px;
            width: 100%;
        }
        
        .alert.success { 
            background: rgba(16, 185, 129, 0.1); 
            color: #065F46; 
            border: 1px solid #10B981; 
        }
        
        .alert.error { 
            background: rgba(239, 68, 68, 0.1); 
            color: #B91C1C; 
            border: 1px solid #EF4444; 
        }

        /* Special Salary Field */
        .salary-field {
            background: var(--bg-body);
            padding: 20px;
            border-radius: 8px;
            border: 2px solid var(--primary);
            margin: 20px 0;
        }
        
        .salary-field label {
            font-size: 1.1rem;
            color: var(--primary);
        }
        
        .salary-field input {
            font-size: 1.3rem;
            font-weight: 700;
            padding: 14px;
            background: var(--bg-card);
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

        /* Smooth Transitions */
        * {
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }

        /* Responsive Design */
        @media (max-width: 1200px) {
            .form-grid {
                gap: 16px;
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
            
            .form-card {
                padding: 24px;
            }
            
            .form-grid { 
                grid-template-columns: 1fr; 
            }
            
            .form-group.full-width { 
                grid-column: span 1; 
            }
            
            .upload-area {
                flex-direction: column;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .form-card {
                padding: 20px;
            }
            
            .card-header {
                font-size: 1.1rem;
            }
            
            .page-header h1 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

    <!-- SIDEBAR (SAME AS OTHER FILES) -->
     <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        
        <!-- HEADER (SAME AS OTHER FILES) -->
        <header class="top-header">
    <div style="display: flex; align-items: center; gap: 16px;">
        <button class="toggle-btn" id="sidebarToggle">
            <i class="ph ph-list"></i>
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
            <span style="font-size: 0.9rem; color: var(--text-muted);">Add Employee</span>
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
                <h1>Add New Employee</h1>
                <p>Register a new staff member and set their initial salary.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert <?php echo $msgType; ?>">
                    <i class="ph <?php echo $msgType === 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>" style="font-size: 1.2rem;"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <div class="form-card">
                <div class="card-header">Employee Details</div>
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-grid">
                        
                        <div class="form-group full-width">
                            <label>Profile Photo</label>
                            <div class="upload-area">
                                <div class="preview-box">
                                    <img id="imagePreview" src="#" alt="Preview" style="display: none;">
                                    <i class="ph ph-camera" id="defaultIcon" style="font-size: 1.5rem;"></i>
                                </div>
                                <div class="upload-input-group">
                                    <input type="file" name="photo" id="photoInput" accept="image/*" onchange="previewImage(event)">
                                    <div class="file-info">
                                        Allowed: JPG, PNG, GIF, WEBP. Max size: 2MB.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Full Name <span>*</span></label>
                            <input type="text" name="full_name" placeholder="e.g. John Doe" required>
                        </div>

                        <div class="form-group">
                            <label>Email Address <span>*</span></label>
                            <input type="email" name="email" placeholder="john@company.com" required>
                        </div>

                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="phone" placeholder="+1 234 567 890">
                        </div>

                        <div class="form-group">
                            <label>Joining Date</label>
                            <input type="date" name="joining_date" value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="form-group">
                            <label>Department</label>
                            <select name="department">
                                <option value="">Select Department</option>
                                <option value="IT / Development">IT / Development</option>
                                <option value="Sales">Sales</option>
                                <option value="HR">HR</option>
                                <option value="Marketing">Marketing</option>
                                <option value="Operations">Operations</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Designation</label>
                            <input type="text" name="designation" placeholder="e.g. Senior Developer">
                        </div>

                        <div class="form-group full-width salary-field">
                            <label>Monthly Salary ($) <span>*</span></label>
                            <input type="number" step="0.01" name="salary" placeholder="0.00" required>
                        </div>

                        <div class="form-group full-width">
                            <label>Status</label>
                            <select name="status">
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                                <option value="On Leave">On Leave</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="ph ph-floppy-disk"></i> Save Employee Record
                    </button>
                </form>
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

        // --- Preview Image Script ---
        function previewImage(event) {
            const input = event.target;
            const preview = document.getElementById('imagePreview');
            const icon = document.getElementById('defaultIcon');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    icon.style.display = 'none';
                }
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
                icon.style.display = 'flex';
            }
        }

        // --- Set today's date as default for joining date ---
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.querySelector('input[name="joining_date"]');
            if (dateInput && !dateInput.value) {
                dateInput.value = today;
            }
        });
    </script>
</body>
</html>