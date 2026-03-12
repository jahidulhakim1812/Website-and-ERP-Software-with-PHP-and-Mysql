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

// DB configuration
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

$mysqli = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo "<h1>Database connection error</h1><p>" . htmlspecialchars($mysqli->connect_error, ENT_QUOTES) . "</p>";
    exit;
}
$mysqli->set_charset('utf8mb4');

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$success = null;
$error = null;
$name = $description = $category = $product_category = '';
$price = 0.00;

/*
  Load product categories from product_category table in a schema-robust way.
  The resulting $productCategories array contains items: ['value' => ..., 'label' => ...]
  - If table has an id-like column, value = id
  - Otherwise value = label (safe fallback)
*/
$productCategories = [];
$table = 'product_category';

// Ensure table exists before attempting SHOW COLUMNS
$check = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");
if ($check && $check->num_rows > 0) {
    $cols = [];
    $colRes = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
        $colRes->free();
    }

    $idCandidates = ['id', 'category_id', 'cat_id', 'cid', 'pk_id'];
    $labelCandidates = ['name', 'title', 'category_name', 'cat_name', 'label', 'slug'];

    $idCol = null;
    $labelCol = null;
    foreach ($idCandidates as $cand) {
        if (in_array($cand, $cols, true)) { $idCol = $cand; break; }
    }
    foreach ($labelCandidates as $cand) {
        if (in_array($cand, $cols, true)) { $labelCol = $cand; break; }
    }

    if ($labelCol === null && count($cols) > 0) {
        foreach ($cols as $c) {
            if (!in_array($c, ['created_at','updated_at','created','updated','status','sort_order'], true)) {
                $labelCol = $c;
                break;
            }
        }
        if ($labelCol === null) $labelCol = $cols[0];
    }

    if ($labelCol !== null) {
        if ($idCol !== null) {
            $sql = "SELECT `{$idCol}` AS cid, `{$labelCol}` AS cname FROM `{$table}` ORDER BY `{$labelCol}`";
        } else {
            $sql = "SELECT `{$labelCol}` AS cname FROM `{$table}` ORDER BY `{$labelCol}`";
        }

        if ($res = $mysqli->query($sql)) {
            while ($row = $res->fetch_assoc()) {
                if (isset($row['cid']) && isset($row['cname'])) {
                    $productCategories[] = ['value' => (string)$row['cid'], 'label' => (string)$row['cname']];
                } elseif (isset($row['cname'])) {
                    $productCategories[] = ['value' => (string)$row['cname'], 'label' => (string)$row['cname']];
                }
            }
            $res->free();
        }
    }
}
$check && $check->free();

