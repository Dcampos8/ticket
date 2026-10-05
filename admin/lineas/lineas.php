<?php
// Esta vista quedó reemplazada por la Gestión de Líneas y Equipos actual
// (admin/lineas/index.php), que ya incluye todo lo que hacía esta página
// (listado, reasignar, editar) más historial completo, stock de equipos
// y papelera con restauración. Además, esta página dependía de dos
// archivos que ya no existen (backend/asignar_manual.php y
// get_employees.php) y de tablas de una versión anterior del sistema
// (phone_lines / phone_deliveries), así que había quedado rota.
// Se deja como redirección para no romper accesos o marcadores antiguos.
include_once '../../config.php';
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('lineas');

header('Location: ' . BASE_URL . 'admin/lineas/');
exit;
