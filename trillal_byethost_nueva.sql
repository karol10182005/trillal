-- ==============================================================
-- TRILLAL - Base de datos v2 para hosting compartido: SOLO TABLAS
-- Proyecto: comunidad de trivias con PHP, HTML, CSS y JavaScript
-- Charset: utf8mb4 (soporta tildes, simbolos y emojis)
-- IMPORTANTE: Selecciona la base de datos creada en tu panel de ByetHost antes de importar.
-- Esta version NO crea bases de datos, VISTAS, TRIGGERS ni PROCEDIMIENTOS.
-- Las contrasenas se guardan como HASH generado en PHP.
-- PHP valida las reglas y entrega los puntos de Cultura en transacciones.
-- ============================================================== 


-- 1. Cuentas: registro, inicio de sesion, perfil y saldo de Cultura.
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(50) NOT NULL,
    correo VARCHAR(150) NOT NULL,
    contrasena_hash VARCHAR(255) NOT NULL,
    avatar_url VARCHAR(500) NULL,
    cultura INT UNSIGNED NOT NULL DEFAULT 0,
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

-- 3. Una sola trivia creada POR CUENTA; se puede jugar en multiples salas.
-- libre y concurso son publicas; privada exige clave de entrada.
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
    UNIQUE KEY uq_trivias_una_por_cuenta (id_creador),
    CONSTRAINT fk_trivias_creador FOREIGN KEY (id_creador)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_trivias_categoria FOREIGN KEY (id_categoria)
        REFERENCES categorias (id_categoria) ON DELETE RESTRICT
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
        REFERENCES trivias (id_trivia) ON DELETE CASCADE
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
        REFERENCES preguntas (id_pregunta) ON DELETE CASCADE
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

