<?php
// TEMPORARY DIAGNOSTIC FILE — DELETE AFTER USE
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo '<style>body{font-family:monospace;padding:20px;background:#111;color:#0f0;} .ok{color:#0f0;} .err{color:#f55;} .warn{color:#ff0;} h2{color:#fff;border-bottom:1px solid #333;padding-bottom:5px;} pre{background:#1a1a1a;padding:10px;border-radius:4px;white-space:pre-wrap;word-break:break-all;}</style>';
echo '<h1 style="color:#fff">Splennet Diagnostic</h1>';

// ── 1. Error log captured from error_handler ──
echo '<h2>Last Errors (from _error_log.txt)</h2>';
$logFile = __DIR__ . '/_error_log.txt';
if (file_exists($logFile)) {
    $contents = file_get_contents($logFile);
    echo '<pre class="err">' . htmlspecialchars($contents ?: '(empty)') . '</pre>';
    echo '<p class="warn">← This is the EXACT error causing the Service Error page.</p>';
} else {
    echo '<p class="warn">No errors logged yet. Visit the brand dashboard first, then refresh this page.</p>';
}

// ── 2. DB connection ──
echo '<h2>Database</h2>';
$envPath = __DIR__ . '/.env';
$envVars = [];
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$n, $v] = array_map('trim', explode('=', $line, 2));
        $envVars[$n] = trim($v, "\"'");
    }
}
try {
    $pdo = new PDO(
        "mysql:host={$envVars['DB_HOST']};dbname={$envVars['DB_NAME']};charset=utf8mb4",
        $envVars['DB_USER'], $envVars['DB_PASS'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo '<p class="ok">Connected to ' . $envVars['DB_NAME'] . '</p>';
} catch (PDOException $e) {
    die('<p class="err">DB FAILED: ' . $e->getMessage() . '</p>');
}

// ── 3. Find brand users ──
echo '<h2>Brand Users in Database</h2>';
$brands = $pdo->query("SELECT u.id as user_id, u.email, u.status, b.id as brand_id, b.brand_name FROM users u LEFT JOIN brands b ON b.user_id = u.id WHERE u.role = 'brand'")->fetchAll();
if (empty($brands)) {
    echo '<p class="warn">No brand users found in the database. Has a brand account been registered?</p>';
} else {
    foreach ($brands as $b) {
        echo '<p class="ok">user_id=' . $b['user_id'] . ' | email=' . $b['email'] . ' | status=' . $b['status'] . ' | brand_id=' . ($b['brand_id'] ?? 'NULL — NO BRANDS RECORD!') . ' | brand_name=' . ($b['brand_name'] ?? 'NULL') . '</p>';
    }
}

// ── 4. Simulate brand dashboard queries for first brand ──
echo '<h2>Brand Dashboard Query Simulation</h2>';
if (!empty($brands)) {
    $testBrand = $brands[0];
    $brand_id  = $testBrand['brand_id'];
    echo '<p>Testing with brand_id=' . ($brand_id ?? 'NULL') . ' (user: ' . $testBrand['email'] . ')</p>';

    $queries = [
        'Active campaigns'     => "SELECT COUNT(*) FROM campaigns WHERE brand_id = ? AND status = 'published'",
        'Pending applications' => "SELECT COUNT(*) FROM applications a JOIN campaigns c ON a.campaign_id = c.id WHERE c.brand_id = ? AND a.status = 'pending'",
        'Jobs awaiting review' => "SELECT COUNT(*) FROM jobs WHERE brand_id = ? AND status IN ('awaiting_review','draft_submitted')",
        'Total spent'          => "SELECT COALESCE(SUM(p.calculated_amount),0) FROM payments p JOIN jobs j ON p.job_id = j.id WHERE j.brand_id = ? AND p.status = 'completed'",
        'Creators hired'       => "SELECT COUNT(DISTINCT creator_id) FROM jobs WHERE brand_id = ? AND status IN ('approved','completed','in_progress')",
        'subscription_tier'    => "SELECT subscription_tier FROM brands WHERE id = ?",
        'site_settings'        => "SELECT setting_key, setting_value FROM site_settings LIMIT 5",
        'Recent applications'  => "SELECT a.id FROM applications a JOIN campaigns c ON a.campaign_id = c.id JOIN creators cr ON a.creator_id = cr.id WHERE c.brand_id = ? ORDER BY a.created_at DESC LIMIT 5",
        'Recent chats'         => "SELECT c.id FROM conversations c JOIN creators cr ON c.creator_id = cr.id WHERE c.brand_id = ? ORDER BY c.updated_at DESC LIMIT 3",
        'UGC orders count'     => "SELECT COUNT(*) FROM ugc_orders WHERE brand_id = ? AND status = 'published'",
        'UGC pending subs'     => "SELECT COUNT(*) FROM ugc_order_submissions us JOIN ugc_orders uo ON us.ugc_order_id = uo.id WHERE uo.brand_id = ? AND us.status = 'submitted'",
        'Contests live'        => "SELECT COUNT(*) FROM contests WHERE brand_id = ? AND status = 'live'",
        'Contest pending subs' => "SELECT COUNT(*) FROM contest_submissions cs JOIN contests c ON cs.contest_id = c.id WHERE c.brand_id = ? AND cs.status = 'submitted'",
    ];

    foreach ($queries as $label => $sql) {
        try {
            // site_settings doesn't need a bind param
            if ($label === 'site_settings') {
                $r = $pdo->query($sql)->fetchAll();
            } else {
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$brand_id]);
                $r = $stmt->fetchAll();
            }
            echo '<p class="ok">✓ ' . $label . ' — OK (' . count($r) . ' row(s))</p>';
        } catch (Throwable $e) {
            echo '<p class="err">✗ ' . $label . ' — FAILED: ' . $e->getMessage() . '</p>';
        }
    }
} else {
    echo '<p class="warn">No brand users to test with. Register a brand account first.</p>';
}

// ── 5. Session ──
echo '<h2>Current Session</h2>';
if (session_status() === PHP_SESSION_NONE) session_start();
echo '<pre>' . htmlspecialchars(print_r($_SESSION, true)) . '</pre>';

echo '<hr><p style="color:#555">Delete debug.php and _error_log.txt from Hostinger File Manager once done.</p>';
