<?php
// includes/functions.php
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $is_https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!defined('APP_URL')) {
    $app_url = getenv('APP_URL');
    if ($app_url === false || trim($app_url) === '') {
        $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
        $host = $_SERVER['HTTP_HOST'] ?? 'splennet.com';
        $app_url = ($is_https ? 'https' : 'http') . '://' . $host . '/';
    }
    if (preg_match('#^https?://#i', $app_url)) {
        define('APP_URL', rtrim($app_url, '/') . '/');
    } else {
        $app_url = '/' . trim($app_url, '/') . '/';
        define('APP_URL', $app_url === '//' ? '/' : $app_url);
    }
}

/**
 * Cache Manager: File-based persistent cache with in-memory TTL layer
 */
class CacheManager {
    private $cache_dir = __DIR__ . '/../.cache/';
    private $memory_cache = [];
    private $memory_ttl = [];

    public function __construct() {
        if (!is_dir($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }

    public function get($key) {
        $key = $this->sanitize_key($key);

        if (isset($this->memory_cache[$key]) && isset($this->memory_ttl[$key])) {
            if (time() < $this->memory_ttl[$key]) {
                return $this->memory_cache[$key];
            } else {
                unset($this->memory_cache[$key], $this->memory_ttl[$key]);
            }
        }

        $file = $this->cache_dir . $key . '.cache';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (isset($data['expires_at']) && time() < $data['expires_at']) {
                $this->memory_cache[$key] = $data['value'];
                $this->memory_ttl[$key] = $data['expires_at'];
                return $data['value'];
            } else {
                @unlink($file);
            }
        }

        return null;
    }

    public function set($key, $value, $ttl = 3600) {
        $key = $this->sanitize_key($key);
        $expires_at = time() + $ttl;

        $this->memory_cache[$key] = $value;
        $this->memory_ttl[$key] = $expires_at;

        $file = $this->cache_dir . $key . '.cache';
        $data = json_encode(['value' => $value, 'expires_at' => $expires_at]);
        file_put_contents($file, $data, LOCK_EX);

        return true;
    }

    public function delete($key) {
        $key = $this->sanitize_key($key);

        unset($this->memory_cache[$key], $this->memory_ttl[$key]);

        $file = $this->cache_dir . $key . '.cache';
        if (file_exists($file)) {
            @unlink($file);
        }

        return true;
    }

    public function flush() {
        $this->memory_cache = [];
        $this->memory_ttl = [];
        $files = glob($this->cache_dir . '*.cache');
        foreach ($files as $file) {
            @unlink($file);
        }
        return true;
    }

    private function sanitize_key($key) {
        return preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
    }
}

function e($text) {
    return htmlspecialchars($text, ENT_QUOTES, "UTF-8");
}

/**
 * Safely render HTML produced by a rich text editor (Quill, etc.).
 *
 * • Backward-compatible: stored plain-text (no HTML tags) is displayed
 *   with preserved line-breaks, exactly as before.
 * • Quill's "empty" state (<p><br></p>) returns an empty string.
 * • Real HTML is stripped to a safe allowlist — no scripts, no event
 *   handlers, no javascript: URLs.
 */
function safe_rich_html(string $html): string {
    if (empty($html)) return '';

    // Quill's empty state or pure whitespace → treat as empty
    if (trim(strip_tags($html)) === '') return '';

    // Backward-compat: plain-text records (no HTML tags) keep line-breaks
    if ($html === strip_tags($html)) {
        return nl2br(htmlspecialchars($html, ENT_QUOTES, 'UTF-8'));
    }

    // Allowlist: safe formatting tags only
    $allowed = '<p><br><strong><b><em><i><u><s><strike><ul><ol><li><h1><h2><h3><h4><blockquote><span><a>';
    $clean   = strip_tags($html, $allowed);

    // Strip event-handler attributes (onclick, onload, onerror …)
    $clean = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>\/]+)/i', '', $clean);

    // Neuter javascript: in href / src
    $clean = preg_replace('/\b(href|src)\s*=\s*["\']?\s*javascript:[^"\'>\s]*/i', '$1="#"', $clean);

    return $clean;
}

