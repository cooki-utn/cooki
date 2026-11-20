-- =====================================================================
--  Cooki · Datos de ejemplo del incremento 1
--  mysql -u root -p cooki < database/seed.sql
--
--  Usuarios de prueba (las contraseñas se guardan con password_hash):
--    Administradores: admin@cooki.test  y  docente@cooki.test  → contraseña Admin1234
--    Generales:       maria@cooki.test, juan@cooki.test, sofia@cooki.test → Usuario1234
-- =====================================================================
SET NAMES utf8mb4;
-- Las fechas de ejemplo (CURDATE, NOW) con la hora de Argentina, como la aplicación (DEC-023). Sin esto, si se
-- importa entre las 21 y las 24 en un servidor en UTC, los vencimientos quedan corridos un día.
SET time_zone = '-03:00';

INSERT INTO usuario (id, email, nombre, hash_contrasena, rol) VALUES
 (1, 'admin@cooki.test',   'Administración', '$2y$10$2v72GYk760OoscGKNBR56u.cbCnpEm0kQIuLzRlN249jILhZF.Zey', 'admin'),
 (2, 'docente@cooki.test', 'Docente',        '$2y$10$2v72GYk760OoscGKNBR56u.cbCnpEm0kQIuLzRlN249jILhZF.Zey', 'admin'),
 (3, 'maria@cooki.test',   'María López',    '$2y$10$G0w1Jd6TRO4i77rN6TvymOrK75s7QTdBjPuYNn4wQcnLmXRFpHJNK',  'general'),
 (4, 'juan@cooki.test',    'Juan Pérez',     '$2y$10$G0w1Jd6TRO4i77rN6TvymOrK75s7QTdBjPuYNn4wQcnLmXRFpHJNK',  'general'),
 (5, 'sofia@cooki.test',   'Sofía Gómez',    '$2y$10$G0w1Jd6TRO4i77rN6TvymOrK75s7QTdBjPuYNn4wQcnLmXRFpHJNK',  'general');

INSERT INTO categoria (id, nombre, color_hex, orden) VALUES
 (1, 'Verduras', '#2E7D32', 1), (2, 'Frutas', '#C62828', 2), (3, 'Lácteos', '#1565C0', 3),
 (4, 'Carnes', '#AD1457', 4), (5, 'Huevos', '#946B00', 5), (6, 'Almacén', '#6D4C41', 6),
 (7, 'Básicos', '#546E7A', 7), (8, 'Legumbres', '#7B5E2A', 8);

