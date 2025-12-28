<?php
$dsn='mysql:host=127.0.0.1;port=3307;charset=utf8mb4';
try {
    $pdo = new PDO($dsn, 'root', 'rootpass');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "OK\n";
} catch (PDOException $e) {
    echo "ERR: " . $e->getMessage() . "\n";
}
