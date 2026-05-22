<?php
// config/database.php
require_once __DIR__ . '/error_handler.php';

if (!function_exists('load_env_file')) {
     function load_env_file($path) {
          if (!is_file($path) || !is_readable($path)) {
               return;
          }

          $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
          foreach ($lines as $line) {
               $line = trim($line);
               if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                    continue;
               }

               [$name, $value] = array_map('trim', explode('=', $line, 2));
               if ($name === '' || getenv($name) !== false) {
                    continue;
               }

               $value = trim($value, "\"'");
               putenv($name . '=' . $value);
               $_ENV[$name] = $value;
               $_SERVER[$name] = $value;
          }
     }
}

load_env_file(__DIR__ . '/../.env');

$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'splennet';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = getenv('DB_CHARSET') ?: 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
     PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
     PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
     PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     error_log('Database connection failed: ' . $e->getMessage());
     http_response_code(500);
     exit('Service unavailable.');
}
?>
