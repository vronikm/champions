<?php
/*
| Alta y edición de una reserva.
| El importe se calcula por horas × tarifa; el servidor vuelve a calcularlo
| y a comprobar disponibilidad al guardar, así que lo que se muestra aquí
| es una previsualización, no la fuente de verdad.
|
| Va en pasos —cliente, instalación y fecha, horario— y la barra de abajo
| lleva siempre a la vista el importe y «Guardar».
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$id      = (int)($_GET['id'] ?? 0);
$reserva = $id > 0 ? $insArena->reserva($id) : null;
$esAlta  = ($reserva === null);

if ($esAlta && !puede_crear('reservaList'))  { require_once __DIR__ . "/accesoDenegado-view.php"; exit(); }
if (!$esAlta && !puede_editar('reservaList')) { require_once __DIR__ . "/accesoDenegado-view.php"; exit(); }

$tituloVista = $esAlta ? 'Nueva reserva' : 'Editar reserva ' . $reserva['reserva_codigo'];
$vistaActual = 'reservaList';

/* Los clientes ya NO se cargan todos: se buscan (buscarClientes). Aquí sólo
   cuántos hay, y el elegido: el de la reserva al editar, o el que llega
   recién creado desde «Crear cliente» (?cliente=). */
$totalClientes = $insArena->totalClientes();
$clienteId     = (int)($reserva['reserva_clienteid'] ?? ($_GET['cliente'] ?? 0));
$clienteSel    = $clienteId > 0 ? $insArena->cliente($clienteId) : null;
if ($clienteSel && $clienteSel['cliente_estado'] === 'E' && $esAlta) { $clienteSel = null; }
$instalaciones = $insArena->instalaciones();
$dias          = $insArena->dias();

/* Tarifas y disponibilidad de cada instalación, para la previsualización
   en el navegador. La validación real ocurre en el servidor. */
$datosInst = [];
foreach ($instalaciones as $i) {
    $franjas = [];
    foreach ($insArena->horarios((int)$i['instalacion_id']) as $h) {
        $franjas[] = ['dia'   => (int)$h['horario_dia'],
                      'desde' => substr($h['horario_desde'], 0, 5),
                      'hasta' => substr($h['horario_hasta'], 0, 5)];
    }
    $datosInst[(int)$i['instalacion_id']] = [
        'valorhora' => (float)$i['instalacion_valorhora'],
        'franjas'   => $franjas,
    ];
}

