<?php
$conexion = new mysqli("31.170.161.103", "u568802486_admintk", "Tvaladez1234*", "u568802486_tickets");
if ($conexion->connect_error) {
    die("Conexión fallida: " . $conexion->connect_error);
}

$usuario = 'user';
$contrasena = password_hash('user123', PASSWORD_DEFAULT);
$nombre = 'Usuario';

$stmt = $conexion->prepare("INSERT INTO usuarios (usuario, contrasena, nombre_completo) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $usuario, $contrasena, $nombre);
$stmt->execute();
$stmt->close();
$conexion->close();

echo "Usuario creado.";
?>