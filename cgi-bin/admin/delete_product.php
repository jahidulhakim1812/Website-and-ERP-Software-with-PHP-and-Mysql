<?php
declare(strict_types=1);
session_start();
ini_set('display_errors','1');
ini_set('display_startup_errors','1');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Dhaka');

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
function flash(string $k, string $m) { $_SESSION[$k] = $m; }

// CSRF + id
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit;
}

// accept either GET?id=... (link) or POST with review_id (form)
$id = 0;
if (isset($_REQUEST['id'])) $id = (int)$_REQUEST['id'];
if ($id <= 0) {
    flash('flash_error','Invalid product id.');
    header('Location: products_manage.php'); exit;
}

// When using a GET link we still want confirmation; the front-end already confirmed with JS.
// Use POST to require CSRF if you want to be stricter. Here we accept GET but prefer POST with csrf.
$requireCsrf = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($requireCsrf) {
    if (empty($_POST['csrf_token']) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$_POST['csrf_token'])) {
        flash('flash_error','Invalid request token.');
        header('Location: products_manage.php'); exit;
    }
}

// DB
$DB_HOST='127.0.0.1'; $DB_USER='root'; $DB_PASS=''; $DB_NAME='alihairwigs';
$mysqli = new mysqli($DB_HOST,$DB_USER,$DB_PASS,$DB_NAME);
if ($mysqli->connect_error) { flash('flash_error','DB connection error.'); header('Location: products_manage.php'); exit; }
$mysqli->set_charset('utf8mb4');

// find product and stored image path
$stmt = $mysqli->prepare("SELECT id, image_placeholder FROM products WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    flash('flash_error','Product not found.');
    header('Location: products_manage.php'); exit;
}

// attempt delete record
$del = $mysqli->prepare("DELETE FROM products WHERE id = ? LIMIT 1");
if (!$del) { flash('flash_error','Prepare failed.'); header('Location: products_manage.php'); exit; }
$del->bind_param('i', $id);
$ok = $del->execute();
$del->close();

if ($ok) {
    // Optionally attempt to remove the image file if it is a local path
    $img = trim((string)$row['image_placeholder']);
    if ($img !== '') {
        // map common public directories to filesystem path
        $candidates = [
            __DIR__ . '/' . ltrim($img, "/\\"),
            __DIR__ . '/admin/images/' . basename($img),
            __DIR__ . '/images/' . basename($img),
            __DIR__ . '/uploads/' . basename($img)
        ];
        foreach ($candidates as $p) {
            if (file_exists($p) && is_file($p)) {
                // unlink but ignore errors
                @unlink($p);
                break;
            }
        }
    }

    flash('flash_success','Product deleted successfully.');
} else {
    flash('flash_error','Failed to delete product.');
}

header('Location: products_manage.php');
exit;
