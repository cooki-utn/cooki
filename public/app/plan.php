<?php

/**
 * Plan semanal (incremento 3 · HU-35): los próximos 7 días, con almuerzo y cena, como notas en la heladera.
 *   plan.php                                       → la semana, a partir de hoy
 *   POST accion=guardar fecha= momento= receta=r:12|ia:5 porciones=  → pone (o cambia) la receta de ese lugar.
 *                                                    Sin porciones, van las de la receta
 *   POST accion=quitar fecha= momento=             → deja el lugar vacío
 * Al guardar se borra lo planeado para días que ya pasaron (no hay tareas programadas).
 * Con el encabezado X-Requested-With: fetch (plan.js) responde JSON en lugar de redirigir.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Dominio\PlanSemanal;
use Cooki\Dominio\Porciones;
use Cooki\Repositorios\IaRepositorio;
use Cooki\Repositorios\PlanRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();

$db = Db::conexion();
$repo = new PlanRepositorio($db);
$usuarioId = Sesion::idUsuario();
$hoy = new DateTimeImmutable('today');
$dias = PlanSemanal::dias($hoy);

if (esPost()) {
    Sesion::verificarCsrf();
    $porFetch = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    $fecha = campo('fecha');
    $momento = campo('momento');
    /** Responde: JSON para plan.js; si no, aviso y vuelta al día que se tocó. */
    $responder = function (bool $ok, string $mensaje, array $datos = []) use ($porFetch, $fecha): never {
        if ($porFetch) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($ok ? 200 : 422);
            echo json_encode(['ok' => $ok, 'mensaje' => $mensaje] + $datos, JSON_UNESCAPED_UNICODE);
            exit;
        }
        Sesion::avisar($ok ? 'exito' : 'error', $mensaje);
        redirigir('app/plan.php' . (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) === 1 ? '#dia-' . $fecha : ''));
    };

    $accion = campo('accion');
    if (in_array($accion, ['guardar', 'quitar'], true) && !PlanSemanal::lugarValido($fecha, $momento, $hoy)) {
        $responder(false, 'Ese día no está en el plan de esta semana.');
    }
    $lugar = PlanSemanal::lugar($fecha, $momento, $hoy);
    if ($accion === 'quitar') {
        $repo->quitar($usuarioId, $fecha, $momento);
        $responder(true, 'Listo, ' . $lugar . ' quedó libre.');
    }
    if ($accion === 'guardar') {
        $elegida = PlanSemanal::opcion(campo('receta'));
        $base = $elegida === null ? null : $repo->porcionesBase($usuarioId, $elegida['receta_id'], $elegida['receta_ia_id']);
        if ($elegida === null || $base === null) {
            $responder(false, 'Elegí una receta de la lista.');
        }
        $porciones = PlanSemanal::porciones(campo('porciones'), $base);
        $repo->limpiar($usuarioId, $hoy->format('Y-m-d'));
        $repo->guardar($usuarioId, $fecha, $momento, $elegida['receta_id'], $elegida['receta_ia_id'], $porciones);
        $responder(true, 'Anotado para ' . $lugar . '.', ['porciones' => $porciones]);
    }
    $responder(false, 'No entendimos qué querías hacer.');
}

$plan = $repo->semana($usuarioId, $dias[0], $dias[PlanSemanal::DIAS - 1]);
// Opciones del desplegable: las recomendadas, las favoritas, las de IA guardadas y todo el recetario
$todas = $repo->recetasParaElegir($usuarioId);
$recomendadas = array_slice((new RecetaRepositorio($db))->recomendadas($usuarioId), 0, 8);
$favoritas = array_values(array_filter($todas, fn (array $r) => $r['favorita'] && !$r['restringida']));
$deIa = (new IaRepositorio($db))->guardadas($usuarioId);

