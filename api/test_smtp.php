<?php
/**
 * API: Prueba de Conexión y Envío de Correo SMTP en Tiempo Real
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/settings.php';
require_once dirname(__DIR__) . '/includes/Mailer.php';

// 1. Validar permisos de administrador
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requieren permisos de administrador.']);
    exit;
}

// 2. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Solo se acepta POST.']);
    exit;
}

// 3. Validar token CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido o expirado. Recarga la página.']);
    exit;
}

// 4. Validar correo de destino de la prueba
$testEmail = trim(filter_var($_POST['test_email'] ?? '', FILTER_SANITIZE_EMAIL));
if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Debes ingresar un correo electrónico válido para recibir la prueba.']);
    exit;
}

// 5. Preparar configuración (permite probar valores en el formulario antes de guardar)
$savedConfig = get_smtp_config();

$host = isset($_POST['host']) && trim($_POST['host']) !== '' ? trim($_POST['host']) : $savedConfig['host'];
$port = isset($_POST['port']) && (int) $_POST['port'] > 0 ? (int) $_POST['port'] : $savedConfig['port'];
$encryption = isset($_POST['encryption']) ? trim($_POST['encryption']) : $savedConfig['encryption'];
$auth = isset($_POST['auth']) ? !empty($_POST['auth']) : $savedConfig['auth'];
$username = isset($_POST['username']) ? trim($_POST['username']) : $savedConfig['username'];
$password = isset($_POST['password']) && $_POST['password'] !== '' ? (string) $_POST['password'] : $savedConfig['password'];
$fromEmail = isset($_POST['from_email']) && trim($_POST['from_email']) !== '' ? trim($_POST['from_email']) : $savedConfig['from_email'];
$fromName = isset($_POST['from_name']) && trim($_POST['from_name']) !== '' ? trim($_POST['from_name']) : $savedConfig['from_name'];

$customConfig = [
    'host'       => $host,
    'port'       => $port,
    'encryption' => $encryption,
    'auth'       => $auth,
    'username'   => $username,
    'password'   => $password,
    'from_email' => $fromEmail,
    'from_name'  => $fromName,
];

app_log('Iniciando prueba interactiva de envío SMTP', [
    'destinatario' => $testEmail,
    'host'         => $host,
    'port'         => $port,
    'encryption'   => $encryption,
]);

// 6. Ejecutar prueba de envío mediante Mailer nativo
$result = Mailer::sendTestEmail($testEmail, $customConfig);

app_log('Resultado de prueba SMTP interactiva', [
    'exito'   => $result['success'],
    'mensaje' => $result['message'],
]);

echo json_encode([
    'success'    => $result['success'],
    'message'    => $result['message'],
    'transcript' => $result['transcript'] ?? [],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
