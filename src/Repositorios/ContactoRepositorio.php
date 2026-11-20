<?php

declare(strict_types=1);

namespace Cooki\Repositorios;

use PDO;

/** Consultas que llegan por el formulario de contacto. */
final class ContactoRepositorio
{
    public const ESTADOS = ['nueva' => 'Nueva', 'respondida' => 'Respondida'];

    public function __construct(private PDO $db)
    {
    }

    /** Asunto de lo que se manda desde "¿Falta algún tipo?" en Recetas, y cuántas se pueden mandar por día. */
    public const ASUNTO_TIPO = 'Sugerencia de tipo';
    public const TIPOS_POR_DIA = 5;

    /** Cuántas consultas con ese asunto mandó hoy el usuario (para el tope de sugerencias). */
    public function contarDeHoy(int $usuarioId, string $asunto): int
    {
        $consulta = $this->db->prepare(
            'SELECT COUNT(*) FROM contacto WHERE usuario_id = :usuario AND asunto = :asunto AND creado_en >= CURDATE()'
        );
        $consulta->execute(['usuario' => $usuarioId, 'asunto' => $asunto]);
        return (int) $consulta->fetchColumn();
    }

    /** @param array{nombre: string, email: string, asunto: string, mensaje: string} $datos */
    public function crear(array $datos, ?int $usuarioId): int
    {
        $this->db->prepare(
            'INSERT INTO contacto (usuario_id, nombre, email, asunto, mensaje)
             VALUES (:usuario, :nombre, :email, :asunto, :mensaje)'
        )->execute($datos + ['usuario' => $usuarioId]);
        return (int) $this->db->lastInsertId();
    }

    public function marcarCorreoEnviado(int $id): void
    {
        $this->db->prepare('UPDATE contacto SET correo_enviado = TRUE WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Listado con buscador (nombre, email, asunto o mensaje) y filtro por estado.
     * @return list<array<string, mixed>>
     */
    public function listar(string $texto = '', string $estado = ''): array
    {
        $consulta = $this->db->prepare(
            'SELECT c.*, u.nombre AS usuario, r.nombre AS respondio
             FROM contacto c LEFT JOIN usuario u ON u.id = c.usuario_id LEFT JOIN usuario r ON r.id = c.respondida_por
             WHERE (c.nombre LIKE :t1 OR c.email LIKE :t2 OR c.asunto LIKE :t3 OR c.mensaje LIKE :t4)
               AND (:estado1 = \'\' OR c.estado = :estado2)
             ORDER BY c.estado = \'respondida\', c.creado_en DESC'
        );
        $patron = "%{$texto}%";
        $consulta->execute([
            't1' => $patron, 't2' => $patron, 't3' => $patron, 't4' => $patron,
            'estado1' => $estado, 'estado2' => $estado,
        ]);
        return $consulta->fetchAll();
    }

    /** @return array<string, mixed>|null */
    public function obtener(int $id): ?array
    {
        $consulta = $this->db->prepare('SELECT * FROM contacto WHERE id = :id');
        $consulta->execute(['id' => $id]);
        return $consulta->fetch() ?: null;
    }

    /**
     * Guarda la respuesta del administrador y deja la consulta como respondida.
     * $enviada = si el correo con la respuesta se pudo mandar.
     */
    public function responder(int $id, string $respuesta, int $adminId, bool $enviada): bool
    {
        $consulta = $this->db->prepare(
            'UPDATE contacto SET respuesta = :respuesta, respondida_en = NOW(), respondida_por = :admin,
                    respuesta_enviada = :enviada, estado = \'respondida\'
             WHERE id = :id'
        );
        $consulta->execute([
            'respuesta' => $respuesta, 'admin' => $adminId, 'enviada' => $enviada ? 1 : 0, 'id' => $id,
        ]);
        return $consulta->rowCount() > 0;
    }

    /**
     * Las consultas que mandó un usuario con cuenta, con la respuesta si ya la tienen ("Mis consultas").
     * @return list<array<string, mixed>>
     */
    public function delUsuario(int $usuarioId): array
    {
        $consulta = $this->db->prepare(
            'SELECT id, asunto, mensaje, creado_en, respuesta, respondida_en
             FROM contacto WHERE usuario_id = :usuario ORDER BY creado_en DESC'
        );
        $consulta->execute(['usuario' => $usuarioId]);
        return $consulta->fetchAll();
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $consulta = $this->db->prepare('UPDATE contacto SET estado = :estado WHERE id = :id');
        $consulta->execute(['estado' => $estado, 'id' => $id]);
        return $consulta->rowCount() > 0;
    }

    public function borrar(int $id): bool
    {
        $consulta = $this->db->prepare('DELETE FROM contacto WHERE id = :id');
        $consulta->execute(['id' => $id]);
        return $consulta->rowCount() > 0;
    }
}
