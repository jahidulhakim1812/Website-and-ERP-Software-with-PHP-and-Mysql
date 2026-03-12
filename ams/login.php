<?php
// login.php
session_start();

/* ---------- Update these DB credentials ---------- */
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

/* ---------- Helper / defaults ---------- */
$message = '';
$logoPath = 'images/logo.jpg'; // adjust if needed

/* ---------- Handle POST (login) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $message = 'Please enter username and password.';
    } else {
        try {
            $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);

            $stmt = $pdo->prepare('SELECT id, username, password, role, avatar FROM users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // Plaintext comparison to match provided SQL schema
            if ($user && hash_equals((string)$user['password'], (string)$password)) {
                // record last_login and a login event
                $update = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
                $update->execute([$user['id']]);

                $insertEvent = $pdo->prepare('INSERT INTO login_events (user_id, username, success, ip) VALUES (:uid, :uname, 1, :ip)');
                $insertEvent->execute([
                    ':uid' => $user['id'],
                    ':uname' => $user['username'],
                    ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                ]);

                // set session
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['avatar'] = $user['avatar'] ?? null;

                // redirect by role
                if ($user['role'] === 'admin') {
                    header('Location: admin/admin_dashboard.php');
                    exit;
                } elseif ($user['role'] === 'manager') {
                    header('Location: user/user_dashboard.php');
                    exit;
                } else {
                    $message = 'Your account has no valid role assigned.';
                }
            } else {
                // record failed login event
                if ($user) {
                    $insertEvent = $pdo->prepare('INSERT INTO login_events (user_id, username, success, ip) VALUES (:uid, :uname, 0, :ip)');
                    $insertEvent->execute([
                        ':uid' => $user['id'],
                        ':uname' => $user['username'],
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                    ]);
                } else {
                    $insertEvent = $pdo->prepare('INSERT INTO login_events (user_id, username, success, ip) VALUES (NULL, :uname, 0, :ip)');
                    $insertEvent->execute([
                        ':uname' => $username,
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'
                    ]);
                }
                $message = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            // In production, log the error instead of exposing it
            $message = 'Database connection error.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<!-- Favicon -->
<link rel="icon" type="image/png" href="images/logo.jpg">
<title>Sign in — Account Management System</title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<style>
/* Reset & base */
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%}
body{
  font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: #333;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  overflow-x: hidden;
  min-height: 100vh;
}

/* Container */
.container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 100vh;
  padding: 20px;
  width: 100%;
}

/* Card */
.card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
  padding: 32px;
  width: 100%;
  max-width: 420px;
  animation: cardEntrance 0.6s cubic-bezier(0.21, 0.61, 0.35, 1);
  position: relative;
  z-index: 10;
  margin: 0 auto;
}

@keyframes cardEntrance {
  from {
    opacity: 0;
    transform: translateY(30px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

/* Header */
.header {
  text-align: center;
  margin-bottom: 32px;
}

.logo-container {
  width: 80px;
  height: 80px;
  margin: 0 auto 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 20px;
  padding: 12px;
  box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-container img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 12px;
}

h1 {
  font-size: 24px;
  font-weight: 700;
  color: #2d3748;
  margin-bottom: 8px;
  line-height: 1.3;
}

.subtitle {
  color: #718096;
  font-size: 14px;
  line-height: 1.5;
}

/* Form */
.form-group {
  margin-bottom: 20px;
}

label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #4a5568;
  margin-bottom: 8px;
}

.input-group {
  position: relative;
}

input[type="text"],
input[type="password"] {
  width: 100%;
  padding: 14px 16px;
  font-size: 16px;
  border: 2px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  transition: all 0.3s ease;
  -webkit-appearance: none;
  appearance: none;
}

input[type="text"]:focus,
input[type="password"]:focus {
  outline: none;
  border-color: #667eea;
  background: white;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

input[type="text"]::placeholder,
input[type="password"]::placeholder {
  color: #a0aec0;
}

/* Button */
button.primary {
  width: 100%;
  padding: 16px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  margin-top: 10px;
  position: relative;
  overflow: hidden;
}

button.primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

button.primary:active {
  transform: translateY(0);
}

button.primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
}

/* Alert */
.alert {
  background: #fed7d7;
  border: 1px solid #fc8181;
  color: #c53030;
  padding: 12px 16px;
  border-radius: 8px;
  margin-bottom: 24px;
  font-size: 14px;
  animation: shake 0.5s ease-in-out;
}

@keyframes shake {
  0%, 100% { transform: translateX(0); }
  10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
  20%, 40%, 60%, 80% { transform: translateX(5px); }
}

/* Links */
.forgot-password {
  text-align: center;
  margin: 20px 0;
}

.forgot-password a {
  color: #667eea;
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition: color 0.2s;
}

.forgot-password a:hover {
  color: #764ba2;
  text-decoration: underline;
}

.role-badges {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin: 20px 0;
  flex-wrap: wrap;
}

.role-badge {
  background: #edf2f7;
  color: #4a5568;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Footer */
.footer {
  text-align: center;
  margin-top: 30px;
  padding-top: 20px;
  border-top: 1px solid #e2e8f0;
  color: #718096;
  font-size: 12px;
}

/* Splash Screen */
#splash {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  transition: opacity 0.5s ease, visibility 0.5s ease;
}

#splash.hidden {
  opacity: 0;
  visibility: hidden;
}

.splash-content {
  text-align: center;
  animation: pulse 2s infinite;
}

.splash-logo {
  width: 120px;
  height: 120px;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 24px;
  padding: 20px;
  margin: 0 auto 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
}

.splash-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
  border-radius: 16px;
}

