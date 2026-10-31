<?php
declare(strict_types=1);
// Install before config.php: bootstrap warnings must not corrupt the API body.
$momoOutputLevel = ob_get_level();
ob_start();
header('Content-Type: application/json; charset=utf-8');

function momo_json_response(int $status, array $payload): void
{
    global $momoOutputLevel;
    while (ob_get_level() > $momoOutputLevel) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity) {
        error_log('MoMo PHP error: ' . $message . ' in ' . $file . ':' . $line);
    }
    return true;
});

set_exception_handler(function (Throwable $error) {
    error_log('MoMo API exception: ' . $error->getMessage());
    momo_json_response(500, ['success' => false, 'message' => 'Hệ thống thanh toán gặp lỗi. Vui lòng thử lại sau hoặc liên hệ quản trị viên.']);
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        momo_json_response(500, ['success' => false, 'message' => 'Hệ thống thanh toán gặp lỗi. Vui lòng liên hệ quản trị viên kiểm tra nhật ký PHP.']);
    }
});
