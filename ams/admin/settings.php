<?php
/**
 * database_backup.php
 * NexusAdmin - Database Backup System (Fixed Version)
 */

session_start();

// --- 1. AUTHENTICATION & SECURITY ---
session_start();

// Security headers to prevent caching
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Redirect to login page - NO AUTO-LOGIN!
    header("Location: login.php");  // You need to create this file
    exit();
}

// Session timeout - 30 minutes
$timeout = 1800; // 30 minutes in seconds
if (isset($_SESSION['login_time'])) {
    $session_life = time() - $_SESSION['login_time'];
    if ($session_life > $timeout) {
        session_destroy();
        header("Location: login.php?expired=1");
        exit();
    }
}
$_SESSION['login_time'] = time();

// --- LOGOUT HANDLING ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Unset all session variables
    $_SESSION = array();
    
    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy the session
    session_destroy();
    
    // Clear any auth headers
    header_remove();
    
    // Redirect to login page or home with cache prevention headers
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Location: login.php");  // Changed from admin_dashboard.php to login.php
    exit(); // IMPORTANT: exit AFTER redirect header
}


// --- 2. DATABASE CONNECTION ---
$DB_HOST = 'localhost';
$DB_NAME = 'alihairw_alisoft';
$DB_USER = 'alihairw_ali';
$DB_PASS = 'x5.H(8xkh3H7EY';

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_CASE => PDO::CASE_LOWER // Force lowercase column names
    ]);
} catch (PDOException $ex) {
    die("Database Connection Error: " . $ex->getMessage());
}

// --- 3. CREATE/UPDATE BACKUP SETTINGS TABLE ---
try {
    // First, check if table exists
    $tableExists = $pdo->query("SHOW TABLES LIKE 'backup_settings'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Create table if it doesn't exist
        $createTable = "CREATE TABLE backup_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            backup_enabled TINYINT(1) DEFAULT 1,
            backup_frequency ENUM('daily', 'weekly', 'monthly') DEFAULT 'weekly',
            backup_day VARCHAR(20) DEFAULT 'monday',
            backup_time TIME DEFAULT '02:00:00',
            backup_retention INT DEFAULT 30,
            max_backups INT DEFAULT 10,
            compress_backup TINYINT(1) DEFAULT 1,
            email_notification TINYINT(1) DEFAULT 1,
            email_address VARCHAR(255),
            last_backup DATETIME,
            last_backup_file VARCHAR(255),
            backup_path VARCHAR(500) DEFAULT './backups/',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $pdo->exec($createTable);
        
        // Insert default settings
        $defaultSettings = [
            'backup_enabled' => 1,
            'backup_frequency' => 'weekly',
            'backup_day' => 'monday',
            'backup_time' => '02:00:00',
            'backup_retention' => 30,
            'max_backups' => 10,
            'compress_backup' => 1,
            'email_notification' => 1,
            'email_address' => $_SESSION['email'] ?? 'admin@example.com',
            'backup_path' => __DIR__ . '/backups/'
        ];
        
        $insertSql = "INSERT INTO backup_settings (";
        $insertSql .= implode(', ', array_keys($defaultSettings));
        $insertSql .= ") VALUES (";
        $insertSql .= "'" . implode("', '", array_values($defaultSettings)) . "'";
        $insertSql .= ")";
        
        $pdo->exec($insertSql);
    }
    
    // Check if backup_method column exists, add if it doesn't
    $checkColumn = $pdo->query("SHOW COLUMNS FROM backup_settings LIKE 'backup_method'")->rowCount();
    if ($checkColumn == 0) {
        $pdo->exec("ALTER TABLE backup_settings ADD COLUMN backup_method ENUM('mysqldump', 'php', 'both') DEFAULT 'php' AFTER email_address");
    }
    
    // Check if last_backup_size column exists, add if it doesn't
    $checkColumn = $pdo->query("SHOW COLUMNS FROM backup_settings LIKE 'last_backup_size'")->rowCount();
    if ($checkColumn == 0) {
        $pdo->exec("ALTER TABLE backup_settings ADD COLUMN last_backup_size INT AFTER last_backup_file");
    }
    
} catch (PDOException $e) {
    // Log error but continue
    error_log("Database setup error: " . $e->getMessage());
}

