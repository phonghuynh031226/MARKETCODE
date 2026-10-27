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

return [
    'environment' => $endpoint === $sandboxEndpoint ? 'sandbox' : 'production',
    'endpoint' => $endpoint,
    'partner_code' => $partnerCode !== '' ? $partnerCode : trim((string)(getenv('MOMO_PARTNER_CODE') ?: '')),
    'access_key' => $accessKey !== '' ? $accessKey : trim((string)(getenv('MOMO_ACCESS_KEY') ?: '')),
    'secret_key' => $secretKey !== '' ? $secretKey : trim((string)(getenv('MOMO_SECRET_KEY') ?: '')),
    'partner_name' => trim((string)(getenv('MOMO_PARTNER_NAME') ?: 'Web Ban Code')),
    'store_id' => trim((string)(getenv('MOMO_STORE_ID') ?: 'WebBanCode')),
    'public_base_url' => rtrim((string)(getenv('MOMO_PUBLIC_BASE_URL') ?: ''), '/'),
    'request_timeout' => 35,
];