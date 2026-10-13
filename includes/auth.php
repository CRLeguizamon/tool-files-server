<?php
/**
 * Módulo de Autenticación, Sesiones y Seguridad CSRF
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

// Iniciar sesión segura si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_name('imgopt_session');
    session_start();
}

/**
 * Verifica si el usuario actual ha iniciado sesión
 */
function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']) && !empty($_SESSION['user']['id']);
}

/**
 * Retorna los datos del usuario actual o null
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Exige inicio de sesión; si no lo está, redirige a login.php
 */
function require_login(): void
{
    if (!is_logged_in()) {
        $loginUrl = base_url('login.php');
        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * Verifica si el usuario actual tiene rol de administrador
 */
function is_admin(): bool
{
    return is_logged_in() && (current_user()['role'] ?? '') === 'admin';
}

/**
 * Exige rol admin; si no es admin, redirige al inicio
 */
function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        header('Location: ' . base_url('index.php'));
        exit;
    }
}

/**
 * Genera o recupera el token CSRF de la sesión
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida un token CSRF
 */
function verify_csrf(?string $token): bool
{
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Intenta autenticar a un usuario por correo y contraseña
 */
function attempt_login(string $email, string $password): array
{
    $email = trim(filter_var($email, FILTER_SANITIZE_EMAIL));

    if (empty($email) || empty($password)) {
        return ['success' => false, 'message' => 'Por favor, ingresa correo y contraseña.'];
    }

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT `id`, `email`, `password`, `name`, `role` FROM `users` WHERE `email` = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Regenerar ID de sesión para prevenir Session Fixation
            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id'    => (int) $user['id'],
                'email' => $user['email'],
                'name'  => $user['name'],
                'role'  => $user['role'],
            ];

            app_log('Inicio de sesión exitoso', ['email' => $email, 'user_id' => $user['id']]);
            return ['success' => true];
        }

        app_log('Intento de inicio de sesión fallido', ['email' => $email]);
        return ['success' => false, 'message' => 'Credenciales incorrectas. Verifica correo o contraseña.'];
    } catch (\Throwable $e) {
        app_log('Error al intentar login', ['error' => $e->getMessage()]);
        return ['success' => false, 'message' => 'Error de conexión con la base de datos. Revisa la configuración.'];
    }
}

/**
 * Cierra la sesión activa
 */
function logout_user(): void
{
    if (is_logged_in()) {
        app_log('Cierre de sesión', ['user_id' => $_SESSION['user']['id'] ?? null]);
    }
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }
    session_destroy();
}
