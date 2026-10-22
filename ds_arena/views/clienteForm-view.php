<?php
/*
| Alta y edición de un cliente de Arena.
|
| El cliente es a quien se le emite el recibo o la factura de la reserva:
| la identificación se valida como la valida el SRI (cédula con dígito
| verificador, RUC, pasaporte) y, al salir del campo, se pregunta al
| servidor (consultarCliente) si ya es cliente o si es representante de
| la escuela, para cargar sus datos en vez de volver a escribirlos.
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$id     = (int)($_GET['id'] ?? 0);
$cli    = $id > 0 ? $insArena->cliente($id) : null;
$esAlta = ($cli === null);
if ($esAlta) { $id = 0; }

if ($esAlta && !puede_crear('clienteList'))  { require_once __DIR__ . "/accesoDenegado-view.php"; exit(); }
if (!$esAlta && !puede_editar('clienteList')) { require_once __DIR__ . "/accesoDenegado-view.php"; exit(); }

$h   = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$val = static fn(string $campo) => $h($cli[$campo] ?? '');

/* El tipo guardado; los clientes antiguos tienen 'TDC' y se deduce de la longitud. */
/* Desde el buscador de la reserva («Crear cliente»): vuelve a ella con el
   cliente elegido y trae lo que se buscó, si era una identificación. */
$volver = ($_GET['volver'] ?? '') === 'reservaForm' ? 'reservaForm' : '';
$urlSalida = APP_URL . ($volver !== '' ? 'reservaForm/' : 'clienteList/');

$identActual = (string)($cli['cliente_identificacion'] ?? '');
if ($esAlta && preg_match('/^[A-Za-z0-9\-]{5,20}$/', (string)($_GET['identificacion'] ?? ''))) {
    $identActual = strtoupper($_GET['identificacion']);
}
$tipoActual  = in_array($cli['cliente_tipoid'] ?? '', ['CED', 'RUC', 'PAS'], true)
    ? $cli['cliente_tipoid']
    : ($identActual === '' ? 'CED' : (preg_match('/^\d{13}$/', $identActual) ? 'RUC' : (preg_match('/^\d{10}$/', $identActual) ? 'CED' : 'PAS')));
$TIPOS = ['CED' => 'Cédula', 'RUC' => 'RUC', 'PAS' => 'Pasaporte'];

