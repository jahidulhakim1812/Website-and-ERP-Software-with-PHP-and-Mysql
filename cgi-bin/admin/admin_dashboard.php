<?php
declare(strict_types=1);
session_start();

// Local debug: disable in production
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('Asia/Dhaka');

// Require authenticated session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Session hardening
if (!isset($_SESSION['CREATED'])) $_SESSION['CREATED'] = time();
elseif (time() - $_SESSION['CREATED'] > 3600) { session_regenerate_id(true); $_SESSION['CREATED'] = time(); }
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) { session_unset(); session_destroy(); header('Location: login.php'); exit; }
$_SESSION['LAST_ACTIVITY'] = time();

// DB configuration (adjust as needed)
$DB_HOST = 'localhost';
$DB_USER = 'alihairw';
$DB_PASS = 'x5.H(8xkh3H7EY';
$DB_NAME = 'alihairw_alihairwigs';

// DB connection
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo "<h1>Database connection error</h1><p>" . htmlspecialchars($mysqli->connect_error, ENT_QUOTES) . "</p>";
    exit;
}
$mysqli->set_charset('utf8mb4');

// Helpers
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function tableExists(mysqli $m, string $table): bool {
    $t = $m->real_escape_string($table);
    $r = @$m->query("SHOW TABLES LIKE '{$t}'");
    if (!$r) return false;
    $ok = $r->num_rows > 0;
    $r->free();
    return $ok;
}
function safeScalar(mysqli $m, string $sql) {
    $r = @$m->query($sql);
    if (!$r) return null;
    $row = $r->fetch_row();
    $r->free();
    return $row[0] ?? null;
}

// Prepare KPIs with safe defaults
$kpi = [
    'total_orders' => 0,
    'total_income_confirmed' => 0.0,
    'completed_orders' => 0,
    'pending_orders' => 0,
    'pending_income' => 0.0,
    'total_customers' => 0,
    'total_products' => 0,
    'completed_shipping' => 0,
    'pending_shipping' => 0,
    'categories_count' => 0,
    'product_categories_count' => 0
];

