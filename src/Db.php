<?php

declare(strict_types=1);

namespace Cooki;

use PDO;

/**
 * Conexión única a MariaDB/MySQL con PDO.
 * Siempre usar consultas preparadas: nunca concatenar datos del usuario en el SQL.
 */
final class Db
{
    private static ?PDO $pdo = null;

    public static function conexion(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Config::get('DB_HOST', '127.0.0.1'),
                Config::get('DB_PORT', '3306'),
                Config::get('DB_NAME', 'cooki')
            );
            self::$pdo = new PDO($dsn, Config::get('DB_USER', 'root'), Config::get('DB_PASS'), [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            // MySQL usa la misma hora que PHP (Argentina, en bootstrap.php). Si no, en un servidor en UTC,
            // entre las 21 y las 24 CURDATE() ya es mañana: "vence hoy", "hace poco" y los límites del día se corren.
            // Se manda como desfase (-03:00) porque los servidores compartidos no siempre tienen los nombres de zonas.
            self::$pdo->exec("SET time_zone = '" . (new \DateTimeImmutable())->format('P') . "'");
        }
        return self::$pdo;
    }
}
