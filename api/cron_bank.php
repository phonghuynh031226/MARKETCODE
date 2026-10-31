<?php
declare(strict_types=1);

// Background state changes are CLI-only; no GET endpoint can mutate payment state.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';

try {
    if (!$db instanceof PDO) { throw new RuntimeException('Database unavailable'); }
    $db->beginTransaction();
    $bank = $db->prepare("SELECT id FROM bank_transactions WHERE status = 'pending' AND deleted_at IS NULL AND created_at < NOW() - INTERVAL 120 MINUTE FOR UPDATE");
    $bank->execute();
    $bankIds = $bank->fetchAll(PDO::FETCH_COLUMN);
    $cancelBank = $db->prepare("UPDATE bank_transactions SET status = 'cancelled' WHERE id = ? AND status = 'pending'");
    foreach ($bankIds as $id) { $cancelBank->execute([(int)$id]); }
    $card = $db->prepare("SELECT id FROM card_transactions WHERE status = 'pending' AND deleted_at IS NULL AND created_at < NOW() - INTERVAL 24 HOUR FOR UPDATE");
    $card->execute();
    $cardIds = $card->fetchAll(PDO::FETCH_COLUMN);
    $cancelCard = $db->prepare("UPDATE card_transactions SET status = 'error', response_message = 'Hết thời gian chờ duyệt' WHERE id = ? AND status = 'pending'");
    foreach ($cardIds as $id) { $cancelCard->execute([(int)$id]); }
    audit_event($db, 'payment.expire_pending', 'payments');
    $db->commit();
    echo json_encode(['success' => true, 'expired_bank' => count($bankIds), 'expired_card' => count($cardIds)], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) { $db->rollBack(); }
    error_log('Payment expiration failed: ' . $error->getMessage());
    echo json_encode(['success' => false, 'message' => 'Background task failed'], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(1);
}
