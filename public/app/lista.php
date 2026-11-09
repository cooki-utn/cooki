<?php

/**
 * Lista de compras (incremento 3 · HU-26 a HU-29).
 *   lista.php                     → agregar a mano + lo que falta comprar (por góndola) + lo comprado hoy
 *   POST accion=agregar           → ingrediente_id (o texto libre), cantidad y unidad opcionales.
 *                                   Con receta=ID (+ porciones): la cantidad sale de la receta, escalada
 *   POST accion=receta            → receta=ID porciones=N: agrega todo lo que falta de esa receta
 *   POST accion=comprar ids[]=…   → lo tildado pasa a la despensa
 *   POST accion=comprar_todo      → todo lo que falta comprar pasa a la despensa
 *   POST comprar_uno=ID           → "Otra cantidad": ese renglón, con lo que de verdad se compró
 *        cantidad_comprada[ID]= unidad_comprada[ID]=   (también valen con accion=comprar)
 *   POST quitar=ID / deshacer=ID  → quita un renglón / deshace una compra de hoy
 * Todas vuelven a "volver" (una dirección de este sitio) o a la lista. Con el encabezado
 * X-Requested-With: fetch (lista.js) responden JSON en lugar de redirigir.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Dominio\ListaCompras;
use Cooki\Dominio\Porciones;
use Cooki\Repositorios\IngredienteRepositorio;
use Cooki\Repositorios\ListaRepositorio;
use Cooki\Repositorios\RecetaRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();

$db = Db::conexion();
$repo = new ListaRepositorio($db);
$usuarioId = Sesion::idUsuario();
/** Unidades para agregar a mano. */
$unidadesLista = ['u' => 'unidades', 'g' => 'g', 'kg' => 'kg', 'ml' => 'ml', 'l' => 'l'];

