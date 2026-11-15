<?php

declare(strict_types=1);

namespace Cooki\Dominio;

/**
 * Los tipos de receta (tabla `etiqueta`): cómo se llaman en pantalla, qué quieren decir y qué ingredientes
 * del catálogo no pueden llevar. La clave es la que se guarda en la base y en los JSON de database/recetas/.
 * "Rápida" no se elige: se calcula con los minutos (preparación + cocción).
 * No usa la base, así se prueba con PHPUnit sin MariaDB.
 */
final class Tipos
{
    /** clave → [nombre, explicación], en el orden en que se muestran */
    public const TIPOS = [
        'vegetariana' => ['Vegetariana', 'Sin carne, pollo ni pescado. Puede llevar huevo, leche o queso.'],
        'vegana' => ['Vegana', 'Sin nada de origen animal: ni carne, ni huevo, ni lácteos, ni miel.'],
        'sin_tacc' => ['Sin TACC', 'Sin trigo, avena, cebada ni centeno. Apta para celíacos.'],
        'sin_lactosa' => ['Sin lactosa', 'Sin leche ni lo que se hace con leche: manteca, crema, queso, yogur.'],
        'sin_huevo' => ['Sin huevo', 'Para quienes son alérgicos al huevo o no lo comen.'],
        'rapida' => ['Rápida', 'Lista en 30 minutos o menos, contando la preparación y la cocción.'],
        'economica' => ['Económica', 'Con ingredientes baratos y fáciles de conseguir.'],
        'dulce' => ['Dulce', 'Postres, tortas, galletitas y meriendas dulces.'],
        'merienda_salada' => ['Merienda salada', 'Para la merienda o para picar: sándwiches, tostados, scones.'],
    ];

    /** Tipos que dicen qué NO lleva la receta: se revisan contra los ingredientes. */
    public const DIETAS = ['vegetariana', 'vegana', 'sin_tacc', 'sin_lactosa', 'sin_huevo'];

    public const RAPIDA = 'rapida';
    public const MINUTOS_RAPIDA = 30;

    public const CATEGORIA_LACTEOS = 3;
    public const CATEGORIA_CARNES = 4;
    public const CATEGORIA_HUEVOS = 5;

    /** Ingredientes con gluten (TACC). */
    public const CON_TACC = ['harina-0000', 'fideos', 'pan-rallado', 'tapa-de-tarta', 'pan', 'tapas-de-empanada',
        'galletitas', 'avena', 'salsa-de-soja'];
    /** Además de la categoría Carnes (que incluye pescados y fiambres). */
    public const NO_VEGETARIANOS = ['atun-en-lata'];
    /** Además de carnes, lácteos y huevos. Las tapas comunes llevan grasa vacuna; las galletitas, manteca o huevo. */
    public const NO_VEGANOS = ['miel', 'dulce-de-leche', 'mayonesa', 'tapa-de-tarta', 'tapas-de-empanada',
        'galletitas', 'chocolate'];
    /** Además de la categoría Lácteos. */
    public const CON_LACTOSA = ['dulce-de-leche'];
    /** Además de la categoría Huevos. */
    public const CON_HUEVO = ['mayonesa'];

    /** El nombre para mostrar: "sin_tacc" → "Sin TACC". Una clave desconocida se muestra prolija igual. */
    public static function nombre(string $clave): string
    {
        return self::TIPOS[$clave][0]
            ?? mb_strtoupper(mb_substr($clave, 0, 1)) . str_replace('_', ' ', mb_substr($clave, 1));
    }

    public static function explicacion(string $clave): string
    {
        return self::TIPOS[$clave][1] ?? '';
    }

    /** El dibujo del tipo (en public/), o null si no tiene. */
    public static function dibujo(string $clave): ?string
    {
        return isset(self::TIPOS[$clave]) ? 'img/tipos/' . $clave . '.svg' : null;
    }

    /**
     * Ordena las claves como en TIPOS y deja afuera las repetidas o vacías (las desconocidas van al final).
     * @param list<string> $claves
     * @return list<string>
     */
    public static function ordenar(array $claves): array
    {
        $orden = array_flip(array_keys(self::TIPOS));
        $claves = array_values(array_unique(array_filter(array_map('trim', $claves))));
        usort($claves, fn ($a, $b) => [$orden[$a] ?? PHP_INT_MAX, $a] <=> [$orden[$b] ?? PHP_INT_MAX, $b]);
        return $claves;
    }

    /** ¿Lleva el tipo "Rápida"? */
    public static function esRapida(int $minutosPrep, int $minutosCoccion): bool
    {
        return $minutosPrep + $minutosCoccion <= self::MINUTOS_RAPIDA;
    }

    /**
     * ¿Este ingrediente del catálogo rompe la dieta? (una de DIETAS)
     * @param string $slug el nombre como slug ("dulce-de-leche")
     */
    public static function rompe(string $dieta, string $slug, int $categoriaId): bool
    {
        $carne = $categoriaId === self::CATEGORIA_CARNES || in_array($slug, self::NO_VEGETARIANOS, true);
        $lacteo = $categoriaId === self::CATEGORIA_LACTEOS || in_array($slug, self::CON_LACTOSA, true);
        $huevo = $categoriaId === self::CATEGORIA_HUEVOS || in_array($slug, self::CON_HUEVO, true);
        return match ($dieta) {
            'vegetariana' => $carne,
            'vegana' => $carne || $lacteo || $huevo || in_array($slug, self::NO_VEGANOS, true),
            'sin_tacc' => in_array($slug, self::CON_TACC, true),
            'sin_lactosa' => $lacteo,
            'sin_huevo' => $huevo,
            default => false,
        };
    }

    /**
     * Las dietas que cumple una receta según sus ingredientes del catálogo (cuentan también los opcionales:
     * para una alergia, más vale no arriesgar).
     * @param list<array{slug: string, categoria_id: int}> $ingredientes
     * @return list<string>
     */
    public static function dietasQueCumple(array $ingredientes): array
    {
        return array_values(array_filter(self::DIETAS, function (string $dieta) use ($ingredientes): bool {
            foreach ($ingredientes as $ing) {
                if (self::rompe($dieta, $ing['slug'], $ing['categoria_id'])) {
                    return false;
                }
            }
            return true;
        }));
    }
}
