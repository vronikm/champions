# Migración de SoccerEasy (champions_cf) a DigiSports

Convierte una copia de la base de `champions_cf` (`digitech_soccereasy`) en una
base DigiSports como la de barcelona. Se aplicó el 2026-10-20 sobre
`digitech_champions_cf`. La base original no se toca.

## Orden

1. Crear la base vacía:

   ```sql
   CREATE DATABASE digitech_champions_cf
     CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
   ```

2. Restaurar el volcado de SoccerEasy con, al principio,
   `SET default_storage_engine=InnoDB; SET FOREIGN_KEY_CHECKS=0;`
   y `SET FOREIGN_KEY_CHECKS=1;` al final.
3. `000_previo_champions.sql`: crea las nueve tablas que barcelona ya tenía
   antes de sus migraciones (consentimientos, configuración de carnet y
   facturación electrónica), sin datos fiscales de barcelona. Deja en NULL el
   representante 0 del alumno 194, que no existe.
4. Las migraciones `001` a `064` de `ds_core/database/`, en orden y siempre con
   `--default-character-set=utf8mb4`. Tres se sustituyen por su versión de
   esta carpeta:
   - `014` y `015`: sólo conceden permisos a roles que existen. En barcelona
     el rol 6 es «Asistente»; en champions no existe, y tras la `029` el 6
     pasa a ser «Árbitro».
   - `041`: el `ALTER DATABASE` apunta a `digitech_champions_cf`.
5. `065_ajustes_champions.sql`. Requiere que `digitech_barcelona` exista en el
   mismo servidor, porque copia de ella siete menús y sus permisos. Además
   alinea `torneo_equipo.equipo_categoria`, retira las tablas vacías de la
   facturación antigua, quita Facturación al Administrador y registra el logo.

```bash
mysql -u USUARIO -p --default-character-set=utf8mb4 digitech_champions_cf < ARCHIVO.sql
```

6. `066_nombre_futbol.sql`: el menú de Insights pasa a llamarse «Fútbol». La
   clave interna del módulo sigue siendo `basketball`.

7. `067_tarjeta_cumple_champions.sql`: la tarjeta de cumpleaños con la
   franja azul y las medidas de champions_cf (venía con las de barcelona).

8. `068_corrige_fecha_pago_7328.sql`: el pago 7328 traía el año `0025`
   en vez de `2025`. Idempotente.

## Comprobación

```bash
php ds_core/database/estado_migraciones.php
```

Resultado esperado: 36 aplicadas, 0 pendientes, 28 no determinables.
