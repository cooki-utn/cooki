# Incremento 3 · Entrega B: qué cambió y de quién es cada parte

> **Base:** la entrega A (lista de compras y correo).
> **Pruebas:** **182 en verde** (eran 145; 2 se saltean si PHP no tiene GD). Ninguna usa la IA real ni la clave. El lint queda limpio.

## 1. Qué trae

| Historia | Qué se hizo | Rol |
|---|---|---|
| HU-30 · Una receta con lo que tenés | En Mi cocina, la nota **¿Y si inventamos algo?**: se eligen porciones, tiempo (15, 30 o 60 min) y tipo, y la IA propone una receta con lo de la despensa, empezando por lo que vence. Muestra "lo tenés" y "te falta" en cada ingrediente, "Por qué esta receta", el sello **Sugerida por IA · revisala antes de cocinar**, "+ lista" para lo que falta y **Pedir otra**. Se guarda en **Mis recetas de IA** | P3 + P2 |
| HU-31 · Adaptar una receta | En la receta, **Adaptar con IA** ofrece solo lo que hace falta: sin huevo, sin TACC, sin lactosa, vegetariana o "con lo que tengo". Sale con las porciones que se estaban mirando y con "Qué cambió". La receta original no cambia | P3 |
| HU-32 · ¿Con qué lo reemplazo? | En lo que falta y no tiene ⇄, **¿Con qué lo reemplazo? (IA)** responde abajo del ingrediente: "Usá leche: la misma cantidad (600 g)" y cómo cambia el resultado. Cada 5 reemplazos se gasta 1 pedido | P3 |
| HU-33 · Límites, caché y privacidad | 5 pedidos por persona y 200 por sitio por día (solo descuenta lo que salió bien). La misma consulta, durante 7 días, sale de la caché y no gasta (en adaptaciones y reemplazos se marca "ya consultado · no gasta"). A Google se le mandan solo ingredientes y restricciones, nunca el nombre ni el correo; el aviso está al lado de cada botón. Sin `IA_CLAVE`, los botones no aparecen | P1 |
| HU-34 · Administración de la IA | Nota **IA** en el panel y `admin/ia.php`: cómo está, uso de hoy y de 7 días por tipo (pedidos, caché, errores, tokens, tiempo) y los últimos errores. **Pasar al recetario** abre una receta guardada en el editor para revisarla (lo que no está en el catálogo queda afuera, con aviso). Los reemplazos sugeridos se **pasan a sustituciones** (calidad 3) o se **descartan** | P1 + P3 |
| Notas más fáciles de usar | Las notas que tienen un enlace, un botón o un campo ("¿Y si inventamos algo?", la lista, los avisos con enlace) ya no se hamacan con el mouse encima: se levantan y se quedan quietas en esa inclinación. Se hamacan recién cuando el mouse sale. Las demás notas siguen igual. En la entrega C pasaron a ponerse derechas con el mouse encima (DEC-024) | P2 |
| API | `GET /ia`, `POST /ia/receta`, `POST /ia/adaptar/{id}`, `POST /ia/reemplazo` ([api.md](api.md)) | P3 |
| Calidad | `RecetaIaTest` (13 pruebas de la validación), `ClienteGeminiTest` (5) e `IaTest` (16 de punta a punta) contra un Gemini de mentira (`servidor-ia-falso.php`, con respuestas lentas, 429 y basura); `PaginasTest` y `NavegacionTest` con las páginas nuevas | P4 revisa |

La decisión está en **DEC-020**.

## 2. Base de datos

Sin migración nueva: las tablas `receta_ia`, `respuesta_ia`, `uso_ia` y `sugerencia_reemplazo` ya venían en la
`007-incremento-3.sql` de la entrega A. `sugerencia_reemplazo.pasada` quiere decir "ya revisada" (pasada o
descartada). Detalle en [modelo-datos.md](modelo-datos.md).

## 3. Configuración

Variables nuevas en el `.env` (están en `.env.example`): `IA_PROVEEDOR`, `IA_CLAVE`, `IA_MODELO`, `IA_URL`,
`IA_TIMEOUT`, `IA_LIMITE_USUARIO_DIA` e `IA_LIMITE_GLOBAL_DIA`. Cómo sacar la clave de Gemini: [publicar.md](publicar.md),
paso 7. Sin clave, todo lo demás anda igual.

