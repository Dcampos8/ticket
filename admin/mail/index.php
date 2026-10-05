<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirAdmin();
?><?php
// Cargar la configuración global y el menú
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/menu.php');

// 1. Crear tablas e infraestructura base si no existen
$conexion->query("CREATE TABLE IF NOT EXISTS correo_carpetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conexion->query("ALTER TABLE correo_carpetas ADD COLUMN IF NOT EXISTS padre_id INT DEFAULT NULL");

$conexion->query("CREATE TABLE IF NOT EXISTS correo_reglas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    direccion_filtro VARCHAR(150) DEFAULT NULL,
    palabra_clave VARCHAR(150) DEFAULT NULL,
    carpeta_id INT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conexion->query("ALTER TABLE correo_reglas MODIFY COLUMN direccion_filtro VARCHAR(150) DEFAULT NULL");
$conexion->query("ALTER TABLE correo_reglas ADD COLUMN IF NOT EXISTS palabra_clave VARCHAR(150) DEFAULT NULL");

// Verificar si existe la carpeta por defecto "Transportes Valadez"
$res_valadez_check = $conexion->query("SELECT id FROM correo_carpetas WHERE id = 1 OR nombre = 'Transportes Valadez' LIMIT 1");
if ($res_valadez_check->num_rows == 0) {
    $conexion->query("INSERT INTO correo_carpetas (id, nombre, padre_id) VALUES (1, 'Transportes Valadez', NULL)");
}

// Asegurar columnas carpeta_id y leido en correos_archivados
$conexion->query("ALTER TABLE correos_archivados ADD COLUMN IF NOT EXISTS carpeta_id INT DEFAULT 1");
$conexion->query("ALTER TABLE correos_archivados ADD COLUMN IF NOT EXISTS leido TINYINT(1) DEFAULT 0");

// Aplicar reglas automáticas configuradas por el usuario (ESTRICTAMENTE al campo remitente/De:)
$res_reglas = $conexion->query("SELECT * FROM correo_reglas");
if ($res_reglas) {
    while ($regla = $res_reglas->fetch_assoc()) {
        $filtro_dir = trim($regla['direccion_filtro']);
        $palabra = trim($regla['palabra_clave']);
        $target_carpeta = intval($regla['carpeta_id']);
        
        $condiciones_sql = [];

        if (!empty($filtro_dir)) {
            $filtro_dir_esc = $conexion->real_escape_string($filtro_dir);
            $condiciones_sql[] = "(remitente LIKE '%$filtro_dir_esc%')";
        }

        if (!empty($palabra)) {
            $palabra_esc = $conexion->real_escape_string($palabra);
            $condiciones_sql[] = "(asunto LIKE '%$palabra_esc%' OR cuerpo LIKE '%$palabra_esc%')";
        }

        if (count($condiciones_sql) > 0) {
            $where_clause = implode(" AND ", $condiciones_sql);
            $conexion->query("UPDATE correos_archivados SET carpeta_id = $target_carpeta WHERE $where_clause");
        }
    }
}

// Manejar acciones de formulario
$mensaje_alerta = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear_carpeta') {
        $nombre_carpeta = trim($_POST['nombre_carpeta']);
        $padre_id = !empty($_POST['padre_id']) ? intval($_POST['padre_id']) : NULL;
        
        if (!empty($nombre_carpeta)) {
            if ($padre_id) {
                $stmt_cf = $conexion->prepare("INSERT INTO correo_carpetas (nombre, padre_id) VALUES (?, ?)");
                $stmt_cf->bind_param("si", $nombre_carpeta, $padre_id);
            } else {
                $stmt_cf = $conexion->prepare("INSERT INTO correo_carpetas (nombre, padre_id) VALUES (?, NULL)");
                $stmt_cf->bind_param("s", $nombre_carpeta);
            }
            $stmt_cf->execute();
            $stmt_cf->close();
            $mensaje_alerta = "¡Carpeta o subcarpeta creada con éxito!";
        }
    } elseif ($accion === 'eliminar_carpeta') {
        $id_carpeta_eliminar = intval($_POST['id_carpeta']);
        
        if ($id_carpeta_eliminar > 1) {
            $stmt_mv = $conexion->prepare("UPDATE correos_archivados SET carpeta_id = 1 WHERE carpeta_id = ?");
            $stmt_mv->bind_param("i", $id_carpeta_eliminar);
            $stmt_mv->execute();
            $stmt_mv->close();

            $stmt_sub = $conexion->prepare("UPDATE correo_carpetas SET padre_id = NULL WHERE padre_id = ?");
            $stmt_sub->bind_param("i", $id_carpeta_eliminar);
            $stmt_sub->execute();
            $stmt_sub->close();

            $stmt_del_reglas = $conexion->prepare("DELETE FROM correo_reglas WHERE carpeta_id = ?");
            $stmt_del_reglas->bind_param("i", $id_carpeta_eliminar);
            $stmt_del_reglas->execute();
            $stmt_del_reglas->close();

            $stmt_del = $conexion->prepare("DELETE FROM correo_carpetas WHERE id = ?");
            $stmt_del->bind_param("i", $id_carpeta_eliminar);
            $stmt_del->execute();
            $stmt_del->close();

            $mensaje_alerta = "¡Carpeta eliminada con éxito! Sus correos fueron movidos a Transportes Valadez.";
        } else {
            $mensaje_alerta = "No se puede eliminar la carpeta principal del sistema.";
        }
    } elseif ($accion === 'eliminar_regla') {
        $id_regla_eliminar = intval($_POST['id_regla']);
        $stmt_del_rg = $conexion->prepare("DELETE FROM correo_reglas WHERE id = ?");
        $stmt_del_rg->bind_param("i", $id_regla_eliminar);
        $stmt_del_rg->execute();
        $stmt_del_rg->close();
        $mensaje_alerta = "¡Regla de filtrado eliminada correctamente!";
    } elseif ($accion === 'reformular_reglas') {
        $res_reglas_ref = $conexion->query("SELECT * FROM correo_reglas");
        if ($res_reglas_ref) {
            while ($regla = $res_reglas_ref->fetch_assoc()) {
                $filtro_dir = trim($regla['direccion_filtro']);
                $palabra = trim($regla['palabra_clave']);
                $target_carpeta = intval($regla['carpeta_id']);
                
                $condiciones_sql = [];

                if (!empty($filtro_dir)) {
                    $filtro_dir_esc = $conexion->real_escape_string($filtro_dir);
                    $condiciones_sql[] = "(remitente LIKE '%$filtro_dir_esc%')";
                }

                if (!empty($palabra)) {
                    $palabra_esc = $conexion->real_escape_string($palabra);
                    $condiciones_sql[] = "(asunto LIKE '%$palabra_esc%' OR cuerpo LIKE '%$palabra_esc%')";
                }

                if (count($condiciones_sql) > 0) {
                    $where_clause = implode(" AND ", $condiciones_sql);
                    $conexion->query("UPDATE correos_archivados SET carpeta_id = $target_carpeta WHERE $where_clause");
                }
            }
        }
        $mensaje_alerta = "¡Reglas reformuladas y aplicadas correctamente a los mensajes existentes!";
    } elseif ($accion === 'crear_regla') {
        $direccion_filtro = trim($_POST['direccion_filtro'] ?? '');
        $palabra_clave = trim($_POST['palabra_clave'] ?? '');
        $carpeta_regla = intval($_POST['carpeta_regla_id']);
        
        if (!empty($direccion_filtro) || !empty($palabra_clave)) {
            $dir_val = !empty($direccion_filtro) ? $direccion_filtro : NULL;
            $pal_val = !empty($palabra_clave) ? $palabra_clave : NULL;

            $stmt_rg = $conexion->prepare("INSERT INTO correo_reglas (direccion_filtro, palabra_clave, carpeta_id) VALUES (?, ?, ?)");
            $stmt_rg->bind_param("ssi", $dir_val, $pal_val, $carpeta_regla);
            $stmt_rg->execute();
            $stmt_rg->close();
            $mensaje_alerta = "¡Regla de filtrado guardada correctamente!";
        } else {
            $mensaje_alerta = "Error: Debes ingresar al menos una dirección o una palabra clave.";
        }
    } elseif ($accion === 'mover_correo') {
        $id_correo_mover = intval($_POST['id_correo']);
        $nueva_carpeta = intval($_POST['nueva_carpeta_id']);
        
        $stmt_mc = $conexion->prepare("UPDATE correos_archivados SET carpeta_id = ? WHERE id = ?");
        $stmt_mc->bind_param("ii", $nueva_carpeta, $id_correo_mover);
        $stmt_mc->execute();
        $stmt_mc->close();
        $mensaje_alerta = "¡Correo movido de carpeta correctamente!";
    } elseif ($accion === 'toggle_leido') {
        $id_correo_leido = intval($_POST['id_correo']);
        $nuevo_estado = intval($_POST['nuevo_estado']);
        
        $stmt_tl = $conexion->prepare("UPDATE correos_archivados SET leido = ? WHERE id = ?");
        $stmt_tl->bind_param("ii", $nuevo_estado, $id_correo_leido);
        $stmt_tl->execute();
        $stmt_tl->close();
    } elseif ($accion === 'marcar_todos_leidos') {
        $carpeta_a_marcar = intval($_POST['carpeta_id']);
        if ($carpeta_a_marcar === -999) {
            $stmt_mtl = $conexion->prepare("UPDATE correos_archivados SET leido = 1 WHERE carpeta_id = -999");
        } else {
            $stmt_mtl = $conexion->prepare("UPDATE correos_archivados SET leido = 1 WHERE carpeta_id = ?");
            $stmt_mtl->bind_param("i", $carpeta_a_marcar);
        }
        $stmt_mtl->execute();
        $stmt_mtl->close();
        $mensaje_alerta = "¡Todos los mensajes de esta carpeta han sido marcados como leídos!";
    } elseif ($accion === 'guardar_contacto') {
        $nombre = trim($_POST['nombre_contacto']);
        $correo = trim($_POST['correo_contacto']);
        $telefono = trim($_POST['telefono_contacto']);
        $empresa = trim($_POST['empresa_contacto']);

        $stmt_c = $conexion->prepare("INSERT INTO contactos (nombre, correo, telefono, empresa) VALUES (?, ?, ?, ?)");
        if ($stmt_c) {
            $stmt_c->bind_param("ssss", $nombre, $correo, $telefono, $empresa);
            $stmt_c->execute();
            $stmt_c->close();
            $mensaje_alerta = "¡Contacto guardado con éxito!";
        }
    } elseif ($accion === 'importar_contactos') {
        if (isset($_FILES['archivo_csv']) && $_FILES['archivo_csv']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['archivo_csv']['tmp_name'];
            if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                fgetcsv($handle);
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    $nombre = trim($data[0] ?? '');
                    $correo = trim($data[1] ?? '');
                    $telefono = trim($data[2] ?? '');
                    $empresa = trim($data[3] ?? '');
                    if (!empty($nombre) && !empty($correo)) {
                        $stmt_imp = $conexion->prepare("INSERT INTO contactos (nombre, correo, telefono, empresa) VALUES (?, ?, ?, ?)");
                        if ($stmt_imp) {
                            $stmt_imp->bind_param("ssss", $nombre, $correo, $telefono, $empresa);
                            $stmt_imp->execute();
                            $stmt_imp->close();
                        }
                    }
                }
                fclose($handle);
                $mensaje_alerta = "¡Contactos importados desde CSV con éxito!";
            }
        }
    } elseif ($accion === 'enviar') {
        $destinatarios = trim($_POST['destinatario']);
        $cc = trim($_POST['cc']);
        $asunto = trim($_POST['asunto']);
        $cuerpo_mensaje = trim($_POST['cuerpo_mensaje']);

        $headers = "MIME-Version: 1.0\r\nContent-type:text/html;charset=UTF-8\r\n";
        $remitente_sistema = defined('MAIL_FROM') ? MAIL_FROM : 'no-reply@' . $_SERVER['SERVER_NAME'];
        $headers .= "From: " . $remitente_sistema . "\r\n";
        if (!empty($cc)) $headers .= "Cc: " . $cc . "\r\n";

        $enviado_exitoso = @mail($destinatarios, $asunto, $cuerpo_mensaje, $headers);

        $stmt_env = $conexion->prepare("INSERT INTO correos_archivados (remitente, destinatario, asunto, cuerpo, fecha, carpeta_id, leido) VALUES (?, ?, ?, ?, NOW(), -999, 1)");
        if ($stmt_env) {
            $remitente_db = $remitente_sistema;
            $destinatario_completo = $destinatarios . (!empty($cc) ? ', ' . $cc : '');
            $stmt_env->bind_param("ssss", $remitente_db, $destinatario_completo, $asunto, $cuerpo_mensaje);
            $stmt_env->execute();
            $stmt_env->close();
        }

        if ($enviado_exitoso) {
            $mensaje_alerta = "¡Correo enviado correctamente y guardado en Elementos Enviados!";
        } else {
            $mensaje_alerta = "¡Correo registrado en 'Elementos Enviados' (Nota: la función mail() local no despacho correo externo)!";
        }
    }
}

