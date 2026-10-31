<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/momo_json.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_logged()) {
    momo_json_response(401, ['success' => false, 'message' => 'Vui lòng đăng nhập để nạp tiền.']);
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    momo_json_response(403, ['success' => false, 'message' => 'Phiên làm việc hết hạn. Hãy tải lại trang và thử lại.']);
}

$amountInput = trim((string)($_POST['amount'] ?? ''));
if (!preg_match('/^\d{1,12}$/', $amountInput)) {
    momo_json_response(422, ['success' => false, 'message' => 'Số tiền nạp không hợp lệ.']);
}
$amount = (int)$amountInput;
if ($amount < 10000) {
    momo_json_response(422, ['success' => false, 'message' => 'Số tiền nạp tối thiểu là 10.000 ₫.']);
}
if ($amount > 50000000) {
    momo_json_response(422, ['success' => false, 'message' => 'Số tiền nạp tối đa mỗi giao dịch là 50.000.000 ₫.']);
}

$momo = require __DIR__ . '/../config/momo.php';
if ($momo['endpoint'] === '' || $momo['partner_code'] === '' || $momo['access_key'] === '' || $momo['secret_key'] === '') {
    momo_json_response(503, ['success' => false, 'message' => 'Cổng MoMo chưa được cấu hình thông tin đối tác.']);
}

$user = current_user();
if (!$user || empty($user['id'])) {
    momo_json_response(401, ['success' => false, 'message' => 'Vui lòng đăng nhập để nạp tiền.']);
}
if (!security_rate_limit('momo-create:' . (int)$user['id'], 10, 300)) {
    momo_json_response(429, ['success' => false, 'message' => 'Bạn tạo giao dịch quá nhanh. Vui lòng thử lại sau ít phút.']);
}

try {
    $pdo = Database::getInstance();
    $orderId = 'MOMO' . date('YmdHis') . strtoupper(bin2hex(random_bytes(6)));
    $requestId = 'REQ' . date('YmdHis') . strtoupper(bin2hex(random_bytes(6)));
    $orderInfo = 'Nap tien tai khoan ' . $user['public_id'];
    $extraData = '';
    if ($momo['public_base_url'] === '' || !str_starts_with($momo['public_base_url'], 'https://')) {
        momo_json_response(503, ['success' => false, 'message' => 'Cần cấu hình MOMO_PUBLIC_BASE_URL là địa chỉ HTTPS công khai để MoMo gửi kết quả thanh toán.']);
    }
    $redirectUrl = $momo['public_base_url'] . '/deposit.php?momo_return=1&order_id=' . rawurlencode($orderId);
    $ipnUrl = $momo['public_base_url'] . '/api/momo_ipn.php';

    $insert = $pdo->prepare("INSERT INTO momo_transactions (public_id, order_id, request_id, user_id, amount, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $insert->execute([uuid_v4(), $orderId, $requestId, (int)$user['id'], $amount]);

    $rawSignature = 'accessKey=' . $momo['access_key']
        . '&amount=' . $amount
        . '&extraData=' . $extraData
        . '&ipnUrl=' . $ipnUrl
        . '&orderId=' . $orderId
        . '&orderInfo=' . $orderInfo
        . '&partnerCode=' . $momo['partner_code']
        . '&redirectUrl=' . $redirectUrl
        . '&requestId=' . $requestId
        . '&requestType=captureWallet';
    $signature = hash_hmac('sha256', $rawSignature, $momo['secret_key']);

    $payload = [
        'partnerCode' => $momo['partner_code'],
        'partnerName' => $momo['partner_name'],
        'storeId' => $momo['store_id'],
        'requestId' => $requestId,
        'amount' => $amount,
        'orderId' => $orderId,
        'orderInfo' => $orderInfo,
        'redirectUrl' => $redirectUrl,
        'ipnUrl' => $ipnUrl,
        'lang' => 'vi',
        'extraData' => $extraData,
        'requestType' => 'captureWallet',
        'signature' => $signature,
    ];

    $ch = curl_init($momo['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=UTF-8'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => (int)$momo['request_timeout'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $responseBody = curl_exec($ch);
    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    $response = is_string($responseBody) ? json_decode($responseBody, true) : null;

    $responseMatchesRequest = is_array($response)
        && hash_equals($momo['partner_code'], (string)($response['partnerCode'] ?? ''))
        && hash_equals($requestId, (string)($response['requestId'] ?? ''))
        && hash_equals($orderId, (string)($response['orderId'] ?? ''))
        && (int)($response['amount'] ?? 0) === $amount;
    if ($curlError !== '' || $httpStatus < 200 || $httpStatus >= 300 || !$responseMatchesRequest || (int)($response['resultCode'] ?? -1) !== 0) {
        $fail = $pdo->prepare("UPDATE momo_transactions SET status = 'failed', result_code = ? WHERE order_id = ? AND status = 'pending'");
        $fail->execute([isset($response['resultCode']) ? (int)$response['resultCode'] : null, $orderId]);
        $momoMsg = $response['message'] ?? 'Không tạo được giao dịch MoMo. Vui lòng kiểm tra lại cấu hình MoMo.';
        error_log('MoMo create payment failed for order ' . $orderId . '; HTTP ' . $httpStatus . '; cURL ' . $curlError . '; MoMo Msg: ' . $momoMsg);
        momo_json_response(502, ['success' => false, 'message' => 'MoMo phản hồi: ' . $momoMsg]);
    }

    $payUrl = (string)($response['payUrl'] ?? '');
    $parts = parse_url($payUrl);
    $host = strtolower((string)($parts['host'] ?? ''));
    $isMomoHost = $host === 'momo.vn' || str_ends_with($host, '.momo.vn');
    if (($parts['scheme'] ?? '') !== 'https' || !$isMomoHost) {
        $fail = $pdo->prepare("UPDATE momo_transactions SET status = 'failed', result_code = ? WHERE order_id = ? AND status = 'pending'");
        $fail->execute([(int)$response['resultCode'], $orderId]);
        error_log('MoMo returned an invalid payment URL for order ' . $orderId);
        momo_json_response(502, ['success' => false, 'message' => 'MoMo trả về đường dẫn thanh toán không hợp lệ.']);
    }

    momo_json_response(200, ['success' => true, 'pay_url' => $payUrl, 'order_id' => $orderId]);
} catch (Throwable $e) {
    error_log('MoMo create payment exception: ' . $e->getMessage());
    momo_json_response(500, ['success' => false, 'message' => 'Hệ thống thanh toán gặp lỗi. Vui lòng liên hệ quản trị viên kiểm tra nhật ký PHP.']);
}
