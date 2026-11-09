<?php

/**
 * Mi cocina: la página a la que llega el usuario al ingresar.
 * Muestra lo que está por vencer, un selector con los ingredientes que tiene
 * (tocás algunos y aparecen las recetas que los usan) y las recomendaciones, ordenadas por puntaje
 * (Dominio\Puntaje) y agrupadas en "Podés hacerla ahora", "Te falta 1" y "Casi".
 *   index.php?ing[]=15&ing[]=1&modo=todos|alguno → recetas con esos ingredientes
 * Con JavaScript (cocina.js) el resultado del selector cambia sin recargar la página.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Dominio\Puntaje;
use Cooki\Repositorios\CocinaRepositorio;
use Cooki\Repositorios\DespensaRepositorio;
use Cooki\Repositorios\ListaRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();

$db = Db::conexion();
$usuario = Sesion::usuario();
$despensa = new DespensaRepositorio($db);
$recetas = new RecetaRepositorio($db);
// Lo vencido no va en "Usalo pronto" (ya no se puede usar): se cuenta aparte, con un enlace a la despensa
$porVencer = $despensa->porVencer($usuario['id'], 7);
$vencidos = count(array_filter($porVencer, fn ($item) => (int) $item['dias'] < 0));
$porVencer = array_values(array_filter($porVencer, fn ($item) => (int) $item['dias'] >= 0));
$cocinando = (new CocinaRepositorio($db))->enCurso($usuario['id']);
$notaLista = (new ListaRepositorio($db))->faltaComprar($usuario['id']);   // la nota de la lista de compras
// Recomendaciones por grupo (hasta 6 en cada uno, las de más puntaje primero)
$porGrupo = array_fill_keys(array_keys(Puntaje::GRUPOS), []);
foreach ($recetas->recomendadas($usuario['id']) as $recomendada) {
    if (count($porGrupo[$recomendada['grupo']]) < 6) {
        $porGrupo[$recomendada['grupo']][] = $recomendada;
    }
}
$hayRecomendaciones = array_filter($porGrupo) !== [];

// Selector de ingredientes: solo se aceptan ingredientes que el usuario tiene
$disponibles = $despensa->disponibles($usuario['id']);
$pedidos = array_map('intval', (array) ($_GET['ing'] ?? []));
$elegidos = array_values(array_intersect($pedidos, array_map('intval', array_column($disponibles, 'id'))));
$modo = ($_GET['modo'] ?? '') === 'alguno' ? 'alguno' : 'todos';
$conElegidos = $elegidos === [] ? [] : $recetas->buscar(['ingredientes' => $elegidos, 'modo' => $modo], $usuario['id']);
$nombresElegidos = array_column(
    array_filter($disponibles, fn ($ing) => in_array((int) $ing['id'], $elegidos, true)),
    'nombre'
);

$titulo = 'Mi cocina';
require parcial('encabezado');
?>
<h1>Hola, <?= e(explode(' ', $usuario['nombre'])[0]) ?></h1>

<?php if ($cocinando !== null) : ?>
  <?php /* Modo cocina en curso: el progreso está guardado en la cuenta, se sigue desde donde quedó */ ?>
  <p class="aviso aviso--info cocinando">
    Estás cocinando <strong><?= e($cocinando['titulo']) ?></strong>
    (<?= e($cocinando['hechos']) ?> de <?= e($cocinando['pasos']) ?> pasos).
    <a href="<?= e(url('app/cocinar.php?sesion=' . $cocinando['id'])) ?>">Seguir cocinando</a>
  </p>
<?php endif; ?>

<?php /* En la disposición panorámica (computadora), lateral y principal quedan lado a lado */ ?>
<div class="cocina">
<div class="cocina__lateral">

<?php /* "Usalo pronto" y, al lado, la nota de la lista de compras (se puede cerrar o apagar en Mis preferencias) */ ?>
<div class="cocina__notas">
<section class="postit">
  <span class="postit__chinche" aria-hidden="true"></span>
  <div class="postit__titulo">
    <h2>Usalo pronto</h2>
    <?php if ($vencidos > 0) : ?>
      <a class="aviso-vencidos" href="<?= e(url('app/despensa.php?estado=vencidos')) ?>">
        <strong><?= $vencidos ?> vencido<?= $vencidos === 1 ? '' : 's' ?></strong>
        <span>Quitalo<?= $vencidos === 1 ? '' : 's' ?> o cambiá la fecha en tu&nbsp;despensa&nbsp;→</span>
      </a>
    <?php endif; ?>
  </div>
  <?php if ($porVencer === []) : ?>
    <p class="texto-suave">Nada vence en los próximos 7 días. <a href="<?= e(url('app/despensa.php')) ?>">Ver mi despensa</a></p>
  <?php else : ?>
    <ul class="lista-vencimientos">
      <?php foreach ($porVencer as $item) : ?>
        <?php [$texto, $estilo] = DespensaRepositorio::estadoVencimiento((int) $item['dias']); ?>
        <li>
          <img class="icono-chico" src="<?= e(iconoIngrediente($item['nombre'], (bool) $item['propio'])) ?>" alt="" width="32" height="32">
          <strong><?= e($item['nombre']) ?></strong>
          <span class="etiqueta etiqueta--<?= e($estilo) ?>"><?= e($texto) ?></span>
          <a href="<?= e(url('app/recetas.php?q=' . urlencode($item['nombre']))) ?>">Recetas con <?= e(mb_strtolower($item['nombre'])) ?></a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</section>
