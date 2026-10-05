<?php
// Defensivo: nos apoyamos en $_SESSION para el token CSRF y para no repetir
// las verificaciones de esquema en cada carga. Si config.php ya abrió la
// sesión no pasa nada (se detecta y se omite), pero si algún día cambia el
// orden de includes, esto evita un fatal por usar $_SESSION sin sesión activa.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('lineas');

/* =========================================================
   BITÁCORA DE LÍNEAS (historial_lineas) Y STOCK DE EQUIPOS
   ---------------------------------------------------------
   Se asegura la estructura aquí arriba (antes de cualquier
   acción) para que quede lista desde la primera visita, y no
   solo cuando se ejecuta una reasignación.

   NUEVO: todo este bloque de verificación de esquema (CREATE TABLE,
   ALTER TABLE para columnas nuevas, e índices) antes corría en CADA
   carga de página. Ahora solo corre una vez por sesión de panel
   ($_SESSION['lt_schema_verificado']), que es lo único que hace falta
   para que el esquema quede al día — así se ahorran ~10 consultas de
   verificación en cada clic normal de uso.
   ========================================================= */
if (empty($_SESSION['lt_schema_verificado'])) {
    $conexion->query("CREATE TABLE IF NOT EXISTS historial_lineas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        id_linea INT NOT NULL,
        numero_telefono VARCHAR(20),
        usuario_anterior VARCHAR(150),
        puesto_anterior VARCHAR(150),
        usuario_nuevo VARCHAR(150),
        puesto_nuevo VARCHAR(150),
        fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
        notas TEXT
    )");
    $conexion->query("CREATE TABLE IF NOT EXISTS equipos_stock (
        id INT AUTO_INCREMENT PRIMARY KEY,
        equipo VARCHAR(100),
        modelo VARCHAR(100),
        imei VARCHAR(100),
        costo DECIMAL(10,2),
        estatus VARCHAR(50) DEFAULT 'Disponible',
        origen_usuario VARCHAR(150),
        numero_telefono VARCHAR(20),
        fecha_ingreso DATETIME DEFAULT CURRENT_TIMESTAMP,
        notas TEXT
    )");
    /* Columnas nuevas de la bitácora: qué tipo de acción fue (para el ícono del
       timeline) y quién la realizó (usuario del panel, no el empleado dueño de
       la línea). Se agregan de forma segura para no romper una tabla que ya
       exista de antes sin estas columnas. */
    function lt_asegurarColumna(mysqli $conexion, string $tabla, string $columna, string $ddl): void {
        try {
            $chk = $conexion->query("SHOW COLUMNS FROM `{$tabla}` LIKE '{$columna}'");
            if ($chk && $chk->num_rows === 0) {
                $conexion->query("ALTER TABLE `{$tabla}` ADD COLUMN {$ddl}");
            }
        } catch (\Throwable $exCol) {
            error_log("lineas.php: no se pudo agregar la columna {$columna} en {$tabla}: " . $exCol->getMessage());
        }
    }
    /**
     * Crea un índice (o único) si todavía no existe con ese nombre en la tabla.
     * Se envuelve en try/catch a propósito: si ya existen datos duplicados
     * (por ejemplo números repetidos de antes de esta mejora), crear el índice
     * único fallaría — y sin este try/catch, tronaría TODA la página en cada
     * carga hasta corregir manualmente los datos. Aquí simplemente se omite
     * y se registra en el log de errores de PHP para revisarlo con calma.
     */
    function lt_asegurarIndice(mysqli $conexion, string $tabla, string $nombreIndice, string $ddl): void {
        try {
            $chk = $conexion->query("SHOW INDEX FROM `{$tabla}` WHERE Key_name = '{$nombreIndice}'");
            if ($chk && $chk->num_rows === 0) {
                $conexion->query("ALTER TABLE `{$tabla}` ADD {$ddl}");
            }
        } catch (\Throwable $exIdx) {
            error_log("lineas.php: no se pudo crear el índice {$nombreIndice} en {$tabla}: " . $exIdx->getMessage());
        }
    }
    lt_asegurarColumna($conexion, 'historial_lineas', 'tipo_accion', "tipo_accion VARCHAR(30) DEFAULT 'otro' AFTER id_linea");
    lt_asegurarColumna($conexion, 'historial_lineas', 'realizado_por', "realizado_por VARCHAR(150) NULL AFTER notas");
    /* Columna dedicada de IMEI en la bitácora. Antes el IMEI solo quedaba
       mezclado dentro del texto de "notas"; con esta columna aparte se
       puede filtrar/exportar un reporte de "quién tenía el equipo antes,
       a quién le quedó, número, fecha e IMEI" sin tener que leer el texto. */
    lt_asegurarColumna($conexion, 'historial_lineas', 'imei', "imei VARCHAR(100) NULL AFTER numero_telefono");
    /* "Eliminar" ya no borra el registro de la base: lo manda a la Papelera
       (eliminado = 1). Desde ahí se puede restaurar o, si de verdad ya no
       sirve, eliminar en definitiva. */
    lt_asegurarColumna($conexion, 'lineas_telefonicas', 'eliminado', "eliminado TINYINT(1) NOT NULL DEFAULT 0");
    lt_asegurarColumna($conexion, 'lineas_telefonicas', 'fecha_eliminado', "fecha_eliminado DATETIME NULL");
    /* Mismo patrón de Papelera, ahora también para el Stock de Equipos: antes
       "Eliminar" en el stock borraba el registro para siempre, sin poder
       recuperarlo. Ahora se comporta igual que en líneas (manda a Papelera). */
    lt_asegurarColumna($conexion, 'equipos_stock', 'eliminado', "eliminado TINYINT(1) NOT NULL DEFAULT 0");
    lt_asegurarColumna($conexion, 'equipos_stock', 'fecha_eliminado', "fecha_eliminado DATETIME NULL");

    /* NUEVO: índices de desempeño sobre las columnas que más se filtran/buscan,
       para que la tabla no se vuelva lenta conforme crezca el catálogo. */
    lt_asegurarIndice($conexion, 'lineas_telefonicas', 'idx_lt_estatus', "INDEX idx_lt_estatus (estatus_linea)");
    lt_asegurarIndice($conexion, 'lineas_telefonicas', 'idx_lt_tipo_emp', "INDEX idx_lt_tipo_emp (tipo_emp)");
    lt_asegurarIndice($conexion, 'lineas_telefonicas', 'idx_lt_usuario', "INDEX idx_lt_usuario (usuario)");
    lt_asegurarIndice($conexion, 'lineas_telefonicas', 'idx_lt_fecha_entrega', "INDEX idx_lt_fecha_entrega (fecha_entrega)");
    lt_asegurarIndice($conexion, 'equipos_stock', 'idx_stk_estatus', "INDEX idx_stk_estatus (estatus)");
    lt_asegurarIndice($conexion, 'historial_lineas', 'idx_hist_id_linea', "INDEX idx_hist_id_linea (id_linea)");
    lt_asegurarIndice($conexion, 'historial_lineas', 'idx_hist_tipo_fecha', "INDEX idx_hist_tipo_fecha (tipo_accion, fecha_cambio)");

    /* NUEVO: unicidad real a nivel de base de datos para número/IMEI "activos"
       (no en Papelera), como respaldo de la validación que ya hace la app.
       La validación en PHP evita duplicados en el caso normal, pero dos
       guardados casi simultáneos pueden pasarla ambos (hay una ventana entre
       el SELECT de verificación y el INSERT). MySQL no soporta índices únicos
       parciales directamente, así que se usa el truco de una columna generada
       que vale NULL cuando el registro está eliminado (los NULL no chocan
       entre sí en un índice único) y el valor real solo cuando está activo. */
    lt_asegurarColumna(
        $conexion, 'lineas_telefonicas', 'numero_telefono_activo',
        "numero_telefono_activo VARCHAR(20) GENERATED ALWAYS AS (CASE WHEN (eliminado = 0 OR eliminado IS NULL) THEN numero_telefono ELSE NULL END) VIRTUAL"
    );
    lt_asegurarColumna(
        $conexion, 'lineas_telefonicas', 'imei_entregado_activo',
        "imei_entregado_activo VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN (eliminado = 0 OR eliminado IS NULL) AND imei_entregado <> '' THEN imei_entregado ELSE NULL END) VIRTUAL"
    );
    lt_asegurarColumna(
        $conexion, 'equipos_stock', 'imei_activo',
        "imei_activo VARCHAR(100) GENERATED ALWAYS AS (CASE WHEN (eliminado = 0 OR eliminado IS NULL) AND imei <> '' THEN imei ELSE NULL END) VIRTUAL"
    );
    lt_asegurarIndice($conexion, 'lineas_telefonicas', 'uq_lt_numero_activo', "UNIQUE INDEX uq_lt_numero_activo (numero_telefono_activo)");
    lt_asegurarIndice($conexion, 'lineas_telefonicas', 'uq_lt_imei_activo', "UNIQUE INDEX uq_lt_imei_activo (imei_entregado_activo)");
    lt_asegurarIndice($conexion, 'equipos_stock', 'uq_stk_imei_activo', "UNIQUE INDEX uq_stk_imei_activo (imei_activo)");

    $_SESSION['lt_schema_verificado'] = true;
}

/* =========================================================
   CSRF: token de sesión y validador
   ---------------------------------------------------------
   Antes, varias acciones que cambian datos (eliminar, restaurar,
   eliminar en definitiva, marcar disponible...) se disparaban con
   un simple link GET, sin ningún token: bastaba con mandarle a
   alguien la URL, o que un crawler la siguiera, para dispararlas.
   Ahora todas esas acciones viajan por POST y llevan este token.
   ========================================================= */
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
function lt_csrfToken(): string {
    return $_SESSION['csrf_token'] ?? '';
}
function lt_csrfValido(): bool {
    $enviado = $_POST['csrf_token'] ?? '';
    return is_string($enviado) && $enviado !== '' && hash_equals($_SESSION['csrf_token'] ?? '', $enviado);
}
/**
 * Envuelve una operación de varios pasos (UPDATE + INSERT + bitácora) en una
 * transacción real: si algo falla a medio camino, se revierte todo en vez de
 * dejar la base en un estado a medias (p. ej. equipo ya quitado de la línea
 * pero sin quedar registrado en stock).
 */
function lt_transaccion(mysqli $conexion, callable $fn) {
    $conexion->begin_transaction();
    try {
        $resultado = $fn();
        $conexion->commit();
        return $resultado;
    } catch (\Throwable $ex) {
        $conexion->rollback();
        throw $ex;
    }
}

/** Nombre del usuario del panel que está haciendo la acción (para la bitácora). */
function lt_usuarioSesion(): string {
    return trim($_SESSION['nombre_completo'] ?? $_SESSION['usuario'] ?? 'Sistema') ?: 'Sistema';
}

/**
 * Inserta un renglón en la bitácora de la línea. Centraliza el registro para
 * que ninguna acción (alta, edición, reasignación, baja, eliminación...)
 * quede sin rastro de qué cambió, cuándo y quién lo hizo.
 *
 * $imei es opcional (por defecto ''): se usa para dejar registrado, en una
 * columna aparte, el IMEI del equipo que quedó vigente en ese movimiento
 * (por ejemplo el IMEI actual en una reasignación, o el IMEI nuevo en una
 * sustitución de equipo), para poder reportarlo sin tener que leer notas.
 */
