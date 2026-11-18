# Contrato de la API v1

Todas las rutas empiezan con `api/` y responden JSON (`Content-Type: application/json; charset=utf-8`).
Los errores tienen siempre la forma `{ "error": "mensaje para mostrar" }`.
La sesión viaja en la cookie de sesión de PHP (la misma que usan las páginas): el frontend no maneja tokens.
Las peticiones que modifican datos (`POST`, `DELETE`…) tienen que mandar `Content-Type: application/json`:
un formulario de otro sitio no puede hacerlo sin permiso, así que eso también protege contra CSRF.

| Método | Ruta | Sesión | Qué hace | Desde |
|---|---|:-:|---|---|
| GET | `/api/salud` | No | Estado de la API y la base | Incremento 1 |
| GET | `/api/ingredientes?q=texto` | No | Busca por nombre o sinónimo, ignora tildes | Incremento 1 |
| POST | `/api/usuarios` | No | Registro (y queda logueado) | Incremento 2 |
| POST | `/api/sesion` | No | Ingreso (con bloqueo por intentos) | Incremento 2 |
| GET | `/api/yo` | Sí | Usuario logueado | Incremento 2 |
| DELETE | `/api/sesion` | No | Salir | Incremento 2 |
| GET | `/api/recetas?q=&pagina=&por_pagina=` | No | Catálogo paginado | Incremento 2 |
| GET | `/api/recetas/{id}?porciones=N` | No | Una receta para N porciones | Incremento 2 |
| GET | `/api/recomendaciones?limite=20` | Sí | Qué puede cocinar, por puntaje | Incremento 2 |
| GET | `/api/despensa` | Sí | La despensa del usuario | Incremento 2 |
| POST | `/api/favoritos/{id}` | Sí | Marca la receta como favorita | Incremento 2 |
| DELETE | `/api/favoritos/{id}` | Sí | La desmarca | Incremento 2 |
| GET | `/api/lista` | Sí | La lista de compras, por góndola, y lo comprado hoy | Incremento 3 |
| POST | `/api/lista` | Sí | Agrega un ingrediente o un texto libre (suma si ya está) | Incremento 3 |
| POST | `/api/lista/{id}/comprado` | Sí | Lo marca como comprado y pasa a la despensa | Incremento 3 |
| DELETE | `/api/lista/{id}/comprado` | Sí | Deshace la compra de hoy | Incremento 3 |
| DELETE | `/api/lista/{id}` | Sí | Lo quita de la lista | Incremento 3 |
| GET | `/api/ia` | Sí | Si hay IA y cuántos pedidos le quedan hoy | Incremento 3 |
| POST | `/api/ia/receta` | Sí | Receta nueva con lo que tiene en la despensa (o "Pedir otra") | Incremento 3 |
| POST | `/api/ia/adaptar/{id}` | Sí | Adapta una receta del recetario (sin huevo, sin TACC…) | Incremento 3 |
| POST | `/api/ia/reemplazo` | Sí | Con qué reemplazar un ingrediente que falta | Incremento 3 |
| GET | `/api/plan` | Sí | El plan de los 7 días desde hoy, con lo que falta en cada comida | Incremento 3 |
| PUT | `/api/plan/{fecha}/{momento}` | Sí | Pone o cambia la receta de un almuerzo o una cena | Incremento 3 |
| DELETE | `/api/plan/{fecha}/{momento}` | Sí | Deja ese lugar vacío | Incremento 3 |

Códigos comunes:
- `400`: el pedido está mal armado (en el plan: no vino ninguna receta, vinieron las dos o las porciones no son un número entero).
- `401`: hace falta iniciar sesión.
- `404`: no existe.
- `405`: el método no va con esa ruta.
- `415`: no se mandó JSON.
- `422`: datos inválidos.
- `429`: demasiados intentos (o, en la IA, el límite del día).

---

## GET /api/ingredientes

Busca ingredientes por nombre canónico o sinónimo.

**Parámetros:** `q` (texto, mínimo 2 caracteres).

**Respuesta 200**
```json
{ "resultados": [ { "id": 3, "nombre": "Morrón", "categoria": "Verduras" } ] }
```

**Errores:** `400` si `q` tiene menos de 2 caracteres.

---

## POST /api/usuarios

