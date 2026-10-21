-- =====================================================================
-- DigiSports · La firma autorizada pasa a ser configurable en Core
-- =====================================================================
-- Los recibos imprimían una imagen fija heredada del sistema anterior
-- (app/views/imagenes/rubricas/RubricaSC.jpg), que ni siquiera existe en
-- esta instalación. Pasa a ser un archivo que se carga desde Core, con
-- la misma regla de respaldo que el logo:
--
--     firma de la sede  ->  si no tiene, firma de la organización
--
-- Los archivos viven junto a los logos, en ds_core/assets/img/marca/.
--
-- Ejecutar con:
--   mysql -uroot --default-character-set=utf8mb4 digitech_barcelona \
--         -e "source ds_core/database/009_firma_documentos.sql"
-- =====================================================================

SELECT '--- ANTES ---' AS info;
SHOW COLUMNS FROM general_escuela LIKE '%firma%';
SHOW COLUMNS FROM general_sede    LIKE '%firma%';

-- ---------------------------------------------------------------------
-- 1. Firma de la organización
-- ---------------------------------------------------------------------
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'general_escuela'
        AND COLUMN_NAME  = 'escuela_firma') = 0,
    'ALTER TABLE general_escuela
       ADD COLUMN escuela_firma VARCHAR(100) NOT NULL DEFAULT ''''
       COMMENT ''Firma autorizada para recibos; archivo en ds_core/assets/img/marca/''
       AFTER escuela_logo',
    'SELECT ''general_escuela.escuela_firma ya existe'' AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 2. Firma propia de cada sede (opcional; si está vacía, hereda)
-- ---------------------------------------------------------------------
SET @sql = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME   = 'general_sede'
        AND COLUMN_NAME  = 'sede_firma') = 0,
    'ALTER TABLE general_sede
       ADD COLUMN sede_firma VARCHAR(100) NOT NULL DEFAULT ''''
       COMMENT ''Firma propia de la sede; vacia = usa la de la organizacion''
       AFTER sede_foto',
    'SELECT ''general_sede.sede_firma ya existe'' AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

-- ---------------------------------------------------------------------
-- 3. Comprobación
-- ---------------------------------------------------------------------
SELECT '--- DESPUES ---' AS info;
SHOW COLUMNS FROM general_escuela LIKE '%firma%';
SHOW COLUMNS FROM general_sede    LIKE '%firma%';

SELECT escuela_id, escuela_nombre, escuela_logo, escuela_firma
  FROM general_escuela;

SELECT sede_id, sede_nombre, sede_foto, sede_firma
  FROM general_sede
 ORDER BY sede_id;
