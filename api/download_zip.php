<?php
/**
 * API: Empaquetado y Descarga de Imágenes en Archivo ZIP
 * 
 * Modos soportados:
 * 1. all=1: Empaqueta todas las imágenes registradas en la Biblioteca de Medios (sin borrarlas).
 * 2. ids=1,2,3: Empaqueta un lote específico de imágenes por sus IDs de base de datos (sin borrarlas).
 * 3. batch_id=...: Empaqueta un lote temporal de subida (uploads/temp_zip/batch_id) y elimina los temporales tras la descarga.
 */

declare(strict_types=1);

// Desactivar compresión zlib para evitar corrupción del flujo binario en descargas ZIP
if (ini_get('zlib.output_compression')) {
    @ini_set('zlib.output_compression', 'Off');
}

require_once dirname(__DIR__) . '/includes/auth.php';

// 1. Verificar autenticación del usuario
if (!is_logged_in()) {
    http_response_code(401);
    if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Sesión expirada o no autorizada. Inicia sesión nuevamente.']);
    } else {
        header('Location: ../login.php');
    }
    exit;
}

/**
 * Obtiene el directorio para archivos ZIP temporales con fallback garantizado
 */
function get_temp_zip_dir(): string
{
    $candidates = [
        defined('TEMP_ZIP_DIR') ? TEMP_ZIP_DIR : (ROOT_PATH . '/uploads/temp_zip'),
        sys_get_temp_dir() . '/image_optimizer_zips',
        sys_get_temp_dir(),
        UPLOAD_DIR,
    ];

    foreach ($candidates as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            return $dir;
        }
    }

    return sys_get_temp_dir();
}

/**
 * Limpia archivos y carpetas temporales de lotes antiguos (> 1 hora)
 */
function cleanup_old_temp_zips(string $baseTempDir): void
{
    if (!is_dir($baseTempDir)) {
        return;
    }
    $now = time();
    $items = @scandir($baseTempDir);
    if (!$items) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === '.gitkeep') {
            continue;
        }
        $fullPath = $baseTempDir . '/' . $item;
        $mtime = @filemtime($fullPath);
        if ($mtime !== false && ($now - $mtime) > 3600) {
            if (is_dir($fullPath)) {
                $subFiles = @scandir($fullPath) ?: [];
                foreach ($subFiles as $sf) {
                    if ($sf !== '.' && $sf !== '..') {
                        @unlink($fullPath . '/' . $sf);
                    }
                }
                @rmdir($fullPath);
            } else {
                @unlink($fullPath);
            }
        }
    }
}

/**
 * Construye un archivo ZIP binario estándar en PHP puro (fallback por si ZipArchive no estuviera activo)
 */
function create_zip_fallback(array $files, string $zipFilePath): bool
{
    $fp = @fopen($zipFilePath, 'wb');
    if (!$fp) {
        return false;
    }

    $centralDir = '';
    $offset = 0;
    $count = 0;

    foreach ($files as $localName => $fullPath) {
        $data = @file_get_contents($fullPath);
        if ($data === false) {
            continue;
        }

        $uncompressedSize = strlen($data);
        $crc = crc32($data);
        $compressedData = gzdeflate($data, 1);
        $compressionMethod = 8;

        if ($compressedData === false || strlen($compressedData) >= $uncompressedSize) {
            $compressedData = $data;
            $compressionMethod = 0;
        }

        $compressedSize = strlen($compressedData);
        $nameLen = strlen($localName);

        // Cabecera local de archivo
        $localHeader = pack(
            'VvvvVVVVvv',
            0x04034b50,
            20,
            0,
            $compressionMethod,
            0,
            $crc,
            $compressedSize,
            $uncompressedSize,
            $nameLen,
            0
        ) . $localName;

        fwrite($fp, $localHeader);
        fwrite($fp, $compressedData);

        // Directorio central
        $centralDir .= pack(
            'VvvvvVVVVvvvvvVV',
            0x02014b50,
            20,
            20,
            0,
            $compressionMethod,
            0,
            $crc,
            $compressedSize,
            $uncompressedSize,
            $nameLen,
            0,
            0,
            0,
            0,
            32,
            $offset
        ) . $localName;

        $offset += strlen($localHeader) + $compressedSize;
        $count++;
    }

    $centralDirSize = strlen($centralDir);
    fwrite($fp, $centralDir);

    // Fin del registro de directorio central
    $eocd = pack(
        'VvvvvVVv',
        0x06054b50,
        0,
        0,
        $count,
        $count,
        $centralDirSize,
        $offset,
        0
    );
    fwrite($fp, $eocd);
    fclose($fp);

    return $count > 0;
}

