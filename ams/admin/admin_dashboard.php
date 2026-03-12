<?php
/**
 * admin_dashboard.php
 * Complete Admin Dashboard V2 with Enhanced Charts & Date Range Filtering
 * LOGIC SYNC:
 * - Paid Revenue: Based on 'purchase_orders' (paid_amount OR total if status is Paid).
 * - Hold Payments: Based on 'purchase_orders' (remaining balance).
 * - Expenses: 'production_costs' (total_cost) + ALL 'amount' columns + EVERY SINGLE Salary Payment Row Value + Accessory Purchases + Subcontractor Payments.
 * - Net Profit: Paid Revenue - Expenses.
 */

session_start();

// --- 1. AUTHENTICATION & SECURITY ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Demo Login for testing
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'Alexander Pierce';
    $_SESSION['role'] = 'admin';
    $_SESSION['email'] = 'alex@example.com';
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header("Location: admin_dashboard.php");
    exit;
}

// --- 2. DATABASE CONNECTION ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $ex) {
    die("Database connection failed: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }

function tableExists($pdo, $table) {
    try {
        $result = $pdo->query("SELECT 1 FROM $table LIMIT 1");
        return $result !== false;
    } catch (Exception $e) { return false; }
}

function fetchVal($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: 0;
    } catch (Exception $e) { return 0; }
}

// --- 4. DATE FILTER LOGIC (Enhanced with custom date range) ---
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

// --- 5. DATA GATHERING ---

// A. User Photo
$userPhoto = null;
if (tableExists($pdo, 'users') && isset($_SESSION['user_id'])) {
    $userPhoto = fetchVal(
        $pdo,
        "SELECT photo_url FROM users WHERE id = ?",
        [$_SESSION['user_id']]
    );
}


// B. Operational Counts (Based on date filter)
$totalCustomers = 0;
$totalVendors = 0;
$totalProducts = 0;
$totalEmployees = 0;

// Customers count within date range
if (tableExists($pdo, 'customers')) {
    $sql = "SELECT COUNT(*) FROM customers WHERE created_at BETWEEN ? AND ?";
    $totalCustomers = (int)fetchVal($pdo, $sql, [$startStr, $endStr]);
}

// Vendors count within date range (if vendors table has created_at)
if (tableExists($pdo, 'vendors')) {
    $sql = "SELECT COUNT(*) FROM vendors WHERE created_at BETWEEN ? AND ?";
    $totalVendors = (int)fetchVal($pdo, $sql, [$startStr, $endStr]);
}

// Products count (all time or date range based on created_at)
if (tableExists($pdo, 'products')) {
    if ($filter === 'all') {
        $totalProducts = (int)fetchVal($pdo, "SELECT COUNT(*) FROM products");
    } else {
        $sql = "SELECT COUNT(*) FROM products WHERE created_at BETWEEN ? AND ?";
        $totalProducts = (int)fetchVal($pdo, $sql, [$startStr, $endStr]);
    }
}

// Employees count (all time)
if (tableExists($pdo, 'employees')) {
    $totalEmployees = (int)fetchVal($pdo, "SELECT COUNT(*) FROM employees");
}

// C. FINANCIAL METRICS

// 1. Paid Revenue
$boxPaid = 0.0;
if (tableExists($pdo, 'purchase_orders')) {
    $sqlPaid = "SELECT COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE paid_amount END), 0) 
                FROM purchase_orders WHERE order_date >= ? AND order_date <= ?";
    $boxPaid = (float)fetchVal($pdo, $sqlPaid, [$startStr, $endStr]);
}

// 2. Hold Payments
$boxHold = 0.0;
if (tableExists($pdo, 'purchase_orders')) {
    $sqlHold = "SELECT COALESCE(SUM(CASE WHEN payment_status = 'Paid' THEN 0 ELSE total_amount - COALESCE(paid_amount, 0) END), 0)
                FROM purchase_orders WHERE order_date >= ? AND order_date <= ?";
    $boxHold = (float)fetchVal($pdo, $sqlHold, [$startStr, $endStr]);
}

// 3. Expenses
$prodCost = 0.0;
$totalAmountColumns = 0.0;

// Production Costs
if (tableExists($pdo, 'production_costs')) {
    $sqlProd = "SELECT COALESCE(SUM(total_cost), 0) FROM production_costs WHERE production_date >= ? AND production_date <= ?";
    $prodCost = (float)fetchVal($pdo, $sqlProd, [$startStr, $endStr]);
}

// ALL 'amount' columns from various tables INCLUDING LOGISTICS_EXPENSES
$amountTables = [
    'logistics_expenses' => ['column' => 'amount', 'date_column' => 'expense_date'],
    'maintenance_costs' => ['column' => 'amount', 'date_column' => 'date'],
    'utilities' => ['column' => 'amount', 'date_column' => 'billing_date'],
    'other_expenses' => ['column' => 'amount', 'date_column' => 'expense_date'],
    'rent_payments' => ['column' => 'amount', 'date_column' => 'payment_date'],
    'tax_payments' => ['column' => 'amount', 'date_column' => 'payment_date'],
    'insurance_payments' => ['column' => 'amount', 'date_column' => 'payment_date'],
    'marketing_expenses' => ['column' => 'amount', 'date_column' => 'date'],
    'office_supplies' => ['column' => 'amount', 'date_column' => 'purchase_date'],
];

$expenseDetails = [];

foreach ($amountTables as $table => $config) {
    if (tableExists($pdo, $table)) {
        $sql = "SELECT COALESCE(SUM({$config['column']}), 0) FROM $table 
                WHERE {$config['date_column']} >= ? AND {$config['date_column']} <= ?";
        $amount = (float)fetchVal($pdo, $sql, [$startStr, $endStr]);
        $totalAmountColumns += $amount;
        
        if ($amount > 0) {
            $expenseDetails[$table] = $amount;
        }
    }
}

// ==============================================
// SALARY PAYMENTS - EVERY SINGLE ROW VALUE COUNTED
// ==============================================
$monthlySalaryPayments = 0.0;
$salaryDetails = [];
$salaryCount = 0;
$salaryRows = []; // Store all individual salary rows
$salaryRowValues = []; // Store individual amount values for verification

// Check which salary table exists
$salaryTableName = '';
$amountColumn = 'amount';
$dateColumn = 'payment_date';
$employeeColumn = 'employee_id';

// Try different possible table names
$possibleTables = ['salary_payments', 'salaries', 'salary', 'employee_salaries', 'salary_transactions'];

foreach ($possibleTables as $table) {
    if (tableExists($pdo, $table)) {
        $salaryTableName = $table;
        echo "<!-- Found salary table: " . $salaryTableName . " -->\n";
        
        // Check what columns exist in this table
        try {
            $checkCols = $pdo->query("SHOW COLUMNS FROM $salaryTableName");
            $columns = $checkCols->fetchAll(PDO::FETCH_COLUMN);
            
            // Determine amount column name
            if (in_array('amount', $columns)) {
                $amountColumn = 'amount';
            } elseif (in_array('payment_amount', $columns)) {
                $amountColumn = 'payment_amount';
            } elseif (in_array('salary_amount', $columns)) {
                $amountColumn = 'salary_amount';
            }
            
            // Determine date column name
            if (in_array('payment_date', $columns)) {
                $dateColumn = 'payment_date';
            } elseif (in_array('paid_date', $columns)) {
                $dateColumn = 'paid_date';
            } elseif (in_array('created_at', $columns)) {
                $dateColumn = 'created_at';
            }
            
            // Determine employee column name
            if (in_array('employee_id', $columns)) {
                $employeeColumn = 'employee_id';
            } elseif (in_array('emp_id', $columns)) {
                $employeeColumn = 'emp_id';
            }
            
            break; // Stop checking once we find a table
        } catch (Exception $e) {
            continue;
        }
    }
}

