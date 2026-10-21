<?php
/*
|--------------------------------------------------------------------------
| Arranque del tema — el de AdminLTE 4.8.5, no uno propio
|--------------------------------------------------------------------------
| Este es el bloque «Theme Init» que la plantilla trae en su demo
| (dist/index.html), copiado sin cambios de fondo. Su comentario original
| dice para qué existe: «prevents flash of incorrect theme on load, #6043».
|
| POR QUE SE ADOPTA EL DE LA PLANTILLA
|
| DigiSports tenia un tema.js propio que hacia lo mismo con OTRA clave de
| almacenamiento («ds-tema»). AdminLTE guarda en «lte-theme» y aplica el
| tema en DOMContentLoaded, asi que habia dos mecanismos compitiendo:
|
|   1. tema.js ponia «light» en la cabecera → la pagina se pintaba clara.
|   2. adminlte.min.js arrancaba, leia SU clave y la pagina saltaba a oscuro.
|
| Ese era el parpadeo. La plantilla ya lo tenia resuelto; el problema era
| tener dos soluciones a la vez, no que faltara una.
|
| PRECEDENCIA, TAL COMO LA DEFINE LA PLANTILLA
|
|   lo que eligio la persona  →  lo que declare la pagina  →  el sistema
|
| Va en el <head> y SIN defer a proposito: tiene que ejecutarse antes del
| primer pintado o la pagina parpadea, que es justo lo que viene a evitar.
*/
?>
<!--begin::Theme Init (prevents flash of incorrect theme on load, #6043)-->
<script>
  (() => {
    'use strict';
    const root = document.documentElement;

    // Las aplicaciones con su propio tema se salen del modo de color de
    // AdminLTE por completo, aquí y en el paquete.
    if (root.getAttribute('data-lte-color-mode') === 'off') {
      return;
    }

    const STORAGE_KEY = 'lte-theme';
    let stored = null;
    try {
      stored = localStorage.getItem(STORAGE_KEY);
    } catch {
      // localStorage puede no estar (modo privado, iframe aislado).
    }
    // Misma precedencia que color-mode.ts: gana la elección de quien visita,
    // luego el tema que declare la página, luego la preferencia del sistema.
    const authored = root.getAttribute('data-bs-theme');
    let resolved = 'light';
    if (stored === 'dark' || stored === 'light') {
      resolved = stored;
    } else if (authored === 'dark' || authored === 'light') {
      resolved = authored;
    } else if (globalThis.matchMedia('(prefers-color-scheme: dark)').matches) {
      resolved = 'dark';
    }
    root.setAttribute('data-bs-theme', resolved);
    root.style.colorScheme = resolved;
    // Se marca que el valor se calculó aquí, para que el paquete no lo tome
    // por un tema declarado por la página y deje de seguir al sistema.
    if (resolved !== authored) {
      root.setAttribute('data-lte-theme-resolved', '');
    }
  })();
</script>
<!--end::Theme Init-->