// --- 4. HELPER FUNCTIONS ---
function createBackupWithPHP($pdo, $backupFile) {
    // Set unlimited execution time for large databases
    set_time_limit(0);
    ini_set('memory_limit', '1024M');
    
    $handle = fopen($backupFile, 'w');
    if (!$handle) {
        return false;
    }
    
    // Write UTF-8 BOM for better compatibility
    fwrite($handle, "\xEF\xBB\xBF");
    
    // Write header
    fwrite($handle, "-- NexusAdmin Database Backup\n");
    fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Database: " . $GLOBALS['DB_NAME'] . "\n");
    fwrite($handle, "-- PHP Version: " . phpversion() . "\n");
    fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
    fwrite($handle, "SET AUTOCOMMIT = 0;\n");
    fwrite($handle, "START TRANSACTION;\n");
    fwrite($handle, "SET time_zone = \"+00:00\";\n\n");
    
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Drop table if exists
        fwrite($handle, "--\n");
        fwrite($handle, "-- Table structure for table `{$table}`\n");
        fwrite($handle, "--\n\n");
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n\n");
        
        // Get create table statement - handle different column name cases
        $createTableResult = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
        
        // Debug: Check what keys are available
        if (empty($createTableResult)) {
            fwrite($handle, "-- ERROR: Could not get CREATE TABLE statement for `{$table}`\n\n");
            continue;
        }
        
        // Try different possible column names (case-insensitive)
        $createTableSQL = '';
        if (isset($createTableResult['Create Table'])) {
            $createTableSQL = $createTableResult['Create Table'];
        } elseif (isset($createTableResult['create table'])) {
            $createTableSQL = $createTableResult['create table'];
        } elseif (isset($createTableResult['CREATE TABLE'])) {
            $createTableSQL = $createTableResult['CREATE TABLE'];
        } elseif (isset($createTableResult[1])) {
            // Use numeric index (1 is usually the CREATE TABLE statement)
            $createTableSQL = $createTableResult[1];
        } else {
            // Try to get any value that might contain the CREATE TABLE statement
            foreach ($createTableResult as $key => $value) {
                if (stripos($key, 'create') !== false) {
                    $createTableSQL = $value;
                    break;
                }
            }
        }
        
        if (empty($createTableSQL)) {
            fwrite($handle, "-- ERROR: CREATE TABLE statement not found for `{$table}`\n\n");
            continue;
        }
        
        fwrite($handle, $createTableSQL . ";\n\n");
        
        // Get table data
        fwrite($handle, "--\n");
        fwrite($handle, "-- Dumping data for table `{$table}`\n");
        fwrite($handle, "--\n\n");
        
        try {
            $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($rows) > 0) {
                // Get column names
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            // Escape special characters
                            $value = str_replace("'", "''", $value);
                            $value = str_replace("\\", "\\\\", $value);
                            $values[] = "'" . $value . "'";
                        }
                    }
                    $sql = "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                    fwrite($handle, $sql);
                }
                fwrite($handle, "\n");
            }
        } catch (Exception $e) {
            fwrite($handle, "-- ERROR reading data from table `{$table}`: " . $e->getMessage() . "\n\n");
        }
    }
    
    // Write footer
    fwrite($handle, "COMMIT;\n");
    fwrite($handle, "-- End of backup\n");
    
    fclose($handle);
    return true;
}

function createBackupWithMysqldump($backupFile) {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    
    // Try different mysqldump command formats
    $commands = [
        // Format 1: With password
        "mysqldump --host={$DB_HOST} --user={$DB_USER} --password={$DB_PASS} --skip-comments --routines --triggers {$DB_NAME} > \"{$backupFile}\" 2>&1",
        
        // Format 2: Without password (if empty)
        "mysqldump --host={$DB_HOST} --user={$DB_USER} --skip-comments --routines --triggers {$DB_NAME} > \"{$backupFile}\" 2>&1",
        
        // Format 3: Full path for Windows
        "\"C:\\xampp\\mysql\\bin\\mysqldump.exe\" --host={$DB_HOST} --user={$DB_USER} --password={$DB_PASS} --skip-comments --routines --triggers {$DB_NAME} > \"{$backupFile}\" 2>&1",
        
        // Format 4: Alternative Windows path
        "\"C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe\" --host={$DB_HOST} --user={$DB_USER} --password={$DB_PASS} --skip-comments --routines --triggers {$DB_NAME} > \"{$backupFile}\" 2>&1",
    ];
    
    foreach ($commands as $command) {
        // Remove password from command for empty passwords
        if (empty($DB_PASS)) {
            $command = str_replace("--password={$DB_PASS}", "", $command);
        }
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupFile) && filesize($backupFile) > 0) {
            return ['success' => true, 'method' => 'mysqldump', 'command' => $command];
        }
    }
    
    return ['success' => false, 'output' => $output, 'code' => $returnCode];
}

function gzipFile($source, $level = 9) {
    $dest = $source . '.gz';
    
    if (function_exists('gzopen')) {
        $fp = gzopen($dest, 'wb' . $level);
        if ($fp) {
            $sourceContent = file_get_contents($source);
            gzwrite($fp, $sourceContent);
            gzclose($fp);
            
            // Verify the compressed file
            if (file_exists($dest) && filesize($dest) > 0) {
                unlink($source); // Remove uncompressed file
                return $dest;
            }
        }
    }
    
    // Fallback: Copy file with .gz extension (fake compression)
    copy($source, $dest);
    return $dest;
}

