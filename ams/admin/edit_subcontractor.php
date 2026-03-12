<?php
/**
 * edit_subcontractor.php
 * Edit Subcontractor Page – with Photo & NID
 */

session_start();

// --- 1. AUTHENTICATION ---
if (!isset($_SESSION['user_id'])) {
    // Demo Login for testing (remove in production)
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexander Pierce';
    $_SESSION['role'] = 'admin';
    $_SESSION['email'] = 'alex@example.com';
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

// --- 4. GET USER PHOTO ---
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

// --- 5. GET SUBCONTRACTOR ID ---
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: subcontractor_management.php?error=Invalid ID");
    exit;
}

// --- 6. FETCH EXISTING DATA ---
$stmt = $pdo->prepare("SELECT * FROM subcontractors WHERE id = ?");
$stmt->execute([$id]);
$subcontractor = $stmt->fetch();

if (!$subcontractor) {
    header("Location: subcontractor_management.php?error=Subcontractor not found");
    exit;
}

// --- 7. HANDLE FORM SUBMISSION ---
$message = '';
$msg_type = '';
$form_data = $subcontractor; // start with existing data

// Upload directory
$upload_dir = __DIR__ . '/uploads/subcontractors/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $required = ['company_name', 'contact_person', 'email', 'phone'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception(ucfirst(str_replace('_', ' ', $field)) . " is required.");
            }
        }
        
        // Validate email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        
        // Check if email already exists for another subcontractor
        $stmt = $pdo->prepare("SELECT id FROM subcontractors WHERE email = ? AND id != ?");
        $stmt->execute([$_POST['email'], $id]);
        if ($stmt->fetch()) {
            throw new Exception("Email already exists for another subcontractor.");
        }
        
        // Handle file upload
        $photo_path = $subcontractor['photo']; // keep old by default
        $remove_photo = isset($_POST['remove_photo']) && $_POST['remove_photo'] == '1';
        
        // If new file uploaded
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['photo'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 2 * 1024 * 1024; // 2 MB
            
            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $allowed_types)) {
                throw new Exception("Photo must be a valid image (JPEG, PNG, GIF, WEBP).");
            }
            
            // Validate file size
            if ($file['size'] > $max_size) {
                throw new Exception("Photo size must be less than 2MB.");
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_filename = uniqid('sub_', true) . '.' . $ext;
            $destination = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new Exception("Failed to upload photo.");
            }
            
            $photo_path = 'uploads/subcontractors/' . $new_filename;
            
            // Delete old photo if exists
            if (!empty($subcontractor['photo']) && file_exists(__DIR__ . '/' . $subcontractor['photo'])) {
                unlink(__DIR__ . '/' . $subcontractor['photo']);
            }
        } elseif ($remove_photo) {
            // Remove current photo if checkbox checked
            if (!empty($subcontractor['photo']) && file_exists(__DIR__ . '/' . $subcontractor['photo'])) {
                unlink(__DIR__ . '/' . $subcontractor['photo']);
            }
            $photo_path = null;
        } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Some upload error occurred
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE   => 'The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form.',
                UPLOAD_ERR_PARTIAL     => 'The file was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR  => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE  => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION   => 'A PHP extension stopped the file upload.'
            ];
            $error_code = $_FILES['photo']['error'];
            $error_message = $upload_errors[$error_code] ?? 'Unknown upload error.';
            throw new Exception("Photo upload failed: " . $error_message);
        }
        
        // Determine specialization (if "Other" is chosen, use the text input)
        $specialization = $_POST['specialization'] ?? '';
        if ($specialization === 'Other' && !empty($_POST['specialization_other'])) {
            $specialization = $_POST['specialization_other'];
        }
        
        // Update query
        $sql = "UPDATE subcontractors SET
                company_name = ?, contact_person = ?, email = ?, phone = ?,
                address = ?, specialization = ?, tax_id = ?, nid_number = ?,
                photo = ?, registration_date = ?, project_rate = ?, status = ?, notes = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['company_name'],
            $_POST['contact_person'],
            $_POST['email'],
            $_POST['phone'],
            $_POST['address'] ?? '',
            $specialization,
            $_POST['tax_id'] ?? '',
            $_POST['nid_number'] ?? '',
            $photo_path,
            !empty($_POST['registration_date']) ? $_POST['registration_date'] : date('Y-m-d'),
            $_POST['project_rate'] ?? 0,
            $_POST['status'] ?? 'Active',
            $_POST['notes'] ?? '',
            $id
        ]);
        
        $message = "Subcontractor updated successfully!";
        $msg_type = 'success';
        
        // Refresh data
        $stmt = $pdo->prepare("SELECT * FROM subcontractors WHERE id = ?");
        $stmt->execute([$id]);
        $subcontractor = $stmt->fetch();
        $form_data = $subcontractor;
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $msg_type = 'error';
        // Keep form data from POST
        $form_data = $_POST;
    }
}

