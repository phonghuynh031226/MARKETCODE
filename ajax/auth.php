<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/passwords.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Phương thức không hợp lệ!');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrfToken)) {
    json_response(false, 'Mã bảo mật CSRF Token không hợp lệ hoặc đã hết hạn! Vui lòng tải lại trang.');
}

if (!$db) {
    json_response(false, 'Lỗi kết nối cơ sở dữ liệu! Vui lòng cấu hình file config/database.php.');
}

$action = trim($_POST['action'] ?? '');

if ($action === 'login') {
    $account = trim($_POST['account'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = safe_redirect_path($_POST['redirect'] ?? '/');
    $loginIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!security_rate_limit('login-ip:' . $loginIp, 30, 900)
        || !security_rate_limit('login-account:' . strtolower($account), 10, 900)) {
        http_response_code(429);
        json_response(false, 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau 15 phút.');
    }

    if (empty($account) || !is_string($password) || $password === '' || strlen($password) > 1024) {
        json_response(false, 'Vui lòng nhập đầy đủ tên đăng nhập/email và mật khẩu!');
    }

    $captchaToken = $_POST['g-recaptcha-response'] ?? ($_POST['cf-turnstile-response'] ?? '');
    if (!verify_captcha($captchaToken)) {
        json_response(false, 'Xác thực Captcha chống bot thất bại! Vui lòng thử lại.');
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT * FROM users WHERE (username = ? OR email = ?) AND deleted_at IS NULL LIMIT 1 FOR UPDATE");
        $stmt->execute([$account, $account]);
        $user = $stmt->fetch();

        if (!$user || !app_password_verify($password, (string)$user['password']) || $user['status'] === 'banned') {
            $db->rollBack();
            json_response(false, 'Thông tin đăng nhập không chính xác!');
        }
        if (app_password_needs_rehash($user['password'])) {
            $upgradedHash = app_password_hash($password, true);
            $upgrade = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $upgrade->execute([$upgradedHash, $user['id']]);
            $user['password'] = $upgradedHash;
        }
        $db->commit();

        // Đăng nhập thành công, tái tạo Session ID để chống Session Fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['auth_password_tag'] = hash('sha256', $user['password']);
        $_SESSION['just_logged_in'] = true;
        $_SESSION['flash_message'] = [
            'type'  => 'success',
            'title' => '🎉 Đăng nhập thành công! Chào mừng ' . ($user['fullname'] ?: $user['username']) . ' quay trở lại.'
        ];

        json_response(true, 'Đăng nhập thành công! Đang chuyển hướng...', [
            'redirect' => $redirect
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        json_response(false, 'Lỗi hệ thống: ' . public_error($e));
    }
}

if ($action === 'register') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $redirect = safe_redirect_path($_POST['redirect'] ?? '/');
    if (!security_rate_limit('register-ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 3600)) {
        http_response_code(429);
        json_response(false, 'Bạn đã tạo quá nhiều yêu cầu đăng ký. Vui lòng thử lại sau.');
    }

    if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
        json_response(false, 'Vui lòng điền đầy đủ tất cả các trường thông tin!');
    }

    $captchaToken = $_POST['g-recaptcha-response'] ?? ($_POST['cf-turnstile-response'] ?? '');
    if (!verify_captcha($captchaToken)) {
        json_response(false, 'Xác thực Captcha chống bot thất bại! Vui lòng thử lại.');
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        json_response(false, 'Tên đăng nhập từ 3 - 30 ký tự, chỉ gồm chữ, số và dấu gạch dưới!');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(false, 'Địa chỉ email không đúng định dạng!');
    }

    if (strlen($password) < 12 || strlen($password) > 1024) {
        json_response(false, 'Mật khẩu phải có từ 12 đến 1024 ký tự!');
    }

    if (!hash_equals($password, $passwordConfirm)) {
        json_response(false, 'Mật khẩu xác nhận không trùng khớp!');
    }

    try {
        // Kiểm tra trùng username hoặc email
        $checkStmt = $db->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $checkStmt->execute([$username, $email]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ($existing['username'] === $username) {
                json_response(false, 'Không thể tạo tài khoản với thông tin này.');
            }
            if ($existing['email'] === $email) {
                json_response(false, 'Không thể tạo tài khoản với thông tin này.');
            }
        }

        // HMAC-SHA256 with environment pepper, then Argon2id
        $hashPassword = app_password_hash($password);

        $insertStmt = $db->prepare("INSERT INTO users (public_id, username, email, password, fullname, role, status) VALUES (?, ?, ?, ?, ?, 'user', 'active')");
        $insertStmt->execute([uuid_v4(), $username, $email, $hashPassword, $fullname]);
        $newUserId = (int)$db->lastInsertId();

        // Tự động đăng nhập
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['user_role'] = 'user';
        $_SESSION['auth_password_tag'] = hash('sha256', $hashPassword);
        $_SESSION['just_logged_in'] = true;
        $_SESSION['flash_message'] = [
            'type'  => 'success',
            'title' => '🎉 Đăng ký thành công! Chào mừng ' . ($fullname ?: $username) . ' đến với MARKETCODE.'
        ];

        json_response(true, 'Đăng ký tài khoản thành công! Đang chuyển hướng...', [
            'redirect' => $redirect
        ]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        json_response(false, 'Lỗi hệ thống: ' . public_error($e));
    }
}

json_response(false, 'Hành động không được hỗ trợ!');
