<?php
declare(strict_types=1);
session_start();

/**
 * search.php
 * Single-file search page for products with applied site header + footer.
 *
 * Notes:
 * - DB constants corrected to match the rest of the site.
 * - Prepared statement binding supports dynamic parameter lists.
 * - Minimal changes to markup: search form action absolute and submit button inside form.
 */

/* ---------- Configuration ---------- */
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

define('CURRENCY_SYMBOL', '$');
define('STORAGE_KEY', 'ali_hair_cart_v1');

$perPage = 16;

/* ---------- DB connection ---------- */
/* Use the same constants used across the site */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn && $conn->connect_error) {
    error_log('DB connect error: ' . $conn->connect_error);
    $conn = null;
}
if ($conn) $conn->set_charset('utf8mb4');

function h(string $s = ''): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ---------- Helper: normalize images ---------- */
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

/* ---------- Utility: bind params with references for mysqli_stmt ---------- */
function bind_params_ref(mysqli_stmt $stmt, string $types, array $params): bool {
    if ($types === '' || empty($params)) return true;
    // mysqli_stmt::bind_param requires references
    $refs = [];
    $refs[] = & $types;
    foreach ($params as $k => $v) {
        $refs[] = & $params[$k];
    }
    return (bool) call_user_func_array([$stmt, 'bind_param'], $refs);
}

/* ---------- Inputs & validation ---------- */
$q = trim((string)($_GET['q'] ?? ''));
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $perPage;
$sort = $_GET['sort'] ?? 'relevance';
$category = isset($_GET['category']) ? trim((string)$_GET['category']) : '';
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : null;

/* If empty query, show helpful message */
if ($q === '') {
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Search Products</title></head><body style="font-family:Inter,system-ui,Arial,Helvetica,sans-serif;padding:20px"><h1>Search</h1><form method="get" action="/search.php"><input name="q" placeholder="Search products" value="" style="padding:8px;width:280px"><button style="padding:8px 12px">Search</button></form><p style="color:#6b7280">Type a search term to find products.</p></body></html>';
    exit;
}

/* If no DB, return graceful message */
if (!$conn) {
    echo '<!doctype html><html><body><h1>Search unavailable</h1><p>Database connection failed.</p></body></html>';
    exit;
}

/* ---------- Schema introspection: build safe select list ---------- */
$availableCols = [];
$colRes = $conn->query("SHOW COLUMNS FROM `products`");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) $availableCols[] = $c['Field'];
    $colRes->free();
}
$has = function(string $col) use ($availableCols) { return in_array($col, $availableCols, true); };

$selectCols = ['p.id'];
if ($has('name')) $selectCols[] = 'p.name';
if ($has('price')) $selectCols[] = 'p.price';
if ($has('short_description')) $selectCols[] = 'p.short_description';
if ($has('description')) $selectCols[] = 'p.description';
foreach (['sku','image','image_url','image_placeholder','tags','category','category_id','created_at'] as $c) {
    if ($has($c) && !in_array("p.$c", $selectCols, true)) $selectCols[] = "p.$c";
}
$selectSql = implode(', ', $selectCols);

/* ---------- Build FROM and WHERE (search) ---------- */
$fromSql = '`products` p';
$whereParts = [];
$bindTypes = '';
$bindParams = [];

// Search fields prioritized
$searchFields = [];
if ($has('name')) $searchFields[] = 'LOWER(p.name)';
if ($has('short_description')) $searchFields[] = 'LOWER(p.short_description)';
if ($has('description')) $searchFields[] = 'LOWER(p.description)';
if ($has('tags')) $searchFields[] = 'LOWER(p.tags)';

if (empty($searchFields)) {
    echo '<!doctype html><html><body><h1>Search not supported</h1><p>Search fields are not available in products table.</p></body></html>';
    exit;
}

// split terms
$terms = preg_split('/\s+/', mb_strtolower($q, 'UTF-8'));
$termLikes = [];
foreach ($terms as $t) {
    if ($t === '') continue;
    // keep the LIKE pattern in bind params (no manual escaping required for prepared statements)
    $termLikes[] = '%' . $t . '%';
}

