<?php

	namespace app\controllers;
	use app\models\mainModel;
	
	class loginController extends mainModel{

		/*----------  Controlador iniciar sesion  ----------*/
		/**
		 * Inicia sesión del usuario.
		 *  - Valida formato de usuario y contraseña.
		 *  - Usa consulta preparada (evita SQL-Injection).
		 *  - Verifica estado y bloqueo.
		 *  - Regenera el ID de sesión.
		 *  - Redirige según el rol.
		 */
		public function iniciarSesionControlador(): void
		{
			// La sesión y los parámetros de su cookie (HttpOnly, SameSite=Lax,
			// Secure sólo bajo HTTPS) se establecen de forma centralizada en
			// app/views/inc/session_start.php, que ya se cargó desde index.php.
			// El require_once lo deja explícito y es idempotente. No duplicar
			// aquí la configuración de la cookie.
			require_once __DIR__ . "/../views/inc/session_start.php";

			/* ----------  0. Testigo anti-CSRF  ----------
			|
			| Lo primero, antes incluso de mirar el usuario: si la petición
			| no viene de una página que este servidor entregó, no hay nada
			| que procesar.
			|
			| Protege del CSRF de inicio de sesión, que consiste en forzar
			| a la víctima a entrar CON LA CUENTA DEL ATACANTE. A partir de
			| ahí todo lo que registre —un pago, un documento— queda en una
			| cuenta que otro controla.
			|
			| El fallo más probable no es un ataque sino una sesión perdida
			| —la pestaña llevaba horas abierta, o el navegador borró la
			| cookie—, así que el mensaje dice qué hacer en vez de acusar.
			*/
			if (!csrf_valido('login')) {
				$this->showError(
					'La sesión del formulario caducó. Recargue la página e inténtelo de nuevo.'
				);
			}

			/* ----------  1. Validación básica de entrada  ---------- */
			$usuario = $_POST['login_usuario'] ?? '';
			$clave   = $_POST['login_clave']   ?? '';

			if ($usuario === '' || $clave === '') {
				$this->showError('Debes rellenar todos los campos.');
			}

			if (!preg_match('/^[a-zA-Z0-9]{4,20}$/', $usuario)) {
				$this->showError('El usuario no cumple el formato solicitado.');
			}

			/*
			| La contrasena NO se valida por formato al entrar.
			|
			| Antes se exigia ^[a-zA-Z0-9$@.\-]{7,100}$, lo que provocaba dos
			| problemas: rechazaba claves perfectamente validas por llevar un
			| simbolo, y no coincidia con lo que aceptaba Core al crearlas, de
			| modo que se podia fijar una contrasena con la que despues era
			| imposible entrar.
			|
			| Filtrar aqui tampoco protege de nada: la consulta va con
			| parametros ligados y la comprobacion es con password_verify().
			| Solo se acota el tamano para no procesar entradas absurdas.
			*/
			if (strlen($clave) > 200) {
				$this->showError('Usuario o contraseña incorrectos.');
			}

			/* ----------  1 bis. Freno a la fuerza bruta  ---------- */
			/* Sin esto se midieron 25 intentos fallidos seguidos sin una
			   sola negativa, a unos 500 por minuto. El freno mira los
			   fallos recientes ANTES de comprobar la clave, para que el
			   coste de bcrypt tampoco se pueda usar como ariete. */
			$espera = 0; $porQue = '';
			if (intentos_frenado($usuario, $espera, $porQue)) {
				intentos_registrar($usuario, false);

				$minutos = (int)ceil($espera / 60);
				$this->showError(
					'Demasiados intentos fallidos. Vuelva a probar en '
					. ($minutos > 1 ? "$minutos minutos." : 'un minuto.')
				);
			}

			/* ----------  2. Consulta preparada  ---------- */
			try {
				$sql = "
					SELECT  usuario_empleadoid,
							empleado_identificacion,
							empleado_nombre,
							empleado_correo,
							empleado_celular,
							empleado_foto,
							sede_nombre AS sede,
							usuario_estado,
							usuario_tienebloqueo,
							usuario_usuario,
							usuario_rolid,
							usuario_clave,
							usuario_id
					FROM    seguridad_usuario
					LEFT    JOIN sujeto_empleado ON empleado_id   = usuario_empleadoid
					LEFT    JOIN general_sede    ON empleado_sedeid = sede_id
					WHERE   usuario_usuario = :usuario
					LIMIT   1";

				$stmt = $this->ejecutarConsulta($sql, ['usuario' => $usuario]);

				if ($stmt->rowCount() !== 1) {
					/* El mensaje genérico no bastaba: sin llegar a bcrypt,
					   una cuenta inexistente respondía en ~9 ms frente a
					   los ~121 ms de una real, y esa diferencia se mide
					   desde fuera. Verificando contra un hash señuelo, los
					   dos caminos cuestan lo mismo. */
					password_verify($clave, intentos_hash_senuelo());
					intentos_registrar($usuario, false);
					$this->showError('Usuario o contraseña incorrectos.');
				}

				$user = $stmt->fetch();

			} catch (\Throwable $e) {
				/* Aquí podrías loguear $e->getMessage() */
				$this->showError('Ocurrió un error inesperado. Inténtalo de nuevo.');
			}

			/* ----------  3. Comprobaciones de estado ---------- */
			/* La clave se comprueba ANTES que el estado. Al revés, "usuario
			   inactivo" se obtenía sin acertar la contraseña y volvía a
			   delatar qué cuentas existen: justo lo que se acaba de cerrar
			   por el lado del tiempo. */
			if (!password_verify($clave, $user['usuario_clave'])) {
				intentos_registrar($usuario, false);
				$this->showError('Usuario o contraseña incorrectos.');
			}
			if ($user['usuario_estado'] === 'I') {
				intentos_registrar($usuario, false);
				$this->showError('Usuario inactivo. Contacte al administrador.');
			}
			if ($user['usuario_tienebloqueo'] === 'S') {
				intentos_registrar($usuario, false);
				$this->showError('Usuario bloqueado. Contacte al administrador.');
			}

			/* ----------  4. Contraseña correcta ---------- */
			/* Queda anotado: además de servir de auditoría, el acierto
			   pone a cero el contador de fallos de esta cuenta. */
			intentos_registrar($usuario, true);

			/* ----------  4 bis. Segundo factor  ----------
			|
			| LA SESIÓN NO SE CREA TODAVÍA.
			|
			| Es el punto entero de un segundo factor: quien acertó la
			| contraseña aún NO está autenticado. Si aquí se rellenara
			| $_SESSION y luego se pidiera el código, bastaría con no
			| responder al segundo paso y navegar a cualquier otra URL para
			| entrar igual, porque las vistas sólo miran si hay sesión.
			|
			| Lo que se guarda es una marca aparte, con su propia caducidad
			| de diez minutos, que sólo sirve para el paso siguiente.
			*/
			if (dosf_activo((int)$user['usuario_id'])) {
				session_regenerate_id(true);
				dosf_pendiente_guardar($user);
				$this->redirect(APP_URL . 'verificar2fa/');
			}

			session_regenerate_id(true);        // Previene Session Fixation

			$this->abrirSesion($user);
		}

		/*==============================================================
		  Segundo paso: el código del segundo factor
		  ==============================================================*/

		/**
		 * Comprueba el código TOTP —o un código de recuperación— de quien
		 * ya superó la contraseña.
		 *
		 * Se llega aquí sólo con la marca que dejó iniciarSesionControlador.
		 * Sin ella no hay nada que verificar y se vuelve al principio: es lo
		 * que impide entrar por esta puerta sin haber pasado por la primera.
		 */
		public function verificar2faControlador(): void
		{
			require_once __DIR__ . "/../views/inc/session_start.php";

			$pendiente = dosf_pendiente();

			if (!$pendiente) {
				$_SESSION['login_aviso'] =
					'La verificación caducó. Vuelva a iniciar sesión.';
				$this->redirect(APP_URL . 'login/');
			}

			if (!csrf_valido('2fa')) {
				$_SESSION['login2fa_aviso'] =
					'La sesión del formulario caducó. Recargue la página.';
				$this->redirect(APP_URL . 'verificar2fa/');
			}

			$usuarioid = (int)$pendiente['usuario_id'];
			$usuario   = (string)$pendiente['usuario'];
			$codigo    = trim((string)($_POST['codigo_2fa'] ?? ''));
			$esRecuperacion = ($_POST['modo'] ?? '') === 'recuperacion';

			if ($codigo === '') {
				$_SESSION['login2fa_aviso'] = 'Escriba el código.';
				$this->redirect(APP_URL . 'verificar2fa/');
			}

			/* El mismo freno que la contraseña. Sin él, seis dígitos son
			   un millón de combinaciones que se recorren en minutos, y el
			   segundo factor no añadiría nada frente a quien ya tiene la
			   contraseña. */
			$espera = 0; $porQue = '';
			if (intentos_frenado($usuario, $espera, $porQue)) {
				intentos_registrar($usuario, false);
				$minutos = (int)ceil($espera / 60);
				$_SESSION['login2fa_aviso'] =
					'Demasiados intentos. Vuelva a probar en '
					. ($minutos > 1 ? "$minutos minutos." : 'un minuto.');
				$this->redirect(APP_URL . 'verificar2fa/');
			}

			$correcto = $esRecuperacion
				? dosf_usar_recuperacion($usuarioid, $codigo)
				: totp_valido(dosf_secreto($usuarioid), $codigo);

			if (!$correcto) {
				intentos_registrar($usuario, false);
				dosf_anotar($usuarioid, 'FALLO',
					$esRecuperacion ? 'Código de recuperación no válido'
					                : 'Código de verificación no válido');

				$_SESSION['login2fa_aviso'] = $esRecuperacion
					? 'Ese código de recuperación no es válido o ya se usó.'
					: 'El código no coincide. Compruebe la hora del teléfono.';
				$this->redirect(APP_URL . 'verificar2fa/');
			}

			/* ----------  Verificado  ---------- */
			intentos_registrar($usuario, true);
			dosf_pendiente_limpiar();

			$user = $this->usuarioPorId($usuarioid);

			if (!$user) {
				$_SESSION['login_aviso'] = 'No se pudo completar el acceso.';
				$this->redirect(APP_URL . 'login/');
			}

			/* Otra vez, ahora que la identidad está completa: el
			   identificador con el que se navegó el paso intermedio no debe
			   ser el de la sesión definitiva. */
			session_regenerate_id(true);

			$this->abrirSesion($user);
		}

		/* ========== Helpers ========== */

		/**
		 * Crea la sesión y manda al Hub.
		 *
		 * Vive aparte porque hay DOS caminos que terminan aquí —con y sin
		 * segundo factor— y tener el array de sesión escrito dos veces es
		 * la forma de que uno de los dos se quede sin un campo.
		 */
		private function abrirSesion(array $user): void
		{
			/* La asignación REEMPLAZA el array entero, no lo mezcla. Es
			   deliberado: así el testigo anti-CSRF del formulario de acceso
			   y la marca del segundo factor desaparecen al entrar y no
			   pueden reutilizarse. Si algún día se cambia por un merge, hay
			   que limpiarlos a mano. */
			$_SESSION = [
				'usuario'        => $user['usuario_usuario'],
				'usuarioid'      => $user['usuario_id'],
				'rol'            => (int)$user['usuario_rolid'],
				'foto'           => $user['empleado_foto'],
				'sede'           => $user['sede'],
				'identificacion' => $user['empleado_identificacion'],
				'usuario_id'     => $user['usuario_empleadoid'],
				/* Sin ficha de empleado se cae al nombre de usuario, NO al id
				   del rol: eso era lo que hacia antes y por eso la cabecera
				   saludaba con un "1" a quien no tuviera empleado asociado. */
				'nombre'         => $user['empleado_nombre'] ?: $user['usuario_usuario'],
			];

			/* Todo usuario aterriza en el Hub del ecosistema, que vive en la
			   raíz DigiSports. Desde allí elige el módulo; cada tarjeta ya
			   resuelve su propia vista de entrada según los permisos del rol
			   (ver ds_core/hub/hubController.php). */
			$this->redirect(DS_HUB_URL);
		}

		/** La misma ficha que busca el acceso, pero por id. */
		private function usuarioPorId(int $usuarioid): ?array
		{
			try {
				$stmt = $this->ejecutarConsulta("
					SELECT  usuario_empleadoid, empleado_identificacion, empleado_nombre,
							empleado_foto, sede_nombre AS sede,
							usuario_estado, usuario_tienebloqueo, usuario_usuario,
							usuario_rolid, usuario_id
					FROM    seguridad_usuario
					LEFT    JOIN sujeto_empleado ON empleado_id     = usuario_empleadoid
					LEFT    JOIN general_sede    ON empleado_sedeid = sede_id
					WHERE   usuario_id = :id
					LIMIT   1", ['id' => $usuarioid]);

				$u = $stmt->fetch();

				/* Se vuelve a mirar el estado: entre la contraseña y el
				   código pueden haber pasado minutos, y en ese hueco un
				   administrador puede haber bloqueado la cuenta. */
				if (!$u || $u['usuario_estado'] === 'I' || $u['usuario_tienebloqueo'] === 'S') {
					return null;
				}

				return $u;
			} catch (\Throwable $e) {
				return null;
			}
		}

		/**
		 * Guarda el aviso y vuelve al formulario.
		 *
		 * Antes imprimía el <script> aquí mismo. Ahora el controlador corre
		 * ANTES de la página, así que no hay ni Swal cargado ni sitio donde
		 * escribir: el mensaje se deja en la sesión y lo pinta la vista.
		 *
		 * De paso se gana el patrón POST-Redirect-GET: al recargar no se
		 * reenvía el formulario con la contraseña dentro.
		 */
		private function showError(string $mensaje): void
		{
			$_SESSION['login_aviso'] = $mensaje;
			$this->redirect(APP_URL . 'login/');
		}

		/** Redirige de forma segura, compatible con cabeceras ya enviadas. */
		private function redirect(string $url): void
		{
			if (headers_sent()) {
				echo "<script>window.location.href='{$url}';</script>";
				echo "<noscript><meta http-equiv='refresh' content='0;url={$url}'></noscript>";
			} else {
				header("Location: {$url}");
			}
			exit;
		}



		/*----------  Controlador cerrar sesion  ----------*/
		public function cerrarSesionControlador() {
			// Inicia la sesión si aún no está activa
			if (session_status() === PHP_SESSION_NONE) {
				session_start();
			}

			// Limpia las variables de sesión
			session_unset();

			// Destruye la sesión
			session_destroy();

			// Asegura que no quede nada en memoria
			$_SESSION = [];    

			// Borra manualmente la cookie de sesión
			if (ini_get("session.use_cookies")) {
				$params = session_get_cookie_params();
				setcookie(session_name(), '', time() - 42000,
					$params["path"], $params["domain"],
					$params["secure"], $params["httponly"]
				);
			}

			// Redirige al login
			$urlLogin = APP_URL . "login/";

			if (headers_sent()) {
				echo "<script>window.location.href='" . $urlLogin . "';</script>";
				echo "<noscript><meta http-equiv='refresh' content='0;url=$urlLogin'></noscript>";
			} else {
				header("Location: $urlLogin");
			}

			exit();
		}


	}