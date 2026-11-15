# Estilo "Heladera" · Qué cambia respecto del proyecto base

> Los cambios de la tanda 1 (relieve, renglones, dibujos de los 43 ingredientes, editor como nota despegada, cantidad exacta, colores por usuario, selector de Mi cocina y sonidos) están en [estilo-heladera-tanda-1.md](estilo-heladera-tanda-1.md).
> Los de la tanda 2 (sonidos en todo, menú de la cuenta, hojas que se levantan, pasos con casilla, vencidos y contador, disposición panorámica) están en [estilo-heladera-tanda-2.md](estilo-heladera-tanda-2.md).
> Los de la tanda 3 (inicio nuevo con vitrina, manija desplazadora, volumen, administración con dibujos, 76 ingredientes con dibujo) están en [estilo-heladera-tanda-3.md](estilo-heladera-tanda-3.md).
> Los de la tanda 4 (barra toda de imanes, color sin cuenta, dibujos en las partes vacías, buscador con stickers e ingredientes propios, aviso al borrar, responder consultas, ver la contraseña y confirmar al salir) están en [estilo-heladera-tanda-4.md](estilo-heladera-tanda-4.md).
> Los de la tanda 5, del incremento 3 (menú de stickers con dibujo, Contacto y Quiénes somos en el pie, notas que se enderezan, letras sin salto, saltar al contenido, foco visible y el plan semanal), están en [estilo-heladera-tanda-5.md](estilo-heladera-tanda-5.md).
> Los de la tanda 6, también del incremento 3 (tipos de receta con sticker, dificultad con semáforo, listas desplegables con dibujo, la bienvenida paso a paso y "¿Falta algún tipo?"), están en [estilo-heladera-tanda-6.md](estilo-heladera-tanda-6.md).

La versión Heladera es **el mismo proyecto** (mismas páginas, base de datos, consultas, validaciones y pruebas) con otra estética: la página es la puerta de una heladera.

- Los ingredientes de la despensa son **stickers** que se pueden poner y quitar.
- Los avisos y "Usalo pronto" son **post-its** con chinche.
- Las tarjetas son **hojas de cuaderno** con renglones, margen rojo y cinta.
- El menú son **stickers** con dibujo (antes, imanes de colores: ver la tanda 5).
- El logo es un **rótulo** de letras de imprenta.

Se probó igual que la base: `composer lint` sin errores, **22 pruebas en verde** y recorrido en el navegador a 390 px y a 1280 px.

**Casi todo el cambio está en el CSS.** En PHP se tocan solo 6 archivos, y en todos los casos son agregados de 1 a 6 líneas de HTML. No cambia ninguna consulta, validación ni regla de seguridad.

---

## Resumen

| Archivo | Tipo de cambio | De quién es en el reparto |
|---|---|---|
| `public/css/estilos.css` | **Reescrito**: mismos nombres de clase, otro aspecto | P2 (parte común) + secciones de P2, P3 y P4 |
| `public/fonts/` | **Nuevo**: 5 tipografías (`.woff2`) + sus licencias | P2 |
| `public/img/ingredientes/` | **Nuevo**: 13 íconos SVG (12 ingredientes + frasco genérico) | P2 |
| `public/img/portada.svg` | **Borrado**: lo reemplazan los stickers de la portada | P2 |
| `src/ayudas.php` | **Agregado** al final: función `iconoIngrediente()` | Común (avisar) |
| `src/Vistas/encabezado.php` | Frase "la puerta de tu heladera" al lado del logo, y `?v=` en el enlace al CSS para que el navegador no use el viejo | Común (avisar) |
| `public/index.php` | Cambia la imagen de la portada por una puerta con 8 stickers | P2 |
| `public/app/index.php` | "Usalo pronto" pasa a ser un post-it, con ícono en cada ingrediente | P2 |
| `public/app/despensa.php` | Sticker del ingrediente y globo "¡ya!" en cada fila | P2 |
| `public/app/receta.php` | Ícono chico al lado de cada ingrediente | P3 |

**No cambia:** la base de datos (`database/`), los repositorios (`src/Repositorios/`), `Sesion.php`, el JavaScript (`public/js/`), las páginas de administración, login, registro, error y contacto (solo cambian por el CSS), las pruebas, Docker ni la API.

---

## 1. `public/css/estilos.css` (reescrito)

Se mantiene **la misma estructura y los mismos nombres de clase**: parte común arriba y las secciones de P2, P3 y P4 debajo de sus comentarios. Por eso las páginas no hay que tocarlas para que tomen el estilo nuevo.

### Parte común (P2)

