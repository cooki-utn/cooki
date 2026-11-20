<?php

declare(strict_types=1);

namespace Cooki\Dominio;

/**
 * Lo que la persona contó en la bienvenida (app/bienvenida.php) y cómo cambia las recetas:
 *   - dieta: filtro firme. Solo se le recomiendan (Mi cocina, "Armá mi semana") recetas que tengan TODOS esos tipos.
 *   - gustos: las recetas con esos tipos suben en las recomendaciones.
 *   - nivel: las recetas más difíciles que su nivel bajan un poco (no se ocultan).
 *   - comensales: las porciones con que se anotan las comidas del plan.
 * No usa la base, así se prueba con PHPUnit sin MariaDB.
 */
final class Perfil
{
    /** Las dietas que se ofrecen ("Como de todo" = ninguna). Sin huevo va como ingrediente en "Lo que no comés". */
    public const DIETAS = ['vegetariana', 'vegana', 'sin_tacc', 'sin_lactosa'];

    /** tipo de receta → cómo se pregunta */
    public const GUSTOS = [
        'dulce' => 'Lo dulce',
        'merienda_salada' => 'Lo salado',
        'rapida' => 'Lo rápido',
        'economica' => 'Lo económico',
    ];

    public const NIVELES = [1 => 'Recién empiezo', 2 => 'Me defiendo', 3 => 'Me animo a todo'];

    /** 5 quiere decir "5 o más" */
    public const COMENSALES = [1 => 'Solo/a', 2 => '2', 3 => '3', 4 => '4', 5 => '5 o más'];

    /** Lo que suma cada gusto que cumple la receta, y lo que resta cada punto de dificultad de más. */
    public const PUNTOS_GUSTO = 5;
    public const PUNTOS_DIFICULTAD = 6;

    /**
     * Limpia lo que llegó del formulario: deja solo valores conocidos, sin repetir.
     * @param array<string, mixed> $post
     * @return array{dietas: list<string>, gustos: list<string>, nivel: ?int, comensales: ?int}
     */
    public static function desdeFormulario(array $post): array
    {
        $lista = fn (string $campo, array $validos): array => array_values(array_intersect(
            $validos,
            array_map('strval', is_array($post[$campo] ?? null) ? $post[$campo] : [])
        ));
        $numero = function (string $campo, array $validos) use ($post): ?int {
            $valor = $post[$campo] ?? null;
            $valor = is_string($valor) && ctype_digit($valor) ? (int) $valor : null;
            return $valor !== null && isset($validos[$valor]) ? $valor : null;
        };
        $dietas = $lista('dieta', self::DIETAS);
        // Vegana ya incluye vegetariana: se guarda solo la más exigente
        if (in_array('vegana', $dietas, true)) {
            $dietas = array_values(array_diff($dietas, ['vegetariana']));
        }
        return [
            'dietas' => $dietas,
            'gustos' => $lista('gustos', array_keys(self::GUSTOS)),
            'nivel' => $numero('nivel', self::NIVELES),
            'comensales' => $numero('comensales', self::COMENSALES),
        ];
    }

    /**
     * ¿La receta va con la dieta? Tiene que tener todos los tipos de la dieta (vegana también cuenta como vegetariana).
     * @param list<string> $tiposReceta
     * @param list<string> $dietas
     */
    public static function vaConLaDieta(array $tiposReceta, array $dietas): bool
    {
        if (in_array('vegana', $tiposReceta, true)) {
            $tiposReceta[] = 'vegetariana';
        }
        return array_diff($dietas, $tiposReceta) === [];
    }

    /**
     * Cuánto sube o baja una receta en las recomendaciones por los gustos y el nivel de la persona.
     * @param list<string> $tiposReceta
     * @param list<string> $gustos
     */
    public static function ajuste(array $tiposReceta, int $dificultad, array $gustos, ?int $nivel): int
    {
        $puntos = self::PUNTOS_GUSTO * count(array_intersect($gustos, $tiposReceta));
        if ($nivel !== null && $dificultad > $nivel) {
            $puntos -= self::PUNTOS_DIFICULTAD * ($dificultad - $nivel);
        }
        return $puntos;
    }

