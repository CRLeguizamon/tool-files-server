<?php
/**
 * Configuración General del Sistema
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/logger.php';

// Zona horaria
date_default_timezone_set('America/Bogota');

// Título de la aplicación
define('APP_NAME', 'Image Optimizer');
define('APP_VERSION', '1.0.7');

// Rutas del servidor
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_PATH . '/uploads');
define('THUMB_DIR', ROOT_PATH . '/uploads/thumbs');
define('TEMP_ZIP_DIR', ROOT_PATH . '/uploads/temp_zip');
define('LOGS_DIR', ROOT_PATH . '/logs');

// Parámetros de procesamiento de imágenes
define('DEFAULT_MAX_WIDTH', 1920);
define('MAX_ALLOWED_WIDTH', 2048);
define('MIN_ALLOWED_WIDTH', 50);
define('DEFAULT_COMPRESS_QUALITY', 76); // Calidad de compresión optimizada (< 1 MB garantizado)

// Formatos permitidos
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'avif']);
define('ALLOWED_MIMES', [
    'image/jpeg',
    'image/pjpeg',
    'image/png',
    'image/x-png',
    'image/webp',
    'image/avif',
]);

// Límite de subida individual (25 MB)
define('MAX_UPLOAD_SIZE_BYTES', 25 * 1024 * 1024);

// Detección dinámica de la URL base
if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $protocol = $isHttps ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        // Directorio relativo
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
        
        // Si estamos dentro de /api o subcarpeta, retrocedemos
        if (str_ends_with($baseDir, '/api')) {
            $baseDir = substr($baseDir, 0, -4);
        }

        $url = $protocol . $host . $baseDir;
        if ($path !== '') {
            $url = rtrim($url, '/') . '/' . ltrim($path, '/');
        }
        return $url;
    }
}
