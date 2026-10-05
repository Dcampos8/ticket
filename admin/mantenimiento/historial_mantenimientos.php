<?php
// 1. Cargar la configuración global
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificar seguridad y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('mantenimiento');

// 4. Conexión a la base de datos
require_once(ROOT_PATH . 'backend/conexion.php');
$conexion->set_charset("utf8");

// 5. Cargar el menú y headers usando rutas absolutas
include(ROOT_PATH . 'admin/menu.php');
if(file_exists(ROOT_PATH . 'admin/header.php')){
    include(ROOT_PATH . 'admin/header.php');
}

/* ================= CONSULTA BASE ================= */
$query = "
SELECT 
    mr.id_programacion,
    mr.fecha_real,
    mr.tecnico,
    mr.actividades,
    mr.refacciones,
    mr.observaciones,
    mr.ticket_generado,
    pm.area AS area_plan,
    u.nombre_completo AS nombre_responsable,
    eq.marca,
    eq.modelo,
    eq.no_serie
FROM mantenimientos_realizados mr
LEFT JOIN programacion_mantenimiento p ON mr.id_programacion = p.id
LEFT JOIN plan_mantenimiento pm ON p.id_plan = pm.id
LEFT JOIN usuarios u ON pm.responsable = u.id
LEFT JOIN inventario_equipos eq ON pm.id_equipo = eq.id
ORDER BY mr.fecha_real DESC
";

$result = $conexion->query($query);

// Obtener el total de registros para el badge superior
$totalHistorial = $result->num_rows;
?>

<style>
    :root { --primary-red: #E30613; --dark-gray: #1a1a1a; }
    body {
        margin-top: 0;
        background-color: #f8f9fa;
    }
    
    /* Contenedor principal unificado */
    .historial-content {
        padding: 20px;
        background-color: #f8f9fa;
        min-height: 100vh;
    }

    .modal { z-index: 2000; }
    
    .main-card { 
        background: #fff; 
        border-radius: 12px; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
        padding: 25px; 
        margin-bottom: 30px; 
        border-top: 5px solid var(--primary-red); 
    }
    
    .table thead { 
        background: var(--dark-gray); 
        color: #fff; 
    }
    
    .btn-primary { 
        background-color: var(--primary-red); 
        border-color: var(--primary-red); 
    }
    
    .btn-primary:hover { 
        background-color: #b3050f; 
        border-color: #b3050f; 
    }

    @media (max-width: 992px) {
        .historial-content { margin-left: 0; }
    }
    
    /* Estilos para impresión dentro del modal */
    #contenidoHoja {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
</style>

<div class="historial-content">
    <div class="container-fluid mt-4">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="fw-bold mb-0">Historial de Mantenimientos Realizados</h2>
                <p class="text-muted">Registro de órdenes completadas e infraestructura IT intervenida</p>
            </div>
            <div class="text-end">
                <span class="badge bg-dark p-2">Total Registros: <?= $totalHistorial ?></span>
            </div>
        </div>

        <div class="main-card">
            <div class="table-responsive">
                <table class="table table-hover align-middle border shadow-sm">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Equipo / Área</th>
                            <th>Responsable</th>
                            <th>Técnico</th>
                            <th>Actividades</th>
                            <th class="text-center">Ticket</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><code><?= $row['id_programacion'] ?></code></td>
                            <td><?= date('d/m/Y', strtotime($row['fecha_real'])) ?></td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['marca'] . " " . $row['modelo']) ?></div>
                                <div class="small text-muted">SN: <?= htmlspecialchars($row['no_serie'] ?? '—') ?> | <?= htmlspecialchars($row['area_plan'] ?? 'N/A') ?></div>
                            </td>
                            <td>
                                <div class="small fw-bold text-danger"><?= htmlspecialchars($row['nombre_responsable'] ?? '—') ?></div>
                            </td>
                            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['tecnico'] ?? '—') ?></span></td>
                            <td class="small text-muted"><?= mb_strimwidth(htmlspecialchars($row['actividades']), 0, 50, "...") ?></td>
                            <td class="text-center">
                                <?= $row['ticket_generado'] ? '<span class="text-success">✅</span>' : '<span class="text-muted">—</span>' ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary fw-bold shadow-sm"
                                    onclick="verHojaServicio(
                                        <?= (int)$row['id_programacion'] ?>,
                                        '<?= addslashes(htmlspecialchars($row['tecnico'])) ?>',
                                        '<?= addslashes(htmlspecialchars($row['nombre_responsable'])) ?>'
                                    )">
                                    <i class="bi bi-printer-fill"></i> Ver / Imprimir
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL HOJA DE SERVICIO -->
<div class="modal fade" id="modalHojaServicio" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text-fill me-2"></i> Hoja de Servicio Digital</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body bg-light" id="contenidoHoja">
        <!-- Contenido dinámico via AJAX -->
      </div>
      <div class="modal-footer bg-white">
        <button class="btn btn-outline-dark fw-bold" data-bs-dismiss="modal">Cerrar</button>
        <button class="btn btn-primary fw-bold shadow-sm" onclick="imprimirHoja()">
            <i class="bi bi-printer me-2"></i> Mandar a Impresora
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function verHojaServicio(id_programacion, tecnico, responsable) {
    const modalDiv = document.getElementById('modalHojaServicio');
    const modal = new bootstrap.Modal(modalDiv);
    const contenedor = document.getElementById('contenidoHoja');

    contenedor.innerHTML = `
        <div class="text-center my-5">
            <div class="spinner-border text-danger" role="status"></div>
            <p class="mt-2 text-muted fw-bold">Generando documento...</p>
        </div>
    `;

    fetch('imprimir_hoja_servicio.php?id=' + id_programacion)
        .then(res => res.text())
        .then(html => {
            contenedor.innerHTML = html;

            const spanTecnico = document.getElementById('tecnicoServicio');
            if (spanTecnico) spanTecnico.textContent = tecnico;

            const spanEmpleado = document.getElementById('usuarioFirma');
            if (spanEmpleado) spanEmpleado.textContent = responsable;
        })
        .catch(err => {
            contenedor.innerHTML = '<div class="alert alert-danger m-3 fw-bold">Error crítico al recuperar la información del servidor.</div>';
        });

    modal.show();
}

function imprimirHoja() {
    const contenido = document.getElementById('contenidoHoja').innerHTML;
    const ventana = window.open('', '', 'width=950,height=800');

    ventana.document.write(`
        <html>
        <head>
            <title>Hoja de Servicio - IT</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; background: white !important; font-size: 12px; }
                .no-print { display: none; }
                @media print {
                    .modal-footer, .btn-close { display: none; }
                }
            </style>
        </head>
        <body>
            ${contenido}
        </body>
        </html>
    `);

    ventana.document.close();
    ventana.focus();

    setTimeout(() => {
        ventana.print();
        ventana.close();
    }, 750);
}
</script>

<?php 
if (isset($conexion)) { 
    $conexion->close(); 
} 
?>
