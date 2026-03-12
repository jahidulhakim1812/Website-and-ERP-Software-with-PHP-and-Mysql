<?php
/**
 * fin_overview.php
 * Financial Dashboard – Matches Admin Dashboard V2 Exactly
 * 
 * INCLUDES:
 * - Production costs (production_costs)
 * - All 'amount' tables (logistics, maintenance, utilities, etc.)
 * - Salary payments – EVERY SINGLE ROW counted
 * - Subcontractor payments (batch_payments)
 * - Accessory purchases (accessory_purchases)
 * - Net Profit = Paid Revenue - Total Expenses
 * 
 * All KPI cards are clickable with detailed modals and have a print icon.
 */

session_start();

// --- 1. AUTH & SETTINGS ---
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexander Pierce';
    $_SESSION['role'] = 'admin';
    $_SESSION['email'] = 'alex@example.com';
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: fin_overview.php");
    exit;
}

// --- 2. DATABASE CONNECTION ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) {
    die("DB Connection Failed: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
function tableExists($pdo, $table) {
    try { $pdo->query("SELECT 1 FROM $table LIMIT 1"); return true; } catch (Exception $e) { return false; }
}
function fetchVal($pdo, $sql, $params = []) {
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchColumn() ?: 0; } catch (Exception $e) { return 0; }
}

// --- 4. DATE FILTER LOGIC (same as admin dashboard) ---
$filter = $_GET['range'] ?? 'month';
$customStart = $_GET['start_date'] ?? '';
$customEnd = $_GET['end_date'] ?? '';
$chartGroupBy = 'day';

// Initialize date objects
$currentDate = new DateTime();
$startDate = new DateTime();

// If custom dates are provided, use them
if ($customStart && $customEnd) {
    $startDate = new DateTime($customStart);
    $endDate = new DateTime($customEnd);
    $filter = 'custom';
} else {
    // Use predefined ranges
    switch ($filter) {
        case 'today':
            $startDate->modify('today');
            $endDate = new DateTime('today');
            $chartGroupBy = 'day';
            break;
        case 'week':
            $startDate->modify('-7 days');
            $endDate = new DateTime();
            $chartGroupBy = 'day';
            break;
        case 'month':
            $startDate->modify('first day of this month');
            $endDate = new DateTime('last day of this month');
            $chartGroupBy = 'day';
            break;
        case 'year':
            $startDate->modify('first day of January this year');
            $endDate = new DateTime('last day of December this year');
            $chartGroupBy = 'month';
            break;
        case 'all':
            $startDate->setDate(2020, 1, 1);
            $endDate = new DateTime();
            $chartGroupBy = 'month';
            break;
        default:
            $startDate->modify('first day of this month');
            $endDate = new DateTime('last day of this month');
            $chartGroupBy = 'day';
            break;
    }
}

// Ensure we have end date
if (!isset($endDate)) {
    $endDate = new DateTime();
}

// Formatted strings for SQL queries
$startStr = $startDate->format('Y-m-d 00:00:00');
$endStr   = $endDate->format('Y-m-d 23:59:59');

// Format for display
$displayDateRange = '';
if ($filter === 'custom') {
    $displayDateRange = $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y');
} else {
    $displayDateRange = $startDate->format('M d, Y') . ' - ' . $endDate->format('M d, Y');
}

// --- 5. USER PHOTO ---
$userPhoto = '';
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userPhoto = $stmt->fetchColumn() ?: '';
    } catch (Exception $e) { $userPhoto = ''; }
}

// ==============================================
// EXPENSE CALCULATION – EXACTLY MATCHES ADMIN DASHBOARD
// ==============================================

// A. Production Costs
$prodCost = 0.0;
if (tableExists($pdo, 'production_costs')) {
    $sqlProd = "SELECT COALESCE(SUM(total_cost), 0) FROM production_costs WHERE production_date >= ? AND production_date <= ?";
    $prodCost = (float)fetchVal($pdo, $sqlProd, [$startStr, $endStr]);
}

// B. ALL 'amount' columns from various tables
$totalAmountColumns = 0.0;
$amountTables = [
    'logistics_expenses' => ['column' => 'amount', 'date_column' => 'expense_date'],
    'maintenance_costs'  => ['column' => 'amount', 'date_column' => 'date'],
    'utilities'          => ['column' => 'amount', 'date_column' => 'billing_date'],
    'other_expenses'     => ['column' => 'amount', 'date_column' => 'expense_date'],
    'rent_payments'      => ['column' => 'amount', 'date_column' => 'payment_date'],
    'tax_payments'       => ['column' => 'amount', 'date_column' => 'payment_date'],
    'insurance_payments' => ['column' => 'amount', 'date_column' => 'payment_date'],
    'marketing_expenses' => ['column' => 'amount', 'date_column' => 'date'],
    'office_supplies'    => ['column' => 'amount', 'date_column' => 'purchase_date'],
];
$expenseDetails = [];

foreach ($amountTables as $table => $config) {
    if (tableExists($pdo, $table)) {
        $sql = "SELECT COALESCE(SUM({$config['column']}), 0) FROM $table WHERE {$config['date_column']} >= ? AND {$config['date_column']} <= ?";
        $amount = (float)fetchVal($pdo, $sql, [$startStr, $endStr]);
        $totalAmountColumns += $amount;
        if ($amount > 0) $expenseDetails[$table] = $amount;
    }
}

// C. SALARY PAYMENTS – EVERY ROW COUNTED
$monthlySalaryPayments = 0.0;
$salaryCount = 0;
$salaryRows = [];

// Detect salary table
$salaryTableName = '';
$amountColumn = 'amount';
$dateColumn = 'payment_date';
$employeeColumn = 'employee_id';
$possibleTables = ['salary_payments', 'salaries', 'salary', 'employee_salaries', 'salary_transactions'];

foreach ($possibleTables as $table) {
    if (tableExists($pdo, $table)) {
        $salaryTableName = $table;
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('amount', $cols)) $amountColumn = 'amount';
            elseif (in_array('payment_amount', $cols)) $amountColumn = 'payment_amount';
            elseif (in_array('salary_amount', $cols)) $amountColumn = 'salary_amount';
            
            if (in_array('payment_date', $cols)) $dateColumn = 'payment_date';
            elseif (in_array('paid_date', $cols)) $dateColumn = 'paid_date';
            elseif (in_array('created_at', $cols)) $dateColumn = 'created_at';
            
            if (in_array('employee_id', $cols)) $employeeColumn = 'employee_id';
            elseif (in_array('emp_id', $cols)) $employeeColumn = 'emp_id';
        } catch (Exception $e) { continue; }
        break;
    }
}

if ($salaryTableName) {
    $sqlAll = "SELECT * FROM $salaryTableName WHERE $dateColumn >= ? AND $dateColumn <= ? ORDER BY $dateColumn DESC";
    try {
        $stmt = $pdo->prepare($sqlAll);
        $stmt->execute([$startStr, $endStr]);
        $salaryRows = $stmt->fetchAll();
        $salaryCount = count($salaryRows);
        $monthlySalaryPayments = array_sum(array_column($salaryRows, $amountColumn));
        if ($monthlySalaryPayments > 0) $expenseDetails['salary_payments'] = $monthlySalaryPayments;
    } catch (Exception $e) {
        $sqlSum = "SELECT COALESCE(SUM($amountColumn), 0) FROM $salaryTableName WHERE $dateColumn >= ? AND $dateColumn <= ?";
        $monthlySalaryPayments = (float)fetchVal($pdo, $sqlSum, [$startStr, $endStr]);
        $sqlCnt = "SELECT COUNT(*) FROM $salaryTableName WHERE $dateColumn >= ? AND $dateColumn <= ?";
        $salaryCount = (int)fetchVal($pdo, $sqlCnt, [$startStr, $endStr]);
        if ($monthlySalaryPayments > 0) $expenseDetails['salary_payments'] = $monthlySalaryPayments;
    }
}

// D. SUBCONTRACTOR PAYMENTS
$subcontractorPayments = 0.0;
if (tableExists($pdo, 'batch_payments')) {
    $sqlSub = "SELECT COALESCE(SUM(amount), 0) FROM batch_payments WHERE payment_date >= ? AND payment_date <= ?";
    $subcontractorPayments = (float)fetchVal($pdo, $sqlSub, [$startStr, $endStr]);
    if ($subcontractorPayments > 0) $expenseDetails['subcontractor_payments'] = $subcontractorPayments;
}

