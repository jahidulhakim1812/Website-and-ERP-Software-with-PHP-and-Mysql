<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);
date_default_timezone_set('Asia/Dhaka');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: orders.php'); exit; }
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
  $_SESSION['flash_error'] = 'Invalid CSRF token'; header('Location: orders.php'); exit;
}

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$status = isset($_POST['order_status']) ? strtolower(trim((string)$_POST['order_status'])) : '';
$allowed = ['pending','complete'];
if ($orderId <= 0 || !in_array($status, $allowed, true)) {
  $_SESSION['flash_error'] = 'Invalid input'; header('Location: orders.php'); exit;
}

$DB_HOST='localhost'; $DB_USER='alihairw'; $DB_PASS='x5.H(8xkh3H7EY'; $DB_NAME='alihairw_alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { $_SESSION['flash_error']='DB connect failed'; header('Location: orders.php'); exit; }
$mysqli->set_charset('utf8mb4');

// detect status column
$statusCandidates = ['order_status','status','state'];
$statusCol = null;
$res = $mysqli->query("SHOW COLUMNS FROM `orders`");
if ($res) {
  while ($r = $res->fetch_assoc()) { if (in_array($r['Field'],$statusCandidates,true)) { $statusCol = $r['Field']; break; } }
  $res->free();
}
if ($statusCol === null) { $_SESSION['flash_error']='No status column'; header('Location: orders.php'); exit; }

// check status_updated_at
$statusUpdatedAtExists = false;
$res2 = $mysqli->query("SHOW COLUMNS FROM `orders`");
if ($res2) {
  while ($r = $res2->fetch_assoc()) { if ($r['Field'] === 'status_updated_at') { $statusUpdatedAtExists = true; break; } }
  $res2->free();
}

$colEsc = $mysqli->real_escape_string($statusCol);
if ($statusUpdatedAtExists) {
  $sql = "UPDATE `orders` SET `{$colEsc}` = ?, `status_updated_at` = NOW() WHERE `id` = ? LIMIT 1";
} else {
  $sql = "UPDATE `orders` SET `{$colEsc}` = ? WHERE `id` = ? LIMIT 1";
}
$stmt = $mysqli->prepare($sql);
if (!$stmt) { $_SESSION['flash_error'] = 'Prepare failed'; header('Location: orders.php'); exit; }
$stmt->bind_param('si', $status, $orderId);
$ok = $stmt->execute();
$stmt->close();
if ($ok) { $_SESSION['flash_success'] = 'Order status updated'; } else { $_SESSION['flash_error'] = 'Failed to update'; }
header('Location: orders.php'); exit;
