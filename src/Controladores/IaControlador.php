<?php

declare(strict_types=1);

namespace Cooki\Controladores;

use Cooki\Db;
use Cooki\Http\ErrorHttp;
use Cooki\Http\Respuesta;
use Cooki\Ia\ErrorIa;
use Cooki\Ia\Ia;
use Cooki\Repositorios\IaRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use PDO;

/**
 * La IA por la API (incremento 3 · HU-30 a HU-33). Todas piden sesión; las que piden algo a la IA, además,
 * solo aceptan JSON (como toda la API: eso protege contra CSRF).
 *   GET  /api/ia                    {disponible, restantes, limite}: si hay IA y cuántos pedidos le quedan hoy
 *   POST /api/ia/receta             receta nueva con lo que tiene: {porciones, minutos, tipo}
 *                                   o "Pedir otra" de una que ya vio: {otra_de: id}
 *                                   → 201 {id, titulo, url, restantes}
 *   POST /api/ia/adaptar/{id}       adapta una receta del recetario: {adaptacion, porciones}
 *                                   (adaptacion: sin_huevo, sin_tacc, sin_lactosa, vegetariana o con_lo_que_tengo)
 *                                   → 201 {id, titulo, url, restantes}
 *   POST /api/ia/reemplazo          con qué reemplazar un ingrediente que falta: {receta, ingrediente, porciones}
 *                                   → 200 {reemplazo, ingrediente_id, ratio, nota, frase, tengo, restantes,
 *                                          sin_gastar, aviso} (los reemplazos gastan 1 pedido cada 5)
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
        self::creada($ia, $db, $usuario, $id);
    }

    /** @param array{id: string} $parametros */
    public static function adaptar(array $parametros): void
    {
        $usuario = SesionControlador::requiereSesion();
        $datos = Respuesta::cuerpo();
        $db = Db::conexion();
        $receta = (new RecetaRepositorio($db))->obtener((int) $parametros['id'], $usuario);
        if ($receta === null) {
            throw new ErrorHttp('No encontramos esa receta', 404);
        }
        $ia = new Ia($db);
        try {
            $id = $ia->adaptar(
                $usuario,
                $receta,
                (string) ($datos['adaptacion'] ?? ''),
                (int) ($datos['porciones'] ?? $receta['porciones_base'])
            );
        } catch (ErrorIa $e) {
            throw self::errorHttp($e);
        }
        self::creada($ia, $db, $usuario, $id);
    }

    public static function reemplazo(): void
    {
        $usuario = SesionControlador::requiereSesion();
        $datos = Respuesta::cuerpo();
        $db = Db::conexion();
        $receta = (new RecetaRepositorio($db))->obtener((int) ($datos['receta'] ?? 0), $usuario);
        if ($receta === null) {
            throw new ErrorHttp('No encontramos esa receta', 404);
        }
        $ia = new Ia($db);
        try {
            $r = $ia->reemplazo(
                $usuario,
                $receta,
                (int) ($datos['ingrediente'] ?? 0),
                (int) ($datos['porciones'] ?? $receta['porciones_base'])
            );
        } catch (ErrorIa $e) {
            throw self::errorHttp($e);
        }
        $restantes = $ia->restantesHoy($usuario);
        $sinGastar = $ia->reemplazosSinGastar($usuario);
        Respuesta::json([
            'reemplazo' => $r['texto'],
            'ingrediente_id' => $r['ingrediente_id'],
            'ratio' => $r['ratio'],
            'nota' => $r['nota'],
            'frase' => $r['frase'],
            'tengo' => $r['tengo'],
            'restantes' => $restantes,
            'sin_gastar' => $sinGastar,
            'aviso' => Ia::textoReemplazos($restantes, $sinGastar),
        ]);
    }

    private static function creada(Ia $ia, PDO $db, int $usuario, int $id): void
    {
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