**Cuerpo:** `{ "nombre": "Ana", "email": "ana@ejemplo.com", "contrasena": "Clave1234" }`

**Respuesta 201** (queda logueada): `{ "usuario": { "id": 9, "nombre": "Ana", "email": "ana@ejemplo.com", "rol": "general" } }`

**Errores:**
- `422`: `{ "error": "Revisá los datos", "campos": { "email": "El email no es válido." } }`.
- `409`: ya hay una cuenta con ese email.

## POST /api/sesion · DELETE /api/sesion · GET /api/yo

- **`POST /api/sesion`**
  - **Cuerpo:** `{ "email": "...", "contrasena": "..." }`.
  - **Respuesta 200:** `{ "usuario": {...} }`.
  - **Errores:**
    - `401`: datos incorrectos (el mensaje es el mismo exista o no la cuenta).
    - `429`: el email está bloqueado por 5 errores en 15 minutos (DEC-014).
- **`DELETE /api/sesion`:** responde `204` sin cuerpo.
- **`GET /api/yo`:** responde `{ "usuario": {...} }`, o `401` si no hay sesión.

## GET /api/recetas

**Parámetros:**
- `q`: busca en el título, la descripción y los ingredientes.
- `pagina`: por defecto 1.
- `por_pagina`: de 1 a 50; por defecto 12.

**Respuesta 200**
```json
{
  "recetas": [
    { "id": 1, "titulo": "Tortilla de papas", "descripcion": "...", "minutos": 40, "dificultad": "Fácil",
      "porciones": 4, "etiquetas": ["economica", "sin_tacc"], "ingredientes": 5, "tengo": 5, "favorita": true }
  ],
  "paginacion": { "pagina": 1, "paginas": 6, "por_pagina": 12, "total": 62 }
}
```
Sin sesión, `tengo` cuenta solo los básicos y `favorita` es `false`.

## GET /api/recetas/{id}

**Parámetros:** `porciones` (1 a 12; por defecto, las de la receta).

**Respuesta 200**
```json
{
  "id": 1, "titulo": "Tortilla de papas", "porciones": 2, "porciones_base": 4, "minutos": 40,
  "dificultad": "Fácil", "imagen": null, "etiquetas": ["economica"], "favorita": false,
  "ingredientes": [
    { "id": 1, "nombre": "Papa", "cantidad": 300, "unidad": "g", "texto": "300 g",
      "opcional": false, "tengo": true, "sustituto": null }
  ],
  "pasos": [ { "numero": 2, "texto": "Calentar el aceite y freír...", "segundos": 900 } ]
}
```

**Errores:** `404` si no existe.

## GET /api/recomendaciones

Pide sesión.

**Parámetros:** `limite` (1 a 60; por defecto 20).

Cada recomendación trae lo mismo que una receta del catálogo, y además:
- `puntaje`;
- `grupo` (`ahora`, `falta1` o `casi`);
- `faltantes`;
- `sustituciones` (`[{ "falta": "Crema de leche", "usar": "Leche" }]`);
- `usa_lo_que_vence`.

## GET /api/despensa

Pide sesión.

**Respuesta 200**
```json
{ "despensa": [ { "id": 12, "ingrediente_id": 1, "nombre": "Papa", "nivel": "Normal", "cantidad": 1,
                  "unidad": "kg", "vence": "2026-11-10", "dias": 7, "ubicacion": "alacena" } ] }
```

## POST · DELETE /api/favoritos/{id}

Pide sesión.

**Respuesta 200:** `{ "receta": 2, "favorita": true }`. Si ya estaba así, no cambia nada y responde igual.

**Errores:** `404` si la receta no existe.

## GET /api/lista

Pide sesión. `pendientes` viene agrupado por categoría, en el orden de las góndolas; lo que no está en el catálogo
(texto libre) va en "Otros". `comprado_hoy` es lo que se marcó hoy (mañana ya no aparece). `texto` es la lista
lista para copiar o mandar por WhatsApp.

**Respuesta 200**
```json
{ "pendientes": [ { "categoria": "Verduras", "items": [
      { "id": 7, "ingrediente_id": 1, "nombre": "Papa", "cantidad": 300, "unidad": "g", "texto_cantidad": "300 g",
        "categoria": "Verduras", "receta": "Tortilla de papas", "comprado_en": null } ] } ],
  "comprado_hoy": [],
  "texto": "Lista de compras

*Verduras*
- Papa · 300 g" }
```

