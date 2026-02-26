<?php
declare(strict_types=1);
session_start();

// -------------------------
// Database configuration
// -------------------------
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

define('CURRENCY_SYMBOL', '$');
define('STORAGE_KEY', 'ali_hair_cart_v1');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$connection_error = null;
if ($conn->connect_error) {
    $connection_error = "Database Connection Failed: " . $conn->connect_error;
    error_log($connection_error);
    $conn = null;
}
if ($conn) $conn->set_charset('utf8mb4');

// -------------------------
// Helper functions
// -------------------------
function fetchSlides($conn) {
    if (!$conn) return false;
    $sql = "SELECT title, subtitle, button_text, image_url FROM sliderimages ORDER BY title ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }
    return false;
}

function fetchProductCategories($conn) {
    if (!$conn) return false;
    $sql = "SELECT category_id, name, description, image_url, sort_order FROM product_category ORDER BY sort_order ASC, name ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }
    return false;
}

function fetchProductsByCategory($conn, $category_identifier) {
    if (!$conn) return false;
    if (is_numeric($category_identifier)) {
        $sql = "SELECT id, name, description, price, image_placeholder FROM products WHERE category_id = ? ORDER BY name ASC";
        if ($stmt = $conn->prepare($sql)) {
            $cid = (int)$category_identifier;
            $stmt->bind_param("i", $cid);
            $stmt->execute();
            $res = $stmt->get_result();
            $stmt->close();
            return $res;
        }
    }
    $sql = "SELECT id, name, description, price, image_placeholder FROM products WHERE category = ? ORDER BY name ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("s", $category_identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }
    return false;
}

function fetchCustomerReviews($conn, int $limit = 6) {
    if (!$conn) return false;
    $sql = "SELECT id, name, rating, title, body, created_at FROM reviews WHERE visible = 1 ORDER BY created_at DESC LIMIT ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $stmt->close();
        return $res;
    }
    return false;
}

function firstProductImage(?string $placeholder): ?string {
    if (empty($placeholder)) return null;
    $decoded = json_decode($placeholder, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (is_array($decoded) && !empty($decoded[0]) && is_string($decoded[0])) {
            return $decoded[0];
        }
    }
    return $placeholder;
}

// -------------------------
// Fetch page data
// -------------------------
$slides_result = $conn ? fetchSlides($conn) : false;
$categories_result = $conn ? fetchProductCategories($conn) : false;
$men_products_result = $conn ? fetchProductsByCategory($conn, 'men') : false;
$women_products_result = $conn ? fetchProductsByCategory($conn, 'women') : false;
$reviews_result = $conn ? fetchCustomerReviews($conn, 6) : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
      <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/favicon.png">

    <title>Ali Hair Wigs — Premium Wigs Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700;800&display=swap" rel="stylesheet">
    <style>
         :root{
      --primary:#7b3f00;
      --secondary:#f7e0c4;
      --muted:#6b7280;
      --bg:#fcfcfc;
      --card:#ffffff;
      --max-width:1200px;
      --container-pad:16px;
    }
    *{box-sizing:border-box}
    html,body{height:100%;margin:0;background:var(--bg);font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,Arial;color:#111827;-webkit-font-smoothing:antialiased}
    img{display:block;max-width:100%;height:auto}
        html,body { height:100%; }
        body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial; background:#fcfcfc; -webkit-font-smoothing:antialiased; color:#1f2937; -webkit-tap-highlight-color: transparent; margin:0; }

        /* HERO: Facebook cover proportions */
#hero-stage{position:relative;overflow:hidden;border-bottom-left-radius:18px;border-bottom-right-radius:18px;background:#000}
.hero-stage {
  margin: 0 auto;               /* center horizontally */
  max-width: 1200px;            /* optional: limit width so stage is centered on large screens */
  width: 100%;
  display: block;
}
@media (max-width:640px){
  .hero-stage {
    aspect-ratio: 640 / 360;
    max-height: 420px;
    min-height: 180px;
  }
}



/* existing selector to update */
.slide img.hero-img {
  width:100%;
  height:100%;
  object-fit:cover;
  object-position: center 70%; 

}

.slides{display:flex;width:100%;height:100%;transition:transform .55s cubic-bezier(.2,.9,.2,1);will-change:transform}
.slide {
  flex: 0 0 100%;
  display: flex;
  align-items: center;         /* vertical center */
  justify-content: center;     /* horizontal center */
  position: relative;
  overflow: hidden;
}
.slide picture, .slide img.hero-img{width:100%;height:100%;display:block}

.slide img.hero-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center center; /* ensures the visible area is centered */
  display: block;
}
@media (max-width:640px) {
  .slide img.hero-img { object-position: center 45%; }
}

/* Remove overlay content visually (keeps markup for accessibility) */
.hero-overlay{position:absolute;inset:0;pointer-events:none}
.hero-overlay .hero-card{display:none}

