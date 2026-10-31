<?php
declare(strict_types=1);
/**
 * ========================================================
 * Cấu hình Hệ thống, Khởi tạo Session & Helper Functions
 * ========================================================
 */

// Bật hiển thị lỗi trong môi trường phát triển (tắt khi lên Production)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/identities.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/../includes/money.php';
require_once __DIR__ . '/../includes/output.php';
set_exception_handler(function (Throwable $error) {
    error_log('Unhandled application error: ' . $error->getMessage());
    http_response_code($error instanceof InvalidArgumentException ? 400 : 500);
    if (preg_match('#/(api|ajax)/#', $_SERVER['SCRIPT_NAME'] ?? '')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Hệ thống gặp lỗi. Vui lòng thử lại sau.'], JSON_UNESCAPED_UNICODE);
    } else { echo 'Hệ thống gặp lỗi. Vui lòng thử lại sau.'; }
});

foreach ([$_GET, $_POST] as $inputSet) {
    foreach ($inputSet as $inputName => $inputValue) {
        if (is_array($inputValue) && $inputName === 'existing_gallery' && count($inputValue) <= 5) {
            foreach ($inputValue as $image) {
                if (!is_string($image) || strlen($image) > 2048) { throw new InvalidArgumentException('Invalid gallery'); }
            }
            continue;
        }
        $maxLength = match ($inputName) {
            'password', 'password_confirm', 'old_password', 'new_password', 'confirm_password' => 1024,
            'email' => 254, 'account', 'fullname', 'title', 'download_url', 'demo_url' => 255,
            'username' => 30, 'name' => 100, 'slug' => 280, 'short_desc', 'description' => 500,
            'serial', 'pin', 'code' => 50, 'csrf_token' => 64, 'otp' => 6,
            'redirect', 'avatar' => 2048, 'q', 'reason' => 1000,
            default => 100000,
        };
        if (!is_string($inputValue) || strlen($inputValue) > $maxLength) { throw new InvalidArgumentException('Invalid request parameter'); }
    }
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443
    || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Cấu hình Timezone Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình Session Cookie an toàn (Chống Session Hijacking và XSS đánh cắp cookie)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 ngày
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

$callbackScripts = ['/api/webhook_bank.php', '/api/callback_card.php', '/api/momo_ipn.php'];
$scriptPath = parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH) ?: '';
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)
    && !in_array($scriptPath, $callbackScripts, true)) {
    security_require_post_csrf();
}

// Định nghĩa đường dẫn
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);

