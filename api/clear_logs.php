<?php
/**
 * API: Vaciar archivo debug.log
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

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

$logFile = LOGS_DIR . '/debug.log';
$user = current_user();
$userName = $user['name'] ?? $user['email'] ?? 'Usuario';

$initialContent = sprintf(
    "[%s] [%s] Log reiniciado por %s (%s)\n",
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
    $userName,
    $user['email'] ?? ''
);

if (@file_put_contents($logFile, $initialContent) !== false) {
    echo json_encode(['success' => true, 'message' => 'El archivo de logs ha sido vaciado correctamente.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo escribir en el archivo de logs. Revisa permisos de carpeta.']);
}
