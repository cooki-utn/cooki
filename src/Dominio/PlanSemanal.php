<?php

declare(strict_types=1);

namespace Cooki\Dominio;

use DateTimeImmutable;

/**
 * Plan semanal (incremento 3 · HU-35 a HU-37): lógica pura, sin base de datos (se prueba en PlanSemanalTest).
 *   - La semana son los próximos 7 días a partir de hoy, con almuerzo y cena.
 *   - En cada lugar va una receta del recetario ("r:12") o una de "Mis recetas de IA" ("ia:5").
 *   - simular(): qué tiene y qué le falta a cada comida, gastando la despensa en orden (HU-37).
 *   - armar(): "Armá mi semana", completa los lugares vacíos con el puntaje de Mi cocina (HU-36).
 */
final class PlanSemanal
{
    public const DIAS = 7;
    public const MOMENTOS = ['almuerzo' => 'Almuerzo', 'cena' => 'Cena'];
    private const NOMBRES_DIA = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

    /**
     * Las fechas de la semana (Y-m-d), de hoy a 6 días más.
     * @return list<string>
     */
    public static function dias(DateTimeImmutable $hoy): array
    {
        $dias = [];
        for ($i = 0; $i < self::DIAS; $i++) {
            $dias[] = $hoy->modify("+{$i} day")->format('Y-m-d');
        }
        return $dias;
    }

    /** "Hoy", "Mañana" o "Jueves 12". */
    public static function nombreDia(string $fecha, DateTimeImmutable $hoy): string
    {
        $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($dia === false) {
            return $fecha;
        }
        $diferencia = (int) $hoy->setTime(0, 0)->diff($dia)->format('%r%a');
        return match ($diferencia) {
            0 => 'Hoy',
            1 => 'Mañana',
            default => self::diaConNumero($fecha),
        };
    }

    /** "Martes 10": el día de la semana con el número. */
    public static function diaConNumero(string $fecha): string
    {
        $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        return $dia === false ? $fecha : self::NOMBRES_DIA[(int) $dia->format('w')] . ' ' . $dia->format('j');
    }

    /** "el almuerzo de hoy", "la cena de mañana", "la cena del miércoles 11" (para los avisos y los lectores). */
    public static function lugar(string $fecha, string $momento, DateTimeImmutable $hoy): string
    {
        $dia = self::nombreDia($fecha, $hoy);
        $comida = $momento === 'cena' ? 'la cena' : 'el almuerzo';
        return $comida . (in_array($dia, ['Hoy', 'Mañana'], true) ? ' de ' : ' del ') . mb_strtolower($dia);
    }

    /** Si la fecha y el momento son un lugar de la semana que empieza hoy. */
    public static function lugarValido(string $fecha, string $momento, DateTimeImmutable $hoy): bool
    {
        return isset(self::MOMENTOS[$momento]) && in_array($fecha, self::dias($hoy), true);
    }

    /**
     * Lo que se eligió en el desplegable: "r:12" → receta 12, "ia:5" → receta de IA 5. Cualquier otra cosa, null.
     * @return array{receta_id: ?int, receta_ia_id: ?int}|null
     */
    public static function opcion(string $valor): ?array
    {
        if (preg_match('/^(r|ia):([1-9]\d{0,9})$/', trim($valor), $partes) !== 1) {
            return null;
        }
        $id = (int) $partes[2];
        return $partes[1] === 'r'
            ? ['receta_id' => $id, 'receta_ia_id' => null]
            : ['receta_id' => null, 'receta_ia_id' => $id];
    }

    /** El valor del desplegable para una receta: el contrario de opcion(). */
    public static function valorOpcion(?int $recetaId, ?int $recetaIaId): string
    {
        return $recetaId !== null ? 'r:' . $recetaId : ($recetaIaId !== null ? 'ia:' . $recetaIaId : '');
    }

    /** Porciones elegidas; vacío o fuera de rango → las de la receta. */
    public static function porciones(string|int|null $pedidas, int $deLaReceta): int
    {
        $texto = trim((string) $pedidas);
        $numero = is_int($pedidas) ? $pedidas : (ctype_digit($texto) ? (int) $texto : 0);
        if ($numero < Porciones::MINIMO || $numero > Porciones::MAXIMO) {
            return max(Porciones::MINIMO, min(Porciones::MAXIMO, $deLaReceta));
        }
        return $numero;
    }

    // ───────────── Lo que falta (HU-37) y "Armá mi semana" (HU-36) ─────────────

