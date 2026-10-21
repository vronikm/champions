<?php
/*
| Plantilla de un equipo inscrito.
|
| La pantalla dice, por cada persona, si PUEDE ser habilitada y por qué no
| cuando no puede. El encargo pedía impedir la habilitación de quien
| incumpla requisitos; mostrar el motivo es lo que convierte esa negativa
| en algo que el usuario puede resolver en vez de en un botón que no
| responde.
|
| La edad se mide a la FECHA DE CORTE de la categoría, no a hoy. Si la
| categoría no tiene corte configurado se avisa, porque entonces «Sub-14»
| significa algo distinto cada mes.
*/

use league\controllers\competenciaController;

$insLeague = new competenciaController();

$inscripcionId = (int)(explode('/', (string)($_GET['views'] ?? ''))[1] ?? 0);

$cabecera = $insLeague->inscripcionConContexto($inscripcionId);

if (!$cabecera) {
    $tituloVista = 'Plantilla';
    $vistaActual = 'plantillaPanel';
    require_once __DIR__ . "/inc/layout-top.php";
    echo '<div class="callout callout-warning"><h6 class="mb-1">'
       . '<i class="fas fa-exclamation-circle me-2"></i>Inscripción no encontrada</h6>'
       . '<p class="mb-0 text-muted">Elija un equipo desde el panel de su categoría.</p></div>';
    require_once __DIR__ . "/inc/layout-bottom.php";
    return;
}

$tituloVista = $cabecera['equipo_nombre'];
$vistaActual = 'plantillaPanel';

$incluirBajas = ($_GET['bajas'] ?? '') === '1';
$filas   = $insLeague->plantilla($inscripcionId, $incluirBajas);
$roles   = $insLeague->rolesPlantilla();
$fotosUrl = competenciaController::fotosUrl();

$habilitados = 0;
foreach ($filas as $f) {
    if ($f['plantilla_habilitado'] === 'S' && $f['plantilla_baja'] === null) { $habilitados++; }
}
$minimo = (int)$cabecera['categoria_minhabilitados'];

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

require_once __DIR__ . "/inc/layout-top.php";
?>

<p class="text-muted mb-3">
    <a href="<?php echo APP_URL; ?>categoriaPanel/<?php echo (int)$cabecera['categoria_id']; ?>/"
       class="ds-link">← <?php echo $h($cabecera['categoria_nombre']); ?></a>
    <span class="mx-2">·</span><?php echo $h($cabecera['torneo_nombre']); ?>
</p>

<?php if (empty($cabecera['categoria_fechacorte'])
          && ($cabecera['categoria_edadmin'] !== null || $cabecera['categoria_edadmax'] !== null)): ?>
<div class="callout callout-warning">
    <h6 class="mb-1"><i class="fas fa-exclamation-circle me-2"></i>Falta la fecha de corte</h6>
    <p class="mb-0 text-muted">
        Esta categoría tiene rango de edad pero no indica a qué fecha se mide, así que
        las edades de abajo están calculadas a hoy y cambiarán solas con el tiempo.
        Configúrela en <a href="<?php echo APP_URL; ?>categoriaList/<?php echo (int)$cabecera['categoria_torneoid']; ?>/">la categoría</a>.
    </p>
</div>
<?php endif; ?>

