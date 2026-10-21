<?php
/*
|--------------------------------------------------------------------------
| Control de tema — con el contrato de AdminLTE 4.8.5
|--------------------------------------------------------------------------
| Este control ya NO lleva logica propia. Usa los atributos que el paquete de
| AdminLTE reconoce, y es su codigo el que aplica el tema, lo guarda y marca
| la opcion activa:
|
|   data-bs-theme-value="light|dark|auto"   en cada opcion. AdminLTE le pone
|                                           .active, aria-pressed y muestra
|                                           su .bi-check-lg.
|   data-lte-theme-icon="light|dark|auto"   en el boton. AdminLTE deja
|                                           visible el que corresponda y
|                                           esconde los otros con d-none.
|
| POR QUE SE CAMBIO
|
| Antes esto se movia con un tema.js propio que guardaba en «ds-tema».
| AdminLTE guarda en «lte-theme» y aplica el tema en DOMContentLoaded, asi
| que habia dos mecanismos pisandose: la pagina se pintaba con el tema
| elegido y acto seguido saltaba al de AdminLTE. Se retiro el nuestro.
|
| TRES OPCIONES, NO UN INTERRUPTOR DE DOS
|
| «El del sistema» sigue la preferencia del sistema operativo, que es lo que
| espera quien tiene el movil o el portatil en oscuro por la noche. Un
| interruptor de dos posiciones obliga a acordarse de cambiarlo dos veces al
| dia.
|
| Uso desde un modulo:
|     require_once __DIR__ . "/../../../../ds_core/inc/tema-control.php";
*/
?>

<li class="nav-item dropdown">
    <a class="nav-link" href="#" role="button" data-bs-toggle="dropdown"
       aria-expanded="false" title="Tema de la interfaz" aria-label="Tema de la interfaz">
        <?php /* AdminLTE deja visible el que coincide con lo elegido. */ ?>
        <i class="fas fa-sun"     data-lte-theme-icon="light"></i>
        <i class="fas fa-moon     d-none" data-lte-theme-icon="dark"></i>
        <i class="fas fa-desktop  d-none" data-lte-theme-icon="auto"></i>
    </a>

    <div class="dropdown-menu dropdown-menu-end">
        <h6 class="dropdown-header">Tema</h6>

        <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                data-bs-theme-value="light" aria-pressed="false">
            <i class="fas fa-sun fa-fw"></i> Claro
            <i class="fas fa-check ms-auto d-none bi-check-lg"></i>
        </button>

        <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                data-bs-theme-value="dark" aria-pressed="false">
            <i class="fas fa-moon fa-fw"></i> Oscuro
            <i class="fas fa-check ms-auto d-none bi-check-lg"></i>
        </button>

        <button type="button" class="dropdown-item d-flex align-items-center gap-2"
                data-bs-theme-value="auto" aria-pressed="false">
            <i class="fas fa-desktop fa-fw"></i> El del sistema
            <i class="fas fa-check ms-auto d-none bi-check-lg"></i>
        </button>
    </div>
</li>