if (esPost()) {
    Sesion::verificarCsrf();
    $porFetch = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    /** Responde: JSON para lista.js; si no, aviso y vuelta a la página de la que vino. */
    $responder = function (bool $ok, string $mensaje, array $datos = []) use ($porFetch, $repo, $usuarioId): never {
        if ($porFetch) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code($ok ? 200 : 422);
            echo json_encode(
                ['ok' => $ok, 'mensaje' => $mensaje, 'pendientes' => $repo->pendientes($usuarioId)] + $datos,
                JSON_UNESCAPED_UNICODE
            );
            exit;
        }
        Sesion::avisar($ok ? 'exito' : 'error', $mensaje);
        $volver = campo('volver');
        if (str_starts_with($volver, '/') && !str_starts_with($volver, '//') && !str_contains($volver, '\\')) {
            header('Location: ' . $volver);
            exit;
        }
        redirigir('app/lista.php');
    };

    if (isset($_POST['quitar'])) {
        $ok = $repo->borrar($usuarioId, (int) $_POST['quitar']);
        $responder($ok, $ok ? 'Se quitó de la lista.' : 'Eso ya no estaba en tu lista.');
    }
    if (isset($_POST['deshacer'])) {
        $ok = $repo->deshacer($usuarioId, (int) $_POST['deshacer']);
        $responder($ok, $ok ? 'Listo, volvió a la lista y tu despensa quedó como estaba.' : 'Ya no se puede deshacer.');
    }

    // "Otra cantidad": lo que de verdad se compró, por renglón (los campos vacíos no cuentan)
    $otraCantidad = [];
    foreach ((array) ($_POST['cantidad_comprada'] ?? []) as $idRenglon => $valor) {
        $valor = str_replace(',', '.', trim((string) $valor));
        if ($valor === '') {
            continue;
        }
        if (!is_numeric($valor) || (float) $valor <= 0 || (float) $valor > 99999) {
            $responder(false, 'La cantidad que compraste tiene que ser un número mayor que 0.');
        }
        $unidadComprada = (string) (((array) ($_POST['unidad_comprada'] ?? []))[$idRenglon] ?? 'u');
        $otraCantidad[(int) $idRenglon] = [
            'cantidad' => (float) $valor,
            'unidad' => isset($unidadesLista[$unidadComprada]) ? $unidadComprada : 'u',
        ];
    }

    if (isset($_POST['comprar_uno'])) {
        $idRenglon = (int) $_POST['comprar_uno'];
        if (!isset($otraCantidad[$idRenglon])) {
            $responder(false, 'Escribí cuánto compraste.');
        }
        $ok = $repo->comprar($usuarioId, [$idRenglon], $otraCantidad) > 0;
        $otra = $otraCantidad[$idRenglon];
        $responder($ok, $ok ? 'Anotamos que compraste ' . Porciones::texto($otra['cantidad'], $otra['unidad']) . '.'
            : 'Eso ya no estaba en tu lista.', ['texto_cantidad' => Porciones::texto($otra['cantidad'], $otra['unidad'])]);
    }

    $accion = campo('accion');
    if ($accion === 'comprar_todo') {
        $hechos = $repo->comprarTodo($usuarioId);
        $responder($hechos > 0, $hechos > 0
            ? "Listo: marcamos {$hechos} cosa" . ($hechos === 1 ? '' : 's') . ' como comprada' . ($hechos === 1 ? '' : 's')
                . '. Los ingredientes ya están en tu despensa.'
            : 'No había nada para comprar.');
    }
    if ($accion === 'comprar') {
        $ids = array_map('intval', (array) ($_POST['ids'] ?? []));
        $hechos = $repo->comprar($usuarioId, $ids, $otraCantidad);
        $responder($hechos > 0, match (true) {
            $hechos === 0 => 'Tildá lo que compraste y después tocá "Pasar a mi despensa".',
            $hechos === 1 => 'Anotado como comprado. Si es un ingrediente, ya está en tu despensa.',
            default => "Anotamos {$hechos} cosas como compradas. Los ingredientes ya están en tu despensa.",
        });
    }

    $recetaId = (int) ($_POST['receta'] ?? 0);
    $receta = $recetaId > 0 ? (new RecetaRepositorio($db))->obtener($recetaId, $usuarioId) : null;
    if ($recetaId > 0 && $receta === null) {
        $responder(false, 'No encontramos esa receta.');
    }
    $factor = 1.0;
    if ($receta !== null) {
        $porciones = max(Porciones::MINIMO, min(Porciones::MAXIMO, (int) ($_POST['porciones'] ?? $receta['porciones_base'])));
        $factor = Porciones::factor((int) $receta['porciones_base'], $porciones);
    }

    if ($accion === 'receta' && $receta !== null) {
        $agregados = $repo->agregarFaltantes($usuarioId, ListaCompras::faltantes($receta['ingredientes'], $factor), $recetaId);
        $responder(true, match ($agregados) {
            0 => 'No te falta nada para esta receta.',
            1 => 'Agregamos 1 ingrediente a tu lista de compras.',
            default => "Agregamos {$agregados} ingredientes a tu lista de compras.",
        }, ['agregados' => $agregados]);
    }

    if ($accion === 'agregar') {
        $ingredienteId = (int) ($_POST['ingrediente_id'] ?? 0);
        $texto = campo('texto');
        $cantidad = null;
        $unidad = null;
        if ($receta !== null) {
            // Desde una receta: la cantidad es la de la receta, para las porciones elegidas
            $deLaReceta = array_values(array_filter(
                $receta['ingredientes'],
                fn (array $ing) => (int) $ing['ingrediente_id'] === $ingredienteId
            ))[0] ?? null;
            if ($deLaReceta === null) {
                $responder(false, 'Ese ingrediente no está en la receta.');
            }
            ['cantidad' => $cantidad, 'unidad' => $unidad] = ListaCompras::escalar($deLaReceta['cantidad'], $deLaReceta['unidad'], $factor);
        } else {
            $textoCantidad = str_replace(',', '.', campo('cantidad'));
            if ($textoCantidad !== '') {
                if (!is_numeric($textoCantidad) || (float) $textoCantidad <= 0 || (float) $textoCantidad > 99999) {
                    $responder(false, 'La cantidad tiene que ser un número mayor que 0, o quedar vacía.');
                }
                $cantidad = (float) $textoCantidad;
                $unidad = isset($unidadesLista[campo('unidad')]) ? campo('unidad') : 'u';
            }
        }
        $hecho = $repo->agregar(
            $usuarioId,
            $ingredienteId > 0 ? $ingredienteId : null,
            $ingredienteId > 0 ? null : $texto,
            $cantidad,
            $unidad,
            $receta === null ? null : $recetaId
        );
        if ($hecho === null) {
            $responder(false, $ingredienteId > 0 || $texto === ''
                ? 'Elegí un ingrediente de la lista, o escribí qué querés comprar.'
                : 'Escribí entre 2 y ' . ListaRepositorio::LARGO_TEXTO . ' letras.');
        }
        $responder(true, $hecho['sumado'] ? 'Ya estaba en tu lista: sumamos la cantidad.' : 'Se agregó a tu lista de compras.', $hecho);
    }
    $responder(false, 'No entendimos qué querías hacer.');
}

