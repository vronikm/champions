<?php

	namespace app\controllers;
	use app\models\mainModel;
	use \DateTime;
	
	class agendaController extends mainModel{		
		public function registrarEvento(){

            $agenda_title   = $this->limpiarCadena($_POST['agenda_title']);
            $agenda_detail  = $this->limpiarCadena($_POST['agenda_detail']);
            $agenda_start   = $this->limpiarCadena($_POST['agenda_start']);
            $agenda_end     = $this->limpiarCadena($_POST['agenda_end']);
            $agenda_color   = $this->limpiarCadena($_POST['agenda_color']);

			if($agenda_title=="" || $agenda_start=="" || $agenda_end==""){
		    	$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No ha completado todos los campos que son obligatorios",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }

			$agenda_reg=[
                [
                    "campo_nombre"=>"agenda_title",
                    "campo_marcador"=>":Titulo",
                    "campo_valor"=>$agenda_title
                ],
                [
                    "campo_nombre"=>"agenda_detail",
                    "campo_marcador"=>":Detalle",
                    "campo_valor"=> $agenda_detail
                ],
                [
                    "campo_nombre"=>"agenda_start",
                    "campo_marcador"=>":Start",
                    "campo_valor"=> $agenda_start
                ],
                [
                    "campo_nombre"=>"agenda_end",
                    "campo_marcador"=>":End",
                    "campo_valor"=> $agenda_end
                ],
                [
                    "campo_nombre"=>"agenda_color",
                    "campo_marcador"=>":Color",
                    "campo_valor"=> $agenda_color
				],
				[
                    "campo_nombre"=>"agenda_estado",
                    "campo_marcador"=>":Estado",
                    "campo_valor"=> "A"
                ]
            ];		

            $registrar_evento=$this->guardarDatos("general_agenda",$agenda_reg);

            if($registrar_evento->rowCount()>0){
                $alerta=[
                    "tipo"=>"recargar",
                    "titulo"=>"Evento registrado",
                    "texto"=>"El evento se registró correctamente",
                    "icono"=>"success"
                ];
            }else{
                $alerta=[
                    "tipo"=>"simple",
                    "titulo"=>"Ocurrió un error inesperado",
                    "texto"=>"No se pudo registrar el evento, por favor intente nuevamente",
                    "icono"=>"error"
                ];
            }
            return json_encode($alerta);
		}

        public function obtenerEventos() {
			$consulta_evento = "SELECT agenda_id AS id, agenda_title AS title, agenda_detail AS detail, agenda_start AS start, agenda_end AS end, agenda_color AS color 
								FROM general_agenda
								WHERE agenda_estado = 'A'";	
            $datos = $this->ejecutarConsulta($consulta_evento);
            $eventos = array();
            if($datos->rowCount()>=0){
				while ($row = $datos->fetch()) {
					$eventos[]=[
						"id" => $row['id'],
						"title"=>$row['title'],
						"start"=>$row['start'],
						"end"=>$row['end'],
						"color"=>$row['color'],
						"detail" => $row['detail'] // Campo adicional si es necesario
					];
					
				}
			}
            // Devuelve los eventos como JSON
            echo json_encode($eventos);
        }

		public function editarEvento() {			

			$agenda_id      = $this->limpiarCadena($_POST['agenda_id']);
			$agenda_title   = $this->limpiarCadena($_POST['agenda_title']);
			$agenda_detail  = $this->limpiarCadena($_POST['agenda_detail']);
			$agenda_start   = $this->limpiarCadena($_POST['agenda_start']);
			$agenda_end     = $this->limpiarCadena($_POST['agenda_end']);
			$agenda_color   = $this->limpiarCadena($_POST['agenda_color']);			
		
			if ($agenda_id == "" || $agenda_title == "" || $agenda_start == "" || $agenda_end == "") {
				$alerta = [
					"tipo" => "simple",
					"titulo" => "Ocurrió un error",
					"texto" => "No ha completado todos los campos que son obligatorios",
					"icono" => "error"
				];
				return json_encode($alerta);
			}			
		
			$agenda_reg=[
                [
                    "campo_nombre"=>"agenda_title",
                    "campo_marcador"=>":Titulo",
                    "campo_valor"=>$agenda_title
                ],
                [
                    "campo_nombre"=>"agenda_detail",
                    "campo_marcador"=>":Detalle",
                    "campo_valor"=> $agenda_detail
                ],
                [
                    "campo_nombre"=>"agenda_start",
                    "campo_marcador"=>":Start",
                    "campo_valor"=> $agenda_start
                ],
                [
                    "campo_nombre"=>"agenda_end",
                    "campo_marcador"=>":End",
                    "campo_valor"=> $agenda_end
                ],
                [
                    "campo_nombre"=>"agenda_color",
                    "campo_marcador"=>":Color",
                    "campo_valor"=> $agenda_color
                ]
            ];
		
			$condicion=[
				"condicion_campo"=>"agenda_id",
				"condicion_marcador"=>":ID",
				"condicion_valor"=>$agenda_id
			];
		
			if($this->actualizarDatos("general_agenda", $agenda_reg, $condicion)){
				$alerta = [
					"tipo" => "recargar",
					"titulo" => "Evento actualizado",
					"texto" => "El evento se actualizó correctamente",
					"icono" => "success"
				];
			}else{
				$alerta = [
					"tipo" => "simple",
					"titulo" => "Ocurrió un error inesperado",
					"texto" => "No se pudo actualizar el evento, por favor intente nuevamente",
					"icono" => "error"
				];
			}
			return json_encode($alerta);			
		}

		public function eliminarEvento() {
			$agenda_id = $this->limpiarCadena($_POST['agenda_id']);
			
			if (empty($agenda_id)) {
				$alerta = [
					"tipo" => "simple",
					"titulo" => "Ocurrió un error",
					"texto" => "No se envió el identificador del evento.",
					"icono" => "error"
				];
				return json_encode($alerta);
			}

			$agenda_reg=[
                [
                    "campo_nombre"=>"agenda_estado",
                    "campo_marcador"=>":Estado",
                    "campo_valor"=> "E"
                ]
            ];
		
			$condicion=[
				"condicion_campo"=>"agenda_id",
				"condicion_marcador"=>":ID",
				"condicion_valor"=>$agenda_id
			];
		
			if($this->actualizarDatos("general_agenda", $agenda_reg, $condicion)){
				$alerta = [
					"tipo" => "recargar",
					"titulo" => "Evento eliminado",
					"texto" => "El evento se eliminó correctamente.",
					"icono" => "success"
				];
			}else{
				$alerta = [
					"tipo" => "simple",
					"titulo" => "Ocurrió un error inesperado",
					"texto" => "No se pudo eliminar el evento. Por favor, intente nuevamente.",
					"icono" => "error"
				];
			}
			return json_encode($alerta);	
		}		
    }
