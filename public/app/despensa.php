<?php

/**
 * Mi despensa: ABM de los ingredientes que tiene el usuario.
 *   despensa.php               → formulario de alta + listado
 *   despensa.php?editar=ID     → formulario de modificación
 * Con JavaScript (despensa.js) el formulario se abre como una nota despegada de la heladera
 * (un <dialog>) y Editar lo completa sin recargar; sin JavaScript funciona igual que siempre.
 *   despensa.php?ubicacion=... → filtra por alacena, heladera o freezer
 *   despensa.php?estado=...    → filtra por bien, por_vencer, vencidos o agotados (los números del contador)
 *   POST accion=guardar|borrar
 * El ingrediente se elige con un buscador de stickers (despensa.js); si no está en la lista,
 * se agrega como ingrediente propio (nombre_nuevo, categoria_nueva, unidad_nueva) con el dibujo del frasco.
 * Lo agotado o con poco tiene "+ lista" para anotarlo en la lista de compras (app/lista.php).
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Repositorios\DespensaRepositorio;
use Cooki\Repositorios\IngredienteRepositorio;
use Cooki\Repositorios\ListaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();

$db = Db::conexion();
$repo = new DespensaRepositorio($db);
$ingredientes = new IngredienteRepositorio($db);
$catalogo = $ingredientes->todos(Sesion::idUsuario());
$categorias = $ingredientes->categorias();
$unidadesNuevo = ['u' => 'unidades', 'g' => 'gramos', 'ml' => 'mililitros'];
// Ingrediente nuevo (propio): arranca en la categoría Almacén y en unidades
$almacen = array_values(array_filter($categorias, fn ($c) => $c['nombre'] === 'Almacén'))[0]['id'] ?? $categorias[0]['id'];
$nuevo = ['nombre' => '', 'categoria_id' => (int) $almacen, 'unidad' => 'u'];
$usuarioId = Sesion::idUsuario();
$errores = [];
$vacio = ['id' => 0, 'ingrediente_id' => 0, 'nivel' => 2, 'cantidad' => '', 'fecha_vencimiento' => '', 'ubicacion' => 'alacena'];
$unidades = array_column($catalogo, 'unidad_default', 'id');
$item = $vacio;

if (esPost()) {
    Sesion::verificarCsrf();
    $id = (int) ($_POST['id'] ?? 0);

    if (campo('accion') === 'borrar') {
        if ($repo->borrar($usuarioId, $id)) {
            Sesion::avisar('exito', 'Se quitó de tu despensa.');
        } else {
            Sesion::avisar('error', 'Ese ingrediente no está en tu despensa.');
        }
        redirigir('app/despensa.php');
    }

    $item = [
        'id' => $id,
        'ingrediente_id' => (int) ($_POST['ingrediente_id'] ?? 0),
        'nivel' => (int) ($_POST['nivel'] ?? 2),
        'cantidad' => str_replace(',', '.', campo('cantidad')),
        'fecha_vencimiento' => campo('fecha_vencimiento'),
        'ubicacion' => campo('ubicacion'),
    ];
    $nuevo = [
        'nombre' => trim((string) preg_replace('/\s+/u', ' ', campo('nombre_nuevo'))),
        'categoria_id' => (int) ($_POST['categoria_nueva'] ?? 0),
        'unidad' => campo('unidad_nueva'),
    ];
    $esNuevo = $item['ingrediente_id'] === 0 && $nuevo['nombre'] !== '';
    if ($esNuevo) {
        if (mb_strlen($nuevo['nombre']) < 2 || mb_strlen($nuevo['nombre']) > 60) {
            $errores['nombre_nuevo'] = 'El nombre tiene que tener entre 2 y 60 letras.';
        }
        if (!in_array($nuevo['categoria_id'], array_map('intval', array_column($categorias, 'id')), true)) {
            $errores['nombre_nuevo'] = 'Elegí de qué tipo es.';
        }
        if (!isset($unidadesNuevo[$nuevo['unidad']])) {
            $errores['nombre_nuevo'] = 'Elegí cómo lo medís.';
        }
    } elseif (!in_array($item['ingrediente_id'], array_map('intval', array_column($catalogo, 'id')), true)) {
        $errores['ingrediente_id'] = 'Elegí un ingrediente de la lista, o agregá uno nuevo si no está.';
    }
    if (!isset(DespensaRepositorio::NIVELES[$item['nivel']])) {
        $errores['nivel'] = 'Elegí cuánto tenés.';
    }
    if ($item['cantidad'] !== '' && (!is_numeric($item['cantidad']) || $item['cantidad'] < 0 || $item['cantidad'] > 99999)) {
        $errores['cantidad'] = 'Poné un número entre 0 y 99999, o dejalo vacío.';
    } elseif ($item['cantidad'] !== '') {
        // Con cantidad exacta, el nivel acompaña: 0 = agotado; si hay algo, no puede quedar "agotado"
        if ((float) $item['cantidad'] === 0.0) {
            $item['nivel'] = 0;
        } elseif ($item['nivel'] === 0) {
            $item['nivel'] = 2;
        }
    }
    if (!isset(DespensaRepositorio::UBICACIONES[$item['ubicacion']])) {
        $errores['ubicacion'] = 'Elegí dónde lo guardás.';
    }
    $fecha = DateTime::createFromFormat('!Y-m-d', $item['fecha_vencimiento']);
    if ($item['fecha_vencimiento'] !== '' && ($fecha === false || $fecha->format('Y-m-d') !== $item['fecha_vencimiento'])) {
        $errores['fecha_vencimiento'] = 'La fecha no es válida.';
    }
    if ($id > 0 && $repo->obtener($usuarioId, $id) === null) {
        redirigir('error.php?motivo=no-encontrado');
    }

    if ($errores === []) {
        if ($esNuevo) {
            // Si ya existe (en la lista general, como sinónimo o entre los suyos) se usa ese; si no, se crea
            $existente = $ingredientes->idPorNombre($nuevo['nombre'], $usuarioId);
            $item['ingrediente_id'] = $existente
                ?? $ingredientes->crearPropio($usuarioId, $nuevo['nombre'], $nuevo['categoria_id'], $nuevo['unidad']);
            $unidades[$item['ingrediente_id']] ??= $nuevo['unidad'];
        }
        $datos = $item;
        $datos['fecha_vencimiento'] = $item['fecha_vencimiento'] !== '' ? $item['fecha_vencimiento'] : null;
        $datos['cantidad'] = $item['cantidad'] !== '' ? (float) $item['cantidad'] : null;
        $datos['unidad'] = $unidades[$item['ingrediente_id']] ?? null;
        if ($repo->guardar($usuarioId, $datos, $id)) {
            Sesion::avisar('exito', match (true) {
                $id > 0 => 'Se guardaron los cambios.',
                $esNuevo && $existente === null => 'Se agregó “' . $nuevo['nombre'] . '” a tu despensa. '
                    . 'Es un ingrediente nuevo: por ahora solo lo ves vos.',
                default => 'Se agregó a tu despensa.',
            });
            redirigir('app/despensa.php');
        }
        $errores['ingrediente_id'] = 'Ese ingrediente ya está en esa ubicación: editalo en la lista.';
    }
} elseif (isset($_GET['editar'])) {
    $item = $repo->obtener($usuarioId, (int) $_GET['editar']);
    if ($item === null) {
        redirigir('error.php?motivo=no-encontrado');
    }
    $item['cantidad'] = $item['cantidad'] === null ? '' : (string) (float) $item['cantidad'];
}

$ubicacion = (string) ($_GET['ubicacion'] ?? '');
if (!isset(DespensaRepositorio::UBICACIONES[$ubicacion])) {
    $ubicacion = '';
}
$estado = (string) ($_GET['estado'] ?? '');
if (!isset(DespensaRepositorio::ESTADOS[$estado])) {
    $estado = '';
}
$items = $repo->listar($usuarioId, '', $ubicacion);
// El contador de arriba es de toda la despensa, aunque se esté viendo una sola ubicación o un solo estado
$resumen = DespensaRepositorio::resumen($ubicacion === '' ? $items : $repo->listar($usuarioId));
if ($estado !== '') {
    $items = array_values(array_filter($items, fn ($fila) => DespensaRepositorio::estado($fila) === $estado));
}
/** Dirección de la despensa con los filtros (ubicación y estado) que se pasen; los vacíos no van. */
$listaCompras = new ListaRepositorio($db);
$enLista = $listaCompras->ingredientesPendientes($usuarioId);
$notaLista = $listaCompras->faltaComprar($usuarioId);   // la nota de la lista de compras, a la derecha de los filtros
$filtro = fn (string $ubic, string $est) => url('app/despensa.php' . (($q = http_build_query(array_filter(['ubicacion' => $ubic, 'estado' => $est]))) !== '' ? '?' . $q : ''));
$error = fn (string $campo) => isset($errores[$campo]) ? '<p class="error-campo">' . e($errores[$campo]) . '</p>' : '';

