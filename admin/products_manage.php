<?php
declare(strict_types=1);
session_start();

// Require authenticated session
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Session hardening
if (!isset($_SESSION['CREATED'])) $_SESSION['CREATED'] = time();
elseif (time() - $_SESSION['CREATED'] > 3600) { session_regenerate_id(true); $_SESSION['CREATED'] = time(); }
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) { session_unset(); session_destroy(); header('Location: login.php'); exit; }
$_SESSION['LAST_ACTIVITY'] = time();

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Database connection (adjust if needed)
$servername = "localhost";
$username = "alihairw";
$password = "x5.H(8xkh3H7EY";
$dbname = "alihairw_alihairwigs";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . esc($conn->connect_error));
}
$conn->set_charset('utf8mb4');

// Fetch all products
$sql = "SELECT id, name, description, price, image_placeholder, category FROM products ORDER BY id DESC";
$result = $conn->query($sql);

$user_name  = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@example.com';

// Helper: detect absolute URL
function is_absolute_url(string $s): bool {
    return (bool)preg_match('#^https?://#i', $s);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Manage Products • Ali Hair Wigs</title>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
    :root{
      --bg:#f5f7fb; --panel:#ffffff; --muted:#6b7280; --accent:#0ea5b6; --accent-dark:#0596a6; --text:#071026; --glass: rgba(11,18,34,0.04);
      --card-shadow: 0 6px 18px rgba(11,18,34,0.03);
      font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;
    }
    [data-theme="dark"]{
      --bg:#071427; --panel:linear-gradient(180deg,#071827,#0a2130); --panel-solid:#061424; --muted:#9aa4b2; --accent:#4dd0c8; --accent-dark:#1fb7ac; --text:#e6eef6; --glass: rgba(255,255,255,0.04); --card-shadow: 0 8px 24px rgba(2,8,15,0.6);
    }
    html,body{height:100%;margin:0;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
    .wrap{max-width:1200px;margin:0 auto;padding:0 12px 32px;display:block}
    .page-inner{display:block;max-width:1200px;margin:16px auto;padding:12px;box-sizing:border-box}

    /* Sidebar */
    .sidebar { position:fixed; left:16px; top:16px; bottom:16px; width:250px; padding:14px; border-radius:12px; background: var(--panel); border:1px solid var(--glass); overflow:auto; box-shadow:var(--card-shadow); color:var(--text); z-index:120; }
    header.header { position:sticky; top:16px; margin-left: calc(250px + 32px); margin-right: 16px; z-index:115; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 16px; border-radius:10px; background:linear-gradient(90deg,rgba(255,255,255,0.98),rgba(250,250,252,0.98)); border:1px solid var(--glass); box-shadow: 0 6px 18px rgba(11,18,34,0.04); backdrop-filter: blur(6px); height:64px; box-sizing:border-box; }
    [data-theme="dark"] header.header{ background: linear-gradient(90deg, rgba(6,24,34,0.92), rgba(6,24,34,0.88)); border-color: rgba(255,255,255,0.04); box-shadow: 0 6px 18px rgba(0,0,0,0.6); }

    .hdr-left{display:flex;gap:12px;align-items:center;min-width:0}
    .brand{display:flex;gap:12px;align-items:center;min-width:0}
    .logo{width:44px;height:44;border-radius:8px;background:linear-gradient(135deg,var(--accent-dark),var(--accent));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;flex:0 0 44px}
    .site-title{font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .site-sub{font-size:12px;color:var(--muted);white-space:nowrap}

    .hdr-actions{display:flex;gap:8px;align-items:center;flex:0 0 auto}
    .btn{padding:8px 12px;border-radius:10px;border:0;background:var(--accent);color:#042b2a;font-weight:700;cursor:pointer}
    .icon-btn{background:transparent;border:0;padding:8px;border-radius:8px;cursor:pointer;color:var(--muted);font-size:16px;display:inline-flex;align-items:center;justify-content:center}

    @media (max-width:960px){
      .sidebar{transform:translateX(-110%);width:280px;border-radius:0;top:0;bottom:0;left:0}
      .sidebar.open{transform:translateX(0)}
      header.header{position:fixed;left:0;right:0;top:0;margin-left:0;margin-right:0;height:56px}
      .page-inner{padding-top:72px}
      .site-sub{display:none}
      .mobile-menu-btn{display:inline-flex}
    }

    main.main { margin-left: calc(250px + 32px); padding:12px 0 40px; }
    footer.footer { margin-left: calc(250px + 32px); padding:12px 0 24px; color:var(--muted); text-align:center; }
    @media (max-width:960px){ main.main, footer.footer { margin-left:0; } }

    .nav-group{margin-top:6px}
    .nav-title{font-size:12px;color:var(--muted);text-transform:uppercase;margin-bottom:8px}
    .nav{display:flex;flex-direction:column;gap:6px}
    .nav a, .nav button.menu-item{display:flex;justify-content:space-between;align-items:center;padding:10px;border-radius:8px;text-decoration:none;color:inherit;border:1px solid transparent;background:transparent;cursor:pointer;font-weight:700}
    .submenu{display:none;flex-direction:column;margin-left:8px;margin-top:6px;gap:6px}
    .sidebar .footer{margin-top:auto;font-size:13px;color:var(--muted)}

    /* Table styles */
    .card { max-width:100%; background:var(--panel); border-radius:12px; padding:18px; box-shadow:var(--card-shadow); border:1px solid var(--glass); }
    table { width:100%; border-collapse:collapse; background:transparent; }
    th, td { padding:12px; border-bottom:1px solid rgba(11,18,34,0.06); text-align:center; vertical-align:middle; }
    th { background: linear-gradient(90deg, rgba(0,0,0,0.06), rgba(0,0,0,0.02)); color:var(--text); font-weight:700; }
    tr:hover { background: rgba(11,18,34,0.02); }
    img.prod-thumb { width:100px; height:auto; border-radius:6px; border:1px solid rgba(11,18,34,0.06); }

    .action-btn { padding:6px 10px; border-radius:6px; text-decoration:none; color:#fff; display:inline-block; }
    .edit { background:#28a745; }
    .delete { background:#dc3545; }
    .add-btn { background:var(--accent); color:#042b2a; padding:8px 14px; border-radius:6px; text-decoration:none; display:inline-block; margin-bottom:12px; }

    .muted { color:var(--muted); font-size:13px; }

    @media (max-width:900px){
      th, td { padding:8px; font-size:13px; }
      img.prod-thumb { width:80px; }
    }
    </style>
</head>
<body data-theme="">
  <div class="wrap">
    <aside class="sidebar" id="sidebar" aria-label="Sidebar navigation">
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px">
        <div class="logo" style="width:40px;height:40;border-radius:8px;font-weight:800">AH</div>
        <div>
          <div style="font-weight:800">Ali Hair Wigs</div>
          <div style="font-size:12px;color:var(--muted)">Control Center</div>
        </div>
      </div>

      <div class="nav-group">
        <div class="nav-title">Navigation</div>
        <nav class="nav" role="navigation" aria-label="Primary">
          <a href="admin_dashboard.php" class="active">Overview <span style="color:var(--muted);font-size:13px">Home</span></a>

          <button class="menu-item" data-target="products-sub" aria-expanded="false">Products <span style="color:var(--muted);font-size:13px">Catalog ▾</span></button>
          <div class="submenu" id="products-sub" aria-hidden="true">
            <a href="add_product.php">Add product</a>
            <a href="products_manage.php">Manage products</a>
          </div>

          <button class="menu-item" data-target="categories-sub" aria-expanded="false">Categories <span style="color:var(--muted);font-size:13px">Structure ▾</span></button>
          <div class="submenu" id="categories-sub" aria-hidden="true">
            <a href="add_product_category.php">Add Products categories</a>
       
          </div>

          <a href="sliderimages.php">Home Slider <span style="color:var(--muted);font-size:13px">Manage</span></a>

          <button class="menu-item" data-target="orders-sub" aria-expanded="false">Orders <span style="color:var(--muted);font-size:13px">All ▾</span></button>
          <div class="submenu" id="orders-sub" aria-hidden="true">
            <a href="orders.php">All orders</a>
            <a href="orders_pending.php">Pending orders</a>
          </div>

          <a href="customers.php">Customers <span style="color:var(--muted);font-size:13px">Manage</span></a>
          <a href="reviews.php">Reviews <span style="color:var(--muted);font-size:13px">Customers</span></a>
           <a href="admin_messages.php">Messages <span style="color:var(--muted);font-size:13px">Reply Need</span></a>
        </nav>
      </div>

      <div style="margin-top:auto" class="footer">
        <a class="btn" href="add_product.php" style="display:block;text-align:center;margin-bottom:8px">+ New product</a>
        <a href="logout.php" class="btn" style="display:block;text-align:center;margin-bottom:8px;background:#fff;color:var(--text);border:1px solid var(--glass);" onclick="return confirm('Sign out?');">Logout</a>

        <div style="margin-top:12px;font-size:13px;color:var(--muted)">
          Signed in as <strong><?php echo esc($user_name); ?></strong><br><?php echo esc($user_email); ?>
        </div>
      </div>
    </aside>

    <header class="header" role="banner" aria-label="Top bar">
      <div class="hdr-left">
        <button class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Open menu" title="Open menu" style="display:none">☰</button>
        <div class="brand" aria-hidden="false">
          <div class="logo" aria-hidden="true" style="width:40px;height:40">AH</div>
          <div style="min-width:0">
            <div class="site-title">Ali Hair Wigs</div>
            <div class="site-sub">Admin Console</div>
          </div>
        </div>
      </div>

      <div class="hdr-actions" role="toolbar" aria-label="Header actions">
        <button id="themeBtn" class="icon-btn" title="Toggle theme" aria-pressed="false" aria-label="Toggle dark mode">🌓</button>
        <a href="logout.php" class="icon-btn" title="Sign out" aria-label="Sign out" onclick="return confirm('Sign out?');">⎋</a>
      </div>
    </header>

    <div class="page-inner">
      <main class="main" role="main" aria-live="polite">
        <div style="height:8px"></div>

        <div class="card" role="region" aria-labelledby="manageProductsTitle">
          <h2 id="manageProductsTitle" style="margin:0 0 12px;">Manage Products</h2>
          <a href="add_product.php" class="add-btn">➕ Add New Product</a>

          <div style="overflow-x:auto">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Image</th>
                  <th>Name</th>
                  <th>Description</th>
                  <th>Price (৳)</th>
                  <th>Category</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
              <?php
              if ($result && $result->num_rows > 0) {
                  while ($row = $result->fetch_assoc()) {
                      // Raw value from DB
                      $imageField = trim((string)$row['image_placeholder']);

                      // Default fallback (public path used in <img src>)
                      $publicPath = 'admin/images/no-image.png';

                      if ($imageField !== '') {
                          // 1) If stored as JSON array, pick first element
                          $decoded = json_decode($imageField, true);
                          if (json_last_error() === JSON_ERROR_NONE && is_array($decoded) && !empty($decoded[0])) {
                              $candidateRaw = (string)$decoded[0];
                          } else {
                              $candidateRaw = $imageField;
                          }

                          $candidateRaw = trim($candidateRaw);

                          // 2) If it's already an absolute URL - use it directly
                          if ($candidateRaw !== '' && is_absolute_url($candidateRaw)) {
                              $publicPath = $candidateRaw;
                          } else {
                              // 3) Normalize candidate and build sensible local candidates
                              $candidateRaw = preg_replace('#^(\./|/)+#', '', $candidateRaw); // remove leading ./ or /
                              $baseName = basename($candidateRaw);

                              $candidates = [
                                  $candidateRaw,                        // exact stored value (e.g., "admin/images/file.jpg" or "images/file.jpg" or "admin/admin/images/file.jpg")
                                  'admin/' . ltrim($candidateRaw, '/\\'), // admin/ + stored
                                  'admin/images/' . $baseName,         // admin/images/filename.ext
                                  'admin/admin/images/' . $baseName,  // admin/admin/images/filename.ext (older path some handlers saved)
                                  'images/' . $baseName,               // images/filename.ext
                                  'uploads/' . $baseName                // uploads/filename.ext
                              ];

                              // Prevent directory traversal: ensure candidate path remains within project dir after normalization
                              foreach ($candidates as $cand) {
                                  $candNorm = ltrim($cand, '/\\');
                                  $fsPath = __DIR__ . '/' . $candNorm;
                                  if (file_exists($fsPath) && is_file($fsPath)) {
                                      $publicPath = $candNorm;
                                      break;
                                  }
                              }

                              // 4) If none of the local candidates matched but the stored value looks like a filename only, try to use it as public path
                              if ($publicPath === 'admin/images/no-image.png' && $candidateRaw !== '') {
                                  // If candidateRaw already looks like a relative public path we can try to use it (it may be served by web server)
                                  $publicPath = $candidateRaw;
                              }
                          }
                      }

                      echo '<tr>';
                      echo '<td>' . esc((string)$row['id']) . '</td>';

                      // Use esc() but avoid double-escaping full URLs that include query strings by encoding as attribute safely
                      $imgSrc = esc($publicPath);
                      echo '<td><img class="prod-thumb" src="' . $imgSrc . '" alt="Product Image" onerror="this.onerror=null;this.src=\'admin/images/no-image.png\';"></td>';

                      echo '<td>' . esc($row['name']) . '</td>';
                      echo '<td>' . esc(strlen((string)$row['description']) > 120 ? substr($row['description'], 0, 120) . '…' : $row['description']) . '</td>';
                      echo '<td>' . esc((string)$row['price']) . '</td>';
                      echo '<td>' . esc($row['category']) . '</td>';
                      echo '<td>
                              <a class="action-btn edit" href="edit_product.php?id=' . esc((string)$row['id']) . '">Edit</a>
                              <a class="action-btn delete" href="delete_product.php?id=' . esc((string)$row['id']) . '" onclick="return confirm(\'Are you sure?\');">Delete</a>
                            </td>';
                      echo '</tr>';
                  }
              } else {
                  echo '<tr><td colspan="7" class="muted">No products found</td></tr>';
              }
              ?>
              </tbody>
            </table>
          </div>
        </div>
      </main>
    </div>

    <footer class="footer">© <?php echo date('Y'); ?> Ali Hair Wigs — Admin</footer>
  </div>

  <script>
    // smooth initial theme transition
    document.documentElement.style.transition = 'background-color .22s ease, color .22s ease';
    setTimeout(()=> document.documentElement.style.transition = '', 300);

    // Elements
    const sidebar = document.getElementById('sidebar');
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const themeBtn = document.getElementById('themeBtn');
    const overlay = document.createElement('div');

    // Overlay for mobile sidebar
    overlay.id = 'overlay';
    overlay.style.display = 'none';
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(2,6,23,0.45)';
    overlay.style.zIndex = '110';
    overlay.addEventListener('click', () => toggleSidebar(false));
    document.body.appendChild(overlay);

    function toggleSidebar(open) {
      if (open) {
        sidebar.classList.add('open');
        overlay.style.display = 'block';
      } else {
        sidebar.classList.remove('open');
        overlay.style.display = 'none';
      }
    }

    mobileMenuBtn?.addEventListener('click', () => toggleSidebar(true));

    // Submenu toggles with ARIA updates + keyboard
    document.querySelectorAll('.menu-item').forEach(btn => {
      btn.addEventListener('click', () => {
        const target = document.getElementById(btn.dataset.target);
        const expanded = btn.getAttribute('aria-expanded') === 'true';
        btn.setAttribute('aria-expanded', String(!expanded));
        if (target) {
          target.style.display = expanded ? 'none' : 'flex';
          target.setAttribute('aria-hidden', String(expanded));
        }
      });
      btn.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          btn.click();
        }
      });
    });

    // Theme persistence and prefers-color-scheme fallback
    function applyTheme(name) {
      if (name === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        themeBtn.setAttribute('aria-pressed', 'true');
        localStorage.setItem('admin_theme', 'dark');
      } else {
        document.documentElement.removeAttribute('data-theme');
        themeBtn.setAttribute('aria-pressed', 'false');
        localStorage.setItem('admin_theme', 'light');
      }
    }
    const stored = localStorage.getItem('admin_theme');
    if (stored) {
      applyTheme(stored === 'dark' ? 'dark' : 'light');
    } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      applyTheme('dark');
    } else {
      applyTheme('light');
    }

    themeBtn?.addEventListener('click', () => {
      const cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
      applyTheme(cur === 'dark' ? 'light' : 'dark');
    });

    // Mobile adjustments
    function onResize() {
      if (window.innerWidth <= 960) {
        document.querySelectorAll('.mobile-menu-btn').forEach(el => el.style.display = 'inline-flex');
      } else {
        document.querySelectorAll('.mobile-menu-btn').forEach(el => el.style.display = 'none');
        toggleSidebar(false);
        overlay.style.display = 'none';
      }
    }
    window.addEventListener('resize', onResize);
    onResize();
  </script>
</body>
</html>
<?php
$conn->close();
?>