function require_creator_record($creator, $redirect = true) {
    if ($creator !== false && is_array($creator) && !empty($creator['id'])) {
        return true;
    }
    if ($redirect) {
        header("Location: " . APP_URL . "creator/profile.php?setup=1");
        exit();
    }
    return false;
}

function require_brand_record($brand, $redirect = true) {
    if ($brand !== false && is_array($brand) && !empty($brand['id'])) {
        return true;
    }
    if ($redirect) {
        header("Location: " . APP_URL . "brand/profile.php?setup=1");
        exit();
    }
    return false;
}

function require_role($role) {
    global $pdo;
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== $role) {
        header("Location: " . APP_URL . "login.php");
        exit();
    }
    
    // Check suspension status
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetchColumn() === 'suspended') {
        session_destroy();
        header("Location: " . APP_URL . "login.php?error=account_suspended");
        exit();
    }
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function redirect($path) {
    header("Location: " . APP_URL . $path);
    exit();
}

function check_profile_completion($creator) {
    $required = [
        'full_name', 'school', 'profile_photo', 'bio', 
        'main_niche', 'sample_video_link'
    ];
    $missing = [];
    foreach ($required as $field) {
        if (empty($creator[$field])) {
            $missing[] = $field;
        }
    }
    
    $total = count($required);
    $completed = $total - count($missing);
    $percent = round(($completed / $total) * 100);
    
    return [
        'percent' => $percent,
        'is_complete' => $percent >= 100,
        'missing' => $missing
    ];
}

function check_brand_profile_completion($brand) {
    $required = [
        'brand_name', 'contact_person', 'industry', 'website', 
        'phone', 'logo', 'country', 'city'
    ];
    $missing = [];
    foreach ($required as $field) {
        if (empty($brand[$field])) {
            $missing[] = $field;
        }
    }
    
    $total = count($required);
    $completed = $total - count($missing);
    $percent = round(($completed / $total) * 100);
    
    return [
        'percent' => $percent,
        'is_complete' => $percent >= 100,
        'missing' => $missing
    ];
}

function time_ago($timestamp) {
    if (!$timestamp) return "Never";
    $time = is_numeric($timestamp) ? $timestamp : strtotime($timestamp);
    $diff = time() - $time;
    
    if ($diff < 60) return "Just now";
    $units = [
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute'
    ];
    
    foreach ($units as $u => $s) {
        if ($diff < $u) continue;
        $n = floor($diff / $u);
        return $n . " " . $s . ($n > 1 ? "s" : "") . " ago";
    }
}

/**
 * Super Admin: Platform Settings Retrieval
 */
function get_setting($key, $default = "") {
    global $pdo;
    static $settings = null;
    if ($settings === null) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $settings[$key] ?? $default;
}

/**
 * Brand Quota Management
 */
function check_brand_quota($brand_id) {
    global $pdo;
    
    // Get brand tier and limits
    $stmt = $pdo->prepare("SELECT subscription_tier FROM brands WHERE id = ?");
    $stmt->execute([$brand_id]);
    $tier = $stmt->fetchColumn() ?: "basic";
    
    $limit_key = ($tier === "basic") ? "basic_monthly_limit" : "pro_monthly_limit";
    $limit = (int)get_setting($limit_key, 3);
    
    // Count campaigns created this month (integer comparisons avoid collation issues)
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM campaigns WHERE brand_id = ? AND YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())");
    $stmt_count->execute([$brand_id]);
    $current_usage = (int)$stmt_count->fetchColumn();
    
    return [
        "limit" => $limit,
        "used" => $current_usage,
        "remaining" => max(0, $limit - $current_usage),
        "tier" => $tier,
        "can_create" => ($current_usage < $limit)
    ];
}

/**
 * Activity Logger
 */
function log_activity($user_id, $action, $details = "") {
    global $pdo;
    $ip = $_SERVER["REMOTE_ADDR"] ?? "unknown";
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $details, $ip]);
}

