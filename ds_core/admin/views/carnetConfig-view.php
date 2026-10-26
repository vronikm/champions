<?php
/*
| Configuración de carnets: color de cada mes y política de reimpresión.
|
| Vive en Core por el mismo criterio que la facturación: es configuración
| del sistema, no operación diaria. Sólo el superadministrador entra.
|
| El color del mes es una decisión con efectos irreversibles: en cuanto se
| emite el primer carnet de un mes, ese mes queda bloqueado, porque
| cambiarlo dejaría en circulación carnets de un color que ya no coincide.
|
| Los cambios se guardan contra el módulo Basketball, que es donde está la
| lógica de validación y el histórico de carnets emitidos.
*/

use admin\controllers\coreController;

if (!es_superadministrador()) {
    http_response_code(403);
    require_once __DIR__ . "/accesoDenegado-view.php";
    exit();
}

$insCore = new coreController();

$meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
          5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
          9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];

/*
| EL AÑO QUE SE ESTÁ CONFIGURANDO
|
| Desde la migración 062 la asignación mes→color es por año. Antes era una
| sola para toda la historia, así que en cuanto abril de 2026 tuvo su primer
| carnet, abril quedaba bloqueado también para 2027 y los siguientes: la
| escuela no podía estrenar paleta nunca más.
|
| Se puede configurar el año EN CURSO y el SIGUIENTE. Preparar la paleta del
| próximo año por adelantado es el caso de uso; reescribir un año pasado
| sería falsear el color de carnets ya impresos, así que esos se ven pero no
| se tocan.
*/
$anioActual = (int) date('Y');
$anioMax    = $anioActual + 1;
$anioSel    = isset($_GET['anio']) ? (int) $_GET['anio'] : $anioActual;

/* Se acota contra los años que existen: una URL a mano no debe poder
   producir una pantalla en blanco de un año inventado. */
$aniosConDatos = $insCore->aniosCarnetConfigurados();
$aniosOfrecidos = array_values(array_unique(array_merge(
    $aniosConDatos, [$anioActual, $anioMax])));
sort($aniosOfrecidos);

if (!in_array($anioSel, $aniosOfrecidos, true)) { $anioSel = $anioActual; }

$anioEditable = $anioSel >= $anioActual && $anioSel <= $anioMax;

$porMes   = $insCore->coloresCarnetPorMes($anioSel);
$colores  = $insCore->catalogoColoresCarnet();
$politica = $insCore->configuracionCarnet();

$tituloVista = 'Carnets';
$vistaActual = 'carnetConfig';

/* La validación y el guardado siguen en Basketball. */
$ajaxCarnet = DS_FUTBOL_URL . 'app/ajax/carnetAjax.php';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$bloqueados = 0;
foreach ($porMes as $m) { if ($m['bloqueado']) { $bloqueados++; } }

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="aviso-superadmin mb-3">
    <i class="fas fa-shield-alt fa-lg mt-1"></i>
    <div>
        <strong>Sólo el superadministrador.</strong><br>
        Un mes queda bloqueado en cuanto se emite su primer carnet: cambiarle el color
        dejaría en circulación carnets que ya no coinciden con el sistema.
        Imprimir carnets es otra cosa y se concede por rol sobre
        «Carnets del mes» en <a href="<?php echo APP_URL; ?>permisoRol/">Permisos</a>.
    </div>
</div>

<?php
/*==================================================================
  DISEÑO DEL CARNET

  Qué lleva el carnet y con qué medidas (ds_core/inc/carnet_diseno.php) y
  sus dos imágenes (la de la franja y la línea decorativa). Lo usan los dos
  PDF de Fútbol a través de app/lib/carnet_render.php.

  La vista previa es HTML medido en milímetros (85,6 × 53,98 mm, escalado
  con unidades de contenedor) y se redibuja con cada cambio, sin guardar.
  «PDF de muestra» lo dibuja el renderizador real con lo GUARDADO: lo que
  se ve en ese PDF es exactamente lo que se imprime.
  ==================================================================*/
$con          = seguridad_conexion();
$diseno       = $con ? carnet_diseno_leer($con) : carnet_diseno_defecto();
$disenoDef    = carnet_diseno_defecto();
$rangos       = carnet_diseno_rangos();
$imgCarnet    = $insCore->imagenesCarnet();
$urlImgCarnet = DS_FUTBOL_URL . 'app/views/imagenes/carnet/';
$mesHoy       = (int)date('n');
$colorHoy     = $insCore->coloresCarnetPorMes($anioActual)[$mesHoy]['color_hex'] ?? '#CCCCCC';
$disenoCambiado = $diseno != $disenoDef;
$sedeEjemplo  = $con ? (string)$con->query("SELECT sede_nombre FROM general_sede ORDER BY sede_id LIMIT 1")->fetchColumn() : '';

