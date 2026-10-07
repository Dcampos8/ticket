# API para crear tickets desde la intranet

Endpoint de producción:

```text
POST https://ticket.transportesvaladez.com/api/crear_ticket_intranet.php
Content-Type: application/json
X-Ticket-Timestamp: <Unix timestamp en segundos>
X-Ticket-Request-Id: <32 caracteres hexadecimales aleatorios>
X-Ticket-Signature: <HMAC SHA-256 hexadecimal>
```

El cuerpo JSON debe incluir los datos de la persona autenticada por la intranet:

```json
{
  "usuario": "usuario.ad",
  "nombre": "Nombre Apellido",
  "area": "Operaciones",
  "descripcion": "Descripción del problema o solicitud"
}
```

La firma se calcula en el **backend de la intranet** con el secreto compartido `INTRANET_TICKET_API_SECRET`:

```php
$timestamp = (string) time();
$requestId = bin2hex(random_bytes(16));
$body = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$signature = hash_hmac('sha256', $timestamp . "\n" . $requestId . "\n" . $body, $secretoCompartido);
```

Enviar `$body` sin modificarlo, junto con los tres encabezados. El servidor acepta firmas con una antigüedad máxima de 60 segundos y rechaza un `requestId` ya usado. El secreto debe tener al menos 32 caracteres aleatorios, guardarse en el `.env` de ambos servidores y nunca incluirse en JavaScript, HTML, Git ni en el navegador.

El endpoint ignora categorías enviadas por quien llama: reutiliza el procesador actual, que clasifica con IA, inserta el ticket y manda las notificaciones. No admite archivos adjuntos por esta API.

Respuestas: `200` con `ticketId` si se creó el ticket; `401` si falla la firma; `409` si se repite un `requestId`; `422` si faltan campos; `503` si falta configuración o falla la clasificación con IA. Para cada intento nuevo, generar otro `requestId`, timestamp y firma.
