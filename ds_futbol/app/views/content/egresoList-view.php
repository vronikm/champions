<?php
/*
| Fútbol › Balance › Egresos (rediseño 2026-11-25)
|
| · Criterios de búsqueda: mes, sede, bloque del reporte, concepto y texto
|   (empresa o detalle). Por omisión, el mes en curso; «Todos los meses»
|   los muestra todos, agrupados por mes.
| · Resumen del mes: total, número de egresos, concepto principal y los que
|   no tienen bloque del reporte (salen en «Egresos generales» de Insights ›
|   Ingresos del mes).
| · Lista por mes: tabla en el escritorio y fichas en el celular.
| · Registrar y editar en un modal (a pantalla completa en el celular). Se
|   guarda con balanceAjax.php como antes; al guardar vuelve al mes del
|   egreso. /egresoList/{id}/ (enlace antiguo) abre el modal de ese egreso.
*/
use app\controllers\balanceController;
$insEgreso = new balanceController();

$h   = static fn ($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$din = static fn ($x) => '$' . number_format((float)$x, 2);
$MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$nombreMes = static function (string $ym) use ($MESES): string {
	[$a, $m] = array_map('intval', explode('-', $ym));
	return ucfirst($MESES[$m] ?? '') . ' ' . $a;
};

$puedeCrear    = puede_crear('egresoList');
$puedeEditar   = puede_editar('egresoList');
$puedeEliminar = puede_eliminar('egresoList');

/* Enlace antiguo /egresoList/{id}/: abre ese egreso, en su mes. */
$abrirId = (int)(($url[1] ?? '') !== '' ? $url[1] : 0);

/* Criterios */
$mesActual = date('Y-m');
$mesGet = (string)($_GET['mes'] ?? '');
if ($mesGet === 'todos') {
	$mes = '';
} elseif (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesGet)) {
	$mes = $mesGet;
} else {
	$mes = ($abrirId > 0 ? $insEgreso->mesDeEgreso($abrirId) : '') ?: $mesActual;
}
$sedeF     = (int)($_GET['sede'] ?? 0);
$bloqueF   = strtoupper((string)($_GET['bloque'] ?? ''));
$conceptoF = (string)($_GET['concepto'] ?? '');
$q         = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));

$sedes     = $insEgreso->sedesEgreso();
$bloques   = $insEgreso->bloquesEgreso();
$conceptos = $insEgreso->catalogoEgreso('balance_egreso');
$formas    = $insEgreso->catalogoEgreso('forma_entregaingreso');
$meses     = $insEgreso->egresosMeses();
if (!isset($meses[$mesActual])) { $meses = [$mesActual => 0] + $meses; krsort($meses); }
if ($mes !== '' && !isset($meses[$mes])) { $meses[$mes] = 0; krsort($meses); }

$filas = $insEgreso->egresosFiltrados(['mes' => $mes, 'sede' => $sedeF, 'bloque' => $bloqueF, 'concepto' => $conceptoF, 'q' => $q]);
$filtrosExtra = ($sedeF > 0) + ($bloqueF !== '') + ($conceptoF !== '') + ($q !== '');

/* Resumen */
$total = 0.0; $sinBloque = 0; $sinBloqueMonto = 0.0; $porConcepto = []; $grupos = [];
foreach ($filas as $f) {
	$monto = (float)$f['egreso_monto'];
	$total += $monto;
	if ((string)$f['egreso_rubroid'] === '') { $sinBloque++; $sinBloqueMonto += $monto; }
	$c = $conceptos[$f['egreso_concepto']] ?? 'Sin concepto';
	$porConcepto[$c] = ($porConcepto[$c] ?? 0) + $monto;
	$ym = substr($f['egreso_fechapago'], 0, 7);
	$grupos[$ym]['filas'][] = $f;
	$grupos[$ym]['total'] = ($grupos[$ym]['total'] ?? 0) + $monto;
}
arsort($porConcepto);
$conceptoTop = array_key_first($porConcepto);

/* Enlaces que conservan los demás criterios */
$enlace = static function (array $cambios) use ($mes, $sedeF, $bloqueF, $conceptoF, $q): string {
	$p = array_merge(['mes' => $mes === '' ? 'todos' : $mes, 'sede' => $sedeF ?: '', 'bloque' => $bloqueF, 'concepto' => $conceptoF, 'q' => $q], $cambios);
	$p = array_filter($p, static fn ($v) => $v !== '' && $v !== null);
	return APP_URL . 'egresoList/' . ($p ? '?' . http_build_query($p) : '');
};