// Obtener pestaña activa, carpeta activa y parámetro de ordenamiento
$pestana_activa = isset($_GET['tab']) ? $_GET['tab'] : 'correos';
$carpeta_activa = isset($_GET['carpeta']) ? intval($_GET['carpeta']) : 1;
$orden_param = isset($_GET['orden']) ? $_GET['orden'] : 'recientes';

// Configurar sentencia ORDER BY SQL
$order_sql = "ORDER BY fecha DESC";
if ($orden_param === 'antiguos') {
    $order_sql = "ORDER BY fecha ASC";
} elseif ($orden_param === 'remitente_asc') {
    $order_sql = "ORDER BY remitente ASC";
} elseif ($orden_param === 'remitente_desc') {
    $order_sql = "ORDER BY remitente DESC";
} elseif ($orden_param === 'asunto') {
    $order_sql = "ORDER BY asunto ASC";
}

// Obtener lista completa de carpetas y reglas
$carpetas = [];
$res_carp = $conexion->query("SELECT * FROM correo_carpetas ORDER BY padre_id ASC, id ASC");
if ($res_carp) {
    while ($rc = $res_carp->fetch_assoc()) {
        $carpetas[] = $rc;
    }
}

$reglas_activas = [];
$res_rg_list = $conexion->query("SELECT r.*, c.nombre AS carpeta_nombre FROM correo_reglas r INNER JOIN correo_carpetas c ON r.carpeta_id = c.id ORDER BY c.id ASC, r.id DESC");
if ($res_rg_list) {
    while ($rg = $res_rg_list->fetch_assoc()) {
        $reglas_activas[] = $rg;
    }
}

