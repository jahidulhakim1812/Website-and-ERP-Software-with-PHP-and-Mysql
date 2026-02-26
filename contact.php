<?php
declare(strict_types=1);
session_start();

/*
  contact.php
  - Single-file contact page + handler
  - Mobile menu/hamburger placed before the logo (desktop nav unchanged)
  - Accepts JSON/AJAX and classic form POST submissions
  - Saves messages to `contact_messages` table (SQL in original file)
*/

// --------- DB constants (adjust if needed) ---------
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

// --------- cart/localStorage constants (kept for template parity) ---------
define('CURRENCY_SYMBOL', '$');
define('STORAGE_KEY', 'ali_hair_cart_v1');

// --------- small helpers ---------
function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getRawJsonInput(): ?array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return null;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : null;
}

// --------- handle submission (AJAX JSON or normal form POST) ---------
$maybeJson = getRawJsonInput();
$isAjaxJson = is_array($maybeJson);
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST' || $isAjaxJson;

if ($isPost) {
    $input = $isAjaxJson ? $maybeJson : $_POST;

    $session_id    = trim((string)($input['session_id'] ?? ($_SESSION['visitor_id'] ?? '')));
    $full_name     = trim((string)($input['full_name'] ?? ''));
    $email         = trim((string)($input['email'] ?? ''));
    $phone         = trim((string)($input['phone'] ?? ''));
    $country       = trim((string)($input['country'] ?? ''));
    $business_name = trim((string)($input['business_name'] ?? ''));
    $business_role = trim((string)($input['business_role'] ?? ''));
    $subject       = trim((string)($input['subject'] ?? ''));
    $message       = trim((string)($input['message'] ?? ''));

    $missing = [];
    if ($full_name === '') $missing[] = 'full_name';
    if ($email === '') $missing[] = 'email';
    if ($phone === '') $missing[] = 'phone';
    if ($country === '') $missing[] = 'country';
    if ($subject === '') $missing[] = 'subject';

    if (!empty($missing)) {
        if ($isAjaxJson) jsonResponse(['success' => false, 'error' => 'Missing required fields', 'missing' => $missing], 400);
        $redirect = $_SERVER['REQUEST_URI'] . '?error=missing';
        header('Location: ' . $redirect);
        exit;
    }

    $full_name = mb_substr($full_name, 0, 150);
    $email = mb_substr($email, 0, 190);
    $phone = mb_substr($phone, 0, 60);
    $country = mb_substr($country, 0, 120);
    $business_name = mb_substr($business_name, 0, 190);
    $business_role = mb_substr($business_role, 0, 80);
    $subject = mb_substr($subject, 0, 190);
    $message = mb_substr($message, 0, 5000);

    if ($session_id === '') {
        if (!isset($_SESSION['visitor_id'])) $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
        $session_id = $_SESSION['visitor_id'];
    }

    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error) {
        error_log('contact.php DB connect error: ' . $conn->connect_error);
        if ($isAjaxJson) jsonResponse(['success' => false, 'error' => 'Server temporarily unavailable'], 500);
        $redirect = $_SERVER['REQUEST_URI'] . '?error=db';
        header('Location: ' . $redirect);
        exit;
    }
    $conn->set_charset('utf8mb4');

    $sql = "INSERT INTO contact_messages (session_id, full_name, email, phone, country, business_name, business_role, subject, message, ip, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log('contact.php prepare failed: ' . $conn->error);
        if ($isAjaxJson) jsonResponse(['success' => false, 'error' => 'Server error'], 500);
        $redirect = $_SERVER['REQUEST_URI'] . '?error=server';
        header('Location: ' . $redirect);
        $conn->close();
        exit;
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512);

    $stmt->bind_param(
        'sssssssssss',
        $session_id,
        $full_name,
        $email,
        $phone,
        $country,
        $business_name,
        $business_role,
        $subject,
        $message,
        $ip,
        $ua
    );

    $ok = $stmt->execute();
    $inserted_id = $stmt->insert_id ?? null;
    $stmt->close();
    $conn->close();

    if ($ok) {
        if ($isAjaxJson) {
            jsonResponse(['success' => true, 'id' => $inserted_id], 200);
        } else {
            $redirect = $_SERVER['REQUEST_URI'] . '?success=1';
            header('Location: ' . $redirect);
        }
        exit;
    } else {
        error_log('contact.php insert failed: ' . ($conn ? $conn->error : 'no-conn'));
        if ($isAjaxJson) jsonResponse(['success' => false, 'error' => 'Failed to save message'], 500);
        $redirect = $_SERVER['REQUEST_URI'] . '?error=save';
        header('Location: ' . $redirect);
        exit;
    }
}

