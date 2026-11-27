<?php
/*
| La pantalla de egresos, común al bar (posEgresos, migración 100) y a las
| instalaciones (egresoInstalaciones, migración 101).
|
| Se incluye DESPUÉS de layout-top.php y pos-comun.php. Quien la incluye
| prepara:
|   $egAmbito        'B' bar o 'I' instalaciones
|   $egVista         la vista (para los enlaces del período)
|   $egIntro         el texto de arriba (HTML)
|   $egKpis          [[etiqueta, icono, cifra, nota, clase], …]
|   $egCuadro        [[texto, valor, clase ('', 'eg-sub', 'eg-total', 'eg-resultado'), claseValor], …]
|   $egTituloCuadro, $egNotaCuadro (HTML o '')
|   $egPorCategoria, $egPorPersona, $egPorInstalacion   [nombre => valor]
|   $egPorDia        [Y-m-d => ['resultado' => x, 'meta' => [texto, …]]]
|   $egInstalaciones [[instalacion_id, instalacion_nombre], …] (sólo I)
|   $egConcepto      ejemplo para el campo «¿En qué se gastó?»
| y las comunes: $pos, $hoy, $minimo, $desde, $hasta, $periodos,
| $periodoActivo, $listo, $salidas, $puedeCrear, $puedeAnular, $categorias,
| $sedes. Sólo se piden el detalle y el valor: no de dónde salió el dinero.
*/

use arena\controllers\posController;

