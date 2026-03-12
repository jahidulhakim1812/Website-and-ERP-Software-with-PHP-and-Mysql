<?php
// sliderimages.php
// Place this file in alihairwigs/admin/
// Uses existing DB table `sliderimages`
// Uploaded images saved to alihairwigs/uploads and DB stores 'uploads/filename.ext'

// ---------- SESSION COOKIE PARAMS (must be before session_start) ----------
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',          // use '/' so cookie is sent for whole site; change to '/alihairwigs' if needed
    'domain' => '',         // default (host only)
    'secure' => false,      // true if using HTTPS
    'httponly' => true,
    'samesite' => 'Lax'     // Lax works for most admin forms; use 'None' with secure=true if cross-site contexts needed
]);
session_start();

// ---------- CSRF token (create only when missing) ----------
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$CSRF = $_SESSION['csrf_token'];

// ---------- CONFIG ----------
$DB_DSN = 'mysql:host=localhost;dbname=alihairw_alihairwigs;charset=utf8mb4';
$DB_USER = 'alihairw';
$DB_PASS = 'x5.H(8xkh3H7EY';

$UPLOAD_DIR    = dirname(__DIR__) . '/uploads'; // filesystem path -> alihairwigs/uploads
$UPLOAD_PUBLIC = 'uploads';                     // DB stored path like uploads/filename.jpg
$MAX_FILE_SIZE = 3 * 1024 * 1024;               // 3 MB
$ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

// ---------- BOOTSTRAP: ensure upload dir exists ----------
if (!is_dir($UPLOAD_DIR)) {
    if (!mkdir($UPLOAD_DIR, 0755, true) && !is_dir($UPLOAD_DIR)) {
        http_response_code(500);
        echo "Failed to create upload directory: " . htmlspecialchars($UPLOAD_DIR);
        exit;
    }
}

// ---------- DB CONNECT ----------
try {
    $pdo = new PDO($DB_DSN, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo "DB connect failed: " . htmlspecialchars($e->getMessage());
    exit;
}

// ---------- HELPERS ----------
function flash($k, $v = null) {
    if ($v === null) {
        $m = $_SESSION['flash'][$k] ?? null;
        unset($_SESSION['flash'][$k]);
        return $m;
    }
    $_SESSION['flash'][$k] = $v;
}

function safe_filename($name) {
    $name = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return preg_replace('/_+/', '_', $name);
}

function handle_image_upload($file, $upload_dir, $upload_public, $allowed_mimes, $max_size) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error code ' . $file['error']);
    if ($file['size'] > $max_size) throw new Exception('File too large');

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed_mimes, true)) throw new Exception('Invalid file type');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext === '') {
        $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext = $map[$mime] ?? 'bin';
    }

    $base = 'slider_' . time() . '_' . bin2hex(random_bytes(6));
    $filename = safe_filename($base) . '.' . $ext;
    $target = rtrim($upload_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) throw new Exception('Failed to move uploaded file');

    return rtrim($upload_public, '/') . '/' . $filename;
}