<div class="row">
    <!-- ==================== La plantilla ==================== -->
    <div class="col-lg-8 mb-3">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">
                    <i class="fas fa-id-card me-2"></i>Plantilla
                    <span class="badge text-bg-<?php echo $habilitados >= $minimo ? 'success' : 'warning'; ?> ms-2">
                        <?php echo $habilitados; ?> habilitados
                        <?php if ($minimo > 0): ?>de <?php echo $minimo; ?> mínimos<?php endif; ?>
                    </span>
                </h3>
                <a href="<?php echo APP_URL; ?>plantillaPanel/<?php echo $inscripcionId; ?>/<?php
                     echo $incluirBajas ? '' : '?bajas=1'; ?>" class="ds-link">
                    <?php echo $incluirBajas ? 'Ocultar bajas' : 'Ver bajas'; ?> →
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr>
                                <th style="width:3rem;"></th>
                                <th>Persona</th>
                                <th>Rol</th>
                                <th class="text-center">Dorsal</th>
                                <th class="text-center">Edad</th>
                                <th>Habilitación</th>
                                <th>Imagen</th>
                                <th class="ds-tabla-acciones"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!$filas): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">
                                Todavía no hay nadie en esta plantilla.
                            </td></tr>
                        <?php else: foreach ($filas as $f):
                            $baja   = $f['plantilla_baja'] !== null;
                            $faltas = $insLeague->motivosNoHabilitable($f);
                            $puede  = !$faltas && !$baja;
                        ?>
                            <tr<?php echo $baja ? ' class="text-muted" style="opacity:.6"' : ''; ?>>
                                <td>
                                    <?php if ($f['persona_foto']): ?>
                                        <img src="<?php echo $fotosUrl . rawurlencode($f['persona_foto']); ?>"
                                             alt="" style="width:34px;height:34px;object-fit:cover;border-radius:50%;">
                                    <?php else: ?>
                                        <span class="d-inline-flex align-items-center justify-content-center text-muted"
                                              style="width:34px;height:34px;border-radius:50%;background:#e9ecef;">
                                            <i class="fas fa-user"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo $h($f['persona_apellidos'] . ' ' . $f['persona_nombres']); ?></strong>
                                    <br><small class="text-muted"><?php echo $h($f['persona_identificacion']); ?></small>
                                    <?php if ($baja): ?>
                                        <br><small>baja el <?php echo $h($f['plantilla_baja']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?php echo $h($roles[$f['plantilla_rol']] ?? '—'); ?></td>
                                <td class="text-center"><?php echo $f['plantilla_dorsal'] !== null
                                        ? (int)$f['plantilla_dorsal'] : '—'; ?></td>
                                <td class="text-center">
                                    <?php echo $f['persona_fechanac'] ? (int)$f['edad'] : '—'; ?>
                                </td>
                                <td>
                                    <?php if ($f['plantilla_habilitado'] === 'S' && !$baja): ?>
                                        <span class="badge text-bg-success">Habilitado</span>
                                    <?php elseif ($faltas): ?>
                                        <span class="badge text-bg-danger" title="<?php echo $h(implode('; ', $faltas)); ?>">
                                            No cumple
                                        </span>
                                        <br><small class="text-danger"><?php echo $h(implode('; ', $faltas)); ?></small>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php /* Sólo tiene sentido preguntar por la
                                             autorización si hay fotografía. */ ?>
                                    <?php if (empty($f['persona_foto'])): ?>
                                        <span class="text-muted" style="font-size:.82rem;">sin foto</span>
                                    <?php else:
                                        $autorizada = ($f['persona_publicarfoto'] ?? 'N') === 'S'; ?>
                                        <?php if (!$baja && puede_editar('plantillaPanel')): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-<?php echo $autorizada ? 'success' : 'outline-secondary'; ?> js-consent"
                                                    data-id="<?php echo (int)$f['persona_id']; ?>"
                                                    data-nombre="<?php echo $h($f['persona_apellidos'] . ' ' . $f['persona_nombres']); ?>"
                                                    data-auth="<?php echo $autorizada ? 'S' : 'N'; ?>"
                                                    title="<?php echo $autorizada
                                                        ? 'Autorizada el ' . $h((string)$f['persona_consentfecha'])
                                                        : 'Sin autorización: en el portal se muestran las iniciales'; ?>">
                                                <i class="fas fa-<?php echo $autorizada ? 'eye' : 'eye-slash'; ?> me-1"></i>
                                                <?php echo $autorizada ? 'Autorizada' : 'Reservada'; ?>
                                            </button>
                                        <?php else: ?>
                                            <span class="badge text-bg-<?php echo $autorizada ? 'success' : 'secondary'; ?>">
                                                <?php echo $autorizada ? 'Autorizada' : 'Reservada'; ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="ds-tabla-acciones">
                                    <?php if (!$baja && puede_editar('plantillaPanel')): ?>
                                        <?php if ($f['plantilla_habilitado'] === 'S'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-habilitar"
                                                    data-id="<?php echo (int)$f['plantilla_id']; ?>" data-valor="N"
                                                    title="Retirar la habilitación"><i class="fas fa-times"></i></button>
                                        <?php elseif ($puede): ?>
                                            <button type="button" class="btn btn-sm btn-actualizar js-habilitar"
                                                    data-id="<?php echo (int)$f['plantilla_id']; ?>" data-valor="S"
                                                    title="Habilitar"><i class="fas fa-check"></i></button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if (!$baja && puede_eliminar('plantillaPanel')): ?>
                                        <button type="button" class="btn btn-sm btn-danger js-baja"
                                                data-id="<?php echo (int)$f['plantilla_id']; ?>"
                                                data-nombre="<?php echo $h($f['persona_apellidos'] . ' ' . $f['persona_nombres']); ?>"
                                                title="Dar de baja"><i class="fas fa-user-slash"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer text-muted" style="font-size:.85rem;">
                Una baja no borra la fila: guarda la fecha. Los partidos ya jugados conservan
                quién estaba habilitado ese día, que es lo que se consulta cuando se impugna
                una alineación.
            </div>
        </div>
    </div>

    <!-- ==================== Alta ==================== -->
    <div class="col-lg-4 mb-3">
        <?php if (puede_crear('plantillaPanel')): ?>
        <form class="FormularioAjax" method="POST" autocomplete="off"
              enctype="multipart/form-data"
              action="<?php echo APP_URL; ?>ajax/leagueAjax.php" id="formFila">
            <input type="hidden" name="modulo_league" value="guardarPersona">
            <input type="hidden" name="persona_id" id="persona_id" value="0">
            <input type="hidden" name="inscripcion_id" value="<?php echo $inscripcionId; ?>">

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" id="tituloForm">
                        <i class="fas fa-user-plus me-2"></i>Añadir a la plantilla
                    </h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="persona_identificacion">Identificación</label>
                        <input type="text" name="persona_identificacion" id="persona_identificacion"
                               class="form-control" maxlength="20" required>
                        <small class="form-text text-muted">
                            Si ya está registrada, se reutiliza su ficha en lugar de duplicarla.
                        </small>
                    </div>
                    <div class="mb-3">
                        <label for="persona_apellidos">Apellidos</label>
                        <input type="text" name="persona_apellidos" id="persona_apellidos"
                               class="form-control" maxlength="150" required>
                    </div>
                    <div class="mb-3">
                        <label for="persona_nombres">Nombres</label>
                        <input type="text" name="persona_nombres" id="persona_nombres"
                               class="form-control" maxlength="150" required>
                    </div>
                    <div class="row g-2">
                        <div class="mb-3 col-7">
                            <label for="persona_fechanac">Nacimiento</label>
                            <input type="date" name="persona_fechanac" id="persona_fechanac"
                                   class="form-control" max="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="mb-3 col-5">
                            <label for="persona_genero">Género</label>
                            <select name="persona_genero" id="persona_genero" class="form-select">
                                <option value="M">Masculino</option>
                                <option value="F">Femenino</option>
                                <option value="X">Sin especificar</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="mb-3 col-7">
                            <label for="plantilla_rol">Rol</label>
                            <select name="plantilla_rol" id="plantilla_rol" class="form-select">
                                <?php foreach ($roles as $k => $v): ?>
                                    <option value="<?php echo $k; ?>"><?php echo $h($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 col-5">
                            <label for="plantilla_dorsal">Dorsal</label>
                            <input type="number" name="plantilla_dorsal" id="plantilla_dorsal"
                                   class="form-control" min="0" max="99">
                        </div>
                    </div>
                    <div class="mb-3 mb-0">
                        <label for="persona_foto">Fotografía</label>
                        <input type="file" name="persona_foto" id="persona_foto"
                               class="form-control" accept="image/jpeg,image/png,image/webp">
                        <small class="form-text text-muted">
                            Para el carné. JPG, PNG o WEBP, hasta 2 MB.
                        </small>
                    </div>
                </div>
                <?php echo ds_acciones_form('', ['limpiar' => true, 'guardar' => 'Añadir']); ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<script>
/* Habilitar, retirar habilitación y dar de baja.
   El servidor vuelve a comprobar los requisitos: aquí sólo se evita el
   viaje cuando ya se sabe que no cumple. */
(function () {
    var url = '<?php echo APP_URL; ?>ajax/leagueAjax.php';

    var enviar = function (campos) {
        var fd = new FormData();
        for (var k in campos) { fd.append(k, campos[k]); }
        fetch(url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                Swal.fire({ icon: j.icono, title: j.titulo, text: j.texto })
                    .then(function () { if (j.tipo === 'recargar') { location.reload(); } });
            })
            .catch(function () {
                Swal.fire({ icon: 'error', title: 'Sin respuesta',
                            text: 'No se pudo contactar con el servidor.' });
            });
    };

    document.querySelectorAll('.js-habilitar').forEach(function (b) {
        b.addEventListener('click', function () {
            enviar({ modulo_league: 'habilitarPlantilla',
                     plantilla_id: b.getAttribute('data-id'),
                     habilitar:    b.getAttribute('data-valor') });
        });
    });

    /* Autorización de imagen.
       Se confirma con el texto completo porque autorizar la publicación de
       la fotografía de un menor en un sitio abierto no es marcar una
       casilla: tiene consecuencias y quien lo hace debe saber cuáles. */
    document.querySelectorAll('.js-consent').forEach(function (b) {
        b.addEventListener('click', function () {
            var auth = b.getAttribute('data-auth') === 'S';
            var nom  = b.getAttribute('data-nombre');

            Swal.fire({
                icon:  auth ? 'question' : 'warning',
                title: auth ? '¿Retirar la autorización?' : '¿Autorizar la publicación?',
                html:  auth
                    ? 'La fotografía de <b>' + nom + '</b> dejará de mostrarse en el portal '
                      + 'público. En su lugar se mostrarán las iniciales.'
                    : '<div style="text-align:left">La fotografía de <b>' + nom + '</b> pasará '
                      + 'a ser visible para cualquiera en el portal público.<br><br>'
                      + 'Si es menor de edad, esta autorización debe provenir de quien ejerce '
                      + 'su representación legal. Queda registrado quién y cuándo la concedió.</div>',
                showCancelButton:  true,
                confirmButtonText: auth ? 'Retirar' : 'Autorizar',
                cancelButtonText:  'Cancelar',
                confirmButtonColor: auth ? '#6c757d' : '#28a745'
            }).then(function (r) {
                if (r.isConfirmed) {
                    enviar({ modulo_league: 'consentimientoImagen',
                             persona_id: b.getAttribute('data-id'),
                             autoriza:   auth ? 'N' : 'S' });
                }
            });
        });
    });
    document.querySelectorAll('.js-baja').forEach(function (b) {
        b.addEventListener('click', function () {
            Swal.fire({
                title: 'Dar de baja a ' + b.getAttribute('data-nombre'),
                text:  'Dejará la plantilla. Los partidos ya jugados conservan su alineación.',
                input: 'text',
                inputLabel: 'Motivo (opcional)',
                showCancelButton: true,
                confirmButtonText: 'Dar de baja',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc3545'
            }).then(function (r) {
                if (r.isConfirmed) {
                    enviar({ modulo_league: 'bajaPlantilla',
                             plantilla_id: b.getAttribute('data-id'),
                             motivo: r.value || '' });
                }
            });
        });
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
