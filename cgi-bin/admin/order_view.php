<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Dhaka');

// ------------------ CONFIG ------------------
$DB_HOST = 'localhost';
$DB_USER = 'alihairw';
$DB_PASS = 'x5.H(8xkh3H7EY';
$DB_NAME = 'alihairw_alihairwigs';

// Public URL prefix that serves your product images (adjust to match your server)
// Example: if images are accessible at https://example.com/admin/admin/images/IMG.jpg use '/admin/admin/images'
$IMG_BASE_WEB = '/admin/admin/images';

// Filesystem path that corresponds to the above web prefix (optional, used to avoid 404s)
// Example: $_SERVER['DOCUMENT_ROOT'] . '/admin/admin/images'
$IMG_BASE_FS = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/admin/admin/images';

// Placeholder image used when the real image is missing (adjust to an existing asset)
$PLACEHOLDER_IMG = '/assets/img/placeholder-56.png';

$COMPANY = [
    'name' => 'Ali Hair Wigs',
    'address' => 'Dhaka, Bangladesh',
    'phone' => '+880 1XXXXXXXXX',
    'email' => 'info@alihairwigs.example',
];
$DEFAULT_CURRENCY_SYMBOL = '$';
const DEBUG_DB = false;
// --------------------------------------------

// Simple auth guard (adjust to your auth)
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Connect
$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo 'Database connection error';
    exit;
}
$mysqli->set_charset('utf8mb4');

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Normalize and validate an image value for use in <img src="...">
 * - Accepts full http(s) URLs and returns them unchanged.
 * - For relative filenames, strips traversal and prefixes $IMG_BASE_WEB.
 * - Optionally checks filesystem existence using $IMG_BASE_FS and returns placeholder if missing.
 * - Always returns a web path (string).
 */
function safe_img_src(string $val): string {
    global $IMG_BASE_WEB, $IMG_BASE_FS, $PLACEHOLDER_IMG;

    $val = trim((string)$val);
    if ($val === '') return $PLACEHOLDER_IMG;

    // If it's already a full URL, return as-is
    if (preg_match('#^https?://#i', $val)) return $val;

    // If the stored value already contains the web prefix, normalize and return
    $normalizedVal = preg_replace('#^/+|/+$#', '', $val);
    $prefixNormalized = preg_replace('#^/+|/+$#', '', (string)$IMG_BASE_WEB);
    if ($prefixNormalized !== '' && stripos($normalizedVal, $prefixNormalized . '/') === 0) {
        // ensure leading slash
        return '/' . ltrim($normalizedVal, '/');
    }

    // Sanitize relative filename
    $val = str_replace("\0", '', $val);
    $val = preg_replace('#\.\./+#', '', $val);
    $val = ltrim($val, '/\\');
    if ($val === '') return $PLACEHOLDER_IMG;

    // Build web path
    $prefix = rtrim((string)$IMG_BASE_WEB, '/');
    $webPath = '/' . ltrim(($prefix === '' ? 'products' : ltrim($prefix, '/')) . '/' . $val, '/');

    // Optional filesystem check to avoid 404s
    if (!empty($IMG_BASE_FS)) {
        $fsPrefix = rtrim((string)$IMG_BASE_FS, '/');
        $fsPath = $fsPrefix . '/' . $val;
        if (is_file($fsPath) && is_readable($fsPath)) {
            return $webPath;
        } else {
            // Log missing file for ops; do not expose details to users
            error_log("Missing product image: " . $fsPath);
            return $PLACEHOLDER_IMG;
        }
    }

    // No FS check configured, return web path (browser will request it)
    return $webPath;
}

// Validate order id
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: orders.php');
    exit;
}

// Safe helper: check column existence
function tableHasColumn(mysqli $m, string $table, string $col): bool {
    $q = $m->real_escape_string($table);
    $c = $m->real_escape_string($col);
    $res = @$m->query("SHOW COLUMNS FROM `{$q}` LIKE '{$c}'");
    if (!$res) return false;
    $ok = $res->num_rows > 0;
    $res->free();
    return $ok;
}