| Bloque | Antes | Ahora |
|---|---|---|
| **Al principio, nuevo** | — | `@font-face` de **Caveat** (letra a mano), **Figtree** (texto) y **Space Mono** (rótulos), desde `public/fonts/` |
| `:root` (variables) | Terracota sobre crema, bordes redondeados | Verde agua de heladera (`--color-fondo: #CFE6DC`), papel (`#FFFDF6`), tinta (`#1E2B26`), rojo birome (`--color-marca: #D3302A`). **Variables nuevas:** `--color-azul`, `--color-renglon`, `--color-margen`, `--color-cinta`, `--color-postit`, `--sombra-iman`, `--fuente-mano`, `--fuente-rotulo` |
| `body` | Fondo liso | Rayitas verticales y brillo diagonal (la chapa de la puerta). `body::after` dibuja **la manija**; se oculta en pantallas angostas |
| `h1` | Sans-serif | Letra a mano (Caveat) |
| `.barra`, `.marca` | Barra blanca con el nombre en color | Sin barra. El nombre es un **rótulo negro inclinado** en Space Mono. **Nuevas:** `.marca-grupo` y `.marca__bajada` |
| `.menu ul a` | Links con fondo al pasar el mouse | **Imanes**: un círculo de color arriba de cada link (`::before`), con un color distinto por posición (`nth-child`). El activo lleva un contorno punteado |
| `.tarjeta` | Caja blanca con borde | **Hoja de cuaderno**: renglones (`repeating-linear-gradient`), margen rojo (`::before`) y cinta rosa arriba (`::after`). El `h2` va en letra a mano roja |
| `.grilla > .tarjeta` | Rectas | Inclinación leve alternada y cinta celeste en una de cada tres |
| `.aviso` | Banda de color | **Post-it** inclinado con chinche azul (`::before`): amarillo (info), verde (éxito), rosa (error) |
| `.boton` | Rectángulo terracota | Píldora negra con sombra "de apretar". Secundario: papel con borde negro. Peligro: borde rojo |
| Campos de formulario | Borde fino gris | Borde negro de 2 px, foco azul. Casillas rojas (`accent-color`) |
| `.tabla-contenedor`, `th` | Tabla con bordes | Hoja de papel con líneas punteadas. Encabezados en Space Mono |
| `.etiqueta` | Píldora | **Tira de cinta de papel** inclinada, en letra a mano |
| `.vacio`, `.error-campo`, `.pie` | Texto normal | Letra a mano |
| **Nuevas al final** | — | `.icono-chico` (íconos de 28 px) y `prefers-reduced-motion`, que apaga las animaciones a quien las tiene desactivadas en su sistema |

### Sección P2 · Página principal, Mi cocina y Mi despensa

| Bloque | Ahora |
|---|---|
| `.portada__titulo` | El nombre "COOKI" como rótulo grande inclinado |
| **Nuevas** `.portada__puerta`, `.sticker`, `.sticker--1` a `--8`, `@keyframes pegar` | La puerta punteada con 8 stickers que "se pegan" con una animación al cargar. El borde blanco de cada sticker es un `filter: drop-shadow` |
| `.pasos-portada h3` | Títulos a mano en rojo |
| **Nuevas** `.postit`, `.postit__chinche` | Post-it amarillo de "Usalo pronto" en Mi cocina |
| `.pestanas a` | Imanes blancos; el activo, negro |
| `.nivel--0` a `--3` | Píldoras en letra a mano |
| **Nuevas** `#tabla-despensa ...`, `.sticker-ingrediente`, `.ya` | **La tabla de la despensa se dibuja como una grilla de stickers**: cada fila es un sticker inclinado con su nombre en cinta y sus botones abajo. Se oculta el encabezado de la tabla. El globo rojo "¡ya!" marca lo que vence hoy o mañana. Hay un ajuste para el celular (2 columnas) |

### Sección P3 · Recetas

| Bloque | Ahora |
|---|---|
| `.tarjeta-receta h2` | Título en negrita (no a mano) para que se lea bien |
| `.lista-ingredientes` | Con espacio para el ícono. Los ingredientes que faltan muestran el ícono en gris (`.falta .icono-chico`) |
| `.lista-pasos` | Renglones de 32 px, iguales a los del cuaderno, con los números en letra a mano roja (`::marker`) |

### Sección P4 · Contacto y consultas

| Bloque | Ahora |
|---|---|
| `.mapa` | El mapa como una **foto pegada**: marco blanco, sombra e inclinación |
| `.consulta--nueva` | Hoja amarillenta con cinta amarilla, en vez del borde lateral |

---

## 2. Archivos nuevos