.splash-text {
  color: white;
  font-size: 18px;
  font-weight: 600;
  letter-spacing: 1px;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

@keyframes pulse {
  0%, 100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}

/* Responsive Design */
@media (max-width: 480px) {
  .container {
    padding: 15px;
  }
  
  .card {
    padding: 24px 20px;
    border-radius: 12px;
  }
  
  .logo-container {
    width: 70px;
    height: 70px;
    padding: 10px;
  }
  
  h1 {
    font-size: 22px;
  }
  
  .subtitle {
    font-size: 13px;
  }
  
  input[type="text"],
  input[type="password"] {
    padding: 16px;
    font-size: 16px; /* Prevents iOS zoom on focus */
  }
  
  button.primary {
    padding: 18px;
  }
  
  .splash-logo {
    width: 100px;
    height: 100px;
  }
}

@media (max-width: 320px) {
  .card {
    padding: 20px 16px;
  }
  
  h1 {
    font-size: 20px;
  }
  
  .role-badges {
    flex-direction: column;
    align-items: center;
  }
}

/* Tablet Styles */
@media (min-width: 768px) and (max-width: 1024px) {
  .card {
    max-width: 480px;
    padding: 40px;
  }
  
  .logo-container {
    width: 90px;
    height: 90px;
  }
  
  h1 {
    font-size: 26px;
  }
}

/* Landscape Mobile */
@media (max-height: 600px) and (orientation: landscape) {
  .container {
    padding: 10px;
  }
  
  .card {
    padding: 20px;
    max-height: 90vh;
    overflow-y: auto;
  }
  
  .header {
    margin-bottom: 20px;
  }
  
  .form-group {
    margin-bottom: 15px;
  }
}

/* High-DPI Screens */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
  body {
    -webkit-font-smoothing: subpixel-antialiased;
  }
}

/* Print Styles */
@media print {
  .card {
    box-shadow: none;
    border: 1px solid #ddd;
  }
  
  button.primary {
    background: #666 !important;
    color: #fff !important;
  }
}

/* Accessibility: Reduced Motion */
@media (prefers-reduced-motion: reduce) {
  * {
    animation-duration: 0.01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 0.01ms !important;
  }
}

/* Dark Mode Support */
@media (prefers-color-scheme: dark) {
  .card {
    background: #2d3748;
    color: #e2e8f0;
  }
  
  h1 {
    color: #e2e8f0;
  }
  
  .subtitle {
    color: #a0aec0;
  }
  
  label {
    color: #cbd5e0;
  }
  
  input[type="text"],
  input[type="password"] {
    background: #4a5568;
    border-color: #718096;
    color: #e2e8f0;
  }
  
  input[type="text"]:focus,
  input[type="password"]:focus {
    background: #2d3748;
  }
  
  .role-badge {
    background: #4a5568;
    color: #cbd5e0;
  }
  
  .footer {
    color: #a0aec0;
    border-color: #4a5568;
  }
}

/* Loading state */
.loading {
  position: relative;
  pointer-events: none;
}

