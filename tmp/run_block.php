<?php
require_once __DIR__ . '/../public_html/bootstrap.php';

use App\Models\Block;

$block = new Block();
try {
    $ok = $block->block(1, 2);
    echo "block returned: " . ($ok ? 'true' : 'false') . PHP_EOL;
} catch (PDOException $e) {
    echo "PDOException: " . $e->getMessage() . PHP_EOL;
    echo $e->getTraceAsString() . PHP_EOL;
}
