<?php
require_once __DIR__ . '/../public_html/bootstrap.php';

use App\Config\Database;
use App\Models\Block;

$pdo = Database::getInstance();
$pdo->beginTransaction();
try {
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, is_verified, is_admin, is_guest, created_at) VALUES (?, ?, ?, ?, 0, 0, ?)');
    $pwd = password_hash('testpass', PASSWORD_BCRYPT, ['cost' => 10]);
    $stmt->execute(['tmpA_' . uniqid(), uniqid() . '@example.com', $pwd, 1, $now]);
    $idA = (int)$pdo->lastInsertId();
    $stmt->execute(['tmpB_' . uniqid(), uniqid() . '@example.com', $pwd, 1, $now]);
    $idB = (int)$pdo->lastInsertId();

    $block = new Block();
        echo "Checking isBlocked first\n";
        var_dump($block->isBlocked($idA, $idB));
        echo "Attempting block($idA, $idB)\n";
        $ok = $block->block($idA, $idB);
    var_dump($ok);

    $pdo->rollBack();
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . PHP_EOL;
    $pdo->rollBack();
}
