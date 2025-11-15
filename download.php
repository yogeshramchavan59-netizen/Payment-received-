<?php
// download.php
require_once __DIR__ . '/init_db.php';
$config = require __DIR__ . '/config.php';

$token = $_GET['token'] ?? null;
if (!$token) {
    http_response_code(400);
    echo 'token required';
    exit;
}

$db = new PDO('sqlite:' . $config['sqlite_db']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// get token
$stmt = $db->prepare("SELECT file_path, expires_at FROM tokens WHERE token = ?");
$stmt->execute([$token]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(403);
    echo 'invalid or used token';
    exit;
}
if (time() > (int)$row['expires_at']) {
    // remove token
    $stmt = $db->prepare("DELETE FROM tokens WHERE token = ?");
    $stmt->execute([$token]);
    http_response_code(403);
    echo 'token expired';
    exit;
}

// One-time use: delete now
$stmt = $db->prepare("DELETE FROM tokens WHERE token = ?");
$stmt->execute([$token]);

$filePath = $row['file_path'];
if (!file_exists($filePath)) {
    http_response_code(404);
    echo 'file not found';
    exit;
}

// send headers and stream file
$basename = basename($filePath);
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$basename.'"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);
exit;