/**
 * Remove an uploaded file safely when an admin deletes the associated record.
 */
function delete_uploaded_file_path($path) {
    if (!$path) {
        return false;
    }

    $relative = trim((string)$path);
    if ($relative === '') {
        return false;
    }

    $relative = str_replace(APP_URL, '', $relative);
    $relative = ltrim($relative, '/\\');
    $absolute = realpath(__DIR__ . '/../' . $relative);

    if ($absolute && file_exists($absolute) && is_file($absolute)) {
        return @unlink($absolute);
    }

    return false;
}

/**
 * Send Notification
 */
function create_notification($user_id, $title, $message, $type = "system", $target_url = null, $target_type = null, $target_id = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, target_url, target_type, target_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $title, $message, $type, $target_url, $target_type, $target_id]);
}

/**
 * Check whether a table has a specific column.
 */
function table_has_column($table, $column) {
    global $pdo;
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $stmt->execute([$table, $column]);
        $cache[$key] = ((int)$stmt->fetchColumn()) > 0;
    } catch (Exception $e) {
        $cache[$key] = false;
    }

    return $cache[$key];
}

/**
 * Currency minimums by code. Same numbers used by server validation and exposed to JS for live form hints.
 */
function minimum_payments() {
    return [
        "NGN" => 5000,
        "GHS" => 50,
        "USD" => 5,
        "EUR" => 5,
        "GBP" => 5,
        "CAD" => 5,
        "AUD" => 5,
    ];
}

/**
 * Validate Minimum Payment Based on Currency
 */
function validate_minimum_payment($amount, $currency = "USD") {
    $amount = (float)$amount;
    $minimums = minimum_payments();
    $min = $minimums[$currency] ?? 5;

    if ($amount < $min) {
        return "Minimum payment for {$currency} is {$min}. You entered {$amount}.";
    }
    return true;
}

/**
 * Map a country name to its default payout currency.
 * Returns NGN for Nigeria, GHS for Ghana, USD for all others.
 * Used during registration and as a fallback on the earnings page.
 */
function country_to_currency(string $country): string {
    $c = strtolower(trim($country));
    if (str_contains($c, 'nigeria')) return 'NGN';
    if (str_contains($c, 'ghana'))   return 'GHS';
    return 'USD'; // default for any unsupported / unknown country
}

/**
 * Currency: supported list, symbols, and admin-set exchange rates.
 * Rates are stored as "1 USD = X target currency" in site_settings (key: fx_rate_<CCY>).
 * USD is the canonical base. Edit rates at admin/settings.php.
 */
function supported_currencies() {
    return ["NGN", "GHS", "USD", "EUR", "GBP"];
}

function get_currency_symbol($currency) {
    $symbols = [
        "NGN" => "₦",
        "GHS" => "₵",
        "USD" => "$",
        "EUR" => "€",
        "GBP" => "£",
        "CAD" => "C$",
        "AUD" => "A$",
    ];
    return $symbols[$currency] ?? ($currency . " ");
}

/**
 * Exchange rate vs USD. fx_rate_USD is always 1.
 * Defaults are sensible fallbacks if admin hasn't set anything yet.
 */
function get_exchange_rate($currency) {
    $currency = strtoupper($currency);
    if ($currency === "USD") return 1.0;

    $defaults = [
        "NGN" => 1500.0,
        "GHS" => 12.0,
        "EUR" => 0.92,
        "GBP" => 0.78,
        "CAD" => 1.36,
        "AUD" => 1.50,
    ];

    $stored = get_setting("fx_rate_" . $currency, null);
    if ($stored !== null && (float)$stored > 0) {
        return (float)$stored;
    }
    return $defaults[$currency] ?? 1.0;
}

/**
 * Convert amount between currencies using USD as the bridge.
 */
function convert_currency($amount, $from, $to) {
    $from = strtoupper($from);
    $to = strtoupper($to);
    if ($from === $to) return (float)$amount;

    $usd_amount = (float)$amount / get_exchange_rate($from); // to USD
    return $usd_amount * get_exchange_rate($to);             // to target
}

