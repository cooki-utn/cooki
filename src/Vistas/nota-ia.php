<?php

/**
 * Mi cocina: la nota "¿Y si inventamos algo?" para pedirle una receta a la IA (HU-30).
 * Solo opciones, sin texto libre (así nadie le escribe instrucciones raras a la IA).
 * Sin JavaScript es un formulario común a app/receta-ia.php; con JavaScript (ia.js) va por la API y
 * muestra "Pensando…". Usa $iaRestantes (pedidos que le quedan hoy). Si la IA no está configurada, no se incluye.
 */

declare(strict_types=1);

use Cooki\Dominio\RecetaIa;
use Cooki\Ia\Ia;
use Cooki\Sesion;

?>
<section class="postit nota-ia" id="ia" aria-labelledby="titulo-ia">
  <span class="postit__chinche" aria-hidden="true"></span>
  <img class="nota-ia__dibujo" src="<?= e(url('img/ia.svg')) ?>" alt="" width="64" height="64">
  <h2 id="titulo-ia">¿Y si inventamos algo?</h2>
  <p>La IA te propone una receta con lo que tenés, empezando por lo que vence.</p>
  <form class="nota-ia__form" method="post" action="<?= e(url('app/receta-ia.php')) ?>"
        data-ia="<?= e(url('api/ia/receta')) ?>">
    <?= Sesion::campoCsrf() ?>
    <input type="hidden" name="accion" value="pedir">
    <div class="nota-ia__opciones">
      <label class="nota-ia__porciones">
        Porciones
        <select name="porciones">
          <?php for ($n = 1; $n <= RecetaIa::PORCIONES_MAX; $n++) : ?>
            <option value="<?= $n ?>"<?= $n === 2 ? ' selected' : '' ?>><?= $n ?></option>
          <?php endfor; ?>
        </select>
      </label>
      <fieldset class="chips">
        <legend>Tiempo máximo</legend>
        <?php foreach (RecetaIa::MINUTOS as $minutos) : ?>
          <label class="chip">
            <input type="radio" name="minutos" value="<?= $minutos ?>"<?= $minutos === 30 ? ' checked' : '' ?>>
            <span><?= $minutos ?> min</span>
          </label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset class="chips">
        <legend>Tipo</legend>
        <?php foreach (RecetaIa::TIPOS as $clave => $nombre) : ?>
          <label class="chip">
            <input type="radio" name="tipo" value="<?= e($clave) ?>"<?= $clave === 'almuerzo_cena' ? ' checked' : '' ?>>
            <span><?= e($nombre) ?></span>
          </label>
        <?php endforeach; ?>
      </fieldset>
    </div>
    <p class="nota-ia__boton">
      <button class="boton" type="submit"<?= $iaRestantes === 0 ? ' disabled' : '' ?>>Pedirle una receta a la IA</button>
      <small class="nota-ia__quedan"><?= e(Ia::textoRestantes($iaRestantes)) ?></small>
    </p>
    <p class="nota-ia__estado" role="status" aria-live="polite"></p>
  </form>
  <p class="nota-ia__privacidad">
    <small>Para armar la receta se envían a Google (Gemini) solo tus ingredientes y restricciones, nunca tu nombre
    ni tu correo.</small>
  </p>
  <p class="nota-ia__mis"><a href="<?= e(url('app/mis-recetas-ia.php')) ?>">Mis recetas de IA →</a></p>
</section>
