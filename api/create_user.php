<?php
/**
 * API: Creación de Nuevo Usuario Administrador
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permisos de administrador para realizar esta acción.']);
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

$name = trim($_POST['name'] ?? '');
$email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$password = trim($_POST['password'] ?? '');
$role = trim($_POST['role'] ?? 'customer');
$role = in_array($role, ['admin', 'customer'], true) ? $role : 'customer';

if (empty($name)) {
    $name = $role === 'admin' ? 'Administrador' : 'Cliente';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El formato del correo electrónico no es válido.']);
    exit;
}

if (strlen($password) < 4) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 4 caracteres.']);
    exit;
}

try {
    $pdo = get_db();

    // Comprobar si el correo ya existe
    $check = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ya existe un usuario registrado con este correo.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO `users` (`name`, `email`, `password`, `role`)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$name, $email, $hash, $role]);

    $newId = (int) $pdo->lastInsertId();
    app_log('Nuevo usuario creado', ['user_id' => $newId, 'email' => $email, 'role' => $role]);

    echo json_encode([
        'success' => true,
        'message' => 'Usuario creado exitosamente con rol ' . strtoupper($role) . '.',
        'data'    => [
            'id'    => $newId,
            'name'  => $name,
            'email' => $email,
            'role'  => $role,
        ]
    ]);
} catch (\Throwable $e) {
    app_log('Error creando administrador', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