$tituloVista = $esAlta ? 'Nuevo cliente' : 'Editar cliente';
$vistaActual = 'clienteList';

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-8">
        <form id="formCliente" class="FormularioAjax ar-cliente" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php" autocomplete="off" novalidate>
            <input type="hidden" name="modulo_arena" value="guardarCliente">
            <input type="hidden" name="cliente_id" value="<?php echo $id; ?>">
            <input type="hidden" name="volver" value="<?php echo $volver; ?>">
            <input type="hidden" name="cliente_repreid" id="cliente_repreid" value="<?php echo (int)($cli['cliente_repreid'] ?? 0) ?: ''; ?>">

            <!-- ============ Identificación ============ -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-id-card me-2" aria-hidden="true"></i>Identificación</h3>
                </div>
                <div class="card-body">
                    <p class="text-body-secondary small mb-3">Con estos datos se emiten los recibos y facturas de sus reservas.</p>

                    <span class="form-label d-block fw-medium" id="rotulo_tipo">Tipo de identificación</span>
                    <div class="btn-group w-100 ar-tipos mb-3" role="radiogroup" aria-labelledby="rotulo_tipo">
                        <?php foreach ($TIPOS as $codigo => $texto): ?>
                            <input type="radio" class="btn-check" name="cliente_tipoid" id="tipo_<?php echo $codigo; ?>" value="<?php echo $codigo; ?>" <?php echo $tipoActual === $codigo ? 'checked' : ''; ?>>
                            <label class="btn btn-outline-secondary" for="tipo_<?php echo $codigo; ?>"><?php echo $texto; ?></label>
                        <?php endforeach; ?>
                    </div>

                    <label for="cliente_identificacion" class="form-label fw-medium">Número <span class="text-danger" aria-hidden="true">*</span></label>
                    <div class="input-group ar-ident">
                        <input type="text" class="form-control form-control-lg" id="cliente_identificacion" name="cliente_identificacion"
                               maxlength="20" required autocapitalize="characters" spellcheck="false"
                               aria-describedby="ar-ident-estado" value="<?php echo $h($identActual); ?>">
                        <span class="input-group-text ar-ident__icono" id="ar-ident-icono" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
                    </div>
                    <div id="ar-ident-estado" class="ar-estado" aria-live="polite"></div>
                </div>
            </div>

            <!-- ============ Datos ============ -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-user me-2" aria-hidden="true"></i>Datos para el recibo</h3>
                </div>
                <div class="card-body">
                    <div id="ar-repre" class="ar-repre<?php echo !empty($cli['cliente_repreid']) ? '' : ' d-none'; ?>" aria-live="polite">
                        <i class="fas fa-user-group" aria-hidden="true"></i>
                        <span id="ar-repre-texto"><?php echo !empty($cli['cliente_repreid']) ? 'Vinculado a un representante de la escuela.' : ''; ?></span>
                    </div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="cliente_nombre" class="form-label fw-medium"><span id="rotulo_nombre"><?php echo $tipoActual === 'RUC' ? 'Razón social' : 'Nombres y apellidos'; ?></span> <span class="text-danger" aria-hidden="true">*</span></label>
                            <input type="text" class="form-control" id="cliente_nombre" name="cliente_nombre"
                                   maxlength="120" required autocapitalize="words" value="<?php echo $val('cliente_nombre'); ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="cliente_celular" class="form-label fw-medium">Celular</label>
                            <input type="tel" class="form-control" id="cliente_celular" name="cliente_celular" maxlength="10"
                                   inputmode="numeric" pattern="0[0-9]{9}" placeholder="09…" value="<?php echo $val('cliente_celular'); ?>">
                        </div>
                        <div class="col-12 col-sm-6">
                            <label for="cliente_correo" class="form-label fw-medium">Correo</label>
                            <input type="email" class="form-control" id="cliente_correo" name="cliente_correo" maxlength="80"
                                   inputmode="email" autocapitalize="off" spellcheck="false" placeholder="nombre@correo.com" value="<?php echo $val('cliente_correo'); ?>">
                            <div class="form-text">Para enviarle los recibos.</div>
                        </div>
                        <div class="col-12">
                            <label for="cliente_direccion" class="form-label fw-medium">Dirección</label>
                            <input type="text" class="form-control" id="cliente_direccion" name="cliente_direccion" maxlength="200" value="<?php echo $val('cliente_direccion'); ?>">
                        </div>
                        <?php if (!$esAlta): ?>
                            <div class="col-12 col-sm-6">
                                <label for="cliente_estado" class="form-label fw-medium">Estado</label>
                                <select class="form-select" id="cliente_estado" name="cliente_estado">
                                    <option value="A" <?php echo ($cli['cliente_estado'] ?? 'A') === 'A' ? 'selected' : ''; ?>>Activo</option>
                                    <option value="I" <?php echo ($cli['cliente_estado'] ?? '') === 'I' ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                        <?php else: ?>
                            <input type="hidden" name="cliente_estado" value="A">
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="ar-barra">
                <a href="<?php echo $urlSalida; ?>" class="btn btn-secondary"><i class="fas fa-xmark me-1" aria-hidden="true"></i>Cancelar</a>
                <button type="submit" class="btn btn-success" id="ar-guardar"><i class="fas fa-floppy-disk me-1" aria-hidden="true"></i><?php echo $esAlta ? 'Crear cliente' : 'Guardar cambios'; ?></button>
            </div>
        </form>
    </div>
</div>

