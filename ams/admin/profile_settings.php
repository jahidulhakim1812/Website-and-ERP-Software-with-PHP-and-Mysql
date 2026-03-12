<?php
/**
 * profile_settings.php
 * Professional User Profile Settings Page
 * Features: Update profile info, change password, update profile picture, manage preferences
 */

// --- 1. AUTHENTICATION & SECURITY ---
session_start();

// Security headers to prevent attacks
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// Session timeout - 30 minutes
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

// --- LOGOUT HANDLING ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Clear session data
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
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
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $ex) {
    error_log("Database Connection Failed: " . $ex->getMessage());
    die("Database Connection Failed. Please try again later.");
}

// Helper functions
function e($val) { 
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); 
}

function formatPhone($phone) {
    if (empty($phone)) return '';
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 10) {
        return preg_replace('/(\d{3})(\d{3})(\d{4})/', '($1) $2-$3', $phone);
    }
    return $phone;
}

function validateCSRF() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            die("CSRF token validation failed.");
        }
    }
    return true;
}

// --- 3. FETCH USER DATA ---
$user = [];
$user_id = $_SESSION['user_id'];

try {
    // Check what columns exist in the users table
    $checkColumns = $pdo->prepare("SHOW COLUMNS FROM users");
    $checkColumns->execute();
    $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
    
    // Build SELECT query - DON'T include password for security
    $selectFields = ['id', 'username', 'role', 'created_at', 'last_login', 'avatar'];
    
    // Add optional columns if they exist
    $optionalFields = ['name', 'email', 'phone', 'bio', 'notifications_enabled', 'two_factor_enabled', 'last_updated', 'status'];
    foreach ($optionalFields as $field) {
        if (in_array($field, $columns)) {
            $selectFields[] = $field;
        }
    }
    
    $sql = "SELECT " . implode(', ', $selectFields) . " FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        die("User not found!");
    }
    
    // Set default values for columns that might not exist
    $defaultValues = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'bio' => '',
        'notifications_enabled' => 1,
        'two_factor_enabled' => 0,
        'last_updated' => $user['created_at'] ?? date('Y-m-d H:i:s'),
        'status' => 'active'
    ];
    
    foreach ($defaultValues as $key => $value) {
        $user[$key] = $user[$key] ?? $value;
    }
    
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    die("Error fetching user data. Please try again.");
}