// E. ACCESSORY PURCHASES
$accessoryCost = 0.0;
if (tableExists($pdo, 'accessory_purchases')) {
    $sqlAccessory = "SELECT COALESCE(SUM(total_cost), 0) FROM accessory_purchases WHERE purchase_date >= ? AND purchase_date <= ?";
    $accessoryCost = (float)fetchVal($pdo, $sqlAccessory, [$startStr, $endStr]);
    if ($accessoryCost > 0) {
        $expenseDetails['accessory_purchases'] = $accessoryCost;
    }
}

// F. TOTAL EXPENSES
$boxExpense = $prodCost + $totalAmountColumns + $monthlySalaryPayments + $subcontractorPayments + $accessoryCost;

// ==============================================
// REVENUE & HOLD PAYMENTS
// ==============================================
$boxPaid = 0.0;
$paidOrders = [];
if (tableExists($pdo, 'purchase_orders')) {
    $sqlPaid = "SELECT COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE paid_amount END), 0) FROM purchase_orders WHERE order_date >= ? AND order_date <= ?";
    $boxPaid = (float)fetchVal($pdo, $sqlPaid, [$startStr, $endStr]);
    
    $sqlPaidOrders = "SELECT id, reference_no, order_date, total_amount, product_name, payment_status FROM purchase_orders WHERE payment_status = 'Paid' AND order_date >= ? AND order_date <= ? ORDER BY order_date DESC LIMIT 50";
    try { $stmt = $pdo->prepare($sqlPaidOrders); $stmt->execute([$startStr, $endStr]); $paidOrders = $stmt->fetchAll(); } catch (Exception $e) { $paidOrders = []; }
}

$boxHold = 0.0;
$holdOrders = [];
if (tableExists($pdo, 'purchase_orders')) {
    $sqlHold = "SELECT COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN 0 ELSE total_amount - COALESCE(paid_amount, 0) END), 0) FROM purchase_orders WHERE order_date >= ? AND order_date <= ?";
    $boxHold = (float)fetchVal($pdo, $sqlHold, [$startStr, $endStr]);
    
    $sqlHoldOrders = "SELECT id, reference_no, order_date, total_amount, product_name, payment_status FROM purchase_orders WHERE payment_status IN ('Pending','Partial') AND order_date >= ? AND order_date <= ? ORDER BY order_date DESC LIMIT 50";
    try { $stmt = $pdo->prepare($sqlHoldOrders); $stmt->execute([$startStr, $endStr]); $holdOrders = $stmt->fetchAll(); } catch (Exception $e) { $holdOrders = []; }
}

$boxNetProfit = $boxPaid - $boxExpense;

// Detailed production costs for modal
$detailedProductionCosts = [];
if (tableExists($pdo, 'production_costs')) {
    $sqlProdDetails = "SELECT id, batch_name, production_date, total_cost, notes FROM production_costs WHERE production_date >= ? AND production_date <= ? ORDER BY production_date DESC";
    try { $stmt = $pdo->prepare($sqlProdDetails); $stmt->execute([$startStr, $endStr]); $detailedProductionCosts = $stmt->fetchAll(); } catch (Exception $e) { $detailedProductionCosts = []; }
}

// Detailed subcontractor payments for modal
$subDetails = [];
if (tableExists($pdo, 'batch_payments')) {
    $sqlSubDetails = "SELECT bp.*, b.lod_name AS batch_name, s.company_name AS subcontractor_name FROM batch_payments bp LEFT JOIN wigs_batches b ON bp.batch_id = b.id LEFT JOIN subcontractors s ON b.subcontractor_id = s.id WHERE bp.payment_date >= ? AND bp.payment_date <= ? ORDER BY bp.payment_date DESC";
    try { $stmt = $pdo->prepare($sqlSubDetails); $stmt->execute([$startStr, $endStr]); $subDetails = $stmt->fetchAll(); } catch (Exception $e) { $subDetails = []; }
}

// Detailed accessory purchases for modal
$accessoryDetails = [];
if (tableExists($pdo, 'accessory_purchases')) {
    $sqlAccessoryDetails = "SELECT id, purchase_date, total_cost, notes FROM accessory_purchases WHERE purchase_date >= ? AND purchase_date <= ? ORDER BY purchase_date DESC";
    try { $stmt = $pdo->prepare($sqlAccessoryDetails); $stmt->execute([$startStr, $endStr]); $accessoryDetails = $stmt->fetchAll(); } catch (Exception $e) { $accessoryDetails = []; }
}

// Recent transactions for tables
$recentPOs = [];
if (tableExists($pdo, 'purchase_orders')) {
    $sqlRecentPOs = "SELECT reference_no, product_name, total_amount, payment_status FROM purchase_orders WHERE order_date >= ? ORDER BY order_date DESC LIMIT 5";
    try { $stmt = $pdo->prepare($sqlRecentPOs); $stmt->execute([$startStr]); $recentPOs = $stmt->fetchAll(); } catch (Exception $e) { $recentPOs = []; }
}
$recentCosts = [];
if (tableExists($pdo, 'production_costs')) {
    $sqlRecentCosts = "SELECT batch_name, production_date, total_cost FROM production_costs WHERE production_date >= ? ORDER BY production_date DESC LIMIT 5";
    try { $stmt = $pdo->prepare($sqlRecentCosts); $stmt->execute([$startStr]); $recentCosts = $stmt->fetchAll(); } catch (Exception $e) { $recentCosts = []; }
}

// ==============================================
// CHART DATA GENERATION
// ==============================================
$chartLabels = [];
$revenueData = [];
$expenseData = [];

$intervalSpec = ($chartGroupBy === 'month') ? 'P1M' : 'P1D';
$period = new DatePeriod($startDate, new DateInterval($intervalSpec), $endDate->modify('+1 day'));

foreach ($period as $dt) {
    if ($chartGroupBy === 'month') {
        $key = $dt->format('Y-m');
        $label = $dt->format('M Y');
        $sqlRev = "SELECT COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE paid_amount END), 0) FROM purchase_orders WHERE DATE_FORMAT(order_date, '%Y-%m') = ?";
        $sqlProd = "SELECT COALESCE(SUM(total_cost), 0) FROM production_costs WHERE DATE_FORMAT(production_date, '%Y-%m') = ?";
        $sqlSub  = "SELECT COALESCE(SUM(amount), 0) FROM batch_payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?";
        $sqlAccess = "SELECT COALESCE(SUM(total_cost), 0) FROM accessory_purchases WHERE DATE_FORMAT(purchase_date, '%Y-%m') = ?";
    } else {
        $key = $dt->format('Y-m-d');
        $label = $dt->format('M d');
        $sqlRev = "SELECT COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE paid_amount END), 0) FROM purchase_orders WHERE DATE(order_date) = ?";
        $sqlProd = "SELECT COALESCE(SUM(total_cost), 0) FROM production_costs WHERE DATE(production_date) = ?";
        $sqlSub  = "SELECT COALESCE(SUM(amount), 0) FROM batch_payments WHERE DATE(payment_date) = ?";
        $sqlAccess = "SELECT COALESCE(SUM(total_cost), 0) FROM accessory_purchases WHERE DATE(purchase_date) = ?";
    }

    $chartLabels[] = $label;
    
    $revenueData[] = (float)fetchVal($pdo, $sqlRev, [$key]);
    $prodForPeriod = (float)fetchVal($pdo, $sqlProd, [$key]);
    $subForPeriod  = (float)fetchVal($pdo, $sqlSub, [$key]);
    $accessForPeriod = (float)fetchVal($pdo, $sqlAccess, [$key]);

    // For other expenses and salary, we distribute proportionally (simplified)
    $periodCount = iterator_count($period);
    $otherFraction = $periodCount > 0 ? $totalAmountColumns / $periodCount : 0;
    $salaryFraction = $periodCount > 0 ? $monthlySalaryPayments / $periodCount : 0;

    $expenseData[] = $prodForPeriod + $otherFraction + $salaryFraction + $subForPeriod + $accessForPeriod;
}

// Reset the currentDate after the loop
$currentDate = new DateTime();

