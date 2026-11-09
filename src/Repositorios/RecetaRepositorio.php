<?php

declare(strict_types=1);

namespace Cooki\Repositorios;

use Cooki\Dominio\Puntaje;
use Cooki\Dominio\Texto;
use PDO;

/** Acceso a las recetas, sus ingredientes, pasos y etiquetas. */
final class RecetaRepositorio
{
    public const DIFICULTADES = [1 => 'Fácil', 2 => 'Media', 3 => 'Difícil'];

    public function __construct(private PDO $db)
    {
    }

    /**
     * Buscador de recetas. Filtros posibles (todos opcionales):
     *   q          texto: busca en el título, la descripción y los ingredientes (también por sinónimo)
     *   dificultad 1, 2 o 3
     *   etiqueta   id de etiqueta
     *   puedo        true: solo las que se pueden hacer con lo que hay en la despensa
     *   ingredientes ids de ingredientes que la receta tiene que usar (selector de Mi cocina)
     *   modo         'todos' (usa todos los elegidos, por defecto) o 'alguno' (usa al menos uno)
     *   favoritas    true: solo las que el usuario marcó con ♥
     * Si se pasa $usuarioId, cada receta trae cuántos ingredientes tiene el usuario ("tengo"), si es una de sus
     * favoritas ("favorita") y si usa algo que no puede comer ("restringida").
     *
     * @param array{q?: string, dificultad?: int, etiqueta?: int, puedo?: bool,
     *              ingredientes?: list<int>, modo?: string, favoritas?: bool} $filtros
     * @return list<array<string, mixed>>
     */
    public function buscar(array $filtros = [], int $usuarioId = 0): array
    {
        $condiciones = [];
        $parametros = self::parametrosUsuario($usuarioId);
        if (!empty($filtros['favoritas'])) {
            $condiciones[] = 'EXISTS (SELECT 1 FROM favorito f2
                WHERE f2.receta_id = r.id AND f2.usuario_id = :usuarioF2)';
            $parametros['usuarioF2'] = $usuarioId;
        }

