-- Registro de avisos ya enviados.
--
-- Sin esto, el proceso que revisa alertas mandaria el mismo correo en cada
-- pasada: las alertas siguen activas hasta que alguien arregla la planta, asi
-- que una averia de tres dias serian tres dias de correos cada diez minutos.
--
-- La clave unica es (usuario, proveedor, alerta): una alerta se avisa una vez
-- por usuario y ya esta.
CREATE TABLE IF NOT EXISTS avisos_enviados (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id   INT          NOT NULL,
  proveedor    VARCHAR(32)  NOT NULL,
  alerta_id    VARCHAR(191) NOT NULL,
  enviado_en   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_aviso (usuario_id, proveedor, alerta_id),
  KEY idx_aviso_fecha (enviado_en),

  CONSTRAINT fk_aviso_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (usuario_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
