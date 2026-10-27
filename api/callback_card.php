<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Nhận dữ liệu Callback từ Cổng gạch thẻ (chấp nhận cả POST, GET hoặc Raw JSON)
$rawData = file_get_contents('php://input');
$json = json_decode($rawData, true) ?: [];

$data = !empty($json) ? $json : array_merge($_GET, $_POST);

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'No data received']);
    exit;
}

$statusApi     = (int)($data['status'] ?? 0);
$message       = trim($data['message'] ?? '');
$requestId     = trim($data['request_id'] ?? '');
$code          = trim($data['code'] ?? ($data['pin'] ?? ''));
$serial        = trim($data['serial'] ?? '');
$realAmount    = (int)($data['value'] ?? ($data['real_amount'] ?? 0));
$receivedAmount= (float)($data['amount'] ?? ($data['received_amount'] ?? 0));
$callbackSign  = trim($data['callback_sign'] ?? ($data['sign'] ?? ''));

$partnerKey = get_setting('card_partner_key', '');

// Kiểm tra chữ ký Callback nếu cổng có gửi kèm
if (!empty($partnerKey) && !empty($callbackSign)) {
    $expectedSign = md5($partnerKey . $code . $serial);
    if (!hash_equals($expectedSign, $callbackSign)) {
        http_response_code(403);
        echo json_encode(['status' => 403, 'message' => 'Invalid callback signature']);
        exit;
    }
}

if (!$db) {
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Tìm giao dịch thẻ tương ứng
    $stmt = $db->prepare("SELECT * FROM card_transactions WHERE request_id = ? OR (pin = ? AND serial = ?) LIMIT 1");
    $stmt->execute([$requestId, $code, $serial]);
    $card = $stmt->fetch();

    if (!$card) {
        http_response_code(404);
        echo json_encode(['status' => 404, 'message' => 'Transaction not found']);
        exit;
    }

    // Nếu thẻ đã được xử lý thành công trước đó (chống cộng tiền lặp)
    if ($card['status'] === 'success') {
        echo json_encode(['status' => 200, 'message' => 'Transaction already processed']);
        exit;
    }

    // THẺ ĐÚNG (status = 1) -> Cộng tiền cho User
    if ($statusApi === 1) {
        $db->beginTransaction();

        // Nếu cổng không trả received_amount, tự tính theo tỷ lệ 85% của realAmount
        if ($receivedAmount <= 0) {
            $receivedAmount = ($realAmount > 0 ? $realAmount : $card['declared_amount']) * 0.85;
        }

        // 1. Cộng tiền vào số dư & tổng nạp của User
        $userStmt = $db->prepare("UPDATE users SET balance = balance + ?, total_deposited = total_deposited + ? WHERE id = ?");
        $userStmt->execute([$receivedAmount, $receivedAmount, $card['user_id']]);

        // 2. Lấy số dư sau khi cộng để ghi balance_logs
        $balStmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
        $balStmt->execute([$card['user_id']]);
        $currentBalance = (float)$balStmt->fetchColumn();
        $balanceBefore = $currentBalance - $receivedAmount;

        // 3. Ghi nhật ký biến động số dư
        $logStmt = $db->prepare("INSERT INTO balance_logs (user_id, amount_before, amount, amount_after, type, note) VALUES (?, ?, ?, ?, 'deposit_card', ?)");
        $logStmt->execute([
            $card['user_id'],
            $balanceBefore,
            $receivedAmount,
            $currentBalance,
            "Nạp thẻ cào {$card['telco']} seri {$card['serial']} thành công"
        ]);

        // 4. Cập nhật trạng thái thẻ thành công
        $upCard = $db->prepare("UPDATE card_transactions SET status = 'success', real_amount = ?, received_amount = ?, response_message = ? WHERE id = ?");
        $upCard->execute([$realAmount ?: $card['declared_amount'], $receivedAmount, $message ?: 'Thẻ đúng, đã cộng tiền', $card['id']]);

        $db->commit();

        echo json_encode(['status' => 200, 'message' => 'Processed success and credited']);
        exit;
    } 
    // THẺ SAI HOẶC LỖI (status = 2 hoặc khác)
    else {
        $upCard = $db->prepare("UPDATE card_transactions SET status = 'error', real_amount = ?, response_message = ? WHERE id = ?");
        $upCard->execute([$realAmount, $message ?: 'Thẻ sai hoặc đã được sử dụng', $card['id']]);

        echo json_encode(['status' => 200, 'message' => 'Marked as failed']);
        exit;
    }

} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Internal server error: ' . $e->getMessage()]);
    exit;
}
