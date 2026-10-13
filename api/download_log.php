<?php
/**
 * API: Descargar archivo debug.log
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_admin();

$logFile = LOGS_DIR . '/debug.log';

if (!file_exists($logFile)) {
    http_response_code(404);
    die('El archivo debug.log aún no contiene registros.');
}

$filename = 'debug_' . date('Y-m-d_H-i-s') . '.log';

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($logFile));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($logFile);
exit;