/** El desplegable de recetas de un lugar. La elegida se marca en "Todo el recetario" o en "Mis recetas de IA". */
$desplegable = function (string $id, string $elegida) use ($recomendadas, $favoritas, $deIa, $todas): void {
    ?>
    <select id="<?= e($id) ?>" name="receta" required>
      <option value="">Elegí una receta…</option>
      <?php if ($recomendadas !== []) : ?>
        <optgroup label="Recomendadas para vos">
          <?php foreach ($recomendadas as $r) : ?>
            <option value="r:<?= e($r['id']) ?>"><?= e($r['titulo']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endif; ?>
      <?php if ($favoritas !== []) : ?>
        <optgroup label="Tus favoritas">
          <?php foreach ($favoritas as $r) : ?>
            <option value="r:<?= e($r['id']) ?>"><?= e($r['titulo']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endif; ?>
      <?php if ($deIa !== []) : ?>
        <optgroup label="Mis recetas de IA">
          <?php foreach ($deIa as $r) : ?>
            <option value="ia:<?= e($r['id']) ?>"<?= 'ia:' . $r['id'] === $elegida ? ' selected' : '' ?>><?= e($r['titulo']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      <?php endif; ?>
      <optgroup label="Todo el recetario">
        <?php foreach ($todas as $r) : ?>
          <option value="r:<?= e($r['id']) ?>"<?= 'r:' . $r['id'] === $elegida ? ' selected' : '' ?>><?= e($r['titulo']) ?><?= $r['restringida'] ? ' (tiene algo que no comés)' : '' ?></option>
        <?php endforeach; ?>
      </optgroup>
    </select>
    <?php
};

$titulo = 'Plan de la semana';
require parcial('encabezado');
?>
<div class="encabezado-pagina plan-encabezado">
  <h1>Plan de la semana</h1>
  <img class="plan-encabezado__dibujo" src="<?= e(url('img/plan.svg')) ?>" alt="" width="72" height="72">
</div>
<p class="plan-ayuda">Elegí qué vas a cocinar cada día. En el almuerzo y la cena de hoy, Mi cocina te lo recuerda.</p>

<div class="plan" id="plan">
  <?php foreach ($dias as $n => $fecha) : ?>
    <?php $nombreDia = PlanSemanal::nombreDia($fecha, $hoy); ?>
    <section class="postit plan-dia plan-dia--<?= $n % 4 ?>" id="dia-<?= e($fecha) ?>" aria-labelledby="titulo-<?= e($fecha) ?>">
      <span class="postit__chinche" aria-hidden="true"></span>
      <h2 id="titulo-<?= e($fecha) ?>"><?= e($nombreDia) ?>
        <?php if ($n < 2) : ?><small><?= e(PlanSemanal::diaConNumero($fecha)) ?></small><?php endif; ?>
      </h2>
      <?php foreach (PlanSemanal::MOMENTOS as $momento => $nombreMomento) : ?>
        <?php
        $comida = $plan[$fecha . '|' . $momento] ?? null;
        $idLugar = $fecha . '-' . $momento;
        $deQuien = PlanSemanal::lugar($fecha, $momento, $hoy);
        ?>
        <div class="plan-comida<?= $comida === null ? ' plan-comida--vacia' : '' ?>" data-fecha="<?= e($fecha) ?>" data-momento="<?= e($momento) ?>">
          <h3 class="plan-comida__momento"><?= e($nombreMomento) ?></h3>
          <?php if ($comida !== null) : ?>
            <?php
            $enlace = $comida['receta_id'] !== null
                ? url('app/receta.php?id=' . $comida['receta_id'] . '&porciones=' . $comida['porciones'])
                : url('app/receta-ia.php?id=' . $comida['receta_ia_id']);
            ?>
            <p class="plan-comida__receta">
              <a href="<?= e($enlace) ?>"><?= e($comida['titulo']) ?></a>
              <small>
                <?= e($comida['porciones']) ?> porci<?= $comida['porciones'] === 1 ? 'ón' : 'ones' ?><?= $comida['minutos'] !== null ? ' · ' . e($comida['minutos']) . ' min' : ' · de IA' ?>
              </small>
            </p>
          <?php endif; ?>
          <?php /* Sin receta, el formulario a la vista; con receta, adentro de "Cambiar" */ ?>
          <?= $comida !== null ? '<details class="plan-comida__cambiar"><summary>Cambiar</summary>' : '' ?>
          <form class="plan-comida__form" method="post" action="<?= e(url('app/plan.php')) ?>" data-sonido="sticker-pega">
            <?= Sesion::campoCsrf() ?>
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
            <input type="hidden" name="momento" value="<?= e($momento) ?>">
            <label class="solo-lectores" for="receta-<?= e($idLugar) ?>">Receta para <?= e($deQuien) ?></label>
            <?php $desplegable('receta-' . $idLugar, $comida === null ? '' : PlanSemanal::valorOpcion($comida['receta_id'], $comida['receta_ia_id'])); ?>
            <div class="plan-comida__fila">
              <label for="porciones-<?= e($idLugar) ?>">Porciones</label>
              <input id="porciones-<?= e($idLugar) ?>" name="porciones" type="number" min="<?= Porciones::MINIMO ?>" max="<?= Porciones::MAXIMO ?>"
                     inputmode="numeric" value="<?= $comida !== null ? e($comida['porciones']) : '' ?>" placeholder="las de la receta">
              <button class="boton boton--chico" type="submit">Guardar<span class="solo-lectores"> <?= e($deQuien) ?></span></button>
            </div>
          </form>
          <?php if ($comida !== null) : ?>
            </details>
            <form class="plan-comida__quitar" method="post" action="<?= e(url('app/plan.php')) ?>" data-sonido="sticker-arranca">
              <?= Sesion::campoCsrf() ?>
              <input type="hidden" name="accion" value="quitar">
              <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
              <input type="hidden" name="momento" value="<?= e($momento) ?>">
              <button class="boton boton--secundario boton--chico" type="submit">Quitar<span class="solo-lectores"> <?= e($deQuien) ?></span></button>
            </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>
<?php require parcial('pie'); ?>
