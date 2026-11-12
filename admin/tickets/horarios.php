<?php
require_once __DIR__ . '/../../config.php';
date_default_timezone_set('America/Mexico_City');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
require_once ROOT_PATH . 'shared/ticket_horarios.php';
requerirModulo('tickets');
if (!esAdminOSuperior()) { http_response_code(403); exit('No autorizado.'); }
require_once ROOT_PATH . 'backend/conexion.php';
$csrf = asegurarTokenCsrf();
$tablaHorariosDisponible = false;
try { $resultadoTablaHorarios = $conexion->query("SHOW TABLES LIKE 'ticket_horarios_atencion'"); $tablaHorariosDisponible = $resultadoTablaHorarios && $resultadoTablaHorarios->num_rows > 0; } catch (Throwable $e) { $tablaHorariosDisponible = false; }
$diasSemana = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$errores = [];
$guardado = ($_GET['guardado'] ?? '') === '1';
$agentes = [];
$resultadoAgentes = $conexion->query("SELECT usuario, nombre_completo, rol FROM usuarios WHERE rol IN ('admin','superadmin') ORDER BY CASE WHEN rol = 'superadmin' THEN 0 ELSE 1 END, nombre_completo, usuario");
if ($resultadoAgentes) while ($agente = $resultadoAgentes->fetch_assoc()) $agentes[(string) $agente['usuario']] = $agente;

$seleccionado = trim((string) ($_POST['usuario_horario'] ?? $_GET['usuario'] ?? TICKET_HORARIO_BASE));
$seleccionadoValido = $seleccionado === TICKET_HORARIO_BASE || isset($agentes[$seleccionado]);
if (!$seleccionadoValido) $seleccionado = TICKET_HORARIO_BASE;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validarTokenCsrf()) { http_response_code(403); exit('La sesión expiró. Recarga la página.'); }
    $captura = $_POST['dias'] ?? [];
    $horarioGuardado = [];
    foreach ($diasSemana as $numeroDia => $nombreDia) {
        $dia = is_array($captura[$numeroDia] ?? null) ? $captura[$numeroDia] : [];
        $activo = isset($dia['activo']);
        $apertura = trim((string) ($dia['inicio'] ?? '09:00'));
        $cierre = trim((string) ($dia['fin'] ?? '19:00'));
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $apertura) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $cierre) || $apertura >= $cierre) {
            $errores[] = "Revisa el horario de {$nombreDia}; la hora final debe ser posterior a la inicial.";
            continue;
        }
        $horarioGuardado[$numeroDia] = [$activo ? 1 : 0, $apertura . ':00', $cierre . ':00'];
    }
    if (!$errores && count($horarioGuardado) === 7) {
        try {
            $conexion->begin_transaction();
            $stmt = $conexion->prepare('INSERT INTO ticket_horarios_atencion (usuario, dia_semana, activo, hora_inicio, hora_fin) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE activo = VALUES(activo), hora_inicio = VALUES(hora_inicio), hora_fin = VALUES(hora_fin)');
            foreach ($horarioGuardado as $numeroDia => [$activo, $apertura, $cierre]) {
                $stmt->bind_param('siiss', $seleccionado, $numeroDia, $activo, $apertura, $cierre);
                if (!$stmt->execute()) throw new RuntimeException('No se pudo guardar el horario.');
            }
            $stmt->close();
            $conexion->commit();
            header('Location: ' . BASE_URL . 'admin/tickets/horarios.php?usuario=' . rawurlencode($seleccionado) . '&guardado=1');
            exit;
        } catch (Throwable $e) {
            $conexion->rollback();
            error_log('No se pudo guardar el horario de tickets: ' . $e->getMessage());
            $errores[] = 'No se guardó el horario. Aplica la migración de horarios de atención y vuelve a intentarlo.';
        }
    }
}

$horarios = cargarHorariosTicket($conexion);
$horario = $seleccionado === TICKET_HORARIO_BASE
    ? $horarios[TICKET_HORARIO_BASE]
    : array_replace($horarios[TICKET_HORARIO_BASE], $horarios[$seleccionado] ?? []);
