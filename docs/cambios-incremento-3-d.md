# Incremento 3 · Entrega D: qué cambió y de quién es cada parte

> **Base:** la entrega C (plan semanal).
> **Pruebas:** **221 en verde** (eran 204; 2 se saltean si PHP no tiene GD). El lint queda limpio.
> **Fecha:** 14/11/2026. Se fue subiendo al hosting por partes y se probó ahí.

## 1. Qué trae

| Qué | Qué se hizo | Rol |
|---|---|---|
| Tipos de receta con dibujo | 9 tipos con mayúscula y bien escritos ("Sin TACC", "Económica"), cada uno con su sticker que sobresale de la cinta. Nuevos: **Vegana**, **Sin lactosa**, **Sin huevo**, **Rápida** (se calcula sola: 30 minutos o menos) y **Merienda salada**. Las reglas de cada dieta están en `Dominio\Tipos` y el importador no deja cargar una receta que dice "vegana" y lleva manteca (DEC-025) | P2 + P3 |
| Dificultad con semáforo | Verde Fácil, amarillo Media, rojo Difícil, con el texto al lado, en las tarjetas, la receta y las recetas de IA | P2 |
| Listas desplegables con dibujo | En Recetas, Dificultad (semáforo), Tipo (stickers) y Ordenar (libro, horno, reloj) son listas propias con dibujo. Sin JavaScript se abren, se elige y se toca Buscar | P2 |
| 8 recetas nuevas | 4 veganas (hamburguesas de lentejas, chili de porotos, curry de garbanzos, wok de fideos) y 4 meriendas saladas (scones de queso, tostado de jamón y queso, bruschettas, tostadas con palta y huevo). Las que ya estaban suman Vegana, Sin lactosa y Sin huevo si cumplen. Son 70 en total | P3 |
| Bienvenida "Contanos cómo comés" | Después de crear la cuenta, 4 notas paso a paso: cómo comés, lo que no, tus gustos (y nivel y para cuántos cocinás) y lo que siempre hay. Todo opcional; se vuelve a abrir desde **Mis preferencias → Cómo comés**. Buscador con stickers y "+ Agregar … como nuevo" (DEC-026) | P2 + P3 |
| Lo que cambia con la bienvenida | La **dieta filtra** Mi cocina, el selector de ingredientes y "Armá mi semana"; en Recetas viene la casilla "Solo de mi dieta" (se puede sacar) y las demás dicen "No va con tu dieta". Los **gustos y el nivel ordenan**. **Para cuántos** son las porciones del plan | P3 |
| ¿Falta algún tipo? | Al final de Recetas, una nota para sugerir un tipo: llega como consulta con el asunto "Sugerencia de tipo" y se responde desde Consultas (hasta 5 por día) | P2 + P1 |
| Aviso en Administración | "Hay N ingredientes nuevos para revisar", con el enlace a Ingredientes filtrado por los que agregaron los usuarios | P1 |
| Arreglos | Ya se puede borrar una cuenta con ingredientes propios en sus listas; "1 receta encontrada" en singular; la bienvenida no muestra las 4 notas un instante al cargar (la clase `js` se pone en el `<head>`); `seed.sql` carga las fechas de ejemplo con la hora de Argentina (DEC-023), así importado entre las 21 y las 24 en un servidor en UTC los vencimientos no se corren un día | P1 + P2 |
| Calidad | `TiposTest` (5), `PerfilTest` (5), `BienvenidaTest` (4), `SugerenciasTest` (2) y una más del importador | P4 revisa |

Las decisiones son **DEC-025** (tipos y listas con dibujo) y **DEC-026** (bienvenida).

## 2. Base de datos

Migración `database/migraciones/010-personalizar.sql`. Se importa **una sola vez**: las 8 recetas nuevas se borran y
se vuelven a crear, y con ellas se irían los favoritos o el plan que alguien haya hecho con ellas.
- `etiqueta`: los tipos 5 a 9 (`vegana`, `sin_lactosa`, `sin_huevo`, `rapida`, `merienda_salada`) con id fijo, y se
  les ponen a las recetas que ya estaban ("Rápida" a todas las de 30 minutos o menos, también las de la administración).
- Las 8 recetas nuevas (generadas con el importador; usan solo ingredientes de la lista general).
- Tabla nueva `usuario_tipo` (dieta y gustos) y columnas `usuario.nivel_cocina` y `usuario.comensales`. El `ALTER`
  va al final: si se importa de nuevo dice "Duplicate column name" y no pasa nada.

No hace falta importar `recetas.sql` en el hosting. Detalle en [modelo-datos.md](modelo-datos.md).

## 3. Configuración

Sin variables nuevas en el `.env`.

## 4. Archivos

