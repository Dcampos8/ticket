<?php
// Endpoint obsoleto: escribía en `phone_lines`, una tabla de una versión
// anterior del sistema que ya no usa ninguna pantalla activa (la alta de
// líneas hoy se hace desde admin/lineas/index.php, modal "Nueva Línea",
// sobre la tabla real `lineas_telefonicas`). Además no validaba sesión,
// así que se deja inerte (ya no toca la base de datos) para no romper si
// algo viejo todavía le llama, sin dejar un hueco de seguridad abierto.
header('Content-Type: application/json');
echo json_encode([
    'success' => false,
    'error'   => 'Endpoint obsoleto. Usa la Gestión de Líneas en admin/lineas/index.php.'
]);
exit;