/**
 * Format an amount with the right symbol and decimals.
 * NGN/GHS typically displayed without cents; USD/EUR/GBP with cents.
 */
function format_money($amount, $currency) {
    $currency = strtoupper($currency);
    $symbol = get_currency_symbol($currency);
    $decimals = in_array($currency, ["NGN", "GHS"]) ? 0 : 2;
    return $symbol . number_format((float)$amount, $decimals);
}

/**
 * Alias of format_money() for backward compatibility.
 */
function format_currency($amount, $currency) {
    return format_money($amount, $currency);
}

/**
 * Detect if FFmpeg is on the server's PATH. Cached per request.
 */
function ffmpeg_available() {
    static $available = null;
    if ($available !== null) return $available;

    $cmd = stripos(PHP_OS, 'WIN') === 0 ? 'where ffmpeg 2>NUL' : 'command -v ffmpeg 2>/dev/null';
    $out = @shell_exec($cmd);
    $available = !empty(trim((string)$out));
    return $available;
}

/**
 * Generate a watermarked preview copy of a video. Falls back to a plain copy
 * (so the brand-side overlay badge still indicates "preview only") if FFmpeg is missing.
 *
 * @param string $input_abs   Absolute path to the source video
 * @param string $output_abs  Absolute path where the watermarked copy should be written
 * @param string|null $text   Watermark text. Defaults to admin setting or "SPLENNET PREVIEW".
 * @return array ['ok' => bool, 'method' => 'ffmpeg'|'copy', 'error' => string|null]
 */
function generate_video_watermark($input_abs, $output_abs, $text = null) {
    if (!file_exists($input_abs)) {
        return ['ok' => false, 'method' => null, 'error' => 'Input file not found'];
    }

    $text = $text ?: get_setting('watermark_text', 'SPLENNET PREVIEW');
    // Escape for FFmpeg's drawtext filter: backslash, colon, single quote, comma
    $safe_text = str_replace(
        ['\\', ':', "'", ','],
        ['\\\\', '\\:', "\\'", '\\,'],
        $text
    );

    if (ffmpeg_available()) {
        // Tile two diagonal watermarks across the video — hard to crop out, easy to read.
        $filter = sprintf(
            "drawtext=text='%s':fontcolor=white@0.55:fontsize=h/16:box=1:boxcolor=black@0.35:boxborderw=8:x=(w-text_w)/2:y=h/4," .
            "drawtext=text='%s':fontcolor=white@0.55:fontsize=h/16:box=1:boxcolor=black@0.35:boxborderw=8:x=(w-text_w)/2:y=h*3/4",
            $safe_text, $safe_text
        );

        $cmd = sprintf(
            'ffmpeg -y -i %s -vf %s -codec:a copy %s 2>&1',
            escapeshellarg($input_abs),
            escapeshellarg($filter),
            escapeshellarg($output_abs)
        );

        $output = [];
        $code = 0;
        @exec($cmd, $output, $code);

        if ($code === 0 && file_exists($output_abs) && filesize($output_abs) > 0) {
            return ['ok' => true, 'method' => 'ffmpeg', 'error' => null];
        }
        // FFmpeg failed — fall through to copy fallback so the upload still succeeds.
        $ffmpeg_err = implode("\n", array_slice($output, -5));
    }

    // Fallback: copy the file. The brand UI still shows the "Watermarked" badge overlay,
    // so creators are still gated behind approval — but install FFmpeg for real protection.
    if (@copy($input_abs, $output_abs)) {
        return ['ok' => true, 'method' => 'copy', 'error' => isset($ffmpeg_err) ? $ffmpeg_err : null];
    }
    return ['ok' => false, 'method' => null, 'error' => 'Failed to write preview file'];
}

/**
 * Batch Notification Creation
 */
