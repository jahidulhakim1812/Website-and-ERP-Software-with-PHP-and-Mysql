<?php
/**
 * work_order_payments.php
 * Work Order Payment Management Page
 * Record and manage payments to subcontractors for completed work orders
 */

session_start();

// --- 1. AUTHENTICATION & SECURITY ---
session_start();

// Security headers to prevent caching
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page - NO AUTO-LOGIN!
    header("Location: login.php");  // You need to create this file
    exit();
}

// Session timeout - 30 minutes
$timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['login_time'])) {
    $session_life = time() - $_SESSION['login_time'];
    if ($session_life > $timeout) {
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }
}
$_SESSION['login_time'] = time();

// --- LOGOUT HANDLING ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear any auth headers
    header_remove();
    
    // Redirect to login page or home with cache prevention headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Location: login.php");  // Changed from admin_dashboard.php to login.php
    exit(); // IMPORTANT: exit AFTER redirect header
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
    die("Database connection failed: " . $ex->getMessage());
}

// --- 3. HELPER FUNCTIONS ---
function e($val) { return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); }
function formatCurrency($amount) { return '$' . number_format($amount, 2); }
function formatDate($date, $format = 'M d, Y') { 
    if (empty($date)) return 'N/A';
    return date($format, strtotime($date)); 
}

// --- 4. HANDLE ACTIONS ---
$message = '';
$msg_type = '';

