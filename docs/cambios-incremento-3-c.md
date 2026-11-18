# Incremento 3 · Entrega C: qué cambió y de quién es cada parte

> **Base:** la entrega B (la IA).
> **Pruebas:** **204 en verde** (eran 182; 2 se saltean si PHP no tiene GD). El lint queda limpio.
> **Fechas:** del 09/11/2026 al 14/11/2026. Se fue subiendo al hosting por partes y se probó ahí.

## 1. Qué trae

| Historia | Qué se hizo | Rol |
|---|---|---|
| HU-35 · Elegir recetas por día | Página **Plan** (`app/plan.php`): los 7 días a partir de hoy, con almuerzo y cena, como notas en la heladera. En cada comida se elige una receta del recetario, una favorita (♥) o una de Mis recetas de IA ("(IA)"), y las porciones; se puede cambiar o quitar. El desplegable agrupa las recetas por lo que les faltaría **en ese lugar**: "Tenés todo", "Te falta 1 cosa" (y cuál), "Te faltan 2 cosas", "Te faltan 3 o más". En Mi cocina, la nota **Hoy** muestra las comidas de hoy con **Cocinar** (modo cocina con las porciones del plan) | P3 + P2 |
| HU-36 · Armar la semana | **Armá mi semana** completa los lugares vacíos: en cada uno elige con el puntaje de Mi cocina, mirando lo que dejaron las comidas anteriores, así lo que vence se usa primero y no se repiten recetas. No usa la IA; las recetas de IA guardadas entran si se marca "Incluir mis recetas de IA" | P3 |
| HU-37 · Lo que falta, a la lista | Cada comida dice **Tenés todo** o **Falta: …**, gastando la despensa en orden: lo que usa el almuerzo ya no está para la cena ("lo usa la tortilla del martes"). **+ lista** por comida y **Agregar lo que falta para la semana**, con las cantidades de las porciones elegidas y la suma de la lista | P3 + P2 |
| Sin recargar | Con JavaScript (`plan.js`), guardar, quitar y "+ lista" no recargan la página: se vuelven a pedir las comidas, porque un cambio cambia lo que falta en las siguientes. Sin JavaScript, todo anda con formularios comunes | P2 |
| Nota "Hoy" plegable | Se cierra (queda "Hoy · 2 comidas") y se apaga en **Mis preferencias → Plan de la semana**, como la nota de la lista | P2 + P1 |
| "Volver" a la misma altura | La flecha de volver deja la pantalla donde estaba: si en Mi cocina bajaste hasta las recetas y abriste una, "Volver a Mi cocina" te deja otra vez en las recetas (DEC-021) | P2 |
| La hora del servidor | MySQL usa la misma hora que PHP: entre las 21 y las 24, "hoy" ya no pasa a ser mañana (DEC-023) | P1 |
| Accesibilidad | "Saltar al contenido" en todas las páginas, foco visible también en la heladera noche, más contraste en el plan, avisos con `aria-live`. Revisado con axe-core en las páginas del incremento: 0 problemas | P2, P4 revisa |
| Diseño | **Menú de stickers con dibujo** (horno, heladera, libro, plan, bolsa…), "Preferencias" en lugar de "Ajustes" sin cuenta, Contacto y Quiénes somos en el **pie** con el año, notas que se ponen **derechas** con el mouse encima, letras sin salto al cambiar de página y caché del navegador (DEC-024, [estilo-heladera-tanda-5.md](estilo-heladera-tanda-5.md)) | P2 |
| API | `GET /plan`, `PUT /plan/{fecha}/{momento}` y `DELETE /plan/{fecha}/{momento}` ([api.md](api.md)) | P3 |
| Calidad | `PlanSemanalTest` (13 pruebas de la lógica: simulación, armar, desplegable, fechas) y `PlanTest` (9 de punta a punta: página con "Saltar al contenido", guardar, cambiar y quitar, otros días y recetas ajenas, borrar lo viejo, respuesta JSON, nota "Hoy", armar, lo que falta a la lista una sola vez y la API); `PaginasTest` con el menú y el pie nuevos | P4 revisa |

La decisión principal es **DEC-022**; también **DEC-021**, **DEC-023** y **DEC-024**.

## 2. Base de datos

- `plan_comida` ya venía en la migración 007 de la entrega A.
- **Columna nueva:** `usuario.nota_hoy` (`abierta`, `cerrada` o `nunca`), en la migración
  `database/migraciones/009-nota-hoy.sql`. Se importa **una sola vez**; si se importa de nuevo dice
  "Duplicate column name" y no pasa nada.
- Detalle en [modelo-datos.md](modelo-datos.md).

## 3. Configuración

Sin variables nuevas en el `.env`. Para la caché del navegador, `public/.htaccess` usa `mod_expires` y `mod_headers`
si el hosting los tiene (si no, anda igual). En Docker, la imagen `web` los activa: después de bajar los cambios,
`docker compose up -d --build`.

