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
if ($id <= 0) { $_SESSION['flash_error']='Invalid id'; header('Location: orders.php'); exit; }

$DB_HOST='localhost'; $DB_USER='alihairw'; $DB_PASS='x5.H(8xkh3H7EY'; $DB_NAME='alihairw_alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { $_SESSION['flash_error']='DB connect failed'; header('Location: orders.php'); exit; }
$stmt = $mysqli->prepare("DELETE FROM `orders` WHERE `id` = ? LIMIT 1");
if (!$stmt) { $_SESSION['flash_error']='Prepare failed'; header('Location: orders.php'); exit; }
$stmt->bind_param('i',$id); $ok = $stmt->execute(); $stmt->close();
$_SESSION['flash_success'] = $ok ? 'Order deleted' : 'Failed to delete';
header('Location: orders.php'); exit;
