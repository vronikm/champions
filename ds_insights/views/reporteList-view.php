<?php
/*
|--------------------------------------------------------------------------
| DigiSports Insights — Centro de reportes
|--------------------------------------------------------------------------
| El catálogo, no los reportes. Cada tarjeta lleva a donde ya se responde la
| pregunta.
|
|
| NO SE DUPLICA LO QUE BASKETBALL YA TIENE
|
| Once reportes existen ahí desde antes de que Insights naciera. El catálogo
| enlaza a ellos y los marca como externos, en vez de reimplementarlos. Es lo
| que pide el §51 del encargo, y además evita que dos pantallas den cifras
| distintas de lo mismo.
|
|
| SÓLO SE OFRECE LO QUE EL USUARIO PUEDE ABRIR
|
| Cada entrada declara la vista que hay que tener concedida. Ofrecer una
| tarjeta que lleva a un 403 no informa de nada: sólo enseña qué existe a
| quien no debería saberlo.
*/

use insights\controllers\insightsController;

$insInsights = new insightsController();

/*----------  Marcar o desmarcar un favorito  ----------*/
/*
| Va por POST y con CSRF: es una escritura, aunque sea sólo una preferencia.
| Después se redirige para que recargar la página no vuelva a alternarlo.
*/
$aviso = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['favorito'])) {
    if (!csrf_valido('insights_favorito')) {
        $aviso = ['tono' => 'danger', 'texto' => 'La solicitud no se pudo verificar. Inténtelo de nuevo.'];
    } else {
        $insInsights->alternarFavorito((string) $_POST['favorito']);
        header('Location: ' . APP_URL . 'reporteList/'
             . (isset($_GET['cat']) ? '?cat=' . urlencode((string) $_GET['cat']) : ''));
        exit();
    }
}

$catalogo  = $insInsights->catalogoReportes();
$favoritos = $insInsights->favoritos();

/* Se descartan los que el usuario no puede abrir. Los externos no llevan
   vista de Insights: su control está en el módulo que los sirve. */
$catalogo = array_filter($catalogo, static function (array $r): bool {
    return $r['vista'] === null || usuario_tiene_permiso($r['vista']);
});

$categorias = array_values(array_unique(array_column($catalogo, 'categoria')));
sort($categorias);

$filtro = isset($_GET['cat']) ? (string) $_GET['cat'] : 'Todos';

$visibles = $catalogo;
if ($filtro === 'Favoritos') {
    $visibles = array_filter($catalogo, static fn(string $k): bool => in_array($k, $favoritos, true),
                             ARRAY_FILTER_USE_KEY);
} elseif ($filtro !== 'Todos') {
    $visibles = array_filter($catalogo, static fn(array $r): bool => $r['categoria'] === $filtro);
}

$tituloVista = 'Centro de reportes';
$vistaActual = 'reporteList';

require_once __DIR__ . "/inc/layout-top.php";
?>

<?php if ($aviso !== null): ?>
    <div class="callout mb-3" style="border-left-color:var(--bs-danger);">
        <?php echo htmlspecialchars($aviso['texto'], ENT_QUOTES, 'UTF-8'); ?>
    </div>
<?php endif; ?>

<!-- ==================== Filtros ==================== -->
<div class="card mb-3">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-center gap-2">
            <?php
            $pestanas = array_merge(['Todos', 'Favoritos'], $categorias);
            foreach ($pestanas as $c):
                $activa = $c === $filtro;
                $cuantos = $c === 'Todos' ? count($catalogo)
                    : ($c === 'Favoritos'
                        ? count(array_intersect(array_keys($catalogo), $favoritos))
                        : count(array_filter($catalogo, static fn(array $r): bool => $r['categoria'] === $c)));
            ?>
                <a href="?cat=<?php echo urlencode($c); ?>"
                   class="btn btn-sm <?php echo $activa ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    <?php if ($c === 'Favoritos'): ?><i class="fas fa-star me-1"></i><?php endif; ?>
                    <?php echo htmlspecialchars($c, ENT_QUOTES, 'UTF-8'); ?>
                    <span class="ms-1 opacity-75"><?php echo $cuantos; ?></span>
                </a>
            <?php endforeach; ?>

            <div class="ms-auto" style="width:16rem;">
                <label for="buscar" class="visually-hidden">Buscar reporte</label>
                <input type="search" id="buscar" class="form-control form-control-sm"
                       placeholder="Buscar por nombre o contenido"
                       aria-describedby="buscarAyuda">
                <span id="buscarAyuda" class="visually-hidden">
                    Filtra las tarjetas mientras escribe
                </span>
            </div>
        </div>
    </div>
