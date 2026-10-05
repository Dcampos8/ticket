<?php
    // 1. Cargar la configuración global (Ajusta la ruta si config.php está en otro nivel)
    require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
    // 4. Cargar el menú (Usando ROOT_PATH para asegurar que lo encuentre)
    include(ROOT_PATH . 'admin/menu.php'); 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Captura de Credenciales - Transportes Valadez</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', sans-serif; }
        .card-captura {
            max-width: 800px;
            margin: 50px auto;
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-top: 100px !important;
        }
        .card-header-custom {
            background: #1a1a1a;
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .form-label { font-weight: 600; font-size: 0.85rem; text-transform: uppercase; color: #555; }
        .section-title {
            border-left: 4px solid #d32f2f;
            padding-left: 10px;
            margin-bottom: 20px;
            font-weight: bold;
            color: #1a1a1a;
        }
        .contpaq-box {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="card card-captura">
        <div class="card-header card-header-custom">
            <h4 class="mb-0"><i class="fas fa-id-card me-2"></i> Registro de Entrega de Credenciales</h4>
        </div>
        <div class="card-body p-4">
            <form id="formCredenciales">
                <!-- DATOS PERSONALES -->
                <div class="section-title">Información del Empleado</div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <label class="form-label">Nombre Completo del Empleado</label>
                        <input type="text" name="nombre" class="form-control" placeholder="Ej. Juan Pérez López" required>
                    </div>
                </div>

                <!-- ACCESOS DE SISTEMA -->
                <div class="section-title">Accesos de Sistemas</div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Usuario Sistema Interno</label>
                        <input type="text" name="sistema_user" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contraseña Sistema Interno</label>
                        <input type="text" name="sistema_pass" class="form-control" required>
                    </div>
                </div>
                <div class="row mb-4">
                    <div class="col-md-12">
                        <label class="form-label">Correo Institucional Asignado</label>
                        <input type="email" name="correo" class="form-control" placeholder="usuario@transportesvaladez.com">
                    </div>
                </div>

                <!-- CONTPAQi (NÓMINAS Y CONTABILIDAD) -->
                <div class="section-title">Accesos CONTPAQi</div>
                
                <!-- CONTPAQi Nóminas -->
                <div class="contpaq-box">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="check_nominas" onchange="toggleContpaq('nominas')">
                        <label class="form-check-label fw-bold text-dark" for="check_nominas">
                            <i class="fas fa-users-gear me-1"></i> Asignar acceso a CONTPAQi Nóminas
                        </label>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Usuario Nóminas</label>
                            <input type="text" id="nominas_user" name="nominas_user" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña Nóminas</label>
                            <input type="text" id="nominas_pass" name="nominas_pass" class="form-control" disabled>
                        </div>
                    </div>
                </div>

                <!-- CONTPAQi Contabilidad -->
                <div class="contpaq-box">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="check_contabilidad" onchange="toggleContpaq('contabilidad')">
                        <label class="form-check-label fw-bold text-dark" for="check_contabilidad">
                            <i class="fas fa-calculator me-1"></i> Asignar acceso a CONTPAQi Contabilidad
                        </label>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Usuario Contabilidad</label>
                            <input type="text" id="conta_user" name="conta_user" class="form-control" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña Contabilidad</label>
                            <input type="text" id="conta_pass" name="conta_pass" class="form-control" disabled>
                        </div>
                    </div>
                </div>

                <!-- CHECADOR Y ALMACEN -->
                <div class="section-title mt-4">Checador y Almacén</div>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label class="form-label">ID Checador</label>
                        <input type="text" name="checador_user" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Código Checador</label>
                        <input type="text" name="checador_code" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">NIP Almacén</label>
                        <input type="text" name="nip_almacen" class="form-control">
                    </div>
                </div>

                <!-- EQUIPO DE COMPUTO -->
                <div class="section-title">Equipo de Cómputo (PC)</div>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label class="form-label">Usuario de Windows</label>
                        <input type="text" name="pc_user" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contraseña de Windows</label>
                        <input type="text" name="pc_pass" class="form-control">
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-danger btn-lg">
                        <i class="fas fa-print me-2"></i> Generar Formato para Imprimir
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Función para activar/desactivar inputs de CONTPAQi según el switch
function toggleContpaq(tipo) {
    if (tipo === 'nominas') {
        const active = document.getElementById('check_nominas').checked;
        const user = document.getElementById('nominas_user');
        const pass = document.getElementById('nominas_pass');
        user.disabled = !active;
        pass.disabled = !active;
        if (!active) { user.value = ''; pass.value = ''; }
        else { user.focus(); }
    } else if (tipo === 'contabilidad') {
        const active = document.getElementById('check_contabilidad').checked;
        const user = document.getElementById('conta_user');
        const pass = document.getElementById('conta_pass');
        user.disabled = !active;
        pass.disabled = !active;
        if (!active) { user.value = ''; pass.value = ''; }
        else { user.focus(); }
    }
}

document.getElementById('formCredenciales').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Obtener todos los datos del formulario (los campos 'disabled' se ignoran automáticamente)
    const formData = new FormData(this);
    const params = new URLSearchParams(formData).toString();
    
    // Abrir la ventana de impresión enviando los datos por URL
    const urlImpresion = 'formato_imprimir.php?' + params;
    window.open(urlImpresion, '_blank');
});
</script>

</body>
</html>