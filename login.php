<?php
session_start();

// ============================
// Database Connection
// ============================
$host = "localhost";
$user = "alihairw";
$pass = "x5.H(8xkh3H7EY";
$dbname = "alihairw_alihairwigs";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn && $conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$message = "";

// ============================
// Login Handler
// ============================
if (isset($_POST['login'])) {

    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $message = "❌ Please enter email and password";
    } else {

        $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();
            $stmt->close();

            if ($userData && password_verify($password, $userData['password'])) {

                // Secure session
                session_regenerate_id(true);

                $_SESSION['user_id'] = $userData['id'];
                $_SESSION['email'] = $userData['email'];

                header("Location: admin/admin_dashboard.php");
                exit();

            } else {
                $message = "❌ Invalid email or password";
            }
        } else {
            $message = "❌ Server error, try again later";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Login - Ali Hair Wigs</title>
  <meta name="viewport" content="width=device-width,initial-scale=1" />
      <!-- Favicon -->
    <link rel="icon" type="image/png" href="uploads/favicon.png">
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    /* small complement to Tailwind for the card look */
    .brand-accent { --accent: #7b3f00; }
    .focus-ring { box-shadow: 0 0 0 4px rgba(123,63,0,0.08); outline: none; border-color: #7b3f00; }
  </style>
</head>
<body class="min-h-screen bg-gradient-to-b from-gray-50 to-white flex items-center justify-center">

  <div class="w-full max-w-5xl mx-4 grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
    <!-- Left: branding / illustration (hidden on small screens) -->
    <div class="hidden md:flex flex-col justify-center gap-6 p-8">
      <a href="index.php" class="inline-flex items-center gap-3">
        <img src="uploads/ahw.png" alt="Ali Hair Wigs" class="h-12 w-12 object-contain rounded-md shadow-sm" onerror="this.onerror=null;this.src='https://placehold.co/80x80/7b3f00/f7e0c4?text=Logo'">
        <div>
          <div class="text-2xl font-extrabold text-[#7b3f00] leading-tight">ALI HAIR</div>
          <div class="text-sm font-semibold text-gray-600 -mt-1">WIGS</div>
        </div>
      </a>

      <div class="mt-6 bg-white/70 p-6 rounded-2xl shadow-lg border border-gray-100">
        <h3 class="text-2xl font-bold text-gray-900 mb-1">Welcome back</h3>
        <p class="text-sm text-gray-600">Sign in to manage products, orders and store settings.</p>

        <ul class="mt-4 space-y-2 text-sm text-gray-700">
          <li class="flex items-start gap-2"><svg class="h-4 w-4 text-[#7b3f00] mt-1" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2a8 8 0 108 8 8 8 0 00-8-8zM9 11l-1-1 4-4 1 1-4 4z"/></svg> Secure admin area</li>
          <li class="flex items-start gap-2"><svg class="h-4 w-4 text-[#7b3f00] mt-1" viewBox="0 0 20 20" fill="currentColor"><path d="M10 1a9 9 0 110 18A9 9 0 0110 1zm1 13H9v-2h2v2zm0-4H9V5h2v5z"/></svg> Two-step verification supported</li>
        </ul>
      </div>
  
    </div>

    <!-- Right: login card -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-10">
      <div class="flex items-center justify-between mb-6">
        <div>
          <h1 class="text-2xl md:text-3xl font-extrabold text-gray-900">Account Login</h1>
          <p class="text-sm text-gray-500 mt-1">Enter your credentials to access the admin area</p>
        </div>
        <div class="hidden sm:flex items-center gap-2 text-sm">
          <span class="text-gray-400">Need help?</span>
          <a href="contact.php" class="text-[#7b3f00] font-semibold hover:underline">Contact Support</a>
        </div>
      </div>

      <?php if ($message): ?>
        <div role="alert" class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-700 px-4 py-3">
          <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
        </div>
      <?php endif; ?>

      <form action="" method="POST" class="space-y-5" novalidate>
        <div>
          <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email</label>
          <input id="email" name="email" type="email" required autocomplete="email"
                 class="block w-full px-4 py-3 rounded-lg border border-gray-200 focus:outline-none focus:ring-0 focus:border-[#7b3f00] shadow-sm"
                 value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES) : ''; ?>">
        </div>

        <div>
          <div class="flex items-center justify-between mb-2">
            <label for="password" class="text-sm font-medium text-gray-700">Password</label>
            <a href="forgot_password.php" class="text-sm text-[#7b3f00] hover:underline">Forgot password?</a>
          </div>
          <input id="password" name="password" type="password" required autocomplete="current-password"
                 class="block w-full px-4 py-3 rounded-lg border border-gray-200 focus:outline-none focus:ring-0 focus:border-[#7b3f00] shadow-sm">
        </div>

        <div class="flex items-center justify-between gap-4">
          <label class="inline-flex items-center gap-2 text-sm">
            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-[#7b3f00] focus:ring-0">
            <span class="text-gray-600">Remember me</span>
          </label>

          <button type="submit" name="login"
                  class="ml-auto inline-flex items-center gap-2 bg-[#7b3f00] hover:bg-[#6a3500] text-white font-semibold px-5 py-3 rounded-lg shadow focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#7b3f00]">
            Sign in
          </button>
        </div>
      </form>

      <div class="mt-6 border-t border-gray-100 pt-5 text-center text-sm text-gray-600">
       

      <div class="mt-6 text-xs text-gray-400">

      </div>
    </div>
  </div>

  <script>
    // UX: focus the first empty field on load
    (function(){
      const email = document.getElementById('email');
      const pwd = document.getElementById('password');
      if (email && !email.value) { email.focus(); }
      else if (pwd) { pwd.focus(); }

      // Small accessible enhancement: press Enter on remember checkbox moves focus back to submit
      const remember = document.querySelector('input[name="remember"]');
      const submit = document.querySelector('button[name="login"]');
      if (remember && submit) {
        remember.addEventListener('keydown', function(e){
          if (e.key === 'Enter') {
            e.preventDefault();
            submit.focus();
          }
        });
      }
    })();
  </script>

</body>
</html>
