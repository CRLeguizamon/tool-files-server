<?php
/**
 * Cabecera y Navegación Común
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$user = current_user();
$title = isset($pageTitle) ? htmlspecialchars($pageTitle) . ' • ' . APP_NAME : APP_NAME;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?></title>
  <meta name="csrf-token" content="<?= csrf_token() ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= APP_VERSION ?>">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%234f46e5'><path d='M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1zm1 2v10h14V7H5zm2 8l3-4 2 2.5 3-3.5 3 5H7z'/></svg>">
</head>
<body>

<header class="site-header">
  <div class="nav-container">
    <a href="index.php" class="brand">
      <div class="brand-icon"><i class="ri-flashlight-line"></i></div>
      <span><?= APP_NAME ?></span>
    </a>

    <?php if (is_logged_in()): ?>
    <nav>
      <ul class="nav-links">
        <li>
          <a href="index.php" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
            <i class="ri-upload-cloud-2-line"></i> Subir y Optimizar
          </a>
        </li>
        <li>
          <a href="media.php" class="nav-link <?= $currentPage === 'media.php' ? 'active' : '' ?>">
            <i class="ri-image-line"></i> Medios
          </a>
        </li>
      </ul>
    </nav>

    <div class="nav-user-dropdown" id="user-dropdown-container">
      <button 
        type="button" 
        class="user-dropdown-trigger" 
        id="user-menu-btn" 
        aria-expanded="false" 
        aria-haspopup="true" 
        title="Opciones de perfil y configuración"
      >
        <span class="user-avatar-circle">
          <?= strtoupper(mb_substr((string) ($user['name'] ?? $user['email'] ?? 'U'), 0, 1)) ?>
        </span>
        <span class="user-name-text"><?= htmlspecialchars((string) ($user['name'] ?? $user['email'] ?? 'Usuario')) ?></span>
        <i class="ri-arrow-down-s-line dropdown-arrow"></i>
      </button>

      <div class="user-dropdown-menu" id="user-dropdown-menu" role="menu" aria-labelledby="user-menu-btn">
        <div class="dropdown-header">
          <div class="dropdown-user-name"><?= htmlspecialchars((string) ($user['name'] ?? 'Usuario')) ?></div>
          <div class="dropdown-user-email"><?= htmlspecialchars((string) ($user['email'] ?? '')) ?></div>
          <div class="dropdown-user-role">
            <span class="role-pill <?= is_admin() ? 'role-admin' : 'role-user' ?>">
              <i class="<?= is_admin() ? 'ri-shield-check-line' : 'ri-user-line' ?>"></i>
              <?= is_admin() ? 'Administrador' : 'Usuario' ?>
            </span>
          </div>
        </div>

        <div class="dropdown-divider"></div>

        <div class="dropdown-section">
          <a href="profile.php" class="dropdown-item <?= $currentPage === 'profile.php' ? 'active' : '' ?>" role="menuitem">
            <i class="ri-user-smile-line"></i>
            <span>Mi Perfil y Contraseña</span>
          </a>
        </div>

        <?php if (is_admin()): ?>
        <div class="dropdown-divider"></div>
        <div class="dropdown-section-title">Configuración</div>
        <div class="dropdown-section">
          <a href="users.php" class="dropdown-item <?= $currentPage === 'users.php' ? 'active' : '' ?>" role="menuitem">
            <i class="ri-group-line"></i>
            <span>Usuarios</span>
          </a>
          <a href="logs.php" class="dropdown-item <?= $currentPage === 'logs.php' ? 'active' : '' ?>" role="menuitem">
            <i class="ri-terminal-box-line"></i>
            <span>Logs</span>
          </a>
          <a href="smtp.php" class="dropdown-item <?= $currentPage === 'smtp.php' ? 'active' : '' ?>" role="menuitem">
            <i class="ri-mail-settings-line"></i>
            <span>SMTP</span>
          </a>
        </div>
        <?php endif; ?>

        <div class="dropdown-divider"></div>

        <div class="dropdown-section">
          <a href="logout.php" class="dropdown-item dropdown-item-danger" role="menuitem">
            <i class="ri-logout-box-r-line"></i>
            <span>Cerrar Sesión</span>
          </a>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>
</header>

<main class="main-content">
