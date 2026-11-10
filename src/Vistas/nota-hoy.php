<?php

/**
 * Mi cocina: la nota "Hoy" con lo que dice el plan semanal para el almuerzo y la cena de hoy (HU-35).
 * Las recetas del recetario tienen "Cocinar" (el modo cocina, con las porciones del plan); las de IA, "Ver la receta".
 * Usa $comidasHoy (PlanRepositorio::semana() de hoy, por "fecha|momento") y $fechaHoy.
 */

declare(strict_types=1);

use Cooki\Dominio\PlanSemanal;
use Cooki\Sesion;

?>
<section class="postit nota-hoy" aria-labelledby="titulo-hoy">
  <span class="postit__chinche" aria-hidden="true"></span>
  <img class="nota-hoy__dibujo" src="<?= e(url('img/plan.svg')) ?>" alt="" width="52" height="52">
  <h2 id="titulo-hoy">Hoy</h2>
  <?php if ($comidasHoy === []) : ?>
    <p>Todavía no elegiste qué cocinar hoy.</p>
    <p><a href="<?= e(url('app/plan.php')) ?>">Armá tu semana →</a></p>
  <?php else : ?>
    <ul class="nota-hoy__comidas">
      <?php foreach (PlanSemanal::MOMENTOS as $momento => $nombreMomento) : ?>
        <?php $comida = $comidasHoy[$fechaHoy . '|' . $momento] ?? null; ?>
        <li>
          <span class="nota-hoy__momento"><?= e($nombreMomento) ?>:</span>
          <?php if ($comida === null) : ?>
            <span class="texto-suave">nada todavía</span>
          <?php elseif ($comida['receta_id'] !== null) : ?>
            <a href="<?= e(url('app/receta.php?id=' . $comida['receta_id'] . '&porciones=' . $comida['porciones'])) ?>"><?= e($comida['titulo']) ?></a>
            <form class="nota-hoy__cocinar" method="post" action="<?= e(url('app/cocinar.php')) ?>">
              <?= Sesion::campoCsrf() ?>
              <input type="hidden" name="accion" value="iniciar">
              <input type="hidden" name="receta" value="<?= e($comida['receta_id']) ?>">
              <input type="hidden" name="porciones" value="<?= e($comida['porciones']) ?>">
              <button class="boton boton--chico" type="submit">Cocinar<span class="solo-lectores"> <?= e($comida['titulo']) ?></span></button>
            </form>
          <?php else : ?>
            <a href="<?= e(url('app/receta-ia.php?id=' . $comida['receta_ia_id'])) ?>"><?= e($comida['titulo']) ?></a>
            <small>(de IA)</small>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
    <p class="nota-hoy__ver"><a href="<?= e(url('app/plan.php')) ?>">Ver el plan de la semana →</a></p>
  <?php endif; ?>
</section>
