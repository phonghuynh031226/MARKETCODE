<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/money.php';

try {
header('Content-Type: application/json; charset=utf-8');

// Authenticated SePay webhook (HMAC or API Key configured privately).
$rawInput = file_get_contents('php://input');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
if (!bank_webhook_authenticated($_SERVER, $rawInput, security_config('BANK_WEBHOOK_HMAC_SECRET'), security_config('BANK_WEBHOOK_API_KEY'))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Webhook authentication failed']);
    exit;
}
$data = json_decode($rawInput, true);

if (empty($data)) {
    // Có thể đối tác gửi dạng form-data / POST
    $data = $_POST;
}

if (!is_array($data) || empty($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No webhook payload provided']);
    exit;
}

foreach (['referenceCode','id','tid','transaction_id','content','description','remark','accountNumber','account_number','subAccount','gateway','bank_brand','bank_code','transferType'] as $field) {
    if (isset($data[$field]) && !is_string($data[$field]) && !is_int($data[$field])) { http_response_code(400); exit; }
}

// Chuẩn hóa dữ liệu theo các nhà cung cấp phổ biến (SePay, Casso, Web2M)
$refId       = trim((string)($data['referenceCode'] ?? ($data['id'] ?? ($data['tid'] ?? ($data['transaction_id'] ?? '')))));
try { $amountMinor = money_minor(provider_decimal($data['transferAmount'] ?? ($data['amount'] ?? ($data['money'] ?? '0')))); }
catch (Throwable $e) { http_response_code(422); exit; }
$amount = money_decimal($amountMinor);
$content     = trim((string)($data['content'] ?? ($data['description'] ?? ($data['remark'] ?? ''))));
$accountNo   = trim((string)($data['accountNumber'] ?? ($data['account_number'] ?? ($data['subAccount'] ?? ''))));
$bankCode    = trim((string)($data['gateway'] ?? ($data['bank_brand'] ?? ($data['bank_code'] ?? 'MBBANK'))));

// Only explicit incoming transfers may credit a balance.
$transferType = strtolower($data['transferType'] ?? '');
if ($transferType !== 'in') {
    echo json_encode(['success' => true, 'message' => 'Ignored outgoing transfer']);
    exit;
}

if ($refId === '' || strlen($refId) > 100 || $amountMinor <= 0 || $amountMinor > 5000000000
    || $accountNo === '' || !hash_equals(get_setting('bank_account_number', ''), $accountNo)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid bank transaction or receiving account']);
    exit;
}

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

$lockName = 'bank:' . substr(hash('sha256', strtolower($refId)), 0, 50);
try {
    // Serialize retries for the same provider reference on the database server.
    $lock = $db->prepare('SELECT GET_LOCK(?, 10)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) { throw new RuntimeException('Bank transaction lock unavailable'); }
    // 1. Chống cộng tiền trùng lặp (Idempotency): Kiểm tra mã refId đã tồn tại chưa
    if (!empty($refId)) {
        $checkStmt = $db->prepare("SELECT id FROM bank_transactions WHERE ref_transaction_id = ? LIMIT 1");
        $checkStmt->execute([$refId]);
        if ($checkStmt->fetch()) {
            echo json_encode(['success' => true, 'message' => 'Transaction has already been processed previously']);
            exit;
        }
    }

    $bankPrefix = get_setting('bank_prefix', 'NAPTIEN');
    $pattern = '/(?:^|\\s)' . preg_quote($bankPrefix, '/') . '\\s+([0-9a-f-]{36})(?:$|\\s)/i';
    $userId = 0;
    if (preg_match($pattern, $content, $matches)) {
        $userId = public_id_to_internal($db, 'users', strtolower($matches[1]));
    }

    if ($userId <= 0) {
        error_log('Bank webhook requires manual reconciliation; reference ' . $refId);
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'No user ID found; manual reconciliation required']);
        exit;
    }

    // 3. Tìm thông tin User
    $db->beginTransaction();
    $uStmt = $db->prepare("SELECT id, username, balance, total_deposited FROM users WHERE id = ? AND status = 'active' FOR UPDATE");
    $uStmt->execute([$userId]);
    $user = $uStmt->fetch();

    if (!$user) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Receiving user unavailable']);
        exit;
    }

    // 4. Bắt đầu PDO Transaction để cộng tiền an toàn tuyệt đối

    $balanceBefore = money_decimal(money_minor($user['balance']));
    $balanceAfter = money_decimal(money_add(money_minor($user['balance']), $amountMinor));
    $totalDeposited = money_decimal(money_add(money_minor($user['total_deposited']), $amountMinor));
    $upUser = $db->prepare('UPDATE users SET balance = ?, total_deposited = ? WHERE id = ?');
    $upUser->execute([$balanceAfter, $totalDeposited, $userId]);

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
    $inBank = $db->prepare("INSERT INTO bank_transactions (public_id, trans_id, user_id, bank_code, account_number, transfer_content, amount, status, ref_transaction_id)
                           VALUES (?, ?, ?, ?, ?, ?, ?, 'success', ?)");
    $inBank->execute([uuid_v4(), $transId, $userId, $bankCode, $accountNo, $content, $amount, $refId]);

    // Commit Transaction
    $db->commit();

    echo json_encode([
        'success' => true,
        'message' => "Successfully credited " . $amount . " VND to user @{$user['username']}",
        'trans_id' => $transId
    ]);

} catch (Throwable $e) {
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    error_log('Bank webhook error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Unable to process bank transaction']);
} finally {
    try {
        $release = $db->prepare('SELECT RELEASE_LOCK(?)');
        $release->execute([$lockName]);
    } catch (Throwable $releaseError) { error_log('Bank lock release failed: ' . $releaseError->getMessage()); }
}

} catch (Throwable $unexpected) {
    error_log('Payment handler failed: ' . $unexpected->getMessage());
    try {
        $connection = $pdo ?? $db ?? null;
        if ($connection instanceof PDO && $connection->inTransaction()) { $connection->rollBack(); }
    } catch (Throwable $rollbackError) { error_log('Payment rollback failed: ' . $rollbackError->getMessage()); }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
