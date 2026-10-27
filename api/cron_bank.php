<?php
/**
 * Cron Job kiểm tra đối soát giao dịch ngân hàng
 * Có thể đặt lịch Cron chạy 1 - 2 phút/lần:
 * curl -s http://your-domain.com/api/cron_bank.php > /dev/null 2>&1
 */
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

$apiKey = $_GET['cron_key'] ?? '';
$configuredKey = get_setting('cron_security_key', 'marketcode_cron_secret');

// Bảo mật CRON bằng key
if (!empty($configuredKey) && $apiKey !== $configuredKey && php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized cron access']);
    exit;
}

if (!$db) {
    die(json_encode(['success' => false, 'message' => 'Database error']));
}

$report = [
    'time'             => date('Y-m-d H:i:s'),
    'checked_pending'  => 0,
    'messages'         => []
];

try {
    // 1. Quét các giao dịch bank pending quá 60 phút để đánh dấu timeout/cancelled
    $cancelStmt = $db->prepare("UPDATE bank_transactions SET status = 'cancelled' 
                               WHERE status = 'pending' AND created_at < NOW() - INTERVAL 120 MINUTE");
    $cancelStmt->execute();
    $report['auto_cancelled_stale'] = $cancelStmt->rowCount();

    // 2. Quét thẻ cào pending quá 24h để đánh dấu timeout
    $cardCancel = $db->prepare("UPDATE card_transactions SET status = 'error', response_message = 'Hết thời gian chờ duyệt (Timeout)' 
                               WHERE status = 'pending' AND created_at < NOW() - INTERVAL 24 HOUR");
    $cardCancel->execute();
    $report['auto_cancelled_cards'] = $cardCancel->rowCount();

    echo json_encode([
        'success' => true,
        'report'  => $report
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Cron error: ' . $e->getMessage()
    ]);
}
