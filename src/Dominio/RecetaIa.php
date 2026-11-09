<?php

declare(strict_types=1);

namespace Cooki\Dominio;

/**
 * Las reglas para usar lo que devuelve la IA (lógica pura, con pruebas en tests/Dominio/RecetaIaTest).
 *   - Valida y normaliza la receta: campos obligatorios, hasta 15 ingredientes y 12 pasos, unidades permitidas,
 *     cantidades positivas. Lo que no cumple se rechaza: nunca se muestra una receta a medias.
 *   - Cruza cada ingrediente con el catálogo (por nombre o sinónimo, sin tildes ni plurales). Los que no están
 *     quedan como texto libre.
 *   - Rechaza la receta si usa algo de las restricciones de la persona, también por sinónimo o por nombre
 *     ("Dulce de leche" si la restricción es Leche), y si no cumple la adaptación pedida (sin huevo, sin TACC...).
 *     Esto se revisa en el servidor: no alcanza con pedírselo a la IA (riesgo R-15).
 *   - Marca "lo tenés" / "te falta" y arma la clave de la caché.
 *
 * No toca la base: recibe el catálogo, como ImportadorRecetas.
 */
final class RecetaIa
{
    public const UNIDADES = ImportadorRecetas::UNIDADES;
    public const A_GUSTO = 'a gusto';
    public const ETIQUETAS = ['vegetariana', 'sin_tacc', 'economica', 'dulce'];
    public const MAX_INGREDIENTES = 15;
    public const MAX_PASOS = 12;

    /** Opciones del formulario "Pedirle una receta a la IA" (no hay texto libre: solo esto). */
    public const TIPOS = ['almuerzo_cena' => 'Almuerzo o cena', 'dulce' => 'Algo dulce', 'rapida' => 'Algo rápido'];
    public const MINUTOS = [15, 30, 60];
    public const PORCIONES_MAX = 8;

    /** "Adaptar con IA" (HU-31). */
    public const ADAPTACIONES = [
        'sin_huevo' => 'Sin huevo',
        'sin_tacc' => 'Sin TACC',
        'sin_lactosa' => 'Sin lactosa',
        'vegetariana' => 'Vegetariana',
        'con_lo_que_tengo' => 'Con lo que tengo',
    ];

    private const CATEGORIA_LACTEOS = 3;
    private const CATEGORIA_HUEVOS = 5;

    /**
     * Palabras que, en un ingrediente fuera del catálogo, rompen una adaptación.
     * Se buscan como palabras enteras (singular o plural): "pan" no encuentra "panqueque".
     */
    private const PALABRAS_PROHIBIDAS = [
        'sin_huevo' => ['huevo', 'mayonesa', 'clara', 'yema'],
        'sin_tacc' => ['trigo', 'fideo', 'pan', 'pan-rallado', 'galletita', 'avena', 'salsa-de-soja', 'harina-0000',
            'harina-000', 'tapa-de-tarta', 'tapa-de-empanada', 'centeno', 'cebada'],
        'sin_lactosa' => ['leche', 'queso', 'manteca', 'crema', 'yogur', 'ricota'],
        'vegetariana' => ['carne', 'pollo', 'pescado', 'jamon', 'panceta', 'atun', 'chorizo', 'salchicha', 'bife',
            'cerdo', 'merluza', 'milanesa', 'salame', 'nalga', 'peceto', 'osobuco', 'bondiola'],
    ];
    /** Si aparecen, el lácteo no es de vaca o no tiene lactosa ("leche de almendras", "queso sin lactosa"). */
    private const SIN_LACTOSA_OK = ['almendra', 'coco', 'soja', 'avena', 'arroz', 'vegetal', 'deslactosada',
        'lactosa', 'mani', 'castana'];

    /** @var array<string, array{id: int, nombre: string, categoria_id: int}> por slug singular */
    private array $porNombre = [];
    /** @var array<int, list<string>> id → slugs del nombre y de los sinónimos */
    private array $nombresDe = [];

