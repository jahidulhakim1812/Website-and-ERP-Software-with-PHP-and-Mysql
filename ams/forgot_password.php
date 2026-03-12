<?php
// forgot_password.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();

/* ---------- Database Configuration ---------- */
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

/* ---------- Email Configuration ---------- */
$COMPANY_NAME = "ALI HAIR WIGS";
$SUPPORT_EMAIL = "support@alihairwigs.com";
$FROM_EMAIL = "contact@alihairwigs.com"; // CRITICAL: Use your domain email
$FROM_NAME = "ALI HAIR WIGS";

/* ---------- Create password_reset_codes table ---------- */
try {
    $pdo_temp = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo_temp->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo_temp->exec("
        CREATE TABLE IF NOT EXISTS password_reset_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            code VARCHAR(6) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
} catch (PDOException $e) {
    // Continue anyway
}

/* ---------- Variables ---------- */
$message = '';
$message_type = 'info';
$logoPath = 'images/logo.jpg';
$show_username_form = true;
$show_verification_form = false;
$show_new_password_form = false;

// Check session state
if (isset($_SESSION['password_reset_verified']) && $_SESSION['password_reset_verified'] === true) {
    $show_username_form = false;
    $show_verification_form = false;
    $show_new_password_form = true;
} elseif (isset($_SESSION['password_reset_user_id']) && !isset($_SESSION['password_reset_verified'])) {
    $show_username_form = false;
    $show_verification_form = true;
}

// **SIMPLE EMAIL FUNCTION THAT WORKS ON SHARED HOSTING**
function sendSimpleEmail($to_email, $subject, $message_body) {
    global $FROM_EMAIL, $FROM_NAME;
    
    // Headers for email
    $headers = "From: $FROM_NAME <$FROM_EMAIL>\r\n";
    $headers .= "Reply-To: $FROM_EMAIL\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    // Send email using PHP's mail() function
    return mail($to_email, $subject, $message_body, $headers);
}

// Function to mask email
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) != 2) return $email;
    
    $username = $parts[0];
    $domain = $parts[1];
    
    $len = strlen($username);
    if ($len <= 2) {
        $masked_username = str_repeat('*', $len);
    } else {
        $masked_username = substr($username, 0, 2) . str_repeat('*', $len - 2);
    }
    
    return $masked_username . '@' . $domain;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Database connection
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Step 1: Submit username/email
        if (isset($_POST['step']) && $_POST['step'] == '1' && isset($_POST['username'])) {
            $input = trim($_POST['username']);
            
            if (empty($input)) {
                $message = 'Please enter your username or email address.';
                $message_type = 'error';
            } else {
                // Check if input is email or username
                if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
                    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE email = ? LIMIT 1');
                } else {
                    $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE username = ? LIMIT 1');
                }
                
                $stmt->execute([$input]);
                $user = $stmt->fetch();
                
                if ($user) {
                    $user_id = $user['id'];
                    $username = $user['username'];
                    $email = $user['email'];
                    
                    // Generate 6-digit verification code
                    $verification_code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expires_at = date('Y-m-d H:i:s', time() + (15 * 60));
                    
                    // Store verification code in database
                    $stmt = $pdo->prepare('INSERT INTO password_reset_codes (user_id, code, expires_at) VALUES (?, ?, ?)');
                    $stmt->execute([$user_id, $verification_code, $expires_at]);
                    
                    // Create simple HTML email
                    $subject = "Password Reset Verification Code - $COMPANY_NAME";
                    
                    $email_body = "
                    <html>
                    <body style='font-family: Arial, sans-serif;'>
                        <div style='max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd;'>
                            <div style='background: #667eea; color: white; padding: 20px; text-align: center;'>
                                <h2>$COMPANY_NAME</h2>
                                <h3>Password Reset Request</h3>
                            </div>
                            <div style='padding: 20px;'>
                                <p>Hello $username,</p>
                                <p>We received a request to reset your password for your account at $COMPANY_NAME.</p>
                                <p>Use the verification code below to proceed with resetting your password:</p>
                                
                                <div style='background: #f8f9fa; border: 2px dashed #667eea; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; color: #667eea; margin: 20px 0;'>
                                    $verification_code
                                </div>
                                
                                <p><strong>Important:</strong></p>
                                <ul>
                                    <li>This code will expire in 15 minutes</li>
                                    <li>If you didn't request this password reset, please ignore this email</li>
                                    <li>For security reasons, do not share this code with anyone</li>
                                </ul>
                                
                                <p>If you're having trouble with the verification code, you can request a new one on the password reset page.</p>
                                
                                <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;'>
                                    <p>This is an automated message. Please do not reply to this email.</p>
                                    <p>© " . date('Y') . " $COMPANY_NAME. All rights reserved.</p>
                                </div>
                            </div>
                        </div>
                    </body>
                    </html>
                    ";
                    
                    // Try to send email
                    $email_sent = sendSimpleEmail($email, $subject, $email_body);
                    
                    // Store in session
                    $_SESSION['password_reset_user_id'] = $user_id;
                    $_SESSION['password_reset_email'] = $email;
                    $_SESSION['password_reset_username'] = $username;
                    $_SESSION['verification_code'] = $verification_code; // Store in session
                    
                    if ($email_sent) {
                        $message = "A 6-digit verification code has been sent to: " . maskEmail($email);
                        $message_type = 'success';
                    } else {
                        // Show code on screen if email fails
                        $message = "Email system issue. Your verification code is: <strong>$verification_code</strong>";
                        $message .= "<br>Please use this code to continue. (Check your spam folder if email arrives later.)";
                        $message_type = 'warning';
                    }
                    
                    $show_username_form = false;
                    $show_verification_form = true;
                    
                } else {
                    $message = 'Username or email not found. Please check and try again.';
                    $message_type = 'error';
                }
            }
        }
        
        // Step 2: Verify code
        elseif (isset($_POST['step']) && $_POST['step'] == '2' && isset($_POST['verification_code'])) {
            $code = trim($_POST['verification_code']);
            
            if (!isset($_SESSION['password_reset_user_id'])) {
                $message = 'Session expired. Please start over.';
                $message_type = 'error';
                $show_username_form = true;
                session_destroy();
            } elseif (empty($code) || strlen($code) !== 6 || !is_numeric($code)) {
                $message = 'Please enter a valid 6-digit code.';
                $message_type = 'error';
                $show_verification_form = true;
            } else {
                $user_id = $_SESSION['password_reset_user_id'];
                $current_time = date('Y-m-d H:i:s');
                
                // Check code in database
                $stmt = $pdo->prepare('
                    SELECT id FROM password_reset_codes 
                    WHERE user_id = ? AND code = ? AND expires_at > ? AND used = 0 
                    ORDER BY created_at DESC LIMIT 1
                ');
                $stmt->execute([$user_id, $code, $current_time]);
                $valid_code = $stmt->fetch();
                
                // Also check session code
                $session_code = isset($_SESSION['verification_code']) ? $_SESSION['verification_code'] : null;
                
                if ($valid_code || ($session_code && $session_code == $code)) {
                    // Mark as used
                    if ($valid_code) {
                        $stmt = $pdo->prepare('UPDATE password_reset_codes SET used = 1 WHERE id = ?');
                        $stmt->execute([$valid_code['id']]);
                    }
                    
                    // Store in session
                    $_SESSION['password_reset_verified'] = true;
                    
                    $message = 'Verification successful! You can now set a new password.';
                    $message_type = 'success';
                    $show_verification_form = false;
                    $show_new_password_form = true;
                } else {
                    $message = 'Invalid or expired verification code. Please try again.';
                    $message_type = 'error';
                    $show_verification_form = true;
                }
            }
        }
        
        // Step 3: Set new password
        elseif (isset($_POST['step']) && $_POST['step'] == '3' && isset($_POST['new_password'])) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (!isset($_SESSION['password_reset_user_id']) || !isset($_SESSION['password_reset_verified'])) {
                $message = 'Session expired. Please start over.';
                $message_type = 'error';
                $show_username_form = true;
                session_destroy();
            } elseif ($new_password !== $confirm_password) {
                $message = 'Passwords do not match.';
                $message_type = 'error';
                $show_new_password_form = true;
            } elseif (strlen($new_password) < 8) {
                $message = 'Password must be at least 8 characters.';
                $message_type = 'error';
                $show_new_password_form = true;
            } else {
                $user_id = $_SESSION['password_reset_user_id'];
                
                // Store password as plain text
                $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $stmt->execute([$new_password, $user_id]);
                
                $message = "Password reset successful!<br><br>Your password has been updated. You can now login with your new password.";
                $message_type = 'success';
                $show_new_password_form = false;
                
                // Clear session
                session_destroy();
            }
        }
        
    } catch (PDOException $e) {
        $message = 'Database error: ' . $e->getMessage();
        $message_type = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo $COMPANY_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container { 
            width: 100%;
            max-width: 500px;
        }
        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            padding: 40px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            max-width: 80px;
            border-radius: 10px;
        }
        .logo h1 {
            margin-top: 15px;
            color: #333;
            font-size: 24px;
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        .alert-success {
            background: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        .alert-warning {
            background: #fff3cd;
            border-color: #ffc107;
            color: #856404;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 600;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
        }
        .btn-secondary {
            background: #6c757d;
            margin-top: 10px;
        }
        .verification-inputs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            justify-content: center;
        }
        .verification-digit {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 8px;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 14px;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        .instructions {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #666;
        }
        .code-display {
            background: #e8f4fd;
            border: 2px dashed #007bff;
            padding: 15px;
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            margin: 15px 0;
            border-radius: 8px;
            color: #004085;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <img src="<?php echo $logoPath; ?>" alt="Logo">
                <h1>Forgot Password</h1>
                <p style="color: #666; margin-top: 5px;">
                    <?php 
                    if ($show_verification_form) {
                        echo 'Enter Verification Code';
                    } elseif ($show_new_password_form) {
                        echo 'Set New Password';
                    } else {
                        echo 'Reset your password';
                    }
                    ?>
                </p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($show_username_form): ?>
                <!-- Step 1: Enter username/email -->
                <div class="instructions">
                    <p><strong>Instructions:</strong></p>
                    <p>Enter your username or email address. We'll send a verification code to your email.</p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="step" value="1">
                    <div class="form-group">
                        <label for="username">Username or Email</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="Enter username or email"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                               autofocus>
                    </div>
                    <button type="submit" class="btn">Send Verification Code</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='login.php'">
                        Back to Login
                    </button>
                </form>
                
            <?php elseif ($show_verification_form && isset($_SESSION['password_reset_email'])): ?>
                <!-- Step 2: Enter verification code -->
                <div class="instructions">
                    <p><strong>Code sent to:</strong> <?php echo maskEmail($_SESSION['password_reset_email']); ?></p>
                    <p>Enter the 6-digit verification code from your email.</p>
                    
                    <?php if (isset($_SESSION['verification_code']) && $message_type == 'warning'): ?>
                        <div class="code-display">
                            Your Code: <?php echo $_SESSION['verification_code']; ?>
                        </div>
                        <p style="color: #666; font-size: 14px; margin-top: 10px;">
                            <em>Email delivery may be delayed. Use the code above if you don't receive the email immediately.</em>
                        </p>
                    <?php endif; ?>
                </div>
                
                <form method="POST" action="" id="verificationForm">
                    <input type="hidden" name="step" value="2">
                    <div class="form-group">
                        <label for="verification_code">Verification Code</label>
                        <div class="verification-inputs">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <input type="text" maxlength="1" class="verification-digit" 
                                       data-index="<?php echo $i; ?>"
                                       oninput="moveToNext(this)">
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="verification_code" id="verification_code">
                    </div>
                    <button type="submit" class="btn" id="verifyBtn" disabled>Verify Code</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='forgot_password.php'">
                        Start Over
                    </button>
                </form>
                
                <div class="footer">
                    <p>Didn't receive the code? <a href="#" onclick="resendCode()">Resend Code</a></p>
                </div>
                
            <?php elseif ($show_new_password_form): ?>
                <!-- Step 3: Set new password -->
                <div class="instructions">
                    <p><strong>Set a new password for your account</strong></p>
                    <p>Password must be at least 8 characters long.</p>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="step" value="3">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" id="new_password" name="new_password" required 
                               placeholder="Enter new password"
                               autofocus>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Confirm new password">
                    </div>
                    <button type="submit" class="btn">Reset Password</button>
                    <button type="button" class="btn btn-secondary" onclick="window.location.href='forgot_password.php'">
                        Cancel
                    </button>
                </form>
                
            <?php else: ?>
                <!-- Success message -->
                <div class="instructions" style="text-align: center;">
                    <p style="font-size: 18px; color: #28a745;">✅ Password reset successful!</p>
                    <p>You can now login with your new password.</p>
                </div>
                <button class="btn" onclick="window.location.href='login.php'">Go to Login</button>
            <?php endif; ?>
            
            <div class="footer">
                Need help? <a href="mailto:<?php echo $SUPPORT_EMAIL; ?>">Contact Support</a><br>
                &copy; <?php echo date('Y'); ?> <?php echo $COMPANY_NAME; ?>
            </div>
        </div>
    </div>

    <script>
        // Handle verification code input
        function moveToNext(input) {
            // Only allow numbers
            input.value = input.value.replace(/\D/g, '');
            
            // Move to next input
            if (input.value.length >= input.maxLength) {
                const nextIndex = parseInt(input.dataset.index);
                const nextInput = document.querySelector(`.verification-digit[data-index="${nextIndex + 1}"]`);
                if (nextInput) {
                    nextInput.focus();
                }
            }
            
            // Update hidden input
            updateVerificationCode();
        }
        
        function updateVerificationCode() {
            let code = '';
            const inputs = document.querySelectorAll('.verification-digit');
            inputs.forEach(input => {
                code += input.value;
            });
            
            document.getElementById('verification_code').value = code;
            document.getElementById('verifyBtn').disabled = code.length !== 6;
        }
        
        // Initialize verification inputs
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('.verification-digit');
            if (inputs.length > 0) {
                inputs[0].focus();
            }
        });
        
        function resendCode() {
            if (confirm('Send a new verification code?')) {
                window.location.href = 'forgot_password.php';
            }
        }
    </script>
</body>
</html>