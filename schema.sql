-- Estructura de Base de Datos para el Sistema de Optimización de Imágenes
-- Compatible con MySQL 5.7+ / 8.0+ / MariaDB 10.3+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- Tabla de Usuarios (Administradores)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(191) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL DEFAULT 'Administrador',
  `role` VARCHAR(30) NOT NULL DEFAULT 'admin',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- Inserción de usuario administrador inicial
-- Email: hola@devcristian.com | Contraseña inicial: admin
-- Hash generado con password_hash('admin', PASSWORD_DEFAULT)
-- -----------------------------------------------------
INSERT INTO `users` (`id`, `email`, `password`, `name`, `role`)
VALUES (1, 'hola@devcristian.com', '$2y$12$nOS7u7mPV/bPiCNLnRdFYuFeDAk3frwLtX5vjGUEjGUYxUTFc092.', 'Admin Principal', 'admin')
ON DUPLICATE KEY UPDATE `email` = VALUES(`email`);

-- -----------------------------------------------------
-- Tabla de Medios / Imágenes Optimizadas
-- -----------------------------------------------------
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

-- -----------------------------------------------------
-- Tabla de Configuraciones del Sistema (Settings / SMTP)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` LONGTEXT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_settings_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
