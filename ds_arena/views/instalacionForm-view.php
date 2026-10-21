<?php
/* Alta y edición de una instalación (cancha o residencia). */

use arena\controllers\arenaController;

$insArena = new arenaController();

$id      = (int)($_GET['id'] ?? 0);
$inst    = $id > 0 ? $insArena->instalacion($id) : null;
$esAlta  = ($inst === null);

if ($esAlta && !puede_crear('instalacionList')) {
    require_once __DIR__ . "/accesoDenegado-view.php"; exit();
}
if (!$esAlta && !puede_editar('instalacionList')) {
    require_once __DIR__ . "/accesoDenegado-view.php"; exit();
}

$tituloVista = $esAlta ? 'Nueva instalación' : 'Editar instalación';
$vistaActual = 'instalacionList';

$sedes = $insArena->sedesAlquiler();
$pisos = $insArena->tiposPiso();

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="row justify-content-center">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header"><h3 class="card-title"><?php echo $tituloVista; ?></h3></div>

            <form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/arenaAjax.php">
                <input type="hidden" name="modulo_arena" value="guardarInstalacion">
                <input type="hidden" name="instalacion_id" value="<?php echo $id; ?>">

                <div class="card-body">

                    <div class="row g-2">
                        <div class="mb-3 col-md-4">
                            <label for="instalacion_clase">Tipo <span class="text-danger">*</span></label>
                            <select class="form-select" id="instalacion_clase" name="instalacion_clase" required>
                                <option value="C" <?php echo ($inst['instalacion_clase'] ?? 'C') === 'C' ? 'selected' : ''; ?>>Cancha</option>
                                <option value="R" <?php echo ($inst['instalacion_clase'] ?? '') === 'R' ? 'selected' : ''; ?>>Residencia</option>
                            </select>
                        </div>

                        <div class="mb-3 col-md-8">
                            <label for="instalacion_sedeid">Sede <span class="text-danger">*</span></label>
                            <select class="form-select" id="instalacion_sedeid" name="instalacion_sedeid" required>
                                <option value="">Seleccione…</option>
                                <?php foreach ($sedes as $s): ?>
                                    <option value="<?php echo (int)$s['sede_id']; ?>"
                                        <?php echo (int)($inst['instalacion_sedeid'] ?? 0) === (int)$s['sede_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['sede_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Sólo aparecen las sedes marcadas como de alquiler.</small>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-4">
                            <label for="instalacion_codigo">Código</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="instalacion_codigo" name="instalacion_codigo"
                                       maxlength="20" placeholder="Se genera solo"
                                       value="<?php echo htmlspecialchars((string)($inst['instalacion_codigo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php if ($esAlta): ?>
                                <?php /* En Bootstrap 5 el botón va suelto dentro
                                         del input-group; el <div> envolvente de
                                         la versión 4 ya no existe. */ ?>
                                <button class="btn btn-outline-secondary" type="button" id="btnCodigoAuto"
                                        title="Volver a la propuesta del sistema">
                                    <i class="fas fa-sync-alt"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted" id="ayudaCodigo">
                                <?php echo $esAlta
                                    ? 'Se asigna solo al elegir sede y tipo. Puede escribir el suyo.'
                                    : 'Único dentro de la sede. Déjelo en blanco para reasignarlo.'; ?>
                            </small>
                        </div>

                        <div class="mb-3 col-md-8">
                            <label for="instalacion_nombre">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="instalacion_nombre" name="instalacion_nombre"
                                   maxlength="80" required
                                   value="<?php echo htmlspecialchars((string)($inst['instalacion_nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                    </div>

                    <!-- Sólo aplica a canchas -->
                    <div class="row g-2" id="camposCancha">
                        <div class="mb-3 col-md-6">
                            <label for="instalacion_cubierta">Cubierta</label>
                            <select class="form-select" id="instalacion_cubierta" name="instalacion_cubierta">
                                <option value="S" <?php echo ($inst['instalacion_cubierta'] ?? 'S') === 'S' ? 'selected' : ''; ?>>Sí, techada</option>
                                <option value="N" <?php echo ($inst['instalacion_cubierta'] ?? '') === 'N' ? 'selected' : ''; ?>>No, al aire libre</option>
                            </select>
                        </div>

                        <div class="mb-3 col-md-6">
                            <label for="instalacion_pisoid">Tipo de piso</label>
                            <select class="form-select" id="instalacion_pisoid" name="instalacion_pisoid">
                                <option value="0">Sin especificar</option>
                                <?php foreach ($pisos as $p): ?>
                                    <option value="<?php echo (int)$p['piso_id']; ?>"
                                        <?php echo (int)($inst['instalacion_pisoid'] ?? 0) === (int)$p['piso_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($p['piso_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-md-4">
                            <label for="instalacion_valorhora">Valor por hora (USD) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" class="form-control"
                                   id="instalacion_valorhora" name="instalacion_valorhora" required
                                   value="<?php echo htmlspecialchars((string)($inst['instalacion_valorhora'] ?? '0.00'), ENT_QUOTES, 'UTF-8'); ?>">
                            <small class="text-muted">Tarifa base. Puede afinarse por franja horaria.</small>
                        </div>

                        <div class="mb-3 col-md-4">
                            <label for="instalacion_capacidad">Capacidad</label>
                            <input type="number" min="0" class="form-control"
                                   id="instalacion_capacidad" name="instalacion_capacidad"
                                   value="<?php echo htmlspecialchars((string)($inst['instalacion_capacidad'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                            <small class="text-muted">Personas o plazas.</small>
                        </div>

                        <div class="mb-3 col-md-4">
                            <label for="instalacion_estado">Estado</label>
                            <select class="form-select" id="instalacion_estado" name="instalacion_estado">
                                <option value="A" <?php echo ($inst['instalacion_estado'] ?? 'A') === 'A' ? 'selected' : ''; ?>>Activa</option>
                                <option value="I" <?php echo ($inst['instalacion_estado'] ?? '') === 'I' ? 'selected' : ''; ?>>Inactiva</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="instalacion_detalle">Descripción</label>
                        <textarea class="form-control" id="instalacion_detalle" name="instalacion_detalle"
                                  rows="2" maxlength="250"><?php echo htmlspecialchars((string)($inst['instalacion_detalle'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>
                </div>

                <?php echo ds_acciones_form(APP_URL . 'instalacionList/'); ?>
            </form>
        </div>
    </div>
</div>

<script>
/* Cubierta y tipo de piso sólo tienen sentido en canchas. */
(function () {
    var clase  = document.getElementById('instalacion_clase');
    var cancha = document.getElementById('camposCancha');

    function alternar() {
        cancha.style.display = (clase.value === 'C') ? '' : 'none';
    }

    clase.addEventListener('change', alternar);
    alternar();
})();

<?php if ($esAlta): ?>
/* El código se propone solo: prefijo de la sede + tipo + consecutivo.
   Sólo en el alta: cambiarlo en una instalación ya registrada obligaría a
   volver a rotularla.

   Quien escriba su propio código manda: en cuanto se teclea en el campo se
   deja de proponer, y sólo el botón de recarga devuelve el mando al
   sistema. Esto es comodidad de la pantalla; la asignación de verdad la
   hace el servidor al guardar. */
(function () {
    var campo  = document.getElementById('instalacion_codigo');
    var sede   = document.getElementById('instalacion_sedeid');
    var clase  = document.getElementById('instalacion_clase');
    var boton  = document.getElementById('btnCodigoAuto');
    var ayuda  = document.getElementById('ayudaCodigo');

    if (!campo || !sede || !clase) { return; }

    var propuesto = '';      /* lo último que propuso el servidor */
    var manual    = false;   /* el usuario tomó el mando */

    campo.addEventListener('input', function () {
        manual = true;
        ayuda.textContent = 'Código propio. Use el botón para volver a la propuesta.';
    });

    function proponer() {
        if (manual || !sede.value) { return; }

        var datos = new FormData();
        datos.append('modulo_arena', 'sugerirCodigo');
        datos.append('instalacion_sedeid', sede.value);
        datos.append('instalacion_clase', clase.value);
        datos.append('instalacion_id', '0');

        fetch('<?php echo APP_URL; ?>ajax/arenaAjax.php', {
            method: 'POST', body: datos, credentials: 'same-origin'
        })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (manual || !d || !d.codigo) { return; }
            propuesto = d.codigo;
            campo.value = d.codigo;
            ayuda.textContent = 'Propuesto por el sistema. Puede escribir otro.';
        })
        .catch(function () {
            /* Si la propuesta no llega, el campo sigue editable y el
               servidor asigna el código igualmente al guardar. */
            ayuda.textContent = 'Déjelo en blanco y el sistema lo asignará al guardar.';
        });
    }

    if (boton) {
        boton.addEventListener('click', function () {
            manual = false;
            campo.value = '';
            proponer();
        });
    }

    sede.addEventListener('change', proponer);
    clase.addEventListener('change', proponer);

    /* Al abrir el alta la sede suele venir vacía; si el navegador la
       recuerda, se propone de entrada. */
    proponer();
})();
<?php endif; ?>
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
