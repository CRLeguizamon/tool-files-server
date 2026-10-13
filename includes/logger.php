<?php
/**
 * Sistema de Logging para Depuración
 * Escribe trazas y eventos clave en logs/debug.log
 */

declare(strict_types=1);

if (!function_exists('app_log')) {
    function app_log(string $message, array $context = []): void
    {
        $logDir = dirname(__DIR__) . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/debug.log';
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | Contexto: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $formatted = sprintf("[%s] [%s] %s%s\n", $timestamp, $ip, $message, $contextStr);
        @file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX);
    }
}
