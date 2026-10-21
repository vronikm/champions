<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Transacciones
|--------------------------------------------------------------------------
| El último salto del drill-down: el pago concreto, con fecha, sede, concepto
| y quién lo hizo. Es donde el dato deja de ser agregado.
|
|
| POR QUÉ ESTA PANTALLA TIENE PERMISO PROPIO
|
| Ver que una sede recaudó $11.000 y ver quién pagó cada cuota son dos cosas
| distintas. La primera es una cifra de gestión; la segunda es información de
| personas identificadas. El §7 lo pide explícitamente y el modelo de
| permisos ya lo permite: «transacciones» es una entrada de menú propia, así
| que su permiso de ver se concede por separado.
|
|
| LA PAGINACIÓN LA HACE EL SERVIDOR
|
| Son 5.499 transacciones. Traerlas todas al navegador para que DataTables
| las pagine es justo lo que prohíbe el §51, y empeora cada mes. Aquí se
| piden las filas de una página y se cuenta aparte.
|
| Por eso esta tabla NO lleva el buscador de DataTables: buscaría sólo dentro
| de la página visible y daría la impresión de haber mirado en todas. Filtrar
| es cosa de los controles de arriba, que van al servidor.
|
|
| SE MUESTRA EL NOMBRE, Y NADA MÁS
|
| Del alumno va el nombre corto: ni identificación, ni teléfono, ni
| dirección. Sin saber quién pagó la pantalla no sirve para conciliar, pero
| eso no obliga a arrastrar el resto de la ficha.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$p = $insInsights->periodo($_GET['desde'] ?? null, $_GET['hasta'] ?? null);

$modulos = $insInsights->modulosTransaccion();
$modulo  = isset($_GET['modulo']) && isset($modulos[$_GET['modulo']]) ? (string) $_GET['modulo'] : '';
$pagina  = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$porPag  = isset($_GET['n']) ? (int) $_GET['n'] : 50;

$r = $insInsights->transaccionesDetalle(
    ['desde' => $p['desde'], 'hasta' => $p['hasta'], 'modulo' => $modulo],
    $pagina, $porPag);

$insInsights->auditar('VER_TRANSACCIONES', 'transacciones', [
    'desde' => $p['desde'], 'hasta' => $p['hasta'], 'filas' => count($r['filas']),
]);

/** Conserva los filtros al saltar de página. */
$enlace = static function (array $cambios) use ($p, $modulo, $r): string {
    $q = array_merge([
        'desde'  => $p['desde'], 'hasta' => $p['hasta'],
        'modulo' => $modulo,     'n'     => $r['porPagina'],
        'pagina' => $r['pagina'],
    ], $cambios);
    return APP_URL . 'transacciones/?' . http_build_query(array_filter($q, static fn($v): bool => $v !== ''));
};

$colores = ['basketball' => 'primary', 'arena' => 'info', 'league' => 'secondary'];

$tituloVista = 'Transacciones';
$vistaActual = 'transacciones';

require_once __DIR__ . "/inc/layout-top.php";
?>

<!-- ==================== Filtros ==================== -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <label for="desde" class="form-label mb-1 small text-muted">Desde</label>
                <input type="date" class="form-control form-control-sm" id="desde" name="desde"
                       value="<?php echo htmlspecialchars($p['desde'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-auto">
                <label for="hasta" class="form-label mb-1 small text-muted">Hasta</label>
                <input type="date" class="form-control form-control-sm" id="hasta" name="hasta"
                       value="<?php echo htmlspecialchars($p['hasta'], ENT_QUOTES, 'UTF-8'); ?>">
            </div>
            <div class="col-auto">
                <label for="modulo" class="form-label mb-1 small text-muted">Módulo</label>
                <select class="form-select form-select-sm" id="modulo" name="modulo">
                    <option value="">Los tres</option>
                    <?php foreach ($modulos as $k => $etq): ?>
                        <option value="<?php echo $k; ?>" <?php echo $modulo === $k ? 'selected' : ''; ?>>
                            <?php echo $etq; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="n" class="form-label mb-1 small text-muted">Por página</label>
                <select class="form-select form-select-sm" id="n" name="n">
                    <?php foreach ([25, 50, 100, 200] as $op): ?>
                        <option value="<?php echo $op; ?>" <?php echo $r['porPagina'] === $op ? 'selected' : ''; ?>>
                            <?php echo $op; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-filter me-1"></i>Aplicar
                </button>
            </div>
            <div class="col-auto ms-auto text-muted small">
                <?php echo number_format($r['total']); ?> transacción(es) ·
                $<?php echo number_format($r['suma'], 2); ?>
            </div>
        </form>
    </div>
