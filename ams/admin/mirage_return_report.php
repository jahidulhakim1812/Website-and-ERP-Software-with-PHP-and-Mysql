<?php
/**
 * mirage_returns_report.php
 * MIRAGE LOD‑3 – Returns Report (Simplified).
 * Lists all batch return transactions with date and quantities.
 * Filter by subcontractor and date range.
 * Shows: return date, batch, subcontractor, type, size, returned, damaged, notes.
 * (No totals row.)
 */

session_start();

// --- Authentication ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexander Pierce';
    $_SESSION['role'] = 'admin';
    $_SESSION['email'] = 'alex@example.com';
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: login.php");
    exit;
}

// --- Database connection ---

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

// --- Get all subcontractors for dropdown ---
$subs = $pdo->query("SELECT id, company_name FROM subcontractors ORDER BY company_name")->fetchAll();

// --- Get filter values ---
$selected_sub_id = isset($_GET['subcontractor_id']) ? (int)$_GET['subcontractor_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query with optional filters
$sql = "
    SELECT 
        r.id,
        r.return_date,
        r.type,
        r.size,
        r.returned_qty,
        r.damaged_qty,
        r.notes,
        b.lod_name AS batch_name,
        s.company_name AS subcontractor_name
    FROM batch_returns r
    LEFT JOIN wigs_batches b ON r.batch_id = b.id
    LEFT JOIN subcontractors s ON b.subcontractor_id = s.id
    WHERE 1=1
";
$params = [];

if ($selected_sub_id > 0) {
    $sql .= " AND b.subcontractor_id = ?";
    $params[] = $selected_sub_id;
}
if (!empty($date_from)) {
    $sql .= " AND r.return_date >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $sql .= " AND r.return_date <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY r.return_date DESC, r.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$returns = $stmt->fetchAll();

// --- Get user photo (optional) ---
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

function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns Report | MIRAGE</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <style>
        /* --- CSS Variables & Reset (same as other pages) --- */
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
            --color-success: #10B981;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;
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
        .menu-link {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; border-radius: 8px;
            color: rgba(255,255,255,0.7); cursor: pointer; transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        .menu-link:hover, .menu-link.active { background-color: rgba(255,255,255,0.1); color: #fff; transform: translateX(2px); }
        .link-content { display: flex; align-items: center; gap: 12px; }
        .menu-icon { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .arrow-icon { transition: transform 0.3s ease; font-size: 0.8rem; opacity: 0.7; }

        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; padding-left: 12px; }
        .menu-item.open > .submenu { max-height: 500px; }
        .menu-item.open > .menu-link .arrow-icon { transform: rotate(180deg); }
        .menu-item.open > .menu-link { color: #fff; }
        .submenu-link {
            display: block; padding: 10px 16px 10px 42px;
            color: rgba(255,255,255,0.5); font-size: 0.9rem; border-radius: 8px;
            transition: all 0.2s ease;
        }
        .submenu-link:hover { color: #fff; background: rgba(255,255,255,0.05); }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

        .top-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 32px; flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .toggle-btn {
            background: none; border: none; font-size: 1.5rem; cursor: pointer;
            color: var(--text-muted); display: flex; align-items: center;
            transition: all 0.2s ease; padding: 8px; border-radius: 8px;
        }
        .toggle-btn:hover { color: var(--primary); background: var(--bg-body); }

        .header-right { display: flex; align-items: center; gap: 24px; }
        .profile-container { position: relative; }
        .profile-menu {
            display: flex; align-items: center; gap: 12px; cursor: pointer;
            padding: 8px 12px; border-radius: 12px; transition: all 0.2s ease;
        }
        .profile-menu:hover { background-color: var(--bg-body); }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-name { font-size: 0.9rem; font-weight: 600; display: block; }
        .profile-role { font-size: 0.75rem; color: var(--text-muted); }
        .profile-img {
            width: 42px; height: 42px; border-radius: 12px; object-fit: cover;
            border: 2px solid var(--border);
        }
        .profile-placeholder {
            width: 42px; height: 42px; border-radius: 12px; background: var(--primary);
            color: white; display: flex; align-items: center; justify-content: center;
            font-weight: 600; font-size: 1.1rem;
        }

        .dropdown-menu {
            position: absolute; top: calc(100% + 8px); right: 0; width: 220px;
            background: var(--bg-card); border: 1px solid var(--border);
            border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15);
            padding: 8px; z-index: 1000; display: none; flex-direction: column; gap: 4px;
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        .dropdown-menu.show { display: flex; }
        .dropdown-item {
            display: flex; align-items: center; gap: 10px; padding: 12px 16px;
            font-size: 0.9rem; color: var(--text-main); border-radius: 8px;
            transition: all 0.2s ease;
        }
        .dropdown-item:hover { background-color: var(--bg-body); color: var(--primary); }
        .dropdown-item.danger:hover { background-color: rgba(239,68,68,0.1); color: #ef4444; }

        .scrollable { flex: 1; overflow-y: auto; padding: 32px; }

        /* Page specific styles */
        .sheet-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 25px;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 1.9rem;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 5px;
        }
        .subtitle {
            color: var(--text-muted);
            margin-bottom: 25px;
            font-size: 0.95rem;
        }
        .filter-bar {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }
        .filter-bar label {
            font-weight: 600;
            color: var(--text-main);
        }
        input, select {
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 0.95rem;
        }
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-secondary {
            background: var(--text-muted);
        }
        .btn-secondary:hover { background: #4b5563; }

        .report-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border);
        }
        .report-table th {
            background: var(--sidebar-bg);
            color: white;
            font-weight: 600;
            padding: 12px 8px;
            text-align: center;
            border: 1px solid var(--border);
        }
        .report-table td {
            padding: 10px 8px;
            border: 1px solid var(--border);
            text-align: center;
        }
        .report-table td:first-child {
            text-align: left;
        }
        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-muted);
        }
        #themeToggle {
            background: var(--bg-card); border: 1px solid var(--border);
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);
            z-index: 45; display: none;
        }
        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -280px; }
            body.mobile-open .sidebar { transform: translateX(280px); }
            body.mobile-open .overlay { display: block; }
            .profile-info { display: none; }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar (simplified) -->
    <?php include 'sidenavbar.php'; ?>
    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle"><i class="ph ph-list"></i></button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Returns Report</span>
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
            <div class="sheet-container">
                <h1>📋 Returns Report</h1>
                <div class="subtitle">View all recorded batch returns – when and how many pieces were returned (undamaged) and damaged.</div>

                <!-- Filter form -->
                <div class="filter-bar">
                    <form method="get" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end; width: 100%;">
                        <div>
                            <label>Subcontractor:</label>
                            <select name="subcontractor_id">
                                <option value="0">All</option>
                                <?php foreach ($subs as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $selected_sub_id == $s['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['company_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label>From Date:</label>
                            <input type="date" name="date_from" value="<?= e($date_from) ?>">
                        </div>
                        <div>
                            <label>To Date:</label>
                            <input type="date" name="date_to" value="<?= e($date_to) ?>">
                        </div>
                        <div>
                            <button type="submit" class="btn"><i class="ph ph-funnel"></i> Filter</button>
                            <a href="mirage_returns_report.php" class="btn btn-secondary"><i class="ph ph-x-circle"></i> Clear</a>
                        </div>
                    </form>
                </div>

                <?php if (!empty($returns)): ?>
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Return Date</th>
                                <th>Batch</th>
                                <th>Subcontractor</th>
                                <th>Type</th>
                                <th>Size</th>
                                <th>Returned (pcs)</th>
                                <th>Damaged (pcs)</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($returns as $r): ?>
                            <tr>
                                <td><?= e($r['return_date']) ?></td>
                                <td><?= e($r['batch_name']) ?></td>
                                <td><?= e($r['subcontractor_name']) ?></td>
                                <td><?= e($r['type']) ?></td>
                                <td><?= e($r['size']) ?></td>
                                <td><?= number_format($r['returned_qty'], 2) ?></td>
                                <td><?= number_format($r['damaged_qty'], 2) ?></td>
                                <td><?= e($r['notes']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <!-- No totals row -->
                    </table>
                <?php else: ?>
                    <div class="no-data">No return records found.</div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        // --- Sidebar Toggle ---
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

        // --- Accordion ---
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
    </script>
</body>
</html>