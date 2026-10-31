<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged() || !$db) {
    json_response(false, 'Chưa đăng nhập');
}

$user = current_user();
$cursor = $_GET['last_id'] ?? '';
$lastId = $cursor === '' ? 0 : public_id_to_internal($db, 'bank_transactions', $cursor);
if ($cursor !== '' && $lastId === 0) { http_response_code(422); json_response(false, 'Mã giao dịch không hợp lệ.'); }

try {
    // Tìm giao dịch ngân hàng thành công gần nhất của user tạo trong 10 phút qua
    $stmt = $db->prepare("SELECT * FROM bank_transactions
                          WHERE user_id = ? AND status = 'success' AND id > ? AND created_at >= NOW() - INTERVAL 10 MINUTE
                          ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user['id'], $lastId]);
    $tx = $stmt->fetch();

    if ($tx) {
        json_response(true, 'Giao dịch thành công', [
            'has_new'    => true,
            'tx_id'      => $tx['public_id'],
            'amount'     => (float)$tx['amount'],
            'amount_fmt' => format_money($tx['amount']),
            'new_balance'=> format_money($user['balance'])
        ]);
    } else {
        json_response(true, 'Đang chờ giao dịch', [
            'has_new' => false
        ]);
    }
} catch (Exception $e) {
    json_response(false, 'Lỗi kiểm tra giao dịch: ' . public_error($e));
}
