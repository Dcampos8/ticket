-- Ejecutar una sola vez para habilitar el historial de atención de tickets.
CREATE TABLE ticket_actividad_historial (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    actor_usuario VARCHAR(100) NOT NULL,
    actor_nombre VARCHAR(150) NOT NULL,
    accion VARCHAR(50) NOT NULL,
    detalle VARCHAR(500) NOT NULL DEFAULT '',
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_actividad_ticket_fecha (ticket_id, fecha),
    INDEX idx_ticket_actividad_actor_fecha (actor_usuario, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