INSERT INTO ingrediente (id, nombre, categoria_id, unidad_default, gramos_por_unidad, es_basico, perecedero, vida_util_dias) VALUES
 (1, 'Papa', 1, 'u', 150, FALSE, TRUE, 21),      (2, 'Cebolla', 1, 'u', 120, FALSE, TRUE, 30),
 (3, 'Morrón', 1, 'u', 150, FALSE, TRUE, 10),    (4, 'Tomate', 1, 'u', 120, FALSE, TRUE, 7),
 (5, 'Zapallito', 1, 'u', 180, FALSE, TRUE, 7),  (6, 'Choclo', 1, 'u', 250, FALSE, TRUE, 7),
 (7, 'Batata', 1, 'u', 200, FALSE, TRUE, 21),    (8, 'Palta', 2, 'u', 200, FALSE, TRUE, 5),
 (9, 'Frutilla', 2, 'g', NULL, FALSE, TRUE, 4),  (10, 'Manteca', 3, 'g', NULL, FALSE, TRUE, 30),
 (11, 'Crema de leche', 3, 'ml', NULL, FALSE, TRUE, 7), (12, 'Queso cremoso', 3, 'g', NULL, FALSE, TRUE, 14),
 (13, 'Carne picada', 4, 'g', NULL, FALSE, TRUE, 2), (14, 'Pollo', 4, 'u', 200, FALSE, TRUE, 3),
 (15, 'Huevo', 5, 'u', 55, FALSE, TRUE, 21),     (16, 'Harina 0000', 6, 'g', NULL, FALSE, FALSE, 365),
 (17, 'Fideos', 6, 'g', NULL, FALSE, FALSE, 730), (18, 'Arroz', 6, 'g', NULL, FALSE, FALSE, 730),
 (19, 'Aceite', 7, 'ml', NULL, TRUE, FALSE, 540), (20, 'Sal', 7, 'g', NULL, TRUE, FALSE, NULL),
 (21, 'Poroto', 8, 'g', NULL, FALSE, FALSE, 730), (22, 'Perejil', 1, 'g', NULL, FALSE, TRUE, 7),
 (23, 'Cebolla de verdeo', 1, 'u', 40, FALSE, TRUE, 7),
 (24, 'Leche', 3, 'ml', NULL, FALSE, TRUE, 7),   (25, 'Queso rallado', 3, 'g', NULL, FALSE, TRUE, 30),
 (26, 'Jamón cocido', 4, 'g', NULL, FALSE, TRUE, 7), (27, 'Pan rallado', 6, 'g', NULL, FALSE, FALSE, 180),
 (28, 'Zanahoria', 1, 'u', 80, FALSE, TRUE, 21), (29, 'Ajo', 1, 'u', 5, FALSE, TRUE, 60),
 (30, 'Lenteja', 8, 'g', NULL, FALSE, FALSE, 730), (31, 'Polenta', 6, 'g', NULL, FALSE, FALSE, 365),
 (32, 'Tapa de tarta', 6, 'u', NULL, FALSE, TRUE, 20), (33, 'Arveja', 8, 'g', NULL, FALSE, FALSE, 730),
 (34, 'Pimentón', 7, 'g', NULL, TRUE, FALSE, 365), (35, 'Calabaza', 1, 'g', NULL, FALSE, TRUE, 30),
 (36, 'Atún en lata', 6, 'u', 170, FALSE, FALSE, 730), (37, 'Lechuga', 1, 'u', 300, FALSE, TRUE, 5),
 (38, 'Limón', 2, 'u', 100, FALSE, TRUE, 21), (39, 'Banana', 2, 'u', 120, FALSE, TRUE, 6),
 (40, 'Azúcar', 6, 'g', NULL, FALSE, FALSE, 730), (41, 'Pimienta', 7, 'g', NULL, TRUE, FALSE, 365),
 (42, 'Caldo de verduras', 6, 'u', NULL, FALSE, FALSE, 365), (43, 'Puré de tomate', 6, 'g', NULL, FALSE, FALSE, 365),
 (44, 'Bife', 4, 'u', 200, FALSE, TRUE, 3),
 (45, 'Manzana', 2, 'u', 180, FALSE, TRUE, 30),
 (46, 'Naranja', 2, 'u', 200, FALSE, TRUE, 21),
 (47, 'Pera', 2, 'u', 170, FALSE, TRUE, 14),
 (48, 'Durazno', 2, 'u', 150, FALSE, TRUE, 5),
 (49, 'Uva', 2, 'g', NULL, FALSE, TRUE, 7),
 (50, 'Ananá', 2, 'u', 1500, FALSE, TRUE, 7),
 (51, 'Kiwi', 2, 'u', 80, FALSE, TRUE, 14),
 (52, 'Sandía', 2, 'u', 4000, FALSE, TRUE, 10),
 (53, 'Mandarina', 2, 'u', 120, FALSE, TRUE, 14),
 (54, 'Cereza', 2, 'g', NULL, FALSE, TRUE, 5),
 (55, 'Berenjena', 1, 'u', 300, FALSE, TRUE, 10),
 (56, 'Brócoli', 1, 'u', 400, FALSE, TRUE, 7),
 (57, 'Espinaca', 1, 'g', NULL, FALSE, TRUE, 5),
 (58, 'Pepino', 1, 'u', 250, FALSE, TRUE, 10),
 (59, 'Champiñón', 1, 'g', NULL, FALSE, TRUE, 6),
 (60, 'Remolacha', 1, 'u', 150, FALSE, TRUE, 21),
 (61, 'Coliflor', 1, 'u', 800, FALSE, TRUE, 10),
 (62, 'Rabanito', 1, 'u', 20, FALSE, TRUE, 7),
 (63, 'Yogur', 3, 'ml', NULL, FALSE, TRUE, 21),
 (64, 'Ricota', 3, 'g', NULL, FALSE, TRUE, 7),
 (65, 'Mozzarella', 3, 'g', NULL, FALSE, TRUE, 14),
 (66, 'Salchicha', 4, 'u', 50, FALSE, TRUE, 14),
 (67, 'Chorizo', 4, 'u', 120, FALSE, TRUE, 5),
 (68, 'Merluza', 4, 'u', 150, FALSE, TRUE, 2),
 (69, 'Langostinos', 4, 'g', NULL, FALSE, TRUE, 2),
 (70, 'Pan', 6, 'g', NULL, FALSE, TRUE, 3),
 (71, 'Tapas de empanada', 6, 'u', NULL, FALSE, TRUE, 20),
 (72, 'Mayonesa', 6, 'g', NULL, FALSE, FALSE, 90),
 (73, 'Miel', 6, 'g', NULL, FALSE, FALSE, 730),
 (74, 'Chocolate', 6, 'g', NULL, FALSE, FALSE, 365),
 (75, 'Yerba', 6, 'g', NULL, FALSE, FALSE, 365),
 (76, 'Café', 6, 'g', NULL, FALSE, FALSE, 365);

