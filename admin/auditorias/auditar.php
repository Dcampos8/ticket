<?php

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
include ($_SERVER['DOCUMENT_ROOT'] .'/admin/menu.php'); 

if(!isset($_GET['id'])){
    header("Location: auditorias.php");
    exit;
}

$id = intval($_GET['id']);

/* ================= OBTENER DATOS DE LA AUDITORÍA (TU LÓGICA ORIGINAL) ================= */
$datos = $conexion->query("
SELECT 
    ap.id,
    ap.area,
    ap.fecha_programada,
    ap.estado,
    u.nombre_completo AS responsable_nombre
FROM auditorias_programadas ap
LEFT JOIN plan_mantenimiento pm ON ap.area = pm.area
LEFT JOIN usuarios u ON pm.responsable = u.id
WHERE ap.id = '$id'
LIMIT 1
")->fetch_assoc();

/* ================= GUARDAR AUDITORÍA ================= */
if(isset($_POST['guardar'])){
    $conexion->query("
    INSERT INTO auditorias_resultados 
    (id_auditoria, software_autorizado, sistema_actualizado,
    antivirus_instalado, antivirus_actualizado, firewall_activo,
    gestion_archivos, observaciones, revisado_por, fecha_revision)
    VALUES (
    '$id',
    '".(isset($_POST['software_autorizado'])?1:0)."',
    '".(isset($_POST['sistema_actualizado'])?1:0)."',
    '".(isset($_POST['antivirus_instalado'])?1:0)."',
    '".(isset($_POST['antivirus_actualizado'])?1:0)."',
    '".(isset($_POST['firewall_activo'])?1:0)."',
    '".(isset($_POST['gestion_archivos'])?1:0)."',
    '".$conexion->real_escape_string($_POST['observaciones'])."',
    '".$conexion->real_escape_string($_POST['revisado_por'])."',
    NOW()
    )
    ");

    $conexion->query("UPDATE auditorias_programadas SET estado='Realizada' WHERE id='$id'");

    echo "<script>
        alert('Auditoría guardada correctamente');
        window.location='auditorias.php';
    </script>";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Checklist Auditoría | Solutek</title>
    <style>
        body { background-color: #f4f7f6; font-family: 'Segoe UI', sans-serif; }
        .audit-card {
            max-width: 700px;
            margin: 100px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            overflow: hidden;
            border-top: 5px solid #e31e24;
        }
        .audit-header { background: #1e3a8a; color: #fff; padding: 20px; }
        .audit-header h2 { margin: 0; font-size: 1.3rem; text-transform: uppercase; }
        
        .info-box { padding: 20px; background: #f8fafc; border-bottom: 1px solid #eee; }
        .info-box p { margin: 5px 0; color: #475569; font-size: 0.95rem; }
        .info-box b { color: #1e3a8a; }

        .checklist-form { padding: 25px; }
        
        /* Estilo de los checks */
        .check-item {
            display: block;
            background: #fff;
            border: 1px solid #e2e8f0;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: 0.2s;
        }
        .check-item:hover { background: #f0f7ff; border-color: #3b82f6; }
        .check-item input { margin-right: 12px; transform: scale(1.2); }

        textarea {
            width: 100%;
            height: 100px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 12px;
            margin: 15px 0;
            box-sizing: border-box;
        }

        .input-rev {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            box-sizing: border-box;
            margin-bottom: 20px;
        }

        .btn-save {
            background: #1e3a8a;
            color: white;
            border: none;
            width: 100%;
            padding: 15px;
            border-radius: 8px;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
        }
        .btn-save:hover { background: #e31e24; }
    </style>
</head>
<body>

<div class="audit-card">
    <div class="audit-header">
        <h2>Checklist de Auditoría</h2>
    </div>

    <div class="info-box">
        <p><b>Área:</b> <?= htmlspecialchars($datos['area']) ?></p>
        <p><b>Responsable:</b> <?= htmlspecialchars($datos['responsable_nombre'] ?? 'Sin asignar') ?></p>
        <p><b>Fecha Programada:</b> <?= $datos['fecha_programada'] ?></p>
    </div>

    <form method="POST" class="checklist-form">
        
        <label class="check-item">
            <input type="checkbox" name="software_autorizado"> Software autorizado
        </label>
        <label class="check-item">
            <input type="checkbox" name="sistema_actualizado"> Sistema actualizado
        </label>
        <label class="check-item">
            <input type="checkbox" name="antivirus_instalado"> Antivirus instalado
        </label>
        <label class="check-item">
            <input type="checkbox" name="antivirus_actualizado"> Antivirus actualizado
        </label>
        <label class="check-item">
            <input type="checkbox" name="firewall_activo"> Firewall activo
        </label>
        <label class="check-item">
            <input type="checkbox" name="gestion_archivos"> Gestión correcta de archivos
        </label>

        <textarea name="observaciones" placeholder="Escribe aquí las observaciones..."></textarea>

        <input type="text" name="revisado_por" class="input-rev" placeholder="Nombre de quien revisa" required>

        <button type="submit" name="guardar" class="btn-save">Guardar Auditoría</button>
    </form>
</div>

</body>
</html>