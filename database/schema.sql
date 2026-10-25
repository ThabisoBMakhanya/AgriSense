CREATE TABLE IF NOT EXISTS users (
    email VARCHAR(254) NOT NULL PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(30) NULL,
    password_hash VARCHAR(255) NOT NULL,
    region VARCHAR(30) NULL,
    weather_location VARCHAR(100) NULL,
    farm_size DECIMAL(6, 2) NULL,
    crop_type VARCHAR(60) NULL,
    soil_quality TINYINT UNSIGNED NULL,
    farmer_type VARCHAR(20) NOT NULL DEFAULT 'crop',
    growth_stage VARCHAR(20) NOT NULL DEFAULT 'vegetative',
    soil_type VARCHAR(20) NOT NULL DEFAULT 'loamy',
    sms_opt_in TINYINT(1) NOT NULL DEFAULT 0,
    registered_at DATETIME NULL,
    last_login DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS weather_cache (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    location VARCHAR(120) NOT NULL UNIQUE,
    temp_c DECIMAL(5,2) NOT NULL,
    humidity_pct TINYINT UNSIGNED NOT NULL,
    rain_probability DECIMAL(5,4) NOT NULL DEFAULT 0,
    wind_kmh DECIMAL(6,2) NOT NULL DEFAULT 0,
    cloud_pct TINYINT UNSIGNED NOT NULL DEFAULT 0,
    condition_text VARCHAR(120) NOT NULL,
    raw_json JSON NOT NULL,
    fetched_at DATETIME NOT NULL,
    INDEX idx_weather_fetched_at (fetched_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ml_models (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(120) NOT NULL,
    model_path VARCHAR(255) NOT NULL,
    accuracy_score DECIMAL(6,5) NULL,
    trained_at DATETIME NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ml_predictions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    farmer_id VARCHAR(254) NOT NULL,
    weather_snapshot_json JSON NOT NULL,
    input_features_json JSON NOT NULL,
    predicted_class VARCHAR(60) NOT NULL,
    confidence DECIMAL(6,5) NOT NULL,
    recommendation_text TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_prediction_farmer_created (farmer_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_data (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    temp_c DECIMAL(5,2) NOT NULL,
    humidity_pct TINYINT UNSIGNED NOT NULL,
    rain_probability DECIMAL(5,4) NOT NULL,
    wind_kmh DECIMAL(6,2) NOT NULL,
    cloud_pct TINYINT UNSIGNED NOT NULL,
    condition_encoded VARCHAR(20) NOT NULL,
    farmer_type_encoded VARCHAR(20) NOT NULL,
    growth_stage VARCHAR(20) NOT NULL,
    soil_type_encoded VARCHAR(20) NOT NULL,
    season_encoded VARCHAR(20) NOT NULL,
    label_class VARCHAR(60) NOT NULL,
    source ENUM('synthetic','manual') NOT NULL DEFAULT 'synthetic',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
