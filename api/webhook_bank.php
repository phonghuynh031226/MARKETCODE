<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

// Lấy dữ liệu Webhook gửi đến từ SePay / Casso / Web2M
$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (empty($data)) {
    // Có thể đối tác gửi dạng form-data / POST
    $data = $_POST;
}

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No webhook payload provided']);
    exit;
}

// Chuẩn hóa dữ liệu theo các nhà cung cấp phổ biến (SePay, Casso, Web2M)
$refId       = trim((string)($data['referenceCode'] ?? ($data['id'] ?? ($data['tid'] ?? ($data['transaction_id'] ?? '')))));
$amount      = (float)($data['transferAmount'] ?? ($data['amount'] ?? ($data['money'] ?? 0)));
$content     = trim((string)($data['content'] ?? ($data['description'] ?? ($data['remark'] ?? ''))));
$accountNo   = trim((string)($data['accountNumber'] ?? ($data['account_number'] ?? ($data['subAccount'] ?? ''))));
$bankCode    = trim((string)($data['gateway'] ?? ($data['bank_brand'] ?? ($data['bank_code'] ?? 'MBBANK'))));

// Chỉ xử lý giao dịch tiền vào (transferType = in hoặc amount > 0)
$transferType = strtolower($data['transferType'] ?? 'in');
if ($transferType === 'out' || $amount <= 0) {
    echo json_encode(['success' => true, 'message' => 'Ignored outgoing transfer']);
    exit;
}

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // 1. Chống cộng tiền trùng lặp (Idempotency): Kiểm tra mã refId đã tồn tại chưa
    if (!empty($refId)) {
        $checkStmt = $db->prepare("SELECT id FROM bank_transactions WHERE ref_transaction_id = ? LIMIT 1");
        $checkStmt->execute([$refId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => true, 'message' => 'Transaction has already been processed previously']);
            exit;
        }
    }

    // 2. Sử dụng Regex bóc tách USER_ID từ nội dung chuyển khoản
    // Hỗ trợ các cú pháp: NAPTIEN 1, NAPTIEN1, NAPTIEN ID1, NAPTIENID1, NAP 1, NAP1
    $bankPrefix = get_setting('bank_prefix', 'NAPTIEN');
    $pattern = '/(?:' . preg_quote($bankPrefix, '/') . '|NAPTIEN|NAP)\s*(?:ID)?\s*([0-9]+)/i';

    $userId = 0;
    if (preg_match($pattern, $content, $matches)) {
        $userId = (int)$matches[1];
    }

    if ($userId <= 0) {
        // Lưu lại giao dịch không rõ chủ để Admin đối soát thủ công
        $fallbackTransId = 'BANK_UNKNOWN_' . date('YmdHis') . '_' . rand(100, 999);
        $inSt = $db->prepare("INSERT INTO bank_transactions (trans_id, user_id, bank_code, account_number, transfer_content, amount, status, ref_transaction_id) 
                             VALUES (?, 1, ?, ?, ?, ?, 'pending', ?)");
        $inSt->execute([$fallbackTransId, $bankCode, $accountNo, $content, $amount, $refId]);

        echo json_encode(['success' => true, 'message' => 'No user ID found in content, saved for manual review']);
        exit;
    }

    // 3. Tìm thông tin User
    $uStmt = $db->prepare("SELECT id, username, balance FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => "User ID {$userId} not found in database"]);
        exit;
    }

    // 4. Bắt đầu PDO Transaction để cộng tiền an toàn tuyệt đối
    $db->beginTransaction();

    // Tăng số dư và tổng nạp
    $upUser = $db->prepare("UPDATE users SET balance = balance + ?, total_deposited = total_deposited + ? WHERE id = ?");
    $upUser->execute([$amount, $amount, $userId]);

    $balanceBefore = (float)$user['balance'];
    $balanceAfter  = $balanceBefore + $amount;

    // Ghi nhật ký biến động số dư
    $logStmt = $db->prepare("INSERT INTO balance_logs (user_id, amount_before, amount, amount_after, type, note) 
                            VALUES (?, ?, ?, ?, 'deposit_bank', ?)");
    $logStmt->execute([
        $userId,
        $balanceBefore,
        $amount,
        $balanceAfter,
        "Nạp tiền tự động VietQR qua ngân hàng {$bankCode} (Mã GD: {$refId})"
    ]);

    // Lưu lịch sử giao dịch ngân hàng
    $transId = 'BANK_' . date('YmdHis') . '_' . rand(1000, 9999);
    $inBank = $db->prepare("INSERT INTO bank_transactions (trans_id, user_id, bank_code, account_number, transfer_content, amount, status, ref_transaction_id) 
                           VALUES (?, ?, ?, ?, ?, ?, 'success', ?)");
    $inBank->execute([$transId, $userId, $bankCode, $accountNo, $content, $amount, $refId]);

    // Commit Transaction
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Successfully credited " . number_format($amount) . " VND to user @{$user['username']} (ID: {$userId})",
        'trans_id' => $transId
    ]);

} catch (Exception $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database transaction error: ' . $e->getMessage()]);
}