    /**
     * @param list<array{id: int|string, nombre: string, categoria_id: int|string}> $ingredientes catálogo
     * @param array<string, int|string> $sinonimos texto → id de ingrediente
     */
    public function __construct(array $ingredientes, array $sinonimos = [])
    {
        foreach ($ingredientes as $ing) {
            $fila = [
                'id' => (int) $ing['id'],
                'nombre' => $ing['nombre'],
                'categoria_id' => (int) $ing['categoria_id'],
            ];
            $slug = self::singular(Texto::slug($ing['nombre']));
            $this->porNombre[$slug] = $fila;
            $this->nombresDe[$fila['id']][] = $slug;
        }
        foreach ($sinonimos as $texto => $id) {
            $id = (int) $id;
            $slug = self::singular(Texto::slug((string) $texto));
            if (!isset($this->nombresDe[$id])) {
                continue;
            }
            $this->nombresDe[$id][] = $slug;
            $this->porNombre[$slug] ??= $this->porNombre[$this->nombresDe[$id][0]];
        }
    }

    /**
     * El ingrediente del catálogo con ese nombre o sinónimo ("Huevos", "huevo" y "HUEVO" encuentran Huevo).
     * @return array{id: int, nombre: string, categoria_id: int}|null
     */
    public function buscar(string $nombre): ?array
    {
        return $this->porNombre[self::singular(Texto::slug($nombre))] ?? null;
    }

    /**
     * Valida la receta que devolvió la IA y la deja en el formato que se guarda (receta_ia.contenido).
     *
     * @param mixed $json lo que devolvió la IA (ya decodificado)
     * @param list<int> $restringidos ids de los ingredientes que la persona no come
     * @param ?string $adaptacion una clave de ADAPTACIONES, si es una receta adaptada
     * @return array{receta: ?array<string, mixed>, error: ?string}
     */
    public function validar(mixed $json, array $restringidos = [], ?string $adaptacion = null): array
    {
        if (!is_array($json)) {
            return self::rechazo('La IA no devolvió una receta.');
        }
        $errorIa = trim((string) ($json['error'] ?? ''));
        if ($errorIa !== '') {
            return self::rechazo('La IA no pudo armar la receta: ' . mb_substr($errorIa, 0, 150));
        }
        $titulo = self::texto($json['titulo'] ?? '', 120);
        if (mb_strlen($titulo) < 3) {
            return self::rechazo('La receta no tiene título.');
        }
        $porciones = self::entero($json['porciones'] ?? null, 1, 20);
        $prep = self::entero($json['minutos_prep'] ?? null, 1, 600);
        $coccion = self::entero($json['minutos_coccion'] ?? null, 0, 600);
        $dificultad = self::entero($json['dificultad'] ?? null, 1, 3) ?? 1;
        if ($porciones === null || $prep === null || $coccion === null) {
            return self::rechazo('Faltan las porciones o los tiempos de la receta.');
        }

        [$ingredientes, $error] = $this->ingredientes($json['ingredientes'] ?? null);
        if ($error !== null) {
            return self::rechazo($error);
        }
        $error = $this->revisarRestricciones($ingredientes, $restringidos)
            ?? ($adaptacion === null ? null : self::revisarAdaptacion($ingredientes, $adaptacion));
        if ($error !== null) {
            return self::rechazo($error);
        }
        [$pasos, $error] = self::pasos($json['pasos'] ?? null);
        if ($error !== null) {
            return self::rechazo($error);
        }

        $etiquetas = array_values(array_intersect(self::ETIQUETAS, (array) ($json['etiquetas'] ?? [])));
        $cambios = [];
        foreach (array_slice((array) ($json['cambios'] ?? []), 0, 6) as $cambio) {
            $cambio = self::texto($cambio, 150);
            if ($cambio !== '') {
                $cambios[] = $cambio;
            }
        }
        return ['receta' => [
            'titulo' => $titulo,
            'descripcion' => self::texto($json['descripcion'] ?? '', 300),
            'porciones' => $porciones,
            'minutos_prep' => $prep,
            'minutos_coccion' => $coccion,
            'dificultad' => $dificultad,
            'etiquetas' => $etiquetas,
            'ingredientes' => $ingredientes,
            'pasos' => $pasos,
            'por_que' => self::texto($json['por_que'] ?? '', 300),
            'cambios' => $cambios,
        ], 'error' => null];
    }

