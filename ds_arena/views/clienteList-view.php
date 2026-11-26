<?php
/*
| Clientes que alquilan instalaciones, con su saldo de monedero.
|
| Como en Fútbol › Alumnos: el filtro está sobre la tabla («Filtrar la
| lista…», a la derecha) y actúa sólo sobre sus filas, sin recargar ni
| tocar la dirección: nombre, identificación, celular o correo; sin
| mayúsculas ni tildes; varias palabras, todas. A la izquierda, los atajos
| «Con saldo» y «Con reservas»; debajo, «1 a N de N clientes». Se cargan
| todos y se filtra en el navegador: son decenas o cientos, no miles. En el
| teléfono cada cliente es una ficha, no una fila de siete columnas.
*/

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Clientes';
$vistaActual = 'clienteList';

$clientes = $insArena->clientes();
$total    = count($clientes);
$conSaldo = count(array_filter($clientes, static fn ($c) => (float)$c['saldo'] > 0));
$conRes   = count(array_filter($clientes, static fn ($c) => (int)$c['reservas'] > 0));

$puedeCrear    = puede_crear('clienteList');
$puedeEditar   = puede_editar('clienteList');
$puedeEliminar = puede_eliminar('clienteList');
$h = static fn ($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center cl-cab">
        <h3 class="card-title mb-0"><?php echo $total; ?> cliente<?php echo $total === 1 ? '' : 's'; ?></h3>
        <?php if ($puedeCrear): ?>
            <a href="<?php echo APP_URL; ?>clienteForm/" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Nuevo cliente
            </a>
        <?php endif; ?>
    </div>

    <div class="card-body cl-cuerpo">
        <?php if ($total): ?>
            <?php /* Sobre la tabla: «Mostrar N» y los atajos a la izquierda, el filtro a la derecha. */ ?>
            <div class="cl-barra">
                <div class="cl-izq">
                <label class="cl-largo">Mostrar
                    <select class="form-select form-select-sm" id="clLargo" aria-label="Clientes por página">
                        <option value="10">10</option><option value="25" selected>25</option><option value="50">50</option>
                        <option value="100">100</option><option value="0">Todos</option>
                    </select>
                </label>
                <div class="cl-chips" role="group" aria-label="Mostrar">
                    <button type="button" class="btn btn-sm cl-chip is-activo" data-filtro="" aria-pressed="true">Todos <span><?php echo $total; ?></span></button>
                    <button type="button" class="btn btn-sm cl-chip" data-filtro="saldo" aria-pressed="false"><i class="fas fa-wallet" aria-hidden="true"></i> Con saldo <span><?php echo $conSaldo; ?></span></button>
                    <button type="button" class="btn btn-sm cl-chip" data-filtro="reservas" aria-pressed="false"><i class="fas fa-calendar-check" aria-hidden="true"></i> Con reservas <span><?php echo $conRes; ?></span></button>
                </div>
                </div>
                <div class="cl-buscar" role="search">
                    <i class="fas fa-search cl-buscar__icono" aria-hidden="true"></i>
                    <input type="search" id="clBuscar" class="form-control form-control-sm" autocomplete="off"
                           placeholder="Filtrar la lista…" aria-controls="clTabla"
                           aria-label="Filtrar clientes por nombre, identificación, celular o correo"
                           title="Nombre, identificación, celular o correo">
                    <button type="button" class="cl-buscar__limpiar" id="clLimpiar" aria-label="Limpiar el filtro" hidden><i class="fas fa-xmark" aria-hidden="true"></i></button>
                </div>
            </div>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table mb-0 cl-tabla" id="clTabla">
                <thead>
                    <tr>
                        <th>Identificación</th>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th class="text-end">Monedero</th>
                        <th>Reservas</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$clientes): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Todavía no hay clientes registrados.</td></tr>
                <?php endif; ?>

                <?php foreach ($clientes as $c): $saldo = (float)$c['saldo']; $res = (int)$c['reservas']; ?>
                    <tr class="cl-fila"
                        data-buscar="<?php echo $h(implode(' ', [$c['cliente_nombre'], $c['cliente_identificacion'], $c['cliente_celular'], $c['cliente_correo']])); ?>"
                        data-saldo="<?php echo $saldo > 0 ? '1' : '0'; ?>" data-reservas="<?php echo $res > 0 ? '1' : '0'; ?>">
                        <td class="cl-id"><code><?php echo $h($c['cliente_identificacion']); ?></code></td>
                        <td class="cl-nombre"><strong><?php echo $h($c['cliente_nombre']); ?></strong></td>
                        <td class="cl-contacto">
                            <?php if ($c['cliente_celular']): ?>
                                <small class="d-block"><i class="fas fa-phone text-muted me-1" aria-hidden="true"></i><?php echo $h($c['cliente_celular']); ?></small>
                            <?php endif; ?>
                            <?php if ($c['cliente_correo']): ?>
                                <small class="d-block text-muted cl-correo"><?php echo $h($c['cliente_correo']); ?></small>
                            <?php endif; ?>
                            <?php if (!$c['cliente_celular'] && !$c['cliente_correo']): ?>
                                <small class="text-muted">Sin contacto</small>
                            <?php endif; ?>
                        </td>
                        <td class="cl-saldo text-end">
                            <?php if ($saldo > 0): ?>
                                <strong class="text-success" title="Saldo en el monedero">$<?php echo number_format($saldo, 2); ?></strong>
                            <?php else: ?>
                                <span class="text-muted">$0.00</span>
                            <?php endif; ?>
                        </td>
                        <td class="cl-reservas">
                            <?php if ($res > 0): ?>
                                <span class="badge text-bg-info"><?php echo $res; ?> vigente(s)</span>
                            <?php else: ?>
                                <span class="text-muted small">Sin reservas</span>
                            <?php endif; ?>
                        </td>
                        <td class="cl-estado">
                            <span class="badge text-bg-<?php echo $c['cliente_estado'] === 'A' ? 'success' : 'secondary'; ?>">
                                <?php echo $c['cliente_estado'] === 'A' ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td class="ds-tabla-acciones cl-acciones">
                            <?php if ($puedeEditar): ?>
                                <a href="<?php echo APP_URL; ?>clienteForm/?id=<?php echo (int)$c['cliente_id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Editar" aria-label="Editar a <?php echo $h($c['cliente_nombre']); ?>"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>

                            <?php if ($puedeEliminar): ?>
                                <form class="FormularioAjax d-inline" method="POST"
                                      action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                                      data-confirmar="El cliente quedará dado de baja.">
                                    <input type="hidden" name="modulo_arena" value="eliminarCliente">
                                    <input type="hidden" name="cliente_id" value="<?php echo (int)$c['cliente_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar" aria-label="Dar de baja a <?php echo $h($c['cliente_nombre']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total): ?>
            <div class="cl-vacio" id="clVacio" hidden>
                <i class="fas fa-user-slash fa-2x mb-2 opacity-50" aria-hidden="true"></i>
                <p class="mb-2" id="clVacioTexto">Ningún cliente coincide con el filtro.</p>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="clVacioLimpiar"><i class="fas fa-xmark me-1" aria-hidden="true"></i>Limpiar el filtro</button>
            </div>
            <?php /* Debajo de la tabla: «1 a N de N clientes» a la izquierda, las páginas a la derecha. */ ?>
            <div class="cl-pie">
                <div class="cl-info" id="clInfo" aria-live="polite">1 a <?php echo $total; ?> de <?php echo $total; ?> cliente<?php echo $total === 1 ? '' : 's'; ?></div>
                <nav aria-label="Páginas de clientes"><ul class="pagination pagination-sm mb-0" id="clPaginas"></ul></nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cl-cab { gap: 12px; }
