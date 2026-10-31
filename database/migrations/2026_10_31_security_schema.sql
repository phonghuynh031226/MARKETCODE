-- Apply with database/migrate_security.php. UUIDv4 backfill uses PHP random_bytes(),
-- never database UUID() (which may generate version 1). Internal keys stay intact.
-- CLI stages public_id nullable, backfills locked batches, then adds UNIQUE/NOT NULL.
-- CLI also adds deleted_at, validates nonnegative financial data and adds CHECKs,
-- and upgrades password_resets to a 64-character token plus unique email.
-- CLI replaces whitelisted baseline foreign keys with DELETE/UPDATE RESTRICT;
-- existing orphaned relationships stop migration without deleting history.
CREATE TABLE IF NOT EXISTS audit_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    actor_user_id INT UNSIGNED NULL,
    action VARCHAR(80) NOT NULL,
    resource_type VARCHAR(80) NOT NULL,
    resource_public_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uk_audit_public_id (public_id),
    KEY idx_audit_actor_created (actor_user_id, created_at),
    KEY idx_audit_created (created_at),
    KEY idx_audit_resource (resource_type, resource_public_id, created_at),
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

