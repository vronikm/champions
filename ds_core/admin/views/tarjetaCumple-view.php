<?php
/*
|--------------------------------------------------------------------------
| Core — Tarjeta de cumpleaños
|--------------------------------------------------------------------------
| Dónde va la foto, el nombre y la edad sobre el marco, y qué marco se usa.
|
|
| POR QUÉ UN EDITOR Y NO UN FORMULARIO DE NÚMEROS
|
| Los números existen —y están ahí abajo, editables— pero nadie sabe si «foto
| a 21,8 %» es el sitio correcto sin verlo. Antes esto se ajustaba editando
| PHP y recargando hasta acertar. Aquí se arrastra la caja sobre el marco y el
| número se escribe solo.
|
|
| LA FOTO VA DETRÁS DEL MARCO
|
| El generador dibuja la foto y luego pone el PNG del marco encima. Por eso el
| marco necesita su ventana transparente: la foto asoma con la forma que tenga
| esa ventana —redondeada, inclinada, con bordes— sin que el código sepa nada
| de su contorno. El editor lo enseña igual, con el marco por encima de las
| cajas, para que lo que se ve aquí sea lo que sale impreso.
|
|
| TODO EN PORCENTAJE
|
| Nunca en píxeles. Así se puede sustituir el marco por otro de distinta
| resolución sin rehacer una sola coordenada.
*/

use admin\controllers\coreController;

$insCore = new coreController();

$plantillas = $insCore->plantillasCumple();
$idSel      = isset($_GET['p']) ? (int) $_GET['p'] : 0;
$p          = $insCore->plantillaCumple($idSel);

$puedeEditar   = puede_editar('tarjetaCumple');
$puedeCrear    = puede_crear('tarjetaCumple');
$puedeEliminar = puede_eliminar('tarjetaCumple');

/** La carpeta de marcos, para poder poner el ?v= de cada archivo. */
$carpetaMarcos = DS_HUB_PATH . 'ds_futbol/app/views/imagenes/cumples/';

/** URL de un marco con su fecha detrás, para que el navegador no sirva el viejo. */
$urlMarco = static function (string $archivo) use ($carpetaMarcos): string {
    $u = DS_FUTBOL_URL . 'app/views/imagenes/cumples/' . rawurlencode($archivo);
    $r = $carpetaMarcos . $archivo;
    return is_file($r) ? $u . '?v=' . filemtime($r) : $u;
};

$tituloVista = 'Tarjeta de cumpleaños';
$vistaActual = 'tarjetaCumple';

require_once __DIR__ . '/inc/layout-top.php';
?>

<?php if ($p === null): ?>
    <div class="card">
        <div class="card-body">
            <p class="mb-0 text-muted">
                No hay ninguna plantilla configurada. Ejecute la migración
                <code>056_tarjeta_cumple.sql</code>.
            </p>
        </div>
    </div>
<?php else: ?>

<?php
    /* La fecha del archivo en la URL: sin ella, subir un marco nuevo deja al
       navegador enseñando el anterior y parece que la subida no funcionó. */
    $marcoUrl = $urlMarco($p['plantilla_archivo']);

    $campos = [
        'foto'   => ['Foto',   'primary', ['x' => 'foto_x', 'y' => 'foto_y',
                                            'w' => 'foto_ancho', 'h' => 'foto_alto']],
        'nombre' => ['Nombre', 'success', ['x' => 'nombre_x', 'y' => 'nombre_y',
                                            'w' => 'nombre_ancho']],
        'edad'   => ['Edad',   'warning', ['x' => 'edad_x', 'y' => 'edad_y',
                                            'w' => 'edad_ancho']],
    ];
?>

