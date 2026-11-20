-- Incremento 3 · PR D: tipos de receta nuevos, recetas nuevas y la personalización de la cuenta.
-- Solo si ya tenías la base (si no: docker compose down -v && docker compose up -d).
--   mysql -u cooki -p cooki < database/migraciones/010-personalizar.sql
-- En el hosting: phpMyAdmin → la base → Importar → este archivo. Se importa UNA sola vez: las 8 recetas nuevas
-- se borran y se vuelven a crear, y con ellas se irían los favoritos o el plan que alguien haya hecho con ellas.
-- (Si se importa de nuevo, el ALTER del final da "Duplicate column name": no pasa nada.)

SET NAMES utf8mb4;

-- ─────────── Tipos nuevos ("rapida" se calcula sola: 30 minutos o menos entre preparación y cocción) ───────────
INSERT IGNORE INTO etiqueta (id, nombre) VALUES
 (5, 'vegana'), (6, 'sin_lactosa'), (7, 'sin_huevo'), (8, 'rapida'), (9, 'merienda_salada');

-- Vegana, Sin lactosa y Sin huevo en las recetas que ya estaban (cumplen la regla de cada tipo)
INSERT IGNORE INTO receta_etiqueta (receta_id, etiqueta_id)
 SELECT id, 5 FROM receta WHERE slug IN (
  'ensalada-de-frutas', 'ensalada-de-garbanzos', 'guiso-de-lentejas', 'pan-casero', 'sopa-de-verduras');
INSERT IGNORE INTO receta_etiqueta (receta_id, etiqueta_id)
 SELECT id, 6 FROM receta WHERE slug IN (
  'arroz-con-pollo', 'arroz-salteado-con-verduras', 'bondiola-al-horno-con-batatas', 'empanadas-de-carne',
  'ensalada-de-arroz', 'ensalada-de-atun', 'ensalada-de-frutas', 'ensalada-de-garbanzos',
  'ensalada-de-lentejas', 'ensalada-de-repollo-y-zanahoria', 'ensalada-rusa', 'estofado-de-carne-con-papas',
  'guiso-de-arroz', 'guiso-de-garbanzos', 'guiso-de-lentejas', 'hamburguesas-caseras', 'locro-sencillo',
  'merluza-al-horno-con-papas', 'milanesas-de-berenjena', 'milanesas-de-pollo', 'muffins-de-banana',
  'pan-casero', 'pollo-al-horno-con-papas', 'revuelto-gramajo', 'salteado-de-pollo-con-verduras',
  'sopa-de-verduras', 'tortilla-de-papas');
INSERT IGNORE INTO receta_etiqueta (receta_id, etiqueta_id)
 SELECT id, 7 FROM receta WHERE slug IN (
  'arroz-con-leche', 'arroz-con-pollo', 'bondiola-al-horno-con-batatas', 'crema-de-calabaza',
  'empanadas-de-jamon-y-queso', 'ensalada-de-frutas', 'ensalada-de-garbanzos', 'estofado-de-carne-con-papas',
  'fideos-a-la-crema-con-champinones', 'fideos-con-brocoli-y-ajo', 'fideos-con-manteca-y-queso',
  'fideos-con-tuco', 'guiso-de-arroz', 'guiso-de-garbanzos', 'guiso-de-lentejas', 'licuado-de-banana',
  'locro-sencillo', 'merluza-al-horno-con-papas', 'pan-casero', 'pizza-casera', 'polenta-con-queso',
  'polenta-con-salsa-y-queso', 'pollo-a-la-mostaza', 'pollo-al-horno-con-papas', 'postre-vigilante',
  'pure-de-papas', 'risotto-de-calabaza', 'salteado-de-pollo-con-verduras', 'sopa-de-puerro-y-papa',
  'sopa-de-verduras', 'torta-de-galletitas-con-dulce-de-leche');
-- Rápida: todas las de 30 minutos o menos (también las que cargó la administración)
INSERT IGNORE INTO receta_etiqueta (receta_id, etiqueta_id)
 SELECT id, 8 FROM receta WHERE minutos_prep + minutos_coccion <= 30;

-- ─────────── 8 recetas nuevas: 4 veganas y 4 meriendas saladas (generadas con el importador) ───────────
-- Usan solo ingredientes de la lista general (ids 1 a 107, iguales en todas las bases).

