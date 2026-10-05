<?php
// Aseguramos que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$userBaseUrl = defined('BASE_URL') ? BASE_URL : '/';
?>
<link rel="icon" href="<?= htmlspecialchars($userBaseUrl) ?>img/icono.png">
<link rel="stylesheet" href="<?= htmlspecialchars($userBaseUrl) ?>css/redesign.css?v=<?= time() ?>">
<style>
    .sidebar { width: 250px; min-height: 100vh; height: 100vh; position: fixed; inset: 0 auto 0 0; z-index: 1000; overflow-y: auto; padding-top: 8px; }
    .sidebar .logo-animate { display: block; width: min(78%, 190px); max-height: 70px; object-fit: contain; margin: 15px auto; }
    .sidebar .nav-link { padding: 11px 16px !important; margin: 2px 10px; width: calc(100% - 20px); font-size: .9rem; font-weight: 500; text-decoration: none; }
    .sidebar .nav-link i { flex: 0 0 22px; width: 22px; margin-right: 10px; text-align: center; }
    .sidebar .collapse .nav-link { padding-left: 24px !important; font-size: .86rem; }
    .sidebar .collapse .nav-link p { display: flex; align-items: center; margin: 0; }
    .sidebar .nav-link.text-danger { color: #f1a7aa !important; }
    .sidebar .nav-link.text-danger:hover { background: #3a2934 !important; color: #fff !important; }
    #pageContent { min-height: 100vh; margin-left: 250px; padding: 28px !important; }
    @media (max-width: 768px) {
        .d-flex:has(> .sidebar) { flex-direction: column; }
        .sidebar { width: 100%; height: auto; min-height: 0; position: relative; }
        .sidebar .logo-animate { max-height: 55px; }
        #pageContent { margin-left: 0; padding: 20px !important; }
    }
</style>

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
