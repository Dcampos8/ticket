# 🎫 Sistema de Tickets

Sistema web para la gestión, seguimiento y control de incidencias técnicas y reportes administrativos. Desarrollado para optimizar la comunicación interna y el flujo de trabajo en entornos de logística y transporte.

## 🚀 Funcionalidades

*   **Levantamiento de Tickets:** Formulario dinámico para usuarios con captura de datos (Área, Tipo de Incidencia, Descripción).
*   **Gestión de Evidencia:** Soporte para carga de imágenes (`uploads/`) vinculadas directamente al reporte.
*   **Notificaciones Automáticas:** Envío de alertas por correo electrónico mediante **PHPMailer** (SMTP) al departamento de sistemas.
*   **Panel Administrativo:** Visualización y cambio de estatus de los tickets (Pendiente, En Proceso, Finalizado).
*   **Reportes en Tiempo Real:** Base de datos normalizada en MySQL para asegurar la integridad de la información.

## 🛠️ Stack Tecnológico

*   **Backend:** PHP 8.x
*   **Base de Datos:** MySQL (MariaDB)
*   **Frontend:** HTML5, CSS3 (Diseño responsivo), JavaScript (AJAX para envío de formularios).
*   **Librerías:** [PHPMailer](https://github.com/PHPMailer/PHPMailer) para la gestión de correos.

## 📋 Requisitos Previos

Para instalar este proyecto, asegúrate de tener:
*   Servidor web (Apache/Nginx).
*   PHP >= 7.4.
*   Extensión `mysqli` habilitada.
*   Acceso a un servidor SMTP (ej. Hostinger, Gmail) para el envío de correos.

## 🔧 Instalación

1.  **Clonar el repositorio:**
    ```bash
    git clone [https://github.com/TV-DEVELOP/ticket.git](https://github.com/TV-DEVELOP/ticket.git)
    ```

2.  **Configurar la Base de Datos:**
    *   Importa el archivo `.sql` (si está disponible) o crea la tabla `tickets` con los campos: `id`, `nombre`, `nombre_completo`, `area`, `tipo_ticket`, `descripcion`, `estatus`, `fecha_creacion`, `imagen`.

3.  **Configurar conexión:**
    *   Edita el archivo `conexion.php` con tus credenciales locales o de producción:
    ```php
    $conexion = new mysqli("localhost", "usuario", "password", "base_de_datos");
    ```

4.  **Configurar PHPMailer:**
    *   En el archivo de procesamiento, ajusta las credenciales SMTP:
    ```php
    $mail->Host = 'tu-servidor-smtp.com';
    $mail->Username = 'tu-correo@dominio.com';
    $mail->Password = 'tu-password';
    ```

## 📁 Estructura del Proyecto
```text
├── src/               # Código fuente (PHP)
├── uploads/           # Carpeta para almacenamiento de imágenes/evidencias
├── js/                # Scripts de frontend
├── css/               # Estilos corporativos
├── PHPMailer/         # Dependencias para envío de correos
└── conexion.php       # Configuración de BD