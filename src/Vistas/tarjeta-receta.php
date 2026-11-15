<?php
/**
 * Tarjeta de una receta para los listados. Espera la variable $receta
 * (una fila de RecetaRepositorio::buscar(), ::sugeridas() o ::recomendadas()).
 * Si viene de recomendadas() también muestra qué usa de lo que vence y con qué se puede reemplazar lo que falta.
 * Toda la tarjeta es clickeable: el enlace del título se estira con CSS (.tarjeta-receta h2 a::before).
 * Muestra los dibujos de hasta 5 ingredientes (y "+N" con el resto); los que faltan van en gris y tachados.
 */

use Cooki\Repositorios\RecetaRepositorio;

$faltan = (int) $receta['total'] - (int) $receta['tengo'];
$dibujos = RecetaRepositorio::ingredientesDeTarjeta($receta['ingredientes'] ?? null);
$maximo = 5;
?>
<article class="tarjeta tarjeta-receta">
  <h2><a href="<?= e(url('app/receta.php?id=' . $receta['id'])) ?>"><?= e($receta['titulo']) ?></a><?php if (!empty($receta['favorita'])) : ?>
    <span class="tarjeta-receta__favorita" title="Una de tus favoritas">♥<span class="solo-lectores"> (favorita)</span></span><?php endif; ?></h2>
  <p class="texto-suave"><?= e($receta['descripcion']) ?></p>
  <?php if ($dibujos !== []) : ?>
    <ul class="mini-ingredientes" aria-label="Ingredientes">
      <?php foreach (array_slice($dibujos, 0, $maximo) as $ing) : ?>
        <li class="<?= $ing['tengo'] ? 'tengo' : 'falta' ?>">
          <img src="<?= e(iconoIngrediente($ing['nombre'])) ?>" width="40" height="40"
               alt="<?= e($ing['nombre'] . ($ing['tengo'] ? '' : ' (te falta)')) ?>" title="<?= e($ing['nombre']) ?>">
        </li>
      <?php endforeach; ?>
      <?php if (count($dibujos) > $maximo) : ?>
        <li class="mini-ingredientes__mas">+<?= count($dibujos) - $maximo ?></li>
      <?php endif; ?>
    </ul>
  <?php endif; ?>
  <p class="tarjeta-receta__datos">
    <span><?= e($receta['minutos']) ?> min</span> ·
    <?= dificultadReceta((int) $receta['dificultad']) ?> ·
    <span><?= e($receta['porciones_base']) ?> porciones</span>
  </p>
  <?php if (!empty($receta['usa_lo_que_vence']) || !empty($receta['sustituciones'])) : ?>
    <ul class="tarjeta-receta__avisos">
      <?php if (!empty($receta['usa_lo_que_vence'])) : ?>
        <li class="tarjeta-receta__vence">Usa lo que vence: <?= e(mb_strtolower(implode(', ', $receta['usa_lo_que_vence']))) ?></li>
      <?php endif; ?>
      <?php foreach ($receta['sustituciones'] ?? [] as $cambio) : ?>
        <li class="tarjeta-receta__cambio">Sin <?= e(mb_strtolower($cambio['falta'])) ?>: podés usar <?= e(mb_strtolower($cambio['usar'])) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p>
    <?php if (!empty($receta['restringida'])) : ?>
      <span class="etiqueta etiqueta--error">Tiene algo que no comés</span>
    <?php endif; ?>
    <?php if (!empty($receta['fuera_de_dieta'])) : ?>
      <span class="etiqueta etiqueta--error">No va con tu dieta</span>
    <?php endif; ?>
    <?php if ($faltan <= 0) : ?>
      <span class="etiqueta etiqueta--exito">Tenés todo</span>
    <?php else : ?>
      <span class="etiqueta<?= $faltan <= 2 ? ' etiqueta--alerta' : '' ?>">Tenés <?= e($receta['tengo']) ?> de <?= e($receta['total']) ?></span>
    <?php endif; ?>
  </p>
  <?= tiposReceta(explode(', ', (string) $receta['etiquetas'])) ?>
</article>
