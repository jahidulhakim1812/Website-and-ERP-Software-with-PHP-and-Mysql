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

// DB config - adjust to your environment
$DB_HOST = 'localhost';
$DB_USER = 'alihairw';
$DB_PASS = 'x5.H(8xkh3H7EY';
$DB_NAME = 'alihairw_alihairwigs';
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) { http_response_code(500); echo 'DB connection error'; exit; }
$mysqli->set_charset('utf8mb4');

// Helpers
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function flash(string $k, string $m) { $_SESSION[$k] = $m; }

// CSRF
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];

// paths for uploads and public references - adjust if needed
$uploadDir = __DIR__ . '/admin/images';
$publicDir = 'admin/images';
if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

// load current user info for header
$user_name  = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@example.com';

// read product id
$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if ($id <= 0) { flash('flash_error','Invalid product id.'); header('Location: products_manage.php'); exit; }

// load product
$stmt = $mysqli->prepare("SELECT id, name, description, price, image_placeholder, category FROM products WHERE id = ? LIMIT 1");
if (!$stmt) { flash('flash_error','DB prepare failed.'); header('Location: products_manage.php'); exit; }
$stmt->bind_param('i', $id);
$stmt->execute();
$prod = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$prod) { flash('flash_error','Product not found.'); header('Location: products_manage.php'); exit; }

// handle POST update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // basic CSRF
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        flash('flash_error','Invalid request token.'); header('Location: edit_product.php?id=' . $id); exit;
    }

    $name = trim((string)($_POST['name'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $price = trim((string)($_POST['price'] ?? '0'));
    $category = trim((string)($_POST['category'] ?? ''));

    if ($name === '') { flash('flash_error','Name is required.'); header('Location: edit_product.php?id=' . $id); exit; }

    // image handling: uploaded file OR provided path string
    $newImagePublic = null;

    // prefer uploaded file
    if (!empty($_FILES['image']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $f = $_FILES['image'];
        if ($f['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg','jpeg','png','gif','webp'];
            if (!in_array($ext, $allowed, true)) {
                flash('flash_error', 'Unsupported image format. Allowed: ' . implode(', ', $allowed));
                header('Location: edit_product.php?id=' . $id); exit;
            }
            $safeName = bin2hex(random_bytes(10)) . '.' . $ext;
            $dest = $uploadDir . '/' . $safeName;
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                flash('flash_error','Failed to save uploaded image.'); header('Location: edit_product.php?id=' . $id); exit;
            }
            @chmod($dest, 0644);
            $newImagePublic = $publicDir . '/' . $safeName;
        } else {
            flash('flash_error','Image upload error code: ' . (int)$f['error']);
            header('Location: edit_product.php?id=' . $id); exit;
        }
    }

    // alternative: typed image path/URL
    $image_input = trim((string)($_POST['image_path'] ?? ''));
    if ($image_input !== '') $newImagePublic = $image_input;

    // final image value (fallback to existing)
    $image_placeholder = $newImagePublic ?? $prod['image_placeholder'];

    // update DB (prepared)
    $up = $mysqli->prepare("UPDATE products SET name = ?, description = ?, price = ?, category = ?, image_placeholder = ? WHERE id = ?");
    if (!$up) { flash('flash_error','DB prepare failed.'); header('Location: edit_product.php?id=' . $id); exit; }
    // price may be numeric; we pass it as string and let DB convert, or validate here
    $up->bind_param('ssdssi', $name, $description, $price, $category, $image_placeholder, $id);
    $ok = $up->execute();
    $up->close();

    if ($ok) flash('flash_success','Product updated successfully.');
    else flash('flash_error','Failed to update product.');

    header('Location: products_manage.php'); exit;
}

// small helper to build preview src for current stored image
function build_public_image(string $raw, string $publicDir): string {
    $raw = trim($raw);
    if ($raw === '') return $publicDir . '/no-image.png';
    // if JSON array stored
    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded[0])) $candidate = trim((string)$decoded[0]);
    else $candidate = $raw;
    if (preg_match('#^https?://#i', $candidate)) return $candidate;
    $candidate = preg_replace('#^(\./|/)+#', '', $candidate);
    $base = basename($candidate);
    $candidates = [
        $candidate,
        'admin/' . ltrim($candidate, '/\\'),
        'admin/images/' . $base,
        'images/' . $base,
        'uploads/' . $base
    ];
    foreach ($candidates as $c) {
        if (file_exists(__DIR__ . '/' . $c)) return $c;
    }
    return $candidate; // allow fallback, may still work as public path
}

