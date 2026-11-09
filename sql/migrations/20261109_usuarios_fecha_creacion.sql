-- Fecha de alta para cuentas nuevas. Los registros anteriores quedan NULL
-- porque la fecha histórica no existe en la tabla actual.
ALTER TABLE usuarios
    ADD COLUMN fecha_creacion DATETIME NULL DEFAULT NULL AFTER id;
