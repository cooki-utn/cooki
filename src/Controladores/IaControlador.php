<?php

declare(strict_types=1);

namespace Cooki\Controladores;

use Cooki\Db;
use Cooki\Http\ErrorHttp;
use Cooki\Http\Respuesta;
use Cooki\Ia\ErrorIa;
use Cooki\Ia\Ia;
use Cooki\Repositorios\IaRepositorio;

/**
 * La IA por la API (incremento 3 · HU-30 a HU-33). Todas piden sesión; las que piden algo a la IA, además,
 * solo aceptan JSON (como toda la API: eso protege contra CSRF).
 *   GET  /api/ia                    {disponible, restantes, limite}: si hay IA y cuántos pedidos le quedan hoy
 *   POST /api/ia/receta             receta nueva con lo que tiene: {porciones, minutos, tipo}
 *                                   o "Pedir otra" de una que ya vio: {otra_de: id}
 *                                   → 201 {id, titulo, url, restantes}
 * Si la IA no puede, responde el error con un mensaje para mostrar tal cual:
 *   503 sin IA configurada · 429 límite del día · 422 faltan datos (despensa vacía) · 504 tardó demasiado ·
 *   502 la IA falló o respondió algo que no sirve.
 */
final class IaControlador
{
    public static function estado(): void
    {
        $usuario = SesionControlador::requiereSesion();
        $disponible = Ia::disponible();
        Respuesta::json([
            'disponible' => $disponible,
            'restantes' => $disponible ? (new Ia(Db::conexion()))->restantesHoy($usuario) : 0,
            'limite' => Ia::limiteUsuario(),
        ]);
    }

    public static function receta(): void
    {
        $usuario = SesionControlador::requiereSesion();
        $datos = Respuesta::cuerpo();
        $db = Db::conexion();
        $evitar = [];
        if (isset($datos['otra_de'])) {
            $anterior = (new IaRepositorio($db))->receta($usuario, (int) $datos['otra_de']);
            if ($anterior === null) {
                throw new ErrorHttp('No encontramos esa receta', 404);
            }
            $datos = $anterior['contenido']['pedido']['opciones'] ?? [];
            $evitar = [...($anterior['contenido']['pedido']['evitar'] ?? []), $anterior['titulo']];
        }
        $ia = new Ia($db);
        try {
            $id = $ia->recetaNueva($usuario, $datos, $evitar);
        } catch (ErrorIa $e) {
            throw self::errorHttp($e);
        }
        $receta = (new IaRepositorio($db))->receta($usuario, $id);
        Respuesta::json([
            'id' => $id,
            'titulo' => $receta['titulo'] ?? '',
            'url' => url('app/receta-ia.php?id=' . $id),
            'restantes' => $ia->restantesHoy($usuario),
        ], 201);
    }

    private static function errorHttp(ErrorIa $e): ErrorHttp
    {
        $estado = match ($e->motivo) {
            ErrorIa::APAGADA => 503,
            ErrorIa::LIMITE => 429,
            ErrorIa::DATOS => 422,
            ErrorIa::TIEMPO => 504,
            default => 502,
        };
        return new ErrorHttp($e->paraUsuario(), $estado);
    }
}
