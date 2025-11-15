<?php
// init_db.php
$config = require __DIR__ . '/config.php';
$dbFile = $config['sqlite_db'];
$dir = dirname($dbFile);
if (!is_dir($dir)) mkdir($dir, 0755, true);

if (!file_exists($dbFile)) {
    $db = new PDO('sqlite:' . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // orders: store razorpay order id -> fileId, amount, createdAt
    $db->exec("CREATE TABLE orders (
        order_id TEXT PRIMARY KEY,
        file_id TEXT,
        amount INTEGER,
        receipt TEXT,
        created_at INTEGER
    )");
    // tokens: one-time download tokens
    $db->exec("CREATE TABLE tokens (
        token TEXT PRIMARY KEY,
        file_path TEXT,
        expires_at INTEGER
    )");
    // optional: files table (map fileId -> filename)
    $db->exec("CREATE TABLE files (
        file_id TEXT PRIMARY KEY,
        filename TEXT
    )");
    // Example rows - update or use upload flow
    $stmt = $db->prepare("INSERT INTO files (file_id, filename) VALUES (?, ?)");
    $stmt->execute(['banner1', 'banner1-clean.png']);
    $stmt->execute(['banner2', 'banner2-clean.jpg']);
}
