<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/config/Config.php';
require_once __DIR__ . '/../src/config/Database.php';

use App\Config\Config;
use App\Config\Database;
use App\Services\SessionService;
use App\Models\User;

Config::load();
$pdo = Database::getInstance();

$username = $argv[1] ?? 'admin';
$stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
$stmt->execute([$username]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    echo "User not found: {$username}\n";
    exit(1);
}
$userId = (int)$row['id'];

$sessionService = new SessionService();
$sid = $sessionService->create($userId);
echo "Created session for user {$username} (id={$userId}) session_id={$sid}\n";
