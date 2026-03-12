<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Dhaka');

// Auth
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Session hardening
if (!isset($_SESSION['CREATED'])) $_SESSION['CREATED'] = time();
elseif (time() - $_SESSION['CREATED'] > 3600) { session_regenerate_id(true); $_SESSION['CREATED'] = time(); }
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) { session_unset(); session_destroy(); header('Location: login.php'); exit; }
$_SESSION['LAST_ACTIVITY'] = time();

// DB config - change if needed
$DB_HOST='localhost'; $DB_USER='alihairw'; $DB_PASS='x5.H(8xkh3H7EY'; $DB_NAME='alihairw_alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { http_response_code(500); echo 'DB connection error'; exit; }
$mysqli->set_charset('utf8mb4');

// Helpers
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function detectColumn(mysqli $m, array $candidates): ?string {
    $res = @$m->query("SHOW COLUMNS FROM `orders`");
    if (!$res) return null;
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    $res->free();
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return null;
}

function detectOrderColumn(mysqli $m): string {
    $preferred = ['created_at','created','id'];
    $res = @$m->query("SHOW COLUMNS FROM `orders`");
    if ($res) {
        $cols = [];
        while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
        $res->free();
        foreach ($preferred as $p) {
            if (in_array($p, $cols, true)) return $p;
        }
    }
    return 'id';
}

$orderByCol = detectOrderColumn($mysqli);

function fetchAllOrders(mysqli $m, string $orderBy, int $limit = 2000, string $search = ''): array {
    $orders = [];
    $resCols = @$m->query("SHOW COLUMNS FROM `orders`");
    $validCols = [];
    if ($resCols) {
        while ($r = $resCols->fetch_assoc()) $validCols[] = $r['Field'];
        $resCols->free();
    }
    if (!in_array($orderBy, $validCols, true)) $orderBy = 'id';

    // If no search, use simple query with limit
    if ($search === '') {
        $sql = "SELECT * FROM `orders` ORDER BY `{$m->real_escape_string($orderBy)}` DESC LIMIT ?";
        $stmt = $m->prepare($sql);
        if (!$stmt) return $orders;
        $stmt->bind_param('i', $limit);
    } else {
        // Use LIKE against several text columns. Build only for columns likely to exist.
        $likeCols = [];
        $candidates = ['order_number','customer_name','customer_email','product_name','items','customer_phone','phone','mobile','customer_address','address','shipping_address','billing_address'];
        foreach ($candidates as $c) {
            if (in_array($c, $validCols, true)) $likeCols[] = "`$c` LIKE ?";
        }
        // Fallback to searching the whole table via concatenation if no columns matched (unlikely)
        if (empty($likeCols)) {
            $sql = "SELECT * FROM `orders` WHERE CONCAT_WS(' ', " . implode(',', array_map(function($c){ return "`$c`"; }, $candidates)) . ") LIKE ? ORDER BY `{$m->real_escape_string($orderBy)}` DESC LIMIT ?";
            $stmt = $m->prepare($sql);
            if (!$stmt) return $orders;
            $param = "%{$search}%";
            $stmt->bind_param('si', $param, $limit);
        } else {
            $where = implode(' OR ', $likeCols);
            $sql = "SELECT * FROM `orders` WHERE ({$where}) ORDER BY `{$m->real_escape_string($orderBy)}` DESC LIMIT ?";
            $stmt = $m->prepare($sql);
            if (!$stmt) return $orders;
            $param = "%{$search}%";
            // bind the same search param multiple times, then limit
            $types = str_repeat('s', count($likeCols)) . 'i';
            $params = array_fill(0, count($likeCols), $param);
            $params[] = $limit;
            // bind_param requires references
            $refs = [];
            $refs[] = & $types;
            foreach ($params as $k => $v) $refs[] = & $params[$k];
            call_user_func_array([$stmt, 'bind_param'], $refs);
        }
    }

    // If previously prepared without the advanced branch (search empty OR fallback handled), ensure $stmt exists
    if (!isset($stmt)) return $orders;

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $orders[] = $row;
    $stmt->close();
    return $orders;
}

// Pretty items (kept)
function prettyItems(array $orderRow): string {
    if (!empty($orderRow['items'])) {
        $j = $orderRow['items'];
        if (is_string($j)) {
            $decoded = json_decode($j, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $parts = [];
                foreach ($decoded as $it) {
                    $n = $it['name'] ?? ($it['product_name'] ?? 'Item');
                    $q = $it['qty'] ?? ($it['quantity'] ?? 1);
                    $p = $it['unit_price'] ?? $it['price'] ?? null;
                    $parts[] = esc((string)$n) . ' ×' . (int)$q . ($p !== null ? ' @ ' . number_format((float)$p,2) : '');
                }
                return implode('; ', $parts);
            }
        }
    }
    if (!empty($orderRow['product_name'])) return esc($orderRow['product_name']);
    return '';
}

