<!-- Main Sidebar Container -->
<?php 
  use app\controllers\menuController;
  $insGenerar = new menuController();	

    $nombre= ($_SESSION['sede'] != "") ? $_SESSION['sede'] : APP_NAME;
    $session_rolid= $_SESSION['rol'];
    $usuario_login=$_SESSION['usuario'];

    /* El menú es dinámico para TODOS los roles: se construye a partir de
       los permisos concedidos desde el módulo Core. El Super Administrador
       recibe el módulo completo porque pasa por encima del control de
       acceso (ver menuController::ObtenerMenu). */
    $menuHTML = '';

    if($usuario_login != ""){
      $GenerarMenu = $insGenerar->ObtenerMenu($usuario_login, DS_MODULO);

      /*
      | Los numeritos del menu.
      |
      | Se calculan SOLO si el usuario tiene esa entrada: quien no puede ver
      | las inscripciones pendientes no paga la consulta. Y el menu se dibuja
      | en cada pagina, asi que cada consulta que se meta aqui la paga el
      | sistema entero: la de inscripciones cuesta 2,4 ms medidos, y es una
      | cola de tareas que no crece sin limite.
      |
      | La mora se quedo fuera a proposito: la cifra que enseña el panel sale
      | de una consulta por sede con varias subconsultas, y repetirla en cada
      | carga no sale a cuenta por un numerito.
      */
      $contadoresMenu = [];
      $tienePendientes = false;
      foreach ($GenerarMenu as $m) {
          if (trim((string) ($m['menu_vista'] ?? ''), "/ \t\n\r\0\x0B") === 'inscripcionPendientes') {
              $tienePendientes = true;
              break;
          }
      }
      if ($tienePendientes) {
          try {
              $insInscripcionMenu = new \app\controllers\inscripcionController();
              $contadoresMenu['inscripcionPendientes'] = $insInscripcionMenu->contarPendientesInscripcion();
          } catch (\Throwable $e) {
              /* Un contador que falla no puede dejar sin menu a nadie. */
              $contadoresMenu = [];
          }
      }

      $menuHTML    = $insGenerar->ConstruirMenu($GenerarMenu, $url[0] ?? '', $contadoresMenu);
    }else{
      session_destroy();
		  header("Location: ".APP_URL."login/");
    }
?>

<aside class="app-sidebar bg-body-secondary shadow ds-core__sidebar" data-bs-theme="dark">
    <div class="sidebar-brand">
    <!-- Brand Logo -->
    <a href="#" class="brand-link">
      <?php /* Desde ds_core, no desde la copia del modulo: es el mismo archivo y
               asi hay uno solo para todo el ecosistema. Con ds_recurso() para que
               lleve version: sin ella, cambiar el logo puede tardar en verse. */ ?>
      <img src="<?php echo ds_recurso('ds_core/assets/img/icon_cz98px.png'); ?>" alt="<?php echo APP_NAME; ?>" class="brand-image opacity-75 shadow">
      <span class="brand-text fw-light"><?php echo $nombre; ?></span>
    </a>

    </div>

    <!-- Sidebar -->
    <div class="sidebar-wrapper">
      <!-- Sidebar Menu -->
      <nav class="mt-2">
        <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="menu" data-accordion="false">               
              
          <?php echo $menuHTML; ?>
          
        </ul>
      </nav>
      <!-- /.sidebar-menu -->

    </div>
    <!-- /.sidebar -->
</aside>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.querySelector('.app-sidebar .sidebar-wrapper');
  var menu = document.querySelector('.app-sidebar .sidebar-menu');
  var storageKey = 'digisports.basketball.sidebar.scrollTop';

  if (!sidebar || !menu || !window.sessionStorage) {
    return;
  }

  function saveSidebarPosition() {
    sessionStorage.setItem(storageKey, String(sidebar.scrollTop));
  }

  function getDirectNavLink(item) {
    for (var i = 0; i < item.children.length; i++) {
      if (item.children[i].matches && item.children[i].matches('a.nav-link')) {
        return item.children[i];
      }
    }

    return null;
  }

  function openActiveBranches() {
    var activeLinks = menu.querySelectorAll('.nav-treeview .nav-link.active');

    activeLinks.forEach(function (activeLink) {
      var treeview = activeLink.closest('.nav-treeview');

      while (treeview && menu.contains(treeview)) {
        var parentItem = treeview.closest('.nav-item');

        if (!parentItem) {
          break;
        }

        parentItem.classList.add('menu-open');

        var parentLink = getDirectNavLink(parentItem);
        if (parentLink) {
          parentLink.classList.add('active');
        }

        treeview = parentItem.parentElement ? parentItem.parentElement.closest('.nav-treeview') : null;
      }
    });
  }

  function keepActiveItemVisible() {
    var activeLinks = menu.querySelectorAll('.nav-link.active');
    var activeLink = activeLinks.length ? activeLinks[activeLinks.length - 1] : null;

    if (!activeLink) {
      return;
    }

    var sidebarRect = sidebar.getBoundingClientRect();
    var activeRect = activeLink.getBoundingClientRect();
    var offset = 16;

    if (activeRect.top < sidebarRect.top) {
      sidebar.scrollTop -= (sidebarRect.top - activeRect.top) + offset;
    } else if (activeRect.bottom > sidebarRect.bottom) {
      sidebar.scrollTop += (activeRect.bottom - sidebarRect.bottom) + offset;
    }
  }

  function restoreSidebarPosition() {
    openActiveBranches();

    var savedScrollTop = parseInt(sessionStorage.getItem(storageKey), 10);

    if (!Number.isNaN(savedScrollTop)) {
      sidebar.scrollTop = savedScrollTop;
    }

    keepActiveItemVisible();
  }

  menu.addEventListener('click', function (event) {
    if (event.target.closest('a.nav-link')) {
      saveSidebarPosition();
    }
  }, true);

  sidebar.addEventListener('scroll', saveSidebarPosition, { passive: true });
  window.addEventListener('beforeunload', saveSidebarPosition);

  openActiveBranches();
  window.requestAnimationFrame(restoreSidebarPosition);
  window.setTimeout(restoreSidebarPosition, 250);
});
</script>