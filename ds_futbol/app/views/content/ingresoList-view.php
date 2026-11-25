<?php
/*
| Fútbol › Balance › Ingresos (rediseño 2026-11-25, como egresoList)
|
| · Criterios de búsqueda: mes, sede, concepto, forma de recepción y texto
|   (empresa o descripción). Por omisión, todos los meses: los ingresos de
|   la escuela (auspicios, donaciones) son pocos y esporádicos.
| · Resumen: total recibido, promedio, concepto y forma de recepción
|   principales.
| · Lista agrupada por mes: tabla en el escritorio y fichas en el celular.
| · Registrar y editar en un modal (a pantalla completa en el celular). Se
|   guarda con balanceAjax.php como antes; al guardar vuelve al mes del
|   ingreso. /ingresoList/{id}/ (enlace antiguo) abre el modal de ese ingreso.
*/
use app\controllers\balanceController;
$insIngreso = new balanceController();

$h   = static fn ($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$din = static fn ($x) => '$' . number_format((float)$x, 2);
$MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$nombreMes = static function (string $ym) use ($MESES): string {
	[$a, $m] = array_map('intval', explode('-', $ym));
	return ucfirst($MESES[$m] ?? '') . ' ' . $a;
};

$puedeCrear    = puede_crear('ingresoList');
$puedeEditar   = puede_editar('ingresoList');
$puedeEliminar = puede_eliminar('ingresoList');

/* Enlace antiguo /ingresoList/{id}/: abre ese ingreso, en su mes. */
$abrirId = (int)(($url[1] ?? '') !== '' ? $url[1] : 0);

/* Criterios */
$mesGet = (string)($_GET['mes'] ?? '');
if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesGet)) {
	$mes = $mesGet;
} elseif ($mesGet === '' && $abrirId > 0) {
	$mes = $insIngreso->mesDeIngreso($abrirId);
} else {
	$mes = '';                                   /* «todos» o sin mes: todos los meses */
}
$sedeF     = (int)($_GET['sede'] ?? 0);
$conceptoF = (string)($_GET['concepto'] ?? '');
$formaF    = (string)($_GET['forma'] ?? '');
$q         = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));

$sedes     = $insIngreso->sedesIngreso();
$conceptos = $insIngreso->catalogoEgreso('balance_ingreso');
$formas    = $insIngreso->catalogoEgreso('forma_entregaingreso');
$meses     = $insIngreso->ingresosMeses();
if ($mes !== '' && !isset($meses[$mes])) { $meses[$mes] = 0; krsort($meses); }

$filas = $insIngreso->ingresosFiltrados(['mes' => $mes, 'sede' => $sedeF, 'concepto' => $conceptoF, 'forma' => $formaF, 'q' => $q]);
$filtrosExtra = ($sedeF > 0) + ($conceptoF !== '') + ($formaF !== '') + ($q !== '');

/* Resumen */
$total = 0.0; $porConcepto = []; $porForma = []; $grupos = [];
foreach ($filas as $f) {
	$monto = (float)$f['ingreso_monto'];
	$total += $monto;
	$c = $conceptos[$f['ingreso_concepto']] ?? 'Sin concepto';
	$porConcepto[$c] = ($porConcepto[$c] ?? 0) + $monto;
	$fo = $formas[$f['ingreso_formaentrega']] ?? 'Sin forma';
	$porForma[$fo] = ($porForma[$fo] ?? 0) + $monto;
	$ym = substr($f['ingreso_fecharecepcion'], 0, 7);
	$grupos[$ym]['filas'][] = $f;
	$grupos[$ym]['total'] = ($grupos[$ym]['total'] ?? 0) + $monto;
}
arsort($porConcepto); arsort($porForma);
$conceptoTop = array_key_first($porConcepto);
$formaTop    = array_key_first($porForma);
$pct = static fn ($v) => $total > 0 ? ' · ' . round($v * 100 / $total) . ' %' : '';

