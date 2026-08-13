-- Los avisos por correo pasan a ser OPT-IN.
--
-- Hasta ahora "sin fila" significaba suscrito: el cron leia
-- COALESCE(p.activas, 1), asi que cualquier usuario dado de alta empezaba a
-- recibir correos de averias sin haberlos pedido nunca. Con la aplicacion a
-- punto de abrirse a clientes eso es exactamente lo que no se quiere.
--
-- El cron ya no escribe a quien no tenga fila (INNER JOIN), y la pantalla de
-- Ajustes muestra el interruptor apagado por defecto. Esto alinea la tabla con
-- las dos cosas: una fila creada por cualquier otra via tampoco activa nada.
ALTER TABLE preferencias_notificaciones
  MODIFY COLUMN activas TINYINT(1) NOT NULL DEFAULT 0;

-- Las filas que YA existen no se tocan: quien haya entrado en Ajustes y
-- activado los avisos los eligio a proposito, y apagarselos aqui seria
-- deshacer su decision sin avisar.
