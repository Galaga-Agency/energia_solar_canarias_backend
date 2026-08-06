-- Gestion de claves de API.
--
-- La tabla solo guardaba usuario, clave y scope: no habia forma de saber
-- cuando se creo una clave, si se ha usado nunca, ni de revocarla sin borrar
-- la fila. Un usuario que sospecha que su clave se ha filtrado no tenia nada
-- que mirar ni nada que pulsar.
ALTER TABLE api_accesos
  ADD COLUMN nombre       VARCHAR(80)  NULL DEFAULT NULL AFTER api_scope,
  ADD COLUMN creado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER nombre,
  ADD COLUMN ultimo_uso   DATETIME     NULL DEFAULT NULL AFTER creado_en,
  ADD COLUMN revocado_en  DATETIME     NULL DEFAULT NULL AFTER ultimo_uso;

-- La verificacion filtra por clave Y por no revocada en cada peticion.
CREATE INDEX idx_api_accesos_activas ON api_accesos (api_key, revocado_en);