// POST handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priceRaw = $_POST['price'] ?? '';
    $price = $priceRaw === '' ? 0.00 : (float)$priceRaw; // allow 0
    $category = trim($_POST['category'] ?? '');
    $product_category = trim($_POST['product_category'] ?? '');

    // Basic validation: name and description required, price must be >= 0
    if ($name === '' || $description === '' || $price < 0) {
        $error = "Please fill in all required fields correctly.";
    }

    // Multiple image upload
    $imagePaths = [];
    $maxFiles = 8;
    $maxSize = 2 * 1024 * 1024; // 2MB per file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    if (!$error) {
        if (!empty($_FILES['images']) && is_array($_FILES['images']['tmp_name'])) {
            $tmpList = array_filter($_FILES['images']['tmp_name'], fn($t) => !empty($t));
            $count = count($tmpList);
            if ($count === 0) {
                $error = "Please upload at least one image.";
            } elseif ($count > $maxFiles) {
                $error = "You may upload up to $maxFiles images.";
            } else {
                $uploadDir = __DIR__ . '/admin/images/';
                if (!is_dir($uploadDir)) {
                    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        $error = "Failed to create upload directory.";
                    }
                }

                if (!$error) {
                    $processed = 0;
                    for ($i = 0; $i < count($_FILES['images']['tmp_name']); $i++) {
                        if (empty($_FILES['images']['tmp_name'][$i])) continue;
                        $tmp = $_FILES['images']['tmp_name'][$i];
                        $orig = basename($_FILES['images']['name'][$i]);
                        $size = $_FILES['images']['size'][$i] ?? 0;
                        $fileError = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_OK;

                        if ($fileError !== UPLOAD_ERR_OK) {
                            $error = "Error uploading one of the images.";
                            break;
                        }

                        $type = mime_content_type($tmp) ?: ($_FILES['images']['type'][$i] ?? '');
                        if (!in_array($type, $allowedTypes, true)) {
                            $error = "Only JPG, PNG, WEBP, and GIF files are allowed.";
                            break;
                        }
                        if ($size > $maxSize) {
                            $error = "Each image must be under 2MB.";
                            break;
                        }

                        $ext = pathinfo($orig, PATHINFO_EXTENSION);
                        $base = pathinfo($orig, PATHINFO_FILENAME);
                        $safeBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$base);
                        try {
                            $uniq = bin2hex(random_bytes(6));
                        } catch (Exception $e) {
                            $uniq = uniqid();
                            $uniq = preg_replace('/[^a-zA-Z0-9]/', '', $uniq);
                        }
                        $safeName = $safeBase . '_' . $uniq . '.' . strtolower($ext);
                        $targetPath = $uploadDir . $safeName;

                        if (move_uploaded_file($tmp, $targetPath)) {
                            // keep original relative convention
                            $imagePaths[] = 'admin/admin/images/' . $safeName;
                            $processed++;
                        } else {
                            $error = "Failed to move uploaded file.";
                            break;
                        }

                        if ($processed >= $maxFiles) break;
                    }
                }
            }
        } else {
            $error = "Please upload at least one image.";
        }
    }

    if (!$error) {
        // Determine canonical product_category slug to store in products.product_category:
        // Priority:
        // 1. value chosen from product_category select (we assume it's a slug or label)
        // 2. fallback to legacy category slug (men/women) from category select
        // 3. empty string if none selected
        $categoryToStore = '';
        if ($product_category !== '') {
            $categoryToStore = $product_category;
        } elseif ($category !== '') {
            $categoryToStore = in_array($category, ['men', 'women'], true) ? $category : $category;
        }

        $imagesJson = json_encode(array_values($imagePaths), JSON_UNESCAPED_SLASHES);

        // Insert now stores the canonical product_category slug into product_category column
        $stmt = $mysqli->prepare("INSERT INTO products (name, description, price, image_placeholder, category, product_category) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $priceFormatted = number_format($price, 2, '.', '');
            // bind_param types: s = name, s = description, d = price, s = image json, s = category (legacy), s = product_category (slug)
            $stmt->bind_param("ssdsss", $name, $description, $priceFormatted, $imagesJson, $category, $categoryToStore);
            if ($stmt->execute()) {
                $success = "Product added successfully!";
                $name = $description = $category = $product_category = '';
                $price = 0.00;
            } else {
                $error = "Error adding product: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Failed to prepare statement: " . $mysqli->error;
        }
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
  <title>Add Product • Ali Hair Wigs</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
/* Dark-mode polished theme + layout (kept identical to original) */
:root{
  --bg:#f5f7fb;
  --panel:#ffffff;
  --panel-solid:#ffffff;
  --muted:#6b7280;
  --accent:#0ea5b6;
  --accent-dark:#0596a6;
  --text:#071026;
  --glass: rgba(11,18,34,0.04);
  --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
  --input-bg: #fff;
  --input-border: rgba(11,18,34,0.06);
  --focus: rgba(124,58,237,0.18);
  font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
  transition: background-color .22s ease, color .22s ease;
}

[data-theme="dark"]{
  --bg:#071427;
  --panel: linear-gradient(180deg,#071827,#0b2230);
  --panel-solid:#061424;
  --muted:#9aa4b2;
  --accent:#4dd0c8;
  --accent-dark:#1fb7ac;
  --text:#e6eef6;
  --glass: rgba(255,255,255,0.04);
  --card-shadow: 0 8px 24px rgba(2,8,15,0.6);
  --input-bg: rgba(255,255,255,0.03);
  --input-border: rgba(255,255,255,0.06);
  --focus: rgba(77,208,200,0.12);
}

/* Base */
html,body{height:100%;margin:0;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
.wrap{max-width:1200px;margin:0 auto;padding:0 12px 32px;display:block}
.page-inner{display:block;max-width:1200px;margin:16px auto;padding:12px;box-sizing:border-box}

/* Sidebar */
.sidebar {
  position:fixed; left:16px; top:16px; bottom:16px; width:250px; padding:14px; border-radius:12px;
  background: var(--panel-solid); background-image: var(--panel); border:1px solid var(--glass);
  overflow:auto; box-shadow:var(--card-shadow); color:var(--text); z-index:120;
  transition: transform .22s ease, box-shadow .22s ease, background .22s ease;
}

/* Header */
header.header {
  position:sticky; top:16px; margin-left: calc(250px + 32px); margin-right: 16px; z-index:115;
  display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 16px;
  border-radius:10px; background:linear-gradient(90deg,rgba(255,255,255,0.98),rgba(250,250,252,0.98));
  border:1px solid var(--glass); box-shadow: 0 6px 18px rgba(11,18,34,0.04); backdrop-filter: blur(6px);
  height:64px; box-sizing:border-box; transition: background .22s ease, border-color .22s ease;
}
[data-theme="dark"] header.header{
  background: linear-gradient(90deg, rgba(6,24,34,0.92), rgba(6,24,34,0.88));
  border-color: rgba(255,255,255,0.04);
  box-shadow: 0 6px 24px rgba(0,0,0,0.6);
}

/* Typography / brand */
.hdr-left{display:flex;gap:12px;align-items:center;min-width:0}
.brand{display:flex;gap:12px;align-items:center;min-width:0}
.logo{
  width:44px;height:44;border-radius:8px;
  background: linear-gradient(135deg,var(--accent-dark),var(--accent));
  display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;flex:0 0 44px;
  box-shadow: 0 6px 18px rgba(11,18,34,0.06), inset 0 -2px 6px rgba(0,0,0,0.08);
}

/* Header small text */
.site-title{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.site-sub{font-size:12px;color:var(--muted);white-space:nowrap}

/* Actions */
.hdr-actions{display:flex;gap:8px;align-items:center;flex:0 0 auto}
.btn{padding:8px 12px;border-radius:10px;border:0;background:var(--accent);color:#042b2a;font-weight:700;cursor:pointer}
.icon-btn{background:transparent;border:0;padding:8px;border-radius:8px;cursor:pointer;color:var(--muted);font-size:16px;display:inline-flex;align-items:center;justify-content:center}

/* Responsive */
@media (max-width:960px){
  .sidebar{transform:translateX(-110%);width:280px;border-radius:0;top:0;bottom:0;left:0}
  .sidebar.open{transform:translateX(0)}
  header.header{position:fixed;left:0;right:0;top:0;margin-left:0;margin-right:0;height:56px}
  .page-inner{padding-top:72px}
  .site-sub{display:none}
  .mobile-menu-btn{display:inline-flex}
}

/* Main / Footer alignment */
main.main { margin-left: calc(250px + 32px); padding:12px 0 40px; transition: margin-left .18s ease; }
footer.footer { margin-left: calc(250px + 32px); padding:12px 0 24px; color:var(--muted); text-align:center; transition: margin-left .18s ease; }
@media (max-width:960px){ main.main, footer.footer { margin-left:0; } }

/* Nav */
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

/* Card */
.card { max-width:820px; background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); border-radius:12px; padding:18px; box-shadow:var(--card-shadow); border:1px solid var(--glass); }
[data-theme="dark"] .card { background: linear-gradient(180deg, rgba(255,255,255,0.02), rgba(255,255,255,0.01)); }

/* Forms */
.field { margin-bottom:12px; }
.label{ display:block; font-weight:700; margin-bottom:6px; color:var(--text); }
.input, .textarea, .select {
  width:100%; padding:10px; border-radius:8px; border:1px solid var(--input-border); font-size:14px; box-sizing:border-box;
  background: var(--input-bg); color:var(--text); transition: box-shadow .14s ease, border-color .14s ease, background .14s ease;
}
.textarea { min-height:120px; resize:vertical; }
.input[type="file"] { padding:8px; }

/* Focus */
.input:focus, .textarea:focus, .select:focus {
  outline: none;
  box-shadow: 0 6px 18px var(--focus);
  border-color: rgba(124,58,237,0.32);
}

/* Buttons */
.btn { background: linear-gradient(180deg,var(--accent),var(--accent-dark)); color: #fff; border: none; }
.btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(11,18,34,0.08); }

/* Alerts */
.alert-success { background:#ecfdf5; color:#065f46; padding:10px; border-radius:8px; }
.alert-error { background:#fef2f2; color:#991b1b; padding:10px; border-radius:8px; }
[data-theme="dark"] .alert-success { background: rgba(34,197,94,0.08); color: #bbf7d0; }
[data-theme="dark"] .alert-error { background: rgba(239,68,68,0.07); color: #fecaca; }

/* Subtle UI flourishes */
.logo { transition: transform .18s ease, box-shadow .18s ease; }
.logo:active { transform: translateY(1px) scale(.997); }

/* Scrollbar (dark-polish) */
.sidebar::-webkit-scrollbar { width:10px; }
.sidebar::-webkit-scrollbar-track { background: transparent; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.12); border-radius:8px; }
[data-theme="dark"] .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.06); }

/* Small screens tidy */
@media (max-width:640px){
  .card{ margin:12px; padding:14px; }
}
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

      <div class="hdr-actions" role="toolbar" aria-label="Header actions">
        <button id="themeBtn" class="icon-btn" title="Toggle theme" aria-pressed="false" aria-label="Toggle dark mode">🌓</button>
        <a href="logout.php" class="icon-btn" title="Sign out" aria-label="Sign out" onclick="return confirm('Sign out?');">⎋</a>
      </div>
    </header>

    <div class="page-inner">
      <main class="main" aria-live="polite" role="main">
        <div style="height:8px"></div>

        <div class="card" role="region" aria-labelledby="addProductTitle">
          <h1 id="addProductTitle" style="font-size:20px;margin:0 0 12px;color:var(--accent-dark);font-weight:800">Add New Wig Product</h1>

          <?php if ($success): ?>
            <div class="alert-success" style="margin-bottom:12px"><?php echo esc($success); ?></div>
          <?php elseif ($error): ?>
            <div class="alert-error" style="margin-bottom:12px"><?php echo esc($error); ?></div>
          <?php endif; ?>

          <form method="POST" enctype="multipart/form-data" class="space-y-4" novalidate>
            <div class="field">
              <label for="name" class="label">Product Name</label>
              <input type="text" id="name" name="name" class="input" value="<?php echo esc($name); ?>" required>
            </div>

            <div class="field">
              <label for="description" class="label">Description</label>
              <textarea id="description" name="description" class="textarea" rows="5" required><?php echo esc($description); ?></textarea>
            </div>

            <div class="field" style="display:flex;gap:12px">
              <div style="flex:1">
                <label for="price" class="label">Price (USD)</label>
                <input type="number" step="0.01" id="price" name="price" class="input" value="<?php echo esc((string)$price); ?>" required>
              </div>
              <div style="flex:1">
                <label for="category" class="label">Category</label>
                <select id="category" name="category" class="select" required>
                  <option value="">Select Category</option>
                  <option value="men" <?php echo ($category === 'men') ? 'selected' : ''; ?>>Men</option>
                  <option value="women" <?php echo ($category === 'women') ? 'selected' : ''; ?>>Women</option>
                </select>
              </div>
            </div>

            <!-- Product Category populated from product_category table -->
            <div class="field" style="margin-top:8px">
              <label for="product_category" class="label">Product Category</label>
              <select id="product_category" name="product_category" class="select">
                <option value="">Select Product Category</option>
                <?php foreach ($productCategories as $pc): ?>
                  <option value="<?php echo esc((string)$pc['value']); ?>" <?php echo ($product_category === (string)$pc['value']) ? 'selected' : ''; ?>>
                    <?php echo esc($pc['label']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div style="font-size:12px;color:var(--muted);margin-top:6px">This field mirrors the Category control. It is captured on submit and saved into product_category.</div>
            </div>

            <div class="field">
              <label for="images" class="label">Upload Images (multiple)</label>
              <input type="file" id="images" name="images[]" accept="image/*" class="input" multiple required>
              <div id="preview" style="display:flex;gap:8px;margin-top:8px;flex-wrap:wrap"></div>
            </div>

            <div style="display:flex;gap:8px;align-items:center">
              <button type="submit" class="btn">Add Product</button>
              <a href="products_manage.php" style="color:var(--muted);text-decoration:none;padding:8px 12px;border-radius:8px;border:1px solid var(--glass);">Manage products</a>
            </div>
          </form>
        </div>
      </main>
    </div>

    <footer class="footer">© <?php echo date('Y'); ?> Ali Hair Wigs — Admin</footer>
  </div>

  <script>
    // smooth initial theme transition
    document.documentElement.style.transition = 'background-color .22s ease, color .22s ease';
    setTimeout(()=> document.documentElement.style.transition = '', 300);

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

    // Preview selected images (max 8 visible)
    document.getElementById('images')?.addEventListener('change', function(e){
      const preview = document.getElementById('preview');
      preview.innerHTML = '';
      Array.from(this.files).slice(0,8).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const reader = new FileReader();
        reader.onload = () => {
          const img = document.createElement('img');
          img.src = reader.result;
          img.style.width = '96px';
          img.style.height = '96px';
          img.style.objectFit = 'cover';
          img.style.borderRadius = '8px';
          img.style.boxShadow = '0 4px 12px rgba(0,0,0,0.08)';
          preview.appendChild(img);
        };
        reader.readAsDataURL(file);
      });
    });
  </script>
</body>
</html>
<?php $mysqli->close(); ?>
