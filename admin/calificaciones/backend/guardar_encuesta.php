<?php
session_start();
require('../../../backend/conexion.php');
require_once __DIR__ . '/../../../shared/integration_settings.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logueado'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$tipo          = $_POST['tipo'] ?? '';
$referencia_id = isset($_POST['referencia_id']) ? intval($_POST['referencia_id']) : 0;
$calificacion  = isset($_POST['calificacion']) ? intval($_POST['calificacion']) : 0;
$resuelto      = $_POST['resuelto'] ?? '';
$comentarios   = trim($_POST['comentarios'] ?? '');

if (!in_array($tipo, ['ticket', 'mantenimiento'], true)
    || $referencia_id <= 0
    || $calificacion < 1 || $calificacion > 5
    || !in_array($resuelto, ['Si', 'No'], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos de la encuesta incompletos o inválidos.']);
    exit;
}

$usuario = $_SESSION['usuario'] ?? null;
$id_usuario = $_SESSION['id'] ?? 0;

// --- Validar que el usuario en sesión tenga derecho a calificar esta referencia ---
if ($tipo === 'ticket') {
    $stmt = mysqli_prepare($conexion, "SELECT id FROM tickets WHERE id = ? AND nombre = ? AND estatus IN ('Finalizado','Cerrado')");
    mysqli_stmt_bind_param($stmt, "is", $referencia_id, $usuario);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
} else {
    $stmt = mysqli_prepare($conexion, "SELECT id FROM notificaciones WHERE id_mantenimiento = ? AND id_usuario = ?");
    mysqli_stmt_bind_param($stmt, "ii", $referencia_id, $id_usuario);
    mysqli_stmt_execute($stmt);
    $ok = mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0;
}

if (!$ok) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permiso para calificar este registro.']);
    exit;
}

// --- Guardar (la clave única tipo+referencia_id evita encuestas duplicadas) ---
$stmt = mysqli_prepare($conexion, "
    INSERT INTO encuestas_satisfaccion (tipo, referencia_id, usuario, id_usuario, calificacion, resuelto, comentarios)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
mysqli_stmt_bind_param(
    $stmt,
    "sisiiss",
    $tipo, $referencia_id, $usuario, $id_usuario, $calificacion, $resuelto, $comentarios
);

if (mysqli_stmt_execute($stmt)) {
    // Si era una encuesta de mantenimiento, marcamos la notificación como leída
    if ($tipo === 'mantenimiento') {
        $stmtU = mysqli_prepare($conexion, "UPDATE notificaciones SET leida = 1 WHERE id_mantenimiento = ? AND id_usuario = ?");
        mysqli_stmt_bind_param($stmtU, "ii", $referencia_id, $id_usuario);
        mysqli_stmt_execute($stmtU);
    }

    // --- Alerta por correo si la calificación es mala ---
    if ($calificacion <= 2 || $resuelto === 'No') {
        require_once(__DIR__ . '../../../backend/PHPMailer/src/Exception.php');
        require_once(__DIR__ . '../../../backend/PHPMailer/src/PHPMailer.php');
        require_once(__DIR__ . '../../../backend/PHPMailer/src/SMTP.php');

        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->CharSet = 'UTF-8';
            $mail->Host = obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_HOST');
            $mail->SMTPAuth = true;
            $mail->Username = obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_USER');
            $mail->Password = obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_PASS');
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            $mail->setFrom('noreply@mensajeriatv.site', 'Transportes Valadez');
            $mail->addAddress('sistemas@transportesvaladez.com');

            $mail->isHTML(true);
            $tipoLabel = $tipo === 'ticket' ? 'Ticket' : 'Mantenimiento';
            $mail->Subject = "⚠️ Calificación baja - $tipoLabel #$referencia_id";
            $mail->Body = "
                <h3 style='background:#B72128;color:#fff;padding:15px'>⚠️ Calificación de satisfacción baja</h3>
                <p><b>Tipo:</b> $tipoLabel</p>
                <p><b>Referencia:</b> #$referencia_id</p>
                <p><b>Usuario:</b> " . htmlspecialchars($usuario ?? ('id ' . $id_usuario)) . "</p>
                <p><b>Calificación:</b> $calificacion / 5</p>
                <p><b>¿Resuelto?:</b> $resuelto</p>
                <p><b>Comentarios:</b> " . nl2br(htmlspecialchars($comentarios ?: 'Sin comentarios')) . "</p>
                <p><a href='" . obtenerConfiguracionIntegracion('APP_BASE_URL', 'https://ticket.transportesvaladez.com/') . "admin/calificaciones/calificaciones.php'>Ver todas las calificaciones</a></p>
            ";
            $mail->send();
        } catch (Exception $e) {
            error_log("Error correo alerta calificación: " . $e->getMessage());
        }
    }

    echo json_encode(['success' => true, 'message' => '¡Gracias por tu evaluación!']);
} else {
    if (mysqli_errno($conexion) === 1062) { // entrada duplicada (ya calificado)
        echo json_encode(['success' => true, 'message' => 'Ya habías calificado este registro.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error al guardar la evaluación.']);
    }
}
