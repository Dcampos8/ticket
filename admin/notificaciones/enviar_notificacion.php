<?php
// Integracion retirada: el sistema ya no usa este servicio.
http_response_code(410);
header('Content-Type: text/plain; charset=utf-8');
echo 'Endpoint legado deshabilitado.';