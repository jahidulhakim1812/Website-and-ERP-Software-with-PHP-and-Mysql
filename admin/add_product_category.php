<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Dhaka');

ini_set('display_errors','0');
error_reporting(E_ALL);

// ---------- CONFIG ----------
$DB_DSN      = 'mysql:host=localhost;dbname=alihairw_alihairwigs;charset=utf8mb4';
$DB_USER     = 'alihairw';
$DB_PASS     = 'x5.H(8xkh3H7EY';
$UPLOAD_DIR  = dirname(__DIR__) . '/uploads'; // filesystem path
$UPLOAD_BASE = 'uploads';                      // stored DB prefix
$MAX_FILE    = 2 * 1024 * 1024;                // 2 MB
$ALLOWED_MIME = ['image/jpeg','image/png','image/webp','image/gif'];
$ADMIN_EMAIL = 'admin@example.com';            // display only

// ---------- BOOTSTRAP ----------
if (!is_dir($UPLOAD_DIR)) {
    if (!mkdir($UPLOAD_DIR, 0755, true) && !is_dir($UPLOAD_DIR)) {
        http_response_code(500);
        echo 'Failed to create upload directory';
        exit;
    }
}

try {
    $pdo = new PDO($DB_DSN, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo 'DB connect failed';
    exit;
}

// Ensure table exists (safe DDL)
$createSQL = <<<SQL
CREATE TABLE IF NOT EXISTS product_category (
  category_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(191) NOT NULL,
  description TEXT NULL,
  image_url VARCHAR(255) NULL,
  sort_order INT NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
$pdo->exec($createSQL);

// ---------- AUTH / CSRF ----------
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$CSRF = $_SESSION['csrf_token'];

// flash helpers
function flash_set(string $k, string $v): void { $_SESSION['flash'][$k] = $v; }
function flash_get(string $k): ?string { $v = $_SESSION['flash'][$k] ?? null; unset($_SESSION['flash'][$k]); return $v; }

// escape
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// ---------- UTILITIES ----------
function safe_filename(string $name): string {
    $n = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $name);
    return preg_replace('/_+/', '_', $n);
}

function detect_mime(string $file): ?string {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if (!$finfo) return null;
    $m = finfo_file($finfo, $file);
    finfo_close($finfo);
    return $m ?: null;
}

function store_uploaded_image(array $file, string $uploadDir, string $uploadBase, array $allowed, int $maxSize): string {
    if (!isset($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload error ' . ($file['error'] ?? ''));
    }
    if ($file['size'] > $maxSize) throw new RuntimeException('File exceeds maximum size');

    $mime = detect_mime($file['tmp_name']) ?? '';
    if (!in_array($mime, $allowed, true)) throw new RuntimeException('Invalid file type');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) ?: ( ($mime === 'image/png') ? 'png' : (($mime === 'image/webp') ? 'webp' : 'jpg') );
    $basename = 'cat_' . time() . '_' . bin2hex(random_bytes(6));
    $filename = safe_filename($basename) . '.' . $ext;
    $dest = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) throw new RuntimeException('Failed to move uploaded file');

    @chmod($dest, 0644);

    return trim($uploadBase, '/') . '/' . $filename;
}

function unlink_if_file(string $relativePath): void {
    if ($relativePath === '') return;
    $path = dirname(__DIR__) . '/' . ltrim($relativePath, '/');
    if (is_file($path)) @unlink($path);
}

