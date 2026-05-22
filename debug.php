<?php
// TEMPORARY DIAGNOSTIC FILE — DELETE AFTER USE
// Access at: https://splennet.com/debug.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<style>body{font-family:monospace;padding:20px;background:#111;color:#0f0;} .ok{color:#0f0;} .err{color:#f55;} .warn{color:#ff0;} h2{color:#fff;border-bottom:1px solid #333;padding-bottom:5px;}</style>';
echo '<h1 style="color:#fff">Splennet Diagnostic</h1>';

// 1. PHP Info
echo '<h2>PHP</h2>';
echo '<p>Version: <strong>' . phpversion() . '</strong>';
echo PHP_VERSION_ID >= 80000 ? ' <span class="ok">[OK - 8.0+]</span>' : ' <span class="err">[FAIL - need 8.0+]</span>';
echo '</p>';

// 2. .env loading
echo '<h2>.env File</h2>';
$envPath = __DIR__ . '/.env';
if (file_exists($envPath) && is_readable($envPath)) {
    echo '<p class="ok">.env found and readable.</p>';
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name] = explode('=', $line, 2);
        $name = trim($name);
        // Show key names but mask passwords
        if (stripos($name, 'pass') !== false || stripos($name, 'secret') !== false) {
            echo "<p>$name = <em>[hidden]</em></p>";
        } else {
            [$n, $v] = array_map('trim', explode('=', $line, 2));
            echo "<p>$n = $v</p>";
        }
    }
} else {
    echo '<p class="err">.env NOT FOUND at: ' . $envPath . '</p>';
    echo '<p class="err">Upload your .env file to the website root (same folder as index.php).</p>';
}

// 3. Database connection
echo '<h2>Database Connection</h2>';
$envVars = [];
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        $envVars[$name] = $value;
    }
}

$host    = $envVars['DB_HOST']    ?? 'localhost';
$db      = $envVars['DB_NAME']    ?? '';
$user    = $envVars['DB_USER']    ?? '';
$pass    = $envVars['DB_PASS']    ?? '';
$charset = $envVars['DB_CHARSET'] ?? 'utf8mb4';

echo "<p>Host: $host | DB: $db | User: $user</p>";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=$charset",
        $user, $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo '<p class="ok">Database connected successfully!</p>';

    // 4. Table check
    echo '<h2>Tables in Database</h2>';
    $required = [
        'users', 'brands', 'creators', 'creator_verifications',
        'campaigns', 'applications', 'jobs', 'submissions',
        'conversations', 'messages', 'notifications', 'payments',
        'reviews', 'support_tickets', 'revision_requests',
        'site_settings', 'activity_logs', 'usage_stats',
        'contests', 'contest_submissions', 'contest_rewards',
        'ugc_orders', 'ugc_order_submissions', 'disputes',
    ];
    $stmt = $pdo->query("SHOW TABLES");
    $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($required as $t) {
        $found = in_array($t, $existing);
        echo '<p ' . ($found ? 'class="ok"' : 'class="err"') . '>' . ($found ? '✓' : '✗') . " $t</p>";
    }

    // 5. Critical column check
    echo '<h2>Critical Columns</h2>';
    $checks = [
        ['users', 'status'],
        ['brands', 'subscription_tier'],
        ['creators', 'sample_video_link'],
        ['campaigns', 'is_featured'],
    ];
    foreach ($checks as [$table, $col]) {
        try {
            $r = $pdo->query("SELECT `$col` FROM `$table` LIMIT 0");
            echo '<p class="ok">✓ ' . $table . '.' . $col . '</p>';
        } catch (Exception $e) {
            echo '<p class="err">✗ ' . $table . '.' . $col . ' — MISSING</p>';
        }
    }

    // 6. site_settings seed data
    echo '<h2>Site Settings</h2>';
    try {
        $r = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $rows = $r->fetchAll();
        if (empty($rows)) {
            echo '<p class="warn">site_settings table is empty — seed data missing.</p>';
        } else {
            foreach ($rows as $row) {
                echo '<p class="ok">✓ ' . $row['setting_key'] . ' = ' . $row['setting_value'] . '</p>';
            }
        }
    } catch (Exception $e) {
        echo '<p class="err">site_settings query failed: ' . $e->getMessage() . '</p>';
    }

} catch (PDOException $e) {
    echo '<p class="err">CONNECTION FAILED: ' . $e->getMessage() . '</p>';
}

// 7. Session test
echo '<h2>Session</h2>';
if (session_status() === PHP_SESSION_NONE) session_start();
echo '<p>Session ID: ' . session_id() . '</p>';
echo '<p>Session data: <pre>' . print_r($_SESSION, true) . '</pre></p>';

echo '<hr><p style="color:#666">Delete this file (debug.php) once done diagnosing.</p>';
