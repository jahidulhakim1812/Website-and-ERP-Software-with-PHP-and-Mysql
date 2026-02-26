<?php
declare(strict_types=1);
session_start();

/*
 men_wigs.php
 - Page shows only products that belong to Men (by category/category_id/product_category join or name/tags fallbacks)
 - Side panel lists only product_category rows that are related to the Men products shown (i.e., categories that have at least one Men product or whose name/slug contains "men")
 - Schema-robust: supports products.product_category (slug or id), products.category_id, or a join table
 - Other UI and behaviour left as in your original file
*/

define('DB_HOST', 'localhost');
define('DB_USER', 'alihairw');
define('DB_PASS', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

define('CURRENCY_SYMBOL', '$');
define('STORAGE_KEY', 'ali_hair_cart_v1');

/* If you know the product_category id that represents "Men", set it here. Leave 0 to auto-detect. */
define('FORCE_MEN_CATEGORY_ID', 0);

/* -------------------------------
   DB connection
   ------------------------------- */
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn && $conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    $conn = null;
}
if ($conn) $conn->set_charset('utf8mb4');

function h(string $s = ''): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* -------------------------------
   Helpers
   ------------------------------- */
function normalizeImagesFromRow(array $row, array $cols = ['image_placeholder','image_url','image']): array {
    $out = [];
    foreach ($cols as $col) {
        if (!isset($row[$col])) continue;
        $stored = $row[$col];
        if ($stored === null || trim((string)$stored) === '') continue;
        $decoded = json_decode((string)$stored, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $it) {
                if (is_string($it) && $it !== '') $out[] = $it;
                elseif (is_array($it)) {
                    if (!empty($it['url'])) $out[] = $it['url'];
                    if (!empty($it['path'])) $out[] = $it['path'];
                    if (!empty($it['filename'])) $out[] = $it['filename'];
                }
            }
        } else {
            $parts = preg_split('/\s*,\s*/', trim((string)$stored));
            foreach ($parts as $p) if ($p !== '') $out[] = $p;
        }
        if (!empty($out)) break;
    }

    $normalized = [];
    foreach (array_values(array_unique($out)) as $u) {
        $u = trim((string)$u);
        if ($u === '') continue;
        if (filter_var($u, FILTER_VALIDATE_URL)) { $normalized[] = $u; continue; }
        $candidate = ltrim($u, '/');
        if (stripos($candidate, 'admin/admin/images') === 0 || stripos($candidate, 'images/') === 0 || stripos($candidate, 'media/') === 0) {
            $normalized[] = $candidate;
            continue;
        }
        $normalized[] = 'uploads/' . $candidate;
    }
    return $normalized;
}

/* -------------------------------
   Inputs
   ------------------------------- */
$perPage = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$sort = $_GET['sort'] ?? 'new'; // new | low | high
$filterCategoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

/* -------------------------------
   Ensure DB available
   ------------------------------- */
if (!$conn) {
    echo "<!doctype html><html><body><h1>Database connection failed</h1></body></html>";
    exit;
}

/* -------------------------------
   Load product_category table (schema-safe)
   ------------------------------- */
$categories = [];
$catCols = [];
$cr = $conn->query("SHOW COLUMNS FROM `product_category`");
if ($cr) {
    while ($c = $cr->fetch_assoc()) $catCols[] = $c['Field'];
    $cr->free();
}

$idCol = null; $nameCol = null; $slugCol = null;
foreach (['id','category_id','product_category_id','pk','cat_id'] as $cand) {
    if (in_array($cand, $catCols, true)) { $idCol = $cand; break; }
}
if ($idCol === null && !empty($catCols)) $idCol = $catCols[0];

foreach (['name','title','category_name','cat_name','label'] as $cand) {
    if (in_array($cand, $catCols, true)) { $nameCol = $cand; break; }
}
if ($nameCol === null) {
    foreach ($catCols as $c) { if ($c !== $idCol) { $nameCol = $c; break; } }
}

foreach (['slug','url_slug','category_slug'] as $cand) {
    if (in_array($cand, $catCols, true)) { $slugCol = $cand; break; }
}

if (!empty($catCols) && $idCol !== null && $nameCol !== null) {
    $idColEsc = $conn->real_escape_string($idCol);
    $nameColEsc = $conn->real_escape_string($nameCol);
    $slugColEsc = $slugCol ? $conn->real_escape_string($slugCol) : null;

    $selectParts = ["`$idColEsc` AS cat_id", "`$nameColEsc` AS cat_name"];
    if ($slugColEsc) $selectParts[] = "`$slugColEsc` AS cat_slug";
    $sql = "SELECT " . implode(', ', $selectParts) . " FROM `product_category` ORDER BY `$nameColEsc` ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $categories[] = [
                'id' => $r['cat_id'],
                'name' => $r['cat_name'],
                'slug' => isset($r['cat_slug']) ? $r['cat_slug'] : null
            ];
        }
        $stmt->close();
    } else {
        error_log('product_category select prepare failed: ' . $conn->error);
    }
} else {
    error_log('product_category schema unexpected or empty: ' . implode(',', $catCols));
}

/* -------------------------------
   Resolve Men category id (optional)
   ------------------------------- */
$menCategoryId = (int)FORCE_MEN_CATEGORY_ID;
if ($menCategoryId <= 0 && !empty($categories)) {
    foreach ($categories as $c) {
        $slug = isset($c['slug']) ? mb_strtolower((string)$c['slug'],'UTF-8') : null;
        $name = isset($c['name']) ? mb_strtolower((string)$c['name'],'UTF-8') : null;
        if ($slug === 'men' || (strpos((string)$name,'men') !== false)) {
            $menCategoryId = (int)$c['id'];
            break;
        }
    }
}

/* -------------------------------
   Schema introspection for products
   ------------------------------- */
$availableCols = [];
$colRes = $conn->query("SHOW COLUMNS FROM `products`");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) $availableCols[] = $c['Field'];
    $colRes->free();
}
$has = function(string $col) use ($availableCols) { return in_array($col, $availableCols, true); };

/* Detect join table name if present */
$joinTable = null;
$tablesRes = $conn->query("SHOW TABLES");
if ($tablesRes) {
    while ($t = $tablesRes->fetch_row()) {
        $tbl = $t[0];
        $candidates = [
            'product_category_map','product_categories','product_to_category','product_category_product',
            'product_category_relation','product_categorylink','product_category_assoc',
            'product_to_product_category','product_category_x_product','product_category_product'
        ];
        if (in_array($tbl, $candidates, true)) { $joinTable = $tbl; break; }
    }
    $tablesRes->free();
}

/* -------------------------------
   Build select list for product query
   ------------------------------- */
$selectCols = ['p.id'];
if ($has('name')) $selectCols[] = 'p.name';
if ($has('price')) $selectCols[] = 'p.price';
foreach (['sku','short_description','image','image_url','image_placeholder','category_id','category','tags','created_at'] as $oc) {
    if ($has($oc) && !in_array("p.$oc", $selectCols, true)) $selectCols[] = "p.$oc";
}
$selectSql = implode(', ', $selectCols);

/* -------------------------------
   Build WHERE for Men products (or apply side-panel selected category if provided)
   ------------------------------- */
$where = [];
$params = [];
$types = '';
$fromSql = '`products` p';

