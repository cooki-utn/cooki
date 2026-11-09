-- Incremento 3: la nota de la lista de compras en Mi cocina y Mi despensa.
-- Solo si ya tenías la base (si no: docker compose down -v && docker compose up -d).
--   mysql -u cooki -p cooki < database/migraciones/008-nota-lista.sql
-- En el hosting: phpMyAdmin → la base → Importar → este archivo. Se importa UNA sola vez
-- (si se importa de nuevo da "Duplicate column name": no pasa nada, la columna ya está).

-- abierta = se ve la nota entera · cerrada = se ve un recuadro chico para desplegarla
-- nunca = no se muestra (se cambia en Mis preferencias)
ALTER TABLE usuario ADD COLUMN nota_lista VARCHAR(10) NOT NULL DEFAULT 'abierta' AFTER volumen;
