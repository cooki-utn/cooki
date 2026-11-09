<?php

declare(strict_types=1);

namespace Cooki\Repositorios;

use PDO;

/** Acceso a la tabla usuario. Las contraseñas se guardan siempre con password_hash(). */
final class UsuarioRepositorio
{
    /** Colores de la puerta de la heladera que puede elegir cada usuario (clave => nombre). */
    public const COLORES = [
        'verde' => 'Verde agua',
        'crema' => 'Crema',
        'celeste' => 'Celeste',
        'rosa' => 'Rosa',
        'noche' => 'Noche (oscuro)',
    ];

    /** Disposiciones de la pantalla (en la computadora). */
    public const DISPOSICIONES = [
        'normal' => 'Normal',
        'panoramica' => 'Panorámica',
    ];

    /**
     * La nota de la lista de compras en Mi cocina y Mi despensa: entera, cerrada (un recuadro chico para
     * desplegarla) o nunca (se apaga en Mis preferencias).
     */
    public const NOTAS_LISTA = [
        'abierta' => 'Abierta',
        'cerrada' => 'Cerrada',
        'nunca' => 'No mostrar',
    ];

    public function __construct(private PDO $db)
    {
    }

    /** @return array<string, mixed>|null */
    public function buscarPorEmail(string $email): ?array
    {
        $consulta = $this->db->prepare('SELECT * FROM usuario WHERE email = :email');
        $consulta->execute(['email' => $email]);
        return $consulta->fetch() ?: null;
    }

    /** @return array<string, mixed>|null */
    public function buscarPorId(int $id): ?array
    {
        $consulta = $this->db->prepare('SELECT id, nombre, email, rol, creado_en FROM usuario WHERE id = :id');
        $consulta->execute(['id' => $id]);
        return $consulta->fetch() ?: null;
    }

    public function existeEmail(string $email, int $exceptoId = 0): bool
    {
        $consulta = $this->db->prepare('SELECT COUNT(*) FROM usuario WHERE email = :email AND id <> :id');
        $consulta->execute(['email' => $email, 'id' => $exceptoId]);
        return (int) $consulta->fetchColumn() > 0;
    }

