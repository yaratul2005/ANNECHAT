<?php
$hash = password_hash('admin12345', PASSWORD_BCRYPT, ['cost' => 12]);
echo $hash . PHP_EOL;