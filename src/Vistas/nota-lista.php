<?php
/**
 * La nota de la lista de compras en Mi cocina y Mi despensa (incremento 3).
 * Antes de incluirla, la página define $notaLista con lo que falta comprar (ListaRepositorio::listar(), sin lo comprado).
 *   abierta  → un post-it con lo que falta comprar y "Cerrar"
 *   cerrada  → un recuadro chico ("Lista de compras · 3") que la vuelve a desplegar
 *   nunca    → no se muestra (se cambia en Mis preferencias)
 * Las dos formas están en el HTML y una está oculta: sin JavaScript, los botones envían el formulario y recargan;
 * con JavaScript (sitio.js) cambia en el momento y se guarda en segundo plano.
 * Uso: $notaLista = [...]; require parcial('nota-lista');
 */

use Cooki\Dominio\Porciones;
use Cooki\Sesion;

$estadoNota = Sesion::notaLista();
if ($estadoNota === 'nunca') {
    return;
}
$cuantasNota = count($notaLista);
$visiblesNota = array_slice($notaLista, 0, 8);
$volverNota = (string) ($_SERVER['REQUEST_URI'] ?? '');
/** El formulario de cada botón: guarda el estado nuevo en la cuenta y vuelve a esta página. */
$formNota = function (string $valor, string $clase, string $contenido, string $titulo) use ($volverNota): void {
    ?>
    <form class="nota-lista__form" method="post" action="<?= e(url('app/preferencias.php')) ?>">
      <?= Sesion::campoCsrf() ?>
      <input type="hidden" name="volver" value="<?= e($volverNota) ?>">
      <button class="<?= e($clase) ?>" type="submit" name="nota_lista" value="<?= e($valor) ?>" title="<?= e($titulo) ?>"><?= $contenido ?></button>
    </form>
    <?php
};
?>
<div class="nota-lista" data-estado="<?= e($estadoNota) ?>">
  <section class="postit postit--celeste nota-lista__abierta" aria-labelledby="titulo-nota-lista"<?= $estadoNota === 'cerrada' ? ' hidden' : '' ?>>
    <span class="postit__chinche" aria-hidden="true"></span>
    <div class="postit__titulo">
      <h2 id="titulo-nota-lista">Lista de compras</h2>
      <?php $formNota('cerrada', 'nota-lista__cerrar', '<span aria-hidden="true">✕</span><span class="solo-lectores">Cerrar la lista de compras</span>', 'Cerrar (queda un recuadro chico)'); ?>
    </div>
    <?php if ($notaLista === []) : ?>
      <p class="texto-suave">No tenés nada anotado. Agregá desde una receta con “+ lista”.</p>
    <?php else : ?>
      <ul class="nota-lista__items">
        <?php foreach ($visiblesNota as $itemNota) : ?>
          <li>
            <img src="<?= e(iconoIngrediente($itemNota['nombre'], (bool) $itemNota['propio'] || $itemNota['ingrediente_id'] === null)) ?>" alt="" width="30" height="30">
            <strong><?= e($itemNota['nombre']) ?></strong>
            <?php if ($itemNota['cantidad'] !== null) : ?>
              <span><?= e(Porciones::texto((float) $itemNota['cantidad'], $itemNota['unidad'])) ?></span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($cuantasNota > count($visiblesNota)) : ?>
        <p class="texto-suave">y <?= $cuantasNota - count($visiblesNota) ?> más.</p>
      <?php endif; ?>
    <?php endif; ?>
    <a class="nota-lista__ir" href="<?= e(url('app/lista.php')) ?>">Ver la lista completa →</a>
  </section>
  <div class="nota-lista__cerrada"<?= $estadoNota === 'abierta' ? ' hidden' : '' ?>>
    <?php $formNota('abierta', 'nota-lista__asomada', 'Lista de compras <b>' . $cuantasNota . '</b><span class="solo-lectores"> cosas. Desplegar</span><span aria-hidden="true"> ▸</span>', 'Desplegar la lista de compras'); ?>
  </div>
</div>
