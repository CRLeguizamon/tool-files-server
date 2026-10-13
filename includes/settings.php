<?php
/**
 * Servicio de Gestión de Configuraciones del Sistema (SMTP y Parámetros Globales)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

/**
 * Obtiene el valor de una configuración almacenada en la base de datos
 *
 * @param string $key Clave de configuración
 * @param string|null $default Valor por defecto en caso de no existir
 * @return string|null
 */
function get_setting(string $key, ?string $default = null): ?string
{
    static $cache = [];

    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("SELECT `setting_value` FROM `settings` WHERE `setting_key` = ? LIMIT 1");
        $stmt->execute([$key]);
        $val = $stmt->fetchColumn();

        $result = ($val !== false && $val !== null) ? (string) $val : $default;
        $cache[$key] = $result;
        return $result;
    } catch (\Throwable $e) {
        app_log('Error leyendo configuración', ['key' => $key, 'error' => $e->getMessage()]);
        return $default;
    }
}

/**
 * Guarda o actualiza una configuración en la base de datos (UPSERT)
 *
 * @param string $key Clave de configuración
 * @param string|null $value Valor a almacenar
 * @return bool
 */
function set_setting(string $key, ?string $value): bool
{
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO `settings` (`setting_key`, `setting_value`)
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`)
        ");
        $stmt->execute([$key, $value]);

        app_log('Configuración guardada', ['key' => $key]);
        return true;
    } catch (\Throwable $e) {
        app_log('Error guardando configuración', ['key' => $key, 'error' => $e->getMessage()]);
        return false;
    }
}

/**
 * Obtiene la configuración completa de SMTP
 *
 * @return array
 */
function get_smtp_config(): array
{
    return [
        'host'       => get_setting('smtp_host', 'smtp.hostinger.com'),
        'port'       => (int) get_setting('smtp_port', '465'),
        'encryption' => get_setting('smtp_encryption', 'ssl'), // ssl, tls, none
        'auth'       => (bool) (int) get_setting('smtp_auth', '1'),
        'username'   => get_setting('smtp_username', ''),
        'password'   => get_setting('smtp_password', ''),
        'from_email' => get_setting('smtp_from_email', ''),
        'from_name'  => get_setting('smtp_from_name', APP_NAME),
    ];
}

/**
 * Guarda un bloque de configuraciones SMTP
 *
 * @param array $config
 * @return bool
 */
function save_smtp_config(array $config): bool
{
    $success = true;
    $keys = [
        'smtp_host'       => (string) ($config['host'] ?? ''),
        'smtp_port'       => (string) ((int) ($config['port'] ?? 465)),
        'smtp_encryption' => in_array($config['encryption'] ?? 'ssl', ['ssl', 'tls', 'none'], true) ? $config['encryption'] : 'ssl',
        'smtp_auth'       => !empty($config['auth']) ? '1' : '0',
        'smtp_username'   => (string) ($config['username'] ?? ''),
        'smtp_from_email' => (string) ($config['from_email'] ?? ''),
        'smtp_from_name'  => (string) ($config['from_name'] ?? APP_NAME),
    ];

    // Solo actualizar contraseña si se proporcionó una nueva (evita sobrescribir con vacío)
    if (isset($config['password']) && $config['password'] !== '') {
        $keys['smtp_password'] = (string) $config['password'];
    }

    foreach ($keys as $k => $v) {
        if (!set_setting($k, $v)) {
            $success = false;
        }
    }

    return $success;
}
