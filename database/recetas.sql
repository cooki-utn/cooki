-- Recetas generadas con: php scripts/importar-recetas.php --sql
-- No editar a mano: se cambia el .json de database/recetas/ y se vuelve a generar.
-- Se carga después de seed.sql (en Docker, solo, como 03-recetas.sql).

SET NAMES utf8mb4;

-- Albóndigas con salsa
DELETE FROM receta WHERE slug = 'albondigas-con-salsa';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('albondigas-con-salsa', 'Albóndigas con salsa', 'Tiernas, en salsa de tomate.', 4, 25, 30, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 13, 500, 'g', 3, FALSE, NULL),
 (@receta, 15, 1, 'u', 2, FALSE, NULL),
 (@receta, 27, 4, 'cda', 2, FALSE, NULL),
 (@receta, 25, 30, 'g', 1, FALSE, NULL),
 (@receta, 43, 500, 'g', 3, FALSE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 29, 2, 'diente', 1, FALSE, NULL),
 (@receta, 22, 1, 'cda', 1, TRUE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Mezclar la carne, el huevo, el pan rallado, el queso, el perejil, un diente de ajo picado y sal.', NULL),
 (@receta, 2, 'Formar bolitas del tamaño de una nuez.', NULL),
 (@receta, 3, 'Dorarlas en el aceite y retirarlas.', 480),
 (@receta, 4, 'En la misma olla, rehogar la cebolla y el otro ajo, sumar el puré de tomate y volver a poner las albóndigas. Cocinar tapado a fuego bajo.', 1200);

-- Alfajores de maicena
DELETE FROM receta WHERE slug = 'alfajores-de-maicena';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('alfajores-de-maicena', 'Alfajores de maicena', 'Tiernos, con dulce de leche y coco.', 12, 40, 12, 3, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 98, 300, 'g', 3, FALSE, NULL),
 (@receta, 16, 200, 'g', 3, FALSE, NULL),
 (@receta, 10, 200, 'g', 3, FALSE, NULL),
 (@receta, 40, 150, 'g', 2, FALSE, NULL),
 (@receta, 15, 3, 'u', 2, FALSE, 'solo las yemas'),
 (@receta, 85, 2, 'cdita', 2, FALSE, NULL),
 (@receta, 96, 400, 'g', 3, FALSE, NULL),
 (@receta, 100, 50, 'g', 1, FALSE, NULL),
 (@receta, 107, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 38, 1, 'u', 1, TRUE, 'la ralladura');
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Batir la manteca blanda con el azúcar hasta que esté cremosa. Sumar las yemas, la esencia y la ralladura.', NULL),
 (@receta, 2, 'Agregar la maicena, la harina y el polvo de hornear tamizados y unir sin amasar.', NULL),
 (@receta, 3, 'Envolver la masa y dejarla en la heladera.', 1800),
 (@receta, 4, 'Estirar de medio centímetro y cortar tapitas.', NULL),
 (@receta, 5, 'Hornear a 170 °C: tienen que quedar blancas, no doradas.', 720),
 (@receta, 6, 'Ya frías, unir de a dos con dulce de leche y pasar el borde por coco rallado.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 4);

-- Arroz con leche
DELETE FROM receta WHERE slug = 'arroz-con-leche';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('arroz-con-leche', 'Arroz con leche', 'Cremoso y perfumado con limón.', 4, 5, 40, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 18, 150, 'g', 3, FALSE, NULL),
 (@receta, 24, 1000, 'ml', 3, FALSE, NULL),
 (@receta, 40, 120, 'g', 2, FALSE, NULL),
 (@receta, 38, 1, 'u', 1, FALSE, 'la cáscara'),
 (@receta, 107, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir el arroz en agua 5 minutos y escurrir.', 300),
 (@receta, 2, 'Sumarlo a la leche con la cáscara de limón y cocinar a fuego bajo, revolviendo seguido.', 1800),
 (@receta, 3, 'Agregar el azúcar y la esencia y cocinar hasta que espese.', 300),
 (@receta, 4, 'Sacar la cáscara y servir tibio o frío.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 7), (@receta, 3), (@receta, 4);

-- Arroz salteado con verduras
DELETE FROM receta WHERE slug = 'arroz-salteado-con-verduras';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('arroz-salteado-con-verduras', 'Arroz salteado con verduras', 'Ideal para usar arroz del día anterior.', 3, 15, 10, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 18, 300, 'g', 3, FALSE, 'ya cocido y frío'),
 (@receta, 28, 1, 'u', 2, FALSE, NULL),
 (@receta, 33, 100, 'g', 2, FALSE, NULL),
 (@receta, 6, 1, 'u', 1, FALSE, NULL),
 (@receta, 23, 2, 'u', 2, FALSE, NULL),
 (@receta, 15, 2, 'u', 2, FALSE, NULL),
 (@receta, 105, 30, 'ml', 2, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Picar la zanahoria chiquita y desgranar el choclo.', NULL),
 (@receta, 2, 'Saltear las verduras en el aceite bien caliente.', 300),
 (@receta, 3, 'Correr las verduras a un costado, romper los huevos y revolverlos hasta que cuajen.', NULL),
 (@receta, 4, 'Sumar {ing:arroz} y la salsa de soja y saltear todo junto.', 240);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 6), (@receta, 3), (@receta, 8);

-- Bizcochuelo de vainilla
DELETE FROM receta WHERE slug = 'bizcochuelo-de-vainilla';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('bizcochuelo-de-vainilla', 'Bizcochuelo de vainilla', 'La base para cualquier torta de cumpleaños.', 8, 20, 40, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 15, 4, 'u', 3, FALSE, NULL),
 (@receta, 40, 200, 'g', 3, FALSE, NULL),
 (@receta, 16, 200, 'g', 3, FALSE, NULL),
 (@receta, 85, 1, 'cdita', 2, FALSE, NULL),
 (@receta, 107, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 10, 10, 'g', 1, FALSE, 'para el molde');
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Enmantecar y enharinar un molde de 24 cm.', NULL),
 (@receta, 2, 'Batir los huevos con el azúcar hasta que dupliquen su volumen y queden claros.', 480),
 (@receta, 3, 'Sumar la esencia y, con movimientos envolventes, la harina tamizada con el polvo de hornear.', NULL),
 (@receta, 4, 'Hornear a 180 °C sin abrir la puerta los primeros 30 minutos.', 2400),
 (@receta, 5, 'Está listo cuando un palillo sale seco. Desmoldar frío.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 4);

-- Bondiola al horno con batatas
DELETE FROM receta WHERE slug = 'bondiola-al-horno-con-batatas';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('bondiola-al-horno-con-batatas', 'Bondiola al horno con batatas', 'Con un glaseado de mostaza y miel.', 4, 15, 70, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 94, 1000, 'g', 3, FALSE, 'bondiola en una pieza'),
 (@receta, 7, 3, 'u', 2, FALSE, NULL),
 (@receta, 83, 2, 'cda', 2, FALSE, NULL),
 (@receta, 73, 2, 'cda', 2, FALSE, NULL),
 (@receta, 29, 2, 'diente', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 41, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Mezclar la mostaza, la miel, el ajo picado, sal y pimienta. Untar la carne.', NULL),
 (@receta, 2, 'Cortar las batatas en gajos y acomodarlas alrededor con un chorrito de aceite.', NULL),
 (@receta, 3, 'Hornear a 180 °C tapado con papel aluminio.', 3000),
 (@receta, 4, 'Destapar, pintar con lo que quedó del glaseado y hornear hasta que se dore.', 1200),
 (@receta, 5, 'Dejar reposar antes de cortar.', 300);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 2), (@receta, 6), (@receta, 7);

-- Brownies
DELETE FROM receta WHERE slug = 'brownies';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('brownies', 'Brownies', 'Húmedos por dentro, con costra arriba.', 12, 15, 25, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 74, 200, 'g', 3, FALSE, NULL),
 (@receta, 10, 150, 'g', 3, FALSE, NULL),
 (@receta, 40, 200, 'g', 2, FALSE, NULL),
 (@receta, 15, 3, 'u', 2, FALSE, NULL),
 (@receta, 16, 100, 'g', 2, FALSE, NULL),
 (@receta, 101, 80, 'g', 1, TRUE, NULL),
 (@receta, 107, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Derretir el chocolate con la manteca a baño María.', 300),
 (@receta, 2, 'Batir los huevos con el azúcar y la esencia y sumar el chocolate tibio.', NULL),
 (@receta, 3, 'Agregar la harina y las nueces y mezclar apenas.', NULL),
 (@receta, 4, 'Hornear a 180 °C en un molde cuadrado: el centro tiene que quedar un poco húmedo.', 1500),
 (@receta, 5, 'Dejar enfriar antes de cortar.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 4);

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

-- Budín de pan
DELETE FROM receta WHERE slug = 'budin-de-pan';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('budin-de-pan', 'Budín de pan', 'Para aprovechar el pan duro.', 8, 15, 50, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 70, 300, 'g', 3, FALSE, 'del día anterior'),
 (@receta, 24, 750, 'ml', 3, FALSE, NULL),
 (@receta, 15, 4, 'u', 3, FALSE, NULL),
 (@receta, 40, 200, 'g', 2, FALSE, NULL),
 (@receta, 107, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 102, 50, 'g', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Remojar el pan en la leche hasta que se ablande y pisarlo.', NULL),
 (@receta, 2, 'Sumar los huevos, la mitad del azúcar, la esencia y las pasas.', NULL),
 (@receta, 3, 'Con el resto del azúcar, hacer caramelo en el molde a fuego bajo.', 360),
 (@receta, 4, 'Volcar la mezcla y hornear a baño María a 180 °C.', 3000),
 (@receta, 5, 'Dejar enfriar en la heladera antes de desmoldar.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 3), (@receta, 4);

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

-- Crema de calabaza
DELETE FROM receta WHERE slug = 'crema-de-calabaza';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('crema-de-calabaza', 'Crema de calabaza', 'Suave y anaranjada, lista en media hora.', 4, 15, 25, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 35, 800, 'g', 3, FALSE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 42, 1, 'u', 2, FALSE, NULL),
 (@receta, 1, 1, 'u', 1, FALSE, NULL),
 (@receta, 11, 100, 'ml', 1, TRUE, NULL),
 (@receta, 81, 1, 'pizca', 1, FALSE, NULL),
 (@receta, 19, 15, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Rehogar la cebolla en el aceite.', 300),
 (@receta, 2, 'Sumar {ing:calabaza} y la papa en cubos, el caldo y agua hasta cubrir. Cocinar hasta que estén tiernas.', 1200),
 (@receta, 3, 'Licuar o pisar hasta que quede lisa. Agregar la crema, la nuez moscada y la sal.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 7), (@receta, 3);

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

-- Empanadas de carne
DELETE FROM receta WHERE slug = 'empanadas-de-carne';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('empanadas-de-carne', 'Empanadas de carne', 'Jugosas, con cebolla y huevo duro. Rinden 12.', 4, 40, 20, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 71, 12, 'u', 3, FALSE, NULL),
 (@receta, 13, 500, 'g', 3, FALSE, NULL),
 (@receta, 2, 2, 'u', 2, FALSE, 'picada'),
 (@receta, 15, 2, 'u', 2, FALSE, 'duros'),
 (@receta, 104, 50, 'g', 1, TRUE, NULL),
 (@receta, 80, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 34, 1, 'cda', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir los huevos 10 minutos, enfriarlos y picarlos.', NULL),
 (@receta, 2, 'Rehogar la cebolla en el aceite hasta que esté transparente.', 480),
 (@receta, 3, 'Sumar {ing:carne-picada}, el pimentón, el comino y la sal. Cocinar hasta que pierda el color rosado.', 600),
 (@receta, 4, 'Dejar enfriar el relleno (si está caliente, rompe las tapas) y mezclar con el huevo y las aceitunas.', NULL),
 (@receta, 5, 'Rellenar cada tapa, cerrar con repulgue o apretando el borde con un tenedor.', NULL),
 (@receta, 6, 'Hornear a 200 °C hasta que estén doradas.', 1200);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 6);

-- Empanadas de jamón y queso
DELETE FROM receta WHERE slug = 'empanadas-de-jamon-y-queso';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('empanadas-de-jamon-y-queso', 'Empanadas de jamón y queso', 'Las más pedidas por los chicos, listas en media hora.', 4, 15, 15, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 71, 12, 'u', 3, FALSE, NULL),
 (@receta, 26, 200, 'g', 3, FALSE, NULL),
 (@receta, 12, 250, 'g', 3, FALSE, 'en cubos'),
 (@receta, 77, 1, 'cdita', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Cortar el jamón en tiras y el queso en cubos chicos.', NULL),
 (@receta, 2, 'Poner un poco de jamón y queso en cada tapa, espolvorear orégano y cerrar bien para que no se escape el queso.', NULL),
 (@receta, 3, 'Hornear a 200 °C en una placa aceitada.', 900);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 7), (@receta, 8);

-- Ensalada de arroz
DELETE FROM receta WHERE slug = 'ensalada-de-arroz';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('ensalada-de-arroz', 'Ensalada de arroz', 'Completa y fría, para llevar en vianda.', 3, 15, 15, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 18, 200, 'g', 3, FALSE, NULL),
 (@receta, 26, 100, 'g', 2, FALSE, NULL),
 (@receta, 6, 1, 'u', 2, FALSE, NULL),
 (@receta, 4, 2, 'u', 2, FALSE, NULL),
 (@receta, 15, 2, 'u', 2, FALSE, 'duros'),
 (@receta, 33, 80, 'g', 1, TRUE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 38, 0.5, 'u', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir el arroz, enjuagarlo con agua fría y escurrir bien.', 900),
 (@receta, 2, 'Cortar el jamón, el tomate y los huevos en cubitos y desgranar el choclo.', NULL),
 (@receta, 3, 'Mezclar todo y condimentar con aceite, limón y sal.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 2), (@receta, 6), (@receta, 8);

-- Ensalada de frutas
DELETE FROM receta WHERE slug = 'ensalada-de-frutas';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('ensalada-de-frutas', 'Ensalada de frutas', 'Con la fruta de estación que haya en casa.', 4, 15, 0, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 45, 2, 'u', 3, FALSE, NULL),
 (@receta, 39, 2, 'u', 3, FALSE, NULL),
 (@receta, 46, 3, 'u', 3, FALSE, '2 en gajos, 1 para el jugo'),
 (@receta, 9, 200, 'g', 2, TRUE, NULL),
 (@receta, 48, 2, 'u', 2, TRUE, NULL),
 (@receta, 40, 2, 'cda', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Lavar y cortar toda la fruta en cubos parecidos.', NULL),
 (@receta, 2, 'Exprimir una naranja y mezclar el jugo con la fruta (evita que la banana y la manzana se pongan negras).', NULL),
 (@receta, 3, 'Endulzar si hace falta y enfriar antes de servir.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 5), (@receta, 2), (@receta, 6), (@receta, 7), (@receta, 4), (@receta, 8);

-- Ensalada de garbanzos
DELETE FROM receta WHERE slug = 'ensalada-de-garbanzos';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('ensalada-de-garbanzos', 'Ensalada de garbanzos', 'Fresca y con proteína, sin horno.', 3, 15, 0, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 87, 250, 'g', 3, FALSE, 'cocidos'),
 (@receta, 4, 2, 'u', 2, FALSE, NULL),
 (@receta, 2, 1, 'u', 1, FALSE, 'morada si hay'),
 (@receta, 3, 1, 'u', 2, FALSE, NULL),
 (@receta, 22, 1, 'cda', 1, FALSE, NULL),
 (@receta, 38, 1, 'u', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL),
 (@receta, 80, 1, 'pizca', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Picar el tomate, la cebolla y el morrón en cubitos.', NULL),
 (@receta, 2, 'Mezclar con los garbanzos y el perejil.', NULL),
 (@receta, 3, 'Condimentar con limón, aceite, sal y comino. Dejar reposar 10 minutos antes de servir.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 5), (@receta, 2), (@receta, 6), (@receta, 7), (@receta, 3), (@receta, 8);

-- Ensalada de lentejas
DELETE FROM receta WHERE slug = 'ensalada-de-lentejas';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('ensalada-de-lentejas', 'Ensalada de lentejas', 'Tibia, con verduras crocantes.', 3, 15, 25, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 30, 250, 'g', 3, FALSE, NULL),
 (@receta, 4, 2, 'u', 2, FALSE, NULL),
 (@receta, 28, 1, 'u', 2, FALSE, 'rallada'),
 (@receta, 23, 1, 'u', 1, FALSE, NULL),
 (@receta, 15, 2, 'u', 1, TRUE, 'duros'),
 (@receta, 82, 15, 'ml', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir las lentejas en agua sin sal hasta que estén tiernas pero enteras.', 1500),
 (@receta, 2, 'Escurrir y dejar entibiar.', NULL),
 (@receta, 3, 'Mezclar con el tomate, la zanahoria y el verdeo picados, y condimentar con aceite, vinagre y sal.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 6), (@receta, 3);

-- Ensalada de repollo y zanahoria
DELETE FROM receta WHERE slug = 'ensalada-de-repollo-y-zanahoria';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('ensalada-de-repollo-y-zanahoria', 'Ensalada de repollo y zanahoria', 'Crocante, para acompañar carnes y hamburguesas.', 4, 15, 0, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 88, 0.5, 'u', 3, FALSE, NULL),
 (@receta, 28, 2, 'u', 2, FALSE, NULL),
 (@receta, 72, 3, 'cda', 2, FALSE, NULL),
 (@receta, 82, 15, 'ml', 1, FALSE, NULL),
 (@receta, 38, 0.5, 'u', 1, TRUE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL),
 (@receta, 40, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Cortar el repollo en tiras muy finas y rallar la zanahoria.', NULL),
 (@receta, 2, 'Mezclar la mayonesa con el vinagre, el azúcar, la sal y el jugo de limón.', NULL),
 (@receta, 3, 'Unir todo y dejar en la heladera 20 minutos antes de servir.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 6), (@receta, 3), (@receta, 8);

-- Ensalada rusa
DELETE FROM receta WHERE slug = 'ensalada-rusa';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('ensalada-rusa', 'Ensalada rusa', 'Infaltable en las fiestas.', 6, 15, 20, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 1, 4, 'u', 3, FALSE, NULL),
 (@receta, 28, 2, 'u', 2, FALSE, NULL),
 (@receta, 33, 150, 'g', 2, FALSE, NULL),
 (@receta, 72, 150, 'g', 2, FALSE, NULL),
 (@receta, 15, 2, 'u', 1, TRUE, 'duros'),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Pelar y cortar las papas y las zanahorias en cubos chicos, todos iguales.', NULL),
 (@receta, 2, 'Hervir las papas y zanahorias en agua con sal hasta que estén tiernas pero firmes.', 720),
 (@receta, 3, 'Sumar las arvejas los últimos minutos.', 180),
 (@receta, 4, 'Escurrir, dejar enfriar y mezclar con la mayonesa. Decorar con huevo duro.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 6), (@receta, 3);

-- Estofado de carne con papas
DELETE FROM receta WHERE slug = 'estofado-de-carne-con-papas';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('estofado-de-carne-con-papas', 'Estofado de carne con papas', 'Carne tierna en salsa, para mojar el pan.', 4, 20, 90, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 93, 700, 'g', 3, FALSE, NULL),
 (@receta, 1, 4, 'u', 3, FALSE, NULL),
 (@receta, 2, 2, 'u', 2, FALSE, NULL),
 (@receta, 28, 2, 'u', 2, FALSE, NULL),
 (@receta, 3, 1, 'u', 1, FALSE, NULL),
 (@receta, 43, 300, 'g', 2, FALSE, NULL),
 (@receta, 79, 2, 'u', 1, FALSE, NULL),
 (@receta, 77, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Dorar la carne en cubos en el aceite, de a poco para que no hierva.', 600),
 (@receta, 2, 'Agregar la cebolla, la zanahoria y el morrón picados y rehogar.', 480),
 (@receta, 3, 'Sumar el puré de tomate, el laurel, el orégano y un vaso de agua. Tapar y cocinar a fuego bajo.', 3600),
 (@receta, 4, 'Agregar {ing:papa} en cubos y cocinar hasta que estén tiernas.', 1500);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 2), (@receta, 6), (@receta, 7);

-- Fideos a la crema con champiñones
DELETE FROM receta WHERE slug = 'fideos-a-la-crema-con-champinones';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('fideos-a-la-crema-con-champinones', 'Fideos a la crema con champiñones', 'Cremosos y rendidores.', 4, 10, 15, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 17, 400, 'g', 3, FALSE, NULL),
 (@receta, 59, 250, 'g', 3, FALSE, 'fileteados'),
 (@receta, 11, 200, 'ml', 3, FALSE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 10, 30, 'g', 1, FALSE, NULL),
 (@receta, 25, 40, 'g', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL),
 (@receta, 41, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Poner a hervir los fideos.', NULL),
 (@receta, 2, 'Saltear la cebolla y los champiñones en la manteca hasta que se doren.', 480),
 (@receta, 3, 'Sumar la crema, sal y pimienta y dejar reducir un poco.', 240),
 (@receta, 4, 'Mezclar con los fideos escurridos y el queso.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 7), (@receta, 8);

-- Fideos con brócoli y ajo
DELETE FROM receta WHERE slug = 'fideos-con-brocoli-y-ajo';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('fideos-con-brocoli-y-ajo', 'Fideos con brócoli y ajo', 'Verde, rápido y con lo que hay.', 4, 10, 15, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 17, 400, 'g', 3, FALSE, NULL),
 (@receta, 56, 1, 'u', 3, FALSE, NULL),
 (@receta, 29, 3, 'diente', 2, FALSE, NULL),
 (@receta, 19, 50, 'ml', 2, FALSE, NULL),
 (@receta, 25, 50, 'g', 1, TRUE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Separar el brócoli en ramitos chicos.', NULL),
 (@receta, 2, 'Hervir los fideos y, 4 minutos antes de que estén, sumar el brócoli a la misma olla.', 720),
 (@receta, 3, 'Mientras, dorar apenas el ajo en láminas en el aceite (si se quema, amarga).', 120),
 (@receta, 4, 'Escurrir, mezclar con el aceite de ajo y servir con queso.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 7), (@receta, 3), (@receta, 8);

-- Fideos con tuco
DELETE FROM receta WHERE slug = 'fideos-con-tuco';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('fideos-con-tuco', 'Fideos con tuco', 'La salsa de tomate de siempre, con zanahoria para que no quede ácida.', 4, 10, 30, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 17, 400, 'g', 3, FALSE, NULL),
 (@receta, 43, 500, 'g', 3, FALSE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 29, 2, 'diente', 1, FALSE, NULL),
 (@receta, 28, 1, 'u', 1, FALSE, 'rallada'),
 (@receta, 79, 1, 'u', 1, FALSE, NULL),
 (@receta, 77, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 25, 50, 'g', 1, TRUE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Rehogar la cebolla, el ajo y la zanahoria en el aceite.', 480),
 (@receta, 2, 'Agregar el puré de tomate, el laurel, el orégano y la sal. Cocinar a fuego bajo tapado.', 1200),
 (@receta, 3, 'Hervir {ing:fideos} en abundante agua con sal el tiempo que indique el paquete.', NULL),
 (@receta, 4, 'Servir con el tuco y queso rallado.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 7), (@receta, 3);

-- Flan casero
DELETE FROM receta WHERE slug = 'flan-casero';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('flan-casero', 'Flan casero', 'Con caramelo, para comer con dulce de leche.', 6, 15, 50, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 15, 5, 'u', 3, FALSE, NULL),
 (@receta, 24, 750, 'ml', 3, FALSE, NULL),
 (@receta, 40, 200, 'g', 3, FALSE, NULL),
 (@receta, 107, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 96, 200, 'g', 1, TRUE, 'para servir');
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hacer un caramelo con 100 g de azúcar en la flanera, a fuego bajo.', 360),
 (@receta, 2, 'Batir los huevos con el resto del azúcar y la esencia, sin hacer espuma. Sumar la leche tibia.', NULL),
 (@receta, 3, 'Volcar en la flanera y cocinar a baño María en el horno a 170 °C.', 3000),
 (@receta, 4, 'Enfriar en la heladera al menos 4 horas y desmoldar.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 4);

-- Galletitas de avena
DELETE FROM receta WHERE slug = 'galletitas-de-avena';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('galletitas-de-avena', 'Galletitas de avena', 'Para la merienda, con pasas o nueces.', 6, 15, 15, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 86, 150, 'g', 3, FALSE, NULL),
 (@receta, 16, 100, 'g', 2, FALSE, NULL),
 (@receta, 10, 100, 'g', 2, FALSE, NULL),
 (@receta, 40, 100, 'g', 2, FALSE, NULL),
 (@receta, 15, 1, 'u', 2, FALSE, NULL),
 (@receta, 85, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 102, 50, 'g', 1, TRUE, NULL),
 (@receta, 101, 50, 'g', 1, TRUE, 'picadas');
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Batir la manteca blanda con el azúcar y sumar el huevo.', NULL),
 (@receta, 2, 'Agregar la avena, la harina, el polvo de hornear y las pasas o nueces.', NULL),
 (@receta, 3, 'Formar bolitas, ponerlas separadas en una placa y aplastarlas un poco.', NULL),
 (@receta, 4, 'Hornear a 180 °C: salen blandas y se endurecen al enfriar.', 900);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 4), (@receta, 8);

-- Guiso de arroz
DELETE FROM receta WHERE slug = 'guiso-de-arroz';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('guiso-de-arroz', 'Guiso de arroz', 'Con carne y verduras, en una sola olla.', 4, 15, 35, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 18, 300, 'g', 3, FALSE, NULL),
 (@receta, 93, 400, 'g', 3, FALSE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 3, 1, 'u', 2, FALSE, NULL),
 (@receta, 28, 1, 'u', 1, FALSE, NULL),
 (@receta, 43, 200, 'g', 2, FALSE, NULL),
 (@receta, 42, 1, 'u', 2, FALSE, NULL),
 (@receta, 34, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 79, 1, 'u', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Dorar la carne en cubos en el aceite.', 480),
 (@receta, 2, 'Sumar la cebolla, el morrón y la zanahoria picados y rehogar.', 360),
 (@receta, 3, 'Agregar el puré de tomate, el pimentón, el laurel y el caldo disuelto en 1 litro de agua caliente.', NULL),
 (@receta, 4, 'Cuando hierva, sumar {ing:arroz} y cocinar a fuego bajo, revolviendo de vez en cuando.', 1080),
 (@receta, 5, 'Apagar y dejar reposar 5 minutos tapado.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 2), (@receta, 6), (@receta, 7), (@receta, 3);

-- Guiso de garbanzos
DELETE FROM receta WHERE slug = 'guiso-de-garbanzos';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('guiso-de-garbanzos', 'Guiso de garbanzos', 'Con chorizo colorado y pimentón, para el invierno.', 4, 15, 40, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 87, 300, 'g', 3, FALSE, 'remojados de la noche anterior'),
 (@receta, 67, 2, 'u', 2, FALSE, NULL),
 (@receta, 92, 100, 'g', 1, TRUE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 3, 1, 'u', 2, FALSE, NULL),
 (@receta, 43, 200, 'g', 2, FALSE, NULL),
 (@receta, 34, 1, 'cda', 1, FALSE, NULL),
 (@receta, 79, 2, 'u', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir los garbanzos remojados en agua sin sal hasta que estén tiernos.', 2400),
 (@receta, 2, 'Dorar el chorizo en rodajas y la panceta en el aceite.', 300),
 (@receta, 3, 'Agregar la cebolla y el morrón picados y rehogar.', 480),
 (@receta, 4, 'Sumar el pimentón, el puré de tomate, el laurel y un cucharón del agua de los garbanzos.', NULL),
 (@receta, 5, 'Incorporar los garbanzos escurridos y cocinar a fuego bajo para que tome sabor.', 900);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 2), (@receta, 6), (@receta, 7);

-- Hamburguesas caseras
DELETE FROM receta WHERE slug = 'hamburguesas-caseras';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('hamburguesas-caseras', 'Hamburguesas caseras', 'Sin conservantes, se pueden freezar crudas.', 4, 15, 10, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 13, 600, 'g', 3, FALSE, NULL),
 (@receta, 15, 1, 'u', 2, FALSE, NULL),
 (@receta, 27, 3, 'cda', 2, FALSE, NULL),
 (@receta, 2, 1, 'u', 1, FALSE, 'rallada'),
 (@receta, 29, 1, 'diente', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 70, 4, 'u', 1, TRUE, 'de hamburguesa'),
 (@receta, 37, 1, 'u', 1, TRUE, NULL),
 (@receta, 4, 1, 'u', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Mezclar la carne con el huevo, el pan rallado, la cebolla, el ajo y la sal, sin amasar de más.', NULL),
 (@receta, 2, 'Formar 4 bollos, aplastarlos y hacerles un hueco en el centro con el dedo (así no se inflan).', NULL),
 (@receta, 3, 'Cocinar en plancha o sartén bien caliente, 4 o 5 minutos de cada lado.', 600),
 (@receta, 4, 'Armar con pan, lechuga y tomate.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 6), (@receta, 8);

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

-- Licuado de banana
DELETE FROM receta WHERE slug = 'licuado-de-banana';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('licuado-de-banana', 'Licuado de banana', 'Desayuno en dos minutos.', 2, 5, 0, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 39, 2, 'u', 3, FALSE, NULL),
 (@receta, 24, 400, 'ml', 3, FALSE, NULL),
 (@receta, 73, 1, 'cda', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Pelar las bananas y cortarlas en trozos.', NULL),
 (@receta, 2, 'Licuar con la leche fría y la miel hasta que quede espumoso.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 7), (@receta, 3), (@receta, 4), (@receta, 8);

-- Locro sencillo
DELETE FROM receta WHERE slug = 'locro-sencillo';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('locro-sencillo', 'Locro sencillo', 'Una versión de una olla, sin tantas carnes, para los feriados patrios.', 8, 30, 120, 3, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 6, 3, 'u', 3, FALSE, 'desgranado'),
 (@receta, 35, 800, 'g', 3, FALSE, NULL),
 (@receta, 21, 250, 'g', 3, FALSE, 'remojados'),
 (@receta, 93, 500, 'g', 2, FALSE, NULL),
 (@receta, 67, 2, 'u', 2, FALSE, NULL),
 (@receta, 92, 150, 'g', 1, FALSE, NULL),
 (@receta, 2, 2, 'u', 2, FALSE, NULL),
 (@receta, 23, 2, 'u', 1, FALSE, 'para la salsita'),
 (@receta, 34, 2, 'cda', 1, FALSE, NULL),
 (@receta, 80, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 50, 'ml', 1, FALSE, NULL),
 (@receta, 20, 2, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Dejar los porotos en remojo la noche anterior.', NULL),
 (@receta, 2, 'Dorar la carne, el chorizo y la panceta cortados en trozos en una olla grande.', 600),
 (@receta, 3, 'Sumar la cebolla picada, los porotos, el choclo y agua hasta cubrir. Cocinar a fuego bajo.', 3600),
 (@receta, 4, 'Agregar la calabaza en cubos y seguir cocinando, revolviendo seguido, hasta que se deshaga.', 3000),
 (@receta, 5, 'Para la salsita: calentar el aceite con el pimentón, el comino y el verdeo picado. Servir el locro con una cucharada encima.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 2), (@receta, 6), (@receta, 7);

-- Merluza al horno con papas
DELETE FROM receta WHERE slug = 'merluza-al-horno-con-papas';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('merluza-al-horno-con-papas', 'Merluza al horno con papas', 'Pescado sin olor en la cocina.', 4, 15, 35, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 68, 4, 'u', 3, FALSE, 'filetes'),
 (@receta, 1, 4, 'u', 3, FALSE, NULL),
 (@receta, 38, 1, 'u', 2, FALSE, NULL),
 (@receta, 29, 2, 'diente', 1, FALSE, NULL),
 (@receta, 22, 1, 'cda', 1, FALSE, NULL),
 (@receta, 19, 40, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Cortar las papas en rodajas finas y hornearlas con aceite y sal a 200 °C.', 1200),
 (@receta, 2, 'Acomodar los filetes encima, con limón, ajo y perejil picados.', NULL),
 (@receta, 3, 'Seguir horneando hasta que el pescado se separe en láminas.', 900);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 2), (@receta, 6), (@receta, 7);

-- Milanesas de berenjena
DELETE FROM receta WHERE slug = 'milanesas-de-berenjena';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('milanesas-de-berenjena', 'Milanesas de berenjena', 'Crocantes, al horno o fritas.', 4, 20, 20, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 55, 2, 'u', 3, FALSE, NULL),
 (@receta, 15, 2, 'u', 2, FALSE, NULL),
 (@receta, 27, 200, 'g', 3, FALSE, NULL),
 (@receta, 29, 1, 'diente', 1, FALSE, NULL),
 (@receta, 22, 1, 'cda', 1, TRUE, NULL),
 (@receta, 19, 50, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Cortar las berenjenas en rodajas de medio centímetro, salarlas y dejarlas 15 minutos para que larguen el agua amarga. Secarlas.', NULL),
 (@receta, 2, 'Batir los huevos con ajo, perejil y sal.', NULL),
 (@receta, 3, 'Pasar cada rodaja por huevo y después por pan rallado.', NULL),
 (@receta, 4, 'Hornear a 200 °C en una placa aceitada, dando vuelta a la mitad.', 1200);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 6), (@receta, 3);

-- Muffins de banana
DELETE FROM receta WHERE slug = 'muffins-de-banana';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('muffins-de-banana', 'Muffins de banana', 'Usan las bananas demasiado maduras.', 12, 15, 25, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 39, 3, 'u', 3, FALSE, 'bien maduras'),
 (@receta, 16, 250, 'g', 3, FALSE, NULL),
 (@receta, 15, 2, 'u', 2, FALSE, NULL),
 (@receta, 40, 120, 'g', 2, FALSE, NULL),
 (@receta, 19, 80, 'ml', 2, FALSE, NULL),
 (@receta, 85, 2, 'cdita', 2, FALSE, NULL),
 (@receta, 101, 50, 'g', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Pisar las bananas con un tenedor.', NULL),
 (@receta, 2, 'Mezclar con los huevos, el azúcar y el aceite.', NULL),
 (@receta, 3, 'Sumar la harina con el polvo de hornear y las nueces, sin batir de más.', NULL),
 (@receta, 4, 'Repartir en pirotines y hornear a 180 °C.', 1500);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 6), (@receta, 4);

-- Ñoquis de papa
DELETE FROM receta WHERE slug = 'noquis-de-papa';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('noquis-de-papa', 'Ñoquis de papa', 'Para el 29, o cualquier día.', 4, 40, 10, 3, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 1, 1000, 'g', 3, FALSE, NULL),
 (@receta, 16, 300, 'g', 3, FALSE, NULL),
 (@receta, 15, 1, 'u', 2, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 81, 1, 'pizca', 1, FALSE, NULL),
 (@receta, 10, 50, 'g', 1, FALSE, 'para servir'),
 (@receta, 25, 50, 'g', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir las papas con cáscara hasta que estén tiernas.', 1500),
 (@receta, 2, 'Pelarlas en caliente y hacer un puré sin grumos.', NULL),
 (@receta, 3, 'Mezclar el puré tibio con el huevo, la sal, la nuez moscada y la harina de a poco, hasta que no se pegue.', NULL),
 (@receta, 4, 'Hacer cilindros, cortarlos en trocitos y marcarlos con un tenedor.', NULL),
 (@receta, 5, 'Cocinarlos en agua hirviendo con sal: están listos cuando suben a la superficie.', 180),
 (@receta, 6, 'Servir con manteca derretida y queso, o con la salsa que prefieras.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 3);

-- Pan casero
DELETE FROM receta WHERE slug = 'pan-casero';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('pan-casero', 'Pan casero', 'Una hogaza simple, con corteza crocante.', 8, 20, 35, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 16, 500, 'g', 3, FALSE, NULL),
 (@receta, 84, 15, 'g', 3, FALSE, NULL),
 (@receta, 20, 2, 'cdita', 1, FALSE, NULL),
 (@receta, 40, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Disolver la levadura y el azúcar en 300 ml de agua tibia.', NULL),
 (@receta, 2, 'Sumar la harina, la sal y el aceite y amasar 10 minutos.', NULL),
 (@receta, 3, 'Dejar levar tapado hasta que duplique.', 3600),
 (@receta, 4, 'Formar el pan, hacerle unos cortes arriba y dejarlo levar de nuevo.', 1200),
 (@receta, 5, 'Hornear a 200 °C con un recipiente con agua en el horno (así queda crocante).', 2100);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 5), (@receta, 6), (@receta, 7), (@receta, 3);

-- Pastafrola
DELETE FROM receta WHERE slug = 'pastafrola';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('pastafrola', 'Pastafrola', 'Con dulce de membrillo y enrejado.', 8, 30, 40, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 16, 300, 'g', 3, FALSE, NULL),
 (@receta, 10, 150, 'g', 3, FALSE, NULL),
 (@receta, 40, 120, 'g', 2, FALSE, NULL),
 (@receta, 15, 2, 'u', 2, FALSE, NULL),
 (@receta, 85, 1, 'cdita', 2, FALSE, NULL),
 (@receta, 97, 400, 'g', 3, FALSE, NULL),
 (@receta, 38, 1, 'u', 1, TRUE, 'la ralladura');
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Batir la manteca con el azúcar, sumar los huevos y la ralladura.', NULL),
 (@receta, 2, 'Agregar la harina con el polvo de hornear y unir sin amasar. Reservar un tercio para el enrejado.', NULL),
 (@receta, 3, 'Ablandar el dulce de membrillo con un poco de agua caliente, pisándolo con un tenedor.', NULL),
 (@receta, 4, 'Forrar un molde con la masa, cubrir con el dulce y armar las tiras cruzadas encima.', NULL),
 (@receta, 5, 'Hornear a 180 °C hasta que la masa esté dorada.', 2400);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 4);

-- Pizza casera
DELETE FROM receta WHERE slug = 'pizza-casera';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('pizza-casera', 'Pizza casera', 'Masa de un leudado, salsa y mozzarella.', 4, 20, 20, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 16, 500, 'g', 3, FALSE, NULL),
 (@receta, 84, 25, 'g', 3, FALSE, 'fresca'),
 (@receta, 43, 200, 'g', 2, FALSE, NULL),
 (@receta, 65, 400, 'g', 3, FALSE, NULL),
 (@receta, 77, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 40, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 104, 50, 'g', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Disolver la levadura y el azúcar en 300 ml de agua tibia.', NULL),
 (@receta, 2, 'Mezclar con la harina, la sal y el aceite. Amasar hasta que quede lisa.', NULL),
 (@receta, 3, 'Dejar levar tapada en un lugar tibio, hasta que duplique su tamaño.', 2400),
 (@receta, 4, 'Estirar en dos pizzeras aceitadas y cubrir con el puré de tomate condimentado con orégano.', NULL),
 (@receta, 5, 'Hornear la prepizza a 220 °C.', 600),
 (@receta, 6, 'Sumar la mozzarella y las aceitunas y hornear hasta que se derrita.', 480);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 7);

-- Polenta con salsa y queso
DELETE FROM receta WHERE slug = 'polenta-con-salsa-y-queso';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('polenta-con-salsa-y-queso', 'Polenta con salsa y queso', 'Polenta firme gratinada con tuco.', 4, 10, 25, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 31, 250, 'g', 3, FALSE, NULL),
 (@receta, 24, 500, 'ml', 2, FALSE, NULL),
 (@receta, 43, 400, 'g', 2, FALSE, NULL),
 (@receta, 12, 200, 'g', 2, FALSE, NULL),
 (@receta, 2, 1, 'u', 1, FALSE, NULL),
 (@receta, 77, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 15, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hacer una salsa rápida: rehogar la cebolla, sumar el puré de tomate, orégano y sal.', 600),
 (@receta, 2, 'Hervir la leche con 500 ml de agua y sal, y echar la polenta en lluvia revolviendo.', 300),
 (@receta, 3, 'Volcar la polenta en una fuente, cubrir con la salsa y el queso en fetas.', NULL),
 (@receta, 4, 'Gratinar en el horno a 200 °C.', 600);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 7), (@receta, 3);

-- Pollo a la mostaza
DELETE FROM receta WHERE slug = 'pollo-a-la-mostaza';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('pollo-a-la-mostaza', 'Pollo a la mostaza', 'Cremoso, va bien con arroz o puré.', 4, 10, 25, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 14, 3, 'u', 3, FALSE, 'pechugas en tiras'),
 (@receta, 83, 2, 'cda', 3, FALSE, NULL),
 (@receta, 11, 200, 'ml', 2, FALSE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 19, 15, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Dorar el pollo en el aceite y retirarlo.', 480),
 (@receta, 2, 'En la misma sartén, rehogar la cebolla en pluma.', 360),
 (@receta, 3, 'Volver a poner el pollo, sumar {ing:crema-de-leche} y la mostaza. Cocinar a fuego bajo hasta que espese.', 600);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 2), (@receta, 7);

-- Pollo al horno con papas
DELETE FROM receta WHERE slug = 'pollo-al-horno-con-papas';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('pollo-al-horno-con-papas', 'Pollo al horno con papas', 'El clásico del domingo, con limón y ajo.', 4, 15, 60, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 14, 4, 'u', 3, FALSE, 'presas'),
 (@receta, 1, 5, 'u', 3, FALSE, NULL),
 (@receta, 38, 1, 'u', 2, FALSE, NULL),
 (@receta, 29, 3, 'diente', 1, FALSE, NULL),
 (@receta, 77, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 50, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Condimentar el pollo con el jugo de limón, el ajo picado, orégano, sal y aceite.', NULL),
 (@receta, 2, 'Cortar las papas en cubos grandes y repartirlas en la fuente junto con el pollo.', NULL),
 (@receta, 3, 'Hornear a 200 °C, dando vuelta todo a la mitad.', 3600);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 2), (@receta, 6), (@receta, 7), (@receta, 3);

-- Postre vigilante
DELETE FROM receta WHERE slug = 'postre-vigilante';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('postre-vigilante', 'Postre vigilante', 'Queso y dulce: el postre más rápido de la casa.', 2, 5, 0, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 12, 150, 'g', 3, FALSE, NULL),
 (@receta, 97, 150, 'g', 3, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Cortar el queso y el dulce en porciones del mismo tamaño.', NULL),
 (@receta, 2, 'Servir una sobre la otra.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 7), (@receta, 4), (@receta, 8);

-- Puré de papas
DELETE FROM receta WHERE slug = 'pure-de-papas';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('pure-de-papas', 'Puré de papas', 'El acompañamiento que combina con todo.', 4, 10, 25, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 1, 1000, 'g', 3, FALSE, NULL),
 (@receta, 24, 150, 'ml', 2, FALSE, NULL),
 (@receta, 10, 40, 'g', 2, FALSE, NULL),
 (@receta, 81, 1, 'pizca', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir las papas peladas en cubos en agua con sal hasta que estén bien tiernas.', 1200),
 (@receta, 2, 'Escurrir y pisar en caliente.', NULL),
 (@receta, 3, 'Sumar la manteca y la leche caliente de a poco, batiendo. Condimentar con nuez moscada.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 7), (@receta, 3);

-- Revuelto de zapallitos
DELETE FROM receta WHERE slug = 'revuelto-de-zapallitos';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('revuelto-de-zapallitos', 'Revuelto de zapallitos', 'Cena liviana en 20 minutos.', 2, 10, 12, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 5, 3, 'u', 3, FALSE, NULL),
 (@receta, 15, 3, 'u', 3, FALSE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 25, 30, 'g', 1, TRUE, NULL),
 (@receta, 19, 15, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Cortar los zapallitos en cubos y la cebolla en pluma.', NULL),
 (@receta, 2, 'Rehogar la cebolla y los zapallitos en el aceite hasta que estén tiernos.', 600),
 (@receta, 3, 'Sumar los huevos batidos con sal y revolver hasta que cuajen. Servir con queso.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 3), (@receta, 8);

-- Risotto de calabaza
DELETE FROM receta WHERE slug = 'risotto-de-calabaza';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('risotto-de-calabaza', 'Risotto de calabaza', 'Cremoso sin crema: el secreto es revolver.', 4, 15, 30, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 18, 300, 'g', 3, FALSE, NULL),
 (@receta, 35, 400, 'g', 3, FALSE, 'en cubos chicos'),
 (@receta, 42, 2, 'u', 2, FALSE, 'disueltos en 1 litro de agua caliente'),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 25, 60, 'g', 2, FALSE, NULL),
 (@receta, 10, 30, 'g', 1, FALSE, NULL),
 (@receta, 19, 15, 'ml', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Rehogar la cebolla picada en el aceite.', 300),
 (@receta, 2, 'Sumar el arroz y la calabaza y nacarar (que el arroz se vea transparente en los bordes).', 120),
 (@receta, 3, 'Agregar el caldo caliente de a un cucharón, revolviendo y esperando que se absorba antes del siguiente.', 1200),
 (@receta, 4, 'Apagar el fuego, sumar la manteca y el queso, tapar 2 minutos y servir.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 7);

-- Salteado de pollo con verduras
DELETE FROM receta WHERE slug = 'salteado-de-pollo-con-verduras';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('salteado-de-pollo-con-verduras', 'Salteado de pollo con verduras', 'Al wok, con salsa de soja y jengibre.', 3, 20, 15, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 14, 2, 'u', 3, FALSE, 'pechugas en tiras'),
 (@receta, 3, 1, 'u', 2, FALSE, NULL),
 (@receta, 28, 1, 'u', 2, FALSE, 'en bastones'),
 (@receta, 23, 2, 'u', 2, FALSE, NULL),
 (@receta, 105, 50, 'ml', 2, FALSE, NULL),
 (@receta, 106, 10, 'g', 1, TRUE, 'rallado'),
 (@receta, 18, 250, 'g', 2, FALSE, 'para acompañar'),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Hervir el arroz.', 900),
 (@receta, 2, 'Cortar todas las verduras antes de prender el fuego: el salteado es rápido.', NULL),
 (@receta, 3, 'Saltear el pollo en el aceite bien caliente hasta dorar.', 300),
 (@receta, 4, 'Sumar las verduras y el jengibre y saltear sin dejar de mover.', 240),
 (@receta, 5, 'Agregar la salsa de soja, mezclar y servir sobre el arroz.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 6), (@receta, 7);

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

-- Sopa de puerro y papa
DELETE FROM receta WHERE slug = 'sopa-de-puerro-y-papa';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('sopa-de-puerro-y-papa', 'Sopa de puerro y papa', 'Reconfortante y muy económica.', 4, 15, 30, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 90, 2, 'u', 3, FALSE, NULL),
 (@receta, 1, 3, 'u', 3, FALSE, NULL),
 (@receta, 2, 1, 'u', 1, FALSE, NULL),
 (@receta, 42, 1, 'u', 2, FALSE, NULL),
 (@receta, 10, 20, 'g', 1, FALSE, NULL),
 (@receta, 24, 200, 'ml', 1, TRUE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Lavar bien los puerros (la tierra se esconde entre las hojas) y cortarlos en rodajas.', NULL),
 (@receta, 2, 'Rehogar el puerro y la cebolla en la manteca.', 480),
 (@receta, 3, 'Sumar las papas en cubos, el caldo y 1 litro de agua. Cocinar hasta que la papa se deshaga.', 1500),
 (@receta, 4, 'Licuar, sumar la leche si se quiere más cremosa y ajustar la sal.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2), (@receta, 7), (@receta, 3);

-- Tarta de acelga
DELETE FROM receta WHERE slug = 'tarta-de-acelga';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('tarta-de-acelga', 'Tarta de acelga', 'Verde, suave y con mucho queso.', 6, 25, 35, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 32, 2, 'u', 3, FALSE, NULL),
 (@receta, 89, 1, 'u', 3, FALSE, 'un atado'),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 15, 3, 'u', 2, FALSE, NULL),
 (@receta, 25, 80, 'g', 2, FALSE, NULL),
 (@receta, 81, 1, 'pizca', 1, FALSE, NULL),
 (@receta, 20, 1, 'cdita', 1, FALSE, NULL),
 (@receta, 19, 15, 'ml', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Lavar la acelga, separar las hojas de las pencas y cortarlas en tiras.', NULL),
 (@receta, 2, 'Hervir la acelga 3 minutos, escurrir muy bien y picar.', 180),
 (@receta, 3, 'Rehogar la cebolla picada en el aceite.', 360),
 (@receta, 4, 'Mezclar la acelga, la cebolla, los huevos batidos, el queso, la sal y la nuez moscada.', NULL),
 (@receta, 5, 'Forrar un molde con una tapa, volcar el relleno y cubrir con la otra. Pinchar la tapa de arriba.', NULL),
 (@receta, 6, 'Hornear a 180 °C hasta que esté dorada.', 2100);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1);

-- Tarta de jamón y queso
DELETE FROM receta WHERE slug = 'tarta-de-jamon-y-queso';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('tarta-de-jamon-y-queso', 'Tarta de jamón y queso', 'Rápida, sin cocinar el relleno antes.', 6, 15, 30, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 32, 2, 'u', 3, FALSE, NULL),
 (@receta, 26, 200, 'g', 3, FALSE, NULL),
 (@receta, 12, 300, 'g', 3, FALSE, NULL),
 (@receta, 15, 3, 'u', 2, FALSE, NULL),
 (@receta, 24, 100, 'ml', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Batir los huevos con la leche.', NULL),
 (@receta, 2, 'Forrar el molde con una tapa, acomodar el jamón y el queso en capas y volcar el batido.', NULL),
 (@receta, 3, 'Tapar con la otra masa, cerrar los bordes y hacer un corte en el centro.', NULL),
 (@receta, 4, 'Hornear a 180 °C.', 1800);

-- Torta de chocolate
DELETE FROM receta WHERE slug = 'torta-de-chocolate';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('torta-de-chocolate', 'Torta de chocolate', 'Húmeda, de una sola mezcla.', 10, 15, 40, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 16, 250, 'g', 3, FALSE, NULL),
 (@receta, 99, 50, 'g', 3, FALSE, NULL),
 (@receta, 40, 250, 'g', 3, FALSE, NULL),
 (@receta, 15, 3, 'u', 2, FALSE, NULL),
 (@receta, 24, 250, 'ml', 2, FALSE, NULL),
 (@receta, 19, 120, 'ml', 2, FALSE, NULL),
 (@receta, 85, 2, 'cdita', 2, FALSE, NULL),
 (@receta, 107, 1, 'cdita', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Mezclar los secos: harina, cacao, azúcar y polvo de hornear.', NULL),
 (@receta, 2, 'Sumar los huevos, la leche, el aceite y la esencia, y batir hasta que no queden grumos.', NULL),
 (@receta, 3, 'Volcar en un molde aceitado y hornear a 180 °C.', 2400);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 4);

-- Torta de galletitas con dulce de leche
DELETE FROM receta WHERE slug = 'torta-de-galletitas-con-dulce-de-leche';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('torta-de-galletitas-con-dulce-de-leche', 'Torta de galletitas con dulce de leche', 'Sin horno: se arma en capas y se enfría.', 8, 25, 0, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 103, 400, 'g', 3, FALSE, 'de chocolate'),
 (@receta, 96, 400, 'g', 3, FALSE, NULL),
 (@receta, 95, 300, 'g', 3, FALSE, NULL),
 (@receta, 76, 1, 'taza', 2, FALSE, 'preparado, frío'),
 (@receta, 99, 1, 'cda', 1, TRUE, 'para decorar');
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Mezclar el dulce de leche con el queso crema hasta que quede liso.', NULL),
 (@receta, 2, 'Mojar las galletitas en el café, de a una y rápido, y armar una capa en una fuente.', NULL),
 (@receta, 3, 'Cubrir con crema y repetir las capas, terminando con crema.', NULL),
 (@receta, 4, 'Espolvorear cacao y dejar en la heladera al menos 4 horas.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 7), (@receta, 4), (@receta, 8);

-- Tortilla de espinaca
DELETE FROM receta WHERE slug = 'tortilla-de-espinaca';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('tortilla-de-espinaca', 'Tortilla de espinaca', 'Verde y alta, se come fría o caliente.', 4, 15, 20, 2, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 57, 300, 'g', 3, FALSE, NULL),
 (@receta, 15, 5, 'u', 3, FALSE, NULL),
 (@receta, 2, 1, 'u', 2, FALSE, NULL),
 (@receta, 25, 40, 'g', 2, FALSE, NULL),
 (@receta, 19, 30, 'ml', 1, FALSE, NULL),
 (@receta, 20, 1, 'pizca', 1, FALSE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Lavar la espinaca y picarla.', NULL),
 (@receta, 2, 'Rehogar la cebolla, sumar la espinaca y cocinar hasta que se achique.', 300),
 (@receta, 3, 'Mezclar con los huevos batidos, el queso y la sal.', NULL),
 (@receta, 4, 'Cocinar en sartén a fuego bajo, tapada.', 480),
 (@receta, 5, 'Dar vuelta con un plato y terminar del otro lado.', 300);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 2);

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

-- Tostadas francesas
DELETE FROM receta WHERE slug = 'tostadas-francesas';
INSERT INTO receta (slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 ('tostadas-francesas', 'Tostadas francesas', 'Pan mojado en huevo y leche, dorado en manteca.', 2, 5, 10, 1, 'Receta del equipo', (SELECT id FROM usuario WHERE rol = 'admin' ORDER BY id LIMIT 1));
SET @receta = LAST_INSERT_ID();
INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (@receta, 70, 4, 'u', 3, FALSE, 'rodajas'),
 (@receta, 15, 2, 'u', 3, FALSE, NULL),
 (@receta, 24, 100, 'ml', 2, FALSE, NULL),
 (@receta, 40, 1, 'cda', 1, FALSE, NULL),
 (@receta, 107, 1, 'cdita', 1, TRUE, NULL),
 (@receta, 10, 20, 'g', 1, FALSE, NULL),
 (@receta, 73, 1, 'cda', 1, TRUE, NULL);
INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (@receta, 1, 'Batir los huevos con la leche, el azúcar y la esencia.', NULL),
 (@receta, 2, 'Pasar las rodajas de pan por la mezcla de los dos lados.', NULL),
 (@receta, 3, 'Dorarlas en la manteca a fuego medio, unos 2 minutos de cada lado.', 300),
 (@receta, 4, 'Servir con miel.', NULL);
INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES (@receta, 1), (@receta, 3), (@receta, 4), (@receta, 8);

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
