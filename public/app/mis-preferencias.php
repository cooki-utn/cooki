<?php

/**
 * Mis preferencias de cocina (incremento 2 · P1 + P3):
 *   POST accion=agregar  lista=basicos|restricciones  ingrediente=<nombre>  → lo suma a la lista
 *   POST accion=quitar   lista=basicos|restricciones  id=<ingrediente>      → lo saca
 *   POST accion=favorita receta=<id> volver=<ruta>                         → marca o desmarca con ♥
 * "Siempre tengo": cuenta como disponible en todas las recetas y no se descuenta al cocinar.
 * "No puedo comer": las recetas que lo usan no se recomiendan y se avisan en el catálogo.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Repositorios\IngredienteRepositorio;
use Cooki\Repositorios\PreferenciasRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();
$db = Db::conexion();
$preferencias = new PreferenciasRepositorio($db);
$ingredientes = new IngredienteRepositorio($db);
$idUsuario = Sesion::idUsuario();

if (esPost()) {
    Sesion::verificarCsrf();
    $accion = campo('accion');
    $lista = campo('lista');

    if ($accion === 'favorita') {
        $queda = $preferencias->alternarFavorita($idUsuario, (int) ($_POST['receta'] ?? 0));
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['favorita' => $queda]);
            exit;
        }
        // Vuelve a la página desde la que se marcó (solo rutas de este sitio)
        $volver = campo('volver');
        if (str_starts_with($volver, '/') && !str_starts_with($volver, '//') && !str_contains($volver, '\\')) {
            header('Location: ' . $volver);
            exit;
        }
        redirigir('app/mis-preferencias.php#favoritas');
    }

    if (isset(PreferenciasRepositorio::LISTAS[$lista])) {
        if ($accion === 'agregar') {
            $nombre = campo('ingrediente');
            $idIngrediente = $nombre === '' ? null : $ingredientes->idPorNombre($nombre, $idUsuario);
            if ($idIngrediente === null) {
                Sesion::avisar('error', 'No encontramos "' . $nombre . '" en la lista de ingredientes. Elegilo de la lista.');
            } else {
                $preferencias->agregar($idUsuario, $lista, $idIngrediente);
            }
        } elseif ($accion === 'quitar') {
            $preferencias->quitar($idUsuario, $lista, (int) ($_POST['id'] ?? 0));
        }
    }
    redirigir('app/mis-preferencias.php#' . ($lista === 'restricciones' ? 'no-como' : 'siempre-tengo'));
}

$basicos = $preferencias->ingredientes($idUsuario, 'basicos');
$restricciones = $preferencias->ingredientes($idUsuario, 'restricciones');
$favoritas = (new RecetaRepositorio($db))->buscar(['favoritas' => true], $idUsuario);
// Para elegir: la lista general más los ingredientes propios del usuario
$opciones = array_column($ingredientes->todos($idUsuario), 'nombre');

$titulo = 'Mis preferencias';
require parcial('encabezado');

/** Una lista de ingredientes con su formulario para agregar y un botón para quitar cada uno. */
$bloque = function (string $lista, string $id, string $tituloBloque, string $explicacion, array $items, string $vacio): void {
    ?>
  <section class="tarjeta preferencia" id="<?= e($id) ?>">
    <?php /* Dibujos sueltos pegados en las esquinas de la hoja */ ?>
    <?php $sueltos = [[90, -9, 60, 12], [93, 84, 44, -10], [1, 85, 38, -14]]; ?>
    <?php require parcial('dibujos-sueltos'); ?>
    <h2><?= e($tituloBloque) ?></h2>
    <p class="texto-suave"><?= e($explicacion) ?></p>
    <?php if ($items === []) : ?>
      <p><?= e($vacio) ?></p>
    <?php else : ?>
      <ul class="chips">
        <?php foreach ($items as $item) : ?>
          <li class="chip">
            <img src="<?= e(iconoIngrediente($item['nombre'])) ?>" alt="" width="30" height="30">
            <span><?= e($item['nombre']) ?></span>
            <form method="post">
              <?= Sesion::campoCsrf() ?>
              <input type="hidden" name="accion" value="quitar">
              <input type="hidden" name="lista" value="<?= e($lista) ?>">
              <input type="hidden" name="id" value="<?= e($item['id']) ?>">
              <button class="chip__quitar" type="submit" title="Quitar">×<span class="solo-lectores"> Quitar <?= e($item['nombre']) ?></span></button>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <form class="preferencia__agregar" method="post">
      <?= Sesion::campoCsrf() ?>
      <input type="hidden" name="accion" value="agregar">
      <input type="hidden" name="lista" value="<?= e($lista) ?>">
      <label class="solo-lectores" for="ingrediente-<?= e($lista) ?>">Ingrediente</label>
      <input id="ingrediente-<?= e($lista) ?>" type="text" name="ingrediente" list="lista-ingredientes" required maxlength="80"
             placeholder="Escribí un ingrediente…" autocomplete="off">
      <button class="boton boton--chico" type="submit">Agregar</button>
    </form>
  </section>
    <?php
};
?>
<?= enlaceVolver('app/index.php', 'Volver a Mi cocina', [['app/perfil.php', 'Mi cuenta']]) ?>
<h1>Mis preferencias</h1>
<p class="texto-suave">Lo que elegís acá cambia las recomendaciones de Mi cocina y el catálogo de recetas.</p>