/* Lo que el modal necesita de cada ingreso (sin rutas del servidor). */
$datosIngreso = static function (array $f): array {
	return [
		'id'       => (int)$f['ingreso_id'],
		'sede'     => (string)$f['ingreso_sedeid'],
		'fecha'    => (string)$f['ingreso_fecharecepcion'],
		'empresa'  => html_entity_decode((string)$f['ingreso_empresa'], ENT_QUOTES, 'UTF-8'),
		'monto'    => number_format((float)$f['ingreso_monto'], 2, '.', ''),
		'forma'    => (string)$f['ingreso_formaentrega'],
		'concepto' => (string)$f['ingreso_concepto'],
		'detalle'  => html_entity_decode((string)$f['ingreso_descripcion'], ENT_QUOTES, 'UTF-8'),
		'foto'     => (string)$f['ingreso_imagenpago'] !== '' ? media_url('fingreso', $f['ingreso_imagenpago']) : '',
	];
};
$acciones = static function (array $f, string $clase = '') use ($puedeEditar, $puedeEliminar, $datosIngreso, $h): string {
	$html = '';
	if ((string)$f['ingreso_imagenpago'] !== '') {
		$html .= '<a class="btn btn-outline-secondary btn-sm' . $clase . '" href="' . $h(media_url('fingreso', $f['ingreso_imagenpago'])) . '" target="_blank" rel="noopener" title="Ver comprobante" aria-label="Ver comprobante de ' . $h($f['ingreso_empresa']) . '"><i class="fas fa-image" aria-hidden="true"></i><span class="ing-accion__txt">Comprobante</span></a>';
	}
	if ($puedeEditar) {
		$html .= '<button type="button" class="btn btn-outline-primary btn-sm ing-editar' . $clase . '" data-ingreso="' . $h(json_encode($datosIngreso($f), JSON_UNESCAPED_UNICODE)) . '" title="Editar" aria-label="Editar ingreso de ' . $h($f['ingreso_empresa']) . '"><i class="fas fa-pen" aria-hidden="true"></i><span class="ing-accion__txt">Editar</span></button>';
	}
	if ($puedeEliminar) {
		$html .= '<form class="FormularioAjax d-inline" action="' . APP_URL . 'app/ajax/balanceAjax.php" method="POST" autocomplete="off">'
		       . '<input type="hidden" name="modulo_ingreso" value="eliminar"><input type="hidden" name="ingreso_id" value="' . (int)$f['ingreso_id'] . '">'
		       . '<button type="submit" class="btn btn-danger btn-sm' . $clase . '" title="Eliminar" aria-label="Eliminar ingreso de ' . $h($f['ingreso_empresa']) . '"><i class="fas fa-trash" aria-hidden="true"></i><span class="ing-accion__txt">Eliminar</span></button></form>';
	}
	return $html;
};