    public function crear(string $nombre, string $email, string $contrasena, string $rol = 'general'): int
    {
        $consulta = $this->db->prepare(
            'INSERT INTO usuario (nombre, email, hash_contrasena, rol) VALUES (:nombre, :email, :hash, :rol)'
        );
        $consulta->execute([
            'nombre' => $nombre,
            'email' => $email,
            'hash' => password_hash($contrasena, PASSWORD_DEFAULT),
            'rol' => $rol,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * Devuelve los datos del usuario si el email y la contraseña son correctos; si no, null.
     * @return array<string, mixed>|null
     */
    public function verificarCredenciales(string $email, string $contrasena): ?array
    {
        $usuario = $this->buscarPorEmail($email);
        if ($usuario === null || !password_verify($contrasena, $usuario['hash_contrasena'])) {
            return null;
        }
        return $usuario;
    }

    /** @return list<array<string, mixed>> */
    public function listar(string $texto = ''): array
    {
        $consulta = $this->db->prepare(
            'SELECT id, nombre, email, rol, creado_en FROM usuario
             WHERE nombre LIKE :texto1 OR email LIKE :texto2
             ORDER BY rol, nombre'
        );
        $consulta->execute(['texto1' => "%{$texto}%", 'texto2' => "%{$texto}%"]);
        return $consulta->fetchAll();
    }

    /** Si $contrasena viene vacía, se mantiene la actual. */
    public function actualizar(int $id, string $nombre, string $email, string $rol, string $contrasena = ''): void
    {
        $this->db->prepare('UPDATE usuario SET nombre = :nombre, email = :email, rol = :rol WHERE id = :id')
            ->execute(['nombre' => $nombre, 'email' => $email, 'rol' => $rol, 'id' => $id]);
        if ($contrasena !== '') {
            $this->db->prepare('UPDATE usuario SET hash_contrasena = :hash WHERE id = :id')
                ->execute(['hash' => password_hash($contrasena, PASSWORD_DEFAULT), 'id' => $id]);
        }
    }

    /**
     * Guarda una preferencia de pantalla: 'color' (uno de COLORES), 'disposicion' (una de DISPOSICIONES),
     * 'nota_lista' (una de NOTAS_LISTA) o 'volumen' (un entero de 0 a 100).
     * Devuelve false si la preferencia o el valor no son válidos.
     */
    public function guardarPreferencia(int $id, string $clave, string $valor): bool
    {
        if ($clave === 'volumen') {
            $numero = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
            if ($numero === false) {
                return false;
            }
            $valor = (string) $numero;
        } else {
            $validos = [
                'color' => self::COLORES, 'disposicion' => self::DISPOSICIONES, 'nota_lista' => self::NOTAS_LISTA,
            ][$clave] ?? [];
            if (!isset($validos[$valor])) {
                return false;
            }
        }
        // $clave sale de una lista fija (color, disposicion, nota_lista o volumen), nunca del usuario
        $this->db->prepare("UPDATE usuario SET {$clave} = :valor WHERE id = :id")
            ->execute(['valor' => $valor, 'id' => $id]);
        return true;
    }

    /** Perfil (incremento 2 · P1): el usuario cambia su nombre y su email. */
    public function actualizarDatos(int $id, string $nombre, string $email): void
    {
        $this->db->prepare('UPDATE usuario SET nombre = :nombre, email = :email WHERE id = :id')
            ->execute(['nombre' => $nombre, 'email' => $email, 'id' => $id]);
    }

    public function cambiarContrasena(int $id, string $nueva): void
    {
        $this->db->prepare('UPDATE usuario SET hash_contrasena = :hash WHERE id = :id')
            ->execute(['hash' => password_hash($nueva, PASSWORD_DEFAULT), 'id' => $id]);
    }

    /** ¿Es la contraseña actual de ese usuario? (para cambiarla o dar de baja la cuenta) */
    public function contrasenaCorrecta(int $id, string $contrasena): bool
    {
        $consulta = $this->db->prepare('SELECT hash_contrasena FROM usuario WHERE id = :id');
        $consulta->execute(['id' => $id]);
        $hash = $consulta->fetchColumn();
        return is_string($hash) && password_verify($contrasena, $hash);
    }

    public function borrar(int $id): void
    {
        $this->db->prepare('DELETE FROM usuario WHERE id = :id')->execute(['id' => $id]);
    }

    public function contarAdministradores(): int
    {
        return (int) $this->db->query("SELECT COUNT(*) FROM usuario WHERE rol = 'admin'")->fetchColumn();
    }

    /**
     * Reglas para nombre, email y contraseña. Devuelve los errores por campo (vacío = todo bien).
     * Se usa en el registro y en el ABM de usuarios.
     * @return array<string, string>
     */
    public static function validar(
        string $nombre,
        string $email,
        string $contrasena,
        bool $contrasenaObligatoria = true
    ): array {
        $errores = [];
        if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) {
            $errores['nombre'] = 'El nombre debe tener entre 2 y 80 caracteres.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores['email'] = 'El email no es válido.';
        }
        if (($contrasena !== '' || $contrasenaObligatoria) && ($error = self::errorContrasena($contrasena)) !== null) {
            $errores['contrasena'] = $error;
        }
        return $errores;
    }

    /** La regla de la contraseña sola (para el registro, Mi cuenta y la recuperación). null = está bien. */
    public static function errorContrasena(string $contrasena): ?string
    {
        if (strlen($contrasena) < 8 || !preg_match('/[A-Za-z]/', $contrasena) || !preg_match('/\d/', $contrasena)) {
            return 'La contraseña debe tener al menos 8 caracteres, con letras y números.';
        }
        return null;
    }
}
