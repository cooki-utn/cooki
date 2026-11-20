<?php

/**
 * Registro de usuarios generales. Los administradores se crean desde Administración → Usuarios.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Repositorios\UsuarioRepositorio;
use Cooki\Sesion;

if (Sesion::estaLogueado()) {
    redirigir('app/index.php');
}

$errores = [];
$nombre = '';
$email = '';

if (esPost()) {
    Sesion::verificarCsrf();
    $nombre = campo('nombre');
    $email = mb_strtolower(campo('email'));
    $contrasena = (string) ($_POST['contrasena'] ?? '');
    $repetir = (string) ($_POST['repetir'] ?? '');

    // Se valida en el servidor aunque el navegador ya lo haya hecho: el HTML se puede saltear.
    $errores = UsuarioRepositorio::validar($nombre, $email, $contrasena);
    if ($contrasena !== $repetir) {
        $errores['repetir'] = 'Las contraseñas no coinciden.';
    }
    $repo = new UsuarioRepositorio(Db::conexion());
    if (!isset($errores['email']) && $repo->existeEmail($email)) {
        $errores['email'] = 'Ya hay una cuenta con ese email.';
    }

    if ($errores === []) {
        $id = $repo->crear($nombre, $email, $contrasena, Sesion::ROL_GENERAL);
        // El color que eligió antes de tener cuenta (cookie) pasa a la cuenta nueva
        $color = Sesion::color();
        $repo->guardarPreferencia($id, 'color', $color);
        Sesion::ingresar([
            'id' => $id, 'nombre' => $nombre, 'email' => $email, 'rol' => Sesion::ROL_GENERAL, 'color' => $color,
        ]);
        // Primero, "Contanos cómo comés" (se puede saltear con "Lo hago después")
        redirigir('app/bienvenida.php?nueva=1');
    }
}

$titulo = 'Crear cuenta';
require parcial('encabezado');
?>
<div class="con-stickers">
<?php
$stickers = ['banana', 'miel', 'cebolla', 'morron', 'pollo', 'brocoli', 'uva', 'pan', 'pera', 'sandia'];
$zona = 'costados';
require parcial('stickers');
?>
<section class="tarjeta tarjeta--angosta">
  <h1>Crear cuenta</h1>
  <p class="texto-suave">¿Ya tenés cuenta? <a href="<?= e(url('login.php')) ?>">Ingresá</a>.</p>

  <form class="formulario" method="post" id="form-registro" novalidate>
    <?= Sesion::campoCsrf() ?>
    <div class="campo">
      <label for="nombre">Nombre</label>
      <input id="nombre" name="nombre" type="text" required minlength="2" maxlength="80" autocomplete="name" value="<?= e($nombre) ?>">
      <?php if (isset($errores['nombre'])) : ?><p class="error-campo"><?= e($errores['nombre']) ?></p><?php endif; ?>
    </div>
    <div class="campo">
      <label for="email">Email</label>
      <input id="email" name="email" type="email" required maxlength="190" autocomplete="email" value="<?= e($email) ?>">
      <?php if (isset($errores['email'])) : ?><p class="error-campo"><?= e($errores['email']) ?></p><?php endif; ?>
    </div>
    <div class="campo">
      <label for="contrasena">Contraseña</label>
      <input id="contrasena" name="contrasena" type="password" required minlength="8" autocomplete="new-password" aria-describedby="ayuda-contrasena">
      <small id="ayuda-contrasena">Al menos 8 caracteres, con letras y números.</small>
      <?php if (isset($errores['contrasena'])) : ?><p class="error-campo"><?= e($errores['contrasena']) ?></p><?php endif; ?>
    </div>
    <div class="campo">
      <label for="repetir">Repetí la contraseña</label>
      <input id="repetir" name="repetir" type="password" required autocomplete="new-password">
      <?php if (isset($errores['repetir'])) : ?><p class="error-campo"><?= e($errores['repetir']) ?></p><?php endif; ?>
    </div>
    <button class="boton" type="submit">Crear cuenta</button>
  </form>
</section>
<div class="stickers-abajo">
<?php
$stickers = ['durazno', 'yogur', 'choclo', 'cereza', 'anana'];
$zona = 'abajo';
require parcial('stickers');
?>
</div>
</div>
<script src="<?= e(recurso('js/validacion.js')) ?>" defer></script>
<?php require parcial('pie'); ?>
