<?php
// 1. Cargar la configuración
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once(ROOT_PATH . 'backend/conexion.php');

// 2. Control de sesión (opcional si quieres que sea público solo para admin)
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['logueado'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// 3. Consulta exclusiva: Solo nombres y correos
$sql = "SELECT nombre_completo, email,phone FROM usuarios WHERE email IS NOT NULL AND email != '' ORDER BY nombre_completo ASC";
$resultado = $conexion->query($sql);
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/usuario__usuarios__index.css?v=<?= filemtime(__DIR__ . '/../../css/pages/usuario__usuarios__index.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<link rel="icon" href="https://https://ticket.transportesvaladez.com/img/icono.png">

<div class="container-fluid py-4">
    <h2 class="mb-4 text-center fw-bold text-primary">
        <i class="fa-solid fa-address-book me-2"></i>Directorio de Contactos
    </h2>
    
    <div class="directorio-card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4">Nombre Completo</th>
                        <th>Correo Electrónico</th>
                        <th>Teléfono</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($resultado && $resultado->num_rows > 0): ?>
                        <?php while ($u = $resultado->fetch_assoc()): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="icon-badge"><i class="fa-solid fa-user text-primary"></i></div>
                                        <?= htmlspecialchars($u['nombre_completo']) ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($u['email']) ?>" class="email-link">
                                        <i class="fa-solid fa-envelope me-2"></i><?= htmlspecialchars($u['email']) ?>
                                    </a>
                                </td>
                                <td>
                                    <a href="tel:<?= htmlspecialchars($u['phone']) ?>" class="text-secondary text-decoration-none">
                                        <i class="fa-solid fa-phone me-2"></i><?= htmlspecialchars($u['phone']) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">No hay contactos registrados.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php $conexion->close(); ?>