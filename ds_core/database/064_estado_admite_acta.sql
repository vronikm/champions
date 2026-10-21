-- =====================================================================
-- 064 · Qué estados de un partido admiten carga de acta
-- =====================================================================
-- QUE PROBLEMA RESUELVE
--
-- La pantalla del acta decidia sola si se podia editar:
--
--   $puedeEditar = puede_editar('actaPartido')
--                  && $partido['estado_codigo'] !== 'CANCELADO';
--
-- ...y guardarActa() no volvia a mirar el estado. Como el acta se guarda
-- por fetch, campo a campo, bastaba repetir la peticion para cargar
-- estadisticas en un partido CANCELADO. Medido antes de escribir esto:
-- la respuesta fue «tipo=acta · Linea guardada» y la fila quedo escrita.
--
-- Es la forma clasica del fallo: una regla que solo vive en la vista no es
-- una regla, es una sugerencia.
--
--
-- POR QUE UNA COLUMNA Y NO UN «IF» EN EL CONTROLADOR
--
-- Repetir la condicion en PHP cerraba el agujero, pero dejaba el literal
-- 'CANCELADO' escrito en dos sitios. El dia que la liga decida que un
-- partido SUSPENDIDO tampoco admite carga, habria que buscar y tocar los
-- dos —y el que se olvide sera el que importe—.
--
-- dsl_estado ya es el catalogo de estados y ya lleva banderas de este tipo
-- (estado_final, estado_efectivo, estado_activo). Esta es una mas, y
-- convierte la regla en un dato que se cambia sin desplegar codigo.
--
--
-- POR QUE SOLO CANCELADO EMPIEZA EN 'N'
--
-- Para no cambiar de comportamiento mas alla de cerrar el agujero: es
-- exactamente lo que la vista ya impedia. SUSPENDIDO y REPROGRAMADO son
-- candidatos —un partido suspendido puede reanudarse, y ahi el acta a
-- medias tiene sentido—, pero eso es una decision de la liga y se toma
-- cambiando una fila, que es justo el punto de esta migracion.
--
-- Los estados de «inscripcion» viven en esta misma tabla y no les afecta:
-- el UPDATE se acota por estado_entidad.
--
--
-- QUE CAMBIA EN EL CODIGO (ya hecho)
--
--   * estadisticaController::guardarActa() · rechaza si el estado del
--     partido no admite acta. Es la comprobacion que faltaba en servidor.
--   * estadisticaController::partido()     · trae la bandera.
--   * actaPartido-view.php                 · deja de comparar contra el
--     literal 'CANCELADO' y usa la bandera, para que pantalla y servidor
--     lean lo mismo.
--
--
-- SE PUEDE REPETIR
--
-- El ADD COLUMN falla con el error 1060 si ya existe; el UPDATE es
-- idempotente. Si se reejecuta, basta ignorar ese 1060.
-- =====================================================================

-- Comprobacion previa: cuantos partidos hay en cada estado, para saber a
-- que se le esta cerrando la puerta.
SELECT E.estado_codigo, COUNT(P.partido_id) AS partidos
  FROM dsl_estado E
  LEFT JOIN dsl_partido P ON P.partido_estadoid = E.estado_id
 WHERE E.estado_entidad = 'partido'
 GROUP BY E.estado_id, E.estado_codigo
 ORDER BY E.estado_orden;

ALTER TABLE dsl_estado
  ADD COLUMN estado_admiteacta CHAR(1) NOT NULL DEFAULT 'S'
  COMMENT 'S = se pueden cargar estadisticas de un partido en este estado'
  AFTER estado_efectivo;

UPDATE dsl_estado
   SET estado_admiteacta = 'N'
 WHERE estado_entidad = 'partido'
   AND estado_codigo  = 'CANCELADO';

-- Comprobacion posterior: debe salir CANCELADO en 'N' y el resto en 'S'.
SELECT estado_codigo, estado_final, estado_efectivo, estado_admiteacta
  FROM dsl_estado
 WHERE estado_entidad = 'partido'
 ORDER BY estado_orden;

-- ---------------------------------------------------------------------
-- Para revertir (requiere devolver antes la comprobacion del controlador):
--
-- ALTER TABLE dsl_estado DROP COLUMN estado_admiteacta;
-- ---------------------------------------------------------------------
