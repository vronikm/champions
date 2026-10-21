<?php

	namespace app\controllers;
	use app\models\mainModel;
	use \DateTime;
	
	class asistenciaController extends mainModel{		
		public function registrarHoraControlador(){							
			
			# Almacenando datos#
			$hora_inicio 	= $this->limpiarCadena($_POST['hora_inicio']);
			$hora_fin		= $this->limpiarCadena($_POST['hora_fin']);
			$hora_detalle	= $this->limpiarCadena($_POST['detalle']);
			
			# Verificando campos obligatorios #
		    if($hora_inicio=="" || $hora_fin=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }		

			$hora_datos_reg=[
				[
					"campo_nombre"=>"hora_inicio",
					"campo_marcador"=>":Horainicio",
					"campo_valor"=>$hora_inicio
				],
				[
					"campo_nombre"=>"hora_fin",
					"campo_marcador"=>":Horafin",
					"campo_valor"=>$hora_fin
				],				
				[
					"campo_nombre"=>"hora_detalle",
					"campo_marcador"=>":Horadetalle",
					"campo_valor"=>$hora_detalle
				],				
				[
					"campo_nombre"=>"hora_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>'A'
				]
			];		

			$registrar_hora=$this->guardarDatos("asistencia_hora",$hora_datos_reg);

			if($registrar_hora->rowCount()>0){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Registro exitoso",
					"texto"=>"La hora se registró correctamente",
					"icono"=>"success"
				];				
			
			}else{				

				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible registrar la hora, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function listarHoras(){ 			
			$tabla="";		
			$consulta_datos="SELECT ROW_NUMBER() OVER (ORDER BY hora_id) AS fila_numero
								,CASE WHEN H.hora_estado = 'A' THEN 'Activo' ELSE 'Inactivo' END ESTADO 
								,H.* 
				FROM asistencia_hora H  
				where H.hora_estado != 'E'
				ORDER BY hora_id ASC";		

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){			
				$tabla.='
					<tr>
						<td>'.$rows['fila_numero'].'</td>
						<td>'.$rows['hora_inicio'].'</td>
						<td>'.$rows['hora_fin'].'</td>
						<td>'.$rows['hora_detalle'].'</td>
						<td>'.$rows['ESTADO'].'</td>
						<td>
							'.$this->siPuede('eliminar','asistenciaHora','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_asistencia" value="eliminar">
								<input type="hidden" name="hora_id" value="'.$rows['hora_id'].'">						
								<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 5px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
							</form>').'	
							<a href="'.APP_URL.'asistenciaHora/'.$rows['hora_id'].'/" class="btn float-end btn-actualizar btn-sm" style="margin-right: 5px;"  title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>							
						</td>
					</tr>';	
			}
			return $tabla;			
		}

		public function actualizarHoraControlador(){			
			$horaid=$this->limpiarCadena($_POST['hora_id']);

			# Verificando pago #
			$datos = $this->ejecutarConsulta("SELECT * FROM asistencia_hora WHERE hora_id = :hora", [':hora' => (int)$horaid]);			
			if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No existe la hora en el sistema: ".$horaid,
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();				
		    }				

			# Almacenando datos#
			$hora_inicio 	= $this->limpiarCadena($_POST['hora_inicio']);
			$hora_fin		= $this->limpiarCadena($_POST['hora_fin']);
			$hora_detalle	= $this->limpiarCadena($_POST['detalle']);
			$estado			= $this->limpiarCadena($_POST['estado']);
			
			# Verificando campos obligatorios #
		    if($hora_inicio=="" || $hora_fin=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }
			$hora_datos_reg=[
				[
					"campo_nombre"=>"hora_inicio",
					"campo_marcador"=>":Horainicio",
					"campo_valor"=>$hora_inicio
				],
				[
					"campo_nombre"=>"hora_fin",
					"campo_marcador"=>":Horafin",
					"campo_valor"=>$hora_fin
				],				
				[
					"campo_nombre"=>"hora_detalle",
					"campo_marcador"=>":Horadetalle",
					"campo_valor"=>$hora_detalle
				],				
				[
					"campo_nombre"=>"hora_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$estado
				]
			];			
		
			$condicion=[
				"condicion_campo"=>"hora_id",
				"condicion_marcador"=>":Horaid",
				"condicion_valor"=>$horaid
			];			

			if($this->actualizarDatos("asistencia_hora",$hora_datos_reg,$condicion)){				
				$alerta=[
					"tipo"=>"redireccionar",			
					"url"=>APP_URL.'asistenciaHora/',					
					"titulo"=>"Hora actualizada",
					"texto"=>"Los datos de la hora ".$horaid." se actualizaron correctamente",
					"icono"=>"success"	
				];								

			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible actualizar los datos de la hora ".$horaid.", por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function BuscarHora($horaid){
		
			$consulta_datos="SELECT H.* 
					FROM asistencia_hora H										
					WHERE hora_id = :hora";	

			$datos = $this->ejecutarConsulta($consulta_datos, [':hora' => (int)$horaid]);		
			return $datos;
		}

		public function eliminarHoraControlador(){			
			$horaid=$this->limpiarCadena($_POST['hora_id']);
			$hora_datos=[
				[
					"campo_nombre"=>"hora_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> 'E'
				]
			];

			$condicion=[
				"condicion_campo"=>"hora_id",
				"condicion_marcador"=>":Horaid",
				"condicion_valor"=>$horaid
			];

			if($this->actualizarDatos("asistencia_hora", $hora_datos, $condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Hora eliminada",
					"texto"=>"La hora fue eliminada correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible eliminar la hora, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}

		//-------------------------------------------------lugar--------------------------------------
		public function BuscarLugar($lugarid){		
			$consulta_datos="SELECT L.* 
					FROM asistencia_lugar L									
					WHERE L.lugar_id = :lugar";	

			$datos = $this->ejecutarConsulta($consulta_datos, [':lugar' => (int)$lugarid]);		
			return $datos;
		}

		public function listarLugar(){			
			$tabla="";		
			$consulta_datos="SELECT ROW_NUMBER() OVER (ORDER BY L.lugar_id) AS fila_numero
					,CASE WHEN L.lugar_estado = 'A' THEN 'Activo' ELSE 'Inactivo' END ESTADO 
					,L.*
					,S.sede_nombre
				FROM asistencia_lugar L  
					INNER JOIN general_sede S ON S.sede_id = L.lugar_sedeid 
				where L.lugar_estado != 'E'
				ORDER BY L.lugar_id ASC";		

			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){			
				$tabla.='
					<tr>
						<td>'.$rows['fila_numero'].'</td>
						<td>'.$rows['sede_nombre'].'</td>
						<td>'.$rows['lugar_nombre'].'</td>
						<td>'.$rows['lugar_direccion'].'</td>
						<td><a href="'.$rows['lugar_detalle'].'" target="_blank">'.$rows['lugar_detalle'].'</a></td>
						<td>'.$rows['ESTADO'].'</td>
						<td>
							'.$this->siPuede('eliminar','asistenciaLugar','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_asistencia" value="eliminar_lugar">
								<input type="hidden" name="lugar_id" value="'.$rows['lugar_id'].'">						
								<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 5px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
							</form>').'							

							<a href="'.APP_URL.'asistenciaLugar/'.$rows['lugar_id'].'/" class="btn float-end btn-success btn-sm" style="margin-right: 5px;"  title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>
							
						</td>
					</tr>';	
			}
			return $tabla;			
		}
		public function listarOptionProfesor($lugar_sedeid, $profesorid){			
			$option="";
			$consulta_datos="SELECT empleado_id, empleado_nombre 
								FROM sujeto_empleado
								WHERE empleado_estado = 'A'";
							
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($profesorid == $rows["empleado_id"]){
					$option.='<option value='.$rows['empleado_id'].' selected>'.$rows['empleado_nombre'].'</option>';	
				}else{
					$option.='<option value='.$rows['empleado_id'].'>'.$rows['empleado_nombre'].'</option>';	
				}			
			}
			return $option;
		}

		public function listarDetalleHorario($horario_id){			
			$option="";
			$consulta_datos="SELECT  lugar_id, lugar_sedeid, detalle_horaid, empleado_id, detalle_dia	  
							FROM asistencia_horario_detalle
							LEFT JOIN asistencia_lugar ON lugar_id = detalle_lugarid
							LEFT JOIN asistencia_hora ON hora_id = detalle_horaid 
							LEFT JOIN sujeto_empleado ON empleado_id = detalle_profesorid	 
							WHERE detalle_horarioid = :horario
							ORDER BY detalle_dia";
							
			$datos = $this->ejecutarConsulta($consulta_datos, [':horario' => (int)$horario_id]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$LU = "";
				$MA = "";
				$MI = "";
				$JU = "";
				$VI = "";
				$SA = "";
				$DO = "";		

				switch ($rows["detalle_dia"]) {
					case 1:
						$dia = 'Lunes';
						$LU = "selected";
						break;
					case 2:
						$dia = 'Martes';
						$MA = "selected";
						break;
					case 3:
						$dia = 'Miércoles';
						$MI = "selected";
						break;
					case 4:
						$dia = 'Jueves';
						$JU = "selected";
						break;
					case 5:
						$dia = 'Viernes';
						$VI = "selected";
						break;
					case 6:
						$dia = 'Sábado';
						$SA = "selected";
						break;
					case 7:
						$dia = 'Domingo';
						$DO = "selected";
						break;
					default:
						$dia = 'Día desconocido';
				}

				// Columna 1: Días de la semana
				$column1 = "<select class='form-control' name='dia[]'>
							<option value='1' ".$LU.">Lunes</option>
							<option value='2' ".$MA.">Martes</option>
							<option value='3' ".$MI.">Miércoles</option>
							<option value='4' ".$JU.">Jueves</option>
							<option value='5' ".$VI.">Viernes</option>
							<option value='6' ".$SA.">Sábado</option>
							<option value='7' ".$DO.">Domingo</option>
							</select>";

				// Columna 2: Lugares de entrenamiento con PHP
				$column2 = "<select class='form-control' id='lugar' name='lugar[]'>".$this->listarOptionLugar($rows['lugar_sedeid'], $rows['lugar_id'])."</select>";
				
				// Columna 3: Horarios con PHP
				$column3 = "<select class='form-control' id='hora' name='hora[]'>".$this->listarOptionHora($rows['detalle_horaid'])."</select>";
				
				// Columna 4: Profesores con PHP
				$column4 = "<select class='form-control' id='profesor' name='profesor[]'>".$this->listarOptionProfesor($rows['lugar_sedeid'], $rows['empleado_id'])."</select>";
				
				$option.=		
					"<tr><td>".$column1."</td>
					<td>".$column2."</td>
					<td>".$column3."</td>
					<td>".$column4."</td>                  
					<td><button type='button' class='btn btn-danger btn-sm btn-icon icon-left btn_remove float-end'>Eliminar<i class='entypo-trash'></i></button></td></tr>";	
			}
			return $option;
		}

		public function informacionSede($sedeid){		
			$consulta_datos="SELECT * FROM general_sede WHERE sede_id = :sede";
			$datos = $this->ejecutarConsulta($consulta_datos, [':sede' => (int)$sedeid]);		
			return $datos;
		}

		public function generarHorario($horario_id){			
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
							WHERE horario_id = :horario
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
							WHERE horario_id = :horario
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
							WHERE horario_id = :horario
							GROUP BY Categoria";
		
							
			$datos = $this->ejecutarConsulta($consulta_datos, [':horario' => (int)$horario_id]);
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

		public function HorarioPDF($horario_id){		
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
							WHERE horario_id = :horario";		
							
			$datos = $this->ejecutarConsulta($consulta_datos, [':horario' => (int)$horario_id]);			
			return $datos;
		}

		public function CanchaPDF($horario_id){
			$consulta_datos = "SELECT 
								'Cancha' AS Categoria,
								MAX(CASE WHEN detalle_dia = 1 THEN lugar_nombre END) AS Lunes,
								MAX(CASE WHEN detalle_dia = 2 THEN lugar_nombre END) AS Martes,
								MAX(CASE WHEN detalle_dia = 3 THEN lugar_nombre END) AS Miercoles,
								MAX(CASE WHEN detalle_dia = 4 THEN lugar_nombre END) AS Jueves,
								MAX(CASE WHEN detalle_dia = 5 THEN lugar_nombre END) AS Viernes								
							FROM asistencia_horario 
							INNER JOIN asistencia_horario_detalle ON detalle_horarioid = horario_id 
							LEFT JOIN asistencia_lugar ON lugar_id = detalle_lugarid
							WHERE horario_id = :horario";		
							
			$datos = $this->ejecutarConsulta($consulta_datos, [':horario' => (int)$horario_id]);			
			return $datos;
		}

		public function ProfesorPDF($horario_id){			
			$consulta_datos = "SELECT 
								'Profesor' AS Categoria,
								MAX(CASE WHEN detalle_dia = 1 THEN empleado_nombre END) AS Lunes,
								MAX(CASE WHEN detalle_dia = 2 THEN empleado_nombre END) AS Martes,
								MAX(CASE WHEN detalle_dia = 3 THEN empleado_nombre END) AS Miercoles,
								MAX(CASE WHEN detalle_dia = 4 THEN empleado_nombre END) AS Jueves,
								MAX(CASE WHEN detalle_dia = 5 THEN empleado_nombre END) AS Viernes
							FROM asistencia_horario 
							INNER JOIN asistencia_horario_detalle ON detalle_horarioid = horario_id 
							LEFT JOIN sujeto_empleado ON empleado_id = detalle_profesorid	 
							WHERE horario_id = :horario";		
							
			$datos = $this->ejecutarConsulta($consulta_datos, [':horario' => (int)$horario_id]);	
			return $datos;
		}

		public function listarOptionSedebusqueda($sedeid){
			$option="";

			$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede";	
					
			$datos = $this->ejecutarConsulta($consulta_datos);
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

		public function listarOptionLugar($sedeid, $lugarid){
			$option="";
			$consulta_datos="SELECT lugar_id, lugar_sedeid, lugar_nombre 
								FROM asistencia_lugar 
								WHERE  lugar_estado = 'A' 
									AND lugar_sedeid = :sede";		

			$datos = $this->ejecutarConsulta($consulta_datos, [':sede' => (int)$sedeid]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if($lugarid != 0){
					if($lugarid==$rows['lugar_id']){
						$option.='<option value='.$rows['lugar_id'].' selected="selected">'.$rows['lugar_nombre'].'</option>';
					}else{
						$option.='<option value='.$rows['lugar_id'].'>'.$rows['lugar_nombre'].'</option>';	
					}
				}else{	
					if($sedeid==$rows['lugar_sedeid']){
						$option.='<option value='.$rows['lugar_id'].' selected="selected">'.$rows['lugar_nombre'].'</option>';
					}else{
						$option.='<option value='.$rows['lugar_id'].'>'.$rows['lugar_nombre'].'</option>';	
					}
				}
			}
			return $option;
		}

		public function listarOptionHora($horaid){
			$option="";
			$consulta_datos="SELECT hora_id, CONCAT(hora_detalle, ' | ', hora_inicio, ' - ', hora_fin) AS HORA FROM asistencia_hora WHERE hora_estado = 'A'";						
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){				
				if($horaid==$rows['hora_id']){
					$option.='<option value='.$rows['hora_id'].' selected>'.$rows['HORA'].'</option>';
				}else{
					$option.='<option value='.$rows['hora_id'].'>'.$rows['HORA'].'</option>';	
				}			
			}
			return $option;
		}

		public function registrarLugarControlador(){		

			# Almacenando datos#			
			$lugar_sedeid  	= $this->limpiarCadena($_POST['lugar_sedeid']);
			$lugar_nombre	= $this->limpiarCadena($_POST['lugar_nombre']);
			$lugar_direccion= $this->limpiarCadena($_POST['lugar_direccion']);
			$lugar_detalle	= $this->limpiarCadena($_POST['lugar_detalle']);			
			
			# Verificando campos obligatorios #
		    if($lugar_sedeid=="" || $lugar_nombre=="" || $lugar_direccion==""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }
			$lugar_datos_reg=[
				[
					"campo_nombre"=>"lugar_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$lugar_sedeid
				],
				[
					"campo_nombre"=>"lugar_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$lugar_nombre
				],				
				[
					"campo_nombre"=>"lugar_direccion",
					"campo_marcador"=>":Direccion",
					"campo_valor"=>$lugar_direccion
				],	
				[
					"campo_nombre"=>"lugar_detalle",
					"campo_marcador"=>":Detalle",
					"campo_valor"=>$lugar_detalle
				],			
				[
					"campo_nombre"=>"lugar_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>"A"
				]
			];		

			$registrar_hora=$this->guardarDatos("asistencia_lugar",$lugar_datos_reg);

			if($registrar_hora->rowCount()>0){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Registro exitoso",
					"texto"=>"Lugar de entrenamiento registrado correctamente",
					"icono"=>"success"
				];				
			
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No se pudo registrar el lugar de entrenamiento, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function listarHorarios($horario_nombre, $horario_detalle, $horario_sedeid){					
			if($horario_nombre!=""){
				$horario_nombre .= '%'; 
			}
			if($horario_detalle!=""){
				$horario_detalle .= '%';
			} 	

			$tabla="";
			/* El array se arma a la par que la consulta: cada rama
			   registra solo lo que su SQL usa. */
			$parametros = [];
			$consulta_datos="SELECT distinct AH.*, IFNULL(TOTAL.TOTAL,0) ALUMNOS 
								FROM asistencia_horario AH
									INNER JOIN asistencia_horario_detalle on detalle_horarioid = horario_id
										LEFT JOIN(
												SELECT asignahorario_horarioid HORARIOID, count(1) TOTAL
												FROM asistencia_asignahorario
												GROUP BY asignahorario_horarioid
										)TOTAL ON TOTAL.HORARIOID = AH.horario_id 
								WHERE (horario_nombre LIKE :nombre 
								OR horario_detalle LIKE :detalle) ";			
			$parametros[':nombre']  = $horario_nombre;
			$parametros[':detalle'] = $horario_detalle;

			if($horario_nombre=="" && $horario_detalle=="" ){
				$consulta_datos="SELECT distinct AH.*, IFNULL(TOTAL.TOTAL,0) ALUMNOS
									FROM asistencia_horario AH
										INNER JOIN asistencia_horario_detalle on detalle_horarioid = horario_id
										LEFT JOIN(
												SELECT asignahorario_horarioid HORARIOID, count(1) TOTAL
												FROM asistencia_asignahorario
												GROUP BY asignahorario_horarioid
										)TOTAL ON TOTAL.HORARIOID = AH.horario_id
									WHERE horario_nombre <> '' ";
				$parametros = [];
			}

			if($horario_sedeid!=""){
				if($horario_sedeid == 0){
					$consulta_datos .= " and horario_sedeid <> :sede"; 
					$parametros[':sede'] = $horario_sedeid;
				}else{
					$consulta_datos .= " and horario_sedeid = :sede"; 
					$parametros[':sede'] = $horario_sedeid;
				}
			}else{
				$consulta_datos = "SELECT distinct AH.*, IFNULL(TOTAL.TOTAL,0) ALUMNOS
									FROM asistencia_horario AH
										INNER JOIN asistencia_horario_detalle on detalle_horarioid = horario_id
										LEFT JOIN(
												SELECT asignahorario_horarioid HORARIOID, count(1) TOTAL
												FROM asistencia_asignahorario
												GROUP BY asignahorario_horarioid
										)TOTAL ON TOTAL.HORARIOID = AH.horario_id
									WHERE horario_nombre = '' ";
				$parametros = [];
			}			

			$consulta_datos .= " AND horario_estado <> 'E'"; 
			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if ($rows['horario_estado'] == 'A'){
					$estado = 'Activo';
					$class = '';
				}elseif($rows['horario_estado'] == 'E'){
					$estado = '<span class="badge bg-danger">ELIMINADO';
					$class = 'class="text-danger"';
				}elseif($rows['horario_estado'] == 'I'){
					$estado = 'Inactivo';
					$class = 'class="text-primary"';
				}				
				$tabla.='
					<tr '.$class.'>
						<td>'.$rows['horario_nombre'].'</td>
						<td>'.$rows['horario_detalle'].'</td>	
						<td>'.$estado.'</td>
						<td>'.$rows['ALUMNOS'].'</td>
						<td>							
							<a href="'.APP_URL.'asistenciaHorarioJugador/'.$rows['horario_id'].'/'.$horario_sedeid.'/" target="_blank" class="btn float-end btn-warning btn-sm" style="margin-right: 5px;"><i class="fas fa-link me-1"></i>Asignar alumnos</a>
							<a href="'.APP_URL.'asistenciaHorarioLista/'.$rows['horario_id'].'/" target="_blank" class="btn float-end btn-ver btn-sm" style="margin-right: 5px;"><i class="fas fa-list me-1"></i>Ver lista</a>
						</td>
						<td>
							'.$this->siPuede('eliminar','asistenciaListHorario','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" >
								<input type="hidden" name="modulo_asistencia" value="eliminar_horario">
								<input type="hidden" name="horario_id" value="'.$rows['horario_id'].'">						
								<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 5px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>
							</form>').'	

							<a href="'.APP_URL.'asistenciaHorario/'.$rows['horario_id'].'/" target="_blank" class="btn float-end btn-actualizar btn-sm" style="margin-right: 5px;" title="Editar" aria-label="Editar"><i class="fas fa-pen"></i></a>
							<a href="'.APP_URL.'asistenciaVerHorario/'.$rows['horario_id'].'/" target="_blank" class="btn float-end btn-ver btn-sm" style="margin-right: 5px;" title="Ver" aria-label="Ver"><i class="fas fa-eye"></i></a>
						</td>
					</tr>';	
			}
			return $tabla;			
		}

		public function listarHorariosProfesor($profesor_id){					
			$tabla="";
			/* La rama del administrador no usa :profesor, asi que el
			   parametro solo acompana a la que si lo lleva. */
			$parametros=[];
			if ($_SESSION['rol'] <> 1 && $_SESSION['rol'] <> 2){
				$consulta_datos="SELECT distinct AH.*, IFNULL(TOTAL.TOTAL,0) ALUMNOS, sede_nombre, lugar_nombre
									FROM asistencia_horario AH
											LEFT JOIN(
													SELECT asignahorario_horarioid HORARIOID, count(1) TOTAL
													FROM asistencia_asignahorario 
														INNER JOIN sujeto_alumno A ON A.alumno_id = asignahorario_alumnoid	
													WHERE A.alumno_estado = 'A'
													GROUP BY asignahorario_horarioid
											)TOTAL ON TOTAL.HORARIOID = AH.horario_id
									INNER JOIN general_sede on AH.horario_sedeid = sede_id
									INNER JOIN asistencia_lugar on AH.horario_sedeid = lugar_sedeid
									INNER JOIN asistencia_horario_detalle on detalle_horarioid = AH.horario_id
									WHERE AH.horario_estado <> 'E'
										AND detalle_lugarid = lugar_id
										AND detalle_profesorid = :profesor";	
				$parametros[':profesor'] = (int)$profesor_id;
			} else{
				$consulta_datos="SELECT distinct AH.*, IFNULL(TOTAL.TOTAL,0) ALUMNOS, sede_nombre, lugar_nombre
									FROM asistencia_horario AH
											LEFT JOIN(
													SELECT asignahorario_horarioid HORARIOID, count(1) TOTAL
													FROM asistencia_asignahorario
														INNER JOIN sujeto_alumno A ON A.alumno_id = asignahorario_alumnoid	
													WHERE A.alumno_estado = 'A'
													GROUP BY asignahorario_horarioid
											)TOTAL ON TOTAL.HORARIOID = AH.horario_id
									INNER JOIN general_sede on AH.horario_sedeid = sede_id
									INNER JOIN asistencia_lugar on AH.horario_sedeid = lugar_sedeid
									INNER JOIN asistencia_horario_detalle on detalle_horarioid = AH.horario_id
									WHERE AH.horario_estado <> 'E'
										AND detalle_lugarid = lugar_id";	
			}

			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				if ($rows['horario_estado'] == 'A'){
					$estado = 'Activo';
					$class = '';
				}elseif($rows['horario_estado'] == 'E'){
					$estado = '<span class="badge bg-danger">ELIMINADO';
					$class = 'class="text-danger"';
				}elseif($rows['horario_estado'] == 'I'){
					$estado = 'Inactivo';
					$class = 'class="text-primary"';
				}				
				$tabla.='
					<tr '.$class.'>
						<td>'.$rows['sede_nombre'].'</td>
						<td>'.$rows['lugar_nombre'].'</td>
						<td>'.$rows['horario_nombre'].'</td>
						<td>'.$rows['horario_detalle'].'</td>						
						<td>'.$rows['ALUMNOS'].'</td>						
						<td>
							<a href="'.APP_URL.'asistenciaAlumno/'.$rows['horario_id'].'/" target="_blank" class="btn float-end btn-warning btn-sm"><i class="fas fa-list me-1"></i>Listado de alumnos</a>
						</td>
					</tr>';	
			}
			return $tabla;			
		}
		public function actualizarLugarControlador(){			
			$lugarid =$this->limpiarCadena($_POST['lugar_id']);

			# Verificando pago #
			$datos = $this->ejecutarConsulta("SELECT lugar_id FROM asistencia_lugar WHERE lugar_id = :lugar", [':lugar' => (int)$lugarid]);			
			if($datos->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No se encuentra la hora en el sistema: ".$lugarid,
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
		    	$datos=$datos->fetch();				
		    }				

			# Almacenando datos#
			$lugar_sedeid  	= $this->limpiarCadena($_POST['lugar_sedeid']);
			$lugar_nombre	= $this->limpiarCadena($_POST['lugar_nombre']);
			$lugar_direccion= $this->limpiarCadena($_POST['lugar_direccion']);
			$lugar_detalle	= $this->limpiarCadena($_POST['lugar_detalle']);
			$lugar_estado	= $this->limpiarCadena($_POST['estado']);
			
			# Verificando campos obligatorios #
		    if($lugar_sedeid=="" || $lugar_nombre=="" || $lugar_direccion==""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);		        
		    }			

			$lugar_datos_reg=[
				[
					"campo_nombre"=>"lugar_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$lugar_sedeid
				],
				[
					"campo_nombre"=>"lugar_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$lugar_nombre
				],				
				[
					"campo_nombre"=>"lugar_direccion",
					"campo_marcador"=>":Direccion",
					"campo_valor"=>$lugar_direccion
				],	
				[
					"campo_nombre"=>"lugar_detalle",
					"campo_marcador"=>":Detalle",
					"campo_valor"=>$lugar_detalle
				],			
				[
					"campo_nombre"=>"lugar_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>$lugar_estado
				]
			];			
		
			$condicion=[
				"condicion_campo"=>"lugar_id",
				"condicion_marcador"=>":Lugarid",
				"condicion_valor"=>$lugarid
			];			

			if($this->actualizarDatos("asistencia_lugar",$lugar_datos_reg,$condicion)){				
				$alerta=[
					"tipo"=>"redireccionar",			
					"url"=>APP_URL.'asistenciaLugar/',					
					"titulo"=>"Lugar actualizado",
					"texto"=>"Los datos del lugar ".$lugar_nombre." se actualizaron correctamente",
					"icono"=>"success"	
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible actualizar los datos de la hora ".$lugarid.", por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function eliminarLugarControlador(){			
			$lugarid=$this->limpiarCadena($_POST['lugar_id']);
			$lugar_datos=[
				[
					"campo_nombre"=>"lugar_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=> 'E'
				]
			];
			$condicion=[
				"condicion_campo"=>"lugar_id",
				"condicion_marcador"=>":Lugarid",
				"condicion_valor"=>$lugarid
			];
			if($this->actualizarDatos("asistencia_lugar", $lugar_datos, $condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Lugar eliminado",
					"texto"=>"El lugar fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible eliminar el lugar, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function registrarHorario(){				
			# Almacenando datos 			
			$lugar_sedeid		= $this->limpiarCadena($_POST['lugar_sedeid']);
			$horario_nombre		= $this->limpiarCadena($_POST['horario_nombre']);
			$horario_detalle	= $this->limpiarCadena($_POST['horario_detalle']);

			# Verificando campos obligatorios #
		    if($lugar_sedeid=="" || $horario_nombre=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }		

			$horario_datos_reg=[
				[
					"campo_nombre"=>"horario_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$lugar_sedeid
				],
				[
					"campo_nombre"=>"horario_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$horario_nombre
				],				
				[
					"campo_nombre"=>"horario_detalle",
					"campo_marcador"=>":Detalle",
					"campo_valor"=>$horario_detalle
				],				
				[
					"campo_nombre"=>"horario_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>'A'
				]
			];   	    		

			$registrar_hora=$this->guardarDatos("asistencia_horario",$horario_datos_reg);

			if($registrar_hora->rowCount()>0){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Registro correcto",
					"texto"=>"El horario se registró correctamente",
					"icono"=>"success"
				];	

				$detalle=$this->ejecutarConsulta("SELECT max(horario_id) HORARIO FROM asistencia_horario");// WHERE horario_nombre='$horario_nombre'");
				if($detalle->rowCount()>0){
					$detalle=$detalle->fetch();
					$horario_id=$detalle['HORARIO'];

					$dias = $_POST['dia'];
					$lugares = $_POST['lugar'];
					$horas = $_POST['hora'];
					$profesores = $_POST['profesor'];
			
					for ($i = 0; $i < count($dias); $i++) { 

						$horario_detalle_reg = [
							[
								"campo_nombre" => "detalle_horarioid",
								"campo_marcador" => ":Horarioid",
								"campo_valor" => $horario_id
							],
							[
								"campo_nombre" => "detalle_lugarid",
								"campo_marcador" => ":Lugarid",
								"campo_valor" => $lugares[$i]
							],
							[
								"campo_nombre" => "detalle_horaid",
								"campo_marcador" => ":Horaid",
								"campo_valor" => $horas[$i]
							],
							[
								"campo_nombre" => "detalle_profesorid",
								"campo_marcador" => ":Profesorid",
								"campo_valor" => $profesores[$i]
							],
							[
								"campo_nombre" => "detalle_dia",
								"campo_marcador" => ":Dia",
								"campo_valor" => $dias[$i]
							]
						];				
						$this->guardarDatos("asistencia_horario_detalle",$horario_detalle_reg);
					}	
				}
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible registrar el horario, por favor intente nuevamente",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		}

		public function actualizarHorario(){			
			# Almacenando datos 			
			$lugar_sedeid		= $this->limpiarCadena($_POST['lugar_sedeid']);
			$horario_nombre		= $this->limpiarCadena($_POST['horario_nombre']);
			$horario_detalle	= $this->limpiarCadena($_POST['horario_detalle']);
			$horario_id			= $this->limpiarCadena($_POST['horario_id']);	
			
			# Verificando campos obligatorios #
		    if($lugar_sedeid=="" || $horario_nombre=="" ){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No ha completado todos los campos obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }		

			$horario_datos_reg=[
				[
					"campo_nombre"=>"horario_sedeid",
					"campo_marcador"=>":Sedeid",
					"campo_valor"=>$lugar_sedeid
				],
				[
					"campo_nombre"=>"horario_nombre",
					"campo_marcador"=>":Nombre",
					"campo_valor"=>$horario_nombre
				],				
				[
					"campo_nombre"=>"horario_detalle",
					"campo_marcador"=>":Detalle",
					"campo_valor"=>$horario_detalle
				],				
				[
					"campo_nombre"=>"horario_estado",
					"campo_marcador"=>":Estado",
					"campo_valor"=>'A'
				]
			];   

			$condicion=[
				"condicion_campo"=>"horario_id",
				"condicion_marcador"=>":Horarioid",
				"condicion_valor"=>$horario_id
			];
			if($this->actualizarDatos("asistencia_horario", $horario_datos_reg, $condicion)){				
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Horario actualizado",
					"texto"=>"El horario se actualizó correctamente",
					"icono"=>"success"
				];

				$detalle=$this->ejecutarConsulta("SELECT detalle_horarioid FROM asistencia_horario_detalle WHERE detalle_horarioid = :horario", [':horario' => (int)$horario_id]);
				if($detalle->rowCount()>0){
					$this->eliminarRegistro("asistencia_horario_detalle","detalle_horarioid",$horario_id);					
				}	

				$dias = $_POST['dia'];
				$lugares = $_POST['lugar'];
				$horas = $_POST['hora'];
				$profesores = $_POST['profesor'];
		
				for ($i = 0; $i < count($dias); $i++) {
					$horario_detalle_reg = [
						[
							"campo_nombre" => "detalle_horarioid",
							"campo_marcador" => ":Horarioid",
							"campo_valor" => $horario_id
						],
						[
							"campo_nombre" => "detalle_lugarid",
							"campo_marcador" => ":Lugarid",
							"campo_valor" => $lugares[$i]
						],
						[
							"campo_nombre" => "detalle_horaid",
							"campo_marcador" => ":Horaid",
							"campo_valor" => $horas[$i]
						],
						[
							"campo_nombre" => "detalle_profesorid",
							"campo_marcador" => ":Profesorid",
							"campo_valor" => $profesores[$i]
						],
						[
							"campo_nombre" => "detalle_dia",
							"campo_marcador" => ":Dia",
							"campo_valor" => $dias[$i]
						]
					];			
					$this->guardarDatos("asistencia_horario_detalle",$horario_detalle_reg);
				}

			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible actualizar los datos del horario: ".$horario_id.", por favor intente nuevamente",
					"icono"=>"success"
				];
			}
			return json_encode($alerta);
		}

		public function eliminarHorario(){
			$horarioid=$this->limpiarCadena($_POST['horario_id']);

			/* Un horario con alumnos asignados no se puede eliminar: la base
			   lo impide con la clave foránea fk_asigna_horario. Se comprueba
			   antes para dar un mensaje claro en lugar de un error SQL.
			   Sin esta comprobación quedaban asignaciones apuntando a un
			   horario inexistente, que es lo que se encontró al declarar las
			   restricciones. */
			$asignados = $this->ejecutarConsulta(
				"SELECT COUNT(1) AS n FROM asistencia_asignahorario
				  WHERE asignahorario_horarioid = :h", [':h' => $horarioid])->fetch();

			if((int)($asignados['n'] ?? 0) > 0){
				return json_encode([
					"tipo"   => "simple",
					"titulo" => "Horario con alumnos",
					"texto"  => "No se puede eliminar: tiene ".$asignados['n']." alumno(s) asignado(s). Retírelos primero.",
					"icono"  => "error"
				]);
			}

			/* Los detalles sí se arrastran: no significan nada sin su
			   horario. La base los borra en cascada (fk_detalle_horario),
			   pero se eliminan aquí para dejar el flujo explícito. */
			$this->eliminarRegistro("asistencia_horario_detalle", "detalle_horarioid", $horarioid);

			$eliminahorario=$this->eliminarRegistro("asistencia_horario", "horario_id", $horarioid);

			if($eliminahorario->rowCount()>0){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Horario eliminado",
					"texto"=>"El horario fue eliminado correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible eliminar el horario, por favor intente nuevamente",
					"icono"=>"error"
				];
			}

			return json_encode($alerta);
		}

		//-------------------------------------------------Asignar alumnos--------------------------------------
		public function listarAlumnos($horario_id, $sede){	
			// Preparar condiciones dinámicas
			$tabla="";
			$condiciones = [];
			
			$condiciones[] = "A.alumno_estado = 'A' AND A.alumno_sedeid = :sede AND A.alumno_id NOT IN (SELECT asignahorario_alumnoid FROM asistencia_asignahorario)";

			$consulta_datos = "SELECT S.sede_nombre, A.* FROM sujeto_alumno A
							   INNER JOIN general_sede S ON S.sede_id = A.alumno_sedeid
							   WHERE " . implode(" AND ", $condiciones);
			
			// Ejecutar consulta			
			$datos = $this->ejecutarConsulta($consulta_datos, [':sede' => (int)$sede]);
			$datos = $datos->fetchAll();

			foreach($datos as $rows){
				$tabla.='					
					<tr>
						'.$this->siPuede('crear','asistencia','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" data-recargar-directo>
						<td>'.$rows['sede_nombre'].'</td>
						<td><input type="hidden" name="alumno_id" value="'.$rows['alumno_id'].'">'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['alumno_primernombre'].' '.$rows['alumno_segundonombre'].' '.$rows['alumno_apellidopaterno'].' '.$rows['alumno_apellidomaterno'].'</td>
						<td>'.$rows['alumno_fechanacimiento'].'</td>
						<td>												
							<input type="hidden" name="modulo_asistencia" value="asignar_alumno">	
							<input type="hidden" name="horario_id" value="'.$horario_id.'">					
							<button type="submit" class="btn float-end btn-actualizar btn-sm" style="margin-right: 5px;"><i class="fas fa-plus me-1"></i>Agregar</button>					
						</td>
						</form>').'
					</tr>
					';
			}
			return $tabla;			
		}

		public function buscarHorario($horario_id){
			$consulta_datos="SELECT AH.horario_id, CONCAT(HORA.hora_inicio, ' - ', HORA.hora_fin, ' - ', AH.horario_detalle) AS HORARIO
								FROM asistencia_horario AH
									INNER JOIN( 
										SELECT detalle_horarioid, detalle_horaid, H.hora_inicio, H.hora_fin
										FROM asistencia_horario_detalle D
										INNER JOIN asistencia_hora H on H.hora_id = D.detalle_horaid
										GROUP BY detalle_horarioid, detalle_horaid, H.hora_inicio, H.hora_fin
									)HORA ON HORA.detalle_horarioid = AH.horario_id

								WHERE AH.horario_estado = 'A' AND AH.horario_id = :horario";

			$datos = $this->ejecutarConsulta($consulta_datos, [':horario' => (int)$horario_id]);		
			return $datos;
		}

		public function BuscarSede($sede_id){
			$consulta_datos="SELECT sede_nombre FROM general_sede WHERE sede_id = :sede";	

			$datos = $this->ejecutarConsulta($consulta_datos, [':sede' => (int)$sede_id]);		
			return $datos;
		}

		public function asignarAlumno(){	
			# Almacenando datos 			
			$horario_id = $this->limpiarCadena($_POST['horario_id']);
			$alumno_id = $_POST['alumno_id'];

			
			# Verificando campos obligatorios #
			if($horario_id=="" || $alumno_id== ""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No existe información de la asignación de horario al alumno",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }				
			$asignacion_horario_reg = [
				[
					"campo_nombre" => "asignahorario_horarioid",
					"campo_marcador" => ":Horarioid",
					"campo_valor" => $horario_id
				],
				[
					"campo_nombre" => "asignahorario_alumnoid",
					"campo_marcador" => ":Alumnoid",
					"campo_valor" => $alumno_id
				]
			];
			
			$asignar_horario=$this->guardarDatos("asistencia_asignahorario",$asignacion_horario_reg);
			
			if($asignar_horario->rowCount()==1){
				$alerta=[
					"tipo"=>"recargar_directo"
				];
				/*	
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Asignación correcta",
					"texto"=>"El alumno fue agregado correctamente al horario seleccionado",
					"icono"=>"success"
				];
				*/
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible agregar el alumno al horario seleccionado",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);			
		}

		public function ListaAlumnosHorario($horarioid){			
			$tabla="";
			$consulta_datos = "SELECT 
										A.alumno_identificacion, 
										CONCAT(A.alumno_primernombre, ' ',A.alumno_segundonombre) AS NOMBRES,  
									CONCAT(A.alumno_apellidopaterno, ' ',A.alumno_apellidomaterno) AS APELLIDOS,
										YEAR(A.alumno_fechanacimiento) AS CATEGORIA, H.*
								FROM asistencia_asignahorario H
										INNER JOIN sujeto_alumno A ON A.alumno_id = H.asignahorario_alumnoid
								WHERE H.asignahorario_horarioid = :horario";
			
			$datos = $this->ejecutarConsulta($consulta_datos, [':horario' => (int)$horarioid]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$tabla.='					
					<tr>
						'.$this->siPuede('eliminar','asistencia','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" >
						<td><input type="hidden" name="asignahorario_alumnoid" value="'.$rows['asignahorario_alumnoid'].'">'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['NOMBRES'].'</td>
						<td>'.$rows['APELLIDOS'].'</td>
						<td>'.$rows['CATEGORIA'].'</td>
						<td>												
							<input type="hidden" name="modulo_asistencia" value="eliminar_alumnolista">												
							<button type="submit" class="btn float-end btn-danger btn-sm" style="margin-right: 5px;" title="Eliminar" aria-label="Eliminar"><i class="fas fa-trash"></i></button>					
						</td>
						</form>').'
					</tr>
					';
			}
			return $tabla;			
		}

		
		/*
		public function ListadoAlumnos($horarioid, $fecha){			
			$tabla="";
			
			$dateTime = new DateTime($fecha);
			$fecha_formateada = $dateTime->format("d-m-Y");

			$anio 		= date('Y', strtotime($fecha)); // Obtiene el año
			$mes 		= date('m', strtotime($fecha));  // Obtiene el mes
			$dia 		= date('d', strtotime($fecha)); // Obtiene el día		
			
			$consulta_datos = "SELECT 
									A.alumno_id, A.alumno_identificacion, 
									CONCAT(A.alumno_primernombre, ' ',A.alumno_segundonombre) AS NOMBRES,  
									CONCAT(A.alumno_apellidopaterno, ' ',A.alumno_apellidomaterno) AS APELLIDOS,
									YEAR(A.alumno_fechanacimiento) AS CATEGORIA, H.*,
									CASE 
										WHEN $dia = 1 THEN E.asistencia_D01
										WHEN $dia = 2 THEN E.asistencia_D02
										WHEN $dia = 3 THEN E.asistencia_D03
										WHEN $dia = 4 THEN E.asistencia_D04
										WHEN $dia = 5 THEN E.asistencia_D05
										WHEN $dia = 6 THEN E.asistencia_D06
										WHEN $dia = 7 THEN E.asistencia_D07
										WHEN $dia = 8 THEN E.asistencia_D08
										WHEN $dia = 9 THEN E.asistencia_D09
										WHEN $dia = 10 THEN E.asistencia_D10
										WHEN $dia = 11 THEN E.asistencia_D11
										WHEN $dia = 12 THEN E.asistencia_D12
										WHEN $dia = 13 THEN E.asistencia_D13
										WHEN $dia = 14 THEN E.asistencia_D14
										WHEN $dia = 15 THEN E.asistencia_D15
										WHEN $dia = 16 THEN E.asistencia_D16
										WHEN $dia = 17 THEN E.asistencia_D17
										WHEN $dia = 18 THEN E.asistencia_D18
										WHEN $dia = 19 THEN E.asistencia_D19
										WHEN $dia = 20 THEN E.asistencia_D20
										WHEN $dia = 21 THEN E.asistencia_D21
										WHEN $dia = 22 THEN E.asistencia_D22
										WHEN $dia = 23 THEN E.asistencia_D23
										WHEN $dia = 24 THEN E.asistencia_D24
										WHEN $dia = 25 THEN E.asistencia_D25
										WHEN $dia = 26 THEN E.asistencia_D26
										WHEN $dia = 27 THEN E.asistencia_D27
										WHEN $dia = 28 THEN E.asistencia_D28
										WHEN $dia = 29 THEN E.asistencia_D29
										WHEN $dia = 30 THEN E.asistencia_D30
										WHEN $dia = 31 THEN E.asistencia_D31
									END AS asistencia_dia

								FROM asistencia_asignahorario H
									INNER JOIN sujeto_alumno A ON A.alumno_id = H.asignahorario_alumnoid	
									LEFT JOIN (
										SELECT * 
										FROM asistencia_asistencia 
										WHERE asistencia_aniomes = $anio$mes
									) E ON E.asistencia_alumnoid = H.asignahorario_alumnoid								
								WHERE H.asignahorario_horarioid = $horarioid AND A.alumno_estado = 'A'
								ORDER BY asistencia_dia, APELLIDOS";
			
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$btn_j = $btn_f = $btn_a = $btn_p = 'btn-dark';
			switch ($rows['asistencia_dia']) {
				case 'J':
					$btn_j = 'btn-info';
					break;
				case 'F':
					$btn_f = 'btn-info';
					break;
				case 'A':
					$btn_a = 'btn-info';
					break;
				case 'P':
					$btn_p = 'btn-info';
					break;
				default:
					$btn_j = $btn_f = $btn_a = $btn_p = 'btn-dark';
					$rows['asistencia_dia'] = 'NR';
					break;
			}	
				
				$tabla.='					
					<tr>
						'.$this->siPuede('crear','asistencia','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" data-recargar-directo>
						<td>'.$rows['CATEGORIA'].'</td>
						<td><input type="hidden" name="asignahorario_alumnoid" value="'.$rows['asignahorario_alumnoid'].'">'.$rows['APELLIDOS'].' '.$rows['NOMBRES'].'</td>
											
						<td style="width: 220px;">
							<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" data-recargar-directo>
								<input type="hidden" name="modulo_asistencia" value="asistencia">
								<input type="hidden" name="estado" value="J">
								<input type="hidden" name="fecha" value="'.$fecha_formateada.'">
								<input type="hidden" name="alumno_id" value="'.$rows['alumno_id'].'">						
								<button type="submit" class="btn float-end '.$btn_j.' btn-sm" style="margin-right: 5px;">Justificado</button>
							</form>').'
							'.$this->siPuede('crear','asistencia','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" data-recargar-directo>
								<input type="hidden" name="modulo_asistencia" value="asistencia">
								<input type="hidden" name="estado" value="F">
								<input type="hidden" name="fecha" value="'.$fecha_formateada.'">
								<input type="hidden" name="alumno_id" value="'.$rows['alumno_id'].'">						
								<button type="submit" class="btn float-end '.$btn_f.' btn-sm" style="margin-right: 5px;">Falta</button>
							</form>').'
							'.$this->siPuede('crear','asistencia','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" data-recargar-directo>
								<input type="hidden" name="modulo_asistencia" value="asistencia">	
								<input type="hidden" name="estado" value="A">
								<input type="hidden" name="fecha" value="'.$fecha_formateada.'">
								<input type="hidden" name="alumno_id" value="'.$rows['alumno_id'].'">						
								<button type="submit" class="btn float-end '.$btn_a.' btn-sm" style="margin-right: 5px;">Atraso</button>
							</form>').'
							'.$this->siPuede('crear','asistencia','<form class="FormularioAjax" action="'.APP_URL.'app/ajax/asistenciaAjax.php" method="POST" autocomplete="off" data-recargar-directo>
								<input type="hidden" name="modulo_asistencia" value="asistencia">	
								<input type="hidden" name="estado" value="P">
								<input type="hidden" name="fecha" value="'.$fecha_formateada.'">
								<input type="hidden" name="alumno_id" value="'.$rows['alumno_id'].'">						
								<button type="submit" class="btn float-end '.$btn_p.' btn-sm" style="margin-right: 5px;">Presente</button>
							</form>').'
						</td>						
					</tr>
					';
			}
			return $tabla;			
		}
		*/

		public function ListadoAlumnos($horarioid, $fecha){			
			$tabla="";
			
			$dateTime = new DateTime($fecha);
			$fecha_formateada = $dateTime->format("d-m-Y");

			$anio 		= date('Y', strtotime($fecha)); // Obtiene el año
			$mes 		= date('m', strtotime($fecha));  // Obtiene el mes
			$dia 		= date('d', strtotime($fecha)); // Obtiene el día		
			
			$consulta_datos = "SELECT 
									A.alumno_id, A.alumno_identificacion, 
									CONCAT(A.alumno_primernombre, ' ',A.alumno_segundonombre) AS NOMBRES,  
									CONCAT(A.alumno_apellidopaterno, ' ',A.alumno_apellidomaterno) AS APELLIDOS,
									YEAR(A.alumno_fechanacimiento) AS CATEGORIA, H.*,
									CASE 
										WHEN $dia = 1 THEN E.asistencia_D01
										WHEN $dia = 2 THEN E.asistencia_D02
										WHEN $dia = 3 THEN E.asistencia_D03
										WHEN $dia = 4 THEN E.asistencia_D04
										WHEN $dia = 5 THEN E.asistencia_D05
										WHEN $dia = 6 THEN E.asistencia_D06
										WHEN $dia = 7 THEN E.asistencia_D07
										WHEN $dia = 8 THEN E.asistencia_D08
										WHEN $dia = 9 THEN E.asistencia_D09
										WHEN $dia = 10 THEN E.asistencia_D10
										WHEN $dia = 11 THEN E.asistencia_D11
										WHEN $dia = 12 THEN E.asistencia_D12
										WHEN $dia = 13 THEN E.asistencia_D13
										WHEN $dia = 14 THEN E.asistencia_D14
										WHEN $dia = 15 THEN E.asistencia_D15
										WHEN $dia = 16 THEN E.asistencia_D16
										WHEN $dia = 17 THEN E.asistencia_D17
										WHEN $dia = 18 THEN E.asistencia_D18
										WHEN $dia = 19 THEN E.asistencia_D19
										WHEN $dia = 20 THEN E.asistencia_D20
										WHEN $dia = 21 THEN E.asistencia_D21
										WHEN $dia = 22 THEN E.asistencia_D22
										WHEN $dia = 23 THEN E.asistencia_D23
										WHEN $dia = 24 THEN E.asistencia_D24
										WHEN $dia = 25 THEN E.asistencia_D25
										WHEN $dia = 26 THEN E.asistencia_D26
										WHEN $dia = 27 THEN E.asistencia_D27
										WHEN $dia = 28 THEN E.asistencia_D28
										WHEN $dia = 29 THEN E.asistencia_D29
										WHEN $dia = 30 THEN E.asistencia_D30
										WHEN $dia = 31 THEN E.asistencia_D31
									END AS asistencia_dia

								FROM asistencia_asignahorario H
									INNER JOIN sujeto_alumno A ON A.alumno_id = H.asignahorario_alumnoid	
									LEFT JOIN (
										SELECT * 
										FROM asistencia_asistencia 
										WHERE asistencia_aniomes = $anio$mes
									) E ON E.asistencia_alumnoid = H.asignahorario_alumnoid								
								WHERE H.asignahorario_horarioid = $horarioid AND A.alumno_estado = 'A'
								ORDER BY APELLIDOS";
			
			$datos = $this->ejecutarConsulta($consulta_datos);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$btn_j = $btn_f = $btn_a = $btn_p = 'btn-dark';
				switch ($rows['asistencia_dia']) {
					case 'J':
						$btn_j = 'btn-info';
						break;
					case 'F':
						$btn_f = 'btn-info';
						break;
					case 'A':
						$btn_a = 'btn-info';
						break;
					case 'P':
						$btn_p = 'btn-info';
						break;
					default:
						$btn_j = $btn_f = $btn_a = $btn_p = 'btn-dark';
						$rows['asistencia_dia'] = 'NR';
						break;
				}	
				
				$tabla .= '					
							<tr>
								<td>'.$rows['CATEGORIA'].'</td>
								<td>'.$rows['APELLIDOS'].' '.$rows['NOMBRES'].'</td>
								<td style="width: 1px; white-space: nowrap;">
					<div class="btn-group btn-group-sm" role="group" aria-label="Estado de asistencia">
									<button type="button" class="btn '.$btn_p.' btn-asistencia" aria-pressed="'.($btn_p === 'btn-info' ? 'true' : 'false').'" data-estado="P" data-alumnoid="'.$rows['alumno_id'].'" data-fecha="'.$fecha_formateada.'">Presente</button>
									<button type="button" class="btn '.$btn_a.' btn-asistencia" aria-pressed="'.($btn_a === 'btn-info' ? 'true' : 'false').'" data-estado="A" data-alumnoid="'.$rows['alumno_id'].'" data-fecha="'.$fecha_formateada.'">Atraso</button>
									<button type="button" class="btn '.$btn_f.' btn-asistencia" aria-pressed="'.($btn_f === 'btn-info' ? 'true' : 'false').'" data-estado="F" data-alumnoid="'.$rows['alumno_id'].'" data-fecha="'.$fecha_formateada.'">Falta</button>
									<button type="button" class="btn '.$btn_j.' btn-asistencia" aria-pressed="'.($btn_j === 'btn-info' ? 'true' : 'false').'" data-estado="J" data-alumnoid="'.$rows['alumno_id'].'" data-fecha="'.$fecha_formateada.'">Justificado</button>								
								</div>								
								</td>
							</tr>';

			}
			return $tabla;			
		}

		public function BuscarHorarioSede($horario_id){
			$consulta_datos="SELECT S.sede_nombre, H.* 
								FROM asistencia_horario H
        							INNER JOIN general_sede S ON S.sede_id = H.horario_sedeid
							WHERE H.horario_estado = 'A' AND H.horario_id = :horario";	

			$datos = $this->ejecutarConsulta($consulta_datos, [':horario' => (int)$horario_id]);		
			return $datos;
		}

		public function listaHorarioPDF($horarioid){		
			$consulta_datos=("SELECT A.alumno_identificacion AS CEDULA, 
									CONCAT(A.alumno_primernombre, ' ',A.alumno_segundonombre) AS NOMBRES,  
									CONCAT(A.alumno_apellidopaterno, ' ',A.alumno_apellidomaterno) AS APELLIDOS,
									case when alumno_numcamiseta = 0 then null else alumno_numcamiseta end AS NUMCAMISETA,
									YEAR(A.alumno_fechanacimiento) AS CATEGORIA, H.*
								FROM asistencia_asignahorario H
										INNER JOIN sujeto_alumno A ON A.alumno_id = H.asignahorario_alumnoid
								WHERE H.asignahorario_horarioid = $horarioid 
								ORDER BY A.alumno_apellidopaterno");	
			$datos = $this->ejecutarConsulta($consulta_datos);		
			return $datos;
		}

		public function eliminar_alumnolista(){	
			# Almacenando datos
			$alumnoid = $_POST['asignahorario_alumnoid'];
					
			# Verificando campos obligatorios #
			if($alumnoid == ""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No existe información del alumno",
					"icono"=>"error"
				];
				return json_encode($alerta);       
		    }				
			
			$eliminar_jugador = $this->eliminarRegistro("asistencia_asignahorario","asignahorario_alumnoid",$alumnoid);
			
			if($eliminar_jugador->rowCount()>0){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Alumno eliminado de la lista",
					"texto"=> "Lista actualizada correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"No fue posible eliminar el alumno de la lista",
					"icono"=>"error"
				];
			}
			return json_encode($alerta);
		} 

		public function registro_asistencia(){

			$estado 	= $this->limpiarCadena($_POST['estado']);
			$fecha		= $this->limpiarCadena($_POST['fecha']);
			$alumno_id	= $this->limpiarCadena($_POST['alumno_id']);

			$anio 		= date('Y', strtotime($fecha)); // Obtiene el año
			$mes 		= date('m', strtotime($fecha));  // Obtiene el mes
			$dia 		= date('d', strtotime($fecha)); // Obtiene el día

			switch ($dia) {
				case 1:
					$dia='asistencia_D01';
					break;
				case 2:
					$dia='asistencia_D02';
					break;
				case 3:
					$dia='asistencia_D03';
					break;
				case 4:
					$dia='asistencia_D04';
					break;
				case 5:
					$dia='asistencia_D05';
					break;
				case 6:
					$dia='asistencia_D06';
					break;
				case 7:
					$dia='asistencia_D07';
					break;
				case 8:
					$dia='asistencia_D08';
					break;
				case 9:
					$dia='asistencia_D09';
					break;
				case 10:
					$dia='asistencia_D10';
					break;
				case 11:
					$dia='asistencia_D11';
					break;
				case 12:
					$dia='asistencia_D12';
					break;
				case 13:
					$dia='asistencia_D13';
					break;
				case 14:
					$dia='asistencia_D14';
					break;
				case 15:
					$dia='asistencia_D15';
					break;
				case 16:
					$dia='asistencia_D16';
					break;
				case 17:
					$dia='asistencia_D17';
					break;
				case 18:
					$dia='asistencia_D18';
					break;
				case 19:
					$dia='asistencia_D19';
					break;
				case 20:
					$dia='asistencia_D20';
					break;
				case 21:
					$dia='asistencia_D21';
					break;
				case 22:
					$dia='asistencia_D22';
					break;
				case 23:
					$dia='asistencia_D23';
					break;
				case 24:
					$dia='asistencia_D24';
					break;
				case 25:
					$dia='asistencia_D25';
					break;
				case 26:
					$dia='asistencia_D26';
					break;
				case 27:
					$dia='asistencia_D27';
					break;
				case 28:
					$dia='asistencia_D28';
					break;
				case 29:
					$dia='asistencia_D29';
					break;
				case 30:
					$dia='asistencia_D30';
					break;
				case 31:
					$dia='asistencia_D31';
					break;
			}


			# Verificando usuario #
		    $datos=$this->ejecutarConsulta("SELECT asistencia_id FROM asistencia_asistencia WHERE asistencia_alumnoid = :alumno AND asistencia_aniomes = :aniomes", [':alumno' => (int)$alumno_id, ':aniomes' => $anio . $mes]);
		    if($datos->rowCount()<=0){
				//insert

				$asistencia_reg=[
					[
						"campo_nombre"=>"asistencia_alumnoid",
						"campo_marcador"=>":Alumnoid",
						"campo_valor"=>$alumno_id
					],
					[
						"campo_nombre"=>"asistencia_aniomes",
						"campo_marcador"=>":Aniomes",
						"campo_valor"=> "$anio$mes"
					],
					[
						"campo_nombre"=>$dia,
						"campo_marcador"=>":Estado",
						"campo_valor"=> $estado
					]
				];		
	
				$registrar_hora=$this->guardarDatos("asistencia_asistencia",$asistencia_reg);
	
				if($registrar_hora->rowCount()>0){
					$alerta=[
						"tipo"=>"recargar_directo"
					];	
				}
				return json_encode($alerta);

		    }else{
		    	$datos=$datos->fetch();
				//update

				$asistencia_reg=[					
					[
						"campo_nombre"=>$dia,
						"campo_marcador"=>":Estado",
						"campo_valor"=> $estado
					]
				];
				
				$condicion=[
					"condicion_campo"=>"asistencia_id",
					"condicion_marcador"=>":Asistencia_id",
					"condicion_valor"=>$datos['asistencia_id']
				];

				if($this->actualizarDatos("asistencia_asistencia",$asistencia_reg,$condicion)){

					$alerta=[
						"tipo"=>"recargar_directo"
					];
				}else{
					$alerta=[
						"tipo"=>"simple",
						"titulo"=>"Ocurrió un error inesperado",
						"texto"=>"No hemos podido actualizar la asistencia del alumno, por favor intente nuevamente",
						"icono"=>"error"
					];
				}
				return json_encode($alerta);

		    }		
		}

		public function BuscarAlumno($alumnoid){		
			$consulta_datos="SELECT S.sede_nombre, CASE WHEN alumno_estado = 'A' THEN 'Activo' WHEN alumno_estado = 'I' THEN 'Inactivo' ELSE 'Sin definir' END estado, Year(alumno_fechanacimiento) anio
					,CASE WHEN IFNULL(R.total, 0) > 0 THEN 1 ELSE 0 END pendiente,  IFNULL(R.total, 0) total
					,A.* 
					FROM sujeto_alumno A
					LEFT JOIN general_sede S ON S.sede_id = A.alumno_sedeid
					LEFT JOIN(
						SELECT COUNT(RP.pago_id) total, RA.alumno_id alumno
						FROM sujeto_alumno RA
						INNER JOIN alumno_pago RP ON RP.pago_alumnoid = RA.alumno_id
						WHERE RP.pago_estado = 'P'
						GROUP BY RA.alumno_id
					)R ON R.alumno = A.alumno_id
				WHERE A.alumno_id = :alumno";	
			$datos = $this->ejecutarConsulta($consulta_datos, [':alumno' => (int)$alumnoid]);
			return $datos;
		}

		public function CalendarioEventos($alumnoid){
			// Consulta para obtener los eventos
			/* Las 31 subconsultas (una por dia del mes) se generan aqui en
			   lugar de escribirse a mano. Cada dia lleva SU marcador para no
			   depender de que PDO emule las preparaciones. */
			$union      = [];
			$parametros = [];
			for($d = 1; $d <= 31; $d++){
				$dd  = str_pad((string)$d, 2, '0', STR_PAD_LEFT);
				$par = ':a'.$dd;
				$union[] = "SELECT asistencia_aniomes, 'D$dd' AS dia, asistencia_D$dd AS valor
								FROM asistencia_asistencia
							   WHERE asistencia_D$dd IS NOT NULL
								 AND asistencia_alumnoid = $par";
				$parametros[$par] = (int)$alumnoid;
			}

			$consulta_evento = "SELECT      
									asistencia_aniomes AS anio_mes, 
									STR_TO_DATE(CONCAT(asistencia_aniomes, LPAD(SUBSTRING_INDEX(dia, 'D', -1), 2, '0')), '%Y%m%d') AS 'start', 
									STR_TO_DATE(CONCAT(asistencia_aniomes, LPAD(SUBSTRING_INDEX(dia, 'D', -1), 2, '0')), '%Y%m%d') AS 'end', 
									CASE 
										WHEN valor = 'P' THEN 'PRESENTE'
										WHEN valor = 'A' THEN 'ATRASO'
										WHEN valor = 'J' THEN 'JUSTIFICADO'
										WHEN valor = 'F' THEN 'FALTA'
									END AS title,
									CASE 
										WHEN valor = 'P' THEN '#007bff'
										WHEN valor = 'A' THEN '#ffc107'
										WHEN valor = 'J' THEN '#6c757d'
										WHEN valor = 'F' THEN '#dc3545'
									END AS color    
								FROM (
								" . implode("\r\n UNION ALL \r\n", $union) . "
								) AS dias";

			$datos = $this->ejecutarConsulta($consulta_evento, $parametros);

			$eventos = array();

			if($datos->rowCount()>=0){
				while ($row = $datos->fetch()) {
					$eventos[]=[
						"title"=>$row['title'],
						"start"=>$row['start'],
						"end"=>$row['end'],
						"color"=>$row['color'],//"#2324ff",
					];		
				}
			}
			
			return json_encode($eventos);
		} 
	}
			