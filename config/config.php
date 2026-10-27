<?php
/**
 * ========================================================
 * Cấu hình Hệ thống, Khởi tạo Session & Helper Functions
 * ========================================================
 */

// Bật hiển thị lỗi trong môi trường phát triển (tắt khi lên Production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Cấu hình Timezone Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Cấu hình Session Cookie an toàn (Chống Session Hijacking và XSS đánh cắp cookie)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7, // 7 ngày
        'path'     => '/',
        'domain'   => '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Định nghĩa đường dẫn
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', __DIR__);

// Tự động nhận diện Base URL linh hoạt cho Localhost hoặc Server
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$scriptDir = rtrim($scriptDir, '/');
// Nếu file nằm trong folder con (như /ajax, /api, /auth, /user hay /admin) thì chuẩn hóa về thư mục gốc của project
$baseDir = preg_replace('/(\/(admin|user|ajax|api|auth|config|includes))$/i', '', $scriptDir);
define('BASE_URL', $protocol . $host . $baseDir);

// Nhúng kết nối CSDL PDO
require_once CONFIG_PATH . '/database.php';

try {
    $db = Database::getInstance();
    // Tự động khởi tạo bảng password_resets nếu chưa có
    $db->exec("CREATE TABLE IF NOT EXISTS `password_resets` (
        `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `email` VARCHAR(255) NOT NULL,
        `token` VARCHAR(10) NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
} catch (Exception $e) {
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

/**
 * Lấy giá trị cài đặt hệ thống
 */
function get_setting(string $key, string $default = ''): string {
    global $SYSTEM_SETTINGS;
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
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
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
        return true; // Fallback an toàn nếu máy chủ không kết nối được Google
    }
    $response = json_decode($result, true);
    return !empty($response['success']);
}

/**
 * Chống tấn công XSS trên dữ liệu đầu ra
 */
function xss_clean(?string $data): string {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Định dạng tiền tệ VND
 */
function format_money(float|int|string|null $amount): string {
    $amount = (float)($amount ?? 0);
    return number_format($amount, 0, ',', '.') . ' ₫';
}

/**
 * Kiểm tra trạng thái đăng nhập
 */
function is_logged(): bool {
    return !empty($_SESSION['user_id']);
}

/**
 * Lấy thông tin tài khoản hiện tại từ database
 */
function current_user(): ?array {
    global $db;
    if (!is_logged() || !$db) {
        return null;
    }
    
    static $cachedUser = null;
    if ($cachedUser !== null) {
        return $cachedUser;
    }

    try {
        $stmt = $db->prepare("SELECT id, username, email, fullname, avatar, balance, total_deposited, role, status FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $cachedUser = $stmt->fetch() ?: null;
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
    $ipKeys = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];

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

