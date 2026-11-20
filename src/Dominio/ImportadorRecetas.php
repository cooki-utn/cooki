<?php

declare(strict_types=1);

namespace Cooki\Dominio;

/**
 * Valida recetas en JSON (formato de docs/guia-recetas.md) y las deja listas para guardar
 * o para escribirlas como SQL. No usa la base: recibe el catálogo de ingredientes y etiquetas,
 * así se puede probar con PHPUnit sin MariaDB.
 */
final class ImportadorRecetas
{
    public const UNIDADES = ['g', 'kg', 'ml', 'l', 'u', 'cda', 'cdita', 'taza', 'pizca', 'diente', 'lata', 'paquete',
        'atado'];

    /** Las reglas de cada tipo están en Tipos (se dejan acá también porque RecetaIa las usa). */
    public const CON_TACC = Tipos::CON_TACC;
    public const CATEGORIA_CARNES = Tipos::CATEGORIA_CARNES;
    public const NO_VEGETARIANOS = Tipos::NO_VEGETARIANOS;

    /** @var array<string, array{id: int, nombre: string, categoria_id: int}> por slug del nombre o sinónimo */
    private array $porNombre = [];

    /**
     * @param list<array{id: int|string, nombre: string, categoria_id: int|string}> $ingredientes catálogo global
     * @param array<string, int|string> $sinonimos  texto → id de ingrediente
     * @param array<string, int|string> $etiquetas  nombre → id
     */
    public function __construct(array $ingredientes, array $sinonimos, private array $etiquetas)
    {
        $porId = [];
        foreach ($ingredientes as $ing) {
            $fila = [
                'id' => (int) $ing['id'],
                'nombre' => $ing['nombre'],
                'categoria_id' => (int) $ing['categoria_id'],
            ];
            $porId[$fila['id']] = $fila;
            $this->porNombre[Porciones::slug($ing['nombre'])] = $fila;
        }
        foreach ($sinonimos as $texto => $id) {
            $this->porNombre[Porciones::slug((string) $texto)] ??= $porId[(int) $id] ?? null;
        }
        $this->porNombre = array_filter($this->porNombre);
    }

    /**
     * @param mixed $json lo que devolvió json_decode(..., true)
     * @return array{receta: ?array<string, mixed>, errores: list<string>}
     */
    public function validar(mixed $json): array
    {
        $errores = [];
        if (!is_array($json)) {
            return ['receta' => null, 'errores' => ['No es un objeto JSON válido.']];
        }
        $titulo = trim((string) ($json['titulo'] ?? ''));
        if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 150) {
            $errores[] = 'El título debe tener entre 3 y 150 caracteres.';
        }
        $descripcion = trim((string) ($json['descripcion'] ?? ''));
        if (mb_strlen($descripcion) > 500) {
            $errores[] = 'La descripción puede tener hasta 500 caracteres.';
        }
        $numero = function (string $campo, int $min, int $max) use ($json, &$errores): int {
            $valor = $json[$campo] ?? null;
            if (!is_int($valor) || $valor < $min || $valor > $max) {
                $errores[] = "\"{$campo}\" tiene que ser un número entero entre {$min} y {$max}.";
                return $min;
            }
            return $valor;
        };
        $datos = [
            'titulo' => $titulo,
            'slug' => Porciones::slug($titulo),
            'descripcion' => $descripcion,
            'porciones_base' => $numero('porciones', 1, 20),
            'minutos_prep' => $numero('minutos_prep', 1, 600),
            'minutos_coccion' => $numero('minutos_coccion', 0, 600),
            'dificultad' => $numero('dificultad', 1, 3),
        ];

        $etiquetas = [];
        foreach ((array) ($json['etiquetas'] ?? []) as $nombre) {
            if (!isset($this->etiquetas[$nombre])) {
                $errores[] = "La etiqueta \"{$nombre}\" no existe (hay: "
                    . implode(', ', array_keys($this->etiquetas)) . ').';
                continue;
            }
            $etiquetas[$nombre] = (int) $this->etiquetas[$nombre];
        }

        [$ingredientes, $slugs, $erroresIng] = $this->ingredientes($json['ingredientes'] ?? null);
        $errores = array_merge($errores, $erroresIng);

