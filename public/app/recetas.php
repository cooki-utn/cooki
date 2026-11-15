<?php

/**
 * Catálogo de recetas con buscador (contenido principal para el usuario general).
 * Busca por título, descripción o ingrediente, y filtra por dificultad, etiqueta
 * y "solo lo que puedo hacer con mi despensa". Se ordena alfabéticamente, por recomendación
 * (puntaje: primero lo que se puede hacer y usa lo que vence) o por tiempo.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Dominio\Paginacion;
use Cooki\Dominio\Tipos;
use Cooki\Repositorios\PreferenciasRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();

$db = Db::conexion();
$repo = new RecetaRepositorio($db);
// La dieta de la bienvenida: el filtro viene puesto y se puede sacar. "dieta_enviada" dice que el formulario
// ya se mandó (si no, cualquier enlace con ?orden=… la apagaría sin querer).
$misDietas = (new PreferenciasRepositorio($db))->perfil(Sesion::idUsuario())['dietas'];
$filtros = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'dificultad' => (int) ($_GET['dificultad'] ?? 0),
    'etiqueta' => (int) ($_GET['etiqueta'] ?? 0),
    'puedo' => isset($_GET['puedo']),
    'favoritas' => isset($_GET['favoritas']),
    'dieta' => $misDietas !== [] && (isset($_GET['dieta']) || !isset($_GET['dieta_enviada'])),
];
$recetas = $repo->buscar($filtros, Sesion::idUsuario());
$ordenes = ['az' => 'A a la Z', 'recomendadas' => 'Recomendadas para mí', 'rapidas' => 'Más rápidas primero'];
$orden = isset($ordenes[$_GET['orden'] ?? '']) ? $_GET['orden'] : 'az';
if ($orden === 'recomendadas') {
    // Las recomendadas (con su puntaje y avisos) primero, en su orden; después el resto, por lo que falta
    $puntajes = [];
    foreach ($repo->recomendadas(Sesion::idUsuario(), 1000) as $posicion => $fila) {
        $puntajes[(int) $fila['id']] = $fila + ['posicion' => $posicion];
    }
    $recetas = array_map(fn ($r) => ($puntajes[(int) $r['id']] ?? []) + $r, $recetas);
    usort($recetas, fn ($a, $b) => [$a['posicion'] ?? PHP_INT_MAX, $a['total'] - $a['tengo'], $a['titulo']]
        <=> [$b['posicion'] ?? PHP_INT_MAX, $b['total'] - $b['tengo'], $b['titulo']]);
} elseif ($orden === 'rapidas') {
    usort($recetas, fn ($a, $b) => [(int) $a['minutos'], $a['titulo']] <=> [(int) $b['minutos'], $b['titulo']]);
}
// De a 12 recetas por página (recetas.php?pagina=2), conservando la búsqueda y los filtros
$paginacion = new Paginacion(count($recetas), 12, (int) ($_GET['pagina'] ?? 1));
$enlace = fn (int $pagina): string => enlacePagina('app/recetas.php', $pagina);
$etiquetas = $repo->etiquetas();
$hayFiltros = $filtros['q'] !== '' || $filtros['dificultad'] || $filtros['etiqueta'] || $filtros['puedo']
    || $filtros['favoritas'] || $orden !== 'az';

$titulo = 'Recetas';
require parcial('encabezado');
?>
<?php /* En la disposición panorámica, a la derecha del título queda una franja vacía: lleva dibujos sueltos */ ?>
<div class="titulo-con-sueltos titulo-con-sueltos--panoramica">
  <h1>Recetas</h1>
  <?php $sueltos = [[36, 6, 66, -8], [56, 30, 58, 10], [75, 0, 70, 6], [91, 12, 60, -12]]; ?>
  <?php require parcial('dibujos-sueltos'); ?>
</div>

