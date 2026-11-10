<?php

declare(strict_types=1);

namespace Cooki\Tests\Integracion;

/**
 * Levanta el servidor de desarrollo de PHP en un puerto libre y hace peticiones HTTP reales a la API.
 * Guarda la cookie de sesión entre peticiones, como un navegador.
 *
 * Uso en una prueba:
 *   $api = ClienteApi::iniciar();
 *   [$estado, $datos] = $api->pedir('POST', '/usuarios', ['email' => 'a@b.com', ...]);
 */
final class ClienteApi
{
    /** @var resource|null */
    private static $proceso = null;
    private static int $puerto = 0;
    private string $cookie = '';

    public static function iniciar(): self
    {
        if (self::$proceso === null) {
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            self::$puerto = (int) explode(':', (string) stream_socket_get_name($socket, false))[1];
            fclose($socket);
            $raiz = dirname(__DIR__, 2);
            $comando = sprintf(
                '%s -S 127.0.0.1:%d -t %s %s',
                escapeshellarg(PHP_BINARY),
                self::$puerto,
                escapeshellarg($raiz . '/public'),
                escapeshellarg($raiz . '/public/router.php')
            );
            $nulo = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
            self::$proceso = proc_open($comando, [1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w']], $tuberias);
            register_shutdown_function(fn () => proc_terminate(self::$proceso));
            for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', self::$puerto) === false; $i++) {
                usleep(100_000);
            }
        }
        return new self();
    }

    /**
     * Levanta otro servidor de prueba con variables de entorno propias (tienen prioridad sobre el .env), para probar
     * una configuración distinta, como la del hosting. Devuelve su dirección base; se apaga al terminar las pruebas.
     * @param array<string, string> $variables
     */
    public static function servidorCon(array $variables): string
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $puerto = (int) explode(':', (string) stream_socket_get_name($socket, false))[1];
        fclose($socket);
        $raiz = dirname(__DIR__, 2);
        $comando = sprintf(
            '%s -S 127.0.0.1:%d -t %s %s',
            escapeshellarg(PHP_BINARY),
            $puerto,
            escapeshellarg($raiz . '/public'),
            escapeshellarg($raiz . '/public/router.php')
        );
        $nulo = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $salidas = [1 => ['file', $nulo, 'w'], 2 => ['file', $nulo, 'w']];
        $proceso = proc_open($comando, $salidas, $tuberias, null, $variables + getenv());
        register_shutdown_function(fn () => proc_terminate($proceso));
        for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $puerto) === false; $i++) {
            usleep(100_000);
        }
        return 'http://127.0.0.1:' . $puerto;
    }

    /** Dirección base del servidor de prueba, por ejemplo http://127.0.0.1:54321 */
    public static function base(): string
    {
        self::iniciar();
        return 'http://127.0.0.1:' . self::$puerto;
    }

    /**
     * @param array<mixed>|null $cuerpo
     * @return array{0: int, 1: array<mixed>} [código HTTP, JSON de la respuesta]
     */
    public function pedir(string $metodo, string $ruta, ?array $cuerpo = null): array
    {
        $encabezados = ['Accept: application/json', 'Content-Type: application/json'];
        if ($this->cookie !== '') {
            $encabezados[] = 'Cookie: ' . $this->cookie;
        }
        $contexto = stream_context_create(['http' => [
            'method' => $metodo,
            'header' => implode("\r\n", $encabezados),
            'content' => $cuerpo === null ? '' : (string) json_encode($cuerpo),
            'ignore_errors' => true,
        ]]);
        $url = 'http://127.0.0.1:' . self::$puerto . '/api' . $ruta;
        $respuesta = (string) file_get_contents($url, false, $contexto);
        $lineas = $http_response_header ?? [];
        preg_match('#HTTP/\S+ (\d{3})#', $lineas[0] ?? '', $codigo);
        foreach ($lineas as $linea) {
            if (preg_match('/^Set-Cookie:\s*([^;]+)/i', $linea, $c)) {
                $this->cookie = $c[1];
            }
        }
        return [(int) ($codigo[1] ?? 0), json_decode($respuesta, true) ?? []];
    }
}