        $texto = trim($filtros['q'] ?? '');
        if ($texto !== '') {
            $condiciones[] = '(r.titulo LIKE :q1 OR r.descripcion LIKE :q2 OR EXISTS (
                SELECT 1 FROM receta_ingrediente ri2
                JOIN ingrediente i2 ON i2.id = ri2.ingrediente_id
                LEFT JOIN sinonimo s ON s.ingrediente_id = i2.id
                WHERE ri2.receta_id = r.id AND (i2.nombre LIKE :q3 OR s.texto LIKE :q4)))';
            foreach (['q1', 'q2', 'q3', 'q4'] as $clave) {
                $parametros[$clave] = '%' . $texto . '%';
            }
        }
        if (!empty($filtros['dificultad'])) {
            $condiciones[] = 'r.dificultad = :dificultad';
            $parametros['dificultad'] = (int) $filtros['dificultad'];
        }
        if (!empty($filtros['etiqueta'])) {
            $condiciones[] = 'EXISTS (SELECT 1 FROM receta_etiqueta re
                WHERE re.receta_id = r.id AND re.etiqueta_id = :etiqueta)';
            $parametros['etiqueta'] = (int) $filtros['etiqueta'];
        }
        $elegidos = array_values(array_unique(array_filter(array_map('intval', $filtros['ingredientes'] ?? []))));
        if ($elegidos !== []) {
            // Un parámetro por ingrediente (:ing0, :ing1...): la consulta sigue siendo preparada
            $marcas = [];
            foreach ($elegidos as $i => $idIngrediente) {
                $marcas[] = ':ing' . $i;
                $parametros['ing' . $i] = $idIngrediente;
            }
            $condiciones[] = '(SELECT COUNT(DISTINCT ri3.ingrediente_id) FROM receta_ingrediente ri3
                WHERE ri3.receta_id = r.id AND ri3.ingrediente_id IN (' . implode(', ', $marcas) . ')) >= :minimo';
            $parametros['minimo'] = ($filtros['modo'] ?? 'todos') === 'alguno' ? 1 : count($elegidos);
        }

        // La consulta de resumen va adentro de otra para poder filtrar y ordenar por "tengo" y "total"
        $sql = 'SELECT * FROM (' . self::SELECT_RESUMEN
            . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '')
            . ' GROUP BY r.id) AS resumen'
            . (!empty($filtros['puedo']) ? ' WHERE tengo >= total' : '')
            . ' ORDER BY titulo';
        $consulta = $this->db->prepare($sql);
        $consulta->execute($parametros);
        return $consulta->fetchAll();
    }

    /**
     * Recetas ordenadas por cuántos de sus ingredientes tiene el usuario (las más completas primero).
     * @return list<array<string, mixed>>
     */
    public function sugeridas(int $usuarioId, int $limite = 6): array
    {
        $sql = 'SELECT * FROM (' . self::SELECT_RESUMEN . ' GROUP BY r.id) AS resumen
                WHERE tengo > 0 AND total > 0
                ORDER BY tengo / total DESC, total - tengo, titulo
                LIMIT ' . max(1, $limite);
        $consulta = $this->db->prepare($sql);
        $consulta->execute(self::parametrosUsuario($usuarioId));
        return $consulta->fetchAll();
    }

    /**
     * Los parámetros que usa SELECT_RESUMEN (con consultas preparadas reales, cada marcador va una sola vez).
     * @return array<string, int>
     */
    private static function parametrosUsuario(int $usuarioId): array
    {
        return ['usuario' => $usuarioId, 'usuarioB' => $usuarioId, 'usuarioF' => $usuarioId, 'usuarioR' => $usuarioId];
    }

    /**
     * Recomendaciones para el usuario con el puntaje del informe (Dominio\Puntaje): cada receta trae
     * "puntaje", "grupo" (ahora, falta1 o casi), "faltantes", "sustituciones" y "usa_lo_que_vence".
     * Las que no entran en ningún grupo no se devuelven; las que usan algo que el usuario no puede
     * comer (restriccion_usuario) tampoco. Ordenadas de más a menos puntaje.
     * @return list<array<string, mixed>>
     */
    public function recomendadas(int $usuarioId, int $limite = 60): array
    {
        $porReceta = [];
        foreach ($this->disponibilidad($usuarioId) as $fila) {
            $porReceta[(int) $fila['receta_id']][] = [
                'nombre' => $fila['nombre'],
                'peso' => (int) $fila['peso'],
                'opcional' => (bool) $fila['es_opcional'],
                'disponible' => match (true) {
                    $fila['en_despensa'] || $fila['basico'] => 1.0,
                    $fila['sustituto'] !== null => 0.5,
                    default => 0.0,
                },
                'vence_pronto' => (bool) $fila['vence_pronto'],
                'sustituto' => $fila['sustituto'],
            ];
        }
        $prohibidas = $this->conRestricciones($usuarioId);
        $recientes = $this->cocinadasHacePoco($usuarioId);
        $resultado = [];
        foreach ($this->buscar([], $usuarioId) as $receta) {
            $id = (int) $receta['id'];
            if (!isset($porReceta[$id]) || isset($prohibidas[$id])) {
                continue;
            }
            $calculo = Puntaje::calcular($porReceta[$id], isset($recientes[$id]));
            if ($calculo['grupo'] === null) {
                continue;
            }
            $resultado[] = $receta + $calculo + ['minutos' => (int) $receta['minutos']];
        }
        usort($resultado, fn ($a, $b) => Puntaje::comparar(
            ['puntaje' => $a['puntaje'], 'minutos' => (int) $a['minutos'], 'dificultad' => (int) $a['dificultad'],
                'titulo' => $a['titulo']],
            ['puntaje' => $b['puntaje'], 'minutos' => (int) $b['minutos'], 'dificultad' => (int) $b['dificultad'],
                'titulo' => $b['titulo']]
        ));
        return array_slice($resultado, 0, max(1, $limite));
    }

    /**
     * Cada ingrediente de cada receta con lo que necesita el puntaje: si el usuario lo tiene (sin vencer y
     * con algo), si es básico (para todos o porque el usuario lo marcó como "siempre tengo"), si vence en
     * 3 días o menos y, si le falta, el nombre de un sustituto que sí tiene (el de mejor calidad).
     * @return list<array<string, mixed>>
     */
    private function disponibilidad(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT ri.receta_id, i.nombre, ri.peso, ri.es_opcional,
                    d.ingrediente_id IS NOT NULL AS en_despensa,
                    (i.es_basico = 1 OR ub.ingrediente_id IS NOT NULL) AS basico,
                    COALESCE(d.vence_pronto, 0) AS vence_pronto,
                    (SELECT i2.nombre FROM sustitucion s
                       JOIN ingrediente i2 ON i2.id = s.reemplazo_id
                      WHERE s.ingrediente_id = ri.ingrediente_id
                        AND (i2.es_basico = 1 OR EXISTS (
                             SELECT 1 FROM despensa_item d2
                              WHERE d2.usuario_id = :usuario2 AND d2.ingrediente_id = s.reemplazo_id AND d2.nivel > 0
                                AND (d2.fecha_vencimiento IS NULL OR d2.fecha_vencimiento >= CURDATE())))
                      ORDER BY s.calidad DESC, i2.nombre LIMIT 1) AS sustituto
             FROM receta_ingrediente ri
             JOIN ingrediente i ON i.id = ri.ingrediente_id
             LEFT JOIN (SELECT ingrediente_id,
                               MIN(fecha_vencimiento) <= CURDATE() + INTERVAL 3 DAY AS vence_pronto
                          FROM despensa_item
                         WHERE usuario_id = :usuario AND nivel > 0
                           AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= CURDATE())
                         GROUP BY ingrediente_id) d ON d.ingrediente_id = ri.ingrediente_id
             LEFT JOIN usuario_basico ub ON ub.usuario_id = :usuario3 AND ub.ingrediente_id = ri.ingrediente_id'
        );
        $consulta->execute(['usuario' => $usuarioId, 'usuario2' => $usuarioId, 'usuario3' => $usuarioId]);
        return $consulta->fetchAll();
    }

    /** @return array<int, true> recetas que usan algún ingrediente que el usuario no puede comer */
    private function conRestricciones(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT DISTINCT ri.receta_id FROM receta_ingrediente ri
             JOIN restriccion_usuario ru ON ru.ingrediente_id = ri.ingrediente_id AND ru.usuario_id = :usuario
             WHERE ri.es_opcional = 0'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return array_fill_keys(array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    /** @return array<int, true> recetas que el usuario terminó en el modo cocina en los últimos 7 días */
    private function cocinadasHacePoco(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            "SELECT DISTINCT receta_id FROM sesion_cocina
             WHERE usuario_id = :usuario AND estado = 'completada' AND finalizada_en >= NOW() - INTERVAL 7 DAY"
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return array_fill_keys(array_map('intval', $consulta->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    /**
     * Datos de una receta con sus ingredientes (indicando si el usuario los tiene), pasos y etiquetas.
     * @return array<string, mixed>|null
     */
    public function obtener(int $id, int $usuarioId = 0): ?array
    {
        $consulta = $this->db->prepare(
            'SELECT r.*, u.nombre AS autor FROM receta r LEFT JOIN usuario u ON u.id = r.autor_id WHERE r.id = :id'
        );
        $consulta->execute(['id' => $id]);
        $receta = $consulta->fetch();
        if (!$receta) {
            return null;
        }

        $consulta = $this->db->prepare(
            'SELECT ri.ingrediente_id, i.nombre, i.categoria_id, ri.cantidad, ri.unidad, ri.peso, ri.es_opcional,
                    ri.nota,
                    (i.es_basico = 1 OR EXISTS (SELECT 1 FROM usuario_basico ub
                        WHERE ub.usuario_id = :usuario3 AND ub.ingrediente_id = ri.ingrediente_id)) AS es_basico,
                    EXISTS (SELECT 1 FROM restriccion_usuario ru
                        WHERE ru.usuario_id = :usuario4 AND ru.ingrediente_id = ri.ingrediente_id) AS restringido,
                    EXISTS (SELECT 1 FROM despensa_item d
                            WHERE d.usuario_id = :usuario AND d.ingrediente_id = ri.ingrediente_id AND d.nivel > 0
                              AND (d.fecha_vencimiento IS NULL OR d.fecha_vencimiento >= CURDATE())) AS en_despensa,
                    (SELECT CONCAT(i2.nombre, IF(s.nota IS NULL, \'\', CONCAT(\' · \', s.nota)))
                       FROM sustitucion s JOIN ingrediente i2 ON i2.id = s.reemplazo_id
                      WHERE s.ingrediente_id = ri.ingrediente_id
                        AND (i2.es_basico = 1 OR EXISTS (
                             SELECT 1 FROM despensa_item d2
                              WHERE d2.usuario_id = :usuario2 AND d2.ingrediente_id = s.reemplazo_id AND d2.nivel > 0
                                AND (d2.fecha_vencimiento IS NULL OR d2.fecha_vencimiento >= CURDATE())))
                      ORDER BY s.calidad DESC, i2.nombre LIMIT 1) AS sustituto
             FROM receta_ingrediente ri
             JOIN ingrediente i ON i.id = ri.ingrediente_id
             WHERE ri.receta_id = :receta
             ORDER BY ri.es_opcional, ri.peso DESC, i.nombre'
        );
        $consulta->execute([
            'usuario' => $usuarioId, 'usuario2' => $usuarioId, 'usuario3' => $usuarioId, 'usuario4' => $usuarioId,
            'receta' => $id,
        ]);
        $receta['ingredientes'] = $consulta->fetchAll();
        $consulta = $this->db->prepare(
            'SELECT COUNT(*) FROM favorito WHERE usuario_id = :usuario AND receta_id = :receta'
        );
        $consulta->execute(['usuario' => $usuarioId, 'receta' => $id]);
        $receta['favorita'] = (int) $consulta->fetchColumn() > 0;

        $consulta = $this->db->prepare(
            'SELECT id, numero, instruccion, segundos_timer FROM paso WHERE receta_id = :id ORDER BY numero'
        );
        $consulta->execute(['id' => $id]);
        $receta['pasos'] = $consulta->fetchAll();

        $consulta = $this->db->prepare(
            'SELECT e.id, e.nombre FROM receta_etiqueta re JOIN etiqueta e ON e.id = re.etiqueta_id
             WHERE re.receta_id = :id ORDER BY e.nombre'
        );
        $consulta->execute(['id' => $id]);
        $receta['etiquetas'] = $consulta->fetchAll();
        return $receta;
    }

    /**
     * Separa la columna "ingredientes" de buscar() y sugeridas().
     * @return list<array{nombre: string, tengo: bool}>
     */
    public static function ingredientesDeTarjeta(?string $columna): array
    {
        $lista = [];
        foreach (array_filter(explode("\n", (string) $columna)) as $renglon) {
            $lista[] = ['nombre' => substr($renglon, 1), 'tengo' => $renglon[0] === '1'];
        }
        return $lista;
    }

    /** @return list<array{id: int, nombre: string}> */
    public function etiquetas(): array
    {
        return $this->db->query('SELECT id, nombre FROM etiqueta ORDER BY nombre')->fetchAll();
    }

    /**
     * Alta (si $id es 0) o modificación de una receta completa, en una transacción:
     * si algo falla, no queda nada guardado a medias.
     *
     * @param array<string, mixed> $datos titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad
     * @param list<array{id: int, cantidad: ?float, unidad: string, peso?: int, opcional: bool}> $ingredientes
     * @param list<string> $pasos
     * @param list<int> $etiquetas
     */
    public function guardar(
        array $datos,
        array $ingredientes,
        array $pasos,
        array $etiquetas,
        int $autorId,
        int $id = 0
    ): int {
        $this->db->beginTransaction();
        try {
            $valores = [
                'titulo' => $datos['titulo'],
                'slug' => $this->slugUnico($datos['titulo'], $id),
                'descripcion' => $datos['descripcion'] !== '' ? $datos['descripcion'] : null,
                'porciones' => $datos['porciones_base'],
                'prep' => $datos['minutos_prep'],
                'coccion' => $datos['minutos_coccion'],
                'dificultad' => $datos['dificultad'],
            ];
            if ($id === 0) {
                $this->db->prepare(
                    'INSERT INTO receta (titulo, slug, descripcion, porciones_base, minutos_prep, minutos_coccion,
                                         dificultad, fuente, autor_id)
                     VALUES (:titulo, :slug, :descripcion, :porciones, :prep, :coccion, :dificultad, :fuente, :autor)'
                )->execute($valores + ['fuente' => 'Receta del equipo', 'autor' => $autorId]);
                $id = (int) $this->db->lastInsertId();
            } else {
                $this->db->prepare(
                    'UPDATE receta SET titulo = :titulo, slug = :slug, descripcion = :descripcion,
                            porciones_base = :porciones, minutos_prep = :prep, minutos_coccion = :coccion,
                            dificultad = :dificultad
                     WHERE id = :id'
                )->execute($valores + ['id' => $id]);
                // Lo más simple y seguro: borrar los ingredientes, pasos y etiquetas y volver a cargarlos.
                foreach (['receta_ingrediente', 'paso', 'receta_etiqueta'] as $tabla) {
                    $this->db->prepare("DELETE FROM {$tabla} WHERE receta_id = :id")->execute(['id' => $id]);
                }
            }

            $insertar = $this->db->prepare(
                'INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota)
                 VALUES (:receta, :ingrediente, :cantidad, :unidad, :peso, :opcional, :nota)'
            );
            foreach ($ingredientes as $ing) {
                $insertar->execute([
                    'receta' => $id,
                    'ingrediente' => $ing['id'],
                    'cantidad' => $ing['cantidad'],
                    'unidad' => $ing['unidad'] !== '' ? $ing['unidad'] : null,
                    'peso' => max(1, min(3, (int) ($ing['peso'] ?? 2))),
                    'opcional' => $ing['opcional'] ? 1 : 0,
                    'nota' => trim((string) ($ing['nota'] ?? '')) !== '' ? mb_substr(trim($ing['nota']), 0, 100) : null,
                ]);
            }
            $insertar = $this->db->prepare(
                'INSERT INTO paso (receta_id, numero, instruccion, segundos_timer)
                 VALUES (:receta, :numero, :texto, :segundos)'
            );
            // Cada paso puede ser un texto o ['texto' => ..., 'segundos' => ...] (con temporizador)
            foreach (array_values($pasos) as $i => $paso) {
                $insertar->execute([
                    'receta' => $id,
                    'numero' => $i + 1,
                    'texto' => is_array($paso) ? $paso['texto'] : $paso,
                    'segundos' => is_array($paso) && !empty($paso['segundos']) ? (int) $paso['segundos'] : null,
                ]);
            }
            $insertar = $this->db->prepare(
                'INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (:receta, :etiqueta)'
            );
            foreach (array_unique($etiquetas) as $etiqueta) {
                $insertar->execute(['receta' => $id, 'etiqueta' => $etiqueta]);
            }
            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Guarda (o borra, con null) la ruta de la foto de la receta. Devuelve la ruta anterior, para borrar el archivo. */
    public function cambiarImagen(int $id, ?string $ruta): ?string
    {
        $consulta = $this->db->prepare('SELECT imagen FROM receta WHERE id = :id');
        $consulta->execute(['id' => $id]);
        $anterior = $consulta->fetchColumn();
        $this->db->prepare('UPDATE receta SET imagen = :imagen WHERE id = :id')
            ->execute(['imagen' => $ruta, 'id' => $id]);
        return $anterior === false ? null : $anterior;
    }

    public function borrar(int $id): void
    {
        // receta_ingrediente, paso y receta_etiqueta se borran solos (ON DELETE CASCADE)
        $this->db->prepare('DELETE FROM receta WHERE id = :id')->execute(['id' => $id]);
    }

    /** "Arroz con pollo" → "arroz-con-pollo" (y "arroz-con-pollo-2" si ya existe). */
    private function slugUnico(string $titulo, int $exceptoId): string
    {
        $base = Texto::slug($titulo) ?: 'receta';
        $slug = $base;
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM receta WHERE slug = :slug AND id <> :id');
        for ($n = 2; $consulta->execute(['slug' => $slug, 'id' => $exceptoId]) && $consulta->fetchColumn() > 0; $n++) {
            $slug = $base . '-' . $n;
        }
        return $slug;
    }

    /**
     * Total de ingredientes obligatorios de cada receta y cuántos tiene el usuario.
     * Los básicos (sal, aceite, pimienta… y los que el usuario marcó como "siempre tengo") cuentan como disponibles.
     * "favorita": el usuario la marcó con ♥. "restringida": usa (sin ser opcional) algo que el usuario no puede comer.
     * Lo vencido o agotado (nivel 0) no cuenta.
     * "ingredientes" trae los obligatorios para dibujarlos en la tarjeta, uno por renglón y de
     * más a menos importante: un 1 o un 0 (si el usuario lo tiene) seguido del nombre.
     * Se separa con RecetaRepositorio::ingredientesDeTarjeta().
     */
    private const SELECT_RESUMEN = 'SELECT r.id, r.titulo, r.descripcion, r.dificultad, r.porciones_base,
            r.minutos_prep + r.minutos_coccion AS minutos,
            COUNT(CASE WHEN ri.es_opcional = 0 THEN 1 END) AS total,
            COUNT(CASE WHEN ri.es_opcional = 0
                        AND (d.ingrediente_id IS NOT NULL OR i.es_basico = 1 OR ub.ingrediente_id IS NOT NULL)
                       THEN 1 END) AS tengo,
            EXISTS (SELECT 1 FROM favorito f WHERE f.receta_id = r.id AND f.usuario_id = :usuarioF) AS favorita,
            EXISTS (SELECT 1 FROM receta_ingrediente ri4
                      JOIN restriccion_usuario ru
                        ON ru.ingrediente_id = ri4.ingrediente_id AND ru.usuario_id = :usuarioR
                     WHERE ri4.receta_id = r.id AND ri4.es_opcional = 0) AS restringida,
            (SELECT GROUP_CONCAT(e.nombre ORDER BY e.nombre SEPARATOR \', \')
               FROM receta_etiqueta re JOIN etiqueta e ON e.id = re.etiqueta_id
              WHERE re.receta_id = r.id) AS etiquetas,
            GROUP_CONCAT(CASE WHEN ri.es_opcional = 0 THEN CONCAT(
                    IF(d.ingrediente_id IS NOT NULL OR i.es_basico = 1 OR ub.ingrediente_id IS NOT NULL, \'1\', \'0\'),
                    i.nombre) END
                ORDER BY ri.peso DESC, i.nombre SEPARATOR \'\\n\') AS ingredientes
        FROM receta r
        LEFT JOIN receta_ingrediente ri ON ri.receta_id = r.id
        LEFT JOIN ingrediente i ON i.id = ri.ingrediente_id
        LEFT JOIN (SELECT DISTINCT ingrediente_id FROM despensa_item
                    WHERE usuario_id = :usuario AND nivel > 0
                      AND (fecha_vencimiento IS NULL OR fecha_vencimiento >= CURDATE())) d
               ON d.ingrediente_id = ri.ingrediente_id
        LEFT JOIN usuario_basico ub ON ub.usuario_id = :usuarioB AND ub.ingrediente_id = ri.ingrediente_id';
}
