-- =====================================================================
-- 086 · Alumnos adultos: inscripción propia, sin representante
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Hay sedes (FOREST EJECUTIVO en ADF Pedro Larrea) que reciben alumnos
-- mayores de edad. No tienen representante, pero el sistema da por hecho
-- que siempre hay uno: la factura electrónica sale a nombre del
-- representante, el recibo se envía a su correo y a su WhatsApp, y la
-- cobranza y los consentimientos se cuelgan de él. Hasta ahora se
-- resolvía a mano registrando al adulto como su propio representante con
-- un parentesco cualquiera (Padre, Hermano…).
--
--
-- LA SOLUCION: EL ADULTO ES SU PROPIO TITULAR
--
-- El proceso nuevo («Enlaces para adultos» y «Nuevo alumno adulto»)
-- registra al alumno y, con sus mismos datos, una ficha de titular en
-- alumno_representante con el parentesco 4TT y repre_factura = 'S'. Así
-- recibos, facturas, cobranza y consentimientos funcionan sin cambiar su
-- lógica: el comprador y el destinatario son el propio alumno. El flujo
-- de los menores no cambia en nada.
--
--
-- QUE HACE ESTA MIGRACION
--
--   1. general_sede.sede_adultos (S/N, por defecto N): qué sedes admiten
--      alumnos adultos. Se marca en Core › Sedes; aquí sólo FOREST
--      EJECUTIVO, que en las otras escuelas no existe (0 filas).
--   2. Parentesco 4TT «Titular (el propio alumno)», INACTIVO a propósito:
--      el formulario público de menores lista sólo los activos, así que no
--      lo ofrece; los selects del panel no filtran por estado, así que una
--      ficha de titular conserva su valor al editarse.
--   3. Menús en «Inscripción Online»: «Enlaces para adultos» y «Nuevo
--      alumno adulto».
--   4. Permisos: a los roles que ya emiten enlaces (inscripcionEnlace) y a
--      los que ya dan de alta alumnos (alumnoList con crear), con lo mismo
--      que tienen allí. Una pantalla nueva sin permisos sería «no me deja
--      entrar» sin pista de por qué.
--
--
-- SE PUEDE REPETIR
--
-- La columna se agrega sólo si falta; los INSERT llevan NOT EXISTS.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Sedes que admiten alumnos adultos
-- ---------------------------------------------------------------------
SET @falta := (SELECT COUNT(*) = 0 FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME   = 'general_sede'
                  AND COLUMN_NAME  = 'sede_adultos');
SET @sql := IF(@falta,
    "ALTER TABLE general_sede ADD COLUMN sede_adultos CHAR(1) NOT NULL DEFAULT 'N' COMMENT 'S: admite alumnos adultos (enlace e inscripción propios)'",
    'SELECT ''sede_adultos ya existe'' AS info');
PREPARE st FROM @sql; EXECUTE st; DEALLOCATE PREPARE st;

UPDATE general_sede SET sede_adultos = 'S'
 WHERE sede_nombre = 'FOREST EJECUTIVO' AND sede_adultos <> 'S';

-- ---------------------------------------------------------------------
-- 2. Parentesco «Titular (el propio alumno)»
-- ---------------------------------------------------------------------
INSERT INTO general_tabla_catalogo
       (catalogo_valor, catalogo_tablaid, catalogo_descripcion, catalogo_estado)
SELECT '4TT', T.tabla_id, 'Titular (el propio alumno)', 'I'
  FROM general_tabla T
 WHERE T.tabla_nombre = 'parentesco'
   AND NOT EXISTS (SELECT 1 FROM (SELECT catalogo_valor FROM general_tabla_catalogo) C
                    WHERE C.catalogo_valor = '4TT')
 LIMIT 1;

-- ---------------------------------------------------------------------
-- 3. Menús
-- ---------------------------------------------------------------------
SET @g_inscripcion = (SELECT menu_id FROM seguridad_menu
                       WHERE menu_modulo = 'basketball'
                         AND menu_nombre = 'Inscripción Online'
                         AND menu_padreid = 0 LIMIT 1);

INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
        menu_vista, menu_icono, menu_estado)
SELECT * FROM (
    SELECT 'basketball' m, 'Enlaces para adultos' n, 4 o, @g_inscripcion p, 'N' h,
           'inscripcionAdultoEnlace' v, 'nav-icon fas fa-user-tie' i, 'A' e
    UNION ALL
    SELECT 'basketball', 'Nuevo alumno adulto', 5, @g_inscripcion, 'N',
           'alumnoAdultoNew', 'nav-icon fas fa-user-plus', 'A'
) nuevos
 WHERE @g_inscripcion IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM (SELECT * FROM seguridad_menu) M
                    WHERE M.menu_modulo = 'basketball' AND M.menu_vista = nuevos.v);

-- ---------------------------------------------------------------------
-- 4. Permisos
-- ---------------------------------------------------------------------
-- «Enlaces para adultos»: lo mismo que cada rol tiene sobre «Generar enlaces».
INSERT INTO seguridad_permiso
    (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear,
     permiso_editar, permiso_eliminar, permiso_exportar, permiso_estado)
SELECT p.permiso_rolid, destino.menu_id, p.permiso_ver, p.permiso_crear,
       p.permiso_editar, p.permiso_eliminar, p.permiso_exportar, 'A'
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 CROSS JOIN (SELECT menu_id FROM seguridad_menu
              WHERE menu_modulo = 'basketball'
                AND menu_vista  = 'inscripcionAdultoEnlace' LIMIT 1) AS destino
 WHERE m.menu_modulo = 'basketball'
   AND m.menu_vista  = 'inscripcionEnlace'
   AND p.permiso_estado = 'A'
   AND NOT EXISTS (SELECT 1 FROM (SELECT permiso_rolid, permiso_menuid FROM seguridad_permiso) ya
                    WHERE ya.permiso_rolid = p.permiso_rolid
                      AND ya.permiso_menuid = destino.menu_id);

-- «Nuevo alumno adulto»: a quien ya puede crear alumnos (alumnoList, crear = S).
-- Ver y crear; nada más, porque la pantalla sólo da de alta.
INSERT INTO seguridad_permiso
    (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear,
     permiso_editar, permiso_eliminar, permiso_exportar, permiso_estado)
SELECT p.permiso_rolid, destino.menu_id, 'S', 'S', 'N', 'N', 'N', 'A'
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
 CROSS JOIN (SELECT menu_id FROM seguridad_menu
              WHERE menu_modulo = 'basketball'
                AND menu_vista  = 'alumnoAdultoNew' LIMIT 1) AS destino
 WHERE m.menu_modulo = 'basketball'
   AND m.menu_vista  = 'alumnoList'
   AND p.permiso_crear  = 'S'
   AND p.permiso_estado = 'A'
   AND NOT EXISTS (SELECT 1 FROM (SELECT permiso_rolid, permiso_menuid FROM seguridad_permiso) ya
                    WHERE ya.permiso_rolid = p.permiso_rolid
                      AND ya.permiso_menuid = destino.menu_id);

-- ---------------------------------------------------------------------
-- Comprobación
-- ---------------------------------------------------------------------
SELECT sede_id, sede_nombre, sede_adultos FROM general_sede WHERE sede_adultos = 'S';

SELECT r.rol_nombre, m.menu_nombre, p.permiso_ver AS ver, p.permiso_crear AS crear
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
  JOIN seguridad_rol  r ON r.rol_id  = p.permiso_rolid
 WHERE m.menu_vista IN ('inscripcionAdultoEnlace', 'alumnoAdultoNew')
 ORDER BY m.menu_nombre, r.rol_id;
