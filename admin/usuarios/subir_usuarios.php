<?php
// 1. Cargar la configuración global
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificar seguridad y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('usuarios');

// 4. Cargar el menú usando la ruta absoluta
include($_SERVER['DOCUMENT_ROOT'] . '/admin/menu.php');
?>

<style>
  /* ===== CONTENEDOR PRINCIPAL ===== */
  .content {
    padding: 1.5rem 2rem;
    min-height: calc(100vh - 56px);
    background: #fff;
    transition: margin-left 0.3s ease;
  }

  h1 {
    font-weight: 600;
    color: #0d6efd;
    margin-bottom: 1.5rem;
    font-size: 1.8rem;
  }

  form label {
    font-weight: 500;
  }

  .form-text {
    font-size: 0.9rem;
    color: #6c757d;
  }

  button {
    padding: 0.55rem 1.4rem;
    font-size: 1rem;
  }

  /* ===== RESPONSIVE ===== */
  @media (max-width: 992px) {
    .content {
      margin-left: 0;
      padding: 1.2rem;
    }
    h1 {
      font-size: 1.6rem;
    }
  }

  @media (max-width: 576px) {
    .content {
      padding: 1rem;
    }
    h1 {
      font-size: 1.4rem;
      text-align: center;
    }
    button {
      /* Ajustado de 15% a 100% para mejor usabilidad en móvil */
      width: 100%; 
      font-size: 1rem;
    }
  }
</style>

<div class="content">
  <h1><i class="fa-solid fa-file-csv me-2"></i>Subir Usuarios masivamente</h1>

  <div class="card shadow-sm p-4" style="max-width: 700px;">
    <!-- El action ahora usa BASE_URL para apuntar correctamente al backend -->
    <form action="<?= BASE_URL ?>admin/usuarios/backend/procesar_csv.php" method="POST" enctype="multipart/form-data">

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
        <small>
          El archivo debe contener las siguientes columnas: 
          <strong>usuario</strong>, <strong>nombre_completo</strong>,<strong>contrasena</strong>,<strong>email</strong>,<strong>phone</strong>, <strong>área</strong>, <strong>rol</strong>.
        </small>
        <a href="#" onclick="descargarPlantilla()" class="btn btn-outline-info">
          <i class="fa-solid fa-download me-2"></i>Descargar Plantilla CSV
        </a>
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
