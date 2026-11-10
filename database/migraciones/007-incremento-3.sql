-- Incremento 3: lista de compras, plan semanal e IA en las recetas (todas las tablas nuevas juntas).
-- Solo si ya tenías la base (si no: docker compose down -v && docker compose up -d).
--   mysql -u cooki -p cooki < database/migraciones/007-incremento-3.sql
-- En el hosting: phpMyAdmin → la base → Importar → este archivo. Se importa una sola vez
-- (si se importa de nuevo no pasa nada: usa CREATE TABLE IF NOT EXISTS).
--
-- Los datos con estructura (la receta de la IA, la caché, el estado para "Deshacer") van en TEXT con JSON adentro,
-- y las reglas entre columnas ("un ítem tiene ingrediente o texto") se validan en PHP: así la migración anda igual
-- en MariaDB (Docker) y en el MySQL del hosting (DEC-018).

-- Recetas que propuso la IA (va primero: la lista y el plan la referencian)
CREATE TABLE IF NOT EXISTS receta_ia (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT UNSIGNED NOT NULL,
    tipo         ENUM('nueva','adaptada') NOT NULL,
    receta_base  INT UNSIGNED NULL,
    titulo       VARCHAR(120) NOT NULL,
    contenido    TEXT         NOT NULL,
    guardada     BOOLEAN      NOT NULL DEFAULT FALSE,
    pasada_a     INT UNSIGNED NULL,
    creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_receta_ia_usuario (usuario_id, guardada),
    CONSTRAINT fk_receta_ia_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_receta_ia_base FOREIGN KEY (receta_base) REFERENCES receta(id) ON DELETE SET NULL,
    CONSTRAINT fk_receta_ia_pasada FOREIGN KEY (pasada_a) REFERENCES receta(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lista_compra_item (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED NOT NULL,
    ingrediente_id  INT UNSIGNED NULL,
    texto           VARCHAR(80)  NULL,
    cantidad        DECIMAL(9,2) NULL,
    unidad          VARCHAR(10)  NULL,
    receta_id       INT UNSIGNED NULL,
    receta_ia_id    INT UNSIGNED NULL,
    comprado_en     DATETIME     NULL,
    deshacer        TEXT         NULL,
    creado_en       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_lista_usuario (usuario_id, comprado_en),
    CONSTRAINT fk_lista_usuario FOREIGN KEY (usuario_id) REFERENCES usuario(id) ON DELETE CASCADE,
    CONSTRAINT fk_lista_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id) ON DELETE CASCADE,
    CONSTRAINT fk_lista_receta FOREIGN KEY (receta_id) REFERENCES receta(id) ON DELETE SET NULL,
    CONSTRAINT fk_lista_receta_ia FOREIGN KEY (receta_ia_id) REFERENCES receta_ia(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS plan_comida (
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

CREATE TABLE IF NOT EXISTS respuesta_ia (
    clave       CHAR(64)     PRIMARY KEY,
    tipo        VARCHAR(20)  NOT NULL,
    respuesta   TEXT         NOT NULL,
    creado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_respuesta_creado (creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS uso_ia (
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

CREATE TABLE IF NOT EXISTS sugerencia_reemplazo (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ingrediente_id  INT UNSIGNED NOT NULL,
    reemplazo_id    INT UNSIGNED NULL,
    reemplazo_texto VARCHAR(80)  NOT NULL,
    ratio           DECIMAL(5,2) NULL,
    nota            VARCHAR(200) NULL,
    veces           INT UNSIGNED NOT NULL DEFAULT 1,
    pasada          BOOLEAN      NOT NULL DEFAULT FALSE,
    UNIQUE KEY uq_sugerencia (ingrediente_id, reemplazo_texto),
    CONSTRAINT fk_sugerencia_ingrediente FOREIGN KEY (ingrediente_id) REFERENCES ingrediente(id) ON DELETE CASCADE,
    CONSTRAINT fk_sugerencia_reemplazo FOREIGN KEY (reemplazo_id) REFERENCES ingrediente(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