// --- 4. HANDLE FORM SUBMISSIONS ---
$message = '';
$msg_type = '';

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRF();
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $username = trim($_POST['username']);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        // Validate inputs
        $errors = [];
        
        if (empty($username)) {
            $errors[] = "Username is required.";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters long.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Username can only contain letters, numbers, and underscores.";
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }
        
        if (!empty($phone) && !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
            $errors[] = "Invalid phone number format.";
        }
        
        if (strlen($bio) > 500) {
            $errors[] = "Bio must be less than 500 characters.";
        }
        
        if (!empty($errors)) {
            throw new Exception(implode("<br>", $errors));
        }
        
        // Check if username already exists for another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
        if ($stmt->fetch()) {
            throw new Exception("Username already exists for another user.");
        }
        
        // Check if email already exists for another user
        if (!empty($email) && in_array('email', $columns)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                throw new Exception("Email already exists for another user.");
            }
        }
        
        // Clean phone number
        $phone_clean = preg_replace('/[^0-9]/', '', $phone);
        
        // Build SQL based on available columns
        $updateFields = ["username = ?"];
        $updateValues = [$username];
        
        if (in_array('name', $columns)) {
            $updateFields[] = "name = ?";
            $updateValues[] = $name;
        }
        
        if (in_array('email', $columns)) {
            $updateFields[] = "email = ?";
            $updateValues[] = $email;
        }
        
        if (in_array('phone', $columns)) {
            $updateFields[] = "phone = ?";
            $updateValues[] = $phone_clean;
        }
        
        if (in_array('bio', $columns)) {
            $updateFields[] = "bio = ?";
            $updateValues[] = $bio;
        }
        
        // Add last_updated if column exists
        if (in_array('last_updated', $columns)) {
            $updateFields[] = "last_updated = CURRENT_TIMESTAMP";
        }
        
        $updateValues[] = $user_id;
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Update session
        $_SESSION['username'] = $username;
        if (!empty($name)) $_SESSION['name'] = $name;
        if (!empty($email)) $_SESSION['email'] = $email;
        
        // Regenerate session ID to prevent session fixation
        session_regenerate_id(true);
        
        $message = "Profile updated successfully!";
        $msg_type = 'success';
        
        // Refresh user data
        $sql = "SELECT " . implode(', ', $selectFields) . " FROM users WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        foreach ($defaultValues as $key => $value) {
            $user[$key] = $user[$key] ?? $value;
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Handle Password Change - PLAIN TEXT VERSION (as requested)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate inputs
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All password fields are required.");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match.");
        }
        
        if (strlen($new_password) < 8) {
            throw new Exception("New password must be at least 8 characters long.");
        }
        
        // Check password strength
        if (!preg_match('/[A-Z]/', $new_password)) {
            throw new Exception("Password must contain at least one uppercase letter.");
        }
        
        if (!preg_match('/[a-z]/', $new_password)) {
            throw new Exception("Password must contain at least one lowercase letter.");
        }
        
        if (!preg_match('/[0-9]/', $new_password)) {
            throw new Exception("Password must contain at least one number.");
        }
        
        // Get current password from database
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $db_user = $stmt->fetch();
        
        if (!$db_user) {
            throw new Exception("User not found.");
        }
        
        $stored_password = $db_user['password'];
        
        // Check current password - simple direct comparison for plain text
        if ($current_password !== $stored_password) {
            throw new Exception("Current password is incorrect.");
        }
        
        // Store the new password in PLAIN TEXT (as requested)
        $plain_password = $new_password;
        
        // Update the password in database as plain text
        $sql = "UPDATE users SET password = ?";
        $params = [$plain_password];
        
        if (in_array('last_updated', $columns)) {
            $sql .= ", last_updated = CURRENT_TIMESTAMP";
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $user_id;
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);
        
        if (!$result) {
            throw new Exception("Failed to update password in database.");
        }
        
        // Verify the update worked by fetching and checking
        $verifyStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $verifyStmt->execute([$user_id]);
        $updated = $verifyStmt->fetch();
        
        if (!$updated || $updated['password'] !== $plain_password) {
            throw new Exception("Password update verification failed.");
        }
        
        // Log successful password change
        error_log("Password changed for user $user_id (STORED IN PLAIN TEXT - SECURITY RISK)");
        
        // Regenerate session ID after password change
        session_regenerate_id(true);
        
        $message = "Password changed successfully! (Note: Password is stored in plain text)";
        $msg_type = 'success';
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $msg_type = 'error';
        
        // Log the error for debugging
        error_log("Password change error: " . $e->getMessage());
        error_log("User ID: " . $user_id);
    }
}