INSERT INTO sinonimo (ingrediente_id, texto, region) VALUES
 (1, 'patata', 'ES'), (3, 'pimiento', NULL), (3, 'ají morrón', 'AR'), (4, 'jitomate', 'MX'),
 (5, 'calabacín', 'ES'), (5, 'zucchini', NULL), (6, 'maíz', NULL), (6, 'elote', 'MX'),
 (7, 'camote', NULL), (7, 'boniato', 'ES'), (8, 'aguacate', NULL), (9, 'fresa', NULL),
 (10, 'mantequilla', NULL), (11, 'nata', 'ES'), (13, 'carne molida', NULL),
 (14, 'suprema', 'AR'), (16, 'harina de trigo', NULL), (17, 'pasta', NULL),
 (21, 'frijol', 'MX'), (21, 'alubia', 'ES'),
 (35, 'zapallo', 'AR'), (24, 'leche entera', NULL), (30, 'lentejas', NULL), (31, 'harina de maíz', NULL),
 (33, 'guisantes', 'ES'), (43, 'salsa de tomate', NULL), (26, 'jamón', NULL),
 (44, 'churrasco', 'AR'), (44, 'filete', NULL),
 (14, 'pechuga', NULL), (48, 'melocotón', 'ES'), (50, 'piña', NULL), (53, 'tangerina', NULL), (59, 'hongo', NULL),
 (60, 'betabel', 'MX'), (62, 'rábano', NULL), (63, 'yogurt', NULL), (64, 'requesón', NULL), (65, 'muzzarella', 'AR'),
 (68, 'pescado', NULL), (69, 'camarones', NULL), (71, 'discos de empanada', NULL);

INSERT INTO etiqueta (id, nombre) VALUES (1, 'vegetariana'), (2, 'sin_tacc'), (3, 'economica'), (4, 'dulce'),
 (5, 'vegana'), (6, 'sin_lactosa'), (7, 'sin_huevo'), (8, 'rapida'), (9, 'merienda_salada');

INSERT INTO receta (id, slug, titulo, descripcion, porciones_base, minutos_prep, minutos_coccion, dificultad, fuente, autor_id) VALUES
 (1, 'tortilla-de-papas', 'Tortilla de papas', 'Clásica, rendidora y con pocos ingredientes.', 4, 15, 25, 1, 'Receta del equipo', 1),
 (2, 'fideos-con-manteca-y-queso', 'Fideos con manteca y queso', 'La salvación de cualquier noche: lista en 15 minutos.', 4, 5, 10, 1, 'Receta del equipo', 1),
 (3, 'arroz-con-pollo', 'Arroz con pollo', 'Un plato completo en una sola olla.', 4, 15, 30, 2, 'Receta del equipo', 1),
 (4, 'milanesas-de-pollo', 'Milanesas de pollo', 'Crocantes por fuera y jugosas por dentro.', 4, 20, 15, 2, 'Receta del equipo', 1),
 (5, 'guiso-de-lentejas', 'Guiso de lentejas', 'Para los días de frío, sin carne y muy rendidor.', 6, 15, 40, 1, 'Receta del equipo', 1),
 (6, 'tarta-de-zapallitos', 'Tarta de zapallitos', 'Suave, con queso y lista para llevar.', 6, 20, 35, 2, 'Receta del equipo', 1),
 (7, 'revuelto-gramajo', 'Revuelto gramajo', 'Papas fritas, huevo y jamón: un clásico porteño.', 2, 15, 20, 1, 'Receta del equipo', 1),
 (8, 'pastel-de-papas', 'Pastel de papas', 'Carne picada bien condimentada con puré gratinado.', 6, 30, 30, 2, 'Receta del equipo', 1),
 (9, 'panqueques', 'Panqueques', 'Base dulce o salada. Rinden unos 12.', 4, 10, 20, 1, 'Receta del equipo', 1),
 (10, 'polenta-con-queso', 'Polenta con queso', 'Cremosa, rápida y económica.', 4, 5, 10, 1, 'Receta del equipo', 1),
 (11, 'sopa-de-verduras', 'Sopa de verduras', 'Aprovecha las verduras que están por vencer.', 4, 15, 35, 1, 'Receta del equipo', 1),
 (12, 'ensalada-de-atun', 'Ensalada de atún', 'Fresca y sin horno, ideal para el verano.', 2, 15, 10, 1, 'Receta del equipo', 1);

