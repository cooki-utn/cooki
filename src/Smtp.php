<?php

declare(strict_types=1);

namespace Cooki;

use RuntimeException;

/**
 * Cliente SMTP propio (sin Composer): habla directamente con el servidor de correo por un socket.
 *
 * Una conversación SMTP es una serie de comandos de texto; cada respuesta empieza con un código de 3 cifras:
 *   220 bienvenida → EHLO → [STARTTLS → EHLO] → [AUTH LOGIN → usuario → contraseña] →
 *   MAIL FROM → RCPT TO → DATA → (el mensaje, terminado en una línea con un punto) → QUIT
 *
 * Seguridad (MAIL_SEGURIDAD en .env):
 *   - ninguna: sin cifrado (Mailpit en Docker, puerto 1025)
 *   - tls:     empieza sin cifrar y pasa a cifrado con STARTTLS (puerto 587, lo más común)
 *   - ssl:     cifrado desde el principio (puerto 465)
 * Si algo falla, lanza RuntimeException con la respuesta del servidor (Correo la registra en el log).
 */
final class Smtp
{
    /** @var resource|null */
    private $conexion = null;

    public function __construct(
        private string $host,
        private int $puerto,
        private string $seguridad = 'ninguna',
        private string $usuario = '',
        private string $contrasena = '',
        private int $espera = 8   // segundos: con el límite de 20 s por página del hosting, sobra margen
    ) {
    }

    /** El cliente configurado en .env, o null si no hay MAIL_HOST (entonces se usa mail() de PHP). */
    public static function desdeConfig(): ?self
    {
        $host = Config::get('MAIL_HOST');
        if ($host === '') {
            return null;
        }
        $seguridad = Config::get('MAIL_SEGURIDAD', 'ninguna');
        $porDefecto = match ($seguridad) {
            'ssl' => '465',
            'tls' => '587',
            default => '25',
        };
        return new self(
            $host,
            (int) Config::get('MAIL_PORT', $porDefecto),
            $seguridad,
            Config::get('MAIL_USUARIO'),
            Config::get('MAIL_CONTRASENA')
        );
    }

    /** Envía un mensaje ya armado (encabezados + cuerpo, ver Correo::armar) de $de a $para. */
    public function enviar(string $de, string $para, string $mensaje): void
    {
        $de = self::direccion($de);
        $para = self::direccion($para);
        $destino = ($this->seguridad === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->puerto;
        $error = '';
        $conexion = @stream_socket_client($destino, $numero, $error, $this->espera);
        if ($conexion === false) {
            throw new RuntimeException("No se pudo conectar a {$destino}: {$error}");
        }
        $this->conexion = $conexion;
        stream_set_timeout($conexion, $this->espera);
        try {
            $this->esperar([220]);
            $this->saludar();
            if ($this->seguridad === 'tls') {
                $this->comando('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($conexion, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('No se pudo activar el cifrado TLS');
                }
                $this->saludar();   // después de STARTTLS hay que volver a presentarse
            }
            if ($this->usuario !== '') {
                $this->comando('AUTH LOGIN', [334]);
                $this->comando(base64_encode($this->usuario), [334], 'AUTH (usuario)');
                $this->comando(base64_encode($this->contrasena), [235], 'AUTH (contraseña)');
            }
            $this->comando('MAIL FROM:<' . $de . '>', [250]);
            $this->comando('RCPT TO:<' . $para . '>', [250, 251]);
            $this->comando('DATA', [354]);
            $this->comando(self::paraData($mensaje) . "\r\n.", [250], 'DATA (el mensaje)');
            $this->comando('QUIT', [221]);
        } finally {
            fclose($conexion);
            $this->conexion = null;
        }
    }

    /**
     * Prepara el mensaje para DATA: saltos de línea CRLF y, si una línea empieza con un punto, se le agrega otro
     * ("dot-stuffing"): una línea con un solo punto significa "fin del mensaje".
     */
    public static function paraData(string $mensaje): string
    {
        $lineas = preg_split('/\r\n|\r|\n/', $mensaje) ?: [];
        return implode("\r\n", array_map(fn (string $l) => str_starts_with($l, '.') ? '.' . $l : $l, $lineas));
    }

    /** Solo direcciones de correo válidas: evita inyectar comandos SMTP con saltos de línea. */
    private static function direccion(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException("Dirección de correo no válida: {$email}");
        }
        return $email;
    }

    private function saludar(): void
    {
        $nombre = preg_replace('/[^A-Za-z0-9.-]/', '', (string) gethostname()) ?: 'cooki.local';
        $this->comando('EHLO ' . $nombre, [250]);
    }

    /** @param list<int> $esperados códigos de respuesta aceptados */
    private function comando(string $linea, array $esperados, string $nombre = ''): string
    {
        fwrite($this->conexion, $linea . "\r\n");
        return $this->esperar($esperados, $nombre !== '' ? $nombre : explode(' ', $linea)[0]);
    }

    /**
     * Lee una respuesta completa (puede tener varias líneas: "250-..." sigue, "250 ..." es la última).
     * @param list<int> $esperados
     */
    private function esperar(array $esperados, string $despuesDe = 'la conexión'): string
    {
        $respuesta = '';
        while (($linea = fgets($this->conexion, 1024)) !== false) {
            $respuesta .= $linea;
            if (strlen($linea) < 4 || $linea[3] !== '-') {
                break;
            }
        }
        $codigo = (int) substr($respuesta, 0, 3);
        if (!in_array($codigo, $esperados, true)) {
            $texto = trim($respuesta) !== '' ? trim($respuesta) : 'sin respuesta (tiempo agotado)';
            throw new RuntimeException("El servidor de correo rechazó {$despuesDe}: {$texto}");
        }
        return $respuesta;
    }
}
