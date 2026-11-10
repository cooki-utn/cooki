<?php

declare(strict_types=1);

namespace Cooki\Http;

/**
 * Enrutador mínimo: asocia método + ruta (con parámetros {id}) a una función.
 * Ejemplo: $router->get('/recetas/{id}', fn (array $p) => ...);
 */
final class Router
{
    /** @var array<int, array{string, string, callable}> */
    private array $rutas = [];

    public function get(string $ruta, callable $accion): void
    {
        $this->rutas[] = ['GET', $ruta, $accion];
    }

    public function post(string $ruta, callable $accion): void
    {
        $this->rutas[] = ['POST', $ruta, $accion];
    }

    public function put(string $ruta, callable $accion): void
    {
        $this->rutas[] = ['PUT', $ruta, $accion];
    }

    public function patch(string $ruta, callable $accion): void
    {
        $this->rutas[] = ['PATCH', $ruta, $accion];
    }

    public function delete(string $ruta, callable $accion): void
    {
        $this->rutas[] = ['DELETE', $ruta, $accion];
    }

    /**
     * Obtiene la ruta de la API a partir de la URL, sin importar en qué carpeta esté instalado
     * el proyecto: "/cooki/public/api/ingredientes?q=x" → "/ingredientes".
     */
    public static function rutaDesdeUri(string $uri): string
    {
        $camino = (string) parse_url($uri, PHP_URL_PATH);
        $posicion = strpos($camino, '/api/');
        if ($posicion === false) {
            return '/';
        }
        $ruta = substr($camino, $posicion + 4);
        return '/' . trim($ruta, '/');
    }

    public function despachar(string $metodo, string $ruta): void
    {
        $metodoValido = false;
        foreach ($this->rutas as [$m, $patron, $accion]) {
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $patron) . '$#';
            if (!preg_match($regex, $ruta, $coincidencias)) {
                continue;
            }
            if ($m !== $metodo) {
                $metodoValido = true;
                continue;
            }
            $parametros = array_filter($coincidencias, 'is_string', ARRAY_FILTER_USE_KEY);
            $accion($parametros);
            return;
        }
        if ($metodoValido) {
            Respuesta::error('Método no permitido', 405);
            return;
        }
        Respuesta::error('Ruta no encontrada', 404);
    }
}
