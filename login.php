<?php
/**
 * Pantalla de Inicio de Sesión
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Si ya tiene sesión, redirigir al panel principal
if (is_logged_in()) {
    header('Location: ' . base_url('index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verify_csrf($csrf)) {
        $error = 'Token de seguridad inválido. Por favor intenta de nuevo.';
    } else {
        $result = attempt_login($email, $password);
        if ($result['success']) {
            header('Location: ' . base_url('index.php'));
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

$pageTitle = 'Iniciar Sesión';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> • <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= APP_VERSION ?>">
  <style>
    body {
      background: radial-gradient(circle at 50% 20%, #f1f5f9 0%, #e2e8f0 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .login-container {
      max-width: 420px;
      width: 100%;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 2.5rem 2rem;
      box-shadow: var(--shadow-lg);
    }
    .login-header {
      text-align: center;
      margin-bottom: 2rem;
    }
    .login-icon {
      width: 52px;
      height: 52px;
      background: var(--primary);
      color: white;
      border-radius: var(--radius-md);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      margin-bottom: 1rem;
      box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
    }
    .login-alert {
      background: var(--danger-light);
      color: var(--danger);
      padding: 0.75rem 1rem;
      border-radius: var(--radius-sm);
      font-size: 0.875rem;
      margin-bottom: 1.25rem;
      border: 1px solid #fecaca;
    }
    .hint-box {
      margin-top: 1.5rem;
      padding: 0.85rem 1rem;
      background: var(--bg-subtle);
      border: 1px dashed var(--border);
      border-radius: var(--radius-sm);
      font-size: 0.825rem;
      color: var(--text-muted);
    }
    .hint-box strong {
      color: var(--text-main);
    }
  </style>
</head>
<body>

<div class="login-container">
  <div class="login-header">
    <div class="login-icon"><i class="ri-shield-keyhole-line"></i></div>
    <h1 style="font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em;">Acceso al Panel</h1>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;"><?= APP_NAME ?></p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="login-alert">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST" action="login.php">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <div class="form-group">
      <label class="form-label" for="email">Correo Electrónico</label>
      <input 
        type="email" 
        id="email" 
        name="email" 
        class="form-input" 
        required 
        autofocus 
        placeholder="admin@ejemplo.com"
        value="<?= htmlspecialchars($_POST['email'] ?? 'hola@devcristian.com') ?>"
      >
    </div>

    <div class="form-group">
      <label class="form-label" for="password">Contraseña</label>
      <input 
        type="password" 
        id="password" 
        name="password" 
        class="form-input" 
        required 
        placeholder="••••••••"
      >
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 0.5rem; padding: 0.75rem;">
      <i class="ri-login-box-line"></i> Entrar al Sistema
    </button>
  </form>

  <div class="hint-box">
    <strong>Credenciales iniciales:</strong><br>
    Usuario: <code>hola@devcristian.com</code><br>
    Clave: <code>admin</code>
  </div>
</div>

</body>
</html>
