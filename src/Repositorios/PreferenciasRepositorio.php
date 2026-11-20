<?php

declare(strict_types=1);

namespace Cooki\Repositorios;

use PDO;

/**
 * Preferencias de cocina de cada usuario:
 *   - "Siempre tengo" (usuario_basico): cuentan como disponibles en todas las recetas y no se descuentan al cocinar.
 *   - "No puedo comer" (restriccion_usuario): alergias o lo que no come; esas recetas no se recomiendan.
 *   - Favoritas (favorito): las recetas marcadas con ♥.
 *   - Lo de la bienvenida (Dominio\Perfil): dieta y gustos (usuario_tipo), nivel y para cuántos cocina (usuario).
 */
final class PreferenciasRepositorio
{
    /** Tabla de cada lista. Es una lista fija: el nombre de la tabla nunca viene del usuario. */
    public const LISTAS = ['basicos' => 'usuario_basico', 'restricciones' => 'restriccion_usuario'];

    public function __construct(private PDO $db)
    {
    }

    /**
     * Ingredientes de una lista ('basicos' o 'restricciones'), ordenados por nombre.
     * @return list<array{id: int, nombre: string}>
     */
    public function ingredientes(int $usuarioId, string $lista): array
    {
        $tabla = self::LISTAS[$lista];
        $consulta = $this->db->prepare(
            "SELECT i.id, i.nombre FROM {$tabla} t JOIN ingrediente i ON i.id = t.ingrediente_id
             WHERE t.usuario_id = :usuario ORDER BY i.nombre"
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return $consulta->fetchAll();
    }

    /** Agrega un ingrediente a la lista. Si ya estaba, no hace nada. */
    public function agregar(int $usuarioId, string $lista, int $ingredienteId): void
    {
        $tabla = self::LISTAS[$lista];
        $this->db->prepare(
            "INSERT IGNORE INTO {$tabla} (usuario_id, ingrediente_id) VALUES (:usuario, :ingrediente)"
        )->execute(['usuario' => $usuarioId, 'ingrediente' => $ingredienteId]);
    }

    public function quitar(int $usuarioId, string $lista, int $ingredienteId): void
    {
        $tabla = self::LISTAS[$lista];
        $this->db->prepare(
            "DELETE FROM {$tabla} WHERE usuario_id = :usuario AND ingrediente_id = :ingrediente"
        )->execute(['usuario' => $usuarioId, 'ingrediente' => $ingredienteId]);
    }

    /** Marca o desmarca una receta como favorita. Devuelve true si quedó marcada. */
    public function alternarFavorita(int $usuarioId, int $recetaId): bool
    {
        $borrar = $this->db->prepare('DELETE FROM favorito WHERE usuario_id = :usuario AND receta_id = :receta');
        $borrar->execute(['usuario' => $usuarioId, 'receta' => $recetaId]);
        if ($borrar->rowCount() > 0) {
            return false;
        }
        $this->db->prepare(
            'INSERT INTO favorito (usuario_id, receta_id)
             SELECT :usuario, id FROM receta WHERE id = :receta'
        )->execute(['usuario' => $usuarioId, 'receta' => $recetaId]);
        return true;
    }

    /** @return list<int> ids de las recetas favoritas */
    public function favoritas(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT receta_id FROM favorito WHERE usuario_id = :usuario ORDER BY creado_en DESC'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
    }

    // ───────────── Bienvenida (PR D) ─────────────

    /**
     * Lo que contó en la bienvenida. Sin datos: como de todo, sin gustos, sin nivel ni comensales.
     * @return array{dietas: list<string>, gustos: list<string>, nivel: ?int, comensales: ?int}
     */
    public function perfil(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT ut.clase, e.nombre FROM usuario_tipo ut JOIN etiqueta e ON e.id = ut.etiqueta_id
             WHERE ut.usuario_id = :usuario ORDER BY e.nombre'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        $perfil = ['dietas' => [], 'gustos' => [], 'nivel' => null, 'comensales' => null];
        foreach ($consulta->fetchAll() as $fila) {
            $perfil[$fila['clase'] === 'dieta' ? 'dietas' : 'gustos'][] = (string) $fila['nombre'];
        }
        $consulta = $this->db->prepare('SELECT nivel_cocina, comensales FROM usuario WHERE id = :usuario');
        $consulta->execute(['usuario' => $usuarioId]);
        $fila = $consulta->fetch() ?: [];
        $perfil['nivel'] = isset($fila['nivel_cocina']) ? (int) $fila['nivel_cocina'] : null;
        $perfil['comensales'] = isset($fila['comensales']) ? (int) $fila['comensales'] : null;
        return $perfil;
    }

    /**
     * Guarda lo de la bienvenida (reemplaza lo anterior). Los tipos que no existen en la tabla etiqueta se ignoran.
     * @param array{dietas: list<string>, gustos: list<string>, nivel: ?int, comensales: ?int} $perfil
     */
    public function guardarPerfil(int $usuarioId, array $perfil): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM usuario_tipo WHERE usuario_id = :usuario')
                ->execute(['usuario' => $usuarioId]);
            $insertar = $this->db->prepare(
                'INSERT IGNORE INTO usuario_tipo (usuario_id, clase, etiqueta_id)
                 SELECT :usuario, :clase, id FROM etiqueta WHERE nombre = :nombre'
            );
            foreach (['dieta' => $perfil['dietas'], 'gusto' => $perfil['gustos']] as $clase => $nombres) {
                foreach ($nombres as $nombre) {
                    $insertar->execute(['usuario' => $usuarioId, 'clase' => $clase, 'nombre' => $nombre]);
                }
            }
            $this->db->prepare('UPDATE usuario SET nivel_cocina = :nivel, comensales = :comensales WHERE id = :usuario')
                ->execute([
                    'nivel' => $perfil['nivel'], 'comensales' => $perfil['comensales'], 'usuario' => $usuarioId,
                ]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Deja la lista ('basicos' o 'restricciones') exactamente con estos ingredientes.
     * @param list<int> $ingredientes
     */
    public function reemplazar(int $usuarioId, string $lista, array $ingredientes): void
    {
        $tabla = self::LISTAS[$lista];
        $this->db->prepare("DELETE FROM {$tabla} WHERE usuario_id = :usuario")->execute(['usuario' => $usuarioId]);
        foreach (array_unique(array_filter(array_map('intval', $ingredientes))) as $id) {
            $this->agregar($usuarioId, $lista, $id);
        }
    }

    /**
     * Recetas que no van con la dieta del usuario: les falta alguno de sus tipos de dieta
     * (para "vegetariana" alcanza con que sea vegana). Sin dieta, ninguna.
     * @return array<int, true>
     */
    public function fueraDeDieta(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            "SELECT r.id FROM receta r
             WHERE EXISTS (
                SELECT 1 FROM usuario_tipo ut JOIN etiqueta e ON e.id = ut.etiqueta_id
                 WHERE ut.usuario_id = :usuario AND ut.clase = 'dieta'
                   AND NOT EXISTS (
                       SELECT 1 FROM receta_etiqueta re JOIN etiqueta e2 ON e2.id = re.etiqueta_id
                        WHERE re.receta_id = r.id
                          AND (e2.id = e.id OR (e.nombre = 'vegetariana' AND e2.nombre = 'vegana'))))"
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return array_fill_keys(array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN)), true);
    }
}
