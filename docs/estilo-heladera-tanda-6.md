# Estilo Heladera · Tanda 6 de cambios (incremento 3)

> **Fecha:** 14/11/2026, en la entrega D del incremento 3.
> **Verificación:**
> - `composer lint` sin errores y **221 pruebas en verde** (2 salteadas si PHP no tiene GD).
> - Capturas a 1400 y 390 px, y la bienvenida sin JavaScript. Probado en el hosting por partes.
> - Referencia visual: una página de prueba con las opciones que se compararon (no está en el repositorio).

---

## Resumen

| # | Cambio | Dónde | Rol |
|---|---|---|---|
| 1 | **Tipos de receta con sticker**: la cinta de papel con un dibujo grande que sobresale | `tiposReceta()` en `src/ayudas.php`, `public/img/tipos/`, CSS `.tipos` | P2 |
| 2 | **Dificultad con semáforo**: verde, amarillo, rojo, con el texto al lado | `dificultadReceta()`, CSS `.dificultad` | P2 |
| 3 | **Listas desplegables con dibujo** en Recetas (dificultad, tipo, orden) | `desplegable()`, `public/js/desplegable.js`, CSS `.desplegable` | P2 |
| 4 | **Bienvenida paso a paso**: 4 notas de colores con opciones que son stickers | `public/app/bienvenida.php`, `public/js/bienvenida.js`, CSS `.paso`, `.elegible` | P2 |
| 5 | **"¿Falta algún tipo?"**: nota rosa centrada, con dibujos sueltos a los costados | `public/app/recetas.php`, CSS `.falta-tipo` | P2 |
| 6 | **Aviso en el panel** de Administración: ingredientes nuevos para revisar | `public/admin/index.php`, CSS `.aviso-panel` | P1 + P2 |

Las decisiones están en **DEC-025** (tipos y listas con dibujo) y **DEC-026** (bienvenida).

---

## 1. Tipos con sticker

- Se compararon tres estilos: el dibujo dentro de la cinta, **un sticker grande que sobresale de la cinta** (elegido)
  y chapitas redondas sin cinta.
- Cada tipo tiene su dibujo en `public/img/tipos/<clave>.svg` (hoja, maceta, espiga tachada, botella tachada, huevo
  tachado, reloj, chanchito, cupcake, hamburguesa y el plato de "Como de todo"), con el borde blanco de los stickers.
- Las cintas se inclinan un poco, una para cada lado, y el sticker sobresale 14 px a la izquierda (por eso las cintas
  tienen 24 px de separación). En la página de la receta son más grandes (`.tipos--receta`).
- Cada cinta lleva en `title` qué quiere decir el tipo ("Sin trigo, avena, cebada ni centeno. Apta para celíacos.").

## 2. Dificultad con semáforo

- Se compararon gorros de cocinero, llamitas, cucharas y **semáforo** (elegido): se entiende de un vistazo.
- Es una bolita con brillo (`.dificultad__luz`) y el texto **siempre al lado**: el color solo no alcanza para quien no
  distingue colores. En la bienvenida, el nivel ("Recién empiezo", "Me defiendo", "Me animo a todo") usa la luz grande.

## 3. Listas desplegables con dibujo

- Un `<select>` no puede mostrar dibujos. `desplegable()` arma un `<details>` con botones de opción (`radio`): se ve
  como los otros campos (borde de 2 px, 44 px de alto, flecha) y en la hoja de cuaderno respeta los renglones.
- Arriba se ve lo elegido con su dibujo; al abrir, cada opción con el suyo y la elegida resaltada con el color de la cinta.
- Con JavaScript: al elegir (clic o Enter) se cierra; con las flechas se recorre; Escape o tocar afuera la cierra.
  Como los demás filtros, busca con **Buscar**. Sin JavaScript se abre, se elige y se toca Buscar.
- Dibujos: Dificultad con el semáforo ("Todas" con las tres luces chiquitas), Tipo con los stickers y Ordenar con
  el libro de recetas (A a la Z), el horno de Mi cocina (Recomendadas para mí) y el reloj (Más rápidas primero).

