<?php
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificar seguridad y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('usuarios');
requerirAdmin();
require_once(ROOT_PATH . 'shared/security.php');
$csrfToken = asegurarTokenCsrf();

// 4. Cargar el menú usando la ruta absoluta
include(ROOT_PATH . 'admin/menu.php');
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__usuarios__subir_usuarios.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__usuarios__subir_usuarios.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="content">
  <h1><i class="fa-solid fa-file-csv me-2"></i>Subir Usuarios masivamente</h1>

  <div class="card shadow-sm p-4" style="max-width: 700px;">
    <!-- El action ahora usa BASE_URL para apuntar correctamente al backend -->
    <form action="<?= BASE_URL ?>admin/usuarios/backend/procesar_csv.php" method="POST" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

      <div class="mb-3">
        <label for="archivoUsuarios" class="form-label">Seleccionar archivo CSV</label>
        <input type="file"
               id="archivoUsuarios"
               name="archivoUsuarios"
               accept=".csv"
               required
               class="form-control" />
      </div>

      <div class="alert alert-info py-2">
        <i class="fa-solid fa-circle-info me-2"></i>
        <div><i class="fa-solid fa-circle-info me-2"></i>El CSV debe incluir estas columnas en este orden:</div>
        <div class="my-2"><code>usuario,nombre_completo,<strong>contrasena</strong>,email,phone,area,rol</code></div>
        <small>La columna <strong>contrasena</strong> es obligatoria. <strong>email y phone son opcionales</strong> y pueden quedar vacíos. La contraseña se guarda como hash y no distingue mayúsculas. Si el usuario ya existe, se actualizan sus datos y contraseña desde el archivo, se conserva su rol y se muestra en el resumen.</small>
        <div class="mt-3"><a href="#" onclick="descargarPlantilla(); return false;" class="btn btn-outline-info">
          <i class="fa-solid fa-download me-2"></i>Descargar Plantilla CSV
        </a></div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
          <i class="fa-solid fa-upload me-2"></i>Procesar Archivo
        </button>
        <a href="index.php" class="btn btn-outline-secondary">
          Cancelar
        </a>
      </div>
    </form>
  </div>
</div>
<script>
function descargarPlantilla() {
    const contenido = "usuario,nombre_completo,contrasena,email,phone,area,rol\njper,Juan Perez,Pass1234,correo@correo.com,4491234567,Ventas,admin\nmlopez,Maria Lopez,Segura2026,correo@correo.com,4491234567,Almacen,usuario";
    const blob = new Blob([contenido], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.setAttribute('href', url);
    a.setAttribute('download', 'plantilla_usuarios.csv');
    a.click();
}
</script>
</body>
</html>