// Fetch order row
$order = null;
try {
    $stmt = $mysqli->prepare("SELECT * FROM `orders` WHERE `id` = ? LIMIT 1");
    if (!$stmt) throw new RuntimeException('Prepare failed: ' . $mysqli->error);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res->fetch_assoc() ?: null;
    $stmt->close();
} catch (Throwable $e) {
    if (DEBUG_DB) { echo "DB error: " . esc($e->getMessage()); } else { echo "Query error"; }
    exit;
}
if (!$order) {
    header('Location: orders.php');
    exit;
}

// Detect available columns in order_items
$has_img = tableHasColumn($mysqli, 'order_items', 'product_img');
$has_meta = tableHasColumn($mysqli, 'order_items', 'meta');

// Build SELECT list defensively
$selectCols = ['id','product_id','product_name','unit_price','qty','line_total'];
if ($has_img) $selectCols[] = 'product_img';
if ($has_meta) $selectCols[] = 'meta';
$selectSql = implode(', ', array_map(function($c){ return "`{$c}`"; }, $selectCols));

// Fetch order_items (if table exists)
$orderItems = [];
try {
    $sqlItems = "SELECT {$selectSql} FROM `order_items` WHERE `order_id` = ? ORDER BY id ASC";
    $stmt = $mysqli->prepare($sqlItems);
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) $orderItems[] = $r;
        $stmt->close();
    }
} catch (Throwable $e) {
    if (DEBUG_DB) echo "Item fetch error: " . esc($e->getMessage());
}

// Extract fields with fallbacks
$order_number = $order['order_number'] ?? ('#' . ($order['id'] ?? $id));
$placed_at = $order['created_at'] ?? $order['created'] ?? $order['ordered_at'] ?? ($order['date'] ?? '');
$customer_name = $order['customer_name'] ?? $order['customer'] ?? '';
$customer_email = $order['customer_email'] ?? $order['email'] ?? '';
$customer_phone = $order['customer_phone'] ?? $order['phone'] ?? '';
$shipping_address = $order['shipping_address'] ?? $order['customer_address'] ?? $order['address'] ?? '';
$country = $order['country'] ?? $order['country_code'] ?? '';
$currency_symbol = $order['currency_symbol'] ?? $DEFAULT_CURRENCY_SYMBOL;
$amountColCandidates = ['total_amount','total_usd','grand_total','amount','total'];
$amountVal = null;
foreach ($amountColCandidates as $c) { if (array_key_exists($c, $order)) { $amountVal = (float)$order[$c]; break; } }
if ($amountVal === null) $amountVal = 0.0;

// Build items array (prefer order_items, else JSON snapshot, else product_name)
$items = [];
if (!empty($orderItems)) {
    foreach ($orderItems as $it) {
        $name = $it['product_name'] ?? ('#' . ($it['product_id'] ?? ''));
        $qty = isset($it['qty']) ? (int)$it['qty'] : 1;
        $unit = isset($it['unit_price']) ? (float)$it['unit_price'] : 0.0;
        $line = isset($it['line_total']) ? (float)$it['line_total'] : round($unit * $qty, 2);
        $img = $it['product_img'] ?? '';
        $meta = $it['meta'] ?? null;
        $items[] = ['name'=>$name,'qty'=>$qty,'price'=>$unit,'line'=>$line,'img'=>$img,'meta'=>$meta];
    }
} else {
    if (!empty($order['items']) && is_string($order['items'])) {
        $decoded = json_decode($order['items'], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            foreach ($decoded as $it) {
                $name = $it['name'] ?? $it['product_name'] ?? ($it['product'] ?? 'Item');
                $qty = isset($it['qty']) ? (int)$it['qty'] : (isset($it['quantity']) ? (int)$it['quantity'] : 1);
                $unit = isset($it['unit_price']) ? (float)$it['unit_price'] : (isset($it['price']) ? (float)$it['price'] : 0.0);
                $line = isset($it['line_total']) ? (float)$it['line_total'] : round($unit * $qty, 2);
                $img = $it['img'] ?? $it['image'] ?? $it['product_img'] ?? $it['thumbnail'] ?? '';
                $items[] = ['name'=>$name,'qty'=>$qty,'price'=>$unit,'line'=>$line,'img'=>$img,'meta'=>null];
            }
        }
    }
    if (empty($items)) {
        if (!empty($order['product_name']) || !empty($order['product'])) {
            $name = $order['product_name'] ?? $order['product'] ?? 'Item';
            $items[] = ['name'=>$name,'qty'=>1,'price'=>$amountVal,'line'=>$amountVal,'img'=>$order['product_img'] ?? '','meta'=>null];
        }
    }
}