    /**
     * "¿Hay algo que no comas?": opciones rápidas. Algunas son un grupo de varios ingredientes del catálogo
     * (Pescado = merluza y atún). clave → [texto, dibujo, nombres de los ingredientes]
     */
    public const NO_COMO = [
        'huevo' => ['Huevo', 'huevo', ['Huevo', 'Mayonesa']],
        'leche' => ['Leche', 'leche', ['Leche']],
        'frutos-secos' => ['Frutos secos', 'nueces', ['Nueces']],
        'pescado' => ['Pescado', 'merluza', ['Merluza', 'Atún en lata']],
        'mariscos' => ['Mariscos', 'langostinos', ['Langostinos']],
        'soja' => ['Soja', 'salsa-de-soja', ['Salsa de soja']],
        'cerdo' => ['Cerdo', 'cerdo', ['Cerdo', 'Panceta', 'Chorizo', 'Jamón cocido']],
        'hongos' => ['Hongos', 'champinon', ['Champiñón']],
        'cebolla' => ['Cebolla', 'cebolla', ['Cebolla', 'Cebolla de verdeo']],
        'morron' => ['Morrón', 'morron', ['Morrón']],
    ];

    /** "¿Qué tenés siempre en casa?": opciones rápidas (la sal, el aceite y la pimienta ya cuentan para todos). */
    public const SIEMPRE_HAY = ['Azúcar', 'Harina 0000', 'Ajo', 'Arroz', 'Fideos', 'Orégano', 'Vinagre',
        'Caldo de verduras', 'Yerba'];

    /** Los ingredientes nuevos que se escriben en la bienvenida quedan en Almacén, por unidad (después se cambian). */
    public const CATEGORIA_NUEVOS = 6;
    public const UNIDAD_NUEVOS = 'u';

    /** Cuántos ingredientes nuevos se pueden escribir por lista de una vez. */
    public const MAXIMO_NUEVOS = 10;

    /**
     * Los ingredientes elegidos en una lista del formulario: "i:15" es un ingrediente; "g:pescado" es un grupo
     * de NO_COMO (se pasa en $grupos: clave → ids). Lo que no se entiende se ignora.
     * @param mixed $valores lo que llegó en $_POST
     * @param array<string, list<int>> $grupos
     * @return list<int>
     */
    public static function ingredientesElegidos(mixed $valores, array $grupos = []): array
    {
        $ids = [];
        foreach (is_array($valores) ? $valores : [] as $valor) {
            $valor = (string) $valor;
            if (preg_match('/^i:(\d{1,9})$/', $valor, $m) === 1) {
                $ids[] = (int) $m[1];
            } elseif (str_starts_with($valor, 'g:') && isset($grupos[substr($valor, 2)])) {
                array_push($ids, ...$grupos[substr($valor, 2)]);
            }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Los nombres escritos a mano ("+ Agregar … como nuevo" o el campo "¿Otra cosa?"): sin espacios de más,
     * con mayúscula, de 2 a 80 letras, sin repetir y como mucho MAXIMO_NUEVOS.
     * @param mixed $valores un texto o una lista de textos
     * @return list<string>
     */
    public static function nombresNuevos(mixed $valores): array
    {
        $nombres = [];
        foreach (is_array($valores) ? $valores : [$valores] as $valor) {
            $texto = trim((string) preg_replace('/\s+/u', ' ', (string) $valor));
            if (mb_strlen($texto) < 2 || mb_strlen($texto) > 80) {
                continue;
            }
            $texto = mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
            $nombres[mb_strtolower($texto)] ??= $texto;
        }
        return array_slice(array_values($nombres), 0, self::MAXIMO_NUEVOS);
    }

    /** Porciones para anotar una comida en el plan: las de la persona si las contó, si no las de la receta. */
    public static function porcionesDelPlan(?int $comensales, int $deLaReceta): int
    {
        return $comensales ?? $deLaReceta;
    }
}
