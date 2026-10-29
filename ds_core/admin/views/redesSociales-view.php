<?php
/*
| Redes sociales (migración 073).
|
| Perfiles de la escuela y la conexión con Meta —página de Facebook y cuenta
| profesional de Instagram— para publicar las felicitaciones de cumpleaños
| desde Fútbol › Cumpleaños. Por permiso de rol (Core › Permisos): el token publica en
| nombre de la escuela. Se guarda cifrado y no vuelve nunca a la pantalla.
|
| La lógica está en ds_core/inc/redes.php; esto sólo la presenta.
*/

use admin\controllers\coreController;

/* Por permisos de rol (Core › Permisos), como el resto de Core: «ver» para
   entrar; sin «editar», sólo lectura. Antes exigía ser superadministrador
   aunque el rol tuviera el permiso. */
if (!usuario_tiene_permiso('redesSociales')) {
    http_response_code(403);
    require_once __DIR__ . "/accesoDenegado-view.php";
    exit();
}
$puedeEditarConfig = puede_editar('redesSociales');

$insCore = new coreController();
$cfg = redes_config();
$publicaciones = $insCore->publicacionesRedes();
$conectado = $cfg['token_guardado'] && $cfg['fb_pagina_id'] !== '';

$tituloVista = 'Redes sociales';
$vistaActual = 'redesSociales';

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$si = static fn($v) => $v === 'S' ? 'checked' : '';

require_once __DIR__ . "/inc/layout-top.php";
?>

<div class="aviso-superadmin mb-3">
    <i class="fas fa-shield-alt fa-lg mt-1"></i>
    <div>
        <strong>Configuración sensible.</strong>
        <?php if (!$puedeEditarConfig): ?><span class="badge text-bg-secondary ms-1">Sólo lectura: su rol no puede editarla</span><?php endif; ?><br>
        El token de la página publica en nombre de la escuela. Publicar las felicitaciones se concede por rol
        sobre «Cumpleaños» en <a href="<?php echo APP_URL; ?>permisoRol/">Permisos</a>.
    </div>
</div>

