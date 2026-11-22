<?php
/*
|--------------------------------------------------------------------------
| Selector de aplicaciones DigiSports
|--------------------------------------------------------------------------
| Boton ▦ para la barra superior de cada modulo: permite saltar a otra
| aplicacion del ecosistema sin volver al Hub, como el app launcher de
| Microsoft 365 o Google Workspace.
|
| Se alimenta del catalogo estatico ds_core/modulos.php, de modo que un
| modulo nuevo aparece aqui automaticamente y sin coste de consultas.
|
| SOLO LOS MODULOS DEL ROL (2026-11-21). Antes listaba todos y un profesor
| veía Core, Arena, League e Insights, que lo llevaban a «acceso denegado».
| Ahora aplica el mismo criterio que el Hub (hubController::modulos()):
| usuario_tiene_modulo(). Fútbol entra por la primera vista que el rol
| puede abrir (un profesor no tiene el dashboard).
|
| Uso desde un modulo:
|     require_once __DIR__ . "/../../../../ds_core/inc/app-launcher.php";
*/

require_once __DIR__ . "/../modulos.php";

/* Marca la aplicacion en la que se esta ahora mismo. */
$moduloActual = defined('APP_URL') ? APP_URL : '';

$modulosLauncher = [];
foreach (ds_modulos() as $claveLauncher => $m) {
    if (!$m['activo'] || !usuario_tiene_modulo($claveLauncher)) {
        continue;
    }
    $m['aqui'] = $m['url'] === $moduloActual;
    if ($claveLauncher === 'basketball' && !usuario_tiene_permiso('dashboard', 'basketball')) {
        $entrada = primera_vista_permitida('basketball');
        if ($entrada !== '') { $m['url'] = DS_FUTBOL_URL . $entrada . '/'; }
    }
    $modulosLauncher[$claveLauncher] = $m;
}
?>

<!-- Design system del ecosistema: aporta los tokens y el panel. -->
<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/digisports.css'); ?>">

<li class="nav-item ds-launcher">
    <a class="nav-link" href="#" id="dsLauncherBtn" role="button"
       aria-haspopup="true" aria-expanded="false" aria-controls="dsLauncherPanel"
       title="Aplicaciones DigiSports" aria-label="Aplicaciones DigiSports">
        <i class="fas fa-th" aria-hidden="true"></i>
    </a>

    <div class="ds-launcher__panel" id="dsLauncherPanel" role="menu" aria-label="Aplicaciones DigiSports">
        <div class="ds-launcher__cab">
            <span class="ds-launcher__titulo">Sus aplicaciones</span>
            <button type="button" class="ds-launcher__cerrar" aria-label="Cerrar">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>

        <div class="ds-launcher__lista">
            <?php foreach ($modulosLauncher as $m): ?>
                <a href="<?php echo $m['url']; ?>" role="menuitem"
                   class="ds-launcher__item<?php echo $m['aqui'] ? ' ds-launcher__item--aqui' : ''; ?>"
                   style="--acento: <?php echo $m['acento']; ?>;"
                   <?php echo $m['aqui'] ? 'aria-current="page"' : ''; ?>>
                    <span class="ds-launcher__icono"><i class="<?php echo $m['icono']; ?>" aria-hidden="true"></i></span>
                    <span class="ds-launcher__texto">
                        <span class="ds-launcher__nombre"><?php echo $m['nombre']; ?></span>
                        <span class="ds-launcher__tagline"><?php echo $m['tagline']; ?></span>
                    </span>
                    <?php if ($m['aqui']): ?><small class="ds-launcher__aqui">Aquí</small><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>

        <a href="<?php echo DS_HUB_URL; ?>" class="ds-launcher__hub" role="menuitem">
            <i class="fas fa-arrow-left" aria-hidden="true"></i>Volver al Hub
        </a>
    </div>
</li>

<script>
(function () {
    var boton = document.getElementById('dsLauncherBtn');
    var panel = document.getElementById('dsLauncherPanel');
    if (!boton || !panel) return;

    function cerrar(devolverFoco) {
        if (!panel.classList.contains('abierto')) return;
        panel.classList.remove('abierto');
        boton.setAttribute('aria-expanded', 'false');
        if (devolverFoco) boton.focus();
    }

    boton.addEventListener('click', function (e) {
        e.preventDefault();
        var abierto = panel.classList.toggle('abierto');
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
        /* En el teléfono el panel es fijo (digisports.css): se coloca justo
           bajo el botón, mida lo que mida la barra. */
        if (abierto && getComputedStyle(panel).position === 'fixed') {
            panel.style.top = Math.round(boton.getBoundingClientRect().bottom + 8) + 'px';
        } else {
            panel.style.top = '';
        }
        if (abierto) {
            var actual = panel.querySelector('.ds-launcher__item');
            if (actual) actual.focus({ preventScroll: true });
        }
    });

    panel.querySelector('.ds-launcher__cerrar').addEventListener('click', function () { cerrar(true); });

    /* Cerrar al pulsar fuera o con Escape. */
    document.addEventListener('click', function (e) {
        if (!panel.contains(e.target) && !boton.contains(e.target)) cerrar(false);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') cerrar(true);
    });
})();
</script>
