<?php

/**
 * Administración de la IA (incremento 3 · HU-34). Solo administradores.
 *   - Si la IA está prendida, con qué modelo y los límites; si Google retiró el modelo, lo avisa arriba.
 *   - El uso de hoy y de los últimos 7 días por tipo (pedidos, caché, errores, tokens, tiempo) y los últimos errores.
 *   - Las recetas de IA que guardaron las personas (sin decir quién): "Pasar al recetario" las abre en
 *     receta-editar.php?desde_ia=N para revisarlas; recién al guardar quedan en el recetario.
 *   - Los reemplazos que sugirió la IA (HU-32): "Pasar a sustituciones" (calidad 3) o "Descartar".
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Config;
use Cooki\Db;
use Cooki\Dominio\Porciones;
use Cooki\Dominio\RecetaIa;
use Cooki\Ia\Ia;
use Cooki\Repositorios\IaRepositorio;
use Cooki\Sesion;

Sesion::requiereAdmin();

$repo = new IaRepositorio(Db::conexion());
// Para cruzar los reemplazos con el catálogo general (la administración pudo cargarlo después de la sugerencia)
$catalogo = new RecetaIa($repo->catalogo(0), $repo->sinonimos());
$reemplazoDe = function (array $s) use ($catalogo): ?array {
    $encontrado = $s['reemplazo_id'] !== null
        ? ['id' => $s['reemplazo_id'], 'nombre' => $s['reemplazo_texto']]
        : $catalogo->buscar($s['reemplazo_texto']);
    return $encontrado !== null && (int) $encontrado['id'] !== $s['ingrediente_id'] ? $encontrado : null;
};

if (esPost()) {
    Sesion::verificarCsrf();
    $accion = campo('accion');
    $sugerencia = $repo->sugerencia((int) ($_POST['id'] ?? 0));
    if ($sugerencia === null) {
        Sesion::avisar('error', 'Ese reemplazo ya se revisó.');
    } elseif ($accion === 'pasar') {
        $reemplazo = $reemplazoDe($sugerencia);
        if ($reemplazo === null) {
            Sesion::avisar('error', '“' . $sugerencia['reemplazo_texto'] . '” no está en el catálogo: cargalo primero en Ingredientes.');
        } else {
            $nueva = $repo->pasarSugerencia(
                $sugerencia['id'],
                $sugerencia['ingrediente_id'],
                (int) $reemplazo['id'],
                $sugerencia['ratio'] === null ? null : (float) $sugerencia['ratio'],
                $sugerencia['nota']
            );
            $par = $sugerencia['ingrediente'] . ' → ' . $reemplazo['nombre'];
            Sesion::avisar('exito', $nueva
                ? "Listo: {$par} ya está en las sustituciones del recetario (calidad 3)."
                : "{$par} ya estaba en las sustituciones: quedó como revisado.");
        }
    } elseif ($accion === 'descartar') {
        $repo->descartarSugerencia($sugerencia['id']);
        Sesion::avisar('exito', 'Se descartó ' . $sugerencia['ingrediente'] . ' → ' . $sugerencia['reemplazo_texto'] . '.');
    }
    redirigir('admin/ia.php#reemplazos');
}

$hoy = $repo->usoPorTipo(1);
$semana = $repo->usoPorTipo(7);
$errores = $repo->ultimosErrores();
$recetas = $repo->recetasParaRevisar();
$sugerencias = $repo->sugerenciasPendientes();
$modeloRetirado = $errores !== [] && str_contains((string) $errores[0]['error'], 'IA_MODELO');
$tipos = ['receta' => 'Receta nueva', 'adaptar' => 'Adaptar', 'reemplazo' => 'Reemplazo'];

/** Suma las filas de un período para la fila "Total". */
$total = function (array $uso): array {
    $suma = ['pedidos' => 0, 'cache' => 0, 'errores' => 0, 'tokens' => 0];
    foreach ($uso as $fila) {
        foreach ($suma as $campo => $valor) {
            $suma[$campo] = $valor + $fila[$campo];
        }
    }
    return $suma + ['ms' => null];
};
$numero = fn (int $n): string => number_format($n, 0, ',', '.');

$titulo = 'IA · Administración';
require parcial('encabezado');
?>
<?= enlaceVolver('admin/index.php', 'Volver al panel', [['admin/index.php', 'Panel']]) ?>
<h1>Inteligencia artificial</h1>

<?php if ($modeloRetirado) : ?>
  <p class="aviso aviso--error" role="alert"><?= e((string) $errores[0]['error']) ?></p>
