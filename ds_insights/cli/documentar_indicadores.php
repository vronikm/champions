<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Generador del documento de indicadores
|--------------------------------------------------------------------------
| Produce un PDF que explica, vista por vista, qué se muestra, de dónde sale
| y con qué fórmula se calcula.
|
|
| POR QUÉ ESTE DOCUMENTO SE GENERA Y NO SE ESCRIBE A MANO
|
| Un documento de fórmulas escrito aparte empieza siendo cierto y deja de
| serlo en la primera corrección. Aquí el contenido vive junto al código y se
| regenera con un comando, así que actualizarlo es barato y se nota cuando no
| se hizo.
|
| Las fórmulas que aparecen abajo se transcribieron del controlador —no de
| la memoria— extrayendo la SQL real de cada método. Están simplificadas para
| que se lean: se omite el filtro de ámbito de sede, que se aplica a TODAS
| por igual y se explica una sola vez en la sección de reglas transversales.
|
|
| Uso:  php ds_insights/cli/documentar_indicadores.php [ruta de salida]
*/

declare(strict_types=1);

$raiz = dirname(__DIR__, 2);
require_once $raiz . '/ds_futbol/app/lib/fpdf.php';

$salida = $argv[1] ?? ($raiz . '/ds_insights/docs/INDICADORES.pdf');

/*==================================================================
| El contenido
|==================================================================*/

$reglas = [
    [
        'titulo' => 'Las tres fuentes de dinero',
        'texto'  => 'Todo importe del sistema sale de una de estas tres tablas, y cada una '
                  . 'marca de forma distinta lo que cuenta como cobrado:',
        'tabla'  => [
            ['Modulo', 'Tabla', 'Cuenta cuando', 'Fecha que usa'],
            ['Fútbol', 'alumno_pago', "pago_estado = 'C'", 'pago_fecha'],
            ['Arena', 'dsa_pago', "pago_estado = 'A'", 'pago_fecha'],
            ['League', 'dsl_abono', "abono_anulado = 'N'", 'abono_fecha'],
        ],
        'nota' => 'Los tres codigos significan lo mismo -el cobro es efectivo- pero se '
                . 'escriben distinto porque cada modulo se construyo por separado. Ninguna '
                . 'cifra de Insights suma un pago que no cumpla su condicion.',
    ],
    [
        'titulo' => 'El periodo',
        'texto'  => 'Salvo que se indique lo contrario, el periodo por omision de cada vista es '
                  . 'el MES EN CURSO: del dia 1 al ultimo del mes. Los filtros de arriba lo '
                  . 'cambian y afectan a todos los indicadores marcados como "del periodo".',
        'nota'   => 'Hay indicadores que NO dependen del periodo y se senalan uno por uno: son '
                  . 'los que miden un estado actual -cuantos alumnos activos hay, cuanto se '
                  . 'debe hoy- y no un flujo entre dos fechas.',
    ],
    [
        'titulo' => 'El ambito de sede',
        'texto'  => 'Si el usuario esta limitado a unas sedes en seguridad_usuario_sede, TODAS '
                  . 'las consultas anaden el filtro correspondiente antes de calcular. No es un '
                  . 'filtro de presentacion: la cifra se calcula ya acotada.',
        'nota'   => 'Por eso dos personas pueden ver numeros distintos en la misma pantalla y '
                  . 'ambos ser correctos. League queda fuera del ambito porque no tiene sede.',
    ],
    [
        'titulo' => 'La sede de un pago es la del momento del cobro',
        'texto'  => 'alumno_pago.pago_sedeid guarda la sede CONGELADA en el instante del pago. '
                  . 'Si un alumno cambia de sede, sus pagos anteriores siguen contando donde se '
                  . 'cobraron.',
        'nota'   => 'Antes se llegaba a la sede a traves del alumno, asi que trasladar a un '
                  . 'alumno reescribia el historico de ingresos de dos sedes a la vez. Arena '
                  . 'hace lo mismo con reserva_sedeid.',
    ],
    [
        'titulo' => 'League no tiene sede',
        'texto'  => 'Sus torneos pueden organizarse fuera de las canchas del club. Por eso no '
                  . 'aparece en ninguna tabla por sede: se rotula aparte y se suma al total, '
                  . 'pero no se atribuye a nadie.',
        'nota'   => 'Repartirlo a prorrateo daria una tabla mas completa y una cifra inventada.',
    ],
];

