<?php
/**
 * mirage_manual_tables.php
 * MIRAGE LOD‑3 – Dynamic cost breakdown with add/remove rows and size selection.
 * Features: unit selector (g/kg) per row, column totals, and save functionality.
 */

session_start();

// --- 1. AUTHENTICATION (optional demo) ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexander Pierce';
    $_SESSION['role'] = 'admin';
    $_SESSION['email'] = 'alex@example.com';
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: logout.php");
    exit;
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

// --- 3. GET SUBCONTRACTOR LIST ---
$subs = $pdo->query("SELECT id, company_name FROM subcontractors ORDER BY company_name")->fetchAll();

// --- 4. HANDLE SUBCONTRACTOR SELECTION (via GET for display) ---
$selected_sub_id = isset($_GET['subcontractor_id']) ? (int)$_GET['subcontractor_id'] : 0;
$selected_sub_name = '';
if ($selected_sub_id) {
    $stmt = $pdo->prepare("SELECT company_name FROM subcontractors WHERE id = ?");
    $stmt->execute([$selected_sub_id]);
    $sub = $stmt->fetch();
    $selected_sub_name = $sub ? $sub['company_name'] : '';
}

// --- 5. GET USER PHOTO (if available) ---
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
    <title>MIRAGE Production | NexusAdmin</title>
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
        .menu-link {
            display: flex; align-items: center; justify-content: space-between;
            padding: 12px 16px; border-radius: 8px;
            color: rgba(255,255,255,0.7); cursor: pointer; transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        .menu-link:hover, .menu-link.active { background-color: rgba(255,255,255,0.1); color: #fff; transform: translateX(2px); }
        .link-content { display: flex; align-items: center; gap: 12px; }
        .menu-icon { font-size: 1.2rem; min-width: 24px; text-align: center; transition: transform 0.2s ease; }
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
        .submenu-link:hover { color: #fff; background: rgba(255,255,255,0.05); transform: translateX(2px); }

        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; position: relative; }

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
        .toggle-btn:hover { color: var(--primary); background: var(--bg-body); transform: translateY(-1px); }

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
            border: 2px solid var(--border); transition: all 0.2s ease;
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
        .dropdown-item:hover { background-color: var(--bg-body); color: var(--primary); transform: translateX(2px); }
        .dropdown-item.danger:hover { background-color: rgba(239,68,68,0.1); color: #ef4444; }

        .scrollable { flex: 1; overflow-y: auto; padding: 32px; scroll-behavior: smooth; }

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
        .control-bar {
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
        .control-bar label {
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
        }
        .btn:hover { background: var(--primary-hover); }
        .btn-secondary {
            background: var(--text-muted);
        }
        .btn-secondary:hover { background: #4b5563; }
        .btn-danger {
            background: var(--color-danger);
        }
        .btn-danger:hover { background: #b91c1c; }
        .assignment-tag {
            margin-left: auto;
            background: #e0f2fe;
            padding: 5px 15px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #0369a1;
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin: 30px 0 20px;
            border-left: 6px solid var(--primary);
            padding-left: 15px;
        }
        .table-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .table-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }
        .table-card h3 {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            border: 1px solid var(--border);
            margin-bottom: 15px;
            min-width: 600px;
        }
        th {
            background: var(--sidebar-bg);
            color: white;
            font-weight: 600;
            padding: 10px 4px;
            text-align: center;
            border: 1px solid var(--border);
        }
        td {
            padding: 8px 4px;
            border: 1px solid var(--border);
            text-align: center;
            font-family: 'Roboto Mono', monospace;
        }
        td:first-child {
            text-align: left;
            font-weight: 500;
            background-color: var(--bg-body);
        }
        tbody tr:nth-child(even) {
            background-color: var(--bg-body);
        }
        .qty-input, .per-piece-input {
            width: 70px;
            text-align: center;
            padding: 4px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--bg-card);
            color: var(--text-main);
        }
        .unit-select {
            width: 50px;
            padding: 4px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 0.8rem;
        }
        .totals-row {
            background: var(--border);
            font-weight: 600;
        }
        .summary-box {
            display: flex;
            justify-content: space-between;
            background: var(--bg-body);
            border-radius: 8px;
            padding: 12px 15px;
            margin-top: 10px;
            font-weight: 500;
        }
        .cost-section {
            margin-top: 40px;
        }
        .cost-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid var(--border);
        }
        .cost-table th {
            background: var(--sidebar-bg);
            color: white;
            padding: 10px;
            text-align: center;
        }
        .cost-table td {
            padding: 10px;
            text-align: center;
            vertical-align: middle;
        }
        .cost-table td:first-child {
            text-align: left;
            background: none;
        }
        .cost-input {
            width: 100px;
            text-align: right;
            padding: 6px 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--bg-card);
            color: var(--text-main);
        }
        .cost-select {
            width: 100px;
            padding: 6px 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--bg-card);
            color: var(--text-main);
        }
        .cost-description {
            width: 150px;
            text-align: left;
            padding: 6px 8px;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--bg-card);
            color: var(--text-main);
        }
        .cost-total {
            font-weight: 600;
            background: var(--bg-body);
        }
        .remove-row {
            background: none;
            border: none;
            color: var(--color-danger);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 4px 8px;
        }
        .remove-row:hover {
            color: #b91c1c;
        }
        .grand-total {
            background: var(--primary);
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
            padding: 15px 25px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 15px;
            margin: 20px 0;
            justify-content: flex-end;
        }
        .footer-note {
            color: var(--text-muted);
            font-size: 0.8rem;
            text-align: right;
            margin-top: 15px;
            border-top: 1px dashed var(--border);
            padding-top: 10px;
        }
        .overlay { 
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.5); backdrop-filter: blur(2px);
            z-index: 45; display: none; animation: fadeIn 0.3s ease;
        }
        #themeToggle {
            background: var(--bg-card); border: 1px solid var(--border);
            width: 42px; height: 42px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            transition: all 0.3s ease;
        }
        #themeToggle:hover { transform: rotate(15deg); border-color: var(--primary); color: var(--primary); }
        .scrollable::-webkit-scrollbar { width: 8px; }
        .scrollable::-webkit-scrollbar-track { background: transparent; }
        .scrollable::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .scrollable::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        @media (max-width: 1100px) {
            .table-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .top-header { padding: 0 20px; }
            .scrollable { padding: 20px; }
            .sidebar { position: fixed; left: -280px; height: 100%; top: 0; }
            body.mobile-open .sidebar { transform: translateX(280px); box-shadow: 0 0 40px rgba(0,0,0,0.3); }
            body.mobile-open .overlay { display: block; }
            .profile-info { display: none; }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar (same as other pages) -->
    <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle"><i class="ph ph-list"></i></button>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
                    <span style="font-size: 0.9rem; color: var(--text-muted);">MIRAGE Production</span>
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
                <h1>🧾 MIRAGE LOD‑3 – Manual Entry Tables</h1>
                <div class="subtitle">Enter Top & Skin quantities and per‑piece values (with unit selection). Dynamic cost rows with size selection.</div>

                <!-- Control bar with LOD name and subcontractor -->
                <div class="control-bar">
                    <label>LOD Name:</label>
                    <input type="text" id="lodName" value="MIRAGE LOD-3" placeholder="e.g. MIRAGE LOD-3">
                    
                    <label>Assign to:</label>
                    <form method="get" style="display: flex; gap: 10px;">
                        <select name="subcontractor_id" id="subcontractorSelect">
                            <option value="0">-- Select subcontractor --</option>
                            <?php foreach ($subs as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= $selected_sub_id == $s['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn">Apply</button>
                    </form>
                    
                    <?php if ($selected_sub_id && $selected_sub_name): ?>
                        <div class="assignment-tag">
                            <i class="ph ph-user-circle"></i> <?= htmlspecialchars($selected_sub_name) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 8 INCH TABLES -->
                <div class="section-title"><i class="ph ph-ruler"></i> 8 Inch Wigs</div>
                <div class="table-grid">
                    <!-- Top Table for 8" -->
                    <div class="table-card">
                        <h3><i class="ph ph-shirt-folded"></i> Top Cap (8″)</h3>
                        <table id="top8Table">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Top/pc</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Top Total</th>
                                </tr>
                            </thead>
                            <tbody id="top8Tbody"></tbody>
                            <tfoot>
                                <tr class="totals-row">
                                    <td colspan="2">Sum per‑piece: <span id="top8SumPerPiece">0</span></td>
                                    <td></td>
                                    <td>Sum qty: <span id="top8SumQty">0</span></td>
                                    <td>Total: <span id="top8Total">0</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <!-- Skin Table for 8" (formerly Iskin) -->
                    <div class="table-card">
                        <h3><i class="ph ph-drop-half"></i> Skin Cap (8″)</h3>
                        <table id="iskin8Table">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Skin/pc</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Skin Total</th>
                                </tr>
                            </thead>
                            <tbody id="iskin8Tbody"></tbody>
                            <tfoot>
                                <tr class="totals-row">
                                    <td colspan="2">Sum per‑piece: <span id="iskin8SumPerPiece">0</span></td>
                                    <td></td>
                                    <td>Sum qty: <span id="iskin8SumQty">0</span></td>
                                    <td>Total: <span id="iskin8Total">0</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- 10 INCH TABLES -->
                <div class="section-title"><i class="ph ph-ruler"></i> 10 Inch Wigs</div>
                <div class="table-grid">
                    <!-- Top Table for 10" -->
                    <div class="table-card">
                        <h3><i class="ph ph-shirt-folded"></i> Top Cap (10″)</h3>
                        <table id="top10Table">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Top/pc</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Top Total</th>
                                </tr>
                            </thead>
                            <tbody id="top10Tbody"></tbody>
                            <tfoot>
                                <tr class="totals-row">
                                    <td colspan="2">Sum per‑piece: <span id="top10SumPerPiece">0</span></td>
                                    <td></td>
                                    <td>Sum qty: <span id="top10SumQty">0</span></td>
                                    <td>Total: <span id="top10Total">0</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <!-- Skin Table for 10" (formerly Iskin) -->
                    <div class="table-card">
                        <h3><i class="ph ph-drop-half"></i> Skin Cap (10″)</h3>
                        <table id="iskin10Table">
                            <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Skin/pc</th>
                                    <th>Unit</th>
                                    <th>Quantity</th>
                                    <th>Skin Total</th>
                                </tr>
                            </thead>
                            <tbody id="iskin10Tbody"></tbody>
                            <tfoot>
                                <tr class="totals-row">
                                    <td colspan="2">Sum per‑piece: <span id="iskin10SumPerPiece">0</span></td>
                                    <td></td>
                                    <td>Sum qty: <span id="iskin10SumQty">0</span></td>
                                    <td>Total: <span id="iskin10Total">0</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- Summary of pieces -->
                <div class="summary-box" style="background:#e0f2fe; margin-top:20px;">
                    <span><strong>Total Top pieces (8″+10″):</strong> <span id="totalTopPcs">0</span></span>
                    <span><strong>Total Skin pieces:</strong> <span id="totalIskinPcs">0</span></span>
                    <span><strong>Total pieces (all):</strong> <span id="totalAllPcs">0</span></span>
                </div>

                <!-- Dynamic Cost Breakdown -->
                <div class="section-title"><i class="ph ph-currency-circle-dollar"></i> Cost Breakdown</div>
                <div class="cost-section">
                    <table class="cost-table">
                        <thead>
                            <tr><th>Size</th><th>Description</th><th>Quantity</th><th>Unit Price (BDT)</th><th>Total (BDT)</th><th></th></tr>
                        </thead>
                        <tbody id="costRowsContainer"></tbody>
                    </table>
                    <div style="margin-top: 10px;">
                        <button class="btn btn-secondary" id="addCostRowBtn"><i class="ph ph-plus-circle"></i> Add Cost Row</button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons">
                    <button class="btn btn-secondary" id="resetBtn">Reset All</button>
                    <button class="btn" id="saveBatchBtn">Save Current Batch</button>
                </div>

                <!-- Grand Total -->
                <div class="grand-total">
                    <span>GRAND TOTAL</span>
                    <span id="grandTotal">0</span>
                </div>

                <div class="footer-note">
                    * All values are manually entered. Use "Add Cost Row" to include any cost item.
                </div>
            </div>
        </div>
    </main>

    <!-- JavaScript -->
    <script>
        // --- Sidebar Toggle (standard) ---
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

        // ===== MIRAGE Calculator JavaScript =====
        const sizes = ['7x5','8x5','8x6','9x6','9x7','10x7','10x8','11x8','11x9'];

        function createTableBody(tbodyId, defaultPerPiece = 0, defaultQty = 0, defaultUnit = 'g') {
            const tbody = document.getElementById(tbodyId);
            tbody.innerHTML = '';
            for (let i = 0; i < sizes.length; i++) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${sizes[i]}</td>
                    <td><input type="number" class="per-piece-input" data-table="${tbodyId}" data-index="${i}" value="${defaultPerPiece}" min="0" step="1"></td>
                    <td>
                        <select class="unit-select" data-table="${tbodyId}" data-index="${i}">
                            <option value="g" ${defaultUnit === 'g' ? 'selected' : ''}>g</option>
                            <option value="kg" ${defaultUnit === 'kg' ? 'selected' : ''}>kg</option>
                        </select>
                    </td>
                    <td><input type="number" class="qty-input" data-table="${tbodyId}" data-index="${i}" value="${defaultQty}" min="0" step="1"></td>
                    <td class="total-cell" data-table="${tbodyId}" data-index="${i}">0</td>
                `;
                tbody.appendChild(tr);
            }
        }

        createTableBody('top8Tbody', 0, 0, 'g');
        createTableBody('iskin8Tbody', 0, 0, 'g');
        createTableBody('top10Tbody', 0, 0, 'g');
        createTableBody('iskin10Tbody', 0, 0, 'g');

        function updateTableTotals() {
            const tables = ['top8Tbody','iskin8Tbody','top10Tbody','iskin10Tbody'];
            let top8Total=0, iskin8Total=0, top10Total=0, iskin10Total=0;
            let top8SumPerPiece=0, iskin8SumPerPiece=0, top10SumPerPiece=0, iskin10SumPerPiece=0;
            let top8SumQty=0, iskin8SumQty=0, top10SumQty=0, iskin10SumQty=0;

            tables.forEach(tableId => {
                let tableSum = 0;
                let sumPerPiece = 0;
                let sumQty = 0;
                const rows = document.querySelectorAll(`#${tableId} tr`);
                rows.forEach(row => {
                    const perPiece = parseFloat(row.querySelector('.per-piece-input')?.value) || 0;
                    const qty = parseFloat(row.querySelector('.qty-input')?.value) || 0;
                    const total = perPiece * qty;
                    const totalCell = row.querySelector('.total-cell');
                    if(totalCell) totalCell.textContent = total.toFixed(0);
                    tableSum += total;
                    sumPerPiece += perPiece;
                    sumQty += qty;
                });

                // Update specific table totals and sums
                if(tableId === 'top8Tbody') {
                    document.getElementById('top8Total').textContent = tableSum.toFixed(0);
                    document.getElementById('top8SumPerPiece').textContent = sumPerPiece.toFixed(0);
                    document.getElementById('top8SumQty').textContent = sumQty.toFixed(0);
                    top8Total = tableSum;
                }
                else if(tableId === 'iskin8Tbody') {
                    document.getElementById('iskin8Total').textContent = tableSum.toFixed(0);
                    document.getElementById('iskin8SumPerPiece').textContent = sumPerPiece.toFixed(0);
                    document.getElementById('iskin8SumQty').textContent = sumQty.toFixed(0);
                    iskin8Total = tableSum;
                }
                else if(tableId === 'top10Tbody') {
                    document.getElementById('top10Total').textContent = tableSum.toFixed(0);
                    document.getElementById('top10SumPerPiece').textContent = sumPerPiece.toFixed(0);
                    document.getElementById('top10SumQty').textContent = sumQty.toFixed(0);
                    top10Total = tableSum;
                }
                else if(tableId === 'iskin10Tbody') {
                    document.getElementById('iskin10Total').textContent = tableSum.toFixed(0);
                    document.getElementById('iskin10SumPerPiece').textContent = sumPerPiece.toFixed(0);
                    document.getElementById('iskin10SumQty').textContent = sumQty.toFixed(0);
                    iskin10Total = tableSum;
                }
            });

            // Update overall piece counts
            let topPcs = 0, iskinPcs = 0;
            document.querySelectorAll('#top8Tbody .qty-input, #top10Tbody .qty-input').forEach(inp => topPcs += parseFloat(inp.value)||0);
            document.querySelectorAll('#iskin8Tbody .qty-input, #iskin10Tbody .qty-input').forEach(inp => iskinPcs += parseFloat(inp.value)||0);
            document.getElementById('totalTopPcs').textContent = topPcs;
            document.getElementById('totalIskinPcs').textContent = iskinPcs;
            document.getElementById('totalAllPcs').textContent = topPcs + iskinPcs;
        }

        function attachTableListeners() {
            document.querySelectorAll('.per-piece-input, .qty-input, .unit-select').forEach(inp => inp.addEventListener('input', updateTableTotals));
        }

        // ----- Dynamic Cost Rows -----
        const costRowsContainer = document.getElementById('costRowsContainer');

        function createCostRow(data = { size: '8', description: '', quantity: 0, unit_price: 0 }) {
            const row = document.createElement('tr');
            row.className = 'cost-row';
            row.innerHTML = `
                <td>
                    <select class="cost-select size-select">
                        <option value="8" ${data.size === '8' ? 'selected' : ''}>8 inch</option>
                        <option value="10" ${data.size === '10' ? 'selected' : ''}>10 inch</option>
                        <option value="both" ${data.size === 'both' ? 'selected' : ''}>Both</option>
                    </select>
                </td>
                <td><input type="text" class="cost-description desc-input" value="${data.description}" placeholder="e.g. Hair"></td>
                <td><input type="number" class="cost-input qty-input" value="${data.quantity}" min="0" step="0.001"></td>
                <td><input type="number" class="cost-input price-input" value="${data.unit_price}" min="0" step="1"></td>
                <td class="cost-total row-total">0</td>
                <td><button class="remove-row" title="Remove row"><i class="ph ph-trash"></i></button></td>
            `;
            row.querySelectorAll('.qty-input, .price-input').forEach(inp => inp.addEventListener('input', updateCostRows));
            row.querySelector('.remove-row').addEventListener('click', () => {
                row.remove();
                updateCostRows();
            });
            return row;
        }

        function updateCostRows() {
            let grand = 0;
            document.querySelectorAll('.cost-row').forEach(row => {
                const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                const price = parseFloat(row.querySelector('.price-input').value) || 0;
                const total = qty * price;
                row.querySelector('.row-total').textContent = total.toFixed(0);
                grand += total;
            });
            document.getElementById('grandTotal').textContent = grand.toFixed(0) + ' BDT';
        }

        function initDefaultCostRows() {
            const defaultRows = [
                { size: '8', description: 'Hair', quantity: 0, unit_price: 0 },
                { size: '10', description: 'Hair', quantity: 0, unit_price: 0 },
                { size: 'both', description: 'Base', quantity: 0, unit_price: 0 },
                { size: 'both', description: 'Noting', quantity: 0, unit_price: 0 },
                { size: 'both', description: 'Needle', quantity: 0, unit_price: 0 },
                { size: 'both', description: 'Courier', quantity: 0, unit_price: 0 },
                { size: 'both', description: 'Processing', quantity: 0, unit_price: 0 },
                { size: 'both', description: 'Other', quantity: 0, unit_price: 0 }
            ];
            defaultRows.forEach(r => costRowsContainer.appendChild(createCostRow(r)));
            updateCostRows();
        }

        document.getElementById('addCostRowBtn').addEventListener('click', () => {
            costRowsContainer.appendChild(createCostRow());
            updateCostRows();
        });

        // ----- Collect data for saving -----
        function collectData() {
            const lodName = document.getElementById('lodName').value;
            const subcontractorId = document.getElementById('subcontractorSelect').value;
            const productionDate = new Date().toISOString().slice(0,10);
            const items = [];
            const tables = [
                { tbody: 'top8Tbody', type: 'top8' },
                { tbody: 'iskin8Tbody', type: 'iskin8' },
                { tbody: 'top10Tbody', type: 'top10' },
                { tbody: 'iskin10Tbody', type: 'iskin10' }
            ];
            tables.forEach(t => {
                const rows = document.querySelectorAll(`#${t.tbody} tr`);
                rows.forEach((row, idx) => {
                    const perPiece = parseFloat(row.querySelector('.per-piece-input').value) || 0;
                    const unit = row.querySelector('.unit-select').value; // new unit field
                    const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
                    // Always save row, even if zero, to preserve unit? We'll save if any field non-zero.
                    if (perPiece !== 0 || qty !== 0) {
                        items.push({ 
                            type: t.type, 
                            size: sizes[idx], 
                            per_piece: perPiece, 
                            unit: unit, 
                            quantity: qty 
                        });
                    }
                });
            });

            const costs = [];
            document.querySelectorAll('.cost-row').forEach(row => {
                const size = row.querySelector('.size-select').value;
                const description = row.querySelector('.desc-input').value;
                const quantity = parseFloat(row.querySelector('.qty-input').value) || 0;
                const unit_price = parseFloat(row.querySelector('.price-input').value) || 0;
                if (description.trim() !== '' || quantity !== 0 || unit_price !== 0) {
                    costs.push({ size, description, quantity, unit_price });
                }
            });

            return { lod_name: lodName, subcontractor_id: subcontractorId, production_date: productionDate, items, costs };
        }

        document.getElementById('saveBatchBtn').addEventListener('click', function() {
            const data = collectData();
            fetch('save_mirage_batch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) { alert('Batch saved! ID: '+result.batch_id); location.reload(); }
                else { alert('Error: '+result.error); }
            })
            .catch(err => alert('Network error: '+err));
        });

        document.getElementById('resetBtn').addEventListener('click', function() {
            if (confirm('Reset all entries to zero?')) {
                document.querySelectorAll('.per-piece-input, .qty-input').forEach(inp => inp.value = 0);
                // Reset units to default 'g'
                document.querySelectorAll('.unit-select').forEach(sel => sel.value = 'g');
                costRowsContainer.innerHTML = '';
                initDefaultCostRows();
                updateTableTotals();
                updateCostRows();
            }
        });

        // Initialize
        attachTableListeners();
        initDefaultCostRows();
        updateTableTotals();
        updateCostRows();
    </script>
</body>
</html>