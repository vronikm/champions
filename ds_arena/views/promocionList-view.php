<?php
/*
| Arena › Promociones (migración 102).
|
| Descuentos de campaña (Black Friday, Navidad…) para el valor de las
| reservas: un porcentaje o un valor fijo por reserva, para las fechas de
| JUEGO entre «desde» y «hasta». Al reservar, el formulario ofrece la
| promoción marcada y quien reserva puede quitarla; en el detalle de una
| reserva ya hecha se aplica con un botón. Cómo se calcula: la sección
| Promociones de arenaController.
|
| Cada promoción es una tarjeta: vigente, próxima, terminada o inactiva, con
| cuántas reservas la tienen y cuánto se ha descontado. El mismo modal sirve
| para crear y para editar.
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Promociones';
$vistaActual = 'promocionList';

$listo         = $insArena->promocionesListas();
$promociones   = $insArena->promociones();
$puedeCrear    = puede_crear('promocionList') && $listo;
$puedeEditar   = puede_editar('promocionList');
$puedeEliminar = puede_eliminar('promocionList');

$hoy = date('Y-m-d');
$e   = static fn ($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$diasCortos  = [1 => 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
$mesesCortos = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
$fecha = static function (string $f) use ($diasCortos, $mesesCortos): string {
    $t = strtotime($f);
    $txt = $diasCortos[(int)date('N', $t)] . ' ' . date('j', $t) . ' ' . $mesesCortos[(int)date('n', $t)];
    return date('Y', $t) === date('Y') ? $txt : $txt . ' ' . date('Y', $t);
};
/* Vigente, próxima, terminada o inactiva. */
$momento = static function (array $p) use ($hoy): array {
    if ($p['promocion_estado'] === 'I') { return ['Inactiva', 'secondary']; }
    if ($hoy > $p['promocion_hasta'])    { return ['Terminada', 'secondary']; }
    if ($hoy < $p['promocion_desde'])    { return ['Próxima', 'primary']; }
    return ['Vigente', 'success'];
};
$vigentes = count(array_filter($promociones, static fn ($p) => $momento($p)[0] === 'Vigente'));

