<?php
require_once 'bootstrap.php';

use App\Services\AuthService;

$authService = new AuthService();
$authService->logout();

header('Location: index.php');
exit;

