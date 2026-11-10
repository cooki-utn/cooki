<?php

/**
 * Plan semanal (incremento 3 · HU-35 a HU-37): los próximos 7 días, con almuerzo y cena, como notas en la heladera.
 *   plan.php                                       → la semana, a partir de hoy. En cada comida, los dibujos de los
 *                                                    ingredientes y qué falta, gastando la despensa en orden
 *                                                    (PlanSemanal::simular: lo que usa el almuerzo ya no está en la cena)
 *   POST accion=guardar fecha= momento= receta=r:12|ia:5 porciones=  → pone (o cambia) la receta de ese lugar.
 *                                                    Sin porciones, van las de la receta
 *   POST accion=quitar fecha= momento=             → deja el lugar vacío
 *   POST accion=armar [con_ia=1]                   → "Armá mi semana": completa los lugares vacíos (PlanSemanal::armar)
 *   POST accion=faltantes [fecha= momento=]        → lo que falta de toda la semana (o de esa comida), a la lista
 * Al guardar se borra lo planeado para días que ya pasaron (no hay tareas programadas).
 * Con el encabezado X-Requested-With: fetch responde JSON en lugar de redirigir.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Dominio\PlanSemanal;
use Cooki\Dominio\Porciones;
use Cooki\Repositorios\IaRepositorio;
use Cooki\Repositorios\ListaRepositorio;
use Cooki\Repositorios\PlanRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();

$db = Db::conexion();
$repo = new PlanRepositorio($db);
$usuarioId = Sesion::idUsuario();
$hoy = new DateTimeImmutable('today');
$dias = PlanSemanal::dias($hoy);
$ultimoDia = $dias[PlanSemanal::DIAS - 1];

if (esPost()) {
    Sesion::verificarCsrf();
    $porFetch = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    $fecha = campo('fecha');
    $momento = campo('momento');
    /** Responde: JSON si lo pidió JavaScript; si no, aviso y vuelta al día que se tocó. */
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
    $unLugar = $fecha !== '' || $momento !== '';
    if (
        (in_array($accion, ['guardar', 'quitar'], true) || ($accion === 'faltantes' && $unLugar))
        && !PlanSemanal::lugarValido($fecha, $momento, $hoy)
    ) {
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
    if ($accion === 'armar') {
        $repo->limpiar($usuarioId, $hoy->format('Y-m-d'));
        $porClave = [];
        foreach ($repo->comidas($usuarioId, $repo->semana($usuarioId, $dias[0], $ultimoDia)) as $comida) {
            $porClave[$comida['clave']] = $comida;
        }
        $lugares = [];
        foreach ($dias as $dia) {
            foreach (array_keys(PlanSemanal::MOMENTOS) as $cual) {
                $lugares[] = ['clave' => $dia . '|' . $cual, 'fecha' => $dia, 'comida' => $porClave[$dia . '|' . $cual] ?? null];
            }
        }
        $candidatas = $repo->candidatas($usuarioId, ($_POST['con_ia'] ?? '') === '1');
        $porId = array_column($candidatas, null, 'id');
        $elegidas = PlanSemanal::armar($lugares, $candidatas, $repo->despensa($usuarioId));
        foreach ($elegidas as $clave => $id) {
            [$dia, $cual] = explode('|', $clave);
            $opcion = PlanSemanal::opcion($id);
            $repo->guardar($usuarioId, $dia, $cual, $opcion['receta_id'], $opcion['receta_ia_id'], $porId[$id]['porciones_base']);
        }
        $vacios = count(array_filter($lugares, fn (array $l) => $l['comida'] === null));
        $responder(true, match (true) {
            $vacios === 0 => 'Ya tenías toda la semana planeada. Quitá alguna comida si querés que la elijamos nosotros.',
            count($elegidas) === 0 => 'No encontramos recetas para completar la semana.',
            count($elegidas) < $vacios => 'Completamos ' . count($elegidas) . ' comidas: no alcanzaron las recetas para todas.',
            default => 'Listo: completamos ' . count($elegidas) . ' comida' . (count($elegidas) === 1 ? '' : 's')
                . ', empezando por lo que vence.',
        }, ['completadas' => count($elegidas)]);
    }
    if ($accion === 'faltantes') {
        $plan = $repo->semana($usuarioId, $dias[0], $ultimoDia);
        $simulacion = PlanSemanal::simular($repo->comidas($usuarioId, $plan), $repo->despensa($usuarioId));
        $claves = $unLugar ? [$fecha . '|' . $momento] : array_keys($plan);
        if ($unLugar && !isset($plan[$claves[0]])) {
            $responder(false, 'En ' . $lugar . ' no hay ninguna receta.');
        }
        $lista = new ListaRepositorio($db);
        $agregados = 0;
        $db->beginTransaction();
        try {
            foreach ($claves as $clave) {
                $agregados += $lista->agregarFaltantes(
                    $usuarioId,
                    $simulacion[$clave]['faltantes'],
                    $plan[$clave]['receta_id'],
                    $plan[$clave]['receta_ia_id']
                );
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        $responder(true, match ($agregados) {
            0 => $unLugar ? 'No te falta nada para ' . $lugar . ' (o ya estaba en tu lista).'
                : 'No te falta nada para la semana (o ya estaba en tu lista).',
            1 => 'Agregamos 1 cosa a tu lista de compras.',
            default => "Agregamos {$agregados} cosas a tu lista de compras.",
        }, ['agregados' => $agregados]);
    }
    $responder(false, 'No entendimos qué querías hacer.');
}

$plan = $repo->semana($usuarioId, $dias[0], $ultimoDia);
// Qué tiene y qué le falta a cada comida, gastando la despensa en orden (de hoy en adelante)
$simulacion = PlanSemanal::simular($repo->comidas($usuarioId, $plan), $repo->despensa($usuarioId));
$enLista = array_flip((new ListaRepositorio($db))->ingredientesPendientes($usuarioId));
/** Lo que falta para una comida y todavía no está en la lista de compras. */
$sinAnotar = fn (array $estado): int => count(array_filter(
    $estado['faltantes'],
    fn (array $f) => $f['ingrediente_id'] === null || !isset($enLista[$f['ingrediente_id']])
));
$faltanSemana = array_sum(array_map($sinAnotar, $simulacion));
$vacios = PlanSemanal::DIAS * count(PlanSemanal::MOMENTOS) - count($plan);
// Opciones del desplegable: las recomendadas, las favoritas, las de IA guardadas y todo el recetario
$todas = $repo->recetasParaElegir($usuarioId);
$recomendadas = array_slice((new RecetaRepositorio($db))->recomendadas($usuarioId), 0, 8);
$favoritas = array_values(array_filter($todas, fn (array $r) => $r['favorita'] && !$r['restringida']));
$deIa = (new IaRepositorio($db))->guardadas($usuarioId);
// Dibujos de ingredientes para decorar: uno chico en cada nota y los grandes en el lugar que queda después del domingo
$decoracion = array_values(array_filter(
    array_map(fn (string $archivo) => basename($archivo, '.svg'), glob(dirname(__DIR__) . '/img/ingredientes/*.svg') ?: []),
    fn (string $nombre) => $nombre !== 'frasco'
));
shuffle($decoracion);

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

/** Un formulario de un solo botón (quitar, + lista): accion, el lugar y el botón. */
$botonLugar = function (string $accion, string $fecha, string $momento, string $clase, string $texto, string $paraLectores, string $sonido): void {
    ?>
    <form class="plan-comida__boton" method="post" action="<?= e(url('app/plan.php')) ?>" data-sonido="<?= e($sonido) ?>">
      <?= Sesion::campoCsrf() ?>
      <input type="hidden" name="accion" value="<?= e($accion) ?>">
      <input type="hidden" name="fecha" value="<?= e($fecha) ?>">
      <input type="hidden" name="momento" value="<?= e($momento) ?>">
      <button class="<?= e($clase) ?>" type="submit"><?= e($texto) ?><span class="solo-lectores"> <?= e($paraLectores) ?></span></button>
    </form>
    <?php
};

$titulo = 'Plan de la semana';
require parcial('encabezado');
?>
<div class="encabezado-pagina plan-encabezado">
  <h1>Plan de la semana</h1>
  <img class="plan-encabezado__dibujo" src="<?= e(url('img/plan.svg')) ?>" alt="" width="72" height="72">
</div>
<p class="plan-ayuda">Elegí qué vas a cocinar cada día. Te mostramos qué tenés y qué te falta, contando lo que usan
  las comidas de antes: si el almuerzo usa los huevos, para la cena ya no están.</p>

<div class="plan-acciones">
  <form class="plan-acciones__armar" method="post" action="<?= e(url('app/plan.php')) ?>" data-sonido="sticker-pega">
    <?= Sesion::campoCsrf() ?>
    <input type="hidden" name="accion" value="armar">
    <button class="boton" type="submit"<?= $vacios === 0 ? ' disabled' : '' ?>>Armá mi semana</button>
    <?php if ($deIa !== []) : ?>
      <label class="plan-acciones__ia"><input type="checkbox" name="con_ia" value="1"> Incluir mis recetas de IA</label>
    <?php endif; ?>
    <small class="plan-acciones__nota"><?= $vacios === 0
        ? 'Ya tenés toda la semana planeada.'
        : 'Completa ' . ($vacios === 1 ? 'la comida vacía' : 'las ' . $vacios . ' comidas vacías') . ' con lo que tenés, empezando por lo que vence. No usa la IA.' ?></small>
  </form>
  <?php if ($faltanSemana > 0) : ?>
    <form class="plan-acciones__lista" method="post" action="<?= e(url('app/plan.php')) ?>" data-sonido="sticker-pega">
      <?= Sesion::campoCsrf() ?>
      <input type="hidden" name="accion" value="faltantes">
      <button class="boton boton--secundario" type="submit">Agregar lo que falta para la semana a la lista</button>
      <small class="plan-acciones__nota"><a href="<?= e(url('app/lista.php')) ?>">Ver mi lista de compras →</a></small>
    </form>
  <?php elseif ($plan !== []) : ?>
    <p class="plan-acciones__todo"><span class="etiqueta etiqueta--exito"><?= array_filter(array_column($simulacion, 'tiene_todo'), fn ($t) => !$t) === []
        ? 'Tenés todo para la semana' : 'Lo que falta para la semana ya está en tu lista' ?></span>
      <a href="<?= e(url('app/lista.php')) ?>">Ver mi lista de compras →</a></p>
  <?php endif; ?>
</div>

<div class="plan" id="plan">
  <?php foreach ($dias as $n => $fecha) : ?>
    <?php $nombreDia = PlanSemanal::nombreDia($fecha, $hoy); ?>
    <section class="postit plan-dia plan-dia--<?= $n % 4 ?>" id="dia-<?= e($fecha) ?>" aria-labelledby="titulo-<?= e($fecha) ?>">
      <span class="postit__chinche" aria-hidden="true"></span>
      <?php if (isset($decoracion[$n])) : ?>
        <img class="plan-dia__sticker plan-dia__sticker--<?= $n % 2 === 0 ? 'derecha' : 'izquierda' ?>"
             src="<?= e(url('img/ingredientes/' . $decoracion[$n] . '.svg')) ?>" alt="" width="46" height="46" aria-hidden="true">
      <?php endif; ?>
      <h2 id="titulo-<?= e($fecha) ?>"><?= e($nombreDia) ?>
        <?php if ($n < 2) : ?><small><?= e(PlanSemanal::diaConNumero($fecha)) ?></small><?php endif; ?>
      </h2>
      <?php foreach (PlanSemanal::MOMENTOS as $momento => $nombreMomento) : ?>
        <?php
        $clave = $fecha . '|' . $momento;
        $comida = $plan[$clave] ?? null;
        $estado = $simulacion[$clave] ?? null;
        $idLugar = $fecha . '-' . $momento;
        $deQuien = PlanSemanal::lugar($fecha, $momento, $hoy);
        ?>
        <div class="plan-comida<?= $comida === null ? ' plan-comida--vacia' : '' ?>" data-fecha="<?= e($fecha) ?>" data-momento="<?= e($momento) ?>">
          <h3 class="plan-comida__momento"><?= e($nombreMomento) ?></h3>
          <?php if ($comida !== null && $estado !== null) : ?>
            <?php
            $enlace = $comida['receta_id'] !== null
                ? url('app/receta.php?id=' . $comida['receta_id'] . '&porciones=' . $comida['porciones'])
                : url('app/receta-ia.php?id=' . $comida['receta_ia_id']);
            $dibujos = array_slice($estado['ingredientes'], 0, 6);
            ?>
            <p class="plan-comida__receta">
              <a href="<?= e($enlace) ?>"><?= e($comida['titulo']) ?></a>
              <small>
                <?= e($comida['porciones']) ?> porci<?= $comida['porciones'] === 1 ? 'ón' : 'ones' ?><?= $comida['minutos'] !== null ? ' · ' . e($comida['minutos']) . ' min' : ' · de IA' ?>
              </small>
            </p>
            <?php if ($dibujos !== []) : ?>
              <ul class="mini-ingredientes plan-comida__ingredientes" aria-label="Ingredientes">
                <?php foreach ($dibujos as $ing) : ?>
                  <?php $tengo = $ing['estado'] === 'tengo'; ?>
                  <li class="<?= $tengo ? 'tengo' : 'falta' ?>">
                    <img src="<?= e(iconoIngrediente($ing['nombre'], $ing['ingrediente_id'] === null)) ?>" width="34" height="34"
                         alt="<?= e($ing['nombre'] . ($tengo ? '' : ' (te falta)')) ?>" title="<?= e($ing['nombre']) ?>">
                  </li>
                <?php endforeach; ?>
                <?php if (count($estado['ingredientes']) > count($dibujos)) : ?>
                  <li class="mini-ingredientes__mas">+<?= count($estado['ingredientes']) - count($dibujos) ?></li>
                <?php endif; ?>
              </ul>
            <?php endif; ?>
            <?php if ($estado['tiene_todo']) : ?>
              <p class="plan-comida__estado"><span class="etiqueta etiqueta--exito">Tenés todo</span></p>
            <?php else : ?>
              <p class="plan-comida__estado plan-comida__estado--falta">
                <strong>Falta:</strong>
                <?php foreach ($estado['ingredientes'] as $ing) : ?>
                  <?php if ($ing['estado'] === 'tengo') {
                      continue;
                  } ?>
                  <span class="plan-comida__falta"><?= e(mb_strtolower($ing['nombre'])) ?><?php
                    $porque = match ($ing['estado']) {
                        'usado' => $ing['lo_usa'] !== null ? 'lo usa ' . mb_strtolower($ing['lo_usa']) : 'ya lo usaste',
                        'vence' => 'vence antes',
                        default => null,
                    };
                    if ($ing['ingrediente_id'] !== null && isset($enLista[$ing['ingrediente_id']])) {
                        $porque = ($porque === null ? '' : $porque . ', ') . 'ya está en tu lista';
                    }
                    ?><?= $porque !== null ? ' <small>(' . e($porque) . ')</small>' : '' ?></span>
                <?php endforeach; ?>
              </p>
            <?php endif; ?>
          <?php endif; ?>
          <div class="plan-comida__acciones">
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
              <?php if ($estado !== null && !$estado['tiene_todo'] && $sinAnotar($estado) === 0) : ?>
                <span class="etiqueta plan-comida__en-lista">Ya está en tu lista</span>
              <?php elseif ($estado !== null && !$estado['tiene_todo']) : ?>
                <?php $botonLugar('faltantes', $fecha, $momento, 'boton boton--chico boton--agregar', '+ lista', 'lo que falta para ' . $deQuien, 'sticker-pega'); ?>
              <?php endif; ?>
              <?php $botonLugar('quitar', $fecha, $momento, 'boton boton--secundario boton--chico', 'Quitar', $deQuien, 'sticker-arranca'); ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
  <?php /* El lugar que queda vacío después del domingo: stickers grandes (decorativos) */ ?>
  <div class="plan-relleno" aria-hidden="true">
    <?php foreach (array_slice($decoracion, PlanSemanal::DIAS, 5) as $n => $dibujo) : ?>
      <img class="plan-relleno__dibujo" src="<?= e(url('img/ingredientes/' . $dibujo . '.svg')) ?>" alt="" width="110" height="110"
           loading="lazy" style="--r: <?= [-8, 6, -3, 9, -6][$n] ?>deg; --d: <?= $n * 0.4 ?>s">
    <?php endforeach; ?>
  </div>
</div>
<?php require parcial('pie'); ?>
