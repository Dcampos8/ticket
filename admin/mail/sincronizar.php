<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirAdmin();
?><?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/config/env.php');

set_time_limit(300);
ini_set('memory_limit', '512M');

$hostname = '{imap.hostinger.com:993/imap/ssl}INBOX';
$username = env('MAIL_SISTEMAS_USER');
$password = env('MAIL_SISTEMAS_PASS');

$uploadDir = __DIR__ . '/correos_adjuntos/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0775, true);
}

$inbox = imap_open($hostname, $username, $password);

if (!$inbox) {
    die('Error IMAP: ' . imap_last_error());
}

function decodeMimeStr($string)
{
    $elements = imap_mime_header_decode($string);
    $result = '';

    foreach ($elements as $el) {
        $charset = strtoupper($el->charset);

        if ($charset != 'DEFAULT') {
            $result .= iconv($charset, 'UTF-8//IGNORE', $el->text);
        } else {
            $result .= $el->text;
        }
    }

    return $result;
}

function decodePart($data, $encoding)
{
    switch ($encoding) {
        case 3:
            return base64_decode($data);
        case 4:
            return quoted_printable_decode($data);
        default:
            return $data;
    }
}

function saveAttachment($correoId, $filename, $mime, $cid, $content, $conexion, $uploadDir)
{
    $ext = pathinfo($filename, PATHINFO_EXTENSION);

    if (!$ext) {
        $ext = 'dat';
    }

    $safeName = uniqid('mail_') . '.' . $ext;

    $path = $uploadDir . $safeName;

    file_put_contents($path, $content);

    $rutaRelativa = 'correos_adjuntos/' . $safeName;

    $stmt = $conexion->prepare("
        INSERT INTO correos_adjuntos
        (correo_id, nombre, mime, cid, ruta, tamano)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    $size = strlen($content);

    $stmt->bind_param(
        'issssi',
        $correoId,
        $filename,
        $mime,
        $cid,
        $rutaRelativa,
        $size
    );

    $stmt->execute();
    $stmt->close();

    return $rutaRelativa;
}

function processParts(
    $imap,
    $msgno,
    $structure,
    $partNumber,
    &$html,
    &$text,
    &$cidMap,
    $correoId,
    $conexion,
    $uploadDir
) {
    $mime = '';

    if (isset($structure->subtype)) {
        $mime = strtolower($structure->subtype);
    }

    if ($partNumber == '') {
        $partNumber = '1';
    }

    $data = imap_fetchbody($imap, $msgno, $partNumber);

    $data = decodePart($data, $structure->encoding ?? 0);

    $filename = '';
    $cid = '';

    if (!empty($structure->ifdparameters)) {
        foreach ($structure->dparameters as $obj) {
            $attr = strtolower($obj->attribute);

            if ($attr == 'filename') {
                $filename = decodeMimeStr($obj->value);
            }
        }
    }

    if (!empty($structure->ifparameters)) {
        foreach ($structure->parameters as $obj) {
            $attr = strtolower($obj->attribute);

            if ($attr == 'name' && empty($filename)) {
                $filename = decodeMimeStr($obj->value);
            }

            if ($attr == 'charset') {
                $charset = strtoupper($obj->value);

                if ($charset != 'UTF-8') {
                    $data = @iconv($charset, 'UTF-8//IGNORE', $data);
                }
            }
        }
    }

    if (!empty($structure->id)) {
        $cid = trim($structure->id, '<>');
    }

    $isAttachment = !empty($filename);

    if ($isAttachment) {
        $fullMime = '';

        switch ($structure->type) {
            case 0:
                $fullMime = 'text/' . $mime;
                break;
            case 1:
                $fullMime = 'multipart/' . $mime;
                break;
            case 2:
                $fullMime = 'message/' . $mime;
                break;
            case 3:
                $fullMime = 'application/' . $mime;
                break;
            case 4:
                $fullMime = 'audio/' . $mime;
                break;
            case 5:
                $fullMime = 'image/' . $mime;
                break;
            case 6:
                $fullMime = 'video/' . $mime;
                break;
            default:
                $fullMime = 'application/octet-stream';
        }

        $ruta = saveAttachment(
            $correoId,
            $filename,
            $fullMime,
            $cid,
            $data,
            $conexion,
            $uploadDir
        );

        if ($cid) {
            $cidMap[$cid] = $ruta;
        }
    }

    if ($structure->type == 0) {
        if ($mime == 'html') {
            $html .= $data;
        } else {
            $text .= $data;
        }
    }

    if (!empty($structure->parts)) {
        foreach ($structure->parts as $index => $subpart) {
            $newPart = $partNumber . '.' . ($index + 1);

            processParts(
                $imap,
                $msgno,
                $subpart,
                $newPart,
                $html,
                $text,
                $cidMap,
                $correoId,
                $conexion,
                $uploadDir
            );
        }
    }
}

$emails = imap_search($inbox, 'ALL');

if (!$emails) {
    die('No hay correos');
}

rsort($emails);

$insertados = 0;

foreach ($emails as $email_number) {

    $uid = imap_uid($inbox, $email_number);

    $check = $conexion->prepare(
        'SELECT id FROM correos_archivados WHERE uid_imap = ?'
    );

    $check->bind_param('i', $uid);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $check->close();
        continue;
    }

    $check->close();

    $overview = imap_fetch_overview($inbox, $email_number, 0)[0];

    $remitente = $overview->from ?? '';
    $destinatario = $overview->to ?? '';
    $asunto = isset($overview->subject)
        ? decodeMimeStr($overview->subject)
        : '(Sin asunto)';

    $fecha = date(
        'Y-m-d H:i:s',
        strtotime($overview->date ?? 'now')
    );

    $htmlVacio = '';
$textoVacio = '';
$cuerpoVacio = '';

$stmt = $conexion->prepare("
    INSERT INTO correos_archivados
    (uid_imap, remitente, destinatario, asunto, cuerpo, html, texto, fecha)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "isssssss",
    $uid,
    $remitente,
    $destinatario,
    $asunto,
    $cuerpoVacio,
    $htmlVacio,
    $textoVacio,
    $fecha
);

    $stmt->execute();

    $correoId = $stmt->insert_id;

    $stmt->close();

    if (!empty($structure->parts)) {

        foreach ($structure->parts as $index => $part) {

            processParts(
                $inbox,
                $email_number,
                $part,
                (string)($index + 1),
                $html,
                $text,
                $cidMap,
                $correoId,
                $conexion,
                $uploadDir
            );
        }

    } else {

        $body = imap_body($inbox, $email_number);

        $body = decodePart($body, $structure->encoding ?? 0);

        if (($structure->subtype ?? '') == 'HTML') {
            $html = $body;
        } else {
            $text = $body;
        }
    }

    if (empty($html) && !empty($text)) {
        $html = nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
    }

    foreach ($cidMap as $cid => $ruta) {
        $html = str_replace(
            'cid:' . $cid,
            $ruta,
            $html
        );
    }

    $cuerpoFinal = !empty($html) ? $html : $text;

    $up = $conexion->prepare("
        UPDATE correos_archivados
        SET cuerpo = ?, html = ?, texto = ?
        WHERE id = ?
    ");

    $up->bind_param(
        'sssi',
        $cuerpoFinal,
        $html,
        $text,
        $correoId
    );

    $up->execute();
    $up->close();

    $insertados++;

    echo '✔ ' . htmlspecialchars($asunto) . '<br>';
}

imap_close($inbox);

echo '<hr><h3>Sincronización terminada: ' . $insertados . ' correos nuevos</h3>';
?>