// Combine conditions: require each term to match at least one searchable field
foreach ($termLikes as $t) {
    $sub = [];
    foreach ($searchFields as $f) {
        $sub[] = "$f LIKE ?";
        $bindTypes .= 's';
        $bindParams[] = $t;
    }
    $whereParts[] = '(' . implode(' OR ', $sub) . ')';
}

// optional category filter
if ($category !== '') {
    if ((string)(int)$category === $category) {
        if ($has('category_id')) {
            $whereParts[] = 'p.category_id = ?';
            $bindTypes .= 'i';
            $bindParams[] = (int)$category;
        } else {
            $catId = (int)$category;
            $jt = false;
            $tablesRes = $conn->query("SHOW TABLES");
            if ($tablesRes) {
                while ($t = $tablesRes->fetch_row()) {
                    $tbl = $t[0];
                    if (in_array($tbl, ['product_category','product_categories','product_to_category','product_category_map'], true)) { $jt = $tbl; break; }
                }
                $tablesRes->free();
            }
            if ($jt) {
                $fromSql = "`products` p JOIN `{$conn->real_escape_string($jt)}` pc ON pc.product_id = p.id";
                $whereParts[] = 'pc.category_id = ?';
                $bindTypes .= 'i';
                $bindParams[] = $catId;
            } else {
                $whereParts[] = 'LOWER(p.category) = ?';
                $bindTypes .= 's';
                $bindParams[] = mb_strtolower($category, 'UTF-8');
            }
        }
    } else {
        if ($has('category')) {
            $whereParts[] = 'LOWER(p.category) = ?';
            $bindTypes .= 's';
            $bindParams[] = mb_strtolower($category, 'UTF-8');
        } else {
            $catStmt = $conn->prepare("SELECT id FROM categories WHERE LOWER(slug) = ? OR LOWER(name) = ? LIMIT 1");
            if ($catStmt) {
                $slug = mb_strtolower($category, 'UTF-8');
                // bind safely
                $catStmt->bind_param('ss', $slug, $slug);
                $catStmt->execute();
                $cres = $catStmt->get_result();
                if ($crow = $cres->fetch_assoc()) {
                    $cid = (int)$crow['id'];
                    $jt = false;
                    $tablesRes = $conn->query("SHOW TABLES");
                    if ($tablesRes) {
                        while ($t = $tablesRes->fetch_row()) {
                            $tbl = $t[0];
                            if (in_array($tbl, ['product_category','product_categories','product_to_category','product_category_map'], true)) { $jt = $tbl; break; }
                        }
                        $tablesRes->free();
                    }
                    if ($jt) {
                        $fromSql = "`products` p JOIN `{$conn->real_escape_string($jt)}` pc ON pc.product_id = p.id";
                        $whereParts[] = 'pc.category_id = ?';
                        $bindTypes .= 'i';
                        $bindParams[] = $cid;
                    } elseif ($has('category_id')) {
                        $whereParts[] = 'p.category_id = ?';
                        $bindTypes .= 'i';
                        $bindParams[] = $cid;
                    }
                }
                $catStmt->close();
            }
        }
    }
}

// price filters
if ($min_price !== null && $has('price')) { $whereParts[] = 'p.price >= ?'; $bindTypes .= 'd'; $bindParams[] = $min_price; }
if ($max_price !== null && $has('price')) { $whereParts[] = 'p.price <= ?'; $bindTypes .= 'd'; $bindParams[] = $max_price; }

// availability
if ($has('status')) {
    $whereParts[] = "(p.status IS NULL OR p.status = 'active' OR p.status = '1')";
}
if ($has('stock')) {
    $whereParts[] = "p.stock > 0";
}

$whereSql = $whereParts ? implode(' AND ', $whereParts) : '1';

/* ---------- Count total results ---------- */
$total = 0;
$countSql = "SELECT COUNT(DISTINCT p.id) AS cnt FROM {$fromSql} WHERE {$whereSql}";
$stmt = $conn->prepare($countSql);
if ($stmt) {
    if ($bindTypes !== '') {
        // bind dynamic params safely
        if (!bind_params_ref($stmt, $bindTypes, $bindParams)) {
            error_log('Failed to bind params for count query');
        }
    }
    $stmt->execute();
    $cres = $stmt->get_result();
    $crow = $cres->fetch_assoc();
    $total = (int)($crow['cnt'] ?? 0);
    $stmt->close();
}

