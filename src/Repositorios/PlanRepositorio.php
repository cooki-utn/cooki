<?php

declare(strict_types=1);

namespace Cooki\Repositorios;

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
     *                             receta_ia_id: ?int, titulo: string, minutos: ?int}>
     */
    public function semana(int $usuarioId, string $desde, string $hasta): array
    {
        $consulta = $this->db->prepare(
            'SELECT p.fecha, p.momento, p.porciones, p.receta_id, p.receta_ia_id,
                    COALESCE(r.titulo, ia.titulo) AS titulo,
                    r.minutos_prep + r.minutos_coccion AS minutos
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
