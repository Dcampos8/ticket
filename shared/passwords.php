<?php
/** Normaliza contraseñas para que el login no distinga mayúsculas. */
if (!function_exists('normalizarContrasena')) {
    function normalizarContrasena(string $contrasena): string
    {
        return function_exists('mb_strtolower')
            ? mb_strtolower($contrasena, 'UTF-8')
            : strtolower($contrasena);
    }
}
