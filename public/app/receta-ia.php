<?php

/**
 * Una receta que propuso la IA (incremento 3 · HU-30 y HU-31).
 *   receta-ia.php?id=N            → la receta: cada ingrediente con "lo tenés" o "te falta", los pasos,
 *                                   "Por qué esta receta" (o "Qué cambió", si es adaptada) y el sello "Sugerida por IA"
 *   POST accion=pedir             → receta nueva con lo que tenés (porciones, minutos, tipo). Viene de Mi cocina
 *   POST accion=otra id=N         → "Pedir otra": las mismas opciones, sin repetir las que ya vio
 *   POST accion=adaptar receta=N  → "Adaptar con IA" (HU-31): adaptacion y porciones. Viene de app/receta.php
 *   POST accion=reemplazo         → "¿Con qué lo reemplazo? (IA)" (HU-32): receta, ingrediente y porciones.
 *                                   La respuesta se guarda en la sesión y se ve una vez en app/receta.php
 *   POST accion=guardar|quitar    → la guarda en "Mis recetas de IA" (o la saca)
 *   POST accion=borrar            → la borra
 * Lo que falta va a la lista con los formularios de app/lista.php (receta_ia=N). Con JavaScript (ia.js) los pedidos
 * a la IA van por la API y muestran "Pensando…"; sin JavaScript, son estos formularios comunes.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Dominio\Porciones;
use Cooki\Dominio\RecetaIa;
use Cooki\Ia\ErrorIa;
use Cooki\Ia\Ia;
use Cooki\Repositorios\IaRepositorio;
use Cooki\Repositorios\ListaRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();

$db = Db::conexion();
$iaRepo = new IaRepositorio($db);
$usuarioId = Sesion::idUsuario();

if (esPost()) {
    Sesion::verificarCsrf();
    $accion = campo('accion');
    $id = (int) ($_POST['id'] ?? 0);
    if ($accion === 'pedir' || $accion === 'otra') {
        $opciones = $_POST;
        $evitar = [];
        if ($accion === 'otra') {
            $anterior = $iaRepo->receta($usuarioId, $id);
            if ($anterior === null) {
                redirigir('app/index.php');
            }
            $opciones = $anterior['contenido']['pedido']['opciones'] ?? [];
            $evitar = [...($anterior['contenido']['pedido']['evitar'] ?? []), $anterior['titulo']];
        }
        try {
            $nueva = (new Ia($db))->recetaNueva($usuarioId, $opciones, $evitar);
            redirigir('app/receta-ia.php?id=' . $nueva);
        } catch (ErrorIa $e) {
            Sesion::avisar('error', $e->paraUsuario());
            redirigir($accion === 'otra' ? 'app/receta-ia.php?id=' . $id : 'app/index.php#ia');
        }
    }
    if ($accion === 'adaptar') {
        $recetaId = (int) ($_POST['receta'] ?? 0);
        $original = (new RecetaRepositorio($db))->obtener($recetaId, $usuarioId);
        if ($original === null) {
            redirigir('error.php?motivo=no-encontrado');
        }
        $porciones = (int) ($_POST['porciones'] ?? $original['porciones_base']);
        try {
            $nueva = (new Ia($db))->adaptar($usuarioId, $original, campo('adaptacion'), $porciones);
            redirigir('app/receta-ia.php?id=' . $nueva);
        } catch (ErrorIa $e) {
            Sesion::avisar('error', $e->paraUsuario());
            redirigir('app/receta.php?id=' . $recetaId . '&porciones=' . $porciones . '#adaptar');
        }
    }
    if ($accion === 'reemplazo') {
        $recetaId = (int) ($_POST['receta'] ?? 0);
        $ingredienteId = (int) ($_POST['ingrediente'] ?? 0);
        $original = (new RecetaRepositorio($db))->obtener($recetaId, $usuarioId);
        if ($original === null) {
            redirigir('error.php?motivo=no-encontrado');
        }
        $porciones = (int) ($_POST['porciones'] ?? $original['porciones_base']);
        try {
            $_SESSION['reemplazo_ia'][$recetaId][$ingredienteId] = (new Ia($db))
                ->reemplazo($usuarioId, $original, $ingredienteId, $porciones);
        } catch (ErrorIa $e) {
            Sesion::avisar('error', $e->paraUsuario());
        }
        redirigir('app/receta.php?id=' . $recetaId . '&porciones=' . $porciones . '#ing-' . $ingredienteId);
    }
    if ($accion === 'guardar' || $accion === 'quitar') {
        $iaRepo->marcarGuardada($usuarioId, $id, $accion === 'guardar');
        Sesion::avisar('exito', $accion === 'guardar'
            ? 'Listo, la guardamos en Mis recetas de IA.'
            : 'La sacamos de Mis recetas de IA.');
        redirigir('app/receta-ia.php?id=' . $id);
    }
    if ($accion === 'borrar') {
        $iaRepo->borrarReceta($usuarioId, $id);
        Sesion::avisar('exito', 'Borramos la receta.');
        redirigir('app/mis-recetas-ia.php');
    }
    redirigir('app/index.php');
}

$receta = $iaRepo->receta($usuarioId, (int) ($_GET['id'] ?? 0));
if ($receta === null) {
    redirigir('error.php?motivo=no-encontrado');
}
$contenido = RecetaIa::marcar($receta['contenido'], $iaRepo->idsQueTengo($usuarioId));
$faltan = RecetaIa::faltantes($contenido);
$pendientes = (new ListaRepositorio($db))->ingredientesPendientes($usuarioId);
$esNueva = $receta['tipo'] === 'nueva';
$volverReceta = 'app/receta-ia.php?id=' . $receta['id'];

$titulo = $receta['titulo'];
require parcial('encabezado');
?>
<?= enlaceVolver('app/mis-recetas-ia.php', 'Volver a Mis recetas de IA', [['app/mis-recetas-ia.php', 'Mis recetas de IA'], ['app/index.php', 'Mi cocina']]) ?>
<article class="receta-ia">
  <div class="receta-titulo">
    <h1><?= e($receta['titulo']) ?></h1>
  </div>
  <p class="sello-ia"><img src="<?= e(url('img/ia.svg')) ?>" alt="" width="36" height="36">
    <span>Sugerida por IA · <strong>revisala antes de cocinar</strong></span></p>
  <?php if (!$esNueva && $receta['titulo_base'] !== null) : ?>
    <p class="texto-suave">Adaptada de <a href="<?= e(url('app/receta.php?id=' . $receta['receta_base'])) ?>"><?= e($receta['titulo_base']) ?></a>
      (la original no cambia).</p>
  <?php endif; ?>
  <?php if ($contenido['descripcion'] !== '') : ?>
    <p class="texto-suave"><?= e($contenido['descripcion']) ?></p>
  <?php endif; ?>
  <p class="acciones">
    <span class="etiqueta"><?= e($contenido['porciones']) ?> porci<?= (int) $contenido['porciones'] === 1 ? 'ón' : 'ones' ?></span>
    <span class="etiqueta">Preparación: <?= e($contenido['minutos_prep']) ?> min</span>
    <span class="etiqueta">Cocción: <?= e($contenido['minutos_coccion']) ?> min</span>
    <span class="etiqueta"><?= e(RecetaRepositorio::DIFICULTADES[(int) $contenido['dificultad']] ?? '') ?></span>
  </p>

  <?php if ($contenido['cambios'] !== []) : ?>
    <section class="postit receta-ia__porque" aria-labelledby="titulo-cambios">
      <span class="postit__chinche" aria-hidden="true"></span>
      <h2 id="titulo-cambios">Qué cambió</h2>
      <ul>
        <?php foreach ($contenido['cambios'] as $cambio) : ?>
          <li><?= e($cambio) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($contenido['por_que'] !== '') : ?>
        <p><?= e($contenido['por_que']) ?></p>
      <?php endif; ?>
    </section>
  <?php elseif ($contenido['por_que'] !== '') : ?>
    <section class="postit receta-ia__porque" aria-labelledby="titulo-porque">
      <span class="postit__chinche" aria-hidden="true"></span>
      <h2 id="titulo-porque">Por qué esta receta</h2>
      <p><?= e($contenido['por_que']) ?></p>
    </section>
  <?php endif; ?>

  <div class="receta-ia__acciones acciones">
    <form method="post" action="<?= e(url('app/receta-ia.php')) ?>">
      <?= Sesion::campoCsrf() ?>
      <input type="hidden" name="id" value="<?= e($receta['id']) ?>">
      <?php if ($receta['guardada']) : ?>
        <input type="hidden" name="accion" value="quitar">
        <button class="boton boton--secundario" type="submit">Sacar de Mis recetas de IA</button>
      <?php else : ?>
        <input type="hidden" name="accion" value="guardar">
        <button class="boton" type="submit">Guardar en Mis recetas de IA</button>
      <?php endif; ?>
    </form>
    <?php if ($esNueva && Ia::disponible()) : ?>
      <form method="post" action="<?= e(url('app/receta-ia.php')) ?>" data-ia="<?= e(url('api/ia/receta')) ?>">
        <?= Sesion::campoCsrf() ?>
        <input type="hidden" name="accion" value="otra">
        <input type="hidden" name="id" value="<?= e($receta['id']) ?>">
        <button class="boton boton--secundario" type="submit">Pedir otra</button>
        <p class="nota-ia__estado" role="status" aria-live="polite"></p>
      </form>
    <?php endif; ?>
  </div>

  <div class="detalle-receta">
    <section class="tarjeta" id="ingredientes">
      <h2>Ingredientes</h2>
      <ul class="lista-ingredientes">
        <?php foreach ($contenido['ingredientes'] as $n => $ing) : ?>
          <li class="<?= $ing['tengo'] ? 'tengo' : 'falta' ?>">
            <span aria-hidden="true"><?= $ing['tengo'] ? '✓' : '✗' ?></span>
            <span class="solo-lectores"><?= $ing['tengo'] ? 'Lo tenés:' : 'Te falta:' ?></span>
            <img class="icono-chico" src="<?= e(iconoIngrediente($ing['nombre'])) ?>" alt="" width="28" height="28">
            <strong><?= e($ing['nombre']) ?></strong>
            <?= e(Porciones::texto($ing['cantidad'], $ing['unidad'] === '' ? null : $ing['unidad'])) ?>
            <?php if (!$ing['tengo']) : ?>
              <?php if ($ing['id'] !== null && in_array($ing['id'], $pendientes, true)) : ?>
                <a class="etiqueta etiqueta--lista" href="<?= e(url('app/lista.php')) ?>">En tu lista</a>
              <?php else : ?>
                <form class="mas-lista" method="post" action="<?= e(url('app/lista.php')) ?>">
                  <?= Sesion::campoCsrf() ?>
                  <input type="hidden" name="accion" value="agregar">
                  <input type="hidden" name="receta_ia" value="<?= e($receta['id']) ?>">
                  <input type="hidden" name="n" value="<?= $n ?>">
                  <input type="hidden" name="volver" value="<?= e(url($volverReceta)) ?>#ingredientes">
                  <button class="mas-lista__boton" type="submit" title="Agregar a la lista de compras">
                    + lista<span class="solo-lectores">: agregar <?= e($ing['nombre']) ?> a la lista de compras</span>
                  </button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($faltan !== []) : ?>
        <form class="mas-lista mas-lista--todo" method="post" action="<?= e(url('app/lista.php')) ?>">
          <?= Sesion::campoCsrf() ?>
          <input type="hidden" name="accion" value="receta">
          <input type="hidden" name="receta_ia" value="<?= e($receta['id']) ?>">
          <input type="hidden" name="volver" value="<?= e(url($volverReceta)) ?>#ingredientes">
          <button class="boton boton--secundario boton--chico" type="submit">Agregar lo que falta a la lista</button>
        </form>
      <?php endif; ?>
      <p class="texto-suave"><small>✓ = está en tu despensa, es un básico como sal o aceite, o lo marcaste en <a href="<?= e(url('app/mis-preferencias.php')) ?>">"Siempre tengo"</a>.</small></p>
    </section>

    <section class="tarjeta">
      <h2>Paso a paso</h2>
      <ol class="lista-pasos">
        <?php foreach ($contenido['pasos'] as $paso) : ?>
          <li class="paso">
            <span class="paso__texto"><?= e($paso['texto']) ?></span>
            <?php if ($paso['minutos'] !== null) : ?>
              <span class="etiqueta"><?= e($paso['minutos']) ?> min</span>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  </div>

  <form class="receta-ia__borrar" method="post" action="<?= e(url('app/receta-ia.php')) ?>"
        data-confirmar="¿Borrar esta receta? No se puede deshacer.">
    <?= Sesion::campoCsrf() ?>
    <input type="hidden" name="accion" value="borrar">
    <input type="hidden" name="id" value="<?= e($receta['id']) ?>">
    <button class="boton boton--peligro boton--chico" type="submit">Borrar esta receta</button>
  </form>
</article>
<script src="<?= e(recurso('js/lista.js')) ?>" defer></script>
<script src="<?= e(recurso('js/ia.js')) ?>" defer></script>
<?php require parcial('pie'); ?>