// Record New Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    try {
        $work_order_id = $_POST['work_order_id'] ?? 0;
        $payment_amount = $_POST['payment_amount'] ?? 0;
        $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
        $payment_method = $_POST['payment_method'] ?? '';
        $reference_number = $_POST['reference_number'] ?? '';
        $payment_notes = $_POST['payment_notes'] ?? '';
        $payment_type = $_POST['payment_type'] ?? 'partial';
        
        // Validate
        if (!$work_order_id) {
            throw new Exception("Please select a work order.");
        }
        
        if ($payment_amount <= 0) {
            throw new Exception("Payment amount must be greater than zero.");
        }
        
        // Get work order details
        $stmt = $pdo->prepare("SELECT wo.*, s.company_name, s.bank_account_info, s.contact_person 
                               FROM subcontractor_work_orders wo
                               JOIN subcontractors s ON wo.subcontractor_id = s.id
                               WHERE wo.id = ?");
        $stmt->execute([$work_order_id]);
        $work_order = $stmt->fetch();
        
        if (!$work_order) {
            throw new Exception("Work order not found.");
        }
        
        // Check if payment exceeds remaining balance
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid 
                               FROM work_order_payments 
                               WHERE work_order_id = ? AND status != 'refunded'");
        $stmt->execute([$work_order_id]);
        $total_paid = $stmt->fetch()['total_paid'];
        
        $actual_cost = $work_order['actual_cost'] ?? $work_order['estimated_cost'] ?? 0;
        $remaining_balance = $actual_cost - $total_paid;
        
        if ($payment_amount > $remaining_balance) {
            throw new Exception("Payment amount ($" . number_format($payment_amount, 2) . ") exceeds remaining balance ($" . number_format($remaining_balance, 2) . ").");
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Insert payment record
            $stmt = $pdo->prepare("INSERT INTO work_order_payments 
                                   (work_order_id, amount, payment_date, payment_method, reference_number, notes, payment_type, created_by, status)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'completed')");
            $stmt->execute([
                $work_order_id,
                $payment_amount,
                $payment_date,
                $payment_method,
                $reference_number,
                $payment_notes,
                $payment_type,
                $_SESSION['user_id']
            ]);
            
            $payment_id = $pdo->lastInsertId();
            
            // Update work order payment status
            $new_total_paid = $total_paid + $payment_amount;
            $payment_status = 'Partially Paid';
            
            if (abs($new_total_paid - $actual_cost) < 0.01) { // Using tolerance for float comparison
                $payment_status = 'Fully Paid';
            } elseif ($new_total_paid >= $actual_cost) {
                $payment_status = 'Overpaid';
            }
            
            $stmt = $pdo->prepare("UPDATE subcontractor_work_orders 
                                   SET payment_status = ?, last_payment_date = ?, total_paid = ?
                                   WHERE id = ?");
            $stmt->execute([
                $payment_status,
                $payment_date,
                $new_total_paid,
                $work_order_id
            ]);
            
            // Record payment activity
            $stmt = $pdo->prepare("INSERT INTO payment_activities 
                                   (payment_id, work_order_id, action, description, performed_by)
                                   VALUES (?, ?, 'payment_recorded', ?, ?)");
            $stmt->execute([
                $payment_id,
                $work_order_id,
                "Payment of " . formatCurrency($payment_amount) . " recorded via " . $payment_method,
                $_SESSION['user_id']
            ]);
            
            $pdo->commit();
            
            $message = "Payment of " . formatCurrency($payment_amount) . " recorded successfully!";
            $msg_type = 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e; // Re-throw to be caught by outer try-catch
        }
        
    } catch (Exception $e) {
        $message = "Error recording payment: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Delete Payment
if (isset($_GET['delete_payment'])) {
    try {
        $payment_id = $_GET['delete_payment'];
        
        // Get payment details
        $stmt = $pdo->prepare("SELECT * FROM work_order_payments WHERE id = ?");
        $stmt->execute([$payment_id]);
        $payment = $stmt->fetch();
        
        if (!$payment) {
            throw new Exception("Payment not found.");
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Delete payment
            $stmt = $pdo->prepare("DELETE FROM work_order_payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            
            // Recalculate work order payment status
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid 
                                   FROM work_order_payments 
                                   WHERE work_order_id = ? AND status != 'refunded'");
            $stmt->execute([$payment['work_order_id']]);
            $new_total_paid = $stmt->fetch()['total_paid'];
            
            // Get work order cost
            $stmt = $pdo->prepare("SELECT actual_cost, estimated_cost FROM subcontractor_work_orders WHERE id = ?");
            $stmt->execute([$payment['work_order_id']]);
            $wo = $stmt->fetch();
            $actual_cost = $wo['actual_cost'] ?? $wo['estimated_cost'] ?? 0;
            
            // Determine payment status
            $payment_status = 'Unpaid';
            if ($new_total_paid > 0) {
                if (abs($new_total_paid - $actual_cost) < 0.01) {
                    $payment_status = 'Fully Paid';
                } elseif ($new_total_paid > $actual_cost) {
                    $payment_status = 'Overpaid';
                } else {
                    $payment_status = 'Partially Paid';
                }
            }
            
            // Update work order
            $stmt = $pdo->prepare("UPDATE subcontractor_work_orders 
                                   SET payment_status = ?, total_paid = ?
                                   WHERE id = ?");
            $stmt->execute([
                $payment_status,
                $new_total_paid,
                $payment['work_order_id']
            ]);
            
            // Record activity
            $stmt = $pdo->prepare("INSERT INTO payment_activities 
                                   (work_order_id, action, description, performed_by)
                                   VALUES (?, 'payment_deleted', ?, ?)");
            $stmt->execute([
                $payment['work_order_id'],
                "Payment of " . formatCurrency($payment['amount']) . " deleted",
                $_SESSION['user_id']
            ]);
            
            $pdo->commit();
            
            $message = "Payment deleted successfully!";
            $msg_type = 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e; // Re-throw to be caught by outer try-catch
        }
        
    } catch (Exception $e) {
        $message = "Error deleting payment: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Mark Payment as Refunded
if (isset($_GET['refund_payment'])) {
    try {
        $payment_id = $_GET['refund_payment'];
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Get payment details
            $stmt = $pdo->prepare("SELECT * FROM work_order_payments WHERE id = ?");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch();
            
            if (!$payment) {
                throw new Exception("Payment not found.");
            }
            
            // Mark as refunded
            $stmt = $pdo->prepare("UPDATE work_order_payments SET status = 'refunded', refund_date = NOW() WHERE id = ?");
            $stmt->execute([$payment_id]);
            
            // Recalculate work order payment status
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid 
                                   FROM work_order_payments 
                                   WHERE work_order_id = ? AND status != 'refunded'");
            $stmt->execute([$payment['work_order_id']]);
            $new_total_paid = $stmt->fetch()['total_paid'];
            
            // Get work order cost
            $stmt = $pdo->prepare("SELECT actual_cost, estimated_cost FROM subcontractor_work_orders WHERE id = ?");
            $stmt->execute([$payment['work_order_id']]);
            $wo = $stmt->fetch();
            $actual_cost = $wo['actual_cost'] ?? $wo['estimated_cost'] ?? 0;
            
            // Determine payment status
            $payment_status = 'Unpaid';
            if ($new_total_paid > 0) {
                if (abs($new_total_paid - $actual_cost) < 0.01) {
                    $payment_status = 'Fully Paid';
                } elseif ($new_total_paid > $actual_cost) {
                    $payment_status = 'Overpaid';
                } else {
                    $payment_status = 'Partially Paid';
                }
            }
            
            // Update work order
            $stmt = $pdo->prepare("UPDATE subcontractor_work_orders 
                                   SET payment_status = ?, total_paid = ?
                                   WHERE id = ?");
            $stmt->execute([
                $payment_status,
                $new_total_paid,
                $payment['work_order_id']
            ]);
            
            // Record activity
            $stmt = $pdo->prepare("INSERT INTO payment_activities 
                                   (payment_id, work_order_id, action, description, performed_by)
                                   VALUES (?, ?, 'payment_refunded', ?, ?)");
            $stmt->execute([
                $payment_id,
                $payment['work_order_id'],
                "Payment of " . formatCurrency($payment['amount']) . " marked as refunded",
                $_SESSION['user_id']
            ]);
            
            $pdo->commit();
            
            $message = "Payment marked as refunded successfully!";
            $msg_type = 'success';
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e; // Re-throw to be caught by outer try-catch
        }
        
    } catch (Exception $e) {
        $message = "Error refunding payment: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// Bulk Payment Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_payment_action'])) {
    try {
        $payment_ids = $_POST['selected_payments'] ?? [];
        
        if (empty($payment_ids)) {
            throw new Exception("No payments selected.");
        }
        
        $placeholders = str_repeat('?,', count($payment_ids) - 1) . '?';
        
        switch ($_POST['bulk_payment_action']) {
            case 'export':
                // Export selected payments
                header("Location: export_payments.php?ids=" . urlencode(implode(',', $payment_ids)));
                exit;
                break;
                
            case 'delete':
                // Begin transaction
                $pdo->beginTransaction();
                
                try {
                    foreach ($payment_ids as $payment_id) {
                        // Get payment details
                        $stmt = $pdo->prepare("SELECT * FROM work_order_payments WHERE id = ?");
                        $stmt->execute([$payment_id]);
                        $payment = $stmt->fetch();
                        
                        if ($payment) {
                            // Delete payment
                            $stmt = $pdo->prepare("DELETE FROM work_order_payments WHERE id = ?");
                            $stmt->execute([$payment_id]);
                            
                            // Record activity
                            $stmt = $pdo->prepare("INSERT INTO payment_activities 
                                                   (work_order_id, action, description, performed_by)
                                                   VALUES (?, 'payment_deleted', ?, ?)");
                            $stmt->execute([
                                $payment['work_order_id'],
                                "Payment of " . formatCurrency($payment['amount']) . " deleted (bulk action)",
                                $_SESSION['user_id']
                            ]);
                        }
                    }
                    
                    // Recalculate all affected work orders
                    $stmt = $pdo->prepare("SELECT DISTINCT work_order_id FROM work_order_payments WHERE id IN ($placeholders)");
                    $stmt->execute($payment_ids);
                    $affected_work_orders = $stmt->fetchAll();
                    
                    foreach ($affected_work_orders as $wo) {
                        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid 
                                               FROM work_order_payments 
                                               WHERE work_order_id = ? AND status != 'refunded'");
                        $stmt->execute([$wo['work_order_id']]);
                        $total_paid = $stmt->fetch()['total_paid'];
                        
                        // Get work order cost
                        $stmt = $pdo->prepare("SELECT actual_cost, estimated_cost FROM subcontractor_work_orders WHERE id = ?");
                        $stmt->execute([$wo['work_order_id']]);
                        $work_order = $stmt->fetch();
                        $actual_cost = $work_order['actual_cost'] ?? $work_order['estimated_cost'] ?? 0;
                        
                        // Determine payment status
                        $payment_status = 'Unpaid';
                        if ($total_paid > 0) {
                            if (abs($total_paid - $actual_cost) < 0.01) {
                                $payment_status = 'Fully Paid';
                            } elseif ($total_paid > $actual_cost) {
                                $payment_status = 'Overpaid';
                            } else {
                                $payment_status = 'Partially Paid';
                            }
                        }
                        
                        // Update work order
                        $stmt = $pdo->prepare("UPDATE subcontractor_work_orders 
                                               SET payment_status = ?, total_paid = ?
                                               WHERE id = ?");
                        $stmt->execute([
                            $payment_status,
                            $total_paid,
                            $wo['work_order_id']
                        ]);
                    }
                    
                    $pdo->commit();
                    
                    $message = count($payment_ids) . " payment(s) deleted successfully!";
                    $msg_type = 'success';
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e; // Re-throw to be caught by outer try-catch
                }
                break;
        }
        
    } catch (Exception $e) {
        $message = "Bulk action error: " . $e->getMessage();
        $msg_type = 'error';
    }
}

// --- 5. GET USER PHOTO ---
$userPhoto = '';
try {
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userData = $stmt->fetch();
    $userPhoto = $userData['avatar'] ?? '';
} catch (Exception $e) {
    $userPhoto = '';
}

// --- 6. FETCH DATA FOR FILTERS ---
$search = $_GET['search'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$payment_status_filter = $_GET['payment_status'] ?? 'all';
$payment_method_filter = $_GET['payment_method'] ?? 'all';
$subcontractor_filter = $_GET['subcontractor'] ?? 'all';

// Fetch work orders for payment dropdown (completed work orders with pending payments)
try {
    $query = "SELECT wo.id, wo.work_order_number, wo.project_name, 
                     wo.estimated_cost, wo.actual_cost, wo.total_paid,
                     wo.payment_status, s.company_name,
                     (COALESCE(wo.actual_cost, wo.estimated_cost) - COALESCE(wo.total_paid, 0)) as remaining_balance
              FROM subcontractor_work_orders wo
              JOIN subcontractors s ON wo.subcontractor_id = s.id
              WHERE wo.work_status IN ('Completed', 'In Progress')
              AND (COALESCE(wo.actual_cost, wo.estimated_cost) > COALESCE(wo.total_paid, 0) OR wo.total_paid IS NULL)
              ORDER BY wo.payment_status ASC, wo.end_date ASC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $work_orders_for_payment = $stmt->fetchAll();
} catch (Exception $e) {
    $work_orders_for_payment = [];
}

// Fetch all payments with filters
$query = "SELECT p.*, 
                 wo.work_order_number, wo.project_name,
                 s.company_name, s.contact_person,
                 u.username as recorded_by
          FROM work_order_payments p
          JOIN subcontractor_work_orders wo ON p.work_order_id = wo.id
          JOIN subcontractors s ON wo.subcontractor_id = s.id
          LEFT JOIN users u ON p.created_by = u.id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (p.reference_number LIKE ? OR wo.work_order_number LIKE ? OR s.company_name LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
}

if ($payment_status_filter !== 'all') {
    $query .= " AND p.status = ?";
    $params[] = $payment_status_filter;
}

if ($payment_method_filter !== 'all') {
    $query .= " AND p.payment_method = ?";
    $params[] = $payment_method_filter;
}

if ($subcontractor_filter !== 'all') {
    $query .= " AND wo.subcontractor_id = ?";
    $params[] = $subcontractor_filter;
}

if (!empty($date_from)) {
    $query .= " AND p.payment_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $query .= " AND p.payment_date <= ?";
    $params[] = $date_to;
}

$query .= " ORDER BY p.payment_date DESC, p.created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
} catch (Exception $e) {
    $payments = [];
}

// Get statistics
try {
    // Payment statistics
    $total_payments = $pdo->query("SELECT COUNT(*) as count FROM work_order_payments WHERE status != 'refunded'")->fetch()['count'];
    $total_paid_amount = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM work_order_payments WHERE status != 'refunded'")->fetch()['total'];
    $pending_payments = $pdo->query("SELECT COUNT(*) as count FROM work_order_payments WHERE status = 'pending'")->fetch()['count'];
    $refunded_amount = $pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM work_order_payments WHERE status = 'refunded'")->fetch()['total'];
    
    // Work order payment statistics
    $unpaid_work_orders = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE payment_status = 'Unpaid'")->fetch()['count'];
    $partially_paid = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE payment_status = 'Partially Paid'")->fetch()['count'];
    $fully_paid = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE payment_status = 'Fully Paid'")->fetch()['count'];
    $overpaid = $pdo->query("SELECT COUNT(*) as count FROM subcontractor_work_orders WHERE payment_status = 'Overpaid'")->fetch()['count'];
    
    // Total outstanding balance
    $outstanding_balance = $pdo->query("
        SELECT COALESCE(SUM(
            CASE 
                WHEN wo.actual_cost > 0 THEN wo.actual_cost - COALESCE(wo.total_paid, 0)
                ELSE wo.estimated_cost - COALESCE(wo.total_paid, 0)
            END
        ), 0) as balance
        FROM subcontractor_work_orders wo
        WHERE (wo.actual_cost > COALESCE(wo.total_paid, 0) OR wo.estimated_cost > COALESCE(wo.total_paid, 0))
    ")->fetch()['balance'];
    
    // Get subcontractors for filter
    $subcontractors = $pdo->query("SELECT id, company_name FROM subcontractors WHERE status = 'Active' ORDER BY company_name")->fetchAll();
    
    // Recent payment activities
    $recent_activities = $pdo->query("
        SELECT pa.*, wo.work_order_number, u.username
        FROM payment_activities pa
        LEFT JOIN subcontractor_work_orders wo ON pa.work_order_id = wo.id
        LEFT JOIN users u ON pa.performed_by = u.id
        ORDER BY pa.created_at DESC
        LIMIT 10
    ")->fetchAll();
    
} catch (Exception $e) {
    $total_payments = $total_paid_amount = $pending_payments = $refunded_amount = 0;
    $unpaid_work_orders = $partially_paid = $fully_paid = $overpaid = 0;
    $outstanding_balance = 0;
    $subcontractors = [];
    $recent_activities = [];
}

// --- 7. PAGINATION ---
$per_page = 15;
$page = $_GET['page'] ?? 1;
$offset = ($page - 1) * $per_page;
$total_pages = ceil(count($payments) / $per_page);
$paged_payments = array_slice($payments, $offset, $per_page);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Order Payments | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
            --shadow: 0 2px 8px rgba(0,0,0,0.04);
            --shadow-hover: 0 8px 25px rgba(0,0,0,0.08);
            --color-success: #10B981;
            --color-warning: #F59E0B;
            --color-danger: #EF4444;
            --color-info: #3B82F6;
            --color-pending: #F59E0B;
            --color-completed: #10B981;
            --color-refunded: #6B7280;
            --color-failed: #DC2626;
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

        /* --- Scrollable Content --- */
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
        
        .page-title {
            font-size: 1.6rem; 
            font-weight: 700; 
            margin-bottom: 4px;
        }
        
        .page-subtitle {
            color: var(--text-muted); 
            font-size: 0.9rem; 
            line-height: 1.4;
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.12);
            color: var(--color-success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.12);
            color: var(--color-danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .alert i {
            font-size: 1.2rem;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 1200px) {
            .stats-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 20px;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }
        
        .stat-card.total::before { background: var(--primary); }
        .stat-card.paid::before { background: var(--color-success); }
        .stat-card.pending::before { background: var(--color-warning); }
        .stat-card.outstanding::before { background: var(--color-danger); }
        .stat-card.refunded::before { background: var(--color-refunded); }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .stat-trend {
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 12px;
            margin-left: 8px;
        }
        
        .trend-up { background: rgba(16, 185, 129, 0.1); color: var(--color-success); }
        .trend-down { background: rgba(239, 68, 68, 0.1); color: var(--color-danger); }

        /* Dashboard Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }
        
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .dashboard-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 24px;
            box-shadow: var(--shadow);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
        }
        
        .card-actions {
            display: flex;
            gap: 8px;
        }

        /* Payment Form */
        .payment-form-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .form-section {
            margin-bottom: 24px;
        }
        
        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-main);
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        
        .form-input, .form-select, .form-textarea {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s ease;
            width: 100%;
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }

        /* Work Order Info Card */
        .work-order-info {
            background: var(--bg-body);
            border-radius: 8px;
            padding: 16px;
            margin-top: 12px;
            border: 1px solid var(--border);
        }
        
        .wo-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .wo-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .wo-info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .wo-info-value {
            font-weight: 500;
        }
        
        .balance-display {
            grid-column: span 2;
            background: var(--bg-card);
            border-radius: 8px;
            padding: 12px;
            margin-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .balance-label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .balance-amount {
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .balance-positive {
            color: var(--color-success);
        }
        
        .balance-zero {
            color: var(--text-muted);
        }
        
        .balance-negative {
            color: var(--color-danger);
        }

        /* Filters Section */
        .filters-section {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
        }
        
        .filters-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .filters-title {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
        }
        
        @media (max-width: 1024px) {
            .filters-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .filter-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--text-muted);
        }
        
        .filter-input, .filter-select {
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--bg-body);
            color: var(--text-main);
            font-size: 0.9rem;
            outline: none;
            transition: all 0.2s ease;
        }
        
        .filter-input:focus, .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: flex-end;
        }

        /* Bulk Actions */
        .bulk-actions {
            background: var(--bg-body);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            display: none;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            border: 1px solid var(--border);
        }
        
        .bulk-actions.show {
            display: flex;
        }
        
        .bulk-selection {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .bulk-controls {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Table Styles */
        .table-container {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(90deg, rgba(79, 70, 229, 0.05) 0%, rgba(79, 70, 229, 0.02) 100%);
        }
        
        .table-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
        }
        
        .table-actions {
            display: flex;
            gap: 8px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
        
        .data-table th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--text-muted);
            border-bottom: 2px solid var(--border);
            white-space: nowrap;
            user-select: none;
        }
        
        .data-table th.sortable {
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .data-table th.sortable:hover {
            color: var(--primary);
        }
        
        .data-table th.sortable .sort-icon {
            margin-left: 4px;
            opacity: 0.5;
            transition: opacity 0.2s ease;
        }
        
        .data-table th.sortable:hover .sort-icon {
            opacity: 1;
        }
        
        .data-table td {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        
        .data-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .data-table tbody tr:hover {
            background: var(--bg-body);
        }
        
        .data-table tbody tr.selected {
            background: rgba(79, 70, 229, 0.05);
        }
        
        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Checkbox Column */
        .select-checkbox {
            width: 40px;
            text-align: center;
        }
        
        .select-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* Payment Status Badges */
        .payment-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .payment-status-completed {
            background: rgba(16, 185, 129, 0.12);
            color: var(--color-success);
        }
        
        .payment-status-pending {
            background: rgba(245, 158, 11, 0.12);
            color: var(--color-warning);
        }
        
        .payment-status-refunded {
            background: rgba(107, 114, 128, 0.12);
            color: var(--color-refunded);
        }
        
        .payment-status-failed {
            background: rgba(220, 38, 38, 0.12);
            color: var(--color-danger);
        }

        /* Payment Method Badges */
        .payment-method-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .method-bank-transfer {
            background: rgba(59, 130, 246, 0.12);
            color: var(--color-info);
        }
        
        .method-check {
            background: rgba(139, 92, 246, 0.12);
            color: var(--primary);
        }
        
        .method-cash {
            background: rgba(16, 185, 129, 0.12);
            color: var(--color-success);
        }
        
        .method-credit-card {
            background: rgba(245, 158, 11, 0.12);
            color: var(--color-warning);
        }
        
        .method-online {
            background: rgba(239, 68, 68, 0.12);
            color: var(--color-danger);
        }

        /* Amount Cell */
        .amount-cell {
            font-weight: 600;
            color: var(--text-main);
        }
        
        .amount-positive {
            color: var(--color-success);
        }
        
        .amount-negative {
            color: var(--color-danger);
        }

        /* Reference Number */
        .reference-number {
            font-family: 'Monaco', 'Courier New', monospace;
            font-size: 0.9rem;
            color: var(--primary);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 10px;
            border-radius: 6px;
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-muted);
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8rem;
            cursor: pointer;
        }
        
        .action-btn:hover {
            background: var(--bg-card);
        }
        
        .action-btn.view:hover { color: var(--primary); border-color: var(--primary); }
        .action-btn.receipt:hover { color: var(--color-info); border-color: var(--color-info); }
        .action-btn.refund:hover { color: var(--color-warning); border-color: var(--color-warning); }
        .action-btn.delete:hover { color: var(--color-danger); border-color: var(--color-danger); }

        /* Recent Activity */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .activity-item {
            display: flex;
            gap: 12px;
            padding: 12px;
            border-radius: 8px;
            background: var(--bg-body);
            border: 1px solid transparent;
            transition: all 0.2s ease;
        }
        
        .activity-item:hover {
            border-color: var(--border);
            background: var(--bg-card);
        }
        
        .activity-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .activity-content {
            flex: 1;
            overflow: hidden;
        }
        
        .activity-title {
            font-weight: 500;
            margin-bottom: 4px;
            font-size: 0.9rem;
        }
        
        .activity-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .activity-info {
            font-size: 0.8rem;
            color: var(--text-muted);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            white-space: nowrap;
            margin-left: 8px;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            background: var(--bg-card);
        }
        
        .pagination-info {
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .pagination-controls {
            display: flex;
            gap: 8px;
        }
        
        .page-btn {
            padding: 8px 12px;
            border-radius: 6px;
            background: var(--bg-body);
            border: 1px solid var(--border);
            color: var(--text-main);
            font-size: 0.85rem;
            transition: all 0.2s ease;
        }
        
        .page-btn:hover {
            background: var(--bg-card);
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-btn.disabled:hover {
            background: var(--bg-body);
            border-color: var(--border);
            color: var(--text-main);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        
        .empty-title {
            font-size: 1.2rem;
            margin-bottom: 12px;
            color: var(--text-main);
        }
        
        .empty-description {
            margin-bottom: 24px;
            max-width: 400px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        
        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-body);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--color-success);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-success:hover {
            background: #0da271;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        
        .btn-danger {
            background: var(--color-danger);
            color: white;
            border: 1px solid transparent;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.2);
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.85rem;
        }
        
        .btn-icon {
            padding: 8px;
            width: 36px;
            height: 36px;
            justify-content: center;
        }

        /* Theme Toggle */
        #themeToggle {
            background: var(--bg-card);
            border: 1px solid var(--border);
            width: 42px;
            height: 42px;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .top-header {
                padding: 0 20px;
            }
            
            .scrollable {
                padding: 20px;
            }
            
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
            
            .profile-info { 
                display: none; 
            }
            
            .table-header {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .pagination {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
            
            .bulk-actions {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .bulk-controls {
                width: 100%;
            }
        }
        
        @media (max-width: 480px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .dashboard-card {
                padding: 16px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                justify-content: center;
            }
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: var(--bg-card);
            border-radius: var(--radius);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
        }
        
        .modal-close:hover {
            background: var(--bg-body);
            color: var(--text-main);
        }
        
        .modal-body {
            padding: 24px;
        }
        
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--border);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <div class="overlay" id="overlay"></div>

     <?php include 'sidenavbar.php'; ?>

    <main class="main-content">
        
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
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Work Order Payments</h1>
                    <p class="page-subtitle">
                        Record and manage payments to subcontractors for completed work orders. 
                        Track payment history and outstanding balances.
                    </p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="showRecordPaymentModal()">
                        <i class="ph ph-plus-circle"></i>
                        Record New Payment
                    </button>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $msg_type == 'success' ? 'success' : 'error'; ?>">
                    <i class="ph <?php echo $msg_type == 'success' ? 'ph-check-circle' : 'ph-warning-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card total">
                    <div class="stat-number"><?php echo formatCurrency($total_paid_amount); ?></div>
                    <div class="stat-label">
                        <i class="ph ph-currency-dollar"></i>
                        Total Paid
                    </div>
                </div>
                <div class="stat-card outstanding">
                    <div class="stat-number"><?php echo formatCurrency($outstanding_balance); ?></div>
                    <div class="stat-label">
                        <i class="ph ph-warning-circle"></i>
                        Outstanding Balance
                    </div>
                </div>
                <div class="stat-card paid">
                    <div class="stat-number"><?php echo $total_payments; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-check-circle"></i>
                        Total Payments
                    </div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-number"><?php echo $pending_payments; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-clock"></i>
                        Pending Payments
                    </div>
                </div>
                <div class="stat-card total">
                    <div class="stat-number"><?php echo $unpaid_work_orders; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-clipboard-text"></i>
                        Unpaid Work Orders
                    </div>
                </div>
                <div class="stat-card paid">
                    <div class="stat-number"><?php echo $partially_paid; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-percent"></i>
                        Partially Paid
                    </div>
                </div>
                <div class="stat-card paid">
                    <div class="stat-number"><?php echo $fully_paid; ?></div>
                    <div class="stat-label">
                        <i class="ph ph-check"></i>
                        Fully Paid
                    </div>
                </div>
                <div class="stat-card refunded">
                    <div class="stat-number"><?php echo formatCurrency($refunded_amount); ?></div>
                    <div class="stat-label">
                        <i class="ph ph-arrow-counter-clockwise"></i>
                        Refunded Amount
                    </div>
                </div>
            </div>

            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ph ph-trend-up" style="color: var(--primary); margin-right: 8px;"></i>
                            Payment Distribution
                        </div>
                        <div class="card-actions">
                            <button class="btn btn-sm btn-secondary" onclick="refreshPaymentChart()">
                                <i class="ph ph-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="chart-container">
                        <canvas id="paymentChart"></canvas>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="ph ph-clock" style="color: var(--primary); margin-right: 8px;"></i>
                            Recent Payment Activities
                        </div>
                        <div class="card-actions">
                            <a href="payment_activities.php" class="btn btn-sm btn-secondary">
                                <i class="ph ph-list"></i> View All
                            </a>
                        </div>
                    </div>
                    <div class="activity-list">
                        <?php if(empty($recent_activities)): ?>
                            <div style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                                <i class="ph ph-currency-dollar" style="font-size: 3rem; opacity: 0.3;"></i>
                                <p style="margin-top: 12px;">No recent payment activities</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($recent_activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="ph 
                                            <?php 
                                            switch($activity['action']) {
                                                case 'payment_recorded': echo 'ph-currency-dollar'; break;
                                                case 'payment_deleted': echo 'ph-trash'; break;
                                                case 'payment_refunded': echo 'ph-arrow-counter-clockwise'; break;
                                                default: echo 'ph-activity';
                                            }
                                            ?>
                                        "></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?php echo e($activity['description']); ?></div>
                                        <div class="activity-details">
                                            <div class="activity-info">
                                                <?php if(!empty($activity['work_order_number'])): ?>
                                                    WO#<?php echo e($activity['work_order_number']); ?>
                                                    <span style="margin: 0 8px;">•</span>
                                                <?php endif; ?>
                                                <?php echo e($activity['username']); ?>
                                            </div>
                                            <div class="activity-time">
                                                <?php 
                                                    $created = new DateTime($activity['created_at']);
                                                    $now = new DateTime();
                                                    $interval = $now->diff($created);
                                                    
                                                    if ($interval->d > 0) {
                                                        echo $interval->d . 'd ago';
                                                    } elseif ($interval->h > 0) {
                                                        echo $interval->h . 'h ago';
                                                    } else {
                                                        echo $interval->i . 'm ago';
                                                    }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Record Payment Form (Modal) -->
            <div class="modal" id="recordPaymentModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 class="modal-title">Record New Payment</h3>
                        <button class="modal-close" onclick="closeModal('recordPaymentModal')">
                            <i class="ph ph-x"></i>
                        </button>
                    </div>
                    <form method="POST" action="">
                        <div class="modal-body">
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="ph ph-clipboard-text"></i>
                                    Work Order Selection
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Select Work Order *</label>
                                    <select name="work_order_id" class="form-select" id="workOrderSelect" required onchange="loadWorkOrderDetails(this.value)">
                                        <option value="">-- Select a Work Order --</option>
                                        <?php foreach($work_orders_for_payment as $wo): ?>
                                            <option value="<?php echo $wo['id']; ?>" 
                                                    data-cost="<?php echo $wo['actual_cost'] ?? $wo['estimated_cost']; ?>"
                                                    data-paid="<?php echo $wo['total_paid'] ?? 0; ?>"
                                                    data-company="<?php echo e($wo['company_name']); ?>">
                                                <?php echo e($wo['work_order_number']); ?> - <?php echo e($wo['project_name']); ?> 
                                                (<?php echo formatCurrency($wo['remaining_balance']); ?> remaining)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div id="workOrderDetails" class="work-order-info" style="display: none;">
                                    <div class="wo-info-grid">
                                        <div class="wo-info-item">
                                            <div class="wo-info-label">Subcontractor</div>
                                            <div class="wo-info-value" id="detailCompany">-</div>
                                        </div>
                                        <div class="wo-info-item">
                                            <div class="wo-info-label">Total Cost</div>
                                            <div class="wo-info-value" id="detailTotalCost">-</div>
                                        </div>
                                        <div class="wo-info-item">
                                            <div class="wo-info-label">Already Paid</div>
                                            <div class="wo-info-value" id="detailPaid">-</div>
                                        </div>
                                        <div class="wo-info-item">
                                            <div class="wo-info-label">Payment Status</div>
                                            <div class="wo-info-value" id="detailStatus">-</div>
                                        </div>
                                        <div class="balance-display">
                                            <div class="balance-label">Remaining Balance</div>
                                            <div class="balance-amount" id="detailBalance">-</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="ph ph-currency-dollar"></i>
                                    Payment Details
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label class="form-label">Payment Amount *</label>
                                        <input type="number" name="payment_amount" class="form-input" 
                                               step="0.01" min="0.01" required placeholder="0.00">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Payment Date *</label>
                                        <input type="date" name="payment_date" class="form-input" 
                                               value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Payment Method *</label>
                                        <select name="payment_method" class="form-select" required>
                                            <option value="">-- Select Method --</option>
                                            <option value="Bank Transfer">Bank Transfer</option>
                                            <option value="Check">Check</option>
                                            <option value="Cash">Cash</option>
                                            <option value="Credit Card">Credit Card</option>
                                            <option value="Online Payment">Online Payment</option>
                                            <option value="Wire Transfer">Wire Transfer</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Payment Type</label>
                                        <select name="payment_type" class="form-select">
                                            <option value="partial">Partial Payment</option>
                                            <option value="final">Final Payment</option>
                                            <option value="advance">Advance Payment</option>
                                            <option value="milestone">Milestone Payment</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label class="form-label">Reference Number</label>
                                        <input type="text" name="reference_number" class="form-input" 
                                               placeholder="Check #, Transaction ID, etc.">
                                    </div>
                                    
                                    <div class="form-group" style="grid-column: span 2;">
                                        <label class="form-label">Payment Notes</label>
                                        <textarea name="payment_notes" class="form-textarea" 
                                                  placeholder="Any additional notes about this payment..."></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" onclick="closeModal('recordPaymentModal')">
                                Cancel
                            </button>
                            <button type="submit" name="record_payment" class="btn btn-primary">
                                <i class="ph ph-currency-dollar"></i>
                                Record Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filters-header">
                    <div class="filters-title">Filter & Search Payments</div>
                    <a href="work_order_payments.php" class="btn btn-secondary btn-sm">
                        <i class="ph ph-arrow-clockwise"></i>
                        Reset Filters
                    </a>
                </div>
                
                <form method="GET" action="" class="filters-grid">
                    <div class="filter-group">
                        <label class="filter-label">Search</label>
                        <input type="text" name="search" class="filter-input" 
                               placeholder="Search by reference, WO#, or company..."
                               value="<?php echo e($search); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Payment Status</label>
                        <select name="payment_status" class="filter-select">
                            <option value="all" <?php echo $payment_status_filter == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="completed" <?php echo $payment_status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="pending" <?php echo $payment_status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="refunded" <?php echo $payment_status_filter == 'refunded' ? 'selected' : ''; ?>>Refunded</option>
                            <option value="failed" <?php echo $payment_status_filter == 'failed' ? 'selected' : ''; ?>>Failed</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Payment Method</label>
                        <select name="payment_method" class="filter-select">
                            <option value="all" <?php echo $payment_method_filter == 'all' ? 'selected' : ''; ?>>All Methods</option>
                            <option value="Bank Transfer" <?php echo $payment_method_filter == 'Bank Transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="Check" <?php echo $payment_method_filter == 'Check' ? 'selected' : ''; ?>>Check</option>
                            <option value="Cash" <?php echo $payment_method_filter == 'Cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="Credit Card" <?php echo $payment_method_filter == 'Credit Card' ? 'selected' : ''; ?>>Credit Card</option>
                            <option value="Online Payment" <?php echo $payment_method_filter == 'Online Payment' ? 'selected' : ''; ?>>Online Payment</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Subcontractor</label>
                        <select name="subcontractor" class="filter-select">
                            <option value="all" <?php echo $subcontractor_filter == 'all' ? 'selected' : ''; ?>>All Subcontractors</option>
                            <?php foreach ($subcontractors as $sub): ?>
                                <option value="<?php echo $sub['id']; ?>" <?php echo $subcontractor_filter == $sub['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($sub['company_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Date From</label>
                        <input type="date" name="date_from" class="filter-input" value="<?php echo e($date_from); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Date To</label>
                        <input type="date" name="date_to" class="filter-input" value="<?php echo e($date_to); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">&nbsp;</label>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="ph ph-magnifying-glass"></i>
                                Apply Filters
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="printPayments()">
                                <i class="ph ph-printer"></i>
                                Print
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <div class="bulk-selection">
                    <input type="checkbox" id="selectAllBulk" onchange="toggleAllSelection()">
                    <span id="selectedCount">0 payments selected</span>
                </div>
                <form method="POST" action="" class="bulk-controls" id="bulkForm">
                    <input type="hidden" name="bulk_payment_action" id="bulkPaymentAction">
                    <button type="button" class="btn btn-secondary btn-sm" onclick="bulkExport()">
                        <i class="ph ph-export"></i> Export Selected
                    </button>
                    <button type="button" class="btn btn-danger btn-sm" onclick="bulkDelete()">
                        <i class="ph ph-trash"></i> Delete Selected
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                        <i class="ph ph-x"></i> Clear Selection
                    </button>
                </form>
            </div>

            <!-- Payments Table -->
            <div class="table-container">
                <div class="table-header">
                    <div class="table-title">
                        Payment Records
                        <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: normal; margin-left: 8px;">
                            (Showing <?php echo count($paged_payments); ?> of <?php echo count($payments); ?>)
                        </span>
                    </div>
                    <div class="table-actions">
                        <button class="btn btn-secondary btn-icon" title="Refresh" onclick="window.location.reload()">
                            <i class="ph ph-arrow-clockwise"></i>
                        </button>
                        <button class="btn btn-secondary btn-icon" title="Select All" onclick="selectAllRows()">
                            <i class="ph ph-check-square"></i>
                        </button>
                        <button class="btn btn-secondary btn-icon" title="Deselect All" onclick="deselectAllRows()">
                            <i class="ph ph-square"></i>
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="select-checkbox">
                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                </th>
                                <th>REFERENCE</th>
                                <th>WORK ORDER</th>
                                <th>SUBCONTRACTOR</th>
                                <th>DATE</th>
                                <th>AMOUNT</th>
                                <th>METHOD</th>
                                <th>STATUS</th>
                                <th>RECORDED BY</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody id="paymentsBody">
                            <?php if (empty($paged_payments)): ?>
                                <tr>
                                    <td colspan="10" class="empty-state">
                                        <i class="ph ph-currency-dollar empty-icon"></i>
                                        <div class="empty-title">No Payment Records Found</div>
                                        <div class="empty-description">
                                            <?php if ($search || $payment_status_filter != 'all' || $payment_method_filter != 'all' || $subcontractor_filter != 'all' || !empty($date_from) || !empty($date_to)): ?>
                                                Try adjusting your filters or search terms
                                            <?php else: ?>
                                                Start by recording your first payment
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!$search && $payment_status_filter == 'all' && $payment_method_filter == 'all' && $subcontractor_filter == 'all' && empty($date_from) && empty($date_to)): ?>
                                            <button class="btn btn-primary" onclick="showRecordPaymentModal()">
                                                <i class="ph ph-currency-dollar"></i>
                                                Record First Payment
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($paged_payments as $payment): ?>
                                    <tr class="payment-row" data-id="<?php echo $payment['id']; ?>">
                                        <td class="select-checkbox">
                                            <input type="checkbox" class="payment-checkbox" 
                                                   name="selected_payments[]" 
                                                   value="<?php echo $payment['id']; ?>"
                                                   onchange="updateBulkActions()">
                                        </td>
                                        <td>
                                            <div class="reference-number">
                                                <?php echo e($payment['reference_number'] ?: 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <div style="font-weight: 600;"><?php echo e($payment['work_order_number']); ?></div>
                                                <div style="font-size: 0.85rem; color: var(--text-muted);">
                                                    <?php echo e($payment['project_name']); ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo e($payment['company_name']); ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 500;"><?php echo formatDate($payment['payment_date']); ?></div>
                                        </td>
                                        <td>
                                            <div class="amount-cell amount-positive">
                                                <?php echo formatCurrency($payment['amount']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="payment-method-badge method-<?php echo strtolower(str_replace(' ', '-', $payment['payment_method'])); ?>">
                                                <i class="ph 
                                                    <?php 
                                                    switch($payment['payment_method']) {
                                                        case 'Bank Transfer': echo 'ph-bank'; break;
                                                        case 'Check': echo 'ph-note'; break;
                                                        case 'Cash': echo 'ph-money'; break;
                                                        case 'Credit Card': echo 'ph-credit-card'; break;
                                                        case 'Online Payment': echo 'ph-globe'; break;
                                                        default: echo 'ph-currency-dollar';
                                                    }
                                                    ?>
                                                "></i>
                                                <?php echo $payment['payment_method']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="payment-status-badge payment-status-<?php echo $payment['status']; ?>">
                                                <i class="ph ph-circle-fill" style="font-size: 6px;"></i>
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 0.85rem;">
                                                <?php echo e($payment['recorded_by'] ?? 'System'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn view" onclick="viewPaymentDetails(<?php echo $payment['id']; ?>)">
                                                    <i class="ph ph-eye"></i> View
                                                </button>
                                                <button class="action-btn receipt" onclick="generateReceipt(<?php echo $payment['id']; ?>)">
                                                    <i class="ph ph-receipt"></i> Receipt
                                                </button>
                                                <?php if($payment['status'] == 'completed'): ?>
                                                    <button class="action-btn refund" onclick="refundPayment(<?php echo $payment['id']; ?>)">
                                                        <i class="ph ph-arrow-counter-clockwise"></i> Refund
                                                    </button>
                                                <?php endif; ?>
                                                <button class="action-btn delete" onclick="deletePayment(<?php echo $payment['id']; ?>)">
                                                    <i class="ph ph-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <div class="pagination-info">
                            Page <?php echo $page; ?> of <?php echo $total_pages; ?> • 
                            <?php echo count($payments); ?> total payments
                        </div>
                        <div class="pagination-controls">
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" 
                               class="page-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                <i class="ph ph-caret-double-left"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>" 
                               class="page-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
                                <i class="ph ph-caret-left"></i>
                            </a>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="page-btn <?php echo $i == $page ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                            
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($total_pages, $page + 1)])); ?>" 
                               class="page-btn <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <i class="ph ph-caret-right"></i>
                            </a>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $total_pages])); ?>" 
                               class="page-btn <?php echo $page == $total_pages ? 'disabled' : ''; ?>">
                                <i class="ph ph-caret-double-right"></i>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Deletion</h3>
                <button class="modal-close" onclick="closeModal('deleteModal')">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px 0;">
                    <i class="ph ph-warning-circle" style="font-size: 4rem; color: var(--color-danger); margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">Are you sure?</h3>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">
                        This action cannot be undone. This payment record will be permanently deleted.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteModal')">
                    Cancel
                </button>
                <a href="#" id="confirmDelete" class="btn btn-danger">
                    <i class="ph ph-trash"></i>
                    Delete Payment
                </a>
            </div>
        </div>
    </div>

    <!-- Refund Confirmation Modal -->
    <div class="modal" id="refundModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Refund</h3>
                <button class="modal-close" onclick="closeModal('refundModal')">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px 0;">
                    <i class="ph ph-arrow-counter-clockwise" style="font-size: 4rem; color: var(--color-warning); margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">Mark Payment as Refunded?</h3>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">
                        This will mark the payment as refunded. The work order's payment status will be recalculated.
                    </p>
                    <p style="color: var(--color-warning); font-weight: 500;">
                        Note: This does not process an actual refund. It only updates the payment status.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('refundModal')">
                    Cancel
                </button>
                <a href="#" id="confirmRefund" class="btn btn-warning">
                    <i class="ph ph-arrow-counter-clockwise"></i>
                    Mark as Refunded
                </a>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Modal -->
    <div class="modal" id="bulkDeleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Bulk Deletion</h3>
                <button class="modal-close" onclick="closeModal('bulkDeleteModal')">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            <div class="modal-body">
                <div style="text-align: center; padding: 20px 0;">
                    <i class="ph ph-warning-circle" style="font-size: 4rem; color: var(--color-danger); margin-bottom: 20px;"></i>
                    <h3 style="margin-bottom: 10px;">Delete <span id="bulkDeleteCount">0</span> Payments?</h3>
                    <p style="color: var(--text-muted); margin-bottom: 20px;">
                        This action cannot be undone. All selected payment records will be permanently deleted.
                    </p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('bulkDeleteModal')">
                    Cancel
                </button>
                <button class="btn btn-danger" onclick="confirmBulkDelete()">
                    <i class="ph ph-trash"></i>
                    Delete All Selected
                </button>
            </div>
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
        });

        // --- 2. Accordion Logic ---
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            const link = item.querySelector('.menu-link');
            if (link && !link.querySelector('a')) {
                link.addEventListener('click', (e) => {
                    if (!link.hasAttribute('href')) {
                        if(body.classList.contains('sidebar-collapsed')) {
                            body.classList.remove('sidebar-collapsed');
                            setTimeout(() => { item.classList.add('open'); }, 100);
                            return;
                        }
                        
                        e.preventDefault();
                        const isOpen = item.classList.contains('open');
                        menuItems.forEach(i => i.classList.remove('open')); 
                        if (!isOpen) item.classList.add('open');
                    }
                });
            }
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

        // --- 5. Modal Functions ---
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function showRecordPaymentModal() {
            openModal('recordPaymentModal');
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeModal(this.id);
                }
            });
        });

        // --- 6. Chart.js Implementation ---
        let paymentChart = null;

        function initPaymentChart() {
            const ctx = document.getElementById('paymentChart').getContext('2d');
            
            // Sample data - in real app, fetch from server
            const paymentData = {
                'Bank Transfer': 45,
                'Check': 25,
                'Cash': 15,
                'Credit Card': 10,
                'Online Payment': 5
            };
            
            // Create chart
            paymentChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: Object.keys(paymentData),
                    datasets: [{
                        data: Object.values(paymentData),
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',   // Bank Transfer
                            'rgba(139, 92, 246, 0.8)',   // Check
                            'rgba(16, 185, 129, 0.8)',   // Cash
                            'rgba(245, 158, 11, 0.8)',   // Credit Card
                            'rgba(239, 68, 68, 0.8)'     // Online Payment
                        ],
                        borderColor: [
                            'rgb(59, 130, 246)',
                            'rgb(139, 92, 246)',
                            'rgb(16, 185, 129)',
                            'rgb(245, 158, 11)',
                            'rgb(239, 68, 68)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 20,
                                usePointStyle: true,
                                pointStyle: 'circle'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    label += context.parsed + '%';
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        function refreshPaymentChart() {
            if (paymentChart) {
                paymentChart.destroy();
            }
            initPaymentChart();
        }

        // --- 7. Work Order Details Loader ---
        function loadWorkOrderDetails(workOrderId) {
            const select = document.getElementById('workOrderSelect');
            const selectedOption = select.options[select.selectedIndex];
            const detailsDiv = document.getElementById('workOrderDetails');
            
            if (!workOrderId) {
                detailsDiv.style.display = 'none';
                return;
            }
            
            const totalCost = parseFloat(selectedOption.getAttribute('data-cost')) || 0;
            const totalPaid = parseFloat(selectedOption.getAttribute('data-paid')) || 0;
            const companyName = selectedOption.getAttribute('data-company') || 'N/A';
            const remainingBalance = totalCost - totalPaid;
            
            // Determine payment status
            let paymentStatus = 'Unpaid';
            if (totalPaid > 0) {
                if (Math.abs(totalPaid - totalCost) < 0.01) {
                    paymentStatus = 'Fully Paid';
                } else if (totalPaid > totalCost) {
                    paymentStatus = 'Overpaid';
                } else {
                    paymentStatus = 'Partially Paid';
                }
            }
            
            // Update UI
            document.getElementById('detailCompany').textContent = companyName;
            document.getElementById('detailTotalCost').textContent = formatCurrency(totalCost);
            document.getElementById('detailPaid').textContent = formatCurrency(totalPaid);
            document.getElementById('detailStatus').textContent = paymentStatus;
            document.getElementById('detailBalance').textContent = formatCurrency(remainingBalance);
            
            // Style balance
            const balanceElement = document.getElementById('detailBalance');
            balanceElement.className = 'balance-amount ';
            if (remainingBalance > 0) {
                balanceElement.classList.add('balance-positive');
            } else if (remainingBalance < 0) {
                balanceElement.classList.add('balance-negative');
            } else {
                balanceElement.classList.add('balance-zero');
            }
            
            // Set max payment amount
            const paymentAmountInput = document.querySelector('input[name="payment_amount"]');
            if (paymentAmountInput) {
                paymentAmountInput.max = remainingBalance;
                paymentAmountInput.placeholder = 'Max: ' + formatCurrency(remainingBalance);
            }
            
            detailsDiv.style.display = 'block';
        }

        // --- 8. Utility Functions ---
        function formatCurrency(amount) {
            return '$' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
        }

        // --- 9. Bulk Actions ---
        let selectedPayments = new Set();

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.payment-checkbox:checked');
            selectedPayments.clear();
            
            checkboxes.forEach(cb => {
                selectedPayments.add(cb.value);
            });
            
            const selectedCount = selectedPayments.size;
            const bulkActions = document.getElementById('bulkActions');
            const selectedCountSpan = document.getElementById('selectedCount');
            
            if (selectedCount > 0) {
                bulkActions.classList.add('show');
                selectedCountSpan.textContent = selectedCount + ' payment(s) selected';
            } else {
                bulkActions.classList.remove('show');
                selectedCountSpan.textContent = '0 payments selected';
            }
            
            // Update select all checkbox
            const totalCheckboxes = document.querySelectorAll('.payment-checkbox').length;
            const selectAll = document.getElementById('selectAll');
            const selectAllBulk = document.getElementById('selectAllBulk');
            
            if (selectedCount === totalCheckboxes && totalCheckboxes > 0) {
                selectAll.checked = true;
                selectAllBulk.checked = true;
            } else {
                selectAll.checked = false;
                selectAllBulk.checked = false;
            }
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll').checked;
            const checkboxes = document.querySelectorAll('.payment-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll;
            });
            
            updateBulkActions();
        }

        function toggleAllSelection() {
            const selectAllBulk = document.getElementById('selectAllBulk').checked;
            const checkboxes = document.querySelectorAll('.payment-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAllBulk;
            });
            
            updateBulkActions();
        }

        function selectAllRows() {
            const checkboxes = document.querySelectorAll('.payment-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            updateBulkActions();
        }

        function deselectAllRows() {
            const checkboxes = document.querySelectorAll('.payment-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            updateBulkActions();
        }

        function clearSelection() {
            deselectAllRows();
        }

        function bulkExport() {
            if (selectedPayments.size === 0) {
                alert('Please select at least one payment.');
                return;
            }
            
            document.getElementById('bulkPaymentAction').value = 'export';
            document.getElementById('bulkForm').submit();
        }

        function bulkDelete() {
            if (selectedPayments.size === 0) {
                alert('Please select at least one payment.');
                return;
            }
            
            document.getElementById('bulkDeleteCount').textContent = selectedPayments.size;
            openModal('bulkDeleteModal');
        }

        function confirmBulkDelete() {
            document.getElementById('bulkPaymentAction').value = 'delete';
            document.getElementById('bulkForm').submit();
        }

        // --- 10. Payment Actions ---
        function viewPaymentDetails(paymentId) {
            // In a real application, this would show detailed payment information
            alert('Viewing payment details for ID: ' + paymentId + '\n\nThis would show a detailed view with all payment information.');
        }

        function generateReceipt(paymentId) {
            window.open('generate_receipt.php?id=' + paymentId, '_blank');
        }

        function refundPayment(paymentId) {
            document.getElementById('confirmRefund').href = '?refund_payment=' + paymentId;
            openModal('refundModal');
        }

        function deletePayment(paymentId) {
            document.getElementById('confirmDelete').href = '?delete_payment=' + paymentId;
            openModal('deleteModal');
        }

        // --- 11. Export Functions ---
        function printPayments() {
            const printWindow = window.open('', '_blank');
            const tableContent = document.querySelector('.table-container').outerHTML;
            
            printWindow.document.write(`
                <html>
                <head>
                    <title>Payment Records Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                        th { background-color: #f5f5f5; font-weight: bold; }
                        .print-header { text-align: center; margin-bottom: 30px; }
                        .print-footer { margin-top: 30px; text-align: center; color: #666; }
                        .payment-status { padding: 4px 8px; border-radius: 12px; font-size: 12px; font-weight: bold; }
                        .status-completed { background: #d1fae5; color: #10b981; }
                        .status-pending { background: #fef3c7; color: #f59e0b; }
                        .status-refunded { background: #f3f4f6; color: #6b7280; }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h1>Payment Records Report</h1>
                        <p>Generated on ${new Date().toLocaleDateString()} at ${new Date().toLocaleTimeString()}</p>
                        <p>Total: <?php echo count($payments); ?> payment records</p>
                    </div>
                    ${tableContent}
                    <div class="print-footer">
                        <p>© ${new Date().getFullYear()} NexusAdmin - Payment Management System</p>
                    </div>
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.focus();
            setTimeout(() => {
                printWindow.print();
            }, 500);
        }

        // --- 12. Form Validation ---
        function validatePaymentForm() {
            const workOrderId = document.getElementById('workOrderSelect').value;
            const paymentAmount = document.querySelector('input[name="payment_amount"]').value;
            const paymentMethod = document.querySelector('select[name="payment_method"]').value;
            
            if (!workOrderId) {
                alert('Please select a work order.');
                return false;
            }
            
            if (!paymentAmount || parseFloat(paymentAmount) <= 0) {
                alert('Please enter a valid payment amount.');
                return false;
            }
            
            if (!paymentMethod) {
                alert('Please select a payment method.');
                return false;
            }
            
            return true;
        }

        // --- 13. Auto-refresh functionality ---
        let autoRefreshInterval = null;

        function startAutoRefresh(seconds = 300) {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
            
            autoRefreshInterval = setInterval(() => {
                console.log('Auto-refreshing payments...');
                // You could add AJAX refresh here instead of full page reload
                // refreshPayments();
            }, seconds * 1000);
        }

        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
                autoRefreshInterval = null;
            }
        }

        // --- 14. Keyboard Shortcuts ---
        document.addEventListener('keydown', function(e) {
            // Ctrl + F for search focus
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                document.querySelector('input[name="search"]').focus();
            }
            
            // Ctrl + N for new payment
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                showRecordPaymentModal();
            }
            
            // Escape to clear selection
            if (e.key === 'Escape') {
                clearSelection();
            }
            
            // Delete key for bulk delete
            if (e.key === 'Delete' && selectedPayments.size > 0) {
                e.preventDefault();
                bulkDelete();
            }
        });

        // --- 15. Initialize on load ---
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize chart
            if (typeof Chart !== 'undefined') {
                initPaymentChart();
            }
            
            // Initialize date pickers
            if (typeof flatpickr !== 'undefined') {
                flatpickr('.filter-input[type="date"]', {
                    dateFormat: "Y-m-d",
                    allowInput: true
                });
                
                flatpickr('.form-input[type="date"]', {
                    dateFormat: "Y-m-d",
                    allowInput: true
                });
            }
            
            // Start auto-refresh (optional)
            // startAutoRefresh(300); // Refresh every 5 minutes
            
            // Add row hover effects
            const rows = document.querySelectorAll('.payment-row');
            rows.forEach(row => {
                row.addEventListener('click', function(e) {
                    if (e.target.type !== 'checkbox' && !e.target.closest('.action-btn')) {
                        const id = this.dataset.id;
                        viewPaymentDetails(id);
                    }
                });
            });
            
            // Initialize tooltips
            initTooltips();
            
            // Check for URL parameters to open modals
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('record_payment')) {
                showRecordPaymentModal();
            }
        });

        // --- 16. Tooltips ---
        function initTooltips() {
            // Add title attributes for tooltips
            const tooltipElements = document.querySelectorAll('[title]');
            tooltipElements.forEach(el => {
                if (!el.getAttribute('data-tooltip-initialized')) {
                    el.setAttribute('data-tooltip-initialized', 'true');
                }
            });
        }

        // --- 17. Loading Overlay ---
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // Show loading for page transitions
        document.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('javascript:')) {
                    showLoading();
                }
            });
        });

        // --- 18. Real-time Updates ---
        function checkForUpdates() {
            // This would typically use WebSockets or AJAX polling
            // For now, just log to console
            console.log('Checking for payment updates...');
        }

        // Check for updates every 30 seconds
        setInterval(checkForUpdates, 30000);
    </script>
</body>
</html>