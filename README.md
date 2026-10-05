# Sistema de Tickets — Transportes Valadez

Aplicación PHP para registrar y administrar tickets, evidencias y módulos administrativos. El repositorio se despliega en Hostinger para `ticket.transportesvaladez.com`.

## Estructura

- `admin/`: panel y módulos administrativos.
- `usuario/`: portal de usuarios.
- `core/`, `shared/`: conexión, autenticación, permisos y componentes compartidos.
- `backend/`: endpoints y librerías existentes.
- `config/`: carga de variables de entorno.
- `css/`, `js/`, `img/`, `fonts/`: recursos de interfaz.
- `uploads/`: archivos dinámicos cargados por usuarios; no versionar su contenido.

## Configuración

1. Usa PHP 8.x con `mysqli` y `curl` habilitados, más una base de datos MySQL/MariaDB.
2. Crea `.env` a partir de `.env.example` y configura las credenciales de base de datos y correo que realmente use la instalación.
3. Nunca subas `.env`, contraseñas, tokens ni claves al repositorio. Si una credencial estuvo en un commit compartido, revócala y genera una nueva.
4. Configura la base de datos del sistema. Este repositorio no incluye un volcado SQL completo; para instalaciones nuevas primero hay que preparar y documentar el esquema vigente.
5. Asegura que `uploads/` exista, sea escribible por PHP y no permita ejecutar scripts subidos.

## Despliegue en Hostinger

El repositorio GitHub se despliega en `public_html/ticket` desde la rama `main`. El flujo es:

1. Editar y revisar cambios localmente.
2. Crear un commit y subirlo a GitHub (`Push origin`).
3. Redistribuir desde hPanel, o usar despliegue automático si está activado.
4. Verificar el sitio y los registros de despliegue.

No vacíes manualmente la carpeta del subdominio para publicar. Mantén la configuración `.env` del servidor fuera de Git y conserva los archivos existentes de `uploads/` durante los despliegues.

## Seguridad operativa

- Usa consultas preparadas cuando los valores de una consulta provengan de una solicitud o sesión.
- Protege cada endpoint en el servidor con autenticación, autorización por rol/módulo y token CSRF en acciones que modifican datos.
- Valida tipo, tamaño y nombre de cada archivo cargado; genera nombres aleatorios y almacena los archivos sin permiso de ejecución.
- Evita imprimir datos de solicitudes, errores de base de datos o credenciales en respuestas visibles; registra errores en logs protegidos.