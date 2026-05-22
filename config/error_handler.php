<?php
/**
 * Global Error Handler - Prevents blank screen on fatal errors
 * Include this at the very start of config/database.php
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display to browser (security)
ini_set('log_errors', 1);

// Custom error handler for non-fatal errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error
    error_log("Error: [$errno] $errstr in $errfile on line $errline");

    // Don't interrupt execution for warnings
    if ($errno === E_WARNING || $errno === E_NOTICE) {
        return true;
    }

    return false;
});

// Handler for fatal errors (MUST be registered via register_shutdown_function)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Log the fatal error
        error_log("Fatal Error: [{$error['type']}] {$error['message']} in {$error['file']} on line {$error['line']}");
        @file_put_contents(
            __DIR__ . '/../_error_log.txt',
            date('[Y-m-d H:i:s]') . " Fatal [{$error['type']}]: {$error['message']}\n  File: {$error['file']}\n  Line: {$error['line']}\n\n",
            FILE_APPEND
        );

        // Send error response with fallback UI
        header('Content-Type: text/html; charset=UTF-8');
        http_response_code(500);

        // Display user-friendly error page
        $homeUrl = defined('APP_URL') ? APP_URL : '/';
        echo '<script>window.__HOME_URL__ = ' . json_encode($homeUrl) . ';</script>';
        $errorId = date('YmdHis');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Error - Splennet</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; color: #333; }
        .container { text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; margin: 20px; }
        h1 { font-size: 2.5em; margin-bottom: 10px; color: #667eea; }
        p { font-size: 1.1em; color: #666; margin: 15px 0; line-height: 1.6; }
        .button { display: inline-block; margin-top: 30px; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; transition: background 0.3s; border: none; cursor: pointer; }
        .button:hover { background: #764ba2; }
        .error-code { font-size: 0.9em; color: #999; margin-top: 20px; font-family: 'Monaco', monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ Service Error</h1>
        <p>We're experiencing technical difficulties.</p>
        <p>Our team has been notified and is working to resolve this.</p>
        <button class="button" onclick="location.href=window.__HOME_URL__">Return to Home</button>
        <div class="error-code">Error ID: {$errorId}</div>
    </div>
</body>
</html>
HTML;
        exit(1);
    }
});

// Handler for uncaught exceptions
set_exception_handler(function($exception) {
    $msg = $exception->getMessage();
    $file = $exception->getFile();
    $line = $exception->getLine();
    error_log("Uncaught Exception: $msg in $file on line $line");
    // Write to a debug log the user can read via debug.php
    @file_put_contents(
        __DIR__ . '/../_error_log.txt',
        date('[Y-m-d H:i:s]') . " Exception: $msg\n  File: $file\n  Line: $line\n\n",
        FILE_APPEND
    );

    header('Content-Type: text/html; charset=UTF-8');
    http_response_code(500);

    $homeUrl = defined('APP_URL') ? APP_URL : '/';
    echo '<script>window.__HOME_URL__ = ' . json_encode($homeUrl) . ';</script>';

    $errorId = date('YmdHis');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Error - Splennet</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; color: #333; }
        .container { text-align: center; background: white; padding: 40px; border-radius: 12px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-width: 500px; margin: 20px; }
        h1 { font-size: 2.5em; margin-bottom: 10px; color: #667eea; }
        p { font-size: 1.1em; color: #666; margin: 15px 0; line-height: 1.6; }
        .button { display: inline-block; margin-top: 30px; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; transition: background 0.3s; border: none; cursor: pointer; }
        .button:hover { background: #764ba2; }
        .error-code { font-size: 0.9em; color: #999; margin-top: 20px; font-family: 'Monaco', monospace; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚠️ Service Error</h1>
        <p>We're experiencing technical difficulties.</p>
        <p>Our team has been notified and is working to resolve this.</p>
        <button class="button" onclick="location.href=window.__HOME_URL__">Return to Home</button>
        <div class="error-code">Error ID: {$errorId}</div>
    </div>
</body>
</html>
HTML;
    exit(1);
});
?>
