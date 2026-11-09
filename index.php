<?php
// index.php
session_start();

// Si no está logueado, enviamos directo al login
if (!isset($_SESSION['logueado'])) {
    header("Location: login.php");
    exit();
}

// Redirección inmediata según el rol
if ($_SESSION['rol'] === 'admin') {
    header("Location: admin/");
} else {
    header("Location: usuario/");
}
exit();
// No necesitas HTML, ni scripts, ni Bootstrap aquí.
?>