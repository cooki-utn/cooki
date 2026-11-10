<?php

/**
 * Probar el correo (solo administradores): muestra la configuración del .env (nunca la contraseña) y manda un
 * correo de prueba a la cuenta de quien lo pide. Sirve para comprobar Gmail en el hosting sin pasar por
 * "Olvidé mi contraseña": si falla, muestra la respuesta del servidor de correo.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Config;
use Cooki\Correo;
use Cooki\Sesion;

Sesion::requiereAdmin();

$destino = (string) (Sesion::usuario()['email'] ?? '');

if (esPost()) {
    Sesion::verificarCsrf();
    $error = Correo::intentar(
        $destino,
        'Correo de prueba de Cooki',
        "Hola:\n\nSi leés esto, el correo de Cooki funciona.\n\n"
            . 'Enviado el ' . date('d/m/Y H:i') . ' desde ' . (Config::get('APP_URL') ?: 'el sitio') . ".\n\n— Cooki"
    );
    if ($error === null) {
        Sesion::avisar('exito', 'Listo: se mandó el correo de prueba a ' . $destino . '. Revisá la bandeja de entrada (y la de spam).');
    } else {
        $pista = str_contains($error, '535')
            ? ' Revisá MAIL_USUARIO y MAIL_CONTRASENA: en Gmail va la contraseña de aplicación de 16 letras, no la de la cuenta.'
            : '';
        Sesion::avisar('error', 'No se pudo mandar: ' . $error . $pista);
    }
    redirigir('admin/correo.php');
}

// Con prefijo "correo": el encabezado común define $usuario (la sesión) y pisaría una variable con ese nombre
$correoHost = Config::get('MAIL_HOST');
$correoSeguridad = Config::get('MAIL_SEGURIDAD', 'ninguna');
$correoUsuario = Config::get('MAIL_USUARIO');
$correoRemitente = Config::get('MAIL_REMITENTE', 'no-responder@cooki.test');
// Gmail cambia el remitente por la cuenta que inicia sesión: conviene que sean la misma dirección
$avisoRemitente = str_contains($correoHost, 'gmail.com') && $correoUsuario !== ''
    && strcasecmp($correoUsuario, $correoRemitente) !== 0;

$titulo = 'Correo · Administración';
require parcial('encabezado');
?>
<?= enlaceVolver('admin/index.php', 'Volver al panel', [['admin/index.php', 'Panel']]) ?>
<h1>Probar el correo</h1>

<section class="tarjeta" aria-labelledby="titulo-config">
  <h2 id="titulo-config">Configuración actual</h2>
  <dl class="datos-correo">
    <dt>Servidor</dt>
    <dd><?= $correoHost !== '' ? e($correoHost . ':' . Config::get('MAIL_PORT')) : 'Sin MAIL_HOST: se usa mail() de PHP' ?></dd>
    <dt>Seguridad</dt>
    <dd><?= e($correoSeguridad) ?></dd>
    <dt>Usuario</dt>
    <dd><?= $correoUsuario !== '' ? e($correoUsuario) : 'sin usuario' ?></dd>
    <dt>Contraseña</dt>
    <dd><?= Config::get('MAIL_CONTRASENA') !== '' ? 'cargada (no se muestra)' : 'vacía' ?></dd>
    <dt>Remitente</dt>
    <dd><?= e($correoRemitente) ?></dd>
    <dt>Consultas de contacto a</dt>
    <dd><?= e(Config::get('MAIL_CONTACTO')) ?></dd>
  </dl>
  <?php if ($avisoRemitente) : ?>
    <p class="aviso aviso--info">Gmail manda los correos a nombre de <strong><?= e($correoUsuario) ?></strong>. Poné esa misma dirección en MAIL_REMITENTE.</p>
  <?php endif; ?>
  <p class="texto-suave"><small>Se cambia en el <code>.env</code> del servidor. La guía está en <code>docs/publicar.md</code>.</small></p>
</section>

<section class="tarjeta" aria-labelledby="titulo-prueba">
  <h2 id="titulo-prueba">Mandar un correo de prueba</h2>
  <p>Se manda a tu cuenta: <strong><?= e($destino) ?></strong>.</p>
  <form method="post">
    <?= Sesion::campoCsrf() ?>
    <button class="boton" type="submit">Mandarme un correo de prueba</button>
  </form>
</section>
<?php require parcial('pie'); ?>
