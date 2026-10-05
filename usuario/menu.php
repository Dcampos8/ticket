<?php
// Aseguramos que la sesión esté iniciada
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>
<link rel="icon" href="https://ticket.transportesvaladez.com/img/icono.png">
<link rel="stylesheet" href="../css/theme.css?v=<?= time() ?>">
<style>
    .sidebar {
        width: 250px;
        background: linear-gradient(180deg, #0f172a 0%, #16213e 100%);
        min-height: 100vh;
        height: 100vh;
        color: #fff;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1000;
        overflow-y: auto;
        border-right: 1px solid rgba(255, 255, 255, 0.06);
    }

    .sidebar .nav-link {
        color: rgba(255, 255, 255, 0.7) !important;
        padding: 13px 20px !important;
        display: flex;
        align-items: center;
        font-size: 0.92rem;
        font-weight: 500;
        border-radius: 10px;
        margin: 2px 10px;
        width: calc(100% - 20px);
        transition: all 0.2s ease;
    }

    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
        background: rgba(227, 30, 36, 0.14);
        color: #ffffff !important;
    }

    .sidebar .nav-link.active {
        box-shadow: inset 3px 0 0 var(--tv-red, #e31e24);
    }

    .sidebar .nav-link.active p { margin-left: 20px; }
    .sidebar .nav-link i { margin-right: 10px; width: 20px; text-align: center; }

    .sidebar .collapse .nav-link { padding-left: 20px !important; font-size: 0.87rem; }

    .sidebar-user {
        padding: 16px;
        text-align: center;
        font-weight: 600;
        font-size: 0.85rem;
        background: rgba(255, 255, 255, 0.04);
        border-top: 1px solid rgba(255, 255, 255, 0.06);
        border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        color: #e2e8f0;
    }

    .sidebar .nav-link.text-danger {
        color: #fca5a5 !important;
    }

    .sidebar .nav-link.text-danger:hover {
        background: rgba(227, 30, 36, 0.18);
        color: #fff !important;
    }

    /* Ajuste en el contenido principal para que empiece después del menú */
    #pageContent {
        margin-left: 250px; /* Igual al ancho de tu sidebar */
        padding: 20px;
    }
    /* Animación sutil para el logo */
    .logo-animate {
        transition: all 0.3s ease;
    }

    .logo-animate:hover {
        filter: drop-shadow(0 0 8px rgba(227, 30, 36, 0.45));
        transform: scale(1.05);
    }

    @media (max-width: 768px) {
        .sidebar { width: 100%; height: auto; position: relative; }
        #pageContent { margin-left: 0; }
    }
</style>

<nav class="sidebar d-flex flex-column">
    <img src="https://ticket.transportesvaladez.com/img/Camion%20Cont%20Der.png" width="80%" class="logo-animate" style="margin: 15px auto 15px auto;">
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