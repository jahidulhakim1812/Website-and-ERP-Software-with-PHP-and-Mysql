<?php
declare(strict_types=1);
session_start();

/*
  product_category.php
  - Resilient category page built without short-array syntax
  - Usage: product_category.php?id=123  OR  product_category.php?slug=mens-wigs
  - Adjust DB constants if needed
*/

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

define('CURRENCY_SYMBOL', '₹');
define('STORAGE_KEY', 'ali_hair_cart_v1');

function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function formatCurrency($v) { return CURRENCY_SYMBOL . ' ' . number_format((float)$v, 2); }

$catIdParam = isset($_GET['id']) ? trim($_GET['id']) : '';
$catSlugParam = isset($_GET['slug']) ? trim($_GET['slug']) : '';

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn && $conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    $conn = null;
} else if ($conn) {
    $conn->set_charset('utf8mb4');
}

/* Determine which columns exist in product_category */
$colMap = array(
    'id' => null,
    'name' => null,
    'slug' => null,
    'description' => null
);

if ($conn) {
    $res = $conn->query("SHOW COLUMNS FROM `product_category`");
    if ($res) {
        $cols = array();
        while ($r = $res->fetch_assoc()) {
            $cols[] = $r['Field'];
        }
        $res->free();

        $candidates = array(
            'id' => array('id','category_id','cat_id','product_category_id','pc_id'),
            'name' => array('name','title','category_name','cat_name'),
            'slug' => array('slug','category_slug','url_slug'),
            'description' => array('description','desc','details')
        );

        foreach ($candidates as $key => $list) {
            foreach ($list as $c) {
                if (in_array($c, $cols, true)) {
                    $colMap[$key] = $c;
                    break;
                }
            }
        }
    }
}

/* Require an id-like column */
if (!$colMap['id']) {
    http_response_code(500);
    echo "Database schema error: no primary id column found on table product_category. Expected one of id, category_id, cat_id, product_category_id.";
    if ($conn) $conn->close();
    exit;
}

