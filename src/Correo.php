<?php

declare(strict_types=1);

namespace Cooki;

use Throwable;

/**
 * Envío de correos.
 * - Si en .env está MAIL_HOST, se usa el cliente SMTP propio (Smtp): con Docker va a Mailpit
 *   (http://localhost:8025, nadie lo recibe de verdad); en el hosting, a Gmail (smtp.gmail.com) con la cuenta del
 *   proyecto y una contraseña de aplicación (docs/publicar.md).
 * - Si no, se usa la función mail() de PHP. Con XAMPP mail() necesita un servidor de correo configurado;
 *   si no lo hay devuelve false, y lo que dependa del correo (por ejemplo, una consulta) igual queda guardado.
 */
final class Correo
{
    public static function enviar(string $para, string $asunto, string $cuerpo, string $responderA = ''): bool
    {
        return self::intentar($para, $asunto, $cuerpo, $responderA) === null;
    }

    /**
     * Igual que enviar(), pero devuelve null si salió o el motivo si no salió (para "Probar el correo" de la
     * administración). El motivo es la respuesta del servidor de correo: nunca incluye la contraseña.
     */
    public static function intentar(string $para, string $asunto, string $cuerpo, string $responderA = ''): ?string
    {
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return 'La dirección de destino no es válida.';
        }
        $remitente = Config::get('MAIL_REMITENTE', 'no-responder@cooki.test');
        // Solo se agrega Reply-To si el email es válido: un salto de línea ahí permitiría
        // inyectar encabezados y usar el formulario para mandar spam.
        $responderA = $responderA !== '' && filter_var($responderA, FILTER_VALIDATE_EMAIL) ? $responderA : '';

        $smtp = Smtp::desdeConfig();
        if ($smtp !== null) {
            try {
                $smtp->enviar($remitente, $para, self::armar($remitente, $para, $asunto, $cuerpo, $responderA));
                return null;
            } catch (Throwable $e) {
                error_log('Correo no enviado a ' . $para . ': ' . $e->getMessage());
                return $e->getMessage();
            }
        }

        $encabezados = [
            'From' => 'Cooki <' . $remitente . '>',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Mailer' => 'PHP/' . PHP_VERSION,
        ];
        if ($responderA !== '') {
            $encabezados['Reply-To'] = $responderA;
        }
        // La @ evita que el aviso de PHP se muestre en la página si no hay servidor de correo
        return @mail($para, self::asunto($asunto), $cuerpo, $encabezados)
            ? null
            : 'La función mail() de PHP no pudo enviarlo (MAIL_HOST está vacío y el servidor no tiene correo).';
    }

    /**
     * Arma el mensaje completo (encabezados + cuerpo) como lo pide el formato de los correos (RFC 5322).
     * El cuerpo va en base64: así los acentos y las líneas largas viajan sin problemas.
     */
    public static function armar(
        string $de,
        string $para,
        string $asunto,
        string $cuerpo,
        string $responderA = ''
    ): string {
        $dominio = substr((string) strrchr($de, '@'), 1) ?: 'cooki.test';
        $encabezados = [
            'Date: ' . date(DATE_RFC2822),
            'From: Cooki <' . $de . '>',
            'To: <' . $para . '>',
            'Subject: ' . self::asunto($asunto),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $dominio . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if ($responderA !== '') {
            $encabezados[] = 'Reply-To: <' . $responderA . '>';
        }
        return implode("\r\n", $encabezados) . "\r\n\r\n" . rtrim(chunk_split(base64_encode($cuerpo), 76, "\r\n"));
    }

    /** Asunto con acentos codificado (=?UTF-8?B?...?=) y sin saltos de línea (evita inyectar encabezados). */
    private static function asunto(string $asunto): string
    {
        return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $asunto)) . '?=';
    }
}
