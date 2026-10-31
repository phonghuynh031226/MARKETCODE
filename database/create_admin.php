<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/identities.php';
require_once __DIR__ . '/../includes/passwords.php';

$db = null;
$locked = false;
try {
    $username = security_config('BOOTSTRAP_ADMIN_USERNAME');
    $email = strtolower(trim(security_config('BOOTSTRAP_ADMIN_EMAIL')));
    $password = security_config('BOOTSTRAP_ADMIN_PASSWORD');
    if (!preg_match('/\A[a-zA-Z0-9_]{3,30}\z/D', $username) || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid bootstrap account');
    }
    $hash = app_password_hash($password);
    $db = Database::getInstance();
    $lock = $db->prepare("SELECT GET_LOCK('marketcode:first-admin', 10)"); $lock->execute();
    if ((int)$lock->fetchColumn() !== 1) { throw new RuntimeException('Bootstrap busy'); }
    $locked = true;
    $db->beginTransaction();
    $existing = $db->prepare("SELECT id FROM users WHERE role = 'admin' AND deleted_at IS NULL LIMIT 1 FOR UPDATE");
    $existing->execute();
    if ($existing->fetchColumn() !== false) { throw new RuntimeException('An administrator already exists'); }
    $insert = $db->prepare("INSERT INTO users (public_id,username,email,password,fullname,role,status) VALUES (?,?,?,?,?,'admin','active')");
    $id = uuid_v4();
    $insert->execute([$id, $username, $email, $hash, 'Administrator']);
    audit_event($db, 'admin.bootstrap', 'users', $id);
    $db->commit();
    echo json_encode(['success' => true, 'public_id' => $id], JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) { $db->rollBack(); }
    error_log('Administrator bootstrap failed (' . get_class($error) . ')');
    echo json_encode(['success' => false, 'message' => 'Check required environment values; bootstrap refuses to replace existing accounts.'], JSON_THROW_ON_ERROR) . PHP_EOL;
    $failed = true;
} finally {
    if ($locked && $db instanceof PDO) {
        try { $release = $db->prepare("SELECT RELEASE_LOCK('marketcode:first-admin')"); $release->execute(); }
        catch (Throwable) { error_log('Bootstrap lock release failed'); }
    }
}
exit(isset($failed) ? 1 : 0);