/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
$tituloVista = 'Ingresos';
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
				<div class="ing-cabecera">
					<div>
						<h4 class="m-0">Ingresos</h4>
						<div class="small text-body-secondary">Dinero que recibe la escuela fuera de los pagos de alumnos: auspicios, donaciones y otros</div>
					</div>
					<?php if ($puedeCrear): ?>
						<button type="button" class="btn btn-primary ing-nuevo" data-ing-nuevo>
							<i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo ingreso
						</button>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<section class="app-content">
			<div class="container-fluid">

				<!-- =================== Criterios de búsqueda =================== -->
				<form method="GET" action="<?php echo APP_URL; ?>ingresoList/" autocomplete="off" class="card mb-3 ing-filtros" id="ingFiltros">
					<div class="card-header ing-filtros__cab">
						<h3 class="card-title mb-0"><i class="fas fa-filter me-2" aria-hidden="true"></i>Criterios de búsqueda</h3>
						<button type="button" class="btn btn-sm btn-outline-secondary d-md-none ing-filtros__plegar" data-bs-toggle="collapse" data-bs-target="#ingFiltrosCuerpo"
						        aria-expanded="<?php echo $filtrosExtra ? 'true' : 'false'; ?>" aria-controls="ingFiltrosCuerpo">
							<i class="fas fa-sliders me-1" aria-hidden="true"></i><span><?php echo $filtrosExtra ? 'Filtros (' . $filtrosExtra . ')' : 'Filtros'; ?></span>
							<i class="fas fa-chevron-down ms-1" aria-hidden="true"></i>
						</button>
					</div>
					<div class="card-body pb-2">
						<div class="row g-2 g-md-3">
							<div class="col-12 col-md-4 col-xl-2">
								<label for="f_mes" class="form-label">Mes</label>
								<select class="form-select" id="f_mes" name="mes">
									<option value="todos" <?php echo $mes === '' ? 'selected' : ''; ?>>Todos los meses</option>
									<?php foreach ($meses as $ym => $n): ?>
										<option value="<?php echo $h($ym); ?>" <?php echo $ym === $mes ? 'selected' : ''; ?>>
											<?php echo $h($nombreMes($ym)); ?><?php echo $n ? ' (' . $n . ')' : ''; ?>
										</option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="collapse ing-filtros__extra<?php echo $filtrosExtra ? ' show' : ''; ?>" id="ingFiltrosCuerpo">
							<div class="row g-2 g-md-3 mt-0 ing-filtros__extra">
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
									<label for="f_forma" class="form-label">Forma de recepción</label>
									<select class="form-select" id="f_forma" name="forma">
										<option value="">Todas</option>
										<?php foreach ($formas as $v => $nombre): ?>
											<option value="<?php echo $h($v); ?>" <?php echo $v === $formaF ? 'selected' : ''; ?>><?php echo $h($nombre); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="col-12 col-md-8 col-xl">
									<label for="f_q" class="form-label">Empresa o descripción</label>
									<input type="search" class="form-control" id="f_q" name="q" maxlength="80" placeholder="Nombre de la empresa, descripción…" value="<?php echo $h($q); ?>">
								</div>
								<div class="col-12 col-md-4 col-xl-auto d-flex align-items-end gap-2">
									<div class="flex-grow-1"><?php echo ds_boton_buscar(); ?></div>
									<?php if ($filtrosExtra || $mes !== ''): ?>
										<a href="<?php echo APP_URL; ?>ingresoList/" class="btn btn-outline-secondary" title="Quitar los filtros">
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
				<div class="ing-kpis mb-3">
					<div class="ing-kpi ing-kpi--total">
						<span class="ing-kpi__et">Total recibido</span>
						<strong class="ing-kpi__val"><?php echo $din($total); ?></strong>
						<span class="ing-kpi__nota"><?php echo count($filas); ?> ingreso<?php echo count($filas) === 1 ? '' : 's'; ?><?php echo $mes === '' ? ' · todos los meses' : ' · ' . $h(mb_strtolower($nombreMes($mes))); ?></span>
					</div>
					<div class="ing-kpi">
						<span class="ing-kpi__et">Promedio por ingreso</span>
						<strong class="ing-kpi__val"><?php echo $din(count($filas) ? $total / count($filas) : 0); ?></strong>
						<span class="ing-kpi__nota"><?php echo count($grupos) > 1 ? count($grupos) . ' meses' : '&nbsp;'; ?></span>
					</div>
					<div class="ing-kpi">
						<span class="ing-kpi__et">Concepto principal</span>
						<strong class="ing-kpi__val ing-kpi__val--texto"><?php echo $conceptoTop !== null ? $h($conceptoTop) : '—'; ?></strong>
						<span class="ing-kpi__nota"><?php echo $conceptoTop !== null ? $din($porConcepto[$conceptoTop]) . $pct($porConcepto[$conceptoTop]) : '&nbsp;'; ?></span>
					</div>
					<div class="ing-kpi">
						<span class="ing-kpi__et">Forma de recepción principal</span>
						<strong class="ing-kpi__val ing-kpi__val--texto"><?php echo $formaTop !== null ? $h($formaTop) : '—'; ?></strong>
						<span class="ing-kpi__nota"><?php echo $formaTop !== null ? $din($porForma[$formaTop]) . $pct($porForma[$formaTop]) : '&nbsp;'; ?></span>
					</div>
				</div>

				<!-- =================== Ingresos por mes =================== -->
				<?php if (!$filas): ?>
					<div class="card">
						<div class="card-body text-center py-5 ing-vacio">
							<i class="fas fa-hand-holding-dollar fa-2x mb-3 text-body-tertiary" aria-hidden="true"></i>
							<p class="mb-1 fw-semibold">No hay ingresos<?php echo $mes !== '' ? ' en ' . $h(mb_strtolower($nombreMes($mes))) : ' registrados'; ?><?php echo $filtrosExtra ? ' con estos criterios' : ''; ?>.</p>
							<p class="small text-body-secondary mb-3"><?php echo $filtrosExtra || $mes !== '' ? 'Cambie los criterios de búsqueda' : 'Aquí aparecerán los auspicios, donaciones y otros ingresos'; ?><?php echo $puedeCrear ? ', o registre uno nuevo' : ''; ?>.</p>
							<?php if ($puedeCrear): ?>
								<button type="button" class="btn btn-primary" data-ing-nuevo><i class="fas fa-plus me-1" aria-hidden="true"></i>Nuevo ingreso</button>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php foreach ($grupos as $ym => $g): ?>
					<section class="card mb-3 ing-mes" aria-labelledby="ingMes<?php echo $h(str_replace('-', '', $ym)); ?>">
						<div class="card-header ing-mes__cab">
							<h3 class="card-title mb-0" id="ingMes<?php echo $h(str_replace('-', '', $ym)); ?>"><?php echo $h($nombreMes($ym)); ?></h3>
							<span class="ing-mes__tot"><span class="text-body-secondary small"><?php echo count($g['filas']); ?> ingreso<?php echo count($g['filas']) === 1 ? '' : 's'; ?> ·</span> <strong><?php echo $din($g['total']); ?></strong></span>
						</div>

						<!-- Escritorio -->
						<div class="table-responsive d-none d-md-block">
							<table class="table table-hover table-sm align-middle mb-0 ing-tabla">
								<colgroup>
									<col style="width:7rem"><col><col style="width:9rem"><col style="width:10rem">
									<col style="width:12rem"><col style="width:7.5rem"><col style="width:7.5rem">
								</colgroup>
								<thead>
									<tr>
										<th scope="col">Fecha</th>
										<th scope="col">Empresa / descripción</th>
										<th scope="col">Sede</th>
										<th scope="col">Concepto</th>
										<th scope="col">Forma de recepción</th>
										<th scope="col" class="text-end">Monto</th>
										<th scope="col" class="text-end"><span class="visually-hidden">Acciones</span></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($g['filas'] as $f): ?>
										<tr>
											<td class="text-nowrap"><?php echo date('d/m/Y', strtotime($f['ingreso_fecharecepcion'])); ?></td>
											<td>
												<div class="fw-semibold ing-texto"><?php echo $f['ingreso_empresa']; ?></div>
												<?php if ($f['ingreso_descripcion'] !== ''): ?><div class="small text-body-secondary ing-texto"><?php echo $f['ingreso_descripcion']; ?></div><?php endif; ?>
											</td>
											<td><?php echo $h($f['sede_nombre']); ?></td>
											<td><?php echo $h($conceptos[$f['ingreso_concepto']] ?? '—'); ?></td>
											<td><?php echo $h($formas[$f['ingreso_formaentrega']] ?? '—'); ?></td>
											<td class="text-end fw-semibold text-nowrap"><?php echo $din($f['ingreso_monto']); ?></td>
											<td class="text-end text-nowrap"><div class="ing-acciones"><?php echo $acciones($f); ?></div></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
								<tfoot>
									<tr><th colspan="5" class="text-end">Total <?php echo $h(mb_strtolower($nombreMes($ym))); ?></th><th class="text-end text-nowrap"><?php echo $din($g['total']); ?></th><th></th></tr>
								</tfoot>
							</table>
						</div>

						<!-- Celular -->
						<ul class="list-group list-group-flush d-md-none ing-fichas">
							<?php foreach ($g['filas'] as $f): ?>
								<li class="list-group-item ing-ficha">
									<div class="ing-ficha__fila">
										<div class="min-w-0">
											<div class="fw-semibold ing-texto"><?php echo $f['ingreso_empresa']; ?></div>
											<div class="small text-body-secondary"><?php echo date('d/m/Y', strtotime($f['ingreso_fecharecepcion'])); ?> · <?php echo $h($f['sede_nombre']); ?></div>
										</div>
										<strong class="ing-ficha__monto"><?php echo $din($f['ingreso_monto']); ?></strong>
									</div>
									<?php if ($f['ingreso_descripcion'] !== ''): ?><div class="small mt-1 ing-texto"><?php echo $f['ingreso_descripcion']; ?></div><?php endif; ?>
									<div class="ing-ficha__chips">
										<span class="badge ing-chip"><?php echo $h($conceptos[$f['ingreso_concepto']] ?? '—'); ?></span>
										<span class="badge ing-chip"><?php echo $h($formas[$f['ingreso_formaentrega']] ?? '—'); ?></span>
									</div>
									<?php $acc = $acciones($f, ' ing-accion'); if ($acc !== ''): ?><div class="ing-ficha__acciones"><?php echo $acc; ?></div><?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>

				<?php if ($puedeCrear): ?>
					<button type="button" class="btn btn-primary ing-fab d-md-none" data-ing-nuevo aria-label="Nuevo ingreso"><i class="fas fa-plus" aria-hidden="true"></i></button>
				<?php endif; ?>
			</div>
		</section>
      </div>

      <?php require_once "app/views/inc/footer.php"; ?>
    </div>

	<?php if ($puedeCrear || $puedeEditar): ?>
	<!-- =================== Registrar / editar =================== -->
	<div class="modal fade" id="modalIngreso" tabindex="-1" aria-labelledby="modalIngresoTitulo" aria-hidden="true">
		<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
			<form class="modal-content FormularioAjax" id="formIngreso" action="<?php echo APP_URL; ?>app/ajax/balanceAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="modulo_ingreso" id="ing_modulo" value="registrar">
				<input type="hidden" name="ingreso_id" id="ing_id" value="0">
				<div class="modal-header">
					<h5 class="modal-title" id="modalIngresoTitulo"><i class="fas fa-hand-holding-dollar me-2" aria-hidden="true"></i><span>Nuevo ingreso</span></h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
				</div>
				<div class="modal-body">
					<div class="row g-3">
						<div class="col-6 col-md-4">
							<label for="ingreso_fecharecepcion" class="form-label">Fecha de recepción <span class="text-danger">*</span></label>
							<input type="date" class="form-control" name="ingreso_fecharecepcion" id="ingreso_fecharecepcion" required>
							<div class="invalid-feedback">Elija la fecha.</div>
						</div>
						<div class="col-6 col-md-4">
							<label for="ingreso_monto" class="form-label">Monto USD <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="ingreso_monto" name="ingreso_monto" inputmode="decimal" required>
							<div class="invalid-feedback">Escriba el monto.</div>
						</div>
						<div class="col-12 col-md-4">
							<label for="ingreso_sedeid" class="form-label">Sede</label>
							<select class="form-select" id="ingreso_sedeid" name="ingreso_sedeid">
								<?php echo $insIngreso->listarOptionSede(0); ?>
							</select>
						</div>
						<div class="col-12">
							<label for="ingreso_empresa" class="form-label">Empresa o persona <span class="text-danger">*</span></label>
							<input type="text" class="form-control" id="ingreso_empresa" name="ingreso_empresa" maxlength="150" required placeholder="Quién entregó el dinero">
							<div class="invalid-feedback">Escriba quién entregó el dinero.</div>
						</div>
						<div class="col-6">
							<label for="ingreso_concepto" class="form-label">Concepto</label>
							<select class="form-select" id="ingreso_concepto" name="ingreso_concepto">
								<?php echo $insIngreso->listarTipoIngreso(''); ?>
							</select>
						</div>
						<div class="col-6">
							<label for="ingreso_formaentrega" class="form-label">Forma de recepción</label>
							<select class="form-select" id="ingreso_formaentrega" name="ingreso_formaentrega">
								<?php echo $insIngreso->listarFormaEntregaIngreso(''); ?>
							</select>
						</div>
						<div class="col-12">
							<label for="ingreso_descripcion" class="form-label">Descripción</label>
							<textarea class="form-control" id="ingreso_descripcion" name="ingreso_descripcion" rows="2" maxlength="300" placeholder="Por ejemplo: auspicio de camisetas del torneo"></textarea>
						</div>
						<div class="col-12">
							<label for="ingreso_imagenpago" class="form-label">Comprobante <span class="text-body-secondary small">(foto JPG o PNG, hasta 4 MB)</span></label>
							<div class="ing-foto">
								<img src="" alt="" class="ing-foto__img" id="ingFotoVista" hidden>
								<div class="ing-foto__vacio" id="ingFotoVacio" aria-hidden="true"><i class="fas fa-camera"></i></div>
								<div class="flex-grow-1 min-w-0">
									<input type="file" class="form-control" name="ingreso_imagenpago" id="ingreso_imagenpago" accept="image/jpeg,image/png">
									<div class="form-text" id="ingFotoAyuda">Opcional.</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="modal-footer ing-modal__pie">
					<button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
					<button type="submit" class="btn btn-primary"><i class="fas fa-save me-1" aria-hidden="true"></i><span id="ingGuardarTxt">Registrar ingreso</span></button>
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
		/* El mes se aplica al elegirlo. */
		var mes = document.getElementById('f_mes');
		if (mes) { mes.addEventListener('change', function () { mes.form.submit(); }); }

		var modalEl = document.getElementById('modalIngreso');
		if (!modalEl) { return; }
		var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
		var form  = document.getElementById('formIngreso');
		var $ = function (id) { return document.getElementById(id); };
		var monto = $('ingreso_monto'), sede = $('ingreso_sedeid');
		var foto  = $('ingreso_imagenpago'), vista = $('ingFotoVista'), vacio = $('ingFotoVacio'), ayudaFoto = $('ingFotoAyuda');
		var HOY    = <?php echo json_encode(date('Y-m-d')); ?>;
		var SEDE_F = <?php echo json_encode($sedeF > 0 ? (string)$sedeF : ''); ?>;

		/* Al tocar el monto se selecciona entero: lo que se escribe lo reemplaza. */
		Inputmask({ alias: 'currency', prefix: '$ ', groupSeparator: ',', autoGroup: true, digits: 2, digitsOptional: false, placeholder: '0',
		            positionCaretOnClick: 'select' }).mask(monto);
		function ponerMonto(v) { if (monto.inputmask) { monto.inputmask.setValue(v); } else { monto.value = v; } }

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
			$('ing_modulo').value = editar ? 'actualizar' : 'registrar';
			$('ing_id').value = editar ? d.id : 0;
			modalEl.querySelector('.modal-title span').textContent = editar ? 'Editar ingreso' : 'Nuevo ingreso';
			$('ingGuardarTxt').textContent = editar ? 'Guardar cambios' : 'Registrar ingreso';
			$('ingreso_fecharecepcion').value = editar ? d.fecha : HOY;
			ponerMonto(editar ? d.monto : '');
			elegir(sede, editar ? d.sede : SEDE_F);
			$('ingreso_empresa').value = editar ? d.empresa : '';
			if (editar) { elegir($('ingreso_concepto'), d.concepto); elegir($('ingreso_formaentrega'), d.forma); }
			$('ingreso_descripcion').value = editar ? d.detalle : '';
			mostrarFoto(editar ? d.foto : '');
			ayudaFoto.textContent = editar && d.foto ? 'Elija otra foto sólo si quiere reemplazar la actual.' : 'Opcional.';
			modal.show();
		}
		modalEl.addEventListener('shown.bs.modal', function () {
			/* En el teléfono no se enfoca: el teclado taparía el formulario. */
			if (window.matchMedia('(min-width: 768px)').matches) { $('ingreso_empresa').focus(); }
		});

		document.querySelectorAll('[data-ing-nuevo]').forEach(function (b) { b.addEventListener('click', function () { abrir(null); }); });
		document.querySelectorAll('.ing-editar').forEach(function (b) {
			b.addEventListener('click', function () { abrir(JSON.parse(b.getAttribute('data-ingreso'))); });
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

		/* Enlace antiguo /ingresoList/{id}/: abre ese ingreso. */
		var abrirId = <?php echo (int)$abrirId; ?>;
		if (abrirId) {
			var b = document.querySelector('.ing-editar[data-ingreso*=\'"id":' + abrirId + ',\']');
			if (b) { abrir(JSON.parse(b.getAttribute('data-ingreso'))); }
		}
	})();
	</script>

	<style>
		.ing-cabecera { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; justify-content: space-between; }
		.min-w-0 { min-width: 0; }

		.ing-filtros__cab { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
		.ing-filtros .form-label { font-size: .8rem; font-weight: 600; color: var(--bs-secondary-color); margin-bottom: .25rem; }
		.ing-filtros__plegar { white-space: nowrap; flex: 0 0 auto; }
		.ing-filtros__plegar[aria-expanded="true"] .fa-chevron-down { transform: rotate(180deg); }
		/* Escritorio: el mes y los demás criterios en la misma fila (el
		   pliegue es sólo para el celular). */
		@media (min-width: 768px) { .ing-filtros__extra { display: contents !important; } }

		.ing-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; }
		.ing-kpi { display: flex; flex-direction: column; gap: .1rem; padding: .8rem 1rem; border-radius: var(--ds-radius-sm, 10px);
		           background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); min-width: 0; }
		.ing-kpi__et { font-size: .75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); font-weight: 600; }
		.ing-kpi__val { font-size: 1.45rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
		.ing-kpi__val--texto { font-size: 1.1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
		.ing-kpi__nota { font-size: .8rem; color: var(--bs-secondary-color); }
		.ing-kpi--total { border-top: 3px solid var(--bs-success); }

		.ing-mes__cab { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
		.ing-mes__cab::after { display: none; }
		.ing-mes__tot { font-variant-numeric: tabular-nums; white-space: nowrap; }
		.ing-tabla { font-size: .9rem; table-layout: fixed; min-width: 860px; }
		.ing-tabla thead th { font-size: .75rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); background: var(--bs-tertiary-bg); }
		.ing-tabla td, .ing-tabla th { padding: .55rem .75rem; }
		.ing-tabla tfoot th { background: var(--bs-tertiary-bg); }
		.ing-texto { overflow-wrap: anywhere; }
		.ing-acciones { display: inline-flex; gap: .35rem; }
		.ing-acciones .btn { width: 2.1rem; height: 2.1rem; padding: 0; display: inline-grid; place-items: center; }
		.ing-accion__txt { display: none; }
		.ing-chip { background: var(--bs-secondary-bg); color: var(--bs-emphasis-color); font-weight: 500; white-space: normal; text-align: left; }

		.ing-ficha { padding: .85rem 1rem; }
		.ing-ficha__fila { display: flex; justify-content: space-between; gap: .75rem; align-items: flex-start; }
		.ing-ficha__monto { font-size: 1.1rem; white-space: nowrap; font-variant-numeric: tabular-nums; color: var(--bs-success-text-emphasis); }
		.ing-ficha__chips { display: flex; flex-wrap: wrap; gap: .3rem; margin-top: .5rem; }
		.ing-ficha__acciones { display: flex; gap: .5rem; margin-top: .65rem; }
		.ing-ficha__acciones form { flex: 1 1 0; display: flex; }
		.ing-ficha__acciones .btn { flex: 1 1 0; min-height: 2.5rem; display: inline-flex; align-items: center; justify-content: center; gap: .4rem; }
		.ing-ficha__acciones .ing-accion__txt { display: inline; }

		.ing-foto { display: flex; gap: .75rem; align-items: center; }
		.ing-foto__img, .ing-foto__vacio { width: 64px; height: 64px; flex: 0 0 auto; border-radius: 8px; object-fit: cover; border: 1px solid var(--bs-border-color); }
		.ing-foto__vacio { display: grid; place-items: center; background: var(--bs-tertiary-bg); color: var(--bs-secondary-color); font-size: 1.3rem; }
		.ing-foto__vacio[hidden], .ing-foto__img[hidden] { display: none; }

		.ing-fab { position: fixed; right: 1rem; bottom: 1.25rem; z-index: 1030; width: 3.5rem; height: 3.5rem; border-radius: 50%;
		           font-size: 1.3rem; box-shadow: 0 .4rem 1rem rgba(0,0,0,.25); display: grid; place-items: center; padding: 0; }

		@media (max-width: 991.98px) { .ing-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
		@media (max-width: 767.98px) {
			.ing-nuevo { display: none; }
			.ing-kpi { padding: .65rem .8rem; }
			.ing-kpi__val { font-size: 1.2rem; }
			.ing-kpi__val--texto { font-size: 1rem; }
			.app-content { padding-bottom: 5rem; }
			.ing-modal__pie { flex-direction: column-reverse; align-items: stretch; }
			.ing-modal__pie .btn { width: 100%; margin: 0; min-height: 2.9rem; }
		}
	</style>
  </body>
</html>