    /**
     * Recorre las comidas en orden (de hoy en adelante) y en cada una se fija qué de la despensa se puede usar ese
     * día. Lo que usa lo "gasta" con Descuento (resta la cantidad, o baja un nivel si no se puede comparar), así la
     * comida siguiente ve lo que queda. Estado de cada ingrediente:
     *   tengo  → está (o es básico / "siempre tengo")
     *   usado  → había, pero lo gastó una comida anterior ("lo_usa" dice cuál)
     *   vence  → lo tenés, pero vence antes de ese día
     *   falta  → no lo tenés (o no está en el catálogo, en las recetas de IA)
     * Los opcionales y lo que la persona no come no cuentan, como en la lista de compras.
     *
     * @param list<array{clave: string, fecha: string, titulo: string, porciones: int, porciones_base: int,
     *                   ingredientes: list<array<string, mixed>>}> $comidas en orden; cada ingrediente:
     *        ingrediente_id (?int), nombre, cantidad (?float, de la receta sin escalar), unidad, opcional, basico,
     *        restringido, gramos_por_unidad (?float) y peso (1 a 3)
     * @param array<int, list<array{nivel: int, cantidad: ?float, unidad: ?string, vence: ?string}>> $despensa
     *        por ingrediente, lo que vence antes primero (solo lo que tiene nivel > 0)
     * @return array<string, array{ingredientes: list<array{ingrediente_id: ?int, nombre: string, estado: string,
     *         lo_usa: ?string}>, faltantes: list<array{ingrediente_id: ?int, texto: ?string, nombre: string,
     *         cantidad: ?float, unidad: ?string}>, tiene_todo: bool}> por clave de la comida
     */
    public static function simular(array $comidas, array $despensa): array
    {
        $estado = ['items' => $despensa, 'quien' => []];
        $resultado = [];
        foreach ($comidas as $comida) {
            $resultado[$comida['clave']] = self::gastar($estado, $comida);
        }
        return $resultado;
    }

    /**
     * "Armá mi semana": completa los lugares vacíos, en orden. En cada uno elige la receta con mejor puntaje
     * (Puntaje: lo que tenés ese día, lo que vence en 3 días o menos, si la cocinaste hace poco) mirando la despensa
     * que dejaron las comidas anteriores, y la "gasta" antes de pasar al siguiente. Primero las que entran en un
     * grupo de Mi cocina (ahora, falta 1, casi); si no alcanzan, la de más puntaje aunque falten cosas. No repite
     * recetas en la semana (tampoco las que ya estaban). Si no quedan recetas, el lugar queda vacío.
     *
     * @param list<array{clave: string, fecha: string, comida: ?array<string, mixed>}> $lugares los 14, en orden;
     *        comida = la que ya está (como en simular(), con "id") o null si está vacío
     * @param list<array{id: string, titulo: string, minutos: int, dificultad: int, porciones_base: int,
     *                   reciente: bool, ingredientes: list<array<string, mixed>>}> $candidatas id = "r:12" o "ia:5"
     * @param array<int, list<array{nivel: int, cantidad: ?float, unidad: ?string, vence: ?string}>> $despensa
     * @return array<string, string> clave del lugar vacío → id de la receta elegida
     */
    public static function armar(array $lugares, array $candidatas, array $despensa): array
    {
        $estado = ['items' => $despensa, 'quien' => []];
        $usadas = [];
        foreach ($lugares as $lugar) {
            if ($lugar['comida'] !== null) {
                $usadas[(string) $lugar['comida']['id']] = true;
            }
        }
        $elegidas = [];
        foreach ($lugares as $lugar) {
            $comida = $lugar['comida'];
            if ($comida === null) {
                $mejor = null;
                foreach ($candidatas as $candidata) {
                    if (isset($usadas[$candidata['id']])) {
                        continue;
                    }
                    $calculo = Puntaje::calcular(
                        self::disponibilidad($estado, $candidata['ingredientes'], $lugar['fecha']),
                        $candidata['reciente']
                    );
                    $orden = [
                        'grupo' => $calculo['grupo'] !== null, 'puntaje' => $calculo['puntaje'],
                        'minutos' => $candidata['minutos'], 'dificultad' => $candidata['dificultad'],
                        'titulo' => $candidata['titulo'],
                    ];
                    if ($mejor === null || self::antes($orden, $mejor['orden'])) {
                        $mejor = ['candidata' => $candidata, 'orden' => $orden];
                    }
                }
                if ($mejor === null) {
                    continue;
                }
                $elegida = $mejor['candidata'];
                $usadas[$elegida['id']] = true;
                $elegidas[$lugar['clave']] = $elegida['id'];
                $comida = [
                    'titulo' => $elegida['titulo'], 'porciones' => $elegida['porciones_base'],
                    'porciones_base' => $elegida['porciones_base'], 'ingredientes' => $elegida['ingredientes'],
                ];
            }
            self::gastar($estado, ['clave' => $lugar['clave'], 'fecha' => $lugar['fecha']] + $comida);
        }
        return $elegidas;
    }

    /**
     * Si una receta va antes que otra al armar la semana: primero las que entran en un grupo, después el orden de
     * siempre (Puntaje::comparar: más puntaje, más rápida, más fácil).
     * @param array{grupo: bool, puntaje: int, minutos: int, dificultad: int, titulo: string} $a
     * @param array{grupo: bool, puntaje: int, minutos: int, dificultad: int, titulo: string} $b
     */
    private static function antes(array $a, array $b): bool
    {
        if ($a['grupo'] !== $b['grupo']) {
            return $a['grupo'];
        }
        return Puntaje::comparar($a, $b) < 0;
    }

