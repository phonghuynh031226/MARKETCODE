<?php
require_once __DIR__ . '/../config/config.php';

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
    $redirect = trim($_POST['redirect'] ?? (BASE_URL . '/'));

    if (empty($account) || empty($password)) {
        json_response(false, 'Vui lòng nhập đầy đủ tên đăng nhập/email và mật khẩu!');
    }

    $captchaToken = $_POST['g-recaptcha-response'] ?? ($_POST['cf-turnstile-response'] ?? '');
    if (!verify_captcha($captchaToken)) {
        json_response(false, 'Xác thực Captcha chống bot thất bại! Vui lòng thử lại.');
    }

    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$account, $account]);
        $user = $stmt->fetch();

        if (!$user) {
            json_response(false, 'Tài khoản không tồn tại trên hệ thống!');
        }

        if ($user['status'] === 'banned') {
            json_response(false, 'Tài khoản của bạn đã bị khóa do vi phạm chính sách! Vui lòng liên hệ Admin.');
        }

        if (!password_verify($password, $user['password'])) {
            json_response(false, 'Mật khẩu đăng nhập không chính xác!');
        }

        // Đăng nhập thành công, tái tạo Session ID để chống Session Fixation
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['just_logged_in'] = true;
        $_SESSION['flash_message'] = [
            'type'  => 'success',
            'title' => '🎉 Đăng nhập thành công! Chào mừng ' . ($user['fullname'] ?: $user['username']) . ' quay trở lại.'
        ];

        json_response(true, 'Đăng nhập thành công! Đang chuyển hướng...', [
            'redirect' => $redirect
        ]);
    } catch (Exception $e) {
        json_response(false, 'Lỗi hệ thống: ' . $e->getMessage());
    }
}

if ($action === 'register') {
    $fullname = trim($_POST['fullname'] ?? '');
    $username = strtolower(trim($_POST['username'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $redirect = trim($_POST['redirect'] ?? (BASE_URL . '/'));

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

    if (strlen($password) < 6) {
        json_response(false, 'Mật khẩu phải chứa ít nhất 6 ký tự!');
    }

    if ($password !== $passwordConfirm) {
        json_response(false, 'Mật khẩu xác nhận không trùng khớp!');
    }

    try {
        // Kiểm tra trùng username hoặc email
        $checkStmt = $db->prepare("SELECT id, username, email FROM users WHERE username = ? OR email = ? LIMIT 1");
        $checkStmt->execute([$username, $email]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            if ($existing['username'] === $username) {
                json_response(false, 'Tên đăng nhập này đã được sử dụng!');
            }
            if ($existing['email'] === $email) {
                json_response(false, 'Địa chỉ email này đã được đăng ký!');
            }
        }

        // Mã hóa mật khẩu an toàn với BCRYPT
        $hashPassword = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);

        $insertStmt = $db->prepare("INSERT INTO users (username, email, password, fullname, role, status) VALUES (?, ?, ?, ?, 'user', 'active')");
        $insertStmt->execute([$username, $email, $hashPassword, $fullname]);
        $newUserId = (int)$db->lastInsertId();

        // Tự động đăng nhập
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newUserId;
        $_SESSION['user_role'] = 'user';
        $_SESSION['just_logged_in'] = true;
        $_SESSION['flash_message'] = [
            'type'  => 'success',
            'title' => '🎉 Đăng ký thành công! Chào mừng ' . ($fullname ?: $username) . ' đến với MARKETCODE.'
        ];

        json_response(true, 'Đăng ký tài khoản thành công! Đang chuyển hướng...', [
            'redirect' => $redirect
        ]);
    } catch (Exception $e) {
        json_response(false, 'Lỗi hệ thống: ' . $e->getMessage());
    }
}

json_response(false, 'Hành động không được hỗ trợ!');
