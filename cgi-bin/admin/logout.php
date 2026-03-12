<?php
// logout.php
declare(strict_types=1);
session_start();

// Clear session data
$_SESSION = [];

// Remove session cookie if present
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Ensure fresh headers and no accidental relative redirect behavior
// Build an absolute URL to /alihairwigs/index.php using current host and scheme
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (!empty($_SERVER['SERVER_PORT']) && (string)$_SERVER['SERVER_PORT'] === '443') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$target = $scheme . '://' . $host . '/index.php';

// Redirect to absolute path
header('Location: ' . $target, true, 302);
exit;