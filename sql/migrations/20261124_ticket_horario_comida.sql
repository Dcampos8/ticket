-- Intervalo opcional de comida que se descuenta del reloj laboral de los SLA.
ALTER TABLE ticket_horarios_atencion
    ADD COLUMN comida_inicio TIME NULL DEFAULT NULL AFTER hora_fin,
    ADD COLUMN comida_fin TIME NULL DEFAULT NULL AFTER comida_inicio;
