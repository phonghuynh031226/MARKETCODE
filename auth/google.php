<?php
require_once __DIR__ . '/../config/config.php';

$clientId     = get_setting('oauth_google_client_id', '');
$clientSecret = get_setting('oauth_google_client_secret', '');
$redirectUri  = get_setting('oauth_google_redirect_uri', BASE_URL . '/auth/google.php');

// Chuẩn hóa Redirect URI thành đường dẫn tuyệt đối bắt buộc theo chuẩn Google OAuth 2.0
if (empty($redirectUri)) {
    $redirectUri = BASE_URL . '/auth/google.php';
} elseif (!preg_match('#^https?://#i', $redirectUri)) {
    $redirectUri = BASE_URL . '/' . ltrim($redirectUri, '/');
}

// Nếu chưa cấu hình OAuth Google, thông báo hướng dẫn
if (empty($clientId) || empty($clientSecret)) {
    die('<!DOCTYPE html>
    <html lang="vi">
    <head><meta charset="UTF-8"><title>Chưa cấu hình Google OAuth</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-2xl p-6 border border-slate-200 shadow-xl text-center">
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">!</div>
            <h2 class="text-xl font-bold mb-2 text-slate-900">Chưa Cấu Hình Google Login</h2>
            <p class="text-sm text-slate-500 mb-6">Quản trị viên cần vào trang <strong>Admin > Cài đặt hệ thống</strong> để điền Client ID và Client Secret từ Google Cloud Console.</p>
            <a href="' . BASE_URL . '/login.php" class="px-5 py-2.5 rounded-xl bg-indigo-600 text-white font-bold text-sm hover:bg-indigo-700 transition-colors shadow-sm">Quay lại Đăng nhập</a>
        </div>
    </body>
    </html>');
}

// BƯỚC 1: Nếu chưa có 'code', chuyển hướng sang Google Login
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = [
        'client_id'     => $clientId,
        'redirect_uri'  => $redirectUri,
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account'
    ];

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
    exit;
}

// BƯỚC 2: Nhận 'code' từ Google và lấy Access Token
$code = $_GET['code'];

$tokenUrl = 'https://oauth2.googleapis.com/token';
$postData = [
    'code'          => $code,
    'client_id'     => $clientId,
    'client_secret' => $clientSecret,
    'redirect_uri'  => $redirectUri,
    'grant_type'    => 'authorization_code'
];

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded']
]);
$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);
if (empty($tokenData['access_token'])) {
    die('Không thể lấy Access Token từ Google: ' . htmlspecialchars($tokenResponse));
}

// BƯỚC 3: Lấy thông tin tài khoản người dùng từ Google
$userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
$ch = curl_init($userInfoUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tokenData['access_token']]
]);
$userResponse = curl_exec($ch);
curl_close($ch);

$googleUser = json_decode($userResponse, true);
if (empty($googleUser['id']) || empty($googleUser['email'])) {
    die('Không thể lấy thông tin người dùng từ Google.');
}

$googleId = $googleUser['id'];
$email    = strtolower(trim($googleUser['email']));
$name     = trim($googleUser['name'] ?? 'Google User');
$picture  = $googleUser['picture'] ?? 'assets/images/default-avatar.png';

// BƯỚC 4: Kiểm tra và lưu thông tin vào MySQL
if ($db) {
    try {
        // Tìm theo oauth_uid hoặc email
        $stmt = $db->prepare("SELECT * FROM users WHERE (oauth_provider = 'google' AND oauth_uid = ?) OR email = ? LIMIT 1");
        $stmt->execute([$googleId, $email]);
        $user = $stmt->fetch();

        if ($user) {
            // Đã tồn tại, đồng bộ lại oauth nếu chưa có
            $upStmt = $db->prepare("UPDATE users SET oauth_provider = 'google', oauth_uid = ?, avatar = COALESCE(avatar, ?) WHERE id = ?");
            $upStmt->execute([$googleId, $picture, $user['id']]);
            $userId = $user['id'];
        } else {
            // Tạo mới user
            $baseUsername = explode('@', $email)[0];
            $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '', $baseUsername);
            $username = $baseUsername;
            $counter = 1;
            while (true) {
                $checkU = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
                $checkU->execute([$username]);
                if (!$checkU->fetch()) break;
                $username = $baseUsername . $counter;
                $counter++;
            }

            $dummyPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $inStmt = $db->prepare("INSERT INTO users (username, email, password, fullname, avatar, oauth_provider, oauth_uid, role, status) VALUES (?, ?, ?, ?, ?, 'google', ?, 'user', 'active')");
            $inStmt->execute([$username, $email, $dummyPass, $name, $picture, $googleId]);
            $userId = (int)$db->lastInsertId();
        }

        // Đăng nhập session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        header('Location: ' . BASE_URL . '/');
        exit;

    } catch (Exception $e) {
        die('Lỗi xử lý đăng nhập Google: ' . $e->getMessage());
    }
} else {
    die('Không có kết nối CSDL.');
}
