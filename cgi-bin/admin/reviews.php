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

// DB config
$DB_HOST='localhost'; $DB_USER='alihairw'; $DB_PASS='x5.H(8xkh3H7EY'; $DB_NAME='alihairw_alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { http_response_code(500); echo 'DB connection error'; exit; }
$mysqli->set_charset('utf8mb4');

// Helpers
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function detectColumn(mysqli $m, array $candidates, string $table = 'reviews'): ?string {
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

// Flash messages (for actions like delete)
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Pagination & search
$perPage = 30;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

// Detect likely columns in reviews table (flexible names)
$tbl = 'reviews'; // change if your table name differs
$col_id      = detectColumn($mysqli, ['id','review_id','rid'], $tbl) ?? 'id';
$col_product = detectColumn($mysqli, ['product_id','product','pid'], $tbl);
$col_title   = detectColumn($mysqli, ['title','review_title'], $tbl);
$col_body    = detectColumn($mysqli, ['body','content','review','comment'], $tbl) ?? 'comment';
$col_rating  = detectColumn($mysqli, ['rating','stars','score'], $tbl) ?? 'rating';
$col_name    = detectColumn($mysqli, ['customer_name','name','reviewer_name','author'], $tbl) ?? 'customer_name';
$col_email   = detectColumn($mysqli, ['customer_email','email','reviewer_email'], $tbl) ?? 'customer_email';
$col_date    = detectColumn($mysqli, ['created_at','created','date','posted_at'], $tbl) ?? 'created_at';
$col_status  = detectColumn($mysqli, ['status','approved','state'], $tbl);

// Build WHERE for search: search name, email, body, title, product id
$whereParts = [];
$params = [];
$types = '';

if ($q !== '') {
    $like = '%' . $q . '%';
    $searchCols = [];
    if ($col_name) $searchCols[] = "`{$mysqli->real_escape_string($col_name)}` LIKE ?";
    if ($col_email) $searchCols[] = "`{$mysqli->real_escape_string($col_email)}` LIKE ?";
    if ($col_title) $searchCols[] = "`{$mysqli->real_escape_string($col_title)}` LIKE ?";
    if ($col_body) $searchCols[] = "`{$mysqli->real_escape_string($col_body)}` LIKE ?";
    if ($col_product) $searchCols[] = "`{$mysqli->real_escape_string($col_product)}` LIKE ?";
    if (empty($searchCols)) {
        // fallback — search concatenation
        $searchCols[] = "CONCAT_WS(' ', `{$mysqli->real_escape_string($col_name)}`, `{$mysqli->real_escape_string($col_email)}`, `{$mysqli->real_escape_string($col_body)}`) LIKE ?";
    }
    $whereParts[] = '(' . implode(' OR ', $searchCols) . ')';
    foreach ($searchCols as $i) { $params[] = $like; $types .= 's'; }
}

$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Count total rows
$sqlCount = "SELECT COUNT(*) AS c FROM `{$mysqli->real_escape_string($tbl)}` {$where}";
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

// Fetch reviews page
$selectCols = ["`{$mysqli->real_escape_string($col_id)}` AS id"];
if ($col_product) $selectCols[] = "`{$mysqli->real_escape_string($col_product)}` AS product_id";
if ($col_title) $selectCols[] = "`{$mysqli->real_escape_string($col_title)}` AS title";
$selectCols[] = "`{$mysqli->real_escape_string($col_body)}` AS body";
$selectCols[] = "`{$mysqli->real_escape_string($col_rating)}` AS rating";
$selectCols[] = "`{$mysqli->real_escape_string($col_name)}` AS reviewer";
$selectCols[] = "`{$mysqli->real_escape_string($col_email)}` AS email";
$selectCols[] = "`{$mysqli->real_escape_string($col_date)}` AS created_at";
if ($col_status) $selectCols[] = "`{$mysqli->real_escape_string($col_status)}` AS status";

$selectList = implode(', ', $selectCols);
$sql = "SELECT {$selectList} FROM `{$mysqli->real_escape_string($tbl)}` {$where} ORDER BY `{$mysqli->real_escape_string($col_date)}` DESC LIMIT ? OFFSET ?";

$reviews = [];
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
    while ($row = $res->fetch_assoc()) $reviews[] = $row;
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
  <title>Reviews — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#f5f7fb; --panel:#ffffff; --muted:#6b7280; --accent:#0ea5b6; --accent-dark:#0596a6; --text:#071026; --glass: rgba(11,18,34,0.04);
      --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
      font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
    }
    [data-theme="dark"]{
      --bg:#06131a; --panel:linear-gradient(180deg,#071824,#0b2a36); --muted:#9fb0bf; --accent:#4dd0c8; --accent-dark:#1fb7ac; --text:#e6eef6;
      --glass: rgba(255,255,255,0.03); --card-shadow: 0 8px 28px rgba(0,0,0,0.6); --table-border: rgba(255,255,255,0.04);
    }
    html,body{height:100%;margin:0;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
    .wrap{max-width:1200px;margin:0 auto;padding:0 12px 32px;display:block}
    .page-inner{display:block;max-width:1200px;margin:16px auto;padding:12px;box-sizing:border-box}

    /* Sidebar + header replicated from orders.php for consistent UI */
    .sidebar { position:fixed; left:16px; top:16px; bottom:16px; width:250px; padding:14px; border-radius:12px; background: var(--panel); border:1px solid var(--glass); overflow:auto; box-shadow:var(--card-shadow); color:var(--text); z-index:120; }
    header.header { position:sticky; top:16px; margin-left: calc(250px + 32px); margin-right: 16px; z-index:115; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 16px; border-radius:10px; background:linear-gradient(90deg,rgba(255,255,255,0.98),rgba(250,250,252,0.98)); border:1px solid var(--glass); box-shadow: 0 6px 18px rgba(11,18,34,0.04); backdrop-filter: blur(6px); height:64px; box-sizing:border-box; }
    [data-theme="dark"] header.header{ background: linear-gradient(90deg, rgba(6,24,34,0.92), rgba(6,24,34,0.88)); border-color: rgba(255,255,255,0.04); box-shadow: 0 6px 18px rgba(0,0,0,0.6); }

    .hdr-left{display:flex;gap:12px;align-items:center;min-width:0}
    .brand{display:flex;gap:12px;align-items:center;min-width:0}
    .logo{width:44px;height:44;border-radius:8px;background:linear-gradient(135deg,var(--accent-dark),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;flex:0 0 44px}
    .site-title{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .site-sub{font-size:12px;color:var(--muted);white-space:nowrap}

    .hdr-actions{display:flex;gap:8px;align-items:center;flex:0 0 auto}
    .btn{padding:8px 12px;border-radius:10px;border:0;background:var(--accent);color:#042b2a;font-weight:700;cursor:pointer}
    .icon-btn{background:transparent;border:0;padding:8px;border-radius:8px;cursor:pointer;color:var(--muted);font-size:16px;display:inline-flex;align-items:center;justify-content:center}

    @media (max-width:1200px){ header.header{margin-left:calc(250px + 16px)} }
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

    .content-wrap{padding:18px;background:transparent}
    .muted{color:var(--muted)}

    table.reviews-table{width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--table-border,#e6e6e6);border-radius:8px;overflow:hidden;box-shadow:0 6px 18px rgba(2,8,15,0.04)}
    thead th{background: var(--table-head,#f1f5f9); padding:12px 10px; text-align:left; font-weight:700; font-size:13px; color:var(--muted); }
    tbody td{padding:12px 10px;border-top:1px solid rgba(0,0,0,0.04);font-size:14px;vertical-align:top}
    [data-theme="dark"] tbody td{ border-top:1px solid rgba(255,255,255,0.03); }

    .star { color:#f59e0b; font-weight:700; margin-right:6px }
    .search { padding:8px 12px; border-radius:8px; border:1px solid rgba(0,0,0,0.06); width:360px; }
    [data-theme="dark"] .search { background:transparent; border:1px solid rgba(255,255,255,0.04); color:var(--text); }
    .search-row { display:flex; gap:8px; align-items:center; }
    .search-btn { padding:8px 12px; border-radius:8px; border:0; background:linear-gradient(180deg,#0b74d1,#065fb0); color:#fff; font-weight:700; cursor:pointer; }
    .btn-small{padding:6px 10px;border-radius:8px;border:0;cursor:pointer;text-decoration:none;font-weight:700}
    .btn-delete{background:#fee2e2;color:#991b1b}
    [data-theme="dark"] .btn-delete{background:rgba(255,80,80,0.08); color:#ffb3b3}
    .excerpt{color:var(--muted);font-size:13px;max-width:420px;display:block;white-space:normal}
    .pager{display:flex;gap:8px;align-items:center;margin-top:12px}
    .page-btn{padding:6px 10px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);background:#fff;cursor:pointer}
    @media (max-width:960px){ header.header{left:0;right:0;margin-left:0;margin-right:0;height:56px} main.main{margin-left:0} .sidebar{transform:translateX(-110%)} }
  </style>
</head>
<body class="reviews-page" data-theme="">
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
            <h1 style="margin:0">Customer Reviews</h1>
            <div style="color:var(--muted)">Reviews from table "<?php echo esc($tbl); ?>" — total <?php echo number_format($count); ?>; page <?php echo $page; ?> of <?php echo $totalPages; ?></div>
          </div>

          <div style="display:flex;gap:12px;align-items:center">
            <div class="search-row">
              <form method="get" action="" style="display:flex;gap:8px;align-items:center;margin:0">
                <input id="q" name="q" class="search" placeholder="Search name, email, product id, or text" aria-label="Search reviews" value="<?php echo esc($q); ?>">
                <button class="search-btn" title="Search reviews">Search</button>
                <button id="clearBtn" class="search-btn" title="Clear search" style="background:#e6eef2;color:#064e3b" type="button">Clear</button>
              </form>
            </div>
            <div style="font-weight:700;color:var(--text)">Signed in as <?php echo esc($user_name); ?></div>
          </div>
        </div>

        <?php if ($flashSuccess): ?><div style="padding:10px;border-radius:8px;background:#ecfdf5;border:1px solid #bbf7d0;color:#064e3b;margin-bottom:12px"><?php echo esc($flashSuccess); ?></div><?php endif; ?>
        <?php if ($flashError): ?><div style="padding:10px;border-radius:8px;background:#fff1f2;border:1px solid #fecaca;color:#991b1b;margin-bottom:12px"><?php echo esc($flashError); ?></div><?php endif; ?>

        <?php if (empty($reviews)): ?>
          <div class="notice">No reviews found.</div>
        <?php else: ?>
          <table class="reviews-table" role="table" aria-label="Reviews table">
            <thead>
              <tr>
                <th>#</th>
                <th>Reviewer</th>
                <th>Contact</th>
                <th>Product</th>
                <th>Rating</th>
                <th>Review</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($reviews as $i => $r):
                $idx = ($page - 1) * $perPage + $i + 1;
                $id = (int)($r['id'] ?? 0);
                $rev = esc($r['reviewer'] ?? $r['customer'] ?? '');
                $email = esc($r['email'] ?? '');
                $product = isset($r['product_id']) ? esc((string)$r['product_id']) : '<span class="muted">—</span>';
                $rating = isset($r['rating']) ? (float)$r['rating'] : null;
                $title = isset($r['title']) ? esc($r['title']) : '';
                $body = esc($r['body'] ?? '');
                $created = esc($r['created_at'] ?? '');
                $status = isset($r['status']) ? esc((string)$r['status']) : '';
                $excerpt = $title ? $title . ' — ' . (strlen($body) > 160 ? substr($body,0,157).'...' : $body) : (strlen($body) > 160 ? substr($body,0,157).'...' : $body);
              ?>
              <tr id="rev-<?php echo $id; ?>">
                <td><?php echo $idx; ?></td>
                <td><?php echo $rev ?: '<span class="muted">—</span>'; ?></td>
                <td style="white-space:nowrap"><?php echo $email ?: '<span class="muted">—</span>'; ?></td>
                <td style="white-space:nowrap"><?php echo $product; ?></td>
                <td>
                  <?php if ($rating !== null): ?>
                    <span class="star"><?php echo number_format($rating,1); ?>★</span>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
                <td><span class="excerpt"><?php echo $excerpt ?: '<span class="muted">—</span>'; ?></span></td>
                <td><?php echo $created ?: '<span class="muted">—</span>'; ?></td>
                <td style="white-space:nowrap">
                  <a class="btn-small btn-view" href="review_view.php?id=<?php echo $id; ?>" style="margin-right:8px;background:#e6eef2;color:#064e3b;padding:6px 10px;border-radius:8px;text-decoration:none">View</a>

                  <form method="post" action="review_delete.php" style="display:inline" onsubmit="return confirm('Delete this review?');">
                    <input type="hidden" name="review_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">
                    <button type="submit" class="btn-small btn-delete">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="pager" role="navigation" aria-label="Pagination">
            <form method="get" action="" style="display:inline">
              <input type="hidden" name="q" value="<?php echo esc($q); ?>">
              <button class="page-btn" type="submit" name="page" value="<?php echo max(1, $page-1); ?>" <?php if ($page<=1) echo 'disabled'; ?>>Previous</button>
            </form>

            <div class="muted" style="padding:6px 10px;border-radius:6px;background:#fff;border:1px solid rgba(0,0,0,0.04)">Page <?php echo $page; ?> of <?php echo $totalPages; ?></div>

            <form method="get" action="" style="display:inline">
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

  // Theme persistence (same behavior as orders page)
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
      window.location.search = params.toString();
    } else { q.value = ''; }
  });
</script>
</body>
</html>
