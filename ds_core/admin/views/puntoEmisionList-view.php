<?php
/*
| Puntos de emisión por módulo.
|
| El SRI numera los comprobantes por (tipo, establecimiento, punto de
| emisión) y exige que el secuencial no se repita dentro de esa terna. Si
| dos módulos emitieran desde el mismo punto llevando cada uno su cuenta,
| generarían el mismo número: el organismo responde error 45 «secuencial
| registrado» y el comprobante ya entregado al cliente no se retira.
|
| Dar a cada módulo su propio punto elimina la colisión por construcción.
| Esta pantalla es donde se hace esa asignación.
|
| Sólo el superadministrador, igual que la configuración del SRI: de aquí
| depende con qué numeración se emiten documentos con validez tributaria.
*/

use admin\controllers\coreController;

if (!es_superadministrador()) {
    http_response_code(403);
    require_once __DIR__ . "/accesoDenegado-view.php";
    exit();
}

$insCore = new coreController();

$puntos      = $insCore->puntosEmision();
$sinAsignar  = $insCore->modulosSinPunto();
$cfg         = $insCore->configuracionSri();
$modulos     = ds_modulos();

$tituloVista = 'Puntos de emisión';
$vistaActual = 'puntoEmisionList';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="aviso-superadmin mb-3">
    <i class="fas fa-shield-alt fa-lg mt-1"></i>
    <div>
        <strong>Sólo el superadministrador.</strong><br>
        Un punto de emisión pertenece a un solo módulo. Es lo que impide que dos
        módulos generen el mismo número de comprobante, que el SRI rechaza y que
        no se puede corregir una vez entregado al cliente.
    </div>
</div>

