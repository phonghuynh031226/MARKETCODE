<?php
declare(strict_types=1);

function uuid_v4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-'
        . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function valid_public_id(mixed $value): bool
{
    return is_string($value) && preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $value) === 1;
}

function identity_table(string $table): string
{
    if (!in_array($table, ['users', 'categories', 'products', 'orders', 'bank_transactions', 'card_transactions', 'momo_transactions'], true)) {
        throw new InvalidArgumentException('Unknown resource type');
    }
    return $table;
}

// This only resolves identity; the calling handler must still authorize ownership/role.
function public_id_to_internal(PDO $db, string $table, mixed $value): int
{
    $table = identity_table($table);
    if (!valid_public_id($value)) { return 0; }
    $stmt = $db->prepare("SELECT id FROM `$table` WHERE public_id = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->execute([$value]);
    return (int)($stmt->fetchColumn() ?: 0);
}

function public_reference(string $table, int $id): string
{
    global $db;
    $table = identity_table($table);
    if (!$db instanceof PDO || $id <= 0) { return ''; }
    static $cache = [];
    $key = $table . ':' . $id;
    if (!array_key_exists($key, $cache)) {
        $stmt = $db->prepare("SELECT public_id FROM `$table` WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        $value = $stmt->fetchColumn();
        $cache[$key] = valid_public_id($value) ? $value : '';
    }
    return $cache[$key];
}

function audit_event(PDO $db, string $action, string $resource, ?string $resourcePublicId = null): void
{
    if ($resourcePublicId !== null && !valid_public_id($resourcePublicId)) { throw new InvalidArgumentException('Invalid audit resource'); }
    $actor = $_SESSION['user_id'] ?? null;
    $stmt = $db->prepare('INSERT INTO audit_events (public_id, actor_user_id, action, resource_type, resource_public_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([uuid_v4(), is_int($actor) ? $actor : null, substr($action, 0, 80), substr($resource, 0, 80), $resourcePublicId,
        substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), mb_strcut((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8')]);
}

function browser_record(string $table, array $record): array
{
    identity_table($table);
    if (isset($record['id'])) { $record['id'] = public_reference($table, (int)$record['id']); }
    foreach (['category_id' => 'categories', 'product_id' => 'products', 'user_id' => 'users', 'admin_id' => 'users'] as $column => $type) {
        if (isset($record[$column])) { $record[$column] = public_reference($type, (int)$record[$column]); }
    }
    unset($record['password'], $record['download_token']);
    return $record;
}
