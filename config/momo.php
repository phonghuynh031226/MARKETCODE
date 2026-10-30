<?php
/**
 * MoMo Gateway configuration.
 * Credentials and endpoint are managed in the admin settings page;
 * environment variables remain available as a fallback for deployments.
 */
$productionEndpoint = 'https://payment.momo.vn/v2/gateway/api/create';
$sandboxEndpoint = 'https://test-payment.momo.vn/v2/gateway/api/create';
$storedEndpoint = trim((string)get_setting('momo_api_endpoint', ''));
$endpoint = $storedEndpoint !== ''
    ? $storedEndpoint
    : trim((string)(getenv('MOMO_API_ENDPOINT') ?: $productionEndpoint));
$allowedEndpoints = [$productionEndpoint, $sandboxEndpoint];
if (!in_array($endpoint, $allowedEndpoints, true)) {
    $endpoint = '';
}

$partnerCode = trim((string)get_setting('momo_partner_code', ''));
$accessKey = trim((string)get_setting('momo_access_key', ''));
$secretKey = trim((string)get_setting('momo_secret_key', ''));

$publicBaseUrl = trim((string)get_setting('momo_public_base_url', ''));
if ($publicBaseUrl === '') {
    $publicBaseUrl = trim((string)(getenv('MOMO_PUBLIC_BASE_URL') ?: ''));
}
if ($publicBaseUrl === '' && defined('BASE_URL')) {
    $publicBaseUrl = rtrim(BASE_URL, '/');
}
// Tự động nâng cấp lên HTTPS cho domain công khai (để MoMo IPN có thể gọi webhook)
if (!empty($publicBaseUrl) && !str_starts_with($publicBaseUrl, 'https://')) {
    $parsedHost = parse_url($publicBaseUrl, PHP_URL_HOST);
    if ($parsedHost && !in_array($parsedHost, ['localhost', '127.0.0.1'], true) && !filter_var($parsedHost, FILTER_VALIDATE_IP)) {
        $publicBaseUrl = preg_replace('#^http://#i', 'https://', $publicBaseUrl);
    }
}

return [
    'environment' => $endpoint === $sandboxEndpoint ? 'sandbox' : 'production',
    'endpoint' => $endpoint,
    'partner_code' => $partnerCode !== '' ? $partnerCode : trim((string)(getenv('MOMO_PARTNER_CODE') ?: '')),
    'access_key' => $accessKey !== '' ? $accessKey : trim((string)(getenv('MOMO_ACCESS_KEY') ?: '')),
    'secret_key' => $secretKey !== '' ? $secretKey : trim((string)(getenv('MOMO_SECRET_KEY') ?: '')),
    'partner_name' => trim((string)(getenv('MOMO_PARTNER_NAME') ?: 'Web Ban Code')),
    'store_id' => trim((string)(getenv('MOMO_STORE_ID') ?: 'WebBanCode')),
    'public_base_url' => $publicBaseUrl,
    'request_timeout' => 35,
];