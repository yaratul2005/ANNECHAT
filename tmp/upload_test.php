<?php
// Programmatic guest login and file upload test
$cookieFile = __DIR__ . '/cookies.txt';

// 1) Guest login
$loginUrl = 'http://127.0.0.1:8000/api/guest-login.php';
$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['username' => 'auto_guest_2','age' => 25]));
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($res === false || $httpCode !== 200) {
    echo "Login failed: HTTP {$httpCode}\n";
    echo "Response: " . ($res ?: curl_error($ch)) . "\n";
    exit(1);
}
curl_close($ch);
echo "Login response: {$res}\n";

// 2) Upload file
$uploadUrl = 'http://127.0.0.1:8000/api/upload.php';
$ch = curl_init($uploadUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$filePath = __DIR__ . '/test_upload.txt';
if (!file_exists($filePath)) {
    file_put_contents($filePath, "Test upload file\n");
}
$cfile = new CURLFile($filePath, 'text/plain', 'test_upload.txt');
$postFields = ['file' => $cfile];
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
if ($res === false) {
    echo "Upload request failed: " . curl_error($ch) . "\n";
    exit(1);
}
curl_close($ch);

echo "Upload HTTP code: {$httpCode}\n";
echo "Upload response: {$res}\n";

// Clean up
@unlink($cookieFile);
?>