<form class="FormularioAjax" method="POST" action="<?php echo APP_URL; ?>ajax/coreAjax.php" autocomplete="off" id="formRedes">
    <input type="hidden" name="modulo_core" value="guardarRedes">

    <div class="row g-3">
        <div class="col-lg-7">

            <!-- Publicar en Facebook e Instagram -->
            <div class="card ds-redes">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-paper-plane me-2" aria-hidden="true"></i>Publicar en Facebook e Instagram</h3>
                    <span class="badge <?php echo $conectado ? 'text-bg-success' : 'text-bg-secondary'; ?> ms-auto">
                        <?php echo $conectado
                            ? '<i class="fas fa-plug me-1" aria-hidden="true"></i>' . ($cfg['fb_pagina_nombre'] !== '' ? $h($cfg['fb_pagina_nombre']) : 'Token guardado')
                            : 'Sin conectar'; ?>
                    </span>
                </div>
                <div class="card-body">
                    <p class="text-muted small">
                        Con la página conectada, desde <strong>Fútbol › Cumpleaños</strong> se publica la tarjeta de felicitación
                        con un toque. Sólo se publica a los alumnos con la <strong>autorización de uso de imagen</strong> vigente
                        (consentimiento LOPDP); a los demás, el sistema no lo permite.
                    </p>

                    <h4 class="ds-redes__red"><i class="fab fa-facebook me-2" aria-hidden="true"></i>Facebook (página)</h4>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="fb_activo" name="fb_activo" value="S" <?php echo $si($cfg['fb_activo']); ?>>
                        <label class="form-check-label" for="fb_activo">Publicar en Facebook</label>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-5 mb-2">
                            <label for="fb_pagina_id" class="form-label">Id de la página</label>
                            <input type="text" class="form-control" id="fb_pagina_id" name="fb_pagina_id" inputmode="numeric" maxlength="40"
                                   value="<?php echo $h($cfg['fb_pagina_id']); ?>">
                        </div>
                        <div class="col-md-7 mb-2">
                            <label for="fb_token" class="form-label">Token de acceso de la página</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="fb_token" name="fb_token" autocomplete="new-password"
                                       placeholder="<?php echo $cfg['token_guardado'] ? 'Guardado; déjelo vacío para conservarlo' : 'Pegue aquí el token de la página'; ?>">
                                <button type="button" class="btn btn-outline-secondary" data-ver-clave="fb_token" title="Ver" aria-label="Ver el token"><i class="fas fa-eye"></i></button>
                            </div>
                            <?php if ($cfg['token_guardado']): ?>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="fb_token_quitar" name="fb_token_quitar" value="S">
                                    <label class="form-check-label small" for="fb_token_quitar">Quitar el token guardado</label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <h4 class="ds-redes__red"><i class="fab fa-instagram me-2" aria-hidden="true"></i>Instagram (cuenta profesional)</h4>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="ig_activo" name="ig_activo" value="S" <?php echo $si($cfg['ig_activo']); ?>>
                        <label class="form-check-label" for="ig_activo">Publicar en Instagram</label>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-5 mb-2">
                            <label for="ig_cuenta_id" class="form-label">Id de la cuenta</label>
                            <input type="text" class="form-control" id="ig_cuenta_id" name="ig_cuenta_id" inputmode="numeric" maxlength="40"
                                   value="<?php echo $h($cfg['ig_cuenta_id']); ?>">
                            <small class="text-muted">«Probar conexión» lo completa si la página la tiene vinculada.</small>
                        </div>
                        <div class="col-md-7 mb-2">
                            <div class="alert alert-light border small mb-0">
                                <i class="fas fa-info-circle me-1" aria-hidden="true"></i>
                                Instagram descarga la imagen desde internet: publica cuando el sistema está en línea
                                (https), no desde un servidor local. Usa el mismo token de la página vinculada.
                            </div>
                        </div>
                    </div>

                    <details class="ds-redes__ayuda mt-2">
                        <summary>Cómo obtener el id y el token de la página</summary>
                        <ol class="small mt-2 mb-0">
                            <li>En <strong>developers.facebook.com</strong>, cree una app de tipo «Empresa» y añada el producto <em>Facebook Login para empresas</em>.</li>
                            <li>En el <strong>Explorador de la Graph API</strong>, elija la app y pida los permisos
                                <code>pages_manage_posts</code>, <code>pages_read_engagement</code> y, para Instagram,
                                <code>instagram_basic</code> e <code>instagram_content_publish</code>.</li>
                            <li>Genere el token de <em>usuario</em>, cámbielo por uno de larga duración y consulte
                                <code>me/accounts</code>: ahí están el <strong>id</strong> de la página y su <strong>access_token</strong>.</li>
                            <li>Pegue ambos aquí, guarde y pulse «Probar conexión».</li>
                        </ol>
                    </details>

                    <div class="row g-2 mt-1">
                        <div class="col-6 col-md-3">
                            <label for="graph_version" class="form-label small">Versión de la API</label>
                            <input type="text" class="form-control form-control-sm" id="graph_version" name="graph_version" maxlength="10"
                                   pattern="v\d{1,2}\.\d" value="<?php echo $h($cfg['graph_version']); ?>">
                        </div>
                    </div>
                </div>
                <div class="card-footer ds-acciones">
                    <button type="submit" form="formProbarRedes" class="btn btn-secondary me-auto" <?php echo $conectado ? '' : 'disabled'; ?>>
                        <i class="fas fa-plug me-1" aria-hidden="true"></i>Probar conexión
                    </button>
                    <?php echo ds_boton('guardar', 'Guardar', ['estilo' => 'primary', 'type' => 'submit']); ?>
                </div>
            </div>

            <!-- Mensaje de cumpleaños -->
            <div class="card ds-redes">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-cake-candles me-2" aria-hidden="true"></i>Mensaje de cumpleaños</h3>
                </div>
                <div class="card-body">
                    <label for="cumple_mensaje" class="form-label">Texto de la publicación</label>
                    <textarea class="form-control" id="cumple_mensaje" name="cumple_mensaje" rows="4" maxlength="1500" required><?php echo $h($cfg['cumple_mensaje']); ?></textarea>
                    <div class="ds-redes__marcas mt-2" aria-label="Datos que se pueden insertar">
                        <?php foreach (['{nombre}' => 'Nombre', '{apellido}' => 'Apellido', '{edad}' => 'Edad', '{sede}' => 'Sede', '{escuela}' => 'Escuela'] as $marca => $rotulo): ?>
                            <button type="button" class="btn btn-sm btn-light border" data-marca="<?php echo $marca; ?>"><?php echo $rotulo; ?></button>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-muted d-block mt-1">
                        Por privacidad de los menores, {nombre} es sólo el primer nombre y {apellido} sólo el primer apellido.
                    </small>
                    <label for="cumple_hashtags" class="form-label mt-3">Hashtags</label>
                    <input type="text" class="form-control" id="cumple_hashtags" name="cumple_hashtags" maxlength="300"
                           value="<?php echo $h($cfg['cumple_hashtags']); ?>" placeholder="#FelizCumpleaños #ChampionsCF">
                    <div class="ds-redes__previa mt-3">
                        <span class="small text-muted d-block mb-1">Así se verá (con datos de ejemplo):</span>
                        <p class="mb-0" id="previaMensaje"></p>
                    </div>
                </div>
                <div class="card-footer ds-acciones">
                    <?php echo ds_boton('guardar', 'Guardar', ['estilo' => 'primary', 'type' => 'submit']); ?>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <!-- Perfiles -->
            <div class="card ds-redes">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-share-nodes me-2" aria-hidden="true"></i>Perfiles de la escuela</h3>
                </div>
                <div class="card-body">
                    <?php foreach ([
                        'url_facebook'  => ['fab fa-facebook', 'Facebook', 'https://www.facebook.com/…'],
                        'url_instagram' => ['fab fa-instagram', 'Instagram', 'https://www.instagram.com/…'],
                        'url_tiktok'    => ['fab fa-tiktok', 'TikTok', 'https://www.tiktok.com/@…'],
                        'url_youtube'   => ['fab fa-youtube', 'YouTube', 'https://www.youtube.com/@…'],
                    ] as $campo => [$icono, $rotulo, $ejemplo]): ?>
                        <label for="<?php echo $campo; ?>" class="form-label mb-1"><i class="<?php echo $icono; ?> me-1" aria-hidden="true"></i><?php echo $rotulo; ?></label>
                        <input type="url" class="form-control mb-2" id="<?php echo $campo; ?>" name="<?php echo $campo; ?>" maxlength="255"
                               placeholder="<?php echo $ejemplo; ?>" value="<?php echo $h($cfg[$campo]); ?>">
                    <?php endforeach; ?>
                    <label for="whatsapp" class="form-label mb-1"><i class="fab fa-whatsapp me-1" aria-hidden="true"></i>WhatsApp</label>
                    <input type="tel" class="form-control" id="whatsapp" name="whatsapp" inputmode="numeric" maxlength="13"
                           placeholder="09…" value="<?php echo $h($cfg['whatsapp']); ?>">
                </div>
            </div>

            <!-- Últimas publicaciones -->
            <div class="card ds-redes">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-clock-rotate-left me-2" aria-hidden="true"></i>Últimas publicaciones</h3>
                </div>
                <div class="card-body p-0">
                    <?php if (!$publicaciones): ?>
                        <p class="text-muted small p-3 mb-0">Aún no se ha publicado nada desde el sistema.</p>
                    <?php else: ?>
                        <ul class="list-group list-group-flush ds-redes__historial">
                            <?php foreach ($publicaciones as $p): $ok = $p['pub_estado'] === 'OK'; ?>
                                <li class="list-group-item">
                                    <i class="fab fa-<?php echo $p['pub_red'] === 'instagram' ? 'instagram' : 'facebook'; ?> me-1" aria-hidden="true"></i>
                                    <strong><?php echo $h($p['alumno'] ?: '—'); ?></strong>
                                    <span class="badge <?php echo $ok ? 'text-bg-success' : 'text-bg-danger'; ?> ms-1"><?php echo $ok ? 'Publicado' : 'Error'; ?></span>
                                    <span class="d-block small text-muted"><?php echo $h(date('d/m/Y H:i', strtotime($p['pub_fecha']))); ?> · <?php echo $h($p['pub_usuario']); ?></span>
                                    <?php if (!$ok && $p['pub_error']): ?><span class="d-block small text-danger-emphasis"><?php echo $h($p['pub_error']); ?></span><?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Probar conexión: formulario aparte (no se anidan); lo envía el botón con form= -->