    /**
     * Los ingredientes de una receta como los quiere Puntaje::calcular(), con la despensa simulada de ese día.
     * @param array{items: array<int, list<array<string, mixed>>>, quien: array<int, string>} $estado
     * @param list<array<string, mixed>> $ingredientes
     * @return list<array{nombre: string, peso: int, opcional: bool, disponible: float, vence_pronto: bool}>
     */
    private static function disponibilidad(array $estado, array $ingredientes, string $fecha): array
    {
        $limite = (new DateTimeImmutable($fecha))->modify('+3 day')->format('Y-m-d');
        $lista = [];
        foreach ($ingredientes as $ing) {
            $id = $ing['ingrediente_id'] ?? null;
            $item = $id === null ? null : self::usable($estado['items'][(int) $id] ?? [], $fecha);
            $lista[] = [
                'nombre' => (string) $ing['nombre'],
                'peso' => (int) ($ing['peso'] ?? 2),
                'opcional' => !empty($ing['opcional']),
                'disponible' => !empty($ing['basico']) || $item !== null ? 1.0 : 0.0,
                'vence_pronto' => $item !== null && $item['vence'] !== null && $item['vence'] <= $limite,
            ];
        }
        return $lista;
    }

    /**
     * El primer lote de la despensa que se puede usar ese día (con algo y sin vencer), o null.
     * @param list<array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    private static function usable(array $items, string $fecha): ?array
    {
        foreach ($items as $item) {
            if ((int) $item['nivel'] > 0 && ($item['vence'] === null || $item['vence'] >= $fecha)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Una comida: qué tiene, qué le falta, y lo que usa se descuenta del estado.
     * @param array{items: array<int, list<array<string, mixed>>>, quien: array<int, string>} $estado
     * @param array<string, mixed> $comida
     * @return array{ingredientes: list<array<string, mixed>>, faltantes: list<array<string, mixed>>, tiene_todo: bool}
     */
    private static function gastar(array &$estado, array $comida): array
    {
        $factor = Porciones::factor(max(1, (int) $comida['porciones_base']), (int) $comida['porciones']);
        $ingredientes = [];
        $faltantes = [];
        foreach ($comida['ingredientes'] as $ing) {
            if (!empty($ing['opcional']) || !empty($ing['restringido'])) {
                continue;
            }
            $id = $ing['ingrediente_id'] === null ? null : (int) $ing['ingrediente_id'];
            $unidad = ($ing['unidad'] ?? '') === '' ? null : (string) $ing['unidad'];
            $cantidad = $ing['cantidad'] === null ? null : (float) $ing['cantidad'] * $factor;
            [$como, $loUsa] = match (true) {
                !empty($ing['basico']) => ['tengo', null],
                $id === null => ['falta', null],
                default => self::usar($estado, $id, $cantidad, $unidad, $ing['gramos_por_unidad'] ?? null, $comida),
            };
            $ingredientes[] = ['ingrediente_id' => $id, 'nombre' => (string) $ing['nombre'], 'estado' => $como,
                'lo_usa' => $loUsa];
            if ($como !== 'tengo') {
                $faltantes[] = [
                    'ingrediente_id' => $id,
                    'texto' => $id === null ? (string) $ing['nombre'] : null,
                    'nombre' => (string) $ing['nombre'],
                ] + ListaCompras::escalar($ing['cantidad'], $unidad, $factor);
            }
        }
        return ['ingredientes' => $ingredientes, 'faltantes' => $faltantes, 'tiene_todo' => $faltantes === []];
    }

    /**
     * Usa un ingrediente de la despensa simulada (el lote que vence antes y todavía sirve ese día).
     * @param array{items: array<int, list<array<string, mixed>>>, quien: array<int, string>} $estado
     * @param array<string, mixed> $comida
     * @return array{0: string, 1: ?string} [estado, qué comida lo gastó antes]
     */
    private static function usar(
        array &$estado,
        int $id,
        ?float $cantidad,
        ?string $unidad,
        float|string|null $gramosPorUnidad,
        array $comida
    ): array {
        $habia = false;
        foreach ($estado['items'][$id] ?? [] as $n => $item) {
            if ($item['vence'] !== null && $item['vence'] < $comida['fecha']) {
                continue;   // vence antes de ese día
            }
            $habia = true;
            if ((int) $item['nivel'] <= 0) {
                continue;
            }
            $despues = Descuento::aplicar(
                $item,
                $cantidad,
                $unidad,
                $gramosPorUnidad === null ? null : (float) $gramosPorUnidad
            );
            $estado['items'][$id][$n] = ['nivel' => $despues['nivel'], 'cantidad' => $despues['cantidad'],
                'unidad' => $despues['unidad'], 'vence' => $item['vence']];
            if ($despues['nivel'] <= 0) {
                $estado['quien'][$id] = (string) $comida['titulo'];
            }
            return ['tengo', null];
        }
        if ($habia) {
            return ['usado', $estado['quien'][$id] ?? null];
        }
        return [($estado['items'][$id] ?? []) === [] ? 'falta' : 'vence', null];
    }
}