<?php require parcial('nota-lista'); ?>
</div>

<section class="selector" aria-labelledby="titulo-selector">
  <h2 id="titulo-selector">¿Qué querés usar?</h2>
  <?php if ($disponibles === []) : ?>
    <p class="texto-suave">Cuando cargues ingredientes en <a href="<?= e(url('app/despensa.php')) ?>">tu despensa</a>, vas a poder tocarlos acá para ver qué recetas los usan.</p>
  <?php else : ?>
    <p class="texto-suave">Tocá ingredientes de tu heladera y te mostramos las recetas que los usan.</p>
    <form class="selector__form" method="get" action="<?= e(url('app/index.php')) ?>#titulo-selector" id="form-selector">
      <div class="selector__stickers">
        <?php foreach ($disponibles as $ing) : ?>
          <?php $urgente = $ing['dias'] !== null && (int) $ing['dias'] <= 1; ?>
          <label class="selector__sticker<?= $urgente ? ' selector__sticker--ya' : '' ?>">
            <input type="checkbox" name="ing[]" value="<?= e($ing['id']) ?>"<?= in_array((int) $ing['id'], $elegidos, true) ? ' checked' : '' ?>>
            <img src="<?= e(iconoIngrediente($ing['nombre'], (bool) $ing['propio'])) ?>" alt="" width="64" height="64">
            <span><?= e($ing['nombre']) ?><?= $urgente ? '<b class="solo-lectores"> (vence ' . ((int) $ing['dias'] === 0 ? 'hoy' : 'mañana') . ')</b>' : '' ?></span>
            <?php if ($urgente) : ?><i class="ya" aria-hidden="true">¡ya!</i><?php endif; ?>
          </label>
        <?php endforeach; ?>
      </div>
      <fieldset class="selector__modo">
        <legend>Recetas que usen</legend>
        <label><input type="radio" name="modo" value="todos"<?= $modo === 'todos' ? ' checked' : '' ?>> todos los que elegí</label>
        <label><input type="radio" name="modo" value="alguno"<?= $modo === 'alguno' ? ' checked' : '' ?>> alguno de los que elegí</label>
      </fieldset>
      <div class="acciones selector__botones">
        <button class="boton" type="submit">Ver recetas</button>
        <?php if ($elegidos !== []) : ?>
          <a class="boton boton--secundario" href="<?= e(url('app/index.php')) ?>#titulo-selector">Limpiar</a>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>
</section>

</div>

<div class="cocina__principal">
  <?php if ($disponibles !== []) : ?>
    <div id="resultado-selector" aria-live="polite">
      <?php if ($elegidos === []) : ?>
        <p class="selector__ayuda">Todavía no elegiste nada.</p>
      <?php elseif ($conElegidos === []) : ?>
        <p class="selector__ayuda">Ninguna receta usa <?= e(implode($modo === 'todos' ? ' y ' : ' o ', $nombresElegidos)) ?><?= $modo === 'todos' && count($elegidos) > 1 ? ' a la vez. Probá con «alguno».' : '.' ?></p>
      <?php else : ?>
        <p class="selector__ayuda"><?= count($conElegidos) ?> receta<?= count($conElegidos) === 1 ? '' : 's' ?> con <?= e(implode($modo === 'todos' ? ' y ' : ' o ', $nombresElegidos)) ?>:</p>
        <div class="grilla">
          <?php foreach ($conElegidos as $receta) : ?>
            <?php require parcial('tarjeta-receta'); ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="encabezado-pagina">
    <h2>Podés cocinar</h2>
    <a href="<?= e(url('app/recetas.php?orden=recomendadas')) ?>">Ver todas las recetas →</a>
  </div>
  <?php if (!$hayRecomendaciones) : ?>
    <p class="vacio">Todavía no hay sugerencias. <a href="<?= e(url('app/despensa.php')) ?>">Cargá lo que tenés en tu despensa</a> y te mostramos qué cocinar.</p>
  <?php else : ?>
    <?php /* Primero lo que se puede hacer ya; después lo que está a uno o pocos ingredientes */ ?>
    <?php foreach (Puntaje::GRUPOS as $clave => $nombreGrupo) : ?>
      <?php if ($porGrupo[$clave] !== []) : ?>
        <section class="grupo-recetas grupo-recetas--<?= e($clave) ?>" aria-labelledby="grupo-<?= e($clave) ?>">
          <h3 class="grupo-recetas__titulo" id="grupo-<?= e($clave) ?>"><?= e($nombreGrupo) ?></h3>
          <div class="grilla">
            <?php foreach ($porGrupo[$clave] as $receta) : ?>
              <?php require parcial('tarjeta-receta'); ?>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
</div>
<script src="<?= e(recurso('js/cocina.js')) ?>" defer></script>
<?php require parcial('pie'); ?>