| Archivo | Cambio |
|---|---|
| `src/Dominio/Tipos.php` | **Nuevo.** Los 9 tipos: nombre, explicación, dibujo, orden, "Rápida" y las reglas de cada dieta |
| `src/Dominio/Perfil.php` | **Nuevo.** Lo de la bienvenida: limpiar el formulario, si una receta va con la dieta, el ajuste por gustos y nivel, los grupos de "lo que no comés" y las porciones del plan |
| `public/app/bienvenida.php` · `public/js/bienvenida.js` | **Nuevos.** La bienvenida paso a paso y su buscador con stickers |
| `public/img/tipos/` (10 dibujos) | **Nuevos.** Un sticker por tipo y "Como de todo" |
| `public/js/desplegable.js` · `src/ayudas.php` | **Nuevos** `desplegable()`, `tiposReceta()` y `dificultadReceta()`; la lista desplegable con dibujo |
| `src/Dominio/ImportadorRecetas.php` · `src/Repositorios/RecetaRepositorio.php` | Revisan los tipos nuevos y calculan "Rápida"; la dieta filtra y los gustos ordenan las recomendaciones |
| `src/Repositorios/PreferenciasRepositorio.php` | Guardar y leer la bienvenida, reemplazar las listas y las recetas que no van con la dieta |
| `src/Repositorios/PlanRepositorio.php` · `src/Dominio/PlanSemanal.php` · `public/app/plan.php` | "Armá mi semana" respeta la dieta y anota las porciones de la persona |
| `src/Repositorios/ContactoRepositorio.php` · `public/app/recetas.php` | "¿Falta algún tipo?", las listas con dibujo y "Solo de mi dieta" |
| `src/Repositorios/ResumenRepositorio.php` · `public/admin/index.php` | El aviso de ingredientes nuevos |
| `src/Repositorios/UsuarioRepositorio.php` | Borrar una cuenta con ingredientes propios en sus listas |
| `src/Vistas/tarjeta-receta.php` · `public/app/receta.php` · `public/app/receta-ia.php` · `public/admin/recetas.php` · `public/admin/receta-editar.php` | Tipos con dibujo y semáforo; en el editor, "Rápida" no se elige |
| `public/registro.php` · `public/app/mis-preferencias.php` · `public/app/index.php` | Crear cuenta lleva a la bienvenida; la nota "Cómo comés"; el selector de Mi cocina respeta la dieta |
| `src/Vistas/encabezado.php` | La clase `js` en `<html>` antes de dibujar |
| `public/css/estilos.css` | Tipos, semáforo, listas con dibujo, bienvenida, "¿Falta algún tipo?" y el aviso del panel |
| `database/schema.sql` · `database/seed.sql` · `database/recetas/*.json` · `database/recetas.sql` · `database/migraciones/010-personalizar.sql` | Tabla, columnas, tipos y recetas |
| `tests/Dominio/TiposTest.php` · `tests/Dominio/PerfilTest.php` · `tests/Integracion/BienvenidaTest.php` · `tests/Integracion/SugerenciasTest.php` | **Nuevas.** 16 pruebas |

## 5. Cómo probarlo en 5 minutos

1. Creá una cuenta nueva: se abre **Contanos cómo comés**. Elegí Vegana, marcá "Pescado", escribí algo que no esté
   (por ejemplo "maní tostado") y elegí para cuántos cocinás.
2. En **Mi cocina** solo aparecen recetas veganas. En **Recetas** está marcada "Solo de mi dieta (Vegana)";
   desmarcala y tocá Buscar: aparecen todas y las otras dicen "No va con tu dieta".
3. Abrí las listas de **Dificultad**, **Tipo** y **Ordenar**: cada opción con su dibujo. Elegí "Merienda salada".
4. En el **Plan**, anotá una receta sin escribir porciones: se guarda con las de la bienvenida.
5. Al final de Recetas, mandá un tipo en **¿Falta algún tipo?**. Entrá como administrador: está en **Consultas** y el
   panel avisa del ingrediente nuevo.
6. Borrá la cuenta de prueba desde **Mi cuenta**.

## 6. Lo que pasó en el camino

- **Probado en el hosting por partes.** Pedidos que entraron: stickers también en la lista de Tipo (un `<select>` no
  los muestra), lo mismo en Dificultad y Ordenar, la bienvenida sin el salto al cargar y la nota de "¿Falta algún tipo?"
  centrada con dibujos a los costados.
- **Un error viejo:** no se podía borrar una cuenta con un ingrediente propio en "No puedo comer" o "Siempre tengo".
  Con la bienvenida iba a pasar más seguido; ahora se borran antes esas listas.
- **Quedó a futuro:** chipá y fainá (necesitan almidón de mandioca y harina de garbanzo, con su dibujo) y que las
  recetas de IA tengan tipos revisados para filtrarlas por dieta.
