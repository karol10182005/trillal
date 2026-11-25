-- TRILLAL: permitir hasta 5 trivias por usuario (ByetHost / MySQL / MariaDB).
-- Haz copia de seguridad e importa este archivo UNA SOLA VEZ, ANTES de subir los PHP nuevos.
-- Mantén el índice no único para conservar el rendimiento y el soporte de la clave foránea.
ALTER TABLE trivias ADD INDEX idx_trivias_creador (id_creador);
ALTER TABLE trivias DROP INDEX uq_trivias_una_por_cuenta;
-- El máximo de 5 se valida en PHP, dentro de transacción y bloqueo del registro de usuario.
-- Para revisar: SHOW INDEX FROM trivias;
