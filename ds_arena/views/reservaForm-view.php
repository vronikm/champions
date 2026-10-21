<?php
/*
| Alta y edición de una reserva.
| El importe se calcula por horas × tarifa; el servidor vuelve a calcularlo
| y a comprobar disponibilidad al guardar, así que lo que se muestra aquí
| es una previsualización, no la fuente de verdad.
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

$clientes      = $insArena->clientes();
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

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if (!$clientes || !$instalaciones): ?>

    <div class="aviso-superadmin">
        <i class="fas fa-info-circle fa-lg mt-1"></i>
        <div>
            <strong>Falta información básica para reservar.</strong><br>
            <?php if (!$instalaciones): ?>
                No hay instalaciones registradas. <a href="<?php echo APP_URL; ?>instalacionList/">Crear una →</a><br>
            <?php endif; ?>
            <?php if (!$clientes): ?>
                No hay clientes registrados. <a href="<?php echo APP_URL; ?>clienteForm/">Crear uno →</a>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo $tituloVista; ?></h3></div>

            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                <input type="hidden" name="modulo_arena" value="guardarReserva">
                <input type="hidden" name="reserva_id" value="<?php echo $id; ?>">

                <div class="card-body">
                    <div class="row g-2">
                        <div class="mb-3 col-md-6">
                            <label for="reserva_clienteid">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select" id="reserva_clienteid" name="reserva_clienteid" required>
                                <option value="">Seleccione…</option>
                                <?php foreach ($clientes as $c): ?>
                                    <option value="<?php echo (int)$c['cliente_id']; ?>"
                                        <?php echo (int)($reserva['reserva_clienteid'] ?? 0) === (int)$c['cliente_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($c['cliente_nombre'] . ' · ' . $c['cliente_identificacion'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="reserva_instalacionid">Instalación <span class="text-danger">*</span></label>
                            <select class="form-select" id="reserva_instalacionid" name="reserva_instalacionid" required>
                                <option value="">Seleccione…</option>
                                <?php foreach ($instalaciones as $i): ?>
                                    <option value="<?php echo (int)$i['instalacion_id']; ?>"
                                        <?php echo (int)($reserva['reserva_instalacionid'] ?? 0) === (int)$i['instalacion_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($i['instalacion_codigo'] . ' · ' . $i['instalacion_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        — $<?php echo number_format((float)$i['instalacion_valorhora'], 2); ?>/h
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" id="avisoFranjas"></small>
                        </div>
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
                    <div class="mb-3">
                        <label for="reserva_fecha">Fecha <span class="text-danger">*</span></label>
                        <div class="d-flex flex-wrap align-items-start gap-2">
                            <input type="date" class="form-control" id="reserva_fecha" name="reserva_fecha" required
                                   style="max-width:190px;"
                                   <?php if ($esAlta): ?>min="<?php echo date('Y-m-d'); ?>"<?php endif; ?>
                                   value="<?php echo htmlspecialchars((string)($reserva['reserva_fecha'] ?? date('Y-m-d')), ENT_QUOTES, 'UTF-8'); ?>">
                            <div class="ar-dias" id="tiraDias" role="group" aria-label="Próximos días"></div>
                        </div>
                    </div>

                    <input type="hidden" id="reserva_horainicio" name="reserva_horainicio"
                           value="<?php echo substr((string)($reserva['reserva_horainicio'] ?? ''), 0, 5); ?>">
                    <input type="hidden" id="reserva_horafin" name="reserva_horafin"
                           value="<?php echo substr((string)($reserva['reserva_horafin'] ?? ''), 0, 5); ?>">

                    <div class="mb-3">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-1">
                            <span class="fw-semibold" id="agendaTitulo">Horario <span class="text-danger">*</span></span>
                            <div class="ar-leyenda" aria-hidden="true">
                                <span><i class="ar-muestra ar-bloque--libre"></i>Libre</span>
                                <span><i class="ar-muestra ar-bloque--sel"></i>Elegido</span>
                                <span><i class="ar-muestra ar-bloque--ocupado"></i>Ocupado</span>
                                <span><i class="ar-muestra ar-bloque--bloqueado"></i>Bloqueado</span>
                                <span><i class="ar-muestra ar-bloque--pasado"></i>Ya pasó</span>
                            </div>
                        </div>
                        <p class="small text-muted mb-2" id="agendaAyuda">
                            Toque la hora de inicio. Para reservar más de una hora, toque después la última hora que necesita.
                        </p>
                        <div class="ar-vacio" id="agendaEstado" role="status" aria-live="polite"></div>
                        <div id="agendaBloques" role="group" aria-labelledby="agendaTitulo"></div>
                        <div class="ar-resumen" id="agendaResumen" aria-live="polite" hidden></div>
                    </div>

                    <!-- Previsualización del importe -->
                    <div class="info-box mb-3" id="cajaImporte" style="display:none;">
                        <span class="info-box-icon bg-info shadow-sm"><i class="fas fa-calculator" aria-hidden="true"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text" id="importeDetalle"></span>
                            <span class="info-box-number" id="importeTotal">$0.00</span>
                        </div>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="reserva_observacion">Observación</label>
                        <textarea class="form-control" id="reserva_observacion" name="reserva_observacion"
                                  rows="2" maxlength="250"><?php echo htmlspecialchars((string)($reserva['reserva_observacion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

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

                <?php echo ds_acciones_form(APP_URL . 'reservaList/'); ?>
            </form>
        </div>
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
            caja.style.display = 'none';
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

        caja.style.display = '';
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
</style>

<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
