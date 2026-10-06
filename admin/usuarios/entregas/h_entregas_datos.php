<?php
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('usuarios');
require_once(__DIR__ . '/../../../config.php');
include(ROOT_PATH . 'admin/menu.php');

// 1. Procesar la actualización de baja
if (isset($_POST['registrar_baja'])) {
    $id = intval($_POST['id_registro']);
    $f_baja = $_POST['fecha_baja'];
    $o_baja = $_POST['obs_baja'];

    $stmt = mysqli_prepare($conexion, "UPDATE checklist_entregas SET fecha_baja = ?, obs_baja = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssi", $f_baja, $o_baja, $id);
    mysqli_stmt_execute($stmt);
    echo "<script>window.location='h_entregas_datos.php';</script>";
}

// 2. Procesar la Edición COMPLETA de Datos
if (isset($_POST['editar_registro'])) {
    $id = intval($_POST['id_registro_edit']);
    
    // Generales
    $colaborador = $_POST['colaborador_nom'];
    $num_nomina  = $_POST['num_nomina'];
    $area        = $_POST['area'];
    $puesto      = $_POST['puesto'];
    $fecha_ev    = $_POST['fecha_evento'];

    // PC / Windows
    $pc_user = $_POST['pc_usuario'];
    $pc_pass = $_POST['pc_password'];

    // Correo Institucional
    $correo_user = $_POST['correo_detalles'];
    $correo_pass = $_POST['correo_password'];

    // SIIAD
    $siiad_user = $_POST['siiad_detalles'];
    $siiad_pass = $_POST['siiad_password'];

    // CONTPAQi
    $contpaq_nom_user = $_POST['contpaq_nom_detalles'];
    $contpaq_nom_pass = $_POST['contpaq_nom_password'];
    $contpaq_con_user = $_POST['contpaq_con_detalles'];
    $contpaq_con_pass = $_POST['contpaq_con_password'];

    // Otros
    $checador_user    = $_POST['checador_detalles'];
    $checador_pass    = $_POST['checador_password'];
    $nip_almacen_pass = $_POST['nip_almacen_pass'];
    $telefono_det     = $_POST['telefono_detalles'];

    // Periféricos / Checkbox
    $pc_escritorio_ok = isset($_POST['pc_escritorio_ok']) ? 1 : 0;
    $monitor_ok       = isset($_POST['monitor_ok']) ? 1 : 0;
    $teclado_mouse_ok = isset($_POST['teclado_mouse_ok']) ? 1 : 0;

    $query = "UPDATE checklist_entregas SET 
                colaborador_nom = ?, num_nomina = ?, area = ?, puesto = ?, fecha_evento = ?,
                pc_usuario = ?, pc_password = ?,
                correo_detalles = ?, correo_password = ?,
                siiad_detalles = ?, siiad_password = ?,
                contpaq_nom_detalles = ?, contpaq_nom_password = ?,
                contpaq_con_detalles = ?, contpaq_con_password = ?,
                checador_detalles = ?, checador_password = ?,
                nip_almacen_pass = ?, telefono_detalles = ?,
                pc_escritorio_ok = ?, monitor_ok = ?, teclado_mouse_ok = ?
              WHERE id = ?";

    $stmt = mysqli_prepare($conexion, $query);
    mysqli_stmt_bind_param($stmt, "sssssssssssssssssssiiii", 
        $colaborador, $num_nomina, $area, $puesto, $fecha_ev,
        $pc_user, $pc_pass,
        $correo_user, $correo_pass,
        $siiad_user, $siiad_pass,
        $contpaq_nom_user, $contpaq_nom_pass,
        $contpaq_con_user, $contpaq_con_pass,
        $checador_user, $checador_pass,
        $nip_almacen_pass, $telefono_det,
        $pc_escritorio_ok, $monitor_ok, $teclado_mouse_ok,
        $id
    );
    
    mysqli_stmt_execute($stmt);
    echo "<script>window.location='h_entregas_datos.php';</script>";
}

// 3. Procesar la Eliminación del Registro
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    $stmt = mysqli_prepare($conexion, "DELETE FROM checklist_entregas WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id_eliminar);
    mysqli_stmt_execute($stmt);
    echo "<script>window.location='h_entregas_datos.php';</script>";
}

