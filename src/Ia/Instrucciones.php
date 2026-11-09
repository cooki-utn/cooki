<?php

declare(strict_types=1);

namespace Cooki\Ia;

use Cooki\Dominio\RecetaIa;

/**
 * Lo que se le dice a la IA: las instrucciones de cada uso (en español), el pedido con los datos y el esquema del
 * JSON de la respuesta. El esquema de la receta es el mismo formato del importador (docs/guia-recetas.md) más
 * "por_que" y "cambios", para poder "Pasar al recetario" sin conversiones raras.
 *
 * Al pedido van solo ingredientes, restricciones y opciones: nunca el nombre ni el correo de la persona (R-16).
 */
final class Instrucciones
{
    private const COMUNES = <<<'TXT'
        Sos el ayudante de cocina de Cooki, un sitio argentino para cocinar con lo que hay en casa.
        Escribís en español rioplatense, simple y claro ("cortá", "agregá"), sin emojis.
        Reglas que no se rompen:
        - Nunca uses los ingredientes de "no_usar", ni nada que los contenga
          (por ejemplo, si dice Huevo, tampoco mayonesa).
        - Cantidades solo en estas unidades: g, kg, ml, l, u, cda, cdita, taza, pizca, diente, lata, paquete, atado.
          Si algo va "a gusto", poné unidad "a gusto" y cantidad null.
        - Nombres de ingredientes simples y en singular, como en un recetario ("Huevo", "Cebolla", "Queso rallado").
        - Pasos cortos, de 4 a 10, en orden, sin numerarlos. "minutos" solo en los pasos que tienen una espera
          (hervir, hornear, reposar); en los demás, null.
        - Tiempos realistas. Cocción segura: carne, pollo, pescado y huevo bien cocidos.
        - "peso": 3 para lo principal, 2 para lo secundario, 1 para condimentos y detalles.
        - Si con lo que te dan no se puede, devolvé solo {"error": "el motivo, en una oración"}.
        TXT;

    public static function receta(): string
    {
        return self::COMUNES . "\n" . <<<'TXT'
            Tu tarea: proponer UNA receta casera con lo que la persona tiene.
            - Usá solo ingredientes de "despensa" y de "basicos", más como máximo 3 que no tenga.
            - Priorizá los que vencen antes ("vence_en_dias" más chico) y los que tiene en nivel "poco".
            - Respetá "opciones": porciones, minutos_max (preparación + cocción) y tipo.
            - No repitas ninguna receta de "evitar_titulos".
            - En "por_que" contá en una oración qué aprovecha de lo que vence o de lo que tiene.
            TXT;
    }

    public static function adaptar(): string
    {
        return self::COMUNES . "\n" . <<<'TXT'
            Tu tarea: adaptar la receta de "receta" según "adaptacion", cambiando lo menos posible.
            - sin_huevo, sin_tacc (sin gluten: nada de harina de trigo, fideos, pan, pan rallado, avena
              ni salsa de soja), sin_lactosa (nada de leche, queso, manteca, crema, yogur ni dulce de leche)
              o vegetariana (sin carne, pollo, pescado, fiambre ni atún): reemplazá lo que no va por algo equivalente.
            - con_lo_que_tengo: reemplazá lo que la persona no tiene por cosas de "despensa" o "basicos".
            - Mantené las porciones de la receta original.
            - En "cambios" poné de 1 a 6 frases cortas con lo que cambiaste ("Huevo → 2 cda de fécula con agua").
            - En "por_que" contá en una oración cómo queda el resultado.
            TXT;
    }

    public static function reemplazo(): string
    {
        return self::COMUNES . "\n" . <<<'TXT'
            Tu tarea: decir con qué reemplazar el ingrediente de "falta" en la receta de "receta".
            - Preferí algo de "despensa" o de "basicos"; si no hay nada que sirva, algo común en Argentina.
            - "ratio": cuánto del reemplazo por cada unidad del original (1 = la misma cantidad, 0.5 = la mitad).
            - "nota": una oración corta sobre cómo cambia el resultado.
            TXT;
    }

    /**
     * El pedido con los datos, en JSON (la IA lo entiende bien y no se mezcla con las instrucciones).
     * @param array<string, mixed> $datos
     */
    public static function pedido(array $datos): string
    {
        return "Datos del pedido:\n" . json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Esquema de la receta (formato del importador). Con $conCambios, también la lista "cambios" (adaptar).
     * @return array<string, mixed>
     */
    public static function esquemaReceta(bool $conCambios = false): array
    {
        $texto = ['type' => 'STRING'];
        $entero = ['type' => 'INTEGER'];
        $propiedades = [
            'titulo' => $texto,
            'descripcion' => $texto,
            'porciones' => $entero,
            'minutos_prep' => $entero,
            'minutos_coccion' => $entero,
            'dificultad' => $entero,
            'etiquetas' => ['type' => 'ARRAY', 'items' => ['type' => 'STRING', 'enum' => RecetaIa::ETIQUETAS]],
            'ingredientes' => ['type' => 'ARRAY', 'items' => [
                'type' => 'OBJECT',
                'properties' => [
                    'nombre' => $texto,
                    'cantidad' => ['type' => 'NUMBER', 'nullable' => true],
                    'unidad' => ['type' => 'STRING', 'enum' => [...RecetaIa::UNIDADES, RecetaIa::A_GUSTO]],
                    'peso' => $entero,
                ],
                'required' => ['nombre', 'cantidad', 'unidad', 'peso'],
            ]],
            'pasos' => ['type' => 'ARRAY', 'items' => [
                'type' => 'OBJECT',
                'properties' => ['texto' => $texto, 'minutos' => ['type' => 'INTEGER', 'nullable' => true]],
                'required' => ['texto'],
            ]],
            'por_que' => $texto,
        ];
        $requeridos = ['titulo', 'descripcion', 'porciones', 'minutos_prep', 'minutos_coccion', 'dificultad',
            'ingredientes', 'pasos', 'por_que'];
        if ($conCambios) {
            $propiedades['cambios'] = ['type' => 'ARRAY', 'items' => $texto];
            $requeridos[] = 'cambios';
        }
        $propiedades['error'] = $texto;
        return ['type' => 'OBJECT', 'properties' => $propiedades, 'required' => $requeridos];
    }

    /** @return array<string, mixed> */
    public static function esquemaReemplazo(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'reemplazo' => ['type' => 'STRING'],
                'ratio' => ['type' => 'NUMBER'],
                'nota' => ['type' => 'STRING'],
                'error' => ['type' => 'STRING'],
            ],
            'required' => ['reemplazo', 'ratio', 'nota'],
        ];
    }
}
