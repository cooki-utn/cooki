# Estilo Heladera · Tanda 5 de cambios (incremento 3)

> **Fecha:** del 09/11/2026 al 13/11/2026, en la entrega C del incremento 3.
> **Verificación:**
> - `composer lint` sin errores y **204 pruebas en verde** (2 salteadas si PHP no tiene GD).
> - Revisión con axe-core en el plan, Mi cocina, la lista, las páginas de la IA, Recetas, Mis preferencias y
>   la administración de la IA y del correo, en la heladera clara y en la noche: después de los arreglos, 0 problemas.
> - Capturas a 1400, 1180, 1000, 870 y 390 px.

---

## Resumen

| # | Cambio | Dónde | Rol |
|---|---|---|---|
| 1 | **Menú de stickers con dibujo** en lugar de las bolitas de colores | `src/Vistas/encabezado.php`, `public/img/menu/`, CSS | P2 |
| 2 | "Ajustes" (sin cuenta) pasa a llamarse **Preferencias** | `encabezado.php` | P2 |
| 3 | Con cuenta, **Contacto y Quiénes somos van al pie**, que lleva el año | `src/Vistas/pie.php` | P2 |
| 4 | Las notas con algo para tocar **se ponen derechas** con el mouse encima y se hamacan al soltarlas | CSS, `public/js/sitio.js` | P2 |
| 5 | **Letras sin salto** al cambiar de página | CSS, `encabezado.php`, `public/.htaccess` | P2 |
| 6 | **Saltar al contenido** y foco visible en todas las heladeras | `encabezado.php`, CSS | P2 |
| 7 | **Plan semanal** como notas en la heladera, con stickers en los huecos | `public/app/plan.php`, CSS | P2 + P3 |
| 8 | **Nota "Hoy"** en Mi cocina, plegable como la de la lista | `src/Vistas/nota-hoy.php` | P2 |

Las decisiones están en **DEC-021** ("Volver" a la misma altura), **DEC-022** (plan semanal) y **DEC-024** (menú,
notas y letras).

---

## 1. Menú de stickers

Se probaron 8 estilos en una página aparte y se eligió el de **stickers con dibujo**. Cada enlace es un dibujo con el
texto abajo:

| Enlace | Dibujo |
|---|---|
| Mi cocina | horno (`img/menu/horno.svg`) |
| Mi despensa | heladera (`heladera.svg`) |
| Recetas | libro (`recetas.svg`) |
| Plan | calendario (`img/plan.svg`) |
| Lista | bolsa de compras (`lista.svg`) |
| Contacto | sobre (`contacto.svg`) |
| Inicio · Nosotros · Ingresar · Crear cuenta · la cuenta · Preferencias · Administración | cada uno el suyo: casa, llave, paleta, portapapeles… |

- `$menu` en `encabezado.php` tiene, por cada enlace, la ruta, el texto y el dibujo. Cada uno lleva un
  `<img class="menu__dibujo" alt="">` (el nombre ya está en `.menu__texto`).
- La página actual tiene el texto **resaltado con fibra** (`aria-current="page"`). Ya no hay bolitas `::before`.
- Los stickers se reparten con un espacio parejo, `--espacio-menu: clamp(14px, 2vw, 28px)`, que también separa la
  cuenta. El menú arranca a 32 px del logo como mínimo, y el logo baja 14 px para quedar a la altura del menú.
- En el celular, el menú desplegable muestra los dibujos de 32 px al lado del texto.
- La paleta de la heladera sin cuenta se llama **Preferencias**. La clase `cuenta--ajustes` y el archivo
  `ajustes.svg` conservan el nombre viejo.

## 2. El pie

Con cuenta, Contacto y Quiénes somos salen del menú y pasan al pie, como dos stickers de 34 px
(`nav.pie__enlaces`, con `aria-current` en la página actual). Abajo dice
"Cooki · <año> · Proyecto integrador · Tecnicatura Universitaria en Programación · UTN Facultad Regional Haedo".
Sin cuenta, Contacto sigue en el menú.

## 3. Notas que se enderezan

Las notas que tienen un enlace, un botón o un campo (`:is(.aviso, .postit):has(a, button, input, select, textarea, summary)`)
están un poco inclinadas, como siempre (entre 0,5° y 1,5°).
- Con el mouse encima, se levantan 4 px y se ponen **derechas** (`rotate(0deg)`, con la transición de 0,4 s).
- Al salir, `hamacar-soltar` arranca derecha, se hamaca y termina en su inclinación (`--giro`).
- Las notas sin nada para tocar se siguen hamacando con el mouse encima, como antes.
- Con `prefers-reduced-motion`, nada se mueve (regla global).

## 4. Letras sin salto

Antes, la página se mostraba primero con una letra del sistema más ancha y "se acomodaba" cuando llegaba Caveat.
Ahora:
- **Letras de respaldo del mismo ancho:** `@font-face` "Caveat respaldo" (Segoe Print con `size-adjust: 68.5%`),
  "Caveat respaldo 2" (Comic Sans, 70,2 %), "Space Mono respaldo" (Consolas, 111,3 %) y "Space Mono respaldo 2"
  (Courier New, 102 %). Van en `--fuente-mano` y `--fuente-rotulo` después de la letra propia.
- **Precarga:** `<link rel="preload">` de `caveat-700`, `figtree-600` y `space-mono-700` en el encabezado.
- **Caché:** `public/.htaccess` guarda las letras 1 año (`immutable`), los dibujos 7 días y el CSS y el JS 30 días
  (llevan `?v=`, así que un cambio se baja igual). Está dentro de `IfModule`: si el hosting no tiene esos módulos,
  anda igual. En Docker, `a2enmod expires headers`.

## 5. Accesibilidad

- **"Saltar al contenido":** el primer Tab de todas las páginas muestra un enlace que lleva a `#contenido`.
- **Foco visible** con `--color-foco`: azul en el papel y en las heladeras claras, `#A9D4FF` en la noche (el azul no
  se veía sobre la puerta oscura). También en `summary` y en lo que tiene `tabindex`.
- **Contraste en la noche:** "Armá mi semana" claro; deshabilitado, punteado con el texto legible (antes, con
  `opacity: .5`, casi no se veía).
- "Falta:" del plan con `--color-marca-oscuro` sobre las notas de colores. El botón Cocinar ya no tiene `opacity`.
- Los avisos del plan, la lista y la IA van en zonas `aria-live`.

## 6. El plan semanal

- `app/plan.php`: una nota por día (hoy primero), con el almuerzo y la cena. La grilla tiene 1, 2 o 3 columnas
  según el ancho. Cada comida muestra los dibujos de sus ingredientes en el estado de la simulación: tengo, usado
  (con "lo usa…"), vence o falta.
- Debajo: "Tenés todo" o "Falta: …", "+ lista" y "Cambiar" (un desplegable agrupado por lo que faltaría ahí).
- **Stickers en los huecos:** chicos, pegados al borde de cada nota, y 8 grandes desparramados en el lugar vacío
  después del último día (`.plan-relleno`, en posiciones fijas como los dibujos sueltos). En el celular es una franja
  de 170 px con 5.
- **Nota "Hoy"** en Mi cocina (`src/Vistas/nota-hoy.php`): se cierra (queda un recuadro "Hoy · 2 comidas") y se apaga
  en Mis preferencias, igual que la nota de la lista. `notaPlegable()` de `sitio.js` sirve para las dos.
- La lista de compras se lee bien en la heladera noche (`.lista-compras` usa tinta oscura).
