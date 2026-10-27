<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

function momo_ipn_reply(array $momo, array $ipn, int $resultCode, string $message): void
{
    $partnerCode = $momo['partner_code'];
    $requestId = (string)($ipn['requestId'] ?? '');
    $orderId = (string)($ipn['orderId'] ?? '');
    $responseTime = (string)round(microtime(true) * 1000);
    $extraData = (string)($ipn['extraData'] ?? '');
    $signatureRaw = 'accessKey=' . $momo['access_key']
        . '&extraData=' . $extraData
        . '&message=' . $message
        . '&orderId=' . $orderId
        . '&partnerCode=' . $partnerCode
        . '&requestId=' . $requestId
        . '&responseTime=' . $responseTime
        . '&resultCode=' . $resultCode;
    $response = [
        'partnerCode' => $partnerCode,
        'requestId' => $requestId,
        'orderId' => $orderId,
        'resultCode' => $resultCode,
        'message' => $message,
        'responseTime' => (int)$responseTime,
        'extraData' => $extraData,
        'signature' => hash_hmac('sha256', $signatureRaw, $momo['secret_key']),
    ];
    http_response_code(200);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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

$required = ['partnerCode', 'accessKey', 'requestId', 'amount', 'orderId', 'orderInfo', 'orderType', 'transId', 'message', 'responseTime', 'resultCode', 'payType', 'extraData', 'signature'];
foreach ($required as $field) {
    if (!array_key_exists($field, $ipn)) {
        http_response_code(400);
        exit;
    }
}
if (!hash_equals($momo['partner_code'], (string)$ipn['partnerCode'])
    || !hash_equals($momo['access_key'], (string)$ipn['accessKey'])) {
    http_response_code(400);
    exit;
}

$signatureRaw = 'accessKey=' . $ipn['accessKey']
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

$pdo = Database::getInstance();
try {
    $pdo->beginTransaction();
    $find = $pdo->prepare('SELECT id, user_id, amount, status, request_id FROM momo_transactions WHERE order_id = ? FOR UPDATE');
    $find->execute([(string)$ipn['orderId']]);
    $transaction = $find->fetch();

    if (!$transaction || !hash_equals((string)$transaction['request_id'], (string)$ipn['requestId']) || (int)$transaction['amount'] !== (int)$ipn['amount']) {
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

    $userStmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
    $userStmt->execute([(int)$transaction['user_id']]);
    $user = $userStmt->fetch();
    if (!$user) {
        throw new RuntimeException('MoMo transaction points to a missing user');
    }

    $balanceBefore = (float)$user['balance'];
    $balanceAfter = $balanceBefore + (int)$transaction['amount'];
    $credit = $pdo->prepare('UPDATE users SET balance = balance + ?, total_deposited = total_deposited + ? WHERE id = ?');
    $credit->execute([(int)$transaction['amount'], (int)$transaction['amount'], (int)$transaction['user_id']]);
    $log = $pdo->prepare("INSERT INTO balance_logs (user_id, amount_before, amount, amount_after, type, note) VALUES (?, ?, ?, ?, 'deposit_momo', ?)");
    $log->execute([
        (int)$transaction['user_id'],
        $balanceBefore,
        (int)$transaction['amount'],
        $balanceAfter,
        'Nạp MoMo thành công (Mã đơn: ' . (string)$ipn['orderId'] . ')',
    ]);
    $success = $pdo->prepare("UPDATE momo_transactions SET status = 'success', result_code = 0, momo_trans_id = ? WHERE id = ? AND status = 'pending'");
    $success->execute([(string)$ipn['transId'], (int)$transaction['id']]);
    $pdo->commit();
    momo_ipn_reply($momo, $ipn, 0, 'Notification received.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('MoMo IPN database processing failed: ' . $e->getMessage());
    http_response_code(500);
}
