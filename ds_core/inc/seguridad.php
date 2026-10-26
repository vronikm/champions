<?php
/*
|--------------------------------------------------------------------------
| Helpers de seguridad compartidos
|--------------------------------------------------------------------------
| Se carga desde session_start.php, por lo que queda disponible tanto en
| index.php (capa de vistas) como en cualquier endpoint de app/ajax/.
|
| Convencion de roles: 1 = Super Administrador y 2 = Administrador tienen
| acceso total, igual que en app/views/inc/main-sidebar.php. El resto de
| roles se rige por la tabla seguridad_permiso.
*/

if (!function_exists('usuario_autenticado')) {

    /*----------  Sesion  ----------*/

    function usuario_autenticado(): bool
    {
        return isset($_SESSION['usuario']) && $_SESSION['usuario'] !== '';
    }

    function rol_actual(): int
    {
        return (int)($_SESSION['rol'] ?? 0);
    }

    /**
     * Empleado vinculado al usuario de la sesion, o 0 si no tiene ninguno.
     *
     * Es lo que permite a una pantalla saber "de quien" son los datos: los
     * horarios de un profesor cuelgan de su empleado_id, no de su usuario.
     *
     * Ojo con el nombre de la clave: el login guarda usuario_empleadoid en
     * $_SESSION['usuario_id'], que suena a id de usuario y no lo es. El id
     * del usuario esta en $_SESSION['usuarioid'], sin guion bajo.
     */
    function empleado_actual(): int
    {
        return (int)($_SESSION['usuario_id'] ?? 0);
    }

    /** Id del usuario de la sesion (seguridad_usuario.usuario_id). */
    function usuario_actual_id(): int
    {
        return (int)($_SESSION['usuarioid'] ?? 0);
    }

    /**
     * Como se nombra al usuario en pantalla.
     *
     * Manda el nombre de la persona; si no hay ficha de empleado detras, el
     * nombre de usuario. Nunca un numero.
     *
     * Se comprueba que no sea numerico a proposito: el login guardaba antes
     * el id del rol cuando faltaba el empleado, asi que las sesiones ya
     * abiertas siguen arrastrando ese valor. Descartarlo aqui las arregla
     * sin obligar a nadie a volver a entrar.
     */
    function ds_nombre_usuario(): string
    {
        $nombre = trim((string)($_SESSION['nombre'] ?? ''));

        if ($nombre !== '' && !is_numeric($nombre)) {
            return $nombre;
        }

        return trim((string)($_SESSION['usuario'] ?? ''));
    }

    /*----------  Politica de contrasenas  ----------*/

    /**
     * Longitud minima al FIJAR una contrasena.
     *
     * Solo se exige al establecerla, nunca al iniciar sesion: comprobar el
     * minimo en el login dejaria fuera a quien tenga una clave anterior mas
     * corta, aunque la escriba correctamente.
     */
    function clave_longitud_minima(): int
    {
        return 8;
    }

    /**
     * Limite real de bcrypt: password_hash() ignora en silencio lo que pase
     * de 72 bytes, asi que dos claves largas que difieran solo al final se
     * volverian equivalentes. Mejor rechazarlo que aceptarlo a medias.
     */
    function clave_longitud_maxima(): int
    {
        return 72;
    }

    /**
     * Deshace las sustituciones tipograficas mas usadas.
     *
     * "P@ssw0rd" y "password" son la misma contrasena para quien ataca: los
     * diccionarios prueban las dos. Comparando tambien la version deshecha,
     * una sola entrada de la lista cubre todas sus variantes.
     */
    function clave_sin_disfraz(string $clave): string
    {
        return strtr(strtolower($clave), [
            '@' => 'a', '4' => 'a', '8' => 'b', '(' => 'c', '3' => 'e',
            '6' => 'g', '1' => 'i', '!' => 'i', '|' => 'i', '0' => 'o',
            '$' => 's', '5' => 's', '7' => 't', '+' => 't', '2' => 'z',
        ]);
    }

    /**
     * Las contrasenas mas usadas, en un conjunto listo para consultar.
     *
     * El archivo solo guarda las de 8 caracteres o mas: las mas cortas ya
     * las rechaza la regla de longitud. Se lee una vez por peticion, y solo
     * ocurre al FIJAR una contrasena, que es una operacion rara.
     */
    function claves_comunes(): array
    {
        static $lista = null;
        if ($lista !== null) { return $lista; }

        $lista   = [];
        $archivo = __DIR__ . '/../data/claves-comunes.txt';

        if (is_file($archivo)) {
            foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
                $l = trim($l);
                if ($l !== '') { $lista[$l] = true; }
            }
        }
        return $lista;
    }

    /**
     * Valida una contrasena que se va a guardar.
     *
     * No se restringen los CARACTERES a proposito. Limitarlos no aporta
     * seguridad —la consulta va con parametros ligados y la comparacion es
     * con password_verify()— y si empobrece las claves. Lo que se mira es
     * si la contrasena es ADIVINABLE, que es lo que de verdad la rompe:
     * con el freno de fuerza bruta se admiten 20 intentos por hora, y a ese
     * ritmo una clave de diccionario cae.
     *
     * Solo se aplica al FIJAR una contrasena. El login no llama a esta
     * funcion, asi que ninguna clave ya en uso deja de funcionar.
     *
     * $usuario permite rechazar la clave que repite el nombre de la cuenta.
     * $motivo recibe el texto que se le puede mostrar al usuario.
     */
    function clave_valida(string $clave, ?string &$motivo = null, string $usuario = ''): bool
    {
        $minimo = clave_longitud_minima();
        $maximo = clave_longitud_maxima();

        if (strlen($clave) < $minimo) {
            $motivo = "La contraseña debe tener al menos {$minimo} caracteres.";
            return false;
        }
        if (strlen($clave) > $maximo) {
            $motivo = "La contraseña no puede superar {$maximo} caracteres.";
            return false;
        }
        if (strpos($clave, "\0") !== false) {
            $motivo = 'La contraseña contiene un carácter no admitido.';
            return false;
        }

        $normal   = strtolower(trim($clave));
        $sinDisfraz = clave_sin_disfraz($clave);
        $comunes  = claves_comunes();

        if (isset($comunes[$normal]) || isset($comunes[$sinDisfraz])) {
            $motivo = 'Esa contraseña aparece en las listas que se usan para atacar '
                    . 'cuentas. Elija otra que no sea una palabra ni una fecha.';
            return false;
        }

        /* La clave no puede ser el propio nombre de usuario ni contenerlo:
           es lo primero que prueba cualquiera. */
        $u = strtolower(trim($usuario));
        if ($u !== '' && strlen($u) >= 4 && str_contains($normal, $u)) {
            $motivo = 'La contraseña no puede contener el nombre de usuario.';
            return false;
        }

        /*
        | PALABRA PREVISIBLE CON ADORNO
        |
        | La lista sólo guarda contraseñas de 8 caracteres o más, así que
        | «password123», «admin123» o «Champions2024» pasaban: la palabra
        | está en la lista (o es corta, o es el nombre de la escuela) y el
        | adorno numérico es justo lo que prueban primero los ataques. Se
        | quita el adorno del principio y del final, se deshacen los
        | disfraces y las tildes, y si lo que queda es una contraseña común
        | o está hecho SÓLO de palabras previsibles, se rechaza.
        */
        $base = clave_sin_disfraz(clave_sin_tildes(preg_replace('/^[\d\W_]+|[\d\W_]+$/u', '', mb_strtolower(trim($clave), 'UTF-8'))));
        if ($base !== '' && (isset($comunes[$base]) || clave_solo_palabras_previsibles($base))) {
            $motivo = 'Esa contraseña es una palabra fácil de adivinar con números o símbolos '
                    . 'alrededor (como «admin123» o el nombre de la escuela con el año). '
                    . 'Elija una frase o una combinación que no se pueda deducir.';
            return false;
        }

        /* Un solo caracter repetido, aunque llegue a los 8. */
        if (preg_match('/^(.)\1+$/u', $normal)) {
            $motivo = 'La contraseña no puede ser un solo carácter repetido.';
            return false;
        }

        /* Secuencias del teclado o del alfabeto: "12345678", "abcdefgh". */
        $seguidos = 1;
        $largo    = strlen($normal);
        for ($i = 1; $i < $largo; $i++) {
            $paso = ord($normal[$i]) - ord($normal[$i - 1]);
            $seguidos = ($paso === 1 || $paso === -1) ? $seguidos + 1 : 1;
            if ($seguidos >= 6) {
                $motivo = 'La contraseña no puede ser una secuencia seguida '
                        . 'como 12345678 o abcdefgh.';
                return false;
            }
        }

        $motivo = '';
        return true;
    }

    /** Texto de ayuda, para que las pantallas no lo escriban cada una. */
    function clave_regla_texto(): string
    {
        return 'Mínimo ' . clave_longitud_minima() . ' caracteres. Se admite cualquier '
             . 'carácter, incluidos espacios y símbolos. No se aceptan contraseñas '
             . 'de uso común, secuencias, el propio nombre de usuario, ni palabras '
             . 'previsibles con números alrededor (como «admin123» o el nombre de la escuela).';
    }

    /** Sin tildes ni eñes: «Fútbol» y «futbol» son la misma palabra para quien ataca. */
    function clave_sin_tildes(string $texto): string
    {
        return strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
                              'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n']);
    }

    /**
     * Palabras que nadie debería usar como contraseña aquí: las genéricas
     * más cortas que la lista común (que sólo guarda las de 8 o más) y las
     * de ESTA organización —el nombre de la escuela y de cada sede—, que
     * es lo primero que prueba quien ataca un sistema concreto.
     */
    function clave_palabras_previsibles(): array
    {
        static $lista = null;
        if ($lista !== null) { return $lista; }

        $lista = ['admin', 'administrador', 'usuario', 'user', 'root', 'test', 'prueba', 'clave', 'contrasena',
                  'pass', 'password', 'welcome', 'bienvenido', 'hola', 'amor', 'love', 'dios', 'temporal', 'cambiar',
                  'qwerty', 'asdf', 'abc', 'abcd', 'futbol', 'football', 'soccer', 'deporte', 'deportes', 'balon',
                  'gol', 'campeon', 'campeones', 'champion', 'champions', 'escuela', 'academia', 'club', 'liga',
                  'equipo', 'ecuador', 'quito', 'guayaquil', 'cuenca', 'loja', 'digisports', 'digitech', 'sistema'];

        $con = seguridad_conexion();
        if ($con !== null) {
            try {
                $nombres = $con->query("SELECT escuela_nombre FROM general_escuela
                                        UNION ALL SELECT sede_nombre FROM general_sede")->fetchAll(PDO::FETCH_COLUMN);
                foreach ($nombres as $n) {
                    foreach (preg_split('/[^a-z0-9]+/', clave_sin_tildes(mb_strtolower(html_entity_decode((string)$n, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'UTF-8'))) as $p) {
                        if (strlen($p) >= 4) { $lista[] = $p; }
                    }
                }
            } catch (\Throwable $e) {
                // Sin la base, quedan las genéricas: mejor eso que no validar.
            }
        }
        /* Las largas primero: así «champions» se quita entera antes que «champion». */
        $lista = array_values(array_unique($lista));
        usort($lista, static fn($a, $b) => strlen($b) - strlen($a));
        return $lista;
    }

    /** Si el texto (ya sin adorno ni disfraz) está hecho sólo de palabras previsibles. */
    function clave_solo_palabras_previsibles(string $base): bool
    {
        $resto = preg_replace('/[^a-z]+/', '', $base);
        if ($resto === '') { return false; }   /* sólo números o símbolos: lo cubren las otras reglas */
        foreach (clave_palabras_previsibles() as $p) {
            $resto = str_replace(clave_sin_disfraz($p), '', $resto);
            if ($resto === '') { return true; }
        }
        return strlen($resto) <= 2;
    }

    /**
     * Super Administrador: el UNICO rol que pasa por encima del control de
     * acceso. Cualquier otro rol, incluido el Administrador, se rige por lo
     * que tenga concedido en seguridad_rol_modulo y seguridad_permiso.
     */
    function es_superadministrador(): bool
    {
        return rol_actual() === self_rol_superadmin();
    }

    /** Id del rol con acceso total. Centralizado para no repetir el numero. */
    function self_rol_superadmin(): int
    {
        return 1;
    }

    /*----------  Sedes que puede ver la sesion  ----------*/

    /**
     * Las sedes cuya informacion puede ver el usuario de la sesion.
     *
     *     null        todas (tambien las que se creen despues)
     *     [1, 3]      solo esas
     *     []          ninguna
     *
     * UNA SOLA REGLA PARA TODO EL ECOSISTEMA (migracion 072). Antes cada
     * pantalla tenia la suya: los roles 1 y 2 «veian todo» porque el codigo
     * lo decia, y en unas pantallas no tener sede era «todas» y en otras
     * «ninguna». Ahora:
     *   · el superadministrador ve todas;
     *   · usuario_todassedes = 'S' ve todas;
     *   · el resto, sus filas de seguridad_usuario_sede; sin ninguna, nada.
     *     Se asignan en Core › Usuarios.
     *
     * Sin la columna (servidor sin la 072) se conserva el comportamiento
     * anterior: los roles 1 y 2 ven todas.
     */
    function sedes_de_la_sesion(): ?array
    {
        static $cache = [];

        $id      = usuario_actual_id();
        $usuario = (string)($_SESSION['usuario'] ?? '');
        $clave   = $id . '|' . $usuario;
        if (array_key_exists($clave, $cache)) {
            return $cache[$clave];
        }
        if (es_superadministrador()) {
            return $cache[$clave] = null;
        }

        $con = seguridad_conexion();
        if ($con === null || ($id <= 0 && $usuario === '')) {
            return $cache[$clave] = [];
        }

        $donde  = $id > 0 ? 'usuario_id = :u' : 'usuario_usuario = :u';
        $valor  = $id > 0 ? $id : $usuario;
        try {
            $st = $con->prepare("SELECT usuario_id, usuario_rolid, usuario_todassedes FROM seguridad_usuario WHERE $donde LIMIT 1");
            $st->execute([':u' => $valor]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if (!$u) { return $cache[$clave] = []; }
            if (($u['usuario_todassedes'] ?? 'N') === 'S') { return $cache[$clave] = null; }
        } catch (\Throwable $e) {
            /* Sin la migracion 072: la regla de antes. */
            if (in_array(rol_actual(), [1, 2], true)) { return $cache[$clave] = null; }
            $st = $con->prepare("SELECT usuario_id FROM seguridad_usuario WHERE $donde LIMIT 1");
            $st->execute([':u' => $valor]);
            $u = $st->fetch(PDO::FETCH_ASSOC);
            if (!$u) { return $cache[$clave] = []; }
        }

        $st = $con->prepare("SELECT usuariosede_sedeid FROM seguridad_usuario_sede WHERE usuariosede_usuarioid = :u");
        $st->execute([':u' => (int)$u['usuario_id']]);
        $ids = array_values(array_unique(array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN))));
        sort($ids);
        return $cache[$clave] = $ids;
    }

    /** ¿Puede la sesion ver la sede $sede? */
    function sede_en_la_sesion(int $sede): bool
    {
        $permitidas = sedes_de_la_sesion();
        return $permitidas === null || in_array($sede, $permitidas, true);
    }

    /**
     * Condicion SQL de sede para una columna, con los ids en linea (salen de
     * la base y pasan por intval, no del usuario). '1 = 1' si ve todas;
     * '1 = 0' si ninguna.
     */
    function sql_sedes_de_la_sesion(string $columna): string
    {
        $permitidas = sedes_de_la_sesion();
        if ($permitidas === null) { return '1 = 1'; }
        if (!$permitidas)         { return '1 = 0'; }
        return $columna . ' IN (' . implode(',', array_map('intval', $permitidas)) . ')';
    }

    /*----------  Anti-CSRF por validacion de origen  ----------*/

    /**
     * Comprueba que una peticion POST provenga del propio sitio.
     *
     * Se apoya en las cabeceras Origin / Referer: el navegador las envia
     * en toda peticion POST y una pagina atacante no puede falsificarlas
     * por JavaScript. Combinado con la cookie de sesion SameSite=Lax que
     * fija session_start.php, cubre el vector CSRF sin necesidad de
     * insertar un token en las ~100 vistas existentes.
     *
     * Las peticiones GET no se validan: no deben producir efectos.
     */
    function origen_es_valido(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return true;
        }

        $host_app = parse_url(APP_URL, PHP_URL_HOST);

        $origen = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origen === '') {
            $origen = $_SERVER['HTTP_REFERER'] ?? '';
        }

        // POST sin Origin ni Referer: no se puede acreditar la procedencia.
        if ($origen === '') {
            return false;
        }

        return parse_url($origen, PHP_URL_HOST) === $host_app;
    }

    /*----------  Acceso a datos de seguridad  ----------*/

    /** Conexion propia y reutilizable para las consultas de seguridad. */
    function seguridad_conexion(): ?PDO
    {
        static $con = null;
        static $intentado = false;

        if ($intentado) {
            return $con;
        }
        $intentado = true;

        try {
            /* utf8mb4, no utf8.
               En MySQL, «utf8» es el alias de utf8mb3: tres bytes por
               caracter. Todo lo que necesite cuatro —emoji, y buena parte
               de la puntuacion tipografica que llega pegada desde Word—
               se pierde o corrompe en el viaje, y no en la tabla, que ya
               esta en utf8mb4 desde la migracion 041, sino en la propia
               conexion. Es el ultimo sitio donde quedaba el juego viejo. */
            $con = new PDO(
                "mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                defined('DS_DB_INIT_COMANDO')
                    ? [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                       PDO::MYSQL_ATTR_INIT_COMMAND => DS_DB_INIT_COMANDO]
                    : [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            /* NO se desactiva ATTR_EMULATE_PREPARES aqui, aunque seria lo
               deseable: con preparadas nativas MySQL rechaza repetir un
               marcador con el mismo nombre y devuelve los enteros como
               enteros en vez de como cadena. Ambas cosas cambian el
               comportamiento de consultas repartidas por los cuatro
               modulos, y hacerlo de paso en un arreglo de codificacion
               seria colar un cambio de conducta dentro de otro. Queda
               anotado como tarea propia. */
        } catch (\Throwable $e) {
            $con = null;
        }

        return $con;
    }

    /**
     * Modulo del contexto en ejecucion. Cada aplicacion lo declara con la
     * constante DS_MODULO en su config; el Hub no pertenece a ninguno.
     */
    function modulo_actual(): string
    {
        return defined('DS_MODULO') ? DS_MODULO : '';
    }

    /*----------  Nivel 1: acceso a modulos  ----------*/

    /**
     * Modulos habilitados para el rol de la sesion.
     * El Super Administrador (rol 1) los tiene todos por definicion.
     */
    function modulos_permitidos(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        if (es_superadministrador()) {
            $cache = array_keys(ds_modulos_conocidos());
            return $cache;
        }

        $cache = [];
        $con   = seguridad_conexion();
        if ($con === null) {
            return $cache;
        }

        try {
            $sql = $con->prepare(
                "SELECT rolmod_modulo
                   FROM seguridad_rol_modulo
                  WHERE rolmod_rolid  = :rol
                    AND rolmod_estado = 'A'"
            );
            $sql->execute([':rol' => rol_actual()]);
            $cache = $sql->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable $e) {
            $cache = [];
        }

        return $cache;
    }

    function usuario_tiene_modulo(string $modulo): bool
    {
        return es_superadministrador() || in_array($modulo, modulos_permitidos(), true);
    }

    /** Claves de modulo que el ecosistema reconoce. */
    function ds_modulos_conocidos(): array
    {
        return [
            'core'       => 'Core',
            'basketball' => 'Fútbol',
            'arena'      => 'Arena',
            'league'     => 'League',
            'insights'   => 'Insights',
        ];
    }

    /*----------  Nivel 2 y 3: vistas y acciones  ----------*/

    /**
     * Carga, una vez por modulo y peticion, las vistas registradas como
     * item de menu de ese modulo y las que el rol tiene habilitadas, con
     * sus acciones.
     *
     * Devuelve:
     *   [ 'ok' => bool,
     *     'registradas' => string[],
     *     'permitidas'  => [vista => ['ver'=>bool,'crear'=>bool,'editar'=>bool,'eliminar'=>bool]] ]
     */
    function permisos_de_la_sesion(string $modulo = ''): array
    {
        static $cache = [];

        $modulo = $modulo !== '' ? $modulo : modulo_actual();
        if (isset($cache[$modulo])) {
            return $cache[$modulo];
        }

        $datos = ['ok' => false, 'registradas' => [], 'permitidas' => []];
        $con   = seguridad_conexion();

        if ($con === null) {
            // Si la BD no responde no se amplian privilegios: 'ok' queda en
            // false y usuario_tiene_permiso() niega el acceso.
            $cache[$modulo] = $datos;
            return $datos;
        }

        try {
            /* Vistas que son item de menu del modulo: las sujetas a permiso. */
            $sql = $con->prepare(
                "SELECT menu_vista
                   FROM seguridad_menu
                  WHERE menu_estado IN ('A', 'O')
                    AND menu_vista NOT IN ('', 'No')
                    AND (:modulo = '' OR menu_modulo = :modulo2)"
            );
            $sql->execute([':modulo' => $modulo, ':modulo2' => $modulo]);
            $datos['registradas'] = $sql->fetchAll(PDO::FETCH_COLUMN);

            /* Vistas habilitadas para el rol, con el alcance de cada una. */
            $sql = $con->prepare(
                "SELECT m.menu_vista,
                        p.permiso_ver, p.permiso_crear,
                        p.permiso_editar, p.permiso_eliminar,
                        p.permiso_exportar
                   FROM seguridad_permiso p
                   JOIN seguridad_menu    m ON m.menu_id = p.permiso_menuid
                  WHERE p.permiso_rolid  = :rol
                    AND p.permiso_estado = 'A'
                    AND m.menu_estado    IN ('A', 'O')
                    AND (:modulo = '' OR m.menu_modulo = :modulo2)"
            );
            $sql->execute([':rol' => rol_actual(), ':modulo' => $modulo, ':modulo2' => $modulo]);

            foreach ($sql->fetchAll(PDO::FETCH_ASSOC) as $f) {
                $datos['permitidas'][$f['menu_vista']] = [
                    'ver'      => $f['permiso_ver']      === 'S',
                    'crear'    => $f['permiso_crear']    === 'S',
                    'editar'   => $f['permiso_editar']   === 'S',
                    'eliminar' => $f['permiso_eliminar'] === 'S',
                    /* Exportar es la accion por la que la informacion SALE del
                       sistema: verla en pantalla y llevarsela en un Excel son dos
                       decisiones distintas. Ver ds_core/database/045. */
                    'exportar' => $f['permiso_exportar'] === 'S',
                ];
            }

            $datos['ok'] = true;

        } catch (\Throwable $e) {
            $datos = ['ok' => false, 'registradas' => [], 'permitidas' => []];
        }

        $cache[$modulo] = $datos;
        return $datos;
    }

    /**
     * Modo estricto de permisos.
     *
     * Por omision, una vista que no esta registrada en seguridad_menu no se
     * restringe: son las vistas de apoyo de Basketball —formularios, PDF,
     * recibos— cuyo control efectivo esta en el listado desde el que se
     * abren. Es una decision deliberada y ahi se mantiene.
     *
     * Un modulo puede rechazarla declarando DS_PERMISOS_ESTRICTOS en su
     * config/app.php. Entonces lo no registrado se DENIEGA. League lo hace:
     * tendra vistas de sorteo, designacion y carga de resultados, y ahi
     * olvidar registrar una no puede significar dejarla abierta a cualquiera
     * con acceso al modulo.
     *
     * La constante la define el config del modulo, que carga su propio front
     * controller, de modo que el alcance es la peticion en curso y no hace
     * falta mantener ninguna lista central.
     */
    function permisos_estrictos(): bool
    {
        return defined('DS_PERMISOS_ESTRICTOS') && DS_PERMISOS_ESTRICTOS === true;
    }

    /**
     * Indica si el rol de la sesion puede abrir una vista.
     *
     * Regla deliberada: una vista que NO esta registrada en seguridad_menu
     * no se restringe. Son vistas de apoyo (formularios de alta, PDF,
     * perfiles, recibos) que no tienen entrada de menu propia y cuyo
     * control efectivo esta en la vista de listado desde la que se abren.
     */
    function usuario_tiene_permiso(string $vista, string $modulo = ''): bool
    {
        if ($vista === '' || es_superadministrador()) {
            return true;
        }

        $permisos = permisos_de_la_sesion($modulo);

        if (!$permisos['ok']) {
            return false;
        }

        if (!in_array($vista, $permisos['registradas'], true)) {
            return !permisos_estrictos();
        }

        return !empty($permisos['permitidas'][$vista]['ver']);
    }

    /**
     * Comprueba una accion concreta sobre una vista.
     * $accion: 'ver' | 'crear' | 'editar' | 'eliminar'
     *
     * Las vistas de apoyo (no registradas como menu) heredan el alcance de
     * quien las abre, por lo que no se restringen aqui.
     */
    function puede(string $accion, string $vista, string $modulo = ''): bool
    {
        if (es_superadministrador()) {
            return true;
        }

        $permisos = permisos_de_la_sesion($modulo);

        if (!$permisos['ok']) {
            return false;
        }

        if (!in_array($vista, $permisos['registradas'], true)) {
            return !permisos_estrictos();
        }

        return !empty($permisos['permitidas'][$vista][$accion]);
    }

    function puede_crear(string $vista, string $modulo = ''): bool    { return puede('crear', $vista, $modulo); }
    function puede_editar(string $vista, string $modulo = ''): bool   { return puede('editar', $vista, $modulo); }
    function puede_eliminar(string $vista, string $modulo = ''): bool { return puede('eliminar', $vista, $modulo); }
    function puede_exportar(string $vista, string $modulo = ''): bool { return puede('exportar', $vista, $modulo); }

    /*----------  Accion que exige cada operacion AJAX  ----------*/

    /**
     * Operaciones que cualquier usuario autenticado puede ejecutar sobre si
     * mismo, con independencia de los permisos de la vista donde viven.
     *
     * CAMBIAR_CLAVE es el caso claro: el formulario esta en la barra
     * superior de todas las pantallas, pero su endpoint pertenece a
     * usuarioAjax (vista userList). Sin esta excepcion, quien no administre
     * usuarios no podria cambiar su propia contrasena.
     */
    function operacion_es_autoservicio(string $operacion): bool
    {
        return in_array(strtoupper($operacion), ['CAMBIAR_CLAVE'], true);
    }

    /**
     * Deduce la accion (ver / crear / editar / eliminar) que exige una
     * operacion AJAX a partir de su nombre.
     *
     * El despacho de los endpoints usa nombres muy regulares
     * (registrar / actualizar / eliminar...), asi que una sola regla
     * central evita tener que instrumentar los controladores uno a uno.
     *
     * Una operacion que no encaje en ningun patron se trata como
     * ESCRITURA: es la opcion prudente, porque dejar pasar una operacion
     * destructiva sin clasificar seria peor que exigir un permiso de mas.
     */
    function accion_de_operacion(string $operacion): string
    {
        $op = strtolower(trim($operacion));

        $patrones = [
            'eliminar' => '^(eliminar|borrar|anular|quitar|remover|desvincular)',
            'ver'      => '^(consultar|buscar|cargar|listar|ver|historial|probar|descargar|estadistica|reporte|obtener|generar_pdf)',
            'crear'    => '^(registrar|crear|agregar|asignar|generar|subir|emitir|importar|analizar|nuevo|asistencia)',
            'editar'   => '^(actualizar|editar|modificar|cambiar|vincular|estado|guardar|descuento|pago|regenerar|descargo|coordenadas|enviar)',
        ];

        foreach ($patrones as $accion => $patron) {
            if (preg_match('~' . $patron . '~', $op)) {
                return $accion;
            }
        }

        return 'editar';
    }

    /**
     * Operacion solicitada en la peticion actual.
     *
     * Los endpoints declaran la operacion en un campo POST cuyo nombre
     * empieza por "modulo_" (modulo_alumno, modulo_pagos...). Se busca asi
     * para no tener que enumerar los 22 endpoints.
     */
    function operacion_de_peticion(): string
    {
        foreach ($_POST as $clave => $valor) {
            if (is_string($valor) && strpos($clave, 'modulo_') === 0) {
                return $valor;
            }
        }

        return '';
    }

    /**
     * Primera vista permitida del modulo, util como destino de aterrizaje.
     * Devuelve '' si el rol no tiene ninguna.
     */
    function primera_vista_permitida(string $modulo = ''): string
    {
        $permisos = permisos_de_la_sesion($modulo);

        foreach ($permisos['permitidas'] as $vista => $acciones) {
            if (!empty($acciones['ver'])) {
                return $vista;
            }
        }

        return '';
    }

    /*----------  Medios con datos personales  ----------*/

    /**
     * Carpetas cuyo contenido son datos personales y por tanto NO pueden
     * servirse directamente por HTTP: fotos de alumnos (menores de edad),
     * cedulas escaneadas, fotos de empleados y comprobantes de pago.
     *
     * Cada clave es el tipo publico que viaja en la URL; el valor es la
     * carpeta real bajo app/views/. Se sirven a traves de app/media.php,
     * que exige sesion activa.
     *
     * El material de marca (iconos, plantillas de carnet, logos de sede,
     * rubricas, fotos de equipos y torneos) NO figura aqui: no contiene
     * datos personales y sigue siendo publico.
     */
    function medios_catalogo(): array
    {
        return [
            'alumno'   => 'imagenes/fotos/alumno/',
            'cedula'   => 'imagenes/cedulas/',
            'empleado' => 'imagenes/fotos/empleado/',
            'usuario'  => 'fotos/usuario/',
            'pago'     => 'imagenes/pagos/',
            'ingreso'  => 'imagenes/ingresos/',
            'fingreso' => 'imagenes/fotos/ingresos/',
            'fegreso'  => 'imagenes/fotos/egresos/',
        ];
    }

    /**
     * URL de un medio protegido.
     *
     * Los medios personales del ecosistema viven hoy en el modulo
     * Basketball, que es quien expone el proxy app/media.php. Por eso se
     * ancla a DS_FUTBOL_URL y no a APP_URL: asi la funcion devuelve la
     * misma URL tanto si la llama el Hub como si la llama el propio modulo.
     *
     * Cuando Arena o League tengan medios propios, cada uno expondra su
     * proxy y este helper recibira el modulo como parametro.
     *
     * Si el archivo viene vacio, el tipo no existe o el archivo YA NO ESTA
     * EN DISCO, devuelve la imagen generica, evitando una peticion que
     * igualmente fallaria.
     *
     * EL ARCHIVO AUSENTE NO ES UN CASO RARO
     *
     * La base guarda el nombre de la foto, no la foto. Si el archivo se
     * borro, se restauro un respaldo de la base sin el de las imagenes o se
     * migro de servidor a medias, el nombre sigue ahi y el archivo no. Sin
     * esta comprobacion el navegador pide la imagen, media.php arranca la
     * sesion entera para acabar respondiendo 404, y el usuario ve el icono
     * de imagen rota. Mirar el disco aqui es mas barato que ese viaje.
     *
     * NO CAMBIA NADA DEL LADO DE media.php: alli el 404 sigue siendo el
     * mismo para un archivo ausente, un tipo desconocido o un intento de
     * salirse de la carpeta. Que las tres cosas se respondan igual es
     * deliberado y no debe tocarse.
     */
    function media_url(string $tipo, ?string $archivo): string
    {
        /*
        | La generica lleva la fecha del archivo detras.
        |
        | Es la imagen que sale alli donde falta un medio, o sea en cientos de
        | sitios. Cuando la escuela cambia su marca se sustituye ESE archivo,
        | y sin la marca de version quien ya visito el sistema seguiria viendo
        | la anterior. Paso: se reemplazo el escudo de otra institucion y
        | seguia apareciendo.
        */
        $generica = DS_FUTBOL_URL . 'app/views/dist/img/foto.jpg';
        $discoGen = DS_HUB_PATH . 'ds_futbol' . DIRECTORY_SEPARATOR . 'app'
                  . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR . 'dist'
                  . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'foto.jpg';
        if (is_file($discoGen)) {
            $generica .= '?v=' . filemtime($discoGen);
        }
        $archivo  = basename(trim((string)$archivo));
        $carpeta  = medios_catalogo()[$tipo] ?? null;

        if ($archivo === '' || $carpeta === null) {
            return $generica;
        }

        /* Las carpetas del catalogo cuelgan de app/views/ de Basketball,
           que es donde vive el proxy. */
        $ruta = DS_HUB_PATH . 'ds_futbol' . DIRECTORY_SEPARATOR
              . 'app' . DIRECTORY_SEPARATOR . 'views' . DIRECTORY_SEPARATOR
              . str_replace('/', DIRECTORY_SEPARATOR, $carpeta) . $archivo;

        if (!is_file($ruta)) {
            return $generica;
        }

        return DS_FUTBOL_URL . 'app/media.php?t=' . rawurlencode($tipo)
                                 . '&f=' . rawurlencode($archivo);
    }

    /*==================================================================
      Freno a los intentos de acceso
      ==================================================================
      Se midió el login sin freno: 25 intentos fallidos seguidos, ninguno
      rechazado, a unos 500 por minuto sostenidos. Contra una clave débil
      eso son horas, no años.

      El freno es por VENTANA DE TIEMPO y no una marca permanente. Un
      bloqueo que hubiera que levantar a mano sería un arma en manos del
      atacante: bastaría con fallar cinco veces contra la cuenta del
      administrador para dejarlo fuera. Pasada la ventana, se reabre solo.

      Se cuenta por DOS vías porque cada una tapa el hueco de la otra:
        · por usuario, contra quien insiste sobre una cuenta concreta
          desde muchas IP;
        · por IP, contra quien barre muchos usuarios desde un sitio.
    */

    /** Fallos tolerados sobre una misma cuenta antes de frenar. */
    function intentos_max_usuario(): int { return 5; }

    /** Fallos tolerados desde una misma IP, sumando todas las cuentas. */
    function intentos_max_ip(): int { return 20; }

    /** Minutos que dura la ventana y, por tanto, la espera máxima. */
    function intentos_ventana_minutos(): int { return 15; }

    /** Días que se conserva la bitácora antes de borrarse sola. */
    function intentos_dias_retencion(): int { return 90; }

    /**
     * IP de origen en binario, lista para VARBINARY(16).
     *
     * Sólo se mira REMOTE_ADDR. Las cabeceras X-Forwarded-For las pone
     * quien hace la petición y se falsean sin esfuerzo: darles crédito
     * convertiría el freno por IP en un adorno. Si algún día hay un proxy
     * delante, hay que resolver la IP real en el servidor web, no aquí.
     */
    function intentos_ip_binaria(): ?string
    {
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }
        $bin = @inet_pton($ip);
        return $bin === false ? null : $bin;
    }

    /** Deja constancia del intento, haya salido bien o mal. */
    function intentos_registrar(string $usuario, bool $exito): void
    {
        $con = seguridad_conexion();
        if ($con === null) { return; }

        try {
            $st = $con->prepare(
                "INSERT INTO seguridad_intento_acceso
                    (intento_usuario, intento_ip, intento_exito)
                 VALUES (:u, :ip, :e)"
            );
            $st->bindValue(':u',  mb_substr($usuario, 0, 20));
            $st->bindValue(':ip', intentos_ip_binaria(), PDO::PARAM_LOB);
            $st->bindValue(':e',  $exito ? 1 : 0, PDO::PARAM_INT);
            $st->execute();

            intentos_purgar($con);

        } catch (\Throwable $e) {
            /* Que la bitácora falle no puede impedir entrar a quien tiene
               credenciales correctas. */
        }
    }

    /**
     * Borra lo que ya no sirve de la bitácora.
     *
     * Va aquí, de propina en algunos registros, y NO en una tarea
     * programada: el despliegue puede no tener cron, o tenerlo y que nadie
     * se acuerde de darlo de alta, y entonces la tabla crece para siempre.
     * Enganchada al propio login, se mantiene sola esté donde esté.
     *
     * Se lanza una vez de cada 200 aproximadamente. Con un ataque en curso
     * eso son varias limpiezas por hora; en uso normal, uno de cada tantos
     * inicios de sesión paga un DELETE que no borra nada y que el índice
     * por fecha resuelve al instante.
     */
    function intentos_purgar(PDO $con, bool $ahora = false): int
    {
        if (!$ahora && random_int(1, 200) !== 1) { return -1; }

        try {
            $st = $con->prepare(
                "DELETE FROM seguridad_intento_acceso
                  WHERE intento_fecha < NOW() - INTERVAL :d DAY"
            );
            $st->bindValue(':d', intentos_dias_retencion(), PDO::PARAM_INT);
            $st->execute();
            return $st->rowCount();
        } catch (\Throwable $e) {
            return -1;
        }
    }

    /**
     * Retrato de los intentos fallidos recientes.
     *
     * Sirve para avisar: hasta ahora el sistema anotaba el ataque y no se
     * lo contaba a nadie. Devuelve los numeros y, sobre todo, si lo visto
     * merece que alguien lo mire.
     */
    function intentos_resumen(int $horas = 24): array
    {
        $vacio = ['fallos' => 0, 'usuarios' => 0, 'ips' => 0,
                  'inexistentes' => 0, 'horas' => $horas, 'alarma' => false];

        $con = seguridad_conexion();
        if ($con === null) { return $vacio; }

        try {
            $st = $con->prepare(
                "SELECT COUNT(*) fallos,
                        COUNT(DISTINCT intento_usuario) usuarios,
                        COUNT(DISTINCT intento_ip) ips,
                        /* Tanteos contra cuentas que NO existen: es la
                           firma de un barrido, no de alguien que olvido
                           su clave. */
                        COUNT(DISTINCT CASE WHEN u.usuario_id IS NULL
                                            THEN i.intento_usuario END) inexistentes
                   FROM seguridad_intento_acceso i
                   LEFT JOIN seguridad_usuario u ON u.usuario_usuario = i.intento_usuario
                  WHERE i.intento_exito = 0
                    AND i.intento_fecha >= NOW() - INTERVAL :h HOUR"
            );
            $st->bindValue(':h', $horas, PDO::PARAM_INT);
            $st->execute();
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return $vacio;
        }

        $resumen = [
            'fallos'       => (int)($r['fallos'] ?? 0),
            'usuarios'     => (int)($r['usuarios'] ?? 0),
            'ips'          => (int)($r['ips'] ?? 0),
            'inexistentes' => (int)($r['inexistentes'] ?? 0),
            'horas'        => $horas,
        ];

        /* Se avisa cuando el patron no se parece a un despiste. Un usuario
           que se equivoca tres veces no debe generar ninguna alarma; que
           alguien tantee cuentas inexistentes, si. */
        $resumen['alarma'] = $resumen['inexistentes'] >= 3
                          || $resumen['fallos']       >= 30
                          || $resumen['usuarios']     >= 5;

        return $resumen;
    }

    /**
     * ¿Hay que frenar este intento?
     *
     * Devuelve true y, por referencia, los segundos que faltan para que
     * la ventana se abra de nuevo, y por cuál de las dos vías se frenó.
     */
    function intentos_frenado(string $usuario, ?int &$segundos = null, ?string &$motivo = null): bool
    {
        $segundos = 0;
        $motivo   = '';

        $con = seguridad_conexion();
        if ($con === null) { return false; }

        $ventana = intentos_ventana_minutos();

        try {
            /* Sólo cuentan los fallos POSTERIORES al último acierto: quien
               ya entró bien parte de cero, y no arrastra los tropiezos de
               antes de acordarse de su clave. */
            $st = $con->prepare(
                "SELECT COUNT(*) fallos, TIMESTAMPDIFF(SECOND, NOW(), MIN(intento_fecha) + INTERVAL :v2 MINUTE) espera
                   FROM seguridad_intento_acceso
                  WHERE intento_usuario = :u
                    AND intento_exito   = 0
                    AND intento_fecha  >= NOW() - INTERVAL :v1 MINUTE
                    AND intento_fecha  > COALESCE((
                            SELECT MAX(intento_fecha) FROM seguridad_intento_acceso
                             WHERE intento_usuario = :u2 AND intento_exito = 1
                        ), '1000-01-01')"
            );
            $st->execute([':u' => $usuario, ':u2' => $usuario, ':v1' => $ventana, ':v2' => $ventana]);
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            if ((int)($r['fallos'] ?? 0) >= intentos_max_usuario()) {
                $segundos = max(1, (int)$r['espera']);
                $motivo   = 'usuario';
                return true;
            }

            $ip = intentos_ip_binaria();
            if ($ip === null) { return false; }

            $st = $con->prepare(
                "SELECT COUNT(*) fallos, TIMESTAMPDIFF(SECOND, NOW(), MIN(intento_fecha) + INTERVAL :v2 MINUTE) espera
                   FROM seguridad_intento_acceso
                  WHERE intento_ip     = :ip
                    AND intento_exito  = 0
                    AND intento_fecha >= NOW() - INTERVAL :v1 MINUTE"
            );
            $st->bindValue(':ip', $ip, PDO::PARAM_LOB);
            $st->bindValue(':v1', $ventana, PDO::PARAM_INT);
            $st->bindValue(':v2', $ventana, PDO::PARAM_INT);
            $st->execute();
            $r = $st->fetch(PDO::FETCH_ASSOC) ?: [];

            if ((int)($r['fallos'] ?? 0) >= intentos_max_ip()) {
                $segundos = max(1, (int)$r['espera']);
                $motivo   = 'ip';
                return true;
            }
        } catch (\Throwable $e) {
            /* Ante un fallo de la bitácora no se cierra el paso: se deja
               pasar a la comprobación de contraseña, que es la barrera
               que de verdad importa. */
            return false;
        }

        return false;
    }

    /**
     * Hash de descarte contra el que verificar cuando el usuario no
     * existe.
     *
     * Sin esto, una cuenta inexistente responde en ~9 ms y una real en
     * ~121 ms, porque sólo la segunda llega a ejecutar bcrypt. Esa
     * diferencia, medida desde fuera, revela qué usuarios existen por
     * mucho que el mensaje de error sea el mismo para todos. Verificando
     * siempre contra un hash real, los dos caminos cuestan lo mismo.
     */
    function intentos_hash_senuelo(): string
    {
        /* Hash real de una cadena que nadie va a teclear. Se deja escrito
           en lugar de generarlo en cada petición: password_hash() cuesta
           lo mismo que la propia verificación y duplicaría el tiempo.

           Tiene que ser un bcrypt BIEN FORMADO. Uno inventado a mano pasa
           por password_get_info() como "unknown", y aunque hoy consuma un
           tiempo parecido, no hay ninguna garantía de que siga siendo así. */
        return '$2y$10$7zxSdOMoTt4ztK41elEZg.B0DbZ9I0oBclQ8zaTnFwxdheDegW6p6';
    }

    /*==================================================================
      Testigo anti-CSRF para formularios

      QUÉ PROBLEMA RESUELVE, Y POR QUÉ NO BASTA CON origen_es_valido()

      origen_es_valido() mira las cabeceras Origin y Referer, y protege
      bien las peticiones AJAX del ecosistema. Pero un formulario HTML
      enviado desde otro sitio puede llegar SIN Referer —basta con
      referrerpolicy="no-referrer"— y esa función deja pasar la petición
      sin cabeceras a propósito, porque hay navegadores y proxis que las
      quitan y bloquearlas rompería el uso legítimo.

      El testigo no depende de cabeceras: es un valor secreto que sólo
      conoce quien recibió la página. Un sitio ajeno puede hacer que el
      navegador de la víctima envíe el formulario, pero no puede leer el
      valor para incluirlo.

      POR QUÉ EL LOGIN TAMBIÉN LO NECESITA

      Suena raro proteger un formulario al que se entra sin sesión, pero
      el ataque existe y se llama CSRF de inicio de sesión: el atacante
      fuerza a la víctima a entrar con LA CUENTA DEL ATACANTE. A partir de
      ahí, todo lo que la víctima haga —registrar un pago, subir un
      documento, guardar una tarjeta— queda dentro de una cuenta ajena, a
      la vista de quien la controla.

      UN TESTIGO POR PROPÓSITO

      El de un formulario no sirve para otro. Si uno se filtra —por un
      Referer, por una captura de pantalla, por un registro—, no se
      convierte en una llave maestra.
      ==================================================================*/

    /**
     * Testigo del proposito indicado, creandolo si aun no existe.
     *
     * Se conserva entre recargas a proposito. Regenerarlo en cada vista
     * rompe la navegacion con dos pestanas abiertas: la primera se queda
     * con un valor caducado y su envio se rechaza sin que el usuario haya
     * hecho nada raro.
     */
    function csrf_token(string $proposito = 'general'): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            /* Sin sesion no hay donde guardarlo. Se devuelve vacio y
               csrf_valido() lo rechazara: es preferible a inventar un
               valor que no se pueda comprobar despues. */
            return '';
        }

        if (empty($_SESSION['ds_csrf'][$proposito])) {
            $_SESSION['ds_csrf'][$proposito] = bin2hex(random_bytes(32));
        }

        return $_SESSION['ds_csrf'][$proposito];
    }

    /** Campo oculto listo para pegar dentro de un <form>. */
    function csrf_campo(string $proposito = 'general'): string
    {
        return '<input type="hidden" name="ds_csrf" value="'
             . htmlspecialchars(csrf_token($proposito), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Comprueba el testigo recibido.
     *
     * hash_equals y no ===: la comparacion normal de cadenas se detiene en
     * el primer byte distinto, y ese tiempo, medido muchas veces, permite
     * ir adivinando el valor byte a byte. hash_equals tarda lo mismo
     * acierte o no.
     */
    function csrf_valido(string $proposito = 'general', ?string $enviado = null): bool
    {
        $esperado = $_SESSION['ds_csrf'][$proposito] ?? '';
        $recibido = $enviado ?? (string)($_POST['ds_csrf'] ?? '');

        if ($esperado === '' || $recibido === '') {
            return false;
        }

        return hash_equals($esperado, $recibido);
    }

    /**
     * Retira el testigo de un proposito.
     *
     * Se llama tras completar la operacion que protegia, para que el mismo
     * valor no sirva dos veces. NO se llama en cada fallo: eso invalidaria
     * la pagina que el usuario tiene delante y convertiria un error de
     * tecleo en «recargue y empiece de nuevo».
     */
    function csrf_renovar(string $proposito = 'general'): void
    {
        unset($_SESSION['ds_csrf'][$proposito]);
    }

    /*======================================================================
      Cambio de contraseña obligatorio
      ======================================================================
      Una contraseña que puso otra persona —la del alta, o la que se
      restablece y se envía por correo o WhatsApp— queda marcada en
      seguridad_usuario.usuario_cambiaclave = 'S'. El login lo pasa a la
      sesión (debe_cambiar_clave) y, mientras siga, ninguna pantalla ni
      petición del ecosistema se atiende salvo la de cambiarla y la de
      salir. Antes la columna se guardaba al crear el usuario pero nadie la
      leía: la «temporal» valía para siempre.
      ====================================================================*/

    /** Si la sesión tiene pendiente cambiar la contraseña. */
    function clave_cambio_pendiente(): bool
    {
        return !empty($_SESSION['debe_cambiar_clave']);
    }

    /**
     * Cambia la contraseña de la PROPIA cuenta, comprobando la actual.
     *
     * La nueva pasa por clave_valida() y no puede ser la misma: si no, el
     * cambio obligatorio se cumpliría volviendo a escribir la temporal.
     * Al cambiarla se quita la marca de cambio pendiente, en la base y en
     * la sesión. Devuelve true, o false con el motivo.
     */
    function clave_cambiar_propia(int $usuarioid, string $actual, string $nueva,
                                  string $confirmar, ?string &$motivo = null): bool
    {
        if ($usuarioid <= 0 || $actual === '' || $nueva === '' || $confirmar === '') {
            $motivo = 'Complete los tres campos.';
            return false;
        }
        if ($nueva !== $confirmar) {
            $motivo = 'La nueva contraseña y su confirmación no coinciden.';
            return false;
        }
        if (!clave_valida($nueva, $motivo, (string)($_SESSION['usuario'] ?? ''))) {
            return false;
        }

        $con = seguridad_conexion();
        if ($con === null) {
            $motivo = 'No hay conexión con la base de datos. Inténtelo de nuevo.';
            return false;
        }
        $st = $con->prepare("SELECT usuario_clave FROM seguridad_usuario WHERE usuario_id = :id AND usuario_estado <> 'E'");
        $st->execute([':id' => $usuarioid]);
        $hash = $st->fetchColumn();
        if ($hash === false || !password_verify($actual, (string)$hash)) {
            $motivo = 'La contraseña actual no es correcta.';
            return false;
        }
        if (password_verify($nueva, (string)$hash)) {
            $motivo = 'La nueva contraseña tiene que ser distinta de la actual.';
            return false;
        }

        $up = $con->prepare("UPDATE seguridad_usuario
                                SET usuario_clave = :c, usuario_fechacambioclave = NOW(), usuario_cambiaclave = 'N'
                              WHERE usuario_id = :id");
        $up->execute([':c' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $usuarioid]);

        unset($_SESSION['debe_cambiar_clave']);
        return true;
    }

    /**
     * Si la sesión debe cambiar la contraseña, sólo deja pasar la página
     * de cambiarla (Hub, ?p=clave), su envío y la salida. Lo demás se
     * redirige allí; una petición AJAX recibe JSON, porque una redirección
     * a HTML la leería el fetch() como un error de conexión.
     *
     * La llama session_start.php, que cargan TODOS los puntos de entrada
     * de todos los módulos: no hay que acordarse de ponerla en cada uno.
     */
    function clave_exigir_cambio_si_pendiente(): void
    {
        if (PHP_SAPI === 'cli' || !clave_cambio_pendiente() || !defined('DS_HUB_URL')) {
            return;
        }

        $script = str_replace('\\', '/', (string)(realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: ''));
        $hub    = defined('DS_HUB_PATH') ? str_replace('\\', '/', (string)(realpath(DS_HUB_PATH . 'index.php') ?: '')) : '';
        if ($hub !== '' && $script === $hub) {
            $p = (string)($_GET['p'] ?? '');
            if ($p === 'clave') { return; }
            if ($p === 'seguridadAjax' && ($_POST['modulo_hub'] ?? '') === 'cambiarClave') { return; }
        }
        /* Salir siempre se puede. */
        if (stripos((string)($_GET['views'] ?? ''), 'logOut') === 0) { return; }

        $destino = DS_HUB_URL . '?p=clave';
        $esAjax  = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
                || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                || str_contains($_SERVER['REQUEST_URI'] ?? '', '/ajax/')
                || stripos(basename($script), 'ajax') !== false
                || ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

        if ($esAjax) {
            if (!headers_sent()) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'ok'     => false,
                'tipo'   => 'redireccionar',
                'icono'  => 'warning',
                'titulo' => 'Cambie su contraseña',
                'texto'  => 'Antes de seguir tiene que cambiar la contraseña que le enviaron.',
                'url'    => $destino,
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        header('Location: ' . $destino);
        exit();
    }
}