        // Los tipos de dieta se revisan contra los ingredientes ("Dice vegana pero lleva Manteca.")
        foreach (Tipos::DIETAS as $dieta) {
            if (!isset($etiquetas[$dieta])) {
                continue;
            }
            foreach ($ingredientes as $ing) {
                if (Tipos::rompe($dieta, $ing['slug'], $ing['categoria_id'])) {
                    $errores[] = "Dice {$dieta} pero lleva {$ing['nombre']}.";
                }
            }
        }
        // "rapida" no se escribe: se calcula con los minutos
        if (isset($etiquetas[Tipos::RAPIDA])) {
            $errores[] = 'No pongas "rapida": se calcula sola (' . Tipos::MINUTOS_RAPIDA . ' minutos o menos).';
        }
        unset($etiquetas[Tipos::RAPIDA]);
        if (
            isset($this->etiquetas[Tipos::RAPIDA])
            && Tipos::esRapida($datos['minutos_prep'], $datos['minutos_coccion'])
        ) {
            $etiquetas[Tipos::RAPIDA] = (int) $this->etiquetas[Tipos::RAPIDA];
        }

        [$pasos, $erroresPasos] = $this->pasos($json['pasos'] ?? null, array_values($slugs));
        $errores = array_merge($errores, $erroresPasos);