$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if (!$totalClientes || !$instalaciones): ?>

    <div class="aviso-superadmin">
        <i class="fas fa-info-circle fa-lg mt-1"></i>
        <div>
            <strong>Falta información básica para reservar.</strong><br>
            <?php if (!$instalaciones): ?>
                No hay instalaciones registradas. <a href="<?php echo APP_URL; ?>instalacionList/">Crear una →</a><br>
            <?php endif; ?>
            <?php if (!$totalClientes): ?>
                No hay clientes registrados. <a href="<?php echo APP_URL; ?>clienteForm/?volver=reservaForm">Crear uno →</a>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
        <form class="FormularioAjax ar-reserva" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php" autocomplete="off">
            <input type="hidden" name="modulo_arena" value="guardarReserva">
            <input type="hidden" name="reserva_id" value="<?php echo $id; ?>">

            <!-- ============ 1. Cliente ============ -->
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><span class="ar-paso">1</span>Cliente</h3></div>
                <div class="card-body">
                    <?php /*
                    | EL CLIENTE SE BUSCA, NO SE ELIGE DE UNA LISTA
                    |
                    | Era un <select> con todos los clientes. Ahora se escribe parte
                    | del nombre, la cédula o el celular y el servidor devuelve los
                    | que coinciden. Con la caja vacía salen los recientes. Si no
                    | está, «Crear cliente» lo da de alta y vuelve aquí con él
                    | elegido. El id viaja en el campo oculto reserva_clienteid.
                    */ ?>
                    <input type="hidden" name="reserva_clienteid" id="reserva_clienteid" value="<?php echo $clienteSel ? (int)$clienteSel['cliente_id'] : ''; ?>">

                    <div class="ar-cli__elegido" id="cliElegido"<?php echo $clienteSel ? '' : ' hidden'; ?>>
                        <span class="ar-cli__avatar" id="cliAvatar" aria-hidden="true"><?php
                            echo $clienteSel ? $h(mb_strtoupper(mb_substr(trim(html_entity_decode($clienteSel['cliente_nombre'])), 0, 1))) : ''; ?></span>
                        <span class="ar-cli__datos">
                            <strong id="cliNombre"><?php echo $clienteSel ? $h($clienteSel['cliente_nombre']) : ''; ?></strong>
                            <span id="cliDetalle"><?php echo $clienteSel ? $h(trim($clienteSel['cliente_identificacion'] . ($clienteSel['cliente_celular'] ? ' · ' . $clienteSel['cliente_celular'] : ''))) : ''; ?></span>
                        </span>
                        <button type="button" class="btn btn-sm btn-secondary" id="cliCambiar"><i class="fas fa-arrow-right-arrow-left me-1" aria-hidden="true"></i>Cambiar</button>
                    </div>

                    <div class="ar-cli__buscar" id="cliBuscar"<?php echo $clienteSel ? ' hidden' : ''; ?>>
                        <label for="cliQ" class="form-label fw-medium">Buscar cliente <span class="text-danger" aria-hidden="true">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
                            <input type="search" class="form-control" id="cliQ" placeholder="Nombre, cédula o celular"
                                   role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="cliLista"
                                   autocomplete="off" spellcheck="false" enterkeyhint="search">
                        </div>
                        <div class="ar-cli__panel" id="cliPanel" hidden>
                            <div class="ar-cli__cabecera" id="cliCabecera"></div>
                            <ul class="ar-cli__lista" id="cliLista" role="listbox" aria-label="Clientes"></ul>
                            <a class="ar-cli__crear" id="cliCrear" href="<?php echo APP_URL; ?>clienteForm/?volver=reservaForm">
                                <i class="fas fa-user-plus me-2" aria-hidden="true"></i><span>Crear cliente nuevo</span>
                            </a>
                        </div>
                        <div class="form-text"><?php echo number_format($totalClientes, 0, ',', '.'); ?> clientes. Escriba al menos dos letras o números.</div>
                    </div>
                </div>
            </div>

            <!-- ============ 2. Instalación y fecha ============ -->
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title"><span class="ar-paso">2</span>Instalación y fecha</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="reserva_instalacionid" class="form-label fw-medium">Instalación <span class="text-danger" aria-hidden="true">*</span></label>
                        <select class="form-select" id="reserva_instalacionid" name="reserva_instalacionid" required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($instalaciones as $i): ?>
                                <option value="<?php echo (int)$i['instalacion_id']; ?>"
                                    <?php echo (int)($reserva['reserva_instalacionid'] ?? 0) === (int)$i['instalacion_id'] || (count($instalaciones) === 1 && $esAlta) ? 'selected' : ''; ?>>
                                    <?php echo $h($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre']); ?>
                                    — $<?php echo number_format((float)$i['instalacion_valorhora'], 2); ?>/h
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted" id="avisoFranjas"></small>
                    </div>

                    <?php
                    /*
                    | FECHA Y HORARIO: SE ELIGE, NO SE ESCRIBE
                    |
                    | Antes eran dos campos de hora libres. Se podía teclear cualquier
                    | hora y sólo al guardar llegaba el rechazo: fuera de horario,
                    | sobre otra reserva o sobre un mantenimiento. Ahora la pantalla
                    | pide al servidor los bloques del día (consultarDisponibilidad,
                    | con las mismas reglas que verifica al guardar) y sólo deja
                    | elegir los libres. «Desde» y «Hasta» viajan en campos ocultos
                    | que rellena la selección.
                    */
                    ?>
                    <label for="reserva_fecha" class="form-label fw-medium">Fecha <span class="text-danger" aria-hidden="true">*</span></label>
                    <div class="ar-fecha">
                        <input type="date" class="form-control" id="reserva_fecha" name="reserva_fecha" required
                               <?php if ($esAlta): ?>min="<?php echo date('Y-m-d'); ?>"<?php endif; ?>
                               value="<?php echo $h($reserva['reserva_fecha'] ?? date('Y-m-d')); ?>">
                        <div class="ar-dias" id="tiraDias" role="group" aria-label="Próximos días"></div>
                    </div>
                </div>
            </div>

            <input type="hidden" id="reserva_horainicio" name="reserva_horainicio"
                   value="<?php echo substr((string)($reserva['reserva_horainicio'] ?? ''), 0, 5); ?>">
            <input type="hidden" id="reserva_horafin" name="reserva_horafin"
                   value="<?php echo substr((string)($reserva['reserva_horafin'] ?? ''), 0, 5); ?>">

            <!-- ============ 3. Horario ============ -->
            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h3 class="card-title" id="agendaTitulo"><span class="ar-paso">3</span>Horario</h3>
                    <div class="ar-leyenda" aria-hidden="true">
                        <span><i class="ar-muestra ar-bloque--libre"></i>Libre</span>
                        <span><i class="ar-muestra ar-bloque--sel"></i>Elegido</span>
                        <span><i class="ar-muestra ar-bloque--ocupado"></i>Ocupado</span>
                        <span><i class="ar-muestra ar-bloque--bloqueado"></i>Bloqueado</span>
                        <span><i class="ar-muestra ar-bloque--pasado"></i>Ya pasó</span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2" id="agendaAyuda">
                        Toque la hora de inicio. Para reservar más de una hora, toque después la última hora que necesita.
                    </p>
                    <div class="ar-vacio" id="agendaEstado" role="status" aria-live="polite"></div>
                    <div id="agendaBloques" role="group" aria-labelledby="agendaTitulo"></div>
                    <div class="ar-resumen" id="agendaResumen" aria-live="polite" hidden></div>

                    <label for="reserva_observacion" class="form-label fw-medium mt-3">Observación</label>
                    <textarea class="form-control" id="reserva_observacion" name="reserva_observacion"
                              rows="2" maxlength="250" placeholder="Opcional"><?php echo $h($reserva['reserva_observacion'] ?? ''); ?></textarea>

                    <?php if (!$esAlta && (float)$reserva['reserva_abonado'] > 0): ?>
                        <div class="aviso-superadmin mt-3">
                            <i class="fas fa-coins fa-lg mt-1"></i>
                            <div>
                                Esta reserva ya tiene <strong>$<?php echo number_format((float)$reserva['reserva_abonado'], 2); ?></strong> abonados.
                                El nuevo importe no puede quedar por debajo de esa cifra.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Importe y guardar, siempre a la vista -->
            <div class="ar-barra">
                <span class="ar-barra__pista" id="barraPista">Elija un horario</span>
                <div class="ar-barra__importe" id="cajaImporte" style="display:none;" aria-live="polite">
                    <span class="ar-barra__total" id="importeTotal">$0.00</span>
                    <span class="ar-barra__detalle" id="importeDetalle"></span>
                </div>
                <a href="<?php echo APP_URL; ?>reservaList/" class="btn btn-secondary" aria-label="Cancelar"><i class="fas fa-xmark me-1" aria-hidden="true"></i><span class="ar-barra__txt">Cancelar</span></a>
                <button type="submit" class="btn btn-success"><i class="fas fa-floppy-disk me-1" aria-hidden="true"></i><?php echo $esAlta ? 'Reservar' : 'Guardar'; ?></button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var datos = <?php echo json_encode($datosInst, JSON_UNESCAPED_UNICODE); ?>;
    var dias  = <?php echo json_encode($dias, JSON_UNESCAPED_UNICODE); ?>;

    var inst   = document.getElementById('reserva_instalacionid');
    var fecha  = document.getElementById('reserva_fecha');
    var desde  = document.getElementById('reserva_horainicio');
    var hasta  = document.getElementById('reserva_horafin');
    var caja   = document.getElementById('cajaImporte');
    var total  = document.getElementById('importeTotal');
    var detalle= document.getElementById('importeDetalle');
    var aviso  = document.getElementById('avisoFranjas');
    var pista  = document.getElementById('barraPista');   /* «Elija un horario», mientras no hay importe */

    var URL_AJAX  = '<?php echo APP_URL; ?>ajax/arenaAjax.php';
    var HOY       = '<?php echo date('Y-m-d'); ?>';
    var RESERVA   = <?php echo (int)$id; ?>;
    /* El horario que ya tenía la reserva, para marcarlo al editar. */
    var ORIGINAL  = { inst: inst.value, fecha: fecha.value, desde: desde.value, hasta: hasta.value };
    var MESES     = ['enero','febrero','marzo','abril','mayo','junio','julio',
                     'agosto','septiembre','octubre','noviembre','diciembre'];

    var tira      = document.getElementById('tiraDias');
    var estado    = document.getElementById('agendaEstado');
    var rejilla   = document.getElementById('agendaBloques');
    var resumen   = document.getElementById('agendaResumen');
    var form      = inst.form;
    var guardar   = form.querySelector('button[type="submit"]');

    var bloques   = [];      /* lo que devolvió el servidor para el día */
    var ancla     = null;    /* índice del primer bloque tocado */
    var sel       = null;    /* { i, j }: rango elegido, inclusivo */
    var peticion  = 0;       /* descarta respuestas que llegan tarde */

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function aMin(h) { var p = h.split(':'); return +p[0] * 60 + +p[1]; }
    function partes(valor) { var p = valor.split('-'); return [+p[0], +p[1] - 1, +p[2]]; }
    /* Día ISO (1=lunes) a partir de una fecha 'YYYY-MM-DD', sin zona horaria. */
    function diaIso(valor) {
        var p = partes(valor), d = new Date(Date.UTC(p[0], p[1], p[2])).getUTCDay();
        return d === 0 ? 7 : d;
    }
    function sumarDias(valor, n) {
        var p = partes(valor), d = new Date(Date.UTC(p[0], p[1], p[2] + n));
        return d.toISOString().slice(0, 10);
    }
    function fechaLarga(valor) {
        var p = partes(valor);
        return dias[diaIso(valor)] + ' ' + p[2] + ' de ' + MESES[p[1]];
    }
    function diasConAtencion(id) {
        var d = datos[id], hay = {};
        if (!d) { return hay; }
        d.franjas.forEach(function (f) { hay[f.dia] = true; });
        return hay;
    }

    /*----------  Tira de días: atajo a las próximas dos semanas  ----------*/
    function pintarTira() {
        var abiertos = diasConAtencion(inst.value);
        var html = '';
        for (var n = 0; n < 14; n++) {
            var f = sumarDias(HOY, n), dia = diaIso(f), p = partes(f);
            var cerrado = inst.value && !abiertos[dia];
            var etiqueta = n === 0 ? 'Hoy' : (n === 1 ? 'Mañana' : dias[dia].slice(0, 3));
            html += '<button type="button" class="ar-dia' + (f === fecha.value ? ' activo' : '') + '"' +
                    ' data-fecha="' + f + '"' + (cerrado ? ' disabled' : '') +
                    ' aria-pressed="' + (f === fecha.value) + '"' +
                    ' title="' + esc(fechaLarga(f)) + (cerrado ? ' · sin atención' : '') + '">' +
                    '<span class="ar-dia__nombre">' + esc(etiqueta) + '</span>' +
                    '<span class="ar-dia__num">' + p[2] + '</span></button>';
        }
        tira.innerHTML = html;
    }

    tira.addEventListener('click', function (e) {
        var b = e.target.closest('.ar-dia');
        if (!b || b.disabled) { return; }
        fecha.value = b.getAttribute('data-fecha');
        cambio();
    });

    /*----------  La disponibilidad del día, pedida al servidor  ----------*/
    function mensaje(html) {
        estado.innerHTML = html;
        estado.hidden = !html;
    }

    function cargar() {
        bloques = []; rejilla.innerHTML = '';
        if (!inst.value || !fecha.value) {
            mensaje('<i class="fas fa-hand-pointer me-1"></i> Elija una instalación y una fecha para ver los horarios.');
            return;
        }

        var d = datos[inst.value];
        if (!d || !d.franjas.length) {
            mensaje('<i class="fas fa-calendar-times me-1"></i> Esta instalación no tiene horario de atención. ' +
                    '<a href="<?php echo APP_URL; ?>horarioList/">Configúrelo en Horarios →</a>');
            return;
        }

        var dia = diaIso(fecha.value);
        if (!diasConAtencion(inst.value)[dia]) {
            var abiertos = Object.keys(diasConAtencion(inst.value)).sort()
                .map(function (n) { return dias[n].toLowerCase(); });
            mensaje('<i class="fas fa-door-closed me-1"></i> No se atiende el ' + esc(dias[dia].toLowerCase()) +
                    '. Días con atención: ' + esc(abiertos.join(', ')) + '.');
            return;
        }

        mensaje('<span class="spinner-border spinner-border-sm me-1" role="status"></span> Consultando horarios…');
        var n = ++peticion;
        var datosPost = new FormData();
        datosPost.append('modulo_arena', 'consultarDisponibilidad');
        datosPost.append('instalacion_id', inst.value);
        datosPost.append('fecha', fecha.value);
        datosPost.append('reserva_id', RESERVA);

        fetch(URL_AJAX, { method: 'POST', body: datosPost, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (n !== peticion) { return; }
                if (!r || !r.ok) {
                    mensaje('<i class="fas fa-exclamation-triangle me-1"></i> ' + esc((r && r.motivo) || 'No se pudo consultar.'));
                    return;
                }
                bloques = r.bloques;
                pintarBloques(r);
                preseleccionar();
            })
            .catch(function () {
                if (n !== peticion) { return; }
                mensaje('<i class="fas fa-wifi me-1"></i> No se pudo consultar la disponibilidad. Revise la conexión e inténtelo de nuevo.');
            });
    }

    function pintarBloques(r) {
        if (!bloques.length) {
            mensaje('<i class="fas fa-door-closed me-1"></i> No hay horarios para este día.');
            return;
        }

        mensaje(r.libres
            ? ''
            : '<i class="fas fa-calendar-times me-1"></i> No quedan horarios libres el ' +
              esc(fechaLarga(fecha.value).toLowerCase()) + '. Pruebe con otro día.');

        /* Un grupo por franja: una reserva no puede saltar de una a otra. */
        var html = '', actual = -1;
        bloques.forEach(function (b, i) {
            if (b.franja !== actual) {
                if (actual !== -1) { html += '</div>'; }
                actual = b.franja;
                if (r.franjas.length > 1) {
                    html += '<div class="ar-franja">Franja ' + esc(r.franjas[actual][0]) + ' – ' + esc(r.franjas[actual][1]) + '</div>';
                }
                html += '<div class="ar-bloques">';
            }
            var libre = b.estado === 'libre';
            var nota  = { ocupado: 'Ocupado', bloqueado: 'Bloqueado', pasado: 'Ya pasó' }[b.estado] || ('$' + (+b.tarifa).toFixed(2));
            html += '<button type="button" class="ar-bloque ar-bloque--' + b.estado + '" data-i="' + i + '"' +
                    (libre ? '' : ' disabled') + ' aria-pressed="false"' +
                    ' title="' + esc(b.desde + ' a ' + b.hasta + (b.detalle ? ' · ' + b.detalle : '')) + '">' +
                    '<span class="ar-bloque__hora">' + esc(b.desde) + '</span>' +
                    '<span class="ar-bloque__nota">' + esc(nota) + '</span></button>';
        });
        rejilla.innerHTML = html + '</div>';
    }

    /*----------  Elegir: un toque fija el inicio, otro extiende hasta la última hora  ----------*/
    function rangoLibre(i, j) {
        if (bloques[i].franja !== bloques[j].franja) { return false; }
        for (var k = i; k <= j; k++) { if (bloques[k].estado !== 'libre') { return false; } }
        return true;
    }

    rejilla.addEventListener('click', function (e) {
        var b = e.target.closest('.ar-bloque');
        if (!b || b.disabled) { return; }
        var i = +b.getAttribute('data-i');

        if (sel && sel.i === i && sel.j === i) {          /* tocar el único elegido lo suelta */
            ancla = null; sel = null;
        } else if (ancla !== null && sel && sel.i === sel.j && i > ancla && rangoLibre(ancla, i)) {
            sel = { i: ancla, j: i };                      /* segundo toque: extiende */
        } else {
            ancla = i; sel = { i: i, j: i };               /* nuevo inicio */
        }
        aplicar();
    });

    function aplicar() {
        rejilla.querySelectorAll('.ar-bloque').forEach(function (el) {
            var i = +el.getAttribute('data-i');
            var dentro = sel && i >= sel.i && i <= sel.j;
            el.classList.toggle('ar-bloque--sel', !!dentro);
            el.setAttribute('aria-pressed', dentro ? 'true' : 'false');
        });

        if (sel) {
            desde.value = bloques[sel.i].desde;
            hasta.value = bloques[sel.j].hasta;
        } else if (!(inst.value === ORIGINAL.inst && fecha.value === ORIGINAL.fecha && RESERVA > 0)) {
            desde.value = ''; hasta.value = '';
        }
        actualizarResumen();
    }

    /* Al editar, el horario de la reserva aparece ya elegido. */
    function preseleccionar() {
        sel = null; ancla = null;
        if (RESERVA > 0 && inst.value === ORIGINAL.inst && fecha.value === ORIGINAL.fecha && ORIGINAL.desde) {
            var i = -1, j = -1;
            bloques.forEach(function (b, k) {
                if (b.desde === ORIGINAL.desde) { i = k; }
                if (b.hasta === ORIGINAL.hasta) { j = k; }
            });
            if (i !== -1 && j >= i && rangoLibre(i, j)) { sel = { i: i, j: j }; ancla = i; }
            desde.value = ORIGINAL.desde; hasta.value = ORIGINAL.hasta;
        }
        aplicar();
    }

    function actualizarResumen() {
        if (!desde.value || !hasta.value) {
            resumen.hidden = true;
            caja.style.display = 'none'; pista.hidden = false;
            if (guardar) { guardar.disabled = true; guardar.title = 'Elija un horario disponible'; }
            return;
        }

        var horas = (aMin(hasta.value) - aMin(desde.value)) / 60;
        /* La misma regla que el servidor: la tarifa vigente a la hora de inicio. */
        var tarifa = sel ? +bloques[sel.i].tarifa : (datos[inst.value] ? datos[inst.value].valorhora : 0);
        var fuera  = !sel;

        resumen.hidden = false;
        resumen.innerHTML = '<i class="fas fa-calendar-check me-2"></i>' +
            '<strong>' + esc(fechaLarga(fecha.value)) + '</strong> · ' +
            esc(desde.value) + ' a ' + esc(hasta.value) + ' · ' +
            horas.toFixed(2).replace(/\.?0+$/, '') + (horas === 1 ? ' hora' : ' horas') +
            (fuera ? '<br><small class="text-muted">Es el horario actual de la reserva. Toque un horario libre para cambiarlo.</small>' : '');

        caja.style.display = ''; pista.hidden = true;
        total.textContent = '$' + (horas * tarifa).toFixed(2);
        detalle.textContent = horas.toFixed(2).replace(/\.?0+$/, '') + ' h × $' + tarifa.toFixed(2) + ' por hora';
        if (guardar) { guardar.disabled = false; guardar.title = ''; }
    }

    function resumenInstalacion() {
        var abiertos = Object.keys(diasConAtencion(inst.value)).sort();
        aviso.textContent = inst.value && abiertos.length
            ? 'Atiende: ' + abiertos.map(function (n) { return dias[n].slice(0, 3).toLowerCase(); }).join(', ') + '.'
            : '';
    }

    function cambio() {
        sel = null; ancla = null;
        resumenInstalacion();
        pintarTira();
        cargar();
        aplicar();
    }

    inst.addEventListener('change', cambio);
    fecha.addEventListener('change', cambio);

    resumenInstalacion();
    pintarTira();
    cargar();
    actualizarResumen();
})();
</script>