$items = $repo->listar($usuarioId);
$pendientes = array_values(array_filter($items, fn (array $i) => $i['comprado_en'] === null));
$comprados = array_values(array_filter($items, fn (array $i) => $i['comprado_en'] !== null));
$grupos = ListaCompras::agrupar($pendientes);
$textoLista = ListaCompras::texto($grupos);
$catalogo = (new IngredienteRepositorio($db))->todos($usuarioId);

$titulo = 'Lista de compras';
require parcial('encabezado');
?>
<div class="encabezado-pagina">
  <h1>Lista de compras</h1>
  <a class="boton boton--agregar" href="#agregar">+ Agregar</a>
</div>

<section class="tarjeta lista-agregar" id="agregar" aria-labelledby="titulo-agregar">
  <h2 id="titulo-agregar">Agregar a la lista</h2>
  <form class="formulario formulario--ancho" method="post" action="<?= e(url('app/lista.php')) ?>" data-sonido="sticker-pega">
    <?= Sesion::campoCsrf() ?>
    <input type="hidden" name="accion" value="agregar">
    <div class="fila-campos">
      <?php /* Sin JavaScript, la lista de siempre; con JavaScript, el buscador con stickers (elegir.js) */ ?>
      <div class="campo campo--ingrediente">
        <label for="ingrediente_lista">Qué</label>
        <select id="ingrediente_lista" name="ingrediente_id">
          <option value="">Elegí…</option>
          <?php $grupo = null; ?>
          <?php foreach ($catalogo as $ing) : ?>
            <?php if ($ing['categoria'] !== $grupo) : ?>
              <?= $grupo !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($ing['categoria']) ?>">
              <?php $grupo = $ing['categoria']; ?>
            <?php endif; ?>
            <option value="<?= e($ing['id']) ?>" data-unidad="<?= e($ing['unidad_default']) ?>"
                    data-dibujo="<?= e(iconoIngrediente($ing['nombre'], (bool) $ing['propio'])) ?>"
                    data-buscar="<?= e(mb_strtolower($ing['nombre'] . ',' . $ing['sinonimos'])) ?>"<?= $ing['propio'] ? ' data-propio' : '' ?>><?= e($ing['nombre']) ?></option>
          <?php endforeach; ?>
          </optgroup>
        </select>
        <details class="nuevo-ingrediente">
          <summary>¿No está en la lista? Escribilo</summary>
          <div class="nuevo-ingrediente__campos">
            <img class="nuevo-ingrediente__dibujo" src="<?= e(url('img/ingredientes/frasco.svg')) ?>" alt="" width="56" height="56">
            <div class="campo">
              <label for="texto_lista">Qué querés comprar</label>
              <input id="texto_lista" name="texto" type="text" maxlength="<?= ListaRepositorio::LARGO_TEXTO ?>" placeholder="Por ejemplo: Servilletas">
            </div>
          </div>
          <p class="nuevo-ingrediente__nota">Se anota con el dibujo del frasco. Al comprarlo no pasa a tu despensa.</p>
        </details>
      </div>
      <div class="campo">
        <label for="cantidad_lista">Cantidad (opcional)</label>
        <input id="cantidad_lista" name="cantidad" type="number" min="0" max="99999" step="any" inputmode="decimal">
      </div>
      <div class="campo">
        <label for="unidad_lista">Unidad</label>
        <select id="unidad_lista" name="unidad">
          <?php foreach ($unidadesLista as $clave => $texto) : ?>
            <option value="<?= e($clave) ?>"><?= e($texto) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="acciones">
      <button class="boton" type="submit">Agregar</button>
    </div>
  </form>
