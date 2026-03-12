<?php
// ali-hair-map-fixed-location.php
declare(strict_types=1);
session_start();

// -------------------------
// Configuration (adjusted for requested fixed location)
// -------------------------
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

define('CURRENCY_SYMBOL', '$');
define('STORAGE_KEY', 'ali_hair_cart_v1');

// Map / page text
$apiKey = ''; // leave empty to use iframe mode
$addressRaw = 'Holdin no: 343/A,Sarker Bari,Uttar Khan,(Helal Market)., Dhaka, Bangladesh';
$placeTitle = 'Ali Hair Wigs';
$zoom = 14; // iframe zoom

// Fixed coordinates from the URL you provided (shop location)
$lat = 23.8801902;
$lng = 90.427837;

// page helpers
function h(string $s = ''): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$pageTitle = $placeTitle . ' — Location map';

// Optional DB connection (keeps behavior identical to your previous files)
$conn = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$connection_error = null;
if ($conn && $conn->connect_error) {
    $connection_error = "Database Connection Failed: " . $conn->connect_error;
    error_log($connection_error);
    $conn = null;
}
if ($conn) $conn->set_charset('utf8mb4');
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
     <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/favicon.png">
  <title><?php echo h($pageTitle); ?></title>


  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700;800&display=swap" rel="stylesheet">

  <style>
    :root { --primary: #7b3f00; --secondary: #f7e0c4; --accent-pin: #ef4444; --muted: #6b7280; }
    html,body{height:100%;margin:0;font-family:Inter,system-ui,-apple-system,'Segoe UI',Roboto,Arial;background:#fcfcfc;color:#0f172a;-webkit-font-smoothing:antialiased}
    a { color: inherit; }

    header.site-header{background:#fff;box-shadow:0 1px 0 rgba(0,0,0,0.04);position:sticky;top:0;z-index:120}
    .header-inner{max-width:1200px;margin:0 auto;padding:10px 16px;display:flex;align-items:center;justify-content:space-between;gap:12px}
    .logo{display:flex;align-items:center;gap:10px;text-decoration:none;font-weight:800;color:var(--primary)}
    .logo img{height:40px;width:40px;object-fit:contain;flex:0 0 40px}
    .logo-text{display:inline-flex;flex-direction:column;line-height:1}
    .logo-main{color:var(--primary);font-weight:800;font-size:1rem}
    .logo-sub{color:#374151;font-weight:800;font-size:.95rem}
    .nav-wrap{flex:1;display:flex;justify-content:center}
    /* Desktop nav: allow wrap into two rows instead of breaking items */
    .nav-desktop{display:none;gap:12px;align-items:center;justify-content:center;flex-wrap:wrap}
    .nav-desktop a{color:#4b5563;text-decoration:none;font-weight:600;padding:8px 10px;border-radius:8px;white-space:nowrap}
    .nav-desktop a:hover{background:rgba(123,63,0,0.04);color:var(--primary)}

    .icon-btn{display:inline-flex;align-items:center;justify-content:center;width:44px;height:44px;border-radius:10px;background:transparent;border:1px solid transparent;cursor:pointer}
    .icon-btn:hover{background:#f8fafc}
    .search-toggle { display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px; border-radius:10px; background:transparent; border:1px solid transparent; cursor:pointer; }
    .search-toggle:hover { background:#f8fafc; }

    .wrap{max-width:1100px;margin:18px auto;padding:16px}
    h1{margin:0 0 10px;font-size:20px}
    .controls{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px}
    .btn{padding:8px 10px;border-radius:8px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;font-weight:700}
    .map-shell{position:relative;border-radius:8px;border:1px solid #e6edf3;overflow:hidden;background:#fff}
    #map, #js-map, #map-iframe{width:100%;height:72vh;display:block}

    /* Pin overlay for iframe mode — centered and labelled like a "shop plaque" */
    .pin-overlay{position:absolute;left:50%;top:50%;transform:translate(-50%,-100%);pointer-events:none;z-index:40;width:54px;height:72px;display:block}
    .pin-overlay svg{width:100%;height:100%}
    .pin-label { position:absolute; left:50%; top:calc(50% + 10px); transform:translate(-50%,0); pointer-events:none; z-index:45; background:rgba(255,255,255,0.95); padding:6px 10px; border-radius:8px; font-weight:700; font-size:13px; color:#111827; box-shadow:0 6px 20px rgba(2,6,23,0.12); white-space:nowrap; border:1px solid #eef2f6; }

    .note{margin-top:12px;color:#475569;font-size:14px}
    .small{font-size:13px;color:var(--muted)}



    .cart-drawer { position:fixed; right:0; top:0; height:100vh; width:90vw; max-width:420px; background:#fff; box-shadow:-20px 0 40px rgba(0,0,0,.12); transform:translateX(110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:60; display:flex; flex-direction:column; border-top-left-radius:14px; border-bottom-left-radius:14px; overflow:hidden; }
    .cart-drawer.open { transform:translateX(0); }
    .cart-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); opacity:0; pointer-events:none; transition:opacity .25s; z-index:50; }
    .cart-backdrop.visible { opacity:1; pointer-events:auto; }

    .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }

    .mobile-left-drawer { position:fixed; left:0; top:0; height:100vh; width:84vw; max-width:320px; background:#fff; box-shadow:20px 0 40px rgba(0,0,0,.12); transform:translateX(-110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:70; padding:1rem; overflow:auto; }
    .mobile-left-drawer.open { transform:translateX(0); }

    .share-btn { padding:8px;border-radius:10px;background:transparent;border:1px solid transparent;cursor:pointer;display:inline-flex;align-items:center;justify-content:center }
    .share-btn:hover { background:#f8fafc; }

    /* ---- Cart item layout ---- */
    .cart-item { display:flex; gap:12px; align-items:flex-start; padding:12px; border-bottom:1px solid #f1f5f9; }
    .cart-item img { width:72px; height:72px; object-fit:cover; border-radius:8px; }
    .cart-meta { flex:1; min-width:0; display:flex; flex-direction:column; gap:6px; }
    .cart-name { font-weight:700; color:#111827; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cart-sub { color:var(--muted); font-size:13px; }

    /* ---- Cart footer buttons styled to match your screenshot ---- */
    .cart-footer { background:linear-gradient(180deg, #fff 0%, #fbfbfb 100%); border-top: 1px solid #eef2f6; padding:14px; display:flex; flex-direction:column; gap:10px; }
    .checkout-row { display:flex; gap:10px; align-items:center; }
    /* Prominent brown checkout button — matches screenshot: bold, saturated, medium height */
    .checkout-btn {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      background: linear-gradient(180deg, #7b3f00 0%, #5f2b00 100%);
      color: #fff;
      padding:12px 16px;
      border-radius:10px;
      font-weight:800;
      text-decoration:none;
      box-shadow: 0 10px 28px rgba(123,63,0,0.18);
      transition:transform .08s ease, box-shadow .12s ease;
      border: 1px solid rgba(0,0,0,0.06);
      min-width:140px;
    }
    .checkout-btn:active { transform:translateY(1px); }
    .checkout-btn:focus { outline: 3px solid rgba(123,63,0,0.15); outline-offset: 2px; }

    /* White/neutral secondary continue button like screenshot */
    .secondary-btn {
      display:inline-flex;
      align-items:center;
      justify-content:center;
      gap:8px;
      background: #fff;
      color: #374151;
      padding:12px 16px;
      border-radius:10px;
      font-weight:700;
      border: 1px solid #e6e6e6;
      min-width:120px;
    }

    /* On narrow widths stack buttons full width with spacing similar to screenshot */
    @media (max-width:480px) {
      .checkout-row { flex-direction:column-reverse; }
      .checkout-btn, .secondary-btn { width:100%; min-width:0; }
    }
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
 @media(min-width:768px){ .nav-desktop{display:flex} }
        .search-toggle { display:inline-flex; align-items:center; justify-content:center; width:44px; height:44px; border-radius:10px; background:transparent; border:1px solid transparent; cursor:pointer; }
        .search-toggle:hover { background:#f8fafc; }
        .search-panel { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1200; }
        .search-panel.open { display:flex; backdrop-filter: blur(2px); }
        .search-box { background:#fff; padding:10px; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); display:flex; gap:8px; width:min(720px,96%); align-items:center; }
        .search-input-wide { border:1px solid #e6eef6; padding:10px 12px; border-radius:8px; width:100%; font-size:16px; }
        .search-close { background:transparent; border:0; cursor:pointer; font-size:18px; color:#374151; }
  </style>
</head>
<body class="min-h-screen">

<!-- HEADER -->
<header class="site-header" role="banner">
  <div class="header-inner">
    <div style="display:flex;align-items:center;gap:12px">
      <button id="hamburger" aria-expanded="false" aria-controls="mobile-left-drawer" class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-100" type="button">
        <svg id="hamburger-open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        <svg id="hamburger-close" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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

      <button id="share-btn" class="share-btn" aria-label="Share location" title="Share location">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 12v7a1 1 0 001 1h14a1 1 0 001-1v-7"/><path d="M16 6l-4-4-4 4"/><path d="M12 2v13"/></svg>
        <span class="sr-only">Share</span>
      </button>

      <div class="relative ml-2">
        <button id="cart-toggle" aria-label="Cart" class="p-2 rounded-md text-gray-700 hover:bg-gray-100 relative" type="button">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h13M7 13H5.4M16 16a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/></svg>
          <span id="cart-count" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">0</span>
        </button>
      </div>
    </div>
  </div>
</header>

<!-- Mobile left drawer -->
<div id="mobile-left-drawer" class="mobile-left-drawer" aria-hidden="true" role="navigation">
  <div class="drawer-inner">
    <div style="display:flex;align-items:center;justify-between;margin-bottom:1rem">
      <div style="display:flex;gap:0.75rem;align-items:center">
        <img src="uploads/ahw.png" alt="Logo" style="height:32px;width:32px;object-fit:contain" onerror="this.onerror=null;this.src='https://placehold.co/80x80/7b3f00/f7e0c4?text=Logo'">
        <div style="font-weight:700;color:var(--primary)">ALI HAIR WIGS</div>
      </div>
      <button id="mobile-left-close" class="mobile-left-close" aria-label="Close menu">✕</button>
    </div>

    <div class="drawer-scroll" style="overflow:auto;flex:1 1 auto;padding-bottom:60px;">
        <a href="index.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Home</a>
            <a href="mens_wigs.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Men's Wigs</a>
            <a href="womens_wigs.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Women's Wigs</a>
            <a href="about.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">About</a>
            <a href="map.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Stoe Location</a>
            <a href="contact.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Contact</a>
            <a href="login.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Account</a>

    </div>

    <div style="border-top:1px solid #f1f5f9;margin-top:12px;padding-top:12px">
      
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
  <div class="wrap">
    <h1 class="text-2xl font-extrabold mb-2"><?php echo h($pageTitle); ?></h1>
    <p class="small">Address: <strong><?php echo h($addressRaw); ?></strong></p>

    <div class="controls" role="toolbar" aria-label="Map controls">
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <label for="addr" class="small">Embed centered at coordinates:</label>
        <input id="addr" type="text" value="<?php echo h($lat . ',' . $lng); ?>" />
        <button id="btn-refresh-iframe" class="btn">Refresh iframe</button>
      </div>
      <div style="align-self:center;margin-left:8px" class="small">Fixed shop location set from provided Google Maps URL. The red pin and label are fixed to this location.</div>
    </div>

    <div class="map-shell iframe-mode">
      <div id="map" role="region" aria-label="Map area">
        <?php
          // Build iframe using coordinates to guarantee consistent centering and marker
          $coordQuery = rawurlencode($lat . ',' . $lng);
          $iframeSrc = "https://www.google.com/maps?q={$coordQuery}&z=" . ((int)$zoom) . "&output=embed";
        ?>
        <iframe id="map-iframe" src="<?php echo h($iframeSrc); ?>" width="100%" height="100%" frameborder="0" style="border:0;min-height:72vh" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>

        <!-- Fixed red pin overlay and visible label "Ali Hair Wigs" -->
        <div class="pin-overlay" aria-hidden="true" title="<?php echo h($placeTitle); ?>">
          <svg viewBox="0 0 24 36" xmlns="http://www.w3.org/2000/svg" role="img" aria-hidden="true">
            <path d="M12 0C7.029 0 3 4.03 3 9.02c0 6.86 8.11 16.04 8.57 16.55.15.15.36.23.56.23.21 0 .42-.08.57-.24C12.89 25.06 21 15.88 21 9.02 21 4.03 16.971 0 12 0z" fill="#ef4444"/>
            <circle cx="12" cy="9" r="3.5" fill="#fff"/>
            <circle cx="12" cy="9" r="2.2" fill="#ef4444"/>
          </svg>
        </div>

        <div class="pin-label" aria-hidden="true">Ali Hair Wigs</div>
      </div>
    </div>

    <div class="note">
      <strong>Notes</strong>
      <ul>
        <li class="small">This page centers the embedded Google map at the exact coordinates you supplied (<?php echo h($lat . ', ' . $lng); ?>) so the visual pin overlay and label represent the shop location precisely.</li>
        <li class="small">Share uses the Web Share API when available and falls back to copying a Google Maps link (coordinates) to the clipboard.</li>
        <li class="small">If you later add a Google Maps JavaScript API key to <code>$apiKey</code> we can switch to an interactive map with the same fixed coordinates and an SVG marker.</li>
      </ul>
    </div>
  </div>
</main>

<!-- CART DRAWER -->
<div id="cart-backdrop" class="cart-backdrop" aria-hidden="true"></div>
<aside id="cart-drawer" class="cart-drawer" aria-hidden="true" role="dialog" aria-label="Shopping cart">
    <div class="p-4 border-b border-gray-200 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="text-lg font-semibold">Your Cart</div>
            <div id="cart-mini-count" class="text-sm text-gray-500">(<span id="cart-count-mini">0</span> items)</div>
        </div>
        <div class="flex items-center gap-2">
            <button id="cart-clear" class="text-sm text-gray-600 hover:underline">Clear</button>
            <button id="cart-close" aria-label="Close cart" class="p-2 rounded-md hover:bg-gray-100">✕</button>
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
        <div class="checkout-row">
            <a href="checkout.php" id="checkout-btn" class="checkout-btn">Checkout</a>
            <button id="continue-shopping" class="secondary-btn">Continue</button>
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

<!-- SCRIPTS -->
<script>
/* Header interactions and mobile drawer */
(function(){
 

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
        if (document.activeElement === first) { e.preventDefault(); last.focus(); }
      } else {
        if (document.activeElement === last) { e.preventDefault(); first.focus(); }
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

  function openLeft(){ if (!mobileLeft) return; mobileLeft.classList.add('open'); mobileLeft.setAttribute('aria-hidden','false'); if(openIcon) openIcon.classList.add('hidden'); if(closeIcon) closeIcon.classList.remove('hidden'); if(hamburger) hamburger.setAttribute('aria-expanded','true'); setTimeout(()=> { const focusable = mobileLeft.querySelector('a,button'); if (focusable) focusable.focus(); trapFocus(mobileLeft); }, 80); document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden'; }
  function closeLeft(){ if (!mobileLeft) return; mobileLeft.classList.remove('open'); mobileLeft.setAttribute('aria-hidden','true'); if(openIcon) openIcon.classList.remove('hidden'); if(closeIcon) closeIcon.classList.add('hidden'); if(hamburger) hamburger.setAttribute('aria-expanded','false'); releaseFocus(mobileLeft); if (hamburger) hamburger.focus(); document.documentElement.style.overflow = ''; document.body.style.overflow = ''; }

  if (hamburger) hamburger.addEventListener('click', () => { if (mobileLeft && mobileLeft.classList.contains('open')) closeLeft(); else openLeft(); });
  if (mobileLeftClose) mobileLeftClose.addEventListener('click', closeLeft);
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') { if (mobileLeft && mobileLeft.classList.contains('open')) closeLeft(); } });
})();
</script>

<script>
/* Cart drawer and localStorage-backed cart (identical logic) */
(function(){
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
      row.className = 'cart-item';

      const link = document.createElement('a');
      link.href = `product_details.php?id=${encodeURIComponent(id)}`;
      const img = document.createElement('img');
      img.src = it.img || 'https://placehold.co/80x80/7b3f00/f7e0c4?text=Img';
      img.alt = (it.name || 'Product');
      link.appendChild(img);

      const meta = document.createElement('div'); meta.className = 'cart-meta';
      const nameEl = document.createElement('div'); nameEl.className = 'cart-name'; nameEl.textContent = it.name || 'Product';
      const subEl = document.createElement('div'); subEl.className = 'cart-sub'; subEl.textContent = `${formatCurrency(it.price)} each`;
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
})();
</script>

<script>
/* iframe mode: refresh iframe, share location using coordinates */
(function(){
  const input = document.getElementById('addr');
  const btn = document.getElementById('btn-refresh-iframe');
  const iframe = document.getElementById('map-iframe');
  const shareBtn = document.getElementById('share-btn');

  // server-provided coordinates
  const lat = <?php echo json_encode((float)$lat); ?>;
  const lng = <?php echo json_encode((float)$lng); ?>;
  const address = <?php echo json_encode($addressRaw); ?>;
  const title = <?php echo json_encode($placeTitle); ?>;

  if (btn && input && iframe) {
    btn.addEventListener('click', function(){
      const v = (input.value || '').trim();
      let src;
      if (v && v.indexOf(',') === -1) {
        // treat as address text
        src = 'https://www.google.com/maps?q=' + encodeURIComponent(v + ' ' + title) + '&output=embed';
      } else if (v && v.indexOf(',') !== -1) {
        // treat as lat,lng
        src = 'https://www.google.com/maps?q=' + encodeURIComponent(v) + '&z=<?php echo (int)$zoom; ?>&output=embed';
      } else {
        src = iframe.getAttribute('data-original-src') || iframe.src;
      }
      iframe.src = src;
    });
    if (!iframe.getAttribute('data-original-src')) iframe.setAttribute('data-original-src', iframe.src);
  }

  // Share button implementation (Web Share API -> Clipboard fallback)
  shareBtn?.addEventListener('click', async function(){
    let mapUrl;
    if (lat && lng) {
      mapUrl = `https://www.google.com/maps?q=${encodeURIComponent(lat + ',' + lng)}`;
    } else {
      mapUrl = `https://www.google.com/maps?q=${encodeURIComponent(address + ' ' + title)}`;
    }

    if (navigator.share) {
      try {
        await navigator.share({ title: title, text: title + ' — ' + address, url: mapUrl });
        return;
      } catch (err) {
        // fallback
      }
    }

    try {
      await navigator.clipboard.writeText(mapUrl);
      alert('Location link copied to clipboard:\n' + mapUrl);
    } catch (err) {
      window.prompt('Copy this location URL', mapUrl);
    }
  });
})();
</script>


</body>
</html>