    /**
     * Valida un reemplazo (HU-32). Si el reemplazo está en el catálogo, se cruza.
     * @param list<int> $restringidos
     * @return array{reemplazo: ?array{texto: string, ingrediente_id: ?int, ratio: float, nota: string}, error: ?string}
     */
    public function validarReemplazo(mixed $json, array $restringidos = []): array
    {
        $no = fn (string $error) => ['reemplazo' => null, 'error' => $error];
        if (!is_array($json)) {
            return $no('La IA no devolvió un reemplazo.');
        }
        $errorIa = trim((string) ($json['error'] ?? ''));
        if ($errorIa !== '') {
            return $no('La IA no encontró un reemplazo: ' . mb_substr($errorIa, 0, 150));
        }
        $texto = self::texto($json['reemplazo'] ?? '', 80);
        $ratio = $json['ratio'] ?? null;
        if ($texto === '' || !(is_int($ratio) || is_float($ratio)) || $ratio <= 0 || $ratio > 50) {
            return $no('La IA devolvió un reemplazo incompleto.');
        }
        $catalogo = $this->buscar($texto);
        $ingrediente = ['nombre' => $catalogo['nombre'] ?? $texto, 'id' => $catalogo['id'] ?? null,
            'categoria_id' => $catalogo['categoria_id'] ?? null];
        $error = $this->revisarRestricciones([$ingrediente], $restringidos);
        if ($error !== null) {
            return $no($error);
        }
        return ['reemplazo' => [
            'texto' => $ingrediente['nombre'],
            'ingrediente_id' => $ingrediente['id'],
            'ratio' => round((float) $ratio, 2),
            'nota' => self::texto($json['nota'] ?? '', 200),
        ], 'error' => null];
    }

    /**
     * Agrega "tengo" a cada ingrediente (se calcula al mostrarla: la despensa cambia).
     * Los ingredientes "a gusto" que son básicos cuentan como que los tenés, igual que en el recetario.
     * @param array<string, mixed> $receta
     * @param list<int> $idsQueTengo despensa con nivel > 0, básicos y "siempre tengo"
     * @return array<string, mixed>
     */
    public static function marcar(array $receta, array $idsQueTengo): array
    {
        $tengo = array_flip($idsQueTengo);
        foreach ($receta['ingredientes'] as &$ing) {
            $ing['tengo'] = $ing['id'] !== null && isset($tengo[$ing['id']]);
        }
        unset($ing);
        return $receta;
    }

    /**
     * Lo que falta de una receta de IA ya marcada, para la lista de compras (como ListaCompras::faltantes()).
     * Los ingredientes fuera del catálogo van como texto libre; la pizca y lo "a gusto", sin cantidad.
     * @param array<string, mixed> $receta lo que devolvió marcar()
     * @return list<array{ingrediente_id: ?int, texto: ?string, nombre: string, cantidad: ?float, unidad: ?string}>
     */
    public static function faltantes(array $receta): array
    {
        $lista = [];
        foreach ($receta['ingredientes'] as $ing) {
            if ($ing['tengo']) {
                continue;
            }
            $lista[] = [
                'ingrediente_id' => $ing['id'],
                'texto' => $ing['id'] === null ? $ing['nombre'] : null,
                'nombre' => $ing['nombre'],
            ] + ListaCompras::escalar($ing['cantidad'], $ing['unidad'] === '' ? null : $ing['unidad']);
        }
        return $lista;
    }

