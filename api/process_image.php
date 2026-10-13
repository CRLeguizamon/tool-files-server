<?php
/**
 * API: Procesamiento por Lotes de Imágenes
 * Recibe una sola imagen por petición para evitar timeouts y sobrecarga de memoria
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/ImageProcessor.php';

// Verificar autenticación
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no autorizada. Inicia sesión nuevamente.']);
    exit;
}

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Solo se acepta POST.']);
    exit;
}

// Validar CSRF token
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido. Recarga la página.']);
    exit;
}

// Validar que se haya enviado un archivo
if (!isset($_FILES['image']) || empty($_FILES['image']['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se recibió ningún archivo de imagen.']);
    exit;
}

try {
    // Parámetros de procesamiento
    $maxWidth = isset($_POST['max_width']) ? (int) $_POST['max_width'] : DEFAULT_MAX_WIDTH;
    $compress = isset($_POST['compress']) ? filter_var($_POST['compress'], FILTER_VALIDATE_BOOLEAN) : true;
    $convertJpg = isset($_POST['convert_jpg']) ? filter_var($_POST['convert_jpg'], FILTER_VALIDATE_BOOLEAN) : false;

    // Procesar la imagen (redimensiona, formatea nombre a slug, comprime y opcionalmente convierte a JPG)
    $result = ImageProcessor::process($_FILES['image'], $maxWidth, $compress, $convertJpg);

    // Guardar registro en la base de datos
    $pdo = get_db();
    $userId = current_user()['id'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO `media` (
            `filename`, `original_name`, `file_path`, `thumb_path`, 
            `file_size`, `original_size`, `width`, `height`, 
            `mime_type`, `is_compressed`, `created_by`
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $result['filename'],
        $result['original_name'],
        $result['file_path'],
        $result['thumb_path'],
        $result['file_size'],
        $result['original_size'],
        $result['width'],
        $result['height'],
        $result['mime_type'],
        $result['is_compressed'],
        $userId,
    ]);

    $mediaId = (int) $pdo->lastInsertId();
    $result['id'] = $mediaId;

    app_log('Registro guardado en BD exitosamente', ['media_id' => $mediaId, 'archivo' => $result['filename']]);

    echo json_encode([
        'success' => true,
        'message' => 'Imagen procesada y guardada con éxito.',
        'data'    => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (\Throwable $e) {
    app_log('Error en api/process_image.php', ['error' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
