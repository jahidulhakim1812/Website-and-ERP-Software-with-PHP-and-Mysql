<?php
// db_connect.php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'alihairwigs');
define('DB_USER', 'root'); // CHANGE THIS TO YOUR DATABASE USERNAME
define('DB_PASS', ''); // CHANGE THIS TO YOUR DATABASE PASSWORD

try {
    // Create a new PDO instance
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Optional: Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Display an error message if connection fails
    die("Database connection failed: " . $e->getMessage());
}
    
    $sql = "SELECT title, subtitle, button_text, image_url FROM sliderimages ORDER BY title ASC";


    <?php
// index.php — Single-file homepage with mobile-optimized hero slider, About/Factory section and slide-out cart
declare(strict_types=1);

// -------------------------
// Database configuration
// -------------------------
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'alihairwigs');

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
$connection_error = null;
if ($conn->connect_error) {
    $connection_error = "Database Connection Failed: " . $conn->connect_error;
    error_log($connection_error);
    $conn = null;
}

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

// -------------------------
// Fetch page data
// -------------------------
$slides_result = $conn ? fetchSlides($conn) : false;
$categories_result = $conn ? fetchProductCategories($conn) : false;
$men_products_result = $conn ? fetchProductsByCategory($conn, 'men') : false;
$women_products_result = $conn ? fetchProductsByCategory($conn, 'women') : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Ali Hair Wigs — Premium Wigs Store</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #7b3f00; --secondary: #f7e0c4; --promo-bg: #6bb04a; --promo-accent: #ffffff; }
        body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, Arial; background:#fcfcfc; -webkit-font-smoothing:antialiased; color:#1f2937; }

        /* HERO SLIDER */
        #hero-slider { height: 88vh; min-height:420px; position:relative; overflow:hidden; }
        .hero-slide { position:absolute; inset:0; background-size:cover; background-position:center center; display:none; opacity:0; transition:opacity .7s ease-in-out; }
        .hero-slide.active { display:block; opacity:1; }
        .hero-overlay { background:linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.25)); backdrop-filter: blur(1px); }
        .hero-caption { max-width:900px; }

        .slider-dot { width:.7rem; height:.7rem; border-radius:999px; background:#fff; opacity:.45; transition:transform .2s, opacity .2s; }
        .slider-dot.active { transform:scale(1.25); opacity:1; }

        /* CATEGORY CAROUSEL */
        .category-carousel { display:flex; gap:1rem; overflow-x:auto; scroll-behavior:smooth; -webkit-overflow-scrolling:touch; padding-bottom:.5rem; }
        .category-card { flex: 0 0 20rem; border-radius:.75rem; overflow:hidden; background:#fff; border:1px solid #eee; transition:transform .25s; text-decoration:none; color:inherit; display:block; }
        .category-card:hover { transform:translateY(-6px); }
        .category-card img { width:100%; height:12rem; object-fit:cover; display:block; }

        .category-card img.mobile-perfect { height:10rem; object-fit:cover; object-position:center; }
        @media (min-width:640px) { .category-card { flex: 0 0 16rem; } }
        @media (min-width:768px) { .category-card { flex: 0 0 13rem; } }

        /* PRODUCT CARDS */
        .prod-img { height:14rem; object-fit:cover; width:100%; }

        /* PROMO STYLE */
        .promo-card {
            background: linear-gradient(180deg, var(--promo-bg) 0%, #4f8f36 100%);
            color: var(--promo-accent);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            gap: 12px;
            align-items: center;
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            text-decoration: none;
        }
        .promo-thumb { width:84px; height:84px; border-radius:8px; object-fit:cover; border: 3px solid rgba(255,255,255,0.12); background:#fff; flex:0 0 84px; }
        .promo-meta { flex:1 1 auto; display:flex; flex-direction:column; gap:6px; }
        .promo-name { font-weight:700; font-size:1rem; line-height:1; color:#fff; }
        .promo-price { font-weight:800; font-size:1.05rem; color:#fff; }
        .promo-quick { background:#fff;color:var(--promo-bg); font-weight:700; padding:8px 12px; border-radius:8px; text-decoration:none; display:inline-block; font-size:.95rem; }

        /* grid responsiveness */
        .grid-2-mobile { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        @media (min-width: 640px) { .grid-sm-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (min-width: 768px) { .grid-md-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); } }

        /* CART DRAWER */
        .cart-drawer { position:fixed; right:0; top:0; height:100vh; width:90vw; max-width:420px; background:#fff; box-shadow:-20px 0 40px rgba(0,0,0,.12); transform:translateX(110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:60; display:flex; flex-direction:column; }
        .cart-drawer.open { transform:translateX(0); }
        .cart-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.35); opacity:0; pointer-events:none; transition:opacity .25s; z-index:50; }
        .cart-backdrop.visible { opacity:1; pointer-events:auto; }
        .cart-item-img { width:64px; height:64px; object-fit:cover; border-radius:.5rem; }

        /* cart item card style */
        .cart-row {
          display:flex;
          gap:0.75rem;
          align-items:center;
          padding:0.5rem;
          border-radius:0.5rem;
          background:#ffffff;
          border:1px solid #e6e6e6;
          box-shadow:0 1px 2px rgba(0,0,0,0.03);
        }
        .cart-row a.cart-link {
          display:flex;
          gap:0.75rem;
          align-items:center;
          text-decoration:none;
          color:inherit;
          flex:1 1 auto;
        }
        .cart-row .cart-item-img {
          width:72px;
          height:72px;
          object-fit:cover;
          border-radius:0.5rem;
          flex:0 0 72px;
        }
        .cart-row .cart-meta { display:flex; flex-direction:column; gap:0.25rem; flex:1 1 auto; }
        .cart-row .cart-name { font-weight:600; font-size:0.95rem; color:#111827; }
        .cart-row .cart-sub { font-size:0.78rem; color:#6b7280; }
        .cart-row .cart-actions { text-align:right; flex:0 0 auto; display:flex; flex-direction:column; gap:0.4rem; align-items:flex-end; }
        .cart-row .cart-price { color:var(--primary); font-weight:700; }
        .cart-row .cart-qty-controls button { padding:0.28rem 0.45rem; border-radius:6px; border:1px solid #e5e7eb; background:#fff; }
        .cart-row .cart-quick { font-size:0.78rem; padding:0.35rem 0.6rem; border-radius:6px; background:#f3f4f6; border:1px solid #e5e7eb; color:#111827; }
        .cart-row .cart-quick:hover { background:#fff; }

        /* display multiple cart items in grid (two per row on >= 640px) */
        #cart-items .cart-grid { display:grid; grid-template-columns: 1fr; gap:0.75rem; }
        @media (min-width:640px) { #cart-items .cart-grid { grid-template-columns: repeat(2, 1fr); } }

        .sr-only { position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }

        /* Left-side mobile nav (off-canvas) - we use a left drawer that animates in */
        .mobile-left-drawer { position:fixed; left:0; top:0; height:100vh; width:84vw; max-width:320px; background:#fff; box-shadow:20px 0 40px rgba(0,0,0,.12); transform:translateX(-110%); transition:transform .32s cubic-bezier(.2,.9,.2,1); z-index:70; padding:1rem; overflow:auto; }
        .mobile-left-drawer.open { transform:translateX(0); }
        .mobile-left-close { display:inline-flex; align-items:center; gap:6px; cursor:pointer; }

        /* mobile layout tweaks */
        @media (max-width: 640px) {
            #hero-slider { min-height:54vh; height:54vh; }
            .hero-slide { background-position: center 30%; }
            .hero-caption { padding: 1.25rem; }
            .hero-caption h1 { font-size: 1.6rem; line-height:1.05; }
        }
    </style>
</head>
<body class="min-h-screen">

<header class="bg-white shadow sticky top-0 z-60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
        <!-- Mobile hamburger moved to the left for mobile -->
        <div class="flex items-center gap-3">
            <button id="hamburger" aria-expanded="false" aria-controls="mobile-left-drawer" class="md:hidden p-2 rounded-md text-gray-700 hover:bg-gray-100" type="button">
                <svg id="hamburger-open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg id="hamburger-close" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                <span class="sr-only">Toggle menu</span>
            </button>

            <a href="index.php" class="flex items-center space-x-2 text-2xl font-extrabold text-[var(--primary)]">
                <img src="assets/logo.png" alt="Ali Hair Wigs Logo" class="h-8 w-8 sm:h-10 sm:w-10 object-contain">
                <span>ALI HAIR <span class="text-gray-700">WIGS</span></span>
            </a>
        </div>

        <nav id="desktop-nav" class="hidden md:flex space-x-8 text-gray-600 font-medium" aria-label="Primary navigation">
            <a href="#categories" class="hover:text-[var(--primary)] transition">Categories</a>
            <a href="#men" class="hover:text-[var(--primary)] transition">Men's Wigs</a>
            <a href="#women" class="hover:text-[var(--primary)] transition">Women's Wigs</a>
            <a href="#about" class="hover:text-[var(--primary)] transition">About</a>
            <a href="login.php" class="hover:text-[var(--primary)] transition">Account</a>
        </nav>

        <div id="cart-icon" class="relative ml-4">
            <button id="cart-toggle" aria-label="Cart" class="p-2 rounded-md text-gray-700 hover:bg-gray-100 relative">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13l-1.5 6h13M7 13H5.4M16 16a2 2 0 1 1-4 0 2 2 0 0 1 4 0z"/>
                </svg>
                <span id="cart-count" class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">0</span>
            </button>
        </div>
    </div>
</header>

<!-- Mobile left drawer (menu) -->
<div id="mobile-left-drawer" class="mobile-left-drawer" aria-hidden="true" role="navigation">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-3">
            <img src="assets/logo.png" alt="Logo" class="h-8 w-8 object-contain">
            <div class="font-bold text-lg text-[var(--primary)]">ALI HAIR WIGS</div>
        </div>
        <button id="mobile-left-close" class="mobile-left-close text-gray-700 p-2 rounded-md hover:bg-gray-100" aria-label="Close menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="space-y-3">
        <a href="#categories" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-50">Categories</a>
        <a href="#men" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-50">Men's Wigs</a>
        <a href="#women" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-50">Women's Wigs</a>
        <a href="#about" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-50">About</a>
        <a href="login.php" class="block px-3 py-2 rounded-md text-base font-medium text-gray-700 hover:bg-gray-50">Account</a>
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
    <div id="slider-container" class="w-full h-full relative">
        <?php
        $slide_count = 0;
        if ($slides_result && $slides_result->num_rows > 0):
            while ($slide = $slides_result->fetch_assoc()):
                $slide_count++;
                $title = htmlspecialchars($slide['title'] ?? 'Ali Hair Wigs');
                $subtitle = htmlspecialchars($slide['subtitle'] ?? '');
                $button_text = htmlspecialchars($slide['button_text'] ?? 'Explore Our Products');
                $img_url = !empty($slide['image_url']) ? htmlspecialchars($slide['image_url']) : 'https://placehold.co/1920x1080/7b3f00/f7e0c4?text=Slide';
                $mobile_img = !empty($slide['mobile_image_url']) ? htmlspecialchars($slide['mobile_image_url']) : $img_url;
                $active = $slide_count === 1 ? 'active' : '';
                $bg_css = "url('{$img_url}')";
        ?>
            <div class="hero-slide <?php echo $active ?>" style="background-image:<?php echo $bg_css ?>" data-mobile-image="<?php echo $mobile_img ?>" data-desktop-bg="<?php echo $bg_css ?>">
                <div class="absolute inset-0 hero-overlay flex items-center justify-center p-6">
                    <div class="hero-caption text-center text-white">
                        <h1 class="text-3xl sm:text-5xl md:text-6xl font-extrabold tracking-tight"><?php echo $title ?></h1>
                        <?php if ($subtitle): ?><p class="mt-4 text-lg sm:text-2xl text-[var(--secondary)] font-light"><?php echo $subtitle ?></p><?php endif; ?>
                        <div class="mt-8 flex justify-center">
                            <a href="#categories" class="inline-flex items-center px-6 py-3 bg-[var(--primary)] text-white font-semibold rounded-full shadow hover:scale-[1.03] transition">
                                <svg class="w-5 h-5 mr-2" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M6 4l10 6-10 6V4z"/></svg>
                                <?php echo $button_text ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php
            endwhile;
            $slides_result->free();
        else:
        ?>
            <div class="hero-slide active" style="background-image:linear-gradient(135deg,#5b2a00 0%,#7b3f00 100%)" data-desktop-bg="linear-gradient(135deg,#5b2a00 0%,#7b3f00 100%)" data-mobile-image="">
                <div class="absolute inset-0 hero-overlay flex items-center justify-center p-6">
                    <div class="text-center text-white">
                        <h1 class="text-3xl sm:text-5xl font-extrabold">Welcome to ALI HAIR WIGS</h1>
                        <p class="mt-3 text-[var(--secondary)]">Premium quality, unmatched style. Start shopping below.</p>
                        <div class="mt-8 flex justify-center">
                            <a href="#categories" class="inline-flex items-center px-6 py-3 bg-[var(--secondary)] text-[var(--primary)] font-semibold rounded-full shadow hover:scale-[1.03] transition">Explore Our Collections</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="slider-dots" class="absolute bottom-6 left-1/2 transform -translate-x-1/2 flex gap-3 z-20" aria-hidden="false"></div>
</section>

<!-- FEATURES -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-16 relative z-10" aria-label="Store features">
    <div class="grid grid-cols-2 md:grid-cols-4 bg-white p-6 md:p-8 rounded-xl shadow-2xl border border-gray-100 divide-x divide-gray-200">
        <div class="flex flex-col items-center justify-center text-center p-3">
            <div class="text-[var(--primary)] mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 md:w-10 md:h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h13v6H3z"/><path d="M16 8h5l-1.5 4.5"/><circle cx="7.5" cy="18.5" r="1.5"/><circle cx="18.5" cy="18.5" r="1.5"/></svg>
            </div>
            <p class="font-bold text-gray-800 text-sm md:text-base">Quick Delivery</p>
            <p class="text-gray-500 text-xs md:text-sm">Fast Shipping Available</p>
        </div>

        <div class="flex flex-col items-center justify-center text-center p-3">
            <div class="text-[var(--primary)] mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 md:w-10 md:h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M7 7V5a5 5 0 0 1 10 0v2"/></svg>
            </div>
            <p class="font-bold text-gray-800 text-sm md:text-base">Payment Secure</p>
            <p class="text-gray-500 text-xs md:text-sm">100% Payment Safe</p>
        </div>

        <div class="flex flex-col items-center justify-center text-center p-3">
            <div class="text-[var(--primary)] mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 md:w-10 md:h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16 5.5 20l2-7L2 9h7z"/></svg>
            </div>
            <p class="font-bold text-gray-800 text-sm md:text-base">Quality Assurance</p>
            <p class="text-gray-500 text-xs md:text-sm">Guaranteed Top-Quality Support</p>
        </div>

        <div class="flex flex-col items-center justify-center text-center p-3">
            <div class="text-[var(--primary)] mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-8 h-8 md:w-10 md:h-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a4 4 0 0 1-4 4H7l-4 2V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <p class="font-bold text-gray-800 text-sm md:text-base">Premium Service</p>
            <p class="text-gray-500 text-xs md:text-sm">Top quality 24/7 Support</p>
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
                    $cat_name = htmlspecialchars($cat['name']);
                    $cat_desc = htmlspecialchars($cat['description']);
                    $cat_img = !empty($cat['image_url']) ? htmlspecialchars($cat['image_url']) : 'https://placehold.co/600x480/7b3f00/f7e0c4?text=No+Image';
                    $cat_slug = rawurlencode(strtolower(str_replace(' ', '_', $cat_name)));
                    $mobile_class = ($cat_name === 'Lace') ? 'mobile-perfect' : '';
            ?>
                <a href="category.php?id=<?php echo $cat_id ?>&slug=<?php echo $cat_slug ?>" class="category-card" aria-label="<?php echo $cat_name ?>">
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

<!-- MEN'S WIGS (PROMO STYLE CARDS) -->
<section id="men" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 bg-[var(--secondary)] rounded-xl shadow-xl">
    <h2 class="text-3xl sm:text-4xl font-bold text-[var(--primary)] mb-8 text-center border-b-2 border-[var(--primary)] pb-3 max-w-md mx-auto">Featured Men's Wigs</h2>

    <!-- Use responsive grid for products; on mobile it's two columns, on desktop 3 -->
    <div class="grid gap-6 grid-cols-2 sm:grid-cols-2 md:grid-cols-3">
        <?php
        if ($men_products_result && $men_products_result->num_rows > 0):
            while ($p = $men_products_result->fetch_assoc()):
                $pid = (int)$p['id'];
                $pname = htmlspecialchars($p['name']);
                $pdesc = htmlspecialchars($p['description']);
                $pprice = number_format((float)$p['price'], 2);
                $pimg = !empty($p['image_placeholder']) ? htmlspecialchars($p['image_placeholder']) : 'https://placehold.co/800x600/7b3f00/f7e0c4?text=Wig+Placeholder';
        ?>
            <a href="product_details.php?id=<?php echo $pid ?>" class="promo-card" aria-label="<?php echo $pname ?>">
                <img src="<?php echo $pimg ?>" alt="<?php echo $pname ?>" class="promo-thumb" onerror="this.onerror=null;this.src='https://placehold.co/160x160/ffffff/000000?text=Img';">
                <div class="promo-meta">
                    <div class="promo-name"><?php echo $pname ?></div>
                    <div class="promo-price">৳ <?php echo $pprice ?></div>
                    <div class="text-sm opacity-90"><?php echo $pdesc ?></div>
                </div>
                <div style="flex:0 0 auto; display:flex; align-items:center;">
                    <button class="promo-quick add-to-cart"
                        data-product-id="<?php echo $pid ?>"
                        data-product-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                        data-product-price="<?php echo (float)$p['price'] ?>"
                        data-product-img="<?php echo $pimg ?>"
                    >Quick Add</button>
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
        <a href="#" class="inline-flex items-center px-6 py-2 border-2 border-[var(--primary)] text-[var(--primary)] rounded-full hover:bg-[var(--primary)] hover:text-white transition">View All Men's Styles</a>
    </div>
</section>

<!-- WOMEN'S WIGS (PROMO STYLE CARDS) -->
<section id="women" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 mt-12">
    <h2 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-8 text-center border-b-2 border-gray-800 pb-3 max-w-md mx-auto">Stunning Women's Wigs</h2>

    <div class="grid gap-6 grid-cols-2 sm:grid-cols-2 md:grid-cols-3">
        <?php
        if ($women_products_result && $women_products_result->num_rows > 0):
            while ($p = $women_products_result->fetch_assoc()):
                $pid = (int)$p['id'];
                $pname = htmlspecialchars($p['name']);
                $pdesc = htmlspecialchars($p['description']);
                $pprice = number_format((float)$p['price'], 2);
                $pimg = !empty($p['image_placeholder']) ? htmlspecialchars($p['image_placeholder']) : 'https://placehold.co/800x600/7b3f00/f7e0c4?text=Wig+Placeholder';
        ?>
            <a href="product_details.php?id=<?php echo $pid ?>" class="promo-card" aria-label="<?php echo $pname ?>">
                <img src="<?php echo $pimg ?>" alt="<?php echo $pname ?>" class="promo-thumb" onerror="this.onerror=null;this.src='https://placehold.co/160x160/ffffff/000000?text=Img';">
                <div class="promo-meta">
                    <div class="promo-name"><?php echo $pname ?></div>
                    <div class="promo-price">৳ <?php echo $pprice ?></div>
                    <div class="text-sm opacity-90"><?php echo $pdesc ?></div>
                </div>
                <div style="flex:0 0 auto; display:flex; align-items:center;">
                    <button class="promo-quick add-to-cart"
                        data-product-id="<?php echo $pid ?>"
                        data-product-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES) ?>"
                        data-product-price="<?php echo (float)$p['price'] ?>"
                        data-product-img="<?php echo $pimg ?>"
                    >Quick Add</button>
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
        <a href="#" class="inline-flex items-center px-6 py-2 border-2 border-gray-800 text-gray-800 rounded-full hover:bg-gray-800 hover:text-white transition">View All Women's Styles</a>
    </div>
</section>

<!-- ABOUT / FACTORY -->
<section id="about" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 bg-white rounded-xl shadow-lg mb-12">
    <div class="grid gap-8 md:grid-cols-2 items-center">
        <div>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-gray-900 mb-4">ABOUT</h2>
            <h3 class="text-2xl font-bold text-[var(--primary)] mb-4">YOUR RELIABLE HAIRPIECE FACTORY AND HUMAN HAIR WIG SUPPLIERS</h3>
            <p class="text-gray-600 mb-6">
                Orme Hair™ is a Bangladesh-based manufacturer offering one-stop solutions for hair extensions and wigs.
                We specialise in sourcing, manufacturing and global supply of premium human hair and synthetic hairpieces.
            </p>

            <div class="grid gap-2 text-gray-700">
                <p><strong>We provide</strong>: tape hair extension; clip-in hair extension; keratin pre-bonded hair extension; micro-ring hair extension; halo hair extension; lace wig with mono top; Jewish wig; full lace wig; frontal lace wig; lace closure; lace frontal.</p>
                <p class="mt-3 text-sm text-gray-500">Ethical sourcing, strict quality control and flexible stock options for retailers, salons and private-label brands worldwide.</p>
            </div>

            <div class="mt-6 flex flex-wrap gap-4">
                <a href="#categories" class="inline-flex items-center px-5 py-2 bg-[var(--primary)] text-white font-semibold rounded-full shadow hover:opacity-95 transition">Explore Products</a>
                <a href="contact.php" class="inline-flex items-center px-5 py-2 border border-gray-300 text-gray-700 rounded-full hover:bg-gray-50 transition">Contact Sales</a>
            </div>
        </div>

        <div class="space-y-4">
            <div class="bg-gray-50 rounded-lg p-4 flex items-start gap-4">
                <img src="assets/factory-1.jpg" alt="Factory shelf and wigs" class="w-28 h-20 object-cover rounded-md shadow-sm" onerror="this.onerror=null;this.src='https://placehold.co/400x300/7b3f00/f7e0c4?text=Image'">
                <div>
                    <p class="font-semibold text-gray-800">Factory & Warehouse</p>
                    <p class="text-sm text-gray-500">On-site quality checks and organised stock for fast order fulfilment.</p>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 flex items-start gap-4">
                <img src="assets/factory-2.jpg" alt="Wig production" class="w-28 h-20 object-cover rounded-md shadow-sm" onerror="this.onerror=null;this.src='https://placehold.co/400x300/7b3f00/f7e0c4?text=Image'">
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

<!-- FOOTER -->
<footer class="bg-gray-800 text-white py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-2 md:grid-cols-4 gap-8">
        <div>
            <h4 class="text-xl font-bold text-[var(--secondary)] mb-3">Ali Hair Wigs</h4>
            <p class="text-gray-400 text-sm">Premium wigs and hairpieces for every style and occasion.</p>
        </div>
        <div>
            <h4 class="text-lg font-semibold mb-3">Quick Links</h4>
            <ul class="space-y-2 text-gray-400 text-sm">
                <li><a href="#" class="hover:text-[var(--secondary)]">Our Mission</a></li>
                <li><a href="#" class="hover:text-[var(--secondary)]">FAQ</a></li>
                <li><a href="#" class="hover:text-[var(--secondary)]">Shipping & Returns</a></li>
                <li><a href="#" class="hover:text-[var(--secondary)]">Privacy Policy</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-lg font-semibold mb-3">Customer Care</h4>
            <ul class="space-y-2 text-gray-400 text-sm">
                <li><a href="#" class="hover:text-[var(--secondary)]">Contact Us</a></li>
                <li><a href="#" class="hover:text-[var(--secondary)]">Sizing Guide</a></li>
                <li><a href="#" class="hover:text-[var(--secondary)]">Wig Care Tips</a></li>
                <li><a href="login.php" class="hover:text-[var(--secondary)]">My Account</a></li>
            </ul>
        </div>
        <div>
            <h4 class="text-lg font-semibold mb-3">Connect</h4>
            <div class="flex space-x-4 text-2xl mb-4">
                <a href="#" class="text-gray-400 hover:text-blue-500" aria-label="Facebook">📘</a>
                <a href="#" class="text-gray-400 hover:text-pink-500" aria-label="Instagram">📸</a>
                <a href="#" class="text-gray-400 hover:text-cyan-400" aria-label="Twitter/X">🐦</a>
            </div>
            <p class="text-sm text-gray-400">Email: info@wigstudio.com</p>
        </div>
    </div>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8 border-t border-gray-700 pt-4 text-center">
        <p class="text-sm text-gray-500">&copy; <?php echo date('Y'); ?> Ali Hair Wigs. All rights reserved.</p>
    </div>
</footer>

<!-- CART DRAWER MARKUP -->
<div id="cart-backdrop" class="cart-backdrop" aria-hidden="true"></div>

<aside id="cart-drawer" class="cart-drawer" aria-hidden="true" role="dialog" aria-label="Shopping cart">
    <div class="p-4 border-b border-gray-200 flex items-center justify-between">
        <div class="text-lg font-semibold">Your Cart</div>
        <div class="flex items-center gap-2">
            <button id="cart-clear" class="text-sm text-gray-600 hover:underline">Clear</button>
            <button id="cart-close" aria-label="Close cart" class="p-2 rounded-md hover:bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-700" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    <div id="cart-items" class="p-4 overflow-auto" style="flex:1 1 auto;">
        <!-- items injected by JS; container uses .cart-grid to show multiple items per row on larger screens -->
        <div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>
    </div>

    <div class="p-4 border-t border-gray-200">
        <div class="flex items-center justify-between mb-3">
            <div class="text-sm text-gray-600">Subtotal</div>
            <div id="cart-subtotal" class="text-lg font-bold text-[var(--primary)]">$0.00</div>
        </div>
        <div class="flex gap-3">
            <a href="checkout.php" id="checkout-btn" class="flex-1 inline-flex justify-center items-center px-4 py-2 bg-[var(--primary)] text-white rounded-md font-semibold">Checkout</a>
            <button id="continue-shopping" class="flex-1 px-4 py-2 border border-gray-300 rounded-md">Continue</button>
        </div>
    </div>
</aside>

<!-- SCRIPTS -->
<script>
/* Mobile left drawer + cart drawer + client-side cart */
(function() {
    const hamburger = document.getElementById('hamburger');
    const mobileLeft = document.getElementById('mobile-left-drawer');
    const mobileLeftClose = document.getElementById('mobile-left-close');

    const cartToggle = document.getElementById('cart-toggle');
    const cartDrawer = document.getElementById('cart-drawer');
    const cartBackdrop = document.getElementById('cart-backdrop');
    const cartClose = document.getElementById('cart-close');
    const cartItemsEl = document.getElementById('cart-items');
    const cartCountEl = document.getElementById('cart-count');
    const cartSubtotalEl = document.getElementById('cart-subtotal');
    const cartClearBtn = document.getElementById('cart-clear');
    const continueBtn = document.getElementById('continue-shopping');

    const openIcon = document.getElementById('hamburger-open');
    const closeIcon = document.getElementById('hamburger-close');

    // Mobile left drawer controls
    function openLeft() {
        mobileLeft.classList.add('open');
        mobileLeft.setAttribute('aria-hidden', 'false');
        if (openIcon) openIcon.classList.add('hidden');
        if (closeIcon) closeIcon.classList.remove('hidden');
        hamburger.setAttribute('aria-expanded','true');
        // focus first link
        setTimeout(()=> mobileLeft.querySelector('a,button')?.focus(), 80);
    }
    function closeLeft() {
        mobileLeft.classList.remove('open');
        mobileLeft.setAttribute('aria-hidden', 'true');
        if (openIcon) openIcon.classList.remove('hidden');
        if (closeIcon) closeIcon.classList.add('hidden');
        hamburger.setAttribute('aria-expanded','false');
        hamburger.focus();
    }
    if (hamburger) hamburger.addEventListener('click', () => {
        if (mobileLeft.classList.contains('open')) closeLeft();
        else openLeft();
    });
    if (mobileLeftClose) mobileLeftClose.addEventListener('click', closeLeft);

    // Use localStorage to persist cart across reloads
    const STORAGE_KEY = 'ali_hair_cart_v1';
    let cart = JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}');

    function saveCart() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(cart));
    }

    function getTotalItems() {
        return Object.values(cart).reduce((s, it) => s + (it.qty || 0), 0);
    }

    function getSubtotal() {
        return Object.values(cart).reduce((s, it) => s + ((it.price || 0) * (it.qty || 0)), 0);
    }

    function formatCurrency(v) {
        return '$' + v.toFixed(2);
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function(m) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[m]; });
    }

    function renderCart() {
        const items = Object.entries(cart);
        cartCountEl.textContent = getTotalItems();

        if (!items.length) {
            cartItemsEl.innerHTML = '<div id="cart-empty" class="text-center text-gray-500 py-12">Your cart is empty.</div>';
            cartSubtotalEl.textContent = formatCurrency(0);
            return;
        }

        // grid container so multiple cart items can display in same row on wider screens
        const container = document.createElement('div');
        container.className = 'cart-grid';

        items.forEach(([id, it]) => {
            const row = document.createElement('div');
            row.className = 'cart-row';

            const productLink = document.createElement('a');
            productLink.className = 'cart-link';
            productLink.href = `product_details.php?id=${encodeURIComponent(id)}`;
            productLink.setAttribute('aria-label', `View ${escapeHtml(it.name)}`);

            const img = document.createElement('img');
            img.className = 'cart-item-img';
            img.src = it.img || 'https://placehold.co/80x80/7b3f00/f7e0c4?text=Img';
            img.alt = escapeHtml(it.name);
            productLink.appendChild(img);

            const meta = document.createElement('div');
            meta.className = 'cart-meta';
            const nameEl = document.createElement('div');
            nameEl.className = 'cart-name';
            nameEl.innerHTML = escapeHtml(it.name);
            const subEl = document.createElement('div');
            subEl.className = 'cart-sub';
            subEl.textContent = `${formatCurrency(it.price)} each`;
            meta.appendChild(nameEl);
            meta.appendChild(subEl);
            productLink.appendChild(meta);

            const actions = document.createElement('div');
            actions.className = 'cart-actions';

            const qtyWrap = document.createElement('div');
            qtyWrap.className = 'cart-qty-controls inline-flex items-center';
            const decBtn = document.createElement('button');
            decBtn.type = 'button';
            decBtn.className = 'px-2 py-1 border rounded text-sm';
            decBtn.textContent = '−';
            decBtn.dataset.action = 'decrease';
            decBtn.dataset.id = id;

            const qtyBadge = document.createElement('div');
            qtyBadge.className = 'px-3 py-1 border rounded text-sm bg-gray-50';
            qtyBadge.textContent = it.qty;

            const incBtn = document.createElement('button');
            incBtn.type = 'button';
            incBtn.className = 'px-2 py-1 border rounded text-sm';
            incBtn.textContent = '+';
            incBtn.dataset.action = 'increase';
            incBtn.dataset.id = id;

            qtyWrap.appendChild(decBtn);
            qtyWrap.appendChild(qtyBadge);
            qtyWrap.appendChild(incBtn);

            const priceEl = document.createElement('div');
            priceEl.className = 'cart-price';
            priceEl.textContent = formatCurrency(it.price * it.qty);

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'text-xs text-gray-500 mt-2 hover:underline';
            removeBtn.textContent = 'Remove';
            removeBtn.dataset.action = 'remove';
            removeBtn.dataset.id = id;

            const quickBtn = document.createElement('a');
            quickBtn.className = 'cart-quick';
            quickBtn.href = `product_details.php?id=${encodeURIComponent(id)}`;
            quickBtn.textContent = 'View';
            quickBtn.setAttribute('role', 'button');

            actions.appendChild(priceEl);
            actions.appendChild(qtyWrap);
            actions.appendChild(removeBtn);
            actions.appendChild(quickBtn);

            row.appendChild(productLink);
            row.appendChild(actions);

            container.appendChild(row);

            [decBtn, incBtn, removeBtn].forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    const action = btn.getAttribute('data-action');
                    const pid = btn.getAttribute('data-id');
                    if (!cart[pid]) return;
                    if (action === 'increase') { cart[pid].qty += 1; }
                    else if (action === 'decrease') { cart[pid].qty = Math.max(1, cart[pid].qty - 1); }
                    else if (action === 'remove') { delete cart[pid]; }
                    saveCart(); renderCart();
                });
            });
        });

        cartItemsEl.innerHTML = '';
        cartItemsEl.appendChild(container);
        cartSubtotalEl.textContent = formatCurrency(getSubtotal());
    }

    function openCart() {
        cartDrawer.classList.add('open');
        cartBackdrop.classList.add('visible');
        cartDrawer.setAttribute('aria-hidden', 'false');
        cartBackdrop.setAttribute('aria-hidden', 'false');
        renderCart();
        setTimeout(() => cartDrawer.querySelector('[data-action], #checkout-btn')?.focus(), 200);
    }
    function closeCart() {
        cartDrawer.classList.remove('open');
        cartBackdrop.classList.remove('visible');
        cartDrawer.setAttribute('aria-hidden', 'true');
        cartBackdrop.setAttribute('aria-hidden', 'true');
    }

    if (cartToggle) cartToggle.addEventListener('click', () => {
        if (cartDrawer.classList.contains('open')) closeCart();
        else openCart();
    });
    if (cartClose) cartClose.addEventListener('click', closeCart);
    if (cartBackdrop) cartBackdrop.addEventListener('click', closeCart);
    if (continueBtn) continueBtn.addEventListener('click', closeCart);

    if (cartClearBtn) cartClearBtn.addEventListener('click', () => {
        if (confirm('Clear all items from your cart?')) {
            cart = {}; saveCart(); renderCart();
        }
    });

    function addToCartFromButton(btn) {
        const id = String(btn.getAttribute('data-product-id'));
        const name = btn.getAttribute('data-product-name') || 'Product';
        const price = parseFloat(btn.getAttribute('data-product-price')) || 0;
        const img = btn.getAttribute('data-product-img') || '';
        if (!cart[id]) cart[id] = { id, name, price, img, qty: 0 };
        cart[id].qty += 1;
        saveCart();
        renderCart();
        openCart();
    }

    document.querySelectorAll('.add-to-cart').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            addToCartFromButton(btn);
        });
    });

    renderCart();
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { closeCart(); closeLeft(); } });

    // Light focus management: close mobile left drawer when clicking outside on desktop-sized screens
    document.addEventListener('click', function(e) {
        if (!mobileLeft.contains(e.target) && !hamburger.contains(e.target) && mobileLeft.classList.contains('open')) {
            closeLeft();
        }
    });
})();
</script>