// Small helper to read an order field with fallbacks
function orderField(array $row, array $candidates): string {
    foreach ($candidates as $c) {
        if (isset($row[$c]) && $row[$c] !== '') return (string)$row[$c];
    }
    return '';
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Read search query from GET (Search button issues a GET)
$searchQuery = '';
if (isset($_GET['q'])) {
    $searchQuery = trim((string)$_GET['q']);
}

// Load data (now with optional search)
$orders = fetchAllOrders($mysqli, $orderByCol, 2000, $searchQuery);
$statusCol = detectColumn($mysqli, ['order_status','status','state']);
$amountCol = detectColumn($mysqli, ['total_amount','total_usd','grand_total','amount','total']);
$user_name = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@example.com';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Orders — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#f5f7fb; --panel:#ffffff; --muted:#6b7280; --accent:#0ea5b6; --accent-dark:#0596a6; --text:#071026; --glass: rgba(11,18,34,0.04);
      --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
      font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
    }

    [data-theme="dark"]{
      --bg:#06131a;
      --panel:linear-gradient(180deg,#071824,#0b2a36);
      --panel-solid:#071421;
      --muted:#9fb0bf;
      --accent:#4dd0c8;
      --accent-dark:#1fb7ac;
      --text:#e6eef6;
      --glass: rgba(255,255,255,0.03);
      --card-shadow: 0 8px 28px rgba(0,0,0,0.6);
      --table-head:#072732;
      --table-row:#071927;
      --table-border: rgba(255,255,255,0.04);
      --btn-bg: linear-gradient(180deg,var(--accent),var(--accent-dark));
      --btn-ghost: rgba(255,255,255,0.04);
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
      background-image: var(--panel);
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
      background: linear-gradient(90deg, rgba(6,24,34,0.92), rgba(6,24,34,0.88));
      border-color: rgba(255,255,255,0.04);
      box-shadow: 0 6px 18px rgba(0,0,0,0.6);
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

    body.orders-page { font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial; }
    .content-wrap{padding:18px;background:transparent}
    .notice{padding:10px;border-radius:8px;margin-bottom:12px}
    .notice.ok{background:#ecfdf5;border:1px solid #bbf7d0;color:#064e3b}
    .notice.err{background:#fff1f2;border:1px solid #fecaca;color:#991b1b}

    table.orders-table{
      width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--table-border, #e6e6e6);border-radius:8px;overflow:hidden;
      box-shadow: 0 6px 18px rgba(2,8,15,0.04);
    }
    [data-theme="dark"] table.orders-table{ background: var(--table-row); border-color: var(--table-border); box-shadow: none; }

    thead th{
      background: var(--table-head, #f1f5f9); padding:12px 10px; text-align:left; font-weight:700; font-size:13px; color:var(--muted);
    }
    [data-theme="dark"] thead th{ background: rgba(255,255,255,0.02); color: var(--muted); border-bottom:1px solid rgba(255,255,255,0.03); }

    tbody td{padding:12px 10px;border-top:1px solid rgba(0,0,0,0.04);font-size:14px;vertical-align:top;color:var(--text);}
    [data-theme="dark"] tbody td{ border-top:1px solid rgba(255,255,255,0.03); color:var(--text); }

    .btn-small{padding:6px 10px;border-radius:8px;border:0;cursor:pointer;text-decoration:none;font-weight:700}
    .btn-print {
      background: linear-gradient(180deg,#0b74d1,#065fb0);
      color: #fff;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      border: 0;
      padding: 6px 10px;
      border-radius: 8px;
      font-weight: 700;
      cursor: pointer;
      transition: transform .08s ease, box-shadow .12s ease, opacity .12s ease;
      box-shadow: 0 6px 14px rgba(11,92,160,0.12);
    }
    .btn-print svg { display:block; flex:0 0 16px; color: #fff; }
    .btn-print:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(7,72,128,0.16); }
    .btn-print:focus { outline: 3px solid rgba(11,116,209,0.14); outline-offset: 2px; }
    .btn-print[disabled], .btn-print[aria-disabled="true"] { opacity: 0.56; cursor: not-allowed; transform: none; box-shadow: none; }
    @media (max-width:640px) { .btn-print span { display:none; } .btn-print { padding:6px; gap:6px; } }

    .btn-view{background:#e6eef2;color:#064e3b}
    [data-theme="dark"] .btn-view{background:var(--btn-ghost); color:var(--text); border:1px solid rgba(255,255,255,0.03)}
    .btn-complete{background:#059669;color:#fff}
    [data-theme="dark"] .btn-complete{background:#0ea98f;color:#05261f}
    .delete-btn{background:#fee2e2;color:#991b1b;border:0;padding:6px 10px;border-radius:8px}
    [data-theme="dark"] .delete-btn{background:rgba(255,80,80,0.08); color:#ffb3b3}

    select.status-select{padding:6px;border-radius:6px;border:1px solid rgba(0,0,0,0.06)}
    [data-theme="dark"] select.status-select{background:transparent;border:1px solid rgba(255,255,255,0.04); color:var(--text)}
    td.actions { display:flex; gap:8px; align-items:center; justify-content:flex-start; white-space:nowrap; }
    td.actions form { margin:0; } td.actions .btn-small { display:inline-flex; align-items:center; gap:6px; }

    @media (max-width:800px) {
      td.actions { flex-wrap:wrap; gap:6px; }
      thead th:nth-child(6), tbody td:nth-child(6) { display:none; }
    }

    footer.site-footer{color:var(--muted);text-align:center;margin-top:8px;padding-bottom:18px}

    .search { padding:8px 12px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); width:320px; }
    [data-theme="dark"] .search { background:transparent; border:1px solid rgba(255,255,255,0.04); color:var(--text); }

    .search-row { display:flex; gap:8px; align-items:center; }
    .search-btn { padding:8px 12px; border-radius:8px; border:0; background:linear-gradient(180deg,#0b74d1,#065fb0); color:#fff; font-weight:700; cursor:pointer; }
    .search-btn:disabled { opacity:0.6; cursor:not-allowed; }
  </style>
</head>
<body class="orders-page" data-theme="">
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
      <main class="main content-wrap" aria-live="polite">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div>
            <h1 style="margin:0">Orders</h1>
            <div style="color:var(--muted)">Manage orders — ordered by <?php echo esc($orderByCol); ?></div>
          </div>
          <div style="display:flex;gap:12px;align-items:center">
            <div class="search-row">
              <form method="get" action="orders.php" style="display:flex;gap:8px;align-items:center;margin:0">
                <input id="q" name="q" value="<?php echo esc($searchQuery); ?>" class="search" placeholder="Search by order number, customer, product or email" aria-label="Search orders">
                <button id="searchBtn" class="search-btn" title="Search orders">Search</button>
                <button id="clearBtn" class="search-btn" title="Clear search" style="background:#e6eef2;color:#064e3b" type="button">Clear</button>
              </form>
            </div>
            <div style="font-weight:700;color:var(--text)">Signed in as <?php echo esc($user_name); ?></div>
          </div>
        </div>

        <?php if ($flashSuccess): ?><div class="notice ok"><?php echo esc($flashSuccess); ?></div><?php endif; ?>
        <?php if ($flashError): ?><div class="notice err"><?php echo esc($flashError); ?></div><?php endif; ?>

        <?php if (empty($orders)): ?>
          <div class="notice">No orders found.</div>
        <?php else: ?>
          <table class="orders-table" role="table" aria-label="Orders table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Country</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Placed</th>
                <th>Print</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="ordersBody">
              <?php foreach ($orders as $o):
                $id = (int)($o['id'] ?? 0);
                $order_no = esc($o['order_number'] ?? ('#'.$id));
                $cust = esc($o['customer_name'] ?? $o['customer'] ?? '');
                $email = esc($o['customer_email'] ?? '');
                $phone = orderField($o, ['customer_phone','phone','mobile','customer_mobile']);
                $address = orderField($o, ['customer_address','address','shipping_address','billing_address']);
                $country = orderField($o, ['country','country_code','customer_country']);
                $phoneEsc = $phone !== '' ? esc($phone) : '';
                $amount = isset($amountCol) && isset($o[$amountCol]) ? number_format((float)$o[$amountCol],2) : (isset($o['total_usd']) ? number_format((float)$o['total_usd'],2) : '0.00');
                $placed = esc($o[$orderByCol] ?? '');
                $currentStatus = strtolower($o[$statusCol] ?? ($o['order_status'] ?? 'pending'));
              ?>
              <tr id="order-row-<?php echo $id; ?>">
                <td><strong><?php echo $order_no; ?></strong></td>

                <td><?php echo $cust; ?>
                  <?php if ($email !== ''): ?><div style="color:var(--muted);font-size:13px"><?php echo $email; ?></div><?php endif; ?>
                </td>

                <td><?php echo $phoneEsc !== '' ? $phoneEsc : '<span style="color:var(--muted)">—</span>'; ?></td>

                <td style="max-width:280px;white-space:normal;"><?php echo $address !== '' ? nl2br(esc($address)) : '<span style="color:var(--muted)">—</span>'; ?></td>

                <td><?php echo $country !== '' ? esc($country) : '<span style="color:var(--muted)">—</span>'; ?></td>

                <td style="font-weight:800">$<?php echo $amount; ?></td>

                <td>
                  <form method="post" action="order_status_update.php" style="display:inline">
                    <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">
                    <select name="order_status" class="status-select" onchange="this.form.submit()">
                      <option value="pending" <?php if ($currentStatus === 'pending') echo 'selected'; ?>>Pending</option>
                      <option value="complete" <?php if ($currentStatus === 'complete') echo 'selected'; ?>>Complete</option>
                    </select>
                  </form>
                </td>

                <td><?php echo $placed; ?></td>

                <td>
                  <?php if ($id > 0): ?>
                    <button
                      type="button"
                      class="btn-small btn-print print-btn"
                      data-id="<?php echo $id; ?>"
                      aria-label="Print invoice for order <?php echo esc($order_no); ?>"
                      title="Print invoice"
                    >
                      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 7h-1V3H6v4H5a2 2 0 0 0-2 2v6h4v4h10v-4h4v-6a2 2 0 0 0-2-2zM8 5h8v2H8V5zm8 14H8v-4h8v4z" fill="currentColor"/>
                      </svg>
                      <span>Print</span>
                    </button>
                  <?php else: ?>
                    <button type="button" class="btn-small btn-print" disabled aria-disabled="true" title="No invoice available">No invoice</button>
                  <?php endif; ?>
                </td>

                <td class="actions" role="cell" aria-label="Actions">
                  <a class="btn-small btn-view" href="order_view.php?id=<?php echo $id; ?>">View</a>

                  <?php if ($currentStatus !== 'complete'): ?>
                    <button class="btn-small btn-complete btn-done" data-id="<?php echo $id; ?>" data-csrf="<?php echo esc($csrf); ?>">Mark complete</button>
                    <noscript>
                      <form method="post" action="mark_complete.php" style="display:inline">
                        <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">
                        <button type="submit" class="btn-small btn-complete">Mark complete</button>
                      </form>
                    </noscript>
                  <?php else: ?>
                    <span style="padding:6px 10px;border-radius:8px;background:#ecfdf5;color:#064e3b">Complete</span>
                  <?php endif; ?>

                  <form method="post" action="delete_order.php" style="display:inline" onsubmit="return confirm('Delete this order?');">
                    <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">
                    <button type="submit" class="delete-btn">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </main>
    </div>

    <footer class="footer">© <?php echo date('Y'); ?> Ali Hair Wigs — Admin</footer>
  </div>

<script>
    // Sidebar toggle for mobile
    const sidebar = document.getElementById('sidebar');
    const overlay = document.createElement('div');
    overlay.id = 'overlay';
    overlay.style.display = 'none';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(2,6,23,0.45)';
    overlay.style.zIndex = '110';
    overlay.addEventListener('click', () => toggleSidebar(false));
    document.body.appendChild(overlay);

    const mobileMenuBtn = document.getElementById('mobileMenuBtn');

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

    // Submenu toggling
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

    // Theme persistence
    const themeBtn = document.getElementById('themeBtn');
    function applyTheme(name) {
      if (name === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        themeBtn.setAttribute('aria-pressed', 'true');
        localStorage.setItem('admin_theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
        themeBtn.setAttribute('aria-pressed', 'false');
        localStorage.setItem('admin_theme', 'light');
      }
    }
    const stored = localStorage.getItem('admin_theme');
    applyTheme(stored === 'dark' ? 'dark' : 'light');
    themeBtn?.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
      applyTheme(cur === 'dark' ? 'light' : 'dark');
    });

    // Ensure overlay closes sidebar on resize to desktop
    function onResize(){
      if (window.innerWidth > 960) { toggleSidebar(false); overlay.style.display = 'none'; }
      if (window.innerWidth <= 960) {
        document.querySelectorAll('.mobile-menu-btn').forEach(el => el.style.display = 'inline-flex');
      } else {
        document.querySelectorAll('.mobile-menu-btn').forEach(el => el.style.display = 'none');
      }
    }
    window.addEventListener('resize', onResize);
    onResize();
</script>

<script>
/* Print button behavior (centered popup with fallback) */
function openInvoicePopup(url, width = 900, height = 700) {
  const left = Math.max(0, Math.floor((screen.width - width) / 2));
  const top = Math.max(0, Math.floor((screen.height - height) / 2));
  const features = `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes`;
  const win = window.open(url, '_blank', features);
  if (win && win.focus) {
    try { win.focus(); } catch (e) {}
    return true;
  }
  try { window.open(url, '_blank', 'noopener'); return true; } catch (e) { return false; }
}

document.addEventListener('click', function (e) {
  const btn = e.target.closest('.print-btn');
  if (!btn) return;
  const id = btn.getAttribute('data-id');
  if (!id) { alert('Invoice not available'); return; }

  const url = 'invoice_print.php?id=' + encodeURIComponent(id);

  // UX: temporary busy state
  btn.disabled = true;
  const original = btn.innerHTML;
  btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M12 2v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg><span>Opening...</span>';

  setTimeout(() => {
    const ok = openInvoicePopup(url);
    // restore state
    btn.disabled = false;
    btn.innerHTML = original;
    if (!ok) alert('Unable to open invoice. Please allow popups or try again.');
  }, 120);
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.body.addEventListener('click', async function (e) {
    const btn = e.target.closest('.btn-done');
    if (!btn) return;
    if (btn.disabled) return;
    if (!confirm('Mark this order as complete?')) return;

    const id = btn.getAttribute('data-id');
    const csrf = btn.getAttribute('data-csrf');
    if (!id || !csrf) { alert('Missing data'); return; }

    const original = btn.textContent;
    btn.disabled = true; btn.textContent = 'Working...';

    try {
      const fd = new FormData();
      fd.append('order_id', id);
      fd.append('status', 'complete');
      fd.append('csrf_token', csrf);

      const resp = await fetch('order_status_action.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      if (!resp.ok) { const txt = await resp.text().catch(()=>resp.statusText); throw new Error(txt || resp.statusText); }
      const data = await resp.json().catch(()=>{ throw new Error('Invalid JSON'); });
      if (!data.ok) throw new Error(data.msg || 'Server error');

      // update row
      const row = document.getElementById('order-row-' + id);
      if (row) {
        const sel = row.querySelector('select[name="order_status"]');
        if (sel) { sel.value = 'complete'; }
        const badgeBtn = row.querySelector('.btn-done');
        if (badgeBtn) { badgeBtn.disabled = true; badgeBtn.textContent = 'Complete ✓'; }
      }

      // update dashboard tiles if present
      const k = data.kpi || {};
      if (typeof k.total_income_confirmed !== 'undefined') document.querySelector('#k_total_income')?.textContent = '$' + Number(k.total_income_confirmed).toFixed(2);
      if (typeof k.pending_income !== 'undefined') document.querySelector('#k_pending_income')?.textContent = '$' + Number(k.pending_income).toFixed(2);
      if (typeof k.completed_orders !== 'undefined') document.querySelector('#k_completed_orders')?.textContent = Number(k.completed_orders).toLocaleString();
      if (typeof k.pending_orders !== 'undefined') document.querySelector('#k_pending_orders')?.textContent = Number(k.pending_orders).toLocaleString();

    } catch (err) {
      console.error(err);
      alert('Failed: ' + err.message);
      btn.disabled = false; btn.textContent = original;
    }
  });

  // Client-side search (still useful for quick filtering of the current page)
  const qInput = document.getElementById('q');
  const rowsSelector = '#ordersBody tr';
  function applySearch(term) {
    const q = String(term || '').toLowerCase().trim();
    const rows = document.querySelectorAll(rowsSelector);
    rows.forEach(r => {
      if (!q) { r.style.display=''; return; }
      const text = r.textContent.toLowerCase();
      r.style.display = text.includes(q) ? '' : 'none';
    });
  }

  // input triggers live filtering
  qInput?.addEventListener('input', function(){
    applySearch(this.value);
  });

  // Clear button resets input and either reloads to show full results or clears client-side filter
  document.getElementById('clearBtn')?.addEventListener('click', function(e){
    e.preventDefault();
    qInput.value = '';
    applySearch('');
    qInput.focus();
    // If page was server-filtered, reload without query to show full list
    const params = new URLSearchParams(window.location.search);
    if (params.has('q')) {
      params.delete('q');
      window.location.search = params.toString();
    }
  });

  // allow Enter key to trigger Search (when focused in input)
  qInput?.addEventListener('keydown', function(e){
    if (e.key === 'Enter') {
      // default form submission already handles it (GET)
      return;
    }
    if (e.key === 'Escape') {
      e.preventDefault();
      document.getElementById('clearBtn')?.click();
    }
  });
});
</script>
</body>
</html>
