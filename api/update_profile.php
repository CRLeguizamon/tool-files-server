<?php
/**
 * API: Actualización de Perfil y Cambio de Contraseña del Usuario Conectado
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';

// 1. Validar autenticación
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no autorizada.']);
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

$currentUser = current_user();
$userId = (int) ($currentUser['id'] ?? 0);

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de usuario de sesión no válido.']);
    exit;
}

$name = trim((string) ($_POST['name'] ?? ''));
$email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
$currentPassword = (string) ($_POST['current_password'] ?? '');
$newPassword = (string) ($_POST['new_password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

if (mb_strlen($name) < 2) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El nombre debe tener al menos 2 caracteres.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El formato del correo electrónico no es válido.']);
    exit;
}

try {
    $pdo = get_db();

    // 4. Verificar unicidad de correo
    $checkEmail = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = ? AND `id` != ? LIMIT 1");
    $checkEmail->execute([$email, $userId]);
    if ($checkEmail->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ya existe otra cuenta registrada con este correo electrónico.']);
        exit;
    }

    // 5. Obtener datos actuales del usuario para validación de clave
    $userStmt = $pdo->prepare("SELECT `id`, `password` FROM `users` WHERE `id` = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $dbUser = $userStmt->fetch();

    if (!$dbUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'El usuario no existe en la base de datos.']);
        exit;
    }

    $changePassword = false;
    $newHash = null;

    if ($newPassword !== '') {
        if ($currentPassword === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Debes ingresar tu contraseña actual para establecer una nueva.']);
            exit;
        }

        if (!password_verify($currentPassword, (string) $dbUser['password'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La contraseña actual ingresada es incorrecta.']);
            exit;
        }

        if (mb_strlen($newPassword) < 4) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 4 caracteres.']);
            exit;
        }

        // Si se envió confirm_password (retrocompatibilidad), se valida; de lo contrario no es obligatorio
        if ($confirmPassword !== '' && $newPassword !== $confirmPassword) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'La nueva contraseña y su confirmación no coinciden.']);
            exit;
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $changePassword = true;
    }

    // 6. Actualizar registro en base de datos
    if ($changePassword && $newHash !== null) {
        $updateStmt = $pdo->prepare("UPDATE `users` SET `name` = ?, `email` = ?, `password` = ? WHERE `id` = ?");
        $updateStmt->execute([$name, $email, $newHash, $userId]);
    } else {
        $updateStmt = $pdo->prepare("UPDATE `users` SET `name` = ?, `email` = ? WHERE `id` = ?");
        $updateStmt->execute([$name, $email, $userId]);
    }

    // 7. Actualizar datos en sesión activa inmediatamente
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['email'] = $email;

    app_log('Perfil de usuario actualizado con éxito', [
        'user_id'          => $userId,
        'email'            => $email,
        'nombre'           => $name,
        'cambio_password'  => $changePassword,
    ]);

    echo json_encode([
        'success' => true,
        'message' => $changePassword 
            ? 'Perfil y contraseña actualizados exitosamente.' 
            : 'Perfil actualizado exitosamente.',
        'user'    => [
            'id'    => $userId,
            'name'  => $name,
            'email' => $email,
        ],
    ]);

} catch (\Throwable $e) {
    app_log('Error en update_profile.php', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al actualizar perfil: ' . $e->getMessage()]);
}
