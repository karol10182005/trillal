-- ==============================================================
-- TRILLAL - Base de datos inicial (MySQL 8.0.16+ / MariaDB 10.5+)
-- Proyecto: comunidad de trivias con PHP, HTML, CSS y JavaScript
-- Charset: utf8mb4 (soporta tildes, simbolos y emojis)
-- IMPORTANTE: las contrasenas se guardan como HASH generado en PHP.
-- ============================================================== 

CREATE DATABASE IF NOT EXISTS trillal
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE trillal;

-- 1. Cuentas: registro, inicio de sesion y perfil.
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    contrasena_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) NULL,
    rol ENUM('usuario', 'administrador') NOT NULL DEFAULT 'usuario',
    estado ENUM('activo', 'suspendido') NOT NULL DEFAULT 'activo',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_nombre (nombre_usuario),
    UNIQUE KEY uq_usuarios_correo (correo)
) ENGINE=InnoDB;

-- 2. Temas que pueden aparecer en las tarjetas (p. ej., Ajedrez).
CREATE TABLE IF NOT EXISTS categorias (
    id_categoria SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    descripcion VARCHAR(255) NULL,
    UNIQUE KEY uq_categorias_nombre (nombre)
) ENGINE=InnoDB;

-- 3. Trivias creadas por usuarios: libre, privada o concurso.
CREATE TABLE IF NOT EXISTS trivias (
    id_trivia INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_creador INT UNSIGNED NOT NULL,
    id_categoria SMALLINT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    modalidad ENUM('libre', 'privada', 'concurso') NOT NULL DEFAULT 'libre',
    contrasena_hash VARCHAR(255) NULL,
    tiempo_limite_segundos INT UNSIGNED NULL,
    max_intentos SMALLINT UNSIGNED NULL,
    estado ENUM('borrador', 'publicada', 'archivada') NOT NULL DEFAULT 'borrador',
    creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    publicada_en DATETIME NULL,
    INDEX idx_trivias_catalogo (estado, modalidad, id_categoria),
    INDEX idx_trivias_creador (id_creador),
    CONSTRAINT fk_trivias_creador FOREIGN KEY (id_creador)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_trivias_categoria FOREIGN KEY (id_categoria)
        REFERENCES categorias (id_categoria) ON DELETE RESTRICT,
    CONSTRAINT ck_trivias_limite CHECK
        (tiempo_limite_segundos IS NULL OR tiempo_limite_segundos > 0),
    CONSTRAINT ck_trivias_intentos CHECK
        (max_intentos IS NULL OR max_intentos > 0),
    CONSTRAINT ck_trivias_privadas CHECK
        (modalidad <> 'privada' OR estado <> 'publicada' OR contrasena_hash IS NOT NULL)
) ENGINE=InnoDB;

-- 4. Preguntas que forman cada trivia, en el orden del editor.
CREATE TABLE IF NOT EXISTS preguntas (
    id_pregunta INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_trivia INT UNSIGNED NOT NULL,
    enunciado TEXT NOT NULL,
    imagen_url VARCHAR(500) NULL,
    orden SMALLINT UNSIGNED NOT NULL,
    puntos SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    tiempo_limite_segundos INT UNSIGNED NULL,
    UNIQUE KEY uq_preguntas_orden (id_trivia, orden),
    INDEX idx_preguntas_trivia (id_trivia),
    CONSTRAINT fk_preguntas_trivia FOREIGN KEY (id_trivia)
        REFERENCES trivias (id_trivia) ON DELETE CASCADE,
    CONSTRAINT ck_preguntas_orden CHECK (orden > 0),
    CONSTRAINT ck_preguntas_puntos CHECK (puntos > 0),
    CONSTRAINT ck_preguntas_tiempo CHECK
        (tiempo_limite_segundos IS NULL OR tiempo_limite_segundos > 0)
) ENGINE=InnoDB;

