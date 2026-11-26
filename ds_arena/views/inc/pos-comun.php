<?php
/*
| Lo común a las pantallas del Punto de Venta y Caja (migración 095):
| formato, estilos y el envío por AJAX.
|
| Se incluye DESPUÉS de layout-top.php. Pensado para el teléfono: botones
| de al menos 44 px, textos de 16 px en los campos (iOS no hace zoom) y
| colores de Bootstrap, que ya tienen su versión oscura.
*/

$posH      = static fn ($v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$posDinero = static fn ($v): string => ((float)$v < 0 ? '−$' : '$') . number_format(abs((float)$v), 2);
$posCant   = static fn ($v): string => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
?>
<style>
    /* Tamaños como el resto del sistema (Instalaciones): con ratón, botones
       .btn-sm de 31 px y campos normales; en pantallas táctiles, 44 px (el
       pulgar), como hace core.css con «pointer: coarse». --pos-toque es esa
       altura mínima: «auto» con ratón, 44 px al tocar. */
    :root { --pos-toque: auto; }
    @media (pointer: coarse) { :root { --pos-toque: 44px; } }
    .pos { --pos-radio: .85rem; }
    .pos .form-control, .pos .form-select { min-height: var(--pos-toque); }
    @media (max-width: 575.98px) { .pos .form-control, .pos .form-select { font-size: 1rem; } }
    /* La acción de una pantalla: un .btn-sm (31 px), 44 px al tocar. En el
       pie de un modal, del tamaño normal, como los demás modales. */
    .pos-btn { --bs-btn-padding-y: .25rem; --bs-btn-padding-x: .6rem; --bs-btn-font-size: .875rem; --bs-btn-border-radius: var(--bs-border-radius-sm);
               min-height: var(--pos-toque); font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: .35rem; }
    .modal-footer .pos-btn, .pos-btn.pos-btn--normal { --bs-btn-padding-y: .375rem; --bs-btn-padding-x: .75rem; --bs-btn-font-size: 1rem; --bs-btn-border-radius: var(--bs-border-radius); }
    .pos-tarjeta { border: 1px solid var(--bs-border-color); border-radius: var(--pos-radio); background: var(--bs-body-bg); }

    /* Cifras */
    .pos-kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(10.5rem, 1fr)); gap: .75rem; margin-bottom: 1rem; }
    .pos-kpi { border: 1px solid var(--bs-border-color); border-radius: var(--pos-radio); padding: .75rem .9rem; background: var(--bs-body-bg); min-width: 0; }
    .pos-kpi__etq { display: flex; align-items: center; gap: .35rem; font-size: .8rem; color: var(--bs-secondary-color); }
    .pos-kpi__cifra { display: block; font-size: 1.4rem; font-weight: 700; line-height: 1.2; margin-top: .15rem; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
    .pos-kpi__nota { font-size: .75rem; color: var(--bs-secondary-color); }
    .pos-kpi--acento { background: #0e7490; border-color: #0e7490; color: #fff; }
    .pos-kpi--acento .pos-kpi__etq, .pos-kpi--acento .pos-kpi__nota { color: rgba(255,255,255,.85); }
    .pos-kpi--alerta { border-color: var(--bs-danger-border-subtle); background: var(--bs-danger-bg-subtle); }
    .pos-kpi--alerta .pos-kpi__cifra { color: var(--bs-danger-text-emphasis); }
    .pos-kpi--ok .pos-kpi__cifra { color: var(--bs-success-text-emphasis); }
    @media (max-width: 575.98px) {
        .pos-kpis { grid-template-columns: 1fr 1fr; gap: .5rem; }
        .pos-kpi { padding: .6rem .7rem; }
        .pos-kpi__cifra { font-size: 1.15rem; }
    }

    /* Listas de fichas (teléfono primero) */
    .pos-lista { list-style: none; margin: 0; padding: 0; }
    .pos-fila { display: grid; grid-template-columns: 1fr auto; gap: .15rem .75rem; padding: .7rem 1rem; align-items: center; }
    .pos-fila + .pos-fila { border-top: 1px solid var(--bs-border-color-translucent); }
    .pos-fila__titulo { font-weight: 600; overflow-wrap: anywhere; }
    .pos-fila__meta { grid-column: 1 / -1; display: flex; flex-wrap: wrap; gap: .1rem .8rem; font-size: .8125rem; color: var(--bs-secondary-color); }
    .pos-fila__valor { font-weight: 700; font-variant-numeric: tabular-nums; text-align: right; white-space: nowrap; }
    .pos-neg { color: var(--bs-danger-text-emphasis); }
    .pos-pos { color: var(--bs-success-text-emphasis); }
    .pos-tachada { text-decoration: line-through; opacity: .65; }

    /* Hoja que sube desde abajo en el teléfono (modales) */
    @media (max-width: 575.98px) {
        .pos-hoja .modal-dialog { margin: 0; min-height: 100%; align-items: flex-end; max-width: none; }
        .pos-hoja.fade .modal-dialog { transform: translateY(100%); }
        .pos-hoja.show .modal-dialog { transform: none; }
        .pos-hoja .modal-content { border-radius: 1rem 1rem 0 0; border: 0; max-height: 94vh; max-height: 94dvh; }
        .pos-hoja .modal-header { position: relative; padding-top: 1.1rem; }
        .pos-hoja .modal-header::before { content: ""; position: absolute; top: .4rem; left: 50%; width: 2.5rem; height: .25rem;
                                           margin-left: -1.25rem; border-radius: 1rem; background: var(--bs-border-color); }
        .pos-hoja .modal-footer { flex-wrap: nowrap; padding-bottom: max(.75rem, env(safe-area-inset-bottom)); }
        .pos-hoja .modal-footer > .btn { flex: 1 1 0; min-height: 2.75rem; margin: 0 .25rem; white-space: nowrap; font-size: .9375rem; padding-inline: .5rem; }
    }
    /* El formulario entre .modal-content y .modal-body: cabecera y pie fijos, el cuerpo se desplaza. */
    .pos-hoja .modal-content > form { display: flex; flex-direction: column; min-height: 0; max-height: 100%; flex: 1 1 auto; }
    .pos-hoja .modal-content > form > .modal-body { overflow-y: auto; min-height: 0; }

    /* Conteo de billetes y monedas */
    .pos-conteo { display: grid; grid-template-columns: repeat(auto-fill, minmax(7.5rem, 1fr)); gap: .4rem; }
    .pos-conteo label { display: flex; align-items: center; gap: .35rem; font-size: .85rem; border: 1px solid var(--bs-border-color);
                        border-radius: .55rem; padding: .25rem .4rem; background: var(--bs-tertiary-bg); }
    .pos-conteo label span { min-width: 3rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .pos-conteo input { min-height: 2.25rem !important; padding: .2rem .4rem; text-align: center; font-size: 1rem; }

    /* Autorización del supervisor */
    .pos-autoriza { border: 1px dashed var(--bs-warning-border-subtle); background: var(--bs-warning-bg-subtle); border-radius: .65rem; padding: .65rem .75rem; }

    @media print {
        .app-header, .app-sidebar, .app-footer, .pos-no-imprimir { display: none !important; }
    }
</style>
<script>
/*
| Envía una operación del punto de venta y devuelve su respuesta JSON.
| El testigo CSRF lo añade csrf.js a cualquier fetch al propio servidor.
*/
window.posEnviar = function (datos, archivo) {
    var f = datos instanceof FormData ? datos : new FormData();
    if (!(datos instanceof FormData)) {
        Object.keys(datos).forEach(function (k) { f.append(k, datos[k] === null || datos[k] === undefined ? '' : datos[k]); });
    }
    return fetch('<?php echo APP_URL; ?>ajax/arenaAjax.php', { method: 'POST', body: f, credentials: 'same-origin' })
        .then(function (r) { return r.text(); })
        .then(function (t) {
            try { return JSON.parse(t); }
            catch (e) { console.error(t); return { ok: false, icono: 'error', titulo: 'Respuesta inesperada', texto: 'El servidor no respondió como se esperaba. Revise si se guardó antes de repetir.' }; }
        })
        .catch(function () { return { ok: false, icono: 'error', titulo: 'Sin conexión', texto: 'No se pudo comunicar con el servidor. Revise su conexión.' }; });
};
/* Muestra la respuesta y, si corresponde, recarga o redirige. */
window.posAviso = function (j) {
    var accion = function () {
        if (j.tipo === 'recargar') { location.reload(); }
        else if (j.tipo === 'redireccionar' && j.url) { location.href = j.url; }
    };
    if (window.Swal) {
        return Swal.fire({ icon: j.icono || (j.ok ? 'success' : 'error'), title: j.titulo || '', text: j.texto || '',
                           confirmButtonText: 'Aceptar', confirmButtonColor: '#0e7490' }).then(accion);
    }
    alert((j.titulo ? j.titulo + '\n' : '') + (j.texto || '')); accion();
};
window.posDinero = function (v) {
    v = Math.round((Number(v) || 0) * 100) / 100;
    return (v < 0 ? '−$' : '$') + Math.abs(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
};
/* Suma un conteo de billetes y monedas y lo deja en JSON. */
window.posContar = function (caja) {
    var total = 0, conteo = {};
    caja.querySelectorAll('input[data-denominacion]').forEach(function (i) {
        var n = parseInt(i.value, 10) || 0;
        if (n > 0) { conteo[i.dataset.denominacion] = n; total += n * parseFloat(i.dataset.denominacion); }
    });
    return { total: Math.round(total * 100) / 100, json: JSON.stringify(conteo) };
};
</script>
<?php
/** Las casillas de billetes y monedas para contar el efectivo. */
$posConteoHtml = static function (string $id) use ($posH): string {
    $h = '<div class="pos-conteo" id="' . $posH($id) . '">';
    foreach (\arena\controllers\posController::DENOMINACIONES as $d) {
        $k = number_format($d, 2, '.', '');
        $et = $d >= 1 ? '$' . (int)$d : (int)round($d * 100) . ' ¢';
        $h .= '<label><span>' . $et . '</span><input type="number" inputmode="numeric" min="0" step="1" class="form-control form-control-sm"'
            . ' data-denominacion="' . $k . '" aria-label="Cantidad de ' . $posH($et) . '" placeholder="0"></label>';
    }
    return $h . '</div>';
};
