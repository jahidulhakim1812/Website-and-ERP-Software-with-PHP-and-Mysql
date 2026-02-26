<?php
session_start();

// ============================
// Database Connection
// ============================
$host = "localhos";
$user = "alihairw";
$pass = "x5.H(8xkh3H7EY";
$dbname = "alihairw_alihairwigs";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// ============================
// Register Logic
// ============================
if (isset($_POST['register'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);

    if ($password !== $confirm_password) {
        $message = "❌ Passwords do not match!";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();

        if ($result->num_rows > 0) {
            $message = "⚠️ Email is already registered!";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insert = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
            $insert->bind_param("ss", $email, $hashedPassword);

            if ($insert->execute()) {
                $_SESSION['success'] = "✅ Registration successful! Please login.";
                header("Location: account.php");
                exit();
            } else {
                $message = "❌ Something went wrong. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Ali Hair Wigs</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <h2 class="text-3xl font-bold text-center mb-6">Create Account</h2>

        <?php if ($message): ?>
            <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" class="space-y-4">
            <div>
                <label for="email" class="block font-semibold mb-1">Email</label>
                <input type="email" name="email" id="email" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black">
            </div>

            <div>
                <label for="password" class="block font-semibold mb-1">Password</label>
                <input type="password" name="password" id="password" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black">
            </div>

            <div>
                <label for="confirm_password" class="block font-semibold mb-1">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required class="w-full border rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-black">
            </div>

            <button type="submit" name="register" class="w-full bg-black text-white py-2 rounded font-semibold hover:bg-gray-800">
                Register
            </button>
        </form>

        <p class="mt-4 text-center text-sm text-gray-600">
            Already have an account? 
            <a href="account.php" class="text-black font-semibold hover:underline">Login</a>
        </p>
    </div>

</body>
</html>
