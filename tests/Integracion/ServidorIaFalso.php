<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

/**
 * Levanta servidor-ia-falso.php (un Gemini de mentira) en un puerto libre, una sola vez para todas las pruebas,
 * y lee lo que recibió. Cada prueba usa su propio registro para contar sus llamadas.
 */
final class ServidorIaFalso
{
    private static string $base = '';

    /** Dirección para IA_URL: http://127.0.0.1:<puerto>/<modo>/<registro> */
    public static function url(string $modo, string $registro): string
    {
        if (self::$base === '') {
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            $puerto = (int) explode(':', (string) stream_socket_get_name($socket, false))[1];
            fclose($socket);
            $comando = sprintf(
                '%s -S 127.0.0.1:%d %s',
                escapeshellarg(PHP_BINARY),
                $puerto,
                escapeshellarg(__DIR__ . '/servidor-ia-falso.php')
            );
            $nulo = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            $proceso = proc_open($comando, [1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w']], $tuberias);
            register_shutdown_function(fn () => proc_terminate($proceso));
            for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $puerto) === false; $i++) {
                usleep(100_000);
            }
            self::$base = 'http://127.0.0.1:' . $puerto;
        }
        return self::$base . '/' . $modo . '/' . $registro;
    }

    /** Un nombre de registro nuevo (y sin pedidos todavía). */
    public static function nuevoRegistro(): string
    {
        $registro = 'r' . bin2hex(random_bytes(6));
        @unlink(self::archivo($registro));
        return $registro;
    }

    /**
     * Los pedidos que recibió con ese registro, en orden.
     * @return list<array{modo: string, ruta: string, clave: string, pedido: array<string, mixed>}>
     */
    public static function pedidos(string $registro): array
    {
        $archivo = self::archivo($registro);
        if (!is_file($archivo)) {
            return [];
        }
        $lineas = file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        return array_map(fn ($linea) => json_decode($linea, true), $lineas);
    }

    private static function archivo(string $registro): string
    {
        return sys_get_temp_dir() . "/ia-falso-{$registro}.log";
    }
}