$vistas = [

/*------------------------------------------------------------------*/
[
'nombre' => 'Panel',
'ruta'   => 'dashboard',
'que'    => 'La vision de los tres modulos en una pantalla: cuanto entro, cuanto falta por '
          . 'cobrar, cuantas operaciones y como va la ocupacion, mas lo que reclama atencion.',
'items'  => [
    ['n' => 'Ingresos cobrados',
     'r' => 'Cuanto dinero entro de verdad en el periodo.',
     'f' => "SUM(alumno_pago.pago_valor) WHERE pago_estado='C'\n"
          . "  + SUM(dsa_pago.pago_valor)  WHERE pago_estado='A'\n"
          . "  + SUM(dsl_abono.abono_valor) WHERE abono_anulado='N'\n"
          . "  ... todos con su fecha BETWEEN desde AND hasta",
     'o' => 'Es la SUMA de los tres modulos al centimo. Si el total no cuadra con las tres '
          . 'tarjetas de abajo, hay un defecto: el arnes lo comprueba en cada barrido.'],

    ['n' => 'Por cobrar',
     'r' => 'Cuanto se debe HOY, sin importar el periodo.',
     'f' => "SUM(alumno_pago.pago_saldo) WHERE pago_estado='P'\n"
          . "  + SUM(dsa_reserva.reserva_saldo) WHERE reserva_estado<>'X'\n"
          . "  + SUM(obligacion_valor - descuento + recargo - abonado) de League",
     'o' => 'NO depende del periodo y NO muestra variacion. Es una proyeccion desde hoy: '
          . 'compararla entre periodos no mide nada, y un porcentaje ahi seria un dato '
          . 'inventado con aspecto de medicion.'],

    ['n' => 'Transacciones',
     'r' => 'Cuantas operaciones de cobro hubo.',
     'f' => "COUNT(*) de las tres tablas con la misma condicion de cobro efectivo\n"
          . "  y su fecha dentro del periodo",
     'o' => 'Cuenta operaciones, no personas: un alumno que paga tres cuotas son tres.'],

    ['n' => 'Ocupacion de Arena',
     'r' => 'Que porcentaje de las horas de apertura se reservo.',
     'f' => "horas reservadas / horas de apertura x 100\n\n"
          . "  reservadas = SUM(reserva_horas) WHERE reserva_estado IN ('U','C')\n"
          . "  apertura   = SUM( duracion_del_horario x veces_que_cae_ese_dia )",
     'o' => 'El denominador NO esta en ninguna tabla: se construye contando cuantas veces cae '
          . 'cada dia de la semana en el periodo y multiplicando por lo que dsa_horario declara '
          . 'para ese dia. La comparacion se expresa en PUNTOS, no en porcentaje: pasar de '
          . '34,8 % a 31,9 % es -2,9 puntos, no -8,3 %.'],

    ['n' => 'Evolucion de ingresos',
     'r' => 'Como se movio el cobro en los ultimos ocho meses.',
     'f' => "GROUP BY DATE_FORMAT(fecha,'%Y-%m') sobre las tres tablas, unidas",
     'o' => 'Lleva limite superior ademas del inferior. Sin el, las reservas de Arena con fecha '
          . 'futura anadian meses por delante y la serie salia con diez columnas en vez de ocho.'],

    ['n' => 'Ingresos por modulo',
     'r' => 'Que peso tiene cada modulo en lo cobrado.',
     'f' => "ingresos del modulo / ingresos totales x 100",
     'o' => 'Cuando no hay cobros en el periodo se dice, en vez de pintar tres barras a cero.'],

    ['n' => 'Requiere tu atencion',
     'r' => 'Que se sale de lo normal ahora mismo.',
     'f' => "seis medidas comparadas contra insights_umbral (ver la vista Indicadores)",
     'o' => 'Hasta la migracion 054 avisaba con "mayor que cero", asi que avisaba siempre. '
          . 'Ahora cada aviso tiene su umbral configurable.'],
],
],

/*------------------------------------------------------------------*/
[
'nombre' => 'Financiero',
'ruta'   => 'financiero',
'que'    => 'De donde viene el dinero: por sede, por concepto, cuanto se deja de cobrar en '
          . 'beneficios y como va la facturacion electronica.',
'items'  => [
    ['n' => 'Ingresos por sede',
     'r' => 'Cuanto cobro cada sede en el periodo.',
     'f' => "SUM(alumno_pago.pago_valor) GROUP BY pago_sedeid\n"
          . "  + SUM(dsa_pago.pago_valor) GROUP BY reserva_sedeid",
     'o' => 'Se usa la sede CONGELADA del pago, no la actual del alumno. Las sedes sin '
          . 'movimiento en el periodo no se listan: una tabla de siete filas a cero no informa.'],

    ['n' => 'Ingresos por concepto',
     'r' => 'Que se esta cobrando, no solo cuanto.',
     'f' => "Fútbol: GROUP BY pago_rubroid, traducido por el catalogo 5\n"
          . "  Arena:      GROUP BY instalacion_clase ('R' = Residencias, resto = Canchas)\n"
          . "  League:     GROUP BY concepto de la obligacion",
     'o' => 'Cada modulo agrupa por lo que tiene: Fútbol por rubro, Arena por tipo de '
          . 'instalacion y League por concepto. No son la misma dimension y por eso van en '
          . 'bloques separados y no en una sola tabla.'],

    ['n' => 'Descuentos y becas',
     'r' => 'Cuanto se deja de cobrar cada mes por beneficios.',
     'f' => "SUM(descuento_valor) GROUP BY rubro, solo descuento_estado='S'\n"
          . "  y alumno_estado='A'",
     'o' => 'Las becas del 100 % se guardan con importe 0,00, asi que ese subsidio NO aparece '
          . 'en esta suma. Se calcula aparte desde la pension de la sede y se rotula como '
          . 'DEDUCIDO: una cosa es un hecho escrito en la fila y otra una consecuencia inferida.'],

    ['n' => 'Ticket promedio',
     'r' => 'Cuanto vale una operacion tipica en cada modulo.',
     'f' => "AVG(valor del pago) por modulo, dentro del periodo",
     'o' => 'Promedio simple, no ponderado. Con pocas operaciones un importe grande lo mueve '
          . 'mucho, y por eso se muestra tambien el numero de operaciones al lado.'],

    ['n' => 'Facturacion electronica',
     'r' => 'En que estado esta lo emitido ante el SRI.',
     'f' => "COUNT(*) y SUM(total) GROUP BY estado_sri, por fecha_emision",
     'o' => 'Se LEE del SRI, no se calcula. El estado importa tanto como el importe: una '
          . 'factura emitida y no autorizada es dinero que el sistema cree facturado y el SRI no.'],
],
],

/*------------------------------------------------------------------*/
[
'nombre' => 'Cartera',
'ruta'   => 'cartera',
'que'    => 'Lo que se debe: de que, de quien y desde cuando.',
'items'  => [
    ['n' => 'Por cobrar',
     'r' => 'Saldo vivo de los tres modulos.',
     'f' => "el mismo calculo que la tarjeta del Panel",
     'o' => 'Esta vista NO tiene filtro de periodo: la deuda es un saldo, no un flujo. No se '
          . 'debe "en agosto", se debe hoy.'],

    ['n' => 'Antiguedad de la deuda',
     'r' => 'Desde cuando se debe cada parte.',
     'f' => "tramos: aun no vencida / hasta 30 / 31-60 / 61-90 / mas de 90 dias\n\n"
          . "  League:     DATEDIFF(hoy, obligacion_vence)\n"
          . "  Arena:      DATEDIFF(hoy, reserva_fecha)\n"
          . "  Fútbol: DATEDIFF(hoy, pago_fecha)",
     'o' => 'SOLO League tiene fecha de vencimiento real. En los otros dos se cuenta desde la '
          . 'fecha del documento porque no hay otra, y la pantalla lo rotula: decir "90 dias de '
          . 'mora" cuando lo que se sabe es "registrado hace 90 dias" seria afirmar mas de lo '
          . 'que el dato aguanta. Las reservas con fecha FUTURA van a su propia columna: no son '
          . 'mora, son dinero que todavia no es exigible.'],

    ['n' => 'Deuda por sede',
     'r' => 'Donde esta la deuda.',
     'f' => "SUM(pago_saldo) y SUM(reserva_saldo) GROUP BY sede",
     'o' => 'League no figura, por no tener sede. Se rotula debajo con su importe.'],

    ['n' => 'Evolucion de la cartera',
     'r' => 'Si la deuda sube o baja mes a mes.',
     'f' => "se LEE de insights_cartera_snapshot, no se recalcula",
     'o' => 'Es la razon de ser de esa tabla. La deuda de marzo no se puede reconstruir hoy: '
          . 'los saldos de marzo que ya se cobraron valen cero ahora, asi que preguntarle a la '
          . 'base devolveria una cifra sistematicamente menor. Se fotografia una vez al mes con '
          . 'cli/capturar_cartera.php.'],

    ['n' => 'Deuda de retirados',
     'r' => 'Cuanto deben los que ya no estan.',
     'f' => "SUM(pago_saldo) WHERE alumno_estado <> 'A'",
     'o' => 'Va en tarjeta APARTE y no dentro del total. Un alumno retirado con saldo no se '
          . 'cobra igual que uno activo, y sumarlos da una cifra que nadie puede gestionar.'],

    ['n' => 'Alumnos activos con saldo',
     'r' => 'A quien hay que cobrarle.',
     'f' => "GROUP BY alumno, con SUM(pago_saldo), COUNT(*) cuotas\n"
          . "  y DATEDIFF(hoy, MIN(pago_fecha)) para el mas antiguo",
     'o' => 'Solo alumnos ACTIVOS, con nombre corto y sin mas datos personales de los '
          . 'necesarios. Ordena por importe, pero la columna de dias esta para ver quien debe '
          . 'poco desde hace mucho, que suele ser el caso a atender primero.'],
],
],

/*------------------------------------------------------------------*/
[
'nombre' => 'Becas y descuentos',
'ruta'   => 'becas',
'que'    => 'Cuanto cuesta el beneficio, a cuantos alcanza, cuanto pagaron aun asi y como asisten.',
'items'  => [
    ['n' => 'Alumnos por tipo de beneficio',
     'r' => 'Cuantos tienen cada beca o descuento.',
     'f' => "COUNT(*) GROUP BY rubro del descuento, solo descuento_estado='S'",
     'o' => 'Se separan activos e inactivos: un descuento vigente sobre un alumno que ya no '
          . 'esta es una anomalia que la propia pantalla senala.'],

    ['n' => 'Valor mensual del beneficio',
     'r' => 'Cuanto se deja de cobrar al mes.',
     'f' => "SUM( CASE WHEN rubro = 'DBC' THEN sede_pension\n"
          . "            ELSE descuento_valor END )",
     'o' => 'Ahi esta el nudo: la Beca 50 % guarda su importe bien -15,00 sobre una pension de '
          . '30,00-, pero la Beca 100 % (DBC) se guarda con 0,00. Para esa se toma la pension '
          . 'de la sede del alumno, y se rotula como DEDUCIDO para no confundirlo con un dato '
          . 'registrado.'],

    ['n' => 'Cuanto pagaron aun asi',
     'r' => 'Si el becado paga algo o nada.',
     'f' => "SUM de sus pagos de pension cobrados, por tipo de beneficio",
     'o' => 'Un becado del 100 % que aparece pagando pension es una anomalia; la pantalla la '
          . 'lista y excluye los pagos ANTERIORES a la concesion de la beca, que no lo son.'],

    ['n' => 'Asistencia por tipo de beneficio',
     'r' => 'Si el beneficio se relaciona con la asistencia.',
     'f' => "marcas 'P' o 'A' / total de marcas x 100, por grupo",
     'o' => 'Los grupos son de tamano MUY distinto: 150 alumnos sin beneficio frente a 7 con '
          . 'beca completa. Con siete alumnos, unas pocas faltas mueven el porcentaje varios '
          . 'puntos. Por eso se muestra el numero de marcas de cada grupo: para que no se lea '
          . 'una diferencia como hallazgo cuando puede ser ruido.'],
],
],

/*------------------------------------------------------------------*/
[
'nombre' => 'Fútbol',
'ruta'   => 'basketball',
'que'    => 'Alumnos, asistencia, retencion y cumplimiento de pago de la escuela formativa.',
'items'  => [
    ['n' => 'Alumnos',
     'r' => 'Cuantos hay y en que estado.',
     'f' => "COUNT(*), SUM(alumno_estado='A'), SUM(alumno_estado='I'), resto",
     'o' => 'El estado no depende del periodo; las altas del periodo si, y se cuentan por '
          . 'alumno_fechaingreso.'],

    ['n' => 'Alumnos por sede',
     'r' => 'Como se reparten y que edad media tienen.',
     'f' => "COUNT(*), SUM(activos), ROUND(AVG(edad),1) GROUP BY sede",
     'o' => 'La edad sale de alumno_fechanacimiento; los que no la tienen no entran en el '
          . 'promedio, y eso se ve en el numero de alumnos con anomalias.'],

    ['n' => 'Alumnos por ano de nacimiento',
     'r' => 'Como se distribuyen las categorias.',
     'f' => "COUNT(*) GROUP BY YEAR(alumno_fechanacimiento)\n"
          . "  con la edad acotada entre 3 y 60 anos",
     'o' => 'Se agrupa por ANO DE NACIMIENTO y no por categoria del torneo: la categoria es una '
          . 'decision de cada competencia y puede no tener alumnos inscritos. El acotado de edad '
          . 'descarta fechas imposibles cargadas por error.'],

    ['n' => 'Retencion por cohorte',
     'r' => 'Cuantos de los que entraron en cada mes siguen.',
     'f' => "SUM(alumno_estado='A') / COUNT(*) x 100\n"
          . "  GROUP BY DATE_FORMAT(alumno_fechaingreso,'%Y-%m')",
     'o' => 'Las cohortes recientes tienen poco recorrido y su retencion alta no significa lo '
          . 'mismo que la de una antigua. La columna de meses transcurridos esta para leerlo bien.'],

    ['n' => 'Asistencia',
     'r' => 'Que porcentaje de las marcas fue presencia.',
     'f' => "marcas 'P' (presente) o 'A' (atraso) / total de marcas x 100",
     'o' => 'El atraso CUENTA como asistencia: el alumno estuvo. Se lee de la vista '
          . 'insights_v_asistencia_dia, que despivota las 31 columnas de asistencia_asistencia '
          . 'en una fila por dia.'],

    ['n' => 'Inasistencias avisadas',
     'r' => 'De las faltas, cuantas aviso el representante.',
     'f' => "marcas 'J' / (marcas 'F' + marcas 'J') x 100",
     'o' => 'La justificada SIGUE siendo una falta -el alumno no estuvo- pero mide algo '
          . 'distinto: que la familia comunico. Por eso es un indicador propio y no se suma a '
          . 'la asistencia.'],

    ['n' => 'Alumnos por entrenador',
     'r' => 'Cuantos atiende cada uno y con que asistencia.',
     'f' => "dos pasos: primero el conjunto DISTINCT de alumnos por profesor,\n"
          . "  despues sus marcas contadas UNA sola vez",
     'o' => 'Se hace en dos pasos por una razon concreta: asistencia_horario_detalle tiene una '
          . 'fila por dia (cinco por horario), asi que un JOIN directo multiplicaba las marcas '
          . 'por 4,4 -2.885 donde hay 1.245-.'],

    ['n' => 'Cumplimiento de pago',
     'r' => 'Cuantas cuotas pago cada alumno de las que le tocaban.',
     'f' => "cuotas pagadas / meses desde el ingreso x 100",
     'o' => 'Excluye a los becados del 100 %: contarlos como incumplidores fue un error real de '
          . 'la primera version, que marcaba como morosos a seis alumnos que no debian nada.'],
],
],

/*------------------------------------------------------------------*/
[
'nombre' => 'Arena',
'ruta'   => 'arena',
'que'    => 'Reservas, ocupacion real de cada instalacion y en que horas se llena.',
'items'  => [
    ['n' => 'Reservas del periodo',
     'r' => 'Cuantas y cuanto valen.',
     'f' => "COUNT(*), efectivas = estado IN ('U','C'), canceladas = estado 'X',\n"
          . "  facturado = SUM(reserva_total) de las no canceladas",
     'o' => 'Las canceladas se cuentan pero no facturan. El saldo vivo sale del mismo bloque.'],

    ['n' => 'Ocupacion',
     'r' => 'Que parte de la capacidad se uso.',
     'f' => "SUM(reserva_horas) / horas de apertura x 100\n\n"
          . "  apertura = SUM( TIME_TO_SEC(hasta - desde)/3600 x veces_del_dia )",
     'o' => 'Verificado a mano sobre una instalacion: Cancha Central, 151 horas reservadas '
          . 'sobre 414 de apertura = 36,5 %, y la pantalla dice 36,5 %.'],

    ['n' => 'Ocupacion por instalacion',
     'r' => 'Cual se usa y cual esta parada.',
     'f' => "el mismo calculo, GROUP BY instalacion\n"
          . "  ingreso por hora = SUM(reserva_total) / SUM(reserva_horas)",
     'o' => 'El ingreso por hora es lo que distingue una cancha muy usada y barata de una poco '
          . 'usada y cara. Las dos pueden facturar lo mismo.'],

    ['n' => 'Mapa de calor',
     'r' => 'Que dias y horas se llenan.',
     'f' => "ocupadas / disponibles por celda (dia de la semana x hora)\n"
          . "  disponibles = instalaciones abiertas esa hora x veces que cae ese dia",
     'o' => 'El calendario de dias ya no se genera en SQL: contar cuantos lunes hay entre dos '
          . 'fechas es aritmetica, y hacerlo en PHP bajo esta consulta de 112,7 a 8,2 ms.'],

    ['n' => 'Anomalias de Arena',
     'r' => 'Configuracion que no cuadra.',
     'f' => "instalaciones activas sin horario, reservas fuera de horario,\n"
          . "  instalaciones sin tarifa, clientes con saldo",
     'o' => 'Una reserva fuera del horario declarado no es un error de calculo: es una reserva '
          . 'que el sistema acepto y que el horario dice que no deberia existir.'],
],
],

/*------------------------------------------------------------------*/
[
'nombre' => 'League',
'ruta'   => 'league',
'que'    => 'Torneos, equipos, partidos, tabla de posiciones y cobranza de la liga.',
'items'  => [
    ['n' => 'Partidos',
     'r' => 'Cuantos se jugaron y cuantos faltan.',
     'f' => "jugados = estado_efectivo='S'; pendientes = estado_final='N';\n"
          . "  mas suspendidos y cancelados por su codigo de estado",
     'o' => 'El estado NO se codifica en el controlador: se lee de dsl_estado, que es el '
          . 'catalogo central de transiciones. Por eso anadir un estado no obliga a tocar '
          . 'Insights.'],

    ['n' => 'Recaudado por torneo',
     'r' => 'Cuanto ha ingresado cada competencia.',
     'f' => "SUM(abono_valor) de los abonos no anulados, por torneo",
     'o' => 'NO se filtra por periodo, a diferencia de casi todo lo demas. La recaudacion de un '
          . 'torneo es una propiedad del torneo, no del mes: filtrarla hacia que la tarjeta '
          . 'dijera 0,00 mientras la tabla de al lado decia 2.250,00.'],

    ['n' => 'Tabla de posiciones',
     'r' => 'Como va la clasificacion de una categoria.',
     'f' => "puntos = victorias x ptsvictoria + walkovers x ptswalkover\n"
          . "            + derrotas x ptsderrota\n"
          . "  desempate: diferencia de puntos, luego puntos a favor",
     'o' => 'Los puntos por victoria, derrota y walkover se leen de la CATEGORIA, no se '
          . 'suponen: cada competencia puede puntuar distinto. Solo entran partidos con '
          . 'estado_efectivo = "S".'],

    ['n' => 'Cartera de League',
     'r' => 'Cuanto se debe de inscripciones y multas.',
     'f' => "SUM( obligacion_valor - descuento + recargo - abonado )\n"
          . "  sobre obligaciones no anuladas",
     'o' => 'Es el unico modulo con fecha de vencimiento real (obligacion_vence), asi que su '
          . 'antiguedad de deuda es la mas fiable de los tres.'],
],
],

/*------------------------------------------------------------------*/
[
'nombre' => 'Transacciones',
'ruta'   => 'transacciones',
'que'    => 'El detalle pago a pago de los tres modulos: el ultimo salto del drill-down.',
'items'  => [
    ['n' => 'La lista',
     'r' => 'Que pago cada persona, cuando y por que concepto.',
     'f' => "UNION ALL de las tres tablas, normalizadas a las mismas columnas:\n"
          . "  fecha, modulo, sede, concepto, quien, referencia, valor",
     'o' => 'Es la UNICA pantalla de Insights que muestra pagos de personas identificadas. Por '
          . 'eso tiene entrada de menu propia y su permiso de ver se concede por separado, y '
          . 'por eso registra en la bitacora quien la consulto y con que periodo.'],

    ['n' => 'Total y suma del filtro',
     'r' => 'Cuantas operaciones y cuanto suman en TODO el filtro.',
     'f' => "COUNT(*) y SUM(valor) sobre la union completa, antes de paginar",
     'o' => 'La suma es la del filtro entero, no la de la pagina visible. Es una distincion que '
          . 'confunde a menudo y por eso la pantalla lo dice.'],

    ['n' => 'Paginacion',
     'r' => 'Como se recorren 5.499 filas sin colgar el navegador.',
     'f' => "LIMIT por pagina OFFSET (pagina-1) x por_pagina, en el SERVIDOR",
     'o' => 'Por eso esta tabla no lleva el buscador de DataTables: buscaria solo dentro de la '
          . 'pagina visible y daria la impresion de haber mirado en todas. Con el filtro de '
          . 'modulo puesto, la union se arma con una rama en vez de tres.'],
],
],

/*------------------------------------------------------------------*/
[
'nombre' => 'Indicadores',
'ruta'   => 'configuracion',
'que'    => 'Desde que numero el panel levanta la mano.',
'items'  => [
    ['n' => 'Umbral por aviso',
     'r' => 'Cuando merece la pena avisar.',
     'f' => "el aviso aparece si  medida >= umbral_valor  y  umbral_estado = 'A'",
     'o' => 'Los seis avisos disparaban antes con "mayor que cero". Con 266 alumnos eso '
          . 'significa que el panel avisa SIEMPRE, y uno que avisa siempre no avisa de nada. Se '
          . 'sembraron con valor 1 para reproducir exactamente el comportamiento anterior: '
          . 'primero configurable, despues la escuela decide sus numeros.'],

    ['n' => 'Desactivar frente a subir el umbral',
     'r' => 'Dos formas distintas de callar un aviso.',
     'f' => "umbral_estado = 'I' lo silencia siempre;\n"
          . "  un valor alto lo silencia mientras la medida no llegue",
     'o' => 'No son lo mismo y por eso son dos controles: desactivar se lee como una decision.'],

    ['n' => 'Que NO hace un umbral',
     'r' => 'Aclaracion importante.',
     'f' => "-",
     'o' => 'Subir un umbral no oculta ninguna cifra. Las pantallas siguen mostrandolo todo: la '
          . 'deuda de Arena se sigue viendo en Cartera aunque su aviso este callado. Lo unico '
          . 'que cambia es cuando el panel llama la atencion.'],

    ['n' => 'Si un umbral desaparece',
     'r' => 'Que pasa si se borra una fila por error.',
     'f' => "sin fila en insights_umbral, el aviso vuelve a la condicion > 0",
     'o' => 'Deliberado: un umbral borrado por error no puede tener el efecto de silenciar algo '
          . 'en silencio. Falla hacia el lado ruidoso.'],
],
],

/*------------------------------------------------------------------*/
[
'nombre' => 'Centro de reportes y Exportar',
'ruta'   => 'reporteList',
'que'    => 'El catalogo de lo que se puede consultar, y la descarga en CSV o PDF.',
'items'  => [
    ['n' => 'El catalogo',
     'r' => 'Que informes existen y donde estan.',
     'f' => "-",
     'o' => 'Enlaza a los once reportes que Fútbol ya tenia en vez de reimplementarlos, y '
          . 'los marca como externos. Duplicarlos habria hecho que dos pantallas dieran cifras '
          . 'distintas de lo mismo. Solo se ofrece lo que el usuario puede abrir: una tarjeta '
          . 'que lleva a un 403 no informa, solo ensena que existe a quien no deberia saberlo.'],

    ['n' => 'La exportacion',
     'r' => 'Llevarse los datos a Excel.',
     'f' => "los MISMOS metodos que pintan la pantalla, sin consulta aparte",
     'o' => 'Si el tablero dice 33,5 %, el archivo dice 33,5 %. El CSV lleva BOM UTF-8 y '
          . 'separador ";" porque en Ecuador la coma es el separador DECIMAL: con "," Excel '
          . 'parte los importes en dos columnas, y sin BOM abre "Ocupacion" como "OcupaciA3n". '
          . 'Exportar exige su propio permiso: ver una cifra y sacarla del sistema son dos '
          . 'decisiones distintas.'],
],
],

];

