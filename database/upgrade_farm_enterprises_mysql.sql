CREATE TABLE IF NOT EXISTS farm_enterprises (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    farmer_email VARCHAR(254) NOT NULL,
    enterprise_type VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL DEFAULT '',
    growth_stage VARCHAR(20) NOT NULL DEFAULT '',
    notes TEXT NOT NULL,
    weather_location VARCHAR(100) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_farm_enterprises_farmer_type (farmer_email, enterprise_type, name),
    CONSTRAINT fk_farm_enterprises_owner FOREIGN KEY (farmer_email) REFERENCES users(email) ON DELETE CASCADE
);
ALTER TABLE farm_enterprises ADD COLUMN IF NOT EXISTS weather_location VARCHAR(100) NOT NULL DEFAULT '';
UPDATE farm_enterprises e JOIN users u ON u.email = e.farmer_email SET e.weather_location = COALESCE(u.weather_location, '') WHERE e.weather_location = '';
INSERT INTO farm_enterprises (farmer_email, enterprise_type, name, growth_stage, weather_location)
SELECT users.email, 'crop', users.crop_type, COALESCE(users.growth_stage, ''), COALESCE(users.weather_location, '')
FROM users
WHERE COALESCE(TRIM(users.crop_type), '') <> ''
  AND NOT EXISTS (
      SELECT 1 FROM farm_enterprises
      WHERE farm_enterprises.farmer_email = users.email
        AND farm_enterprises.enterprise_type = 'crop'
        AND farm_enterprises.name = users.crop_type
  );