$titulo = 'Mi despensa';
require parcial('encabezado');
?>
<div class="encabezado-pagina">
  <h1>Mi despensa</h1>
  <a class="boton boton--agregar" href="#formulario" id="abrir-editor">+ Agregar producto</a>
</div>

<?php /* data-abrir: despensa.js abre la nota sola si se está editando o si hay errores */ ?>
<section class="tarjeta editor" id="formulario" data-abrir="<?= $item['id'] || $errores ? '1' : '0' ?>" aria-labelledby="titulo-editor">
  <h2 id="titulo-editor"><?= $item['id'] ? 'Editar ingrediente' : 'Agregar ingrediente' ?></h2>
  <form class="formulario formulario--ancho" method="post" action="<?= e(url('app/despensa.php')) ?>" data-sonido="sticker-pega">
    <?= Sesion::campoCsrf() ?>
    <input type="hidden" name="accion" value="guardar">
    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
    <div class="fila-campos">
      <?php /* Ingrediente: sin JavaScript, la lista de siempre. Con JavaScript, despensa.js la cambia
                 por un buscador con stickers (la lista sigue abajo, oculta, y es la que se envía) */ ?>
      <div class="campo campo--ingrediente">
        <label for="ingrediente_id">Ingrediente</label>
        <select id="ingrediente_id" name="ingrediente_id">
          <option value="">Elegí…</option>
          <?php $grupo = null; ?>
          <?php foreach ($catalogo as $ing) : ?>
            <?php if ($ing['categoria'] !== $grupo) : ?>
              <?= $grupo !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($ing['categoria']) ?>">
              <?php $grupo = $ing['categoria']; ?>
            <?php endif; ?>
            <option value="<?= e($ing['id']) ?>" data-unidad="<?= e($ing['unidad_default']) ?>"
                    data-dibujo="<?= e(iconoIngrediente($ing['nombre'], (bool) $ing['propio'])) ?>"
                    data-buscar="<?= e(mb_strtolower($ing['nombre'] . ',' . $ing['sinonimos'])) ?>"<?= $ing['propio'] ? ' data-propio' : '' ?>
                    <?= (int) $item['ingrediente_id'] === (int) $ing['id'] ? ' selected' : '' ?>><?= e($ing['nombre']) ?></option>
          <?php endforeach; ?>
          </optgroup>
        </select>
        <?= $error('ingrediente_id') ?>
        <?php /* ¿No está en la lista? Un <details> anda sin JavaScript; con JavaScript lo abre "+ Agregar … como nuevo" */ ?>
        <details class="nuevo-ingrediente"<?= $nuevo['nombre'] !== '' ? ' open' : '' ?>>
          <summary>¿No está en la lista? Agregalo</summary>
          <div class="nuevo-ingrediente__campos">
            <img class="nuevo-ingrediente__dibujo" src="<?= e(url('img/ingredientes/frasco.svg')) ?>" alt="" width="56" height="56">
            <div class="campo">
              <label for="nombre_nuevo">Nombre</label>
              <input id="nombre_nuevo" name="nombre_nuevo" type="text" maxlength="60" value="<?= e($nuevo['nombre']) ?>"
                     placeholder="Por ejemplo: Quinoa">
            </div>
            <div class="campo">
              <label for="categoria_nueva">Tipo</label>
              <select id="categoria_nueva" name="categoria_nueva">
                <?php foreach ($categorias as $cat) : ?>
                  <option value="<?= e($cat['id']) ?>"<?= (int) $cat['id'] === $nuevo['categoria_id'] ? ' selected' : '' ?>><?= e($cat['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="campo">
              <label for="unidad_nueva">Se mide en</label>
              <select id="unidad_nueva" name="unidad_nueva">
                <?php foreach ($unidadesNuevo as $clave => $texto) : ?>
                  <option value="<?= e($clave) ?>"<?= $clave === $nuevo['unidad'] ? ' selected' : '' ?>><?= e($texto) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <p class="nuevo-ingrediente__nota">Lleva el dibujo del frasco y solo lo ves vos. No aparece en las recetas hasta que lo sume la administración.</p>
          <?= $error('nombre_nuevo') ?>
        </details>
      </div>
      <div class="campo">
        <label for="nivel">¿Cuánto tenés?</label>
        <select id="nivel" name="nivel" aria-describedby="ayuda-nivel">
          <?php foreach (DespensaRepositorio::NIVELES as $valor => $texto) : ?>
            <option value="<?= $valor ?>"<?= (int) $item['nivel'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $error('nivel') ?>
      </div>
      <div class="campo">
        <label for="cantidad">Cantidad exacta (opcional)</label>
        <div class="con-unidad">
          <input id="cantidad" name="cantidad" type="number" min="0" max="99999" step="any" inputmode="decimal"
                 value="<?= e((string) $item['cantidad']) ?>" aria-describedby="unidad-cantidad">
          <span id="unidad-cantidad" class="con-unidad__unidad"><?= e($unidades[$item['ingrediente_id']] ?? '') ?></span>
        </div>
        <?= $error('cantidad') ?>
      </div>
      <div class="campo">
        <label for="ubicacion">¿Dónde está?</label>
        <select id="ubicacion" name="ubicacion">
          <?php foreach (DespensaRepositorio::UBICACIONES as $valor => $texto) : ?>
            <option value="<?= e($valor) ?>"<?= $item['ubicacion'] === $valor ? ' selected' : '' ?>><?= e($texto) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $error('ubicacion') ?>
      </div>
      <div class="campo">
        <label for="fecha_vencimiento">Vence el (opcional)</label>
        <input id="fecha_vencimiento" name="fecha_vencimiento" type="date" value="<?= e((string) $item['fecha_vencimiento']) ?>">
        <?= $error('fecha_vencimiento') ?>
      </div>
    </div>
    <p class="ayuda-nivel" id="ayuda-nivel"><b>“Agotado”</b> en “¿Cuánto tenés?”: se te terminó, pero lo dejás anotado para acordarte de comprarlo. No cuenta para las recetas.</p>
    <div class="acciones">
      <button class="boton" type="submit" id="guardar-editor"><?= $item['id'] ? 'Guardar cambios' : 'Agregar' ?></button>
      <a class="boton boton--secundario" href="<?= e(url('app/despensa.php')) ?>" id="cancelar-editor"<?= $item['id'] ? '' : ' hidden' ?>>Cancelar</a>
    </div>
  </form>
</section>

<?php /* Contador, pestañas y buscador a la izquierda; la nota de la lista de compras en el hueco de la derecha */ ?>
<div class="despensa-arriba">
<div class="despensa-arriba__filtros">
<?php if (array_sum($resumen) > 0) : ?>
  <?php /* Cada número es un filtro: tocarlo muestra solo esos; tocarlo de nuevo, todos */ ?>
  <nav class="contador" aria-label="Filtrar por estado">
    <?php foreach (DespensaRepositorio::ESTADOS as $clave => $texto) : ?>
      <?php if ($clave === 'agotados' && $resumen['agotados'] === 0) {
          continue;
      } ?>
      <?php $activo = $estado === $clave; ?>
      <a class="contador__item contador__item--<?= e(str_replace('_', '-', $clave)) ?>"
         href="<?= e($filtro($ubicacion, $activo ? '' : $clave)) ?>"<?= $activo ? ' aria-current="true"' : '' ?>
         title="<?= $activo ? 'Ver todos' : 'Ver solo los que están ' . e($texto) ?>">
        <strong><?= $resumen[$clave] ?></strong> <?= e($resumen[$clave] === 1 ? rtrim($texto, 's') : $texto) ?>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<nav class="pestanas" aria-label="Filtrar por ubicación">
  <a href="<?= e($filtro('', $estado)) ?>"<?= $ubicacion === '' ? ' aria-current="page"' : '' ?>>Todo</a>
  <?php foreach (DespensaRepositorio::UBICACIONES as $valor => $texto) : ?>
    <a href="<?= e($filtro($valor, $estado)) ?>"<?= $ubicacion === $valor ? ' aria-current="page"' : '' ?>><?= e($texto) ?></a>
  <?php endforeach; ?>
</nav>
<?php if ($items !== []) : ?>
  <div class="campo filtro-rapido">
    <label for="filtro">Buscar en mi despensa</label>
    <input id="filtro" type="search" placeholder="Escribí para filtrar la lista" autocomplete="off">
  </div>
<?php endif; ?>
</div>
<?php require parcial('nota-lista'); ?>
</div>

<?php if ($items === []) : ?>
  <p class="vacio">
    <?php if ($estado !== '') : ?>
      No hay productos <?= e(DespensaRepositorio::ESTADOS[$estado]) ?><?= $ubicacion !== '' ? ' en esta ubicación' : '' ?>.
      <a href="<?= e($filtro($ubicacion, '')) ?>">Ver todos</a>
    <?php else : ?>
      <?= $ubicacion === '' ? 'Tu despensa está vacía. Agregá lo que tenés con el botón de arriba.' : 'No hay nada en esta ubicación.' ?>
    <?php endif; ?>
  </p>
<?php else : ?>
  <div class="tabla-contenedor">
    <table id="tabla-despensa" class="tabla-tarjetas">
      <thead><tr><th>Ingrediente</th><th>Cantidad</th><th>Vencimiento</th><th>Ubicación</th><th><span class="solo-lectores">Acciones</span></th></tr></thead>
      <tbody>
      <?php foreach ($items as $fila) : ?>
        <?php [$textoVence, $estilo] = DespensaRepositorio::estadoVencimiento($fila['dias'] === null ? null : (int) $fila['dias']); ?>
        <?php $textoCantidad = DespensaRepositorio::textoCantidad($fila['cantidad'], $fila['unidad']); ?>
        <?php $vencido = $fila['dias'] !== null && (int) $fila['dias'] < 0; ?>
        <tr<?= $vencido ? ' class="vencido"' : '' ?> data-nombre="<?= e(mb_strtolower($fila['nombre'])) ?>" data-id="<?= e($fila['id']) ?>"
            data-ingrediente="<?= e($fila['ingrediente_id']) ?>" data-nivel="<?= e($fila['nivel']) ?>"
            data-cantidad="<?= e($fila['cantidad'] === null ? '' : (string) (float) $fila['cantidad']) ?>"
            data-ubicacion="<?= e($fila['ubicacion']) ?>" data-vence="<?= e((string) $fila['fecha_vencimiento']) ?>">
          <td>
            <img class="sticker-ingrediente" src="<?= e(iconoIngrediente($fila['nombre'], (bool) $fila['propio'])) ?>" alt="" width="88" height="88">
            <?php if ($vencido) : ?>
              <?php /* Por si en realidad no venció: abre la nota con la fecha seleccionada */ ?>
              <a class="cambiar-fecha" data-editar data-foco="fecha_vencimiento"
                 href="<?= e(url('app/despensa.php?editar=' . $fila['id'] . '#formulario')) ?>">¿Venció? <span>Cambiar fecha</span></a>
            <?php elseif ($fila['dias'] !== null && (int) $fila['dias'] <= 1 && (int) $fila['nivel'] > 0) : ?>
              <span class="ya" aria-hidden="true">¡ya!</span>
            <?php endif; ?>
            <span class="punto" style="--color: <?= e($fila['color_hex']) ?>" aria-hidden="true"></span>
            <strong><?= e($fila['nombre']) ?></strong>
            <small class="texto-suave"><?= e($fila['categoria']) ?></small>
          </td>
          <td>
            <?php /* Agotado o con poco: "+ lista" va al lado de la cantidad, así se ve de qué ingrediente es */ ?>
            <div class="cantidad-y-lista">
              <span class="nivel nivel--<?= (int) $fila['nivel'] ?>"><?= e($textoCantidad !== '' ? $textoCantidad : DespensaRepositorio::NIVELES[(int) $fila['nivel']]) ?></span>
              <?php if ((int) $fila['nivel'] <= 1 && in_array((int) $fila['ingrediente_id'], $enLista, true)) : ?>
                <a class="etiqueta etiqueta--lista" href="<?= e(url('app/lista.php')) ?>">En tu lista</a>
              <?php elseif ((int) $fila['nivel'] <= 1) : ?>
                <?php /* Agotado o poco: "+ lista" (sin JavaScript recarga; con JavaScript, lista.js lo agrega sin recargar) */ ?>
                <form class="mas-lista" method="post" action="<?= e(url('app/lista.php')) ?>">
                  <?= Sesion::campoCsrf() ?>
                  <input type="hidden" name="accion" value="agregar">
                  <input type="hidden" name="ingrediente_id" value="<?= e($fila['ingrediente_id']) ?>">
                  <input type="hidden" name="volver" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                  <button class="mas-lista__boton" type="submit" title="Agregar a la lista de compras">
                    + lista<span class="solo-lectores">: agregar <?= e($fila['nombre']) ?> a la lista de compras</span>
                  </button>
                </form>
              <?php endif; ?>
            </div>
          </td>
          <td>
            <span class="etiqueta<?= $estilo ? ' etiqueta--' . $estilo : '' ?>"><?= e($textoVence) ?></span>
            <?php if ($fila['fecha_vencimiento']) : ?><small class="texto-suave"><?= e(date('d/m', strtotime($fila['fecha_vencimiento']))) ?></small><?php endif; ?>
          </td>
          <td><?= e(DespensaRepositorio::UBICACIONES[$fila['ubicacion']]) ?></td>
          <td>
            <div class="acciones">
              <a class="boton boton--secundario boton--chico" data-editar href="<?= e(url('app/despensa.php?editar=' . $fila['id'] . '#formulario')) ?>">Editar</a>
              <form method="post" data-confirmar="¿Quitar <?= e($fila['nombre']) ?> de tu despensa?" data-confirmar-si="Sí, quitar"
                    data-confirmar-dibujo="<?= e(iconoIngrediente($fila['nombre'], (bool) $fila['propio'])) ?>" data-sonido="sticker-arranca">
                <?= Sesion::campoCsrf() ?>
                <input type="hidden" name="accion" value="borrar">
                <input type="hidden" name="id" value="<?= e($fila['id']) ?>">
                <button class="boton boton--peligro boton--chico" type="submit">Quitar</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="vacio" id="sin-resultados" hidden>Nada coincide con lo que escribiste.</p>
<?php endif; ?>
<script src="<?= e(recurso('js/elegir.js')) ?>" defer></script>
<script src="<?= e(recurso('js/despensa.js')) ?>" defer></script>
<script src="<?= e(recurso('js/lista.js')) ?>" defer></script>
<?php require parcial('pie'); ?>
