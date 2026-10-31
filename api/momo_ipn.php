<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/money.php';
require_once __DIR__ . '/../config/database.php';

try {
header('Content-Type: application/json; charset=utf-8');

function momo_ipn_reply(array $momo, array $ipn, int $resultCode, string $message): void
{
    // The provider acknowledges successful receipt with an empty HTTP 204.
    http_response_code($resultCode === 0 ? 204 : 422);
    exit;
}

$momo = require __DIR__ . '/../config/momo.php';
if ($momo['partner_code'] === '' || $momo['access_key'] === '' || $momo['secret_key'] === '') {
    error_log('MoMo IPN received while MoMo credentials are not configured');
    http_response_code(503);
    exit;
}

$rawBody = file_get_contents('php://input');
$ipn = json_decode($rawBody, true);
if (!is_array($ipn)) {
    http_response_code(400);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); exit; }

$required = ['partnerCode', 'requestId', 'amount', 'orderId', 'orderInfo', 'orderType', 'transId', 'message', 'responseTime', 'resultCode', 'payType', 'extraData', 'signature'];
foreach ($required as $field) {
    if (!array_key_exists($field, $ipn)) {
        http_response_code(400);
        exit;
    }
    if (!is_string($ipn[$field]) && !is_int($ipn[$field])) { http_response_code(400); exit; }
}
if (isset($ipn['accessKey']) && !is_string($ipn['accessKey'])) { http_response_code(400); exit; }
if (!hash_equals($momo['partner_code'], (string)$ipn['partnerCode'])
    || (isset($ipn['accessKey']) && !hash_equals($momo['access_key'], (string)$ipn['accessKey']))) {
    http_response_code(400);
    exit;
}

$signatureRaw = 'accessKey=' . $momo['access_key']
    . '&amount=' . $ipn['amount']
    . '&extraData=' . $ipn['extraData']
    . '&message=' . $ipn['message']
    . '&orderId=' . $ipn['orderId']
    . '&orderInfo=' . $ipn['orderInfo']
    . '&orderType=' . $ipn['orderType']
    . '&partnerCode=' . $ipn['partnerCode']
    . '&payType=' . $ipn['payType']
    . '&requestId=' . $ipn['requestId']
    . '&responseTime=' . $ipn['responseTime']
    . '&resultCode=' . $ipn['resultCode']
    . '&transId=' . $ipn['transId'];
$expectedSignature = hash_hmac('sha256', $signatureRaw, $momo['secret_key']);
if (!hash_equals($expectedSignature, (string)$ipn['signature'])) {
    http_response_code(400);
    exit;
}

if ($ipn['orderType'] !== 'momo_wallet' || !ctype_digit((string)$ipn['amount']) || !ctype_digit((string)$ipn['resultCode'])) {
    http_response_code(400);
    exit;
}

$pdo = null;
try {
    $amountMinor = money_minor($ipn['amount']);
    if ($amountMinor <= 0) { throw new InvalidArgumentException('Invalid MoMo amount'); }
    $pdo = Database::getInstance();
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT id, user_id, amount, status, request_id FROM momo_transactions WHERE order_id = ? FOR UPDATE');
    $find->execute([(string)$ipn['orderId']]);
    $transaction = $find->fetch();

    if (!$transaction || !hash_equals((string)$transaction['request_id'], (string)$ipn['requestId']) || money_minor($transaction['amount']) !== $amountMinor) {
        $pdo->rollBack();
        error_log('MoMo IPN did not match a pending top-up; order ' . (string)$ipn['orderId']);
        momo_ipn_reply($momo, $ipn, 1, 'Unknown or mismatched order.');
    }

    if ($transaction['status'] !== 'pending') {
        $pdo->commit();
        momo_ipn_reply($momo, $ipn, 0, 'Notification already processed.');
    }

    $resultCode = (int)$ipn['resultCode'];
    if ($resultCode !== 0) {
        $failed = $pdo->prepare("UPDATE momo_transactions SET status = 'failed', result_code = ?, momo_trans_id = ? WHERE id = ? AND status = 'pending'");
        $failed->execute([$resultCode, (string)$ipn['transId'], (int)$transaction['id']]);
        $pdo->commit();
        momo_ipn_reply($momo, $ipn, 0, 'Notification received.');
    }

    $userStmt = $pdo->prepare('SELECT balance, total_deposited FROM users WHERE id = ? FOR UPDATE');
    $userStmt->execute([(int)$transaction['user_id']]);
    $user = $userStmt->fetch();
    if (!$user) {
        throw new RuntimeException('MoMo transaction points to a missing user');
    }

    $balanceBefore = money_decimal(money_minor($user['balance']));
    $balanceAfter = money_decimal(money_add(money_minor($user['balance']), $amountMinor));
    $totalDeposited = money_decimal(money_add(money_minor($user['total_deposited']), $amountMinor));
    $amount = money_decimal($amountMinor);
    $credit = $pdo->prepare('UPDATE users SET balance = ?, total_deposited = ? WHERE id = ?');
    $credit->execute([$balanceAfter, $totalDeposited, (int)$transaction['user_id']]);
    $log = $pdo->prepare("INSERT INTO balance_logs (user_id, amount_before, amount, amount_after, type, note) VALUES (?, ?, ?, ?, 'deposit_momo', ?)");
    $log->execute([
        (int)$transaction['user_id'],
        $balanceBefore,
        $amount,
        $balanceAfter,
        'Nạp MoMo thành công (Mã đơn: ' . (string)$ipn['orderId'] . ')',
    ]);
    $success = $pdo->prepare("UPDATE momo_transactions SET status = 'success', result_code = 0, momo_trans_id = ? WHERE id = ? AND status = 'pending'");
    $success->execute([(string)$ipn['transId'], (int)$transaction['id']]);
    $pdo->commit();
    momo_ipn_reply($momo, $ipn, 0, 'Notification received.');
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('MoMo IPN database processing failed: ' . $e->getMessage());
    http_response_code(500);
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
