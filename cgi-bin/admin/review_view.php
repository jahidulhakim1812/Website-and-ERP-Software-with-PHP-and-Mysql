<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Dhaka');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// DB
$DB_HOST='localhost'; $DB_USER='alihairw'; $DB_PASS='x5.H(8xkh3H7EY'; $DB_NAME='alihairw_alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { http_response_code(500); echo 'DB connection error'; exit; }
$mysqli->set_charset('utf8mb4');

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function detectColumn(mysqli $m, array $candidates, string $table = 'reviews'): ?string {
    $res = @$m->query("SHOW COLUMNS FROM `{$m->real_escape_string($table)}`");
    if (!$res) return null;
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    $res->free();
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return null;
}

$tbl = 'reviews';
$idCol = detectColumn($mysqli, ['id','review_id','rid'], $tbl) ?? 'id';
$productCol = detectColumn($mysqli, ['product_id','product','pid'], $tbl);
$titleCol = detectColumn($mysqli, ['title','review_title'], $tbl);
$bodyCol = detectColumn($mysqli, ['body','content','review','comment'], $tbl) ?? 'comment';
$ratingCol = detectColumn($mysqli, ['rating','stars','score'], $tbl) ?? 'rating';
$nameCol = detectColumn($mysqli, ['customer_name','name','reviewer_name','author'], $tbl) ?? 'customer_name';
$emailCol = detectColumn($mysqli, ['customer_email','email','reviewer_email'], $tbl) ?? 'customer_email';
$dateCol = detectColumn($mysqli, ['created_at','created','date','posted_at'], $tbl) ?? 'created_at';
$statusCol = detectColumn($mysqli, ['status','approved','visible','state'], $tbl);

// read id
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { $_SESSION['flash_error'] = 'Invalid review id.'; header('Location: reviews.php'); exit; }

// fetch review
$cols = [];
$cols[] = "`{$mysqli->real_escape_string($idCol)}` AS id";
if ($productCol) $cols[] = "`{$mysqli->real_escape_string($productCol)}` AS product_id";
if ($titleCol) $cols[] = "`{$mysqli->real_escape_string($titleCol)}` AS title";
$cols[] = "`{$mysqli->real_escape_string($bodyCol)}` AS body";
$cols[] = "`{$mysqli->real_escape_string($ratingCol)}` AS rating";
$cols[] = "`{$mysqli->real_escape_string($nameCol)}` AS reviewer";
$cols[] = "`{$mysqli->real_escape_string($emailCol)}` AS email";
$cols[] = "`{$mysqli->real_escape_string($dateCol)}` AS created_at";
if ($statusCol) $cols[] = "`{$mysqli->real_escape_string($statusCol)}` AS status";

$sql = "SELECT " . implode(', ', $cols) . " FROM `{$mysqli->real_escape_string($tbl)}` WHERE `{$mysqli->real_escape_string($idCol)}` = ? LIMIT 1";
$stmt = $mysqli->prepare($sql);
if (!$stmt) { $_SESSION['flash_error'] = 'Query prepare failed.'; header('Location: reviews.php'); exit; }
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$review = $res->fetch_assoc();
$stmt->close();

if (!$review) { $_SESSION['flash_error'] = 'Review not found.'; header('Location: reviews.php'); exit; }

// CSRF for delete form
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
$csrf = $_SESSION['csrf_token'];
$user_name = $_SESSION['name'] ?? 'Admin';
$user_email = $_SESSION['email'] ?? 'admin@example.com';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>View review #<?php echo esc((string)$review['id']); ?> — Admin</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <style>
    :root{ --bg:#f5f7fb; --panel:#fff; --muted:#6b7280; --text:#071026; --accent:#0ea5b6 }
    [data-theme="dark"]{ --bg:#06131a; --panel:#071421; --muted:#9fb0bf; --text:#e6eef6 }
    html,body{margin:0;padding:0;background:var(--bg);color:var(--text);font-family:Inter,system-ui,Arial}
    .card{max-width:1000px;margin:28px auto;padding:18px;background:var(--panel);border-radius:10px;border:1px solid rgba(0,0,0,0.04)}
    h1{margin:0 0 12px}
    .meta{color:var(--muted);margin-bottom:12px}
    .field{margin:10px 0}
    .label{font-weight:700;color:var(--muted);display:block;margin-bottom:6px}
    .body{white-space:pre-wrap;background:transparent;padding:12px;border-radius:8px;border:1px solid rgba(0,0,0,0.04)}
    .controls{display:flex;gap:8px;margin-top:14px}
    .btn{padding:8px 12px;border-radius:8px;border:0;cursor:pointer;font-weight:700}
    .btn-back{background:#eef2ff;color:#0b3b6f}
    .btn-delete{background:#fee2e2;color:#991b1b}
    .muted{color:var(--muted)}
  </style>
</head>
<body data-theme="">
  <div class="card" role="main">
    <a href="reviews.php" class="btn btn-back" style="text-decoration:none;display:inline-block;margin-bottom:12px">← Back to reviews</a>
    <h1>Review #<?php echo esc((string)$review['id']); ?></h1>
    <div class="meta">
      <strong><?php echo esc((string)$review['reviewer'] ?? ''); ?></strong>
      <?php if (!empty($review['email'])): ?> — <span class="muted"><?php echo esc((string)$review['email']); ?></span><?php endif; ?>
      <?php if (!empty($review['created_at'])): ?> • <span class="muted"><?php echo esc((string)$review['created_at']); ?></span><?php endif; ?>
    </div>

    <?php if (!empty($review['title'])): ?>
      <div class="field"><span class="label">Title</span><div><?php echo esc((string)$review['title']); ?></div></div>
    <?php endif; ?>

    <div class="field"><span class="label">Rating</span>
      <?php if ($review['rating'] !== null && $review['rating'] !== ''): ?>
        <div><?php echo esc((string)number_format((float)$review['rating'], 1)); ?> ★</div>
      <?php else: ?>
        <div class="muted">—</div>
      <?php endif; ?>
    </div>

    <div class="field"><span class="label">Review</span>
      <div class="body"><?php echo nl2br(esc((string)$review['body'])); ?></div>
    </div>

    <?php if (!empty($review['product_id'])): ?>
      <div class="field"><span class="label">Product</span>
        <div><a href="product_view.php?id=<?php echo urlencode((string)$review['product_id']); ?>"><?php echo esc((string)$review['product_id']); ?></a></div>
      </div>
    <?php endif; ?>

    <?php if (isset($review['status'])): ?>
      <div class="field"><span class="label">Status</span><div class="muted"><?php echo esc((string)$review['status']); ?></div></div>
    <?php endif; ?>

    <div class="controls">
      <a class="btn btn-back" href="reviews.php">Back</a>

      <form method="post" action="review_delete.php" onsubmit="return confirm('Delete this review?');" style="display:inline">
        <input type="hidden" name="review_id" value="<?php echo esc((string)$review['id']); ?>">
        <input type="hidden" name="csrf_token" value="<?php echo esc($csrf); ?>">
        <button type="submit" class="btn btn-delete">Delete review</button>
      </form>
    </div>
  </div>

<script>
  // Apply stored theme
  const stored = localStorage.getItem('admin_theme');
  if (stored === 'dark') document.documentElement.setAttribute('data-theme','dark');
</script>
</body>
</html>