## POST /api/lista

Pide sesión. **Cuerpo:** `{ "ingrediente_id": 4, "cantidad": 2, "unidad": "u" }` o `{ "texto": "Servilletas" }`.
`cantidad` y `unidad` son opcionales; la unidad puede ser `u`, `g`, `kg`, `ml` o `l`. Un texto que coincide con un
ingrediente del catálogo (o con un sinónimo) se guarda como ese ingrediente.

**Respuesta 201** (renglón nuevo) o **200** (ya estaba sin comprar y se sumó la cantidad): `{ "id": 7, "sumado": false }`.

**Errores:** `400` si falta `ingrediente_id` o `texto` (o vienen los dos), o si la cantidad o la unidad no sirven;
`422` si el ingrediente no existe o el texto no tiene entre 2 y 80 letras.

## POST · DELETE /api/lista/{id}/comprado

Pide sesión. `POST` lo marca como comprado: si es un ingrediente, pasa a la despensa (nivel "Normal", vencimiento
estimado con la vida útil del ingrediente y la ubicación que ya tenía o la sugerida). `DELETE` lo deshace, solo el
mismo día: vuelve a la lista y la despensa queda como estaba.

Si se compró otra cantidad de la prevista, el `POST` puede llevar `{ "cantidad": 2, "unidad": "kg" }` (la unidad, una de
`u`, `g`, `kg`, `ml` o `l`): esa cantidad pasa a la despensa y queda en el renglón. Al deshacer vuelve la prevista.

**Respuesta 200:** `{ "id": 7, "comprado": true }` (o `false` al deshacer).

**Errores:** `400` si la cantidad o la unidad no sirven; `404` si no está en la lista del usuario, si ya estaba comprado (al marcar) o si no hay nada que deshacer.

## DELETE /api/lista/{id}

Pide sesión. **Respuesta 200:** `{ "id": 7, "quitado": true }`. **Errores:** `404` si no está en la lista del usuario.

## La IA (incremento 3)

Las cuatro rutas piden sesión. Ninguna manda a Google el nombre ni el correo: solo ingredientes, restricciones y las
opciones elegidas. Cada persona tiene `IA_LIMITE_USUARIO_DIA` pedidos por día (5 por defecto) y todo el sitio
`IA_LIMITE_GLOBAL_DIA` (200). Solo descuentan los pedidos que salieron bien; la misma consulta, durante 7 días, sale
de la caché y no descuenta. Los reemplazos gastan 1 pedido cada 5. Ver DEC-020.

Si la IA no puede, responde `{ "error": "..." }` con un mensaje para mostrar tal cual:

| Código | Cuándo |
|---|---|
| `503` | La IA no está configurada (falta `IA_CLAVE`) |
| `429` | Se llegó al límite del día (de la persona o del sitio) |
| `422` | Faltan datos o el pedido no tiene sentido (menos de 2 ingredientes en la despensa, una adaptación que la receta ya cumple, un ingrediente que ya tiene) |
| `504` | Gemini tardó más de `IA_TIMEOUT` segundos |
| `502` | Gemini falló o respondió algo que no sirve (también si la receta usaba algo que la persona no come) |

## GET /api/ia

**Respuesta 200:** `{ "disponible": true, "restantes": 3, "limite": 5 }`. Sin IA: `disponible` es `false` y `restantes`, 0.

## POST /api/ia/receta

**Cuerpo:** `{ "porciones": 2, "minutos": 30, "tipo": "almuerzo_cena" }`. `minutos` es 15, 30 o 60; `tipo`,
`almuerzo_cena`, `dulce` o `rapida`; `porciones`, de 1 a 8. Lo que no sirva se cambia por el valor por defecto.
Para **"Pedir otra"**: `{ "otra_de": 12 }` (una receta de IA de la persona): usa las mismas opciones, le pide a la IA que
no repita las que ya vio y no sale de la caché.

**Respuesta 201**
```json
{ "id": 13, "titulo": "Revuelto de zapallitos", "url": "/app/receta-ia.php?id=13", "restantes": 2 }
```

