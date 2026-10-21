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
| Uso desde un modulo:
|     require_once __DIR__ . "/../../../../ds_core/inc/app-launcher.php";
*/

require_once __DIR__ . "/../modulos.php";

$modulosLauncher = ds_modulos();

/* Marca la aplicacion en la que se esta ahora mismo. */
$moduloActual = defined('APP_URL') ? APP_URL : '';
?>

<!-- Design system del ecosistema: aporta los tokens y el panel. -->
<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/digisports.css'); ?>">

<li class="nav-item ds-launcher">
    <a class="nav-link" href="#" id="dsLauncherBtn" role="button"
       aria-haspopup="true" aria-expanded="false" title="Aplicaciones DigiSports">
        <i class="fas fa-th"></i>
    </a>

    <div class="ds-launcher__panel" id="dsLauncherPanel">
        <div class="ds-launcher__titulo">DigiSports Apps</div>

        <?php foreach ($modulosLauncher as $m): ?>
            <?php if ($m['activo']): ?>
                <a href="<?php echo $m['url']; ?>"
                   class="ds-launcher__item"
                   style="--acento: <?php echo $m['acento']; ?>;">
                    <i class="<?php echo $m['icono']; ?>"></i><?php echo $m['nombre']; ?>
                    <?php if ($m['url'] === $moduloActual): ?><small>Aquí</small><?php endif; ?>
                </a>
            <?php else: ?>
                <span class="ds-launcher__item ds-launcher__item--inactivo"
                      style="--acento: <?php echo $m['acento']; ?>;">
                    <i class="<?php echo $m['icono']; ?>"></i><?php echo $m['nombre']; ?>
                    <small>Pronto</small>
                </span>
            <?php endif; ?>
        <?php endforeach; ?>

        <div class="ds-launcher__sep"></div>

        <a href="<?php echo DS_HUB_URL; ?>" class="ds-launcher__item">
            <i class="fas fa-arrow-left"></i>Volver al Hub
        </a>
    </div>
</li>

<script>
(function () {
    var boton = document.getElementById('dsLauncherBtn');
    var panel = document.getElementById('dsLauncherPanel');
    if (!boton || !panel) return;

    function cerrar() {
        panel.classList.remove('abierto');
        boton.setAttribute('aria-expanded', 'false');
    }

    boton.addEventListener('click', function (e) {
        e.preventDefault();
        var abierto = panel.classList.toggle('abierto');
        boton.setAttribute('aria-expanded', abierto ? 'true' : 'false');
    });

    /* Cerrar al pulsar fuera o con Escape. */
    document.addEventListener('click', function (e) {
        if (!panel.contains(e.target) && !boton.contains(e.target)) cerrar();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') cerrar();
    });
})();
</script>
