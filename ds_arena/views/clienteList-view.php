<?php
/* Clientes que alquilan instalaciones, con su saldo de monedero. */

use arena\controllers\arenaController;

$insArena = new arenaController();

$tituloVista = 'Clientes';
$vistaActual = 'clienteList';

$busqueda = trim((string)($_GET['q'] ?? ''));
$clientes = $insArena->clientes($busqueda);

$puedeCrear    = puede_crear('clienteList');
$puedeEditar   = puede_editar('clienteList');
$puedeEliminar = puede_eliminar('clienteList');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center" style="gap:12px;">
        <h3 class="card-title mb-0"><?php echo count($clientes); ?> cliente<?php echo count($clientes) === 1 ? '' : 's'; ?></h3>

        <div class="d-flex align-items-center" style="gap:12px;">
            <form method="GET" action="<?php echo APP_URL; ?>clienteList/">
                <?php /* input-group mantiene campo y botón unidos y en una línea:
                         con form-control suelto el campo toma ancho completo y
                         empujaba el botón a la fila de abajo. */ ?>
                <div class="input-group input-group-sm" style="width:16rem;">
                    <input type="text" name="q" class="form-control"
                           placeholder="Nombre o identificación"
                           aria-label="Buscar por nombre o identificación"
                           value="<?php echo htmlspecialchars($busqueda, ENT_QUOTES, 'UTF-8'); ?>">
                    <button type="submit" class="btn btn-outline-secondary"
                            title="Buscar" aria-label="Buscar"><i class="fas fa-search"></i></button>
                </div>
            </form>

            <?php if ($puedeCrear): ?>
                <a href="<?php echo APP_URL; ?>clienteForm/" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus me-1"></i> Nuevo cliente
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table mb-0">
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
                    <tr><td colspan="7" class="text-center text-muted py-4">
                        <?php echo $busqueda !== '' ? 'Sin resultados para esa búsqueda.' : 'Todavía no hay clientes registrados.'; ?>
                    </td></tr>
                <?php endif; ?>

                <?php foreach ($clientes as $c): $saldo = (float)$c['saldo']; ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($c['cliente_identificacion'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td><strong><?php echo htmlspecialchars($c['cliente_nombre'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                        <td>
                            <?php if ($c['cliente_celular']): ?>
                                <small class="d-block"><i class="fas fa-phone text-muted me-1"></i><?php echo htmlspecialchars($c['cliente_celular'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                            <?php if ($c['cliente_correo']): ?>
                                <small class="d-block text-muted"><?php echo htmlspecialchars($c['cliente_correo'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                            <?php if (!$c['cliente_celular'] && !$c['cliente_correo']): ?>
                                <small class="text-muted">—</small>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($saldo > 0): ?>
                                <strong class="text-success">$<?php echo number_format($saldo, 2); ?></strong>
                            <?php else: ?>
                                <span class="text-muted">$0.00</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int)$c['reservas'] > 0): ?>
                                <span class="badge text-bg-info"><?php echo (int)$c['reservas']; ?> vigente(s)</span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?php echo $c['cliente_estado'] === 'A' ? 'success' : 'secondary'; ?>">
                                <?php echo $c['cliente_estado'] === 'A' ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </td>
                        <td class="ds-tabla-acciones">
                            <?php if ($puedeEditar): ?>
                                <a href="<?php echo APP_URL; ?>clienteForm/?id=<?php echo (int)$c['cliente_id']; ?>"
                                   class="btn btn-sm btn-outline-secondary" title="Editar"><i class="fas fa-pen"></i></a>
                            <?php endif; ?>

                            <?php if ($puedeEliminar): ?>
                                <form class="FormularioAjax d-inline" method="POST"
                                      action="<?php echo APP_URL; ?>ajax/arenaAjax.php"
                                      data-confirmar="El cliente quedará dado de baja.">
                                    <input type="hidden" name="modulo_arena" value="eliminarCliente">
                                    <input type="hidden" name="cliente_id" value="<?php echo (int)$c['cliente_id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
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
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
