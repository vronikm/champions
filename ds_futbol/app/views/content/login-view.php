<?php
/*
| Pantalla de acceso a DigiSports Basketball
|--------------------------------------------------------------------------
| Comparte lenguaje visual con el Hub: carga digisports.css (los tokens del
| ecosistema) y encima login.css. Entrar y llegar al Hub tienen que
| sentirse como la misma aplicacion.
|
| EL POST SE PROCESA ANTES DE IMPRIMIR NADA. No es un detalle de estilo:
| estaba al final del archivo, y como output_buffering son 4096 bytes y la
| pagina pasa de eso, el buffer se vaciaba a mitad y las cabeceras salian
| antes de tiempo. Con ello:
|
|   · session_regenerate_id() fallaba con un aviso y NO regeneraba nada, de
|     modo que la proteccion contra fijacion de sesion no se aplicaba;
|   · la redireccion caia al <script>window.location</script>.
|
| Cualquier cambio en esta vista debe mantener este bloque el primero.
*/

if (isset($_POST['login_usuario'], $_POST['login_clave'])) {
    $insLogin->iniciarSesionControlador();
}

/* Aviso de un intento anterior: se lee y se descarta para que no
   reaparezca al recargar. */
$avisoLogin = (string)($_SESSION['login_aviso'] ?? '');
unset($_SESSION['login_aviso']);

/* Desde ds_core y no desde el modulo: es el mismo icono para todo el
   ecosistema, y una sola copia no se desincroniza.

   Dos imagenes y no una: el icono de la pestaña tiene que ser cuadrado, y
   logo.png es el escudo en alta resolucion sobre un lienzo apaisado con
   margenes transparentes. En pantalla, login.css lo encuadra con
   object-fit: cover para que esos margenes no encojan el escudo. */
$icono  = ds_recurso('ds_core/assets/img/icon_cz98px.png');
$logo   = ds_recurso('ds_core/assets/img/logo.png');
$vendor = APP_URL . 'app/views/dist/';

/* Fotografia de fondo. Vive en ds_core/assets porque es del ecosistema, no
   de este modulo: el dia que League o Insights tengan su propia entrada,
   la reutilizan sin copiarla.

   Se usa la copia en JPEG, no el PNG original: aquel pesaba 1,8 MB y es lo
   primero que descarga la pantalla mas publica del sistema. Si el JPEG no
   estuviera, se cae al PNG; y si tampoco, al fondo construido con luz. No
   hay ningun caso en que quede una peticion rota.

   Se comprueba con is_file() sobre la ruta real, no sobre la URL: una URL
   rota no se puede detectar desde aqui. */
$imgCore  = __DIR__ . '/../../../../ds_core/assets/img/';
$urlCore  = DS_HUB_URL . 'ds_core/assets/img/';

$fondo = '';
foreach (['fondo_login.jpg', 'fonfo_login.png'] as $archivo) {
    if (is_file($imgCore . $archivo)) { $fondo = $urlCore . $archivo; break; }
}
$conFoto = $fondo !== '';

/* Lo que de verdad hace este modulo. Antes que rellenar con reclamos
   genericos, se nombran las funciones que el usuario va a encontrar
   dentro. */
/* Iconos de Font Awesome 5, que es la version que carga el proyecto.
   fa-chart-column es de la 6 y aqui sale un hueco en blanco. */