<!--
| ==================== Las plantillas ====================
|
| Va FUERA del formulario del editor, y no por gusto: activar y eliminar son
| envíos propios, y HTML no permite anidar un formulario dentro de otro.
| Anidarlos hace que el navegador cierre el primero por su cuenta y los campos
| del editor dejen de enviarse, que es un fallo silencioso y difícil de ver.
|
| (Y no se escribe aquí la etiqueta literal ni siquiera dentro de este
| comentario: el navegador la ignora, pero cualquier comprobación que cuente
| etiquetas la suma y acusa un anidamiento que no existe. Pasó.)
-->
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Plantillas</h3>
        <?php if ($puedeCrear): ?>
            <button class="btn btn-sm btn-outline-primary" type="button"
                    data-bs-toggle="collapse" data-bs-target="#nuevaPlantilla"
                    aria-expanded="false" aria-controls="nuevaPlantilla">
                <i class="fas fa-plus me-1"></i>Nueva plantilla
            </button>
        <?php endif; ?>
    </div>

    <div class="card-body">
        <p class="text-muted small">
            Cada marco es una plantilla con sus propias medidas. Sólo una se imprime;
            las demás quedan guardadas para volver a ellas cuando haga falta.
        </p>

        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:64px;">Marco</th>
                        <th>Plantilla</th>
                        <th class="d-none d-md-table-cell">Archivo</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($plantillas as $fila):
                    $esActiva = $fila['plantilla_estado'] === 'A';
                    $esVista  = (int) $fila['plantilla_id'] === (int) $p['plantilla_id'];
                    $existe   = is_file($carpetaMarcos . $fila['plantilla_archivo']);
                ?>
                    <tr<?php echo $esVista ? ' class="table-active"' : ''; ?>>
                        <td>
                            <?php if ($existe): ?>
                                <!-- loading="lazy": un marco de imprenta pesa dos megas y
                                     esta tabla puede tener varios. -->
                                <img src="<?php echo htmlspecialchars($urlMarco($fila['plantilla_archivo']), ENT_QUOTES, 'UTF-8'); ?>"
                                     alt="" loading="lazy" width="48" height="60"
                                     style="object-fit:contain; background:var(--bs-tertiary-bg);
                                            border-radius:4px;">
                            <?php else: ?>
                                <span class="text-danger" title="El archivo no está en su carpeta">
                                    <i class="fas fa-triangle-exclamation"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($fila['plantilla_nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ($esActiva): ?>
                                <span class="badge text-bg-success ms-1">Se imprime</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <code class="small"><?php
                                echo htmlspecialchars($fila['plantilla_archivo'], ENT_QUOTES, 'UTF-8'); ?></code>
                        </td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a class="btn btn-sm btn-outline-secondary"
                                   href="?p=<?php echo (int) $fila['plantilla_id']; ?>">
                                    <i class="fas fa-sliders me-1"></i>Ajustar
                                </a>

                                <?php if (!$esActiva && $puedeEditar && $existe): ?>
                                    <form class="FormularioAjax d-inline" method="POST"
                                          action="<?php echo APP_URL; ?>ajax/coreAjax.php">
                                        <input type="hidden" name="modulo_core" value="activarPlantillaCumple">
                                        <input type="hidden" name="plantilla_id"
                                               value="<?php echo (int) $fila['plantilla_id']; ?>">
                                        <?php echo csrf_campo(); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-check me-1"></i>Usar esta
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if (!$esActiva && $puedeEliminar && count($plantillas) > 1): ?>
                                    <form class="FormularioAjax d-inline" method="POST"
                                          action="<?php echo APP_URL; ?>ajax/coreAjax.php">
                                        <input type="hidden" name="modulo_core" value="eliminarPlantillaCumple">
                                        <input type="hidden" name="plantilla_id"
                                               value="<?php echo (int) $fila['plantilla_id']; ?>">
                                        <?php echo csrf_campo(); ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                title="Eliminar la plantilla">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($puedeCrear): ?>
            <div class="collapse mt-3" id="nuevaPlantilla">
                <form class="FormularioAjax border rounded p-3" method="POST"
                      action="<?php echo APP_URL; ?>ajax/coreAjax.php"
                      enctype="multipart/form-data" autocomplete="off">
                    <input type="hidden" name="modulo_core" value="nuevaPlantillaCumple">
                    <!-- Las medidas se copian de la que se está viendo: los marcos de
                         una misma escuela se parecen y casi siempre basta con
                         mover la ventana un poco. -->
                    <input type="hidden" name="copiar_de" value="<?php echo (int) $p['plantilla_id']; ?>">
                    <?php echo csrf_campo(); ?>

                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-4">
                            <label for="nueva_nombre" class="form-label small mb-1">Nombre</label>
                            <input type="text" class="form-control form-control-sm" id="nueva_nombre"
                                   name="plantilla_nombre" maxlength="80" required
                                   placeholder="Marco 2027">
                        </div>
                        <div class="col-12 col-md-5">
                            <label for="nueva_marco" class="form-label small mb-1">Marco (PNG)</label>
                            <input type="file" class="form-control form-control-sm" id="nueva_marco"
                                   name="plantilla_marco" accept="image/png" required>
                        </div>
                        <div class="col-12 col-md-3 d-grid">
                            <button type="submit" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus me-1"></i>Crear
                            </button>
                        </div>
                    </div>
                    <div class="form-text mt-2">
                        Copia las medidas de «<?php echo htmlspecialchars($p['plantilla_nombre'], ENT_QUOTES, 'UTF-8'); ?>»
                        y entra <strong>inactiva</strong>: se ajusta primero y se activa después.
                        Si el PNG trae el damero de la transparencia pintado en vez de vacío,
                        se detecta y se abre la ventana automáticamente.
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<form class="FormularioAjax" method="POST"
      action="<?php echo APP_URL; ?>ajax/coreAjax.php"
      enctype="multipart/form-data" autocomplete="off">
    <input type="hidden" name="modulo_core" value="guardarPlantillaCumple">
    <input type="hidden" name="plantilla_id" value="<?php echo (int) $p['plantilla_id']; ?>">
    <?php echo csrf_campo(); ?>

    <div class="row">

        <!-- ==================== El editor ==================== -->
        <div class="col-12 col-xl-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Dónde va cada cosa</h3>
                    <span class="text-muted small">
                        <?php echo $p['marco_ancho']; ?> × <?php echo $p['marco_alto']; ?> px
                    </span>
                </div>
                <div class="card-body">

                    <?php if (!$p['marco_existe']): ?>
                        <div class="callout mb-3" style="border-left-color:var(--bs-danger);">
                            El archivo del marco no está en su carpeta:
                            <code><?php echo htmlspecialchars($p['plantilla_archivo'], ENT_QUOTES, 'UTF-8'); ?></code>.
                            Suba uno nuevo abajo.
                        </div>
                    <?php endif; ?>

                    <p class="text-muted small">
                        Arrastre cada caja para moverla, o el tirador de su esquina para
                        cambiar el tamaño. El marco se dibuja <strong>encima</strong>, igual
                        que en la tarjeta impresa.
                    </p>

                    <!--
                    | El lienzo mantiene la proporción del marco con aspect-ratio, así
                    | que las cajas en % caen donde caerán de verdad sea cual sea el
                    | ancho de la pantalla.
                    -->
                    <div id="lienzo"
                         style="position:relative; width:100%;
                                aspect-ratio:<?php echo max(1, (int) $p['marco_ancho']); ?>/<?php echo max(1, (int) $p['marco_alto']); ?>;
                                background:var(--bs-tertiary-bg); overflow:hidden; user-select:none;
                                border-radius:8px;">

                        <?php foreach ($campos as $clave => [$etq, $tono, $mapa]): ?>
                            <div class="caja-area" data-area="<?php echo $clave; ?>"
                                 style="position:absolute; box-sizing:border-box;
                                        border:2px solid var(--bs-<?php echo $tono; ?>);
                                        background:rgb(from var(--bs-<?php echo $tono; ?>) r g b / .18);
                                        cursor:move; z-index:2;
                                        left:<?php echo (float) $p[$mapa['x']]; ?>%;
                                        top:<?php echo (float) $p[$mapa['y']]; ?>%;
                                        width:<?php echo (float) $p[$mapa['w']]; ?>%;
                                        height:<?php echo isset($mapa['h']) ? (float) $p[$mapa['h']] . '%' : '4%'; ?>;">
                                <span class="badge text-bg-<?php echo $tono; ?>"
                                      style="position:absolute; top:-1px; left:-1px; font-size:.65rem;">
                                    <?php echo $etq; ?>
                                </span>
                                <?php if (isset($mapa['h'])): ?>
                                    <span class="tirador"
                                          style="position:absolute; right:-6px; bottom:-6px; width:14px; height:14px;
                                                 background:var(--bs-<?php echo $tono; ?>); border-radius:3px;
                                                 cursor:nwse-resize;"></span>
                                <?php else: ?>
                                    <span class="tirador tirador-ancho"
                                          style="position:absolute; right:-6px; top:50%; transform:translateY(-50%);
                                                 width:12px; height:18px;
                                                 background:var(--bs-<?php echo $tono; ?>); border-radius:3px;
                                                 cursor:ew-resize;"></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <!-- El marco, POR ENCIMA de las cajas: es como sale impreso -->
                        <img id="marcoImg" src="<?php echo htmlspecialchars($marcoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                             alt="Marco de la tarjeta"
                             style="position:absolute; inset:0; width:100%; height:100%;
                                    pointer-events:none; z-index:3;">
                    </div>

                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <a href="<?php echo DS_FUTBOL_URL; ?>cumpleaniosTarjeta/<?php
                             echo (int) ($insCore->unAlumnoParaPrueba() ?? 0); ?>/"
                           target="_blank" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-eye me-1"></i>Ver una tarjeta real
                        </a>
                        <span class="text-muted small align-self-center">
                            Se abre con un alumno de ejemplo, tal como saldrá impresa.
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== Los números ==================== -->
        <div class="col-12 col-xl-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Ajuste fino</h3>
                </div>
                <div class="card-body">

                    <div class="mb-3">
                        <label for="plantilla_nombre" class="form-label">Nombre de la plantilla</label>
                        <input type="text" class="form-control" id="plantilla_nombre" name="plantilla_nombre"
                               value="<?php echo htmlspecialchars($p['plantilla_nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                               maxlength="80" <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                    </div>

                    <div class="mb-3">
                        <label for="plantilla_marco" class="form-label">Cambiar el marco</label>
                        <input type="file" class="form-control" id="plantilla_marco" name="plantilla_marco"
                               accept="image/png" <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                        <div class="form-text">
                            Tiene que ser <strong>PNG con la ventana de la foto transparente</strong>:
                            es por donde asoma la foto, que se dibuja detrás. En JPEG esa ventana
                            saldría rellena y taparía la foto.
                            <br>
                            Al subirlo se comprueba que por el área de la foto se vea algo.
                            Si el damero gris viene <em>pintado</em> —un fallo corriente al
                            exportar— se abre la ventana automáticamente y se conserva el
                            original. Si no hay ventana de ninguna clase, no se guarda.
                            <br>
                            Sustituye el marco de <strong>esta</strong> plantilla. Para tener
                            los dos, use «Nueva plantilla» arriba.
                        </div>
                    </div>

                    <hr>

                    <?php
                    /* Cada bloque: las medidas de un área. Los inputs y las cajas del
                       lienzo se sincronizan en los dos sentidos. */
                    $bloques = [
                        ['Foto', 'foto', [
                            ['foto_x', 'Izquierda'], ['foto_y', 'Arriba'],
                            ['foto_ancho', 'Ancho'], ['foto_alto', 'Alto']]],
                        ['Nombre', 'nombre', [
                            ['nombre_x', 'Izquierda'], ['nombre_y', 'Base del texto'],
                            ['nombre_ancho', 'Ancho'], ['nombre_tam', 'Tamaño']]],
                        ['Edad', 'edad', [
                            ['edad_x', 'Izquierda'], ['edad_y', 'Base del texto'],
                            ['edad_ancho', 'Ancho'], ['edad_tam', 'Tamaño']]],
                    ];
                    foreach ($bloques as [$titulo, $clave, $cs]): ?>
                        <h6 class="fw-bold mt-3"><?php echo $titulo; ?></h6>
                        <div class="row g-2 mb-2">
                            <?php foreach ($cs as [$campo, $etq]): ?>
                                <div class="col-6 col-sm-3">
                                    <label for="<?php echo $campo; ?>" class="form-label small mb-1">
                                        <?php echo $etq; ?>
                                    </label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.001" min="0" max="100"
                                               class="form-control text-end campo-area"
                                               id="<?php echo $campo; ?>" name="<?php echo $campo; ?>"
                                               data-area="<?php echo $clave; ?>"
                                               value="<?php echo (float) $p[$campo]; ?>"
                                               <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <?php if ($clave !== 'foto'): ?>
                                <div class="col-6 col-sm-3">
                                    <label for="<?php echo $clave; ?>_color" class="form-label small mb-1">Color</label>
                                    <input type="color" class="form-control form-control-sm form-control-color w-100"
                                           id="<?php echo $clave; ?>_color" name="<?php echo $clave; ?>_color"
                                           value="<?php echo htmlspecialchars($p[$clave . '_color'], ENT_QUOTES, 'UTF-8'); ?>"
                                           <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                                </div>
                                <div class="col-6 col-sm-3">
                                    <label for="<?php echo $clave; ?>_alinea" class="form-label small mb-1">Alineación</label>
                                    <select class="form-select form-select-sm"
                                            id="<?php echo $clave; ?>_alinea" name="<?php echo $clave; ?>_alinea"
                                            <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                                        <?php foreach (['I' => 'Izquierda', 'C' => 'Centro', 'D' => 'Derecha'] as $v => $t): ?>
                                            <option value="<?php echo $v; ?>"
                                                <?php echo $p[$clave . '_alinea'] === $v ? 'selected' : ''; ?>>
                                                <?php echo $t; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="mb-3">
                        <label for="edad_texto" class="form-label small">Texto de la edad</label>
                        <input type="text" class="form-control form-control-sm" id="edad_texto" name="edad_texto"
                               value="<?php echo htmlspecialchars($p['edad_texto'], ENT_QUOTES, 'UTF-8'); ?>"
                               maxlength="40" <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                        <div class="form-text">
                            <code>{edad}</code> se sustituye por el número. Por ejemplo
                            «<code>{edad} AÑOS</code>» o «<code>¡Cumple {edad}!</code>».
                        </div>
                    </div>

                    <hr>

                    <!--
                    | La franja: sólo para marcos que NO traen sus casillas.
                    | Un marco con «NOMBRE:» y «EDAD:» dibujados no la necesita, y
                    | pintarla encima lo estropea.
                    -->
                    <div class="form-check form-switch mb-2">
                        <input type="hidden" name="franja_activa" value="0">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="franja_activa" name="franja_activa" value="1"
                               <?php echo $p['franja_activa'] === 'S' ? 'checked' : ''; ?>
                               <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="franja_activa">
                            Dibujar una franja bajo la foto para el texto
                        </label>
                        <div class="form-text">
                            Actívela sólo si el marco <strong>no</strong> trae sus propias casillas
                            de nombre y edad.
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6 col-sm-3">
                            <label for="franja_alto" class="form-label small mb-1">Alto</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.001" min="0" max="100" class="form-control text-end"
                                       id="franja_alto" name="franja_alto"
                                       value="<?php echo (float) $p['franja_alto']; ?>"
                                       <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <label for="franja_color" class="form-label small mb-1">Color</label>
                            <input type="color" class="form-control form-control-sm form-control-color w-100"
                                   id="franja_color" name="franja_color"
                                   value="<?php echo htmlspecialchars($p['franja_color'], ENT_QUOTES, 'UTF-8'); ?>"
                                   <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                        </div>
                        <div class="col-6 col-sm-3">
                            <label for="franja_opacidad" class="form-label small mb-1">Opacidad</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="1" min="0" max="100" class="form-control text-end"
                                       id="franja_opacidad" name="franja_opacidad"
                                       value="<?php echo (int) $p['franja_opacidad']; ?>"
                                       <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                                <span class="input-group-text">%</span>
                            </div>
                        </div>
                        <div class="col-6 col-sm-3">
                            <label for="angulo" class="form-label small mb-1">Inclinación</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" min="-45" max="45" class="form-control text-end"
                                       id="angulo" name="angulo"
                                       value="<?php echo (float) $p['angulo']; ?>"
                                       <?php echo $puedeEditar ? '' : 'disabled'; ?>>
                                <span class="input-group-text">°</span>
                            </div>
                        </div>
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
                            Puede consultar la plantilla pero no modificarla: requiere el permiso
                            de editar sobre esta vista.
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<script src="<?php echo ds_recurso('ds_core/assets/js/tarjeta-editor.js'); ?>"></script>

<?php endif; ?>

<?php require_once __DIR__ . '/inc/layout-bottom.php'; ?>