<form class="FormularioAjax d-none" method="POST" action="<?php echo APP_URL; ?>ajax/coreAjax.php" id="formProbarRedes">
    <input type="hidden" name="modulo_core" value="probarRedes">
</form>

<script>
(function () {
    var mensaje = document.getElementById('cumple_mensaje'), hashtags = document.getElementById('cumple_hashtags'),
        previa = document.getElementById('previaMensaje');
    var ejemplo = { '{nombre}': 'Mateo', '{apellido}': 'Andrade', '{edad}': '10', '{sede}': 'LOJA',
                    '{escuela}': <?php echo json_encode(ds_nombre_organizacion(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG); ?> };
    function pintar() {
        var t = mensaje.value;
        Object.keys(ejemplo).forEach(function (m) { t = t.split(m).join(ejemplo[m]); });
        if (hashtags.value.trim()) { t += '\n\n' + hashtags.value.trim(); }
        previa.textContent = t;
    }
    mensaje.addEventListener('input', pintar);
    hashtags.addEventListener('input', pintar);
    pintar();

    /* Los botones de datos insertan su marca donde está el cursor. */
    document.querySelectorAll('[data-marca]').forEach(function (b) {
        b.addEventListener('click', function () {
            var ini = mensaje.selectionStart, fin = mensaje.selectionEnd, m = b.dataset.marca;
            mensaje.setRangeText(m, ini, fin, 'end');
            mensaje.focus();
            pintar();
        });
    });

    document.querySelectorAll('[data-ver-clave]').forEach(function (b) {
        b.addEventListener('click', function () {
            var c = document.getElementById(b.dataset.verClave), oculta = c.type === 'password';
            c.type = oculta ? 'text' : 'password';
            b.innerHTML = oculta ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        });
    });
})();
</script>

<?php if (!$puedeEditarConfig): ?>
<script>
/* Sólo lectura: sin permiso de editar, nada que enviar. */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form.FormularioAjax, form[method="POST"], form[method="post"]').forEach(function (f) {
        f.querySelectorAll('input, select, textarea, button').forEach(function (c) { if (c.type !== 'hidden') { c.disabled = true; } });
    });
});
</script>
<?php endif; ?>
<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