$funciones = [
    ['fa-users',               'Gestión de', 'alumnos'],
    ['fa-clipboard-check',     'Control de', 'asistencia'],
    ['fa-file-invoice-dollar', 'Pagos y',    'cobranza'],
    ['fa-chart-bar',           'Reportes e', 'indicadores'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo APP_NAME; ?> | Iniciar sesión</title>

    <link rel="icon" type="image/png" href="<?php echo $icono; ?>">
    <?php /* La tipografia se sirve desde aqui, no desde fonts.googleapis.com:
             con el sistema en internet, aquel enlace enviaba la IP de cada
             visitante a un tercero ANTES de iniciar sesion, y ataba la
             pantalla de acceso a un servicio ajeno. */ ?>
    <?php /* Sin core.css: esta pantalla no usa AdminLTE ni el estándar de
             acciones, tiene su propio diseño, y es la página más pública del
             sistema. Cada KB cuenta aquí. */ ?>
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
    <link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/digisports.css'); ?>">

    <?php /* SweetAlert2 va ANTES que login.css: su tema es claro y choca con
             la pantalla, asi que login.css lo reviste al final. Al reves
             ganaria el tema claro. */ ?>
    <link rel="stylesheet" href="<?php echo $vendor; ?>css/sweetalert2.min.css">
    <script src="<?php echo $vendor; ?>js/sweetalert2.all.min.js"></script>

    <link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/login.css'); ?>">
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
</head>
<body class="dsl-body">

<div class="dsl-split">

    <!-- ==================== La marca ==================== -->
    <section class="dsl-hero<?php echo $conFoto ? ' dsl-hero--foto' : ''; ?>"
             <?php if ($conFoto): ?>style="--dsl-foto:url('<?php echo htmlspecialchars($fondo, ENT_QUOTES, 'UTF-8'); ?>')"<?php endif; ?>>

        <span class="dsl-hero__trama" aria-hidden="true"></span>

        <div class="dsl-marca">
            <img src="<?php echo $logo; ?>" alt="" class="dsl-marca__logo">
            <p class="dsl-marca__texto">
                <?php echo DS_HUB_NAME; ?>
                <span class="dsl-marca__modulo">Tecnología que impulsa tu deporte</span>
            </p>
        </div>

        <div class="dsl-hero__mensaje">
            <h1 class="dsl-hero__titulo">Bienvenido</h1>
            <div class="dsl-hero__regla"></div>
            <p class="dsl-hero__texto">
                Inicia sesión para gestionar tu club desde
                <b>un solo lugar</b>.
            </p>
        </div>

        <ul class="dsl-features">
            <?php foreach ($funciones as $f): ?>
            <li class="dsl-feature">
                <i class="fas <?php echo $f[0]; ?>" aria-hidden="true"></i>
                <?php echo $f[1]; ?><br><?php echo $f[2]; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>

    <!-- ==================== El formulario ==================== -->
    <section class="dsl-panel">
        <div class="dsl-card">

            <?php
            /*
            | LA MARCA DE LA ESCUELA, EN UNA SOLA PIEZA
            |
            | Aquí había el icono dentro de un círculo y, debajo, el nombre
            | «Caballito Zeballos» escrito a mano en un <h2>. Dos problemas:
            |
            |   1. El logo de la escuela YA trae su nombre dibujado. Ponerlo
            |      otra vez en texto lo repetía, y con una tipografía que no
            |      es la de la marca.
            |   2. El nombre estaba escrito en la plantilla. La escuela puede
            |      cambiárselo desde Core → Organización, y esta pantalla se
            |      habría quedado con el viejo sin que nadie lo notara.
            |
            | Ahora sale de ds_core/assets/img/logo.png, el escudo de la
            | escuela sobre fondo transparente (el que configura Core venía
            | sobre un cuadro blanco que en la tarjeta oscura se veía como
            | una placa). Si ese archivo falta, se usa el de Core,
            | ds_logo_url(), que devuelve la imagen que la escuela subió allí.
            |
            | SI NO HAY LOGO CONFIGURADO NO SE ROMPE NADA
            |
            | ds_logo_url() devuelve cadena vacía cuando no hay ninguno o el
            | archivo se perdió del disco. En ese caso vuelve el emblema con
            | el icono del ecosistema y el nombre en texto —también leído de
            | la base, no escrito aquí—. Es la pantalla más pública del
            | sistema: no puede quedarse sin identidad por un archivo que
            | falte.
            */
            $logoEscuela = is_file(DS_HUB_PATH . 'ds_core/assets/img/logo.png')
                ? $logo
                : (function_exists('ds_logo_url') ? ds_logo_url(0) : '');
            $nombreEscuela = function_exists('ds_nombre_organizacion')
                ? ds_nombre_organizacion(0) : APP_NAME;
            ?>

            <?php if ($logoEscuela !== ''): ?>
                <div class="dsl-card__marca">
                    <img src="<?php echo htmlspecialchars($logoEscuela, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($nombreEscuela, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
            <?php else: ?>
                <div class="dsl-card__emblema">
                    <img src="<?php echo $logo; ?>" alt="">
                </div>
                <h2 class="dsl-card__titulo"><?php
                    echo htmlspecialchars($nombreEscuela, ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php endif; ?>

            <div class="dsl-card__regla"></div>

            <form method="POST" action="" id="formLogin">

                <?php
                /* Testigo anti-CSRF.
                   Sí, en el login. El ataque no es entrar en la cuenta de
                   la víctima: es forzarla a entrar en la DEL ATACANTE, de
                   modo que todo lo que registre después quede en una
                   cuenta ajena. Ver csrf_token() en ds_core/inc/seguridad.php. */
                echo csrf_campo('login');
                ?>

                <div class="dsl-campo">
                    <label class="dsl-campo__etiqueta" for="login_usuario">Usuario</label>
                    <div class="dsl-campo__caja">
                        <i class="fas fa-user dsl-campo__icono" aria-hidden="true"></i>
                        <?php /* maxlength era 15 y el servidor admite 20: los usuarios de
                                16 a 20 caracteres no se podian teclear enteros. */ ?>
                        <input type="text" class="dsl-campo__input"
                               id="login_usuario" name="login_usuario"
                               pattern="[a-zA-Z0-9]{4,20}" maxlength="20"
                               autocomplete="username" autofocus
                               placeholder="Ingresa tu usuario" required>
                    </div>
                </div>

                <div class="dsl-campo">
                    <label class="dsl-campo__etiqueta" for="login_clave">Contraseña</label>
                    <div class="dsl-campo__caja">
                        <i class="fas fa-lock dsl-campo__icono" aria-hidden="true"></i>
                        <?php /* Sin pattern: restringia los caracteres en el navegador y no
                                coincidia con la politica real, asi que se podia fijar en Core
                                una clave imposible de teclear aqui. Los limites salen de la
                                propia politica; el maximo es el de bcrypt, que trunca a 72.

                                POR AHORA SIN MINIMO: las claves heredadas de SoccerEasy pueden
                                ser mas cortas que clave_longitud_minima(), y el minlength del
                                navegador impedia enviarlas. El servidor nunca exigio minimo al
                                entrar, y el minimo sigue vigente al crear o cambiar una clave.
                                Para restaurarlo, volver a poner en este input el atributo
                                minlength con el valor de clave_longitud_minima(). */ ?>
                        <input type="password" class="dsl-campo__input"
                               id="login_clave" name="login_clave"
                               maxlength="<?php echo clave_longitud_maxima(); ?>"
                               autocomplete="current-password"
                               placeholder="Ingresa tu contraseña" required>
                        <button type="button" class="dsl-ojo" id="verClave"
                                aria-label="Mostrar la contraseña" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="dsl-boton" id="botonEntrar">
                    Iniciar sesión
                    <i class="fas fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="dsl-pie__sep"></div>

            <p class="dsl-pie">
                ¿Problemas para entrar? <strong>Contacta al administrador.</strong><br>
            </p>
        </div>
    </section>
</div>

<script>
/* Ver u ocultar la contraseña. Ayuda sobre todo en móvil, donde teclear
   una clave larga a ciegas es la causa habitual de que el intento falle
   —y ahora cada fallo cuenta para el freno de fuerza bruta. */
(function () {
    var boton = document.getElementById('verClave');
    var campo = document.getElementById('login_clave');
    if (!boton || !campo) { return; }

    boton.addEventListener('click', function () {
        var visible = campo.type === 'text';
        campo.type = visible ? 'password' : 'text';
        boton.setAttribute('aria-pressed', visible ? 'false' : 'true');
        boton.setAttribute('aria-label', visible ? 'Mostrar la contraseña' : 'Ocultar la contraseña');
        boton.querySelector('i').className = visible ? 'fas fa-eye' : 'fas fa-eye-slash';
        campo.focus();
    });
})();

/* Un solo envío por pulsación: sin esto, insistir con el botón manda
   varias peticiones y cada una cuenta como un intento fallido más. */
(function () {
    var form  = document.getElementById('formLogin');
    var boton = document.getElementById('botonEntrar');
    if (!form || !boton) { return; }

    form.addEventListener('submit', function () {
        /* El navegador no envía el valor de un botón deshabilitado, pero
           aquí no se envía nada por el botón, así que es seguro. */
        boton.disabled = true;
        boton.querySelector('i').className = 'fas fa-circle-notch fa-spin';
    });
})();
</script>

<?php if ($avisoLogin !== ''): ?>
<script>
/* El mensaje se pinta aquí, no desde el controlador: allí la página aún no
   existe y SweetAlert2 no estaría cargado. json_encode lo escapa, de modo
   que ningún carácter del texto pueda cerrar el <script>. */
Swal.fire({
    icon:  'error',
    title: 'No se pudo iniciar sesión',
    text:  <?php echo json_encode($avisoLogin, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    confirmButtonColor: '#ff7900'
}).then(function () {
    var u = document.getElementById('login_usuario');
    if (u) { u.focus(); }
});
</script>
<?php endif; ?>

</body>
</html>
