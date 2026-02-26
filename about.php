<?php
declare(strict_types=1);
session_start();

/**
 * about.php
 * PBARIA LIMITED / Ali Hair Wigs
 *
 * Change: certificate photos displayed as a responsive collage (grid/mosaic).
 * - Each cert can include image, image2, image3, image4 (optional).
 * - Missing files fall back gracefully to the primary image or a placeholder.
 * - Clicking any thumbnail opens the same lightbox.
 */

define('STORAGE_KEY', 'ali_hair_cart_v1');
define('CURRENCY_SYMBOL', '$');

function h(string $s = ''): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
$year = date('Y');

// Certificates dataset (add more image keys for collage)
$certs = [
    'iso' => [
        'title' => 'CERTIFICATE (ISO 9001:2015)',
        'issuer' => 'RoyalCert International Registrars GmbH',
        'company' => 'PBARIA LIMITED',
        'scope' => 'Import, Export, Manufacturing of Hair Systems and Wigs; E-Commerce; Trading',
        'certificate_no' => '23.100.01035',
        'initial_certification' => '13.03.2023',
        'issue_date' => '13.03.2023',
        'expiry_date' => '12.03.2026',
        'signatory' => 'Chris Markopoulos',
        'image' => 'uploads/ali.jpg',
        'image2' => 'uploads/3.jpg',
        'image3' => 'uploads/ali-2.jpg',
        'image4' => 'uploads/ali-3.jpg'
    ],
    'dcci' => [
        'title' => 'MEMBERSHIP CERTIFICATE (Dhaka Chamber of Commerce & Industry)',
        'issuer' => 'Dhaka Chamber of Commerce & Industry',
        'company' => 'PBARIA LIMITED',
        'membership_no' => '10313',
        'date' => '01 January 2023',
        'signatory' => 'Md. Jashim Uddin, President',
        'image' => 'uploads/ali.jpg',
        'image2' => 'uploads/3.jpg'
    ],
    'duns' => [
        'title' => 'D‑U‑N‑S® Registered™ Certificate',
        'issuer' => 'Dun & Bradstreet',
        'company' => 'PBARIA LIMITED',
        'duns_number' => '72-777-8480',
        'address' => '49/1 North Soconomy, Narayanganj, Uttarkhan, Dhaka-1230, Bangladesh',
        'website' => 'www.pbarialtd.com',
        'image' => 'uploads/ali.jpg',
        'image2' => 'uploads/3.jpg'
    ]
];

$selectedCertKey = 'iso';
if (!isset($certs[$selectedCertKey])) $selectedCertKey = array_key_first($certs);
$cert = $certs[$selectedCertKey] ?? [];

