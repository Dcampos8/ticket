<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');
// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificar seguridad y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('mantenimiento');

// 4. Conexión a la base de datos
require_once(ROOT_PATH . 'backend/conexion.php');

// 5. Cargar el menú y headers
include(ROOT_PATH . 'admin/menu.php');
?>

<style>
    body { background-color: #f8fafc; margin-top:0;}

    /* Contenedor principal para respetar el menú lateral */
    .calendar-main-content {
        padding: 30px;
        transition: all 0.3s;
    }

    .fc { 
        background: #ffffff; 
        padding: 25px; 
        border-radius: 15px; 
        box-shadow: 0 10px 25px rgba(0,0,0,0.05); 
        border: none;
    }

    .btn-email-main {
        background: #1e3a8a;
        border: none; padding: 12px 25px; border-radius: 10px; font-weight: 700; color: white;
        transition: transform 0.2s;
    }
    .btn-email-main:hover { transform: translateY(-2px); color: #fff; opacity: 0.9; }

    /* Estilo para el botón de Excel */
    .btn-excel-main {
        background: #187443;
        border: none; padding: 12px 25px; border-radius: 10px; font-weight: 700; color: white;
        transition: transform 0.2s;
    }
    .btn-excel-main:hover { transform: translateY(-2px); color: #fff; opacity: 0.9; }

    .section-title { 
        color: #1e3a8a; 
        font-weight: 800; 
        border-left: 6px solid #e31e24; 
        padding-left: 15px; 
        text-transform: uppercase; 
    }

    .modal-xl-custom { max-width: 95%; }

    @media (max-width: 992px) {
        .calendar-main-content { margin-left: 0; padding: 15px; }
    }
</style>

<div class="calendar-main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4 class="section-title">Calendario de Mantenimientos</h4>
            <div>
                <a href="<?= BASE_URL ?>admin/mantenimiento/backend/exportar_excel_programacion.php" class="btn btn-excel-main shadow-sm me-2">
                    <i class="fa-solid fa-file-excel me-2"></i> Exportar a Excel
                </a>
                <button class="btn btn-email-main shadow-sm" data-bs-toggle="modal" data-bs-target="#modalTrimestre">
                    <i class="fa-solid fa-paper-plane me-2"></i> Enviar por Trimestre
                </button>
            </div>
        </div>

        <div id="calendar"></div>

        <div class="modal fade" id="modalTrimestre" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="<?= BASE_URL ?>admin/mantenimiento/backend/enviar_planeacion.php" class="w-100">
                    <div class="modal-content shadow-lg" style="border-radius: 20px;">
                        <div class="modal-header" style="background: #1e3a8a; color: white;">
                            <h5 class="modal-title">Seleccionar Periodo de Envío</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Trimestre a Notificar:</label>
                                <select name="trimestre_val" class="form-select border-2" required>
                                    <option value="">--- Seleccione Trimestre ---</option>
                                    <?php
                                    $anio_act = date('Y');
                                    $q = $conexion->query("SELECT DISTINCT QUARTER(fecha_programada) as q FROM programacion_mantenimiento WHERE YEAR(fecha_programada) = $anio_act ORDER BY q ASC");
                                    $nombres = [1 => "1er Trimestre (Ene-Mar)", 2 => "2do Trimestre (Abr-Jun)", 3 => "3er Trimestre (Jul-Sep)", 4 => "4to Trimestre (Oct-Dic)"];
                                    while($r = $q->fetch_assoc()) {
                                        echo "<option value='".$r['q']."'>".$nombres[$r['q']]." $anio_act</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Destinatarios:</label>
                                <textarea class="form-control border-2" name="correos" rows="2" placeholder="ejemplo@valadez.com" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Mensaje Adicional:</label>
                                <textarea class="form-control border-2" name="mensaje_extra" rows="2"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="submit" class="btn btn-primary px-4 fw-bold btn-email-main">Enviar Planeación</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="modalRegistro" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-xl-custom">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
                    <div class="modal-header bg-dark text-white">
                        <h5 class="modal-title"><i class="fa-solid fa-tools me-2"></i> Gestión de Mantenimiento</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-0">
                        <iframe id="frameRegistro" src="" frameborder="0" style="width: 100%; height: 75vh;"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');
    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'es',
        headerToolbar: { 
            left: 'prev,next today', 
            center: 'title', 
            right: 'dayGridMonth,timeGridWeek' 
        },
        events: '<?= BASE_URL ?>admin/mantenimiento/backend/eventos_mantenimientos.php',

        eventClick: function(info) {
            info.jsEvent.preventDefault();
            var id_prog = info.event.id;
            var urlDestino = '<?= BASE_URL ?>admin/mantenimiento/registrar_mantenimiento.php?id=' + id_prog;
            
            console.log("Cargando URL:", urlDestino); 
            
            document.getElementById('frameRegistro').src = urlDestino;
            
            var myModal = new bootstrap.Modal(document.getElementById('modalRegistro'));
            myModal.show();
        },
        eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false }
    });
    calendar.render();
});
</script>

<?php 
if (isset($conexion)) { $conexion->close(); } 
?>