// --------- render page (GET) ---------
if (!isset($_SESSION['visitor_id'])) $_SESSION['visitor_id'] = bin2hex(random_bytes(16));
$visitor_id = $_SESSION['visitor_id'];

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$connection_error = null;
if ($conn && $conn->connect_error) {
    $connection_error = "Database Connection Failed: " . $conn->connect_error;
    error_log($connection_error);
    $conn = null;
}
if ($conn) $conn->set_charset('utf8mb4');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
     <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/favicon.png">
  <title>Contact — Ali Hair Wigs</title>
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


    /* Mobile hamburger placed before logo */
    .mobile-actions{display:flex;align-items:center;gap:8px}
    .hamburger{display:flex;align-items:center;gap:6px;padding:8px;border-radius:8px;background:transparent;border:1px solid transparent}
    .hamburger svg{width:22px;height:22px;color:#374151}
    @media(min-width:768px){ .hamburger{display:none} }

    /* Mobile left drawer */
    .mobile-drawer { position:fixed; left:0; top:0; height:100vh; width:84vw; max-width:320px; background:#fff; box-shadow:20px 0 40px rgba(0,0,0,.12); transform:translateX(-120%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:120; display:flex; flex-direction:column; padding:18px; gap:12px; }
    .mobile-drawer.open { transform:translateX(0); }
    .mobile-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); opacity:0; pointer-events:none; transition:opacity .25s; z-index:110; }
    .mobile-backdrop.visible { opacity:1; pointer-events:auto; }

    .form-card{max-width:920px;margin:32px auto;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(12,18,24,0.06);overflow:hidden;display:grid;grid-template-columns:1fr 380px;gap:0}
    @media (max-width:980px){.form-card{grid-template-columns:1fr}}
    .form-left{padding:28px}
    .form-right{background:linear-gradient(180deg,#fff 0%,#fbfbfb 100%);padding:20px;border-left:1px solid #f1f5f9}
    label{font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:8px}
    .input,textarea,select{width:100%;padding:12px 14px;border:1px solid #e6eef6;border-radius:8px;font-size:14px;color:#111827;background:#fff}
    textarea{min-height:140px;resize:vertical}
    .row{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;margin-bottom:12px}
    @media(max-width:640px){.row{grid-template-columns:1fr}}
    .muted{font-size:13px;color:#6b7280}
    .submit-btn{background:#dc2626;color:#fff;padding:12px 18px;border-radius:8px;font-weight:700;border:0;cursor:pointer}
    footer.site-footer{background:#111827;color:#fff;padding:36px 0;margin-top:40px}
    .flash{max-width:920px;margin:12px auto;padding:12px;border-radius:8px}

    /* Cart drawer */
    .cart-drawer { position:fixed; right:0; top:0; height:100vh; width:90vw; max-width:420px; background:#fff; box-shadow:-20px 0 40px rgba(0,0,0,.12); transform:translateX(110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:60; display:flex; flex-direction:column; border-top-left-radius:14px; border-bottom-left-radius:14px; overflow:hidden; }
    .cart-drawer.open { transform:translateX(0); }
    .cart-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); opacity:0; pointer-events:none; transition:opacity .25s; z-index:50; }
    .cart-backdrop.visible { opacity:1; pointer-events:auto; }
    .secondary-btn { flex:1; border:1px solid #e6e6e6; background:#fff; padding:12px 14px; border-radius:10px; text-align:center; font-weight:700; color:#374151; text-decoration:none; }
    .checkout-btn { flex:1; background:var(--primary); color:#fff; padding:12px 14px; border-radius:10px; text-align:center; font-weight:800; text-decoration:none; box-shadow: 0 8px 22px rgba(123,63,0,0.18); }

    /* Search overlay */
    .search-panel { position:fixed; inset:0; display:none; align-items:center; justify-content:center; z-index:1200; }
    .search-panel.open { display:flex; backdrop-filter: blur(2px); }
    .search-box { background:#fff; padding:10px; border-radius:10px; box-shadow:0 10px 30px rgba(0,0,0,.12); display:flex; gap:8px; width:min(720px,96%); align-items:center; }
    .search-input-wide { border:1px solid #e6eef6; padding:10px 12px; border-radius:8px; width:100%; font-size:16px; }
    .search-close { background:transparent; border:0; cursor:pointer; font-size:18px; color:#374151; }
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
     <a href="index.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Home</a>
            <a href="mens_wigs.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Men's Wigs</a>
            <a href="womens_wigs.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Women's Wigs</a>
            <a href="about.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">About</a>
            <a href="map.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Stoe Location</a>
            <a href="contact.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Contact</a>
            <a href="login.php" class="block px-4 py-4 rounded-md text-lg font-medium text-gray-800 hover:bg-gray-50">Account</a>

  </nav>

  <div style="margin-top:auto">
    <hr style="border:none;border-top:1px solid #eee;margin:12px 0">
    <div style="color:#6b7280;font-size:13px">
      <div style="margin-bottom:8px">Connect</div>
      
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

<main>
  <?php if ($connection_error): ?>
    <div class="flash" style="background:#fff7f7;border-left:4px solid #f87171;color:#7f1d1d;">
      <strong>Database Connection Warning</strong>
      <div class="muted">Could not connect to the database. Contact submissions will not be saved.</div>
    </div>
  <?php endif; ?>

  <?php if (isset($_GET['success'])): ?>
    <div class="flash" style="background:#ecfdf5;border-left:4px solid #10b981;color:#065f46;">
      Thank you — your message has been received. We will contact you soon.
    </div>
  <?php elseif (isset($_GET['error'])): ?>
    <div class="flash" style="background:#fff7f7;border-left:4px solid #f87171;color:#7f1d1d;">
      There was an issue submitting your message. Please try again.
    </div>
  <?php endif; ?>

  <div class="form-card" role="region" aria-labelledby="contact-heading">
    <div class="form-left">
      <h1 id="contact-heading" style="font-size:22px;font-weight:800;color:#111827;margin-bottom:6px">Send Us A Message</h1>
      <p class="muted" style="margin-bottom:18px">Whether you're a salon owner, stylist or business partner — share details and we'll respond quickly.</p>

      <form id="contactForm" method="post" action="contact.php" novalidate>
        <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($visitor_id, ENT_QUOTES); ?>">
        <div class="row">
          <div>
            <label for="full_name">Full Name *</label>
            <input id="full_name" name="full_name" class="input" type="text" required maxlength="150" placeholder="Your full name">
          </div>
          <div>
            <label for="email">Email address *</label>
            <input id="email" name="email" class="input" type="email" required maxlength="190" placeholder="you@example.com">
          </div>
        </div>

        <div class="row">
          <div>
            <label for="phone">Phone / Whatsapp *</label>
            <input id="phone" name="phone" class="input" type="tel" required maxlength="60" placeholder="+8801XXXXXXXXX">
          </div>
          <div>
            <label for="country">Country *</label>
            <input id="country" name="country" class="input" type="text" required maxlength="120" placeholder="Bangladesh">
          </div>
        </div>

        <div class="row">
          <div>
            <label for="business_name">Business Name</label>
            <input id="business_name" name="business_name" class="input" type="text" maxlength="190" placeholder="Salon / Company name">
          </div>
          <div>
            <label for="business_role">Select Your Business Role</label>
            <select id="business_role" name="business_role" class="input" aria-label="Business role">
              <option value="Salon Owner" selected>Salon Owner</option>
              <option value="Distributor">Distributor</option>
              <option value="Retailer">Retailer</option>
              <option value="Stylist">Stylist</option>
              <option value="Buyer">Buyer</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>

        <div style="margin-bottom:12px">
          <label for="subject">Subject *</label>
          <input id="subject" name="subject" class="input" type="text" required maxlength="190" placeholder="Subject">
        </div>

        <div style="margin-bottom:18px">
          <label for="message">Your message (optional)</label>
          <textarea id="message" name="message" class="input" maxlength="5000" placeholder="Write your message..."></textarea>
        </div>

        <div style="display:flex;gap:12px;align-items:center">
          <button type="submit" class="submit-btn">Submit</button>
          <div class="muted">We typically reply within 1 business day.</div>
        </div>
      </form>
    </div>

    <aside class="form-right">
      <h3 style="font-weight:700;color:#111827;margin-bottom:8px">Contact Details</h3>
      <p class="muted">Email: alihairwig.bd@gmail.com<br>Phone: +880 1920-899031</p>

      <div style="margin-top:18px">
        <h4 style="font-weight:700;margin-bottom:6px">Why partner with us?</h4>
        <ul class="muted" style="margin-left:14px;line-height:1.6">
          <li>Premium human hair & synthetic pieces</li>
          <li>Flexible stock and private-label options</li>
          <li>Fast, reliable shipping</li>
        </ul>
      </div>

      <div style="margin-top:18px">
        <h4 style="font-weight:700;margin-bottom:6px">Address</h4>
        <div class="muted">Holdin no: 343/A,Sarker Bari,Uttar Khan,(Helal Market). 1230 Dhaka, Bangladesh</div>
      </div>
    </aside>
  </div>
</main>

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

<script>
/* Header/search/cart/mobile scripts */
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
/* Contact form AJAX handling — sends JSON to same endpoint, falls back to classic POST */
document.addEventListener('DOMContentLoaded', function(){
  const form = document.getElementById('contactForm');
  if (!form) return;
  form.addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(form);
    const required = ['full_name','email','phone','country','subject'];
    for (const k of required) {
      if (!fd.get(k) || String(fd.get(k)).trim() === '') {
        alert('Please complete all required fields marked with *');
        return;
      }
    }
    const payload = {};
    fd.forEach((v,k)=> payload[k]=v);

    const btn = form.querySelector('button[type="submit"]');
    const orig = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Sending…';

    try {
      const res = await fetch(form.action, {
        method: 'POST',
        headers: {'Content-Type':'application/json','Accept':'application/json'},
        body: JSON.stringify(payload),
        cache: 'no-store'
      });
      const ct = res.headers.get('Content-Type') || '';
      let body;
      if (ct.includes('application/json')) body = await res.json();
      else body = await res.text();

      if (!res.ok) {
        if (body && body.error) alert('Error: ' + body.error);
        else alert('Server error. Try again later.');
        console.warn('Unexpected response:', body);
      } else {
        if (body && body.success) {
          alert('Thanks — your message has been received. We will contact you soon.');
          form.reset();
          window.location.href = window.location.pathname + '?success=1';
        } else {
          alert('Could not submit. Try again later.');
        }
      }
    } catch (err) {
      console.error(err);
      alert('Network error. Try again later.');
    } finally {
      btn.disabled = false;
      btn.textContent = orig;
    }
  });
});
</script>

<?php if ($conn) $conn->close(); ?>
</body>
</html>