</div>

<!-- ==================== La lista ==================== -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">
            <?php echo $modulo === '' ? 'Todos los módulos' : $modulos[$modulo]; ?>
        </h3>
        <span class="text-muted small">
            <?php if ($r['total'] > 0): ?>
                página <?php echo $r['pagina']; ?> de <?php echo $r['paginas']; ?>
            <?php endif; ?>
        </span>
    </div>

    <div class="card-body">
        <?php if ($r['total'] === 0): ?>
            <p class="text-muted mb-0">
                No hay transacciones en el periodo seleccionado
                <?php if ($modulo !== ''): ?>para <?php echo $modulos[$modulo]; ?><?php endif; ?>.
            </p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Fecha</th>
                            <th scope="col">Módulo</th>
                            <th scope="col">Sede</th>
                            <th scope="col">Concepto</th>
                            <th scope="col">Quién</th>
                            <th scope="col">Referencia</th>
                            <th scope="col" class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($r['filas'] as $f): ?>
                            <tr>
                                <td class="text-nowrap"><?php echo htmlspecialchars((string) $f['fecha'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="badge text-bg-<?php echo $colores[$f['modulo']] ?? 'secondary'; ?>">
                                        <?php echo htmlspecialchars($modulos[$f['modulo']] ?? $f['modulo'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?php if ($f['sede'] === null || $f['sede'] === ''): ?>
                                        <span class="text-muted" title="League no tiene sede: sus torneos pueden organizarse fuera del club">
                                            fuera de sede
                                        </span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars((string) $f['sede'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?php echo htmlspecialchars((string) ($f['concepto'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($f['quien'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="small text-muted font-monospace">
                                    <?php echo htmlspecialchars((string) ($f['referencia'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td class="text-end">$<?php echo number_format((float) $f['valor'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- ==================== Paginación ==================== -->
            <?php if ($r['paginas'] > 1): ?>
                <?php
                /* Una ventana alrededor de la página actual. Con 110 páginas,
                   pintarlas todas llena la pantalla de números que nadie usa. */
                $ini = max(1, $r['pagina'] - 2);
                $fin = min($r['paginas'], $r['pagina'] + 2);
                ?>
                <nav class="mt-3" aria-label="Paginación de transacciones">
                    <ul class="pagination pagination-sm mb-0 justify-content-center flex-wrap">
                        <li class="page-item <?php echo $r['pagina'] <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($enlace(['pagina' => $r['pagina'] - 1]), ENT_QUOTES, 'UTF-8'); ?>"
                               aria-label="Página anterior">&laquo;</a>
                        </li>

                        <?php if ($ini > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo htmlspecialchars($enlace(['pagina' => 1]), ENT_QUOTES, 'UTF-8'); ?>">1</a>
                            </li>
                            <?php if ($ini > 2): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $ini; $i <= $fin; $i++): ?>
                            <li class="page-item <?php echo $i === $r['pagina'] ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo htmlspecialchars($enlace(['pagina' => $i]), ENT_QUOTES, 'UTF-8'); ?>"
                                   <?php echo $i === $r['pagina'] ? 'aria-current="page"' : ''; ?>><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($fin < $r['paginas']): ?>
                            <?php if ($fin < $r['paginas'] - 1): ?>
                                <li class="page-item disabled"><span class="page-link">…</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo htmlspecialchars($enlace(['pagina' => $r['paginas']]), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo $r['paginas']; ?>
                                </a>
                            </li>
                        <?php endif; ?>

                        <li class="page-item <?php echo $r['pagina'] >= $r['paginas'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="<?php echo htmlspecialchars($enlace(['pagina' => $r['pagina'] + 1]), ENT_QUOTES, 'UTF-8'); ?>"
                               aria-label="Página siguiente">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

            <p class="text-muted small mb-0 mt-3">
                La suma de arriba es la de <strong>todo el filtro</strong>, no la de esta
                página. Esta pantalla registra en la bitácora quién la consultó y con qué
                periodo: es la única de Insights que muestra pagos de personas
                identificadas.
            </p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