if ($salaryTableName) {
    // GET EVERY SINGLE ROW in the date range
    $sqlAllSalaries = "SELECT * FROM $salaryTableName 
                      WHERE $dateColumn >= ? AND $dateColumn <= ?
                      ORDER BY $dateColumn DESC";
    
    try {
        $stmt = $pdo->prepare($sqlAllSalaries);
        $stmt->execute([$startStr, $endStr]);
        $salaryRows = $stmt->fetchAll();
        $salaryCount = count($salaryRows);
        
        // ==============================================
        // MANUALLY SUM UP EVERY SINGLE ROW'S AMOUNT VALUE
        // ==============================================
        $monthlySalaryPayments = 0.0;
        foreach ($salaryRows as $index => $salary) {
            // Try different possible amount column names
            $rowAmount = 0.0;
            if (isset($salary[$amountColumn])) {
                $rowAmount = (float)$salary[$amountColumn];
            } elseif (isset($salary['amount'])) {
                $rowAmount = (float)$salary['amount'];
            } elseif (isset($salary['payment_amount'])) {
                $rowAmount = (float)$salary['payment_amount'];
            }
            
            $monthlySalaryPayments += $rowAmount;
            
            // Store individual row values for debugging
            $employeeId = $salary[$employeeColumn] ?? $salary['employee_id'] ?? 'N/A';
            $paymentDate = $salary[$dateColumn] ?? $salary['payment_date'] ?? 'N/A';
            
            $salaryRowValues[] = [
                'row' => $index + 1,
                'amount' => $rowAmount,
                'employee_id' => $employeeId,
                'payment_date' => $paymentDate
            ];
        }
        
        // Debug output
        echo "<!-- ============================================== -->\n";
        echo "<!-- SALARY PAYMENTS: EVERY ROW VALUE COUNTED -->\n";
        echo "<!-- ============================================== -->\n";
        echo "<!-- Table used: " . $salaryTableName . " -->\n";
        echo "<!-- Amount column: " . $amountColumn . " -->\n";
        echo "<!-- Date column: " . $dateColumn . " -->\n";
        echo "<!-- Total rows found: " . $salaryCount . " -->\n";
        echo "<!-- Manual sum of all rows: $" . number_format($monthlySalaryPayments, 2) . " -->\n";
        
        // Show first 3 rows for verification
        if ($salaryCount > 0) {
            echo "<!-- Sample rows being counted: -->\n";
            for ($i = 0; $i < min(3, $salaryCount); $i++) {
                echo "<!-- Row " . ($i + 1) . ": Amount = $" . number_format($salaryRowValues[$i]['amount'], 2) . 
                     ", Employee ID = " . $salaryRowValues[$i]['employee_id'] . 
                     ", Date = " . $salaryRowValues[$i]['payment_date'] . " -->\n";
            }
        }
        
        // Verify with SQL SUM as well
        $sqlSum = "SELECT COALESCE(SUM($amountColumn), 0) FROM $salaryTableName 
                   WHERE $dateColumn >= ? AND $dateColumn <= ?";
        $sqlTotal = (float)fetchVal($pdo, $sqlSum, [$startStr, $endStr]);
        echo "<!-- SQL SUM for verification: $" . number_format($sqlTotal, 2) . " -->\n";
        
        // Verify manual sum matches SQL sum
        if (abs($monthlySalaryPayments - $sqlTotal) > 0.01) {
            echo "<!-- WARNING: Manual sum differs from SQL sum by $" . 
                 number_format(abs($monthlySalaryPayments - $sqlTotal), 2) . " -->\n";
        }
        
    } catch (Exception $e) {
        echo "<!-- ERROR fetching salary rows: " . $e->getMessage() . " -->\n";
        // Fallback to SQL sum if manual method fails
        $sqlSalary = "SELECT COALESCE(SUM($amountColumn), 0) FROM $salaryTableName 
                      WHERE $dateColumn >= ? AND $dateColumn <= ?";
        $monthlySalaryPayments = (float)fetchVal($pdo, $sqlSalary, [$startStr, $endStr]);
        
        $sqlCount = "SELECT COUNT(*) FROM $salaryTableName WHERE $dateColumn >= ? AND $dateColumn <= ?";
        $salaryCount = (int)fetchVal($pdo, $sqlCount, [$startStr, $endStr]);
    }
    
    // Store salary details for modal
    $salaryDetails = $salaryRows;
    
    if ($monthlySalaryPayments > 0) {
        $expenseDetails['salary_payments'] = $monthlySalaryPayments;
    }
    
    // For monthly breakdown in year view
    $salaryMonthlyDetails = [];
    if ($filter === 'year') {
        $currentYear = date('Y');
        for ($month = 1; $month <= 12; $month++) {
            $monthStart = date('Y-m-d', mktime(0, 0, 0, $month, 1, $currentYear));
            $monthEnd = date('Y-m-t', mktime(0, 0, 0, $month, 1, $currentYear));
            
            // Get ALL salary rows for the month and sum manually
            $sqlMonthRows = "SELECT * FROM $salaryTableName 
                           WHERE $dateColumn >= ? AND $dateColumn <= ?";
            $stmt = $pdo->prepare($sqlMonthRows);
            $stmt->execute([$monthStart . ' 00:00:00', $monthEnd . ' 23:59:59']);
            $monthRows = $stmt->fetchAll();
            
            $monthSalary = 0.0;
            foreach ($monthRows as $row) {
                $monthRowAmount = 0.0;
                if (isset($row[$amountColumn])) {
                    $monthRowAmount = (float)$row[$amountColumn];
                } elseif (isset($row['amount'])) {
                    $monthRowAmount = (float)$row['amount'];
                }
                $monthSalary += $monthRowAmount;
            }
            
            if ($monthSalary > 0) {
                $monthName = date('F', mktime(0, 0, 0, $month, 1));
                $salaryMonthlyDetails[$monthName] = $monthSalary;
            }
        }
    }
} else {
    echo "<!-- No salary table found! Checked: " . implode(", ", $possibleTables) . " -->\n";
}

// ==============================================
// ACCESSORY PURCHASES COST
// ==============================================
$accessoryCost = 0.0;
if (tableExists($pdo, 'accessory_purchases')) {
    $sqlAccessory = "SELECT COALESCE(SUM(total_cost), 0) FROM accessory_purchases 
                     WHERE purchase_date >= ? AND purchase_date <= ?";
    $accessoryCost = (float)fetchVal($pdo, $sqlAccessory, [$startStr, $endStr]);
    
    if ($accessoryCost > 0) {
        $expenseDetails['accessory_purchases'] = $accessoryCost;
    }
}

// ==============================================
// SUBCONTRACTOR PAYMENTS (NEW)
// ==============================================
$subcontractorPayments = 0.0;
if (tableExists($pdo, 'batch_payments')) {
    $sqlSub = "SELECT COALESCE(SUM(amount), 0) FROM batch_payments WHERE payment_date >= ? AND payment_date <= ?";
    $subcontractorPayments = (float)fetchVal($pdo, $sqlSub, [$startStr, $endStr]);
    if ($subcontractorPayments > 0) {
        $expenseDetails['subcontractor_payments'] = $subcontractorPayments;
    }
}

// ==============================================
// TOTAL EXPENSES: Production + Other Expenses + Salary + Accessories + Subcontractor Payments
// ==============================================
$boxExpense = $prodCost + $totalAmountColumns + $monthlySalaryPayments + $accessoryCost + $subcontractorPayments;

echo "<!-- ============================================== -->\n";
echo "<!-- FINAL EXPENSE CALCULATION -->\n";
echo "<!-- ============================================== -->\n";
echo "<!-- Production Costs: $" . number_format($prodCost, 2) . " -->\n";
echo "<!-- Other Expenses: $" . number_format($totalAmountColumns, 2) . " -->\n";
echo "<!-- Salary Payments: $" . number_format($monthlySalaryPayments, 2) . " (from " . $salaryCount . " rows) -->\n";
echo "<!-- Accessory Purchases: $" . number_format($accessoryCost, 2) . " -->\n";
echo "<!-- Subcontractor Payments: $" . number_format($subcontractorPayments, 2) . " -->\n";
echo "<!-- Total Expenses: $" . number_format($boxExpense, 2) . " -->\n";

// 4. Net Profit
$boxNetProfit = $boxPaid - $boxExpense;
echo "<!-- Net Profit: $" . number_format($boxNetProfit, 2) . " (Revenue: $" . number_format($boxPaid, 2) . " - Expenses: $" . number_format($boxExpense, 2) . ") -->\n";

// --- 6. CHART DATA GENERATION ---

// 6.1 Revenue vs Expenses Line Chart
$chartLabels = [];
$incomeData = [];
$expenseData = [];

// Determine Date Interval based on date range
$dateDiff = $startDate->diff($endDate);
$daysDiff = $dateDiff->days;

if ($daysDiff <= 31) {
    $chartGroupBy = 'day';
    $intervalSpec = 'P1D';
} elseif ($daysDiff <= 365) {
    $chartGroupBy = 'month';
    $intervalSpec = 'P1M';
} else {
    $chartGroupBy = 'month';
    $intervalSpec = 'P1M';
}

// Create period range for chart
$periodRange = new DatePeriod(
    $startDate, 
    new DateInterval($intervalSpec), 
    $endDate->modify('+1 day')
);

// Prepare Chart Queries - For salary, we need to get ALL rows and sum manually
if ($chartGroupBy === 'month') {
    $sqlChartInc = "SELECT SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE paid_amount END) FROM purchase_orders WHERE DATE_FORMAT(order_date, '%Y-%m') = ?";
    $sqlChartProd = "SELECT SUM(total_cost) FROM production_costs WHERE DATE_FORMAT(production_date, '%Y-%m') = ?";
    
    $chartAmountQueries = [];
    foreach ($amountTables as $table => $config) {
        if (tableExists($pdo, $table)) {
            $chartAmountQueries[$table] = "SELECT SUM({$config['column']}) FROM $table WHERE DATE_FORMAT({$config['date_column']}, '%Y-%m') = ?";
        }
    }
    
    // Get ALL salary rows for the period to count each row value
    $sqlChartSalaryRows = $salaryTableName ? 
        "SELECT * FROM $salaryTableName WHERE DATE_FORMAT($dateColumn, '%Y-%m') = ?" : 
        "SELECT 1 WHERE 1=0"; // Empty query if no salary table
    
    // Accessory purchases
    $sqlChartAccessory = "SELECT SUM(total_cost) FROM accessory_purchases WHERE DATE_FORMAT(purchase_date, '%Y-%m') = ?";
    
    // Subcontractor payments
    $sqlChartSub = "SELECT SUM(amount) FROM batch_payments WHERE DATE_FORMAT(payment_date, '%Y-%m') = ?";
    
    $dateFormatPHP = 'M Y';
    $dateFormatSQL = 'Y-m';
} else {
    $sqlChartInc = "SELECT SUM(CASE WHEN payment_status = 'Paid' THEN total_amount ELSE paid_amount END) FROM purchase_orders WHERE DATE(order_date) = ?";
    $sqlChartProd = "SELECT SUM(total_cost) FROM production_costs WHERE DATE(production_date) = ?";
    
    $chartAmountQueries = [];
    foreach ($amountTables as $table => $config) {
        if (tableExists($pdo, $table)) {
            $chartAmountQueries[$table] = "SELECT SUM({$config['column']}) FROM $table WHERE DATE({$config['date_column']}) = ?";
        }
    }
    
    // Get ALL salary rows for the period to count each row value
    $sqlChartSalaryRows = $salaryTableName ? 
        "SELECT * FROM $salaryTableName WHERE DATE($dateColumn) = ?" : 
        "SELECT 1 WHERE 1=0"; // Empty query if no salary table
    
    // Accessory purchases
    $sqlChartAccessory = "SELECT SUM(total_cost) FROM accessory_purchases WHERE DATE(purchase_date) = ?";
    
    // Subcontractor payments
    $sqlChartSub = "SELECT SUM(amount) FROM batch_payments WHERE DATE(payment_date) = ?";
    
    $dateFormatPHP = 'M d';
    $dateFormatSQL = 'Y-m-d';
}

