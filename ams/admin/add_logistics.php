<?php
/**
 * add_logistics.php
 * Page to record logistics and shipping expenses.
 * Updated: Header and sidebar to match admin_dashboard.php
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
    die("DB Connection Failed: " . $ex->getMessage());
}

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

// --- 3. HANDLE FORM SUBMISSION ---
$message = "";
$msg_type = ""; // success or error

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_logistics'])) {
    
    // Sanitize inputs
    $reference = trim($_POST['reference_no'] ?? '');
    $provider = trim($_POST['provider'] ?? '');
    $date = $_POST['expense_date'];
    $amount = floatval($_POST['amount']);
    $method = $_POST['payment_method'];
    $status = $_POST['status'];
    $desc = trim($_POST['description'] ?? '');

    // Basic Validation
    if (empty($provider) || $amount <= 0 || empty($date)) {
        $message = "Please fill in Provider, Date, and a valid Amount.";
        $msg_type = "error";
    } else {
        try {
            $sql = "INSERT INTO logistics_expenses 
                    (reference_no, provider, expense_date, amount, payment_method, status, description) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$reference, $provider, $date, $amount, $method, $status, $desc]);

            $message = "Logistics expense added successfully!";
            $msg_type = "success";
            
            // Optional: Clear POST data to prevent resubmission on refresh
            $_POST = []; 
        } catch (Exception $e) {
            $message = "Database Error: " . $e->getMessage();
            $msg_type = "error";
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
    <title>Add Logistics Expense | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        /* --- CSS Variables & Shared Styles --- */
        :root {
            --primary: #4F46E5; --primary-hover: #4338ca;
            --bg-body: #F3F4F6; --bg-card: #ffffff;
            --text-main: #111827; --text-muted: #6B7280;
            --border: #E5E7EB;
            --sidebar-width: 280px; --sidebar-bg: #111827; --sidebar-text: #E5E7EB;
            --header-height: 64px; --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 0.75rem;
        }
        [data-theme="dark"] {
            --bg-body: #0f172a; --bg-card: #1e293b;
            --text-main: #f8fafc; --text-muted: #94a3b8;
            --border: #334155; --sidebar-bg: #020617; --primary: #6366f1;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button { font-family: inherit; }

        /* --- Sidebar & Layout --- */
        body.sidebar-collapsed { --sidebar-width: 80px; }
        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-bg); color: var(--sidebar-text); display: flex; flex-direction: column; transition: width var(--transition); z-index: 50; flex-shrink: 0; white-space: nowrap; }
        .sidebar-header { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 700; font-size: 1.25rem; color: #fff; gap: 10px; overflow: hidden; }
        
        /* Collapsed Sidebar Logic */
        body.sidebar-collapsed .logo-text, 
        body.sidebar-collapsed .link-text, 
        body.sidebar-collapsed .arrow-icon { display: none; opacity: 0; }
        body.sidebar-collapsed .sidebar-header { justify-content: center; padding: 0; }
        body.sidebar-collapsed .menu-link { justify-content: center; padding: 12px 0; }
        body.sidebar-collapsed .link-content { gap: 0; }
        body.sidebar-collapsed .menu-icon { font-size: 1.5rem; margin: 0; }
        body.sidebar-collapsed .submenu { display: none !important; }

        .sidebar-menu { padding: 16px 12px; overflow-y: auto; flex: 1; }
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
        .submenu-link:hover { color: #fff; background: rgba(255,255,255,0.05); }
        .submenu-link.active { color: #fff; background: var(--primary); font-weight: 500; }

        /* --- HEADER & DROPDOWN --- */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; flex-shrink: 0; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .toggle-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; padding: 8px; border-radius: 8px; transition: all 0.2s ease; }
        .toggle-btn:hover { color: var(--primary); background: var(--bg-body); transform: translateY(-1px); }

        .header-right { display: flex; align-items: center; gap: 24px; }
        
        /* DARK MODE TOGGLE BUTTON - Matches admin_dashboard.php */
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
            cursor: pointer;
            color: var(--text-muted);
        }
        
        #themeToggle:hover {
            transform: rotate(15deg);
            border-color: var(--primary);
            color: var(--primary);
        }

        .profile-container { position: relative; }
        .profile-menu { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 8px 12px; border-radius: 12px; transition: all 0.2s ease; }
        .profile-menu:hover { background-color: var(--bg-body); }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-name { font-size: 0.9rem; font-weight: 600; display: block; }
        .profile-role { font-size: 0.75rem; color: var(--text-muted); }
        .profile-img { width: 42px; height: 42px; border-radius: 12px; object-fit: cover; border: 2px solid var(--border); transition: all 0.2s ease; }
        .profile-placeholder { width: 42px; height: 42px; border-radius: 12px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.1rem; }

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
        
        .dropdown-menu.show { display: flex; }
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
        .dropdown-item:hover { background-color: var(--bg-body); color: var(--primary); transform: translateX(2px); }
        .dropdown-item.danger:hover { background-color: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* --- Form Page Specific --- */
        .scrollable { flex: 1; overflow-y: auto; padding: 32px; }
        
        .page-container { max-width: 900px; margin: 0 auto; }
        
        .card { 
            background: var(--bg-card); border-radius: var(--radius); 
            border: 1px solid var(--border); padding: 32px; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); 
        }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .form-group { display: flex; flex-direction: column; gap: 8px; }
        .form-group.full-width { grid-column: span 2; }

        label { font-size: 0.9rem; font-weight: 500; color: var(--text-main); }
        .required { color: #EF4444; margin-left: 2px; }

        input, select, textarea { 
            padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; 
            background: var(--bg-body); color: var(--text-main); font-size: 0.95rem; outline: none; transition: 0.2s; 
        }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        textarea { resize: vertical; min-height: 100px; }

        .btn-submit { 
            background: var(--primary); color: white; border: none; padding: 12px 24px; 
            border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.2s; 
            display: inline-flex; align-items: center; gap: 8px; font-size: 1rem;
        }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-1px); }

        /* Alerts */
        .alert { padding: 16px; border-radius: 8px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #065F46; border: 1px solid #10B981; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #991B1B; border: 1px solid #EF4444; }
        [data-theme="dark"] .alert-success { color: #6EE7B7; }
        [data-theme="dark"] .alert-error { color: #FCA5A5; }

        /* Mobile */
        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 45; display: none; animation: fadeIn 0.3s ease; }
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; }
            .form-group.full-width { grid-column: span 1; }
            body.sidebar-collapsed { --sidebar-width: 280px; } 
            .sidebar { position: fixed; left: -280px; height: 100%; }
            body.mobile-open .sidebar { transform: translateX(280px); }
            body.mobile-open .overlay { display: block; }
            .logo-text, .link-text, .arrow-icon { display: inline !important; opacity: 1 !important; }
            .sidebar-header { justify-content: flex-start !important; padding: 0 24px !important; }
            .top-header { padding: 0 20px; }
            .scrollable { padding: 24px 20px; }
            .profile-info { display: none; }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

     <?php include 'sidenavbar.php'; ?>
    <main class="main-content">
        <!-- Header - Matches admin_dashboard.php -->
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle">
                    <i class="ph ph-list"></i>
                </button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Add Logistics Expense</span>
                </div>
            </div>

            <div class="header-right">
                <!-- Dark Mode Toggle Button -->
                <button id="themeToggle" title="Toggle Theme">
                    <i class="ph ph-moon" id="themeIcon"></i>
                </button>

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
            <div class="page-container">
                
                <div style="margin-bottom: 24px;">
                    <h1 style="font-size: 1.75rem; font-weight: 700;">Add Logistics Expense</h1>
                    <p style="color: var(--text-muted); margin-top: 4px;">Record shipping, delivery, and transportation expenses.</p>
                </div>

                <?php if ($message): ?>
                    <div class="alert <?php echo ($msg_type === 'success') ? 'alert-success' : 'alert-error'; ?>">
                        <i class="ph <?php echo ($msg_type === 'success') ? 'ph-check-circle' : 'ph-warning-circle'; ?>" style="font-size: 1.25rem;"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <form method="POST" action="">
                        <div class="form-grid">
                            
                            <div class="form-group">
                                <label>Provider / Carrier <span class="required">*</span></label>
                                <input type="text" name="provider" placeholder="e.g. DHL, FedEx, In-house" required value="<?php echo e($_POST['provider'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Reference / Tracking No.</label>
                                <input type="text" name="reference_no" placeholder="e.g. TRK-882910" value="<?php echo e($_POST['reference_no'] ?? ''); ?>">
                            </div>

                            <div class="form-group">
                                <label>Expense Date <span class="required">*</span></label>
                                <input type="date" name="expense_date" required value="<?php echo e($_POST['expense_date'] ?? date('Y-m-d')); ?>">
                            </div>

                            <div class="form-group">
                                <label>Amount (Cost) <span class="required">*</span></label>
                                <div style="position: relative;">
                                    <span style="position: absolute; left: 12px; top: 11px; color: var(--text-muted);">$</span>
                                    <input type="number" name="amount" step="0.01" min="0" placeholder="0.00" style="padding-left: 28px; width: 100%;" required value="<?php echo e($_POST['amount'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Payment Method</label>
                                <select name="payment_method">
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Credit Card">Credit Card</option>
                                    <option value="On Credit">On Credit (Unpaid)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Payment Status</label>
                                <select name="status">
                                    <option value="Paid">Paid</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Overdue">Overdue</option>
                                </select>
                            </div>

                            <div class="form-group full-width">
                                <label>Description / Notes</label>
                                <textarea name="description" placeholder="Details about the shipment, destination, or goods..."><?php echo e($_POST['description'] ?? ''); ?></textarea>
                            </div>

                        </div>

                        <div style="margin-top: 32px; display: flex; justify-content: flex-end;">
                            <button type="submit" name="save_logistics" class="btn-submit">
                                <i class="ph ph-floppy-disk"></i> Save Expense
                            </button>
                        </div>
                    </form>
                </div>

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

        // --- 5. Close modal on Escape key ---
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
            }
        });

        // --- 6. Form validation and auto-focus ---
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus first input field
            const firstInput = document.querySelector('input[type="text"]');
            if (firstInput) {
                firstInput.focus();
            }
            
            // Set max date to today for expense date
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.querySelector('input[type="date"]');
            if (dateInput) {
                dateInput.max = today;
            }
            
            // Add success message auto-hide
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(() => {
                    successAlert.style.opacity = '0';
                    successAlert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 3000);
            }
        });
    </script>
</body>
</html>