<?php
// verify.php
header('Content-Type: application/json');
$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/init_db.php';

$input = json_decode(file_get_contents('php://input'), true);
$razorpay_payment_id = $input['razorpay_payment_id'] ?? null;
$razorpay_order_id = $input['razorpay_order_id'] ?? null;
$razorpay_signature = $input['razorpay_signature'] ?? null;

if (!$razorpay_payment_id || !$razorpay_order_id || !$razorpay_signature) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'missing params']);
    exit;
}

// verify signature
$generated_signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $config['rzp_key_secret']);
if (!hash_equals($generated_signature, $razorpay_signature)) {
    http_response_code(403);
    echo json_encode(['success'=>false, 'message'=>'invalid signature']);
    exit;
}

// optional: further verify payment status by fetching payment details from Razorpay API
$ch = curl_init('https://api.razorpay.com/v1/payments/' . $razorpay_payment_id);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $config['rzp_key_id'] . ':' . $config['rzp_key_secret']);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$paymentInfo = $resp ? json_decode($resp, true) : null;
if (!$paymentInfo || ($paymentInfo['status'] ?? '') !== 'captured') {
    // payment not captured yet
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>'payment not captured']);
    exit;
}

// find order and file
$db = new PDO('sqlite:' . $config['sqlite_db']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $db->prepare("SELECT file_id FROM orders WHERE order_id = ?");
$stmt->execute([$razorpay_order_id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) {
    http_response_code(404);
    echo json_encode(['success'=>false, 'message'=>'order not found']);
    exit;
}
$fileId = $row['file_id'];
$stmt = $db->prepare("SELECT filename FROM files WHERE file_id = ?");
$stmt->execute([$fileId]);
$fileRow = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$fileRow) {
    http_response_code(404);
    echo json_encode(['success'=>false, 'message'=>'file not found']);
    exit;
}

$filename = $fileRow['filename'];
$filePath = $config['protected_dir'] . '/' . $filename;
if (!file_exists($filePath)) {
    http_response_code(404);
    echo json_encode(['success'=>false, 'message'=>'file missing']);
    exit;
}

// create one-time token
$token = bin2hex(random_bytes(16));
$expires = time() + $config['download_ttl'];

// insert into tokens DB
$stmt = $db->prepare("INSERT INTO tokens (token, file_path, expires_at) VALUES (?, ?, ?)");
$stmt->execute([$token, $filePath, $expires]);

// return token to client
echo json_encode(['success'=>true, 'downloadToken'=>$token, 'expiresIn'=>$config['download_ttl']]);