if (tableExists($mysqli, 'orders')) {
    $kpi['total_orders'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `orders`") ?? 0);

    $orderCols = [];
    $cr = $mysqli->query("SHOW COLUMNS FROM `orders`");
    if ($cr) {
        while ($c = $cr->fetch_assoc()) $orderCols[] = $c['Field'];
        $cr->free();
    }

    $statusCandidates = ['order_status','status','state'];
    $amountCandidates = ['total_amount','total_usd','grand_total','amount','total'];
    $shipCandidates = ['shipping_status','ship_status','shipment_status','delivery_status','shipped'];

    $statusCol = null; foreach ($statusCandidates as $c) if (in_array($c, $orderCols, true)) { $statusCol = $c; break; }
    $amountCol = null; foreach ($amountCandidates as $c) if (in_array($c, $orderCols, true)) { $amountCol = $c; break; }
    $shipCol = null; foreach ($shipCandidates as $c) if (in_array($c, $orderCols, true)) { $shipCol = $c; break; }

    $completedStatuses = ['completed','done','shipped','delivered','complete'];
    $pendingStatuses = ['pending','new','processing','awaiting','hold','created'];

    if ($statusCol !== null) {
        $s = $mysqli->real_escape_string($statusCol);
        $completed_list = implode("','", array_map('strtolower',$completedStatuses));
        $pending_list = implode("','", array_map('strtolower',$pendingStatuses));
        $kpi['completed_orders'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `orders` WHERE LOWER(`{$s}`) IN ('{$completed_list}')") ?? 0);
        $kpi['pending_orders'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `orders` WHERE LOWER(`{$s}`) IN ('{$pending_list}')") ?? 0);
    }

    if ($amountCol !== null) {
        $a = $mysqli->real_escape_string($amountCol);
        if ($statusCol !== null) {
            $s = $mysqli->real_escape_string($statusCol);
            $completed_list = implode("','", array_map('strtolower',$completedStatuses));
            $pending_list = implode("','", array_map('strtolower',$pendingStatuses));
            $kpi['total_income_confirmed'] = (float)(safeScalar($mysqli, "SELECT IFNULL(SUM(`{$a}`),0) FROM `orders` WHERE LOWER(`{$s}`) IN ('{$completed_list}')") ?? 0.0);
            $kpi['pending_income'] = (float)(safeScalar($mysqli, "SELECT IFNULL(SUM(`{$a}`),0) FROM `orders` WHERE LOWER(`{$s}`) IN ('{$pending_list}')") ?? 0.0);
        } else {
            $kpi['total_income_confirmed'] = (float)(safeScalar($mysqli, "SELECT IFNULL(SUM(`{$a}`),0) FROM `orders`") ?? 0.0);
            $kpi['pending_income'] = 0.0;
        }
    }

    if ($shipCol !== null) {
        $sh = $mysqli->real_escape_string($shipCol);
        $kpi['completed_shipping'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `orders` WHERE LOWER(`{$sh}`) IN ('shipped','delivered','complete')") ?? 0);
        $kpi['pending_shipping'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `orders` WHERE LOWER(`{$sh}`) IN ('pending','ready','awaiting')") ?? 0);
    }

    $customerIdCandidates = ['customer_id','customerid','customer','customer_email','customer_email_address','customer_phone','customer_name','email'];
    $custCol = null;
    foreach ($customerIdCandidates as $c) {
        if (in_array($c, $orderCols, true)) { $custCol = $c; break; }
    }

    if ($custCol !== null) {
        $cc = $mysqli->real_escape_string($custCol);
        $kpi['total_customers'] = (int)(safeScalar($mysqli, "SELECT COUNT(DISTINCT `{$cc}`) FROM `orders` WHERE `{$cc}` IS NOT NULL AND `{$cc}` != ''") ?? 0);
    } else {
        if (tableExists($mysqli, 'customers')) {
            $kpi['total_customers'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `customers`") ?? 0);
        } elseif (tableExists($mysqli, 'users')) {
            $kpi['total_customers'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `users`") ?? 0);
        } else {
            $kpi['total_customers'] = 0;
        }
    }
}

// Products
if (tableExists($mysqli, 'products')) {
    $kpi['total_products'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `products`") ?? 0);
}

// Categories
foreach (['categories','category'] as $t) {
    if (tableExists($mysqli, $t)) {
        $kpi['categories_count'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `{$t}`") ?? 0);
        break;
    }
}
foreach (['product_category','product_categories','product_category_table'] as $t) {
    if (tableExists($mysqli, $t)) {
        $kpi['product_categories_count'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `{$t}`") ?? 0);
        break;
    }
}

if (!tableExists($mysqli, 'orders')) {
    if (tableExists($mysqli, 'customers')) {
        $kpi['total_customers'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `customers`") ?? 0);
    } elseif (tableExists($mysqli, 'users')) {
        $kpi['total_customers'] = (int)(safeScalar($mysqli, "SELECT COUNT(*) FROM `users`") ?? 0);
    }
}

$user_name = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@example.com';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Admin • Ali Hair Wigs</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#f5f7fb; --panel:#ffffff; --muted:#6b7280; --accent:#0ea5b6; --accent-dark:#0596a6; --text:#071026; --glass: rgba(11,18,34,0.04);
      --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
      font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
    }

    [data-theme="dark"]{
      --bg:#071427;
      --panel:linear-gradient(180deg,#071827,#0a2130);
      --panel-solid:#061424;
      --muted:#9aa4b2;
      --accent:#4dd0c8;
      --accent-dark:#1fb7ac;
      --text:#e6eef6;
      --glass: rgba(255,255,255,0.04);
      --card-shadow: 0 8px 24px rgba(2,8,15,0.6);
    }

    html,body{height:100%;margin:0;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
    /* Layout: fixed left sidebar, header sticky aligned with content */
    .wrap{max-width:1200px;margin:0 auto;padding:0 12px 32px;display:block}
    .page-inner{display:block;max-width:1200px;margin:16px auto;padding:12px;box-sizing:border-box}

    /* Sidebar (fixed) */
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

    /* Header: sticky, aligned with main content (not overlapping sidebar) */
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

    /* Header center removed per request (no search button) */

    /* Header right: actions */
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

    /* Main content and footer align to header's content area */
    main.main { margin-left: calc(250px + 32px); padding:12px 0 40px; }
    footer.footer { margin-left: calc(250px + 32px); padding:12px 0 24px; color:var(--muted); text-align:center; }
    @media (max-width:960px){ main.main, footer.footer { margin-left:0; } }

    /* Sidebar internals */
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

    /* Metrics */
    .main{display:flex;flex-direction:column;gap:14px}
    .top-row{display:flex;gap:12px;flex-wrap:wrap}
    .metric{flex:1 1 180px;background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01));padding:14px;border-radius:12px;border:1px solid var(--glass);box-shadow:var(--card-shadow);display:flex;flex-direction:column;justify-content:space-between;min-width:160px;backdrop-filter: blur(6px);}
    .metric .title{font-size:13px;color:var(--muted);font-weight:700}
    .metric .value{font-size:22px;font-weight:900;margin-top:8px}
    .metric .sub{color:var(--muted);font-size:13px;margin-top:6px}
    .grid-rows{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
    @media (max-width:1000px){ .grid-rows{grid-template-columns:repeat(2,1fr)} }
    @media (max-width:640px){ .grid-rows{grid-template-columns:1fr} .metric{flex:1 1 100%} }
  </style>
</head>
<body data-theme="">
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

      <!-- header center (search) removed per request -->

      <div class="hdr-actions" role="toolbar" aria-label="Header actions">
        <!-- New product button removed from header per request -->

        <button id="themeBtn" class="icon-btn" title="Toggle theme" aria-pressed="false" aria-label="Toggle dark mode">🌓</button>

        <!-- Admin/user pill removed from header per request -->

        <a href="logout.php" class="icon-btn" title="Sign out" aria-label="Sign out" onclick="return confirm('Sign out?');">⎋</a>
      </div>
    </header>

    <div class="page-inner">
      <main class="main" aria-live="polite" role="main">
        <div style="height:8px"></div>
        <!-- top metrics row -->
        <div class="top-row" style="margin-top:12px">
          <div class="metric">
            <div class="title">Total Orders</div>
            <div class="value"><?php echo number_format($kpi['total_orders']); ?></div>
            <div class="sub">All orders placed</div>
          </div>

          <div class="metric">
            <div class="title">Total Income</div>
            <div class="value">$<?php echo number_format($kpi['total_income_confirmed'],2); ?></div>
            <div class="sub">Confirmed revenue</div>
          </div>

          <div class="metric">
            <div class="title">Completed Orders</div>
            <div class="value"><?php echo number_format($kpi['completed_orders']); ?></div>
            <div class="sub">Processed / shipped</div>
          </div>

          <div class="metric">
            <div class="title">Pending Orders</div>
            <div class="value"><?php echo number_format($kpi['pending_orders']); ?></div>
            <div class="sub">Awaiting confirmation</div>
          </div>

          <div class="metric">
            <div class="title">Pending Income</div>
            <div class="value">$<?php echo number_format($kpi['pending_income'],2); ?></div>
            <div class="sub">Amount held until confirmation</div>
          </div>

          <div class="metric">
            <div class="title">Total Customers</div>
            <div class="value"><?php echo number_format($kpi['total_customers']); ?></div>
            <div class="sub">Unique customers (from orders)</div>
          </div>
        </div>

        <div class="grid-rows" style="margin-top:12px">
          <div class="metric">
            <div class="title">Total Products</div>
            <div class="value"><?php echo number_format($kpi['total_products']); ?></div>
            <div class="sub">All products in catalog</div>
          </div>

          <div class="metric">
            <div class="title">Categories</div>
            <div class="value"><?php echo number_format($kpi['categories_count']); ?></div>
            <div class="sub">Top-level categories</div>
          </div>

          <div class="metric">
            <div class="title">Product Categories</div>
            <div class="value"><?php echo number_format($kpi['product_categories_count']); ?></div>
            <div class="sub">Product-category mappings</div>
          </div>
        </div>
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

    // Theme persistence
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
