<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('usuarios');
?><?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

set_time_limit(300);
ini_set('memory_limit', '512M');

$mensaje = "";


/*
|--------------------------------------------------------------------------
| GUARDAR FORMULARIO
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // ==========================================================
    // 1. DATOS GENERALES
    // ==========================================================

    $tipo       = $_POST['tipo_movimiento'] ?? 'ALTA';
    $colab_nom  = trim($_POST['nombre'] ?? '');
    $num_nomina = trim($_POST['num_nomina'] ?? '');
    $puesto     = trim($_POST['puesto'] ?? '');
    $area       = trim($_POST['area'] ?? '');

    $fecha_ev = !empty($_POST['fecha'])
        ? $_POST['fecha']
        : date('Y-m-d');

    $obs = trim($_POST['observaciones'] ?? '');


    // ==========================================================
    // 2. VALIDACIONES
    // ==========================================================

    if ($colab_nom === '') {

        $mensaje = '
            <div class="alert alert-danger alert-dismissible fade show">
                El nombre del colaborador es obligatorio.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        ';

    } elseif ($puesto === '') {

        $mensaje = '
            <div class="alert alert-danger alert-dismissible fade show">
                El puesto es obligatorio.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        ';

    } elseif ($area === '') {

        $mensaje = '
            <div class="alert alert-danger alert-dismissible fade show">
                El área es obligatoria.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        ';

    } else {


        // ==========================================================
        // 3. SWITCHES / CHECKBOXES
        // ==========================================================

        $correo_ok   = isset($_POST['correo_ok']) ? 1 : 0;
        $siiad_ok    = isset($_POST['siiad_ok']) ? 1 : 0;
        $checador_ok = isset($_POST['checador_ok']) ? 1 : 0;

        $pc_ok       = isset($_POST['pc_escritorio_ok']) ? 1 : 0;
        $monitor_ok  = isset($_POST['monitor_ok']) ? 1 : 0;
        $tk_mouse_ok = isset($_POST['teclado_mouse_ok']) ? 1 : 0;
        $pc_user_ok  = isset($_POST['pc_user_ok']) ? 1 : 0;

        $tel_ok = isset($_POST['telefono_ext_ok']) ? 1 : 0;

        $nip_ok = isset($_POST['nip_almacen_ok']) ? 1 : 0;

        $contpaq_nom_ok = isset($_POST['contpaq_nom_ok']) ? 1 : 0;
        $contpaq_con_ok = isset($_POST['contpaq_con_ok']) ? 1 : 0;


        // ==========================================================
        // 4. CORREO
        // ==========================================================

        $correo_det  = $_POST['correo_detalles'] ?? '';
        $correo_pass = $_POST['correo_password'] ?? '';


        // ==========================================================
        // 5. SIIAD
        // ==========================================================

        $siiad_det  = $_POST['siiad_detalles'] ?? '';
        $siiad_pass = $_POST['siiad_password'] ?? '';


        // ==========================================================
        // 6. CHECADOR
        // ==========================================================

        $checador_det  = $_POST['checador_detalles'] ?? '';
        $checador_pass = $_POST['checador_password'] ?? '';


        // ==========================================================
        // 7. PC / WINDOWS
        // ==========================================================

        $pc_usuario  = $_POST['pc_usuario'] ?? '';
        $pc_password = $_POST['pc_password'] ?? '';


        // ==========================================================
        // 8. TELEFONO
        // ==========================================================

        $tel_det = $_POST['telefono_detalles'] ?? '';


        // ==========================================================
        // 9. NIP ALMACEN
        // ==========================================================

        $nip_det  = $_POST['nip_almacen_detalles'] ?? '';
        $nip_pass = $_POST['nip_almacen_pass'] ?? '';


        // ==========================================================
        // 10. CONTPAQ NOMINAS
        // ==========================================================

        $contpaq_nom_det  = $_POST['contpaq_nom_detalles'] ?? '';
        $contpaq_nom_pass = $_POST['contpaq_nom_password'] ?? '';


        // ==========================================================
        // 11. CONTPAQ CONTABILIDAD
        // ==========================================================

        $contpaq_con_det  = $_POST['contpaq_con_detalles'] ?? '';
        $contpaq_con_pass = $_POST['contpaq_con_password'] ?? '';


        // ==========================================================
        // 12. INSERT
        // ==========================================================

        $sql = "INSERT INTO checklist_entregas
        (
            tipo_movimiento,
            colaborador_nom,
            num_nomina,
            puesto,
            area,
            fecha_evento,

            correo_ok,
            correo_detalles,
            correo_password,

            siiad_ok,
            siiad_detalles,
            siiad_password,

            checador_ok,
            checador_detalles,
            checador_password,

            pc_escritorio_ok,
            monitor_ok,
            teclado_mouse_ok,
            pc_user_ok,

            pc_usuario,
            pc_password,

            telefono_ext_ok,
            telefono_detalles,

            nip_almacen_ok,
            nip_almacen_detalles,
            nip_almacen_pass,

            contpaq_nom_ok,
            contpaq_nom_detalles,
            contpaq_nom_password,

            contpaq_con_ok,
            contpaq_con_detalles,
            contpaq_con_password,

            observaciones
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?
        )";


        // ==========================================================
        // 13. PREPARAR
        // ==========================================================

        $stmt = mysqli_prepare($conexion, $sql);

        if (!$stmt) {

            $mensaje = '
                <div class="alert alert-danger alert-dismissible fade show">
                    <strong>Error al preparar la consulta:</strong><br>
                    ' . htmlspecialchars(mysqli_error($conexion)) . '
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            ';

        } else {


            /*
             * ======================================================
             * CADENA CORRECTA
             * ======================================================
             *
             * 1-6   ssssss
             *
             * 7-9   iss
             * 10-12 iss
             * 13-15 iss
             *
             * 16-19 iiii
             *
             * 20-21 ss
             *
             * 22-23 is
             *
             * 24-26 iss
             *
             * 27-29 iss
             *
             * 30-32 iss
             *
             * 33    s
             *
             * TOTAL = 33
             *
             * IMPORTANTE:
             * Los campos password son STRING (s).
             */

            $tipos = "ssssssissississiiiissisissississs";


            mysqli_stmt_bind_param(
                $stmt,
                $tipos,

                // ==================================================
                // 1 - 6 DATOS GENERALES
                // ==================================================

                $tipo,
                $colab_nom,
                $num_nomina,
                $puesto,
                $area,
                $fecha_ev,


                // ==================================================
                // 7 - 9 CORREO
                // ==================================================

                $correo_ok,
                $correo_det,
                $correo_pass,


                // ==================================================
                // 10 - 12 SIIAD
                // ==================================================

                $siiad_ok,
                $siiad_det,
                $siiad_pass,


                // ==================================================
                // 13 - 15 CHECADOR
                // ==================================================

                $checador_ok,
                $checador_det,
                $checador_pass,


                // ==================================================
                // 16 - 19 HARDWARE
                // ==================================================

                $pc_ok,
                $monitor_ok,
                $tk_mouse_ok,
                $pc_user_ok,


                // ==================================================
                // 20 - 21 PC
                // ==================================================

                $pc_usuario,
                $pc_password,


                // ==================================================
                // 22 - 23 TELEFONO
                // ==================================================

                $tel_ok,
                $tel_det,


                // ==================================================
                // 24 - 26 NIP ALMACEN
                // ==================================================

                $nip_ok,
                $nip_det,
                $nip_pass,


                // ==================================================
                // 27 - 29 CONTPAQ NOMINAS
                // ==================================================

                $contpaq_nom_ok,
                $contpaq_nom_det,
                $contpaq_nom_pass,


                // ==================================================
                // 30 - 32 CONTPAQ CONTABILIDAD
                // ==================================================

                $contpaq_con_ok,
                $contpaq_con_det,
                $contpaq_con_pass,


                // ==================================================
                // 33 OBSERVACIONES
                // ==================================================

                $obs
            );


            // ======================================================
            // 14. EJECUTAR
            // ======================================================

            if (mysqli_stmt_execute($stmt)) {

                $nuevo_id = mysqli_insert_id($conexion);

                mysqli_stmt_close($stmt);


                /*
                 * ==================================================
                 * PRG
                 * ==================================================
                 */

                header(
                    "Location: " .
                    $_SERVER['PHP_SELF'] .
                    "?guardado=1&id=" .
                    $nuevo_id
                );

                exit;

            } else {

                $mensaje = '
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Error al guardar el registro:</strong><br>
                        ' . htmlspecialchars(mysqli_stmt_error($stmt)) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                ';

                mysqli_stmt_close($stmt);
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| MENSAJE DE REGISTRO EXITOSO
|--------------------------------------------------------------------------
*/

if (isset($_GET['guardado']) && $_GET['guardado'] == '1') {

    $id_guardado = intval($_GET['id'] ?? 0);

    $mensaje = '
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <strong>¡Registro guardado correctamente!</strong><br>
            El movimiento fue registrado en el expediente.
            ' . (
                $id_guardado > 0
                ? '<br><small>Folio: <strong>#' . $id_guardado . '</strong></small>'
                : ''
            ) . '
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    ';
}


