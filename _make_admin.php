<?php
// ONE-TIME ADMIN SETUP — DELETE THIS FILE IMMEDIATELY AFTER RUNNING
require_once 'config/database.php';

$email    = 'skalu@splennet.com';
$password = 'DreamBig2020@$';
$hash     = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if user already exists
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing user to admin and reset password
        $pdo->prepare("UPDATE users SET role = 'admin', password_hash = ?, status = 'active' WHERE email = ?")
            ->execute([$hash, $email]);
        echo "<b>Updated:</b> " . htmlspecialchars($email) . " is now an admin with the new password.";
    } else {
        // Create new admin user
        $pdo->prepare("INSERT INTO users (email, password_hash, role, status) VALUES (?, ?, 'admin', 'active')")
            ->execute([$email, $hash]);
        echo "<b>Created:</b> Admin account for " . htmlspecialchars($email) . " is ready.";
    }

    echo "<br><br><strong style='color:red'>Delete this file (_make_admin.php) from Hostinger File Manager now.</strong>";
} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
