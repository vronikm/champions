<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Indicadores
|--------------------------------------------------------------------------
| Desde cuándo un número merece que el panel levante la mano.
|
|
| EL PROBLEMA QUE RESUELVE
|
| «Requiere tu atención» avisaba con la condición más simple que existe:
| mayor que cero. Un partido sin marcador, un alumno sin pagar, un dólar
| pendiente en Arena — todo disparaba. Con 266 alumnos eso significa que el
| panel avisa siempre, y un panel que avisa siempre no avisa de nada: se
| aprende a no mirarlo.
|
| Cuál es el número que de verdad merece atención lo sabe la escuela, no el
| código. Por eso se configura aquí.
|
|
| SUBIR UN UMBRAL NO OCULTA NADA
|
| Lo único que cambia es cuándo aparece el aviso. Las cifras siguen enteras
| en su pantalla: la deuda de Arena se sigue viendo en Cartera aunque su
| aviso esté callado. Conviene tenerlo claro antes de ponerlos altos, y por
| eso lo dice la propia pantalla y no sólo este comentario.
|
|
| ESCRIBIR AQUÍ NO CONTRADICE «INSIGHTS SÓLO LEE»
|
| La regla es que Insights no toca el dato de otros módulos. `insights_umbral`
| es tabla propia y el candado de InsightsConexion la admite justamente para
| esto: el módulo administra su configuración, no la información de nadie.
|
| Aun así, guardar exige `permiso_editar` sobre esta vista, va por POST con
| CSRF y queda en la bitácora. Quien sólo tiene «ver» mira los umbrales y no
| puede moverlos.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

$puedeEditar = puede_editar('configuracion');

/*----------  Guardar  ----------*/
$aviso = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!$puedeEditar) {
        /* No debería llegarse aquí: sin permiso el formulario no se pinta.
           Se comprueba igual, porque un formulario que no está en la página
           se puede enviar de todos modos. */
        http_response_code(403);
        $insInsights->auditar('EDITAR_UMBRALES', 'configuracion', ['ok' => false]);
        require __DIR__ . '/accesoDenegado-view.php';
        exit();
    }

    if (!csrf_valido('insights_umbral')) {
        $aviso = ['tono' => 'danger', 'texto' => 'La solicitud no se pudo verificar. Inténtelo de nuevo.'];
    } else {
        $enviados = [];
        foreach ((array) ($_POST['umbral'] ?? []) as $codigo => $datos) {
            $enviados[(string) $codigo] = [
                'valor'  => $datos['valor']  ?? 0,
                'estado' => $datos['estado'] ?? 'I',   /* la casilla no marcada no viaja */
            ];
        }

        $n = $insInsights->guardarUmbrales($enviados);

        /* Redirección después de escribir: así recargar la página no vuelve
           a enviar el formulario. */
        header('Location: ' . APP_URL . 'configuracion/?g=' . $n);
        exit();
    }
}

if (isset($_GET['g'])) {
    $n = (int) $_GET['g'];
    $aviso = ['tono' => $n > 0 ? 'success' : 'secondary',
              'texto' => $n > 0
                  ? ($n === 1 ? 'Se guardó 1 umbral.' : "Se guardaron $n umbrales.")
                  : 'No había nada que cambiar.'];
}

$umbrales = $insInsights->umbrales();

/* Para que la pantalla no pida a ciegas: se enseña el valor que tiene HOY
   cada indicador, al lado de su umbral. Sin eso hay que adivinar si 50 es
   mucho o poco. */
$avisosActuales = $insInsights->requiereAtencion($insInsights->periodo());

