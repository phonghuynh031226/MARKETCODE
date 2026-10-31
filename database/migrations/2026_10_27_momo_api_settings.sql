-- Add MoMo gateway settings to the existing key/value settings table.
-- Existing values are preserved when this migration is run more than once.
INSERT IGNORE INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES
('momo_partner_code', '', 'MoMo Partner Code'),
('momo_access_key', '', 'MoMo Access Key'),
('momo_secret_key', '', 'MoMo Secret Key'),
('momo_api_endpoint', 'https://payment.momo.vn/v2/gateway/api/create', 'MoMo API Endpoint');