-- 5. Opciones de respuesta: texto, imagen o ambos.
-- Cada pregunta debe tener UNA opcion correcta: validar al publicar en PHP.
CREATE TABLE IF NOT EXISTS opciones (
    id_opcion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_pregunta INT UNSIGNED NOT NULL,
    texto VARCHAR(500) NULL,
    imagen_url VARCHAR(500) NULL,
    es_correcta TINYINT(1) NOT NULL DEFAULT 0,
    orden TINYINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_opciones_orden (id_pregunta, orden),
    UNIQUE KEY uq_opciones_id_pregunta (id_opcion, id_pregunta),
    CONSTRAINT fk_opciones_pregunta FOREIGN KEY (id_pregunta)
        REFERENCES preguntas (id_pregunta) ON DELETE CASCADE,
    CONSTRAINT ck_opciones_contenido CHECK
        (texto IS NOT NULL OR imagen_url IS NOT NULL),
    CONSTRAINT ck_opciones_correcta CHECK (es_correcta IN (0, 1)),
    CONSTRAINT ck_opciones_orden CHECK (orden > 0)
) ENGINE=InnoDB;

-- 6. Acceso autorizado despues de introducir la contrasena de una trivia.
-- Si la contrasena cambia, eliminar los accesos de esa trivia.
CREATE TABLE IF NOT EXISTS accesos_privados (
    id_trivia INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    autorizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_trivia, id_usuario),
    CONSTRAINT fk_accesos_trivia FOREIGN KEY (id_trivia)
        REFERENCES trivias (id_trivia) ON DELETE CASCADE,
    CONSTRAINT fk_accesos_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 7. Un intento es una partida individual de una trivia.
CREATE TABLE IF NOT EXISTS intentos (
    id_intento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_trivia INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    numero_intento SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    estado ENUM('en_curso', 'finalizado', 'abandonado')
        NOT NULL DEFAULT 'en_curso',
    puntuacion_total INT UNSIGNED NOT NULL DEFAULT 0,
    respuestas_correctas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    tiempo_total_segundos INT UNSIGNED NULL,
    iniciado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_en DATETIME NULL,
    UNIQUE KEY uq_intentos_numero (id_trivia, id_usuario, numero_intento),
    INDEX idx_intentos_ranking
        (id_trivia, estado, puntuacion_total, tiempo_total_segundos),
    INDEX idx_intentos_usuario (id_usuario, iniciado_en),
    CONSTRAINT fk_intentos_trivia FOREIGN KEY (id_trivia)
        REFERENCES trivias (id_trivia) ON DELETE RESTRICT,
    CONSTRAINT fk_intentos_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT ck_intentos_numero CHECK (numero_intento > 0),
    CONSTRAINT ck_intentos_finalizacion CHECK
        (estado <> 'finalizado' OR finalizado_en IS NOT NULL)
) ENGINE=InnoDB;

-- 8. Respuesta elegida en cada pregunta de un intento.
-- FK compuesta garantiza que la opcion pertenezca a esa pregunta.
CREATE TABLE IF NOT EXISTS respuestas (
    id_respuesta BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_intento BIGINT UNSIGNED NOT NULL,
    id_pregunta INT UNSIGNED NOT NULL,
    id_opcion INT UNSIGNED NOT NULL,
    es_correcta TINYINT(1) NOT NULL,
    puntos_obtenidos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    tiempo_respuesta_segundos INT UNSIGNED NULL,
    respondida_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_respuesta_por_pregunta (id_intento, id_pregunta),
    INDEX idx_respuestas_opcion_pregunta (id_opcion, id_pregunta),
    CONSTRAINT fk_respuestas_intento FOREIGN KEY (id_intento)
        REFERENCES intentos (id_intento) ON DELETE CASCADE,
    CONSTRAINT fk_respuestas_pregunta FOREIGN KEY (id_pregunta)
        REFERENCES preguntas (id_pregunta) ON DELETE RESTRICT,
    CONSTRAINT fk_respuestas_opcion_pregunta
        FOREIGN KEY (id_opcion, id_pregunta)
        REFERENCES opciones (id_opcion, id_pregunta) ON DELETE RESTRICT,
    CONSTRAINT ck_respuestas_correcta CHECK (es_correcta IN (0, 1))
) ENGINE=InnoDB;

