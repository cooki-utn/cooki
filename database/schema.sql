-- =====================================================================
--  Cooki · Esquema de base de datos (MariaDB 10.6+ / MySQL 8.0+)
--  Incremento 1 (2.º parcial de Programación): usuarios con rol, tabla de contacto y autor de cada receta.
--  Incremento 2: modo cocina, preferencias, recuperación de contraseña e intentos de ingreso.
--  Incremento 3: lista de compras, plan semanal e IA en las recetas.
--  Ejecutar sobre una base vacía:  mysql -u root -p cooki < database/schema.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS lista_compra_item, plan_comida, sugerencia_reemplazo, uso_ia, respuesta_ia, receta_ia,
    intento_ingreso, recuperacion, contacto, paso_completado, sesion_cocina, favorito, receta_etiqueta, etiqueta,
    paso, receta_ingrediente, receta, despensa_item, restriccion_usuario, usuario_basico,
    sustitucion, sinonimo, ingrediente, categoria, usuario;

SET FOREIGN_KEY_CHECKS = 1;

-- ─────────────────────────── Usuarios ───────────────────────────
CREATE TABLE usuario (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email            VARCHAR(190) NOT NULL UNIQUE,
    nombre           VARCHAR(80)  NOT NULL,
    hash_contrasena  VARCHAR(255) NOT NULL,          -- password_hash() de PHP, nunca la contraseña
    rol              ENUM('general','admin') NOT NULL DEFAULT 'general',
    color            VARCHAR(10)  NOT NULL DEFAULT 'verde',  -- color de la heladera elegido (UsuarioRepositorio::COLORES)
    disposicion      VARCHAR(12)  NOT NULL DEFAULT 'normal', -- 'normal' o 'panoramica' (UsuarioRepositorio::DISPOSICIONES)
    volumen          TINYINT UNSIGNED NOT NULL DEFAULT 50,   -- volumen de los sonidos, de 0 a 100
    creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cuentas de usuario. La identidad la maneja la propia app (sesiones PHP).';

-- ─────────────────────── Taxonomía de ingredientes ───────────────────────
CREATE TABLE categoria (
    id         SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre     VARCHAR(50) NOT NULL UNIQUE,          -- Verduras, Carnes, Lácteos...
    color_hex  CHAR(7)     NOT NULL,
    orden      SMALLINT    NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE ingrediente (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre             VARCHAR(80)  NOT NULL,        -- nombre canónico
    categoria_id       SMALLINT UNSIGNED NOT NULL,
    icono              VARCHAR(60)  NULL,            -- clave del icono (OpenMoji)
    unidad_default     VARCHAR(10)  NOT NULL,        -- g, ml, u
    gramos_por_unidad  DECIMAL(8,2) NULL,            -- para convertir "2 huevos" a gramos
    es_basico          BOOLEAN      NOT NULL DEFAULT FALSE,  -- sal, aceite, agua
    perecedero         BOOLEAN      NOT NULL DEFAULT TRUE,
    vida_util_dias     SMALLINT     NULL,
    -- NULL = lista general. Con un usuario: ingrediente propio que agregó desde su despensa
    -- (solo lo ve él; el administrador lo puede pasar a la lista general)
    usuario_id         INT UNSIGNED NULL,
    -- 0 para la lista general: así el nombre no se repite en la lista general ni entre los propios de cada uno
    ambito             INT UNSIGNED AS (IFNULL(usuario_id, 0)) PERSISTENT,
    UNIQUE KEY uq_ingrediente_nombre (nombre, ambito),
    CONSTRAINT fk_ingrediente_categoria FOREIGN KEY (categoria_id) REFERENCES categoria(id),
    CONSTRAINT fk_ingrediente_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Variantes regionales: palta = aguacate, morrón = pimiento
CREATE TABLE sinonimo (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ingrediente_id  INT UNSIGNED NOT NULL,
    texto           VARCHAR(80)  NOT NULL,
    region          VARCHAR(10)  NULL,               -- AR, ES, MX; NULL = general
    UNIQUE KEY uq_sinonimo (ingrediente_id, texto),
    KEY idx_sinonimo_texto (texto),
    CONSTRAINT fk_sinonimo_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE sustitucion (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ingrediente_id  INT UNSIGNED NOT NULL,
    reemplazo_id    INT UNSIGNED NOT NULL,
    ratio           DECIMAL(5,2) NOT NULL DEFAULT 1, -- cantidad de reemplazo por unidad
    nota            VARCHAR(200) NULL,
    calidad         TINYINT      NOT NULL DEFAULT 3, -- 1 a 5
    CONSTRAINT ck_sustitucion_distinto CHECK (ingrediente_id <> reemplazo_id),
    CONSTRAINT fk_sust_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id),
    CONSTRAINT fk_sust_reemplazo  FOREIGN KEY (reemplazo_id)  REFERENCES ingrediente(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────── Preferencias del usuario ───────────────────────
CREATE TABLE usuario_basico (                        -- "siempre tengo": sal, aceite, agua...
    usuario_id      INT UNSIGNED NOT NULL,
    ingrediente_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, ingrediente_id),
    CONSTRAINT fk_ub_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_ub_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE restriccion_usuario (                   -- alergias y dietas: nunca recomendar
    usuario_id      INT UNSIGNED NOT NULL,
    ingrediente_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, ingrediente_id),
    CONSTRAINT fk_ru_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_ru_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────── Despensa ───────────────────────────
CREATE TABLE despensa_item (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id         INT UNSIGNED NOT NULL,
    ingrediente_id     INT UNSIGNED NOT NULL,
    nivel              TINYINT      NOT NULL DEFAULT 2,   -- 0 agotado · 1 poco · 2 normal · 3 mucho
    cantidad           DECIMAL(9,2) NULL,                 -- opcional: cantidad exacta
    unidad             VARCHAR(10)  NULL,
    fecha_vencimiento  DATE         NULL,
    ubicacion          ENUM('alacena','heladera','freezer') NOT NULL DEFAULT 'alacena',
    actualizado_en     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT ck_despensa_nivel CHECK (nivel BETWEEN 0 AND 3),
    UNIQUE KEY uq_despensa (usuario_id, ingrediente_id, ubicacion),
    KEY idx_despensa_vencimiento (usuario_id, fecha_vencimiento),
    CONSTRAINT fk_despensa_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_despensa_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────── Recetas ───────────────────────────
CREATE TABLE receta (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(120) NOT NULL UNIQUE,
    titulo           VARCHAR(150) NOT NULL,
    descripcion      TEXT         NULL,
    porciones_base   TINYINT      NOT NULL DEFAULT 4,
    minutos_prep     SMALLINT     NOT NULL,
    minutos_coccion  SMALLINT     NOT NULL DEFAULT 0,
    dificultad       TINYINT      NOT NULL,
    imagen           VARCHAR(255) NULL,
    fuente           VARCHAR(255) NULL,          -- atribución / inspiración
    autor_id         INT UNSIGNED NULL,          -- administrador que la cargó
    creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ck_receta_dificultad CHECK (dificultad BETWEEN 1 AND 3),
    CONSTRAINT fk_receta_autor FOREIGN KEY (autor_id) REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE receta_ingrediente (
    receta_id       INT UNSIGNED NOT NULL,
    ingrediente_id  INT UNSIGNED NOT NULL,
    cantidad        DECIMAL(9,2) NULL,
    unidad          VARCHAR(10)  NULL,
    peso            TINYINT      NOT NULL DEFAULT 2,   -- 3 principal · 2 secundario · 1 accesorio
    es_opcional     BOOLEAN      NOT NULL DEFAULT FALSE,
    nota            VARCHAR(100) NULL,                 -- "picado fino", "a gusto"
    PRIMARY KEY (receta_id, ingrediente_id),
    KEY idx_ri_ingrediente (ingrediente_id),
    CONSTRAINT ck_ri_peso CHECK (peso BETWEEN 1 AND 3),
    CONSTRAINT fk_ri_receta FOREIGN KEY (receta_id) REFERENCES receta(id) ON DELETE CASCADE,
    CONSTRAINT fk_ri_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paso (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receta_id      INT UNSIGNED NOT NULL,
    numero         TINYINT      NOT NULL,
    instruccion    TEXT         NOT NULL,          -- usa marcadores {ing:papa} para reescalar
    segundos_timer SMALLINT UNSIGNED NULL,
    UNIQUE KEY uq_paso (receta_id, numero),
    CONSTRAINT fk_paso_receta FOREIGN KEY (receta_id) REFERENCES receta(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE etiqueta (
    id      SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre  VARCHAR(40) NOT NULL UNIQUE               -- vegetariana, sin_tacc, economica...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE receta_etiqueta (
    receta_id    INT UNSIGNED NOT NULL,
    etiqueta_id  SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (receta_id, etiqueta_id),
    CONSTRAINT fk_re_receta FOREIGN KEY (receta_id) REFERENCES receta(id) ON DELETE CASCADE,
    CONSTRAINT fk_re_etiqueta FOREIGN KEY (etiqueta_id) REFERENCES etiqueta(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorito (
    usuario_id  INT UNSIGNED NOT NULL,
    receta_id   INT UNSIGNED NOT NULL,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, receta_id),
    CONSTRAINT fk_fav_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_receta FOREIGN KEY (receta_id) REFERENCES receta(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────── Modo cocina ───────────────────────────
CREATE TABLE sesion_cocina (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id        INT UNSIGNED NOT NULL,
    receta_id         INT UNSIGNED NOT NULL,
    porciones         TINYINT      NOT NULL,
    estado            ENUM('en_curso','completada','abandonada') NOT NULL DEFAULT 'en_curso',
    stock_descontado  BOOLEAN      NOT NULL DEFAULT FALSE,  -- evita descontar dos veces
    iniciada_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizada_en     DATETIME     NULL,
    KEY idx_sesion_usuario (usuario_id, estado),
    CONSTRAINT fk_sesion_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_sesion_receta FOREIGN KEY (receta_id) REFERENCES receta(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paso_completado (
    sesion_id      INT UNSIGNED NOT NULL,
    paso_id        INT UNSIGNED NOT NULL,
    completado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (sesion_id, paso_id),
    CONSTRAINT fk_pc_sesion FOREIGN KEY (sesion_id) REFERENCES sesion_cocina(id) ON DELETE CASCADE,
    CONSTRAINT fk_pc_paso FOREIGN KEY (paso_id) REFERENCES paso(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────── Contacto ───────────────────────────
-- Consultas del formulario de contacto. Si quien escribe tiene sesión iniciada,
-- se guarda su usuario; si no, queda en NULL.
CREATE TABLE contacto (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NULL,
    nombre          VARCHAR(80)  NOT NULL,
    email           VARCHAR(190) NOT NULL,
    asunto          VARCHAR(120) NOT NULL,
    mensaje         TEXT         NOT NULL,
    estado          ENUM('nueva','respondida') NOT NULL DEFAULT 'nueva',
    correo_enviado  BOOLEAN      NOT NULL DEFAULT FALSE,   -- si mail() pudo enviarlo
    creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Respuesta desde la administración (se manda por correo y el usuario con cuenta la ve en Contacto)
    respuesta       TEXT         NULL,
    respondida_en   DATETIME     NULL,
    respondida_por  INT UNSIGNED NULL,
    respuesta_enviada BOOLEAN    NOT NULL DEFAULT FALSE,   -- si mail() pudo enviar la respuesta
    KEY idx_contacto_estado (estado, creado_en),
    CONSTRAINT fk_contacto_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE SET NULL,
    CONSTRAINT fk_contacto_respondio FOREIGN KEY (respondida_por) REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────── Cuentas: recuperar la contraseña y bloqueo ───────────────────
-- Pedidos de "Olvidé mi contraseña". Se guarda el hash SHA-256 del token, nunca el token:
-- si alguien lee la base, no puede usar los enlaces. Cada enlace vale 1 hora y una sola vez.
CREATE TABLE recuperacion (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id  INT UNSIGNED NOT NULL,
    token_hash  CHAR(64)     NOT NULL UNIQUE,
    vence_en    DATETIME     NOT NULL,
    usado_en    DATETIME     NULL,
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_recuperacion_usuario (usuario_id, creado_en),
    CONSTRAINT fk_recuperacion_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ingresos fallidos: 5 en 15 minutos para el mismo email bloquean ese email 15 minutos.
-- Se guarda el email aunque no exista la cuenta (así no se puede averiguar cuáles existen).
CREATE TABLE intento_ingreso (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(190) NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    creado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_intento_email (email, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────── Incremento 3: IA, lista de compras y plan semanal ───────────────────
-- Los datos con estructura van en TEXT con JSON adentro, y las reglas entre columnas se validan en PHP:
-- así el esquema anda igual en MariaDB y en el MySQL del hosting (DEC-018).

-- Recetas que propuso la IA. guardada = FALSE: se generó pero la persona no la guardó (se limpia a los 7 días).
CREATE TABLE receta_ia (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT UNSIGNED NOT NULL,
    tipo         ENUM('nueva','adaptada') NOT NULL,
    receta_base  INT UNSIGNED NULL,                  -- la original, si es adaptada
    titulo       VARCHAR(120) NOT NULL,
    contenido    TEXT         NOT NULL,              -- JSON: la receta ya validada y cruzada con el catálogo
    guardada     BOOLEAN      NOT NULL DEFAULT FALSE,
    pasada_a     INT UNSIGNED NULL,                  -- receta.id si la administración la pasó al recetario
    creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_receta_ia_usuario (usuario_id, guardada),
    CONSTRAINT fk_receta_ia_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_receta_ia_base FOREIGN KEY (receta_base) REFERENCES receta(id) ON DELETE SET NULL,
    CONSTRAINT fk_receta_ia_pasada FOREIGN KEY (pasada_a) REFERENCES receta(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Lista de compras. Cada ítem tiene ingrediente_id (del catálogo) o texto (texto libre): lo valida ListaRepositorio.
CREATE TABLE lista_compra_item (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    ingrediente_id  INT UNSIGNED NULL,              -- NULL = texto libre
    texto           VARCHAR(80)  NULL,              -- solo si no está en el catálogo
    cantidad        DECIMAL(9,2) NULL,              -- NULL = sin cantidad ("a gusto")
    unidad          VARCHAR(10)  NULL,
    receta_id       INT UNSIGNED NULL,              -- de qué receta salió (para mostrar "para: …")
    receta_ia_id    INT UNSIGNED NULL,
    comprado_en     DATETIME     NULL,              -- NULL = pendiente
    deshacer        TEXT         NULL,              -- JSON: cómo estaba la despensa antes de comprarlo
    creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lista_usuario (usuario_id, comprado_en),
    CONSTRAINT fk_lista_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_lista_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id) ON DELETE CASCADE,
    CONSTRAINT fk_lista_receta FOREIGN KEY (receta_id) REFERENCES receta(id) ON DELETE SET NULL,
    CONSTRAINT fk_lista_receta_ia FOREIGN KEY (receta_ia_id) REFERENCES receta_ia(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plan semanal: una receta (del recetario o de la IA) por día y comida.
CREATE TABLE plan_comida (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id    INT UNSIGNED NOT NULL,
    fecha         DATE         NOT NULL,
    momento       ENUM('almuerzo','cena') NOT NULL,
    receta_id     INT UNSIGNED NULL,
    receta_ia_id  INT UNSIGNED NULL,
    porciones     TINYINT      NOT NULL DEFAULT 2,
    UNIQUE KEY uq_plan (usuario_id, fecha, momento),
    CONSTRAINT fk_plan_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_plan_receta FOREIGN KEY (receta_id) REFERENCES receta(id) ON DELETE CASCADE,
    CONSTRAINT fk_plan_receta_ia FOREIGN KEY (receta_ia_id) REFERENCES receta_ia(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Caché de la IA: la misma consulta no se paga dos veces (vale 7 días).
CREATE TABLE respuesta_ia (
    clave       CHAR(64)     PRIMARY KEY,            -- sha256 del tipo + datos normalizados
    tipo        VARCHAR(20)  NOT NULL,               -- receta | adaptar | reemplazo
    respuesta   TEXT         NOT NULL,               -- JSON
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_respuesta_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cada pedido a la IA, para los límites por día y el panel de administración.
CREATE TABLE uso_ia (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id       INT UNSIGNED NULL,
    tipo             VARCHAR(20)  NOT NULL,
    desde_cache      BOOLEAN      NOT NULL DEFAULT FALSE,
    ok               BOOLEAN      NOT NULL,
    error            VARCHAR(200) NULL,
    tokens_entrada   INT UNSIGNED NULL,
    tokens_salida    INT UNSIGNED NULL,
    milisegundos     INT UNSIGNED NULL,
    creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_uso_dia (creado_en),
    KEY idx_uso_usuario (usuario_id, creado_en),
    CONSTRAINT fk_uso_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reemplazos que sugirió la IA, para que la administración los pase a `sustitucion`.
CREATE TABLE sugerencia_reemplazo (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ingrediente_id  INT UNSIGNED NOT NULL,
    reemplazo_id    INT UNSIGNED NULL,               -- NULL si el reemplazo no está en el catálogo
    reemplazo_texto VARCHAR(80)  NOT NULL,
    ratio           DECIMAL(5,2) NULL,
    nota            VARCHAR(200) NULL,
    veces           INT UNSIGNED NOT NULL DEFAULT 1,
    pasada          BOOLEAN      NOT NULL DEFAULT FALSE,
    UNIQUE KEY uq_sugerencia (ingrediente_id, reemplazo_texto),
    CONSTRAINT fk_sugerencia_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id) ON DELETE CASCADE,
    CONSTRAINT fk_sugerencia_reemplazo FOREIGN KEY (reemplazo_id) REFERENCES ingrediente(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
