<?php
declare(strict_types=1);
function public_error(Throwable $error): string
{
    error_log('Application error: ' . $error->getMessage());
    return 'Hệ thống gặp lỗi. Vui lòng liên hệ quản trị viên.';
}

function like_pattern(string $literal): string
{
    return '%' . strtr($literal, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
}

function provider_decimal(mixed $value): string|int
{
    if (is_int($value) || is_string($value)) { return $value; }
    if (is_float($value) && is_finite($value)) {
        $text = json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        if (preg_match('/\A[0-9]{1,13}(?:\.[0-9]{1,2})?\z/D', $text)) { return $text; }
    }
    throw new InvalidArgumentException('Invalid provider amount');
}

function card_pin_digest(string $serial, string $pin): string
{
    $key = security_config('CARD_DATA_KEY');
    if (strlen($key) < 32) { throw new RuntimeException('Card data key is not configured'); }
    if ($serial === '' || $pin === '' || strlen($serial) > 50 || strlen($pin) > 50) {
        throw new InvalidArgumentException('Invalid card identifiers');
    }
    return hash_hmac('sha256', $serial . ':' . $pin, $key);
}

function reset_token_digest(string $email, string $otp): string
{
    $key = security_config('RESET_TOKEN_KEY');
    if (strlen($key) < 32) { throw new RuntimeException('Reset token secret not configured'); }
    return hash_hmac('sha256', strtolower($email) . ':' . $otp, $key);
}
// Private deployment overrides are intentionally not shipped in release archives.
function security_config(string $key, string $default = ''): string
{
    if (array_key_exists($key, $_ENV)) {
        if (!is_string($_ENV[$key])) { throw new RuntimeException('Invalid environment value'); }
        return $_ENV[$key];
    }
    $value = getenv($key);
    return $value !== false ? $value : $default;
}

function bank_webhook_authenticated(array $server, string $body, string $hmacSecret, string $apiKey): bool
{
    if (strlen($hmacSecret) >= 32) {
        $timestamp = $server['HTTP_X_SEPAY_TIMESTAMP'] ?? '';
        $signature = $server['HTTP_X_SEPAY_SIGNATURE'] ?? '';
        return is_string($timestamp) && ctype_digit($timestamp)
            && abs(time() - (int)$timestamp) <= 300
            && is_string($signature)
            && hash_equals('sha256=' . hash_hmac('sha256', $timestamp . '.' . $body, $hmacSecret), $signature);
    }
    if ($hmacSecret !== '' || strlen($apiKey) < 32) { return false; }
    $authorization = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    return is_string($authorization) && hash_equals('Apikey ' . $apiKey, $authorization);
}

function card_callback_authenticated(string $key, string $code, string $serial, string $signature, string $requestId): bool
{
    return $key !== '' && $code !== '' && $serial !== '' && $requestId !== ''
        && preg_match('/^[a-f0-9]{32}$/i', $signature)
        && hash_equals(md5($key . $code . $serial), strtolower($signature));
}

function oauth_create_state(string $provider): string
{
    $state = bin2hex(random_bytes(32));
    $_SESSION['oauth_states'][$provider] = ['value' => $state, 'expires' => time() + 600];
    return $state;
}

function oauth_consume_state(string $provider, mixed $state): bool
{
    $stored = $_SESSION['oauth_states'][$provider] ?? null;
    unset($_SESSION['oauth_states'][$provider]);
    return is_array($stored) && is_string($state) && $state !== ''
        && $stored['expires'] >= time() && hash_equals($stored['value'], $state);
}

function safe_redirect_path(mixed $path): string
{
    if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')
        || preg_match('/[\\\\\x00-\x20\x7f]/', $path) || str_contains(rawurldecode($path), '\\')
        || str_starts_with(rawurldecode($path), '//')) { return '/'; }
    return $path;
}

function valid_image_file(string $path, string $extension, int $maxBytes): bool
{
    $mimeTypes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'ico' => 'image/vnd.microsoft.icon'];
    if (!isset($mimeTypes[$extension]) || !is_file($path) || filesize($path) > $maxBytes || filesize($path) <= 0) { return false; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if ($extension === 'ico') { return in_array($mime, ['image/x-icon', 'image/vnd.microsoft.icon'], true); }
    $info = @getimagesize($path);
    return $mime === $mimeTypes[$extension] && is_array($info)
        && $info[0] > 0 && $info[1] > 0 && $info[0] <= 12000 && $info[1] <= 12000;
}

// Shared server-side buckets, independent of cookies; file lock serializes requests.
function security_rate_limit(string $bucket, int $limit, int $window): bool
{
    $directory = security_config('RATE_LIMIT_DIRECTORY', dirname(__DIR__) . '/storage/rate-limits');
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) { return false; }
    $file = @fopen($directory . '/' . hash('sha256', $bucket) . '.json', 'c+');
    if (!$file) { return false; }
    try {
        if (!flock($file, LOCK_EX)) { return false; }
        $record = json_decode(stream_get_contents($file), true);
        if (!is_array($record) || ($record['expires'] ?? 0) <= time()) {
            $record = ['count' => 0, 'expires' => time() + $window];
        }
        if ($record['count'] >= $limit) { return false; }
        $record['count']++;
        rewind($file);
        ftruncate($file, 0);
        return fwrite($file, json_encode($record)) !== false;
    } finally { flock($file, LOCK_UN); fclose($file); }
}

function security_require_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        if (preg_match('#/(api|ajax)/#', $_SERVER['SCRIPT_NAME'] ?? '')) {
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ. Vui lòng tải lại trang.'], JSON_UNESCAPED_UNICODE));
        }
        exit('Yêu cầu không hợp lệ. Vui lòng tải lại trang.');
    }
}
