<?php

/**
 * Panel de administración: resumen y accesos a cada ABM. Solo administradores.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Config;
use Cooki\Db;
use Cooki\Repositorios\ResumenRepositorio;
use Cooki\Sesion;

Sesion::requiereAdmin();

$totales = (new ResumenRepositorio(Db::conexion()))->totales();

// [página, nombre, dato, descripción, dibujo, color de la nota]
$secciones = [
    ['recetas.php', 'Recetas', $totales['recetas'] . ' cargadas', 'Alta, baja y modificación del recetario.', 'fideos', 'amarillo'],
    ['ingredientes.php', 'Ingredientes', $totales['ingredientes'] . ' en el catálogo',
        'Los ingredientes que se pueden usar en recetas y despensas.', 'zanahoria', 'rosa'],
    ['consultas.php', 'Consultas', $totales['consultas_nuevas'] . ' sin responder',
        'Mensajes que llegaron desde el formulario de contacto.', 'yerba', 'celeste'],
    ['usuarios.php', 'Usuarios', $totales['usuarios'] . ' generales · ' . $totales['administradores'] . ' administradores',
        'Cuentas, roles y contraseñas.', 'manzana', 'verde'],
    ['correo.php', 'Correo', Config::get('MAIL_HOST') !== '' ? Config::get('MAIL_HOST') : 'sin servidor configurado',
        'Ver la configuración y mandar un correo de prueba.', 'limon', 'amarillo'],
];

$titulo = 'Administración';
require parcial('encabezado');
?>
<h1>Administración</h1>
<?php /* Cada sección es una nota de color con su dibujo; toda la nota es el enlace (se levanta y se hamaca) */ ?>
<div class="notas-panel">
  <?php foreach ($secciones as [$ruta, $nombre, $dato, $descripcion, $dibujo, $color]) : ?>
    <article class="postit postit--<?= e($color) ?> nota-panel">
      <span class="postit__chinche" aria-hidden="true"></span>
      <img class="nota-panel__dibujo" src="<?= e(url('img/ingredientes/' . $dibujo . '.svg')) ?>" alt="" width="72" height="72">
      <h2><a href="<?= e(url('admin/' . $ruta)) ?>"><?= e($nombre) ?></a></h2>
      <p class="nota-panel__dato"><?= e($dato) ?></p>
      <p><?= e($descripcion) ?></p>
      <span class="nota-panel__ir" aria-hidden="true">Gestionar →</span>
    </article>
  <?php endforeach; ?>
</div>
<?php require parcial('pie'); ?>
