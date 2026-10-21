-- =====================================================================
-- 060 · Permiso sobre «Fotos de alumnos»
-- =====================================================================
-- POR QUE ESTA VEZ SI SE SIEMBRA EL PERMISO
--
-- La 057 y la 059 crearon sus entradas de menu SIN conceder permisos, con
-- el argumento de que quien decide que rol administra que es la escuela y no
-- una migracion. Ese argumento sigue en pie: lo que cambia es que la escuela
-- YA lo decidio y pidio concederlo.
--
-- Se hace aqui y no a mano en la base porque, si no, habria que repetirlo en
-- produccion acordandose, y un permiso olvidado alli se manifiesta como «la
-- pantalla no me deja entrar» sin ninguna pista de por que.
--
--
-- A QUIEN SE LE CONCEDE, Y POR QUE ASI
--
-- A los mismos roles que ya administran el padron de alumnos, deducidos de
-- la propia base en vez de escritos a mano. Hoy son Administrador, Profesor
-- Catamayo y Asistente, pero si manana la escuela cambia esa lista, esta
-- migracion aplicada en otra instalacion concedera lo correcto alli y no lo
-- que era cierto en este servidor el dia que se escribio.
--
-- El criterio de fondo: esta pantalla NO amplia lo que esos roles ya pueden
-- hacer. Quien puede editar la ficha de un alumno ya puede cambiarle la
-- foto desde alumnoUpdate. Lo que aporta es verlo todo junto y saber que
-- fichas apuntan a un archivo que no esta.
--
--
-- SOLO VER Y EDITAR
--
-- La pantalla no crea ni elimina alumnos: lista y sube fotos. Conceder
-- «crear» y «eliminar» seria dar acciones que ninguna parte del codigo
-- ejercita, y el dia que alguien revise quien puede borrar que, esas dos
-- eses mentirian sobre lo que de verdad hace la pantalla.
--
--   ver     -> listar y ver el diagnostico de fotos
--   editar  -> subir o reemplazar la foto de un alumno
--
-- El rol 1 no aparece: es_superadministrador() lo salta todo y darle una
-- fila explicita solo crea la duda de si hace falta.
--
--
-- ES REPETIBLE
--
-- El INSERT esta protegido con NOT EXISTS sobre el par rol/menu, asi que
-- lanzarla dos veces no duplica filas ni pisa un permiso que la escuela haya
-- ajustado despues a mano.
--
-- Aplicar con:  mysql -u root digitech_barcelona < 060_permiso_fotos_alumno.sql

SET NAMES utf8mb4;

INSERT INTO seguridad_permiso
    (permiso_rolid, permiso_menuid, permiso_ver, permiso_crear,
     permiso_editar, permiso_eliminar, permiso_exportar, permiso_estado)
SELECT
    base.permiso_rolid,
    destino.menu_id,
    'S',   -- ver
    'N',   -- crear:    la pantalla no da de alta alumnos
    'S',   -- editar:   subir o reemplazar la foto
    'N',   -- eliminar: la pantalla no borra alumnos ni fotos
    'N',   -- exportar: no hay descarga masiva, y son caras de menores
    'A'
  FROM (SELECT DISTINCT p.permiso_rolid
          FROM seguridad_permiso p
          JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
         WHERE m.menu_vista   = 'alumnoList'
           AND m.menu_modulo  = 'basketball'
           AND p.permiso_ver  = 'S'
           AND p.permiso_estado = 'A') AS base
 CROSS JOIN (SELECT menu_id FROM seguridad_menu
              WHERE menu_vista  = 'alumnoFotos'
                AND menu_modulo = 'basketball' LIMIT 1) AS destino
 WHERE NOT EXISTS (
     SELECT 1 FROM (SELECT permiso_rolid, permiso_menuid
                      FROM seguridad_permiso) AS ya
      WHERE ya.permiso_rolid  = base.permiso_rolid
        AND ya.permiso_menuid = destino.menu_id);

-- ---------------------------------------------------------------------
-- Comprobacion
-- ---------------------------------------------------------------------
-- Cada rol que administra alumnos tiene que salir con ver=S y editar=S.
SELECT r.rol_id, r.rol_nombre,
       p.permiso_ver AS ver, p.permiso_crear AS crear,
       p.permiso_editar AS editar, p.permiso_eliminar AS eliminar,
       p.permiso_estado AS estado
  FROM seguridad_permiso p
  JOIN seguridad_menu m ON m.menu_id = p.permiso_menuid
  JOIN seguridad_rol  r ON r.rol_id  = p.permiso_rolid
 WHERE m.menu_vista = 'alumnoFotos'
 ORDER BY r.rol_id;
