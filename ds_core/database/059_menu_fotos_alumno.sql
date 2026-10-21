-- =====================================================================
-- 059 · «Fotos de alumnos» entra en el menu de Basketball
-- =====================================================================
-- QUE RESUELVE LA PANTALLA
--
-- La base guarda el NOMBRE de la foto, no la foto. Son dos cosas que pueden
-- separarse y se separan: se restaura un respaldo de la base sin el de las
-- imagenes, se migra de servidor a medias, alguien limpia la carpeta.
--
-- Hoy eso NO se nota. media_url() mira el disco y, si el archivo falta,
-- devuelve la foto generica —lo correcto, porque evita un icono roto— pero el
-- efecto es que un alumno sin archivo se ve IGUAL que uno que nunca tuvo
-- foto. Se descubre al imprimir un carne, tarde.
--
-- La pantalla distingue los tres estados y deja subir la foto que falta.
--
--
-- DONDE SE COLOCA
--
-- Justo despues de «Cumpleanos» (orden 19), que es lo que se pidio. Ambas son
-- pantallas de revision sobre el padron de alumnos, asi que quedan juntas.
--
--
-- EL PERMISO NO SE CONCEDE AQUI
--
-- Igual que la 057. La entrada de menu se crea, pero ningun rol la recibe: eso
-- se hace en Core > Permisos, que es donde la escuela decide quien ve que.
-- Sembrar un permiso desde una migracion es decidir por ella, y ademas se
-- saltaria el rastro de quien lo concedio.
--
-- Y aqui importa mas que de costumbre: la pantalla enseña caras de menores en
-- cuadricula, que es mas exposicion que la ficha de uno en uno.
--
--   ver     -> listar y ver el diagnostico
--   editar  -> subir o reemplazar la foto
--
--
-- ES REPETIBLE
--
-- El INSERT esta protegido con NOT EXISTS sobre la vista, asi que lanzarla dos
-- veces no duplica la entrada del menu.
--
-- Aplicar con:  mysql -u root digitech_barcelona < 059_menu_fotos_alumno.sql

SET NAMES utf8mb4;

INSERT INTO seguridad_menu
    (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo, menu_vista, menu_icono, menu_estado)
SELECT
    'basketball', 'Fotos de alumnos', 20, 0, 'N', 'alumnoFotos',
    'nav-icon fas fa-images text-warning', 'A'
  FROM DUAL
 WHERE NOT EXISTS (
     SELECT 1 FROM (SELECT 1 FROM seguridad_menu
                     WHERE menu_modulo = 'basketball'
                       AND menu_vista  = 'alumnoFotos' LIMIT 1) AS ya);

-- ---------------------------------------------------------------------
-- Comprobacion
-- ---------------------------------------------------------------------
-- «Fotos de alumnos» tiene que salir justo debajo de «Cumpleanos».
SELECT menu_id, menu_orden, menu_nombre, menu_vista, menu_estado
  FROM seguridad_menu
 WHERE menu_modulo = 'basketball' AND menu_padreid = 0 AND menu_orden >= 18
 ORDER BY menu_orden;
