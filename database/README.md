# database/

| Archivo / carpeta | Qué es | Dueño |
|---|---|---|
| `schema.sql` | Crea todas las tablas. Se puede correr las veces que haga falta (borra y recrea) | P3 |
| `seed.sql` | Datos iniciales: categorías, ingredientes, sinónimos y una receta de ejemplo | P3 |
| `datos/ingredientes.csv` | Planilla de ingredientes exportada (P2-04), fuente del seed | P2 |
| `recetas/*.json` | Una receta por archivo, con el formato de `docs/guia-recetas.md` (50 recetas) | todos |
| `recetas.sql` | Las recetas de `recetas/` como SQL. Se genera con `php scripts/importar-recetas.php --sql`: no editar a mano | P3 |

## Cargar la base

**Con Docker:** se carga sola la primera vez que se crea el contenedor. Para empezar de cero:
```bash
docker compose down -v      # borra la base (¡se pierden los datos locales!)
docker compose up -d        # la recrea con schema.sql + seed.sql
```

**Sin Docker (XAMPP / Laragon) o en el hosting:** en phpMyAdmin, crear la base `cooki`
(intercalación `utf8mb4_unicode_ci`) → pestaña **Importar** → `schema.sql`, después `seed.sql` y por último `recetas.sql`.

**Por consola:**
```bash
mysql -u root -p cooki < database/schema.sql
mysql -u root -p cooki < database/seed.sql
mysql -u root -p cooki < database/recetas.sql
```

## Si ya tenías la base de Alacena

El proyecto cambió de nombre: la base pasó a llamarse `cooki` y los usuarios de prueba a `@cooki.test`
(por ejemplo `admin@cooki.test`). Una base vieja no tiene esos usuarios, así que hay que crearla de nuevo.
Primero, en tu `.env` cambiá `alacena` por `cooki` en `DB_NAME`, `DB_USER`, `DB_PASS`, `MAIL_CONTACTO` y `MAIL_REMITENTE`
(o copiá otra vez `.env.example`). Después:

- **Con Docker:** `docker compose down` y `docker compose up -d --build`. Como cambió el nombre del proyecto,
  Docker crea una base nueva y la carga sola. La vieja queda guardada sin usar; se borra con
  `docker volume rm alacena_datos`.
- **Con XAMPP / Laragon:** crear la base `cooki` e importar `schema.sql` y `seed.sql` como arriba.
  La base `alacena` se puede borrar desde phpMyAdmin.
- Si preferís conservar tu base vieja, alcanza con poner `DB_NAME=alacena` en tu `.env`, pero los usuarios de
  prueba seguirán siendo `@alacena.test` y algunas pruebas automáticas van a fallar.

## Migraciones

Si ya tenías la base creada y `schema.sql` cambió, en `migraciones/` está el cambio suelto para no perder tus datos:
```bash
mysql -u cooki -p cooki < database/migraciones/001-tanda-heladera.sql
mysql -u cooki -p cooki < database/migraciones/002-tanda-heladera-2.sql   # siempre en orden
mysql -u cooki -p cooki < database/migraciones/003-tanda-heladera-3.sql
mysql -u cooki -p cooki < database/migraciones/004-tanda-heladera-4.sql
mysql -u cooki -p cooki < database/migraciones/005-incremento-2-a.sql
mysql -u cooki -p cooki < database/migraciones/006-incremento-2-b.sql
mysql -u cooki -p cooki < database/migraciones/007-incremento-3.sql
mysql -u cooki -p cooki < database/migraciones/008-nota-lista.sql
mysql -u cooki -p cooki < database/recetas.sql                           # el recetario nuevo
```

## Reglas
- Todo cambio de tablas va en `schema.sql` **y** se avisa en el grupo: afecta a los cuatro.
- Nunca se sube un volcado con datos de usuarios reales.
- La intercalación `utf8mb4_unicode_ci` hace que las búsquedas ignoren tildes y mayúsculas ("morron" = "Morrón"). No cambiarla.
