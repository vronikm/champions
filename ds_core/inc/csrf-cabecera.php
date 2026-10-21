<?php
/*
|--------------------------------------------------------------------------
| El testigo CSRF, para la cabecera de cualquier módulo
|--------------------------------------------------------------------------
| Se incluye dentro del <head> de cada plantilla de módulo:
|
|     <?php require DS_HUB_PATH . 'ds_core/inc/csrf-cabecera.php'; ?>
|
| Emite dos cosas: el testigo en una etiqueta <meta> y el script que lo pega
| a todo lo que sale del navegador.
|
|
| POR QUÉ EN <meta> Y NO EN UNA VARIABLE JS SUELTA
|
| Un <meta> lo lee cualquier script sin depender del orden de carga ni de una
| variable global que alguien puede pisar. Y no se escribe dentro de un
| bloque <script>: el valor es hexadecimal y no puede romper nada, pero meter
| datos de servidor dentro de JavaScript es la costumbre que un día trae un
| valor con comillas y abre una inyección.
|
|
| POR QUÉ EN LA CABECERA Y NO AL FINAL
|
| El script tiene que estar puesto ANTES de que cualquier otro código lance
| una petición. Al final del cuerpo llegaría tarde para todo lo que se
| dispare durante la carga, y esas peticiones saldrían sin testigo: el
| servidor las rechazaría y parecería un fallo del sistema.
|
| Por eso va sin defer ni async. Es un archivo de tres kilobytes.
*/

/* csrf_token() devuelve cadena vacía si no hay sesión activa, que es
   exactamente cuando no hay nada que proteger: no se emite nada y csrf.js
   ni siquiera se descarga. */
if (function_exists('csrf_token') && function_exists('ds_recurso')):
    $dsTestigo = csrf_token();
    if ($dsTestigo !== ''):
?>
    <meta name="ds-csrf" content="<?php echo htmlspecialchars($dsTestigo, ENT_QUOTES, 'UTF-8'); ?>">
    <script src="<?php echo ds_recurso('ds_core/assets/js/csrf.js'); ?>"></script>
<?php
    endif;
endif;
