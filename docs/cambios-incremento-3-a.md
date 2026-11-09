# Incremento 3 · Entrega A: qué cambió y de quién es cada parte

> **Base:** `main` con el arreglo de los dibujos con tilde (#49).
> **Pruebas:** **144 en verde** (2 se saltean si PHP no tiene GD). El lint queda limpio.

## 1. Qué trae

| Historia | Qué se hizo | Rol |
|---|---|---|
| HU-38 · Correo real desde el hosting | El sitio manda los correos con Gmail (587 + tls) usando el cliente `Smtp` que ya estaba. Administración → **Correo** muestra la configuración (sin la contraseña) y manda un correo de prueba. Guía en [publicar.md](publicar.md) | P1 |
| HU-39 · Equipo de cuatro | Quiénes somos y el README muestran a los cuatro integrantes con apellido | P1 |
| HU-26 · Lo que falta de una receta | En la receta, **"+ lista"** en cada ingrediente que falta y **"Agregar lo que falta a la lista"**, con las cantidades de las porciones elegidas. Si ya estaba con la misma unidad, se suma. Cada renglón dice "para: Tortilla de papas" | P2 |
| HU-27 · A mano y desde la despensa | `app/lista.php` agrega con el mismo buscador de stickers de Mi despensa (`elegir.js`); lo que no está en el catálogo va como texto libre, con el frasco. En Mi despensa, lo agotado o con poco tiene "+ lista" | P2 |
| HU-28 · Lo compré | Al tildar, el ingrediente pasa a la despensa: nivel "Normal", vencimiento estimado con la vida útil y la ubicación que ya tenía (o la sugerida). Queda en "Comprado hoy" con **Deshacer** hasta el día siguiente. Sin JavaScript: "Pasar lo tildado a mi despensa" | P2 |
| HU-28 · Otra cantidad y todo junto | "Otra cantidad" en cada renglón anota lo que de verdad se compró (eso pasa a la despensa; "Deshacer" vuelve a lo previsto). **Marcar todo como comprado** pasa todo junto (DEC-019) | P2 |
| La lista a mano | Una nota con la lista al lado de "Usalo pronto" en Mi cocina y a la derecha de los filtros en Mi despensa. Se cierra y queda un recuadro chico; en **Mis preferencias** se apaga del todo (DEC-019). En Mi despensa, "+ lista" va al lado de la cantidad | P2 + P1 |
| HU-29 · Compartir e imprimir | La lista se ordena por góndola. **Copiar la lista**, **Mandar por WhatsApp** (un enlace `wa.me` con el texto) e **Imprimir** (sin menú ni decoración) | P2 |
| API | `GET /lista`, `POST /lista`, `POST` y `DELETE /lista/{id}/comprado`, `DELETE /lista/{id}` ([api.md](api.md)) | P3 |
| Calidad | `ListaComprasTest` (11 pruebas de la lógica) y `ListaTest` (7 de punta a punta: repositorio, páginas y API) | P4 revisa |

Las decisiones están en **DEC-018** y **DEC-019**.

## 2. Base de datos

- **Tablas nuevas** (todas las del incremento, aunque la IA y el plan se programan en las entregas B y C):
  `lista_compra_item`, `receta_ia`, `respuesta_ia`, `uso_ia`, `sugerencia_reemplazo` y `plan_comida`.
  Detalle en [modelo-datos.md](modelo-datos.md).
- **Columna nueva:** `usuario.nota_lista` (la nota de la lista en Mi cocina y Mi despensa).
- **Migraciones:** `database/migraciones/007-incremento-3.sql` (usa `CREATE TABLE IF NOT EXISTS`: si se importa dos veces, no pasa nada)
  y `008-nota-lista.sql` (se importa una sola vez).

Para actualizar después de unir:
- **Con Docker:** `docker compose down -v` y `docker compose up -d`.
- **Sin borrar datos:** `mysql -u cooki -p cooki < database/migraciones/007-incremento-3.sql` y después la `008-nota-lista.sql`.
- **En el hosting:** phpMyAdmin → la base → Importar → `007-incremento-3.sql`, y después `008-nota-lista.sql`.

## 3. Archivos

| Archivo | Cambio |
|---|---|
| `src/Dominio/ListaCompras.php` | **Nuevo.** Lógica pura: sumar, lo que falta de una receta, vencimiento, ubicación, agrupar por góndola y el texto para compartir |
| `src/Repositorios/ListaRepositorio.php` | **Nuevo.** Agregar, quitar, comprar y deshacer (en transacciones) |
| `src/Controladores/ListaControlador.php` | **Nuevo.** La API de la lista |
| `public/app/lista.php` · `public/js/lista.js` | **Nuevos.** La página y lo que hace sin recargar |
| `public/js/elegir.js` | **Nuevo.** El buscador de stickers, que antes estaba adentro de `despensa.js` |
| `public/app/receta.php` · `public/app/despensa.php` | Botones "+ lista" y "En tu lista" |
| `src/Vistas/encabezado.php` | Imán **Lista** en el menú |
| `src/Vistas/nota-lista.php` | **Nuevo.** La nota de la lista (abierta o cerrada), que usan Mi cocina y Mi despensa |
| `public/app/index.php` · `public/app/mis-preferencias.php` · `public/app/preferencias.php` | La nota en Mi cocina y el ajuste para apagarla |
| `src/Repositorios/UsuarioRepositorio.php` · `src/Sesion.php` · `public/js/sitio.js` · `public/js/sonidos.js` | La preferencia `nota_lista` y abrir o cerrar la nota sin recargar |
| `public/css/estilos.css` | La lista como hoja de cuaderno, los botones "+ lista", el sexto imán y la vista de impresión |
| `database/schema.sql` · `database/migraciones/007-incremento-3.sql` · `008-nota-lista.sql` | Las seis tablas y la columna `usuario.nota_lista` |

## 4. Cómo probarlo en 5 minutos (con Docker)

1. Ingresá como `juan@cooki.test` / `Usuario1234` (despensa vacía) → Recetas → **Tortilla de papas** → bajá a 2 porciones.
2. Tocá **"+ lista"** en la cebolla: dice "En tu lista". Después, **Agregar lo que falta a la lista**.
3. Imán **Lista**: está todo por góndola, con "para: Tortilla de papas" y las cantidades para 2.
4. Agregá "Servilletas" a mano (no está en el catálogo: va con el frasco, en "Otros").
5. Tildá la papa: pasa a **Comprado hoy**. En Mi despensa aparece con su vencimiento. Volvé a la lista y tocá **Deshacer**.
6. En otro renglón tocá **Otra cantidad**, poné otra cantidad y **Lo compré**: en Mi despensa queda esa cantidad. Probá **Marcar todo como comprado**.
7. En **Mi cocina** y en **Mi despensa** está la nota de la lista: cerrala con ✕ (queda un recuadro chico) y volvé a abrirla. En **Mis preferencias**, apagá "Mostrar mi lista de compras": desaparece de las dos.
8. **Copiar la lista** y **Mandar por WhatsApp**: el texto sale ordenado por góndola. **Imprimir** muestra solo la lista.
9. Probalo también en el ancho de un celular y sin JavaScript (los formularios recargan la página, pero todo anda).