/*==================================================================
| El PDF
|==================================================================*/

/** FPDF habla latin1; el documento entero esta en UTF-8. */
function t(string $s): string
{
    return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?: $s;
}

class Doc extends FPDF
{
    public string $seccion = '';

    function Header(): void
    {
        if ($this->PageNo() === 1) { return; }

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 6, t('DigiSports Insights - Indicadores y formulas'), 0, 0, 'L');
        $this->Cell(0, 6, t($this->seccion), 0, 1, 'R');
        $this->SetDrawColor(220, 220, 220);
        $this->Line(15, 22, $this->GetPageWidth() - 15, 22);
        $this->Ln(6);
        $this->SetTextColor(0, 0, 0);
    }

    function Footer(): void
    {
        if ($this->PageNo() === 1) { return; }

        $this->SetY(-15);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 10, t('Pagina ' . $this->PageNo()), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }
}

$pdf = new Doc('P', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetMargins(15, 15, 15);
$ancho = $pdf->GetPageWidth() - 30;

/*----------  Portada  ----------*/
$pdf->AddPage();
$pdf->Ln(55);

$pdf->SetFont('Helvetica', 'B', 26);
$pdf->Cell(0, 12, t('DigiSports Insights'), 0, 1);

$pdf->SetFont('Helvetica', '', 15);
$pdf->SetTextColor(90, 90, 90);
$pdf->Cell(0, 9, t('Indicadores y formulas, vista por vista'), 0, 1);

$pdf->Ln(4);
$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $pdf->GetY(), 90, $pdf->GetY());
$pdf->Ln(10);