// Obtener correos filtrados por la carpeta seleccionada con su respectivo orden
if ($carpeta_activa === -999) {
    $stmt_correos = $conexion->prepare("SELECT * FROM correos_archivados WHERE carpeta_id = -999 $order_sql");
    $stmt_correos->execute();
    $resultado_c = $stmt_correos->get_result();
    $stmt_correos->close();
} else {
    $stmt_correos = $conexion->prepare("SELECT * FROM correos_archivados WHERE carpeta_id = ? $order_sql");
    $stmt_correos->bind_param("i", $carpeta_activa);
    $stmt_correos->execute();
    $resultado_c = $stmt_correos->get_result();
    $stmt_correos->close();
}

$correos = [];
while ($row = $resultado_c->fetch_assoc()) {
    $correos[] = $row;
}

// Obtener contactos
$contactos = [];
$res_contactos = $conexion->query("SHOW TABLES LIKE 'contactos'");
if ($res_contactos && $res_contactos->num_rows > 0) {
    $q_c = $conexion->query("SELECT * FROM contactos ORDER BY nombre ASC");
    if ($q_c) while ($row_c = $q_c->fetch_assoc()) $contactos[] = $row_c;
}

// Ver un correo específico y marcarlo automáticamente como leído al abrirlo
$correo_actual = null;
if (isset($_GET['id'])) {
    $id_correo = intval($_GET['id']);
    
    if ($carpeta_activa !== -999) {
        $conexion->query("UPDATE correos_archivados SET leido = 1 WHERE id = $id_correo");
    }

    $stmt_unico = $conexion->prepare("SELECT * FROM correos_archivados WHERE id = ?");
    $stmt_unico->bind_param("i", $id_correo);
    $stmt_unico->execute();
    $correo_actual = $stmt_unico->get_result()->fetch_assoc();
    $stmt_unico->close();
}

function obtenerConteoNoLeidos($conexion, $carpeta_id) {
    $res = $conexion->query("SELECT COUNT(*) AS total FROM correos_archivados WHERE carpeta_id = $carpeta_id AND leido = 0");
    if ($res) {
        $row = $res->fetch_assoc();
        return intval($row['total']);
    }
    return 0;
}

function renderizarArbolCarpetas($conexion, $carpetas, $padre_id = NULL, $nivel = 0, $carpeta_activa = 1, $orden_param = 'recientes') {
    $filtro = array_filter($carpetas, fn($c) => ($padre_id === NULL) ? empty($c['padre_id']) : $c['padre_id'] == $padre_id);
    foreach ($filtro as $cp) {
        $indentacionClass = ($nivel > 0) ? 'subfolder-item' : '';
        $paddingStyle = ($nivel > 0) ? 'padding-left: ' . (12 + ($nivel * 12)) . 'px !important;' : '';
        $activaClass = ($carpeta_activa == $cp['id']) ? 'active text-white' : '';
        $no_leidos = obtenerConteoNoLeidos($conexion, $cp['id']);
        
        echo '<div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 ' . $indentacionClass . ' ' . $activaClass . '" style="' . $paddingStyle . ' font-size: 0.85rem;" oncontextmenu="mostrarMenuContextual(event, ' . $cp['id'] . ', \'' . htmlspecialchars($cp['nombre'], ENT_QUOTES) . '\');">';
        echo '<a href="?tab=correos&carpeta=' . $cp['id'] . '&orden=' . $orden_param . '" class="text-reset flex-grow-1 text-truncate d-flex justify-content-between align-items-center pe-2">';
        echo '<span class="text-truncate">';
        if ($nivel == 0) {
            echo '<i class="fas fa-folder me-2 text-warning"></i>';
        } else {
            echo '<i class="fas fa-level-up-alt fa-rotate-90 me-1 text-secondary"></i>';
        }
        echo htmlspecialchars($cp['nombre']) . '</span>';
        
        if ($no_leidos > 0) {
            $badge_bg = ($carpeta_activa == $cp['id']) ? 'bg-light text-dark' : 'bg-danger text-white';
            echo '<span class="badge ' . $badge_bg . ' rounded-pill" style="font-size: 0.65rem;">' . $no_leidos . '</span>';
        }
        echo '</a>';
        
        echo '<div class="d-flex align-items-center gap-1">';
        if ($cp['id'] > 1) {
            echo '<form method="POST" onsubmit="return confirm(\'¿Seguro que deseas eliminar la carpeta ' . htmlspecialchars($cp['nombre'], ENT_QUOTES) . '?\');" style="margin:0;">';
            echo '<input type="hidden" name="accion" value="eliminar_carpeta">';
            echo '<input type="hidden" name="id_carpeta" value="' . $cp['id'] . '">';
            echo '<button type="submit" class="btn btn-sm p-0 text-danger" title="Eliminar carpeta"><i class="fas fa-trash-alt fa-xs"></i></button>';
            echo '</form>';
        }
        echo '</div>';
        echo '</div>';
        
        renderizarArbolCarpetas($conexion, $carpetas, $cp['id'], $nivel + 1, $carpeta_activa, $orden_param);
    }
}

function renderizarOpcionesSelect($carpetas, $padre_id = NULL, $nivel = 0, $seleccionado = 0) {
    $filtro = array_filter($carpetas, fn($c) => ($padre_id === NULL) ? empty($c['padre_id']) : $c['padre_id'] == $padre_id);
    foreach ($filtro as $cp) {
        $prefijo = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $nivel) . ($nivel > 0 ? '└─ ' : '');
        $selected = ($seleccionado == $cp['id']) ? 'selected' : '';
        echo '<option value="' . $cp['id'] . '" ' . $selected . '>' . $prefijo . htmlspecialchars($cp['nombre']) . '</option>';
        renderizarOpcionesSelect($carpetas, $cp['id'], $nivel + 1, $seleccionado);
    }
}

