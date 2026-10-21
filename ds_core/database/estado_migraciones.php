<?php
/*
|--------------------------------------------------------------------------
| DigiSports — Qué migraciones están aplicadas
|--------------------------------------------------------------------------
| SOLO LEE. No aplica nada, no escribe nada, no toca ni una fila.
|
|
| POR QUÉ HACE FALTA
|
| Las migraciones de ds_core/database se cuentan solas al arrancar, y NO
| existe tabla que registre cuáles se aplicaron. En desarrollo da igual: se
| sabe. En una base de producción no, y ahí la pregunta importa mucho,
| porque unas cuantas NO se pueden reejecutar sin error —añaden columnas,
| crean índices o insertan filas sin protección—. El informe las nombra.
|
| Aplicarlas todas a ciegas revienta en la primera columna duplicada y deja
| el despliegue a medias, que es el peor sitio donde quedarse.
|
|
| CÓMO LO AVERIGUA
|
| No adivina: lee la migración, extrae qué DEJA en el esquema —una tabla, una
| columna, un índice, una vista— y comprueba si eso está. Es una huella
| derivada del propio archivo, así que una migración nueva se comprueba sola
| sin tocar este script.
|
|
| LO QUE NO PUEDE SABER
|
| Una migración que sólo inserta filas, o que cambia algo que ya existe —un
| cotejamiento, un valor por omisión—, no deja un objeto nuevo en el
| esquema. Esas se marcan «sin huella comprobable» en vez de suponer,
| porque suponer aquí lleva a reejecutar un INSERT y duplicar menús.
|
| Tampoco distingue una migración aplicada de una cuyo efecto se consiguió
| por otro camino. Es un indicio muy bueno, no un registro. El registro de
| verdad sería una tabla de migraciones, y está recomendado en la guía de
| despliegue.
|
|
| Uso:  php ds_core/database/estado_migraciones.php
*/

declare(strict_types=1);

$raiz = dirname(__DIR__, 2);

require_once $raiz . '/ds_core/config/secrets.php';
require_once $raiz . '/ds_core/config/app.php';

try {
    $db = new PDO(
        'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $e) {
    fwrite(STDERR, "  No se pudo conectar: " . $e->getMessage() . "\n");
    exit(2);
}

/*----------  Lo que hay en el esquema ahora  ----------*/

$tablas = [];
foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
    $tablas[strtolower($t)] = true;
}

