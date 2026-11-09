<?php

declare(strict_types=1);

namespace Cooki\Dominio;

use DateTimeImmutable;

/**
 * Las reglas de la lista de compras (lógica pura, con pruebas en tests/Dominio/ListaComprasTest).
 *   - Qué falta de una receta, con las cantidades escaladas a las porciones elegidas.
 *   - Sumar dos cantidades del mismo ingrediente: misma unidad, o g con kg y ml con l. Si no se pueden sumar
 *     (una taza y 200 g), van en renglones separados.
 *   - Al comprar: cómo queda el ingrediente en la despensa (nivel, cantidad, vencimiento estimado y ubicación).
 *   - Agrupar por categoría, como las góndolas del súper, y armar el texto para copiar o mandar por WhatsApp.
 *
 * La usa ListaRepositorio; no toca la base.
 */
final class ListaCompras
{
    /** Categorías que van siempre a la heladera. */
    private const SIEMPRE_HELADERA = ['Lácteos', 'Carnes'];
    /** Categorías que van a la heladera si duran poco (la papa y la cebolla, no; el tomate y la frutilla, sí). */
    private const HELADERA_SI_DURA_POCO = ['Verduras', 'Frutas', 'Huevos'];
    private const DIAS_DURA_POCO = 14;
    /** Grupo de los ítems sin categoría (los de texto libre). Va al final. */
    public const OTROS = 'Otros';

    /** Unidades que se pasan a una unidad base: [base, factor]. */
    private const CONVERSIONES = [
        'g' => ['g', 1.0], 'kg' => ['g', 1000.0],
        'ml' => ['ml', 1.0], 'l' => ['ml', 1000.0],
        'u' => ['u', 1.0],
    ];

    /**
     * Suma dos cantidades del mismo ingrediente. Devuelve null si no se pueden sumar (unidades distintas).
     * Sin cantidad ("a gusto") más una cantidad = esa cantidad. Con g y kg (o ml y l), el resultado va en g (o ml).
     * @return array{cantidad: ?float, unidad: ?string}|null
     */
    public static function sumar(?float $cantidadA, ?string $unidadA, ?float $cantidadB, ?string $unidadB): ?array
    {
        $unidadA = self::unidad($unidadA);
        $unidadB = self::unidad($unidadB);
        if ($cantidadA === null) {
            return ['cantidad' => $cantidadB, 'unidad' => $cantidadB === null ? null : $unidadB];
        }
        if ($cantidadB === null) {
            return ['cantidad' => $cantidadA, 'unidad' => $unidadA];
        }
        if ($unidadA === $unidadB) {
            return ['cantidad' => round($cantidadA + $cantidadB, 2), 'unidad' => $unidadA];
        }
        $a = self::CONVERSIONES[(string) $unidadA] ?? null;
        $b = self::CONVERSIONES[(string) $unidadB] ?? null;
        if ($a === null || $b === null || $a[0] !== $b[0]) {
            return null;
        }
        return ['cantidad' => round($cantidadA * $a[1] + $cantidadB * $b[1], 2), 'unidad' => $a[0]];
    }

    /**
     * Lo que falta de una receta para la lista. No entran: lo que está en la despensa, los básicos y lo de
     * "siempre tengo", lo que la persona no come y los opcionales. La pizca y lo "a gusto" van sin cantidad.
     * @param list<array<string, mixed>> $ingredientes los de RecetaRepositorio::obtener()
     * @return list<array{ingrediente_id: int, nombre: string, cantidad: ?float, unidad: ?string}>
     */
    public static function faltantes(array $ingredientes, float $factor = 1.0): array
    {
        $lista = [];
        foreach ($ingredientes as $ing) {
            if ($ing['en_despensa'] || $ing['es_basico'] || $ing['restringido'] || $ing['es_opcional']) {
                continue;
            }
            $lista[] = ['ingrediente_id' => (int) $ing['ingrediente_id'], 'nombre' => (string) $ing['nombre']]
                + self::escalar($ing['cantidad'], $ing['unidad'], $factor);
        }
        return $lista;
    }

