<?php

declare(strict_types=1);

namespace Cooki\Repositorios;

use PDO;

/**
 * Lo que guarda la IA (incremento 3 · HU-30 a HU-34):
 *   - respuesta_ia: la caché (la misma consulta no se paga dos veces durante 7 días);
 *   - uso_ia: cada pedido, para los límites diarios y el panel de administración;
 *   - receta_ia: las recetas que propuso la IA (guardada = la persona la guardó en "Mis recetas de IA");
 *   - sugerencia_reemplazo: los reemplazos que sugirió, para pasarlos a la tabla sustitucion.
 * También junta lo que se le manda a la IA: despensa, básicos y restricciones (nunca nombre ni correo).
 * Las consultas de recetas filtran por usuario_id: nadie ve las recetas de IA de otro.
 */
final class IaRepositorio
{
    /** Días que dura la caché y que se guardan las recetas generadas que nadie guardó. */
    public const DIAS_CACHE = 7;
    /** Los reemplazos (HU-32) gastan 1 pedido cada tantos. */
    public const REEMPLAZOS_POR_PEDIDO = 5;
    /** Como mucho se mandan tantos ingredientes de la despensa (los que vencen antes primero). */
    private const MAX_DESPENSA = 40;

    public function __construct(private PDO $db)
    {
    }

    // ───────────── Datos para el pedido ─────────────

    /**
     * Catálogo para cruzar la respuesta: la lista general y los ingredientes propios de la persona.
     * @return list<array{id: int, nombre: string, categoria_id: int}>
     */
    public function catalogo(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT id, nombre, categoria_id FROM ingrediente WHERE usuario_id IS NULL OR usuario_id = :usuario
             ORDER BY usuario_id IS NOT NULL, id'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return $consulta->fetchAll();
    }

    /** @return array<string, int> texto del sinónimo → id del ingrediente */
    public function sinonimos(): array
    {
        $filas = $this->db->query('SELECT texto, ingrediente_id FROM sinonimo')->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_map('intval', $filas);
    }

    /**
     * Lo que tiene en la despensa (con algo y sin vencer), sin repetir, lo que vence antes primero.
     * "nivel" es el mayor si está en dos lugares; "dias", los días hasta el vencimiento más cercano.
     * @return list<array{id: int, nombre: string, nivel: int, dias: ?int}>
     */
    public function despensa(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT * FROM (
                SELECT i.id, i.nombre, MAX(d.nivel) AS nivel, MIN(DATEDIFF(d.fecha_vencimiento, CURDATE())) AS dias
                FROM despensa_item d JOIN ingrediente i ON i.id = d.ingrediente_id
                WHERE d.usuario_id = :usuario AND d.nivel > 0
                  AND (d.fecha_vencimiento IS NULL OR d.fecha_vencimiento >= CURDATE())
                GROUP BY i.id, i.nombre
             ) AS t
             ORDER BY dias IS NULL, dias, nombre
             LIMIT ' . self::MAX_DESPENSA
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return array_map(fn (array $f) => [
            'id' => (int) $f['id'],
            'nombre' => $f['nombre'],
            'nivel' => (int) $f['nivel'],
            'dias' => $f['dias'] === null ? null : (int) $f['dias'],
        ], $consulta->fetchAll());
    }

    /**
     * Los básicos: los de todos (sal, aceite, agua) y los que la persona marcó como "Siempre tengo".
     * @return list<array{id: int, nombre: string}>
     */
    public function basicos(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT i.id, i.nombre FROM ingrediente i
             WHERE i.es_basico = 1
                OR EXISTS (SELECT 1 FROM usuario_basico ub WHERE ub.usuario_id = :usuario AND ub.ingrediente_id = i.id)
             ORDER BY i.nombre'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return array_map(fn (array $f) => ['id' => (int) $f['id'], 'nombre' => $f['nombre']], $consulta->fetchAll());
    }

    /**
     * Los ingredientes que cuentan como "lo tenés": en la despensa (con algo y sin vencer), básicos y "Siempre tengo".
     * @return list<int>
     */
    public function idsQueTengo(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT d.ingrediente_id FROM despensa_item d
             WHERE d.usuario_id = :usuario AND d.nivel > 0
               AND (d.fecha_vencimiento IS NULL OR d.fecha_vencimiento >= CURDATE())'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        $ids = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
        return array_values(array_unique(array_merge($ids, array_column($this->basicos($usuarioId), 'id'))));
    }