/*
|--------------------------------------------------------------------------
| MENU
|--------------------------------------------------------------------------
*/

include(ROOT_PATH . 'admin/menu.php');

?>

<!DOCTYPE html>

<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Checklist Digital - Solutek</title>


    <!-- Bootstrap -->

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Animate -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"
    >


    <style>

        :root {
            --primary: #003366;
            --accent: #b8922e;
        }


        body {
            background-color: #f8f9fa;
            font-size: 0.9rem;
            margin-top: 0;
        }


        .card-header {
            background: var(--primary);
            color: white;
            border-bottom: 4px solid var(--accent);
        }


        .section-header {
            background: #eee;
            padding: 7px 12px;
            font-weight: bold;
            color: var(--primary);
            border-radius: 4px;
            margin: 20px 0;
            font-size: 0.85rem;
            border-left: 4px solid var(--accent);
        }


        .detail-box {
            border-left: 3px solid var(--accent);
            padding: 5px 10px;
            background: #fffdf5;
            margin-top: 8px;
            border-radius: 0 5px 5px 0;
        }


        .form-switch .form-check-input {
            width: 2.5em;
            height: 1.25em;
            cursor: pointer;
        }


        .form-control:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 0.25rem rgba(184, 146, 46, 0.15);
        }


        .btn-primary {
            background-color: var(--primary);
            border: none;
        }


        .btn-primary:hover {
            background-color: #002244;
        }


        .section-title {
            font-size: 0.85rem;
            font-weight: bold;
        }


        .password-toggle {
            cursor: pointer;
        }


        .required-label::after {
            content: " *";
            color: #dc3545;
        }

    </style>

