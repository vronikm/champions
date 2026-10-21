<?php
/*
| Mis partidos · la agenda de quien la abre.
|
| ESTA VISTA ES EL ALCANCE POR FILA (D4)
|
| La consulta filtra por designación del usuario en sesión, siempre y para
| cualquiera: un administrador que entre aquí ve sus propios partidos, no
| todos. El alcance no es una comprobación colada en el código, es lo que
| la pantalla ES.
|
| A un árbitro se le concede permiso sobre esta vista y no sobre
| categoriaPanel. Con eso queda limitado a lo suyo sin que ninguna línea
| mencione su rol, que es lo que pedía el encargo.
|
| El servidor tampoco se fía de lo que aquí se pinte: guardarResultado()
| vuelve a comprobar la designación antes de aceptar un marcador.
*/

use league\controllers\competenciaController;

$insLeague = new competenciaController();

$tituloVista = 'Mis partidos';
$vistaActual = 'partidoAgenda';

$partidos = $insLeague->misPartidos();
$funciones = $insLeague->funcionesDesignacion();

$tono = ['exito' => 'success', 'aviso' => 'warning', 'peligro' => 'danger',
         'info' => 'info', 'neutro' => 'secondary'];

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-whistle me-2"></i>Partidos en los que participo</h3>
    </div>
    <div class="card-body p-0">
        <?php if (!$partidos): ?>
            <p class="text-center text-muted py-4 mb-0">
                No tiene partidos designados.
            </p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead>
                    <tr>
                        <th>Cuándo</th>
                        <th>Competencia</th>
                        <th>Local</th>
                        <th class="text-center" style="width:9rem;">Resultado</th>
                        <th>Visitante</th>
                        <th>Mi función</th>
                        <th style="width:8rem;">Estado</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($partidos as $p):
                    $cerrado = $p['estado_efectivo'] === 'S';
                ?>
                    <tr>
                        <td>
                            <?php if ($p['partido_fecha']): ?>
                                <?php echo $h($p['partido_fecha']); ?><br>
                                <small class="text-muted">
                                    <?php echo $h(substr((string)$p['partido_hora'], 0, 5)); ?>
                                    <?php if ($p['cancha']): ?>· <?php echo $h($p['cancha']); ?><?php endif; ?>
                                </small>
                            <?php else: ?>
                                <span class="text-muted">Sin programar</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted">
                            <?php echo $h($p['categoria_nombre']); ?><br>
                            <small><?php echo $h($p['torneo_nombre']); ?></small>
                        </td>
                        <td><?php echo $h($p['local']); ?></td>
                        <td class="text-center">
                            <?php if ($cerrado): ?>
                                <strong><?php echo (int)$p['partido_puntoslocal']; ?>
                                        – <?php echo (int)$p['partido_puntosvisitante']; ?></strong>
                            <?php else: ?>
                                <?php /* El formulario se ofrece porque el usuario está
                                        designado a ESTE partido; el servidor lo vuelve
                                        a comprobar de todos modos. */ ?>
                                <form class="FormularioAjax d-inline-flex" method="POST"
                                      action="<?php echo APP_URL; ?>ajax/leagueAjax.php"
                                      style="gap:.25rem;">
                                    <input type="hidden" name="modulo_league" value="guardarResultado">
                                    <input type="hidden" name="partido_id" value="<?php echo (int)$p['partido_id']; ?>">
                                    <input type="number" name="puntos_local" class="form-control form-control-sm"
                                           style="width:3.6rem;" min="0" max="300" required>
                                    <input type="number" name="puntos_visitante" class="form-control form-control-sm"
                                           style="width:3.6rem;" min="0" max="300" required>
                                    <button type="submit" class="btn btn-sm btn-primary" title="Guardar resultado">
                                        <i class="fas fa-save"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $h($p['visitante']); ?></td>
                        <td class="text-muted">
                            <?php echo $h($funciones[$p['designacion_funcion']] ?? '—'); ?>
                        </td>
                        <td>
                            <span class="badge text-bg-<?php echo $tono[$p['estado_tono']] ?? 'secondary'; ?>">
                                <?php echo $h($p['estado_nombre']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-footer text-muted" style="font-size:.85rem;">
        Esta pantalla muestra únicamente los partidos a los que usted está designado.
        Es así para todos los perfiles, incluido el administrador: para ver la
        competencia completa está el panel de la categoría.
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
