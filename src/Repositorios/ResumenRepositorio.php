<?php

declare(strict_types=1);

namespace Cooki\Repositorios;

use PDO;

/** Números para el panel de administración. */
final class ResumenRepositorio
{
    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, int> */
    public function totales(): array
    {
        $sql = "SELECT
                  (SELECT COUNT(*) FROM usuario WHERE rol = 'general') AS usuarios,
                  (SELECT COUNT(*) FROM usuario WHERE rol = 'admin') AS administradores,
                  (SELECT COUNT(*) FROM receta) AS recetas,
                  (SELECT COUNT(*) FROM ingrediente) AS ingredientes,
                  (SELECT COUNT(*) FROM ingrediente WHERE usuario_id IS NOT NULL) AS ingredientes_propios,
                  (SELECT COUNT(*) FROM contacto WHERE estado = 'nueva') AS consultas_nuevas";
        return array_map('intval', $this->db->query($sql)->fetch());
    }
}