<style>
    .ar-cliente .card-title { font-size: 1rem; font-weight: 600; }
    .ar-tipos .btn { min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center;
                     color: var(--bs-body-color); border-color: var(--bs-border-color); }
    /* El elegido, con el color de la marca y legible en los dos temas. */
    .ar-tipos .btn-check:checked + .btn { background: var(--bs-emphasis-color); border-color: var(--bs-emphasis-color); color: var(--bs-body-bg); }
    .ar-ident__icono { min-width: 3rem; justify-content: center; }
    .ar-ident__icono.is-ok   { color: var(--bs-success); }
    .ar-ident__icono.is-mal  { color: var(--bs-danger); }
    .ar-estado { font-size: .875rem; margin-top: .5rem; }
    .ar-estado:empty { display: none; }
    .ar-estado--ok   { color: var(--bs-success-text-emphasis); }
    .ar-estado--mal  { color: var(--bs-danger-text-emphasis); }
    .ar-estado--cliente {
        padding: .6rem .75rem; border-radius: var(--bs-border-radius);
        border: 1px solid var(--bs-warning-border-subtle); background: var(--bs-warning-bg-subtle); color: var(--bs-warning-text-emphasis);
    }
    .ar-estado__acciones { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .5rem; }
    .ar-repre {
        display: flex; align-items: center; gap: .6rem; margin-bottom: 1rem; padding: .6rem .75rem;
        border-radius: var(--bs-border-radius); border: 1px solid var(--bs-info-border-subtle);
        background: var(--bs-info-bg-subtle); color: var(--bs-info-text-emphasis); font-size: .875rem;
    }
    .ar-cliente .is-cargado { background-color: var(--bs-info-bg-subtle); transition: background-color 1.5s ease; }
    /* Guardar siempre a la vista, también en el teléfono. */
    .ar-barra {
        position: sticky; bottom: 0; z-index: 5; display: flex; justify-content: flex-end; gap: .5rem;
        padding: .75rem; margin-bottom: 1rem; background: var(--bs-body-bg);
        border: 1px solid var(--bs-border-color); border-radius: var(--bs-border-radius-lg);
        box-shadow: 0 -.25rem .75rem rgba(0, 0, 0, .08);
    }
    @media (max-width: 575.98px) {
        .ar-barra .btn { min-height: 2.75rem; display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
        .ar-barra .btn-success { flex: 1 1 auto; }
        .ar-cliente .form-control:not(.form-control-lg), .ar-cliente .form-select { min-height: 2.75rem; }
    }
    @media (prefers-reduced-motion: reduce) { .ar-cliente .is-cargado { transition: none; } }
</style>

<script>
(function () {
    var form    = document.getElementById('formCliente');
    var ident   = document.getElementById('cliente_identificacion');
    var estado  = document.getElementById('ar-ident-estado');
    var icono   = document.getElementById('ar-ident-icono');
    var repreId = document.getElementById('cliente_repreid');
    var repreCaja  = document.getElementById('ar-repre');
    var repreTexto = document.getElementById('ar-repre-texto');
    var BASE    = <?php echo json_encode(APP_URL); ?>;
    var esAlta  = <?php echo $esAlta ? 'true' : 'false'; ?>;

    /* Lo último que dijo el servidor de la identificación escrita. Se usa
       para no dejar guardar algo que ya se sabe que va a rechazar. */
    var revision = { valor: null, bloquea: '' };

    function tipo() { return (form.querySelector('[name="cliente_tipoid"]:checked') || {}).value || 'CED'; }

    /* Cada tipo con su teclado y su largo: en el teléfono, la cédula y el
       RUC abren el teclado numérico. */
    function ajustarTipo() {
        var t = tipo();
        ident.maxLength = t === 'CED' ? 10 : (t === 'RUC' ? 13 : 20);
        ident.inputMode = t === 'PAS' ? 'text' : 'numeric';
        ident.placeholder = t === 'CED' ? '10 dígitos' : (t === 'RUC' ? '13 dígitos' : 'Letras y números');
        document.getElementById('rotulo_nombre').textContent = t === 'RUC' ? 'Razón social' : 'Nombres y apellidos';
    }

    function pintar(html, clase, icon) {
        estado.className = 'ar-estado' + (clase ? ' ar-estado--' + clase : '');
        estado.innerHTML = html;
        icono.className = 'input-group-text ar-ident__icono' + (icon === 'ok' ? ' is-ok' : (icon === 'mal' ? ' is-mal' : ''));
        icono.innerHTML = '<i class="fas ' + (icon === 'ok' ? 'fa-circle-check' : (icon === 'mal' ? 'fa-circle-xmark' : (icon === 'busca' ? 'fa-spinner fa-spin' : 'fa-magnifying-glass'))) + '"></i>';
    }
    function texto(s) { var e = document.createElement('span'); e.textContent = s; return e.innerHTML; }

    var DATOS = ['cliente_nombre', 'cliente_celular', 'cliente_correo', 'cliente_direccion'];

    /* Rellena sólo lo que esté vacío: lo que ya se escribió a mano no se
       pisa. Cada campo que llena guarda el valor que puso (data-cargado),
       para poder retirarlo si se cambia de identificación. Devuelve
       cuántos campos llenó. */
    function rellenar(r) {
        var campos = { cliente_nombre: r.nombre, cliente_celular: r.celular, cliente_correo: r.correo, cliente_direccion: r.direccion };
        var cargados = 0;
        Object.keys(campos).forEach(function (id) {
            var c = document.getElementById(id);
            if (c && !c.value.trim() && campos[id]) {
                c.value = campos[id];
                c.dataset.cargado = campos[id];
                c.classList.add('is-cargado');
                setTimeout(function () { c.classList.remove('is-cargado'); }, 1600);
                cargados++;
            }
        });
        return cargados;
    }

    /* Otra identificación es otra persona: se retira lo que se cargó de la
       anterior. Antes se quedaba, y como sólo se rellenaba lo vacío, la
       nueva no entraba: el recibo salía con el nombre de una y la cédula
       de otra. Lo que se cambió a mano después de cargarlo se respeta. */
    function retirarCargados() {
        DATOS.forEach(function (id) {
            var c = document.getElementById(id);
            if (!c || c.dataset.cargado === undefined) { return; }
            if (c.value === c.dataset.cargado) { c.value = ''; }
            delete c.dataset.cargado;
        });
    }

    function cargarRepresentante(r) {
        var cargados = rellenar(r);
        repreId.value = r.id;
        repreTexto.innerHTML = '<strong>Representante de la escuela</strong>'
            + (r.alumnos ? ' (' + r.alumnos + (r.alumnos === 1 ? ' alumno activo' : ' alumnos activos') + ')' : '')
            + (cargados ? '. Se cargaron sus datos: revíselos antes de guardar.' : '. Sus datos ya estaban escritos.');
        repreCaja.classList.remove('d-none');
    }

    function revisar() {
        var valor = ident.value.trim().toUpperCase();
        if (valor !== ident.value) { ident.value = valor; }
        var clave = tipo() + '|' + valor;
        if (clave === revision.valor) { return; }
        revision = { valor: clave, bloquea: '' };

        /* Otra identificación: ni los datos cargados ni el enlace al
           representante anterior valen ya. */
        retirarCargados();
        repreId.value = '';
        repreCaja.classList.add('d-none');
        if (!valor) { pintar('', '', ''); return; }

        pintar('Comprobando…', '', 'busca');
        var datos = new FormData();
        datos.append('modulo_arena', 'consultarCliente');
        datos.append('cliente_identificacion', valor);
        datos.append('cliente_tipoid', tipo());
        datos.append('cliente_id', form.querySelector('[name="cliente_id"]').value);
        fetch(form.getAttribute('action'), { method: 'POST', body: datos })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (revision.valor !== clave) { return; }   /* ya se escribió otra cosa */
                if (!j.valida) {
                    revision.bloquea = j.motivo || 'Identificación no válida.';
                    pintar('<i class="fas fa-circle-exclamation me-1" aria-hidden="true"></i>' + texto(revision.bloquea), 'mal', 'mal');
                    return;
                }
                if (j.cliente && !j.cliente.baja) {
                    revision.bloquea = 'Ya existe un cliente con esta identificación: ' + j.cliente.nombre + '.';
                    pintar('<i class="fas fa-triangle-exclamation me-1" aria-hidden="true"></i>Ya es cliente: <strong>' + texto(j.cliente.nombre) + '</strong>.'
                        + '<span class="ar-estado__acciones"><a class="btn btn-sm btn-secondary" href="' + BASE + 'clienteForm/?id=' + j.cliente.id + '">Abrir su ficha</a></span>',
                        'cliente', 'mal');
                    return;
                }
                var nombreTipo = { CED: 'Cédula válida', RUC: 'RUC válido', PAS: 'Pasaporte' }[j.tipo] || 'Válida';
                if (j.cliente && j.cliente.baja) {
                    rellenar(j.cliente);
                    pintar('<i class="fas fa-rotate-left me-1" aria-hidden="true"></i>' + nombreTipo + '. <strong>' + texto(j.cliente.nombre)
                        + '</strong> fue cliente y está dado de baja: se cargaron sus datos y al guardar se reactivará.', 'cliente', 'ok');
                    return;
                }
                pintar('<i class="fas fa-circle-check me-1" aria-hidden="true"></i>' + nombreTipo + (j.representante ? '' : '. No está registrado como cliente.'), 'ok', 'ok');
                if (j.representante) { cargarRepresentante(j.representante); }
            })
            .catch(function () { pintar('', '', ''); revision.valor = null; });
    }

    form.querySelectorAll('[name="cliente_tipoid"]').forEach(function (r) {
        r.addEventListener('change', function () { ajustarTipo(); revisar(); ident.focus(); });
    });
    ident.addEventListener('blur', revisar);
    ident.addEventListener('change', revisar);
    /* Al completar los dígitos de una cédula o un RUC no hace falta salir
       del campo: se comprueba solo. */
    ident.addEventListener('input', function () {
        if (tipo() !== 'PAS') { ident.value = ident.value.replace(/\D/g, ''); }
        /* En cuanto deja de ser el número comprobado, el «válida» y el aviso
           del representante ya no hablan de él: fuera, hasta comprobarlo. */
        if (tipo() + '|' + ident.value.trim().toUpperCase() !== revision.valor) {
            pintar('', '', '');
            repreCaja.classList.add('d-none');
        }
        if (ident.value.length === ident.maxLength) { revisar(); }
    });
    document.getElementById('cliente_celular').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '');
    });

    /* Antes que core.js (captura): si ya se sabe que el servidor lo va a
       rechazar, o falta algo, no se pregunta «¿Desea realizar…?». */
    document.addEventListener('submit', function (e) {
        if (e.target !== form) { return; }
        var problema = '', campo = null;
        if (!ident.value.trim()) { problema = 'Escriba la identificación.'; campo = ident; }
        else if (revision.bloquea) { problema = revision.bloquea; campo = ident; }
        else if (!document.getElementById('cliente_nombre').value.trim()) { problema = 'Escriba el nombre.'; campo = document.getElementById('cliente_nombre'); }
        else {
            ['cliente_celular', 'cliente_correo'].some(function (id) {
                var c = document.getElementById(id);
                if (c.value.trim() && !c.checkValidity()) {
                    problema = id === 'cliente_celular' ? 'El celular son diez cifras empezando por 0.' : 'Revise el correo.';
                    campo = c; return true;
                }
            });
        }
        if (!problema) { return; }
        e.preventDefault();
        e.stopImmediatePropagation();
        campo.classList.add('is-invalid');
        campo.focus();
        Swal.fire({ icon: 'warning', title: 'Revise los datos', text: problema, confirmButtonText: 'Entendido' });
    }, true);
    form.addEventListener('input', function (e) { e.target.classList.remove('is-invalid'); });

    ajustarTipo();
    /* En la edición se marca la identificación guardada sin consultarla:
       sólo se pregunta si se cambia. */
    if (!esAlta && ident.value) { revision.valor = tipo() + '|' + ident.value.trim(); }
    if (esAlta && ident.value) { revisar(); document.getElementById('cliente_nombre').focus(); }
    else if (esAlta) { ident.focus(); }
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
