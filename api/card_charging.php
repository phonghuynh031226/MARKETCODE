<?php
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

// Kiểm tra thẻ đã từng gửi lên hệ thống chưa (chống spam thẻ trùng)
try {
    $dupStmt = $db->prepare("SELECT id FROM card_transactions WHERE pin = ? AND serial = ? LIMIT 1");
    $dupStmt->execute([$pin, $serial]);
    if ($dupStmt->fetch()) {
        json_response(false, 'Thẻ cào này đã từng được gửi lên hệ thống, vui lòng không gửi lại!');
    }
} catch (Exception $e) {
    json_response(false, 'Lỗi hệ thống kiểm tra thẻ: ' . $e->getMessage());
}

$partnerId  = get_setting('card_partner_id', '');
$partnerKey = get_setting('card_partner_key', '');
$apiUrl     = get_setting('card_api_url', 'https://thesieure.com/chargingws/v2');

$transId   = 'CARD_' . date('YmdHis') . '_' . rand(1000, 9999);
$requestId = (string)rand(100000000, 999999999);

// Tính chiết khấu dự kiến (mặc định nhận ~85% nếu chưa có cấu hình riêng)
$discountRate = 0.85;
$estimatedReceived = $amount * $discountRate;

try {
    // Lưu thông tin thẻ vào database với trạng thái pending
    $inStmt = $db->prepare("INSERT INTO card_transactions (trans_id, request_id, user_id, telco, declared_amount, real_amount, received_amount, serial, pin, status, response_message) 
                           VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 'pending', 'Đang gửi thẻ sang cổng gạch...')");
    $inStmt->execute([$transId, $requestId, $user['id'], $telco, $amount, $estimatedReceived, $serial, $pin]);
    $cardId = $db->lastInsertId();

    // Nếu chưa cấu hình API Key đối tác (Chế độ mô phỏng Sandbox / Test)
    if (empty($partnerId) || empty($partnerKey)) {
        json_response(true, 'Thẻ cào đã được tiếp nhận và đưa vào hàng chờ xử lý! (Chế độ Sandbox: Điền Partner Key tại Admin để đẩy API thực tế)', [
            'trans_id'   => $transId,
            'status'     => 'pending',
            'is_sandbox' => true
        ]);
    }

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
        CURLOPT_SSL_VERIFYPEER => false,
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
    json_response(false, 'Lỗi xử lý nạp thẻ: ' . $e->getMessage());
}
