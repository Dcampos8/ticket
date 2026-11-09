<?php
/** Registra quién atendió o modificó un ticket. Solo registra acciones de soporte/admin. */
function registrarActividadTicket($conexion, int $ticketId, string $accion, string $detalle = '', bool $reclamarSiLibre = true): bool
{
    if (empty($_SESSION['logueado']) || !function_exists('esAdminOSuperior') || !esAdminOSuperior()) {
        return false;
    }

    $actorUsuario = trim((string) ($_SESSION['usuario'] ?? ''));
    $actorNombre = trim((string) ($_SESSION['nombre_completo'] ?? '')) ?: $actorUsuario;
    if ($ticketId < 1 || $actorUsuario === '') {
        return false;
    }

    $detalle = mb_substr(trim($detalle), 0, 500, 'UTF-8');
    $stmt = $conexion->prepare('INSERT INTO ticket_actividad_historial (ticket_id, actor_usuario, actor_nombre, accion, detalle) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        error_log('Tickets: no se pudo preparar el registro de actividad.');
        return false;
    }
    $stmt->bind_param('issss', $ticketId, $actorUsuario, $actorNombre, $accion, $detalle);
    $registrado = $stmt->execute();
    $stmt->close();
    if (!$registrado) {
        error_log('Tickets: no se pudo guardar la actividad del ticket.');
        return false;
    }

    // La primera interacción identifica al responsable; nunca desplaza a uno ya asignado.
    if ($reclamarSiLibre) {
        $stmtResponsable = $conexion->prepare("UPDATE tickets SET asignado_a = NULLIF(?, '') WHERE id = ? AND (asignado_a IS NULL OR asignado_a = '')");
        if ($stmtResponsable) {
            $stmtResponsable->bind_param('si', $actorUsuario, $ticketId);
            $stmtResponsable->execute();
            $stmtResponsable->close();
        }
    }

    return true;
}
