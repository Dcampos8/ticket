<?php
require_once __DIR__ . '/../../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
requerirModulo('tickets');
if (!esAdminOSuperior()) { http_response_code(403); exit('No autorizado.'); }
require_once ROOT_PATH . 'backend/conexion.php';
$csrf = asegurarTokenCsrf();
$prioridades = ['Baja', 'Normal', 'Alta', 'Urgente'];
$errores = [];
$guardado = ($_GET['guardado'] ?? '') === '1';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validarTokenCsrf()) { http_response_code(403); exit('La sesión expiró. Recarga la página.'); }
    $valores = [];
    foreach ($prioridades as $prioridad) {
        $respuesta = filter_var($_POST['respuesta'][$prioridad] ?? null, FILTER_VALIDATE_INT);
        $resolucion = filter_var($_POST['resolucion'][$prioridad] ?? null, FILTER_VALIDATE_INT);
        if ($respuesta === false || $resolucion === false || $respuesta < 1 || $respuesta > 525600 || $resolucion < 1 || $resolucion > 525600) {
            $errores[] = "Los tiempos para prioridad {$prioridad} deben estar entre 1 y 525600 minutos.";
            continue;
        }
        $valores[$prioridad] = [$respuesta, $resolucion];
    }
    if (!$errores && count($valores) === count($prioridades)) {
        $conexion->begin_transaction();
        try {
            $stmt = $conexion->prepare('UPDATE ticket_sla_politicas SET respuesta_minutos = ?, resolucion_minutos = ? WHERE prioridad = ?');
            foreach ($valores as $prioridad => [$respuesta, $resolucion]) {
                $stmt->bind_param('iis', $respuesta, $resolucion, $prioridad);
                if (!$stmt->execute() || $stmt->affected_rows < 0) throw new RuntimeException('No se pudieron guardar las políticas.');
            }
            $stmt->close();
            $conexion->commit();
            header('Location: ' . BASE_URL . 'admin/tickets/sla.php?guardado=1');
            exit;
        } catch (Throwable $e) {
            $conexion->rollback();
            error_log('No se pudieron guardar las políticas SLA: ' . $e->getMessage());
            $errores[] = 'No se guardaron los cambios. Verifica que la migración de operación de tickets esté aplicada.';
        }
    }
}

$politicas = [];
$resultado = false;
try { $resultado = $conexion->query('SELECT prioridad, respuesta_minutos, resolucion_minutos FROM ticket_sla_politicas'); } catch (Throwable $e) { error_log('No se pudieron cargar las políticas SLA: ' . $e->getMessage()); }
if ($resultado) while ($fila = $resultado->fetch_assoc()) $politicas[$fila['prioridad']] = $fila;
include ROOT_PATH . 'admin/menu.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>css/pages/admin__tickets__sla.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__tickets__sla.css') ?>">
<main class="sla-page">
  <header class="sla-heading"><div><span class="sla-eyebrow">SERVICE DESK · CONFIGURACIÓN</span><h1><i class="fa-solid fa-stopwatch"></i> Políticas SLA</h1><p>Define cuánto tiempo tiene soporte para responder y resolver cada prioridad.</p></div><a class="btn btn-outline-secondary" href="<?= BASE_URL ?>admin/tickets/"><i class="fa-solid fa-arrow-left me-2"></i>Volver a tickets</a></header>
  <?php if ($guardado): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>Políticas guardadas. Se aplican a los tickets nuevos y al cambiar prioridad en un ticket abierto.</div><?php endif; ?>
  <?php foreach ($errores as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
  <?php if (count($politicas) !== count($prioridades)): ?><div class="alert alert-warning">No se encontró la tabla de políticas SLA. Aplica la migración <code>20261007_ticket_operacion_y_base_conocimiento.sql</code>.</div><?php endif; ?>
  <section class="sla-note"><i class="fa-solid fa-circle-info"></i><div><strong>Los objetivos se cuentan en minutos laborables</strong><span>El reloj solo avanza dentro del horario semanal del responsable asignado. Los tickets sin asignar usan el horario base de Sistemas. Si cambia el horario, los vencimientos abiertos se recalculan; los objetivos en minutos se conservan.</span></div></section>
  <form method="post" class="sla-card">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Prioridad</th><th>Primera respuesta (minutos)</th><th>Resolución (minutos)</th></tr></thead><tbody>
    <?php foreach ($prioridades as $prioridad): $actual = $politicas[$prioridad] ?? []; $respuesta = $_POST['respuesta'][$prioridad] ?? $actual['respuesta_minutos'] ?? ''; $resolucion = $_POST['resolucion'][$prioridad] ?? $actual['resolucion_minutos'] ?? ''; ?>
      <tr><th><span class="sla-priority sla-<?= strtolower($prioridad) ?>"><?= htmlspecialchars($prioridad) ?></span></th><td><label class="visually-hidden" for="respuesta-<?= strtolower($prioridad) ?>">Minutos para primera respuesta, prioridad <?= htmlspecialchars($prioridad) ?></label><input id="respuesta-<?= strtolower($prioridad) ?>" class="form-control" type="number" min="1" max="525600" required name="respuesta[<?= htmlspecialchars($prioridad) ?>]" value="<?= htmlspecialchars((string) $respuesta, ENT_QUOTES, 'UTF-8') ?>"></td><td><label class="visually-hidden" for="resolucion-<?= strtolower($prioridad) ?>">Minutos para resolución, prioridad <?= htmlspecialchars($prioridad) ?></label><input id="resolucion-<?= strtolower($prioridad) ?>" class="form-control" type="number" min="1" max="525600" required name="resolucion[<?= htmlspecialchars($prioridad) ?>]" value="<?= htmlspecialchars((string) $resolucion, ENT_QUOTES, 'UTF-8') ?>"></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <footer><small>60 minutos laborables equivalen a 1 hora de atención.</small><button class="btn btn-danger" type="submit" <?= count($politicas) !== count($prioridades) ? 'disabled' : '' ?>><i class="fa-solid fa-floppy-disk me-2"></i>Guardar políticas</button></footer>
  </form>
</main>