INSERT INTO receta_ingrediente (receta_id, ingrediente_id, cantidad, unidad, peso, es_opcional, nota) VALUES
 (1, 1, 600, 'g', 3, FALSE, NULL), (1, 15, 5, 'u', 3, FALSE, NULL), (1, 2, 1, 'u', 2, FALSE, NULL),
 (1, 19, 200, 'ml', 2, FALSE, NULL), (1, 20, 1, 'cda', 1, FALSE, NULL), (1, 22, 1, 'cda', 1, TRUE, 'picado, para servir'),
 (2, 17, 400, 'g', 3, FALSE, NULL),
 (2, 10, 50, 'g', 2, FALSE, NULL),
 (2, 25, 60, 'g', 2, FALSE, NULL),
 (2, 20, 1, 'pizca', 1, FALSE, NULL),
 (3, 14, 2, 'u', 3, FALSE, 'en cubos'),
 (3, 18, 300, 'g', 3, FALSE, NULL),
 (3, 2, 1, 'u', 2, FALSE, NULL),
 (3, 3, 1, 'u', 2, FALSE, NULL),
 (3, 29, 2, 'u', 1, FALSE, 'picado'),
 (3, 42, 1, 'u', 1, FALSE, NULL),
 (3, 34, 1, 'cdita', 1, FALSE, NULL),
 (3, 19, 30, 'ml', 1, FALSE, NULL),
 (3, 20, 1, 'cdita', 1, FALSE, NULL),
 (4, 14, 3, 'u', 3, FALSE, 'fileteadas'),
 (4, 15, 2, 'u', 2, FALSE, NULL),
 (4, 27, 200, 'g', 3, FALSE, NULL),
 (4, 29, 1, 'u', 1, FALSE, NULL),
 (4, 22, 1, 'cda', 1, TRUE, 'picado'),
 (4, 19, 200, 'ml', 2, FALSE, 'para freír'),
 (4, 20, 1, 'cdita', 1, FALSE, NULL),
 (5, 30, 300, 'g', 3, FALSE, 'remojadas 2 h'),
 (5, 28, 2, 'u', 2, FALSE, NULL),
 (5, 2, 1, 'u', 2, FALSE, NULL),
 (5, 3, 1, 'u', 2, FALSE, NULL),
 (5, 1, 2, 'u', 2, FALSE, NULL),
 (5, 43, 200, 'g', 2, FALSE, NULL),
 (5, 34, 1, 'cdita', 1, FALSE, NULL),
 (5, 42, 1, 'u', 1, FALSE, NULL),
 (5, 19, 30, 'ml', 1, FALSE, NULL),
 (5, 20, 1, 'cdita', 1, FALSE, NULL),
 (6, 32, 2, 'u', 3, FALSE, NULL),
 (6, 5, 3, 'u', 3, FALSE, NULL),
 (6, 2, 1, 'u', 2, FALSE, NULL),
 (6, 15, 3, 'u', 2, FALSE, NULL),
 (6, 12, 150, 'g', 2, FALSE, NULL),
 (6, 25, 40, 'g', 1, TRUE, NULL),
 (6, 19, 30, 'ml', 1, FALSE, NULL),
 (6, 20, 1, 'cdita', 1, FALSE, NULL),
 (7, 1, 3, 'u', 3, FALSE, 'en bastones finos'),
 (7, 15, 4, 'u', 3, FALSE, NULL),
 (7, 26, 150, 'g', 2, FALSE, 'en tiras'),
 (7, 33, 100, 'g', 1, TRUE, NULL),
 (7, 2, 1, 'u', 2, FALSE, NULL),
 (7, 19, 200, 'ml', 2, FALSE, 'para freír'),
 (7, 20, 1, 'pizca', 1, FALSE, NULL),
 (8, 1, 1000, 'g', 3, FALSE, NULL),
 (8, 13, 500, 'g', 3, FALSE, NULL),
 (8, 2, 2, 'u', 2, FALSE, NULL),
 (8, 3, 1, 'u', 1, FALSE, NULL),
 (8, 15, 1, 'u', 1, FALSE, NULL),
 (8, 24, 100, 'ml', 1, FALSE, NULL),
 (8, 10, 30, 'g', 1, FALSE, NULL),
 (8, 34, 1, 'cdita', 1, FALSE, NULL),
 (8, 20, 1, 'cdita', 1, FALSE, NULL),
 (9, 16, 150, 'g', 3, FALSE, NULL),
 (9, 24, 300, 'ml', 3, FALSE, NULL),
 (9, 15, 2, 'u', 3, FALSE, NULL),
 (9, 10, 20, 'g', 1, FALSE, 'derretida'),
 (9, 40, 1, 'cda', 1, TRUE, 'si son dulces'),
 (9, 20, 1, 'pizca', 1, FALSE, NULL),
 (10, 31, 250, 'g', 3, FALSE, NULL),
 (10, 24, 500, 'ml', 2, FALSE, NULL),
 (10, 12, 150, 'g', 2, FALSE, NULL),
 (10, 10, 30, 'g', 1, FALSE, NULL),
 (10, 25, 40, 'g', 1, FALSE, NULL),
 (10, 20, 1, 'cdita', 1, FALSE, NULL),
 (11, 35, 500, 'g', 3, FALSE, NULL),
 (11, 28, 2, 'u', 2, FALSE, NULL),
 (11, 1, 2, 'u', 2, FALSE, NULL),
 (11, 2, 1, 'u', 2, FALSE, NULL),
 (11, 5, 1, 'u', 1, TRUE, NULL),
 (11, 42, 1, 'u', 1, FALSE, NULL),
 (11, 20, 1, 'cdita', 1, FALSE, NULL),
 (12, 36, 1, 'u', 3, FALSE, NULL),
 (12, 37, 1, 'u', 2, FALSE, NULL),
 (12, 4, 2, 'u', 2, FALSE, NULL),
 (12, 15, 2, 'u', 2, FALSE, 'duros'),
 (12, 38, 1, 'u', 1, FALSE, 'el jugo'),
 (12, 19, 30, 'ml', 1, FALSE, NULL),
 (12, 20, 1, 'pizca', 1, FALSE, NULL);