-- Hamburguesas de lentejas
DELETE FROM receta WHERE slug = 'hamburguesas-de-lentejas';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('hamburguesas-de-lentejas', 'Hamburguesas de lentejas', 'Doraditas por fuera, sin carne ni huevo.', 4, 20, 35, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 30, 250, 'g', 3, FALSE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 28, 1, 'u', 2, FALSE, 'rallada'),
 (@receta, 86, 60, 'g', 2, FALSE, NULL),
 (@receta, 29, 1, 'diente', 1, FALSE, NULL),
 (@receta, 80, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir las lentejas hasta que estén bien tiernas y escurrirlas.', 1500),
 (@receta, 2, 'Rehogar la cebolla y el ajo picados con un poco de aceite.', NULL),
 (@receta, 3, 'Pisar las lentejas y mezclarlas con la cebolla, la zanahoria, la avena, el comino y sal. Formar 8 hamburguesas.', NULL),
 (@receta, 4, 'Dorarlas en una sartén con el resto del aceite, de los dos lados.', 480);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 5), (@receta, 6), (@receta, 7), (@receta, 3);

-- Chili de porotos
DELETE FROM receta WHERE slug = 'chili-de-porotos';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('chili-de-porotos', 'Chili de porotos', 'Un guiso picantito y rendidor, con porotos y morrón.', 4, 15, 30, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 21, 300, 'g', 3, FALSE, 'ya cocidos'),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 3, 1, 'u', 2, FALSE, NULL),
 (@receta, 43, 400, 'g', 2, FALSE, NULL),
 (@receta, 29, 2, 'diente', 1, FALSE, NULL),
 (@receta, 80, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 34, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Picar la cebolla, el morrón y el ajo.', NULL),
 (@receta, 2, 'Rehogarlos en el aceite hasta que estén blandos.', 480),
 (@receta, 3, 'Sumar el comino y el pimentón, revolver un minuto y agregar el puré de tomate y los porotos.', NULL),
 (@receta, 4, 'Cocinar a fuego bajo, revolviendo de vez en cuando, hasta que espese. Salar a gusto.', 1200);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 5), (@receta, 2), (@receta, 6), (@receta, 7), (@receta, 3);

