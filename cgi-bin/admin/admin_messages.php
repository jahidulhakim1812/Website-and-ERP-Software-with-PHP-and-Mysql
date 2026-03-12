<?php
declare(strict_types=1);
session_start();

// Local debug: disable in production
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('Asia/Dhaka');

// Require authenticated session (same as admin dashboard)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Session hardening (same logic as dashboard)
if (!isset($_SESSION['CREATED'])) $_SESSION['CREATED'] = time();
elseif (time() - $_SESSION['CREATED'] > 3600) { session_regenerate_id(true); $_SESSION['CREATED'] = time(); }
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) { session_unset(); session_destroy(); header('Location: login.php'); exit; }
$_SESSION['LAST_ACTIVITY'] = time();

// DB configuration (adjust as needed)
$DB_HOST = 'localhost';
$DB_USER = 'alihairw';
$DB_PASS = 'x5.H(8xkh3H7EY';
$DB_NAME = 'alihairw_alihairwigs';

// Connect
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo "<h1>Database connection error</h1><p>" . htmlspecialchars($mysqli->connect_error, ENT_QUOTES) . "</p>";
    exit;
}
$mysqli->set_charset('utf8mb4');

// Helpers
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// CSRF simple token (stored in session)
if (!isset($_SESSION['admin_csrf'])) $_SESSION['admin_csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['admin_csrf'];

/*
  Actions: delete (POST)
  - requires csrf and numeric id
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $token = $_POST['csrf'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if (!hash_equals($csrf, (string)$token) || $id <= 0) {
        $_SESSION['flash_error'] = 'Invalid delete request.';
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
    $stmt = $mysqli->prepare('DELETE FROM contact_messages WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $deleted = $stmt->affected_rows;
    $stmt->close();
    $_SESSION['flash_success'] = $deleted ? 'Message deleted.' : 'Message not found.';
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

/* Query params: page, q, view */
$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));
$q    = trim((string)($_GET['q'] ?? ''));
$view = (int)($_GET['view'] ?? 0);
$offset = ($page - 1) * $perPage;

