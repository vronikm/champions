# Champions CF — DigiSports

Sistema de gestión de la escuela de fútbol **Champions CF**: alumnos y representantes,
pensiones y cobros, asistencia, carnés, torneos, facturación electrónica SRI y
analítica. Construido sobre el ecosistema **DigiSports** (PHP + MySQL, MVC propio).

| Carpeta       | Qué es                                                        |
|---------------|---------------------------------------------------------------|
| `index.php`   | Hub: lanzador de módulos y seguridad de la propia cuenta      |
| `ds_core/`    | Núcleo compartido: configuración, sesión, seguridad, migraciones, administración |
| `ds_futbol/`  | Gestión de la escuela (alumnos, pagos, asistencia, carnés…)  |
| `ds_form/`    | Formulario público de inscripción (enlaces firmados)          |
| `ds_insights/`| Analítica de solo lectura                                     |
| `ds_arena/`   | Alquiler de instalaciones                                     |
| `ds_league/`  | Ligas y campeonatos                                           |

## Requisitos

- PHP 8.3 o superior con `pdo_mysql`, `openssl`, `mbstring`, `gd`, `curl`, `fileinfo`,
  `dom` y `simplexml` (las dos últimas, para los comprobantes del SRI).
- MySQL 8 (InnoDB, `utf8mb4_0900_ai_ci`).
- Apache con `mod_rewrite` y **`AllowOverride All`**: los `.htaccess` del proyecto
  son los que impiden servir el núcleo, las carpetas con fotos y los archivos internos.
- Certificado HTTPS. Fuera de `localhost` el sistema redirige a HTTPS.

## Instalación

### 1. Código

```bash
git clone https://github.com/vronikm/champions.git
```

Se instala en la raíz del dominio (`https://champions.digitech.com.ec/`) o en una
subcarpeta; la ruta sale de `DS_HUB_URL`.

### 2. Secretos del entorno

```bash
cp ds_core/config/secrets.example.php ds_core/config/secrets.php
```

Y completar en `secrets.php`, que **no se versiona**:

- `DS_HUB_URL` — URL pública, con barra final.
- `DB_*` — un usuario MySQL propio, con permisos sólo sobre la base de la escuela.
- `TOKEN_SECRET` — uno nuevo para este servidor:
  `php -r "echo bin2hex(random_bytes(32));"`

### 3. Base de datos

Crear la base con el cotejamiento correcto **antes** de restaurar:

```sql
CREATE DATABASE digitech_champions_cf
  CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
```

Restaurar el volcado de la base (no está en el repositorio: contiene datos
personales), con `SET default_storage_engine=InnoDB; SET FOREIGN_KEY_CHECKS=0;`
al principio y `SET FOREIGN_KEY_CHECKS=1;` al final.

**En un hosting compartido, el volcado no puede llevar `DEFINER`.** Las vistas
salen exportadas como `DEFINER=root@localhost`, y crear un objeto a nombre de
otro usuario exige el privilegio `SUPER`, que el hosting no da: la importación se
corta con `#1227 - Acceso negado`. Con phpMyAdmin el corte es peor de lo que
parece, porque deja las claves primarias, los `AUTO_INCREMENT` y las claves ajenas
para el final del archivo: la base queda a medias y hay que vaciarla y volver a
importar. Generar el volcado así:

```bash
mysqldump -u root --default-character-set=utf8mb4 --single-transaction \
  --set-gtid-purged=OFF --no-tablespaces --skip-add-locks digitech_champions_cf \
  | sed -E 's/DEFINER=`[^`]+`@`[^`]+`//g' > champions_produccion.sql
```

Sin `DEFINER`, las vistas quedan a nombre del usuario que importa.

Aplicar las migraciones que falten, siempre con el juego de caracteres explícito:

```bash
mysql -u USUARIO -p --default-character-set=utf8mb4 digitech_champions_cf < ds_core/database/NOMBRE.sql
```

Para saber cuáles están aplicadas sin tocar nada:

```bash
php ds_core/database/estado_migraciones.php
```

El orden y las particularidades de Champions están en
`ds_core/database/migracion_champions/LEEME.md`.

### 4. Carpetas escribibles

Bajo `ds_futbol/app/views/` el usuario de Apache debe poder escribir en:

```
imagenes/fotos/alumno/     imagenes/cedulas/      imagenes/pagos/
imagenes/fotos/empleado/   imagenes/ingresos/     fotos/usuario/
imagenes/fotos/ingresos/   imagenes/fotos/egresos/
```

Las fotos y documentos **no** se versionan y se sirven sólo con sesión iniciada
(`ds_futbol/app/media.php`). Si se migra un servidor, se copian aparte.

### 5. Tarea programada

Una vez al mes, entre el día 1 y el 5:

```bash
php ds_insights/cli/capturar_cartera.php
```

Fotografía la deuda del mes que cierra. La deuda pasada no se puede reconstruir
después, así que sin esta tarea la evolución de la cartera queda en blanco.

## Datos personales

El sistema trata datos de menores de edad (cédulas, fotos, información médica),
sujetos a la Ley Orgánica de Protección de Datos Personales del Ecuador. Nunca
se suben al repositorio volcados de la base, fotos, cédulas escaneadas,
comprobantes ni certificados de firma electrónica: el `.gitignore` los excluye.

## Licencia

MIT — ver `LICENSE`.
