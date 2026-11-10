<?php

declare(strict_types=1);

namespace Cooki\Repositorios;

use Cooki\Dominio\ListaCompras;
use DateTimeImmutable;
use PDO;

/**
 * La lista de compras de cada usuario (incremento 3 · HU-26 a HU-29).
 * Todas las consultas filtran por usuario_id: nadie ve ni toca la lista de otro.
 *
 * Un ítem es un ingrediente del catálogo (ingrediente_id) o un texto libre (texto), nunca los dos ni ninguno:
 * la base no lo controla (DEC-018), lo controla agregar().
 * Al comprarlo, pasa a la despensa en una transacción y se guarda cómo estaba antes, para "Deshacer".
 * Lo comprado se sigue mostrando hasta que termina el día.
 */
final class ListaRepositorio
{
    public const LARGO_TEXTO = 80;

    public function __construct(private PDO $db)
    {
    }

    /**
     * Lo que falta comprar y lo comprado hoy. "nombre" = el del ingrediente o el texto libre;
     * "receta" = el título de la receta de la que salió (o null).
     * @return list<array<string, mixed>>
     */
    public function listar(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT l.id, l.ingrediente_id, COALESCE(i.nombre, l.texto) AS nombre, i.usuario_id IS NOT NULL AS propio,
                    i.unidad_default, c.nombre AS categoria, c.orden, c.color_hex, l.cantidad, l.unidad, l.receta_id,
                    COALESCE(r.titulo, ri.titulo) AS receta, l.comprado_en,
                    l.ingrediente_id IS NOT NULL AND l.deshacer IS NOT NULL AS paso_a_despensa
             FROM lista_compra_item l
             LEFT JOIN ingrediente i ON i.id = l.ingrediente_id
             LEFT JOIN categoria c ON c.id = i.categoria_id
             LEFT JOIN receta r ON r.id = l.receta_id
             LEFT JOIN receta_ia ri ON ri.id = l.receta_ia_id
             WHERE l.usuario_id = :usuario AND (l.comprado_en IS NULL OR l.comprado_en >= CURDATE())
             ORDER BY l.creado_en, l.id'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return $consulta->fetchAll();
    }

    /**
     * Solo lo que falta comprar (para la nota de Mi cocina y Mi despensa).
     * @return list<array<string, mixed>>
     */
    public function faltaComprar(int $usuarioId): array
    {
        return array_values(array_filter($this->listar($usuarioId), fn (array $i) => $i['comprado_en'] === null));
    }

