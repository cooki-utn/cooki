# Modelo de datos

Estructura completa en `database/schema.sql`; datos de ejemplo en `database/seed.sql`; recetario en `database/recetas/*.json` → `database/recetas.sql`.
Responsable: P3.

## Tablas que usa el incremento 1

```mermaid
erDiagram
    USUARIO ||--o{ CONTACTO : "envía (opcional)"
    USUARIO ||--o{ DESPENSA_ITEM : posee
    USUARIO ||--o{ RECETA : "carga (admin)"
    CATEGORIA ||--o{ INGREDIENTE : agrupa
    INGREDIENTE ||--o{ SINONIMO : "se llama también"
    INGREDIENTE ||--o{ DESPENSA_ITEM : "está en"
    RECETA ||--o{ RECETA_INGREDIENTE : requiere
    INGREDIENTE ||--o{ RECETA_INGREDIENTE : "se usa en"
    RECETA ||--o{ PASO : tiene
    RECETA ||--o{ RECETA_ETIQUETA : ""
    ETIQUETA ||--o{ RECETA_ETIQUETA : ""
```

| Tabla | Para qué | Relaciones |
|---|---|---|
| `usuario` | Cuentas. `rol` = `general` o `admin`. `hash_contrasena` con `password_hash()`. `color` = color de la heladera elegido (`verde`, `crema`, `celeste`, `rosa`, `noche`). `disposicion` = `normal` o `panoramica`. `volumen` = 0 a 100 (sonidos) | — |
| `contacto` | Consultas del formulario de contacto. `respuesta`, `respondida_en` y `respuesta_enviada`: la respuesta de la administración (DEC-007) | `usuario_id` → usuario (NULL si no estaba logueado; `ON DELETE SET NULL`) · `respondida_por` → usuario (`ON DELETE SET NULL`) |
| `receta` | Contenido principal del sitio | `autor_id` → usuario (`ON DELETE SET NULL`) |
| `receta_ingrediente` | Ingredientes de cada receta, con cantidad y unidad. `peso` = 3 principal, 2 secundario, 1 accesorio (para el puntaje, DEC-008). `es_opcional` no cuenta para el puntaje. `nota` = "picado", "para servir"… | → receta (`CASCADE`), → ingrediente |
| `paso` | Pasos numerados de cada receta. `instruccion` puede tener marcadores `{ing:papa}` que se reemplazan por la cantidad escalada; `segundos_timer` = temporizador del paso (modo cocina) | → receta (`CASCADE`) |
| `etiqueta` · `receta_etiqueta` | Vegetariana, sin TACC, económica, dulce | → receta, → etiqueta |
| `sustitucion` | "Si falta X, podés usar Y" (manteca → aceite). Cuenta como medio ingrediente en el puntaje (DEC-008) | `ingrediente_id` y `reemplazo_id` → ingrediente |
| `categoria` · `ingrediente` · `sinonimo` | Catálogo de ingredientes y nombres alternativos (palta = aguacate). `ingrediente.usuario_id` NULL = lista general; con un usuario = ingrediente propio que agregó desde su despensa (DEC-006) | ingrediente → categoria · ingrediente → usuario (`CASCADE`) |
| `despensa_item` | Lo que tiene cada usuario: nivel, vencimiento y ubicación | → usuario (`CASCADE`), → ingrediente |

## Incremento 2

- **Entrega A (hecha):**
  - Se usan `receta_ingrediente.peso`, `nota`, `paso.segundos_timer` y la tabla `sustitucion`.
- **Entrega B (hecha):**

| Tabla | Para qué | Se usa en |
|---|---|---|
| `usuario_basico` | "Siempre tengo": cuenta como disponible y no se descuenta al cocinar (DEC-012) | Mis preferencias, recomendación, catálogo, modo cocina |
| `restriccion_usuario` | "No puedo comer": esas recetas no se recomiendan (DEC-012) | Mis preferencias, recomendación, catálogo |
| `favorito` | Recetas marcadas con ♥ (DEC-012) | Detalle, catálogo, Mis preferencias, API |
| `sesion_cocina` · `paso_completado` | La receta que se está cocinando y los pasos hechos; `stock_descontado` evita descontar dos veces (DEC-011) | Modo cocina, Mi cocina, puntaje (−8 si se cocinó hace poco) |
| `recuperacion` (nueva) | Enlaces de "Olvidé mi contraseña": hash del token, vence en 1 hora, un solo uso (DEC-013) | `olvide.php`, `restablecer.php` |
| `intento_ingreso` (nueva) | Ingresos fallidos por email, para el bloqueo de 15 minutos (DEC-014) | Ingreso (página y API) |
| `receta.imagen` | Ruta de la foto de la receta, dentro de `public/img/recetas/` (DEC-015) | Editor y detalle de la receta, API |

