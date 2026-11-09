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

    /** Pedidos de hoy que salieron bien y no vinieron de la caché (los que cuentan para el límite de la persona). */
    public function usosHoy(int $usuarioId): int
    {
        $consulta = $this->db->prepare(
            'SELECT COUNT(*) FROM uso_ia
             WHERE usuario_id = :usuario AND ok = 1 AND desde_cache = 0 AND creado_en >= CURDATE()'
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
}