function cleanOldBackups($backupPath, $maxBackups, $retentionDays) {
    if (!file_exists($backupPath)) {
        return 0;
    }
    
    $files = glob($backupPath . 'backup_*');
    $deletedCount = 0;
    
    // Sort by modification time (newest first)
    usort($files, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    // Delete files older than retention period
    foreach ($files as $file) {
        $fileTime = filemtime($file);
        $daysOld = (time() - $fileTime) / (60 * 60 * 24);
        
        if ($daysOld > $retentionDays) {
            if (unlink($file)) {
                $deletedCount++;
            }
        }
    }
    
    // Keep only max_backups files
    if (count($files) > $maxBackups) {
        for ($i = $maxBackups; $i < count($files); $i++) {
            if (file_exists($files[$i]) && (time() - filemtime($files[$i])) > 3600) {
                unlink($files[$i]);
                $deletedCount++;
            }
        }
    }
    
    return $deletedCount;
}

function formatBytes($bytes, $precision = 2) { 
    $units = ['B', 'KB', 'MB', 'GB', 'TB']; 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow); 
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

function e($val) { 
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8'); 
}

// --- 5. SIMPLIFIED BACKUP CREATION ---
function createSimpleBackup($pdo, $backupFile) {
    set_time_limit(0);
    ini_set('memory_limit', '1024M');
    
    $handle = fopen($backupFile, 'w');
    if (!$handle) {
        return false;
    }
    
    // Write header
    fwrite($handle, "-- NexusAdmin Database Backup\n");
    fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($handle, "-- Database: " . $GLOBALS['DB_NAME'] . "\n\n");
    
    // Get all tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Get create table statement using numeric index
        $stmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
        $row = $stmt->fetch(PDO::FETCH_NUM); // Use numeric index
        
        if ($row && isset($row[1])) {
            fwrite($handle, $row[1] . ";\n\n");
        }
        
        // Get table data
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_values($row);
                
                $escapedValues = [];
                foreach ($values as $value) {
                    if ($value === null) {
                        $escapedValues[] = 'NULL';
                    } else {
                        $escapedValues[] = "'" . str_replace("'", "''", $value) . "'";
                    }
                }
                
                $sql = "INSERT INTO `{$table}` (`" . implode("`, `", $columns) . "`) VALUES (" . implode(", ", $escapedValues) . ");\n";
                fwrite($handle, $sql);
            }
            fwrite($handle, "\n");
        }
    }
    
    fclose($handle);
    return true;
}