    /** @return list<array{id: int, nombre: string}> lo que la persona no come */
    public function restricciones(int $usuarioId): array
    {
        $filas = (new PreferenciasRepositorio($this->db))->ingredientes($usuarioId, 'restricciones');
        return array_map(fn (array $f) => ['id' => (int) $f['id'], 'nombre' => $f['nombre']], $filas);
    }

    // ───────────── Caché ─────────────

    /** @return array<string, mixed>|null la respuesta guardada, si tiene menos de 7 días */
    public function cache(string $clave): ?array
    {
        $consulta = $this->db->prepare(
            'SELECT respuesta FROM respuesta_ia
             WHERE clave = :clave AND creado_en >= NOW() - INTERVAL ' . self::DIAS_CACHE . ' DAY'
        );
        $consulta->execute(['clave' => $clave]);
        $texto = $consulta->fetchColumn();
        $datos = $texto === false ? null : json_decode((string) $texto, true);
        return is_array($datos) ? $datos : null;
    }

    /**
     * Cuáles de estas claves tienen una respuesta guardada de menos de 7 días (para marcar "ya consultado").
     * @param list<string> $claves
     * @return list<string>
     */
    public function enCache(array $claves): array
    {
        if ($claves === []) {
            return [];
        }
        $marcas = implode(', ', array_fill(0, count($claves), '?'));
        $consulta = $this->db->prepare(
            "SELECT clave FROM respuesta_ia
             WHERE clave IN ({$marcas}) AND creado_en >= NOW() - INTERVAL " . self::DIAS_CACHE . ' DAY'
        );
        $consulta->execute($claves);
        return $consulta->fetchAll(PDO::FETCH_COLUMN);
    }

