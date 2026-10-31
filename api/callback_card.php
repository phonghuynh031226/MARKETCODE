<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/money.php';

try {
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

// Provider callbacks must use POST with a mandatory signature.
$rawData = file_get_contents('php://input');
$json = json_decode($rawData, true, 512, JSON_BIGINT_AS_STRING);
if ($json !== null && !is_array($json)) { http_response_code(400); exit; }

$data = !empty($json) ? $json : $_POST;

if (empty($data)) {
    http_response_code(400);
    echo json_encode(['status' => 400, 'message' => 'No data received']);
    exit;
}

foreach (['status','message','request_id','code','pin','serial','callback_sign','sign'] as $field) {
    if (isset($data[$field]) && !is_string($data[$field]) && !is_int($data[$field])) { http_response_code(400); exit; }
}

$statusApi     = (int)($data['status'] ?? 0);
$message       = trim((string)($data['message'] ?? ''));
$requestId     = trim((string)($data['request_id'] ?? ''));
$code          = trim((string)($data['code'] ?? ($data['pin'] ?? '')));
$serial        = trim((string)($data['serial'] ?? ''));
try {
    $realMinor = money_minor(provider_decimal($data['value'] ?? ($data['real_amount'] ?? '0')));
    if ($realMinor % 100 !== 0) { throw new InvalidArgumentException('Invalid denomination'); }
    $realAmount = intdiv($realMinor, 100);
    $receivedInput = provider_decimal($data['amount'] ?? ($data['received_amount'] ?? '0'));
} catch (Throwable $error) { http_response_code(422); exit; }
$callbackSign  = trim((string)($data['callback_sign'] ?? ($data['sign'] ?? '')));

$partnerKey = get_setting('card_partner_key', '');

// Fail closed when signature or partner key is missing.
if (!card_callback_authenticated($partnerKey, $code, $serial, $callbackSign, $requestId)) {
        http_response_code(403);
        echo json_encode(['status' => 403, 'message' => 'Invalid callback signature']);
        exit;
}

if (!$db) {
    http_response_code(500);
    echo json_encode(['status' => 500, 'message' => 'Database connection failed']);
    exit;
}

try {
    $db->beginTransaction();
    // Tìm giao dịch thẻ tương ứng
    $stmt = $db->prepare("SELECT * FROM card_transactions WHERE request_id = ? AND serial = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$requestId, $serial]);
    $card = $stmt->fetch();

    if (!$card || !hash_equals((string)$card['pin_digest'], card_pin_digest($serial, $code))) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 404, 'message' => 'Transaction not found']);
        exit;
    }

    // Nếu thẻ đã được xử lý thành công trước đó (chống cộng tiền lặp)
    if ($card['status'] === 'success') {
        $db->commit();
        echo json_encode(['status' => 200, 'message' => 'Transaction already processed']);
        exit;
    }

    // THẺ ĐÚNG (status = 1) -> Cộng tiền cho User
    if ($statusApi === 1) {

        $receivedMinor = money_minor($receivedInput);
        if ($receivedMinor === 0) { $receivedMinor = $realAmount * 85; }
        if (!in_array($realAmount, [10000, 20000, 30000, 50000, 100000, 200000, 300000, 500000, 1000000], true)
            || $receivedMinor <= 0 || $receivedMinor > $realAmount * 100) {
            throw new InvalidArgumentException('Invalid card amount');
        }
        $receivedAmount = money_decimal($receivedMinor);
        $balStmt = $db->prepare('SELECT balance, total_deposited FROM users WHERE id = ? FOR UPDATE');
        $balStmt->execute([$card['user_id']]);
        $user = $balStmt->fetch();
        if (!$user) { throw new RuntimeException('Card user unavailable'); }
        $balanceBefore = money_decimal(money_minor($user['balance']));
        $currentBalance = money_decimal(money_add(money_minor($user['balance']), $receivedMinor));
        $totalDeposited = money_decimal(money_add(money_minor($user['total_deposited']), $receivedMinor));
        $userStmt = $db->prepare('UPDATE users SET balance = ?, total_deposited = ? WHERE id = ?');
        $userStmt->execute([$currentBalance, $totalDeposited, $card['user_id']]);

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
    elseif ($statusApi === 2 || $statusApi === 3 || $statusApi === 4) {
        $upCard = $db->prepare("UPDATE card_transactions SET status = 'error', real_amount = ?, response_message = ? WHERE id = ?");
        $upCard->execute([$realAmount, $message ?: 'Thẻ sai hoặc đã được sử dụng', $card['id']]);
        $db->commit();

        echo json_encode(['status' => 200, 'message' => 'Marked as failed']);
        exit;
    } else {
        $db->rollBack();
        http_response_code(422);
        exit(json_encode(['status' => 422, 'message' => 'Unsupported card status']));
    }

} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    error_log('Card callback error: ' . $e->getMessage());
    echo json_encode(['status' => 500, 'message' => 'Internal server error']);
    exit;
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