<script>
/*----------  El buscador de clientes  ----------*/
/*
| Un combobox: se escribe, el servidor responde (buscarClientes) y se elige
| con el dedo, el ratón o el teclado (flechas, Intro, Escape). Las
| respuestas que llegan tarde se descartan: con una conexión lenta, la de
| «ga» podría llegar después de la de «garcía» y pisarla.
*/
(function () {
    var URL_AJAX = '<?php echo APP_URL; ?>ajax/arenaAjax.php';
    var URL_CREAR = '<?php echo APP_URL; ?>clienteForm/?volver=reservaForm';
    var campo    = document.getElementById('reserva_clienteid');
    var q        = document.getElementById('cliQ');
    var panel    = document.getElementById('cliPanel');
    var lista    = document.getElementById('cliLista');
    var cabecera = document.getElementById('cliCabecera');
    var crear    = document.getElementById('cliCrear');
    var buscar   = document.getElementById('cliBuscar');
    var elegido  = document.getElementById('cliElegido');
    var form     = q.form;

    var resultados = [], activo = -1, peticion = 0, espera = null;

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    /* Resalta lo escrito dentro del nombre (sin distinguir tildes ni mayúsculas). */
    function resaltar(texto, buscado) {
        var plano = function (s) { return s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); };
        var i = buscado ? plano(texto).indexOf(plano(buscado)) : -1;
        if (i < 0) { return esc(texto); }
        return esc(texto.slice(0, i)) + '<mark>' + esc(texto.slice(i, i + buscado.length)) + '</mark>' + esc(texto.slice(i + buscado.length));
    }

    function abrir(si) {
        panel.hidden = !si;
        q.setAttribute('aria-expanded', si ? 'true' : 'false');
        if (!si) { q.removeAttribute('aria-activedescendant'); activo = -1; }
    }

    function pintar(modo, texto) {
        activo = -1;
        cabecera.textContent = modo === 'recientes'
            ? (resultados.length ? 'Recientes' : '')
            : (resultados.length ? resultados.length + (resultados.length === 12 ? '+ coincidencias: afine la búsqueda' : (resultados.length === 1 ? ' coincidencia' : ' coincidencias')) : 'No hay clientes con «' + texto + '».');
        lista.innerHTML = resultados.map(function (c, i) {
            var detalle = [c.identificacion, c.celular].filter(Boolean).join(' · ');
            return '<li role="option" id="cliOpt' + i + '" data-i="' + i + '" aria-selected="false" class="ar-cli__opcion' + (c.activo ? '' : ' ar-cli__opcion--inactivo') + '">' +
                   '<span class="ar-cli__avatar" aria-hidden="true">' + esc(c.nombre.trim().charAt(0).toUpperCase()) + '</span>' +
                   '<span class="ar-cli__datos"><strong>' + resaltar(c.nombre, texto) + '</strong><span>' + resaltar(detalle, texto) + '</span></span>' +
                   (c.activo ? '' : '<span class="badge text-bg-secondary">Inactivo</span>') +
                   (c.saldo > 0 ? '<span class="badge text-bg-success" title="Saldo a favor en su monedero">$' + c.saldo.toFixed(2) + '</span>' : '') +
                   '</li>';
        }).join('');
        /* «Crear cliente» trae lo buscado si parece una identificación. */
        var esId = /^[0-9A-Za-z-]{5,20}$/.test(texto) && /\d{5}/.test(texto);
        crear.href = URL_CREAR + (esId ? '&identificacion=' + encodeURIComponent(texto) : '');
        crear.querySelector('span').textContent = esId ? 'Crear cliente con ' + texto : 'Crear cliente nuevo';
        abrir(true);
    }

    function consultar() {
        var texto = q.value.trim();
        if (texto.length === 1) { return; }            /* una letra coincide con casi todos */
        var n = ++peticion;
        var datos = new FormData();
        datos.append('modulo_arena', 'buscarClientes');
        datos.append('q', texto);
        fetch(URL_AJAX, { method: 'POST', body: datos, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (n !== peticion || !r || !r.clientes) { return; }
                resultados = r.clientes;
                pintar(r.modo, texto);
            })
            .catch(function () {
                if (n !== peticion) { return; }
                resultados = []; lista.innerHTML = '';
                cabecera.textContent = 'No se pudo buscar. Revise la conexión.';
                abrir(true);
            });
    }

    function marcar(i) {
        var opciones = lista.querySelectorAll('.ar-cli__opcion');
        if (!opciones.length) { return; }
        activo = (i + opciones.length) % opciones.length;
        opciones.forEach(function (o, k) { o.setAttribute('aria-selected', k === activo ? 'true' : 'false'); });
        q.setAttribute('aria-activedescendant', 'cliOpt' + activo);
        opciones[activo].scrollIntoView({ block: 'nearest' });
    }

    function elegir(c) {
        campo.value = c.id;
        document.getElementById('cliAvatar').textContent = c.nombre.trim().charAt(0).toUpperCase();
        document.getElementById('cliNombre').textContent = c.nombre;
        document.getElementById('cliDetalle').textContent = [c.identificacion, c.celular].filter(Boolean).join(' · ')
            + (c.saldo > 0 ? ' · saldo $' + c.saldo.toFixed(2) : '');
        abrir(false);
        buscar.hidden = true;
        elegido.hidden = false;
        buscar.classList.remove('is-invalid');
        /* Siguiente paso: la instalación, o el horario si ya está. */
        var inst = document.getElementById('reserva_instalacionid');
        (inst.value ? document.getElementById('agendaBloques') : inst).scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    q.addEventListener('input', function () {
        clearTimeout(espera);
        espera = setTimeout(consultar, 250);
    });
    q.addEventListener('focus', function () { if (!panel.hidden || q.value.trim().length !== 1) { consultar(); } });
    q.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (panel.hidden) { consultar(); } else { marcar(activo + 1); } }
        else if (e.key === 'ArrowUp') { e.preventDefault(); marcar(activo - 1); }
        else if (e.key === 'Enter') {
            /* Intro elige, no envía el formulario. */
            if (!panel.hidden && resultados.length) { e.preventDefault(); elegir(resultados[activo >= 0 ? activo : 0]); }
            else { e.preventDefault(); }
        }
        else if (e.key === 'Escape') { abrir(false); }
    });
    /* mousedown y no click: el click llega después del blur y la lista ya se habría cerrado. */
    lista.addEventListener('mousedown', function (e) {
        var o = e.target.closest('.ar-cli__opcion');
        if (!o) { return; }
        e.preventDefault();
        elegir(resultados[+o.getAttribute('data-i')]);
    });
    q.addEventListener('blur', function () { setTimeout(function () { abrir(false); }, 150); });

    document.getElementById('cliCambiar').addEventListener('click', function () {
        elegido.hidden = true;
        buscar.hidden = false;
        q.value = '';
        q.focus();
    });

    /* Sin cliente no se envía: se lleva al buscador (antes que core.js). */
    document.addEventListener('submit', function (e) {
        if (e.target !== form || campo.value) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        buscar.classList.add('is-invalid');
        buscar.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { q.focus({ preventScroll: true }); }, 300);
        Swal.fire({ icon: 'warning', title: 'Falta el cliente', text: 'Busque y elija a quién se le hace la reserva.', confirmButtonText: 'Entendido' });
    }, true);
})();
</script>

