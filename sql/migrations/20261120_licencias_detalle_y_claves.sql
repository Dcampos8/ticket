ALTER TABLE licenses
    MODIFY COLUMN license_key TEXT NULL,
    ADD COLUMN license_mode VARCHAR(40) NOT NULL DEFAULT 'Otro' AFTER license_type,
    ADD COLUMN version_edition VARCHAR(120) DEFAULT NULL AFTER license_mode,
    ADD COLUMN quantity INT UNSIGNED NOT NULL DEFAULT 1 AFTER version_edition,
    ADD COLUMN assigned_to VARCHAR(190) DEFAULT NULL AFTER quantity,
    ADD COLUMN purchase_date DATE DEFAULT NULL AFTER assigned_to;