INSERT INTO paso (receta_id, numero, instruccion, segundos_timer) VALUES
 (1, 1, 'Pelar las papas y cortarlas en rodajas finas. Cortar la cebolla en pluma.', NULL),
 (1, 2, 'Calentar el aceite y freír papas y cebolla a fuego medio-bajo hasta que estén tiernas.', 900),
 (1, 3, 'Batir los huevos con la sal e incorporar las papas tibias. Reposar 5 minutos.', 300),
 (1, 4, 'Volcar en la sartén y cocinar a fuego bajo hasta que cuaje el fondo.', 420),
 (1, 5, 'Dar vuelta con un plato y terminar del otro lado. Servir con perejil.', 300),
 (2, 1, 'Hervir los fideos en abundante agua con sal el tiempo que indique el paquete.', NULL),
 (2, 2, 'Colar y volver a la olla con la manteca.', NULL),
 (2, 3, 'Mezclar con el queso rallado y servir enseguida.', NULL),
 (3, 1, 'Dorar el pollo en la olla con el aceite y reservar.', NULL),
 (3, 2, 'Rehogar la cebolla, el morrón y el ajo picados.', NULL),
 (3, 3, 'Agregar el arroz, el pimentón y el pollo; mezclar un minuto.', NULL),
 (3, 4, 'Cubrir con el caldo caliente (el doble de volumen que el arroz) y cocinar tapado 18 minutos.', NULL),
 (3, 5, 'Apagar y dejar reposar 5 minutos antes de servir.', NULL),
 (4, 1, 'Batir los huevos con el ajo, el perejil y la sal.', NULL),
 (4, 2, 'Pasar cada filete por el huevo y después por el pan rallado, apretando bien.', NULL),
 (4, 3, 'Freír en aceite caliente 3 a 4 minutos por lado.', NULL),
 (4, 4, 'Escurrir sobre papel absorbente.', NULL),
 (5, 1, 'Rehogar la cebolla, el morrón y la zanahoria en el aceite.', NULL),
 (5, 2, 'Agregar las papas en cubos, el pimentón y el puré de tomate.', NULL),
 (5, 3, 'Sumar las lentejas escurridas y el caldo; cubrir con agua.', NULL),
 (5, 4, 'Cocinar a fuego bajo 35 minutos, revolviendo cada tanto, hasta que espese.', NULL),
 (6, 1, 'Rehogar la cebolla y los zapallitos rallados hasta que pierdan el agua.', NULL),
 (6, 2, 'Fuera del fuego, mezclar con los huevos y el queso cremoso en cubos.', NULL),
 (6, 3, 'Forrar una tartera con una tapa, volcar el relleno y cubrir con la otra.', NULL),
 (6, 4, 'Hornear a 180 °C durante 35 minutos.', NULL),
 (7, 1, 'Freír las papas en bastones finos hasta que estén doradas.', NULL),
 (7, 2, 'En otra sartén, saltear la cebolla y el jamón.', NULL),
 (7, 3, 'Agregar las papas y los huevos batidos; revolver hasta que estén cremosos.', NULL),
 (7, 4, 'Sumar las arvejas al final y servir.', NULL),
 (8, 1, 'Hervir las papas y hacer un puré con la manteca y la leche.', NULL),
 (8, 2, 'Rehogar la cebolla y el morrón, sumar la carne y el pimentón y cocinar hasta que se dore.', NULL),
 (8, 3, 'Poner la carne en una fuente, cubrir con el puré y pintar con huevo batido.', NULL),
 (8, 4, 'Gratinar en el horno fuerte 20 minutos.', NULL),
 (9, 1, 'Licuar la harina, la leche, los huevos, la manteca y la sal. Reposar 15 minutos.', NULL),
 (9, 2, 'Calentar una panquequera y untarla apenas con manteca.', NULL),
 (9, 3, 'Volcar un cucharón de mezcla, cocinar hasta que se despegue, dar vuelta y cocinar 30 segundos más.', NULL),
 (10, 1, 'Calentar la leche con 500 ml de agua y la sal.', NULL),
 (10, 2, 'Agregar la polenta en lluvia, batiendo para que no se formen grumos.', NULL),
 (10, 3, 'Cocinar 5 minutos sin dejar de revolver.', NULL),
 (10, 4, 'Fuera del fuego, sumar la manteca y los quesos.', NULL),
 (11, 1, 'Pelar y cortar todas las verduras en cubos.', NULL),
 (11, 2, 'Rehogar la cebolla, agregar el resto de las verduras y el caldo.', NULL),
 (11, 3, 'Cubrir con agua y cocinar 30 minutos.', NULL),
 (11, 4, 'Procesar si se quiere una crema, o servir con las verduras enteras.', NULL),
 (12, 1, 'Hervir los huevos 10 minutos y pelarlos.', NULL),
 (12, 2, 'Lavar y cortar la lechuga y los tomates.', NULL),
 (12, 3, 'Mezclar con el atún escurrido y los huevos en cuartos.', NULL),
 (12, 4, 'Condimentar con limón, aceite y sal.', NULL);

