<?php
session_start();

// --- DATABASE CONNECTION ---
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'alihairw');
define('DB_PASSWORD', 'x5.H(8xkh3H7EY');
define('DB_NAME', 'alihairw_alihairwigs');

// Create connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = '';

if (isset($_POST['send_code'])) {
    $email = trim($_POST['email']);

    // Check if the email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows == 0) {
        $message = "❌ This email is not registered.";
    } else {
        // Generate 6-digit code
        $code = rand(100000, 999999);

        // Set expiry 10 minutes
        $expiry = date("Y-m-d H:i:s", time() + 600);

        // Insert code into table
        $stmt2 = $conn->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?,?,?)");
        $stmt2->bind_param("sss", $email, $code, $expiry);
        $stmt2->execute();

        // Prepare email
        $subject = "Your Ali Hair Wigs Password Reset Code";
        $body = "Your password reset code is: $code\n\nThis code will expire in 10 minutes.";
        $headers = "From: no-reply@alihairwigs.com";

        // Send email
        mail($email, $subject, $body, $headers);

        header("Location: verify_code.php?email=" . urlencode($email));
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">

<div class="w-full max-w-md bg-white p-8 rounded-xl shadow">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Forgot Password</h2>
    <p class="text-gray-600 mb-4">Enter your registered email. A verification code will be sent.</p>

    <?php if ($message): ?>
    <div class="bg-red-100 border border-red-200 text-red-700 p-3 rounded mb-4">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
        <div>
            <label class="text-sm text-gray-700">Email</label>
            <input type="email" name="email" class="w-full border rounded-lg p-3" required>
        </div>

        <button name="send_code" class="w-full bg-[#7b3f00] text-white py-3 rounded-lg font-semibold hover:bg-[#5a2e00] transition">
            Send Code
        </button>

        <a href="login.php" class="block text-center mt-3 text-[#7b3f00] hover:underline">Back to Login</a>
    </form>
</div>

</body>
</html>