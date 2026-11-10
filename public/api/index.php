<?php

/**
 * Punto de entrada único de la API: todas las peticiones a /api/... llegan acá
 * (ver public/.htaccess). Acá solo se registran las rutas; la lógica va en src/.
 */

declare(strict_types=1);

use Cooki\Config;
use Cooki\Controladores\IngredientesControlador;
use Cooki\Controladores\ListaControlador;
use Cooki\Controladores\RecetasControlador;
use Cooki\Controladores\SaludControlador;
use Cooki\Controladores\SesionControlador;
use Cooki\Http\ErrorHttp;
use Cooki\Http\Respuesta;
use Cooki\Http\Router;

require dirname(__DIR__, 2) . '/src/bootstrap.php';

$router = new Router();

$router->get('/salud', fn () => SaludControlador::ver());
$router->get('/ingredientes', fn () => IngredientesControlador::buscar());
// Incremento 2 (ver docs/api.md)
$router->post('/usuarios', fn () => SesionControlador::registrar());
$router->post('/sesion', fn () => SesionControlador::ingresar());
$router->delete('/sesion', fn () => SesionControlador::salir());
$router->get('/yo', fn () => SesionControlador::yo());
$router->get('/recetas', fn () => RecetasControlador::listar());
$router->get('/recetas/{id}', fn (array $p) => RecetasControlador::ver($p));
$router->get('/recomendaciones', fn () => RecetasControlador::recomendaciones());
$router->get('/despensa', fn () => RecetasControlador::despensa());
$router->post('/favoritos/{id}', fn (array $p) => RecetasControlador::marcarFavorita($p));
$router->delete('/favoritos/{id}', fn (array $p) => RecetasControlador::desmarcarFavorita($p));
// Incremento 3: lista de compras
$router->get('/lista', fn () => ListaControlador::ver());
$router->post('/lista', fn () => ListaControlador::agregar());
$router->post('/lista/{id}/comprado', fn (array $p) => ListaControlador::comprar($p));
$router->delete('/lista/{id}/comprado', fn (array $p) => ListaControlador::deshacer($p));
$router->delete('/lista/{id}', fn (array $p) => ListaControlador::quitar($p));

$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    // Las operaciones que modifican datos solo aceptan JSON: un formulario de otro sitio
    // no puede enviar JSON sin permiso, así que esto también protege contra CSRF.
    $modifica = in_array($metodo, ['POST', 'PATCH', 'PUT', 'DELETE'], true);
    if ($modifica && !str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        throw new ErrorHttp('Las peticiones deben enviarse como JSON', 415);
    }
    $router->despachar($metodo, Router::rutaDesdeUri($_SERVER['REQUEST_URI'] ?? '/'));
} catch (ErrorHttp $e) {
    Respuesta::error($e->getMessage(), $e->getCode());
} catch (Throwable $e) {
    error_log((string) $e);
    $detalle = Config::esProduccion() ? 'Error interno' : $e->getMessage();
    Respuesta::error($detalle, 500);
}