$resultado = mysqli_query($conexion, "SELECT * FROM checklist_entregas ORDER BY id DESC");
?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        .table th { background-color: #1e293b !important; color: #fff !important; }
        body { margin-top: 0; }
    </style>
<div class="container py-4">
    <div class="card shadow border-0" style="border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center p-3">
            <h5 class="mb-0 fw-bold"><i class="bi bi-laptop me-2"></i>CONTROL DE ACTIVOS TI</h5>
            <a href="entrega_recursos.php" class="btn btn-sm btn-success fw-bold px-3"><i class="bi bi-plus-lg"></i> Nueva Entrega</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Colaborador</th>
                            <th>Entrega (Alta)</th>
                            <th>Devolución (Baja)</th>
                            <th>Estado</th>
                            <th class="text-center pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = mysqli_fetch_assoc($resultado)): ?>
                        <tr>
                            <td class="ps-4"><strong><?php echo htmlspecialchars($row['colaborador_nom']); ?></strong></td>
                            <td><?php echo $row['fecha_evento'] ? date('d/m/Y', strtotime($row['fecha_evento'])) : '—'; ?></td>
                            <td><?php echo $row['fecha_baja'] ? date('d/m/Y', strtotime($row['fecha_baja'])) : '<span class="text-muted italic">Pendiente</span>'; ?></td>
                            <td>
                                <?php if(!$row['fecha_baja']): ?>
                                    <span class="badge bg-primary px-2.5 py-1.5">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary px-2.5 py-1.5">Finalizado</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-4">
                                <?php if(!$row['fecha_baja']): ?>
                                    <button class="btn btn-sm btn-warning fw-semibold" onclick="abrirModalBaja(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['colaborador_nom'], ENT_QUOTES); ?>')">
                                        <i class="bi bi-box-arrow-in-down"></i> Baja
                                    </button>
                                <?php else: ?>
                                    <a href="imprimir_baja.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-info text-white">
                                        <i class="bi bi-file-earmark-pdf"></i> Imprimir Baja
                                    </a>
                                <?php endif; ?>
                                
                                <a href="imprimir_acuse.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-dark" title="Imprimir Acuse">
                                    <i class="bi bi-printer"></i> Alta
                                </a>

                                <button class="btn btn-sm btn-outline-primary" onclick='abrirModalEditar(<?php echo json_encode($row); ?>)' title="Editar Todos los Datos">
                                    <i class="bi bi-pencil-square"></i>
                                </button>

                                <a href="h_entregas_datos.php?eliminar=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro de que deseas eliminar este registro permanentemente?');" title="Eliminar Registro">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL REGISTRAR BAJA -->
<div class="modal fade" id="modalBaja" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down me-2"></i>Registrar Devolución de Equipo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_registro" id="id_registro">
                <p class="fs-5">Colaborador: <strong id="nom_colaborador"></strong></p>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Fecha de Devolución</label>
                    <input type="date" name="fecha_baja" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-bold text-uppercase">Observaciones de Entrega / Estado</label>
                    <textarea name="obs_baja" class="form-control" rows="3" placeholder="¿En qué condiciones regresa el equipo?"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="registrar_baja" class="btn btn-warning fw-bold">Guardar Baja</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR DATOS COMPLETO (PESTAÑAS) -->
<div class="modal fade" id="modalEditar" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form class="modal-content" method="POST">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Registro Completo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_registro_edit" id="edit_id_registro">

                <!-- Pestañas de Navegación -->
                <ul class="nav nav-tabs mb-3" id="editTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold" id="general-tab" data-bs-toggle="tab" data-bs-target="#tab-general" type="button">1. Datos Generales</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold" id="credenciales-tab" data-bs-toggle="tab" data-bs-target="#tab-credenciales" type="button">2. Cuentas y Accesos</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold" id="equipos-tab" data-bs-toggle="tab" data-bs-target="#tab-equipos" type="button">3. Periféricos</button>
                    </li>
                </ul>

                <div class="tab-content" id="editTabsContent">
                    <!-- Tab 1: Datos Generales -->
                    <div class="tab-pane fade show active" id="tab-general">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-uppercase">Nombre del Colaborador</label>
                            <input type="text" name="colaborador_nom" id="edit_colaborador_nom" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-uppercase">N° Nómina</label>
                                <input type="text" name="num_nomina" id="edit_num_nomina" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-uppercase">Fecha de Entrega (Alta)</label>
                                <input type="date" name="fecha_evento" id="edit_fecha_evento" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-uppercase">Área / Depto</label>
                                <input type="text" name="area" id="edit_area" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small fw-bold text-uppercase">Puesto</label>
                                <input type="text" name="puesto" id="edit_puesto" class="form-control">
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Cuentas y Accesos -->
                    <div class="tab-pane fade" id="tab-credenciales">
                        <div class="row g-2">
                            <!-- Windows -->
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">PC / Windows Usuario</label>
                                <input type="text" name="pc_usuario" id="edit_pc_usuario" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">PC / Windows Password</label>
                                <input type="text" name="pc_password" id="edit_pc_password" class="form-control form-control-sm">
                            </div>

                            <!-- Correo -->
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Correo Institucional</label>
                                <input type="text" name="correo_detalles" id="edit_correo_detalles" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Correo Password</label>
                                <input type="text" name="correo_password" id="edit_correo_password" class="form-control form-control-sm">
                            </div>

                            <!-- SIIAD -->
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">SIIAD Usuario</label>
                                <input type="text" name="siiad_detalles" id="edit_siiad_detalles" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">SIIAD Password</label>
                                <input type="text" name="siiad_password" id="edit_siiad_password" class="form-control form-control-sm">
                            </div>

                            <!-- CONTPAQi Nóminas -->
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">CONTPAQi Nóminas User</label>
                                <input type="text" name="contpaq_nom_detalles" id="edit_contpaq_nom_detalles" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">CONTPAQi Nóminas Pass</label>
                                <input type="text" name="contpaq_nom_password" id="edit_contpaq_nom_password" class="form-control form-control-sm">
                            </div>

                            <!-- CONTPAQi Contabilidad -->
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">CONTPAQi Conta User</label>
                                <input type="text" name="contpaq_con_detalles" id="edit_contpaq_con_detalles" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">CONTPAQi Conta Pass</label>
                                <input type="text" name="contpaq_con_password" id="edit_contpaq_con_password" class="form-control form-control-sm">
                            </div>

                            <!-- Checador, NIP, Teléfono -->
                            <div class="col-md-4 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Checador User/ID</label>
                                <input type="text" name="checador_detalles" id="edit_checador_detalles" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Checador Pass</label>
                                <input type="text" name="checador_password" id="edit_checador_password" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">NIP Almacén</label>
                                <input type="text" name="nip_almacen_pass" id="edit_nip_almacen_pass" class="form-control form-control-sm">
                            </div>

                            <div class="col-12 mb-2">
                                <label class="form-label small fw-bold text-muted text-uppercase mb-1">Extensión Telefónica</label>
                                <input type="text" name="telefono_detalles" id="edit_telefono_detalles" class="form-control form-control-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: Periféricos Asignados -->
                    <div class="tab-pane fade" id="tab-equipos">
                        <label class="form-label small fw-bold text-uppercase d-block mb-3">Hardware / Periféricos Entregados</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="pc_escritorio_ok" id="edit_pc_escritorio_ok" value="1">
                            <label class="form-check-label" for="edit_pc_escritorio_ok">PC Escritorio</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="monitor_ok" id="edit_monitor_ok" value="1">
                            <label class="form-check-label" for="edit_monitor_ok">Monitor</label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="teclado_mouse_ok" id="edit_teclado_mouse_ok" value="1">
                            <label class="form-check-label" for="edit_teclado_mouse_ok">Teclado y Mouse</label>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" name="editar_registro" class="btn btn-primary fw-bold">Guardar Todos los Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function abrirModalBaja(id, nombre) {
    document.getElementById('id_registro').value = id;
    document.getElementById('nom_colaborador').innerText = nombre;
    new bootstrap.Modal(document.getElementById('modalBaja')).show();
}