INSERT INTO receta_etiqueta (receta_id, etiqueta_id) VALUES
 (1, 1), (1, 2), (1, 3), (2, 1), (2, 3), (3, 2), (3, 3), (5, 1), (5, 2), (5, 3), (6, 1), (7, 2), (8, 2), (9, 1), (9, 3), (10, 1), (10, 2), (10, 3), (11, 1), (11, 2), (11, 3), (12, 2),
 -- Tipos del PR D ("rapida" = 30 minutos o menos entre preparación y cocción)
 (1, 6), (2, 7), (2, 8), (3, 6), (3, 7), (4, 6), (5, 5), (5, 6), (5, 7), (7, 6), (9, 8), (10, 7), (10, 8),
 (11, 5), (11, 6), (11, 7), (12, 6), (12, 8);

-- Despensa de ejemplo (las fechas son relativas a hoy, para que siempre haya algo "por vencer")
INSERT INTO despensa_item (usuario_id, ingrediente_id, nivel, fecha_vencimiento, ubicacion) VALUES
 (3, 1, 2, CURDATE() + INTERVAL 10 DAY, 'alacena'),  (3, 15, 3, CURDATE() + INTERVAL 6 DAY, 'heladera'),
 (3, 2, 2, NULL, 'alacena'),                         (3, 24, 2, CURDATE() + INTERVAL 2 DAY, 'heladera'),
 (3, 12, 1, CURDATE() + INTERVAL 1 DAY, 'heladera'), (3, 17, 3, NULL, 'alacena'),
 (3, 10, 2, CURDATE() + INTERVAL 15 DAY, 'heladera'),(3, 4, 1, CURDATE() - INTERVAL 1 DAY, 'heladera'),
 (3, 18, 2, NULL, 'alacena'),                        (3, 16, 1, NULL, 'alacena'),
 (3, 19, 2, NULL, 'alacena'),                        (3, 20, 3, NULL, 'alacena'),
 (3, 5, 2, CURDATE() + INTERVAL 3 DAY, 'heladera'),  (3, 25, 1, CURDATE() + INTERVAL 20 DAY, 'heladera'),
 (4, 14, 2, CURDATE() + INTERVAL 60 DAY, 'freezer'), (4, 18, 3, NULL, 'alacena'),
 (4, 30, 2, NULL, 'alacena'),                        (4, 28, 2, CURDATE() + INTERVAL 8 DAY, 'heladera'),
 (4, 2, 1, NULL, 'alacena');