$useProductCategoryCol = $has('product_category'); // may store slug or id
$useProductCategoryIdCol = $has('category_id');     // numeric category id column
$haveJoin = $joinTable !== null;

if ($filterCategoryId > 0) {
    // user clicked a category in side panel (which will be limited to Men-related categories)
    $selCat = null;
    foreach ($categories as $c) if ((int)$c['id'] === $filterCategoryId) { $selCat = $c; break; }

    if ($useProductCategoryCol) {
        if (!empty($selCat['slug'])) {
            $where[] = 'LOWER(p.product_category) = ?';
            $types .= 's';
            $params[] = mb_strtolower((string)$selCat['slug'], 'UTF-8');
        } else {
            $where[] = 'p.product_category = ?';
            $types .= 's';
            $params[] = (string)$filterCategoryId;
        }
    } elseif ($useProductCategoryIdCol) {
        $where[] = 'p.category_id = ?';
        $types .= 'i';
        $params[] = $filterCategoryId;
    } elseif ($haveJoin) {
        $fromSql = "`products` p JOIN `{$conn->real_escape_string($joinTable)}` pc ON pc.product_id = p.id";
        $where[] = 'pc.category_id = ?';
        $types .= 'i';
        $params[] = $filterCategoryId;
    } else {
        // fallback to name match (rare)
        if ($selCat !== null && $has('name')) {
            $where[] = 'LOWER(p.name) LIKE ?';
            $types .= 's';
            $params[] = '%' . mb_strtolower((string)$selCat['name'], 'UTF-8') . '%';
        }
    }

    // ALSO ensure results remain Men-only (defensive): require men-match as well when possible
    $menSub = [];
    if ($has('category')) $menSub[] = "LOWER(p.category) = 'men'";
    if ($has('tags')) { $menSub[] = 'LOWER(p.tags) LIKE ?'; $types .= 's'; $params[] = '%men%'; }
    if ($has('name')) { $menSub[] = 'LOWER(p.name) LIKE ?'; $types .= 's'; $params[] = '%men%'; }
    if ($menSub) $where[] = '(' . implode(' OR ', $menSub) . ')';

} else {
    // No specific category selected: show Men products
    if ($has('category')) {
        $where[] = "LOWER(p.category) = 'men'";
    } elseif ($has('category_id')) {
        if ($menCategoryId <= 0) {
            // fallback to tags/name containing "men"
            $sub = [];
            if ($has('tags')) { $sub[] = 'LOWER(p.tags) LIKE ?'; $types .= 's'; $params[] = '%men%'; }
            if ($has('name')) { $sub[] = 'LOWER(p.name) LIKE ?'; $types .= 's'; $params[] = '%men%'; }
            if ($sub) $where[] = '(' . implode(' OR ', $sub) . ')';
            else {
                echo "<!doctype html><html><body><h1>Men category id not found. Set FORCE_MEN_CATEGORY_ID or ensure product_category contains a Men entry.</h1></body></html>";
                exit;
            }
        } else {
            $where[] = 'p.category_id = ?';
            $types .= 'i';
            $params[] = $menCategoryId;
        }
    } elseif ($haveJoin) {
        if ($menCategoryId <= 0) {
            echo "<!doctype html><html><body><h1>Men category id not found for join table match. Set FORCE_MEN_CATEGORY_ID or ensure product_category contains a Men entry.</h1></body></html>";
            exit;
        }
        $jt = $joinTable;
        $fromSql = "`products` p JOIN `{$conn->real_escape_string($jt)}` pc ON pc.product_id = p.id";
        $where[] = 'pc.category_id = ?';
        $types .= 'i';
        $params[] = $menCategoryId;
    } else {
        $sub = [];
        if ($has('tags')) { $sub[] = 'LOWER(p.tags) LIKE ?'; $types .= 's'; $params[] = '%men%'; }
        if ($has('name')) { $sub[] = 'LOWER(p.name) LIKE ?'; $types .= 's'; $params[] = '%men%'; }
        if ($sub) $where[] = '(' . implode(' OR ', $sub) . ')';
        else {
            echo "<!doctype html><html><body><h1>Cannot reliably filter products to Men because products table lacks category/category_id/tags/name and no join table found.</h1></body></html>";
            exit;
        }
    }
}

$whereSql = $where ? implode(' AND ', $where) : '1';

/* -------------------------------
   Count total (for pagination)
   ------------------------------- */
$total = 0;
$countSql = "SELECT COUNT(DISTINCT p.id) AS cnt FROM {$fromSql} WHERE {$whereSql}";
$stmt = $conn->prepare($countSql);
if ($stmt) {
    if ($types !== '') $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $cres = $stmt->get_result();
    $crow = $cres->fetch_assoc();
    $total = (int)($crow['cnt'] ?? 0);
    $stmt->close();
}

/* -------------------------------
   Sorting + fetch page
   ------------------------------- */
$orderSql = 'p.id DESC';
if ($sort === 'low' && $has('price')) $orderSql = 'p.price ASC';
if ($sort === 'high' && $has('price')) $orderSql = 'p.price DESC';

$products = [];
$pages = 1;
$sql = "SELECT DISTINCT {$selectSql} FROM {$fromSql} WHERE {$whereSql} ORDER BY {$orderSql} LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($types === '') {
        $stmt->bind_param('ii', $perPage, $offset);
    } else {
        $bindTypes = $types . 'ii';
        $bindParams = array_merge($params, [$perPage, $offset]);
        $stmt->bind_param($bindTypes, ...$bindParams);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $products[] = $r;
    $stmt->close();
    $pages = max(1, (int)ceil($total / $perPage));
}

/* -------------------------------
   Compute category counts (only for categories we loaded)
   We'll use these counts to determine which categories relate to Men products.
   ------------------------------- */
