# Migración de SoccerEasy (champions_cf) a DigiSports

Convierte una copia de la base de SoccerEasy en una base DigiSports. Se aplicó
el 2026-10-20 sobre `digitech_champions_cf` (pruebas) y el 2026-10-21 se
ensayó completa sobre `digitech_migra_champions_cf`, la copia más reciente del
sistema anterior. La base de origen nunca se toca: se trabaja sobre una copia.

## Atajo: un solo comando

Tras los pasos 1 y 2 de abajo:

```bash
MYSQL=/ruta/a/mysql MYSQL_OPTS="-u USUARIO -pCLAVE" \
  ds_core/database/migracion_champions/aplicar_migracion.sh BASE
```

Aplica los 76 archivos en el orden correcto y se detiene en el primer error.
Antes de empezar comprueba que `BASE` sea una copia de SoccerEasy **sin migrar**:
si ya tiene `facturas_electronicas`, no aplica nada, porque varias migraciones no
se pueden repetir.

No depende de ninguna otra base del servidor ni de su nombre: se puede ejecutar
en desarrollo y llevar el resultado a producción con un volcado (ver más abajo).

## Orden

1. Crear la base vacía con el cotejamiento correcto:

   ```sql
   CREATE DATABASE nombre_de_la_base
     CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   ```

2. Restaurar el volcado de SoccerEasy con, al principio,
   `SET default_storage_engine=InnoDB; SET FOREIGN_KEY_CHECKS=0;`
   y `SET FOREIGN_KEY_CHECKS=1;` al final.
3. `pre_retirar_residuos_qa.sql`: retira lo que el arnés de pruebas dejó por
   error en `digitech_migra_champions_cf` el 2026-10-21 (alumnos y
   representante con identificación `99000000…`, el usuario `qa2fatester` y la
   tabla `carnet_configuracion`, con la que `000` abortaría). Sobre una copia
   limpia de SoccerEasy no encuentra nada.
4. `000_previo_champions.sql`: crea las nueve tablas que barcelona ya tenía
   antes de sus migraciones (consentimientos, configuración de carnet y
   facturación electrónica), sin datos fiscales de barcelona. Deja en NULL el
   representante 0 del alumno 194, que no existe.
5. Las migraciones `001` a `064` de `ds_core/database/`, en orden y siempre con
   `--default-character-set=utf8mb4`. Tres se sustituyen por su versión de
   esta carpeta:
   - `014` y `015`: sólo conceden permisos a roles que existen. En barcelona
     el rol 6 es «Asistente»; en champions no existe, y tras la `029` el 6
     pasa a ser «Árbitro».
   - `041`: el `ALTER DATABASE` va sin nombre, así que convierte la base sobre
     la que se ejecuta. Hasta el 2026-10-21 decía `digitech_champions_cf`, y
     aplicada a otra base convertía la de pruebas.
6. `065_ajustes_champions.sql`: añade ocho menús y sus permisos (carnés,
   LOPDP, inscripciones pendientes y vistas ocultas de League), alinea
   `torneo_equipo.equipo_categoria`, retira las tablas vacías de la
   facturación antigua, quita Facturación al Administrador y registra el logo.
   Hasta el 2026-10-21 copiaba esos menús desde `digitech_barcelona`, y en un
   servidor sin esa base fallaba; ahora los lleva escritos y da el mismo
   resultado.
7. `066_nombre_futbol.sql`: el menú de Insights pasa a llamarse «Fútbol». La
   clave interna del módulo sigue siendo `basketball`.
8. `067_tarjeta_cumple_champions.sql`: la tarjeta de cumpleaños con la
   franja azul y las medidas de champions_cf (venía con las de barcelona).
9. `068_corrige_fecha_pago_7328.sql`: el pago 7328 traía el año `0025`
   en vez de `2025`. Idempotente.
10. `069_sri_ambientes.sql`: tabla `facturas_electronicas_ambiente` con las
    direcciones del SRI (pruebas y producción) y sus tiempos, que antes
    estaban en el código. Se editan en Core › Facturación electrónica ›
    Ambientes del SRI. Repetible.
11. `070_menu_facturas_emitidas.sql`: grupo «Facturación» con «Emitir
    facturas» (la entrada de siempre, mismo id y permisos) y «Facturas
    emitidas», que nace con los permisos de la primera. Repetible.
12. `071_facturacion_servicio_correo.sql`: columnas de la configuración del
    SRI para el estado del servicio (mantenimiento), el teléfono del emisor,
    el RUC del proveedor del sistema (NAC-DGERCGC26-00000027) y el correo
    por SMTP (clave cifrada). Por defecto, todo como antes. Repetible.