// --- Prepare data for JavaScript ---
$jsLabels = json_encode($chartLabels);
$jsRevenue = json_encode($revenueData);
$jsExpense = json_encode($expenseData);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Overview | NexusAdmin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        /* --- CSS Variables & Reset (same as admin dashboard) --- */
        :root {
            --primary: #4F46E5; --primary-hover: #4338ca;
            --bg-body: #F3F4F6; --bg-card: #ffffff;
            --text-main: #111827; --text-muted: #6B7280;
            --border: #E5E7EB;
            --sidebar-width: 280px; --sidebar-bg: #111827; --sidebar-text: #E5E7EB;
            --header-height: 64px; --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 12px;
            --shadow: 0 2px 8px rgba(0,0,0,0.04);
            --shadow-lg: 0 10px 25px -5px rgba(0,0,0,0.1);
            --color-revenue: #10B981;
            --color-hold: #F59E0B;
            --color-expense: #EF4444;
            --color-profit: #3B82F6;
            --color-salary: #8B5CF6;
            --color-subcontractor: #EC4899;
            --color-accessory: #06B6D4;
        }
        [data-theme="dark"] {
            --bg-body: #0f172a; --bg-card: #1e293b; --text-main: #f8fafc; --text-muted: #94a3b8;
            --border: #334155; --sidebar-bg: #020617; --primary: #6366f1;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-body); color: var(--text-main); height: 100vh; display: flex; overflow: hidden; }
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button, select { font-family: inherit; cursor: pointer; }

        /* --- Sidebar (copied from admin dashboard) --- */
        body.sidebar-collapsed { --sidebar-width: 80px; }
        .sidebar { width: var(--sidebar-width); background-color: var(--sidebar-bg); color: var(--sidebar-text); display: flex; flex-direction: column; transition: width var(--transition), transform var(--transition); z-index: 50; flex-shrink: 0; white-space: nowrap; }
        .sidebar-header { height: var(--header-height); display: flex; align-items: center; padding: 0 24px; border-bottom: 1px solid rgba(255,255,255,0.05); font-weight: 700; font-size: 1.25rem; color: #fff; gap: 12px; overflow: hidden; }
        body.sidebar-collapsed .logo-text, body.sidebar-collapsed .link-text, body.sidebar-collapsed .arrow-icon { display: none; opacity: 0; }
        body.sidebar-collapsed .sidebar-header { justify-content: center; padding: 0; }
        body.sidebar-collapsed .menu-link { justify-content: center; padding: 12px 0; }
        body.sidebar-collapsed .link-content { gap: 0; }
        body.sidebar-collapsed .menu-icon { font-size: 1.5rem; margin: 0; }
        body.sidebar-collapsed .submenu { display: none !important; }

        .sidebar-menu { padding: 20px 12px; overflow-y: auto; overflow-x: hidden; flex: 1; }
        .menu-item { margin-bottom: 4px; }
        .menu-link { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border-radius: 8px; color: rgba(255,255,255,0.7); transition: all 0.2s; font-size: 0.95rem; }
        .menu-link:hover, .menu-link.active { background-color: rgba(255,255,255,0.1); color: #fff; transform: translateX(2px); }
        .link-content { display: flex; align-items: center; gap: 12px; }
        .menu-icon { font-size: 1.2rem; min-width: 24px; text-align: center; }
        .arrow-icon { transition: transform 0.3s; font-size: 0.8rem; opacity: 0.7; }
        .submenu { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-in-out; padding-left: 12px; }
        .menu-item.open > .submenu { max-height: 500px; }
        .menu-item.open > .menu-link .arrow-icon { transform: rotate(180deg); }
        .menu-item.open > .menu-link { color: #fff; }
        .submenu-link { display: block; padding: 10px 16px 10px 42px; color: rgba(255,255,255,0.5); font-size: 0.9rem; border-radius: 8px; transition: all 0.2s; }
        .submenu-link:hover { color: #fff; background: rgba(255,255,255,0.05); }

        /* --- Main Header --- */
        .main-content { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .top-header { height: var(--header-height); background: var(--bg-card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; padding: 0 32px; flex-shrink: 0; box-shadow: var(--shadow); }
        .toggle-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); display: flex; align-items: center; padding: 8px; border-radius: 8px; transition: all 0.2s; }
        .toggle-btn:hover { color: var(--primary); background: var(--bg-body); transform: translateY(-1px); }
        .header-right { display: flex; align-items: center; gap: 24px; }

        /* --- Dark Mode Toggle (same as admin) --- */
        #themeToggle { background: var(--bg-card); border: 1px solid var(--border); width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; transition: all 0.3s; cursor: pointer; color: var(--text-muted); }
        #themeToggle:hover { transform: rotate(15deg); border-color: var(--primary); color: var(--primary); }

        /* --- Profile Dropdown --- */
        .profile-container { position: relative; }
        .profile-menu { display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 8px 12px; border-radius: 12px; transition: all 0.2s; }
        .profile-menu:hover { background-color: var(--bg-body); }
        .profile-info { text-align: right; line-height: 1.2; }
        .profile-name { font-size: 0.9rem; font-weight: 600; display: block; }
        .profile-role { font-size: 0.75rem; color: var(--text-muted); }
        .profile-img { width: 42px; height: 42px; border-radius: 12px; object-fit: cover; border: 2px solid var(--border); }
        .profile-placeholder { width: 42px; height: 42px; border-radius: 12px; background: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.1rem; }
        .dropdown-menu { position: absolute; top: calc(100% + 8px); right: 0; width: 220px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.15); padding: 8px; z-index: 1000; display: none; flex-direction: column; gap: 4px; animation: fadeIn 0.2s; }
        .dropdown-menu.show { display: flex; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 12px 16px; font-size: 0.9rem; color: var(--text-main); border-radius: 8px; transition: all 0.2s; }
        .dropdown-item:hover { background-color: var(--bg-body); color: var(--primary); transform: translateX(2px); }
        .dropdown-item.danger:hover { background-color: rgba(239,68,68,0.1); color: #ef4444; }

        /* --- Content Area --- */
        .scrollable { flex: 1; overflow-y: auto; padding: 32px; }
        .page-container { max-width: 1400px; margin: 0 auto; }

        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 32px; 
            flex-wrap: wrap; 
            gap: 16px; 
        }
        .filter-form { 
            display: flex; 
            align-items: center; 
            gap: 10px; 
            background: var(--bg-card); 
            padding: 8px 16px; 
            border: 1px solid var(--border); 
            border-radius: 12px; 
            transition: all 0.2s ease;
            flex-wrap: wrap;
        }
        .filter-form:hover { border-color: var(--primary); }
        .filter-select { 
            border: none; 
            background: transparent; 
            font-size: 0.9rem; 
            color: var(--text-main); 
            padding: 4px 0; 
            outline: none; 
            cursor: pointer; 
            font-weight: 500;
            min-width: 120px;
        }
        .date-range-container {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-left: 8px;
        }
        .date-input {
            padding: 6px 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--bg-card);
            color: var(--text-main);
            font-size: 0.85rem;
            width: 120px;
        }
        .date-input:focus {
            outline: none;
            border-color: var(--primary);
        }
        .filter-btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .filter-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        .reset-btn {
            background: var(--bg-body);
            color: var(--text-muted);
            border: 1px solid var(--border);
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .reset-btn:hover {
            background: var(--bg-card);
            color: var(--text-main);
        }
        
        /* Date Range Display */
        .date-range-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        /* --- KPI Cards (clickable) – exactly like admin's stat cards --- */
        .grid { 
            display: grid; 
            grid-template-columns: repeat(12, 1fr); 
            gap: 24px; 
            margin-bottom: 32px;
        }
        .col-3 { 
            grid-column: span 3;
            min-height: 168px;
            height: 100%;
        }
        .card { 
            background: var(--bg-card); 
            border-radius: var(--radius); 
            padding: 28px; 
            border: 1px solid var(--border); 
            box-shadow: var(--shadow);
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border-color: var(--border);
        }
        .stat-card { 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between;
            height: 100%;
            cursor: pointer;
        }
        .stat-card:hover .stat-icon-container {
            transform: scale(1.05);
        }
        .stat-head { 
            color: var(--text-muted); 
            font-size: 0.85rem; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 0.5px; 
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-val { 
            font-size: 1.9rem; 
            font-weight: 700; 
            color: var(--text-main); 
            margin: 12px 0;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }
        .stat-sub { 
            font-size: 0.8rem; 
            display: flex; 
            align-items: center; 
            gap: 6px; 
            margin-top: auto;
            color: var(--text-muted);
            font-weight: 500;
        }
        .stat-icon-container {
            position: absolute;
            right: 24px;
            top: 24px;
            width: 52px;
            height: 52px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        .stat-icon {
            font-size: 1.6rem;
            transition: transform 0.3s ease;
        }
        .card:hover .stat-icon {
            transform: scale(1.1);
        }
        /* Print icon on each card */
        .print-icon {
            position: absolute;
            bottom: 24px;
            right: 24px;
            font-size: 1.2rem;
            color: var(--text-muted);
            opacity: 0.6;
            transition: all 0.2s;
            cursor: pointer;
            z-index: 10;
        }
        .print-icon:hover {
            opacity: 1;
            color: var(--primary);
            transform: scale(1.1);
        }
        /* Card-specific icon colors */
        .revenue .stat-icon-container { background: rgba(16, 185, 129, 0.12); }
        .revenue .stat-icon { color: var(--color-revenue); }
        .hold .stat-icon-container { background: rgba(245, 158, 11, 0.12); }
        .hold .stat-icon { color: var(--color-hold); }
        .expenses .stat-icon-container { background: rgba(239, 68, 68, 0.12); }
        .expenses .stat-icon { color: var(--color-expense); }
        .profit .stat-icon-container { background: rgba(59, 130, 246, 0.12); }
        .profit .stat-icon { color: var(--color-profit); }

        /* Expense breakdown on card */
        .expense-breakdown { margin-top: 14px; font-size: 0.75rem; }
        .expense-summary { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; padding-bottom: 6px; border-bottom: 1px solid rgba(0,0,0,0.04); }
        .expense-summary:last-child { border-bottom: none; }
        .expense-category { color: var(--text-muted); font-size: 0.72rem; font-weight: 500; }
        .expense-value { font-weight: 600; font-size: 0.72rem; }
        .view-details { margin-top: 10px; font-size: 0.72rem; color: var(--primary); cursor: pointer; display: inline-flex; align-items: center; gap: 4px; font-weight: 500; transition: all 0.2s; padding: 4px 8px; border-radius: 6px; }
        .view-details:hover { background: rgba(79,70,229,0.08); transform: translateX(2px); }
        .salary-debug { display: inline-flex; align-items: center; gap: 6px; background: rgba(139,92,246,0.12); padding: 2px 8px; border-radius: 10px; font-size: 0.65rem; margin-top: 4px; color: var(--color-salary); width: fit-content; border: 1px dashed rgba(139,92,246,0.3); }

        /* Chart section */
        .chart-section { background: var(--bg-card); border-radius: var(--radius); padding: 24px; border: 1px solid var(--border); margin-bottom: 32px; box-shadow: var(--shadow); }
        .chart-container { height: 300px; }

        /* Tables */
        .tables-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .table-card { background: var(--bg-card); border-radius: var(--radius); border: 1px solid var(--border); overflow: hidden; box-shadow: var(--shadow); }
        .table-header-text { padding: 20px 24px; border-bottom: 1px solid var(--border); font-weight: 600; font-size: 1rem; background: var(--bg-body); }
        table { width: 100%; border-collapse: collapse; }
        th { padding: 16px 24px; font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; background: var(--bg-body); border-bottom: 1px solid var(--border); font-weight: 600; }
        td { padding: 14px 24px; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .badge { padding: 6px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; }
        .badge-success { background: rgba(16,185,129,0.15); color: #10B981; }
        .badge-warning { background: rgba(245,158,11,0.15); color: #F59E0B; }
        .badge-danger { background: rgba(239,68,68,0.15); color: #EF4444; }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; animation: fadeIn 0.3s; }
        .modal-content { background: var(--bg-card); border-radius: 16px; width: 90%; max-width: 1000px; max-height: 80vh; overflow: hidden; box-shadow: var(--shadow-lg); animation: slideUp 0.3s; }
        .modal-header { padding: 24px 32px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--bg-body); }
        .modal-title { font-size: 1.25rem; font-weight: 600; }
        .modal-close { background: none; border: none; font-size: 1.5rem; color: var(--text-muted); cursor: pointer; padding: 8px; border-radius: 8px; transition: all 0.2s; }
        .modal-close:hover { color: var(--error); background: var(--bg-body); transform: rotate(90deg); }
        .modal-body { overflow-y: auto; max-height: calc(80vh - 130px); }
        .modal-tabs { display: flex; border-bottom: 1px solid var(--border); background: var(--bg-body); }
        .modal-tab { padding: 16px 24px; background: none; border: none; border-bottom: 3px solid transparent; color: var(--text-muted); cursor: pointer; font-weight: 500; transition: all 0.2s; }
        .modal-tab.active { color: var(--primary); border-bottom-color: var(--primary); background: var(--bg-card); }
        .tab-content { display: none; padding: 24px; }
        .tab-content.active { display: block; }
        .modal-table { width: 100%; border-collapse: collapse; }
        .modal-table th { padding: 12px 16px; font-size: 0.8rem; color: var(--text-muted); border-bottom: 2px solid var(--border); }
        .modal-table td { padding: 12px 16px; border-bottom: 1px solid var(--border); }
        .modal-table tr:hover { background: var(--bg-body); }

        .overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); z-index: 45; display: none; }

        /* Print styles */
        @media print {
            body * { visibility: hidden; }
            .print-report, .print-report * { visibility: visible; }
            .print-report { position: absolute; left: 0; top: 0; width: 100%; background: white; padding: 2cm; font-size: 12pt; }
            .print-report .letterhead { display: flex; justify-content: space-between; border-bottom: 2px solid #000; margin-bottom: 20px; padding-bottom: 10px; }
            .print-report .letterhead h1 { font-size: 24pt; color: #000; }
            .print-report .letterhead .date { font-size: 10pt; color: #333; }
            .print-report table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            .print-report th { background: #f0f0f0; font-weight: bold; padding: 8px; border: 1px solid #ccc; }
            .print-report td { padding: 6px; border: 1px solid #ccc; }
            .print-report .total-row { font-weight: bold; background: #f9f9f9; }
        }

        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (max-width: 1200px) { .grid .col-3 { grid-column: span 6; } }
        @media (max-width: 1024px) { .tables-grid { grid-template-columns: 1fr; } }
        @media (max-width: 768px) {
            body.sidebar-collapsed { --sidebar-width: 280px; }
            .sidebar { position: fixed; left: -280px; height: 100%; }
            body.mobile-open .sidebar { transform: translateX(280px); }
            body.mobile-open .overlay { display: block; }
            .grid .col-3 { grid-column: span 12; }
            .profile-info { display: none; }
        }
    </style>
</head>
<body>
    <div class="overlay" id="overlay"></div>

    <!-- Sidebar (same as admin) -->
    <?php include 'sidenavbar.php'; ?>

    <div class="main-content">
        <header class="top-header">
            <div style="display: flex; align-items: center; gap: 16px;">
                <button class="toggle-btn" id="sidebarToggle"><i class="ph ph-list"></i></button>
                <div style="display: flex; align-items: center; gap: 8px;"><div style="width:8px; height:8px; background:var(--primary); border-radius:50%;"></div><span style="font-size:0.9rem; color:var(--text-muted);">Financial Overview</span></div>
            </div>
            <div class="header-right">
                <button id="themeToggle" title="Toggle Theme"><i class="ph ph-moon" id="themeIcon"></i></button>
                <div class="profile-container" id="profileContainer">
                    <div class="profile-menu" onclick="toggleProfileMenu()">
                        <div class="profile-info"><span class="profile-name"><?= e($_SESSION['username']) ?></span><span class="profile-role"><?= ucfirst(e($_SESSION['role'])) ?></span></div>
                        <?php if (!empty($userPhoto)): ?><img src="<?= e($userPhoto) ?>" alt="Profile" class="profile-img"><?php else: ?><div class="profile-placeholder"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div><?php endif; ?>
                    </div>
                    <div class="dropdown-menu" id="profileDropdown">
                        <a href="admin_dashboard.php" class="dropdown-item"><i class="ph ph-house"></i> Dashboard</a>
                        <a href="profile_settings.php" class="dropdown-item"><i class="ph ph-user-gear"></i> Profile Settings</a>
                        <div style="border-top:1px solid var(--border); margin:4px 0;"></div>
                        <a href="?action=logout" class="dropdown-item danger"><i class="ph ph-sign-out"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="scrollable">
            <div class="page-container">
                <div class="page-header">
                    <div>
                        <h1 style="font-size: 1.6rem; font-weight: 700; margin-bottom: 4px;">Financial Overview</h1>
                        <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.4;">
                            Revenue, expenses & profit • 
                            <span style="color: var(--primary); font-weight: 500;"><?php echo ucfirst($filter); ?> View</span>
                        </p>
                        <?php if ($filter === 'custom'): ?>
                        <div class="date-range-display">
                            <i class="ph ph-calendar-blank"></i>
                            <span><?php echo $displayDateRange; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <form method="GET" class="filter-form" id="dashboardFilter">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="ph ph-calendar-blank" style="color: var(--text-muted); font-size: 0.9rem;"></i>
                            <select name="range" class="filter-select" id="rangeSelect" onchange="toggleCustomDates()">
                                <option value="today" <?php if($filter=='today') echo 'selected'; ?>>Today</option>
                                <option value="week" <?php if($filter=='week') echo 'selected'; ?>>Last 7 Days</option>
                                <option value="month" <?php if($filter=='month') echo 'selected'; ?>>This Month</option>
                                <option value="year" <?php if($filter=='year') echo 'selected'; ?>>This Year</option>
                                <option value="all" <?php if($filter=='all') echo 'selected'; ?>>All Time</option>
                                <option value="custom" <?php if($filter=='custom') echo 'selected'; ?>>Custom Range</option>
                            </select>
                        </div>
                        
                        <div class="date-range-container" id="customDates" style="<?php echo ($filter == 'custom') ? 'display: flex;' : 'display: none;'; ?>">
                            <input type="text" name="start_date" class="date-input" id="startDate" 
                                   placeholder="Start Date" value="<?php echo $customStart ?: $startDate->format('Y-m-d'); ?>">
                            <span style="color: var(--text-muted);">to</span>
                            <input type="text" name="end_date" class="date-input" id="endDate" 
                                   placeholder="End Date" value="<?php echo $customEnd ?: $endDate->format('Y-m-d'); ?>">
                            <button type="submit" class="filter-btn">
                                <i class="ph ph-check"></i>
                                <span>Apply</span>
                            </button>
                            <button type="button" class="reset-btn" onclick="resetFilter()">
                                <i class="ph ph-arrow-clockwise"></i>
                                <span>Reset</span>
                            </button>
                        </div>
                        <input type="hidden" name="refresh" value="1">
                    </form>
                </div>

                <!-- KPI Cards – using same grid as admin dashboard -->
                <div class="grid">
                    <!-- Paid Revenue -->
                    <div class="card stat-card col-3 revenue" onclick="showModal('paid')">
                        <div class="stat-head"><i class="ph ph-currency-dollar"></i> Paid Revenue</div>
                        <div class="stat-val text-green">$<?= number_format($boxPaid,2) ?></div>
                        <div class="stat-sub"><i class="ph ph-trend-up"></i> Received Amount</div>
                        <div class="stat-icon-container"><i class="ph ph-currency-dollar stat-icon"></i></div>
                        <div class="print-icon" onclick="event.stopPropagation(); printMetric('paid')" title="Print this report"><i class="ph ph-printer"></i></div>
                    </div>
                    <!-- Hold Payments -->
                    <div class="card stat-card col-3 hold" onclick="showModal('hold')">
                        <div class="stat-head"><i class="ph ph-clock"></i> Hold Payments</div>
                        <div class="stat-val text-orange">$<?= number_format($boxHold,2) ?></div>
                        <div class="stat-sub"><i class="ph ph-hourglass"></i> Pending Collection</div>
                        <div class="stat-icon-container"><i class="ph ph-clock stat-icon"></i></div>
                        <div class="print-icon" onclick="event.stopPropagation(); printMetric('hold')" title="Print this report"><i class="ph ph-printer"></i></div>
                    </div>
                    <!-- Expenses -->
                    <div class="card stat-card col-3 expenses" onclick="showModal('expense')">
                        <div class="stat-head"><i class="ph ph-trend-up"></i> Total Expenses</div>
                        <div class="stat-val text-red">$<?= number_format($boxExpense,2) ?></div>
                        
                        <!-- Salary Debug Info - Shows EVERY ROW COUNTED -->
                        <div class="salary-debug"><i class="ph ph-currency-dollar" style="font-size:0.7rem;"></i> Salary: $<?= number_format($monthlySalaryPayments,2) ?> (<?= $salaryCount ?> rows)</div>
                        <?php if ($subcontractorPayments > 0): ?>
                        <div class="salary-debug" style="background:rgba(236,72,153,0.12); color:var(--color-subcontractor);"><i class="ph ph-handshake" style="font-size:0.7rem;"></i> Subcontractor: $<?= number_format($subcontractorPayments,2) ?></div>
                        <?php endif; ?>
                        <?php if ($accessoryCost > 0): ?>
                        <div class="salary-debug" style="background:rgba(6,182,212,0.12); color:var(--color-accessory);"><i class="ph ph-package" style="font-size:0.7rem;"></i> Accessories: $<?= number_format($accessoryCost,2) ?></div>
                        <?php endif; ?>
                        
                        <div class="expense-breakdown">
                            <div class="expense-summary"><span class="expense-category">Production</span><span class="expense-value">$<?= number_format($prodCost,2) ?></span></div>
                            <?php if ($monthlySalaryPayments>0): ?>
                            <div class="expense-summary"><span class="expense-category">Salaries</span><span class="expense-value" style="color:var(--color-salary);">$<?= number_format($monthlySalaryPayments,2) ?> <span style="font-size:0.6rem;">(<?= $salaryCount ?>)</span></span></div>
                            <?php endif; ?>
                            <?php if ($subcontractorPayments>0): ?>
                            <div class="expense-summary"><span class="expense-category">Subcontractor</span><span class="expense-value" style="color:var(--color-subcontractor);">$<?= number_format($subcontractorPayments,2) ?></span></div>
                            <?php endif; ?>
                            <?php if ($accessoryCost>0): ?>
                            <div class="expense-summary"><span class="expense-category">Accessories</span><span class="expense-value" style="color:var(--color-accessory);">$<?= number_format($accessoryCost,2) ?></span></div>
                            <?php endif; ?>
                            <div class="expense-summary"><span class="expense-category">Other</span><span class="expense-value">$<?= number_format($totalAmountColumns,2) ?></span></div>
                            <div class="view-details"><span>View All Details</span><i class="ph ph-arrow-right" style="font-size:0.7rem;"></i></div>
                        </div>
                        
                        <div class="stat-icon-container"><i class="ph ph-trend-up stat-icon"></i></div>
                        <div class="print-icon" onclick="event.stopPropagation(); printMetric('expense')" title="Print this report"><i class="ph ph-printer"></i></div>
                    </div>
                    <!-- Net Profit -->
                    <div class="card stat-card col-3 profit" onclick="showModal('profit')">
                        <div class="stat-head"><i class="ph ph-chart-line-up"></i> Net Profit</div>
                        <div class="stat-val text-blue">$<?= number_format($boxNetProfit,2) ?></div>
                        <div class="stat-sub <?= $boxNetProfit>=0?'trend-up':'trend-down' ?>">
                            <i class="ph ph-<?= $boxNetProfit>=0?'trend-up':'trend-down' ?>"></i>
                            <span><?= $boxNetProfit>=0?'+':'' ?><?= number_format(($boxNetProfit/($boxPaid?:1))*100,1) ?>%</span>
                        </div>
                        <div class="stat-icon-container"><i class="ph ph-chart-line-up stat-icon"></i></div>
                        <div class="print-icon" onclick="event.stopPropagation(); printMetric('profit')" title="Print this report"><i class="ph ph-printer"></i></div>
                    </div>
                </div>

                <!-- Chart Section -->
                <div class="chart-section">
                    <h2 style="margin-bottom:16px; font-size:1.1rem; font-weight:600;">Revenue vs Expenses</h2>
                    <div class="chart-container"><canvas id="financialChart"></canvas></div>
                </div>

                <!-- Recent Transactions Tables -->
                <div class="tables-grid">
                    <div class="table-card">
                        <div class="table-header-text">Recent Purchase Orders</div>
                        <table>
                            <thead><tr><th>Reference</th><th>Product</th><th>Amount</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if (empty($recentPOs)): ?><tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:40px;">No records found</td></tr>
                                <?php else: foreach ($recentPOs as $po): ?>
                                    <tr><td style="font-weight:500;"><?= e($po['reference_no']) ?></td><td><?= e($po['product_name']?:'N/A') ?></td><td style="font-weight:600;">$<?= number_format($po['total_amount'],2) ?></td>
                                    <td><span class="badge badge-<?= $po['payment_status']=='Paid'?'success':($po['payment_status']=='Unpaid'?'danger':'warning') ?>"><?= e($po['payment_status']) ?></span></td></tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-card">
                        <div class="table-header-text">Recent Production Costs</div>
                        <table>
                            <thead><tr><th>Batch Name</th><th>Date</th><th>Cost</th></tr></thead>
                            <tbody>
                                <?php if (empty($recentCosts)): ?><tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding:40px;">No records found</td></tr>
                                <?php else: foreach ($recentCosts as $cost): ?>
                                    <tr><td style="font-weight:500;"><?= e($cost['batch_name']?:'Unnamed') ?></td><td><?= date('M d, Y', strtotime($cost['production_date'])) ?></td><td style="font-weight:600; color:var(--color-expense);">$<?= number_format($cost['total_cost'],2) ?></td></tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Dynamic Modal -->
    <div class="modal" id="dynamicModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Modal Title</h2>
                <button class="modal-close" id="modalClose">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>

    <script>
        // --- Modal Data (PHP to JS) ---
        const modalData = {
            paid: <?= json_encode($paidOrders) ?>,
            hold: <?= json_encode($holdOrders) ?>,
            expense: {
                production: <?= json_encode($detailedProductionCosts) ?>,
                salaryRows: <?= json_encode($salaryRows) ?>,
                subDetails: <?= json_encode($subDetails) ?>,
                accessoryDetails: <?= json_encode($accessoryDetails) ?>,
                expenseDetails: <?= json_encode($expenseDetails) ?>,
                totalSalary: <?= $monthlySalaryPayments ?>,
                salaryCount: <?= $salaryCount ?>,
                productionTotal: <?= $prodCost ?>,
                subcontractorTotal: <?= $subcontractorPayments ?>,
                accessoryTotal: <?= $accessoryCost ?>,
                otherExpenses: <?= $totalAmountColumns ?>,
                totalExpense: <?= $boxExpense ?>
            },
            profit: {
                revenue: <?= $boxPaid ?>,
                expenses: <?= $boxExpense ?>,
                net: <?= $boxNetProfit ?>,
                salary: <?= $monthlySalaryPayments ?>,
                subcontractor: <?= $subcontractorPayments ?>,
                accessory: <?= $accessoryCost ?>,
                production: <?= $prodCost ?>,
                other: <?= $totalAmountColumns ?>
            }
        };

        // --- Theme, Sidebar, Profile (same as admin) ---
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const htmlElement = document.documentElement;
        const currentTheme = localStorage.getItem('theme') || 'light';
        if (currentTheme === 'dark') { htmlElement.setAttribute('data-theme', 'dark'); themeIcon.className = 'ph ph-sun'; }
        else { themeIcon.className = 'ph ph-moon'; }
        themeToggle.addEventListener('click', () => {
            const isDark = htmlElement.getAttribute('data-theme') === 'dark';
            if (isDark) { htmlElement.removeAttribute('data-theme'); themeIcon.className = 'ph ph-moon'; localStorage.setItem('theme', 'light'); }
            else { htmlElement.setAttribute('data-theme', 'dark'); themeIcon.className = 'ph ph-sun'; localStorage.setItem('theme', 'dark'); }
        });

        const sidebarToggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('overlay');
        const body = document.body;
        sidebarToggle.addEventListener('click', () => {
            if (window.innerWidth <= 768) { body.classList.toggle('mobile-open'); body.classList.remove('sidebar-collapsed'); }
            else { body.classList.toggle('sidebar-collapsed'); if(body.classList.contains('sidebar-collapsed')) document.querySelectorAll('.menu-item.open').forEach(i=>i.classList.remove('open')); }
        });
        overlay.addEventListener('click', () => { body.classList.remove('mobile-open'); closeAllDropdowns(); closeModal(); });

        function toggleProfileMenu() {
            const dd = document.getElementById('profileDropdown');
            const isShown = dd.classList.contains('show');
            closeAllDropdowns();
            if (!isShown) dd.classList.add('show');
        }
        function closeAllDropdowns() { document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.remove('show')); }
        window.addEventListener('click', e => { if (!document.getElementById('profileContainer').contains(e.target)) closeAllDropdowns(); });

        document.querySelectorAll('.menu-item.has-submenu > .menu-link').forEach(link => {
            link.addEventListener('click', (e) => { e.preventDefault(); link.parentElement.classList.toggle('open'); });
        });

        // --- Date range picker ---
        document.addEventListener('DOMContentLoaded', function() {
            const startDatePicker = flatpickr("#startDate", { dateFormat: "Y-m-d", maxDate: "today" });
            const endDatePicker = flatpickr("#endDate", { dateFormat: "Y-m-d", maxDate: "today" });
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            if (startDate) endDatePicker.set('minDate', startDate);
            if (endDate) startDatePicker.set('maxDate', endDate);
        });

        function toggleCustomDates() {
            const rangeSelect = document.getElementById('rangeSelect');
            const customDates = document.getElementById('customDates');
            if (rangeSelect.value === 'custom') customDates.style.display = 'flex';
            else { customDates.style.display = 'none'; document.getElementById('dashboardFilter').submit(); }
        }
        function resetFilter() { window.location.href = 'fin_overview.php'; }

        // --- Modal functions (unchanged) ---
        const dynamicModal = document.getElementById('dynamicModal');
        const modalClose = document.getElementById('modalClose');
        function showModal(cardType) {
            const titleEl = document.getElementById('modalTitle');
            const bodyEl = document.getElementById('modalBody');
            let title = '', content = '';
            switch(cardType) {
                case 'paid': title = 'Paid Revenue Details'; content = generatePaidContent(); break;
                case 'hold': title = 'Hold Payments Details'; content = generateHoldContent(); break;
                case 'expense': title = 'Expense Breakdown'; content = generateExpenseContent(); break;
                case 'profit': title = 'Net Profit Breakdown'; content = generateProfitContent(); break;
            }
            titleEl.textContent = title;
            bodyEl.innerHTML = content;
            attachTabListeners();
            dynamicModal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        function closeModal() { dynamicModal.style.display = 'none'; document.body.style.overflow = 'auto'; }
        modalClose.addEventListener('click', closeModal);
        dynamicModal.addEventListener('click', e => { if (e.target === dynamicModal) closeModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

        // --- Content generators (same as before) ---
        function generatePaidContent() {
            if (!modalData.paid.length) return '<p style="padding:20px; text-align:center; color:var(--text-muted);">No paid orders found.</p>';
            let html = '<table class="modal-table"><thead><tr><th>Ref</th><th>Product</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
            modalData.paid.forEach(o => {
                html += `<tr><td style="font-weight:500;">${o.reference_no}</td><td>${o.product_name||'N/A'}</td><td>${new Date(o.order_date).toLocaleDateString()}</td><td style="font-weight:600; color:var(--color-revenue);">$${parseFloat(o.total_amount).toFixed(2)}</td><td><span class="badge badge-success">${o.payment_status}</span></td></tr>`;
            });
            html += '</tbody></table>';
            return html;
        }
        function generateHoldContent() {
            if (!modalData.hold.length) return '<p style="padding:20px; text-align:center; color:var(--text-muted);">No hold orders found.</p>';
            let html = '<table class="modal-table"><thead><tr><th>Ref</th><th>Product</th><th>Date</th><th>Amount</th><th>Status</th></tr></thead><tbody>';
            modalData.hold.forEach(o => {
                const cls = o.payment_status === 'Partial' ? 'badge-warning' : 'badge-danger';
                html += `<tr><td style="font-weight:500;">${o.reference_no}</td><td>${o.product_name||'N/A'}</td><td>${new Date(o.order_date).toLocaleDateString()}</td><td style="font-weight:600; color:var(--color-hold);">$${parseFloat(o.total_amount).toFixed(2)}</td><td><span class="badge ${cls}">${o.payment_status}</span></td></tr>`;
            });
            html += '</tbody></table>';
            return html;
        }
        function generateExpenseContent() {
            const e = modalData.expense;
            return `
                <div class="modal-tabs">
                    <button class="modal-tab active" data-tab="summary">Summary</button>
                    <button class="modal-tab" data-tab="production">Production (${e.production.length})</button>
                    <button class="modal-tab" data-tab="salaries">Salaries (${e.salaryCount})</button>
                    <button class="modal-tab" data-tab="subcontractor">Subcontractor (${e.subDetails.length})</button>
                    <button class="modal-tab" data-tab="accessory">Accessories (${e.accessoryDetails.length})</button>
                    <button class="modal-tab" data-tab="other">Other</button>
                </div>
                <div class="tab-content active" id="summaryTab">
                    <div style="background:rgba(239,68,68,0.1); padding:20px; border-radius:12px; margin-bottom:24px;">
                        <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:8px;">Total Expenses</div>
                        <div style="font-size:1.8rem; font-weight:700; color:var(--color-expense);">$${e.totalExpense.toFixed(2)}</div>
                        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:4px;">• Salary: ${e.salaryCount} rows • Subcontractor: ${e.subDetails.length} payments • Accessories: ${e.accessoryDetails.length} purchases</div>
                    </div>
                    <div style="margin-bottom:24px;">
                        <div style="font-size:0.9rem; color:var(--text-muted); margin-bottom:16px;"><i class="ph ph-list-dashes"></i> Breakdown</div>
                        <div style="display:flex; flex-direction:column; gap:12px;">
                            <div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--color-expense);"><div><div>Production</div><div style="font-size:0.75rem; color:var(--text-muted);">Manufacturing</div></div><div style="font-weight:700; color:var(--color-expense);">$${e.productionTotal.toFixed(2)}</div></div>
                            ${e.totalSalary>0?`<div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--color-salary);"><div><div style="color:var(--color-salary);">Salaries</div><div style="font-size:0.75rem; color:var(--text-muted);">${e.salaryCount} payments</div></div><div style="font-weight:700; color:var(--color-salary);">$${e.totalSalary.toFixed(2)}</div></div>`:''}
                            ${e.subcontractorTotal>0?`<div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--color-subcontractor);"><div><div>Subcontractor</div><div style="font-size:0.75rem; color:var(--text-muted);">Batch payments</div></div><div style="font-weight:700; color:var(--color-subcontractor);">$${e.subcontractorTotal.toFixed(2)}</div></div>`:''}
                            ${e.accessoryTotal>0?`<div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--color-accessory);"><div><div>Accessories</div><div style="font-size:0.75rem; color:var(--text-muted);">Purchases</div></div><div style="font-weight:700; color:var(--color-accessory);">$${e.accessoryTotal.toFixed(2)}</div></div>`:''}
                            ${e.otherExpenses>0?`<div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--primary);"><div><div>Other</div><div style="font-size:0.75rem; color:var(--text-muted);">Operational</div></div><div style="font-weight:700;">$${e.otherExpenses.toFixed(2)}</div></div>`:''}
                        </div>
                    </div>
                    <div style="padding:16px; background:var(--bg-body); border-radius:8px;">
                        <h3 style="margin-bottom:12px;">Distribution</h3>
                        <div style="display:flex; align-items:center; gap:24px;">
                            <div style="width:120px; height:120px;"><canvas id="expenseChart"></canvas></div>
                            <div style="flex:1;">${[
                                ['Production', e.productionTotal, '#EF4444'],
                                ['Salaries', e.totalSalary, '#8B5CF6'],
                                ['Subcontractor', e.subcontractorTotal, '#EC4899'],
                                ['Accessories', e.accessoryTotal, '#06B6D4'],
                                ['Other', e.otherExpenses, '#4F46E5']
                            ].filter(([_,v]) => v>0).map(([l,v,c]) => `<div style="display:flex; align-items:center; gap:8px;"><div style="width:12px; height:12px; background:${c}; border-radius:2px;"></div><div style="flex:1;">${l}</div><div style="font-weight:600;">$${v.toFixed(2)}</div></div>`).join('')}</div>
                        </div>
                    </div>
                </div>
                <div class="tab-content" id="productionTab">${e.production.length ? `<table class="modal-table"><thead><tr><th>Batch</th><th>Date</th><th>Cost</th><th>Notes</th></tr></thead><tbody>${e.production.map(p => `<tr><td>${p.batch_name||'Unnamed'}</td><td>${new Date(p.production_date).toLocaleDateString()}</td><td style="color:var(--color-expense);">$${parseFloat(p.total_cost).toFixed(2)}</td><td>${p.notes||''}</td></tr>`).join('')}</tbody></table>` : '<p style="padding:20px; text-align:center; color:var(--text-muted);">No production costs.</p>'}</div>
                <div class="tab-content" id="salariesTab">${e.salaryCount ? `<div style="background:rgba(139,92,246,0.05); padding:16px; border-radius:8px; margin-bottom:16px;"><div style="display:flex; justify-content:space-between;"><div><div style="color:var(--color-salary);">Total Salary</div><div style="font-size:1.5rem; font-weight:700; color:var(--color-salary);">$${e.totalSalary.toFixed(2)}</div></div><div><div>Rows</div><div style="font-size:1.2rem; font-weight:700;">${e.salaryCount}</div></div></div></div><div style="overflow-x:auto;"><table class="modal-table"><thead><tr><th>#</th><th>Employee</th><th>Date</th><th>Amount</th></tr></thead><tbody>${e.salaryRows.slice(0,50).map((r,i)=>`<tr><td>${i+1}</td><td>${r.employee_id||'N/A'}</td><td>${r.payment_date?new Date(r.payment_date).toLocaleDateString():'N/A'}</td><td style="color:var(--color-salary);">$${parseFloat(r.amount||0).toFixed(2)}</td></tr>`).join('')}</tbody></table></div>` : '<p style="padding:20px; text-align:center; color:var(--text-muted);">No salary payments.</p>'}</div>
                <div class="tab-content" id="subcontractorTab">${e.subDetails.length ? `<div style="background:rgba(236,72,153,0.05); padding:16px; border-radius:8px; margin-bottom:16px;"><div style="display:flex; justify-content:space-between;"><div><div style="color:var(--color-subcontractor);">Total Subcontractor</div><div style="font-size:1.5rem; font-weight:700; color:var(--color-subcontractor);">$${e.subcontractorTotal.toFixed(2)}</div></div><div><div>Payments</div><div style="font-size:1.2rem; font-weight:700;">${e.subDetails.length}</div></div></div></div><table class="modal-table"><thead><tr><th>Batch</th><th>Subcontractor</th><th>Date</th><th>Amount</th></tr></thead><tbody>${e.subDetails.map(p => `<tr><td>${p.batch_name||'N/A'}</td><td>${p.subcontractor_name||'N/A'}</td><td>${new Date(p.payment_date).toLocaleDateString()}</td><td style="color:var(--color-subcontractor);">$${parseFloat(p.amount).toFixed(2)}</td></tr>`).join('')}</tbody></table>` : '<p style="padding:20px; text-align:center; color:var(--text-muted);">No subcontractor payments.</p>'}</div>
                <div class="tab-content" id="accessoryTab">${e.accessoryDetails.length ? `<div style="background:rgba(6,182,212,0.05); padding:16px; border-radius:8px; margin-bottom:16px;"><div style="display:flex; justify-content:space-between;"><div><div style="color:var(--color-accessory);">Total Accessories</div><div style="font-size:1.5rem; font-weight:700; color:var(--color-accessory);">$${e.accessoryTotal.toFixed(2)}</div></div><div><div>Purchases</div><div style="font-size:1.2rem; font-weight:700;">${e.accessoryDetails.length}</div></div></div></div><table class="modal-table"><thead><tr><th>Date</th><th>Amount</th><th>Notes</th></tr></thead><tbody>${e.accessoryDetails.map(p => `<tr><td>${new Date(p.purchase_date).toLocaleDateString()}</td><td style="color:var(--color-accessory);">$${parseFloat(p.total_cost).toFixed(2)}</td><td>${p.notes||''}</td></tr>`).join('')}</tbody></table>` : '<p style="padding:20px; text-align:center; color:var(--text-muted);">No accessory purchases.</p>'}</div>
                <div class="tab-content" id="otherTab">${e.otherExpenses>0 ? `<div style="background:rgba(79,70,229,0.05); padding:16px; border-radius:8px; margin-bottom:16px;"><div style="font-size:1.5rem; font-weight:700; color:var(--primary);">$${e.otherExpenses.toFixed(2)}</div></div><div style="display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:12px;">${Object.entries(e.expenseDetails).filter(([k]) => !['salary_payments','subcontractor_payments','accessory_purchases'].includes(k)).map(([t,a]) => `<div style="background:var(--bg-body); padding:16px; border-radius:8px; border:1px solid var(--border);"><div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:4px;">${t.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase())}</div><div style="font-size:1.1rem; font-weight:700;">$${parseFloat(a).toFixed(2)}</div></div>`).join('')}</div>` : '<p style="padding:20px; text-align:center; color:var(--text-muted);">No other expenses.</p>'}</div>
            `;
        }
        function generateProfitContent() {
            const p = modalData.profit;
            const margin = p.revenue > 0 ? ((p.net / p.revenue) * 100).toFixed(2) : 0;
            return `
                <div style="padding:24px;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px; margin-bottom:32px;">
                        <div style="background:var(--bg-body); padding:20px; border-radius:8px; border-left:4px solid var(--color-revenue);"><div style="color:var(--text-muted);">Revenue</div><div style="font-size:2rem; font-weight:700; color:var(--color-revenue);">$${p.revenue.toFixed(2)}</div></div>
                        <div style="background:var(--bg-body); padding:20px; border-radius:8px; border-left:4px solid var(--color-expense);"><div style="color:var(--text-muted);">Expenses</div><div style="font-size:2rem; font-weight:700; color:var(--color-expense);">$${p.expenses.toFixed(2)}</div></div>
                    </div>
                    <div style="background:var(--bg-body); padding:20px; border-radius:8px; margin-bottom:32px; border-left:4px solid ${p.net>=0?'var(--color-profit)':'var(--color-expense)'};">
                        <div style="color:var(--text-muted);">Net Profit</div>
                        <div style="font-size:2.5rem; font-weight:700; color:${p.net>=0?'var(--color-profit)':'var(--color-expense)'};">$${p.net.toFixed(2)}</div>
                        <div style="margin-top:8px;">Profit Margin: ${margin}% • <span style="color:${p.net>=0?'var(--color-revenue)':'var(--color-expense)'};">${p.net>=0?'Profitable':'Loss'}</span></div>
                    </div>
                    <div><h4 style="margin-bottom:16px;">Expense Breakdown</h4><div style="display:flex; flex-direction:column; gap:12px;">
                        <div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--color-expense);"><div><div>Production</div><div style="font-size:0.75rem; color:var(--text-muted);">Manufacturing</div></div><div style="font-weight:700; color:var(--color-expense);">$${p.production.toFixed(2)}</div></div>
                        ${p.salary>0?`<div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--color-salary);"><div><div style="color:var(--color-salary);">Salaries</div><div style="font-size:0.75rem; color:var(--text-muted);">All payments</div></div><div style="font-weight:700; color:var(--color-salary);">$${p.salary.toFixed(2)}</div></div>`:''}
                        ${p.subcontractor>0?`<div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--color-subcontractor);"><div><div>Subcontractor</div><div style="font-size:0.75rem; color:var(--text-muted);">Batch payments</div></div><div style="font-weight:700; color:var(--color-subcontractor);">$${p.subcontractor.toFixed(2)}</div></div>`:''}
                        ${p.accessory>0?`<div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--color-accessory);"><div><div>Accessories</div><div style="font-size:0.75rem; color:var(--text-muted);">Purchases</div></div><div style="font-weight:700; color:var(--color-accessory);">$${p.accessory.toFixed(2)}</div></div>`:''}
                        ${p.other>0?`<div style="display:flex; justify-content:space-between; padding:14px 16px; background:var(--bg-body); border-radius:10px; border-left:4px solid var(--primary);"><div><div>Other</div><div style="font-size:0.75rem; color:var(--text-muted);">Operational</div></div><div style="font-weight:700;">$${p.other.toFixed(2)}</div></div>`:''}
                    </div></div>
                </div>
            `;
        }

        function attachTabListeners() {
            document.querySelectorAll('#dynamicModal .modal-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    document.querySelectorAll('#dynamicModal .modal-tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    document.querySelectorAll('#dynamicModal .tab-content').forEach(c => {
                        c.classList.remove('active');
                        if (c.id === tabId+'Tab') c.classList.add('active');
                    });
                    if (tabId === 'summary') setTimeout(() => renderExpenseChart(), 100);
                });
            });
        }

        function renderExpenseChart() {
            const ctx = document.getElementById('expenseChart')?.getContext('2d');
            if (!ctx) return;
            if (window.expenseChart) window.expenseChart.destroy();
            const e = modalData.expense;
            const data = [], labels = [], colors = [];
            if (e.productionTotal>0) { data.push(e.productionTotal); labels.push('Production'); colors.push('#EF4444'); }
            if (e.totalSalary>0) { data.push(e.totalSalary); labels.push('Salaries'); colors.push('#8B5CF6'); }
            if (e.subcontractorTotal>0) { data.push(e.subcontractorTotal); labels.push('Subcontractor'); colors.push('#EC4899'); }
            if (e.accessoryTotal>0) { data.push(e.accessoryTotal); labels.push('Accessories'); colors.push('#06B6D4'); }
            if (e.otherExpenses>0) { data.push(e.otherExpenses); labels.push('Other'); colors.push('#4F46E5'); }
            if (data.length===0) { data.push(1); labels.push('No Data'); colors.push('#E5E7EB'); }
            window.expenseChart = new Chart(ctx, {
                type: 'doughnut',
                data: { labels, datasets: [{ data, backgroundColor: colors, borderWidth: 0, hoverOffset: 15 }] },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, cutout: '70%' }
            });
        }

        // --- Print function ---
        function printMetric(type) {
            const title = type === 'paid' ? 'Paid Revenue' : type === 'hold' ? 'Hold Payments' : type === 'expense' ? 'Total Expenses' : 'Net Profit';
            let content = '';
            if (type === 'paid') content = generatePaidContent();
            else if (type === 'hold') content = generateHoldContent();
            else if (type === 'expense') content = generateExpenseContent();
            else if (type === 'profit') content = generateProfitContent();

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>${title} Report</title>
                    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
                    <style>
                        body { font-family: 'Inter', sans-serif; margin: 2cm; }
                        .letterhead { display: flex; justify-content: space-between; border-bottom: 2px solid #4F46E5; padding-bottom: 10px; margin-bottom: 20px; }
                        .letterhead h1 { font-size: 24pt; color: #4F46E5; margin: 0; }
                        .letterhead .date { font-size: 10pt; color: #64748b; }
                        h2 { font-size: 18pt; margin-top: 0; }
                        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                        th { background: #f1f5f9; font-weight: 600; padding: 8px; border: 1px solid #cbd5e1; }
                        td { padding: 6px; border: 1px solid #cbd5e1; }
                        .total-row { background: #fef9c3; font-weight: 600; }
                        .footer { margin-top: 30px; text-align: center; font-size: 9pt; color: #94a3b8; }
                    </style>
                </head>
                <body>
                    <div class="letterhead">
                        <div>
                            <h1>NEXUS ADMIN</h1>
                            <div style="font-size:10pt; color:#64748b;">Financial Report</div>
                        </div>
                        <div class="date">Generated: ${new Date().toLocaleDateString()}</div>
                    </div>
                    <h2>${title}</h2>
                    <p><strong>Date Range:</strong> <?= $displayDateRange ?></p>
                    ${content}
                    <div class="footer">This is a computer‑generated report. Valid without signature.</div>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.focus();
            printWindow.print();
        }

        // Main Chart
        new Chart(document.getElementById('financialChart'), {
            type: 'line',
            data: {
                labels: <?= $jsLabels ?>,
                datasets: [
                    { label: 'Revenue', data: <?= $jsRevenue ?>, borderColor: '#10B981', backgroundColor: 'rgba(16,185,129,0.1)', tension: 0.4, fill: true, borderWidth: 2 },
                    { label: 'Expenses', data: <?= $jsExpense ?>, borderColor: '#EF4444', backgroundColor: 'rgba(239,68,68,0.1)', tension: 0.4, fill: true, borderWidth: 2 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { usePointStyle: true } } },
                scales: { y: { beginAtZero: true, ticks: { callback: v => '$' + v } } }
            }
        });
    </script>
</body>
</html>