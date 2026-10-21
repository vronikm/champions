<?php
/*
|--------------------------------------------------------------------------
| Captura la fotografía mensual de la cartera
|--------------------------------------------------------------------------
| Uso:
|     php capturar_cartera.php              retrata el mes en curso
|     php capturar_cartera.php 2026-07      el mes anterior, si aun no se hizo
|
| Es idempotente: volver a capturar el mismo mes actualiza la fila en vez de
| duplicarla. Se puede llamar desde el planificador de tareas el día 1 de
| cada mes, o a mano.
|
|
| POR QUÉ EXISTE
|
| La cartera de Basketball no está almacenada: se deduce de la pensión y el
| descuento ACTUALES y de CURDATE(). Comparar la de marzo con la de agosto
| era comparar dos proyecciones hechas desde el mismo instante. Esta captura
| conserva el valor tal como se vio. Ver ds_core/database/046.
|
|
| TRES TIPOS, PORQUE SON TRES PREGUNTAS DISTINTAS
|
|   REGISTRADA   Lo que consta pendiente en documentos ya emitidos. Es un
|                hecho escrito en una fila: pago_saldo, reserva_saldo,
|                obligación menos abonos.
|
|   PROYECTADA   Sólo Basketball. Lo que se infiere que deberían haber
|                pagado y no pagaron: meses transcurridos × pensión. Es una
|                estimación y depende del precio de hoy. Difiere de la
|                registrada en un factor de 124, así que presentarlas
|                juntas sin distinguirlas sería un error de informe.
|
|   RETIRADOS    La deuda de quienes ya no están. La consulta operativa de
|                cartera filtra alumno_estado <> 'I', así que un alumno que
|                se va debiendo desaparece del informe: la deuda no se
|                cancela, se vuelve invisible. Aquí se cuenta aparte, que es
|                lo que se decidió: ni se mezcla con la cartera viva ni se
|                pierde.
|
|   El módulo y la sede acompañan a cada cifra. League va siempre con sede
|   nula porque sus torneos pueden organizarse fuera de las instalaciones
|   del club: no es un hueco, es cómo funciona.
*/

const APP_NAME = 'DigiSports Insights';
require_once __DIR__ . '/../../ds_core/config/app.php';
require_once __DIR__ . '/../config/conexion.php';

/*----------  Conexión  ----------*/
/*
| La conexión de Insights, que rechaza escribir fuera de insights_*. Este
| script lee de alumno_pago, dsa_reserva y dsl_obligacion y escribe sólo en
| insights_cartera_snapshot: encaja exactamente en lo que el candado
| permite, y sirve de prueba viva de que el candado no estorba al trabajo
| legítimo.
*/
$db = InsightsConexion::abrir();

$periodo = $argv[1] ?? date('Y-m');
if (!preg_match('~^\d{4}-\d{2}$~', $periodo)) {
    fwrite(STDERR, "  periodo invalido: se espera AAAA-MM\n");
    exit(1);
}

/*
| NO SE PUEDE RETRATAR EL PASADO, Y HAY QUE IMPEDIRLO
|
| Todas las consultas de abajo leen el estado de HOY: reserva_saldo,
| pago_saldo y la proyeccion con CURDATE() son fotos del momento, no series
| fechadas. Aceptar un periodo pasado guardaria las cifras actuales
| etiquetadas como si fueran de aquel mes, y eso no es un hueco de datos:
| es historia falsa, indistinguible de la verdadera una vez guardada.
|
| Se aprendio probandolo: la primera version aceptaba 2026-03 y escribio
| diez filas con las cifras de agosto.
|
| Se permite el mes en curso. Y el inmediatamente anterior SOLO durante los
| primeros cinco dias, que es cuando una captura programada para el dia 1
| podria ejecutarse con retraso y las cifras aun no se han movido. Pasado
| ese margen, retratar el mes anterior es tan falso como retratar marzo: se
| comprobo escribiendo julio con las cifras del 28 de agosto.
| inmediatamente anterior, cuyas cifras aun no han tenido tiempo de moverse.
*/
$mesActual   = date('Y-m');
$mesAnterior = (int) date('j') <= 5
    ? date('Y-m', strtotime('first day of last month'))
    : null;