<!-- HERO SLIDER -->
<script>
(function() {
    const slides = Array.from(document.querySelectorAll('.hero-slide'));
    const dotsContainer = document.getElementById('slider-dots');
    if (!slides.length) return;
    let current = 0, interval = null, slideDuration = 5000;
    function createDots() {
        dotsContainer.innerHTML = '';
        slides.forEach((_, idx) => {
            const btn = document.createElement('button');
            btn.className = 'slider-dot';
            btn.setAttribute('aria-label', `Go to slide ${idx+1}`);
            if (idx === 0) btn.classList.add('active');
            btn.addEventListener('click', () => goTo(idx));
            dotsContainer.appendChild(btn);
        });
    }
    function update() {
        slides.forEach((s,i) => s.classList.toggle('active', i === current));
        Array.from(dotsContainer.children).forEach((d,i) => d.classList.toggle('active', i === current));
    }
    function next() { current = (current + 1) % slides.length; update(); }
    function goTo(i) { if (i < 0 || i >= slides.length) return; current = i; update(); resetInterval(); }
    function resetInterval() { if (interval) clearInterval(interval); interval = setInterval(next, slideDuration); }
    document.addEventListener('DOMContentLoaded', function(){ createDots(); update(); resetInterval(); });
})();
</script>

<!-- CATEGORY CAROUSEL -->
<script>
(function() {
    const carousel = document.getElementById('category-carousel');
    if (!carousel) return;
    const nextBtn = document.getElementById('cat-next');
    const prevBtn = document.getElementById('cat-prev');

    function getStep() {
        const card = carousel.querySelector('.category-card');
        if (!card) return Math.round(carousel.clientWidth * 0.8);
        const gap = parseFloat(getComputedStyle(carousel).gap || 16);
        return Math.round(card.getBoundingClientRect().width + gap);
    }

    const AUTO_INTERVAL = 3000;
    let autoHandle = null;
    let touching = false;

    function scrollNext() {
        const step = getStep();
        carousel.scrollBy({ left: step, behavior: 'smooth' });
        setTimeout(() => {
            if (carousel.scrollLeft + carousel.clientWidth >= carousel.scrollWidth - 10) {
                carousel.scrollTo({ left: 0, behavior: 'smooth' });
            }
        }, 500);
    }

    function scrollPrev() {
        const step = getStep();
        if (carousel.scrollLeft <= 0) carousel.scrollTo({ left: carousel.scrollWidth, behavior: 'instant' });
        carousel.scrollBy({ left: -step, behavior: 'smooth' });
    }

    function startAuto() { stopAuto(); autoHandle = setInterval(scrollNext, AUTO_INTERVAL); }
    function stopAuto() { if (autoHandle) { clearInterval(autoHandle); autoHandle = null; } }

    if (nextBtn) nextBtn.addEventListener('click', () => { scrollNext(); stopAuto(); startAuto(); });
    if (prevBtn) prevBtn.addEventListener('click', () => { scrollPrev(); stopAuto(); startAuto(); });

    carousel.addEventListener('mouseenter', stopAuto);
    carousel.addEventListener('mouseleave', startAuto);
    carousel.addEventListener('focusin', stopAuto);
    carousel.addEventListener('focusout', startAuto);

    let startX = 0;
    carousel.addEventListener('touchstart', (e) => { stopAuto(); touching = true; startX = e.touches[0].clientX; }, { passive: true });
    carousel.addEventListener('touchmove', (e) => { if (!touching) return; const dx = startX - e.touches[0].clientX; carousel.scrollLeft += dx; startX = e.touches[0].clientX; }, { passive: true });
    carousel.addEventListener('touchend', () => {
        touching = false;
        const step = getStep();
        const offset = carousel.scrollLeft % step;
        if (offset !== 0) {
            if (offset > step / 2) carousel.scrollBy({ left: step - offset, behavior: 'smooth' });
            else carousel.scrollBy({ left: -offset, behavior: 'smooth' });
        }
        startAuto();
    });

    carousel.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowRight') { e.preventDefault(); scrollNext(); stopAuto(); startAuto(); }
        if (e.key === 'ArrowLeft')  { e.preventDefault(); scrollPrev(); stopAuto(); startAuto(); }
    });

    document.addEventListener('DOMContentLoaded', startAuto);
})();
</script>

<!-- MOBILE SLIDER IMAGE SWAP -->
<script>
(function() {
    const BREAKPOINT = 640;
    let resizeTimer = null;

    function applyMobileImages() {
        const isMobile = window.innerWidth <= BREAKPOINT;
        document.querySelectorAll('.hero-slide').forEach(slide => {
            const desktopBg = slide.getAttribute('data-desktop-bg') || slide.style.backgroundImage;
            const mobileUrl = slide.getAttribute('data-mobile-image') || '';
            if (!slide.getAttribute('data-desktop-bg')) slide.setAttribute('data-desktop-bg', desktopBg);

            if (isMobile && mobileUrl) {
                const wrapper = mobileUrl.trim().startsWith('url(') ? mobileUrl : `url('${mobileUrl}')`;
                slide.style.backgroundImage = wrapper;
            } else {
                const stored = slide.getAttribute('data-desktop-bg');
                if (stored) slide.style.backgroundImage = stored;
            }
        });
    }

    window.addEventListener('DOMContentLoaded', applyMobileImages);
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(applyMobileImages, 120);
    });
})();
</script>

</body>
</html>
<?php if ($conn) $conn->close(); ?>
