# Incremento 3 · Resumen: IA en las recetas, lista de compras y plan semanal

> **Entregas:** A (lista de compras y correo), B (la IA), C (plan semanal, accesibilidad y diseño) y D (tipos de
> receta con dibujo y la bienvenida "Contanos cómo comés").
> **Pruebas:** **221 en verde** al cerrar la D (eran 119 antes de la entrega A; 2 se saltean si PHP no tiene GD).
> Ninguna usa la IA real. El lint queda limpio.
> **Detalle de cada entrega:** [cambios-incremento-3-a.md](cambios-incremento-3-a.md),
> [cambios-incremento-3-b.md](cambios-incremento-3-b.md), [cambios-incremento-3-c.md](cambios-incremento-3-c.md) y
> [cambios-incremento-3-d.md](cambios-incremento-3-d.md).

## 1. Objetivos

| Objetivo | Cómo se cumple |
|---|---|
| **OE9** · IA generativa de forma controlada | Tres usos de Gemini (receta con lo que tenés, adaptar una receta, ¿con qué lo reemplazo?), con límites diarios, caché de 7 días, validación de cada respuesta contra el catálogo y lo que la persona no come, y sin mandar datos personales. Nada de la IA entra solo al recetario (DEC-020) |
| **OE10** · Cerrar el ciclo de compras | De la receta a la lista (lo que falta, con las porciones), de la lista a la despensa (Lo compré, con Deshacer) y un plan semanal que gasta la despensa en orden y usa primero lo que vence (DEC-018, DEC-019, DEC-022) |
| **OE8** · Correo real | El hosting manda los correos con Gmail por SMTP: recuperar la contraseña y el correo de prueba de la administración |

## 2. Historias de usuario

| HU | Qué | Entrega | Rol |
|---|---|:-:|---|
| HU-26 | Lo que falta de una receta, a la lista | A | P2 |
| HU-27 | Agregar a mano y desde la despensa | A | P2 |
| HU-28 | Lo compré → a la despensa, con Deshacer, otra cantidad y todo junto | A | P2 |
| HU-29 | Compartir (copiar, WhatsApp) e imprimir la lista | A | P2 |
| HU-30 | Una receta de la IA con lo que tenés | B | P3 + P2 |
| HU-31 | Adaptar una receta con IA | B | P3 |
| HU-32 | ¿Con qué lo reemplazo? (IA) | B | P3 |
| HU-33 | Límites, caché y privacidad de la IA | B | P1 |
| HU-34 | Administración de la IA | B | P1 + P3 |
| HU-35 | Elegir recetas por día en el plan semanal | C | P3 + P2 |
| HU-36 | Armá mi semana | C | P3 |
| HU-37 | Lo que falta para la semana, a la lista | C | P3 + P2 |
| HU-38 | Correo real desde el hosting | A | P1 |
| HU-39 | Equipo de cuatro en el repositorio | A | P1 |

Además: "Volver" a la misma altura (DEC-021), la hora del servidor (DEC-023), accesibilidad con axe-core y el menú
de stickers con dibujo, el pie y las letras sin salto (DEC-024). En la entrega D: 9 tipos de receta con dibujo,
la dificultad con semáforo y 8 recetas nuevas (DEC-025), y la bienvenida "Contanos cómo comés", donde la dieta filtra
las recomendaciones y los gustos las ordenan (DEC-026).

## 3. Base de datos

| Migración | Qué trae | Entrega |
|---|---|:-:|
| `007-incremento-3.sql` | Las seis tablas: `lista_compra_item`, `receta_ia`, `respuesta_ia`, `uso_ia`, `sugerencia_reemplazo` y `plan_comida` | A |
| `008-nota-lista.sql` | `usuario.nota_lista` | A |
| `009-nota-hoy.sql` | `usuario.nota_hoy` | C |
| `010-personalizar.sql` | Tipos 5 a 9, 8 recetas nuevas, `usuario_tipo`, `usuario.nivel_cocina` y `usuario.comensales` | D |

En una base que ya existe se importan en orden, una sola vez cada una. Detalle en [modelo-datos.md](modelo-datos.md).

## 4. Configuración

- `MAIL_*`: el correo por SMTP de Gmail (entrega A, [publicar.md](publicar.md), paso 6).
- `IA_*`: la clave y los límites de Gemini (entrega B, paso 7). Sin `IA_CLAVE`, la IA queda apagada y todo lo demás anda.
- Opcional: `mod_expires` y `mod_headers` en Apache para la caché del navegador (entrega C).

## 5. API nueva

| Rutas | Entrega |
|---|:-:|
| `GET /lista`, `POST /lista`, `POST` y `DELETE /lista/{id}/comprado`, `DELETE /lista/{id}` | A |
| `GET /ia`, `POST /ia/receta`, `POST /ia/adaptar/{id}`, `POST /ia/reemplazo` | B |
| `GET /plan`, `PUT` y `DELETE /plan/{fecha}/{momento}` | C |
| Sin rutas nuevas; `/recomendaciones` respeta la dieta y `etiquetas` suma los tipos nuevos | D |

Contrato completo en [api.md](api.md).

## 6. Pruebas

| Al cerrar | Pruebas | Nuevas |
|---|--:|---|
| Antes de la entrega A | 119 | — |
| Entrega A | 145 | `ListaComprasTest` (11), `ListaTest` (7) y 8 más (correo, equipo y páginas) |
| Entrega B | 182 | `RecetaIaTest` (13), `ClienteGeminiTest` (5), `IaTest` (16) y 3 de páginas de la IA |
| Entrega C | 204 | `PlanSemanalTest` (13) y `PlanTest` (9) |
| Entrega D | 221 | `TiposTest` (5), `PerfilTest` (5), `BienvenidaTest` (4), `SugerenciasTest` (2) y 1 del importador |

## 7. Lo que queda a futuro

- Avisos de vencimiento por correo y despensa compartida: el hosting gratuito no tiene tareas programadas y la despensa
  compartida cambia el modelo de datos.
- El menú en una columna al costado (probado, no adoptado por ahora).
- En el pie: GitHub, créditos de las letras y una página de privacidad.
- Chipá y fainá (necesitan ingredientes nuevos) y tipos revisados en las recetas de la IA, para filtrarlas por dieta.