// Handle Profile Picture Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_photo') {
    try {
        if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception("Please select a valid image file.");
        }
        
        $file = $_FILES['profile_photo'];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $file_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.");
        }
        
        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            throw new Exception("File size too large. Maximum size is 5MB.");
        }
        
        // Create uploads directory if it doesn't exist
        $upload_dir = 'uploads/profile/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename with better security
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            throw new Exception("Invalid file extension.");
        }
        
        // Create a unique filename
        $filename = 'profile_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $file_ext;
        $filepath = $upload_dir . $filename;
        
        // Check if image is valid
        $image_info = getimagesize($file['tmp_name']);
        if (!$image_info) {
            throw new Exception("Invalid image file.");
        }
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Remove old avatar if exists
            if (!empty($user['avatar']) && file_exists($user['avatar']) && $user['avatar'] !== $filepath) {
                @unlink($user['avatar']);
            }
            
            // Build update query
            $updateFields = ["avatar = ?"];
            if (in_array('last_updated', $columns)) {
                $updateFields[] = "last_updated = CURRENT_TIMESTAMP";
            }
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$filepath, $user_id]);
            
            // Update session
            $_SESSION['avatar'] = $filepath;
            
            // Refresh user data
            $user['avatar'] = $filepath;
            
            $message = "Profile picture updated successfully!";
            $msg_type = 'success';
        } else {
            throw new Exception("Failed to upload image. Please try again.");
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Handle Profile Picture Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_photo') {
    try {
        // Remove photo file if exists
        if (!empty($user['avatar']) && file_exists($user['avatar'])) {
            @unlink($user['avatar']);
        }
        
        // Build update query
        $updateFields = ["avatar = NULL"];
        if (in_array('last_updated', $columns)) {
            $updateFields[] = "last_updated = CURRENT_TIMESTAMP";
        }
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$user_id]);
        
        // Update session
        unset($_SESSION['avatar']);
        
        // Refresh user data
        $user['avatar'] = null;
        
        $message = "Profile picture removed successfully!";
        $msg_type = 'success';
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Handle Preferences Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_preferences') {
    try {
        $notifications_enabled = isset($_POST['notifications_enabled']) ? 1 : 0;
        $two_factor_enabled = isset($_POST['two_factor_enabled']) ? 1 : 0;
        
        // Build SQL based on available columns
        $updateFields = [];
        $updateValues = [];
        
        if (in_array('notifications_enabled', $columns)) {
            $updateFields[] = "notifications_enabled = ?";
            $updateValues[] = $notifications_enabled;
        }
        
        if (in_array('two_factor_enabled', $columns)) {
            $updateFields[] = "two_factor_enabled = ?";
            $updateValues[] = $two_factor_enabled;
        }
        
        if (in_array('last_updated', $columns)) {
            $updateFields[] = "last_updated = CURRENT_TIMESTAMP";
        }
        
        if (empty($updateFields)) {
            throw new Exception("Preference columns not found in database.");
        }
        
        $updateValues[] = $user_id;
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);
        
        // Update user data
        $user['notifications_enabled'] = $notifications_enabled;
        $user['two_factor_enabled'] = $two_factor_enabled;
        
        $message = "Preferences updated successfully!";
        $msg_type = 'success';
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Helper function for initials
function getInitials($name, $username = '') {
    if (!empty($name)) {
        $initials = '';
        $words = explode(' ', $name);
        $count = 0;
        foreach ($words as $word) {
            if (!empty($word) && $count < 2) {
                $initials .= strtoupper($word[0]);
                $count++;
            }
        }
        return $initials;
    } elseif (!empty($username)) {
        return strtoupper(substr($username, 0, 2));
    }
    return 'U';
}