/* Interruptores de la sección: [clave, rótulo, ayuda]. */
$camposVisibles = [
    ['ci', 'Cédula', ''], ['horario', 'Horario', ''], ['edad', 'Edad', ''], ['sede', 'Sede', ''],
    ['correo', 'Correo de la sede', ''], ['telefono', 'Teléfono de la sede', ''], ['fecha', 'Fecha de impresión', 'Abajo a la derecha'],
];
?>
<div class="card ds-cdiseno" id="disenoCarnet">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-id-card me-2" aria-hidden="true"></i>Diseño del carnet</h3>
        <span class="badge text-bg-warning ms-auto" id="cdSinGuardar" hidden>Cambios sin guardar</span>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <!-- Vista previa: arriba y fija mientras se ajusta en el teléfono -->
            <div class="col-lg-6 order-lg-2 ds-cdiseno__col-previa">
                <div class="ds-cdiseno__previa">
                    <div class="ds-cdiseno__marco">
                        <div class="ds-cdiseno__carnet" id="cdCarnet" role="img"
                             aria-label="Vista previa del carnet con datos de ejemplo"
                             data-color="<?php echo $h($colorHoy); ?>"
                             data-mes="<?php echo $h(mb_strtoupper($meses[$mesHoy], 'UTF-8')); ?>"
                             data-sede="<?php echo $h($sedeEjemplo); ?>"
                             data-correo="<?php echo $h(ds_email_organizacion()); ?>"
                             data-telefono="<?php echo $h(ds_telefono_organizacion()); ?>">
                            <img class="cd-fondo" alt="" src="<?php echo $h($urlImgCarnet . $imgCarnet['fondo']); ?>">
                            <span class="cd-velo"></span>
                            <img class="cd-linea" alt="" src="<?php echo $h($urlImgCarnet . $imgCarnet['linea']); ?>">
                            <img class="cd-logo" alt="" src="<?php echo $h(ds_logo_url()); ?>">
                            <span class="cd-qr" aria-hidden="true"><i class="fas fa-qrcode"></i></span>
                            <span class="cd-foto" aria-hidden="true"><i class="fas fa-user"></i></span>
                            <div class="cd-textos"></div>
                        </div>
                    </div>
                    <p class="small text-body-secondary mt-2 mb-0">
                        Vista previa con datos de ejemplo y el color de <?php echo $h(mb_strtolower($meses[$mesHoy], 'UTF-8')); ?>.
                        <a href="<?php echo $h($ajaxCarnet . '?modulo_carnet=muestra_pdf'); ?>" target="_blank" rel="noopener" id="cdMuestra">
                            <i class="fas fa-file-pdf me-1" aria-hidden="true"></i>PDF de muestra</a>
                        <span class="d-block">El PDF usa el diseño <strong>guardado</strong>: es exactamente lo que se imprime.</span>
                    </p>
                </div>
            </div>

            <!-- Ajustes -->
            <div class="col-lg-6 order-lg-1">
                <h4 class="ds-cdiseno__grupo">Imágenes</h4>
                <div class="ds-cdiseno__imagenes">
                    <?php foreach (['fondo' => ['Franja lateral', 'Vertical; se recorta a la franja.'], 'linea' => ['Línea decorativa', 'Una tira vertical fina.']] as $tipo => [$rotulo, $ayuda]): ?>
                        <div class="ds-cdiseno__imagen">
                            <img src="<?php echo $h($urlImgCarnet . $imgCarnet[$tipo]); ?>" alt="<?php echo $h($rotulo); ?> actual">
                            <div class="min-w-0">
                                <strong class="d-block"><?php echo $rotulo; ?></strong>
                                <span class="small text-body-secondary d-block"><?php echo $ayuda; ?></span>
                                <div class="ds-cdiseno__imagen-botones">
                                    <form class="FormularioAjax" method="POST" enctype="multipart/form-data" action="<?php echo $ajaxCarnet; ?>">
                                        <input type="hidden" name="modulo_carnet" value="subir_imagen_carnet">
                                        <input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                                        <label class="btn btn-sm btn-primary mb-0">
                                            <i class="fas fa-upload me-1" aria-hidden="true"></i>Cambiar
                                            <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" class="visually-hidden" data-subir-al-elegir>
                                        </label>
                                    </form>
                                    <?php if (!str_starts_with((string)$imgCarnet[$tipo], 'vertical_')): ?>
                                        <form class="FormularioAjax" method="POST" action="<?php echo $ajaxCarnet; ?>"
                                              data-confirmar="Se volverá a usar la imagen original.">
                                            <input type="hidden" name="modulo_carnet" value="restaurar_imagen_carnet">
                                            <input type="hidden" name="tipo" value="<?php echo $tipo; ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary"><i class="fas fa-undo me-1" aria-hidden="true"></i>Original</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="ds-cdiseno__imagen">
                        <img src="<?php echo $h(ds_logo_url()); ?>" alt="Logo actual">
                        <div class="min-w-0">
                            <strong class="d-block">Logo</strong>
                            <span class="small text-body-secondary d-block">El de la organización o el de cada sede.</span>
                            <a class="btn btn-sm btn-secondary mt-1" href="<?php echo APP_URL; ?>organizacionForm/"><i class="fas fa-building me-1" aria-hidden="true"></i>Cambiar en Organización</a>
                        </div>
                    </div>
                </div>

                <form class="FormularioAjax" method="POST" action="<?php echo $ajaxCarnet; ?>" id="formDiseno" autocomplete="off">
                    <input type="hidden" name="modulo_carnet" value="guardar_diseno">

                    <h4 class="ds-cdiseno__grupo">Franja lateral</h4>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="cd_franja_imagen" name="diseno[franja_imagen]" value="1" <?php echo $diseno['franja_imagen'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cd_franja_imagen">Imagen de fondo</label>
                    </div>
                    <?php foreach (['franja_ancho' => ['Ancho', 'mm'], 'franja_opacidad' => ['Color del mes encima', '%']] as $clave => [$rotulo, $unidad]): ?>
                        <div class="ds-cdiseno__rango">
                            <label for="cd_<?php echo $clave; ?>" class="form-label mb-0"><?php echo $rotulo; ?></label>
                            <output for="cd_<?php echo $clave; ?>" id="cd_<?php echo $clave; ?>_v"><?php echo (int)$diseno[$clave] . ' ' . $unidad; ?></output>
                            <input type="range" class="form-range" id="cd_<?php echo $clave; ?>" name="diseno[<?php echo $clave; ?>]"
                                   min="<?php echo $rangos[$clave][0]; ?>" max="<?php echo $rangos[$clave][1]; ?>" step="1"
                                   value="<?php echo (int)$diseno[$clave]; ?>" data-unidad="<?php echo $unidad; ?>">
                        </div>
                    <?php endforeach; ?>

                    <h4 class="ds-cdiseno__grupo">Logo, QR y línea</h4>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="cd_logo" name="diseno[logo]" value="1" <?php echo $diseno['logo'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cd_logo">Logo</label>
                    </div>
                    <div class="ds-cdiseno__rango">
                        <label for="cd_logo_tamano" class="form-label mb-0">Tamaño del logo</label>
                        <output for="cd_logo_tamano" id="cd_logo_tamano_v"><?php echo (int)$diseno['logo_tamano']; ?> mm</output>
                        <input type="range" class="form-range" id="cd_logo_tamano" name="diseno[logo_tamano]"
                               min="<?php echo $rangos['logo_tamano'][0]; ?>" max="<?php echo $rangos['logo_tamano'][1]; ?>" step="1"
                               value="<?php echo (int)$diseno['logo_tamano']; ?>" data-unidad="mm">
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="cd_qr" name="diseno[qr]" value="1" <?php echo $diseno['qr'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cd_qr">Código QR <span class="small text-body-secondary">(estado de la pensión)</span></label>
                    </div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="cd_linea" name="diseno[linea]" value="1" <?php echo $diseno['linea'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cd_linea">Línea decorativa</label>
                    </div>

                    <h4 class="ds-cdiseno__grupo">Textos</h4>
                    <div class="mb-3">
                        <label for="cd_titulo" class="form-label">Título sobre el nombre</label>
                        <input type="text" class="form-control" id="cd_titulo" name="diseno[titulo]" maxlength="24"
                               value="<?php echo $h($diseno['titulo']); ?>" placeholder="DEPORTISTA">
                    </div>
                    <div class="ds-cdiseno__campos">
                        <?php foreach ($camposVisibles as [$clave, $rotulo, $ayuda]): ?>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="cd_<?php echo $clave; ?>" name="diseno[<?php echo $clave; ?>]" value="1" <?php echo $diseno[$clave] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="cd_<?php echo $clave; ?>"><?php echo $rotulo; ?></label>
                            </div>
                        <?php endforeach; ?>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="cd_mes" checked disabled>
                            <label class="form-check-label" for="cd_mes">Mes de vigencia <span class="small text-body-secondary">(siempre)</span></label>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="card-footer ds-acciones ds-cdiseno__acciones">
        <?php if ($disenoCambiado): ?>
            <form class="FormularioAjax me-auto" method="POST" action="<?php echo $ajaxCarnet; ?>"
                  data-confirmar="El carnet volverá a su diseño de siempre (las imágenes no cambian).">
                <input type="hidden" name="modulo_carnet" value="restaurar_diseno">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-undo me-1" aria-hidden="true"></i>Diseño original</button>
            </form>
        <?php endif; ?>
        <button type="submit" form="formDiseno" class="btn btn-primary"><i class="fas fa-save me-1" aria-hidden="true"></i>Guardar diseño</button>
    </div>
</div>

<form class="FormularioAjax" method="POST" action="<?php echo $ajaxCarnet; ?>" autocomplete="off">
    <input type="hidden" name="modulo_carnet" value="actualizar_colores">
    <?php /* El anio viaja con el formulario: sin el, guardar desde la
             pantalla de 2027 escribiria sobre 2026. */ ?>
    <input type="hidden" name="anio" value="<?php echo (int)$anioSel; ?>">

    <div class="row">
        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-money-check-alt me-2"></i>Reimpresión</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size:.9rem;">
                        Cuando está activo, reponer un carnet extraviado genera el cobro configurado.
                    </p>

                    <!-- El campo oculto asegura que llegue un 0 si no se marca -->
                    <input type="hidden" name="cobrar_reimpresion" value="0">
                    <?php /* Bootstrap 5 sustituye custom-control/custom-switch
                             por form-check form-switch, y las clases internas
                             pasan a form-check-input y form-check-label.
                             font-weight-bold es ahora fw-bold. */ ?>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox" class="form-check-input" role="switch"
                               id="cobrar_reimpresion" name="cobrar_reimpresion" value="1"
                               <?php echo $politica['cobrar'] ? 'checked' : ''; ?>>
                        <label class="form-check-label fw-bold" for="cobrar_reimpresion">
                            Cobrar la reimpresión
                        </label>
                    </div>

                    <div class="mb-3 mb-0">
                        <label for="valor_reimpresion">Valor del rubro</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control text-end" id="valor_reimpresion"
                                   name="valor_reimpresion" min="0.01" step="0.01" required
                                   value="<?php echo number_format($politica['valor'], 2, '.', ''); ?>">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-palette me-2"></i>Estado</h3>
                </div>
                <div class="card-body">
                    <dl class="row mb-0" style="font-size:.9rem;">
                        <dt class="col-7 text-muted font-weight-normal">Meses bloqueados</dt>
                        <dd class="col-5"><strong><?php echo $bloqueados; ?></strong> de 12</dd>
                        <dt class="col-7 text-muted font-weight-normal">Colores en catálogo</dt>
                        <dd class="col-5"><strong><?php echo count($colores); ?></strong></dd>
                    </dl>
                    <?php if (!$colores): ?>
                        <p class="text-warning mb-0 mt-2" style="font-size:.9rem;">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            No hay colores activos en el catálogo.
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Color por mes
                    </h3>
                    <?php
                    /* El selector va por GET y recarga: cambiar de año cambia
                       la pantalla entera —asignaciones, bloqueos y catálogo—,
                       así que traerla de nuevo es lo honesto y lo simple. */
                    ?>
                    <div class="d-flex align-items-center gap-2">
                        <label for="anioSel" class="small text-body-secondary mb-0">Año</label>
                        <select id="anioSel" class="form-select form-select-sm w-auto"
                                onchange="location.href='?anio=' + this.value">
                            <?php foreach ($aniosOfrecidos as $a): ?>
                                <option value="<?php echo (int)$a; ?>"
                                    <?php echo $a === $anioSel ? 'selected' : ''; ?>>
                                    <?php echo (int)$a; ?><?php
                                        echo $a === $anioActual ? ' (actual)'
                                             : ($a > $anioActual ? ' (próximo)' : ''); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <?php if (!$anioEditable): ?>
                <div class="card-body py-2 border-bottom small">
                    <i class="fas fa-lock me-1" aria-hidden="true"></i>
                    <strong><?php echo (int)$anioSel; ?> ya pasó.</strong>
                    Se muestra cómo se imprimió, y no se puede cambiar: estos tonos
                    están en los carnets que circularon ese año.
                </div>
                <?php endif; ?>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 ds-colormes">
                            <thead>
                                <tr>
                                    <th style="width:38%">Mes</th>
                                    <th>Color</th>
                                    <th style="width:80px">Muestra</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($meses as $num => $nombre):
                                $datos    = $porMes[$num];
                                $bloqueado = $datos['bloqueado']; ?>
                                <tr>
                                    <td>
                                        <?php echo $nombre; ?>
                                        <?php if ($bloqueado): ?>
                                            <span class="badge text-bg-warning ms-1">
                                                <i class="fas fa-lock"></i> Bloqueado
                                            </span>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $datos['total_carnets']; ?> carnet(s) emitido(s)
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php /* Un año pasado se consulta, no se edita: todos
                                                 sus meses van deshabilitados aunque no tengan
                                                 carnets. El servidor lo vuelve a comprobar. */ ?>
                                        <select class="form-select color-mes" data-mes="<?php echo $num; ?>"
                                                name="color_mes[<?php echo $num; ?>]"
                                                <?php echo ($bloqueado || !$anioEditable) ? 'disabled' : ''; ?>>
                                            <option value="0" data-color="#FFFFFF">— Sin asignar —</option>
                                            <?php foreach ($colores as $c):
                                                /* Un color sólo puede estar en un mes: se ofrece si
                                                   está libre o si ya es el de este mes. */
                                                $esActual = (int)$c['catcolor_id'] === $datos['color_id'];
                                                $libre    = (int)$c['asignado_a'] === 0 || $esActual; ?>
                                                <option value="<?php echo (int)$c['catcolor_id']; ?>"
                                                        data-color="<?php echo $h($c['catcolor_hex']); ?>"
                                                        <?php echo $esActual ? 'selected' : ''; ?>
                                                        <?php echo $libre ? '' : 'disabled'; ?>>
                                                    <?php echo $h($c['catcolor_nombre']); ?><?php echo $libre ? '' : ' (ya asignado)'; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if ($bloqueado): ?>
                                            <!-- Un select deshabilitado no se envía: el valor viaja aparte -->
                                            <input type="hidden" name="color_mes[<?php echo $num; ?>]"
                                                   value="<?php echo $datos['color_id']; ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="muestra-color" data-mes="<?php echo $num; ?>"
                                              style="display:block;height:28px;border-radius:var(--ds-radius-sm);
                                                     border:1px solid var(--core-borde);
                                                     background-color:<?php echo $h($datos['color_hex']); ?>"></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php echo ds_acciones_form(APP_URL . 'panel/', ['guardar' => 'Guardar']); ?>
            </div>
        </div>
    </div>
</form>

<?php
/*==================================================================
  EL CATÁLOGO DE COLORES

  Hasta ahora los colores sólo se podían ASIGNAR: el catálogo venía
  sembrado y no había forma de añadir uno ni de corregir un tono desde la
  pantalla. Con doce meses y un color por mes, quedarse sin tonos
  distinguibles o heredar uno mal elegido obligaba a tocar la base a mano.

  LA REGLA QUE MANDA

  Un color con carnets impresos NO se toca. alumno_carnet guarda mes y año
  pero NO el color con el que se imprimió: el color se deduce de la
  asignación vigente. Cambiar el tono de un color que ya circula no
  «actualiza» nada —deja en la calle carnets de un tono que el sistema ya
  no reconoce como suyo—.

  Por eso no es una restricción de esta pantalla sino del modelo, y el
  servidor la vuelve a comprobar: aquí el campo se deshabilita, pero una
  petición se arma a mano.

  CADA FORMULARIO VA APARTE, Y FUERA DEL DE ARRIBA

  Un <form> dentro de otro es HTML inválido y el navegador saca el interior,
  dejando sus campos huérfanos. Por eso este bloque empieza donde el
  anterior ya cerró.

  LA LECTURA SALE DE CORE, LA ESCRITURA VA A BASKETBALL

  El primer intento instanció aquí carnetController, de Basketball, y falló:
  «Class app\controllers\carnetController not found». El cargador de Core no
  llega, y que no llegue es correcto —un módulo que instancia el controlador
  de otro deja de tener frontera—.

  Esta pantalla lleva desde el principio leyendo de Core y enviando a
  carnetAjax.php de Basketball. Se mantiene ese reparto.
  ==================================================================*/
$catalogo = $insCore->catalogoColoresCarnetAdmin($anioSel);

$editables = 0;
foreach ($catalogo as $c) { if ($c['editable']) { $editables++; } }
?>

<div class="card mt-3">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <h3 class="card-title mb-0">
            <i class="fas fa-palette me-2"></i>Catálogo de colores
        </h3>
        <span class="small text-body-secondary">
            <?php echo count($catalogo); ?> activos ·
            <?php echo $editables; ?> editables ·
            <?php echo count($catalogo) - $editables; ?> en circulación
        </span>
    </div>

    <div class="card-body">
        <p class="text-body-secondary" style="font-size:.9rem;">
            Un color queda <strong>bloqueado</strong> en cuanto se imprime el primer carnet
            del mes al que está asignado. Los carnets ya emitidos llevan ese tono, y
            cambiarlo dejaría en circulación tarjetas que el sistema ya no reconocería.
            Para un tono distinto, agregue un color nuevo y asígnelo a un mes libre.
        </p>

        <!-- ==================== Alta ==================== -->
        <form class="FormularioAjax row g-2 align-items-end mb-3" method="POST"
              action="<?php echo $ajaxCarnet; ?>" autocomplete="off">
            <input type="hidden" name="modulo_carnet" value="crear_color">

            <div class="col-12 col-md-5">
                <label class="form-label small mb-1" for="nuevo_nombre">Nombre del color</label>
                <input type="text" class="form-control" id="nuevo_nombre" name="color_nombre"
                       maxlength="50" required placeholder="Ej.: Índigo">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small mb-1" for="nuevo_hex">Tono</label>
                <?php /* El selector nativo y el texto van sincronizados: uno se
                         elige a ojo y el otro se pega desde una guía de marca. */ ?>
                <div class="input-group">
                    <input type="color" class="form-control form-control-color js-hex-visual"
                           id="nuevo_hex_visual" value="#1B4F72" aria-label="Elegir tono">
                    <input type="text" class="form-control js-hex-texto" id="nuevo_hex"
                           name="color_hex" value="#1B4F72" maxlength="7"
                           pattern="#[0-9A-Fa-f]{6}" required>
                </div>
            </div>
            <div class="col-6 col-md-4 d-grid">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i>Agregar color
                </button>
            </div>
        </form>

        <!-- ==================== El catálogo ==================== -->
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:3rem;">Tono</th>
                        <th>Nombre</th>
                        <th class="d-none d-md-table-cell">Mes</th>
                        <th class="text-end d-none d-sm-table-cell">Carnets</th>
                        <th class="text-end" style="width:14rem;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($catalogo as $c): ?>
                    <tr>
                        <td>
                            <span class="d-inline-block rounded border"
                                  style="width:1.75rem;height:1.75rem;background:<?php echo $h($c['hex']); ?>;"
                                  title="<?php echo $h($c['hex']); ?>"></span>
                        </td>
                        <td>
                            <span class="fw-semibold"><?php echo $h($c['nombre']); ?></span>
                            <span class="d-block small text-body-secondary font-monospace">
                                <?php echo $h($c['hex']); ?>
                            </span>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?php if ($c['mes'] > 0): ?>
                                <?php echo $h($c['mes_nombre']); ?>
                            <?php else: ?>
                                <span class="text-body-secondary">sin asignar</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end d-none d-sm-table-cell font-monospace">
                            <?php echo $c['carnets'] > 0 ? (int)$c['carnets'] : '—'; ?>
                        </td>
                        <td class="text-end">
                            <?php if ($c['editable']): ?>
                                <button type="button" class="btn btn-sm btn-outline-primary js-editar-color"
                                        data-id="<?php echo (int)$c['id']; ?>"
                                        data-nombre="<?php echo $h($c['nombre']); ?>"
                                        data-hex="<?php echo $h($c['hex']); ?>">
                                    <i class="fas fa-pen me-1"></i>Editar
                                </button>
                                <?php if ($c['retirable']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger js-retirar-color"
                                            data-id="<?php echo (int)$c['id']; ?>"
                                            data-nombre="<?php echo $h($c['nombre']); ?>">
                                        <i class="fas fa-times"></i>
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php
                                /*
                                | El motivo es SIEMPRE el mismo desde que el tono
                                | depende sólo del histórico: hay carnets impresos
                                | con este color, de este año o de otro.
                                |
                                | Antes se decidía con los carnets del AÑO EN CURSO
                                | y al mirar 2027 —donde ese contador es cero para
                                | todos— cinco colores bloqueados se rotulaban «Mes
                                | congelado», que no era su motivo.
                                */
                                ?>
                                <span class="badge text-bg-secondary"
                                      title="<?php echo $h($c['motivo']); ?>">
                                    <i class="fas fa-lock me-1"></i>En circulación
                                </span>
                                <span class="d-block small text-body-secondary">
                                    <?php echo $h($c['motivo']); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
/* Los dos formularios de acción, uno por operación, fuera de la tabla:
   un <form> hijo de <tr> es HTML inválido. Se rellenan al pulsar. */
?>
<form class="FormularioAjax d-none" id="formEditarColor" method="POST"
      action="<?php echo $ajaxCarnet; ?>" autocomplete="off">
    <input type="hidden" name="modulo_carnet" value="editar_color">
    <input type="hidden" name="color_id"     id="edColorId"     value="">
    <input type="hidden" name="color_nombre" id="edColorNombre" value="">
    <input type="hidden" name="color_hex"    id="edColorHex"    value="">
</form>

<form class="FormularioAjax d-none" id="formRetirarColor" method="POST"
      action="<?php echo $ajaxCarnet; ?>" autocomplete="off"
      data-confirmar="El color dejará de ofrecerse para asignar a un mes.">
    <input type="hidden" name="modulo_carnet" value="retirar_color">
    <input type="hidden" name="color_id" id="reColorId" value="">
</form>

<script>
(function () {
    /* La muestra se pinta con el color que ya viaja en cada <option>:
       no hace falta preguntarle nada al servidor. */
    document.querySelectorAll('.color-mes').forEach(function (sel) {
        sel.addEventListener('change', function () {
            var opcion  = this.options[this.selectedIndex];
            var muestra = document.querySelector('.muestra-color[data-mes="' + this.dataset.mes + '"]');
            if (muestra) {
                muestra.style.backgroundColor = opcion.dataset.color || '#FFFFFF';
            }
        });
    });

    /*==============================================================
      CATÁLOGO DE COLORES
      ==============================================================*/

    /* El selector nativo y la casilla de texto son el mismo dato: uno se
       elige a ojo y en el otro se pega un valor de una guía de marca.
       Mantenerlos sincronizados evita que se envíe uno y se vea el otro. */
    function sincronizar(raiz) {
        var visual = raiz.querySelector('.js-hex-visual');
        var texto  = raiz.querySelector('.js-hex-texto');
        if (!visual || !texto) { return; }

        visual.addEventListener('input', function () {
            texto.value = visual.value.toUpperCase();
        });
        texto.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(texto.value)) { visual.value = texto.value; }
        });
    }
    document.querySelectorAll('form').forEach(sincronizar);

    /*----------  Editar  ----------*/
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.js-editar-color');
        if (!b) { return; }

        /* Un diálogo y no una fila editable: son dos campos y se usa de
           tarde en tarde; convertir la fila en formulario obligaría a
           duplicar los controles en las veinticuatro. */
        Swal.fire({
            title: 'Editar color',
            html:
                '<div class="text-start">' +
                '<label class="form-label small mb-1" for="swNombre">Nombre</label>' +
                '<input id="swNombre" class="form-control mb-2" maxlength="50" value="' +
                    b.dataset.nombre.replace(/"/g, '&quot;') + '">' +
                '<label class="form-label small mb-1" for="swHex">Tono</label>' +
                '<div class="input-group">' +
                '<input id="swHexVisual" type="color" class="form-control form-control-color" value="' +
                    b.dataset.hex + '">' +
                '<input id="swHex" class="form-control" maxlength="7" value="' + b.dataset.hex + '">' +
                '</div></div>',
            didOpen: function () {
                var v = document.getElementById('swHexVisual');
                var t = document.getElementById('swHex');
                v.addEventListener('input', function () { t.value = v.value.toUpperCase(); });
                t.addEventListener('input', function () {
                    if (/^#[0-9A-Fa-f]{6}$/.test(t.value)) { v.value = t.value; }
                });
            },
            showCancelButton: true,
            confirmButtonText: 'Guardar',
            cancelButtonText: 'Cancelar',
            preConfirm: function () {
                var nombre = document.getElementById('swNombre').value.trim();
                var hex    = document.getElementById('swHex').value.trim().toUpperCase();

                /* Se valida aquí sólo para no gastar un viaje; el servidor
                   lo vuelve a comprobar, que es donde cuenta. */
                if (!nombre) {
                    Swal.showValidationMessage('El nombre es obligatorio');
                    return false;
                }
                if (!/^#[0-9A-F]{6}$/.test(hex)) {
                    Swal.showValidationMessage('El tono debe ser #RRGGBB');
                    return false;
                }
                return { nombre: nombre, hex: hex };
            }
        }).then(function (r) {
            if (!r.isConfirmed) { return; }
            document.getElementById('edColorId').value     = b.dataset.id;
            document.getElementById('edColorNombre').value = r.value.nombre;
            document.getElementById('edColorHex').value    = r.value.hex;
            document.getElementById('formEditarColor').requestSubmit();
        });
    });

    /*----------  Retirar  ----------*/
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.js-retirar-color');
        if (!b) { return; }
        document.getElementById('reColorId').value = b.dataset.id;
        document.getElementById('formRetirarColor').requestSubmit();
    });
})();
</script>

