<?php
// verify_code.php
session_start();

/* CONFIG - update for your environment */
$db_host = 'localhost';
$db_user = 'alihairw';
$db_pass = 'x5.H(8xkh3H7EY';
$db_name = 'alihairw_alihairwigs';


$app_timezone = 'Asia/Dhaka';
$users_table = 'users';         // your users table
$password_column = 'password';  // password column
$role_column = 'role';          // role column (if you use it)
$admin_role_value = 'admin';    // value that identifies admin users
$redirect_after_reset = 'admin/admin_dashboard.php'; // final redirect

/* CONNECT */
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_errno) {
    http_response_code(500);
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset('utf8mb4');

/* INPUT */
$email = trim($_GET['email'] ?? '');
$message = '';
$show_reset_form = false;

if ($email === '') {
    http_response_code(400);
    die("Error: Email missing. Return to the forget password page.");
}

/* Helper: fetch latest password_resets row for email */
function fetch_latest_reset($conn, $email) {
    $stmt = $conn->prepare("SELECT id, code, expires_at FROM password_resets WHERE email = ? ORDER BY id DESC LIMIT 1");
    if (!$stmt) return false;
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) { $stmt->close(); return false; }
    $stmt->bind_result($id, $code, $expires_at);
    if ($stmt->fetch()) {
        $stmt->close();
        return ['id' => $id, 'code' => $code, 'expires_at' => $expires_at];
    }
    $stmt->close();
    return false;
}

/* Helper: fetch user row by email (id, role) */
function fetch_user_by_email($conn, $email, $users_table, $role_column) {
    $sql = "SELECT id, {$role_column} FROM {$users_table} WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) return false;
    $stmt->bind_param("s", $email);
    if (!$stmt->execute()) { $stmt->close(); return false; }
    $stmt->bind_result($id, $role);
    if ($stmt->fetch()) {
        $stmt->close();
        return ['id' => $id, 'role' => $role];
    }
    $stmt->close();
    return false;
}

/* POST: verifying code */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify'])) {
    $code = trim((string)($_POST['code'] ?? ''));

    if ($code === '') {
        $message = "❌ Please enter the verification code.";
    } else {
        $row = fetch_latest_reset($conn, $email);
        if (!$row) {
            $message = "❌ Invalid or expired code.";
        } else {
            try {
                $tz = new DateTimeZone($app_timezone);
                $now = new DateTime('now', $tz);
                $exp = new DateTime($row['expires_at'], new DateTimeZone('UTC'));
                $exp->setTimezone($tz);
            } catch (Exception $e) {
                $message = "Date parsing error.";
                $exp = null;
            }

            if ($exp !== null && $exp < $now) {
                $message = "❌ Invalid or expired code.";
            } else {
                $db_code = $row['code'];
                $is_valid = false;

                if (is_string($db_code) && preg_match('/^\$2[axy]\$/', $db_code)) {
                    $is_valid = password_verify($code, $db_code);
                } else {
                    $is_valid = hash_equals((string)$db_code, (string)$code);
                }

                if ($is_valid) {
                    $_SESSION['email_auth'] = $email;
                    $_SESSION['can_reset'] = true;
                    $show_reset_form = true;
                } else {
                    $message = "❌ Invalid or expired code.";
                }
            }
        }
    }
}

/* POST: submit new password */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if (empty($_SESSION['email_auth']) || empty($_SESSION['can_reset']) || $_SESSION['email_auth'] !== $email) {
        $message = "❌ Unauthorized request. Please verify code first.";
    } else {
        $pass = $_POST['password'] ?? '';
        $pass_confirm = $_POST['password_confirm'] ?? '';

        if ($pass === '' || $pass_confirm === '') {
            $message = "❌ Please fill both password fields.";
            $show_reset_form = true;
        } elseif ($pass !== $pass_confirm) {
            $message = "❌ Passwords do not match.";
            $show_reset_form = true;
        } elseif (strlen($pass) < 8) {
            $message = "❌ Password must be at least 8 characters.";
            $show_reset_form = true;
        } else {
            $password_hashed = password_hash($pass, PASSWORD_DEFAULT);

            $update = $conn->prepare("UPDATE {$users_table} SET {$password_column} = ? WHERE email = ?");
            if (!$update) {
                $message = "SQL prepare error: " . $conn->error;
                $show_reset_form = true;
            } else {
                $update->bind_param("ss", $password_hashed, $email);
                if (!$update->execute()) {
                    $message = "SQL execute error: " . $update->error;
                    $show_reset_form = true;
                } elseif ($update->affected_rows <= 0) {
                    $message = "❌ No account found for that email.";
                    $show_reset_form = true;
                } else {
                    $update->close();

                    // Delete any password_resets rows for that email
                    $del = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
                    if ($del) {
                        $del->bind_param("s", $email);
                        $del->execute();
                        $del->close();
                    }

                    // Log the user in as admin if role matches (or just set logged-in session)
                    $user = fetch_user_by_email($conn, $email, $users_table, $role_column);
                    if ($user) {
                        // Set session values your admin area expects.
                        // Common examples:
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $email;
                        // mark admin if role matches
                        if (!empty($user['role']) && $user['role'] === $admin_role_value) {
                            $_SESSION['is_admin'] = true;
                        } else {
                            // still allow login; remove or change as your policy requires
                            $_SESSION['is_admin'] = false;
                        }
                    } else {
                        // fallback: set basic session
                        $_SESSION['user_email'] = $email;
                    }

                    // Clear the temporary reset flags
                    unset($_SESSION['can_reset']);
                    unset($_SESSION['email_auth']);

                    // Redirect to admin dashboard inside admin folder
                    header("Location: " . $redirect_after_reset);
                    exit();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Verify Code / Reset Password</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-gray-100">
  <div class="w-full max-w-md bg-white p-8 rounded-xl shadow">
    <h2 class="text-2xl font-bold text-gray-800 mb-4">Reset Password</h2>

    <p class="text-gray-600 mb-4">
      Process for:
      <b><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></b>
    </p>

    <?php if ($message): ?>
    <div class="bg-red-100 border border-red-200 text-red-700 p-3 rounded mb-4">
      <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['can_reset']) && $_SESSION['can_reset'] && $_SESSION['email_auth'] === $email): ?>
      <form method="POST" class="space-y-4" autocomplete="off">
        <div>
          <label class="text-sm text-gray-700">New Password</label>
          <input type="password" name="password" class="w-full border rounded-lg p-3" required />
        </div>
        <div>
          <label class="text-sm text-gray-700">Confirm New Password</label>
          <input type="password" name="password_confirm" class="w-full border rounded-lg p-3" required />
        </div>

        <button name="reset_password" class="w-full bg-[#7b3f00] text-white py-3 rounded-lg font-semibold">
          Set New Password
        </button>
      </form>
    <?php else: ?>
      <form method="POST" class="space-y-4" autocomplete="off">
        <div>
          <label class="text-sm text-gray-700">Enter Verification Code</label>
          <input type="text" name="code" class="w-full border rounded-lg p-3" required inputmode="numeric" pattern="\d*" />
        </div>

        <button name="verify" class="w-full bg-[#7b3f00] text-white py-3 rounded-lg font-semibold">
          Verify Code
        </button>
      </form>

      <p class="text-xs text-gray-500 mt-4">If you didn't receive a code, request a new one from the forget password page.</p>
    <?php endif; ?>
  </div>
</body>
</html>
