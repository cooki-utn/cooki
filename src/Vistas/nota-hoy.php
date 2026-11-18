<?php

/**
 * Mi cocina: la nota "Hoy" con lo que dice el plan semanal para el almuerzo y la cena de hoy (HU-35).
 * Las recetas del recetario tienen "Cocinar" (el modo cocina, con las porciones del plan); las de IA, "Ver la receta".
 * Se cierra y se despliega como la nota de la lista (Sesion::notaHoy()):
 *   abierta  → la nota entera, con la ✕ para cerrarla
 *   cerrada  → un recuadro chico ("Hoy · 2 comidas") que la vuelve a desplegar
 *   nunca    → no se muestra (se cambia en Mis preferencias)
 * Las dos formas están en el HTML y una está oculta: sin JavaScript, los botones envían el formulario y recargan;
 * con JavaScript (sitio.js) cambia en el momento y se guarda en segundo plano.
 * Usa $comidasHoy (PlanRepositorio::semana() de hoy, por "fecha|momento") y $fechaHoy.
 */

declare(strict_types=1);

use Cooki\Dominio\PlanSemanal;
use Cooki\Sesion;

$estadoHoy = Sesion::notaHoy();
if ($estadoHoy === 'nunca') {
    return;
}
$volverHoy = (string) ($_SERVER['REQUEST_URI'] ?? '');
/** El formulario de cada botón: guarda el estado nuevo en la cuenta y vuelve a esta página. */
$formHoy = function (string $valor, string $clase, string $contenido, string $titulo) use ($volverHoy): void {
    ?>
    <form class="nota-lista__form" method="post" action="<?= e(url('app/preferencias.php')) ?>">
      <?= Sesion::campoCsrf() ?>
      <input type="hidden" name="volver" value="<?= e($volverHoy) ?>">
      <button class="<?= e($clase) ?>" type="submit" name="nota_hoy" value="<?= e($valor) ?>" title="<?= e($titulo) ?>"><?= $contenido ?></button>
    </form>
    <?php
};
$cuantasHoy = count($comidasHoy);
?>
<div class="nota-plegable nota-hoy-plegable" data-campo="nota_hoy" data-estado="<?= e($estadoHoy) ?>">
  <section class="postit nota-hoy nota-plegable__abierta" aria-labelledby="titulo-hoy"<?= $estadoHoy === 'cerrada' ? ' hidden' : '' ?>>
    <span class="postit__chinche" aria-hidden="true"></span>
    <div class="postit__titulo">
      <img class="nota-hoy__dibujo" src="<?= e(url('img/plan.svg')) ?>" alt="" width="44" height="44">
      <h2 id="titulo-hoy">Hoy</h2>
      <?php $formHoy('cerrada', 'nota-lista__cerrar', '<span aria-hidden="true">✕</span><span class="solo-lectores">Cerrar la nota de hoy</span>', 'Cerrar (queda un recuadro chico)'); ?>
    </div>
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
  <div class="nota-lista__cerrada nota-plegable__cerrada"<?= $estadoHoy === 'abierta' ? ' hidden' : '' ?>>
    <?php $formHoy('abierta', 'nota-lista__asomada nota-hoy__asomada', 'Hoy <b>' . $cuantasHoy . '</b><span class="solo-lectores"> comida' . ($cuantasHoy === 1 ? '' : 's') . ' en el plan. Desplegar</span><span aria-hidden="true"> ▸</span>', 'Desplegar la nota de hoy'); ?>
  </div>
</div>