/* Controls */
.hero-control{position:absolute;top:50%;transform:translateY(-50%);z-index:40;background:rgba(0,0,0,0.45);color:#fff;border:0;width:44px;height:44px;border-radius:999px;display:flex;align-items:center;justify-content:center;cursor:pointer}
.hero-control.prev{left:12px}
.hero-control.next{right:12px}
@media (max-width:640px){ .hero-control{display:none} }

.hero-dots{position:absolute;bottom:12px;left:50%;transform:translateX(-50%);display:flex;gap:8px;z-index:45}
.hero-dots button{background:rgba(255,255,255,0.45);border:0;height:8px;border-radius:999px;width:8px;padding:0;transition:all .18s;cursor:pointer}
.hero-dots button.active{width:28px;background:#fff}

        /* rest of your CSS unchanged (omitted here for brevity in comment) */
        /* CATEGORY CAROUSEL */
        .category-carousel { display:flex; gap:1rem; overflow-x:auto; scroll-behavior:smooth; -webkit-overflow-scrolling:touch; padding-bottom:.5rem; }
        .category-card { flex: 0 0 20rem; border-radius:.75rem; overflow:hidden; background:#fff; border:1px solid #eee; transition:transform .25s; text-decoration:none; color:inherit; display:block; box-shadow:0 6px 18px rgba(20,20,20,0.04); }
        .category-card:hover { transform:translateY(-6px); }
        .category-card img { width:100%; height:12rem; object-fit:cover; display:block; }

        .category-card img.mobile-perfect { height:10rem; object-fit:cover; object-position:center; }
        @media (min-width:640px) { .category-card { flex: 0 0 16rem; } }
        @media (min-width:768px) { .category-card { flex: 0 0 13rem; } }

        /* PRODUCT GRID */
        .products-grid {
          display: grid;
          gap: 0.75rem;
          grid-template-columns: repeat(2, minmax(0, 1fr));
          align-items: stretch;
        }
        @media (min-width: 768px) { .products-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; } }
        @media (min-width: 1024px) { .products-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.25rem; } }

        .product-card { background: #fff; border-radius: 12px; overflow: hidden; display: flex; flex-direction: column; gap: 10px; border: 1px solid #eef2f6; box-shadow: 0 6px 18px rgba(12,18,24,0.04); text-decoration: none; color: inherit; padding: 10px; height: 100%; }
        .product-thumb { width: 100%; aspect-ratio: 4 / 4; object-fit: cover; border-radius: 8px; background: #f8fafc; flex: 0 0 auto; box-shadow: 0 6px 18px rgba(43, 25, 13, 0.06); }
      
        .product-meta { display: flex; flex-direction: column; gap: 6px; flex: 1 1 auto; min-height: 0; }
        .product-name { font-weight:700; font-size:0.98rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .product-desc { font-size:0.8rem; color:#6b7280; line-height:1.15; overflow:hidden; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
        .product-footer { display:flex; gap:8px; align-items:center; justify-content:space-between; flex: 0 0 auto; margin-top:auto; }
        .product-price { font-weight:800; color:var(--primary); font-size:0.98rem; }
        .product-cta { background:rgba(26, 14, 2, 0.18); color:#fff; border-radius:8px; padding:8px 10px; font-weight:700; border:none; cursor:pointer; }

        /* CART DRAWER */
        .cart-drawer { position:fixed; right:0; top:0; height:100vh; width:90vw; max-width:420px; background:#fff; box-shadow:-20px 0 40px rgba(0,0,0,.12); transform:translateX(110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:60; display:flex; flex-direction:column; border-top-left-radius:14px; border-bottom-left-radius:14px; overflow:hidden; }
        .cart-drawer.open { transform:translateX(0); }
        .cart-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); opacity:0; pointer-events:none; transition:opacity .25s; z-index:50; }
        .cart-backdrop.visible { opacity:1; pointer-events:auto; }

        .cart-item-img { width:72px; height:72px; aspect-ratio:1 / 1; object-fit:cover; border-radius:.5rem; flex:0 0 72px; box-shadow:0 6px 18px rgba(0,0,0,0.06); }

        .cart-row {
          display: flex;
          flex-direction: column;
          gap: 0.625rem;
          align-items: stretch;
          padding:0.6rem;
          border-radius:0.75rem;
          background:#ffffff;
          border:1px solid #eef2f6;
          box-shadow:0 2px 6px rgba(12,18,24,0.04);
        }
        .cart-row a.cart-link { display:flex; gap:0.75rem; align-items:center; text-decoration:none; color:inherit; width:100%; }
        .cart-row .cart-meta { display:flex; flex-direction:column; gap:0.25rem; min-width:0; }
        .cart-row .cart-name { font-weight:700; font-size:0.98rem; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .cart-row .cart-sub { font-size:0.78rem; color:#6b7280; }
        .cart-row .cart-actions { text-align:left; display:flex; gap:0.5rem; align-items:center; justify-content:flex-start; flex-wrap:wrap; }
        .cart-row .cart-price { color:var(--primary); font-weight:800; font-size:0.95rem; }
        .cart-row .cart-qty-controls button { padding:0.26rem 0.45rem; border-radius:8px; border:1px solid #e5e7eb; background:#fff; cursor:pointer; }
        .cart-row .cart-qty-controls .qty-badge { padding:6px 9px; border-radius:8px; background:#f8fafc; border:1px solid #eef2f6; font-weight:600; color:#111827; }
        .cart-row .cart-quick { font-size:0.78rem; padding:0.35rem 0.6rem; border-radius:8px; background:#f3f4f6; border:1px solid #010f2bff; color:#111827; text-decoration:none; display:inline-block; }
        .cart-row .cart-quick:hover { background:#fff; }

        /* Make the cart items grid simple: single column list */
        #cart-items .cart-grid { display:block; gap:0.75rem; }
        #cart-items .cart-grid > .cart-row { margin-bottom:0.75rem; }

        .cart-footer { background:linear-gradient(180deg, #fff 0%, #fbfbfb 100%); border-top: 1px solid #eef2f6; padding:14px; display:flex; flex-direction:column; gap:10px; }
        .cart-footer .checkout-cta { width:100%; display:flex; gap:10px; }
        .checkout-btn { flex:1; background:var(--primary); color:#fff; padding:12px 14px; border-radius:10px; text-align:center; font-weight:800; text-decoration:none; box-shadow: 0 8px 22px rgba(123,63,0,0.18); }
        .secondary-btn { flex:1; border:1px solid #e6e6e6; background:#fff; padding:12px 14px; border-radius:10px; text-align:center; font-weight:700; color:#374151; text-decoration:none; }

        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }

        /* Mobile left drawer - now FULL SCREEN on small devices */
        .mobile-left-drawer {
            position:fixed;
            left:0;
            top:0;
            height:100vh;
            width:84vw;
            max-width:320px;
            background:#fff;
            box-shadow:20px 0 40px rgba(0,0,0,.12);
            transform:translateX(-110%);
            transition:transform .32s cubic-bezier(.2,.9,.2,1);
            z-index:70;
            padding:1rem;
            overflow:auto;
        }
        @media (max-width: 640px) {
            .mobile-left-drawer { width:100vw; max-width:none; border-top-right-radius:0; border-bottom-right-radius:0; padding:1.25rem; }
            .mobile-left-drawer .drawer-inner { height:100vh; display:flex; flex-direction:column; }
            .mobile-left-drawer .drawer-scroll { overflow:auto; flex:1 1 auto; padding-bottom:60px; }
        }
        .mobile-left-drawer.open { transform:translateX(0); top:0; left:0; }

        .mobile-left-close { display:inline-flex; align-items:center; gap:6px; cursor:pointer; }

        /* Ensure accessible focus outline */
        :focus { outline: 3px solid rgba(59,130,246,0.25); outline-offset: 3px; }

       

         /* Header */
    header.site-header{position:sticky;top:0;z-index:90;background:var(--card);box-shadow:0 1px 0 rgba(0,0,0,0.04)}
    .header-inner{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;max-width:var(--max-width);margin:0 auto;padding-left:var(--container-pad);padding-right:var(--container-pad)}
    .logo{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--primary);font-weight:800}
    .logo img{width:44px;height:44px;object-fit:contain;border-radius:6px}
    .logo-text{display:flex;flex-direction:column;line-height:1}
    .logo-main{font-weight:800;color:var(--primary);font-size:1rem}
    .logo-sub{font-weight:800;color:#374151;font-size:.95rem}

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

 /* Container */
    .container{max-width:var(--max-width);margin:0 auto;padding:0 var(--container-pad)}
    /* Footer social icons: 3 per row on small screens, inline on larger screens */
.footer-social {
  display: grid;
  grid-template-columns: repeat(6, auto); /* default: 6 inline items */
  gap: 0.5rem;
  align-items: center;
  justify-content: start;
}

/* Make icons visually consistent */
.footer-social a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 6px;
}

/* Mobile: 3 columns (two rows when 6 icons present) */
@media (max-width: 640px) {
  .footer-social {
    grid-template-columns: repeat(3, 1fr);
    justify-items: center;
  }
  .footer-social a { width: 44px; height: 44px; }
  .footer-social svg { width: 22px; height: 22px; }
}

/* Slight spacing/tone adjustments */
.footer-connect { display:flex; flex-direction:column; gap:6px; }
.footer-connect .contact-email { margin-top:4px; color:#cbd5e1; font-size:0.95rem; }


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

<!-- Mobile left drawer (FULL-SCREEN on small devices) -->
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
        </div>

        <div class="px-4 py-4 border-t border-gray-100">
            <div class="flex items-center justify-between">
                <div class="text-sm text-gray-700">Email: alihairwig.bd@gmail.com</div>
              
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
     <button type="submit" class="search-submit">Search</button>
  </div>
</div>

<main>

<?php if ($connection_error): ?>
    <div class="max-w-7xl mx-auto mt-6 p-4 bg-red-50 border-l-4 border-red-500 text-red-700 rounded-md shadow-md">
        <p class="font-bold">Database Connection Warning</p>
        <p class="text-sm">Could not connect to the database. Dynamic content will be unavailable.</p>
    </div>
<?php endif; ?>

<!-- HERO SLIDER -->
<section id="hero-slider" aria-label="Homepage banner">
  <div class="hero-stage" id="hero-stage">
    <div class="slides" id="slides">
      <?php
      $slide_count = 0;
      if ($slides_result && $slides_result->num_rows > 0):
          while ($slide = $slides_result->fetch_assoc()):
              $slide_count++;
              $desktop = !empty($slide['image_url']) ? htmlspecialchars($slide['image_url'], ENT_QUOTES) : 'https://placehold.co/1600x900/7b3f00/f7e0c4?text=Slide';
              $mobile = !empty($slide['mobile_image_url']) ? htmlspecialchars($slide['mobile_image_url'], ENT_QUOTES) : $desktop;
      ?>
        <div class="slide" role="group" aria-roledescription="slide" data-index="<?php echo $slide_count - 1 ?>">
          <picture>
            <source media="(max-width:640px)" srcset="<?php echo $mobile ?>">
            <img class="hero-img" src="<?php echo $desktop ?>" alt="" loading="eager" draggable="false">
          </picture>
          <div class="hero-overlay" aria-hidden="true">
            <div class="hero-card" role="region" aria-label="Hero content"></div>
          </div>
        </div>
      <?php
          endwhile;
          $slides_result->free();
      else:
      ?>
        <div class="slide" role="group" aria-roledescription="slide" data-index="0">
          <img class="hero-img" src="https://placehold.co/1600x900/7b3f00/f7e0c4?text=Ali+Hair+Wigs" alt="">
          <div class="hero-overlay" aria-hidden="true"><div class="hero-card" role="region" aria-label="Hero content"></div></div>
        </div>
      <?php endif; ?>
    </div>

    <button class="hero-control prev" id="hero-prev" aria-label="Previous slide" type="button">‹</button>
    <button class="hero-control next" id="hero-next" aria-label="Next slide" type="button">›</button>

    <div class="hero-dots" id="hero-dots" role="tablist" aria-label="Slide dots"></div>
  </div>
</section>




<!-- FEATURES -->
<section class="features" aria-label="Store features" style="margin-top:18px">
  <div class="features-grid" style="max-width:var(--max-width);margin:0 auto">
    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:12px">
      <div style="color:var(--primary);margin-bottom:8px">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M3 12h13v6H3z"/><path d="M16 8h5l-1.5 4.5"/><circle cx="7.5" cy="18.5" r="1.5"/><circle cx="18.5" cy="18.5" r="1.5"/></svg>
      </div>
      <p style="font-weight:700;margin:0">Quick Delivery</p>
      <p style="color:var(--muted);font-size:13px;margin:6px 0 0">Fast Shipping Available</p>
    </div>

    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:12px">
      <div style="color:var(--primary);margin-bottom:8px">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M7 7V5a5 5 0 0 1 10 0v2"/></svg>
      </div>
      <p style="font-weight:700;margin:0">Payment Secure</p>
      <p style="color:var(--muted);font-size:13px;margin:6px 0 0">100% Payment Safe</p>
    </div>

    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:12px">
      <div style="color:var(--primary);margin-bottom:8px">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16 5.5 20l2-7L2 9h7z"/></svg>
      </div>
      <p style="font-weight:700;margin:0">Quality Assurance</p>
      <p style="color:var(--muted);font-size:13px;margin:6px 0 0">Guaranteed Top-Quality Support</p>
    </div>

    <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:12px">
      <div style="color:var(--primary);margin-bottom:8px">
        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M21 15a4 4 0 0 1-4 4H7l-4 2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
      </div>
      <p style="font-weight:700;margin:0">Premium Service</p>
      <p style="color:var(--muted);font-size:13px;margin:6px 0 0">Top quality 24/7 Support</p>
    </div>
  </div>
</section>

<!-- PRODUCT CATEGORIES -->
<section id="categories" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <h2 class="text-4xl font-extrabold text-gray-900 mb-4 text-center">Find Your Perfect Base</h2>
    <p class="text-center text-lg text-gray-600 mb-8 max-w-3xl mx-auto">Explore our premium hair system categories, each designed for comfort, durability, and realism.</p>

    <div class="relative">
        <div id="category-carousel" class="category-carousel" aria-label="Product categories carousel" tabindex="0">
            <?php
            if ($categories_result && $categories_result->num_rows > 0):
                while ($cat = $categories_result->fetch_assoc()):
                    $cat_id = (int)$cat['category_id'];
                    $cat_name = htmlspecialchars($cat['name'], ENT_QUOTES);
                    $cat_desc = htmlspecialchars($cat['description'], ENT_QUOTES);
                    $cat_img = !empty($cat['image_url']) ? htmlspecialchars($cat['image_url'], ENT_QUOTES) : 'https://placehold.co/600x480/7b3f00/f7e0c4?text=No+Image';
                    $cat_slug = rawurlencode(strtolower(str_replace(' ', '_', $cat_name)));
                    $mobile_class = ($cat_name === 'Lace') ? 'mobile-perfect' : '';
            ?>
                <a href="#categories?id=<?php echo $cat_id ?>&slug=<?php echo $cat_slug ?>" class="category-card" aria-label="<?php echo $cat_name ?>">
                    <img src="<?php echo $cat_img ?>" alt="<?php echo $cat_name ?>" class="<?php echo $mobile_class ?>" onerror="this.onerror=null;this.src='https://placehold.co/600x480/374151/ffffff?text=Image';">
                    <div class="p-4 text-center">
                        <h3 class="text-lg font-bold text-gray-900"><?php echo $cat_name ?></h3>
                        <p class="text-sm text-[var(--primary)] mt-1"><?php echo $cat_desc ?></p>
                    </div>
                </a>
            <?php
                endwhile;
                $categories_result->free();
            else:
            ?>
                <div class="p-8 bg-gray-100 rounded-lg text-center">No product categories found.</div>
            <?php endif; ?>
        </div>

        <button id="cat-prev" aria-label="Previous category" class="absolute left-2 top-1/2 -translate-y-1/2 bg-white p-2 rounded-full shadow hidden sm:inline-flex">‹</button>
        <button id="cat-next" aria-label="Next category" class="absolute right-2 top-1/2 -translate-y-1/2 bg-white p-2 rounded-full shadow hidden sm:inline-flex">›</button>
    </div>
</section>

<!-- MEN'S WIGS (responsive product grid) -->
<section id="men" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 bg-[var(--secondary)] rounded-xl shadow-xl">
    <h2 class="text-3xl sm:text-4xl font-bold text-[var(--primary)] mb-8 text-center border-b-2 border-[var(--primary)] pb-3 max-w-md mx-auto">Men's Wigs</h2>

    <div class="products-grid">
        <?php
        if ($men_products_result && $men_products_result->num_rows > 0):
            while ($p = $men_products_result->fetch_assoc()):
                $pid = (int)$p['id'];
                $pname = htmlspecialchars($p['name'], ENT_QUOTES);
                $pdesc = htmlspecialchars($p['description'], ENT_QUOTES);
                $pprice = number_format((float)$p['price'], 2);

                $firstImage = firstProductImage($p['image_placeholder'] ?? null);
                if ($firstImage) {
                    $pimg = htmlspecialchars($firstImage, ENT_QUOTES);
                } else {
                    $pimg = 'https://placehold.co/800x600/7b3f00/f7e0c4?text=Wig+Placeholder';
                }
        ?>
            <a href="product_details.php?id=<?php echo $pid ?>" class="product-card" aria-label="<?php echo $pname ?>">
                <img src="<?php echo $pimg ?>" alt="<?php echo $pname ?>" class="product-thumb" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/400x300/ffffff/000000?text=Img';">
                <div class="product-meta">
                    <div class="product-name"><?php echo $pname ?></div>
                    <div class="product-desc"><?php echo $pdesc ?></div>
                </div>
                <div class="product-footer">
                    <div class="product-price"><?php echo CURRENCY_SYMBOL . ' ' . $pprice ?></div>
                    <button class="product-cta add-to-cart"
                        data-product-id="<?php echo $pid ?>"
                        data-product-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                        data-product-price="<?php echo (float)$p['price'] ?>"
                        data-product-img="<?php echo $pimg ?>">Add</button>
                </div>
            </a>
        <?php
            endwhile;
            $men_products_result->free();
        else:
        ?>
            <div class="col-span-full text-center p-6 bg-white rounded-lg">No men's products found.</div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-8">
        <a href="mens_wigs.php" class="inline-flex items-center px-6 py-2 border-2 border-[var(--primary)] text-[var(--primary)] rounded-full hover:bg-[var(--primary)] hover:text-white transition">View All Men's Styles</a>
    </div>
</section>

<!-- WOMEN'S WIGS (responsive product grid) -->
<section id="women" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 mt-12">
    <h2 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-8 text-center border-b-2 border-gray-800 pb-3 max-w-md mx-auto">Women's Wigs</h2>

    <div class="products-grid">
        <?php
        if ($women_products_result && $women_products_result->num_rows > 0):
            while ($p = $women_products_result->fetch_assoc()):
                $pid = (int)$p['id'];
                $pname = htmlspecialchars($p['name'], ENT_QUOTES);
                $pdesc = htmlspecialchars($p['description'], ENT_QUOTES);
                $pprice = number_format((float)$p['price'], 2);

                $firstImage = firstProductImage($p['image_placeholder'] ?? null);
                if ($firstImage) {
                    $pimg = htmlspecialchars($firstImage, ENT_QUOTES);
                } else {
                    $pimg = 'https://placehold.co/800x600/7b3f00/f7e0c4?text=Wig+Placeholder';
                }
        ?>
            <a href="product_details.php?id=<?php echo $pid ?>" class="product-card" aria-label="<?php echo $pname ?>">
                <img src="<?php echo $pimg ?>" alt="<?php echo $pname ?>" class="product-thumb" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/400x300/ffffff/000000?text=Img';">
                <div class="product-meta">
                    <div class="product-name"><?php echo $pname ?></div>
                    <div class="product-desc"><?php echo $pdesc ?></div>
                </div>
                <div class="product-footer">
                    <div class="product-price"><?php echo CURRENCY_SYMBOL . ' ' . $pprice ?></div>
                    <button class="product-cta add-to-cart"
                        data-product-id="<?php echo $pid ?>"
                        data-product-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                        data-product-price="<?php echo (float)$p['price'] ?>"
                        data-product-img="<?php echo $pimg ?>">Add</button>
                </div>
            </a>
        <?php
            endwhile;
            $women_products_result->free();
        else:
        ?>
            <div class="col-span-full text-center p-6 bg-gray-100 rounded-lg">No women's products found.</div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-8">
        <a href="womens_wigs.php" class="inline-flex items-center px-6 py-2 border-2 border-gray-800 text-gray-800 rounded-full hover:bg-gray-800 hover:text-white transition">View All Women's Styles</a>
    </div>
</section>

<!-- CUSTOMER REVIEWS -->
<section id="reviews" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 mt-12 bg-white rounded-xl shadow-lg">
    <h2 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-6 text-center">Customer Reviews</h2>
    <p class="text-center text-lg text-gray-600 mb-8 max-w-3xl mx-auto">Real feedback from customers who love our wigs. Read the latest reviews below.</p>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php
        if ($reviews_result && $reviews_result->num_rows > 0):
            while ($r = $reviews_result->fetch_assoc()):
                $rid = (int)$r['id'];
                $rname = htmlspecialchars($r['name'] ?? 'Anonymous', ENT_QUOTES);
                $rtitle = htmlspecialchars($r['title'] ?? '', ENT_QUOTES);
                $rbody = htmlspecialchars($r['body'] ?? '', ENT_QUOTES);
                $rrating = max(0, min(5, (int)($r['rating'] ?? 0)));
                $rdate = !empty($r['created_at']) ? date('M j, Y', strtotime($r['created_at'])) : '';
        ?>
            <article class="p-4 bg-gray-50 rounded-lg border border-gray-100 shadow-sm">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 rounded-full bg-[var(--primary)] text-white flex items-center justify-center font-bold"><?php echo strtoupper(substr($rname,0,1)) ?></div>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div class="font-semibold text-gray-900"><?php echo $rname ?></div>
                                <?php if ($rtitle): ?><div class="text-sm text-[var(--primary)]"><?php echo $rtitle ?></div><?php endif; ?>
                            </div>
                            <div class="text-sm text-gray-500"><?php echo $rdate ?></div>
                        </div>

                        <div class="mt-3 text-sm text-gray-700">
                            <?php echo $rbody ?>
                        </div>

                        <div class="mt-3">
                            <div class="flex items-center gap-1" aria-hidden="true">
                                <?php for ($i=1;$i<=5;$i++): ?>
                                    <?php if ($i <= $rrating): ?>
                                        <svg class="w-4 h-4 text-yellow-400" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.966a1 1 0 00.95.69h4.178c.969 0 1.371 1.24.588 1.81l-3.385 2.455a1 1 0 00-.364 1.118l1.286 3.966c.3.921-.755 1.688-1.538 1.118L10 15.347l-3.85 2.7c-.783.57-1.838-.197-1.538-1.118l1.286-3.966a1 1 0 00-.364-1.118L2.15 9.393c-.783-.57-.38-1.81.588-1.81h4.178a1 1 0 00.95-.69L9.049 2.927z"/></svg>
                                    <?php else: ?>
                                        <svg class="w-4 h-4 text-gray-300" viewBox="0 0 20 20" fill="currentColor"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.966a1 1 0 00.95.69h4.178c.969 0 1.371 1.24.588 1.81l-3.385 2.455a1 1 0 00-.364 1.118l1.286 3.966c.3.921-.755 1.688-1.538 1.118L10 15.347l-3.85 2.7c-.783.57-1.838-.197-1.538-1.118l1.286-3.966a1 1 0 00-.364-1.118L2.15 9.393c-.783-.57-.38-1.81.588-1.81h4.178a1 1 0 00.95-.69L9.049 2.927z"/></svg>
                                    <?php endif; ?>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        <?php
            endwhile;
            $reviews_result->free();
        else:
        ?>
            <div class="col-span-full text-center p-6 bg-gray-50 rounded-lg">No customer reviews yet.</div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-8">
        <a href="reviews.php" class="inline-flex items-center px-6 py-2 border-2 border-[var(--primary)] text-[var(--primary)] rounded-full hover:bg-[var(--primary)] hover:text-white transition">Read all reviews</a>
    </div>
</section>

<div class="h-12"></div>

<!-- ABOUT / FACTORY -->
<section id="about" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 bg-white rounded-xl shadow-lg mb-12">
    <div class="grid gap-8 md:grid-cols-2 items-center">
        <div>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">ABOUT ALI HAIR WIGS</h2>
            <h3 class="text-2xl font-bold text-[var(--primary)] mb-4">YOUR TRUSTED HUMAN HAIR WIG & EXTENSION PARTNER</h3>
            <p class="text-gray-600 mb-6">
                Ali Hair Wigs is a Bangladesh-based premium manufacturer and global supplier of high-quality human hair wigs and hair extensions. We specialize in sourcing, crafting, and delivering top-grade human hair and synthetic hair solutions to meet the diverse needs of retailers, salons, beauty professionals, and private-label brands worldwide.

With a commitment to excellence, Ali Hair Wigs ensures ethical sourcing, advanced manufacturing techniques, and strict quality control across every stage of production. Our products are designed for durability, comfort, and a natural look—helping customers feel confident and beautiful.
            </p>

            <div class="grid gap-2 text-gray-700">
               
            </div>

            <div class="mt-6 flex flex-wrap gap-4">
                <a href="#categories" class="inline-flex items-center px-5 py-2 bg-[var(--primary)] text-white font-semibold rounded-full shadow hover:opacity-95 transition">Explore Products</a>
                <a href="contact.php" class="inline-flex items-center px-5 py-2 border border-gray-300 text-gray-700 rounded-full hover:bg-gray-50 transition">Contact Sales</a>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-gray-50 rounded-lg p-4 flex items-start gap-4">
                <img src="uploads/ahw.png" alt="Factory shelf and wigs" class="w-28 h-20 object-cover rounded-md shadow-sm" onerror="this.onerror=null;this.src='https://placehold.co/400x300/7b3f00/f7e0c4?text=Image'">
                <div>
                    <p class="font-semibold text-gray-800">Factory & Warehouse</p>
                    <p class="text-sm text-gray-500">On-site quality checks and organised stock for fast order fulfilment.</p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 flex items-start gap-4">
                <img src="uploads/1.jpg" alt="Wig production" class="w-28 h-20 object-cover rounded-md shadow-sm" onerror="this.onerror=null;this.src='https://placehold.co/400x300/7b3f00/f7e0c4?text=Image'">
                <div>
                    <p class="font-semibold text-gray-800">Skilled Craftsmanship</p>
                    <p class="text-sm text-gray-500">Experienced technicians produce consistent, natural-looking hairpieces.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
        <div class="bg-[var(--primary)] text-white rounded-lg p-6">
            <div class="text-2xl font-extrabold">180+</div>
            <div class="mt-1 text-sm">COUNTRIES</div>
        </div>
        <div class="bg-gray-100 rounded-lg p-6">
            <div class="text-2xl font-extrabold">100%</div>
            <div class="mt-1 text-sm">ETHICAL SOURCE</div>
        </div>
        <div class="bg-gray-100 rounded-lg p-6">
            <div class="text-2xl font-extrabold">250+</div>
            <div class="mt-1 text-sm">STOCK TYPES</div>
        </div>
    </div>
</section>

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
        <li><a href="ams/login.php" class="hover:text-[var(--secondary)]">Software</a></li>
      </ul>
    </div>

    <div>
      <h4 class="text-lg font-semibold mb-3">Connect</h4>

      <!-- Three icons per row, centered, SVGs use currentColor so hover works -->
      <div class="grid grid-cols-3 gap-3 text-2xl mb-4 justify-items-center">
        <!-- Facebook -->
        <a href="https://www.facebook.com/alihairwigs" class="text-white hover:text-blue-500 flex items-center justify-center p-2" aria-label="Facebook">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M22 12a10 10 0 1 0-11.5 9.9v-7h-2v-3h2v-2.3c0-2 1.2-3.1 3-3.1.9 0 1.8.1 2 .1v2.3h-1.1c-1 0-1.3.6-1.3 1.2V12h2.6l-.4 3h-2.2v7A10 10 0 0 0 22 12z"/>
          </svg>
        </a>

        <!-- WhatsApp -->
        <a href="https://wa.me/+8801920899031" class="text-white hover:text-green-500 flex items-center justify-center p-2" aria-label="WhatsApp">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12.04 2c-5.52 0-10 4.48-10 10 0 1.76.46 3.47 1.34 4.99L2 22l5.12-1.34A10.02 10.02 0 0 0 12.04 22c5.52 0 10-4.48 10-10s-4.48-10-10-10zm0 18.3c-1.62 0-3.2-.43-4.58-1.25l-.33-.19-3.04.8.82-2.96-.2-.34A8.26 8.26 0 0 1 3.7 12c0-4.57 3.72-8.3 8.34-8.3 2.22 0 4.3.86 5.87 2.43a8.2 8.2 0 0 1 2.43 5.87c0 4.57-3.72 8.3-8.3 8.3zm4.48-6.25c-.24-.12-1.42-.7-1.64-.78-.22-.08-.38-.12-.55.12-.16.24-.63.78-.78.94-.14.16-.29.18-.53.06-.24-.12-1.01-.37-1.92-1.15-.71-.63-1.19-1.41-1.33-1.65-.14-.24-.02-.36.1-.48.1-.1.24-.24.36-.36.12-.12.16-.2.24-.33.08-.16.04-.3-.02-.42-.06-.12-.55-1.33-.76-1.82-.2-.49-.41-.42-.55-.42l-.47-.02c-.16 0-.42.06-.64.3-.22.24-.85.83-.85 2.03 0 1.2.87 2.36 1 2.52.12.16 1.72 2.63 4.3 3.69.6.26 1.06.41 1.42.52.6.19 1.16.16 1.6.1.49-.07 1.42-.58 1.62-1.14.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28z"/>
          </svg>
        </a>

        <!-- Instagram -->
        <a href="https://www.instagram.com/alihairwigs/?hl=en" class="text-white hover:text-pink-500 flex items-center justify-center p-2" aria-label="Instagram">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M7 2C4.2 2 2 4.2 2 7v10c0 2.8 2.2 5 5 5h10c2.8 0 5-2.2 5-5V7c0-2.8-2.2-5-5-5H7zm10 2c1.7 0 3 1.3 3 3v10c0 1.7-1.3 3-3 3H7c-1.7 0-3-1.3-3-3V7c0-1.7 1.3-3 3-3h10zm-5 3a5 5 0 1 0 .001 10.001A5 5 0 0 0 12 7zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm4.5-.8a1.2 1.2 0 1 0 0-2.4 1.2 1.2 0 0 0 0 2.4z"/>
          </svg>
        </a>

        <!-- Twitter/X -->
        <a href="https://x.com/alihairwig" class="text-white hover:text-cyan-400 flex items-center justify-center p-2" aria-label="Twitter/X">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 2H6a4 4 0 0 0-4 4v12a4 4 0 0 0 4 4h12a4 4 0 0 0 4-4V6a4 4 0 0 0-4-4zm-2.1 6.7l-2.6 3 3 4.3h-2.3l-1.9-2.8-2.1 2.8h-2.3l3.2-4.3-2.7-3h2.3l1.8 2.4 1.9-2.4h2.7z"/>
          </svg>
        </a>

        <!-- LinkedIn -->
        <a href="https://www.linkedin.com/in/ali-hair-wigs-410b69389/" class="text-white hover:text-blue-400 flex items-center justify-center p-2" aria-label="LinkedIn">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M4.98 3.5C4.98 4.88 3.86 6 2.5 6S0 4.88 0 3.5 1.12 1 2.5 1s2.48 1.12 2.48 2.5zM.5 8h4V23h-4V8zm7.5 0h3.8v2h.1c.5-1 1.8-2.1 3.7-2.1 4 0 4.7 2.6 4.7 6V23h-4v-7.3c0-1.7 0-3.9-2.4-3.9-2.4 0-2.8 1.8-2.8 3.7V23h-4V8z"/>
          </svg>
        </a>

        <!-- YouTube -->
        <a href="https://www.youtube.com/@ALIHAIRWIGS" class="text-white hover:text-red-500 flex items-center justify-center p-2" aria-label="YouTube">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
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



<!-- CART DRAWER MARKUP -->
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
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    <div id="cart-items" class="p-4 overflow-auto" style="flex:1 1 auto;">
        <div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>
    </div>

    <div class="cart-footer cart-footer-sticky">
        <div class="flex items-center justify-between mb-1">
            <div class="text-sm text-gray-600">Subtotal</div>
            <div id="cart-subtotal" class="text-lg font-bold text-[var(--primary)]"><?php echo CURRENCY_SYMBOL . ' 0.00'; ?></div>
        </div>
        <div class="checkout-cta">
            <a href="checkout.php" id="checkout-btn" class="checkout-btn">Checkout</a>
            <button id="continue-shopping" class="secondary-btn">Continue</button>
        </div>
        <div class="text-xs text-gray-500 mt-1">Shipping calculated at checkout</div>
    </div>
</aside>

<!-- SCRIPTS -->
 <script>
  (function(){
    const slidesEl = document.getElementById('slides');
    if (!slidesEl) return;
    // ensure track starts centered on first slide
    slidesEl.style.transform = 'translateX(0%)';
  })();
</script>

 <script>

(function(){
  const slidesEl = document.getElementById('slides');
  if (!slidesEl) return;
  const slides = Array.from(slidesEl.children);
  const prevBtn = document.getElementById('hero-prev');
  const nextBtn = document.getElementById('hero-next');
  const dotsContainer = document.getElementById('hero-dots');
  const stage = document.getElementById('hero-stage');
  if (!slides.length) return;

  let current = 0;
  let timer = null;
  const autoplayInterval = 5000;
  let isAnimating = false;

  function buildDots(){
    dotsContainer.innerHTML = '';
    slides.forEach((s,i)=>{
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.setAttribute('aria-label','Go to slide '+(i+1));
      btn.dataset.index = i;
      if(i===0) btn.classList.add('active');
      btn.addEventListener('click', ()=> goTo(i, true));
      dotsContainer.appendChild(btn);
    });
  }

  function updateDots(){
    Array.from(dotsContainer.children).forEach((b,i)=>{
      b.classList.toggle('active', i===current);
    });
  }

  function setTranslateX(percent){ slidesEl.style.transform = 'translateX(' + percent + '%)'; }

  function goTo(index, userTriggered=false){
    if (isAnimating) return;
    index = Math.max(0, Math.min(index, slides.length-1));
    if (index === current && !userTriggered) return;
    isAnimating = true;
    setTranslateX(-100 * index);
    current = index;
    updateDots();
    setTimeout(()=>{ isAnimating = false; }, 600);
    if (userTriggered) restartAutoplay();
  }

  function next(){ goTo((current+1) % slides.length, true); }
  function prev(){ goTo((current-1+slides.length) % slides.length, true); }

  function startAutoplay(){ stopAutoplay(); timer = setInterval(()=> goTo((current+1)%slides.length, false), autoplayInterval); }
  function stopAutoplay(){ if (timer) { clearInterval(timer); timer = null; } }
  function restartAutoplay(){ stopAutoplay(); startAutoplay(); }

  function onKey(e){ if (e.key === 'ArrowLeft') prev(); if (e.key === 'ArrowRight') next(); }

  // touch support
  let touchStartX = 0, touchDeltaX = 0;
  function onTouchStart(e){ stopAutoplay(); touchStartX = e.touches ? e.touches[0].clientX : e.clientX; touchDeltaX = 0; slidesEl.style.transition = 'none'; }
  function onTouchMove(e){ if (!touchStartX) return; const x = e.touches ? e.touches[0].clientX : e.clientX; touchDeltaX = x - touchStartX; const percent = (-100 * current) + (touchDeltaX / stage.clientWidth) * 100; setTranslateX(percent); }
  function onTouchEnd(){ slidesEl.style.transition = ''; if (Math.abs(touchDeltaX) > (stage.clientWidth * 0.15)) { if (touchDeltaX < 0) next(); else prev(); } else { goTo(current, false); } touchStartX = 0; touchDeltaX = 0; restartAutoplay(); }

  // init
  buildDots();
  updateDots();
  setTranslateX(0);

  if (nextBtn) nextBtn.addEventListener('click', next);
  if (prevBtn) prevBtn.addEventListener('click', prev);
  document.addEventListener('keydown', onKey);

  stage.addEventListener('touchstart', onTouchStart, {passive:true});
  stage.addEventListener('touchmove', onTouchMove, {passive:true});
  stage.addEventListener('touchend', onTouchEnd);

  // mouse drag for desktop
  let mouseDown = false;
  stage.addEventListener('mousedown', (e)=>{ mouseDown = true; onTouchStart(e); });
  window.addEventListener('mousemove', (e)=>{ if (!mouseDown) return; onTouchMove(e); });
  window.addEventListener('mouseup', (e)=>{ if (!mouseDown) return; mouseDown = false; onTouchEnd(e); });

  stage.addEventListener('mouseenter', stopAutoplay);
  stage.addEventListener('mouseleave', restartAutoplay);

  startAutoplay();
})();
</script>



<script>
/* Mobile left drawer + Search panel + Slide-out cart (localStorage-backed) */
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
  document.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') { closeSearch(); closeLeft(); } });

  if (searchPanel) {
    searchPanel.addEventListener('click', (e)=> {
      if (e.target === searchPanel) closeSearch();
    });
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
  const cartCountMini = document.getElementById('cart-count-mini');

  function loadCart(){ try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e) { return {}; } }
  let cart = loadCart();

  function saveCart(){ try { localStorage.setItem(STORAGE_KEY, JSON.stringify(cart)); } catch(e){} renderCart(); }
  function getTotalItems(){ return Object.values(cart).reduce((s,it)=> s + (it.qty||0), 0); }
  function getSubtotal(){ return Object.values(cart).reduce((s,it)=> s + ((it.price||0) * (it.qty||0)), 0); }
  function formatCurrency(v){ return '<?php echo CURRENCY_SYMBOL; ?> ' + Number(v||0).toFixed(2); }

  function renderCart(){
    const entries = Object.entries(cart || {});
    if (cartCountEl) cartCountEl.textContent = getTotalItems();
    if (cartCountMini) cartCountMini.textContent = getTotalItems();

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
  if (cartClearBtn) cartClearBtn.addEventListener('click', ()=>{
    if (confirm('Clear all items from your cart?')) { cart = {}; saveCart(); }
  });

  renderCart();
  window.addEventListener('storage', ()=> { cart = loadCart(); renderCart(); });
})();
</script>


<?php if ($conn) $conn->close(); ?>
</body>
</html>