$currentImagePublic = build_public_image((string)$prod['image_placeholder'], $publicDir);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Edit product #<?php echo esc((string)$prod['id']); ?> — Admin</title>
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
    .page-inner{display:block;max-width:1000px;margin:16px auto;padding:12px;box-sizing:border-box}

    /* Sidebar + header (copied from orders.php for consistent UI) */
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
    .btn-ghost{background:#e6eef2;color:#064e3b}

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

    .card{background:var(--panel);border-radius:12px;padding:18px;border:1px solid var(--glass);box-shadow:var(--card-shadow)}
    label{display:block;margin-top:10px;font-weight:700}
    input[type=text], textarea, input[type=number] { width:100%; padding:10px;margin-top:6px;border:1px solid rgba(0,0,0,0.06);border-radius:8px; box-sizing:border-box }
    input[type=file]{margin-top:8px}
    .row{display:flex;gap:12px}
    .col{flex:1}
    .actions{margin-top:14px}
    img.thumb{max-width:160px;border-radius:6px;border:1px solid rgba(0,0,0,0.04);display:block}
    .muted{color:var(--muted)}
    .nav-group{margin-top:6px}
    .nav-title{font-size:12px;color:var(--muted);text-transform:uppercase;margin-bottom:8px}
    .nav{display:flex;flex-direction:column;gap:6px}
    .nav a, .nav button.menu-item{display:flex;justify-content:space-between;align-items:center;padding:10px;border-radius:8px;text-decoration:none;color:inherit;border:1px solid transparent;background:transparent;cursor:pointer;font-weight:700}
    .submenu{display:none;flex-direction:column;margin-left:8px;margin-top:6px;gap:6px}
    .sidebar .footer{margin-top:auto;font-size:13px;color:var(--muted)}
  </style>
</head>
<body class="orders-page" data-theme="">
  <div class="wrap">
    <aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px">
        <div class="logo" style="width:40px;height:40;font-weight:800">AH</div>
        <div>
          <div style="font-weight:800">Ali Hair Wigs</div>
          <div style="font-size:12px;color:var(--muted)">Control Center</div>
        </div>
      </div>

      <div class="nav-group">
        <div class="nav-title">Navigation</div>
        <nav class="nav" role="navigation" aria-label="Primary">
          <a href="admin_dashboard.php">Overview <span style="color:var(--muted);font-size:13px">Home</span></a>

          <button class="menu-item" data-target="products-sub" aria-expanded="false">Products <span style="color:var(--muted);font-size:13px">Catalog ▾</span></button>
          <div class="submenu" id="products-sub" aria-hidden="true">
            <a href="add_product.php">Add product</a>
            <a href="products_manage.php">Manage products</a>
          </div>

          <button class="menu-item" data-target="categories-sub" aria-expanded="false">Categories <span style="color:var(--muted);font-size:13px">Structure ▾</span></button>
          <div class="submenu" id="categories-sub" aria-hidden="true">
            <a href="category_manage.php">Manage categories</a>
            <a href="product_category_manage.php">Product categories</a>
          </div>

          <a href="sliderimages.php">Home Slider <span style="color:var(--muted);font-size:13px">Manage</span></a>

          <button class="menu-item" data-target="orders-sub" aria-expanded="false">Orders <span style="color:var(--muted);font-size:13px">All ▾</span></button>
          <div class="submenu" id="orders-sub" aria-hidden="true" style="display:flex;flex-direction:column;">
            <a href="orders.php">All orders</a>
            <a href="orders_pending.php">Pending orders</a>
          </div>

          <a href="customers.php">Customers <span style="color:var(--muted);font-size:13px">Manage</span></a>
          <a href="settings.php">Settings <span style="color:var(--muted);font-size:13px">Account</span></a>
        </nav>
      </div>

      <div style="margin-top:auto" class="footer">
        <a href="logout.php" class="btn" style="display:block;text-align:center;margin-top:12px;background:#fff;color:var(--text);border:1px solid var(--glass);" onclick="return confirm('Sign out?');">Logout</a>
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
      <main class="main content-wrap" role="main" aria-live="polite">
        <div class="card" role="region" aria-labelledby="editProductTitle">
          <h2 id="editProductTitle" style="margin:0 0 12px">Edit product #<?php echo esc((string)$prod['id']); ?></h2>

          <?php if (!empty($_SESSION['flash_error'])): ?>
            <div style="background:#fff1f2;border:1px solid #fecaca;padding:10px;border-radius:8px;margin-bottom:12px;color:#991b1b">
              <?php echo esc($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?>
            </div>
          <?php endif; ?>
          <?php if (!empty($_SESSION['flash_success'])): ?>
            <div style="background:#ecfdf5;border:1px solid #bbf7d0;padding:10px;border-radius:8px;margin-bottom:12px;color:#064e3b">
              <?php echo esc($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?>
            </div>
          <?php endif; ?>

          <form method="post" enctype="multipart/form-data" action="edit_product.php?id=<?php echo $prod['id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">

            <label for="name">Name</label>
            <input id="name" name="name" type="text" required value="<?php echo esc((string)$prod['name']); ?>">

            <label for="description">Description</label>
            <textarea id="description" name="description" rows="6"><?php echo esc((string)$prod['description']); ?></textarea>

            <div class="row">
              <div class="col">
                <label for="price">Price (৳)</label>
                <input id="price" name="price" type="text" value="<?php echo esc((string)$prod['price']); ?>">
              </div>
              <div class="col">
                <label for="category">Category</label>
                <input id="category" name="category" type="text" value="<?php echo esc((string)$prod['category']); ?>">
              </div>
            </div>

            <label>Current image</label>
            <div style="display:flex;gap:12px;align-items:center">
              <img src="<?php echo esc($currentImagePublic); ?>" alt="current" class="thumb" onerror="this.onerror=null;this.src='<?php echo esc($publicDir . '/no-image.png'); ?>'">
            </div>

            <label for="image">Upload new image (optional)</label>
            <input id="image" name="image" type="file" accept="image/*">

            <label for="image_path">Or provide image path/URL (optional)</label>
            <input id="image_path" name="image_path" type="text" placeholder="admin/images/yourfile.jpg or https://..." value="">

            <div class="actions">
              <button class="btn" type="submit">Save changes</button>
              <a class="btn-ghost" href="products_manage.php" style="display:inline-block;padding:8px 12px;border-radius:8px;margin-left:8px;text-decoration:none">Cancel</a>
            </div>
          </form>
        </div>
      </main>
    </div>

    <footer class="footer">© <?php echo date('Y'); ?> Ali Hair Wigs — Admin</footer>
  </div>

<script>
  // theme persistence
  const themeBtn = document.getElementById('themeBtn');
  function applyTheme(name) {
    if (name === 'dark') { document.documentElement.setAttribute('data-theme','dark'); themeBtn.setAttribute('aria-pressed','true'); localStorage.setItem('admin_theme','dark'); }
    else { document.documentElement.removeAttribute('data-theme'); themeBtn.setAttribute('aria-pressed','false'); localStorage.setItem('admin_theme','light'); }
  }
  const stored = localStorage.getItem('admin_theme');
  applyTheme(stored === 'dark' ? 'dark' : 'light');
  themeBtn?.addEventListener('click', () => {
    const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    applyTheme(cur === 'dark' ? 'light' : 'dark');
  });

  // mobile sidebar toggle (keeps parity with other admin pages)
  const sidebar = document.getElementById('sidebar');
  const overlay = document.createElement('div');
  overlay.id = 'overlay';
  overlay.style.display = 'none';
  overlay.style.position = 'fixed';
  overlay.style.inset = '0';
  overlay.style.background = 'rgba(2,6,23,0.45)';
  overlay.style.zIndex = '110';
  overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.style.display = 'none'; });
  document.body.appendChild(overlay);
  document.querySelectorAll('.menu-item').forEach(btn => {
    btn.addEventListener('click', () => {
      const target = document.getElementById(btn.dataset.target);
      const expanded = btn.getAttribute('aria-expanded') === 'true';
      btn.setAttribute('aria-expanded', String(!expanded));
      if (target) { target.style.display = expanded ? 'none' : 'flex'; target.setAttribute('aria-hidden', String(expanded)); }
    });
  });
  function onResize() {
    if (window.innerWidth <= 960) document.querySelectorAll('.mobile-menu-btn').forEach(el => el.style.display = 'inline-flex');
    else document.querySelectorAll('.mobile-menu-btn').forEach(el => el.style.display = 'none');
  }
  window.addEventListener('resize', onResize); onResize();
</script>
</body>
</html>
