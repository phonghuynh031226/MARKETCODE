<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/passwords.php';

$appId       = get_setting('oauth_zalo_app_id', '');
$secretKey   = get_setting('oauth_zalo_secret_key', '');
$redirectUri = get_setting('oauth_zalo_redirect_uri', BASE_URL . '/auth/zalo.php');

if (empty($appId) || empty($secretKey)) {
    die('<!DOCTYPE html>
    <html lang="vi">
    <head><meta charset="UTF-8"><title>Chưa cấu hình Zalo OAuth</title></head>
    <body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-2xl p-6 border border-slate-200 shadow-xl text-center">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">Z</div>
            <h2 class="text-xl font-bold mb-2 text-slate-900">Chưa Cấu Hình Zalo Login</h2>
            <p class="text-sm text-slate-500 mb-6">Quản trị viên cần vào trang <strong>Admin > Cài đặt hệ thống</strong> để điền Zalo App ID và Secret Key từ Zalo for Developers.</p>
            <a href="' . BASE_URL . '/login.php" class="px-5 py-2.5 rounded-xl bg-blue-500 text-white font-bold text-sm hover:bg-blue-600 transition-colors shadow-sm">Quay lại Đăng nhập</a>
        </div>
    </body>
    </html>');
}

// BƯỚC 1: Chuyển hướng sang Zalo Permission Dialog
if (!isset($_GET['code'])) {
    $codeVerifier = bin2hex(random_bytes(32));
    $_SESSION['zalo_code_verifier'] = $codeVerifier;
    $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

    $params = [
        'app_id'         => $appId,
        'redirect_uri'   => $redirectUri,
        'code_challenge' => $codeChallenge,
        'state'          => oauth_create_state('zalo')
    ];

    header('Location: https://oauth.zaloapp.com/v4/permission?' . http_build_query($params));
    exit;
}

// BƯỚC 2: Đổi code lấy Access Token
if (!oauth_consume_state('zalo', $_GET['state'] ?? null)) { http_response_code(403); exit('Phiên đăng nhập không hợp lệ hoặc đã hết hạn.'); }
$code = $_GET['code'];
$codeVerifier = $_SESSION['zalo_code_verifier'] ?? '';

$tokenUrl = 'https://oauth.zaloapp.com/v4/access_token';
$postData = [
    'code'          => $code,
    'app_id'        => $appId,
    'grant_type'    => 'authorization_code',
    'code_verifier' => $codeVerifier
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/x-www-form-urlencoded',
        'secret_key: ' . $secretKey
    ]
]);
$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);
if (empty($tokenData['access_token'])) {
    error_log('OAuth token exchange failed'); http_response_code(502); exit('Không thể kết nối dịch vụ đăng nhập.');
}

// BƯỚC 3: Lấy thông tin user
$infoUrl = 'https://graph.zalo.me/v2.0/me?fields=id,name,picture';
$ch = curl_init($infoUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => ['access_token: ' . $tokenData['access_token']]
]);
$userResponse = curl_exec($ch);
curl_close($ch);

$zaloUser = json_decode($userResponse, true);
if (empty($zaloUser['id'])) {
    die('Không thể lấy thông tin tài khoản Zalo.');
}

$zaloId  = $zaloUser['id'];
$name    = trim($zaloUser['name'] ?? 'Zalo User');
$picture = $zaloUser['picture']['data']['url'] ?? 'assets/images/default-avatar.png';
$email   = "zalo_{$zaloId}@zalo.user";

if ($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE oauth_provider = 'zalo' AND oauth_uid = ? LIMIT 1");
        $stmt->execute([$zaloId]);
        $user = $stmt->fetch();

        if ($user) {
            if (($user['status'] !== 'active' || $user['deleted_at'] !== null)) { http_response_code(403); exit('Tài khoản đã bị khóa.'); }
            $userId = $user['id'];
        } else {
            $username = 'zalo_' . substr($zaloId, 0, 10);
            $dummyPass = app_password_hash(bin2hex(random_bytes(32)));
            $inStmt = $db->prepare("INSERT INTO users (public_id, username, email, password, fullname, avatar, oauth_provider, oauth_uid, role, status) VALUES (?, ?, ?, ?, ?, ?, 'zalo', ?, 'user', 'active')");
            $inStmt->execute([uuid_v4(), $username, $email, $dummyPass, $name, $picture, $zaloId]);
            $userId = (int)$db->lastInsertId();
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $user['role'] ?? 'user';
        $_SESSION['auth_password_tag'] = hash('sha256', $user['password'] ?? $dummyPass);

        header('Location: ' . BASE_URL . '/');
        exit;
    } catch (Throwable $e) {
        die('Lỗi xử lý Zalo OAuth: ' . public_error($e));
    }
}