if ($periodo !== $mesActual && $periodo !== $mesAnterior) {
    fwrite(STDERR,
        "  El periodo $periodo no se puede retratar.\n" .
        "  Las consultas leen el estado de hoy, asi que guardarlo como $periodo\n" .
        "  inventaria un historico. Solo se admite " . $mesActual .
        ($mesAnterior !== null ? " o $mesAnterior" : " (hoy es dia " . date('j')
         . ", fuera del margen para retratar el mes anterior)") . ".
");
    exit(1);
}

$guardar = $db->prepare(
    "INSERT INTO insights_cartera_snapshot
        (snapshot_periodo, snapshot_modulo, snapshot_tipo, snapshot_sedeid,
         snapshot_valor, snapshot_deudores, snapshot_tomada)
     VALUES (:p, :m, :t, :s, :v, :d, NOW())
     ON DUPLICATE KEY UPDATE
        snapshot_valor    = VALUES(snapshot_valor),
        snapshot_deudores = VALUES(snapshot_deudores),
        snapshot_tomada   = NOW()"
);

$filas = 0;
$eco = function (string $modulo, string $tipo, $sede, float $valor, int $deudores)
       use ($guardar, $periodo, &$filas): void {
    $guardar->execute([':p' => $periodo, ':m' => $modulo, ':t' => $tipo,
                       ':s' => $sede, ':v' => $valor, ':d' => $deudores]);
    printf("  %-11s %-11s sede %-4s %12.2f  (%d deudores)\n",
           $modulo, $tipo, $sede === null ? '—' : $sede, $valor, $deudores);
    $filas++;
};

echo "Cartera del periodo $periodo\n\n";

/*==============  Basketball · registrada, por sede  ==============*/
/* Se agrupa por pago_sedeid, la sede CONGELADA del pago: si el alumno se
   traslada, su deuda anterior sigue contando donde se generó. Ver 044. */
foreach ($db->query(
    "SELECT pago_sedeid s, SUM(pago_saldo) v, COUNT(DISTINCT pago_alumnoid) d
       FROM alumno_pago
      WHERE pago_estado = 'P' AND pago_saldo > 0
      GROUP BY pago_sedeid") as $f) {
    $eco('basketball', 'REGISTRADA', (int) $f['s'], (float) $f['v'], (int) $f['d']);
}

/*==============  Basketball · proyectada, por sede  ==============*/
/*
| La fórmula que usan cobranzaController y reporteController. Se replica
| aquí a propósito y no se factoriza: aquello es la vista operativa del día
| y esto es el histórico. Si mañana cambian la regla de negocio, el
| histórico ya capturado NO debe cambiar con ella.
*/
foreach ($db->query(
    "SELECT B.sede s,
            SUM(CASE WHEN B.fecha > CURDATE() THEN 0 ELSE
                GREATEST(0, TIMESTAMPDIFF(MONTH, B.fecha, CURDATE())
                            + (DAY(CURDATE()) < DAY(B.fecha))) * (B.pension - IFNULL(B.dscto,0))
                END) v,
            COUNT(*) d
       FROM (SELECT MAX(p.pago_fecha) fecha, p.pago_alumnoid,
                    MAX(dc.descuento_valor) dscto, MAX(s.sede_pension) pension,
                    a.alumno_sedeid sede
               FROM sujeto_alumno a
               LEFT JOIN alumno_pago p  ON p.pago_alumnoid = a.alumno_id
               LEFT JOIN alumno_pago_descuento dc
                      ON dc.descuento_alumnoid = a.alumno_id AND dc.descuento_estado = 'S'
               LEFT JOIN general_sede s ON s.sede_id = a.alumno_sedeid
              WHERE p.pago_rubroid = 'RPE' AND a.alumno_estado <> 'I'
              GROUP BY p.pago_alumnoid, a.alumno_sedeid) B
      GROUP BY B.sede") as $f) {
    $eco('basketball', 'PROYECTADA', (int) $f['s'], (float) $f['v'], (int) $f['d']);
}

/*==============  Basketball · deuda de los retirados  ==============*/
/* R12: no se mezcla con la cartera viva, pero tampoco se pierde. */
foreach ($db->query(
    "SELECT p.pago_sedeid s, SUM(p.pago_saldo) v, COUNT(DISTINCT p.pago_alumnoid) d
       FROM alumno_pago p
       JOIN sujeto_alumno a ON a.alumno_id = p.pago_alumnoid
      WHERE p.pago_saldo > 0 AND a.alumno_estado = 'I'
      GROUP BY p.pago_sedeid") as $f) {
    $eco('basketball', 'RETIRADOS', (int) $f['s'], (float) $f['v'], (int) $f['d']);
}

/*==============  Arena · registrada, por sede  ==============*/
foreach ($db->query(
    "SELECT reserva_sedeid s, SUM(reserva_saldo) v, COUNT(DISTINCT reserva_clienteid) d
       FROM dsa_reserva
      WHERE reserva_estado <> 'X' AND reserva_saldo > 0
      GROUP BY reserva_sedeid") as $f) {
    $eco('arena', 'REGISTRADA', (int) $f['s'], (float) $f['v'], (int) $f['d']);
}

/*==============  League · registrada, sin sede  ==============*/
/* Sede nula por definición del negocio: los torneos pueden organizarse
   fuera de las canchas y sedes del club. */
$f = $db->query(
    "SELECT IFNULL(SUM(o.obligacion_valor - o.obligacion_descuento + o.obligacion_recargo
            - IFNULL((SELECT SUM(a.abono_valor) FROM dsl_abono a
                       WHERE a.abono_obligacionid = o.obligacion_id
                         AND a.abono_anulado = 'N'), 0)), 0) v,
            COUNT(DISTINCT o.obligacion_equipoid) d
       FROM dsl_obligacion o
      WHERE o.obligacion_estado <> 'ANULADA'")->fetch();
$eco('league', 'REGISTRADA', null, (float) $f['v'], (int) $f['d']);

echo "\n  $filas filas guardadas para $periodo\n";
