<?php
function enviarNotificacionAdmin($usuario, $titulo) {
    $app_id = "37a5671b-f945-42f1-a788-8146c12cd3b8"; // Tu App ID OneSignal
    $rest_api_key = "os_v2_app_g6swog7zivbpdj4iqfdmclgtxdv7nnztbkhefjmyiz5xqvx2bvo4wc3rsuullve5ifpwbhoanxentniwgcxzvs5qv5627b347dihfhq"; // Sustituye por tu REST API Key

    $contenido = array(
        "en" => "$usuario creó un nuevo ticket: $titulo"
    );

    $campos = array(
        'app_id' => $app_id,
        'included_segments' => array('All'),
        'contents' => $contenido,
        'headings' => array("en" => "Nuevo Ticket Registrado"),
        'url' => 'https://transportesvaladez.com/ticket/admin/listar_tickets.php'
    );

    $campos = json_encode($campos);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $rest_api_key
    ));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HEADER, FALSE);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $campos);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);

    $respuesta = curl_exec($ch);
    curl_close($ch);
}
?>