$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(60, 60, 60);
$pdf->MultiCell($ancho, 5.5, t(
    'Que informacion presenta cada pantalla, de donde sale y con que formula se calcula.'
  . "\n\n"
  . 'Las formulas se transcribieron del controlador, no de la memoria: se extrajo la SQL real '
  . 'de cada metodo. Estan simplificadas para que se lean -se omite el filtro de ambito de '
  . 'sede, que se aplica a todas por igual y se explica una sola vez-.'
  . "\n\n"
  . 'Cada indicador lleva un apartado "Ojo" con lo que conviene saber para no leerlo mal. Ahi '
  . 'estan las trampas reales: los atrasos que cuentan como asistencia, las becas del 100 % que '
  . 'se guardan con importe cero, la deuda pasada que no se puede reconstruir.'
), 0, 'L');

$pdf->Ln(14);
$pdf->SetFont('Helvetica', '', 9);
$pdf->SetTextColor(130, 130, 130);
$pdf->Cell(0, 5, t('Generado el ' . date('d/m/Y')
    . ' por ds_insights/cli/documentar_indicadores.php'), 0, 1);
$pdf->Cell(0, 5, t(count($vistas) . ' vistas documentadas'), 0, 1);

/*----------  Reglas transversales  ----------*/
$pdf->seccion = 'Reglas que afectan a todo';
$pdf->AddPage();

