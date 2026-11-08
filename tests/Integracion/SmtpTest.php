<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

use Cooki\Correo;
use Cooki\Smtp;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Cliente SMTP propio (incremento 2 · P1), contra un servidor de mentira (servidor-smtp-falso.php)
 * que corre en otro proceso y guarda la conversación.
 */
final class SmtpTest extends TestCase
{
    private string $salida = '';
    /** @var resource|null */
    private $proceso = null;

    private function levantar(string $modo = ''): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $puerto = (int) explode(':', (string) stream_socket_get_name($socket, false))[1];
        fclose($socket);
        $this->salida = sys_get_temp_dir() . '/smtp-' . uniqid() . '.txt';
        $comando = sprintf(
            '%s %s %d %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(__DIR__ . '/servidor-smtp-falso.php'),
            $puerto,
            escapeshellarg($this->salida),
            $modo
        );
        $this->proceso = proc_open($comando, [], $tuberias);
        for ($i = 0; $i < 50 && !is_file($this->salida . '.listo'); $i++) {
            usleep(50_000);
        }
        return $puerto;
    }

    protected function tearDown(): void
    {
        if ($this->proceso) {
            proc_terminate($this->proceso);
        }
        @unlink($this->salida);
        @unlink($this->salida . '.listo');
    }

    private function conversacion(): string
    {
        for ($i = 0; $i < 50 && !is_file($this->salida); $i++) {
            usleep(50_000);
        }
        return (string) file_get_contents($this->salida);
    }

    public function testEnviaConAutenticacionYElServidorRecibeElMensaje(): void
    {
        $puerto = $this->levantar();
        $texto = "Hola, María.\n.Línea que empieza con punto";
        $mensaje = Correo::armar('no-responder@cooki.test', 'maria@cooki.test', 'Cambiar tu contraseña', $texto);
        (new Smtp('127.0.0.1', $puerto, 'ninguna', 'usuario', 'secreto'))
            ->enviar('no-responder@cooki.test', 'maria@cooki.test', $mensaje);

        $log = $this->conversacion();
        $this->assertStringContainsString('C: AUTH LOGIN', $log);
        $this->assertStringContainsString('C: ' . base64_encode('usuario'), $log);
        $this->assertStringContainsString('C: MAIL FROM:<no-responder@cooki.test>', $log);
        $this->assertStringContainsString('C: RCPT TO:<maria@cooki.test>', $log);
        $this->assertStringContainsString('S: 221 chau', $log);
        $this->assertStringContainsString('Subject: =?UTF-8?B?' . base64_encode('Cambiar tu contraseña') . '?=', $log);
        // El cuerpo va en base64: decodificado tiene los acentos y la línea con punto intacta
        preg_match('/C: \r?\n((?:C: [A-Za-z0-9+\/=]+\n)+)C: \./', $log, $m);
        $cuerpo = base64_decode(str_replace(['C: ', "\n"], '', $m[1] ?? ''));
        $this->assertSame($texto, $cuerpo);
    }

    public function testSiElServidorRechazaAvisaConLaRespuesta(): void
    {
        $puerto = $this->levantar('rechazar');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('550 no existe ese buzón');
        $mensaje = Correo::armar('a@cooki.test', 'nadie@cooki.test', 'Hola', 'Hola');
        (new Smtp('127.0.0.1', $puerto))->enviar('a@cooki.test', 'nadie@cooki.test', $mensaje);
    }

    public function testSinServidorAvisaQueNoPudoConectar(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No se pudo conectar');
        (new Smtp('127.0.0.1', 1, 'ninguna', '', '', 2))->enviar('a@cooki.test', 'b@cooki.test', 'x');
    }

    public function testElPuntoAlPrincipioDeUnaLineaSeDuplica(): void
    {
        $this->assertSame("hola\r\n..punto\r\nchau", Smtp::paraData("hola\n.punto\nchau"));
    }

    public function testNoAceptaDireccionesConSaltosDeLinea(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no válida');
        (new Smtp('127.0.0.1', 1))->enviar("a@cooki.test\r\nRCPT TO:<otro@x.com>", 'b@cooki.test', 'x');
    }

    public function testIntentarDevuelveElMotivoSiNoSePuedeMandar(): void
    {
        $this->assertSame('La dirección de destino no es válida.', Correo::intentar('no-es-un-correo', 'Hola', 'Hola'));
        $this->assertFalse(Correo::enviar('no-es-un-correo', 'Hola', 'Hola'));
    }
}