13. `072_usuario_sedes.sql`: marca «todas las sedes» en `seguridad_usuario`
    y una sede una sola vez por usuario. Desde aquí la regla de qué sedes ve
    cada usuario es una (`sedes_de_la_sesion()`); para que nadie pierda
    acceso, los roles 1 y 2 quedan con «todas» y quien no tenía sede recibe la
    de su ficha de empleado. Se ajusta en Core › Usuarios. Repetible.
14. `073_redes_sociales.sql`: configuración de redes sociales (perfiles y
    conexión con Meta, token cifrado) y el registro de publicaciones; menú
    «Redes sociales» en Core. Las felicitaciones de cumpleaños se publican
    desde Fútbol › Cumpleaños, sólo con autorización de imagen. Repetible.
15. `074_consentimiento_enlace.sql`: registro de los enlaces de autorización
    LOPDP que Fútbol › Consentimientos envía por WhatsApp o correo a los
    representantes que aún no autorizan (un solo uso, con vencimiento). Lo
    que responden queda en `alumno_consentimiento` con origen ENLACE. Repetible.

```bash
mysql -u USUARIO -p --default-character-set=utf8mb4 BASE < ARCHIVO.sql
```

## Llevarla a producción

La migración se hace **en desarrollo**, sobre una copia, y a producción viaja
el resultado ya comprobado. En el hosting no hay `SUPER` ni Terminal, y una
migración a medias allí no tiene vuelta atrás.

1. **Congelar SoccerEasy.** Desde el momento de la copia, nada se registra en
   el sistema anterior: lo que entre después no estará en la base nueva.
2. Exportar SoccerEasy, crear la base local y restaurarla (pasos 1 y 2).
3. `aplicar_migracion.sh BASE`.
4. Comprobar (abajo).
5. Volcar sin `DEFINER` (ver el README de la raíz) e importar en la base del
   hosting **vacía**. Lo que hubiera en ella se pierde: confirmar antes que
   nadie haya registrado nada en el sistema nuevo.
6. Copiar las fotos y documentos desde el **servidor** de SoccerEasy: no van en
   la base ni en el repositorio, y las copias locales no los tienen.

   | SoccerEasy (`app/views/imagenes/…`) | DigiSports (`ds_futbol/app/views/…`) |
   |---|---|
   | `fotos/alumno/`   | `imagenes/fotos/alumno/`   |
   | `fotos/profesor/` | `imagenes/fotos/empleado/` |
   | `fotos/ingresos/` | `imagenes/fotos/ingresos/` |
   | `fotos/egresos/`  | `imagenes/fotos/egresos/`  |
   | `fotos/usuario/`  | `fotos/usuario/`           |
   | `cedulas/`        | `imagenes/cedulas/`        |
   | `pagos/`          | `imagenes/pagos/`          |

   La pantalla «Fotos de alumnos» dice cuántas fichas apuntan a un archivo que
   falta; sin la foto, el carné de ese alumno no se genera.

## Desde cPanel, sin consola (phpMyAdmin)

En el hosting no hay Terminal. La misma cadena, en un solo archivo:

```bash
ds_core/database/migracion_champions/aplicar_migracion.sh --generar migracion_produccion_champions.sql
```

1. **Respaldar** la base de producción: phpMyAdmin → la base → *Exportar*.
2. phpMyAdmin → la base con la copia de SoccerEasy → *Importar* →
   `migracion_produccion_champions.sql`, juego de caracteres `utf8mb4`.
   Dejar marcada la opción que permite interrumpir la importación: si el
   servidor corta por tiempo, phpMyAdmin la retoma donde quedó.
3. Comprobar que terminó sin error. Si se detiene, **no** repetir el archivo
   entero sobre lo ya aplicado: restaurar el respaldo y empezar de nuevo.

Probado el 2026-10-22 con un usuario sin `SUPER` (como el del hosting) sobre
una copia de SoccerEasy: 25 segundos, sin errores, y la base resultante
idéntica a la migrada con el script.

## Comprobación

Con la base apuntada en `secrets.php`:

```bash
php ds_core/database/estado_migraciones.php
```

Resultado esperado: 36 aplicadas, 0 pendientes, 28 no determinables.

El ensayo del 2026-10-21 se comparó además contra `digitech_champions_cf`:
101 tablas y vistas, 1069 columnas, 380 índices, 86 claves ajenas, 243
restricciones y las 3 vistas, idénticos. Los datos de origen llegaron
íntegros: mismo número de alumnos, representantes y pagos, y mismas sumas de
valores y saldos que la copia de SoccerEasy.
