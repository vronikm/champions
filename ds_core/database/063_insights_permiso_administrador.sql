-- =====================================================================
-- 063 · Insights para el rol «Administrador»
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- Insights tenia once entradas de menu y CERO permisos concedidos. Solo lo
-- veia el superadministrador, que se los salta todos. Para cualquier otro
-- rol el modulo existia y era invisible.
--
-- La escuela pidio concederselo al rol «Administrador».
--
--
-- HACEN FALTA DOS COSAS, NO UNA
--
-- El acceso se comprueba en dos niveles y faltaban los dos:
--
--   1. seguridad_rol_modulo — ¿este rol entra al modulo?  NINGUN rol tenia
--      'insights'. Sin esta fila, los permisos de abajo no llegan a
--      evaluarse: usuario_tiene_modulo() corta antes.
--   2. seguridad_permiso    — ¿que puede hacer en cada vista?
--
-- Conceder solo lo segundo habria dejado la pantalla igual de inaccesible y
-- sin ninguna pista de por que.
--
--
-- QUE SE CONCEDE, Y POR QUE ESO
--
--   ver        SI, en las once vistas. Es para lo que existe el modulo.
--
--   crear      NO
--   eliminar   NO
--              Insights no crea ni borra nada. Su conexion es de SOLO
--              LECTURA sobre los datos de los otros modulos y el propio
--              panel lo comprueba intentando una escritura prohibida y
--              esperando que falle. Conceder estas acciones no daria mas
--              poder: daria una promesa que la base no puede cumplir.
--
--   editar     SOLO en «configuracion» (Indicadores). Esa pantalla escribe
--              en insights_umbral, que son los umbrales del PROPIO Insights
--              —cuando un indicador se considera en rojo—, no datos de otro
--              modulo. En las otras diez va en NO.
--
--   exportar   SI, en las seis vistas que ofrecen exportacion: arena,
--              basketball, becas, cartera, financiero y league.
--
--
-- SOBRE «exportar», QUE MERECE LEERSE
--
-- Exportar saca datos personales del sistema —nombres, cedulas, importes— a
-- un archivo que ya vive fuera de el. Es la accion con mas peso de las
-- cinco desde el punto de vista de proteccion de datos, y por eso tiene
-- permiso propio desde la migracion 045.
--
-- Se concede porque un Administrador que puede ver los informes es
-- razonable que pueda llevarselos, y porque el sistema deja rastro: cada
-- exportacion se registra en insights_auditoria, con exito o denegada.
--
-- Si la escuela prefiere que no, se revoca sin tocar nada mas:
--
--     UPDATE seguridad_permiso p
--       JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
--        SET p.permiso_exportar = 'N'
--      WHERE m.menu_modulo = 'insights'
--        AND p.permiso_rolid = (SELECT rol_id FROM seguridad_rol
--                                WHERE rol_nombre = 'Administrador');
--
--
-- EL ROL SE BUSCA POR NOMBRE, NO POR ID
--
-- En desarrollo «Administrador» es el rol 2. En produccion puede no serlo, y
-- escribir el 2 a mano concederia Insights a quien alli tenga ese id. Se
-- deduce de la base, como hizo la 060 con los permisos de fotos.
--
-- Se excluye «Super Administrador» explicitamente: LIKE 'Administrador%' lo
-- habria cogido tambien, y no hace falta —el rol 1 se salta el control—.
--
--
-- REPETIBLE
--
-- Los dos INSERT van protegidos con NOT EXISTS. Reejecutarla no duplica
-- nada; es la diferencia con las migraciones de menu de Insights (048, 049,
-- 052), que si duplican.
-- =====================================================================

-- Comprobacion previa: el rol existe y hoy no tiene Insights.
SELECT r.rol_id, r.rol_nombre,
       (SELECT COUNT(*) FROM seguridad_rol_modulo rm
         WHERE rm.rolmod_rolid = r.rol_id AND rm.rolmod_modulo = 'insights') AS tiene_modulo,
       (SELECT COUNT(*) FROM seguridad_permiso p
          JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
         WHERE p.permiso_rolid = r.rol_id AND m.menu_modulo = 'insights') AS permisos
  FROM seguridad_rol r
 WHERE r.rol_nombre = 'Administrador' AND r.rol_estado = 'A';

-- ---------------------------------------------------------------------
-- 1. Acceso al modulo
-- ---------------------------------------------------------------------
INSERT INTO seguridad_rol_modulo (rolmod_rolid, rolmod_modulo, rolmod_estado)
SELECT r.rol_id, 'insights', 'A'
  FROM seguridad_rol r
 WHERE r.rol_nombre = 'Administrador'
   AND r.rol_estado = 'A'
   AND NOT EXISTS (
       SELECT 1 FROM seguridad_rol_modulo rm
        WHERE rm.rolmod_rolid = r.rol_id
          AND rm.rolmod_modulo = 'insights');

-- ---------------------------------------------------------------------
-- 2. Permiso sobre cada vista
-- ---------------------------------------------------------------------
INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear,
        permiso_editar, permiso_eliminar, permiso_exportar, permiso_estado)
SELECT r.rol_id,
       m.menu_id,
       'S',                                                   -- ver
       'N',                                                   -- crear
       CASE WHEN m.menu_vista = 'configuracion'
            THEN 'S' ELSE 'N' END,                            -- editar
       'N',                                                   -- eliminar
       CASE WHEN m.menu_vista IN ('arena', 'basketball', 'becas',
                                  'cartera', 'financiero', 'league')
            THEN 'S' ELSE 'N' END,                            -- exportar
       'A'
  FROM seguridad_rol r
  JOIN seguridad_menu m ON m.menu_modulo = 'insights'
 WHERE r.rol_nombre = 'Administrador'
   AND r.rol_estado = 'A'
   AND NOT EXISTS (
       SELECT 1 FROM seguridad_permiso p
        WHERE p.permiso_rolid  = r.rol_id
          AND p.permiso_menuid = m.menu_id);

-- ---------------------------------------------------------------------
-- Comprobacion posterior
-- ---------------------------------------------------------------------
SELECT m.menu_vista, m.menu_nombre,
       p.permiso_ver AS ver, p.permiso_editar AS editar,
       p.permiso_exportar AS exportar, p.permiso_estado AS estado
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
  JOIN seguridad_rol  r ON r.rol_id  = p.permiso_rolid
 WHERE m.menu_modulo = 'insights'
   AND r.rol_nombre  = 'Administrador'
 ORDER BY m.menu_orden;
