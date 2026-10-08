<?php
/** Helpers compartidos para acciones autenticadas de la aplicación. */

if (!function_exists('asegurarTokenCsrf')) {
    function asegurarTokenCsrf(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validarTokenCsrf')) {
    function validarTokenCsrf(): bool
    {
        $esperado = $_SESSION['csrf_token'] ?? '';
        $recibido = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($esperado) && $esperado !== ''
            && is_string($recibido) && $recibido !== ''
            && hash_equals($esperado, $recibido);
    }
}

if (!function_exists('usuarioPuedeAccederTicket')) {
    function usuarioPuedeAccederTicket(mysqli $conexion, int $ticketId): bool
    {
        if ($ticketId <= 0 || empty($_SESSION['logueado'])) {
            return false;
        }
        if (function_exists('esAdminOSuperior') && esAdminOSuperior()) {
            return !function_exists('tieneModulo') || tieneModulo('tickets');
        }
        if (($_SESSION['rol'] ?? '') !== 'usuario') {
            return false;
        }
        if (!empty($_SESSION['puede_ver_todos_tickets'])) {
            return true;
        }

        $stmt = $conexion->prepare('SELECT 1 FROM tickets WHERE id = ? AND nombre = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $usuario = (string) ($_SESSION['usuario'] ?? '');
        $stmt->bind_param('is', $ticketId, $usuario);
        $stmt->execute();
        $permitido = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $permitido;
    }
}