<?php /* En la disposición panorámica, los filtros quedan en una columna a la izquierda */ ?>
<div class="catalogo">
<form class="tarjeta buscador" method="get" role="search">
  <div class="campo">
    <label for="q">Buscar receta o ingrediente</label>
    <input id="q" name="q" type="search" placeholder="Ej.: tarta, pollo, zapallo" value="<?= e($filtros['q']) ?>">
  </div>
  <?php
  /* Dificultad, tipo y orden: listas desplegables con dibujo (desplegable() en src/ayudas.php), todas iguales */
  $dibujo = fn (string $ruta): string => '<img src="' . e(url($ruta)) . '" alt="" width="30" height="30">';
  $opcionesDificultad = [0 => ['Todas', '<span class="semaforo-todas"><i></i><i></i><i></i></span>']];
  foreach (RecetaRepositorio::DIFICULTADES as $valor => $texto) {
      $opcionesDificultad[$valor] = [$texto, '<span class="dificultad dificultad--' . $valor . '"><span class="dificultad__luz"></span></span>'];
  }
  $opcionesTipo = [0 => ['Todos', $dibujo('img/tipos/todo.svg')]];
  foreach ($etiquetas as $etiqueta) {
      $clave = $etiqueta['nombre'];
      $opcionesTipo[(int) $etiqueta['id']] = [
          Tipos::nombre($clave) . ($clave === 'sin_tacc' ? ' (apta celíacos)' : ''),
          $dibujo(Tipos::dibujo($clave) ?? 'img/tipos/todo.svg'),
      ];
  }
  $dibujosOrden = ['az' => 'img/menu/recetas.svg', 'recomendadas' => 'img/menu/horno.svg', 'rapidas' => 'img/tipos/rapida.svg'];
  $opcionesOrden = [];
  foreach ($ordenes as $valor => $texto) {
      $opcionesOrden[$valor] = [$texto, $dibujo($dibujosOrden[$valor])];
  }
  ?>
  <div class="campo"><?= desplegable('dificultad', 'Dificultad', $opcionesDificultad, $filtros['dificultad']) ?></div>
  <div class="campo"><?= desplegable('etiqueta', 'Tipo', $opcionesTipo, $filtros['etiqueta']) ?></div>
  <div class="campo"><?= desplegable('orden', 'Ordenar', $opcionesOrden, $orden) ?></div>
  <div class="campo casillas">
    <label><input type="checkbox" name="puedo" value="1"<?= $filtros['puedo'] ? ' checked' : '' ?>> Solo las que puedo hacer ya</label>
    <label><input type="checkbox" name="favoritas" value="1"<?= $filtros['favoritas'] ? ' checked' : '' ?>> Solo mis favoritas ♥</label>
    <?php if ($misDietas !== []) : ?>
      <input type="hidden" name="dieta_enviada" value="1">
      <label><input type="checkbox" name="dieta" value="1"<?= $filtros['dieta'] ? ' checked' : '' ?>>
        Solo de mi dieta (<?= e(implode(', ', array_map([Tipos::class, 'nombre'], Tipos::ordenar($misDietas)))) ?>)</label>
    <?php endif; ?>
  </div>
  <button class="boton" type="submit">Buscar</button>
  <?php if ($hayFiltros) : ?>
    <a class="boton boton--secundario" href="<?= e(url('app/recetas.php')) ?>">Limpiar</a>
  <?php endif; ?>
  <?php /* Lo que sobra de la línea del botón (la hoja ocupa todo el ancho) se llena con dibujos sueltos */ ?>
  <div class="buscador__relleno">
    <?php $sueltos = [[14, -10, 50, -10], [50, 18, 38, 12], [80, -22, 58, 6]]; ?>
    <?php require parcial('dibujos-sueltos'); ?>
  </div>
</form>

<div class="catalogo__resultados">
<p class="texto-suave" role="status"><?= count($recetas) ?> receta<?= count($recetas) === 1 ? '' : 's' ?><?= $hayFiltros ? ' encontradas' : '' ?></p>

<?php if ($recetas === []) : ?>
  <p class="vacio">No encontramos recetas con esos filtros. Probá con otra palabra o sacá algún filtro.</p>
<?php else : ?>
  <div class="grilla">
    <?php foreach ($paginacion->cortar($recetas) as $receta) : ?>
      <?php require parcial('tarjeta-receta'); ?>
    <?php endforeach; ?>
  </div>
  <?php require parcial('paginacion'); ?>
<?php endif; ?>
</div>
</div>
<script src="<?= e(recurso('js/desplegable.js')) ?>" defer></script>
<?php require parcial('pie'); ?>
