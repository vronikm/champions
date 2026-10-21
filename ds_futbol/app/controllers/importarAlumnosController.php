<?php

	namespace app\controllers;
	use app\models\mainModel;

	class importarAlumnosController extends mainModel {

		/* =====================================================
		 *  IMPORTACIÓN MASIVA DE ALUMNOS DESDE ARCHIVO CSV
		 *  - Tablas destino: alumno_representante, sujeto_alumno
		 *  - Deduplica representantes por cédula (hermanos)
		 *  - Valida cédula ecuatoriana, correo y celular
		 *  - Si el alumno no tiene cédula → "SINCEDULA"
		 * =====================================================
		 */

		/* ---------- Helpers de normalización ---------- */

		private function leerCSV($path) {
			$contenido = file_get_contents($path);
			if ($contenido === false) return [];

			// Quitar BOM UTF-8 si existe
			$contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);

			// Detectar codificación y normalizar a UTF-8
			$enc = mb_detect_encoding($contenido, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
			if ($enc && $enc !== 'UTF-8') {
				$contenido = mb_convert_encoding($contenido, 'UTF-8', $enc);
			}

			$lineas = preg_split('/\r\n|\n|\r/', $contenido);
			$filas = [];
			foreach ($lineas as $linea) {
				if (trim($linea) === '') continue;
				$filas[] = str_getcsv($linea, ';');
			}
			return $filas;
		}

		private function partirNombre($nombreCompleto) {
			$nombreCompleto = trim(preg_replace('/\s+/', ' ', $nombreCompleto));
			if ($nombreCompleto === '') {
				return ['','','',''];
			}
			$partes = explode(' ', $nombreCompleto);
			$n = count($partes);

			if ($n == 1) return [$partes[0], '', '', ''];
			if ($n == 2) return [$partes[0], '', $partes[1], ''];
			if ($n == 3) return [$partes[0], '', $partes[1], $partes[2]];

			// 4 o más → últimos 2 = apellidos, los demás = nombres
			$apMat = array_pop($partes);
			$apPat = array_pop($partes);
			$primerNombre = array_shift($partes);
			$segundoNombre = implode(' ', $partes);
			return [$primerNombre, $segundoNombre, $apPat, $apMat];
		}

		private function normalizarFecha($valor) {
			$valor = trim($valor);
			if ($valor === '') return ['', null];

			// Casos malformados típicos: "3//4/2012", "15/9/20255"
			if (substr_count($valor, '//') > 0) {
				return [$valor, 'Formato inválido'];
			}

			$partes = explode('/', $valor);
			if (count($partes) !== 3) return [$valor, 'Formato inválido'];

			$d = intval($partes[0]);
			$m = intval($partes[1]);
			$a = intval($partes[2]);

			if ($a < 1900 || $a > 2100) return [$valor, 'Año inválido'];
			if (!checkdate($m, $d, $a)) return [$valor, 'Fecha inválida'];

			return [sprintf('%04d-%02d-%02d', $a, $m, $d), null];
		}

		private function normalizarCelular($valor) {
			$valor = preg_replace('/\D/', '', $valor);
			if ($valor === '') return ['', 'vacío'];

			// Caso: viene sin el 0 inicial (9 dígitos comenzando en 9)
			if (strlen($valor) === 9 && $valor[0] === '9') {
				$valor = '0'.$valor;
			}
			// Caso: 8 dígitos sin 09 inicial
			if (strlen($valor) === 8) {
				$valor = '09'.$valor;
			}

			if (strlen($valor) !== 10) return [$valor, 'longitud inválida ('.strlen($valor).')'];
			if (substr($valor, 0, 2) !== '09') return [$valor, 'no inicia con 09'];

			return [$valor, null];
		}

		private function validarCorreo($valor) {
			$valor = trim($valor);
			if ($valor === '') return ['', 'vacío'];
			if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) return [$valor, 'formato inválido'];
			return [$valor, null];
		}

		private function normalizarCedulaAlumno($valor) {
			$valor = preg_replace('/\D/', '', $valor);
			if ($valor === '') return ['SINCEDULA', null];

			// Cédulas ecuatorianas: 10 dígitos
			if (strlen($valor) === 10 && $this->validarCedula($valor)) {
				return [$valor, null];
			}
			// Si tiene dígitos pero no es válida, conservamos el dato como "no validado" pero NO usamos SINCEDULA
			// (regla: SINCEDULA solo cuando NO viene cédula)
			return [$valor, 'cédula no válida según algoritmo'];
		}

		private function normalizarCedulaRepre($valor) {
			$valor = preg_replace('/\D/', '', $valor);
			if ($valor === '') return ['', 'sin cédula'];
			if (strlen($valor) === 10 && $this->validarCedula($valor)) {
				return [$valor, null];
			}
			return [$valor, 'cédula no válida según algoritmo'];
		}

		/* ---------- Análisis (preview) ---------- */

		public function analizarCSVControlador() {
			if (!isset($_FILES['archivo_csv']) || $_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
				return json_encode([
					"tipo"   => "simple",
					"titulo" => "Archivo no recibido",
					"texto"  => "Debe seleccionar un archivo CSV válido.",
					"icono"  => "error"
				]);
			}

			$sedeId = isset($_POST['alumno_sedeid']) ? intval($_POST['alumno_sedeid']) : 0;
			if ($sedeId <= 0) {
				return json_encode([
					"tipo"   => "simple",
					"titulo" => "Sede requerida",
					"texto"  => "Debe seleccionar la sede destino para la carga.",
					"icono"  => "error"
				]);
			}

			$resultado = $this->procesarCSV($_FILES['archivo_csv']['tmp_name'], $sedeId);

			// Persistir el resultado en sesión para que el paso de carga lo reutilice
			$_SESSION['import_alumnos_payload'] = $resultado;

			return json_encode([
				"tipo"   => "preview",
				"html"   => $this->generarHTMLReporte($resultado),
				"resumen"=> $resultado['resumen']
			]);
		}

		/* ---------- Carga (commit) ---------- */

		public function cargarCSVControlador() {
			if (!isset($_SESSION['import_alumnos_payload'])) {
				return json_encode([
					"tipo"   => "simple",
					"titulo" => "Sin datos para cargar",
					"texto"  => "Primero debe analizar un archivo CSV.",
					"icono"  => "error"
				]);
			}

			$payload = $_SESSION['import_alumnos_payload'];
			$conn = $this->conectar();

			$repreInsertados = 0;
			$repreReutilizados = 0;
			$alumnosInsertados = 0;
			$omitidos = 0;
			$errores = [];

			// Mapa local: cedula_repre → repre_id (para no consultar la BD por hermano)
			$mapaRepreCedula = [];

			foreach ($payload['filas'] as $fila) {
				if ($fila['bloquear']) { $omitidos++; continue; }

				$repreCedula = $fila['repre']['identificacion'];

				try {
					// 1) Resolver / insertar representante
					if ($repreCedula !== '' && isset($mapaRepreCedula[$repreCedula])) {
						$repreId = $mapaRepreCedula[$repreCedula];
						$repreReutilizados++;
					} else {
						$repreId = null;
						if ($repreCedula !== '') {
							$check = $conn->prepare("SELECT repre_id FROM alumno_representante WHERE repre_identificacion = :ci LIMIT 1");
							$check->bindParam(':ci', $repreCedula);
							$check->execute();
							if ($check->rowCount() > 0) {
								$repreId = (int) $check->fetchColumn();
								$repreReutilizados++;
							}
						}

						if ($repreId === null) {
							$ins = $conn->prepare("INSERT INTO alumno_representante
								(repre_tipoidentificacion, repre_identificacion, repre_primernombre, repre_segundonombre,
								 repre_apellidopaterno, repre_apellidomaterno, repre_direccion, repre_correo, repre_celular,
								 repre_sexo, repre_parentesco, repre_factura, repre_estado, repre_firmado)
								VALUES ('CED', :ci, :n1, :n2, :a1, :a2, :dir, :mail, :cel, '', '4MA', 'N', 'A', 'N')");
							$ins->bindParam(':ci',  $fila['repre']['identificacion']);
							$ins->bindParam(':n1',  $fila['repre']['primer_nombre']);
							$ins->bindParam(':n2',  $fila['repre']['segundo_nombre']);
							$ins->bindParam(':a1',  $fila['repre']['apellido_paterno']);
							$ins->bindParam(':a2',  $fila['repre']['apellido_materno']);
							$ins->bindParam(':dir', $fila['repre']['direccion']);
							$ins->bindParam(':mail',$fila['repre']['correo']);
							$ins->bindParam(':cel', $fila['repre']['celular']);
							$ins->execute();
							$repreId = (int) $conn->lastInsertId();
							$repreInsertados++;
						}

						if ($repreCedula !== '') {
							$mapaRepreCedula[$repreCedula] = $repreId;
						}
					}

					// 2) Insertar alumno
					$insA = $conn->prepare("INSERT INTO sujeto_alumno
						(alumno_sedeid, alumno_posicionid, alumno_nacionalidadid, alumno_repreid,
						 alumno_tipoidentificacion, alumno_identificacion, alumno_primernombre, alumno_segundonombre,
						 alumno_apellidopaterno, alumno_apellidomaterno, alumno_nombrecorto, alumno_direccion,
						 alumno_fechanacimiento, alumno_fechaingreso, alumno_genero, alumno_hermanos, alumno_estado,
						 alumno_imagen, alumno_numcamiseta)
						VALUES (:sede, '', 'ECU', :repid, 'CED', :ci, :n1, :n2, :a1, :a2, '', :dir,
								:fnac, :fing, '', 'N', 'A', '', 0)");

					$insA->bindParam(':sede', $payload['sede_id'], \PDO::PARAM_INT);
					$insA->bindParam(':repid',$repreId, \PDO::PARAM_INT);
					$insA->bindParam(':ci',   $fila['alumno']['identificacion']);
					$insA->bindParam(':n1',   $fila['alumno']['primer_nombre']);
					$insA->bindParam(':n2',   $fila['alumno']['segundo_nombre']);
					$insA->bindParam(':a1',   $fila['alumno']['apellido_paterno']);
					$insA->bindParam(':a2',   $fila['alumno']['apellido_materno']);
					$insA->bindParam(':dir',  $fila['repre']['direccion']);

					if ($fila['alumno']['fecha_nacimiento'] === null) {
						$insA->bindValue(':fnac', null, \PDO::PARAM_NULL);
					} else {
						$insA->bindParam(':fnac', $fila['alumno']['fecha_nacimiento']);
					}
					if ($fila['alumno']['fecha_ingreso'] === null) {
						$insA->bindValue(':fing', null, \PDO::PARAM_NULL);
					} else {
						$insA->bindParam(':fing', $fila['alumno']['fecha_ingreso']);
					}

					$insA->execute();
					$alumnosInsertados++;

				} catch (\PDOException $e) {
					$errores[] = "Fila ".$fila['linea']." (".$fila['alumno']['nombre_completo']."): ".$e->getMessage();
					$omitidos++;
				}
			}

			unset($_SESSION['import_alumnos_payload']);

			$texto = "Alumnos insertados: $alumnosInsertados | Representantes nuevos: $repreInsertados | "
				   . "Representantes reutilizados: $repreReutilizados | Filas omitidas: $omitidos";

			if (!empty($errores)) {
				$texto .= " | Errores: ".count($errores);
			}

			return json_encode([
				"tipo"   => "recargar",
				"titulo" => "Importación finalizada",
				"texto"  => $texto,
				"icono"  => $alumnosInsertados > 0 ? "success" : "warning"
			]);
		}

		/* ---------- Núcleo: parseo + validación ---------- */

		public function procesarCSV($path, $sedeId) {
			$filas = $this->leerCSV($path);
			if (count($filas) < 2) {
				return [
					'sede_id'  => $sedeId,
					'filas'    => [],
					'resumen'  => ['mensaje' => 'CSV vacío o sin filas válidas.'],
				];
			}

			// La primera fila es encabezado, se descarta
			array_shift($filas);

			$conn = $this->conectar();

			// Pre-cargar cédulas existentes para detectar duplicados contra la BD
			$alumnosExistentes = [];
			$rs = $conn->query("SELECT alumno_identificacion FROM sujeto_alumno WHERE alumno_identificacion <> 'SINCEDULA'");
			while ($row = $rs->fetch(\PDO::FETCH_ASSOC)) {
				$alumnosExistentes[$row['alumno_identificacion']] = true;
			}

			$repreExistentes = [];
			$rs = $conn->query("SELECT repre_id, repre_identificacion FROM alumno_representante WHERE repre_identificacion <> ''");
			while ($row = $rs->fetch(\PDO::FETCH_ASSOC)) {
				$repreExistentes[$row['repre_identificacion']] = $row['repre_id'];
			}

			$cedulasAlumnoEnCSV = [];
			$cedulasRepreEnCSV  = [];

			$resultado = [
				'sede_id' => $sedeId,
				'filas'   => [],
				'resumen' => [
					'total_filas'             => 0,
					'alumnos_a_insertar'      => 0,
					'representantes_unicos'   => 0,
					'representantes_nuevos'   => 0,
					'representantes_reuso'    => 0,
					'errores_bloqueantes'     => 0,
					'advertencias'            => 0,
					'alumnos_sin_cedula'      => 0,
				],
			];

			$lineaCSV = 1;
			foreach ($filas as $cols) {
				$lineaCSV++;
				if (count($cols) < 2) continue;

				// Mapeo posicional del CSV
				$alumnoNombre   = isset($cols[0]) ? trim($cols[0]) : '';
				$alumnoCedula   = isset($cols[1]) ? trim($cols[1]) : '';
				$alumnoFNac     = isset($cols[2]) ? trim($cols[2]) : '';
				$alumnoFIng     = isset($cols[4]) ? trim($cols[4]) : '';
				$repreNombre    = isset($cols[5]) ? trim($cols[5]) : '';
				$repreCedula    = isset($cols[6]) ? trim($cols[6]) : '';
				$repreCorreo    = isset($cols[7]) ? trim($cols[7]) : '';
				$repreCelular   = isset($cols[8]) ? trim($cols[8]) : '';
				$repreDireccion = isset($cols[9]) ? trim($cols[9]) : '';

				// Saltar filas totalmente vacías
				if ($alumnoNombre === '' && $alumnoCedula === '' && $repreNombre === '') continue;

				$resultado['resumen']['total_filas']++;

				$obs = [];           // observaciones de la fila
				$bloquear = false;   // si true → no se inserta

				// --- Alumno ---
				list($aPN, $aSN, $aAP, $aAM) = $this->partirNombre($alumnoNombre);
				if ($aPN === '' || $aAP === '') {
					$obs[] = ['tipo'=>'error', 'msg'=>'Nombre del alumno incompleto'];
					$bloquear = true;
				}

				list($aCed, $aCedErr) = $this->normalizarCedulaAlumno($alumnoCedula);
				if ($aCed === 'SINCEDULA') {
					$obs[] = ['tipo'=>'info', 'msg'=>'Alumno sin cédula → se usará SINCEDULA'];
					$resultado['resumen']['alumnos_sin_cedula']++;
				} elseif ($aCedErr) {
					$obs[] = ['tipo'=>'warn', 'msg'=>"Cédula del alumno '$aCed' $aCedErr"];
				}

				if ($aCed !== 'SINCEDULA') {
					if (isset($alumnosExistentes[$aCed])) {
						$obs[] = ['tipo'=>'error', 'msg'=>"Alumno con cédula $aCed YA existe en la base"];
						$bloquear = true;
					}
					if (isset($cedulasAlumnoEnCSV[$aCed])) {
						$obs[] = ['tipo'=>'error', 'msg'=>"Alumno duplicado dentro del CSV (cédula $aCed)"];
						$bloquear = true;
					} else {
						$cedulasAlumnoEnCSV[$aCed] = true;
					}
				}

				list($aFNac, $aFNacErr) = $this->normalizarFecha($alumnoFNac);
				if ($alumnoFNac === '') {
					$obs[] = ['tipo'=>'error', 'msg'=>'Fecha de nacimiento vacía → es obligatoria'];
					$bloquear = true;
				} elseif ($aFNacErr) {
					$obs[] = ['tipo'=>'error', 'msg'=>"Fecha de nacimiento '$alumnoFNac' inválida ($aFNacErr)"];
					$bloquear = true;
				}

				list($aFIng, $aFIngErr) = $this->normalizarFecha($alumnoFIng);
				if ($aFIngErr) {
					$obs[] = ['tipo'=>'warn', 'msg'=>"Fecha de ingreso '$alumnoFIng' inválida ($aFIngErr) → se omitirá"];
					$aFIng = null;
				} elseif ($aFIng === '') {
					$aFIng = null;
				}

				// --- Representante ---
				list($rPN, $rSN, $rAP, $rAM) = $this->partirNombre($repreNombre);
				if ($repreNombre === '' || $rPN === '') {
					$obs[] = ['tipo'=>'error', 'msg'=>'Representante sin nombre → no se puede vincular'];
					$bloquear = true;
				}

				list($rCed, $rCedErr) = $this->normalizarCedulaRepre($repreCedula);
				if ($rCed === '') {
					$obs[] = ['tipo'=>'error', 'msg'=>'Representante sin cédula → no se puede crear/identificar'];
					$bloquear = true;
				} elseif ($rCedErr) {
					$obs[] = ['tipo'=>'warn', 'msg'=>"Cédula del representante '$rCed' $rCedErr"];
				}

				list($rMail, $rMailErr) = $this->validarCorreo($repreCorreo);
				if ($rMailErr) {
					$obs[] = ['tipo'=>'warn', 'msg'=>"Correo del representante '$repreCorreo' $rMailErr"];
					if ($rMail === '') $rMail = '';
				}

				list($rCel, $rCelErr) = $this->normalizarCelular($repreCelular);
				if ($rCelErr) {
					$obs[] = ['tipo'=>'warn', 'msg'=>"Celular del representante '$repreCelular' $rCelErr"];
				}

				// --- Detección de hermanos / dedup representante ---
				$repreEsHermanoEnCSV = false;
				$repreYaEnBD = false;
				if ($rCed !== '') {
					if (isset($cedulasRepreEnCSV[$rCed])) {
						$repreEsHermanoEnCSV = true;
						$obs[] = ['tipo'=>'info', 'msg'=>"Representante repetido en CSV (hermano detectado, cédula $rCed) → se reutilizará"];
					} else {
						$cedulasRepreEnCSV[$rCed] = true;
					}
					if (isset($repreExistentes[$rCed])) {
						$repreYaEnBD = true;
						$obs[] = ['tipo'=>'info', 'msg'=>"Representante con cédula $rCed YA existe en BD (repre_id ".$repreExistentes[$rCed].") → se reutilizará"];
					}
				}

				// Conteo de advertencias / errores
				foreach ($obs as $o) {
					if ($o['tipo'] === 'warn') $resultado['resumen']['advertencias']++;
				}
				if ($bloquear) {
					$resultado['resumen']['errores_bloqueantes']++;
				} else {
					$resultado['resumen']['alumnos_a_insertar']++;
					if ($rCed !== '' && !$repreEsHermanoEnCSV && !$repreYaEnBD) {
						$resultado['resumen']['representantes_nuevos']++;
					} else {
						$resultado['resumen']['representantes_reuso']++;
					}
				}

				$resultado['filas'][] = [
					'linea'    => $lineaCSV,
					'bloquear' => $bloquear,
					'obs'      => $obs,
					'alumno'   => [
						'nombre_completo'   => $alumnoNombre,
						'identificacion'    => $aCed,
						'primer_nombre'     => $aPN,
						'segundo_nombre'    => $aSN,
						'apellido_paterno'  => $aAP,
						'apellido_materno'  => $aAM,
						'fecha_nacimiento'  => $aFNac ?: null,
						'fecha_ingreso'     => $aFIng,
					],
					'repre'    => [
						'nombre_completo'   => $repreNombre,
						'identificacion'    => $rCed,
						'primer_nombre'     => $rPN,
						'segundo_nombre'    => $rSN,
						'apellido_paterno'  => $rAP,
						'apellido_materno'  => $rAM,
						'correo'            => $rMail,
						'celular'           => $rCel,
						'direccion'         => $repreDireccion,
					],
				];
			}

			$resultado['resumen']['representantes_unicos'] = count($cedulasRepreEnCSV);
			return $resultado;
		}

		/* ---------- Render del reporte ---------- */

		private function generarHTMLReporte($r) {
			$res = $r['resumen'];
			$html  = '<div class="row mb-3">';
			$html .= '<div class="col-md-3"><div class="small-box text-bg-info"><div class="inner"><h3>'.$res['total_filas'].'</h3><p>Filas en CSV</p></div></div></div>';
			$html .= '<div class="col-md-3"><div class="small-box text-bg-success"><div class="inner"><h3>'.$res['alumnos_a_insertar'].'</h3><p>Alumnos a insertar</p></div></div></div>';
			$html .= '<div class="col-md-3"><div class="small-box text-bg-primary"><div class="inner"><h3>'.$res['representantes_nuevos'].'</h3><p>Repres. nuevos</p></div></div></div>';
			$html .= '<div class="col-md-3"><div class="small-box text-bg-warning"><div class="inner"><h3>'.$res['errores_bloqueantes'].'</h3><p>Filas bloqueadas</p></div></div></div>';
			$html .= '</div>';

			$html .= '<div class="row mb-3">';
			$html .= '<div class="col-md-3"><span class="badge text-bg-secondary">Repres. únicos en CSV: '.$res['representantes_unicos'].'</span></div>';
			$html .= '<div class="col-md-3"><span class="badge text-bg-secondary">Repres. reutilizados: '.$res['representantes_reuso'].'</span></div>';
			$html .= '<div class="col-md-3"><span class="badge text-bg-secondary">Advertencias: '.$res['advertencias'].'</span></div>';
			$html .= '<div class="col-md-3"><span class="badge text-bg-secondary">Sin cédula (SINCEDULA): '.$res['alumnos_sin_cedula'].'</span></div>';
			$html .= '</div>';

			$html .= '<table class="table table-sm table-bordered table-striped"><thead class="thead-dark"><tr>'
				   . '<th>#</th><th>Estado</th><th>Alumno</th><th>Cédula</th><th>F.Nac</th><th>Representante</th><th>Cédula Rep.</th><th>Observaciones</th>'
				   . '</tr></thead><tbody>';

			foreach ($r['filas'] as $f) {
				$badge = $f['bloquear']
					? '<span class="badge text-bg-danger">BLOQUEADO</span>'
					: '<span class="badge text-bg-success">OK</span>';

				$obsHtml = '';
				foreach ($f['obs'] as $o) {
					$cls = $o['tipo'] === 'error' ? 'text-danger' : ($o['tipo'] === 'warn' ? 'text-warning' : 'text-info');
					$obsHtml .= '<div class="'.$cls.'"><small>• '.htmlspecialchars($o['msg']).'</small></div>';
				}

				$html .= '<tr>'
					. '<td>'.$f['linea'].'</td>'
					. '<td>'.$badge.'</td>'
					. '<td>'.htmlspecialchars($f['alumno']['nombre_completo']).'</td>'
					. '<td>'.htmlspecialchars($f['alumno']['identificacion']).'</td>'
					. '<td>'.htmlspecialchars($f['alumno']['fecha_nacimiento'] ?? '').'</td>'
					. '<td>'.htmlspecialchars($f['repre']['nombre_completo']).'</td>'
					. '<td>'.htmlspecialchars($f['repre']['identificacion']).'</td>'
					. '<td>'.($obsHtml ?: '<small class="text-muted">—</small>').'</td>'
					. '</tr>';
			}

			$html .= '</tbody></table>';
			return $html;
		}
	}
