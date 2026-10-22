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
			if (($error = $this->validarRangoEvento($agenda_start, $agenda_end)) !== null) {
				return $error;
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

        /*
        | Los eventos, en JSON para FullCalendar.
        |
        | DEVUELVE en vez de imprimir: la vista lo incrusta en un <script> y
        | agendaAjax.php lo imprime con echo; antes imprimía por su cuenta y
        | devolvía null, y sólo funcionaba porque echo null no saca nada.
        |
        | limpiarHtml() guarda los textos ya escapados («Reunión &quot;A&quot;»).
        | FullCalendar los pinta como TEXTO, así que se decodifican: si no, el
        | título mostraría las entidades al pie de la letra. Las banderas JSON_HEX
        | impiden que un «</script>» dentro de un título cierre la etiqueta.
        */
        public function obtenerEventos() {
			$consulta_evento = "SELECT agenda_id AS id, agenda_title AS title, agenda_detail AS detail, agenda_start AS start, agenda_end AS end, agenda_color AS color
								FROM general_agenda
								WHERE agenda_estado = 'A'";
            $datos = $this->ejecutarConsulta($consulta_evento);
            $texto = static fn($v) => html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $eventos = array();
			while ($row = $datos->fetch()) {
				$eventos[]=[
					"id"     => $row['id'],
					"title"  => $texto($row['title']),
					"start"  => $row['start'],
					"end"    => $row['end'],
					"color"  => $row['color'],
					"detail" => $texto($row['detail'])
				];
			}
            return json_encode($eventos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        }

        /* Los próximos eventos (los que aún no han terminado), para la
           columna lateral de la agenda. Textos decodificados como arriba: la
           vista los escapa al imprimirlos. */
        public function proximosEventos(int $limite = 6): array {
			$datos = $this->ejecutarConsulta(
				"SELECT agenda_id AS id, agenda_title AS title, agenda_start AS start, agenda_end AS end, agenda_color AS color
				   FROM general_agenda
				  WHERE agenda_estado = 'A' AND agenda_end >= NOW()
				  ORDER BY agenda_start ASC
				  LIMIT " . max(1, min(20, $limite)));
			$texto = static fn($v) => html_entity_decode((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$filas = [];
			while ($row = $datos->fetch()) {
				$row['title'] = $texto($row['title']);
				$filas[] = $row;
			}
			return $filas;
        }

        /* Fin no anterior al inicio, y fechas que sean fechas. Devuelve la
           alerta en JSON o null. Antes se aceptaba un evento que terminaba
           antes de empezar y el calendario lo dibujaba vacío. */
        private function validarRangoEvento(string $inicio, string $fin): ?string {
			$i = strtotime(str_replace('T', ' ', $inicio));
			$f = strtotime(str_replace('T', ' ', $fin));
			if ($i === false || $f === false) {
				return json_encode(["tipo"=>"simple", "titulo"=>"Fecha no válida",
					"texto"=>"Revise la fecha y hora de inicio y de fin del evento.", "icono"=>"error"]);
			}
			if ($f < $i) {
				return json_encode(["tipo"=>"simple", "titulo"=>"Fechas invertidas",
					"texto"=>"El evento no puede terminar antes de empezar.", "icono"=>"error"]);
			}
			return null;
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
			if (($error = $this->validarRangoEvento($agenda_start, $agenda_end)) !== null) {
				return $error;
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
