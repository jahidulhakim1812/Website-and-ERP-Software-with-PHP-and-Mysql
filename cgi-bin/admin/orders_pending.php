<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Dhaka');

// Auth guard
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// DB config
$DB_HOST='localhost'; $DB_USER='alihairw'; $DB_PASS='x5.H(8xkh3H7EY'; $DB_NAME='alihairw_alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { http_response_code(500); echo 'DB connection error'; exit; }
$mysqli->set_charset('utf8mb4');

// Simple esc
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// detect column helper
function detectColumn(mysqli $m, array $candidates): ?string {
    $res = @$m->query("SHOW COLUMNS FROM `orders`");
    if (!$res) return null;
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    $res->free();
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return null;
}

// identify columns
$statusCol = detectColumn($mysqli, ['order_status','status','state']) ?? 'order_status';
$amountCol = detectColumn($mysqli, ['total_amount','total_usd','grand_total','amount','total']);

// fetch pending orders (case-insensitive)
$orders = [];
$sql = "SELECT * FROM `orders` WHERE LOWER(`{$mysqli->real_escape_string($statusCol)}`) = 'pending' ORDER BY `id` DESC LIMIT 1000";
$res = $mysqli->query($sql);
if ($res) {
    while ($r = $res->fetch_assoc()) $orders[] = $r;
    $res->free();
}

// CSRF token
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

// Flash
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);