</head>


<body>


<div class="container py-4">

    <div class="row justify-content-center">

        <div class="col-lg-10">

            <?php echo $mensaje; ?>


            <div class="card shadow-sm border-0">


                <!-- ==================================================
                     FORMULARIO
                =================================================== -->

                <form
                    action=""
                    method="POST"
                    autocomplete="off"
                >


                    <!-- ==================================================
                         HEADER
                    =================================================== -->

                    <div class="card-header d-flex justify-content-between align-items-center py-3">

                        <h5 class="mb-0 fw-bold">
                            GESTIÓN DE RECURSOS TI
                        </h5>


                        <select
                            name="tipo_movimiento"
                            class="form-select w-auto fw-bold shadow-sm"
                        >

                            <option value="ALTA">
                                🟢 ALTA / ENTREGA
                            </option>

                            <option value="BAJA">
                                🔴 BAJA / RECEPCIÓN
                            </option>

                        </select>

                    </div>


                    <!-- ==================================================
                         BODY
                    =================================================== -->

                    <div class="card-body p-4">


                        <!-- ==================================================
                             1. PERSONAL
                        =================================================== -->

                        <div class="section-header text-uppercase">
                            1. Datos del Colaborador
                        </div>


                        <div class="row g-3 mb-4">

                            <div class="col-md-5">

                                <label class="form-label small fw-bold required-label">
                                    Nombre Completo
                                </label>

                                <input
                                    type="text"
                                    name="nombre"
                                    class="form-control shadow-sm"
                                    required
                                >

                            </div>


                            <div class="col-md-2">

                                <label class="form-label small fw-bold">
                                    N° Nómina
                                </label>

                                <input
                                    type="text"
                                    name="num_nomina"
                                    class="form-control shadow-sm"
                                    placeholder="Ej. 1042"
                                >

                            </div>


                            <div class="col-md-2">

                                <label class="form-label small fw-bold required-label">
                                    Área
                                </label>

                                <input
                                    type="text"
                                    name="area"
                                    class="form-control shadow-sm"
                                    required
                                >

                            </div>


                            <div class="col-md-3">

                                <label class="form-label small fw-bold required-label">
                                    Puesto
                                </label>

                                <input
                                    type="text"
                                    name="puesto"
                                    class="form-control shadow-sm"
                                    required
                                >

                            </div>


                            <div class="col-md-3 ms-auto">

                                <label class="form-label small fw-bold">
                                    Fecha de Evento
                                </label>

                                <input
                                    type="date"
                                    name="fecha"
                                    class="form-control shadow-sm"
                                    value="<?php echo date('Y-m-d'); ?>"
                                >

                            </div>

                        </div>


                        <!-- ==================================================
                             2. ACCESOS Y SISTEMAS
                        =================================================== -->

                        <div class="section-header text-uppercase">
                            2. Accesos a Sistemas y Software
                        </div>


                        <div class="row g-4">


                            <!-- CORREO -->

                            <div class="col-md-4">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="correo_ok"
                                        id="swCorreo"
                                        onchange="toggleBox('boxCorreo', this)"
                                    >

                                    <label
                                        class="form-check-label fw-bold"
                                        for="swCorreo"
                                    >
                                        Correo Corporativo
                                    </label>

                                </div>


                                <div
                                    id="boxCorreo"
                                    class="detail-box d-none"
                                >

                                    <input
                                        type="text"
                                        name="correo_detalles"
                                        class="form-control form-control-sm mb-1"
                                        placeholder="usuario@empresa.com"
                                    >


                                    <input
                                        type="password"
                                        name="correo_password"
                                        class="form-control form-control-sm"
                                        placeholder="Contraseña de Correo"
                                    >

                                </div>

                            </div>


                            <!-- SIIAD -->

                            <div class="col-md-4">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="siiad_ok"
                                        id="swSiiad"
                                        onchange="toggleBox('boxSiiad', this)"
                                    >

                                    <label
                                        class="form-check-label fw-bold"
                                        for="swSiiad"
                                    >
                                        Sistema Interno (SIIAD)
                                    </label>

                                </div>


                                <div
                                    id="boxSiiad"
                                    class="detail-box d-none"
                                >

                                    <input
                                        type="text"
                                        name="siiad_detalles"
                                        class="form-control form-control-sm mb-1"
                                        placeholder="Usuario"
                                    >


                                    <input
                                        type="password"
                                        name="siiad_password"
                                        class="form-control form-control-sm"
                                        placeholder="Contraseña"
                                    >

                                </div>

                            </div>


                            <!-- CHECADOR -->

                            <div class="col-md-4">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="checador_ok"
                                        id="swChec"
                                        onchange="toggleBox('boxChec', this)"
                                    >

                                    <label
                                        class="form-check-label fw-bold"
                                        for="swChec"
                                    >
                                        Reloj Checador
                                    </label>

                                </div>


                                <div
                                    id="boxChec"
                                    class="detail-box d-none"
                                >

                                    <input
                                        type="text"
                                        name="checador_detalles"
                                        class="form-control form-control-sm mb-1"
                                        placeholder="ID / Código Usuario"
                                    >


                                    <input
                                        type="password"
                                        name="checador_password"
                                        class="form-control form-control-sm"
                                        placeholder="PIN / Password Checador"
                                    >

                                </div>

                            </div>


                            <!-- CONTPAQ NOMINAS -->

                            <div class="col-md-4">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="contpaq_nom_ok"
                                        id="swContpaqNom"
                                        onchange="toggleBox('boxContpaqNom', this)"
                                    >

                                    <label
                                        class="form-check-label fw-bold"
                                        for="swContpaqNom"
                                    >
                                        CONTPAQi Nóminas
                                    </label>

                                </div>


                                <div
                                    id="boxContpaqNom"
                                    class="detail-box d-none"
                                >

                                    <input
                                        type="text"
                                        name="contpaq_nom_detalles"
                                        class="form-control form-control-sm mb-1"
                                        placeholder="Usuario / Perfil"
                                    >


                                    <input
                                        type="password"
                                        name="contpaq_nom_password"
                                        class="form-control form-control-sm"
                                        placeholder="Contraseña"
                                    >

                                </div>

                            </div>


                            <!-- CONTPAQ CONTABILIDAD -->

                            <div class="col-md-4">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="contpaq_con_ok"
                                        id="swContpaqCon"
                                        onchange="toggleBox('boxContpaqCon', this)"
                                    >

                                    <label
                                        class="form-check-label fw-bold"
                                        for="swContpaqCon"
                                    >
                                        CONTPAQi Contabilidad
                                    </label>

                                </div>


                                <div
                                    id="boxContpaqCon"
                                    class="detail-box d-none"
                                >

                                    <input
                                        type="text"
                                        name="contpaq_con_detalles"
                                        class="form-control form-control-sm mb-1"
                                        placeholder="Usuario / Perfil"
                                    >


                                    <input
                                        type="password"
                                        name="contpaq_con_password"
                                        class="form-control form-control-sm"
                                        placeholder="Contraseña"
                                    >

                                </div>

                            </div>


                            <!-- NIP ALMACEN -->

                            <div class="col-md-4">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="nip_almacen_ok"
                                        id="swNip"
                                        onchange="toggleBox('boxNip', this)"
                                    >

                                    <label
                                        class="form-check-label fw-bold"
                                        for="swNip"
                                    >
                                        NIP de Almacén
                                    </label>

                                </div>


                                <div
                                    id="boxNip"
                                    class="detail-box d-none"
                                >

                                    <input
                                        type="password"
                                        name="nip_almacen_pass"
                                        class="form-control form-control-sm"
                                        placeholder="Código NIP / PIN"
                                    >

                                </div>

                            </div>

                        </div>


                        <!-- ==================================================
                             3. HARDWARE
                        =================================================== -->

                        <div class="section-header text-uppercase">
                            3. Equipo de Cómputo y Telefonía
                        </div>


                        <div class="row g-3">


                            <!-- CPU -->

                            <div class="col-md-3">

                                <div class="form-check border p-2 rounded bg-white shadow-xs">

                                    <input
                                        class="form-check-input ms-0 me-2"
                                        type="checkbox"
                                        name="pc_escritorio_ok"
                                        id="h1"
                                    >

                                    <label
                                        class="form-check-label small fw-bold"
                                        for="h1"
                                    >
                                        CPU / LAPTOP
                                    </label>

                                </div>

                            </div>


                            <!-- MONITOR -->

                            <div class="col-md-3">

                                <div class="form-check border p-2 rounded bg-white">

                                    <input
                                        class="form-check-input ms-0 me-2"
                                        type="checkbox"
                                        name="monitor_ok"
                                        id="h2"
                                    >

                                    <label
                                        class="form-check-label small fw-bold"
                                        for="h2"
                                    >
                                        MONITOR
                                    </label>

                                </div>

                            </div>


                            <!-- TECLADO MOUSE -->

                            <div class="col-md-3">

                                <div class="form-check border p-2 rounded bg-white">

                                    <input
                                        class="form-check-input ms-0 me-2"
                                        type="checkbox"
                                        name="teclado_mouse_ok"
                                        id="h3"
                                    >

                                    <label
                                        class="form-check-label small fw-bold"
                                        for="h3"
                                    >
                                        TECLADO / MOUSE
                                    </label>

                                </div>

                            </div>


                            <!-- TELEFONO -->

                            <div class="col-md-3">

                                <div class="form-check form-switch border p-2 rounded bg-white">

                                    <input
                                        class="form-check-input ms-0 me-2"
                                        type="checkbox"
                                        name="telefono_ext_ok"
                                        id="swTel"
                                        onchange="toggleBox('boxTel', this)"
                                    >

                                    <label
                                        class="form-check-label small fw-bold"
                                        for="swTel"
                                    >
                                        TELÉFONO / EXT
                                    </label>

                                </div>


                                <div
                                    id="boxTel"
                                    class="detail-box d-none"
                                >

                                    <input
                                        type="text"
                                        name="telefono_detalles"
                                        class="form-control form-control-sm"
                                        placeholder="Extensión o Núm. Teléfono"
                                    >

                                </div>

                            </div>


                            <!-- CREDENCIALES PC -->

                            <div class="col-md-6 mt-3">

                                <div class="form-check form-switch border p-2 rounded bg-white">

                                    <input
                                        class="form-check-input ms-0 me-2"
                                        type="checkbox"
                                        name="pc_user_ok"
                                        id="swPcUser"
                                        onchange="toggleBox('boxPcUser', this)"
                                    >

                                    <label
                                        class="form-check-label small fw-bold"
                                        for="swPcUser"
                                    >
                                        CREDENCIALES DE PC (Windows/Dominio)
                                    </label>

                                </div>


                                <div
                                    id="boxPcUser"
                                    class="detail-box d-none"
                                >

                                    <div class="row g-2">

                                        <div class="col-6">

                                            <input
                                                type="text"
                                                name="pc_usuario"
                                                class="form-control form-control-sm"
                                                placeholder="Usuario PC"
                                            >

                                        </div>


                                        <div class="col-6">

                                            <input
                                                type="password"
                                                name="pc_password"
                                                class="form-control form-control-sm"
                                                placeholder="Contraseña PC"
                                            >

                                        </div>

                                    </div>

                                </div>

                            </div>

                        </div>


                        <!-- ==================================================
                             4. OBSERVACIONES
                        =================================================== -->

                        <div class="mt-4">

                            <label class="form-label small fw-bold text-muted text-uppercase">
                                Observaciones Generales
                            </label>


                            <textarea
                                name="observaciones"
                                class="form-control shadow-sm"
                                rows="3"
                                placeholder="Estado del equipo, accesorios adicionales, etc."
                            ></textarea>

                        </div>

                    </div>


                    <!-- ==================================================
                         FOOTER
                    =================================================== -->

                    <div class="card-footer bg-light text-end py-3">

                        <button
                            type="submit"
                            class="btn btn-primary px-5 fw-bold shadow-sm"
                        >
                            GUARDAR EN EXPEDIENTE
                        </button>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>


<!-- ==========================================================
     JAVASCRIPT
=========================================================== -->

<script>

function toggleBox(id, sw) {

    const box = document.getElementById(id);

    if (!box) {
        return;
    }

    const inputs = box.querySelectorAll('input');


    if (sw.checked) {

        box.classList.remove('d-none');

        box.classList.remove(
            'animate__animated',
            'animate__fadeInDown'
        );

        void box.offsetWidth;

        box.classList.add(
            'animate__animated',
            'animate__fadeInDown'
        );


        if (inputs.length > 0) {

            setTimeout(function () {

                inputs[0].focus();

            }, 150);

        }

    } else {

        box.classList.add('d-none');

        /*
         * IMPORTANTE:
         * Aquí se limpian los campos cuando se desactiva
         * el sistema.
         */

        inputs.forEach(function(input) {

            input.value = "";

        });

    }

}

</script>


<!-- Bootstrap JS -->

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


</body>

</html>
