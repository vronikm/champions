-- La tarjeta de cumpleaños como la generaba champions_cf
-- (app/views/content/cumpleaniosTarjeta-view.php del sistema anterior).
--
-- La plantilla vino de barcelona: franja VERDE (#054017) y coordenadas de su
-- marco, así que la franja con el nombre caía a media foto. Aquí se pasan a
-- porcentajes del marco (900 x 1600) las medidas fijas de champions:
--   foto     x 20,4 %  y 29,8 %  ancho 58,8 %  alto 48,2 %
--   franja   18 % del alto de la foto = 8,676 % del marco, al pie de la foto,
--            azul #002A64 al 70 % (alpha GD 38), inclinada -2,5°
--   nombre   5,5 % del ancho de la foto = 3,234 % del marco, blanco,
--            línea base en 72,822 % (centro de la franja menos 18 % de su alto)
--   edad     4 % del ancho de la foto = 2,352 % del marco, amarillo #FFE001,
--            línea base en 75,772 % (centro de la franja más 16 % de su alto)
SET NAMES utf8mb4;

UPDATE cumple_plantilla
   SET foto_x = 20.400, foto_y = 29.800, foto_ancho = 58.800, foto_alto = 48.200,
       nombre_x = 20.400, nombre_y = 72.822, nombre_ancho = 58.800, nombre_tam = 3.234,
       nombre_color = '#FFFFFF', nombre_alinea = 'C',
       edad_x = 20.400, edad_y = 75.772, edad_ancho = 58.800, edad_tam = 2.352,
       edad_color = '#FFE001', edad_alinea = 'C', edad_texto = '{edad} AÑOS',
       franja_activa = 'S', franja_alto = 8.676, franja_color = '#002A64', franja_opacidad = 70,
       angulo = -2.50
 WHERE plantilla_archivo = 'marco.png';

-- Las plantillas nuevas ya no nacen con el verde de barcelona.
ALTER TABLE cumple_plantilla ALTER COLUMN franja_color SET DEFAULT '#002A64';
