<?php

/**
 * Quiénes somos (pública): el equipo, qué hizo cada uno y sus enlaces.
 * Se llega con el imán "Nosotros" (solo sin cuenta) o desde "¿Por qué Cooki?" en el inicio.
 * Los datos de cada integrante se cargan en src/equipo.php.
 */

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Cooki\Config;

/** @var list<array<string, string>> $equipo */
$equipo = require dirname(__DIR__) . '/src/equipo.php';
$correoEquipo = Config::get('MAIL_CONTACTO', 'contacto@cooki.test');

// Solo enlaces http(s) y correos con formato válido: un dato mal cargado no se muestra
$enlaceWeb = fn (mixed $u): string => is_string($u) && preg_match('#^https?://#i', $u) ? $u : '';
$enlaceCorreo = fn (mixed $c): string => is_string($c) && filter_var($c, FILTER_VALIDATE_EMAIL) ? $c : '';

// Íconos de las aplicaciones (GitHub, LinkedIn y correo), en blanco sobre un imán de su color
$iconos = [
    'github' => '<path d="' . 'M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04'
        . '-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.08'
        . '4-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.60'
        . '5-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005'
        . '-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.64'
        . '5 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81'
        . ' 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.'
        . '373-12-12-12'
        . '"/>',
    'linkedin' => '<path d="' . 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.'
        . '351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.43'
        . '3a2.062 2.062 0 1 1 0-4.125 2.062 2.062 0 0 1 0 4.125zM7.119 20.452H3.555V9h3.564z'
        . '"/>',
    'correo' => '<path d="' . 'M3 5h18a2 2 0 0 1 2 2v.4l-11 7-11-7V7a2 2 0 0 1 2-2zm-2 4.8 11 7 11-7V17a2 2 0 0 1-2 2H3a2 2 0 0 1-2'
        . '-2z'
        . '"/>',
];
$imanEnlace = function (string $tipo, string $href, string $texto) use ($iconos): string {
    $externo = $tipo === 'correo' ? '' : ' target="_blank" rel="noopener"';
    return '<a class="iman-enlace iman-enlace--' . $tipo . '" href="' . e($href) . '"' . $externo
        . ' title="' . e($texto) . '"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"'
        . ' fill="currentColor">' . $iconos[$tipo] . '</svg><span class="solo-lectores">' . e($texto) . '</span></a>';
};

// A los costados de la nota de contacto: el mate y el café, grandes y meciéndose (solo en pantallas anchas)
$adornos = ['yerba', 'cafe'];

$titulo = 'Quiénes somos';
require parcial('encabezado');
?>
<h1 class="titulo-con-dibujo">Quiénes somos
  <img class="titulo-con-dibujo__img" src="<?= e(url('img/ingredientes/fideos.svg')) ?>" alt="" width="64" height="64">
</h1>
<p class="nosotros__bajada">Somos estudiantes de la Tecnicatura Universitaria en Programación de la UTN Facultad Regional Haedo.
  Cooki es nuestro proyecto integrador: cada uno se ocupó de una parte.</p>

<div class="nosotros">
  <?php foreach ($equipo as $i => $persona) : ?>
    <?php
    $github = $enlaceWeb($persona['github'] ?? '');
    $linkedin = $enlaceWeb($persona['linkedin'] ?? '');
    $correo = $enlaceCorreo($persona['correo'] ?? '');
    $quien = trim((string) ($persona['nombre'] ?? ''));
    $imagen = preg_replace('/[^a-z0-9-]/', '', (string) ($persona['imagen'] ?? ''));
    ?>
    <article class="tarjeta nosotros__persona" style="--giro: <?= [-.6, .5, -.3, .7, -.5][$i % 5] ?>deg">
      <div class="nosotros__foto" aria-hidden="true">
        <?php if ($imagen !== '' && is_file(__DIR__ . '/img/equipo/' . $imagen . '.svg')) : ?>
          <img src="<?= e(url('img/equipo/' . $imagen . '.svg')) ?>" alt="" width="84" height="84">
        <?php else : ?>
          <img src="<?= e(url('img/ingredientes/frasco.svg')) ?>" alt="" width="84" height="84">
        <?php endif; ?>
      </div>
      <div class="nosotros__datos">
        <h2 class="nosotros__nombre"><?= $quien !== '' ? e($quien) : '<span class="texto-suave">Nombre a confirmar</span>' ?></h2>
        <p><strong><?= e($persona['area']) ?>.</strong> <?= e($persona['descripcion']) ?></p>
        <?php if ($github !== '' || $linkedin !== '' || $correo !== '') : ?>
          <p class="nosotros__enlaces">
            <?php $de = $quien !== '' ? ' de ' . $quien : ''; ?>
            <?= $github !== '' ? $imanEnlace('github', $github, 'GitHub' . $de . ' (se abre en otra pestaña)') : '' ?>
            <?= $linkedin !== '' ? $imanEnlace('linkedin', $linkedin, 'LinkedIn' . $de . ' (se abre en otra pestaña)') : '' ?>
            <?= $correo !== '' ? $imanEnlace('correo', 'mailto:' . $correo, 'Correo' . $de) : '' ?>
          </p>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<div class="nosotros__fila-contacto">
<img class="nosotros__adorno" src="<?= e(url('img/ingredientes/' . $adornos[0] . '.svg')) ?>" alt="" width="150" height="150">
<aside class="postit nosotros__contacto" aria-label="Contactar al equipo">
  <span class="postit__chinche" aria-hidden="true"></span>
  <p class="nosotros__pregunta">¿Querés contactarte con el equipo o proponer ideas?</p>
  <p class="acciones">
    <a class="boton" href="<?= e(url('contacto.php')) ?>">Ir a Contacto</a>
    <a class="boton boton--secundario" href="mailto:<?= e($correoEquipo) ?>">Enviar un correo</a>
  </p>
</aside>
<img class="nosotros__adorno nosotros__adorno--derecha" src="<?= e(url('img/ingredientes/' . $adornos[1] . '.svg')) ?>" alt="" width="150" height="150">
</div>
<?= enlaceVolver('index.php', 'Volver al inicio') ?>
<?php require parcial('pie'); ?>