.loading::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 20px;
  height: 20px;
  margin: -10px 0 0 -10px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top: 2px solid white;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}
</style>
</head>
<body>
  <!-- Splash Screen -->
  <div id="splash">
    <div class="splash-content">
      <div class="splash-logo">
        <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="AMS Logo">
      </div>
      <div class="splash-text">ALI HAIR WIGS</div>
    </div>
  </div>

  <!-- Main Content -->
  <div class="container">
    <div class="card">
      <div class="header">
        <div class="logo-container">
          <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="AMS Logo">
        </div>
        <h1>Sign In</h1>
        <div class="subtitle">Account Management System</div>
      </div>

      <?php if ($message): ?>
        <div class="alert" role="alert">
          <?php echo htmlspecialchars($message); ?>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off" novalidate id="loginForm">
        <div class="form-group">
          <label for="username">Username</label>
          <div class="input-group">
            <input 
              type="text" 
              id="username" 
              name="username" 
              required 
              placeholder="Enter your username"
              autocapitalize="none"
              autocorrect="off"
              spellcheck="false"
            >
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-group">
            <input 
              type="password" 
              id="password" 
              name="password" 
              required 
              placeholder="Enter your password"
              autocapitalize="none"
              autocorrect="off"
              spellcheck="false"
            >
          </div>
        </div>

        <button type="submit" class="primary" id="submitBtn">
          Sign In
        </button>

        <div class="forgot-password">
          <a href="forgot_password.php">Forgot Password?</a>
        </div>

        <div class="role-badges">
          <span class="role-badge">Admin</span>
          <span class="role-badge">Manager</span>
        </div>
      </form>

      <div class="footer">
        &copy; <?php echo date('Y'); ?> Account Management System
      </div>
    </div>
  </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const splash = document.getElementById('splash');
  const loginForm = document.getElementById('loginForm');
  const submitBtn = document.getElementById('submitBtn');
  const usernameInput = document.getElementById('username');
  
  // Handle splash screen
  function hideSplash() {
    splash.classList.add('hidden');
    setTimeout(() => {
      splash.style.display = 'none';
      if (usernameInput) {
        usernameInput.focus();
      }
    }, 500);
  }
  
  // Hide splash screen after minimum time or on user interaction
  let splashTimer = setTimeout(hideSplash, 1500);
  
  splash.addEventListener('click', () => {
    clearTimeout(splashTimer);
    hideSplash();
  });
  
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' || e.key === ' ' || e.key === 'Escape') {
      clearTimeout(splashTimer);
      hideSplash();
    }
  });
  
  // Handle form submission
  if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
      if (!submitBtn.disabled) {
        submitBtn.disabled = true;
        submitBtn.classList.add('loading');
        submitBtn.innerHTML = 'Signing In...';
        
        // Re-enable after 10 seconds if still on page
        setTimeout(() => {
          if (submitBtn.disabled) {
            submitBtn.disabled = false;
            submitBtn.classList.remove('loading');
            submitBtn.innerHTML = 'Sign In';
          }
        }, 10000);
      }
    });
  }
  
  // Auto-focus username field when splash is hidden
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.attributeName === 'class' && 
          splash.classList.contains('hidden') && 
          usernameInput) {
        setTimeout(() => usernameInput.focus(), 100);
      }
    });
  });
  
  observer.observe(splash, { attributes: true });
  
  // Handle mobile keyboard adjustments
  function adjustForKeyboard() {
    if (window.innerHeight < 500) {
      document.body.style.minHeight = window.innerHeight + 'px';
    }
  }
  
  window.addEventListener('resize', adjustForKeyboard);
  adjustForKeyboard();
  
  // Prevent form resubmission on refresh
  if (window.history.replaceState) {
    window.history.replaceState(null, null, window.location.href);
  }
  
  // Touch feedback for mobile
  const buttons = document.querySelectorAll('button, input[type="submit"], a');
  buttons.forEach(button => {
    button.addEventListener('touchstart', function() {
      this.classList.add('touch-active');
    }, { passive: true });
    
    button.addEventListener('touchend', function() {
      this.classList.remove('touch-active');
    }, { passive: true });
  });
  
  // Handle orientation change
  window.addEventListener('orientationchange', function() {
    setTimeout(() => {
      window.scrollTo(0, 0);
    }, 100);
  });
});
</script>
</body>
</html>