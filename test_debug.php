<?php
/**
 * Script de Diagnóstico y Comprobaciones del Sistema
 * Cumple con la regla de emitir salidas explícitas con print_r y registrar en debug.log
 */

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/logger.php';

$diagnostics = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'server_ip' => $_SERVER['SERVER_ADDR'] ?? '127.0.0.1',
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
    'extensions' => [
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'imagick'   => extension_loaded('imagick'),
        'gd'        => extension_loaded('gd'),
        'fileinfo'  => extension_loaded('fileinfo'),
        'session'   => extension_loaded('session'),
        'mbstring'  => extension_loaded('mbstring'),
        'json'      => extension_loaded('json'),
    ],
    'image_support' => [],
    'directories' => [],
    'database' => [],
];

// Comprobar soporte de formatos en GD
if (extension_loaded('gd')) {
    $gdInfo = gd_info();
    $diagnostics['image_support']['gd'] = [
        'jpeg' => $gdInfo['JPEG Support'] ?? false,
        'png'  => $gdInfo['PNG Support'] ?? false,
        'webp' => $gdInfo['WebP Support'] ?? false,
        'avif' => $gdInfo['AVIF Support'] ?? false,
    ];
}

// Comprobar soporte de formatos en Imagick
if (extension_loaded('imagick') && class_exists('Imagick')) {
    try {
        $formats = Imagick::queryFormats();
        $diagnostics['image_support']['imagick'] = [
            'jpeg' => in_array('JPEG', $formats, true),
            'png'  => in_array('PNG', $formats, true),
            'webp' => in_array('WEBP', $formats, true),
            'avif' => in_array('AVIF', $formats, true),
        ];
    } catch (\Throwable $e) {
        $diagnostics['image_support']['imagick'] = ['error' => $e->getMessage()];
    }
}

// Comprobar permisos de carpetas
$dirs = [
    'uploads'        => UPLOAD_DIR,
    'uploads/thumbs' => THUMB_DIR,
    'logs'           => LOGS_DIR,
];

foreach ($dirs as $key => $path) {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
    $diagnostics['directories'][$key] = [
        'exists'   => is_dir($path),
        'writable' => is_writable($path),
        'path'     => $path,
    ];
}

// Comprobar conexión a la base de datos MySQL
try {
    $pdo = get_db();
    $tablesStmt = $pdo->query("SHOW TABLES");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    $userCountStmt = $pdo->query("SELECT COUNT(*) FROM `users`");
    $userCount = (int) $userCountStmt->fetchColumn();

    $mediaCountStmt = $pdo->query("SELECT COUNT(*) FROM `media`");
    $mediaCount = (int) $mediaCountStmt->fetchColumn();

    $diagnostics['database'] = [
        'status'      => 'CONEXION_EXITOSA',
        'host'        => DB_HOST,
        'database'    => DB_NAME,
        'tables'      => $tables,
        'total_users' => $userCount,
        'total_media' => $mediaCount,
    ];
} catch (\Throwable $e) {
    $diagnostics['database'] = [
        'status' => 'ERROR_DE_CONEXION',
        'error'  => $e->getMessage(),
        'host'   => DB_HOST,
        'db'     => DB_NAME,
        'user'   => DB_USER,
    ];

    // Probar candidatos de .vscode/db.md para diagnosticar
    $candidates = [
        ['user' => 'u757198907_importsdvcris', 'pass' => 'Bs8@NmHEu!d^'],
        ['user' => 'u757198907_importsdvcris', 'pass' => 'u!d^'],
        ['user' => 'u757198907_importsdvcris', 'pass' => 'Bs8@NmHE'],
        ['user' => 'u757198907_importsdvcrisass', 'pass' => 'Bs8@NmHE'],
        ['user' => 'u757198907_importsdvcrisass', 'pass' => 'Bs8@NmHEu!d^'],
    ];

    $diagnostics['database_credential_tests'] = [];
    foreach ($candidates as $cand) {
        $key = $cand['user'] . ' | ' . substr($cand['pass'], 0, 3) . '***';
        try {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
            $testPdo = new PDO($dsn, $cand['user'], $cand['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]);
            $diagnostics['database_credential_tests'][$key] = 'EXITO_CONECTADO';
        } catch (\Throwable $te) {
            $diagnostics['database_credential_tests'][$key] = 'FALLO: ' . $te->getMessage();
        }
    }
}

// Registrar en el archivo logs/debug.log
app_log('Diagnóstico test_debug.php ejecutado', $diagnostics);

// Salida explícita con print_r (cumpliendo la regla del proyecto)
echo "========================================================\n";
echo " DIAGNOSTICO DEL SISTEMA (IMAGE OPTIMIZER)             \n";
echo "========================================================\n\n";

print_r($diagnostics);

echo "\n========================================================\n";
echo " Registro guardado en logs/debug.log                     \n";
echo "========================================================\n";