## 4. Archivos

| Archivo | Cambio |
|---|---|
| `src/Ia/ProveedorIa.php` · `RespuestaIa.php` · `ErrorIa.php` | **Nuevos.** La interfaz del proveedor, la respuesta y los errores con un mensaje amable |
| `src/Ia/ClienteGemini.php` | **Nuevo.** El pedido a Gemini con cURL: esquema JSON, `thinkingLevel: minimal`, tiempo máximo y errores claros (404 de modelo, clave, 429) |
| `src/Ia/Instrucciones.php` | **Nuevo.** Las instrucciones en español y los esquemas de las respuestas |
| `src/Ia/Ia.php` | **Nuevo.** La fachada: límites, caché, un reintento si da el tiempo, registro del uso |
| `src/Dominio/RecetaIa.php` | **Nuevo.** Lógica pura: valida y cruza con el catálogo, restricciones, adaptaciones, clave de la caché, la frase del reemplazo y la conversión para "Pasar al recetario" |
| `src/Repositorios/IaRepositorio.php` | **Nuevo.** Caché, uso, recetas de IA, reemplazos sugeridos y lo que usa la administración |
| `src/Controladores/IaControlador.php` · `public/api/index.php` | **Nuevo.** La API de la IA |
| `public/app/receta-ia.php` · `public/app/mis-recetas-ia.php` | **Nuevos.** Una receta de IA (guardar, borrar, pedir otra) y las guardadas |
| `src/Vistas/nota-ia.php` · `public/app/index.php` | **Nuevo.** La nota "¿Y si inventamos algo?" en Mi cocina |
| `public/app/receta.php` | "Adaptar con IA" y "¿Con qué lo reemplazo? (IA)" |
| `public/js/ia.js` | **Nuevo.** Los pedidos por la API con "Pensando…"; si algo falla, usa el formulario común |
| `public/admin/ia.php` · `public/admin/index.php` · `public/admin/receta-editar.php` | **Nuevo.** La administración de la IA, su nota en el panel y "Pasar al recetario" |
| `public/app/lista.php` · `src/Repositorios/ListaRepositorio.php` | Lo que falta de una receta de IA va a la lista |
| `public/css/estilos.css` · `public/img/ia.svg` | La nota, el sello, los reemplazos y la administración; el dibujo de la IA |
| `public/js/sitio.js` | Hamacar las notas con enlaces recién cuando el mouse sale |
| `src/ayudas.php` | Las páginas de la IA en la flecha de volver |
| `.env.example` | Las variables `IA_*` |

## 5. Cómo probarlo en 5 minutos

Hace falta una clave de Gemini en `IA_CLAVE` (paso 7 de [publicar.md](publicar.md)).

1. Ingresá como `maria@cooki.test` / `Usuario1234`. En **Mi cocina**, en **¿Y si inventamos algo?**, elegí 2 porciones y 30 min y tocá **Pedirle una receta a la IA**.
2. La receta muestra qué tenés y qué te falta, "Por qué esta receta" y el sello. Tocá **Guardar en Mis recetas de IA** y después **Pedir otra**: sale otra distinta. Mirá cuántos pedidos te quedan.
3. Recetas → **Tortilla de papas** → **Adaptar con IA** → "Sin huevo": sale la adaptada con "Qué cambió". La original sigue igual.
4. Volvé a la tortilla y abrí **Adaptar con IA**: "Sin huevo" dice "ya consultado · no gasta". Pedila otra vez: no descuenta.
5. En un ingrediente que te falta, **¿Con qué lo reemplazo? (IA)**: la respuesta aparece abajo, con el aviso de cuántos reemplazos te quedan sin gastar.
6. Ingresá como `admin@cooki.test` / `Admin1234` → nota **IA**: el uso de hoy, la receta que guardó María (sin su nombre) y el reemplazo. **Pasar al recetario** → revisala → **Guardar receta**: aparece en Recetas. **Pasar a sustituciones** el reemplazo: en esa receta, el ingrediente muestra ⇄.
7. Sin `IA_CLAVE`, los botones de la IA no aparecen y el resto anda igual. Probalo también en el ancho de un celular y sin JavaScript.
