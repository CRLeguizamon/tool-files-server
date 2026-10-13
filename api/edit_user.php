<?php
/**
 * API: Edición y Actualización de Usuario por Administrador
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';

// 1. Validar autenticación
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no activa o expirada. Inicia sesión nuevamente.']);
    exit;
}

// 2. Validar permisos de administrador
if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Se requieren permisos de administrador.']);
    exit;
}

// 3. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Solo se acepta POST.']);
    exit;
}

// 4. Validar CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido o expirado. Recarga la página.']);
    exit;
}

// 5. Sanitizar y validar parámetros
$userId = (int) ($_POST['id'] ?? 0);
$name = trim((string) ($_POST['name'] ?? ''));
$email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$role = trim((string) ($_POST['role'] ?? 'customer'));
$role = in_array($role, ['admin', 'customer'], true) ? $role : 'customer';
$password = (string) ($_POST['password'] ?? '');

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de usuario no válido.']);
    exit;
}

if ($name === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre no puede estar vacío.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El formato del correo electrónico no es válido.']);
    exit;
}

if ($password !== '' && strlen($password) < 4) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 4 caracteres.']);
    exit;
}

try {
    $pdo = get_db();

    // 6. Verificar existencia del usuario
    $stmt = $pdo->prepare("SELECT `id`, `name`, `email`, `role` FROM `users` WHERE `id` = ? LIMIT 1");
    $stmt->execute([$userId]);
    $existingUser = $stmt->fetch();

    if (!$existingUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'El usuario que intentas editar no existe en el sistema.']);
        exit;
    }

    // 7. Proteger usuario principal de degradación de rol
    if ($userId === 1 && $role !== 'admin') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No es posible revocar el rol de Administrador al usuario principal.']);
        exit;
    }

    // 8. Verificar unicidad de correo electrónico
    $checkEmail = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = ? AND `id` != ? LIMIT 1");
    $checkEmail->execute([$email, $userId]);
    if ($checkEmail->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El correo electrónico ya se encuentra registrado por otro usuario.']);
        exit;
    }

    // 9. Ejecutar actualización
    $passwordChanged = false;
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE `users` SET `name` = ?, `email` = ?, `role` = ?, `password` = ? WHERE `id` = ?");
        $upd->execute([$name, $email, $role, $hash, $userId]);
        $passwordChanged = true;
    } else {
        $upd = $pdo->prepare("UPDATE `users` SET `name` = ?, `email` = ?, `role` = ? WHERE `id` = ?");
        $upd->execute([$name, $email, $role, $userId]);
    }

    // 10. Si el usuario editado es el mismo que está en sesión, refrescar datos de sesión
    $currentUser = current_user();
    if ($currentUser && (int) ($currentUser['id'] ?? 0) === $userId) {
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['role'] = $role;
    }

    // 11. Registro en archivo de log conforme a GEMINI.md
    app_log('Usuario editado exitosamente por administrador', [
        'target_user_id'   => $userId,
        'name'             => $name,
        'email'            => $email,
        'role'             => $role,
        'password_changed' => $passwordChanged,
        'admin'            => $currentUser['email'] ?? 'admin',
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Usuario #' . $userId . ' actualizado exitosamente.',
        'data'    => [
            'id'    => $userId,
            'name'  => $name,
            'email' => $email,
            'role'  => $role,
        ]
    ]);

} catch (\Throwable $e) {
    app_log('Error al editar usuario en BD', [
        'target_user_id' => $userId,
        'error'          => $e->getMessage()
    ]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error en base de datos: ' . $e->getMessage()]);
}
