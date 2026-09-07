<?php
// config.php — GymPro Central Configuration & Database Bootstrap

// Show errors during setup to diagnose any hosting issues (remove once working)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 1. Safe Environment Variable Loader (works even if putenv() or getenv() is disabled)
$env_vars = [];
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $k = trim($k);
            $v = trim(trim($v), '"\'');
            $env_vars[$k] = $v;
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
            @putenv("$k=$v");
        }
    }
}

function env($key, $default = '') {
    global $env_vars;
    if (isset($env_vars[$key])) return $env_vars[$key];
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    $val = @getenv($key);
    return ($val !== false && $val !== '') ? $val : $default;
}

// 2. Database Connection
$host = env('DB_HOST', 'localhost');
$user = env('DB_USER', 'root');
$pass = env('DB_PASS', '');
$db   = env('DB_NAME', 'gym_db');
$port = (int)env('DB_PORT', 3306);

@mysqli_report(MYSQLI_REPORT_OFF);
try {
    $conn = @new mysqli($host, $user, $pass, $db, $port);
    if ($conn->connect_error) {
        die('<div style="font-family:sans-serif;max-width:600px;margin:50px auto;padding:25px;border:1px solid #f5c6cb;background:#f8d7da;color:#721c24;border-radius:8px;">'
            . '<h2 style="margin-top:0;">Database Connection Failed</h2>'
            . '<p><strong>Error:</strong> ' . htmlspecialchars($conn->connect_error) . '</p>'
            . '<p>Host attempted: <code>' . htmlspecialchars($host) . '</code></p>'
            . '<p>User attempted: <code>' . htmlspecialchars($user) . '</code></p>'
            . '<p>Database: <code>' . htmlspecialchars($db) . '</code></p>'
            . '<p>Please ensure your <code>.env</code> file is in <code>htdocs/</code> and has the correct InfinityFree credentials.</p>'
            . '</div>');
    }
} catch (Throwable $e) {
    die('<div style="font-family:sans-serif;max-width:600px;margin:50px auto;padding:25px;border:1px solid #f5c6cb;background:#f8d7da;color:#721c24;border-radius:8px;">'
        . '<h2 style="margin-top:0;">Database Error</h2>'
        . '<p>' . htmlspecialchars($e->getMessage()) . '</p>'
        . '</div>');
}

// Set standard UTF-8 charset
@$conn->set_charset('utf8mb4');

// 3. Hardened Session Settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.use_only_cookies', 1);

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// 4. System Settings Table & Helper Functions
@$conn->query("CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(60) PRIMARY KEY,
    `setting_value` TEXT,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

/**
 * Retrieve a system setting from DB, falling back to environment variable, then default.
 */
function get_setting($key, $default = '') {
    global $conn;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = @$conn->query("SELECT setting_key, setting_value FROM system_settings");
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $cache[$r['setting_key']] = $r['setting_value'];
            }
        }
    }
    if (isset($cache[$key]) && $cache[$key] !== '') {
        return $cache[$key];
    }
    return env($key, $default);
}

/**
 * Update or insert a system setting in the database.
 */
function update_setting($key, $value) {
    global $conn;
    $st = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    if ($st) {
        $st->bind_param('ss', $key, $value);
        $ok = $st->execute();
        $st->close();
        return $ok;
    }
    return false;
}