    /** @param array<string, mixed> $respuesta */
    public function guardarCache(string $clave, string $tipo, array $respuesta): void
    {
        $this->db->prepare(
            'REPLACE INTO respuesta_ia (clave, tipo, respuesta, creado_en) VALUES (:clave, :tipo, :respuesta, NOW())'
        )->execute([
            'clave' => $clave,
            'tipo' => $tipo,
            'respuesta' => json_encode($respuesta, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Borra la caché vieja y las recetas generadas hace más de 7 días que nadie guardó (y que no se usan en la
     * lista ni en el plan). No hay tareas programadas en el hosting: se llama cada vez que se le pide algo a la IA.
     */
    public function limpiar(): void
    {
        $this->db->exec('DELETE FROM respuesta_ia WHERE creado_en < NOW() - INTERVAL ' . self::DIAS_CACHE . ' DAY');
        $this->db->exec(
            'DELETE FROM receta_ia WHERE guardada = 0 AND creado_en < NOW() - INTERVAL ' . self::DIAS_CACHE . ' DAY
               AND id NOT IN (SELECT receta_ia_id FROM plan_comida WHERE receta_ia_id IS NOT NULL)'
        );
    }

    // ───────────── Uso y límites ─────────────

    /**
     * Pedidos gastados hoy: los que salieron bien y no vinieron de la caché. Los reemplazos cuentan 1 cada 5,
     * redondeando para arriba (el primero de cada 5 ya gasta).
     */
    public function usosHoy(int $usuarioId): int
    {
        $consulta = $this->db->prepare(
            'SELECT COALESCE(SUM(tipo <> \'reemplazo\'), 0)
                    + CEIL(COALESCE(SUM(tipo = \'reemplazo\'), 0) / ' . self::REEMPLAZOS_POR_PEDIDO . ')
             FROM uso_ia
             WHERE usuario_id = :usuario AND ok = 1 AND desde_cache = 0 AND creado_en >= CURDATE()'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return (int) $consulta->fetchColumn();
    }

    /** Reemplazos de hoy que salieron bien y no vinieron de la caché. */
    public function reemplazosHoy(int $usuarioId): int
    {
        $consulta = $this->db->prepare(
            'SELECT COUNT(*) FROM uso_ia
             WHERE usuario_id = :usuario AND tipo = \'reemplazo\' AND ok = 1 AND desde_cache = 0
               AND creado_en >= CURDATE()'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return (int) $consulta->fetchColumn();
    }

    /** Llamadas reales a la IA de hoy, de todo el sitio, también las que fallaron (cuidan el cupo de Google). */
    public function llamadasHoy(): int
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM uso_ia WHERE desde_cache = 0 AND creado_en >= CURDATE()'
        )->fetchColumn();
    }

    public function registrarUso(
        ?int $usuarioId,
        string $tipo,
        bool $desdeCache,
        bool $ok,
        ?string $error = null,
        ?int $tokensEntrada = null,
        ?int $tokensSalida = null,
        ?int $milisegundos = null
    ): void {
        $this->db->prepare(
            'INSERT INTO uso_ia (usuario_id, tipo, desde_cache, ok, error, tokens_entrada, tokens_salida, milisegundos)
             VALUES (:usuario, :tipo, :cache, :ok, :error, :entrada, :salida, :ms)'
        )->execute([
            'usuario' => $usuarioId,
            'tipo' => $tipo,
            'cache' => $desdeCache ? 1 : 0,
            'ok' => $ok ? 1 : 0,
            'error' => $error === null ? null : mb_substr($error, 0, 200),
            'entrada' => $tokensEntrada,
            'salida' => $tokensSalida,
            'ms' => $milisegundos,
        ]);
    }

    // ───────────── Recetas de IA ─────────────

    /** @param array<string, mixed> $contenido la receta validada (RecetaIa::validar) y el pedido */
    public function crearReceta(int $usuarioId, string $tipo, ?int $recetaBase, array $contenido): int
    {
        $this->db->prepare(
            'INSERT INTO receta_ia (usuario_id, tipo, receta_base, titulo, contenido)
             VALUES (:usuario, :tipo, :base, :titulo, :contenido)'
        )->execute([
            'usuario' => $usuarioId,
            'tipo' => $tipo,
            'base' => $recetaBase,
            'titulo' => mb_substr((string) $contenido['titulo'], 0, 120),
            'contenido' => json_encode($contenido, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Una receta de IA de la persona, con el contenido decodificado (o null si no es suya o no existe).
     * @return array<string, mixed>|null
     */
    public function receta(int $usuarioId, int $id): ?array
    {
        $consulta = $this->db->prepare(
            'SELECT r.id, r.tipo, r.receta_base, b.titulo AS titulo_base, r.titulo, r.contenido, r.guardada,
                    r.creado_en
             FROM receta_ia r LEFT JOIN receta b ON b.id = r.receta_base
             WHERE r.id = :id AND r.usuario_id = :usuario'
        );
        $consulta->execute(['id' => $id, 'usuario' => $usuarioId]);
        $fila = $consulta->fetch();
        if ($fila === false) {
            return null;
        }
        $fila['contenido'] = json_decode((string) $fila['contenido'], true) ?: [];
        $fila['guardada'] = (bool) $fila['guardada'];
        return $fila;
    }

    /** Guarda la receta en "Mis recetas de IA" (o la saca, con false). */
    public function marcarGuardada(int $usuarioId, int $id, bool $guardada): bool
    {
        $consulta = $this->db->prepare(
            'UPDATE receta_ia SET guardada = :guardada WHERE id = :id AND usuario_id = :usuario'
        );
        $consulta->execute(['guardada' => $guardada ? 1 : 0, 'id' => $id, 'usuario' => $usuarioId]);
        return $consulta->rowCount() > 0 || $this->receta($usuarioId, $id) !== null;
    }

    public function borrarReceta(int $usuarioId, int $id): bool
    {
        $consulta = $this->db->prepare('DELETE FROM receta_ia WHERE id = :id AND usuario_id = :usuario');
        $consulta->execute(['id' => $id, 'usuario' => $usuarioId]);
        return $consulta->rowCount() > 0;
    }

    /**
     * "Mis recetas de IA": las que la persona guardó, la más nueva primero.
     * @return list<array{id: int, tipo: string, titulo: string, creado_en: string, titulo_base: ?string}>
     */
    public function guardadas(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT r.id, r.tipo, r.titulo, r.creado_en, b.titulo AS titulo_base
             FROM receta_ia r LEFT JOIN receta b ON b.id = r.receta_base
             WHERE r.usuario_id = :usuario AND r.guardada = 1
             ORDER BY r.creado_en DESC, r.id DESC'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return $consulta->fetchAll();
    }

    // ───────────── Reemplazos sugeridos (HU-32) ─────────────

    /**
     * Anota un reemplazo que sugirió la IA, para que la administración lo pase a la tabla sustitucion.
     * Si ya estaba, suma una vez más (y se queda con la proporción y la nota más nuevas).
     * @param array{texto: string, ingrediente_id: ?int, ratio: float, nota: string} $reemplazo
     */
    public function sumarSugerencia(int $ingredienteId, array $reemplazo): void
    {
        $this->db->prepare(
            'INSERT INTO sugerencia_reemplazo (ingrediente_id, reemplazo_id, reemplazo_texto, ratio, nota)
             VALUES (:ingrediente, :reemplazo, :texto, :ratio, :nota)
             ON DUPLICATE KEY UPDATE veces = veces + 1, reemplazo_id = VALUES(reemplazo_id),
                                     ratio = VALUES(ratio), nota = VALUES(nota)'
        )->execute([
            'ingrediente' => $ingredienteId,
            'reemplazo' => $reemplazo['ingrediente_id'],
            'texto' => mb_substr($reemplazo['texto'], 0, 80),
            'ratio' => $reemplazo['ratio'],
            'nota' => $reemplazo['nota'] === '' ? null : mb_substr($reemplazo['nota'], 0, 200),
        ]);
    }

    // ───────────── Administración (HU-34) ─────────────

    /**
     * El uso desde hace $dias días (1 = hoy), por tipo: pedidos, cuántos salieron de la caché, errores,
     * tokens y el tiempo promedio de las llamadas reales que salieron bien.
     * @return array<string, array{pedidos: int, cache: int, errores: int, tokens: int, ms: ?int}>
     */
    public function usoPorTipo(int $dias): array
    {
        $consulta = $this->db->prepare(
            'SELECT tipo, COUNT(*) AS pedidos, COALESCE(SUM(desde_cache), 0) AS cache,
                    COALESCE(SUM(ok = 0), 0) AS errores,
                    COALESCE(SUM(tokens_entrada), 0) + COALESCE(SUM(tokens_salida), 0) AS tokens,
                    AVG(CASE WHEN ok = 1 AND desde_cache = 0 THEN milisegundos END) AS ms
             FROM uso_ia WHERE creado_en >= CURDATE() - INTERVAL :dias DAY
             GROUP BY tipo ORDER BY tipo'
        );
        $consulta->execute(['dias' => max(1, $dias) - 1]);
        $uso = [];
        foreach ($consulta->fetchAll() as $f) {
            $uso[$f['tipo']] = [
                'pedidos' => (int) $f['pedidos'],
                'cache' => (int) $f['cache'],
                'errores' => (int) $f['errores'],
                'tokens' => (int) $f['tokens'],
                'ms' => $f['ms'] === null ? null : (int) round((float) $f['ms']),
            ];
        }
        return $uso;
    }

    /**
     * Los últimos errores (los mensajes de ErrorIa: nunca la clave ni datos de la persona).
     * @return list<array{creado_en: string, tipo: string, error: ?string}>
     */
    public function ultimosErrores(int $cuantos = 10): array
    {
        return $this->db->query(
            'SELECT creado_en, tipo, error FROM uso_ia WHERE ok = 0
             ORDER BY creado_en DESC, id DESC LIMIT ' . max(1, $cuantos)
        )->fetchAll();
    }

    /**
     * Las recetas de IA que guardaron las personas, para revisarlas (sin decir quién las guardó).
     * Primero las que todavía no se pasaron al recetario.
     * @return list<array<string, mixed>>
     */
    public function recetasParaRevisar(): array
    {
        return $this->paraRevisar('1 = 1', []);
    }

    /**
     * Una receta de IA guardada, de cualquier persona (solo para la administración).
     * @return array<string, mixed>|null
     */
    public function recetaParaRevisar(int $id): ?array
    {
        return $this->paraRevisar('r.id = :id', ['id' => $id])[0] ?? null;
    }

    /**
     * @param array<string, int> $parametros
     * @return list<array<string, mixed>>
     */
    private function paraRevisar(string $condicion, array $parametros): array
    {
        $consulta = $this->db->prepare(
            "SELECT r.id, r.tipo, r.titulo, r.contenido, r.creado_en, r.pasada_a, b.titulo AS titulo_base,
                    p.titulo AS titulo_pasada
             FROM receta_ia r
             LEFT JOIN receta b ON b.id = r.receta_base
             LEFT JOIN receta p ON p.id = r.pasada_a
             WHERE r.guardada = 1 AND {$condicion}
             ORDER BY r.pasada_a IS NOT NULL, r.creado_en DESC, r.id DESC
             LIMIT 100"
        );
        $consulta->execute($parametros);
        $filas = $consulta->fetchAll();
        foreach ($filas as &$f) {
            $f['contenido'] = json_decode((string) $f['contenido'], true) ?: [];
        }
        return $filas;
    }

    /** Anota en qué receta del recetario quedó la receta de IA (después de guardarla en receta-editar.php). */
    public function marcarPasada(int $id, int $recetaId): void
    {
        $this->db->prepare('UPDATE receta_ia SET pasada_a = :receta WHERE id = :id')
            ->execute(['receta' => $recetaId, 'id' => $id]);
    }

    /**
     * Los reemplazos que sugirió la IA y que la administración todavía no revisó, los más pedidos primero.
     * "pasada" = ya revisada: se pasó a sustitucion o se descartó (no vuelve aunque la IA la sugiera otra vez).
     * @return list<array{id: int, ingrediente_id: int, ingrediente: string, reemplazo_id: ?int,
     *     reemplazo_texto: string, ratio: ?string, nota: ?string, veces: int}>
     */
    public function sugerenciasPendientes(): array
    {
        $filas = $this->db->query(
            'SELECT s.id, s.ingrediente_id, i.nombre AS ingrediente, s.reemplazo_id, s.reemplazo_texto, s.ratio,
                    s.nota, s.veces
             FROM sugerencia_reemplazo s JOIN ingrediente i ON i.id = s.ingrediente_id
             WHERE s.pasada = 0
             ORDER BY s.veces DESC, i.nombre, s.reemplazo_texto
             LIMIT 100'
        )->fetchAll();
        return array_map(fn (array $f) => ['id' => (int) $f['id'], 'ingrediente_id' => (int) $f['ingrediente_id'],
            'reemplazo_id' => $f['reemplazo_id'] === null ? null : (int) $f['reemplazo_id'],
            'veces' => (int) $f['veces']] + $f, $filas);
    }

    /** @return array<string, mixed>|null una sugerencia pendiente */
    public function sugerencia(int $id): ?array
    {
        foreach ($this->sugerenciasPendientes() as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }
        return null;
    }

    /**
     * "Pasar a sustituciones": agrega el reemplazo a la tabla sustitucion (calidad 3, como en el recetario) y la
     * marca como revisada. Si ese par ya estaba en sustitucion, no lo repite. Devuelve false si ya estaba.
     */
    public function pasarSugerencia(int $id, int $ingredienteId, int $reemplazoId, ?float $ratio, ?string $nota): bool
    {
        $this->db->beginTransaction();
        try {
            $existe = $this->db->prepare(
                'SELECT 1 FROM sustitucion WHERE ingrediente_id = :ingrediente AND reemplazo_id = :reemplazo'
            );
            $existe->execute(['ingrediente' => $ingredienteId, 'reemplazo' => $reemplazoId]);
            $nueva = $existe->fetchColumn() === false;
            if ($nueva) {
                $this->db->prepare(
                    'INSERT INTO sustitucion (ingrediente_id, reemplazo_id, ratio, nota, calidad)
                     VALUES (:ingrediente, :reemplazo, :ratio, :nota, 3)'
                )->execute([
                    'ingrediente' => $ingredienteId,
                    'reemplazo' => $reemplazoId,
                    'ratio' => $ratio ?? 1,
                    'nota' => $nota,
                ]);
            }
            $this->db->prepare('UPDATE sugerencia_reemplazo SET pasada = 1, reemplazo_id = :reemplazo WHERE id = :id')
                ->execute(['reemplazo' => $reemplazoId, 'id' => $id]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $nueva;
    }

    /** "Descartar": queda revisada sin pasarla (no vuelve a la lista aunque la IA la sugiera otra vez). */
    public function descartarSugerencia(int $id): bool
    {
        $consulta = $this->db->prepare('UPDATE sugerencia_reemplazo SET pasada = 1 WHERE id = :id AND pasada = 0');
        $consulta->execute(['id' => $id]);
        return $consulta->rowCount() > 0;
    }
}
