<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Transportes Valadez</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- 1. ICONO -->
    <link rel="icon" href="<?= BASE_URL ?>img/icono.png">

    <!-- 2. ESTILOS EXTERNOS (CDN) -->
    <!-- FontAwesome (Solo una vez) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Bootstrap 5 (El estándar para tu inventario) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables y FullCalendar -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- 3. ESTILOS PROPIOS (Con BASE_URL y anti-caché) -->
    <!-- Tema global del rediseño: se carga primero para que las páginas
         puedan sobrescribir puntualmente si lo necesitan -->
    <link rel="stylesheet" href="<?= BASE_URL ?>css/theme.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/menu.css?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>css/redesign.css?v=<?= time() ?>">

</head>
<body class="admin-shell">