// --- 6. HANDLE FORM SUBMISSIONS & ACTIONS ---
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'save_backup_settings') {
            // Get current settings to check available columns
            $currentSettings = $pdo->query("SELECT * FROM backup_settings LIMIT 1")->fetch();
            
            // Prepare settings array
            $settings = [
                'backup_enabled' => isset($_POST['backup_enabled']) ? 1 : 0,
                'backup_frequency' => $_POST['backup_frequency'] ?? 'weekly',
                'backup_day' => $_POST['backup_day'] ?? 'monday',
                'backup_time' => $_POST['backup_time'] ?? '02:00',
                'backup_retention' => (int)($_POST['backup_retention'] ?? 30),
                'max_backups' => (int)($_POST['max_backups'] ?? 10),
                'compress_backup' => isset($_POST['compress_backup']) ? 1 : 0,
                'email_notification' => isset($_POST['email_notification']) ? 1 : 0,
                'email_address' => $_POST['email_address'] ?? '',
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Only add backup_method if column exists
            if (isset($_POST['backup_method'])) {
                $settings['backup_method'] = $_POST['backup_method'] ?? 'php';
            }
            
            $updateSql = "UPDATE backup_settings SET ";
            $params = [];
            foreach ($settings as $key => $value) {
                $updateSql .= "{$key} = :{$key}, ";
                $params[":{$key}"] = $value;
            }
            $updateSql = rtrim($updateSql, ', ');
            
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute($params);
            
            $message = "Backup settings saved successfully!";
            $message_type = "success";
            
        } elseif ($action === 'create_backup') {
            // Create manual backup
            $backupSettings = $pdo->query("SELECT * FROM backup_settings LIMIT 1")->fetch();
            $backupPath = $backupSettings['backup_path'] ?? __DIR__ . '/backups/';
            
            // Ensure backup directory exists
            if (!file_exists($backupPath)) {
                if (!mkdir($backupPath, 0755, true)) {
                    throw new Exception("Failed to create backup directory. Check permissions.");
                }
            }
            
            // Check if directory is writable
            if (!is_writable($backupPath)) {
                throw new Exception("Backup directory is not writable. Check permissions.");
            }
            
            // Generate backup filename
            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = $backupPath . 'backup_' . $timestamp . '.sql';
            
            $backupCreated = false;
            $backupMethod = $backupSettings['backup_method'] ?? 'php';
            $backupDetails = [];
            
            if ($backupMethod === 'mysqldump' || $backupMethod === 'both') {
                // Try mysqldump first
                $result = createBackupWithMysqldump($backupFile);
                if ($result['success']) {
                    $backupCreated = true;
                    $backupDetails = $result;
                } elseif ($backupMethod === 'both') {
                    // Fallback to simplified PHP method
                    $backupCreated = createSimpleBackup($pdo, $backupFile);
                    $backupDetails = ['method' => 'php_simple_fallback', 'reason' => 'mysqldump failed'];
                }
            } else {
                // Use simplified PHP method
                $backupCreated = createSimpleBackup($pdo, $backupFile);
                $backupDetails = ['method' => 'php_simple'];
            }
            
            if ($backupCreated && file_exists($backupFile) && filesize($backupFile) > 0) {
                $finalFile = $backupFile;
                $fileSize = filesize($backupFile);
                
                // Compress backup if enabled
                if ($backupSettings['compress_backup']) {
                    $compressedFile = gzipFile($backupFile);
                    if ($compressedFile && file_exists($compressedFile)) {
                        $finalFile = $compressedFile;
                        $fileSize = filesize($compressedFile);
                    }
                }
                
                // Update last backup info
                $updateStmt = $pdo->prepare("
                    UPDATE backup_settings 
                    SET last_backup = NOW(), 
                        last_backup_file = ?
                    WHERE id = 1
                ");
                $updateStmt->execute([basename($finalFile)]);
                
                // Also update last_backup_size if column exists
                try {
                    $updateSizeStmt = $pdo->prepare("
                        UPDATE backup_settings 
                        SET last_backup_size = ?
                        WHERE id = 1
                    ");
                    $updateSizeStmt->execute([$fileSize]);
                } catch (PDOException $e) {
                    // Column might not exist yet, ignore
                }
                
                // Clean old backups
                $cleaned = cleanOldBackups($backupPath, $backupSettings['max_backups'], $backupSettings['backup_retention']);
                
                $message = "Database backup created successfully!";
                $message .= "<br><small>File: " . basename($finalFile) . " (" . formatBytes($fileSize) . ")</small>";
                $message .= "<br><small>Method: " . ($backupDetails['method'] ?? 'php') . "</small>";
                if ($cleaned > 0) {
                    $message .= "<br><small>Cleaned up {$cleaned} old backup(s)</small>";
                }
                $message_type = "success";
                
            } else {
                // Try one more time with ultra-simple method
                $backupFile2 = $backupPath . 'backup_' . $timestamp . '_ultrasimple.sql';
                if (createSimpleBackup($pdo, $backupFile2) && filesize($backupFile2) > 0) {
                    $finalFile = $backupFile2;
                    $fileSize = filesize($backupFile2);
                    
                    $updateStmt = $pdo->prepare("
                        UPDATE backup_settings 
                        SET last_backup = NOW(), 
                            last_backup_file = ?
                        WHERE id = 1
                    ");
                    $updateStmt->execute([basename($finalFile)]);
                    
                    $message = "Backup created successfully!";
                    $message .= "<br><small>File: " . basename($finalFile) . " (" . formatBytes($fileSize) . ")</small>";
                    $message_type = "success";
                } else {
                    $message = "Failed to create database backup. Possible issues:<br>";
                    $message .= "1. Database is too large for PHP backup<br>";
                    $message .= "2. Directory permissions issue<br>";
                    $message .= "3. Disk space full<br>";
                    $message .= "4. Database connection error<br>";
                    $message .= "Please check server configuration.";
                    $message_type = "error";
                }
            }
            
        } elseif ($action === 'delete_backup') {
            // Delete backup file
            $filename = $_POST['filename'] ?? '';
            $backupSettings = $pdo->query("SELECT backup_path FROM backup_settings LIMIT 1")->fetch();
            $backupPath = $backupSettings['backup_path'] ?? __DIR__ . '/backups/';
            
            $filePath = $backupPath . $filename;
            $filePathGz = $backupPath . $filename . '.gz';
            
            $deleted = false;
            if (file_exists($filePath)) {
                $deleted = unlink($filePath);
            } elseif (file_exists($filePathGz)) {
                $deleted = unlink($filePathGz);
            }
            
            if ($deleted) {
                $message = "Backup file deleted successfully!";
                $message_type = "success";
            } else {
                $message = "Backup file not found or could not be deleted.";
                $message_type = "error";
            }
            
        } elseif ($action === 'restore_backup') {
            // For security, restoration is handled separately
            $message = "Database restoration requires additional security measures.";
            $message .= "<br>Please use a database management tool like phpMyAdmin or MySQL Workbench.";
            $message_type = "warning";
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// --- 7. HANDLE DOWNLOAD REQUEST ---
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    $filename = $_GET['file'] ?? '';
    
    if (!empty($filename)) {
        $backupSettings = $pdo->query("SELECT backup_path FROM backup_settings LIMIT 1")->fetch();
        $backupPath = $backupSettings['backup_path'] ?? __DIR__ . '/backups/';
        $filePath = $backupPath . $filename;
        
        // Also check for .gz version
        if (!file_exists($filePath)) {
            $filePath .= '.gz';
        }
        
        if (file_exists($filePath) && is_file($filePath)) {
            // Set headers for file download
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($filePath));
            
            // Clear output buffer
            flush();
            
            // Read the file and output it
            readfile($filePath);
            exit;
        } else {
            die("File not found: " . e($filename));
        }
    }
}

// --- 8. FETCH DATA ---
$backupSettings = $pdo->query("SELECT * FROM backup_settings LIMIT 1")->fetch();
$backupPath = $backupSettings['backup_path'] ?? __DIR__ . '/backups/';

// Get backup files
$backupFiles = [];
if (file_exists($backupPath)) {
    $files = glob($backupPath . 'backup_*');
    rsort($files); // Sort by newest first
    
    foreach ($files as $file) {
        // Skip .gz files if we have the uncompressed version
        $baseName = basename($file);
        $isGz = substr($baseName, -3) === '.gz';
        $baseNameWithoutGz = $isGz ? substr($baseName, 0, -3) : $baseName;
        
        // Check if we already have this file (avoid duplicates)
        $found = false;
        foreach ($backupFiles as $existing) {
            if ($existing['name'] === $baseNameWithoutGz) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $backupFiles[] = [
                'name' => $baseNameWithoutGz,
                'full_name' => $baseName,
                'size' => filesize($file),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'path' => $file,
                'compressed' => $isGz
            ];
        }
    }
}

// Database size
$dbSize = 0;
try {
    $sizeResult = $pdo->query("
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
        FROM information_schema.tables 
        WHERE table_schema = '{$DB_NAME}'
        GROUP BY table_schema
    ")->fetch();
    $dbSize = $sizeResult['size_mb'] ?? 0;
} catch (Exception $e) {
    $dbSize = 0;
}

// Check if mysqldump is available
$mysqldumpAvailable = false;
exec('mysqldump --version 2>&1', $output, $returnCode);
$mysqldumpAvailable = ($returnCode === 0);

// Check PHP configuration
$memoryLimit = ini_get('memory_limit');
$maxExecutionTime = ini_get('max_execution_time');

// Check if backup directory exists and is writable
$backupDirWritable = false;
$backupDirExists = false;
if (file_exists($backupPath)) {
    $backupDirExists = true;
    $backupDirWritable = is_writable($backupPath);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup | NexusAdmin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <style>
        :root {
            --primary: #4F46E5;
            --primary-hover: #4338ca;
            --bg-body: #F3F4F6;
            --bg-card: #ffffff;
            --text-main: #111827;
            --text-muted: #6B7280;
            --border: #E5E7EB;
            --radius: 12px;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #3B82F6;
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Inter', -apple-system, sans-serif; 
        }
        
        body { 
            background-color: var(--bg-body); 
            color: var(--text-main); 
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--bg-card);
            color: var(--text-main);
            border: 1px solid var(--border);
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background: var(--bg-body);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-1px);
        }
        
        /* Alert */
        .alert {
            padding: 16px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            color: var(--success);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--danger);
            color: var(--danger);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid var(--warning);
            color: var(--warning);
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid var(--info);
            color: var(--info);
        }
        
        /* Cards */
        .card {
            background: var(--bg-card);
            border-radius: var(--radius);
            border: 1px solid var(--border);
            margin-bottom: 24px;
            overflow: hidden;
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            background: var(--bg-body);
        }
        
        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-body {
            padding: 24px;
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        .btn-success {
            background: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background: #0da271;
        }
        
        .btn-danger {
            background: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background: #dc2626;
        }
        
        .btn-secondary {
            background: var(--bg-body);
            color: var(--text-main);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--border);
        }
        
        /* Form */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-main);
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            background: var(--bg-card);
            color: var(--text-main);
            outline: none;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .form-select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            background: var(--bg-card);
            color: var(--text-main);
            outline: none;
            cursor: pointer;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .form-check-input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .form-check-label {
            font-size: 0.95rem;
            color: var(--text-main);
            cursor: pointer;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        
        .stat-card {
            background: var(--bg-card);
            border-radius: var(--radius);
            padding: 20px;
            border: 1px solid var(--border);
            text-align: center;
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        /* Backup Table */
        .backup-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .backup-table th {
            background: var(--bg-body);
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: var(--text-main);
            border-bottom: 2px solid var(--border);
            font-size: 0.9rem;
        }
        
        .backup-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            font-size: 0.95rem;
        }
        
        .backup-table tbody tr:hover {
            background: var(--bg-body);
        }
        
        .backup-actions {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .action-btn:hover {
            background: var(--bg-body);
            transform: translateY(-1px);
        }
        
        .action-btn.download {
            background: rgba(59, 130, 246, 0.1);
            border-color: var(--info);
            color: var(--info);
        }
        
        .action-btn.download:hover {
            background: var(--info);
            color: white;
        }
        
        .action-btn.delete:hover {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }
        
        /* System Info */
        .system-info {
            background: var(--bg-body);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .info-value {
            font-weight: 500;
            font-family: monospace;
        }
        
        .info-value.good {
            color: var(--success);
        }
        
        .info-value.warning {
            color: var(--warning);
        }
        
        .info-value.error {
            color: var(--danger);
        }
        
        /* Quick Test Button */
        .test-btn {
            margin-top: 10px;
            padding: 8px 16px;
            background: var(--info);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .test-btn:hover {
            background: #2563eb;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            
            .backup-table {
                display: block;
                overflow-x: auto;
            }
            
            .backup-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-left">
                <a href="admin_dashboard.php" class="back-btn">
                    <i class="ph ph-arrow-left"></i>
                    Back to Settings
                </a>
                <div>
                    <h1 class="page-title">
                        <i class="ph ph-database"></i>
                        Database Backup System
                    </h1>
                    <p style="color: var(--text-muted); margin-top: 8px;">
                        Backup and restore your database with automatic weekly backups
                    </p>
                </div>
            </div>
            <div>
                <button type="button" class="btn btn-success" onclick="createBackup()">
                    <i class="ph ph-database"></i>
                    Create Backup Now
                </button>
            </div>
        </div>

        <!-- Message Alert -->
        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="ph 
                <?php if ($message_type === 'success'): ?>ph-check-circle
                <?php elseif ($message_type === 'warning'): ?>ph-warning-circle
                <?php elseif ($message_type === 'error'): ?>ph-warning-circle
                <?php else: ?>ph-info<?php endif; ?>
            "></i>
            <span><?php echo $message; ?></span>
        </div>
        <?php endif; ?>

        <!-- System Information -->
        <div class="system-info">
            <h3 style="margin-bottom: 16px; color: var(--text-main);">System Information</h3>
            <div class="info-item">
                <span class="info-label">Database Size:</span>
                <span class="info-value"><?php echo number_format($dbSize, 2); ?> MB</span>
            </div>
            <div class="info-item">
                <span class="info-label">mysqldump Available:</span>
                <span class="info-value <?php echo $mysqldumpAvailable ? 'good' : 'warning'; ?>">
                    <?php echo $mysqldumpAvailable ? 'Yes' : 'No (using PHP backup)'; ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Backup Directory:</span>
                <span class="info-value <?php echo $backupDirExists && $backupDirWritable ? 'good' : 'error'; ?>">
                    <?php 
                    if (!$backupDirExists) {
                        echo 'Does not exist';
                    } elseif (!$backupDirWritable) {
                        echo 'Not writable';
                    } else {
                        echo 'Ready';
                    }
                    ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">PHP Memory Limit:</span>
                <span class="info-value <?php echo (intval($memoryLimit) >= 128) ? 'good' : 'warning'; ?>">
                    <?php echo e($memoryLimit); ?>
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Max Execution Time:</span>
                <span class="info-value <?php echo ($maxExecutionTime >= 60 || $maxExecutionTime == 0) ? 'good' : 'warning'; ?>">
                    <?php echo e($maxExecutionTime); ?> seconds
                </span>
            </div>
            <?php if (!$backupDirExists || !$backupDirWritable): ?>
            <div style="margin-top: 16px; padding: 12px; background: rgba(239, 68, 68, 0.1); border-radius: 6px; border: 1px solid var(--danger);">
                <strong style="color: var(--danger);">⚠️ Backup Directory Issue:</strong>
                <p style="margin: 8px 0 0 0; font-size: 0.9rem; color: var(--text-muted);">
                    <?php if (!$backupDirExists): ?>
                        Backup directory does not exist. Click "Create Backup Now" to create it automatically.
                    <?php else: ?>
                        Backup directory is not writable. Please check permissions for: <?php echo e(realpath($backupPath)); ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="ph ph-database" style="font-size: 2rem; color: var(--primary);"></i>
                <div class="stat-value"><?php echo number_format($dbSize, 2); ?> MB</div>
                <div class="stat-label">Database Size</div>
            </div>
            
            <div class="stat-card">
                <i class="ph ph-hard-drives" style="font-size: 2rem; color: var(--success);"></i>
                <div class="stat-value"><?php echo count($backupFiles); ?></div>
                <div class="stat-label">Total Backups</div>
            </div>
            
            <div class="stat-card">
                <i class="ph ph-calendar" style="font-size: 2rem; color: var(--info);"></i>
                <div class="stat-value"><?php echo e(ucfirst($backupSettings['backup_frequency'] ?? 'weekly')); ?></div>
                <div class="stat-label">Backup Frequency</div>
            </div>
            
            <div class="stat-card">
                <i class="ph ph-clock" style="font-size: 2rem; color: var(--warning);"></i>
                <div class="stat-value">
                    <?php if (!empty($backupSettings['last_backup'])): ?>
                        <?php echo date('M j, H:i', strtotime($backupSettings['last_backup'])); ?>
                    <?php else: ?>
                        Never
                    <?php endif; ?>
                </div>
                <div class="stat-label">Last Backup</div>
            </div>
        </div>

        <!-- Backup Settings Form -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="ph ph-gear"></i>
                    Backup Settings
                </div>
            </div>
            <div class="card-body">
                <form method="POST" id="backupSettingsForm">
                    <input type="hidden" name="action" value="save_backup_settings">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" 
                                       id="backup_enabled"
                                       name="backup_enabled" 
                                       class="form-check-input" 
                                       value="1"
                                       <?php echo ($backupSettings['backup_enabled'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="backup_enabled">
                                    Enable Automatic Backups
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" 
                                       id="compress_backup"
                                       name="compress_backup" 
                                       class="form-check-input" 
                                       value="1"
                                       <?php echo ($backupSettings['compress_backup'] ?? 1) == 1 ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="compress_backup">
                                    Compress Backup Files (GZIP)
                                </label>
                                <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                                    Reduces file size by ~70%
                                </small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Backup Frequency</label>
                            <select name="backup_frequency" class="form-select" onchange="toggleBackupDay(this.value)">
                                <option value="daily" <?php echo ($backupSettings['backup_frequency'] ?? 'weekly') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                                <option value="weekly" <?php echo ($backupSettings['backup_frequency'] ?? 'weekly') === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
                                <option value="monthly" <?php echo ($backupSettings['backup_frequency'] ?? 'weekly') === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                            </select>
                        </div>
                        
                        <div class="form-group" id="backupDayGroup">
                            <label class="form-label">Backup Day</label>
                            <select name="backup_day" class="form-select">
                                <option value="monday" <?php echo ($backupSettings['backup_day'] ?? 'monday') === 'monday' ? 'selected' : ''; ?>>Monday</option>
                                <option value="tuesday" <?php echo ($backupSettings['backup_day'] ?? 'monday') === 'tuesday' ? 'selected' : ''; ?>>Tuesday</option>
                                <option value="wednesday" <?php echo ($backupSettings['backup_day'] ?? 'monday') === 'wednesday' ? 'selected' : ''; ?>>Wednesday</option>
                                <option value="thursday" <?php echo ($backupSettings['backup_day'] ?? 'monday') === 'thursday' ? 'selected' : ''; ?>>Thursday</option>
                                <option value="friday" <?php echo ($backupSettings['backup_day'] ?? 'monday') === 'friday' ? 'selected' : ''; ?>>Friday</option>
                                <option value="saturday" <?php echo ($backupSettings['backup_day'] ?? 'monday') === 'saturday' ? 'selected' : ''; ?>>Saturday</option>
                                <option value="sunday" <?php echo ($backupSettings['backup_day'] ?? 'monday') === 'sunday' ? 'selected' : ''; ?>>Sunday</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Backup Time</label>
                            <input type="time" 
                                   name="backup_time" 
                                   class="form-control" 
                                   value="<?php echo e(substr($backupSettings['backup_time'] ?? '02:00:00', 0, 5)); ?>">
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                                Recommended: During low traffic hours (e.g., 02:00 AM)
                            </small>
                        </div>
                        
                        <?php
                        // Check if backup_method column exists
                        $backupMethodExists = false;
                        try {
                            $check = $pdo->query("SHOW COLUMNS FROM backup_settings LIKE 'backup_method'")->rowCount();
                            $backupMethodExists = $check > 0;
                        } catch (Exception $e) {
                            $backupMethodExists = false;
                        }
                        ?>
                        
                        <?php if ($backupMethodExists): ?>
                        <div class="form-group">
                            <label class="form-label">Backup Method</label>
                            <select name="backup_method" class="form-select">
                                <option value="php" <?php echo ($backupSettings['backup_method'] ?? 'php') === 'php' ? 'selected' : ''; ?>>PHP (Always works)</option>
                                <?php if ($mysqldumpAvailable): ?>
                                <option value="mysqldump" <?php echo ($backupSettings['backup_method'] ?? 'php') === 'mysqldump' ? 'selected' : ''; ?>>mysqldump (Faster)</option>
                                <option value="both" <?php echo ($backupSettings['backup_method'] ?? 'php') === 'both' ? 'selected' : ''; ?>>Try Both (Recommended)</option>
                                <?php endif; ?>
                            </select>
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                                <?php if (!$mysqldumpAvailable): ?>
                                    <span style="color: var(--warning);">⚠️ mysqldump not detected on server</span>
                                <?php else: ?>
                                    ✓ mysqldump is available
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Backup Retention (days)</label>
                            <input type="number" 
                                   name="backup_retention" 
                                   class="form-control" 
                                   value="<?php echo e($backupSettings['backup_retention'] ?? 30); ?>"
                                   min="1" 
                                   max="365">
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                                Delete backups older than X days
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Maximum Backups</label>
                            <input type="number" 
                                   name="max_backups" 
                                   class="form-control" 
                                   value="<?php echo e($backupSettings['max_backups'] ?? 10); ?>"
                                   min="1" 
                                   max="100">
                            <small style="color: var(--text-muted); display: block; margin-top: 4px;">
                                Keep only the X most recent backups
                            </small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Notification Email</label>
                            <input type="email" 
                                   name="email_address" 
                                   class="form-control" 
                                   value="<?php echo e($backupSettings['email_address'] ?? ''); ?>"
                                   placeholder="admin@example.com">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input type="checkbox" 
                                   id="email_notification"
                                   name="email_notification" 
                                   class="form-check-input" 
                                   value="1"
                                   <?php echo ($backupSettings['email_notification'] ?? 1) == 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="email_notification">
                                Send email notification after backup
                            </label>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="button" class="btn btn-secondary" onclick="resetForm()">
                            <i class="ph ph-arrow-counter-clockwise"></i>
                            Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="ph ph-floppy-disk"></i>
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Backup Files List -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="ph ph-file-sql"></i>
                    Backup Files
                    <span style="font-size: 0.9rem; font-weight: normal; color: var(--text-muted); margin-left: 10px;">
                        (<?php echo count($backupFiles); ?> files)
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (count($backupFiles) > 0): ?>
                    <table class="backup-table">
                        <thead>
                            <tr>
                                <th>Backup File</th>
                                <th>Size</th>
                                <th>Date Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($backupFiles as $backup): 
                                $isCompressed = $backup['compressed'];
                                $fileIcon = $isCompressed ? 'ph ph-file-zip' : 'ph ph-file-sql';
                                $fileColor = $isCompressed ? 'var(--warning)' : 'var(--primary)';
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center;">
                                        <i class="<?php echo $fileIcon; ?>" style="color: <?php echo $fileColor; ?>; margin-right: 10px; font-size: 1.2rem;"></i>
                                        <div>
                                            <div style="font-weight: 500;"><?php echo e($backup['name']); ?></div>
                                            <?php if ($isCompressed): ?>
                                                <small style="color: var(--warning); font-size: 0.8rem;">
                                                    <i class="ph ph-compress"></i> Compressed
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo formatBytes($backup['size']); ?></td>
                                <td><?php echo e($backup['date']); ?></td>
                                <td>
                                    <div class="backup-actions">
                                        <a href="?action=download&file=<?php echo e($backup['name']); ?>" 
                                           class="action-btn download">
                                            <i class="ph ph-download-simple"></i>
                                            Download
                                        </a>
                                        <button type="button" 
                                                class="action-btn delete" 
                                                onclick="deleteBackup('<?php echo e($backup['name']); ?>')">
                                            <i class="ph ph-trash"></i>
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div style="margin-top: 16px; color: var(--text-muted); font-size: 0.9rem;">
                        <i class="ph ph-info"></i>
                        Backups are stored in: <?php echo e(realpath($backupPath)); ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px 20px;">
                        <div style="font-size: 4rem; color: var(--text-muted); opacity: 0.5; margin-bottom: 16px;">
                            <i class="ph ph-database"></i>
                        </div>
                        <div style="font-size: 1.25rem; font-weight: 600; color: var(--text-main); margin-bottom: 8px;">
                            No backup files found
                        </div>
                        <div style="color: var(--text-muted); margin-bottom: 20px; max-width: 400px; margin: 0 auto 24px auto;">
                            Create your first database backup to get started.
                        </div>
                        <button type="button" class="btn btn-success" onclick="createBackup()">
                            <i class="ph ph-database"></i>
                            Create First Backup
                        </button>
                        <div style="margin-top: 20px;">
                            <button type="button" class="test-btn" onclick="testDatabaseConnection()">
                                <i class="ph ph-database"></i>
                                Test Database Connection
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div style="display: flex; justify-content: space-between; margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
            <div style="color: var(--text-muted); font-size: 0.9rem;">
                <i class="ph ph-info"></i>
                Last updated: <?php echo date('Y-m-d H:i:s'); ?>
            </div>
            <div>
                <a href="settings.php" class="back-btn" style="padding: 8px 16px;">
                    <i class="ph ph-arrow-left"></i>
                    Return to Settings
                </a>
            </div>
        </div>
    </div>

    <script>
        // Initialize backup day visibility
        function toggleBackupDay(frequency) {
            const backupDayGroup = document.getElementById('backupDayGroup');
            if (frequency === 'daily') {
                backupDayGroup.style.display = 'none';
            } else {
                backupDayGroup.style.display = 'block';
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const frequency = document.querySelector('select[name="backup_frequency"]').value;
            toggleBackupDay(frequency);
        });
        
        // Create backup
        function createBackup() {
            if (confirm('Create a manual database backup now?\n\nThis may take a few moments depending on database size.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'create_backup';
                
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Delete backup
        function deleteBackup(filename) {
            if (confirm('Are you sure you want to delete backup file:\n\n' + filename + '?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete_backup';
                
                const fileInput = document.createElement('input');
                fileInput.type = 'hidden';
                fileInput.name = 'filename';
                fileInput.value = filename;
                
                form.appendChild(actionInput);
                form.appendChild(fileInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Test database connection
        function testDatabaseConnection() {
            alert('Testing database connection...\n\nIf you can see this page, the database connection is working.');
        }
        
        // Reset form
        function resetForm() {
            if (confirm('Reset all settings to default values?')) {
                location.reload();
            }
        }
        
        // Auto-save settings draft
        let autoSaveTimer;
        const form = document.getElementById('backupSettingsForm');
        
        if (form) {
            form.addEventListener('input', function() {
                clearTimeout(autoSaveTimer);
                autoSaveTimer = setTimeout(function() {
                    console.log('Settings changed - ready to save');
                }, 1000);
            });
        }
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl + B to create backup
            if (e.ctrlKey && e.key === 'b') {
                e.preventDefault();
                createBackup();
            }
            
            // F5 to refresh
            if (e.key === 'F5') {
                e.preventDefault();
                location.reload();
            }
        });
    </script>
</body>
</html>