    /** Cuántos ítems faltan comprar. */
    public function pendientes(int $usuarioId): int
    {
        $consulta = $this->db->prepare(
            'SELECT COUNT(*) FROM lista_compra_item WHERE usuario_id = :usuario AND comprado_en IS NULL'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return (int) $consulta->fetchColumn();
    }

    /**
     * Ids de los ingredientes que están en la lista sin comprar (para mostrar "En la lista" en una receta).
     * @return list<int>
     */
    public function ingredientesPendientes(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT DISTINCT ingrediente_id FROM lista_compra_item
             WHERE usuario_id = :usuario AND comprado_en IS NULL AND ingrediente_id IS NOT NULL'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Agrega un ingrediente (del catálogo o texto libre). Si ya está sin comprar y las cantidades se pueden sumar
     * (misma unidad, o g con kg, ml con l), se suman en el mismo renglón; si no, va en otro.
     * Un texto libre que coincide con un ingrediente del catálogo (o con un sinónimo) se guarda como ese ingrediente.
     * Devuelve ['id' => id del renglón, 'sumado' => si se sumó a uno que ya estaba], o null si los datos no sirven
     * (ingrediente que no existe o que es de otro usuario, texto vacío o muy largo, cantidad negativa).
     * @return array{id: int, sumado: bool}|null
     */
    public function agregar(
        int $usuarioId,
        ?int $ingredienteId,
        ?string $texto,
        ?float $cantidad = null,
        ?string $unidad = null,
        ?int $recetaId = null,
        ?int $recetaIaId = null
    ): ?array {
        $texto = trim((string) preg_replace('/\s+/u', ' ', (string) $texto));
        if ($ingredienteId === null && $texto !== '') {
            $ingredienteId = (new IngredienteRepositorio($this->db))->idPorNombre($texto, $usuarioId);
        }
        if ($ingredienteId !== null) {
            if (!$this->ingredienteVisible($usuarioId, $ingredienteId)) {
                return null;
            }
            $texto = null;
        } elseif (mb_strlen($texto) < 2 || mb_strlen($texto) > self::LARGO_TEXTO) {
            return null;
        }
        if ($cantidad !== null && ($cantidad <= 0 || $cantidad > 99999)) {
            return null;
        }
        $unidad = mb_substr(trim((string) $unidad), 0, 10);
        $unidad = $cantidad === null ? null : ($unidad === '' ? 'u' : $unidad);

        // ¿Ya está sin comprar? Se suma al primer renglón con una unidad compatible
        $mismo = $ingredienteId !== null ? 'ingrediente_id = :clave' : 'ingrediente_id IS NULL AND texto = :clave';
        $consulta = $this->db->prepare(
            'SELECT id, cantidad, unidad FROM lista_compra_item
             WHERE usuario_id = :usuario AND comprado_en IS NULL AND ' . $mismo . '
             ORDER BY id'
        );
        $consulta->execute(['usuario' => $usuarioId, 'clave' => $ingredienteId ?? $texto]);
        foreach ($consulta->fetchAll() as $fila) {
            $antes = $fila['cantidad'] === null ? null : (float) $fila['cantidad'];
            $suma = ListaCompras::sumar($antes, $fila['unidad'], $cantidad, $unidad);
            if ($suma === null) {
                continue;
            }
            $this->db->prepare(
                'UPDATE lista_compra_item SET cantidad = :cantidad, unidad = :unidad,
                        receta_id = COALESCE(receta_id, :receta), receta_ia_id = COALESCE(receta_ia_id, :receta_ia)
                 WHERE id = :id'
            )->execute($suma + ['receta' => $recetaId, 'receta_ia' => $recetaIaId, 'id' => $fila['id']]);
            return ['id' => (int) $fila['id'], 'sumado' => true];
        }

        $this->db->prepare(
            'INSERT INTO lista_compra_item
                    (usuario_id, ingrediente_id, texto, cantidad, unidad, receta_id, receta_ia_id)
             VALUES (:usuario, :ingrediente, :texto, :cantidad, :unidad, :receta, :receta_ia)'
        )->execute([
            'usuario' => $usuarioId, 'ingrediente' => $ingredienteId, 'texto' => $texto,
            'cantidad' => $cantidad, 'unidad' => $unidad, 'receta' => $recetaId, 'receta_ia' => $recetaIaId,
        ]);
        return ['id' => (int) $this->db->lastInsertId(), 'sumado' => false];
    }

    /**
     * Agrega lo que falta de una receta (ListaCompras::faltantes()). Todo o nada, en una transacción.
     * Lo que ya está en la lista sin comprar desde esa misma receta no se vuelve a agregar: así, tocar dos veces
     * "Agregar lo que falta" no duplica las cantidades (de otra receta, sí se suma). Devuelve cuántos agregó.
     * @param list<array{ingrediente_id: int, cantidad: ?float, unidad: ?string}> $faltantes
     */
    public function agregarFaltantes(
        int $usuarioId,
        array $faltantes,
        ?int $recetaId = null,
        ?int $recetaIaId = null
    ): int {
        $propia = !$this->db->inTransaction();   // el plan semanal la llama adentro de su propia transacción
        if ($propia) {
            $this->db->beginTransaction();
        }
        try {
            $yaEstan = [];
            if ($recetaId !== null || $recetaIaId !== null) {
                $consulta = $this->db->prepare(
                    'SELECT ingrediente_id FROM lista_compra_item
                     WHERE usuario_id = :usuario AND comprado_en IS NULL
                       AND (receta_id = :receta OR receta_ia_id = :receta_ia)'
                );
                $consulta->execute(['usuario' => $usuarioId, 'receta' => $recetaId, 'receta_ia' => $recetaIaId]);
                $yaEstan = array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN));
            }
            $agregados = 0;
            foreach ($faltantes as $f) {
                if (in_array($f['ingrediente_id'], $yaEstan, true)) {
                    continue;
                }
                $hecho = $this->agregar(
                    $usuarioId,
                    $f['ingrediente_id'],
                    null,
                    $f['cantidad'],
                    $f['unidad'],
                    $recetaId,
                    $recetaIaId
                );
                $agregados += $hecho === null ? 0 : 1;
            }
            if ($propia) {
                $this->db->commit();
            }
            return $agregados;
        } catch (\Throwable $e) {
            if ($propia) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /** Quita un renglón de la lista. */
    public function borrar(int $usuarioId, int $id): bool
    {
        $consulta = $this->db->prepare('DELETE FROM lista_compra_item WHERE id = :id AND usuario_id = :usuario');
        $consulta->execute(['id' => $id, 'usuario' => $usuarioId]);
        return $consulta->rowCount() > 0;
    }

    /**
     * Marca como comprados los renglones (sin comprar) de la lista y pasa los ingredientes a la despensa
     * (ListaCompras::alComprar). Los de texto libre se marcan, pero no pasan a la despensa.
     * Todo en una transacción: si algo falla, no queda nada a medias. Devuelve cuántos marcó.
     * $otraCantidad: lo que de verdad se compró, si no fue lo previsto ([id => ['cantidad' => 2.0, 'unidad' => 'kg']]).
     * Esa cantidad es la que pasa a la despensa y queda en el renglón; "Deshacer" vuelve a la prevista.
     * @param list<int> $ids
     * @param array<int, array{cantidad: float, unidad: string}> $otraCantidad
     */
    public function comprar(int $usuarioId, array $ids, array $otraCantidad = []): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)));
        if ($ids === []) {
            return 0;
        }
        $this->db->beginTransaction();
        try {
            $hoy = new DateTimeImmutable((string) $this->db->query('SELECT CURDATE()')->fetchColumn());
            $marcados = 0;
            foreach ($ids as $id) {
                $marcados += $this->comprarUno($usuarioId, $id, $hoy, $otraCantidad[$id] ?? null) ? 1 : 0;
            }
            $this->db->commit();
            return $marcados;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Marca como comprado todo lo que falta comprar (ver comprar()). Devuelve cuántos marcó. */
    public function comprarTodo(int $usuarioId): int
    {
        return $this->comprar($usuarioId, array_map(fn (array $i) => (int) $i['id'], $this->faltaComprar($usuarioId)));
    }

    /**
     * Deshace la compra de hoy de un renglón: vuelve a "sin comprar" y la despensa queda como estaba
     * (si el ingrediente era nuevo en la despensa, se saca). Devuelve false si no hay nada que deshacer.
     */
    public function deshacer(int $usuarioId, int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $consulta = $this->db->prepare(
                'SELECT * FROM lista_compra_item
                 WHERE id = :id AND usuario_id = :usuario AND comprado_en >= CURDATE() FOR UPDATE'
            );
            $consulta->execute(['id' => $id, 'usuario' => $usuarioId]);
            $item = $consulta->fetch();
            if (!$item) {
                $this->db->rollBack();
                return false;
            }
            $antes = json_decode((string) $item['deshacer'], true);
            if (is_array($antes) && isset($antes['id'])) {
                if (!empty($antes['nuevo'])) {
                    $this->db->prepare('DELETE FROM despensa_item WHERE id = :id AND usuario_id = :usuario')
                        ->execute(['id' => $antes['id'], 'usuario' => $usuarioId]);
                } else {
                    $this->db->prepare(
                        'UPDATE despensa_item SET nivel = :nivel, cantidad = :cantidad, unidad = :unidad,
                                fecha_vencimiento = :vence
                         WHERE id = :id AND usuario_id = :usuario'
                    )->execute([
                        'nivel' => (int) $antes['nivel'], 'cantidad' => $antes['cantidad'],
                        'unidad' => $antes['unidad'], 'vence' => $antes['fecha_vencimiento'],
                        'id' => $antes['id'], 'usuario' => $usuarioId,
                    ]);
                }
            }
            if (is_array($antes) && isset($antes['lista'])) {   // se había comprado otra cantidad: vuelve la prevista
                $this->db->prepare('UPDATE lista_compra_item SET cantidad = :cantidad, unidad = :unidad WHERE id = :id')
                    ->execute(['id' => $id] + $antes['lista']);
            }
            $this->db->prepare('UPDATE lista_compra_item SET comprado_en = NULL, deshacer = NULL WHERE id = :id')
                ->execute(['id' => $id]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Un renglón comprado (adentro de la transacción de comprar()).
     * @param array{cantidad: float, unidad: string}|null $otra lo que se compró, si no fue lo previsto
     */
    private function comprarUno(int $usuarioId, int $id, DateTimeImmutable $hoy, ?array $otra = null): bool
    {
        $consulta = $this->db->prepare(
            'SELECT l.id, l.ingrediente_id, l.cantidad, l.unidad, c.nombre AS categoria, i.perecedero,
                    i.vida_util_dias, i.unidad_default, i.gramos_por_unidad
             FROM lista_compra_item l
             LEFT JOIN ingrediente i ON i.id = l.ingrediente_id
             LEFT JOIN categoria c ON c.id = i.categoria_id
             WHERE l.id = :id AND l.usuario_id = :usuario AND l.comprado_en IS NULL FOR UPDATE'
        );
        $consulta->execute(['id' => $id, 'usuario' => $usuarioId]);
        $item = $consulta->fetch();
        if (!$item) {
            return false;
        }
        $deshacer = null;
        $prevista = null;
        if ($otra !== null) {
            $prevista = ['cantidad' => $item['cantidad'], 'unidad' => $item['unidad']];
            $item['cantidad'] = $otra['cantidad'];
            $item['unidad'] = $otra['unidad'];
        }
        if ($item['ingrediente_id'] !== null) {
            // Si ya está en la despensa (en cualquier ubicación), se actualiza el renglón que tiene algo;
            // si todos están agotados, el último que se tocó
            $consulta = $this->db->prepare(
                'SELECT id, nivel, cantidad, unidad, fecha_vencimiento, ubicacion FROM despensa_item
                 WHERE usuario_id = :usuario AND ingrediente_id = :ingrediente
                 ORDER BY nivel > 0 DESC, actualizado_en DESC, id LIMIT 1 FOR UPDATE'
            );
            $consulta->execute(['usuario' => $usuarioId, 'ingrediente' => $item['ingrediente_id']]);
            $despensa = $consulta->fetch() ?: null;
            $nuevo = ListaCompras::alComprar(
                $despensa,
                $item,
                $item['cantidad'] === null ? null : (float) $item['cantidad'],
                $item['unidad'],
                $hoy
            );
            if ($despensa === null) {
                $this->db->prepare(
                    'INSERT INTO despensa_item
                            (usuario_id, ingrediente_id, nivel, cantidad, unidad, fecha_vencimiento, ubicacion)
                     VALUES (:usuario, :ingrediente, :nivel, :cantidad, :unidad, :fecha_vencimiento, :ubicacion)'
                )->execute(['usuario' => $usuarioId, 'ingrediente' => $item['ingrediente_id']] + $nuevo);
                $deshacer = ['id' => (int) $this->db->lastInsertId(), 'nuevo' => true];
            } else {
                unset($nuevo['ubicacion']);
                $this->db->prepare(
                    'UPDATE despensa_item SET nivel = :nivel, cantidad = :cantidad, unidad = :unidad,
                            fecha_vencimiento = :fecha_vencimiento
                     WHERE id = :id'
                )->execute($nuevo + ['id' => $despensa['id']]);
                $deshacer = [
                    'id' => (int) $despensa['id'],
                    'nivel' => (int) $despensa['nivel'],
                    'cantidad' => $despensa['cantidad'],
                    'unidad' => $despensa['unidad'],
                    'fecha_vencimiento' => $despensa['fecha_vencimiento'],
                ];
            }
        }
        if ($prevista !== null) {
            $deshacer = ($deshacer ?? []) + ['lista' => $prevista];
        }
        $this->db->prepare(
            'UPDATE lista_compra_item
             SET comprado_en = NOW(), deshacer = :deshacer, cantidad = :cantidad, unidad = :unidad
             WHERE id = :id'
        )->execute([
            'deshacer' => $deshacer === null ? null : json_encode($deshacer),
            'cantidad' => $item['cantidad'], 'unidad' => $item['unidad'], 'id' => $id,
        ]);
        return true;
    }

    private function ingredienteVisible(int $usuarioId, int $ingredienteId): bool
    {
        $consulta = $this->db->prepare(
            'SELECT 1 FROM ingrediente WHERE id = :id AND (usuario_id IS NULL OR usuario_id = :usuario)'
        );
        $consulta->execute(['id' => $ingredienteId, 'usuario' => $usuarioId]);
        return $consulta->fetchColumn() !== false;
    }
}
