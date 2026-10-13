<?php
/**
 * Cierre de Sesión
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logout_user();
header('Location: ' . base_url('login.php'));
exit;