function cert_img_src(string $path, string $placeholder = ''): string {
    if ($path && file_exists(__DIR__ . '/' . $path)) return $path;
    if ($placeholder !== '') return $placeholder;
    return 'https://placehold.co/800x1000/ffffff/7b3f00?text=Certificate';
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
     <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/favicon.png">
  <title>About — PBARIA LIMITED / Ali Hair Wigs</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700;800&display=swap" rel="stylesheet">

  <style>
    :root { --primary: #7b3f00; --secondary: #f7e0c4; --muted: #6b7280; }
    html,body { height:100%; }
    body { font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, Arial; background:#fcfcfc; color:#1f2937; margin:0; -webkit-font-smoothing:antialiased; }

    header.site-header { background:#fff; box-shadow:0 1px 0 rgba(0,0,0,.04); position:sticky; top:0; z-index:70; }
    .header-inner { max-width:1200px; margin:0 auto; padding:10px 16px; display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .logo { display:flex; align-items:center; gap:10px; text-decoration:none; font-weight:800; color:var(--primary); }
    .logo img { height:40px; width:40px; object-fit:contain; }
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

    .page-wrap { max-width:1100px; margin:28px auto; padding:0 16px; }

    /* Collage grid for certificate photos */
    .cert-card { display:flex; gap:20px; align-items:flex-start; background:#fff; border:1px solid #eef2f6; border-radius:12px; padding:18px; box-shadow:0 10px 30px rgba(12,18,24,0.04); }
    .cert-collage { width:360px; display:grid; grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr; gap:8px; align-items:stretch; }
    .collage-item { width:100%; height:100%; overflow:hidden; border-radius:8px; background:#f8fafc; cursor:zoom-in; display:block; object-fit:cover; }
    .collage-item.big { grid-column:1 / 2; grid-row: 1 / 3; height:100%; }
    .collage-item.small { width:100%; height:100%; object-fit:cover; }

    @media(max-width:900px) {
      .cert-card { flex-direction:column; align-items:center; }
      .cert-collage { width:86%; grid-template-columns: 1fr 1fr; grid-template-rows: auto auto; }
      .collage-item.big { grid-column:1 / 3; grid-row: 1 / 2; height:auto; }
    }

    .cert-body { flex:1; display:flex; flex-direction:column; gap:8px; min-width:0; }
    .cert-title { font-weight:800; color:var(--primary); font-size:1.1rem; }
    .cert-meta { color:#374151; font-size:0.95rem; }
    .cert-note { color:var(--muted); font-size:0.95rem; }

    .search-panel { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1200; }
    .search-panel.open { display:flex; backdrop-filter: blur(3px); }
    .search-box { background:#fff; padding:10px; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); display:flex; gap:8px; width:min(720px,96%); align-items:center; }
    .search-input-wide { border:1px solid #e6eef6; padding:10px 12px; border-radius:8px; width:100%; font-size:16px; }

    .lightbox-modal { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1400; background:rgba(0,0,0,.75); padding:20px; }
    .lightbox-modal[aria-hidden="false"] { display:flex; }
    .lightbox-body img { max-width:100%; max-height:95vh; border-radius:6px; box-shadow:0 18px 40px rgba(2,6,23,0.6); }

    .cart-drawer { position:fixed; right:0; top:0; height:100vh; width:94vw; max-width:420px; background:#fff; box-shadow:-20px 0 40px rgba(0,0,0,.12); transform:translateX(110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:60; display:flex; flex-direction:column; border-top-left-radius:14px; border-bottom-left-radius:14px; overflow:hidden; }
    .cart-drawer.open { transform:translateX(0); }
    .cart-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); opacity:0; pointer-events:none; transition:opacity .25s; z-index:50; }
    .cart-backdrop.visible { opacity:1; pointer-events:auto; }

    .cart-row { display:flex; gap:12px; align-items:flex-start; padding:10px; border-radius:10px; background:#fff; border:1px solid #eef2f6; margin-bottom:10px; }
    .cart-item-img { width:72px; height:72px; object-fit:cover; border-radius:8px; }
    .cart-meta { flex:1; min-width:0; display:flex; flex-direction:column; gap:4px; }
    .cart-name { font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cart-sub { color:var(--muted); font-size:0.85rem; }
    .cart-price { font-weight:800; color:var(--primary); }

    .checkout-btn { background:var(--primary); color:#fff; padding:12px 14px; border-radius:10px; font-weight:800; width:100%; text-align:center; display:inline-block; box-shadow:0 8px 22px rgba(123,63,0,0.18); }
    .secondary-btn { border:1px solid #e6e6e6; background:#fff; padding:12px 14px; border-radius:10px; font-weight:700; color:#374151; width:100%; }

    .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
    :focus { outline: 3px solid rgba(59,130,246,0.25); outline-offset: 3px; }
  </style>
</head>
<body>

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

<!-- Mobile left drawer -->
<aside id="mobile-left-drawer" aria-hidden="true" class="fixed inset-y-0 left-0 w-4/5 max-w-xs bg-white shadow-lg transform -translate-x-full transition-transform z-60" style="display:none" role="navigation">
  <div class="p-4 h-full flex flex-col">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-3">
        <img src="uploads/ahw.png" alt="Logo" class="h-8 w-8 object-contain" onerror="this.onerror=null;this.src='https://placehold.co/80x80/7b3f00/f7e0c4?text=Logo'">
        <div class="font-bold text-lg text-[var(--primary)]">ALI HAIR WIGS</div>
      </div>
      <button id="mobile-left-close" aria-label="Close menu" class="p-2 rounded-md text-gray-700 hover:bg-gray-100">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
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

    <div class="mt-4 border-t pt-3 text-sm text-gray-600">
     
    </div>
  </div>
</aside>

<main class="page-wrap">
  <section class="intro mb-6">
    <h1 class="text-2xl font-extrabold mb-2">About ALI HAIR WIGS</h1>
    <p class="cert-note">ALI HAIR WIGS is committed to manufacturing and distributing premium hair systems and wigs with strong attention to quality, compliance and global trade. Below is a selected credential that demonstrates our compliance and membership.</p>
  </section>

  <section aria-labelledby="certificate" class="mb-6">
    <h2 id="certificate" class="text-lg font-bold mb-3">Our Certificate</h2>

    <?php if (empty($cert)): ?>
      <div class="cert-card">
        <div class="cert-body">
          <div class="cert-title">No certificate configured</div>
          <div class="cert-meta">Edit about.php and set $selectedCertKey to one of 'iso', 'dcci', or 'duns'.</div>
        </div>
      </div>
    <?php else:
      // collect up to 4 images for collage, falling back sensibly
      $images = [];
      $primary = $cert['image'] ?? '';
      for ($i = 1; $i <= 4; $i++) {
          $key = $i === 1 ? 'image' : 'image' . $i;
          $val = $cert[$key] ?? null;
          if (!$val && $i > 1) $val = $primary;
          $images[] = cert_img_src((string)$val, 'https://placehold.co/800x1000/ffffff/7b3f00?text=Certificate');
      }
    ?>
      <article class="cert-card" aria-label="<?php echo h($cert['title'] ?? 'Certificate'); ?>">
        <div class="cert-collage" role="group" aria-label="Certificate photos">
          <!-- big panel: image1 -->
          <img class="collage-item big lightbox" src="<?php echo h($images[0]); ?>" data-full="<?php echo h($images[0]); ?>" alt="<?php echo h(($cert['title'] ?? '') . ' — ' . ($cert['company'] ?? '')); ?>" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/800x1000/ffffff/7b3f00?text=Certificate'">
          <!-- small panels -->
          <img class="collage-item small lightbox" src="<?php echo h($images[1]); ?>" data-full="<?php echo h($images[1]); ?>" alt="<?php echo h(($cert['title'] ?? '') . ' — alternate 1'); ?>" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/400x600/ffffff/7b3f00?text=Certificate'">
          <img class="collage-item small lightbox" src="<?php echo h($images[2]); ?>" data-full="<?php echo h($images[2]); ?>" alt="<?php echo h(($cert['title'] ?? '') . ' — alternate 2'); ?>" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/400x600/ffffff/7b3f00?text=Certificate'">
        </div>

        <div class="cert-body">
          <h2 id="certificate" class="text-lg font-bold mb-3">Our Certificate</h2>
          <p> Here is the nacessary documents of or company</p>
          <p> This things wil build your trust on and it confirmed that we are certified company</p>
          

          <?php if (!empty($cert['website'])):
            $link = (strpos($cert['website'], 'http') === 0) ? $cert['website'] : 'https://'.$cert['website'];
          ?>
            <div class="cert-meta mt-2"><strong>Website</strong>: <a href="<?php echo h($link); ?>" target="_blank" rel="noopener noreferrer" class="text-[var(--primary)]"><?php echo h($cert['website']); ?></a></div>
          <?php endif; ?>

          <?php if (!empty($cert['signatory']) || !empty($cert['issuer'])): ?>
            <div class="mt-auto">
             
          <?php endif; ?>
        </div>
      </article>
    <?php endif; ?>
  </section>

  <section class="mb-6">
    <h3 class="font-bold mb-2">Company Information</h3>
    <div class="text-gray-700">
      <div><strong>Company</strong>: ALI HAIR WIGS</div>
      <div><strong>Address</strong>:Holdin no: 343/A,Sarker Bari,Uttar Khan,(Helal Market)., Dhaka, Bangladesh</div>
      <div><strong>Email</strong>: alihairwigs.bd@gmail.com</div>
      <div><strong>Website</strong>: <a href="https://www.alihairwigs.com" target="_blank" rel="noopener noreferrer" class="text-[var(--primary)]">www.pbarialtd.com</a></div>
    </div>
  </section>

  <section class="mb-12">
    <a href="contact.php" class="inline-block bg-[var(--primary)] text-white px-4 py-2 rounded-lg font-bold">Contact Sales</a>
  </section>
</main>

<!-- Search overlay -->
<div id="search-panel" class="search-panel" aria-hidden="true">
  <div class="search-box" role="search" aria-label="Site search">
    <form id="search-form" action="search.php" method="get" class="flex gap-2 w-full">
      <label for="q-wide" class="sr-only">Search products</label>
      <input id="q-wide" name="q" type="search" class="search-input-wide" placeholder="Search products, styles..." aria-label="Search products" autocomplete="off">
    </form>
    <button id="search-close" class="search-close" aria-label="Close search" style="background:transparent;border:0;cursor:pointer;font-size:20px">&times;</button>
  </div>
</div>

<!-- Lightbox modal -->
<div id="lightbox-modal" class="lightbox-modal" aria-hidden="true" role="dialog" aria-modal="true">
  <button id="lightbox-close" class="lightbox-close" aria-label="Close image" style="background:transparent;border:0;color:#fff;font-size:28px">&times;</button>
  <div class="lightbox-body" role="document">
    <img id="lightbox-img" src="" alt="">
  </div>
</div>

<!-- Cart backdrop + drawer -->
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

  <div class="cart-footer" style="padding:14px;border-top:1px solid #eef2f6;">
    <div class="flex items-center justify-between mb-1">
      <div class="text-sm text-gray-600">Subtotal</div>
      <div id="cart-subtotal" class="text-lg font-bold text-[var(--primary)]"><?php echo CURRENCY_SYMBOL . ' 0.00'; ?></div>
    </div>
    <div style="display:flex;gap:10px;">
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
  // --- DOM refs
  const mobileDrawer = document.getElementById('mobile-left-drawer');
  const hamburger = document.getElementById('hamburger');
  const hambOpenIcon = document.getElementById('hamburger-open');
  const hambCloseIcon = document.getElementById('hamburger-close');
  const mobileLeftClose = document.getElementById('mobile-left-close');

  const searchToggle = document.getElementById('search-toggle');
  const searchPanel = document.getElementById('search-panel');
  const searchClose = document.getElementById('search-close');
  const searchInput = document.getElementById('q-wide');

  const lightboxModal = document.getElementById('lightbox-modal');
  const lightboxImg = document.getElementById('lightbox-img');
  const lightboxClose = document.getElementById('lightbox-close');

  const STORAGE_KEY = '<?php echo addslashes(STORAGE_KEY); ?>';
  const cartToggle = document.getElementById('cart-toggle');
  const cartDrawer = document.getElementById('cart-drawer');
  const cartBackdrop = document.getElementById('cart-backdrop');
  const cartItemsEl = document.getElementById('cart-items');
  const cartCountEl = document.getElementById('cart-count');
  const cartCountMini = document.getElementById('cart-count-mini');
  const cartSubtotalEl = document.getElementById('cart-subtotal');
  const cartClose = document.getElementById('cart-close');
  const cartClearBtn = document.getElementById('cart-clear');
  const continueBtn = document.getElementById('continue-shopping');

  // Mobile drawer
  function openMobileDrawer(){ if (!mobileDrawer) return; mobileDrawer.style.display = 'block'; requestAnimationFrame(()=> mobileDrawer.classList.add('translate-x-0','open')); mobileDrawer.classList.remove('-translate-x-full'); hamburger.setAttribute('aria-expanded','true'); if (hambOpenIcon) hambOpenIcon.classList.add('hidden'); if (hambCloseIcon) hambCloseIcon.classList.remove('hidden'); document.documentElement.style.overflow = 'hidden'; }
  function closeMobileDrawer(){ if (!mobileDrawer) return; mobileDrawer.classList.remove('open'); mobileDrawer.classList.add('-translate-x-full'); mobileDrawer.style.display = 'none'; hamburger.setAttribute('aria-expanded','false'); if (hambOpenIcon) hambOpenIcon.classList.remove('hidden'); if (hambCloseIcon) hambCloseIcon.classList.add('hidden'); document.documentElement.style.overflow = ''; }
  hamburger?.addEventListener('click', ()=> { if (mobileDrawer && mobileDrawer.classList.contains('open')) closeMobileDrawer(); else openMobileDrawer(); });
  mobileLeftClose?.addEventListener('click', closeMobileDrawer);

  // Escape closes overlays
  document.addEventListener('keydown', (e)=> { if (e.key === 'Escape') { closeMobileDrawer(); closeSearch(); closeLightbox(); closeCart(); } });

  // Search overlay
  function openSearch(){ if (!searchPanel) return; searchPanel.classList.add('open'); searchPanel.setAttribute('aria-hidden','false'); searchPanel.style.display = 'flex'; setTimeout(()=> searchInput?.focus(), 60); document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden'; }
  function closeSearch(){ if (!searchPanel) return; searchPanel.classList.remove('open'); searchPanel.setAttribute('aria-hidden','true'); searchPanel.style.display = 'none'; document.documentElement.style.overflow = ''; document.body.style.overflow = ''; searchToggle?.focus(); }
  searchToggle?.addEventListener('click', (e)=>{ e.preventDefault(); if (searchPanel && searchPanel.classList.contains('open')) closeSearch(); else openSearch(); });
  searchClose?.addEventListener('click', (e)=>{ e.preventDefault(); closeSearch(); });
  searchPanel?.addEventListener('click', (e)=> { if (e.target === searchPanel) closeSearch(); });

  // Lightbox
  function openLightbox(src, alt){ if(!lightboxModal || !lightboxImg) return; lightboxImg.src = src; lightboxImg.alt = alt || ''; lightboxModal.setAttribute('aria-hidden','false'); lightboxModal.style.display = 'flex'; lightboxClose?.focus(); document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden'; }
  function closeLightbox(){ if(!lightboxModal || !lightboxImg) return; lightboxModal.setAttribute('aria-hidden','true'); lightboxModal.style.display = 'none'; lightboxImg.src = ''; document.documentElement.style.overflow = ''; document.body.style.overflow = ''; }
  document.addEventListener('click', function(e){ const img = e.target.closest && e.target.closest('img.lightbox'); if (!img) return; e.preventDefault(); const src = img.getAttribute('data-full') || img.src; openLightbox(src, img.alt || ''); }, true);
  lightboxClose?.addEventListener('click', closeLightbox);
  lightboxModal?.addEventListener('click', function(e){ if (e.target === lightboxModal) closeLightbox(); });

  // Cart (localStorage)
  function loadCart(){ try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e) { return {}; } }
  let cart = loadCart();
  function saveCart(){ try { localStorage.setItem(STORAGE_KEY, JSON.stringify(cart)); } catch(e){} renderCart(); }
  function getTotalItems(){ return Object.values(cart).reduce((s,it)=> s + (it.qty||0), 0); }
  function getSubtotal(){ return Object.values(cart).reduce((s,it)=> s + ((it.price||0) * (it.qty||0)), 0); }
  function formatCurrency(v){ return '<?php echo addslashes(CURRENCY_SYMBOL); ?> ' + Number(v||0).toFixed(2); }

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
      row.className = 'cart-row';

      const link = document.createElement('a');
      link.href = `product_details.php?id=${encodeURIComponent(id)}`;
      link.style.display = 'block';
      const img = document.createElement('img');
      img.className = 'cart-item-img';
      img.src = it.img || 'https://placehold.co/80x80/7b3f00/f7e0c4?text=Img';
      img.alt = (it.name || 'Product');
      link.appendChild(img);

      const meta = document.createElement('div');
      meta.className = 'cart-meta';
      const nameEl = document.createElement('div');
      nameEl.className = 'cart-name';
      nameEl.textContent = it.name || 'Product';
      const subEl = document.createElement('div');
      subEl.className = 'cart-sub';
      subEl.textContent = `${formatCurrency(it.price)} each`;
      meta.appendChild(nameEl);
      meta.appendChild(subEl);

      const actions = document.createElement('div');
      actions.style.display = 'flex';
      actions.style.flexDirection = 'column';
      actions.style.alignItems = 'flex-end';
      actions.style.gap = '8px';
      const priceEl = document.createElement('div');
      priceEl.className = 'cart-price';
      priceEl.textContent = formatCurrency((it.price||0) * (it.qty||0));
      const qtyWrap = document.createElement('div');
      qtyWrap.className = 'cart-qty-controls';
      const decBtn = document.createElement('button'); decBtn.type = 'button'; decBtn.textContent = '−'; decBtn.dataset.action='decrease'; decBtn.dataset.id=id;
      const qtyBadge = document.createElement('div'); qtyBadge.className='qty-badge'; qtyBadge.textContent = it.qty;
      const incBtn = document.createElement('button'); incBtn.type='button'; incBtn.textContent = '+'; incBtn.dataset.action='increase'; incBtn.dataset.id=id;
      const removeBtn = document.createElement('button'); removeBtn.type='button'; removeBtn.textContent='Remove'; removeBtn.style.marginTop='8px'; removeBtn.dataset.action='remove'; removeBtn.dataset.id=id;

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

  function openCart() { cartDrawer.classList.add('open'); cartBackdrop.classList.add('visible'); cartDrawer.setAttribute('aria-hidden','false'); cartBackdrop.setAttribute('aria-hidden','false'); renderCart(); document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden'; }
  function closeCart() { cartDrawer.classList.remove('open'); cartBackdrop.classList.remove('visible'); cartDrawer.setAttribute('aria-hidden','true'); cartBackdrop.setAttribute('aria-hidden','true'); document.documentElement.style.overflow = ''; document.body.style.overflow = ''; }

  const aliCart = {
    add(item){ if(!item||!item.id) return; const id = String(item.id); if(!cart[id]) cart[id] = { id, name: item.name || 'Product', price: Number(item.price||0), img: item.img||'', qty: 0 }; cart[id].qty = (cart[id].qty||0) + (Number(item.qty)||1); saveCart(); openCart(); },
    update(id,data){ id=String(id); if(!cart[id]) return; if('qty' in data) cart[id].qty = Number(data.qty)||0; if('price' in data) cart[id].price = Number(data.price)||cart[id].price; if('name' in data) cart[id].name = data.name; if(cart[id].qty<=0) delete cart[id]; saveCart(); },
    remove(id){ id=String(id); if(cart[id]){ delete cart[id]; saveCart(); } },
    clear(){ cart={}; saveCart(); },
    get(){ return JSON.parse(JSON.stringify(cart)); }
  };

  window.aliCart = aliCart;

  // delegated add-to-cart handler
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

  cartToggle?.addEventListener('click', ()=> { if (cartDrawer.classList.contains('open')) closeCart(); else { renderCart(); openCart(); }});
  cartClose?.addEventListener('click', closeCart);
  cartBackdrop?.addEventListener('click', closeCart);
  continueBtn?.addEventListener('click', closeCart);

  cartClearBtn?.addEventListener('click', ()=>{
    if (confirm('Clear all items from your cart?')) { cart = {}; saveCart(); }
  });

  renderCart();
  window.addEventListener('storage', ()=> { cart = loadCart(); renderCart(); });
})();
</script>

</body>
</html>
