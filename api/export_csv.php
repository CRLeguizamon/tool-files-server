<?php
/**
 * API: Exportación de Medios a CSV con formato compatible con Excel (BOM UTF-8)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (!is_logged_in()) {
    http_response_code(401);
    die('Acceso no autorizado.');
}

try {
    $pdo = get_db();
    $stmt = $pdo->query("SELECT * FROM `media` ORDER BY `id` DESC");
    $items = $stmt->fetchAll();

    $filename = sprintf('medios_%s.csv', date('Y-m-d_His'));

    // Encabezados HTTP para descarga de archivo CSV
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Escribir BOM UTF-8 para compatibilidad directa con Microsoft Excel en español
    fputs($output, "\xEF\xBB\xBF");

    // Fila de encabezados de columnas
    fputcsv($output, [
        'ID',
        'Nombre Archivo',
        'Nombre Original',
        'URL Directa',
        'URL Miniatura',
        'Ancho (px)',
        'Alto (px)',
        'Dimensiones',
        'Tamaño Original (KB)',
        'Tamaño Optimizado (KB)',
        'Ahorro (%)',
        'Formato MIME',
        'Comprimido',
        'Fecha de Creación',
    ]);

    foreach ($items as $row) {
        $origKb = round((int) $row['original_size'] / 1024, 2);
        $finalKb = round((int) $row['file_size'] / 1024, 2);
        $savedPercent = $row['original_size'] > 0
            ? round((($row['original_size'] - $row['file_size']) / $row['original_size']) * 100, 1)
            : 0;

        fputcsv($output, [
            $row['id'],
            $row['filename'],
            $row['original_name'],
            base_url($row['file_path']),
            base_url($row['thumb_path']),
            $row['width'],
            $row['height'],
            "{$row['width']}x{$row['height']}",
            $origKb,
            $finalKb,
            $savedPercent . '%',
            $row['mime_type'],
            $row['is_compressed'] ? 'Sí' : 'No',
            $row['created_at'],
        ]);
    }

    fclose($output);
    app_log('Exportación CSV completada', ['total_filas' => count($items), 'archivo' => $filename]);
    exit;

} catch (\Throwable $e) {
    app_log('Error al exportar CSV', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo "Error al generar el archivo CSV: " . htmlspecialchars($e->getMessage());
    exit;
}
