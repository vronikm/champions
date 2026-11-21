<?php
/*
| Resumen de cuenta de un alumno: beca o descuento, último pago de pensión,
| última asistencia y lo que debe (con ingreso, si se pide).
|
| Lo usan la ficha del alumno (alumnoProfile) y «Representa a» de la del
| representante (representanteProfile), para que digan lo mismo y con el
| mismo aspecto. Estilos: futbol.css (.ds-repre__resumen, .ds-repre__deuda).
|
| Espera:
|   $rcAlumno   la fila del alumno con alumno_estado, alumno_fechaingreso y
|               lo que añade mainModel::resumenCuentaAlumnos()
|   $rcIngreso  true para mostrar también la fecha de ingreso
|
| Se incluye con include (no _once): en «Representa a» va una vez por alumno.
*/
$rcH     = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$rcFecha = static fn($f) => ($f && !str_starts_with((string)$f, '0000')) ? substr((string)$f, 0, 10) : '';
/* «hace 1 año y 7 meses», «hace 3 meses», «este mes». */
$rcHace = static function (string $f): string {
	$d = (new DateTime($f))->diff(new DateTime('today'));
	if ($d->invert) { return 'más adelante'; }
	if ($d->y > 0)  { return 'hace ' . $d->y . ($d->y === 1 ? ' año' : ' años') . ($d->m > 0 ? ' y ' . $d->m . ($d->m === 1 ? ' mes' : ' meses') : ''); }
	if ($d->m > 0)  { return 'hace ' . $d->m . ($d->m === 1 ? ' mes' : ' meses'); }
	return 'este mes';
};

$rcActivo   = ($rcAlumno['alumno_estado'] ?? '') === 'A';
$rcIng      = $rcFecha($rcAlumno['alumno_fechaingreso'] ?? '');
$rcAsis     = $rcFecha($rcAlumno['ultima_asis'] ?? '');
$rcDiasAsis = $rcAsis !== '' ? (int)(new DateTime($rcAsis))->diff(new DateTime('today'))->days : null;
$rcPen      = $rcFecha($rcAlumno['pension_fecha'] ?? '');
$rcPago     = $rcFecha($rcAlumno['pago_fecha'] ?? '');
/* Un pago de otro concepto posterior al de pensión: también se muestra. */
$rcOtroPago = $rcPago !== '' && ($rcAlumno['pago_rubroid'] ?? '') !== 'RPE' && $rcPago >= $rcPen;

/* Beneficio: descuento_valor es la pensión que PAGA (como lo cobra pagosNew). */
$rcCod = (string)($rcAlumno['descuento_rubroid'] ?? '');
if ($rcCod !== '') {
	$rcSede   = (float)($rcAlumno['sede_pension'] ?? 0);
	$rcPaga   = $rcCod === 'DBC' ? 0.0 : min(max((float)$rcAlumno['descuento_valor'], 0), $rcSede);
	$rcNombre = ['DBC' => 'Beca 100 %', 'DBP' => 'Beca 50 %'][$rcCod] ?? (trim((string)$rcAlumno['descuento_nombre']) !== '' ? $rcAlumno['descuento_nombre'] : $rcCod);
	$rcDetalle = $rcCod === 'DBC' ? 'no paga pensión' : 'paga $' . number_format($rcPaga, 2) . ($rcSede > 0 ? ' de $' . number_format($rcSede, 2) : '');
}