$catCounts = [];
if (!empty($categories)) {
    $ids = array_map(fn($r) => (int)$r['id'], $categories);

    // If products.product_category exists and stores slugs
    if ($useProductCategoryCol = $has('product_category')) {
        $slugToId = [];
        foreach ($categories as $c) {
            if (!empty($c['slug'])) $slugToId[mb_strtolower((string)$c['slug'],'UTF-8')] = (int)$c['id'];
        }
        if (!empty($slugToId)) {
            $sql = "SELECT LOWER(product_category) AS pc, COUNT(*) AS cnt FROM products WHERE product_category IS NOT NULL AND product_category <> '' GROUP BY LOWER(product_category)";
            $res = $conn->query($sql);
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $pc = $r['pc'];
                    if (isset($slugToId[$pc])) $catCounts[$slugToId[$pc]] = (int)$r['cnt'];
                }
                $res->free();
            }
        } else {
            // product_category stored as numeric string
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types2 = str_repeat('i', count($ids));
            $sql = "SELECT product_category AS pc, COUNT(*) AS cnt FROM products WHERE product_category IN ($placeholders) GROUP BY product_category";
            if ($stmt = $conn->prepare($sql)) {
                $tmp = $ids;
                $refs = [];
                $refs[] = &$types2;
                for ($i = 0; $i < count($tmp); $i++) $refs[] = &$tmp[$i];
                call_user_func_array([$stmt, 'bind_param'], $refs);
                $stmt->execute();
                $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) {
                    $key = (int)$row['pc'];
                    $catCounts[$key] = (int)$row['cnt'];
                }
                $stmt->close();
            }
        }
    } elseif ($useProductCategoryIdCol = $has('category_id')) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types2 = str_repeat('i', count($ids));
        $sql = "SELECT category_id, COUNT(*) AS cnt FROM products WHERE category_id IN ($placeholders) GROUP BY category_id";
        if ($stmt = $conn->prepare($sql)) {
            $tmp = $ids;
            $refs = [];
            $refs[] = &$types2;
            for ($i = 0; $i < count($tmp); $i++) $refs[] = &$tmp[$i];
            call_user_func_array([$stmt, 'bind_param'], $refs);
            $stmt->execute();
            $r = $stmt->get_result();
            while ($row = $r->fetch_assoc()) {
                $catCounts[(int)$row['category_id']] = (int)$row['cnt'];
            }
            $stmt->close();
        }
    } elseif ($haveJoin) {
        $jr = $conn->query("SHOW COLUMNS FROM `{$conn->real_escape_string($joinTable)}`");
        $jcols = [];
        if ($jr) {
            while ($cc = $jr->fetch_assoc()) $jcols[] = $cc['Field'];
            $jr->free();
        }
        $catCol = in_array('category_id', $jcols, true) ? 'category_id' : (in_array('product_category_id', $jcols, true) ? 'product_category_id' : null);
        $prodCol = in_array('product_id', $jcols, true) ? 'product_id' : (in_array('product', $jcols, true) ? 'product' : null);
        if ($catCol !== null && $prodCol !== null) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $types2 = str_repeat('i', count($ids));
            $sql = "SELECT {$catCol} AS cid, COUNT(DISTINCT {$prodCol}) AS cnt FROM `{$conn->real_escape_string($joinTable)}` WHERE {$catCol} IN ($placeholders) GROUP BY {$catCol}";
            if ($stmt = $conn->prepare($sql)) {
                $tmp = $ids;
                $refs = [];
                $refs[] = &$types2;
                for ($i = 0; $i < count($tmp); $i++) $refs[] = &$tmp[$i];
                call_user_func_array([$stmt, 'bind_param'], $refs);
                $stmt->execute();
                $r = $stmt->get_result();
                while ($row = $r->fetch_assoc()) {
                    $catCounts[(int)$row['cid']] = (int)$row['cnt'];
                }
                $stmt->close();
            }
        }
    }
}

/* -------------------------------
   Filter categories to those related to Men products
   Criteria:
   - category name or slug contains 'men' OR
   - category id equals resolved Men category id OR
   - category has non-zero product count in $catCounts
   Result: $categories will only contain Men-related categories for side panel
   ------------------------------- */
$menCategories = [];
if (!empty($categories)) {
    foreach ($categories as $c) {
        $cid = (int)$c['id'];
        $name = isset($c['name']) ? mb_strtolower((string)$c['name'],'UTF-8') : '';
        $slug = isset($c['slug']) ? mb_strtolower((string)$c['slug'],'UTF-8') : '';
        $isMenName = (strpos($name, 'men') !== false) || ($slug === 'men') || (strpos($slug, 'men') !== false);
        $hasCount = isset($catCounts[$cid]) && $catCounts[$cid] > 0;
        if ($isMenName || $cid === $menCategoryId || $hasCount) {
            $menCategories[] = $c;
        }
    }
    $categories = array_values(array_unique($menCategories, SORT_REGULAR));
}