<datalist id="lista-ingredientes">
  <?php foreach ($opciones as $nombre) : ?>
    <option value="<?= e($nombre) ?>"></option>
  <?php endforeach; ?>
</datalist>

<div class="preferencias-cocina">
  <?php
  $bloque(
      'basicos',
      'siempre-tengo',
      'Siempre tengo',
      'Lo que nunca te falta, además de la sal, el aceite y la pimienta. Cuenta como que lo tenés en todas las recetas y no se descuenta al cocinar.',
      $basicos,
      'Todavía no agregaste nada.'
  );
  $bloque(
      'restricciones',
      'no-como',
      'No puedo comer',
      'Alergias o lo que no comés. Las recetas que lo usan no se recomiendan y en el catálogo se marcan con un aviso.',
      $restricciones,
      'No tenés restricciones cargadas.'
  );
  ?>
</div>

<?php /* La nota de la lista de compras: la casilla se guarda en app/preferencias.php (el campo oculto "nunca"
         va primero: si la casilla está tildada, PHP se queda con su valor) */ ?>
<section class="tarjeta preferencia" id="nota-lista">
  <h2>Lista de compras</h2>
  <form class="preferencia__nota-lista" method="post" action="<?= e(url('app/preferencias.php')) ?>">
    <?= Sesion::campoCsrf() ?>
    <input type="hidden" name="volver" value="<?= e(url('app/mis-preferencias.php#nota-lista')) ?>">
    <input type="hidden" name="avisar" value="1">
    <input type="hidden" name="nota_lista" value="nunca">
    <label class="preferencia__casilla">
      <input type="checkbox" name="nota_lista" value="abierta"<?= Sesion::notaLista() !== 'nunca' ? ' checked' : '' ?>>
      Mostrar mi lista de compras en Mi cocina y Mi despensa
    </label>
    <p class="texto-suave">Si la apagás, no aparece ni siquiera cerrada. La lista sigue en el imán <a href="<?= e(url('app/lista.php')) ?>">Lista</a>.</p>
    <button class="boton boton--chico" type="submit">Guardar</button>
  </form>
</section>

<section id="favoritas" aria-labelledby="titulo-favoritas">
  <h2 id="titulo-favoritas" class="titulo-mano">Mis favoritas ♥</h2>
  <?php if ($favoritas === []) : ?>
    <p class="vacio">Todavía no marcaste ninguna. En cada receta está el botón ♡ para guardarla acá.</p>
  <?php else : ?>
    <div class="grilla">
      <?php foreach ($favoritas as $receta) : ?>
        <?php require parcial('tarjeta-receta'); ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require parcial('pie'); ?>
