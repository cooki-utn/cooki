<?php

declare(strict_types=1);

namespace Cooki\Ia;

use Cooki\Config;
use Cooki\Dominio\Porciones;
use Cooki\Dominio\RecetaIa;
use Cooki\Repositorios\IaRepositorio;
use PDO;

/**
 * La IA del sitio: lo único que usan las páginas y la API (incremento 3 · HU-30 a HU-33).
 *
 * Cada pedido: ¿hay IA? → ¿está en la caché? → ¿quedan pedidos hoy? → llama a la IA → valida (RecetaIa) →
 * guarda la caché y el uso. Si algo falla, lanza ErrorIa con un mensaje amable (paraUsuario()).
 *
 * Límites (DEC-020): a la persona solo le descuentan los pedidos que salieron bien y no vinieron de la caché, y los
 * reemplazos cuentan 1 pedido cada 5 (se gasta con el primero de cada 5);
 * el límite de todo el sitio cuenta todas las llamadas reales, también las que fallaron, para cuidar el cupo.
 */
final class Ia
{
    /** El hosting corta cada página a los 20 s: solo se reintenta si da el tiempo (riesgo R-13). */
    private const SEGUNDOS_PAGINA = 18;

    private IaRepositorio $repo;
    private ?ProveedorIa $proveedor;
    private float $inicio;
    /** Si el último pedido salió de la caché (así no se cuenta dos veces un reemplazo sugerido). */
    private bool $desdeCache = false;
    /** @var array<int, array{restricciones: list<array{id: int, nombre: string}>, despensa: list<string>,
     *     basicos: list<string>}> lo de cada persona que va en los pedidos (se busca una vez por página) */
    private array $contextos = [];

