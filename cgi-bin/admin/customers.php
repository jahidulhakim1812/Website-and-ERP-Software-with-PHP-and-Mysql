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

function detectColumn(mysqli $m, array $candidates, string $table = 'orders'): ?string {
    $res = @$m->query("SHOW COLUMNS FROM `{$m->real_escape_string($table)}`");
    if (!$res) return null;
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    $res->free();
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return null;
}

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

// Flash messages
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Pagination & search
$perPage = 40;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Detect likely customer columns in orders table
$col_customer_id    = detectColumn($mysqli, ['customer_id','user_id','buyer_id','customerId','userId']) ?? null;
$col_name           = detectColumn($mysqli, ['customer_name','name','full_name','buyer_name']) ?? detectColumn($mysqli, ['customer','buyer']) ?? 'customer_name';
$col_email          = detectColumn($mysqli, ['customer_email','email','buyer_email']) ?? 'customer_email';
$col_phone          = detectColumn($mysqli, ['customer_phone','phone','mobile','buyer_phone']) ?? 'customer_phone';
$col_address        = detectColumn($mysqli, ['customer_address','address','shipping_address','billing_address']) ?? null;
$col_country        = detectColumn($mysqli, ['country','country_code','customer_country']) ?? null;
$col_order_id       = detectColumn($mysqli, ['id','order_id']) ?? 'id';
$col_order_date     = detectColumn($mysqli, ['created_at','created','order_date','placed_at']) ?? 'created_at';

// Build query to derive distinct customers from orders
$whereParts = [];
$params = [];
$types = '';

if ($q !== '') {
    $like = '%' . $q . '%';
    $searchCols = [];
    if ($col_name) $searchCols[] = "`{$mysqli->real_escape_string($col_name)}` LIKE ?";
    if ($col_email) $searchCols[] = "`{$mysqli->real_escape_string($col_email)}` LIKE ?";
    if ($col_phone) $searchCols[] = "`{$mysqli->real_escape_string($col_phone)}` LIKE ?";
    if ($col_address) $searchCols[] = "`{$mysqli->real_escape_string($col_address)}` LIKE ?";
    $searchCols[] = "`{$mysqli->real_escape_string($col_order_id)}` LIKE ?";
    $whereParts[] = '(' . implode(' OR ', $searchCols) . ')';
    // add params for each search column in same order
    foreach ($searchCols as $i) { $params[] = $like; $types .= 's'; }
    // order id like param already included above as last item (duplicate param handling preserved)
}

$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// group by email when present, otherwise by name + phone
$hasEmailColumn = $col_email && $col_email !== '';
$groupBy = $hasEmailColumn ? "`{$mysqli->real_escape_string($col_email)}`" : "`{$mysqli->real_escape_string($col_name)}`, `{$mysqli->real_escape_string($col_phone)}`";

// Build select list
$selectCols = [];
$selectCols[] = "MIN(`{$mysqli->real_escape_string($col_order_id)}`) AS example_order_id";
$selectCols[] = $col_name ? "`{$mysqli->real_escape_string($col_name)}` AS name" : "'' AS name";
$selectCols[] = $col_email ? "`{$mysqli->real_escape_string($col_email)}` AS email" : "'' AS email";
$selectCols[] = $col_phone ? "`{$mysqli->real_escape_string($col_phone)}` AS phone" : "'' AS phone";
if ($col_address) $selectCols[] = "`{$mysqli->real_escape_string($col_address)}` AS address";
if ($col_country) $selectCols[] = "`{$mysqli->real_escape_string($col_country)}` AS country";
$selectCols[] = "COUNT(*) AS orders_count";
$selectCols[] = "MAX(`{$mysqli->real_escape_string($col_order_date)}`) AS last_order_at";

$selectList = implode(', ', $selectCols);

// count total distinct groups
$sqlCount = "SELECT COUNT(1) AS c FROM (SELECT 1 FROM `orders` {$where} GROUP BY {$groupBy}) AS t";
$count = 0;
if ($stmt = $mysqli->prepare($sqlCount)) {
    if ($params) {
        $refs = [];
        $refs[] = & $types;
        foreach ($params as $k => $v) $refs[] = & $params[$k];
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    $count = (int)($r['c'] ?? 0);
    $stmt->close();
}

// fetch page of customers
$sql = "SELECT {$selectList} FROM `orders` {$where} GROUP BY {$groupBy} ORDER BY MAX(`{$mysqli->real_escape_string($col_order_date)}`) DESC LIMIT ? OFFSET ?";
$customers = [];
if ($stmt = $mysqli->prepare($sql)) {
    $bindTypes = $types . 'ii';
    $paramsForBind = $params;
    $paramsForBind[] = $perPage;
    $paramsForBind[] = $offset;
    $refs = [];
    $refs[] = & $bindTypes;
    foreach ($paramsForBind as $k => $v) $refs[] = & $paramsForBind[$k];
    call_user_func_array([$stmt, 'bind_param'], $refs);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) $customers[] = $row;
    $stmt->close();
}

