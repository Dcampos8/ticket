-- Ejecutar una sola vez sobre la base existente antes de desplegar el código.
-- No elimina ni reescribe tickets existentes.
ALTER TABLE tickets
    ADD COLUMN prioridad ENUM('Baja','Normal','Alta','Urgente') NOT NULL DEFAULT 'Normal' AFTER tipo_ticket,
    ADD COLUMN asignado_a VARCHAR(50) DEFAULT NULL AFTER prioridad,
    ADD COLUMN fecha_primera_respuesta DATETIME DEFAULT NULL AFTER fecha_modificacion,
    ADD COLUMN fecha_resolucion DATETIME DEFAULT NULL AFTER fecha_primera_respuesta,
    ADD COLUMN objetivo_respuesta_minutos INT UNSIGNED NOT NULL DEFAULT 480 AFTER fecha_resolucion,
    ADD COLUMN objetivo_resolucion_minutos INT UNSIGNED NOT NULL DEFAULT 2880 AFTER objetivo_respuesta_minutos,
    ADD INDEX idx_tickets_asignado_estatus (asignado_a, estatus),
    ADD INDEX idx_tickets_fecha_resolucion (fecha_resolucion);

-- Objetivos iniciales en tiempo corrido. Ajustables desde esta tabla según el horario de soporte.
CREATE TABLE ticket_sla_politicas (
    prioridad ENUM('Baja','Normal','Alta','Urgente') NOT NULL PRIMARY KEY,
    respuesta_minutos INT UNSIGNED NOT NULL,
    resolucion_minutos INT UNSIGNED NOT NULL,
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ticket_sla_politicas (prioridad, respuesta_minutos, resolucion_minutos) VALUES
    ('Baja', 1440, 5760),
    ('Normal', 480, 2880),
    ('Alta', 240, 1440),
    ('Urgente', 60, 480);

CREATE TABLE base_conocimiento (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(180) NOT NULL,
    resumen VARCHAR(400) DEFAULT NULL,
    contenido MEDIUMTEXT NOT NULL,
    categoria VARCHAR(80) DEFAULT NULL,
    palabras_clave VARCHAR(500) DEFAULT NULL,
    publicado TINYINT(1) NOT NULL DEFAULT 0,
    creado_por VARCHAR(50) NOT NULL,
    actualizado_por VARCHAR(50) NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_base_conocimiento_publicado_categoria (publicado, categoria),
    FULLTEXT INDEX ft_base_conocimiento_busqueda (titulo, resumen, contenido, palabras_clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