<style>
    /* Tira de días */
    .ar-dias { display: flex; gap: 6px; overflow-x: auto; padding-bottom: 4px; max-width: 100%; }
    .ar-dia {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        min-width: 50px; min-height: 44px; padding: 4px 6px; line-height: 1.1;
        border: 1px solid var(--bs-border-color); border-radius: var(--ds-radius-sm, 10px);
        background: var(--bs-body-bg); color: var(--bs-body-color); cursor: pointer;
    }
    .ar-dia__nombre { font-size: .72rem; color: var(--bs-secondary-color); }
    .ar-dia__num    { font-weight: 700; font-size: 1rem; }
    .ar-dia:hover:not(:disabled) { border-color: var(--ds-acento, #0e7490); }
    .ar-dia.activo  { background: var(--ds-acento, #0e7490); border-color: var(--ds-acento, #0e7490); color: #fff; }
    .ar-dia.activo .ar-dia__nombre { color: rgba(255, 255, 255, .85); }
    .ar-dia:disabled { opacity: .4; cursor: not-allowed; text-decoration: line-through; }

    /* Bloques horarios */
    .ar-franja  { font-size: .78rem; font-weight: 600; color: var(--bs-secondary-color); margin: 8px 0 4px; }
    .ar-bloques { display: grid; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); gap: 8px; }
    .ar-bloque {
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        min-height: 52px; padding: 6px 4px; line-height: 1.15;
        border: 1px solid var(--bs-border-color); border-radius: var(--ds-radius-sm, 10px);
        background: var(--bs-body-bg); color: var(--bs-body-color);
        transition: background .12s, border-color .12s, transform .12s;
    }
    .ar-bloque__hora { font-weight: 700; font-size: 1rem; }
    .ar-bloque__nota { font-size: .72rem; color: var(--bs-secondary-color); }
    .ar-bloque--libre { border-color: var(--bs-success-border-subtle); cursor: pointer; }
    .ar-bloque--libre:hover { background: var(--bs-success-bg-subtle); transform: translateY(-1px); }
    .ar-bloque--sel, .ar-bloque--sel:hover {
        background: var(--ds-acento, #0e7490); border-color: var(--ds-acento, #0e7490); color: #fff;
    }
    .ar-bloque--sel .ar-bloque__nota { color: rgba(255, 255, 255, .85); }
    .ar-bloque--ocupado { background: var(--bs-secondary-bg); cursor: not-allowed; }
    .ar-bloque--ocupado .ar-bloque__hora { text-decoration: line-through; opacity: .7; }
    .ar-bloque--bloqueado {
        cursor: not-allowed; border-color: var(--bs-danger-border-subtle);
        background: repeating-linear-gradient(135deg, var(--bs-danger-bg-subtle) 0 6px, transparent 6px 12px);
    }
    .ar-bloque--pasado { opacity: .45; cursor: not-allowed; }
    .ar-bloque:focus-visible, .ar-dia:focus-visible { outline: 3px solid var(--ds-acento, #0e7490); outline-offset: 2px; }

    /* Leyenda, mensajes y resumen */
    .ar-leyenda { display: flex; flex-wrap: wrap; gap: 10px; font-size: .75rem; color: var(--bs-secondary-color); }
    .ar-leyenda span { display: inline-flex; align-items: center; gap: 4px; }
    .ar-muestra { display: inline-block; width: 14px; height: 14px; min-height: 0; padding: 0; border-radius: 4px; border: 1px solid var(--bs-border-color); }
    .ar-vacio {
        padding: 12px 14px; border: 1px dashed var(--bs-border-color);
        border-radius: var(--ds-radius-sm, 10px); color: var(--bs-secondary-color); font-size: .9rem;
    }
    .ar-vacio[hidden] { display: none; }
    .ar-resumen {
        margin-top: 10px; padding: 10px 14px; border-radius: var(--ds-radius-sm, 10px);
        background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis);
        border: 1px solid var(--bs-success-border-subtle);
    }

    /* Pasos */
    .ar-reserva .card-title { font-size: 1rem; font-weight: 600; display: flex; align-items: center; }
    .ar-paso {
        display: inline-grid; place-items: center; width: 1.6rem; height: 1.6rem; margin-right: .6rem;
        border-radius: 50%; background: var(--ds-acento, #0e7490); color: #fff; font-size: .85rem; font-weight: 700;
    }
    .ar-fecha { display: flex; flex-wrap: wrap; align-items: flex-start; gap: .5rem; }
    .ar-fecha input[type="date"] { max-width: 190px; }

    /* Buscador de clientes */
    .ar-cli__buscar { position: relative; }
    .ar-cli__buscar.is-invalid .form-control, .ar-cli__buscar.is-invalid .input-group-text { border-color: var(--bs-danger); }
    .ar-cli__buscar input[type="search"] { min-height: 2.75rem; }
    .ar-cli__panel {
        position: absolute; left: 0; right: 0; z-index: 20; margin-top: .25rem;
        border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg);
        background: var(--bs-body-bg); box-shadow: 0 .5rem 1.25rem rgba(0, 0, 0, .15); overflow: hidden;
    }
    .ar-cli__cabecera { padding: .4rem .85rem; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); }
    .ar-cli__cabecera:empty { display: none; }
    .ar-cli__lista { list-style: none; margin: 0; padding: 0; max-height: min(22rem, 50vh); overflow-y: auto; }
    .ar-cli__opcion, .ar-cli__elegido { display: flex; align-items: center; gap: .65rem; }
    .ar-cli__opcion { min-height: 3.25rem; padding: .45rem .85rem; cursor: pointer; border-top: 1px solid var(--bs-border-color-translucent); }
    .ar-cli__opcion:hover, .ar-cli__opcion[aria-selected="true"] { background: var(--bs-tertiary-bg); }
    .ar-cli__opcion--inactivo .ar-cli__datos strong { color: var(--bs-secondary-color); }
    .ar-cli__avatar {
        flex: none; display: grid; place-items: center; width: 2.25rem; height: 2.25rem; border-radius: 50%;
        background: var(--bs-tertiary-bg); color: var(--bs-emphasis-color); font-weight: 700;
    }
    .ar-cli__datos { display: flex; flex-direction: column; min-width: 0; flex: 1 1 auto; }
    .ar-cli__datos strong { color: var(--bs-emphasis-color); overflow-wrap: anywhere; }
    .ar-cli__datos span { font-size: .825rem; color: var(--bs-secondary-color); overflow-wrap: anywhere; }
    .ar-cli__datos mark { padding: 0; background: var(--bs-warning-bg-subtle); color: inherit; }
    .ar-cli__crear {
        display: flex; align-items: center; min-height: 3rem; padding: .5rem .85rem;
        border-top: 1px solid var(--bs-border-color); font-weight: 600; text-decoration: none;
    }
    .ar-cli__crear:hover { background: var(--bs-tertiary-bg); }
    .ar-cli__elegido {
        padding: .6rem .75rem; border: 1px solid var(--bs-success-border-subtle);
        border-radius: var(--bs-border-radius-lg); background: var(--bs-success-bg-subtle);
    }
    .ar-cli__elegido .ar-cli__avatar { background: var(--bs-body-bg); }
    .ar-cli__elegido[hidden], .ar-cli__buscar[hidden] { display: none; }

    /* Barra de abajo: importe y guardar, siempre a la vista */
    .ar-barra {
        position: sticky; bottom: 0; z-index: 10; display: flex; align-items: center; justify-content: flex-end; gap: .5rem;
        padding: .65rem .75rem; margin-bottom: 1rem; background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg);
        box-shadow: 0 -.25rem .75rem rgba(0, 0, 0, .08);
    }
    .ar-barra__importe { display: flex; flex-direction: column; margin-right: auto; line-height: 1.15; min-width: 0; }
    .ar-barra__pista   { margin-right: auto; font-size: .875rem; color: var(--bs-secondary-color); }
    .ar-barra__pista[hidden] { display: none; }
    .ar-barra__total   { font-size: 1.35rem; font-weight: 700; color: var(--bs-emphasis-color); }
    .ar-barra__detalle { font-size: .78rem; color: var(--bs-secondary-color); }
    .ar-barra .btn { min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
    @media (max-width: 575.98px) {
        .ar-barra__txt { display: none; }          /* en el teléfono, Cancelar sólo con la X */
        .ar-barra .btn-secondary .fas { margin-right: 0 !important; }
        .ar-fecha input[type="date"] { max-width: none; }
    }
</style>

<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