// Preparar directorio temporal y limpieza preventiva
$tempBaseDir = get_temp_zip_dir();
cleanup_old_temp_zips($tempBaseDir);

$imageFiles = [];
$isTempBatch = false;
$batchDir = null;
$zipDownloadName = 'imagenes-optimizadas-' . date('Ymd_His') . '.zip';

// Determinar modo de descarga
$isAll = isset($_REQUEST['all']) && (string) $_REQUEST['all'] === '1';
$rawIds = isset($_REQUEST['ids']) ? trim((string) $_REQUEST['ids']) : '';
$rawBatchId = isset($_REQUEST['batch_id']) ? trim((string) $_REQUEST['batch_id']) : '';

if ($isAll) {
    // -------------------------------------------------------------
    // MODO 1: Descargar TODAS las imágenes de la Biblioteca de Medios
    // -------------------------------------------------------------
    $pdo = get_db();
    $stmt = $pdo->query("SELECT `id`, `filename`, `file_path` FROM `media` ORDER BY `id` DESC");
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        app_log('Intento de descargar ZIP total sin registros en BD');
        header('Location: ../media.php?error=no_media');
        exit;
    }

    foreach ($rows as $r) {
        $cleanPath = ltrim(str_replace('\\', '/', (string)($r['file_path'] ?? '')), '/');
        $candidates = [
            ROOT_PATH . '/' . $cleanPath,
            UPLOAD_DIR . '/' . basename((string)($r['filename'] ?? '')),
            UPLOAD_DIR . '/' . basename($cleanPath),
        ];

        $found = null;
        foreach ($candidates as $cp) {
            if (is_file($cp) && filesize($cp) > 0) {
                $found = $cp;
                break;
            }
        }

        if ($found !== null) {
            $entryName = basename((string)($r['filename'] ?? 'imagen.jpg'));
            if (isset($imageFiles[$entryName])) {
                $entryName = $r['id'] . '_' . $entryName;
            }
            $imageFiles[$entryName] = $found;
        }
    }

    $zipDownloadName = 'medios_' . date('Y-m-d_His') . '.zip';

} elseif ($rawIds !== '') {
    // -------------------------------------------------------------
    // MODO 2: Descargar lote específico por IDs de base de datos
    // -------------------------------------------------------------
    $idArray = array_filter(array_map('intval', explode(',', $rawIds)), fn($id) => $id > 0);
    if (empty($idArray)) {
        http_response_code(400);
        die('Parámetros de IDs de imágenes inválidos.');
    }

    $pdo = get_db();
    $placeholders = implode(',', array_fill(0, count($idArray), '?'));
    $stmt = $pdo->prepare("SELECT `id`, `filename`, `file_path` FROM `media` WHERE `id` IN ($placeholders) ORDER BY `id` DESC");
    $stmt->execute(array_values($idArray));
    $rows = $stmt->fetchAll();

    if (empty($rows)) {
        app_log('No se encontraron registros en BD para los IDs del lote', ['ids' => $rawIds]);
        header('Location: ../index.php?error=not_found');
        exit;
    }

    foreach ($rows as $r) {
        $cleanPath = ltrim(str_replace('\\', '/', (string)($r['file_path'] ?? '')), '/');
        $candidates = [
            ROOT_PATH . '/' . $cleanPath,
            UPLOAD_DIR . '/' . basename((string)($r['filename'] ?? '')),
            UPLOAD_DIR . '/' . basename($cleanPath),
        ];

        $found = null;
        foreach ($candidates as $cp) {
            if (is_file($cp) && filesize($cp) > 0) {
                $found = $cp;
                break;
            }
        }

        if ($found !== null) {
            $entryName = basename((string)($r['filename'] ?? 'imagen.jpg'));
            if (isset($imageFiles[$entryName])) {
                $entryName = $r['id'] . '_' . $entryName;
            }
            $imageFiles[$entryName] = $found;
        }
    }

    $zipDownloadName = 'lote_imagenes_' . date('Y-m-d_His') . '.zip';

} elseif ($rawBatchId !== '') {
    // -------------------------------------------------------------
    // MODO 3: Descargar lote temporal de subida
    // -------------------------------------------------------------
    $batchId = preg_replace('/[^a-zA-Z0-9_-]/', '', $rawBatchId);
    if ($batchId === '' || $batchId !== $rawBatchId) {
        http_response_code(400);
        die('Identificador de lote inválido.');
    }

    $batchDir = $tempBaseDir . '/' . $batchId;
    if (!is_dir($batchDir)) {
        $batchDir = (defined('TEMP_ZIP_DIR') ? TEMP_ZIP_DIR : (ROOT_PATH . '/uploads/temp_zip')) . '/' . $batchId;
    }

    if (!is_dir($batchDir)) {
        http_response_code(404);
        die('El lote solicitado no existe o ya fue descargado.');
    }

    $entries = @scandir($batchDir) ?: [];
    foreach ($entries as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $batchDir . '/' . $entry;
        if (is_file($path) && filesize($path) > 0) {
            $imageFiles[$entry] = $path;
        }
    }
    $isTempBatch = true;
    $zipDownloadName = 'lote_temporal_' . date('Y-m-d_His') . '.zip';

} else {
    http_response_code(400);
    die('Parámetros de descarga no especificados. Usa ?all=1 o ?ids=...');
}