/* Lo que el modal necesita de cada egreso (sin rutas del servidor). */
$datosEgreso = static function (array $f): array {
	return [
		'id'       => (int)$f['egreso_id'],
		'sede'     => (string)$f['egreso_sedeid'],
		'fecha'    => (string)$f['egreso_fechapago'],
		'empresa'  => html_entity_decode((string)$f['egreso_empresa'], ENT_QUOTES, 'UTF-8'),
		'monto'    => number_format((float)$f['egreso_monto'], 2, '.', ''),
		'forma'    => (string)$f['egreso_formaentrega'],
		'concepto' => (string)$f['egreso_concepto'],
		'bloque'   => (string)$f['egreso_rubroid'],
		'lugar'    => (string)($f['egreso_lugarid'] ?? ''),
		'detalle'  => html_entity_decode((string)$f['egreso_descripcion'], ENT_QUOTES, 'UTF-8'),
		'foto'     => (string)$f['egreso_imagenpago'] !== '' ? media_url('fegreso', $f['egreso_imagenpago']) : '',
	];
};
$bloqueTexto = static function (array $f) use ($bloques, $h): string {
	if ((string)$f['egreso_rubroid'] === '') {
		return '<span class="badge text-bg-warning egr-chip" title="Sale en «Egresos generales» del reporte Ingresos del mes">Sin bloque</span>';
	}
	return '<span class="badge egr-chip egr-chip--bloque">' . $h($bloques[$f['egreso_rubroid']] ?? $f['egreso_rubroid'])
	     . ($f['lugar_nombre'] ? ' · ' . $h($f['lugar_nombre']) : '') . '</span>';
};
$acciones = static function (array $f, string $clase = '') use ($puedeEditar, $puedeEliminar, $datosEgreso, $h, $insEgreso): string {
	$html = '';
	if ((string)$f['egreso_imagenpago'] !== '') {
		$html .= '<a class="btn btn-outline-secondary btn-sm' . $clase . '" href="' . $h(media_url('fegreso', $f['egreso_imagenpago'])) . '" target="_blank" rel="noopener" title="Ver comprobante" aria-label="Ver comprobante de ' . $h($f['egreso_empresa']) . '"><i class="fas fa-image" aria-hidden="true"></i><span class="egr-accion__txt">Comprobante</span></a>';
	}
	if ($puedeEditar) {
		$html .= '<button type="button" class="btn btn-outline-primary btn-sm egr-editar' . $clase . '" data-egreso="' . $h(json_encode($datosEgreso($f), JSON_UNESCAPED_UNICODE)) . '" title="Editar" aria-label="Editar egreso de ' . $h($f['egreso_empresa']) . '"><i class="fas fa-pen" aria-hidden="true"></i><span class="egr-accion__txt">Editar</span></button>';
	}
	if ($puedeEliminar) {
		$html .= '<form class="FormularioAjax d-inline" action="' . APP_URL . 'app/ajax/balanceAjax.php" method="POST" autocomplete="off">'
		       . '<input type="hidden" name="modulo_egreso" value="eliminar"><input type="hidden" name="egreso_id" value="' . (int)$f['egreso_id'] . '">'
		       . '<button type="submit" class="btn btn-danger btn-sm' . $clase . '" title="Eliminar" aria-label="Eliminar egreso de ' . $h($f['egreso_empresa']) . '"><i class="fas fa-trash" aria-hidden="true"></i><span class="egr-accion__txt">Eliminar</span></button></form>';
	}
	return $html;
};