    /**
     * @param ?ProveedorIa $proveedor otro proveedor (por ejemplo, en una prueba); si no, Gemini con el .env
     * @param ?float $inicio cuándo empezó el pedido (microtime); si no, el comienzo de esta página
     */
    public function __construct(PDO $db, ?ProveedorIa $proveedor = null, ?float $inicio = null)
    {
        $this->repo = new IaRepositorio($db);
        $this->proveedor = $proveedor ?? (self::disponible() ? ClienteGemini::desdeConfig() : null);
        $this->inicio = $inicio ?? (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    }

    /** ¿Está configurada la IA? Sin IA_CLAVE, los botones de la IA no se muestran y el resto anda igual. */
    public static function disponible(): bool
    {
        return Config::get('IA_CLAVE') !== '' && Config::get('IA_PROVEEDOR', 'gemini') === 'gemini';
    }

    public static function limiteUsuario(): int
    {
        return max(1, (int) Config::get('IA_LIMITE_USUARIO_DIA', '5'));
    }

    public static function limiteGlobal(): int
    {
        return max(1, (int) Config::get('IA_LIMITE_GLOBAL_DIA', '200'));
    }

    /** "Te quedan 3 pedidos hoy." o el aviso de que ya no le quedan (al lado de cada botón de la IA). */
    public static function textoRestantes(int $restantes): string
    {
        return $restantes === 0
            ? 'Por hoy ya usaste tus ' . self::limiteUsuario() . ' pedidos. Mañana podés pedir más.'
            : 'Te quedan ' . $restantes . ' pedido' . ($restantes === 1 ? '' : 's') . ' hoy.';
    }

    /**
     * El aviso de los reemplazos (HU-32): cada 5 reemplazos se gasta 1 pedido; se gasta con el primero de cada 5.
     */
    public static function textoReemplazos(int $restantes, int $sinGastar): string
    {
        $cada = IaRepositorio::REEMPLAZOS_POR_PEDIDO;
        if ($sinGastar > 0) {
            return 'Te quedan ' . $sinGastar . ' reemplazo' . ($sinGastar === 1 ? '' : 's')
                . " sin gastar pedidos (cada {$cada} reemplazos se gasta 1 pedido).";
        }
        if ($restantes > 0) {
            return "Cada {$cada} reemplazos se gasta 1 pedido: el próximo gasta 1 y te deja " . ($cada - 1)
                . ' más sin gastar.';
        }
        return 'Por hoy no te quedan pedidos para reemplazos nuevos. Los ya consultados los podés ver igual.';
    }

    /** Reemplazos que todavía puede pedir hoy sin gastar otro pedido (ya pagó el primero de cada 5). */
    public function reemplazosSinGastar(int $usuarioId): int
    {
        $cada = IaRepositorio::REEMPLAZOS_POR_PEDIDO;
        $hechos = $this->repo->reemplazosHoy($usuarioId) % $cada;
        return $hechos === 0 ? 0 : $cada - $hechos;
    }

    /** Pedidos que le quedan hoy a la persona. */
    public function restantesHoy(int $usuarioId): int
    {
        return max(0, self::limiteUsuario() - $this->repo->usosHoy($usuarioId));
    }

    /**
     * HU-30: una receta nueva con lo que hay en la despensa. Devuelve el id de la receta de IA (receta_ia).
     * @param array{porciones?: mixed, minutos?: mixed, tipo?: mixed} $opciones lo elegido en el formulario
     * @param list<string> $evitar títulos que ya vio ("Pedir otra")
     * @throws ErrorIa
     */
    public function recetaNueva(int $usuarioId, array $opciones, array $evitar = []): int
    {
        $opciones = self::opciones($opciones);
        $evitar = array_values(array_slice(array_unique(array_filter(array_map(
            fn ($t) => mb_substr(trim((string) $t), 0, 120),
            $evitar
        ))), -5));
        $restricciones = $this->repo->restricciones($usuarioId);
        $idsRestringidos = array_column($restricciones, 'id');
        $despensa = array_values(array_filter(
            $this->repo->despensa($usuarioId),
            fn (array $i) => !in_array($i['id'], $idsRestringidos, true)
        ));
        if (count($despensa) < 2) {
            throw new ErrorIa(
                ErrorIa::DATOS,
                'Para pedirle una receta a la IA, cargá al menos 2 ingredientes en tu despensa.'
            );
        }
        $niveles = [1 => 'poco', 2 => 'normal', 3 => 'mucho'];
        $datos = [
            'despensa' => array_map(fn (array $i) => array_filter([
                'nombre' => $i['nombre'],
                'nivel' => $niveles[$i['nivel']] ?? 'normal',
                // Solo lo que vence en 10 días o menos: así la caché sirve más días
                'vence_en_dias' => $i['dias'] !== null && $i['dias'] <= 10 ? $i['dias'] : null,
            ], fn ($v) => $v !== null), $despensa),
            'basicos' => array_column($this->repo->basicos($usuarioId), 'nombre'),
            'no_usar' => array_column($restricciones, 'nombre'),
            'opciones' => [
                'porciones' => $opciones['porciones'],
                'minutos_max' => $opciones['minutos'],
                'tipo' => RecetaIa::TIPOS[$opciones['tipo']],
            ],
            'evitar_titulos' => $evitar,
        ];
        $receta = $this->pedir(
            $usuarioId,
            'receta',
            $datos,
            Instrucciones::receta(),
            Instrucciones::esquemaReceta(),
            fn (RecetaIa $validador, array $json) => $validador->validar($json, $idsRestringidos)
        );
        $receta['pedido'] = ['opciones' => $opciones, 'evitar' => $evitar];
        return $this->repo->crearReceta($usuarioId, 'nueva', null, $receta);
    }

    /**
     * HU-31: adapta una receta del recetario (sin huevo, sin TACC, sin lactosa, vegetariana o con lo que tiene).
     * La original no cambia: se crea una receta de IA "adaptada". Devuelve su id.
     * @param array<string, mixed> $receta la receta como la devuelve RecetaRepositorio::obtener() para la persona
     * @param int $porciones las porciones que estaba mirando (la adaptada sale con esas)
     * @throws ErrorIa
     */
    public function adaptar(int $usuarioId, array $receta, string $adaptacion, int $porciones): int
    {
        $faltan = self::faltantes($receta);
        if (!isset(RecetaIa::adaptacionesPara($receta['ingredientes'], $faltan !== [])[$adaptacion])) {
            throw new ErrorIa(ErrorIa::DATOS, 'Esa adaptación no hace falta en esta receta: ya la cumple.');
        }
        $porciones = max(Porciones::MINIMO, min(Porciones::MAXIMO, $porciones));
        $datos = $this->datosAdaptar($usuarioId, $receta, $adaptacion, $porciones);
        $idsRestringidos = array_column($this->contexto($usuarioId)['restricciones'], 'id');
        $resultado = $this->pedir(
            $usuarioId,
            'adaptar',
            $datos,
            Instrucciones::adaptar(),
            Instrucciones::esquemaReceta(true),
            fn (RecetaIa $validador, array $json) => $validador->validar($json, $idsRestringidos, $adaptacion)
        );
        $resultado['pedido'] = ['adaptacion' => $adaptacion, 'porciones' => $porciones];
        return $this->repo->crearReceta($usuarioId, 'adaptada', (int) $receta['id'], $resultado);
    }

    /**
     * Las adaptaciones de esta receta (con estas porciones) que ya están en la caché: verlas no gasta un pedido.
     * @param array<string, mixed> $receta como la devuelve RecetaRepositorio::obtener() para la persona
     * @return list<string> claves de RecetaIa::ADAPTACIONES
     */
    public function adaptacionesConsultadas(int $usuarioId, array $receta, int $porciones): array
    {
        $porciones = max(Porciones::MINIMO, min(Porciones::MAXIMO, $porciones));
        $claves = [];
        $posibles = RecetaIa::adaptacionesPara($receta['ingredientes'], self::faltantes($receta) !== []);
        foreach (array_keys($posibles) as $a) {
            $claves[$a] = RecetaIa::clave('adaptar', $this->datosAdaptar($usuarioId, $receta, $a, $porciones));
        }
        $enCache = $this->repo->enCache(array_values($claves));
        return array_keys(array_filter($claves, fn (string $clave) => in_array($clave, $enCache, true)));
    }

    /**
     * Lo que se manda para adaptar. Solo "con lo que tengo" lleva la despensa: así las otras adaptaciones se
     * reusan entre personas (caché).
     * @param array<string, mixed> $receta
     * @return array<string, mixed>
     */
    private function datosAdaptar(int $usuarioId, array $receta, string $adaptacion, int $porciones): array
    {
        $factor = Porciones::factor((int) $receta['porciones_base'], $porciones);
        $porSlug = [];
        foreach ($receta['ingredientes'] as $ing) {
            $porSlug[Porciones::slug($ing['nombre'])] = [
                'nombre' => $ing['nombre'],
                'cantidad' => $ing['cantidad'] === null ? null : (float) $ing['cantidad'],
                'unidad' => $ing['unidad'],
            ];
        }
        $contexto = $this->contexto($usuarioId);
        $datos = [
            'receta' => [
                'titulo' => $receta['titulo'],
                'porciones' => $porciones,
                'ingredientes' => array_map(fn (array $i) => array_filter([
                    'nombre' => $i['nombre'],
                    'cantidad' => $i['cantidad'] === null ? null : round((float) $i['cantidad'] * $factor, 2),
                    'unidad' => $i['unidad'] ?: null,
                    'opcional' => $i['es_opcional'] ? true : null,
                ], fn ($v) => $v !== null), $receta['ingredientes']),
                'pasos' => array_map(
                    fn (array $p) => Porciones::pasoConCantidades($p['instruccion'], $porSlug, $factor),
                    $receta['pasos']
                ),
            ],
            'adaptacion' => $adaptacion,
            'no_usar' => array_column($contexto['restricciones'], 'nombre'),
        ];
        if ($adaptacion === 'con_lo_que_tengo') {
            $datos['falta'] = self::faltantes($receta);
            $datos['despensa'] = $contexto['despensa'];
            $datos['basicos'] = $contexto['basicos'];
        }
        return $datos;
    }

    /**
     * Restricciones, despensa (sin lo restringido) y básicos de la persona, una vez por página.
     * @return array{restricciones: list<array{id: int, nombre: string}>, despensa: list<string>, basicos: list<string>}
     */
    private function contexto(int $usuarioId): array
    {
        if (!isset($this->contextos[$usuarioId])) {
            $restricciones = $this->repo->restricciones($usuarioId);
            $ids = array_column($restricciones, 'id');
            $this->contextos[$usuarioId] = [
                'restricciones' => $restricciones,
                'despensa' => array_values(array_column(array_filter(
                    $this->repo->despensa($usuarioId),
                    fn (array $i) => !in_array($i['id'], $ids, true)
                ), 'nombre')),
                'basicos' => array_column($this->repo->basicos($usuarioId), 'nombre'),
            ];
        }
        return $this->contextos[$usuarioId];
    }

    /**
     * HU-32: con qué reemplazar un ingrediente que le falta de una receta del recetario (preferentemente algo de su
     * despensa). Lo sugerido se anota para la administración (sugerencia_reemplazo).
     * @param array<string, mixed> $receta como la devuelve RecetaRepositorio::obtener() para la persona
     * @return array{texto: string, ingrediente_id: ?int, ratio: float, nota: string, frase: string, tengo: bool}
     * @throws ErrorIa
     */
    public function reemplazo(int $usuarioId, array $receta, int $ingredienteId, int $porciones): array
    {
        $falta = null;
        foreach ($receta['ingredientes'] as $ing) {
            if ((int) $ing['ingrediente_id'] === $ingredienteId) {
                $falta = $ing;
            }
        }
        if ($falta === null) {
            throw new ErrorIa(ErrorIa::DATOS, 'Ese ingrediente no está en la receta.');
        }
        if ($falta['en_despensa'] || $falta['es_basico']) {
            throw new ErrorIa(ErrorIa::DATOS, "Ya tenés {$falta['nombre']}: no hace falta reemplazarlo.");
        }
        $factor = Porciones::factor(
            (int) $receta['porciones_base'],
            max(Porciones::MINIMO, min(Porciones::MAXIMO, $porciones))
        );
        $datos = $this->datosReemplazo($usuarioId, $receta, $falta, $factor);
        $idsRestringidos = array_column($this->contexto($usuarioId)['restricciones'], 'id');
        $reemplazo = $this->pedir(
            $usuarioId,
            'reemplazo',
            $datos,
            Instrucciones::reemplazo(),
            Instrucciones::esquemaReemplazo(),
            function (RecetaIa $validador, array $json) use ($idsRestringidos, $ingredienteId, $falta) {
                $resultado = $validador->validarReemplazo($json, $idsRestringidos);
                // validarReemplazo() ya lo cruzó con el catálogo (por nombre o sinónimo)
                if (($resultado['reemplazo']['ingrediente_id'] ?? null) === $ingredienteId) {
                    return ['reemplazo' => null, 'error' => "La IA propuso el mismo ingrediente ({$falta['nombre']})."];
                }
                return $resultado;
            }
        );
        if (!$this->desdeCache) {
            $this->repo->sumarSugerencia($ingredienteId, $reemplazo);
        }
        $cantidad = $falta['cantidad'] === null
            ? null
            : Porciones::texto((float) $falta['cantidad'] * $reemplazo['ratio'], $falta['unidad'], $factor);
        return $reemplazo + [
            'frase' => RecetaIa::frase($reemplazo['texto'], $reemplazo['ratio'], $cantidad),
            'tengo' => $reemplazo['ingrediente_id'] !== null
                && in_array($reemplazo['ingrediente_id'], $this->repo->idsQueTengo($usuarioId), true),
        ];
    }

    /**
     * Los ingredientes que le faltan de esta receta cuyo reemplazo (con estas porciones) ya está en la caché:
     * verlo no gasta nada.
     * @param array<string, mixed> $receta como la devuelve RecetaRepositorio::obtener() para la persona
     * @return list<int> ids de los ingredientes
     */
    public function reemplazosConsultados(int $usuarioId, array $receta, int $porciones): array
    {
        $factor = Porciones::factor(
            (int) $receta['porciones_base'],
            max(Porciones::MINIMO, min(Porciones::MAXIMO, $porciones))
        );
        $claves = [];
        foreach ($receta['ingredientes'] as $ing) {
            if (!$ing['en_despensa'] && !$ing['es_basico']) {
                $claves[(int) $ing['ingrediente_id']] = RecetaIa::clave(
                    'reemplazo',
                    $this->datosReemplazo($usuarioId, $receta, $ing, $factor)
                );
            }
        }
        $enCache = $this->repo->enCache(array_values($claves));
        return array_keys(array_filter($claves, fn (string $clave) => in_array($clave, $enCache, true)));
    }

    /**
     * Lo que se manda para pedir un reemplazo.
     * @param array<string, mixed> $receta
     * @param array<string, mixed> $falta el ingrediente de la receta que falta
     * @return array<string, mixed>
     */
    private function datosReemplazo(int $usuarioId, array $receta, array $falta, float $factor): array
    {
        $contexto = $this->contexto($usuarioId);
        return [
            'falta' => array_filter([
                'nombre' => $falta['nombre'],
                'cantidad' => $falta['cantidad'] === null ? null : round((float) $falta['cantidad'] * $factor, 2),
                'unidad' => $falta['unidad'] ?: null,
            ], fn ($v) => $v !== null),
            'receta' => [
                'titulo' => $receta['titulo'],
                'ingredientes' => array_column($receta['ingredientes'], 'nombre'),
            ],
            'despensa' => $contexto['despensa'],
            'basicos' => $contexto['basicos'],
            'no_usar' => array_column($contexto['restricciones'], 'nombre'),
        ];
    }

    /**
     * Los nombres de lo que le falta de una receta del recetario (sin contar lo opcional).
     * @param array<string, mixed> $receta como la devuelve RecetaRepositorio::obtener()
     * @return list<string>
     */
    public static function faltantes(array $receta): array
    {
        $faltan = [];
        foreach ($receta['ingredientes'] as $ing) {
            if (!$ing['en_despensa'] && !$ing['es_basico'] && !$ing['es_opcional']) {
                $faltan[] = (string) $ing['nombre'];
            }
        }
        return $faltan;
    }

    /**
     * Las opciones del formulario, dentro de lo permitido (no hay texto libre).
     * @param array<string, mixed> $opciones
     * @return array{porciones: int, minutos: int, tipo: string}
     */
    public static function opciones(array $opciones): array
    {
        $minutos = (int) ($opciones['minutos'] ?? 30);
        $tipo = (string) ($opciones['tipo'] ?? '');
        return [
            'porciones' => max(1, min(RecetaIa::PORCIONES_MAX, (int) ($opciones['porciones'] ?? 2))),
            'minutos' => in_array($minutos, RecetaIa::MINUTOS, true) ? $minutos : 30,
            'tipo' => isset(RecetaIa::TIPOS[$tipo]) ? $tipo : 'almuerzo_cena',
        ];
    }

    /**
     * El camino de todos los pedidos: caché, límites, llamada, validación y registro del uso.
     * @param array<string, mixed> $datos lo que se manda (sin datos personales)
     * @param array<string, mixed> $esquema
     * @param callable(RecetaIa, array<string, mixed>): array{error: ?string} $validar
     * @return array<string, mixed> lo que devolvió $validar (sin la clave "error")
     * @throws ErrorIa
     */
    private function pedir(
        int $usuarioId,
        string $tipo,
        array $datos,
        string $instrucciones,
        array $esquema,
        callable $validar
    ): array {
        if ($this->proveedor === null) {
            throw new ErrorIa(ErrorIa::APAGADA, 'La IA no está disponible ahora. El resto del sitio anda igual.');
        }
        $this->desdeCache = false;
        $validador = new RecetaIa($this->repo->catalogo($usuarioId), $this->repo->sinonimos());
        $clave = RecetaIa::clave($tipo, $datos);

        $guardada = $this->repo->cache($clave);
        if ($guardada !== null) {
            $resultado = $validar($validador, $guardada);
            if ($resultado['error'] === null) {
                $this->desdeCache = true;
                $this->repo->registrarUso($usuarioId, $tipo, true, true);
                return self::sinError($resultado);
            }
        }

        // Un reemplazo que entra en los 5 ya pagados no mira el límite de la persona (sí el del sitio)
        $pagado = $tipo === 'reemplazo' && $this->reemplazosSinGastar($usuarioId) > 0;
        if (!$pagado && $this->repo->usosHoy($usuarioId) >= self::limiteUsuario()) {
            $limite = self::limiteUsuario();
            throw new ErrorIa(
                ErrorIa::LIMITE,
                "Por hoy ya usaste tus {$limite} pedidos a la IA. Mañana podés pedir más."
            );
        }
        if ($this->repo->llamadasHoy() >= self::limiteGlobal()) {
            throw new ErrorIa(
                ErrorIa::LIMITE,
                'Por hoy la IA ya respondió todo lo que podía. Mañana vuelve a estar disponible.'
            );
        }

        $this->repo->limpiar();
        $pedido = Instrucciones::pedido($datos);
        for ($intento = 1;; $intento++) {
            try {
                $respuesta = $this->proveedor->generarJson($instrucciones, $pedido, $esquema);
                $resultado = $validar($validador, $respuesta->datos);
                if ($resultado['error'] !== null) {
                    throw new ErrorIa(ErrorIa::FORMATO, $resultado['error']);
                }
            } catch (ErrorIa $e) {
                $this->repo->registrarUso($usuarioId, $tipo, false, false, $e->getMessage());
                if ($e->motivo === ErrorIa::FORMATO && $intento === 1 && $this->hayTiempoParaOtro()) {
                    continue;
                }
                throw $e;
            }
            break;
        }
        $this->repo->registrarUso(
            $usuarioId,
            $tipo,
            false,
            true,
            null,
            $respuesta->tokensEntrada,
            $respuesta->tokensSalida,
            $respuesta->milisegundos
        );
        $this->repo->guardarCache($clave, $tipo, $respuesta->datos);
        return self::sinError($resultado);
    }

    /** ¿Da el tiempo para otro intento completo antes de que el hosting corte la página? */
    private function hayTiempoParaOtro(): bool
    {
        $timeout = max(1, (int) Config::get('IA_TIMEOUT', '12'));
        return microtime(true) - $this->inicio + $timeout < self::SEGUNDOS_PAGINA;
    }

    /**
     * @param array<string, mixed> $resultado
     * @return array<string, mixed>
     */
    private static function sinError(array $resultado): array
    {
        unset($resultado['error']);
        return count($resultado) === 1 ? (array) reset($resultado) : $resultado;
    }
}
