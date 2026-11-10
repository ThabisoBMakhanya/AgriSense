-- MySQL/MariaDB equivalent of upgrade_farm_journal.sql for local XAMPP use.
CREATE TABLE IF NOT EXISTS farm_tasks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    farmer_email VARCHAR(254) NOT NULL,
    client_id CHAR(36) NOT NULL,
    title VARCHAR(120) NOT NULL,
    record_scope VARCHAR(20) NOT NULL DEFAULT 'crops',
    category VARCHAR(60) NOT NULL,
    due_date DATE NOT NULL,
    repeat_days INT NOT NULL DEFAULT 0,
    note TEXT NOT NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_farm_tasks_owner_client (farmer_email, client_id),
    KEY idx_farm_tasks_farmer_due (farmer_email, due_date),
    CONSTRAINT fk_farm_tasks_owner FOREIGN KEY (farmer_email) REFERENCES users(email) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS farm_records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    farmer_email VARCHAR(254) NOT NULL,
    client_id CHAR(36) NOT NULL,
    record_type VARCHAR(20) NOT NULL,
    record_scope VARCHAR(20) NOT NULL DEFAULT 'crops',
    item VARCHAR(150) NOT NULL,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL DEFAULT '',
    entry_date DATE NOT NULL,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_farm_records_owner_client (farmer_email, client_id),
    KEY idx_farm_records_farmer_date (farmer_email, entry_date),
    CONSTRAINT fk_farm_records_owner FOREIGN KEY (farmer_email) REFERENCES users(email) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS field_cases (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    farmer_email VARCHAR(254) NOT NULL,
    client_id CHAR(36) NOT NULL,
    subject VARCHAR(150) NOT NULL,
    subject_type VARCHAR(20) NOT NULL DEFAULT 'crops',
    severity VARCHAR(20) NOT NULL,
    symptoms TEXT NOT NULL,
    photo_data MEDIUMBLOB NULL,
    photo_mime VARCHAR(80) NULL,
    photo_name VARCHAR(200) NULL,
    entry_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_field_cases_owner_client (farmer_email, client_id),
    KEY idx_field_cases_farmer_date (farmer_email, entry_date),
    CONSTRAINT fk_field_cases_owner FOREIGN KEY (farmer_email) REFERENCES users(email) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS animal_records (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    farmer_email VARCHAR(254) NOT NULL,
    client_id CHAR(36) NOT NULL,
    animal_group VARCHAR(150) NOT NULL,
    activity VARCHAR(60) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL DEFAULT '',
    entry_date DATE NOT NULL,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_animal_records_owner_client (farmer_email, client_id),
    KEY idx_animal_records_farmer_date (farmer_email, entry_date),
    CONSTRAINT fk_animal_records_owner FOREIGN KEY (farmer_email) REFERENCES users(email) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS farm_inventory (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    farmer_email VARCHAR(254) NOT NULL,
    client_id CHAR(36) NOT NULL,
    input_name VARCHAR(150) NOT NULL,
    input_type VARCHAR(30) NOT NULL,
    quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    unit VARCHAR(30) NOT NULL,
    minimum_quantity DECIMAL(14,3) NOT NULL DEFAULT 0,
    expiry_date DATE NULL,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_farm_inventory_owner_client (farmer_email, client_id),
    KEY idx_farm_inventory_farmer_name (farmer_email, input_name),
    CONSTRAINT fk_farm_inventory_owner FOREIGN KEY (farmer_email) REFERENCES users(email) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS farm_stock_movements (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    farmer_email VARCHAR(254) NOT NULL,
    client_id CHAR(36) NOT NULL,
    stock_client_id CHAR(36) NOT NULL,
    linked_entry_client_id CHAR(36) NOT NULL,
    quantity_delta DECIMAL(14,3) NOT NULL,
    entry_date DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_farm_stock_owner_client (farmer_email,client_id),
    KEY idx_farm_stock_item (farmer_email,stock_client_id,entry_date),
    CONSTRAINT fk_farm_stock_owner FOREIGN KEY (farmer_email) REFERENCES users(email) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS farm_journal_preferences (
    farmer_email VARCHAR(254) PRIMARY KEY,
    settings JSON NOT NULL,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    CONSTRAINT fk_farm_journal_preferences_owner FOREIGN KEY (farmer_email) REFERENCES users(email) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS field_harvests (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    farmer_email VARCHAR(254) NOT NULL,
    client_id CHAR(36) NOT NULL,
    field_name VARCHAR(120) NOT NULL,
    crop VARCHAR(120) NOT NULL,
    harvest_date DATE NOT NULL,
    quantity DECIMAL(14,3) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    grade VARCHAR(60) NOT NULL,
    sale_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    note TEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    UNIQUE KEY uq_field_harvests_owner_client (farmer_email, client_id),
    KEY idx_field_harvests_farmer_date (farmer_email, harvest_date),
    KEY idx_field_harvests_farmer_plot (farmer_email, field_name, crop),
    CONSTRAINT fk_field_harvests_owner FOREIGN KEY (farmer_email) REFERENCES users(email) ON DELETE CASCADE
);

ALTER TABLE farm_tasks MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
ALTER TABLE farm_records MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
ALTER TABLE field_cases MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
ALTER TABLE animal_records MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
ALTER TABLE farm_inventory MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
ALTER TABLE field_harvests MODIFY updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6);
