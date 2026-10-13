<?php
/**
 * API: Eliminación de Imagen y su Registro
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido.']);
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de imagen inválido.']);
    exit;
}

try {
    $pdo = get_db();
    $stmt = $pdo->prepare("SELECT * FROM `media` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'La imagen no existe en la base de datos.']);
        exit;
    }

    // 1. Resolver rutas absolutas exactas
    $filename = basename($item['filename']);
    $filePathsToDelete = [
        ROOT_PATH . '/' . ltrim($item['file_path'], '/'),
        ROOT_PATH . '/' . ltrim($item['thumb_path'], '/'),
        UPLOAD_DIR . '/' . $filename,
        THUMB_DIR . '/' . $filename,
    ];

    $deletedFilesCount = 0;
    foreach (array_unique($filePathsToDelete) as $targetPath) {
        if (file_exists($targetPath) && is_file($targetPath)) {
            if (@unlink($targetPath)) {
                $deletedFilesCount++;
            }
        }
    }
    clearstatcache();

    // 2. Eliminar registro de la base de datos
    $del = $pdo->prepare("DELETE FROM `media` WHERE `id` = ?");
    $del->execute([$id]);

    app_log('Eliminación completa: archivos físicos y registro en BD', [
        'id'                 => $id,
        'archivo'            => $filename,
        'archivos_borrados'  => $deletedFilesCount,
    ]);

    echo json_encode(['success' => true, 'message' => 'Imagen eliminada correctamente.']);
} catch (\Throwable $e) {
    app_log('Error al eliminar imagen', ['id' => $id, 'error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
}