/* -------------------------------
   Render HTML (keeps your UI/JS/layout intact)
   ------------------------------- */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
     <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/favicon.png">
  <title>Men's Wigs — Ali Hair Wigs</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --primary:#7b3f00; --secondary:#f7e0c4; --muted:#6b7280; --accent:#2b6cb0; --max-width:1200px; }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;background:#fafafa;color:#111827;margin:0;-webkit-font-smoothing:antialiased}
    .container{max-width:var(--max-width);margin:24px auto;padding:0 16px}
    header.site-header{background:#fff;box-shadow:0 1px 0 rgba(0,0,0,0.04);position:sticky;top:0;z-index:90}
    .header-inner{max-width:var(--max-width);margin:0 auto;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .logo{display:flex;align-items:center;gap:10px;text-decoration:none;font-weight:800;color:var(--primary)}
    .logo img{height:40px;width:40px;object-fit:contain;flex:0 0 40px}
    .logo-text{display:inline-flex;flex-direction:column;line-height:1}
    .logo-main{color:var(--primary);font-weight:800;font-size:1rem}
    .logo-sub{color:#374151;font-weight:800;font-size:.95rem}
    @media(min-width:640px){ .logo img{height:44px;width:44px} .logo-main{font-size:1.15rem} .logo-sub{font-size:1rem} }

    /* Desktop nav: allow wrap into two rows instead of breaking items */
    .nav-desktop{display:none;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap}
    .nav-desktop a{color:#4b5563;text-decoration:none;font-weight:600;padding:8px 10px;border-radius:8px;white-space:nowrap}
    .nav-desktop a:hover{background:rgba(123,63,0,0.04);color:var(--primary)}

    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:10px;background:transparent;border:1px solid transparent;cursor:pointer}
    .icon-btn:hover{background:#f8fafc}

    .cart-wrap{position:relative}
    #cart-count{position:absolute;top:-6px;right:-6px;background:#ef4444;color:#fff;font-weight:700;font-size:11px;line-height:18px;height:18px;min-width:18px;padding:0 5px;border-radius:999px;text-align:center;box-shadow:0 1px 0 rgba(0,0,0,0.12)}
    #cart-count.hide-zero{display:none!important}

        @media(min-width:768px){ .nav-desktop{display:flex} }
        .search-toggle { display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px; border-radius:10px; background:transparent; border:1px solid transparent; cursor:pointer; }
        .search-toggle:hover { background:#f8fafc; }
        .search-panel { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1200; }
        .search-panel.open { display:flex; backdrop-filter: blur(2px); }
        .search-box { background:#fff; padding:10px; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); display:flex; gap:8px; width:min(720px,96%); align-items:center; }
        .search-input-wide { border:1px solid #e6eef6; padding:10px 12px; border-radius:8px; width:100%; font-size:16px; }
        .search-close { background:transparent; border:0; cursor:pointer; font-size:18px; color:#374151; }
         /* Features */
    .features{margin-top:-48px;position:relative;z-index:20}
    .features-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;background:var(--card);padding:16px;border-radius:12px;box-shadow:0 10px 30px rgba(15,23,42,0.06);border:1px solid #eef2f6}
    @media(min-width:768px){ .features-grid{grid-template-columns:repeat(4,1fr)} }

    .hamburger{display:inline-flex;align-items:center;justify-content:center;padding:8px;border-radius:8px}
    @media(min-width:768px){ .hamburger{display:none} }

    .titlebar{display:flex;flex-direction:column;gap:12px;margin-bottom:16px}
    .titlebar-row{display:flex;align-items:center;justify-content:space-between;gap:12px}
    .titlebar h1{margin:0;font-size:1.25rem;font-weight:800}
    @media(min-width:640px){ .titlebar h1{font-size:1.6rem} }

    .page-grid{display:grid;grid-template-columns:1fr;gap:18px}
    @media(min-width:1024px){ .page-grid{grid-template-columns:260px 1fr} }

    .side-panel{background:#fff;border:1px solid #eef2f6;border-radius:12px;padding:14px}
    .side-title{font-weight:800;color:var(--primary);margin-bottom:10px}
    .cat-list{display:flex;flex-direction:column;gap:6px}
    .cat-item{display:flex;justify-content:space-between;align-items:center;padding:8px;border-radius:8px;text-decoration:none;color:#374151}
    .cat-item:hover{background:#f8fafc}
    .cat-item.active{background:linear-gradient(90deg,#fff7f0, #fff);border:1px solid #f2e0d0;color:var(--primary);font-weight:700}

    .grid{display:grid;gap:16px;grid-template-columns:repeat(2,1fr)}
    @media(min-width:640px){ .grid{grid-template-columns:repeat(3,1fr)} }
    @media(min-width:1024px){ .grid{grid-template-columns:repeat(4,1fr)} }

    .card{background:#fff;border:1px solid #eef2f6;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:10px;transition:transform .12s,box-shadow .12s}
    .card:hover{transform:translateY(-6px);box-shadow:0 10px 30px rgba(12,18,24,.06)}
    .thumb{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:8px;background:#f3f4f6}
    .product-name{font-weight:700;font-size:0.98rem;color:#111827}
    .product-desc{color:var(--muted);font-size:0.9rem}

    .btn{padding:10px 12px;border-radius:10px;font-weight:700;cursor:pointer}
    .btn-add{background:var(--primary);color:#fff;border:none}
    .btn-view{background:#fff;border:1px solid #e6eef6}

    .pagination{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-top:18px}
    .pagination a{padding:8px 12px;border-radius:8px;background:#fff;border:1px solid #e6eef6;color:#374151;text-decoration:none}
    .pagination a.active{background:var(--primary);color:#fff;border-color:var(--primary)}

    .search-toggle { display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px; border-radius:10px; background:transparent; border:1px solid transparent; cursor:pointer; }
    .search-toggle:hover { background:#f8fafc; }
    .search-panel { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1200; }
    .search-panel.open { display:flex; backdrop-filter: blur(2px); }
    .search-box { background:#fff; padding:10px; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); display:flex; gap:8px; width:min(720px,96%); align-items:center; }
    .search-input-wide { border:1px solid #e6eef6; padding:10px 12px; border-radius:8px; width:100%; font-size:16px; }
    .search-close { background:transparent; border:0; cursor:pointer; font-size:18px; color:#374151; }

    .cart-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);opacity:0;pointer-events:none;transition:opacity .25s;z-index:1200}
    .cart-backdrop.visible{opacity:1;pointer-events:auto}
    .cart-drawer{position:fixed;right:0;top:0;height:100vh;width:92vw;max-width:420px;background:#fff;box-shadow:-20px 0 40px rgba(0,0,0,.14);transform:translateX(110%);transition:transform .32s;z-index:1300;display:flex;flex-direction:column;border-top-left-radius:14px;border-bottom-left-radius:14px;overflow:hidden}
    .cart-drawer.open{transform:translateX(0)}
    .cart-item-img{width:72px;height:72px;aspect-ratio:1/1;object-fit:cover;border-radius:.5rem;flex:0 0 72px;box-shadow:0 6px 18px rgba(0,0,0,0.06)}

    /* Checkout / Continue buttons styling to match provided design */
    #checkout-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: var(--primary);
      color: #fff;
      padding: 10px 16px;
      border-radius: 10px;
      font-weight: 800;
      text-decoration: none;
      border: 0;
      box-shadow: 0 6px 18px rgba(123,63,0,0.14);
      transition: transform .12s, box-shadow .12s, opacity .12s;
    }
    #checkout-btn:hover { transform: translateY(-2px); opacity: .98; }
    #checkout-btn:active { transform: translateY(0); }

    #continue-shopping {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      background: #fff;
      color: #111827;
      padding: 10px 16px;
      border-radius: 10px;
      font-weight: 700;
      border: 1px solid #e6eef6;
      cursor: pointer;
      text-decoration: none;
      transition: transform .12s, box-shadow .12s, background .12s;
    }
    #continue-shopping:hover { background: #fafafa; transform: translateY(-2px); }

    .sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    :focus{outline:3px solid rgba(59,130,246,0.18);outline-offset:3px}

    .mobile-left-drawer { position:fixed; left:0; top:0; height:100vh; width:84vw; max-width:320px; background:#fff; box-shadow:20px 0 40px rgba(0,0,0,.12); transform:translateX(-110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:1400; padding:1rem; overflow:auto; }
    .mobile-left-drawer.open { transform:translateX(0); }
    .mobile-left-close { display:inline-flex; align-items:center; gap:6px; cursor:pointer; }

    /* hide category list on small screens by default; provide toggle in side-panel header if needed */
    .cat-list.mobile-hidden { display: none; }
    .cat-toggle-btn { display:inline-flex; align-items:center; gap:8px; padding:6px 8px; border-radius:8px; border:1px solid #eef2f6; background:#fff; cursor:pointer; font-weight:600; }
    @media(min-width:1024px) {
      .cat-toggle-btn { display:none; }
      .cat-list.mobile-hidden { display:flex; }
    }

    @media(prefers-reduced-motion:reduce){ *{transition:none!important} }
  </style>
</head>
<body class="min-h-screen">

<header class="site-header" role="banner">
  <div class="header-inner">
    <div style="display:flex;align-items:center;gap:12px">
      <!-- Mobile hamburger (visible on small screens) -->
      <button id="hamburger" aria-expanded="false" aria-controls="mobile-left-drawer" class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-100" type="button">
        <svg id="hamburger-open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <svg id="hamburger-close" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        <span class="sr-only">Toggle menu</span>
      </button>

      <a href="index.php" class="logo" aria-label="Ali Hair Wigs home">
        <img src="uploads/ahw.png" alt="Ali Hair Wigs logo" onerror="this.onerror=null;this.src='https://placehold.co/80x80/7b3f00/f7e0c4?text=Logo'">
        <span class="logo-text"><span class="logo-main">ALI HAIR</span><span class="logo-sub">WIGS</span></span>
      </a>
    </div>

    <div class="nav-wrap" aria-hidden="false">
      <nav class="nav-desktop" aria-label="Primary navigation">
        <a href="index.php">Home</a>
        <a href="mens_wigs.php">Men's Wigs</a>
        <a href="womens_wigs.php">Women's Wigs</a>
        <a href="about.php">About</a>
        <a href="map.php">Store Location</a>
        <a href="contact.php">Contact</a>
        <a href="login.php">Account</a>
      </nav>
    </div>

    <div style="display:flex;align-items:center;gap:8px">
      <!-- Search toggle button -->
      <button id="search-toggle" class="search-toggle" aria-expanded="false" aria-controls="search-panel" aria-label="Open search">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M5.5 11a5.5 5.5 0 1111 0 5.5 5.5 0 01-11 0z"/>
        </svg>
      </button>

      <div class="relative ml-2">
        <button id="cart-toggle" aria-label="Cart" class="p-2 rounded-md text-gray-700 hover:bg-gray-100 relative" type="button">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h13M7 13H5.4M16 16a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
          </svg>
          <span id="cart-count" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">0</span>
        </button>
      </div>
    </div>
  </div>
</header>

<!-- Mobile left drawer (menu) -->
<div id="mobile-left-drawer" class="mobile-left-drawer" aria-hidden="true" role="navigation">
  <div class="flex items-center justify-between mb-4">
    <div class="flex items-center gap-3">
      <img src="uploads/ahw.png" alt="Logo" class="h-8 w-8 object-contain">
      <div class="font-bold text-lg text-[var(--primary)]">ALI HAIR WIGS</div>
    </div>
    <button id="mobile-left-close" class="mobile-left-close text-gray-700 p-2 rounded-md hover:bg-gray-100" aria-label="Close menu">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <div class="drawer-scroll">
      
            <a href="index.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Home</a>
            <a href="mens_wigs.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Men's Wigs</a>
            <a href="womens_wigs.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Women's Wigs</a>
            <a href="about.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">About</a>
            <a href="map.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Stoe Location</a>
            <a href="contact.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Contact</a>
            <a href="login.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Account</a>

          
            </div>
</div>

<!-- Search overlay panel -->
<div id="search-panel" class="search-panel" aria-hidden="true">
  <div class="search-box" role="search" aria-label="Site search">
    <form id="search-form" action="search.php" method="get" style="display:flex;flex:1;gap:8px;align-items:center">
      <label for="q-wide" class="sr-only">Search products</label>
      <input id="q-wide" name="q" type="search" class="search-input-wide" placeholder="Search products, styles..." aria-label="Search products" autocomplete="off" />
    </form>
    <button id="search-close" class="search-close" aria-label="Close search">&times;</button>
  </div>
</div>

<main class="container">
  <div class="titlebar">
    <div class="titlebar-row">
      <div>
        <h1>Men's Wigs</h1>
        <div style="color:var(--muted)"><?php echo (int)$total; ?> results<?php if ($filterCategoryId>0) echo ' — filtered'; ?></div>
      </div>

      <form method="get" style="display:flex;gap:8px;align-items:center">
        <input type="hidden" name="page" value="1" />
        <?php if ($filterCategoryId>0): ?>
          <input type="hidden" name="category_id" value="<?php echo (int)$filterCategoryId; ?>" />
        <?php endif; ?>
        <select name="sort" onchange="this.form.submit()" class="rounded border px-3 py-2 text-sm">
          <option value="new" <?php if($sort==='new') echo 'selected'; ?>>Newest</option>
          <option value="low" <?php if($sort==='low') echo 'selected'; ?>>Price: Low to High</option>
          <option value="high" <?php if($sort==='high') echo 'selected'; ?>>Price: High to Low</option>
        </select>
      </form>
    </div>
  </div>

  <div class="page-grid" role="region" aria-label="Products and categories">
    <!-- Side categories panel (only Men-related categories) -->
    <aside class="side-panel" aria-label="Product categories">
      <div class="side-title">
        Product Categories
        <button id="cat-toggle" class="cat-toggle-btn" aria-expanded="false" aria-controls="cat-list">Categories</button>
      </div>

      <nav id="cat-list" class="cat-list mobile-hidden" aria-label="Categories list">
        <?php if (empty($categories)): ?>
          <div class="text-sm text-gray-500">No men categories found.</div>
        <?php else: ?>
          <?php
            $allActive = $filterCategoryId <= 0 ? 'active' : '';
            $baseParams = $_GET;
            unset($baseParams['category_id'], $baseParams['page']);
            $allHref = '?' . http_build_query($baseParams);
          ?>
          <a href="<?php echo h($allHref); ?>" class="cat-item <?php echo $allActive; ?>">
            <span>All</span>
            <span style="color:var(--muted)"><?php echo (int)$total; ?></span>
          </a>

          <?php foreach ($categories as $c):
            $cid = (int)$c['id'];
            $active = $filterCategoryId === $cid ? 'active' : '';
            $bp = $_GET;
            $bp['category_id'] = $cid;
            $bp['page'] = 1;
            $href = '?' . http_build_query($bp);
            $count = $catCounts[$cid] ?? null;
          ?>
            <a href="<?php echo h($href); ?>" class="cat-item <?php echo $active; ?>" aria-current="<?php echo $active ? 'true' : 'false'; ?>">
              <span><?php echo h($c['name']); ?></span>
              <span style="color:var(--muted)"><?php echo ($count !== null) ? (int)$count : '—'; ?></span>
            </a>
          <?php endforeach; ?>
        <?php endif; ?>
      </nav>
    </aside>

    <!-- Products listing (Men products only) -->
    <section>
      <div class="grid" id="productGrid">
        <?php if (empty($products)): ?>
          <div class="text-gray-500">No men's wigs found for the selected criteria.</div>
        <?php else: foreach ($products as $p):
          $imgs = normalizeImagesFromRow($p);
          $img = $imgs[0] ?? 'https://placehold.co/600x600/7b3f00/f7e0c4?text=Img';
          $name = $p['name'] ?? 'Product';
          $price = isset($p['price']) ? number_format((float)$p['price'],2) : '0.00';
        ?>
          <article class="card" data-price="<?php echo (float)($p['price'] ?? 0); ?>">
            <a href="product_details.php?id=<?php echo (int)($p['id'] ?? 0); ?>" style="text-decoration:none;color:inherit">
              <img src="<?php echo h($img); ?>" alt="<?php echo h($name); ?>" class="thumb" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/600x600/ffffff/000000?text=Img'">
            </a>

            <div class="product-name"><?php echo h($name); ?></div>
            <?php if (!empty($p['short_description'])): ?>
              <div class="product-desc"><?php echo h($p['short_description']); ?></div>
            <?php endif; ?>

            <div class="flex items-center justify-between">
              <div style="color:var(--primary);font-weight:800"><?php echo CURRENCY_SYMBOL . ' ' . $price; ?></div>
              <?php if (!empty($p['sku'])): ?>
                <div class="text-sm" style="color:#6b7280">SKU: <?php echo h($p['sku']); ?></div>
              <?php endif; ?>
            </div>

            <div style="margin-top:auto;display:flex;gap:8px">
              <button class="btn btn-add add-to-cart"
                      data-product-id="<?php echo (int)($p['id'] ?? 0); ?>"
                      data-product-name="<?php echo h($name); ?>"
                      data-product-price="<?php echo (float)($p['price'] ?? 0); ?>"
                      data-product-img="<?php echo h($img); ?>">
                Add
              </button>
              <a href="product_details.php?id=<?php echo (int)($p['id'] ?? 0); ?>" class="btn btn-view">View</a>
            </div>
          </article>
        <?php endforeach; endif; ?>
      </div>

      <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
          <?php
            $range = 3;
            $start = max(1, $page - $range);
            $end = min($pages, $page + $range);
            $base = $_GET;
            if ($page > 1) { $base['page'] = $page - 1; ?>
              <a href="?<?php echo http_build_query($base); ?>">&larr; Prev</a>
          <?php }
            for ($i = $start; $i <= $end; $i++): $base['page'] = $i; ?>
              <a class="<?php if($i === $page) echo 'active'; ?>" href="?<?php echo http_build_query($base); ?>"><?php echo $i; ?></a>
          <?php endfor;
            if ($page < $pages) { $base['page'] = $page + 1; ?>
              <a href="?<?php echo http_build_query($base); ?>">Next &rarr;</a>
          <?php } ?>
        </nav>
      <?php endif; ?>
    </section>
  </div>
</main>

<!-- Cart backdrop -->
<div id="cart-backdrop" class="cart-backdrop" aria-hidden="true"></div>

<!-- Cart drawer -->
<aside id="cart-drawer" class="cart-drawer" aria-hidden="true" role="dialog" aria-label="Shopping cart">
  <div class="p-4 border-b flex items-center justify-between">
    <div class="font-semibold">Your Cart</div>
    <div class="flex items-center gap-2">
      <button id="cart-clear" class="text-sm text-gray-600 hover:underline">Clear</button>
      <button id="cart-close" aria-label="Close cart" class="p-2 rounded-md hover:bg-gray-100">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>

  <div id="cart-items" class="p-4 overflow-auto" style="flex:1 1 auto;">
    <div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>
  </div>

  <div class="cart-footer p-4 border-t">
    <div class="flex items-center justify-between mb-3">
      <div class="text-sm text-gray-600">Subtotal</div>
      <div id="cart-subtotal" class="text-lg font-bold text-[var(--primary)]"><?php echo CURRENCY_SYMBOL . ' 0.00'; ?></div>
    </div>
    <div class="flex gap-3">
      <a href="checkout.php" id="checkout-btn" class="inline-block">Checkout</a>
      <button id="continue-shopping" class="inline-block">Continue</button>
    </div>
    <div class="text-xs text-gray-500 mt-3">Shipping calculated at checkout</div>
  </div>
</aside>

<footer class="bg-gray-800 text-white py-12">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-x-1 gap-y-4">

    <div>
      <h4 class="text-xl font-bold text-[var(--secondary)] mb-3">
        <a href="index.php" class="hover:text-[var(--secondary)]">Ali Hair Wigs</a>
      </h4>
      <p class="text-gray-400 text-sm">Your Trusted Human Hair Wig & Extension Partner.</p>
    </div>

    <div>
      <h4 class="text-lg font-semibold mb-3">Quick Links</h4>
      <ul class="space-y-2 text-gray-400 text-sm">
        <li><a href="our_mission.php" class="hover:text-[var(--secondary)]">Our Mission</a></li>
        <li><a href="shipping.php" class="hover:text-[var(--secondary)]">Shipping & Returns</a></li>
        <li><a href="privecy.php" class="hover:text-[var(--secondary)]">Privacy Policy</a></li>
      </ul>
    </div>

    <div>
      <h4 class="text-lg font-semibold mb-3">Customer Care</h4>
      <ul class="space-y-2 text-gray-400 text-sm">
        <li><a href="contact.php" class="hover:text-[var(--secondary)]">Contact Us</a></li>
        <li><a href="wig_care.php" class="hover:text-[var(--secondary)]">Wig Care Tips</a></li>
        <li><a href="login.php" class="hover:text-[var(--secondary)]">My Account</a></li>
      </ul>
    </div>

    <div>
      <h4 class="text-lg font-semibold mb-3">Connect</h4>

      <!-- Responsive grid: 2 columns on xs, 3 columns on sm+ -> 6 icons form two rows -->
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-2xl mb-4">
        <!-- Facebook -->
        <a href="https://www.facebook.com/alihairwigs" class="text-white hover:text-blue-500 flex items-center justify-center p-2" aria-label="Facebook">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="white" viewBox="0 0 24 24">
            <path d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2v-3h2v-2.3c0-2 1.2-3.1 3-3.1.9 0 1.8.1 2 .1v2.3h-1.1c-1 0-1.3.6-1.3 1.2V12h2.6l-.4 3h-2.2v7A10 10 0 0 0 22 12z"/>
          </svg>
        </a>

        <!-- WhatsApp -->
        <a href="https://wa.me/+8801920899031" class="text-white hover:text-green-500 flex items-center justify-center p-2" aria-label="WhatsApp">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="white" viewBox="0 0 24 24">
            <path d="M12.04 2c-5.52 0-10 4.48-10 10 0 1.76.46 3.47 1.34 4.99L2 22l5.12-1.34A10.02 10.02 0 0 0 12.04 22c5.52 0 10-4.48 10-10s-4.48-10-10-10zm0 18.3c-1.62 0-3.2-.43-4.58-1.25l-.33-.19-3.04.8.82-2.96-.2-.34A8.26 8.26 0 0 1 3.7 12c0-4.57 3.72-8.3 8.34-8.3 2.22 0 4.3.86 5.87 2.43a8.2 8.2 0 0 1 2.43 5.87c0 4.57-3.72 8.3-8.3 8.3zm4.48-6.25c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.55.12-.16.24-.63.78-.78.94-.14.16-.29.18-.53.06-.24-.12-1.01-.37-1.92-1.15-.71-.63-1.19-1.41-1.33-1.65-.14-.24-.02-.36.1-.48.1-.1.24-.24.36-.36.12-.12.16-.2.24-.33.08-.16.04-.3-.02-.42-.06-.12-.55-1.33-.76-1.82-.2-.49-.41-.42-.55-.42l-.47-.02c-.16 0-.42.06-.64.3-.22.24-.85.83-.85 2.03 0 1.2.87 2.36 1 2.52.12.16 1.72 2.63 4.3 3.69.6.26 1.06.41 1.42.52.6.19 1.16.16 1.6.1.49-.07 1.42-.58 1.62-1.14.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28z"/>
          </svg>
        </a>

        <!-- Instagram -->
        <a href="https://www.instagram.com/alihairwigs/?hl=en" class="text-white hover:text-pink-500 flex items-center justify-center p-2" aria-label="Instagram">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="white" viewBox="0 0 24 24">
            <path d="M7 2C4.2 2 2 4.2 2 7v10c0 2.8 2.2 5 5 5h10c2.8 0 5-2.2 5-5V7c0-2.8-2.2-5-5-5H7zm10 2c1.7 0 3 1.3 3 3v10c0 1.7-1.3 3-3 3H7c-1.7 0-3-1.3-3-3V7c0-1.7 1.3-3 3-3h10zm-5 3a5 5 0 1 0 .001 10.001A5 5 0 0 0 12 7zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm4.5-.8a1.2 1.2 0 1 0 0-2.4 1.2 1.2 0 0 0 0 2.4z"/>
          </svg>
        </a>

        <!-- Twitter/X -->
        <a href="https://x.com/alihairwig" class="text-white hover:text-cyan-400 flex items-center justify-center p-2" aria-label="Twitter/X">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="white" viewBox="0 0 24 24">
            <path d="M18 2H6a4 4 0 0 0-4 4v12a4 4 0 0 0 4 4h12a4 4 0 0 0 4-4V6a4 4 0 0 0-4-4zm-2.1 6.7l-2.6 3 3 4.3h-2.3l-1.9-2.8-2.1 2.8h-2.3l3.2-4.3-2.7-3h2.3l1.8 2.4 1.9-2.4h2.7z"/>
          </svg>
        </a>

        <!-- LinkedIn -->
        <a href="https://www.linkedin.com/in/ali-hair-wigs-410b69389/" class="text-white hover:text-blue-400 flex items-center justify-center p-2" aria-label="LinkedIn">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="white" viewBox="0 0 24 24">
            <path d="M4.98 3.5C4.98 4.88 3.86 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.5 8h4V23h-4V8zm7.5 0h3.8v2h.1c.5-1 1.8-2.1 3.7-2.1 4 0 4.7 2.6 4.7 6V23h-4v-7.3c0-1.7 0-3.9-2.4-3.9-2.4 0-2.8 1.8-2.8 3.7V23h-4V8z"/>
          </svg>
        </a>

        <!-- YouTube -->
        <a href="https://www.youtube.com/@ALIHAIRWIGS" class="text-white hover:text-red-500 flex items-center justify-center p-2" aria-label="YouTube">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="white" viewBox="0 0 24 24">
            <path d="M23.5 6.2s-.2-1.7-.9-2.5c-.8-.9-1.7-.9-2.1-1C17.5 2.3 12 2.3 12 2.3h-.1s-5.5 0-8.5.4c-.4.1-1.3.1-2.1 1-.7.8-.9 2.5-.9 2.5S0 8.2 0 10.2v1.7c0 1.9.2 4 0.4 6 0 0 .2 1.7.9 2.5.8.9 1.9.9 2.3 1C6.5 21.7 12 21.7 12 21.7s5.5 0 8.4-.4c.4-.1 1.3-.1 2.1-1 .7-.8.9-2.5.9-2.5.2-2 .4-4 .4-6V10.2c0-1.9-.2-4-.4-6zM9.7 15.6V8.4l6.4 3.6-6.4 3.6z"/>
          </svg>
        </a>
      </div>

      <p class="text-sm text-gray-400">Email: alihairwig.bd@gmail.com</p>
    </div>
  </div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 border-t border-gray-700 pt-4 text-center">
    <p class="text-sm text-gray-500">© <?php echo date('Y'); ?> Ali Hair Wigs. All rights reserved.</p>
    <p class="text-sm text-gray-500"> Developed By AR TECH SOLUTION.</p>
  </div>
</footer>


<script>
(function(){
  // Mobile left drawer
  const hamburger = document.getElementById('hamburger');
  const mobileLeft = document.getElementById('mobile-left-drawer');
  const mobileLeftClose = document.getElementById('mobile-left-close');
  const openIcon = document.getElementById('hamburger-open');
  const closeIcon = document.getElementById('hamburger-close');

  function trapFocus(container) {
    const focusable = container.querySelectorAll('a,button,textarea,input,select,[tabindex]:not([tabindex="-1"])');
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    function keyListener(e) {
      if (e.key !== 'Tab') return;
      if (e.shiftKey) {
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
      } else {
        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }
    container._keyListener = keyListener;
    container.addEventListener('keydown', keyListener);
  }
  function releaseFocus(container) {
    if (!container._keyListener) return;
    container.removeEventListener('keydown', container._keyListener);
    delete container._keyListener;
  }

  function openLeft() {
    if (!mobileLeft) return;
    mobileLeft.classList.add('open');
    mobileLeft.setAttribute('aria-hidden','false');
    if (openIcon) openIcon.classList.add('hidden');
    if (closeIcon) closeIcon.classList.remove('hidden');
    hamburger.setAttribute('aria-expanded','true');
    setTimeout(() => {
      const focusable = mobileLeft.querySelector('a,button');
      if (focusable) focusable.focus();
      trapFocus(mobileLeft);
    }, 80);
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
  }
  function closeLeft() {
    if (!mobileLeft) return;
    mobileLeft.classList.remove('open');
    mobileLeft.setAttribute('aria-hidden','true');
    if (openIcon) openIcon.classList.remove('hidden');
    if (closeIcon) closeIcon.classList.add('hidden');
    hamburger.setAttribute('aria-expanded','false');
    releaseFocus(mobileLeft);
    hamburger.focus();
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
  }
  if (hamburger) hamburger.addEventListener('click', () => {
    if (mobileLeft && mobileLeft.classList.contains('open')) closeLeft();
    else openLeft();
  });
  if (mobileLeftClose) mobileLeftClose.addEventListener('click', closeLeft);

  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeLeft(); closeSearch(); closeCart(); } });

  document.addEventListener('click', function(e) {
    if (mobileLeft && mobileLeft.classList.contains('open') && !mobileLeft.contains(e.target) && !hamburger.contains(e.target)) {
      closeLeft();
    }
  });

  // Search panel
  const searchToggle = document.getElementById('search-toggle');
  const searchPanel = document.getElementById('search-panel');
  const searchClose = document.getElementById('search-close');
  const searchInput = document.getElementById('q-wide');

  function openSearch() {
    if (!searchPanel) return;
    searchPanel.classList.add('open');
    searchPanel.setAttribute('aria-hidden','false');
    if (searchToggle) searchToggle.setAttribute('aria-expanded','true');
    setTimeout(()=> searchInput?.focus(), 60);
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
  }
  function closeSearch() {
    if (!searchPanel) return;
    searchPanel.classList.remove('open');
    searchPanel.setAttribute('aria-hidden','true');
    if (searchToggle) searchToggle.setAttribute('aria-expanded','false');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
    searchToggle?.focus();
  }
  if (searchToggle) searchToggle.addEventListener('click', (e)=>{ e.preventDefault(); if (searchPanel && searchPanel.classList.contains('open')) closeSearch(); else openSearch(); });
  if (searchClose) searchClose.addEventListener('click', closeSearch);
  if (searchPanel) {
    searchPanel.addEventListener('click', (e)=> { if (e.target === searchPanel) closeSearch(); });
  }

  // Category mobile toggle
  const catToggle = document.getElementById('cat-toggle');
  const catList = document.getElementById('cat-list');
  if (catToggle && catList) {
    catToggle.addEventListener('click', (e) => {
      e.preventDefault();
      const expanded = catList.classList.toggle('mobile-hidden') === false;
      catToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      if (expanded) setTimeout(()=> catList.scrollIntoView({behavior:'smooth', block:'start'}), 60);
    });
    function adjustCatListOnResize() {
      if (window.innerWidth >= 1024) {
        catList.classList.remove('mobile-hidden');
        catToggle.setAttribute('aria-expanded', 'true');
      } else {
        catList.classList.add('mobile-hidden');
        catToggle.setAttribute('aria-expanded', 'false');
      }
    }
    window.addEventListener('resize', adjustCatListOnResize);
    adjustCatListOnResize();
  }

  // Slide-out cart (localStorage-backed)
  const STORAGE_KEY = '<?php echo STORAGE_KEY; ?>';
  const cartToggle = document.getElementById('cart-toggle');
  const cartDrawer = document.getElementById('cart-drawer');
  const cartBackdrop = document.getElementById('cart-backdrop');
  const cartClose = document.getElementById('cart-close');
  const cartItemsEl = document.getElementById('cart-items');
  const cartCountEl = document.getElementById('cart-count');
  const cartSubtotalEl = document.getElementById('cart-subtotal');
  const cartClearBtn = document.getElementById('cart-clear');
  const continueBtn = document.getElementById('continue-shopping');

  function loadCart(){ try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e) { return {}; } }
  let cart = loadCart();

  function saveCart(){ try { localStorage.setItem(STORAGE_KEY, JSON.stringify(cart)); } catch(e){} renderCart(); }
  function getTotalItems(){ return Object.values(cart).reduce((s,it)=> s + (it.qty||0), 0); }
  function getSubtotal(){ return Object.values(cart).reduce((s,it)=> s + ((it.price||0) * (it.qty||0)), 0); }
  function formatCurrency(v){ return '<?php echo CURRENCY_SYMBOL; ?> ' + Number(v||0).toFixed(2); }

  function renderCart(){
    const entries = Object.entries(cart || {});
    if (cartCountEl) cartCountEl.textContent = getTotalItems();

    if (!entries.length) {
      cartItemsEl.innerHTML = '<div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>';
      if (cartSubtotalEl) cartSubtotalEl.textContent = formatCurrency(0);
      return;
    }

    const container = document.createElement('div');
    container.style.display = 'flex';
    container.style.flexDirection = 'column';
    container.style.gap = '10px';

    entries.forEach(([id,it])=>{
      const row = document.createElement('div');
      row.style.display = 'flex';
      row.style.gap = '12px';
      row.style.alignItems = 'flex-start';
      row.style.border = '1px solid #eef2f6';
      row.style.borderRadius = '10px';
      row.style.padding = '10px';

      const link = document.createElement('a');
      link.href = `product_details.php?id=${encodeURIComponent(id)}`;
      link.style.display = 'block';
      const img = document.createElement('img');
      img.src = it.img || 'https://placehold.co/80x80/7b3f00/f7e0c4?text=Img';
      img.alt = (it.name || 'Product');
      img.style.width = '72px';
      img.style.height = '72px';
      img.style.objectFit = 'cover';
      img.style.borderRadius = '8px';
      link.appendChild(img);

      const meta = document.createElement('div'); meta.style.flex = '1';
      const nameEl = document.createElement('div'); nameEl.style.fontWeight = '700'; nameEl.textContent = it.name;
      const subEl = document.createElement('div'); subEl.style.color = '#6b7280'; subEl.style.fontSize = '13px'; subEl.textContent = `${formatCurrency(it.price)} each`;
      meta.appendChild(nameEl); meta.appendChild(subEl);

      const actions = document.createElement('div'); actions.style.textAlign = 'right';
      const priceEl = document.createElement('div'); priceEl.style.fontWeight = '800'; priceEl.style.color = 'var(--primary)'; priceEl.textContent = formatCurrency((it.price||0) * (it.qty||0));
      const qtyWrap = document.createElement('div'); qtyWrap.style.display = 'flex'; qtyWrap.style.gap = '8px'; qtyWrap.style.alignItems = 'center'; qtyWrap.style.justifyContent = 'flex-end';
      const decBtn = document.createElement('button'); decBtn.type = 'button'; decBtn.textContent = '−'; decBtn.style.padding='6px'; decBtn.dataset.action='decrease'; decBtn.dataset.id=id;
      const qtyBadge = document.createElement('div'); qtyBadge.style.padding='6px 10px'; qtyBadge.style.border='1px solid #eef2f6'; qtyBadge.style.borderRadius='8px'; qtyBadge.textContent = it.qty;
      const incBtn = document.createElement('button'); incBtn.type='button'; incBtn.textContent = '+'; incBtn.style.padding='6px'; incBtn.dataset.action='increase'; incBtn.dataset.id=id;
      const removeBtn = document.createElement('button'); removeBtn.type='button'; removeBtn.textContent='Remove'; removeBtn.style.display='block'; removeBtn.style.marginTop='8px'; removeBtn.dataset.action='remove'; removeBtn.dataset.id = id;
      qtyWrap.appendChild(decBtn); qtyWrap.appendChild(qtyBadge); qtyWrap.appendChild(incBtn);
      actions.appendChild(priceEl); actions.appendChild(qtyWrap); actions.appendChild(removeBtn);

      row.appendChild(link);
      row.appendChild(meta);
      row.appendChild(actions);
      container.appendChild(row);

      [decBtn, incBtn, removeBtn].forEach(btn=>{
        btn.addEventListener('click', (e)=>{
          e.stopPropagation(); e.preventDefault();
          const action = btn.getAttribute('data-action');
          const pid = btn.getAttribute('data-id');
          if (!cart[pid]) return;
          if (action === 'increase') { cart[pid].qty += 1; }
          else if (action === 'decrease') { cart[pid].qty = Math.max(1, cart[pid].qty - 1); }
          else if (action === 'remove') { delete cart[pid]; }
          saveCart();
        });
      });
    });

    cartItemsEl.innerHTML = '';
    cartItemsEl.appendChild(container);
    if (cartSubtotalEl) cartSubtotalEl.textContent = formatCurrency(getSubtotal());
  }

  function openCart() {
    cartDrawer.classList.add('open');
    cartBackdrop.classList.add('visible');
    cartDrawer.setAttribute('aria-hidden','false');
    cartBackdrop.setAttribute('aria-hidden','false');
    renderCart();
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
  }
  function closeCart() {
    cartDrawer.classList.remove('open');
    cartBackdrop.classList.remove('visible');
    cartDrawer.setAttribute('aria-hidden','true');
    cartBackdrop.setAttribute('aria-hidden','true');
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
  }

  const aliCart = {
    add(item){ if(!item||!item.id) return; const id = String(item.id); if(!cart[id]) cart[id] = { id, name: item.name || 'Product', price: Number(item.price||0), img: item.img||'', qty: 0 }; cart[id].qty = (cart[id].qty||0) + (Number(item.qty)||1); saveCart(); openCart(); },
    update(id,data){ id=String(id); if(!cart[id]) return; if('qty' in data) cart[id].qty = Number(data.qty)||0; if('price' in data) cart[id].price = Number(data.price)||cart[id].price; if('name' in data) cart[id].name = data.name; if(cart[id].qty<=0) delete cart[id]; saveCart(); },
    remove(id){ id=String(id); if(cart[id]){ delete cart[id]; saveCart(); } },
    clear(){ cart={}; saveCart(); },
    get(){ return JSON.parse(JSON.stringify(cart)); }
  };

  window.aliCart = aliCart;

  document.addEventListener('click', function(e){
    const btn = e.target.closest && e.target.closest('.add-to-cart');
    if (!btn) return;
    e.preventDefault();
    const id = btn.getAttribute('data-product-id') || '';
    if (!id) return;
    const name = btn.getAttribute('data-product-name') || 'Product';
    const price = parseFloat(btn.getAttribute('data-product-price') || 0) || 0;
    const img = btn.getAttribute('data-product-img') || '';
    aliCart.add({ id, name, price, img, qty: 1 });
    const original = btn.innerHTML;
    btn.textContent = 'Added';
    setTimeout(()=> { btn.innerHTML = original; }, 800);
  }, true);

  if (cartToggle) cartToggle.addEventListener('click', ()=> { if (cartDrawer.classList.contains('open')) closeCart(); else openCart(); });
  if (cartClose) cartClose.addEventListener('click', closeCart);
  if (cartBackdrop) cartBackdrop.addEventListener('click', closeCart);
  if (continueBtn) continueBtn.addEventListener('click', closeCart);
  if (cartClearBtn) cartClearBtn.addEventListener('click', ()=>{ if (confirm('Clear all items from your cart?')) { cart = {}; saveCart(); } });

  renderCart();
  window.addEventListener('storage', ()=> { cart = loadCart(); renderCart(); });

  // expose close functions for escape handler above
  window.closeSearch = closeSearch;
  window.closeCart = closeCart;
})();
</script>

<?php if ($conn) $conn->close(); ?>
</body>
</html>
