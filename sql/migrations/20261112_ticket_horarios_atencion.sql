-- Horarios semanales usados por el reloj de SLA de tickets.
-- El registro __base__ se aplica a tickets que todavía no tienen responsable.
CREATE TABLE ticket_horarios_atencion (
    usuario VARCHAR(50) NOT NULL,
    dia_semana TINYINT UNSIGNED NOT NULL COMMENT 'ISO-8601: lunes=1, domingo=7',
    activo TINYINT(1) NOT NULL DEFAULT 0,
    hora_inicio TIME NOT NULL DEFAULT '09:00:00',
    hora_fin TIME NOT NULL DEFAULT '19:00:00',
    actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario, dia_semana),
    CONSTRAINT chk_ticket_horario_dia CHECK (dia_semana BETWEEN 1 AND 7),
    CONSTRAINT chk_ticket_horario_rango CHECK (hora_inicio < hora_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO ticket_horarios_atencion (usuario, dia_semana, activo, hora_inicio, hora_fin) VALUES
    ('__base__', 1, 1, '09:00:00', '19:00:00'),
    ('__base__', 2, 1, '09:00:00', '19:00:00'),
    ('__base__', 3, 1, '09:00:00', '19:00:00'),
    ('__base__', 4, 1, '09:00:00', '19:00:00'),
    ('__base__', 5, 1, '09:00:00', '19:00:00'),
    ('__base__', 6, 0, '09:00:00', '19:00:00'),
    ('__base__', 7, 0, '09:00:00', '19:00:00');
