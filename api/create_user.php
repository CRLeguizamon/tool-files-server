<?php
/**
 * API: Creación de Nuevo Usuario (Admin o Customer)
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/includes/auth.php';

// 1. Verificar autenticación
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no activa o expirada. Por favor inicia sesión nuevamente.']);
    exit;
}

// 2. Verificar permisos de administrador (con fallback a BD si la sesión era previa a la migración)
$currentUser = current_user();
$isAdmin = is_admin();

if (!$isAdmin) {
    try {
        $pdo = get_db();
        $st = $pdo->prepare("SELECT `role` FROM `users` WHERE `id` = ? LIMIT 1");
        $st->execute([$currentUser['id'] ?? 0]);
        $dbRole = $st->fetchColumn();
        if ($dbRole === 'admin' || (int) ($currentUser['id'] ?? 0) === 1) {
            $_SESSION['user']['role'] = 'admin';
            $isAdmin = true;
        }
    } catch (\Throwable $e) {
        app_log('Error comprobando rol admin en BD', ['error' => $e->getMessage()]);
    }
}

if (!$isAdmin) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tienes permisos de administrador para crear usuarios.']);
    exit;
}

// 3. Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Solo se acepta POST.']);
    exit;
}

// 4. Validar token CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido o expirado. Recarga la página.']);
    exit;
}

// 5. Sanitizar y validar campos
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

app_log('Intento de creación de usuario', ['email' => $email, 'role' => $role, 'creador' => $currentUser['email'] ?? '']);

try {
    $pdo = get_db();

    // Comprobar si el correo ya existe
    $check = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1");
    $check->execute([$email]);
    if ($check->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ya existe un usuario registrado con este correo electrónico.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("
        INSERT INTO `users` (`name`, `email`, `password`, `role`)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$name, $email, $hash, $role]);

    $newId = (int) $pdo->lastInsertId();
    app_log('Usuario creado exitosamente', ['user_id' => $newId, 'email' => $email, 'role' => $role]);

    // Intento de notificación por correo si SMTP está configurado
    $emailSent = false;
    $emailMsg = '';
    try {
        require_once dirname(__DIR__) . '/includes/Mailer.php';
        $smtpConfig = get_smtp_config();
        if (!empty($smtpConfig['host'])) {
            $mailRes = Mailer::sendAccountCreatedEmail($name, $email, $password);
            $emailSent = $mailRes['success'];
            $emailMsg = $mailRes['message'];
            app_log('Notificación de cuenta nueva enviada por SMTP', [
                'to'      => $email,
                'exito'   => $emailSent,
                'mensaje' => $emailMsg,
            ]);
        }
    } catch (\Throwable $mEx) {
        app_log('No se pudo enviar correo de bienvenida por SMTP', ['error' => $mEx->getMessage()]);
    }

    $successMsg = 'Usuario creado exitosamente con rol ' . strtoupper($role) . '.';
    if ($emailSent) {
        $successMsg .= ' Se enviaron las credenciales de acceso a su correo electrónico.';
    }

    echo json_encode([
        'success'    => true,
        'message'    => $successMsg,
        'email_sent' => $emailSent,
        'data'       => [
            'id'    => $newId,
            'name'  => $name,
            'email' => $email,
            'role'  => $role,
        ]
    ]);
} catch (\Throwable $e) {
    app_log('Error creando usuario en BD', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar en base de datos: ' . $e->getMessage()]);
}