// Función robusta corregida para separar el cuerpo del mensaje y renderizar archivos adjuntos limpios
// Función para parsear, separar y extraer correctamente adjuntos MIME e imágenes incrustadas en Base64
function renderizarCuerpoYAdjuntos($cuerpo_crudo) {
    // Si contiene bloques MIME o cadenas base64
    if (strpos($cuerpo_crudo, 'Content-Type:') !== false || strpos($cuerpo_crudo, 'base64') !== false) {
        $partes = preg_split('/boundary=|Content-Type:|--\w+/', $cuerpo_crudo);
        $html_limpio = trim($partes[0]);
        
        if (empty($html_limpio)) {
            $html_limpio = "<p class='text-muted'>(Mensaje en formato alternativo o binario)</p>";
        }

        echo '<div class="correo-html-body small mb-3">' . $html_limpio . '</div>';
        
        echo '<div class="card bg-light p-2 mt-2 border"><small class="fw-bold text-muted mb-2"><i class="fas fa-paperclip me-1"></i> Archivos adjuntos y elementos detectados:</small>';
        echo '<div class="d-flex flex-wrap gap-2">';
        
        $encontrados = false;
        foreach ($partes as $idx => $p) {
            // Buscar nombres de archivo y tipos MIME de imágenes o documentos
            if ($idx > 0 && (stripos($p, 'name=') !== false || stripos($p, 'filename=') !== false || stripos($p, 'image/') !== false || stripos($p, 'application/') !== false)) {
                $encontrados = true;
                
                // Extraer el nombre del archivo si existe
                preg_match('/(?:name|filename)="([^"]+)"/i', $p, $matches);
                $nombre_archivo = $matches[1] ?? 'adjunto_' . $idx . '.dat';
                
                // Intentar detectar si es una imagen en base64 dentro del bloque
                if (stripos($p, 'base64') !== false) {
                    $bloques_base64 = explode("\n", $p);
                    $base64_data = "";
                    $capturando = false;
                    
                    foreach ($bloques_base64 as $linea) {
                        $linea_trim = trim($linea);
                        if (empty($linea_trim)) {
                            $capturando = true;
                            continue;
                        }
                        if ($capturando && !empty($linea_trim)) {
                            $base64_data .= $linea_trim;
                        }
                    }
                    
                    // Limpiar posibles espacios corruptos en la cadena base64
                    $base64_data = str_replace(' ', '+', $base64_data);
                    
                    if (!empty($base64_data) && base64_decode($base64_data, true) !== false) {
                        // Determinar tipo MIME aproximado para renderizar
                        $mime_tipo = 'application/octet-stream';
                        if (stripos($p, 'image/jpeg') !== false) $mime_tipo = 'image/jpeg';
                        elseif (stripos($p, 'image/png') !== false) $mime_tipo = 'image/png';
                        elseif (stripos($p, 'application/pdf') !== false) $mime_tipo = 'application/pdf';

                        if (strpos($mime_tipo, 'image/') === 0) {
                            // Si es imagen, mostrar miniatura interactiva
                            $src_img = 'data:' . $mime_tipo . ';base64,' . $base64_data;
                            echo '<div class="p-1 border bg-white rounded text-center shadow-sm" style="width: 110px;">';
                            echo '<a href="' . $src_img . '" target="_blank"><img src="' . $src_img . '" class="rounded img-fluid" style="height: 70px; object-fit: cover; width: 100%;"></a>';
                            echo '<div class="text-truncate small mt-1" style="font-size: 0.65rem;" title="' . htmlspecialchars($nombre_archivo) . '">' . htmlspecialchars($nombre_archivo) . '</div>';
                            echo '</div>';
                            continue;
                        }
                    }
                }

                // Insignia estándar para archivos generales (PDF, Word, etc.)
                echo '<div class="badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm">';
                echo '<i class="fas fa-file-alt text-danger fa-lg"></i>';
                echo '<div class="text-start"><div class="fw-bold text-truncate" style="max-width: 150px;">' . htmlspecialchars($nombre_archivo) . '</div><small class="text-muted" style="font-size: 0.65rem;">Archivo adjunto</small></div>';
                echo '</div>';
            }
        }

        if (!$encontrados) {
            echo '<div class="badge bg-white text-dark border p-2 d-flex align-items-center gap-2 shadow-sm">';
            echo '<i class="fas fa-file-code text-primary fa-lg"></i>';
            echo '<div class="text-start"><div class="fw-bold">estructura_multimodal.dat</div><small class="text-muted" style="font-size: 0.65rem;">Bloque de datos sin nombre</small></div>';
            echo '</div>';
        }

        echo '</div></div>';
    } else {
        echo '<div class="correo-html-body small">' . $cuerpo_crudo . '</div>';
    }
}
?>