</div>

<!-- ==================== Las tarjetas ==================== -->
<?php if (count($visibles) === 0): ?>
    <div class="card">
        <div class="card-body">
            <p class="text-muted mb-0">
                <?php if ($filtro === 'Favoritos'): ?>
                    Todavía no ha marcado ningún reporte como favorito. Use la estrella de
                    cada tarjeta para tenerlos a mano.
                <?php else: ?>
                    No hay reportes en esta categoría que usted pueda abrir.
                <?php endif; ?>
            </p>
        </div>
    </div>
<?php else: ?>
    <div class="row" id="rejilla">
        <?php foreach ($visibles as $clave => $r):
            $esFavorito = in_array($clave, $favoritos, true); ?>
            <div class="col-12 col-md-6 col-xl-4 mb-3 tarjeta-reporte"
                 data-busca="<?php echo htmlspecialchars(
                     mb_strtolower($r['titulo'] . ' ' . $r['resumen'] . ' ' . $r['categoria']),
                     ENT_QUOTES, 'UTF-8'); ?>">
                <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-start justify-content-between mb-2">
                            <span class="d-inline-flex align-items-center justify-content-center rounded
                                         bg-secondary text-white"
                                  style="width:42px;height:42px;font-size:1.1rem;">
                                <i class="<?php echo $r['icono']; ?>" aria-hidden="true"></i>
                            </span>

                            <form method="post" action="?cat=<?php echo urlencode($filtro); ?>" class="m-0">
                                <?php echo csrf_campo('insights_favorito'); ?>
                                <input type="hidden" name="favorito" value="<?php echo htmlspecialchars($clave, ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="btn btn-sm btn-link p-0 border-0"
                                        title="<?php echo $esFavorito ? 'Quitar de favoritos' : 'Marcar como favorito'; ?>"
                                        aria-label="<?php echo $esFavorito ? 'Quitar de favoritos' : 'Marcar como favorito'; ?>"
                                        aria-pressed="<?php echo $esFavorito ? 'true' : 'false'; ?>">
                                    <i class="<?php echo $esFavorito ? 'fas' : 'far'; ?> fa-star
                                              <?php echo $esFavorito ? 'text-warning' : 'text-muted'; ?>"
                                       style="font-size:1.1rem;"></i>
                                </button>
                            </form>
                        </div>

                        <h5 class="mb-1"><?php echo htmlspecialchars($r['titulo'], ENT_QUOTES, 'UTF-8'); ?></h5>

                        <p class="text-muted small flex-grow-1">
                            <?php echo htmlspecialchars($r['resumen'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>

                        <div class="d-flex align-items-center justify-content-between mt-2">
                            <span class="badge text-bg-secondary">
                                <?php echo htmlspecialchars($r['categoria'], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <?php if ($r['externo']): ?>
                                <span class="text-muted small"
                                      title="Este reporte lo sirve el módulo Fútbol. Insights enlaza a él en vez de duplicarlo.">
                                    <i class="fas fa-arrow-up-right-from-square me-1"></i>Fútbol
                                </span>
                            <?php endif; ?>
                        </div>

                        <a href="<?php echo htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8'); ?>"
                           class="btn btn-sm btn-outline-primary mt-3">
                            Ver reporte <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="text-muted small" id="sinResultados" hidden>
        Ningún reporte coincide con la búsqueda.
    </p>
<?php endif; ?>

<script>
/*
| El buscador filtra en el navegador, sin recargar. Son trece tarjetas: pedir
| al servidor por cada tecla sería gastar una petición por letra para filtrar
| una lista que ya está entera en la página.
|
| El texto de búsqueda se preparó en el servidor —título, resumen y
| categoría, en minúsculas— para no repetir la normalización en cada
| pulsación.
*/
(function () {
    var caja = document.getElementById('buscar');
    if (!caja) { return; }

    var tarjetas = [].slice.call(document.querySelectorAll('.tarjeta-reporte'));
    var vacio = document.getElementById('sinResultados');

    function filtrar() {
        var q = caja.value.trim().toLowerCase();
        var visibles = 0;

        tarjetas.forEach(function (t) {
            var coincide = q === '' || t.getAttribute('data-busca').indexOf(q) !== -1;
            t.hidden = !coincide;
            if (coincide) { visibles++; }
        });

        if (vacio) { vacio.hidden = visibles > 0; }
    }

    /* Sin debounce: filtrar trece nodos no justifica el retraso, y un
       buscador que responde tarde se percibe como roto. */
    caja.addEventListener('input', filtrar);
})();
</script>

<?php require_once __DIR__ . "/inc/layout-bottom.php"; ?>
