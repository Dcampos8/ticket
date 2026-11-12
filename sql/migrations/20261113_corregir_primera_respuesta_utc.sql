-- Corrige primeras respuestas guardadas seis horas por delante del cierre local
-- cuando NOW() de MySQL estaba en UTC y fecha_modificacion ya estaba en hora CDMX.
-- Es idempotente: una fila corregida deja de cumplir fecha_primera_respuesta > fecha_modificacion.
UPDATE tickets
SET fecha_primera_respuesta = DATE_SUB(fecha_primera_respuesta, INTERVAL 6 HOUR)
WHERE fecha_primera_respuesta IS NOT NULL
  AND fecha_modificacion IS NOT NULL
  AND fecha_primera_respuesta > fecha_modificacion;
