<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['email_auth'])) {
    die("Unauthorized access.");
}

$email = $_SESSION['email_auth'];

// Fetch user
$stmt = $conn->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Log the user in
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['email'] = $user['email'];

unset($_SESSION['email_auth']); // Clear temporary session
?>
<!DOCTYPE html>
<html>
<head>
  <title>Login Successful</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-green-100">

<div class="w-full max-w-md bg-white p-8 rounded-xl shadow text-center">
  <h2 class="text-2xl font-bold text-green-700">Verification Successful</h2>
  <p class="mt-2 text-gray-600">You are being logged in automatically...</p>

  <script>
    setTimeout(() => {
      window.location.href = "admin/admin_dashboard.php";
    }, 1200);
  </script>
</div>

</body>
</html>
