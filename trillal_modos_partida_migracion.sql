-- TRILLAL / Actualización V1.1: partidas NORMAL y RÁPIDA.
-- Importar UNA SOLA VEZ desde phpMyAdmin en la base EXISTENTE.
-- HACER RESPALDO antes de ejecutar. No importa ni borra registros.
-- No necesita VISTAS ni TRIGGERS.
-- Si la columna tipo_partida ya existe, NO repitas este ALTER TABLE.
ALTER TABLE salas
  ADD COLUMN tipo_partida ENUM('sala','normal','rapida') NOT NULL DEFAULT 'sala' AFTER id_trivia;

-- Comprobación: debe aparecer tipo_partida con valor por defecto 'sala'.
SHOW COLUMNS FROM salas LIKE 'tipo_partida';

-- Esta actualización NO elimina índices de intentos. Si el diagnóstico detecta
-- uq_intentos_numero, realiza un respaldo y revisa la migración v1 a v2.
-- Únicamente si ese índice antiguo sigue presente tras la migración, podría
-- necesitarse: ALTER TABLE intentos DROP INDEX uq_intentos_numero;