.cl-cab::before, .cl-cab::after { content: none; display: none; }
.cl-cuerpo { padding: .75rem 0 .5rem; }
.cl-barra { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem 1rem; padding: 0 1rem .75rem; }
.cl-chips { display: flex; flex-wrap: wrap; gap: .4rem; }
.cl-chip { border-radius: 2rem; --bs-btn-color: var(--bs-body-color); --bs-btn-border-color: var(--bs-border-color); --bs-btn-hover-bg: var(--bs-tertiary-bg); }
.cl-chip span { margin-left: .2rem; font-weight: 700; opacity: .75; }
.cl-chip.is-activo { --bs-btn-bg: #0e7490; --bs-btn-border-color: #0e7490; --bs-btn-color: #fff; --bs-btn-hover-bg: #0e7490; --bs-btn-hover-color: #fff; }
.cl-buscar { position: relative; width: 16rem; }
.cl-buscar__icono { position: absolute; left: .65rem; top: 50%; transform: translateY(-50%); color: var(--bs-secondary-color); font-size: .8rem; pointer-events: none; }
.cl-buscar .form-control { padding-left: 1.9rem; padding-right: 2rem; }
.cl-buscar .form-control::-webkit-search-cancel-button { display: none; }
.cl-buscar__limpiar { position: absolute; right: .2rem; top: 50%; transform: translateY(-50%); border: 0; background: transparent; color: var(--bs-secondary-color);
                      width: 1.75rem; height: 1.75rem; border-radius: 50%; display: grid; place-items: center; }
.cl-buscar__limpiar:hover, .cl-buscar__limpiar:focus-visible { background: var(--bs-tertiary-bg); color: var(--bs-body-color); }
.cl-buscar__limpiar[hidden] { display: none; }
.cl-tabla { border-top: 1px solid var(--bs-border-color); }
.cl-izq { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; min-width: 0; }
.cl-largo { display: inline-flex; align-items: center; gap: .4rem; margin: 0; font-size: .875rem; color: var(--bs-secondary-color); white-space: nowrap; }
.cl-largo .form-select { width: auto; }
.cl-pie { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: .5rem 1rem; padding: .6rem 1rem 0; }
.cl-info { font-size: .875rem; color: var(--bs-secondary-color); }
.cl-pie .page-link { cursor: pointer; }
.cl-vacio { text-align: center; color: var(--bs-secondary-color); padding: 2rem 1rem; }
.cl-correo { overflow-wrap: anywhere; }

@media (pointer: coarse) {
    .cl-buscar__limpiar { width: 2.5rem; height: 2.5rem; right: 0; }
    .cl-chip { min-height: 44px; display: inline-flex; align-items: center; }
    .cl-buscar .form-control, .cl-largo .form-select { min-height: 44px; }
    .cl-pie .page-link { min-height: 44px; min-width: 44px; display: flex; align-items: center; justify-content: center; }
}

/* Teléfono: el filtro arriba a lo ancho, los atajos en una fila que se
   desliza, y cada cliente es una ficha. */
@media (max-width: 575.98px) {
    .cl-barra { flex-direction: column-reverse; align-items: stretch; }
    .cl-buscar { width: 100%; }
    .cl-buscar .form-control { font-size: 1rem; }      /* iOS no hace zoom */
    .cl-izq { flex-wrap: nowrap; width: 100%; max-width: 100%; }
    .cl-izq .cl-chips { flex: 1 1 0; }
    .cl-chips { flex-wrap: nowrap; overflow-x: auto; scrollbar-width: none; min-width: 0; max-width: 100%; }
    .cl-pie { flex-direction: column; align-items: center; }
    .cl-chips::-webkit-scrollbar { display: none; }
    .cl-chip { flex: 0 0 auto; white-space: nowrap; }
    .cl-tabla thead { display: none; }
    .cl-tabla, .cl-tabla tbody { display: block; }
    .cl-tabla tr.cl-fila { display: grid; grid-template-columns: minmax(0, 1fr) auto; column-gap: .75rem; row-gap: .25rem;
                           grid-template-areas: "nombre saldo" "id estado" "contacto contacto" "reservas acciones";
                           padding: .8rem 1rem; border-bottom: 1px solid var(--bs-border-color); }
    .cl-tabla tr.cl-fila[hidden] { display: none; }
    .cl-tabla tr.cl-fila > td { display: block; border: 0; padding: 0 !important; background: transparent; }
    .cl-tabla .cl-nombre { grid-area: nombre; font-size: 1rem; overflow-wrap: anywhere; }
    .cl-tabla .cl-saldo { grid-area: saldo; align-self: center; }
    .cl-tabla .cl-id { grid-area: id; }
    .cl-tabla .cl-estado { grid-area: estado; text-align: right; }
    .cl-tabla .cl-contacto { grid-area: contacto; }
    .cl-tabla .cl-reservas { grid-area: reservas; align-self: center; }
    .cl-tabla .cl-acciones { grid-area: acciones; text-align: right; white-space: nowrap; }
}
</style>

<script>
(function () {
    var campo = document.getElementById('clBuscar');
    if (!campo) { return; }
    var filas = Array.prototype.slice.call(document.querySelectorAll('#clTabla tr.cl-fila'));
    var info = document.getElementById('clInfo'), vacio = document.getElementById('clVacio');
    var limpiar = document.getElementById('clLimpiar'), chips = document.querySelectorAll('.cl-chip');
    var TOTAL = filas.length, filtro = '', espera = null, pagina = 1;
    var largoSel = document.getElementById('clLargo'), paginas = document.getElementById('clPaginas');
    /* «Mostrar N»: se recuerda en este navegador (como en Alumnos); si no se
       puede guardar (modo privado), vuelve a 25. 0 = todos. */
    var CLAVE = 'ds-arena-clientes-por-pagina';
    try { var g = localStorage.getItem(CLAVE); if (g !== null && largoSel.querySelector('option[value="' + g + '"]')) { largoSel.value = g; } } catch (e) {}
    var plural = function (n) { return n === 1 ? ' cliente' : ' clientes'; };
    var norm = function (s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim(); };
    /* El texto de cada fila, normalizado una sola vez. */
    filas.forEach(function (f) { f._texto = norm(f.dataset.buscar); });

    /* Filtra y pagina sólo las filas de la tabla: no recarga ni cambia la
       dirección. Cambiar el filtro, un atajo o «Mostrar» vuelve a la página 1. */
    function aplicar(nuevaPagina) {
        var q = norm(campo.value), partes = q ? q.split(/\s+/) : [];
        var coinciden = filas.filter(function (f) {
            return partes.every(function (p) { return f._texto.indexOf(p) !== -1; }) && (!filtro || f.dataset[filtro] === '1');
        });
        var n = coinciden.length, largo = parseInt(largoSel.value, 10) || 0;
        var total = largo ? Math.max(1, Math.ceil(n / largo)) : 1;
        pagina = Math.min(Math.max(1, nuevaPagina || 1), total);
        var desde = largo ? (pagina - 1) * largo : 0, hasta = largo ? Math.min(n, desde + largo) : n;
        filas.forEach(function (f) { f.hidden = true; });
        coinciden.slice(desde, hasta).forEach(function (f) { f.hidden = false; });

        info.textContent = n ? (desde + 1) + ' a ' + hasta + ' de ' + n + plural(n) + (n !== TOTAL ? ' (de ' + TOTAL + ')' : '')
                             : 'Sin clientes (de ' + TOTAL + ')';
        pintarPaginas(total);
        limpiar.hidden = !campo.value;
        vacio.hidden = n > 0;
        document.getElementById('clVacioTexto').textContent = campo.value.trim()
            ? 'Ningún cliente coincide con «' + campo.value.trim() + '».' : 'Ningún cliente en este filtro.';
    }
    /* «Anterior · 1 2 3 · Siguiente»; con muchas páginas, la primera, la
       última y las vecinas de la actual. Sin páginas que pasar, no se ve. */
    function pintarPaginas(total) {
        paginas.parentNode.hidden = total <= 1;
        if (total <= 1) { paginas.innerHTML = ''; return; }
        var item = function (txt, p, estado, etiqueta) {
            return '<li class="page-item' + (estado ? ' ' + estado : '') + '">' + (p
                ? '<button type="button" class="page-link" data-pagina="' + p + '"' + (estado === 'active' ? ' aria-current="page"' : '') + (etiqueta ? ' aria-label="' + etiqueta + '"' : '') + '>' + txt + '</button>'
                : '<span class="page-link">' + txt + '</span>') + '</li>';
        };
        var html = item('Anterior', pagina > 1 ? pagina - 1 : 0, pagina > 1 ? '' : 'disabled', 'Página anterior'), ultima = 0;
        for (var p = 1; p <= total; p++) {
            if (p === 1 || p === total || Math.abs(p - pagina) <= 1) {
                if (ultima && p - ultima > 1) { html += item('…', 0, 'disabled'); }
                html += item(String(p), p, p === pagina ? 'active' : '', 'Página ' + p);
                ultima = p;
            }
        }
        html += item('Siguiente', pagina < total ? pagina + 1 : 0, pagina < total ? '' : 'disabled', 'Página siguiente');
        paginas.innerHTML = html;
    }
    paginas.addEventListener('click', function (e) {
        var b = e.target.closest('[data-pagina]'); if (!b) { return; }
        aplicar(+b.dataset.pagina);
        var t = document.getElementById('clTabla'); if (t.getBoundingClientRect().top < 0) { t.scrollIntoView({ block: 'start' }); }
    });
    largoSel.addEventListener('change', function () {
        try { localStorage.setItem(CLAVE, largoSel.value); } catch (e) {}
        aplicar(1);
    });

    function ponerFiltro(v) {
        filtro = v;
        chips.forEach(function (c) { var si = c.dataset.filtro === v; c.classList.toggle('is-activo', si); c.setAttribute('aria-pressed', si ? 'true' : 'false'); });
    }

    campo.addEventListener('input', function () { clearTimeout(espera); espera = setTimeout(aplicar, 120); });
    campo.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); clearTimeout(espera); aplicar(); }
        else if (e.key === 'Escape' && campo.value) { e.preventDefault(); campo.value = ''; aplicar(); }
    });
    limpiar.addEventListener('click', function () { campo.value = ''; aplicar(); campo.focus(); });
    chips.forEach(function (c) { c.addEventListener('click', function () { ponerFiltro(c.dataset.filtro); aplicar(); }); });
    document.getElementById('clVacioLimpiar').addEventListener('click', function () { campo.value = ''; ponerFiltro(''); aplicar(); campo.focus(); });

    aplicar(1);   /* la primera página con el «Mostrar» elegido */
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