$pdf->SetFont('Helvetica', 'B', 16);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 10, t('Reglas que afectan a todas las cifras'), 0, 1);
$pdf->Ln(2);

$pdf->SetFont('Helvetica', '', 10);
$pdf->SetTextColor(70, 70, 70);
$pdf->MultiCell($ancho, 5, t(
    'Antes de la primera pantalla conviene tener claras cinco cosas. Explican por que dos '
  . 'personas pueden ver numeros distintos y por que algunos indicadores no cambian al mover '
  . 'las fechas.'), 0, 'L');
$pdf->Ln(6);

foreach ($reglas as $r) {
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->SetTextColor(20, 20, 20);
    $pdf->MultiCell($ancho, 6, t($r['titulo']), 0, 'L');

    $pdf->SetFont('Helvetica', '', 9.5);
    $pdf->SetTextColor(60, 60, 60);
    $pdf->MultiCell($ancho, 4.8, t($r['texto']), 0, 'L');
    $pdf->Ln(1.5);

    if (isset($r['tabla'])) {
        $cols = [30, 34, 52, 34];
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetFillColor(240, 243, 247);
        $pdf->SetTextColor(40, 40, 40);
        foreach ($r['tabla'][0] as $i => $h) {
            $pdf->Cell($cols[$i], 6, t($h), 1, 0, 'L', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Courier', '', 8);
        $pdf->SetTextColor(60, 60, 60);
        foreach (array_slice($r['tabla'], 1) as $fila) {
            foreach ($fila as $i => $c) {
                $pdf->Cell($cols[$i], 5.5, t($c), 1, 0, 'L');
            }
            $pdf->Ln();
        }
        $pdf->Ln(2);
    }

    if (isset($r['nota'])) {
        $pdf->SetFont('Helvetica', 'I', 9);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell($ancho, 4.6, t($r['nota']), 0, 'L');
    }

    $pdf->Ln(6);
}

$desbordes = [];

/*----------  Una seccion por vista  ----------*/
foreach ($vistas as $v) {
    $pdf->seccion = $v['nombre'];
    $pdf->AddPage();

    $pdf->SetFont('Helvetica', 'B', 16);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, t($v['nombre']), 0, 1);

    $pdf->SetFont('Courier', '', 9);
    $pdf->SetTextColor(140, 140, 140);
    $pdf->Cell(0, 5, t('/ds_insights/' . $v['ruta'] . '/'), 0, 1);
    $pdf->Ln(3);

    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(70, 70, 70);
    $pdf->MultiCell($ancho, 5, t($v['que']), 0, 'L');
    $pdf->Ln(5);

    foreach ($v['items'] as $it) {
        /* Un indicador no se parte entre paginas si cabe entero. */
        if ($pdf->GetY() > 225) { $pdf->AddPage(); }

        $pdf->SetFont('Helvetica', 'B', 11.5);
        $pdf->SetTextColor(15, 15, 15);
        $pdf->MultiCell($ancho, 6, t($it['n']), 0, 'L');

        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell($ancho, 4.8, t('Responde:  ' . $it['r']), 0, 'L');
        $pdf->Ln(1.5);

        if ($it['f'] !== '-') {
            /*
            | El recuadro se dibuja con una altura calculada a partir de los
            | saltos de linea, asi que una linea que no quepa se saldria por
            | debajo sin avisar. Se mide con la fuente real antes de pintar.
            */
            $pdf->SetFont('Courier', '', 8.2);
            foreach (explode("
", $it['f']) as $linea) {
                if ($pdf->GetStringWidth(t($linea)) > $ancho - 6) {
                    $desbordes[] = $v['nombre'] . ' / ' . $it['n'] . ': ' . $linea;
                }
            }

            $y = $pdf->GetY();
            $lineas = substr_count($it['f'], "\n") + 1;
            $alto   = $lineas * 4.2 + 4;

            $pdf->SetFillColor(246, 248, 251);
            $pdf->SetDrawColor(226, 232, 240);
            $pdf->Rect(15, $y, $ancho, $alto, 'FD');

            $pdf->SetXY(18, $y + 2);
            $pdf->SetFont('Courier', '', 8.2);
            $pdf->SetTextColor(45, 55, 72);
            $pdf->MultiCell($ancho - 6, 4.2, t($it['f']), 0, 'L');
            $pdf->SetY($y + $alto + 2);
        }

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(120, 90, 30);
        $pdf->MultiCell($ancho, 4.6, t('Ojo:  ' . $it['o']), 0, 'L');

        $pdf->Ln(4);
        $pdf->SetDrawColor(235, 235, 235);
        $pdf->Line(15, $pdf->GetY(), 15 + $ancho, $pdf->GetY());
        $pdf->Ln(4);
    }
}

$pdf->Output('F', $salida);

printf("  %s\n  %d KB\n", $salida, (int) (filesize($salida) / 1024));

/*
| Si alguna linea de formula no cabia en su recuadro, se dice y se falla.
| El recuadro se dibuja con una altura calculada a partir de los saltos de
| linea, asi que una linea larga se saldria por debajo y pisaria el texto
| siguiente. Callarlo produciria un PDF de aspecto correcto y contenido
| ilegible justo en la parte que mas importa.
*/
if ($desbordes !== []) {
    fwrite(STDERR, "\n  AVISO: " . count($desbordes) . " linea(s) de formula se salen del recuadro:\n");
    foreach ($desbordes as $d) {
        fwrite(STDERR, '    ' . $d . "\n");
    }
    exit(1);
}

echo "  ninguna formula se sale de su recuadro\n";