/* If viewing single message */
$message = null;
if ($view > 0) {
    $stmt = $mysqli->prepare('SELECT id, session_id, full_name, email, phone, country, business_name, business_role, subject, message, ip, user_agent, created_at FROM contact_messages WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $view);
    $stmt->execute();
    $res = $stmt->get_result();
    $message = $res->fetch_assoc() ?: null;
    $stmt->close();
}

/* Listing: build WHERE and params for search */
$where = '';
$params = [];
$types = '';
if ($q !== '') {
    $where = "WHERE (full_name LIKE ? OR email LIKE ? OR subject LIKE ? OR message LIKE ?)";
    $like = '%' . $q . '%';
    $params = [$like, $like, $like, $like];
    $types = 'ssss';
}

/* count total */
$sqlCount = "SELECT COUNT(*) AS c FROM contact_messages $where";
$stmt = $mysqli->prepare($sqlCount);
if ($where !== '') {
    $bind_names = [];
    $bind_names[] = & $types;
    for ($i = 0; $i < count($params); $i++) $bind_names[] = & $params[$i];
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}
$stmt->execute();
$res = $stmt->get_result();
$total = (int)($res->fetch_assoc()['c'] ?? 0);
$stmt->close();

/* fetch list with pagination */
$sqlList = "SELECT id, full_name, email, subject, phone, country, created_at FROM contact_messages $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
$stmt = $mysqli->prepare($sqlList);
if (!$stmt) {
    http_response_code(500);
    echo 'Query prepare failed.';
    exit;
}

if ($where === '') {
    $stmt->bind_param('ii', $perPage, $offset);
} else {
    $types_extended = $types . 'ii';
    $params_extended = $params;
    $params_extended[] = $perPage;
    $params_extended[] = $offset;
    $bind_args = [];
    $bind_args[] = & $types_extended;
    for ($i = 0; $i < count($params_extended); $i++) $bind_args[] = & $params_extended[$i];
    call_user_func_array([$stmt, 'bind_param'], $bind_args);
}

$stmt->execute();
$list = $stmt->get_result();
$messages = [];
while ($row = $list->fetch_assoc()) $messages[] = $row;
$stmt->close();

function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$pages = (int)ceil(max(1, $total) / $perPage);

/* Flash */
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$user_name = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@example.com';
$connection_error = $mysqli->connect_error ? "DB connect failed: {$mysqli->connect_error}" : null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Admin Messages • Ali Hair Wigs</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#f5f7fb;
      --panel:#ffffff;
      --muted:#6b7280;
      --accent:#0ea5b6;
      --accent-dark:#0596a6;
      --text:#071026;
      --glass: rgba(11,18,34,0.04);
      --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
      --danger:#ef4444;
      --success:#10b981;
      --focus: rgba(59,130,246,0.18);
      font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
    }

    /* Dark theme variables */
    [data-theme="dark"]{
      --bg:#071427;
      --panel:#071827;
      --panel-solid:#061424;
      --panel-muted:#0b2833;
      --muted:#94a3b8;
      --muted-2:#8b98a6;
      --accent:#4dd0c8;
      --accent-dark:#1fb7ac;
      --text:#e6eef6;
      --glass: rgba(255,255,255,0.03);
      --card-shadow: 0 10px 30px rgba(2,8,15,0.6);
      --input-bg: rgba(255,255,255,0.03);
      --border: rgba(255,255,255,0.06);
      --table-head: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));
      --focus: rgba(77,208,200,0.18);
      --danger:#ff7b7b;
      --success:#10b981;
      color-scheme: dark;
    }

    html,body{height:100%;margin:0;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
    .wrap{max-width:1200px;margin:0 auto;padding:0 12px 32px;display:block}
    .page-inner{display:block;max-width:1200px;margin:16px auto;padding:12px;box-sizing:border-box}

    .sidebar {
      position:fixed;
      left:16px;
      top:16px;
      bottom:16px;
      width:250px;
      padding:14px;
      border-radius:12px;
      background: var(--panel-solid, #fff);
      border:1px solid var(--glass);
      overflow:auto;
      box-shadow:var(--card-shadow);
      color:var(--text);
      z-index:120;
    }

    header.header {
      position:sticky;
      top:16px;
      margin-left: calc(250px + 32px);
      margin-right: 16px;
      z-index:115;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      padding:10px 16px;
      border-radius:10px;
      background:linear-gradient(90deg,rgba(255,255,255,0.98),rgba(250,250,252,0.98));
      border:1px solid var(--glass);
      box-shadow: 0 6px 18px rgba(11,18,34,0.04);
      backdrop-filter: blur(6px);
      height:64px;
      box-sizing:border-box;
    }
    [data-theme="dark"] header.header{
      background: linear-gradient(90deg, rgba(6,24,34,0.96), rgba(6,24,34,0.92));
      border: 1px solid var(--border);
      box-shadow: var(--card-shadow);
      color: var(--text);
    }

    .hdr-left{display:flex;gap:12px;align-items:center;min-width:0}
    .brand{display:flex;gap:12px;align-items:center;min-width:0}
    .logo{width:44px;height:44;border-radius:8px;background:linear-gradient(135deg,var(--accent-dark),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;flex:0 0 44px}
    .site-title{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .site-sub{font-size:12px;color:var(--muted);white-space:nowrap}

    .hdr-actions{display:flex;gap:8px;align-items:center;flex:0 0 auto}
    .btn{padding:8px 12px;border-radius:10px;border:0;background:var(--accent);color:#042b2a;font-weight:700;cursor:pointer}
    .icon-btn{background:transparent;border:0;padding:8px;border-radius:8px;cursor:pointer;color:var(--muted);font-size:16px;display:inline-flex;align-items:center;justify-content:center}

    @media (max-width:1200px){
      header.header{margin-left:calc(250px + 16px)}
    }
    @media (max-width:960px){
      .sidebar{transform:translateX(-110%);width:280px;border-radius:0;top:0;bottom:0;left:0}
      .sidebar.open{transform:translateX(0)}
      header.header{position:fixed;left:0;right:0;top:0;margin-left:0;margin-right:0;height:56px}
      .page-inner{padding-top:72px}
      .site-sub{display:none}
      .mobile-menu-btn{display:inline-flex}
    }

    main.main { margin-left: calc(250px + 32px); padding:12px 0 40px; }
    footer.footer { margin-left: calc(250px + 32px); padding:12px 0 24px; color:var(--muted); text-align:center; }
    @media (max-width:960px){ main.main, footer.footer { margin-left:0; } }

    .nav-group{margin-top:6px}
    .nav-title{font-size:12px;color:var(--muted);text-transform:uppercase;margin-bottom:8px}
    .nav{display:flex;flex-direction:column;gap:6px}
    .nav a, .nav button.menu-item{display:flex;justify-content:space-between;align-items:center;padding:10px;border-radius:8px;text-decoration:none;color:inherit;border:1px solid transparent;background:transparent;cursor:pointer;font-weight:700}
    .nav a:hover, .nav button.menu-item:hover{background:rgba(14,165,233,0.03);border-color:var(--glass)}
    .nav a.active, .nav button.menu-item.active{background:linear-gradient(90deg, rgba(124,58,237,0.06), rgba(14,165,233,0.03));color:var(--accent-dark)}
    .submenu{display:none;flex-direction:column;margin-left:8px;margin-top:6px;gap:6px}
    .submenu a{padding:8px;border-radius:6px;text-decoration:none;color:var(--muted);font-weight:600}
    .submenu a:hover{color:var(--accent-dark);background:rgba(14,165,233,0.02)}
    .sidebar .footer{margin-top:auto;font-size:13px;color:var(--muted)}

    table{width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--border);border-radius:8px;overflow:hidden}
    th,td{padding:10px 12px;border-bottom:1px solid rgba(0,0,0,0.06);text-align:left;font-size:14px;color:var(--text)}
    thead th{background:var(--table-head);font-weight:700;color:var(--muted)}
    .btn-ghost{background:var(--panel);border:1px solid var(--border);color:var(--accent);padding:6px 8px;border-radius:8px;text-decoration:none;font-weight:700}
    .muted{color:var(--muted);font-size:13px}
    .card{background:var(--panel);padding:14px;border-radius:12px;border:1px solid var(--glass);box-shadow:var(--card-shadow); color:var(--text)}

    input[type="text"], input[type="search"], textarea, input[type="email"], input[type="tel"] {
      background: #fff;
      border:1px solid #e6eef6;
      color:#111827;
      padding:8px 10px;
      border-radius:8px;
      box-sizing:border-box;
    }
    [data-theme="dark"] input[type="text"], [data-theme="dark"] input[type="search"], [data-theme="dark"] textarea, [data-theme="dark"] input[type="email"], [data-theme="dark"] input[type="tel"] {
      background: var(--input-bg);
      border:1px solid var(--border);
      color: var(--text);
    }
    .search-input { padding:8px 10px; border-radius:8px; width:420px; box-sizing:border-box; }

    button, .btn, .btn-ghost { font-family: inherit; }
    [data-theme="dark"] .btn { background: linear-gradient(180deg, var(--accent), var(--accent-dark)); color: #042b2a; box-shadow: 0 8px 22px rgba(31,183,172,0.08); }
    [data-theme="dark"] .btn-ghost { background: transparent; border: 1px solid rgba(255,255,255,0.04); color: var(--accent); }

    [data-theme="dark"] .icon-btn { color: var(--muted); background: transparent; border: 1px solid transparent; }
    [data-theme="dark"] .icon-btn:hover { background: rgba(255,255,255,0.02); color: var(--text); }

    :focus { outline: 3px solid var(--focus); outline-offset: 3px; }
    [data-theme="dark"] :focus { outline: 3px solid var(--focus); outline-offset: 3px; }

    a { color: var(--accent); }
    [data-theme="dark"] a { color: var(--accent); }

    pre { background:#f8fafc;padding:8px;border-radius:6px;font-size:12px;overflow:auto }
    [data-theme="dark"] pre { background: rgba(255,255,255,0.02); color:var(--muted-2); border:1px solid var(--border); }

    .flash {
      padding:12px;
      border-radius:6px;
      margin-bottom:12px;
      font-weight:600;
      box-shadow: 0 6px 18px rgba(11,18,34,0.03);
    }
    .flash-success { border-left:4px solid var(--success); background: rgba(16,185,129,0.08); color: #064e3b; }
    .flash-error   { border-left:4px solid var(--danger);  background: rgba(239,68,68,0.06); color: #7f1d1d; }

    [data-theme="dark"] .flash-success { background: rgba(16,185,129,0.06); color: #bbf7d0; }
    [data-theme="dark"] .flash-error   { background: rgba(239,68,68,0.04); color: #ffd6d6; }

    .message-body { color: var(--text); line-height:1.5; white-space:pre-wrap; }

    [data-theme="dark"] ::-webkit-scrollbar { width: 10px; height: 10px; }
    [data-theme="dark"] ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.04); border-radius: 8px; }
    [data-theme="dark"] ::-webkit-scrollbar-track { background: transparent; }

    [data-theme="dark"] .site-sub, [data-theme="dark"] .muted { color: rgba(230,238,246,0.65); }
    [data-theme="dark"] tbody tr:hover td { background: rgba(255,255,255,0.01); }

  </style>
</head>

<body>
  <div class="wrap">
    <aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px">
        <div class="logo" style="width:40px;height:40;border-radius:8px;font-weight:800">AH</div>
        <div>
          <div style="font-weight:800">Ali Hair Wigs</div>
          <div style="font-size:12px;color:var(--muted)">Control Center</div>
        </div>
      </div>

     <div class="nav-group">
        <div class="nav-title">Navigation</div>
        <nav class="nav" role="navigation" aria-label="Primary">
          <a href="admin_dashboard.php" class="active">Overview <span style="color:var(--muted);font-size:13px">Home</span></a>

          <button class="menu-item" data-target="products-sub" aria-expanded="false">Products <span style="color:var(--muted);font-size:13px">Catalog ▾</span></button>
          <div class="submenu" id="products-sub" aria-hidden="true">
            <a href="add_product.php">Add product</a>
            <a href="products_manage.php">Manage products</a>
          </div>

          <button class="menu-item" data-target="categories-sub" aria-expanded="false">Categories <span style="color:var(--muted);font-size:13px">Structure ▾</span></button>
          <div class="submenu" id="categories-sub" aria-hidden="true">
            <a href="add_product_category.php">Add Products categories</a>
          </div>

          <a href="sliderimages.php">Home Slider <span style="color:var(--muted);font-size:13px">Manage</span></a>

          <button class="menu-item" data-target="orders-sub" aria-expanded="false">Orders <span style="color:var(--muted);font-size:13px">All ▾</span></button>
          <div class="submenu" id="orders-sub" aria-hidden="true">
            <a href="orders.php">All orders</a>
            <a href="orders_pending.php">Pending orders</a>
          </div>

          <a href="customers.php">Customers <span style="color:var(--muted);font-size:13px">Manage</span></a>
          <a href="reviews.php">Reviews <span style="color:var(--muted);font-size:13px">Customers</span></a>
          <a href="admin_messages.php">Messages <span style="color:var(--muted);font-size:13px">Reply Need</span></a>
        </nav>
      </div>

      <div style="margin-top:auto" class="footer">
        <a class="btn" href="add_product.php" style="display:block;text-align:center;margin-bottom:8px">+ New product</a>
        <a href="logout.php" class="btn" style="display:block;text-align:center;margin-bottom:8px;background:#fff;color:var(--text);border:1px solid var(--glass);" onclick="return confirm('Sign out?');">Logout</a>

        <div style="margin-top:12px;font-size:13px;color:var(--muted)">
          Signed in as <strong><?php echo esc($user_name); ?></strong><br><?php echo esc($user_email); ?>
        </div>
      </div>
    </aside>

    <header class="header" role="banner" aria-label="Top bar">
      <div class="hdr-left">
        <button class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu" title="Open menu" style="display:none">☰</button>
        <div class="brand" aria-hidden="false">
          <div class="logo" aria-hidden="true" style="width:40px;height:40">AH</div>
          <div style="min-width:0">
            <div class="site-title">Ali Hair Wigs</div>
            <div class="site-sub">Admin Console</div>
          </div>
        </div>
      </div>

      <div class="hdr-actions" role="toolbar" aria-label="Header actions">
        <button id="themeBtn" class="icon-btn" title="Toggle theme" aria-pressed="false" aria-label="Toggle dark mode">🌓</button>
        <a href="logout.php" class="icon-btn" title="Sign out" aria-label="Sign out" onclick="return confirm('Sign out?');">⎋</a>
      </div>
    </header>

    <div class="page-inner">
      <main class="main" aria-live="polite" role="main">
        <div style="height:8px"></div>

        <?php if ($flashSuccess): ?>
          <div class="flash flash-success card"><?php echo e($flashSuccess); ?></div>
        <?php endif; ?>
        <?php if ($flashError): ?>
          <div class="flash flash-error card"><?php echo e($flashError); ?></div>
        <?php endif; ?>

        <?php if ($view > 0 && !empty($message)): ?>
          <section class="card" style="margin-bottom:16px">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
              <div>
                <h2 style="margin:0 0 6px;font-size:18px;color:var(--text)"><?php echo e($message['subject']); ?></h2>
                <div class="muted"><?php echo e($message['full_name']); ?> — <?php echo e($message['email']); ?> — <?php echo e($message['phone']); ?></div>
                <div class="muted" style="margin-top:6px;font-size:12px"><?php echo e($message['created_at']); ?> • IP: <?php echo e($message['ip']); ?></div>
              </div>

              <div style="display:flex;gap:8px;align-items:center">
                <form method="post" onsubmit="return confirm('Delete this message?');" style="margin:0">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?php echo (int)$message['id']; ?>">
                  <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                  <button class="btn" style="background:var(--danger);color:#fff;border-radius:8px">Delete</button>
                </form>
                <a href="admin_messages.php" class="btn btn-ghost" style="background:transparent;border:1px solid var(--border);color:var(--text)">Back to list</a>
              </div>
            </div>

            <hr style="margin:12px 0;border:none;border-top:1px solid var(--glass)">

            <div class="message-body"><?php echo e($message['message']); ?></div>

            <div style="margin-top:12px;font-size:13px;color:var(--muted)">
              <div><strong>Business</strong>: <?php echo e($message['business_name']); ?></div>
              <div><strong>Role</strong>: <?php echo e($message['business_role']); ?></div>
              <div style="margin-top:6px"><strong>Session id</strong>: <?php echo e($message['session_id']); ?></div>
            </div>
          </section>
        <?php else: ?>

          <div style="display:flex;gap:12px;align-items:center;margin-bottom:12px">
            <form method="get" style="display:flex;gap:8px;align-items:center">
              <input type="text" name="q" value="<?php echo e($q); ?>" placeholder="Search name, email, subject or message" class="search-input">
              <button class="btn" style="padding:8px 12px;border-radius:8px">Search</button>
            </form>
            <div style="margin-left:auto;color:var(--muted)">Total: <strong><?php echo $total; ?></strong></div>
          </div>

          <section class="card">
            <table>
              <thead>
                <tr>
                  <th style="width:60px">#</th>
                  <th>Name / Email</th>
                  <th>Subject</th>
                  <th>Phone</th>
                  <th>Country</th>
                  <th style="width:160px">Received</th>
                  <th style="width:180px">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($messages)): ?>
                  <tr><td colspan="7" style="padding:18px;text-align:center;color:var(--muted)">No messages found.</td></tr>
                <?php else: foreach ($messages as $m): ?>
                  <tr>
                    <td><?php echo e($m['id']); ?></td>
                    <td>
                      <div style="font-weight:700;color:var(--text)"><?php echo e($m['full_name']); ?></div>
                      <div style="font-size:13px;color:var(--muted)"><?php echo e($m['email']); ?></div>
                    </td>
                    <td style="color:var(--text)"><?php echo e($m['subject']); ?></td>
                    <td style="color:var(--text)"><?php echo e($m['phone']); ?></td>
                    <td style="color:var(--text)"><?php echo e($m['country']); ?></td>
                    <td style="font-size:13px;color:var(--muted)"><?php echo e($m['created_at']); ?></td>
                    <td>
                      <a class="btn-ghost" href="?view=<?php echo (int)$m['id']; ?>" style="margin-right:8px">View</a>

                      <form method="post" style="display:inline" onsubmit="return confirm('Delete this message?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo (int)$m['id']; ?>">
                        <input type="hidden" name="csrf" value="<?php echo e($csrf); ?>">
                        <button style="background:transparent;border:0;color:var(--danger);cursor:pointer;padding:6px 8px">Delete</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($pages > 1): ?>
              <nav style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap" aria-label="Pagination">
                <?php for ($p = 1; $p <= $pages; $p++):
                  $qs = $_GET;
                  $qs['page'] = $p;
                  $qs['q'] = $q;
                  $url = strtok($_SERVER['PHP_SELF'], '?') . '?' . http_build_query($qs);
                ?>
                  <a href="<?php echo e($url); ?>" style="padding:8px 10px;border-radius:8px;text-decoration:none;<?php echo $p===$page ? 'background:var(--accent);color:#042b2a' : 'background:var(--panel);border:1px solid var(--glass);color:var(--muted)' ?>"><?php echo $p; ?></a>
                <?php endfor; ?>
              </nav>
            <?php endif; ?>
          </section>
        <?php endif; ?>

      </main>
    </div>

    <footer class="footer">© <?php echo date('Y'); ?> Ali Hair Wigs — Admin</footer>
  </div>

  <script>
    // Elements
    const sidebar = document.getElementById('sidebar');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const themeBtn = document.getElementById('themeBtn');
    const overlay = document.createElement('div');

    // Overlay for mobile sidebar
    overlay.id = 'overlay';
    overlay.style.display = 'none';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(2,6,23,0.45)';
    overlay.style.zIndex = '110';
    overlay.addEventListener('click', () => toggleSidebar(false));
    document.body.appendChild(overlay);

    // Toggle sidebar (mobile)
    function toggleSidebar(open) {
      if (open) {
        sidebar.classList.add('open');
        overlay.style.display = 'block';
      } else {
        sidebar.classList.remove('open');
        overlay.style.display = 'none';
      }
    }

    mobileMenuBtn?.addEventListener('click', () => toggleSidebar(true));

    // Submenu toggles with ARIA updates + keyboard
    document.querySelectorAll('.menu-item').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));
        if (target) {
          target.style.display = expanded ? 'none' : 'flex';
          target.setAttribute('aria-hidden', String(expanded));
        }
      });
      btn.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          btn.click();
        }
      });
    });

    // Theme handling: persist and toggle
    function setTheme(name) {
      if (name === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        themeBtn.setAttribute('aria-pressed', 'true');
        themeBtn.textContent = '☀️';
        localStorage.setItem('admin_theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
        themeBtn.setAttribute('aria-pressed', 'false');
        themeBtn.textContent = '🌙';
        localStorage.setItem('admin_theme', 'light');
      }
    }

    function initTheme() {
      const stored = localStorage.getItem('admin_theme');
      if (stored === 'dark') {
        setTheme('dark');
      } else if (stored === 'light') {
        setTheme('light');
      } else {
        const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        setTheme(prefersDark ? 'dark' : 'light');
      }
    }

    // Initialize theme on load
    initTheme();

    // Toggle handler
    themeBtn?.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
      setTheme(cur === 'dark' ? 'light' : 'dark');
    });

    // Mobile adjustments
    function onResize() {
      if (window.innerWidth <= 960) {
        document.querySelectorAll('.mobile-menu-btn').forEach(el => el.style.display = 'inline-flex');
      } else {
        document.querySelectorAll('.mobile-menu-btn').forEach(el => el.style.display = 'none');
        toggleSidebar(false);
        overlay.style.display = 'none';
      }
    }
    window.addEventListener('resize', onResize);
    onResize();
  </script>
</body>
</html>