<?php endif; ?>
<section class="tarjeta">
  <h2>Cómo está</h2>
  <?php if (Ia::disponible()) : ?>
    <p><span class="etiqueta etiqueta--exito">Prendida</span>
      Modelo <code><?= e(Config::get('IA_MODELO', 'gemini-3.5-flash-lite')) ?></code>.</p>
  <?php else : ?>
    <p><span class="etiqueta etiqueta--alerta">Apagada</span>
      Falta <code>IA_CLAVE</code> en el <code>.env</code>: los botones de la IA no se muestran y el resto del sitio anda igual.</p>
  <?php endif; ?>
  <p class="texto-suave">
    Cada persona puede hacer <?= e(Ia::limiteUsuario()) ?> pedidos por día (los reemplazos gastan 1 cada <?= e(IaRepositorio::REEMPLAZOS_POR_PEDIDO) ?>).
    Todo el sitio: <?= e($numero($repo->llamadasHoy())) ?> de <?= e($numero(Ia::limiteGlobal())) ?> llamadas hoy.
    Las respuestas se guardan <?= e(IaRepositorio::DIAS_CACHE) ?> días: lo que sale de la caché no llama a Google ni gasta pedidos.
  </p>
</section>

<section aria-labelledby="titulo-uso">
  <h2 id="titulo-uso">Uso</h2>
  <?php foreach (['Hoy' => $hoy, 'Últimos 7 días' => $semana] as $periodo => $uso) : ?>
    <div class="tabla-contenedor">
      <table class="tabla-tarjetas tabla-ia">
        <caption><?= e($periodo) ?></caption>
        <thead>
          <tr><th>Tipo</th><th>Pedidos</th><th>De la caché</th><th>Con error</th><th>Tokens</th><th>Tiempo promedio</th></tr>
        </thead>
        <tbody>
        <?php foreach (array_keys($tipos + $uso) as $tipo) : ?>
          <?php $fila = $uso[$tipo] ?? ['pedidos' => 0, 'cache' => 0, 'errores' => 0, 'tokens' => 0, 'ms' => null]; ?>
          <tr>
            <td><?= e($tipos[$tipo] ?? $tipo) ?></td>
            <td><?= e($numero($fila['pedidos'])) ?><small class="rotulo-celular"> pedidos</small></td>
            <td><?= e($numero($fila['cache'])) ?><small class="rotulo-celular"> de la caché</small></td>
            <td><?= $fila['errores'] > 0 ? '<span class="etiqueta etiqueta--error">' . e($fila['errores']) . ' con error</span>' : '0<small class="rotulo-celular"> con error</small>' ?></td>
            <td><?= e($numero($fila['tokens'])) ?><small class="rotulo-celular"> tokens</small></td>
            <td><?= $fila['ms'] === null ? '—' : e(number_format($fila['ms'] / 1000, 1, ',', '.')) . ' s' ?><small class="rotulo-celular"> en promedio</small></td>
          </tr>
        <?php endforeach; ?>
        <?php $suma = $total($uso); ?>
          <tr class="tabla-total">
            <td><strong>Total</strong></td>
            <td><strong><?= e($numero($suma['pedidos'])) ?></strong><small class="rotulo-celular"> pedidos</small></td>
            <td><?= e($numero($suma['cache'])) ?><small class="rotulo-celular"> de la caché</small></td>
            <td><?= e($numero($suma['errores'])) ?><small class="rotulo-celular"> con error</small></td>
            <td><?= e($numero($suma['tokens'])) ?><small class="rotulo-celular"> tokens</small></td>
            <td></td>
          </tr>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>

  <details class="tarjeta ia-errores"<?= $modeloRetirado ? ' open' : '' ?>>
    <summary>Últimos errores (<?= e(count($errores)) ?>)</summary>
    <?php if ($errores === []) : ?>
      <p class="vacio">No hubo errores.</p>
    <?php else : ?>
      <ul>
        <?php foreach ($errores as $error) : ?>
          <li><small class="texto-suave"><?= e(date('d/m H:i', strtotime($error['creado_en']))) ?> · <?= e($tipos[$error['tipo']] ?? $error['tipo']) ?></small>
            <?= e((string) $error['error']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </details>
</section>

<section aria-labelledby="titulo-recetas" id="recetas">
  <h2 id="titulo-recetas">Recetas de IA guardadas</h2>
  <p class="texto-suave">Las que las personas guardaron en “Mis recetas de IA”. Nada de la IA entra solo al recetario:
    “Pasar al recetario” la abre en el editor para revisarla, y recién al guardar queda publicada.</p>
  <?php if ($recetas === []) : ?>
    <p class="vacio">Todavía nadie guardó una receta de la IA.</p>
  <?php endif; ?>
  <?php foreach ($recetas as $r) : ?>
    <?php $c = $r['contenido']; ?>
    <article class="tarjeta ia-revisar" id="receta-ia-<?= e($r['id']) ?>">
      <header class="encabezado-pagina">
        <div>
          <h3><?= e($r['titulo']) ?></h3>
          <p class="texto-suave">
            <?= $r['tipo'] === 'adaptada' ? 'Adaptada de ' . e($r['titulo_base'] ?? 'una receta borrada') : 'Receta nueva' ?>
            · <?= e(date('d/m/Y', strtotime($r['creado_en']))) ?>
            <?php $nIng = count($c['ingredientes'] ?? []); $nPasos = count($c['pasos'] ?? []); ?>
            · <?= e($nIng) ?> ingrediente<?= $nIng === 1 ? '' : 's' ?> · <?= e($nPasos) ?> paso<?= $nPasos === 1 ? '' : 's' ?>
          </p>
        </div>
        <?php if ($r['pasada_a'] !== null) : ?>
          <span class="etiqueta etiqueta--exito">En el recetario</span>
        <?php endif; ?>
      </header>
      <details>
        <summary>Ver la receta</summary>
        <?php if (($c['descripcion'] ?? '') !== '') : ?>
          <p><?= e($c['descripcion']) ?></p>
        <?php endif; ?>
        <ul class="ia-revisar__ingredientes">
          <?php foreach ($c['ingredientes'] ?? [] as $ing) : ?>
            <li>
              <img src="<?= e(iconoIngrediente($ing['nombre'])) ?>" alt="" width="28" height="28">
              <?= e($ing['nombre']) ?>
              <small class="texto-suave"><?= e(Porciones::texto($ing['cantidad'], $ing['unidad'] === '' ? null : $ing['unidad'])) ?></small>
              <?php if ($ing['id'] === null) : ?>
                <span class="etiqueta etiqueta--alerta">no está en el catálogo</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
        <ol>
          <?php foreach ($c['pasos'] ?? [] as $paso) : ?>
            <li><?= e($paso['texto']) ?><?= $paso['minutos'] ? ' <small class="texto-suave">(' . e($paso['minutos']) . ' min)</small>' : '' ?></li>
          <?php endforeach; ?>
        </ol>
      </details>
      <div class="acciones">
        <?php if ($r['pasada_a'] !== null) : ?>
          <a class="boton boton--secundario boton--chico" href="<?= e(url('app/receta.php?id=' . $r['pasada_a'])) ?>">Ver “<?= e($r['titulo_pasada']) ?>” en el recetario</a>
        <?php else : ?>
          <a class="boton boton--chico" href="<?= e(url('admin/receta-editar.php?desde_ia=' . $r['id'])) ?>">Pasar al recetario</a>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</section>

<section aria-labelledby="titulo-reemplazos" id="reemplazos">
  <h2 id="titulo-reemplazos">Reemplazos sugeridos</h2>
  <p class="texto-suave">Lo que respondió la IA en “¿Con qué lo reemplazo?”, los más pedidos primero. “Pasar a sustituciones”
    lo suma a la tabla que usa el recetario (con ⇄) con calidad 3; “Descartar” lo saca de esta lista para siempre.</p>
  <?php if ($sugerencias === []) : ?>
    <p class="vacio">No hay reemplazos para revisar.</p>
  <?php else : ?>
    <div class="tabla-contenedor">
      <table class="tabla-tarjetas">
        <thead>
          <tr><th>Falta</th><th>Reemplazo</th><th>Proporción</th><th>Nota</th><th>Veces</th><th><span class="solo-lectores">Acciones</span></th></tr>
        </thead>
        <tbody>
        <?php foreach ($sugerencias as $s) : ?>
          <?php $reemplazo = $reemplazoDe($s); ?>
          <tr>
            <td><img src="<?= e(iconoIngrediente($s['ingrediente'])) ?>" alt="" width="28" height="28"> <?= e($s['ingrediente']) ?> <span aria-hidden="true">⇄</span></td>
            <td>
              <?= e($s['reemplazo_texto']) ?>
              <?php if ($reemplazo === null) : ?>
                <span class="etiqueta etiqueta--alerta">no está en el catálogo</span>
              <?php endif; ?>
            </td>
            <td><?= $s['ratio'] === null ? '—' : e(RecetaIa::proporcion((float) $s['ratio'])) ?></td>
            <td><small><?= e((string) $s['nota']) ?></small></td>
            <td><?= e($s['veces']) ?><small class="rotulo-celular"> <?= $s['veces'] === 1 ? 'vez' : 'veces' ?></small></td>
            <td>
              <div class="acciones">
                <form method="post">
                  <?= Sesion::campoCsrf() ?>
                  <input type="hidden" name="id" value="<?= e($s['id']) ?>">
                  <?php if ($reemplazo !== null) : ?>
                    <button class="boton boton--chico" name="accion" value="pasar">Pasar a sustituciones</button>
                  <?php else : ?>
                    <a class="boton boton--secundario boton--chico" href="<?= e(url('admin/ingredientes.php?nuevo')) ?>">Cargar en Ingredientes</a>
                  <?php endif; ?>
                  <button class="boton boton--secundario boton--chico" name="accion" value="descartar">Descartar</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require parcial('pie'); ?>
