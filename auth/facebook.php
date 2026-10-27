<?php
require_once __DIR__ . '/../config/config.php';

$appId       = get_setting('oauth_facebook_app_id', '');
$appSecret   = get_setting('oauth_facebook_app_secret', '');
$redirectUri = get_setting('oauth_facebook_redirect_uri', BASE_URL . '/auth/facebook.php');

if (empty($appId) || empty($appSecret)) {
    die('<!DOCTYPE html>
    <html lang="vi">
    <head><meta charset="UTF-8"><title>Chưa cấu hình Facebook OAuth</title><script src="https://cdn.tailwindcss.com"></script></head>
    <body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-2xl p-6 border border-slate-200 shadow-xl text-center">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 border border-blue-200 flex items-center justify-center mx-auto mb-4 text-2xl font-bold">f</div>
            <h2 class="text-xl font-bold mb-2 text-slate-900">Chưa Cấu Hình Facebook Login</h2>
            <p class="text-sm text-slate-500 mb-6">Quản trị viên cần vào trang <strong>Admin > Cài đặt hệ thống</strong> để điền App ID và App Secret từ Facebook Developers.</p>
            <a href="' . BASE_URL . '/login.php" class="px-5 py-2.5 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 transition-colors shadow-sm">Quay lại Đăng nhập</a>
        </div>
    </body>
    </html>');
}

// BƯỚC 1: Chuyển hướng sang Facebook Login Dialog
if (!isset($_GET['code'])) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;

    $params = [
        'client_id'     => $appId,
        'redirect_uri'  => $redirectUri,
        'state'         => $state,
        'scope'         => 'email,public_profile'
    ];

    header('Location: https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query($params));
    exit;
}

// BƯỚC 2: Đổi code lấy Access Token
$code = $_GET['code'];
$tokenUrl = 'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query([
    'client_id'     => $appId,
    'client_secret' => $appSecret,
    'redirect_uri'  => $redirectUri,
    'code'          => $code
]);

$ch = curl_init($tokenUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false
]);
$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);
if (empty($tokenData['access_token'])) {
    die('Không thể lấy Token từ Facebook: ' . htmlspecialchars($tokenResponse));
}

// BƯỚC 3: Lấy thông tin người dùng từ Facebook Graph API
$infoUrl = 'https://graph.facebook.com/me?fields=id,name,email,picture.type(large)&access_token=' . $tokenData['access_token'];
$ch = curl_init($infoUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false
]);
$userResponse = curl_exec($ch);
curl_close($ch);

$fbUser = json_decode($userResponse, true);
if (empty($fbUser['id'])) {
    die('Không thể lấy thông tin tài khoản Facebook.');
}

$fbId    = $fbUser['id'];
$email   = !empty($fbUser['email']) ? strtolower(trim($fbUser['email'])) : "fb_{$fbId}@facebook.user";
$name    = trim($fbUser['name'] ?? 'Facebook User');
$picture = $fbUser['picture']['data']['url'] ?? 'assets/images/default-avatar.png';

// BƯỚC 4: Tìm hoặc tạo tài khoản trong CSDL
if ($db) {
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE (oauth_provider = 'facebook' AND oauth_uid = ?) OR email = ? LIMIT 1");
        $stmt->execute([$fbId, $email]);
        $user = $stmt->fetch();

        if ($user) {
            $upStmt = $db->prepare("UPDATE users SET oauth_provider = 'facebook', oauth_uid = ? WHERE id = ?");
            $upStmt->execute([$fbId, $user['id']]);
            $userId = $user['id'];
        } else {
            $baseUsername = 'fb_' . substr($fbId, 0, 10);
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
            $inStmt = $db->prepare("INSERT INTO users (username, email, password, fullname, avatar, oauth_provider, oauth_uid, role, status) VALUES (?, ?, ?, ?, ?, 'facebook', ?, 'user', 'active')");
            $inStmt->execute([$username, $email, $dummyPass, $name, $picture, $fbId]);
            $userId = (int)$db->lastInsertId();
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $user['role'] ?? 'user';

        header('Location: ' . BASE_URL . '/');
        exit;
    } catch (Exception $e) {
        die('Lỗi xử lý Facebook OAuth: ' . $e->getMessage());
    }
}