<script>
/* =====================================================================
   Vista previa del diseño del carnet.

   Repite la geometría de ds_futbol/app/lib/carnet_render.php en mm
   (las mismas posiciones y los mismos saltos de línea) para que lo que se
   ajusta aquí sea lo que sale en el PDF. Se redibuja con cada cambio, sin
   guardar; «PDF de muestra» confirma con el renderizador real.
   ===================================================================== */
(function () {
    var carnet = document.getElementById('cdCarnet'), form = document.getElementById('formDiseno');
    if (!carnet || !form) { return; }
    var textos = carnet.querySelector('.cd-textos'), aviso = document.getElementById('cdSinGuardar');
    var inicial = new URLSearchParams(new FormData(form)).toString();
    var datos = carnet.dataset;
    var esc = function (t) { var d = document.createElement('span'); d.textContent = t; return d.innerHTML; };

    function valor(nombre) {
        var e = form.elements['diseno[' + nombre + ']'];
        if (!e) { return null; }
        return e.type === 'checkbox' ? e.checked : e.value;
    }
    /* Un texto en mm: x, y (arriba de la celda), alto de la celda, tamaño en pt. */
    function texto(t, x, y, alto, pt, extra) {
        return '<span class="cd-t' + (extra ? ' ' + extra : '') + '" style="--x:' + x + ';--y:' + y + ';--h:' + alto + ';--pt:' + pt + '">' + t + '</span>';
    }

    function dibujar() {
        var franja = +valor('franja_ancho'), info = franja + 2, y = 17, html = '';
        carnet.style.setProperty('--franja', franja);
        carnet.style.setProperty('--color', datos.color);
        carnet.style.setProperty('--opacidad', (+valor('franja_opacidad')) / 100);
        carnet.style.setProperty('--logo', +valor('logo_tamano'));
        carnet.classList.toggle('sin-fondo', !valor('franja_imagen'));
        carnet.classList.toggle('sin-linea', !valor('linea'));
        carnet.classList.toggle('sin-logo', !valor('logo'));
        carnet.classList.toggle('sin-qr', !valor('qr'));

        html += texto(esc((valor('titulo') || 'DEPORTISTA').toUpperCase()), info, y, 3, 6, 'cd-b');
        y += 4;
        /* «Nombre Apellido Ejemplo» pasa de 21 letras: dos líneas, como en el PDF. */
        html += texto('NOMBRE APELLIDO<br>EJEMPLO', info, y, 6, 10, 'cd-b cd-nombre');
        y += 6 + 1;
        var linea = function (rotulo, sangria, v, pt) {
            html += texto(rotulo, info, y, 2.5, 8) + texto(v, info + sangria, y, 2.5, pt || 8, 'cd-b');
            y += 3;
        };
        if (valor('ci'))      { linea('C.I.:', 8, '0000000000'); }
        if (valor('horario')) { linea('Horario:', 12, '16H00 PM'); }
        if (valor('edad'))    { linea('Edad:', 10, '10 años', 7.5); }
        html += texto('Mes vigencia:', info, y, 2.5, 8)
              + '<span class="cd-t cd-b cd-mes" style="--x:' + (info + 18) + ';--y:' + (y - 0.5) + ';--h:3;--pt:9;--w:20">' + esc(datos.mes) + '</span>';
        y += 3;
        if (valor('sede'))    { linea('Sede:', 10, esc(datos.sede)); }
        y += 0.5;
        if (valor('correo'))   { html += texto(esc(datos.correo), info, y, 2, 7, 'cd-gris'); y += 3; }
        if (valor('telefono')) { html += texto(esc(datos.telefono), info, y, 2, 7, 'cd-gris'); }
        if (valor('fecha'))    { html += '<span class="cd-t cd-fecha" style="--x:60.6;--y:49.48;--h:2;--pt:5;--w:22">Impreso: ' + new Date().toLocaleDateString('es-EC') + '</span>'; }
        html += '<span class="cd-pie" style="--x:' + info + '"></span>';
        textos.innerHTML = html;

        form.querySelectorAll('input[type="range"]').forEach(function (r) {
            var o = document.getElementById(r.id + '_v');
            if (o) { o.textContent = r.value + ' ' + r.dataset.unidad; }
        });
        if (aviso) { aviso.hidden = new URLSearchParams(new FormData(form)).toString() === inicial; }
    }

    form.addEventListener('input', dibujar);
    form.addEventListener('change', dibujar);
    dibujar();

    /* Cambiar una imagen: se envía al elegir el archivo. */
    document.querySelectorAll('[data-subir-al-elegir]').forEach(function (i) {
        i.addEventListener('change', function () { if (i.files.length) { i.form.requestSubmit(); } });
    });
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
