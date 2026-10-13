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
        <?php if (is_admin()): ?>
        <li>
          <a href="users.php" class="nav-link <?= $currentPage === 'users.php' ? 'active' : '' ?>">
            <i class="ri-user-settings-line"></i> Usuarios
          </a>
        </li>
        <?php endif; ?>
        <li>
          <a href="logs.php" class="nav-link <?= $currentPage === 'logs.php' ? 'active' : '' ?>">
            <i class="ri-terminal-box-line"></i> Logs
          </a>
        </li>
      </ul>
    </nav>

    <div class="nav-user">
      <span class="user-badge" title="<?= htmlspecialchars($user['email'] ?? '') ?>">
        <i class="ri-user-3-line"></i> <?= htmlspecialchars($user['name'] ?? $user['email'] ?? 'Admin') ?>
      </span>
      <a href="logout.php" class="btn-logout" title="Cerrar Sesión">
        <i class="ri-logout-box-r-line"></i> Salir
      </a>
    </div>
    <?php endif; ?>
  </div>
</header>

<main class="main-content">