// Execute Loop for Line Chart - COUNT EVERY SALARY ROW VALUE
foreach ($periodRange as $dt) {
    $label = $dt->format($dateFormatPHP);
    $sqlParam = $dt->format($dateFormatSQL);
    
    $chartLabels[] = $label;

    $inc = 0;
    if (tableExists($pdo, 'purchase_orders')) {
        $inc = (float)fetchVal($pdo, $sqlChartInc, [$sqlParam]);
    }
    $incomeData[] = $inc;

    $exp = 0;
    
    // 1. Production costs
    if (tableExists($pdo, 'production_costs')) {
        $exp += (float)fetchVal($pdo, $sqlChartProd, [$sqlParam]);
    }
    
    // 2. Other expenses
    foreach ($chartAmountQueries as $table => $query) {
        $exp += (float)fetchVal($pdo, $query, [$sqlParam]);
    }
    
    // 3. SALARY PAYMENTS - GET ALL ROWS AND SUM EACH ROW VALUE
    if ($salaryTableName) {
        try {
            $stmt = $pdo->prepare($sqlChartSalaryRows);
            $stmt->execute([$sqlParam]);
            $periodSalaryRows = $stmt->fetchAll();
            
            $periodSalary = 0.0;
            $rowCount = 0;
            
            // SUM EACH ROW'S AMOUNT VALUE
            foreach ($periodSalaryRows as $salaryRow) {
                $rowAmount = 0.0;
                if (isset($salaryRow[$amountColumn])) {
                    $rowAmount = (float)$salaryRow[$amountColumn];
                } elseif (isset($salaryRow['amount'])) {
                    $rowAmount = (float)$salaryRow['amount'];
                }
                $periodSalary += $rowAmount;
                $rowCount++;
            }
            
            $exp += $periodSalary;
            
            // Debug output for chart data
            if ($periodSalary > 0) {
                echo "<!-- CHART: Period $sqlParam - Counted $rowCount salary rows, Total: $" . number_format($periodSalary, 2) . " -->\n";
            }
        } catch (Exception $e) {
            // Fallback to SQL sum if manual method fails
            $sqlChartSalary = ($chartGroupBy === 'month') 
                ? "SELECT SUM($amountColumn) FROM $salaryTableName WHERE DATE_FORMAT($dateColumn, '%Y-%m') = ?"
                : "SELECT SUM($amountColumn) FROM $salaryTableName WHERE DATE($dateColumn) = ?";
            $salaryAmount = (float)fetchVal($pdo, $sqlChartSalary, [$sqlParam]);
            $exp += $salaryAmount;
        }
    }
    
    // 4. Accessory purchases
    if (tableExists($pdo, 'accessory_purchases')) {
        $exp += (float)fetchVal($pdo, $sqlChartAccessory, [$sqlParam]);
    }
    
    // 5. Subcontractor payments
    if (tableExists($pdo, 'batch_payments')) {
        $exp += (float)fetchVal($pdo, $sqlChartSub, [$sqlParam]);
    }
    
    $expenseData[] = $exp;
}

// Convert to JSON for JS
$jsLabels = json_encode($chartLabels);
$jsIncome = json_encode($incomeData);
$jsExpense = json_encode($expenseData);

// 6.2 Top Selling Products Pie Chart
$topProducts = [];
if (tableExists($pdo, 'purchase_order_items')) {
    try {
        // First, let's check what columns exist in the products table
        $productColumns = [];
        if (tableExists($pdo, 'products')) {
            $checkColumns = $pdo->query("SHOW COLUMNS FROM products");
            $productColumns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
        }
        
        // Determine product name column
        $productNameColumn = 'product_name';
        if (in_array('name', $productColumns)) {
            $productNameColumn = 'name';
        } elseif (in_array('product_name', $productColumns)) {
            $productNameColumn = 'product_name';
        } elseif (in_array('title', $productColumns)) {
            $productNameColumn = 'title';
        }
        
        // Try to get top selling products
        $sqlTopProducts = "SELECT 
                            COALESCE(p.{$productNameColumn}, 'Unknown Product') as product_name,
                            SUM(poi.quantity) as total_quantity,
                            SUM(poi.quantity * poi.unit_price) as total_revenue
                        FROM purchase_order_items poi
                        LEFT JOIN products p ON poi.product_id = p.id
                        JOIN purchase_orders po ON poi.purchase_order_id = po.id
                        WHERE po.order_date >= ? AND po.order_date <= ?
                        GROUP BY poi.product_id, COALESCE(p.{$productNameColumn}, 'Unknown Product')
                        ORDER BY total_quantity DESC
                        LIMIT 8";
        
        $stmt = $pdo->prepare($sqlTopProducts);
        $stmt->execute([$startStr, $endStr]);
        $topProducts = $stmt->fetchAll();
        
    } catch (Exception $e) {
        // Fallback query
        $sqlTopProducts = "SELECT 
                            'Product ' || poi.product_id as product_name,
                            SUM(poi.quantity) as total_quantity,
                            SUM(poi.quantity * poi.unit_cost) as total_revenue
                        FROM purchase_order_items poi
                        JOIN purchase_orders po ON poi.purchase_id = po.id
                        WHERE po.order_date >= ? AND po.order_date <= ?
                        GROUP BY poi.product_id
                        ORDER BY total_quantity DESC
                        LIMIT 8";
        
        $stmt = $pdo->prepare($sqlTopProducts);
        $stmt->execute([$startStr, $endStr]);
        $topProducts = $stmt->fetchAll();
    }
}

$productLabels = [];
$productQuantities = [];
$productRevenues = [];
$productColors = [];

$colorPalette = [
    '#4F46E5', '#10B981', '#F59E0B', '#EF4444', 
    '#8B5CF6', '#3B82F6', '#EC4899', '#06B6D4'
];

foreach ($topProducts as $index => $product) {
    $productLabels[] = e($product['product_name']);
    $productQuantities[] = (int)$product['total_quantity'];
    $productRevenues[] = (float)$product['total_revenue'];
    $productColors[] = $colorPalette[$index % count($colorPalette)];
}

// If no products found, show placeholder
if (empty($topProducts)) {
    $productLabels = ['No Sales Data'];
    $productQuantities = [100];
    $productRevenues = [0];
    $productColors = ['#E5E7EB'];
}

$jsProductLabels = json_encode($productLabels);
$jsProductQuantities = json_encode($productQuantities);
$jsProductRevenues = json_encode($productRevenues);
$jsProductColors = json_encode($productColors);

// 6.3 Expense Distribution Pie Chart - MUST show salaries with EVERY ROW COUNTED and subcontractor payments
$expenseDistributionLabels = [];
$expenseDistributionData = [];
$expenseDistributionColors = [];

// Production Costs
if ($prodCost > 0) {
    $expenseDistributionLabels[] = 'Production';
    $expenseDistributionData[] = $prodCost;
    $expenseDistributionColors[] = '#EF4444';
}

// SALARY PAYMENTS - EVERY ROW VALUE COUNTED
if ($monthlySalaryPayments > 0) {
    $expenseDistributionLabels[] = 'Salaries';
    $expenseDistributionData[] = $monthlySalaryPayments;
    $expenseDistributionColors[] = '#8B5CF6';
    echo "<!-- PIE CHART: Added Salaries: $" . number_format($monthlySalaryPayments, 2) . " (from " . $salaryCount . " rows) -->\n";
}

// Other Expenses
if ($totalAmountColumns > 0) {
    $expenseDistributionLabels[] = 'Other Expenses';
    $expenseDistributionData[] = $totalAmountColumns;
    $expenseDistributionColors[] = '#F59E0B';
}

// Accessory Purchases
if ($accessoryCost > 0) {
    $expenseDistributionLabels[] = 'Accessories';
    $expenseDistributionData[] = $accessoryCost;
    $expenseDistributionColors[] = '#06B6D4';
}

// Subcontractor Payments (NEW)
if ($subcontractorPayments > 0) {
    $expenseDistributionLabels[] = 'Subcontractor';
    $expenseDistributionData[] = $subcontractorPayments;
    $expenseDistributionColors[] = '#EC4899'; // Pink
}

// If no expenses at all, show placeholder
if (empty($expenseDistributionData)) {
    $expenseDistributionLabels[] = 'No Expenses';
    $expenseDistributionData[] = 1;
    $expenseDistributionColors[] = '#E5E7EB';
}

$jsExpenseLabels = json_encode($expenseDistributionLabels);
$jsExpenseData = json_encode($expenseDistributionData);
$jsExpenseColors = json_encode($expenseDistributionColors);

// 6.4 Customer Acquisition Bar Chart
$customerAcquisition = [];
if (tableExists($pdo, 'customers')) {
    if ($daysDiff <= 31) {
        // Daily view for short ranges
        $sqlCustomers = "SELECT 
                            DATE_FORMAT(created_at, '%W') as day_name,
                            COUNT(*) as count
                        FROM customers 
                        WHERE created_at >= ? AND created_at <= ?
                        GROUP BY DAYOFWEEK(created_at), DATE_FORMAT(created_at, '%W')
                        ORDER BY DAYOFWEEK(created_at)";
    } else {
        // Monthly view for longer ranges
        $sqlCustomers = "SELECT 
                            DATE_FORMAT(created_at, '%M') as month_name,
                            COUNT(*) as count
                        FROM customers 
                        WHERE created_at >= ? AND created_at <= ?
                        GROUP BY MONTH(created_at), DATE_FORMAT(created_at, '%M')
                        ORDER BY MONTH(created_at)";
    }
    
    try {
        $stmt = $pdo->prepare($sqlCustomers);
        $stmt->execute([$startStr, $endStr]);
        $customerAcquisition = $stmt->fetchAll();
    } catch (Exception $e) {
        $customerAcquisition = [];
    }
}

$customerLabels = [];
$customerData = [];

foreach ($customerAcquisition as $item) {
    $customerLabels[] = e($item[array_keys($item)[0]]);
    $customerData[] = (int)$item['count'];
}

// If no customer data, create sample data for chart
if (empty($customerLabels)) {
    if ($daysDiff <= 31) {
        $customerLabels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    } else {
        $customerLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    }
    $customerData = array_fill(0, count($customerLabels), 0);
}

$jsCustomerLabels = json_encode($customerLabels);
$jsCustomerData = json_encode($customerData);

// 6.5 Payment Status Donut Chart
$paymentStatusData = [];
if (tableExists($pdo, 'purchase_orders')) {
    $sqlPaymentStatus = "SELECT 
                            payment_status,
                            COUNT(*) as count,
                            SUM(total_amount) as total_amount
                        FROM purchase_orders 
                        WHERE order_date >= ? AND order_date <= ?
                        GROUP BY payment_status";
    
    try {
        $stmt = $pdo->prepare($sqlPaymentStatus);
        $stmt->execute([$startStr, $endStr]);
        $paymentStatusData = $stmt->fetchAll();
    } catch (Exception $e) {
        $paymentStatusData = [];
    }
}

$paymentLabels = [];
$paymentCounts = [];
$paymentAmounts = [];
$paymentStatusColors = [
    'Paid' => '#10B981',
    'Pending' => '#F59E0B',
    'Partial' => '#3B82F6',
    'Overdue' => '#EF4444',
    'Cancelled' => '#6B7280',
    'Unpaid' => '#8B5CF6'
];

foreach ($paymentStatusData as $status) {
    $paymentLabels[] = e($status['payment_status']);
    $paymentCounts[] = (int)$status['count'];
    $paymentAmounts[] = (float)$status['total_amount'];
}

// If no payment data, show placeholder
if (empty($paymentLabels)) {
    $paymentLabels = ['No Payment Data'];
    $paymentCounts = [1];
    $paymentAmounts = [0];
}

