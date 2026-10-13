<?php
/**
 * Conexión y Gestión de Base de Datos MySQL (PDO)
 * Incluye auto-inicialización de tablas y admin por defecto.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// =========================================================================
// Credenciales de la Base de Datos (Hostinger)
// Si cambias tu contraseña o usuario, edita estas líneas:
// =========================================================================
defined('DB_HOST') || define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
defined('DB_PORT') || define('DB_PORT', getenv('DB_PORT') ?: '3306');
defined('DB_NAME') || define('DB_NAME', getenv('DB_NAME') ?: 'u757198907_importsdvcris');
defined('DB_USER') || define('DB_USER', getenv('DB_USER') ?: 'u757198907_importsdvcris');
defined('DB_PASS') || define('DB_PASS', getenv('DB_PASS') ?: '7f85M4+K;');

/**
 * Obtiene la conexión PDO Singleton a MySQL
 */
function get_db(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Auto-inicializar tablas si no existen
        init_database_schema($pdo);
        
        return $pdo;
    } catch (PDOException $e) {
        app_log('Error crítico de conexión a la base de datos MySQL', [
            'error' => $e->getMessage(),
            'code'  => $e->getCode(),
            'host'  => DB_HOST,
            'db'    => DB_NAME,
            'user'  => DB_USER,
        ]);
        throw new RuntimeException("Error conectando a la base de datos: " . $e->getMessage());
    }
}

/**
 * Inicializa automáticamente el esquema de tablas y el usuario administrador inicial
 */
function init_database_schema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    try {
        // 1. Tabla de Usuarios
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `users` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `email` VARCHAR(191) NOT NULL UNIQUE,
              `password` VARCHAR(255) NOT NULL,
              `name` VARCHAR(100) NOT NULL DEFAULT 'Administrador',
              `role` VARCHAR(30) NOT NULL DEFAULT 'admin',
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // Migración automática: asegurar que 'name' y 'role' existen en 'users' si la tabla ya existía previamente
        try {
            $userCols = $pdo->query("SHOW COLUMNS FROM `users`")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('name', $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `name` VARCHAR(100) NOT NULL DEFAULT 'Administrador' AFTER `password`");
            }
            if (!in_array('role', $userCols, true)) {
                $pdo->exec("ALTER TABLE `users` ADD COLUMN `role` VARCHAR(30) NOT NULL DEFAULT 'admin' AFTER `name`");
            }
        } catch (\Throwable $e) {
            // Ignorar si las columnas ya existen
        }

        // 2. Tabla de Medios / Imágenes
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `media` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `filename` VARCHAR(255) NOT NULL,
              `original_name` VARCHAR(255) NOT NULL,
              `file_path` VARCHAR(255) NOT NULL,
              `thumb_path` VARCHAR(255) NOT NULL,
              `file_size` BIGINT NOT NULL,
              `original_size` BIGINT NOT NULL,
              `width` INT NOT NULL,
              `height` INT NOT NULL,
              `mime_type` VARCHAR(50) NOT NULL,
              `is_compressed` TINYINT(1) NOT NULL DEFAULT 0,
              `created_by` INT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              INDEX `idx_media_created_at` (`created_at`),
              INDEX `idx_media_filename` (`filename`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        // 3. Crear usuario admin inicial si no existe
        $stmt = $pdo->prepare("SELECT `id` FROM `users` WHERE `email` = ? LIMIT 1");
        $stmt->execute(['hola@devcristian.com']);
        $existingAdmin = $stmt->fetch();

        if (!$existingAdmin) {
            $hashedPassword = password_hash('admin', PASSWORD_DEFAULT);
            $insert = $pdo->prepare("
                INSERT INTO `users` (`email`, `password`, `name`, `role`)
                VALUES (?, ?, 'Admin Principal', 'admin')
            ");
            $insert->execute(['hola@devcristian.com', $hashedPassword]);
            app_log('Usuario admin inicial creado exitosamente', ['email' => 'hola@devcristian.com']);
        }

        $initialized = true;
    } catch (PDOException $e) {
        app_log('Error en init_database_schema', ['error' => $e->getMessage()]);
    }
}