    /**
     * Cantidad de un ingrediente de receta para la lista, escalada a las porciones.
     * @return array{cantidad: ?float, unidad: ?string}
     */
    public static function escalar(string|float|null $cantidad, ?string $unidad, float $factor = 1.0): array
    {
        $unidad = self::unidad($unidad);
        if ($cantidad === null || $cantidad === '' || $unidad === 'pizca') {
            return ['cantidad' => null, 'unidad' => null];
        }
        return ['cantidad' => round((float) $cantidad * $factor, 2), 'unidad' => $unidad ?? 'u'];
    }

    /** Vencimiento estimado de algo que se compra hoy: hoy + vida útil (null si el ingrediente no tiene). */
    public static function vencimiento(?int $vidaUtilDias, DateTimeImmutable $hoy): ?string
    {
        if ($vidaUtilDias === null || $vidaUtilDias <= 0) {
            return null;
        }
        return $hoy->modify("+{$vidaUtilDias} days")->format('Y-m-d');
    }

    /** Dónde se guarda algo recién comprado, según la categoría y cuánto dura. */
    public static function ubicacion(string $categoria, bool $perecedero, ?int $vidaUtilDias): string
    {
        if (!$perecedero) {
            return 'alacena';
        }
        if (in_array($categoria, self::SIEMPRE_HELADERA, true)) {
            return 'heladera';
        }
        $duraPoco = $vidaUtilDias !== null && $vidaUtilDias <= self::DIAS_DURA_POCO;
        return in_array($categoria, self::HELADERA_SI_DURA_POCO, true) && $duraPoco ? 'heladera' : 'alacena';
    }

    /**
     * Cómo queda el ingrediente en la despensa al marcarlo como comprado.
     *   - Nivel: por lo menos "Normal" (si tenía "Mucho", queda "Mucho").
     *   - Cantidad: si se sabe cuánto había y cuánto se compró (en unidades que se pueden pasar a la del
     *     ingrediente), se suman; si no, queda sin cantidad exacta.
     *   - Vencimiento: hoy + vida útil.
     *   - Ubicación: la que ya tenía; si es nuevo, la sugerida.
     * @param array<string, mixed>|null $despensa fila de despensa_item, o null si no estaba
     * @param array{categoria: string, perecedero: bool|int|string, vida_util_dias: int|string|null,
     *              unidad_default: string, gramos_por_unidad: float|string|null} $ingrediente
     * @return array{nivel: int, cantidad: ?float, unidad: ?string, fecha_vencimiento: ?string, ubicacion: string}
     */
    public static function alComprar(
        ?array $despensa,
        array $ingrediente,
        ?float $cantidad,
        ?string $unidad,
        DateTimeImmutable $hoy
    ): array {
        $vidaUtil = $ingrediente['vida_util_dias'] === null ? null : (int) $ingrediente['vida_util_dias'];
        $unidadIngrediente = (string) $ingrediente['unidad_default'];
        $gramos = $ingrediente['gramos_por_unidad'] === null ? null : (float) $ingrediente['gramos_por_unidad'];
        $nivelAntes = $despensa === null ? 0 : (int) $despensa['nivel'];

        $comprado = self::enUnidad($cantidad, $unidad, $unidadIngrediente, $gramos);
        $habia = match (true) {
            $nivelAntes === 0 => 0.0,   // no estaba o estaba agotado
            $despensa['cantidad'] === null => null,   // había, pero no se sabe cuánto
            default => self::enUnidad((float) $despensa['cantidad'], $despensa['unidad'], $unidadIngrediente, $gramos),
        };
        $total = $comprado !== null && $habia !== null ? round($comprado + $habia, 2) : null;

        return [
            'nivel' => max(2, $nivelAntes),
            'cantidad' => $total,
            'unidad' => $total === null ? null : $unidadIngrediente,
            'fecha_vencimiento' => self::vencimiento($vidaUtil, $hoy),
            'ubicacion' => $despensa['ubicacion']
                ?? self::ubicacion((string) $ingrediente['categoria'], (bool) $ingrediente['perecedero'], $vidaUtil),
        ];
    }

