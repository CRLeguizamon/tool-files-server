# Reglas del Proyecto (GEMINI.md)

Este archivo establece las directrices y reglas obligatorias para el desarrollo y depuración de este proyecto.

---

## 1. Reglas de Depuración y Diagnóstico
- **PHP**: Toda prueba, verificación o diagnóstico debe realizarse mostrando salidas explícitas mediante `print_r` o registrándose en el archivo de log del proyecto (`logs/debug.log`).
- **JavaScript**: Cada paso del flujo (selección de archivos, colas de procesamiento AJAX, respuesta de cada imagen, errores) debe emitir trazas claras en la consola del navegador mediante `console.log`.
- **Archivo de log**: Todas las operaciones clave del backend (conexión a base de datos, inicio y fin de procesamiento de imágenes, excepciones) deben escribirse en `logs/debug.log` con marca de tiempo.

---

## 2. Flujo de Trabajo y Despliegue
1. **Generación Local**: El asistente prepara y valida la sintaxis de todos los archivos en el entorno de trabajo local.
2. **Subida Manual por FTP**: El usuario sube manualmente los archivos creados o actualizados a su servidor Hostinger a través de su cliente FTP/SFTP.
3. **Comprobaciones Posteriores**: Solo después de que el usuario haya confirmado la subida por FTP se procede a realizar las comprobaciones, pruebas y verificaciones en el servidor.

---

## 3. Estándares Técnicos
- **Entorno**: PHP 8.5 en Hostinger, base de datos MySQL, extensiones activas `imagick`, `gd`, `pdo_mysql`.
- **Arquitectura**: Código nativo y limpio (PHP + JS Vanilla + CSS Vanilla), sin dependencias externas pesadas.
- **Seguridad**:
  - Contraseñas protegidas mediante `password_hash()` y `password_verify()`.
  - Consultas preparadas con PDO contra inyecciones SQL.
  - Validación estricta de MIME types en el servidor (JPG, PNG, AVIF, WEBP).
  - Bloqueo de ejecución de scripts PHP dentro del directorio de `uploads/`.