$user_name = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@example.com';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pending Orders — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
  :root{
    --bg:#f5f7fb; --panel:#ffffff; --muted:#6b7280; --accent:#0ea5b6; --accent-dark:#0596a6; --text:#071026; --glass: rgba(11,18,34,0.04);
    --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
    font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
  }

  [data-theme="dark"]{
    --bg:#06131a; --panel:linear-gradient(180deg,#071824,#0b2a36); --panel-solid:#071421;
    --muted:#9fb0bf; --accent:#4dd0c8; --accent-dark:#1fb7ac; --text:#e6eef6; --glass: rgba(255,255,255,0.03);
    --card-shadow: 0 8px 28px rgba(0,0,0,0.6); --table-head:#072732; --table-row:#071927; --table-border: rgba(255,255,255,0.04);
    --btn-bg: linear-gradient(180deg,var(--accent),var(--accent-dark)); --btn-ghost: rgba(255,255,255,0.04);
  }

  html,body{height:100%;margin:0;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
  .wrap{max-width:1200px;margin:0 auto;padding:0 12px 32px;display:block}
  .page-inner{display:block;max-width:1200px;margin:16px auto;padding:12px;box-sizing:border-box}

  /* Sidebar (fixed) */
  .sidebar {
    position:fixed; left:16px; top:16px; bottom:16px; width:250px; padding:14px; border-radius:12px;
    background: var(--panel-solid, #fff); background-image: var(--panel); border:1px solid var(--glass);
    overflow:auto; box-shadow:var(--card-shadow); color:var(--text); z-index:120;
  }

  /* Header: sticky, aligned with main content (not overlapping sidebar) */
  header.header {
    position:sticky; top:16px; margin-left: calc(250px + 32px); margin-right: 16px; z-index:115;
    display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 16px;
    border-radius:10px; background:linear-gradient(90deg,rgba(255,255,255,0.98),rgba(250,250,252,0.98));
    border:1px solid var(--glass); box-shadow: 0 6px 18px rgba(11,18,34,0.04); backdrop-filter: blur(6px);
    height:64px; box-sizing:border-box;
  }
  [data-theme="dark"] header.header{
    background: linear-gradient(90deg, rgba(6,24,34,0.92), rgba(6,24,34,0.88)); border-color: rgba(255,255,255,0.04);
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

  body.orders-page { font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial; }
  .content-wrap{padding:18px;background:transparent}
  .notice{padding:10px;border-radius:8px;margin-bottom:12px}
  .notice.ok{background:#ecfdf5;border:1px solid #bbf7d0;color:#064e3b}
  .notice.err{background:#fff1f2;border:1px solid #fecaca;color:#991b1b}

  table.orders-table{ width:100%;border-collapse:collapse;background:var(--panel);border:1px solid var(--table-border, #e6e6e6);border-radius:8px;overflow:hidden; box-shadow: 0 6px 18px rgba(2,8,15,0.04); }
  [data-theme="dark"] table.orders-table{ background: var(--table-row); border-color: var(--table-border); box-shadow: none; }

  thead th{ background: var(--table-head, #f1f5f9); padding:12px 10px; text-align:left; font-weight:700; font-size:13px; color:var(--muted); }
  [data-theme="dark"] thead th{ background: rgba(255,255,255,0.02); color: var(--muted); border-bottom:1px solid rgba(255,255,255,0.03); }

  tbody td{padding:12px 10px;border-top:1px solid rgba(0,0,0,0.04);font-size:14px;vertical-align:top;color:var(--text);}
  [data-theme="dark"] tbody td{ border-top:1px solid rgba(255,255,255,0.03); color:var(--text); }

  .btn-small{padding:6px 10px;border-radius:8px;border:0;cursor:pointer;text-decoration:none;font-weight:700}
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
    thead th:nth-child(8), tbody td:nth-child(8) { display:none; }
  }

  footer.site-footer{color:var(--muted);text-align:center;margin-top:8px;padding-bottom:18px}
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
            <h1 style="margin:0">Pending Orders</h1>
            <div style="color:var(--muted)">Orders with status pending</div>
          </div>
          <div style="display:flex;gap:8px;align-items:center">
            <input id="q" class="search" placeholder="Search by order number, customer or product" style="padding:8px;border-radius:8px;border:1px solid #e6eef2;width:320px">
          </div>
        </div>

        <?php if ($flash): ?><div class="notice ok"><?php echo esc($flash); ?></div><?php endif; ?>

        <?php if (empty($orders)): ?>
          <div class="notice">No pending orders found.</div>
        <?php else: ?>
          <table class="orders-table" role="table" aria-label="Pending orders">
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
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="ordersBody">
              <?php foreach ($orders as $o):
                $id = (int)($o['id'] ?? 0);
                $order_no = esc($o['order_number'] ?? ('#'.$id));
                $cust = esc($o['customer_name'] ?? $o['customer'] ?? '');
                $email = esc($o['customer_email'] ?? '');
                $phone = esc($o['customer_phone'] ?? $o['phone'] ?? '');
                $address = esc($o['customer_address'] ?? $o['address'] ?? $o['shipping_address'] ?? '');
                $country = esc($o['country'] ?? $o['country_code'] ?? '');
                $amount = isset($amountCol) && isset($o[$amountCol]) ? number_format((float)$o[$amountCol],2) : (isset($o['total_usd']) ? number_format((float)$o['total_usd'],2) : '0.00');
                $placed = esc($o['created_at'] ?? $o['created'] ?? '');
                $currentStatus = strtolower((string)($o[$statusCol] ?? ($o['order_status'] ?? 'pending')));
              ?>
              <tr id="order-row-<?php echo $id; ?>">
                <td><strong><?php echo $order_no; ?></strong></td>

                <td>
                  <?php echo $cust; ?>
                  <?php if ($email !== ''): ?><div style="color:var(--muted);font-size:13px"><?php echo $email; ?></div><?php endif; ?>
                </td>

                <td><?php echo $phone !== '' ? $phone : '<span style="color:var(--muted)">—</span>'; ?></td>

                <td style="max-width:260px;white-space:normal;"><?php echo $address !== '' ? nl2br($address) : '<span style="color:var(--muted)">—</span>'; ?></td>

                <td><?php echo $country !== '' ? $country : '<span style="color:var(--muted)">—</span>'; ?></td>

                <td style="font-weight:800">$<?php echo $amount; ?></td>

                <td>
                  <form method="post" action="order_status_update.php" style="margin:0">
                    <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">
                    <select name="order_status" class="status-select" onchange="this.form.submit()">
                      <option value="pending" <?php if ($currentStatus === 'pending') echo 'selected'; ?>>Pending</option>
                      <option value="complete" <?php if ($currentStatus === 'complete') echo 'selected'; ?>>Complete</option>
                    </select>
                  </form>
                </td>

                <td><?php echo $placed; ?></td>

                <td class="actions" role="cell">
                  <?php if ($currentStatus !== 'complete'): ?>
                    <button class="btn-small btn-complete" data-id="<?php echo $id; ?>" data-csrf="<?php echo esc($csrf); ?>">Mark complete</button>
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
  // Sidebar toggle and overlay
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

  // Submenu toggling and keyboard support
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

  // Ensure orders submenu is expanded and pending link is active
  document.addEventListener('DOMContentLoaded', function(){
    const ordersSub = document.getElementById('orders-sub');
    if (ordersSub) ordersSub.style.display = 'flex';
    const pendingLink = document.querySelector('#orders-sub a[href="orders_pending.php"]');
    if (pendingLink) pendingLink.classList.add('active');
  });

  // Theme toggle: updates data-theme and persists
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

  // Close sidebar on desktop resize
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

  // Mark complete AJAX
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.btn-complete');
    if (!btn) return;
    if (!confirm('Mark this order as complete?')) return;
    btn.disabled = true;
    const id = btn.getAttribute('data-id');
    const csrf = btn.getAttribute('data-csrf');
    const original = btn.innerHTML;
    btn.innerHTML = 'Working...';

    try {
      const fd = new FormData();
      fd.append('order_id', id);
      fd.append('status', 'complete');
      fd.append('csrf_token', csrf);

      const resp = await fetch('order_status_action.php', { method: 'POST', body: fd, credentials: 'same-origin' });
      if (!resp.ok) { const txt = await resp.text().catch(()=>resp.statusText); throw new Error(txt || resp.statusText); }
      const data = await resp.json().catch(()=>{ throw new Error('Invalid JSON'); });
      if (!data.ok) throw new Error(data.msg || 'Server error');

      // update row select to complete
      const row = document.getElementById('order-row-' + id);
      if (row) {
        const sel = row.querySelector('select[name="order_status"]');
        if (sel) sel.value = 'complete';
        btn.textContent = 'Complete ✓';
      }
      alert('Order marked complete');
    } catch (err) {
      console.error(err);
      alert('Failed: ' + err.message);
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });

  // Client-side search
  document.getElementById('q')?.addEventListener('input', function(){
    const q = String(this.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#ordersBody tr');
    rows.forEach(r => {
      if (!q) { r.style.display=''; return; }
      const text = r.textContent.toLowerCase();
      r.style.display = text.includes(q) ? '' : 'none';
    });
  });
</script>
</body>
</html>
