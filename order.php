<?php
// order.php
header('Content-Type: application/json');
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/init_db.php';

$input = json_decode(file_get_contents('php://input'), true);
$fileId = $input['fileId'] ?? null;
$amount = isset($input['amount']) ? (int)$input['amount'] : 100; // paise

if (!$fileId) {
    http_response_code(400);
    echo json_encode(['error'=>'fileId required']);
    exit;
}

// verify file exists in DB
$db = new PDO('sqlite:' . $config['sqlite_db']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $db->prepare("SELECT filename FROM files WHERE file_id = ?");
$stmt->execute([$fileId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['error'=>'file not found']);
    exit;
}

$receipt = 'rcpt_' . bin2hex(random_bytes(8));

// create order with Razorpay API (server-side)
$payload = [
    'amount' => $amount,
    'currency' => 'INR',
    'receipt' => $receipt,
    'payment_capture' => 1
];

$ch = curl_init('https://api.razorpay.com/v1/orders');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_USERPWD, $config['rzp_key_id'] . ':' . $config['rzp_key_secret']);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode !== 200 && $httpcode !== 201) {
    http_response_code(500);
    echo json_encode(['error'=>'razorpay-order-failed', 'raw'=>$response]);
    exit;
}
$order = json_decode($response, true);

// save order -> file mapping into DB
$stmt = $db->prepare("INSERT INTO orders (order_id, file_id, amount, receipt, created_at) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([$order['id'], $fileId, $order['amount'], $receipt, time()]);

// return order to client
echo json_encode([
    'success' => true,
    'orderId' => $order['id'],
    'amount' => $order['amount'],
    'currency' => $order['currency'],
    'rzpKeyId' => $config['rzp_key_id']
]);
