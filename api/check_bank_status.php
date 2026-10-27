<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged() || !$db) {
    json_response(false, 'Chưa đăng nhập');
}

$user = current_user();
$lastId = (int)($_GET['last_id'] ?? 0);

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
            'tx_id'      => $tx['id'],
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
    json_response(false, 'Lỗi kiểm tra giao dịch: ' . $e->getMessage());
}
