# Guía para cargar recetas en JSON

> Incremento 2 · P3. Hay dos formas de cargar recetas:
> - **De a una**, en el ABM de recetas de la administración.
> - **Muchas juntas**: una por archivo en `database/recetas/` y el importador.
>
> Esta guía es para la segunda forma.

## 1. Un archivo por receta

`database/recetas/<titulo-sin-tildes>.json`, por ejemplo `tarta-de-acelga.json`:

```json
{
  "titulo": "Tarta de acelga",
  "descripcion": "Verde, suave y con mucho queso.",
  "porciones": 6,
  "minutos_prep": 25,
  "minutos_coccion": 35,
  "dificultad": 2,
  "etiquetas": ["vegetariana"],
  "ingredientes": [
    { "nombre": "Tapa de tarta", "cantidad": 2, "unidad": "u", "peso": 3 },
    { "nombre": "Acelga", "cantidad": 1, "unidad": "u", "peso": 3, "nota": "un atado" },
    { "nombre": "Queso rallado", "cantidad": 80, "unidad": "g" },
    { "nombre": "Nuez moscada", "cantidad": 1, "unidad": "pizca", "peso": 1, "opcional": true }
  ],
  "pasos": [
    "Lavar la acelga y cortarla en tiras.",
    { "texto": "Hervir {ing:acelga} y escurrir muy bien.", "minutos": 3 },
    { "texto": "Hornear a 180 °C hasta que esté dorada.", "minutos": 35 }
  ]
}
```

| Campo | Obligatorio | Qué va |
|---|---|---|
| `titulo` | sí | De 3 a 150 caracteres. No se puede repetir. |
| `descripcion` | no | Hasta 500 caracteres. |
| `porciones` | sí | Entero de 1 a 20. Es la base para escalar las cantidades. |
| `minutos_prep` / `minutos_coccion` | sí | Enteros. La preparación lleva al menos 1; la cocción puede ser 0. |
| `dificultad` | sí | 1 fácil, 2 media, 3 difícil. |
| `etiquetas` | no | Los tipos: `vegetariana`, `vegana`, `sin_tacc`, `sin_lactosa`, `sin_huevo`, `economica`, `dulce`, `merienda_salada`. **`rapida` no se escribe**: se pone sola si preparación + cocción suman 30 minutos o menos. |
| `ingredientes` | sí | Al menos uno (ver abajo). |
| `pasos` | sí | Un texto, o `{ "texto": ..., "minutos": N }` si el paso tiene temporizador (de 1 a 180). |

### Cada ingrediente

| Campo | Obligatorio | Qué va |
|---|---|---|
| `nombre` | sí | Como está en el catálogo, o uno de sus otros nombres. No importan tildes ni mayúsculas: "morron" encuentra "Morrón" y "patata" encuentra "Papa". |
| `cantidad` | no | Número mayor que cero. Sin cantidad se muestra "a gusto". |
| `unidad` | no | `g`, `kg`, `ml`, `l`, `u`, `cda`, `cdita`, `taza`, `pizca`, `diente`, `lata`, `paquete`, `atado`. La pizca no se multiplica al cambiar las porciones. |
| `peso` | no | Para el puntaje: 3 principal, 2 secundario (el valor por defecto), 1 accesorio. Toda receta necesita al menos un principal. |
| `opcional` | no | `true` si se puede omitir: no cuenta para el puntaje. |
| `nota` | no | "picado", "en cubos", "para servir"… Hasta 100 caracteres. |

### Marcadores en los pasos

`{ing:papa}` se reemplaza por "papa (600 g)", con la cantidad ya escalada a las porciones que eligió el usuario. Va el nombre del ingrediente en minúscula, sin tildes y con guiones: `{ing:crema-de-leche}`, `{ing:harina-0000}`.

## 2. Qué revisa el importador

El importador no carga nada si alguna receta tiene un error, y dice en qué archivo está y qué falta. Revisa:
- que los ingredientes estén en el catálogo y no se repitan;
- que los números estén dentro del rango;
- que la unidad exista;
- que cada marcador `{ing:...}` sea un ingrediente de la receta;
- que las etiquetas sean ciertas:
  - una receta `vegetariana` no puede llevar carnes, pescados, fiambres ni atún;
  - una receta `vegana`, además, no puede llevar lácteos, huevo, miel, dulce de leche, mayonesa, tapas de tarta o de
    empanada (llevan grasa), galletitas ni chocolate;
  - una receta `sin_tacc` no puede llevar harina, fideos, pan, pan rallado, tapas, galletitas, avena ni salsa de soja;
  - una receta `sin_lactosa` no puede llevar lácteos (leche, manteca, crema, quesos, yogur, ricota) ni dulce de leche;
  - una receta `sin_huevo` no puede llevar huevo ni mayonesa;
  - los ingredientes opcionales también cuentan (para una alergia, más vale no arriesgar);
  - y que no diga `rapida` (se calcula sola).

Las reglas están en `src/Dominio/Tipos.php`; si se agrega un ingrediente que rompe alguna dieta y no es de la
categoría Carnes, Lácteos o Huevos, hay que sumarlo ahí.

## 3. Cómo se corre

```bash
# Con Docker (desde la carpeta del proyecto)
docker compose exec web php scripts/importar-recetas.php --validar   # solo revisa
docker compose exec web php scripts/importar-recetas.php             # carga o actualiza en la base
docker compose exec web php scripts/importar-recetas.php --sql       # regenera database/recetas.sql

# Sin Docker
php scripts/importar-recetas.php --validar
```

Cuando una receta ya existe (mismo título), el importador la **actualiza**: no la duplica.

### Después de agregar o cambiar un .json

1. Correr `--sql` y subir **el .json y `database/recetas.sql`** en el mismo PR.
2. Si se olvidan de este paso, la prueba `RecetarioTest` lo detecta y la CI queda en rojo.

`recetas.sql` es lo que carga Docker al crear la base (como `03-recetas.sql`). También sirve para el hosting: se importa en phpMyAdmin después de `seed.sql`.

## 4. Las recetas de ejemplo

- Las 12 recetas del incremento 1 siguen en `seed.sql`.
- Las 50 de `database/recetas/` las escribió el equipo para el incremento 2: son recetas caseras argentinas redactadas por nosotros, no copiadas de ningún sitio.
- En el incremento 3 (PR D) se sumaron 8 más: 4 veganas (hamburguesas de lentejas, chili de porotos, curry de garbanzos, wok de fideos) y 4 meriendas saladas (scones de queso, tostado de jamón y queso, bruschettas, tostadas con palta y huevo).
- Entre todas suman **70 recetas**.
