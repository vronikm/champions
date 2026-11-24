-- =====================================================================
-- 093 · Insights: reporte «Ingresos del mes»
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- La escuela armaba a mano, en Excel, el reporte mensual de lo cobrado a
-- los alumnos: un bloque por rubro (campeonatos, inscripciones, kits,
-- uniformes) y las pensiones por sede y lugar de entrenamiento, con cada
-- cobro en la columna de su forma de pago (efectivo, Banco de Loja, Banco
-- Pichincha). Ahora lo da Insights › Centro de reportes › Ingresos del mes.
--
--
-- QUE HACE ESTA MIGRACION
--
--   1. Registra la vista «ingresosMes» en el menú de Insights como OCULTA
--      ('O'), igual que Exportar (052): con DS_PERMISOS_ESTRICTOS una vista
--      que no está en seguridad_menu se deniega, pero ésta no va en el menú
--      lateral; se entra desde el Centro de reportes.
--
--   2. Concede el permiso a los roles que ya ven «Transacciones», que es la
--      pantalla equivalente: las dos muestran personas identificadas.
--        ver       = el que el rol tiene en «transacciones»
--        exportar  = el que el rol tiene en «financiero» (sacar a Excel o
--                    PDF lo que ya puede exportar en el consolidado)
--        crear, editar, eliminar = N (Insights no escribe datos)
--
--
-- SE PUEDE REPETIR
--
-- El menú se crea sólo si falta y los permisos llevan NOT EXISTS: aplicarla
-- otra vez no duplica nada ni pisa lo que se haya cambiado en Core.
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO seguridad_menu
    (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'insights', 'Ingresos del mes', 98, 0, 'N', 'ingresosMes', 'nav-icon fas fa-cash-register', 'O'
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu
                    WHERE menu_modulo = 'insights' AND menu_vista = 'ingresosMes');

INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear,
        permiso_editar, permiso_eliminar, permiso_exportar, permiso_estado)
SELECT pt.permiso_rolid,
       m.menu_id,
       pt.permiso_ver,
       'N',
       'N',
       'N',
       IFNULL((SELECT pf.permiso_exportar
                 FROM seguridad_permiso pf
                 JOIN seguridad_menu mf ON mf.menu_id = pf.permiso_menuid
                WHERE mf.menu_modulo = 'insights' AND mf.menu_vista = 'financiero'
                  AND pf.permiso_rolid = pt.permiso_rolid
                LIMIT 1), 'N'),
       pt.permiso_estado
  FROM seguridad_permiso pt
  JOIN seguridad_menu mt ON mt.menu_id = pt.permiso_menuid
                        AND mt.menu_modulo = 'insights' AND mt.menu_vista = 'transacciones'
  JOIN seguridad_menu m  ON m.menu_modulo = 'insights' AND m.menu_vista = 'ingresosMes'
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_permiso p
                    WHERE p.permiso_rolid = pt.permiso_rolid
                      AND p.permiso_menuid = m.menu_id);

-- Comprobación
SELECT r.rol_nombre, p.permiso_ver AS ver, p.permiso_exportar AS exportar, p.permiso_estado AS estado
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
  JOIN seguridad_rol  r ON r.rol_id  = p.permiso_rolid
 WHERE m.menu_modulo = 'insights' AND m.menu_vista = 'ingresosMes';
