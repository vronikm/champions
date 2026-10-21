<?php
/*
|--------------------------------------------------------------------------
| DigiSports League — portal público
|--------------------------------------------------------------------------
| Entrada SEPARADA de la administración, a propósito.
|
| El index.php del módulo exige sesión antes de cualquier otra cosa, y esa
| garantía tiene valor precisamente porque no admite excepciones. Añadir
| aquí una lista de vistas «públicas» la convertiría en una regla con
| casos especiales, y las reglas con casos especiales acaban dejando pasar
| lo que no debían.
|
| Con dos entradas, el guard de administración sigue siendo absoluto y
| esta superficie tiene el suyo, que es de otra naturaleza: no controla
| QUIÉN entra —entra cualquiera— sino QUÉ puede salir.
|
| Lo que sale está limitado en tres capas:
|
|   · sólo torneos con torneo_publico = 'S', comprobado en cada consulta;
|   · las consultas no seleccionan cédula ni fecha de nacimiento;
|   · las fotografías sólo con consentimiento registrado.
|
| Ninguna de las tres depende de que la vista se acuerde de filtrar.
*/

require_once __DIR__ . "/../config/app.php";
require_once __DIR__ . "/../../ds_core/inc/seguridad.php";
require_once __DIR__ . "/publicoController.php";

use league\publico\publicoController;

/*----------  Cabeceras  ----------
| Sin X-Frame-Options restrictivo: este contenido SÍ se quiere poder
| incrustar, es media parte de para qué existe un portal público. Lo demás
| se mantiene.
*/
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    /* Se permite indexar y compartir: el portal existe para eso. Pero se
       le dice al navegador que no adivine tipos ni cargue nada de fuera
       salvo las imágenes propias. */
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
         . "style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'");
}

/*----------  Rutas  ----------
| /publico/                        índice de torneos
| /publico/t/{slug}/               un torneo y sus categorías
| /publico/c/{id}/                 categoría: posiciones y calendario
| /publico/c/{id}/equipos/         equipos de la categoría
| /publico/c/{id}/lideres/         líderes
| /publico/c/{id}/llaves/          eliminatorias
| /publico/e/{inscripcionId}/      plantilla de un equipo
*/
$ruta  = trim((string)($_GET['ruta'] ?? ''), '/');
$parte = $ruta === '' ? [] : explode('/', $ruta);

$ins = new publicoController();

$seccion = $parte[0] ?? '';
$clave   = $parte[1] ?? '';
$sub     = $parte[2] ?? '';

/* Cada rama valida lo suyo y decide qué vista pintar. Un identificador
   que no corresponda a algo publicado cae en el 404, no en una página
   vacía: decir «no existe» y «no puedes verlo» de la misma forma evita
   revelar qué torneos hay sin publicar. */
$vista = 'inicio';
$datos = [];

switch ($seccion) {

    case 't':
        $torneo = $ins->torneo($clave);
        if (!$torneo) { $vista = '404'; break; }
        $vista = 'torneo';
        $datos = ['torneo' => $torneo,
                  'categorias' => $ins->categorias((int)$torneo['torneo_id']),
                  'campeones'  => $ins->campeones((int)$torneo['torneo_id'])];
        break;

    case 'c':
        $categoria = $ins->categoria((int)$clave);
        if (!$categoria) { $vista = '404'; break; }

        $datos = ['categoria' => $categoria];

        switch ($sub) {
            case 'equipos':
                $vista = 'equipos';
                $datos['equipos'] = $ins->equipos((int)$clave);
                break;
            case 'lideres':
                $vista = 'lideres';
                $datos['rankings'] = [];
                foreach (['PTS' => 'Máximos anotadores', 'REB' => 'Rebotes',
                          'AST' => 'Asistencias', 'ROB' => 'Robos',
                          'TAP' => 'Tapones', 'VAL' => 'Valoración'] as $c => $titulo) {
                    $l = $ins->lideres((int)$clave, $c, 5);
                    if ($l) { $datos['rankings'][$titulo] = $l; }
                }
                break;
            case 'llaves':
                $vista = 'llaves';
                $datos['llaves'] = $ins->llaves((int)$clave);
                break;
            default:
                $vista = 'categoria';
                $datos['grupos']   = $ins->grupos((int)$clave);
                $datos['proximos'] = $ins->partidos((int)$clave, 'proximos', 20);
                $datos['jugados']  = $ins->partidos((int)$clave, 'jugados', 20);
                /* Con grupos, una tabla por grupo; sin ellos, una sola. */
                $datos['tablas'] = [];
                if ($datos['grupos']) {
                    foreach ($datos['grupos'] as $g) {
                        $datos['tablas'][$g['grupo_nombre']] = $ins->tabla((int)$clave, (int)$g['grupo_id']);
                    }
                } else {
                    $datos['tablas'][''] = $ins->tabla((int)$clave);
                }
        }
        break;

    case 'e':
        /* La plantilla comprueba por su cuenta que el torneo esté
           publicado; si no lo está devuelve vacío y aquí se traduce a un
           404, no a una página con un equipo sin jugadores. */
        $plantilla = $ins->plantilla((int)$clave);
        if (!$plantilla) { $vista = '404'; break; }
        $vista = 'plantilla';
        $datos = ['plantilla' => $plantilla, 'inscripcion' => (int)$clave];
        break;

    case '':
        $vista = 'inicio';
        $datos = ['torneos' => $ins->torneos()];
        break;

    default:
        $vista = '404';
}

/* LA CACHÉ SE DECIDE DESPUÉS DE SABER QUÉ SE VA A SERVIR
   ---------------------------------------------------------------------
   Un portal de resultados se consulta mucho y cambia poco, así que un
   minuto de caché quita la mayor parte de la carga sin que nadie note un
   resultado viejo.

   Pero un 404 NO se cachea. Estas páginas devuelven 404 mientras el
   torneo no está publicado, y si esa respuesta quedara guardada, quien lo
   hubiera visitado antes seguiría viendo «no existe» durante un minuto
   después de publicarlo — justo cuando la organización acaba de anunciar
   el enlace. */
if ($vista === '404') {
    http_response_code(404);
    if (!headers_sent()) { header('Cache-Control: no-store'); }
} elseif (!headers_sent()) {
    header('Cache-Control: public, max-age=60');
}

require __DIR__ . "/vistas/" . $vista . ".php";
