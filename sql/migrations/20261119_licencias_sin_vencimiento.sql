ALTER TABLE licenses
    MODIFY COLUMN expiration_date DATE NULL,
    ADD COLUMN license_type VARCHAR(80) NOT NULL DEFAULT 'Servicio' AFTER service_name;
