<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

function momo_status_response(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_logged()) {
    momo_status_response(401, ['success' => false, 'message' => 'Vui lòng đăng nhập.']);
}
$orderId = trim((string)($_GET['order_id'] ?? ''));
if ($orderId === '' || !preg_match('/^MOMO[A-Z0-9]+$/', $orderId)) {
    momo_status_response(422, ['success' => false, 'message' => 'Mã giao dịch không hợp lệ.']);
}

$pdo = Database::getInstance();
$stmt = $pdo->prepare('SELECT order_id, amount, status, created_at FROM momo_transactions WHERE order_id = ? AND user_id = ? LIMIT 1');
$stmt->execute([$orderId, (int)$_SESSION['user_id']]);
$transaction = $stmt->fetch();
if (!$transaction) {
    momo_status_response(404, ['success' => false, 'message' => 'Không tìm thấy giao dịch.']);
}

momo_status_response(200, [
    'success' => true,
    'order_id' => $transaction['order_id'],
    'amount' => (int)$transaction['amount'],
    'status' => $transaction['status'],
]);