// ---------- ACTIONS (POST) ----------
$action = $_REQUEST['action'] ?? '';
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], (string)$token)) throw new RuntimeException('Invalid CSRF token');

        if ($action === 'add' || $action === 'edit') {
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $sort_order = ($_POST['sort_order'] ?? '') === '' ? null : intval($_POST['sort_order'] ?? 0);
            if ($name === '') throw new RuntimeException('Name is required');

            $image_url = '';
            if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $image_url = store_uploaded_image($_FILES['image'], $UPLOAD_DIR, $UPLOAD_BASE, $ALLOWED_MIME, $MAX_FILE);
            }

            if ($action === 'add') {
                $sql = "INSERT INTO product_category (name, description, image_url, sort_order) VALUES (:name, :description, :image_url, :sort_order)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':name' => $name,
                    ':description' => $description ?: null,
                    ':image_url' => $image_url ?: null,
                    ':sort_order' => $sort_order,
                ]);
                flash_set('success', 'Category added');
            } else {
                $category_id = intval($_POST['category_id'] ?? 0);
                if ($category_id <= 0) throw new RuntimeException('Invalid category id');

                // if new image uploaded, delete old one
                if ($image_url !== '') {
                    $old = $pdo->prepare("SELECT image_url FROM product_category WHERE category_id = :id");
                    $old->execute([':id' => $category_id]);
                    $row = $old->fetch();
                    if ($row && !empty($row['image_url'])) unlink_if_file($row['image_url']);
                }

                $sql = "UPDATE product_category SET name = :name, description = :description, sort_order = :sort_order";
                if ($image_url !== '') $sql .= ", image_url = :image_url";
                $sql .= " WHERE category_id = :id LIMIT 1";
                $params = [
                    ':name' => $name,
                    ':description' => $description ?: null,
                    ':sort_order' => $sort_order,
                    ':id' => $category_id,
                ];
                if ($image_url !== '') $params[':image_url'] = $image_url;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                flash_set('success', 'Category updated');
            }

            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }

        if ($action === 'reorder') {
            $pairs = $_POST['order'] ?? null;
            if (is_array($pairs)) {
                $upd = $pdo->prepare("UPDATE product_category SET sort_order = :so WHERE category_id = :id");
                foreach ($pairs as $id => $so) {
                    $upd->execute([':so' => intval($so), ':id' => intval($id)]);
                }
                flash_set('success', 'Order saved');
            }
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
    }

    // GET delete (with CSRF token in query)
    if ($action === 'delete' && isset($_GET['id'])) {
        $token = $_GET['csrf'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'], (string)$token)) throw new RuntimeException('Invalid CSRF token for delete');
        $id = intval($_GET['id']);
        if ($id <= 0) throw new RuntimeException('Invalid id');

        $old = $pdo->prepare("SELECT image_url FROM product_category WHERE category_id = :id");
        $old->execute([':id' => $id]);
        $row = $old->fetch();
        if ($row && !empty($row['image_url'])) unlink_if_file($row['image_url']);

        $del = $pdo->prepare("DELETE FROM product_category WHERE category_id = :id");
        $del->execute([':id' => $id]);
        flash_set('success', 'Category deleted');
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }
} catch (Throwable $ex) {
    flash_set('error', $ex->getMessage());
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ---------- READ ----------
$editing = null;
if (isset($_GET['edit']) && intval($_GET['edit']) > 0) {
    $stmt = $pdo->prepare("SELECT * FROM product_category WHERE category_id = :id LIMIT 1");
    $stmt->execute([':id' => intval($_GET['edit'])]);
    $editing = $stmt->fetch() ?: null;
}

// safe ordering: prefer sort_order if present
$resOrder = $pdo->query("SHOW COLUMNS FROM product_category LIKE 'sort_order'")->fetch();
$hasSort = !empty($resOrder);
$orderClause = $hasSort ? 'sort_order IS NULL, sort_order ASC, created_at DESC' : 'created_at DESC';
$categories = $pdo->query("SELECT * FROM product_category ORDER BY {$orderClause}")->fetchAll();

// flash retrieval
$flashSuccess = flash_get('success');
$flashError   = flash_get('error');

$user_name = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? $ADMIN_EMAIL;
$csrf_token = $_SESSION['csrf_token'];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Product categories — Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#f5f7fb; --panel:#ffffff; --panel-solid:#ffffff; --muted:#6b7280; --accent:#0ea5b6; --accent-dark:#0596a6; --text:#071026; --glass: rgba(11,18,34,0.04);
  --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
  font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
}
[data-theme="dark"]{
  --bg:#071427; --panel:linear-gradient(180deg,#071827,#0a2130); --panel-solid:#061424; --muted:#9fb0bf; --accent:#4dd0c8; --accent-dark:#1fb7ac; --text:#e6eef6; --glass:rgba(255,255,255,0.04);
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
  background: var(--panel-solid);
  background-image: var(--panel);
  border:1px solid var(--glass);
  overflow:auto;
  box-shadow:var(--card-shadow);
  color:var(--text);
  z-index:120;
}

/* Header: sticky aligned to content (like edit_product.php) */
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

/* Main content and footer align to header's content area */
main.main { margin-left: calc(250px + 32px); padding:12px 0 40px; }
footer.footer { margin-left: calc(250px + 32px); padding:12px 0 24px; color:var(--muted); text-align:center; }
@media (max-width:960px){
  .sidebar{transform:translateX(-110%);width:280px;border-radius:0;top:0;bottom:0;left:0}
  .sidebar.open{transform:translateX(0)}
  header.header{position:fixed;left:0;right:0;top:0;margin-left:0;margin-right:0;height:56px}
  .page-inner{padding-top:72px}
  .site-sub{display:none}
  .mobile-menu-btn{display:inline-flex}
  main.main, footer.footer { margin-left:0; }
}