$fechaCorta    = static fn ($d) => date('d/m/Y', strtotime($d));
$diasSemana    = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];
$tituloPeriodo = $desde === $hasta ? $fechaCorta($desde) : $fechaCorta($desde) . ' – ' . $fechaCorta($hasta);
$barras = static function (array $datos) use ($posH, $posDinero): string {
    $max = $datos ? max($datos) : 0;
    $h = '<div class="py-1">';
    foreach ($datos as $n => $v) {
        $h .= '<div class="eg-barra"><div class="eg-barra__fila"><span>' . $posH($n) . '</span><span>' . $posDinero($v) . '</span></div>'
            . '<div class="eg-barra__pista" aria-hidden="true"><i style="width: ' . ($max > 0 ? max(2, round($v / $max * 100)) : 0) . '%"></i></div></div>';
    }
    return $h . '</div>';
};
$esBar = $egAmbito === 'B';
?>
<style>
    @media (max-width: 575.98px) { #egNuevo { width: 100%; } }
    /* Período: atajos y fechas */
    .eg-periodo { padding: .75rem; display: grid; gap: .6rem; }
    .eg-atajos { display: flex; gap: .4rem; overflow-x: auto; scrollbar-width: none; padding-bottom: 2px; }
    .eg-atajos::-webkit-scrollbar { display: none; }
    .eg-atajos .btn { white-space: nowrap; flex: 0 0 auto; }
    .eg-fechas { display: grid; grid-template-columns: 1fr 1fr auto; gap: .5rem; align-items: end; }
    @media (max-width: 575.98px) { .eg-fechas { grid-template-columns: 1fr 1fr; } .eg-fechas > :last-child { grid-column: 1 / -1; } }

    /* Cuadro de ingresos y egresos */
    .eg-cuadro { list-style: none; margin: 0; padding: 0; }
    .eg-cuadro li { display: flex; justify-content: space-between; gap: .75rem; padding: .45rem 1rem; font-variant-numeric: tabular-nums; }
    .eg-cuadro li > span:last-child { white-space: nowrap; font-weight: 600; }
    .eg-cuadro .eg-sub { padding-left: 1.75rem; font-size: .875rem; color: var(--bs-secondary-color); }
    .eg-cuadro .eg-sub > span:last-child { font-weight: 500; }
    .eg-cuadro .eg-total { border-top: 1px solid var(--bs-border-color); font-weight: 700; }
    .eg-cuadro .eg-resultado { background: var(--bs-tertiary-bg); font-size: 1.05rem; font-weight: 700; border-top: 2px solid var(--bs-border-color); }

    /* Barras por categoría */
    .eg-barra { padding: .5rem 1rem; }
    .eg-barra__fila { display: flex; justify-content: space-between; gap: .75rem; font-size: .875rem; }
    .eg-barra__fila span:last-child { font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .eg-barra__pista { height: .45rem; border-radius: 1rem; background: var(--bs-tertiary-bg); margin-top: .25rem; overflow: hidden; }
    .eg-barra__pista i { display: block; height: 100%; border-radius: 1rem; background: #0e7490; }

    /* Día por día */
    .eg-dia { display: grid; grid-template-columns: 1fr auto; gap: .1rem .75rem; padding: .6rem 1rem; align-items: center; }
    .eg-dia + .eg-dia { border-top: 1px solid var(--bs-border-color-translucent); }
    .eg-dia__meta { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: .1rem .9rem; font-size: .8125rem; color: var(--bs-secondary-color); font-variant-numeric: tabular-nums; }
    .eg-dias { max-height: 26rem; overflow-y: auto; }

    /* Lista de egresos */
    .eg-buscar { padding: .6rem 1rem; border-bottom: 1px solid var(--bs-border-color-translucent); }
    .eg-tipo { font-size: .7rem; font-weight: 600; }

    /* Formulario */
    .eg-chips { display: flex; flex-wrap: wrap; gap: .35rem; margin-top: .4rem; }
    /* El banco desde el que se pagó (bar) */
    .eg-formas { display: grid; grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr)); gap: .5rem; }
    .eg-formas .btn { min-height: var(--pos-toque); --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color);
                      font-weight: 600; font-size: .875rem; line-height: 1.25; display: flex; align-items: center; justify-content: center; padding: .5rem .5rem; }
    .eg-formas .btn-check:checked + .btn { background: #0e7490; border-color: #0e7490; color: #fff; }
    .eg-chips .btn { --bs-btn-padding-y: .15rem; --bs-btn-padding-x: .55rem; --bs-btn-font-size: .8rem; border-radius: 1rem; }
</style>

<div class="pos">
    <?php if (!$listo): ?>
        <div class="alert alert-warning">Falta aplicar la migración <?php echo $esBar ? '100' : '101'; ?> para registrar egresos.</div>
    <?php endif; ?>

    <div class="pos-tarjeta mb-3 p-3 d-flex flex-wrap gap-3 align-items-center">
        <p class="mb-0 small text-body-secondary flex-grow-1" style="min-width: 15rem;">
            <i class="fas fa-circle-info me-1" aria-hidden="true"></i><?php echo $egIntro; ?>
        </p>
        <?php if ($puedeCrear): ?>
            <button type="button" class="btn btn-danger pos-btn px-4" id="egNuevo"><i class="fas fa-minus" aria-hidden="true"></i>Nuevo egreso</button>
        <?php endif; ?>
    </div>

    <form class="pos-tarjeta mb-3 eg-periodo" method="GET" action="<?php echo APP_URL . $egVista; ?>/" aria-label="Período">
        <div class="eg-atajos" role="group" aria-label="Períodos rápidos">
            <?php foreach ($periodos as $n => [$a, $b]): ?>
                <a class="btn btn-sm <?php echo $periodoActivo === $n ? 'btn-primary' : 'btn-outline-secondary'; ?>"
                   href="<?php echo APP_URL . $egVista; ?>/?desde=<?php echo $a; ?>&amp;hasta=<?php echo $b; ?>"<?php echo $periodoActivo === $n ? ' aria-current="true"' : ''; ?>><?php echo $n; ?></a>
            <?php endforeach; ?>
        </div>
        <div class="eg-fechas">
            <div><label class="form-label small fw-semibold mb-1" for="egDesde">Desde</label><input type="date" class="form-control form-control-sm" id="egDesde" name="desde" value="<?php echo $desde; ?>" max="<?php echo $hoy; ?>"></div>
            <div><label class="form-label small fw-semibold mb-1" for="egHasta">Hasta</label><input type="date" class="form-control form-control-sm" id="egHasta" name="hasta" value="<?php echo $hasta; ?>" max="<?php echo $hoy; ?>"></div>
            <button type="submit" class="btn btn-outline-primary pos-btn"><i class="fas fa-magnifying-glass" aria-hidden="true"></i>Consultar</button>
        </div>
    </form>

    <div class="pos-kpis">
        <?php foreach ($egKpis as [$etq, $icono, $cifra, $nota, $clase]): ?>
            <div class="pos-kpi <?php echo $clase; ?>"><span class="pos-kpi__etq"><i class="fas <?php echo $icono; ?>" aria-hidden="true"></i><?php echo $posH($etq); ?></span>
                <strong class="pos-kpi__cifra"><?php echo $posDinero($cifra); ?></strong>
                <span class="pos-kpi__nota"><?php echo $nota; ?></span></div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="pos-tarjeta h-100 overflow-hidden">
                <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0"><?php echo $posH($egTituloCuadro); ?> <small class="text-body-secondary fw-normal">· <?php echo $tituloPeriodo; ?></small></h3></div>
                <ul class="eg-cuadro">
                    <?php foreach ($egCuadro as [$texto, $valor, $clase, $claseValor]): ?>
                        <li class="<?php echo $clase; ?>"><span><?php echo $texto; ?></span><span class="<?php echo $claseValor; ?>"><?php echo $posDinero($valor); ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($egNotaCuadro !== ''): ?>
                    <p class="small text-body-secondary px-3 py-2 mb-0 border-top"><?php echo $egNotaCuadro; ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-lg-6 d-flex flex-column gap-3">
            <div class="pos-tarjeta">
                <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0">Egresos por categoría</h3></div>
                <?php echo $egPorCategoria ? $barras($egPorCategoria) : '<p class="text-body-secondary text-center p-3 mb-0 small">Sin egresos en el período.</p>'; ?>
            </div>
            <?php if (!empty($egPorInstalacion)): ?>
                <div class="pos-tarjeta">
                    <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0">Egresos por instalación</h3></div>
                    <?php echo $barras($egPorInstalacion); ?>
                </div>
            <?php endif; ?>
            <?php if ($egPorPersona): ?>
                <div class="pos-tarjeta">
                    <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0">Pagado con dinero propio</h3></div>
                    <ul class="eg-cuadro py-1">
                        <?php foreach ($egPorPersona as $quien => $v): ?>
                            <li><span><i class="fas fa-user me-2 text-body-secondary" aria-hidden="true"></i><?php echo $posH($quien); ?></span><span><?php echo $posDinero($v); ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($egPorDia) > 1): ?>
        <div class="pos-tarjeta mb-3">
            <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0">Día por día <small class="text-body-secondary fw-normal">· <?php echo count($egPorDia); ?> día(s) con movimiento</small></h3></div>
            <div class="eg-dias">
                <?php foreach ($egPorDia as $f => $d): ?>
                    <div class="eg-dia">
                        <span class="fw-semibold"><?php echo $diasSemana[(int)date('w', strtotime($f))] . ' ' . $fechaCorta($f); ?></span>
                        <span class="fw-bold <?php echo $d['resultado'] < 0 ? 'pos-neg' : 'pos-pos'; ?>" style="font-variant-numeric: tabular-nums;"><?php echo $posDinero($d['resultado']); ?></span>
                        <span class="eg-dia__meta"><?php foreach ($d['meta'] as $m): ?><span><?php echo $m; ?></span><?php endforeach; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="pos-tarjeta">
        <div class="p-3 border-bottom"><h3 class="h6 fw-bold mb-0">Egresos del período <small class="text-body-secondary fw-normal">· <span id="egConteo"><?php echo count($salidas); ?></span></small></h3></div>
        <?php if (!$salidas): ?>
            <p class="text-body-secondary text-center p-4 mb-0"><i class="fas fa-money-bill-transfer fa-2x d-block mb-2 opacity-50" aria-hidden="true"></i>No hay egresos en esas fechas.</p>
        <?php else: ?>
            <?php if (count($salidas) > 5): ?>
                <div class="eg-buscar"><label class="visually-hidden" for="egFiltro">Filtrar egresos</label>
                    <input type="search" class="form-control form-control-sm" id="egFiltro" placeholder="Filtrar por concepto, categoría, quién pagó…" autocomplete="off"></div>
            <?php endif; ?>
            <ul class="pos-lista" id="egLista">
                <?php foreach ($salidas as $s): $anulado = $s['anulado'];
                    $tipo = ['egreso' => ['Egreso', 'text-bg-danger'], 'caja' => ['Caja', 'text-bg-secondary'], 'compra' => ['Compra', 'text-bg-info']][$s['tipo']];
                    $instalacion = (string)($s['instalacion'] ?? ''); ?>
                    <li class="pos-fila" data-buscar="<?php echo $posH(mb_strtolower($s['concepto'] . ' ' . $s['categoria'] . ' ' . $s['origen'] . ' ' . $s['usuario'] . ' ' . $s['referencia'] . ' ' . $instalacion)); ?>">
                        <span class="pos-fila__titulo<?php echo $anulado ? ' pos-tachada' : ''; ?>"><?php echo $posH($s['concepto']); ?>
                            <?php if ($anulado): ?><span class="badge text-bg-secondary ms-1">Anulado</span><?php endif; ?></span>
                        <span class="pos-fila__valor pos-neg<?php echo $anulado ? ' pos-tachada' : ''; ?>"><?php echo $posDinero(-$s['valor']); ?></span>
                        <span class="pos-fila__meta">
                            <span><?php if ($esBar): ?><span class="badge eg-tipo <?php echo $tipo[1]; ?> me-1"><?php echo $tipo[0]; ?></span><?php endif; ?><?php echo $diasSemana[(int)date('w', strtotime($s['fecha']))] . ' ' . $fechaCorta($s['fecha']); ?></span>
                            <span><i class="fas fa-tag me-1" aria-hidden="true"></i><?php echo $posH($s['categoria']); ?></span>
                            <?php if (!$esBar): ?><span><i class="fas fa-warehouse me-1" aria-hidden="true"></i><?php echo $posH($instalacion !== '' ? $instalacion : 'General'); ?></span><?php endif; ?>
                            <?php if ($s['origen'] !== ''): ?><span><i class="fas fa-wallet me-1" aria-hidden="true"></i><?php echo $posH($s['origen']); ?></span><?php endif; ?>
                            <?php if ($s['referencia'] !== ''): ?><span><i class="fas fa-file-invoice me-1" aria-hidden="true"></i><?php echo $posH($s['referencia']); ?></span><?php endif; ?>
                            <?php if ($s['usuario'] !== ''): ?><span><i class="fas fa-user-pen me-1" aria-hidden="true"></i><?php echo $posH($s['usuario']); ?></span><?php endif; ?>
                            <?php if ($anulado && $s['anulacion'] !== ''): ?><span class="w-100">Anulado: <?php echo $posH($s['anulacion']); ?></span><?php endif; ?>
                        </span>
                        <?php if ($s['url'] !== '' || ($s['tipo'] === 'egreso' && !$anulado && $puedeAnular && !$s['cajaCerrada'])): ?>
                            <span class="d-flex gap-2 mt-1" style="grid-column:1/-1;">
                                <?php if ($s['url'] !== ''): ?>
                                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo $posH($s['url']); ?>"><i class="fas fa-up-right-from-square me-1" aria-hidden="true"></i><?php echo $s['tipo'] === 'compra' ? 'Ver la compra' : 'Ver la caja'; ?></a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-anular="<?php echo (int)$s['id']; ?>" data-concepto="<?php echo $posH($s['concepto'] . ' · ' . $posDinero($s['valor'])); ?>"
                                            data-caja="<?php echo $s['enCaja'] ? 'S' : 'N'; ?>"><i class="fas fa-ban me-1" aria-hidden="true"></i>Anular</button>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="text-body-secondary text-center p-3 mb-0 small" id="egSinCoincidencias" hidden>Ningún egreso coincide con el filtro.</p>
        <?php endif; ?>
    </div>