$totalPages = max(1, (int)ceil(max(0,$count) / $perPage));
$user_name = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@example.com';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Customers — Admin</title>
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

    /* Sidebar unchanged */
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

    /* HEADER AND MAIN: bring them closer to the sidebar by reducing left offsets and gaps */
    /* reduced gap between sidebar and header/main from 32px to 12px for a tighter layout */
    header.header {
      position:sticky;
      top:16px;
      margin-left: calc(250px + 12px);
      margin-right: 12px;
      z-index:115;
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:12px;
      padding:10px 14px;
      border-radius:10px;
      background:linear-gradient(90deg,rgba(255,255,255,0.98),rgba(250,250,252,0.98));
      border:1px solid var(--glass);
      box-shadow: 0 6px 18px rgba(11,18,34,0.04);
      backdrop-filter: blur(6px);
      height:60px;
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
      header.header{margin-left:calc(250px + 12px)}
    }
    @media (max-width:960px){
      .sidebar{transform:translateX(-110%);width:280px;border-radius:0;top:0;bottom:0;left:0}
      .sidebar.open{transform:translateX(0)}
      header.header{position:fixed;left:0;right:0;top:0;margin-left:0;margin-right:0;height:56px}
      .page-inner{padding-top:72px}
      .site-sub{display:none}
      .mobile-menu-btn{display:inline-flex}
    }

    /* Main content moved closer to sidebar same as header */
    main.main { margin-left: calc(250px + 12px); padding:10px 0 36px; }
    footer.footer { margin-left: calc(250px + 12px); padding:10px 0 20px; color:var(--muted); text-align:center; }
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

    .content-wrap{padding:18px;background:transparent}
    .notice{padding:10px;border-radius:8px;margin-bottom:12px}
    .notice.ok{background:#ecfdf5;border:1px solid #bbf7d0;color:#064e3b}
    .notice.err{background:#fff1f2;border:1px solid #fecaca;color:#991b1b}

    table.customers-table{
      width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--table-border, #e6e6e6);border-radius:8px;overflow:hidden;
      box-shadow: 0 6px 18px rgba(2,8,15,0.04);
    }
    [data-theme="dark"] table.customers-table{ background: var(--table-row); border-color: var(--table-border); box-shadow: none; }

    thead th{
      background: var(--table-head, #f1f5f9); padding:12px 10px; text-align:left; font-weight:700; font-size:13px; color:var(--muted);
    }
    [data-theme="dark"] thead th{ background: rgba(255,255,255,0.02); color: var(--muted); border-bottom:1px solid rgba(255,255,255,0.03); }

    tbody td{padding:12px 10px;border-top:1px solid rgba(0,0,0,0.04);font-size:14px;vertical-align:top;color:var(--text);}
    [data-theme="dark"] tbody td{ border-top:1px solid rgba(255,255,255,0.03); color:var(--text); }

    .btn-small{padding:6px 10px;border-radius:8px;border:0;cursor:pointer;text-decoration:none;font-weight:700}
    .btn-view{background:#e6eef2;color:#064e3b}
    [data-theme="dark"] .btn-view{background:var(--btn-ghost); color:var(--text); border:1px solid rgba(255,255,255,0.03)}

    .search { padding:8px 12px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); width:320px; }
    [data-theme="dark"] .search { background:transparent; border:1px solid rgba(255,255,255,0.04); color:var(--text); }

    .search-row { display:flex; gap:8px; align-items:center; }
    .search-btn { padding:8px 12px; border-radius:8px; border:0; background:linear-gradient(180deg,#0b74d1,#065fb0); color:#fff; font-weight:700; cursor:pointer; }
    .muted{color:var(--muted)}
    .pager{display:flex;gap:8px;align-items:center;margin-top:12px}
    .page-btn{padding:6px 10px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);background:#fff;cursor:pointer}
    .page-btn[disabled]{opacity:0.6;cursor:not-allowed}
  </style>
</head>
<body class="customers-page" data-theme="">
  <div class="wrap">
    <aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px">
        <div class="logo" style="width:40px;height:40;border-radius:8px;background:linear-gradient(135deg,var(--accent-dark),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800">AH</div>
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
            <h1 style="margin:0">Customers</h1>
            <div style="color:var(--muted)">Customers derived from orders — total <?php echo number_format($count); ?>; page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
          </div>
          <div style="display:flex;gap:12px;align-items:center">
            <div class="search-row">
              <form method="get" action="customers.php" style="display:flex;gap:8px;align-items:center;margin:0">
                <input id="q" name="q" class="search" placeholder="Search by id, name, email, phone or address" aria-label="Search customers" value="<?php echo esc($q); ?>">
                <button id="searchBtn" class="search-btn" title="Search customers">Search</button>
                <button id="clearBtn" class="search-btn" title="Clear search" style="background:#e6eef2;color:#064e3b" type="button">Clear</button>
              </form>
            </div>
            <div style="font-weight:700;color:var(--text)">Signed in as <?php echo esc($user_name); ?></div>
          </div>
        </div>

        <?php if ($flashSuccess): ?><div style="padding:10px;border-radius:8px;background:#ecfdf5;border:1px solid #bbf7d0;color:#064e3b;margin-bottom:12px"><?php echo esc($flashSuccess); ?></div><?php endif; ?>
        <?php if ($flashError): ?><div style="padding:10px;border-radius:8px;background:#fff1f2;border:1px solid #fecaca;color:#991b1b;margin-bottom:12px"><?php echo esc($flashError); ?></div><?php endif; ?>

        <?php if (empty($customers)): ?>
          <div class="notice">No customers found.</div>
        <?php else: ?>
          <table class="customers-table" role="table" aria-label="Customers table">
            <thead>
              <tr>
                <th>#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Address</th>
                <th>Orders</th>
                <th>Last order</th>
                <th>Example order</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customers as $i => $c):
                $idx = ($page - 1) * $perPage + $i + 1;
                $exampleOrder = (int)($c['example_order_id'] ?? 0);
                $name = esc($c['name'] ?? '');
                $email = esc($c['email'] ?? '');
                $phone = esc($c['phone'] ?? '');
                $address = isset($c['address']) ? esc($c['address']) : '';
                $country = isset($c['country']) ? esc($c['country']) : '';
                $ordersCount = (int)($c['orders_count'] ?? 0);
                $lastOrderAt = esc($c['last_order_at'] ?? '');
              ?>
              <tr id="cust-<?php echo $idx; ?>">
                <td><?php echo $idx; ?></td>
                <td><?php echo $name ?: '<span class="muted">—</span>'; ?></td>
                <td><?php echo $email ?: '<span class="muted">—</span>'; ?></td>
                <td><?php echo $phone ?: '<span class="muted">—</span>'; ?></td>
                <td style="max-width:260px;white-space:normal;"><?php echo $address ?: '<span class="muted">—</span>'; ?></td>
                <td><?php echo $ordersCount; ?></td>
                <td><?php echo $lastOrderAt ?: '<span class="muted">—</span>'; ?></td>
                <td><?php if ($exampleOrder > 0): ?><a href="order_view.php?id=<?php echo $exampleOrder; ?>">#<?php echo $exampleOrder; ?></a><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td style="white-space:nowrap">
                  <?php if ($exampleOrder > 0): ?>
                    <a class="btn-small btn-view" href="order_view.php?id=<?php echo $exampleOrder; ?>" style="margin-right:8px;padding:6px 10px;border-radius:8px;text-decoration:none">View order</a>
                  <?php endif; ?>
                  <form method="get" action="orders.php" style="display:inline">
                    <input type="hidden" name="q" value="<?php echo esc($email ?: $name ?: $phone ?: $exampleOrder); ?>">
                    <button class="btn-small" type="submit" style="background:#fff;border:1px solid var(--glass);padding:6px 10px;border-radius:8px;cursor:pointer">Search orders</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="pager" role="navigation" aria-label="Pagination">
            <form method="get" action="customers.php" style="display:inline">
              <input type="hidden" name="q" value="<?php echo esc($q); ?>">
              <button class="page-btn" type="submit" name="page" value="<?php echo max(1, $page-1); ?>" <?php if ($page<=1) echo 'disabled'; ?>>Previous</button>
            </form>

            <div class="muted" style="padding:6px 10px;border-radius:6px;background:#fff;border:1px solid rgba(0,0,0,0.04)">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>

            <form method="get" action="customers.php" style="display:inline">
              <input type="hidden" name="q" value="<?php echo esc($q); ?>">
              <button class="page-btn" type="submit" name="page" value="<?php echo min($totalPages, $page+1); ?>" <?php if ($page>=$totalPages) echo 'disabled'; ?>>Next</button>
            </form>
          </div>
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
      if (open) { sidebar.classList.add('open'); overlay.style.display = 'block'; }
      else { sidebar.classList.remove('open'); overlay.style.display = 'none'; }
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
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
      });
    });

    // Theme persistence (keeps parity with orders page)
    const themeBtn = document.getElementById('themeBtn');
    function applyTheme(name) {
      if (name === 'dark') { document.documentElement.setAttribute('data-theme', 'dark'); themeBtn.setAttribute('aria-pressed', 'true'); localStorage.setItem('admin_theme', 'dark'); }
      else { document.documentElement.removeAttribute('data-theme'); themeBtn.setAttribute('aria-pressed', 'false'); localStorage.setItem('admin_theme', 'light'); }
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

    // Clear button behavior
    document.getElementById('clearBtn')?.addEventListener('click', function(e){
      e.preventDefault();
      const q = document.getElementById('q');
      if (!q) return;
      const params = new URLSearchParams(window.location.search);
      if (params.has('q')) {
        params.delete('q'); params.delete('page');
        // Redirect preserving other params (if any)
        const newQuery = params.toString();
        window.location.search = newQuery;
      } else { q.value = ''; }
    });
</script>
</body>
</html>