<div class="row">
    <!-- ==================== Los puntos configurados ==================== -->
    <div class="col-lg-7 mb-3">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-hashtag me-2"></i>Asignación actual</h3>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Módulo</th>
                                <th>Punto</th>
                                <th class="text-end">Desde</th>
                                <th class="text-end">Emitidos</th>
                                <th>Estado</th>
                                <th class="ds-tabla-acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$puntos): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">
                                No hay puntos de emisión configurados.
                            </td></tr>
                        <?php else: foreach ($puntos as $p):
                            $nombre = $modulos[$p['punto_modulo']]['nombre'] ?? $p['punto_modulo'];
                            $activo = $p['punto_estado'] === 'A';
                        ?>
                            <tr>
                                <td>
                                    <strong><?php echo $h($nombre); ?></strong>
                                    <?php if ($p['punto_descripcion'] !== ''): ?>
                                        <br><small class="text-muted"><?php echo $h($p['punto_descripcion']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-monospace">
                                    <?php echo $h($p['punto_establecimiento'] . '-' . $p['punto_codigo']); ?>
                                </td>
                                <td class="text-end"><?php echo (int)$p['punto_secuencialinicio']; ?></td>
                                <td class="text-end">
                                    <?php echo (int)$p['emitidos']; ?>
                                    <?php if ((int)$p['contador'] > 0): ?>
                                        <br><small class="text-muted">contador: <?php echo (int)$p['contador']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge text-bg-<?php echo $activo ? 'success' : 'secondary'; ?>">
                                        <?php echo $activo ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="ds-tabla-acciones">
                                    <button type="button" class="btn btn-sm btn-actualizar js-editar-punto"
                                            title="Editar"
                                            data-punto='<?php echo $h(json_encode([
                                                'id'     => (int)$p['punto_id'],
                                                'modulo' => $p['punto_modulo'],
                                                'estab'  => $p['punto_establecimiento'],
                                                'codigo' => $p['punto_codigo'],
                                                'inicio' => (int)$p['punto_secuencialinicio'],
                                                'desc'   => $p['punto_descripcion'],
                                                'estado' => $p['punto_estado'],
                                                'emitidos' => (int)$p['emitidos'],
                                            ], JSON_UNESCAPED_UNICODE)); ?>'>
                                        <i class="fas fa-pen"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted" style="font-size:.86rem;">
                <strong>Emitidos</strong> cuenta los comprobantes de todos los módulos que
                salieron de ese punto. Si aparece un número donde no debería haberlo, dos
                módulos comparten punto y hay que separarlos antes de seguir emitiendo.
            </div>
        </div>

        <?php if ($sinAsignar): ?>
        <div class="callout callout-warning mt-3">
            <h6 class="mb-1"><i class="fas fa-exclamation-circle me-2"></i>Módulos sin punto asignado</h6>
            <p class="mb-0 text-muted">
                <?php echo $h(implode(', ', $sinAsignar)); ?>.
                Mientras no lo tengan, no pueden emitir comprobantes.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ==================== Alta y edición ==================== -->
    <div class="col-lg-5 mb-3">
        <form class="FormularioAjax" method="POST" autocomplete="off"
              action="<?php echo APP_URL; ?>ajax/coreAjax.php" id="formPunto">
            <input type="hidden" name="modulo_core" value="guardarPuntoEmision">
            <input type="hidden" name="punto_id" id="punto_id" value="0">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" id="tituloFormPunto">
                        <i class="fas fa-plus me-2"></i>Nuevo punto de emisión
                    </h3>
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label for="punto_modulo">Módulo</label>
                        <select name="punto_modulo" id="punto_modulo" class="form-select" required>
                            <option value="">Seleccione…</option>
                            <?php foreach ($modulos as $clave => $m):
                                if ($clave === 'core') { continue; } ?>
                                <option value="<?php echo $h($clave); ?>"><?php echo $h($m['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            Core no aparece porque administra, no factura.
                        </small>
                    </div>

                    <div class="row g-2">
                        <div class="mb-3 col-6">
                            <label for="punto_establecimiento">Establecimiento</label>
                            <input type="text" name="punto_establecimiento" id="punto_establecimiento"
                                   class="form-control text-monospace" maxlength="3" pattern="\d{3}"
                                   value="<?php echo $h($cfg['codigo_establecimiento']); ?>" required>
                        </div>
                        <div class="mb-3 col-6">
                            <label for="punto_codigo">Punto de emisión</label>
                            <input type="text" name="punto_codigo" id="punto_codigo"
                                   class="form-control text-monospace" maxlength="3" pattern="\d{3}"
                                   placeholder="003" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="punto_secuencialinicio">Numerar desde</label>
                        <input type="number" name="punto_secuencialinicio" id="punto_secuencialinicio"
                               class="form-control" min="1" max="999999999" value="1" required>
                        <small class="form-text text-muted">
                            Para continuar una serie que venía de otro sistema, indique el
                            siguiente número libre.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label for="punto_descripcion">Descripción</label>
                        <input type="text" name="punto_descripcion" id="punto_descripcion"
                               class="form-control" maxlength="100"
                               placeholder="Qué se factura desde aquí">
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="punto_estado">Estado</label>
                        <select name="punto_estado" id="punto_estado" class="form-select">
                            <option value="I">Inactivo — reserva el número, no permite emitir</option>
                            <option value="A">Activo — el módulo ya puede facturar</option>
                        </select>
                    </div>
                </div>

                <?php echo ds_acciones_form('', ['limpiar' => true, 'guardar' => 'Guardar punto']); ?>
            </div>
        </form>
    </div>
</div>

<script>
/* Cargar una fila en el formulario para editarla. Los datos vienen del
   atributo data-punto, ya escapados por el servidor: no se vuelve a
   consultar la base para algo que ya está en la página. */
(function () {
    var form = document.getElementById('formPunto');
    if (!form) { return; }

    var campos = {
        id: 'punto_id', modulo: 'punto_modulo', estab: 'punto_establecimiento',
        codigo: 'punto_codigo', inicio: 'punto_secuencialinicio',
        desc: 'punto_descripcion', estado: 'punto_estado'
    };

    document.querySelectorAll('.js-editar-punto').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var d;
            try { d = JSON.parse(boton.getAttribute('data-punto')); } catch (e) { return; }

            for (var k in campos) {
                var el = document.getElementById(campos[k]);
                if (el) { el.value = d[k]; }
            }

            document.getElementById('tituloFormPunto').innerHTML =
                '<i class="fas fa-pen me-2"></i>Editar ' + d.estab + '-' + d.codigo;

            /* Si el punto ya emitió, el número inicial no puede bajar de ahí.
               El servidor lo rechaza igualmente; esto sólo evita el viaje. */
            var inicio = document.getElementById('punto_secuencialinicio');
            if (inicio && d.emitidos > 0) { inicio.min = d.inicio; }

            form.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    /* Al limpiar, el formulario vuelve a ser un alta. Sin esto, el
       punto_id oculto seguiría apuntando al último registro editado y un
       "nuevo" acabaría sobrescribiéndolo. */
    form.addEventListener('reset', function () {
        setTimeout(function () {
            document.getElementById('punto_id').value = '0';
            document.getElementById('tituloFormPunto').innerHTML =
                '<i class="fas fa-plus me-2"></i>Nuevo punto de emisión';
            var inicio = document.getElementById('punto_secuencialinicio');
            if (inicio) { inicio.min = 1; }
        }, 0);
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