// Compute totals
$subtotal = 0.0;
foreach ($items as $it) $subtotal += (float)($it['line'] ?? ((float)$it['price'] * (int)$it['qty']));
$tax = isset($order['tax']) ? (float)$order['tax'] : 0.0;
$shipping = isset($order['shipping']) ? (float)$order['shipping'] : 0.0;
$total = $subtotal + $tax + $shipping;
if ($total <= 0 && $amountVal > 0) $total = $amountVal;

// Render printable invoice (single page)
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice <?php echo esc((string)$order_number); ?></title>
<style>
:root{--base:#071026;--muted:#6b7280;--accent:#0ea5b6;font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,Arial;color:var(--base)}
html,body{margin:0;padding:18px;background:#fff;color:var(--base);-webkit-font-smoothing:antialiased}
.container{max-width:920px;margin:0 auto;background:#fff;padding:20px;border-radius:10px;box-shadow:0 10px 30px rgba(2,6,23,0.04)}
.header{display:flex;justify-content:space-between;gap:18px;align-items:flex-start}
.brand{display:flex;gap:12px;align-items:center}
.logo{width:56px;height:56;border-radius:8px;background:linear-gradient(135deg,var(--accent),#0b74d1);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800}
.meta{font-size:13px;color:var(--muted)}
.customer{margin-top:14px;padding:12px;border:1px solid #eef3f7;border-radius:8px;background:#fbfdfe}
.table{width:100%;border-collapse:collapse;margin-top:16px;font-size:14px}
.table th{background:#f7fafb;text-align:left;padding:10px;font-weight:700;color:var(--muted);border-bottom:1px solid #eef3f7}
.table td{padding:10px;border-bottom:1px solid #f1f5f7;vertical-align:middle}
.img-thumb{width:56px;height:56;object-fit:cover;border-radius:6px;border:1px solid #eef3f7}
.items-desc{display:flex;gap:12px;align-items:center}
.right{text-align:right}
.small{font-size:13px;color:var(--muted)}
.totals{margin-top:14px;max-width:420px;margin-left:auto}
.totals .row{display:flex;justify-content:space-between;padding:10px 12px;border-top:1px dashed #eef3f7;font-weight:700}
.totals .row:first-child{border-top:0}
.totals .grand{font-size:18px;background:#f8fafc;padding:12px;border-radius:6px}
.print-actions{display:flex;gap:10px;margin-top:14px;align-items:center}
.btn{padding:8px 12px;border-radius:8px;border:0;background:var(--accent);color:#043033;font-weight:700;cursor:pointer}
.btn.ghost{background:transparent;border:1px solid #e6eef2;color:var(--muted)}
@media print{.no-print{display:none}}
</style>
</head>
<body>
  <div class="container" role="document" aria-label="Invoice <?php echo esc((string)$order_number); ?>">
    <div class="header">
      <div class="brand">
        <div class="logo" aria-hidden="true">AH</div>
        <div>
          <div style="font-weight:800"><?php echo esc($COMPANY['name']); ?></div>
          <div class="meta"><?php echo esc($COMPANY['address']); ?></div>
          <div class="meta">Phone: <?php echo esc($COMPANY['phone']); ?> 路 Email: <?php echo esc($COMPANY['email']); ?></div>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-weight:800;font-size:16px">Invoice</div>
        <div class="meta">Order: <strong><?php echo esc((string)$order_number); ?></strong></div>
        <div class="meta">Date: <?php echo esc((string)$placed_at); ?></div>
        <div class="meta">Customer: <?php echo esc((string)$customer_name); ?></div>
      </div>
    </div>

    <div style="display:flex;gap:18px;margin-top:14px">
      <div style="flex:1">
        <div class="customer" aria-label="Billing details">
          <div style="font-weight:700">Bill to</div>
          <div><?php echo esc($customer_name); ?></div>
          <?php if ($customer_email !== ''): ?><div class="small"><?php echo esc($customer_email); ?></div><?php endif; ?>
          <?php if ($customer_phone !== ''): ?><div class="small">Phone: <?php echo esc($customer_phone); ?></div><?php endif; ?>
          <?php if ($shipping_address !== ''): ?><div style="margin-top:8px"><?php echo nl2br(esc($shipping_address)); ?></div><?php endif; ?>
          <?php if ($country !== ''): ?><div class="small" style="margin-top:8px">Country: <?php echo esc($country); ?></div><?php endif; ?>
        </div>
      </div>
      <div style="min-width:220px">
        <div style="font-weight:700;margin-bottom:6px">Summary</div>
        <div class="small">Order total</div>
        <div style="font-size:20px;font-weight:800;margin-top:6px"><?php echo esc($currency_symbol) . number_format((float)$total,2); ?></div>
        <div class="small" style="margin-top:8px;color:var(--muted)">Order #: <?php echo esc((string)$order_number); ?></div>
      </div>
    </div>

    <table class="table" aria-label="Invoice items">
      <thead>
        <tr>
          <th style="width:50%">Item</th>
          <th style="width:16%" class="right">Unit</th>
          <th style="width:12%" class="right">Qty</th>
          <th style="width:22%" class="right">Line</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($items)): ?>
          <tr><td colspan="4" class="small">No itemized snapshot available</td></tr>
        <?php else: foreach ($items as $it):
            $name = $it['name'] ?? 'Item';
            $qty = (int)($it['qty'] ?? 1);
            $unit = isset($it['price']) ? (float)$it['price'] : 0.0;
            $line = isset($it['line']) ? (float)$it['line'] : round($unit * $qty, 2);
            $imgVal = (string)($it['img'] ?? '');
            $src = safe_img_src($imgVal);
            $meta = $it['meta'] ?? null;
        ?>
        <tr>
          <td>
            <div class="items-desc">
              <img src="<?php echo esc($src); ?>"
                   alt="<?php echo esc($name); ?>"
                   class="img-thumb"
                   onerror="this.onerror=null;this.src='<?php echo esc($PLACEHOLDER_IMG); ?>'">
              <div>
                <div style="font-weight:700"><?php echo esc((string)$name); ?></div>
                <?php if (!empty($meta) && is_string($meta)): ?>
                  <div class="small"><?php
                    $dec = json_decode($meta, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($dec)) {
                        $parts = [];
                        foreach ($dec as $k=>$v) $parts[] = esc((string)$k . ':' . (is_scalar($v) ? (string)$v : json_encode($v)));
                        echo implode('; ', $parts);
                    } else {
                        echo esc($meta);
                    }
                  ?></div>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td class="right"><?php echo esc($currency_symbol) . number_format((float)$unit, 2); ?></td>
          <td class="right"><?php echo esc((string)$qty); ?></td>
          <td class="right"><?php echo esc($currency_symbol) . number_format((float)$line, 2); ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

    <div class="totals" role="note" aria-hidden="false">
      <div class="row"><span class="small">Subtotal</span><span><?php echo esc($currency_symbol) . number_format((float)$subtotal,2); ?></span></div>
      <div class="row"><span class="small">Tax</span><span><?php echo esc($currency_symbol) . number_format((float)$tax,2); ?></span></div>
      <div class="row"><span class="small">Shipping</span><span><?php echo esc($currency_symbol) . number_format((float)$shipping,2); ?></span></div>
      <div class="row grand"><span>Total</span><span><?php echo esc($currency_symbol) . number_format((float)$total,2); ?></span></div>
    </div>

    <div class="print-actions no-print">
      <button class="btn" id="doPrint" type="button">Print</button>
      <a class="btn btn-ghost" href="orders.php" style="text-decoration:none;padding:8px 12px;border-radius:8px;border:1px solid #e6eef2;background:transparent;color:var(--muted)">Back</a>
      <div style="flex:1"></div>
      <div class="small" style="color:var(--muted)">Tip: choose "Save as PDF" in print dialog to download</div>
    </div>

    <footer class="small" style="margin-top:18px;color:var(--muted);text-align:center">Thank you for your order. For support, contact <?php echo esc($COMPANY['email']); ?>.</footer>
  </div>

<script>
function triggerPrint(){ setTimeout(function(){ try{ window.print(); }catch(e){} }, 250); }
(function(){
  if (window.opener){ triggerPrint(); window.onafterprint = function(){ try{ window.close(); }catch(e){} }; }
  var btn = document.getElementById('doPrint');
  if (btn) btn.addEventListener('click', triggerPrint);
})();
</script>
</body>
</html>
