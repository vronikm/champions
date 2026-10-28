<?php
/*
| Textos de las autorizaciones LOPDP (migración 075).
|
| Los dos textos que acepta el representante —tratamiento de datos y uso de
| imagen— en el formulario de inscripción (Fútbol › Generar enlaces) y en el
| enlace de autorización (Fútbol › Consentimientos LOPDP). Sólo el
| superadministrador: es un texto legal.
|
| Guardar crea una versión nueva; ninguna se reescribe. La lógica está en
| ds_core/inc/consentimientos.php; esto sólo la presenta.
*/

use admin\controllers\coreController;

if (!es_superadministrador()) {
    http_response_code(403);
    require_once __DIR__ . "/accesoDenegado-view.php";
    exit();
}

$insCore   = new coreController();
$vigentes  = consent_vigentes_crudos();
$escuela   = ds_nombre_organizacion() ?: DS_HUB_NAME;
$versiones = [];
foreach (array_keys(CONSENT_EDITABLES) as $tipo) {
    $versiones[$tipo] = $insCore->versionesTextoConsentimiento($tipo);
}
$tipoActivo = isset(CONSENT_EDITABLES[$_GET['tipo'] ?? '']) ? $_GET['tipo'] : 'DATOS';
$iconos = ['DATOS' => 'fa-file-shield', 'IMAGEN' => 'fa-camera', 'FORMULARIO' => 'fa-file-contract'];
$cortos = ['DATOS' => 'Tratamiento de datos', 'IMAGEN' => 'Uso de imagen', 'FORMULARIO' => 'Formulario en papel'];
/* Una sede de muestra para la vista previa del formulario en papel. */
$muestra = ['sede_nombre' => 'SEDE', 'sede_email' => ds_email_organizacion()];
try {
    $muestra = seguridad_conexion()->query("SELECT sede_nombre, sede_email FROM general_sede ORDER BY sede_id LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: $muestra;
} catch (\Throwable $e) {
}

$tituloVista = 'Textos de autorizaciones';
$vistaActual = 'consentimientoTextos';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fecha = static fn($v) => $v ? date('d/m/Y H:i', strtotime($v)) : '';

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="aviso-superadmin mb-3">
    <i class="fas fa-shield-alt fa-lg mt-1"></i>
    <div>
        <strong>Sólo el superadministrador.</strong><br>
        Son los textos que acepta el representante en el <strong>formulario de inscripción</strong>
        (<a href="<?php echo DS_FUTBOL_URL; ?>inscripcionEnlace/">Fútbol › Generar enlaces</a>) y en el
        <strong>enlace de autorización</strong> (<a href="<?php echo DS_FUTBOL_URL; ?>consentimientoList/">Fútbol › Consentimientos LOPDP</a>),
        y el <strong>formulario en papel</strong> que firma en la sede (Fútbol › Representantes › Formulario LPD y su PDF).
        Cada cambio se guarda como una <strong>versión nueva</strong>: quien ya aceptó no tiene que volver a hacerlo,
        y su aceptación sigue ligada al texto que vio.
    </div>
</div>

<ul class="nav nav-tabs nav-fill ds-textos__pestanas" role="tablist">
    <?php foreach (CONSENT_EDITABLES as $tipo => $nombre): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $tipo === $tipoActivo ? 'active' : ''; ?>" id="pestana-<?php echo $tipo; ?>" data-bs-toggle="tab"
                    data-bs-target="#panel-<?php echo $tipo; ?>" type="button" role="tab" aria-controls="panel-<?php echo $tipo; ?>"
                    aria-selected="<?php echo $tipo === $tipoActivo ? 'true' : 'false'; ?>">
                <i class="fas <?php echo $iconos[$tipo]; ?> me-1" aria-hidden="true"></i><?php echo $cortos[$tipo]; ?>
                <span class="badge text-bg-secondary ms-1 d-none d-sm-inline"><?php echo $vigentes[$tipo]['version'] ? 'v' . (int)$vigentes[$tipo]['version'] : 'original'; ?></span>
            </button>
        </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content ds-textos">
<?php foreach (CONSENT_EDITABLES as $tipo => $nombre):
    $v = $vigentes[$tipo];
    $papel = $tipo === 'FORMULARIO';
    $siguiente = (int)($versiones[$tipo][0]['version'] ?? 0) + 1; ?>
    <div class="tab-pane fade <?php echo $tipo === $tipoActivo ? 'show active' : ''; ?>" id="panel-<?php echo $tipo; ?>" role="tabpanel" aria-labelledby="pestana-<?php echo $tipo; ?>" tabindex="0">
        <div class="row g-3">
            <!-- Editor -->
            <div class="col-lg-7">
                <form class="FormularioAjax card ds-textos__form" method="POST" action="<?php echo APP_URL; ?>ajax/coreAjax.php" autocomplete="off"
                      data-tipo="<?php echo $tipo; ?>"
                      data-confirmar="<?php echo $papel
                          ? 'Desde ahora el formulario en papel (pantalla y PDF) saldrá con este texto (versión ' . $siguiente . ').'
                          : 'Desde ahora los representantes verán este texto (versión ' . $siguiente . '). Las aceptaciones anteriores quedan con la versión que vieron.'; ?>">
                    <input type="hidden" name="modulo_core" value="guardarTextoConsentimiento">
                    <input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-pen-to-square me-2" aria-hidden="true"></i>Editar</h3>
                        <span class="small text-body-secondary ms-auto">
                            <?php echo $v['origen'] === 'BD'
                                ? 'Vigente: versión ' . (int)$v['version'] . ' · ' . $h($fecha($v['fecha'])) . ' · ' . $h($v['usuario'])
                                : 'Vigente: texto original del sistema'; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info py-2 small mb-3" data-t="cargado" hidden></div>

                        <label class="form-label" for="titulo-<?php echo $tipo; ?>">Título</label>
                        <input type="text" class="form-control" id="titulo-<?php echo $tipo; ?>" name="titulo" maxlength="200" required
                               value="<?php echo $h($v['titulo']); ?>" data-t="titulo">

                        <label class="form-label mt-3" for="texto-<?php echo $tipo; ?>">Texto</label>
                        <div class="btn-toolbar gap-1 mb-1 ds-textos__barra" role="toolbar" aria-label="Formato del texto">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="p" title="Párrafo"><i class="fas fa-paragraph" aria-hidden="true"></i><span class="visually-hidden">Párrafo</span></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="strong" title="Negrita"><i class="fas fa-bold" aria-hidden="true"></i><span class="visually-hidden">Negrita</span></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="em" title="Cursiva"><i class="fas fa-italic" aria-hidden="true"></i><span class="visually-hidden">Cursiva</span></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="ul" title="Lista"><i class="fas fa-list-ul" aria-hidden="true"></i><span class="visually-hidden">Lista</span></button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="{ESCUELA}" title="Insertar el nombre de la escuela">{ESCUELA}</button>
                            <?php if ($papel): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="{SEDE}" title="Insertar el nombre de la sede del representante">{SEDE}</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="{CORREO_SEDE}" title="Insertar el correo de la sede">{CORREO_SEDE}</button>
                            <?php endif; ?>
                        </div>
                        <textarea class="form-control ds-textos__texto" id="texto-<?php echo $tipo; ?>" name="texto" rows="16" required
                                  data-t="texto" spellcheck="true"><?php echo $h($v['texto']); ?></textarea>
                        <div class="form-text">
                            Seleccione un trozo y use los botones para darle formato. Se admiten párrafos, negrita, cursiva, subrayado y listas;
                            cualquier otra etiqueta se quita al guardar. <code>{ESCUELA}</code> se cambia por «<?php echo $h($escuela); ?>».
                            <?php if ($papel): ?>
                                <code>{SEDE}</code> y <code>{CORREO_SEDE}</code>, por la sede del representante y su correo.
                                La sección 1 (nombre e identificación del representante) la pone el sistema: el texto empieza en la 2.
                            <?php endif; ?>
                        </div>

                        <?php if ($papel): ?>
                            <label class="form-label mt-3" for="casilla-<?php echo $tipo; ?>">Autorizaciones al pie (una por línea)</label>
                            <textarea class="form-control" id="casilla-<?php echo $tipo; ?>" name="casilla" rows="3" maxlength="600" required data-t="casilla"><?php echo $h($v['casilla']); ?></textarea>
                        <?php else: ?>
                            <label class="form-label mt-3" for="casilla-<?php echo $tipo; ?>">Casilla para aceptar</label>
                            <input type="text" class="form-control" id="casilla-<?php echo $tipo; ?>" name="casilla" maxlength="300" required
                                   value="<?php echo $h($v['casilla']); ?>" data-t="casilla">
                        <?php endif; ?>
                    </div>
                    <div class="card-footer d-flex flex-wrap align-items-center gap-2">
                        <span class="small text-body-secondary me-auto" data-t="estado">Sin cambios</span>
                        <button type="submit" class="btn btn-primary" data-t="guardar" disabled>
                            <i class="fas fa-floppy-disk me-1" aria-hidden="true"></i>Guardar como versión <?php echo $siguiente; ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Vista previa: como lo ve el representante -->
            <div class="col-lg-5">
                <div class="card ds-textos__previa">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas <?php echo $papel ? 'fa-print' : 'fa-mobile-screen'; ?> me-2" aria-hidden="true"></i><?php echo $papel ? 'Así se imprime' : 'Así lo ve el representante'; ?></h3>
                    </div>
                    <div class="card-body">
                        <?php if ($papel): ?>
                            <?php /* Una hoja: el título, la sección 1 que pone el sistema, el texto y el pie. */ ?>
                            <div class="ds-textos__hoja">
                                <p class="ds-textos__hoja-titulo" data-p="titulo"></p>
                                <p class="mb-1"><strong>1. Datos del Titular y Representante del alumno</strong></p>
                                <ul class="text-body-secondary"><li>Nombre del representante: (lo pone el sistema)</li><li>Número de identificación: (lo pone el sistema)</li></ul>
                                <div class="ds-textos__hoja-texto" data-p="texto"></div>
                                <div class="ds-textos__hoja-pie" data-p="casilla"></div>
                                <p class="ds-textos__hoja-firma">Firma del representante legal: ____________________</p>
                            </div>
                        <?php else: ?>
                            <div class="ds-textos__muestra">
                                <h4 class="ds-textos__muestra-titulo"><i class="fas <?php echo $iconos[$tipo]; ?> me-1" aria-hidden="true"></i><span data-p="titulo"></span></h4>
                                <div class="ds-textos__muestra-texto" data-p="texto"></div>
                                <label class="ds-textos__muestra-casilla"><input type="checkbox" disabled> <span data-p="casilla"></span></label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Versiones -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><i class="fas fa-clock-rotate-left me-2" aria-hidden="true"></i>Versiones</h3>
                    </div>
                    <ul class="list-group list-group-flush ds-textos__versiones">
                        <?php foreach ($versiones[$tipo] as $i => $ver): ?>
                            <li class="list-group-item">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <strong><?php echo $ver['version'] ? 'Versión ' . (int)$ver['version'] : 'Original del sistema'; ?></strong>
                                    <?php if ($i === 0): ?><span class="badge text-bg-success">Vigente</span><?php endif; ?>
                                    <?php if ($ver['aceptaciones'] !== null): ?>
                                        <span class="badge text-bg-light border" title="Aceptaciones registradas con este texto exacto">
                                            <?php echo (int)$ver['aceptaciones']; ?> <?php echo (int)$ver['aceptaciones'] === 1 ? 'aceptación' : 'aceptaciones'; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($i > 0): ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-cargar="<?php echo $tipo; ?>"
                                                data-version="<?php echo $h($ver['version'] ? 'la versión ' . (int)$ver['version'] : 'el texto original'); ?>"
                                                data-contenido="<?php echo $h(json_encode(['titulo' => $ver['titulo'], 'texto' => $ver['texto'], 'casilla' => $ver['casilla']], JSON_UNESCAPED_UNICODE)); ?>">
                                            <i class="fas fa-arrow-up-from-bracket me-1" aria-hidden="true"></i>Cargar en el editor
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-body-secondary mt-1">
                                    <?php echo $ver['version'] ? $h($fecha($ver['fecha'])) . ' · ' . $h($ver['usuario']) : 'Texto con el que se instaló el sistema'; ?>
                                    <?php if (!$papel): ?>
                                        · huella <code title="SHA-256 del texto: la que se guarda con cada aceptación"><?php echo $h(substr($ver['hash'], 0, 12)); ?></code>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script>
(function () {
    var ESCUELA = <?php echo json_encode($escuela, JSON_UNESCAPED_UNICODE); ?>;
    /* En la vista previa del papel, una sede de muestra. */
    var MARCAS = { '{ESCUELA}': ESCUELA, '{SEDE}': <?php echo json_encode((string)$muestra['sede_nombre'], JSON_UNESCAPED_UNICODE); ?>,
                   '{CORREO_SEDE}': <?php echo json_encode((string)$muestra['sede_email'], JSON_UNESCAPED_UNICODE); ?> };
    function marcas(t) { Object.keys(MARCAS).forEach(function (k) { t = t.split(k).join(MARCAS[k]); }); return t; }
    var PERMITIDAS = ['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'UL', 'OL', 'LI'];
    var QUITAR = ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'TEMPLATE'];

    /* La misma limpieza que el servidor (consent_limpiar_html): la vista
       previa nunca pinta una etiqueta o un atributo que no se guardaría. */
    function limpiar(nodo) {
        [].slice.call(nodo.childNodes).forEach(function (h) {
            if (h.nodeType === 1) {
                if (QUITAR.indexOf(h.nodeName) !== -1) { h.remove(); return; }
                limpiar(h);
                if (PERMITIDAS.indexOf(h.nodeName) === -1) { while (h.firstChild) { nodo.insertBefore(h.firstChild, h); } h.remove(); return; }
                while (h.attributes.length) { h.removeAttribute(h.attributes[0].name); }
            } else if (h.nodeType !== 3) { h.remove(); }
        });
        return nodo;
    }
    function pintar(destino, html) {
        var esc = function (v) { return v.replace(/[&<>"]/g, function (c) { return '&#' + c.charCodeAt(0) + ';'; }); };
        Object.keys(MARCAS).forEach(function (k) { html = html.split(k).join(esc(MARCAS[k])); });
        var doc = new DOMParser().parseFromString('<div>' + html + '</div>', 'text/html');
        destino.replaceChildren.apply(destino, [].slice.call(limpiar(doc.body.firstChild).childNodes));
    }

    document.querySelectorAll('.ds-textos__form').forEach(function (form) {
        var panel = form.closest('.tab-pane');
        var c = function (n) { return form.querySelector('[data-t="' + n + '"]'); };
        var p = function (n) { return panel.querySelector('[data-p="' + n + '"]'); };
        var titulo = c('titulo'), texto = c('texto'), casilla = c('casilla'), guardar = c('guardar'), estado = c('estado');
        var original = [titulo.value, texto.value, casilla.value].join('\u0000');

        var papel = form.dataset.tipo === 'FORMULARIO';
        function refrescar() {
            p('titulo').textContent = marcas(titulo.value);
            pintar(p('texto'), texto.value);
            if (papel) {
                /* Las autorizaciones del pie, una por línea. */
                p('casilla').replaceChildren.apply(p('casilla'), casilla.value.split('\n').filter(function (l) { return l.trim(); })
                    .map(function (l) { var e = document.createElement('p'); e.textContent = marcas(l.trim()); return e; }));
            } else {
                p('casilla').textContent = casilla.value;
            }
            var cambiado = [titulo.value, texto.value, casilla.value].join('\u0000') !== original;
            guardar.disabled = !cambiado;
            estado.textContent = cambiado ? 'Con cambios sin guardar' : 'Sin cambios';
            estado.classList.toggle('text-warning-emphasis', cambiado);
        }
        [titulo, texto, casilla].forEach(function (e) { e.addEventListener('input', refrescar); });

        /* Formato: envuelve la selección (o inserta la etiqueta vacía). */
        form.querySelectorAll('[data-formato]').forEach(function (b) {
            b.addEventListener('click', function () {
                var f = b.dataset.formato, ini = texto.selectionStart, fin = texto.selectionEnd;
                var sel = texto.value.slice(ini, fin), nuevo;
                if (f.charAt(0) === '{') { nuevo = f; }
                else if (f === 'ul') {
                    var items = (sel || 'Elemento').split('\n').filter(function (l) { return l.trim() !== ''; });
                    nuevo = '<ul>\n' + items.map(function (l) { return '    <li>' + l.trim() + '</li>'; }).join('\n') + '\n</ul>';
                } else { nuevo = '<' + f + '>' + (sel || (f === 'p' ? 'Nuevo párrafo' : 'texto')) + '</' + f + '>'; }
                texto.setRangeText(nuevo, ini, fin, 'select');
                texto.focus();
                refrescar();
            });
        });
        refrescar();
    });

    /* «Cargar en el editor»: trae una versión anterior para volver a usarla
       (se guarda como una versión nueva, no se reactiva la vieja). */
    document.querySelectorAll('[data-cargar]').forEach(function (b) {
        b.addEventListener('click', function () {
            var form = document.querySelector('.ds-textos__form[data-tipo="' + b.dataset.cargar + '"]');
            var d = JSON.parse(b.dataset.contenido);
            form.querySelector('[data-t="titulo"]').value = d.titulo;
            form.querySelector('[data-t="texto"]').value = d.texto;
            form.querySelector('[data-t="casilla"]').value = d.casilla;
            form.querySelector('[data-t="titulo"]').dispatchEvent(new Event('input'));
            var aviso = form.querySelector('[data-t="cargado"]');
            aviso.textContent = 'Se cargó ' + b.dataset.version + '. Revísela y guárdela para que vuelva a ser la vigente.';
            aviso.hidden = false;
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    /* La pestaña elegida queda en la dirección: al recargar tras guardar, se vuelve a ella. */
    document.querySelectorAll('.ds-textos__pestanas [data-bs-toggle="tab"]').forEach(function (t) {
        t.addEventListener('shown.bs.tab', function () {
            var u = new URL(location.href); u.searchParams.set('tipo', t.id.replace('pestana-', '')); history.replaceState(null, '', u);
        });
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