**Errores:** los de la tabla de arriba y `404` si `otra_de` no es una receta de IA de la persona.

## POST /api/ia/adaptar/{id}

**Cuerpo:** `{ "adaptacion": "sin_huevo", "porciones": 4 }`. `adaptacion` es `sin_huevo`, `sin_tacc`, `sin_lactosa`,
`vegetariana` o `con_lo_que_tengo` (reemplaza lo que falta por cosas de la despensa: es la única que manda la
despensa). La receta original no cambia: se crea una receta de IA adaptada, con "Qué cambió".

**Respuesta 201:** igual que `POST /api/ia/receta`.

**Errores:** `404` si la receta no existe; `422` si esa adaptación no hace falta (la receta ya la cumple).

## POST /api/ia/reemplazo

**Cuerpo:** `{ "receta": 1, "ingrediente": 15, "porciones": 4 }`: un ingrediente que la receta lleva y que a la persona
le falta. La respuesta no se guarda en la cuenta, pero queda anotada (sin datos de la persona) para que la
administración la pase a la tabla de sustituciones.

**Respuesta 200**
```json
{ "reemplazo": "Leche", "ingrediente_id": 24, "ratio": 1, "nota": "Queda más suave.",
  "frase": "Usá leche: la misma cantidad (600 g).", "tengo": true, "restantes": 4, "sin_gastar": 4,
  "aviso": "Te quedan 4 reemplazos sin gastar pedidos (cada 5 reemplazos se gasta 1 pedido)." }
```
`ingrediente_id` es `null` si el reemplazo no está en el catálogo; `tengo` dice si está en la despensa.

**Errores:** `404` si la receta no existe; `422` si el ingrediente no está en la receta o ya lo tiene.

## El plan semanal (incremento 3)

Las tres rutas piden sesión. La semana son los 7 días a partir de hoy, con almuerzo y cena. `{fecha}` es `AAAA-MM-DD`
(de hoy a 6 días más) y `{momento}`, `almuerzo` o `cena`; cualquier otro lugar responde `422`. Lo que falta en cada
comida se calcula gastando la despensa en orden: lo que usa el almuerzo ya no está para la cena (DEC-022).

## GET /api/plan

**Respuesta 200**
```json
{ "dias": [
  { "fecha": "2026-11-14", "dia": "Hoy",
    "almuerzo": { "receta_id": 3, "receta_ia_id": null, "titulo": "Tortilla de papas", "porciones": 4, "minutos": 40,
                  "tiene_todo": false,
                  "faltantes": [ { "ingrediente_id": 15, "nombre": "Huevo", "cantidad": 2, "unidad": "u" } ] },
    "cena": null },
  { "fecha": "2026-11-15", "dia": "Mañana", "almuerzo": null, "cena": null }
] }
```
Un lugar vacío es `null`. En `faltantes`, `cantidad` y `unidad` son `null` cuando no se puede calcular (por ejemplo,
"sal a gusto"); `ingrediente_id` es `null` en lo que una receta de IA usa y no está en el catálogo.

## PUT /api/plan/{fecha}/{momento}

**Cuerpo:** `{ "receta_id": 3, "porciones": 4 }` o `{ "receta_ia_id": 5 }`: una de las dos, nunca las dos. `porciones`
es opcional (de 1 a 12); si no viene, van las de la receta. Si en ese lugar ya había una receta, se cambia.

**Respuesta 200:** `{ "fecha": "2026-11-14", "momento": "almuerzo", "receta_id": 3, "receta_ia_id": null,
"titulo": "Tortilla de papas", "porciones": 4, "minutos": 40 }`. Para ver qué falta, pedí `GET /api/plan`, porque
cambiar una comida cambia lo que falta en las siguientes.

**Errores:** `400` si no vino ninguna receta, vinieron las dos o `porciones` no es un entero de 1 a 12; `404` si la
receta no existe o la receta de IA no es de la persona; `422` si el lugar no es de esta semana.

## DELETE /api/plan/{fecha}/{momento}

**Respuesta 200:** `{ "fecha": "2026-11-14", "momento": "almuerzo", "quitado": true }`.

**Errores:** `404` si en ese lugar no había nada; `422` si el lugar no es de esta semana.
