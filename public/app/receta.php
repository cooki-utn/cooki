<?php

/**
 * Detalle de una receta: ingredientes (marcando cuáles tiene el usuario y con qué reemplazar lo que falta)
 * y pasos. Se puede escalar a otra cantidad de porciones:
 *   receta.php?id=6&porciones=2 → cantidades y pasos ({ing:...}) recalculados (Dominio\Porciones)
 * Lo que falta se manda a la lista de compras (app/lista.php) con las cantidades de esas porciones:
 * de a uno ("+ lista") o todo junto ("Agregar lo que falta a la lista").
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Dominio\ListaCompras;
use Cooki\Dominio\Porciones;
use Cooki\Repositorios\CocinaRepositorio;
use Cooki\Repositorios\ListaRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();

$receta = (new RecetaRepositorio(Db::conexion()))->obtener((int) ($_GET['id'] ?? 0), Sesion::idUsuario());
if ($receta === null) {
    redirigir('error.php?motivo=no-encontrado');
}

// Porciones elegidas (por defecto, las de la receta) y factor para escalar
$base = (int) $receta['porciones_base'];
$porciones = max(Porciones::MINIMO, min(Porciones::MAXIMO, (int) ($_GET['porciones'] ?? $base)));
$factor = Porciones::factor($base, $porciones);
$cantidad = fn (array $ing): string => Porciones::texto(
    $ing['cantidad'] === null ? null : (float) $ing['cantidad'],
    $ing['unidad'],
    $factor
);
$porSlug = [];
foreach ($receta['ingredientes'] as $ing) {
    $porSlug[Porciones::slug($ing['nombre'])] = [
        'nombre' => $ing['nombre'],
        'cantidad' => $ing['cantidad'] === null ? null : (float) $ing['cantidad'],
        'unidad' => $ing['unidad'],
    ];
}
// Modo cocina: si ya la está cocinando, el botón dice "Seguir cocinando"
$cocinando = (new CocinaRepositorio(Db::conexion()))->enCurso(Sesion::idUsuario());
$sigue = $cocinando !== null && (int) $cocinando['receta_id'] === (int) $receta['id'] ? $cocinando : null;
$enlacePorciones = fn (int $n): string => url('app/receta.php?id=' . $receta['id'] . '&porciones=' . $n) . '#ingredientes';
// Lista de compras: qué falta (para el botón de todo junto) y qué ya está anotado
$faltantes = ListaCompras::faltantes($receta['ingredientes'], $factor);
$enLista = (new ListaRepositorio(Db::conexion()))->ingredientesPendientes(Sesion::idUsuario());
$volverReceta = (string) ($_SERVER['REQUEST_URI'] ?? '') . '#ingredientes';

$titulo = $receta['titulo'];
require parcial('encabezado');
?>
<?= enlaceVolver('app/recetas.php', 'Volver a Recetas', [['app/recetas.php', 'Recetas']]) ?>
<article>
  <div class="receta-titulo">
    <h1><?= e($receta['titulo']) ?></h1>
    <?php /* Favorita: sin JavaScript recarga la página; con JavaScript (receta.js) cambia sin recargar */ ?>
    <form class="favorita" method="post" action="<?= e(url('app/mis-preferencias.php')) ?>">
      <?= Sesion::campoCsrf() ?>
      <input type="hidden" name="accion" value="favorita">
      <input type="hidden" name="receta" value="<?= e($receta['id']) ?>">
      <input type="hidden" name="volver" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
      <button class="favorita__boton" type="submit" aria-pressed="<?= $receta['favorita'] ? 'true' : 'false' ?>"
              title="<?= $receta['favorita'] ? 'Sacar de mis favoritas' : 'Guardar en mis favoritas' ?>">
        <span class="favorita__corazon" aria-hidden="true"><?= $receta['favorita'] ? '♥' : '♡' ?></span>
        <span class="favorita__texto"><?= $receta['favorita'] ? 'En mis favoritas' : 'Guardar' ?></span>
      </button>
    </form>
  </div>
  <?php if (!empty($receta['imagen'])) : ?>
    <?php /* La foto (si la cargó la administración) va pegada como una foto instantánea */ ?>
    <figure class="foto-receta">
      <img src="<?= e(url($receta['imagen'])) ?>" alt="Foto de <?= e($receta['titulo']) ?>" loading="lazy">
    </figure>
  <?php endif; ?>
  <p class="texto-suave"><?= e($receta['descripcion']) ?></p>
  <p class="acciones">
    <span class="etiqueta">Preparación: <?= e($receta['minutos_prep']) ?> min</span>
    <span class="etiqueta">Cocción: <?= e($receta['minutos_coccion']) ?> min</span>
    <span class="etiqueta"><?= e(RecetaRepositorio::DIFICULTADES[(int) $receta['dificultad']]) ?></span>
    <?php foreach ($receta['etiquetas'] as $etiqueta) : ?>
      <span class="etiqueta"><?= e(str_replace('_', ' ', $etiqueta['nombre'])) ?></span>
    <?php endforeach; ?>
  </p>

  <?php /* Modo cocina: paso a paso con letra grande, temporizadores y descuento de la despensa al terminar */ ?>
  <div class="empezar-cocina">
    <?php if ($sigue !== null) : ?>
      <a class="boton boton--cocinar" href="<?= e(url('app/cocinar.php?sesion=' . $sigue['id'])) ?>">
        Seguir cocinando <small>(<?= e($sigue['hechos']) ?> de <?= e($sigue['pasos']) ?> pasos)</small>
      </a>
    <?php else : ?>
      <form method="post" action="<?= e(url('app/cocinar.php')) ?>">
        <?= Sesion::campoCsrf() ?>
        <input type="hidden" name="accion" value="iniciar">
        <input type="hidden" name="receta" value="<?= e($receta['id']) ?>">
        <input type="hidden" name="porciones" value="<?= $porciones ?>">
        <button class="boton boton--cocinar" type="submit">Cocinar paso a paso <small>(<?= $porciones ?> porci<?= $porciones === 1 ? 'ón' : 'ones' ?>)</small></button>
      </form>
    <?php endif; ?>
    <p class="texto-suave"><small>Letra grande, temporizadores y, al terminar, se descuenta de tu despensa lo que usaste.</small></p>
  </div>

  <div class="detalle-receta">
    <section class="tarjeta" id="ingredientes">
      <h2>Ingredientes</h2>
      <?php /* Porciones: − y + son enlaces (andan sin JavaScript); las cantidades y los pasos se recalculan */ ?>
      <p class="porciones" aria-label="Porciones">
        <a class="porciones__boton<?= $porciones <= Porciones::MINIMO ? ' porciones__boton--tope' : '' ?>"
           href="<?= e($enlacePorciones(max(Porciones::MINIMO, $porciones - 1))) ?>" aria-label="Una porción menos">−</a>
        <span class="porciones__numero"><strong><?= $porciones ?></strong> porci<?= $porciones === 1 ? 'ón' : 'ones' ?></span>
        <a class="porciones__boton<?= $porciones >= Porciones::MAXIMO ? ' porciones__boton--tope' : '' ?>"
           href="<?= e($enlacePorciones(min(Porciones::MAXIMO, $porciones + 1))) ?>" aria-label="Una porción más">+</a>
        <?php if ($porciones !== $base) : ?>
          <a class="porciones__volver" href="<?= e($enlacePorciones($base)) ?>">Volver a <?= $base ?></a>
        <?php endif; ?>
      </p>
      <ul class="lista-ingredientes">
        <?php foreach ($receta['ingredientes'] as $ing) : ?>
          <?php $tiene = $ing['en_despensa'] || $ing['es_basico']; ?>
          <li class="<?= $tiene ? 'tengo' : 'falta' ?><?= $ing['restringido'] ? ' no-como' : '' ?>">
            <span aria-hidden="true"><?= $tiene ? '✓' : '✗' ?></span>
            <span class="solo-lectores"><?= $tiene ? 'Lo tenés:' : 'Te falta:' ?></span>
            <img class="icono-chico" src="<?= e(iconoIngrediente($ing['nombre'])) ?>" alt="" width="28" height="28">
            <strong><?= e($ing['nombre']) ?></strong>
            <?= e($cantidad($ing)) ?>
            <?php if ($ing['nota']) : ?><span class="texto-suave">(<?= e($ing['nota']) ?>)</span><?php endif; ?>
            <?php if ($ing['es_opcional']) : ?><span class="etiqueta">opcional</span><?php endif; ?>
            <?php if ($ing['restringido']) : ?><span class="etiqueta etiqueta--error">No lo comés</span><?php endif; ?>
            <?php if (!$tiene && $ing['sustituto'] !== null) : ?>
              <span class="cambio-ingrediente">⇄ Podés usar <?= e(mb_strtolower($ing['sustituto'])) ?></span>
            <?php endif; ?>
            <?php if (!$tiene && !$ing['restringido']) : ?>
              <?php if (in_array((int) $ing['ingrediente_id'], $enLista, true)) : ?>
                <a class="etiqueta etiqueta--lista" href="<?= e(url('app/lista.php')) ?>">En tu lista</a>
              <?php else : ?>
                <?php /* "+ lista": sin JavaScript recarga y vuelve acá; con JavaScript (lista.js) se agrega sin recargar */ ?>
                <form class="mas-lista" method="post" action="<?= e(url('app/lista.php')) ?>"<?= $ing['es_opcional'] ? ' data-opcional' : '' ?>>
                  <?= Sesion::campoCsrf() ?>
                  <input type="hidden" name="accion" value="agregar">
                  <input type="hidden" name="ingrediente_id" value="<?= e($ing['ingrediente_id']) ?>">
                  <input type="hidden" name="receta" value="<?= e($receta['id']) ?>">
                  <input type="hidden" name="porciones" value="<?= $porciones ?>">
                  <input type="hidden" name="volver" value="<?= e($volverReceta) ?>">
                  <button class="mas-lista__boton" type="submit" title="Agregar a la lista de compras">
                    + lista<span class="solo-lectores">: agregar <?= e($ing['nombre']) ?> a la lista de compras</span>
                  </button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($faltantes !== []) : ?>
        <form class="mas-lista mas-lista--todo" method="post" action="<?= e(url('app/lista.php')) ?>">
          <?= Sesion::campoCsrf() ?>
          <input type="hidden" name="accion" value="receta">
          <input type="hidden" name="receta" value="<?= e($receta['id']) ?>">
          <input type="hidden" name="porciones" value="<?= $porciones ?>">
          <input type="hidden" name="volver" value="<?= e($volverReceta) ?>">
          <button class="boton boton--secundario boton--chico" type="submit">Agregar lo que falta a la lista</button>
        </form>
      <?php endif; ?>
      <p class="texto-suave"><small>✓ = está en tu despensa, es un básico como sal o aceite, o lo marcaste en <a href="<?= e(url('app/mis-preferencias.php')) ?>">"Siempre tengo"</a>.</small></p>
    </section>

    <section class="tarjeta">
      <h2>Paso a paso</h2>
      <?php /* Cada paso se puede tildar para saber por dónde vas. receta.js lo recuerda en este navegador. */ ?>
      <ol class="lista-pasos" data-receta="<?= e($receta['id']) ?>">
        <?php foreach ($receta['pasos'] as $paso) : ?>
          <li class="paso">
            <label>
              <input class="paso__check" type="checkbox">
              <span class="paso__texto"><?= e(Porciones::pasoConCantidades($paso['instruccion'], $porSlug, $factor)) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
      </ol>
      <p class="pasos-acciones" hidden>
        <button class="boton boton--secundario boton--chico" type="button" id="reiniciar-pasos">Empezar de nuevo</button>
      </p>
    </section>
  </div>
  <?php if ($receta['autor']) : ?>
    <p class="texto-suave"><small>Cargada por <?= e($receta['autor']) ?>.</small></p>
  <?php endif; ?>
</article>
<script src="<?= e(recurso('js/receta.js')) ?>" defer></script>
<script src="<?= e(recurso('js/lista.js')) ?>" defer></script>
<?php require parcial('pie'); ?>