-- Curry de garbanzos
DELETE FROM receta WHERE slug = 'curry-de-garbanzos';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('curry-de-garbanzos', 'Curry de garbanzos', 'Con coco, tomate y jengibre, para servir con arroz.', 4, 15, 25, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 87, 300, 'g', 3, FALSE, 'ya cocidos'),
 (@receta, 18, 250, 'g', 2, FALSE, NULL),
 (@receta, 100, 50, 'g', 2, FALSE, 'remojado en una taza de agua caliente'),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 4, 2, 'u', 2, FALSE, NULL),
 (@receta, 29, 2, 'diente', 1, FALSE, NULL),
 (@receta, 106, 10, 'g', 1, FALSE, 'rallado'),
 (@receta, 80, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Cocinar el arroz en agua con sal.', 1080),
 (@receta, 2, 'Rehogar la cebolla, el ajo y el jengibre picados en el aceite.', NULL),
 (@receta, 3, 'Sumar el comino y los tomates picados y cocinar hasta que se deshagan.', NULL),
 (@receta, 4, 'Agregar los garbanzos y el coco con su agua. Cocinar a fuego bajo hasta que la salsa espese.', 600),
 (@receta, 5, 'Servir el curry arriba del arroz.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 5), (@receta, 2), (@receta, 6), (@receta, 7);

-- Wok de fideos con verduras
DELETE FROM receta WHERE slug = 'wok-de-fideos-con-verduras';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('wok-de-fideos-con-verduras', 'Wok de fideos con verduras', 'Fideos salteados con brócoli, zanahoria y salsa de soja.', 3, 15, 15, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 17, 300, 'g', 3, FALSE, NULL),
 (@receta, 56, 1, 'u', 2, FALSE, NULL),
 (@receta, 28, 1, 'u', 2, FALSE, 'en tiritas'),
 (@receta, 3, 1, 'u', 2, FALSE, 'en tiritas'),
 (@receta, 105, 30, 'ml', 2, FALSE, NULL),
 (@receta, 106, 10, 'g', 1, TRUE, NULL),
 (@receta, 29, 1, 'diente', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir los fideos en agua con sal y escurrirlos.', 600),
 (@receta, 2, 'Cortar el brócoli en arbolitos y las demás verduras en tiritas.', NULL),
 (@receta, 3, 'Saltear las verduras con el aceite, el ajo y el jengibre a fuego fuerte, que queden crocantes.', 300),
 (@receta, 4, 'Sumar los fideos y la salsa de soja y mezclar bien.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 5), (@receta, 6), (@receta, 7), (@receta, 8);

-- Scones de queso
DELETE FROM receta WHERE slug = 'scones-de-queso';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('scones-de-queso', 'Scones de queso', 'Esponjosos y bien quesosos, para el mate.', 4, 15, 15, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 16, 300, 'g', 3, FALSE, NULL),
 (@receta, 25, 100, 'g', 3, FALSE, NULL),
 (@receta, 10, 60, 'g', 2, FALSE, NULL),
 (@receta, 24, 120, 'ml', 2, FALSE, NULL),
 (@receta, 15, 1, 'u', 2, FALSE, NULL),
 (@receta, 85, 15, 'g', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Mezclar la harina, el polvo de hornear, la sal y el queso.', NULL),
 (@receta, 2, 'Sumar la manteca fría en cubitos y deshacerla con los dedos hasta que quede arenoso.', NULL),
 (@receta, 3, 'Agregar el huevo y la leche y unir sin amasar de más.', NULL),
 (@receta, 4, 'Estirar de 2 cm de alto, cortar redondeles y pintarlos con un poco de leche.', NULL),
 (@receta, 5, 'Hornear a 200 °C hasta que estén doraditos.', 900);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 9), (@receta, 8);

-- Tostado de jamón y queso
DELETE FROM receta WHERE slug = 'tostado-de-jamon-y-queso';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('tostado-de-jamon-y-queso', 'Tostado de jamón y queso', 'El clásico de la merienda, crocante y con el queso derretido.', 2, 5, 5, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 70, 4, 'u', 3, FALSE, 'rebanadas de pan lactal'),
 (@receta, 26, 100, 'g', 3, FALSE, NULL),
 (@receta, 12, 100, 'g', 3, FALSE, 'en fetas'),
 (@receta, 10, 20, 'g', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Armar dos sándwiches con el jamón y el queso.', NULL),
 (@receta, 2, 'Untar el pan por fuera con manteca, si querés que quede más dorado.', NULL),
 (@receta, 3, 'Tostarlos en una sartén o tostadora, apretando, hasta que el queso se derrita.', 300);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 9), (@receta, 8);

-- Bruschettas de tomate
DELETE FROM receta WHERE slug = 'bruschettas-de-tomate';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('bruschettas-de-tomate', 'Bruschettas de tomate', 'Pan tostado con tomate, albahaca y un toque de ajo.', 4, 10, 5, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 70, 8, 'u', 3, FALSE, 'rodajas de pan francés'),
 (@receta, 4, 3, 'u', 3, FALSE, NULL),
 (@receta, 78, 10, 'g', 2, FALSE, NULL),
 (@receta, 29, 1, 'diente', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Picar los tomates en cubitos y mezclarlos con la albahaca, la mitad del aceite y sal.', NULL),
 (@receta, 2, 'Tostar las rodajas de pan.', 300),
 (@receta, 3, 'Frotar cada tostada con el diente de ajo cortado al medio y rociarlas con el resto del aceite.', NULL),
 (@receta, 4, 'Poner el tomate arriba y servir enseguida.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 5), (@receta, 6), (@receta, 7), (@receta, 3), (@receta, 9), (@receta, 8);

-- Tostadas con palta y huevo
DELETE FROM receta WHERE slug = 'tostadas-con-palta-y-huevo';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('tostadas-con-palta-y-huevo', 'Tostadas con palta y huevo', 'Palta pisada con limón y un huevo arriba.', 2, 10, 5, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 70, 4, 'u', 3, FALSE, 'rodajas'),
 (@receta, 8, 1, 'u', 3, FALSE, NULL),
 (@receta, 15, 2, 'u', 2, FALSE, NULL),
 (@receta, 38, 0.5, 'u', 1, FALSE, 'el jugo'),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL),
 (@receta, 41, 1, 'pizca', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Pisar la palta con el jugo de limón y sal.', NULL),
 (@receta, 2, 'Cocinar los huevos en una sartén con unas gotas de aceite, o hervirlos 7 minutos.', 300),
 (@receta, 3, 'Tostar el pan, untarlo con la palta y poner un huevo arriba. Terminar con pimienta.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 6), (@receta, 9), (@receta, 8);


-- ─────────── Bienvenida: dieta, gustos, nivel y para cuántos cocina ───────────
CREATE TABLE IF NOT EXISTS usuario_tipo (
    usuario_id   INT UNSIGNED NOT NULL,
    clase        ENUM('dieta','gusto') NOT NULL,
    etiqueta_id  SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, clase, etiqueta_id),
    CONSTRAINT fk_ut_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_ut_etiqueta FOREIGN KEY (etiqueta_id) REFERENCES etiqueta(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Va al final: si importás el archivo de nuevo, esta línea da "Duplicate column name" y no pasa nada
-- (todo lo de arriba ya quedó hecho).
ALTER TABLE usuario
    ADD COLUMN nivel_cocina TINYINT UNSIGNED NULL AFTER nota_hoy,
    ADD COLUMN comensales TINYINT UNSIGNED NULL AFTER nivel_cocina;
