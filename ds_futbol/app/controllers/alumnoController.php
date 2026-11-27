<?php

	namespace app\controllers;
	use app\models\mainModel;
	use Exception;

	class alumnoController extends mainModel{

		/*----------  Controlador registrar alumno  ----------*/		
		public function registrarAlumnoControlador(){						
			/*---------------Variables para el registro del tab del alumno----------------*/
			$alumno_repreid 			= $this->limpiarCadena($_POST['alumno_repreid']);
			$alumno_identificacion 		= $this->limpiarCadena($_POST['alumno_identificacion']);
			$alumno_apellidopaterno 	= $this->limpiarCadena($_POST['alumno_apellido1']);
			$alumno_apellidomaterno 	= $this->limpiarCadena($_POST['alumno_apellido2']);
			$alumno_tipoidentificacion 	= $this->limpiarCadena($_POST['alumno_tipoidentificacion']);			
			$alumno_primernombre 		= $this->limpiarCadena($_POST['alumno_nombre1']);
			$alumno_segundonombre 		= $this->limpiarCadena($_POST['alumno_nombre2']);
			$alumno_nacionalidadid		= $this->limpiarCadena($_POST['alumno_nacionalidadid']);
			$alumno_fechanacimiento 	= $this->limpiarCadena($_POST['alumno_fechanacimiento']);
			$alumno_direccion 			= $this->limpiarCadena($_POST['alumno_direccion']);	
			$alumno_fechaingreso		= $this->limpiarCadena($_POST['alumno_fechaingreso']);
			$alumno_sedeid 				= $this->limpiarCadena($_POST['alumno_sedeid']);
			$alumno_nombrecorto 		= ""; //$this->limpiarCadena($_POST['alumno_nombrecorto']);
			$alumno_posicionid			= $this->posicionDeJuego($_POST['alumno_posicionid'] ?? '');
			$alumno_numcamiseta 		= $_POST['alumno_numcamiseta'];
			$alumno_estado 				= "A";
			$alumno_genero 				= "";
			$alumno_hermanos 			= "";

			if ($alumno_numcamiseta == "" ){$alumno_numcamiseta = 0;}

			if (isset($_POST['alumno_genero']) && isset($_POST['alumno_hermanos'])) {
				$alumno_genero 				= $_POST['alumno_genero'];
				$alumno_hermanos 			= $_POST['alumno_hermanos'];

			}else{
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No ha completado los campos obligatorios del alumno",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}			
			
		    # Verificando campos obligatorios #
		    if($alumno_identificacion=="" || $alumno_primernombre=="" || $alumno_apellidopaterno=="" || $alumno_fechanacimiento==""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No ha completado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }

		    # Verificando integridad de los datos #
		    if($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ]{3,40}",$alumno_primernombre)){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El nombre ingresado no coincide con el formato solicitado",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }	    

            # Verificando identificacion #
		    $check_alumno=$this->ejecutarConsulta("SELECT alumno_identificacion FROM sujeto_alumno WHERE alumno_identificacion = :ident", [':ident' => $alumno_identificacion]);
		    if($check_alumno->rowCount()>0){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"La identificación ingresada ya se encuentra registrada, por favor verificar",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }

		    # Directorio de imagenes #
    		$img_dir="../views/imagenes/fotos/alumno/";
			$codigo=rand(0,100);

    		# Comprobar si se selecciono una imagen #
    		if($_FILES['alumno_foto']['name']!="" && $_FILES['alumno_foto']['size']>0){

    			# Creando directorio #
		        if(!file_exists($img_dir)){
		            if(!mkdir($img_dir,0777)){
		            	$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error",
							"texto"=>"No fue posible crear el directorio",
							"icono"=>"error"
						];
						return json_encode($alerta);
		            } 
		        }

		        # Verificando formato de imagenes #
		        if(mime_content_type($_FILES['alumno_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['alumno_foto']['tmp_name'])!="image/png"){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Verificando peso de imagen #
		        if(($_FILES['alumno_foto']['size']/1024)>4000){
		        	$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
		        }

		        # Nombre de la foto #
		        $foto=str_ireplace(" ","_",$alumno_identificacion);
		        $foto=$foto."_".$codigo;

		        # Extension de la imagen #
		        switch(mime_content_type($_FILES['alumno_foto']['tmp_name'])){
		            case 'image/jpeg':
		                $foto=$foto.".jpg";
		            break;
		            case 'image/png':
		                $foto=$foto.".png";
		            break;
		        }
				$maxWidth = 800;
    			$maxHeight = 600;

				chmod($img_dir,0777);
				$inputFile = ($_FILES['alumno_foto']['tmp_name']);
       			$outputFile = $img_dir.$foto;

				# Moviendo imagen al directorio #
				//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
				if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
					
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"No es posible subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

    		}else{
    			$foto="";
    		}

			/*---------------Registro del tab Cedula del alumno----------------*/

			# Directorio de imagenes #
			$img_cedula="../views/imagenes/cedulas/";

			# Comprobar si seleccionó el Anverso de la cédula #
			if($_FILES['alumno_cedulaA']['name']!="" && $_FILES['alumno_cedulaA']['size']>0){

				# Creando directorio #
				if(!file_exists($img_cedula)){
					if(!mkdir($img_cedula,0777)){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error",
							"texto"=>"No fue posible crear el directorio para almacenar las imágenes de la cédula",
							"icono"=>"error"
						];
						return json_encode($alerta);
					} 
				}

				# Verificando formato de imagenes #
				if(mime_content_type($_FILES['alumno_cedulaA']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['alumno_cedulaA']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['alumno_cedulaA']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$cedulaA=str_ireplace(" ","_",$alumno_identificacion);
				$cedulaA=$cedulaA."_A".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['alumno_cedulaA']['tmp_name'])){
					case 'image/jpeg':
						$cedulaA=$cedulaA.".jpg";
					break;
					case 'image/png':
						$cedulaA=$cedulaA.".png";
					break;
				}
				$maxWidth = 800;
				$maxHeight = 600;

				chmod($img_cedula,0777);
				$inputFile = ($_FILES['alumno_cedulaA']['tmp_name']);
				$outputFile = $img_cedula.$cedulaA;

				# Moviendo imagen al directorio #
				//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
				if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
					
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"No es posible subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

			}else{
				$cedulaA="";
			}

			# Comprobar si seleccionó el reverso de la cédula #
			if($_FILES['alumno_cedulaR']['name']!="" && $_FILES['alumno_cedulaR']['size']>0){

				# Creando directorio #
				if(!file_exists($img_cedula)){
					if(!mkdir($img_cedula,0777)){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Ocurrió un error",
							"texto"=>"No fue posible crear el directorio para almacenar las imágenes de la cédula",
							"icono"=>"error"
						];
						return json_encode($alerta);
					} 
				}

				# Verificando formato de imagenes #
				if(mime_content_type($_FILES['alumno_cedulaR']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['alumno_cedulaR']['tmp_name'])!="image/png"){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"La imagen que ha seleccionado es de un formato no permitido",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Verificando peso de imagen #
				if(($_FILES['alumno_cedulaR']['size']/1024)>4000){
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"La imagen que ha seleccionado supera el peso permitido 4MB",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

				# Nombre de la foto #
				$cedulaR=str_ireplace(" ","_",$alumno_identificacion);
				$cedulaR=$cedulaR."_R".$codigo;

				# Extension de la imagen #
				switch(mime_content_type($_FILES['alumno_cedulaR']['tmp_name'])){
					case 'image/jpeg':
						$cedulaR=$cedulaR.".jpg";
					break;
					case 'image/png':
						$cedulaR=$cedulaR.".png";
					break;
				}
				$maxWidth = 800;
				$maxHeight = 600;

				chmod($img_cedula,0777);
				$inputFile = ($_FILES['alumno_cedulaR']['tmp_name']);
				$outputFile = $img_cedula.$cedulaR;

				# Moviendo imagen al directorio #
				//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
				if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
					
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"No es posible subir la imagen al sistema en este momento",
						"icono"=>"error"
					];
					return json_encode($alerta);
				}

			}else{
				$cedulaR="";
			}
				
		    $alumno_datos_reg=[
				[
					"campo_nombre"=>"alumno_repreid",
					"campo_marcador"=>":Repreid",
					"campo_valor"=>$alumno_repreid
				],
				[
					"campo_nombre"=>"alumno_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$alumno_sedeid
				],
				[
					"campo_nombre"=>"alumno_posicionid",
					"campo_marcador"=>":Posicionid",
					"campo_valor"=>$alumno_posicionid
				],
				[
					"campo_nombre"=>"alumno_nacionalidadid",
					"campo_marcador"=>":Nacionalidadid",
					"campo_valor"=>$alumno_nacionalidadid
				],
				[
					"campo_nombre"=>"alumno_tipoidentificacion",
					"campo_marcador"=>":Tipoidentificacion",
					"campo_valor"=>$alumno_tipoidentificacion
				],
				[
					"campo_nombre"=>"alumno_identificacion",
					"campo_marcador"=>":Identificacion",
					"campo_valor"=>$alumno_identificacion
				],				
				[
					"campo_nombre"=>"alumno_primernombre",
					"campo_marcador"=>":Primernombre",
					"campo_valor"=>$alumno_primernombre
				],
				[
					"campo_nombre"=>"alumno_segundonombre",
					"campo_marcador"=>":Segundonombre",
					"campo_valor"=>$alumno_segundonombre
				],				
				[
					"campo_nombre"=>"alumno_apellidopaterno",
					"campo_marcador"=>":Apellidopaterno",
					"campo_valor"=>$alumno_apellidopaterno
				],
				[
					"campo_nombre"=>"alumno_apellidomaterno",
					"campo_marcador"=>":Apellidomaterno",
					"campo_valor"=>$alumno_apellidomaterno
				],
				[
					"campo_nombre"=>"alumno_nombrecorto",
					"campo_marcador"=>":Nombrecorto",
					"campo_valor"=>$alumno_nombrecorto
				],
				[
					"campo_nombre"=>"alumno_direccion",
					"campo_marcador"=>":Direccion",
					"campo_valor"=>$alumno_direccion
				],
				[
					"campo_nombre"=>"alumno_fechanacimiento",
					"campo_marcador"=>":Fechanacimiento",
					"campo_valor"=>$alumno_fechanacimiento
				],
				[
					"campo_nombre"=>"alumno_fechaingreso",
					"campo_marcador"=>":Fechaingreso",
					"campo_valor"=>$alumno_fechaingreso
				],
				[
					"campo_nombre"=>"alumno_genero",
					"campo_marcador"=>":Genero",
					"campo_valor"=>$alumno_genero
				],
				[
					"campo_nombre"=>"alumno_hermanos",
					"campo_marcador"=>":Hermanos",
					"campo_valor"=>$alumno_hermanos
				],
				[
					"campo_nombre"=>"alumno_estado",
					"campo_marcador"=>":Activo",
					"campo_valor"=>$alumno_estado
				],
				[
					"campo_nombre"=>"alumno_imagen",
					"campo_marcador"=>":Foto",
					"campo_valor"=>$foto
				],
				[
					"campo_nombre"=>"alumno_numcamiseta",
					"campo_marcador"=>":Camiseta",
					"campo_valor"=>$alumno_numcamiseta
				],
				[
					"campo_nombre"=>"alumno_cedulaA",
					"campo_marcador"=>":CedulaA",
					"campo_valor"=>$cedulaA
				],
				[
					"campo_nombre"=>"alumno_cedulaR",
					"campo_marcador"=>":CedulaR",
					"campo_valor"=>$cedulaR
				]
			];

			$registrar_alumno=$this->guardarDatos("sujeto_alumno",$alumno_datos_reg);

			/*---------------Inicio de registro de Información de los tabs*/
			if($registrar_alumno->rowCount()==1){
				$alerta=[
					"tipo"=>"limpiar",
					"titulo"=>"Alumno registrado",
					"texto"=>"El alumno ".$alumno_identificacion." | ".$alumno_primernombre." ".$alumno_apellidopaterno." se registró correctamente",
					"icono"=>"success"
				];

				$infomedic_tiposangre 	= $this->limpiarCadena($_POST['infomedic_tiposangre']);
				$infomedic_peso		  	= $this->limpiarCadena($_POST['infomedic_peso']);
				$infomedic_talla 	  	= $this->limpiarCadena($_POST['infomedic_talla']);
				$infomedic_enfermedad 	= $this->limpiarCadena($_POST['infomedic_enfermedad']);
				$infomedic_medicamentos = $this->limpiarCadena($_POST['infomedic_medicamentos']);
				$infomedic_alergia1 	= $this->limpiarCadena($_POST['infomedic_alergia1']);
				$infomedic_alergia2 	= $this->limpiarCadena($_POST['infomedic_alergia2']);
				$infomedic_cirugias 	= $this->limpiarCadena($_POST['infomedic_cirugias']);
				$infomedic_observacion	= $this->limpiarCadena($_POST['infomedic_observacion']);

				if ($infomedic_peso ==""){$infomedic_peso = 0;}
				if ($infomedic_talla ==""){$infomedic_talla = 0;}

				if(isset($_POST['infomedic_covid'])){ $infomedic_covid  = $_POST['infomedic_covid']; }else {$infomedic_covid="";}
				if(isset($_POST['infomedic_vacunas'])){ $infomedic_vacunas  = $_POST['infomedic_vacunas']; }else {$infomedic_vacunas="";}

				/*---------------Obtengo campo alumnoid para todas las tablas*/
				$check_alumno=$this->ejecutarConsulta("SELECT alumno_id FROM sujeto_alumno WHERE alumno_identificacion = :ident", [':ident' => $alumno_identificacion]);
		
				if($check_alumno->rowCount()==1){
					$alumno=$check_alumno->fetchAll(); 					
					foreach( $alumno as $rows ){
						$alumnoid = $rows['alumno_id'];
					}
				}

				/*---------------Registro del tab Información Médica del alumno*/
				if($infomedic_tiposangre!="" || $infomedic_peso>0 || $infomedic_talla>0 || $infomedic_enfermedad!=""||
					$infomedic_medicamentos!="" || $infomedic_alergia1!="" || $infomedic_alergia2!="" || $infomedic_cirugias!="" ||
					$infomedic_observacion!=""){

					$infomedic_reg=[
						[
							"campo_nombre"=>"infomedic_alumnoid",
							"campo_marcador"=>":Alumnoid",
							"campo_valor"=>$alumnoid
						],
						[
							"campo_nombre"=>"infomedic_fecha",
							"campo_marcador"=>":Fechacreacion",
							"campo_valor"=>date("Y-m-d H:i:s")
						],
						[
							"campo_nombre"=>"infomedic_tiposangre",
							"campo_marcador"=>":Tiposangre",
							"campo_valor"=>$infomedic_tiposangre
						],
						[
							"campo_nombre"=>"infomedic_peso",
							"campo_marcador"=>":Peso",
							"campo_valor"=>$infomedic_peso
						],
						[
							"campo_nombre"=>"infomedic_talla",
							"campo_marcador"=>":Talla",
							"campo_valor"=>$infomedic_talla
						],
						[
							"campo_nombre"=>"infomedic_enfermedad",
							"campo_marcador"=>":Enfermedad",
							"campo_valor"=>$infomedic_enfermedad
						],
						[
							"campo_nombre"=>"infomedic_medicamentos",
							"campo_marcador"=>":Medicamentos",
							"campo_valor"=>$infomedic_medicamentos
						],
						[
							"campo_nombre"=>"infomedic_alergia1",
							"campo_marcador"=>":AlergiaMedicamentos",
							"campo_valor"=>$infomedic_alergia1
						],
						[
							"campo_nombre"=>"infomedic_alergia2",
							"campo_marcador"=>":AlergiaObjetos",
							"campo_valor"=>$infomedic_alergia2
						],
						[
							"campo_nombre"=>"infomedic_cirugias",
							"campo_marcador"=>":Cirugias",
							"campo_valor"=>$infomedic_cirugias
						],
						[
							"campo_nombre"=>"infomedic_observacion",
							"campo_marcador"=>":Observacion",
							"campo_valor"=>$infomedic_observacion
						],
						[
							"campo_nombre"=>"infomedic_covid",
							"campo_marcador"=>":VacunasCovid",
							"campo_valor"=>$infomedic_covid
						],
						[
							"campo_nombre"=>"infomedic_vacunas",
							"campo_marcador"=>":Vacunas",
							"campo_valor"=>$infomedic_vacunas
						]
					];

					$this->guardarDatos("alumno_infomedic",$infomedic_reg);
				}

			 	/*---------------Fin de registro del tab Información Médica del alumno*/

			 	/*---------------Registro del tab Contacto Emergencia del alumno------------*/
				$cemer_nombre 		= $this->limpiarCadena($_POST['cemer_nombre']);
				$cemer_celular 		= $this->limpiarCadena($_POST['cemer_celular']);
				$cemer_parentesco	= $this->limpiarCadena($_POST['cemer_parentesco']);

				if($cemer_nombre!="" || $cemer_celular!=""){					
					$cemergencia_reg=[
						[
							"campo_nombre"=>"cemer_alumnoid",
							"campo_marcador"=>":Alumnoid",
							"campo_valor"=>$alumnoid
						],						
						[
							"campo_nombre"=>"cemer_nombre",
							"campo_marcador"=>":NombreContactoEmer",
							"campo_valor"=>$cemer_nombre
						],
						[
							"campo_nombre"=>"cemer_celular",
							"campo_marcador"=>":CelularContactoEmer",
							"campo_valor"=>$cemer_celular
						],
						[
							"campo_nombre"=>"cemer_parentesco",
							"campo_marcador"=>":ParentescoContactoEmer",
							"campo_valor"=>$cemer_parentesco
						]
					];

					$this->guardarDatos("alumno_cemergencia",$cemergencia_reg);
				}
			 	/*---------------Fin de registro del tab Contacto Emergencia del alumno------*/

				/*---------------Actulizar horario de entrenamiento---------------------*/
				$horario_id = $this->limpiarCadena($_POST['horarioid']);

				/* Sólo si se eligió horario. La condición anterior era
				   ($horario_id != "" || $alumnoid != ""), siempre cierta porque el
				   alumno ya existe: sin horario insertaba horarioid vacío, la clave
				   ajena fk_asigna_horario lo rechazaba (1452) y el usuario veía un
				   error con el alumno YA guardado, invitando a registrarlo dos veces. */
				if($horario_id != ""){

					$asignacion_horario_reg = [
						[
							"campo_nombre" => "asignahorario_horarioid",
							"campo_marcador" => ":Horarioid",
							"campo_valor" => $horario_id
						],
						[
							"campo_nombre" => "asignahorario_alumnoid",
							"campo_marcador" => ":Alumnoid",
							"campo_valor" => $alumnoid						
						]
					];

					$this->guardarDatos("asistencia_asignahorario",$asignacion_horario_reg);
				}

				
				/*---------------Fin de actualizacion de horariop de entrenamiento------*/
			}else{				
				if(is_file($img_dir.$foto)){
					chmod($img_dir.$foto,0777);
					unlink($img_dir.$foto);
				}

				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No se pudo registrar la información del alumno, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}


		/*----------  Listado de alumnos (alumnoList)  ----------*/
		/*
		| Una fila por alumno con el marcado de fichas (.ds-crud, futbol.css):
		| tabla en escritorio, ficha por debajo de 992 px.
		|
		| $estado: A por defecto —el listado muestra los activos—, I o T.
		| La búsqueda por identificación, apellido y nombre se combina con O,
		| como antes; el año, la sede y el estado, con Y.
		*/
		/*
		| $posicion filtra por la posición de juego (código del catálogo).
		| $porPosicion devuelve cuántos alumnos del resultado hay en cada una
		| ([codigo => ['texto' => …, 'total' => n]]), para el resumen del listado.
		*/
		/*
		| $horario: '' todos, 'S' con horario asignado, 'N' sin horario.
		| $porHorario devuelve cuántos del resultado tienen y no tienen horario
		| (antes de aplicar ese filtro, para que el resumen muestre las dos cifras).
		| Cuenta sólo los horarios activos: uno inactivo no da clase.
		*/
		public function listarAlumnos($identificacion, $apellidopaterno, $primernombre, $anio, $sede, $estado = 'A', $posicion = '', ?array &$porPosicion = null, $horario = '', ?array &$porHorario = null){
			$condiciones = [];
			$busqueda = [];
			$parametros = [];
			$porPosicion = [];
			$porHorario = ['S' => 0, 'N' => 0];

			if ($identificacion != "") {
				$busqueda[] = "A.alumno_identificacion LIKE :ident";
				$parametros[':ident'] = $identificacion . '%';
				/* La cédula real también encuentra su registro en la sede con
				   código (PA000001…), que lleva el código como identificación. */
				if ($this->hayVinculoAlumnos()) {
					$busqueda[] = "A.alumno_origenid IN (SELECT O.alumno_id FROM sujeto_alumno O WHERE O.alumno_identificacion LIKE :identO)";
					$parametros[':identO'] = $identificacion . '%';
				}
			}
			if ($primernombre != "") {
				$busqueda[] = "A.alumno_primernombre LIKE :nombre";
				$parametros[':nombre'] = $primernombre . '%';
			}
			if ($apellidopaterno != "") {
				$busqueda[] = "A.alumno_apellidopaterno LIKE :apellido";
				$parametros[':apellido'] = $apellidopaterno . '%';
			}
			if ($busqueda) {
				$condiciones[] = "(" . implode(" OR ", $busqueda) . ")";
			}

			/* Año de nacimiento (la categoría): rango, para que use el índice. */
			if (preg_match('/^\d{4}$/', (string)$anio)) {
				$condiciones[] = "A.alumno_fechanacimiento BETWEEN :anioIni AND :anioFin";
				$parametros[':anioIni'] = $anio . '-01-01';
				$parametros[':anioFin'] = $anio . '-12-31';
			}

			if (preg_match('/^[A-Z0-9]{3}$/', (string)$posicion)) {
				$condiciones[] = "A.alumno_posicionid = :posicion";
				$parametros[':posicion'] = $posicion;
			}

			$condiciones[] = $this->condicionSede('A.alumno_sedeid', $sede, $parametros);
			$condiciones[] = $this->condicionEstadoAlumno('A.alumno_estado', $estado);

			$datos = $this->ejecutarConsulta("SELECT A.alumno_id, A.alumno_identificacion, A.alumno_primernombre, A.alumno_segundonombre,
						A.alumno_apellidopaterno, A.alumno_apellidomaterno, A.alumno_fechanacimiento, A.alumno_estado, A.alumno_imagen,
						A.alumno_posicionid, PJ.catalogo_descripcion AS posicion,
						S.sede_nombre, TIMESTAMPDIFF(YEAR, A.alumno_fechanacimiento, CURDATE()) AS EDAD,
						HA.horario_id, HA.horario_nombre, HA.horario_detalle
					FROM sujeto_alumno A
					LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
					/* El horario activo asignado (asistenciaHorarioJugador). Uno por
					   alumno: la asignación sólo ofrece a los que no tienen ninguno;
					   si alguna vez hubiera dos, se toma el primero. */
					LEFT JOIN (SELECT G.asignahorario_alumnoid AS alumnoid, MIN(H.horario_id) AS horario_id
					           FROM asistencia_asignahorario G
					           INNER JOIN asistencia_horario H ON H.horario_id = G.asignahorario_horarioid AND H.horario_estado = 'A'
					           GROUP BY G.asignahorario_alumnoid) GA ON GA.alumnoid = A.alumno_id
					LEFT JOIN asistencia_horario HA ON HA.horario_id = GA.horario_id
					LEFT JOIN (general_tabla_catalogo PJ
					           INNER JOIN general_tabla PJT ON PJT.tabla_id = PJ.catalogo_tablaid
					                                       AND PJT.tabla_nombre = 'posicion_juego')
					       ON PJ.catalogo_valor = A.alumno_posicionid
					WHERE " . implode(" AND ", $condiciones) . "
					ORDER BY A.alumno_apellidopaterno, A.alumno_apellidomaterno, A.alumno_primernombre", $parametros)->fetchAll();

			/* La interfaz sólo ofrece lo que el rol puede hacer en esta
			   pantalla. Es acompañamiento del control real, que sigue
			   estando en ajax_guard: aquí se evita ofrecer un botón que
			   luego iba a rechazarse. */
			$puedeEditar   = puede_editar('alumnoList');
			$puedeEliminar = puede_eliminar('alumnoList');

			/* Los textos se guardan escapados (limpiarHtml): se deshace y se
			   escapa una sola vez. Antes iban tal cual a la tabla. */
			$h = static fn($v) => htmlspecialchars(html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
			$junta = static fn(...$p) => preg_replace('/\s+/', ' ', trim(implode(' ', $p)));

			$tabla = "";
			foreach ($datos as $al) {
				/* Con / sin horario: se cuenta antes de filtrar por ello, para que
				   el resumen diga siempre las dos cifras del resultado. */
				$conHorario = $al['horario_id'] !== null;
				$porHorario[$conHorario ? 'S' : 'N']++;
				if (($horario === 'S' && !$conHorario) || ($horario === 'N' && $conHorario)) {
					continue;
				}
				$id      = (int)$al['alumno_id'];
				$activo  = $al['alumno_estado'] === 'A';
				$nombre  = $junta($al['alumno_primernombre'], $al['alumno_segundonombre'], $al['alumno_apellidopaterno'], $al['alumno_apellidomaterno']);
				$orden   = $junta($al['alumno_apellidopaterno'], $al['alumno_apellidomaterno'], $al['alumno_primernombre']);
				$inicial = mb_strtoupper(mb_substr(html_entity_decode((string)$al['alumno_primernombre']), 0, 1) . mb_substr(html_entity_decode((string)$al['alumno_apellidopaterno']), 0, 1));
				$fnac    = (string)$al['alumno_fechanacimiento'];
				$nacio   = ($fnac !== '' && !str_starts_with($fnac, '0000')) ? date('d/m/Y', strtotime($fnac)) : '';

				/* Sin posición o con una que ya no está en el catálogo: «No definido». */
				$posCodigo = $al['posicion'] !== null ? (string)$al['alumno_posicionid'] : self::POSICION_NO_DEFINIDA;
				$posTexto  = $al['posicion'] !== null ? (string)$al['posicion'] : 'No definido';
				$porPosicion[$posCodigo] ??= ['texto' => $posTexto, 'total' => 0];
				$porPosicion[$posCodigo]['total']++;
				$posCelda = $posCodigo === self::POSICION_NO_DEFINIDA
					? '<span class="text-body-secondary">'.$h($posTexto).'</span>'
					: $h($posTexto);

				/* Horario: el nombre, que lleva a su lista de alumnos; o un aviso
				   «Sin horario», que lleva a asignarlo desde los horarios. */
				$horCelda = $conHorario
					? '<a href="'.APP_URL.'asistenciaHorarioLista/'.(int)$al['horario_id'].'/" class="ds-alumnos__horario" title="'.$h($al['horario_detalle']).'"><i class="far fa-clock" aria-hidden="true"></i> '.$h($al['horario_nombre']).'</a>'
					: '<a href="'.APP_URL.'asistenciaListHorario/" class="badge ds-alumnos__sin-horario" title="Asignarle un horario desde la lista de horarios"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Sin horario</a>';

				$botones = '<a href="'.APP_URL.'alumnoProfile/'.$id.'/" class="btn btn-primary btn-sm ds-ficha__primario"><i class="fas fa-id-card me-1" aria-hidden="true"></i>Ver ficha</a>';
				if ($puedeEditar) {
					$botones .= '<a href="'.APP_URL.'alumnoUpdate/'.$id.'/" class="btn btn-info btn-sm" title="Editar" aria-label="Editar a '.$h($nombre).'"><i class="fas fa-pen" aria-hidden="true"></i></a>';
					/* Sin un formulario por fila: el botón lleva la acción y el id,
					   y un único formulario de la vista (#formAccionAlumno) se
					   rellena al pulsarlo. Con dos formularios por alumno, 223
					   activos eran 543 KB de página, y «Todos», más de 2 MB. */
					$botones .= '<button type="button" class="btn btn-sm '.($activo ? 'btn-secondary' : 'btn-success').' js-alumno" data-accion="actualizarestado" data-id="'.$id.'"'
						.' title="'.($activo ? 'Inactivar' : 'Activar').'" aria-label="'.($activo ? 'Inactivar' : 'Activar').' a '.$h($nombre).'">'
						.'<i class="fas '.($activo ? 'fa-user-slash' : 'fa-user-check').'" aria-hidden="true"></i></button>';
				}
				if ($puedeEliminar) {
					$botones .= ds_boton_eliminar('a '.$nombre, ['type' => 'button', 'class' => 'js-alumno', 'data' => ['accion' => 'eliminar', 'id' => $id]]);
				}

				$tabla .= '
					<tr>
						<td class="ds-ficha__principal" data-order="'.$h($orden).'">
							<div class="ds-ficha__con-imagen">
								'.$this->avatarAlumno($al['alumno_imagen'], $inicial).'
								<div class="min-w-0">
									<div class="ds-ficha__nombre">'.$h($nombre).'</div>
									<div class="ds-ficha__meta"><i class="fas fa-id-card" aria-hidden="true"></i> '.$h($al['alumno_identificacion']).'</div>
								</div>
							</div>
						</td>
						<td class="ds-ficha__dato ds-ficha__nowrap" data-etiqueta="Nacimiento" data-order="'.$h($fnac).'">'
							.($nacio !== '' ? $nacio.' · <strong>'.(int)$al['EDAD'].' años</strong>' : '<span class="text-body-secondary">—</span>').'</td>
						<td class="ds-ficha__dato ds-col-opcional" data-etiqueta="Sede">'.$h($al['sede_nombre'] ?? '').'</td>
						<td class="ds-ficha__dato" data-etiqueta="Posición">'.$posCelda.'</td>
						<td class="ds-ficha__dato" data-etiqueta="Horario" data-order="'.($conHorario ? '1 '.$h($al['horario_nombre']) : '0').'" data-search="'.($conHorario ? $h($al['horario_nombre']) : 'Sin horario').'">'.$horCelda.'</td>
						<td class="ds-ficha__estado" data-order="'.($activo ? 'A' : 'I').'"><span class="badge '.($activo ? 'text-bg-success' : 'text-bg-secondary').'">'.($activo ? 'Activo' : 'Inactivo').'</span></td>
						<td class="ds-tabla-acciones ds-ficha__acciones"><div class="ds-ficha__botones">'.$botones.'</div></td>
					</tr>';
			}
			return $tabla;
		}
		/*----------  Obtener el tipo de documento guardado  ----------*/
		public function listarOptionTipoIdentificacion($tipoidentificacion){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'tipo_documento'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($tipoidentificacion == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';	
				}else{
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}
			}
			return $option;
		}

		/*----------  Posición de juego preferida (catálogo posicion_juego)  ----------*/
		/*
		| Sirve para el trabajo diferenciado y para armar equipos y horarios.
		| Quien aún no la tiene queda en «No definido» (3ND), nunca vacío: así
		| un listado por posición cuenta a todos los alumnos (migración 085).
		*/
		public const POSICION_NO_DEFINIDA = '3ND';

		/* Opciones del catálogo: «No definido» primero y el resto por nombre.
		   La guardada sale aunque esté inactiva, para no cambiarla en silencio. */
		public function listarOptionPosicion($seleccionada = ''){
			$seleccionada = (string)$seleccionada !== '' ? (string)$seleccionada : self::POSICION_NO_DEFINIDA;
			$datos = $this->ejecutarConsulta("SELECT C.catalogo_valor, C.catalogo_descripcion
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T ON T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'posicion_juego'
								  AND (C.catalogo_estado = 'A' OR C.catalogo_valor = :sel)
								ORDER BY C.catalogo_valor = :nd DESC, C.catalogo_descripcion",
								[':sel' => $seleccionada, ':nd' => self::POSICION_NO_DEFINIDA])->fetchAll();
			$option = '';
			foreach ($datos as $rows) {
				$valor = htmlspecialchars($rows['catalogo_valor'], ENT_QUOTES, 'UTF-8');
				$texto = htmlspecialchars($rows['catalogo_descripcion'], ENT_QUOTES, 'UTF-8');
				$option .= '<option value="' . $valor . '"' . ($rows['catalogo_valor'] === $seleccionada ? ' selected' : '') . '>' . $texto . '</option>';
			}
			return $option;
		}

		/* Para el filtro del listado: las activas y las inactivas que algún
		   alumno aún tiene ([codigo => descripcion]), «No definido» primero. */
		public function catalogoPosiciones(): array {
			return $this->ejecutarConsulta("SELECT C.catalogo_valor, C.catalogo_descripcion
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T ON T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'posicion_juego'
								  AND (C.catalogo_estado = 'A'
								       OR EXISTS (SELECT 1 FROM sujeto_alumno A WHERE A.alumno_posicionid = C.catalogo_valor))
								ORDER BY C.catalogo_valor = :nd DESC, C.catalogo_descripcion",
								[':nd' => self::POSICION_NO_DEFINIDA])->fetchAll(\PDO::FETCH_KEY_PAIR);
		}

		/* El código recibido si es del catálogo y está activo (o es el que ya
		   tenía el alumno); si no, «No definido». */
		private function posicionDeJuego($codigo, string $actual = ''): string {
			$codigo = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper((string)$codigo)), 0, 3);
			if ($codigo === '') {
				return self::POSICION_NO_DEFINIDA;
			}
			$existe = $this->ejecutarConsulta("SELECT 1
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T ON T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'posicion_juego'
								  AND C.catalogo_valor = :cod
								  AND (C.catalogo_estado = 'A' OR C.catalogo_valor = :actual)
								LIMIT 1", [':cod' => $codigo, ':actual' => $actual])->fetchColumn();
			return $existe ? $codigo : self::POSICION_NO_DEFINIDA;
		}

		/*----------  Obtener la nacionalidad guardada  ----------*/
		public function listarOptionNacionalidad($alumno_nacionalidadid){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'nacionalidad'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($alumno_nacionalidadid == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';	
				}else{
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}
			}
			return $option;
		}
				
		/*----------  Obtener la sede guardada  ----------*/
		public function listarSedeAlumno($alumno_sedeid){
			$option="";

			/* Las activas y la que ya tiene el alumno, aunque esté inactiva. */
			$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede WHERE " . sql_sede_activa('sede_id', (int)$alumno_sedeid);	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($alumno_sedeid == $rows['sede_id']){
					$option.='<option value='.$rows['sede_id'].' selected="selected">'.$rows['sede_nombre'].'</option>';	
				}else{
					$option.='<option value='.$rows['sede_id'].'>'.$rows['sede_nombre'].'</option>';	
				}
			}
			return $option;
		}

		/*----------  Obtener la posición de juego guardada  ----------*/
		/* El PDF de «Imprimir» del listado: el mismo estado (A, I o T) y las
		   mismas sedes que el usuario puede ver. Antes la sede 0 eran todas
		   para cualquiera, igual que en el listado. */
		public function listarAlumnosPDF($categoriaid, $sedeid, $estado = 'A'){
			$parametros = [];
			$consulta_datos = "SELECT sede_nombre, alumno_identificacion, alumno_primernombre, alumno_segundonombre,
									alumno_apellidopaterno, alumno_apellidomaterno, alumno_fechanacimiento, alumno_estado
								FROM sujeto_alumno, general_sede
								WHERE alumno_sedeid = sede_id
									AND " . $this->condicionEstadoAlumno('alumno_estado', $estado) . "
									AND " . $this->condicionSede('alumno_sedeid', $sedeid, $parametros);

			if($categoriaid!=0){
				$consulta_datos .= " and YEAR(alumno_fechanacimiento) = :categoria";
				$parametros[':categoria'] = (int)$categoriaid;
			}

			$consulta_datos.= " ORDER BY alumno_fechanacimiento";

			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);		
			return $datos;
		}


		/*----------  Controlador eliminar alumno  ----------*/
		public function actualizarEstadoAlumnoControlador(){

			$alumno_id=$this->limpiarCadena($_POST['alumno_id']);

			# Verificando usuario #
		    $datos=$this->ejecutarConsulta("SELECT * FROM sujeto_alumno WHERE alumno_id = :id", [':id' => (int)$alumno_id]);
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El alumno no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();
		    }
			if($datos['alumno_estado']=='A'){
				$estadoA = 'I';
			}else{
				$estadoA = 'A';
			}
            $alumno_datos_up=[
				[
					"campo_nombre"=>"alumno_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"alumno_id",
				"condicion_marcador"=>":Alumnoid",
				"condicion_valor"=>$alumno_id
			];

			if($this->actualizarDatos("sujeto_alumno",$alumno_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Estado actualizado correctamente",
					"texto"=>"El estado del alumno ".$datos['alumno_primernombre']." | ".$datos['alumno_apellidopaterno']." fue actualizado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido actualizar el estado del alumno ".$datos['alumno_primernombre']." ".$datos['alumno_apellidopaterno'].", por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		/*----------  Controlador eliminar alumno  ----------*/
		public function eliminarAlumnoControlador(){

			$alumno_id=$this->limpiarCadena($_POST['alumno_id']);

			# Verificando usuario #
		    $datos=$this->ejecutarConsulta("SELECT * FROM sujeto_alumno WHERE alumno_id = :id", [':id' => (int)$alumno_id]);
		    if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El alumno no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();
		    }
			if($datos['alumno_estado']=='A' || $datos['alumno_estado']=='I'){
				$estadoA = 'E';
			}else{
				$estadoA = 'X';
			}
            $alumno_datos_up=[
				[
					"campo_nombre"=>"alumno_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> $estadoA
				]
			];
			$condicion=[
				"condicion_campo"=>"alumno_id",
				"condicion_marcador"=>":Alumnoid",
				"condicion_valor"=>$alumno_id
			];

			if($this->actualizarDatos("sujeto_alumno",$alumno_datos_up,$condicion)){

				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"El alumno fue eliminado correctamente",
					"texto"=>"El alumno ".$datos['alumno_primernombre']." | ".$datos['alumno_apellidopaterno']." fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error inesperado",
					"texto"=>"No hemos podido eliminar el alumno ".$datos['alumno_primernombre']." ".$datos['alumno_apellidopaterno'].", por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}		

		/*----------  Controlador actualizar alumno  ----------*/
		public function actualizarAlumnoControlador(){
			# Verificando si se envió el formulario #			
			try {
				if(!isset($_POST['alumno_id'])){
					throw new Exception("No se recibió el identificador del alumno.");
				}
				$alumnoid=$this->limpiarCadena($_POST['alumno_id']);

				# Verificando existencia del alumno #				
				$datos=$this->ejecutarConsulta("SELECT * FROM sujeto_alumno WHERE alumno_id = :id", [":id"=>$alumnoid]);
				if(!$datos || $datos->rowCount()<=0){
					return json_encode([
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error",
						"texto"=>"El alumno no se encuentra en el sistema",
						"icono"=>"error"
					]);	    
				}
				$datos=$datos->fetch();
   
				/*---------------Variables para el registro del tab del alumno----------------*/
				$alumno_identificacion 		= $this->limpiarCadena($_POST['alumno_identificacion'] ?? "");
				$alumno_apellidopaterno 	= $this->limpiarCadena($_POST['alumno_apellido1'] ?? "");
				$alumno_apellidomaterno 	= $this->limpiarCadena($_POST['alumno_apellido2'] ?? "");
				$alumno_tipoidentificacion 	= $this->limpiarCadena($_POST['alumno_tipoidentificacion'] ?? "");			
				$alumno_primernombre 		= $this->limpiarCadena($_POST['alumno_nombre1'] ?? "");
				$alumno_segundonombre 		= $this->limpiarCadena($_POST['alumno_nombre2'] ?? "");
				$alumno_nacionalidadid		= $this->limpiarCadena($_POST['alumno_nacionalidadid'] ?? "");
				$alumno_fechanacimiento 	= $this->limpiarCadena($_POST['alumno_fechanacimiento'] ?? "");
				$alumno_direccion 			= $this->limpiarCadena($_POST['alumno_direccion'] ?? "");	
				$alumno_fechaingreso		= $this->limpiarCadena($_POST['alumno_fechaingreso'] ?? "");
				$alumno_sedeid 				= $this->limpiarCadena($_POST['alumno_sedeid'] ?? "");				
				$alumno_numcamiseta 		= intval($_POST['alumno_numcamiseta'] ?? 0);
				/* La que ya tenía vale aunque hoy esté inactiva en el catálogo:
				   guardar el resto de la ficha no debe cambiarla sin que nadie lo pida. */
				$alumno_posicionid			= $this->posicionDeJuego($_POST['alumno_posicionid'] ?? '', (string)$datos['alumno_posicionid']);
				$alumno_genero 				= $_POST['alumno_genero'] ?? "";
				$alumno_hermanos 			= $_POST['alumno_hermanos'] ?? "";
				
				# Verificando campos obligatorios #
				if($alumno_identificacion=="" || $alumno_primernombre=="" || $alumno_apellidopaterno=="" || $alumno_fechanacimiento==""){
					 throw new Exception("No ha completado todos los campos que son obligatorios.");
				}

				# Verificando integridad de los datos #
				if($this->verificarDatos("[a-zA-ZáéíóúÁÉÍÓÚñÑ]{3,40}",$alumno_primernombre)){
					 throw new Exception("El campo nombre no coincide con el formato solicitado.");
				}
				
				$alumno_datos_reg=[
					["campo_nombre"=>"alumno_sedeid","campo_marcador"=>":Sedeid","campo_valor"=>$alumno_sedeid],
					["campo_nombre"=>"alumno_posicionid","campo_marcador"=>":Posicionid","campo_valor"=>$alumno_posicionid],
					["campo_nombre"=>"alumno_nacionalidadid","campo_marcador"=>":Nacionalidadid","campo_valor"=>$alumno_nacionalidadid],
					["campo_nombre"=>"alumno_tipoidentificacion","campo_marcador"=>":Tipoidentificacion","campo_valor"=>$alumno_tipoidentificacion],
					["campo_nombre"=>"alumno_identificacion","campo_marcador"=>":Identificacion","campo_valor"=>$alumno_identificacion],				
					["campo_nombre"=>"alumno_primernombre","campo_marcador"=>":Primernombre","campo_valor"=>$alumno_primernombre],
					["campo_nombre"=>"alumno_segundonombre","campo_marcador"=>":Segundonombre","campo_valor"=>$alumno_segundonombre],				
					["campo_nombre"=>"alumno_apellidopaterno","campo_marcador"=>":Apellidopaterno","campo_valor"=>$alumno_apellidopaterno],
					["campo_nombre"=>"alumno_apellidomaterno","campo_marcador"=>":Apellidomaterno","campo_valor"=>$alumno_apellidomaterno],
					["campo_nombre"=>"alumno_direccion","campo_marcador"=>":Direccion","campo_valor"=>$alumno_direccion],
					["campo_nombre"=>"alumno_fechanacimiento","campo_marcador"=>":Fechanacimiento","campo_valor"=>$alumno_fechanacimiento],
					["campo_nombre"=>"alumno_fechaingreso","campo_marcador"=>":Fechaingreso","campo_valor"=>$alumno_fechaingreso],
					["campo_nombre"=>"alumno_genero","campo_marcador"=>":Genero","campo_valor"=>$alumno_genero],
					["campo_nombre"=>"alumno_hermanos","campo_marcador"=>":Hermanos","campo_valor"=>$alumno_hermanos],			
					["campo_nombre"=>"alumno_numcamiseta","campo_marcador"=>":Camiseta","campo_valor"=>$alumno_numcamiseta]
				];

				# Directorio de fotos #
				$codigorand=rand(0,100);
				$img_dir="../views/imagenes/fotos/alumno/";

				# Directorio de imagenes cedula#
				$dir_cedula="../views/imagenes/cedulas/";
				/*
				# Comprobar si se selecciono una imagen #
				if($_FILES['alumno_foto']['name']!="" && $_FILES['alumno_foto']['size']>0){
			
					# Creando directorio #
					if(!file_exists($img_dir)){
						if(!mkdir($img_dir,0777)){
							$alerta=[
								"tipo"=>"simple",
								"titulo"=>"Error",
								"texto"=>"No se creó el directorio",
								"icono"=>"error"
							];
							return json_encode($alerta);
							//exit();
						} 
					}

					# Verificando formato de imagenes #
					if(mime_content_type($_FILES['alumno_foto']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['alumno_foto']['tmp_name'])!="image/png"){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"La imagen que ha seleccionado es de un formato no permitido ",
							"icono"=>"error"
						];
						return json_encode($alerta);
						//exit();
					}

					# Verificando peso de imagen #
					if(($_FILES['alumno_foto']['size']/1024)>4000){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"La imagen que ha seleccionado supera el peso permitido 4MB",
							"icono"=>"error"
						];
						return json_encode($alerta);
						//exit();
					}

					#nombre de la foto
					$foto=str_ireplace(" ","_",$alumno_identificacion);
					$foto=$foto."_".$codigorand;
					

					# Extension de la imagen #
					switch(mime_content_type($_FILES['alumno_foto']['tmp_name'])){
						case 'image/jpeg':
							$foto=$foto.".jpg";
						break;
						case 'image/png':
							$foto=$foto.".png";
						break;
					}
					$maxWidth = 800;
					$maxHeight = 600;

					chmod($img_dir,0777);
					$inputFile = ($_FILES['alumno_foto']['tmp_name']);
					$outputFile = $img_dir.$foto;

					# Moviendo imagen al directorio #
					//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
					if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
						
					}else{
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"No es posible subir la imagen al sistema en este momento",
							"icono"=>"error"
						];
						return json_encode($alerta);
					}
					
					# Eliminando imagen anterior #
					if(is_file($img_dir.$datos['alumno_imagen']) && $datos['alumno_imagen']!=$foto){
						chmod($img_dir.$datos['alumno_imagen'], 0777);
						unlink($img_dir.$datos['alumno_imagen']);
					}														
					$alumno_datos_reg[] = [
						"campo_nombre" => "alumno_imagen",
						"campo_marcador" => ":Foto",
						"campo_valor" => $foto
					];				
				}*/

				# Manejo de archivos con try/catch aparte #
				try {
					if(isset($_FILES['alumno_foto']) && $_FILES['alumno_foto']['error'] === UPLOAD_ERR_OK){
						$foto = $this->procesarImagen($_FILES['alumno_foto'], "../views/imagenes/fotos/alumno/", $alumno_identificacion);
						if($foto){
							$alumno_datos_reg[] = ["campo_nombre"=>"alumno_imagen","campo_marcador"=>":Foto","campo_valor"=>$foto];
						}
					}
				} catch (Exception $e) {
					return json_encode([
						"tipo"=>"simple",
						"titulo"=>"Error en la imagen",
						"texto"=>$e->getMessage(),
						"icono"=>"error"
					]);
				}

				if($_FILES['alumno_cedulaA']['name']!="" && $_FILES['alumno_cedulaA']['size']>0){
			
					# Creando directorio #
					if(!file_exists($dir_cedula)){
						if(!mkdir($dir_cedula,0777)){
							$alerta=[
								"tipo"=>"simple",
								"titulo"=>"Error",
								"texto"=>"No se creó el directorio",
								"icono"=>"error"
							];
							return json_encode($alerta);
						} 
					}

					# Verificando formato de imagenes #
					if(mime_content_type($_FILES['alumno_cedulaA']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['alumno_cedulaA']['tmp_name'])!="image/png"){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"La imagen que ha seleccionado es de un formato no permitido ",
							"icono"=>"error"
						];
						return json_encode($alerta);
					}

					# Verificando peso de imagen #
					if(($_FILES['alumno_cedulaA']['size']/1024)>4000){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"La imagen que ha seleccionado supera el peso permitido 4MB",
							"icono"=>"error"
						];
						return json_encode($alerta);
					}

					#nombre de la imagen cedula
					$CedulaA=str_ireplace(" ","_",$alumno_identificacion);
					$CedulaA=$CedulaA."_A".$codigorand=rand(0,100);					

					# Extension de la imagen #
					switch(mime_content_type($_FILES['alumno_cedulaA']['tmp_name'])){
						case 'image/jpeg':
							$CedulaA=$CedulaA.".jpg";
						break;
						case 'image/png':
							$CedulaA=$CedulaA.".png";
						break;
					}
					$maxWidth = 800;
					$maxHeight = 600;

					chmod($img_dir,0777);
					$inputFile = ($_FILES['alumno_cedulaA']['tmp_name']);
					$outputFile = $dir_cedula.$CedulaA;

					# Moviendo imagen al directorio #
					//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
					if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
						
					}else{
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"No es posible subir la imagen de la cedula al sistema en este momento",
							"icono"=>"error"
						];
						return json_encode($alerta);
					}
					
					# Eliminando imagen anterior #
					if(is_file($dir_cedula.$datos['alumno_cedulaA']) && $datos['alumno_cedulaA']!=$CedulaA){
						chmod($dir_cedula.$datos['alumno_cedulaA'], 0777);
						unlink($dir_cedula.$datos['alumno_cedulaA']);
					}				
					
					$alumno_datos_reg[] = [
						"campo_nombre" => "alumno_cedulaA",
						"campo_marcador" => ":CedulaA",
						"campo_valor" => $CedulaA
					];				
				}

				if($_FILES['alumno_cedulaR']['name']!="" && $_FILES['alumno_cedulaR']['size']>0){
			
					# Creando directorio #
					if(!file_exists($dir_cedula)){
						if(!mkdir($dir_cedula,0777)){
							$alerta=[
								"tipo"=>"simple",
								"titulo"=>"Error",
								"texto"=>"No se creó el directorio",
								"icono"=>"error"
							];
							return json_encode($alerta);
						} 
					}

					# Verificando formato de imagenes #
					if(mime_content_type($_FILES['alumno_cedulaR']['tmp_name'])!="image/jpeg" && mime_content_type($_FILES['alumno_cedulaR']['tmp_name'])!="image/png"){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"La imagen que ha seleccionado es de un formato no permitido ",
							"icono"=>"error"
						];
						return json_encode($alerta);
					}

					# Verificando peso de imagen #
					if(($_FILES['alumno_cedulaR']['size']/1024)>4000){
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"La imagen que ha seleccionado supera el peso permitido 4MB",
							"icono"=>"error"
						];
						return json_encode($alerta);
					}

					#nombre imagen cedula reverso
					$CedulaR=str_ireplace(" ","_",$alumno_identificacion);
					$CedulaR=$CedulaR."_R".$codigorand;				

					# Extension de la imagen #
					switch(mime_content_type($_FILES['alumno_cedulaR']['tmp_name'])){
						case 'image/jpeg':
							$CedulaR=$CedulaR.".jpg";
						break;
						case 'image/png':
							$CedulaR=$CedulaR.".png";
						break;
					}
					$maxWidth = 800;
					$maxHeight = 600;

					chmod($img_dir,0777);
					$inputFile = ($_FILES['alumno_cedulaR']['tmp_name']);
					$outputFile = $dir_cedula.$CedulaR;

					# Moviendo imagen al directorio #
					//if(!move_uploaded_file($_FILES['alumno_foto']['tmp_name'],$img_dir.$foto)){
					if ($this->resizeImageGD($inputFile, $maxWidth, $maxHeight, $outputFile)) {
						
					}else{
						$alerta=[
							"tipo"=>"simple",
							"titulo"=>"Error",
							"texto"=>"No es posible subir la imagen de la cedula al sistema en este momento",
							"icono"=>"error"
						];
						return json_encode($alerta);
					}
					
					# Eliminando imagen anterior #
					if(is_file($dir_cedula.$datos['alumno_cedulaR']) && $datos['alumno_cedulaR']!=$CedulaR){
						chmod($dir_cedula.$datos['alumno_cedulaR'], 0777);
						unlink($dir_cedula.$datos['alumno_cedulaR']);
					}				
					
					$alumno_datos_reg[] = [
						"campo_nombre" => "alumno_cedulaR",
						"campo_marcador" => ":CedulaR",
						"campo_valor" => $CedulaR
					];				
				}

				$condicion=[
					"condicion_campo"=>"alumno_id",
					"condicion_marcador"=>":Alumnoid",
					"condicion_valor"=>$alumnoid
				];

				if(!$this->actualizarDatos("sujeto_alumno",$alumno_datos_reg,$condicion)){
					throw new Exception("No fue posible actualizar los datos del alumno, por favor intente nuevamente.");
				}
		
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Alumno actualizado",
					"texto"=>"El alumno ".$alumno_identificacion." | ".$alumno_primernombre." ".$alumno_apellidopaterno." se actualizó correctamente",
					"icono"=>"success"
				];

				/*---------------Inicio de registro de Información de los tabs*/
				$infomedic_tiposangre 	= $this->limpiarCadena($_POST['infomedic_tiposangre'] ?? "");
				$infomedic_peso		  	= $this->limpiarCadena($_POST['infomedic_peso'] ?? "");
				$infomedic_talla 	  	= $this->limpiarCadena($_POST['infomedic_talla'] ?? "");
				$infomedic_enfermedad 	= $this->limpiarCadena($_POST['infomedic_enfermedad'] ?? "");
				$infomedic_medicamentos = $this->limpiarCadena($_POST['infomedic_medicamentos'] ?? "");
				$infomedic_alergia1 	= $this->limpiarCadena($_POST['infomedic_alergia1'] ?? "");
				$infomedic_alergia2 	= $this->limpiarCadena($_POST['infomedic_alergia2'] ?? "");
				$infomedic_cirugias 	= $this->limpiarCadena($_POST['infomedic_cirugias'] ?? "");
				$infomedic_observacion	= $this->limpiarCadena($_POST['infomedic_observacion'] ?? "");

				if(isset($_POST['infomedic_covid'])){ $infomedic_covid  = $_POST['infomedic_covid']; }else {$infomedic_covid="";}
				if(isset($_POST['infomedic_vacunas'])){ $infomedic_vacunas  = $_POST['infomedic_vacunas']; }else {$infomedic_vacunas="";}
                
                
            	if ($infomedic_peso ==""){$infomedic_peso = 0;}
				if ($infomedic_talla ==""){$infomedic_talla = 0;}
                
				$infomedic=$this->ejecutarConsulta("SELECT * FROM alumno_infomedic WHERE infomedic_alumnoid = :alumno", [':alumno' => (int)$alumnoid]);
				if($infomedic->rowCount()>0){
					$infomedic_reg=[
						["campo_nombre"=>"infomedic_alumnoid","campo_marcador"=>":Alumnoid","campo_valor"=>$alumnoid],
						["campo_nombre"=>"infomedic_fecha","campo_marcador"=>":Fechacreacion","campo_valor"=>date("Y-m-d H:i:s")],
						["campo_nombre"=>"infomedic_tiposangre","campo_marcador"=>":Tiposangre","campo_valor"=>$infomedic_tiposangre],
						["campo_nombre"=>"infomedic_peso","campo_marcador"=>":Peso","campo_valor"=>$infomedic_peso],
						["campo_nombre"=>"infomedic_talla","campo_marcador"=>":Talla","campo_valor"=>$infomedic_talla],
						["campo_nombre"=>"infomedic_enfermedad","campo_marcador"=>":Enfermedad","campo_valor"=>$infomedic_enfermedad],
						["campo_nombre"=>"infomedic_medicamentos","campo_marcador"=>":Medicamentos","campo_valor"=>$infomedic_medicamentos],
						["campo_nombre"=>"infomedic_alergia1","campo_marcador"=>":AlergiaMedicamentos","campo_valor"=>$infomedic_alergia1],
						["campo_nombre"=>"infomedic_alergia2","campo_marcador"=>":AlergiaObjetos","campo_valor"=>$infomedic_alergia2],
						["campo_nombre"=>"infomedic_cirugias","campo_marcador"=>":Cirugias","campo_valor"=>$infomedic_cirugias],
						["campo_nombre"=>"infomedic_observacion","campo_marcador"=>":Observacion","campo_valor"=>$infomedic_observacion],
						["campo_nombre"=>"infomedic_covid","campo_marcador"=>":VacunasCovid","campo_valor"=>$infomedic_covid],
						["campo_nombre"=>"infomedic_vacunas","campo_marcador"=>":Vacunas","campo_valor"=>$infomedic_vacunas]
					];
					
					$condicion=[
						"condicion_campo"=>"infomedic_alumnoid",
						"condicion_marcador"=>":Alumnoid",
						"condicion_valor"=>$alumnoid
					];

					$this->actualizarDatos("alumno_infomedic",$infomedic_reg,$condicion);

				}else{
					if($infomedic_tiposangre!="" || $infomedic_peso>0 || $infomedic_talla>0 || $infomedic_enfermedad!=""||
						$infomedic_medicamentos!="" || $infomedic_alergia1!="" || $infomedic_alergia2!="" || $infomedic_cirugias!="" ||
						$infomedic_observacion!=""){
							$infomedic_reg=[
								["campo_nombre"=>"infomedic_alumnoid","campo_marcador"=>":Alumnoid","campo_valor"=>$alumnoid],
								["campo_nombre"=>"infomedic_fecha","campo_marcador"=>":Fechacreacion","campo_valor"=>date("Y-m-d H:i:s")],
								["campo_nombre"=>"infomedic_tiposangre","campo_marcador"=>":Tiposangre","campo_valor"=>$infomedic_tiposangre],
								["campo_nombre"=>"infomedic_peso","campo_marcador"=>":Peso","campo_valor"=>$infomedic_peso],
								["campo_nombre"=>"infomedic_talla","campo_marcador"=>":Talla","campo_valor"=>$infomedic_talla],
								["campo_nombre"=>"infomedic_enfermedad","campo_marcador"=>":Enfermedad","campo_valor"=>$infomedic_enfermedad],
								["campo_nombre"=>"infomedic_medicamentos","campo_marcador"=>":Medicamentos","campo_valor"=>$infomedic_medicamentos],
								["campo_nombre"=>"infomedic_alergia1","campo_marcador"=>":AlergiaMedicamentos","campo_valor"=>$infomedic_alergia1],
								["campo_nombre"=>"infomedic_alergia2","campo_marcador"=>":AlergiaObjetos","campo_valor"=>$infomedic_alergia2],
								["campo_nombre"=>"infomedic_cirugias","campo_marcador"=>":Cirugias","campo_valor"=>$infomedic_cirugias],
								["campo_nombre"=>"infomedic_observacion","campo_marcador"=>":Observacion","campo_valor"=>$infomedic_observacion],
								["campo_nombre"=>"infomedic_covid","campo_marcador"=>":VacunasCovid","campo_valor"=>$infomedic_covid],
								["campo_nombre"=>"infomedic_vacunas","campo_marcador"=>":Vacunas","campo_valor"=>$infomedic_vacunas]
							];

						$this->guardarDatos("alumno_infomedic",$infomedic_reg);
					}
				}
				/*---------------Fin de registro del tab Información Médica del alumno*/
				/*---------------Registro del tab Contacto Emergencia del alumno------------*/
				$cemer_nombre 		= $this->limpiarCadena($_POST['cemer_nombre']);
				$cemer_celular 		= $this->limpiarCadena($_POST['cemer_celular']);
				$cemer_parentesco	= $this->limpiarCadena($_POST['cemer_parentesco']);				

				$cmer=$this->ejecutarConsulta("SELECT * FROM alumno_cemergencia WHERE cemer_alumnoid = :alumno", [':alumno' => (int)$alumnoid]);
				if($cmer->rowCount()>0){

					$cemergencia_reg=[
						["campo_nombre"=>"cemer_alumnoid","campo_marcador"=>":Alumnoid","campo_valor"=>$alumnoid],
						["campo_nombre"=>"cemer_nombre","campo_marcador"=>":NombreContactoEmer","campo_valor"=>$cemer_nombre],
						["campo_nombre"=>"cemer_celular","campo_marcador"=>":CelularContactoEmer","campo_valor"=>$cemer_celular],
						["campo_nombre"=>"cemer_parentesco","campo_marcador"=>":ParentescoContactoEmer","campo_valor"=>$cemer_parentesco]
					];
	
					$condicion=[
						"condicion_campo"=>"cemer_alumnoid",
						"condicion_marcador"=>":Alumnoid",
						"condicion_valor"=>$alumnoid
					];

					$this->actualizarDatos("alumno_cemergencia",$cemergencia_reg,$condicion);

				}else{
					if($cemer_nombre!="" || $cemer_celular!=""){

						$cemergencia_reg=[
							["campo_nombre"=>"cemer_alumnoid","campo_marcador"=>":Alumnoid","campo_valor"=>$alumnoid],						
							["campo_nombre"=>"cemer_nombre","campo_marcador"=>":NombreContactoEmer","campo_valor"=>$cemer_nombre],
							["campo_nombre"=>"cemer_celular","campo_marcador"=>":CelularContactoEmer","campo_valor"=>$cemer_celular],
							["campo_nombre"=>"cemer_parentesco","campo_marcador"=>":ParentescoContactoEmer","campo_valor"=>$cemer_parentesco]
						];
		
						$condicion=[
							"condicion_campo"=>"cemer_alumnoid",
							"condicion_marcador"=>":Alumnoid",
							"condicion_valor"=>$alumnoid
						];
						$this->guardarDatos("alumno_cemergencia",$cemergencia_reg);
					}
				}
				/*---------------Fin de registro del tab Contacto Emergencia del alumno------*/

				/*---------------Actulizar horario de entrenamiento---------------------*/
				$horario_id = $this->limpiarCadena($_POST['horarioid'] ?? '');
				if($horario_id != null){
					/* Queda UN horario: el elegido. Antes se hacía UPDATE de todas
					   las filas del alumno y, si tenía dos horarios, quedaban dos
					   filas iguales. Se borra y se inserta. */
					$this->eliminarRegistro("asistencia_asignahorario","asignahorario_alumnoid",$alumnoid);
					$asignacion_horario_reg = [
						["campo_nombre" => "asignahorario_horarioid","campo_marcador" => ":Horarioid","campo_valor" => $horario_id],
						["campo_nombre" => "asignahorario_alumnoid","campo_marcador" => ":Alumnoid","campo_valor" => $alumnoid]
					];
					$this->guardarDatos("asistencia_asignahorario",$asignacion_horario_reg);
				}else{
					/* Sin horario elegido se quita el que tuviera. Cero filas
					   borradas no es un fallo: el alumno simplemente no tenía
					   horario. Antes eso se anunciaba como error aunque los datos
					   ya estaban actualizados, y pisaba el aviso de éxito. */
					$this->eliminarRegistro("asistencia_asignahorario","asignahorario_alumnoid",$alumnoid);
				}
				return json_encode($alerta);
				/*---------------Fin de actualizacion de horariop de entrenamiento------*/

			}catch (Exception $e) {
				return json_encode([
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>$e->getMessage(),
					"icono"=>"error"
				]);
			}
		}

		
		/**
		 * Procesa y valida una imagen subida
		 */
		private function procesarImagen($file, $directorio, $identificacion){
			if($file['size']/1024 > 4000){
				throw new Exception("La imagen supera el peso permitido (4MB).");
			}
			$mime = mime_content_type($file['tmp_name']);
			if(!in_array($mime, ["image/jpeg","image/png"])){
				throw new Exception("El formato de la imagen no está permitido.");
			}

			if(!file_exists($directorio)){
				if(!mkdir($directorio,0777,true)){
					throw new Exception("No se pudo crear el directorio de imágenes.");
				}
			}

			$ext = ($mime=="image/jpeg")?".jpg":".png";
			$nombre = str_ireplace(" ","_",$identificacion)."_".rand(0,100).$ext;
			$rutaFinal = $directorio.$nombre;

			if(!$this->resizeImageGD($file['tmp_name'],800,600,$rutaFinal)){
				throw new Exception("No fue posible guardar la imagen.");
			}
			return $nombre;
		}


		
		# Consultar datos del representante para la vista vincular alumno
		public function datosRepresentante($alumnoid){			
			$consulta_repre = "SELECT repre_identificacion IDENTIFICACION, 
									concat(repre_primernombre, ' ', repre_segundonombre, ' ', repre_apellidopaterno, ' ', repre_apellidomaterno) AS REPRESENTANTE,
									catalogo_descripcion PARENTESCO, repre_direccion, repre_correo, repre_celular, repre_factura
									FROM alumno_representante
									LEFT JOIN sujeto_alumno on alumno_repreid = repre_id
									LEFT JOIN general_tabla_catalogo ON repre_parentesco = catalogo_valor
									LEFT JOIN general_tabla ON tabla_id = catalogo_tablaid        
									WHERE alumno_id = :alumno";			
			$datos = $this->ejecutarConsulta($consulta_repre, [':alumno' => (int)$alumnoid]);		
			return $datos;
		}

		/* ==================================== Roles ==================================== */

		public function listarOptionSede($rolid = null, $usuario = null ){
			$option="";

			/* Las sedes de la sesión: sedes_de_la_sesion() (ds_core/inc/seguridad.php).
			   Alta: sólo las activas (sql_sede_activa, migración 078). */
			$consulta_datos = "SELECT sede_id, sede_nombre FROM general_sede WHERE " . sql_sedes_de_la_sesion('sede_id') . " AND " . sql_sede_activa('sede_id') . " ORDER BY sede_nombre";
			$parametros = [];				
					
			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$option.='<option value='.$rows['sede_id'].'>'.$rows['sede_nombre'].'</option>';					
			}
			return $option;
		}

		public function listarSedebusqueda($sedeid, $rolid = null, $usuario = null){
			$option="";

			/* Las sedes de la sesión: sedes_de_la_sesion() (ds_core/inc/seguridad.php).
			   Búsqueda: sólo las activas (2026-11-27); la ya elegida se conserva. */
			$consulta_datos = "SELECT sede_id, sede_nombre FROM general_sede WHERE " . sql_sedes_de_la_sesion('sede_id') . " AND " . sql_sede_activa('sede_id', (int)$sedeid) . " ORDER BY sede_nombre";
			$parametros = [];						
					
			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($sedeid == $rows['sede_id']){
					$option.='<option value='.$rows['sede_id'].' selected>'.$rows['sede_nombre'].'</option>';
				}else{
					$option.='<option value='.$rows['sede_id'].'>'.$rows['sede_nombre'].'</option>';	
				}
					
			}
			return $option;
		}
		
		public function listarCatalogoTipoDocumento(){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'tipo_documento'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
			}
			return $option;
		}
		
		public function listarCatalogoNacionalidad(){
			$option="";

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'nacionalidad'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
			}
			return $option;
		}
		
		/* En el formulario de alta no hay parentesco previo que preseleccionar,
		   asi que el argumento es opcional: pedirlo obligaba a la vista a pasar
		   una variable inexistente y eso imprimia un aviso de PHP. */
		public function listarCatalogoParentesco($cemer_parentesco = ''){
			$option ='<option value=0> Seleccione una opción</option>';

			$consulta_datos="SELECT C.catalogo_valor, C.catalogo_descripcion 
								FROM general_tabla_catalogo C
								INNER JOIN general_tabla T on T.tabla_id = C.catalogo_tablaid
								WHERE T.tabla_nombre = 'parentesco'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($cemer_parentesco == $rows['catalogo_valor']){
					$option.='<option value='.$rows['catalogo_valor'].' selected="selected">'.$rows['catalogo_descripcion'].'</option>';
				}else{
					$option.='<option value='.$rows['catalogo_valor'].'>'.$rows['catalogo_descripcion'].'</option>';	
				}	

			}
			return $option;
		}		

		public function informacionSede($sedeid){		
			$consulta_datos="SELECT * FROM general_sede WHERE sede_id = :sede";
			$datos = $this->ejecutarConsulta($consulta_datos, [':sede' => (int)$sedeid]);		
			return $datos;
		}

		//horarios

		public function generarHorarioProfile($horario_id = null){			
			$tabla="";
			$consulta_datos = "SELECT  
								'Horario' AS Categoria,
								MAX(CASE WHEN detalle_dia = 1 THEN CONCAT(hora_inicio, ' - ', hora_fin) END) AS Lunes,
								MAX(CASE WHEN detalle_dia = 2 THEN CONCAT(hora_inicio, ' - ', hora_fin) END) AS Martes,
								MAX(CASE WHEN detalle_dia = 3 THEN CONCAT(hora_inicio, ' - ', hora_fin) END) AS Miercoles,
								MAX(CASE WHEN detalle_dia = 4 THEN CONCAT(hora_inicio, ' - ', hora_fin) END) AS Jueves,
								MAX(CASE WHEN detalle_dia = 5 THEN CONCAT(hora_inicio, ' - ', hora_fin) END) AS Viernes							
							FROM asistencia_horario 
							INNER JOIN asistencia_horario_detalle ON detalle_horarioid = horario_id 
							LEFT JOIN asistencia_hora ON hora_id = detalle_horaid 
							WHERE horario_id = :h1
							GROUP BY Categoria
							
							UNION ALL
							
							SELECT 
								'Cancha' AS Categoria,
								MAX(CASE WHEN detalle_dia = 1 THEN lugar_nombre END) AS Lunes,
								MAX(CASE WHEN detalle_dia = 2 THEN lugar_nombre END) AS Martes,
								MAX(CASE WHEN detalle_dia = 3 THEN lugar_nombre END) AS Miercoles,
								MAX(CASE WHEN detalle_dia = 4 THEN lugar_nombre END) AS Jueves,
								MAX(CASE WHEN detalle_dia = 5 THEN lugar_nombre END) AS Viernes								
							FROM asistencia_horario 
							INNER JOIN asistencia_horario_detalle ON detalle_horarioid = horario_id 
							LEFT JOIN asistencia_lugar ON lugar_id = detalle_lugarid
							WHERE horario_id = :h2
							GROUP BY Categoria
							
							UNION ALL
							
							SELECT 
								'Profesor' AS Categoria,
								MAX(CASE WHEN detalle_dia = 1 THEN empleado_nombre END) AS Lunes,
								MAX(CASE WHEN detalle_dia = 2 THEN empleado_nombre END) AS Martes,
								MAX(CASE WHEN detalle_dia = 3 THEN empleado_nombre END) AS Miercoles,
								MAX(CASE WHEN detalle_dia = 4 THEN empleado_nombre END) AS Jueves,
								MAX(CASE WHEN detalle_dia = 5 THEN empleado_nombre END) AS Viernes
							FROM asistencia_horario 
							INNER JOIN asistencia_horario_detalle ON detalle_horarioid = horario_id 
							LEFT JOIN sujeto_empleado ON empleado_id = detalle_profesorid	 
							WHERE horario_id = :h3
							GROUP BY Categoria";
		
							
			$datos = $this->ejecutarConsulta($consulta_datos, [':h1' => (int)$horario_id, ':h2' => (int)$horario_id, ':h3' => (int)$horario_id]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.="	<tr style='font-size: 14px'>					
								<th>".$rows['Categoria']."</th>	
								<td>".$rows['Lunes']."</td>
								<td>".$rows['Martes']."</td>
								<td>".$rows['Miercoles']."</td>
								<td>".$rows['Jueves']."</td>
								<td>".$rows['Viernes']."</td>																														
							</tr>";
			}
			return $tabla;
		}

		public function HorarioID($alumnoid){
			$consulta_datos="SELECT asignahorario_horarioid FROM asistencia_asignahorario WHERE asignahorario_alumnoid = :alumno";
			$datos = $this->ejecutarConsulta($consulta_datos, [':alumno' => (int)$alumnoid]);
			return $datos;
		}

		/*----------  Todo lo que muestra la ficha del alumno, ya en texto  ----------*/
		/*
		| La ficha es de solo lectura. Antes pintaba campos deshabilitados y,
		| para mostrar el nombre de la sede o de la nacionalidad, generaba los
		| <option> de cada catálogo. Aquí sale el texto.
		|
		| Cada catálogo se filtra por SU tabla: el parentesco del representante
		| se buscaba sólo por valor y, si el valor existía en otro catálogo,
		| salían dos filas y la pestaña quedaba con variables sin definir.
		|
		| Un alumno puede tener más de un horario asignado (los hay): se
		| devuelven todos. Antes, con dos, el horario salía vacío.
		|
		| Devuelve null si el alumno no existe.
		*/
		public function fichaAlumno($alumnoid){
			$p = [':alumno' => (int)$alumnoid];
			$catalogo = static fn(string $alias, string $tabla, string $valor): string =>
				"LEFT JOIN (general_tabla_catalogo $alias
				            INNER JOIN general_tabla {$alias}T ON {$alias}T.tabla_id = $alias.catalogo_tablaid
				                                            AND {$alias}T.tabla_nombre = '$tabla')
				       ON $alias.catalogo_valor = $valor";

			$alumno = $this->ejecutarConsulta("SELECT A.*, S.sede_nombre,
						TI.catalogo_descripcion AS tipo_identificacion,
						NA.catalogo_descripcion AS nacionalidad,
						PJ.catalogo_descripcion AS posicion_juego
					FROM sujeto_alumno A
					LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
					" . $catalogo('TI', 'tipo_documento', 'A.alumno_tipoidentificacion') . "
					" . $catalogo('NA', 'nacionalidad', 'A.alumno_nacionalidadid') . "
					" . $catalogo('PJ', 'posicion_juego', 'A.alumno_posicionid') . "
					WHERE A.alumno_id = :alumno", $p)->fetch();
			if (!$alumno) {
				return null;
			}
			/* Beneficio, últimos pagos, última asistencia y lo que debe, con
			   la misma cuenta que Pagos y Cobranza (mainModel). Antes la ficha
			   sólo contaba los pagos con saldo: quien debía pensiones sin
			   generar salía «Pagos al día». */
			$alumno += $this->resumenCuentaAlumnos([(int)$alumnoid])[(int)$alumnoid] ?? [];

			$representante = $this->ejecutarConsulta("SELECT R.*, PA.catalogo_descripcion AS parentesco
					FROM sujeto_alumno A
					INNER JOIN alumno_representante R ON R.repre_id = A.alumno_repreid
					" . $catalogo('PA', 'parentesco', 'R.repre_parentesco') . "
					WHERE A.alumno_id = :alumno LIMIT 1", $p)->fetch();

			$emergencia = $this->ejecutarConsulta("SELECT C.*, PA.catalogo_descripcion AS parentesco
					FROM alumno_cemergencia C
					" . $catalogo('PA', 'parentesco', 'C.cemer_parentesco') . "
					WHERE C.cemer_alumnoid = :alumno ORDER BY C.cemer_id DESC LIMIT 1", $p)->fetch();

			$medica = $this->ejecutarConsulta("SELECT * FROM alumno_infomedic
					WHERE infomedic_alumnoid = :alumno ORDER BY infomedic_id DESC LIMIT 1", $p)->fetch();

			$ids = $this->ejecutarConsulta("SELECT DISTINCT asignahorario_horarioid FROM asistencia_asignahorario
					WHERE asignahorario_alumnoid = :alumno ORDER BY 1", $p)->fetchAll(\PDO::FETCH_COLUMN);
			$horarios = array_values(array_filter(array_map(fn($id) => $this->horarioDias($id), $ids)));

			/*
			| Vínculos (096): el registro del que salió éste (origen) y los que
			| salieron de él en una sede con código (PA000001…). Son la MISMA
			| persona: se muestran aparte y no salen entre los hermanos.
			*/
			$vinculos = [];
			$excluir = [];
			if ($this->hayVinculoAlumnos()) {
				$raiz = (int)($alumno['alumno_origenid'] ?: $alumnoid);
				$vinculos = $this->ejecutarConsulta("SELECT A.alumno_id, A.alumno_identificacion, A.alumno_estado, A.alumno_origenid, S.sede_nombre
						FROM sujeto_alumno A LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
						WHERE (A.alumno_id = :r1 OR A.alumno_origenid = :r2) AND A.alumno_id <> :yo AND A.alumno_estado IN ('A','I')
						ORDER BY A.alumno_origenid IS NULL DESC, A.alumno_id",
						[':r1' => $raiz, ':r2' => $raiz, ':yo' => (int)$alumnoid])->fetchAll(\PDO::FETCH_ASSOC);
				$excluir = array_map('intval', array_column($vinculos, 'alumno_id'));
			}

			/* Hermanos: los demás alumnos (activos e inactivos) del mismo
			   representante, con su beneficio vigente (descuento_valor es la
			   pensión que PAGA). Los activos primero. */
			$hermanos = (int)($alumno['alumno_repreid'] ?? 0) > 0
				? $this->ejecutarConsulta("SELECT A.alumno_id, A.alumno_primernombre, A.alumno_segundonombre,
							A.alumno_apellidopaterno, A.alumno_apellidomaterno, A.alumno_fechanacimiento,
							A.alumno_estado, A.alumno_imagen, S.sede_nombre, S.sede_pension,
							D.descuento_rubroid, D.descuento_valor, DC.catalogo_descripcion AS descuento_nombre
						FROM sujeto_alumno A
						LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
						LEFT JOIN alumno_pago_descuento D ON D.descuento_alumnoid = A.alumno_id AND D.descuento_estado = 'S'
						LEFT JOIN general_tabla_catalogo DC ON DC.catalogo_valor = D.descuento_rubroid AND DC.catalogo_tablaid = 7
						WHERE A.alumno_repreid = :repre AND A.alumno_id <> :alumno AND A.alumno_estado IN ('A','I')
						" . ($excluir ? "AND A.alumno_id NOT IN (" . implode(',', $excluir) . ")" : "") . "
						ORDER BY A.alumno_estado = 'A' DESC, A.alumno_fechanacimiento",
						[':repre' => (int)$alumno['alumno_repreid'], ':alumno' => (int)$alumnoid])->fetchAll(\PDO::FETCH_ASSOC)
				: [];
			/* Lo que debe cada hermano: la misma cuenta que Pagos y Cobranza. */
			$cuentas = $this->resumenCuentaAlumnos(array_column($hermanos, 'alumno_id'));
			foreach ($hermanos as &$hn) {
				$c = $cuentas[(int)$hn['alumno_id']] ?? [];
				$hn['deuda']       = (float)($c['deuda'] ?? 0);
				$hn['meses_deuda'] = (int)($c['meses_deuda'] ?? 0);
				$hn['saldo']       = (float)($c['saldo'] ?? 0);
			}
			unset($hn);

			/*
			| Torneos en los que participó: los de un equipo en el que está
			| inscrito (torneo_jugador → torneo_equipo) y los que pagó (rubro
			| RPC «Campeonato», pago_campeonatoid = torneo). Hacen falta los
			| dos: en champions 705 pagos son de alumnos que no figuran en el
			| equipo de ese torneo, y hay inscritos sin pago.
			|
			| De la inscripción: lo pagado (completos y abonos, sin eliminados),
			| el saldo pendiente y la fecha del último pago; el valor de la
			| inscripción es el del equipo (0: sin costo fijado).
			*/
			$torneos = $this->ejecutarConsulta("SELECT T.torneo_id, T.torneo_nombre, T.torneo_ciudad, T.torneo_lugar,
						T.torneo_fechainicio, T.torneo_fechafin,
						MAX(E.equipo_nombre) AS equipo, MAX(E.equipo_categoria) AS categoria,
						MAX(E.equipo_valorinscripcion) AS valor_inscripcion,
						MAX(PO.catalogo_descripcion) AS posicion, MAX(TP.catalogo_descripcion) AS tipo,
						MAX(J.jugador_alumnoid IS NOT NULL) AS inscrito,
						MAX(PG.pagado) AS pagado, MAX(PG.saldo) AS saldo, MAX(PG.pagos) AS pagos, MAX(PG.ultimo) AS ultimo_pago
					FROM torneo_torneo T
					LEFT JOIN (torneo_jugador J INNER JOIN torneo_equipo E ON E.equipo_id = J.jugador_equipoid)
					       ON E.equipo_torneoid = T.torneo_id AND J.jugador_alumnoid = :a1
					" . $catalogo('PO', 'posicion_juego', 'J.jugador_posicioncod') . "
					" . $catalogo('TP', 'tipo_participacion', 'J.jugador_tipocod') . "
					LEFT JOIN (SELECT pago_campeonatoid, SUM(pago_valor) AS pagado,
					                  SUM(IF(pago_estado = 'P', pago_saldo, 0)) AS saldo, COUNT(*) AS pagos, MAX(pago_fecha) AS ultimo
					             FROM alumno_pago
					            WHERE pago_alumnoid = :a2 AND pago_rubroid = 'RPC' AND pago_estado IN ('C','P')
					            GROUP BY pago_campeonatoid) PG ON PG.pago_campeonatoid = T.torneo_id
					WHERE J.jugador_alumnoid IS NOT NULL OR PG.pagos IS NOT NULL
					GROUP BY T.torneo_id, T.torneo_nombre, T.torneo_ciudad, T.torneo_lugar, T.torneo_fechainicio, T.torneo_fechafin
					ORDER BY T.torneo_fechainicio DESC, T.torneo_id DESC",
					[':a1' => (int)$alumnoid, ':a2' => (int)$alumnoid])->fetchAll(\PDO::FETCH_ASSOC);

			return [
				'alumno'        => $alumno,
				'representante' => $representante ?: null,
				'emergencia'    => $emergencia ?: null,
				'medica'        => $medica ?: null,
				'horarios'      => $horarios,
				'hermanos'      => $hermanos,
				'torneos'       => $torneos,
				'vinculos'      => $vinculos,
			];
		}

		/*======================================================================
		  INSCRIPCIÓN EN UNA SEDE CON CÓDIGO (migración 096, 2026-11-25)
		  ======================================================================
		| La sede PERSONALIZADO (entrenamiento individual) recibe alumnos de
		| otras sedes y les cobra su propia pensión. La pensión, la deuda y la
		| cobranza van por REGISTRO de alumno con la pensión de su sede, y la
		| cédula no se repite: se crea un registro propio en esa sede, con el
		| código interno de la sede (PA000001…) como identificación, el mismo
		| representante y alumno_origenid apuntando al alumno original.
		|
		| Qué sede: la que tiene general_sede.sede_codigoalumno (el prefijo).
		| Sin la migración 096 o sin esa sede, nada de esto aparece.
		|
		| El registro nuevo es un alumno más de esa sede: pagos, cobranza,
		| asistencia, carnets e Insights lo tratan igual que a cualquiera.
		*/

		/* hayVinculoAlumnos(): ¿está la 096? (mainModel) */

		/** La sede activa con código interno: [sede_id, sede_nombre, prefijo, pension, inscripcion] o null. */
		private function sedeConCodigo(): ?array{
			if (!$this->hayVinculoAlumnos()) { return null; }
			try {
				$s = $this->ejecutarConsulta("SELECT sede_id, sede_nombre, sede_codigoalumno AS prefijo, sede_pension AS pension, sede_inscripcion AS inscripcion
						FROM general_sede
						WHERE sede_codigoalumno IS NOT NULL AND sede_codigoalumno <> '' AND " . sql_sede_activa('sede_id', 0) . "
						ORDER BY sede_id LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
			} catch (\Throwable $e) { return null; }
			if (!$s || !preg_match('/^[A-Z]{1,3}$/', strtoupper((string)$s['prefijo']))) { return null; }
			$s['prefijo'] = strtoupper($s['prefijo']);
			return $s;
		}

		/** El siguiente código libre del prefijo: PA000001, PA000002… */
		private function proximoCodigoAlumno(string $prefijo, ?\PDO $pdo = null): string{
			$sql = "SELECT COALESCE(MAX(CAST(SUBSTRING(alumno_identificacion, " . (strlen($prefijo) + 1) . ") AS UNSIGNED)), 0)
					  FROM sujeto_alumno WHERE alumno_identificacion REGEXP :patron";
			$p = [':patron' => '^' . $prefijo . '[0-9]{6}$'];
			if ($pdo) { $q = $pdo->prepare($sql); $q->execute($p); $max = (int)$q->fetchColumn(); }
			else { $max = (int)$this->ejecutarConsulta($sql, $p)->fetchColumn(); }
			return $prefijo . str_pad((string)($max + 1), 6, '0', STR_PAD_LEFT);
		}

		/**
		 * Lo que la ficha necesita para ofrecer «Inscribir en …»: la sede, su
		 * pensión e inscripción, sus horarios activos, el próximo código y, si
		 * ya está inscrito, ese registro. null si no corresponde: no hay sede
		 * con código, el alumno ya es de ella, el alumno ya es un registro
		 * derivado, o el usuario no trabaja con esa sede.
		 */
		public function sedeParaInscribir(array $al): ?array{
			$sede = $this->sedeConCodigo();
			if (!$sede || (int)$al['alumno_sedeid'] === (int)$sede['sede_id'] || !empty($al['alumno_origenid'])) { return null; }
			$permitidas = sedes_de_la_sesion();
			if ($permitidas !== null && !in_array((int)$sede['sede_id'], $permitidas, true)) { return null; }

			$existente = $this->ejecutarConsulta("SELECT alumno_id, alumno_identificacion, alumno_estado FROM sujeto_alumno
					WHERE alumno_origenid = :a AND alumno_sedeid = :s AND alumno_estado IN ('A','I')
					ORDER BY alumno_estado = 'A' DESC, alumno_id DESC LIMIT 1",
					[':a' => (int)$al['alumno_id'], ':s' => (int)$sede['sede_id']])->fetch(\PDO::FETCH_ASSOC) ?: null;

			$horarios = [];
			foreach ($this->ejecutarConsulta("SELECT H.horario_id, H.horario_nombre, H.horario_detalle,
						MIN(HO.hora_inicio) AS inicio, MAX(HO.hora_fin) AS fin
					FROM asistencia_horario H
					LEFT JOIN asistencia_horario_detalle D ON D.detalle_horarioid = H.horario_id
					LEFT JOIN asistencia_hora HO ON HO.hora_id = D.detalle_horaid
					WHERE H.horario_estado = 'A' AND H.horario_sedeid = :s
					GROUP BY H.horario_id, H.horario_nombre, H.horario_detalle
					ORDER BY H.horario_nombre", [':s' => (int)$sede['sede_id']])->fetchAll(\PDO::FETCH_ASSOC) as $h) {
				$texto = trim(html_entity_decode((string)($h['horario_detalle'] ?: $h['horario_nombre']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
				if ($h['inicio']) { $texto .= ' · ' . substr($h['inicio'], 0, 5) . '–' . substr((string)$h['fin'], 0, 5); }
				$horarios[(int)$h['horario_id']] = $texto;
			}

			return $sede + [
				'codigo'    => $existente ? $existente['alumno_identificacion'] : $this->proximoCodigoAlumno($sede['prefijo']),
				'existente' => $existente,
				'horarios'  => $horarios,
			];
		}

		/*
		| POST modulo_alumno=registrarSedeCodigo: alumno_id, alumno_fechaingreso y
		| horarioid (opcional). Copia del original los datos personales, la foto
		| y las imágenes de la cédula (como archivos nuevos: si el original las
		| cambia, éste no se queda sin ellas), la última información médica y el
		| último contacto de emergencia. El código sale bajo un bloqueo con
		| nombre (GET_LOCK): dos inscripciones a la vez no se llevan el mismo.
		*/
		public function inscribirEnSedeCodigo(){
			$error = static fn(string $t) => json_encode(["tipo" => "simple", "titulo" => "No se pudo inscribir", "texto" => $t, "icono" => "error"]);

			if (!puede('crear', 'alumnoList')) {
				return $error('Su rol no puede inscribir alumnos.');
			}
			$id = (int)($_POST['alumno_id'] ?? 0);
			$al = $id > 0 ? $this->ejecutarConsulta("SELECT * FROM sujeto_alumno WHERE alumno_id = :a", [':a' => $id])->fetch(\PDO::FETCH_ASSOC) : false;
			if (!$al) {
				return $error('El alumno no existe.');
			}
			if ($al['alumno_estado'] !== 'A') {
				return $error('Sólo se puede inscribir a un alumno activo.');
			}
			$destino = $this->sedeParaInscribir($al);
			if (!$destino) {
				return $error('Este alumno no se puede inscribir en otra sede.');
			}
			if ($destino['existente']) {
				return $error('Ya está inscrito en ' . $destino['sede_nombre'] . ' con el código ' . $destino['existente']['alumno_identificacion'] . '.');
			}
			$fecha = (string)($_POST['alumno_fechaingreso'] ?? '');
			$f = \DateTime::createFromFormat('!Y-m-d', $fecha);
			if (!$f || $f->format('Y-m-d') !== $fecha) {
				return $error('Elija la fecha de ingreso.');
			}
			$horario = (int)($_POST['horarioid'] ?? 0);
			if ($horario > 0 && !isset($destino['horarios'][$horario])) {
				return $error('El horario elegido no es de ' . $destino['sede_nombre'] . '.');
			}

			/* Foto e imágenes de la cédula: copias con el nombre del código. */
			$dirFoto   = __DIR__ . '/../views/imagenes/fotos/alumno/';
			$dirCedula = __DIR__ . '/../views/imagenes/cedulas/';
			$copiados = [];
			$copiar = static function (?string $archivo, string $dir, string $codigo, string $sufijo) use (&$copiados): string {
				$archivo = basename((string)$archivo);
				if ($archivo === '' || !is_file($dir . $archivo)) { return ''; }
				$nuevo = $codigo . $sufijo . '_' . random_int(10, 99) . '.' . strtolower(pathinfo($archivo, PATHINFO_EXTENSION) ?: 'jpg');
				if (!@copy($dir . $archivo, $dir . $nuevo)) { return ''; }
				$copiados[] = $dir . $nuevo;
				return $nuevo;
			};

			$pdo = $this->conectar();
			$bloqueo = 'ds_codigo_alumno_' . $destino['prefijo'];
			try {
				if ((int)$pdo->query("SELECT GET_LOCK(" . $pdo->quote($bloqueo) . ", 10)")->fetchColumn() !== 1) {
					throw new \RuntimeException('Otra inscripción está en curso. Inténtelo de nuevo en un momento.');
				}
				$codigo = $this->proximoCodigoAlumno($destino['prefijo'], $pdo);
				$q = $pdo->prepare("SELECT COUNT(*) FROM sujeto_alumno WHERE alumno_identificacion = :c");
				$q->execute([':c' => $codigo]);
				if ((int)$q->fetchColumn() > 0) {
					throw new \RuntimeException('El código ' . $codigo . ' ya está en uso. Inténtelo de nuevo.');
				}
				$foto    = $copiar($al['alumno_imagen'], $dirFoto, $codigo, '');
				$cedulaA = $copiar($al['alumno_cedulaA'], $dirCedula, $codigo, '_A');
				$cedulaR = $copiar($al['alumno_cedulaR'], $dirCedula, $codigo, '_R');

				$pdo->beginTransaction();
				$pdo->prepare("INSERT INTO sujeto_alumno
						(alumno_sedeid, alumno_posicionid, alumno_nacionalidadid, alumno_repreid, alumno_origenid,
						 alumno_tipoidentificacion, alumno_identificacion, alumno_primernombre, alumno_segundonombre,
						 alumno_apellidopaterno, alumno_apellidomaterno, alumno_nombrecorto, alumno_direccion,
						 alumno_fechanacimiento, alumno_fechaingreso, alumno_genero, alumno_hermanos, alumno_estado,
						 alumno_imagen, alumno_numcamiseta, alumno_cedulaA, alumno_cedulaR)
					VALUES (:sede, :pos, :nac, :repre, :origen, 'COD', :codigo, :n1, :n2, :a1, :a2, :corto, :dir,
					        :nacim, :ingreso, :genero, :hermanos, 'A', :foto, 0, :ceda, :cedr)")
				    ->execute([
						':sede' => (int)$destino['sede_id'], ':pos' => $al['alumno_posicionid'] ?: '3ND', ':nac' => $al['alumno_nacionalidadid'],
						':repre' => $al['alumno_repreid'], ':origen' => (int)$al['alumno_id'], ':codigo' => $codigo,
						':n1' => $al['alumno_primernombre'], ':n2' => $al['alumno_segundonombre'], ':a1' => $al['alumno_apellidopaterno'],
						':a2' => $al['alumno_apellidomaterno'], ':corto' => $al['alumno_nombrecorto'], ':dir' => $al['alumno_direccion'],
						':nacim' => $al['alumno_fechanacimiento'], ':ingreso' => $fecha, ':genero' => $al['alumno_genero'],
						':hermanos' => $al['alumno_hermanos'], ':foto' => $foto, ':ceda' => $cedulaA, ':cedr' => $cedulaR,
					]);
				$nuevo = (int)$pdo->lastInsertId();

				/* La última información médica y el último contacto de emergencia. */
				$pdo->prepare("INSERT INTO alumno_infomedic
						(infomedic_alumnoid, infomedic_fecha, infomedic_tiposangre, infomedic_peso, infomedic_talla, infomedic_enfermedad,
						 infomedic_medicamentos, infomedic_alergia1, infomedic_alergia2, infomedic_cirugias, infomedic_observacion,
						 infomedic_covid, infomedic_vacunas)
					SELECT :nuevo, NOW(), infomedic_tiposangre, infomedic_peso, infomedic_talla, infomedic_enfermedad,
						 infomedic_medicamentos, infomedic_alergia1, infomedic_alergia2, infomedic_cirugias, infomedic_observacion,
						 infomedic_covid, infomedic_vacunas
					  FROM alumno_infomedic WHERE infomedic_alumnoid = :a ORDER BY infomedic_id DESC LIMIT 1")
				    ->execute([':nuevo' => $nuevo, ':a' => (int)$al['alumno_id']]);
				$pdo->prepare("INSERT INTO alumno_cemergencia (cemer_alumnoid, cemer_nombre, cemer_celular, cemer_parentesco)
					SELECT :nuevo, cemer_nombre, cemer_celular, cemer_parentesco
					  FROM alumno_cemergencia WHERE cemer_alumnoid = :a ORDER BY cemer_id DESC LIMIT 1")
				    ->execute([':nuevo' => $nuevo, ':a' => (int)$al['alumno_id']]);

				if ($horario > 0) {
					$pdo->prepare("INSERT INTO asistencia_asignahorario (asignahorario_horarioid, asignahorario_alumnoid) VALUES (:h, :a)")
					    ->execute([':h' => $horario, ':a' => $nuevo]);
				}
				$pdo->commit();
			} catch (\Throwable $e) {
				if ($pdo->inTransaction()) { $pdo->rollBack(); }
				foreach ($copiados as $archivo) { @unlink($archivo); }
				try { $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($bloqueo) . ")"); } catch (\Throwable $e2) {}
				return $error($e instanceof \RuntimeException ? $e->getMessage() : 'No fue posible inscribir al alumno. Inténtelo de nuevo.');
			}
			try { $pdo->query("SELECT RELEASE_LOCK(" . $pdo->quote($bloqueo) . ")"); } catch (\Throwable $e) {}

			$nombre = html_entity_decode(trim($al['alumno_primernombre'] . ' ' . $al['alumno_apellidopaterno']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
			return json_encode([
				"tipo"   => "redireccionar",
				"url"    => APP_URL . 'alumnoProfile/' . $nuevo . '/',
				"titulo" => "Inscrito en " . $destino['sede_nombre'],
				"texto"  => $nombre . " quedó inscrito en " . $destino['sede_nombre'] . " con el código " . $codigo . ".",
				"icono"  => "success",
			]);
		}

		/*----------  El representante al que se le añade un alumno  ----------*/
		/*
		| Para la cabecera del alta (alumnoNew/{repre}): quién es, cómo
		| localizarlo, su dirección (que el alta ofrece copiar) y cuántos
		| alumnos tiene ya, que ayuda a contestar «¿hermanos en la escuela?».
		| Los textos salen sin escapar. Devuelve null si no existe.
		*/
		public function representanteParaAlta($repreid){
			if ((int)$repreid <= 0) {
				return null;
			}
			$r = $this->ejecutarConsulta("SELECT R.repre_id, R.repre_identificacion, R.repre_primernombre, R.repre_segundonombre,
						R.repre_apellidopaterno, R.repre_apellidomaterno, R.repre_celular, R.repre_direccion,
						(SELECT COUNT(*) FROM sujeto_alumno A WHERE A.alumno_repreid = R.repre_id) AS alumnos
					FROM alumno_representante R WHERE R.repre_id = :repre", [':repre' => (int)$repreid])->fetch();
			if (!$r) {
				return null;
			}
			$texto = static fn($v) => html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			return [
				'id'             => (int)$r['repre_id'],
				'nombre'         => preg_replace('/\s+/', ' ', trim($texto($r['repre_primernombre'] . ' ' . $r['repre_segundonombre'] . ' '
				                    . $r['repre_apellidopaterno'] . ' ' . $r['repre_apellidomaterno']))),
				'identificacion' => $texto($r['repre_identificacion']),
				'celular'        => $texto($r['repre_celular']),
				'direccion'      => trim($texto($r['repre_direccion'])),
				'alumnos'        => (int)$r['alumnos'],
			];
		}

		/*----------  Un horario, día por día  ----------*/
		/*
		| Un renglón por día con hora, cancha y profesor. Lo usan la ficha y la
		| edición del alumno (también al cambiar de horario, por AJAX): la tabla
		| de antes —una columna por día— no cabía en un teléfono.
		|
		| Los textos salen ya sin escapar (se guardan con limpiarHtml): quien
		| los pinte los escapa una vez, en PHP o con textContent.
		|
		| Devuelve null si el horario no existe.
		*/
		public function horarioDias($horarioid){
			$filas = $this->ejecutarConsulta("SELECT H.horario_id, H.horario_nombre, H.horario_detalle,
						D.detalle_dia, HO.hora_inicio, HO.hora_fin, L.lugar_nombre, E.empleado_nombre
					FROM asistencia_horario H
					LEFT JOIN asistencia_horario_detalle D ON D.detalle_horarioid = H.horario_id
					LEFT JOIN asistencia_hora HO ON HO.hora_id = D.detalle_horaid
					LEFT JOIN asistencia_lugar L ON L.lugar_id = D.detalle_lugarid
					LEFT JOIN sujeto_empleado E ON E.empleado_id = D.detalle_profesorid
					WHERE H.horario_id = :horario
					ORDER BY D.detalle_dia, HO.hora_inicio", [':horario' => (int)$horarioid])->fetchAll();
			if (!$filas) {
				return null;
			}

			$texto = static fn($v) => $v === null ? '' : html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$horario = ['id' => (int)$filas[0]['horario_id'], 'nombre' => $texto($filas[0]['horario_nombre']),
			            'detalle' => $texto($filas[0]['horario_detalle']), 'dias' => []];
			foreach ($filas as $f) {
				if ($f['detalle_dia'] !== null) {
					$horario['dias'][] = [
						'dia'      => (int)$f['detalle_dia'],
						'inicio'   => $f['hora_inicio'] ? substr($f['hora_inicio'], 0, 5) : '',
						'fin'      => $f['hora_fin'] ? substr($f['hora_fin'], 0, 5) : '',
						'lugar'    => $texto($f['lugar_nombre']),
						'profesor' => $texto($f['empleado_nombre']),
					];
				}
			}
			return $horario;
		}

		public function listarhorariosProfile($horarioid = null, $sedeid = null){
			$option="";

			$consulta_datos="SELECT AH.horario_id, CONCAT(AH.horario_detalle, ' | ',HORA.hora_inicio, ' - ', HORA.hora_fin ) AS HORARIO
								FROM asistencia_horario AH
									INNER JOIN( 
										SELECT detalle_horarioid, detalle_horaid, H.hora_inicio, H.hora_fin
										FROM asistencia_horario_detalle D
										INNER JOIN asistencia_hora H on H.hora_id = D.detalle_horaid
										GROUP BY detalle_horarioid, detalle_horaid, H.hora_inicio, H.hora_fin
									)HORA ON HORA.detalle_horarioid = AH.horario_id

								WHERE (AH.horario_estado = 'A' AND AH.horario_sedeid = :sede)
								   OR AH.horario_id = :actual";
			/* El horario que ya tiene el alumno entra siempre, aunque esté
			   inactivo o sea de otra sede: si faltaba, el desplegable abría en
			   «Seleccione» y al guardar se le quitaba el horario sin avisar. */
			$datos = $this->ejecutarConsulta($consulta_datos, [':sede' => (int)$sedeid, ':actual' => (int)$horarioid]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($horarioid == $rows['horario_id']){
					$option.='<option value='.$rows['horario_id'].' selected="selected">'.$rows['HORARIO'].'</option>';
				}else{
					$option.='<option value='.$rows['horario_id'].'>'.$rows['HORARIO'].'</option>';
				}					
			}
			return $option;
		}

		public function listarhorarios(){
			$option="";

			$consulta_datos="SELECT AH.horario_id, CONCAT(AH.horario_detalle, ' | ',HORA.hora_inicio, ' - ', HORA.hora_fin ) AS HORARIO
								FROM asistencia_horario AH
									INNER JOIN( 
										SELECT detalle_horarioid, detalle_horaid, H.hora_inicio, H.hora_fin
										FROM asistencia_horario_detalle D
										INNER JOIN asistencia_hora H on H.hora_id = D.detalle_horaid
										GROUP BY detalle_horarioid, detalle_horaid, H.hora_inicio, H.hora_fin
									)HORA ON HORA.detalle_horarioid = AH.horario_id

								WHERE AH.horario_estado = 'A'";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){				
				$option.='<option value='.$rows['horario_id'].'>'.$rows['HORARIO'].'</option>';									
			}
			return $option;
		}
	}