<?php
// Este archivo se conserva a proposito como puente de compatibilidad.
// El codigo real de conexion ahora vive en /core/conexion.php (parte de la
// reorganizacion del proyecto). Se deja este puente aqui porque decenas de
// archivos en todo el sitio lo referencian con require/include relativos
// ('conexion.php', '../backend/conexion.php', etc.) y cambiar cada uno de
// esos archivos habria sido un cambio de alto riesgo para un solo beneficio
// cosmetico. No se elimina este archivo.
require_once __DIR__ . '/../core/conexion.php';
