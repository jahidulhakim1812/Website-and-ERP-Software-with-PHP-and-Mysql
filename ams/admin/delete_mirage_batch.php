<?php
/**
 * delete_mirage_batch.php
 * Deletes a batch and all related items, costs, and payments.
 * Redirects back to the due payments page with the same subcontractor selected.
 */

session_start();

// --- Authentication ---
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// --- Database connection ---
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

// --- Get POST data ---
$batch_id = isset($_POST['batch_id']) ? (int)$_POST['batch_id'] : 0;
$subcontractor_id = isset($_POST['subcontractor_id']) ? (int)$_POST['subcontractor_id'] : 0;

if ($batch_id <= 0) {
    die("Invalid batch ID.");
}

// Optional: verify that the batch belongs to the given subcontractor (security)
if ($subcontractor_id > 0) {
    $stmt = $pdo->prepare("SELECT id FROM wigs_batches WHERE id = ? AND subcontractor_id = ?");
    $stmt->execute([$batch_id, $subcontractor_id]);
    if (!$stmt->fetch()) {
        die("Batch does not belong to the specified subcontractor.");
    }
}

// --- Delete the batch (cascade will remove items, costs, payments) ---
try {
    $stmt = $pdo->prepare("DELETE FROM wigs_batches WHERE id = ?");
    $stmt->execute([$batch_id]);
} catch (Exception $e) {
    die("Error deleting batch: " . $e->getMessage());
}

// --- Redirect back to the due payments page with the same subcontractor ---
if ($subcontractor_id > 0) {
    header("Location: mirage_due_payments.php?subcontractor_id=" . $subcontractor_id);
} else {
    header("Location: mirage_due_payments.php");
}
exit;