$jsPaymentLabels = json_encode($paymentLabels);
$jsPaymentCounts = json_encode($paymentCounts);
$jsPaymentAmounts = json_encode($paymentAmounts);
$jsPaymentColors = json_encode(array_values($paymentStatusColors));

// Function to format table names for display
function formatTableName($tableName) {
    $formatted = str_replace('_', ' ', $tableName);
    $formatted = ucwords($formatted);
    return $formatted;
}

// Function to get employee name by ID
function getEmployeeName($pdo, $employeeId) {
    if (!tableExists($pdo, 'employees') || !$employeeId) {
        return 'Unknown Employee';
    }
    
    $sql = "SELECT name FROM employees WHERE id = ?";
    $name = fetchVal($pdo, $sql, [$employeeId]);
    return $name ?: 'Employee #' . $employeeId;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Nexus V2</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <style>
        /* --- CSS Variables & Reset --- */
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338ca;
            --bg-body: #F3F4F6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --sidebar-width: 280px;
            --sidebar-bg: #111827;
            --sidebar-text: #E5E7EB;
            --header-height: 64px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --radius: 12px;
            
            /* Financial Colors */
            --color-revenue: #10B981;
            --color-hold: #F59E0B;
            --color-expense: #EF4444;
            --color-profit: #3B82F6;
            --color-salary: #8B5CF6;
            --color-subcontractor: #EC4899;
        }

        [data-theme="dark"] {
            --bg-body: #0f172a;
            --bg-card: #1e293b;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border: #334155;
            --sidebar-bg: #020617;
            --primary: #6366f1;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; 
        }
        
        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            height: 100vh; 
            display: flex; 
            overflow: hidden; 
        }
        
        a { text-decoration: none; color: inherit; }
        ul { list-style: none; }
        button { font-family: inherit; cursor: pointer; }

        /* --- Sidebar & Layout Logic --- */
        body.sidebar-collapsed { --sidebar-width: 80px; }

        .sidebar {
            width: var(--sidebar-width);
            background-color: var(--sidebar-bg);
            color: var(--sidebar-text);
            display: flex;
            flex-direction: column;
            transition: width var(--transition), transform var(--transition);
            z-index: 50;
            flex-shrink: 0;
            white-space: nowrap;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .sidebar-header {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 24px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            gap: 12px;
            overflow: hidden;
        }

        body.sidebar-collapsed .logo-text,
        body.sidebar-collapsed .link-text,
        body.sidebar-collapsed .arrow-icon { display: none; opacity: 0; }
        
        body.sidebar-collapsed .sidebar-header { justify-content: center; padding: 0; }
        body.sidebar-collapsed .menu-link { justify-content: center; padding: 12px 0; }
        body.sidebar-collapsed .link-content { gap: 0; }
        body.sidebar-collapsed .menu-icon { font-size: 1.5rem; margin: 0; }
        body.sidebar-collapsed .submenu { display: none !important; }

        .sidebar-menu { 
            padding: 20px 12px; 
            overflow-y: auto; 
            overflow-x: hidden; 
            flex: 1; 
        }
        
        .menu-item { 
            margin-bottom: 4px; 
        }
        
        .menu-link {
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            padding: 12px 16px; 
            border-radius: 8px;
            color: rgba(255,255,255,0.7); 
            cursor: pointer; 
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        
        .menu-link:hover, .menu-link.active { 
            background-color: rgba(255,255,255,0.1); 
            color: #fff; 
            transform: translateX(2px);
        }
        
        .link-content { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
        }
        
        .menu-icon { 
            font-size: 1.2rem; 
            min-width: 24px; 
            text-align: center; 
            transition: transform 0.2s ease;
        }
        
        .arrow-icon { 
            transition: transform 0.3s ease; 
            font-size: 0.8rem; 
            opacity: 0.7; 
        }

        .submenu {
            max-height: 0; 
            overflow: hidden; 
            transition: max-height 0.3s ease-in-out; 
            padding-left: 12px;
        }
        
        .menu-item.open > .submenu { 
            max-height: 500px; 
        }
        
        .menu-item.open > .menu-link .arrow-icon { 
            transform: rotate(180deg); 
        }
        
        .menu-item.open > .menu-link { 
            color: #fff; 
        }
        
        .submenu-link {
            display: block; 
            padding: 10px 16px 10px 42px;
            color: rgba(255,255,255,0.5); 
            font-size: 0.9rem; 
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .submenu-link:hover { 
            color: #fff; 
            background: rgba(255,255,255,0.05); 
            transform: translateX(2px);
        }

        /* --- Main Content --- */
        .main-content { 
            flex: 1; 
            display: flex; 
            flex-direction: column; 
            overflow: hidden; 
            position: relative; 
        }

        .top-header {
            height: var(--header-height);
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            display: flex; 
            align-items: center; 
            justify-content: space-between;
            padding: 0 32px; 
            flex-shrink: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .toggle-btn { 
            background: none; 
            border: none; 
            font-size: 1.5rem; 
            cursor: pointer; 
            color: var(--text-muted); 
            display: flex; 
            align-items: center;
            transition: all 0.2s ease;
            padding: 8px;
            border-radius: 8px;
        }
        
        .toggle-btn:hover { 
            color: var(--primary); 
            background: var(--bg-body);
            transform: translateY(-1px);
        }

        /* --- Profile Dropdown --- */
        .header-right { 
            display: flex; 
            align-items: center; 
            gap: 24px; 
        }
        
        .profile-container { 
            position: relative; 
        }
        
        .profile-menu { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            cursor: pointer; 
            padding: 8px 12px; 
            border-radius: 12px; 
            transition: all 0.2s ease;
        }
        
        .profile-menu:hover { 
            background-color: var(--bg-body); 
        }
        
        .profile-info { 
            text-align: right; 
            line-height: 1.2; 
        }
        
        .profile-name { 
            font-size: 0.9rem; 
            font-weight: 600; 
            display: block; 
        }
        
        .profile-role { 
            font-size: 0.75rem; 
            color: var(--text-muted); 
        }
        
        .profile-img { 
            width: 42px; 
            height: 42px; 
            border-radius: 12px; 
            object-fit: cover; 
            border: 2px solid var(--border); 
            transition: all 0.2s ease;
        }
        
        .profile-placeholder { 
            width: 42px; 
            height: 42px; 
            border-radius: 12px; 
            background: var(--primary); 
            color: white; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 600; 
            font-size: 1.1rem;
        }

        .dropdown-menu {
            position: absolute; 
            top: calc(100% + 8px); 
            right: 0; 
            width: 220px;
            background: var(--bg-card); 
            border: 1px solid var(--border);
            border-radius: 12px; 
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
            padding: 8px; 
            z-index: 1000; 
            display: none; 
            flex-direction: column; 
            gap: 4px;
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .dropdown-menu.show { 
            display: flex; 
        }
        
        .dropdown-item {
            display: flex; 
            align-items: center; 
            gap: 10px; 
            padding: 12px 16px;
            font-size: 0.9rem; 
            color: var(--text-main); 
            border-radius: 8px; 
            transition: all 0.2s ease;
        }
        
        .dropdown-item:hover { 
            background-color: var(--bg-body); 
            color: var(--primary); 
            transform: translateX(2px);
        }
        
        .dropdown-item.danger:hover { 
            background-color: rgba(239, 68, 68, 0.1); 
            color: #ef4444; 
        }

        /* --- Dashboard Body --- */
        .scrollable { 
            flex: 1; 
            overflow-y: auto; 
            padding: 32px; 
            scroll-behavior: smooth;
        }
        
        .page-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 32px; 
            flex-wrap: wrap; 
            gap: 16px; 
        }
        
        /* Enhanced Filter Form */
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
        
        .filter-form:hover {
            border-color: var(--primary);
        }
        
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

        /* Grid & Cards - Enhanced for Charts */
        .grid { 
            display: grid; 
            grid-template-columns: repeat(12, 1fr); 
            gap: 24px; 
        }
        
        .col-3 { 
            grid-column: span 3;
            min-height: 168px;
            height: 100%;
        }
        
        .col-4 { 
            grid-column: span 4;
            min-height: 380px;
        }
        
        .col-6 { 
            grid-column: span 6;
            min-height: 380px;
        }
        
        .col-12 { 
            grid-column: span 12;
            min-height: 420px;
        }
        
        .card { 
            background: var(--bg-card); 
            border-radius: var(--radius); 
            padding: 28px; 
            border: 1px solid var(--border); 
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
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
        
        /* Icon Container - Perfect Alignment */
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
        
        /* Card-specific icon colors */
        .customers .stat-icon-container { background: rgba(79, 70, 229, 0.12); }
        .customers .stat-icon { color: var(--primary); }
        
        .vendors .stat-icon-container { background: rgba(99, 102, 241, 0.12); }
        .vendors .stat-icon { color: #6366f1; }
        
        .products .stat-icon-container { background: rgba(139, 92, 246, 0.12); }
        .products .stat-icon { color: #8b5cf6; }
        
        .employees .stat-icon-container { background: rgba(168, 85, 247, 0.12); }
        .employees .stat-icon { color: #a855f7; }
        
        .revenue .stat-icon-container { background: rgba(16, 185, 129, 0.12); }
        .revenue .stat-icon { color: var(--color-revenue); }
        
        .hold .stat-icon-container { background: rgba(245, 158, 11, 0.12); }
        .hold .stat-icon { color: var(--color-hold); }
        
        .expenses .stat-icon-container { background: rgba(239, 68, 68, 0.12); }
        .expenses .stat-icon { color: var(--color-expense); }
        
        .profit .stat-icon-container { background: rgba(59, 130, 246, 0.12); }
        .profit .stat-icon { color: var(--color-profit); }
        
        /* Custom Fin Colors */
        .text-green { color: var(--color-revenue); } 
        .text-orange { color: var(--color-hold); }
        .text-red { color: var(--color-expense); } 
        .text-blue { color: var(--color-profit); }
        .text-primary { color: var(--primary); }
        .text-purple { color: var(--color-salary); }
        .text-pink { color: var(--color-subcontractor); }

        /* Chart Container */
        .chart-container {
            height: 300px;
            width: 100%;
            position: relative;
            margin-top: 16px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chart-actions {
            display: flex;
            gap: 8px;
        }
        
        .chart-action-btn {
            background: var(--bg-body);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 10px;
            font-size: 0.8rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .chart-action-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .chart-action-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        /* Chart Legend */
        .chart-legend {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
            font-size: 0.8rem;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 4px;
        }

        /* Expense Breakdown */
        .expense-breakdown {
            margin-top: 14px;
            font-size: 0.75rem;
        }
        
        .expense-summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
            padding-bottom: 6px;
            border-bottom: 1px solid rgba(0,0,0,0.04);
        }
        
        .expense-summary:last-child {
            border-bottom: none;
        }
        
        .expense-category {
            color: var(--text-muted);
            font-size: 0.72rem;
            font-weight: 500;
        }
        
        .expense-value {
            font-weight: 600;
            font-size: 0.72rem;
        }
        
        .view-details {
            margin-top: 10px;
            font-size: 0.72rem;
            color: var(--primary);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 4px 8px;
            border-radius: 6px;
        }
        
        .view-details:hover {
            background: rgba(79, 70, 229, 0.08);
            transform: translateX(2px);
        }

        /* Monthly Summary Badge */
        .monthly-summary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(16, 185, 129, 0.12);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.72rem;
            margin-top: 6px;
            color: var(--color-revenue);
            font-weight: 500;
            width: fit-content;
        }

        /* Salary Debug Badge */
        .salary-debug {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(139, 92, 246, 0.12);
            padding: 5px 10px;
            border-radius: 8px;
            font-size: 0.72rem;
            margin-top: 6px;
            color: var(--color-salary);
            font-weight: 500;
            width: fit-content;
            border: 1px dashed rgba(139, 92, 246, 0.3);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 28px;
            width: 100%;
            max-width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
            border: 1px solid var(--border);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        
        .modal-close {
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 1.3rem;
            padding: 6px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: var(--bg-body);
            color: var(--text-main);
            transform: rotate(90deg);
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
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
        
        .date-range-display i {
            font-size: 1rem;
        }

        /* Salary Details Table */
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        
        .salary-table th {
            background: var(--bg-body);
            padding: 12px 16px;
            text-align: left;
            font-size: 0.8rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 2px solid var(--border);
        }
        
        .salary-table td {
            padding: 12px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 0.85rem;
        }
        
        .salary-table tr:hover {
            background: var(--bg-body);
        }
        
        .salary-amount {
            font-weight: 600;
            color: var(--color-salary);
        }
        
        .salary-date {
            color: var(--text-muted);
            font-size: 0.8rem;
        }

        /* Salary Row Counter */
        .salary-row-counter {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(139, 92, 246, 0.08);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
            color: var(--color-salary);
            font-weight: 500;
            margin-left: 6px;
        }

        /* Row Verification Badge */
        .row-verified {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(16, 185, 129, 0.1);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.65rem;
            color: var(--color-revenue);
            font-weight: 500;
            margin-left: 6px;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        /* --- Responsive Design --- */
        @media (max-width: 1200px) {
            .col-3 { 
                grid-column: span 6;
                min-height: 160px;
            }
            
            .col-4, .col-6 { 
                grid-column: span 12;
                min-height: 350px;
            }
            
            .filter-form {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .date-range-container {
                margin-left: 0;
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .col-3 { 
                grid-column: span 12;
                min-height: 152px;
            }
            
            .col-4, .col-6 { 
                grid-column: span 12;
                min-height: 320px;
            }
            
            body.sidebar-collapsed { --sidebar-width: 280px; } 
            .sidebar { 
                position: fixed; 
                left: -280px; 
                height: 100%; 
                top: 0;
            }
            
            body.mobile-open .sidebar { 
                transform: translateX(280px); 
                box-shadow: 0 0 40px rgba(0, 0, 0, 0.3);
            }
            
            body.mobile-open .overlay { 
                display: block; 
            }
            
            .logo-text, .link-text, .arrow-icon { 
                display: inline !important; 
                opacity: 1 !important; 
            }
            
            .sidebar-header { 
                justify-content: flex-start !important; 
                padding: 0 24px !important; 
            }
            
            .top-header { 
                padding: 0 20px; 
            }
            
            .scrollable { 
                padding: 24px 20px; 
            }
            
            .profile-info { 
                display: none; 
            }
            
            .stat-val {
                font-size: 1.7rem;
            }
            
            .stat-icon-container {
                width: 46px;
                height: 46px;
                right: 20px;
                top: 20px;
            }
            
            .stat-icon {
                font-size: 1.4rem;
            }
            
            .card {
                padding: 24px;
            }
            
            .filter-form {
                width: 100%;
            }
            
            .date-input {
                width: 100%;
            }
            
            .modal-content {
                max-width: 95%;
                padding: 20px;
            }
            
            .salary-table {
                display: block;
                overflow-x: auto;
            }
        }
        
        @media (max-width: 480px) {
            .stat-val {
                font-size: 1.5rem;
            }
            
            .stat-head {
                font-size: 0.8rem;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-form {
                width: 100%;
            }
            
            .date-range-container {
                flex-direction: column;
                width: 100%;
            }
            
            .date-input {
                width: 100%;
            }
            
            .chart-actions {
                flex-wrap: wrap;
            }
        }

        /* Overlay */
        .overlay { 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100%; 
            height: 100%; 
            background: rgba(0,0,0,0.5); 
            backdrop-filter: blur(2px);
            z-index: 45; 
            display: none; 
            animation: fadeIn 0.3s ease;
        }
        
        /* Theme Toggle */
        #themeToggle {
            background: var(--bg-card);
            border: 1px solid var(--border);
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        #themeToggle:hover {
            transform: rotate(15deg);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        /* Scrollbar Styling */
        .scrollable::-webkit-scrollbar {
            width: 8px;
        }
        
        .scrollable::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .scrollable::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 4px;
        }
        
        .scrollable::-webkit-scrollbar-thumb:hover {
            background: var(--text-muted);
        }
        
        /* Loading Animation */
        .loading {
            opacity: 0.7;
            pointer-events: none;
        }
        
        /* Smooth Transitions */
        * {
            transition: background-color 0.3s ease, border-color 0.3s ease;
        }
        
        /* No Data Message */
        .no-data {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-muted);
            text-align: center;
            padding: 20px;
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .no-data p {
            font-size: 0.9rem;
            margin-top: 8px;
        }
        
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

<?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        
      <!-- The header section is now updated to match profile_settings.php -->
<header class="top-header">
    <div style="display: flex; align-items: center; gap: 16px;">
        <button class="toggle-btn" id="sidebarToggle">
            <i class="ph ph-list"></i>
        </button>
        <div style="display: flex; align-items: center; gap: 8px;">
            <div style="width: 8px; height: 8px; background: var(--primary); border-radius: 50%;"></div>
            <span style="font-size: 0.9rem; color: var(--text-muted);">Live Dashboard</span>
        </div>
    </div>

    <div class="header-right">
        <button id="themeToggle" title="Toggle Theme">
            <i class="ph ph-moon" id="themeIcon"></i>
        </button>

        <div class="profile-container" id="profileContainer">
            <div class="profile-menu" onclick="toggleProfileMenu()">
                <div class="profile-info">
                    <span class="profile-name"><?php echo e($_SESSION['username']); ?></span>
                    <span class="profile-role"><?php echo ucfirst(e($_SESSION['role'])); ?></span>
                </div>
                <?php 
                // Get user photo from database
                $userPhoto = '';
                if (isset($_SESSION['user_id'])) {
                    try {
                        $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $userData = $stmt->fetch();
                        $userPhoto = $userData['avatar'] ?? '';
                    } catch (Exception $e) {
                        $userPhoto = '';
                    }
                }
                
                if (!empty($userPhoto)): ?>
                    <img src="<?php echo e($userPhoto); ?>" alt="Profile" class="profile-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="profile-placeholder" style="display: none;">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                <?php else: ?>
                    <div class="profile-placeholder">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </div>
                <?php endif; ?>                          
            </div>

            <div class="dropdown-menu" id="profileDropdown">
              
                <a href="profile_settings.php" class="dropdown-item">
                    <i class="ph ph-user-gear" style="font-size: 1.1rem;"></i> 
                    <span>Profile Settings</span>
                </a>
                <div style="border-top: 1px solid var(--border); margin: 4px 0;"></div>
                <a href="logout.php" class="dropdown-item danger">
                    <i class="ph ph-sign-out" style="font-size: 1.1rem;"></i> 
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</header>

        <div class="scrollable">
            <div class="page-header">
                <div>
                    <h1 style="font-size: 1.6rem; font-weight: 700; margin-bottom: 4px;">Dashboard</h1>
                    <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.4;">
                        System overview & financial performance • 
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

            <div class="grid">
                <!-- Row 1: Operational Stats -->
                <div class="card stat-card col-3 customers">
                    <div class="stat-head">
                        <i class="ph ph-users-three" style="font-size: 0.9rem;"></i>
                        <span>Customers</span>
                    </div>
                    <div class="stat-val"><?php echo number_format($totalCustomers); ?></div>
                    <div class="stat-sub">
                        <i class="ph ph-trend-up" style="font-size: 0.8rem;"></i>
                        <span>Active Accounts</span>
                    </div>
                    <div class="stat-icon-container">
                        <i class="ph ph-users-three stat-icon"></i>
                    </div>
                </div>

                <div class="card stat-card col-3 vendors">
                    <div class="stat-head">
                        <i class="ph ph-storefront" style="font-size: 0.9rem;"></i>
                        <span>Vendors</span>
                    </div>
                    <div class="stat-val"><?php echo number_format($totalVendors); ?></div>
                    <div class="stat-sub">
                        <i class="ph ph-handshake" style="font-size: 0.8rem;"></i>
                        <span>Business Partners</span>
                    </div>
                    <div class="stat-icon-container">
                        <i class="ph ph-storefront stat-icon"></i>
                    </div>
                </div>

                <div class="card stat-card col-3 products">
                    <div class="stat-head">
                        <i class="ph ph-package" style="font-size: 0.9rem;"></i>
                        <span>Products</span>
                    </div>
                    <div class="stat-val"><?php echo number_format($totalProducts); ?></div>
                    <div class="stat-sub">
                        <i class="ph ph-warehouse" style="font-size: 0.8rem;"></i>
                        <span>In Stock</span>
                    </div>
                    <div class="stat-icon-container">
                        <i class="ph ph-package stat-icon"></i>
                    </div>
                </div>

                <div class="card stat-card col-3 employees">
                    <div class="stat-head">
                        <i class="ph ph-identification-badge" style="font-size: 0.9rem;"></i>
                        <span>Employees</span>
                    </div>
                    <div class="stat-val"><?php echo number_format($totalEmployees); ?></div>
                    <div class="stat-sub">
                        <i class="ph ph-users" style="font-size: 0.8rem;"></i>
                        <span>Staff Members</span>
                    </div>
                    <div class="stat-icon-container">
                        <i class="ph ph-identification-badge stat-icon"></i>
                    </div>
                </div>

                <!-- Row 2: Financial Stats -->
                <div class="card stat-card col-3 revenue">
                    <div class="stat-head">
                        <i class="ph ph-currency-dollar" style="font-size: 0.9rem;"></i>
                        <span>Paid Revenue</span>
                    </div>
                    <div class="stat-val text-green">$<?php echo number_format($boxPaid, 2); ?></div>
                    <div class="stat-sub">
                        <i class="ph ph-arrow-down-right" style="font-size: 0.8rem;"></i>
                        <span>Received Amount</span>
                    </div>
                    <div class="stat-icon-container">
                        <i class="ph ph-currency-dollar stat-icon"></i>
                    </div>
                </div>

                <div class="card stat-card col-3 hold">
                    <div class="stat-head">
                        <i class="ph ph-clock" style="font-size: 0.9rem;"></i>
                        <span>Hold Payments</span>
                    </div>
                    <div class="stat-val text-orange">$<?php echo number_format($boxHold, 2); ?></div>
                    <div class="stat-sub">
                        <i class="ph ph-hourglass" style="font-size: 0.8rem;"></i>
                        <span>Pending Collection</span>
                    </div>
                    <div class="stat-icon-container">
                        <i class="ph ph-clock stat-icon"></i>
                    </div>
                </div>

                <div class="card stat-card col-3 expenses">
                    <div class="stat-head">
                        <i class="ph ph-trend-up" style="font-size: 0.9rem;"></i>
                        <span>Total Expenses</span>
                    </div>
                    <div class="stat-val text-red">$<?php echo number_format($boxExpense, 2); ?></div>
                    
                    <!-- Salary Debug Info - Shows EVERY ROW COUNTED -->
                    <div class="salary-debug">
                        <i class="ph ph-currency-dollar" style="font-size: 0.7rem;"></i>
                        <span>Salary: $<?php echo number_format($monthlySalaryPayments, 2); ?></span>
                        <span class="salary-row-counter">
                            <i class="ph ph-list-numbers" style="font-size: 0.6rem;"></i>
                            <?php echo $salaryCount; ?> rows counted
                        </span>
                    </div>
                    
                    <!-- Subcontractor Payments Info -->
                    <?php if ($subcontractorPayments > 0): ?>
                    <div class="salary-debug" style="background: rgba(236, 72, 153, 0.12); color: var(--color-subcontractor);">
                        <i class="ph ph-handshake" style="font-size: 0.7rem;"></i>
                        <span>Subcontractor: $<?php echo number_format($subcontractorPayments, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($filter === 'month'): ?>
                    <div class="monthly-summary">
                        <i class="ph ph-calendar-check"></i>
                        <span><?php echo date('M Y'); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="expense-breakdown">
                        <div class="expense-summary">
                            <span class="expense-category">Production</span>
                            <span class="expense-value">$<?php echo number_format($prodCost, 2); ?></span>
                        </div>
                        <?php if ($monthlySalaryPayments > 0): ?>
                        <div class="expense-summary">
                            <span class="expense-category">Salaries</span>
                            <span class="expense-value" style="color: var(--color-salary);">
                                $<?php echo number_format($monthlySalaryPayments, 2); ?>
                                <span class="salary-row-counter"><?php echo $salaryCount; ?> payments</span>
                            </span>
                        </div>
                        <?php endif; ?>
                        <?php if ($accessoryCost > 0): ?>
                        <div class="expense-summary">
                            <span class="expense-category">Accessories</span>
                            <span class="expense-value" style="color: #06B6D4;">$<?php echo number_format($accessoryCost, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($subcontractorPayments > 0): ?>
                        <div class="expense-summary">
                            <span class="expense-category">Subcontractor</span>
                            <span class="expense-value" style="color: var(--color-subcontractor);">$<?php echo number_format($subcontractorPayments, 2); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="expense-summary">
                            <span class="expense-category">Other</span>
                            <span class="expense-value">$<?php echo number_format($totalAmountColumns, 2); ?></span>
                        </div>
                        <div class="view-details" onclick="showExpenseDetails()">
                            <span>View All Salary Details</span>
                            <i class="ph ph-arrow-right" style="font-size: 0.7rem;"></i>
                        </div>
                    </div>
                    
                    <div class="stat-icon-container">
                        <i class="ph ph-trend-up stat-icon"></i>
                    </div>
                </div>

                <div class="card stat-card col-3 profit">
                    <div class="stat-head">
                        <i class="ph ph-chart-line-up" style="font-size: 0.9rem;"></i>
                        <span>Net Profit</span>
                    </div>
                    <div class="stat-val text-blue">$<?php echo number_format($boxNetProfit, 2); ?></div>
                    <div class="stat-sub">
                        <i class="ph ph-graph" style="font-size: 0.8rem;"></i>
                        <span>Revenue - Expenses</span>
                    </div>
                    <div class="stat-icon-container">
                        <i class="ph ph-chart-line-up stat-icon"></i>
                    </div>
                </div>

                <!-- Row 3: Charts Section -->
                <!-- Revenue vs Expenses Line Chart -->
                <div class="card col-6">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="ph ph-chart-line" style="color: var(--primary);"></i>
                            <span>Revenue vs Expenses</span>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" onclick="changeChartType('line')">Line</button>
                            <button class="chart-action-btn" onclick="changeChartType('bar')">Bar</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="financeChart"></canvas>
                    </div>
                    <div class="chart-legend" id="financeLegend"></div>
                </div>

                <!-- Top Selling Products Pie Chart -->
                <div class="card col-6">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="ph ph-trophy" style="color: #F59E0B;"></i>
                            <span>Top Selling Products</span>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" onclick="changeProductChart('quantity')">By Quantity</button>
                            <button class="chart-action-btn" onclick="changeProductChart('revenue')">By Revenue</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="productsChart"></canvas>
                    </div>
                    <div class="chart-legend" id="productsLegend"></div>
                </div>

                <!-- Expense Distribution Pie Chart -->
                <div class="card col-4">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="ph ph-pie-chart" style="color: #EF4444;"></i>
                            <span>Expense Distribution</span>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="expenseChart"></canvas>
                    </div>
                    <div class="chart-legend" id="expenseLegend"></div>
                </div>

                <!-- Customer Acquisition Bar Chart -->
                <div class="card col-4">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="ph ph-users" style="color: #4F46E5;"></i>
                            <span>Customer Acquisition</span>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" onclick="changeCustomerChart('bar')">Bar</button>
                            <button class="chart-action-btn" onclick="changeCustomerChart('line')">Line</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="customerChart"></canvas>
                    </div>
                </div>

                <!-- Payment Status Donut Chart -->
                <div class="card col-4">
                    <div class="chart-header">
                        <div class="chart-title">
                            <i class="ph ph-credit-card" style="color: #10B981;"></i>
                            <span>Payment Status</span>
                        </div>
                        <div class="chart-actions">
                            <button class="chart-action-btn active" onclick="changePaymentChart('doughnut')">Donut</button>
                            <button class="chart-action-btn" onclick="changePaymentChart('pie')">Pie</button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>
                    <div class="chart-legend" id="paymentLegend"></div>
                </div>
            </div>
        </div>
    </main>

    <!-- Expense Details Modal -->
    <div class="modal" id="expenseModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="ph ph-list-bullets" style="color: var(--color-expense);"></i>
                    <span>Expense Details - EVERY Salary Row Counted</span>
                </div>
                <button class="modal-close" onclick="closeExpenseDetails()">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            
            <div style="margin-bottom: 24px;">
                <div style="background: rgba(239, 68, 68, 0.1); padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                    <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 8px; font-weight: 500;">Total Expenses</div>
                    <div style="font-size: 1.8rem; font-weight: 700; color: var(--color-expense);">$<?php echo number_format($boxExpense, 2); ?></div>
                    <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">
                        <?php echo $displayDateRange; ?> • 
                        <span style="color: var(--color-salary); font-weight: 600;">
                            <?php echo $salaryCount; ?> salary rows counted
                        </span>
                    </div>
                </div>
                
                <div style="margin-bottom: 24px;">
                    <div style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="ph ph-list-dashes"></i>
                        <span>Expense Breakdown</span>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: var(--bg-body); border-radius: 10px; border-left: 4px solid var(--color-expense);">
                            <div>
                                <div style="font-size: 0.85rem; color: var(--text-muted);">Production Costs</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Manufacturing expenses</div>
                            </div>
                            <div style="font-weight: 700; font-size: 1rem; color: var(--color-expense);">$<?php echo number_format($prodCost, 2); ?></div>
                        </div>
                        
                        <?php if ($monthlySalaryPayments > 0): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: var(--bg-body); border-radius: 10px; border-left: 4px solid var(--color-salary);">
                            <div>
                                <div style="font-size: 0.85rem; color: var(--color-salary);">Salary Payments</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">
                                    Employee compensation - <?php echo $salaryCount; ?> individual payments counted
                                </div>
                            </div>
                            <div style="font-weight: 700; font-size: 1rem; color: var(--color-salary);">$<?php echo number_format($monthlySalaryPayments, 2); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($accessoryCost > 0): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: var(--bg-body); border-radius: 10px; border-left: 4px solid #06B6D4;">
                            <div>
                                <div style="font-size: 0.85rem; color: var(--text-main);">Accessory Purchases</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Raw materials & components</div>
                            </div>
                            <div style="font-weight: 700; font-size: 1rem; color: #06B6D4;">$<?php echo number_format($accessoryCost, 2); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($subcontractorPayments > 0): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: var(--bg-body); border-radius: 10px; border-left: 4px solid var(--color-subcontractor);">
                            <div>
                                <div style="font-size: 0.85rem; color: var(--text-main);">Subcontractor Payments</div>
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Work order / batch payments</div>
                            </div>
                            <div style="font-weight: 700; font-size: 1rem; color: var(--color-subcontractor);">$<?php echo number_format($subcontractorPayments, 2); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($expenseDetails)): ?>
                            <?php foreach ($expenseDetails as $table => $amount): ?>
                                <?php if ($table !== 'salary_payments' && $table !== 'accessory_purchases' && $table !== 'subcontractor_payments'): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: var(--bg-body); border-radius: 10px; border-left: 4px solid var(--primary);">
                                    <div>
                                        <div style="font-size: 0.85rem; color: var(--text-main);"><?php echo formatTableName($table); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 2px;">Operational expenses</div>
                                    </div>
                                    <div style="font-weight: 700; font-size: 1rem; color: var(--text-main);">$<?php echo number_format($amount, 2); ?></div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (!empty($salaryRows)): ?>
                <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid rgba(139, 92, 246, 0.2);">
                    <div style="font-size: 0.9rem; color: var(--color-salary); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                        <i class="ph ph-currency-dollar"></i>
                        <span>All Salary Payments (<?php echo $salaryCount; ?> rows)</span>
                        <span style="font-size: 0.75rem; background: rgba(139, 92, 246, 0.1); padding: 2px 8px; border-radius: 10px;">
                            Total: $<?php echo number_format($monthlySalaryPayments, 2); ?>
                        </span>
                    </div>
                    
                    <?php if (count($salaryRows) > 0): ?>
                    <div style="overflow-x: auto; max-height: 300px;">
                        <table class="salary-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Employee</th>
                                    <th>Payment Month</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $rowNum = 1; ?>
                                <?php $manualTotal = 0; ?>
                                <?php foreach ($salaryRows as $salary): ?>
                                <?php 
                                $salaryAmount = 0.0;
                                if (isset($salary[$amountColumn])) {
                                    $salaryAmount = (float)$salary[$amountColumn];
                                } elseif (isset($salary['amount'])) {
                                    $salaryAmount = (float)$salary['amount'];
                                }
                                $manualTotal += $salaryAmount;
                                ?>
                                <tr>
                                    <td style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $rowNum++; ?></td>
                                    <td><?php echo e(getEmployeeName($pdo, $salary[$employeeColumn] ?? $salary['employee_id'] ?? '')); ?></td>
                                    <td><?php echo e($salary['payment_month'] ?? date('M Y', strtotime($salary[$dateColumn] ?? ''))); ?></td>
                                    <td>
                                        <div class="salary-date"><?php echo date('M d, Y', strtotime($salary[$dateColumn] ?? $salary['payment_date'] ?? '')); ?></div>
                                    </td>
                                    <td>
                                        <div class="salary-amount">$<?php echo number_format($salaryAmount, 2); ?></div>
                                    </td>
                                    <td><?php echo e($salary['payment_method'] ?? 'Cash'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <!-- Total Row -->
                                <tr style="background: rgba(139, 92, 246, 0.05); border-top: 2px solid rgba(139, 92, 246, 0.2);">
                                    <td colspan="4" style="text-align: right; font-weight: 600; color: var(--color-salary);">
                                        TOTAL (<?php echo $salaryCount; ?> rows):
                                    </td>
                                    <td style="font-weight: 700; font-size: 1rem; color: var(--color-salary);">
                                        $<?php echo number_format($manualTotal, 2); ?>
                                    </td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 12px; padding: 10px; background: rgba(139, 92, 246, 0.05); border-radius: 8px; font-size: 0.8rem; color: var(--text-muted);">
                        <i class="ph ph-info" style="color: var(--color-salary);"></i>
                        <span>Every salary row above has been individually counted in the total expenses.</span>
                    </div>
                    <?php else: ?>
                    <div style="text-align: center; padding: 20px; color: var(--text-muted);">
                        <i class="ph ph-info" style="font-size: 2rem; margin-bottom: 10px; opacity: 0.5;"></i>
                        <p>No salary payments found for the selected date range.</p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($salaryMonthlyDetails) && $filter === 'year'): ?>
                <div style="margin-top: 24px; padding-top: 24px; border-top: 2px solid rgba(139, 92, 246, 0.2);">
                    <div style="font-size: 0.9rem; color: var(--color-salary); margin-bottom: 16px; display: flex; align-items: center; gap: 8px; font-weight: 600;">
                        <i class="ph ph-calendar-dots"></i>
                        <span>Monthly Salary Breakdown (<?php echo date('Y'); ?>)</span>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                        <?php foreach ($salaryMonthlyDetails as $month => $amount): ?>
                        <div style="background: rgba(139, 92, 246, 0.08); padding: 12px; border-radius: 10px; border: 1px solid rgba(139, 92, 246, 0.2);">
                            <div style="font-size: 0.75rem; color: var(--color-salary); font-weight: 500;"><?php echo $month; ?></div>
                            <div style="font-size: 0.9rem; font-weight: 700; color: var(--color-salary); margin-top: 4px;">$<?php echo number_format($amount, 2); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <button onclick="closeExpenseDetails()" style="width: 100%; padding: 14px; background: var(--primary); color: white; border: none; border-radius: 12px; cursor: pointer; font-weight: 600; font-size: 0.95rem; transition: all 0.3s ease;">
                Close Details
            </button>
        </div>
    </div>

    <script>
        // --- 1. Sidebar Toggle Logic ---
        const sidebarToggle = document.getElementById('sidebarToggle');
        const overlay = document.getElementById('overlay');
        const body = document.body;

        function handleSidebarToggle() {
            if (window.innerWidth <= 768) {
                body.classList.toggle('mobile-open');
                body.classList.remove('sidebar-collapsed'); 
            } else {
                body.classList.toggle('sidebar-collapsed');
                if(body.classList.contains('sidebar-collapsed')) {
                    document.querySelectorAll('.menu-item.open').forEach(item => item.classList.remove('open'));
                }
            }
        }

        sidebarToggle.addEventListener('click', handleSidebarToggle);
        overlay.addEventListener('click', () => {
            body.classList.remove('mobile-open');
            closeAllDropdowns();
            closeExpenseDetails();
        });

        // --- 2. Accordion Logic ---
        const menuItems = document.querySelectorAll('.has-submenu');
        menuItems.forEach(item => {
            const link = item.querySelector('.menu-link');
            link.addEventListener('click', (e) => {
                if(body.classList.contains('sidebar-collapsed')) {
                    body.classList.remove('sidebar-collapsed');
                    setTimeout(() => { item.classList.add('open'); }, 100);
                    return;
                }
                
                e.preventDefault();
                const isOpen = item.classList.contains('open');
                menuItems.forEach(i => i.classList.remove('open')); 
                if (!isOpen) item.classList.add('open');
            });
        });

        // --- 3. Profile Dropdown Logic ---
        function toggleProfileMenu() {
            const dropdown = document.getElementById('profileDropdown');
            const isShown = dropdown.classList.contains('show');
            closeAllDropdowns();
            if (!isShown) dropdown.classList.add('show');
        }

        function closeAllDropdowns() {
            document.querySelectorAll('.dropdown-menu').forEach(d => d.classList.remove('show'));
        }

        window.addEventListener('click', function(e) {
            if (!document.getElementById('profileContainer').contains(e.target)) {
                closeAllDropdowns();
            }
        });

        // --- 4. Dark Mode ---
        const themeBtn = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        
        if(localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            themeIcon.classList.replace('ph-moon', 'ph-sun');
        }

        themeBtn.addEventListener('click', () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            if(isDark) {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                themeIcon.classList.replace('ph-sun', 'ph-moon');
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                themeIcon.classList.replace('ph-moon', 'ph-sun');
            }
        });

        // --- 5. Date Range Picker ---
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize flatpickr for date inputs
            const startDatePicker = flatpickr("#startDate", {
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        endDatePicker.set('minDate', dateStr);
                    }
                }
            });
            
            const endDatePicker = flatpickr("#endDate", {
                dateFormat: "Y-m-d",
                maxDate: "today",
                onChange: function(selectedDates, dateStr) {
                    if (dateStr) {
                        startDatePicker.set('maxDate', dateStr);
                    }
                }
            });
            
            // Set initial date limits
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('startDate').max = today;
            document.getElementById('endDate').max = today;
            
            // If custom dates are set, apply min/max
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (startDate) {
                endDatePicker.set('minDate', startDate);
            }
            if (endDate) {
                startDatePicker.set('maxDate', endDate);
            }
        });

        // --- 6. Filter Functions ---
        function toggleCustomDates() {
            const rangeSelect = document.getElementById('rangeSelect');
            const customDates = document.getElementById('customDates');
            
            if (rangeSelect.value === 'custom') {
                customDates.style.display = 'flex';
            } else {
                customDates.style.display = 'none';
                // Submit form when changing predefined ranges
                document.getElementById('dashboardFilter').submit();
            }
        }
        
        function resetFilter() {
            window.location.href = 'admin_dashboard.php';
        }
        
        // --- 7. Expense Details Modal ---
        function showExpenseDetails() {
            document.getElementById('expenseModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }
        
        function closeExpenseDetails() {
            document.getElementById('expenseModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        document.getElementById('expenseModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeExpenseDetails();
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeExpenseDetails();
                closeAllDropdowns();
            }
        });

        // --- 8. Chart Instances ---
        let financeChart, productsChart, expenseChart, customerChart, paymentChart;
        let currentProductChartType = 'quantity';
        let currentFinanceChartType = 'line';
        let currentCustomerChartType = 'bar';
        let currentPaymentChartType = 'doughnut';

        // --- 9. Revenue vs Expenses Chart ---
        function initFinanceChart(type = 'line') {
            const ctx = document.getElementById('financeChart').getContext('2d');
            
            // Check if we have data
            const incomeData = <?php echo $jsIncome; ?>;
            const expenseData = <?php echo $jsExpense; ?>;
            const labels = <?php echo $jsLabels; ?>;
            
            // Create gradients
            const gradIncome = ctx.createLinearGradient(0, 0, 0, 300);
            const gradExpense = ctx.createLinearGradient(0, 0, 0, 300);
            
            if (document.documentElement.getAttribute('data-theme') === 'dark') {
                gradIncome.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
                gradIncome.addColorStop(1, 'rgba(16, 185, 129, 0.05)');
                gradExpense.addColorStop(0, 'rgba(239, 68, 68, 0.2)');
                gradExpense.addColorStop(1, 'rgba(239, 68, 68, 0.02)');
            } else {
                gradIncome.addColorStop(0, 'rgba(16, 185, 129, 0.25)');
                gradIncome.addColorStop(1, 'rgba(16, 185, 129, 0.05)');
                gradExpense.addColorStop(0, 'rgba(239, 68, 68, 0.15)');
                gradExpense.addColorStop(1, 'rgba(239, 68, 68, 0.02)');
            }

            const chartConfig = {
                type: type,
                data: {
                    labels: labels,
                    datasets: [
                        { 
                            label: 'Paid Revenue', 
                            data: incomeData, 
                            borderColor: '#10B981', 
                            backgroundColor: type === 'line' ? gradIncome : '#10B981', 
                            borderWidth: 3, 
                            fill: type === 'line', 
                            tension: 0.4,
                            pointBackgroundColor: '#10B981',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        },
                        { 
                            label: 'Total Expenses', 
                            data: expenseData, 
                            borderColor: '#EF4444', 
                            backgroundColor: type === 'line' ? gradExpense : '#EF4444', 
                            borderWidth: 3, 
                            borderDash: type === 'line' ? [6, 4] : [],
                            fill: type === 'line', 
                            tension: 0.4,
                            pointBackgroundColor: '#EF4444',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { 
                        legend: { 
                            display: false
                        }, 
                        tooltip: { 
                            mode: 'index', 
                            intersect: false,
                            backgroundColor: 'var(--bg-card)',
                            titleColor: 'var(--text-main)',
                            bodyColor: 'var(--text-muted)',
                            borderColor: 'var(--border)',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            boxPadding: 6,
                            usePointStyle: true,
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += '$' + context.parsed.y.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    });
                                    return label;
                                }
                            }
                        } 
                    },
                    scales: { 
                        x: { 
                            grid: { 
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                },
                                color: 'var(--text-muted)',
                                padding: 10
                            }
                        }, 
                        y: { 
                            beginAtZero: true, 
                            grid: { 
                                borderDash: [5, 5],
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                },
                                color: 'var(--text-muted)',
                                padding: 10,
                                callback: function(value) {
                                    if (value >= 1000) {
                                        return '$' + (value / 1000).toFixed(1) + 'k';
                                    }
                                    return '$' + value;
                                }
                            }
                        } 
                    },
                    interaction: { 
                        mode: 'nearest', 
                        axis: 'x', 
                        intersect: false 
                    },
                    elements: {
                        line: {
                            tension: 0.4
                        },
                        bar: {
                            borderRadius: 4
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            };

            if (financeChart) {
                financeChart.destroy();
            }
            
            financeChart = new Chart(ctx, chartConfig);
            updateLegend('financeLegend', chartConfig.data.datasets);
        }

        function changeChartType(type) {
            currentFinanceChartType = type;
            const buttons = document.querySelectorAll('#financeChart').closest('.card').querySelectorAll('.chart-action-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            initFinanceChart(type);
        }

        // --- 10. Top Selling Products Chart ---
        function initProductsChart(type = 'quantity') {
            const ctx = document.getElementById('productsChart').getContext('2d');
            const data = type === 'quantity' ? <?php echo $jsProductQuantities; ?> : <?php echo $jsProductRevenues; ?>;
            const labels = <?php echo $jsProductLabels; ?>;
            const colors = <?php echo $jsProductColors; ?>;
            
            const chartConfig = {
                type: 'pie',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: 'var(--bg-card)',
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'var(--bg-card)',
                            titleColor: 'var(--text-main)',
                            bodyColor: 'var(--text-muted)',
                            borderColor: 'var(--border)',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    
                                    let displayValue;
                                    if (type === 'quantity') {
                                        displayValue = `${value} units`;
                                    } else {
                                        displayValue = `$${value.toLocaleString('en-US', {
                                            minimumFractionDigits: 2,
                                            maximumFractionDigits: 2
                                        })}`;
                                    }
                                    
                                    return `${label}: ${displayValue} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 1000
                    }
                }
            };

            if (productsChart) {
                productsChart.destroy();
            }
            
            productsChart = new Chart(ctx, chartConfig);
            
            // Update legend with custom colors
            const legendContainer = document.getElementById('productsLegend');
            legendContainer.innerHTML = '';
            
            labels.forEach((label, index) => {
                const color = colors[index];
                const value = type === 'quantity' ? 
                    data[index] + ' units' : 
                    '$' + data[index].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.innerHTML = `
                    <div class="legend-color" style="background-color: ${color};"></div>
                    <span style="color: var(--text-muted);">${label}</span>
                    <span style="margin-left: auto; font-weight: 600; color: var(--text-main);">${value}</span>
                `;
                legendContainer.appendChild(legendItem);
            });
        }

        function changeProductChart(type) {
            currentProductChartType = type;
            const buttons = document.querySelectorAll('#productsChart').closest('.card').querySelectorAll('.chart-action-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            initProductsChart(type);
        }

        // --- 11. Expense Distribution Chart ---
        function initExpenseChart() {
            const ctx = document.getElementById('expenseChart').getContext('2d');
            const labels = <?php echo $jsExpenseLabels; ?>;
            const data = <?php echo $jsExpenseData; ?>;
            const colors = <?php echo $jsExpenseColors; ?>;
            
            const chartConfig = {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: 'var(--bg-card)',
                        hoverOffset: 20
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '65%',
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'var(--bg-card)',
                            titleColor: 'var(--text-main)',
                            bodyColor: 'var(--text-muted)',
                            borderColor: 'var(--border)',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: $${value.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })} (${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 1000
                    }
                }
            };

            if (expenseChart) {
                expenseChart.destroy();
            }
            
            expenseChart = new Chart(ctx, chartConfig);
            
            // Update legend
            const legendContainer = document.getElementById('expenseLegend');
            legendContainer.innerHTML = '';
            
            labels.forEach((label, index) => {
                const color = colors[index];
                const value = '$' + data[index].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.innerHTML = `
                    <div class="legend-color" style="background-color: ${color};"></div>
                    <span style="color: var(--text-muted);">${label}</span>
                    <span style="margin-left: auto; font-weight: 600; color: var(--text-main);">${value}</span>
                `;
                legendContainer.appendChild(legendItem);
            });
        }

        // --- 12. Customer Acquisition Chart ---
        function initCustomerChart(type = 'bar') {
            const ctx = document.getElementById('customerChart').getContext('2d');
            const labels = <?php echo $jsCustomerLabels; ?>;
            const data = <?php echo $jsCustomerData; ?>;
            
            const chartConfig = {
                type: type,
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'New Customers',
                        data: data,
                        backgroundColor: type === 'bar' ? '#4F46E5' : 'transparent',
                        borderColor: '#4F46E5',
                        borderWidth: type === 'line' ? 3 : 0,
                        fill: type === 'line',
                        tension: 0.4,
                        pointBackgroundColor: '#4F46E5',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 5,
                        pointHoverRadius: 7
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'var(--bg-card)',
                            titleColor: 'var(--text-main)',
                            bodyColor: 'var(--text-muted)',
                            borderColor: 'var(--border)',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    return `New Customers: ${context.parsed.y}`;
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                },
                                color: 'var(--text-muted)',
                                padding: 10
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                borderDash: [5, 5],
                                drawBorder: false,
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                font: {
                                    size: 11,
                                    family: "'Inter', sans-serif"
                                },
                                color: 'var(--text-muted)',
                                padding: 10,
                                precision: 0
                            }
                        }
                    },
                    elements: {
                        bar: {
                            borderRadius: 4
                        },
                        line: {
                            tension: 0.4
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeOutQuart'
                    }
                }
            };

            if (customerChart) {
                customerChart.destroy();
            }
            
            customerChart = new Chart(ctx, chartConfig);
        }

        function changeCustomerChart(type) {
            currentCustomerChartType = type;
            const buttons = document.querySelectorAll('#customerChart').closest('.card').querySelectorAll('.chart-action-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            initCustomerChart(type);
        }

        // --- 13. Payment Status Chart ---
        function initPaymentChart(type = 'doughnut') {
            const ctx = document.getElementById('paymentChart').getContext('2d');
            const labels = <?php echo $jsPaymentLabels; ?>;
            const data = <?php echo $jsPaymentAmounts; ?>;
            const colors = <?php echo $jsPaymentColors; ?>;
            
            const chartConfig = {
                type: type,
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        borderWidth: 2,
                        borderColor: 'var(--bg-card)',
                        hoverOffset: type === 'doughnut' ? 20 : 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: type === 'doughnut' ? '65%' : 0,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: 'var(--bg-card)',
                            titleColor: 'var(--text-main)',
                            bodyColor: 'var(--text-muted)',
                            borderColor: 'var(--border)',
                            borderWidth: 1,
                            padding: 12,
                            cornerRadius: 8,
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const count = <?php echo $jsPaymentCounts; ?>[context.dataIndex];
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                    return `${label}: $${value.toLocaleString('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })} (${count} orders, ${percentage}%)`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateScale: true,
                        animateRotate: true,
                        duration: 1000
                    }
                }
            };

            if (paymentChart) {
                paymentChart.destroy();
            }
            
            paymentChart = new Chart(ctx, chartConfig);
            
            // Update legend
            const legendContainer = document.getElementById('paymentLegend');
            legendContainer.innerHTML = '';
            
            labels.forEach((label, index) => {
                const color = colors[index];
                const value = '$' + data[index].toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                const count = <?php echo $jsPaymentCounts; ?>[index];
                
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.innerHTML = `
                    <div class="legend-color" style="background-color: ${color};"></div>
                    <span style="color: var(--text-muted);">${label}</span>
                    <span style="margin-left: auto; display: flex; gap: 8px;">
                        <span style="font-weight: 600; color: var(--text-main);">${value}</span>
                        <span style="color: var(--text-muted);">(${count})</span>
                    </span>
                `;
                legendContainer.appendChild(legendItem);
            });
        }

        function changePaymentChart(type) {
            currentPaymentChartType = type;
            const buttons = document.querySelectorAll('#paymentChart').closest('.card').querySelectorAll('.chart-action-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            initPaymentChart(type);
        }

        // --- 14. Helper Functions ---
        function updateLegend(containerId, datasets) {
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            
            datasets.forEach(dataset => {
                const legendItem = document.createElement('div');
                legendItem.className = 'legend-item';
                legendItem.innerHTML = `
                    <div class="legend-color" style="background-color: ${dataset.borderColor};"></div>
                    <span style="color: var(--text-muted);">${dataset.label}</span>
                `;
                container.appendChild(legendItem);
            });
        }

        // --- 15. Initialize All Charts ---
        document.addEventListener('DOMContentLoaded', function() {
            try {
                initFinanceChart(currentFinanceChartType);
                initProductsChart(currentProductChartType);
                initExpenseChart();
                initCustomerChart(currentCustomerChartType);
                initPaymentChart(currentPaymentChartType);
            } catch (error) {
                console.error('Error initializing charts:', error);
            }
            
            // Auto-refresh every 5 minutes
            setInterval(() => {
                if (!document.hidden) {
                    window.location.reload();
                }
            }, 300000);
        });

        // --- 16. Add loading state to filter form ---
        const filterSelect = document.getElementById('rangeSelect');
        filterSelect.addEventListener('change', function() {
            if (this.value !== 'custom') {
                const form = this.closest('form');
                form.classList.add('loading');
                setTimeout(() => {
                    form.classList.remove('loading');
                }, 1000);
            }
        });
    </script>
</body>
</html>