-- 9. Vista: la mejor partida finalizada por usuario en cada trivia.
-- Se calcula el puesto, sin guardarlo como numero fijo.
CREATE OR REPLACE VIEW vista_clasificacion AS
SELECT
    mejores.id_trivia,
    mejores.id_usuario,
    mejores.id_intento,
    mejores.puntuacion_total,
    mejores.respuestas_correctas,
    mejores.tiempo_total_segundos,
    ROW_NUMBER() OVER (
        PARTITION BY mejores.id_trivia
        ORDER BY mejores.puntuacion_total DESC,
                 COALESCE(mejores.tiempo_total_segundos, 4294967295) ASC,
                 mejores.finalizado_en ASC,
                 mejores.id_intento ASC
    ) AS puesto
FROM (
    SELECT
        i.*,
        ROW_NUMBER() OVER (
            PARTITION BY i.id_trivia, i.id_usuario
            ORDER BY i.puntuacion_total DESC,
                     COALESCE(i.tiempo_total_segundos, 4294967295) ASC,
                     i.finalizado_en ASC,
                     i.id_intento ASC
        ) AS numero_mejor
    FROM intentos AS i
    WHERE i.estado = 'finalizado'
) AS mejores
WHERE mejores.numero_mejor = 1;

-- Categorias iniciales de ejemplo. Puedes ampliarlas desde el panel.
INSERT INTO categorias (nombre, descripcion) VALUES
    ('Ajedrez', 'Preguntas sobre piezas, reglas y partidas de ajedrez'),
    ('Cultura general', 'Preguntas sobre conocimientos variados'),
    ('Videojuegos', 'Preguntas de juegos, personajes e historias'),
    ('Ciencia', 'Preguntas sobre ciencia y tecnologia')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- ==============================================================
-- EJEMPLOS DE CONSULTAS (descomentar cuando tengas datos)
-- ==============================================================
-- Ver trivias publicadas para la pantalla Jugar:
-- SELECT t.id_trivia, t.titulo, t.modalidad, c.nombre AS categoria,
--        u.nombre_usuario AS creador
-- FROM trivias AS t
-- JOIN categorias AS c ON c.id_categoria = t.id_categoria
-- JOIN usuarios AS u ON u.id_usuario = t.id_creador
-- WHERE t.estado = 'publicada'
-- ORDER BY t.publicada_en DESC;
--
-- Ver la clasificacion de la trivia numero 1:
-- SELECT v.puesto, u.nombre_usuario, v.puntuacion_total,
--        v.respuestas_correctas, v.tiempo_total_segundos
-- FROM vista_clasificacion AS v
-- JOIN usuarios AS u ON u.id_usuario = v.id_usuario
-- WHERE v.id_trivia = 1
-- ORDER BY v.puesto ASC;
--
-- NOTAS PARA PHP:
-- * Para usuarios y trivias privadas: password_hash() y password_verify().
-- * Nunca enviar al navegador es_correcta ni contrasena_hash.
-- * Corregir respuestas y validar el tiempo en el servidor, no en JavaScript.
-- * Antes de publicar: validar >= 1 pregunta y exactamente 1 opcion
--   correcta por pregunta (y >= 2 opciones por pregunta).
-- * Confirmar que la pregunta pertenezca a la trivia del intento.
-- * Respetar max_intentos y acceso privado antes de crear un intento.
-- * No editar preguntas/opciones de trivias con intentos registrados:
--   archivar y duplicar la trivia para conservar el historial.
