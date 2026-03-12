<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Dhaka');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

function redirect_with_flash(string $key, string $msg, string $loc = 'reviews.php') {
    $_SESSION[$key] = $msg;
    header('Location: ' . $loc);
    exit;
}

// CSRF
if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
    redirect_with_flash('flash_error', 'Invalid request token.');
}

if (!isset($_POST['review_id'])) {
    redirect_with_flash('flash_error', 'Missing review id.');
}

$review_id = (int)$_POST['review_id'];
if ($review_id <= 0) {
    redirect_with_flash('flash_error', 'Invalid review id.');
}

// DB
$DB_HOST='localhost'; $DB_USER='alihairw'; $DB_PASS='x5.H(8xkh3H7EY'; $DB_NAME='alihairw_alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { redirect_with_flash('flash_error', 'DB connection error.'); }
$mysqli->set_charset('utf8mb4');

$tbl = 'reviews';
// try to detect id column name (if your schema uses different name); fallback to 'id'
$resCols = $mysqli->query("SHOW COLUMNS FROM `{$mysqli->real_escape_string($tbl)}`");
$idCol = 'id';
if ($resCols) {
    while ($c = $resCols->fetch_assoc()) {
        if (in_array($c['Field'], ['id','review_id','rid'], true)) { $idCol = $c['Field']; break; }
    }
    $resCols->free();
}

// Optional: check review exists (to show more accurate message)
$stmt = $mysqli->prepare("SELECT COUNT(1) AS c FROM `{$mysqli->real_escape_string($tbl)}` WHERE `{$mysqli->real_escape_string($idCol)}` = ? LIMIT 1");
if (!$stmt) redirect_with_flash('flash_error', 'Prepare failed.');
$stmt->bind_param('i', $review_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (empty($r['c'])) {
    redirect_with_flash('flash_error', 'Review not found or already deleted.');
}

// perform delete
$del = $mysqli->prepare("DELETE FROM `{$mysqli->real_escape_string($tbl)}` WHERE `{$mysqli->real_escape_string($idCol)}` = ? LIMIT 1");
if (!$del) redirect_with_flash('flash_error', 'Delete prepare failed.');
$del->bind_param('i', $review_id);
$ok = $del->execute();
$del->close();

if ($ok) {
    redirect_with_flash('flash_success', 'Review deleted successfully.');
} else {
    redirect_with_flash('flash_error', 'Failed to delete review. Try again.');
}