</div>

<?php if ($puedeCrear): ?>
<!-- ==================== Nuevo egreso ==================== -->
<div class="modal fade pos-hoja" id="egModal" tabindex="-1" aria-labelledby="egModalTitulo">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <form id="egForm" autocomplete="off" novalidate>
                <div class="modal-header"><h5 class="modal-title" id="egModalTitulo"><i class="fas fa-money-bill-transfer me-2" aria-hidden="true"></i>Nuevo egreso<?php echo $esBar ? '' : ' de instalaciones'; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <div class="row g-2">
                        <div class="col-6 col-md-4"><label class="form-label fw-semibold" for="egFecha">Fecha</label>
                            <input type="date" class="form-control" id="egFecha" value="<?php echo $hoy; ?>" min="<?php echo $minimo; ?>" max="<?php echo $hoy; ?>" required></div>
                        <div class="col-6 col-md-3"><label class="form-label fw-semibold" for="egValor">Valor</label>
                            <input type="text" inputmode="decimal" class="form-control text-end" id="egValor" placeholder="0.00" required></div>
                        <?php if (!$esBar && $egInstalaciones): ?>
                            <div class="col-12 col-md-5"><label class="form-label fw-semibold" for="egInstalacion">Instalación</label>
                                <select class="form-select" id="egInstalacion">
                                    <option value="0">General (todas)</option>
                                    <?php foreach ($egInstalaciones as $i): ?><option value="<?php echo (int)$i['instalacion_id']; ?>"><?php echo $posH($i['instalacion_nombre']); ?></option><?php endforeach; ?>
                                </select></div>
                        <?php elseif (count($sedes) > 1): ?>
                            <div class="col-12 col-md-5" id="egSedeGrupo"><label class="form-label fw-semibold" for="egSede">Sede</label>
                                <select class="form-select" id="egSede"><?php foreach ($sedes as $s): ?><option value="<?php echo (int)$s['sede_id']; ?>"><?php echo $posH($s['sede_nombre']); ?></option><?php endforeach; ?></select></div>
                        <?php endif; ?>
                        <div class="col-12"><label class="form-label fw-semibold" for="egConcepto">¿En qué se gastó?</label>
                            <input type="text" class="form-control" id="egConcepto" maxlength="120" placeholder="<?php echo $posH($egConcepto); ?>" required></div>
                        <div class="col-12"><label class="form-label fw-semibold" for="egCategoria">Categoría</label>
                            <input type="text" class="form-control" id="egCategoria" maxlength="60" list="egCategorias" placeholder="Elija una o escriba otra" required>
                            <datalist id="egCategorias"><?php foreach ($categorias as $c): ?><option value="<?php echo $posH($c); ?>"></option><?php endforeach; ?></datalist>
                            <div class="eg-chips" role="group" aria-label="Categorías frecuentes">
                                <?php foreach (array_slice($categorias, 0, 10) as $c): ?>
                                    <button type="button" class="btn btn-outline-secondary" data-categoria="<?php echo $posH($c); ?>"><?php echo $posH($c); ?></button>
                                <?php endforeach; ?>
                            </div></div>
                    </div>

                    <?php if (!empty($egFormasPago)): /* Forma de pago (2026-11-26): bar, transferencia; instalaciones, también efectivo. */ ?>
                        <h3 class="h6 fw-bold mt-3"><?php echo $esBar ? 'Pagado por transferencia' : 'Forma de pago'; ?> <span class="text-danger">*</span></h3>
                        <div class="eg-formas mb-1" role="radiogroup" aria-label="<?php echo $esBar ? 'Banco desde el que se pagó' : 'Forma de pago'; ?>">
                            <?php foreach ($egFormasPago as $i => $fo): ?>
                                <input type="radio" class="btn-check" name="egForma" id="egForma<?php echo (int)$fo['forma_id']; ?>" value="<?php echo (int)$fo['forma_id']; ?>"<?php echo $i === 0 ? ' checked' : ''; ?>>
                                <label class="btn btn-outline-secondary" for="egForma<?php echo (int)$fo['forma_id']; ?>"><i class="fas <?php echo $fo['forma_codigo'] === 'EFE' ? 'fa-money-bill-wave' : 'fa-building-columns'; ?> me-1" aria-hidden="true"></i><?php echo $posH($fo['forma_nombre']); ?></label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="mt-2"><label class="form-label small fw-semibold" for="egRef">Factura o comprobante <span class="text-body-secondary fw-normal">(opcional)</span></label>
                        <input type="text" class="form-control" id="egRef" maxlength="60"></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger pos-btn" id="egGuardar"><i class="fas fa-check" aria-hidden="true"></i>Registrar egreso</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($puedeAnular): ?>
