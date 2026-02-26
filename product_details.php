<?php
declare(strict_types=1);
session_start();

/**
 * product_details.php
 * Full product page — modified so both "Add to cart" and "Buy now" add the product
 * using the selected quantity from #qty (fallback to 1). Everything else unchanged.
 * Added: image hover zoom and click-to-open full image modal (no other behavior changed).
 */

/* Config */
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

define('CURRENCY_SYMBOL', '$');
define('STORAGE_KEY', 'ali_hair_cart_v1');

/* DB */
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$connection_error = null;
if ($conn->connect_error) {
    $connection_error = "Database Connection Failed";
    error_log("Database Connection Failed: " . $conn->connect_error);
    $conn = null;
}
if ($conn) $conn->set_charset('utf8mb4');

/* Helpers */
function escapeHtml(string $s = ''): string {
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function resolveProductImages(?string $stored): array {
    if (empty($stored)) return [];
    $out = [];
    $decoded = json_decode($stored, true);
    if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
        if (is_array($decoded) && array_values($decoded) !== $decoded) {
            if (!empty($decoded['url']) && is_string($decoded['url'])) $out[] = $decoded['url'];
            if (!empty($decoded['path']) && is_string($decoded['path'])) $out[] = $decoded['path'];
            if (!empty($decoded['filename']) && is_string($decoded['filename'])) $out[] = $decoded['filename'];
        } else {
            foreach ($decoded as $item) {
                if (is_string($item)) $out[] = $item;
                elseif (is_array($item)) {
                    if (!empty($item['url']) && is_string($item['url'])) $out[] = $item['url'];
                    if (!empty($item['path']) && is_string($item['path'])) $out[] = $item['path'];
                    if (!empty($item['filename']) && is_string($item['filename'])) $out[] = $item['filename'];
                }
            }
        }
    } else {
        $parts = preg_split('/\s*,\s*/', trim($stored));
        foreach ($parts as $p) { if ($p !== '') $out[] = $p; }
    }

    $normalized = [];
    foreach ($out as $u) {
        $u = trim($u);
        if ($u === '') continue;
        if (filter_var($u, FILTER_VALIDATE_URL)) { $normalized[] = $u; continue; }
        if (strpos($u, '//') === 0) { $normalized[] = $u; continue; }
        $candidate = ltrim($u, '/');
        if (stripos($candidate, 'uploads/') === 0 || stripos($candidate, 'admin/admin/images') === 0) {
            $normalized[] = $candidate;
        } else {
            $normalized[] = 'uploads/' . $candidate;
        }
    }

    return array_values(array_unique($normalized));
}

/* CSRF token */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}
$csrf_token = $_SESSION['csrf_token'];

/* Handle review submission */
$review_errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($csrf_token, (string)$token)) {
        $review_errors[] = 'Invalid form submission.';
    } else {
        $p_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $name  = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $rating = (int)($_POST['rating'] ?? 0);
        $title = trim((string)($_POST['title'] ?? ''));
        $body  = trim((string)($_POST['body'] ?? ''));

        if ($p_id <= 0) $review_errors[] = 'Invalid product.';
        if ($name === '') $review_errors[] = 'Name is required.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $review_errors[] = 'Email is invalid.';
        if ($rating < 1 || $rating > 5) $review_errors[] = 'Rating must be between 1 and 5.';
        if ($body === '') $review_errors[] = 'Review text is required.';

        if (empty($review_errors) && $conn) {
            $sql = "INSERT INTO reviews (name, email, rating, title, body, product_id, visible, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
            if ($stmt = $conn->prepare($sql)) {
                $visible = 1;
                $stmt->bind_param('ssissii', $name, $email, $rating, $title, $body, $p_id, $visible);
                if ($stmt->execute()) {
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
                    header('Location: product_details.php?id=' . $p_id . '&review_submitted=1#reviews');
                    exit;
                } else {
                    $review_errors[] = 'Failed to save review.';
                }
                $stmt->close();
            } else {
                $review_errors[] = 'Database error.';
            }
        }
    }
}

/* Fetch product, related, reviews */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$product = null;
$related = [];
$reviews = [];
if ($conn && $id > 0) {
    $sql = "SELECT * FROM products WHERE id = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $product = $res->fetch_assoc() ?: null;
        $stmt->close();
    }
    if ($product) {
        $catId = 0;
        if (!empty($product['category_id'])) $catId = (int)$product['category_id'];
        elseif (!empty($product['category']) && is_numeric($product['category'])) $catId = (int)$product['category'];

        if ($catId > 0) {
            $rSql = "SELECT * FROM products WHERE id != ? AND (category_id = ? OR (category = ? AND category REGEXP '^[0-9]+$')) ORDER BY name ASC LIMIT 8";
            if ($rStmt = $conn->prepare($rSql)) {
                $rStmt->bind_param('iii', $id, $catId, $catId);
                $rStmt->execute();
                $rRes = $rStmt->get_result();
                while ($row = $rRes->fetch_assoc()) $related[] = $row;
                $rStmt->close();
            }
        } else {
            $catLabel = $product['category'] ?? '';
            if ($catLabel !== '') {
                $rSql = "SELECT * FROM products WHERE id != ? AND (category = ? OR category_id IS NULL) ORDER BY name ASC LIMIT 8";
                if ($rStmt = $conn->prepare($rSql)) {
                    $rStmt->bind_param('is', $id, $catLabel);
                    $rStmt->execute();
                    $rRes = $rStmt->get_result();
                    while ($row = $rRes->fetch_assoc()) $related[] = $row;
                    $rStmt->close();
                }
            }
        }

        $revSql = "SELECT name, rating, title, body, created_at FROM reviews WHERE product_id = ? AND visible = 1 ORDER BY created_at DESC LIMIT 50";
        if ($revStmt = $conn->prepare($revSql)) {
            $revStmt->bind_param('i', $id);
            $revStmt->execute();
            $revRes = $revStmt->get_result();
            while ($rv = $revRes->fetch_assoc()) $reviews[] = $rv;
            $revStmt->close();
        }
    }
}

