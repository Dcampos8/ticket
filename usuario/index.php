<?php
// 1. Sesión y Seguridad
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'usuario') {
    header("Location: ../login.php");
    exit();
}

// 2. Conexión (config.php define BASE_URL/ROOT_PATH y ya incluye conexion.php)
require_once '../config.php';

// 3. Lógica de negocio (Avisos)
$aviso = null;
$query_aviso = "SELECT * FROM avisos_sistema WHERE activo = 1 ORDER BY id DESC LIMIT 1";
$resultado_aviso = $conexion->query($query_aviso);
if ($resultado_aviso && $resultado_aviso->num_rows > 0) {
    $aviso = $resultado_aviso->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Tema global del rediseño (retinta Bootstrap a los colores de marca) -->
    <link rel="stylesheet" href="../css/theme.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../css/redesign.css?v=<?= time() ?>">
    <link rel="icon" href="https://ticket.transportesvaladez.com/img/icono.png">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="d-flex">
        <?php include('menu.php'); ?>
        
       <main class="flex-grow-1 p-4" id="pageContent" style="min-height: 100vh;">
    <div class="mb-4">
        <h2>Bienvenido, <?= htmlspecialchars($_SESSION['nombre_completo']) ?></h2>
        <p class="text-muted">Selecciona una opción del menú lateral para comenzar.</p>
    </div>
    
    <!-- Contenedor Grid de Bootstrap -->
    <div class="row g-4">
        
        <!-- Columna 1: Políticas de la Empresa -->
        <div class="col-12 col-md-6">
            <div class="card h-100 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fa-solid fa-gavel me-2"></i>Política General de Sistemas</h5>
                </div>
                <div class="card-body">
                    <ul><h4>Lineamientos Generales</h4>
                        <li><h5>Uso de Equipos y Sistemas</h5>
                            <ul>
                                <li>Los equipos, cuentas y sistemas proporcionados por la empresa son para fines laborales.</li>
                                <li>Está prohibido instalar software sin autorización del área de Sistemas.</li>
                            </ul>
                        </li>
                        <li><h5>Seguridad de la Información</h5>
                            <ul>
                                <li>Cada usuario es responsable de la confidencialidad de sus contraseñas.</li>
                                <li>No se deben compartir credenciales de acceso con terceros.</li>
                                <li>Toda información de la empresa deberá manejarse de forma segura y únicamente para actividades autorizadas.</li>
                            </ul>
                        </li>
                        <li><h5>Soporte Técnico</h5>
                            <ul>
                                <li>Cualquier falla, incidente o requerimiento tecnológico deberá reportarse mediante los canales oficiales de soporte o sistema de tickets.</li>
                                <li>Los usuarios no deberán realizar modificaciones técnicas en equipos o configuraciones sin autorización.</li>
                                <!--<li></li>
                                <li></li>
                                <li></li>-->
                            </ul>
                        </li>
                        <li><h5>Uso de Internet y Correo Electrónico</h5>
                            <ul>
                                <li>El acceso a internet y correo corporativo deberá utilizarse de manera responsable y profesional.</li>
                                <li>Está prohibido acceder, descargar o distribuir contenido que represente un riesgo para la seguridad de la empresa.</li>
                                <!--<li></li>
                                <li></li>
                                <li></li>-->
                            </ul>
                        </li>
                        <li><h5>Resguardo de Equipos</h5>
                            <ul>
                                <li>Los usuarios son responsables del cuidado de los equipos asignados.</li>
                                <li>Cualquier daño, pérdida o robo deberá reportarse inmediatamente al área de Sistemas y al responsable correspondiente.</li>
                                <!--<li></li>
                                <li></li>
                                <li></li>-->
                            </ul>
                        </li>
                        <li><h5>Incumplimiento</h5>
                            <ul>
                                <li>El incumplimiento de esta política podrá derivar en la suspensión de accesos, restricciones de uso de recursos tecnológicos o las medidas administrativas que la empresa considere pertinentes.</li>
                                <!--<li></li>
                                <li></li>
                                <li></li>
                                <li></li>-->
                            </ul>
                        </li>
                        <li><h5>Vigencia</h5>
                            <ul>
                                <li>La presente política entra en vigor a partir de su publicación y podrá ser actualizada cuando las necesidades operativas o de seguridad de la empresa lo requieran.</li>
                                <!--<li></li>
                                <li></li>
                                <li></li>
                                <li></li>-->
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Columna 2: Avisos del Sistema -->
        <div class="col-12 col-md-6">
            <?php if ($aviso): ?>
                <div class="card h-100 border-<?= htmlspecialchars($aviso['color_alerta']) ?> shadow-sm">
                    <div class="card-header bg-<?= htmlspecialchars($aviso['color_alerta']) ?> text-white">
                        <h5 class="card-title mb-0"><i class="fa-solid fa-circle-exclamation me-2"></i><?= htmlspecialchars($aviso['titulo']) ?></h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text" style="white-space: pre-line;"><?= htmlspecialchars($aviso['mensaje']) ?></p>
                    </div>
                </div>
            <?php else: ?>
                <!-- Estado cuando no hay avisos activos -->
                <div class="card h-100 border-light bg-light shadow-sm d-flex align-items-center justify-content-center text-center p-4">
                    <div class="text-muted">
                        <i class="fa-regular fa-bell-slash fa-2x mb-2"></i>
                        <p class="mb-0">No hay avisos pendientes en este momento.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include('encuesta_modal.php'); ?>
    <?php require_once ROOT_PATH . 'shared/chat_widget.php'; ?>
    <script>
        window.RUTA_BACKEND_ENCUESTAS = '../backend/';
        iniciarEncuestas('../');
    </script>
</body>
</html>

<?php
// Cerramos conexión al final
$conexion->close();
?>
