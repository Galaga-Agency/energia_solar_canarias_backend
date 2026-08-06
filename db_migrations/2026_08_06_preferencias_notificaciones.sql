-- Preferencias de notificacion.
--
-- La app antigua tenia esta pantalla, pero guardaba todo en localStorage y el
-- boton de guardar solo lanzaba un alert(). Es decir: el usuario elegia sus
-- avisos y no llegaba nada a ningun sitio.
--
-- Una fila por usuario. Sin fila = valores por defecto, asi que no hace falta
-- sembrar nada para los usuarios que ya existen.
CREATE TABLE IF NOT EXISTS preferencias_notificaciones (
  usuario_id        INT          NOT NULL PRIMARY KEY,

  -- Interruptor general. Apagado silencia todo sin perder el detalle de abajo.
  activas           TINYINT(1)   NOT NULL DEFAULT 1,

  -- Canales.
  email             TINYINT(1)   NOT NULL DEFAULT 1,

  -- Severidad minima que genera aviso: 'critical', 'warning' o 'info'.
  severidad_minima  VARCHAR(16)  NOT NULL DEFAULT 'critical',

  -- Con que frecuencia se agrupan: 'immediate', 'daily' o 'weekly'.
  frecuencia        VARCHAR(16)  NOT NULL DEFAULT 'immediate',

  actualizado_en    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                 ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_prefnotif_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios (usuario_id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