<!-- ==================== Anular egreso ==================== -->
<div class="modal fade pos-hoja" id="egModalAnular" tabindex="-1" aria-labelledby="egAnularTitulo">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="egFormAnular" autocomplete="off" novalidate>
                <div class="modal-header"><h5 class="modal-title" id="egAnularTitulo"><i class="fas fa-ban me-2" aria-hidden="true"></i>Anular egreso</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body pos">
                    <p class="fw-semibold mb-2" id="egAnularConcepto"></p>
                    <p class="small text-body-secondary mb-2" id="egAnularCaja" hidden>Salió de una caja abierta: el dinero vuelve a contar en ella.</p>
                    <label class="form-label small fw-semibold" for="egAnularMotivo">¿Por qué se anula?</label>
                    <textarea class="form-control" id="egAnularMotivo" rows="2" maxlength="200" required placeholder="Ej.: se registró dos veces"></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Volver</button>
                    <button type="submit" class="btn btn-danger pos-btn" id="egAnularBoton"><i class="fas fa-ban" aria-hidden="true"></i>Anular</button></div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = function (id) { return document.getElementById(id); };
    var num = function (s) { s = String(s || '').replace(/[$\s]/g, ''); if (/^\d+,\d{1,2}$/.test(s)) { s = s.replace(',', '.'); } return Math.round((parseFloat(s.replace(/,/g, '')) || 0) * 100) / 100; };
    var aviso = function (t) { Swal.fire({ toast: true, position: 'top', icon: 'warning', title: t, showConfirmButton: false, timer: 2400 }); };

    /* ---------- filtro de la lista ---------- */
    var filtro = $('egFiltro');
    if (filtro) {
        var norm = function (s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); };
        var filas = Array.prototype.slice.call(document.querySelectorAll('#egLista > li'));
        filas.forEach(function (li) { li.dataset.clave = norm(li.dataset.buscar); });
        filtro.addEventListener('input', function () {
            var partes = norm(filtro.value).split(/\s+/).filter(Boolean), n = 0;
            filas.forEach(function (li) { var ok = partes.every(function (t) { return li.dataset.clave.indexOf(t) !== -1; }); li.hidden = !ok; if (ok) { n++; } });
            $('egConteo').textContent = partes.length ? n + ' de ' + filas.length : filas.length;
            $('egSinCoincidencias').hidden = n > 0;
        });
    }

    /* ---------- nuevo egreso: sólo el detalle y el valor (2026-11-26) ---------- */
    if ($('egModal')) {
        var AMBITO = '<?php echo $esBar ? 'B' : 'I'; ?>';
        var HOY = '<?php echo $hoy; ?>';
        var modal = new bootstrap.Modal($('egModal'));
        document.querySelectorAll('[data-categoria]').forEach(function (b) {
            b.addEventListener('click', function () { $('egCategoria').value = b.dataset.categoria; $('egCategoria').classList.remove('is-invalid'); });
        });

        $('egNuevo').addEventListener('click', function () {
            $('egForm').reset(); $('egFecha').value = HOY; modal.show();   /* reset deja marcado el primer banco */
        });
        $('egModal').addEventListener('shown.bs.modal', function () { if (window.matchMedia('(min-width: 576px)').matches) { $('egValor').focus(); } });

        $('egForm').addEventListener('submit', function (e) {
            e.preventDefault();
            var f = $('egFecha').value, valor = num($('egValor').value);
            if (!f || f > HOY || f < $('egFecha').min) { $('egFecha').focus(); aviso('Elija una fecha válida: hoy o un día anterior'); return; }
            if (valor <= 0) { $('egValor').focus(); aviso('Escriba el valor'); return; }
            if ($('egConcepto').value.trim().length < 3) { $('egConcepto').focus(); aviso('Escriba en qué se gastó'); return; }
            if ($('egCategoria').value.trim().length < 2) { $('egCategoria').focus(); aviso('Elija o escriba la categoría'); return; }
            var forma = document.querySelector('input[name="egForma"]:checked');
            if (document.querySelector('input[name="egForma"]') && !forma) { aviso(AMBITO === 'B' ? 'Elija desde qué banco se pagó' : 'Elija la forma de pago'); return; }
            var b = $('egGuardar'); b.disabled = true;
            posEnviar({ modulo_arena: 'posGuardarEgreso', ambito: AMBITO, fecha: f, valor: valor, concepto: $('egConcepto').value, categoria: $('egCategoria').value,
                        forma_id: forma ? forma.value : '', referencia: $('egRef').value, sede: $('egSede') ? $('egSede').value : '', instalacion_id: $('egInstalacion') ? $('egInstalacion').value : '' })
                .then(function (j) { b.disabled = false; if (j.ok) { modal.hide(); } posAviso(j); });
        });
    }
    /* ---------- anular ---------- */
    if ($('egModalAnular')) {
        var modalAnular = new bootstrap.Modal($('egModalAnular')), anularId = 0;
        document.querySelectorAll('[data-anular]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                anularId = btn.dataset.anular;
                $('egAnularConcepto').textContent = btn.dataset.concepto;
                $('egAnularCaja').hidden = btn.dataset.caja !== 'S';
                $('egAnularMotivo').value = '';
                modalAnular.show();
            });
        });
        $('egModalAnular').addEventListener('shown.bs.modal', function () { $('egAnularMotivo').focus(); });
        $('egFormAnular').addEventListener('submit', function (e) {
            e.preventDefault();
            if ($('egAnularMotivo').value.trim().length < 4) { $('egAnularMotivo').focus(); aviso('Escriba por qué se anula'); return; }
            var b = $('egAnularBoton'); b.disabled = true;
            posEnviar({ modulo_arena: 'posAnularEgreso', egreso_id: anularId, motivo: $('egAnularMotivo').value })
                .then(function (j) { b.disabled = false; if (j.ok) { modalAnular.hide(); } posAviso(j); });
        });
    }
});
</script>
