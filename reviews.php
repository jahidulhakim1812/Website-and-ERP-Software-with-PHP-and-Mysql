<?php
declare(strict_types=1);
session_start();

/*
  reviews.php
  - Displays all customer reviews (paginated)
  - Header, footer, mobile drawer, search overlay and sliding cart match our_mission.php exactly
  - Auto-detects common review table names and common column names
  - Adjust DB constants if needed
*/

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

define('CURRENCY_SYMBOL', '$');
define('STORAGE_KEY', 'ali_hair_cart_v1');

function esc($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function fmtDate($d) { if (!$d) return ''; $t = strtotime($d); return $t ? date('M d, Y', $t) : esc($d); }
function starsHtml($n) { $n = (int)max(0, min(5, round($n))); $out = ''; for ($i=0;$i<5;$i++) $out .= ($i < $n) ? '★' : '☆'; return $out; }

if (!isset($_SESSION['visitor_id'])) $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
$visitor_id = $_SESSION['visitor_id'];

// connect
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn && $conn->connect_error) { error_log('DB connect error: '.$conn->connect_error); $conn = null; }
if ($conn) $conn->set_charset('utf8mb4');

// detect reviews table
$reviewTable = null;
$possibleTables = array('product_reviews','reviews','customer_reviews','feedback','product_review');
if ($conn) {
    $res = $conn->query("SHOW TABLES");
    if ($res) {
        $tables = array();
        while ($r = $res->fetch_row()) $tables[] = $r[0];
        $res->free();
        foreach ($possibleTables as $t) {
            if (in_array($t, $tables, true)) { $reviewTable = $t; break; }
        }
    }
}
if (!$reviewTable) $reviewTable = 'reviews';

// detect columns
$cols = array();
if ($conn) {
    $r = $conn->query("SHOW COLUMNS FROM `".$conn->real_escape_string($reviewTable)."`");
    if ($r) {
        while ($row = $r->fetch_assoc()) $cols[] = $row['Field'];
        $r->free();
    }
}

// map likely columns
$colMap = array(
    'id' => null,
    'name' => null,
    'rating' => null,
    'comment' => null,
    'product_id' => null,
    'product_name' => null,
    'created_at' => null
);
if (!empty($cols)) {
    foreach ($cols as $c) {
        $lc = strtolower($c);
        if (!$colMap['id'] && in_array($lc, array('id','review_id','rid'), true)) $colMap['id'] = $c;
        if (!$colMap['name'] && in_array($lc, array('name','customer_name','reviewer','author'), true)) $colMap['name'] = $c;
        if (!$colMap['rating'] && in_array($lc, array('rating','stars','score'), true)) $colMap['rating'] = $c;
        if (!$colMap['comment'] && in_array($lc, array('comment','review','body','message','content'), true)) $colMap['comment'] = $c;
        if (!$colMap['product_id'] && in_array($lc, array('product_id','pid','p_id','item_id','product'), true)) $colMap['product_id'] = $c;
        if (!$colMap['product_name'] && in_array($lc, array('product_name','producttitle','product_title','pname'), true)) $colMap['product_name'] = $c;
        if (!$colMap['created_at'] && in_array($lc, array('created_at','created','date','date_added','created_on','added_at'), true)) $colMap['created_at'] = $c;
    }
}
if (!$colMap['id']) $colMap['id'] = $cols[0] ?? 'id';
if (!$colMap['comment']) {
    foreach ($cols as $c) {
        if (stripos($c, 'comment') !== false || stripos($c, 'review') !== false || stripos($c, 'message') !== false) { $colMap['comment'] = $c; break; }
    }
}
if (!$colMap['comment']) $colMap['comment'] = $cols[1] ?? $colMap['id'];

// pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$reviews = array();
$total = 0;

