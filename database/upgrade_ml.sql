ALTER TABLE users
    ADD COLUMN IF NOT EXISTS farmer_type VARCHAR(20) NOT NULL DEFAULT 'crop',
    ADD COLUMN IF NOT EXISTS growth_stage VARCHAR(20) NOT NULL DEFAULT 'vegetative',
    ADD COLUMN IF NOT EXISTS soil_type VARCHAR(20) NOT NULL DEFAULT 'loamy',
    ADD COLUMN IF NOT EXISTS sms_opt_in TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS sms_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    farmer_email VARCHAR(254) NOT NULL,
    recipient VARCHAR(30) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('submitted','failed') NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'Lunyazi',
    provider_response_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sms_farmer_status_created (farmer_email,status,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Run this migration once on an existing database before deploying the updated PHP forms.
-- New installations should use schema.sql, which already contains these columns and ML tables.
