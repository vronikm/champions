-- =====================================================================
-- 019 · League · Alta del módulo en seguridad
-- =====================================================================
-- Da de alta el módulo en los dos sitios de los que depende el control de
-- acceso: seguridad_rol_modulo (nivel 1, «¿puede entrar?») y
-- seguridad_menu (nivel 2, «¿puede abrir esta vista?»).
--
-- POR QUÉ SÓLO EL ROL 1 RECIBE ACCESO
--
-- El superadministrador es el único con acceso a todo. El resto de roles
-- no recibe nada aquí: sus permisos sobre League se conceden desde la
-- pantalla del Core, uno a uno y por decisión de la administración. Un
-- módulo nuevo que se autoconcediera permisos a los roles existentes
-- ampliaría el alcance de gente que nunca lo pidió.
--
-- EL MODO ESTRICTO CAMBIA EL SIGNIFICADO DE ESTA TABLA
--
-- En Basketball, seguridad_menu es la lista de lo que está RESTRINGIDO:
-- lo que no está registrado queda abierto. En League es al revés, porque
-- el módulo declara DS_PERMISOS_ESTRICTOS. Aquí la tabla es la lista de
-- lo que EXISTE, y lo que falte queda denegado.
--
-- De ahí el estado 'O' (oculta), que se introduce con esta migración: una
-- vista de apoyo —un formulario, un acta, un PDF— necesita estar
-- registrada para poder abrirse, pero no tiene sentido como entrada de
-- menú. Con 'O' queda sujeta a permiso y fuera de la barra lateral.
-- Todavía no hay ninguna; el estado se documenta aquí porque es donde se
-- entiende para qué sirve.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Nivel 1: acceso al módulo
-- ---------------------------------------------------------------------
-- La conexion del cliente puede llegar en cp850 (consola de Windows). Sin
-- esta linea, un texto con tilde o guion largo se guarda doblemente
-- codificado y se lee como caracteres rotos.
SET NAMES utf8mb4;

INSERT INTO seguridad_rol_modulo (rolmod_rolid, rolmod_modulo, rolmod_estado)
SELECT R.rol_id, 'league', 'A'
  FROM seguridad_rol R
 WHERE R.rol_id = 1
   AND NOT EXISTS (SELECT 1 FROM seguridad_rol_modulo M
                    WHERE M.rolmod_rolid = R.rol_id
                      AND M.rolmod_modulo = 'league');


-- ---------------------------------------------------------------------
-- Nivel 2: las vistas del módulo
--
-- De momento sólo el panel. Cada vista de la fase 1 se irá añadiendo aquí
-- a medida que exista, y en este módulo eso no es opcional: sin su fila,
-- la vista devuelve 403 aunque el fichero esté en su sitio.
-- ---------------------------------------------------------------------
INSERT INTO seguridad_menu
       (menu_modulo, menu_nombre, menu_orden, menu_padreid,
        menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT 'league', 'Panel', 10, NULL, 'N', 'panel', 'fas fa-trophy', 'A'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM seguridad_menu
                    WHERE menu_modulo = 'league' AND menu_vista = 'panel');


-- ---------------------------------------------------------------------
-- Permiso de lectura para el superadministrador.
--
-- es_superadministrador() ya devuelve true sin consultar esta tabla, así
-- que la fila no cambia su acceso. Se crea igualmente para que la
-- pantalla de permisos del Core muestre el módulo con su estado real en
-- vez de en blanco, que es lo que confunde al administrarlo.
-- ---------------------------------------------------------------------
INSERT INTO seguridad_permiso
       (permiso_rolid, permiso_menuid, permiso_ver,
        permiso_crear, permiso_editar, permiso_eliminar, permiso_estado)
SELECT 1, M.menu_id, 'S', 'S', 'S', 'S', 'A'
  FROM seguridad_menu M
 WHERE M.menu_modulo = 'league'
   AND M.menu_vista  = 'panel'
   AND NOT EXISTS (SELECT 1 FROM seguridad_permiso P
                    WHERE P.permiso_rolid  = 1
                      AND P.permiso_menuid = M.menu_id);