Migración para bases existentes: `database/migraciones/006-incremento-2-b.sql`.

## Incremento 3

Las seis tablas se crean juntas en la entrega A (migración `database/migraciones/007-incremento-3.sql`), aunque
la IA y el plan semanal se programan en las entregas B y C. Los datos con estructura van en `TEXT` con JSON adentro y
las reglas entre columnas se validan en PHP, para que el esquema ande igual en MariaDB y en el MySQL del hosting (DEC-018).

```mermaid
erDiagram
    USUARIO ||--o{ LISTA_COMPRA_ITEM : anota
    INGREDIENTE ||--o{ LISTA_COMPRA_ITEM : "se compra"
    RECETA |o--o{ LISTA_COMPRA_ITEM : "para"
    USUARIO ||--o{ RECETA_IA : guarda
    RECETA_IA |o--o{ LISTA_COMPRA_ITEM : "para"
    USUARIO ||--o{ PLAN_COMIDA : planifica
    RECETA |o--o{ PLAN_COMIDA : ""
    RECETA_IA |o--o{ PLAN_COMIDA : ""
    USUARIO |o--o{ USO_IA : pide
    INGREDIENTE ||--o{ SUGERENCIA_REEMPLAZO : ""
```

| Tabla | Para qué | Relaciones | Se usa en |
|---|---|---|---|
| `lista_compra_item` | La lista de compras. Cada renglón tiene `ingrediente_id` (del catálogo) **o** `texto` (texto libre). `cantidad` NULL = sin cantidad. `comprado_en` NULL = falta comprar. `deshacer` = cómo estaba la despensa antes de comprar (JSON) | → usuario (`CASCADE`), → ingrediente (`CASCADE`), → receta y → receta_ia (`SET NULL`) | Entrega A: `app/lista.php`, receta, despensa, API |
| `receta_ia` | Recetas que propuso la IA. `contenido` = la receta validada (JSON). `guardada` = la guardó la persona. `pasada_a` = la receta del recetario en la que la convirtió la administración | → usuario (`CASCADE`), `receta_base` y `pasada_a` → receta (`SET NULL`) | Entrega B |
| `respuesta_ia` | Caché de la IA: la misma consulta no se paga dos veces durante 7 días. `clave` = sha256 del tipo y los datos | — | Entrega B |
| `uso_ia` | Cada pedido a la IA: límites por día y panel de administración | → usuario (`SET NULL`) | Entrega B |
| `sugerencia_reemplazo` | Reemplazos que sugirió la IA, para pasarlos a `sustitucion` | → ingrediente (`CASCADE`; el reemplazo, `SET NULL`) | Entrega B |
| `plan_comida` | Plan semanal: una receta (del recetario o de la IA) por día y comida. Único por usuario, fecha y momento | → usuario, → receta, → receta_ia (`CASCADE`) | Entrega C |

## Reglas importantes

- No se puede borrar un ingrediente que usa alguna receta o despensa (la clave foránea lo impide y la página lo avisa).
- Al borrar una receta se borran solos sus ingredientes, pasos y etiquetas (`ON DELETE CASCADE`).
- Un renglón de la lista tiene ingrediente o texto, nunca los dos ni ninguno: lo controla `ListaRepositorio::agregar()`.
  Si el ingrediente ya está sin comprar con una unidad que se puede sumar, se suma en el mismo renglón.
- Comprar (pasar a la despensa) y deshacer se hacen en una transacción: no queda nada a medias.
- Un ingrediente que está en alguna lista de compras se puede borrar: se borra también de la lista (`CASCADE`).
- Un usuario no puede tener dos veces el mismo ingrediente en la misma ubicación (`UNIQUE (usuario_id, ingrediente_id, ubicacion)`).
- El nombre de un ingrediente no se repite en la lista general ni entre los propios de un mismo usuario (`UNIQUE (nombre, ambito)`, donde `ambito` = `usuario_id` o 0).
- Las búsquedas ignoran tildes y mayúsculas gracias al cotejamiento `utf8mb4_unicode_ci` ("morron" encuentra "Morrón").