include ROOT_PATH . 'admin/menu.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>css/pages/admin__tickets__horarios.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__tickets__horarios.css') ?>">
<main class="ticket-hours-page">
  <header class="ticket-hours-heading">
    <div><span class="hours-eyebrow">SERVICE DESK · CONFIGURACIÓN</span><h1><i class="fa-solid fa-calendar-days"></i> Horarios de atención</h1><p>El reloj de respuesta y resolución solo avanza durante el horario activo.</p></div>
    <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>admin/tickets/"><i class="fa-solid fa-arrow-left me-2"></i>Volver a tickets</a>
  </header>
  <?php if ($guardado): ?><div class="alert alert-success"><i class="fa-solid fa-circle-check me-2"></i>Horario guardado. Los vencimientos activos se calcularán con este horario.</div><?php endif; ?>
  <?php if (!$tablaHorariosDisponible): ?><div class="alert alert-warning">Aplica primero la migración <code>20261112_ticket_horarios_atencion.sql</code> en la base de datos para guardar horarios.</div><?php endif; ?>
  <?php foreach ($errores as $error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endforeach; ?>
  <section class="hours-note"><i class="fa-solid fa-circle-info"></i><div><strong>Horario semanal, zona Ciudad de México</strong><span>Los tickets asignados usan el horario de su responsable. Los tickets sin asignar heredan el horario base de Sistemas. Los días y horas inactivos no cuentan para el SLA.</span></div></section>
  <form method="get" class="hours-select-form">
    <label for="usuario_horario">Configurar horario de</label>
    <select id="usuario_horario" name="usuario" class="form-select" onchange="this.form.submit()">
      <option value="<?= htmlspecialchars(TICKET_HORARIO_BASE, ENT_QUOTES, 'UTF-8') ?>" <?= $seleccionado === TICKET_HORARIO_BASE ? 'selected' : '' ?>>Horario base de Sistemas · Tickets sin asignar</option>
      <?php foreach ($agentes as $usuario => $agente): ?>
        <option value="<?= htmlspecialchars($usuario, ENT_QUOTES, 'UTF-8') ?>" <?= $seleccionado === $usuario ? 'selected' : '' ?>><?= htmlspecialchars(($agente['nombre_completo'] ?: $usuario) . ' · ' . strtoupper($agente['rol']), ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <form method="post" class="hours-card">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="usuario_horario" value="<?= htmlspecialchars($seleccionado, ENT_QUOTES, 'UTF-8') ?>">
    <div class="hours-table-wrap"><table class="hours-table"><thead><tr><th>Día</th><th>Atiende</th><th>Desde</th><th>Hasta</th></tr></thead><tbody>
      <?php foreach ($diasSemana as $numeroDia => $nombreDia): $turno = $horario[$numeroDia] ?? ['activo' => false, 'hora_inicio' => '09:00:00', 'hora_fin' => '19:00:00']; $inicioVisible = $_POST['dias'][$numeroDia]['inicio'] ?? substr((string) ($turno['hora_inicio'] ?? '09:00:00'), 0, 5); $finVisible = $_POST['dias'][$numeroDia]['fin'] ?? substr((string) ($turno['hora_fin'] ?? '19:00:00'), 0, 5); ?>
        <tr>
          <th scope="row"><?= htmlspecialchars($nombreDia, ENT_QUOTES, 'UTF-8') ?></th>
          <td><label class="hours-toggle"><input type="checkbox" name="dias[<?= $numeroDia ?>][activo]" value="1" <?= !empty($_POST) ? (isset($_POST['dias'][$numeroDia]['activo']) ? 'checked' : '') : (!empty($turno['activo']) ? 'checked' : '') ?>><span>Activo</span></label></td>
          <td><input aria-label="Hora de inicio, <?= htmlspecialchars($nombreDia, ENT_QUOTES, 'UTF-8') ?>" class="form-control" type="time" name="dias[<?= $numeroDia ?>][inicio]" value="<?= htmlspecialchars((string) $inicioVisible, ENT_QUOTES, 'UTF-8') ?>" required></td>
          <td><input aria-label="Hora de fin, <?= htmlspecialchars($nombreDia, ENT_QUOTES, 'UTF-8') ?>" class="form-control" type="time" name="dias[<?= $numeroDia ?>][fin]" value="<?= htmlspecialchars((string) $finVisible, ENT_QUOTES, 'UTF-8') ?>" required></td>
        </tr>
      <?php endforeach; ?>
    </tbody></table></div>
    <footer><span>Ejemplo: de lunes a viernes, de 9:00 a 19:00.</span><button type="submit" class="btn btn-danger"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar horario</button></footer>
  </form>
</main>