// Tự động nhận diện Base URL linh hoạt cho Localhost hoặc Server (Hỗ trợ SSL Cloudflare & Reverse Proxy)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (!empty($_SERVER['HTTP_CF_VISITOR']) && str_contains($_SERVER['HTTP_CF_VISITOR'], '"https"'))
    || (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
$protocol = $isHttps ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (!is_string($host) || !preg_match('/\A(?:[a-zA-Z0-9.-]+|\[[a-fA-F0-9:]+\])(?::[0-9]{1,5})?\z/D', $host)) {
    throw new InvalidArgumentException('Invalid host');
}
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$scriptDir = rtrim($scriptDir, '/');
// Nếu file nằm trong folder con (như /ajax, /api, /auth, /user hay /admin) thì chuẩn hóa về thư mục gốc của project
$baseDir = preg_replace('/(\/(admin|user|ajax|api|auth|config|includes))$/i', '', $scriptDir);
define('BASE_URL', $protocol . $host . $baseDir);

// Nhúng kết nối CSDL PDO
require_once CONFIG_PATH . '/database.php';

try {
    $db = Database::getInstance();
} catch (Throwable $error) {
    error_log('Database initialization failed: ' . $error->getMessage());
    $db = null;
}

/**
 * Cache cài đặt hệ thống từ bảng settings
 */
$SYSTEM_SETTINGS = [];
if ($db) {
    try {
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch()) {
            $SYSTEM_SETTINGS[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        $SYSTEM_SETTINGS = [];
    }
}

// Request audit never stores submitted values, passwords, tokens, PINs or signatures.
if ($db instanceof PDO && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    register_shutdown_function(static function (): void {
        global $db;
        if (!$db instanceof PDO || $db->inTransaction()) { return; }
        try { audit_event($db, 'request.http_' . (string)http_response_code(), basename($_SERVER['SCRIPT_NAME'] ?? 'unknown')); }
        catch (Throwable $error) { error_log('Audit persistence failed'); }
    });
}

/**
 * Lấy giá trị cài đặt hệ thống
 */
function get_setting(string $key, string $default = ''): string {
    global $SYSTEM_SETTINGS;
    $secretName = match ($key) {
        'momo_partner_code' => 'MOMO_PARTNER_CODE', 'momo_access_key' => 'MOMO_ACCESS_KEY', 'momo_secret_key' => 'MOMO_SECRET_KEY',
        'card_partner_id' => 'CARD_PARTNER_ID', 'card_partner_key' => 'CARD_PARTNER_KEY',
        'oauth_google_client_id' => 'GOOGLE_CLIENT_ID', 'oauth_google_client_secret' => 'GOOGLE_CLIENT_SECRET',
        'oauth_facebook_app_id' => 'FACEBOOK_APP_ID', 'oauth_facebook_app_secret' => 'FACEBOOK_APP_SECRET',
        'oauth_zalo_app_id' => 'ZALO_APP_ID', 'oauth_zalo_secret_key' => 'ZALO_SECRET_KEY',
        'recaptcha_secret_key' => 'CAPTCHA_SECRET_KEY', 'cron_security_key' => 'CRON_SECURITY_KEY',
        default => null,
    };
    if ($secretName !== null) { return security_config($secretName, $default); }
    return $SYSTEM_SETTINGS[$key] ?? $default;
}

/**
 * Cập nhật hoặc lưu cài đặt hệ thống vào CSDL
 */
function update_setting(string $key, string $value, string $desc = ''): bool {
    global $db, $SYSTEM_SETTINGS;
    if (!$db) return false;
    try {
        $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value, description)
                              VALUES (?, ?, ?)
                              ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()");
        $stmt->execute([$key, $value, $desc]);
        $SYSTEM_SETTINGS[$key] = $value;
        return true;
    } catch (Exception $e) {
        error_log('Setting update failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Tạo CSRF Token chống giả mạo request
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Xác thực CSRF Token
 */
function verify_csrf_token(mixed $token): bool {
    if (!is_string($token) || strlen($token) !== 64 || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Xác thực Google reCAPTCHA v2 / Cloudflare Turnstile
 */
function verify_captcha(?string $captchaResponse): bool {
    $secretKey = get_setting('recaptcha_secret_key', '');
    // Nếu chưa cấu hình secret key trong hệ thống, bỏ qua để không chặn dev
    if (empty($secretKey)) {
        return true;
    }

    if (empty($captchaResponse)) {
        return false;
    }

    $url = 'https://www.google.com/recaptcha/api/siteverify';
    $data = [
        'secret'   => $secretKey,
        'response' => $captchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
            'timeout' => 5
        ]
    ];
    $context  = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        return false;
    }
    $response = json_decode($result, true);
    return !empty($response['success']);
}

/**
 * Chống tấn công XSS trên dữ liệu đầu ra
 */
function xss_clean(?string $data): string {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Định dạng tiền tệ VND
 */
function format_money(float|int|string|null $amount): string {
    $amount = (float)($amount ?? 0);
    return number_format((float)($amount), 0, ',', '.') . ' ₫';
}

/**
 * Kiểm tra trạng thái đăng nhập
 */
function is_logged(): bool {
    return !empty($_SESSION['user_id']) && current_user() !== null;
}

/**
 * Lấy thông tin tài khoản hiện tại từ database
 */
function current_user(): ?array {
    global $db;
    if (empty($_SESSION['user_id']) || !$db) {
        return null;
    }

    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    try {
        $stmt = $db->prepare("SELECT id, public_id, username, email, fullname, avatar, balance, total_deposited, role, status, password FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $cachedUser = $stmt->fetch() ?: null;
        if ($cachedUser && ($cachedUser['status'] !== 'active'
            || !hash_equals(hash('sha256', $cachedUser['password']), (string)($_SESSION['auth_password_tag'] ?? '')))) {
            unset($_SESSION['user_id'], $_SESSION['user_role']);
            $cachedUser = null;
        }
        if ($cachedUser) { unset($cachedUser['password']); }
        return $cachedUser;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Lấy URL avatar người dùng an toàn, tự sinh SVG avatar đẹp nếu file ảnh bị thiếu hoặc lỗi
 */
function get_user_avatar(?array $user): string {
    $displayName = !empty($user['fullname']) ? $user['fullname'] : (!empty($user['username']) ? $user['username'] : 'User');
    $defaultAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($displayName) . '&background=6366f1&color=fff&bold=true&rounded=true';

    if (!$user || empty($user['avatar'])) {
        return $defaultAvatar;
    }

    $avatar = trim($user['avatar']);

    // Nếu là URL online (Google, Facebook, Unsplash, v.v.)
    if (preg_match('#^https?://#i', $avatar)) {
        return $avatar;
    }

    // Nếu là file cục bộ (assets/uploads/avatars/...)
    if ($avatar === 'assets/images/default-avatar.png') {
        return $defaultAvatar;
    }

    $localPath = ROOT_PATH . '/' . ltrim($avatar, '/');
    if (file_exists($localPath)) {
        return BASE_URL . '/' . ltrim($avatar, '/');
    }

    return $defaultAvatar;
}

/**
 * Kiểm tra xem user hiện tại có phải là Admin không
 */
function is_admin(): bool {
    $user = current_user();
    return $user !== null && $user['role'] === 'admin';
}

/**
 * Yêu cầu đăng nhập, nếu chưa đăng nhập thì chuyển hướng
 */
function require_login(string $redirectUrl = ''): void {
    if (!is_logged()) {
        $redirect = !empty($redirectUrl) ? $redirectUrl : $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "/login?redirect=" . urlencode($redirect));
        exit;
    }
}

/**
 * Trả về JSON Response chuẩn
 */
function json_response(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Chuyển đổi tiêu đề thành Slug SEO-friendly
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text ?: 'n-a');
}

/**
 * Lấy địa chỉ IP thực của người dùng (hỗ trợ Proxy, Cloudflare, Load Balancer)
 */
function getClientIP(): string {
    // Forwarded client headers must not let callers bypass rate limits.
    $ipKeys = ['REMOTE_ADDR'];

    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ipList = explode(',', $_SERVER[$key]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