## 4. Archivos

| Archivo | Cambio |
|---|---|
| `src/Dominio/PlanSemanal.php` | **Nuevo.** Lógica pura: los días, simular lo que falta gastando la despensa en orden, armar la semana y los grupos del desplegable |
| `src/Repositorios/PlanRepositorio.php` | **Nuevo.** El plan de la semana, guardar, quitar, borrar lo viejo, y los datos para la simulación (despensa, comidas, recetas candidatas) |
| `public/app/plan.php` | **Nueva.** La página del plan (responde JSON a `plan.js`) |
| `public/js/plan.js` | **Nuevo.** Guardar, quitar y "+ lista" sin recargar |
| `src/Controladores/PlanControlador.php` · `public/api/index.php` · `src/Http/Router.php` | **Nuevo.** La API del plan y `Router::put()` |
| `src/Vistas/nota-hoy.php` · `public/app/index.php` | **Nuevo.** La nota "Hoy" en Mi cocina |
| `public/app/mis-preferencias.php` · `public/app/preferencias.php` · `src/Sesion.php` · `src/Repositorios/UsuarioRepositorio.php` | Mostrar u ocultar la nota "Hoy" (`nota_hoy`) |
| `database/schema.sql` · `database/migraciones/009-nota-hoy.sql` | La columna `usuario.nota_hoy` |
| `src/Db.php` | `SET time_zone` con la hora de PHP |
| `src/Vistas/encabezado.php` | Menú de stickers, "Saltar al contenido", "Preferencias" y la precarga de las letras |
| `src/Vistas/pie.php` | Contacto y Quiénes somos con stickers, y el año |
| `public/img/menu/` (12 dibujos) · `public/img/plan.svg` | **Nuevos.** Los dibujos del menú y del plan |
| `public/css/estilos.css` | El plan, la nota "Hoy", el menú, el pie, las notas que se enderezan, las letras de respaldo, el foco y el contraste |
| `public/js/sitio.js` | "Volver" a la misma altura, notas que se hamacan al soltarlas y `notaPlegable()` para las notas de la lista y "Hoy" |
| `public/.htaccess` | Caché del navegador para letras, dibujos, CSS y JS |
| `docker/php/Dockerfile` | Activa `expires` y `headers` en Apache |
| `src/ayudas.php` | El plan en la flecha de volver |
| `tests/Dominio/PlanSemanalTest.php` · `tests/Integracion/PlanTest.php` | **Nuevas.** 22 pruebas |
| `tests/Integracion/PaginasTest.php` · `tests/Integracion/Navegador.php` | El menú y el pie nuevos, y los pedidos como los de JavaScript (`X-Requested-With`) |

## 5. Cómo probarlo en 5 minutos

1. Ingresá como `maria@cooki.test` / `Usuario1234` y tocá el sticker **Plan**.
2. En el almuerzo de hoy, abrí el desplegable: las recetas están agrupadas por lo que te falta. Elegí una con 4
   porciones y tocá **Guardar**: la página no se recarga y la comida dice "Tenés todo" o "Falta: …".
3. Poné en la cena una receta que use lo mismo (por ejemplo, otra con huevo): ahora puede faltarte algo que "lo usa"
   el almuerzo.
4. Tocá **Armá mi semana**: se completan los lugares vacíos sin repetir recetas. Después, **Agregar lo que falta para
   la semana** y mirá la **Lista**.
5. Volvé a **Mi cocina**: la nota **Hoy** tiene el almuerzo con **Cocinar**. Cerrala con ✕ y después apagala en
   **Mis preferencias → Plan de la semana**.
6. En Mi cocina, bajá hasta las recetas, abrí una y tocá **Volver a Mi cocina**: quedás a la misma altura.
7. Apretá Tab al abrir cualquier página: aparece **Saltar al contenido**. Probá todo en la heladera noche, en el ancho
   de un celular y sin JavaScript.

## 6. Lo que pasó en el camino

- **La hora del hosting:** después de las 21, "vence hoy" mostraba lo de mañana. El MySQL del hosting está en UTC;
  se resolvió con `SET time_zone` (DEC-023).
- **El seed y las fechas:** los vencimientos de prueba se calculan el día que se importa la base. Si pasan varios días,
  algunas pruebas de vencimientos fallan sin que haya un error en el código: se reinicia la base de Docker
  (`docker compose down -v` y `docker compose up -d`).
- **Pedidos del equipo al probar en el hosting:** el desplegable agrupado por lo que falta, los stickers
  desparramados en el hueco del final del plan, la nota "Hoy" plegable, el menú con dibujos y el salto de las letras
  al cambiar de página. Todos entraron en esta entrega.
- **Quedó a futuro:** el menú en una columna al costado (se probó y por ahora queda el de siempre), y en el pie
  GitHub, los créditos de las letras y una página de privacidad.