function lt_registrarHistorial(
    mysqli $conexion,
    int $id_linea,
    string $telefono,
    string $usr_ant,
    string $puesto_ant,
    string $usr_nuevo,
    string $puesto_nuevo,
    string $notas,
    string $tipo_accion,
    string $imei = ''
): void {
    $realizado_por = lt_usuarioSesion();
    $stmt = $conexion->prepare("INSERT INTO historial_lineas
        (id_linea, tipo_accion, numero_telefono, usuario_anterior, puesto_anterior, usuario_nuevo, puesto_nuevo, notas, realizado_por, imei)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param(
        "isssssssss",
        $id_linea, $tipo_accion, $telefono, $usr_ant, $puesto_ant, $usr_nuevo, $puesto_nuevo, $notas, $realizado_por, $imei
    );
    $stmt->execute();
}

/**
 * Catálogo único de tipos de acción de la bitácora -> [icono FontAwesome, etiqueta legible].
 * Centralizado aquí para que el timeline por línea, el reporte/bitácora general
 * y su exportación a Excel usen siempre las mismas etiquetas e iconos.
 */
function lt_catalogoAcciones(): array {
    return [
        'alta'             => ['fa-solid fa-circle-plus',         'Alta de línea'],
        'alta_csv'         => ['fa-solid fa-file-csv',            'Alta por carga masiva'],
        'reasignar'        => ['fa-solid fa-right-left',          'Reasignación'],
        'equipo_nuevo'     => ['fa-solid fa-mobile-screen-button','Sustitución de equipo'],
        'stock'            => ['fa-solid fa-boxes-stacked',       'Enviado a stock'],
        'stock_disponible' => ['fa-solid fa-rotate-left',         'Disponible en stock'],
        'stock_eliminado'  => ['fa-solid fa-dumpster',            'Movido a papelera (stock)'],
        'stock_restaurado' => ['fa-solid fa-trash-arrow-up',      'Restaurado de papelera (stock)'],
        'stock_eliminado_definitivo' => ['fa-solid fa-fire',      'Eliminado en definitiva (stock)'],
        'alta_stock_manual' => ['fa-solid fa-circle-plus',        'Alta manual en stock'],
        'resguardo'        => ['fa-solid fa-box-archive',         'En resguardo'],
        'baja'             => ['fa-solid fa-triangle-exclamation','Baja'],
        'edicion'          => ['fa-solid fa-pen-to-square',       'Edición manual'],
        'eliminacion'      => ['fa-solid fa-trash',                'Movido a papelera'],
        'restauracion'     => ['fa-solid fa-trash-arrow-up',       'Restaurado de papelera'],
        'eliminacion_definitiva' => ['fa-solid fa-fire',           'Eliminado en definitiva'],
        'otro'             => ['fa-solid fa-clock-rotate-left',   'Movimiento'],
    ];
}

/**
 * Pinta el timeline visual de la bitácora a partir de un resultado de
 * `historial_lineas` (o false/vacío si no hay nada). Centralizado para que
 * el historial por línea y el historial por equipo individual de Stock
 * (que se filtra distinto, por IMEI en vez de por id_linea) se vean igual.
 */
function lt_renderTimeline($q_hist): void {
    $catalogoAcciones = lt_catalogoAcciones();
    if ($q_hist && $q_hist->num_rows > 0) {
        echo '<div class="lt-timeline">';
        while ($h = $q_hist->fetch_assoc()) {
            $tipo = $h['tipo_accion'] ?? 'otro';
            if (!isset($catalogoAcciones[$tipo])) { $tipo = 'otro'; }
            [$icono, $etiqueta] = $catalogoAcciones[$tipo];

            $usrAnt = trim((string)($h['usuario_anterior'] ?? ''));
            $usrNvo = trim((string)($h['usuario_nuevo'] ?? ''));
            $mostrarFlujo = ($usrAnt !== '' && $usrNvo !== '' && $usrAnt !== 'N/A' && $usrAnt !== $usrNvo);

            echo '<div class="lt-timeline-item">';
            echo '  <div class="lt-timeline-dot lt-dot-' . htmlspecialchars($tipo) . '"><i class="' . htmlspecialchars($icono) . '"></i></div>';
            echo '  <div class="lt-timeline-card">';
            echo '    <div class="lt-timeline-top">';
            echo '      <span class="lt-badge-accion lt-dot-' . htmlspecialchars($tipo) . '">' . htmlspecialchars($etiqueta) . '</span>';
            echo '      <span class="lt-timeline-fecha"><i class="fa-regular fa-clock me-1"></i>' . date('d/m/Y H:i', strtotime($h['fecha_cambio'])) . '</span>';
            echo '    </div>';
            if ($mostrarFlujo) {
                echo '    <div class="lt-timeline-flujo">' . htmlspecialchars($usrAnt) . ' (' . htmlspecialchars($h['puesto_anterior'] ?: 'N/A') . ') <i class="fa-solid fa-arrow-right-long mx-1 text-muted"></i> <b>' . htmlspecialchars($usrNvo) . '</b> (' . htmlspecialchars($h['puesto_nuevo'] ?: 'N/A') . ')</div>';
            }
            if (!empty($h['notas'])) {
                echo '    <div class="lt-timeline-notas">' . nl2br(htmlspecialchars($h['notas'])) . '</div>';
            }
            echo '    <div class="lt-timeline-autor"><i class="fa-solid fa-user-shield"></i> ' . htmlspecialchars($h['realizado_por'] ?? 'Sistema') . '</div>';
            echo '  </div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning mb-0 text-center">Este equipo/línea aún no tiene registros en el historial.</div>';
    }
}

/**
 * Busca la línea más reciente asociada a un número telefónico, para poder
 * vincular al historial acciones que ocurren fuera del flujo normal de una
 * línea (por ejemplo, movimientos hechos directamente desde el Stock de
 * Equipos). Devuelve 0 si no encuentra ninguna línea con ese número.
 */
function lt_buscarIdLineaPorTelefono(mysqli $conexion, string $telefono): int {
    $telefono = trim($telefono);
    if ($telefono === '') return 0;
    $stmt = $conexion->prepare("SELECT id FROM lineas_telefonicas WHERE numero_telefono = ? ORDER BY id DESC LIMIT 1");
    $stmt->bind_param("s", $telefono);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return $row ? (int)$row['id'] : 0;
}

/**
 * Busca si ya existe otra línea ACTIVA (no eliminada, es decir que no está
 * en la Papelera) con el mismo valor en `numero_telefono` o `imei_entregado`.
 * Sirve para no dejar dar de alta / editar / sustituir equipo con un número
 * o un IMEI que ya está en uso en otro registro. Devuelve los datos del
 * registro con el que choca, o null si no hay conflicto.
 */
function lt_buscarDuplicado(mysqli $conexion, string $campo, string $valor, int $excluirId = 0): ?array {
    $valor = trim($valor);
    if ($valor === '' || !in_array($campo, ['numero_telefono', 'imei_entregado'], true)) {
        return null;
    }
    $stmt = $conexion->prepare("SELECT id, numero_telefono, usuario, estatus_linea FROM lineas_telefonicas
        WHERE $campo = ? AND (eliminado = 0 OR eliminado IS NULL) AND id <> ? LIMIT 1");
    $stmt->bind_param("si", $valor, $excluirId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
}

/**
 * Busca si ya existe un equipo (no eliminado) en `equipos_stock` con el
 * mismo IMEI. Sirve para no duplicar un equipo que ya está registrado en
 * el inventario (alta manual o edición del stock).
 */
function lt_buscarDuplicadoStock(mysqli $conexion, string $imei, int $excluirId = 0): ?array {
    $imei = trim($imei);
    if ($imei === '') return null;
    $stmt = $conexion->prepare("SELECT id, equipo, modelo, estatus FROM equipos_stock
        WHERE imei = ? AND (eliminado = 0 OR eliminado IS NULL) AND id <> ? LIMIT 1");
    $stmt->bind_param("si", $imei, $excluirId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    return $row ?: null;
}

/** Deja solo dígitos de un número telefónico. */
function lt_limpiarTelefono(string $v): string {
    return preg_replace('/\D/', '', $v);
}

/** Un teléfono válido tiene exactamente 10 dígitos (es obligatorio). */
function lt_validarTelefono(string $v): bool {
    return strlen(lt_limpiarTelefono($v)) === 10;
}

/** El IMEI es opcional, pero si viene debe ser numérico de 15 dígitos. */
function lt_validarImei(string $v): bool {
    $v = trim($v);
    if ($v === '') return true;
    return (bool)preg_match('/^\d{15}$/', $v);
}

/** Catálogo único de estatus válidos para una línea (evita valores basura). */
function lt_estatusValidos(): array {
    return ['Activa', 'En Resguardo', 'Inactiva', 'Suspendida', 'Baja'];
}

/**
 * Normaliza un estatus capturado a mano o por CSV contra el catálogo válido
 * (sin importar mayúsculas/acentos simples). Si no coincide con nada,
 * regresa null para que quien llama decida el valor por default y lo reporte.
 */
function lt_normalizarEstatus(string $v): ?string {
    $v = trim($v);
    if ($v === '') return null;
    $plano = strtr(mb_strtolower($v), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
    foreach (lt_estatusValidos() as $valido) {
        $validoPlano = strtr(mb_strtolower($valido), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
        if ($plano === $validoPlano) return $valido;
    }
    return null;
}

/* =========================================================
   0. EXPORTAR A EXCEL (TODOS LOS CAMPOS)
   ========================================================= */
if (isset($_GET['exportar_excel'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Lineas_Telefonicas_Completo_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF";
    // Nota: desde PHP 8.1, mysqli lanza una excepción (mysqli_sql_exception) ante
    // cualquier error de consulta en lugar de solo devolver false. Por eso envolvemos
    // las consultas en try/catch: así, si algo falla (tabla, columna o permiso), lo vemos
    // como texto claro en vez de descargar un archivo vacío/corrupto.
    // NUEVO: el Excel respeta los mismos filtros que la tabla en pantalla — antes
    // exportaba siempre TODO el catálogo sin importar lo que el usuario hubiera
    // filtrado/buscado.
    $condicionesExcel = ["(l.eliminado = 0 OR l.eliminado IS NULL)"];
    $paramsExcel = []; $tiposExcel = '';
    $bx = trim($_GET['buscar'] ?? '');
    $ex_estatus = trim($_GET['estatus_filtro'] ?? '');
    $ex_tipo = trim($_GET['tipo_emp_filtro'] ?? '');
    $ex_desde = trim($_GET['fecha_desde'] ?? '');
    $ex_hasta = trim($_GET['fecha_hasta'] ?? '');
    if ($bx !== '') {
        $likeBx = '%' . $bx . '%';
        $condicionesExcel[] = "(l.numero_telefono LIKE ? OR l.usuario LIKE ? OR l.puesto LIKE ? OR l.equipo LIKE ? OR l.modelo LIKE ? OR l.imei_entregado LIKE ? OR l.estatus_linea LIKE ? OR l.cuenta_google LIKE ?)";
        array_push($paramsExcel, $likeBx, $likeBx, $likeBx, $likeBx, $likeBx, $likeBx, $likeBx, $likeBx);
        $tiposExcel .= 'ssssssss';
    }
    if ($ex_estatus !== '') { $condicionesExcel[] = "l.estatus_linea = ?"; $paramsExcel[] = $ex_estatus; $tiposExcel .= 's'; }
    if ($ex_tipo !== '') { $condicionesExcel[] = "l.tipo_emp = ?"; $paramsExcel[] = $ex_tipo; $tiposExcel .= 's'; }
    if ($ex_desde !== '') { $condicionesExcel[] = "l.fecha_entrega >= ?"; $paramsExcel[] = $ex_desde; $tiposExcel .= 's'; }
    if ($ex_hasta !== '') { $condicionesExcel[] = "l.fecha_entrega <= ?"; $paramsExcel[] = $ex_hasta; $tiposExcel .= 's'; }
    $whereExcel = 'WHERE ' . implode(' AND ', $condicionesExcel);
    try {
        $stmtExcel = $conexion->prepare("SELECT l.*,
            (SELECT h.usuario_anterior FROM historial_lineas h WHERE h.id_linea = l.id AND h.usuario_anterior <> h.usuario_nuevo ORDER BY h.fecha_cambio DESC LIMIT 1) AS usuario_anterior_hist
            FROM lineas_telefonicas l $whereExcel ORDER BY l.id DESC");
        if ($tiposExcel !== '') {
            $stmtExcel->bind_param($tiposExcel, ...$paramsExcel);
        }
        if (!$stmtExcel->execute()) {
            throw new Exception($conexion->error);
        }
        $q_excel = $stmtExcel->get_result();
    } catch (\Throwable $ex) {
        echo "Error al generar el reporte: " . htmlspecialchars($ex->getMessage());
        exit();
    }
    ?>
    <table border="1">
        <thead>
            <tr style="background-color: #E26B00; color: #FFFFFF; font-weight: bold;">
                <th>ID</th>
                <th>Número Teléfono</th>
                <th>Tipo EMP</th>
                <th>Estatus Línea</th>
                <th>Usuario / Resguardante</th>
                <th>Puesto</th>
                <th>Usuario Anterior Registrado</th>
                <th>Fecha Entrega</th>
                <th>Equipo</th>
                <th>Modelo</th>
                <th>Cuenta Google</th>
                <th>EQ Nuevo</th>
                <th>Resguardo</th>
                <th>IMEI Entregado</th>
                <th>Costo</th>
                <th>Comentarios Entrega</th>
                <th>Comentarios Extras</th>
                <th>Motivo Baja</th>
                <th>Fecha Baja</th>
            </tr>
        </thead>
        <tbody>
            <?php while($e = $q_excel->fetch_assoc()): ?>
                <tr>
                    <td><?= $e['id'] ?></td>
                    <td style="vnd.ms-excel.numberformat:@">'<?= htmlspecialchars($e['numero_telefono'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['tipo_emp'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['estatus_linea'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['usuario'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['puesto'] ?? '') ?></td>
                    <td><?= !empty($e['usuario_anterior_hist']) ? htmlspecialchars($e['usuario_anterior_hist']) : 'Sin historial' ?></td>
                    <td><?= $e['fecha_entrega'] ?></td>
                    <td><?= htmlspecialchars($e['equipo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['modelo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['cuenta_google'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['eq_nuevo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['resguardo'] ?? '') ?></td>
                    <td style="vnd.ms-excel.numberformat:@">'<?= htmlspecialchars($e['imei_entregado'] ?? '') ?></td>
                    <td style="vnd.ms-excel.numberformat:$#,##0.00"><?= floatval($e['costo'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($e['comentarios_entrega'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['comentarios_extras'] ?? '') ?></td>
                    <td><?= htmlspecialchars($e['motivo_baja'] ?? '') ?></td>
                    <td><?= $e['fecha_baja'] ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php
    exit();
}
/* =========================================================
   0b. EXPORTAR STOCK DE EQUIPOS A EXCEL (ARCHIVO APARTE)
   ========================================================= */
if (isset($_GET['exportar_stock_excel'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Stock_Equipos_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF";
    try {
        // NUEVO: en vez de depender de la bitácora, se busca directo en
        // lineas_telefonicas (la tabla donde se registran las entregas) por
        // IMEI: así se obtiene el número de línea y el usuario ACTUALES de
        // quien tiene ese equipo hoy, aunque haya quedado en una línea/número
        // distinto al que tenía asociado antes de entrar a stock. Funciona
        // de inmediato para todo lo ya asignado, no solo hacia adelante.
        $q_stock_excel = $conexion->query("SELECT s.*,
            la.numero_telefono AS numero_actual,
            la.usuario AS usuario_actual,
            la.puesto AS puesto_actual
            FROM equipos_stock s
            LEFT JOIN lineas_telefonicas la
                ON CONVERT(la.imei_entregado USING utf8mb4) COLLATE utf8mb4_general_ci
                   = CONVERT(s.imei USING utf8mb4) COLLATE utf8mb4_general_ci
                AND s.imei <> ''
                AND (la.eliminado = 0 OR la.eliminado IS NULL)
            ORDER BY s.fecha_ingreso DESC");
        if (!$q_stock_excel) {
            throw new Exception($conexion->error);
        }
    } catch (\Throwable $ex) {
        echo "Error al generar el reporte: " . htmlspecialchars($ex->getMessage());
        exit();
    }
    ?>
    <table border="1">
        <thead>
            <tr style="background-color: #0f172a; color: #FFFFFF; font-weight: bold;">
                <th>ID</th>
                <th>Equipo</th>
                <th>Modelo</th>
                <th>IMEI</th>
                <th>Costo</th>
                <th>Estatus</th>
                <th>Usuario Anterior / Origen</th>
                <th>Teléfono Anterior (Origen)</th>
                <th>Número Actual (Línea Asignada)</th>
                <th>Usuario Nuevo (Asignado Actualmente)</th>
                <th>Puesto Nuevo</th>
                <th>Fecha Ingreso a Stock</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            <?php while($s = $q_stock_excel->fetch_assoc()): ?>
                <tr>
                    <td><?= $s['id'] ?></td>
                    <td><?= htmlspecialchars($s['equipo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($s['modelo'] ?? '') ?></td>
                    <td style="vnd.ms-excel.numberformat:@">'<?= htmlspecialchars($s['imei'] ?? '') ?></td>
                    <td style="vnd.ms-excel.numberformat:$#,##0.00"><?= floatval($s['costo'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($s['estatus'] ?? '') ?></td>
                    <td><?= htmlspecialchars($s['origen_usuario'] ?? '') ?></td>
                    <td style="vnd.ms-excel.numberformat:@">'<?= htmlspecialchars($s['numero_telefono'] ?? '') ?></td>
                    <td style="vnd.ms-excel.numberformat:@">'<?= htmlspecialchars($s['numero_actual'] ?? '') ?></td>
                    <td><?= !empty($s['usuario_actual']) ? htmlspecialchars($s['usuario_actual']) : ($s['estatus'] === 'Disponible' ? '' : 'N/A') ?></td>
                    <td><?= htmlspecialchars($s['puesto_actual'] ?? '') ?></td>
                    <td><?= $s['fecha_ingreso'] ?></td>
                    <td><?= htmlspecialchars($s['notas'] ?? '') ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php
    exit();
}
/* =========================================================
   0c. EXPORTAR BITÁCORA / REPORTE DE MOVIMIENTOS A EXCEL
   ---------------------------------------------------------
   Respeta los mismos filtros (tipo, búsqueda, fechas) que la
   vista del modal "Bitácora General de Movimientos". Al no
   depender de que la línea siga existiendo, también sirve como
   reporte de líneas ya eliminadas.
   ========================================================= */
if (isset($_GET['exportar_reporte_excel'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Reporte_Movimientos_Lineas_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF";

    $rt = trim($_GET['rep_tipo'] ?? '');
    $rb = trim($_GET['rep_buscar'] ?? '');
    $rd = trim($_GET['rep_desde'] ?? '');
    $rh = trim($_GET['rep_hasta'] ?? '');
    $condicionesRepX = []; $paramsRepX = []; $tiposRepX = '';
    if ($rt !== '') { $condicionesRepX[] = "tipo_accion = ?"; $paramsRepX[] = $rt; $tiposRepX .= 's'; }
    if ($rb !== '') {
        $likeRepX = '%' . $rb . '%';
        $condicionesRepX[] = "(numero_telefono LIKE ? OR usuario_anterior LIKE ? OR usuario_nuevo LIKE ? OR realizado_por LIKE ? OR notas LIKE ?)";
        array_push($paramsRepX, $likeRepX, $likeRepX, $likeRepX, $likeRepX, $likeRepX);
        $tiposRepX .= 'sssss';
    }
    if ($rd !== '') { $condicionesRepX[] = "fecha_cambio >= ?"; $paramsRepX[] = $rd . ' 00:00:00'; $tiposRepX .= 's'; }
    if ($rh !== '') { $condicionesRepX[] = "fecha_cambio <= ?"; $paramsRepX[] = $rh . ' 23:59:59'; $tiposRepX .= 's'; }
    $whereRepX = $condicionesRepX ? ('WHERE ' . implode(' AND ', $condicionesRepX)) : '';

    try {
        $stmtRepX = $conexion->prepare("SELECT * FROM historial_lineas $whereRepX ORDER BY fecha_cambio DESC, id DESC");
        if ($tiposRepX !== '') {
            $stmtRepX->bind_param($tiposRepX, ...$paramsRepX);
        }
        if (!$stmtRepX->execute()) {
            throw new Exception($conexion->error);
        }
        $q_rep_excel = $stmtRepX->get_result();
    } catch (\Throwable $ex) {
        echo "Error al generar el reporte: " . htmlspecialchars($ex->getMessage());
        exit();
    }
    $catalogoRepX = lt_catalogoAcciones();
    ?>
    <table border="1">
        <thead>
            <tr style="background-color: #1e3a8a; color: #FFFFFF; font-weight: bold;">
                <th>Fecha</th>
                <th>Teléfono</th>
                <th>Tipo de Movimiento</th>
                <th>Usuario Anterior</th>
                <th>Puesto Anterior</th>
                <th>Usuario Nuevo</th>
                <th>Puesto Nuevo</th>
                <th>Realizado Por</th>
                <th>Notas</th>
            </tr>
        </thead>
        <tbody>
            <?php while($r = $q_rep_excel->fetch_assoc()): ?>
                <tr>
                    <td><?= $r['fecha_cambio'] ?></td>
                    <td style="vnd.ms-excel.numberformat:@">'<?= htmlspecialchars($r['numero_telefono'] ?? '') ?></td>
                    <td><?= htmlspecialchars($catalogoRepX[$r['tipo_accion']][1] ?? $r['tipo_accion']) ?></td>
                    <td><?= htmlspecialchars($r['usuario_anterior'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['puesto_anterior'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['usuario_nuevo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['puesto_nuevo'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['realizado_por'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['notas'] ?? '') ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php
    exit();
}
/* =========================================================
   0d. EXPORTAR REPORTE DE ENTREGAS / REASIGNACIONES A EXCEL
   ---------------------------------------------------------
   NUEVO. Reporte acotado (a diferencia del de arriba, que trae
   TODOS los tipos de movimiento) solo a los movimientos donde
   cambia quién tiene el equipo: reasignaciones y sustituciones
   de equipo. Columnas: fecha, número, responsable anterior,
   nuevo responsable e IMEI — justo lo que se pidió reportar.
   ========================================================= */
if (isset($_GET['exportar_entregas_excel'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=Reporte_Entregas_Reasignaciones_" . date('Y-m-d') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF";

    $ed = trim($_GET['ent_desde'] ?? '');
    $eh = trim($_GET['ent_hasta'] ?? '');
    $eb = trim($_GET['ent_buscar'] ?? '');
    $condEntX = ["tipo_accion IN ('reasignar','equipo_nuevo')"];
    $paramsEntX = []; $tiposEntX = '';
    if ($eb !== '') {
        $likeEntX = '%' . $eb . '%';
        $condEntX[] = "(numero_telefono LIKE ? OR usuario_anterior LIKE ? OR usuario_nuevo LIKE ? OR imei LIKE ?)";
        array_push($paramsEntX, $likeEntX, $likeEntX, $likeEntX, $likeEntX);
        $tiposEntX .= 'ssss';
    }
    if ($ed !== '') { $condEntX[] = "fecha_cambio >= ?"; $paramsEntX[] = $ed . ' 00:00:00'; $tiposEntX .= 's'; }
    if ($eh !== '') { $condEntX[] = "fecha_cambio <= ?"; $paramsEntX[] = $eh . ' 23:59:59'; $tiposEntX .= 's'; }
    $whereEntX = 'WHERE ' . implode(' AND ', $condEntX);

    try {
        $stmtEntX = $conexion->prepare("SELECT * FROM historial_lineas $whereEntX ORDER BY fecha_cambio DESC, id DESC");
        if ($tiposEntX !== '') {
            $stmtEntX->bind_param($tiposEntX, ...$paramsEntX);
        }
        if (!$stmtEntX->execute()) {
            throw new Exception($conexion->error);
        }
        $q_ent_excel = $stmtEntX->get_result();
    } catch (\Throwable $ex) {
        echo "Error al generar el reporte: " . htmlspecialchars($ex->getMessage());
        exit();
    }
    $catalogoEntX = lt_catalogoAcciones();
    ?>
    <table border="1">
        <thead>
            <tr style="background-color: #16a34a; color: #FFFFFF; font-weight: bold;">
                <th>Fecha</th>
                <th>Número</th>
                <th>Movimiento</th>
                <th>Usuario Anterior</th>
                <th>Nuevo Responsable</th>
                <th>IMEI</th>
                <th>Realizado Por</th>
            </tr>
        </thead>
        <tbody>
            <?php while($r = $q_ent_excel->fetch_assoc()): ?>
                <tr>
                    <td><?= $r['fecha_cambio'] ?></td>
                    <td style="vnd.ms-excel.numberformat:@">'<?= htmlspecialchars($r['numero_telefono'] ?? '') ?></td>
                    <td><?= htmlspecialchars($catalogoEntX[$r['tipo_accion']][1] ?? $r['tipo_accion']) ?></td>
                    <td><?= htmlspecialchars($r['usuario_anterior'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['usuario_nuevo'] ?? '') ?></td>
                    <td style="vnd.ms-excel.numberformat:@">'<?= htmlspecialchars($r['imei'] ?? '') ?></td>
                    <td><?= htmlspecialchars($r['realizado_por'] ?? '') ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    <?php
    exit();
}
/* =========================================================
   000. OBTENER HISTORIAL (PETICIÓN AJAX / FETCH)
   ========================================================= */
if (isset($_GET['obtener_historial'])) {
    while (ob_get_level()) { ob_end_clean(); }
    $id_hist = intval($_GET['obtener_historial']);
    $q_hist  = $conexion->query("SELECT * FROM historial_lineas WHERE id_linea = $id_hist ORDER BY fecha_cambio DESC, id DESC");
    lt_renderTimeline($q_hist);
    exit();
}
/* =========================================================
   000b. OBTENER HISTORIAL DE UN EQUIPO INDIVIDUAL DEL STOCK (AJAX)
   ---------------------------------------------------------
   Un equipo de Stock no siempre está ligado a una línea activa (id_linea),
   así que aquí se busca por su IMEI dentro del texto de las notas: todas
   las acciones que tocan ese equipo (sustitución, envío a stock, marcado
   como disponible, eliminado del stock...) siempre mencionan su IMEI.
   ========================================================= */
if (isset($_GET['obtener_historial_stock'])) {
    while (ob_get_level()) { ob_end_clean(); }
    $id_stk_hist = intval($_GET['obtener_historial_stock']);
    $q_stk_h = $conexion->query("SELECT * FROM equipos_stock WHERE id = $id_stk_hist");
    $stk_h = $q_stk_h ? $q_stk_h->fetch_assoc() : null;
    $imei_h = trim($stk_h['imei'] ?? '');
    $q_hist_stk = false;
    if ($imei_h !== '') {
        // CORREGIDO: se agregó la columna dedicada `imei` en historial_lineas
        // precisamente para no depender de buscar el IMEI como texto libre
        // dentro de `notas` (coincidencias parciales, un IMEI que es substring
        // de otro, etc.). Ahora se filtra por esa columna con coincidencia
        // exacta; se deja el LIKE sobre notas solo como respaldo para
        // movimientos viejos registrados antes de que existiera la columna.
        $stmt_h = $conexion->prepare("SELECT * FROM historial_lineas WHERE imei = ? OR (imei IS NULL AND notas LIKE ?) ORDER BY fecha_cambio DESC, id DESC");
        $likeImeiH = '%' . $imei_h . '%';
        $stmt_h->bind_param("ss", $imei_h, $likeImeiH);
        $stmt_h->execute();
        $q_hist_stk = $stmt_h->get_result();
    }
    if ($imei_h === '') {
        echo '<div class="alert alert-warning mb-0 text-center">Este equipo no tiene IMEI registrado, así que no se puede rastrear su historial individual.</div>';
    } else {
        lt_renderTimeline($q_hist_stk);
    }
    exit();
}
include ($_SERVER['DOCUMENT_ROOT'] . '/admin/menu.php');
echo '<link rel="stylesheet" href="' . BASE_URL . 'css/lineas.css?v=' . time() . '">';
$msg = '';
/* =========================================================
   FUNCIÓN PARSEADORA DE FECHAS (PHP)
   ========================================================= */
function convertirFechaParaBDD($fechaRaw) {
    if (empty($fechaRaw)) return null;
    $f = mb_strtolower(trim($fechaRaw), 'UTF-8');
    $meses = [
        'enero' => '01', 'ene' => '01',
        'febrero' => '02', 'feb' => '02',
        'marzo' => '03', 'mar' => '03',
        'abril' => '04', 'abr' => '04',
        'mayo' => '05', 'may' => '05',
        'junio' => '06', 'jun' => '06',
        'julio' => '07', 'jul' => '07',
        'agosto' => '08', 'ago' => '08',
        'septiembre' => '09', 'septimbre' => '09', 'setiembre' => '09', 'sep' => '09',
        'octubre' => '10', 'oct' => '10',
        'noviembre' => '11', 'nov' => '11',
        'diciembre' => '12', 'dic' => '12'
    ];
    if (preg_match('/(\d{1,2})\s+(?:de\s+)?([a-z]+)(?:\s+(?:de|del)?\s*(\d{2,4}))?/u', $f, $m)) {
        $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mesStr = $m[2];
        $anio = !empty($m[3]) ? $m[3] : date('Y');
        if (isset($meses[$mesStr])) {
            $mes = $meses[$mesStr];
            if (strlen($anio) == 2) {
                $anio = '20' . $anio;
            }
            return "$anio-$mes-$dia";
        }
    }
    if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $f, $m)) {
        $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mes = str_pad($m[2], 2, '0', STR_PAD_LEFT);
        $anio = $m[3];
        if (strlen($anio) == 2) {
            $anio = '20' . $anio;
        }
        return "$anio-$mes-$dia";
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f)) {
        return $f;
    }
    $timestamp = strtotime($fechaRaw);
    return $timestamp ? date('Y-m-d', $timestamp) : null;
}
/* =========================================================
   00. DESCARGAR PLANTILLA CSV
   ========================================================= */
if (isset($_GET['descargar_plantilla'])) {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="Plantilla_Lineas_Telefonicas.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, [
        'numero_telefono', 'tipo_emp', 'estatus_linea', 'usuario', 'puesto',
        'fecha_entrega', 'equipo', 'modelo', 'cuenta_google', 'eq_nuevo',
        'resguardo', 'imei_entregado', 'costo', 'comentarios_entrega', 'comentarios_extras'
    ]);
    fputcsv($output, [
        '4491234567', 'A', 'ACTIVA', 'JUAN PEREZ', 'SUPERVISOR',
        '06 DE JULIO 2026', 'SAMSUNG', 'GALAXY A14', 'juan@empresa.com', 'SI',
        'SI', '864209041234567', '4500.00', 'EQUIPO COMPLETO CON CARGADOR', 'NINGUNO'
    ]);
    fclose($output);
    exit();
}
/* =========================================================
   1. CARGA MASIVA DESDE CSV
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar_csv'])) {
    if (!lt_csrfValido()) {
        $msg = "⚠️ No se importó: la sesión del formulario expiró. Recarga la página e intenta de nuevo.";
    } elseif (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] == 0) {
        $filename = $_FILES['archivo_csv']['tmp_name'];
        $handle = fopen($filename, "r");
        fgetcsv($handle, 1000, ",");
        $importados = 0;
        $duplicadosCsv = [];
        $invalidosCsv = [];
        $normalizadosCsv = 0;
        while (($datos = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty(array_filter($datos))) continue;
            $numero_telefono    = lt_limpiarTelefono(trim($datos[0] ?? ''));
            $tipo_emp           = trim($datos[1] ?? '');
            $estatus_linea_raw  = trim($datos[2] ?? 'Activa');
            $usuario            = trim($datos[3] ?? '');
            $puesto             = trim($datos[4] ?? '');
            $fecha_raw          = trim($datos[5] ?? '');
            $equipo             = trim($datos[6] ?? '');
            $modelo             = trim($datos[7] ?? '');
            $cuenta_google      = trim($datos[8] ?? '');
            $eq_nuevo           = trim($datos[9] ?? 'NO');
            $resguardo          = trim($datos[10] ?? '');
            $imei_entregado     = trim($datos[11] ?? '');
            $costo              = max(0, floatval($datos[12] ?? 0));
            $comentarios_entrega = trim($datos[13] ?? '');
            $comentarios_extras  = trim($datos[14] ?? '');
            $fecha_final = convertirFechaParaBDD($fecha_raw);

            // Normaliza el estatus contra el catálogo válido; si no coincide
            // con nada se usa "Activa" por default y se avisa al final.
            $estatus_normalizado = lt_normalizarEstatus($estatus_linea_raw);
            if ($estatus_normalizado === null) {
                $estatus_linea = 'Activa';
                if ($estatus_linea_raw !== '') $normalizadosCsv++;
            } else {
                $estatus_linea = $estatus_normalizado;
            }

            if (!empty($numero_telefono)) {
                // No se importa una fila con teléfono/IMEI en formato inválido
                // (evita basura en la base por errores de captura del CSV).
                if (!lt_validarTelefono($numero_telefono)) {
                    $invalidosCsv[] = $numero_telefono . ' (teléfono inválido, deben ser 10 dígitos)';
                    continue;
                }
                if (!lt_validarImei($imei_entregado)) {
                    $invalidosCsv[] = $numero_telefono . ' (IMEI inválido, deben ser 15 dígitos)';
                    continue;
                }
                // No se importa una fila si su número o su IMEI ya están en
                // uso en otra línea activa (evita duplicados por error de captura).
                $dupTelCsv  = lt_buscarDuplicado($conexion, 'numero_telefono', $numero_telefono);
                $dupImeiCsv = !empty($imei_entregado) ? lt_buscarDuplicado($conexion, 'imei_entregado', $imei_entregado) : null;
                if ($dupTelCsv || $dupImeiCsv) {
                    $duplicadosCsv[] = $numero_telefono . ($dupTelCsv ? ' (número duplicado)' : ' (IMEI duplicado)');
                    continue;
                }
                $conexion->begin_transaction();
                try {
                    $stmt = $conexion->prepare("INSERT INTO lineas_telefonicas
                        (numero_telefono, tipo_emp, estatus_linea, usuario, puesto, fecha_entrega, equipo, modelo, cuenta_google, eq_nuevo, resguardo, imei_entregado, costo, comentarios_entrega, comentarios_extras)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                    $stmt->bind_param("ssssssssssssdss",
                        $numero_telefono, $tipo_emp, $estatus_linea, $usuario, $puesto, $fecha_final,
                        $equipo, $modelo, $cuenta_google, $eq_nuevo, $resguardo, $imei_entregado,
                        $costo, $comentarios_entrega, $comentarios_extras
                    );
                    if ($stmt->execute()) {
                        $importados++;
                        lt_registrarHistorial($conexion, $conexion->insert_id, $numero_telefono, 'N/A', 'N/A', $usuario ?: 'N/A', $puesto ?: 'N/A', 'Alta por carga masiva (CSV).', 'alta_csv', $imei_entregado);
                    }
                    $conexion->commit();
                } catch (\Throwable $exCsv) {
                    $conexion->rollback();
                    $invalidosCsv[] = $numero_telefono . ' (error al guardar: ' . $exCsv->getMessage() . ')';
                }
            }
        }
        fclose($handle);
        $msg = "✅ Carga masiva completada: Se importaron " . htmlspecialchars((string)$importados) . " registros correctamente.";
        if (!empty($invalidosCsv)) {
            $listaInv = htmlspecialchars(implode(', ', array_slice($invalidosCsv, 0, 10)) . (count($invalidosCsv) > 10 ? '…' : ''));
            $msg .= " ❌ Se omitieron " . count($invalidosCsv) . " fila(s) por teléfono/IMEI con formato inválido: {$listaInv}.";
        }
        if (!empty($duplicadosCsv)) {
            $listaDup = htmlspecialchars(implode(', ', array_slice($duplicadosCsv, 0, 10)) . (count($duplicadosCsv) > 10 ? '…' : ''));
            $msg .= " ⚠️ Se omitieron " . count($duplicadosCsv) . " fila(s) por número/IMEI duplicado: {$listaDup}.";
        }
        if ($normalizadosCsv > 0) {
            $msg .= " ℹ️ " . (int)$normalizadosCsv . " fila(s) traían un estatus no reconocido y se guardaron como \"Activa\" por default.";
        }
    } else {
        $msg = "❌ Error al subir el archivo al servidor.";
    }
}
/* =========================================================
   3. ACCIONES RÁPIDAS DE PAPELERA / STOCK (POST + CSRF)
   ---------------------------------------------------------
   Antes cada una de estas era un link GET independiente
   (?eliminar=, ?restaurar=, ?eliminar_definitivo=, ?eliminar_stock=,
   ?restaurar_stock=, ?eliminar_stock_definitivo=,
   ?marcar_disponible_stock=). Se unifican aquí en un solo handler
   por POST, con validación de CSRF, para que no se puedan disparar
   con solo visitar una URL.
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_rapida'])) {
    if (!lt_csrfValido()) {
        $msg = "⚠️ No se pudo procesar la acción: la sesión del formulario expiró. Recarga la página e intenta de nuevo.";
    } else {
        $accionRapida = trim($_POST['accion_rapida']);
        $idRapida = intval($_POST['id_rapida'] ?? 0);

        if ($accionRapida === 'eliminar' && $idRapida > 0) {
            $q_del = $conexion->query("SELECT numero_telefono, usuario, puesto FROM lineas_telefonicas WHERE id = " . $idRapida);
            $datos_del = $q_del ? $q_del->fetch_assoc() : null;
            if ($datos_del) {
                lt_registrarHistorial(
                    $conexion, $idRapida, $datos_del['numero_telefono'] ?? '',
                    $datos_del['usuario'] ?: 'N/A', $datos_del['puesto'] ?: 'N/A',
                    'N/A', 'N/A', 'Registro movido a la Papelera. Se puede restaurar desde ahí si fue un error.', 'eliminacion'
                );
                // Baja lógica: ya no se borra de la base. Se manda a la Papelera
                // (eliminado = 1) para poder restaurarlo si hizo falta por error.
                $conexion->query("UPDATE lineas_telefonicas SET eliminado = 1, fecha_eliminado = NOW() WHERE id = " . $idRapida);
                $msg = "🗑️ Registro movido a la Papelera. Puedes restaurarlo desde ahí si fue un error.";
            }
        } elseif ($accionRapida === 'restaurar' && $idRapida > 0) {
            $q_r = $conexion->query("SELECT numero_telefono, usuario, puesto FROM lineas_telefonicas WHERE id = " . $idRapida . " AND eliminado = 1");
            $datos_r = $q_r ? $q_r->fetch_assoc() : null;
            if ($datos_r) {
                $conexion->query("UPDATE lineas_telefonicas SET eliminado = 0, fecha_eliminado = NULL WHERE id = " . $idRapida);
                lt_registrarHistorial(
                    $conexion, $idRapida, $datos_r['numero_telefono'] ?? '',
                    'N/A', 'N/A', $datos_r['usuario'] ?: 'N/A', $datos_r['puesto'] ?: 'N/A',
                    'Registro restaurado desde la Papelera.', 'restauracion'
                );
                $msg = "♻️ Registro restaurado correctamente. Ya aparece de nuevo en la tabla.";
            }
        } elseif ($accionRapida === 'eliminar_definitivo' && $idRapida > 0) {
            $q_pd = $conexion->query("SELECT numero_telefono, usuario, puesto FROM lineas_telefonicas WHERE id = " . $idRapida . " AND eliminado = 1");
            $datos_pd = $q_pd ? $q_pd->fetch_assoc() : null;
            if ($datos_pd) {
                lt_registrarHistorial(
                    $conexion, $idRapida, $datos_pd['numero_telefono'] ?? '',
                    $datos_pd['usuario'] ?: 'N/A', $datos_pd['puesto'] ?: 'N/A',
                    'N/A', 'N/A', 'Registro eliminado EN DEFINITIVA desde la Papelera (ya no se puede restaurar).', 'eliminacion_definitiva'
                );
                $conexion->query("DELETE FROM lineas_telefonicas WHERE id = " . $idRapida);
                $msg = "🔥 Registro eliminado en definitiva. Esto ya no se puede deshacer.";
            }
        } elseif ($accionRapida === 'eliminar_stock' && $idRapida > 0) {
            $q_stk_del = $conexion->query("SELECT * FROM equipos_stock WHERE id = " . $idRapida . " AND (eliminado = 0 OR eliminado IS NULL)");
            $stk_del = $q_stk_del ? $q_stk_del->fetch_assoc() : null;
            if ($stk_del) {
                $tel_stk_del  = trim($stk_del['numero_telefono'] ?? '');
                $origen_stk   = trim($stk_del['origen_usuario'] ?? '') ?: 'N/A';
                $id_linea_rel = lt_buscarIdLineaPorTelefono($conexion, $tel_stk_del);
                $nota_stk = "Equipo movido a la Papelera de Stock: {$stk_del['equipo']} {$stk_del['modelo']} (IMEI: {$stk_del['imei']}). Estatus previo en stock: {$stk_del['estatus']}.";
                lt_registrarHistorial($conexion, $id_linea_rel, $tel_stk_del, $origen_stk, 'N/A', $origen_stk, 'N/A', $nota_stk, 'stock_eliminado', $stk_del['imei'] ?? '');
                $conexion->query("UPDATE equipos_stock SET eliminado = 1, fecha_eliminado = NOW() WHERE id = " . $idRapida);
                $msg = "🗑️ Equipo movido a la Papelera de Stock. Puedes restaurarlo desde ahí si fue un error.";
            }
        } elseif ($accionRapida === 'restaurar_stock' && $idRapida > 0) {
            $q_stk_r = $conexion->query("SELECT * FROM equipos_stock WHERE id = " . $idRapida . " AND eliminado = 1");
            $stk_r = $q_stk_r ? $q_stk_r->fetch_assoc() : null;
            if ($stk_r) {
                $tel_stk_r  = trim($stk_r['numero_telefono'] ?? '');
                $origen_r   = trim($stk_r['origen_usuario'] ?? '') ?: 'N/A';
                $id_linea_rel = lt_buscarIdLineaPorTelefono($conexion, $tel_stk_r);
                $conexion->query("UPDATE equipos_stock SET eliminado = 0, fecha_eliminado = NULL WHERE id = " . $idRapida);
                $nota_stk = "Equipo restaurado desde la Papelera de Stock: {$stk_r['equipo']} {$stk_r['modelo']} (IMEI: {$stk_r['imei']}).";
                lt_registrarHistorial($conexion, $id_linea_rel, $tel_stk_r, $origen_r, 'N/A', $origen_r, 'N/A', $nota_stk, 'stock_restaurado', $stk_r['imei'] ?? '');
                $msg = "♻️ Equipo restaurado correctamente. Ya aparece de nuevo en el Stock.";
            }
        } elseif ($accionRapida === 'eliminar_stock_definitivo' && $idRapida > 0) {
            $q_stk_pd = $conexion->query("SELECT * FROM equipos_stock WHERE id = " . $idRapida . " AND eliminado = 1");
            $stk_pd = $q_stk_pd ? $q_stk_pd->fetch_assoc() : null;
            if ($stk_pd) {
                $tel_stk_pd  = trim($stk_pd['numero_telefono'] ?? '');
                $origen_pd   = trim($stk_pd['origen_usuario'] ?? '') ?: 'N/A';
                $id_linea_rel = lt_buscarIdLineaPorTelefono($conexion, $tel_stk_pd);
                $nota_stk = "Equipo eliminado EN DEFINITIVA desde la Papelera de Stock (ya no se puede restaurar): {$stk_pd['equipo']} {$stk_pd['modelo']} (IMEI: {$stk_pd['imei']}).";
                lt_registrarHistorial($conexion, $id_linea_rel, $tel_stk_pd, $origen_pd, 'N/A', $origen_pd, 'N/A', $nota_stk, 'stock_eliminado_definitivo', $stk_pd['imei'] ?? '');
                $conexion->query("DELETE FROM equipos_stock WHERE id = " . $idRapida);
                $msg = "🔥 Equipo eliminado en definitiva del stock. Esto ya no se puede deshacer.";
            }
        } elseif ($accionRapida === 'marcar_disponible_stock' && $idRapida > 0) {
            $q_stk_disp = $conexion->query("SELECT * FROM equipos_stock WHERE id = " . $idRapida);
            $stk_disp = $q_stk_disp ? $q_stk_disp->fetch_assoc() : null;
            $conexion->query("UPDATE equipos_stock SET estatus = 'Disponible' WHERE id = " . $idRapida);
            if ($stk_disp) {
                $tel_stk_disp = trim($stk_disp['numero_telefono'] ?? '');
                $origen_disp  = trim($stk_disp['origen_usuario'] ?? '') ?: 'N/A';
                $id_linea_rel = lt_buscarIdLineaPorTelefono($conexion, $tel_stk_disp);
                $nota_stk = "Equipo marcado como Disponible en stock: {$stk_disp['equipo']} {$stk_disp['modelo']} (IMEI: {$stk_disp['imei']}). Estatus previo en stock: {$stk_disp['estatus']}.";
                lt_registrarHistorial($conexion, $id_linea_rel, $tel_stk_disp, $origen_disp, 'N/A', $origen_disp, 'N/A', $nota_stk, 'stock_disponible', $stk_disp['imei'] ?? '');
            }
            $msg = "🟢 Equipo marcado como Disponible en el stock.";
        } elseif ($accionRapida === 'reactivar_linea' && $idRapida > 0) {
            // NUEVO: reactivar una línea que estaba en estatus "Baja" sin
            // tener que editarla a mano campo por campo.
            $q_react = $conexion->query("SELECT numero_telefono, usuario, puesto FROM lineas_telefonicas WHERE id = " . $idRapida);
            $datos_react = $q_react ? $q_react->fetch_assoc() : null;
            if ($datos_react) {
                $conexion->query("UPDATE lineas_telefonicas SET estatus_linea = 'Activa', motivo_baja = NULL, fecha_baja = NULL WHERE id = " . $idRapida);
                lt_registrarHistorial(
                    $conexion, $idRapida, $datos_react['numero_telefono'] ?? '',
                    $datos_react['usuario'] ?: 'N/A', $datos_react['puesto'] ?: 'N/A',
                    $datos_react['usuario'] ?: 'N/A', $datos_react['puesto'] ?: 'N/A',
                    'Línea reactivada manualmente (estaba dada de Baja).', 'edicion'
                );
                $msg = "✅ Línea reactivada. Su estatus volvió a Activa.";
            }
        }
    }
}
/* =========================================================
   3c. ALTA MANUAL DE EQUIPO EN STOCK (SIN PASAR POR UNA LÍNEA)
   ---------------------------------------------------------
   Antes la única forma de que un equipo llegara a `equipos_stock` era
   como efecto secundario de una sustitución, un resguardo o una baja de
   línea. Si se compran equipos de repuesto que aún no se asignan a
   nadie, no había forma de registrarlos. Este bloque la agrega.
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_stock_manual'])) {
    if (!lt_csrfValido()) {
        $msg = "⚠️ No se guardó: la sesión del formulario expiró. Recarga la página e intenta de nuevo.";
    } else {
    $eq_equipo  = trim($_POST['stock_equipo'] ?? '');
    $eq_modelo  = trim($_POST['stock_modelo'] ?? '');
    $eq_imei    = trim($_POST['stock_imei'] ?? '');
    $eq_costo   = max(0, floatval($_POST['stock_costo'] ?? 0));
    $eq_estatus = trim($_POST['stock_estatus'] ?? 'Disponible');
    $eq_notas   = trim($_POST['stock_notas'] ?? '');

    $dupImeiStockAlta = lt_buscarDuplicadoStock($conexion, $eq_imei);
    $dupImeiLineaAlta = !empty($eq_imei) ? lt_buscarDuplicado($conexion, 'imei_entregado', $eq_imei) : null;

    if (empty($eq_equipo)) {
        $msg = "⚠️ No se guardó: indica al menos la marca del equipo.";
    } elseif (!lt_validarImei($eq_imei)) {
        $msg = "⚠️ No se guardó: el IMEI debe tener 15 dígitos numéricos, o dejarse vacío.";
    } elseif ($dupImeiStockAlta) {
        $msg = "⚠️ No se guardó: ese IMEI ya está registrado en el stock (" . htmlspecialchars($dupImeiStockAlta['equipo'] . ' ' . $dupImeiStockAlta['modelo']) . ", estatus: " . htmlspecialchars($dupImeiStockAlta['estatus']) . ").";
    } elseif ($dupImeiLineaAlta) {
        $msg = "⚠️ No se guardó: ese IMEI ya está asignado en una línea activa (" . htmlspecialchars($dupImeiLineaAlta['numero_telefono'] ?: 'N/A') . ", a: " . htmlspecialchars($dupImeiLineaAlta['usuario'] ?: 'sin usuario') . ").";
    } else {
        try {
            lt_transaccion($conexion, function() use ($conexion, $eq_equipo, $eq_modelo, $eq_imei, $eq_costo, $eq_estatus, $eq_notas) {
                $stmt = $conexion->prepare("INSERT INTO equipos_stock (equipo, modelo, imei, costo, estatus, origen_usuario, notas) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $origen_manual = 'Alta manual (Sistemas)';
                $stmt->bind_param("sssdsss", $eq_equipo, $eq_modelo, $eq_imei, $eq_costo, $eq_estatus, $origen_manual, $eq_notas);
                $stmt->execute();
                $nota_alta = "Alta manual de equipo en stock: {$eq_equipo} {$eq_modelo} (IMEI: {$eq_imei}).";
                lt_registrarHistorial($conexion, 0, '', 'N/A', 'N/A', $origen_manual, 'N/A', $nota_alta, 'alta_stock_manual', $eq_imei);
            });
            $msg = "✅ Equipo agregado al stock correctamente.";
        } catch (\Throwable $exStock) {
            $msg = "❌ Error al guardar el equipo en stock: " . htmlspecialchars($exStock->getMessage());
        }
    }
    }
}
/* =========================================================
   3d. EDITAR UN EQUIPO DEL STOCK
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_stock'])) {
    if (!lt_csrfValido()) {
        $msg = "⚠️ No se guardó: la sesión del formulario expiró. Recarga la página e intenta de nuevo.";
    } else {
    $id_stk_edit = intval($_POST['stock_id_edit'] ?? 0);
    $eq_equipo   = trim($_POST['stock_equipo'] ?? '');
    $eq_modelo   = trim($_POST['stock_modelo'] ?? '');
    $eq_imei     = trim($_POST['stock_imei'] ?? '');
    $eq_costo    = max(0, floatval($_POST['stock_costo'] ?? 0));
    $eq_estatus  = trim($_POST['stock_estatus'] ?? 'Disponible');
    $eq_notas    = trim($_POST['stock_notas'] ?? '');

    if ($id_stk_edit > 0) {
        $dupImeiStockEdit = lt_buscarDuplicadoStock($conexion, $eq_imei, $id_stk_edit);
        $dupImeiLineaEdit = !empty($eq_imei) ? lt_buscarDuplicado($conexion, 'imei_entregado', $eq_imei) : null;

        if (empty($eq_equipo)) {
            $msg = "⚠️ No se guardó: indica al menos la marca del equipo.";
        } elseif (!lt_validarImei($eq_imei)) {
            $msg = "⚠️ No se guardó: el IMEI debe tener 15 dígitos numéricos, o dejarse vacío.";
        } elseif ($dupImeiStockEdit) {
            $msg = "⚠️ No se guardó: ese IMEI ya está registrado en otro equipo del stock (" . htmlspecialchars($dupImeiStockEdit['equipo'] . ' ' . $dupImeiStockEdit['modelo']) . ").";
        } elseif ($dupImeiLineaEdit) {
            $msg = "⚠️ No se guardó: ese IMEI ya está asignado en una línea activa (" . htmlspecialchars($dupImeiLineaEdit['numero_telefono'] ?: 'N/A') . ", a: " . htmlspecialchars($dupImeiLineaEdit['usuario'] ?: 'sin usuario') . ").";
        } else {
            try {
                lt_transaccion($conexion, function() use ($conexion, $id_stk_edit, $eq_equipo, $eq_modelo, $eq_imei, $eq_costo, $eq_estatus, $eq_notas) {
                    $stmt = $conexion->prepare("UPDATE equipos_stock SET equipo = ?, modelo = ?, imei = ?, costo = ?, estatus = ?, notas = ? WHERE id = ?");
                    $stmt->bind_param("sssdssi", $eq_equipo, $eq_modelo, $eq_imei, $eq_costo, $eq_estatus, $eq_notas, $id_stk_edit);
                    $stmt->execute();
                    $nota_edit = "Edición manual del equipo en stock: {$eq_equipo} {$eq_modelo} (IMEI: {$eq_imei}).";
                    lt_registrarHistorial($conexion, 0, '', 'N/A', 'N/A', 'N/A', 'N/A', $nota_edit, 'edicion', $eq_imei);
                });
                $msg = "✏️ Equipo del stock actualizado correctamente.";
            } catch (\Throwable $exStockEdit) {
                $msg = "❌ Error al actualizar el equipo: " . htmlspecialchars($exStockEdit->getMessage());
            }
        }
    }
    }
}
/* =========================================================
   4. EDITAR REGISTRO EXISTENTE
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_linea'])) {
    if (!lt_csrfValido()) {
        $msg = "⚠️ No se guardó: la sesión del formulario expiró. Recarga la página e intenta de nuevo.";
    } else {
    $id_edit            = intval($_POST['id_linea_edit'] ?? 0);
    $numero_telefono    = lt_limpiarTelefono(trim($_POST['numero_telefono'] ?? ''));
    $tipo_emp           = trim($_POST['tipo_emp'] ?? '');
    $estatus_linea      = trim($_POST['estatus_linea'] ?? 'Activa');
    $usuario            = trim($_POST['usuario'] ?? '');
    $puesto             = trim($_POST['puesto'] ?? '');
    $fecha_entrega      = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : NULL;
    $equipo             = trim($_POST['equipo'] ?? '');
    $modelo             = trim($_POST['modelo'] ?? '');
    $cuenta_google      = trim($_POST['cuenta_google'] ?? '');
    $eq_nuevo           = trim($_POST['eq_nuevo'] ?? 'NO');
    $resguardo          = trim($_POST['resguardo'] ?? '');
    $imei_entregado     = trim($_POST['imei_entregado'] ?? '');
    $costo              = max(0, floatval($_POST['costo'] ?? 0));
    $comentarios_entrega = trim($_POST['comentarios_entrega'] ?? '');
    $comentarios_extras  = trim($_POST['comentarios_extras'] ?? '');
    if ($id_edit > 0 && $numero_telefono) {
        // Capturamos el estado previo para poder registrar en la bitácora
        // exactamente qué campos cambiaron con esta edición manual.
        $q_prev_edit = $conexion->query("SELECT * FROM lineas_telefonicas WHERE id = " . $id_edit);
        $prev_edit = $q_prev_edit ? $q_prev_edit->fetch_assoc() : null;

        // No se guarda la edición si el número o el IMEI ya quedarían
        // duplicados con OTRA línea activa.
        $dupTelEdit  = lt_buscarDuplicado($conexion, 'numero_telefono', $numero_telefono, $id_edit);
        $dupImeiEdit = !empty($imei_entregado) ? lt_buscarDuplicado($conexion, 'imei_entregado', $imei_entregado, $id_edit) : null;

        if (!$prev_edit) {
            $msg = "⚠️ No se guardó: el registro que intentas editar ya no existe.";
        } elseif (!lt_validarTelefono($numero_telefono)) {
            $msg = "⚠️ No se guardó: el número de teléfono debe tener 10 dígitos (ej. 4491234567).";
        } elseif (!lt_validarImei($imei_entregado)) {
            $msg = "⚠️ No se guardó: el IMEI debe tener 15 dígitos numéricos, o dejarse vacío.";
        } elseif ($dupTelEdit) {
            $msg = "⚠️ No se guardó: el número " . htmlspecialchars($numero_telefono) . " ya está en uso en otra línea activa (asignada a: " . htmlspecialchars($dupTelEdit['usuario'] ?: 'sin usuario') . ").";
        } elseif ($dupImeiEdit) {
            $msg = "⚠️ No se guardó: el IMEI " . htmlspecialchars($imei_entregado) . " ya está en uso en otra línea activa (" . htmlspecialchars($dupImeiEdit['numero_telefono'] ?: 'N/A') . ", asignada a: " . htmlspecialchars($dupImeiEdit['usuario'] ?: 'sin usuario') . ").";
        } else {
        try {
            lt_transaccion($conexion, function() use (
                $conexion, $id_edit, $numero_telefono, $tipo_emp, $estatus_linea, $usuario, $puesto,
                $fecha_entrega, $equipo, $modelo, $cuenta_google, $eq_nuevo, $resguardo, $imei_entregado,
                $costo, $comentarios_entrega, $comentarios_extras, $prev_edit
            ) {
                $stmt = $conexion->prepare("UPDATE lineas_telefonicas SET
                    numero_telefono = ?, tipo_emp = ?, estatus_linea = ?, usuario = ?, puesto = ?,
                    fecha_entrega = ?, equipo = ?, modelo = ?, cuenta_google = ?, eq_nuevo = ?,
                    resguardo = ?, imei_entregado = ?, costo = ?, comentarios_entrega = ?, comentarios_extras = ?
                    WHERE id = ?");
                $stmt->bind_param("ssssssssssssdssi",
                    $numero_telefono, $tipo_emp, $estatus_linea, $usuario, $puesto,
                    $fecha_entrega, $equipo, $modelo, $cuenta_google, $eq_nuevo,
                    $resguardo, $imei_entregado, $costo, $comentarios_entrega, $comentarios_extras, $id_edit
                );
                $stmt->execute();

                $campos_editables = [
                    'numero_telefono' => 'Número', 'tipo_emp' => 'Tipo EMP', 'estatus_linea' => 'Estatus',
                    'usuario' => 'Usuario', 'puesto' => 'Puesto', 'fecha_entrega' => 'Fecha entrega',
                    'equipo' => 'Equipo', 'modelo' => 'Modelo', 'cuenta_google' => 'Cuenta Google',
                    'eq_nuevo' => 'EQ Nuevo', 'resguardo' => 'Resguardo', 'imei_entregado' => 'IMEI',
                    'costo' => 'Costo', 'comentarios_entrega' => 'Coment. entrega', 'comentarios_extras' => 'Coment. extras',
                ];
                $valores_nuevos = compact(
                    'numero_telefono', 'tipo_emp', 'estatus_linea', 'usuario', 'puesto', 'fecha_entrega',
                    'equipo', 'modelo', 'cuenta_google', 'eq_nuevo', 'resguardo', 'imei_entregado',
                    'costo', 'comentarios_entrega', 'comentarios_extras'
                );
                $cambios = [];
                foreach ($campos_editables as $campo => $etiqueta) {
                    $antes   = trim((string)($prev_edit[$campo] ?? ''));
                    $despues = trim((string)($valores_nuevos[$campo] ?? ''));
                    if ($antes !== $despues) {
                        $cambios[] = "{$etiqueta}: \"{$antes}\" -> \"{$despues}\"";
                    }
                }
                if ($cambios) {
                    $nota_edicion = 'Edición manual del registro. Cambios: ' . implode(' | ', $cambios);
                    lt_registrarHistorial(
                        $conexion, $id_edit, $numero_telefono,
                        $prev_edit['usuario'] ?: 'N/A', $prev_edit['puesto'] ?: 'N/A',
                        $usuario ?: 'N/A', $puesto ?: 'N/A',
                        $nota_edicion, 'edicion', $imei_entregado
                    );
                }
            });
            $msg = "✏️ Registro actualizado correctamente.";
        } catch (\Throwable $exEdit) {
            $msg = "❌ Error al actualizar: " . htmlspecialchars($exEdit->getMessage());
        }
        }
    }
    }
}
/* =========================================================
   5. PROCESAR ACCIONES (REASIGNAR, DAÑO/EQUIPO NUEVO, RESGUARDO, BAJA)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_linea'])) {
  if (!lt_csrfValido()) {
    $msg = "⚠️ No se procesó la acción: la sesión del formulario expiró. Recarga la página e intenta de nuevo.";
  } else {
    $id_linea = intval($_POST['id_linea'] ?? 0);
    $accion   = trim($_POST['accion_linea']);
    if ($id_linea > 0) {
        $q_act = $conexion->query("SELECT numero_telefono, usuario, puesto, equipo, modelo, imei_entregado, costo FROM lineas_telefonicas WHERE id = $id_linea");
        $datos_previos = $q_act ? $q_act->fetch_assoc() : null;
        if (!$datos_previos) {
            $msg = "⚠️ No se pudo procesar: la línea ya no existe.";
        } else {
        $tel_act    = $datos_previos['numero_telefono'] ?? '';
        $eq_ant     = $datos_previos['equipo'] ?? '';
        $mod_ant    = $datos_previos['modelo'] ?? '';
        $imei_ant   = $datos_previos['imei_entregado'] ?? '';
        $costo_ant  = floatval($datos_previos['costo'] ?? 0);

        $usr_limpio_ant = $datos_previos['usuario'] ?? '';
        $usr_limpio_ant = preg_replace('/\s*\(En Resguardo\).*$/i', '', $usr_limpio_ant);
        $usr_limpio_ant = preg_replace('/\s*\(Último:.*?\)$/i', '', $usr_limpio_ant);

        $usr_ant    = !empty(trim($usr_limpio_ant)) ? trim($usr_limpio_ant) : 'N/A';
        $puesto_ant = !empty(trim($datos_previos['puesto'] ?? '')) ? trim($datos_previos['puesto']) : 'N/A';
        if ($accion === 'reasignar') {
            $nuevo_usuario = trim($_POST['nuevo_usuario'] ?? '');
            $nuevo_puesto  = trim($_POST['nuevo_puesto'] ?? '');
            $comentarios   = trim($_POST['notas_reasignacion'] ?? '');

            if ($nuevo_usuario && $nuevo_puesto) {
                try {
                    lt_transaccion($conexion, function() use ($conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $nuevo_usuario, $nuevo_puesto, $comentarios, $imei_ant) {
                        // NUEVO: se agrega $imei_ant al final — el equipo no cambia en
                        // una reasignación, solo el responsable, así que queda registrado
                        // el IMEI que sigue vigente para el reporte de entregas.
                        lt_registrarHistorial($conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $nuevo_usuario, $nuevo_puesto, $comentarios, 'reasignar', $imei_ant);

                        $stmt = $conexion->prepare("UPDATE lineas_telefonicas SET
                            usuario = ?,
                            puesto = ?,
                            estatus_linea = 'Activa',
                            fecha_entrega = CURDATE()
                            WHERE id = ?");
                        $stmt->bind_param("ssi", $nuevo_usuario, $nuevo_puesto, $id_linea);
                        $stmt->execute();
                    });
                    $msg = "🔄 Equipo y línea reasignados exitosamente a " . htmlspecialchars($nuevo_usuario) . ".";
                } catch (\Throwable $exReasignar) {
                    $msg = "❌ Error al reasignar: " . htmlspecialchars($exReasignar->getMessage());
                }
            } else {
                $msg = "⚠️ Faltan datos obligatorios (Nuevo usuario y puesto).";
            }
        } elseif ($accion === 'equipo_nuevo') {
            // Sustitución de equipo dañado conservando el mismo usuario
            // Origen del equipo nuevo: 'nuevo' (compra directa) o 'stock' (reutilizar existente)
            $origen_equipo_nuevo = trim($_POST['origen_equipo_nuevo'] ?? 'nuevo');
            $stock_id_sustitucion_asignado = null;

            if ($origen_equipo_nuevo === 'stock') {
                $stock_id_sus = intval($_POST['stock_id_sustitucion'] ?? 0);
                $nuevo_equipo = ''; $nuevo_modelo = ''; $nuevo_imei = ''; $nuevo_costo = 0;
                if ($stock_id_sus > 0) {
                    $q_stk_sus = $conexion->query("SELECT * FROM equipos_stock WHERE id = $stock_id_sus");
                    if ($q_stk_sus && ($stk_sus = $q_stk_sus->fetch_assoc())) {
                        $nuevo_equipo = $stk_sus['equipo'];
                        $nuevo_modelo = $stk_sus['modelo'];
                        $nuevo_imei   = $stk_sus['imei'];
                        $nuevo_costo  = max(0, floatval($stk_sus['costo']));
                        $stock_id_sustitucion_asignado = $stock_id_sus;
                    }
                }
            } else {
                $nuevo_equipo   = trim($_POST['nuevo_equipo'] ?? '');
                $nuevo_modelo   = trim($_POST['nuevo_modelo'] ?? '');
                $nuevo_imei     = trim($_POST['nuevo_imei'] ?? '');
                $nuevo_costo    = max(0, floatval($_POST['nuevo_costo'] ?? 0));
            }

            // No se asigna si el IMEI ya está en uso en otra línea activa
            // (no aplica al propio registro que se está sustituyendo).
            $dupImeiSustitucion = !empty($nuevo_imei) ? lt_buscarDuplicado($conexion, 'imei_entregado', $nuevo_imei, $id_linea) : null;

            if (empty($nuevo_equipo)) {
                $msg = "⚠️ Debes indicar o seleccionar un equipo válido para la sustitución.";
            } elseif (!lt_validarImei($nuevo_imei)) {
                $msg = "⚠️ No se asignó: el IMEI debe tener 15 dígitos numéricos, o dejarse vacío.";
            } elseif ($dupImeiSustitucion) {
                $msg = "⚠️ No se asignó: ese IMEI (" . htmlspecialchars($nuevo_imei) . ") ya está registrado en otra línea activa (" . htmlspecialchars($dupImeiSustitucion['numero_telefono'] ?: 'N/A') . ", asignada a: " . htmlspecialchars($dupImeiSustitucion['usuario'] ?: 'sin usuario') . ").";
            } else {
                // Decisión sobre el destino del equipo anterior
                $destino_viejo  = trim($_POST['destino_equipo_viejo'] ?? 'Disponible');
                $comentarios    = trim($_POST['notas_equipo_nuevo'] ?? 'Sustitución por equipo nuevo.');
                try {
                    lt_transaccion($conexion, function() use (
                        $conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $eq_ant, $mod_ant, $imei_ant, $costo_ant,
                        $nuevo_equipo, $nuevo_modelo, $nuevo_imei, $nuevo_costo, $destino_viejo, $comentarios,
                        $stock_id_sustitucion_asignado
                    ) {
                        $nota_historial = "Sustitución de equipo. Equipo anterior: {$eq_ant} {$mod_ant} (IMEI: {$imei_ant}) mandado a stock con estatus: [{$destino_viejo}]. Notas: {$comentarios}";
                        // NUEVO: se agrega $nuevo_imei al final — es el IMEI del equipo
                        // que queda asignado a partir de esta sustitución (venga de
                        // compra directa o de stock), clave para el reporte de entregas.
                        lt_registrarHistorial($conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $usr_ant, $puesto_ant, $nota_historial, 'equipo_nuevo', $nuevo_imei);
                        $stmt = $conexion->prepare("UPDATE lineas_telefonicas SET
                            equipo = ?,
                            modelo = ?,
                            imei_entregado = ?,
                            costo = ?,
                            fecha_entrega = CURDATE(),
                            estatus_linea = 'Activa'
                            WHERE id = ?");
                        $stmt->bind_param("ssdsi", $nuevo_equipo, $nuevo_modelo, $nuevo_imei, $nuevo_costo, $id_linea);
                        $stmt->execute();
                        // Si el equipo nuevo vino del stock, se marca como Asignado para que ya no aparezca disponible
                        if ($stock_id_sustitucion_asignado) {
                            $conexion->query("UPDATE equipos_stock SET estatus = 'Asignado' WHERE id = " . intval($stock_id_sustitucion_asignado));
                        }
                        if (!empty($eq_ant) || !empty($imei_ant)) {
                            $stmt_stock = $conexion->prepare("INSERT INTO equipos_stock (equipo, modelo, imei, costo, estatus, origen_usuario, numero_telefono, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt_stock->bind_param("sssdssss", $eq_ant, $mod_ant, $imei_ant, $costo_ant, $destino_viejo, $usr_ant, $tel_act, $comentarios);
                            $stmt_stock->execute();
                        }
                    });
                    $origen_txt = ($origen_equipo_nuevo === 'stock') ? " (equipo tomado del stock)" : "";
                    $msg = "📱 Se asignó el equipo nuevo{$origen_txt} al operador y el equipo anterior se mandó al inventario con estatus: <strong>" . htmlspecialchars($destino_viejo) . "</strong>.";
                } catch (\Throwable $exSustitucion) {
                    $msg = "❌ Error al sustituir el equipo: " . htmlspecialchars($exSustitucion->getMessage());
                }
            }
        } elseif ($accion === 'registrar_equipo_libre') {
            $estatus_stock = trim($_POST['estatus_stock'] ?? 'Disponible');
            $comentarios   = trim($_POST['notas_stock'] ?? 'Equipo recibido en sistemas.');
            if (!empty($eq_ant) || !empty($imei_ant)) {
                try {
                    lt_transaccion($conexion, function() use ($conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $eq_ant, $mod_ant, $imei_ant, $costo_ant, $estatus_stock, $comentarios) {
                        $stmt_stock = $conexion->prepare("INSERT INTO equipos_stock (equipo, modelo, imei, costo, estatus, origen_usuario, numero_telefono, notas) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt_stock->bind_param("sssdssss", $eq_ant, $mod_ant, $imei_ant, $costo_ant, $estatus_stock, $usr_ant, $tel_act, $comentarios);
                        $stmt_stock->execute();

                        $nota_hist_libre = "Equipo enviado a stock de sistemas. Equipo: {$eq_ant} {$mod_ant} (IMEI: {$imei_ant}) con estatus: [{$estatus_stock}]. Notas: {$comentarios}";
                        lt_registrarHistorial($conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $usr_ant, $puesto_ant, $nota_hist_libre, 'stock', $imei_ant);

                        $stmt_up = $conexion->prepare("UPDATE lineas_telefonicas SET equipo = '', modelo = '', imei_entregado = '', costo = 0, cuenta_google = '' WHERE id = ?");
                        $stmt_up->bind_param("i", $id_linea);
                        $stmt_up->execute();
                    });
                    $msg = "📦 El equipo anterior (" . htmlspecialchars($eq_ant . ' ' . $mod_ant) . ") ha sido registrado exitosamente en el <strong>Stock de Equipos</strong> para control de sistemas.";
                } catch (\Throwable $exLibre) {
                    $msg = "❌ Error al enviar al stock: " . htmlspecialchars($exLibre->getMessage());
                }
            } else {
                $msg = "⚠️ Esta línea no tenía ningún equipo registrado para mandar al stock.";
            }
        } elseif ($accion === 'resguardo') {
            $comentarios = trim($_POST['notas_resguardo'] ?? 'Equipo devuelto a sistemas para resguardo.');

            $nuevo_usuario = $usr_ant . ' (En Resguardo)';
            $nuevo_puesto = $puesto_ant;
            try {
                lt_transaccion($conexion, function() use ($conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $nuevo_usuario, $nuevo_puesto, $comentarios, $imei_ant) {
                    lt_registrarHistorial($conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $nuevo_usuario, $nuevo_puesto, $comentarios, 'resguardo', $imei_ant);
                    $stmt = $conexion->prepare("UPDATE lineas_telefonicas SET
                        usuario = ?,
                        puesto = ?,
                        estatus_linea = 'En Resguardo'
                        WHERE id = ?");
                    $stmt->bind_param("ssi", $nuevo_usuario, $puesto_ant, $id_linea);
                    $stmt->execute();
                });
                $msg = "📦 La línea/equipo ha sido puesta EN RESGUARDO.";
            } catch (\Throwable $exResguardo) {
                $msg = "❌ Error al poner en resguardo: " . htmlspecialchars($exResguardo->getMessage());
            }
        } elseif ($accion === 'baja') {
            $motivo_baja  = trim($_POST['motivo_baja'] ?? '');
            $fecha_actual = date('Y-m-d H:i:s');
            if ($motivo_baja) {
                try {
                    lt_transaccion($conexion, function() use ($conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $motivo_baja, $fecha_actual, $imei_ant) {
                        $stmt = $conexion->prepare("UPDATE lineas_telefonicas SET
                            estatus_linea = 'Baja',
                            motivo_baja = ?,
                            fecha_baja = ?
                            WHERE id = ?");
                        $stmt->bind_param("ssi", $motivo_baja, $fecha_actual, $id_linea);
                        $stmt->execute();

                        $nuevo_usuario_baja = $usr_ant . ' (Baja)';
                        lt_registrarHistorial($conexion, $id_linea, $tel_act, $usr_ant, $puesto_ant, $nuevo_usuario_baja, $puesto_ant, "Línea/equipo dada de BAJA. Motivo: {$motivo_baja}.", 'baja', $imei_ant);
                    });
                    $msg = "⚠️ La línea/equipo ha sido dada de BAJA correctamente.";
                } catch (\Throwable $exBaja) {
                    $msg = "❌ Error al procesar la baja: " . htmlspecialchars($exBaja->getMessage());
                }
            } else {
                $msg = "⚠️ Debes indicar el motivo de la baja.";
            }
        }
        }
    }
  }
}
/* =========================================================
   6. GUARDAR LÍNEA (NUEVA CAPTURA INDIVIDUAL - SOPORTA STOCK O NUEVO)
   ========================================================= */
$auto_open_row = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_linea'])) {
    if (!lt_csrfValido()) {
        $msg = "⚠️ No se guardó: la sesión del formulario expiró. Recarga la página e intenta de nuevo.";
    } else {
    $numero_telefono     = lt_limpiarTelefono(trim($_POST['numero_telefono'] ?? ''));
    $tipo_emp            = trim($_POST['tipo_emp'] ?? '');
    $estatus_linea        = trim($_POST['estatus_linea'] ?? 'Activa');
    $usuario             = trim($_POST['usuario'] ?? '');
    $puesto              = trim($_POST['puesto'] ?? '');
    $fecha_entrega       = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : NULL;
    $cuenta_google       = trim($_POST['cuenta_google'] ?? '');
    $resguardo           = trim($_POST['resguardo'] ?? '');
    $comentarios_entrega = trim($_POST['comentarios_entrega'] ?? '');
    $comentarios_extras  = trim($_POST['comentarios_extras'] ?? '');
    $tipo_asignacion     = trim($_POST['tipo_asignacion'] ?? 'nuevo');
    $stock_id_usado_alta = null;
    if ($numero_telefono) {
        if ($tipo_asignacion === 'stock') {
            // CASO A: Equipo elegido del stock existente
            $stock_id = intval($_POST['stock_id'] ?? 0);

            if ($stock_id > 0) {
                // Obtener datos del equipo en stock
                $q_stk = $conexion->query("SELECT * FROM equipos_stock WHERE id = $stock_id");
                if ($q_stk && ($stk = $q_stk->fetch_assoc())) {
                    $equipo         = $stk['equipo'];
                    $modelo         = $stk['modelo'];
                    $imei_entregado = $stk['imei'];
                    $costo          = max(0, floatval($stk['costo']));
                    $eq_nuevo       = 'NO'; // Viene del stock, no es compra directa nueva
                    $stock_id_usado_alta = $stock_id;
                } else {
                    $equipo = ''; $modelo = ''; $imei_entregado = ''; $costo = 0; $eq_nuevo = 'NO';
                }
            } else {
                $equipo = ''; $modelo = ''; $imei_entregado = ''; $costo = 0; $eq_nuevo = 'NO';
            }
        } else {
            // CASO B: Equipo completamente nuevo
            $equipo         = trim($_POST['equipo'] ?? '');
            $modelo         = trim($_POST['modelo'] ?? '');
            $imei_entregado = trim($_POST['imei_entregado'] ?? '');
            $costo          = max(0, floatval($_POST['costo'] ?? 0));
            $eq_nuevo       = trim($_POST['eq_nuevo'] ?? 'NO');
        }

        // No se guarda si el número o el IMEI ya están en uso en otra línea activa.
        $dupTelAlta  = lt_buscarDuplicado($conexion, 'numero_telefono', $numero_telefono);
        $dupImeiAlta = !empty($imei_entregado) ? lt_buscarDuplicado($conexion, 'imei_entregado', $imei_entregado) : null;

        if (!lt_validarTelefono($numero_telefono)) {
            $msg = "⚠️ No se guardó: el número de teléfono debe tener 10 dígitos (ej. 4491234567).";
        } elseif (!lt_validarImei($imei_entregado)) {
            $msg = "⚠️ No se guardó: el IMEI debe tener 15 dígitos numéricos, o dejarse vacío.";
        } elseif ($dupTelAlta) {
            $msg = "⚠️ No se guardó: ya existe una línea activa con el número " . htmlspecialchars($numero_telefono) . " (asignada a: " . htmlspecialchars($dupTelAlta['usuario'] ?: 'sin usuario') . ", estatus: " . htmlspecialchars($dupTelAlta['estatus_linea']) . ").";
        } elseif ($dupImeiAlta) {
            $msg = "⚠️ No se guardó: ese IMEI (" . htmlspecialchars($imei_entregado) . ") ya está registrado en otra línea activa (" . htmlspecialchars($dupImeiAlta['numero_telefono'] ?: 'N/A') . ", asignada a: " . htmlspecialchars($dupImeiAlta['usuario'] ?: 'sin usuario') . ").";
        } else {
        try {
            $nuevo_id = lt_transaccion($conexion, function() use (
                $conexion, $numero_telefono, $tipo_emp, $estatus_linea, $usuario, $puesto, $fecha_entrega,
                $equipo, $modelo, $cuenta_google, $eq_nuevo, $resguardo, $imei_entregado, $costo,
                $comentarios_entrega, $comentarios_extras, $stock_id_usado_alta
            ) {
                $stmt = $conexion->prepare("INSERT INTO lineas_telefonicas
                    (numero_telefono, tipo_emp, estatus_linea, usuario, puesto, fecha_entrega, equipo, modelo, cuenta_google, eq_nuevo, resguardo, imei_entregado, costo, comentarios_entrega, comentarios_extras)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->bind_param("ssssssssssssdss",
                    $numero_telefono, $tipo_emp, $estatus_linea, $usuario, $puesto, $fecha_entrega,
                    $equipo, $modelo, $cuenta_google, $eq_nuevo, $resguardo, $imei_entregado,
                    $costo, $comentarios_entrega, $comentarios_extras
                );
                $stmt->execute();
                $nuevoIdInterno = $conexion->insert_id;
                // Si el equipo vino del stock, se marca como Asignado para que ya no aparezca disponible.
                if ($stock_id_usado_alta) {
                    $conexion->query("UPDATE equipos_stock SET estatus = 'Asignado' WHERE id = " . intval($stock_id_usado_alta));
                }
                lt_registrarHistorial($conexion, $nuevoIdInterno, $numero_telefono, 'N/A', 'N/A', $usuario ?: 'N/A', $puesto ?: 'N/A', 'Alta inicial del registro / entrega de línea.', 'alta', $imei_entregado);
                return $nuevoIdInterno;
            });
            $res = $conexion->query("SELECT * FROM lineas_telefonicas WHERE id = " . intval($nuevo_id));
            $auto_open_row = $res ? $res->fetch_assoc() : null;
            $msg = "✅ Registro de línea guardado correctamente.";
        } catch (\Throwable $exGuardar) {
            $msg = "❌ Error al guardar: " . htmlspecialchars($exGuardar->getMessage());
        }
        }
    }
    }
}
/* =========================================================
   7. CONSULTA CON BUSCADOR Y FILTROS DINÁMICOS (+ PAGINACIÓN)
   ========================================================= */
$buscar = trim($_GET['buscar'] ?? '');
$filtrosLineas = [
    'buscar'          => $buscar,
    'estatus_filtro'  => trim($_GET['estatus_filtro'] ?? ''),
    'tipo_emp_filtro' => trim($_GET['tipo_emp_filtro'] ?? ''),
    'fecha_desde'     => trim($_GET['fecha_desde'] ?? ''),
    'fecha_hasta'     => trim($_GET['fecha_hasta'] ?? ''),
];
$hayFiltrosLineas = count(array_filter($filtrosLineas, fn($v) => $v !== '')) > 0;

// La tabla principal nunca muestra lo que está en la Papelera (eliminado = 1);
// esas líneas solo se ven/administran desde el modal de Papelera.
$condicionesLineas = ["(eliminado = 0 OR eliminado IS NULL)"];
$paramsLineas = [];
$tiposLineas = '';

if ($filtrosLineas['buscar'] !== '') {
    $like = '%' . $filtrosLineas['buscar'] . '%';
    $condicionesLineas[] = "(numero_telefono LIKE ? OR usuario LIKE ? OR puesto LIKE ? OR equipo LIKE ? OR modelo LIKE ? OR imei_entregado LIKE ? OR estatus_linea LIKE ? OR cuenta_google LIKE ?)";
    array_push($paramsLineas, $like, $like, $like, $like, $like, $like, $like, $like);
    $tiposLineas .= 'ssssssss';
}
if ($filtrosLineas['estatus_filtro'] !== '') {
    $condicionesLineas[] = "estatus_linea = ?";
    $paramsLineas[] = $filtrosLineas['estatus_filtro'];
    $tiposLineas .= 's';
}
if ($filtrosLineas['tipo_emp_filtro'] !== '') {
    $condicionesLineas[] = "tipo_emp = ?";
    $paramsLineas[] = $filtrosLineas['tipo_emp_filtro'];
    $tiposLineas .= 's';
}
if ($filtrosLineas['fecha_desde'] !== '') {
    $condicionesLineas[] = "fecha_entrega >= ?";
    $paramsLineas[] = $filtrosLineas['fecha_desde'];
    $tiposLineas .= 's';
}
if ($filtrosLineas['fecha_hasta'] !== '') {
    $condicionesLineas[] = "fecha_entrega <= ?";
    $paramsLineas[] = $filtrosLineas['fecha_hasta'];
    $tiposLineas .= 's';
}

$whereLineas = $condicionesLineas ? ('WHERE ' . implode(' AND ', $condicionesLineas)) : '';

// --- Paginación: primero se cuenta el total con los MISMOS filtros, y
// luego se trae solo la página actual con LIMIT/OFFSET. Antes se traía
// la tabla completa en cada carga, lo cual se vuelve lento conforme
// crece el catálogo de líneas.
$porPaginaOpciones = [10, 25, 50, 100];
$porPagina = intval($_GET['por_pagina'] ?? 25);
if (!in_array($porPagina, $porPaginaOpciones, true)) {
    $porPagina = 25;
}
$paginaActual = max(1, intval($_GET['pagina'] ?? 1));

$sqlConteoLineas = "SELECT COUNT(*) AS c FROM lineas_telefonicas $whereLineas";
$stmtConteo = $conexion->prepare($sqlConteoLineas);
if ($tiposLineas !== '') {
    $stmtConteo->bind_param($tiposLineas, ...$paramsLineas);
}
$stmtConteo->execute();
$total_lineas = (int)($stmtConteo->get_result()->fetch_assoc()['c'] ?? 0);

$totalPaginas = max(1, (int)ceil($total_lineas / $porPagina));
if ($paginaActual > $totalPaginas) {
    $paginaActual = $totalPaginas;
}
$offsetLineas = ($paginaActual - 1) * $porPagina;

// NUEVO: ordenar por columna (clic en el encabezado). Se valida contra una
// lista blanca de columnas para no exponer un SQL injection vía el nombre
// de columna, que no se puede parametrizar con "?".
$columnasOrdenables = [
    'numero_telefono' => 'numero_telefono',
    'usuario'         => 'usuario',
    'puesto'          => 'puesto',
    'estatus_linea'   => 'estatus_linea',
    'equipo'          => 'equipo',
    'fecha_entrega'   => 'fecha_entrega',
    'id'              => 'id',
];
$ordenCol = $_GET['orden'] ?? 'id';
if (!isset($columnasOrdenables[$ordenCol])) { $ordenCol = 'id'; }
$ordenDir = (strtolower($_GET['dir'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';
$columnaOrdenSql = $columnasOrdenables[$ordenCol];

$sqlLineas = "SELECT * FROM lineas_telefonicas $whereLineas ORDER BY {$columnaOrdenSql} {$ordenDir}, id DESC LIMIT ? OFFSET ?";
$stmt = $conexion->prepare($sqlLineas);
$tiposLineasPag = $tiposLineas . 'ii';
$paramsLineasPag = array_merge($paramsLineas, [$porPagina, $offsetLineas]);
$stmt->bind_param($tiposLineasPag, ...$paramsLineasPag);
$stmt->execute();
$result = $stmt->get_result();

/** Construye la URL de paginación conservando todos los filtros activos. */
function lt_urlPagina(array $filtros, int $pagina, int $porPagina): string {
    $params = array_filter($filtros, fn($v) => $v !== '');
    $params['pagina'] = $pagina;
    $params['por_pagina'] = $porPagina;
    if (!empty($_GET['orden'])) { $params['orden'] = $_GET['orden']; }
    if (!empty($_GET['dir'])) { $params['dir'] = $_GET['dir']; }
    return '?' . http_build_query($params);
}
/** Construye la URL para ordenar por una columna, alternando asc/desc si ya es la activa. */
function lt_urlOrden(array $filtros, string $columna, string $ordenColActual, string $ordenDirActual, int $porPagina): string {
    $params = array_filter($filtros, fn($v) => $v !== '');
    $params['por_pagina'] = $porPagina;
    $params['orden'] = $columna;
    $params['dir'] = ($ordenColActual === $columna && $ordenDirActual === 'ASC') ? 'desc' : 'asc';
    return '?' . http_build_query($params);
}

// Valores distintos para el select de "Tipo EMP"
$tiposEmpDisponibles = [];
$q_tipos_emp = $conexion->query("SELECT DISTINCT tipo_emp FROM lineas_telefonicas WHERE tipo_emp IS NOT NULL AND TRIM(tipo_emp) <> '' ORDER BY tipo_emp ASC");
if ($q_tipos_emp) {
    while ($r = $q_tipos_emp->fetch_assoc()) {
        $tiposEmpDisponibles[] = $r['tipo_emp'];
    }
}
$estatusLineaDisponibles = ['Activa', 'En Resguardo', 'Inactiva', 'Suspendida', 'Baja'];
/* =========================================================
   7b. BITÁCORA GENERAL / REPORTE DE MOVIMIENTOS (REASIGNACIONES, ETC.)
   ---------------------------------------------------------
   Lee directo de historial_lineas (no de lineas_telefonicas), así que
   también cubre líneas que ya fueron eliminadas: su historial queda
   archivado y visible aquí aunque el registro original ya no exista.
   ========================================================= */
$repFiltros = [
    'rep_tipo'   => trim($_GET['rep_tipo'] ?? ''),
    'rep_buscar' => trim($_GET['rep_buscar'] ?? ''),
    'rep_desde'  => trim($_GET['rep_desde'] ?? ''),
    'rep_hasta'  => trim($_GET['rep_hasta'] ?? ''),
];
$condicionesRep = [];
$paramsRep = [];
$tiposRep = '';
if ($repFiltros['rep_tipo'] !== '') {
    $condicionesRep[] = "tipo_accion = ?";
    $paramsRep[] = $repFiltros['rep_tipo'];
    $tiposRep .= 's';
}
if ($repFiltros['rep_buscar'] !== '') {
    $likeRep = '%' . $repFiltros['rep_buscar'] . '%';
    $condicionesRep[] = "(numero_telefono LIKE ? OR usuario_anterior LIKE ? OR usuario_nuevo LIKE ? OR realizado_por LIKE ? OR notas LIKE ?)";
    array_push($paramsRep, $likeRep, $likeRep, $likeRep, $likeRep, $likeRep);
    $tiposRep .= 'sssss';
}
if ($repFiltros['rep_desde'] !== '') {
    $condicionesRep[] = "fecha_cambio >= ?";
    $paramsRep[] = $repFiltros['rep_desde'] . ' 00:00:00';
    $tiposRep .= 's';
}
if ($repFiltros['rep_hasta'] !== '') {
    $condicionesRep[] = "fecha_cambio <= ?";
    $paramsRep[] = $repFiltros['rep_hasta'] . ' 23:59:59';
    $tiposRep .= 's';
}
$whereRep = $condicionesRep ? ('WHERE ' . implode(' AND ', $condicionesRep)) : '';
$reporteMovimientos = [];
$stmtRep = $conexion->prepare("SELECT * FROM historial_lineas $whereRep ORDER BY fecha_cambio DESC, id DESC LIMIT 500");
if ($stmtRep) {
    if ($tiposRep !== '') {
        $stmtRep->bind_param($tiposRep, ...$paramsRep);
    }
    $stmtRep->execute();
    $resultRep = $stmtRep->get_result();
    $reporteMovimientos = $resultRep ? $resultRep->fetch_all(MYSQLI_ASSOC) : [];
}
$totalReasignacionesGlobal = 0;
$q_cnt_reasig = $conexion->query("SELECT COUNT(*) AS c FROM historial_lineas WHERE tipo_accion = 'reasignar'");
if ($q_cnt_reasig) {
    $totalReasignacionesGlobal = (int)($q_cnt_reasig->fetch_assoc()['c'] ?? 0);
}
/* =========================================================
   7c. REPORTE DE ENTREGAS / REASIGNACIONES (SOLO CAMBIOS DE RESPONSABLE)
   ---------------------------------------------------------
   Igual patrón que la Bitácora General (7b) pero acotado
   solo a los movimientos donde cambia quién tiene el equipo:
   reasignaciones y sustituciones de equipo. Es el reporte que se
   pidió: quién lo tenía antes, a qué número, quién es el nuevo
   responsable, cuándo y con qué IMEI.
   ========================================================= */
$entFiltros = [
    'ent_buscar' => trim($_GET['ent_buscar'] ?? ''),
    'ent_desde'  => trim($_GET['ent_desde'] ?? ''),
    'ent_hasta'  => trim($_GET['ent_hasta'] ?? ''),
];
$condicionesEnt = ["tipo_accion IN ('reasignar','equipo_nuevo')"];
$paramsEnt = [];
$tiposEnt = '';
if ($entFiltros['ent_buscar'] !== '') {
    $likeEnt = '%' . $entFiltros['ent_buscar'] . '%';
    $condicionesEnt[] = "(numero_telefono LIKE ? OR usuario_anterior LIKE ? OR usuario_nuevo LIKE ? OR imei LIKE ?)";
    array_push($paramsEnt, $likeEnt, $likeEnt, $likeEnt, $likeEnt);
    $tiposEnt .= 'ssss';
}
if ($entFiltros['ent_desde'] !== '') {
    $condicionesEnt[] = "fecha_cambio >= ?";
    $paramsEnt[] = $entFiltros['ent_desde'] . ' 00:00:00';
    $tiposEnt .= 's';
}
if ($entFiltros['ent_hasta'] !== '') {
    $condicionesEnt[] = "fecha_cambio <= ?";
    $paramsEnt[] = $entFiltros['ent_hasta'] . ' 23:59:59';
    $tiposEnt .= 's';
}
$whereEnt = 'WHERE ' . implode(' AND ', $condicionesEnt);
$reporteEntregas = [];
$stmtEnt = $conexion->prepare("SELECT * FROM historial_lineas $whereEnt ORDER BY fecha_cambio DESC, id DESC LIMIT 500");
if ($stmtEnt) {
    if ($tiposEnt !== '') {
        $stmtEnt->bind_param($tiposEnt, ...$paramsEnt);
    }
    $stmtEnt->execute();
    $resultEnt = $stmtEnt->get_result();
    $reporteEntregas = $resultEnt ? $resultEnt->fetch_all(MYSQLI_ASSOC) : [];
}
/* =========================================================
   8. STOCK DE EQUIPOS DISPONIBLES (PARA LOS SELECTS DE ASIGNACIÓN)
   ========================================================= */
$stock_disponible = [];
$q_stock_all = $conexion->query("SELECT id, equipo, modelo, imei, costo, notas, origen_usuario, fecha_ingreso FROM equipos_stock WHERE estatus = 'Disponible' AND (eliminado = 0 OR eliminado IS NULL) ORDER BY fecha_ingreso DESC");
if ($q_stock_all) {
    while ($r = $q_stock_all->fetch_assoc()) {
        $stock_disponible[] = $r;
    }
}
function renderStockOptions($stock_disponible) {
    if (empty($stock_disponible)) {
        echo '<option value="" disabled>No hay equipos disponibles en stock</option>';
        return;
    }
    foreach ($stock_disponible as $stk) {
        $fechaFmt = !empty($stk['fecha_ingreso']) ? date('d/m/Y', strtotime($stk['fecha_ingreso'])) : 'N/A';
        // Texto informativo de quién tuvo este equipo antes, visible
        // directamente en la lista (no solo al seleccionarlo), para poder
        // elegir a ojo sin tener que abrir el detalle de cada uno.
        $origenTxt = trim((string)($stk['origen_usuario'] ?? ''));
        $origenLabel = ($origenTxt !== '' && $origenTxt !== 'N/A') ? " — antes: {$origenTxt}" : ' — sin uso previo';
        echo '<option value="' . $stk['id'] . '" '
           . 'data-equipo="' . htmlspecialchars($stk['equipo']) . '" '
           . 'data-modelo="' . htmlspecialchars($stk['modelo']) . '" '
           . 'data-imei="' . htmlspecialchars($stk['imei']) . '" '
           . 'data-costo="' . number_format((float)$stk['costo'], 2) . '" '
           . 'data-notas="' . htmlspecialchars($stk['notas'] ?? '') . '" '
           . 'data-origen="' . htmlspecialchars($stk['origen_usuario'] ?? 'N/A') . '" '
           . 'data-fecha="' . htmlspecialchars($fechaFmt) . '">'
           . htmlspecialchars($stk['equipo'] . ' - ' . $stk['modelo'] . ' (IMEI: ' . $stk['imei'] . ')' . $origenLabel)
           . '</option>';
    }
}
/* =========================================================
   9. STOCK DE EQUIPOS COMPLETO (PARA LA VISTA / MODAL DE STOCK)
   ========================================================= */
$stock_todos = [];
// Mismo LEFT JOIN por IMEI contra lineas_telefonicas que usa el Excel de
// Stock, para que el modal en pantalla muestre también el número y usuario
// ACTUALES a los que quedó asignado cada equipo (aunque haya quedado en una
// línea distinta a la que tenía antes de entrar a stock).
$q_stock_todos = $conexion->query("SELECT s.*,
        la.numero_telefono AS numero_actual,
        la.usuario AS usuario_actual,
        la.puesto AS puesto_actual
    FROM equipos_stock s
    LEFT JOIN lineas_telefonicas la
        ON CONVERT(la.imei_entregado USING utf8mb4) COLLATE utf8mb4_general_ci
           = CONVERT(s.imei USING utf8mb4) COLLATE utf8mb4_general_ci
        AND s.imei <> ''
        AND (la.eliminado = 0 OR la.eliminado IS NULL)
    WHERE (s.eliminado = 0 OR s.eliminado IS NULL)
    ORDER BY s.fecha_ingreso DESC");
if ($q_stock_todos) {
    while ($r = $q_stock_todos->fetch_assoc()) {
        $stock_todos[] = $r;
    }
}
$stock_disponible_count = count($stock_disponible);
/* Papelera de Stock (equipos con eliminado = 1): mismo patrón que la
   Papelera de líneas, para poder restaurar o purgar en definitiva. */
$papelera_stock = [];
$q_papelera_stock = $conexion->query("SELECT * FROM equipos_stock WHERE eliminado = 1 ORDER BY fecha_eliminado DESC, id DESC");
if ($q_papelera_stock) {
    while ($r = $q_papelera_stock->fetch_assoc()) {
        $papelera_stock[] = $r;
    }
}
$papelera_stock_count = count($papelera_stock);
/* =========================================================
   10. PAPELERA (LÍNEAS CON eliminado = 1, PARA RESTAURAR O PURGAR)
   ========================================================= */
$papelera_lineas = [];
$q_papelera = $conexion->query("SELECT * FROM lineas_telefonicas WHERE eliminado = 1 ORDER BY fecha_eliminado DESC, id DESC");
if ($q_papelera) {
    while ($r = $q_papelera->fetch_assoc()) {
        $papelera_lineas[] = $r;
    }
}
$papelera_count = count($papelera_lineas);
/* =========================================================
   11. KPIs DEL DASHBOARD (RESUMEN RÁPIDO ARRIBA DE LOS FILTROS)
   ========================================================= */
$kpi_estatus = ['Activa' => 0, 'En Resguardo' => 0, 'Baja' => 0, 'Suspendida' => 0, 'Inactiva' => 0];
$q_kpi_estatus = $conexion->query("SELECT estatus_linea, COUNT(*) AS c FROM lineas_telefonicas WHERE (eliminado = 0 OR eliminado IS NULL) GROUP BY estatus_linea");
if ($q_kpi_estatus) {
    while ($r = $q_kpi_estatus->fetch_assoc()) {
        if (isset($kpi_estatus[$r['estatus_linea']])) {
            $kpi_estatus[$r['estatus_linea']] = (int)$r['c'];
        }
    }
}
$kpi_costo_total = 0;
$q_kpi_costo = $conexion->query("SELECT SUM(costo) AS total FROM lineas_telefonicas WHERE (eliminado = 0 OR eliminado IS NULL)");
if ($q_kpi_costo) {
    $kpi_costo_total = (float)($q_kpi_costo->fetch_assoc()['total'] ?? 0);
}
$total_lineas_general = 0;
$q_kpi_total_general = $conexion->query("SELECT COUNT(*) AS c FROM lineas_telefonicas WHERE (eliminado = 0 OR eliminado IS NULL)");
if ($q_kpi_total_general) {
    $total_lineas_general = (int)($q_kpi_total_general->fetch_assoc()['c'] ?? 0);
}
// Cualquier línea cuyo estatus_linea no calce exactamente con el catálogo (vacío, NULL,
// mayúsculas/espacios distintos, valor viejo, etc.) cae aquí para que las tarjetas
// siempre sumen el mismo total que "Total de líneas".
$kpi_otros = max(0, $total_lineas_general - array_sum($kpi_estatus));
$kpi_otros_detalle = [];
if ($kpi_otros > 0) {
    $q_kpi_otros = $conexion->query("SELECT id, numero_telefono, usuario, estatus_linea FROM lineas_telefonicas
        WHERE (eliminado = 0 OR eliminado IS NULL)
          AND (estatus_linea IS NULL OR estatus_linea NOT IN ('Activa','En Resguardo','Inactiva','Suspendida','Baja'))
        LIMIT 20");
    if ($q_kpi_otros) {
        while ($r = $q_kpi_otros->fetch_assoc()) {
            $kpi_otros_detalle[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Líneas y Responsivas</title>

    <style>
        :root {
            /* Paleta alineada a css/theme.css (colores de marca de Transportes
               Valadez: azul marino corporativo + rojo corporativo), en vez de
               los azul/cian genéricos que traía antes este módulo. */
            --lt-ink: #0f172a;
            --lt-sub: #64748b;
            --lt-line: #e2e8f0;
            --lt-primary: #1e3a8a;
            --lt-primary-dark: #172554;
            --lt-primary-soft: #e6ebf8;
            --lt-accent: #3b82f6;
            --lt-accent-soft: #dbeafe;
            --lt-brand-red: #e31e24;
            --lt-brand-red-dark: #b91c1c;
            --lt-radius-lg: 20px;
            --lt-radius-md: 12px;
            --lt-radius-sm: 8px;
            --lt-shadow-sm: 0 2px 8px rgba(15, 23, 42, 0.06);
            --lt-shadow-md: 0 10px 25px rgba(15, 23, 42, 0.08);
            --lt-shadow-lg: 0 20px 40px rgba(15, 23, 42, 0.12);
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --bg-light-surface: #f8f9fa;
            --border-radius-custom: 0.5rem;
            --box-shadow-custom: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: var(--lt-ink);
            background: #f3f5f8;
        }

        /* Encabezado */
        .lineas-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: var(--lt-primary-dark);
            border-radius: var(--lt-radius-lg);
            padding: 26px 30px;
            margin-bottom: 22px;
            box-shadow: var(--lt-shadow-lg);
        }
        .lineas-header h4 {
            color: #fff !important;
            font-size: 1.3rem;
            letter-spacing: -0.2px;
            margin: 0;
        }
        .lineas-header h4 i { color: #fff !important; }
        .lineas-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.82rem;
            font-weight: 500;
            margin-top: 3px;
        }
        .lineas-header .btn-outline-light {
            font-weight: 600;
            font-size: 0.85rem;
            transition: background-color .15s ease, border-color .15s ease;
        }
        .lineas-header .btn-light {
            font-weight: 700;
            color: var(--lt-primary);
            transition: transform .15s ease;
        }

        /* Tarjeta de filtros */
        .filtros-card {
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid var(--lt-line);
            border-radius: var(--lt-radius-lg);
            padding: 20px 24px;
            margin-bottom: 22px;
            box-shadow: var(--lt-shadow-sm);
        }
        .filtros-titulo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 700;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--lt-primary);
            margin-bottom: 14px;
        }
        .filtros-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            align-items: end;
        }
        .filtros-grid .campo-busqueda { grid-column: span 2; }
        .filtros-grid label {
            display: block;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: var(--lt-sub);
            margin-bottom: 5px;
        }
        .filtros-grid .form-control,
        .filtros-grid .form-select {
            border-radius: var(--lt-radius-sm);
            border: 1px solid var(--lt-line);
            font-size: 0.85rem;
        }
        .filtros-grid .form-control:focus,
        .filtros-grid .form-select:focus {
            border-color: var(--lt-primary);
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.16);
        }
        .filtros-acciones { display: flex; gap: 8px; }
        .btn-filtrar {
            background: var(--lt-primary);
            border: none;
            color: #fff;
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: var(--lt-radius-sm);
            padding: 7px 16px;
            white-space: nowrap;
        }
        .btn-filtrar:hover { background: var(--lt-primary-dark); color: #fff; }
        .btn-limpiar {
            background: #f1f5f9;
            border: 1px solid var(--lt-line);
            color: var(--lt-sub);
            font-weight: 700;
            font-size: 0.85rem;
            border-radius: var(--lt-radius-sm);
            padding: 7px 14px;
            white-space: nowrap;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-limpiar:hover { background: #e2e8f0; color: var(--lt-ink); }
        .filtros-activas-aviso {
            margin-top: 10px;
            font-size: 0.78rem;
            color: var(--lt-accent);
            font-weight: 600;
        }

        /* Resumen / KPIs */
        .lt-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .lt-kpi-tile {
            display: flex;
            align-items: center;
            gap: 14px;
            background: #fff;
            border: 1px solid var(--lt-line);
            border-radius: var(--lt-radius-md);
            padding: 16px 18px;
            box-shadow: var(--lt-shadow-sm);
        }
        .lt-kpi-icon {
            width: 44px;
            height: 44px;
            min-width: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
        }
        .lt-kpi-value {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1.1;
            color: var(--lt-ink);
        }
        .lt-kpi-label {
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: var(--lt-sub);
            margin-top: 2px;
        }
        .lt-kpi-success .lt-kpi-icon { background: #ecfdf5; color: #16a34a; }
        .lt-kpi-info    .lt-kpi-icon { background: var(--lt-accent-soft); color: var(--lt-accent); }
        .lt-kpi-danger  .lt-kpi-icon { background: #fef2f2; color: #dc2626; }
        .lt-kpi-warning .lt-kpi-icon { background: #fffbeb; color: #d97706; }
        .lt-kpi-primary .lt-kpi-icon { background: var(--lt-primary-soft); color: var(--lt-primary); }
        .lt-kpi-neutral .lt-kpi-icon { background: #f1f5f9; color: #475569; }
        .lt-kpi-secondary .lt-kpi-icon { background: #f5f3ff; color: #7c3aed; }
        .lt-kpi-dark      .lt-kpi-icon { background: #eef2ff; color: #1e293b; }
        .lt-kpi-muted     .lt-kpi-icon { background: #f8fafc; color: #64748b; }

        .custom-card {
            background-color: #ffffff;
            border: 1px solid #edf1f5;
            border-radius: var(--lt-radius-lg);
            box-shadow: var(--lt-shadow-md);
            margin-bottom: 1.75rem;
            overflow: hidden;
        }
        .card-header-styled {
            background-color: transparent;
            border-bottom: 1px solid #edf1f5;
            padding: 1.25rem 1.5rem;
        }
        .section-label {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        .form-control, .form-select {
            border-radius: 0.375rem;
        }

        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(30, 58, 138, 0.16);
        }
        .btn-action-group .btn {
            padding: 0.3rem 0.55rem;
            font-size: 0.875rem;
        }
        .table > :not(caption) > * > * {
            padding: 0.65rem 0.85rem;
        }
        .table thead.table-light th {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: var(--lt-sub);
        }
        .table tbody tr {
            transition: background-color .12s ease;
        }
        .table tbody tr:hover { background-color: #f7f9fc; }
        /* NUEVO: encabezados ordenables (clic para ordenar la tabla principal) */
        .lt-th-ordenable {
            cursor: pointer;
            user-select: none;
            white-space: nowrap;
        }
        .lt-th-ordenable:hover { color: var(--lt-primary); }
        .lt-th-ordenable .lt-orden-icono {
            font-size: 0.65rem;
            margin-left: 4px;
            opacity: 0.45;
        }
        .lt-th-ordenable.lt-orden-activo .lt-orden-icono { opacity: 1; color: var(--lt-primary); }
        /* NUEVO: paginación */
        .lt-paginacion {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 14px 20px;
            border-top: 1px solid var(--lt-line);
            background: #fafbfc;
        }
        .lt-paginacion .lt-pag-info { font-size: 0.8rem; color: var(--lt-sub); }
        .lt-paginacion .lt-pag-botones { display: flex; gap: 4px; flex-wrap: wrap; }
        .lt-paginacion .lt-pag-botones a,
        .lt-paginacion .lt-pag-botones span {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border-radius: var(--lt-radius-sm);
            font-size: 0.8rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--lt-primary);
            border: 1px solid var(--lt-line);
            background: #fff;
        }
        .lt-paginacion .lt-pag-botones a:hover { background: var(--lt-primary-soft); }
        .lt-paginacion .lt-pag-botones .lt-pag-activa {
            background: var(--lt-primary);
            color: #fff;
            border-color: var(--lt-primary);
        }
        .lt-paginacion .lt-pag-botones .lt-pag-disabled {
            opacity: 0.4;
            pointer-events: none;
        }
        /* NUEVO: cajas de búsqueda rápida dentro de modales (Stock/Papelera) */
        .lt-buscador-modal {
            position: relative;
            margin-bottom: 14px;
        }
        .lt-buscador-modal i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--lt-sub);
        }
        .lt-buscador-modal input {
            padding-left: 34px;
            border-radius: var(--lt-radius-sm);
            border: 1px solid var(--lt-line);
        }

        @media (max-width: 991px) {
            .lineas-header { padding: 18px; }
            .lineas-header h4 { font-size: 1.1rem; }
            .filtros-grid .campo-busqueda { grid-column: 1 / -1; }
        }
        @media (max-width: 576px) {
            .lineas-header { flex-direction: column; align-items: flex-start; }
            .filtros-grid { grid-template-columns: 1fr 1fr; }
            .filtros-acciones { width: 100%; }
            .filtros-acciones .btn-filtrar,
            .filtros-acciones .btn-limpiar { flex: 1; text-align: center; }
        }

        .page-responsiva {
            width: 100%;
            max-width: 800px;
            min-height: 1000px;
            margin: 0 auto 30px auto;
            padding: 50px 40px;
            border: 1px solid #ddd;
            color: #000;
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.35;
            box-sizing: border-box;
            background-image: url('https://ticket.transportesvaladez.com/admin/lineas/img/fondo.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #ffffff;
        }
        i.fa.fa-trash {
            margin-top: 0;
        }
        .fecha-responsiva {
            text-align: right;
            font-size: 10pt;
            font-weight: bold;
            margin-bottom: 45px;
            margin-top: 10px;
        }
        .page-responsiva h2 {
            font-size: 11pt;
            text-align: center;
            text-transform: uppercase;
            margin-bottom: 12px;
            font-weight: bold;
        }
        .page-responsiva h4 {
            font-size: 9.5pt;
            margin-top: 10px;
            margin-bottom: 3px;
            text-transform: uppercase;
            font-weight: bold;
        }
        .page-responsiva p,
        .page-responsiva li {
            text-align: justify;
            margin: 2px 0;
            font-size: 9.5pt;
        }
        .page-responsiva ul {
            padding-left: 20px;
            margin: 2px 0;
        }
        .info-box {
            margin: 6px 0;
            line-height: 1.35;
        }
        .info-box p {
            margin: 2px 0;
            text-align: left;
        }
        .firmas {
            margin-top: 150px;
            text-align: center;
        }
        .linea-firma {
            width: 300px;
            border-top: 1px solid #000;
            margin: 0 auto 5px auto;
        }
        .ocultar-hoja {
            display: none !important;
        }
        @media print {
            @page {
                size: letter portrait;
                margin: 0 !important;
            }
            body > *:not(#modalResponsiva) {
                display: none !important;
            }
            html, body {
                width: 100% !important;
                height: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
                overflow: hidden !important;
            }
            #modalResponsiva,
            .modal-dialog,
            .modal-content,
            .modal-body {
                position: static !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
                background: transparent !important;
                box-shadow: none !important;
                transform: none !important;
            }
            .no-print, .modal-header, .modal-footer, .modal-backdrop {
                display: none !important;
            }
            .page-responsiva:not(.ocultar-hoja) {
                display: block !important;
                position: absolute !important;
                top: 0 !important;
                left: 0 !important;
                width: 8.5in !important;
                height: 11in !important;
                max-width: 8.5in !important;
                margin: 0 !important;
                padding: 80px 60px 40px 60px !important;
                box-sizing: border-box !important;
                background-image: url('https://ticket.transportesvaladez.com/admin/lineas/img/fondo.png') !important;
                background-size: 100% 100% !important;
                background-position: top center !important;
                background-repeat: no-repeat !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            .fecha-responsiva {
                margin-top: 5px !important;
                margin-bottom: 45px !important;
            }
            .firmas {
                margin-top: 100px !important;
            }
        }

        /* =========================================================
           SISTEMA VISUAL UNIFICADO PARA MODALES (.lt-modal)
           ========================================================= */
        .lt-modal .modal-dialog.modal-xl {
            max-width: min(1400px, 94vw);
        }
        .lt-modal .modal-content {
            border: none;
            border-radius: var(--lt-radius-lg);
            overflow: hidden;
            box-shadow: 0 16px 44px rgba(15, 23, 42, 0.16);
        }
        .lt-modal .modal-header {
            background: var(--lt-primary-dark);
            border-bottom: none;
            padding: 20px 26px;
            color: #fff;
        }
        .lt-modal .modal-header.lt-header-danger {
            background: #b91c1c;
        }
        .lt-modal .modal-header.lt-header-neutral {
            background: #334155;
        }
        .lt-modal .modal-title {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 1.05rem;
            letter-spacing: 0.2px;
        }
        .lt-modal .modal-title small {
            display: block;
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255, 255, 255, 0.75);
            margin-top: 2px;
        }
        .lt-modal-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(4px);
            font-size: 1.05rem;
        }
        .lt-modal .btn-close {
            opacity: 0.85;
        }
        .lt-modal .btn-close:hover { opacity: 1; }
        .lt-modal .modal-body {
            padding: 1.75rem 2rem;
            background: #fcfdfe;
        }
        .lt-modal .modal-footer {
            background: #f8fafc;
            border-top: 1px solid var(--lt-line);
            padding: 16px 26px;
        }
        .lt-modal .modal-footer .btn {
            border-radius: var(--lt-radius-sm);
            font-weight: 700;
            font-size: 0.85rem;
            padding: 8px 18px;
            transition: filter .12s ease;
        }
        .lt-modal .section-label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--lt-primary);
        }
        .lt-modal .section-label i { font-size: 0.75rem; }
        .lt-step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 19px;
            height: 19px;
            border-radius: 50%;
            background: var(--lt-primary);
            color: #fff;
            font-size: 0.66rem;
            font-weight: 800;
        }

        /* Tarjetas de acción dentro del modal de Gestión */
        .lt-card {
            border-radius: var(--lt-radius-md);
            padding: 18px 20px;
            margin-bottom: 18px;
            border: 1px solid var(--lt-line);
            background: #fff;
            box-shadow: var(--lt-shadow-sm);
        }
        .lt-card-reasignar { border-left: 4px solid var(--lt-primary); background: var(--lt-primary-soft); }
        .lt-card-equipo    { border-left: 4px solid #16a34a; background: #f0fdf4; }
        .lt-card-stock     { border-left: 4px solid #d97706; background: #fffbeb; }
        .lt-card-resguardo { border-left: 4px solid var(--lt-accent); background: var(--lt-accent-soft); }
        .lt-card-baja      { border-left: 4px solid #dc2626; background: #fef2f2; }

        .lt-usuario-actual {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f1f5f9;
            border: 1px solid var(--lt-line);
            border-radius: var(--lt-radius-md);
            padding: 12px 16px;
            margin-bottom: 18px;
        }
        .lt-usuario-actual .avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--lt-primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        select.lt-action-select {
            font-size: 0.95rem;
            padding: 10px 14px;
            border-width: 2px;
            border-color: var(--lt-primary);
        }

        /* Dropzone visual para el CSV */
        .lt-dropzone {
            border: 2px dashed #93c5fd;
            border-radius: var(--lt-radius-md);
            background: var(--lt-primary-soft);
            padding: 26px;
            text-align: center;
            transition: 0.15s;
        }
        .lt-dropzone:hover { border-color: var(--lt-primary); }
        .lt-dropzone i { font-size: 1.8rem; color: var(--lt-primary); }

        /* ---------- Timeline del Historial ---------- */
        .lt-timeline {
            position: relative;
            padding-left: 40px;
            margin: 4px 0 0 0;
        }
        .lt-timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            background: var(--lt-primary);
        }
        .lt-timeline-item { position: relative; padding-bottom: 20px; }
        .lt-timeline-item:last-child { padding-bottom: 0; }
        .lt-timeline-dot {
            position: absolute;
            left: -40px;
            top: 0;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.85rem;
            box-shadow: 0 0 0 4px #fcfdfe, 0 3px 8px rgba(16, 24, 40, 0.12);
        }
        .lt-dot-alta       { background: #0ea5e9; }
        .lt-dot-alta_csv   { background: #38bdf8; }
        .lt-dot-reasignar  { background: var(--lt-primary); }
        .lt-dot-equipo_nuevo { background: #16a34a; }
        .lt-dot-stock      { background: #d97706; }
        .lt-dot-stock_disponible { background: #0d9488; }
        .lt-dot-stock_eliminado  { background: #9f1239; }
        .lt-dot-resguardo  { background: var(--lt-accent); }
        .lt-dot-baja       { background: #dc2626; }
        .lt-dot-edicion    { background: #64748b; }
        .lt-dot-eliminacion{ background: #7f1d1d; }
        .lt-dot-restauracion { background: #15803d; }
        .lt-dot-eliminacion_definitiva { background: #450a0a; }
        .lt-dot-otro       { background: #94a3b8; }
        .lt-timeline-card {
            background: #fff;
            border: 1px solid var(--lt-line);
            border-radius: var(--lt-radius-md);
            padding: 14px 18px;
            box-shadow: var(--lt-shadow-sm);
        }
        .lt-timeline-top {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 6px;
        }
        .lt-badge-accion {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 3px 9px;
            border-radius: 999px;
            color: #fff;
        }
        .lt-timeline-fecha {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--lt-sub);
        }
        .lt-timeline-flujo {
            font-size: 0.85rem;
            color: var(--lt-ink);
            margin-bottom: 4px;
        }
        .lt-timeline-flujo b { color: var(--lt-primary-dark); }
        .lt-timeline-notas {
            font-size: 0.8rem;
            color: var(--lt-sub);
            margin-bottom: 6px;
        }
        .lt-timeline-autor {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.72rem;
            font-weight: 700;
            color: #0f172a;
            background: #f1f5f9;
            border-radius: 999px;
            padding: 2px 10px;
            width: fit-content;
        }
        .lt-timeline-autor i { color: var(--lt-primary); }
    </style>
</head>
<body>
<div class="container-fluid px-4 mb-5">
    <!-- NOTIFICACIONES -->
    <?php if($msg): ?>
        <div class="alert alert-info alert-dismissible fade show shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-circle-info fs-5 me-2"></i>
                <div><?= $msg ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- ENCABEZADO -->
    <div class="lineas-header">
        <div>
            <h4 class="fw-bold">
                <i class="fa-solid fa-mobile-screen-button me-2"></i>Líneas Telefónicas
            </h4>
            <div class="lineas-subtitle">
                <?php if ($hayFiltrosLineas): ?>
                    Mostrando <?= (int)$total_lineas ?> resultado(s) filtrado(s)
                <?php else: ?>
                    <?= (int)$total_lineas ?> línea(s) registrada(s)
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex flex-wrap justify-content-end gap-2">
            <button type="button" class="btn btn-light fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevaLinea">
                <i class="fa-solid fa-plus me-1"></i> Nueva Línea
            </button>
            <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modalCSV">
                <i class="fa-solid fa-file-csv me-1"></i> Carga CSV
            </button>
            <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modalNuevoStock">
                <i class="fa-solid fa-plus me-1"></i> Agregar Equipo
            </button>
            <button type="button" class="btn btn-outline-light position-relative" data-bs-toggle="modal" data-bs-target="#modalStock">
                <i class="fa-solid fa-boxes-stacked me-1"></i> Stock de Equipos
                <?php if ($stock_disponible_count > 0): ?>
                    <span class="badge rounded-pill bg-success ms-1"><?= $stock_disponible_count ?> disp.</span>
                <?php endif; ?>
                <?php if ($papelera_stock_count > 0): ?>
                    <span class="badge rounded-pill bg-danger ms-1"><?= $papelera_stock_count ?> en papelera</span>
                <?php endif; ?>
            </button>
            <button type="button" class="btn btn-outline-light position-relative" data-bs-toggle="modal" data-bs-target="#modalPapelera">
                <i class="fa-solid fa-trash-can me-1"></i> Papelera
                <?php if ($papelera_count > 0): ?>
                    <span class="badge rounded-pill bg-danger ms-1"><?= $papelera_count ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="btn btn-outline-light position-relative" data-bs-toggle="modal" data-bs-target="#modalReporte">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Bitácora / Reasignaciones
                <?php if ($totalReasignacionesGlobal > 0): ?>
                    <span class="badge rounded-pill bg-primary ms-1"><?= $totalReasignacionesGlobal ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="btn btn-outline-light" data-bs-toggle="modal" data-bs-target="#modalEntregas">
                <i class="fa-solid fa-arrow-right-arrow-left me-1"></i> Reporte de Entregas
            </button>
            <a href="?exportar_excel=1<?= $hayFiltrosLineas ? '&' . http_build_query(array_filter($filtrosLineas, fn($v) => $v !== '')) : '' ?>" class="btn btn-outline-light" title="Exporta respetando los filtros activos">
                <i class="fa-solid fa-file-excel me-1"></i> Excel
            </a>
        </div>
    </div>

    <!-- RESUMEN / KPIs -->
    <div class="lt-kpi-grid">
        <div class="lt-kpi-tile lt-kpi-dark">
            <div class="lt-kpi-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div>
                <div class="lt-kpi-value"><?= (int)$total_lineas_general ?></div>
                <div class="lt-kpi-label">Total de líneas</div>
            </div>
        </div>
        <div class="lt-kpi-tile lt-kpi-success">
            <div class="lt-kpi-icon"><i class="fa-solid fa-signal"></i></div>
            <div>
                <div class="lt-kpi-value"><?= (int)$kpi_estatus['Activa'] ?></div>
                <div class="lt-kpi-label">Líneas activas</div>
            </div>
        </div>
        <div class="lt-kpi-tile lt-kpi-info">
            <div class="lt-kpi-icon"><i class="fa-solid fa-box-archive"></i></div>
            <div>
                <div class="lt-kpi-value"><?= (int)$kpi_estatus['En Resguardo'] ?></div>
                <div class="lt-kpi-label">En resguardo</div>
            </div>
        </div>
        <div class="lt-kpi-tile lt-kpi-secondary">
            <div class="lt-kpi-icon"><i class="fa-solid fa-circle-pause"></i></div>
            <div>
                <div class="lt-kpi-value"><?= (int)$kpi_estatus['Suspendida'] ?></div>
                <div class="lt-kpi-label">Líneas suspendidas</div>
            </div>
        </div>
        <?php if ($kpi_otros > 0): ?>
        <div class="lt-kpi-tile lt-kpi-muted" title="Líneas cuyo estatus no calza con el catálogo (vacío, NULL, mayúsculas/espacios distintos, etc.)">
            <div class="lt-kpi-icon"><i class="fa-solid fa-circle-question"></i></div>
            <div>
                <div class="lt-kpi-value"><?= (int)$kpi_otros ?></div>
                <div class="lt-kpi-label">Sin estatus / otros</div>
            </div>
        </div>
        <?php endif; ?>
        <div class="lt-kpi-tile lt-kpi-danger">
            <div class="lt-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div>
                <div class="lt-kpi-value"><?= (int)$kpi_estatus['Baja'] ?></div>
                <div class="lt-kpi-label">De baja</div>
            </div>
        </div>
        <div class="lt-kpi-tile lt-kpi-warning">
            <div class="lt-kpi-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div>
                <div class="lt-kpi-value"><?= (int)$stock_disponible_count ?></div>
                <div class="lt-kpi-label">Equipos en stock</div>
            </div>
        </div>
        <div class="lt-kpi-tile lt-kpi-primary">
            <div class="lt-kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div>
                <div class="lt-kpi-value">$<?= number_format($kpi_costo_total, 0) ?></div>
                <div class="lt-kpi-label">Invertido en equipos activos</div>
            </div>
        </div>
        <div class="lt-kpi-tile lt-kpi-neutral">
            <div class="lt-kpi-icon"><i class="fa-solid fa-right-left"></i></div>
            <div>
                <div class="lt-kpi-value"><?= (int)$totalReasignacionesGlobal ?></div>
                <div class="lt-kpi-label">Reasignaciones (histórico)</div>
            </div>
        </div>
    </div>

    <?php if (!empty($kpi_otros_detalle)): ?>
    <div class="alert alert-warning py-2 px-3 mb-3" style="font-size:0.85rem;">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        Hay <?= (int)$kpi_otros ?> línea(s) con un estatus que no coincide con el catálogo (vacío o distinto a Activa/En Resguardo/Inactiva/Suspendida/Baja), por eso no se ven reflejadas en ninguna tarjeta de arriba. Corrígelas editando su estatus:
        <ul class="mb-0 mt-1">
            <?php foreach ($kpi_otros_detalle as $ko): ?>
                <li>ID <?= (int)$ko['id'] ?> — <?= htmlspecialchars($ko['numero_telefono']) ?> (<?= htmlspecialchars($ko['usuario']) ?>) — estatus actual: "<?= htmlspecialchars($ko['estatus_linea'] ?? 'NULL') ?>"</li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- BARRA DE FILTROS -->
    <div class="filtros-card">
        <div class="filtros-titulo"><i class="fa-solid fa-sliders"></i> Filtrar líneas</div>
        <form method="GET" action="" id="formFiltrosLineas">
            <div class="filtros-grid">
                <div class="campo-busqueda">
                    <label for="f_buscar">Búsqueda</label>
                    <input type="text" id="f_buscar" name="buscar" class="form-control"
                           placeholder="Teléfono, usuario, equipo, IMEI, cuenta Google..."
                           value="<?= htmlspecialchars($buscar) ?>">
                </div>
                <div>
                    <label for="f_estatus_filtro">Estatus</label>
                    <select id="f_estatus_filtro" name="estatus_filtro" class="form-select auto-submit-lineas">
                        <option value="">Todos</option>
                        <?php foreach ($estatusLineaDisponibles as $e): ?>
                            <option value="<?= htmlspecialchars($e) ?>" <?= $filtrosLineas['estatus_filtro'] === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f_tipo_emp_filtro">Tipo EMP</label>
                    <select id="f_tipo_emp_filtro" name="tipo_emp_filtro" class="form-select auto-submit-lineas">
                        <option value="">Todos</option>
                        <?php foreach ($tiposEmpDisponibles as $t): ?>
                            <option value="<?= htmlspecialchars($t) ?>" <?= $filtrosLineas['tipo_emp_filtro'] === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="f_desde">Entrega desde</label>
                    <input type="date" id="f_desde" name="fecha_desde" class="form-control auto-submit-lineas" value="<?= htmlspecialchars($filtrosLineas['fecha_desde']) ?>">
                </div>
                <div>
                    <label for="f_hasta">Entrega hasta</label>
                    <input type="date" id="f_hasta" name="fecha_hasta" class="form-control auto-submit-lineas" value="<?= htmlspecialchars($filtrosLineas['fecha_hasta']) ?>">
                </div>
                <div>
                    <label for="f_por_pagina">Por página</label>
                    <select id="f_por_pagina" name="por_pagina" class="form-select auto-submit-lineas">
                        <?php foreach ($porPaginaOpciones as $opcionPP): ?>
                            <option value="<?= $opcionPP ?>" <?= $porPagina === $opcionPP ? 'selected' : '' ?>><?= $opcionPP ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filtros-acciones">
                    <button type="submit" class="btn-filtrar"><i class="fa-solid fa-magnifying-glass me-1"></i>Buscar</button>
                    <?php if ($hayFiltrosLineas): ?>
                        <a href="?" class="btn-limpiar"><i class="fa-solid fa-xmark"></i>Limpiar</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
        <?php if ($hayFiltrosLineas): ?>
            <div class="filtros-activas-aviso"><i class="fa-solid fa-circle-check me-1"></i>Filtros activos</div>
        <?php endif; ?>
    </div>

    <!-- LISTADO GENERAL DE LÍNEAS -->
    <div class="custom-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php
                        // NUEVO: encabezados ordenables — clic para ordenar por esa
                        // columna (alterna asc/desc si ya es la columna activa).
                        $columnasCabecera = [
                            'numero_telefono' => 'Número',
                            'usuario'         => 'Usuario Actual',
                            'puesto'          => 'Puesto',
                            'estatus_linea'   => 'Estatus',
                            'equipo'          => 'Equipo / Modelo',
                            'fecha_entrega'   => 'Fecha Entrega',
                        ];
                        foreach ($columnasCabecera as $colKey => $colLabel):
                            $esActiva = ($ordenCol === $colKey);
                            $iconoOrden = $esActiva ? ($ordenDir === 'ASC' ? 'fa-sort-up' : 'fa-sort-down') : 'fa-sort';
                        ?>
                            <th class="lt-th-ordenable <?= $esActiva ? 'lt-orden-activo' : '' ?> <?= $colKey === 'numero_telefono' ? 'ps-3' : '' ?>"
                                onclick="location.href='<?= htmlspecialchars(lt_urlOrden($filtrosLineas, $colKey, $ordenCol, $ordenDir, $porPagina)) ?>'">
                                <?= htmlspecialchars($colLabel) ?><i class="fa-solid <?= $iconoOrden ?> lt-orden-icono"></i>
                            </th>
                        <?php endforeach; ?>
                        <th class="text-end pe-3">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($row['numero_telefono']) ?></td>
                            <td><?= !empty($row['usuario']) ? htmlspecialchars($row['usuario']) : '<span class="text-muted fst-italic">Sin asignar</span>' ?></td>
                            <td><?= htmlspecialchars($row['puesto']) ?></td>
                            <td>
                                <?php
                                    // CORREGIDO: antes cualquier estatus "desconocido" (vacío, mal
                                    // capturado, etc.) caía en el "else" y se pintaba verde como si
                                    // fuera Activa. Ahora el default es un badge neutro (gris).
                                    $badgeClass = 'bg-secondary';
                                    if ($row['estatus_linea'] === 'Activa') $badgeClass = 'bg-success';
                                    elseif ($row['estatus_linea'] === 'Baja') $badgeClass = 'bg-danger';
                                    elseif ($row['estatus_linea'] === 'En Resguardo') $badgeClass = 'bg-info text-dark';
                                    elseif ($row['estatus_linea'] === 'Inactiva' || $row['estatus_linea'] === 'Suspendida') $badgeClass = 'bg-warning text-dark';
                                ?>
                                <span class="badge <?= $badgeClass ?> px-2 py-1">
                                    <?= htmlspecialchars($row['estatus_linea'] ?: 'Sin estatus') ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($row['equipo'] . ' ' . $row['modelo']) ?></td>
                            <td><?= $row['fecha_entrega'] ?></td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-action-group" role="group">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            title="Editar Registro"
                                            onclick="abrirModalEdit(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-warning text-dark"
                                            title="Reasignar, Surtir equipo nuevo por daño, Stock o Baja"
                                            onclick="abrirModalGestion(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['numero_telefono'])) ?>', '<?= htmlspecialchars(addslashes($row['usuario'])) ?>', '<?= htmlspecialchars(addslashes($row['puesto'])) ?>')">
                                        <i class="fa-solid fa-user-gear"></i>
                                    </button>

                                    <button type="button" class="btn btn-outline-info btn-sm" title="Ver Historial" onclick="cargarHistorial(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['numero_telefono'])) ?>')">
                                        📜
                                    </button>
                                    <?php if ($row['estatus_linea'] === 'Baja'): ?>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-success"
                                            title="Reactivar línea (estaba dada de Baja)"
                                            onclick="return confirm('¿Reactivar esta línea? Su estatus volverá a Activa.') && ejecutarAccionRapida('reactivar_linea', <?= $row['id'] ?>);">
                                        <i class="fa-solid fa-rotate-left"></i>
                                    </button>
                                    <?php endif; ?>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Imprimir Responsiva"
                                            data-usuario="<?= htmlspecialchars($row['usuario'] ?? '') ?>"
                                            data-telefono="<?= htmlspecialchars($row['numero_telefono'] ?? '') ?>"
                                            data-google="<?= htmlspecialchars($row['cuenta_google'] ?? '') ?>"
                                            data-equipo="<?= htmlspecialchars(($row['equipo'] ?? '') . ' / ' . ($row['modelo'] ?? '')) ?>"
                                            data-imei="<?= htmlspecialchars($row['imei_entregado'] ?? '') ?>"
                                            data-costo="<?= number_format($row['costo'] ?? 0, 2) ?>"
                                            data-entrega="<?= htmlspecialchars($row['comentarios_entrega'] ?? '') ?>"
                                            data-extras="<?= htmlspecialchars($row['comentarios_extras'] ?? '') ?>"
                                            data-fecha="<?= htmlspecialchars($row['fecha_entrega'] ?? '') ?>"
                                            onclick="abrirModalResponsiva(this)">
                                        <i class="fa fa-print"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Eliminar"
                                            onclick="confirmarEliminarLinea(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['numero_telefono'])) ?>', '<?= htmlspecialchars(addslashes($row['usuario'] ?: 'Sin asignar')) ?>')">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <?= $hayFiltrosLineas ? 'No se encontraron líneas que coincidan con los filtros aplicados.' : 'No se encontraron líneas telefónicas registradas.' ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_lineas > 0): ?>
        <!-- NUEVO: paginación de la tabla principal -->
        <div class="lt-paginacion">
            <div class="lt-pag-info">
                Página <?= (int)$paginaActual ?> de <?= (int)$totalPaginas ?> — <?= (int)$total_lineas ?> registro(s)
            </div>
            <div class="lt-pag-botones">
                <?php if ($paginaActual <= 1): ?>
                    <span class="lt-pag-disabled">&laquo;</span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(lt_urlPagina($filtrosLineas, $paginaActual - 1, $porPagina)) ?>">&laquo;</a>
                <?php endif; ?>
                <?php
                    $inicioPag = max(1, $paginaActual - 2);
                    $finPag = min($totalPaginas, $paginaActual + 2);
                    for ($p = $inicioPag; $p <= $finPag; $p++):
                ?>
                    <?php if ($p === $paginaActual): ?>
                        <span class="lt-pag-activa"><?= $p ?></span>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars(lt_urlPagina($filtrosLineas, $p, $porPagina)) ?>"><?= $p ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($paginaActual >= $totalPaginas): ?>
                    <span class="lt-pag-disabled">&raquo;</span>
                <?php else: ?>
                    <a href="<?= htmlspecialchars(lt_urlPagina($filtrosLineas, $paginaActual + 1, $porPagina)) ?>">&raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<!-- =========================================================
     FORMULARIO OCULTO PARA ACCIONES RÁPIDAS (Papelera / Stock / Reactivar)
     ---------------------------------------------------------
     NUEVO: antes estas acciones eran links GET (?eliminar=, ?restaurar=,
     etc.), disparables con solo visitar la URL. Ahora viajan por POST con
     token CSRF a través de este único formulario oculto reutilizable.
   ========================================================= -->
<form method="POST" id="formAccionRapida" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(lt_csrfToken()) ?>">
    <input type="hidden" name="accion_rapida" id="accionRapidaTipo">
    <input type="hidden" name="id_rapida" id="accionRapidaId">
</form>
<!-- =========================================================
     MODAL DE NUEVA CAPTURA DE LÍNEA
   ========================================================= -->
<div class="modal fade lt-modal" id="modalNuevaLinea" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="formLinea">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(lt_csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="lt-modal-icon"><i class="fa-solid fa-mobile-screen-button"></i></span>
                        <span>
                            Captura / Entrega de Línea
                            <small>Nueva línea telefónica corporativa</small>
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="section-label"><span class="lt-step">1</span> Datos Básicos del Servicio</div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Número de Teléfono <span class="text-danger">*</span></label>
                            <input type="text" name="numero_telefono" class="form-control" required placeholder="Ej. 4491234567">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">Tipo EMP</label>
                            <input type="text" name="tipo_emp" class="form-control" placeholder="Categoría">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">Estatus Línea</label>
                            <select name="estatus_linea" class="form-select fw-bold">
                                <option value="Activa">Activa</option>
                                <option value="En Resguardo">En Resguardo</option>
                                <option value="Inactiva">Inactiva</option>
                                <option value="Suspendida">Suspendida</option>
                                <option value="Baja">Baja</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Usuario (Empleado)</label>
                            <input type="text" name="usuario" class="form-control" placeholder="Nombre completo">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label fw-semibold small">Puesto</label>
                            <input type="text" name="puesto" class="form-control" placeholder="Cargo">
                        </div>
                    </div>
                    <div class="section-label"><span class="lt-step">2</span> Hardware y Asignación de Equipo</div>
                    <div class="row g-3 mb-4 bg-light p-3 rounded border">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Fecha Entrega</label>
                            <input type="date" name="fecha_entrega" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small text-primary">Origen del Equipo</label>
                            <select name="tipo_asignacion" id="tipo_asignacion" class="form-select border-primary fw-bold" onchange="toggleOrigenCaptura(this.value)">
                                <option value="nuevo">Nuevo (Registrar directo)</option>
                                <option value="stock">Del Stock (Reutilizar existente)</option>
                            </select>
                        </div>
                        <div class="col-md-5" id="seccion_stock" style="display: none;">
                            <label class="form-label fw-semibold small text-success">Seleccionar Equipo del Stock</label>
                            <select name="stock_id" id="stock_id" class="form-select border-success" onchange="mostrarDetalleStock(this, 'detalle_stock_nueva')">
                                <option value="">-- Seleccione un equipo disponible --</option>
                                <?php renderStockOptions($stock_disponible); ?>
                            </select>
                            <div id="detalle_stock_nueva" class="mt-2 p-2 border rounded bg-white small d-none"></div>
                        </div>
                        <div class="row g-3 m-0 p-0" id="seccion_nuevo">
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">Marca Equipo</label>
                                <input type="text" name="equipo" id="input_equipo" class="form-control" placeholder="Ej. Samsung">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold small">Modelo</label>
                                <input type="text" name="modelo" id="input_modelo" class="form-control" placeholder="Ej. Galaxy A14">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold small">IMEI Entregado</label>
                                <input type="text" name="imei_entregado" id="input_imei" class="form-control" placeholder="15 dígitos">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label fw-semibold small">EQ Nuevo</label>
                                <select name="eq_nuevo" class="form-select">
                                    <option value="NO">NO</option>
                                    <option value="SÍ">SÍ</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="section-label"><span class="lt-step">3</span> Complementos y Observaciones</div>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small">Costo Equipo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" name="costo" class="form-control text-end" value="0.00">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Cuenta Google</label>
                            <input type="email" name="cuenta_google" class="form-control" placeholder="correo@empresa.com">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold small">Resguardo</label>
                            <input type="text" name="resguardo" class="form-control" placeholder="Identificador / Folio interno">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Comentarios Entrega</label>
                            <textarea name="comentarios_entrega" class="form-control" rows="2" placeholder="Accesorios incluidos, estado físico..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Comentarios Extras</label>
                            <textarea name="comentarios_extras" class="form-control" rows="2" placeholder="Notas adicionales..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="guardar_linea" class="btn btn-success px-4 fw-semibold">
                        <i class="fa-solid fa-floppy-disk me-2"></i>Guardar y Generar Responsiva
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- =========================================================
     MODAL EDITAR REGISTRO
   ========================================================= -->
<div class="modal fade lt-modal" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(lt_csrfToken()) ?>">
                <div class="modal-header lt-header-neutral">
                    <h5 class="modal-title">
                        <span class="lt-modal-icon"><i class="fa-solid fa-pen-to-square"></i></span>
                        <span>
                            Editar Registro
                            <small>Cada cambio queda en el historial de la línea</small>
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_linea_edit" id="edit_id">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Número de Teléfono</label>
                            <input type="text" name="numero_telefono" id="edit_numero_telefono" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Tipo EMP</label>
                            <input type="text" name="tipo_emp" id="edit_tipo_emp" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Estatus Línea</label>
                            <select name="estatus_linea" id="edit_estatus_linea" class="form-select fw-bold">
                                <option value="Activa">Activa</option>
                                <option value="En Resguardo">En Resguardo</option>
                                <option value="Inactiva">Inactiva</option>
                                <option value="Suspendida">Suspendida</option>
                                <option value="Baja">Baja</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Usuario (Empleado)</label>
                            <input type="text" name="usuario" id="edit_usuario" class="form-control" placeholder="Opcional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Puesto</label>
                            <input type="text" name="puesto" id="edit_puesto" class="form-control" placeholder="Opcional">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Fecha Entrega</label>
                            <input type="date" name="fecha_entrega" id="edit_fecha_entrega" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Equipo</label>
                            <input type="text" name="equipo" id="edit_equipo" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Modelo</label>
                            <input type="text" name="modelo" id="edit_modelo" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">IMEI Entregado</label>
                            <input type="text" name="imei_entregado" id="edit_imei_entregado" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Cuenta Google</label>
                            <input type="email" name="cuenta_google" id="edit_cuenta_google" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">EQ Nuevo</label>
                            <select name="eq_nuevo" id="edit_eq_nuevo" class="form-select">
                                <option value="NO">NO</option>
                                <option value="SÍ">SÍ</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Resguardo</label>
                            <input type="text" name="resguardo" id="edit_resguardo" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Costo Equipo</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0" name="costo" id="edit_costo" class="form-control text-end">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Comentarios Entrega</label>
                            <textarea name="comentarios_entrega" id="edit_comentarios_entrega" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Comentarios Extras</label>
                            <textarea name="comentarios_extras" id="edit_comentarios_extras" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="actualizar_linea" class="btn btn-success"><i class="fa-solid fa-floppy-disk me-1"></i> Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- =========================================================
     MODAL DE CARGA MASIVA (CSV)
   ========================================================= -->
<div class="modal fade lt-modal" id="modalCSV" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(lt_csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="lt-modal-icon"><i class="fa-solid fa-file-csv"></i></span>
                        <span>
                            Carga Masiva
                            <small>Importar varias líneas desde un archivo CSV</small>
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-between align-items-center bg-light p-3 border rounded mb-3">
                        <div>
                            <strong class="d-block text-dark">¿No tienes la plantilla?</strong>
                            <span class="small text-muted">Descarga el formato de ejemplo configurado.</span>
                        </div>
                        <button type="button" onclick="descargarPlantillaCSV()" class="btn btn-sm btn-outline-success fw-bold">
                            <i class="fa-solid fa-download me-1"></i> Descargar Plantilla
                        </button>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-bold">Seleccionar Archivo CSV</label>
                        <div class="lt-dropzone">
                            <i class="fa-solid fa-file-arrow-up mb-2 d-block"></i>
                            <input type="file" name="archivo_csv" class="form-control" accept=".csv" required>
                        </div>
                    </div>
                    <p class="small text-muted mb-0"><i class="fa-solid fa-circle-info me-1"></i> Cada línea importada queda registrada en el historial como "Alta por carga masiva".</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="importar_csv" class="btn btn-primary">
                        <i class="fa-solid fa-upload me-1"></i> Subir e Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- =========================================================
     MODAL DE GESTIÓN (REASIGNACIÓN, DAÑO/EQUIPO NUEVO, STOCK, RESGUARDO, BAJA)
   ========================================================= -->
<div class="modal fade lt-modal" id="modalGestion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(lt_csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <span class="lt-modal-icon"><i class="fa-solid fa-sliders"></i></span>
                        <span>
                            Gestión de Línea
                            <small id="lblTelefono"></small>
                        </span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_linea" id="gestion_id_linea">

                    <div class="lt-usuario-actual">
                        <span class="avatar"><i class="fa-solid fa-user"></i></span>
                        <div>
                            <div class="small text-muted fw-semibold">Usuario / resguardante actual</div>
                            <div><strong id="lblUsuarioActual"></strong> &middot; <span id="lblPuestoActual" class="text-muted"></span></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Selecciona la Acción</label>
                        <select name="accion_linea" id="accion_linea" class="form-select lt-action-select" onchange="toggleFormGestion()">
                            <option value="reasignar">🔄 Reasignar a un Nuevo Usuario</option>
                            <option value="equipo_nuevo">📱 Surtir Equipo Nuevo (Sustitución por Daño)</option>
                            <option value="registrar_equipo_libre">📥 Mandar Equipo Actual al Stock de Sistemas (Libre/Dañado)</option>
                            <option value="resguardo">📦 Poner en Resguardo (Sistemas)</option>
                            <option value="baja">⚠️ Dar de Baja Equipo / Línea</option>
                        </select>
                    </div>
                    <div id="secReasignar" class="lt-card lt-card-reasignar">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nuevo Usuario (Empleado) *</label>
                            <input type="text" name="nuevo_usuario" id="nuevo_usuario" class="form-control" placeholder="Ej. Juan Pérez">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Nuevo Puesto *</label>
                            <input type="text" name="nuevo_puesto" id="nuevo_puesto" class="form-control" placeholder="Ej. Chofer / Supervisor">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Notas / Comentarios de cambio</label>
                            <textarea name="notas_reasignacion" class="form-control" rows="2" placeholder="Motivo o detalle de cambio..."></textarea>
                        </div>
                    </div>
                    <div id="secEquipoNuevo" class="lt-card lt-card-equipo d-none">
                        <div class="alert alert-info small">
                            <i class="fa-solid fa-circle-info me-1"></i> El usuario conservará su número, se le registrará su nuevo aparato y decidirás el destino del equipo anterior.
                        </div>

                        <div class="mb-3 border p-3 rounded bg-light">
                            <label class="form-label fw-bold text-dark">¿Qué deseas hacer con el equipo anterior?</label>
                            <select name="destino_equipo_viejo" class="form-select fw-bold border-primary">
                                <option value="Disponible">🟢 Guardado para Reasignación (Disponible en Stock)</option>
                                <option value="En Reparación / Taller">🟡 Mandar a Reparación / Taller</option>
                                <option value="Baja por Daño">🔴 Dar de Baja por Daño / Obsolescencia</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Origen del Equipo Nuevo</label>
                            <select id="origen_equipo_nuevo" name="origen_equipo_nuevo" class="form-select fw-bold border-primary" onchange="toggleOrigenEquipoNuevo(this.value)">
                                <option value="nuevo">🆕 Nuevo (Compra directa)</option>
                                <option value="stock">📦 Del Stock (Reutilizar equipo existente)</option>
                            </select>
                        </div>

                        <div id="seccion_stock_sustitucion" class="mb-3 d-none">
                            <label class="form-label fw-semibold small text-success">Seleccionar Equipo del Stock</label>
                            <select name="stock_id_sustitucion" id="stock_id_sustitucion" class="form-select border-success" onchange="mostrarDetalleStock(this, 'detalle_stock_sustitucion')">
                                <option value="">-- Seleccione un equipo disponible --</option>
                                <?php renderStockOptions($stock_disponible); ?>
                            </select>
                            <div id="detalle_stock_sustitucion" class="mt-2 p-2 border rounded bg-white small d-none"></div>
                        </div>

                        <div id="seccion_nuevo_sustitucion" class="row g-3 m-0">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Marca del Equipo Nuevo *</label>
                                <input type="text" name="nuevo_equipo" id="nuevo_equipo" class="form-control" placeholder="Ej. Motorola / Samsung">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Modelo Nuevo *</label>
                                <input type="text" name="nuevo_modelo" id="nuevo_modelo" class="form-control" placeholder="Ej. Moto G24">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Nuevo IMEI *</label>
                                <input type="text" name="nuevo_imei" id="nuevo_imei" class="form-control" placeholder="IMEI del nuevo aparato">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold small">Costo del Equipo Nuevo</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0" name="nuevo_costo" id="nuevo_costo" class="form-control text-end" value="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Motivo del cambio / Notas del incidente</label>
                            <textarea name="notas_equipo_nuevo" class="form-control" rows="2" placeholder="Ej. Pantalla estrellada, se procede al cambio..."></textarea>
                        </div>
                    </div>
                    <div id="secStock" class="lt-card lt-card-stock d-none">
                        <div class="alert alert-warning small">
                            <i class="fa-solid fa-boxes-stacked me-1"></i> Esta opción retira el equipo actual de esta línea y lo registra en el inventario/stock de equipos de sistemas (disponibles o pendientes de revisión).
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Estatus del Equipo en Stock *</label>
                            <select name="estatus_stock" id="estatus_stock" class="form-select">
                                <option value="Disponible">Disponible (Listo para reasignar)</option>
                                <option value="Dañado / En Revisión">Dañado / En Revisión</option>
                                <option value="En Reparación / Garantía">En Reparación / Garantía</option>
                                <option value="Obsoleto / Baja">Obsoleto / Baja</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Notas / Motivo de devolución</label>
                            <textarea name="notas_stock" class="form-control" rows="2" placeholder="Ej. Se recibe equipo devuelto por cambio de modelo..."></textarea>
                        </div>
                    </div>
                    <div id="secResguardo" class="lt-card lt-card-resguardo d-none">
                        <div class="alert alert-warning small">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> El equipo será marcado como <strong>En Resguardo</strong> conservando el nombre del operador anterior.
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Notas de Resguardo</label>
                            <textarea name="notas_resguardo" class="form-control" rows="2" placeholder="Ej. Se recibe equipo dañado para revisión..."></textarea>
                        </div>
                    </div>
                    <div id="secBaja" class="lt-card lt-card-baja d-none">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-danger">Motivo de la Baja *</label>
                            <select name="motivo_baja" id="motivo_baja" class="form-select">
                                <option value="Robo o Extravío">Robo o Extravío</option>
                                <option value="Daño Total del Equipo">Daño Total del Equipo</option>
                                <option value="Fin de Relación Laboral / Devolución">Fin de Relación Laboral / Devolución</option>
                                <option value="Cancelación de Línea Corporativa">Cancelación de Línea Corporativa</option>
                                <option value="Otro Motivo">Otro Motivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarGestion">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- =========================================================
     MODAL DE RESPONSIVA (IMPRESIÓN)
   ========================================================= -->
<div class="modal fade lt-modal" id="modalResponsiva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl" style="margin-top:80px !important;">
        <div class="modal-content">
            <div class="modal-header no-print">
                <h5 class="modal-title">
                    <span class="lt-modal-icon"><i class="fa fa-print"></i></span>
                    <span>
                        Vista Previa de Responsiva
                        <small>Documento listo para imprimir y firmar</small>
                    </span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-0">
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center no-print">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="btnTabCelular" onclick="cambiarTab('celular')">
                            📱 Responsiva Equipo Celular
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="btnTabSim" onclick="cambiarTab('sim')">
                            💳 Responsiva Solo Tarjeta SIM
                        </button>
                    </div>
                    <button type="button" class="btn btn-success" onclick="window.print()">
                        <i class="fa fa-print me-1"></i> Imprimir
                    </button>
                </div>
                <div id="hojaCelular" class="page-responsiva">
                    <h2>RESPONSIVA DE EQUIPO CELULAR CORPORATIVO</h2>

                    <div class="fecha-responsiva">
                        Aguascalientes, Ags., a <span class="res_fecha_texto"></span>
                    </div>
                    <p>
                        Yo, <strong class="res_usuario">---</strong>, confirmo que recibo por parte de <strong>Transportes Valadez S. de R.L. de C.V.</strong> el siguiente equipo para uso laboral:
                    </p>
                    <div class="info-box">
                        <p><strong>Información del Equipo:</strong></p>
                        <p><strong>Número asignado:</strong> <span class="res_telefono">---</span></p>
                        <p><strong>Correo corporativo asignado:</strong> <span class="res_google">---</span></p>
                        <p><strong>Marca / Modelo:</strong> <span class="res_equipo">---</span></p>
                        <p><strong>IMEI:</strong> <span class="res_imei">---</span></p>
                        <p><strong>Comentarios de entrega:</strong> <span class="res_entrega">---</span></p>
                        <p><strong>Detalles:</strong> <span class="res_extras">---</span></p>
                    </div><br>
                    <h4>CONDICIONES DE USO</h4><br>
                    <p>El equipo y la línea asignada son exclusivamente para actividades laborales.</p>
                    <p>Queda prohibido:</p>
                    <ul>
                        <li>Registrar cuentas personales (Google, WhatsApp personal, bancos, redes sociales, etc.)</li>
                        <li>Utilizar la línea o el equipo para trámites o fines personales.</li>
                        <li>Instalar aplicaciones no autorizadas o modificar configuraciones.</li>
                    </ul><br>
                    <h4>RESPONSABILIDAD</h4><br>
                    <p>Me comprometo a:</p>
                    <ul>
                        <li>Cuidar el equipo y mantenerlo funcional.</li>
                        <li>Reportar fallas, daño o extravío de inmediato.</li>
                    </ul><br>

                    <p>
                        En caso de daño por mal uso, pérdida, robo negligente o falta de devolución cuando sea solicitado, me obligo a cubrir el valor de reposición de <strong>$<span class="res_costo">0.00</span> MXN</strong> o el monto actualizado determinado por la empresa.
                        Asimismo, si el daño al equipo deriva de una mala práctica o descuido, seré responsable de recobrar la comunicación (ya sea mediante los costos y gestiones para su reparación) o bien optar por el descuento total del equipo.
                    </p><br>
                    <p>
                        Adicionalmente, el formateo, borrado de información o restablecimiento del equipo sin autorización previa, ya sea al momento de la entrega o devolución del mismo, generará un cobro o sanción independiente, conforme al monto definido por la empresa, el cual no sustituye ni exime del pago por daño, pérdida o no devolución del equipo.
                    </p><br>
                    <p>
                        El equipo deberá ser devuelto cuando la empresa así lo solicite, en las condiciones establecidas.
                    </p>
                    <div class="firmas">
                        <div class="linea-firma"></div>
                        <strong class="res_usuario">---</strong>
                    </div>
                </div>
                <div id="hojaSim" class="page-responsiva ocultar-hoja">
                    <h2>RESPONSIVA DE LÍNEA Y TARJETA SIM CORPORATIVA</h2>
                    <div class="fecha-responsiva">
                        Aguascalientes, Ags., a <span class="res_fecha_texto"></span>
                    </div>
                    <p>
                        Yo, <strong class="res_usuario">---</strong>, confirmo que recibo por parte de <strong>Transportes Valadez S. de R.L. de C.V.</strong> la tarjeta SIM / chip corporativo para uso strictly laboral:
                    </p>
                    <div class="info-box">
                        <p><strong>Información de la Línea / SIM:</strong></p>
                        <p><strong>Número Asignado:</strong> <span class="res_telefono">---</span></p>
                        <p><strong>Usuario / Resguardante:</strong> <span class="res_usuario">---</span></p>
                        <p><strong>Comentarios de entrega:</strong> <span class="res_entrega">---</span></p>
                        <p><strong>Detalles adicionales:</strong> <span class="res_extras">---</span></p>
                    </div><br>
                    <h4>CONDICIONES DE USO</h4><br>
                    <p>La línea telefónica y el chip asignado son propiedad de la empresa y se proporcionan únicamente para dar cumplimiento a las labores operativas asignadas.</p>
                    <p>Queda prohibido:</p>
                    <ul>
                        <li>Hacer uso personal de la línea o transferir la SIM a dispositivos no autorizados.</li>
                        <li>Utilizar el número corporativo para suscripciones personales, aplicaciones bancarias o trámites ajenos a la empresa.</li>
                    </ul><br>
                    <h4>RESPONSABILIDAD Y REPOSICIÓN</h4><br>
                    <p>Me comprometo a dar un uso adecuado al servicio y notificar inmediatamente en caso de falla, robo o extravío para su oportuno bloqueo.</p>
                    <p>
                        En caso de robo, pérdida, descuido o reposición de la tarjeta SIM por negligencia, me obligo a cubrir el costo fijo de reposición del chip por la cantidad de <strong>$350.00 MXN</strong>. Si el daño ocurre en el equipo asociado por mala práctica, seré responsable de recobrar la comunicación (mediante su envío a reparación) o bien optar por cubrir el descuento total del equipo, según lo determine la empresa.
                    </p>
                    <p>
                        La tarjeta SIM deberá ser devuelta en el momento en que la empresa lo solicite o al término de la relación laboral.
                    </p>
                    <div class="firmas">
                        <div class="linea-firma"></div>
                        <strong class="res_usuario">---</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- =========================================================
     MODAL DE STOCK DE EQUIPOS
   ========================================================= -->
<div class="modal fade lt-modal" id="modalStock" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" style="margin-top:80px !important; height: calc(100vh - 100px) !important;">
    <div class="modal-content">
      <div class="modal-header lt-header-neutral">
        <h5 class="modal-title">
            <span class="lt-modal-icon"><i class="fa-solid fa-boxes-stacked"></i></span>
            <span>
                Stock de Equipos
                <small>Inventario disponible para reasignar</small>
            </span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <!-- NUEVO: buscador rápido (filtra en el navegador, sin recargar) -->
            <div class="lt-buscador-modal flex-grow-1" style="max-width:340px;">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" class="form-control form-control-sm" id="buscadorStock" placeholder="Buscar equipo, modelo, IMEI, usuario..." oninput="lt_filtrarTabla('tablaStockActivo', this.value)">
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnTogglePapeleraStock" onclick="togglePapeleraStock()">
                <i class="fa-solid fa-trash-can me-1"></i> Ver Papelera de Equipos (<?= $papelera_stock_count ?>)
            </button>
        </div>
        <div id="bloqueStockActivo">
        <?php if (empty($stock_todos)): ?>
            <div class="alert alert-info mb-0 text-center">No hay equipos registrados en el stock todavía.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" id="tablaStockActivo">
                <thead class="table-light">
                    <tr>
                        <th>Equipo / Modelo</th>
                        <th>IMEI</th>
                        <th>Costo</th>
                        <th>Estatus</th>
                        <th>Origen / Último Usuario</th>
                        <th>Teléfono asociado</th>
                        <th>Número Actual</th>
                        <th>Usuario Nuevo (Asignado)</th>
                        <th>Fecha Ingreso</th>
                        <th>Notas</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stock_todos as $stk): ?>
                        <?php
                            $stkBadge = 'bg-secondary';
                            if ($stk['estatus'] === 'Disponible') $stkBadge = 'bg-success';
                            elseif ($stk['estatus'] === 'Asignado') $stkBadge = 'bg-primary';
                            elseif (stripos($stk['estatus'], 'Dañ') !== false || stripos($stk['estatus'], 'Baja') !== false) $stkBadge = 'bg-danger';
                            elseif (stripos($stk['estatus'], 'Repara') !== false) $stkBadge = 'bg-warning text-dark';
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($stk['equipo'] . ' ' . $stk['modelo']) ?></td>
                            <td><?= htmlspecialchars($stk['imei'] ?? '') ?></td>
                            <td>$<?= number_format((float)($stk['costo'] ?? 0), 2) ?></td>
                            <td><span class="badge <?= $stkBadge ?>"><?= htmlspecialchars($stk['estatus']) ?></span></td>
                            <td><?= htmlspecialchars($stk['origen_usuario'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($stk['numero_telefono'] ?? 'N/A') ?></td>
                            <td><?= !empty($stk['numero_actual']) ? htmlspecialchars($stk['numero_actual']) : '<span class="text-muted">N/A</span>' ?></td>
                            <td><?= !empty($stk['usuario_actual']) ? '<strong>' . htmlspecialchars($stk['usuario_actual']) . '</strong>' : ($stk['estatus'] === 'Disponible' ? '<span class="text-muted">—</span>' : '<span class="text-muted">N/A</span>') ?></td>
                            <td><?= !empty($stk['fecha_ingreso']) ? date('d/m/Y', strtotime($stk['fecha_ingreso'])) : 'N/A' ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($stk['notas'] ?? '') ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-warning text-dark" title="Editar equipo"
                                            onclick="abrirModalEditarStock(<?= htmlspecialchars(json_encode($stk), ENT_QUOTES, 'UTF-8') ?>)">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <?php if (!empty($stk['imei'])): ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" title="Ver historial de este equipo"
                                                onclick="cargarHistorialStock(<?= (int)$stk['id'] ?>, '<?= htmlspecialchars(addslashes($stk['equipo'] . ' ' . $stk['modelo'] . ' (IMEI: ' . $stk['imei'] . ')')) ?>')">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($stk['estatus'] !== 'Disponible'): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success" title="Marcar como Disponible"
                                                onclick="ejecutarAccionRapida('marcar_disponible_stock', <?= (int)$stk['id'] ?>)">
                                            <i class="fa-solid fa-rotate-left"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Mandar a la Papelera"
                                            onclick="return confirm('¿Mandar este equipo a la Papelera de Stock? Podrás restaurarlo después.') && ejecutarAccionRapida('eliminar_stock', <?= (int)$stk['id'] ?>);">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        </div>
        <div id="bloquePapeleraStock" style="display:none;">
            <p class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i> Estos equipos ya no aparecen en el stock activo, pero puedes recuperarlos con "Restaurar". Solo "Eliminar en definitiva" borra el registro por completo.</p>
            <?php if (empty($papelera_stock)): ?>
                <div class="alert alert-info mb-0 text-center">La Papelera de Equipos está vacía.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" id="tablaPapeleraStock">
                    <thead class="table-light">
                        <tr>
                            <th>Equipo / Modelo</th>
                            <th>IMEI</th>
                            <th>Costo</th>
                            <th>Estatus previo</th>
                            <th>Fecha eliminación</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($papelera_stock as $stkp): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($stkp['equipo'] . ' ' . $stkp['modelo']) ?></td>
                            <td><?= htmlspecialchars($stkp['imei'] ?? '') ?></td>
                            <td>$<?= number_format((float)($stkp['costo'] ?? 0), 2) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($stkp['estatus'] ?? '') ?></span></td>
                            <td><?= !empty($stkp['fecha_eliminado']) ? date('d/m/Y H:i', strtotime($stkp['fecha_eliminado'])) : 'N/A' ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-success" title="Restaurar"
                                            onclick="return confirm('¿Restaurar este equipo? Volverá a aparecer en el stock activo.') && ejecutarAccionRapida('restaurar_stock', <?= (int)$stkp['id'] ?>);">
                                        <i class="fa-solid fa-trash-arrow-up"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar en definitiva"
                                            onclick="return confirm('¿ELIMINAR EN DEFINITIVA este equipo? Esta acción SÍ borra el registro por completo y no se puede deshacer.') && ejecutarAccionRapida('eliminar_stock_definitivo', <?= (int)$stkp['id'] ?>);">
                                        <i class="fa-solid fa-fire"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
      </div>
      <div class="modal-footer bg-light">
        <a href="?exportar_stock_excel=1" class="btn btn-outline-success">
            <i class="fa-solid fa-file-excel me-1"></i> Exportar a Excel
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- =========================================================
     MODAL: AGREGAR EQUIPO AL STOCK (ALTA MANUAL, SIN LÍNEA)
   ========================================================= -->
<div class="modal fade lt-modal" id="modalNuevoStock" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(lt_csrfToken()) ?>">
        <div class="modal-header lt-header-neutral">
            <h5 class="modal-title">
                <span class="lt-modal-icon"><i class="fa-solid fa-plus"></i></span>
                <span>Agregar Equipo al Stock</span>
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Marca del Equipo <span class="text-danger">*</span></label>
                <input type="text" name="stock_equipo" class="form-control" required placeholder="Ej. Samsung">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Modelo</label>
                <input type="text" name="stock_modelo" class="form-control" placeholder="Ej. Galaxy A14">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">IMEI</label>
                <input type="text" name="stock_imei" class="form-control" placeholder="15 dígitos (opcional)">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Costo</label>
                <input type="number" step="0.01" min="0" name="stock_costo" class="form-control text-end" value="0.00">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Estatus</label>
                <select name="stock_estatus" class="form-select">
                    <option value="Disponible" selected>Disponible</option>
                    <option value="Dañado">Dañado</option>
                    <option value="En reparación">En reparación</option>
                    <option value="Baja">Baja</option>
                </select>
            </div>
            <div class="mb-1">
                <label class="form-label fw-semibold small">Notas</label>
                <textarea name="stock_notas" class="form-control" rows="2" placeholder="Ej. Compra de repuesto para rotación de equipos..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" name="guardar_stock_manual" value="1" class="btn btn-primary fw-bold">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- =========================================================
     MODAL: EDITAR EQUIPO DEL STOCK
   ========================================================= -->
<div class="modal fade lt-modal" id="modalEditarStock" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(lt_csrfToken()) ?>">
        <div class="modal-header lt-header-neutral">
            <h5 class="modal-title">
                <span class="lt-modal-icon"><i class="fa-solid fa-pen-to-square"></i></span>
                <span>Editar Equipo del Stock</span>
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="stock_id_edit" id="edit_stock_id">
            <div class="mb-3">
                <label class="form-label fw-semibold small">Marca del Equipo <span class="text-danger">*</span></label>
                <input type="text" name="stock_equipo" id="edit_stock_equipo" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Modelo</label>
                <input type="text" name="stock_modelo" id="edit_stock_modelo" class="form-control">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">IMEI</label>
                <input type="text" name="stock_imei" id="edit_stock_imei" class="form-control" placeholder="15 dígitos (opcional)">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Costo</label>
                <input type="number" step="0.01" min="0" name="stock_costo" id="edit_stock_costo" class="form-control text-end">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold small">Estatus</label>
                <select name="stock_estatus" id="edit_stock_estatus" class="form-select">
                    <option value="Disponible">Disponible</option>
                    <option value="Asignado">Asignado</option>
                    <option value="Dañado">Dañado</option>
                    <option value="En reparación">En reparación</option>
                    <option value="Baja">Baja</option>
                </select>
            </div>
            <div class="mb-1">
                <label class="form-label fw-semibold small">Notas</label>
                <textarea name="stock_notas" id="edit_stock_notas" class="form-control" rows="2"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" name="actualizar_stock" value="1" class="btn btn-warning fw-bold">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- =========================================================
     MODAL: PAPELERA (LÍNEAS ELIMINADAS - RESTAURAR O PURGAR)
   ========================================================= -->
<div class="modal fade lt-modal" id="modalPapelera" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" style="margin-top:80px !important; height: calc(100vh - 100px) !important;">
    <div class="modal-content">
      <div class="modal-header lt-header-danger">
        <h5 class="modal-title">
            <span class="lt-modal-icon"><i class="fa-solid fa-trash-can"></i></span>
            <span>
                Papelera
                <small>Líneas eliminadas — se pueden restaurar</small>
            </span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if (empty($papelera_lineas)): ?>
            <div class="alert alert-info mb-0 text-center">La Papelera está vacía.</div>
        <?php else: ?>
        <p class="small text-muted"><i class="fa-solid fa-circle-info me-1"></i> Estos registros ya no aparecen en la tabla principal, pero siguen en la base y puedes recuperarlos con "Restaurar". Solo "Eliminar en definitiva" borra el registro por completo (esa sí ya no se puede deshacer).</p>
        <!-- NUEVO: buscador rápido dentro de la Papelera -->
        <div class="lt-buscador-modal" style="max-width:340px;">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" class="form-control form-control-sm" id="buscadorPapelera" placeholder="Buscar teléfono, usuario, equipo..." oninput="lt_filtrarTabla('tablaPapelera', this.value)">
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0" id="tablaPapelera">
                <thead class="table-light">
                    <tr>
                        <th>Teléfono</th>
                        <th>Usuario / Resguardante</th>
                        <th>Puesto</th>
                        <th>Equipo / Modelo</th>
                        <th>Estatus previo</th>
                        <th>Fecha eliminación</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($papelera_lineas as $pl): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($pl['numero_telefono'] ?? '') ?></td>
                            <td><?= htmlspecialchars($pl['usuario'] ?? '') ?></td>
                            <td><?= htmlspecialchars($pl['puesto'] ?? '') ?></td>
                            <td><?= htmlspecialchars(trim(($pl['equipo'] ?? '') . ' ' . ($pl['modelo'] ?? ''))) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($pl['estatus_linea'] ?? '') ?></span></td>
                            <td><?= !empty($pl['fecha_eliminado']) ? date('d/m/Y H:i', strtotime($pl['fecha_eliminado'])) : 'N/A' ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-action-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-success" title="Restaurar"
                                            onclick="return confirm('¿Restaurar esta línea? Volverá a aparecer en la tabla principal.') && ejecutarAccionRapida('restaurar', <?= (int)$pl['id'] ?>);">
                                        <i class="fa-solid fa-trash-arrow-up"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Eliminar en definitiva"
                                            onclick="return confirm('¿ELIMINAR EN DEFINITIVA esta línea? Esta acción SÍ borra el registro por completo y no se puede deshacer. El historial quedará archivado, pero el registro no.') && ejecutarAccionRapida('eliminar_definitivo', <?= (int)$pl['id'] ?>);">
                                        <i class="fa-solid fa-fire"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- MODAL PARA VER EL HISTORIAL -->
<div class="modal fade lt-modal" id="modalHistorial" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg" style="margin-top:80px !important;">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
            <span class="lt-modal-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <span>
                Historial de Cambios
                <small>Línea: <span id="lblHistorialTelefono">—</span></small>
            </span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="contenidoHistorial">
            <p class="text-center text-muted py-3">Cargando historial...</p>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- =========================================================
     MODAL: BITÁCORA GENERAL / REPORTE DE MOVIMIENTOS Y REASIGNACIONES
   ========================================================= -->
<div class="modal fade lt-modal" id="modalReporte" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" style="margin-top:80px !important; height: calc(100vh - 100px) !important;">
    <div class="modal-content">
      <div class="modal-header lt-header-neutral">
        <h5 class="modal-title">
            <span class="lt-modal-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
            <span>
                Bitácora General de Movimientos
                <small>Reasignaciones, altas, bajas y demás — con usuarios anteriores</small>
            </span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="GET" action="" id="formFiltrosReporte" class="row g-2 mb-3">
            <input type="hidden" name="rep_open" value="1">
            <?php foreach ($filtrosLineas as $kfl => $vfl): if ($vfl !== ''): ?>
                <input type="hidden" name="<?= htmlspecialchars($kfl) ?>" value="<?= htmlspecialchars($vfl) ?>">
            <?php endif; endforeach; ?>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Tipo de movimiento</label>
                <select name="rep_tipo" class="form-select form-select-sm auto-submit-rep">
                    <option value="">Todos los movimientos</option>
                    <?php foreach (lt_catalogoAcciones() as $keyAcc => $infoAcc): ?>
                        <option value="<?= htmlspecialchars($keyAcc) ?>" <?= $repFiltros['rep_tipo'] === $keyAcc ? 'selected' : '' ?>><?= htmlspecialchars($infoAcc[1]) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Buscar</label>
                <input type="text" name="rep_buscar" class="form-control form-control-sm" placeholder="Teléfono, usuario, notas..." value="<?= htmlspecialchars($repFiltros['rep_buscar']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Desde</label>
                <input type="date" name="rep_desde" class="form-control form-control-sm auto-submit-rep" value="<?= htmlspecialchars($repFiltros['rep_desde']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold">Hasta</label>
                <input type="date" name="rep_hasta" class="form-control form-control-sm auto-submit-rep" value="<?= htmlspecialchars($repFiltros['rep_hasta']) ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
            </div>
        </form>
        <?php if (empty($reporteMovimientos)): ?>
            <div class="alert alert-info mb-0 text-center">No hay movimientos registrados con estos filtros.</div>
        <?php else: ?>
        <div class="small text-muted mb-2">
            Mostrando <?= count($reporteMovimientos) ?> movimiento(s)<?= count($reporteMovimientos) >= 500 ? ' (límite de 500 — afina los filtros para ver el resto)' : '' ?>.
        </div>
        <div class="table-responsive" style="max-height: 55vh;">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                    <tr>
                        <th>Fecha</th>
                        <th>Teléfono</th>
                        <th>Movimiento</th>
                        <th>Usuario Anterior</th>
                        <th>Usuario Nuevo</th>
                        <th>Realizado Por</th>
                        <th>Notas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $catalogoRep = lt_catalogoAcciones(); foreach ($reporteMovimientos as $r): $tipoRep = $r['tipo_accion'] ?? 'otro'; if (!isset($catalogoRep[$tipoRep])) { $tipoRep = 'otro'; } $infoTipo = $catalogoRep[$tipoRep]; ?>
                        <tr>
                            <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($r['fecha_cambio'])) ?></td>
                            <td><?= htmlspecialchars($r['numero_telefono'] ?? '') ?></td>
                            <td><span class="lt-badge-accion lt-dot-<?= htmlspecialchars($tipoRep) ?>"><i class="<?= htmlspecialchars($infoTipo[0]) ?> me-1"></i><?= htmlspecialchars($infoTipo[1]) ?></span></td>
                            <td><?= htmlspecialchars($r['usuario_anterior'] ?? '') ?><?php if (!empty($r['puesto_anterior']) && $r['puesto_anterior'] !== 'N/A'): ?> <span class="text-muted small">(<?= htmlspecialchars($r['puesto_anterior']) ?>)</span><?php endif; ?></td>
                            <td><?= htmlspecialchars($r['usuario_nuevo'] ?? '') ?><?php if (!empty($r['puesto_nuevo']) && $r['puesto_nuevo'] !== 'N/A'): ?> <span class="text-muted small">(<?= htmlspecialchars($r['puesto_nuevo']) ?>)</span><?php endif; ?></td>
                            <td><?= htmlspecialchars($r['realizado_por'] ?? 'Sistema') ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($r['notas'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer bg-light">
        <a href="?exportar_reporte_excel=1&rep_tipo=<?= urlencode($repFiltros['rep_tipo']) ?>&rep_buscar=<?= urlencode($repFiltros['rep_buscar']) ?>&rep_desde=<?= urlencode($repFiltros['rep_desde']) ?>&rep_hasta=<?= urlencode($repFiltros['rep_hasta']) ?>" class="btn btn-outline-success">
            <i class="fa-solid fa-file-excel me-1"></i> Exportar a Excel
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- =========================================================
     MODAL: REPORTE DE ENTREGAS Y REASIGNACIONES
   ========================================================= -->
<div class="modal fade lt-modal" id="modalEntregas" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" style="margin-top:80px !important; height: calc(100vh - 100px) !important;">
    <div class="modal-content">
      <div class="modal-header lt-header-neutral">
        <h5 class="modal-title">
            <span class="lt-modal-icon"><i class="fa-solid fa-arrow-right-arrow-left"></i></span>
            <span>
                Reporte de Entregas y Reasignaciones
                <small>Quién tenía el equipo, a quién le quedó, número, fecha e IMEI</small>
            </span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="GET" action="" id="formFiltrosEntregas" class="row g-2 mb-3">
            <input type="hidden" name="ent_open" value="1">
            <?php foreach ($filtrosLineas as $kfl => $vfl): if ($vfl !== ''): ?>
                <input type="hidden" name="<?= htmlspecialchars($kfl) ?>" value="<?= htmlspecialchars($vfl) ?>">
            <?php endif; endforeach; ?>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Buscar</label>
                <input type="text" name="ent_buscar" class="form-control form-control-sm" placeholder="Teléfono, usuario, IMEI..." value="<?= htmlspecialchars($entFiltros['ent_buscar']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Desde</label>
                <input type="date" name="ent_desde" class="form-control form-control-sm auto-submit-ent" value="<?= htmlspecialchars($entFiltros['ent_desde']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Hasta</label>
                <input type="date" name="ent_hasta" class="form-control form-control-sm auto-submit-ent" value="<?= htmlspecialchars($entFiltros['ent_hasta']) ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-sm btn-primary w-100"><i class="fa-solid fa-filter"></i></button>
            </div>
        </form>
        <?php if (empty($reporteEntregas)): ?>
            <div class="alert alert-info mb-0 text-center">No hay reasignaciones ni sustituciones de equipo registradas con estos filtros.</div>
        <?php else: ?>
        <div class="small text-muted mb-2">
            Mostrando <?= count($reporteEntregas) ?> movimiento(s)<?= count($reporteEntregas) >= 500 ? ' (límite de 500 — afina los filtros)' : '' ?>.
        </div>
        <div class="table-responsive" style="max-height: 55vh;">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                    <tr>
                        <th>Fecha</th>
                        <th>Número</th>
                        <th>Movimiento</th>
                        <th>Usuario Anterior</th>
                        <th>Nuevo Responsable</th>
                        <th>IMEI</th>
                        <th>Realizado Por</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $catalogoEnt = lt_catalogoAcciones(); foreach ($reporteEntregas as $r): $tipoEnt = $r['tipo_accion'] ?? 'otro'; if (!isset($catalogoEnt[$tipoEnt])) { $tipoEnt = 'otro'; } $infoTipoEnt = $catalogoEnt[$tipoEnt]; ?>
                        <tr>
                            <td class="text-nowrap"><?= date('d/m/Y H:i', strtotime($r['fecha_cambio'])) ?></td>
                            <td><?= htmlspecialchars($r['numero_telefono'] ?? '') ?></td>
                            <td><span class="lt-badge-accion lt-dot-<?= htmlspecialchars($tipoEnt) ?>"><i class="<?= htmlspecialchars($infoTipoEnt[0]) ?> me-1"></i><?= htmlspecialchars($infoTipoEnt[1]) ?></span></td>
                            <td><?= htmlspecialchars($r['usuario_anterior'] ?? '') ?></td>
                            <td><strong><?= htmlspecialchars($r['usuario_nuevo'] ?? '') ?></strong></td>
                            <td><?= !empty($r['imei']) ? htmlspecialchars($r['imei']) : '<span class="text-muted">N/A</span>' ?></td>
                            <td><?= htmlspecialchars($r['realizado_por'] ?? 'Sistema') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer bg-light">
        <a href="?exportar_entregas_excel=1&ent_buscar=<?= urlencode($entFiltros['ent_buscar']) ?>&ent_desde=<?= urlencode($entFiltros['ent_desde']) ?>&ent_hasta=<?= urlencode($entFiltros['ent_hasta']) ?>" class="btn btn-outline-success">
            <i class="fa-solid fa-file-excel me-1"></i> Exportar a Excel
        </a>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- =========================================================
     MODAL DE CONFIRMACIÓN PARA ELIMINAR LÍNEA
   ========================================================= -->
<div class="modal fade lt-modal" id="modalConfirmarEliminar" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header lt-header-danger">
        <h5 class="modal-title">
            <span class="lt-modal-icon"><i class="fa-solid fa-trash-can"></i></span>
            <span>
                Eliminar Línea
                <small>Se puede restaurar después desde la Papelera</small>
            </span>
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p class="mb-2">Estás a punto de mandar a la Papelera la línea:</p>
        <div class="lt-usuario-actual">
            <span class="avatar" style="background:#dc2626;"><i class="fa-solid fa-sim-card"></i></span>
            <div>
                <div><strong id="elimNumero"></strong></div>
                <div class="small text-muted">Usuario / resguardante: <span id="elimUsuario"></span></div>
            </div>
        </div>
        <p class="small text-muted mb-0"><i class="fa-solid fa-circle-info me-1"></i> Ya no aparecerá en la tabla principal ni en el Excel general, pero no se borra: queda en la <strong>Papelera</strong> (botón en la parte superior) por si la necesitas de vuelta, junto con todo su historial.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" id="btnConfirmarEliminarLinea" class="btn btn-danger fw-bold"><i class="fa-solid fa-trash me-1"></i> Sí, mandar a la Papelera</button>
      </div>
    </div>
  </div>
</div>
<script>
// Filtros: los selects y fechas se auto-envían al cambiar de valor
document.querySelectorAll('#formFiltrosLineas .auto-submit-lineas').forEach(function (el) {
    el.addEventListener('change', function () {
        document.getElementById('formFiltrosLineas').submit();
    });
});
// Filtros del modal de Bitácora/Reporte: mismo comportamiento de auto-envío
document.querySelectorAll('#formFiltrosReporte .auto-submit-rep').forEach(function (el) {
    el.addEventListener('change', function () {
        document.getElementById('formFiltrosReporte').submit();
    });
});
// Filtros del modal de Reporte de Entregas/Reasignaciones: mismo patrón
document.querySelectorAll('#formFiltrosEntregas .auto-submit-ent').forEach(function (el) {
    el.addEventListener('change', function () {
        document.getElementById('formFiltrosEntregas').submit();
    });
});
<?php if (isset($_GET['rep_open'])): ?>
// Reabrir automáticamente el modal de Bitácora tras filtrar (el formulario recarga la página)
document.addEventListener("DOMContentLoaded", function () {
    const modalRepEl = document.getElementById('modalReporte');
    bootstrap.Modal.getOrCreateInstance(modalRepEl).show();
});
<?php endif; ?>
<?php if (isset($_GET['ent_open'])): ?>
// Reabrir automáticamente el modal de Reporte de Entregas tras filtrar
document.addEventListener("DOMContentLoaded", function () {
    const modalEntEl = document.getElementById('modalEntregas');
    bootstrap.Modal.getOrCreateInstance(modalEntEl).show();
});
<?php endif; ?>

// NUEVO: envío de las acciones rápidas (Papelera/Stock/Reactivar) por POST
// con token CSRF, en vez de links GET directos.
function ejecutarAccionRapida(tipo, id) {
    document.getElementById('accionRapidaTipo').value = tipo;
    document.getElementById('accionRapidaId').value = id;
    document.getElementById('formAccionRapida').submit();
    return true;
}

// NUEVO: filtro de búsqueda en vivo (sin recargar) para las tablas de los
// modales de Stock y Papelera, ocultando las filas que no coincidan.
function lt_filtrarTabla(idTabla, texto) {
    const tabla = document.getElementById(idTabla);
    if (!tabla) return;
    const filtro = texto.trim().toLowerCase();
    tabla.querySelectorAll('tbody tr').forEach(function (fila) {
        const contenido = fila.textContent.toLowerCase();
        fila.style.display = (filtro === '' || contenido.includes(filtro)) ? '' : 'none';
    });
}

function cargarHistorial(idLinea, telefono) {
    const contenedor = document.getElementById('contenidoHistorial');
    contenedor.innerHTML = '<p class="text-center text-muted py-3">Cargando historial...</p>';
    document.getElementById('lblHistorialTelefono').textContent = telefono || '—';

    const modalHistorial = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHistorial'));
    modalHistorial.show();
    fetch('?obtener_historial=' + idLinea)
        .then(response => response.text())
        .then(html => {
            contenedor.innerHTML = html;
        })
        .catch(err => {
            contenedor.innerHTML = '<div class="alert alert-danger">Error al cargar el historial.</div>';
        });
}

// Alterna entre el stock activo y la Papelera de Equipos dentro del mismo modal.
function togglePapeleraStock() {
    const activo = document.getElementById('bloqueStockActivo');
    const papelera = document.getElementById('bloquePapeleraStock');
    const btn = document.getElementById('btnTogglePapeleraStock');
    const enPapelera = papelera.style.display !== 'none';
    activo.style.display = enPapelera ? '' : 'none';
    papelera.style.display = enPapelera ? 'none' : '';
    btn.innerHTML = enPapelera
        ? '<i class="fa-solid fa-trash-can me-1"></i> Ver Papelera de Equipos'
        : '<i class="fa-solid fa-boxes-stacked me-1"></i> Ver Stock Activo';
}

// Llena y abre el modal de edición de un equipo del stock.
function abrirModalEditarStock(stk) {
    document.getElementById('edit_stock_id').value = stk.id;
    document.getElementById('edit_stock_equipo').value = stk.equipo || '';
    document.getElementById('edit_stock_modelo').value = stk.modelo || '';
    document.getElementById('edit_stock_imei').value = stk.imei || '';
    document.getElementById('edit_stock_costo').value = parseFloat(stk.costo || 0).toFixed(2);
    document.getElementById('edit_stock_notas').value = stk.notas || '';
    const selEstatus = document.getElementById('edit_stock_estatus');
    if ([...selEstatus.options].some(o => o.value === stk.estatus)) {
        selEstatus.value = stk.estatus;
    }
    bootstrap.Modal.getOrCreateInstance(document.getElementById('modalEditarStock')).show();
}
function cargarHistorialStock(idStock, etiqueta) {
    const contenedor = document.getElementById('contenidoHistorial');
    contenedor.innerHTML = '<p class="text-center text-muted py-3">Cargando historial...</p>';
    document.getElementById('lblHistorialTelefono').textContent = etiqueta || '—';

    const modalHistorial = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHistorial'));
    modalHistorial.show();
    fetch('?obtener_historial_stock=' + idStock)
        .then(response => response.text())
        .then(html => {
            contenedor.innerHTML = html;
        })
        .catch(err => {
            contenedor.innerHTML = '<div class="alert alert-danger">Error al cargar el historial.</div>';
        });
}
function formatearFechaTexto(fechaRaw) {
    if (!fechaRaw || fechaRaw === '' || fechaRaw === 'null' || fechaRaw === '0000-00-00') {
        const hoy = new Date();
        var dia = hoy.getDate();
        var mesIdx = hoy.getMonth();
        var anio = hoy.getFullYear();
    } else {
        const soloFecha = fechaRaw.trim().split(' ')[0];
        const partes = soloFecha.split('-');

        if (partes.length === 3) {
            var anio = partes[0];
            var mesIdx = parseInt(partes[1], 10) - 1;
            var dia = parseInt(partes[2], 10);
        } else {
            return fechaRaw;
        }
    }
    const meses = [
        'ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO',
        'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'
    ];
    const diaStr = dia < 10 ? '0' + dia : dia;
    return `${diaStr} DE ${meses[mesIdx]} DEL ${anio}`;
}
function abrirModalResponsiva(btn) {
    const d = btn.dataset ? btn.dataset : btn;

    const fechaTexto = formatearFechaTexto(d.fecha);

    document.querySelectorAll('.res_fecha_texto').forEach(el => {
        el.textContent = fechaTexto;
    });
    let nombreUsuario = (d.usuario && d.usuario.trim() !== '' && d.usuario.trim() !== 'N/A')
        ? d.usuario
        : "________________________________________";
    document.querySelectorAll('.res_usuario').forEach(el => el.textContent = nombreUsuario);
    document.querySelectorAll('.res_telefono').forEach(el => el.textContent = d.telefono || 'N/A');
    document.querySelectorAll('.res_google').forEach(el => el.textContent = d.google || 'N/A');
    document.querySelectorAll('.res_equipo').forEach(el => el.textContent = d.equipo || 'N/A');
    document.querySelectorAll('.res_imei').forEach(el => el.textContent = d.imei || 'N/A');
    document.querySelectorAll('.res_costo').forEach(el => el.textContent = d.costo || '0.00');
    document.querySelectorAll('.res_entrega').forEach(el => el.textContent = d.entrega || 'N/A');
    document.querySelectorAll('.res_extras').forEach(el => el.textContent = d.extras || 'N/A');
    cambiarTab('celular');
    var modalEl = document.getElementById('modalResponsiva');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
function abrirModalEdit(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_numero_telefono').value = data.numero_telefono || '';
    document.getElementById('edit_tipo_emp').value = data.tipo_emp || '';
    document.getElementById('edit_estatus_linea').value = data.estatus_linea || 'Activa';
    document.getElementById('edit_usuario').value = data.usuario || '';
    document.getElementById('edit_puesto').value = data.puesto || '';
    document.getElementById('edit_fecha_entrega').value = data.fecha_entrega || '';
    document.getElementById('edit_equipo').value = data.equipo || '';
    document.getElementById('edit_modelo').value = data.modelo || '';
    document.getElementById('edit_imei_entregado').value = data.imei_entregado || '';
    document.getElementById('edit_cuenta_google').value = data.cuenta_google || '';
    document.getElementById('edit_eq_nuevo').value = data.eq_nuevo || 'NO';
    document.getElementById('edit_resguardo').value = data.resguardo || '';
    document.getElementById('edit_costo').value = data.costo || '0.00';
    document.getElementById('edit_comentarios_entrega').value = data.comentarios_entrega || '';
    document.getElementById('edit_comentarios_extras').value = data.comentarios_extras || '';
    var modalEl = document.getElementById('modalEditar');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
function cambiarTab(tipo) {
    const btnCelular = document.getElementById('btnTabCelular');
    const btnSim = document.getElementById('btnTabSim');
    const hojaCelular = document.getElementById('hojaCelular');
    const hojaSim = document.getElementById('hojaSim');
    if (tipo === 'celular') {
        btnCelular.classList.add('active');
        btnSim.classList.remove('active');
        hojaCelular.classList.remove('ocultar-hoja');
        hojaSim.classList.add('ocultar-hoja');
    } else {
        btnSim.classList.add('active');
        btnCelular.classList.remove('active');
        hojaSim.classList.remove('ocultar-hoja');
        hojaCelular.classList.add('ocultar-hoja');
    }
}
function mostrarDetalleStock(selectEl, detalleId) {
    const detalle = document.getElementById(detalleId);
    if (!detalle) return;
    const opt = selectEl.options[selectEl.selectedIndex];
    if (!opt || !opt.value) {
        detalle.classList.add('d-none');
        detalle.innerHTML = '';
        return;
    }
    const d = opt.dataset;
    detalle.innerHTML = `
        <div><strong>Equipo:</strong> ${d.equipo || 'N/A'} ${d.modelo || ''}</div>
        <div><strong>IMEI:</strong> ${d.imei || 'N/A'}</div>
        <div><strong>Costo registrado:</strong> $${d.costo || '0.00'}</div>
        <div><strong>Último usuario / origen:</strong> ${d.origen || 'N/A'}</div>
        <div><strong>Fecha de ingreso a stock:</strong> ${d.fecha || 'N/A'}</div>
        <div><strong>Notas:</strong> ${d.notas || 'Ninguna'}</div>
    `;
    detalle.classList.remove('d-none');
}
function toggleOrigenEquipoNuevo(valor) {
    const seccionStock = document.getElementById('seccion_stock_sustitucion');
    const seccionNuevo = document.getElementById('seccion_nuevo_sustitucion');
    const stockSelect = document.getElementById('stock_id_sustitucion');
    const reqEquipo = document.getElementById('nuevo_equipo');
    const reqModelo = document.getElementById('nuevo_modelo');
    const reqImei = document.getElementById('nuevo_imei');
    if (valor === 'stock') {
        seccionStock.classList.remove('d-none');
        seccionNuevo.classList.add('d-none');
        stockSelect.required = true;
        reqEquipo.required = false;
        reqModelo.required = false;
        reqImei.required = false;
    } else {
        seccionStock.classList.add('d-none');
        seccionNuevo.classList.remove('d-none');
        stockSelect.required = false;
        reqEquipo.required = true;
        reqModelo.required = true;
        reqImei.required = true;
    }
}
function abrirModalGestion(id, telefono, usuario, puesto) {
    document.getElementById('gestion_id_linea').value = id;
    document.getElementById('lblTelefono').textContent = telefono;
    document.getElementById('lblUsuarioActual').textContent = usuario ? usuario : 'Sin asignar';
    document.getElementById('lblPuestoActual').textContent = puesto ? puesto : 'N/A';
    document.getElementById('accion_linea').value = 'reasignar';
    document.getElementById('nuevo_usuario').value = '';
    document.getElementById('nuevo_puesto').value = '';
    document.getElementById('nuevo_equipo').value = '';
    document.getElementById('nuevo_modelo').value = '';
    document.getElementById('nuevo_imei').value = '';
    document.getElementById('nuevo_costo').value = '0.00';
    document.getElementById('origen_equipo_nuevo').value = 'nuevo';
    document.getElementById('stock_id_sustitucion').value = '';
    toggleOrigenEquipoNuevo('nuevo');

    toggleFormGestion();
    var modalEl = document.getElementById('modalGestion');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
function toggleFormGestion() {
    const accion = document.getElementById('accion_linea').value;
    const secReasignar = document.getElementById('secReasignar');
    const secEquipoNuevo = document.getElementById('secEquipoNuevo');
    const secStock = document.getElementById('secStock');
    const secResguardo = document.getElementById('secResguardo');
    const secBaja = document.getElementById('secBaja');
    const btnGuardar = document.getElementById('btnGuardarGestion');

    const reqUsuario = document.getElementById('nuevo_usuario');
    const reqPuesto = document.getElementById('nuevo_puesto');
    const reqEquipo = document.getElementById('nuevo_equipo');
    const reqModelo = document.getElementById('nuevo_modelo');
    const reqImei = document.getElementById('nuevo_imei');
    secReasignar.classList.add('d-none');
    secEquipoNuevo.classList.add('d-none');
    secStock.classList.add('d-none');
    secResguardo.classList.add('d-none');
    secBaja.classList.add('d-none');
    if (accion === 'reasignar') {
        secReasignar.classList.remove('d-none');
        btnGuardar.className = 'btn btn-primary';
        btnGuardar.textContent = 'Reasignar Equipo';
        reqUsuario.required = true;
        reqPuesto.required = true;
        reqEquipo.required = false;
        reqModelo.required = false;
        reqImei.required = false;
    } else if (accion === 'equipo_nuevo') {
        secEquipoNuevo.classList.remove('d-none');
        btnGuardar.className = 'btn btn-success fw-bold';
        btnGuardar.textContent = 'Asignar Equipo Nuevo';
        reqUsuario.required = false;
        reqPuesto.required = false;
        toggleOrigenEquipoNuevo(document.getElementById('origen_equipo_nuevo').value);
    } else if (accion === 'registrar_equipo_libre') {
        secStock.classList.remove('d-none');
        btnGuardar.className = 'btn btn-warning text-dark fw-bold';
        btnGuardar.textContent = 'Enviar al Stock de Sistemas';
        reqUsuario.required = false;
        reqPuesto.required = false;
        reqEquipo.required = false;
        reqModelo.required = false;
        reqImei.required = false;
    } else if (accion === 'resguardo') {
        secResguardo.classList.remove('d-none');
        btnGuardar.className = 'btn btn-info text-dark fw-bold';
        btnGuardar.textContent = 'Guardar en Resguardo';
        reqUsuario.required = false;
        reqPuesto.required = false;
        reqEquipo.required = false;
        reqModelo.required = false;
        reqImei.required = false;
    } else if (accion === 'baja') {
        secBaja.classList.remove('d-none');
        btnGuardar.className = 'btn btn-danger';
        btnGuardar.textContent = 'Dar de Baja';
        reqUsuario.required = false;
        reqPuesto.required = false;
        reqEquipo.required = false;
        reqModelo.required = false;
        reqImei.required = false;
    }
}
function toggleOrigenCaptura(valor) {
    const seccionStock = document.getElementById('seccion_stock');
    const seccionNuevo = document.getElementById('seccion_nuevo');
    const inputEquipo = document.getElementById('input_equipo');
    const inputModelo = document.getElementById('input_modelo');
    const inputImei = document.getElementById('input_imei');
    if (valor === 'stock') {
        seccionStock.style.display = 'block';
        seccionNuevo.style.display = 'none';
        inputEquipo.required = false;
        inputModelo.required = false;
        inputImei.required = false;
    } else {
        seccionStock.style.display = 'none';
        seccionNuevo.style.display = 'flex';
        inputEquipo.required = true;
        inputModelo.required = true;
        inputImei.required = true;
    }
}
<?php if ($auto_open_row): ?>
document.addEventListener("DOMContentLoaded", function() {
    const fakeBtn = {
        dataset: {
            usuario: <?= json_encode($auto_open_row['usuario']) ?>,
            telefono: <?= json_encode($auto_open_row['numero_telefono']) ?>,
            google: <?= json_encode($auto_open_row['cuenta_google']) ?>,
            equipo: <?= json_encode($auto_open_row['equipo'] . ' / ' . $auto_open_row['modelo']) ?>,
            imei: <?= json_encode($auto_open_row['imei_entregado']) ?>,
            costo: <?= json_encode(number_format($auto_open_row['costo'], 2)) ?>,
            entrega: <?= json_encode($auto_open_row['comentarios_entrega']) ?>,
            extras: <?= json_encode($auto_open_row['comentarios_extras']) ?>
        }
    };
    abrirModalResponsiva(fakeBtn);
});
<?php endif; ?>
function descargarPlantillaCSV() {
    const columnas = [
        'numero_telefono', 'tipo_emp', 'estatus_linea', 'usuario', 'puesto',
        'fecha_entrega', 'equipo', 'modelo', 'cuenta_google', 'eq_nuevo',
        'resguardo', 'imei_entregado', 'costo', 'comentarios_entrega', 'comentarios_extras'
    ];

    const fila1 = [
        '4491234567', 'A', 'ACTIVA', 'JUAN PEREZ', 'SUPERVISOR',
        '06 DE JULIO 2026', 'SAMSUNG', 'GALAXY A14', 'juan@empresa.com', 'SI',
        'SI', '864209041234567', '4500.00', 'EQUIPO COMPLETO CON CARGADOR', 'NINGUNO'
    ];
    const contenido = [
        columnas.join(','),
        fila1.map(v => `"${v}"`).join(',')
    ].join('\n');
    const blob = new Blob(['﻿' + contenido], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', 'Plantilla_Lineas_Telefonicas.csv');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}
// NUEVO: el botón de confirmación ahora manda la eliminación por POST con
// CSRF (ejecutarAccionRapida) en vez de un link GET directo.
function confirmarEliminarLinea(id, telefono, usuario) {
    document.getElementById('elimNumero').textContent = telefono || 'N/A';
    document.getElementById('elimUsuario').textContent = usuario || 'Sin asignar';
    const btnConfirmar = document.getElementById('btnConfirmarEliminarLinea');
    btnConfirmar.onclick = function () {
        ejecutarAccionRapida('eliminar', id);
    };
    const modalEl = document.getElementById('modalConfirmarEliminar');
    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
}
</script>
</body>
</html>