/* ---------- Ordering ---------- */
$orderSql = 'p.id DESC';
if ($sort === 'new' && $has('created_at')) $orderSql = 'p.created_at DESC';
if ($sort === 'price_low' && $has('price')) $orderSql = 'p.price ASC';
if ($sort === 'price_high' && $has('price')) $orderSql = 'p.price DESC';

/* ---------- Fetch results ---------- */
$products = [];
$pages = 1;
$sql = "SELECT DISTINCT {$selectSql} FROM {$fromSql} WHERE {$whereSql} ORDER BY {$orderSql} LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
if ($stmt) {
    if ($bindTypes === '') {
        // only limit/offset
        $stmt->bind_param('ii', $perPage, $offset);
    } else {
        // append limit/offset types and params
        $bTypes = $bindTypes . 'ii';
        $bParams = array_merge($bindParams, [$perPage, $offset]);
        if (!bind_params_ref($stmt, $bTypes, $bParams)) {
            error_log('Failed to bind params for select query');
        }
    }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $products[] = $r;
    $stmt->close();
    $pages = max(1, (int)ceil($total / $perPage));
}

/* ---------- Render HTML (header + footer applied) ---------- */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Search results for "<?php echo h($q); ?>"</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700;800&display=swap" rel="stylesheet">
  <style>
    body{font-family:Inter,system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#fafafa;color:#111827}
    .grid { display:grid; gap:16px; grid-template-columns:repeat(2,1fr) }
    @media(min-width:640px){ .grid{ grid-template-columns:repeat(3,1fr) } }
    @media(min-width:1024px){ .grid{ grid-template-columns:repeat(4,1fr) } }
    .card{background:#fff;border:1px solid #eef2f6;border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px;height:100%}
    .thumb{width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:8px;background:#f3f4f6}
    .btn{padding:8px 12px;border-radius:8px;font-weight:700}
    .btn-add{background:#7b3f00;color:#fff;border:none}
    .pagination{display:flex;gap:8px;justify-content:center;margin-top:18px}
    .cart-drawer{position:fixed;right:0;top:0;height:100vh;width:92vw;max-width:420px;background:#fff;box-shadow:-20px 0 40px rgba(0,0,0,.14);transform:translateX(110%);transition:transform .32s;z-index:1400;display:flex;flex-direction:column}
    .cart-drawer.open{transform:translateX(0)}
    .cart-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.45);opacity:0;pointer-events:none;transition:opacity .25s;z-index:1300}
    .cart-backdrop.visible{opacity:1;pointer-events:auto}

    /* Header styles */
    :root { --primary: #7b3f00; --secondary: #f7e0c4; }
    header.site-header{background:#fff;box-shadow:0 1px 0 rgba(0,0,0,0.04);position:sticky;top:0;z-index:70}
    .header-inner{max-width:1200px;margin:0 auto;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .logo{display:flex;align-items:center;gap:10px;text-decoration:none;font-weight:800;color:var(--primary)}
    .logo img{height:40px;width:40px;object-fit:contain;flex:0 0 40px}
    .logo-text{display:inline-flex;flex-direction:column;line-height:1}
    .logo-main{color:var(--primary);font-weight:800;font-size:1rem}
    .logo-sub{color:#374151;font-weight:800;font-size:.95rem}
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


    /* Mobile drawer and search panel (restored) */
    .mobile-left-drawer{
        position:fixed;left:0;top:0;height:100vh;width:84vw;max-width:320px;background:#fff;box-shadow:20px 0 40px rgba(0,0,0,.12);transform:translateX(-110%);transition:transform .32s cubic-bezier(.2,.9,.2,1);z-index:70;padding:1rem;overflow:auto;border-top-right-radius:14px;border-bottom-right-radius:14px;
    }
    .mobile-left-drawer.open{transform:translateX(0)}
    .mobile-left-close{display:inline-flex;align-items:center;gap:6px;cursor:pointer}
    .search-panel { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1200; }
    .search-panel.open { display:flex; backdrop-filter: blur(2px); }
    .search-box { background:#fff; padding:10px; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); display:flex; gap:8px; width:min(720px,96%); align-items:center; }
    .search-input-wide { border:1px solid #e6eef6; padding:10px 12px; border-radius:8px; width:100%; font-size:16px; }
    .search-close { background:transparent; border:0; cursor:pointer; font-size:18px; color:#374151; }
    .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    :focus { outline: 3px solid rgba(59,130,246,0.25); outline-offset: 3px; }
  </style>
</head>
<body class="min-h-screen">

<header class="site-header" role="banner">
  <div class="header-inner">
    <!-- MOBILE HAMBURGER FIRST -->
    <div style="display:flex;align-items:center;gap:12px">
      <button id="hamburger" class="hamburger" aria-label="Open menu" aria-expanded="false" type="button">
        <svg id="hamburger-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <svg id="hamburger-close" xmlns="http://www.w3.org/2000/svg" style="display:none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>

      <!-- LOGO AFTER HAMBURGER -->
      <a href="index.php" class="logo" aria-label="Ali Hair Wigs home">
        <img src="uploads/ahw.png" alt="Ali Hair Wigs logo" onerror="this.onerror=null;this.src='https://placehold.co/80x80/7b3f00/f7e0c4?text=Logo'">
        <span class="logo-text"><span class="logo-main">ALI HAIR</span><span class="logo-sub">WIGS</span></span>
      </a>
    </div>

    <div class="nav-wrap" aria-hidden="false">
           <nav class="nav-desktop" aria-label="Primary navigation">
        <a href="index.php#categories">Categories</a>
        <a href="mens_wigs.php">Men's Wigs</a>
        <a href="womens_wigs.php">Women's Wigs</a>
        <a href="about.php">About</a>
        <a href="map.php">Store Location</a>
        <a href="contact.php">Contact</a>
        <a href="login.php">Account</a>
      </nav>
    </div>

    <div style="display:flex;align-items:center;gap:8px">
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
<!-- Mobile left drawer -->
<div id="mobile-left-drawer" class="mobile-left-drawer" aria-hidden="true" role="navigation">
    <div class="drawer-inner">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <img src="uploads/ahw.png" alt="Logo" class="h-8 w-8 object-contain">
                <div class="font-bold text-lg text-[var(--primary)]">ALI HAIR WIGS</div>
            </div>
            <button id="mobile-left-close" class="mobile-left-close text-gray-700 p-2 rounded-md hover:bg-gray-100" aria-label="Close menu">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="drawer-scroll">
            <a href="#categories" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Categories</a>
            <a href="mens_wigs.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Men's Wigs</a>
            <a href="womens_wigs.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Women's Wigs</a>
            <a href="#about" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">About</a>
            <a href="login.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Account</a>

        
        </div>

        
    </div>
</div>

<!-- Search overlay panel -->
<div id="search-panel" class="search-panel" aria-hidden="true">
  <div class="search-box" role="search" aria-label="Site search">
    <form id="search-form" action="/search.php" method="get" style="display:flex;flex:1;gap:8px;align-items:center">
      <label for="q-wide" class="sr-only">Search products</label>
      <input id="q-wide" name="q" type="search" class="search-input-wide" placeholder="Search products, styles..." aria-label="Search products" autocomplete="off" value="<?php echo h($q); ?>" />
      <button type="submit" class="search-submit btn" style="background:#7b3f00;color:#fff;border-radius:8px;padding:8px 10px">Search</button>
    </form>
    <button id="search-close" class="search-close" aria-label="Close search">&times;</button>
  </div>
</div>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-2xl font-extrabold">Search results for "<?php echo h($q); ?>"</h1>
      <p class="text-sm text-gray-600"><?php echo (int)$total; ?> result<?php echo $total===1 ? '' : 's'; ?></p>
    </div>

    <div class="flex items-center gap-3">
      <form method="get" class="flex items-center gap-2">
        <input type="hidden" name="q" value="<?php echo h($q); ?>">
        <label class="text-sm text-gray-600">Sort</label>
        <select name="sort" onchange="this.form.submit()" class="border rounded px-2 py-1">
          <option value="relevance" <?php echo $sort==='relevance' ? 'selected': ''; ?>>Relevance</option>
          <option value="new" <?php echo $sort==='new' ? 'selected': ''; ?>>Newest</option>
          <option value="price_low" <?php echo $sort==='price_low' ? 'selected': ''; ?>>Price: Low to High</option>
          <option value="price_high" <?php echo $sort==='price_high' ? 'selected': ''; ?>>Price: High to Low</option>
        </select>
      </form>
    </div>
  </div>

  <div class="grid" id="productGrid">
    <?php if (empty($products)): ?>
      <div class="col-span-full text-gray-600">No products matched your search.</div>
    <?php else: foreach ($products as $p):
      $imgs = normalizeImagesFromRow($p);
      $img = $imgs[0] ?? 'https://placehold.co/600x600/7b3f00/f7e0c4?text=Img';
      $name = $p['name'] ?? 'Product';
      $price = isset($p['price']) ? number_format((float)$p['price'], 2) : '0.00';
    ?>
      <article class="card">
        <a href="product_details.php?id=<?php echo (int)($p['id'] ?? 0); ?>" style="text-decoration:none;color:inherit">
          <img src="<?php echo h($img); ?>" alt="<?php echo h($name); ?>" class="thumb" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/600x600/ffffff/000000?text=Img'">
          <div class="mt-2 font-semibold"><?php echo h($name); ?></div>
        </a>

        <?php if (!empty($p['short_description'])): ?>
          <div class="text-sm text-gray-600"><?php echo h($p['short_description']); ?></div>
        <?php endif; ?>

        <div class="flex items-center justify-between">
          <div class="text-lg font-extrabold text-[var(--primary)]"><?php echo CURRENCY_SYMBOL . ' ' . $price; ?></div>
          <?php if (!empty($p['sku'])): ?><div class="text-sm text-gray-500">SKU: <?php echo h($p['sku']); ?></div><?php endif; ?>
        </div>

        <div class="mt-2 flex gap-2">
          <button class="btn btn-add add-to-cart"
                  data-product-id="<?php echo (int)($p['id'] ?? 0); ?>"
                  data-product-name="<?php echo h($name); ?>"
                  data-product-price="<?php echo (float)($p['price'] ?? 0); ?>"
                  data-product-img="<?php echo h($img); ?>">
            Add
          </button>
          <a href="product_details.php?id=<?php echo (int)($p['id'] ?? 0); ?>" class="btn border">View</a>
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
      ?>
      <?php if ($page > 1): $base['page'] = $page - 1; ?>
        <a href="?<?php echo http_build_query($base); ?>">&larr; Prev</a>
      <?php endif; ?>

      <?php for ($i = $start; $i <= $end; $i++): $base['page'] = $i; ?>
        <a href="?<?php echo http_build_query($base); ?>" class="<?php echo $i=== $page ? 'active bg-[var(--primary)] text-white px-3 py-1 rounded' : ''; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>

      <?php if ($page < $pages): $base['page'] = $page + 1; ?>
        <a href="?<?php echo http_build_query($base); ?>">Next &rarr;</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
</main>

<!-- Footer -->
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

<!-- Cart backdrop and drawer -->
<div id="cart-backdrop" class="cart-backdrop" aria-hidden="true"></div>

<aside id="cart-drawer" class="cart-drawer" aria-hidden="true" role="dialog" aria-label="Shopping cart">
  <div class="p-4 border-b flex items-center justify-between">
    <div class="font-semibold">Your Cart</div>
    <div>
      <button id="cart-clear" class="text-sm text-gray-600">Clear</button>
      <button id="cart-close" class="ml-2 p-2">✕</button>
    </div>
  </div>

  <div id="cart-items" class="p-4" style="flex:1 1 auto;overflow:auto">Your cart is empty.</div>

  <div class="p-4 border-t">
    <div class="flex items-center justify-between mb-2"><div class="text-sm text-gray-600">Subtotal</div><div id="cart-subtotal" class="font-bold text-[var(--primary)]"><?php echo CURRENCY_SYMBOL; ?> 0.00</div></div>
    <div class="flex gap-2">
      <a href="checkout.php" class="flex-1 bg-[var(--primary)] text-white px-4 py-2 rounded text-center font-bold">Checkout</a>
      <button id="continue-shopping" class="flex-1 border px-4 py-2 rounded">Continue</button>
    </div>
    <div class="text-xs text-gray-500 mt-2">Shipping calculated at checkout</div>
  </div>
</aside>

<script>
/* Header drawer + search toggle + cart (localStorage-backed) */
(function(){
  // Mobile drawer toggles
  const hamburger = document.getElementById('hamburger');
  const mobileLeft = document.getElementById('mobile-left-drawer');
  const mobileLeftClose = document.getElementById('mobile-left-close');
  const openIcon = document.getElementById('hamburger-open');
  const closeIcon = document.getElementById('hamburger-close');

  function trapFocus(container) {
    const focusable = container.querySelectorAll('a,button,[tabindex]:not([tabindex="-1"])');
    if (!focusable.length) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    function keyListener(e) {
      if (e.key !== 'Tab') return;
      if (e.shiftKey) {
        if (document.activeElement === first) {
          e.preventDefault();
          last.focus();
        }
      } else {
        if (document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
    }
    container._keyListener = keyListener;
    container.addEventListener('keydown', keyListener);
  }
  function releaseFocus(container) {
    if (!container || !container._keyListener) return;
    container.removeEventListener('keydown', container._keyListener);
    delete container._keyListener;
  }

  function openLeft() {
    if (!mobileLeft) return;
    mobileLeft.classList.add('open');
    mobileLeft.setAttribute('aria-hidden','false');
    if (openIcon) openIcon.classList.add('hidden');
    if (closeIcon) closeIcon.classList.remove('hidden');
    if (hamburger) hamburger.setAttribute('aria-expanded','true');
    setTimeout(()=> {
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
    if (hamburger) hamburger.setAttribute('aria-expanded','false');
    releaseFocus(mobileLeft);
    if (hamburger) hamburger.focus();
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
  }
  if (hamburger) hamburger.addEventListener('click', ()=> {
    if (mobileLeft && mobileLeft.classList.contains('open')) closeLeft();
    else openLeft();
  });
  if (mobileLeftClose) mobileLeftClose.addEventListener('click', closeLeft);

  // Search toggle
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
  }
  function closeSearch() {
    if (!searchPanel) return;
    searchPanel.classList.remove('open');
    searchPanel.setAttribute('aria-hidden','true');
    if (searchToggle) searchToggle.setAttribute('aria-expanded','false');
    document.documentElement.style.overflow = '';
    searchToggle?.focus();
  }

  if (searchToggle) searchToggle.addEventListener('click', (e)=>{ e.preventDefault(); if (searchPanel && searchPanel.classList.contains('open')) closeSearch(); else openSearch(); });
  if (searchClose) searchClose.addEventListener('click', closeSearch);
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') { closeSearch(); closeLeft(); closeCart(); } });

  if (searchPanel) {
    searchPanel.addEventListener('click', (e)=> {
      if (e.target === searchPanel) closeSearch();
    });
  }

  // Slide-out cart (localStorage-backed)
  const STORAGE_KEY = '<?php echo addslashes(STORAGE_KEY); ?>';
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
  function formatCurrency(v){ return '<?php echo addslashes(CURRENCY_SYMBOL); ?> ' + Number(v||0).toFixed(2); }

  function renderCart(){
    const entries = Object.entries(cart || {});
    if (cartCountEl) cartCountEl.textContent = getTotalItems();

    if (!entries.length) {
      if (cartItemsEl) cartItemsEl.innerHTML = '<div style="text-align:center;color:#6b7280;padding:24px">Your cart is empty.</div>';
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
      const qtyBadge = document.createElement('div'); qtyBadge.style.padding='6px 9px'; qtyBadge.style.border='1px solid #eef2f6'; qtyBadge.style.borderRadius='8px'; qtyBadge.textContent = it.qty;
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

    if (cartItemsEl) {
      cartItemsEl.innerHTML = '';
      cartItemsEl.appendChild(container);
    }
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
  window.addEventListener('storage', (e)=> { cart = loadCart(); renderCart(); });
})();
</script>

</body>
</html>
