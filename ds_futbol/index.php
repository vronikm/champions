<?php

    require_once __DIR__ . "/../ds_core/inc/errores.php"; // antes que config: si falla, sin rutas a la vista
    require_once "./config/app.php";
    require_once "./autoload.php";

    /*---------- Iniciando sesion ----------*/
    require_once "./app/views/inc/session_start.php";

    if(isset($_GET['views'])){
        $url=explode("/", $_GET['views']);
    }else{
        $url=[""];
    }

    # La raiz del modulo es el panel, como en Core, Arena y League. Antes
    # resolvia a «login» y mostraba la pantalla de acceso AUN CON SESION
    # ABIERTA; era el unico de los cuatro que lo hacia.
    #
    # Se arregla aqui y no en el enlace del lanzador porque el problema no
    # es del lanzador: afecta igual a un marcador del navegador o a una URL
    # escrita a mano.
    #
    # A quien no tenga sesion lo sigue mandando al login el guardian de mas
    # abajo, el mismo que protege el resto de vistas. Aqui solo se decide a
    # donde apunta la raiz, no quien puede entrar.
    if(!isset($url[0]) || $url[0]===""){
        $url[0]="dashboard";
    }

    use app\controllers\viewsController;
    use app\controllers\loginController;

    $insLogin = new loginController();

    $viewsController= new viewsController();
    $vista=$viewsController->obtenerVistasControlador($url[0]);

    # La verificacion del segundo factor va con el login y no con el resto:
    # quien llega ahi acerto la contrasena pero AUN NO esta autenticado, asi
    # que no puede pasar por los controles que exigen sesion. Su propia
    # puerta es la marca que dejo el paso anterior, y la vista la comprueba
    # antes de imprimir nada.
    if($url[0]=="verificar2fa"){
        require_once "app/views/content/verificar2fa-view.php";
        exit();
    }

    # El recibo que se comparte por WhatsApp. Lo abre el representante, que
    # no tiene cuenta, así que tampoco pasa por los controles de sesión. Su
    # puerta es la firma del enlace: sirve UN recibo concreto, caduca sola y
    # se invalida si el pago se anula (pagosController::reciboDeEnlacePublico).
    # Sólo entrega el PDF de ese recibo —de un pago o de un abono—; nunca
    # otra vista.
    if($url[0]=="reciboPublico"){
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: private, no-store');
        header('Referrer-Policy: no-referrer');
        $reciboPublico = (new \app\controllers\pagosController())->reciboDeEnlacePublico((string)($url[1] ?? ''));
        if($reciboPublico !== null && $reciboPublico['tipo'] === 'abono'){
            $url = ['pagospendienteReciboPDF', (string)$reciboPublico['id']];
            require_once "app/views/content/pagospendienteReciboPDF-view.php";
        }elseif($reciboPublico !== null){
            $url = ['pagosReciboPDF', (string)$reciboPublico['id']];
            require_once "app/views/content/pagosReciboPDF-view.php";
        }else{
            require_once DS_HUB_PATH . "ds_core/inc/reciboEnlaceInvalido.php";
        }
        exit();
    }

    if($vista=="login" || $vista=="404"){
        # Una ruta inexistente debe responder 404, no 200 con la página de
        # error: los buscadores y las herramientas se guían por el estado.
        if($vista=="404"){ http_response_code(404); }
        require_once "app/views/content/".$vista."-view.php";
    }else{

      # Cerrar sesion #
      if((!isset($_SESSION['usuario']) || $_SESSION['usuario']=="")){
          $insLogin->cerrarSesionControlador();
          exit();
      }

      # Control de acceso, nivel 1: el rol debe tener concedido el modulo #
      if(!usuario_tiene_modulo(DS_MODULO)){
          http_response_code(403);
          require_once "app/views/content/accesoDenegado-view.php";
          exit();
      }

      # Control de acceso, nivel 2: permiso de lectura sobre la vista #
      # Impide que un usuario autenticado abra por URL una pantalla que su
      # rol no tiene habilitada. Las vistas que no son item de menu
      # (formularios, PDF, perfiles) heredan el permiso de las pantallas
      # desde las que se abren: ds_core/inc/vistas_apoyo.php.
      # Ver usuario_tiene_permiso() en ds_core/inc/seguridad.php
      if(!usuario_tiene_permiso($url[0])){
          http_response_code(403);
          require_once "app/views/content/accesoDenegado-view.php";
          exit();
      }

      require_once $vista;

    } 