    /**
     * La clave de la caché: sha256 del tipo y de los datos normalizados (claves ordenadas, listas ordenadas).
     * Los mismos datos en otro orden dan la misma clave.
     * @param array<array-key, mixed> $datos
     */
    public static function clave(string $tipo, array $datos): string
    {
        return hash('sha256', $tipo . '|' . json_encode(self::normalizar($datos), JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param array<array-key, mixed> $datos
     * @return array<array-key, mixed>
     */
    private static function normalizar(array $datos): array
    {
        foreach ($datos as &$valor) {
            if (is_array($valor)) {
                $valor = self::normalizar($valor);
            } elseif (is_string($valor)) {
                $valor = mb_strtolower(trim($valor));
            }
        }
        unset($valor);
        if (array_is_list($datos)) {
            usort($datos, fn ($a, $b) => strcmp((string) json_encode($a), (string) json_encode($b)));
        } else {
            ksort($datos);
        }
        return $datos;
    }

    /**
     * @return array{0: list<array{id: ?int, nombre: string, categoria_id: ?int, cantidad: ?float, unidad: string,
     *     peso: int}>, 1: ?string}
     */
    private function ingredientes(mixed $lista): array
    {
        if (!is_array($lista) || $lista === []) {
            return [[], 'La receta no tiene ingredientes.'];
        }
        if (count($lista) > self::MAX_INGREDIENTES) {
            return [[], 'La receta tiene más de ' . self::MAX_INGREDIENTES . ' ingredientes.'];
        }
        $ingredientes = [];
        $vistos = [];
        foreach ($lista as $fila) {
            $nombre = is_array($fila) ? self::texto($fila['nombre'] ?? '', 80) : '';
            if ($nombre === '') {
                return [[], 'Hay un ingrediente sin nombre.'];
            }
            $catalogo = $this->buscar($nombre);
            $nombre = $catalogo['nombre'] ?? mb_strtoupper(mb_substr($nombre, 0, 1)) . mb_substr($nombre, 1);
            $slug = self::singular(Texto::slug($nombre));
            if (isset($vistos[$slug])) {
                continue;
            }
            $vistos[$slug] = true;

            $unidad = trim((string) ($fila['unidad'] ?? ''));
            $cantidad = $fila['cantidad'] ?? null;
            if ($unidad === self::A_GUSTO || $unidad === '' || $cantidad === null) {
                $unidad = '';
                $cantidad = null;
            } elseif (!in_array($unidad, self::UNIDADES, true)) {
                return [[], "La unidad \"{$unidad}\" de {$nombre} no es válida."];
            } elseif (!(is_int($cantidad) || is_float($cantidad)) || $cantidad <= 0 || $cantidad > 100000) {
                return [[], "La cantidad de {$nombre} no es válida."];
            }
            $peso = $fila['peso'] ?? 2;
            $ingredientes[] = [
                'id' => $catalogo['id'] ?? null,
                'nombre' => $nombre,
                'categoria_id' => $catalogo['categoria_id'] ?? null,
                'cantidad' => $cantidad === null ? null : round((float) $cantidad, 2),
                'unidad' => $unidad,
                'peso' => is_int($peso) && isset(Puntaje::PESOS[$peso]) ? $peso : 2,
            ];
        }
        // Toda receta necesita al menos un ingrediente principal (para el puntaje y el recetario)
        if (!in_array(3, array_column($ingredientes, 'peso'), true)) {
            $ingredientes[0]['peso'] = 3;
        }
        return [$ingredientes, null];
    }

    /**
     * @return array{0: list<array{texto: string, minutos: ?int}>, 1: ?string}
     */
    private static function pasos(mixed $lista): array
    {
        if (!is_array($lista) || $lista === []) {
            return [[], 'La receta no tiene pasos.'];
        }
        if (count($lista) > self::MAX_PASOS) {
            return [[], 'La receta tiene más de ' . self::MAX_PASOS . ' pasos.'];
        }
        $pasos = [];
        foreach ($lista as $paso) {
            $texto = is_array($paso) ? ($paso['texto'] ?? '') : $paso;
            // Sin marcadores {ing:...}: en una receta de IA se muestran tal cual
            $texto = (string) preg_replace_callback(
                '/\{ing:([a-z0-9-]+)\}/',
                fn ($m) => str_replace('-', ' ', $m[1]),
                self::texto($texto, 500)
            );
            if ($texto === '') {
                continue;
            }
            $minutos = is_array($paso) ? ($paso['minutos'] ?? null) : null;
            $pasos[] = ['texto' => $texto, 'minutos' => self::entero($minutos, 1, 180)];
        }
        return $pasos === [] ? [[], 'La receta no tiene pasos.'] : [$pasos, null];
    }

    /**
     * @param list<array{id: ?int, nombre: string, categoria_id: ?int}> $ingredientes
     * @param list<int> $restringidos
     */
    private function revisarRestricciones(array $ingredientes, array $restringidos): ?string
    {
        $prohibidos = [];
        foreach ($restringidos as $id) {
            foreach ($this->nombresDe[(int) $id] ?? [] as $slug) {
                $prohibidos[] = $slug;
            }
        }
        $restringidos = array_map('intval', $restringidos);
        foreach ($ingredientes as $ing) {
            $porId = $ing['id'] !== null && in_array($ing['id'], $restringidos, true);
            if ($porId || self::contieneAlguna($ing['nombre'], $prohibidos)) {
                return "La IA propuso {$ing['nombre']}, que está en tus restricciones. Probá de nuevo.";
            }
        }
        return null;
    }

    /**
     * "Adaptar con IA" (HU-31): las adaptaciones que tienen sentido para una receta. Se dejan afuera las que ya
     * cumple ("Sin huevo" si no lleva huevo) y "Con lo que tengo" si no le falta nada.
     * @param list<array{nombre: string, categoria_id: int|string|null}> $ingredientes
     * @return array<string, string> clave → nombre, en el orden de ADAPTACIONES
     */
    public static function adaptacionesPara(array $ingredientes, bool $faltaAlgo): array
    {
        $ingredientes = array_map(fn (array $i) => [
            'nombre' => (string) $i['nombre'],
            'categoria_id' => $i['categoria_id'] === null ? null : (int) $i['categoria_id'],
        ], $ingredientes);
        $sirven = [];
        foreach (self::ADAPTACIONES as $clave => $nombre) {
            $sirve = $clave === 'con_lo_que_tengo'
                ? $faltaAlgo
                : self::revisarAdaptacion($ingredientes, $clave) !== null;
            if ($sirve) {
                $sirven[$clave] = $nombre;
            }
        }
        return $sirven;
    }

    /** @param list<array{nombre: string, categoria_id: ?int}> $ingredientes */
    private static function revisarAdaptacion(array $ingredientes, string $adaptacion): ?string
    {
        $palabras = self::PALABRAS_PROHIBIDAS[$adaptacion] ?? [];
        foreach ($ingredientes as $ing) {
            $slug = Texto::slug($ing['nombre']);
            $mal = match ($adaptacion) {
                'sin_huevo' => $ing['categoria_id'] === self::CATEGORIA_HUEVOS,
                'sin_tacc' => in_array($slug, ImportadorRecetas::CON_TACC, true),
                'sin_lactosa' => ($ing['categoria_id'] === self::CATEGORIA_LACTEOS || $slug === 'dulce-de-leche')
                    && !self::contieneAlguna($ing['nombre'], self::SIN_LACTOSA_OK),
                'vegetariana' => $ing['categoria_id'] === ImportadorRecetas::CATEGORIA_CARNES
                    || in_array($slug, ImportadorRecetas::NO_VEGETARIANOS, true),
                default => false,
            };
            if (!$mal && $adaptacion === 'sin_lactosa') {
                $mal = self::contieneAlguna($ing['nombre'], $palabras)
                    && !self::contieneAlguna($ing['nombre'], self::SIN_LACTOSA_OK);
            } elseif (!$mal) {
                $mal = self::contieneAlguna($ing['nombre'], $palabras);
            }
            if ($mal) {
                $como = mb_strtolower(self::ADAPTACIONES[$adaptacion] ?? $adaptacion);
                return "La receta tenía que ser {$como}, pero lleva {$ing['nombre']}. Probá de nuevo.";
            }
        }
        return null;
    }

    /**
     * ¿El nombre contiene alguna de las palabras (slugs) como palabras enteras? Compara en singular:
     * "Huevos duros" contiene "huevo"; "Panqueque" no contiene "pan"; "Dulce de leche" contiene "leche".
     * @param list<string> $slugs
     */
    private static function contieneAlguna(string $nombre, array $slugs): bool
    {
        $palabras = '-' . self::singular(Texto::slug($nombre)) . '-';
        foreach ($slugs as $slug) {
            if ($slug !== '' && str_contains($palabras, '-' . self::singular($slug) . '-')) {
                return true;
            }
        }
        return false;
    }

    /**
     * La raíz de cada palabra del slug, para comparar singular y plural: se saca la "s" final y después la "e"
     * después de consonante. "huevos" y "huevo" → "huevo"; "limones" y "limón" → "limon"; "panes" y "pan" → "pan".
     * No es español perfecto, pero da lo mismo para las dos formas de cada palabra, que es lo que importa.
     */
    private static function singular(string $slug): string
    {
        return implode('-', array_map(function (string $palabra): string {
            if (strlen($palabra) > 3 && str_ends_with($palabra, 's')) {
                $palabra = substr($palabra, 0, -1);
            }
            if (strlen($palabra) > 3 && preg_match('/[^aeiou]e$/', $palabra)) {
                $palabra = substr($palabra, 0, -1);
            }
            return $palabra;
        }, explode('-', $slug)));
    }

    private static function texto(mixed $valor, int $largo): string
    {
        $texto = is_scalar($valor) ? trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '') : '';
        return mb_substr(strip_tags($texto), 0, $largo);
    }

    private static function entero(mixed $valor, int $min, int $max): ?int
    {
        if (is_float($valor) && floor($valor) === $valor) {
            $valor = (int) $valor;
        }
        return is_int($valor) && $valor >= $min && $valor <= $max ? $valor : null;
    }

    /** @return array{receta: null, error: string} */
    private static function rechazo(string $error): array
    {
        return ['receta' => null, 'error' => $error];
    }
}