// Validar que se hayan encontrado archivos físicos
if (empty($imageFiles)) {
    app_log('No se encontraron archivos físicos en el servidor para generar el ZIP', [
        'modo' => $isAll ? 'all' : ($rawIds !== '' ? 'ids' : 'batch_id'),
    ]);
    if ($isTempBatch && $batchDir && is_dir($batchDir)) {
        @rmdir($batchDir);
    }
    header('Location: ../media.php?error=no_files_found');
    exit;
}

// Ruta única del archivo ZIP generado
$uniqueZipId = 'zip_' . bin2hex(random_bytes(8)) . '_' . time();
$tempZipPath = $tempBaseDir . '/' . $uniqueZipId . '.zip';

app_log('Iniciando construcción de archivo ZIP', [
    'modo'           => $isAll ? 'all' : ($rawIds !== '' ? 'ids' : 'batch_id'),
    'total_archivos' => count($imageFiles),
    'zip_file'       => $zipDownloadName,
    'temp_path'      => $tempZipPath,
]);

$zipCreated = false;

// Intentar creación mediante ZipArchive nativo
if (class_exists('ZipArchive')) {
    $zip = new ZipArchive();
    $openRes = $zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($openRes === true) {
        foreach ($imageFiles as $name => $fullPath) {
            $zip->addFile($fullPath, $name);
        }
        $zipCreated = $zip->close();
    } else {
        app_log('ZipArchive::open no tuvo éxito, usando fallback puro', ['codigo_error' => $openRes]);
    }
}

// Fallback en PHP puro si ZipArchive no está presente o falló
if (!$zipCreated || !file_exists($tempZipPath) || filesize($tempZipPath) === 0) {
    $zipCreated = create_zip_fallback($imageFiles, $tempZipPath);
}

// Limpiar archivos si fue un lote temporal volátil
if ($isTempBatch && $batchDir && is_dir($batchDir)) {
    foreach ($imageFiles as $fullPath) {
        @unlink($fullPath);
    }
    @rmdir($batchDir);
}

// Verificar que el ZIP se creó y tiene tamaño > 0
if (!$zipCreated || !file_exists($tempZipPath) || filesize($tempZipPath) === 0) {
    app_log('Fallo definitivo al crear archivo ZIP', ['zip_file' => $zipDownloadName]);
    http_response_code(500);
    die('Error en el servidor al generar el archivo ZIP.');
}

// Registrar eliminación del archivo ZIP temporal tras completar la transmisión
register_shutdown_function(static function () use ($tempZipPath, $zipDownloadName): void {
    if (file_exists($tempZipPath)) {
        @unlink($tempZipPath);
    }
    clearstatcache();
    app_log('ZIP transmitido y eliminado de almacenamiento temporal', ['archivo' => $zipDownloadName]);
});

ignore_user_abort(true);
clearstatcache(true, $tempZipPath);
$zipSize = filesize($tempZipPath);

// Limpiar cualquier búfer de salida para prevenir corrupción de bytes binarios
while (ob_get_level() > 0) {
    ob_end_clean();
}

// Encabezados HTTP estándar para descarga forzada de archivos binarios ZIP
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipDownloadName . '"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . (string) $zipSize);
header('Cache-Control: private, must-revalidate, post-check=0, pre-check=0, max-age=0');
header('Pragma: public');
header('Expires: 0');

readfile($tempZipPath);
@unlink($tempZipPath);
exit;