## 4. Bienvenida paso a paso

- Se compararon tres formas: una sola hoja, **paso a paso** (elegida) y "armá tu heladera" (lo elegido se pegaba como
  imán en una puerta al costado).
- Cada paso es una nota pegada con chinche: amarilla, rosa, celeste y verde. Arriba, los puntitos 1 a 4 marcan el paso
  (el actual en rojo, los hechos en verde).
- Cada opción es un **sticker para despegar**: gris y con borde de puntos hasta que se elige; elegido, se pone en
  color, se levanta, se inclina y lleva un ✓ verde. Se maneja con el teclado (son casillas y botones de opción) y el
  foco se ve.
- "¿Otra cosa? Buscala": los stickers del catálogo que coinciden (también por otro nombre) y, si no está,
  "+ Agregar … como nuevo" con el frasco y la marquita "nuevo".
- **Sin salto al cargar:** el `<head>` pone la clase `js` en `<html>` antes de dibujar, así con JavaScript se ve una
  sola nota desde el primer momento. Sin JavaScript se ven las 4 juntas, sin los puntitos ni "Siguiente".
- En el celular las opciones son más chicas y el título de cada nota baja de tamaño.

## 5. "¿Falta algún tipo?"

- Nota rosa con chinche, centrada al final de Recetas. A los costados, dibujos sueltos (cambian en cada carga y no se
  muestran en pantallas angostas), como en Contacto y Mi cuenta.

## 6. Aviso en el panel

- Arriba de las notas de Administración, un aviso amarillo: "Hay N ingredientes nuevos para revisar…" con el enlace a
  Ingredientes. La nota de Ingredientes también lo dice.

## 7. Notas más tranquilas al soltarlas

- Al sacar el mouse de una nota con enlaces, `hamacar-soltar` se movía demasiado (hasta 3° y cuatro idas y vueltas en
  1,4 s). Ahora es un balanceo corto: 1,5° como mucho, dos idas y vueltas que se apagan, en 1 segundo. Con "reducir
  movimiento", la nota sigue volviendo a su giro sin balancearse.
- Lo mismo para las notas sin enlaces, como los 3 papeles de "Cómo funciona" en la portada: `hamacar` (al pasar el
  mouse) iba de +4° a −3° en 1,4 s; ahora se mece apenas hacia su lado (entre 0,6° y 2,4° más que su giro) en 1 segundo.
- Al volver a su lugar, las notas y la hoja "¿Por qué Cooki?" se pasaban de largo (`cubic-bezier(.3, 1.5, .5, 1)`);
  ahora se pasan mucho menos (`1.2`).
- Los ingredientes sueltos (la vitrina de la portada, los costados de las páginas, los del plan y los de "¿Falta algún
  tipo?") también se mueven menos con el mouse encima: crecen 10 a 12 % en lugar de 18 a 25 %, y se mecen con
  `menear-suave` (−3° a 4°, en 0,8 s) en lugar de `menear` (−6° a 8°, en 0,5 s). `menear` queda para los dibujos que se
  menean solos (al confirmar y al cambiar el dibujo de una receta).
- Los ingredientes sueltos se mecen como los de la cinta de la portada, con una diferencia: arrancan hacia el otro
  lado y van un poco más lentos (`alternate-reverse`, 0,9 s).
- Los demás dibujos también se mueven menos: el logo (y la ola de las letras, que suben 4 px en lugar de 7), los
  stickers del menú y del pie (crecen 8 % en lugar de 15 %), el dibujo que baila en el título de la portada (`bailar` a
  la mitad y crece 20 % en lugar de 45 %; al cambiarlo gira 20° en lugar de 40°), los dibujos de las notas del panel y
  de las hojas, el corazón de favoritos, el dibujo de "pensando" de la IA, las opciones con dibujo y los imanes.
- Todos los rebotes al crecer y al volver bajan: `cubic-bezier(.3, 1.6, .5, 1)` pasa a `1.3`.
