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
    echo "<h1>Database connection error</h1>";
    exit;
}
$mysqli->set_charset('utf8mb4');

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// 1. GET PRODUCT ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: products_manage.php'); exit; }

$success = null;
$error = null;

// 2. LOAD EXISTING PRODUCT DATA
$stmt = $mysqli->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $id);
$stmt->execute();
$prod = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$prod) { die("Product not found."); }

// 3. LOAD PRODUCT CATEGORIES
$productCategories = [];
$table = 'product_category';
$check = $mysqli->query("SHOW TABLES LIKE '" . $mysqli->real_escape_string($table) . "'");
if ($check && $check->num_rows > 0) {
    $colRes = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
    $cols = [];
    while ($c = $colRes->fetch_assoc()) { $cols[] = $c['Field']; }
    
    $idCol = in_array('id', $cols) ? 'id' : (in_array('category_id', $cols) ? 'category_id' : null);
    $labelCol = in_array('name', $cols) ? 'name' : (in_array('category_name', $cols) ? 'category_name' : $cols[0]);

    $sql = $idCol ? "SELECT `{$idCol}` AS cid, `{$labelCol}` AS cname FROM `{$table}` ORDER BY `{$labelCol}`" 
                 : "SELECT `{$labelCol}` AS cname FROM `{$table}` ORDER BY `{$labelCol}`";
    
    if ($res = $mysqli->query($sql)) {
        while ($row = $res->fetch_assoc()) {
            $productCategories[] = [
                'value' => (string)($row['cid'] ?? $row['cname']),
                'label' => (string)$row['cname']
            ];
        }
    }
}

