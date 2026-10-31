<?php
declare(strict_types=1);

// Run with a deployment database account; the web account must not have DDL rights.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/identities.php';

final class MigrationPrecondition extends RuntimeException {}

function migration_column(PDO $db, string $table, string $column): array|false
{
    $stmt = $db->prepare('SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLLATION_NAME, DATETIME_PRECISION FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$table, $column]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function migration_unique(PDO $db, string $table, string $column): bool
{
    $stmt = $db->prepare('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING COUNT(*) = 1 AND MAX(COLUMN_NAME) = ?');
    $stmt->execute([$table, $column]);
    return $stmt->fetchColumn() !== false;
}

function migration_composite_unique(PDO $db, string $table, array $columns): bool
{
    $stmt = $db->prepare('SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND NON_UNIQUE = 0 GROUP BY INDEX_NAME HAVING GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR \',\') = ? AND SUM(CASE WHEN SUB_PART IS NULL THEN 0 ELSE 1 END) = 0');
    $stmt->execute([$table, implode(',', $columns)]);
    return $stmt->fetchColumn() !== false;
}

$db = null;
$locked = false;
$lockName = '';
try {
    $db = Database::getInstance();
    $version = (string)$db->getAttribute(PDO::ATTR_SERVER_VERSION);
    if (preg_match('/[0-9]+\.[0-9]+\.[0-9]+/', $version, $versionParts) !== 1
        || version_compare($versionParts[0], str_contains(strtolower($version), 'mariadb') ? '10.2.1' : '8.0.16', '<')) {
        throw new MigrationPrecondition('database_version_must_enforce_check_constraints');
    }
    $schema = $db->prepare('SELECT DATABASE()');
    $schema->execute();
    $lockName = 'security-schema:' . substr(hash('sha256', (string)$schema->fetchColumn()), 0, 40);
    $lock = $db->prepare('SELECT GET_LOCK(?, 30)');
    $lock->execute([$lockName]);
    if ((int)$lock->fetchColumn() !== 1) { throw new MigrationPrecondition('migration_lock_unavailable'); }
    $locked = true;

    // DDL auto-commits in MySQL/MariaDB: never wrap it in a transaction.
    $db->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NOT NULL, token VARCHAR(64) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $momo = file_get_contents(__DIR__ . '/migrations/2026_10_27_create_momo_transactions.sql');
    if ($momo === false) { throw new MigrationPrecondition('missing_momo_base_migration'); }
    if (preg_match('/CREATE TABLE IF NOT EXISTS `momo_transactions`\s*\([\s\S]*?;/', $momo, $match) !== 1) {
        throw new MigrationPrecondition('invalid_momo_base_migration');
    }
    $db->exec($match[0]);

    // Stop rather than rewriting financial history to satisfy constraints.
    foreach (['users' => ['balance', 'total_deposited'], 'products' => ['price', 'sale_price']] as $table => $columns) {
        foreach ($columns as $column) {
            $check = $db->prepare("SELECT COUNT(*) FROM `$table` WHERE `$column` < 0");
            $check->execute();
            if ((int)$check->fetchColumn() !== 0) { throw new MigrationPrecondition('negative_financial_values_require_manual_review'); }
        }
    }

    $summary = ['version' => '2026_10_31_security_schema', 'backfilled' => []];
    foreach (['users', 'categories', 'products', 'orders', 'bank_transactions', 'card_transactions', 'momo_transactions'] as $candidate) {
        $table = identity_table($candidate); // The only source of interpolated resource table identifiers.
        if (!migration_column($db, $table, 'public_id')) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL");
        }
        if (!migration_column($db, $table, 'deleted_at')) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN deleted_at DATETIME(6) NULL");
        }
        $deletedColumn = migration_column($db, $table, 'deleted_at');
        if ($deletedColumn['DATA_TYPE'] !== 'datetime' || (int)$deletedColumn['DATETIME_PRECISION'] !== 6 || $deletedColumn['IS_NULLABLE'] !== 'YES') {
            $db->exec("ALTER TABLE `$table` MODIFY COLUMN deleted_at DATETIME(6) NULL");
        }
        $invalid = $db->prepare("SELECT public_id FROM `$table` WHERE public_id IS NOT NULL AND public_id <> ''");
        $invalid->execute();
        while (($publicId = $invalid->fetchColumn()) !== false) {
            if (!valid_public_id($publicId)) { throw new MigrationPrecondition('existing_invalid_public_id_requires_manual_review'); }
        }
        $summary['backfilled'][$table] = 0;
        do {
            $db->beginTransaction();
            $batch = $db->prepare("SELECT id FROM `$table` WHERE public_id IS NULL OR public_id = '' ORDER BY id LIMIT 250 FOR UPDATE");
            $batch->execute();
            $ids = $batch->fetchAll(PDO::FETCH_COLUMN);
            $update = $db->prepare("UPDATE `$table` SET public_id = ? WHERE id = ? AND (public_id IS NULL OR public_id = '')");
            foreach ($ids as $id) {
                $update->execute([uuid_v4(), $id]);
                $summary['backfilled'][$table] += $update->rowCount();
            }
            $db->commit();
        } while ($ids !== []);
        if (!migration_unique($db, $table, 'public_id')) {
            $db->exec("ALTER TABLE `$table` ADD UNIQUE KEY `uk_security_public_id` (public_id)");
        }
        $column = migration_column($db, $table, 'public_id');
        if ($column['IS_NULLABLE'] !== 'NO' || $column['DATA_TYPE'] !== 'char' || (int)$column['CHARACTER_MAXIMUM_LENGTH'] !== 36 || $column['COLLATION_NAME'] !== 'ascii_bin') {
            $db->exec("ALTER TABLE `$table` MODIFY COLUMN public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL");
        }
    }

    // Widen account fields without losing existing values or changing nullability.
    foreach (['email' => [254, 'NO'], 'fullname' => [255, 'YES']] as $field => [$length, $nullable]) {
        $tooLong = $db->prepare("SELECT COUNT(*) FROM users WHERE CHAR_LENGTH(`$field`) > ?");
        $tooLong->execute([$length]);
        if ((int)$tooLong->fetchColumn() !== 0) { throw new MigrationPrecondition('account_field_length_requires_manual_review'); }
        $definition = migration_column($db, 'users', $field);
        if ($definition['DATA_TYPE'] !== 'varchar' || (int)$definition['CHARACTER_MAXIMUM_LENGTH'] !== $length || $definition['IS_NULLABLE'] !== $nullable || $definition['COLLATION_NAME'] !== 'utf8mb4_unicode_ci') {
            $nullSql = $nullable === 'YES' ? 'NULL' : 'NOT NULL';
            $db->exec("ALTER TABLE users MODIFY COLUMN `$field` VARCHAR($length) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci $nullSql");
        }
    }
    $oauthDuplicates = $db->prepare('SELECT 1 FROM users WHERE oauth_provider IS NOT NULL AND oauth_uid IS NOT NULL GROUP BY oauth_provider, oauth_uid HAVING COUNT(*) > 1 LIMIT 1');
    $oauthDuplicates->execute();
    if ($oauthDuplicates->fetchColumn() !== false) { throw new MigrationPrecondition('duplicate_oauth_identity_requires_manual_review'); }
    if (!migration_composite_unique($db, 'users', ['oauth_provider', 'oauth_uid'])) {
        $db->exec('ALTER TABLE users ADD UNIQUE KEY uk_users_oauth_identity (oauth_provider, oauth_uid)');
    }

    if (!migration_column($db, 'card_transactions', 'pin_digest')) {
        $db->exec('ALTER TABLE card_transactions ADD COLUMN pin_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL');
    }
    $rawCards = $db->prepare("SELECT COUNT(*) FROM card_transactions WHERE pin IS NOT NULL AND pin <> ''");
    $rawCards->execute();
    $cardKey = '';
    if ((int)$rawCards->fetchColumn() > 0) {
        $environmentKey = $_ENV['CARD_DATA_KEY'] ?? getenv('CARD_DATA_KEY');
        $cardKey = is_string($environmentKey) ? $environmentKey : '';
        if (strlen($cardKey) < 32) { throw new MigrationPrecondition('legacy_card_data_requires_card_data_key'); }
    }
    // Validate all rows before clearing any raw PIN. Never print card material.
    $cards = $db->prepare('SELECT pin, pin_digest FROM card_transactions');
    $cards->execute();
    while ($card = $cards->fetch(PDO::FETCH_ASSOC)) {
        $validDigest = is_string($card['pin_digest']) && preg_match('/\A[a-f0-9]{64}\z/D', $card['pin_digest']) === 1;
        if (!$validDigest && (!is_string($card['pin']) || $card['pin'] === '')) {
            throw new MigrationPrecondition('missing_card_pin_and_digest_requires_manual_review');
        }
    }
    unset($card);
    $summary['card_pin_digests_backfilled'] = 0;
    do {
        $db->beginTransaction();
        $cardBatch = $db->prepare("SELECT id, serial, pin, pin_digest FROM card_transactions WHERE pin IS NOT NULL AND pin <> '' ORDER BY id LIMIT 250 FOR UPDATE");
        $cardBatch->execute();
        $legacyCards = $cardBatch->fetchAll(PDO::FETCH_ASSOC);
        $saveCard = $db->prepare("UPDATE card_transactions SET pin_digest = ?, pin = '', updated_at = updated_at WHERE id = ?");
        foreach ($legacyCards as $legacyCard) {
            if (strlen($cardKey) < 32) { throw new MigrationPrecondition('legacy_card_data_requires_card_data_key'); }
            $existingDigest = $legacyCard['pin_digest'];
            $digest = is_string($existingDigest) && preg_match('/\A[a-f0-9]{64}\z/D', $existingDigest) === 1
                ? $existingDigest : hash_hmac('sha256', (string)$legacyCard['serial'] . ':' . (string)$legacyCard['pin'], $cardKey);
            $saveCard->execute([$digest, $legacyCard['id']]);
            $summary['card_pin_digests_backfilled'] += $saveCard->rowCount();
        }
        $db->commit();
        unset($legacyCard, $existingDigest, $digest);
    } while ($legacyCards !== []);
    unset($legacyCards, $cardKey);
    $duplicateCards = $db->prepare('SELECT 1 FROM card_transactions GROUP BY serial, pin_digest HAVING COUNT(*) > 1 LIMIT 1');
    $duplicateCards->execute();
    if ($duplicateCards->fetchColumn() !== false) { throw new MigrationPrecondition('duplicate_card_identity_requires_manual_review'); }
    $digestColumn = migration_column($db, 'card_transactions', 'pin_digest');
    if ($digestColumn['DATA_TYPE'] !== 'char' || (int)$digestColumn['CHARACTER_MAXIMUM_LENGTH'] !== 64 || $digestColumn['IS_NULLABLE'] !== 'NO' || $digestColumn['COLLATION_NAME'] !== 'ascii_bin') {
        $db->exec('ALTER TABLE card_transactions MODIFY COLUMN pin_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL');
    }
    if (!migration_composite_unique($db, 'card_transactions', ['serial', 'pin_digest'])) {
        $db->exec('ALTER TABLE card_transactions ADD UNIQUE KEY uk_card_serial_digest (serial, pin_digest)');
    }

    $db->beginTransaction();
    // Only obsolete token formats are invalidated. Keep newest valid token per email.
    $summary['obsolete_reset_tokens'] = $db->exec('DELETE FROM password_resets WHERE CHAR_LENGTH(token) <> 64');
    $summary['duplicate_reset_tokens'] = $db->exec('DELETE old FROM password_resets old JOIN password_resets newer ON newer.email = old.email AND (COALESCE(newer.created_at, \'1000-01-01\') > COALESCE(old.created_at, \'1000-01-01\') OR (COALESCE(newer.created_at, \'1000-01-01\') = COALESCE(old.created_at, \'1000-01-01\') AND newer.id > old.id))');
    $db->commit();
    $tokenColumn = migration_column($db, 'password_resets', 'token');
    if ($tokenColumn['DATA_TYPE'] !== 'varchar' || (int)$tokenColumn['CHARACTER_MAXIMUM_LENGTH'] !== 64) {
        $db->exec('ALTER TABLE password_resets MODIFY COLUMN token VARCHAR(64) NOT NULL');
    }
    if (!migration_unique($db, 'password_resets', 'email')) {
        $db->exec('ALTER TABLE password_resets ADD UNIQUE KEY uk_password_resets_email (email)');
    }

    $auditSql = file_get_contents(__DIR__ . '/migrations/2026_10_31_security_schema.sql');
    if ($auditSql === false) { throw new MigrationPrecondition('missing_security_schema_sql'); }
    $db->exec($auditSql);
    foreach (['users' => ['balance', 'total_deposited'], 'products' => ['price', 'sale_price']] as $table => $columns) {
        foreach ($columns as $column) {
            $name = 'chk_security_' . $column . '_nonnegative';
            $exists = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'CHECK'");
            $exists->execute([$table, $name]);
            if ((int)$exists->fetchColumn() === 0) {
                $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$name` CHECK (`$column` >= 0)");
            }
        }
    }
    // Exact, closed whitelist of baseline relationships. No database-derived identifier
    // is ever interpolated into DDL. Preserve all historical child rows.
    $relationships = [
        ['balance_logs', 'fk_balance_user', 'user_id', 'users', 'id'],
        ['bank_transactions', 'fk_bank_user', 'user_id', 'users', 'id'],
        ['card_transactions', 'fk_card_user', 'user_id', 'users', 'id'],
        ['momo_transactions', 'fk_momo_user', 'user_id', 'users', 'id'],
        ['orders', 'fk_orders_product', 'product_id', 'products', 'id'],
        ['orders', 'fk_orders_user', 'user_id', 'users', 'id'],
        ['products', 'fk_products_category', 'category_id', 'categories', 'id'],
    ];
    $summary['restricted_foreign_keys'] = 0;
    foreach ($relationships as [$table, $constraint, $column, $parent, $parentColumn]) {
        $metadata = $db->prepare('SELECT k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.CONSTRAINT_SCHEMA = DATABASE() AND k.TABLE_NAME = ? AND k.CONSTRAINT_NAME = ? ORDER BY k.ORDINAL_POSITION');
        $metadata->execute([$table, $constraint]);
        $keys = $metadata->fetchAll(PDO::FETCH_ASSOC);
        if ($keys !== [] && (count($keys) !== 1 || $keys[0]['COLUMN_NAME'] !== $column || $keys[0]['REFERENCED_TABLE_NAME'] !== $parent || $keys[0]['REFERENCED_COLUMN_NAME'] !== $parentColumn)) {
            throw new RuntimeException('Unexpected baseline relationship');
        }
        $orphans = $db->prepare("SELECT COUNT(*) FROM `$table` child LEFT JOIN `$parent` parent ON parent.`$parentColumn` = child.`$column` WHERE child.`$column` IS NOT NULL AND parent.`$parentColumn` IS NULL");
        $orphans->execute();
        if ((int)$orphans->fetchColumn() !== 0) { throw new RuntimeException('Orphaned relationship requires manual review'); }
        if ($keys !== [] && $keys[0]['DELETE_RULE'] === 'RESTRICT' && $keys[0]['UPDATE_RULE'] === 'RESTRICT') { continue; }
        // MariaDB cannot reuse the same FK name in a single DROP+ADD ALTER.
        // If interrupted after DROP, the next run observes the missing FK and restores it.
        if ($keys !== []) { $db->exec("ALTER TABLE `$table` DROP FOREIGN KEY `$constraint`"); }
        $db->exec("ALTER TABLE `$table` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$column`) REFERENCES `$parent` (`$parentColumn`) ON DELETE RESTRICT ON UPDATE RESTRICT");
        $summary['restricted_foreign_keys']++;
    }
    // Legacy secret settings are no longer used; environment values are authoritative.
    $legacySecrets = ['momo_partner_code','momo_access_key','momo_secret_key','card_partner_id','card_partner_key',
        'oauth_google_client_id','oauth_google_client_secret','oauth_facebook_app_id','oauth_facebook_app_secret',
        'oauth_zalo_app_id','oauth_zalo_secret_key','recaptcha_secret_key','cron_security_key'];
    $clearSecret = $db->prepare("UPDATE settings SET setting_value = '' WHERE setting_key = ?");
    foreach ($legacySecrets as $legacyKey) { $clearSecret->execute([$legacyKey]); }
    echo json_encode(['success' => true] + $summary, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    if ($db instanceof PDO && $db->inTransaction()) { $db->rollBack(); }
    error_log('Security schema migration failed (' . get_class($error) . '; code ' . $error->getCode() . ')');
    echo json_encode(['success' => false, 'error' => $error instanceof MigrationPrecondition ? $error->getMessage() : 'migration_failed_review_database_configuration_and_schema'], JSON_THROW_ON_ERROR) . PHP_EOL;
    $failed = true;
} finally {
    if ($locked && $db instanceof PDO) {
        try { $release = $db->prepare('SELECT RELEASE_LOCK(?)'); $release->execute([$lockName]); }
        catch (Throwable) { error_log('Security schema migration lock release failed'); }
    }
}
exit(isset($failed) ? 1 : 0);