-- Algunos ítems con la cantidad exacta (la unidad es la del ingrediente)
UPDATE despensa_item SET cantidad = 6, unidad = 'u' WHERE usuario_id = 3 AND ingrediente_id = 15;
UPDATE despensa_item SET cantidad = 1000, unidad = 'ml' WHERE usuario_id = 3 AND ingrediente_id = 24;
UPDATE despensa_item SET cantidad = 500, unidad = 'g' WHERE usuario_id = 3 AND ingrediente_id = 17;
UPDATE despensa_item SET cantidad = 4, unidad = 'u' WHERE usuario_id = 3 AND ingrediente_id = 1;

INSERT INTO contacto (usuario_id, nombre, email, asunto, mensaje, estado, correo_enviado, creado_en) VALUES
 (3, 'María López', 'maria@cooki.test', 'Sugerencia de receta', '¿Podrían agregar recetas sin horno? Gracias.', 'nueva', TRUE, NOW() - INTERVAL 1 DAY),
 (NULL, 'Carlos Ruiz', 'carlos@ejemplo.test', 'Consulta', '¿La app es gratuita?', 'respondida', TRUE, NOW() - INTERVAL 3 DAY);

-- Incremento 2: 31 ingredientes nuevos (77 a 107), cada uno con su dibujo, para el recetario ampliado.
INSERT INTO ingrediente (id, nombre, categoria_id, unidad_default, gramos_por_unidad, es_basico, perecedero, vida_util_dias) VALUES
 (77, 'Orégano', 7, 'g', NULL, FALSE, FALSE, 365), (78, 'Albahaca', 1, 'g', NULL, FALSE, TRUE, 5),
 (79, 'Laurel', 7, 'u', NULL, FALSE, FALSE, 365), (80, 'Comino', 7, 'g', NULL, FALSE, FALSE, 365),
 (81, 'Nuez moscada', 7, 'g', NULL, FALSE, FALSE, 365), (82, 'Vinagre', 6, 'ml', NULL, FALSE, FALSE, 365),
 (83, 'Mostaza', 6, 'g', NULL, FALSE, FALSE, 180), (84, 'Levadura', 6, 'g', NULL, FALSE, TRUE, 20),
 (85, 'Polvo de hornear', 6, 'g', NULL, FALSE, FALSE, 365), (86, 'Avena', 6, 'g', NULL, FALSE, FALSE, 365),
 (87, 'Garbanzo', 8, 'g', NULL, FALSE, FALSE, 365), (88, 'Repollo', 1, 'u', 1500, FALSE, TRUE, 14),
 (89, 'Acelga', 1, 'u', 500, FALSE, TRUE, 5), (90, 'Puerro', 1, 'u', 150, FALSE, TRUE, 10),
 (91, 'Apio', 1, 'u', 50, FALSE, TRUE, 10), (92, 'Panceta', 4, 'g', NULL, FALSE, TRUE, 10),
 (93, 'Carne para guiso', 4, 'g', NULL, FALSE, TRUE, 3), (94, 'Cerdo', 4, 'g', NULL, FALSE, TRUE, 3),
 (95, 'Queso crema', 3, 'g', NULL, FALSE, TRUE, 15), (96, 'Dulce de leche', 6, 'g', NULL, FALSE, FALSE, 90),
 (97, 'Dulce de membrillo', 6, 'g', NULL, FALSE, FALSE, 90), (98, 'Maicena', 6, 'g', NULL, FALSE, FALSE, 365),
 (99, 'Cacao', 6, 'g', NULL, FALSE, FALSE, 365), (100, 'Coco rallado', 6, 'g', NULL, FALSE, FALSE, 180),
 (101, 'Nueces', 6, 'g', NULL, FALSE, FALSE, 180), (102, 'Pasas de uva', 6, 'g', NULL, FALSE, FALSE, 180),
 (103, 'Galletitas', 6, 'g', NULL, FALSE, FALSE, 90), (104, 'Aceitunas', 6, 'g', NULL, FALSE, FALSE, 180),
 (105, 'Salsa de soja', 6, 'ml', NULL, FALSE, FALSE, 365), (106, 'Jengibre', 1, 'g', NULL, FALSE, TRUE, 21),
 (107, 'Esencia de vainilla', 6, 'ml', NULL, FALSE, FALSE, 365);