/* Helper to fetch single category by id or slug */
function fetchCategory($conn, $colMap, $byId = null, $bySlug = null) {
    $selectCols = array();
    foreach (array('id','name','slug','description') as $k) {
        if (!empty($colMap[$k])) $selectCols[] = "`{$colMap[$k]}` AS `{$k}`";
    }
    $select = implode(',', $selectCols);
    if ($select === '') $select = "`{$colMap['id']}` AS `id`";

    if ($byId !== null) {
        $sql = "SELECT {$select} FROM `product_category` WHERE `{$colMap['id']}` = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $byId);
    } elseif ($bySlug !== null && !empty($colMap['slug'])) {
        $sql = "SELECT {$select} FROM `product_category` WHERE `{$colMap['slug']}` = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('s', $bySlug);
    } else {
        return null;
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc() ?: null;
    $stmt->close();
    return $row;
}

/* Try to find requested category */
$category = null;
if ($conn) {
    if ($catIdParam !== '') {
        $category = fetchCategory($conn, $colMap, $catIdParam, null);
    }
    if (!$category && $catSlugParam !== '') {
        $category = fetchCategory($conn, $colMap, null, $catSlugParam);
    }
}

/* If no category requested/found, list categories */
$showCategoryList = false;
$categories = array();
if (!$category) {
    $showCategoryList = true;
    if ($conn) {
        $selectCols = array();
        foreach (array('id','name','slug','description') as $k) {
            if (!empty($colMap[$k])) $selectCols[] = "`{$colMap[$k]}` AS `{$k}`";
        }
        $select = implode(',', $selectCols);
        if ($select === '') $select = "`{$colMap['id']}` AS `id`";
        $orderBy = $colMap['name'] ? "`{$colMap['name']}`" : "`{$colMap['id']}`";
        $sql = "SELECT {$select} FROM `product_category` ORDER BY {$orderBy} ASC LIMIT 500";
        $rs = $conn->query($sql);
        if ($rs) {
            while ($r = $rs->fetch_assoc()) $categories[] = $r;
            $rs->free();
        }
    }
}

/* Fetch products for the category if available */
$products = array();
if ($category && $conn) {
    /* get product columns */
    $prodCols = array();
    $res = $conn->query("SHOW COLUMNS FROM `products`");
    if ($res) {
        while ($r = $res->fetch_assoc()) $prodCols[] = $r['Field'];
        $res->free();
    }

    /* detect product -> category column */
    $possibleCols = array('category_id','category','cat_id','p_category','product_category_id');
    $prodCatCol = null;
    foreach ($possibleCols as $c) {
        if (in_array($c, $prodCols, true)) { $prodCatCol = $c; break; }
    }
    if (!$prodCatCol && in_array('category', $prodCols, true)) $prodCatCol = 'category';

    /* build select columns for products */
    $prodSelectFields = array();
    if (in_array('id', $prodCols, true)) $prodSelectFields[] = '`id`';
    // name/title
    if (in_array('name', $prodCols, true)) {
        $prodSelectFields[] = '`name`';
    } else {
        if (in_array('title', $prodCols, true)) $prodSelectFields[] = '`title` AS `name`';
        elseif (in_array('product_name', $prodCols, true)) $prodSelectFields[] = '`product_name` AS `name`';
    }
    // price
    if (in_array('price', $prodCols, true)) {
        $prodSelectFields[] = '`price`';
    } else {
        if (in_array('sale_price', $prodCols, true)) $prodSelectFields[] = '`sale_price` AS `price`';
        elseif (in_array('mrp', $prodCols, true)) $prodSelectFields[] = '`mrp` AS `price`';
    }
    if (in_array('short_description', $prodCols, true)) $prodSelectFields[] = '`short_description`';
    if (in_array('image', $prodCols, true)) $prodSelectFields[] = '`image`';

    if (empty($prodSelectFields)) {
        // fallback minimal
        if (in_array('id', $prodCols, true)) $prodSelectFields[] = '`id`';
        if (in_array('name', $prodCols, true)) $prodSelectFields[] = '`name`';
    }

    $selectClause = implode(',', $prodSelectFields);

    $perPage = 48;
    $page = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $perPage;

    if ($prodCatCol) {
        // check column type
        $typeIsInt = false;
        $r = $conn->query("SHOW COLUMNS FROM `products` LIKE '" . $conn->real_escape_string($prodCatCol) . "'");
        if ($r && $row = $r->fetch_assoc()) {
            if (preg_match('/int/i', $row['Type'] ?? '')) $typeIsInt = true;
            $r->free();
        }
        if ($typeIsInt) {
            $stmt = $conn->prepare("SELECT {$selectClause} FROM `products` WHERE `{$prodCatCol}` = ? AND (status = 1 OR status IS NULL) ORDER BY id DESC LIMIT ? OFFSET ?");
            $catKey = $category['id'];
            if ($stmt) {
                $stmt->bind_param('iii', $catKey, $perPage, $offset);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($p = $res->fetch_assoc()) $products[] = $p;
                $stmt->close();
            }
        } else {
            $catKey = isset($category['slug']) && $category['slug'] !== '' ? $category['slug'] : (isset($category['name']) ? $category['name'] : '');
            $stmt = $conn->prepare("SELECT {$selectClause} FROM `products` WHERE `{$prodCatCol}` = ? AND (status = 1 OR status IS NULL) ORDER BY id DESC LIMIT ? OFFSET ?");
            if ($stmt) {
                $stmt->bind_param('sii', $catKey, $perPage, $offset);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($p = $res->fetch_assoc()) $products[] = $p;
                $stmt->close();
            }
        }
    }
}

if ($conn) $conn->close();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title><?php echo esc($category['name'] ?? 'Categories'); ?> — Ali Hair Wigs</title>
<style>
  body{font-family:Inter,system-ui,-apple-system,'Segoe UI',Roboto,Arial;background:#fcfcfc;color:#111827;margin:0;padding:16px}
  .container{max-width:1100px;margin:0 auto}
  .grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
  @media(max-width:1024px){.grid{grid-template-columns:repeat(3,1fr)}}
  @media(max-width:768px){.grid{grid-template-columns:repeat(2,1fr)}}
  @media(max-width:420px){.grid{grid-template-columns:1fr}}
  .card{background:#fff;padding:12px;border-radius:10px;box-shadow:0 6px 18px rgba(0,0,0,0.06)}
  .empty{padding:40px;text-align:center;color:#6b7280}
  .cat-list{display:flex;flex-wrap:wrap;gap:12px}
  .cat-item{background:#fff;padding:10px 14px;border-radius:10px;box-shadow:0 6px 12px rgba(0,0,0,0.04);text-decoration:none;color:#111;font-weight:700}
  a { color: inherit; text-decoration: none; }
</style>
</head>
<body>
  <div class="container">
    <header style="display:flex;align-items:center;gap:12px;margin-bottom:18px">
      <a href="index.php" style="display:flex;align-items:center;gap:10px;text-decoration:none;color:#7b3f00">
        <img src="uploads/ahw.png" alt="" style="width:46px;height:46px;object-fit:contain" onerror="this.onerror=null;this.src='https://placehold.co/80x80/7b3f00/f7e0c4?text=Logo'">
        <div style="font-weight:800">ALI HAIR <div style="font-size:12px;color:#6b7280">WIGS</div></div>
      </a>
    </header>

    <?php if ($showCategoryList): ?>
      <h1 style="font-size:20px;margin-bottom:12px">Categories</h1>
      <?php if (!empty($categories)): ?>
        <div class="cat-list">
          <?php foreach ($categories as $c):
            $cid = isset($c['id']) ? $c['id'] : (isset($c['category_id']) ? $c['category_id'] : '');
            $slug = isset($c['slug']) ? $c['slug'] : '';
            $name = isset($c['name']) ? $c['name'] : (isset($c['title']) ? $c['title'] : 'Category');
          ?>
            <a class="cat-item" href="product_category.php?id=<?php echo urlencode((string)$cid); ?>&slug=<?php echo urlencode((string)$slug); ?>"><?php echo esc($name); ?></a>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="empty">No categories found.</div>
      <?php endif; ?>

    <?php else: ?>

      <h1 style="font-size:20px;margin-bottom:8px"><?php echo esc($category['name'] ?? $category['title'] ?? 'Category'); ?></h1>
      <?php if (!empty($category['description'])): ?><p style="color:#6b7280;margin-bottom:16px"><?php echo esc($category['description']); ?></p><?php endif; ?>

      <?php if (empty($products)): ?>
        <div class="empty">No products found for this category.</div>
      <?php else: ?>
        <div class="grid">
          <?php foreach ($products as $p):
            $pid = isset($p['id']) ? $p['id'] : '';
            $pname = isset($p['name']) ? $p['name'] : (isset($p['title']) ? $p['title'] : 'Product');
            $pimg = isset($p['image']) ? $p['image'] : '';
            $pdesc = isset($p['short_description']) ? $p['short_description'] : '';
            $pprice = isset($p['price']) ? $p['price'] : 0;
          ?>
            <div class="card">
              <a href="product_details.php?id=<?php echo urlencode((string)$pid); ?>">
                <div style="height:160px;background:#f6f6f6;border-radius:8px;overflow:hidden;margin-bottom:8px">
                  <img src="<?php echo esc($pimg ?: 'https://placehold.co/600x400/7b3f00/f7e0c4?text=Product'); ?>" alt="<?php echo esc($pname); ?>" style="width:100%;height:100%;object-fit:cover">
                </div>
                <div style="font-weight:800;margin-bottom:6px"><?php echo esc($pname); ?></div>
                <div style="color:#6b7280;font-size:13px;margin-bottom:8px"><?php echo esc(mb_substr((string)$pdesc,0,120)); ?></div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                  <div style="font-weight:700;color:#7b3f00"><?php echo formatCurrency($pprice); ?></div>
                  <button class="add-btn" data-id="<?php echo esc((string)$pid); ?>" data-name="<?php echo esc($pname); ?>" data-price="<?php echo esc((string)$pprice); ?>">Add</button>
                </div>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

<script>
document.addEventListener('click', function(e){
  var btn = (e.target.closest && e.target.closest('.add-btn')) || null;
  if (!btn) return;
  e.preventDefault();
  var id = btn.getAttribute('data-id') || '';
  var name = btn.getAttribute('data-name') || 'Product';
  var price = parseFloat(btn.getAttribute('data-price') || 0) || 0;
  try {
    var STORAGE_KEY = '<?php echo STORAGE_KEY; ?>';
    var raw = localStorage.getItem(STORAGE_KEY) || '{}';
    var cart = JSON.parse(raw || '{}');
    if (!cart[id]) cart[id] = { id: id, name: name, price: price, img:'', qty:0 };
    cart[id].qty = (cart[id].qty || 0) + 1;
    localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    btn.textContent = 'Added';
    setTimeout(function(){ btn.textContent = 'Add'; }, 700);
  } catch (err) {
    console.error(err);
  }
}, true);
</script>
</body>
</html>
