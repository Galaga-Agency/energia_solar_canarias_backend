-- Correo de respaldo.
--
-- Un usuario que pierde el acceso a su correo principal pierde la cuenta
-- entera: el login es un enlace magico y no hay contrasena que recuperar.
-- Con un segundo correo puede seguir entrando.
--
-- No es UNIQUE: dos personas de la misma empresa pueden compartir un buzon de
-- respaldo, y forzar unicidad convertiria eso en un error incomprensible.
ALTER TABLE usuarios
  ADD COLUMN email_respaldo VARCHAR(255) NULL DEFAULT NULL AFTER email;

-- El login busca por cualquiera de los dos, asi que la columna necesita indice.
CREATE INDEX idx_usuarios_email_respaldo ON usuarios (email_respaldo);
