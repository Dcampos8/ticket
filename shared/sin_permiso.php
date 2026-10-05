<?php
/**
 * shared/sin_permiso.php
 * Pantalla mostrada cuando un admin está logueado correctamente pero
 * no tiene asignado el módulo al que intenta entrar. Se incluye desde
 * requerirModulo() en shared/permisos.php (ya viene con header/menu
 * cargados si el caller los cargó antes; si no, se ve igual de bien
 * como página suelta).
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso restringido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body style="background:#f1f5f9;">
    <div class="d-flex flex-column align-items-center justify-content-center text-center" style="min-height:60vh; padding-top:100px;">
        <i class="fa-solid fa-lock text-danger mb-3" style="font-size:3rem;"></i>
        <h2 class="fw-bold text-danger">Acceso restringido</h2>
        <p class="text-muted mb-4">Tu cuenta no tiene asignado este módulo.<br>Pídele al superadministrador que te lo habilite desde <em>Usuarios &gt; Permisos de Módulos</em>.</p>
        <a href="<?= defined('BASE_URL') ? BASE_URL . 'admin/' : '../' ?>" class="btn btn-primary">
            <i class="fa-solid fa-arrow-left me-1"></i> Volver al Dashboard
        </a>
    </div>
</body>
</html>
