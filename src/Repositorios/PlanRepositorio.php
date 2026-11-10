<?php

declare(strict_types=1);

namespace Cooki\Repositorios;

use Cooki\Dominio\PlanSemanal;
use PDO;

/**
 * Plan semanal (tabla plan_comida): qué se come cada día de la semana, en el almuerzo y en la cena.
 * Cada lugar tiene una receta del recetario o una de "Mis recetas de IA" (guardada), con sus porciones.
 */
final class PlanRepositorio
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Lo planeado entre dos fechas (inclusive), con el título y los minutos de cada receta.
     * La clave es "fecha|momento" ("2026-11-09|cena").
     * @return array<string, array{fecha: string, momento: string, porciones: int, receta_id: ?int,
     *                             receta_ia_id: ?int, titulo: string, minutos: ?int, porciones_base: ?int}>
     */
    public function semana(int $usuarioId, string $desde, string $hasta): array
    {
        $consulta = $this->db->prepare(
            'SELECT p.fecha, p.momento, p.porciones, p.receta_id, p.receta_ia_id,
                    COALESCE(r.titulo, ia.titulo) AS titulo,
                    r.minutos_prep + r.minutos_coccion AS minutos, r.porciones_base
             FROM plan_comida p
             LEFT JOIN receta r ON r.id = p.receta_id
             LEFT JOIN receta_ia ia ON ia.id = p.receta_ia_id
             WHERE p.usuario_id = :usuario AND p.fecha BETWEEN :desde AND :hasta
             ORDER BY p.fecha, p.momento'
        );
        $consulta->execute(['usuario' => $usuarioId, 'desde' => $desde, 'hasta' => $hasta]);
        $plan = [];
        foreach ($consulta->fetchAll() as $fila) {
            $plan[$fila['fecha'] . '|' . $fila['momento']] = [
                'fecha' => (string) $fila['fecha'],
                'momento' => (string) $fila['momento'],
                'porciones' => (int) $fila['porciones'],
                'receta_id' => $fila['receta_id'] === null ? null : (int) $fila['receta_id'],
                'receta_ia_id' => $fila['receta_ia_id'] === null ? null : (int) $fila['receta_ia_id'],
                'titulo' => (string) $fila['titulo'],
                'minutos' => $fila['minutos'] === null ? null : (int) $fila['minutos'],
                'porciones_base' => $fila['porciones_base'] === null ? null : (int) $fila['porciones_base'],
            ];
        }
        return $plan;
    }

    /**
     * Porciones base de una receta que se puede poner en el plan: una del recetario, o una receta de IA que la
     * persona guardó. Null si no existe, no es suya o no la guardó.
     */
    public function porcionesBase(int $usuarioId, ?int $recetaId, ?int $recetaIaId): ?int
    {
        if ($recetaId !== null) {
            $consulta = $this->db->prepare('SELECT porciones_base FROM receta WHERE id = :id');
            $consulta->execute(['id' => $recetaId]);
        } elseif ($recetaIaId !== null) {
            $consulta = $this->db->prepare(
                'SELECT contenido FROM receta_ia WHERE id = :id AND usuario_id = :usuario AND guardada = 1'
            );
            $consulta->execute(['id' => $recetaIaId, 'usuario' => $usuarioId]);
        } else {
            return null;
        }
        $valor = $consulta->fetchColumn();
        if ($valor === false) {
            return null;
        }
        if ($recetaId !== null) {
            return (int) $valor;
        }
        $contenido = json_decode((string) $valor, true);
        return (int) (is_array($contenido) ? ($contenido['porciones'] ?? 4) : 4);
    }

    /** Pone una receta en un lugar del plan (si ya había otra, la cambia). */
    public function guardar(
        int $usuarioId,
        string $fecha,
        string $momento,
        ?int $recetaId,
        ?int $recetaIaId,
        int $porciones
    ): void {
        $consulta = $this->db->prepare(
            'INSERT INTO plan_comida (usuario_id, fecha, momento, receta_id, receta_ia_id, porciones)
             VALUES (:usuario, :fecha, :momento, :receta, :receta_ia, :porciones)
             ON DUPLICATE KEY UPDATE receta_id = VALUES(receta_id), receta_ia_id = VALUES(receta_ia_id),
                                     porciones = VALUES(porciones)'
        );
        $consulta->execute([
            'usuario' => $usuarioId, 'fecha' => $fecha, 'momento' => $momento,
            'receta' => $recetaId, 'receta_ia' => $recetaIaId, 'porciones' => $porciones,
        ]);
    }

    /** Deja vacío un lugar del plan. */
    public function quitar(int $usuarioId, string $fecha, string $momento): bool
    {
        $consulta = $this->db->prepare(
            'DELETE FROM plan_comida WHERE usuario_id = :usuario AND fecha = :fecha AND momento = :momento'
        );
        $consulta->execute(['usuario' => $usuarioId, 'fecha' => $fecha, 'momento' => $momento]);
        return $consulta->rowCount() > 0;
    }

    /** Borra lo planeado para días que ya pasaron (no hay tareas programadas: se hace al guardar). */
    public function limpiar(int $usuarioId, string $hoy): void
    {
        $consulta = $this->db->prepare('DELETE FROM plan_comida WHERE usuario_id = :usuario AND fecha < :hoy');
        $consulta->execute(['usuario' => $usuarioId, 'hoy' => $hoy]);
    }

    // ───────────── Datos para PlanSemanal::simular() y ::armar() ─────────────

    /**
     * La despensa por ingrediente (solo lo que tiene algo), lo que vence antes primero y lo que no vence al final.
     * @return array<int, list<array{nivel: int, cantidad: ?float, unidad: ?string, vence: ?string}>>
     */
    public function despensa(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT ingrediente_id, nivel, cantidad, unidad, fecha_vencimiento FROM despensa_item
             WHERE usuario_id = :usuario AND nivel > 0
             ORDER BY ingrediente_id, fecha_vencimiento IS NULL, fecha_vencimiento'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        $despensa = [];
        foreach ($consulta->fetchAll() as $fila) {
            $despensa[(int) $fila['ingrediente_id']][] = [
                'nivel' => (int) $fila['nivel'],
                'cantidad' => $fila['cantidad'] === null ? null : (float) $fila['cantidad'],
                'unidad' => $fila['unidad'],
                'vence' => $fila['fecha_vencimiento'],
            ];
        }
        return $despensa;
    }

    /**
     * Las comidas del plan como las quiere PlanSemanal::simular(), en orden (de hoy en adelante).
     * @param array<string, array<string, mixed>> $plan lo que devolvió semana()
     * @return list<array<string, mixed>>
     */
    public function comidas(int $usuarioId, array $plan): array
    {
        $deRecetas = $this->ingredientes($usuarioId, array_values(array_filter(array_column($plan, 'receta_id'))));
        $deIa = $this->recetasIa($usuarioId, array_values(array_filter(array_column($plan, 'receta_ia_id'))));
        $comidas = [];
        foreach ($plan as $clave => $comida) {
            $ia = $comida['receta_ia_id'] !== null ? ($deIa[$comida['receta_ia_id']] ?? null) : null;
            $comidas[] = [
                'clave' => $clave,
                'id' => PlanSemanal::valorOpcion($comida['receta_id'], $comida['receta_ia_id']),
                'fecha' => $comida['fecha'],
                'titulo' => $comida['titulo'],
                'porciones' => $comida['porciones'],
                'porciones_base' => $ia['porciones_base'] ?? $comida['porciones_base'],
                'ingredientes' => $ia['ingredientes'] ?? ($deRecetas[$comida['receta_id']] ?? []),
            ];
        }
        return $comidas;
    }

    /**
     * Recetas para "Armá mi semana": las del recetario que no tienen nada que la persona no come y, si quiere,
     * sus recetas de IA guardadas. Cada una con sus ingredientes y si la cocinó en los últimos 7 días.
     * @return list<array{id: string, titulo: string, minutos: int, dificultad: int, porciones_base: int,
     *                    reciente: bool, ingredientes: list<array<string, mixed>>}>
     */
    public function candidatas(int $usuarioId, bool $conIa): array
    {
        $consulta = $this->db->prepare(
            "SELECT r.id, r.titulo, r.minutos_prep + r.minutos_coccion AS minutos, r.dificultad, r.porciones_base,
                    EXISTS (SELECT 1 FROM sesion_cocina s
                             WHERE s.usuario_id = :usuario AND s.receta_id = r.id AND s.estado = 'completada'
                               AND s.finalizada_en >= NOW() - INTERVAL 7 DAY) AS reciente
             FROM receta r
             WHERE NOT EXISTS (SELECT 1 FROM receta_ingrediente ri
                                 JOIN restriccion_usuario ru ON ru.ingrediente_id = ri.ingrediente_id
                                WHERE ri.receta_id = r.id AND ri.es_opcional = 0 AND ru.usuario_id = :usuario2)"
        );
        $consulta->execute(['usuario' => $usuarioId, 'usuario2' => $usuarioId]);
        $ingredientes = $this->ingredientes($usuarioId);
        $candidatas = [];
        foreach ($consulta->fetchAll() as $fila) {
            $candidatas[] = [
                'id' => 'r:' . $fila['id'],
                'titulo' => (string) $fila['titulo'],
                'minutos' => (int) $fila['minutos'],
                'dificultad' => (int) $fila['dificultad'],
                'porciones_base' => (int) $fila['porciones_base'],
                'reciente' => (bool) $fila['reciente'],
                'ingredientes' => $ingredientes[(int) $fila['id']] ?? [],
            ];
        }
        if ($conIa) {
            foreach ($this->recetasIa($usuarioId) as $id => $ia) {
                $candidatas[] = [
                    'id' => 'ia:' . $id, 'titulo' => $ia['titulo'], 'minutos' => $ia['minutos'], 'dificultad' => 2,
                    'porciones_base' => $ia['porciones_base'], 'reciente' => false,
                    'ingredientes' => $ia['ingredientes'],
                ];
            }
        }
        return $candidatas;
    }

    /**
     * Ingredientes de recetas del recetario, por receta (de todas si $ids es null).
     * @param list<int>|null $ids
     * @return array<int, list<array<string, mixed>>>
     */
    private function ingredientes(int $usuarioId, ?array $ids = null): array
    {
        if ($ids === []) {
            return [];
        }
        $marcas = $ids === null ? '' : implode(',', array_fill(0, count($ids), '?'));
        $consulta = $this->db->prepare(
            'SELECT ri.receta_id, ri.ingrediente_id, i.nombre, ri.cantidad, ri.unidad, ri.peso, ri.es_opcional,
                    i.gramos_por_unidad, i.usuario_id IS NOT NULL AS propio,
                    (i.es_basico = 1 OR EXISTS (SELECT 1 FROM usuario_basico ub
                        WHERE ub.usuario_id = ? AND ub.ingrediente_id = ri.ingrediente_id)) AS basico,
                    EXISTS (SELECT 1 FROM restriccion_usuario ru
                        WHERE ru.usuario_id = ? AND ru.ingrediente_id = ri.ingrediente_id) AS restringido
             FROM receta_ingrediente ri JOIN ingrediente i ON i.id = ri.ingrediente_id'
            . ($ids === null ? '' : " WHERE ri.receta_id IN ({$marcas})")
            . ' ORDER BY ri.receta_id, ri.es_opcional, ri.peso DESC, i.nombre'
        );
        $consulta->execute(array_merge([$usuarioId, $usuarioId], $ids ?? []));
        $porReceta = [];
        foreach ($consulta->fetchAll() as $fila) {
            $porReceta[(int) $fila['receta_id']][] = [
                'ingrediente_id' => (int) $fila['ingrediente_id'],
                'nombre' => (string) $fila['nombre'],
                'cantidad' => $fila['cantidad'] === null ? null : (float) $fila['cantidad'],
                'unidad' => $fila['unidad'],
                'peso' => (int) $fila['peso'],
                'opcional' => (bool) $fila['es_opcional'],
                'basico' => (bool) $fila['basico'],
                'restringido' => (bool) $fila['restringido'],
                'gramos_por_unidad' => $fila['gramos_por_unidad'] === null ? null : (float) $fila['gramos_por_unidad'],
                'propio' => (bool) $fila['propio'],
            ];
        }
        return $porReceta;
    }

    /**
     * Las recetas de IA guardadas de la persona (o solo esas $ids), con los ingredientes como los de las recetas
     * del recetario. Lo que no está en el catálogo va con ingrediente_id null.
     * @param list<int>|null $ids
     * @return array<int, array{titulo: string, minutos: int, porciones_base: int,
     *                          ingredientes: list<array<string, mixed>>}>
     */
    private function recetasIa(int $usuarioId, ?array $ids = null): array
    {
        if ($ids === []) {
            return [];
        }
        $marcas = $ids === null ? '' : implode(',', array_fill(0, count($ids), '?'));
        $consulta = $this->db->prepare(
            'SELECT id, titulo, contenido FROM receta_ia WHERE usuario_id = ? AND guardada = 1'
            . ($ids === null ? '' : " AND id IN ({$marcas})")
        );
        $consulta->execute(array_merge([$usuarioId], $ids ?? []));
        $recetas = [];
        $usados = [];
        foreach ($consulta->fetchAll() as $fila) {
            $contenido = json_decode((string) $fila['contenido'], true);
            $contenido = is_array($contenido) ? $contenido : [];
            $recetas[(int) $fila['id']] = [
                'titulo' => (string) $fila['titulo'],
                'minutos' => (int) ($contenido['minutos'] ?? 30),
                'porciones_base' => (int) ($contenido['porciones'] ?? 4),
                'ingredientes' => is_array($contenido['ingredientes'] ?? null) ? $contenido['ingredientes'] : [],
            ];
            foreach ($recetas[(int) $fila['id']]['ingredientes'] as $ing) {
                if (($ing['id'] ?? null) !== null) {
                    $usados[(int) $ing['id']] = true;
                }
            }
        }
        $datos = $this->datosDeIngredientes($usuarioId, array_keys($usados));
        foreach ($recetas as &$receta) {
            $receta['ingredientes'] = array_map(function (array $ing) use ($datos): array {
                $id = ($ing['id'] ?? null) === null ? null : (int) $ing['id'];
                return [
                    'ingrediente_id' => $id,
                    'nombre' => (string) ($ing['nombre'] ?? ''),
                    'cantidad' => ($ing['cantidad'] ?? null) === null ? null : (float) $ing['cantidad'],
                    'unidad' => ($ing['unidad'] ?? '') === '' ? null : (string) $ing['unidad'],
                    'peso' => (int) ($ing['peso'] ?? 2),
                    'opcional' => false,
                ] + ($datos[$id] ?? ['basico' => false, 'restringido' => false, 'gramos_por_unidad' => null,
                    'propio' => $id === null]);
            }, $receta['ingredientes']);
        }
        unset($receta);
        return $recetas;
    }

    /**
     * Si cada ingrediente es básico para la persona, si no lo come y cuánto pesa una unidad.
     * @param list<int> $ids
     * @return array<int, array{basico: bool, restringido: bool, gramos_por_unidad: ?float, propio: bool}>
     */
    private function datosDeIngredientes(int $usuarioId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $consulta = $this->db->prepare(
            "SELECT i.id, i.gramos_por_unidad, i.usuario_id IS NOT NULL AS propio,
                    (i.es_basico = 1 OR EXISTS (SELECT 1 FROM usuario_basico ub
                        WHERE ub.usuario_id = ? AND ub.ingrediente_id = i.id)) AS basico,
                    EXISTS (SELECT 1 FROM restriccion_usuario ru WHERE ru.usuario_id = ? AND ru.ingrediente_id = i.id)
                        AS restringido
             FROM ingrediente i WHERE i.id IN ({$marcas})"
        );
        $consulta->execute(array_merge([$usuarioId, $usuarioId], $ids));
        $datos = [];
        foreach ($consulta->fetchAll() as $fila) {
            $datos[(int) $fila['id']] = [
                'basico' => (bool) $fila['basico'],
                'restringido' => (bool) $fila['restringido'],
                'gramos_por_unidad' => $fila['gramos_por_unidad'] === null ? null : (float) $fila['gramos_por_unidad'],
                'propio' => (bool) $fila['propio'],
            ];
        }
        return $datos;
    }

    /**
     * Todas las recetas del recetario para el desplegable, por título, con si es favorita y si usa algo que la
     * persona no come.
     * @return list<array{id: int, titulo: string, porciones_base: int, favorita: bool, restringida: bool}>
     */
    public function recetasParaElegir(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT r.id, r.titulo, r.porciones_base,
                    EXISTS (SELECT 1 FROM favorito f
                             WHERE f.usuario_id = :usuario AND f.receta_id = r.id) AS favorita,
                    EXISTS (SELECT 1 FROM receta_ingrediente ri
                              JOIN restriccion_usuario ru ON ru.ingrediente_id = ri.ingrediente_id
                             WHERE ri.receta_id = r.id AND ri.es_opcional = 0
                               AND ru.usuario_id = :usuario2) AS restringida
             FROM receta r
             ORDER BY r.titulo'
        );
        $consulta->execute(['usuario' => $usuarioId, 'usuario2' => $usuarioId]);
        return array_map(fn (array $fila): array => [
            'id' => (int) $fila['id'],
            'titulo' => (string) $fila['titulo'],
            'porciones_base' => (int) $fila['porciones_base'],
            'favorita' => (bool) $fila['favorita'],
            'restringida' => (bool) $fila['restringida'],
        ], $consulta->fetchAll());
    }
}
