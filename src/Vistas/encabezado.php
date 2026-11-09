<?php
/**
 * Parte de arriba de todas las páginas: <head>, menú de navegación y avisos.
 * Antes de incluirlo, la página define $titulo. Uso:
 *   $titulo = 'Mi despensa';
 *   require parcial('encabezado');
 */

use Cooki\Repositorios\UsuarioRepositorio;
use Cooki\Sesion;

$usuario = Sesion::usuario();
$avisos = Sesion::avisos();
$actual = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$carpeta = basename(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));

/** Devuelve aria-current si el enlace es la página actual (el CSS lo resalta). */
$marcar = function (string $ruta) use ($actual, $carpeta): string {
    $partes = explode('/', $ruta);
    $archivo = end($partes);
    $dir = count($partes) > 1 ? $partes[0] : '';
    $mismaCarpeta = $dir === '' ? !in_array($carpeta, ['app', 'admin'], true) : $carpeta === $dir;
    return $mismaCarpeta && $archivo === $actual ? ' aria-current="page"' : '';
};

// Todos los enlaces de la barra son imanes (sin cuenta también Ingresar y Crear cuenta)
if ($usuario !== null) {
    $menu = [
        ['app/index.php', 'Mi cocina'], ['app/despensa.php', 'Mi despensa'], ['app/recetas.php', 'Recetas'],
        ['app/lista.php', 'Lista'],
    ];
    if ($usuario['rol'] === Sesion::ROL_ADMIN) {
        $menu[] = ['admin/index.php', 'Administración'];
    }
    $menu[] = ['contacto.php', 'Contacto'];
} else {
    // "Nosotros" (Quiénes somos) va solo en el menú sin cuenta
    $menu = [
        ['index.php', 'Inicio'], ['nosotros.php', 'Nosotros'], ['contacto.php', 'Contacto'],
        ['login.php', 'Ingresar'], ['registro.php', 'Crear cuenta'],
    ];
}

/** Los imanes de colores para elegir el color de la heladera (con y sin cuenta). */
$imanesColor = function (): void {
    foreach (UsuarioRepositorio::COLORES as $clave => $nombre) : ?>
                  <button class="colores__iman colores__iman--<?= e($clave) ?>" type="submit" name="color" value="<?= e($clave) ?>"
                          title="<?= e($nombre) ?>" aria-pressed="<?= Sesion::color() === $clave ? 'true' : 'false' ?>">
                    <span class="solo-lectores"><?= e($nombre) ?></span>
                  </button>
    <?php endforeach;
};
?>
<!doctype html>
<html lang="es-AR" data-color="<?= e(Sesion::color()) ?>" data-disposicion="<?= e(Sesion::disposicion()) ?>"<?= $usuario ? ' data-volumen="' . e(Sesion::volumen()) . '"' : '' ?>>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($titulo ?? 'Cooki') ?> · Cooki</title>
  <meta name="description" content="Cooki: tu despensa y qué cocinar con lo que tenés">
  <?php /* recurso(): agrega ?v=fecha del archivo, así el navegador baja el CSS y el JS nuevos cada vez que cambian */ ?>
  <link rel="icon" type="image/svg+xml" href="<?= e(url('img/ingredientes/fideos.svg')) ?>">
  <link rel="stylesheet" href="<?= e(recurso('css/estilos.css')) ?>">
  <script src="<?= e(recurso('js/sitio.js')) ?>" defer></script>
  <script src="<?= e(recurso('js/sonidos.js')) ?>" defer></script>
  <script src="<?= e(recurso('js/manija.js')) ?>" defer></script>
