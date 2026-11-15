<?php

/**
 * Bienvenida (incremento 3 · PR D): "Contanos cómo comés", en 4 notas (paso a paso con JavaScript; sin JavaScript
 * se ven las 4 juntas). Aparece sola después de "Crear cuenta" y se vuelve a abrir desde Mis preferencias.
 *   GET           → las notas, con lo que ya eligió marcado
 *   POST          → guarda todo (reemplaza lo anterior) y va a Mi cocina
 *     dieta[]       vegetariana · vegana · sin_tacc · sin_lactosa (ninguna = "Como de todo")
 *     nocomo[]      "g:pescado" (grupo de Perfil::NO_COMO) o "i:15" (ingrediente) → restriccion_usuario
 *     nocomo_nuevo[] / nocomo_otro      lo que escribió y no está en la lista: se crea como ingrediente propio
 *     gustos[]      dulce · merienda_salada · rapida · economica
 *     nivel         1 a 3 · comensales 1 a 5
 *     basicos[]     "i:20" → usuario_basico ("Siempre tengo"); basicos_nuevo[] / basicos_otro, igual que arriba
 * Qué hace cada cosa con las recetas: ver Dominio\Perfil.
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/src/bootstrap.php';

use Cooki\Db;
use Cooki\Dominio\Perfil;
use Cooki\Dominio\Tipos;
use Cooki\Repositorios\IngredienteRepositorio;
use Cooki\Repositorios\PreferenciasRepositorio;
use Cooki\Sesion;

Sesion::requiereLogin();
$db = Db::conexion();
$preferencias = new PreferenciasRepositorio($db);
$ingredientes = new IngredienteRepositorio($db);
$usuarioId = Sesion::idUsuario();

// Los ingredientes que puede elegir: la lista general y los suyos (id → fila)
$catalogo = array_column($ingredientes->todos($usuarioId), null, 'id');
$idDe = fn (string $nombre): ?int => $ingredientes->idPorNombre($nombre, 0);
// Las opciones rápidas, con los ids que de verdad están en el catálogo
$grupos = [];
foreach (Perfil::NO_COMO as $clave => [, , $nombres]) {
    $grupos[$clave] = array_values(array_filter(array_map($idDe, $nombres)));
}
$grupos = array_filter($grupos);
$siempreHay = [];
foreach (Perfil::SIEMPRE_HAY as $nombre) {
    $id = $idDe($nombre);
    if ($id !== null) {
        $siempreHay[$id] = $nombre;
    }
}

if (esPost()) {
    Sesion::verificarCsrf();
    $preferencias->guardarPerfil($usuarioId, Perfil::desdeFormulario($_POST));
    $creados = 0;
    foreach (['restricciones' => 'nocomo', 'basicos' => 'basicos'] as $lista => $campo) {
        $ids = Perfil::ingredientesElegidos($_POST[$campo] ?? [], $lista === 'restricciones' ? $grupos : []);
        // Solo de la lista general o propios de esta persona
        $ids = array_values(array_filter($ids, fn (int $id) => isset($catalogo[$id])));
        $escritos = is_array($_POST[$campo . '_nuevo'] ?? null) ? $_POST[$campo . '_nuevo'] : [];
        foreach (Perfil::nombresNuevos([...$escritos, campo($campo . '_otro')]) as $nombre) {
            // Si ya existe (en la lista general, como sinónimo o entre los suyos) se usa ese; si no, se crea.
            // El administrador ve los nuevos en su panel y los puede pasar a la lista de todos.
            $id = $ingredientes->idPorNombre($nombre, $usuarioId);
            if ($id === null) {
                $id = $ingredientes->crearPropio($usuarioId, $nombre, Perfil::CATEGORIA_NUEVOS, Perfil::UNIDAD_NUEVOS);
                $creados++;
            }
            $ids[] = $id;
        }
        $preferencias->reemplazar($usuarioId, $lista, $ids);
    }
    Sesion::avisar('exito', '¡Listo! Ya te mostramos recetas a tu medida. Lo cambiás cuando quieras en Mis preferencias.'
        . ($creados > 0 ? ' Lo que agregaste como nuevo le llega al administrador para sumarlo a la lista de todos.' : ''));
    redirigir('app/index.php');
}

$perfil = $preferencias->perfil($usuarioId);
$noComo = array_map('intval', array_column($preferencias->ingredientes($usuarioId, 'restricciones'), 'id'));
$basicos = array_map('intval', array_column($preferencias->ingredientes($usuarioId, 'basicos'), 'id'));
// Un grupo queda marcado si tiene todos sus ingredientes; lo demás que no come se muestra suelto
$gruposMarcados = array_filter($grupos, fn (array $ids) => array_diff($ids, $noComo) === []);
$noComoSueltos = array_values(array_diff($noComo, ...array_values($gruposMarcados ?: [[]])));
$basicosSueltos = array_values(array_diff($basicos, array_keys($siempreHay)));
$primeraVez = ($_GET['nueva'] ?? '') === '1';
$nombreUsuario = (string) strtok((string) (Sesion::usuario()['nombre'] ?? ''), ' ');

/** Una casilla con forma de sticker. $dibujo es la URL de una imagen o HTML ya armado (semáforo, número). */
$elegible = function (string $tipo, string $nombreCampo, string $valor, string $texto, string $dibujo, bool $marcado, string $clase = ''): void {
    static $n = 0;
    $id = 'elegible-' . (++$n);
    ?>
    <li class="elegible<?= $clase !== '' ? ' ' . e($clase) : '' ?>">
      <input type="<?= e($tipo) ?>" id="<?= e($id) ?>" name="<?= e($nombreCampo) ?>" value="<?= e($valor) ?>"<?= $marcado ? ' checked' : '' ?>>
      <label for="<?= e($id) ?>">
        <?php if (str_starts_with($dibujo, '<')) : ?>
          <?= $dibujo /* HTML armado acá mismo, sin datos del usuario */ ?>
        <?php else : ?>
          <img src="<?= e($dibujo) ?>" alt="" width="46" height="46">
        <?php endif; ?>
        <span><?= e($texto) ?></span>
      </label>
    </li>
    <?php
};
/** El buscador "¿Otra cosa?": sin JavaScript es un campo con la lista de ingredientes; bienvenida.js lo mejora. */
$buscador = function (string $campo, string $ejemplo): void {
    ?>
    <div class="bienvenida__buscar" data-buscar="<?= e($campo) ?>">
      <label for="<?= e($campo) ?>-otro">¿Otra cosa? Escribila:</label>
      <input id="<?= e($campo) ?>-otro" name="<?= e($campo) ?>_otro" type="text" list="ingredientes-bienvenida" maxlength="80"
             autocomplete="off" placeholder="<?= e($ejemplo) ?>">
    </div>
    <?php
};