/* Header internals */
.hdr-left{display:flex;gap:12px;align-items:center;min-width:0}
.brand{display:flex;gap:12px;align-items:center;min-width:0}
.logo{width:44px;height:44;border-radius:8px;background:linear-gradient(135deg,var(--accent-dark),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;flex:0 0 44px}
.site-title{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.site-sub{font-size:12px;color:var(--muted);white-space:nowrap}

/* Header right: actions */
.hdr-actions{display:flex;gap:8px;align-items:center;flex:0 0 auto}
.btn{padding:8px 12px;border-radius:10px;border:0;background:var(--accent);color:#042b2a;font-weight:700;cursor:pointer}
.icon-btn{background:transparent;border:0;padding:8px;border-radius:8px;cursor:pointer;color:var(--muted);font-size:16px;display:inline-flex;align-items:center;justify-content:center}

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

/* Cards, forms, table */
.card{background:var(--panel);padding:16px;border-radius:8px;border:1px solid var(--glass);box-shadow:var(--card-shadow)}
.form-grid{display:grid;grid-template-columns:1fr 320px;gap:12px}
@media(max-width:880px){.form-grid{grid-template-columns:1fr}}
input, textarea, select {width:100%;padding:8px;border:1px solid #e6eef2;border-radius:6px}
.label{font-size:13px;color:var(--muted);margin-bottom:6px}
.button{background:var(--accent);color:#042b2a;padding:8px 12px;border-radius:6px;border:none;cursor:pointer}
.table{width:100%;border-collapse:collapse;margin-top:12px}
.table th,.table td{padding:10px;border-bottom:1px solid rgba(0,0,0,0.04);text-align:left;vertical-align:top}
.thumbnail{width:64px;height:40px;object-fit:cover;border-radius:4px}
.flash{padding:10px;border-radius:6px;margin-bottom:12px}
.flash.success{background:#ecfdf5;border:1px solid #bbf7d0;color:#064e3b}
.flash.error{background:#fff1f2;border:1px solid #fecaca;color:#991b1b}
.small{font-size:13px;color:var(--muted)}
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
          Signed in as <strong><?php echo e($user_name); ?></strong><br><?php echo e($user_email); ?>
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
      <main class="main" role="main" aria-live="polite">
        <div style="height:8px"></div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
          <div>
            <h1 style="margin:0">Product Categories</h1>
            <div class="small">Add, edit and manage categories</div>
          </div>
          <div class="small">Signed in as <?= e($user_name) ?></div>
        </div>

        <?php if ($flashSuccess): ?><div class="flash success card"><?= e($flashSuccess) ?></div><?php endif; ?>
        <?php if ($flashError): ?><div class="flash error card"><?= e($flashError) ?></div><?php endif; ?>

        <div class="card" style="margin-bottom:12px">
          <div class="small" style="margin-bottom:8px">Add or edit a category. Images are stored to <code><?= e($UPLOAD_BASE) ?>/</code></div>
          <div class="form-grid">
            <form method="post" enctype="multipart/form-data" novalidate>
              <input type="hidden" name="csrf_token" value="<?= e($csrf_token) ?>">
              <input type="hidden" name="category_id" value="<?= $editing ? (int)$editing['category_id'] : 0 ?>">
              <input type="hidden" name="action" value="<?= $editing ? 'edit' : 'add' ?>">

              <label class="label">Name</label>
              <input name="name" type="text" required value="<?= $editing ? e($editing['name']) : '' ?>">

              <label class="label">Description</label>
              <textarea name="description" rows="4"><?= $editing ? e($editing['description']) : '' ?></textarea>

              <label class="label">Sort Order (optional)</label>
              <input name="sort_order" type="number" value="<?= ($editing && $editing['sort_order'] !== null) ? (int)$editing['sort_order'] : '' ?>">

              <label class="label">Image (optional)</label>
              <input name="image" type="file" accept="image/*">

              <div style="margin-top:10px">
                <button class="button" type="submit"><?= $editing ? 'Update' : 'Add Category' ?></button>
                <?php if ($editing): ?><a href="<?= e(strtok($_SERVER['REQUEST_URI'], '?')) ?>" style="margin-left:10px;color:#666">Cancel</a><?php endif; ?>
              </div>

              <div class="small" style="margin-top:8px">Example saved path: <strong><?= e($UPLOAD_BASE) ?>/cat_1600000000_xxx.jpg</strong></div>
            </form>

            <div class="card" style="padding:12px">
              <h4 style="margin:0 0 8px 0">Preview</h4>
              <?php if ($editing && !empty($editing['image_url'])): ?>
                <img src="../<?= e(ltrim($editing['image_url'], '/')) ?>" class="thumbnail" alt="">
              <?php else: ?>
                <div class="small">No image</div>
              <?php endif; ?>

              <div style="margin-top:12px">
                <strong><?= $editing ? e($editing['name']) : 'Name preview' ?></strong>
                <div class="small"><?= $editing ? e($editing['description']) : 'Description' ?></div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <h4 style="margin:0 0 12px 0">Existing Categories</h4>
          <table class="table" role="table" aria-label="Categories">
            <thead>
              <tr><th>ID</th><th>Image</th><th>Name</th><th>Description</th><th>Sort</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($categories)): ?>
              <tr><td colspan="7" class="small">No categories yet</td></tr>
            <?php else: foreach ($categories as $r): ?>
              <tr>
                <td><?= (int)$r['category_id'] ?></td>
                <td>
                  <?php $img = $r['image_url'] ?? ''; $imgFs = dirname(__DIR__) . '/' . ltrim($img, '/'); ?>
                  <?php if ($img && is_file($imgFs)): ?>
                    <img src="../<?= e(ltrim($img, '/')) ?>" class="thumbnail" alt="">
                  <?php elseif ($img): ?>
                    <img src="../<?= e(ltrim($img, '/')) ?>" class="thumbnail" alt="" onerror="this.style.opacity=.5">
                  <?php else: ?>
                    <span class="small">—</span>
                  <?php endif; ?>
                </td>
                <td><?= e($r['name']) ?></td>
                <td style="max-width:300px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($r['description']) ?></td>
                <td><?= $r['sort_order'] !== null ? (int)$r['sort_order'] : '' ?></td>
                <td><?= e($r['created_at']) ?></td>
                <td>
                  <a href="?edit=<?= (int)$r['category_id'] ?>">Edit</a>
                  <a href="?id=<?= (int)$r['category_id'] ?>&action=delete&csrf=<?= e($csrf_token) ?>" onclick="return confirm('Delete this category?')">Delete</a>
                  <a href="?copy=<?= (int)$r['category_id'] ?>" style="color:#666">Copy</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

      </main>
    </div>

    <footer class="footer">© <?= date('Y') ?> Ali Hair Wigs — Admin</footer>
  </div>

<script>
  // Elements
  const sidebar = document.getElementById('sidebar');
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const themeBtn = document.getElementById('themeBtn');

  // Create overlay for mobile sidebar
  const overlay = document.createElement('div');
  overlay.id = 'overlay';
  overlay.style.display = 'none';
  overlay.style.position = 'fixed';
  overlay.style.inset = '0';
  overlay.style.background = 'rgba(2,6,23,0.45)';
  overlay.style.zIndex = '110';
  overlay.addEventListener('click', () => toggleSidebar(false));
  document.body.appendChild(overlay);

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
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
    });
  });

  // Theme persistence and prefers-color-scheme fallback
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
  if (stored) {
    applyTheme(stored === 'dark' ? 'dark' : 'light');
  } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    applyTheme('dark');
  } else {
    applyTheme('light');
  }
  themeBtn?.addEventListener('click', () => {
    const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    applyTheme(cur === 'dark' ? 'light' : 'dark');
  });

  // Mobile adjustments (no centered header tweaks; sticky header layout like edit_product.php)
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

<?php
// ---------- COPY handler (POST-safe pattern) ----------
if (isset($_GET['copy']) && intval($_GET['copy']) > 0) {
    $id = intval($_GET['copy']);
    $stmt = $pdo->prepare("SELECT name, description, image_url, sort_order FROM product_category WHERE category_id = :id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row) {
        $stmt = $pdo->prepare("INSERT INTO product_category (name, description, image_url, sort_order) VALUES (:name, :desc, :img, :so)");
        $stmt->execute([
            ':name' => $row['name'] . ' (copy)',
            ':desc' => $row['description'],
            ':img'  => $row['image_url'],
            ':so'   => $row['sort_order'],
        ]);
        flash_set('success', 'Category copied');
    } else {
        flash_set('error', 'Category not found to copy');
    }
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
?>
</body>
</html>