if ($conn) {
    $countSql = "SELECT COUNT(*) AS cnt FROM `".$conn->real_escape_string($reviewTable)."`";
    $cr = $conn->query($countSql);
    if ($cr && ($rw = $cr->fetch_assoc())) { $total = (int)$rw['cnt']; $cr->free(); }

    $selectCols = array();
    foreach (array('id','name','rating','comment','product_id','product_name','created_at') as $k) {
        if (!empty($colMap[$k])) $selectCols[] = "`".$conn->real_escape_string($colMap[$k])."` AS `".$k."`";
    }
    $select = implode(',', $selectCols);
    if ($select === '') $select = '*';

    $order = !empty($colMap['created_at']) ? "`".$conn->real_escape_string($colMap['created_at'])."` DESC" : "`".$conn->real_escape_string($colMap['id'])."` DESC";
    $sql = "SELECT {$select} FROM `".$conn->real_escape_string($reviewTable)."` ORDER BY {$order} LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('ii', $perPage, $offset);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $reviews[] = $r;
        $stmt->close();
    }
}
if ($conn) $conn->close();

$totalPages = max(1, (int)ceil($total / $perPage));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/favicon.png">
  <title>Customer Reviews — Ali Hair Wigs</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700;800&display=swap" rel="stylesheet">
  <style>
    :root{--primary:#7b3f00;--secondary:#f7e0c4}
    html,body{height:100%}
    body{font-family:'Inter',system-ui,-apple-system,'Segoe UI',Roboto,Arial;background:#fcfcfc;color:#111827;margin:0;-webkit-font-smoothing:antialiased}
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
    .hamburger{display:flex;align-items:center;gap:6px;padding:8px;border-radius:8px;background:transparent;border:1px solid transparent}
    .hamburger svg{width:22px;height:22px;color:#374151}
    @media(min-width:768px){ .hamburger{display:none} }

    .page-inner{max-width:1100px;margin:28px auto;padding:0 16px}
    .hero{background:linear-gradient(180deg,#fff 0%,#fbfbfb 100%);padding:22px;border-radius:12px;margin-bottom:18px;box-shadow:0 6px 20px rgba(12,18,24,0.04)}
    h1{font-size:24px;font-weight:800;color:#111827;margin:0 0 8px}
    .muted{color:#6b7280}
    .review{background:#fff;padding:14px;border-radius:12px;box-shadow:0 8px 30px rgba(12,18,24,0.06);margin-bottom:14px}
    .stars{color:#f59e0b;font-weight:800}
    .grid{display:grid;grid-template-columns:1fr;gap:14px}
    .pagination{display:flex;gap:8px;justify-content:center;margin-top:18px}
    .page-link{background:#fff;border:1px solid #e6e6e6;padding:8px 10px;border-radius:8px;text-decoration:none;color:#374151;font-weight:700}
    .page-link.active{background:var(--primary);color:#fff;border-color:var(--primary)}
    footer.site-footer{background:#111827;color:#fff;padding:36px 0;margin-top:40px}
    .mobile-drawer { position:fixed; left:0; top:0; height:100vh; width:84vw; max-width:320px; background:#fff; box-shadow:20px 0 40px rgba(0,0,0,.12); transform:translateX(-120%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:120; display:flex; flex-direction:column; padding:18px; gap:12px; }
    .mobile-drawer.open { transform:translateX(0); }
    .mobile-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); opacity:0; pointer-events:none; transition:opacity .25s; z-index:110; }
    .mobile-backdrop.visible { opacity:1; pointer-events:auto; }

    .cart-drawer { position:fixed; right:0; top:0; height:100vh; width:90vw; max-width:420px; background:#fff; box-shadow:-20px 0 40px rgba(0,0,0,.12); transform:translateX(110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:60; display:flex; flex-direction:column; border-top-left-radius:14px; border-bottom-left-radius:14px; overflow:hidden; }
    .cart-drawer.open { transform:translateX(0); }
    .cart-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); opacity:0; pointer-events:none; transition:opacity .25s; z-index:50; }
    .cart-backdrop.visible { opacity:1; pointer-events:auto; }
    .secondary-btn { flex:1; border:1px solid #e6e6e6; background:#fff; padding:12px 14px; border-radius:10px; text-align:center; font-weight:700; color:#374151; text-decoration:none; }
    .checkout-btn { flex:1; background:var(--primary); color:#fff; padding:12px 14px; border-radius:10px; text-align:center; font-weight:800; text-decoration:none; box-shadow: 0 8px 22px rgba(123,63,0,0.18); }
    .search-panel { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1200; }
    .search-panel.open { display:flex; backdrop-filter: blur(2px); }
    .search-box { background:#fff; padding:10px; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); display:flex; gap:8px; width:min(720px,96%); align-items:center; }
    .search-input-wide { border:1px solid #e6eef6; padding:10px 12px; border-radius:8px; width:100%; font-size:16px; }
  </style>
</head>
<body class="min-h-screen">

<header class="site-header" role="banner">
  <div class="header-inner">
    <div style="display:flex;align-items:center;gap:12px">
      <button id="hamburger" class="hamburger" aria-label="Open menu" aria-expanded="false" type="button">
        <svg id="hamburger-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
        <svg id="hamburger-close" xmlns="http://www.w3.org/2000/svg" style="display:none" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
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
        <a href="product_category.php">Categories</a>
        <a href="login.php">Account</a>
      </nav>
    </div>

    <div style="display:flex;align-items:center;gap:8px">
      <button id="search-toggle" class="search-toggle" aria-expanded="false" aria-controls="search-panel" aria-label="Open search" style="border:0;background:transparent;padding:6px">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true" style="width:20px;height:20px;color:#374151">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M5.5 11a5.5 5.5 0 1111 0 5.5 5.5 0 01-11 0z"/>
        </svg>
      </button>

      <div class="relative ml-2">
        <button id="cart-toggle" aria-label="Cart" class="p-2 rounded-md text-gray-700 hover:bg-gray-100 relative" type="button" style="border:0;background:transparent">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true" style="width:20px;height:20px;color:#374151">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h13M7 13H5.4M16 16a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
          </svg>
          <span id="cart-count" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">0</span>
        </button>
      </div>
    </div>
  </div>
</header>

<!-- Mobile drawer + backdrop -->
<div id="mobile-backdrop" class="mobile-backdrop" aria-hidden="true"></div>
<aside id="mobile-drawer" class="mobile-drawer" aria-hidden="true" role="dialog" aria-label="Mobile menu">
  <div style="display:flex;align-items:center;justify-content:space-between">
    <div style="display:flex;align-items:center;gap:10px">
      <img src="uploads/ahw.png" alt="" style="height:36px;width:36px;object-fit:contain">
      <div style="font-weight:800;color:var(--primary)">ALI HAIR <div style="font-size:12px;color:#6b7280">WIGS</div></div>
    </div>
    <button id="mobile-close" aria-label="Close menu" style="background:transparent;border:0;font-size:20px;">&times;</button>
  </div>

  <nav style="display:flex;flex-direction:column;gap:10px;margin-top:12px" aria-label="Mobile navigation">
    <a href="index.php" style="padding:10px;border-radius:8px;color:#111827;text-decoration:none;font-weight:700">Home</a>
    <a href="mens_wigs.php" style="padding:10px;border-radius:8px;color:#111827;text-decoration:none;font-weight:700">Men's Wigs</a>
    <a href="womens_wigs.php" style="padding:10px;border-radius:8px;color:#111827;text-decoration:none;font-weight:700">Women's Wigs</a>
    <a href="about.php" style="padding:10px;border-radius:8px;color:#111827;text-decoration:none;font-weight:700">About</a>
    <a href="product_category.php" style="padding:10px;border-radius:8px;color:#111827;text-decoration:none;font-weight:700">Categories</a>
    <a href="login.php" style="padding:10px;border-radius:8px;color:#111827;text-decoration:none;font-weight:700">Account</a>
  </nav>

  <div style="margin-top:auto">
    <hr style="border:none;border-top:1px solid #eee;margin:12px 0">
    <div style="color:#6b7280;font-size:13px">
      <div style="margin-bottom:8px">Connect</div>
      <div>Email: info@wigstudio.com</div>
      <div style="margin-top:8px">Dhaka, Bangladesh</div>
    </div>
  </div>
</aside>

<!-- Search overlay -->
<div id="search-panel" class="search-panel" aria-hidden="true">
  <div class="search-box" role="search" aria-label="Site search">
    <form id="search-form" action="search.php" method="get" style="display:flex;flex:1;gap:8px;align-items:center">
      <label for="q-wide" class="sr-only">Search products</label>
      <input id="q-wide" name="q" type="search" class="search-input-wide" placeholder="Search products, styles..." aria-label="Search products" autocomplete="off" />
      <button type="submit" class="submit-btn" style="background:var(--primary);border-radius:8px;color:#fff;padding:8px 12px">Search</button>
    </form>
    <button id="search-close" class="search-close" aria-label="Close search">&times;</button>
  </div>
</div>

<main class="page-inner" role="main">
  <div class="hero">
    <h1>Customer Reviews</h1>
    <p class="muted">All customer reviews are shown below. Thank you to everyone who shares feedback.</p>
  </div>

  <div style="max-width:1100px;margin:0 auto;padding:0 16px">
    <p class="muted" style="margin-bottom:12px"><?php echo $total ?: 0; ?> review<?php echo $total===1 ? '' : 's'; ?> found</p>

    <?php if (empty($reviews)): ?>
      <div class="review">No reviews found yet.</div>
    <?php else: ?>
      <div class="grid" role="list">
        <?php foreach ($reviews as $r): ?>
          <article class="review" role="listitem">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
              <div>
                <div style="font-weight:800"><?php echo esc($r['name'] ?? 'Anonymous'); ?></div>
                <div class="muted" style="font-size:13px"><?php echo !empty($r['product_name']) ? esc($r['product_name']) : (!empty($r['product_id']) ? 'Product ID: '.esc($r['product_id']) : ''); ?></div>
              </div>
              <div style="text-align:right">
                <div class="stars" aria-hidden="true"><?php echo starsHtml($r['rating'] ?? 0); ?></div>
                <div class="muted" style="font-size:13px"><?php echo fmtDate($r['created_at'] ?? ''); ?></div>
              </div>
            </div>
            <div style="margin-top:12px;color:#374151"><?php echo nl2br(esc($r['comment'] ?? '')); ?></div>
          </article>
        <?php endforeach; ?>
      </div>

      <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Pagination">
          <?php for ($p=1;$p<=$totalPages;$p++): ?>
            <?php $active = $p === $page ? 'active' : ''; ?>
            <a class="page-link <?php echo $active; ?>" href="?page=<?php echo $p; ?>"><?php echo $p; ?></a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    <?php endif; ?>

    <section style="margin-top:18px;background:#fff;padding:16px;border-radius:12px;box-shadow:0 8px 30px rgba(12,18,24,0.06)">
      <h3 style="margin:0 0 8px;font-weight:800">Leave a review</h3>
      <p class="muted" style="margin-bottom:10px">Submit your review and help others choose the right product.</p>

      <form id="reviewForm" method="post" action="submit_review.php" style="display:grid;gap:8px;grid-template-columns:1fr 120px">
        <input name="product_id" type="hidden" value="">
        <input name="product_name" type="hidden" value="">
        <input name="name" type="text" placeholder="Your name" required style="padding:10px;border:1px solid #e6eef6;border-radius:8px">
        <select name="rating" required style="padding:10px;border:1px solid #e6eef6;border-radius:8px">
          <option value="">Rating</option>
          <option value="5">5 — Excellent</option>
          <option value="4">4 — Very good</option>
          <option value="3">3 — Good</option>
          <option value="2">2 — Fair</option>
          <option value="1">1 — Poor</option>
        </select>
        <textarea name="comment" placeholder="Write your review (max 2000 chars)" maxlength="2000" required style="grid-column:1/ -1; padding:10px;border:1px solid #e6eef6;border-radius:8px;min-height:120px"></textarea>
        <div style="grid-column:1/ -1;display:flex;justify-content:flex-end">
          <button type="submit" style="background:var(--primary);color:#fff;border:0;padding:10px 14px;border-radius:8px;font-weight:800">Submit Review</button>
        </div>
      </form>
    </section>
  </div>
</main>

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
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>

  <div id="cart-items" class="p-4 overflow-auto" style="flex:1 1 auto;">
    <div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>
  </div>

  <div class="cart-footer cart-footer-sticky" style="padding:14px;border-top:1px solid #f1f5f9">
    <div class="flex items-center justify-between mb-1">
      <div class="text-sm text-gray-600">Subtotal</div>
      <div id="cart-subtotal" class="text-lg font-bold text-[var(--primary)]"><?php echo CURRENCY_SYMBOL . ' 0.00'; ?></div>
    </div>
    <div class="checkout-cta" style="display:flex;gap:8px">
      <a href="checkout.php" id="checkout-btn" class="checkout-btn">Checkout</a>
      <button id="continue-shopping" class="secondary-btn">Continue</button>
    </div>
    <div class="text-xs text-gray-500 mt-1">Shipping calculated at checkout</div>
  </div>
</aside>

<script>
/* Header/search/cart/mobile scripts (same behaviour as our_mission.php) */
(function(){
  // Mobile drawer toggle
  const hamburger = document.getElementById('hamburger');
  const mobileDrawer = document.getElementById('mobile-drawer');
  const mobileBackdrop = document.getElementById('mobile-backdrop');
  const mobileClose = document.getElementById('mobile-close');
  const openIcon = document.getElementById('hamburger-open');
  const closeIcon = document.getElementById('hamburger-close');

  function openMobile(){ if(!mobileDrawer) return; mobileDrawer.classList.add('open'); mobileBackdrop.classList.add('visible'); mobileDrawer.setAttribute('aria-hidden','false'); hamburger.setAttribute('aria-expanded','true'); if(openIcon) openIcon.style.display='none'; if(closeIcon) closeIcon.style.display='block'; document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden'; }
  function closeMobile(){ if(!mobileDrawer) return; mobileDrawer.classList.remove('open'); mobileBackdrop.classList.remove('visible'); mobileDrawer.setAttribute('aria-hidden','true'); hamburger.setAttribute('aria-expanded','false'); if(openIcon) openIcon.style.display='block'; if(closeIcon) closeIcon.style.display='none'; document.documentElement.style.overflow=''; document.body.style.overflow=''; hamburger.focus(); }
  if (hamburger) hamburger.addEventListener('click', ()=> { if (mobileDrawer && mobileDrawer.classList.contains('open')) closeMobile(); else openMobile(); });
  if (mobileClose) mobileClose.addEventListener('click', closeMobile);
  if (mobileBackdrop) mobileBackdrop.addEventListener('click', closeMobile);
  document.addEventListener('keydown', (e)=> { if (e.key === 'Escape') { closeMobile(); window.closeCart && window.closeCart(); } });

  // Search panel
  const searchToggle = document.getElementById('search-toggle');
  const searchPanel = document.getElementById('search-panel');
  const searchClose = document.getElementById('search-close');
  const searchInput = document.getElementById('q-wide');
  const searchForm = document.getElementById('search-form');

  function openSearch(){ if(!searchPanel) return; searchPanel.classList.add('open'); searchPanel.setAttribute('aria-hidden','false'); if(searchToggle) searchToggle.setAttribute('aria-expanded','true'); setTimeout(()=> searchInput?.focus(), 60); document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden'; }
  function closeSearch(){ if(!searchPanel) return; searchPanel.classList.remove('open'); searchPanel.setAttribute('aria-hidden','true'); if(searchToggle) searchToggle.setAttribute('aria-expanded','false'); document.documentElement.style.overflow=''; document.body.style.overflow=''; searchToggle?.focus(); }

  if (searchToggle) searchToggle.addEventListener('click', (e)=>{ e.preventDefault(); if (searchPanel && searchPanel.classList.contains('open')) closeSearch(); else openSearch(); });
  if (searchClose) searchClose.addEventListener('click', closeSearch);
  if (searchPanel) searchPanel.addEventListener('click', (e)=> { if (e.target === searchPanel) closeSearch(); });
  if (searchForm) {
    searchForm.addEventListener('submit', function(e){
      const q = (searchInput && searchInput.value || '').trim();
      if (!q) { e.preventDefault(); searchInput.focus(); return; }
      closeSearch();
    });
  }

  // Cart (localStorage-backed)
  const STORAGE_KEY = '<?php echo STORAGE_KEY; ?>';
  const cartToggle = document.getElementById('cart-toggle');
  const cartDrawer = document.getElementById('cart-drawer');
  const cartBackdrop = document.getElementById('cart-backdrop');
  const cartClose = document.getElementById('cart-close');
  const cartItemsEl = document.getElementById('cart-items');
  const cartCountEl = document.getElementById('cart-count');
  const cartCountMini = document.getElementById('cart-count-mini');
  const cartSubtotalEl = document.getElementById('cart-subtotal');
  const cartClearBtn = document.getElementById('cart-clear');
  const continueBtn = document.getElementById('continue-shopping');

  function loadCart(){ try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}'); } catch(e) { return {}; } }
  let cart = loadCart();

  function saveCart(){ try{ localStorage.setItem(STORAGE_KEY, JSON.stringify(cart)); }catch(e){} renderCart(); }
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
          if (action === 'increase') cart[pid].qty += 1;
          else if (action === 'decrease') cart[pid].qty = Math.max(1, cart[pid].qty - 1);
          else if (action === 'remove') delete cart[pid];
          saveCart();
        });
      });
    });

    cartItemsEl.innerHTML = '';
    cartItemsEl.appendChild(container);
    if (cartSubtotalEl) cartSubtotalEl.textContent = formatCurrency(getSubtotal());
  }

  function openCart(){ cartDrawer.classList.add('open'); cartBackdrop.classList.add('visible'); renderCart(); document.documentElement.style.overflow='hidden'; document.body.style.overflow='hidden'; }
  function closeCart(){ cartDrawer.classList.remove('open'); cartBackdrop.classList.remove('visible'); document.documentElement.style.overflow=''; document.body.style.overflow=''; }

  const aliCart = {
    add(item){ if(!item||!item.id) return; const id=String(item.id); if(!cart[id]) cart[id] = { id, name:item.name||'Product', price:Number(item.price||0), img:item.img||'', qty:0 }; cart[id].qty = (cart[id].qty||0) + (Number(item.qty)||1); saveCart(); openCart(); },
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
  if (cartClearBtn) cartClearBtn.addEventListener('click', ()=> { if (confirm('Clear all items from your cart?')) { cart = {}; saveCart(); } });

  renderCart();
  window.addEventListener('storage', ()=> { cart = loadCart(); renderCart(); });

  window.closeCart = closeCart;
  window.openCart = openCart;
})();
</script>

<script>
/* Search and mobile keyboard/escape behaviour */
(function(){
  const searchForm = document.getElementById('search-form');
  const searchInput = document.getElementById('q-wide');
  const searchPanel = document.getElementById('search-panel');
  const searchClose = document.getElementById('search-close');

  if (searchForm) {
    searchForm.addEventListener('submit', function(e){
      const q = (searchInput && searchInput.value || '').trim();
      if (!q) { e.preventDefault(); searchInput.focus(); return; }
      if (searchPanel) searchPanel.classList.remove('open');
    });
  }
  if (searchClose) searchClose.addEventListener('click', function(){ if (searchPanel) searchPanel.classList.remove('open'); });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') {
      document.getElementById('mobile-drawer')?.classList.remove('open');
      document.getElementById('search-panel')?.classList.remove('open');
      document.getElementById('cart-drawer')?.classList.remove('open');
    }
  });
})();
</script>

</body>
</html>