INSERT INTO sinonimo (ingrediente_id, texto, region) VALUES
 (84, 'levadura fresca', NULL), (85, 'polvo leudante', NULL), (87, 'garbanzos', NULL), (88, 'col', NULL),
 (90, 'ajo porro', NULL), (92, 'tocino', NULL), (92, 'bacon', NULL), (93, 'carnaza', 'AR'),
 (93, 'cubos de carne', NULL), (94, 'bondiola', 'AR'), (94, 'chancho', 'AR'), (95, 'queso untable', NULL),
 (98, 'almidón de maíz', NULL), (98, 'fécula de maíz', NULL), (99, 'cacao amargo', NULL),
 (97, 'membrillo', NULL), (102, 'uvas pasas', NULL), (103, 'galletas', NULL), (104, 'olivas', NULL),
 (105, 'soya', 'MX'), (106, 'kion', 'PE'), (107, 'vainilla', NULL), (82, 'aceto', NULL);

-- Sustituciones (incremento 2): si falta el primero y el usuario tiene el segundo, la receta igual se
-- puede hacer y se lo avisa ("Sin crema de leche: podés usar leche"). calidad: 1 a 5 (5 = casi igual).
INSERT INTO sustitucion (id, ingrediente_id, reemplazo_id, ratio, nota, calidad) VALUES
 (1, 10, 19, 0.80, 'Usá un poco menos de aceite que de manteca.', 3),
 (2, 11, 24, 1.00, 'Leche con una cucharada de manteca.', 3),
 (3, 11, 63, 1.00, 'Yogur natural, sin hervir.', 3),
 (4, 12, 65, 1.00, NULL, 4),
 (5, 65, 12, 1.00, NULL, 4),
 (6, 25, 12, 1.00, 'Rallado grueso o en cubitos.', 2),
 (7, 64, 12, 1.00, 'Pisado con un tenedor.', 2),
 (8, 27, 70, 1.00, 'Pan seco rallado o procesado.', 4),
 (9, 2, 23, 1.00, NULL, 4),
 (10, 23, 2, 1.00, NULL, 3),
 (11, 32, 71, 1.00, 'Tapas de empanada superpuestas.', 3),
 (12, 71, 32, 1.00, 'Tapa de tarta cortada en discos.', 3),
 (13, 1, 7, 1.00, NULL, 3),
 (14, 7, 1, 1.00, NULL, 3),
 (15, 5, 55, 1.00, NULL, 2),
 (16, 35, 7, 1.00, NULL, 3),
 (17, 43, 4, 1.50, 'Tomate rallado.', 3),
 (18, 4, 43, 0.70, 'Para salsas y guisos.', 3),
 (19, 30, 21, 1.00, NULL, 4),
 (20, 21, 30, 1.00, NULL, 4);

-- Incremento 2 · entrega B: preferencias de ejemplo de María (usuario 3).
-- "Siempre tengo" ajo; dos recetas favoritas. No tiene restricciones (se prueban desde "Mis preferencias").
INSERT INTO usuario_basico (usuario_id, ingrediente_id) VALUES (3, 29);
INSERT INTO favorito (usuario_id, receta_id) VALUES (3, 1), (3, 6);
