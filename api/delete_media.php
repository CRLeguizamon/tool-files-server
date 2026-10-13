<?php
/**
 * API: Eliminación de Imagen o Lote de Imágenes y sus Registros
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado. Inicia sesión nuevamente.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Solo se acepta POST.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido o expirado.']);
    exit;
}

// Obtener IDs a eliminar (soporta 'id' individual o 'ids' en lote)
$idList = [];
if (!empty($_POST['ids'])) {
    if (is_array($_POST['ids'])) {
        $idList = array_filter(array_map('intval', $_POST['ids']), fn($x) => $x > 0);
    } else {
        $idList = array_filter(array_map('intval', explode(',', (string) $_POST['ids'])), fn($x) => $x > 0);
    }
} elseif (!empty($_POST['id'])) {
    $singleId = (int) $_POST['id'];
    if ($singleId > 0) {
        $idList = [$singleId];
    }
}

if (empty($idList)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se indicaron IDs válidos para eliminar.']);
    exit;
}

// Limitar a un máximo razonable por lote para evitar timeouts
$idList = array_slice(array_unique($idList), 0, 300);

try {
    $pdo = get_db();
    $placeholders = implode(',', array_fill(0, count($idList), '?'));
    $stmt = $pdo->prepare("SELECT `id`, `filename`, `file_path`, `thumb_path` FROM `media` WHERE `id` IN ($placeholders)");
    $stmt->execute(array_values($idList));
    $items = $stmt->fetchAll();

    if (empty($items)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'No se encontraron las imágenes solicitadas en la base de datos.']);
        exit;
    }

    $deletedFilesCount = 0;
    $deletedIds = [];

    foreach ($items as $item) {
        $filename = basename((string) $item['filename']);
        $cleanPath = ltrim(str_replace('\\', '/', (string)($item['file_path'] ?? '')), '/');
        $cleanThumb = ltrim(str_replace('\\', '/', (string)($item['thumb_path'] ?? '')), '/');

        $pathsToDelete = [
            ROOT_PATH . '/' . $cleanPath,
            ROOT_PATH . '/' . $cleanThumb,
            UPLOAD_DIR . '/' . $filename,
            THUMB_DIR . '/' . $filename,
        ];

        foreach (array_unique($pathsToDelete) as $tp) {
            if (file_exists($tp) && is_file($tp)) {
                if (@unlink($tp)) {
                    $deletedFilesCount++;
                }
            }
        }
        $deletedIds[] = (int) $item['id'];
    }
    clearstatcache();

    // Eliminar registros de la base de datos
    $delPlaceholders = implode(',', array_fill(0, count($deletedIds), '?'));
    $delStmt = $pdo->prepare("DELETE FROM `media` WHERE `id` IN ($delPlaceholders)");
    $delStmt->execute($deletedIds);

    app_log('Eliminación de medios ejecutada', [
        'total_registros_borrados'  => count($deletedIds),
        'archivos_fisicos_borrados' => $deletedFilesCount,
        'ids'                       => $deletedIds,
    ]);

    $message = count($deletedIds) === 1 
        ? 'Imagen eliminada correctamente.' 
        : sprintf('Se eliminaron %d imágenes correctamente.', count($deletedIds));

    echo json_encode([
        'success'     => true,
        'message'     => $message,
        'deleted_ids' => $deletedIds,
        'count'       => count($deletedIds),
    ]);

} catch (\Throwable $e) {
    app_log('Error en api/delete_media.php', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
