<?php
/**
 * subcontractor_management.php
 * Subcontractor Management Page – Shared Hosting Friendly (with working photo column)
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

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: /ams/login.php");
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

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

// --- 4. HANDLE ACTIONS ---
$message = '';
$msg_type = '';

// Handle Delete
if (isset($_GET['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM subcontractors WHERE id = ?");
        $stmt->execute([$_GET['delete_id']]);
        $message = "Subcontractor deleted successfully!";
        $msg_type = 'success';
    } catch (Exception $e) {
        $message = "Error deleting subcontractor: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Handle Status Update
if (isset($_GET['toggle_status'])) {
    try {
        $sub_id = $_GET['toggle_status'];
        $stmt = $pdo->prepare("SELECT status FROM subcontractors WHERE id = ?");
        $stmt->execute([$sub_id]);
        $current = $stmt->fetch();
        
        $new_status = ($current['status'] == 'Active') ? 'Inactive' : 'Active';
        
        $stmt = $pdo->prepare("UPDATE subcontractors SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $sub_id]);
        
        $message = "Status updated to " . $new_status;
        $msg_type = 'success';
    } catch (Exception $e) {
        $message = "Error updating status: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// --- 5. GET USER PHOTO ---
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

// --- 6. FETCH SUBCONTRACTORS (include photo and nid_number) ---
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$specialization_filter = $_GET['specialization'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'newest';

$query = "SELECT id, company_name, contact_person, email, phone, address, specialization,
                 tax_id, nid_number, photo, registration_date, project_rate, status, rating, notes, created_at
          FROM subcontractors WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (company_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if ($status_filter !== 'all') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if ($specialization_filter !== 'all') {
    $query .= " AND specialization = ?";
    $params[] = $specialization_filter;
}

switch ($sort_by) {
    case 'name_asc':  $query .= " ORDER BY company_name ASC"; break;
    case 'name_desc': $query .= " ORDER BY company_name DESC"; break;
    case 'rate_high': $query .= " ORDER BY project_rate DESC"; break;
    case 'rate_low':  $query .= " ORDER BY project_rate ASC"; break;
    case 'oldest':    $query .= " ORDER BY created_at ASC"; break;
    case 'newest':
    default:          $query .= " ORDER BY created_at DESC"; break;
}

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $subcontractors = $stmt->fetchAll();
} catch (Exception $e) {
    $subcontractors = [];
    $message = "Error fetching subcontractors: " . $e->getMessage();
    $msg_type = 'error';
}

// Get stats
try {
    $total_count   = $pdo->query("SELECT COUNT(*) as count FROM subcontractors")->fetch()['count'];
    $active_count  = $pdo->query("SELECT COUNT(*) as count FROM subcontractors WHERE status = 'Active'")->fetch()['count'];
    $pending_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractors WHERE status = 'Pending'")->fetch()['count'];
    $inactive_count = $pdo->query("SELECT COUNT(*) as count FROM subcontractors WHERE status = 'Inactive'")->fetch()['count'];
    
    $specializations = $pdo->query("SELECT DISTINCT specialization FROM subcontractors WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $total_count = $active_count = $pending_count = $inactive_count = 0;
    $specializations = [];
}

// --- 7. PAGINATION ---
$per_page = 10;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $per_page;
$total_pages = ceil(count($subcontractors) / $per_page);
$paged_subcontractors = array_slice($subcontractors, $offset, $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subcontractors | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* --- All CSS remains exactly as before --- */
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
            --color-active: #10B981;
            --color-inactive: #EF4444;
            --color-pending: #F59E0B;
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

        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-bg); color: var(--sidebar-text); display: flex; flex-direction: column; transition: width var(--transition), transform var(--transition); z-index: 50; flex-shrink: 0; white-space: nowrap; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .sidebar-header { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 700; font-size: 1.25rem; color: #fff; gap: 12px; overflow: hidden; }
        body.sidebar-collapsed .logo-text, body.sidebar-collapsed .link-text, body.sidebar-collapsed .arrow-icon { display: none; opacity: 0; }
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

        .stats-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-card); padding: 20px; border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-hover); }
        .stat-card.active { border-left: 4px solid var(--color-active); }
        .stat-card.inactive { border-left: 4px solid var(--color-inactive); }
        .stat-card.pending { border-left: 4px solid var(--color-pending); }
        .stat-card.total { border-left: 4px solid var(--primary); }
        .stat-number { font-size: 1.8rem; font-weight: 700; margin-bottom: 8px; }
        .stat-label { font-size: 0.9rem; color: var(--text-muted); display: flex; align-items: center; gap: 6px; }
        .stat-change { font-size: 0.75rem; padding: 2px 6px; border-radius: 12px; margin-left: 8px; }
        .positive { background: rgba(16,185,129,0.1); color: var(--color-success); }
        .negative { background: rgba(239,68,68,0.1); color: var(--color-danger); }

        .filters-section { background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border); padding: 20px; margin-bottom: 24px; box-shadow: var(--shadow); }
        .filters-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
        .filters-title { font-size: 1.1rem; font-weight: 600; }
        .filters-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        @media (max-width:1024px){ .filters-grid{grid-template-columns:repeat(2,1fr);} }
        @media (max-width:768px){ .filters-grid{grid-template-columns:1fr;} }
        .filter-group { display: flex; flex-direction: column; gap: 8px; }
        .filter-label { font-size: 0.85rem; font-weight: 500; color: var(--text-muted); }
        .filter-input, .filter-select { padding: 10px 12px; border-radius: 8px; border: 1px solid var(--border); background: var(--bg-body); color: var(--text-main); font-size: 0.9rem; outline: none; transition: all 0.2s ease; }
        .filter-input:focus, .filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(79,70,229,0.1); }
        .filter-actions { display: flex; gap: 8px; align-items: flex-end; }

        .btn { padding: 12px 24px; border-radius: 8px; font-size: 0.95rem; font-weight: 600; transition: all 0.2s ease; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; border: none; }
        .btn-primary { background: var(--primary); color: white; border: 1px solid transparent; }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79,70,229,0.2); }
        .btn-secondary { background: var(--bg-card); color: var(--text-main); border: 1px solid var(--border); }
        .btn-secondary:hover { background: var(--bg-body); transform: translateY(-1px); }
        .btn-success { background: var(--color-success); color: white; border: 1px solid transparent; }
        .btn-success:hover { background: #0da271; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,185,129,0.2); }
        .btn-danger { background: var(--color-danger); color: white; border: 1px solid transparent; }
        .btn-danger:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239,68,68,0.2); }
        .btn-sm { padding: 8px 12px; font-size: 0.85rem; }
        .btn-icon { padding: 8px; width: 36px; height: 36px; justify-content: center; }

        .table-container { background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border); box-shadow: var(--shadow); overflow: hidden; }
        .table-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-bottom: 1px solid var(--border); background: linear-gradient(90deg, rgba(79,70,229,0.05) 0%, rgba(79,70,229,0.02) 100%); }
        .table-title { font-size: 1.1rem; font-weight: 700; color: var(--text-main); }
        .table-actions { display: flex; gap: 8px; }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; min-width: 1200px; }
        .data-table th { padding: 16px 20px; text-align: left; font-weight: 600; font-size: 0.85rem; color: var(--text-muted); border-bottom: 2px solid var(--border); white-space: nowrap; }
        .data-table td { padding: 16px 20px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        .data-table tbody tr { transition: all 0.2s ease; }
        .data-table tbody tr:hover { background: var(--bg-body); }
        .data-table tbody tr:last-child td { border-bottom: none; }

        .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-active { background: rgba(16,185,129,0.12); color: var(--color-success); }
        .status-inactive { background: rgba(239,68,68,0.12); color: var(--color-danger); }
        .status-pending { background: rgba(245,158,11,0.12); color: var(--color-warning); }

        .photo-thumb { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border); }
        .photo-placeholder { width: 40px; height: 40px; border-radius: 8px; background: var(--bg-body); display: flex; align-items: center; justify-content: center; color: var(--text-muted); border: 1px dashed var(--border); }

        .rating-stars { display: inline-flex; gap: 2px; }
        .rating-stars i { color: #E5E7EB; font-size: 0.9rem; }
        .rating-stars i.filled { color: #F59E0B; }

        .company-cell { display: flex; align-items: center; gap: 12px; }
        .company-avatar { width: 40px; height: 40px; border-radius: 10px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1rem; flex-shrink: 0; }
        .company-info { display: flex; flex-direction: column; }
        .company-name { font-weight: 600; font-size: 0.95rem; margin-bottom: 2px; }
        .company-tax { font-size: 0.75rem; color: var(--text-muted); }

        .contact-info { display: flex; flex-direction: column; }
        .contact-name { font-weight: 500; margin-bottom: 4px; }
        .contact-email { font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 4px; }

        .action-buttons { display: flex; gap: 6px; }
        .action-btn { padding: 6px 10px; border-radius: 6px; background: var(--bg-body); border: 1px solid var(--border); color: var(--text-muted); transition: all 0.2s ease; display: flex; align-items: center; gap: 4px; font-size: 0.8rem; }
        .action-btn:hover { background: var(--bg-card); color: var(--text-main); }
        .action-btn.view:hover { color: var(--primary); border-color: var(--primary); }
        .action-btn.edit:hover { color: var(--color-warning); border-color: var(--color-warning); }
        .action-btn.delete:hover { color: var(--color-danger); border-color: var(--color-danger); }

        .pagination { display: flex; justify-content: space-between; align-items: center; padding: 20px 24px; border-top: 1px solid var(--border); background: var(--bg-card); }
        .pagination-info { font-size: 0.9rem; color: var(--text-muted); }
        .pagination-controls { display: flex; gap: 8px; }
        .page-btn { padding: 8px 12px; border-radius: 6px; background: var(--bg-body); border: 1px solid var(--border); color: var(--text-main); font-size: 0.85rem; transition: all 0.2s ease; }
        .page-btn:hover { background: var(--bg-card); border-color: var(--primary); color: var(--primary); }
        .page-btn.active { background: var(--primary); color: white; border-color: var(--primary); }
        .page-btn.disabled { opacity: 0.5; cursor: not-allowed; }
        .page-btn.disabled:hover { background: var(--bg-body); border-color: var(--border); color: var(--text-main); }

        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; animation: fadeIn 0.3s ease; }
        .modal-content { background: var(--bg-card); border-radius: var(--radius); box-shadow: 0 20px 40px rgba(0,0,0,0.2); width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .modal-title { font-size: 1.2rem; font-weight: 700; }
        .modal-close { background: none; border: none; font-size: 1.2rem; color: var(--text-muted); cursor: pointer; padding: 4px; border-radius: 4px; }
        .modal-close:hover { background: var(--bg-body); color: var(--text-main); }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 20px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }

        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 45; display: none; animation: fadeIn 0.3s ease; }
        #themeToggle { background: var(--bg-card); border: 1px solid var(--border); width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; }
        #themeToggle:hover { transform: rotate(15deg); border-color: var(--primary); color: var(--primary); }
        .scrollable::-webkit-scrollbar { width: 8px; }
        .scrollable::-webkit-scrollbar-track { background: transparent; }
        .scrollable::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .scrollable::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

        @media (max-width:768px){
            .top-header { padding: 0 20px; }
            .scrollable { padding: 20px; }
            .sidebar { position: fixed; left: -280px; height: 100%; top: 0; }
            body.mobile-open .sidebar { transform: translateX(280px); box-shadow: 0 0 40px rgba(0,0,0,0.3); }
            body.mobile-open .overlay { display: block; }
            .logo-text, .link-text, .arrow-icon { display: inline !important; opacity: 1 !important; }
            .sidebar-header { justify-content: flex-start !important; padding: 0 24px !important; }
            .profile-info { display: none; }
            .stats-cards { grid-template-columns: repeat(2,1fr); }
            .table-header { flex-direction: column; gap: 12px; align-items: flex-start; }
            .pagination { flex-direction: column; gap: 12px; align-items: flex-start; }
        }
        @media (max-width:480px){
            .stats-cards { grid-template-columns: 1fr; }
            .page-header { flex-direction: column; align-items: flex-start; }
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
                    <span style="font-size: 0.9rem; color: var(--text-muted);">Manage Subcontractors</span>
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
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Subcontractor Management</h1>
                    <p class="page-subtitle">
                        View, manage, and track all your subcontractors in one place.
                        Currently managing <?php echo $total_count; ?> subcontractors.
                    </p>
                </div>
                <div>
                    <a href="add_subcontractor.php" class="btn btn-primary"><i class="ph ph-user-plus"></i> Add New Subcontractor</a>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type == 'success' ? 'success' : 'error'; ?>">
                    <i class="ph <?php echo $msg_type == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card total"><div class="stat-number"><?php echo $total_count; ?></div><div class="stat-label"><i class="ph ph-users-three"></i> Total Subcontractors</div></div>
                <div class="stat-card active"><div class="stat-number"><?php echo $active_count; ?></div><div class="stat-label"><i class="ph ph-check-circle"></i> Active</div></div>
                <div class="stat-card pending"><div class="stat-number"><?php echo $pending_count; ?></div><div class="stat-label"><i class="ph ph-clock"></i> Pending</div></div>
                <div class="stat-card inactive"><div class="stat-number"><?php echo $inactive_count; ?></div><div class="stat-label"><i class="ph ph-x-circle"></i> Inactive</div></div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filters-header">
                    <div class="filters-title">Filter & Search</div>
                    <a href="subcontractor_management.php" class="btn btn-secondary btn-sm"><i class="ph ph-arrow-clockwise"></i> Reset Filters</a>
                </div>
                <form method="GET" action="" class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Search by name, contact, or email..." value="<?php echo e($search); ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select name="status" class="filter-select">
                            <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Specialization</label>
                        <select name="specialization" class="filter-select">
                            <option value="all" <?php echo $specialization_filter == 'all' ? 'selected' : ''; ?>>All Specializations</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?php echo e($spec); ?>" <?php echo $specialization_filter == $spec ? 'selected' : ''; ?>><?php echo e($spec); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Sort By</label>
                        <select name="sort" class="filter-select">
                            <option value="newest" <?php echo $sort_by == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort_by == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name_asc" <?php echo $sort_by == 'name_asc' ? 'selected' : ''; ?>>Name A-Z</option>
                            <option value="name_desc" <?php echo $sort_by == 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                            <option value="rate_high" <?php echo $sort_by == 'rate_high' ? 'selected' : ''; ?>>Highest Rate</option>
                            <option value="rate_low" <?php echo $sort_by == 'rate_low' ? 'selected' : ''; ?>>Lowest Rate</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary"><i class="ph ph-magnifying-glass"></i> Apply Filters</button>
                            <button type="button" class="btn btn-secondary" onclick="exportData()"><i class="ph ph-export"></i> Export</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Table Section -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        Subcontractor List
                        <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: normal; margin-left: 8px;">
                            (Showing <?php echo count($paged_subcontractors); ?> of <?php echo count($subcontractors); ?>)
                        </span>
                    </div>
                    <div class="table-actions">
                        <button class="btn btn-secondary btn-icon" title="Refresh" onclick="window.location.reload()"><i class="ph ph-arrow-clockwise"></i></button>
                        <button class="btn btn-secondary btn-icon" title="Print" onclick="printTable()"><i class="ph ph-printer"></i></button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>PHOTO</th>
                                <th>NID</th>
                                <th>COMPANY</th>
                                <th>CONTACT</th>
                                <th>SPECIALIZATION</th>
                                <th>PROJECT RATE</th>
                                <th>REGISTRATION DATE</th>
                                <th>STATUS</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($paged_subcontractors)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                        <i class="ph ph-users-three" style="font-size: 3rem; margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                                        <div style="font-size: 1.1rem; margin-bottom: 8px;">No subcontractors found</div>
                                        <div style="font-size: 0.9rem; margin-bottom: 16px;">
                                            <?php if ($search || $status_filter != 'all' || $specialization_filter != 'all'): ?>
                                                Try adjusting your filters
                                            <?php else: ?>
                                                <a href="add_subcontractor.php" class="btn btn-primary btn-sm">Add your first subcontractor</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($paged_subcontractors as $sub): 
                                    // Build absolute URL for photo (ensure leading slash)
                                    $photo_url = !empty($sub['photo']) ? '/' . ltrim($sub['photo'], '/') : '';
                                    $photo_exists = false;
                                    if (!empty($sub['photo'])) {
                                        $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($sub['photo'], '/');
                                        $photo_exists = file_exists($full_path);
                                    }
                                ?>
                                    <tr>
                                        <!-- PHOTO column -->
                                        <td>
                                            <?php if ($photo_exists): ?>
                                                <img src="<?php echo e($photo_url); ?>" alt="Photo" class="photo-thumb">
                                            <?php else: ?>
                                                <div class="photo-placeholder"><i class="ph ph-camera"></i></div>
                                            <?php endif; ?>
                                        </td>
                                        <!-- NID column -->
                                        <td><?php echo !empty($sub['nid_number']) ? e($sub['nid_number']) : '<span style="color: var(--text-muted);">—</span>'; ?></td>
                                        <!-- COMPANY column -->
                                        <td>
                                            <div class="company-cell">
                                                <div class="company-avatar"><?php echo strtoupper(substr($sub['company_name'], 0, 2)); ?></div>
                                                <div class="company-info">
                                                    <div class="company-name"><?php echo e($sub['company_name']); ?></div>
                                                    <?php if (!empty($sub['tax_id'])): ?><div class="company-tax">Tax ID: <?php echo e($sub['tax_id']); ?></div><?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <!-- CONTACT column -->
                                        <td>
                                            <div class="contact-info">
                                                <div class="contact-name"><?php echo e($sub['contact_person']); ?></div>
                                                <div class="contact-email"><i class="ph ph-envelope-simple"></i> <?php echo e($sub['email']); ?></div>
                                                <div class="contact-email"><i class="ph ph-phone"></i> <?php echo e($sub['phone']); ?></div>
                                            </div>
                                        </td>
                                        <!-- SPECIALIZATION column -->
                                        <td><div style="font-weight: 500;"><?php echo e($sub['specialization'] ?: 'N/A'); ?></div></td>
                                        <!-- PROJECT RATE column -->
                                        <td><div style="font-weight: 700; color: var(--color-success);">$<?php echo number_format($sub['project_rate'], 2); ?></div></td>
                                        <!-- REGISTRATION DATE column -->
                                        <td>
                                            <div style="font-size: 0.9rem;"><?php echo date('M d, Y', strtotime($sub['registration_date'])); ?></div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                <?php 
                                                    $reg_date = new DateTime($sub['registration_date']);
                                                    $today = new DateTime();
                                                    $interval = $today->diff($reg_date);
                                                    echo $interval->format('%y years, %m months');
                                                ?>
                                            </div>
                                        </td>
                                        <!-- STATUS column -->
                                        <td>
                                            <?php 
                                                $status_class = '';
                                                switch ($sub['status']) {
                                                    case 'Active': $status_class = 'status-active'; break;
                                                    case 'Inactive': $status_class = 'status-inactive'; break;
                                                    case 'Pending': $status_class = 'status-pending'; break;
                                                }
                                            ?>
                                            <div class="status-badge <?php echo $status_class; ?>"><i class="ph ph-circle-fill" style="font-size: 6px;"></i> <?php echo $sub['status']; ?></div>
                                        </td>
                                        <!-- ACTIONS column -->
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn view" onclick="viewSubcontractor(<?php echo $sub['id']; ?>)"><i class="ph ph-eye"></i> View</button>
                                                <button class="action-btn edit" onclick="editSubcontractor(<?php echo $sub['id']; ?>)"><i class="ph ph-pencil-simple"></i> Edit</button>
                                                <button class="action-btn delete" onclick="deleteSubcontractor(<?php echo $sub['id']; ?>)"><i class="ph ph-trash"></i> Delete</button>
                                            </div>
                                            <div style="margin-top: 4px;">
                                                <button class="btn btn-secondary btn-sm" onclick="toggleStatus(<?php echo $sub['id']; ?>)">
                                                    <i class="ph ph-toggle-<?php echo $sub['status'] == 'Active' ? 'right' : 'left'; ?>"></i>
                                                    <?php echo $sub['status'] == 'Active' ? 'Deactivate' : 'Activate'; ?>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></div>
                        <div class="pagination-controls">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="page-btn <?php echo $page == 1 ? 'disabled' : ''; ?>"><i class="ph ph-caret-double-left"></i></a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>" class="page-btn <?php echo $page == 1 ? 'disabled' : ''; ?>"><i class="ph ph-caret-left"></i></a>
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="page-btn <?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                            <?php endfor; ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>" class="page-btn <?php echo $page == $total_pages ? 'disabled' : ''; ?>"><i class="ph ph-caret-right"></i></a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" class="page-btn <?php echo $page == $total_pages ? 'disabled' : ''; ?>"><i class="ph ph-caret-double-right"></i></a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- View Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Subcontractor Details</h3>
                <button class="modal-close" onclick="closeModal('viewModal')"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body" id="viewModalBody"><!-- loaded via AJAX --></div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
                <button class="btn btn-primary" id="editButton" onclick="">Edit Subcontractor</button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')"><i class="ph ph-x"></i></button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px 0;">
                    <i class="ph ph-warning-circle" style="font-size: 4rem; color: var(--color-danger); margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">Are you sure?</h3>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">This action cannot be undone. This will permanently delete the subcontractor and all associated data.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <a href="#" id="confirmDelete" class="btn btn-danger"><i class="ph ph-trash"></i> Delete Permanently</a>
            </div>
        </div>
    </div>

    <script>
        // --- Sidebar Toggle (unchanged) ---
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

        // --- Accordion (unchanged) ---
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

        // --- Profile Dropdown (unchanged) ---
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

        // --- Dark Mode (unchanged) ---
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

        // --- Modal Functions (unchanged) ---
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) closeModal(this.id);
            });
        });

        // --- View Subcontractor (updated to use absolute photo URL) ---
        function viewSubcontractor(id) {
            document.getElementById('viewModalBody').innerHTML = `
                <div style="text-align: center; padding: 40px;">
                    <i class="ph ph-circle-notch ph-spin" style="font-size: 2rem; color: var(--primary);"></i>
                    <p style="margin-top: 16px; color: var(--text-muted);">Loading details...</p>
                </div>
            `;
            openModal('viewModal');
            // Edit button inside modal now points to edit_subcontractor.php
            document.getElementById('editButton').onclick = function() {
                window.location.href = `edit_subcontractor.php?id=${id}`;
            };

            // Fetch data from get_subcontractor.php (you need to create this endpoint)
            fetch(`get_subcontractor.php?id=${id}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    // Ensure photo URL is absolute
                    const photoUrl = data.photo ? '/' + data.photo.replace(/^\/+/, '') : null;
                    document.getElementById('viewModalBody').innerHTML = `
                        <div class="subcontractor-details">
                            <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border);">
                                <div style="width: 60px; height: 60px; border-radius: 12px; overflow: hidden; background: var(--bg-body);">
                                    ${photoUrl ? `<img src="${photoUrl}" style="width: 100%; height: 100%; object-fit: cover;">` : 
                                        `<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; color:var(--text-muted);"><i class="ph ph-camera"></i></div>`}
                                </div>
                                <div>
                                    <h3 style="margin-bottom: 4px;">${data.company_name}</h3>
                                    <div style="color: var(--text-muted); font-size: 0.9rem;">ID: #${data.id} • Registered: ${new Date(data.registration_date).toLocaleDateString()}</div>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 24px;">
                                <div><div style="font-size: 0.85rem; color: var(--text-muted);">Contact Person</div><div style="font-weight: 600;">${data.contact_person}</div></div>
                                <div><div style="font-size: 0.85rem; color: var(--text-muted);">Email</div><div>${data.email}</div></div>
                                <div><div style="font-size: 0.85rem; color: var(--text-muted);">Phone</div><div>${data.phone}</div></div>
                                <div><div style="font-size: 0.85rem; color: var(--text-muted);">Specialization</div><div>${data.specialization || 'N/A'}</div></div>
                                <div><div style="font-size: 0.85rem; color: var(--text-muted);">Tax ID</div><div>${data.tax_id || 'Not provided'}</div></div>
                                <div><div style="font-size: 0.85rem; color: var(--text-muted);">NID Number</div><div>${data.nid_number || 'Not provided'}</div></div>
                                <div><div style="font-size: 0.85rem; color: var(--text-muted);">Project Rate</div><div style="color: var(--color-success); font-weight: 700;">$${parseFloat(data.project_rate).toFixed(2)}</div></div>
                                <div><div style="font-size: 0.85rem; color: var(--text-muted);">Status</div><div class="status-badge ${data.status === 'Active' ? 'status-active' : data.status === 'Inactive' ? 'status-inactive' : 'status-pending'}">${data.status}</div></div>
                                <div><div style="font-size: 0.85rem; color: var(--text-muted);">Created</div><div>${new Date(data.created_at).toLocaleDateString()}</div></div>
                            </div>
                            <div style="margin-bottom: 16px;">
                                <div style="font-size: 0.85rem; color: var(--text-muted);">Address</div>
                                <div style="background: var(--bg-body); padding: 12px; border-radius: 8px; border: 1px solid var(--border);">${data.address || 'Not provided'}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">Notes</div>
                                <div style="background: var(--bg-body); padding: 12px; border-radius: 8px; border: 1px solid var(--border); min-height: 60px;">${data.notes || 'No additional notes'}</div>
                            </div>
                        </div>
                    `;
                })
                .catch(error => {
                    document.getElementById('viewModalBody').innerHTML = `
                        <div style="text-align: center; padding: 40px; color: var(--color-danger);">
                            <i class="ph ph-warning-circle" style="font-size: 2rem;"></i>
                            <p>Failed to load subcontractor details. Please ensure get_subcontractor.php exists and returns valid JSON.</p>
                        </div>
                    `;
                });
        }

        // --- Delete Subcontractor (unchanged) ---
        let currentDeleteId = null;
        function deleteSubcontractor(id) {
            currentDeleteId = id;
            document.getElementById('confirmDelete').href = `?delete_id=${id}`;
            openModal('deleteModal');
        }

        // --- Toggle Status (unchanged) ---
        function toggleStatus(id) {
            if (confirm('Are you sure you want to change the status of this subcontractor?')) {
                window.location.href = `?toggle_status=${id}`;
            }
        }

        // --- Export Function (unchanged) ---
        function exportData() {
            const params = new URLSearchParams(window.location.search);
            const exportUrl = `export_subcontractors.php?${params.toString()}`;
            window.open(exportUrl, '_blank');
        }

        // --- Print Function (unchanged) ---
        function printTable() {
            const printWindow = window.open('', '_blank');
            const tableContent = document.querySelector('.table-container').outerHTML;
            printWindow.document.write(`
                <html><head><title>Subcontractors Report</title>
                <style>body{font-family:Arial,sans-serif;margin:20px;} table{width:100%;border-collapse:collapse;margin:20px 0;} th,td{border:1px solid #ddd;padding:10px;text-align:left;} th{background-color:#f5f5f5;font-weight:bold;} .print-header{text-align:center;margin-bottom:30px;} .print-footer{margin-top:30px;text-align:center;color:#666;}</style>
                </head><body><div class="print-header"><h1>Subcontractors Report</h1><p>Generated on ${new Date().toLocaleDateString()}</p></div>${tableContent}<div class="print-footer"><p>© ${new Date().getFullYear()} NexusAdmin</p></div></body></html>
            `);
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => { printWindow.print(); printWindow.close(); }, 500);
        }

        // --- Edit Subcontractor (redirects to edit_subcontractor.php) ---
        function editSubcontractor(id) {
            window.location.href = `edit_subcontractor.php?id=${id}`;
        }

        // --- Quick Actions (unchanged) ---
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'f') { e.preventDefault(); document.querySelector('input[name="search"]').focus(); }
            if (e.ctrlKey && e.key === 'n') { e.preventDefault(); window.location.href = 'add_subcontractor.php'; }
        });

        // --- Tooltips (unchanged) ---
        function initTooltips() {
            document.querySelectorAll('[title]').forEach(el => {
                el.addEventListener('mouseenter', showTooltip);
                el.addEventListener('mouseleave', hideTooltip);
            });
        }
        function showTooltip(e) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = this.title;
            tooltip.style.cssText = 'position:fixed; background:var(--bg-card); color:var(--text-main); padding:6px 12px; border-radius:6px; font-size:0.8rem; border:1px solid var(--border); box-shadow:var(--shadow); z-index:10000; pointer-events:none; white-space:nowrap;';
            document.body.appendChild(tooltip);
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + rect.width/2 - tooltip.offsetWidth/2 + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
            this.tooltipElement = tooltip;
        }
        function hideTooltip() { if (this.tooltipElement) { this.tooltipElement.remove(); this.tooltipElement = null; } }
        document.addEventListener('DOMContentLoaded', initTooltips);
    </script>
</body>
</html>