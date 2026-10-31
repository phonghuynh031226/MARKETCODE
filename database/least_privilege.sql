-- TEMPLATE: replace app_database, app_runtime and deployment_host before applying.
-- Create the login account separately using your database secret management.
-- Apply to a fresh role/account: these grants do not revoke historical privileges.
-- Keep deployment/migration credentials separate from this runtime account.
-- No global grants, DDL, FILE, GRANT OPTION, or history deletion privileges.
CREATE ROLE IF NOT EXISTS app_runtime;
GRANT SELECT, INSERT, UPDATE ON app_database.users TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON app_database.categories TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON app_database.products TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON app_database.orders TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON app_database.bank_transactions TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON app_database.card_transactions TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON app_database.momo_transactions TO app_runtime;
GRANT SELECT, INSERT ON app_database.balance_logs TO app_runtime;
GRANT SELECT, INSERT, UPDATE ON app_database.settings TO app_runtime;
GRANT SELECT ON app_database.product_views TO app_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON app_database.password_resets TO app_runtime;
GRANT SELECT, INSERT ON app_database.audit_events TO app_runtime;
-- MariaDB role assignment template (account must already exist):
-- GRANT app_runtime TO 'app_web'@'deployment_host';
-- SET DEFAULT ROLE app_runtime FOR 'app_web'@'deployment_host';
-- MySQL role assignment uses its version-specific quoted role/default-role syntax.
