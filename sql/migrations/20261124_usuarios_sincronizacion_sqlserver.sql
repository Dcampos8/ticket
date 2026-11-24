-- Estado de origen SQL Server; las cuentas existentes permanecen activas.
ALTER TABLE usuarios
    ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER contrasena,
    MODIFY COLUMN email VARCHAR(125) NULL;
