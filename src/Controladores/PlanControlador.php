<?php

declare(strict_types=1);

namespace Cooki\Controladores;

use Cooki\Db;
use Cooki\Dominio\PlanSemanal;
use Cooki\Dominio\Porciones;
use Cooki\Http\ErrorHttp;
use Cooki\Http\Respuesta;
use Cooki\Repositorios\PlanRepositorio;
use DateTimeImmutable;

/**
 * Plan semanal por la API (incremento 3 · HU-35 a HU-37). Todas piden sesión.
 *   GET    /api/plan                    los 7 días a partir de hoy, con almuerzo y cena; en cada comida, si tenés todo
 *                                       y qué falta (gastando la despensa en orden, como la página)
 *   PUT    /api/plan/{fecha}/{momento}  pone (o cambia) la receta: {receta_id} o {receta_ia_id}, y {porciones}
 *                                       opcional (si no viene, las de la receta)
 *   DELETE /api/plan/{fecha}/{momento}  deja el lugar vacío
 * {fecha} es AAAA-MM-DD (de hoy a 6 días más) y {momento}, "almuerzo" o "cena".
 */
final class PlanControlador
{
    public static function ver(): void
    {
        $usuario = SesionControlador::requiereSesion();
        $repo = new PlanRepositorio(Db::conexion());
        $hoy = new DateTimeImmutable('today');
        $dias = PlanSemanal::dias($hoy);
        $plan = $repo->semana($usuario, $dias[0], $dias[PlanSemanal::DIAS - 1]);
        $simulacion = PlanSemanal::simular($repo->comidas($usuario, $plan), $repo->despensa($usuario));
        $semana = [];
        foreach ($dias as $fecha) {
            $comidas = [];
            foreach (array_keys(PlanSemanal::MOMENTOS) as $momento) {
                $clave = $fecha . '|' . $momento;
                $comidas[$momento] = isset($plan[$clave])
                    ? self::comida($plan[$clave], $simulacion[$clave] ?? null)
                    : null;
            }
            $semana[] = ['fecha' => $fecha, 'dia' => PlanSemanal::nombreDia($fecha, $hoy)] + $comidas;
        }
        Respuesta::json(['dias' => $semana]);
    }

    /** @param array{fecha: string, momento: string} $parametros */
    public static function guardar(array $parametros): void
    {
        $usuario = SesionControlador::requiereSesion();
        [$fecha, $momento, $hoy] = self::lugar($parametros);
        $datos = Respuesta::cuerpo();
        $recetaId = isset($datos['receta_id']) ? (int) $datos['receta_id'] : null;
        $recetaIaId = isset($datos['receta_ia_id']) ? (int) $datos['receta_ia_id'] : null;
        if (($recetaId === null) === ($recetaIaId === null)) {
            throw new ErrorHttp('Mandá "receta_id" o "receta_ia_id" (uno de los dos)', 400);
        }
        $porcionesPedidas = $datos['porciones'] ?? null;
        $minimo = Porciones::MINIMO;
        $maximo = Porciones::MAXIMO;
        if (
            $porcionesPedidas !== null
            && (!is_int($porcionesPedidas) || $porcionesPedidas < $minimo || $porcionesPedidas > $maximo)
        ) {
            throw new ErrorHttp("\"porciones\" tiene que ser un número entero de {$minimo} a {$maximo}", 400);
        }
        $repo = new PlanRepositorio(Db::conexion());
        $base = $repo->porcionesBase($usuario, $recetaId, $recetaIaId);
        if ($base === null) {
            throw new ErrorHttp(
                $recetaId !== null ? 'No existe esa receta' : 'No tenés guardada esa receta de IA',
                404
            );
        }
        $porciones = PlanSemanal::porciones($porcionesPedidas, $base);
        $repo->limpiar($usuario, $hoy->format('Y-m-d'));
        $repo->guardar($usuario, $fecha, $momento, $recetaId, $recetaIaId, $porciones);
        $guardada = $repo->semana($usuario, $fecha, $fecha)[$fecha . '|' . $momento];
        Respuesta::json(['fecha' => $fecha, 'momento' => $momento] + self::comida($guardada, null));
    }

    /** @param array{fecha: string, momento: string} $parametros */
    public static function quitar(array $parametros): void
    {
        $usuario = SesionControlador::requiereSesion();
        [$fecha, $momento] = self::lugar($parametros);
        if (!(new PlanRepositorio(Db::conexion()))->quitar($usuario, $fecha, $momento)) {
            throw new ErrorHttp('En ese lugar no hay ninguna receta', 404);
        }
        Respuesta::json(['fecha' => $fecha, 'momento' => $momento, 'quitado' => true]);
    }

    /**
     * Valida {fecha} y {momento} de la ruta: tienen que ser un lugar de esta semana.
     * @param array{fecha: string, momento: string} $parametros
     * @return array{string, string, DateTimeImmutable}
     */
    private static function lugar(array $parametros): array
    {
        $hoy = new DateTimeImmutable('today');
        if (!PlanSemanal::lugarValido($parametros['fecha'], $parametros['momento'], $hoy)) {
            throw new ErrorHttp(
                'El lugar tiene que ser una fecha de hoy a 6 días más (AAAA-MM-DD) y "almuerzo" o "cena"',
                422
            );
        }
        return [$parametros['fecha'], $parametros['momento'], $hoy];
    }

    /**
     * @param array<string, mixed> $comida fila de PlanRepositorio::semana()
     * @param ?array<string, mixed> $estado lo que dijo PlanSemanal::simular() de esa comida (null: no se calcula)
     * @return array<string, mixed>
     */
    private static function comida(array $comida, ?array $estado): array
    {
        $datos = [
            'receta_id' => $comida['receta_id'],
            'receta_ia_id' => $comida['receta_ia_id'],
            'titulo' => $comida['titulo'],
            'porciones' => $comida['porciones'],
            'minutos' => $comida['minutos'],
        ];
        if ($estado !== null) {
            $datos['tiene_todo'] = $estado['tiene_todo'];
            $datos['faltantes'] = array_map(fn (array $f) => [
                'ingrediente_id' => $f['ingrediente_id'],
                'nombre' => $f['nombre'],
                'cantidad' => $f['cantidad'],
                'unidad' => $f['cantidad'] === null ? null : $f['unidad'],
            ], $estado['faltantes']);
        }
        return $datos;
    }
}