$titulo = 'Contanos cómo comés';
require parcial('encabezado');
?>
<?php if (!$primeraVez) : ?>
  <?= enlaceVolver('app/mis-preferencias.php', 'Volver a Mis preferencias', [['app/index.php', 'Mi cocina']]) ?>
<?php endif; ?>
<div class="bienvenida-arriba">
  <h1>Contanos cómo comés</h1>
  <p class="texto-suave">Así te mostramos recetas que te sirvan. Tarda un minuto, todo es opcional y lo cambiás cuando
    quieras en <a href="<?= e(url('app/mis-preferencias.php')) ?>">Mis preferencias</a>.</p>
</div>

<ol class="pasos__puntos" aria-label="Pasos" hidden>
  <li aria-current="step"><span>1</span> Cómo comés</li>
  <li><span>2</span> Lo que no</li>
  <li><span>3</span> Tus gustos</li>
  <li><span>4</span> Lo que siempre hay</li>
</ol>

<form class="pasos bienvenida" method="post" action="<?= e(url('app/bienvenida.php')) ?>" data-bienvenida>
  <?= Sesion::campoCsrf() ?>

  <section class="postit paso paso--actual" aria-labelledby="paso-1">
    <span class="postit__chinche" aria-hidden="true"></span>
    <h2 id="paso-1"><?= $nombreUsuario !== '' ? '¡Hola, ' . e($nombreUsuario) . '! ' : '' ?>¿Cómo comés?</h2>
    <fieldset>
      <legend class="solo-lectores">Cómo comés</legend>
      <p class="ayuda">Podés marcar más de una. Solo te vamos a recomendar recetas que vayan con lo que marques.</p>
      <ul class="elegibles" data-dieta>
        <?php $elegible('checkbox', 'dieta[]', 'todo', 'Como de todo', url('img/tipos/todo.svg'), $perfil['dietas'] === []); ?>
        <?php foreach (Perfil::DIETAS as $dieta) : ?>
          <?php $texto = Tipos::nombre($dieta) . ($dieta === 'sin_tacc' ? ' (celíaca)' : ''); ?>
          <?php $elegible('checkbox', 'dieta[]', $dieta, $texto, url((string) Tipos::dibujo($dieta)), in_array($dieta, $perfil['dietas'], true)); ?>
        <?php endforeach; ?>
      </ul>
    </fieldset>
    <div class="paso__botones">
      <a href="<?= e(url('app/index.php')) ?>">Lo hago después</a>
      <button class="boton" type="button" data-paso="1" hidden>Siguiente</button>
    </div>
  </section>

  <section class="postit paso paso--rosa" aria-labelledby="paso-2">
    <span class="postit__chinche" aria-hidden="true"></span>
    <h2 id="paso-2">¿Hay algo que no comas?</h2>
    <fieldset>
      <legend class="solo-lectores">Lo que no comés</legend>
      <p class="ayuda">Alergias o cosas que no te gustan: nunca te vamos a recomendar recetas que las lleven.</p>
      <ul class="elegibles" data-lista="nocomo">
        <?php foreach (Perfil::NO_COMO as $clave => [$texto, $dibujo]) : ?>
          <?php if (isset($grupos[$clave])) : ?>
            <?php $elegible('checkbox', 'nocomo[]', 'g:' . $clave, $texto, url('img/ingredientes/' . $dibujo . '.svg'), isset($gruposMarcados[$clave])); ?>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php foreach ($noComoSueltos as $id) : ?>
          <?php if (isset($catalogo[$id])) : ?>
            <?php $ing = $catalogo[$id]; ?>
            <?php $elegible('checkbox', 'nocomo[]', 'i:' . $id, $ing['nombre'], iconoIngrediente($ing['nombre'], (bool) $ing['propio']), true); ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </fieldset>
    <?php $buscador('nocomo', 'Ej.: berenjena, palta, maní…'); ?>
    <div class="paso__botones">
      <button class="boton boton--secundario" type="button" data-paso="-1" hidden>Atrás</button>
      <button class="boton" type="button" data-paso="1" hidden>Siguiente</button>
    </div>
  </section>

  <section class="postit paso paso--celeste" aria-labelledby="paso-3">
    <span class="postit__chinche" aria-hidden="true"></span>
    <h2 id="paso-3">¿Qué te gusta cocinar?</h2>
    <fieldset>
      <legend>¿Qué te gusta?</legend>
      <p class="ayuda">Esas recetas te las mostramos primero.</p>
      <ul class="elegibles">
        <?php foreach (Perfil::GUSTOS as $gusto => $texto) : ?>
          <?php $elegible('checkbox', 'gustos[]', $gusto, $texto, url((string) Tipos::dibujo($gusto)), in_array($gusto, $perfil['gustos'], true)); ?>
        <?php endforeach; ?>
      </ul>
    </fieldset>
    <fieldset>
      <legend>¿Cómo te llevás con la cocina?</legend>
      <p class="ayuda">Si recién empezás, las difíciles quedan más abajo (pero no se esconden).</p>
      <ul class="elegibles">
        <?php foreach (Perfil::NIVELES as $nivel => $texto) : ?>
          <?php $elegible('radio', 'nivel', (string) $nivel, $texto, '<span class="dificultad dificultad--' . $nivel . ' dificultad--grande"><span class="dificultad__luz"></span></span>', $perfil['nivel'] === $nivel, 'elegible--ancho'); ?>
        <?php endforeach; ?>
      </ul>
    </fieldset>
    <fieldset>
      <legend>¿Para cuántos cocinás?</legend>
      <p class="ayuda">Con esas porciones se anotan las comidas del plan semanal.</p>
      <ul class="elegibles">
        <?php foreach (Perfil::COMENSALES as $cuantos => $texto) : ?>
          <?php $elegible('radio', 'comensales', (string) $cuantos, $texto, '<span class="elegible__numero" aria-hidden="true">' . ($cuantos === 5 ? '5+' : $cuantos) . '</span>', $perfil['comensales'] === $cuantos, 'elegible--chico'); ?>
        <?php endforeach; ?>
      </ul>
    </fieldset>
    <div class="paso__botones">
      <button class="boton boton--secundario" type="button" data-paso="-1" hidden>Atrás</button>
      <button class="boton" type="button" data-paso="1" hidden>Siguiente</button>
    </div>
  </section>

  <section class="postit paso paso--verde" aria-labelledby="paso-4">
    <span class="postit__chinche" aria-hidden="true"></span>
    <h2 id="paso-4">¿Qué tenés siempre en casa?</h2>
    <fieldset>
      <legend class="solo-lectores">Lo que siempre tenés</legend>
      <p class="ayuda">Cuenta como que lo tenés en todas las recetas y no se descuenta al cocinar. La sal, el aceite y la
        pimienta ya cuentan siempre.</p>
      <ul class="elegibles" data-lista="basicos">
        <?php foreach ($siempreHay as $id => $nombreIng) : ?>
          <?php $elegible('checkbox', 'basicos[]', 'i:' . $id, $nombreIng === 'Harina 0000' ? 'Harina' : $nombreIng, iconoIngrediente($nombreIng), in_array($id, $basicos, true)); ?>
        <?php endforeach; ?>
        <?php foreach ($basicosSueltos as $id) : ?>
          <?php if (isset($catalogo[$id])) : ?>
            <?php $ing = $catalogo[$id]; ?>
            <?php $elegible('checkbox', 'basicos[]', 'i:' . $id, $ing['nombre'], iconoIngrediente($ing['nombre'], (bool) $ing['propio']), true); ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </ul>
    </fieldset>
    <?php $buscador('basicos', 'Ej.: polenta, lentejas, café…'); ?>
    <div class="paso__botones">
      <button class="boton boton--secundario" type="button" data-paso="-1" hidden>Atrás</button>
      <button class="boton" type="submit">Listo, ir a mi cocina</button>
    </div>
  </section>
</form>

<?php /* Para el buscador: sin JavaScript, sugerencias del navegador; con JavaScript, stickers (bienvenida.js) */ ?>
<datalist id="ingredientes-bienvenida">
  <?php foreach ($catalogo as $id => $ing) : ?>
    <option value="<?= e($ing['nombre']) ?>" data-id="<?= e($id) ?>"
            data-dibujo="<?= e(iconoIngrediente($ing['nombre'], (bool) $ing['propio'])) ?>"
            data-buscar="<?= e(mb_strtolower($ing['nombre'] . ',' . $ing['sinonimos'])) ?>"></option>
  <?php endforeach; ?>
</datalist>
<script src="<?= e(recurso('js/bienvenida.js')) ?>" data-frasco="<?= e(url('img/ingredientes/frasco.svg')) ?>" defer></script>
<?php require parcial('pie'); ?>
