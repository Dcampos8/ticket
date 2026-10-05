<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('firmas');

include '../menu.php';

$usuarios = $conexion->query("
  SELECT id, nombre_completo, area
  FROM usuarios
  ORDER BY nombre_completo ASC
");
?>
<style>
    /* Base y Tipografía */
    body { background-color: #f4f7f6; font-family: 'Inter', sans-serif; }
    
    /* Contenedor Pro */
    .container { 
        background: #ffffff; 
        padding: 30px; 
        border-radius: 20px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.05); 
        margin-top: 135px !important;
    }

    h4 { 
        color: #1e3a8a; 
        font-weight: 700; 
        border-left: 5px solid #e31e24; 
        padding-left: 15px; 
        margin-bottom: 25px;
        text-transform: uppercase;
        font-size: 1.2rem;
    }

    /* Tabla Estilo Dashboard */
    .table { border: none; border-collapse: separate; border-spacing: 0 10px; }
    .table thead th { 
        background-color: #1e3a8a !important; 
        color: white !important; 
        border: none; 
        padding: 15px;
        font-size: 0.85rem;
        text-align: center;
    }
    .table thead th:first-child { border-radius: 10px 0 0 10px; }
    .table thead th:last-child { border-radius: 0 10px 10px 0; }

    .table tbody tr { 
        background-color: #fdfdfd; 
        transition: all 0.2s;
        box-shadow: 0 2px 5px rgba(0,0,0,0.02);
    }
    .table tbody tr:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        background-color: #fff;
    }

    .table td { 
        padding: 12px !important; 
        vertical-align: middle; 
        border: none !important;
        color: #475569;
        font-size: 0.9rem;
    }

    /* Inputs dentro de la tabla */
    .form-control-sm { 
        border-radius: 8px; 
        border: 1px solid #e2e8f0; 
        padding: 8px 12px;
        transition: all 0.3s;
    }
    .form-control-sm:focus { 
        border-color: #e31e24; 
        box-shadow: 0 0 0 3px rgba(227, 30, 36, 0.1); 
    }

    /* Botón Generar */
    .btn-primary { 
        background: #1e3a8a;
        border: none; 
        border-radius: 8px; 
        padding: 8px 15px; 
        font-weight: 600; 
        transition: transform 0.2s;
    }
    .btn-primary:hover { 
        transform: scale(1.05); 
        box-shadow: 0 4px 12px rgba(30, 58, 138, 0.2);
    }

    /* Badge de Área */
    .area-badge {
        background: #eff6ff;
        color: #1e40af;
        padding: 4px 10px;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.8rem;
    }
</style>
<div class="container">
<h4>Generar firma de correo</h4>

<table class="table table-bordered table-sm">
<thead class="table-dark">
<tr>
<th>Usuario</th>
<th>Área</th>
<th>Puesto</th>
<th>Teléfono</th>
<th>Correo destino</th>
<th>Acción</th>
</tr>
</thead>

<tbody>

<?php while($u = $usuarios->fetch_assoc()): ?>

<form method="POST" action="backend/procesar_firma.php">
<tr>

<td><?= htmlspecialchars($u['nombre_completo']) ?></td>

<td><?= htmlspecialchars($u['area']) ?></td>

<td>
<input type="text"
name="puesto"
class="form-control form-control-sm"
placeholder="Ej. Gerente de Operaciones"
required>
</td>

<td>
    <input type="text" 
           name="telefono" 
           class="form-control form-control-sm" 
           placeholder="Teléfono">
</td>

<td>
<input type="email"
name="correo"
class="form-control form-control-sm"
placeholder="correo@empresa.com"
required>
</td>

<td>

<input type="hidden" name="user_id" value="<?= $u['id'] ?>">

<button class="btn btn-sm btn-primary">
Generar y enviar
</button>

</td>

</tr>
</form>

<?php endwhile; ?>

</tbody>
</table>
</div>
