<?php
/**
 * API: Guardar Configuración del Servidor SMTP
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/settings.php';

// 1. Validar autenticación y rol admin
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requieren permisos de administrador.']);
    exit;
}

// 2. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// 3. Validar CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido o expirado.']);
    exit;
}

$host = trim((string) ($_POST['host'] ?? ''));
$port = (int) ($_POST['port'] ?? 465);
$encryption = trim((string) ($_POST['encryption'] ?? 'ssl'));
$auth = !empty($_POST['auth']);
$username = trim((string) ($_POST['username'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
$fromEmail = trim((string) ($_POST['from_email'] ?? ''));
$fromName = trim((string) ($_POST['from_name'] ?? APP_NAME));

if (empty($host)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El servidor SMTP (Host) es obligatorio.']);
    exit;
}

if ($port <= 0 || $port > 65535) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El puerto SMTP no es válido (ej. 465 o 587).']);
    exit;
}

if (!empty($fromEmail) && !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El correo del remitente no tiene un formato válido.']);
    exit;
}

$config = [
    'host'       => $host,
    'port'       => $port,
    'encryption' => $encryption,
    'auth'       => $auth,
    'username'   => $username,
    'from_email' => $fromEmail,
    'from_name'  => $fromName,
];

if ($password !== '') {
    $config['password'] = $password;
}

$saved = save_smtp_config($config);

if ($saved) {
    app_log('Configuración SMTP guardada exitosamente por administrador', [
        'host'       => $host,
        'port'       => $port,
        'encryption' => $encryption,
        'from_email' => $fromEmail,
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Configuración SMTP guardada correctamente en el sistema.',
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error al persistir la configuración en la base de datos.',
    ]);
}