$tituloVista = 'Indicadores';
$vistaActual = 'configuracion';

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if ($aviso !== null): ?>
    <div class="callout mb-3" style="border-left-color:var(--bs-<?php echo $aviso['tono']; ?>);">
        <?php echo htmlspecialchars($aviso['texto'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">Cuándo avisar</h3>
                <span class="text-muted small"><?php echo count($umbrales); ?> indicadores</span>
            </div>

            <form method="post" action="<?php echo APP_URL; ?>configuracion/">
                <?php echo csrf_campo('insights_umbral'); ?>

                <div class="card-body">
                    <p class="text-muted small">
                        El panel avisa cuando la medida alcanza o supera el umbral.
                        Desactivar un indicador silencia su aviso por completo, que no es
                        lo mismo que ponerle un número muy alto: se lee como una decisión.
                    </p>

                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Indicador</th>
                                    <th scope="col" class="text-end" style="width:9rem;">Avisar desde</th>
                                    <th scope="col" class="text-center" style="width:6rem;">Activo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($umbrales as $codigo => $u): ?>
                                    <tr>
                                        <th scope="row" class="fw-normal">
                                            <label for="u_<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($u['umbral_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                            </label>
                                            <span class="d-block text-muted small">
                                                <?php echo htmlspecialchars($u['umbral_ayuda'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                            <?php if ($u['umbral_modificado'] !== null): ?>
                                                <span class="d-block text-muted small fst-italic">
                                                    modificado el <?php echo htmlspecialchars(
                                                        substr((string) $u['umbral_modificado'], 0, 16), ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php endif; ?>
                                        </th>

                                        <td>
                                            <div class="input-group input-group-sm">
                                                <?php if ($u['umbral_unidad'] === 'DINERO'): ?>
                                                    <span class="input-group-text">$</span>
                                                <?php endif; ?>
                                                <input type="number"
                                                       class="form-control text-end"
                                                       id="u_<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>"
                                                       name="umbral[<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>][valor]"
                                                       value="<?php echo $u['umbral_unidad'] === 'DINERO'
                                                            ? number_format((float) $u['umbral_valor'], 2, '.', '')
                                                            : (string) (int) $u['umbral_valor']; ?>"
                                                       min="0"
                                                       step="<?php echo $u['umbral_unidad'] === 'DINERO' ? '0.01' : '1'; ?>"
                                                       <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                                            </div>
                                        </td>

                                        <td class="text-center">
                                            <div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input" type="checkbox" role="switch"
                                                       id="e_<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>"
                                                       name="umbral[<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>][estado]"
                                                       value="A"
                                                       <?php echo $u['umbral_estado'] === 'A' ? 'checked' : ''; ?>
                                                       <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                                                <label class="form-check-label visually-hidden"
                                                       for="e_<?php echo htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'); ?>">
                                                    Activar el aviso de
                                                    <?php echo htmlspecialchars($u['umbral_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($puedeEditar): ?>
                    <div class="card-footer d-flex justify-content-end">
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="fas fa-save me-1"></i>Guardar
                        </button>
                    </div>
                <?php else: ?>
                    <div class="card-footer">
                        <span class="text-muted small">
                            Puede consultar los umbrales pero no modificarlos: requiere el
                            permiso de editar sobre esta vista, que se concede en Core.
                        </span>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- ==================== Cómo está el panel ahora ==================== -->
    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header">
                <h3 class="card-title mb-0">Con estos umbrales, el panel dice</h3>
            </div>
            <div class="card-body">
                <?php if (count($avisosActuales) === 0): ?>
                    <p class="text-muted mb-0">
                        Nada. Ningún indicador alcanza su umbral, así que el panel no
                        levanta la mano.
                    </p>
                <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($avisosActuales as $a): ?>
                            <li class="d-flex align-items-start mb-3">
                                <i class="fas <?php echo htmlspecialchars($a['icono'], ENT_QUOTES, 'UTF-8'); ?>
                                          text-<?php echo htmlspecialchars($a['tono'], ENT_QUOTES, 'UTF-8'); ?> me-2 mt-1"
                                   aria-hidden="true"></i>
                                <span class="small"><?php echo htmlspecialchars($a['texto'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="text-muted small mb-0 mt-3">
                        Es exactamente lo que se ve en el Panel. Cambie un umbral, guarde y
                        vuelva a mirar aquí: se comprueba el efecto sin salir de la
                        pantalla.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
