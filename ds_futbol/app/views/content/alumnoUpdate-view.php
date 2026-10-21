<?php
	use app\controllers\alumnoController;
	$insAlumno = new alumnoController();	

	$alumnoid=$insAlumno->limpiarCadena($url[1]);

	$datos=$insAlumno->seleccionarDatos("Unico","sujeto_alumno","alumno_id",$alumnoid);

	if($datos->rowCount()==1){
		$datos=$datos->fetch();
		if ($datos['alumno_imagen']!=""){
			$foto = media_url('alumno', $datos['alumno_imagen']);
		}else{
			$foto = ds_recurso('ds_futbol/app/views/dist/img/foto.jpg');
		}

		if ($datos['alumno_cedulaA']!=""){
			$cedulaA = media_url('cedula', $datos['alumno_cedulaA']);
		}else{
			$cedulaA = media_url('cedula', 'Sinregistro.jpg');
		}

		if ($datos['alumno_cedulaR']!=""){
			$cedulaR = media_url('cedula', $datos['alumno_cedulaR']);
		}else{
			$cedulaR = media_url('cedula', 'Sinregistro.jpg');
		}
		
		if ($datos['alumno_genero']=='M'){
			$alumno_generoM = "checked";
		}else{
			$alumno_generoM = "";
		}

		if ($datos['alumno_genero']=='F'){
			$alumno_generoF = "checked";
		}else{
			$alumno_generoF = "";
		}

		if ($datos['alumno_hermanos']=='S'){
			$alumno_hermanosSi = "checked";
		}else{
			$alumno_hermanosSi = "";
		}

		if ($datos['alumno_hermanos']=='N'){
			$alumno_hermanosNo = "checked";
		}else{
			$alumno_hermanosNo = "";
		}
	
	$datosmedic=$insAlumno->seleccionarDatos("Unico","alumno_infomedic","infomedic_alumnoid",$alumnoid);
	if($datosmedic->rowCount()==1){
		$datosmedic=$datosmedic->fetch();
		
		$tipo_sangre	=$datosmedic['infomedic_tiposangre'];
		$peso			=$datosmedic['infomedic_peso'];
		$talla			=$datosmedic['infomedic_talla'];
		$enfermedad		=$datosmedic['infomedic_enfermedad'];
		$medicamentos	=$datosmedic['infomedic_medicamentos'];
		$alergia1		=$datosmedic['infomedic_alergia1'];
		$alergia2		=$datosmedic['infomedic_alergia2'];
		$cirugias		=$datosmedic['infomedic_cirugias'];
		$observacion	=$datosmedic['infomedic_observacion'];
		
		if ($datosmedic['infomedic_covid']=='S'){
			$infomedic_covidSi = "checked";
		}else{
			$infomedic_covidSi = "";
		}

		if ($datosmedic['infomedic_covid']=='N'){
			$infomedic_covidNo = "checked";
		}else{
			$infomedic_covidNo = "";
		}
		if ($datosmedic['infomedic_vacunas']=='S'){
			$infomedic_vacunasSi = "checked";
		}else{
			$infomedic_vacunasSi = "";
		}
		if ($datosmedic['infomedic_vacunas']=='N'){
			$infomedic_vacunasNo = "checked";
		}else{
			$infomedic_vacunasNo = "";
		}

	}else{
		$tipo_sangre		="";
		$peso				="";
		$talla				="";
		$enfermedad			="";
		$medicamentos		="";
		$alergia1			="";
		$alergia2			="";
		$cirugias			="";
		$observacion		="";
		$infomedic_covidSi 	= "";
		$infomedic_covidNo	= "";
		$infomedic_vacunasSi = "";
		$infomedic_vacunasNo = "";
	}

	$datoscemer=$insAlumno->seleccionarDatos("Unico","alumno_cemergencia","cemer_alumnoid",$alumnoid);
	if($datoscemer->rowCount()==1){
		$datoscemer=$datoscemer->fetch();
		$cemer_nombre			=$datoscemer['cemer_nombre'];
		$cemer_celular			=$datoscemer['cemer_celular'];
		$cemer_parentesco		=$datoscemer['cemer_parentesco'];	

	}else{
		$cemer_nombre		="";
		$cemer_celular		="";
		$cemer_parentesco	="";
	}

	$horario_id=$insAlumno->HorarioID($alumnoid);
	if($horario_id->rowCount()==1){
		$horario_id=$horario_id->fetch(); 
		$horarioid = $horario_id['asignahorario_horarioid'];
    }else{
		$horarioid = 0;
	}


	$datoshorario=$insAlumno->seleccionarDatos("Unico","asistencia_horario","horario_id",$horarioid);
	if($datoshorario->rowCount()==1){
		$datoshorario=$datoshorario->fetch();
		$lugar_sedeid 		= $datoshorario['horario_sedeid'];
		$horario_nombre 	= $datoshorario['horario_nombre'];
		$horario_detalle	= $datoshorario['horario_detalle'];
		$horario_estado		= $datoshorario['horario_estado'];
	}else{
		$lugar_sedeid = isset($_POST['horario_sedeid']) ? $insHorario->limpiarCadena($_POST['horario_sedeid']) : 0;
		$horario_nombre 	= "";
		$horario_detalle	= "";
		$horario_estado		= "";
	}
?>

<?php
	/* La cabecera es comun a todas las vistas: ds_futbol/app/views/inc/cabecera.php */
	$tituloVista = 'Ficha alumno';
	$extras      = array (0 => 'select2',1 => 'dropzone',);
	$cabeceraExtra = <<<'CSS'
	<style>
		input:invalid {
		  box-shadow: 0 0 2px 1px red;
		}
		input:focus:invalid {
		  box-shadow: none;
		}
		textarea:invalid {
		  box-shadow: 0 0 2px 1px red;
		}
		textarea:focus:invalid {
		  box-shadow: none;
		}
	</style>
CSS;
	require_once "app/views/inc/cabecera.php";
?>

  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">

		<!-- Preloader -->
		<!--?php require_once "app/views/inc/preloader.php"; ?-->
		<!-- /.Preloader -->

		<!-- Navbar -->
		<?php require_once "app/views/inc/navbar.php"; ?>
		<!-- /.navbar -->

		<!-- Main Sidebar Container -->
		<?php require_once "app/views/inc/main-sidebar.php"; ?>
		<!-- /.Main Sidebar Container -->  

		<!-- vista -->
		<div class="app-main">

			<!-- Content Header (Page header) -->
			<div class="app-content-header">
				<div class="container-fluid">
					<div class="row mb-2">
						<div class="col-sm-6">
							<h1 class="m-0">Actualizar Alumno</h1>
						</div><!-- /.col -->
						<div class="col-sm-6">
							<ol class="breadcrumb float-sm-end">
								<li class="breadcrumb-item"><a href="#">Inicio</a></li>
								<li class="breadcrumb-item active">Ficha Alumno</li>
							</ol>
						</div><!-- /.col -->
					</div><!-- /.row -->
				</div><!-- /.container-fluid -->
			</div>
			<!-- /.content-header -->

			<!-- Main content -->
			<section class="app-content">				
				<!-- /.container-fluid información alumno -->
				<form id="formAlumno" class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/alumnoAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" novalidate>
				<input type="hidden" name="modulo_alumno" value="actualizar">
				<input type="hidden" name="alumno_id" value="<?php echo $datos['alumno_id']; ?>">
				<div class="container-fluid">						
					<div class="card">
						<div class="card-header p-2">
							<ul class="nav nav-pills">
								<li class="nav-item"><a class="nav-link active" href="#informacionp" data-bs-toggle="tab">Información Personal</a></li>
								<li class="nav-item"><a class="nav-link" href="#cedula" data-bs-toggle="tab">Cédula</a></li>
								<li class="nav-item"><a class="nav-link" href="#contactoem" data-bs-toggle="tab">Contacto emergencia</a></li>											
								<li class="nav-item"><a class="nav-link" href="#informacionm" data-bs-toggle="tab">Información Médica</a></li>
								<li class="nav-item"><a class="nav-link" href="#horario" data-bs-toggle="tab">Horario</a></li>
							</ul>
						</div><!-- /.card-header -->
					
						<div class="card-body">
							<div class="tab-content">
								<!-- Tab de información personal del alumno -->
								<div class="active tab-pane" id="informacionp"> 
									<!-- Primera sección foto-->
									<div class="row">
										<div class="col-md-2">
											<div class="mb-3">
												<label for="alumno_foto">Foto (250KB)</label>		
												<div class="input-group">											
													<div class="fileinput fileinput-new" data-provides="fileinput">
														<div class="fileinput-new thumbnail" style="width: 130px; min-height: 158px;" data-trigger="fileinput">
															<img src="<?php echo $foto; ?>"></div>
														<div class="fileinput-preview fileinput-exists thumbnail" style="max-width: 130px; max-height: 158px"></div>
														<div>
															<span class="bton bton-white bton-file">
																<span class="fileinput-new">Seleccionar Foto</span>
																<span class="fileinput-exists">Cambiar</span>
																<input type="file" name="alumno_foto" id="alumno_foto" accept="image/*">
															</span>
															<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Remover</a>
														</div>
													</div>
												</div>		
											</div>
											<!-- /.mb-3 -->	
										</div>
										<div class="col-md-10"> 
											<div class="row">
												<div class="col-md-4">
													<div class="mb-3">
														<label for="alumno_identificacion">Identificación</label>                        
														<input type="text" class="form-control" id="alumno_identificacion" name="alumno_identificacion" value="<?php echo $datos['alumno_identificacion']; ?>" required>
													</div>
												</div>											
												<div class="col-md-4">                        
													<div class="mb-3">
														<label for="alumno_apellido1">Apellido paterno</label>
														<input type="text" class="form-control" id="alumno_apellido1" name="alumno_apellido1" value="<?php echo $datos['alumno_apellidopaterno']; ?>" required>
													</div>
												</div>
												<div class="col-md-4">
													<label for="alumno_apellido2">Apellido materno</label>
													<input type="text" class="form-control" id="alumno_apellido2" name="alumno_apellido2" value="<?php echo $datos['alumno_apellidomaterno']; ?>">
												</div>
												<div class="col-md-4">
													<div class="mb-3">
														<label for="alumno_tipoidentificacion">Tipo identificación</label>
														<select id="alumno_tipoidentificacion" class="form-select" name="alumno_tipoidentificacion">																					
															<?php echo $insAlumno->listarOptionTipoIdentificacion($datos['alumno_tipoidentificacion']); ?>
														</select>
													</div>          
												</div>
												<div class="col-md-4">                        
													<div class="mb-3">
														<label for="alumno_nombre1">Primer nombre</label>
														<input type="text" class="form-control" id="alumno_nombre1" name="alumno_nombre1" value="<?php echo $datos['alumno_primernombre']; ?>" required>
													</div>
												</div>
												<div class="col-md-4">
													<label for="alumno_nombre2">Segundo nombre</label>
													<input type="text" class="form-control" id="alumno_nombre2" name="alumno_nombre2" value="<?php echo $datos['alumno_segundonombre']; ?>">
												</div>    
												<div class="col-md-4">
													<div class="mb-3">
														<label for="alumno_nacionalidadid">Nacionalidad</label>
														<select class="form-select" style="width: 100%;" id="alumno_nacionalidadid" name="alumno_nacionalidadid">
															<?php echo $insAlumno->listarOptionNacionalidad($datos['alumno_nacionalidadid']); ?>
														</select>
													</div> 
												</div>
												<div class="col-md-4">									
													<div class="mb-3">
														<label for="alumno_fechanacimiento">Fecha nacimiento</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" id="alumno_fechanacimiento" name="alumno_fechanacimiento" value="<?php echo $datos['alumno_fechanacimiento']; ?>" required>
																																										<span id="edad_texto" class="input-group-text" style="min-width:50px; font-weight:bold; color:#007bff;">--</span>
											
														</div>
													<!-- /.input group -->
													</div>												
												</div>
												<div class="col-md-4">
													<div class="mb-3">
														<label for="alumno_fechaingreso">Fecha ingreso</label>
														<div class="input-group">
																											<span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
											
															<input type="date" class="form-control" id="alumno_fechaingreso" name="alumno_fechaingreso" value="<?php echo $datos['alumno_fechaingreso']; ?>" required>
																											<span id="ingreso_texto" class="input-group-text" style="font-weight:bold; color:#007bff;">--</span>
											

														</div>
													<!-- /.input group -->
													</div>								
												</div>
											</div>
											<!-- Fin primera sección foto-->
										</div>
									</div> <!--fin col md 10-->

									<!-- Segunda sección foto-->
									<div class="row">										
										<div class="col-md-2">
											<div class="mb-3">
												<label for="Numcamiseta">Número de camiseta</label>
												<input type="text" class="form-control" id="alumno_numcamiseta" name="alumno_numcamiseta" value="<?php echo $datos['alumno_numcamiseta']; ?>"> 
											</div>
										</div>  
										<div class="col-md-2">
											<div class="mb-3">
												<label for="alumno_sedeid">Sede</label>
												<select class="form-select" id="alumno_sedeid" name="alumno_sedeid">									
													<?php echo $insAlumno->listarSedeAlumno($datos['alumno_sedeid']); ?>
												</select>	
											</div>
										</div> 
										<div class="col-md-3">
											<div class="mb-3">
												<label for="alumno_direccion">Dirección</label>
												<input type="text" class="form-control" id="alumno_direccion" name="alumno_direccion" value="<?php echo $datos['alumno_direccion']; ?>">
											</div>	
										</div>
										<div class="col-md-2">
											<div class="mb-3">
												<label for="alumno_hermanos">Tiene hermanos?</label>
												<!-- radio -->
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="alumno_hermanosSi" name="alumno_hermanos" value="S" <?php echo $alumno_hermanosSi; ?> required>
												        <label class="form-check-label" for="alumno_hermanosSi">Si</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="alumno_hermanosNo" name="alumno_hermanos" value="N" <?php echo $alumno_hermanosNo; ?>>
												        <label class="form-check-label" for="alumno_hermanosNo">No</label>
												    </div>
												</div>
											</div>
										</div>	  
										<div class="col-md-3">
											<div class="mb-3">
												<label for="alumno_genero">Sexo</label>
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="alumno_generoM" name="alumno_genero" value="M" <?php echo $alumno_generoM; ?> required>
												        <label class="form-check-label" for="alumno_generoM">Masculino</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="alumno_generoF" name="alumno_genero" value="F" <?php echo $alumno_generoF; ?>>
												        <label class="form-check-label" for="alumno_generoF">Femenino</label>
												    </div>
												</div> 
											</div>
										</div>   
									</div>  <!--./row line 874--> 
									<!-- Fin segunda sección foto-->			
								</div>

								<!-- Tab de información médica del alumno -->
								<div class="tab-pane" id="informacionm">
									<div class="row">
										<div class="col-md-3">
											<div class="mb-3">
												<label for="infomedic_tiposangre">Tipo de sangre</label>
												<input type="text" class="form-control" id="infomedic_tiposangre" name="infomedic_tiposangre" value="<?php echo $tipo_sangre;?>" >                          
											</div>
										</div> 
										<div class="col-md-3">
											<div class="mb-3">
												<label for="Peso">Peso (Kg)</label>
												<input type="text" class="form-control" id="infomedic_peso" name="infomedic_peso"  value="<?php echo $peso;?>" >                          
											</div>
										</div>   
										<div class="col-md-3">
											<div class="mb-3">
												<label for="Talla">Talla (cm)</label>
												<input type="text" class="form-control" id="infomedic_talla" name="infomedic_talla"  value="<?php echo $talla;?>" >                          
											</div>
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="Enfermedad">Enfermedad diagnosticada</label>
												<input type="text" class="form-control" id="infomedic_enfermedad" name="infomedic_enfermedad"  value="<?php echo $enfermedad;?>" >                          
											</div>
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="Medicamentos">Medicamentos</label>
												<input type="text" class="form-control" id="infomedic_medicamentos" name="infomedic_medicamentos"  value="<?php echo $medicamentos;?>" >                          
											</div>
										</div> 
										<div class="col-md-3">
											<div class="mb-3">
												<label for="Alergia1">Alergia a medicamentos</label>
												<input type="text" class="form-control" id="infomedic_alergia1" name="infomedic_alergia1"  value="<?php echo $alergia1;?>" >                          
											</div>
										</div> 
										<div class="col-md-3">
											<div class="mb-3">
												<label for="Alergia2">Alergia a objetos</label>
												<input type="text" class="form-control" id="infomedic_alergia2" name="infomedic_alergia2"  value="<?php echo $alergia2;?>" >                          
											</div>
										</div>  
										<div class="col-md-3">
											<div class="mb-3">
												<label for="Cirugias">Cirugías</label>
												<input type="text" class="form-control" id="infomedic_cirugias" name="infomedic_cirugias"  value="<?php echo $cirugias;?>" >                          
											</div>
										</div>  
										<div class="col-md-3">
											<div class="mb-3">
												<label for="Observacion">Observación</label>
												<input type="text" class="form-control" id="infomedic_observacion" name="infomedic_observacion"  value="<?php echo $observacion;?>" >                          
											</div>
										</div>  
										<div class="col-md-3">
											<div class="mb-3">
												<label for="Covid">Carnet vacunación Covid</label>
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="infomedic_covidSi" name="infomedic_covid" value="S" <?php echo $infomedic_covidSi;?>>
												        <label class="form-check-label" for="infomedic_covidSi">Si</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="infomedic_covidNo" name="infomedic_covid" value="N" <?php echo $infomedic_covidNo;?>>
												        <label class="form-check-label" for="infomedic_covidNo">No</label>
												    </div>
												</div>
											</div>
										</div>
										<div class="col-md-4">
											<div class="mb-3">
												<label for="Vacunas">Carnet vacunación habitual</label>
												<div class="d-flex flex-wrap gap-4">
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="infomedic_vacunasSi" name="infomedic_vacunas" value="S" <?php echo $infomedic_vacunasSi;?>>
												        <label class="form-check-label" for="infomedic_vacunasSi">Si</label>
												    </div>
												    <div class="form-check">
												        <input class="form-check-input" type="radio" id="infomedic_vacunasNo" name="infomedic_vacunas" value="N" <?php echo $infomedic_vacunasNo;?>>
												        <label class="form-check-label" for="infomedic_vacunasNo">No</label>
												    </div>
												</div>                         
											</div>
										</div>  
									</div>
								</div>

								<!-- Tab información contacto de emergencia -->
								<div class="tab-pane" id="contactoem">
									<div class="row">
										<div class="col-md-3">
											<div class="mb-3">
												<label for="cemer_celular">Celular emergencia</label>
												<input type="text" class="form-control" id="cemer_celular" name="cemer_celular" value="<?php echo $cemer_celular;?>" >                          
											</div>
										</div>
										<div class="col-md-3">
											<div class="mb-3">
												<label for="cemer_nombre">Nombre contacto emergencia</label>
												<input type="text" class="form-control" id="cemer_nombre" name="cemer_nombre" value="<?php echo $cemer_nombre;?>" >                          
											</div>
										</div>
										<div class="col-md-4">
											<div class="mb-3">
												<label for="cemer_parentesco">Parentesco</label>
												<select class="form-select" style="width: 100%;" id="cemer_parentesco" name="cemer_parentesco" >
													<?php echo $insAlumno->listarCatalogoParentesco($cemer_parentesco); ?>
												</select>
											</div> 
										</div>
									</div>		
								</div>
								
								<!-- Tab cedula del alumno -->
								<div class="tab-pane" id="cedula">
									<div class="row">
										<div class="col-md-4">
											<div class="mb-3">
												<label for="alumno_cedulaA">Anverso</label>		
												<div class="input-group">											
													<div class="fileinput fileinput-new" data-provides="fileinput">
														<div class="fileinput-new thumbnail" style="width: 100%; max-width: 330px; height: 210px;" data-trigger="fileinput">
															<img src="<?php echo $cedulaA; ?>"></div>
														<div class="fileinput-preview fileinput-exists thumbnail" style="width: 100%; max-width: 330px; height: 210px"></div>
														<div>
															<span class="bton bton-white bton-file">
																<span class="fileinput-new">Imagen</span>
																<span class="fileinput-exists">Cambiar</span>
																<input type="file" name="alumno_cedulaA" id="alumno_cedulaA" accept="image/*">
															</span>
															<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Remover</a>
														</div>
													</div>
												</div>		
											</div>
											<!-- /.mb-3 -->	
										</div>
										<div class="col-md-2">
											<div class="mb-3">
												<label for="alumno_cedulaR">Reverso</label>		
												<div class="input-group">											
													<div class="fileinput fileinput-new" data-provides="fileinput">
														<div class="fileinput-new thumbnail" style="width: 100%; max-width: 330px; height: 210px;" data-trigger="fileinput">
															<img src="<?php echo $cedulaR; ?>"></div>
														<div class="fileinput-preview fileinput-exists thumbnail" style="width: 100%; max-width: 330px; height: 210px"></div>
														<div>
															<span class="bton bton-white bton-file">
																<span class="fileinput-new">Imagen</span>
																<span class="fileinput-exists">Cambiar</span>
																<input type="file" name="alumno_cedulaR" id="alumno_cedulaR" accept="image/*">
															</span>
															<a href="#" class="bton bton-orange fileinput-exists" data-bs-dismiss="fileinput">Remover</a>
														</div>
													</div>
												</div>		
											</div>
											<!-- /.mb-3 -->	
										</div>
									</div>
								</div>
								<!-- /.tab-pane -->

								<!-- Tab horario del alumno -->
								<div class="tab-pane" id="horario">
									<div class="container-fluid">													
										<!-- Table row -->
										<div class="row">
											<div class="col-md-4">
												<div class="mb-3">
													<label for="horarioid">Horarios</label>
													<select id="horarioid" class="form-control js-buscador" name="horarioid">
														<option value="">Seleccione un horario</option>																					
														<?php echo $insAlumno->listarhorariosProfile($horarioid, $datos['alumno_sedeid']); ?>
													</select>
												</div>          
											</div>

											<div class="col-md-12 table-responsive">
												<table class="table table-striped table-bordered table-sm">											
													<thead>												
														<tr>													
															<th colspan="8">Horario <?php echo $horario_nombre." - ".$horario_detalle; ?></th>																							
														</tr>
														<tr>		
															<th></th>												
															<th>LUNES</th>	
															<th>MARTES</th>
															<th>MIERCOLES</th>
															<th>JUEVES</th>
															<th>VIERNES</th>																																		
														</tr>
													</thead>	
													<tbody id="tabla_horario">														
															<?php echo $datos=$insAlumno->generarHorarioProfile($horarioid);?>	
													</tbody>
												</table>
											</div>
											<!-- /.col -->
										</div>
										
									</div><!-- /.container-fluid -->
								</div>
								<!-- /.tab-pane -->		

							</div>
							<!-- /.tab-content -->
						</div><!-- /.card-body -->
					</div>
					<!-- /.card -->
					
				</div>
				<div class="card-footer">						
					<button type="submit" class="btn btn-success btn-sm">Actualizar</button>	
					<button class="btn btn-dark btn-back btn-sm" onclick="cerrarPestana()">Regresar</button>													
				</div>					
				</form>
			</section>
			<!-- /.content -->    
			
			<?php
				}else{
					include "./app/views/inc/error_alert.php";
				}
			?>
		</div>
		<!-- /.vista -->

		<?php require_once "app/views/inc/footer.php"; ?>

		<!-- Control Sidebar -->
		<aside class="control-sidebar control-sidebar-dark">
		<!-- Control sidebar content goes here -->
		</aside>
      <!-- /.control-sidebar -->
    </div>
    <!-- ./wrapper -->

    
	<!-- jQuery -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/jquery/jquery.min.js"></script>
	<!-- Bootstrap 4 -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/js/overlayscrollbars.browser.es6.min.js"></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/bootstrap5/js/bootstrap.bundle.min.js"></script>
	
	<!-- Select2 -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/select2/js/select2.full.min.js"></script>
	<!-- Bootstrap4 Duallistbox -->
	<!-- InputMask -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/inputmask/jquery.inputmask.min.js"></script>
	<!-- date-range-picker -->
	<!-- bootstrap color picker -->
	<!-- Tempusdominus Bootstrap 4 -->
	<!-- Bootstrap Switch -->
	<!-- BS-Stepper -->
	<!-- dropzonejs -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/dropzone/min/dropzone.min.js"></script>

	<!-- AdminLTE App -->
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	
	<!-- fileinput -->
    
	<script>
		$(function () {
			/* Lo único del bloque de ejemplo de AdminLTE que esta página
			   usaba de verdad. El resto —tempusdominus, daterangepicker,
			   duallistbox, colorpicker, bootstrap-switch y bs-stepper—
			   apuntaba a selectores inexistentes: se descargaban seis
			   librerías para ejecutar código contra nada. */
			$('.js-buscador').select2({ width: '100%' })
			$('[data-mask]').inputmask()
		})
	</script>

	<script>
		function calcularEdad(fecha) {
			if (!fecha) return "";
			const fechaNacimiento = new Date(fecha);
			if (isNaN(fechaNacimiento)) return "";
			
			const hoy = new Date();
			let edad = hoy.getFullYear() - fechaNacimiento.getFullYear();
			const mes = hoy.getMonth() - fechaNacimiento.getMonth();
			if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNacimiento.getDate())) {
				edad--;
			}
			return `${edad} años`;
		}

		function actualizarEdad() {
			const fecha = document.getElementById("alumno_fechanacimiento").value;
			document.getElementById("edad_texto").textContent = calcularEdad(fecha) || "--";
		}

		// Calcular edad al cargar la página
		window.addEventListener("DOMContentLoaded", actualizarEdad);

		// Recalcular edad cuando cambia la fecha
		document.getElementById("alumno_fechanacimiento").addEventListener("input", actualizarEdad);

		//-------------------------------Fecha ingreso ---------------------------------//

		function calcularFechaIngreso(fecha) {
			if (!fecha) return "";
			const fechaIngreso = new Date(fecha);
			if (isNaN(fechaIngreso)) return "";
			
			const hoy = new Date();
			let edad = hoy.getFullYear() - fechaIngreso.getFullYear();
			const mes = hoy.getMonth() - fechaIngreso.getMonth();
			if (mes < 0 || (mes === 0 && hoy.getDate() < fechaIngreso.getDate())) {
				edad--;
			}
			return `${edad} años`;
		}

		function actualizarIngreso() {
			const fecha = document.getElementById("alumno_fechaingreso").value;
			document.getElementById("ingreso_texto").textContent = calcularFechaIngreso(fecha) || "--";
		}

		// Calcular edad al cargar la página
		window.addEventListener("DOMContentLoaded", actualizarIngreso);

		// Recalcular edad cuando cambia la fecha
		document.getElementById("alumno_fechaingreso").addEventListener("input", actualizarIngreso);
	</script>

	<!-- horarioid-->
	<script>
		$(document).ready(function() {
			$('#horarioid').change(function() {
				var horario_id = $(this).val();

				if (horario_id) {
					$.ajax({
						type: 'POST',
						url: '<?php echo APP_URL; ?>app/ajax/alumnoAjax.php',
						data: {
							modulo_alumno: 'cargarHorario',
							horarioid: horario_id							
						},
						success: function(response) {
							$('#tabla_horario').html(response);
						}
					});
				} else {
					$('#horarioid').html('<option value="">Seleccione un horario</option>');
				}
			});
		});
	</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    document.getElementById("formAlumno").addEventListener("submit", function(e) {
        let form = this;
        let camposRequeridos = form.querySelectorAll("[required]");
        let valido = true;
        let primerCampoVacio = null;

        camposRequeridos.forEach(function(campo){
            if((campo.type === "radio" || campo.type === "checkbox")){
                // Validación para radios/checkbox
                let grupo = form.querySelectorAll(`[name="${campo.name}"]`);
                let algunoMarcado = Array.from(grupo).some(el => el.checked);
                if(!algunoMarcado){
                    valido = false;
                    if(!primerCampoVacio) primerCampoVacio = campo;
                }
            } else if(!campo.value.trim()){
                valido = false;
                campo.style.border = "1px solid red";
                if(!primerCampoVacio) primerCampoVacio = campo;
            } else {
                campo.style.border = "";
            }
        });

        if(!valido){
            e.preventDefault();

            if(primerCampoVacio){
                // Obtener label del campo
                let labelTexto = "";
                let label = form.querySelector(`label[for="${primerCampoVacio.id}"]`);
                if(label){
                    labelTexto = label.innerText.trim();
                } else {
                    let labelPadre = primerCampoVacio.closest("label");
                    if(labelPadre){
                        labelTexto = labelPadre.innerText.trim();
                    }
                }

                // Abrir tab donde está el campo vacío
                let tabPane = primerCampoVacio.closest(".tab-pane");
                if(tabPane && !tabPane.classList.contains("active")){
                    let trigger =
                        document.querySelector(`.nav-pills [href="#${tabPane.id}"]`) ||
                        document.querySelector(`.nav-pills [data-bs-toggle="tab"][data-bs-target="#${tabPane.id}"]`);
                    if(trigger){
                        trigger.click(); // activa el tab
                    }
                }

                // Dar foco al campo vacío
                setTimeout(() => {
                    primerCampoVacio.focus();
                }, 200);

                // Mostrar alerta con nombre del campo
                Swal.fire({
                    title: "Error",
                    text: `Por favor complete el campo obligatorio: "${labelTexto}"`,
                    icon: "error"
                });
            }
        }
    });
});
</script>


	
	<script type="text/javascript">
		function cerrarPestana() {
			window.close();
		}
    </script>

	<script src="<?php echo ds_recurso('ds_core/assets/js/foto.js'); ?>"></script>
  </body>
</html>