/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
$tituloVista = 'Egresos';
$extras      = array (0 => 'swal',);
require_once "app/views/inc/cabecera.php";
?>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">
      <?php require_once "app/views/inc/navbar.php"; ?>
      <?php require_once "app/views/inc/main-sidebar.php"; ?>

      <div class="app-main">
		<div class="app-content-header">
			<div class="container-fluid">
				<div class="egr-cabecera">
					<div>
						<h4 class="m-0">Egresos</h4>
						<div class="small text-body-secondary">Pagos de la escuela: arriendos, publicidad y otros gastos</div>
					</div>
					<?php if ($puedeCrear): ?>
						<button type="button" class="btn btn-primary egr-nuevo" data-egr-nuevo>
							<i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo egreso
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">

				<!-- =================== Criterios de búsqueda =================== -->
				<form method="GET" action="<?php echo APP_URL; ?>egresoList/" autocomplete="off" class="card mb-3 egr-filtros" id="egrFiltros">
					<div class="card-header egr-filtros__cab">
						<h3 class="card-title mb-0"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<button type="button" class="btn btn-sm btn-outline-secondary d-md-none egr-filtros__plegar" data-bs-toggle="collapse" data-bs-target="#egrFiltrosCuerpo"
						        aria-expanded="false" aria-controls="egrFiltrosCuerpo">
							<i class="fas fa-sliders me-1" aria-hidden="true"></i><span class="egr-filtros__resumen"><?php echo $filtrosExtra ? 'Filtros (' . $filtrosExtra . ')' : 'Filtros'; ?></span>
							<i class="fas fa-chevron-down ms-1" aria-hidden="true"></i>
						</button>
					</div>
					<div class="card-body pb-2">
						<div class="row g-2 g-md-3">
							<div class="col-12 col-md-4 col-xl-2">
								<label for="f_mes" class="form-label">Mes</label>
								<select class="form-select" id="f_mes" name="mes" data-egr-auto>
									<option value="todos" <?php echo $mes === '' ? 'selected' : ''; ?>>Todos los meses</option>
									<?php foreach ($meses as $ym => $n): ?>
										<option value="<?php echo $h($ym); ?>" <?php echo $ym === $mes ? 'selected' : ''; ?>>
											<?php echo $h($nombreMes($ym)); ?><?php echo $n ? ' (' . $n . ')' : ''; ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="collapse egr-filtros__extra<?php echo $filtrosExtra ? ' show' : ''; ?>" id="egrFiltrosCuerpo">
							<div class="row g-2 g-md-3 mt-0 egr-filtros__extra">
								<div class="col-6 col-md-4 col-xl-2">
									<label for="f_sede" class="form-label">Sede</label>
									<select class="form-select" id="f_sede" name="sede">
										<option value="">Todas</option>
										<?php foreach ($sedes as $id => $nombre): ?>
											<option value="<?php echo (int)$id; ?>" <?php echo $id === $sedeF ? 'selected' : ''; ?>><?php echo $h($nombre); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-6 col-md-4 col-xl-2">
									<label for="f_concepto" class="form-label">Concepto</label>
									<select class="form-select" id="f_concepto" name="concepto">
										<option value="">Todos</option>
										<?php foreach ($conceptos as $v => $nombre): ?>
											<option value="<?php echo $h($v); ?>" <?php echo $v === $conceptoF ? 'selected' : ''; ?>><?php echo $h($nombre); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-12 col-md-4 col-xl-2">
									<label for="f_bloque" class="form-label">Bloque del reporte</label>
									<select class="form-select" id="f_bloque" name="bloque">
										<option value="">Todos</option>
										<option value="SIN" <?php echo $bloqueF === 'SIN' ? 'selected' : ''; ?>>Sin bloque</option>
										<?php foreach ($bloques as $v => $nombre): ?>
											<option value="<?php echo $h($v); ?>" <?php echo $v === $bloqueF ? 'selected' : ''; ?>><?php echo $h($nombre); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-12 col-md-8 col-xl">
									<label for="f_q" class="form-label">Empresa o detalle</label>
									<input type="search" class="form-control" id="f_q" name="q" maxlength="80" placeholder="Nombre de la empresa, detalle…" value="<?php echo $h($q); ?>">
								</div>
								<div class="col-12 col-md-4 col-xl-auto d-flex align-items-end gap-2">
									<div class="flex-grow-1"><?php echo ds_boton_buscar(); ?></div>
									<?php if ($filtrosExtra || $mes !== $mesActual): ?>
										<a href="<?php echo APP_URL; ?>egresoList/" class="btn btn-outline-secondary" title="Quitar los filtros y volver al mes en curso">
											<i class="fas fa-eraser" aria-hidden="true"></i><span class="visually-hidden">Limpiar</span>
										</a>
									<?php endif; ?>
								</div>
							</div>
							</div>
						</div>
					</div>
				</form>

				<!-- =================== Resumen =================== -->
				<?php /* El mes se elige sólo en «Criterios de búsqueda» (sin flechas
				         aparte); cada grupo de la lista lleva su mes en la cabecera. */ ?>
				<div class="egr-kpis mb-3">
					<div class="egr-kpi egr-kpi--total">
						<span class="egr-kpi__et">Total egresado</span>
						<strong class="egr-kpi__val"><?php echo $din($total); ?></strong>
						<span class="egr-kpi__nota"><?php echo count($filas); ?> egreso<?php echo count($filas) === 1 ? '' : 's'; ?></span>
					</div>
					<div class="egr-kpi">
						<span class="egr-kpi__et">Promedio por egreso</span>
						<strong class="egr-kpi__val"><?php echo $din(count($filas) ? $total / count($filas) : 0); ?></strong>
						<span class="egr-kpi__nota"><?php echo count($grupos) > 1 ? count($grupos) . ' meses' : '&nbsp;'; ?></span>
					</div>
					<div class="egr-kpi">
						<span class="egr-kpi__et">Concepto principal</span>
						<strong class="egr-kpi__val egr-kpi__val--texto"><?php echo $conceptoTop !== null ? $h($conceptoTop) : '—'; ?></strong>
						<span class="egr-kpi__nota"><?php echo $conceptoTop !== null ? $din($porConcepto[$conceptoTop]) . ($total > 0 ? ' · ' . round($porConcepto[$conceptoTop] * 100 / $total) . ' %' : '') : '&nbsp;'; ?></span>
					</div>
					<a class="egr-kpi egr-kpi--aviso<?php echo $sinBloque ? '' : ' egr-kpi--ok'; ?>" href="<?php echo $h($enlace(['bloque' => $sinBloque && $bloqueF !== 'SIN' ? 'SIN' : ''])); ?>"
					   title="<?php echo $sinBloque ? 'Ver sólo los egresos sin bloque del reporte' : ''; ?>">
						<span class="egr-kpi__et">Sin bloque del reporte</span>
						<strong class="egr-kpi__val"><?php echo $sinBloque; ?></strong>
						<span class="egr-kpi__nota"><?php echo $sinBloque ? $din($sinBloqueMonto) . ' en «Egresos generales»' : 'Todos asignados'; ?></span>
					</a>
				</div>

				<!-- =================== Egresos por mes =================== -->
				<?php if (!$filas): ?>
					<div class="card">
						<div class="card-body text-center py-5 egr-vacio">
							<i class="fas fa-receipt fa-2x mb-3 text-body-tertiary" aria-hidden="true"></i>
							<p class="mb-1 fw-semibold">No hay egresos <?php echo $mes !== '' ? 'en ' . $h(mb_strtolower($nombreMes($mes))) : ''; ?><?php echo $filtrosExtra ? ' con estos criterios' : ''; ?>.</p>
							<p class="small text-body-secondary mb-3">Cambie el mes o los criterios de búsqueda<?php echo $puedeCrear ? ', o registre uno nuevo' : ''; ?>.</p>
							<?php if ($puedeCrear): ?>
								<button type="button" class="btn btn-primary" data-egr-nuevo><i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo egreso</button>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php foreach ($grupos as $ym => $g): ?>
					<section class="card mb-3 egr-mes" aria-labelledby="egrMes<?php echo $h(str_replace('-', '', $ym)); ?>">
						<div class="card-header egr-mes__cab">
							<h3 class="card-title mb-0" id="egrMes<?php echo $h(str_replace('-', '', $ym)); ?>"><?php echo $h($nombreMes($ym)); ?></h3>
							<span class="egr-mes__tot"><span class="text-body-secondary small"><?php echo count($g['filas']); ?> egreso<?php echo count($g['filas']) === 1 ? '' : 's'; ?> ·</span> <strong><?php echo $din($g['total']); ?></strong></span>
						</div>

						<!-- Escritorio -->
						<div class="table-responsive d-none d-md-block">
							<table class="table table-hover table-sm align-middle mb-0 egr-tabla">
								<colgroup>
									<col style="width:7rem"><col><col style="width:9rem"><col style="width:9.5rem">
									<col style="width:10.5rem"><col style="width:11rem"><col style="width:7rem"><col style="width:7.5rem">
								</colgroup>
								<thead>
									<tr>
										<th scope="col">Fecha</th>
										<th scope="col">Empresa / detalle</th>
										<th scope="col">Sede</th>
										<th scope="col">Concepto</th>
										<th scope="col">Forma de pago</th>
										<th scope="col">Bloque del reporte</th>
										<th scope="col" class="text-end">Monto</th>
										<th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($g['filas'] as $f): ?>
										<tr>
											<td class="text-nowrap"><?php echo date('d/m/Y', strtotime($f['egreso_fechapago'])); ?></td>
											<td>
												<div class="fw-semibold"><?php echo $f['egreso_empresa']; ?></div>
												<?php if ($f['egreso_descripcion'] !== ''): ?><div class="small text-body-secondary egr-detalle"><?php echo $f['egreso_descripcion']; ?></div><?php endif; ?>
											</td>
											<td><?php echo $h($f['sede_nombre']); ?></td>
											<td><?php echo $h($conceptos[$f['egreso_concepto']] ?? '—'); ?></td>
											<td><?php echo $h($formas[$f['egreso_formaentrega']] ?? '—'); ?></td>
											<td><?php echo $bloqueTexto($f); ?></td>
											<td class="text-end fw-semibold text-nowrap"><?php echo $din($f['egreso_monto']); ?></td>
											<td class="text-end text-nowrap"><div class="egr-acciones"><?php echo $acciones($f); ?></div></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
								<tfoot>
									<tr><th colspan="6" class="text-end">Total <?php echo $h(mb_strtolower($nombreMes($ym))); ?></th><th class="text-end text-nowrap"><?php echo $din($g['total']); ?></th><th></th></tr>
								</tfoot>
							</table>
						</div>

						<!-- Celular -->
						<ul class="list-group list-group-flush d-md-none egr-fichas">
							<?php foreach ($g['filas'] as $f): ?>
								<li class="list-group-item egr-ficha">
									<div class="egr-ficha__fila">
										<div class="min-w-0">
											<div class="fw-semibold egr-ficha__empresa"><?php echo $f['egreso_empresa']; ?></div>
											<div class="small text-body-secondary"><?php echo date('d/m/Y', strtotime($f['egreso_fechapago'])); ?> · <?php echo $h($f['sede_nombre']); ?></div>
										</div>
										<strong class="egr-ficha__monto"><?php echo $din($f['egreso_monto']); ?></strong>
									</div>
									<?php if ($f['egreso_descripcion'] !== ''): ?><div class="small mt-1 egr-detalle"><?php echo $f['egreso_descripcion']; ?></div><?php endif; ?>
									<div class="egr-ficha__chips">
										<span class="badge egr-chip"><?php echo $h($conceptos[$f['egreso_concepto']] ?? '—'); ?></span>
										<span class="badge egr-chip"><?php echo $h($formas[$f['egreso_formaentrega']] ?? '—'); ?></span>
										<?php echo $bloqueTexto($f); ?>
									</div>
									<?php $acc = $acciones($f, ' egr-accion'); if ($acc !== ''): ?><div class="egr-ficha__acciones"><?php echo $acc; ?></div><?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>

				<?php if ($puedeCrear): ?>
					<button type="button" class="btn btn-primary egr-fab d-md-none" data-egr-nuevo aria-label="Nuevo egreso"><i class="fas fa-plus" aria-hidden="true"></i></button>
				<?php endif; ?>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<?php if ($puedeCrear || $puedeEditar): ?>
	<!-- =================== Registrar / editar =================== -->
	<div class="modal fade" id="modalEgreso" tabindex="-1" aria-labelledby="modalEgresoTitulo" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
			<form class="modal-content FormularioAjax" id="formEgreso" action="<?php echo APP_URL; ?>app/ajax/balanceAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="modulo_egreso" id="egr_modulo" value="registrar">
				<input type="hidden" name="egreso_id" id="egr_id" value="0">
				<div class="modal-header">
					<h5 class="modal-title" id="modalEgresoTitulo"><i class="fas fa-file-invoice-dollar me-2" aria-hidden="true"></i><span>Nuevo egreso</span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="row g-3">
						<div class="col-6 col-md-4">
							<label for="egreso_fechapago" class="form-label">Fecha de pago <span class="text-danger">*</span></label>
							<input type="date" class="form-control" name="egreso_fechapago" id="egreso_fechapago" required>
							<div class="invalid-feedback">Elija la fecha.</div>
						</div>
						<div class="col-6 col-md-4">
							<label for="egreso_monto" class="form-label">Monto USD <span class="text-danger">*</span></label>
							<input type="text" class="form-control egr-monto" id="egreso_monto" name="egreso_monto" inputmode="decimal" required>
							<div class="invalid-feedback">Escriba el monto.</div>
						</div>
						<div class="col-12 col-md-4">
							<label for="egreso_sedeid" class="form-label">Sede</label>
							<select class="form-select" id="egreso_sedeid" name="egreso_sedeid">
								<?php echo $insEgreso->listarOptionSede(0); ?>
							</select>
						</div>
						<div class="col-12">
							<label for="egreso_empresa" class="form-label">Empresa o beneficiario <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="egreso_empresa" name="egreso_empresa" maxlength="150" required placeholder="A quién se pagó">
							<div class="invalid-feedback">Escriba a quién se pagó.</div>
						</div>
						<div class="col-6">
							<label for="egreso_concepto" class="form-label">Concepto</label>
							<select class="form-select" id="egreso_concepto" name="egreso_concepto">
								<?php echo $insEgreso->listarTipoEgreso(''); ?>
							</select>
						</div>
						<div class="col-6">
							<label for="egreso_formaentrega" class="form-label">Forma de pago</label>
							<select class="form-select" id="egreso_formaentrega" name="egreso_formaentrega">
								<?php echo $insEgreso->listarFormaEntregaIngreso(''); ?>
							</select>
						</div>
						<?php /* Bloque de Insights › Ingresos del mes (migración 094). */ ?>
						<div class="col-12 col-md-6">
							<label for="egreso_rubroid" class="form-label">Bloque del reporte</label>
							<select class="form-select" id="egreso_rubroid" name="egreso_rubroid" aria-describedby="egresoBloqueAyuda">
								<?php echo $insEgreso->listarBloqueEgreso(''); ?>
							</select>
							<div id="egresoBloqueAyuda" class="form-text">Dónde se resta en «Ingresos del mes».</div>
						</div>
						<div class="col-12 col-md-6" id="egresoLugarCampo" hidden>
							<label for="egreso_lugarid" class="form-label">Lugar de entrenamiento</label>
							<select class="form-select" id="egreso_lugarid" name="egreso_lugarid">
								<?php echo $insEgreso->listarLugarEgreso(0); ?>
							</select>
						</div>
						<div class="col-12">
							<label for="egreso_descripcion" class="form-label">Detalle</label>
							<textarea class="form-control" id="egreso_descripcion" name="egreso_descripcion" rows="2" maxlength="300" placeholder="Por ejemplo: arriendo de la cancha de octubre"></textarea>
						</div>
						<div class="col-12">
							<label for="egreso_imagenpago" class="form-label">Comprobante <span class="text-body-secondary small">(foto JPG o PNG, hasta 4 MB)</span></label>
							<div class="egr-foto">
								<img src="" alt="" class="egr-foto__img" id="egrFotoVista" hidden>
								<div class="egr-foto__vacio" id="egrFotoVacio" aria-hidden="true"><i class="fas fa-camera"></i></div>
								<div class="flex-grow-1 min-w-0">
									<input type="file" class="form-control" name="egreso_imagenpago" id="egreso_imagenpago" accept="image/jpeg,image/png">
									<div class="form-text" id="egrFotoAyuda">Opcional.</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer egr-modal__pie">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1" aria-hidden="true"></i><span id="egrGuardarTxt">Registrar egreso</span></button>
				</div>
			</form>
		</div>
	</div>
	<?php endif; ?>

	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/inputmask/jquery.inputmask.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>"></script>

	<script>
	(function () {
		/* El mes se aplica al elegirlo: es el filtro de todos los días. */
		var mes = document.getElementById('f_mes');
		if (mes) { mes.addEventListener('change', function () { mes.form.submit(); }); }

		var modalEl = document.getElementById('modalEgreso');
		if (!modalEl) { return; }
		var modal   = bootstrap.Modal.getOrCreateInstance(modalEl);
		var form    = document.getElementById('formEgreso');
		var $ = function (id) { return document.getElementById(id); };
		var monto   = $('egreso_monto');
		var bloque  = $('egreso_rubroid'), sede = $('egreso_sedeid'), lugar = $('egreso_lugarid'), campo = $('egresoLugarCampo');
		var foto    = $('egreso_imagenpago'), vista = $('egrFotoVista'), vacio = $('egrFotoVacio'), ayudaFoto = $('egrFotoAyuda');
		var HOY     = <?php echo json_encode(date('Y-m-d')); ?>;
		var SEDE_F  = <?php echo json_encode($sedeF > 0 ? (string)$sedeF : ''); ?>;

		/* Al entrar al monto se selecciona entero: lo que se escribe lo
		   reemplaza. Con el cursor al final de «$ 0.00», «12.50» quedaba
		   en 0.50 (los enteros se perdían). */
		Inputmask({ alias: 'currency', prefix: '$ ', groupSeparator: ',', autoGroup: true, digits: 2, digitsOptional: false, placeholder: '0',
		            positionCaretOnClick: 'select' }).mask(monto);
		function ponerMonto(v) { if (monto.inputmask) { monto.inputmask.setValue(v); } else { monto.value = v; } }

		/* Lugar de entrenamiento: sólo con «Pensiones», y sólo los de la sede
		   elegida. Si el lugar elegido no es de la nueva sede, «Toda la sede». */
		function ajustarLugar() {
			campo.hidden = bloque.value !== 'RPE';
			[].forEach.call(lugar.options, function (o) {
				var ajeno = o.value !== '' && o.getAttribute('data-sede') !== sede.value;
				o.hidden = ajeno; o.disabled = ajeno;
			});
			var elegido = lugar.options[lugar.selectedIndex];
			if (campo.hidden || (elegido && elegido.disabled)) { lugar.value = ''; }
		}
		bloque.addEventListener('change', ajustarLugar);
		sede.addEventListener('change', ajustarLugar);

		function mostrarFoto(url) {
			vista.hidden = !url; vacio.hidden = !!url;
			if (url) { vista.src = url; } else { vista.removeAttribute('src'); }
		}
		foto.addEventListener('change', function () {
			var f = foto.files && foto.files[0];
			if (!f) { return; }
			if (f.size > 4000 * 1024) {
				foto.value = ''; ayudaFoto.innerHTML = '<span class="text-danger">La imagen supera los 4 MB: elija otra.</span>'; return;
			}
			mostrarFoto(URL.createObjectURL(f));
			ayudaFoto.textContent = f.name;
		});

		function elegir(select, valor) {
			if (valor !== '' && [].some.call(select.options, function (o) { return o.value === valor; })) { select.value = valor; }
			else if (valor === '') { select.selectedIndex = 0; }
		}

		/* Abrir: vacío para registrar, o con los datos de la fila para editar. */
		function abrir(d) {
			form.reset();
			form.classList.remove('was-validated');
			var editar = !!(d && d.id);
			$('egr_modulo').value = editar ? 'actualizar' : 'registrar';
			$('egr_id').value = editar ? d.id : 0;
			modalEl.querySelector('.modal-title span').textContent = editar ? 'Editar egreso' : 'Nuevo egreso';
			$('egrGuardarTxt').textContent = editar ? 'Guardar cambios' : 'Registrar egreso';
			$('egreso_fechapago').value = editar ? d.fecha : HOY;
			ponerMonto(editar ? d.monto : '');
			elegir(sede, editar ? d.sede : SEDE_F);
			$('egreso_empresa').value = editar ? d.empresa : '';
			if (editar) { elegir($('egreso_concepto'), d.concepto); elegir($('egreso_formaentrega'), d.forma); }
			elegir(bloque, editar ? d.bloque : '');
			ajustarLugar();
			if (editar && d.lugar) { elegir(lugar, d.lugar); }
			$('egreso_descripcion').value = editar ? d.detalle : '';
			mostrarFoto(editar ? d.foto : '');
			ayudaFoto.textContent = editar && d.foto ? 'Elija otra foto sólo si quiere reemplazar la actual.' : 'Opcional.';
			modal.show();
		}
		modalEl.addEventListener('shown.bs.modal', function () {
			/* En el teléfono no se enfoca: el teclado taparía el formulario. */
			if (window.matchMedia('(min-width: 768px)').matches) { $('egreso_empresa').focus(); }
		});

		document.querySelectorAll('[data-egr-nuevo]').forEach(function (b) { b.addEventListener('click', function () { abrir(null); }); });
		document.querySelectorAll('.egr-editar').forEach(function (b) {
			b.addEventListener('click', function () { abrir(JSON.parse(b.getAttribute('data-egreso'))); });
		});

		/* Validación antes de la confirmación de ajax.js (que escucha el
		   mismo submit): si falta algo, se marca y no se envía. */
		form.addEventListener('submit', function (e) {
			var sinMonto = !(parseFloat(String(monto.value).replace(/[^0-9.]/g, '')) > 0);
			monto.setCustomValidity(sinMonto ? 'Escriba el monto' : '');
			if (!form.checkValidity()) {
				e.preventDefault(); e.stopImmediatePropagation();
				form.classList.add('was-validated');
				var malo = form.querySelector(':invalid'); if (malo) { malo.focus(); }
			}
		}, true);
		monto.addEventListener('input', function () { monto.setCustomValidity(''); });

		/* Enlace antiguo /egresoList/{id}/: abre ese egreso. */
		var abrirId = <?php echo (int)$abrirId; ?>;
		if (abrirId) {
			var b = document.querySelector('.egr-editar[data-egreso*=\'"id":' + abrirId + ',\']');
			if (b) { abrir(JSON.parse(b.getAttribute('data-egreso'))); }
		}
	})();
	</script>

	<style>
		.egr-cabecera { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
		.min-w-0 { min-width: 0; }

		.egr-filtros__cab { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
		.egr-filtros .form-label { font-size: .8rem; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: .25rem; }
		.egr-filtros__plegar { white-space: nowrap; flex: 0 0 auto; }
		.egr-filtros__plegar[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }
		/* Escritorio: el mes y los demás criterios en la misma fila (el
		   pliegue es sólo para el celular). */
		@media (min-width: 768px) { .egr-filtros__extra { display: contents !important; } }


		.egr-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
		.egr-kpi { display: flex; flex-direction: column; gap: .1rem; padding: .8rem 1rem; border-radius: var(--ds-radius-sm, 10px);
		           background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); color: inherit; text-decoration: none; min-width: 0; }
		.egr-kpi__et { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); font-weight: 600; }
		.egr-kpi__val { font-size: 1.45rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
		.egr-kpi__val--texto { font-size: 1.1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.egr-kpi__nota { font-size: .8rem; color: var(--bs-secondary-color); }
		.egr-kpi--total { border-top: 3px solid var(--bs-danger); }
		.egr-kpi--aviso { border-top: 3px solid var(--bs-warning); }
		.egr-kpi--aviso:hover { background: var(--bs-tertiary-bg); }
		.egr-kpi--ok { border-top-color: var(--bs-success); pointer-events: none; }

		.egr-mes__cab { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
		.egr-mes__cab::after { display: none; }
		.egr-mes__tot { font-variant-numeric: tabular-nums; white-space: nowrap; }
		.egr-tabla { font-size: .9rem; table-layout: fixed; min-width: 900px; }
		.egr-tabla thead th { font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); }
		.egr-tabla td, .egr-tabla th { padding: .55rem .75rem; }
		.egr-tabla tfoot th { background: var(--bs-tertiary-bg); }
		.egr-detalle { overflow-wrap: anywhere; }
		.egr-acciones { display: inline-flex; gap: .35rem; }
		.egr-acciones .btn { width: 2.1rem; height: 2.1rem; padding: 0; display: inline-grid; place-items: center; }
		.egr-accion__txt { display: none; }
		.egr-chip { background: var(--bs-secondary-bg); color: var(--bs-emphasis-color); font-weight: 500; white-space: normal; text-align: left; }
		.egr-chip--bloque { background: var(--bs-info-bg-subtle); color: var(--bs-info-text-emphasis); }

		.egr-ficha { padding: .85rem 1rem; }
		.egr-ficha__fila { display: flex; justify-content: space-between; gap: .75rem; align-items: flex-start; }
		.egr-ficha__empresa { overflow-wrap: anywhere; }
		.egr-ficha__monto { font-size: 1.1rem; white-space: nowrap; font-variant-numeric: tabular-nums; }
		.egr-ficha__chips { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .5rem; }
		.egr-ficha__acciones { display: flex; gap: .5rem; margin-top: .65rem; }
		.egr-ficha__acciones form { flex: 1 1 0; display: flex; }
		.egr-ficha__acciones .btn { flex: 1 1 0; min-height: 2.5rem; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; }
		.egr-ficha__acciones .egr-accion__txt { display: inline; }

		.egr-foto { display: flex; gap: .75rem; align-items: center; }
		.egr-foto__img, .egr-foto__vacio { width: 64px; height: 64px; flex: 0 0 auto; border-radius: 8px; object-fit: cover; border: 1px solid var(--bs-border-color); }
		.egr-foto__vacio { display: grid; place-items: center; background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); font-size: 1.3rem; }
		.egr-foto__vacio[hidden], .egr-foto__img[hidden] { display: none; }

		.egr-fab { position: fixed; right: 1rem; bottom: 1.25rem; z-index: 1030; width: 3.5rem; height: 3.5rem; border-radius: 50%;
		           font-size: 1.3rem; box-shadow: 0 .4rem 1rem rgba(0,0,0,.25); display: grid; place-items: center; padding: 0; }

		@media (max-width: 991.98px) { .egr-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
		@media (max-width: 767.98px) {
			.egr-nuevo { display: none; }
			.egr-kpi { padding: .65rem .8rem; }
			.egr-kpi__val { font-size: 1.2rem; }
			.egr-kpi__val--texto { font-size: 1rem; }
			.app-content { padding-bottom: 5rem; }
			.egr-modal__pie { flex-direction: column-reverse; align-items: stretch; }
			.egr-modal__pie .btn { width: 100%; margin: 0; min-height: 2.9rem; }
		}
	</style>
  </body>
</html>