function abrirModalEditar(datos) {
    // ID
    document.getElementById('edit_id_registro').value = datos.id;

    // Generales
    document.getElementById('edit_colaborador_nom').value = datos.colaborador_nom || '';
    document.getElementById('edit_num_nomina').value = datos.num_nomina || '';
    document.getElementById('edit_fecha_evento').value = datos.fecha_evento || '';
    document.getElementById('edit_area').value = datos.area || '';
    document.getElementById('edit_puesto').value = datos.puesto || '';

    // Credenciales
    document.getElementById('edit_pc_usuario').value = datos.pc_usuario || '';
    document.getElementById('edit_pc_password').value = datos.pc_password || '';
    document.getElementById('edit_correo_detalles').value = datos.correo_detalles || '';
    document.getElementById('edit_correo_password').value = datos.correo_password || '';
    document.getElementById('edit_siiad_detalles').value = datos.siiad_detalles || '';
    document.getElementById('edit_siiad_password').value = datos.siiad_password || '';
    document.getElementById('edit_contpaq_nom_detalles').value = datos.contpaq_nom_detalles || '';
    document.getElementById('edit_contpaq_nom_password').value = datos.contpaq_nom_password || '';
    document.getElementById('edit_contpaq_con_detalles').value = datos.contpaq_con_detalles || '';
    document.getElementById('edit_contpaq_con_password').value = datos.contpaq_con_password || '';
    document.getElementById('edit_checador_detalles').value = datos.checador_detalles || '';
    document.getElementById('edit_checador_password').value = datos.checador_password || '';
    document.getElementById('edit_nip_almacen_pass').value = datos.nip_almacen_pass || '';
    document.getElementById('edit_telefono_detalles').value = datos.telefono_detalles || '';

    // Checkboxes
    document.getElementById('edit_pc_escritorio_ok').checked = (datos.pc_escritorio_ok == 1);
    document.getElementById('edit_monitor_ok').checked = (datos.monitor_ok == 1);
    document.getElementById('edit_teclado_mouse_ok').checked = (datos.teclado_mouse_ok == 1);

    // Asegurar que abra en la primera pestaña
    var firstTab = new bootstrap.Tab(document.querySelector('#editTabs button:first-child'));
    firstTab.show();

    new bootstrap.Modal(document.getElementById('modalEditar')).show();
}
</script>
