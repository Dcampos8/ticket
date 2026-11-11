-- Aplicar una sola vez para registrar alertas diarias de tickets sin duplicarlas.
CREATE TABLE ticket_alertas_sla_enviadas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    tipo_alerta VARCHAR(32) NOT NULL,
    fecha_alerta DATE NOT NULL,
    enviado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ticket_alerta_dia (ticket_id, tipo_alerta, fecha_alerta),
    INDEX idx_ticket_alerta_fecha (fecha_alerta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
