<?php

/**
 * Funciones de ayuda para las páginas. Se cargan solas desde src/bootstrap.php.
 */

declare(strict_types=1);

/**
 * Escapa un valor para mostrarlo dentro del HTML. Evita la inyección de código (XSS).
 * Regla: todo dato que venga de la base o del usuario se imprime con e().
 */
function e(string|int|float|null $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

/**
 * Arma la dirección de una página del sitio, por ejemplo url('admin/recetas.php').
 * Funciona igual en Docker, con `composer servidor` y en XAMPP dentro de una subcarpeta.
 */
function url(string $ruta = ''): string
{
    static $base = null;
    if ($base === null) {
        $publico = str_replace('\\', '/', (string) realpath(dirname(__DIR__) . '/public'));
        $script = str_replace('\\', '/', (string) realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $relativo = str_starts_with($script, $publico . '/') ? substr($script, strlen($publico) + 1) : '';
        $nombre = (string) ($_SERVER['SCRIPT_NAME'] ?? '/');
        $base = ($relativo !== '' && str_ends_with($nombre, $relativo))
            ? substr($nombre, 0, -strlen($relativo))
            : '/';
    }
    return $base . ltrim($ruta, '/');
}

/**
 * Dirección completa (con https://dominio) de una página, para los enlaces que van en los correos.
 * Si en .env está APP_URL (en el hosting conviene ponerla), se usa esa; si no, la del pedido actual.
 */
function urlAbsoluta(string $ruta): string
{
    $base = rtrim(\Cooki\Config::get('APP_URL'), '/');
    if ($base !== '') {
        return $base . '/' . ltrim($ruta, '/');
    }
    $https = esHttps();
    $host = preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https://' : 'http://') . $host . url($ruta);
}

/**
 * Enlace a otra página de un listado, conservando la búsqueda y los filtros de la URL actual.
 * enlacePagina('app/recetas.php', 3) → "/app/recetas.php?q=tarta&pagina=3"
 */
function enlacePagina(string $ruta, int $pagina): string
{
    $parametros = array_merge($_GET, ['pagina' => $pagina]);
    if ($pagina <= 1) {
        unset($parametros['pagina']);
    }
    return url($ruta) . ($parametros === [] ? '' : '?' . http_build_query($parametros));
}

/** true si la página llegó por https (también detrás del proxy de un hosting, que avisa con X-Forwarded-Proto). */
function esHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Redirige a otra página del sitio y corta la ejecución. */
function redirigir(string $ruta): never
{
    header('Location: ' . url($ruta));
    exit;
}

/** Ruta de una parte común de las páginas (encabezado, pie). Uso: require parcial('encabezado'); */
function parcial(string $nombre): string
{
    return __DIR__ . '/Vistas/' . $nombre . '.php';
}

/** true si la página se pidió enviando un formulario (POST). */
function esPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Lee un campo de texto de un formulario, sin espacios al principio ni al final. */
function campo(string $nombre): string
{
    return trim((string) ($_POST[$nombre] ?? ''));
}

/**
 * Ícono (sticker) de un ingrediente para el estilo Heladera: public/img/ingredientes/<nombre>.svg.
 * Primero busca el nombre completo sin tildes ("Cebolla de verdeo" → cebolla-de-verdeo.svg);
 * si no está, la primera palabra ("Pollo asado" → pollo.svg, "Cebolla morada" → cebolla.svg).
 * Si no hay dibujo, devuelve el frasco genérico. Los ingredientes propios (los que un usuario agregó
 * desde su despensa) siempre llevan el frasco: es el dibujo que no usa ningún producto de la lista.
 */
function iconoIngrediente(string $nombre, bool $propio = false): string
{
    if ($propio) {
        return url('img/ingredientes/frasco.svg');
    }
    $palabras = preg_split('/[^a-z]+/', \Cooki\Dominio\Texto::sinTildes($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ([implode('-', $palabras), $palabras[0] ?? ''] as $candidato) {
        $archivo = 'img/ingredientes/' . $candidato . '.svg';
        if ($candidato !== '' && is_file(dirname(__DIR__) . '/public/' . $archivo)) {
            return url($archivo);
        }
    }
    return url('img/ingredientes/frasco.svg');
}

/**
 * Páginas a las que se puede "volver", con el nombre que se lee en la flecha.
 * Las que empiezan con "el" se leen "Volver al …".
 */
const PAGINAS_PARA_VOLVER = [
    'index.php' => 'el inicio',
    'nosotros.php' => 'Nosotros',
    'contacto.php' => 'Contacto',
    'app/index.php' => 'Mi cocina',
    'app/despensa.php' => 'Mi despensa',
    'app/lista.php' => 'la lista de compras',
    'app/plan.php' => 'el plan',
    'app/recetas.php' => 'Recetas',
    'app/receta.php' => 'la receta',
    'app/receta-ia.php' => 'la receta',
    'app/mis-recetas-ia.php' => 'Mis recetas de IA',
    'app/perfil.php' => 'Mi cuenta',
    'app/mis-preferencias.php' => 'Mis preferencias',
    'app/bienvenida.php' => 'Cómo comés',
    'admin/index.php' => 'el panel',
    'admin/recetas.php' => 'Recetas',
    'admin/ingredientes.php' => 'Ingredientes',
    'admin/usuarios.php' => 'Usuarios',
    'admin/consultas.php' => 'Consultas',
    'admin/ia.php' => 'la IA',
];

/** "Volver a Recetas", "Volver al panel"… */
function textoVolver(string $nombre): string
{
    return str_starts_with($nombre, 'el ') ? 'Volver al ' . substr($nombre, 3) : 'Volver a ' . $nombre;
}

/** Ruta de una dirección de este sitio sin la base: "/cooki/public/app/receta.php" → "app/receta.php". */
function rutaDelSitio(string $camino): ?string
{
    $base = url();
    return str_starts_with($camino, $base) ? substr($camino, strlen($base)) : null;
}

/**
 * La página de la que vino quien está mirando esta (la dirección anterior del navegador),
 * si es una página de este sitio a la que se puede volver. Se recuerda en la sesión por página,
 * así se mantiene aunque la página se recargue a sí misma (cambiar porciones, guardar un favorito…).
 * Los enlaces de volver llevan ?atras=1: al volver no se pisa lo recordado (si no, A → B → volver a A
 * haría que A "vuelva" a B).
 * @return array{url: string, ruta: string, nombre: string}|null
 */
function paginaAnterior(): ?array
{
    $actual = rutaDelSitio((string) parse_url((string) ($_SERVER['SCRIPT_NAME'] ?? ''), PHP_URL_PATH));
    if ($actual === null || session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }
    $previa = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''));
    $hostPrevio = is_array($previa) ? ($previa['host'] ?? '') : '';
    if ($hostPrevio !== '' && isset($previa['port'])) {
        $hostPrevio .= ':' . $previa['port'];
    }
    $mismoSitio = $hostPrevio !== '' && strcasecmp($hostPrevio, (string) ($_SERVER['HTTP_HOST'] ?? '')) === 0;
    $ruta = $mismoSitio ? rutaDelSitio((string) ($previa['path'] ?? '')) : null;
    if ($ruta === '') {
        $ruta = 'index.php';
    }
    if ($ruta !== null && $ruta !== $actual && isset(PAGINAS_PARA_VOLVER[$ruta]) && !isset($_GET['atras'])) {
        parse_str((string) ($previa['query'] ?? ''), $parametros);
        unset($parametros['atras']);
        $_SESSION['volver'][$actual] = ['ruta' => $ruta, 'parametros' => $parametros];
    }
    $recordada = $_SESSION['volver'][$actual] ?? null;
    if (!is_array($recordada) || !isset(PAGINAS_PARA_VOLVER[$recordada['ruta'] ?? ''])) {
        return null;
    }
    $parametros = (array) $recordada['parametros'] + ['atras' => 1];
    return [
        'url' => url($recordada['ruta']) . '?' . http_build_query($parametros),
        'ruta' => $recordada['ruta'],
        'nombre' => PAGINAS_PARA_VOLVER[$recordada['ruta']],
    ];
}

/**
 * Flecha de volver que va arriba de las páginas: una flecha dibujada a mano y el texto en letra de birome.
 *   $ruta, $texto  adónde va si no se sabe de dónde vino (o si $fija es true: un formulario dentro de un listado)
 *   $atajos        otros lugares a los que ir, como papelitos al lado: [['app/recetas.php', 'Recetas'], ...]
 *                  (el que es igual a adonde vuelve la flecha no se muestra)
 * Uso: <?= enlaceVolver('app/recetas.php', 'Volver a Recetas', [['app/index.php', 'Mi cocina']]) ?>
 * @param list<array{0: string, 1: string}> $atajos
 */
function enlaceVolver(string $ruta, string $texto, array $atajos = [], bool $fija = false): string
{
    $anterior = $fija ? null : paginaAnterior();
    $destino = $anterior['ruta'] ?? (string) strtok($ruta, '?');
    $html = '<nav class="volver-barra" aria-label="Volver">'
        . '<a class="volver" href="' . e($anterior['url'] ?? url($ruta)) . '">'
        . '<span class="volver__flecha" aria-hidden="true"></span>'
        . '<span class="volver__texto">'
        . e($anterior !== null ? textoVolver($anterior['nombre']) : $texto) . '</span></a>';
    $otros = array_filter($atajos, fn (array $atajo): bool => (string) strtok($atajo[0], '?') !== $destino);
    if ($otros !== []) {
        $html .= '<span class="volver-barra__atajos"><span class="volver-barra__ir">o ir a</span>';
        foreach ($otros as [$rutaAtajo, $textoAtajo]) {
            $html .= ' <a class="atajo" href="' . e(url($rutaAtajo)) . '">' . e($textoAtajo) . '</a>';
        }
        $html .= '</span>';
    }
    return $html . '</nav>';
}

/**
 * URL de un archivo de public/ (CSS o JS) con su fecha de modificación al final (?v=…).
 * Así el navegador baja la versión nueva cada vez que el archivo cambia, en lugar de usar la guardada.
 */
function recurso(string $ruta): string
{
    $archivo = dirname(__DIR__) . '/public/' . $ruta;
    return url($ruta) . (is_file($archivo) ? '?v=' . filemtime($archivo) : '');
}

/**
 * Los tipos de una receta como cintas con su sticker (Dominio\Tipos), en el orden de siempre.
 * Uso: <?= tiposReceta(['sin_tacc', 'vegetariana']) ?>
 * @param list<string> $claves claves de la tabla etiqueta ("sin_tacc")
 */
function tiposReceta(array $claves, string $clase = ''): string
{
    $claves = \Cooki\Dominio\Tipos::ordenar($claves);
    if ($claves === []) {
        return '';
    }
    $html = '<ul class="tipos' . ($clase !== '' ? ' ' . e($clase) : '') . '" aria-label="Tipo de receta">';
    foreach ($claves as $clave) {
        $dibujo = \Cooki\Dominio\Tipos::dibujo($clave);
        $html .= '<li class="tipo" title="' . e(\Cooki\Dominio\Tipos::explicacion($clave)) . '">'
            . ($dibujo !== null ? '<img src="' . e(url($dibujo)) . '" alt="" width="38" height="38">' : '')
            . '<span>' . e(\Cooki\Dominio\Tipos::nombre($clave)) . '</span></li>';
    }
    return $html . '</ul>';
}

/**
 * La dificultad con un semáforo: verde Fácil, amarillo Media, rojo Difícil. El texto va siempre al lado
 * (el color solo no alcanza para quien no distingue colores).
 */
function dificultadReceta(int $nivel): string
{
    $texto = \Cooki\Repositorios\RecetaRepositorio::DIFICULTADES[$nivel] ?? '';
    if ($texto === '') {
        return '';
    }
    return '<span class="dificultad dificultad--' . $nivel . '">'
        . '<span class="dificultad__luz" aria-hidden="true"></span>' . e($texto) . '</span>';
}

/**
 * Lista desplegable con un dibujo en cada opción (en Recetas: dificultad, tipo y orden). Un <select> no puede
 * mostrar dibujos: es un <details> con botones de opción (radio). Sin JavaScript se abre, se elige y se manda
 * el formulario; con JavaScript (js/desplegable.js) se cierra al elegir y muestra arriba lo elegido.
 *   $opciones  valor => [texto, dibujo]; dibujo es HTML ya armado por la página (nunca datos del usuario)
 * Uso: <?= desplegable('orden', 'Ordenar', ['az' => ['A a la Z', '<img …>']], $orden) ?>
 * @param array<string|int, array{0: string, 1: string}> $opciones
 */
function desplegable(string $nombre, string $rotulo, array $opciones, string|int $elegido): string
{
    $elegido = isset($opciones[$elegido]) ? $elegido : array_key_first($opciones);
    [$textoElegido, $dibujoElegido] = $opciones[$elegido];
    $id = 'desplegable-' . $nombre;
    $html = '<span class="campo__rotulo" id="' . e($id) . '">' . e($rotulo) . '</span>'
        . '<details class="desplegable" data-desplegable>'
        . '<summary class="desplegable__resumen" aria-describedby="' . e($id) . '">'
        . '<span class="desplegable__dibujo">' . $dibujoElegido . '</span>'
        . '<span class="desplegable__texto">' . e($textoElegido) . '</span></summary>'
        . '<fieldset class="desplegable__panel"><legend class="solo-lectores">' . e($rotulo) . '</legend>';
    foreach ($opciones as $valor => [$texto, $dibujo]) {
        $html .= '<label class="desplegable__opcion">'
            . '<input type="radio" name="' . e($nombre) . '" value="' . e($valor) . '"'
            . ((string) $valor === (string) $elegido ? ' checked' : '') . '>'
            . '<span class="desplegable__dibujo">' . $dibujo . '</span>'
            . '<span class="desplegable__texto">' . e($texto) . '</span></label>';
    }
    return $html . '</fieldset></details>';
}