- **`public/fonts/`**: `caveat-latin-700-normal.woff2`, `figtree-latin-500/600/800-normal.woff2`, `space-mono-latin-700-normal.woff2` y `LICENSE-*.txt`. Son de Google Fonts, con licencia libre OFL. Van en el proyecto, no en un enlace a Google, para que funcione sin internet (por ejemplo, en la PC del profesor).
- **`public/img/ingredientes/`**: `ajo`, `cebolla`, `harina`, `huevo`, `leche`, `limon`, `morron`, `papa`, `pollo`, `queso`, `tomate`, `zanahoria` y `frasco` (genérico), en `.svg`. Son los mismos dibujos de la página de diseño de la heladera.

---

## 3. Cambios en PHP

### `src/ayudas.php`: función nueva al final

```php
function iconoIngrediente(string $nombre): string
```

Devuelve la dirección del ícono de un ingrediente según **la primera palabra de su nombre, sin tildes**: "Pollo (pechuga)" → `pollo.svg`, "Queso cremoso" → `queso.svg`, "Morrón" → `morron.svg`. Si no hay dibujo, devuelve `frasco.svg`. **Para agregar un ícono** alcanza con poner el SVG en `public/img/ingredientes/` con ese nombre (por ejemplo, `arroz.svg`).

### `src/Vistas/encabezado.php`: el logo con su frase

```php
<div class="marca-grupo">
  <a class="marca" href="...">Cooki</a>
  <span class="marca__bajada">la puerta de tu heladera</span>
</div>
```

Antes era solo el `<a class="marca">`.

Y el enlace al CSS lleva la fecha del archivo, así el navegador baja el CSS nuevo cada vez que cambia en vez de usar el que tenía guardado:

```php
<link rel="stylesheet" href="<?= e(url('css/estilos.css') . '?v=' . filemtime(dirname(__DIR__, 2) . '/public/css/estilos.css')) ?>">
```

Sin esto, si antes abriste la versión original en `localhost:8080`, el navegador puede mezclar el HTML nuevo con el CSS viejo.

### `public/index.php`: portada

La `<img class="portada__imagen" src="img/portada.svg">` se reemplaza por una puerta con 8 stickers:

```php
<div class="portada__puerta" aria-hidden="true">
  <?php foreach (['tomate', 'queso', 'huevo', 'zanahoria', 'leche', 'limon', 'papa', 'morron'] as $i => $icono) : ?>
    <img class="sticker sticker--<?= $i + 1 ?>" src="<?= e(url('img/ingredientes/' . $icono . '.svg')) ?>" alt="" width="96" height="96">
  <?php endforeach; ?>
</div>
```

Es decorativa (`aria-hidden`, `alt=""`). El `<h1 class="portada__titulo">` no cambia, así que la prueba automática de la página principal sigue pasando.

### `public/app/index.php`: Mi cocina

- `<section class="tarjeta">` de "Usalo pronto" → `<section class="postit">` + `<span class="postit__chinche" aria-hidden="true"></span>`.
- En cada `<li>` se agrega el ícono antes del nombre: `<img class="icono-chico" src="<?= e(iconoIngrediente($item['nombre'])) ?>" alt="" ...>`.

### `public/app/despensa.php`: Mi despensa

En la primera celda de cada fila, antes del nombre:

```php
<img class="sticker-ingrediente" src="<?= e(iconoIngrediente($fila['nombre'])) ?>" alt="" width="88" height="88">
<?php if ($fila['dias'] !== null && (int) $fila['dias'] <= 1 && (int) $fila['nivel'] > 0) : ?>
  <span class="ya" aria-hidden="true">¡ya!</span>
<?php endif; ?>
```

La tabla sigue siendo una `<table>` con las mismas columnas. Lo de "stickers" lo hace el CSS (`#tabla-despensa`), así que el filtro en vivo (`despensa.js`), editar y quitar funcionan igual.

### `public/app/receta.php`: detalle de receta

En cada ingrediente, después del texto para lectores de pantalla:

```php
<img class="icono-chico" src="<?= e(iconoIngrediente($ing['nombre'])) ?>" alt="" width="28" height="28">
```

---

## 4. Cómo pasar el proyecto del equipo a este estilo

Si deciden usarlo, conviene hacerlo **cuando las páginas ya estén en `main`**, en un solo PR de P2, para no generar conflictos:

1. Reemplazar `public/css/estilos.css` por el de esta versión.
2. Copiar las carpetas `public/fonts/` y `public/img/ingredientes/`, y borrar `public/img/portada.svg`.
3. Agregar `iconoIngrediente()` al final de `src/ayudas.php`.
4. Hacer los agregados de la sección 3 en `encabezado.php`, `index.php`, `app/index.php`, `app/despensa.php` y `app/receta.php`.
5. `composer lint` y `composer test` en verde, y mirar las páginas a 360 px y en la computadora.

Si alguien ya cambió algo en `estilos.css` (por ejemplo, P2 con la identidad visual), hay que pasar esos cambios a mano: los nombres de clase son los mismos, pero el contenido de cada regla cambió.