</head>
<body>
<header class="barra">
  <div class="barra__interior">
    <?php /* El dibujo de los fideos es el de la aplicación: va al lado del nombre y en la pestaña */ ?>
    <a class="marca" href="<?= e(url($usuario ? 'app/index.php' : 'index.php')) ?>">
      <img class="marca__dibujo" src="<?= e(url('img/ingredientes/fideos.svg')) ?>" alt="" width="46" height="46">
      <?php /* Cada letra es un <span> para que salten de a una al pasar el mouse (los lectores leen la palabra) */ ?>
      <span class="marca__rotulo">
        <span class="solo-lectores">Cooki</span>
        <?php foreach (mb_str_split('Cooki') as $i => $letra) : ?><span class="marca__letra" style="--i: <?= $i ?>" aria-hidden="true"><?= e($letra) ?></span><?php endforeach; ?>
      </span>
      <span class="marca__vapor" aria-hidden="true"><i></i><i></i><i></i></span>
    </a>
    <button class="barra__boton" type="button" aria-expanded="false" aria-controls="menu-principal">Menú</button>
    <nav id="menu-principal" class="menu" aria-label="Principal">
      <ul<?= $usuario === null ? ' class="menu__invitado"' : '' ?>>
        <?php foreach ($menu as [$ruta, $texto]) : ?>
          <li><a href="<?= e(url($ruta)) ?>"<?= $marcar($ruta) ?>><?= e($texto) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <div class="menu__cuenta">
        <?php if ($usuario !== null) : ?>
          <?php /* Menú de la cuenta: un <details> funciona sin JavaScript; sitio.js lo cierra al tocar afuera */ ?>
          <details class="cuenta">
            <summary class="cuenta__resumen">
              <span class="cuenta__nombre"><?= e(explode(' ', $usuario['nombre'])[0]) ?><span class="cuenta__flecha" aria-hidden="true"></span></span>
            </summary>
            <div class="cuenta__panel">
              <p class="cuenta__quien">
                <strong><?= e($usuario['nombre']) ?></strong>
                <small><?= e($usuario['email']) ?><?= $usuario['rol'] === Sesion::ROL_ADMIN ? ' · administración' : '' ?></small>
              </p>
              <?php /* Mi cuenta y Mis preferencias: dos imanes chicos con el texto a mano */ ?>
              <div class="cuenta__enlaces">
                <a class="cuenta__enlace cuenta__enlace--cuenta" href="<?= e(url('app/perfil.php')) ?>"<?= $carpeta === 'app' && $actual === 'perfil.php' ? ' aria-current="page"' : '' ?>>Mi cuenta</a>
                <a class="cuenta__enlace cuenta__enlace--preferencias" href="<?= e(url('app/mis-preferencias.php')) ?>"<?= $carpeta === 'app' && $actual === 'mis-preferencias.php' ? ' aria-current="page"' : '' ?>>Mis preferencias</a>
              </div>
              <form class="preferencias" id="form-preferencias" method="post" action="<?= e(url('app/preferencias.php')) ?>">
                <?= Sesion::campoCsrf() ?>
                <input type="hidden" name="volver" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                <fieldset class="colores">
                  <legend>Color de la heladera</legend>
                  <?php $imanesColor(); ?>
                </fieldset>
                <fieldset class="disposicion">
                  <legend>Disposición <small>(en la computadora)</small></legend>
                  <?php foreach (UsuarioRepositorio::DISPOSICIONES as $clave => $nombre) : ?>
                    <button class="disposicion__boton disposicion__boton--<?= e($clave) ?>" type="submit" name="disposicion" value="<?= e($clave) ?>"
                            aria-pressed="<?= Sesion::disposicion() === $clave ? 'true' : 'false' ?>"><?= e($nombre) ?></button>
                  <?php endforeach; ?>
                </fieldset>
              </form>
              <?php /* Imán para prender o apagar los sonidos: lo muestra sonidos.js (sin JavaScript no hay sonidos) */ ?>
              <button class="iman-sonido" type="button" aria-pressed="false" hidden>
                <span class="iman-sonido__texto">Sonidos</span>
              </button>
              <?php /* Volumen: se guarda en la cuenta. Sin JavaScript no hay sonidos, así que la barra no aparece */ ?>
              <label class="volumen" hidden>
                <span class="volumen__texto">Volumen</span>
                <input class="volumen__barra" type="range" min="0" max="100" step="5" value="<?= e(Sesion::volumen()) ?>"
                       name="volumen" form="form-preferencias">
                <output class="volumen__valor"><?= e(Sesion::volumen()) ?></output>
              </label>
              <?php /* Salir: con JavaScript pregunta "¿Seguro?" en el mismo lugar antes de cerrar la sesión */ ?>
              <form class="salir" method="post" action="<?= e(url('salir.php')) ?>">
                <?= Sesion::campoCsrf() ?>
                <p class="salir__pregunta" hidden>¿Seguro que querés salir?</p>
                <div class="salir__botones">
                  <button class="boton boton--secundario cuenta__salir" type="submit">Salir</button>
                  <button class="boton boton--secundario salir__no" type="button" hidden>No, me quedo</button>
                </div>
              </form>
            </div>
          </details>
        <?php else : ?>
          <?php /* Sin cuenta: "Ajustes" despliega una hoja con el color de la heladera (cookie), el sonido y el volumen */ ?>
          <details class="cuenta cuenta--ajustes">
            <summary class="cuenta__resumen cuenta__resumen--ajustes">
              <span class="cuenta__nombre">Ajustes<span class="cuenta__flecha" aria-hidden="true"></span></span>
            </summary>
            <div class="cuenta__panel">
              <form class="preferencias" method="post" action="<?= e(url('app/preferencias.php')) ?>">
                <?= Sesion::campoCsrf() ?>
                <input type="hidden" name="volver" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '')) ?>">
                <fieldset class="colores">
                  <legend>Color de la heladera</legend>
                  <?php $imanesColor(); ?>
                </fieldset>
              </form>
              <?php /* Sonido y volumen: los muestra sonidos.js (sin JavaScript no hay sonidos). El volumen se guarda en el navegador */ ?>
              <button class="iman-sonido" type="button" aria-pressed="false" hidden>
                <span class="iman-sonido__texto">Sonidos</span>
              </button>
              <label class="volumen" hidden>
                <span class="volumen__texto">Volumen</span>
                <input class="volumen__barra" type="range" min="0" max="100" step="5" value="50">
                <output class="volumen__valor">50</output>
              </label>
              <p class="cuenta__nota">Con una cuenta, el color queda guardado en todos tus dispositivos.</p>
            </div>
          </details>
        <?php endif; ?>
      </div>
    </nav>
  </div>
</header>
<main class="contenido" id="contenido">
<?php foreach ($avisos as $aviso) : ?>
  <p class="aviso aviso--<?= e($aviso['tipo']) ?>" role="<?= $aviso['tipo'] === 'error' ? 'alert' : 'status' ?>"><?= e($aviso['mensaje']) ?></p>
<?php endforeach; ?>
