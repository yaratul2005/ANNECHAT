<?php
$dsn = 'mysql:host=127.0.0.1;port=3307;dbname=anne_chat;charset=utf8mb4';
$user = 'root';
$pass = 'rootpass';
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $password = 'admin12345';
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $pdo->prepare('UPDATE users SET password_hash = ? , email = ? WHERE username = ?');
    $stmt->execute([$hash, 'ratul41g@gmail.com', 'admin']);
    echo "OK\n";
    echo "Stored hash (first 32 chars): " . substr($hash,0,32) . "\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
