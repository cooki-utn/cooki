<?php

declare(strict_types=1);

namespace Cooki;

use Cooki\Repositorios\UsuarioRepositorio;

/**
 * Todo lo relacionado con la sesión del usuario: quién está logueado, qué rol tiene,
 * protección de páginas, mensajes de aviso y el token contra envíos falsos (CSRF).
 *
 * Los datos del usuario logueado se guardan en la variable de sesión $_SESSION['usuario'].
 */
final class Sesion
{
    public const ROL_GENERAL = 'general';
    public const ROL_ADMIN = 'admin';
    /** Cookie con el color de la heladera de quien no tiene cuenta (pasa a la cuenta al registrarse). */
    public const COOKIE_COLOR = 'cooki_color';

    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('cooki');
        session_set_cookie_params([
            'httponly' => true,      // JavaScript no puede leer la cookie de sesión
            'samesite' => 'Lax',     // otro sitio no puede enviar formularios con nuestra sesión
            'secure' => esHttps(),
        ]);
        session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
    }

    /** @return array{id: int, nombre: string, email: string, rol: string, color?: string, disposicion?: string, volumen?: int}|null */
    public static function usuario(): ?array
    {
        self::iniciar();
        return $_SESSION['usuario'] ?? null;
    }

    public static function estaLogueado(): bool
    {
        return self::usuario() !== null;
    }

    public static function esAdmin(): bool
    {
        return (self::usuario()['rol'] ?? '') === self::ROL_ADMIN;
    }

    public static function idUsuario(): int
    {
        return (int) (self::usuario()['id'] ?? 0);
    }

    /**
     * Guarda en la sesión al usuario que acaba de ingresar.
     * @param array<string, mixed> $usuario fila de la tabla usuario
     */
    public static function ingresar(array $usuario): void
    {
        self::iniciar();
        session_regenerate_id(true);   // nuevo identificador de sesión: evita el robo de sesión
        $_SESSION['usuario'] = [
            'id' => (int) $usuario['id'],
            'nombre' => (string) $usuario['nombre'],
            'email' => (string) $usuario['email'],
            'rol' => (string) $usuario['rol'],
            'color' => (string) ($usuario['color'] ?? 'verde'),
            'disposicion' => (string) ($usuario['disposicion'] ?? 'normal'),
            'volumen' => (int) ($usuario['volumen'] ?? 50),
            'nota_lista' => (string) ($usuario['nota_lista'] ?? 'abierta'),
        ];
    }

    /** Volumen de los sonidos del usuario logueado (0 a 100; 50 si no hay nadie o si no eligió). */
    public static function volumen(): int
    {
        return (int) (self::usuario()['volumen'] ?? 50);
    }

    /** La nota de la lista de compras del usuario logueado: 'abierta', 'cerrada' o 'nunca'. */
    public static function notaLista(): string
    {
        return (string) (self::usuario()['nota_lista'] ?? 'abierta');
    }

    /** Disposición de la pantalla del usuario logueado: 'normal' o 'panoramica'. */
    public static function disposicion(): string
    {
        return (string) (self::usuario()['disposicion'] ?? 'normal');
    }

    /**
     * Color de la heladera: el de la cuenta; sin cuenta, el de la cookie (lo elige en "Ajustes").
     * 'verde' si no eligió ninguno.
     */
    public static function color(): string
    {
        $usuario = self::usuario();
        if ($usuario !== null) {
            return (string) ($usuario['color'] ?? 'verde');
        }
        $color = (string) ($_COOKIE[self::COOKIE_COLOR] ?? '');
        return isset(UsuarioRepositorio::COLORES[$color]) ? $color : 'verde';
    }

    /** Guarda en una cookie (un año) el color elegido por alguien sin cuenta. Devuelve false si no es válido. */
    public static function recordarColor(string $color): bool
    {
        if (!isset(UsuarioRepositorio::COLORES[$color])) {
            return false;
        }
        if (!headers_sent()) {
            setcookie(self::COOKIE_COLOR, $color, [
                'expires' => time() + 365 * 24 * 3600,
                'path' => '/',
                'samesite' => 'Lax',
                'secure' => esHttps(),
            ]);
        }
        $_COOKIE[self::COOKIE_COLOR] = $color;
        return true;
    }

    /**
     * Actualiza una preferencia guardada en la sesión ('color', 'disposicion', 'nota_lista' o 'volumen').
     * La base la actualiza UsuarioRepositorio::guardarPreferencia().
     */
    public static function cambiarPreferencia(string $clave, string $valor): void
    {
        self::iniciar();
        if (isset($_SESSION['usuario'])) {
            $_SESSION['usuario'][$clave] = $clave === 'volumen' ? (int) $valor : $valor;
        }
    }

    /** Después de editar el perfil: el menú de la cuenta muestra el nombre y el email nuevos. */
    public static function actualizarDatos(string $nombre, string $email): void
    {
        self::iniciar();
        if (isset($_SESSION['usuario'])) {
            $_SESSION['usuario']['nombre'] = $nombre;
            $_SESSION['usuario']['email'] = $email;
        }
    }

    public static function salir(): void
    {
        self::iniciar();
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    /** Si no hay nadie logueado, manda a la página de error. Va al principio de cada página privada. */
    public static function requiereLogin(): void
    {
        if (!self::estaLogueado()) {
            redirigir('error.php?motivo=sin-sesion');
        }
    }

    /** Solo deja pasar a los administradores. */
    public static function requiereAdmin(): void
    {
        self::requiereLogin();
        if (!self::esAdmin()) {
            redirigir('error.php?motivo=sin-permiso');
        }
    }

    /** Campo oculto que va dentro de cada <form method="post">. */
    public static function campoCsrf(): string
    {
        self::iniciar();
        return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
    }

    /** Se llama al procesar cada POST: si el token no coincide, el envío no vino de nuestro sitio. */
    public static function verificarCsrf(): void
    {
        self::iniciar();
        if (!hash_equals((string) $_SESSION['csrf'], (string) ($_POST['csrf'] ?? ''))) {
            redirigir('error.php?motivo=formulario-vencido');
        }
    }

    /** Guarda un mensaje para mostrar en la próxima página. Tipos: exito, error, info. */
    public static function avisar(string $tipo, string $mensaje): void
    {
        self::iniciar();
        $_SESSION['avisos'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    /** @return list<array{tipo: string, mensaje: string}> Devuelve los avisos pendientes y los borra. */
    public static function avisos(): array
    {
        self::iniciar();
        $avisos = $_SESSION['avisos'] ?? [];
        unset($_SESSION['avisos']);
        return $avisos;
    }
}