-- 7. Salas: cada encuentro sincronizado de una trivia (cupo y horario).
-- Todos los DATETIME se guardan en UTC; fecha_competencia corresponde
-- al dia local (p. ej. America/Mexico_City) del inicio programado.
-- Publicas compiten por los premios del DIA por trivia, no por cada sala.
-- Privadas compiten por los premios de CADA SALA finalizada.
CREATE TABLE IF NOT EXISTS salas (
    id_sala BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_trivia INT UNSIGNED NOT NULL,
    inscripciones_abren_en DATETIME NOT NULL,
    inscripciones_cierran_en DATETIME NOT NULL,
    inicio_programado_en DATETIME NOT NULL,
    inicio_real_en DATETIME NULL,
    finalizada_en DATETIME NULL,
    fecha_competencia DATE NOT NULL,
    duracion_segundos INT UNSIGNED NOT NULL,
    cupo_maximo SMALLINT UNSIGNED NOT NULL,
    estado ENUM('programada', 'en_curso', 'finalizada', 'cancelada')
        NOT NULL DEFAULT 'programada',
    creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_salas_id_trivia (id_sala, id_trivia),
    INDEX idx_salas_trivia_fecha (id_trivia, fecha_competencia, estado),
    INDEX idx_salas_agenda (estado, inicio_programado_en),
    CONSTRAINT fk_salas_trivia FOREIGN KEY (id_trivia)
        REFERENCES trivias (id_trivia) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 8. Participantes registrados en una sala. El cupo se valida con
-- transaccion y bloqueo de la fila de salas, NO solo con un COUNT suelto.
CREATE TABLE IF NOT EXISTS inscripciones_sala (
    id_sala BIGINT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    estado ENUM('inscrito', 'retirado') NOT NULL DEFAULT 'inscrito',
    inscrito_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id_sala, id_usuario),
    INDEX idx_inscritos_sala_estado (id_sala, estado),
    CONSTRAINT fk_inscripciones_sala FOREIGN KEY (id_sala)
        REFERENCES salas (id_sala) ON DELETE RESTRICT,
    CONSTRAINT fk_inscripciones_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 9. Un intento es una participacion en una sala (una por usuario/sala).
CREATE TABLE IF NOT EXISTS intentos (
    id_intento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_trivia INT UNSIGNED NOT NULL,
    id_sala BIGINT UNSIGNED NULL, -- NULL solo para intentos antiguos (migracion v1)
    id_usuario INT UNSIGNED NOT NULL,
    numero_intento SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    estado ENUM('en_curso', 'finalizado', 'abandonado')
        NOT NULL DEFAULT 'en_curso',
    puntuacion_total INT UNSIGNED NOT NULL DEFAULT 0,
    respuestas_correctas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    tiempo_total_segundos INT UNSIGNED NULL,
    iniciado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_en DATETIME NULL,
    UNIQUE KEY uq_intentos_sala_usuario (id_sala, id_usuario),
    INDEX idx_intentos_ranking
        (id_trivia, estado, puntuacion_total, tiempo_total_segundos),
    INDEX idx_intentos_usuario (id_usuario, iniciado_en),
    CONSTRAINT fk_intentos_trivia FOREIGN KEY (id_trivia)
        REFERENCES trivias (id_trivia) ON DELETE RESTRICT,
    CONSTRAINT fk_intentos_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_intentos_sala_trivia FOREIGN KEY (id_sala, id_trivia)
        REFERENCES salas (id_sala, id_trivia) ON DELETE RESTRICT,
    CONSTRAINT fk_intentos_inscripcion FOREIGN KEY (id_sala, id_usuario)
        REFERENCES inscripciones_sala (id_sala, id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 10. Respuesta elegida en cada pregunta de un intento.
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
        REFERENCES opciones (id_opcion, id_pregunta) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 11. Cierres que fijan cuando se repartieron premios de Cultura.
-- Una trivia PUBLICA solo se premia una vez por dia local.
-- Una sala PRIVADA solo se premia una vez por sesion (sin limite diario).
-- No cerrar el dia publico hasta que termine en su zona horaria y
-- esten finalizadas todas las salas de ese dia.
CREATE TABLE IF NOT EXISTS cierres_cultura (
    id_cierre BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('publica_diaria', 'privada_sala') NOT NULL,
    id_trivia INT UNSIGNED NOT NULL,
    id_sala BIGINT UNSIGNED NULL,
    fecha_competencia DATE NULL,
    cerrado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cierre_dia_publico (id_trivia, fecha_competencia),
    UNIQUE KEY uq_cierre_sala_privada (id_sala),
    CONSTRAINT fk_cierre_trivia FOREIGN KEY (id_trivia)
        REFERENCES trivias (id_trivia) ON DELETE RESTRICT,
    CONSTRAINT fk_cierre_sala_trivia FOREIGN KEY (id_sala, id_trivia)
        REFERENCES salas (id_sala, id_trivia) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 12. Historial inmutable de premios concedidos.
-- Cada cierre tiene como maximo un ganador por puesto y un premio por usuario.
CREATE TABLE IF NOT EXISTS movimientos_cultura (
    id_movimiento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cierre BIGINT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    puesto TINYINT UNSIGNED NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    otorgado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_movimiento_cierre_usuario (id_cierre, id_usuario),
    UNIQUE KEY uq_movimiento_cierre_puesto (id_cierre, puesto),
    INDEX idx_movimientos_usuario (id_usuario, otorgado_en),
    CONSTRAINT fk_movimiento_cierre FOREIGN KEY (id_cierre)
        REFERENCES cierres_cultura (id_cierre) ON DELETE RESTRICT,
    CONSTRAINT fk_movimiento_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuarios (id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB;


-- CATEGORIAS INICIALES
INSERT INTO categorias (nombre, descripcion) VALUES
  ('Ajedrez', 'Preguntas sobre ajedrez'),
  ('Cultura general', 'Conocimientos variados'),
  ('Videojuegos', 'Juegos, personajes e historias'),
  ('Ciencia', 'Ciencia y tecnologia')
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre);

-- REGLAS DEL BACKEND (PHP):
-- 1. cultura se inicia en 0 y solo PHP modifica su saldo.
-- 2. Al premiar, hacer INSERT en movimientos_cultura Y
--    UPDATE usuarios SET cultura = cultura + ? WHERE id_usuario = ?
--    dentro de UNA MISMA TRANSACCION (COMMIT / ROLLBACK).
-- 3. El UNIQUE (id_trivia,fecha_competencia) impide doble cierre publico/dia.
--    El UNIQUE (id_sala) impide doble cierre privado/sala.
-- 4. En publicas: premios por la mejor partida de cada jugador
--    en la trivia y dia local, una vez al cerrar el dia.
-- 5. En privadas: premios por sala finalizada, sin limite diario.
-- 6. Premios: 1er puesto = 25; 2do = 20; 3ro = 20.
-- 7. Inicios y finales de salas, limites de tiempo, cupo y
--    respuestas correctas deben verificarse en PHP (no solo JavaScript).
-- 8. Para horarios: guardar DATETIME UTC y fecha_competencia
--    usando la zona horaria local definida para los concursos.
-- 9. La creacion de nuevas salas y el cierre de Cultura deben usar
--    bloqueo de la fila de trivias para no aceptar salas antiguas
--    tras el cierre de ese dia.
