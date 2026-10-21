<?php
/*
| Cobranza de una categoría.
|
| La pantalla se acota a una categoría a propósito. Es como se trabaja
| —«qué me deben en Sub-14»— y además bota los selectores: sin ese límite,
| elegir a quién se le cobra sería un desplegable de cuatrocientos
| nombres, que es la forma más cómoda de cobrarle al que no era.
|
| El saldo que se muestra sale de v_dsl_saldo, no de un campo guardado.
| Un saldo almacenado se desincroniza en cuanto se anula un cobro, y
| entonces la pantalla afirma una deuda inexistente con la misma seguridad
| con que afirmaría una correcta.
*/

use league\controllers\comprobanteController;

/* comprobanteController extiende finanzaController: la pantalla necesita
   ambas cosas y una sola instancia evita abrir dos veces lo mismo. */
$insLeague = new comprobanteController();

$tituloVista = 'Cobranza';
$vistaActual = 'cobranzaPanel';

$categoriaId = (int)(explode('/', (string)($_GET['views'] ?? ''))[1] ?? 0);

$categorias = $insLeague->categorias();

/* Sin categoría elegida no se muestran obligaciones sueltas de todo el
   sistema: se pide elegir. Un listado global mezcla torneos y da cifras
   que no significan nada. */
if ($categoriaId <= 0) {
    require_once __DIR__ . "/inc/layout-top.php"; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-file-invoice-dollar me-2"></i>Elija una categoría</h3>
        </div>
        <div class="card-body">
            <?php if (!$categorias): ?>
                <p class="text-muted mb-0">Todavía no hay categorías creadas.</p>
            <?php else: ?>
            <div class="list-group">
                <?php foreach ($categorias as $c): ?>
                    <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                       href="<?php echo APP_URL; ?>cobranzaPanel/<?php echo (int)$c['categoria_id']; ?>/">
                        <span>
                            <strong><?php echo htmlspecialchars($c['categoria_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small class="text-muted ms-2"><?php echo htmlspecialchars($c['torneo_nombre'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </span>
                        <span class="badge text-bg-light border"><?php
                            $n = (int)$c['equipos'];
                            echo $n . ' ' . ($n === 1 ? 'equipo' : 'equipos'); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php require_once __DIR__ . "/inc/layout-bottom.php";
    return;
}

$cat = $insLeague->categoria($categoriaId);

if (!$cat) {
    require_once __DIR__ . "/inc/layout-top.php";
    echo '<div class="callout callout-warning"><h6 class="mb-1">'
       . '<i class="fas fa-exclamation-circle me-2"></i>Categoría no encontrada</h6>'
       . '<p class="mb-0 text-muted">Vuelva a elegirla desde la lista.</p></div>';
    require_once __DIR__ . "/inc/layout-bottom.php";
    return;
}

$tituloVista = 'Cobranza · ' . $cat['categoria_nombre'];

$filtroEstado = strtoupper(trim((string)($_GET['estado'] ?? '')));
$soloVencidas = ($_GET['vencidas'] ?? '') === '1';

$obligaciones = $insLeague->obligaciones([
    'categoria' => $categoriaId,
    'estado'    => \in_array($filtroEstado, ['PENDIENTE','PARCIAL','PAGADA','ANULADA'], true)
                     ? $filtroEstado : '',
    'vencidas'  => $soloVencidas,
]);

$resumen   = $insLeague->resumenCobranza($categoriaId);
$conceptos = $insLeague->conceptos();

/* Los cobros de todo el listado, en una sola consulta. */
$abonos = $insLeague->abonosDe(array_column($obligaciones, 'obligacion_id'));

$formasPago = ['01' => 'Efectivo', '16' => 'Tarjeta de débito',
               '17' => 'Otro', '19' => 'Tarjeta de crédito', '20' => 'Transferencia'];

/* Facturación: qué se puede emitir y qué se emitió ya. */
$facturables  = $insLeague->facturables($categoriaId);
$comprobantes = $insLeague->comprobantes($categoriaId, 30);
$sriListo     = sri_disponible();

/* Las facturables, agrupadas por equipo: un comprobante lleva una sola
   identificación de comprador, así que la pantalla no debe permitir
   siquiera seleccionar obligaciones de equipos distintos. */
$porEquipo = [];
foreach ($facturables as $f) {
    $porEquipo[(int)$f['equipoid']]['equipo'] = $f;
    $porEquipo[(int)$f['equipoid']]['filas'][] = $f;
}

/* Los selectores del formulario, ya acotados a esta categoría. */
$inscripciones = $insLeague->equiposDeCategoria($categoriaId, false);
$personas      = $insLeague->personasDeCategoria($categoriaId);
$partidos      = $insLeague->partidosDeCategoria($categoriaId);

$h    = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$dine = static fn($v) => number_format((float)$v, 2);

$tono = ['PENDIENTE' => 'warning', 'PARCIAL' => 'info',
         'PAGADA' => 'success', 'ANULADA' => 'secondary'];

$puedeCobrar = puede_crear('cobranzaPanel');
$puedeAnular = puede_eliminar('cobranzaPanel');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="mb-3">
    <a href="<?php echo APP_URL; ?>cobranzaPanel/" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Otra categoría
    </a>
    <span class="text-muted ms-2" style="font-size:.9rem;">
        <?php echo $h($cat['torneo_nombre']); ?> · <?php echo $h($cat['temporada_nombre']); ?>
    </span>
</div>

<?php /* Resumen. El vencido va aparte del pendiente porque es lo que
         cambia lo que se hace hoy, no lo que se sabrá a fin de mes. */ ?>
<div class="row">
    <div class="col-6 col-lg-3 mb-3">
        <div class="small-box text-bg-light">
            <div class="inner">
                <h3 style="font-size:1.6rem;"><?php echo $dine($resumen['facturado']); ?></h3>
                <p class="mb-0">Emitido</p>
            </div>
            <i class="fas fa-file-invoice small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="small-box text-bg-success">
            <div class="inner">
                <h3 style="font-size:1.6rem;"><?php echo $dine($resumen['cobrado']); ?></h3>
                <p class="mb-0">Cobrado</p>
            </div>
            <i class="fas fa-hand-holding-usd small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="small-box text-bg-warning">
            <div class="inner">
                <h3 style="font-size:1.6rem;"><?php echo $dine($resumen['pendiente']); ?></h3>
                <p class="mb-0">Por cobrar</p>
            </div>
            <i class="fas fa-clock small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3">
        <div class="small-box <?php echo (float)$resumen['vencido'] > 0 ? 'text-bg-danger' : 'text-bg-light'; ?>">
            <div class="inner">
                <h3 style="font-size:1.6rem;"><?php echo $dine($resumen['vencido']); ?></h3>
                <p class="mb-0">Vencido</p>
            </div>
            <i class="fas fa-exclamation-triangle small-box-icon" aria-hidden="true"></i>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header d-flex flex-wrap align-items-center" style="gap:.5rem;">
                <h3 class="card-title mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Obligaciones</h3>
                <div class="ms-auto">
                    <?php
                    $base = APP_URL . 'cobranzaPanel/' . $categoriaId . '/';
                    $tabs = ['' => 'Todas', 'PENDIENTE' => 'Pendientes',
                             'PARCIAL' => 'Parciales', 'PAGADA' => 'Pagadas'];
                    foreach ($tabs as $cod => $txt):
                        $activo = ($filtroEstado === $cod && !$soloVencidas); ?>
                        <a href="<?php echo $base . ($cod !== '' ? '?estado=' . $cod : ''); ?>"
                           class="btn btn-sm btn-<?php echo $activo ? 'primary' : 'outline-secondary'; ?>">
                            <?php echo $txt; ?>
                        </a>
                    <?php endforeach; ?>
                    <a href="<?php echo $base; ?>?vencidas=1"
                       class="btn btn-sm btn-<?php echo $soloVencidas ? 'danger' : 'outline-danger'; ?>">
                        Vencidas
                    </a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <?php /* El id distingue esta tabla de la del bloque de
                             facturación, que lista lo pendiente de emitir y
                             NO responde al filtro por estado —es correcto que
                             no lo haga—. Sin el id, buscar «tbody tr» encuentra
                             filas de las dos y una comprobación sobre el filtro
                             mira donde no debe. */ ?>
                    <table class="table table-sm mb-0" id="tablaObligaciones">
                        <thead>
                            <tr>
                                <th>Deudor</th>
                                <th>Concepto</th>
                                <th>Vence</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Abonado</th>
                                <th class="text-end">Saldo</th>
                                <th class="ds-tabla-acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$obligaciones): ?>
                            <?php /* Distinguir «no hay nada» de «nada con
                                     este filtro»: en el primer caso el
                                     usuario tiene que crear algo, en el
                                     segundo sólo quitar el filtro. */ ?>
                            <tr><td colspan="7" class="text-center text-muted py-4">
                                <?php echo ($filtroEstado !== '' || $soloVencidas)
                                    ? 'Ninguna obligación coincide con ese filtro.'
                                    : 'Todavía no hay obligaciones en esta categoría.'; ?>
                            </td></tr>
                        <?php else: foreach ($obligaciones as $o):
                            $vencida = (int)$o['dias_vencido'] > 0;
                            $saldo   = (float)$o['saldo']; ?>
                            <tr>
                                <td>
                                    <strong><?php echo $h($o['obligacion_deudor']); ?></strong>
                                    <?php if ($o['obligacion_detalle'] !== ''): ?>
                                        <br><small class="text-muted"><?php echo $h($o['obligacion_detalle']); ?></small>
                                    <?php endif; ?>
                                    <?php if ($o['factura_secuencial'] ?? null): ?>
                                        <br><small class="text-muted">
                                            <i class="fas fa-receipt me-1"></i>Fact. <?php echo $h($o['factura_secuencial']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $h($o['concepto_nombre']); ?>
                                    <br><span class="badge text-bg-<?php echo $tono[$o['obligacion_estado']] ?? 'secondary'; ?>">
                                        <?php echo $h($o['obligacion_estado']); ?></span>
                                </td>
                                <td class="<?php echo $vencida ? 'text-danger' : 'text-muted'; ?>"
                                    style="font-size:.85rem;">
                                    <?php echo $o['obligacion_vence'] ? $h($o['obligacion_vence']) : '—'; ?>
                                    <?php if ($vencida): ?>
                                        <br><small><?php echo (int)$o['dias_vencido']; ?> días</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?php echo $dine($o['total']); ?></td>
                                <td class="text-end text-success"><?php echo $dine($o['abonado']); ?></td>
                                <td class="text-end">
                                    <strong class="<?php echo $saldo > 0 ? ($vencida ? 'text-danger' : '') : 'text-muted'; ?>">
                                        <?php echo $dine($saldo); ?>
                                    </strong>
                                </td>
                                <td class="ds-tabla-acciones">
                                    <?php if ($puedeCobrar && $saldo > 0 && $o['obligacion_estado'] !== 'ANULADA'): ?>
                                    <button type="button" class="btn btn-sm btn-success js-cobrar" title="Registrar cobro"
                                            data-id="<?php echo (int)$o['obligacion_id']; ?>"
                                            data-deudor="<?php echo $h($o['obligacion_deudor']); ?>"
                                            data-saldo="<?php echo $dine($saldo); ?>">
                                        <i class="fas fa-hand-holding-usd"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if ((float)$o['abonado'] > 0): ?>
                                    <button type="button" class="btn btn-sm btn-actualizar js-abonos" title="Ver cobros"
                                            data-id="<?php echo (int)$o['obligacion_id']; ?>">
                                        <i class="fas fa-list"></i>
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($puedeAnular && $o['obligacion_estado'] !== 'ANULADA'
                                              && (float)$o['abonado'] <= 0): ?>
                                    <button type="button" class="btn btn-sm btn-eliminar js-anular-obl" title="Anular"
                                            data-id="<?php echo (int)$o['obligacion_id']; ?>"
                                            data-deudor="<?php echo $h($o['obligacion_deudor']); ?>">
                                        <i class="fas fa-ban"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php /* Los cobros de esta obligación. Nace
                                     plegada: el listado se lee por saldos,
                                     no por movimientos. */ ?>
                            <?php $lista = $abonos[(int)$o['obligacion_id']] ?? [];
                                  if ($lista): ?>
                            <tr id="abonos-<?php echo (int)$o['obligacion_id']; ?>" style="display:none;">
                                <td colspan="7" class="bg-light py-2">
                                    <table class="table table-sm mb-0 bg-transparent">
                                        <thead>
                                            <tr class="text-muted" style="font-size:.8rem;">
                                                <th>Fecha</th>
                                                <th>Forma</th>
                                                <th>Referencia</th>
                                                <th class="text-end">Importe</th>
                                                <th class="ds-tabla-acciones"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($lista as $a):
                                            $anulado = $a['abono_anulado'] === 'S'; ?>
                                            <?php /* La clase marca la fila como movimiento.
                                                     Sin ella hay que distinguirla por selector
                                                     de tabla anidada, y «tbody tr» acaba
                                                     contando también la cabecera de dentro. */ ?>
                                            <tr class="ds-abono <?php echo $anulado ? 'text-muted' : ''; ?>">
                                                <td><?php echo $h($a['abono_fecha']); ?></td>
                                                <td><?php echo $h($formasPago[$a['abono_forma']] ?? $a['abono_forma']); ?></td>
                                                <td>
                                                    <?php echo $a['abono_referencia'] !== ''
                                                                ? $h($a['abono_referencia']) : '—'; ?>
                                                    <?php if ($anulado): ?>
                                                        <br><small class="text-danger">
                                                            Anulado: <?php echo $h($a['abono_motivoanula']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end">
                                                    <span <?php echo $anulado
                                                        ? 'style="text-decoration:line-through;"' : ''; ?>>
                                                        <?php echo $dine($a['abono_valor']); ?>
                                                    </span>
                                                </td>
                                                <td class="ds-tabla-acciones">
                                                    <?php if ($puedeAnular && !$anulado): ?>
                                                    <button type="button" class="btn btn-sm btn-eliminar js-anular-abono"
                                                            title="Anular este cobro"
                                                            data-id="<?php echo (int)$a['abono_id']; ?>">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted" style="font-size:.85rem;">
                El saldo se calcula en cada consulta a partir de los cobros vigentes.
                Anular un cobro lo devuelve al saldo sin borrar el movimiento.
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-3">
        <?php if ($puedeCobrar): ?>
        <form class="FormularioAjax" method="POST" autocomplete="off"
              action="<?php echo APP_URL; ?>ajax/leagueAjax.php" id="formFila">
            <input type="hidden" name="modulo_league" value="guardarObligacion">
            <input type="hidden" name="origen_tipo" id="origen_tipo" value="INSCRIPCION">
            <?php /* La categoría en la que se está trabajando. Para los
                     orígenes que no la implican —una multa a un equipo—
                     es el único sitio de donde puede salir. */ ?>
            <input type="hidden" name="categoria_id" value="<?php echo $categoriaId; ?>">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-plus me-2"></i>Nueva obligación</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="concepto_id">Concepto</label>
                        <select name="concepto_id" id="concepto_id" class="form-select" required>
                            <option value="">— Elija —</option>
                            <?php foreach ($conceptos as $c): ?>
                                <option value="<?php echo (int)$c['concepto_id']; ?>"
                                        data-ambito="<?php echo $h($c['concepto_ambito']); ?>"
                                        data-valor="<?php echo $dine($c['concepto_valor']); ?>">
                                    <?php echo $h($c['concepto_nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            El concepto decide a qué se puede aplicar la obligación.
                        </small>
                    </div>

                    <?php /* Un bloque por ámbito. Se muestra el que
                             corresponde al concepto elegido; el resto se
                             deshabilita para que el navegador no envíe
                             selects que no vienen a cuento. */ ?>
                    <div class="mb-3 js-origen" data-para="INSCRIPCION" style="display:none;">
                        <label for="origen_INSCRIPCION">Equipo inscrito</label>
                        <select id="origen_INSCRIPCION" class="form-select js-origen-campo" disabled>
                            <option value="">— Elija —</option>
                            <?php foreach ($inscripciones as $i): ?>
                                <option value="<?php echo (int)$i['inscripcion_id']; ?>">
                                    <?php echo $h($i['equipo_nombre']); ?>
                                    (<?php echo $h($i['estado_nombre']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 js-origen" data-para="EQUIPO" style="display:none;">
                        <label for="origen_EQUIPO">Equipo</label>
                        <select id="origen_EQUIPO" class="form-select js-origen-campo" disabled>
                            <option value="">— Elija —</option>
                            <?php foreach ($inscripciones as $i): ?>
                                <option value="<?php echo (int)$i['equipo_id']; ?>">
                                    <?php echo $h($i['equipo_nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 js-origen" data-para="PERSONA" style="display:none;">
                        <label for="origen_PERSONA">Persona</label>
                        <select id="origen_PERSONA" class="form-select js-origen-campo" disabled>
                            <option value="">— Elija —</option>
                            <?php foreach ($personas as $p): ?>
                                <option value="<?php echo (int)$p['persona_id']; ?>">
                                    <?php echo $h($p['persona_apellidos'] . ' ' . $p['persona_nombres']); ?>
                                    · <?php echo $h($p['equipo_nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3 js-origen" data-para="PARTIDO" style="display:none;">
                        <label for="origen_PARTIDO">Partido</label>
                        <select id="origen_PARTIDO" class="form-select js-origen-campo" disabled>
                            <option value="">— Elija —</option>
                            <?php foreach ($partidos as $p): ?>
                                <option value="<?php echo (int)$p['partido_id']; ?>"
                                        data-local="<?php echo (int)$p['local_equipoid']; ?>"
                                        data-local-nombre="<?php echo $h($p['local_nombre']); ?>"
                                        data-visitante="<?php echo (int)$p['visitante_equipoid']; ?>"
                                        data-visitante-nombre="<?php echo $h($p['visitante_nombre']); ?>">
                                    <?php echo $p['partido_fecha'] ? $h($p['partido_fecha']) . ' · ' : ''; ?>
                                    <?php echo $h($p['local_nombre']); ?> vs <?php echo $h($p['visitante_nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="equipo_id" class="mt-2">Se le cobra a</label>
                        <select name="equipo_id" id="equipo_id" class="form-select" disabled>
                            <option value="">— Elija primero el partido —</option>
                        </select>
                        <small class="form-text text-muted">
                            Un partido no debe dinero: lo deben los equipos que lo juegan.
                        </small>
                    </div>

                    <input type="hidden" name="origen_id" id="origen_id" value="0">

                    <div class="row g-2">
                        <div class="mb-3 col-6">
                            <label for="valor">Valor</label>
                            <input type="number" name="valor" id="valor" class="form-control text-end"
                                   step="0.01" min="0.01" required>
                        </div>
                        <div class="mb-3 col-6">
                            <label for="vence">Vence</label>
                            <input type="date" name="vence" id="vence" class="form-control"
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="mb-3 col-6">
                            <label for="descuento">Descuento</label>
                            <input type="number" name="descuento" id="descuento" class="form-control text-end"
                                   step="0.01" min="0" value="0.00">
                        </div>
                        <div class="mb-3 col-6">
                            <label for="recargo">Recargo</label>
                            <input type="number" name="recargo" id="recargo" class="form-control text-end"
                                   step="0.01" min="0" value="0.00">
                        </div>
                    </div>
                    <div class="mb-3 mb-0">
                        <label for="detalle">Detalle</label>
                        <input type="text" name="detalle" id="detalle" class="form-control"
                               maxlength="250" placeholder="Cuota única, segunda letra…">
                    </div>
                </div>
                <?php echo ds_acciones_form('', ['limpiar' => true]); ?>
            </div>
        </form>
        <?php else: ?>
        <div class="callout callout-info">
            <h6 class="mb-1"><i class="fas fa-info-circle me-2"></i>Sólo lectura</h6>
            <p class="mb-0 text-muted" style="font-size:.9rem;">
                Su rol puede consultar la cobranza pero no generar obligaciones ni
                registrar cobros.
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php /*==========  Facturación  ==========*/ ?>
<div class="row">
    <div class="col-lg-7 mb-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-file-invoice me-2"></i>Emitir comprobante</h3>
            </div>
            <div class="card-body">
                <?php if (!$sriListo['ok']): ?>
                    <div class="callout callout-warning mb-0">
                        <h6 class="mb-1"><i class="fas fa-exclamation-triangle me-2"></i>
                            Facturación no configurada</h6>
                        <p class="mb-0 text-muted" style="font-size:.9rem;">
                            <?php echo $h(ucfirst(implode('; ', $sriListo['faltan']))); ?>.
                        </p>
                    </div>
                <?php elseif (!$porEquipo): ?>
                    <p class="text-muted mb-0">
                        No hay obligaciones pendientes de facturar en esta categoría.
                    </p>
                <?php else: ?>
                    <p class="text-muted mb-3" style="font-size:.88rem;">
                        Un comprobante lleva una sola identificación de comprador, así que las
                        obligaciones se agrupan por equipo. Los conceptos de jugador se facturan
                        al club, nunca a la persona.
                    </p>

                    <?php foreach ($porEquipo as $eqid => $bloque):
                        $eq = $bloque['equipo'];
                        $puedeEmitir = $eq['equipo_identificacion'] !== ''
                                    && $eq['equipo_razonsocial'] !== ''
                                    && $eq['equipo_direccion'] !== ''; ?>
                        <div class="border rounded p-2 mb-2">
                            <div class="d-flex align-items-center mb-2" style="gap:.5rem;">
                                <strong><?php echo $h($eq['equipo_nombre']); ?></strong>
                                <?php if ($puedeEmitir): ?>
                                    <small class="text-muted"><?php echo $h($eq['equipo_identificacion']); ?></small>
                                <?php else: ?>
                                    <span class="badge text-bg-warning">sin datos tributarios</span>
                                <?php endif; ?>
                                <?php if ($puedeEmitir && $puedeCobrar): ?>
                                <button type="button" class="btn btn-sm btn-primary ms-auto js-emitir"
                                        data-equipo="<?php echo (int)$eqid; ?>"
                                        data-nombre="<?php echo $h($eq['equipo_razonsocial']); ?>">
                                    <i class="fas fa-file-invoice me-1"></i>Emitir
                                </button>
                                <?php else: ?>
                                <a href="<?php echo APP_URL; ?>equipoList/"
                                   class="btn btn-sm btn-outline-secondary ms-auto">
                                    Completar datos
                                </a>
                                <?php endif; ?>
                            </div>
                            <table class="table table-sm mb-0">
                                <tbody>
                                <?php foreach ($bloque['filas'] as $o): ?>
                                    <tr>
                                        <td style="width:2rem;">
                                            <input type="checkbox" class="js-facturable"
                                                   data-equipo="<?php echo (int)$eqid; ?>"
                                                   value="<?php echo (int)$o['obligacion_id']; ?>"
                                                   <?php echo $puedeEmitir ? 'checked' : 'disabled'; ?>>
                                        </td>
                                        <td>
                                            <?php echo $h($o['concepto_nombre']); ?>
                                            <?php if ($o['obligacion_detalle'] !== ''): ?>
                                                <small class="text-muted"> · <?php echo $h($o['obligacion_detalle']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo $dine($o['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5 mb-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-receipt me-2"></i>Comprobantes emitidos</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Número</th>
                                <th>Comprador</th>
                                <th class="text-end">Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$comprobantes): ?>
                            <tr><td colspan="4" class="text-center text-muted py-4">
                                Todavía no se ha emitido ningún comprobante.
                            </td></tr>
                        <?php else: foreach ($comprobantes as $c):
                            $tonoSri = ['AUTORIZADO' => 'success', 'GENERADA' => 'secondary',
                                        'FIRMADA' => 'info', 'ENVIADA' => 'info',
                                        'DEVUELTA' => 'danger', 'NO_AUTORIZADO' => 'danger',
                                        'ERROR' => 'danger', 'ANULADA' => 'dark']; ?>
                            <tr>
                                <td>
                                    <code style="font-size:.8rem;"><?php
                                        echo $h($c['factura_establecimiento'] . '-'
                                              . $c['factura_puntoemision'] . '-'
                                              . $c['factura_secuencial']); ?></code>
                                    <br><small class="text-muted"><?php echo $h($c['factura_fechaemision']); ?></small>
                                </td>
                                <td style="font-size:.85rem;">
                                    <?php echo $h($c['factura_clienterazon']); ?>
                                    <br><small class="text-muted"><?php echo $h($c['factura_clienteid_num']); ?></small>
                                    <?php /* La clave de acceso es lo que se
                                             cita ante el SRI cuando hay que
                                             preguntar por un comprobante:
                                             tenerla a mano ahorra entrar a
                                             la base a buscarla. */ ?>
                                    <br><small class="text-muted js-clave"
                                               style="font-size:.7rem;word-break:break-all;"
                                               title="Clave de acceso">
                                        <?php echo $h($c['factura_claveacceso']); ?>
                                    </small>
                                </td>
                                <td class="text-end"><?php echo $dine($c['factura_total']); ?></td>
                                <td>
                                    <span class="badge text-bg-<?php echo $tonoSri[$c['factura_estadosri']] ?? 'secondary'; ?>">
                                        <?php echo $h($c['factura_estadosri']); ?></span>
                                    <br><small class="text-muted"><?php echo (int)$c['obligaciones']; ?> líneas</small>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted" style="font-size:.85rem;">
                GENERADA significa que el XML existe y el número está consumido. Firmarlo y
                enviarlo al SRI es el paso siguiente.
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var url = '<?php echo APP_URL; ?>ajax/leagueAjax.php';

    var enviar = function (campos) {
        var fd = new FormData();
        for (var k in campos) { fd.append(k, campos[k]); }
        fetch(url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                Swal.fire({ icon: j.icono, title: j.titulo, text: j.texto })
                    .then(function () { if (j.tipo === 'recargar') { location.reload(); } });
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Sin respuesta',
                            text: 'No se pudo contactar con el servidor.' });
            });
    };

    /*----------  Formulario: el concepto manda  ----------*/
    var concepto = document.getElementById('concepto_id');

    if (concepto) {
        var tipo   = document.getElementById('origen_tipo');
        var origen = document.getElementById('origen_id');
        var cobra  = document.getElementById('equipo_id');

        /* El campo que de verdad se envía es origen_id. Los selects
           visibles sólo lo alimentan: así el servidor recibe un único
           par (tipo, id) y no cuatro campos de los que tres sobran. */
        var sincronizar = function () {
            var op = concepto.options[concepto.selectedIndex];
            var ambito = op ? (op.getAttribute('data-ambito') || '') : '';

            tipo.value  = ambito;
            origen.value = '0';

            document.querySelectorAll('.js-origen').forEach(function (bloque) {
                var suyo = bloque.getAttribute('data-para') === ambito;
                bloque.style.display = suyo ? '' : 'none';
                var campo = bloque.querySelector('.js-origen-campo');
                if (campo) { campo.disabled = !suyo; if (!suyo) { campo.value = ''; } }
            });

            if (cobra) { cobra.disabled = (ambito !== 'PARTIDO'); }

            /* El valor del catálogo es una sugerencia: sólo se propone si
               el usuario todavía no escribió nada. */
            var valor = document.getElementById('valor');
            var sug   = op ? parseFloat(op.getAttribute('data-valor') || '0') : 0;
            if (valor && sug > 0 && !valor.value) { valor.value = sug.toFixed(2); }
        };

        concepto.addEventListener('change', sincronizar);
        sincronizar();

        document.querySelectorAll('.js-origen-campo').forEach(function (campo) {
            campo.addEventListener('change', function () {
                origen.value = campo.value || '0';

                /* Al elegir partido, los dos equipos que lo juegan pasan a
                   ser las únicas opciones de cobro. */
                if (campo.id === 'origen_PARTIDO' && cobra) {
                    var op = campo.options[campo.selectedIndex];
                    cobra.innerHTML = '<option value="">— Elija —</option>';
                    if (op && op.value) {
                        [['local', 'local-nombre'], ['visitante', 'visitante-nombre']]
                            .forEach(function (par) {
                                var o = document.createElement('option');
                                o.value = op.getAttribute('data-' + par[0]);
                                o.textContent = op.getAttribute('data-' + par[1]);
                                cobra.appendChild(o);
                            });
                    }
                }
            });
        });
    }

    /*----------  Registrar un cobro  ----------*/
    document.querySelectorAll('.js-cobrar').forEach(function (b) {
        b.addEventListener('click', function () {
            var saldo = b.getAttribute('data-saldo');

            Swal.fire({
                title: 'Registrar cobro',
                html: '<div style="text-align:left">'
                    + '<p class="mb-2">' + b.getAttribute('data-deudor') + '</p>'
                    + '<p class="mb-2 text-muted">Saldo pendiente: <b>' + saldo + '</b></p>'
                    + '<label style="font-size:.85rem;">Importe</label>'
                    + '<input id="ab_valor" type="number" step="0.01" min="0.01" max="' + saldo + '"'
                    +        ' value="' + saldo + '" class="swal2-input" style="margin:0 0 .6rem 0;width:100%">'
                    + '<label style="font-size:.85rem;">Fecha</label>'
                    + '<input id="ab_fecha" type="date" value="<?php echo date('Y-m-d'); ?>"'
                    +        ' max="<?php echo date('Y-m-d'); ?>" class="swal2-input"'
                    +        ' style="margin:0 0 .6rem 0;width:100%">'
                    + '<label style="font-size:.85rem;">Forma de pago</label>'
                    + '<select id="ab_forma" class="swal2-input" style="margin:0 0 .6rem 0;width:100%">'
                    +   '<option value="01">Efectivo</option>'
                    +   '<option value="20">Transferencia</option>'
                    +   '<option value="19">Tarjeta de crédito</option>'
                    +   '<option value="16">Tarjeta de débito</option>'
                    +   '<option value="17">Otro</option>'
                    + '</select>'
                    + '<label style="font-size:.85rem;">Referencia</label>'
                    + '<input id="ab_ref" type="text" maxlength="60" class="swal2-input"'
                    +        ' style="margin:0;width:100%" placeholder="Nº de depósito, comprobante…">'
                    + '</div>',
                showCancelButton: true,
                confirmButtonText: 'Registrar',
                cancelButtonText:  'Cancelar',
                confirmButtonColor: '#28a745',
                preConfirm: function () {
                    var v = parseFloat(document.getElementById('ab_valor').value || '0');
                    if (!(v > 0)) { Swal.showValidationMessage('Indique un importe mayor que cero.'); return false; }
                    if (v > parseFloat(saldo)) {
                        Swal.showValidationMessage('El importe supera el saldo pendiente.');
                        return false;
                    }
                    return { valor: v,
                             fecha: document.getElementById('ab_fecha').value,
                             forma: document.getElementById('ab_forma').value,
                             referencia: document.getElementById('ab_ref').value };
                }
            }).then(function (r) {
                if (r.isConfirmed) {
                    enviar({ modulo_league: 'guardarAbono',
                             obligacion_id: b.getAttribute('data-id'),
                             valor: r.value.valor, fecha: r.value.fecha,
                             forma: r.value.forma, referencia: r.value.referencia });
                }
            });
        });
    });

    /*----------  Ver y anular cobros  ----------*/
    document.querySelectorAll('.js-abonos').forEach(function (b) {
        b.addEventListener('click', function () {
            var caja = document.getElementById('abonos-' + b.getAttribute('data-id'));
            if (!caja) { return; }
            caja.style.display = caja.style.display === 'none' ? '' : 'none';
        });
    });

    document.querySelectorAll('.js-anular-abono').forEach(function (b) {
        b.addEventListener('click', function () {
            Swal.fire({
                icon: 'warning',
                title: '¿Anular este cobro?',
                html: 'El movimiento NO se borra: queda marcado como anulado y su importe '
                    + 'vuelve al saldo. Escriba por qué.',
                input: 'text',
                inputPlaceholder: 'Motivo de la anulación',
                showCancelButton: true,
                confirmButtonText: 'Anular',
                cancelButtonText:  'Cancelar',
                confirmButtonColor: '#dc3545',
                preConfirm: function (v) {
                    if (!v || !v.trim()) {
                        Swal.showValidationMessage('El motivo es obligatorio.');
                        return false;
                    }
                    return v.trim();
                }
            }).then(function (r) {
                if (r.isConfirmed) {
                    enviar({ modulo_league: 'anularAbono',
                             abono_id: b.getAttribute('data-id'), motivo: r.value });
                }
            });
        });
    });

    /*----------  Emitir comprobante  ----------*/
    document.querySelectorAll('.js-emitir').forEach(function (b) {
        b.addEventListener('click', function () {
            var eq = b.getAttribute('data-equipo');

            /* Sólo las de ESTE equipo: mezclar deudores produciría una
               factura a nombre de quien no debe la mitad de lo que dice,
               y el servidor la rechaza igualmente. */
            var ids = [...document.querySelectorAll('.js-facturable')]
                .filter(function (c) { return c.getAttribute('data-equipo') === eq && c.checked; })
                .map(function (c) { return c.value; });

            if (!ids.length) {
                Swal.fire({ icon: 'warning', title: 'Nada seleccionado',
                            text: 'Marque al menos una obligación de este equipo.' });
                return;
            }

            Swal.fire({
                icon: 'question',
                title: '¿Emitir el comprobante?',
                html: '<div style="text-align:left">'
                    + '<p class="mb-2">Se emitirá a <b>' + b.getAttribute('data-nombre') + '</b> '
                    + 'por <b>' + ids.length + '</b> concepto' + (ids.length === 1 ? '' : 's') + '.</p>'
                    + '<p class="mb-2 text-muted" style="font-size:.87rem;">El número de '
                    + 'comprobante se consume al emitir y <b>no se reutiliza</b>, aunque algo '
                    + 'falle después: el SRI admite huecos en la numeración, pero no '
                    + 'repeticiones.</p>'
                    + '<label style="font-size:.85rem;">Forma de pago</label>'
                    + '<select id="fa_forma" class="swal2-input" style="margin:0;width:100%">'
                    +   '<option value="01">Efectivo</option>'
                    +   '<option value="20" selected>Transferencia</option>'
                    +   '<option value="19">Tarjeta de crédito</option>'
                    +   '<option value="16">Tarjeta de débito</option>'
                    + '</select></div>',
                showCancelButton: true,
                confirmButtonText: 'Emitir',
                cancelButtonText:  'Cancelar',
                preConfirm: function () { return document.getElementById('fa_forma').value; }
            }).then(function (r) {
                if (r.isConfirmed) {
                    enviar({ modulo_league: 'emitirComprobante',
                             obligaciones: ids.join(','), forma: r.value });
                }
            });
        });
    });

    /*----------  Anular una obligación  ----------*/
    document.querySelectorAll('.js-anular-obl').forEach(function (b) {
        b.addEventListener('click', function () {
            Swal.fire({
                icon: 'warning',
                title: '¿Anular la obligación?',
                html: 'Dejará de contar como deuda de <b>' + b.getAttribute('data-deudor')
                    + '</b>. Escriba por qué.',
                input: 'text',
                inputPlaceholder: 'Motivo de la anulación',
                showCancelButton: true,
                confirmButtonText: 'Anular',
                cancelButtonText:  'Cancelar',
                confirmButtonColor: '#dc3545',
                preConfirm: function (v) {
                    if (!v || !v.trim()) {
                        Swal.showValidationMessage('El motivo es obligatorio.');
                        return false;
                    }
                    return v.trim();
                }
            }).then(function (r) {
                if (r.isConfirmed) {
                    enviar({ modulo_league: 'anularObligacion',
                             obligacion_id: b.getAttribute('data-id'), motivo: r.value });
                }
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