require_once __DIR__ . "/inc/layout-top.php";
?>
<style>
    .ar-promo__cabecera { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem; }
    .ar-promo__cabecera .card-title { float: none; }
    .ar-promo__cabecera::after { display: none; }
    .ar-promo__lista { display: grid; grid-template-columns: repeat(auto-fill, minmax(17rem, 1fr)); gap: .75rem; }
    .ar-promo { border: 1px solid var(--bs-border-color); border-radius: .85rem; padding: .85rem 1rem; display: flex; flex-direction: column; gap: .5rem;
                background: var(--bs-body-bg); min-width: 0; }
    .ar-promo--apagada { opacity: .7; }
    .ar-promo__arriba { display: flex; justify-content: space-between; align-items: flex-start; gap: .5rem; }
    .ar-promo__nombre { font-weight: 700; font-size: 1.05rem; overflow-wrap: anywhere; }
    .ar-promo__valor { font-size: 1.6rem; font-weight: 800; line-height: 1; color: #b91c1c; font-variant-numeric: tabular-nums; white-space: nowrap; }
    [data-bs-theme="dark"] .ar-promo__valor { color: #f87171; }
    .ar-promo__cuando { font-size: .875rem; color: var(--bs-secondary-color); }
    .ar-promo__uso { font-size: .8125rem; color: var(--bs-secondary-color); }
    .ar-promo__acciones { display: flex; flex-wrap: wrap; gap: .4rem; margin-top: auto; padding-top: .25rem; }
    .ar-promo__acciones form { display: inline; }
    .ar-promo-tipo { display: grid; grid-template-columns: 1fr 1fr; gap: .5rem; }
    .ar-promo-tipo .btn { min-height: 2.75rem; font-weight: 600; }
    .ar-promo-vista { border: 1px dashed var(--bs-border-color); border-radius: .65rem; padding: .6rem .75rem; font-size: .9rem; background: var(--bs-tertiary-bg); }
    @media (max-width: 575.98px) {
        .ar-promo__cabecera .btn { width: 100%; }
        .ar-promo__acciones .btn { flex: 1 1 auto; }
    }
</style>

<?php if (!$listo): ?>
    <div class="alert alert-warning">Falta aplicar la migración 102 para usar promociones.</div>
<?php endif; ?>

<div class="card">
    <div class="card-header ar-promo__cabecera">
        <div>
            <h3 class="card-title mb-0"><?php echo count($promociones); ?> promoci<?php echo count($promociones) === 1 ? 'ón' : 'ones'; ?></h3>
            <small class="text-body-secondary d-block mt-1">
                <?php echo $vigentes ? '<i class="fas fa-circle text-success me-1" style="font-size:.6rem"></i>' . $vigentes . ' vigente' . ($vigentes === 1 ? '' : 's') . ' hoy · ' : ''; ?>
                Se ofrecen al reservar para las fechas de juego indicadas.
            </small>
        </div>
        <?php if ($puedeCrear): ?>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalPromo">
                <i class="fas fa-plus me-1" aria-hidden="true"></i>Nueva promoción
            </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (!$promociones): ?>
            <p class="text-body-secondary text-center py-4 mb-0">
                <i class="fas fa-tags fa-2x d-block mb-2 opacity-50" aria-hidden="true"></i>
                Aún no hay promociones. Cree una, por ejemplo «Black Friday», con el descuento y las fechas de juego.
            </p>
        <?php else: ?>
            <div class="ar-promo__lista">
                <?php foreach ($promociones as $p): [$mom, $tono] = $momento($p); $apagada = in_array($mom, ['Inactiva', 'Terminada'], true); ?>
                    <article class="ar-promo<?php echo $apagada ? ' ar-promo--apagada' : ''; ?>">
                        <div class="ar-promo__arriba">
                            <div class="min-w-0">
                                <span class="badge text-bg-<?php echo $tono; ?> mb-1"><?php echo $mom; ?></span>
                                <div class="ar-promo__nombre"><?php echo $e($p['promocion_nombre']); ?></div>
                            </div>
                            <span class="ar-promo__valor"><?php echo $e(arenaController::etiquetaPromocion($p)); ?></span>
                        </div>
                        <div class="ar-promo__cuando"><i class="far fa-calendar me-1" aria-hidden="true"></i>
                            <?php echo $p['promocion_desde'] === $p['promocion_hasta']
                                ? 'Reservas del ' . $fecha($p['promocion_desde'])
                                : 'Reservas del ' . $fecha($p['promocion_desde']) . ' al ' . $fecha($p['promocion_hasta']); ?>
                            <span class="d-block small"><?php echo $p['promocion_tipo'] === 'P' ? 'Porcentaje del valor de la reserva' : 'Valor fijo por reserva'; ?></span>
                        </div>
                        <div class="ar-promo__uso"><i class="fas fa-calendar-check me-1" aria-hidden="true"></i>
                            <?php echo (int)$p['reservas']; ?> reserva<?php echo (int)$p['reservas'] === 1 ? '' : 's'; ?>
                            <?php if ((float)$p['descontado'] > 0): ?> · $<?php echo number_format((float)$p['descontado'], 2); ?> descontados<?php endif; ?>
                        </div>
                        <?php if ($puedeEditar || $puedeEliminar): ?>
                            <div class="ar-promo__acciones">
                                <?php if ($puedeEditar): ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalPromo"
                                            data-id="<?php echo (int)$p['promocion_id']; ?>" data-nombre="<?php echo $e($p['promocion_nombre']); ?>"
                                            data-tipo="<?php echo $e($p['promocion_tipo']); ?>" data-valor="<?php echo $e(number_format((float)$p['promocion_valor'], 2, '.', '')); ?>"
                                            data-desde="<?php echo $e($p['promocion_desde']); ?>" data-hasta="<?php echo $e($p['promocion_hasta']); ?>">
                                        <i class="fas fa-pen me-1" aria-hidden="true"></i>Editar</button>
                                    <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                                        <input type="hidden" name="modulo_arena" value="estadoPromocion">
                                        <input type="hidden" name="promocion_id" value="<?php echo (int)$p['promocion_id']; ?>">
                                        <input type="hidden" name="promocion_estado" value="<?php echo $p['promocion_estado'] === 'A' ? 'I' : 'A'; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary">
                                            <i class="fas <?php echo $p['promocion_estado'] === 'A' ? 'fa-pause' : 'fa-play'; ?> me-1" aria-hidden="true"></i><?php echo $p['promocion_estado'] === 'A' ? 'Desactivar' : 'Activar'; ?></button>
                                    </form>
                                <?php endif; ?>
                                <?php if ($puedeEliminar): ?>
                                    <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                                          data-confirmar="Se eliminará «<?php echo $e($p['promocion_nombre']); ?>». Las reservas que ya la tienen conservan su descuento.">
                                        <input type="hidden" name="modulo_arena" value="estadoPromocion">
                                        <input type="hidden" name="promocion_id" value="<?php echo (int)$p['promocion_id']; ?>">
                                        <input type="hidden" name="promocion_estado" value="E">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Eliminar <?php echo $e($p['promocion_nombre']); ?>"><i class="fas fa-trash-can" aria-hidden="true"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($puedeCrear || $puedeEditar): ?>
<!-- ==================== Nueva / editar promoción ==================== -->
<div class="modal fade" id="modalPromo" tabindex="-1" aria-labelledby="modalPromoTitulo">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php" id="formPromo" autocomplete="off">
                <input type="hidden" name="modulo_arena" value="guardarPromocion">
                <input type="hidden" name="promocion_id" id="promoId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalPromoTitulo"><i class="fas fa-tags me-2" aria-hidden="true"></i>Nueva promoción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="promoNombre" class="form-label fw-medium">Nombre <span class="text-danger">*</span></label>
                    <input type="text" class="form-control mb-3" id="promoNombre" name="promocion_nombre" maxlength="80" required placeholder="Ej.: Black Friday">

                    <label class="form-label fw-medium">Descuento <span class="text-danger">*</span></label>
                    <div class="ar-promo-tipo mb-2" role="radiogroup" aria-label="Tipo de descuento">
                        <input type="radio" class="btn-check" name="promocion_tipo" id="promoTipoP" value="P" checked>
                        <label class="btn btn-outline-secondary" for="promoTipoP"><i class="fas fa-percent me-1" aria-hidden="true"></i>Porcentaje</label>
                        <input type="radio" class="btn-check" name="promocion_tipo" id="promoTipoV" value="V">
                        <label class="btn btn-outline-secondary" for="promoTipoV"><i class="fas fa-dollar-sign me-1" aria-hidden="true"></i>Valor fijo</label>
                    </div>
                    <div class="input-group mb-1">
                        <span class="input-group-text" id="promoPrefijo">%</span>
                        <input type="text" inputmode="decimal" class="form-control text-end fw-bold" id="promoValor" name="promocion_valor" required placeholder="Ej.: 20">
                    </div>
                    <div class="form-text mb-3" id="promoValorAyuda">Porcentaje del valor de cada reserva.</div>

                    <div class="row g-2">
                        <div class="col-6"><label for="promoDesde" class="form-label fw-medium">Reservas desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="promoDesde" name="promocion_desde" required></div>
                        <div class="col-6"><label for="promoHasta" class="form-label fw-medium">Hasta <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="promoHasta" name="promocion_hasta" required></div>
                    </div>
                    <div class="form-text mb-3">Es la <strong>fecha de juego</strong> de la reserva, no el día en que se registra.</div>

                    <div class="ar-promo-vista" id="promoVista" aria-live="polite"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-floppy-disk me-1" aria-hidden="true"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = function (id) { return document.getElementById(id); };
    var HOY = '<?php echo $hoy; ?>';
    var num = function (s) { s = String(s || '').replace(',', '.'); return Math.round((parseFloat(s) || 0) * 100) / 100; };
    var tipo = function () { return $('promoTipoV').checked ? 'V' : 'P'; };

    /* El efecto sobre lo que vale de verdad una reserva de 1 hora en cada
       instalación (el valor de cada franja). Sin tarifas, con $40. */
    var VALORES = <?php echo json_encode($insArena->valoresHoraInstalaciones(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?>;
    var esc = function (s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; };
    function vista() {
        var v = num($('promoValor').value), t = tipo();
        $('promoPrefijo').textContent = t === 'P' ? '%' : '$';
        $('promoValorAyuda').textContent = t === 'P' ? 'Porcentaje del valor de cada reserva (1 a 100).' : 'Se resta ese valor de cada reserva (nunca más que su valor).';
        if (v <= 0) { $('promoVista').innerHTML = '<i class="fas fa-calculator me-1"></i>Escriba el descuento para ver cuánto queda cada reserva.'; return; }
        var linea = function (valor) {
            var d = Math.round((t === 'P' ? Math.min(valor, valor * v / 100) : Math.min(valor, v)) * 100) / 100;
            return 'una reserva de <strong>$' + valor.toFixed(2) + '</strong> queda en <strong>$' + (valor - d).toFixed(2) + '</strong> (−$' + d.toFixed(2) + ')';
        };
        if (!VALORES.length) { $('promoVista').innerHTML = '<i class="fas fa-calculator me-1"></i>Ejemplo: ' + linea(40) + '.'; return; }
        var varias = VALORES.some(function (x) { return x.instalacion !== VALORES[0].instalacion; });
        $('promoVista').innerHTML = '<i class="fas fa-calculator me-1"></i>Con el valor de 1 hora de reserva:'
            + '<ul class="mb-0 ps-3 mt-1">' + VALORES.map(function (x) {
                return '<li>' + (varias ? esc(x.instalacion) + ': ' : '') + linea(x.valor) + '</li>';
            }).join('') + '</ul>';
    }
    ['promoValor'].forEach(function (id) { $(id).addEventListener('input', vista); });
    document.querySelectorAll('input[name="promocion_tipo"]').forEach(function (r) { r.addEventListener('change', vista); });
    $('promoDesde').addEventListener('change', function () { if (!$('promoHasta').value || $('promoHasta').value < this.value) { $('promoHasta').value = this.value; } });

    /* El botón que abre el modal decide si es alta o edición. */
    $('modalPromo').addEventListener('show.bs.modal', function (e) {
        var b = e.relatedTarget, editar = b && b.dataset.id;
        $('formPromo').reset();
        $('promoId').value = editar ? b.dataset.id : '0';
        $('modalPromoTitulo').innerHTML = '<i class="fas fa-tags me-2" aria-hidden="true"></i>' + (editar ? 'Editar promoción' : 'Nueva promoción');
        $('promoNombre').value = editar ? b.dataset.nombre : '';
        (editar && b.dataset.tipo === 'V' ? $('promoTipoV') : $('promoTipoP')).checked = true;
        $('promoValor').value = editar ? b.dataset.valor : '';
        $('promoDesde').value = editar ? b.dataset.desde : HOY;
        $('promoHasta').value = editar ? b.dataset.hasta : HOY;
        vista();
    });
    $('modalPromo').addEventListener('shown.bs.modal', function () { if (window.matchMedia('(min-width: 576px)').matches) { $('promoNombre').focus(); } });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
