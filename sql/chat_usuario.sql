-- Usuario de SOLO LECTURA para el chat (ejecutar como root en phpMyAdmin o consola).
-- Cambia la clave y defínela como variable de entorno AROMA_CHAT_DB_PASS (usuario: AROMA_CHAT_DB_USER=aroma_chat).
CREATE USER IF NOT EXISTS 'aroma_chat'@'localhost' IDENTIFIED BY 'CAMBIAR_ESTA_CLAVE';
CREATE USER IF NOT EXISTS 'aroma_chat'@'127.0.0.1' IDENTIFIED BY 'CAMBIAR_ESTA_CLAVE';

GRANT SELECT ON aroma_se.vw_lote_estado        TO 'aroma_chat'@'localhost';
GRANT SELECT ON aroma_se.vw_lote_estado        TO 'aroma_chat'@'127.0.0.1';
GRANT SELECT ON aroma_se.vw_cobertura_insumo   TO 'aroma_chat'@'localhost';
GRANT SELECT ON aroma_se.vw_cobertura_insumo   TO 'aroma_chat'@'127.0.0.1';
GRANT SELECT ON aroma_se.vw_porciones_posibles TO 'aroma_chat'@'localhost';
GRANT SELECT ON aroma_se.vw_porciones_posibles TO 'aroma_chat'@'127.0.0.1';
GRANT SELECT ON aroma_se.insumo                TO 'aroma_chat'@'localhost';
GRANT SELECT ON aroma_se.insumo                TO 'aroma_chat'@'127.0.0.1';
GRANT SELECT ON aroma_se.plato                 TO 'aroma_chat'@'localhost';
GRANT SELECT ON aroma_se.plato                 TO 'aroma_chat'@'127.0.0.1';
GRANT SELECT ON aroma_se.receta                TO 'aroma_chat'@'localhost';
GRANT SELECT ON aroma_se.receta                TO 'aroma_chat'@'127.0.0.1';
GRANT SELECT ON aroma_se.regla                 TO 'aroma_chat'@'localhost';
GRANT SELECT ON aroma_se.regla                 TO 'aroma_chat'@'127.0.0.1';
GRANT SELECT ON aroma_se.lote                  TO 'aroma_chat'@'localhost';
GRANT SELECT ON aroma_se.lote                  TO 'aroma_chat'@'127.0.0.1';
GRANT SELECT ON aroma_se.decision              TO 'aroma_chat'@'localhost';
GRANT SELECT ON aroma_se.decision              TO 'aroma_chat'@'127.0.0.1';
-- consulta: sin la columna `observaciones` (texto libre del personal).
GRANT SELECT (consulta_id, fecha_hora, tipo, fecha_base) ON aroma_se.consulta TO 'aroma_chat'@'localhost';
GRANT SELECT (consulta_id, fecha_hora, tipo, fecha_base) ON aroma_se.consulta TO 'aroma_chat'@'127.0.0.1';
FLUSH PRIVILEGES;