/* Choose images */
$images = [];
$main = 'https://placehold.co/1200x900/7b3f00/f7e0c4?text=Image';
if ($product) {
    if (!empty($product['image_placeholder'])) $images = resolveProductImages($product['image_placeholder']);
    if (empty($images) && !empty($product['image_url'])) $images = resolveProductImages($product['image_url']);
    if (!empty($images)) $main = $images[0];
}

$review_submitted = isset($_GET['review_submitted']) ? true : false;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
       <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/favicon.png">
  <title><?php echo $product ? escapeHtml($product['name']) . " — Ali Hair Wigs" : "Product Not Found — Ali Hair Wigs"; ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --primary: #7b3f00; --secondary: #f7e0c4; --accent-cta: #2b6cb0; }
    html,body{height:100%}
    body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial; background:#fcfcfc; -webkit-font-smoothing:antialiased; color:#1f2937; margin:0; }
    /* Header / search / mobile drawer styles copied exactly from index.php */
    :root { --primary:#7b3f00; --secondary:#f7e0c4; --promo-bg:#6bb04a; --promo-accent:#ffffff; }
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


    .mobile-left-drawer {
        position:fixed; left:0; top:0; height:100vh; width:84vw; max-width:320px; background:#fff;
        box-shadow:20px 0 40px rgba(0,0,0,.12); transform:translateX(-110%); transition:transform .32s cubic-bezier(.2,.9,.2,1);
        z-index:70; padding:1rem; overflow:auto;
    }
    @media (max-width: 640px) {
        .mobile-left-drawer { width:100vw; max-width:none; border-top-right-radius:0; border-bottom-right-radius:0; padding:1.25rem; }
        .mobile-left-drawer .drawer-inner { height:100vh; display:flex; flex-direction:column; }
        .mobile-left-drawer .drawer-scroll { overflow:auto; flex:1 1 auto; padding-bottom:60px; }
    }
    .mobile-left-drawer.open { transform:translateX(0); top:0; left:0; }
    .mobile-left-close { display:inline-flex; align-items:center; gap:6px; cursor:pointer; }

    /* Product page core styles (kept from product template) */
    .container { max-width:1200px; margin:28px auto; padding:0 16px; }
    .grid-2 { display:grid; grid-template-columns: 1fr; gap:20px; }
    @media(min-width:1000px){ .grid-2 { grid-template-columns: 520px 1fr; gap:36px; } }
    .gallery { background:#fff; border-radius:12px; border:1px solid #eef2f6; padding:16px; }
    /* Main image: added zoom on hover using transform and will-change for smoothness */
    .main-img-wrapper { position:relative; overflow:hidden; border-radius:10px; }
    .main-img {
      width:100%; height:520px; object-fit:cover; display:block; transition: transform .28s cubic-bezier(.2,.9,.2,1); cursor:zoom-in;
      will-change: transform;
      -webkit-transform-origin: center center;
      transform-origin: center center;
    }
    .main-img.zoomed { transform: scale(1.35); cursor:zoom-out; }
    .thumbs { margin-top:12px; display:flex; gap:8px; overflow:auto; }
    .thumbs img { width:86px; height:86px; object-fit:cover; border-radius:8px; cursor:pointer; border:2px solid transparent; }
    .thumbs img.active { border-color:var(--primary); }
    .info-card { background:#fff; border-radius:12px; border:1px solid #eef2f6; padding:20px; box-shadow:0 6px 18px rgba(12,18,24,0.03); }
    .title { font-size:1.75rem; font-weight:800; color:#111827; }
    .meta { color:#6b7280; margin-top:6px; }
    .price { color:var(--primary); font-weight:800; font-size:1.6rem; margin-top:8px; }
    .btn-primary { background:var(--primary); color:#fff; padding:12px 18px; border-radius:10px; font-weight:800; border:none; cursor:pointer; }
    .btn-secondary { border:1px solid #e6e6e6; background:#fff; padding:10px 14px; border-radius:10px; cursor:pointer; }
    .tabs { margin-top:20px; background:#fff; border-radius:12px; padding:14px; border:1px solid #eef2f6; }
    .tab-buttons { display:flex; gap:8px; border-bottom:1px solid #f3f4f6; padding-bottom:10px; margin-bottom:12px; }
    .tab-buttons button { background:transparent; border:none; padding:8px 10px; cursor:pointer; color:#6b7280; font-weight:600; }
    .tab-buttons button.active { color:#111827; border-bottom:2px solid var(--primary); }

    .related { margin-top: 18px; display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; align-items: start; row-gap: 24px; }
    @media(min-width: 640px) { .related { grid-template-columns: repeat(4, 1fr); gap: 24px; row-gap: 28px; } }
    .related .rp-card { background: #fff; padding: 12px; border-radius: 10px; border: 1px solid #eef2f6; text-decoration: none; color: inherit; display: flex; flex-direction: column; gap: 10px; min-height: 100%; transition: transform .12s ease, box-shadow .12s ease; }
    .rp-thumb { width: 100%; aspect-ratio: 1 / 1; object-fit: cover; border-radius: 8px; display: block; flex-shrink: 0; }
    .rp-card > div { line-height: 1.15; }

    .cart-drawer { position:fixed; right:0; top:0; height:100vh; width:92vw; max-width:420px; background:#fff; box-shadow:-20px 0 40px rgba(0,0,0,.14); transform:translateX(110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:1400; display:flex; flex-direction:column; border-top-left-radius:14px; border-bottom-left-radius:14px; overflow:hidden; }
    .cart-drawer.open { transform:translateX(0); }
    .cart-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); opacity:0; pointer-events:none; transition:opacity .25s; z-index:1300; }
    .cart-backdrop.visible { opacity:1; pointer-events:auto; }
    .cart-row { display:flex; gap:12px; align-items:flex-start; background:#fff; border:1px solid #eef2f6; border-radius:12px; padding:12px; margin-bottom:12px; box-shadow:0 6px 18px rgba(12,18,24,0.04); }
    .cart-item-img { width:72px; height:72px; object-fit:cover; border-radius:8px; flex:0 0 72px; }
    .cart-meta { flex:1 1 auto; display:flex; flex-direction:column; gap:6px; min-width:0; }
    .cart-name { font-weight:700; font-size:0.98rem; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cart-sub { color:#6b7280; font-size:0.85rem; }
    .cart-actions { display:flex; flex-direction:column; gap:8px; align-items:flex-end; min-width:120px; text-align:right; }
    .cart-price { font-weight:800; color:var(--primary); white-space:nowrap; }
    .cart-qty-controls { display:flex; gap:8px; align-items:center; }
    .qty-badge { padding:6px 10px; border-radius:8px; background:#f8fafc; border:1px solid #eef2f6; font-weight:700; min-width:36px; text-align:center; }
    .checkout-cta { display:flex; gap:10px; }
    .checkout-btn { flex:1; background:var(--primary); color:#fff; padding:12px 14px; border-radius:10px; font-weight:800; text-align:center; text-decoration:none; }
    .form-input { width:100%; padding:10px; border:1px solid #e6e6e6; border-radius:8px; }
    .error { color:#b91c1c; margin-top:8px; }
    .success { color:#047857; margin-top:8px; }

    .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    :focus { outline: 3px solid rgba(59,130,246,0.25); outline-offset: 3px; }

    /* Fullscreen modal for clicked image */
    .img-modal {
      position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1600;
      background: rgba(0,0,0,0.85);
    }
    .img-modal.open { display:flex; }
    .img-modal .modal-inner { max-width:95%; max-height:95%; display:flex; align-items:center; justify-content:center; }
    .img-modal img { max-width:100%; max-height:100%; object-fit:contain; border-radius:6px; box-shadow:0 10px 40px rgba(0,0,0,.6); }
    .img-modal .close-btn {
      position:absolute; top:18px; right:18px; background:rgba(0,0,0,0.3); color:#fff; border:0; padding:10px 12px; border-radius:8px; cursor:pointer;
    }

    /* Optional: subtle zoom-pan when moving mouse over zoomed image */
    .main-img-wrapper:hover .main-img.zoomed { transition: transform .18s linear; }
  </style>
</head>
<body class="min-h-screen">

<header class="site-header" role="banner">
  <div class="header-inner">
    <div style="display:flex;align-items:center;gap:12px">
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
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
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

        <div class="px-4 py-4 border-t border-gray-100">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">Email: info@wigstudio.com</div>
            </div>
        </div>
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
  <?php if ($connection_error): ?>
    <div class="p-4 mb-6 bg-red-50 text-red-700 rounded">Database Connection Warning — dynamic content may be unavailable.</div>
  <?php endif; ?>

  <?php if (!$product): ?>
    <div class="info-card">
      <h1 class="title">Product not found</h1>
      <p class="meta">We couldn't find the product you requested. Please check the link or return to the home page.</p>
      <div style="margin-top:12px"><a href="index.php" class="btn-secondary">Back to Home</a></div>
    </div>
  <?php else:
    $pname = $product['name'] ?? 'Product';
    $pdesc = $product['description'] ?? '';
    $price_float = isset($product['price']) ? (float)$product['price'] : 0.0;
    $price = number_format($price_float, 2);
    $sku = $product['sku'] ?? ($product['sku_code'] ?? '');
  ?>
  <div class="grid-2">
    <div class="gallery" aria-label="Product gallery">
      <div class="main-img-wrapper" id="main-img-wrapper">
        <img id="mainImage" src="<?php echo escapeHtml($main); ?>" alt="<?php echo escapeHtml($pname); ?>" class="main-img" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/1200x900/ffffff/000000?text=Img';">
      </div>
      <?php if (!empty($images)): ?>
        <div class="thumbs" role="list">
          <?php foreach ($images as $i => $src): ?>
            <img src="<?php echo escapeHtml($src); ?>" data-src="<?php echo escapeHtml($src); ?>" alt="Thumb <?php echo $i+1; ?>" class="<?php echo $i===0 ? 'active' : '' ?>" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/120x120/ffffff/000000?text=Img';">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="info-card" aria-labelledby="product-title">
      <h1 id="product-title" class="title"><?php echo escapeHtml($pname); ?></h1>
      <?php if ($sku): ?><div class="sku">SKU: <?php echo escapeHtml($sku); ?></div><?php endif; ?>
      <div class="meta">Premium hair systems & wigs</div>
      <div class="price"><?php echo CURRENCY_SYMBOL . ' ' . $price; ?></div>

      <div style="margin-top:14px;display:flex;gap:12px;flex-wrap:wrap;">
        <div>
          <label class="meta" for="qty">Quantity</label><br>
          <div style="display:flex;gap:8px;align-items:center;margin-top:6px;">
            <button id="qty-dec" class="btn-secondary" type="button">−</button>
            <input id="qty" class="qty" type="number" min="1" value="1" style="width:72px;text-align:center;" aria-label="Quantity">
            <button id="qty-inc" class="btn-secondary" type="button">+</button>
          </div>
        </div>
      </div>

      <div class="mt-6" style="display:flex;gap:12px;align-items:center;">
        <button id="addToCart" class="btn-primary add-to-cart"
          data-product-id="<?php echo (int)$product['id']; ?>"
          data-product-name="<?php echo escapeHtml($pname); ?>"
          data-product-price="<?php echo $price_float; ?>"
          data-product-img="<?php echo escapeHtml($main); ?>"
        >Add to cart</button>

        <!-- Buy now adds with selected qty then redirects to checkout -->
        <button id="buyNow" type="button" class="btn-secondary"
          data-product-id="<?php echo (int)$product['id']; ?>"
          data-product-name="<?php echo escapeHtml($pname); ?>"
          data-product-price="<?php echo $price_float; ?>"
          data-product-img="<?php echo escapeHtml($main); ?>"
        >Buy now</button>

        <div style="margin-left:auto;text-align:right;">
          <div class="meta">Estimated processing: <strong>2-5 business days</strong></div>
          <div class="meta">Shipping: calculated at checkout</div>
        </div>
      </div>

      <div class="tabs" id="prod-tabs">
        <div class="tab-buttons" role="tablist" aria-label="Product information tabs">
          <button class="active" data-tab="desc" role="tab">Description</button>
          <button data-tab="reviews" role="tab">Reviews (<?php echo count($reviews); ?>)</button>
        </div>

        <div class="tab-panel" id="tab-desc" data-panel>
          <?php echo nl2br(escapeHtml($pdesc)); ?>
        </div>

        <div class="tab-panel" id="tab-reviews" data-panel style="display:none;" id="reviews">
          <?php if ($review_submitted): ?>
            <div class="success">Thank you — your review was submitted.</div>
          <?php endif; ?>

          <?php if (!empty($reviews)): foreach ($reviews as $rv): ?>
            <div style="border-top:1px dashed #eef2f6;padding-top:12px;margin-top:12px;">
              <div style="display:flex;justify-content:space-between;align-items:center;">
                <div><strong><?php echo escapeHtml($rv['name'] ?? 'Anonymous'); ?></strong></div>
                <div class="meta"><?php echo !empty($rv['created_at']) ? date('M j, Y', strtotime($rv['created_at'])) : ''; ?></div>
              </div>
              <div class="meta"><?php echo escapeHtml($rv['title'] ?? ''); ?></div>
              <div class="stars" style="color:#f59e0b;margin-top:6px;" aria-hidden="true">
                <?php $r = max(0, min(5, (int)($rv['rating'] ?? 0))); for ($i=1;$i<=5;$i++): ?>
                  <?php if ($i <= $r): ?><span>★</span><?php else: ?><span style="color:#e5e7eb">★</span><?php endif; ?>
                <?php endfor; ?>
              </div>
              <div style="margin-top:8px;"><?php echo nl2br(escapeHtml($rv['body'] ?? '')); ?></div>
            </div>
          <?php endforeach; else: ?>
            <div class="meta">Be the first to leave a review.</div>
          <?php endif; ?>

          <div style="margin-top:18px;border-top:1px solid #f3f4f6;padding-top:14px;">
            <h3 style="font-weight:700;margin-bottom:8px;">Leave a review</h3>

            <?php if (!empty($review_errors)): ?>
              <div class="error">
                <?php foreach ($review_errors as $err) echo '<div>' . escapeHtml($err) . '</div>'; ?>
              </div>
            <?php endif; ?>

            <form method="post" action="#reviews" style="margin-top:12px;">
              <input type="hidden" name="csrf_token" value="<?php echo escapeHtml($csrf_token); ?>">
              <input type="hidden" name="product_id" value="<?php echo (int)$product['id']; ?>">

              <div style="margin-bottom:8px;">
                <label class="meta">Name *</label>
                <input class="form-input" type="text" name="name" required maxlength="120" value="<?php echo isset($_POST['name']) ? escapeHtml($_POST['name']) : ''; ?>">
              </div>

              <div style="margin-bottom:8px;">
                <label class="meta">Email (optional)</label>
                <input class="form-input" type="email" name="email" maxlength="200" value="<?php echo isset($_POST['email']) ? escapeHtml($_POST['email']) : ''; ?>">
              </div>

              <div style="margin-bottom:8px;">
                <label class="meta">Rating *</label>
                <select name="rating" class="form-input" required>
                  <?php $selRating = isset($_POST['rating']) ? (int)$_POST['rating'] : 5;
                  for ($i=5;$i>=1;$i--): ?>
                    <option value="<?php echo $i; ?>" <?php echo $selRating === $i ? 'selected' : ''; ?>><?php echo $i; ?> star<?php echo $i>1 ? 's' : ''; ?></option>
                  <?php endfor; ?>
                </select>
              </div>

              <div style="margin-bottom:8px;">
                <label class="meta">Title</label>
                <input class="form-input" type="text" name="title" maxlength="150" value="<?php echo isset($_POST['title']) ? escapeHtml($_POST['title']) : ''; ?>">
              </div>

              <div style="margin-bottom:10px;">
                <label class="meta">Review *</label>
                <textarea name="body" class="form-input" rows="5" required><?php echo isset($_POST['body']) ? escapeHtml($_POST['body']) : ''; ?></textarea>
              </div>

              <div style="display:flex;gap:10px;align-items:center;">
                <button type="submit" name="submit_review" class="btn-primary">Submit review</button>
                <div class="meta text-sm">We may moderate reviews before publishing.</div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <div style="grid-column:1 / -1; margin-top:18px;">
      <div class="info-card">
        <h3 style="font-weight:800;margin-bottom:10px;">Related products</h3>
        <?php if (!empty($related)): ?>
          <div class="related">
            <?php foreach ($related as $rp):
              $candidateImages = [];
              if (!empty($rp['image_placeholder'])) $candidateImages = array_merge($candidateImages, resolveProductImages((string)$rp['image_placeholder']));
              if (!empty($rp['images'])) $candidateImages = array_merge($candidateImages, resolveProductImages((string)$rp['images']));
              if (!empty($rp['image'])) $candidateImages = array_merge($candidateImages, resolveProductImages((string)$rp['image']));
              if (!empty($rp['image_url'])) $candidateImages = array_merge($candidateImages, resolveProductImages((string)$rp['image_url']));
              $candidateImages = array_values(array_unique($candidateImages));
              $rmain = $candidateImages[0] ?? (isset($rp['image_url']) ? $rp['image_url'] : (isset($rp['image']) ? $rp['image'] : 'https://placehold.co/400x400/7b3f00/f7e0c4?text=Img'));
            ?>
              <a href="product_details.php?id=<?php echo (int)$rp['id']; ?>" class="rp-card">
                <img src="<?php echo escapeHtml($rmain); ?>" alt="<?php echo escapeHtml($rp['name'] ?? ''); ?>" class="rp-thumb" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/400x400/ffffff/000000?text=Img';">
                <div style="font-weight:700;"><?php echo escapeHtml($rp['name'] ?? ''); ?></div>
                <div style="color:var(--primary);font-weight:800;"><?php echo CURRENCY_SYMBOL . ' ' . number_format((float)($rp['price'] ?? 0),2); ?></div>
              </a>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="meta">No related items found.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</main>

<!-- Image modal for full-size view -->
<div id="imgModal" class="img-modal" aria-hidden="true" role="dialog" aria-label="Full size image">
  <button class="close-btn" id="imgModalClose" aria-label="Close image">✕</button>
  <div class="modal-inner">
    <img id="imgModalImg" src="" alt="Full size">
  </div>
</div>

<!-- Slide cart markup -->
<div id="cart-backdrop" class="cart-backdrop" aria-hidden="true"></div>

<aside id="cart-drawer" class="cart-drawer" aria-hidden="true" role="dialog" aria-label="Shopping cart">
  <div class="p-4 border-b border-gray-200 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="text-lg font-semibold">Your Cart</div>
      <div id="cart-mini-count" class="text-sm text-gray-500">(<span id="cart-count-mini">0</span> items)</div>
    </div>
    <div class="flex items-center gap-2">
      <button id="cart-clear" class="text-sm text-gray-600 hover:underline">Clear</button>
      <button id="cart-close" aria-label="Close cart" class="p-2 rounded-md hover:bg-gray-100">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
      </button>
    </div>
  </div>

  <div id="cart-items" class="p-4 overflow-auto" style="flex:1 1 auto;">
    <div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>
  </div>

  <div class="cart-footer cart-footer-sticky" style="padding:14px;border-top:1px solid #eef2f6;background:linear-gradient(180deg,#fff,#fbfbfb);">
    <div class="flex items-center justify-between mb-1">
      <div class="text-sm text-gray-600">Subtotal</div>
      <div id="cart-subtotal" class="text-lg font-bold text-[var(--primary)]"><?php echo CURRENCY_SYMBOL . ' 0.00'; ?></div>
    </div>
    <div class="checkout-cta" style="display:flex;gap:10px;">
      <a href="checkout.php" id="checkout-btn" class="checkout-btn">Checkout</a>
      <button id="continue-shopping" class="btn-secondary">Continue</button>
    </div>
    <div class="text-xs text-gray-500 mt-1">Shipping calculated at checkout</div>
  </div>
</aside>

<!-- FOOTER -->
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
/* Header interactions (mobile drawer, search panel) copied from index.php */
(function(){
  // Mobile drawer
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
    if (hamburger) hamburger.setAttribute('aria-expanded','false');
    releaseFocus(mobileLeft);
    if (hamburger) hamburger.focus();
    document.documentElement.style.overflow = '';
    document.body.style.overflow = '';
  }
  if (hamburger) hamburger.addEventListener('click', () => { if (mobileLeft && mobileLeft.classList.contains('open')) closeLeft(); else openLeft(); });
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
    setTimeout(() => searchInput?.focus(), 60);
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
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') { closeSearch(); closeLeft(); } });

  if (searchPanel) {
    searchPanel.addEventListener('click', (e)=> { if (e.target === searchPanel) closeSearch(); });
  }
})();
</script>

<script>
/* Unified cart implementation — STORAGE_KEY echoed from PHP */
(function(){
  const STORAGE_KEY = '<?php echo STORAGE_KEY; ?>';
  const cartCountEl = document.getElementById('cart-count');
  const cartCountMiniEl = document.getElementById('cart-count-mini');
  const cartDrawer = document.getElementById('cart-drawer');
  const cartBackdrop = document.getElementById('cart-backdrop');
  const cartItemsEl = document.getElementById('cart-items');
  const cartSubtotalEl = document.getElementById('cart-subtotal');
  const cartToggle = document.getElementById('cart-toggle');
  const cartClose = document.getElementById('cart-close');
  const cartClearBtn = document.getElementById('cart-clear');
  const continueBtn = document.getElementById('continue-shopping');

  function formatCurrency(v){ return '<?php echo CURRENCY_SYMBOL; ?> ' + Number(v || 0).toFixed(2); }
  function escapeHtml(s){ return String(s || '').replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

  function loadCart(){ try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e){ return {}; } }
  let cart = loadCart();

  function persistCart(){ try { localStorage.setItem(STORAGE_KEY, JSON.stringify(cart)); } catch(e){} renderCart(); }

  function getTotalItems(){ return Object.values(cart).reduce((s,it)=> s + (it.qty||0), 0); }
  function getSubtotal(){ return Object.values(cart).reduce((s,it)=> s + ((it.price||0) * (it.qty||0)), 0); }

  function renderCart(){
    const entries = Object.entries(cart);
    if(cartCountEl) cartCountEl.textContent = getTotalItems();
    if(cartCountMiniEl) cartCountMiniEl.textContent = getTotalItems();

    if(!cartItemsEl) return;
    if(!entries.length){
      cartItemsEl.innerHTML = '<div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>';
      if(cartSubtotalEl) cartSubtotalEl.textContent = formatCurrency(0);
      return;
    }
    const container = document.createElement('div');
    container.className = 'cart-grid';
    entries.forEach(([id,it])=>{
      const row = document.createElement('div'); row.className='cart-row'; row.dataset.pid=id;
      const link = document.createElement('a'); link.className='cart-link'; link.href=`product_details.php?id=${encodeURIComponent(id)}`;
      const img = document.createElement('img'); img.className='cart-item-img'; img.src = it.img || 'https://placehold.co/80x80/7b3f00/f7e0c4?text=Img'; img.alt = it.name; img.loading='lazy';
      img.onerror = function(){ this.onerror=null; this.src='https://placehold.co/80x80/ffffff/000000?text=Img'; };
      link.appendChild(img);
      const meta = document.createElement('div'); meta.className='cart-meta';
      const nameEl = document.createElement('div'); nameEl.className='cart-name'; nameEl.textContent = it.name;
      const subEl = document.createElement('div'); subEl.className='cart-sub'; subEl.textContent = formatCurrency(it.price) + ' each';
      meta.appendChild(nameEl); meta.appendChild(subEl); link.appendChild(meta);
      const actions = document.createElement('div'); actions.className='cart-actions';
      const priceEl = document.createElement('div'); priceEl.className='cart-price'; priceEl.textContent = formatCurrency((it.price||0)*(it.qty||0));
      const qtyWrap = document.createElement('div'); qtyWrap.className='cart-qty-controls';
      const decBtn = document.createElement('button'); decBtn.type='button'; decBtn.className='px-2 py-1 border rounded text-sm'; decBtn.textContent='−'; decBtn.dataset.action='decrease'; decBtn.dataset.id=id;
      const qtyBadge = document.createElement('div'); qtyBadge.className='qty-badge'; qtyBadge.textContent = it.qty;
      const incBtn = document.createElement('button'); incBtn.type='button'; incBtn.className='px-2 py-1 border rounded text-sm'; incBtn.textContent='+'; incBtn.dataset.action='increase'; incBtn.dataset.id=id;
      qtyWrap.appendChild(decBtn); qtyWrap.appendChild(qtyBadge); qtyWrap.appendChild(incBtn);
      const removeBtn = document.createElement('button'); removeBtn.type='button'; removeBtn.className='text-xs text-gray-500 mt-2 hover:underline'; removeBtn.textContent='Remove'; removeBtn.dataset.action='remove'; removeBtn.dataset.id=id;
      const quickBtn = document.createElement('a'); quickBtn.className='cart-quick'; quickBtn.href=`product_details.php?id=${encodeURIComponent(id)}`; quickBtn.textContent='View';
      actions.appendChild(priceEl); actions.appendChild(qtyWrap); actions.appendChild(removeBtn); actions.appendChild(quickBtn);
      row.appendChild(link); row.appendChild(actions); container.appendChild(row);

      [decBtn, incBtn, removeBtn].forEach(btn=>{
        btn.addEventListener('click', (e)=>{
          e.stopPropagation(); e.preventDefault();
          const action = btn.dataset.action; const pid = btn.dataset.id;
          if(!cart[pid]) return;
          if(action === 'increase') cart[pid].qty += 1;
          else if(action === 'decrease') cart[pid].qty = Math.max(1, cart[pid].qty - 1);
          else if(action === 'remove') delete cart[pid];
          persistCart();
        });
      });
    });

    cartItemsEl.innerHTML = ''; cartItemsEl.appendChild(container);
    if(cartSubtotalEl) cartSubtotalEl.textContent = formatCurrency(getSubtotal());
  }

  function openCart(){ if(cartDrawer) cartDrawer.classList.add('open'); if(cartBackdrop) cartBackdrop.classList.add('visible'); if(cartDrawer) cartDrawer.setAttribute('aria-hidden','false'); if(cartBackdrop) cartBackdrop.setAttribute('aria-hidden','false'); renderCart(); document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden'; }
  function closeCart(){ if(cartDrawer) cartDrawer.classList.remove('open'); if(cartBackdrop) cartBackdrop.classList.remove('visible'); if(cartDrawer) cartDrawer.setAttribute('aria-hidden','true'); if(cartBackdrop) cartBackdrop.setAttribute('aria-hidden','true'); document.documentElement.style.overflow=''; document.body.style.overflow=''; }

  if(cartToggle) cartToggle.addEventListener('click', ()=>{ if(cartDrawer && cartDrawer.classList.contains('open')) closeCart(); else openCart(); });
  if(cartClose) cartClose.addEventListener('click', closeCart);
  if(cartBackdrop) cartBackdrop.addEventListener('click', closeCart);
  if(continueBtn) continueBtn.addEventListener('click', closeCart);
  if(cartClearBtn) cartClearBtn.addEventListener('click', ()=>{ if(confirm('Clear all items from your cart?')){ cart = {}; persistCart(); } });

  const aliCart = {
    add(item){ if(!item || !item.id) return; const id = String(item.id); if(!cart[id]) cart[id] = { id, name: item.name || 'Product', price: Number(item.price || 0), img: item.img || '', qty: 0 }; cart[id].qty = (cart[id].qty || 0) + (Number(item.qty) || 1); persistCart(); openCart(); },
    update(id,data){ id=String(id); if(!cart[id]) return; if('qty' in data) cart[id].qty = Number(data.qty)||0; if('price' in data) cart[id].price = Number(data.price)||cart[id].price; if('name' in data) cart[id].name = data.name; if(cart[id].qty<=0) delete cart[id]; persistCart(); },
    remove(id){ id=String(id); if(cart[id]){ delete cart[id]; persistCart(); } },
    clear(){ cart={}; persistCart(); },
    get(){ return JSON.parse(JSON.stringify(cart)); }
  };

  function qtyFromPageOrButton(el){
    // Priority: data-qty on button, then #qty input, then default 1
    if(el && el.getAttribute){
      const d = el.getAttribute('data-product-qty');
      if(d && !Number.isNaN(parseInt(d,10))) return Math.max(1, parseInt(d,10));
    }
    const qtyInput = document.getElementById('qty');
    if(qtyInput){
      const q = parseInt(qtyInput.value || '1', 10);
      if(!Number.isNaN(q) && q > 0) return q;
    }
    return 1;
  }

  function addFromButtonElement(el, qty){
    if(!el) return;
    const id = el.getAttribute('data-product-id');
    if(!id) return;
    const name = el.getAttribute('data-product-name') || el.getAttribute('data-product-title') || 'Product';
    const price = parseFloat(el.getAttribute('data-product-price')) || 0;
    const img = el.getAttribute('data-product-img') || '';
    aliCart.add({ id, name, price, img, qty: qty || 1 });
  }

  window.aliCart = aliCart;
  window.addToCartFromButton = function(el, qty){ addFromButtonElement(el, qty || 1); };
  window.addToCartFromButtonWithQty = function(el){ const qtyInput = document.getElementById('qty'); const qty = qtyInput ? Math.max(1, parseInt(qtyInput.value||1,10)) : 1; addFromButtonElement(el, qty); };

  window.addEventListener('storage', function(e){ if(e.key === STORAGE_KEY || e.key === null){ cart = loadCart(); renderCart(); } });

  // Delegated click handler: Add to cart uses selected qty (from data-qty or #qty)
  document.addEventListener('click', function(e){
    const btn = e.target.closest && e.target.closest('.add-to-cart, #addToCart');
    if(!btn) return;
    e.preventDefault();
    const qty = qtyFromPageOrButton(btn);
    addFromButtonElement(btn, qty);
  }, true);

  renderCart();
})();
</script>

<script>
/* Page wiring: thumbs, qty buttons, tabs, and Buy Now behaviour
   plus hover-zoom and click-to-open-full-image modal logic */
(function(){
  // Thumbs -> change main image
  document.querySelectorAll('.thumbs img').forEach(img=>{
    img.addEventListener('click', function(){
      document.querySelectorAll('.thumbs img').forEach(i=>i.classList.remove('active'));
      this.classList.add('active');
      const src = this.getAttribute('data-src') || this.src;
      const main = document.getElementById('mainImage');
      if(main && src) main.src = src;
    });
  });

  // Qty controls
  const qtyInput = document.getElementById('qty');
  document.getElementById('qty-dec')?.addEventListener('click', ()=> qtyInput.value = Math.max(1, parseInt(qtyInput.value||1)-1));
  document.getElementById('qty-inc')?.addEventListener('click', ()=> qtyInput.value = Math.max(1, parseInt(qtyInput.value||1)+1));

  // Tabs
  const tabButtons = document.querySelectorAll('.tab-buttons button');
  const panels = document.querySelectorAll('[data-panel]');
  tabButtons.forEach(btn=>btn.addEventListener('click', ()=>{
    tabButtons.forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    const t = btn.getAttribute('data-tab');
    panels.forEach(p=> p.style.display = (p.id === 'tab-' + t) ? 'block' : 'none');
  }));

  // BUY NOW behaviour: add selected qty to cart, then go to checkout
  document.getElementById('buyNow')?.addEventListener('click', function(e){
    const btn = this;
    // get selected quantity (matching same priority used for Add to cart)
    function qtyFromPageOrButtonLocal(el){
      if(el && el.getAttribute){
        const d = el.getAttribute('data-product-qty');
        if(d && !Number.isNaN(parseInt(d,10))) return Math.max(1, parseInt(d,10));
      }
      const qtyInput = document.getElementById('qty');
      if(qtyInput){
        const q = parseInt(qtyInput.value || '1', 10);
        if(!Number.isNaN(q) && q > 0) return q;
      }
      return 1;
    }
    const qty = qtyFromPageOrButtonLocal(btn);
    const id = btn.getAttribute('data-product-id') || '';
    if (!id) { window.location.href = 'checkout.php'; return; }
    const name = btn.getAttribute('data-product-name') || 'Product';
    const price = parseFloat(btn.getAttribute('data-product-price') || 0) || 0;
    const img = btn.getAttribute('data-product-img') || '';
    try {
      if (window.aliCart && typeof window.aliCart.add === 'function') {
        window.aliCart.add({ id: String(id), name: name, price: price, img: img, qty: qty });
        setTimeout(()=> { window.location.href = 'checkout.php'; }, 220);
      } else {
        const STORAGE_KEY = '<?php echo STORAGE_KEY; ?>';
        const raw = localStorage.getItem(STORAGE_KEY) || '{}';
        let cart = {};
        try { cart = JSON.parse(raw || '{}'); } catch(e){}
        const key = String(id);
        if (!cart[key]) cart[key] = { id: key, name: name, price: Number(price || 0), img: img || '', qty: 0 };
        cart[key].qty = (Number(cart[key].qty || 0) + Number(qty || 1));
        try { localStorage.setItem(STORAGE_KEY, JSON.stringify(cart)); } catch(e){}
        setTimeout(()=> { window.location.href = 'checkout.php'; }, 220);
      }
    } catch(err) {
      window.location.href = 'checkout.php';
    }
  });

  /* Hover-zoom: toggle a CSS scale class on mouseenter/mouseleave.
     Click -> open modal with full-size image. Also support keyboard (Enter) when focused. */
  const mainImg = document.getElementById('mainImage');
  const mainWrapper = document.getElementById('main-img-wrapper');
  const imgModal = document.getElementById('imgModal');
  const imgModalImg = document.getElementById('imgModalImg');
  const imgModalClose = document.getElementById('imgModalClose');

  if (mainImg && mainWrapper && imgModal && imgModalImg) {
    // Allow focusing for keyboard users
    mainWrapper.setAttribute('tabindex', '0');
    mainWrapper.style.outline = 'none';

    // Hover / pointer interactions
    mainWrapper.addEventListener('pointerenter', function(){
      mainImg.classList.add('zoomed');
    });
    mainWrapper.addEventListener('pointerleave', function(){
      mainImg.classList.remove('zoomed');
      // reset transform origin
      mainImg.style.transformOrigin = 'center center';
    });

    // Move the transform origin to cursor position for nicer pan effect
    mainWrapper.addEventListener('pointermove', function(ev){
      if (!mainImg.classList.contains('zoomed')) return;
      const rect = mainWrapper.getBoundingClientRect();
      const x = ((ev.clientX - rect.left) / rect.width) * 100;
      const y = ((ev.clientY - rect.top) / rect.height) * 100;
      mainImg.style.transformOrigin = `${x}% ${y}%`;
    });

    // Click/tap opens modal with full image
    function openImageModal(src, alt) {
      imgModalImg.src = src || mainImg.src;
      imgModalImg.alt = alt || mainImg.alt || 'Image';
      imgModal.classList.add('open');
      imgModal.setAttribute('aria-hidden', 'false');
      // prevent background scroll
      document.documentElement.style.overflow = 'hidden';
      document.body.style.overflow = 'hidden';
      // set focus to close button
      setTimeout(()=> { imgModalClose?.focus(); }, 60);
    }
    function closeImageModal() {
      imgModal.classList.remove('open');
      imgModal.setAttribute('aria-hidden', 'true');
      imgModalImg.src = '';
      document.documentElement.style.overflow = '';
      document.body.style.overflow = '';
      mainWrapper.focus();
    }

    mainWrapper.addEventListener('click', function(e){
      // If zoomed, clicking should open modal rather than toggle zoom class
      openImageModal(mainImg.src, mainImg.alt);
    });

    // keyboard support: Enter or Space opens modal
    mainWrapper.addEventListener('keydown', function(e){
      if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        openImageModal(mainImg.src, mainImg.alt);
      } else if (e.key === 'Escape') {
        // ensure zoom removed on Escape
        mainImg.classList.remove('zoomed');
      }
    });

    // Modal close interactions
    imgModalClose?.addEventListener('click', closeImageModal);
    imgModal.addEventListener('click', function(e){
      if (e.target === imgModal) closeImageModal();
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && imgModal.classList.contains('open')) {
        closeImageModal();
      }
    });
  }
})();
</script>

<?php if ($conn) $conn->close(); ?>
</body>
</html>
