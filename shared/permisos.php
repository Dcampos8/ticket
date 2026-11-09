<?php
// 1. Función para verificar si el usuario en sesión es Superadmin
if (!function_exists('esSuperAdmin')) {
    function esSuperAdmin() {
        return isset($_SESSION['rol']) && strtolower($_SESSION['rol']) === 'superadmin';
    }
}

// 2. Función para cargar los módulos activos desde la BD
if (!function_exists('cargarModulosSistema')) {
    function cargarModulosSistema() {
        global $conexion;
        
        // Si no existe la conexión en el entorno, se requiere el archivo
        if (!isset($conexion) || !($conexion instanceof mysqli)) {
            require_once($_SERVER['DOCUMENT_ROOT'] . '/backend/conexion.php');
        }

        $modulos = [];

        if (isset($conexion) && $conexion instanceof mysqli && !$conexion->connect_error) {
            $res = @$conexion->query("SELECT clave, label, icon, ruta FROM modulos_sistema ORDER BY id ASC");
            
            if ($res && $res->num_rows > 0) {
                while ($row = $res->fetch_assoc()) {
                    $modulos[$row['clave']] = [
                        'label' => $row['label'],
                        'icon'  => $row['icon'],
                        'ruta'  => $row['ruta']
                    ];
                }
            }
        }

        return $modulos;
    }
}

// 3. Registrar la constante global de módulos
if (!defined('MODULOS_DISPONIBLES')) {
    define('MODULOS_DISPONIBLES', cargarModulosSistema());
}

// 4. Parsea los módulos de la BD a un array limpio
if (!function_exists('parsearModulos')) {
    function parsearModulos($modulosBD) {
        // La base de datos guarda JSON, mientras que la sesión puede guardar
        // el resultado ya decodificado. Aceptar ambos formatos evita pasar
        // un array a json_decode() al comprobar permisos desde el menú.
        if (is_array($modulosBD)) {
            return array_values(array_filter(array_map(
                static fn($modulo) => is_string($modulo) ? trim($modulo) : '',
                $modulosBD
            ), static fn($modulo) => $modulo !== ''));
        }

        if (!is_string($modulosBD)) {
            return [];
        }

        if (empty($modulosBD)) {
            return [];
        }
        
        $decoded = json_decode($modulosBD, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        
        return array_map('trim', explode(',', $modulosBD));
    }
}

// 5. Convierte el array de módulos a formato JSON para guardar en BD
if (!function_exists('serializarModulos')) {
    function serializarModulos($arrayModulos) {
        if (!is_array($arrayModulos)) {
            return json_encode([]);
        }
        return json_encode(array_values($arrayModulos));
    }
}

// 6. Verifica si el usuario actual tiene acceso a un módulo específico
if (!function_exists('tieneModulo')) {
    function tieneModulo($moduloClave) {
        if (esSuperAdmin()) {
            return true;
        }
        
        $modulosUsuario = parsearModulos($_SESSION['modulos_permitidos'] ?? '');
        return in_array($moduloClave, $modulosUsuario, true);
    }
}

// 7. Redirige al usuario al inicio si no tiene acceso al módulo indicado
if (!function_exists('requerirModulo')) {
    function requerirModulo($moduloClave) {
        if (!isset($_SESSION['logueado'])) {
            header("Location: " . BASE_URL . "login.php");
            exit();
        }
        
        if (!tieneModulo($moduloClave)) {
            header("Location: " . BASE_URL . "index.php?error=acceso_denegado");
            exit();
        }
    }
}

// 8. Verifica que el usuario sea Admin o Superadmin; de lo contrario bloquea el acceso
if (!function_exists('requerirAdmin')) {
    function requerirAdmin() {
        if (!isset($_SESSION['logueado'])) {
            header("Location: " . BASE_URL . "login.php");
            exit();
        }
        
        $rol = strtolower($_SESSION['rol'] ?? '');
        if ($rol !== 'admin' && $rol !== 'superadmin') {
            header("Location: " . BASE_URL . "index.php?error=acceso_denegado");
            exit();
        }
    }
}

// 9. Helper opcional por si algún script requiere exclusivamente ser Superadmin
if (!function_exists('requerirSuperAdmin')) {
    function requerirSuperAdmin() {
        if (!isset($_SESSION['logueado'])) {
            header("Location: " . BASE_URL . "login.php");
            exit();
        }
        
        if (!esSuperAdmin()) {
            header("Location: " . BASE_URL . "index.php?error=acceso_denegado");
            exit();
        }
    }
}

if (!function_exists('esAdminOSuperior')) {
    function esAdminOSuperior() {
        $rol = strtolower($_SESSION['rol'] ?? '');
        return ($rol === 'admin' || $rol === 'superadmin');
    }
}
