<?php
include '../backend/conexion.php';
$query_aviso = "SELECT * FROM avisos_sistema WHERE activo = 1 ORDER BY id DESC LIMIT 1";
$resultado_aviso = $conexion->query($query_aviso);
$aviso = ($resultado_aviso && $resultado_aviso->num_rows > 0) ? $resultado_aviso->fetch_assoc() : null;

if ($aviso): ?>
    <div class="aviso-sistema aviso-<?= htmlspecialchars($aviso['color_alerta']) ?>" 
         style="padding: 15px; margin: 10px 0; border: 1px solid #ccc; border-radius: 5px; border-left: 5px solid;">
        <strong>⚠️ <?= htmlspecialchars($aviso['titulo']) ?></strong><br>
        <p><?= nl2br(htmlspecialchars($aviso['mensaje'])) ?></p>
        <span class="fecha" style="font-size: 0.8rem; color: #666;">
            Publicado: <?= date('d/m/Y H:i', strtotime($aviso['fecha_creacion'])) ?>
        </span>
    </div>
<?php endif; ?>