        $receta = $errores === [] ? [
            'datos' => $datos,
            'ingredientes' => array_map(
                fn ($i) => array_diff_key($i, ['slug' => 0, 'categoria_id' => 0]),
                $ingredientes
            ),
            'pasos' => $pasos,
            'etiquetas' => array_values($etiquetas),
        ] : null;
        return ['receta' => $receta, 'errores' => $errores];
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: array<string, string>, 2: list<string>}
     */
    private function ingredientes(mixed $lista): array
    {
        if (!is_array($lista) || $lista === []) {
            return [[], [], ['Falta la lista de "ingredientes".']];
        }
        $errores = [];
        $ingredientes = [];
        $slugs = [];   // nombre → slug del ingrediente del catálogo
        foreach (array_values($lista) as $n => $fila) {
            $donde = 'Ingrediente ' . ($n + 1);
            $nombre = is_array($fila) ? trim((string) ($fila['nombre'] ?? '')) : '';
            $catalogo = $this->porNombre[Porciones::slug($nombre)] ?? null;
            if ($catalogo === null) {
                $errores[] = "{$donde}: \"{$nombre}\" no está en el catálogo (cargalo primero en Ingredientes).";
                continue;
            }
            $slug = Porciones::slug($catalogo['nombre']);
            if (in_array($slug, $slugs, true)) {
                $errores[] = "{$donde}: {$catalogo['nombre']} está repetido.";
                continue;
            }
            $cantidad = $fila['cantidad'] ?? null;
            $esNumero = is_int($cantidad) || is_float($cantidad);
            if ($cantidad !== null && (!$esNumero || $cantidad <= 0 || $cantidad > 100000)) {
                $errores[] = "{$donde}: la cantidad tiene que ser un número mayor que cero.";
            }
            $unidad = isset($fila['unidad']) ? (string) $fila['unidad'] : null;
            if ($unidad !== null && !in_array($unidad, self::UNIDADES, true)) {
                $errores[] = "{$donde}: la unidad \"{$unidad}\" no es válida (" . implode(', ', self::UNIDADES) . ').';
            }
            $peso = $fila['peso'] ?? 2;
            if (!isset(Puntaje::PESOS[$peso])) {
                $errores[] = "{$donde}: \"peso\" tiene que ser 3 (principal), 2 (secundario) o 1 (accesorio).";
                $peso = 2;
            }
            $slugs[$catalogo['nombre']] = $slug;
            $ingredientes[] = [
                'id' => $catalogo['id'],
                'nombre' => $catalogo['nombre'],
                'slug' => $slug,
                'categoria_id' => $catalogo['categoria_id'],
                'cantidad' => $cantidad === null ? null : (float) $cantidad,
                'unidad' => $unidad ?? '',
                'peso' => (int) $peso,
                'opcional' => !empty($fila['opcional']),
                'nota' => mb_substr(trim((string) ($fila['nota'] ?? '')), 0, 100),
            ];
        }
        return [$ingredientes, $slugs, $errores];
    }

    /**
     * @param list<string> $slugs ingredientes de la receta (para revisar los marcadores {ing:...})
     * @return array{0: list<array{texto: string, segundos: ?int}>, 1: list<string>}
     */
    private function pasos(mixed $lista, array $slugs): array
    {
        if (!is_array($lista) || $lista === []) {
            return [[], ['Falta la lista de "pasos".']];
        }
        $errores = [];
        $pasos = [];
        foreach (array_values($lista) as $n => $paso) {
            $texto = trim((string) (is_array($paso) ? ($paso['texto'] ?? '') : $paso));
            $minutos = is_array($paso) ? ($paso['minutos'] ?? null) : null;
            if ($texto === '') {
                $errores[] = 'Paso ' . ($n + 1) . ': está vacío.';
                continue;
            }
            if ($minutos !== null && (!is_int($minutos) || $minutos < 1 || $minutos > 180)) {
                $errores[] = 'Paso ' . ($n + 1) . ': "minutos" tiene que ser un entero entre 1 y 180.';
                $minutos = null;
            }
            preg_match_all('/\{ing:([a-z0-9-]+)\}/', $texto, $marcas);
            foreach (array_diff($marcas[1], $slugs) as $marca) {
                $errores[] = 'Paso ' . ($n + 1) . ": el marcador {ing:{$marca}} no es un ingrediente de la receta.";
            }
            $pasos[] = ['texto' => $texto, 'segundos' => $minutos === null ? null : $minutos * 60];
        }
        return [$pasos, $errores];
    }

    /**
     * La receta como SQL, para cargarla sin PHP (phpMyAdmin, Docker). Borra la anterior con el mismo slug.
     * @param array<string, mixed> $receta lo que devolvió validar()
     */
    public static function aSql(array $receta): string
    {
        $d = $receta['datos'];
        $q = fn (?string $texto) => $texto === null || $texto === ''
            ? 'NULL' : "'" . str_replace(['\\', "'"], ['\\\\', "''"], $texto) . "'";
        $sql = "-- {$d['titulo']}\n"
            . "DELETE FROM receta WHERE slug = {$q($d['slug'])};\n"
            . 'INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, '
            . 'dificultad, '
            . "fuente, autor_id) VALUES\n ({$q($d['slug'])}, {$q($d['titulo'])}, {$q($d['descripcion'])}, "
            . "{$d['porciones_base']}, {$d['minutos_prep']}, {$d['minutos_coccion']}, {$d['dificultad']}, "
            . "'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));\n"
            . "SET @receta = LAST_INSERT_ID();\n";
        $filas = array_map(fn ($i) => sprintf(
            ' (@receta, %d, %s, %s, %d, %s, %s)',
            $i['id'],
            $i['cantidad'] === null ? 'NULL' : rtrim(rtrim(number_format($i['cantidad'], 2, '.', ''), '0'), '.'),
            $q($i['unidad']),
            $i['peso'],
            $i['opcional'] ? 'TRUE' : 'FALSE',
            $q($i['nota'])
        ), $receta['ingredientes']);
        $sql .= "INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) "
            . "VALUES\n" . implode(",\n", $filas) . ";\n";
        $filas = [];
        foreach ($receta['pasos'] as $n => $paso) {
            $filas[] = sprintf(' (@receta, %d, %s, %s)', $n + 1, $q($paso['texto']), $paso['segundos'] ?? 'NULL');
        }
        $sql .= "INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES\n"
            . implode(",\n", $filas) . ";\n";
        if ($receta['etiquetas'] !== []) {
            $sql .= 'INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES '
                . implode(', ', array_map(fn ($id) => "(@receta, {$id})", $receta['etiquetas'])) . ";\n";
        }
        return $sql;
    }

    /**
     * El contenido completo de database/recetas.sql.
     * @param array<array-key, array<string, mixed>> $recetas en el orden de los archivos
     */
    public static function archivoSql(array $recetas): string
    {
        return "-- Recetas generadas con: php scripts/importar-recetas.php --sql\n"
            . "-- No editar a mano: se cambia el .json de database/recetas/ y se vuelve a generar.\n"
            . "-- Se carga después de seed.sql (en Docker, solo, como 03-recetas.sql).\n\n"
            . "SET NAMES utf8mb4;\n\n"
            . implode("\n", array_map([self::class, 'aSql'], array_values($recetas)));
    }
}
