<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Phương thức không hợp lệ!');
}

if (!is_logged()) {
    json_response(false, 'Vui lòng đăng nhập để thực hiện nạp thẻ cào!');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    json_response(false, 'Mã bảo mật CSRF Token không hợp lệ hoặc đã hết hạn! Vui lòng tải lại trang.');
}

if (!$db) {
    json_response(false, 'Lỗi kết nối cơ sở dữ liệu!');
}

$user = current_user();
if (!security_rate_limit('card-create:' . (int)$user['id'], 10, 300)) {
    http_response_code(429);
    json_response(false, 'Bạn gửi thẻ quá nhanh. Vui lòng thử lại sau ít phút.');
}
$telco = strtoupper(trim($_POST['telco'] ?? ''));
$amount = (int)($_POST['amount'] ?? 0);
$serial = trim($_POST['serial'] ?? '');
$pin = trim($_POST['pin'] ?? '');

// Danh sách nhà mạng hợp lệ
$validTelcos = ['VIETTEL', 'VINAPHONE', 'MOBIFONE', 'VIETNAMOBILE', 'ZING'];
if (!in_array($telco, $validTelcos)) {
    json_response(false, 'Nhà mạng không hợp lệ!');
}

// Mệnh giá thẻ hợp lệ
$validAmounts = [10000, 20000, 30000, 50000, 100000, 200000, 300000, 500000, 1000000];
if (!in_array($amount, $validAmounts)) {
    json_response(false, 'Mệnh giá thẻ không hợp lệ!');
}

if (empty($serial) || empty($pin)) {
    json_response(false, 'Vui lòng nhập đầy đủ Số Seri và Mã Thẻ (PIN)!');
}

if (strlen($serial) < 6 || strlen($pin) < 6) {
    json_response(false, 'Số Seri hoặc Mã thẻ không đúng định dạng!');
}
if (!preg_match('/\A[a-zA-Z0-9-]{6,50}\z/D', $serial) || !preg_match('/\A[a-zA-Z0-9-]{6,50}\z/D', $pin)) {
    json_response(false, 'Mã thẻ hoặc số serial không hợp lệ.');
}
$pinDigest = card_pin_digest($serial, $pin);

// Kiểm tra thẻ đã từng gửi lên hệ thống chưa (chống spam thẻ trùng)
try {
    $dupStmt = $db->prepare("SELECT id FROM card_transactions WHERE pin_digest = ? AND serial = ? LIMIT 1");
    $dupStmt->execute([$pinDigest, $serial]);
    if ($dupStmt->fetch()) {
        json_response(false, 'Thẻ cào này đã từng được gửi lên hệ thống, vui lòng không gửi lại!');
    }
} catch (Exception $e) {
    json_response(false, 'Lỗi hệ thống kiểm tra thẻ: ' . public_error($e));
}

$partnerId  = get_setting('card_partner_id', '');
$partnerKey = get_setting('card_partner_key', '');
$apiUrl     = get_setting('card_api_url', 'https://thesieure.com/chargingws/v2');
if ($partnerId === '' || $partnerKey === '') { http_response_code(503); json_response(false, 'Cổng thẻ chưa được cấu hình.'); }
if (!in_array($apiUrl, ['https://thesieure.com/chargingws/v2'], true)) {
    http_response_code(503);
    json_response(false, 'Cổng thẻ chưa có endpoint được cho phép.');
}

$transId   = 'CARD_' . bin2hex(random_bytes(16));
$requestId = bin2hex(random_bytes(16));

// Tính chiết khấu dự kiến (mặc định nhận ~85% nếu chưa có cấu hình riêng)
$discountRate = 0.85;
$estimatedReceived = money_decimal(intdiv($amount * 100 * 85, 100));

try {
    // Lưu thông tin thẻ vào database với trạng thái pending
    $inStmt = $db->prepare("INSERT INTO card_transactions (public_id, trans_id, request_id, user_id, telco, declared_amount, real_amount, received_amount, serial, pin, pin_digest, status, response_message)
                           VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, '', ?, 'pending', 'Đang gửi thẻ sang cổng gạch...')");
    $inStmt->execute([uuid_v4(), $transId, $requestId, $user['id'], $telco, $amount, $estimatedReceived, $serial, $pinDigest]);
    $cardId = $db->lastInsertId();

    // Gửi cURL sang cổng gạch thẻ (Chuẩn API Thesieure / Doithe1s)
    $sign = md5($partnerKey . $pin . $serial);
    $postData = [
        'telco'      => $telco,
        'code'       => $pin,
        'serial'     => $serial,
        'amount'     => $amount,
        'request_id' => $requestId,
        'partner_id' => $partnerId,
        'sign'       => $sign,
        'command'    => 'charging'
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($postData),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        $upStmt = $db->prepare("UPDATE card_transactions SET response_message = ? WHERE id = ?");
        $upStmt->execute(['Lỗi kết nối cổng gạch thẻ: ' . $curlErr, $cardId]);
        json_response(false, 'Không thể kết nối đến máy chủ gạch thẻ! Thẻ đã được lưu vào hệ thống để duyệt bù.');
    }

    $resData = json_decode($response, true);
    $statusApi = $resData['status'] ?? 99;
    $messageApi = $resData['message'] ?? 'Thẻ đã gửi thành công, vui lòng chờ duyệt trong 30s - 2 phút!';

    $upStmt = $db->prepare("UPDATE card_transactions SET response_message = ? WHERE id = ?");
    $upStmt->execute([$messageApi, $cardId]);

    json_response(true, $messageApi, [
        'trans_id' => $transId,
        'status'   => 'pending'
    ]);

} catch (Exception $e) {
    json_response(false, 'Lỗi xử lý nạp thẻ: ' . public_error($e));
}