// Helper function to show relative time
function relativeTime($date) {
    if (empty($date) || $date === '0000-00-00 00:00:00') return 'Never';
    
    $time = strtotime($date);
    if ($time === false) return 'Invalid date';
    
    $time_diff = time() - $time;
    
    if ($time_diff < 60) {
        return 'Just now';
    } elseif ($time_diff < 3600) {
        $minutes = floor($time_diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($time_diff < 86400) {
        $hours = floor($time_diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($time_diff < 604800) {
        $days = floor($time_diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings | NexusAdmin</title>
    
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

        /* Settings Container */
        .settings-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 32px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Settings Sidebar */
        .settings-sidebar {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 24px;
            height: fit-content;
            position: sticky;
            top: 32px;
        }
        
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            border-radius: 8px;
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
            background: none;
            text-align: left;
        }
        
        .nav-item:hover {
            background: var(--bg-body);
            color: var(--text-main);
        }
        
        .nav-item.active {
            background: var(--primary);
            color: white;
        }
        
        .nav-item i {
            font-size: 1.1rem;
        }
        
        /* Settings Content */
        .settings-content {
            display: flex;
            flex-direction: column;
            gap: 32px;
        }
        
        /* Card Styles */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .card-header {
            padding: 24px 32px;
            border-bottom: 1px solid var(--border);
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
        
        /* Form Styles */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
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
            color: #EF4444;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="tel"],
        select,
        textarea {
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
        
        .form-hint {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        /* Profile Picture Section */
        .profile-picture-section {
            display: flex;
            align-items: center;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        .profile-preview {
            position: relative;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            object-fit: cover;
            border: 3px solid var(--border);
            box-shadow: var(--shadow);
        }
        
        .avatar-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 600;
            border: 3px solid var(--border);
        }
        
        .avatar-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
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
        
        .btn-outline {
            background: transparent;
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .btn-outline:hover {
            background: var(--bg-body);
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: #EF4444;
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-danger:hover {
            background: #DC2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }
        
        /* File Upload */
        .file-upload-box {
            border: 2px dashed var(--border);
            border-radius: 8px;
            padding: 24px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            background: var(--bg-body);
            margin-top: 16px;
        }
        
        .file-upload-box:hover {
            border-color: var(--primary);
            background: rgba(79, 70, 229, 0.05);
        }
        
        .file-input {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
        }
        
        .upload-content i {
            font-size: 2.5rem;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        
        #fileName {
            font-size: 0.9rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        
        /* Alerts */
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
            color: #10B981;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            color: #EF4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-top: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary);
            background: rgba(79, 70, 229, 0.1);
        }
        
        .stat-info h4 {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        
        .stat-info p {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }
        
        /* Switch Toggle */
        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--border);
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        input:checked + .slider {
            background-color: var(--primary);
        }
        
        input:checked + .slider:before {
            transform: translateX(24px);
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
            width: 42px;
            height: 42px;
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
        @media (max-width: 1200px) {
            .scrollable {
                padding: 24px;
            }
            
            .settings-container {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            
            .settings-sidebar {
                position: static;
            }
            
            .sidebar-nav {
                flex-direction: row;
                overflow-x: auto;
                padding-bottom: 8px;
            }
            
            .nav-item {
                white-space: nowrap;
            }
        }
        
        @media (max-width: 992px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
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
            
            .profile-picture-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-header {
                padding: 20px;
            }
            
            .card-body {
                padding: 20px;
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
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Profile Settings</span>
                </div>
            </div>

            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info">
                            <span class="profile-name"><?php echo e($user['username']); ?></span>
                            <span class="profile-role"><?php echo ucfirst(e($user['role'])); ?></span>
                        </div>
                        <?php if (!empty($user['avatar'])): ?>
                            <img src="<?php echo e($user['avatar']); ?>" alt="Profile" class="profile-img">
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <?php echo getInitials($user['name'] ?? '', $user['username']); ?>
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
            <div class="page-header">
                <div>
                    <h1 class="page-title">Profile Settings</h1>
                    <p class="page-subtitle">
                        Manage your account settings and preferences
                    </p>
                </div>
            </div>

            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type; ?>">
                    <i class="ph <?php echo $msg_type == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <div class="settings-container">
                <!-- Settings Sidebar -->
                <div class="settings-sidebar">
                    <div class="sidebar-nav">
                        <button class="nav-item active" data-tab="profile-tab">
                            <i class="ph ph-user"></i>
                            <span>Profile Information</span>
                        </button>
                        <button class="nav-item" data-tab="security-tab">
                            <i class="ph ph-lock-key"></i>
                            <span>Security</span>
                        </button>
                        <button class="nav-item" data-tab="photo-tab">
                            <i class="ph ph-image"></i>
                            <span>Profile Photo</span>
                        </button>
                        <?php if (in_array('notifications_enabled', $columns) || in_array('two_factor_enabled', $columns)): ?>
                        <button class="nav-item" data-tab="preferences-tab">
                            <i class="ph ph-gear"></i>
                            <span>Preferences</span>
                        </button>
                        <?php endif; ?>
                        <button class="nav-item" data-tab="account-tab">
                            <i class="ph ph-shield"></i>
                            <span>Account Settings</span>
                        </button>
                    </div>

                    <!-- User Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="ph ph-user"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Role</h4>
                                <p><?php echo ucfirst(e($user['role'])); ?></p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="ph ph-calendar-blank"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Member Since</h4>
                                <p><?php echo date('M Y', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="ph ph-shield-check"></i>
                            </div>
                            <div class="stat-info">
                                <h4>Status</h4>
                                <p>Active</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- Profile Information Tab -->
                    <div class="card tab-content active" id="profile-tab">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="ph ph-user"></i>
                                Profile Information
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="profile-picture-section">
                                <div class="profile-preview">
                                    <?php if (!empty($user['avatar'])): ?>
                                        <img src="<?php echo e($user['avatar']); ?>" alt="Profile" class="profile-avatar" id="avatarPreview">
                                        <div style="display: none;" id="avatarPlaceholder" class="avatar-placeholder">
                                            <?php echo getInitials($user['name'] ?? '', $user['username']); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar-placeholder" id="avatarPlaceholder">
                                            <?php echo getInitials($user['name'] ?? '', $user['username']); ?>
                                        </div>
                                        <img src="" alt="Profile" class="profile-avatar" id="avatarPreview" style="display: none;">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="avatar-actions">
                                    <button type="button" class="btn btn-primary" onclick="openPhotoTab()">
                                        <i class="ph ph-camera"></i> Change Photo
                                    </button>
                                    <button type="button" class="btn btn-outline" onclick="openPhotoTab()">
                                        <i class="ph ph-pencil"></i> Edit Profile
                                    </button>
                                </div>
                            </div>
                            
                            <form action="" method="POST" id="profileForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Username <span class="required">*</span></label>
                                        <input type="text" name="username" required 
                                               value="<?php echo e($user['username']); ?>"
                                               placeholder="Enter your username">
                                        <div class="form-hint">Your display name in the system</div>
                                    </div>
                                    
                                    <?php if (in_array('name', $columns)): ?>
                                    <div class="form-group">
                                        <label>Full Name</label>
                                        <input type="text" name="name" 
                                               value="<?php echo e($user['name']); ?>"
                                               placeholder="Enter your full name">
                                        <div class="form-hint">Your display name</div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array('email', $columns)): ?>
                                    <div class="form-group">
                                        <label>Email Address</label>
                                        <input type="email" name="email" 
                                               value="<?php echo e($user['email']); ?>"
                                               placeholder="you@example.com">
                                        <div class="form-hint">We'll never share your email</div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array('phone', $columns)): ?>
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="tel" name="phone" 
                                               value="<?php echo formatPhone($user['phone']); ?>"
                                               placeholder="(123) 456-7890">
                                        <div class="form-hint">Optional contact number</div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array('bio', $columns)): ?>
                                    <div class="form-group full-width">
                                        <label>Bio</label>
                                        <textarea name="bio" placeholder="Tell us a little about yourself..."><?php echo e($user['bio']); ?></textarea>
                                        <div class="form-hint">Brief description for your profile</div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="form-group">
                                        <label>User ID</label>
                                        <input type="text" value="#<?php echo e($user['id']); ?>" disabled>
                                        <div class="form-hint">Your unique identifier in the system</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>User Role</label>
                                        <input type="text" value="<?php echo ucfirst(e($user['role'])); ?>" disabled>
                                        <div class="form-hint">Role can only be changed by system administrator</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Account Created</label>
                                        <input type="text" 
                                               value="<?php echo date('F j, Y, g:i a', strtotime($user['created_at'])); ?>" 
                                               disabled>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Last Login</label>
                                        <input type="text" 
                                               value="<?php echo !empty($user['last_login']) && $user['last_login'] !== '0000-00-00 00:00:00' ? date('F j, Y, g:i a', strtotime($user['last_login'])) : 'Never'; ?>" 
                                               disabled>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 32px; display: flex; gap: 12px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ph ph-floppy-disk"></i> Save Changes
                                    </button>
                                    <button type="button" class="btn btn-outline" onclick="resetProfileForm()">
                                        <i class="ph ph-arrow-counter-clockwise"></i> Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Security Tab -->
                    <div class="card tab-content" id="security-tab">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="ph ph-lock-key"></i>
                                Security Settings
                            </h2>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" id="passwordForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label>Current Password <span class="required">*</span></label>
                                        <input type="password" name="current_password" required 
                                               placeholder="Enter your current password">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>New Password <span class="required">*</span></label>
                                        <input type="password" name="new_password" required 
                                               placeholder="Enter new password" id="newPassword">
                                        <div class="form-hint">Minimum 8 characters with uppercase, lowercase and numbers</div>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label>Confirm New Password <span class="required">*</span></label>
                                        <input type="password" name="confirm_password" required 
                                               placeholder="Confirm new password">
                                    </div>
                                </div>
                                
                                <div class="form-group full-width">
                                    <div style="background: rgba(16, 185, 129, 0.1); padding: 16px; border-radius: 8px; margin-top: 16px;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <i class="ph ph-info" style="color: #10B981;"></i>
                                            <span style="font-weight: 600; color: #10B981;">Password Requirements</span>
                                        </div>
                                        <ul style="font-size: 0.85rem; color: var(--text-muted); list-style: none; padding-left: 0;">
                                            <li style="margin-bottom: 4px;">✓ At least 8 characters long</li>
                                            <li style="margin-bottom: 4px;">✓ Include uppercase and lowercase letters</li>
                                            <li style="margin-bottom: 4px;">✓ Include at least one number</li>
                                            <li>✓ Include special character (optional but recommended)</li>
                                        </ul>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 32px; display: flex; gap: 12px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ph ph-lock-key"></i> Update Password
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Profile Photo Tab -->
                    <div class="card tab-content" id="photo-tab">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="ph ph-image"></i>
                                Profile Photo
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="profile-picture-section">
                                <div class="profile-preview">
                                    <?php if (!empty($user['avatar'])): ?>
                                        <img src="<?php echo e($user['avatar']); ?>" alt="Profile" class="profile-avatar" id="avatarPreview">
                                        <div style="display: none;" id="avatarPlaceholder" class="avatar-placeholder">
                                            <?php echo getInitials($user['name'] ?? '', $user['username']); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="avatar-placeholder" id="avatarPlaceholder">
                                            <?php echo getInitials($user['name'] ?? '', $user['username']); ?>
                                        </div>
                                        <img src="" alt="Profile" class="profile-avatar" id="avatarPreview" style="display: none;">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="avatar-actions">
                                    <button type="button" class="btn btn-primary" onclick="document.getElementById('photoInput').click()">
                                        <i class="ph ph-upload-simple"></i> Upload New Photo
                                    </button>
                                    <?php if (!empty($user['avatar'])): ?>
                                        <button type="button" class="btn btn-danger" onclick="removeProfilePhoto()">
                                            <i class="ph ph-trash"></i> Remove Photo
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <form action="" method="POST" enctype="multipart/form-data" id="photoForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="update_photo">
                                <input type="file" name="profile_photo" id="photoInput" class="file-input" accept="image/*" style="display: none;">
                                
                                <div class="file-upload-box" onclick="document.getElementById('photoInput').click()">
                                    <div class="upload-content">
                                        <i class="ph ph-cloud-arrow-up"></i>
                                        <p id="fileName">Click to upload or drag and drop</p>
                                        <p style="font-size: 0.8rem; color: var(--text-muted);">PNG, JPG, GIF, WEBP up to 5MB</p>
                                    </div>
                                </div>
                                
                                <div style="margin-top: 32px; display: flex; gap: 12px;">
                                    <button type="submit" class="btn btn-primary" id="savePhotoBtn" disabled>
                                        <i class="ph ph-floppy-disk"></i> Save Photo
                                    </button>
                                    <button type="button" class="btn btn-outline" onclick="cancelPhotoUpload()">
                                        <i class="ph ph-x"></i> Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Preferences Tab -->
                    <?php if (in_array('notifications_enabled', $columns) || in_array('two_factor_enabled', $columns)): ?>
                    <div class="card tab-content" id="preferences-tab">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="ph ph-gear"></i>
                                Preferences
                            </h2>
                        </div>
                        <div class="card-body">
                            <form action="" method="POST" id="preferencesForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="update_preferences">
                                
                                <?php if (in_array('notifications_enabled', $columns)): ?>
                                <div class="form-group full-width">
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border); margin-bottom: 16px;">
                                        <div>
                                            <div style="font-weight: 600; margin-bottom: 4px;">Email Notifications</div>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);">Receive email alerts for important updates</div>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="notifications_enabled" 
                                                   <?php echo ($user['notifications_enabled'] ?? 1) ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (in_array('two_factor_enabled', $columns)): ?>
                                <div class="form-group full-width">
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: var(--bg-body); border-radius: 8px; border: 1px solid var(--border); margin-bottom: 16px;">
                                        <div>
                                            <div style="font-weight: 600; margin-bottom: 4px;">Two-Factor Authentication</div>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);">Add an extra layer of security to your account</div>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" name="two_factor_enabled" 
                                                   <?php echo ($user['two_factor_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div style="margin-top: 32px; display: flex; gap: 12px;">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ph ph-floppy-disk"></i> Save Preferences
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Account Settings Tab -->
                    <div class="card tab-content" id="account-tab">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="ph ph-shield"></i>
                                Account Settings
                            </h2>
                        </div>
                        <div class="card-body">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label>Account Status</label>
                                    <input type="text" value="Active" disabled style="color: #10B981;">
                                    <div class="form-hint">Your current account status</div>
                                </div>
                                
                                <div class="form-group">
                                    <label>Last Updated</label>
                                    <input type="text" 
                                           value="<?php echo !empty($user['last_updated']) ? date('F j, Y g:i A', strtotime($user['last_updated'])) : date('F j, Y g:i A', strtotime($user['created_at'])); ?>" 
                                           disabled>
                                </div>
                                
                                <div class="form-group full-width">
                                    <label>Account Deletion</label>
                                    <div style="background: rgba(239, 68, 68, 0.1); padding: 20px; border-radius: 8px; border: 1px solid rgba(239, 68, 68, 0.2);">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                                            <i class="ph ph-warning" style="color: #EF4444;"></i>
                                            <span style="font-weight: 600; color: #EF4444;">Danger Zone</span>
                                        </div>
                                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 16px;">
                                            Once you delete your account, there is no going back. Please be certain.
                                        </p>
                                        <button type="button" class="btn btn-danger" onclick="confirmAccountDeletion()">
                                            <i class="ph ph-trash"></i> Delete Account
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Delete Account Modal -->
    <div class="modal" id="deleteAccountModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--bg-card); border-radius: 12px; padding: 24px; width: 90%; max-width: 400px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                <h3 style="font-size: 1.25rem; font-weight: 600;">Delete Account</h3>
                <button onclick="closeModal()" style="background: none; border: none; color: var(--text-muted); cursor: pointer;">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <p style="margin-bottom: 16px; color: var(--text-muted);">Are you sure you want to delete your account? This action cannot be undone.</p>
            <input type="password" id="confirmPassword" placeholder="Enter your password" style="width: 100%; padding: 12px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 16px;">
            <div style="display: flex; gap: 12px;">
                <button onclick="closeModal()" class="btn btn-outline" style="flex: 1;">Cancel</button>
                <button onclick="deleteAccount()" class="btn btn-danger" style="flex: 1;">Delete Account</button>
            </div>
        </div>
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
            if (link) {
                link.addEventListener('click', (e) => {
                    if (!link.hasAttribute('href') && !link.querySelector('a')) {
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
            document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
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

        // --- 5. Tab Navigation ---
        const tabButtons = document.querySelectorAll('.nav-item');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const tabId = button.getAttribute('data-tab');
                
                // Update active tab button
                tabButtons.forEach(btn => btn.classList.remove('active'));
                button.classList.add('active');
                
                // Show active tab content
                tabContents.forEach(content => content.classList.remove('active'));
                document.getElementById(tabId).classList.add('active');
            });
        });

        function openPhotoTab() {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            document.querySelector('[data-tab="photo-tab"]').classList.add('active');
            tabContents.forEach(content => content.classList.remove('active'));
            document.getElementById('photo-tab').classList.add('active');
        }

        // --- 6. Profile Form Reset ---
        function resetProfileForm() {
            const form = document.getElementById('profileForm');
            form.reset();
            showToast('Form reset to original values', 'info');
        }

        // --- 7. Password Validation ---
        const passwordForm = document.getElementById('passwordForm');
        const newPasswordInput = document.getElementById('newPassword');
        
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                const currentPassword = this.querySelector('input[name="current_password"]').value;
                const newPassword = this.querySelector('input[name="new_password"]').value;
                const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    showToast('Password must be at least 8 characters long', 'error');
                    return;
                }
                
                if (!/[A-Z]/.test(newPassword)) {
                    e.preventDefault();
                    showToast('Password must contain at least one uppercase letter', 'error');
                    return;
                }
                
                if (!/[a-z]/.test(newPassword)) {
                    e.preventDefault();
                    showToast('Password must contain at least one lowercase letter', 'error');
                    return;
                }
                
                if (!/[0-9]/.test(newPassword)) {
                    e.preventDefault();
                    showToast('Password must contain at least one number', 'error');
                    return;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    showToast('New passwords do not match', 'error');
                    return;
                }
            });
        }

        // --- 8. Profile Photo Upload ---
        const photoInput = document.getElementById('photoInput');
        const savePhotoBtn = document.getElementById('savePhotoBtn');
        const avatarPreview = document.getElementById('avatarPreview');
        const avatarPlaceholder = document.getElementById('avatarPlaceholder');
        
        if (photoInput) {
            photoInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validate file type
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!allowedTypes.includes(file.type)) {
                        showToast('Invalid file type. Please select an image file.', 'error');
                        this.value = '';
                        return;
                    }
                    
                    // Validate file size (5MB max)
                    if (file.size > 5 * 1024 * 1024) {
                        showToast('File size too large. Maximum size is 5MB.', 'error');
                        this.value = '';
                        return;
                    }
                    
                    // Show preview
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (avatarPreview) {
                            avatarPreview.src = e.target.result;
                            avatarPreview.style.display = 'block';
                        }
                        
                        if (avatarPlaceholder) {
                            avatarPlaceholder.style.display = 'none';
                        }
                        
                        // Enable save button
                        savePhotoBtn.disabled = false;
                        
                        // Update file name display
                        const fileName = document.getElementById('fileName');
                        if (fileName) {
                            fileName.textContent = file.name;
                        }
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        function cancelPhotoUpload() {
            if (photoInput) {
                photoInput.value = '';
            }
            savePhotoBtn.disabled = true;
            
            // Reset preview
            if (avatarPreview) {
                avatarPreview.style.display = 'none';
            }
            if (avatarPlaceholder) {
                avatarPlaceholder.style.display = 'flex';
            }
            
            // Reset file name display
            const fileName = document.getElementById('fileName');
            if (fileName) {
                fileName.textContent = 'Click to upload or drag and drop';
            }
            
            showToast('Photo upload cancelled', 'info');
        }

        function removeProfilePhoto() {
            if (confirm('Are you sure you want to remove your profile photo?')) {
                // Create a form and submit it via POST
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = 'csrf_token';
                csrfToken.value = '<?php echo $_SESSION['csrf_token']; ?>';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'remove_photo';
                
                form.appendChild(csrfToken);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // --- 9. Account Deletion ---
        function confirmAccountDeletion() {
            document.getElementById('deleteAccountModal').style.display = 'flex';
            document.getElementById('confirmPassword').value = '';
            document.getElementById('confirmPassword').focus();
        }

        function closeModal() {
            document.getElementById('deleteAccountModal').style.display = 'none';
        }

        function deleteAccount() {
            const password = document.getElementById('confirmPassword').value;
            if (!password) {
                showToast('Please enter your password to confirm', 'error');
                return;
            }
            
            // In a real application, you would make an AJAX request here
            showToast('Account deletion is not available in this demo. Contact administrator.', 'warning');
            closeModal();
        }

        // --- 10. Toast Notification System ---
        function showToast(message, type = 'info') {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());
            
            // Create toast element
            const toast = document.createElement('div');
            toast.className = `toast`;
            
            // Set icon based on type
            let icon = 'ph-info';
            let color = '#3B82F6';
            if (type === 'success') {
                icon = 'ph-check-circle';
                color = '#10B981';
            }
            if (type === 'error') {
                icon = 'ph-warning-circle';
                color = '#EF4444';
            }
            if (type === 'warning') {
                icon = 'ph-warning';
                color = '#F59E0B';
            }
            
            toast.innerHTML = `
                <i class="ph ${icon}" style="color: ${color};"></i>
                <span>${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    <i class="ph ph-x"></i>
                </button>
            `;
            
            // Add styles
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--bg-card);
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 16px 20px;
                display: flex;
                align-items: center;
                gap: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                z-index: 9999;
                animation: slideInRight 0.3s ease;
                max-width: 400px;
                color: var(--text-main);
            `;
            
            // Add close button styling
            const closeBtn = toast.querySelector('.toast-close');
            closeBtn.style.cssText = `
                background: none;
                border: none;
                color: var(--text-muted);
                cursor: pointer;
                margin-left: auto;
                padding: 4px;
                border-radius: 4px;
                transition: all 0.2s ease;
            `;
            closeBtn.onmouseover = function() {
                this.style.background = 'var(--bg-body)';
                this.style.color = 'var(--text-main)';
            };
            closeBtn.onmouseout = function() {
                this.style.background = 'none';
                this.style.color = 'var(--text-muted)';
            };
            
            document.body.appendChild(toast);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }

        // --- 11. Initialize ---
        window.addEventListener('load', function() {
            // Focus on first input in active tab
            const activeTab = document.querySelector('.tab-content.active');
            if (activeTab) {
                const firstInput = activeTab.querySelector('input:not([type="hidden"])');
                if (firstInput) {
                    setTimeout(() => {
                        firstInput.focus();
                    }, 300);
                }
            }
        });
    </script>
</body>
</html>