<?php
/**
 * Genera la tarjeta de cumpleaños como imagen JPEG.
 * Ruta: cumpleaniosTarjeta/{alumno_id}/
 *
 * ---------------------------------------------------------------------------
 * LO QUE CAMBIÓ, Y POR QUÉ
 *
 * Antes todo estaba escrito aquí: el marco era una ruta fija y las áreas eran
 * cuatro fracciones mágicas —0.218, 0.176, 0.558, 0.463— con el comentario
 * «ajustar estos valores si se cambia la plantilla».
 *
 * Es decir: cambiar el marco exigía editar PHP y adivinar números probando.
 * Quien decide cómo se ve la tarjeta no es quien edita PHP.
 *
 * Ahora todo eso vive en cumple_plantilla y se administra desde Core
 * (ds_core/admin → Tarjeta de cumpleaños), con un editor donde las áreas se
 * arrastran sobre el marco.
 *
 * ESTE ARCHIVO SÓLO DIBUJA. No decide nada.
 *
 *
 * LA FOTO VA DETRÁS DEL MARCO
 *
 * Se dibuja primero la foto y luego el marco encima con imagecopy(), así que
 * el PNG del marco necesita su ventana transparente. Eso permite que la foto
 * asome con la forma que tenga esa ventana —redondeada, inclinada, con
 * bordes— sin que este código sepa nada de su contorno.
 */

	ob_start();
	use app\controllers\cumpleaniosController;

	$insCumple = new cumpleaniosController();

	// Validar ID
	$alumno_id = isset($url[1]) ? (int)$insCumple->limpiarCadena($url[1]) : 0;
	if ($alumno_id <= 0) {
		http_response_code(400);
		exit('ID de alumno no válido.');
	}

	// Obtener datos del alumno
	$datos = $insCumple->infoAlumno($alumno_id);
	if ($datos->rowCount() !== 1) {
		http_response_code(404);
		exit('Alumno no encontrado.');
	}
	$alumno = $datos->fetch();

	// ── La plantilla activa ────────────────────────────────────
	$p = $insCumple->plantillaTarjeta();
	if ($p === null) {
		http_response_code(500);
		exit('No hay ninguna plantilla de tarjeta activa. Se configura en Core.');
	}

	// ── Rutas físicas ──────────────────────────────────────────
	$base_path    = realpath(__DIR__ . '/../../..');
	$marco_dir    = $base_path . '/app/views/imagenes/cumples/';
	/* basename(): el nombre viene de la base, y aunque lo escribe una pantalla
	   con permisos, un valor con «../» apuntaría fuera de la carpeta. */
	$marco_path   = $marco_dir . basename($p['plantilla_archivo']);
	$foto_dir     = $base_path . '/app/views/imagenes/fotos/alumno/';
	$default_foto = $foto_dir  . 'alumno.png';

	// ── Cargar plantilla ───────────────────────────────────────
	if (!file_exists($marco_path)) {
		http_response_code(500);
		exit('Plantilla no encontrada: ' . basename($marco_path));
	}
	$marco = @imagecreatefrompng($marco_path);
	if (!$marco) {
		http_response_code(500);
		exit('No se pudo cargar la plantilla.');
	}
	imagesavealpha($marco, true);
	$marco_w = imagesx($marco);
	$marco_h = imagesy($marco);

	// ── Lienzo base (la foto irá aquí, el marco encima) ────────
	$canvas = imagecreatetruecolor($marco_w, $marco_h);
	$bg = imagecolorallocate($canvas, 255, 255, 255);
	imagefilledrectangle($canvas, 0, 0, $marco_w - 1, $marco_h - 1, $bg);

	/**
	 * Los porcentajes de la plantilla, a píxeles de ESTE marco.
	 *
	 * Guardarlos en porcentaje es lo que permite cambiar el marco por otro de
	 * distinta resolución sin rehacer una sola coordenada.
	 */
	$pxW = static fn($pct): int => (int) round($marco_w * ((float) $pct) / 100);
	$pxH = static fn($pct): int => (int) round($marco_h * ((float) $pct) / 100);

	/** «#RRGGBB» al color asignado en el lienzo. */
	$color = static function ($img, string $hex, int $alpha = 0) {
		$hex = ltrim($hex, '#');
		if (strlen($hex) !== 6) { $hex = 'FFFFFF'; }
		return imagecolorallocatealpha($img,
			hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)), $alpha);
	};

	// ── El área de la foto ─────────────────────────────────────
	$rect_x = $pxW($p['foto_x']);
	$rect_y = $pxH($p['foto_y']);
	$rect_w = $pxW($p['foto_ancho']);
	$rect_h = $pxH($p['foto_alto']);

	// ── Cargar foto del alumno ─────────────────────────────────
	$foto_path = ($alumno['alumno_imagen'] !== '')
		? $foto_dir . $alumno['alumno_imagen']
		: $default_foto;

	if (!file_exists($foto_path)) {
		$foto_path = $default_foto;
	}

	$ext      = strtolower(pathinfo($foto_path, PATHINFO_EXTENSION));
	$foto_src = null;
	if      ($ext === 'jpg' || $ext === 'jpeg') { $foto_src = @imagecreatefromjpeg($foto_path); }
	elseif  ($ext === 'png')                    { $foto_src = @imagecreatefrompng($foto_path);  }
	elseif  ($ext === 'gif')                    { $foto_src = @imagecreatefromgif($foto_path);  }

	if (!$foto_src && file_exists($default_foto)) {
		$foto_src = @imagecreatefrompng($default_foto);
	}

	// ── Encajar la foto en su área ─────────────────────────────
	if ($foto_src && $rect_w > 0 && $rect_h > 0) {
		$foto_orig_w = imagesx($foto_src);
		$foto_orig_h = imagesy($foto_src);

		// Centro-recortar la foto para que coincida con la relación de aspecto del área
		$aspecto_rect = $rect_w / $rect_h;
		$aspecto_foto = $foto_orig_w / $foto_orig_h;

		if ($aspecto_foto > $aspecto_rect) {
			// La foto es más ancha que el área: recortar laterales
			$src_h = $foto_orig_h;
			$src_w = (int)($foto_orig_h * $aspecto_rect);
			$src_x = (int)(($foto_orig_w - $src_w) / 2);
			$src_y = 0;
		} else {
			// La foto es más alta que el área: recortar arriba/abajo
			$src_w = $foto_orig_w;
			$src_h = (int)($foto_orig_w / $aspecto_rect);
			$src_x = 0;
			$src_y = (int)(($foto_orig_h - $src_h) / 2);
		}

		imagecopyresampled(
			$canvas, $foto_src,
			$rect_x, $rect_y,
			$src_x,  $src_y,
			$rect_w, $rect_h,
			$src_w,  $src_h
		);

		imagedestroy($foto_src);
	}

	// ── Los textos ─────────────────────────────────────────────
	$nombre = trim($alumno['alumno_primernombre'] . ' ' . $alumno['alumno_segundonombre'])
		. ' ' .
		trim($alumno['alumno_apellidopaterno'] . ' ' . $alumno['alumno_apellidomaterno']);
	$edad = (int)$alumno['edad'];

	// Buscar fuente TrueType — primero fuentes incluidas en el proyecto,
	// luego rutas comunes de hosting Linux/Unix, finalmente Windows (desarrollo local).
	$font_paths = [
		// ── Fuentes del proyecto (funciona en cualquier hosting) ──
		$base_path . '/app/views/dist/fonts/OpenSans-Bold.ttf',
		$base_path . '/app/views/dist/fonts/OpenSans-Regular.ttf',
		// ── Linux hosting: DejaVu (instalado en casi todos) ──────
		'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/dejavu-sans-fonts/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
		'/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
		// ── Linux hosting: Liberation (cPanel/CentOS/AlmaLinux) ──
		'/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
		'/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
		'/usr/share/fonts/liberation/LiberationSans-Bold.ttf',
		// ── Linux hosting: FreeSans ───────────────────────────────
		'/usr/share/fonts/truetype/freefont/FreeSansBold.ttf',
		'/usr/share/fonts/gnu-free/FreeSansBold.ttf',
		// ── Linux hosting: Ubuntu fonts ───────────────────────────
		'/usr/share/fonts/truetype/ubuntu-font-family/Ubuntu-B.ttf',
		'/usr/share/fonts/ubuntu/Ubuntu-B.ttf',
		// ── Windows (desarrollo local) ────────────────────────────
		'C:/Windows/Fonts/arialbd.ttf',
		'C:/Windows/Fonts/arial.ttf',
	];
	$font_file = null;
	foreach ($font_paths as $fp) {
		if (file_exists($fp)) { $font_file = $fp; break; }
	}

	$angle_deg = (float) $p['angulo'];

	// ── La franja, sólo si la plantilla la pide ────────────────
	/*
	| Un marco que ya trae sus casillas de «NOMBRE:» y «EDAD:» no la necesita,
	| y dibujarla encima lo estropearía. Por eso es un interruptor y no una
	| constante.
	*/
	if ($p['franja_activa'] === 'S') {
		$tilt     = (int)($rect_w * tan(deg2rad($angle_deg)));
		$franja_h = $pxH($p['franja_alto']);
		$franja_y = $rect_y + $rect_h - $franja_h;

		/* La opacidad va de 0 a 100 en la pantalla; GD la quiere al revés y
		   de 0 a 127. Se convierte aquí para que quien configura piense en
		   «70 % opaco» y no en «alpha 38». */
		$alpha = (int) round(127 - (((int) $p['franja_opacidad']) * 127 / 100));

		$franja_overlay = imagecreatetruecolor($marco_w, $marco_h);
		imagealphablending($franja_overlay, false);
		imagesavealpha($franja_overlay, true);
		$clear = imagecolorallocatealpha($franja_overlay, 0, 0, 0, 127);
		imagefilledrectangle($franja_overlay, 0, 0, $marco_w - 1, $marco_h - 1, $clear);

		$franja_color = $color($franja_overlay, $p['franja_color'], $alpha);
		$poly_points = [
			$rect_x,            $franja_y,
			$rect_x + $rect_w,  $franja_y - $tilt,
			$rect_x + $rect_w,  $franja_y + $franja_h - $tilt,
			$rect_x,            $franja_y + $franja_h,
		];
		imagefilledpolygon($franja_overlay, $poly_points, $franja_color);
		imagealphablending($canvas, true);
		imagecopy($canvas, $franja_overlay, 0, 0, 0, 0, $marco_w, $marco_h);
		imagedestroy($franja_overlay);
	}

	/**
	 * Escribe un texto dentro de su caja, alineado y encogido si no cabe.
	 *
	 * La caja es lo que permite colocar el texto sobre una casilla del marco:
	 * sin ella habría que centrar a ojo respecto al marco entero.
	 */
	$escribir = static function ($canvas, string $texto, array $caja, ?string $font_file,
	                             float $angulo, $col, callable $pxW, callable $pxH) {
		if ($texto === '') { return; }

		$bx = $pxW($caja['x']);
		$by = $pxH($caja['y']);
		$bw = $pxW($caja['ancho']);
		$tam = max(7, $pxW($caja['tam']));

		if ($font_file === null) {
			/* Sin TrueType no hay tamaños ni ángulos: GD sólo trae cinco
			   fuentes de mapa de bits. Se escribe lo que se pueda antes que
			   dejar la tarjeta sin nombre. */
			$gd = 5;
			$tw = strlen($texto) * imagefontwidth($gd);
			$x  = match ($caja['alinea']) {
				'I' => $bx,
				'D' => $bx + $bw - $tw,
				default => $bx + (int)(($bw - $tw) / 2),
			};
			imagestring($canvas, $gd, $x, $by, $texto, $col);
			return;
		}

		/* Se encoge hasta que quepa. El margen de 20 px evita que el texto
		   toque el borde de su casilla. */
		$bbox = imagettfbbox($tam, $angulo, $font_file, $texto);
		$tw   = abs($bbox[4] - $bbox[0]);
		while ($tw > $bw - 20 && $tam > 7) {
			$tam--;
			$bbox = imagettfbbox($tam, $angulo, $font_file, $texto);
			$tw   = abs($bbox[4] - $bbox[0]);
		}

		$x = match ($caja['alinea']) {
			'I' => $bx,
			'D' => $bx + $bw - $tw,
			default => $bx + (int)(($bw - $tw) / 2),
		};

		imagettftext($canvas, $tam, $angulo, $x, $by, $col, $font_file, $texto);
	};

	// ── Superponer el marco PNG encima de todo lo anterior ──────
	imagealphablending($canvas, true);
	imagecopy($canvas, $marco, 0, 0, 0, 0, $marco_w, $marco_h);
	imagedestroy($marco);

	/*
	| Los textos van AQUI, encima del marco, y no antes.
	|
	| Escribirlos antes funcionaba con el marco clasico porque caian dentro
	| de su ventana transparente. Un marco que trae sus propias casillas es
	| OPACO justo ahi: sus recuadros blancos tapaban el nombre y la edad, y
	| la tarjeta salia con las casillas vacias sin ningun error.
	|
	| Encima del marco los dos casos funcionan: sobre una ventana
	| transparente se ve igual que antes, y sobre una casilla dibujada se ve
	| por fin. La foto sigue detras, que es lo que debe estar detras.
	*/
	$escribir($canvas, $nombre, [
		'x' => $p['nombre_x'], 'y' => $p['nombre_y'], 'ancho' => $p['nombre_ancho'],
		'tam' => $p['nombre_tam'], 'alinea' => $p['nombre_alinea'],
	], $font_file, $angle_deg, $color($canvas, $p['nombre_color']), $pxW, $pxH);

	$texto_edad = str_replace('{edad}', (string) $edad, $p['edad_texto']);
	$escribir($canvas, $texto_edad, [
		'x' => $p['edad_x'], 'y' => $p['edad_y'], 'ancho' => $p['edad_ancho'],
		'tam' => $p['edad_tam'], 'alinea' => $p['edad_alinea'],
	], $font_file, $angle_deg, $color($canvas, $p['edad_color']), $pxW, $pxH);


	// ── Enviar imagen al navegador ─────────────────────────────
	ob_end_clean();
	header('Content-Type: image/jpeg');
	header('Content-Disposition: inline; filename="cumpleanios_' . $alumno_id . '.jpg"');
	/* Sin caché mientras se ajusta la plantilla: con max-age el navegador
	   sigue mostrando la tarjeta anterior y parece que el editor no guarda. */
	header('Cache-Control: no-store');
	imagejpeg($canvas, null, 92);
	imagedestroy($canvas);
	exit;