    /**
     * Agrupa los ítems por categoría, en el orden de las góndolas (categoria.orden). Los de texto libre
     * van al final, en "Otros". Dentro de cada grupo, por nombre.
     * @param list<array<string, mixed>> $items con 'nombre', 'categoria' (o null) y 'orden' (o null)
     * @return list<array{categoria: string, items: list<array<string, mixed>>}>
     */
    public static function agrupar(array $items): array
    {
        usort($items, fn (array $a, array $b) => [self::orden($a), Texto::sinTildes((string) $a['nombre'])]
            <=> [self::orden($b), Texto::sinTildes((string) $b['nombre'])]);
        $grupos = [];
        foreach ($items as $item) {
            $categoria = $item['categoria'] ?? self::OTROS;
            if ($grupos === [] || $grupos[count($grupos) - 1]['categoria'] !== $categoria) {
                $grupos[] = ['categoria' => $categoria, 'items' => []];
            }
            $grupos[count($grupos) - 1]['items'][] = $item;
        }
        return $grupos;
    }

    /** "Papa · 3 unidades", "Sal" (sin cantidad), "Harina 0000 · 1,5 kg". */
    public static function renglon(string $nombre, string|float|null $cantidad, ?string $unidad): string
    {
        if ($cantidad === null || $cantidad === '') {
            return $nombre;
        }
        return $nombre . ' · ' . Porciones::texto((float) $cantidad, $unidad);
    }

    /**
     * La lista como texto, para copiar o mandar por WhatsApp (los títulos con *asteriscos* salen en negrita).
     * @param list<array{categoria: string, items: list<array<string, mixed>>}> $grupos los de agrupar()
     */
    public static function texto(array $grupos): string
    {
        $renglones = ['Lista de compras'];
        foreach ($grupos as $grupo) {
            $renglones[] = '';
            $renglones[] = '*' . $grupo['categoria'] . '*';
            foreach ($grupo['items'] as $item) {
                $renglones[] = '- ' . self::renglon((string) $item['nombre'], $item['cantidad'], $item['unidad']);
            }
        }
        return implode("\n", $renglones);
    }

    /**
     * Pasa una cantidad a la unidad del ingrediente (g, ml o u). Con el peso de una unidad, también
     * de unidades a gramos y al revés. Devuelve null si no se puede.
     */
    public static function enUnidad(
        ?float $valor,
        ?string $desde,
        string $hacia,
        ?float $gramosPorUnidad = null
    ): ?float {
        $desde = self::unidad($desde);
        if ($valor === null || $desde === null) {
            return null;
        }
        $tabla = self::CONVERSIONES;
        if ($gramosPorUnidad !== null && $gramosPorUnidad > 0 && ($desde === 'u' xor $hacia === 'u')) {
            $tabla['u'] = ['g', $gramosPorUnidad];
        }
        $origen = $tabla[$desde] ?? null;
        $destino = $tabla[$hacia] ?? null;
        if ($origen === null || $destino === null || $origen[0] !== $destino[0]) {
            return null;
        }
        return round($valor * $origen[1] / $destino[1], 2);
    }

    /** @param array<string, mixed> $item */
    private static function orden(array $item): int
    {
        return ($item['categoria'] ?? null) === null ? PHP_INT_MAX : (int) ($item['orden'] ?? 0);
    }

    private static function unidad(?string $unidad): ?string
    {
        $unidad = trim((string) $unidad);
        return $unidad === '' ? null : $unidad;
    }
}