// ---------- ACTIONS (mutations via POST only) ----------
$action = $_REQUEST['action'] ?? '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) throw new Exception('Invalid CSRF token');

        // ADD / EDIT
        if ($action === 'add' || $action === 'edit') {
            $title = trim((string)($_POST['title'] ?? ''));
            $subtitle = trim((string)($_POST['subtitle'] ?? ''));
            $button_text = trim((string)($_POST['button_text'] ?? ''));
            $display_order = $_POST['display_order'] !== '' ? intval($_POST['display_order']) : null;

            if ($title === '') throw new Exception('Title is required');

            $image_url = null;
            if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $maybe = handle_image_upload($_FILES['image'], $UPLOAD_DIR, $UPLOAD_PUBLIC, $ALLOWED_MIMES, $MAX_FILE_SIZE);
                if ($maybe !== null) $image_url = $maybe;
            }

            if ($action === 'add') {
                $sql = "INSERT INTO sliderimages (title, subtitle, button_text, image_url, display_order) VALUES (:title, :subtitle, :button_text, :image_url, :display_order)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':title' => $title,
                    ':subtitle' => $subtitle ?: null,
                    ':button_text' => $button_text ?: null,
                    ':image_url' => $image_url,
                    ':display_order' => $display_order,
                ]);
                flash('success', 'Slider item added');
            } else {
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) throw new Exception('Invalid id');

                if ($image_url !== null) {
                    $old = $pdo->prepare("SELECT image_url FROM sliderimages WHERE id = :id");
                    $old->execute([':id' => $id]);
                    $r = $old->fetch();
                    if ($r && !empty($r['image_url'])) {
                        $oldpath = dirname(__DIR__) . '/' . ltrim($r['image_url'], '/');
                        if (is_file($oldpath)) @unlink($oldpath);
                    }
                }

                $sql = "UPDATE sliderimages SET title = :title, subtitle = :subtitle, button_text = :button_text, display_order = :display_order";
                if ($image_url !== null) $sql .= ", image_url = :image_url";
                $sql .= " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $params = [
                    ':title' => $title,
                    ':subtitle' => $subtitle ?: null,
                    ':button_text' => $button_text ?: null,
                    ':display_order' => $display_order,
                    ':id' => $id,
                ];
                if ($image_url !== null) $params[':image_url'] = $image_url;
                $stmt->execute($params);
                flash('success', 'Slider item updated');
            }

            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        // DELETE (POST)
        if ($action === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');

            $old = $pdo->prepare("SELECT image_url FROM sliderimages WHERE id = :id");
            $old->execute([':id' => $id]);
            $r = $old->fetch();
            if ($r && !empty($r['image_url'])) {
                $oldpath = dirname(__DIR__) . '/' . ltrim($r['image_url'], '/');
                if (is_file($oldpath)) @unlink($oldpath);
            }

            $del = $pdo->prepare("DELETE FROM sliderimages WHERE id = :id");
            $del->execute([':id' => $id]);
            flash('success', 'Slider item deleted');
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        // COPY (POST)
        if ($action === 'copy') {
            $id = intval($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("SELECT title, subtitle, button_text, image_url, display_order FROM sliderimages WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $r = $stmt->fetch();
            if ($r) {
                $stmt = $pdo->prepare("INSERT INTO sliderimages (title, subtitle, button_text, image_url, display_order) VALUES (:title, :subtitle, :button_text, :image_url, :display_order)");
                $stmt->execute([
                    ':title' => $r['title'] . ' (copy)',
                    ':subtitle' => $r['subtitle'],
                    ':button_text' => $r['button_text'],
                    ':image_url' => $r['image_url'],
                    ':display_order' => $r['display_order'],
                ]);
                flash('success', 'Slider item copied');
            }
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }
} catch (Exception $e) {
    flash('error', $e->getMessage());
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ---------- READ ----------
$editing = null;
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $stmt = $pdo->prepare("SELECT * FROM sliderimages WHERE id = :id");
    $stmt->execute([':id' => intval($_GET['edit'])]);
    $editing = $stmt->fetch() ?: null;
}

// listing ordered by display_order then id desc (no created_at required)
$items = $pdo->query("SELECT * FROM sliderimages ORDER BY display_order IS NULL, display_order ASC, id DESC")->fetchAll();

// For header/sidebar user display
function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$user_name = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@example.com';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Slider Images Admin • Ali Hair Wigs</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f5f7fb; --panel:#ffffff; --muted:#6b7280; --accent:#0ea5b6; --accent-dark:#0596a6; --text:#071026; --glass: rgba(11,18,34,0.04);
  --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
  font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
}

/* Dark theme vars */
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
  transition:transform .28s ease, box-shadow .28s ease;
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

/* Header right: actions */
.hdr-actions{display:flex;gap:8px;align-items:center;flex:0 0 auto}
.btn{padding:8px 12px;border-radius:10px;border:0;background:var(--accent);color:#042b2a;font-weight:700;cursor:pointer}
.icon-btn{background:transparent;border:0;padding:8px;border-radius:8px;cursor:pointer;color:var(--muted);font-size:18px;display:inline-flex;align-items:center;justify-content:center}

/* Mobile and responsive behavior */
@media (max-width:1200px){
  header.header{margin-left:calc(250px + 16px)}
}
@media (max-width:960px){
  /* Sidebar hidden off-canvas and becomes full-height panel */
  .sidebar{transform:translateX(-110%);width:320px;border-radius:0;top:0;bottom:0;left:0;box-shadow: 0 18px 48px rgba(2,6,23,0.6)}
  .sidebar.open{transform:translateX(0)}
  header.header{position:fixed;left:0;right:0;top:0;margin-left:0;margin-right:0;height:56px;border-radius:0;padding:8px 12px}
  .page-inner{padding-top:72px}
  .site-sub{display:none}
  .mobile-menu-btn{display:inline-flex}

  /* Ensure main content doesn't hide under fixed header and stay scrollable */
  main.main { margin-left:0;padding:84px 12px 40px; }
  footer.footer { margin-left:0;padding:12px 12px 24px;text-align:center;color:var(--muted); }

  /* Larger touch targets inside sidebar */
  .sidebar .nav a, .sidebar .nav button.menu-item{padding:14px 12px;border-radius:0;font-size:15px}
  .sidebar .footer .btn{padding:12px 14px;border-radius:6px}

  /* Close button inside sidebar for discoverability */
  .sidebar .close-btn { position: absolute; right:12px; top:12px; background:transparent;border:0;font-size:26px;color:var(--muted); padding:6px; border-radius:8px; cursor:pointer; }
  .sidebar .close-btn:focus { outline:2px solid var(--accent); }
}

/* Main content and footer align to header's content area on desktop */
main.main { margin-left: calc(250px + 32px); padding:12px 0 40px; transition: margin-left .2s ease; }
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

/* Form + table basics reused from previous slider UI */
.container{max-width:980px;margin:0;padding:18px}
.card{background:var(--panel);padding:16px;border-radius:8px;border:1px solid var(--glass)}
.form-grid{display:grid;grid-template-columns:1fr 360px;gap:12px;align-items:start}
@media(max-width:900px){
  .form-grid{grid-template-columns:1fr;gap:14px}
  .card{padding:14px}
}
input, textarea, select {width:100%;padding:10px;border:1px solid #e6e9ef;border-radius:8px;background:transparent;color:inherit;box-sizing:border-box}
.label{font-size:13px;color:var(--muted);margin-bottom:6px}
.button{background:var(--accent);color:#fff;padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
.table{width:100%;border-collapse:collapse;margin-top:12px}
.table th,.table td{padding:10px;border-bottom:1px solid rgba(0,0,0,0.06);text-align:left;vertical-align:middle}
.thumbnail{width:120px;height:64px;object-fit:cover;border-radius:6px;border:1px solid rgba(0,0,0,0.06)}
@media (max-width:520px){
  .thumbnail{width:100px;height:56px}
  .table th:nth-child(2), .table td:nth-child(2) { width: 96px; }
}
.actions a{margin-right:8px;color:var(--accent);text-decoration:none}
.flash{padding:10px;border-radius:6px;margin-bottom:12px}
.flash.success{background:#e6f4ff;color:#064e8a}
.flash.error{background:#fff1f0;color:#8a1f11}
.small{font-size:13px;color:var(--muted)}
form.inline { display:inline; margin:0; padding:0; }
form.inline button { background:none;border:none;color:var(--accent);cursor:pointer;padding:0;font-size:14px; }

/* overlay (JS toggles display) */
#overlay{position:fixed;inset:0;background:rgba(2,6,23,0.45);z-index:110;display:none;}

/* Accessibility: reduce motion for users who prefer it */
@media (prefers-reduced-motion: reduce) {
  .sidebar, header.header, main.main { transition: none !important; }
}
</style>
</head>
<body data-theme="">
  <div class="wrap">
    <aside class="sidebar" id="sidebar" aria-label="Sidebar navigation" role="navigation">
      <button class="close-btn" id="sidebarClose" aria-label="Close menu" style="display:none">×</button>
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
      <div class="hdr-left" style="align-items:center">
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

        <div class="card" style="margin-bottom:12px">
          <div class="container">
            <div class="header" style="margin-bottom:12px">
              <h2>Slider Images</h2>
              <div class="small">Manage homepage slider items</div>
            </div>

            <?php if ($m = flash('success')): ?><div class="flash success card"><?php echo htmlspecialchars($m); ?></div><?php endif; ?>
            <?php if ($m = flash('error')): ?><div class="flash error card"><?php echo htmlspecialchars($m); ?></div><?php endif; ?>

            <div class="card" style="margin-bottom:12px">
              <div class="small" style="margin-bottom:8px">Add a new slider item or edit an existing one. Image types: JPG, PNG, WEBP (max 3MB).</div>
              <div class="form-grid">
                <form method="post" enctype="multipart/form-data" class="card" style="padding:12px" novalidate>
                  <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                  <input type="hidden" name="id" value="<?php echo $editing ? (int)$editing['id'] : 0; ?>">
                  <input type="hidden" name="action" value="<?php echo $editing ? 'edit' : 'add'; ?>">

                  <div>
                    <label class="label">Title</label>
                    <input type="text" name="title" required value="<?php echo $editing ? htmlspecialchars($editing['title']) : ''; ?>">
                  </div>

                  <div>
                    <label class="label">Subtitle</label>
                    <textarea name="subtitle" rows="3"><?php echo $editing ? htmlspecialchars($editing['subtitle']) : ''; ?></textarea>
                  </div>

                  <div>
                    <label class="label">Button Text</label>
                    <input type="text" name="button_text" value="<?php echo $editing ? htmlspecialchars($editing['button_text']) : ''; ?>">
                  </div>

                  <div>
                    <label class="label">Display Order (lower = earlier)</label>
                    <input type="number" name="display_order" value="<?php echo $editing && $editing['display_order'] !== null ? (int)$editing['display_order'] : ''; ?>">
                  </div>

                  <div>
                    <label class="label">Image (optional)</label>
                    <input type="file" name="image" accept="image/*">
                  </div>

                  <div style="margin-top:10px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                    <button class="button" type="submit"><?php echo $editing ? 'Update Slider' : 'Add Slider'; ?></button>
                    <?php if ($editing): ?><a href="<?php echo strtok($_SERVER['REQUEST_URI'], '?'); ?>" style="margin-left:12px;color:#666">Cancel</a><?php endif; ?>
                  </div>

                  <div style="margin-top:8px;color:#666;font-size:13px">Saved image path example: <strong><?php echo htmlspecialchars($UPLOAD_PUBLIC); ?>/slider_1600000000_xxx.jpg</strong></div>
                </form>

                <div class="card" style="padding:12px">
                  <h4 style="margin:0 0 8px 0">Preview</h4>
                  <?php if ($editing && !empty($editing['image_url'])): ?>
                    <img src="../<?php echo htmlspecialchars(ltrim($editing['image_url'], '/')); ?>" class="thumbnail" alt="">
                  <?php else: ?>
                    <div style="color:#999">No image selected</div>
                  <?php endif; ?>
                  <div style="margin-top:12px">
                    <div><strong><?php echo $editing ? htmlspecialchars($editing['title']) : 'Title preview'; ?></strong></div>
                    <div class="small"><?php echo $editing ? htmlspecialchars($editing['subtitle']) : 'Subtitle preview'; ?></div>
                    <div style="margin-top:8px"><button class="button" disabled><?php echo $editing ? htmlspecialchars($editing['button_text'] ?: 'Button') : 'Button'; ?></button></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card">
              <h4 style="margin:0 0 12px 0">Existing Slider Items</h4>
              <table class="table" role="table" aria-label="Existing slider items">
                <thead>
                  <tr>
                    <th style="width:56px">ID</th>
                    <th>Image</th>
                    <th>Title</th>
                    <th>Subtitle</th>
                    <th style="width:96px">Order</th>
                    <th style="width:180px">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($items)): ?>
                    <tr><td colspan="7" class="small">No slider items yet.</td></tr>
                  <?php else: foreach ($items as $row): ?>
                    <tr>
                      <td><?php echo (int)$row['id']; ?></td>
                      <td>
                        <?php
                          $imgUrl = $row['image_url'] ?? '';
                          $imgFs = dirname(__DIR__) . '/' . ltrim($imgUrl, '/');
                        ?>
                        <?php if (!empty($imgUrl) && is_file($imgFs)): ?>
                          <img src="../<?php echo htmlspecialchars(ltrim($imgUrl, '/')); ?>" class="thumbnail" alt="">
                        <?php elseif (!empty($imgUrl)): ?>
                          <img src="../<?php echo htmlspecialchars(ltrim($imgUrl, '/')); ?>" class="thumbnail" alt="" onerror="this.style.opacity=.4">
                        <?php else: ?>
                          <span style="color:#999">—</span>
                        <?php endif; ?>
                      </td>
                      <td><?php echo htmlspecialchars($row['title']); ?></td>
                      <td style="max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo htmlspecialchars($row['subtitle']); ?></td>
                      <td><?php echo $row['display_order'] !== null ? (int)$row['display_order'] : ''; ?></td>
                      <td class="actions">
                        <a href="?edit=<?php echo (int)$row['id']; ?>">Edit</a>

                        <form method="post" class="inline" onsubmit="return confirm('Delete this slider item?');">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                          <button type="submit">Delete</button>
                        </form>

                        <form method="post" class="inline" style="margin-left:8px" onsubmit="return confirm('Copy this slider item?');">
                          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF); ?>">
                          <input type="hidden" name="action" value="copy">
                          <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                          <button type="submit">Copy</button>
                        </form>

                      </td>
                    </tr>
                  <?php endforeach; endif; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>

        <!-- metrics area left empty because this page focuses on slider management -->
      </main>
    </div>

    <footer class="footer">© <?php echo date('Y'); ?> Ali Hair Wigs — Admin</footer>
  </div>

<div id="overlay" aria-hidden="true"></div>

<script>
  // Elements
  const sidebar = document.getElementById('sidebar');
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebarClose = document.getElementById('sidebarClose');
  const themeBtn = document.getElementById('themeBtn');
  const overlay = document.getElementById('overlay');

  // Show/hide overlay and sidebar
  function toggleSidebar(open) {
    if (open) {
      sidebar.classList.add('open');
      overlay.style.display = 'block';
      overlay.setAttribute('aria-hidden', 'false');
      // show close button only on small screens
      if (window.innerWidth <= 960) {
        sidebarClose.style.display = 'inline-flex';
        sidebarClose.focus();
      }
      document.body.style.overflow = 'hidden';
    } else {
      sidebar.classList.remove('open');
      overlay.style.display = 'none';
      overlay.setAttribute('aria-hidden', 'true');
      sidebarClose.style.display = 'none';
      document.body.style.overflow = '';
    }
  }

  // Overlay click hides sidebar
  overlay.addEventListener('click', () => toggleSidebar(false));
  sidebarClose?.addEventListener('click', () => toggleSidebar(false));

  // Mobile menu button
  mobileMenuBtn?.addEventListener('click', () => toggleSidebar(true));

  // Accessibility: close on Escape
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && sidebar.classList.contains('open')) {
      toggleSidebar(false);
    }
  });

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

  // Mobile adjustments: show mobile menu button depending on width
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

  // Prevent body scroll when sidebar open on small screens (mutation observer)
  const observer = new MutationObserver(() => {
    if (sidebar.classList.contains('open')) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
  });
  observer.observe(sidebar, { attributes: true });

  // Ensure overlay covers full area
  function updateOverlay() {
    if (window.innerWidth <= 960) overlay.style.top = '0';
    else overlay.style.top = '';
  }
  window.addEventListener('resize', updateOverlay);
  updateOverlay();
</script>
</body>
</html>
