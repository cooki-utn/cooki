<?php

declare(strict_types=1);

namespace Cooki\Controladores;

use Cooki\Db;
use Cooki\Dominio\ListaCompras;
use Cooki\Dominio\Porciones;
use Cooki\Http\ErrorHttp;
use Cooki\Http\Respuesta;
use Cooki\Repositorios\ListaRepositorio;

/**
 * Lista de compras por la API (incremento 3 · HU-26 a HU-29). Todas piden sesión.
 *   GET    /api/lista                  lo que falta comprar (por góndola), lo comprado hoy y el texto para compartir
 *   POST   /api/lista                  agrega {ingrediente_id} o {texto}, con {cantidad, unidad} opcionales
 *   POST   /api/lista/{id}/comprado    lo marca como comprado: el ingrediente pasa a la despensa
 *   DELETE /api/lista/{id}/comprado    deshace la compra (solo la de hoy)
 *   DELETE /api/lista/{id}             lo quita de la lista
 */
final class ListaControlador
{
    /** Unidades que se aceptan al agregar (las mismas que en la página). */
    private const UNIDADES = ['u', 'g', 'kg', 'ml', 'l'];

    public static function ver(): void
    {
        $usuario = SesionControlador::requiereSesion();
        $items = (new ListaRepositorio(Db::conexion()))->listar($usuario);
        $pendientes = array_values(array_filter($items, fn (array $i) => $i['comprado_en'] === null));
        $comprados = array_values(array_filter($items, fn (array $i) => $i['comprado_en'] !== null));
        $grupos = ListaCompras::agrupar($pendientes);
        Respuesta::json([
            'pendientes' => array_map(fn (array $g) => [
                'categoria' => $g['categoria'],
                'items' => array_map(self::item(...), $g['items']),
            ], $grupos),
            'comprado_hoy' => array_map(self::item(...), $comprados),
            'texto' => ListaCompras::texto($grupos),
        ]);
    }

    public static function agregar(): void
    {
        $usuario = SesionControlador::requiereSesion();
        $datos = Respuesta::cuerpo();
        $ingrediente = isset($datos['ingrediente_id']) ? (int) $datos['ingrediente_id'] : null;
        $texto = isset($datos['texto']) ? trim((string) $datos['texto']) : null;
        if (($ingrediente === null) === ($texto === null || $texto === '')) {
            throw new ErrorHttp('Mandá "ingrediente_id" o "texto" (uno de los dos)', 400);
        }
        $cantidad = null;
        $unidad = null;
        if (isset($datos['cantidad'])) {
            $numero = is_numeric($datos['cantidad']) ? (float) $datos['cantidad'] : 0.0;
            if ($numero <= 0 || $numero > 99999) {
                throw new ErrorHttp('"cantidad" tiene que ser un número mayor que 0', 400);
            }
            $cantidad = (float) $datos['cantidad'];
            $unidad = (string) ($datos['unidad'] ?? 'u');
            if (!in_array($unidad, self::UNIDADES, true)) {
                throw new ErrorHttp('"unidad" tiene que ser una de estas: ' . implode(', ', self::UNIDADES), 400);
            }
        }
        $hecho = (new ListaRepositorio(Db::conexion()))->agregar($usuario, $ingrediente, $texto, $cantidad, $unidad);
        if ($hecho === null) {
            throw new ErrorHttp($ingrediente !== null
                ? 'No existe ese ingrediente'
                : 'El texto tiene que tener entre 2 y ' . ListaRepositorio::LARGO_TEXTO . ' letras', 422);
        }
        Respuesta::json($hecho, $hecho['sumado'] ? 200 : 201);
    }

    /** @param array{id: string} $parametros */
    public static function comprar(array $parametros): void
    {
        $usuario = SesionControlador::requiereSesion();
        if ((new ListaRepositorio(Db::conexion()))->comprar($usuario, [(int) $parametros['id']]) === 0) {
            throw new ErrorHttp('No está en tu lista, o ya lo compraste', 404);
        }
        Respuesta::json(['id' => (int) $parametros['id'], 'comprado' => true]);
    }

    /** @param array{id: string} $parametros */
    public static function deshacer(array $parametros): void
    {
        $usuario = SesionControlador::requiereSesion();
        if (!(new ListaRepositorio(Db::conexion()))->deshacer($usuario, (int) $parametros['id'])) {
            throw new ErrorHttp('No hay una compra de hoy para deshacer', 404);
        }
        Respuesta::json(['id' => (int) $parametros['id'], 'comprado' => false]);
    }

    /** @param array{id: string} $parametros */
    public static function quitar(array $parametros): void
    {
        $usuario = SesionControlador::requiereSesion();
        if (!(new ListaRepositorio(Db::conexion()))->borrar($usuario, (int) $parametros['id'])) {
            throw new ErrorHttp('No está en tu lista', 404);
        }
        Respuesta::json(['id' => (int) $parametros['id'], 'quitado' => true]);
    }

    /**
     * @param array<string, mixed> $i fila de ListaRepositorio::listar()
     * @return array<string, mixed>
     */
    private static function item(array $i): array
    {
        $cantidad = $i['cantidad'] === null ? null : (float) $i['cantidad'];
        return [
            'id' => (int) $i['id'],
            'ingrediente_id' => $i['ingrediente_id'] === null ? null : (int) $i['ingrediente_id'],
            'nombre' => $i['nombre'],
            'cantidad' => $cantidad,
            'unidad' => $cantidad === null ? null : $i['unidad'],
            'texto_cantidad' => $cantidad === null ? null : Porciones::texto($cantidad, $i['unidad']),
            'categoria' => $i['categoria'] ?? ListaCompras::OTROS,
            'receta' => $i['receta'],
            'comprado_en' => $i['comprado_en'],
        ];
    }
}