function create_notification_batch($user_ids, $title, $message, $type = "system", $target_url = "", $target_type = "", $target_id = 0) {
    global $pdo;

    if (empty($user_ids)) return false;

    $placeholders = implode(',', array_fill(0, count($user_ids), '(?, ?, ?, ?, ?, ?, ?)'));
    $sql = "INSERT INTO notifications (user_id, title, message, type, target_url, target_type, target_id) VALUES {$placeholders}";

    $params = [];
    foreach ($user_ids as $uid) {
        $params[] = $uid;
        $params[] = $title;
        $params[] = $message;
        $params[] = $type;
        $params[] = $target_url;
        $params[] = $target_type;
        $params[] = $target_id;
    }

    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Get Contest Status (live, closed, announcement_pending, announced)
 */
function get_contest_status($contest_id) {
    global $pdo;

    $stmt = $pdo->prepare("SELECT submission_deadline, winner_announcement_date, status FROM contests WHERE id = ?");
    $stmt->execute([$contest_id]);
    $contest = $stmt->fetch();

    if (!$contest) return ['current_status' => 'not_found'];

    $now = date('Y-m-d H:i:s');
    $submission_deadline = $contest['submission_deadline'] . ' 23:59:59';
    $announcement_date = $contest['winner_announcement_date'] . ' 00:00:00';

    if ($now < $submission_deadline) {
        return ['current_status' => 'live', 'deadline' => $contest['submission_deadline']];
    } elseif ($now < $announcement_date) {
        return ['current_status' => 'closed', 'announcement_date' => $contest['winner_announcement_date']];
    } else {
        return ['current_status' => 'announced', 'announced_at' => $contest['winner_announcement_date']];
    }
}

/**
 * Get Contest Board Data - Sorted by filter
 */
function get_contest_board_data($contest_id, $sort = 'newest') {
    global $pdo;

    $valid_sorts = ['newest', 'most_viewed', 'most_engaged', 'top_ranked', 'shortlisted', 'winners'];
    $sort = in_array($sort, $valid_sorts) ? $sort : 'newest';

    $where = "cs.contest_id = ? AND cs.status IN ('submitted', 'shortlisted', 'winner')";

    if ($sort === 'shortlisted') {
        $where .= " AND cs.status = 'shortlisted'";
    } elseif ($sort === 'winners') {
        $where .= " AND cs.status = 'winner'";
    }

    $order_by = match($sort) {
        'most_viewed' => 'cs.view_count DESC, cs.submitted_at DESC',
        'most_engaged' => 'cs.engagement_count DESC, cs.submitted_at DESC',
        'top_ranked' => 'CASE WHEN cs.status = "winner" THEN 0 WHEN cs.status = "shortlisted" THEN 1 ELSE 2 END, cs.view_count DESC',
        'shortlisted', 'winners' => 'cs.submitted_at DESC',
        default => 'cs.submitted_at DESC',
    };

    $sql = "
        SELECT
            cs.*,
            cr.full_name,
            cr.school,
            cr.profile_photo,
            c.title as contest_title,
            b.brand_name
        FROM contest_submissions cs
        JOIN creators cr ON cs.creator_id = cr.id
        JOIN contests c ON cs.contest_id = c.id
        JOIN brands b ON c.brand_id = b.id
        WHERE {$where}
        ORDER BY {$order_by}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$contest_id]);
    return $stmt->fetchAll();
}

/**
 * Get Brand's UGC Orders
 */
