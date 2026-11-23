ALTER TABLE licenses
    ADD COLUMN renewal_cycle VARCHAR(40) NOT NULL DEFAULT 'No aplica' AFTER expiration_date,
    ADD COLUMN renewal_months SMALLINT UNSIGNED DEFAULT NULL AFTER renewal_cycle,
    ADD COLUMN auto_renewal TINYINT(1) NOT NULL DEFAULT 0 AFTER renewal_months;
