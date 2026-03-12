<?php
/**
 * edit_employee.php
 * NexusAdmin V2 - Edit Employee
 * Feature: Displays Exiting Photo on Load + Previews New Photo on Change
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

// --- 3. FETCH EXISTING DATA ---
if (!isset($_GET['id'])) {
    header("Location: employee_list.php");
    exit;
}
$id = $_GET['id'];
$employee = null;

try {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
    if (!$employee) die("Employee not found.");
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

// --- 4. PREPARE EXISTING PHOTO URL ---
// This logic runs BEFORE the page loads to decide what image to show
$displayAvatar = "https://ui-avatars.com/api/?name=" . urlencode($employee['full_name']) . "&background=random&color=fff"; // Default

if (!empty($employee['photo'])) {
    // Check if the file actually exists in the uploads folder
    if (file_exists('uploads/' . $employee['photo'])) {
        $displayAvatar = 'uploads/' . $employee['photo'];
    } 
    // Or if it's an external link (like ui-avatars saved in DB)
    elseif (filter_var($employee['photo'], FILTER_VALIDATE_URL)) {
        $displayAvatar = $employee['photo'];
    }
}

// --- 5. HANDLE FORM SUBMISSION (UPDATE) ---
$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name   = trim($_POST['full_name']);
    $email       = trim($_POST['email']);
    $phone       = trim($_POST['phone']);
    $department  = $_POST['department'];
    $designation = $_POST['designation'];
    $salary      = $_POST['salary'];
    $status      = $_POST['status'];
    $joining_date= $_POST['joining_date'];
    
    // Default to keeping the old photo
    $photoVal = $employee['photo']; 

    // Check if a NEW file was uploaded
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['photo']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            if (!is_dir('uploads')) { mkdir('uploads', 0777, true); }
            
            // Unique Name: timestamp_random.jpg
            $newName = time() . '_' . rand(1000,9999) . '.' . $ext;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], 'uploads/' . $newName)) {
                $photoVal = $newName; // Set new photo for DB
            }
        }
    }

    try {
        $sql = "UPDATE employees SET 
                full_name=?, email=?, phone=?, department=?, designation=?, 
                salary=?, status=?, joining_date=?, photo=? 
                WHERE id=?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $full_name, $email, $phone, $department, $designation, 
            $salary, $status, $joining_date, $photoVal, $id
        ]);

        $msg = "Employee updated successfully!";
        $msgType = "success";
        
        // Refresh data so the form updates immediately
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$id]);
        $employee = $stmt->fetch();
        
        // Refresh the display variable with new photo
        if (!empty($employee['photo']) && file_exists('uploads/' . $employee['photo'])) {
            $displayAvatar = 'uploads/' . $employee['photo'];
        }

    } catch (PDOException $e) {
        $msg = "Error: " . $e->getMessage();
        $msgType = "error";
    }
}

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee | NexusAdmin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- GLOBAL STYLES --- */
        :root {
            --primary: #4F46E5; --primary-hover: #4338ca;
            --bg-body: #F3F4F6; --bg-card: #ffffff;
            --text-main: #111827; --text-muted: #6B7280;
            --border: #E5E7EB; --error: #EF4444;
            --sidebar-width: 280px; --sidebar-bg: #111827; --sidebar-text: #E5E7EB;
            --header-height: 64px; --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1); --radius: 0.75rem;
        }
        [data-theme="dark"] {
            --bg-body: #0f172a; --bg-card: #1e293b;
            --text-main: #f8fafc; --text-muted: #94a3b8;
            --border: #334155; --sidebar-bg: #020617; --primary: #6366f1;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; }
        a { text-decoration: none; color: inherit; } ul { list-style: none; } button { font-family: inherit; }

        /* Sidebar */
        body.sidebar-collapsed { --sidebar-width: 80px; }
        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-bg); color: var(--sidebar-text); display: flex; flex-direction: column; transition: width var(--transition); z-index: 50; flex-shrink: 0; white-space: nowrap; }
        .sidebar-header { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 700; font-size: 1.25rem; color: #fff; gap: 10px; overflow: hidden; }
        body.sidebar-collapsed .logo-text, body.sidebar-collapsed .link-text, body.sidebar-collapsed .arrow-icon { display: none; }
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
        .menu-item.open > .menu-link { color: #fff; }
        .submenu-link { display: block; padding: 10px 16px 10px 42px; color: rgba(255,255,255,0.5); font-size: 0.9rem; border-radius: 8px; }
        .submenu-link:hover, .submenu-link.active { color: #fff; background: rgba(255,255,255,0.05); }

        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; flex-shrink: 0; }
        .toggle-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; }
        .header-right { display: flex; align-items: center; gap: 24px; }
        .profile-container { position: relative; }
        .profile-menu { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 4px 8px; border-radius: 8px; transition: background 0.2s; }
        .profile-menu:hover { background-color: var(--bg-body); }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-name { font-size: 0.9rem; font-weight: 600; display: block; }
        .profile-role { font-size: 0.75rem; color: var(--text-muted); }
        .profile-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border); }
        .dropdown-menu { position: absolute; top: 120%; right: 0; width: 200px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); padding: 8px; z-index: 100; display: none; flex-direction: column; gap: 4px; }
        .dropdown-menu.show { display: flex; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; font-size: 0.9rem; color: var(--text-main); border-radius: 6px; transition: background 0.2s; }
        .dropdown-item:hover { background-color: var(--bg-body); color: var(--primary); }
        .dropdown-item.danger:hover { background-color: #fef2f2; color: #ef4444; }
        .scrollable { flex: 1; overflow-y: auto; padding: 32px; }

        /* Page & Form Styles */
        .page-header-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-header-title h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 5px; }
        .back-btn { display: flex; align-items: center; gap: 8px; font-weight: 500; color: var(--text-muted); transition: 0.2s; }
        .back-btn:hover { color: var(--primary); }

        .form-card { background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border); padding: 32px; max-width: 900px; margin: 0 auto; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        
        /* UPLOADER STYLES */
        .photo-upload-container { display: flex; align-items: center; gap: 24px; padding-bottom: 24px; margin-bottom: 24px; border-bottom: 1px dashed var(--border); }
        
        .current-photo-box { 
            width: 100px; height: 100px; 
            border-radius: 50%; 
            border: 3px solid var(--bg-body); 
            box-shadow: 0 0 0 1px var(--border); 
            overflow: hidden; 
            position: relative; 
            background: #eee;
            flex-shrink: 0;
        }
        .current-photo-box img { width: 100%; height: 100%; object-fit: cover; }
        
        .upload-controls h4 { font-size: 1rem; margin-bottom: 4px; font-weight: 600; }
        .upload-btn { 
            display: inline-flex; align-items: center; gap: 8px; 
            padding: 8px 16px; 
            background: var(--bg-body); 
            border: 1px solid var(--border); 
            border-radius: 6px; 
            cursor: pointer; 
            font-size: 0.85rem; font-weight: 600; 
            transition: 0.2s; margin-top: 10px; 
        }
        .upload-btn:hover { background: var(--border); border-color: var(--text-muted); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .full-width { grid-column: span 2; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-label { font-size: 0.9rem; font-weight: 500; color: var(--text-main); }
        .form-input, .form-select { padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; background: var(--bg-body); color: var(--text-main); font-size: 0.95rem; outline: none; transition: border 0.2s; }
        .form-input:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }

        .form-actions { margin-top: 32px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid var(--border); padding-top: 24px; }
        .btn-submit { background: var(--primary); color: white; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .btn-submit:hover { background: var(--primary-hover); }
        .btn-cancel { background: white; border: 1px solid var(--border); color: var(--text-main); padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }

        .alert { padding: 14px 16px; border-radius: 8px; margin-bottom: 24px; font-size: 0.95rem; display: flex; align-items: center; gap: 10px; }
        .alert.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .alert.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 40; display: none; }
        @media (max-width: 768px) {
            body.sidebar-collapsed { --sidebar-width: 280px; }
            .sidebar { position: fixed; left: -280px; height: 100%; }
            body.mobile-open .sidebar { transform: translateX(280px); }
            body.mobile-open .overlay { display: block; }
            .form-grid { grid-template-columns: 1fr; }
            .full-width { grid-column: span 1; }
            .photo-upload-container { flex-direction: column; text-align: center; }
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
                <button id="themeToggle" style="background:none; border:none; cursor:pointer; font-size:1.2rem; color:var(--text-muted);"><i class="ph ph-moon" id="themeIcon"></i></button>
                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($_SESSION['username']); ?></span>
                            <span class="profile-role"><?php echo ucfirst(e($_SESSION['role'])); ?></span>
                        </div>
                        <img src="<?php echo e($_SESSION['avatar']); ?>" alt="Profile" class="profile-img">
                    </div>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="profile_settings.php" class="dropdown-item"><i class="ph ph-user-gear" style="font-size: 1.1rem;"></i> Profile Settings</a>
                        <div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>
                        <a href="?action=logout" class="dropdown-item danger"><i class="ph ph-sign-out" style="font-size: 1.1rem;"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="scrollable">
            <div class="page-header-title">
                <div>
                    <h1>Edit Employee</h1>
                    <p style="color:var(--text-muted)">Update details for <span style="font-weight:600; color:var(--text-main);"><?php echo e($employee['full_name']); ?></span></p>
                </div>
                <a href="employee_list.php" class="back-btn"><i class="ph ph-arrow-left"></i> Back to List</a>
            </div>

            <?php if ($msg): ?>
                <div class="alert <?php echo $msgType; ?>">
                    <i class="ph <?php echo $msgType == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>" style="font-size:1.2rem;"></i>
                    <?php echo $msg; ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="form-card">
                
                <div class="photo-upload-container">
                    <div class="current-photo-box">
                        <img src="<?php echo $displayAvatar; ?>" id="previewImg" alt="Current Employee Photo">
                    </div>
                    
                    <div class="upload-controls">
                        <h4>Profile Photo</h4>
                        <p style="color:var(--text-muted); font-size:0.8rem; margin-bottom: 5px;">
                            Currently showing existing photo.<br>
                            Click below to choose a new one.
                        </p>
                        <label class="upload-btn">
                            <i class="ph ph-camera"></i> Change Photo
                            <input type="file" name="photo" style="display:none;" accept="image/*" onchange="previewImage(this)">
                        </label>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-input" required value="<?php echo e($employee['full_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-input" required value="<?php echo e($employee['email']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone" class="form-input" value="<?php echo e($employee['phone']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-select">
                            <option value="">Select Department</option>
                            <?php 
                                $depts = ['Engineering', 'Marketing', 'Sales', 'HR', 'Finance', 'Support'];
                                foreach($depts as $d) {
                                    $sel = ($employee['department'] === $d) ? 'selected' : '';
                                    echo "<option value='$d' $sel>$d</option>";
                                }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Designation</label>
                        <input type="text" name="designation" class="form-input" value="<?php echo e($employee['designation']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Joining Date</label>
                        <input type="date" name="joining_date" class="form-input" value="<?php echo e($employee['joining_date']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Salary</label>
                        <input type="number" name="salary" class="form-input" value="<?php echo e($employee['salary']); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="Active" <?php echo $employee['status']=='Active'?'selected':''; ?>>Active</option>
                            <option value="Inactive" <?php echo $employee['status']=='Inactive'?'selected':''; ?>>Inactive</option>
                            <option value="On Leave" <?php echo $employee['status']=='On Leave'?'selected':''; ?>>On Leave</option>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <a href="employee_list.php" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-submit">Save Changes</button>
                </div>
            </form>

            <div style="margin-top: 40px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
                &copy; <?php echo date('Y'); ?> NexusAdmin System.
            </div>
        </div>
    </main>

    <script>
        // Sidebar & UI Logic
        const sidebarToggle = document.getElementById('sidebarToggle'); const overlay = document.getElementById('overlay'); const body = document.body;
        function handleSidebarToggle() { 
            if (window.innerWidth <= 768) { body.classList.toggle('mobile-open'); body.classList.remove('sidebar-collapsed'); } 
            else { body.classList.toggle('sidebar-collapsed'); if(body.classList.contains('sidebar-collapsed')) { document.querySelectorAll('.menu-item.open').forEach(item => item.classList.remove('open')); }}
        }
        sidebarToggle.addEventListener('click', handleSidebarToggle); 
        overlay.addEventListener('click', () => body.classList.remove('mobile-open'));

        const menuItems = document.querySelectorAll('.has-submenu'); 
        menuItems.forEach(item => { 
            const link = item.querySelector('.menu-link'); 
            link.addEventListener('click', (e) => { 
                if(body.classList.contains('sidebar-collapsed')) { body.classList.remove('sidebar-collapsed'); setTimeout(() => { item.classList.add('open'); }, 100); return; } 
                e.preventDefault(); const isOpen = item.classList.contains('open'); 
                menuItems.forEach(i => i.classList.remove('open')); if (!isOpen) item.classList.add('open'); 
            }); 
        });

        function toggleProfileMenu() { document.getElementById('profileDropdown').classList.toggle('show'); }
        window.addEventListener('click', function(e) { if (!document.getElementById('profileContainer').contains(e.target)) { document.getElementById('profileDropdown').classList.remove('show'); }});

        const themeBtn = document.getElementById('themeToggle'); const themeIcon = document.getElementById('themeIcon');
        if(localStorage.getItem('theme') === 'dark') { document.documentElement.setAttribute('data-theme', 'dark'); themeIcon.classList.replace('ph-moon', 'ph-sun'); }
        themeBtn.addEventListener('click', () => { const isDark = document.documentElement.getAttribute('data-theme') === 'dark'; if(isDark) { document.documentElement.removeAttribute('data-theme'); localStorage.setItem('theme', 'light'); themeIcon.classList.replace('ph-sun', 'ph-moon'); } else { document.documentElement.setAttribute('data-theme', 'dark'); localStorage.setItem('theme', 'dark'); themeIcon.classList.replace('ph-moon', 'ph-sun'); }});

        // --- PREVIEW IMAGE LOGIC ---
        // 1. User picks file. 2. FileReader reads it. 3. Replaces src of existing image.
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>