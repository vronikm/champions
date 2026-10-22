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
			$alumno_posicionid			= ""; //$this->limpiarCadena($_POST['alumno_posicionid']);					
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

		/*----------  Matriz de alumnos con opciones Ver, Actualizar, Eliminar  ----------*/
		public function listarAlumnos($identificacion, $apellidopaterno, $primernombre, $anio, $sede){
			$tabla="";
			$condiciones = [];
			$busqueda = [];
			/* Se llena a la par que las condiciones. Las ramas que
			   reemplazan la consulta entera lo vacian. */
			$parametros = [];

			// Identificación
			if ($identificacion != "") {
				$busqueda[] = "alumno_identificacion LIKE :ident";
				$parametros[':ident'] = $identificacion . '%';
			}

			// Nombre
			if ($primernombre != "") {
				$busqueda[] = "alumno_primernombre LIKE :nombre";
				$parametros[':nombre'] = $primernombre . '%';
			}

			// Apellido
			if ($apellidopaterno != "") {
				$busqueda[] = "alumno_apellidopaterno LIKE :apellido";
				$parametros[':apellido'] = $apellidopaterno . '%';
			}

			// Agrupar búsqueda por nombre/identificación/apellido
			if (!empty($busqueda)) {
				$condiciones[] = "(".implode(" OR ", $busqueda).")";
			}

			// Filtro por año (usar rango en lugar de YEAR para índices)
			if ($anio != "") {
				$condiciones[] = "alumno_fechanacimiento BETWEEN :anioIni AND :anioFin";
				$parametros[':anioIni'] = $anio . '-01-01';
				$parametros[':anioFin'] = $anio . '-12-31';
			}

			// Si no hay ningún filtro de identificación, nombre o apellido
			if ($identificacion == "" && $primernombre == "" && $apellidopaterno == "") {
				if ($anio == "") {					
					$condiciones = ["alumno_primernombre <> ''"];
					$parametros = [];
				}
			}

			// Filtro de sede
			if ($sede != "") {
				if ($sede == 0) {
					$condiciones[] = "alumno_sedeid <> 0";
				} else {
					$condiciones[] = "alumno_sedeid = :sede";
					$parametros[':sede'] = (int)$sede;
				}
			} else {
				$condiciones = ["alumno_primernombre = ''"]; // sin sede -> nunca traerá resultados
				$parametros = [];
			}

			// Condición fija
			$condiciones[] = "alumno_estado <> 'E'  ORDER BY alumno_numcamiseta";

			// Construir SQL final
			$consulta_datos = "SELECT *, TIMESTAMPDIFF(YEAR, alumno_fechanacimiento, CURDATE()) AS EDAD FROM sujeto_alumno WHERE " . implode(" AND ", $condiciones);
			
			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();

			/* La interfaz sólo ofrece lo que el rol puede hacer en esta
			   pantalla. Es acompañamiento del control real, que sigue
			   estando en ajax_guard: aquí se evita ofrecer un botón que
			   luego iba a rechazarse. */
			$puedeEditar   = puede_editar('alumnoList');
			$puedeEliminar = puede_eliminar('alumnoList');

			foreach($datos as $rows){
				if($rows['alumno_estado']=='A'){
					$estado = "Activo";
					$texto = "Inactivar";
					$boton = "btn-secondary";
				}else{
					$estado = "Inactivo";
					$texto = "Activar";
					$boton = "btn-info";
				}

				$acciones = '';

				if($puedeEliminar){
					$acciones.='
							<form class="FormularioAjax" action="'.APP_URL.'app/ajax/alumnoAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_alumno" value="eliminar">
								<input type="hidden" name="alumno_id" value="'.$rows['alumno_id'].'">
								<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 5px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
							</form>';
				}

				if($puedeEditar){
					$acciones.='
							<a href="'.APP_URL.'alumnoUpdate/'.$rows['alumno_id'].'/" target="_blank" class="btn float-end btn-actualizar btn-sm" style="margin-right: 5px;" title="Actualizar" aria-label="Actualizar"><i class="fas fa-pen"></i></a>';
				}

				/* Ver es lectura: quien llega a la pantalla ya tiene permiso. */
				$acciones.='
							<a href="'.APP_URL.'alumnoProfile/'.$rows['alumno_id'].'/" target="_blank" class="btn float-end btn-ver btn-sm" style="margin-right: 5px;" title="Ver" aria-label="Ver"><i class="fas fa-eye"></i></a>';

				if($puedeEditar){
					$acciones.='
							<form class="FormularioAjax" action="'.APP_URL.'app/ajax/alumnoAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_alumno" value="actualizarestado">
								<input type="hidden" name="alumno_id" value="'.$rows['alumno_id'].'">
								<button type="submit" class="btn float-end '.$boton.' btn-sm" style="margin-right: 5px;"> '.$texto.' </button>
							</form>';
				}

				$tabla.='
					<tr>
						<td>'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['alumno_primernombre'].' '.$rows['alumno_segundonombre'].'</td>
						<td>'.$rows['alumno_apellidopaterno'].' '.$rows['alumno_apellidomaterno'].'</td>
						<td>'.$rows['alumno_fechanacimiento'].' - <p style="font-weight:bold; color:#007bff; display:inline; margin:0;">'.$rows['EDAD'].' años</p></td>

						<td>'.$acciones.'</td>
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

			$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede";	
					
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
		public function listarAlumnosPDF($categoriaid,$sedeid){		
			$consulta_datos=("SELECT sede_nombre, alumno_identificacion, alumno_primernombre, alumno_segundonombre, 
									alumno_apellidopaterno, alumno_apellidomaterno, alumno_fechanacimiento
								FROM sujeto_alumno, general_sede
								WHERE alumno_estado = 'A'
									AND alumno_sedeid = sede_id");	
			$parametros = [];

			if($categoriaid!=0){
				$consulta_datos .= " and YEAR(alumno_fechanacimiento) = :categoria"; 
				$parametros[':categoria'] = (int)$categoriaid;
			}

			if($sedeid!=0){
				$consulta_datos .= " and alumno_sedeid = :sede"; 
				$parametros[':sede'] = (int)$sedeid;
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

			if($rolid != 1 && $rolid != 2){
				$consulta_datos="SELECT S.sede_id, S.sede_nombre 
									FROM general_sede S
									INNER JOIN seguridad_usuario_sede US ON US.usuariosede_sedeid = S.sede_id
									INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
									WHERE U.usuario_usuario = :usuario";
				$parametros = [':usuario' => $usuario];
			}else{
				$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede";
				$parametros = [];
			}				
					
			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$option.='<option value='.$rows['sede_id'].'>'.$rows['sede_nombre'].'</option>';					
			}
			return $option;
		}

		public function listarSedebusqueda($sedeid, $rolid = null, $usuario = null){
			$option="";

			if($rolid != 1 && $rolid != 2){
				$consulta_datos="SELECT S.sede_id, S.sede_nombre 
									FROM general_sede S
									INNER JOIN seguridad_usuario_sede US ON US.usuariosede_sedeid = S.sede_id
									INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
									WHERE U.usuario_usuario = :usuario";
				$parametros = [':usuario' => $usuario];
			}else{
				$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede";
				$parametros = [];
			}						
					
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
						(SELECT COUNT(*) FROM alumno_pago WHERE pago_alumnoid = A.alumno_id AND pago_estado = 'P') AS pagos_pendientes
					FROM sujeto_alumno A
					LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
					" . $catalogo('TI', 'tipo_documento', 'A.alumno_tipoidentificacion') . "
					" . $catalogo('NA', 'nacionalidad', 'A.alumno_nacionalidadid') . "
					WHERE A.alumno_id = :alumno", $p)->fetch();
			if (!$alumno) {
				return null;
			}

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

			return [
				'alumno'        => $alumno,
				'representante' => $representante ?: null,
				'emergencia'    => $emergencia ?: null,
				'medica'        => $medica ?: null,
				'horarios'      => $horarios,
			];
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