$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subcontractor | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* --- Copy all CSS from add_subcontractor.php here --- */
        /* (Keep the same styles for consistency) */
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
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
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

        .sidebar-menu { padding: 20px 12px; overflow-y: auto; overflow-x: hidden; flex: 1; }
        .menu-item { margin-bottom: 4px; }
        .menu-link { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 8px; color: rgba(255,255,255,0.7); cursor: pointer; transition: all 0.2s ease; font-size: 0.95rem; }
        .menu-link:hover, .menu-link.active { background-color: rgba(255,255,255,0.1); color: #fff; transform: translateX(2px); }
        .link-content { display: flex; align-items: center; gap: 12px; }
        .menu-icon { font-size: 1.2rem; min-width: 24px; text-align: center; transition: transform 0.2s ease; }
        .arrow-icon { transition: transform 0.3s ease; font-size: 0.8rem; opacity: 0.7; }

        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; padding-left: 12px; }
        .menu-item.open > .submenu { max-height: 500px; }
        .menu-item.open > .menu-link .arrow-icon { transform: rotate(180deg); }
        .menu-item.open > .menu-link { color: #fff; }
        .submenu-link { display: block; padding: 10px 16px 10px 42px; color: rgba(255,255,255,0.5); font-size: 0.9rem; border-radius: 8px; transition: all 0.2s ease; }
        .submenu-link:hover { color: #fff; background: rgba(255,255,255,0.05); transform: translateX(2px); }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .toggle-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; transition: all 0.2s ease; padding: 8px; border-radius: 8px; }
        .toggle-btn:hover { color: var(--primary); background: var(--bg-body); transform: translateY(-1px); }

        .header-right { display: flex; align-items: center; gap: 24px; }
        .profile-container { position: relative; }
        .profile-menu { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 8px 12px; border-radius: 12px; transition: all 0.2s ease; }
        .profile-menu:hover { background-color: var(--bg-body); }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-name { font-size: 0.9rem; font-weight: 600; display: block; }
        .profile-role { font-size: 0.75rem; color: var(--text-muted); }
        .profile-img { width: 42px; height: 42px; border-radius: 12px; object-fit: cover; border: 2px solid var(--border); transition: all 0.2s ease; }
        .profile-placeholder { width: 42px; height: 42px; border-radius: 12px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.1rem; }

        .dropdown-menu { position: absolute; top: calc(100% + 8px); right: 0; width: 220px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); padding: 8px; z-index: 1000; display: none; flex-direction: column; gap: 4px; animation: fadeIn 0.2s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .dropdown-menu.show { display: flex; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; font-size: 0.9rem; color: var(--text-main); border-radius: 8px; transition: all 0.2s ease; }
        .dropdown-item:hover { background-color: var(--bg-body); color: var(--primary); transform: translateX(2px); }
        .dropdown-item.danger:hover { background-color: rgba(239,68,68,0.1); color: #ef4444; }

        .scrollable { flex: 1; overflow-y: auto; padding: 32px; scroll-behavior: smooth; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
        .page-title { font-size: 1.6rem; font-weight: 700; margin-bottom: 4px; }
        .page-subtitle { color: var(--text-muted); font-size: 0.9rem; line-height: 1.4; }

        .alert { padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; font-size: 0.95rem; display: flex; align-items: center; gap: 12px; animation: slideIn 0.3s ease; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .alert-success { background: rgba(16,185,129,0.12); color: var(--color-success); border: 1px solid rgba(16,185,129,0.2); }
        .alert-error { background: rgba(239,68,68,0.12); color: var(--color-danger); border: 1px solid rgba(239,68,68,0.2); }
        .alert i { font-size: 1.2rem; }

        .form-container { background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; margin-bottom: 24px; }
        .form-header { padding: 24px 32px; border-bottom: 1px solid var(--border); background: linear-gradient(90deg, rgba(79,70,229,0.05) 0%, rgba(79,70,229,0.02) 100%); }
        .form-title { font-size: 1.2rem; font-weight: 700; color: var(--text-main); display: flex; align-items: center; gap: 12px; }
        .form-title i { color: var(--primary); }
        .form-body { padding: 32px; }
        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 24px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: span 2; }
        label { font-size: 0.9rem; font-weight: 600; color: var(--text-main); }
        label span.required { color: var(--color-danger); }
        input, select, textarea { padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); outline: none; transition: all 0.2s ease; font-size: 0.95rem; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        textarea { min-height: 120px; resize: vertical; }

        input[type="file"] { padding: 8px; }
        input[type="file"]::file-selector-button { padding: 8px 16px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-card); color: var(--text-main); cursor: pointer; margin-right: 12px; transition: all 0.2s ease; }
        input[type="file"]::file-selector-button:hover { background: var(--primary); color: white; border-color: var(--primary); }

        .photo-preview { margin-top: 8px; display: flex; align-items: center; gap: 12px; }
        .photo-preview img { width: 60px; height: 60px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border); }
        .photo-preview .remove-checkbox { display: flex; align-items: center; gap: 4px; font-size: 0.9rem; }

        .form-actions { display: flex; gap: 12px; margin-top: 32px; padding-top: 24px; border-top: 1px solid var(--border); }
        .btn { padding: 12px 24px; border-radius: 8px; font-size: 0.95rem; font-weight: 600; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; }
        .btn-primary { background: var(--primary); color: white; border: 1px solid transparent; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79,70,229,0.2); }
        .btn-secondary { background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--bg-body); transform: translateY(-1px); }
        .btn-success { background: var(--color-success); color: white; border: 1px solid transparent; }
        .btn-success:hover { background: #0da271; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,185,129,0.2); }

        .form-tips { background: var(--bg-body); padding: 20px; border-radius: 8px; margin-top: 24px; border-left: 4px solid var(--primary); }
        .form-tips h4 { font-size: 0.95rem; margin-bottom: 8px; color: var(--text-main); display: flex; align-items: center; gap: 8px; }
        .form-tips ul { padding-left: 20px; }
        .form-tips li { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 4px; }

        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 45; display: none; animation: fadeIn 0.3s ease; }
        #themeToggle { background: var(--bg-card); border: 1px solid var(--border); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; }
        #themeToggle:hover { transform: rotate(15deg); border-color: var(--primary); color: var(--primary); }
        .scrollable::-webkit-scrollbar { width: 8px; }
        .scrollable::-webkit-scrollbar-track { background: transparent; }
        .scrollable::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .scrollable::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        @media (max-width: 1200px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
        }
        @media (max-width: 768px) {
            .top-header { padding: 0 20px; }
            .scrollable { padding: 20px; }
            .sidebar { position: fixed; left: -280px; height: 100%; top: 0; }
            body.mobile-open .sidebar { transform: translateX(280px); box-shadow: 0 0 40px rgba(0,0,0,0.3); }
            body.mobile-open .overlay { display: block; }
            .logo-text, .link-text, .arrow-icon { display: inline !important; opacity: 1 !important; }
            .sidebar-header { justify-content: flex-start !important; padding: 0 24px !important; }
            .profile-info { display: none; }
            .form-header, .form-body { padding: 20px; }
            .form-actions { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
        @media (max-width: 480px) {
            .page-header { flex-direction: column; align-items: flex-start; }
            .form-body { padding: 16px; }
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
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Edit Subcontractor</span>
                </div>
            </div>

            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme"><i class="ph ph-moon" id="themeIcon"></i></button>
                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($_SESSION['username']); ?></span>
                            <span class="profile-role"><?php echo ucfirst(e($_SESSION['role'])); ?></span>
                        </div>
                        <?php if (!empty($userPhoto)): ?>
                            <img src="<?php echo e($userPhoto); ?>" alt="Profile" class="profile-img">
                        <?php else: ?>
                            <div class="profile-placeholder"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="admin_dashboard.php" class="dropdown-item"><i class="ph ph-house"></i> Dashboard</a>
                        <a href="profile_settings.php" class="dropdown-item"><i class="ph ph-user-gear"></i> Profile Settings</a>
                        <div style="border-top:1px solid var(--border); margin:4px 0;"></div>
                        <a href="?action=logout" class="dropdown-item danger"><i class="ph ph-sign-out"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <div class="scrollable">
            <div class="page-header">
                <div>
                    <h1 class="page-title">Edit Subcontractor</h1>
                    <p class="page-subtitle">
                        Update the information for <?php echo e($subcontractor['company_name']); ?>.
                    </p>
                </div>
                <div>
                    <a href="subcontractor_management.php" class="btn btn-secondary">
                        <i class="ph ph-arrow-left"></i>
                        Back to List
                    </a>
                </div>
            </div>

            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type; ?>">
                    <i class="ph <?php echo $msg_type == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Edit Form -->
            <div class="form-container">
                <div class="form-header">
                    <h2 class="form-title">
                        <i class="ph ph-user-pencil"></i>
                        Edit Subcontractor (ID: <?php echo $id; ?>)
                    </h2>
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data" class="form-body">
                    <div class="form-grid">
                        <!-- Company Information -->
                        <div class="form-group">
                            <label>Company Name <span class="required">*</span></label>
                            <input type="text" name="company_name" required 
                                   value="<?php echo e($form_data['company_name']); ?>"
                                   placeholder="Enter company name">
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Person <span class="required">*</span></label>
                            <input type="text" name="contact_person" required
                                   value="<?php echo e($form_data['contact_person']); ?>"
                                   placeholder="Full name of contact person">
                        </div>
                        
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="email" required
                                   value="<?php echo e($form_data['email']); ?>"
                                   placeholder="email@company.com">
                        </div>
                        
                        <div class="form-group">
                            <label>Phone <span class="required">*</span></label>
                            <input type="text" name="phone" required
                                   value="<?php echo e($form_data['phone']); ?>"
                                   placeholder="+1 (555) 123-4567">
                        </div>
                        
                        <div class="form-group">
                            <label>Tax ID / Business Number</label>
                            <input type="text" name="tax_id"
                                   value="<?php echo e($form_data['tax_id']); ?>"
                                   placeholder="EIN, GST, VAT, etc.">
                        </div>

                        <!-- NID Card Number -->
                        <div class="form-group">
                            <label>NID Card Number</label>
                            <input type="text" name="nid_number"
                                   value="<?php echo e($form_data['nid_number']); ?>"
                                   placeholder="National ID / Passport">
                        </div>

                        <!-- Photo Upload -->
                        <div class="form-group">
                            <label>Photo (max 2MB, JPEG/PNG/GIF/WEBP)</label>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp" id="photoInput">
                            <?php if (!empty($subcontractor['photo'])): ?>
                                <?php 
                                    $photo_path = $subcontractor['photo'];
                                    $full_photo_path = __DIR__ . '/' . $photo_path;
                                    $photo_exists = file_exists($full_photo_path);
                                ?>
                                <div class="photo-preview">
                                    <?php if ($photo_exists): ?>
                                        <img src="<?php echo e($photo_path); ?>" alt="Current photo">
                                    <?php else: ?>
                                        <div style="color: var(--color-danger);">Current photo file missing (path: <?php echo e($photo_path); ?>)</div>
                                    <?php endif; ?>
                                    <label class="remove-checkbox">
                                        <input type="checkbox" name="remove_photo" value="1"> Remove current photo
                                    </label>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Specialization (wig store options) -->
                        <div class="form-group">
                            <label>Specialization</label>
                            <select name="specialization" id="specializationSelect">
                                <option value="">Select Specialization</option>
                                <option value="Lace Front Wigs" <?php echo ($form_data['specialization'] == 'Lace Front Wigs') ? 'selected' : ''; ?>>Lace Front Wigs</option>
                                <option value="Full Lace Wigs" <?php echo ($form_data['specialization'] == 'Full Lace Wigs') ? 'selected' : ''; ?>>Full Lace Wigs</option>
                                <option value="Synthetic Wigs" <?php echo ($form_data['specialization'] == 'Synthetic Wigs') ? 'selected' : ''; ?>>Synthetic Wigs</option>
                                <option value="Human Hair Wigs" <?php echo ($form_data['specialization'] == 'Human Hair Wigs') ? 'selected' : ''; ?>>Human Hair Wigs</option>
                                <option value="Wig Customization" <?php echo ($form_data['specialization'] == 'Wig Customization') ? 'selected' : ''; ?>>Wig Customization</option>
                                <option value="Wig Styling" <?php echo ($form_data['specialization'] == 'Wig Styling') ? 'selected' : ''; ?>>Wig Styling</option>
                                <option value="Wig Repair" <?php echo ($form_data['specialization'] == 'Wig Repair') ? 'selected' : ''; ?>>Wig Repair</option>
                                <option value="Wig Cleaning" <?php echo ($form_data['specialization'] == 'Wig Cleaning') ? 'selected' : ''; ?>>Wig Cleaning</option>
                                <option value="Wig Manufacturing" <?php echo ($form_data['specialization'] == 'Wig Manufacturing') ? 'selected' : ''; ?>>Wig Manufacturing</option>
                                <option value="Other" <?php echo (!in_array($form_data['specialization'], ['Lace Front Wigs','Full Lace Wigs','Synthetic Wigs','Human Hair Wigs','Wig Customization','Wig Styling','Wig Repair','Wig Cleaning','Wig Manufacturing']) && !empty($form_data['specialization'])) ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <!-- Other specialization field (hidden unless "Other" selected) -->
                        <div class="form-group" id="otherSpecializationGroup" style="display: none;">
                            <label>Specify Specialization</label>
                            <input type="text" name="specialization_other" id="specializationOther"
                                   value="<?php 
                                       $spec = $form_data['specialization'] ?? '';
                                       if (!in_array($spec, ['Lace Front Wigs','Full Lace Wigs','Synthetic Wigs','Human Hair Wigs','Wig Customization','Wig Styling','Wig Repair','Wig Cleaning','Wig Manufacturing'])) {
                                           echo e($spec);
                                       }
                                   ?>"
                                   placeholder="Enter specialization">
                        </div>
                        
                        <div class="form-group">
                            <label>Project Rate ($)</label>
                            <input type="number" name="project_rate" step="0.01" min="0"
                                   value="<?php echo e($form_data['project_rate']); ?>"
                                   placeholder="0.00">
                        </div>
                        
                        <div class="form-group">
                            <label>Registration Date</label>
                            <input type="date" name="registration_date" 
                                   value="<?php echo e($form_data['registration_date']); ?>"
                                   id="regDate">
                        </div>
                        
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="Active" <?php echo ($form_data['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?php echo ($form_data['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                <option value="Pending" <?php echo ($form_data['status'] == 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        
                        <!-- Address -->
                        <div class="form-group full-width">
                            <label>Address</label>
                            <textarea name="address" rows="2" placeholder="Street address, City, State, ZIP"><?php echo e($form_data['address']); ?></textarea>
                        </div>
                        
                        <!-- Notes -->
                        <div class="form-group full-width">
                            <label>Additional Notes</label>
                            <textarea name="notes" rows="3" placeholder="Any additional information about this subcontractor..."><?php echo e($form_data['notes']); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="ph ph-check"></i>
                            Update Subcontractor
                        </button>
                        <a href="subcontractor_management.php" class="btn btn-secondary">
                            <i class="ph ph-x"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Form Tips -->
            <div class="form-tips">
                <h4><i class="ph ph-lightbulb"></i> Quick Tips</h4>
                <ul>
                    <li>Fill in all required fields marked with asterisk (*).</li>
                    <li>Upload a new photo only if you want to replace the existing one.</li>
                    <li>Check "Remove current photo" to delete the photo without uploading a new one.</li>
                    <li>Project rate is the standard rate charged by this subcontractor.</li>
                    <li>Registration date is when the subcontractor registered with your company.</li>
                    <li>Add notes for any special instructions or important details.</li>
                </ul>
            </div>
        </div>
    </main>

    <script>
        // --- Sidebar Toggle (same as add page) ---
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

        // --- Accordion Logic ---
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

        // --- Profile Dropdown ---
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

        // --- Dark Mode ---
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

        // --- Specialization "Other" handling ---
        const specSelect = document.getElementById('specializationSelect');
        const otherGroup = document.getElementById('otherSpecializationGroup');
        const otherInput = document.getElementById('specializationOther');

        function toggleOtherField() {
            if (specSelect.value === 'Other') {
                otherGroup.style.display = 'block';
                otherInput.required = true;
            } else {
                otherGroup.style.display = 'none';
                otherInput.required = false;
                // If we switch away from Other, clear the other input?
                // Not necessary, but could be nice.
            }
        }
        specSelect.addEventListener('change', toggleOtherField);
        toggleOtherField(); // run on page load

        // --- Phone formatting (optional, same as add page) ---
        const phoneInput = document.querySelector('input[name="phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                if (value.length > 0) {
                    if (value.length <= 3) {
                        value = '(' + value;
                    } else if (value.length <= 6) {
                        value = '(' + value.substring(0, 3) + ') ' + value.substring(3);
                    } else {
                        value = '(' + value.substring(0, 3) + ') ' + value.substring(3, 6) + '-' + value.substring(6, 10);
                    }
                }
                e.target.value = value;
            });
        }

        // --- Flatpickr for date inputs ---
        if (typeof flatpickr !== 'undefined') {
            flatpickr("#regDate", { dateFormat: "Y-m-d", allowInput: true });
        }

        // --- Form validation (same as add page) ---
        document.querySelector('form').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (field.type !== 'file' && !field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'var(--color-danger)';
                    if (!field.nextElementSibling || !field.nextElementSibling.classList.contains('error-message')) {
                        const error = document.createElement('div');
                        error.className = 'error-message';
                        error.style.color = 'var(--color-danger)';
                        error.style.fontSize = '0.8rem';
                        error.style.marginTop = '4px';
                        error.textContent = 'This field is required';
                        field.parentNode.appendChild(error);
                    }
                } else {
                    field.style.borderColor = '';
                    const errorMsg = field.parentNode.querySelector('.error-message');
                    if (errorMsg) errorMsg.remove();
                    
                    if (field.type === 'email' && field.value) {
                        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(field.value)) {
                            isValid = false;
                            field.style.borderColor = 'var(--color-danger)';
                            const error = document.createElement('div');
                            error.className = 'error-message';
                            error.style.color = 'var(--color-danger)';
                            error.style.fontSize = '0.8rem';
                            error.style.marginTop = '4px';
                            error.textContent = 'Please enter a valid email address';
                            field.parentNode.appendChild(error);
                        }
                    }
                }
            });
            if (!isValid) {
                e.preventDefault();
                const firstError = this.querySelector('[style*="border-color: var(--color-danger)"]');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
            }
        });
    </script>
</body>
</html>