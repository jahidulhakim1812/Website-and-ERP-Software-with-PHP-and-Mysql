<?php
declare(strict_types=1);
session_start();
date_default_timezone_set('Asia/Dhaka');
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: orders.php'); exit; }
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
  $_SESSION['flash_error'] = 'Invalid CSRF'; header('Location: orders.php'); exit;
}
$id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($id <= 0) { $_SESSION['flash_error']='Invalid order id'; header('Location: orders.php'); exit; }

$DB_HOST='127.0.0.1'; $DB_USER='root'; $DB_PASS=''; $DB_NAME='alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { $_SESSION['flash_error']='DB connect failed'; header('Location: orders.php'); exit; }
$mysqli->set_charset('utf8mb4');

$statusCandidates = ['order_status','status','state']; $statusCol = null;
$res = $mysqli->query("SHOW COLUMNS FROM `orders`");
if ($res) { while ($r = $res->fetch_assoc()) { if (in_array($r['Field'],$statusCandidates,true)) { $statusCol = $r['Field']; break; } } $res->free(); }
if ($statusCol === null) { $_SESSION['flash_error']='No status column'; header('Location: orders.php'); exit; }

$statusUpdatedAtExists = false; $res2 = $mysqli->query("SHOW COLUMNS FROM `orders`");
if ($res2) { while ($r = $res2->fetch_assoc()) { if ($r['Field']==='status_updated_at') { $statusUpdatedAtExists = true; break; } } $res2->free(); }

$col = $mysqli->real_escape_string($statusCol);
if ($statusUpdatedAtExists) $sql = "UPDATE `orders` SET `{$col}` = 'complete', `status_updated_at` = NOW() WHERE `id` = ? LIMIT 1";
else $sql = "UPDATE `orders` SET `{$col}` = 'complete' WHERE `id` = ? LIMIT 1";

$stmt = $mysqli->prepare($sql);
if (!$stmt) { $_SESSION['flash_error']='Prepare failed'; header('Location: orders.php'); exit; }
$stmt->bind_param('i',$id); $ok = $stmt->execute(); $stmt->close();
$_SESSION['flash_success'] = $ok ? 'Order marked complete' : 'Failed to update';
header('Location: orders.php'); exit;
