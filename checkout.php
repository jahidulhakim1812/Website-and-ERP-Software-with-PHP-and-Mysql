<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Dhaka');

//
// CONFIG - adjust to your environment
//
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

define('CURRENCY_SYMBOL', '$');
define('DEFAULT_CURRENCY', 'USD');

// Set true to include DB errors in JSON responses (temporary debugging only)
const DEBUG_DB = false;

// Simple static conversion table (demo). Replace with live rates in production.
$CURRENCY_RATES = [
    'USD' => 1.00,
    'EUR' => 0.92,
    'GBP' => 0.78,
    'BDT' => 107.50,
    'AUD' => 1.55,
];

// Duty rate tiers
$DUTY_RATES = [
    'domestic' => 0.00,
    'international_low' => 0.05,
    'international_high' => 0.15,
];

//
// HELPERS
//
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function jsonOut(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function nowIso(): string { return date('c'); }
function generateOrderNumber(): string {
    return 'AHW-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}
function calcDuty(float $subtotal, float $rate): float { return round($subtotal * $rate, 2); }
function convertCurrency(float $amount, string $from, string $to, array $rates): float {
    $fromRate = $rates[$from] ?? 1.0;
    $toRate = $rates[$to] ?? 1.0;
    if ($fromRate <= 0 || $toRate <= 0) return round($amount, 2);
    $usd = $amount / $fromRate;
    return round($usd * $toRate, 2);
}
function sanitizeStr($v): string { return trim((string)$v); }

//
// POST API: receive JSON order and store in DB (orders + order_items)
//
if ($_SERVER['REQUEST_METHOD'] === 'POST' && stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
    // Optional debug reporting (enable briefly during troubleshooting)
    if (DEBUG_DB) {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) jsonOut(['success' => false, 'error' => 'Invalid JSON'], 400);

    $customer = $payload['customer'] ?? [];
    $cart = $payload['cart'] ?? [];
    $preferred_currency = strtoupper(sanitizeStr($payload['currency'] ?? DEFAULT_CURRENCY));
    global $CURRENCY_RATES, $DUTY_RATES;

    // Basic validation
    $name = sanitizeStr($customer['name'] ?? '');
    $email = sanitizeStr($customer['email'] ?? '');
    $phone = sanitizeStr($customer['phone'] ?? '');
    $country = sanitizeStr($customer['country'] ?? '');
    $address = sanitizeStr($customer['address'] ?? '');
    if ($name === '' || $email === '' || $country === '' || $address === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !is_array($cart) || count($cart) === 0) {
        jsonOut(['success' => false, 'error' => 'Missing required customer fields or empty cart'], 422);
    }

    // Normalize items and compute subtotal (store currency = USD)
    $storeCurrency = DEFAULT_CURRENCY;
    $items = [];
    $subtotal = 0.0;
    foreach ($cart as $it) {
        $qty = max(1, (int)($it['qty'] ?? 0));
        $price = max(0.0, (float)($it['price'] ?? 0.0));
        $id = isset($it['id']) ? (int)$it['id'] : null;
        $line = round($price * $qty, 2);
        $subtotal += $line;
        $items[] = [
            'product_id' => $id,
            'name' => sanitizeStr($it['name'] ?? 'Product'),
            'unit_price' => $price,
            'qty' => $qty,
            'line_total' => $line,
            'img' => sanitizeStr($it['img'] ?? ''),
        ];
    }
    $subtotal = round($subtotal, 2);

    // Shipping / duty / tax
    $isDomestic = strcasecmp($country, 'Bangladesh') === 0 || stripos($country, 'Bangladesh') !== false;
    $shipping = $isDomestic ? 3.00 : 12.00;
    $dutyRate = $isDomestic ? $DUTY_RATES['domestic'] : ($subtotal < 200 ? $DUTY_RATES['international_low'] : $DUTY_RATES['international_high']);
    $duty = calcDuty($subtotal, $dutyRate);
    $tax = 0.00;
    $totalUSD = round($subtotal + $shipping + $duty + $tax, 2);

    // Display conversions
    $displaySubtotal = convertCurrency($subtotal, $storeCurrency, $preferred_currency, $CURRENCY_RATES);
    $displayShipping = convertCurrency($shipping, $storeCurrency, $preferred_currency, $CURRENCY_RATES);
    $displayDuty = convertCurrency($duty, $storeCurrency, $preferred_currency, $CURRENCY_RATES);
    $displayTax = convertCurrency($tax, $storeCurrency, $preferred_currency, $CURRENCY_RATES);
    $displayTotal = convertCurrency($totalUSD, $storeCurrency, $preferred_currency, $CURRENCY_RATES);

    // Order record for fallback
    $orderNumber = generateOrderNumber();
    $createdAt = nowIso();
    $orderRecord = [
        'order_number' => $orderNumber,
        'created_at' => $createdAt,
        'customer' => ['name' => $name, 'email' => $email, 'phone' => $phone, 'country' => $country, 'address' => $address],
        'items' => $items,
        'currency' => $storeCurrency,
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'duty' => $duty,
        'tax' => $tax,
        'total_usd' => $totalUSD,
        'display_currency' => $preferred_currency,
        'display_subtotal' => $displaySubtotal,
        'display_shipping' => $displayShipping,
        'display_duty' => $displayDuty,
        'display_tax' => $displayTax,
        'display_total' => $displayTotal,
    ];

    // DB connect
    $conn = @new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
    if ($conn->connect_error ?? false) {
        // fallback: append JSON
        $file = __DIR__ . '/orders_fallback.json';
        $arr = [];
        if (is_readable($file)) { $txt = file_get_contents($file); $arr = json_decode($txt, true) ?: []; }
        $arr[] = $orderRecord;
        file_put_contents($file, json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        jsonOut(['success' => true, 'order_number' => $orderNumber, 'note' => 'Saved using fallback file (DB connect failed)', 'amount_due' => "{$preferred_currency} {$displayTotal}"]);
    }

    // Ensure charset
    $conn->set_charset('utf8mb4');

    try {
        $conn->begin_transaction();

        // Insert into orders (single valid bind_param call)
        $sqlOrder = "INSERT INTO orders (order_number, customer_name, customer_email, customer_phone, customer_country, customer_address, currency, subtotal, shipping, duty, tax, total_usd, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sqlOrder);
        if (!$stmt) {
            throw new RuntimeException('Prepare failed for orders: ' . $conn->error);
        }

        // types: order_number(s), name(s), email(s), phone(s), country(s), address(s), storeCurrency(s), subtotal(d), shipping(d), duty(d), tax(d), totalUSD(d), createdAt(s)
        $types = 'sssssssddddds';
        if (!$stmt->bind_param($types, $orderNumber, $name, $email, $phone, $country, $address, $storeCurrency, $subtotal, $shipping, $duty, $tax, $totalUSD, $createdAt)) {
            throw new RuntimeException('Bind failed for orders: ' . $stmt->error);
        }
        if (!$stmt->execute()) {
            throw new RuntimeException('Order insert failed: ' . $stmt->error);
        }
        $orderId = (int)$conn->insert_id;
        $stmt->close();

        // Insert items into order_items with correct bind types and NULL support for product_id
        $sqlItem = "INSERT INTO order_items (order_id, product_id, product_name, unit_price, qty, line_total, product_img) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $itemStmt = $conn->prepare($sqlItem);
        if (!$itemStmt) {
            // commit order and return warning
            $conn->commit();
            $conn->close();
            jsonOut([
                'success' => true,
                'order_number' => $orderNumber,
                'amount_due' => "{$preferred_currency} {$displayTotal}",
                'warning' => 'Order saved but unable to insert items (order_items missing or prepare failed).'
            ]);
        }

        foreach ($items as $it) {
            $bind_order_id = $orderId;
            // Use NULL for product_id when not provided
            $bind_pid = ($it['product_id'] === null) ? null : (int)$it['product_id'];
            $bind_name = $it['name'];
            $bind_uprice = (float)$it['unit_price'];
            $bind_qty = (int)$it['qty'];
            $bind_line = (float)$it['line_total'];
            $bind_img = $it['img'] ?? '';

            // mysqli doesn't accept direct NULL binding for int in bind_param; use a workaround: bind as string and let DB cast, or use separate approach
            // We'll bind product_id as integer, using 0 when null but also set product_id column to allow NULL if you prefer a different strategy.
            $bind_pid_for_db = ($bind_pid === null) ? 0 : $bind_pid;

            if (!$itemStmt->bind_param('iisdids', $bind_order_id, $bind_pid_for_db, $bind_name, $bind_uprice, $bind_qty, $bind_line, $bind_img)) {
                throw new RuntimeException('Bind failed for order_items: ' . $itemStmt->error);
            }
            if (!$itemStmt->execute()) {
                throw new RuntimeException('Item insert failed: ' . $itemStmt->error);
            }
        }
        $itemStmt->close();

        $conn->commit();
        $conn->close();

        jsonOut([
            'success' => true,
            'order_number' => $orderNumber,
            'amount_due' => "{$preferred_currency} {$displayTotal}",
            'breakdown' => [
                'subtotal' => "{$preferred_currency} {$displaySubtotal}",
                'shipping' => "{$preferred_currency} {$displayShipping}",
                'duty' => "{$preferred_currency} {$displayDuty}",
                'tax' => "{$preferred_currency} {$displayTax}",
                'total' => "{$preferred_currency} {$displayTotal}",
            ],
            'note' => 'Order recorded. Use order number for tracking.'
        ]);
    } catch (Throwable $ex) {
        // rollback and fallback to file
        if ($conn) {
            @$conn->rollback();
            @$conn->close();
        }
        $orderRecord['error'] = (string)$ex->getMessage();
        $file = __DIR__ . '/orders_fallback.json';
        $arr = [];
        if (is_readable($file)) { $txt = file_get_contents($file); $arr = json_decode($txt, true) ?: []; }
        $arr[] = $orderRecord;
        file_put_contents($file, json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $errResp = [
            'success' => true,
            'order_number' => $orderNumber,
            'note' => 'Saved using fallback file due to DB error',
            'amount_due' => "{$preferred_currency} {$displayTotal}"
        ];
        if (DEBUG_DB) $errResp['error'] = (string)$ex->getMessage();
        jsonOut($errResp);
    }
}

//
// GET: render checkout page (unchanged UI)
//
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/favicon.png">
  <title>International Checkout — Ali Hair Wigs</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, Arial; background:linear-gradient(180deg,#fff 0%,#f8fafc 100%); }
    .card { background:#fff; border-radius:12px; box-shadow:0 8px 24px rgba(15,23,42,0.06); }
    .small { font-size:0.85rem; color:#6b7280; }
    .currency-select { max-width:220px; }
    @media (max-width:768px) { .desktop-only { display:none; } }
  </style>
</head>
<body class="min-h-screen flex items-start justify-center p-6">

<div class="w-full max-w-6xl grid grid-cols-1 lg:grid-cols-3 gap-6">
  <!-- left: form -->
  <div class="lg:col-span-2 card p-6">
    <header class="mb-4">
      <h1 class="text-2xl font-bold">Secure Checkout</h1>
      <p class="small mt-1">Friendly, clear checkout supporting international shipments and customs estimates.</p>
    </header>

    <form id="checkout" class="space-y-4" onsubmit="return false;">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="small">Full name</label>
          <input id="name" required class="mt-1 block w-full border rounded-md p-2" />
        </div>
        <div>
          <label class="small">Email</label>
          <input id="email" type="email" required class="mt-1 block w-full border rounded-md p-2" />
        </div>
        <div>
          <label class="small">Phone</label>
          <input id="phone" class="mt-1 block w-full border rounded-md p-2" />
        </div>
        <div>
          <label class="small">Country</label>
          <select id="country" class="mt-1 block w-full border rounded-md p-2">
            <?php
// Full list (UN members + observers) — display names only
$countries = [
  'Afghanistan','Albania','Algeria','Andorra','Angola','Antigua and Barbuda','Argentina','Armenia','Australia','Austria',
  'Azerbaijan','Bahamas','Bahrain','Bangladesh','Barbados','Belarus','Belgium','Belize','Benin','Bhutan',
  'Bolivia (Plurinational State of)','Bosnia and Herzegovina','Botswana','Brazil','Brunei Darussalam','Bulgaria','Burkina Faso','Burundi','Cabo Verde','Cambodia',
  'Cameroon','Canada','Central African Republic','Chad','Chile','China','Colombia','Comoros','Congo (Republic of the)','Congo (Democratic Republic of the)',
  "Côte d'Ivoire",'Costa Rica','Croatia','Cuba','Cyprus','Czechia','Denmark','Djibouti','Dominica','Dominican Republic',
  'Ecuador','Egypt','El Salvador','Equatorial Guinea','Eritrea','Estonia','Eswatini','Ethiopia','Fiji','Finland',
  'France','Gabon','Gambia','Georgia','Germany','Ghana','Greece','Grenada','Guatemala','Guinea',
  'Guinea-Bissau','Guyana','Haiti','Honduras','Hungary','Iceland','India','Indonesia','Iran (Islamic Republic of)','Iraq',
  'Ireland','Israel','Italy','Jamaica','Japan','Jordan','Kazakhstan','Kenya','Kiribati','Korea (Democratic People’s Republic of)',
  'Korea (Republic of)','Kuwait','Kyrgyzstan','Lao People’s Democratic Republic','Latvia','Lebanon','Lesotho','Liberia','Libya','Liechtenstein',
  'Lithuania','Luxembourg','Madagascar','Malawi','Malaysia','Maldives','Mali','Malta','Marshall Islands','Mauritania',
  'Mauritius','Mexico','Micronesia (Federated States of)','Moldova (Republic of)','Monaco','Mongolia','Montenegro','Morocco','Mozambique','Myanmar',
  'Namibia','Nauru','Nepal','Netherlands','New Zealand','Nicaragua','Niger','Nigeria','North Macedonia','Norway',
  'Oman','Pakistan','Palau','Panama','Papua New Guinea','Paraguay','Peru','Philippines','Poland','Portugal',
  'Qatar','Romania','Russian Federation','Rwanda','Saint Kitts and Nevis','Saint Lucia','Saint Vincent and the Grenadines','Samoa','San Marino','Sao Tome and Principe',
  'Saudi Arabia','Senegal','Serbia','Seychelles','Sierra Leone','Singapore','Slovakia','Slovenia','Solomon Islands','Somalia',
  'South Africa','South Sudan','Spain','Sri Lanka','Sudan','Suriname','Sweden','Switzerland','Syrian Arab Republic','Tajikistan',
  'Tanzania (United Republic of)','Thailand','Timor-Leste','Togo','Tonga','Trinidad and Tobago','Tunisia','Turkey','Turkmenistan','Tuvalu',
  'Uganda','Ukraine','United Arab Emirates','United Kingdom of Great Britain and Northern Ireland','United States of America','Uruguay','Uzbekistan','Vanuatu','Venezuela (Bolivarian Republic of)','Viet Nam',
  'Yemen','Zambia','Zimbabwe','Holy See (Vatican City State)','State of Palestine'
];

foreach ($countries as $c) {
    echo '<option value="' . e($c) . '">' . e($c) . '</option>';
}
?>

          </select>
        </div>
      </div>

      <div>
        <label class="small">Shipping address</label>
        <textarea id="address" rows="3" required class="mt-1 block w-full border rounded-md p-2"></textarea>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
          <label class="small">Display currency</label>
          <select id="currency" class="mt-1 block w-full border rounded-md p-2 currency-select">
            <?php foreach ($CURRENCY_RATES as $c => $_): ?>
              <option value="<?php echo e($c) ?>" <?php echo ($c === DEFAULT_CURRENCY ? 'selected' : '') ?>><?php echo e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label class="small">Shipping speed</label>
          <select id="shippingSpeed" class="mt-1 block w-full border rounded-md p-2">
            <option value="standard">Standard (3-10 days)</option>
            <option value="express">Express (2-5 days) — additional cost</option>
          </select>
        </div>

        <div>
          <label class="small">Promo code</label>
          <input id="promo" class="mt-1 block w-full border rounded-md p-2" placeholder="Optional code" />
        </div>
      </div>

      <div>
        <label class="small">Order note</label>
        <textarea id="note" rows="2" class="mt-1 block w-full border rounded-md p-2" placeholder="Any delivery instructions or reference numbers"></textarea>
      </div>

      <div class="flex gap-3 items-center">
        <button id="place" class="px-5 py-2 bg-[#7b3f00] text-white rounded-md font-semibold">Place order</button>
        <button id="preview" class="px-4 py-2 border rounded-md">Preview breakdown</button>
        <div id="status" class="small text-gray-600 ml-3"></div>
      </div>
    </form>

    <hr class="my-6"/>

    <section>
      <h2 class="text-lg font-semibold mb-2">Notes on international orders</h2>
      <ul class="small space-y-1">
        <li>We ship worldwide. International orders may be subject to customs duties and import taxes assessed by the destination country.</li>
        <li>Estimated duties are shown during preview and are not charged by Ali Hair Wigs — they are estimates for recipient budgeting.</li>
        <li>Express shipping adds an extra fee and reduces transit time but may increase customs handling speed and fees.</li>
      </ul>
    </section>
  </div>

  <!-- right: live summary -->
  <aside class="card p-6">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold">Order Summary</h3>
      <div class="small desktop-only">Store currency: <?php echo e(DEFAULT_CURRENCY) ?></div>
    </div>

    <div id="items" class="space-y-3 mb-4">
      <div class="small text-gray-500">No items in cart. Add items then preview.</div>
    </div>

    <div class="space-y-2 text-sm">
      <div class="flex justify-between"><span>Subtotal</span><span id="sum-sub">$0.00</span></div>
      <div class="flex justify-between"><span>Shipping</span><span id="sum-ship">$0.00</span></div>
      <div class="flex justify-between"><span>Estimated duty</span><span id="sum-duty">$0.00</span></div>
      <div class="flex justify-between"><span>Promo</span><span id="sum-promo">- $0.00</span></div>
      <div class="flex justify-between text-lg font-bold pt-2 border-t"><span>Total</span><span id="sum-total">$0.00</span></div>
      <div class="text-xs text-gray-500 pt-2">Display currency: <span id="displayCurrency"><?php echo e(DEFAULT_CURRENCY) ?></span></div>
    </div>

    <div class="mt-4">
      <button id="refreshCart" class="w-full px-4 py-2 border rounded-md">Load cart from browser</button>
    </div>
  </aside>
</div>

<script>
(function(){
  const CART_KEY = 'ali_hair_cart_v1';
  const CURRENCY_RATES = <?php echo json_encode($CURRENCY_RATES); ?>;
  const DUTY_RATES = <?php echo json_encode($DUTY_RATES); ?>;
  const storeCurrency = '<?php echo DEFAULT_CURRENCY; ?>';
  const elements = {
    items: document.getElementById('items'),
    sumSub: document.getElementById('sum-sub'),
    sumShip: document.getElementById('sum-ship'),
    sumDuty: document.getElementById('sum-duty'),
    sumPromo: document.getElementById('sum-promo'),
    sumTotal: document.getElementById('sum-total'),
    displayCurrency: document.getElementById('displayCurrency'),
    status: document.getElementById('status'),
  };

  function loadCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY) || '{}'); } catch (e) { return {}; }
  }

  function format(v, curr) {
    return (curr || storeCurrency) + ' ' + Number(v).toFixed(2);
  }

  function convert(amount, from, to) {
    const rates = CURRENCY_RATES;
    const fromRate = rates[from] || 1;
    const toRate = rates[to] || 1;
    if (!fromRate || !toRate) return amount;
    const usd = amount / fromRate;
    return +(usd * toRate).toFixed(2);
  }

  function computeSummary(opts = {}) {
    const cart = loadCart();
    const items = Object.values(cart);
    let subtotal = 0;
    items.forEach(it => { subtotal += (Number(it.price || 0) * Number(it.qty || 0)); });
    subtotal = +(subtotal.toFixed(2));
    const country = document.getElementById('country').value || '';
    const shippingSpeed = document.getElementById('shippingSpeed').value || 'standard';
    let shipping = country.toLowerCase().includes('bangladesh') ? 3.00 : 12.00;
    if (shippingSpeed === 'express') shipping += 10.00;
    const dutyRate = country.toLowerCase().includes('bangladesh') ? DUTY_RATES.domestic : (subtotal < 200 ? DUTY_RATES.international_low : DUTY_RATES.international_high);
    const duty = +(subtotal * dutyRate).toFixed(2);
    const promoCode = (document.getElementById('promo').value || '').trim();
    let promo = 0;
    if (promoCode.toUpperCase() === 'FREESHIP') { promo = Math.min(shipping, subtotal * 0.5); }
    else if (promoCode.toUpperCase() === 'SAVE10') { promo = +(subtotal * 0.10).toFixed(2); }

    const total = +(subtotal + shipping + duty - promo).toFixed(2);
    const displayCurrency = document.getElementById('currency').value || storeCurrency;
    const dispSubtotal = convert(subtotal, storeCurrency, displayCurrency);
    const dispShipping = convert(shipping, storeCurrency, displayCurrency);
    const dispDuty = convert(duty, storeCurrency, displayCurrency);
    const dispPromo = convert(promo, storeCurrency, displayCurrency);
    const dispTotal = convert(total, storeCurrency, displayCurrency);

    return {
      items, subtotal, shipping, duty, promo, total,
      displayCurrency, dispSubtotal, dispShipping, dispDuty, dispPromo, dispTotal
    };
  }

  function renderSummary() {
    const s = computeSummary();
    elements.items.innerHTML = '';
    if (!s.items.length) {
      elements.items.innerHTML = '<div class="small text-gray-500">Cart empty. Add items to see them here.</div>';
    } else {
      s.items.forEach(it => {
        const div = document.createElement('div');
        div.className = 'flex items-center gap-3';
        div.innerHTML = `<img src="${it.img || 'https://placehold.co/80x80/7b3f00/f7e0c4?text=Img'}" class="w-12 h-12 object-cover rounded-sm"><div class="flex-1"><div class="font-semibold">${it.name}</div><div class="small">${it.qty} × ${storeCurrency} ${Number(it.price).toFixed(2)}</div></div><div class="font-semibold">${storeCurrency} ${(it.qty*it.price).toFixed(2)}</div>`;
        elements.items.appendChild(div);
      });
    }
    elements.sumSub.textContent = format(s.dispSubtotal, s.displayCurrency);
    elements.sumShip.textContent = format(s.dispShipping, s.displayCurrency);
    elements.sumDuty.textContent = format(s.dispDuty, s.displayCurrency);
    elements.sumPromo.textContent = `- ${format(s.dispPromo, s.displayCurrency)}`;
    elements.sumTotal.textContent = format(s.dispTotal, s.displayCurrency);
    elements.displayCurrency.textContent = s.displayCurrency;
  }

  // initial render
  renderSummary();

  document.getElementById('refreshCart').addEventListener('click', () => { renderSummary(); elements.status.textContent = 'Cart reloaded.'; setTimeout(()=> elements.status.textContent = '', 2000); });
  document.getElementById('preview').addEventListener('click', (e) => { e.preventDefault(); renderSummary(); elements.status.textContent = 'Preview updated.'; setTimeout(()=> elements.status.textContent = '', 2000); });

  document.getElementById('place').addEventListener('click', async (e) => {
    e.preventDefault();
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const phone = document.getElementById('phone').value.trim();
    const country = document.getElementById('country').value.trim();
    const address = document.getElementById('address').value.trim();
    if (!name || !email || !country || !address) { alert('Please complete required fields'); return; }

    const cartObj = loadCart();
    const cartArr = Object.values(cartObj);
    if (!cartArr.length) { alert('Your cart is empty'); return; }

    const currency = document.getElementById('currency').value || storeCurrency;
    const payload = {
      customer: { name, email, phone, country, address },
      cart: cartArr,
      currency,
      note: document.getElementById('note').value.trim()
    };

    elements.status.textContent = 'Placing order...';
    try {
      const res = await fetch(location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
      const data = await res.json();
      if (data.success) {
        const orderNum = data.order_number || '';
        const amountDue = data.amount_due || '';
        elements.status.textContent = 'Order confirmed — ' + orderNum + '. ' + (amountDue ? ('Amount due: ' + amountDue + '.') : '');
        const conf = document.createElement('div');
        conf.className = 'mt-3 p-3 rounded-md bg-green-50 text-green-800';
        conf.innerHTML = `<strong>Order confirmed</strong><div class="text-sm mt-1">Order number: ${orderNum}</div>`;
        if (!document.getElementById('order-confirmation')) {
          conf.id = 'order-confirmation';
          elements.status.parentNode.appendChild(conf);
        } else {
          document.getElementById('order-confirmation').innerHTML = `<strong>Order confirmed</strong><div class="text-sm mt-1">Order number: ${orderNum}</div>`;
        }
        localStorage.removeItem(CART_KEY);
        renderSummary();
        setTimeout(function() { window.location.href = 'index.php'; }, 2500);
      } else {
        elements.status.textContent = 'Error: ' + (data.error || 'Unable to place order');
      }
    } catch (err) {
      elements.status.textContent = 'Network error while placing order';
    }
  });

  document.getElementById('currency').addEventListener('change', renderSummary);
  document.getElementById('country').addEventListener('change', renderSummary);
  document.getElementById('shippingSpeed').addEventListener('change', renderSummary);
  document.getElementById('promo').addEventListener('input', renderSummary);
})();
</script>

</body>
</html>