function get_brand_ugc_orders($brand_id, $status = null) {
    global $pdo;

    $sql = "SELECT * FROM ugc_orders WHERE brand_id = ?";
    $params = [$brand_id];

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get Creator's Contest Submissions
 */
function get_creator_contest_submissions($creator_id, $contest_id = null) {
    global $pdo;

    $sql = "
        SELECT
            cs.*,
            c.title as contest_title,
            c.submission_deadline,
            b.brand_name
        FROM contest_submissions cs
        JOIN contests c ON cs.contest_id = c.id
        JOIN brands b ON c.brand_id = b.id
        WHERE cs.creator_id = ?
    ";
    $params = [$creator_id];

    if ($contest_id) {
        $sql .= " AND cs.contest_id = ?";
        $params[] = $contest_id;
    }

    $sql .= " ORDER BY cs.submitted_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get Creator's UGC Order Submissions
 */
function get_creator_ugc_submissions($creator_id, $ugc_order_id = null) {
    global $pdo;

    $sql = "
        SELECT
            us.*,
            uo.title as order_title,
            uo.deadline,
            b.brand_name
        FROM ugc_order_submissions us
        JOIN ugc_orders uo ON us.ugc_order_id = uo.id
        JOIN brands b ON uo.brand_id = b.id
        WHERE us.creator_id = ?
    ";
    $params = [$creator_id];

    if ($ugc_order_id) {
        $sql .= " AND us.ugc_order_id = ?";
        $params[] = $ugc_order_id;
    }

    $sql .= " ORDER BY us.submitted_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Calculate CPM Payment
 * Formula: (approved_views / 1000) * pay_per_1000_views
 * Capped at max_payable_views per creator
 */
function calculate_cpm_payment($view_count, $pay_per_1000_views, $max_payable_views = null) {
    $view_count = max(0, (int)$view_count);
    $pay_per_1000_views = (float)$pay_per_1000_views;

    if ($max_payable_views !== null) {
        $view_count = min($view_count, (int)$max_payable_views);
    }

    if ($view_count === 0 || $pay_per_1000_views === 0) {
        return 0;
    }

    return round(($view_count / 1000) * $pay_per_1000_views, 2);
}

/**
 * Get Contest Rewards
 */
function get_contest_rewards($contest_id) {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT * FROM contest_rewards
        WHERE contest_id = ?
        ORDER BY position_number ASC
    ");
    $stmt->execute([$contest_id]);
    return $stmt->fetchAll();
}

/**
 * Check if Creator Can Submit to Contest
 */
function can_creator_submit_contest($creator_id, $contest_id) {
    global $pdo;

    // Verification is optional — unverified creators can still submit

    // Check contest deadline
    $stmt = $pdo->prepare("SELECT submission_deadline FROM contests WHERE id = ?");
    $stmt->execute([$contest_id]);
    $deadline = $stmt->fetchColumn();

    if (!$deadline || strtotime($deadline . ' 23:59:59') < time()) {
        return ['can_submit' => false, 'reason' => 'deadline_passed'];
    }

    // Check if already submitted (MVP: one submission per creator per contest)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM contest_submissions
        WHERE contest_id = ? AND creator_id = ?
    ");
    $stmt->execute([$contest_id, $creator_id]);
    $submission_count = (int)$stmt->fetchColumn();

    if ($submission_count > 0) {
        return ['can_submit' => false, 'reason' => 'already_submitted'];
    }

    return ['can_submit' => true];
}

/**
 * Check if Creator Can Submit to UGC Order
 */
function can_creator_submit_ugc($creator_id, $ugc_order_id) {
    global $pdo;

    // Verification is optional — unverified creators can still submit

    // Check UGC order deadline
    $stmt = $pdo->prepare("SELECT deadline FROM ugc_orders WHERE id = ?");
    $stmt->execute([$ugc_order_id]);
    $deadline = $stmt->fetchColumn();

    if (!$deadline || strtotime($deadline . ' 23:59:59') < time()) {
        return ['can_submit' => false, 'reason' => 'deadline_passed'];
    }

    return ['can_submit' => true];
}

/**
 * Get Recent Notifications (cached)
 */
function get_recent_notifications($user_id, $limit = 10) {
    global $pdo;
    $cache = new CacheManager();
    $cache_key = "notifications_user_{$user_id}_{$limit}";
    $ttl = 60;

    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $stmt = $pdo->prepare("
        SELECT * FROM notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$user_id, $limit]);
    $result = $stmt->fetchAll();

    $cache->set($cache_key, $result, $ttl);
    return $result;
}

/**
 * Invalidate Notification Cache
 */
function invalidate_notifications_cache($user_id) {
    $cache = new CacheManager();
    $cache->delete("notifications_user_{$user_id}_4");
    $cache->delete("notifications_user_{$user_id}_10");
}
?>
