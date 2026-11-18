<?php

/**
 * Mis recetas de IA (incremento 3 · HU-30): las que la IA propuso y la persona guardó.
 * Las que no se guardan se borran solas a los 7 días.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Ia\Ia;
use Cooki\Repositorios\IaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();

$guardadasIa = (new IaRepositorio(Db::conexion()))->guardadas(Sesion::idUsuario());

$titulo = 'Mis recetas de IA';
require parcial('encabezado');
?>
<?= enlaceVolver('app/index.php', 'Volver a Mi cocina', [['app/recetas.php', 'Recetas']]) ?>
<h1>Mis recetas de IA</h1>
<p class="texto-suave">Las recetas que te propuso la IA y guardaste. Son sugerencias: revisalas antes de cocinar.</p>

<?php if ($guardadasIa === []) : ?>
  <p class="vacio">Todavía no guardaste ninguna.
    <?php if (Ia::disponible()) : ?>
      Pedile una a la IA desde <a href="<?= e(url('app/index.php#ia')) ?>">Mi cocina</a> y tocá "Guardar".
    <?php endif; ?>
  </p>
<?php else : ?>
  <div class="grilla">
    <?php foreach ($guardadasIa as $recetaIa) : ?>
      <article class="tarjeta tarjeta-receta tarjeta-ia">
        <h2><a href="<?= e(url('app/receta-ia.php?id=' . $recetaIa['id'])) ?>"><?= e($recetaIa['titulo']) ?></a></h2>
        <p class="texto-suave">
          <?php if ($recetaIa['tipo'] === 'adaptada' && $recetaIa['titulo_base'] !== null) : ?>
            Adaptada de <?= e($recetaIa['titulo_base']) ?> ·
          <?php endif; ?>
          <?= e(date('d/m/Y', (int) strtotime($recetaIa['creado_en']))) ?>
        </p>
        <span class="etiqueta">Sugerida por IA</span>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php require parcial('pie'); ?>
