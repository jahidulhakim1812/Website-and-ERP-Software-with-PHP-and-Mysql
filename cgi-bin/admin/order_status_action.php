<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors','1'); ini_set('display_startup_errors','1'); error_reporting(E_ALL);
date_default_timezone_set('Asia/Dhaka');

if (!isset($_SESSION['user_id'])) { http_response_code(401); echo json_encode(['ok'=>false,'msg'=>'Unauthenticated']); exit; }
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], (string)$_POST['csrf_token'])) { http_response_code(403); echo json_encode(['ok'=>false,'msg'=>'Invalid CSRF']); exit; }

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($orderId <= 0) { echo json_encode(['ok'=>false,'msg'=>'Invalid order id']); exit; }
$posted = isset($_POST['status']) ? strtolower(trim((string)$_POST['status'])) : 'complete';
$newStatus = in_array($posted, ['complete','completed','done'], true) ? 'complete' : preg_replace('/[^a-z0-9_\- ]/','',$posted) ?: 'complete';

$DB_HOST='127.0.0.1'; $DB_USER='root'; $DB_PASS=''; $DB_NAME='alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { http_response_code(500); echo json_encode(['ok'=>false,'msg'=>'DB connect failed']); exit; }
$mysqli->set_charset('utf8mb4');

// detect status and amount columns
$statusCandidates = ['order_status','status','state'];
$statusCol = null;
$res = $mysqli->query("SHOW COLUMNS FROM `orders`");
if ($res) { while ($r = $res->fetch_assoc()) { if (in_array($r['Field'],$statusCandidates,true)) { $statusCol = $r['Field']; break; } } $res->free(); }
if ($statusCol === null) { echo json_encode(['ok'=>false,'msg'=>'No status column']); exit; }

$statusUpdatedAtExists = false;
$res2 = $mysqli->query("SHOW COLUMNS FROM `orders`");
if ($res2) { while ($r = $res2->fetch_assoc()) { if ($r['Field']==='status_updated_at') { $statusUpdatedAtExists = true; break; } } $res2->free(); }

$colEsc = $mysqli->real_escape_string($statusCol);
if ($statusUpdatedAtExists) $sql = "UPDATE `orders` SET `{$colEsc}` = ?, `status_updated_at` = NOW() WHERE `id` = ? LIMIT 1";
else $sql = "UPDATE `orders` SET `{$colEsc}` = ? WHERE `id` = ? LIMIT 1";

$stmt = $mysqli->prepare($sql);
if (!$stmt) { echo json_encode(['ok'=>false,'msg'=>'Prepare failed']); exit; }
$stmt->bind_param('si', $newStatus, $orderId);
$execOk = $stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();
if (!$execOk) { echo json_encode(['ok'=>false,'msg'=>'Execute failed']); exit; }

// recompute KPIs
$amountCandidates = ['total_amount','total_usd','grand_total','amount','total'];
$amountCol = null;
$res3 = $mysqli->query("SHOW COLUMNS FROM `orders`");
if ($res3) { while ($r = $res3->fetch_assoc()) { if (in_array($r['Field'],$amountCandidates,true)) { $amountCol = $r['Field']; break; } } $res3->free(); }

$completedStatuses = ['complete','completed','done','shipped','delivered'];
$pendingStatuses = ['pending','new','processing','awaiting','hold','created'];
$kpi = ['total_income_confirmed'=>0.0,'pending_income'=>0.0,'completed_orders'=>0,'pending_orders'=>0];

if ($amountCol !== null) {
  $a = $mysqli->real_escape_string($amountCol);
  $s = $mysqli->real_escape_string($statusCol);
  $completed_list = implode("','", $completedStatuses);
  $pending_list = implode("','", $pendingStatuses);
  $row = $mysqli->query("SELECT IFNULL(SUM(`{$a}`),0) FROM `orders` WHERE LOWER(`{$s}`) IN ('{$completed_list}')")->fetch_row();
  $kpi['total_income_confirmed'] = (float)($row[0] ?? 0.0);
  $row = $mysqli->query("SELECT IFNULL(SUM(`{$a}`),0) FROM `orders` WHERE LOWER(`{$s}`) IN ('{$pending_list}')")->fetch_row();
  $kpi['pending_income'] = (float)($row[0] ?? 0.0);
  $row = $mysqli->query("SELECT COUNT(*) FROM `orders` WHERE LOWER(`{$s}`) IN ('{$completed_list}')")->fetch_row();
  $kpi['completed_orders'] = (int)($row[0] ?? 0);
  $row = $mysqli->query("SELECT COUNT(*) FROM `orders` WHERE LOWER(`{$s}`) IN ('{$pending_list}')")->fetch_row();
  $kpi['pending_orders'] = (int)($row[0] ?? 0);
}

echo json_encode(['ok'=>true,'msg'=>'Order updated','order_id'=>$orderId,'written_status'=>$newStatus,'affected_rows'=>$affected,'kpi'=>$kpi], JSON_UNESCAPED_UNICODE);
exit;