$columnas = [];
foreach ($db->query(
    "SELECT LOWER(TABLE_NAME) t, LOWER(COLUMN_NAME) c
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $columnas[$r['t'] . '.' . $r['c']] = true;
}

$indices = [];
foreach ($db->query(
    "SELECT LOWER(TABLE_NAME) t, LOWER(INDEX_NAME) i
       FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $indices[$r['t'] . '.' . $r['i']] = true;
}

$vistas = [];
foreach ($db->query(
    "SELECT LOWER(TABLE_NAME) v FROM information_schema.VIEWS
      WHERE TABLE_SCHEMA = DATABASE()")->fetchAll(PDO::FETCH_COLUMN) as $v) {
    $vistas[$v] = true;
}

/*
| Columnas GENERADAS.
|
| Hacía falta un inventario propio: una migración que convierte una columna
| que YA existe en generada —la 061 con dsa_reserva.reserva_saldo— no crea
| nada nuevo, así que la huella de «columna» la daba por presente desde
| siempre y la de «tabla» tampoco servía. El informe la marcaba «sin huella
| comprobable», que es justo lo contrario de la verdad: deja una huella
| nítida en el esquema, sólo que de otro tipo.
|
| Y es una migración que NO se puede repetir a ciegas ni omitir: sin ella el
| código nuevo no mantiene el saldo, y con ella el código viejo falla con el
| error 3105 en toda creación, edición y cobro.
*/
$generadas = [];
foreach ($db->query(
    "SELECT LOWER(TABLE_NAME) t, LOWER(COLUMN_NAME) c
       FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
        AND GENERATION_EXPRESSION <> ''")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $generadas[$r['t'] . '.' . $r['c']] = true;
}

/*----------  La huella de cada migración  ----------*/

/**
 * Qué deja esta migración en el esquema.
 *
 * Se ignoran los comentarios antes de buscar: varias migraciones explican en
 * prosa las tablas que NO tocan, y contarlas daría huellas falsas.
 */
function huellas(string $sql): array
{
    $sql = preg_replace(['~/\*.*?\*/~s', '~--[^\n]*~'], '', $sql);
    $h = [];

    if (preg_match_all('~CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s+`?(\w+)`?~i', $sql, $m)) {
        foreach ($m[1] as $t) { $h[] = ['tabla', strtolower($t)]; }
    }

    if (preg_match_all('~CREATE(?:\s+OR\s+REPLACE)?\s+(?:ALGORITHM\s*=\s*\w+\s+)?'
                     . '(?:DEFINER\s*=\s*\S+\s+)?(?:SQL\s+SECURITY\s+\w+\s+)?VIEW\s+`?(\w+)`?~i',
                       $sql, $m)) {
        foreach ($m[1] as $v) { $h[] = ['vista', strtolower($v)]; }
    }

    if (preg_match_all('~ALTER\s+TABLE\s+`?(\w+)`?[^;]*?ADD\s+(?:COLUMN\s+)?`?(\w+)`?~is', $sql, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $col = strtolower($x[2]);
            /* «ADD INDEX/KEY/CONSTRAINT» no nombra una columna. */
            if (in_array($col, ['index', 'key', 'constraint', 'unique', 'primary', 'foreign'], true)) { continue; }
            $h[] = ['columna', strtolower($x[1]) . '.' . $col];
        }
    }

    if (preg_match_all('~CREATE\s+(?:UNIQUE\s+)?INDEX\s+`?(\w+)`?\s+ON\s+`?(\w+)`?~i', $sql, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) { $h[] = ['indice', strtolower($x[2]) . '.' . strtolower($x[1])]; }
    }

    /* Convertir una columna existente en GENERADA. No crea columna ni
       tabla, así que ninguna de las huellas de arriba la ve; sin esto la
       061 salía «sin huella comprobable» teniendo una perfectamente
       verificable. Cubre MODIFY y CHANGE, con o sin la palabra COLUMN. */
    if (preg_match_all(
            '~ALTER\s+TABLE\s+`?(\w+)`?\s+(?:MODIFY|CHANGE)\s+(?:COLUMN\s+)?`?(\w+)`?'
          . '[^;]*?GENERATED\s+ALWAYS\s+AS~is', $sql, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) { $h[] = ['generada', strtolower($x[1]) . '.' . strtolower($x[2])]; }
    }

    /* ALTER TABLE t ADD UNIQUE KEY nombre (...) deja un indice, igual que
       CREATE INDEX. Sin esto, la 002 salia como «solo datos» y a la vez
       como no repetible: dos partes de este script contradiciendose. */
    if (preg_match_all('~ALTER\s+TABLE\s+`?(\w+)`?[^;]*?ADD\s+(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?(?:KEY|INDEX)\s+`?(\w+)`?~is', $sql, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) { $h[] = ['indice', strtolower($x[1]) . '.' . strtolower($x[2])]; }
    }

    return $h;
}

/**
 * ¿Se puede volver a ejecutar sin romper nada?
 *
 * Es la pregunta que de verdad importa cuando no se sabe si ya se aplicó. Una
 * migración protegida se puede lanzar y no pasa nada; una sin proteger aborta
 * el despliegue en la primera columna o fila duplicada, y deja la base a
 * medio migrar.
 */
function repetible(string $sql): array
{
    $sql = preg_replace(['~/\*.*?\*/~s', '~--[^\n]*~'], '', $sql);
    $riesgos = [];

    if (preg_match('~CREATE\s+TABLE(?!\s+IF\s+NOT\s+EXISTS)~i', $sql)) {
        $riesgos[] = 'CREATE TABLE';
    }
    if (!preg_match('~ADD\s+(?:COLUMN\s+)?IF\s+NOT\s+EXISTS~i', $sql)) {
        /* Se nombra lo que de verdad se anade. Llamar «ADD COLUMN» a una
           clave unica confunde a quien lee el informe para decidir. */
        foreach (['COLUMN' => 'ADD COLUMN', 'CONSTRAINT' => 'ADD CONSTRAINT',
                  'KEY' => 'ADD KEY', 'INDEX' => 'ADD INDEX'] as $que => $etq) {
            if (preg_match('~ALTER\s+TABLE[^;]*?\sADD\s+(?:UNIQUE\s+|FULLTEXT\s+|SPATIAL\s+)?' . $que . '\s~is', $sql)) {
                $riesgos[] = $etq;
            }
        }
        /* ALTER TABLE t ADD `col` TIPO, sin la palabra COLUMN. */
        if ($riesgos === [] && preg_match('~ALTER\s+TABLE[^;]*?\sADD\s+`?\w+`?\s+(?:INT|VARCHAR|CHAR|DATE|DECIMAL|TEXT|TINY|BIG|ENUM)~is', $sql)) {
            $riesgos[] = 'ADD COLUMN';
        }
    }
    if (preg_match('~CREATE\s+(?:UNIQUE\s+)?INDEX~i', $sql)) {
        $riesgos[] = 'CREATE INDEX';
    }
    if (preg_match('~INSERT\s+INTO~i', $sql)
        && !preg_match('~ON\s+DUPLICATE|INSERT\s+IGNORE|NOT\s+EXISTS~i', $sql)) {
        $riesgos[] = 'INSERT sin proteger';
    }

    return $riesgos;
}

$archivos = glob($raiz . '/ds_core/database/*.sql');
sort($archivos);

$aplicadas = 0;
$faltan    = 0;
$parciales = 0;
$dudosas   = 0;
$pendiente = [];
$revisar   = [];

echo "\n";
printf("  %-44s %s\n", 'MIGRACIÓN', 'ESTADO');
echo '  ' . str_repeat('-', 74) . "\n";

foreach ($archivos as $f) {
    $nombre = basename($f);
    $sql = (string) file_get_contents($f);
    $h   = huellas($sql);
    $riesgo = repetible($sql);

    if ($h === []) {
        $dudosas++;
        printf("  %-44s %s\n", $nombre, $riesgo === []
            ? 'sin huella comprobable · repetirla es SEGURO'
            : 'sin huella comprobable · NO REPETIR (' . implode(', ', $riesgo) . ')');
        if ($riesgo !== []) { $revisar[] = $nombre; }
        continue;
    }

    $hay = 0;
    $faltantes = [];
    foreach ($h as [$tipo, $clave]) {
        $existe = match ($tipo) {
            'tabla'    => isset($tablas[$clave]),
            'columna'  => isset($columnas[$clave]),
            'indice'   => isset($indices[$clave]),
            'vista'    => isset($vistas[$clave]),
            'generada' => isset($generadas[$clave]),
        };
        if ($existe) { $hay++; } else { $faltantes[] = "$tipo $clave"; }
    }

    if ($hay === count($h)) {
        $aplicadas++;
        printf("  %-44s %s\n", $nombre, 'aplicada');
    } elseif ($hay === 0) {
        $faltan++;
        $pendiente[] = $nombre;
        printf("  %-44s %s\n", $nombre, 'PENDIENTE');
    } else {
        $parciales++;
        $pendiente[] = $nombre;
        printf("  %-44s %s\n", $nombre,
            'A MEDIAS (' . $hay . '/' . count($h) . ') falta: ' . implode(', ', array_slice($faltantes, 0, 2)));
    }
}

echo '  ' . str_repeat('-', 74) . "\n\n";
printf("  aplicadas %d · pendientes %d · a medias %d · no determinables %d\n",
    $aplicadas, $faltan, $parciales, $dudosas);

if ($pendiente !== []) {
    echo "\n  Por aplicar, EN ESTE ORDEN:\n";
    foreach ($pendiente as $p) { echo '    ' . $p . "\n"; }
    echo "\n  Aplicar cada una con:\n";
    echo "    mysql -u USUARIO -p --default-character-set=utf8mb4 BASE < ds_core/database/NOMBRE.sql\n";
    echo "\n  El --default-character-set NO es opcional: sin él, el cliente de\n";
    echo "  Windows lee el archivo con la página de códigos de la consola y las\n";
    echo "  tildes se guardan mal. Ocurrió con la 054.\n";
}

if ($dudosas > 0) {
    echo "\n  Las «no determinables» sólo insertan filas y no dejan rastro en el\n";
    echo "  esquema. Antes de reejecutar una, comprobar a mano si sus filas ya\n";
    echo "  están: repetir un INSERT sin protección duplica menús o permisos.\n";
}

echo "\n";
exit($pendiente === [] ? 0 : 1);
