-- Metodos de acceso que tiene activados cada usuario.
--
-- Hasta ahora solo habia una forma de entrar en la web: el enlace que llega por
-- correo. Se añade usuario + contraseña, y cada usuario decide en Ajustes cuales
-- quiere tener encendidos. Por defecto: enlace SI, contraseña NO.
--
-- Una fila por (usuario, metodo). Sin fila = valor por defecto del metodo
-- (MetodosAcceso::METODOS), asi que no hay que sembrar nada para los usuarios
-- que ya existen ni para los nuevos, y un metodo que se añada mañana (totp, sms,
-- google, microsoft...) no necesita migrar filas: basta con declararlo en PHP.
CREATE TABLE IF NOT EXISTS usuarios_metodos_acceso (
  usuario_id      INT          NOT NULL,

  -- 'enlace' | 'password'. Lista blanca en MetodosAcceso::METODOS.
  metodo          VARCHAR(20)  NOT NULL,

  activo          TINYINT(1)   NOT NULL,

  actualizado_en  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                               ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (usuario_id, metodo),

  CONSTRAINT fk_metodos_acceso_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (usuario_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