// 4. POST HANDLING
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priceRaw = $_POST['price'] ?? '0';
    $price = (float)$priceRaw;
    $category = trim($_POST['category'] ?? '');
    $product_category = trim($_POST['product_category'] ?? '');

    if ($name === '' || $description === '') {
        $error = "Please fill in all required fields.";
    }

    $imagePaths = [];
    if (!empty($_FILES['images']['tmp_name'][0])) {
        $maxFiles = 8;
        $uploadDir = __DIR__ . '/admin/images/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        for ($i = 0; $i < count($_FILES['images']['tmp_name']); $i++) {
            if ($i >= $maxFiles) break;
            $tmp = $_FILES['images']['tmp_name'][$i];
            if (empty($tmp)) continue;

            $ext = pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION);
            $safeName = bin2hex(random_bytes(6)) . '_' . time() . '.' . strtolower($ext);
            
            if (move_uploaded_file($tmp, $uploadDir . $safeName)) {
                $imagePaths[] = 'admin/admin/images/' . $safeName;
            }
        }
        $imagesJson = json_encode($imagePaths, JSON_UNESCAPED_SLASHES);
    } else {
        $imagesJson = $prod['image_placeholder'];
    }

    if (!$error) {
        $update = $mysqli->prepare("UPDATE products SET name=?, description=?, price=?, category=?, product_category=?, image_placeholder=? WHERE id=?");
        $priceFormatted = number_format($price, 2, '.', '');
        $update->bind_param("ssdsssi", $name, $description, $priceFormatted, $category, $product_category, $imagesJson, $id);
        
        if ($update->execute()) {
            $success = "Product updated successfully!";
            $prod['name'] = $name;
            $prod['description'] = $description;
            $prod['price'] = $priceFormatted;
            $prod['category'] = $category;
            $prod['product_category'] = $product_category;
            $prod['image_placeholder'] = $imagesJson;
        } else {
            $error = "Error updating database: " . $update->error;
        }
        $update->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Edit Product • Ali Hair Wigs</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:#f5f7fb; --panel:#ffffff; --panel-solid:#ffffff; --muted:#6b7280; --accent:#0ea5b6; --accent-dark:#0596a6;
      --text:#071026; --glass: rgba(11,18,34,0.04); --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
      --input-bg: #fff; --input-border: rgba(11,18,34,0.06); --focus: rgba(124,58,237,0.18);
      font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
    }
    [data-theme="dark"]{
      --bg:#071427; --panel: linear-gradient(180deg,#071827,#0b2230); --panel-solid:#061424;
      --muted:#9aa4b2; --accent:#4dd0c8; --accent-dark:#1fb7ac; --text:#e6eef6;
      --glass: rgba(255,255,255,0.04); --card-shadow: 0 8px 24px rgba(2,8,15,0.6);
      --input-bg: rgba(255,255,255,0.03); --input-border: rgba(255,255,255,0.06);
    }
    html,body{height:100%;margin:0;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
    
    /* Centered Layout without Sidebar/Header */
    main.main { 
        max-width: 900px;
        margin: 40px auto; 
        padding: 0 20px;
    }

    .card { background: var(--panel); border-radius:12px; padding:32px; box-shadow:var(--card-shadow); border:1px solid var(--glass); }
    .input, .textarea, .select { width:100%; padding:12px; border-radius:8px; border:1px solid var(--input-border); background: var(--input-bg); color:var(--text); box-sizing:border-box; font-size:14px; }
    .btn { background: linear-gradient(180deg,var(--accent),var(--accent-dark)); color: #fff; border: none; padding:12px 24px; border-radius:8px; font-weight:700; cursor:pointer; }
    .gallery-preview { display:flex; gap:10px; flex-wrap:wrap; margin-top:10px; margin-bottom:15px; }
    .gallery-preview img { width:100px; height:100px; object-fit:cover; border-radius:8px; border:2px solid var(--glass); }
    
    .logo-small { width:40px; height:40px; border-radius:8px; background: linear-gradient(135deg,var(--accent-dark),var(--accent)); display:flex; align-items:center; justify-content:center; color:#fff; font-weight:800; margin-bottom: 20px; }
  </style>
</head>
<body>

  <main class="main">
    <div class="logo-small">AH</div>
    
    <div class="card">
      <h1 style="margin-top:0; font-size:24px; margin-bottom:8px;">Edit Product</h1>
      <p style="color:var(--muted); margin-bottom:24px;">Currently modifying: <strong><?php echo esc($prod['name']); ?></strong></p>

      <?php if ($success): ?><div style="color:#059669; background:#ecfdf5; padding:12px; border-radius:8px; margin-bottom:20px; border: 1px solid #10b981;">✓ <?php echo $success; ?></div><?php endif; ?>
      <?php if ($error): ?><div style="color:#dc2626; background:#fef2f2; padding:12px; border-radius:8px; margin-bottom:20px; border: 1px solid #ef4444;">✗ <?php echo $error; ?></div><?php endif; ?>

      <form method="POST" enctype="multipart/form-data">
        <div style="margin-bottom:20px">
          <label style="display:block; font-weight:700; margin-bottom:8px">Product Name</label>
          <input type="text" name="name" class="input" value="<?php echo esc($prod['name']); ?>" required>
        </div>

        <div style="margin-bottom:20px">
          <label style="display:block; font-weight:700; margin-bottom:8px">Description</label>
          <textarea name="description" class="textarea" rows="6" required><?php echo esc($prod['description']); ?></textarea>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px">
          <div>
            <label style="display:block; font-weight:700; margin-bottom:8px">Price ($)</label>
            <input type="number" step="0.01" name="price" class="input" value="<?php echo (float)$prod['price']; ?>">
          </div>
          <div>
            <label style="display:block; font-weight:700; margin-bottom:8px">Gender Category</label>
            <select name="category" class="select">
              <option value="men" <?php if($prod['category']=='men') echo 'selected'; ?>>Men</option>
              <option value="women" <?php if($prod['category']=='women') echo 'selected'; ?>>Women</option>
            </select>
          </div>
        </div>

        <div style="margin-bottom:20px">
          <label style="display:block; font-weight:700; margin-bottom:8px">Product Category Type</label>
          <select name="product_category" class="select">
            <?php foreach ($productCategories as $pc): ?>
              <option value="<?php echo esc($pc['value']); ?>" <?php if($prod['product_category']==$pc['value']) echo 'selected'; ?>>
                  <?php echo esc($pc['label']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div style="margin-bottom:20px">
          <label style="display:block; font-weight:700; margin-bottom:8px">Current Product Images</label>
          <div class="gallery-preview">
            <?php 
              $imgs = json_decode($prod['image_placeholder'], true);
              if(is_array($imgs)){
                foreach($imgs as $i){
                  $displayPath = str_replace('admin/admin/', 'admin/', $i);
                  echo '<img src="'.$displayPath.'" alt="Product Image">';
                }
              }
            ?>
          </div>
          <p style="font-size:12px; color:var(--muted)">Note: Uploading new images will replace the entire gallery.</p>
        </div>

        <div style="margin-bottom:30px">
          <label style="display:block; font-weight:700; margin-bottom:8px">Upload New Images (Max 8)</label>
          <input type="file" name="images[]" class="input" multiple accept="image/*">
        </div>

        <div style="display:flex; align-items:center; gap:20px; border-top: 1px solid var(--input-border); padding-top:20px;">
          <button type="submit" class="btn">Update Product</button>
          <a href="products_manage.php" style="text-decoration:none; color:var(--muted); font-size:14px; font-weight:600;">Cancel and Return</a>
        </div>
      </form>
    </div>
  </main>

  <script>
    // Theme logic (persists even without the button if desired)
    if(localStorage.getItem('admin_theme') === 'dark') {
        document.documentElement.setAttribute('data-theme','dark');
    }
  </script>
</body>
</html>