<style>
    :root { --dark-valadez: #1e293b; --accent-valadez: #ef4444; --bg-light: #f8fafc; }
    body { background-color: var(--bg-light); font-family: 'Segoe UI', Roboto, sans-serif; margin-top: 100px; }
    a, a:hover { text-decoration: none !important; }
    .pro-card { background: white; border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); overflow: hidden; margin-bottom: 20px; }
    .pro-header { background: white; padding: 15px 20px; border-bottom: 1px solid #f1f5f9; display: flex; align-items: center; justify-content: space-between; }
    .indicator { width: 5px; height: 16px; background: var(--accent-valadez); border-radius: 10px; margin-right: 10px; }
    .correo-html-body { background: #ffffff; padding: 20px; border-radius: 10px; border: 1px solid #e2e8f0; max-height: 45vh; overflow-y: auto; word-break: break-word; }
    .subfolder-item { background-color: #fbfbfb; }
    .modal { margin-top: 100px; }
    .correo-no-leido { font-weight: bold; background-color: #f8fafc; border-left: 4px solid var(--accent-valadez); }
    
    #custom-context-menu {
        display: none;
        position: absolute;
        z-index: 1000;
        background: #fff;
        border: 1px solid #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-radius: 8px;
        padding: 5px 0;
        width: 180px;
    }
    #custom-context-menu button {
        width: 100%;
        text-align: left;
        background: none;
        border: none;
        padding: 8px 15px;
        font-size: 0.85rem;
        color: #1e293b;
    }
    #custom-context-menu button:hover {
        background: #f1f5f9;
        color: var(--accent-valadez);
    }
</style>

<!-- Menú contextual flotante -->
<div id="custom-context-menu">
    <form method="POST" id="form-menu-contextual" style="margin:0;">
        <input type="hidden" name="accion" value="marcar_todos_leidos">
        <input type="hidden" name="carpeta_id" id="context-carpeta-id" value="">
        <button type="submit"><i class="fas fa-check-double me-2"></i> Marc. todos leídos</button>
    </form>
</div>

<div class="container-fluid mt-4 px-4">
    <?php if (!empty($mensaje_alerta)): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= $mensaje_alerta; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Pestañas de Navegación -->
    <ul class="nav nav-pills mb-3 gap-2">
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 py-2 rounded-pill <?= ($pestana_activa == 'correos') ? 'bg-dark text-white shadow' : 'bg-white text-dark border'; ?>" href="?tab=correos">
                <i class="fas fa-inbox me-2"></i> Gestión de Correos
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 py-2 rounded-pill <?= ($pestana_activa == 'reglas') ? 'bg-dark text-white shadow' : 'bg-white text-dark border'; ?>" href="?tab=reglas">
                <i class="fas fa-filter me-2"></i> Reglas y Filtros
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 py-2 rounded-pill <?= ($pestana_activa == 'contactos') ? 'bg-dark text-white shadow' : 'bg-white text-dark border'; ?>" href="?tab=contactos">
                <i class="fas fa-address-book me-2"></i> Directorio de Contactos
            </a>
        </li>
    </ul>

    <?php if ($pestana_activa == 'correos'): ?>
        <!-- VISTA DE 3 COLUMNAS -->
        <div class="row">
            <!-- COLUMNA 1: ÁRBOL DE CARPETAS Y ENVIADOS -->
            <div class="col-md-3">
                <div class="pro-card">
                    <div class="pro-header py-2.5 px-3">
                        <h6 class="fw-bold m-0 text-uppercase fs-7"><i class="fas fa-folder-open me-1 text-danger"></i> Carpetas</h6>
                        <button class="btn btn-xs btn-dark py-1 px-2" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#nuevaCarpetaModal"><i class="fas fa-plus"></i> Nueva</button>
                    </div>
                    <div class="list-group list-group-flush" style="max-height: 68vh; overflow-y: auto;">
                        <?php 
                            $enviados_activa = ($carpeta_activa === -999) ? 'active text-white' : '';
                            $conteo_enviados = obtenerConteoNoLeidos($conexion, -999);
                        ?>
                        <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 <?= $enviados_activa; ?>" style="font-size: 0.85rem; border-bottom: 2px solid #f1f5f9;" oncontextmenu="mostrarMenuContextual(event, -999, 'Elementos Enviados');">
                            <a href="?tab=correos&carpeta=-999&orden=<?= $orden_param; ?>" class="text-reset flex-grow-1 text-truncate d-flex justify-content-between align-items-center pe-2">
                                <span class="text-truncate">
                                    <i class="fas fa-paper-plane me-2 text-primary"></i> Elementos Enviados
                                </span>
                                <?php if ($conteo_enviados > 0): ?>
                                    <span class="badge <?= ($carpeta_activa === -999) ? 'bg-light text-dark' : 'bg-danger text-white'; ?> rounded-pill" style="font-size: 0.65rem;"><?= $conteo_enviados; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>

                        <?php renderizarArbolCarpetas($conexion, $carpetas, NULL, 0, $carpeta_activa, $orden_param); ?>
                    </div>
                </div>
            </div>

            <!-- COLUMNA 2: LISTA DE CORREOS -->
            <div class="col-md-4">
                <div class="pro-card">
                    <div class="pro-header py-2 px-3 flex-column align-items-stretch gap-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="indicator"></div>
                                <h4 class="mb-0 fs-6"><?= ($carpeta_activa === -999) ? 'Enviados' : 'Mensajes'; ?></h4>
                            </div>
                            <div class="d-flex gap-1">
                                <?php if ($carpeta_activa !== -999): ?>
                                    <form method="POST" style="margin:0;">
                                        <input type="hidden" name="accion" value="marcar_todos_leidos">
                                        <input type="hidden" name="carpeta_id" value="<?= $carpeta_activa; ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm py-1 px-2" style="font-size: 0.75rem;" title="Marcar todos como leídos en esta carpeta">
                                            <i class="fas fa-check-double"></i> Todo leído
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-dark btn-sm py-1 px-2 fw-bold" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#sincronizarModal" onclick="iniciarSincronizacion()">
                                    <i class="fas fa-sync-alt"></i> Sincronizar
                                </button>
                            </div>
                        </div>
                        <!-- Menú selector para ordenar mensajes -->
                        <div class="d-flex align-items-center justify-content-between bg-light p-1 rounded" style="font-size: 0.75rem;">
                            <span class="text-muted fw-bold ps-1"><i class="fas fa-sort me-1"></i> Ordenar:</span>
                            <select class="form-select form-select-sm py-0 px-2" style="font-size: 0.75rem; width: auto;" onchange="location.href=this.value;">
                                <option value="?tab=correos&carpeta=<?= $carpeta_activa; ?>&orden=recientes<?= isset($_GET['id']) ? '&id='.$_GET['id'] : ''; ?>" <?= $orden_param === 'recientes' ? 'selected' : ''; ?>>Más recientes primero</option>
                                <option value="?tab=correos&carpeta=<?= $carpeta_activa; ?>&orden=antiguos<?= isset($_GET['id']) ? '&id='.$_GET['id'] : ''; ?>" <?= $orden_param === 'antiguos' ? 'selected' : ''; ?>>Más antiguos primero</option>
                                <option value="?tab=correos&carpeta=<?= $carpeta_activa; ?>&orden=remitente_asc<?= isset($_GET['id']) ? '&id='.$_GET['id'] : ''; ?>" <?= $orden_param === 'remitente_asc' ? 'selected' : ''; ?>>Remitente (A - Z)</option>
                                <option value="?tab=correos&carpeta=<?= $carpeta_activa; ?>&orden=remitente_desc<?= isset($_GET['id']) ? '&id='.$_GET['id'] : ''; ?>" <?= $orden_param === 'remitente_desc' ? 'selected' : ''; ?>>Remitente (Z - A)</option>
                                <option value="?tab=correos&carpeta=<?= $carpeta_activa; ?>&orden=asunto<?= isset($_GET['id']) ? '&id='.$_GET['id'] : ''; ?>" <?= $orden_param === 'asunto' ? 'selected' : ''; ?>>Asunto</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0" style="max-height: 62vh; overflow-y: auto;">
                        <div class="list-group list-group-flush">
                            <?php if (count($correos) > 0): ?>
                                <?php foreach ($correos as $c): 
                                    $es_seleccionado = (isset($_GET['id']) && $_GET['id'] == $c['id']);
                                    $es_no_leido = ($c['leido'] == 0 && $carpeta_activa !== -999);
                                    
                                    $clases_item = "list-group-item list-group-item-action p-3 ";
                                    if ($es_seleccionado) {
                                        $clases_item .= "active text-white";
                                    } elseif ($es_no_leido) {
                                        $clases_item .= "correo-no-leido";
                                    }
                                ?>
                                    <div class="<?= $clases_item; ?> d-flex justify-content-between align-items-center">
                                        <a href="?tab=correos&carpeta=<?= $carpeta_activa; ?>&orden=<?= $orden_param; ?>&id=<?= $c['id']; ?>" class="text-reset flex-grow-1 text-truncate" style="min-width: 0;">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1 text-truncate fs-7 <?= $es_no_leido && !$es_seleccionado ? 'fw-bold text-dark' : ''; ?>" style="max-width: 70%;">
                                                    <?= htmlspecialchars(($carpeta_activa === -999) ? 'Para: ' . $c['destinatario'] : $c['remitente']); ?>
                                                </h6>
                                                <small class="<?= $es_seleccionado ? 'text-white' : 'opacity-75'; ?>" style="font-size: 0.75rem;"><?= date('d M', strtotime($c['fecha'])); ?></small>
                                            </div>
                                            <p class="mb-1 text-truncate small <?= $es_no_leido && !$es_seleccionado ? 'fw-bold text-dark' : ''; ?>"><?= htmlspecialchars($c['asunto']); ?></p>
                                        </a>
                                        <?php if ($carpeta_activa !== -999): ?>
                                            <form method="POST" class="ms-2 mb-0">
                                                <input type="hidden" name="accion" value="toggle_leido">
                                                <input type="hidden" name="id_correo" value="<?= $c['id']; ?>">
                                                <input type="hidden" name="nuevo_estado" value="<?= $es_no_leido ? 1 : 0; ?>">
                                                <button type="submit" class="btn btn-sm p-1 <?= $es_seleccionado ? 'text-white' : ($es_no_leido ? 'text-danger' : 'text-muted'); ?>" title="<?= $es_no_leido ? 'Marcar como leído' : 'Marcar como no leído'; ?>">
                                                    <i class="fas <?= $es_no_leido ? 'fa-circle' : 'fa-check-circle'; ?> fa-xs"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="p-4 text-center text-muted">
                                    <p class="mb-0 small fw-bold">No hay correos en esta sección.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMNA 3: PANTALLA DE VISUALIZACIÓN DE MENSAJE -->
            <div class="col-md-5">
                <div class="pro-card">
                    <div class="pro-header py-2.5 px-3">
                        <div class="d-flex align-items-center">
                            <div class="indicator" style="background: #0ea5e9;"></div>
                            <h4 class="mb-0 fs-6">Visualizador</h4>
                        </div>
                        <div class="d-flex gap-1">
                            <?php if ($correo_actual): ?>
                                <?php if ($carpeta_activa !== -999): ?>
                                    <form method="POST" class="mb-0">
                                        <input type="hidden" name="accion" value="toggle_leido">
                                        <input type="hidden" name="id_correo" value="<?= $correo_actual['id']; ?>">
                                        <input type="hidden" name="nuevo_estado" value="<?= $correo_actual['leido'] == 1 ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-outline-secondary btn-sm py-1 px-2" style="font-size: 0.75rem;" title="Marcar como no leído / leído">
                                            <i class="fas fa-envelope<?= $correo_actual['leido'] == 1 ? '' : '-open'; ?>"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <button class="btn btn-outline-secondary btn-sm py-1 px-2" style="font-size: 0.75rem;" data-bs-toggle="modal" data-bs-target="#moverCorreoModal" title="Mover">
                                    <i class="fas fa-folder-arrow-right"></i>
                                </button>
                                <button class="btn btn-outline-primary btn-sm py-1 px-2" style="font-size: 0.75rem;" onclick="prefillResponder('<?= htmlspecialchars($correo_actual['remitente'], ENT_QUOTES); ?>', 'Re: <?= htmlspecialchars($correo_actual['asunto'], ENT_QUOTES); ?>')" title="Responder">
                                    <i class="fas fa-reply"></i> Responder
                                </button>
                                <button class="btn btn-outline-info btn-sm py-1 px-2 text-dark" style="font-size: 0.75rem;" onclick="prefillResponderTodos('<?= htmlspecialchars($correo_actual['remitente'], ENT_QUOTES); ?>', '<?= htmlspecialchars($correo_actual['destinatario'], ENT_QUOTES); ?>', 'Re: <?= htmlspecialchars($correo_actual['asunto'], ENT_QUOTES); ?>')" title="Responder a Todos">
                                    <i class="fas fa-reply-all"></i> Todos
                                </button>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm py-1 px-2 fw-bold" style="font-size: 0.75rem; background: var(--dark-valadez); border: none;" data-bs-toggle="modal" data-bs-target="#nuevoCorreoModal" title="Redactar">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-3" style="min-height: 68vh; max-height: 68vh; overflow-y: auto;">
                        <?php if ($correo_actual): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h5 class="fw-bold text-dark fs-6 mb-1"><?= htmlspecialchars($correo_actual['asunto']); ?></h5>
                                    <?php if ($carpeta_activa !== -999): ?>
                                        <span class="badge bg-<?= $correo_actual['leido'] == 1 ? 'secondary' : 'danger'; ?> fs-8">
                                            <?= $correo_actual['leido'] == 1 ? 'Leído' : 'No leído'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-primary fs-8">Enviado</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-1 small"><strong>De:</strong> <?= htmlspecialchars($correo_actual['remitente']); ?></p>
                                <p class="mb-1 small"><strong>Para:</strong> <?= htmlspecialchars($correo_actual['destinatario']); ?></p>
                                <p class="text-muted mb-0"><small style="font-size: 0.75rem;">Fecha: <?= $correo_actual['fecha']; ?></small></p>
                            </div>
                            
                            <!-- Visualizador optimizado integrando la función para separar cuerpo y adjuntos limpiamente -->
                            <?php renderizarCuerpoYAdjuntos($correo_actual['cuerpo']); ?>

                        <?php else: ?>
                            <div class="text-center text-muted py-5 mt-4">
                                <i class="fas fa-envelope-open-text fa-3x mb-3 text-secondary"></i>
                                <h6 class="fw-bold">Selecciona un mensaje</h6>
                                <p class="small text-muted">Haz clic en un correo del centro para ver su contenido y marcarlo como leído automáticamente.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php elseif ($pestana_activa == 'reglas'): ?>
        <!-- VISTA DE REGLAS Y FILTROS -->
        <div class="row">
            <div class="col-md-12">
                <div class="pro-card">
                    <div class="pro-header">
                        <div class="d-flex align-items-center">
                            <div class="indicator" style="background: #f59e0b;"></div>
                            <h4 class="mb-0">Reglas y Filtros Automáticos por Carpeta</h4>
                        </div>
                        <div class="d-flex gap-2">
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="accion" value="reformular_reglas">
                                <button type="submit" class="btn btn-outline-dark btn-sm fw-bold text-uppercase">
                                    <i class="fas fa-sync me-1"></i> Reformular Reglas
                                </button>
                            </form>
                            <button type="button" class="btn btn-dark btn-sm fw-bold text-uppercase" data-bs-toggle="modal" data-bs-target="#nuevaReglaModal" style="background: var(--dark-valadez); border: none;">
                                <i class="fas fa-plus-circle"></i> Nueva Regla
                            </button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-muted">Filtros organizados según la carpeta de destino (aplicando únicamente al remitente o campo De:).</p>
                        
                        <?php if (count($carpetas) > 0): ?>
                            <div class="accordion" id="accordionReglas">
                                <?php foreach ($carpetas as $index => $carp): 
                                    $reglas_de_carpeta = array_filter($reglas_activas, fn($r) => $r['carpeta_id'] == $carp['id']);
                                    $collapseId = "collapseCarpeta" . $carp['id'];
                                    $headingId = "headingCarpeta" . $carp['id'];
                                ?>
                                    <div class="accordion-item mb-3 border" style="border-radius: 12px; overflow: hidden;">
                                        <h2 class="accordion-header" id="<?= $headingId; ?>">
                                            <button class="accordion-button <?= ($index > 0) ? 'collapsed' : ''; ?> fw-bold bg-light text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId; ?>" aria-expanded="<?= ($index === 0) ? 'true' : 'false'; ?>" aria-controls="<?= $collapseId; ?>">
                                                <i class="fas fa-folder-open me-2 text-danger"></i> <?= htmlspecialchars($carp['nombre']); ?> 
                                                <span class="badge bg-dark ms-2"><?= count($reglas_de_carpeta); ?> reglas</span>
                                            </button>
                                        </h2>
                                        <div id="<?= $collapseId; ?>" class="accordion-collapse collapse <?= ($index === 0) ? 'show' : ''; ?>" aria-labelledby="<?= $headingId; ?>" data-bs-parent="#accordionReglas">
                                            <div class="accordion-body p-0">
                                                <?php if (count($reglas_de_carpeta) > 0): ?>
                                                    <table class="table table-hover align-middle mb-0">
                                                        <thead class="table-light text-secondary small">
                                                            <tr>
                                                                <th class="ps-4">Dirección o Remitente (De:)</th>
                                                                <th>Palabra Clave</th>
                                                                <th>Fecha Creación</th>
                                                                <th class="text-end pe-4">Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($reglas_de_carpeta as $rg): ?>
                                                                <tr>
                                                                    <td class="ps-4">
                                                                        <?php if (!empty($rg['direccion_filtro'])): ?>
                                                                            <span class="fw-bold text-primary"><i class="fas fa-at me-1"></i> <?= htmlspecialchars($rg['direccion_filtro']); ?></span>
                                                                        <?php else: ?>
                                                                            <span class="text-muted small">Cualquiera (N/A)</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td>
                                                                        <?php if (!empty($rg['palabra_clave'])): ?>
                                                                            <span class="badge bg-info text-dark"><i class="fas fa-key me-1"></i> <?= htmlspecialchars($rg['palabra_clave']); ?></span>
                                                                        <?php else: ?>
                                                                            <span class="text-muted small">Cualquiera (N/A)</span>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td><small class="text-muted"><?= $rg['creado_en']; ?></small></td>
                                                                    <td class="text-end pe-4">
                                                                        <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar esta regla de filtrado?');" style="margin:0; display:inline-block;">
                                                                            <input type="hidden" name="accion" value="eliminar_regla">
                                                                            <input type="hidden" name="id_regla" value="<?= $rg['id']; ?>">
                                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar regla"><i class="fas fa-trash-alt"></i></button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                <?php else: ?>
                                                    <div class="p-3 text-center text-muted small">
                                                        No hay reglas automáticas asignadas a esta carpeta.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">No hay carpetas creadas.</div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- VISTA DE CONTACTOS -->
        <div class="row">
            <div class="col-md-12">
                <div class="pro-card">
                    <div class="pro-header">
                        <div class="d-flex align-items-center"><div class="indicator" style="background: #10b981;"></div><h4 class="mb-0">Directorio de Contactos</h4></div>
                        <div>
                            <button type="button" class="btn btn-outline-secondary btn-sm fw-bold text-uppercase me-2" data-bs-toggle="modal" data-bs-target="#importarContactoModal"><i class="fas fa-file-csv"></i> Importar CSV</button>
                            <button type="button" class="btn btn-dark btn-sm fw-bold text-uppercase" data-bs-toggle="modal" data-bs-target="#nuevoContactoModal" style="background: var(--dark-valadez); border: none;"><i class="fas fa-user-plus"></i> Nuevo Contacto</button>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <table class="table table-hover align-middle">
                            <thead class="table-light"><tr><th>Nombre</th><th>Correo</th><th>Teléfono</th><th>Empresa</th><th>Acciones</th></tr></thead>
                            <tbody>
                                <?php foreach ($contactos as $ct): ?>
                                    <tr>
                                        <td class="fw-bold"><?= htmlspecialchars($ct['nombre']); ?></td>
                                        <td><?= htmlspecialchars($ct['correo']); ?></td>
                                        <td><?= htmlspecialchars($ct['telefono']); ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($ct['empresa']); ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-dark" onclick="prefillEmail('<?= htmlspecialchars($ct['correo'], ENT_QUOTES); ?>')" data-bs-toggle="modal" data-bs-target="#nuevoCorreoModal" title="Enviar correo">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal para Crear Carpeta o Subcarpeta -->
<div class="modal fade" id="nuevaCarpetaModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
      <form method="POST">
          <input type="hidden" name="accion" value="crear_carpeta">
          <div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Crear Carpeta / Subcarpeta</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
          <div class="modal-body p-4">
              <div class="mb-3">
                  <label class="form-label">Carpeta o Subcarpeta Padre (Opcional):</label>
                  <select name="padre_id" class="form-select">
                      <option value="">-- Ninguna (Carpeta Principal) --</option>
                      <?php renderizarOpcionesSelect($carpetas, NULL, 0, 0); ?>
                  </select>
              </div>
              <div class="mb-3">
                  <label class="form-label">Nombre:</label>
                  <input type="text" name="nombre_carpeta" class="form-control" required placeholder="Ej. Reclutamiento, Nómina...">
              </div>
          </div>
          <div class="modal-footer bg-light"><button type="submit" class="btn btn-dark fw-bold" style="background: var(--dark-valadez);">Guardar</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Modal para Nueva Regla -->
<div class="modal fade" id="nuevaReglaModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
      <form method="POST">
          <input type="hidden" name="accion" value="crear_regla">
          <div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Nueva Regla Automática</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
          <div class="modal-body p-4">
              <p class="text-muted small">El filtro por dirección de correo o dominio evaluará exclusivamente el campo <strong>De: (remitente)</strong>.</p>
              <div class="mb-3">
                  <label class="form-label">Dirección o dominio del Remitente (Opcional):</label>
                  <input type="text" name="direccion_filtro" class="form-control" placeholder="Ej. @transportesvaladez">
              </div>
              <div class="mb-3">
                  <label class="form-label">Palabra clave en Asunto o Cuerpo (Opcional):</label>
                  <input type="text" name="palabra_clave" class="form-control" placeholder="Ej. Factura, Urgente">
              </div>
              <div class="mb-3">
                  <label class="form-label">Carpeta de Destino:</label>
                  <select name="carpeta_regla_id" class="form-select" required>
                      <?php renderizarOpcionesSelect($carpetas, NULL, 0, 0); ?>
                  </select>
              </div>
          </div>
          <div class="modal-footer bg-light"><button type="submit" class="btn btn-dark fw-bold" style="background: var(--dark-valadez);">Guardar Regla</button></div>
      </form>
    </div>
  </div>
</div>

<!-- Modal para Mover Correo -->
<?php if ($correo_actual): ?>
<div class="modal fade" id="moverCorreoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
      <form method="POST">
          <input type="hidden" name="accion" value="mover_correo">
          <input type="hidden" name="id_correo" value="<?= $correo_actual['id']; ?>">
          <div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Mover Correo a otra Carpeta</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
          <div class="modal-body p-4">
              <div class="mb-3">
                  <label class="form-label">Selecciona la carpeta de destino:</label>
                  <select name="nueva_carpeta_id" class="form-select" required>
                      <?php renderizarOpcionesSelect($carpetas, NULL, 0, $correo_actual['carpeta_id']); ?>
                  </select>
              </div>
          </div>
          <div class="modal-footer bg-light"><button type="submit" class="btn btn-dark fw-bold" style="background: var(--dark-valadez);">Mover Mensaje</button></div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modales de Sincronizar, Redactar y Contactos -->
<div class="modal fade" id="sincronizarModal" data-bs-backdrop="static" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg" style="border-radius: 15px;"><div class="modal-body text-center p-5">
      <div id="sync-spinner"><div class="spinner-border text-danger mb-3" style="width: 3rem; height: 3rem;" role="status"></div><h4 class="fw-bold text-dark">Sincronizando correos...</h4></div>
      <div id="sync-resultado" style="display: none;"><i class="fas fa-check-circle text-success fa-3x mb-3"></i><h4 class="fw-bold text-dark">¡Sincronización Terminada!</h4><button type="button" class="btn btn-dark fw-bold px-4" onclick="location.reload();">Aceptar</button></div>
      <iframe id="sync-iframe" src="" style="display:none;" onload="terminoSync();"></iframe>
  </div></div></div>
</div>

<div class="modal fade" id="nuevoCorreoModal" tabindex="-1">
  <div class="modal-dialog modal-lg"><div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
      <form method="POST"><input type="hidden" name="accion" value="enviar">
          <div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Redactar Mensaje</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
          <div class="modal-body p-4">
              <div class="mb-3"><label class="form-label">Destinatario:</label><input type="text" id="input_destinatario" name="destinatario" class="form-control" required placeholder="correo@ejemplo.com"></div>
              <div class="mb-3"><label class="form-label">CC (Opcional):</label><input type="text" id="input_cc" name="cc" class="form-control" placeholder="copia@ejemplo.com"></div>
              <div class="mb-3"><label class="form-label">Asunto:</label><input type="text" id="input_asunto" name="asunto" class="form-control" required placeholder="Asunto"></div>
              <div class="mb-3"><label class="form-label">Mensaje:</label><textarea id="input_cuerpo" name="cuerpo_mensaje" class="form-control" rows="6" required></textarea></div>
          </div>
          <div class="modal-footer bg-light"><button type="submit" class="btn btn-dark fw-bold" style="background: var(--dark-valadez);">Enviar Mensaje</button></div>
      </form>
  </div></div>
</div>

<div class="modal fade" id="nuevoContactoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
      <form method="POST">
          <input type="hidden" name="accion" value="guardar_contacto">
          <div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Registrar Contacto</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
          <div class="modal-body p-4">
              <div class="mb-3"><label class="form-label">Nombre Completo:</label><input type="text" name="nombre_contacto" class="form-control" required placeholder="Ej. Juan Pérez"></div>
              <div class="mb-3"><label class="form-label">Correo Electrónico:</label><input type="email" name="correo_contacto" class="form-control" required placeholder="correo@empresa.com"></div>
              <div class="mb-3"><label class="form-label">Teléfono:</label><input type="text" name="telefono_contacto" class="form-control" placeholder="Ej. 4491234567"></div>
              <div class="mb-3"><label class="form-label">Empresa / Área:</label><input type="text" name="empresa_contacto" class="form-control" placeholder="Ej. Transportes Valadez"></div>
          </div>
          <div class="modal-footer bg-light"><button type="submit" class="btn btn-dark fw-bold" style="background: var(--dark-valadez);">Guardar Contacto</button></div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="importarContactoModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 15px;">
      <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="accion" value="importar_contactos">
          <div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Importar Contactos desde CSV</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
          <div class="modal-body p-4">
              <p class="text-muted">Sube un archivo <strong>.CSV</strong> con las columnas: <em>Nombre, Correo, Teléfono, Empresa</em>.</p>
              <div class="mb-3"><input type="file" name="archivo_csv" class="form-control" accept=".csv" required></div>
              <div class="alert alert-info py-2 small mb-0"><a href="#" onclick="descargarPlantilla(event)" class="fw-bold text-decoration-underline">Descargar plantilla de ejemplo aquí</a>.</div>
          </div>
          <div class="modal-footer bg-light"><button type="submit" class="btn btn-dark fw-bold" style="background: var(--dark-valadez);">Importar</button></div>
      </form>
    </div>
  </div>
</div>

<script>
function iniciarSincronizacion() {
    document.getElementById('sync-spinner').style.display = 'block';
    document.getElementById('sync-resultado').style.display = 'none';
    document.getElementById('sync-iframe').src = 'sincronizar.php';
}

function terminoSync() {
    var iframeSrc = document.getElementById('sync-iframe').src;
    if (iframeSrc !== "" && iframeSrc.indexOf('sincronizar.php') !== -1) {
        setTimeout(function() {
            document.getElementById('sync-spinner').style.display = 'none';
            document.getElementById('sync-resultado').style.display = 'block';
        }, 1000);
    }
}

function prefillEmail(email) {
    document.getElementById('input_destinatario').value = email;
    document.getElementById('input_cc').value = '';
    document.getElementById('input_asunto').value = '';
    document.getElementById('input_cuerpo').value = '';
}

function prefillResponder(remitente, asunto) {
    document.getElementById('input_destinatario').value = remitente;
    document.getElementById('input_cc').value = '';
    document.getElementById('input_asunto').value = asunto;
    document.getElementById('input_cuerpo').value = "\n\n--- Respondiendo al mensaje anterior ---\n";
    var myModal = new bootstrap.Modal(document.getElementById('nuevoCorreoModal'));
    myModal.show();
}

function prefillResponderTodos(remitente, destinatarioOriginal, asunto) {
    document.getElementById('input_destinatario').value = remitente;
    document.getElementById('input_cc').value = destinatarioOriginal;
    document.getElementById('input_asunto').value = asunto;
    document.getElementById('input_cuerpo').value = "\n\n--- Respondiendo a todos en el mensaje anterior ---\n";
    var myModal = new bootstrap.Modal(document.getElementById('nuevoCorreoModal'));
    myModal.show();
}

function descargarPlantilla(e) {
    e.preventDefault();
    const csvContent = "data:text/csv;charset=utf-8,Nombre,Correo,Telefono,Empresa\nJuan Perez,juan.perez@ejemplo.com,4491234567,Transportes Valadez\nMaria Gomez,maria.gomez@ejemplo.com,4499876543,General";
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "plantilla_contactos.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Lógica de Menú Contextual (Clic derecho en carpetas)
function mostrarMenuContextual(e, carpetaId, nombreCarpeta) {
    e.preventDefault();
    const menu = document.getElementById('custom-context-menu');
    document.getElementById('context-carpeta-id').value = carpetaId;
    
    menu.style.display = 'block';
    menu.style.left = e.pageX + 'px';
    menu.style.top = e.pageY + 'px';
}

window.addEventListener('click', function() {
    const menu = document.getElementById('custom-context-menu');
    if (menu) {
        menu.style.display = 'none';
    }
});
</script>