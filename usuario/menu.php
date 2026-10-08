<?php
// Aseguramos que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$userBaseUrl = defined('BASE_URL') ? BASE_URL : '/';
?>
<link rel="icon" href="<?= htmlspecialchars($userBaseUrl) ?>img/icono.png">
<link rel="stylesheet" href="<?= htmlspecialchars($userBaseUrl) ?>css/redesign.css?v=<?= time() ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/usuario__menu.css?v=<?= filemtime(__DIR__ . '/../css/pages/usuario__menu.css') ?>">

<nav class="sidebar d-flex flex-column">
    <img src="<?= htmlspecialchars($userBaseUrl) ?>img/Camion%20Cont%20Der.png" class="logo-animate" alt="Transportes Valadez">
    <div class="sidebar-user">
        <i class="fa-solid fa-user-circle me-2"></i>
        <?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Usuario') ?>
    </div>
    <ul class="nav flex-column pt-2">
        <li class="nav-item"><a href="index.php" class="nav-link"><i class="fa fa-chart-line"></i> Tablero</a></li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#ticketsMenu"><i class="fa fa-ticket"></i> Tickets</a>
            <div class="collapse" id="ticketsMenu">
                <a href="#" class="nav-link" data-page="ticket/crear_ticket.php"><p><i class="fa fa-plus"></i> <label>Crear</label></p></a>
                <a href="#" class="nav-link" data-page="ticket/mis_tickets.php"><p><i class="fa fa-list"></i> <label>Mis Tickets</label></p></a>
                <a href="#" class="nav-link" data-page="ticket/ayuda.php"><p><i class="fa fa-circle-question"></i> <label>Centro de ayuda</label></p></a>
            </div>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#usuariosMenu"><i class="fa fa-user"></i> Usuarios</a>
            <div class="collapse" id="usuariosMenu">
                <a href="#" class="nav-link" data-page="usuarios/index.php"><p><i class="fa fa-info-circle"></i> <label>Informacion</label></p></a>
            </div>
        </li>
        <li class="nav-item mt-auto"><a href="../core/logout.php" class="nav-link text-danger"><i class="fa fa-sign-out"></i> Salir</a></li>
    </ul>
</nav>

<script>
$(document).on('click', '.nav-link[data-page]', function (e) {
    e.preventDefault();
    let page = $(this).data('page');
    $('.nav-link').removeClass('active');
    $(this).addClass('active');
    $('#pageContent').fadeOut(100).load(page, function(){ $(this).fadeIn(100); });
});

</script>
