<?php

declare(strict_types=1);

namespace Cooki\Dominio;

use DateTimeImmutable;

/**
 * Plan semanal (incremento 3 · HU-35 a HU-37): lógica pura, sin base de datos (se prueba en PlanSemanalTest).
 *   - La semana son los próximos 7 días a partir de hoy, con almuerzo y cena.
 *   - En cada lugar va una receta del recetario ("r:12") o una de "Mis recetas de IA" ("ia:5").
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
}