</section>

<?php if ($items === []) : ?>
  <p class="vacio">Tu lista está vacía. Agregá cosas con el formulario de arriba, o desde una receta con
    <strong>“Agregar lo que falta a la lista”</strong>.</p>
<?php else : ?>
  <?php /* Un solo formulario: tildar + "Pasar a mi despensa" anda sin JavaScript; Quitar y Deshacer son botones del mismo
           formulario. Con JavaScript (lista.js), tildar pasa el ingrediente a la despensa en el momento */ ?>
  <form class="lista-compras" id="lista-compras" method="post" action="<?= e(url('app/lista.php')) ?>">
    <?= Sesion::campoCsrf() ?>
    <?php if ($pendientes !== []) : ?>
      <div class="lista-compras__compartir">
        <button class="boton boton--secundario boton--chico" type="button" id="copiar-lista" hidden>Copiar la lista</button>
        <a class="boton boton--secundario boton--chico" id="whatsapp-lista" href="https://wa.me/?text=<?= e(rawurlencode($textoLista)) ?>"
           target="_blank" rel="noopener">Mandar por WhatsApp</a>
        <button class="boton boton--secundario boton--chico" type="button" id="imprimir-lista" hidden>Imprimir</button>
        <span class="lista-compras__copiado" role="status"></span>
      </div>
      <textarea class="solo-lectores" id="texto-lista" tabindex="-1" aria-hidden="true" readonly><?= e($textoLista) ?></textarea>
    <?php endif; ?>
    <?php foreach ($grupos as $grupoLista) : ?>
      <section class="lista-compras__grupo" aria-labelledby="grupo-<?= e(md5($grupoLista['categoria'])) ?>">
        <h2 id="grupo-<?= e(md5($grupoLista['categoria'])) ?>"><?= e($grupoLista['categoria']) ?></h2>
        <ul class="lista-compras__items">
          <?php foreach ($grupoLista['items'] as $item) : ?>
            <li class="renglon" data-id="<?= e($item['id']) ?>">
              <label class="renglon__tilde">
                <input type="checkbox" name="ids[]" value="<?= e($item['id']) ?>">
                <img class="renglon__dibujo" src="<?= e(iconoIngrediente($item['nombre'], (bool) $item['propio'] || $item['ingrediente_id'] === null)) ?>" alt="" width="44" height="44">
                <span class="renglon__texto">
                  <strong><?= e($item['nombre']) ?></strong>
                  <?php if ($item['cantidad'] !== null) : ?>
                    <span class="renglon__cantidad"><?= e(Porciones::texto((float) $item['cantidad'], $item['unidad'])) ?></span>
                  <?php endif; ?>
                  <?php if ($item['receta'] !== null) : ?>
                    <small class="renglon__receta">para: <?= e($item['receta']) ?></small>
                  <?php endif; ?>
                </span>
              </label>
              <?php /* Si compraste otra cantidad: se anota y eso es lo que pasa a la despensa */ ?>
              <details class="renglon__otra">
                <summary>Otra cantidad</summary>
                <div class="renglon__otra-campos">
                  <label class="solo-lectores" for="otra-<?= e($item['id']) ?>">Cuánto compraste de <?= e($item['nombre']) ?></label>
                  <input id="otra-<?= e($item['id']) ?>" name="cantidad_comprada[<?= e($item['id']) ?>]" type="number"
                         min="0" max="99999" step="any" inputmode="decimal" placeholder="¿Cuánto?">
                  <label class="solo-lectores" for="otra-unidad-<?= e($item['id']) ?>">Unidad</label>
                  <select id="otra-unidad-<?= e($item['id']) ?>" name="unidad_comprada[<?= e($item['id']) ?>]">
                    <?php $unidadRenglon = isset($unidadesLista[(string) $item['unidad']]) ? $item['unidad'] : ($item['unidad_default'] ?? 'u'); ?>
                    <?php foreach ($unidadesLista as $clave => $texto) : ?>
                      <option value="<?= e($clave) ?>"<?= $clave === $unidadRenglon ? ' selected' : '' ?>><?= e($texto) ?></option>
                    <?php endforeach; ?>
                  </select>
                  <button class="boton boton--chico" type="submit" name="comprar_uno" value="<?= e($item['id']) ?>">Lo compré</button>
                </div>
              </details>
              <button class="renglon__quitar" type="submit" name="quitar" value="<?= e($item['id']) ?>"
                      title="Quitar de la lista" data-sonido="sticker-arranca">
                <span aria-hidden="true">✕</span><span class="solo-lectores">Quitar <?= e($item['nombre']) ?> de la lista</span>
              </button>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endforeach; ?>
    <?php if ($pendientes !== []) : ?>
      <div class="acciones lista-compras__pasar">
        <button class="boton" type="submit" name="accion" value="comprar" id="pasar-lista">Pasar lo tildado a mi despensa</button>
      </div>
      <div class="acciones lista-compras__todo">
        <button class="boton boton--secundario" type="submit" name="accion" value="comprar_todo">Marcar todo como comprado</button>
      </div>
    <?php else : ?>
      <p class="vacio">¡No te falta nada! Lo que compraste hoy está abajo.</p>
    <?php endif; ?>

    <?php if ($comprados !== []) : ?>
      <section class="lista-compras__comprado" aria-labelledby="titulo-comprado">
        <h2 id="titulo-comprado">Comprado hoy</h2>
        <ul class="lista-compras__items">
          <?php foreach ($comprados as $item) : ?>
            <li class="renglon renglon--comprado">
              <img class="renglon__dibujo" src="<?= e(iconoIngrediente($item['nombre'], (bool) $item['propio'] || $item['ingrediente_id'] === null)) ?>" alt="" width="44" height="44">
              <span class="renglon__texto">
                <s><?= e($item['nombre']) ?></s>
                <small><?= $item['paso_a_despensa'] ? 'Ya está en tu despensa' : 'Comprado' ?></small>
              </span>
              <button class="boton boton--secundario boton--chico" type="submit" name="deshacer" value="<?= e($item['id']) ?>">Deshacer</button>
            </li>
          <?php endforeach; ?>
        </ul>
        <p class="texto-suave"><small>Lo comprado se ve hasta mañana. Mirá cómo quedó en <a href="<?= e(url('app/despensa.php')) ?>">Mi despensa</a>.</small></p>
      </section>
    <?php endif; ?>
  </form>
<?php endif; ?>
<script src="<?= e(recurso('js/elegir.js')) ?>" defer></script>
<script src="<?= e(recurso('js/lista.js')) ?>" defer></script>
<?php require parcial('pie'); ?>