/* Lo que debe (mainModel::resumenCuentaAlumnos: la misma cuenta que Pagos y Cobranza). */
$rcDeuda  = (float)($rcAlumno['deuda'] ?? 0);
$rcMeses  = (int)($rcAlumno['meses_deuda'] ?? 0);
$rcSaldo  = (float)($rcAlumno['saldo'] ?? 0);
$rcPartes = [];
if ($rcMeses > 0) { $rcPartes[] = $rcMeses . ($rcMeses === 1 ? ' pensión' : ' pensiones') . ($rcActivo ? '' : ($rcMeses === 1 ? ' asistida' : ' asistidas') . ' sin pagar'); }
if ($rcSaldo > 0) { $rcPartes[] = 'saldo $' . number_format($rcSaldo, 2); }
?>
<dl class="ds-repre__resumen">
	<?php if (!empty($rcIngreso)): ?>
		<div>
			<dt><i class="fas fa-right-to-bracket" aria-hidden="true"></i>Ingreso</dt>
			<dd>
				<?php if ($rcIng !== ''): ?>
					<?php echo date('d/m/Y', strtotime($rcIng)); ?>
					<small><?php echo $rcH($rcHace($rcIng)); ?></small>
				<?php else: ?>
					<span class="text-body-secondary">Sin fecha</span>
				<?php endif; ?>
			</dd>
		</div>
	<?php endif; ?>
	<?php if ($rcActivo): /* Al inactivo no se le cobra pensión: su beneficio no aplica. */ ?>
		<div>
			<dt><i class="fas fa-award" aria-hidden="true"></i>Beca o descuento</dt>
			<dd>
				<?php if ($rcCod === ''): ?>
					<span class="text-body-secondary">No tiene</span>
				<?php else: ?>
					<span class="badge ds-repre__beneficio"><?php echo $rcH($rcNombre); ?></span><small><?php echo $rcH($rcDetalle); ?></small>
				<?php endif; ?>
			</dd>
		</div>
	<?php endif; ?>
	<div>
		<dt><i class="fas fa-money-bill-wave" aria-hidden="true"></i>Último pago de pensión</dt>
		<dd>
			<?php if ($rcPen !== ''): ?>
				<?php echo date('d/m/Y', strtotime($rcPen)); ?>
				<small><?php echo $rcH(implode(' · ', array_filter([trim((string)$rcAlumno['pension_periodo']), '$' . number_format((float)$rcAlumno['pension_valor'], 2)]))); ?></small>
			<?php else: ?>
				<span class="text-body-secondary">Sin pagos de pensión</span>
			<?php endif; ?>
			<?php if ($rcOtroPago): ?>
				<small class="ds-repre__otro-pago">Después: <?php echo $rcH(trim((string)$rcAlumno['pago_concepto']) !== '' ? $rcAlumno['pago_concepto'] : 'otro concepto'); ?> $<?php echo number_format((float)$rcAlumno['pago_valor'], 2); ?> el <?php echo date('d/m/Y', strtotime($rcPago)); ?></small>
			<?php endif; ?>
		</dd>
	</div>
	<div>
		<dt><i class="fas fa-clipboard-check" aria-hidden="true"></i>Última asistencia</dt>
		<dd>
			<?php if ($rcAsis !== ''): ?>
				<?php echo date('d/m/Y', strtotime($rcAsis)); ?>
				<small class="<?php echo $rcDiasAsis > 30 ? 'text-danger' : ''; ?>"><?php echo $rcDiasAsis === 0 ? 'hoy' : ($rcDiasAsis === 1 ? 'ayer' : ($rcDiasAsis <= 60 ? 'hace ' . $rcDiasAsis . ' días' : $rcHace($rcAsis))); ?></small>
			<?php else: ?>
				<span class="text-body-secondary">Sin asistencias</span>
			<?php endif; ?>
		</dd>
	</div>
	<div class="ds-repre__deuda <?php echo $rcDeuda > 0 ? 'ds-repre__deuda--mora' : 'ds-repre__deuda--aldia'; ?>">
		<dt><i class="fas <?php echo $rcDeuda > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?>" aria-hidden="true"></i>Valor adeudado</dt>
		<dd>
			<?php if ($rcDeuda > 0): ?>
				$<?php echo number_format($rcDeuda, 2); ?> <span class="ds-repre__deuda-estado">en mora</span>
				<small><?php echo $rcH(implode(' + ', $rcPartes)); ?></small>
			<?php else: ?>
				Al día
			<?php endif; ?>
		</dd>
	</div>
</dl>
