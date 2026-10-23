<?php

	namespace app\controllers;
	use app\models\mainModel;

	/**
	 * Revisión de las fotos de los alumnos.
	 *
	 *
	 * QUÉ PROBLEMA RESUELVE
	 *
	 * La base guarda el NOMBRE de la foto, no la foto. Son dos cosas que
	 * pueden separarse y se separan: se restaura un respaldo de la base sin el
	 * de las imágenes, se migra de servidor a medias, alguien limpia la
	 * carpeta. El nombre sigue en la ficha y el archivo ya no está.
	 *
	 * Hoy eso NO se nota. media_url() comprueba el disco y, si el archivo
	 * falta, devuelve la foto genérica —comportamiento correcto, porque evita
	 * un icono roto y una petición que iba a fallar—, pero el efecto es que un
	 * alumno sin archivo se ve exactamente igual que uno que nunca tuvo foto,
	 * y nadie se entera hasta que hay que imprimir un carné.
	 *
	 * Esta pantalla existe para hacer visible justo eso. Distingue TRES
	 * estados donde la interfaz normal sólo enseña dos:
	 *
	 *   OK        la ficha tiene nombre y el archivo está en disco
	 *   AUSENTE   la ficha tiene nombre y el archivo NO está   ← el invisible
	 *   SIN       la ficha no tiene nombre: nunca se subió foto
	 *
	 *
	 * POR QUÉ UN CONTROLADOR APARTE Y NO MÁS MÉTODOS EN alumnoController
	 *
	 * Porque alumnoController pasa de mil setecientas líneas y mezcla el alta,
	 * la baja, los pagos, la foto del USUARIO —que ni siquiera es la del
	 * alumno: actualizarFotoAlumnoControlador() escribe en la tabla usuario— y
	 * media docena de asuntos más. Añadir ahí lo que es un asunto nuevo y
	 * cerrado sólo agranda ese nudo.
	 *
	 *
	 * DATOS PERSONALES DE MENORES
	 *
	 * Esta pantalla enseña caras de niños en cuadrícula, que es más exposición
	 * que la ficha de uno en uno. De ahí tres decisiones:
	 *
	 *   · el alcance por sede se aplica SIEMPRE, no como filtro que el usuario
	 *     elige, sino como techo que no puede levantar;
	 *   · no hay exportación ni descarga masiva;
	 *   · las miniaturas salen por app/media.php, que exige sesión, y no por
	 *     una URL directa a la carpeta —que además está cerrada por .htaccess.
	 */
	class fotoAlumnoController extends mainModel {

		/** Los tres estados posibles. Centralizados: no se escriben sueltos. */
		const OK      = 'OK';
		const AUSENTE = 'AUSENTE';
		const SIN     = 'SIN';

		/** Carpeta real de las fotos, desde la raíz del módulo. */
		private function carpeta(): string {
			return __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
			     . 'views' . DIRECTORY_SEPARATOR . 'imagenes' . DIRECTORY_SEPARATOR
			     . 'fotos' . DIRECTORY_SEPARATOR . 'alumno' . DIRECTORY_SEPARATOR;
		}

		/**
		 * Sedes que este usuario puede ver. Vacío = todas.
		 *
		 * Mismo criterio que sedesDelUsuario() en insightsController, y no por
		 * gusto de copiar: las dos pantallas leen el mismo padrón y tienen que
		 * responder lo mismo a «qué puede ver esta persona».
		 *
		 *
		 * DOS REGLAS QUE PARECEN UNA
		 *
		 *   · Lista VACÍA en seguridad_usuario_sede = sin restricción, no
		 *     «ninguna sede». Confundirlas deja sin ver nada a quien no tiene
		 *     asignación explícita, que es la mayoría de los administradores.
		 *
		 *   · El superadministrador queda exento ANTES de mirar la tabla.
		 *     Aquí no es un detalle: un superadministrador puede tener una
		 *     sede asignada, y sin esta salida temprana el rol que debe verlo
		 *     todo veía sólo los alumnos de esa sede; el resto no aparecía en
		 *     ningún sitio ni en los contadores. Se detectó probando esta
		 *     misma pantalla.
		 */
		public function sedesPermitidas(): array {
			if (function_exists('es_superadministrador') && es_superadministrador()) {
				return [];
			}

			$id = (int)($_SESSION['usuarioid'] ?? 0);
			if ($id <= 0) { return [-1]; }          /* sin sesión, nada */

			$filas = $this->ejecutarConsulta(
				"SELECT usuariosede_sedeid FROM seguridad_usuario_sede
				  WHERE usuariosede_usuarioid = :u",
				[':u' => $id])->fetchAll();

			return array_map(static fn($f) => (int)$f['usuariosede_sedeid'], $filas);
		}

		/** Las sedes del desplegable, ya recortadas al alcance del usuario. */
		public function sedes(): array {
			$permitidas = $this->sedesPermitidas();

			/* general_sede NO tiene columna de estado: una sede se retira
			   borrándola, no marcándola. Filtrar por sede_estado revienta la
			   consulta, y sólo se nota en esta rama —la del usuario sin
			   restricción—, que es la que menos se prueba. Pasó. */
			if ($permitidas === []) {
				return $this->ejecutarConsulta(
					"SELECT sede_id, sede_nombre FROM general_sede
					  ORDER BY sede_nombre")->fetchAll();
			}

			/* Marcadores numerados y no una lista concatenada: los ids salen
			   de la sesión, pero la regla de la casa es que NADA se pega
			   dentro del SQL. */
			$marcas = implode(',', array_fill(0, count($permitidas), '?'));

			return $this->ejecutarConsulta(
				"SELECT sede_id, sede_nombre FROM general_sede
				  WHERE sede_id IN ($marcas) ORDER BY sede_nombre",
				$permitidas)->fetchAll();
		}

		/**
		 * Los alumnos con el estado de su foto ya resuelto.
		 *
		 * El estado no se puede consultar en SQL: depende del disco. Se trae
		 * la fila y se mira el archivo una vez por alumno. Con trescientos
		 * alumnos son trescientos stat, que el sistema operativo resuelve de
		 * caché; si algún día son decenas de miles habrá que paginar, y por
		 * eso $tope existe y no es infinito.
		 *
		 * @param string $estado        '', 'OK', 'AUSENTE' o 'SIN' (estado de la FOTO)
		 * @param string $alumnoEstado  A, I o T (estado del ALUMNO; T = activos e inactivos)
		 */
		public function listar(string $busqueda = '', $sede = '', string $estado = '', string $alumnoEstado = 'T', int $tope = 2000): array {

			$condiciones = [$this->condicionEstadoAlumno('a.alumno_estado', $alumnoEstado)];
			$parametros  = [];

			/*
			| El alcance por sede va SIEMPRE, y antes que el filtro que elige
			| el usuario. Si sólo se aplicara el desplegable, bastaría con
			| pedir otra sede por la URL para ver caras que no corresponden.
			*/
			$permitidas = $this->sedesPermitidas();
			if ($permitidas !== []) {
				$marcas = implode(',', array_fill(0, count($permitidas), '?'));
				$condiciones[] = "a.alumno_sedeid IN ($marcas)";
				$parametros = array_merge($parametros, $permitidas);
			}

			if ($sede !== '' && (int)$sede > 0) {
				$condiciones[]  = "a.alumno_sedeid = ?";
				$parametros[]   = (int)$sede;
			}

			if (trim($busqueda) !== '') {
				$condiciones[] = "(a.alumno_identificacion LIKE ?
				               OR a.alumno_primernombre   LIKE ?
				               OR a.alumno_apellidopaterno LIKE ?
				               OR a.alumno_apellidomaterno LIKE ?)";
				$como = '%' . trim($busqueda) . '%';
				array_push($parametros, $como, $como, $como, $como);
			}

			$sql = "SELECT a.alumno_id, a.alumno_identificacion, a.alumno_tipoidentificacion,
			               a.alumno_primernombre, a.alumno_segundonombre,
			               a.alumno_apellidopaterno, a.alumno_apellidomaterno,
			               a.alumno_fechanacimiento, a.alumno_fechaingreso, a.alumno_estado, a.alumno_imagen,
			               a.alumno_sedeid, s.sede_nombre,
			               TIMESTAMPDIFF(YEAR, a.alumno_fechanacimiento, CURDATE()) AS edad
			          FROM sujeto_alumno a
			          LEFT JOIN general_sede s ON s.sede_id = a.alumno_sedeid
			         WHERE " . implode(' AND ', $condiciones) . "
			         ORDER BY a.alumno_apellidopaterno, a.alumno_apellidomaterno,
			                  a.alumno_primernombre
			         LIMIT " . (int)$tope;

			$filas = $this->ejecutarConsulta($sql, $parametros)->fetchAll();
			$carpeta = $this->carpeta();

			foreach ($filas as $i => $f) {
				$filas[$i]['foto_estado'] = $this->estadoDeFoto($f['alumno_imagen'], $carpeta);
			}

			/* El filtro por estado se aplica DESPUÉS porque el estado no
			   existe hasta haber mirado el disco. */
			if (in_array($estado, [self::OK, self::AUSENTE, self::SIN], true)) {
				$filas = array_values(array_filter($filas,
					static fn($f) => $f['foto_estado'] === $estado));
			}

			return $filas;
		}

		/** En cuál de los tres estados está la foto de esta ficha. */
		private function estadoDeFoto(?string $archivo, string $carpeta): string {
			$archivo = trim((string)$archivo);

			if ($archivo === '') { return self::SIN; }

			/* basename y no el valor crudo: si una fila trajera «../algo», el
			   is_file() miraría fuera de la carpeta y respondería por un
			   archivo que no es de nadie. */
			return is_file($carpeta . basename($archivo)) ? self::OK : self::AUSENTE;
		}

		/** Cuántos alumnos hay en cada estado, sobre las filas ya traídas. */
		public function resumen(array $filas): array {
			$r = [self::OK => 0, self::AUSENTE => 0, self::SIN => 0];
			foreach ($filas as $f) { $r[$f['foto_estado']]++; }
			$r['TOTAL'] = count($filas);
			return $r;
		}

		/*==============================================================
		| Subir la foto de un alumno
		|==============================================================*/

		/**
		 * Recibe y guarda la foto de UN alumno.
		 *
		 * La imagen se vuelve a dibujar con GD y se escribe de nuevo
		 * (resizeImageGD). No es sólo para que pese menos: un PHP escondido
		 * tras una cabecera JPEG no sobrevive a imagecreatefromjpeg() seguido
		 * de imagejpeg(), y ése es el vector clásico de las subidas.
		 */

		/*==============================================================
		| Cómo se pinta cada estado — UNA sola fuente
		|
		| Estaba escrito en la vista, que era el único sitio que lo
		| necesitaba. Ahora las filas las arma también el endpoint de la
		| tabla, y dos copias del mapa significan «Sin foto» en gris en un
		| sitio y «No tiene» en rojo en otro para la misma cosa.
		|==============================================================*/
		public function pinta(): array {
			return [
				self::OK => [
					'etiqueta' => 'Con foto',   'color' => 'success',
					'icono'    => 'fa-circle-check',
					'ayuda'    => 'La ficha tiene foto y el archivo está en el servidor.',
				],
				self::AUSENTE => [
					'etiqueta' => 'Archivo ausente', 'color' => 'danger',
					'icono'    => 'fa-triangle-exclamation',
					'ayuda'    => 'La ficha registra una foto, pero el archivo NO está en el servidor. '
					            . 'Vuelva a subirla.',
				],
				self::SIN => [
					'etiqueta' => 'Sin foto',   'color' => 'secondary',
					'icono'    => 'fa-user',
					'ayuda'    => 'Nunca se le subió una foto.',
				],
			];
		}

		/*==============================================================
		| La tabla, servida página a página (DataTables serverSide)
		|
		| POR QUE
		|
		| La pantalla pintaba las 308 filas y pesaba 443 KB. DataTables
		| paginaba después, en el navegador: se pagaba el peso entero para
		| enseñar veinticinco. Cada fila lleva una foto, dos insignias con
		| su texto de ayuda y un botón, así que la fila sale cara.
		|
		| POR QUE AQUI SE RECORREN TODAS LAS FILAS IGUALMENTE
		|
		| El estado de la foto no está en la base: sale de mirar si el
		| archivo existe. Por eso listar() tiene que evaluar todo el
		| alcance antes de poder contar o filtrar por estado.
		|
		| Se midió: 307 comprobaciones de disco cuestan 5,86 ms. Lo caro
		| nunca fue mirar el disco, sino mandar el HTML.
		|
		| La búsqueda de DataTables se aplica aquí, sobre el conjunto
		| completo, de modo que sigue alcanzando a todos los alumnos y no
		| sólo a la página que se ve.
		|==============================================================*/
		public function fotosServidor(array $peticion): array {

			$dibujo  = (int)($peticion['draw'] ?? 1);
			$inicio  = max(0, (int)($peticion['start'] ?? 0));
			$largo   = (int)($peticion['length'] ?? 25);
			$largo   = ($largo < 1 || $largo > 200) ? 25 : $largo;
			$buscado = trim((string)($peticion['search']['value'] ?? ''));

			/*
			| Los filtros de la PANTALLA viajan aparte de los de DataTables, y
			| los tres tienen que llegar aquí.
			|
			| «q» se pasó por alto en la primera versión: el resumen de arriba
			| sí lo aplicaba y la tabla no, así que las tarjetas decían «12
			| alumnos» sobre una lista de 307. Dos cifras de lo mismo que no
			| coinciden es peor que ninguna.
			*/
			$q      = isset($peticion['q'])      ? $this->limpiarCadena($peticion['q'])      : '';
			$sede   = isset($peticion['sede'])   ? $this->limpiarCadena($peticion['sede'])   : '';
			$estado = isset($peticion['estado']) ? $this->limpiarCadena($peticion['estado']) : '';
			/* Estado del ALUMNO: activos salvo que se pida otra cosa (desde el 2026-10-23). */
			$alumnoEstado = in_array($peticion['alumno_estado'] ?? '', ['A', 'I', 'T'], true) ? $peticion['alumno_estado'] : 'A';

			/* El alcance por sede lo aplica listar(), no este método: es la
			   única forma de que no se pueda saltar pidiendo otra cosa. */
			$filas = $this->listar($q, $sede, $estado, $alumnoEstado);
			$total = count($filas);

			/* La búsqueda, sobre el conjunto ya resuelto. Sin tildes y en
			   minúscula: quien busca «Nuñez» debe encontrar «Núñez». */
			if ($buscado !== '') {
				$aguja = ds_sin_tildes(mb_strtolower($buscado, 'UTF-8'));
				$filas = array_values(array_filter($filas, function ($f) use ($aguja) {
					$pajar = ds_sin_tildes(mb_strtolower(
						$f['alumno_identificacion'] . ' '
						. $f['alumno_apellidopaterno'] . ' ' . $f['alumno_apellidomaterno'] . ' '
						. $f['alumno_primernombre'] . ' ' . $f['alumno_segundonombre'] . ' '
						. (string)$f['sede_nombre'], 'UTF-8'));
					return mb_strpos($pajar, $aguja) !== false;
				}));
			}

			/*
			| El orden, también sobre el conjunto completo (columnas de la
			| vista: 0 Alumno, 1 Sede, 2 Fecha de ingreso, 3 Foto). Antes la
			| tabla no ordenaba: las celdas ya venían armadas.
			*/
			$ORDEN = [
				0 => static fn($f) => ds_sin_tildes(mb_strtolower($f['alumno_apellidopaterno'] . ' ' . $f['alumno_apellidomaterno'] . ' ' . $f['alumno_primernombre'], 'UTF-8')),
				1 => static fn($f) => ds_sin_tildes(mb_strtolower((string)$f['sede_nombre'], 'UTF-8')),
				2 => static fn($f) => (string)$f['alumno_fechaingreso'],
				3 => static fn($f) => [self::AUSENTE => 0, self::SIN => 1, self::OK => 2][$f['foto_estado']],
			];
			$columna = (int)($peticion['order'][0]['column'] ?? 0);
			if (isset($ORDEN[$columna])) {
				$clave  = $ORDEN[$columna];
				$sentido = strtolower((string)($peticion['order'][0]['dir'] ?? 'asc')) === 'desc' ? -1 : 1;
				/* El nombre desempata: con la misma fecha o sede, alfabético. */
				usort($filas, static fn($a, $b) => (($clave($a) <=> $clave($b)) * $sentido) ?: ($ORDEN[0]($a) <=> $ORDEN[0]($b)));
			}

			$filtrados = count($filas);
			$pagina    = array_slice($filas, $inicio, $largo);

			$PINTA       = $this->pinta();
			$puedeEditar = puede('editar', 'alumnoFotos');
			$h           = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
			$fecha       = static fn($v) => $v && !str_starts_with((string)$v, '0000') ? (string)$v : '';

			/*
			| Cada fila son las celdas de una ficha (.ds-crud): la foto va
			| dentro de la celda del alumno, que en el teléfono encabeza la
			| ficha; className y data-etiqueta los pone la vista.
			*/
			$datos = [];
			foreach ($pagina as $f) {
				$p = $PINTA[$f['foto_estado']];
				$nombre = preg_replace('/\s+/', ' ', trim($f['alumno_primernombre'] . ' ' . $f['alumno_segundonombre']
				             . ' ' . $f['alumno_apellidopaterno'] . ' ' . $f['alumno_apellidomaterno']));

				/* loading="lazy" sigue haciendo falta: cada foto entra por
				   app/media.php, que arranca la sesión entera para servirla. */
				$foto = $f['foto_estado'] === self::OK
					? '<img src="' . $h(media_url('alumno', $f['alumno_imagen'])) . '" alt="" loading="lazy"'
					  . ' width="44" height="44" class="ds-ficha__imagen">'
					: '<span class="ds-ficha__imagen d-inline-flex align-items-center justify-content-center rounded-circle'
					  . ' text-bg-' . $p['color'] . '" title="' . $h($p['ayuda']) . '"><i class="fas ' . $p['icono'] . '" aria-hidden="true"></i></span>';

				$insignia = '<span class="badge text-bg-' . $p['color'] . '" title="' . $h($p['ayuda']) . '">'
				          . $h($p['etiqueta']) . '</span>';

				if ($f['foto_estado'] === self::AUSENTE) {
					$insignia .= '<code class="d-block small text-danger text-break">' . $h($f['alumno_imagen']) . '</code>';
				}

				$edad = $f['edad'] !== null ? (int)$f['edad'] : null;
				$alumno = '<div class="ds-ficha__con-imagen">' . $foto
					. '<div class="min-w-0"><div class="ds-ficha__nombre">' . $h($nombre) . '</div>'
					. '<div class="ds-ficha__meta"><i class="fas fa-id-card" aria-hidden="true"></i> ' . $h($f['alumno_identificacion'])
					. ($edad !== null ? ' · ' . $edad . ($edad === 1 ? ' año' : ' años') : '')
					. ($f['alumno_estado'] !== 'A' ? ' · <span class="badge text-bg-secondary">Inactivo</span>' : '')
					. '</div></div></div>';

				/* Fecha de ingreso, con la antigüedad. */
				$ingreso = $fecha($f['alumno_fechaingreso']);
				if ($ingreso !== '') {
					$antig = (new \DateTime($ingreso))->diff(new \DateTime('today'));
					$tiempo = $antig->y > 0 ? $antig->y . ($antig->y === 1 ? ' año' : ' años')
						: ($antig->m > 0 ? $antig->m . ($antig->m === 1 ? ' mes' : ' meses') : 'Este mes');
					$celdaIngreso = date('d/m/Y', strtotime($ingreso)) . '<span class="d-block small text-body-secondary">' . $tiempo . '</span>';
				} else {
					$celdaIngreso = '<span class="text-body-secondary">Sin fecha</span>';
				}

				$fila = [
					$alumno,
					$h((string)$f['sede_nombre']),
					$celdaIngreso,
					$insignia,
				];

				if ($puedeEditar) {
					$fila[] = '<div class="ds-ficha__botones"><button type="button" class="btn btn-sm btn-primary btn-subir ds-ficha__primario"'
					        . ' data-id="' . (int)$f['alumno_id'] . '"'
					        . ' data-nombre="' . $h($nombre) . '">'
					        . '<i class="fas fa-camera me-1" aria-hidden="true"></i>'
					        . ($f['foto_estado'] === self::OK ? 'Cambiar foto' : 'Subir foto') . '</button></div>';
				}

				$datos[] = $fila;
			}

			return [
				'draw'            => $dibujo,
				'recordsTotal'    => $total,
				'recordsFiltered' => $filtrados,
				'data'            => $datos,
			];
		}
		public function guardarFoto(): string {

			if (!puede_editar('alumnoFotos')) {
				return $this->aviso('Acceso denegado',
					'Su rol no puede modificar las fotos de los alumnos.', 'error');
			}

			$id = (int)($_POST['alumno_id'] ?? 0);
			if ($id <= 0) {
				return $this->aviso('Error', 'No se indicó de qué alumno es la foto.', 'error');
			}

			$alumno = $this->ejecutarConsulta(
				"SELECT alumno_id, alumno_identificacion, alumno_imagen, alumno_sedeid
				   FROM sujeto_alumno WHERE alumno_id = :id", [':id' => $id])->fetch();

			if (!$alumno) {
				return $this->aviso('Error', 'El alumno no existe.', 'error');
			}

			/*
			| El alcance por sede se vuelve a comprobar AQUÍ.
			|
			| Que la pantalla no muestre a un alumno no impide que alguien
			| mande su id por POST. El listado filtra para la vista; esto
			| decide de verdad.
			*/
			$permitidas = $this->sedesPermitidas();
			if ($permitidas !== [] && !in_array((int)$alumno['alumno_sedeid'], $permitidas, true)) {
				return $this->aviso('Acceso denegado',
					'Este alumno no pertenece a una sede que usted administre.', 'error');
			}

			if (!isset($_FILES['alumno_foto']) || $_FILES['alumno_foto']['error'] === UPLOAD_ERR_NO_FILE) {
				return $this->aviso('Falta la foto', 'No seleccionó ninguna imagen.', 'error');
			}
			if ($_FILES['alumno_foto']['error'] !== UPLOAD_ERR_OK) {
				return $this->aviso('Error',
					'No se pudo recibir el archivo. Puede que supere el tamaño permitido por el servidor.',
					'error');
			}
			if (!is_uploaded_file($_FILES['alumno_foto']['tmp_name'])) {
				return $this->aviso('Error', 'El archivo no llegó por una subida válida.', 'error');
			}
			if ($_FILES['alumno_foto']['size'] / 1024 > 4000) {
				return $this->aviso('Imagen muy pesada', 'La foto no puede pasar de 4 MB.', 'error');
			}

			/* El contenido y no la extensión: la extensión la escribe quien
			   sube, el contenido no se falsea igual de fácil. */
			$info = @getimagesize($_FILES['alumno_foto']['tmp_name']);
			if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
				return $this->aviso('Formato no permitido',
					'La foto tiene que ser JPG o PNG.', 'error');
			}

			$carpeta = $this->carpeta();
			if (!is_dir($carpeta) || !is_writable($carpeta)) {
				return $this->aviso('Error',
					'La carpeta de fotos no admite escritura.', 'error');
			}

			$ext    = ($info[2] === IMAGETYPE_JPEG) ? '.jpg' : '.png';
			$base   = preg_replace('~[^A-Za-z0-9_-]~', '_',
			                       (string)$alumno['alumno_identificacion']) ?: ('alumno' . $id);
			/*
			| El nombre lo pone el servidor, nunca quien sube.
			|
			| Y lleva la hora, no un rand(0,100) como el alta: con cien
			| valores posibles, reemplazar la foto de un alumno acierta el
			| mismo nombre una de cada cien veces, y entonces el navegador
			| sigue enseñando la anterior porque la URL no cambió.
			*/
			$nombre = $base . '_' . date('YmdHis') . $ext;

			if (!$this->resizeImageGD($_FILES['alumno_foto']['tmp_name'], 800, 600, $carpeta . $nombre)) {
				return $this->aviso('Error', 'No fue posible procesar la imagen.', 'error');
			}

			$anterior = basename(trim((string)$alumno['alumno_imagen']));

			$this->ejecutarConsulta(
				"UPDATE sujeto_alumno SET alumno_imagen = :f WHERE alumno_id = :id",
				[':f' => $nombre, ':id' => $id]);

			/*
			| La anterior se borra DESPUÉS de que la base apunte a la nueva.
			|
			| Al revés —borrar y luego actualizar— un fallo entre las dos
			| operaciones deja al alumno sin archivo Y con el nombre viejo en
			| la ficha: exactamente el estado AUSENTE que esta pantalla existe
			| para corregir.
			*/
			if ($anterior !== '' && $anterior !== $nombre && is_file($carpeta . $anterior)) {
				@unlink($carpeta . $anterior);
			}

			return $this->aviso('Foto guardada',
				'La foto del alumno se actualizó.', 'success', 'recargar');
		}

		/** Alerta con el formato que espera alertas_ajax() en ajax.js */
		private function aviso(string $titulo, string $texto,
		                       string $icono = 'success', string $tipo = 'simple'): string {
			return json_encode([
				'tipo'   => $tipo,
				'titulo' => $titulo,
				'texto'  => $texto,
				'icono'  => $icono,
			], JSON_UNESCAPED_UNICODE);
		}
	}
