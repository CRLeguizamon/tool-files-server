<?php
/**
 * Servicio de Envío de Correos Electrónicos mediante SMTP Nativo en PHP
 * Sin dependencias externas pesadas, compatible con SSL/TLS en Hostinger PHP 8.5
 */

declare(strict_types=1);

require_once __DIR__ . '/settings.php';

class Mailer
{
    /**
     * Envía un correo electrónico a través de conexión SMTP por sockets en PHP puro
     *
     * @param string $to Correo del destinatario
     * @param string $subject Asunto del correo
     * @param string $htmlBody Cuerpo del correo en HTML
     * @param array|null $customConfig Configuración SMTP personalizada (opcional, si es null lee de BD)
     * @return array ['success' => bool, 'message' => string, 'transcript' => array]
     */
    public static function send(string $to, string $subject, string $htmlBody, ?array $customConfig = null): array
    {
        $config = $customConfig ?? get_smtp_config();

        $host = trim((string) ($config['host'] ?? ''));
        $port = (int) ($config['port'] ?? 465);
        $encryption = strtolower((string) ($config['encryption'] ?? 'ssl'));
        $username = trim((string) ($config['username'] ?? ''));
        $password = (string) ($config['password'] ?? '');
        $fromEmail = trim((string) ($config['from_email'] ?? $username));
        $fromName = trim((string) ($config['from_name'] ?? APP_NAME));

        $transcript = [];
        $logStep = static function (string $dir, string $msg) use (&$transcript): void {
            $transcript[] = ['direction' => $dir, 'message' => trim($msg), 'time' => microtime(true)];
        };

        if (empty($host)) {
            return ['success' => false, 'message' => 'El servidor SMTP (Host) no está configurado.', 'transcript' => $transcript];
        }

        if (empty($fromEmail)) {
            $fromEmail = !empty($username) ? $username : 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        }

        $remoteAddress = $host . ':' . $port;
        if ($encryption === 'ssl') {
            $remoteAddress = 'ssl://' . $host . ':' . $port;
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ]
        ]);

        $timeout = 15;
        $errno = 0;
        $errstr = '';

        $logStep('SYS', "Conectando a {$remoteAddress} (Timeout: {$timeout}s)...");
        $socket = @stream_socket_client($remoteAddress, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if (!$socket) {
            $errMsg = "Fallo al conectar con el servidor SMTP ({$errno}): {$errstr}";
            $logStep('ERR', $errMsg);
            app_log('SMTP Connection Error', ['target' => $remoteAddress, 'error' => $errMsg]);
            return ['success' => false, 'message' => $errMsg, 'transcript' => $transcript];
        }

        stream_set_timeout($socket, $timeout);

        // Helper para leer respuesta del servidor SMTP (soporta respuestas multilínea)
        $readResponse = static function () use ($socket, &$logStep): string {
            $response = '';
            while (!feof($socket)) {
                $line = fgets($socket, 515);
                if ($line === false) {
                    break;
                }
                $response .= $line;
                // Si el 4to carácter es un espacio o fin de línea, terminó la respuesta
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            $logStep('SERVER', $response);
            return $response;
        };

        // Helper para enviar comandos SMTP
        $sendCommand = static function (string $cmd, string $mask = '') use ($socket, &$logStep): void {
            $logStep('CLIENT', $mask !== '' ? $mask : $cmd);
            fwrite($socket, $cmd . "\r\n");
        };

        try {
            // 1. Saludo inicial del servidor (espera 220)
            $res = $readResponse();
            if (!str_starts_with($res, '220')) {
                throw new RuntimeException("El servidor rechazó el saludo inicial: " . trim($res));
            }

            // 2. EHLO
            $clientDomain = $_SERVER['SERVER_NAME'] ?? 'localhost';
            $sendCommand("EHLO {$clientDomain}");
            $res = $readResponse();
            if (!str_starts_with($res, '250')) {
                $sendCommand("HELO {$clientDomain}");
                $res = $readResponse();
                if (!str_starts_with($res, '250')) {
                    throw new RuntimeException("Error en protocolo EHLO/HELO: " . trim($res));
                }
            }

            // 3. STARTTLS si el cifrado es TLS
            if ($encryption === 'tls') {
                $sendCommand('STARTTLS');
                $res = $readResponse();
                if (!str_starts_with($res, '220')) {
                    throw new RuntimeException("El servidor no aceptó STARTTLS: " . trim($res));
                }
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
                if (!$crypto) {
                    throw new RuntimeException("No fue posible negociar el cifrado TLS con el servidor.");
                }
                $logStep('SYS', 'Cifrado TLS establecido con éxito.');

                // Reenviar EHLO tras establecer TLS
                $sendCommand("EHLO {$clientDomain}");
                $res = $readResponse();
                if (!str_starts_with($res, '250')) {
                    throw new RuntimeException("Error en EHLO post-TLS: " . trim($res));
                }
            }

            // 4. Autenticación (AUTH LOGIN)
            if (!empty($config['auth'])) {
                $sendCommand('AUTH LOGIN');
                $res = $readResponse();
                if (!str_starts_with($res, '334')) {
                    throw new RuntimeException("El servidor rechazó AUTH LOGIN: " . trim($res));
                }

                // Usuario
                $sendCommand(base64_encode($username), '[BASE64 USERNAME]');
                $res = $readResponse();
                if (!str_starts_with($res, '334')) {
                    throw new RuntimeException("Usuario SMTP rechazado: " . trim($res));
                }

                // Contraseña
                $sendCommand(base64_encode($password), '[BASE64 PASSWORD]');
                $res = $readResponse();
                if (!str_starts_with($res, '235')) {
                    throw new RuntimeException("Autenticación SMTP fallida (usuario o clave incorrectos): " . trim($res));
                }
                $logStep('SYS', 'Autenticación SMTP exitosa.');
            }

            // 5. MAIL FROM
            $sendCommand("MAIL FROM:<{$fromEmail}>");
            $res = $readResponse();
            if (!str_starts_with($res, '250')) {
                throw new RuntimeException("Error en MAIL FROM ({$fromEmail}): " . trim($res));
            }

            // 6. RCPT TO
            $sendCommand("RCPT TO:<{$to}>");
            $res = $readResponse();
            if (!str_starts_with($res, '250') && !str_starts_with($res, '251')) {
                throw new RuntimeException("Destinatario rechazado ({$to}): " . trim($res));
            }

            // 7. DATA
            $sendCommand('DATA');
            $res = $readResponse();
            if (!str_starts_with($res, '354')) {
                throw new RuntimeException("El servidor no aceptó el comando DATA: " . trim($res));
            }

            // 8. Construcción de cabeceras y cuerpo
            $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
            $encodedFromName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $date = date('r');
            $messageId = '<' . time() . '.' . bin2hex(random_bytes(8)) . '@' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . '>';

            $headers = [
                "Date: {$date}",
                "From: {$encodedFromName} <{$fromEmail}>",
                "To: <{$to}>",
                "Subject: {$encodedSubject}",
                "Message-ID: {$messageId}",
                "MIME-Version: 1.0",
                "Content-Type: text/html; charset=UTF-8",
                "Content-Transfer-Encoding: 8bit",
                "X-Mailer: ImageOptimizer-NativeMailer/1.0",
            ];

            // Reemplazo de puntos iniciales para evitar dot-stuffing en SMTP
            $safeBody = preg_replace('/^\./m', '..', $htmlBody);
            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $safeBody . "\r\n.";

            $sendCommand($payload, '[CONTENIDO HTML Y CABECERAS DEL MENSAJE]');
            $res = $readResponse();
            if (!str_starts_with($res, '250')) {
                throw new RuntimeException("El servidor no confirmó la entrega del mensaje: " . trim($res));
            }

            // 9. QUIT
            $sendCommand('QUIT');
            $readResponse();
            @fclose($socket);

            app_log('Correo enviado con éxito vía SMTP', [
                'to'      => $to,
                'subject' => $subject,
                'host'    => $host,
            ]);

            return [
                'success'    => true,
                'message'    => "Correo enviado exitosamente a {$to}.",
                'transcript' => $transcript,
            ];

        } catch (\Throwable $e) {
            @fclose($socket);
            $errorMsg = $e->getMessage();
            $logStep('ERR', $errorMsg);
            app_log('Fallo enviando correo SMTP', ['to' => $to, 'error' => $errorMsg]);
            return [
                'success'    => false,
                'message'    => $errorMsg,
                'transcript' => $transcript,
            ];
        }
    }

    /**
     * Genera una plantilla de correo electrónico HTML moderna, limpia y responsiva
     *
     * @param string $title Título destacado del correo
     * @param string $contentHtml Contenido principal en HTML
     * @param string|null $ctaText Texto del botón de acción (opcional)
     * @param string|null $ctaUrl URL del botón de acción (opcional)
     * @param string|null $footerNote Nota o aviso secundario al pie (opcional)
     * @return string
     */
    public static function getTemplate(
        string $title,
        string $contentHtml,
        ?string $ctaText = null,
        ?string $ctaUrl = null,
        ?string $footerNote = null
    ): string {
        $year = date('Y');
        $appName = htmlspecialchars(APP_NAME);
        $safeTitle = htmlspecialchars($title);

        $buttonHtml = '';
        if (!empty($ctaText) && !empty($ctaUrl)) {
            $safeCtaText = htmlspecialchars($ctaText);
            $safeCtaUrl = htmlspecialchars($ctaUrl);
            $buttonHtml = "
              <div style=\"text-align: center; margin: 2rem 0 1.5rem;\">
                <a href=\"{$safeCtaUrl}\" target=\"_blank\" style=\"display: inline-block; background-color: #4f46e5; color: #ffffff; text-decoration: none; padding: 12px 28px; font-weight: 600; font-size: 15px; border-radius: 8px; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.25); letter-spacing: -0.01em;\">
                  {$safeCtaText} &rarr;
                </a>
              </div>
            ";
        }

        $footerNoteHtml = $footerNote !== null 
            ? "<p style=\"margin: 0 0 10px; font-size: 12px; color: #94a3b8;\">" . htmlspecialchars($footerNote) . "</p>"
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$safeTitle}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; color: #0f172a; line-height: 1.6;">

  <table width="100%" border="0" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; padding: 35px 15px;">
    <tr>
      <td align="center">
        <!-- Contenedor Principal -->
        <table width="100%" border="0" cellpadding="0" cellspacing="0" style="max-width: 560px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 14px; overflow: hidden; box-shadow: 0 6px 16px -2px rgba(15, 23, 42, 0.04);">
          
          <!-- Encabezado con Logo/Marca -->
          <tr>
            <td style="padding: 28px 32px 20px; background-color: #ffffff; border-bottom: 1px solid #f1f5f9;">
              <table width="100%" border="0" cellpadding="0" cellspacing="0">
                <tr>
                  <td style="vertical-align: middle;">
                    <div style="display: inline-block; background-color: #4f46e5; color: #ffffff; font-weight: 800; font-size: 16px; width: 34px; height: 34px; line-height: 34px; text-align: center; border-radius: 8px; margin-right: 8px;">⚡</div>
                    <span style="font-weight: 700; font-size: 18px; color: #0f172a; letter-spacing: -0.02em; vertical-align: middle;">{$appName}</span>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Contenido Central -->
          <tr>
            <td style="padding: 32px 32px 28px;">
              <h1 style="margin: 0 0 16px; font-size: 21px; font-weight: 700; color: #0f172a; letter-spacing: -0.02em;">{$safeTitle}</h1>
              
              <div style="font-size: 15px; color: #334155; line-height: 1.65;">
                {$contentHtml}
              </div>

              {$buttonHtml}
            </td>
          </tr>

          <!-- Pie de Página -->
          <tr>
            <td style="padding: 20px 32px 26px; background-color: #fafbfc; border-top: 1px solid #f1f5f9; text-align: center;">
              {$footerNoteHtml}
              <p style="margin: 0; font-size: 12px; color: #94a3b8;">
                &copy; {$year} {$appName} &bull; Sistema minimalista de optimización y gestión de imágenes.
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>

</body>
</html>
HTML;
    }

    /**
     * Envía un correo de prueba de configuración SMTP
     *
     * @param string $recipient Correo de destino
     * @param array|null $customConfig Configuración opcional
     * @return array
     */
    public static function sendTestEmail(string $recipient, ?array $customConfig = null): array
    {
        $subject = 'Prueba de Conexión SMTP - ' . APP_NAME;
        $serverTime = date('Y-m-d H:i:s');
        $ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'Servidor';

        $htmlContent = <<<HTML
<p>Hola,</p>
<p>Este es un correo de prueba generado automáticamente para verificar que la configuración de tu servidor <strong>SMTP</strong> está funcionando de manera óptima en <strong>{$_SERVER['HTTP_HOST']}</strong>.</p>

<div style="background-color: #f1f5f9; border-radius: 8px; padding: 14px 18px; margin: 18px 0; font-size: 13px; font-family: monospace; color: #334155;">
  <div><strong>Estado:</strong> Conexión y autenticación exitosas</div>
  <div><strong>Fecha del servidor:</strong> {$serverTime}</div>
  <div><strong>Destinatario:</strong> {$recipient}</div>
</div>

<p>Ya puedes utilizar este servicio para el envío de credenciales de cuentas nuevas y correos de restablecimiento de contraseña.</p>
HTML;

        $body = self::getTemplate(
            '¡Configuración SMTP Verificada con Éxito!',
            $htmlContent,
            'Ir a la Plataforma',
            base_url('index.php'),
            'Si no solicitaste esta prueba, puedes ignorar este mensaje con seguridad.'
        );

        return self::send($recipient, $subject, $body, $customConfig);
    }

    /**
     * Envía correo de bienvenida y entrega de credenciales a un nuevo usuario
     *
     * @param string $name Nombre del usuario
     * @param string $email Correo de acceso
     * @param string $password Contraseña temporal
     * @param string|null $loginUrl URL de acceso
     * @return array
     */
    public static function sendAccountCreatedEmail(
        string $name,
        string $email,
        string $password,
        ?string $loginUrl = null
    ): array {
        $subject = 'Bienvenido a ' . APP_NAME . ' - Tus Credenciales de Acceso';
        $url = $loginUrl ?? base_url('login.php');
        $safeName = htmlspecialchars($name);
        $safeEmail = htmlspecialchars($email);
        $safePass = htmlspecialchars($password);

        $htmlContent = <<<HTML
<p>Hola <strong>{$safeName}</strong>,</p>
<p>Se ha creado una cuenta para ti en la plataforma <strong>{$_SERVER['HTTP_HOST']}</strong>. A continuación encontrarás tus credenciales de acceso para comenzar a optimizar imágenes:</p>

<div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px 20px; margin: 20px 0;">
  <div style="margin-bottom: 8px; font-size: 14px;"><strong>Usuario / Correo:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #4f46e5;">{$safeEmail}</code></div>
  <div style="font-size: 14px;"><strong>Contraseña temporal:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; color: #0f172a; font-weight: 700;">{$safePass}</code></div>
</div>

<p style="font-size: 13px; color: #64748b;">Por seguridad, te sugerimos ingresar al sistema e ir a tu perfil para cambiar tu contraseña.</p>
HTML;

        $body = self::getTemplate(
            'Bienvenido a ' . APP_NAME,
            $htmlContent,
            'Iniciar Sesión',
            $url,
            'Este mensaje contiene credenciales confidenciales de acceso.'
        );

        return self::send($email, $subject, $body);
    }

    /**
     * Envía correo de restablecimiento de contraseña
     *
     * @param string $name Nombre del usuario
     * @param string $email Correo de destino
     * @param string $resetUrl Enlace seguro para restablecer clave
     * @return array
     */
    public static function sendPasswordResetEmail(string $name, string $email, string $resetUrl): array
    {
        $subject = 'Restablecimiento de Contraseña - ' . APP_NAME;
        $safeName = htmlspecialchars($name);

        $htmlContent = <<<HTML
<p>Hola <strong>{$safeName}</strong>,</p>
<p>Hemos recibido una solicitud para restablecer la contraseña de tu cuenta en <strong>{$_SERVER['HTTP_HOST']}</strong>.</p>
<p>Haz clic en el siguiente botón para definir una nueva contraseña. Por razones de seguridad, este enlace es de un solo uso:</p>
HTML;

        $body = self::getTemplate(
            'Restablecer Contraseña',
            $htmlContent,
            'Crear Nueva Contraseña',
            $resetUrl,
            'Si no solicitaste este cambio, puedes ignorar este correo. Tu contraseña actual no sufrirá modificaciones.'
        );

        return self::send($email, $subject, $body);
    }
}
