<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>Testing Email Configuration</h2>";

// Test 1: Check if PHPMailer exists
echo "<h3>Test 1: PHPMailer Files</h3>";
$files = [
    'PHPMailer/src/Exception.php',
    'PHPMailer/src/PHPMailer.php',
    'PHPMailer/src/SMTP.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✓ $file exists<br>";
    } else {
        echo "✗ $file NOT FOUND<br>";
    }
}

// Test 2: Test SMTP connection
echo "<h3>Test 2: SMTP Connection</h3>";
$smtp = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 10);
if ($smtp) {
    echo "✓ SMTP connection successful<br>";
    fclose($smtp);
} else {
    echo "✗ SMTP connection failed: $errno - $errstr<br>";
}

// Test 3: Test PHP mail function
echo "<h3>Test 3: PHP Mail Function</h3>";
if (function_exists('mail')) {
    echo "✓ mail() function exists<br>";
    
    // Send a test email
    $to = "test@example.com";
    $subject = "Test Email";
    $message = "This is a test email from PHP mail() function";
    $headers = "From: test@localhost\r\n";
    
    if (mail($to, $subject, $message, $headers)) {
        echo "✓ Test email sent via mail()<br>";
    } else {
        echo "✗ mail() function failed<br>";
    }
} else {
    echo "✗ mail() function not available<br>";
}

// Test 4: Check if session is working
echo "<h3>Test 4: Session</h3>";
session_start();
$_SESSION['test'] = 'Hello World';
echo "✓ Session started and test variable set<br>";

// Test 5: Database connection
echo "<h3>Test 5: Database Connection</h3>";
try {
    $pdo = new PDO('mysql:host=localhost;dbname=ams;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "✓ Database connection successful<br>";
    
    // Check if users table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    if (count($tables) > 0) {
        echo "✓ Users table exists<br>";
    } else {
        echo "✗ Users table does not exist<br>";
    }
    
    // Check if password_reset_codes table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'password_reset_codes'")->fetchAll();
    if (count($tables) > 0) {
        echo "✓ password_reset_codes table exists<br>";
    } else {
        echo "✗ password_reset_codes table does not exist<br>";
        echo "Run this SQL:<br><pre>";
        echo "CREATE TABLE IF NOT EXISTS password_reset_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);</pre>";
    }
    
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "<br>";
}

echo "<hr><h2>Common Issues and Solutions:</h2>";
echo "<ol>";
echo "<li><strong>PHPMailer not found:</strong> Download from https://github.com/PHPMailer/PHPMailer and extract to your project folder</li>";
echo "<li><strong>Gmail SMTP issues:</strong> Make sure you have enabled 2-factor authentication and generated an App Password</li>";
echo "<li><strong>Database tables missing:</strong> Run the SQL queries shown above</li>";
echo "<li><strong>PHP mail() function:</strong> On localhost, you might need to configure SMTP in php.ini</li>";
echo "</ol>";
?>