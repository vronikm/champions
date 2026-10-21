<?php

	namespace app\controllers;
	use app\models\mainModel;
	use app\services\SRI\FacturaElectronicaService;
	use app\services\SRI\FirmaElectronicaService;
	use app\services\SRI\WebServiceSRIService;

	class facturasController extends mainModel{


		/*  funciones para el manejo de facturas */
		public function listarSedeFacturas($sedeid, $rolid = null, $usuario = null){
			$option="";
			/* El parametro solo se pasa si la consulta lo usa: la rama de
			   administrador no lleva :usuario y PDO rechaza los sobrantes. */
			$parametros=[];

			if($rolid != 1 && $rolid != 2){
				$consulta_datos="SELECT S.sede_id, S.sede_nombre
									FROM general_sede S
									INNER JOIN seguridad_usuario_sede US ON US.usuariosede_sedeid = S.sede_id
									INNER JOIN seguridad_usuario U ON U.usuario_id = US.usuariosede_usuarioid
									WHERE U.usuario_usuario = :usuario";
				$parametros[':usuario'] = $usuario;
			}else{
				$consulta_datos="SELECT sede_id, sede_nombre FROM general_sede";
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

		public function listarAlumnosFacturas($identificacion, $apellidopaterno, $primernombre, $anio, $sede){
			/*
			| Mismo criterio que en el buscador de pagos: los valores van
			| ligados, no concatenados. El comodin se anade al parametro, no
			| a la consulta, para que un apellido con apostrofo se busque tal
			| cual lo escribio el usuario.
			|
			| Se conservan las mismas ramas que antes:
			|   · con algun criterio de texto -> los tres LIKE en OR
			|   · sin criterios pero con anio -> solo por anio de nacimiento
			|   · sin nada                    -> todos
			|   · sin sede                    -> no se lista nada
			*/
			$tabla="";
			$parametros = [];

			$hayTexto = ($identificacion != "" || $primernombre != "" || $apellidopaterno != "");

			if($hayTexto){
				$consulta_datos = "SELECT * FROM sujeto_alumno
									WHERE (alumno_primernombre LIKE :primernombre
									OR alumno_identificacion LIKE :identificacion
									OR alumno_apellidopaterno LIKE :apellidopaterno) ";

				/* El comodin solo se anade a lo que trae valor: un criterio
				   vacio debe seguir sin coincidir con nada, como antes. */
				$parametros[':primernombre']    = $primernombre    != "" ? $primernombre.'%'    : '';
				$parametros[':identificacion']  = $identificacion  != "" ? $identificacion.'%'  : '';
				$parametros[':apellidopaterno'] = $apellidopaterno != "" ? $apellidopaterno.'%' : '';

				if($anio!=""){
					$consulta_datos .= " and YEAR(alumno_fechanacimiento) = :anio";
					$parametros[':anio'] = $anio;
				}
			}elseif($anio!=""){
				$consulta_datos = "SELECT * FROM sujeto_alumno WHERE YEAR(alumno_fechanacimiento) = :anio";
				$parametros[':anio'] = $anio;
			}else{
				$consulta_datos = "SELECT * FROM sujeto_alumno WHERE alumno_primernombre <> '' ";
			}

			if($sede!=""){
				if($sede == 0){
					$consulta_datos .= " and alumno_sedeid <> :sede";
				}else{
					$consulta_datos .= " and alumno_sedeid = :sede";
				}
				$parametros[':sede'] = $sede;
			}else{
				/* Sin sede no se lista nada; se descartan los parametros
				   recogidos porque la consulta que los usaba desaparece. */
				$consulta_datos = "SELECT * FROM sujeto_alumno WHERE alumno_primernombre = '' ";
				$parametros = [];
			}

			// Solo alumnos activos cuyo representante factura (repre_factura = 'S')
			$consulta_datos .= " AND alumno_estado = 'A' AND alumno_repreid IN (SELECT repre_id FROM alumno_representante WHERE repre_factura = 'S')";
			$datos = $this->ejecutarConsulta($consulta_datos, $parametros);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				// Estado de facturacion del alumno (por prioridad):
				//   ya tiene facturas (algun pago facturado, incluido via hermano) -> amarillo (btn-warning)
				//   pagos pendientes de facturar -> azul (btn-info)
				//   sin pagos -> gris (btn-secondary)
				$alumnoId = (int)$rows['alumno_id'];
				try{
					$estado = $this->ejecutarConsulta(
						"SELECT
							(SELECT COUNT(*) FROM alumno_pago P
								WHERE P.pago_alumnoid = :alumno1 AND P.pago_estado = 'C'
									AND NOT EXISTS (
										SELECT 1 FROM facturas_electronicas_detalle FD
										INNER JOIN facturas_electronicas FE ON FE.id = FD.factura_electronica_id
										WHERE FD.pago_id = P.pago_id AND ".$this->estadoFacturaBloqueanteSql('FE')."
									)
							) AS pagos_pendientes,
							(SELECT COUNT(*) FROM alumno_pago P2
								WHERE P2.pago_alumnoid = :alumno2
									AND EXISTS (
										SELECT 1 FROM facturas_electronicas_detalle FD2
										INNER JOIN facturas_electronicas FE2 ON FE2.id = FD2.factura_electronica_id
										WHERE FD2.pago_id = P2.pago_id AND ".$this->estadoFacturaBloqueanteSql('FE2')."
									)
							) AS facturas"
					, [':alumno1' => $alumnoId, ':alumno2' => $alumnoId])->fetch();
					$pagosPendientes = (int)($estado['pagos_pendientes'] ?? 0);
					$tieneFacturas   = (int)($estado['facturas'] ?? 0);
				}catch(\Throwable $e){
					$pagosPendientes = 0;
					$tieneFacturas   = 0;
				}

				if($tieneFacturas > 0){
					$botonpago = "btn-warning";    // ya tiene facturas (incluye via hermano)
				}elseif($pagosPendientes > 0){
					$botonpago = "btn-info";       // pagos pendientes de facturar
				}else{
					$botonpago = "btn-secondary";  // sin pagos
				}
				if($rows['alumno_estado']=="I"){
					$class = 'class="text-primary"';
				}else{
					$class = '';
				}
				$tabla.='
					<tr '.$class.'>
						<td>'.$rows['alumno_identificacion'].'</td>
						<td>'.$rows['alumno_primernombre'].' '.$rows['alumno_segundonombre'].'</td>
						<td>'.$rows['alumno_apellidopaterno'].' '.$rows['alumno_apellidomaterno'].'</td>
						<td>'.$rows['alumno_fechanacimiento'].'</td>
						<td>
							'.$this->siPuede('crear','facturasList','<a href="'.APP_URL.'facturasNew/'.$rows['alumno_id'].'/" class="btn float-end '.$botonpago.' btn-sm" target="_blank"><i class="fas fa-file-invoice-dollar me-1"></i>Registrar factura</a>').'
						</td>
					</tr>';
			}
			return $tabla;
		}

		public function BuscarAlumnoFactura($alumnoid, $fecha_inicio,$fecha_fin){
			$consulta_datos="SELECT
								RE.repre_identificacion,
								RE.repre_direccion,
								RE.repre_correo,
								RE.repre_celular,
								RE.repre_tipoidentificacion,
								RE.repre_id,
								RE.representante,
								IFNULL(RP.pagos, 0) AS pagos   -- devuelve 0 si no hay pagos
							FROM (
								SELECT
									R.repre_identificacion,
									R.repre_direccion,
									R.repre_correo,
									R.repre_celular,
									R.repre_tipoidentificacion,
									R.repre_id,
									CONCAT(R.repre_primernombre, ' ', R.repre_segundonombre, ' ', R.repre_apellidopaterno, ' ', R.repre_apellidomaterno) AS representante
								FROM sujeto_alumno A
								INNER JOIN alumno_representante R ON R.repre_id = A.alumno_repreid
								WHERE A.alumno_id = :alumno
							) RE
								LEFT JOIN (
									SELECT R.repre_id, COUNT(*) AS pagos
									FROM alumno_representante R
									INNER JOIN sujeto_alumno AL ON AL.alumno_repreid = R.repre_id
									INNER JOIN alumno_pago P ON P.pago_alumnoid = AL.alumno_id
									WHERE P.pago_estado = 'C' AND P.pago_fecharegistro BETWEEN :desde AND :hasta
									GROUP BY R.repre_id
								) RP ON RP.repre_id = RE.repre_id";

			/* Parámetros ligados: el id llega de la URL. */
			$datos = $this->ejecutarConsulta($consulta_datos, [
				':alumno' => (int)$alumnoid,
				':desde'  => $fecha_inicio,
				':hasta'  => $fecha_fin,
			]);
			return $datos;
		}

		public function listarPagosFactura($alumnoid, $fecha_inicio, $fecha_fin){ //29052024
			$configSri = $this->sriConfig();
			$ivaTarifa = (float)($configSri['iva_tarifa_default'] ?? 0);
			$tabla="";

			$consulta_datos="SELECT
								ROW_NUMBER() OVER (ORDER BY RP.pago_id) AS fila_numero,
								RE.repre_identificacion,
								RE.repre_direccion,
								RE.repre_correo,
								RE.repre_celular,
								RE.repre_tipoidentificacion,
								RE.repre_id,
								RE.representante,
								RP.pago_fecharegistro, RP.pago_valor, RP.detalle,
								RP.alumno,
								RP.pago_id,
								RP.codigo,
								RP.forma_pago
							FROM (
								SELECT
									R.repre_identificacion,
									R.repre_direccion,
									R.repre_correo,
									R.repre_celular,
									R.repre_tipoidentificacion,
									R.repre_id,
									CONCAT(R.repre_primernombre, ' ', R.repre_segundonombre, ' ', R.repre_apellidopaterno, ' ', R.repre_apellidomaterno) AS representante
								FROM sujeto_alumno A
									INNER JOIN alumno_representante R ON R.repre_id = A.alumno_repreid
								WHERE A.alumno_id = :alumno
							) RE
								LEFT JOIN (
									SELECT
										P.pago_fecharegistro, P.pago_valor, CONCAT(C.catalogo_descripcion, ' ', P.pago_periodo, ', ', P.pago_concepto ) AS detalle,
										CONCAT(AL.alumno_primernombre, ' ', AL.alumno_segundonombre, ' ', AL.alumno_apellidopaterno, ' ', AL.alumno_apellidomaterno) AS alumno,
										P.pago_id, R.repre_id, C.catalogo_valor AS codigo,
										F.catalogo_descripcion AS forma_pago
									FROM alumno_representante R
									INNER JOIN sujeto_alumno AL ON AL.alumno_repreid = R.repre_id
									INNER JOIN alumno_pago P ON P.pago_alumnoid = AL.alumno_id
									INNER JOIN general_tabla_catalogo C ON C.catalogo_valor = P.pago_rubroid
									LEFT JOIN general_tabla_catalogo F ON F.catalogo_valor = P.pago_formapagoid
									WHERE P.pago_estado = 'C' AND P.pago_fecharegistro BETWEEN :desde AND :hasta
										AND NOT EXISTS (
											SELECT 1 FROM facturas_electronicas_detalle FD
											INNER JOIN facturas_electronicas FE ON FE.id = FD.factura_electronica_id
											WHERE FD.pago_id = P.pago_id AND ".$this->estadoFacturaBloqueanteSql('FE')."
										)
								) RP ON RP.repre_id = RE.repre_id";

			/* Id y fechas ligados: esta consulta no los validaba de ninguna
			   forma y el id entraba sin comillas, donde escapar no sirve. */
			$datos = $this->ejecutarConsulta($consulta_datos, [
				':alumno' => (int)$alumnoid,
				':desde'  => $fecha_inicio,
				':hasta'  => $fecha_fin,
			]);
			$datos = $datos->fetchAll();
			foreach($datos as $rows){
				$detallePago = htmlspecialchars($this->limpiarTextoFactura($rows['detalle'] ?? ''), ENT_QUOTES, 'UTF-8');
				$alumnoPago = htmlspecialchars($this->limpiarTextoFactura($rows['alumno'] ?? ''), ENT_QUOTES, 'UTF-8');
				$codigoPago = htmlspecialchars((string)($rows['codigo'] ?? ''), ENT_QUOTES, 'UTF-8');
				$fechaPago = htmlspecialchars((string)($rows['pago_fecharegistro'] ?? ''), ENT_QUOTES, 'UTF-8');
				$valorPago = number_format((float)($rows['pago_valor'] ?? 0), 2, '.', '');
				$formaPago = htmlspecialchars($this->limpiarTextoFactura($rows['forma_pago'] ?? ''), ENT_QUOTES, 'UTF-8');
                if(empty($rows['pago_id'])){
                    continue;
                }
				$checkbox_id = "pagoCheckbox".$rows['pago_id'];	// id ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âºnico por pago
				$tabla.='
					<tr>
						<td>'.$rows['fila_numero'].'</td>
						<td>'.$fechaPago.'</td>
						<td>'.$valorPago.'</td>
						<td>'.$formaPago.'</td>
						<td>'.$detallePago.'</td>
						<td>'.$alumnoPago.'</td>
						<td>
							<div class="custom-control custom-checkbox">
								<input class="custom-control-input chk-pago"
									type="checkbox"
									id="'.$checkbox_id.'"
									name="pagos_seleccionados[]"
									value="'.$rows['pago_id'].'"
									data-codigo="'.$codigoPago.'"
									data-fecha="'.$fechaPago.'"
									data-detalle="Pago '.$detallePago.' del alumno '.$alumnoPago.'"
									data-valor="'.$valorPago.'"
									data-tarifa="'.$ivaTarifa.'">
								<label for="'.$checkbox_id.'" class="custom-control-label"></label>
							</div>
						</td>
					</tr>';
			}
            if($tabla===""){
                $tabla = '<tr><td colspan="7" class="text-center text-muted">No hay pagos pendientes de facturar en el rango seleccionado.</td></tr>';
            }
			return $tabla;
		}

		public function actualizarRepresentanteFactura(){

			$repreid=$this->limpiarCadena($_POST['repre_id']);

			# Verificando existencia de representante #
			$representante=$this->ejecutarConsulta("SELECT repre_id, repre_tipoidentificacion FROM alumno_representante WHERE repre_id = :repre", [':repre' => (int)$repreid]);
			if($representante->rowCount()<=0){
		        $alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"El representante no se encuentra en el sistema",
					"icono"=>"error"
				];
				return json_encode($alerta);
		    }else{
			$representante=$representante->fetch();
		    }

			/*---------------Variables para el registro del tab Representante del alumno----------------*/
			$repre_identificacion 	  	= $this->limpiarCadena($_POST['identificacion']);
			$repre_direccion 		  	= $this->limpiarCadena($_POST['direccion']);
			$repre_correo 			  	= $this->limpiarCadena($_POST['correo']);
			$repre_celular 			  	= $this->limpiarCadena($_POST['celular']);

			# Verificando campos obligatorios #
			if($repre_identificacion=="" || $repre_direccion=="" || $repre_correo=="" || $repre_celular==""){
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Ocurrió un error",
					"texto"=>"No ha completado los campos obligatorios del representante",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			if (!$this->validarIdentificacionSri($repre_identificacion, $representante['repre_tipoidentificacion'] ?? 'CED')) {
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"La cédula ingresada no es válida",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			if (!filter_var($repre_correo, FILTER_VALIDATE_EMAIL)) {
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Error",
					"texto"=>"El correo ingresado no es válido",
					"icono"=>"error"
				];
				return json_encode($alerta);
			}

			$representante_reg=[

				[
					"campo_nombre"=>"repre_identificacion",
					"campo_marcador"=>":IdnetificacionRep",
					"campo_valor"=>$repre_identificacion
				],
				[
					"campo_nombre"=>"repre_direccion",
					"campo_marcador"=>":DireccionRep",
					"campo_valor"=>$repre_direccion
				],
				[
					"campo_nombre"=>"repre_correo",
					"campo_marcador"=>":CorreoRep",
					"campo_valor"=>$repre_correo
				],
				[
					"campo_nombre"=>"repre_celular",
					"campo_marcador"=>":CelularRep",
					"campo_valor"=>$repre_celular
				]
			];
			$condicion=[
				"condicion_campo"=>"repre_id",
				"condicion_marcador"=>":Repreid",
				"condicion_valor"=>$repreid
			];

			if($this->actualizarDatos("alumno_representante",$representante_reg,$condicion)){
				$alerta=[
					"tipo"=>"recargar",
					"titulo"=>"Informacion actualizada",
					"texto"=>"El representante se actualizo correctamente",
					"icono"=>"success"
				];
			}else{
				$alerta=[
					"tipo"=>"simple",
					"titulo"=>"Informacion no actualizada",
					"texto"=>"Por favor intente nuevamente",
					"icono"=>"alert"
				];
			}
			return json_encode($alerta);
		}


		/**
		 * Defaults estructurales de la facturacion SRI (constantes de la app, no
		 * datos editables). Permiten que el sistema funcione aunque se borren
		 * config/sri.php y config/sri_local.php: los datos del emisor, parametros
		 * y formas de pago se leen de la BD; lo estructural vive aqui.
		 */
		private function sriEstructuraBase(){
			$raiz = dirname(__DIR__, 2);
			return [
				'ambiente' => (string)(getenv('SENDERO_SRI_AMBIENTE') ?: '1'),
				'tipo_emision' => '1',
				'version_factura' => '2.1.0',
				'iva_tarifa_default' => (float)(getenv('SENDERO_SRI_IVA_TARIFA') !== false ? getenv('SENDERO_SRI_IVA_TARIFA') : 0),
				'valores_incluyen_iva' => true,
				'secuencial_inicio' => 1,
				'emisor' => [
					'ruc' => '', 'razon_social' => '', 'nombre_comercial' => '',
					'direccion_matriz' => '', 'direccion_establecimiento' => '',
					'codigo_establecimiento' => '001', 'punto_emision' => '001',
					'obligado_contabilidad' => 'NO', 'contribuyente_especial' => '',
					'agente_retencion' => '', 'contribuyente_rimpe' => '',
				],
				'firma' => [
					'archivo' => $raiz . '/storage/certificados/firma.p12',
					'clave' => getenv('SENDERO_SRI_FIRMA_CLAVE') ?: '',
				],
				'correo' => [
					'from' => getenv('SENDERO_FACTURACION_FROM') ?: ds_email_organizacion(),
				],
				'webservices' => [
					'pruebas' => [
						'recepcion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
						'autorizacion' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
					],
					'produccion' => [
						'recepcion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl',
						'autorizacion' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
					],
				],
				'formas_pago' => [
					'01' => 'SIN UTILIZACION DEL SISTEMA FINANCIERO',
					'15' => 'COMPENSACION DE DEUDAS',
					'16' => 'TARJETA DE DEBITO',
					'17' => 'DINERO ELECTRONICO',
					'18' => 'TARJETA PREPAGO',
					'19' => 'TARJETA DE CREDITO',
					'20' => 'OTROS CON UTILIZACION DEL SISTEMA FINANCIERO',
					'21' => 'ENDOSO DE TITULOS',
				],
				'forma_pago_default' => getenv('SENDERO_SRI_FORMA_PAGO') ?: '20',
				'impuestos' => [
					'IVA' => [
						'codigo' => '2',
						'tarifas' => [
							'0' => ['codigo' => '0', 'porcentaje' => 0],
							'12' => ['codigo' => '2', 'porcentaje' => 12],
							'14' => ['codigo' => '3', 'porcentaje' => 14],
							'15' => ['codigo' => '4', 'porcentaje' => 15],
							'NO_OBJETO' => ['codigo' => '6', 'porcentaje' => 0],
							'EXENTO' => ['codigo' => '7', 'porcentaje' => 0],
						],
					],
				],
				'storage' => [
					'xml_generados' => $raiz . '/storage/sri/xml/generados/',
					'xml_firmados' => $raiz . '/storage/sri/xml/firmados/',
					'xml_autorizados' => $raiz . '/storage/sri/xml/autorizados/',
					'ride' => $raiz . '/storage/sri/ride/',
					'logs' => $raiz . '/storage/sri/logs/',
					'certificados' => $raiz . '/storage/certificados/',
				],
			];
		}

		private function sriConfig(){
			// Base estructural en codigo (siempre presente, aunque falten los archivos)
			$config = $this->sriEstructuraBase();
			$configPath = dirname(__DIR__, 2).'/config/sri.php';
			if(is_file($configPath)){
				$archivo = require $configPath;
				if(is_array($archivo)){
					$config = array_replace_recursive($config, $archivo);
				}
			}

			// 1. Fuente principal: tabla facturas_electronicas_config
			$bd = $this->configEmisorDesdeBd();
			if(!empty($bd)){
				$config = array_replace_recursive($config, $bd);
			}else{
				// 2. Fallback legacy: config/sri_local.php (pre-wiring / BD no disponible)
				$localPath = dirname(__DIR__, 2).'/config/sri_local.php';
				if(is_file($localPath)){
					$local = require $localPath;
					if(is_array($local)){
						$config = array_replace_recursive($config, $local);
					}
				}
			}

			// 3. Catalogo de formas de pago desde la BD (si esta poblado)
			$formas = $this->formasPagoDesdeBd();
			if(!empty($formas)){
				$config['formas_pago'] = $formas;
			}

			return $config;
		}

		/**
		 * Devuelve el overlay de emisor + parametros desde la tabla
		 * facturas_electronicas_config, o [] si no hay fila configurada
		 * (RUC vacio) o la BD no esta disponible.
		 */
		private function configEmisorDesdeBd(){
			try{
				$stmt = $this->ejecutarConsulta("SELECT * FROM facturas_electronicas_config WHERE config_lock = 'X' LIMIT 1");
				$row = $stmt->fetch();
			}catch(\Throwable $e){
				return [];
			}
			if(!$row || trim((string)($row['ruc'] ?? '')) === ''){
				return [];
			}
			return [
				'ambiente' => (string)$row['ambiente'],
				'tipo_emision' => (string)$row['tipo_emision'],
				'iva_tarifa_default' => (float)$row['iva_tarifa_default'],
				'forma_pago_default' => (string)$row['forma_pago_default'],
				'valores_incluyen_iva' => (bool)((int)$row['valores_incluyen_iva']),
				'secuencial_inicio' => max(1, (int)($row['secuencial_inicio'] ?? 1)),
				'emisor' => [
					'ruc' => (string)$row['ruc'],
					'razon_social' => (string)$row['razon_social'],
					'nombre_comercial' => (string)$row['nombre_comercial'],
					'direccion_matriz' => (string)$row['direccion_matriz'],
					'direccion_establecimiento' => (string)$row['direccion_establecimiento'],
					'codigo_establecimiento' => (string)$row['codigo_establecimiento'],
					'punto_emision' => (string)$row['punto_emision'],
					'obligado_contabilidad' => (string)$row['obligado_contabilidad'],
					'contribuyente_especial' => (string)$row['contribuyente_especial'],
					'agente_retencion' => (string)$row['agente_retencion'],
					'contribuyente_rimpe' => (string)$row['contribuyente_rimpe'],
				],
			];
		}

		/** Catalogo SRI de formas de pago desde la BD (codigo => nombre). */
		private function formasPagoDesdeBd(){
			try{
				$stmt = $this->ejecutarConsulta("SELECT codigo, nombre FROM facturas_electronicas_forma_pago WHERE activo = 1 ORDER BY orden, codigo");
				$rows = $stmt->fetchAll();
			}catch(\Throwable $e){
				return [];
			}
			$formas = [];
			foreach($rows as $r){
				$formas[(string)$r['codigo']] = (string)$r['nombre'];
			}
			return $formas;
		}

		public function obtenerConfiguracionSri(){
			return $this->sriConfig();
		}

		private function rutaConfigLocalSri(){
			return dirname(__DIR__, 2).'/config/sri_local.php';
		}

		private function textoConfigSri($valor, $limite=300){
			$valor = trim(strip_tags((string)$valor));
			$valor = preg_replace('/\s+/', ' ', $valor);
			return function_exists('mb_substr') ? mb_substr($valor, 0, $limite, 'UTF-8') : substr($valor, 0, $limite);
		}

        private function valorNoAplicaSri($valor){
            $valor = strtoupper(trim((string)$valor));
            return $valor === '' || in_array($valor, ['NO', 'N/A', 'NA', 'NINGUNO', 'NO APLICA', '0'], true);
        }

        private function normalizarTextoOpcionalSri($valor, $limite=300){
            $valor = $this->textoConfigSri($valor, $limite);
            return $this->valorNoAplicaSri($valor) ? '' : $valor;
        }

        private function normalizarNumeroOpcionalSri($valor, $limite=13){
            $valor = $this->textoConfigSri($valor, $limite);
            if($this->valorNoAplicaSri($valor)){ return ''; }
            return preg_replace('/\D+/', '', $valor);
        }

		/**
		 * Quién puede tocar la configuración del SRI.
		 *
		 * Sólo el superadministrador: aquí se define el RUC emisor, el
		 * ambiente y el certificado de firma, es decir, en nombre de quién
		 * se emiten comprobantes con validez tributaria. Emitir facturas es
		 * otra cosa y sí se concede por permisos de rol sobre facturasList.
		 *
		 * La pantalla vive en el módulo Core; esto guarda la puerta de atrás.
		 */
		private function autorizadoConfigSri(){
			return es_superadministrador();
		}

		private function rutaClaveFirmaSri(){
			$config = $this->sriConfig();
			$dir = $config['storage']['certificados'] ?? (dirname(__DIR__, 2).'/storage/certificados/');
			return rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'firma.key.php';
		}

		/**
		 * Ruta del secreto de cifrado, fuera del control de versiones.
		 * Vive en storage/ (protegido por .htaccess) y NO debe versionarse.
		 */
		private function rutaSecretoCriptoSri(){
			$config = $this->sriConfig();
			$dir = $config['storage']['certificados'] ?? (dirname(__DIR__, 2).'/storage/certificados/');
			return rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'.cryptokey.php';
		}

		/**
		 * Secreto aleatorio (32 bytes) persistido en disco fuera del repo.
		 * Si $generar es true y no existe, se crea uno nuevo.
		 */
		private function obtenerSecretoCriptoSri($generar = false){
			$path = $this->rutaSecretoCriptoSri();
			if(is_file($path)){
				$raw = trim(str_replace('<?php exit; ?>', '', (string)file_get_contents($path)));
				$secreto = base64_decode($raw, true);
				if($secreto !== false && $secreto !== ''){
					return $secreto;
				}
			}
			if($generar && function_exists('random_bytes')){
				$this->protegerDirectorioSri(dirname($path));
				$secreto = random_bytes(32);
				if(file_put_contents($path, "<?php exit; ?>\n".base64_encode($secreto), LOCK_EX) !== false){
					@chmod($path, 0600);
					return $secreto;
				}
			}
			return '';
		}

		/**
		 * Llave para CIFRAR: la mas fuerte disponible.
		 * Prioridad: variable de entorno SENDERO_SRI_CRYPTO_KEY (secreto explicito)
		 *  > secreto aleatorio en storage (se genera si falta)
		 *  > derivacion legacy (constantes publicas) solo como ultimo recurso.
		 */
		private function claveCriptoSri(){
			$env = getenv('SENDERO_SRI_CRYPTO_KEY');
			if($env !== false && $env !== ''){
				return hash('sha256', $env, true);
			}
			$secreto = $this->obtenerSecretoCriptoSri(true);
			if($secreto !== ''){
				return hash('sha256', $secreto, true);
			}
			return hash('sha256', APP_SESSION_NAME.'|'.APP_URL, true);
		}

		/**
		 * Llaves candidatas para DESCIFRAR (incluye la legacy para poder leer
		 * datos cifrados con la llave antigua durante la transicion).
		 */
		private function clavesCriptoSriCandidatas(){
			$claves = [];
			$env = getenv('SENDERO_SRI_CRYPTO_KEY');
			if($env !== false && $env !== ''){
				$claves[] = hash('sha256', $env, true);
			}
			$secreto = $this->obtenerSecretoCriptoSri(false);
			if($secreto !== ''){
				$claves[] = hash('sha256', $secreto, true);
			}
			$claves[] = hash('sha256', APP_SESSION_NAME.'|'.APP_URL, true); // legacy
			return $claves;
		}

		private function protegerDirectorioSri($dir){
			if(!is_dir($dir)){
				mkdir($dir, 0755, true);
			}

			$htaccess = rtrim($dir, '/\\').DIRECTORY_SEPARATOR.'.htaccess';
			// Apache 2.4 usa mod_authz_core (Require); el "Deny" legacy de 2.2 se
			// envuelve en IfModule para no romper el servidor cuando mod_access_compat
			// no esta cargado (provocaba HTTP 500 en vez de denegar limpiamente).
			$reglas = "Options -Indexes\nRequire all denied\n<IfModule mod_access_compat.c>\n    Deny from all\n</IfModule>\n";
			if(!is_file($htaccess) || trim((string)@file_get_contents($htaccess)) !== trim($reglas)){
				file_put_contents($htaccess, $reglas, LOCK_EX);
			}
		}

		private function guardarClaveFirmaSri($clave){
			if(!function_exists('openssl_encrypt')){
				return false;
			}

			$iv = random_bytes(16);
			$cipher = openssl_encrypt((string)$clave, 'AES-256-CBC', $this->claveCriptoSri(), OPENSSL_RAW_DATA, $iv);

			if($cipher === false){
				return false;
			}

			$payload = base64_encode(json_encode([
				'method' => 'AES-256-CBC',
				'iv' => base64_encode($iv),
				'data' => base64_encode($cipher)
			]));

			return $this->guardarClaveFirmaEnBd($payload);
		}

		/** Persiste el blob cifrado de la clave de la firma en la BD (fila unica). */
		private function guardarClaveFirmaEnBd($payload){
			try{
				$conexion = $this->conectar();
				$stmt = $conexion->prepare(
					"INSERT INTO facturas_electronicas_certificado (cert_lock, clave_cifrada)
					 VALUES ('X', :clave)
					 ON DUPLICATE KEY UPDATE clave_cifrada = VALUES(clave_cifrada)"
				);
				return $stmt->execute([':clave' => $payload]);
			}catch(\Throwable $e){
				return false;
			}
		}

		/** Lee el blob cifrado de la clave de la firma desde la BD. */
		private function leerClaveFirmaDeBd(){
			try{
				$stmt = $this->ejecutarConsulta("SELECT clave_cifrada FROM facturas_electronicas_certificado WHERE cert_lock = 'X' LIMIT 1");
				$row = $stmt->fetch();
				return ($row && !empty($row['clave_cifrada'])) ? (string)$row['clave_cifrada'] : '';
			}catch(\Throwable $e){
				return '';
			}
		}

		/** Descifra un blob (base64 de JSON) probando las llaves candidatas. */
		private function descifrarClaveFirma($raw){
			$raw = trim((string)$raw);
			if($raw === '' || !function_exists('openssl_decrypt')){
				return '';
			}
			$payload = json_decode(base64_decode($raw), true);
			if(!is_array($payload) || empty($payload['iv']) || empty($payload['data'])){
				return '';
			}
			$method = $payload['method'] ?? 'AES-256-CBC';
			$data = base64_decode($payload['data']);
			$iv = base64_decode($payload['iv']);
			foreach($this->clavesCriptoSriCandidatas() as $key){
				$clave = openssl_decrypt($data, $method, $key, OPENSSL_RAW_DATA, $iv);
				if($clave !== false && $clave !== ''){
					return (string)$clave;
				}
			}
			return '';
		}

		/**
		 * Migra la clave de la firma desde el archivo legacy (firma.key.php) a la BD,
		 * recifrandola con la llave endurecida. Idempotente.
		 */
		public function migrarClaveFirmaSriABd(){
			if($this->descifrarClaveFirma($this->leerClaveFirmaDeBd()) !== ''){
				return 'YA_EN_BD';
			}
			$path = $this->rutaClaveFirmaSri();
			if(!is_file($path)){
				return 'SIN_ARCHIVO';
			}
			$raw = trim(str_replace('<?php exit; ?>', '', (string)file_get_contents($path)));
			$clave = $this->descifrarClaveFirma($raw);
			if($clave === ''){
				return 'NO_DESCIFRABLE';
			}
			return $this->guardarClaveFirmaSri($clave) ? 'MIGRADA' : 'ERROR_BD';
		}

		private function leerClaveFirmaSri(){
			$config = $this->sriConfig();
			$env = getenv('SENDERO_SRI_FIRMA_CLAVE');
			if($env !== false && $env !== ''){
				return (string)$env;
			}

			if(!empty($config['firma']['clave'])){
				return (string)$config['firma']['clave'];
			}

			// 1. Ubicacion principal: base de datos
			$clave = $this->descifrarClaveFirma($this->leerClaveFirmaDeBd());
			if($clave !== ''){
				return $clave;
			}

			// 2. Fallback: archivo legacy firma.key.php (compatibilidad / pre-migracion)
			$path = $this->rutaClaveFirmaSri();
			if(is_file($path)){
				$raw = trim(str_replace('<?php exit; ?>', '', (string)file_get_contents($path)));
				return $this->descifrarClaveFirma($raw);
			}

			return '';
		}

        private function extraerRucCertificadoSri(array $certInfo){
            $extensions = $certInfo['extensions'] ?? [];
            $valores = [];
            if(!empty($extensions['1.3.6.1.4.1.59382.3.11'])){
                $valores[] = $extensions['1.3.6.1.4.1.59382.3.11'];
            }
            foreach($extensions as $valor){
                $valores[] = $valor;
            }
            foreach($valores as $valor){
                if(preg_match('/\d{13}/', (string)$valor, $match)){
                    return $match[0];
                }
            }
            return '';
        }

		public function obtenerInfoCertificadoSri(){
			$config = $this->sriConfig();
			$archivo = $config['firma']['archivo'] ?? '';
			$info = [
				'estado' => 'NO_CONFIGURADO',
				'archivo' => $archivo ? basename($archivo) : '',
				'ruta' => $archivo,
				'titular' => '',
				'emisor' => '',
                'ruc' => '',
				'serial' => '',
				'valido_desde' => '',
				'valido_hasta' => '',
				'dias_restantes' => null
			];

			if($archivo === '' || !is_file($archivo)){
				return $info;
			}

			$clave = $this->leerClaveFirmaSri();
			if($clave === ''){
				$info['estado'] = 'SIN_CLAVE';
				return $info;
			}

			if(!function_exists('openssl_pkcs12_read')){
				$info['estado'] = 'OPENSSL_NO_DISPONIBLE';
				return $info;
			}

			$certs = [];
			if(!openssl_pkcs12_read(file_get_contents($archivo), $certs, $clave) || empty($certs['cert'])){
				$info['estado'] = 'CLAVE_INVALIDA';
				return $info;
			}

			$certInfo = openssl_x509_parse($certs['cert']);
			if(!is_array($certInfo)){
				$info['estado'] = 'NO_LEIBLE';
				return $info;
			}

			$subject = $certInfo['subject'] ?? [];
			$issuer = $certInfo['issuer'] ?? [];
			$validFrom = $certInfo['validFrom_time_t'] ?? null;
			$validTo = $certInfo['validTo_time_t'] ?? null;
			$dias = $validTo ? (int)floor(($validTo - time()) / 86400) : null;

			$info['estado'] = ($validTo && $validTo < time()) ? 'CADUCADO' : 'VALIDO';
			$info['titular'] = $subject['CN'] ?? ($subject['commonName'] ?? '');
			$info['emisor'] = $issuer['CN'] ?? ($issuer['commonName'] ?? '');
            $info['ruc'] = $this->extraerRucCertificadoSri($certInfo);
            $rucEmisor = preg_replace('/\D+/', '', (string)($config['emisor']['ruc'] ?? ''));
            if($info['ruc'] !== '' && $rucEmisor !== '' && $info['ruc'] !== $rucEmisor){
                $info['estado'] = 'RUC_NO_COINCIDE';
            }
			$info['serial'] = (string)($certInfo['serialNumberHex'] ?? ($certInfo['serialNumber'] ?? ''));
			$info['valido_desde'] = $validFrom ? date('Y-m-d H:i', $validFrom) : '';
			$info['valido_hasta'] = $validTo ? date('Y-m-d H:i', $validTo) : '';
			$info['dias_restantes'] = $dias;

			return $info;
		}

		public function guardarConfiguracionSri(){
			if(!$this->autorizadoConfigSri()){
				return $this->respuestaJson('simple', 'Acceso restringido', 'Solo un usuario administrador puede modificar la configuracion SRI.', 'error');
			}

			$config = $this->sriConfig();
			$formasPago = $config['formas_pago'] ?? [];
			$ambiente = in_array((string)($_POST['ambiente'] ?? '1'), ['1', '2'], true) ? (string)$_POST['ambiente'] : '1';
			$ruc = preg_replace('/\D+/', '', (string)($_POST['ruc'] ?? ''));
			$establecimiento = str_pad(substr(preg_replace('/\D+/', '', (string)($_POST['codigo_establecimiento'] ?? '')), 0, 3), 3, '0', STR_PAD_LEFT);
			$puntoEmision = str_pad(substr(preg_replace('/\D+/', '', (string)($_POST['punto_emision'] ?? '')), 0, 3), 3, '0', STR_PAD_LEFT);
			$iva = (string)($_POST['iva_tarifa_default'] ?? '0');
			$formaPago = (string)($_POST['forma_pago_default'] ?? '20');
			$obligado = strtoupper((string)($_POST['obligado_contabilidad'] ?? 'NO')) === 'SI' ? 'SI' : 'NO';
			$secuencialInicio = max(1, min(999999999, (int)($_POST['secuencial_inicio'] ?? 1)));

			if(strlen($ruc) !== 13 || substr($ruc, -3) === '000'){
				return $this->respuestaJson('simple', 'RUC no valido', 'El RUC del emisor debe tener 13 digitos y terminar con un codigo de establecimiento valido.', 'error');
			}

			if($establecimiento === '000' || $puntoEmision === '000'){
				return $this->respuestaJson('simple', 'Establecimiento no valido', 'El establecimiento y punto de emision deben tener tres digitos y no pueden ser 000.', 'error');
			}

			if(!in_array($iva, ['0', '12', '14', '15'], true)){
				return $this->respuestaJson('simple', 'IVA no valido', 'Seleccione una tarifa IVA permitida por la configuracion del SRI.', 'error');
			}

			if(!isset($formasPago[$formaPago])){
				return $this->respuestaJson('simple', 'Forma de pago no valida', 'Seleccione una forma de pago del catalogo SRI.', 'error');
			}

			$local = [
				'ambiente' => $ambiente,
				'iva_tarifa_default' => (float)$iva,
				'forma_pago_default' => $formaPago,
				'emisor' => [
					'ruc' => $ruc,
					'razon_social' => $this->textoConfigSri($_POST['razon_social'] ?? '', 300),
					'nombre_comercial' => $this->textoConfigSri($_POST['nombre_comercial'] ?? '', 300),
					'direccion_matriz' => $this->textoConfigSri($_POST['direccion_matriz'] ?? '', 300),
					'direccion_establecimiento' => $this->textoConfigSri($_POST['direccion_establecimiento'] ?? '', 300),
					'codigo_establecimiento' => $establecimiento,
					'punto_emision' => $puntoEmision,
					'obligado_contabilidad' => $obligado,
                    'contribuyente_especial' => $this->normalizarNumeroOpcionalSri($_POST['contribuyente_especial'] ?? '', 13),
                    'agente_retencion' => $this->normalizarNumeroOpcionalSri($_POST['agente_retencion'] ?? '', 8),
                    'contribuyente_rimpe' => $this->normalizarTextoOpcionalSri($_POST['contribuyente_rimpe'] ?? '', 300)
				]
			];

			foreach(['razon_social', 'direccion_matriz', 'direccion_establecimiento'] as $campo){
				if($local['emisor'][$campo] === ''){
					return $this->respuestaJson('simple', 'Datos incompletos', 'Complete razon social, direccion matriz y direccion del establecimiento.', 'warning');
				}
			}

			$em = $local['emisor'];
			$guardado = $this->guardarConfigEmisorEnBd([
				'ambiente' => $local['ambiente'],
				'tipo_emision' => (string)($config['tipo_emision'] ?? '1'),
				'iva_tarifa_default' => (float)$local['iva_tarifa_default'],
				'forma_pago_default' => $local['forma_pago_default'],
				'valores_incluyen_iva' => !empty($config['valores_incluyen_iva']) ? 1 : 0,
				'secuencial_inicio' => $secuencialInicio,
				'ruc' => $em['ruc'],
				'razon_social' => $em['razon_social'],
				'nombre_comercial' => $em['nombre_comercial'],
				'direccion_matriz' => $em['direccion_matriz'],
				'direccion_establecimiento' => $em['direccion_establecimiento'],
				'codigo_establecimiento' => $em['codigo_establecimiento'],
				'punto_emision' => $em['punto_emision'],
				'obligado_contabilidad' => $em['obligado_contabilidad'],
				'contribuyente_especial' => $em['contribuyente_especial'],
				'agente_retencion' => $em['agente_retencion'],
				'contribuyente_rimpe' => $em['contribuyente_rimpe'],
			]);

			if(!$guardado){
				return $this->respuestaJson('simple', 'No se pudo guardar', 'No fue posible guardar la configuracion en la base de datos.', 'error');
			}

			return $this->respuestaJson('recargar', 'Configuracion guardada', 'La configuracion de facturacion electronica fue actualizada.', 'success');
		}

		/** Upsert de la fila unica de configuracion del emisor en la BD. */
		private function guardarConfigEmisorEnBd(array $d){
			try{
				$conexion = $this->conectar();
				$stmt = $conexion->prepare(
					"INSERT INTO facturas_electronicas_config
						(config_lock, ambiente, tipo_emision, iva_tarifa_default, forma_pago_default,
						 valores_incluyen_iva, secuencial_inicio, ruc, razon_social, nombre_comercial, direccion_matriz,
						 direccion_establecimiento, codigo_establecimiento, punto_emision,
						 obligado_contabilidad, contribuyente_especial, agente_retencion,
						 contribuyente_rimpe, updated_by)
					 VALUES ('X', :ambiente, :tipo_emision, :iva, :fp, :vii, :sini, :ruc, :rs, :nc, :dm, :de,
						 :ce, :pe, :oc, :cesp, :ar, :rimpe, :uby)
					 ON DUPLICATE KEY UPDATE
						 ambiente=VALUES(ambiente), tipo_emision=VALUES(tipo_emision),
						 iva_tarifa_default=VALUES(iva_tarifa_default), forma_pago_default=VALUES(forma_pago_default),
						 valores_incluyen_iva=VALUES(valores_incluyen_iva), secuencial_inicio=VALUES(secuencial_inicio), ruc=VALUES(ruc),
						 razon_social=VALUES(razon_social), nombre_comercial=VALUES(nombre_comercial),
						 direccion_matriz=VALUES(direccion_matriz), direccion_establecimiento=VALUES(direccion_establecimiento),
						 codigo_establecimiento=VALUES(codigo_establecimiento), punto_emision=VALUES(punto_emision),
						 obligado_contabilidad=VALUES(obligado_contabilidad),
						 contribuyente_especial=VALUES(contribuyente_especial),
						 agente_retencion=VALUES(agente_retencion), contribuyente_rimpe=VALUES(contribuyente_rimpe),
						 updated_by=VALUES(updated_by)"
				);
				return $stmt->execute([
					':ambiente'=>$d['ambiente'], ':tipo_emision'=>$d['tipo_emision'], ':iva'=>$d['iva_tarifa_default'],
					':fp'=>$d['forma_pago_default'], ':vii'=>$d['valores_incluyen_iva'], ':sini'=>$d['secuencial_inicio'], ':ruc'=>$d['ruc'],
					':rs'=>$d['razon_social'], ':nc'=>$d['nombre_comercial'], ':dm'=>$d['direccion_matriz'],
					':de'=>$d['direccion_establecimiento'], ':ce'=>$d['codigo_establecimiento'], ':pe'=>$d['punto_emision'],
					':oc'=>$d['obligado_contabilidad'], ':cesp'=>$d['contribuyente_especial'], ':ar'=>$d['agente_retencion'],
					':rimpe'=>$d['contribuyente_rimpe'], ':uby'=>($_SESSION['usuarioid'] ?? null),
				]);
			}catch(\Throwable $e){
				return false;
			}
		}

		public function subirCertificadoSri(){
			if(!$this->autorizadoConfigSri()){
				return $this->respuestaJson('simple', 'Acceso restringido', 'Solo un usuario administrador puede cargar la firma electronica.', 'error');
			}

			if(empty($_FILES['certificado']['tmp_name']) || ($_FILES['certificado']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK){
				return $this->respuestaJson('simple', 'Archivo requerido', 'Seleccione el archivo .p12 de la firma electronica.', 'warning');
			}

			$clave = (string)($_POST['clave_certificado'] ?? '');
			if($clave === ''){
				return $this->respuestaJson('simple', 'Clave requerida', 'Ingrese la clave de la firma electronica para validarla.', 'warning');
			}

			$nombre = $_FILES['certificado']['name'] ?? '';
			$extension = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
			if(!in_array($extension, ['p12', 'pfx'], true)){
				return $this->respuestaJson('simple', 'Formato no permitido', 'La firma debe ser un archivo .p12 o .pfx.', 'error');
			}

			if((int)($_FILES['certificado']['size'] ?? 0) > 5242880){
				return $this->respuestaJson('simple', 'Archivo muy grande', 'La firma no debe superar 5 MB.', 'error');
			}

			if(!function_exists('openssl_pkcs12_read')){
				return $this->respuestaJson('simple', 'OpenSSL no disponible', 'Active la extension OpenSSL de PHP para validar la firma electronica.', 'error');
			}

			$contenido = file_get_contents($_FILES['certificado']['tmp_name']);
			if($contenido === false || strlen($contenido) < 10){
				return $this->respuestaJson('simple', 'Archivo no legible', 'No fue posible leer el archivo de firma electronica subido.', 'error');
			}

			$lecturaCertificado = $this->leerPkcs12ConVariantes($contenido, $clave, $_FILES['certificado']['tmp_name']);
			if(empty($lecturaCertificado['ok'])){
				return $this->respuestaJson('simple', 'Firma no valida', $lecturaCertificado['mensaje'] ?? 'No fue posible leer el certificado con la clave ingresada.', 'error');
			}
			$certs = $lecturaCertificado['certs'];
			$clave = $lecturaCertificado['clave'];
			$contenido = $lecturaCertificado['contenido'];

			$certInfo = openssl_x509_parse($certs['cert']);
            $config = $this->sriConfig();
            $rucEmisor = preg_replace('/\D+/', '', (string)($config['emisor']['ruc'] ?? ''));
            $rucCertificado = is_array($certInfo) ? $this->extraerRucCertificadoSri($certInfo) : '';
            if($rucCertificado !== '' && $rucEmisor !== '' && $rucCertificado !== $rucEmisor){
                return $this->respuestaJson('simple', 'Firma no corresponde al emisor', 'El RUC del certificado '.$rucCertificado.' no coincide con el RUC emisor configurado '.$rucEmisor.'. Cargue la firma electronica del contribuyente correcto.', 'error');
            }
			if(is_array($certInfo) && !empty($certInfo['validTo_time_t']) && $certInfo['validTo_time_t'] < time()){
				return $this->respuestaJson('simple', 'Firma caducada', 'El certificado seleccionado ya se encuentra caducado.', 'error');
			}

			$destino = $config['firma']['archivo'] ?? (dirname(__DIR__, 2).'/storage/certificados/firma.p12');
			$this->protegerDirectorioSri(dirname($destino));

			if(file_put_contents($destino, $contenido, LOCK_EX) === false){
				return $this->respuestaJson('simple', 'No se pudo guardar', 'Revise permisos de escritura en storage/certificados.', 'error');
			}

			if(!$this->guardarClaveFirmaSri($clave)){
				return $this->respuestaJson('simple', 'Clave no guardada', 'La firma fue validada, pero no se pudo proteger la clave localmente.', 'error');
			}

			// Persistir metadatos del certificado para la administracion de la vista
			$this->guardarMetadatosCertificadoSri();

			return $this->respuestaJson('recargar', 'Firma cargada', 'La firma electronica fue validada y almacenada correctamente.', 'success');
		}

		/** Upsert de los metadatos del certificado (no toca clave_cifrada). */
		private function guardarMetadatosCertificadoSri(){
			try{
				$info = $this->obtenerInfoCertificadoSri();
				$toDt = static function($s){ $s = trim((string)$s); if($s === ''){ return null; } $ts = strtotime($s); return $ts ? date('Y-m-d H:i:s', $ts) : null; };
				$conexion = $this->conectar();
				$stmt = $conexion->prepare(
					"INSERT INTO facturas_electronicas_certificado
						(cert_lock, archivo, nombre_original, ruc_certificado, razon_social, serial, valido_desde, valido_hasta, estado, updated_by)
					 VALUES ('X', :archivo, :nombre, :ruc, :rs, :serial, :vd, :vh, :estado, :uby)
					 ON DUPLICATE KEY UPDATE
						 archivo=VALUES(archivo), nombre_original=VALUES(nombre_original), ruc_certificado=VALUES(ruc_certificado),
						 razon_social=VALUES(razon_social), serial=VALUES(serial), valido_desde=VALUES(valido_desde),
						 valido_hasta=VALUES(valido_hasta), estado=VALUES(estado), updated_by=VALUES(updated_by)"
				);
				return $stmt->execute([
					':archivo'=>(string)($info['ruta'] ?? ''),
					':nombre'=>(string)($info['archivo'] ?? ''),
					':ruc'=>(string)($info['ruc'] ?? ''),
					':rs'=>(string)($info['titular'] ?? ''),
					':serial'=>(string)($info['serial'] ?? ''),
					':vd'=>$toDt($info['valido_desde'] ?? ''),
					':vh'=>$toDt($info['valido_hasta'] ?? ''),
					':estado'=>(string)($info['estado'] ?? 'NO_CONFIGURADO'),
					':uby'=>($_SESSION['usuarioid'] ?? null),
				]);
			}catch(\Throwable $e){
				return false;
			}
		}

		public function probarCertificadoSri(){
			$info = $this->obtenerInfoCertificadoSri();
			if(($info['estado'] ?? '') === 'VALIDO'){
				return $this->respuestaJson('simple', 'Firma valida', 'Certificado vigente hasta '.$info['valido_hasta'].'.', 'success');
			}

			return $this->respuestaJson('simple', 'Firma pendiente', 'Estado actual del certificado: '.($info['estado'] ?? 'NO_CONFIGURADO').'.', 'warning');
		}

		public function probarConexionSri(){
			$config = $this->sriConfig();
			$ambiente = ((string)($config['ambiente'] ?? '1') === '2') ? 'produccion' : 'pruebas';
			$webService = new WebServiceSRIService($config);
			$resultado = $webService->verificarConectividad();
			if(!empty($resultado['recepcion']) && !empty($resultado['autorizacion'])){
				return $this->respuestaJson('simple', 'Conexion SRI disponible', 'Recepcion y autorizacion respondieron en ambiente '.$ambiente.'.', 'success');
			}

			$estado = 'Recepcion: '.(!empty($resultado['recepcion']) ? 'OK' : 'sin respuesta').'. Autorizacion: '.(!empty($resultado['autorizacion']) ? 'OK' : 'sin respuesta').'.';
			return $this->respuestaJson('simple', 'Conexion SRI parcial', $estado, 'warning');
		}

		private function limpiarErroresOpenSsl(){
			while(openssl_error_string() !== false){}
		}

		private function obtenerErroresOpenSsl(){
			$errores = [];
			while($error = openssl_error_string()){
				$errores[] = $error;
			}
			return implode(' | ', array_unique($errores));
		}

		private function variantesClaveCertificado($clave){
			$clave = (string)$clave;
			$variantes = [
				$clave,
				trim($clave),
				html_entity_decode($clave, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
				preg_replace('/^\xEF\xBB\xBF/', '', $clave)
			];

			if(function_exists('mb_convert_encoding')){
				$variantes[] = @mb_convert_encoding($clave, 'ISO-8859-1', 'UTF-8');
				$variantes[] = @mb_convert_encoding($clave, 'Windows-1252', 'UTF-8');
			}
			if(function_exists('iconv')){
				$latin1 = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $clave);
				$win1252 = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $clave);
				if($latin1 !== false){ $variantes[] = $latin1; }
				if($win1252 !== false){ $variantes[] = $win1252; }
			}

			$limpias = [];
			foreach($variantes as $variante){
				if($variante === null || $variante === false || $variante === ''){
					continue;
				}
				$limpias[$variante] = $variante;
			}
			return array_values($limpias);
		}

		private function leerPkcs12ConVariantes($contenido, $clave, $tmpPath){
			$errores = [];
			$requiereLegacy = false;
			foreach($this->variantesClaveCertificado($clave) as $clavePrueba){
				$this->limpiarErroresOpenSsl();
				$certs = [];
				if(openssl_pkcs12_read($contenido, $certs, $clavePrueba) && !empty($certs['cert']) && !empty($certs['pkey'])){
					return [
						'ok'=>true,
						'certs'=>$certs,
						'clave'=>$clavePrueba,
						'contenido'=>$contenido,
						'convertido'=>false
					];
				}
				$error = $this->obtenerErroresOpenSsl();
				if($error !== ''){
					$errores[] = $error;
					if(stripos($error, 'unsupported') !== false || stripos($error, 'inner_evp_generic_fetch') !== false || stripos($error, 'RC2') !== false){
						$requiereLegacy = true;
					}
				}
			}

			if($requiereLegacy){
				foreach($this->variantesClaveCertificado($clave) as $clavePrueba){
					$convertido = $this->convertirP12Legacy($tmpPath, $clavePrueba);
					if($convertido === null){
						continue;
					}
					$this->limpiarErroresOpenSsl();
					$certs = [];
					if(openssl_pkcs12_read($convertido, $certs, $clavePrueba) && !empty($certs['cert']) && !empty($certs['pkey'])){
						return [
							'ok'=>true,
							'certs'=>$certs,
							'clave'=>$clavePrueba,
							'contenido'=>$convertido,
							'convertido'=>true
						];
					}
					$error = $this->obtenerErroresOpenSsl();
					if($error !== ''){
						$errores[] = $error;
					}
				}

				return [
					'ok'=>false,
					'mensaje'=>'El certificado usa cifrado legacy incompatible con OpenSSL 3.x y no pudo convertirse automaticamente. Detalle: '.($errores ? implode(' | ', array_unique($errores)) : 'sin detalle OpenSSL')
				];
			}

			return [
				'ok'=>false,
				'mensaje'=>'No fue posible leer el certificado con la clave ingresada. Detalle OpenSSL: '.($errores ? implode(' | ', array_unique($errores)) : 'sin informacion adicional')
			];
		}

		private function convertirP12Legacy($p12Path, $clave){
			if(!function_exists('exec')){
				return null;
			}

			$openssl = null;
			$apacheDir = null;
			$apacheBase = 'C:/wamp64/bin/apache/';
			if(is_dir($apacheBase)){
				$entries = scandir($apacheBase) ?: [];
				rsort($entries);
				foreach($entries as $entry){
					if(strncmp($entry, 'apache', 6) !== 0){ continue; }
					$candidate = $apacheBase.$entry.'/bin/openssl.exe';
					if(is_file($candidate)){
						$openssl = $candidate;
						$apacheDir = $apacheBase.$entry;
						break;
					}
				}
			}
			if($openssl === null){
				return null;
			}

			$modulesDir = null;
			$moduleCandidates = [
				$apacheDir.'/conf/lib/ossl-modules',
				$apacheDir.'/bin/ossl-modules',
				$apacheDir.'/lib/ossl-modules',
				$apacheDir.'/ossl-modules',
				dirname($openssl).'/ossl-modules'
			];
			$phpBase = 'C:/wamp64/bin/php/';
			if(is_dir($phpBase)){
				$phpEntries = scandir($phpBase) ?: [];
				rsort($phpEntries);
				foreach($phpEntries as $phpEntry){
					if(strncmp($phpEntry, 'php', 3) !== 0){ continue; }
					$moduleCandidates[] = $phpBase.$phpEntry.'/extras/ssl';
					$moduleCandidates[] = $phpBase.$phpEntry.'/extras/openssl';
				}
			}
			foreach($moduleCandidates as $dir){
				if(is_file($dir.'/legacy.dll')){
					$modulesDir = $dir;
					break;
				}
			}

			$prefix = rtrim(sys_get_temp_dir(), '/\\').DIRECTORY_SEPARATOR.'sendero_cert_'.uniqid('', true);
			$tmpPem = $prefix.'.pem';
			$tmpP12 = $prefix.'_new.p12';
			$tmpPassIn = $prefix.'_in.pass';
			$tmpPassOut = $prefix.'_out.pass';
			file_put_contents($tmpPassIn, (string)$clave);
			file_put_contents($tmpPassOut, (string)$clave);

			$prevModules = getenv('OPENSSL_MODULES');
			if($modulesDir !== null){
				putenv('OPENSSL_MODULES='.str_replace('/', '\\', $modulesDir));
			}

			try{
				$providerArg = $modulesDir
					? sprintf(' -provider-path "%s" -provider legacy -provider default', str_replace('/', '\\', $modulesDir))
					: '';
				$cmd1 = sprintf(
					'"%s" pkcs12 -legacy%s -in "%s" -out "%s" -passin file:"%s" -nodes 2>&1',
					str_replace('/', '\\', $openssl),
					$providerArg,
					str_replace('/', '\\', $p12Path),
					str_replace('/', '\\', $tmpPem),
					str_replace('/', '\\', $tmpPassIn)
				);
				$out1 = []; $ret1 = -1;
				exec($cmd1, $out1, $ret1);
				if($ret1 !== 0 || !is_file($tmpPem) || filesize($tmpPem) < 10){
					return null;
				}

				$cmd2 = sprintf(
					'"%s" pkcs12 -export -in "%s" -out "%s" -passin file:"%s" -passout file:"%s" 2>&1',
					str_replace('/', '\\', $openssl),
					str_replace('/', '\\', $tmpPem),
					str_replace('/', '\\', $tmpP12),
					str_replace('/', '\\', $tmpPassIn),
					str_replace('/', '\\', $tmpPassOut)
				);
				$out2 = []; $ret2 = -1;
				exec($cmd2, $out2, $ret2);
				if($ret2 !== 0 || !is_file($tmpP12) || filesize($tmpP12) < 10){
					return null;
				}

				return file_get_contents($tmpP12) ?: null;
			}finally{
				if($modulesDir !== null){
					$prevModules !== false ? putenv('OPENSSL_MODULES='.$prevModules) : putenv('OPENSSL_MODULES=');
				}
				@unlink($tmpPem);
				@unlink($tmpP12);
				@unlink($tmpPassIn);
				@unlink($tmpPassOut);
			}
		}

		private function facturaElectronicaInstalada(){
			try{
				$check = $this->ejecutarConsulta("SHOW TABLES LIKE 'facturas_electronicas'");
				return $check->rowCount() > 0;
			}catch(\Throwable $e){
				return false;
			}
		}

		private function respuestaJson($tipo, $titulo, $texto, $icono='info', array $extra=[]){
			if(!headers_sent()){
				header('Content-Type: application/json; charset=UTF-8');
			}
			return json_encode(array_merge([
				"tipo"=>$tipo,
				"titulo"=>$titulo,
				"texto"=>$texto,
				"icono"=>$icono
			], $extra), JSON_UNESCAPED_UNICODE);
		}

		private function codigoSriTipoIdentificacion($tipo, $identificacion){
			$tipo = strtoupper(trim((string)$tipo));
			$identificacion = preg_replace('/\D+/', '', (string)$identificacion);
			if(in_array($tipo, ['RUC', '04'], true) || strlen($identificacion) === 13){ return '04'; }
			if(in_array($tipo, ['CED', 'CEDULA', '05'], true) || strlen($identificacion) === 10){ return '05'; }
			if(in_array($tipo, ['PAS', 'PASAPORTE', '06'], true)){ return '06'; }
			if(in_array($tipo, ['VENTA_FINAL', 'CONSUMIDOR_FINAL', '07'], true)){ return '07'; }
			return '08';
		}

		public function validarIdentificacionSri($identificacion, $tipo='CED'){
			$codigo = $this->codigoSriTipoIdentificacion($tipo, $identificacion);
			$identificacionLimpia = preg_replace('/\D+/', '', (string)$identificacion);
			if($codigo === '05'){
				return strlen($identificacionLimpia) === 10 && $this->validarCedula($identificacionLimpia);
			}
			if($codigo === '04'){
				return strlen($identificacionLimpia) === 13 && substr($identificacionLimpia, -3) !== '000';
			}
			if($codigo === '07'){
				return $identificacionLimpia === '9999999999999';
			}
			$identificacion = trim((string)$identificacion);
			return strlen($identificacion) >= 3 && strlen($identificacion) <= 20;
		}

		private function limpiarTextoFactura($valor){
			$valor = html_entity_decode((string)$valor, ENT_QUOTES | ENT_HTML5, 'UTF-8');
			$valor = preg_replace('/<br\s*\/?>/i', ' ', $valor);
			$valor = strip_tags($valor);
			$valor = preg_replace('/Warning:\s*Undefined variable \$disabled in .*? on line \d+/i', ' ', $valor);
			$valor = preg_replace('/\s+/', ' ', $valor);
			return trim($valor, " \t\n\r\0\x0B,>");
		}
		private function normalizarTextoSri($valor, $limite=300){
			$valor = $this->limpiarTextoFactura($valor);
			return function_exists('mb_substr') ? mb_substr($valor, 0, $limite, 'UTF-8') : substr($valor, 0, $limite);
		}

		private function tarifaIvaSri($tarifa){
			$config = $this->sriConfig();
			$tarifa = (float)$tarifa;
			$tarifas = $config['impuestos']['IVA']['tarifas'] ?? [];
			$key = (string)(int)$tarifa;
			if(isset($tarifas[$key])){
				return ['codigo'=>'2', 'codigo_porcentaje'=>$tarifas[$key]['codigo'], 'porcentaje'=>(float)$tarifas[$key]['porcentaje']];
			}
			return ['codigo'=>'2', 'codigo_porcentaje'=>'0', 'porcentaje'=>0.00];
		}

		private function estadoFacturaBloqueanteSql($alias = 'FE'){
			$alias = trim((string)$alias);
			$campo = $alias !== '' ? $alias.'.estado_sri' : 'estado_sri';
			return $campo." IN ('PENDIENTE','GENERADA','FIRMADA','ENVIADA','RECIBIDA','AUTORIZADO')";
		}

		private function esErrorSecuencialRegistrado($mensaje){
			$mensaje = strtoupper((string)$mensaje);
			return strpos($mensaje, 'SECUENCIAL REGISTRADO') !== false || preg_match('/(^|[^0-9])45([^0-9]|$)/', $mensaje);
		}

		private function mensajeSecuencialRegistrado(array $factura){
			$numero = ($factura['establecimiento'] ?? '').'-'.($factura['punto_emision'] ?? '').'-'.($factura['secuencial'] ?? '');
			return 'El SRI indica que el secuencial '.$numero.' ya fue registrado. No se debe reenviar la misma clave de acceso; consulte la factura y, si no esta autorizada, genere una nueva factura con el siguiente secuencial.';
		}

		private function reservarSecuencialFactura(\PDO $conexion, $tipoComprobante, $establecimiento, $puntoEmision, $inicio = 1){
			$inicio = max(1, (int)$inicio);
			$insert = $conexion->prepare("INSERT IGNORE INTO facturas_electronicas_secuenciales (tipo_comprobante, establecimiento, punto_emision, secuencial_actual) VALUES (:tipo, :estab, :pto, 0)");
			$insert->execute([':tipo'=>$tipoComprobante, ':estab'=>$establecimiento, ':pto'=>$puntoEmision]);
			$maxStmt = $conexion->prepare("SELECT COALESCE(MAX(CAST(secuencial AS UNSIGNED)), 0) AS max_secuencial FROM facturas_electronicas WHERE tipo_comprobante = :tipo AND establecimiento = :estab AND punto_emision = :pto");
			$maxStmt->execute([':tipo'=>$tipoComprobante, ':estab'=>$establecimiento, ':pto'=>$puntoEmision]);
			$maxUsado = (int)($maxStmt->fetchColumn() ?: 0);
			$inicioSeguro = max($inicio, $maxUsado + 1);
			$update = $conexion->prepare("UPDATE facturas_electronicas_secuenciales SET secuencial_actual = LAST_INSERT_ID(GREATEST(secuencial_actual + 1, CAST(:inicio AS UNSIGNED))) WHERE tipo_comprobante = :tipo AND establecimiento = :estab AND punto_emision = :pto");
			$update->execute([':tipo'=>$tipoComprobante, ':estab'=>$establecimiento, ':pto'=>$puntoEmision, ':inicio'=>$inicioSeguro]);
			$secuencial = (int)$conexion->lastInsertId();
			if($secuencial <= 0){ throw new \RuntimeException('No fue posible reservar el secuencial de la factura.'); }
			return str_pad((string)$secuencial, 9, '0', STR_PAD_LEFT);
		}

		private function obtenerRepresentanteFactura(\PDO $conexion, $alumnoid){
			$stmt = $conexion->prepare("SELECT A.alumno_id, A.alumno_repreid, R.repre_id, R.repre_identificacion, R.repre_direccion, R.repre_correo, R.repre_celular, R.repre_tipoidentificacion, CONCAT(R.repre_primernombre, ' ', R.repre_segundonombre, ' ', R.repre_apellidopaterno, ' ', R.repre_apellidomaterno) AS representante FROM sujeto_alumno A INNER JOIN alumno_representante R ON R.repre_id = A.alumno_repreid WHERE A.alumno_id = :alumno");
			$stmt->execute([':alumno'=>(int)$alumnoid]);
			$representante = $stmt->fetch(\PDO::FETCH_ASSOC);
			if(!$representante){ throw new \RuntimeException('No se encontro el representante del alumno.'); }
			return $representante;
		}

		private function obtenerPagosFacturaElectronica(\PDO $conexion, $representanteId, array $pagosIds){
			$pagosIds = array_values(array_unique(array_filter(array_map('intval', $pagosIds))));
			if(empty($pagosIds)){ throw new \RuntimeException('Seleccione al menos un pago para facturar.'); }
			$placeholders = [];
			$params = [':representante'=>(int)$representanteId];
			foreach($pagosIds as $i=>$pagoId){
				$key = ':pago'.$i;
				$placeholders[] = $key;
				$params[$key] = $pagoId;
			}
			$sql = "SELECT P.pago_id, P.pago_valor, P.pago_periodo, P.pago_concepto, C.catalogo_valor AS codigo, C.catalogo_descripcion, CONCAT(C.catalogo_descripcion, ' ', P.pago_periodo, ', ', P.pago_concepto) AS detalle, CONCAT(AL.alumno_primernombre, ' ', AL.alumno_segundonombre, ' ', AL.alumno_apellidopaterno, ' ', AL.alumno_apellidomaterno) AS alumno FROM alumno_representante R INNER JOIN sujeto_alumno AL ON AL.alumno_repreid = R.repre_id INNER JOIN alumno_pago P ON P.pago_alumnoid = AL.alumno_id INNER JOIN general_tabla_catalogo C ON C.catalogo_valor = P.pago_rubroid WHERE R.repre_id = :representante AND P.pago_estado = 'C' AND P.pago_id IN (".implode(',', $placeholders).") AND NOT EXISTS (SELECT 1 FROM facturas_electronicas_detalle FD INNER JOIN facturas_electronicas FE ON FE.id = FD.factura_electronica_id WHERE FD.pago_id = P.pago_id AND ".$this->estadoFacturaBloqueanteSql('FE').")";
			$stmt = $conexion->prepare($sql);
			$stmt->execute($params);
			$pagos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
			if(count($pagos) !== count($pagosIds)){ throw new \RuntimeException('Uno o mas pagos ya fueron facturados o no pertenecen al representante seleccionado.'); }
			return $pagos;
		}

		private function crearRideHtml(array $factura, array $detalles, array $config, $archivo){
			$emisor = $config['emisor'];
			$ambiente = (($factura['ambiente'] ?? '1') === '2') ? 'PRODUCCION' : 'PRUEBAS';
			$h = function($valor){ return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8'); };
			$estadoSri = (string)($factura['estado_sri'] ?? 'GENERADA');
			$estaAutorizada = $estadoSri === 'AUTORIZADO';
			$numeroAutorizacion = trim((string)($factura['numero_autorizacion'] ?? ''));
			$fechaAutorizacion = trim((string)($factura['fecha_autorizacion'] ?? ''));
			$bannerClase = $estaAutorizada ? 'ok' : 'warn';
			$bannerTexto = $estaAutorizada
				? '<strong>Documento autorizado por el SRI.</strong> Este RIDE corresponde al comprobante electronico autorizado.'
				: '<strong>Documento generado localmente.</strong> Para validez tributaria debe firmarse electronicamente y autorizarse en el SRI.';
			$filas = '';
			foreach($detalles as $detalle){
				$filas .= '<tr><td>'.$h($detalle['codigo']).'</td><td class="right">'.number_format((float)$detalle['cantidad'], 2, '.', '').'</td><td>'.$h($detalle['descripcion']).'</td><td class="right">'.number_format((float)$detalle['precio_unitario'], 2, '.', '').'</td><td class="right">'.number_format((float)$detalle['descuento'], 2, '.', '').'</td><td class="right">'.number_format((float)$detalle['precio_total_sin_impuesto'], 2, '.', '').'</td></tr>';
			}
			// Logo de la sede a la que pertenece el alumno (seccion de la escuela)
			$logoHtml = '';
			$alumnoId = (int)($factura['alumno_id'] ?? 0);
			if($alumnoId > 0){
				try{
					$sedeRow = $this->ejecutarConsulta("SELECT S.sede_foto FROM sujeto_alumno A INNER JOIN general_sede S ON S.sede_id = A.alumno_sedeid WHERE A.alumno_id = :alumno LIMIT 1", [':alumno' => $alumnoId])->fetch();
					$sedeFoto = trim((string)($sedeRow['sede_foto'] ?? ''));
					if($sedeFoto !== ''){
						$logoHtml = '<img src="'.$h(APP_URL.'app/views/imagenes/fotos/sedes/'.$sedeFoto).'" alt="Logo sede" style="max-height:80px;max-width:220px;display:block;margin-bottom:8px">';
					}
				}catch(\Throwable $e){ /* sin logo si no se puede resolver la sede */ }
			}

			$html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><title>RIDE '.$h($factura['numero']).'</title><style>body{font-family:Arial,sans-serif;font-size:12px;color:#222}.wrap{max-width:920px;margin:24px auto}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.box{border:1px solid #555;padding:10px}h1,h2,h3{margin:0 0 8px}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{border:1px solid #999;padding:6px}th{background:#eee}.right{text-align:right}.warn{background:#fff3cd;border:1px solid #ffeeba;padding:8px;margin:10px 0}.ok{background:#d4edda;border:1px solid #c3e6cb;padding:8px;margin:10px 0}</style></head><body><div class="wrap"><div class="'.$bannerClase.'">'.$bannerTexto.'</div><div class="grid"><div class="box">'.$logoHtml.'<h2>'.$h($emisor['razon_social'] ?? '').'</h2><p><strong>RUC:</strong> '.$h($emisor['ruc'] ?? '').'</p><p><strong>Nombre comercial:</strong> '.$h($emisor['nombre_comercial'] ?? '').'</p><p><strong>Direccion matriz:</strong> '.$h($emisor['direccion_matriz'] ?? '').'</p><p><strong>Direccion establecimiento:</strong> '.$h($emisor['direccion_establecimiento'] ?? '').'</p><p><strong>Obligado contabilidad:</strong> '.$h($emisor['obligado_contabilidad'] ?? 'NO').'</p></div><div class="box"><h1>Factura</h1><p><strong>No.:</strong> '.$h($factura['numero']).'</p><p><strong>Clave de acceso:</strong> '.$h($factura['clave_acceso']).'</p><p><strong>Numero autorizacion:</strong> '.$h($numeroAutorizacion !== '' ? $numeroAutorizacion : 'Pendiente').'</p><p><strong>Fecha autorizacion:</strong> '.$h($fechaAutorizacion !== '' ? $fechaAutorizacion : 'Pendiente').'</p><p><strong>Estado:</strong> '.$h($estadoSri).'</p><p><strong>Ambiente:</strong> '.$h($ambiente).'</p><p><strong>Emision:</strong> NORMAL</p></div></div><div class="box" style="margin-top:16px"><h3>Datos del cliente</h3><p><strong>Cliente:</strong> '.$h($factura['cliente_razon_social']).'</p><p><strong>Identificacion:</strong> '.$h($factura['cliente_identificacion']).'</p><p><strong>Direccion:</strong> '.$h($factura['cliente_direccion']).'</p><p><strong>Email:</strong> '.$h($factura['cliente_email']).'</p></div><table><thead><tr><th>Codigo</th><th>Cantidad</th><th>Detalle</th><th>Precio unitario</th><th>Descuento</th><th>Total sin impuesto</th></tr></thead><tbody>'.$filas.'</tbody></table><table><tr><td class="right"><strong>Subtotal IVA</strong></td><td class="right">'.number_format((float)$factura['subtotal_iva'], 2, '.', '').'</td></tr><tr><td class="right"><strong>Subtotal 0%</strong></td><td class="right">'.number_format((float)$factura['subtotal_0'], 2, '.', '').'</td></tr><tr><td class="right"><strong>IVA</strong></td><td class="right">'.number_format((float)$factura['iva'], 2, '.', '').'</td></tr><tr><td class="right"><strong>Valor total</strong></td><td class="right"><strong>'.number_format((float)$factura['total'], 2, '.', '').'</strong></td></tr></table></div></body></html>';
			$directorioRide = dirname($archivo);
			if(!is_dir($directorioRide)){
				$warningDirectorio = '';
				set_error_handler(function($errno, $errstr) use (&$warningDirectorio){
					$warningDirectorio = trim((string)$errstr);
					return true;
				});
				try{
					$directorioCreado = mkdir($directorioRide, 0755, true);
				}finally{
					restore_error_handler();
				}
				if(!$directorioCreado && !is_dir($directorioRide)){
					$detalle = $warningDirectorio !== '' ? ' Detalle tecnico: '.$warningDirectorio : '';
					throw new \RuntimeException('No fue posible crear el directorio del RIDE. Revise permisos en storage/sri/ride.'.$detalle);
				}
			}

			$warningEscritura = '';
			set_error_handler(function($errno, $errstr) use (&$warningEscritura){
				$warningEscritura = trim((string)$errstr);
				return true;
			});
			try{
				$rideEscrito = file_put_contents($archivo, $html, LOCK_EX);
			}finally{
				restore_error_handler();
			}
			if($rideEscrito === false){
				$detalle = $warningEscritura !== '' ? ' Detalle tecnico: '.$warningEscritura : '';
				throw new \RuntimeException('No fue posible guardar el RIDE. Revise permisos en storage/sri/ride.'.$detalle);
			}

			return $archivo;
		}

		/** Genera el RIDE en PDF (FPDF) y lo guarda en $archivo. Devuelve la ruta. */
		private function crearRidePdf(array $factura, array $detalles, array $config, $archivo){
			require_once dirname(__DIR__, 2).'/app/lib/fpdf.php';

			$emisor = $config['emisor'] ?? [];
			$ambiente = (($factura['ambiente'] ?? '1') === '2') ? 'PRODUCCION' : 'PRUEBAS';
			$estadoSri = (string)($factura['estado_sri'] ?? 'GENERADA');
			$estaAutorizada = $estadoSri === 'AUTORIZADO';
			$numeroAutorizacion = trim((string)($factura['numero_autorizacion'] ?? ''));
			$fechaAutorizacion = trim((string)($factura['fecha_autorizacion'] ?? ''));

			// Logo local de la sede del alumno
			$logoPath = '';
			$alumnoId = (int)($factura['alumno_id'] ?? 0);
			if($alumnoId > 0){
				try{
					$row = $this->ejecutarConsulta("SELECT S.sede_foto FROM sujeto_alumno A INNER JOIN general_sede S ON S.sede_id = A.alumno_sedeid WHERE A.alumno_id = :alumno LIMIT 1", [':alumno' => $alumnoId])->fetch();
					$foto = trim((string)($row['sede_foto'] ?? ''));
					if($foto !== ''){
						$cand = dirname(__DIR__, 2).'/app/views/imagenes/fotos/sedes/'.$foto;
						if(is_file($cand)){ $logoPath = $cand; }
					}
				}catch(\Throwable $e){ /* sin logo */ }
			}

			$t = static function($s){ $c = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', (string)$s); return $c !== false ? $c : utf8_decode((string)$s); };
			$money = static function($v){ return number_format((float)$v, 2, '.', ''); };
			$anchoUtil = 186;

			$pdf = new \FPDF('P', 'mm', 'A4');
			$pdf->SetMargins(12, 12, 12);
			$pdf->SetAutoPageBreak(true, 15);
			$pdf->AddPage();

			// Banner de estado
			if($estaAutorizada){ $pdf->SetFillColor(212, 237, 218); } else { $pdf->SetFillColor(255, 243, 205); }
			$pdf->SetFont('Arial', 'B', 9);
			$pdf->SetTextColor(40, 40, 40);
			$pdf->Cell($anchoUtil, 8, $t($estaAutorizada ? 'DOCUMENTO AUTORIZADO POR EL SRI' : 'DOCUMENTO GENERADO LOCALMENTE - pendiente de autorizacion SRI'), 1, 1, 'C', true);
			$pdf->Ln(3);

			// Cabecera: logo + datos del emisor
			$yIni = $pdf->GetY();
			$xTexto = 12;
			if($logoPath !== ''){
				try{ $pdf->Image($logoPath, 12, $yIni, 32); $xTexto = 48; }catch(\Throwable $e){ $xTexto = 12; }
			}
			$anchoTexto = $anchoUtil - ($xTexto - 12);
			$pdf->SetXY($xTexto, $yIni);
			$pdf->SetFont('Arial', 'B', 12);
			$pdf->SetTextColor(0, 0, 0);
			$pdf->MultiCell($anchoTexto, 6, $t($emisor['razon_social'] ?? ''), 0, 'L');
			$pdf->SetFont('Arial', '', 9);
			foreach([
				'RUC: '.($emisor['ruc'] ?? ''),
				'Nombre comercial: '.($emisor['nombre_comercial'] ?? ''),
				'Dir. matriz: '.($emisor['direccion_matriz'] ?? ''),
				'Dir. establecimiento: '.($emisor['direccion_establecimiento'] ?? ''),
				'Obligado a contabilidad: '.($emisor['obligado_contabilidad'] ?? 'NO'),
			] as $l){ $pdf->SetX($xTexto); $pdf->MultiCell($anchoTexto, 5, $t($l), 0, 'L'); }
			$yLogoBottom = ($logoPath !== '') ? $yIni + 26 : $pdf->GetY();
			if($pdf->GetY() < $yLogoBottom){ $pdf->SetY($yLogoBottom); }
			$pdf->Ln(2);

			// Caja: datos de la factura
			$pdf->SetFont('Arial', 'B', 10);
			$pdf->SetFillColor(238, 238, 238);
			$pdf->Cell($anchoUtil, 7, $t('FACTURA  No. '.$factura['numero']), 1, 1, 'L', true);
			$pdf->SetFont('Arial', '', 9);
			$yBox = $pdf->GetY();
			foreach([
				'Clave de acceso: '.$factura['clave_acceso'],
				'Numero de autorizacion: '.($numeroAutorizacion !== '' ? $numeroAutorizacion : 'Pendiente'),
				'Fecha de autorizacion: '.($fechaAutorizacion !== '' ? $fechaAutorizacion : 'Pendiente'),
				'Estado: '.$estadoSri.'     Ambiente: '.$ambiente.'     Emision: NORMAL',
			] as $l){ $pdf->MultiCell($anchoUtil, 5, $t($l), 0, 'L'); }
			$pdf->Rect(12, $yBox, $anchoUtil, $pdf->GetY() - $yBox);
			$pdf->Ln(2);

			// Caja: datos del cliente
			$pdf->SetFont('Arial', 'B', 10);
			$pdf->SetFillColor(238, 238, 238);
			$pdf->Cell($anchoUtil, 7, $t('DATOS DEL CLIENTE'), 1, 1, 'L', true);
			$pdf->SetFont('Arial', '', 9);
			$yBox = $pdf->GetY();
			foreach([
				'Cliente: '.($factura['cliente_razon_social'] ?? ''),
				'Identificacion: '.($factura['cliente_identificacion'] ?? ''),
				'Direccion: '.($factura['cliente_direccion'] ?? ''),
				'Email: '.($factura['cliente_email'] ?? ''),
			] as $l){ $pdf->MultiCell($anchoUtil, 5, $t($l), 0, 'L'); }
			$pdf->Rect(12, $yBox, $anchoUtil, $pdf->GetY() - $yBox);
			$pdf->Ln(3);

			// Tabla de detalle
			$cols = [['Codigo', 20], ['Cant.', 16], ['Detalle', 86], ['P. Unit.', 22], ['Desc.', 20], ['Total', 22]];
			$pdf->SetFont('Arial', 'B', 8);
			$pdf->SetFillColor(238, 238, 238);
			foreach($cols as $c){ $pdf->Cell($c[1], 7, $t($c[0]), 1, 0, 'C', true); }
			$pdf->Ln();
			$pdf->SetFont('Arial', '', 8);
			foreach($detalles as $d){
				$detalleTxt = $t($d['descripcion'] ?? '');
				$nb = $this->fpdfContarLineas($pdf, 86, $detalleTxt);
				$rowH = max(5, $nb * 5);
				$y = $pdf->GetY();
				$pdf->Cell(20, $rowH, $t($d['codigo'] ?? ''), 1, 0, 'L');
				$pdf->Cell(16, $rowH, number_format((float)($d['cantidad'] ?? 1), 2, '.', ''), 1, 0, 'R');
				$xDet = $pdf->GetX();
				$pdf->MultiCell(86, 5, $detalleTxt, 1, 'L');
				$pdf->SetXY($xDet + 86, $y);
				$pdf->Cell(22, $rowH, $money($d['precio_unitario'] ?? 0), 1, 0, 'R');
				$pdf->Cell(20, $rowH, $money($d['descuento'] ?? 0), 1, 0, 'R');
				$pdf->Cell(22, $rowH, $money($d['precio_total_sin_impuesto'] ?? 0), 1, 1, 'R');
				if($pdf->GetY() < $y + $rowH){ $pdf->SetY($y + $rowH); }
			}
			$pdf->Ln(2);

			// Totales (bloque a la derecha)
			$bloqueW = 92; $xTot = 12 + $anchoUtil - $bloqueW;
			foreach([
				['Subtotal IVA', $money($factura['subtotal_iva'] ?? 0), false],
				['Subtotal 0%', $money($factura['subtotal_0'] ?? 0), false],
				['IVA', $money($factura['iva'] ?? 0), false],
				['VALOR TOTAL', $money($factura['total'] ?? 0), true],
			] as $r){
				$pdf->SetX($xTot);
				$pdf->SetFont('Arial', $r[2] ? 'B' : '', 9);
				$pdf->Cell(60, 7, $t($r[0]), 1, 0, 'R');
				$pdf->Cell(32, 7, $r[1], 1, 1, 'R');
			}

			$directorio = dirname($archivo);
			if(!is_dir($directorio)){ @mkdir($directorio, 0755, true); }
			$pdf->Output('F', $archivo);
			return $archivo;
		}

		/** Aproxima cuantas lineas ocupara $texto en un MultiCell de $ancho mm. */
		private function fpdfContarLineas($pdf, $ancho, $texto){
			$ancho = $ancho - 2;
			$texto = str_replace("\r", '', (string)$texto);
			$total = 0;
			foreach(explode("\n", $texto) as $parrafo){
				$palabras = preg_split('/ +/', trim($parrafo));
				if(empty($palabras) || $palabras === ['']){ $total++; continue; }
				$actual = ''; $cuenta = 1;
				foreach($palabras as $w){
					$prueba = $actual === '' ? $w : $actual.' '.$w;
					if($pdf->GetStringWidth($prueba) > $ancho && $actual !== ''){ $cuenta++; $actual = $w; }
					else{ $actual = $prueba; }
				}
				$total += $cuenta;
			}
			return max(1, $total);
		}

		public function contarFacturasGeneradas($alumnoid, $fecha_inicio, $fecha_fin){
			$alumnoid = (int)$alumnoid;
			// Rango de fechas valido -> filtra; vacio/invalido -> cuenta todas
			$parametros = [':alumno' => $alumnoid];
			$filtroFecha = "";

			if(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_inicio)
			   && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_fin)){
				$filtroFecha = " AND FE.fecha_emision BETWEEN :desde AND :hasta";
				$parametros[':desde'] = $fecha_inicio;
				$parametros[':hasta'] = $fecha_fin;
			}

			$consulta = "SELECT COUNT(*) AS total FROM sujeto_alumno A INNER JOIN alumno_representante R ON R.repre_id = A.alumno_repreid INNER JOIN facturas_electronicas FE ON FE.representante_id = R.repre_id WHERE A.alumno_id = :alumno AND FE.estado_sri <> 'ANULADA'".$filtroFecha;
			$datos = $this->ejecutarConsulta($consulta, $parametros)->fetch();
			return (int)($datos['total'] ?? 0);
		}

		public function listarFacturasGeneradas($alumnoid, $fecha_inicio, $fecha_fin){
			$alumnoid = (int)$alumnoid;
			// Rango de fechas valido -> filtra; vacio/invalido -> lista todas (carga inicial)
			$parametros = [':alumno' => $alumnoid];
			$filtroFecha = "";

			if(preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_inicio)
			   && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fecha_fin)){
				$filtroFecha = " AND FE.fecha_emision BETWEEN :desde AND :hasta";
				$parametros[':desde'] = $fecha_inicio;
				$parametros[':hasta'] = $fecha_fin;
			}

			$tabla = '';
			$consulta = "SELECT FE.id, FE.fecha_emision, FE.establecimiento, FE.punto_emision, FE.secuencial, FE.total, FE.estado_sri, FE.clave_acceso, FE.cliente_razon_social, FE.cliente_email, FE.numero_autorizacion, FE.mensaje_error FROM sujeto_alumno A INNER JOIN alumno_representante R ON R.repre_id = A.alumno_repreid INNER JOIN facturas_electronicas FE ON FE.representante_id = R.repre_id WHERE A.alumno_id = :alumno AND FE.estado_sri <> 'ANULADA'".$filtroFecha." ORDER BY FE.fecha_emision DESC, FE.id DESC";
			$datos = $this->ejecutarConsulta($consulta, $parametros)->fetchAll();
			$contador = 1;
			foreach($datos as $rows){
				$numero = $rows['establecimiento'].'-'.$rows['punto_emision'].'-'.$rows['secuencial'];
				$xmlUrl = APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=DESCARGAR_XML&factura_id='.$rows['id'];
				$rideUrl = APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=VER_RIDE&factura_id='.$rows['id'];
				$estado = (string)$rows['estado_sri'];
				$estadoClass = ($estado==='AUTORIZADO') ? 'text-bg-success' : (in_array($estado, ['DEVUELTA','NO_AUTORIZADO','ERROR'], true) ? 'text-bg-danger' : (in_array($estado, ['ENVIADA','RECIBIDA','FIRMADA'], true) ? 'text-bg-info' : 'text-bg-warning'));
				$errorSecuencial = $this->esErrorSecuencialRegistrado($rows['mensaje_error'] ?? '');
				/* RIDE, XML y Consultar son lectura: los ve cualquiera que
				   llegue a la pantalla. Emitir, regenerar y enviar son
				   escrituras y dependen del permiso del rol. */
				$puedeEmitir  = puede_crear('facturasList');
				$puedeGestion = puede_editar('facturasList');

				$acciones = '<a href="'.$rideUrl.'" class="btn btn-sm btn-outline-info" target="_blank"><i class="fas fa-file-invoice"></i> RIDE</a> <a href="'.$xmlUrl.'" class="btn btn-sm btn-outline-secondary"><i class="fas fa-code"></i> XML</a>';
				if($estado !== 'AUTORIZADO'){
					if(!$errorSecuencial && $puedeEmitir){
						$acciones .= ' <button type="button" class="btn btn-sm btn-success btn-emitir-sri" data-id="'.$rows['id'].'"><i class="fas fa-paper-plane"></i> Emitir SRI</button>';
					}
					$acciones .= ' <button type="button" class="btn btn-sm btn-outline-primary btn-consultar-sri" data-id="'.$rows['id'].'"><i class="fas fa-sync-alt"></i> Consultar</button>';
					if(($errorSecuencial || in_array($estado, ['DEVUELTA','NO_AUTORIZADO'], true)) && $puedeGestion){
						$acciones .= ' <button type="button" class="btn btn-sm btn-warning btn-regenerar-factura" data-id="'.$rows['id'].'"><i class="fas fa-redo"></i> Nuevo secuencial</button>';
					}
				}elseif($puedeGestion && filter_var($rows['cliente_email'] ?? '', FILTER_VALIDATE_EMAIL)){
					$acciones .= ' <button type="button" class="btn btn-sm btn-outline-success btn-enviar-factura" data-id="'.$rows['id'].'" data-email="'.htmlspecialchars($rows['cliente_email'], ENT_QUOTES, 'UTF-8').'"><i class="fas fa-envelope"></i> Enviar</button>';
				}
				$autorizacion = !empty($rows['numero_autorizacion']) ? '<br><small class="text-success">Aut.: '.htmlspecialchars($rows['numero_autorizacion'], ENT_QUOTES, 'UTF-8').'</small>' : '';
				$error = !empty($rows['mensaje_error']) ? '<br><small class="text-danger">'.htmlspecialchars($this->limpiarTextoFactura($rows['mensaje_error']), ENT_QUOTES, 'UTF-8').'</small>' : '';
				$tabla .= '<tr><td>'.$contador.'</td><td>'.$rows['fecha_emision'].'</td><td>'.number_format((float)$rows['total'], 2, '.', '').'</td><td><strong>'.$numero.'</strong><br><small class="text-muted">Clave: '.$rows['clave_acceso'].'</small>'.$autorizacion.'</td><td><span class="badge '.$estadoClass.'">'.$estado.'</span>'.$error.'</td><td>'.$acciones.'</td></tr>';
				$contador++;
			}
			if($tabla===''){ $tabla = '<tr><td colspan="6" class="text-center text-muted">'.($filtroFecha === '' ? 'No hay facturas generadas.' : 'No hay facturas generadas en el rango seleccionado.').'</td></tr>'; }
			return $tabla;
		}

		private function extraerMensajesSri(array $resultado){
			$mensajes = [];
			if(!empty($resultado['mensaje'])){
				$mensajes[] = (string)$resultado['mensaje'];
			}
			foreach($resultado['comprobantes'] ?? [] as $comprobante){
				foreach($comprobante['mensajes'] ?? [] as $mensaje){
					$partes = array_filter([
						$mensaje['identificador'] ?? '',
						$mensaje['mensaje'] ?? '',
						$mensaje['informacion_adicional'] ?? '',
						$mensaje['tipo'] ?? ''
					]);
					if(!empty($partes)){
						$mensajes[] = implode(' - ', $partes);
					}
				}
			}
			foreach($resultado['autorizaciones'] ?? [] as $autorizacion){
				foreach($autorizacion['mensajes'] ?? [] as $mensaje){
					$partes = array_filter([
						$mensaje['identificador'] ?? '',
						$mensaje['mensaje'] ?? '',
						$mensaje['informacion_adicional'] ?? '',
						$mensaje['tipo'] ?? ''
					]);
					if(!empty($partes)){
						$mensajes[] = implode(' - ', $partes);
					}
				}
			}
			$texto = trim(implode(' | ', array_unique($mensajes)));
			return $texto !== '' ? $texto : 'El SRI no devolvio un mensaje detallado.';
		}

		private function normalizarFechaAutorizacionSri($fecha){
			if(empty($fecha)){
				return date('Y-m-d H:i:s');
			}
			$timestamp = strtotime((string)$fecha);
			return $timestamp ? date('Y-m-d H:i:s', $timestamp) : date('Y-m-d H:i:s');
		}

		private function crearXmlAutorizadoSri(array $autorizacion, $claveAcceso){
			$dom = new \DOMDocument('1.0', 'UTF-8');
			$dom->formatOutput = true;
			$root = $dom->createElement('autorizacion');
			$dom->appendChild($root);
			$root->appendChild($dom->createElement('estado', (string)($autorizacion['estado'] ?? 'AUTORIZADO')));
			$root->appendChild($dom->createElement('numeroAutorizacion', (string)($autorizacion['numero_autorizacion'] ?? $claveAcceso)));
			$root->appendChild($dom->createElement('fechaAutorizacion', (string)($autorizacion['fecha_autorizacion'] ?? date('c'))));
			$root->appendChild($dom->createElement('ambiente', (string)($autorizacion['ambiente'] ?? '')));
			$comprobante = $dom->createElement('comprobante');
			$comprobante->appendChild($dom->createCDATASection((string)($autorizacion['comprobante'] ?? '')));
			$root->appendChild($comprobante);
			return $dom->saveXML();
		}

		private function obtenerFacturaElectronicaPorId(\PDO $conexion, $facturaId){
			$stmt = $conexion->prepare("SELECT * FROM facturas_electronicas WHERE id = :id LIMIT 1");
			$stmt->execute([':id'=>(int)$facturaId]);
			$factura = $stmt->fetch(\PDO::FETCH_ASSOC);
			if(!$factura){
				throw new \RuntimeException('Factura electronica no encontrada.');
			}
			return $factura;
		}

		private function obtenerDetallesRideFactura(\PDO $conexion, $facturaId){
			$stmt = $conexion->prepare("SELECT codigo_principal AS codigo, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto FROM facturas_electronicas_detalle WHERE factura_electronica_id = :id ORDER BY id");
			$stmt->execute([':id'=>(int)$facturaId]);
			return $stmt->fetchAll(\PDO::FETCH_ASSOC);
		}

        private function obtenerDetallesFacturaSri(\PDO $conexion, $facturaId){
            $stmt = $conexion->prepare("SELECT codigo_principal AS codigo, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto, iva_tarifa, iva_codigo_porcentaje, iva_valor FROM facturas_electronicas_detalle WHERE factura_electronica_id = :id ORDER BY id");
            $stmt->execute([':id'=>(int)$facturaId]);
            $detalles = [];
            foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row){
                $detalles[] = [
                    'codigo'=>$row['codigo'],
                    'descripcion'=>$row['descripcion'],
                    'cantidad'=>(float)$row['cantidad'],
                    'precio_unitario'=>(float)$row['precio_unitario'],
                    'descuento'=>(float)$row['descuento'],
                    'precio_total_sin_impuesto'=>(float)$row['precio_total_sin_impuesto'],
                    'impuestos'=>[[
                        'codigo'=>'2',
                        'codigo_porcentaje'=>$row['iva_codigo_porcentaje'],
                        'tarifa'=>(float)$row['iva_tarifa'],
                        'base_imponible'=>(float)$row['precio_total_sin_impuesto'],
                        'valor'=>(float)$row['iva_valor']
                    ]]
                ];
            }
            return $detalles;
        }

        private function obtenerPagosFacturaSri(\PDO $conexion, $facturaId){
            $stmt = $conexion->prepare("SELECT forma_pago, total, plazo, unidad_tiempo FROM facturas_electronicas_pagos WHERE factura_electronica_id = :id ORDER BY id");
            $stmt->execute([':id'=>(int)$facturaId]);
            $pagos = [];
            foreach($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row){
                $pago = ['forma_pago'=>$row['forma_pago'], 'total'=>(float)$row['total']];
                if(!empty($row['plazo'])){
                    $pago['plazo'] = (int)$row['plazo'];
                    $pago['unidad_tiempo'] = $row['unidad_tiempo'] ?: 'dias';
                }
                $pagos[] = $pago;
            }
            return $pagos;
        }

        private function impuestosTotalesFacturaSri(array $detalles){
            $grupos = [];
            foreach($detalles as $detalle){
                foreach($detalle['impuestos'] as $impuesto){
                    $key = $impuesto['codigo'].'|'.$impuesto['codigo_porcentaje'].'|'.$impuesto['tarifa'];
                    if(!isset($grupos[$key])){
                        $grupos[$key] = [
                            'codigo'=>$impuesto['codigo'],
                            'codigo_porcentaje'=>$impuesto['codigo_porcentaje'],
                            'base_imponible'=>0.00,
                            'tarifa'=>(float)$impuesto['tarifa'],
                            'valor'=>0.00
                        ];
                    }
                    $grupos[$key]['base_imponible'] += (float)$impuesto['base_imponible'];
                    $grupos[$key]['valor'] += (float)$impuesto['valor'];
                }
            }
            return array_values($grupos);
        }

        private function regenerarXmlFacturaSri(\PDO $conexion, array $factura, array $config, FacturaElectronicaService $service){
            $detalles = $this->obtenerDetallesFacturaSri($conexion, $factura['id']);
            $pagos = $this->obtenerPagosFacturaSri($conexion, $factura['id']);
            if(empty($detalles)){ throw new \RuntimeException('La factura no tiene detalles para regenerar el XML.'); }
            if(empty($pagos)){ $pagos = [['forma_pago'=>(string)($config['forma_pago_default'] ?? '20'), 'total'=>(float)$factura['total']]]; }
            $datosFactura = [
                'clave_acceso'=>$factura['clave_acceso'],
                'establecimiento'=>$factura['establecimiento'],
                'punto_emision'=>$factura['punto_emision'],
                'secuencial'=>$factura['secuencial'],
                'fecha_emision'=>date('d/m/Y', strtotime($factura['fecha_emision'])),
                'cliente'=>[
                    'tipo_identificacion'=>$factura['cliente_tipo_identificacion'],
                    'identificacion'=>$factura['cliente_identificacion'],
                    'razon_social'=>$factura['cliente_razon_social'],
                    'direccion'=>$factura['cliente_direccion']
                ],
                'totales'=>[
                    'subtotal'=>(float)$factura['subtotal'],
                    'descuento'=>(float)$factura['descuento'],
                    'total'=>(float)$factura['total'],
                    'impuestos'=>$this->impuestosTotalesFacturaSri($detalles)
                ],
                'detalles'=>$detalles,
                'pagos'=>$pagos,
                'info_adicional'=>array_filter([
                    'Email'=>$factura['cliente_email'] ?? '',
                    'Telefono'=>$factura['cliente_telefono'] ?? ''
                ])
            ];
            $xml = $service->generarXMLFactura($datosFactura);
            $validacionXml = $service->validarXML($xml);
            if(empty($validacionXml['valido'])){ throw new \RuntimeException('El XML regenerado no es valido: '.implode('; ', $validacionXml['errores'] ?? [])); }
            $xmlPath = $service->guardarXML($xml, $factura['clave_acceso'], 'generados');
            $this->actualizarFacturaSri($conexion, $factura['id'], [
                'estado_sri'=>'GENERADA',
                'xml_generado'=>$xmlPath,
                'xml_firmado'=>null,
                'xml_autorizado'=>null,
                'numero_autorizacion'=>null,
                'fecha_autorizacion'=>null,
                'mensaje_error'=>null
            ]);
            return $xmlPath;
        }

		private function actualizarFacturaSri(\PDO $conexion, $facturaId, array $campos){
			if(empty($campos)){
				return;
			}
			$set = [];
			$params = [':id'=>(int)$facturaId];
			foreach($campos as $campo=>$valor){
				$set[] = $campo.' = :'.$campo;
				$params[':'.$campo] = $valor;
			}
			$sql = "UPDATE facturas_electronicas SET ".implode(', ', $set)." WHERE id = :id";
			$stmt = $conexion->prepare($sql);
			$stmt->execute($params);
		}

		private function actualizarRideFacturaSri(\PDO $conexion, array $factura, array $config){
			$detalles = $this->obtenerDetallesRideFactura($conexion, $factura['id']);
			$numero = $factura['establecimiento'].'-'.$factura['punto_emision'].'-'.$factura['secuencial'];
			$ridePath = rtrim($config['storage']['ride'], '/\\').DIRECTORY_SEPARATOR.$factura['clave_acceso'].'.html';
			$ridePath = $this->crearRideHtml([
				'numero'=>$numero,
				'alumno_id'=>$factura['alumno_id'] ?? null,
				'clave_acceso'=>$factura['clave_acceso'],
				'estado_sri'=>$factura['estado_sri'],
				'ambiente'=>$factura['ambiente'],
				'cliente_razon_social'=>$factura['cliente_razon_social'],
				'cliente_identificacion'=>$factura['cliente_identificacion'],
				'cliente_direccion'=>$factura['cliente_direccion'],
				'cliente_email'=>$factura['cliente_email'],
				'numero_autorizacion'=>$factura['numero_autorizacion'] ?? '',
				'fecha_autorizacion'=>$factura['fecha_autorizacion'] ?? '',
				'subtotal_iva'=>$factura['subtotal_iva'],
				'subtotal_0'=>$factura['subtotal_0'],
				'iva'=>$factura['iva'],
				'total'=>$factura['total']
			], $detalles, $config, $ridePath);
			$this->actualizarFacturaSri($conexion, $factura['id'], ['ride_html'=>$ridePath]);
			return $ridePath;
		}

		private function emitirFacturaSriLocal($facturaId, $soloConsulta=false){
			$config = $this->sriConfig();
			$conexion = $this->conectar();
			$factura = $this->obtenerFacturaElectronicaPorId($conexion, $facturaId);
			$service = new FacturaElectronicaService($config);

			if($factura['estado_sri'] === 'AUTORIZADO'){
				return [
					'exito'=>true,
					'estado'=>'AUTORIZADO',
					'titulo'=>'Factura ya autorizada',
					'mensaje'=>'La factura ya se encuentra autorizada por el SRI.',
					'factura'=>$factura
				];
			}

			if(!$soloConsulta && in_array((string)$factura['estado_sri'], ['DEVUELTA','ERROR','NO_AUTORIZADO'], true) && $this->esErrorSecuencialRegistrado($factura['mensaje_error'] ?? '')){
				return [
					'exito'=>false,
					'estado'=>(string)$factura['estado_sri'],
					'titulo'=>'Secuencial ya registrado',
					'mensaje'=>$this->mensajeSecuencialRegistrado($factura),
					'factura'=>$factura
				];
			}

			if(!$soloConsulta){
				$xmlFirmadoPath = $factura['xml_firmado'] ?? '';
                $estadoRequiereXmlNuevo = in_array((string)$factura['estado_sri'], ['ERROR', 'DEVUELTA', 'NO_AUTORIZADO'], true);
                if($estadoRequiereXmlNuevo){
                    $factura['xml_generado'] = $this->regenerarXmlFacturaSri($conexion, $factura, $config, $service);
                    $factura['xml_firmado'] = '';
                    $factura['xml_autorizado'] = '';
                    $xmlFirmadoPath = '';
                }
				$debeFirmar = $xmlFirmadoPath === '' || !is_file($xmlFirmadoPath) || in_array((string)$factura['estado_sri'], ['ERROR', 'DEVUELTA', 'NO_AUTORIZADO'], true);
				if($debeFirmar){
					$xmlGeneradoPath = $factura['xml_generado'] ?? '';
					if($xmlGeneradoPath === '' || !is_file($xmlGeneradoPath)){
						throw new \RuntimeException('No se encontro el XML generado para firmar.');
					}
					$claveFirma = $this->leerClaveFirmaSri();
					if($claveFirma === ''){
						throw new \RuntimeException('Cargue la firma electronica y su clave antes de emitir.');
					}
					$firma = new FirmaElectronicaService($config);
					$firma->cargarCertificado($config['firma']['archivo'] ?? '', $claveFirma);
					$xmlFirmado = $firma->firmarXML(file_get_contents($xmlGeneradoPath));
					if(!$firma->verificarFirma($xmlFirmado)){
						throw new \RuntimeException('La firma electronica no pudo verificarse estructuralmente.');
					}
					$xmlFirmadoPath = $service->guardarXML($xmlFirmado, $factura['clave_acceso'], 'firmados');
					$this->actualizarFacturaSri($conexion, $facturaId, [
						'estado_sri'=>'FIRMADA',
						'xml_firmado'=>$xmlFirmadoPath,
						'mensaje_error'=>null
					]);
				}else{
					$xmlFirmado = file_get_contents($xmlFirmadoPath);
				}

				$webService = new WebServiceSRIService($config);
				$resultado = $webService->procesarComprobante($xmlFirmado, $factura['clave_acceso']);
			}else{
				$webService = new WebServiceSRIService($config);
				$consulta = $webService->consultarAutorizacion($factura['clave_acceso']);
				$resultado = [
					'exito'=>!empty($consulta['exito']),
					'etapa'=>!empty($consulta['exito']) ? 'autorizado' : 'consulta',
					'resultado'=>$consulta
				];
			}

			$autorizaciones = $resultado['resultado']['autorizaciones'] ?? [];
			$autorizacion = null;
			foreach($autorizaciones as $item){
				if(($item['estado'] ?? '') === 'AUTORIZADO'){
					$autorizacion = $item;
					break;
				}
			}
			if($autorizacion === null && !empty($autorizaciones)){
				$autorizacion = $autorizaciones[0];
			}

			if($autorizacion && ($autorizacion['estado'] ?? '') === 'NO AUTORIZADO'){
				$mensajeNoAutorizado = $this->extraerMensajesSri(['autorizaciones'=>[$autorizacion]]);
				$this->actualizarFacturaSri($conexion, $facturaId, [
					'estado_sri'=>'NO_AUTORIZADO',
					'mensaje_error'=>$mensajeNoAutorizado
				]);
				$facturaActualizada = $this->obtenerFacturaElectronicaPorId($conexion, $facturaId);
				return [
					'exito'=>false,
					'estado'=>'NO_AUTORIZADO',
					'titulo'=>'Factura no autorizada',
					'mensaje'=>$mensajeNoAutorizado,
					'factura'=>$facturaActualizada
				];
			}

			if(!empty($resultado['exito']) && $autorizacion){
				$xmlAutorizado = $this->crearXmlAutorizadoSri($autorizacion, $factura['clave_acceso']);
				$xmlAutorizadoPath = $service->guardarXML($xmlAutorizado, $factura['clave_acceso'], 'autorizados');
				$this->actualizarFacturaSri($conexion, $facturaId, [
					'estado_sri'=>'AUTORIZADO',
					'numero_autorizacion'=>($autorizacion['numero_autorizacion'] ?? $factura['clave_acceso']),
					'fecha_autorizacion'=>$this->normalizarFechaAutorizacionSri($autorizacion['fecha_autorizacion'] ?? null),
					'xml_autorizado'=>$xmlAutorizadoPath,
					'mensaje_error'=>null
				]);
				$facturaActualizada = $this->obtenerFacturaElectronicaPorId($conexion, $facturaId);
				$this->actualizarRideFacturaSri($conexion, $facturaActualizada, $config);
				return [
					'exito'=>true,
					'estado'=>'AUTORIZADO',
					'titulo'=>'Factura autorizada',
					'mensaje'=>'La factura fue firmada, enviada y autorizada por el SRI.',
					'factura'=>$facturaActualizada
				];
			}

			$etapa = $resultado['etapa'] ?? 'error';
			$mensaje = $this->extraerMensajesSri($resultado['resultado'] ?? $resultado);
			if(in_array($etapa, ['en_procesamiento', 'timeout', 'consulta'], true)){
				$estadoPendiente = !empty($resultado['recepcion']['exito']) ? 'RECIBIDA' : 'ENVIADA';
				$this->actualizarFacturaSri($conexion, $facturaId, [
					'estado_sri'=>$estadoPendiente,
					'mensaje_error'=>$resultado['mensaje'] ?? $mensaje
				]);
				$facturaActualizada = $this->obtenerFacturaElectronicaPorId($conexion, $facturaId);
				return [
					'exito'=>true,
					'estado'=>$estadoPendiente,
					'titulo'=>'Factura enviada',
					'mensaje'=>$resultado['mensaje'] ?? 'La factura esta en procesamiento en el SRI. Consulte nuevamente en unos minutos.',
					'factura'=>$facturaActualizada
				];
			}

			$estadoError = ($etapa === 'no_autorizado') ? 'NO_AUTORIZADO' : (($resultado['resultado']['estado'] ?? '') === 'DEVUELTA' ? 'DEVUELTA' : 'ERROR');
			$this->actualizarFacturaSri($conexion, $facturaId, [
				'estado_sri'=>$estadoError,
				'mensaje_error'=>$mensaje
			]);
			$facturaActualizada = $this->obtenerFacturaElectronicaPorId($conexion, $facturaId);
			return [
				'exito'=>false,
				'estado'=>$estadoError,
				'titulo'=>'Factura no autorizada',
				'mensaje'=>$mensaje,
				'factura'=>$facturaActualizada
			];
		}

		public function emitirFacturaElectronicaSri(){
			$facturaId = (int)($_POST['factura_id'] ?? 0);
			if($facturaId <= 0){
				return $this->respuestaJson('simple', 'Factura no valida', 'No fue posible identificar la factura a emitir.', 'error');
			}
			try{
				$resultado = $this->emitirFacturaSriLocal($facturaId, false);
				$factura = $resultado['factura'] ?? [];
				$tipo = !empty($resultado['exito']) ? (($resultado['estado'] === 'AUTORIZADO') ? 'factura_autorizada' : 'factura_enviada') : 'factura_error_sri';
				return $this->respuestaJson($tipo, $resultado['titulo'], $resultado['mensaje'], !empty($resultado['exito']) ? 'success' : 'error', [
					'factura_id'=>$facturaId,
					'estado_sri'=>$resultado['estado'],
					'numero_autorizacion'=>$factura['numero_autorizacion'] ?? '',
					'xml_url'=>APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=DESCARGAR_XML&factura_id='.$facturaId,
					'ride_url'=>APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=VER_RIDE&factura_id='.$facturaId
				]);
			}catch(\Throwable $e){
				return $this->respuestaJson('simple', 'No fue posible emitir', $e->getMessage(), 'error');
			}
		}

		public function consultarFacturaElectronicaSri(){
			$facturaId = (int)($_POST['factura_id'] ?? 0);
			if($facturaId <= 0){
				return $this->respuestaJson('simple', 'Factura no valida', 'No fue posible identificar la factura a consultar.', 'error');
			}
			try{
				$resultado = $this->emitirFacturaSriLocal($facturaId, true);
				$factura = $resultado['factura'] ?? [];
				$tipo = !empty($resultado['exito']) ? (($resultado['estado'] === 'AUTORIZADO') ? 'factura_autorizada' : 'factura_enviada') : 'factura_error_sri';
				return $this->respuestaJson($tipo, $resultado['titulo'], $resultado['mensaje'], !empty($resultado['exito']) ? 'success' : 'warning', [
					'factura_id'=>$facturaId,
					'estado_sri'=>$resultado['estado'],
					'numero_autorizacion'=>$factura['numero_autorizacion'] ?? '',
					'xml_url'=>APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=DESCARGAR_XML&factura_id='.$facturaId,
					'ride_url'=>APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=VER_RIDE&factura_id='.$facturaId
				]);
			}catch(\Throwable $e){
				return $this->respuestaJson('simple', 'No fue posible consultar', $e->getMessage(), 'error');
			}
		}

		private function limpiarHeaderCorreo($valor){
			return trim(preg_replace('/[\r\n]+/', ' ', (string)$valor));
		}

		private function nombreAdjuntoSeguro($nombre){
			$nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string)$nombre);
			return trim($nombre, '._') ?: 'adjunto';
		}

		private function enviarCorreoConAdjuntos($destinatario, $asunto, $mensaje, $from, array $adjuntos){
			if(!function_exists('mail')){
				throw new \RuntimeException('La funcion mail() no esta disponible en este servidor.');
			}

			// Mismo esquema que "Enviar recibo" (pagosReciboEnvio), que si entrega:
			// mail() con asunto y cuerpo en ISO-8859-1 y parte de texto 7bit.
			// El esquema previo (UTF-8 / 8bit / asunto RFC2047) era rechazado por
			// el MTA del servidor, por eso no llegaban los correos de facturas.
			$destinatario = $this->limpiarHeaderCorreo($destinatario);
			$from = $this->limpiarHeaderCorreo($from);
			$asunto = mb_convert_encoding($this->limpiarHeaderCorreo($asunto), 'ISO-8859-1', 'UTF-8');
			$mensaje = mb_convert_encoding($mensaje, 'ISO-8859-1', 'UTF-8');

			$uid = md5(uniqid((string)time()));
			$headers = "From: ".$from;
			$headers .= "\r\nMIME-Version: 1.0\r\n";
			$headers .= "Content-Type: multipart/mixed; boundary=\"".$uid."\"\r\n\r\n";

			$body = "--".$uid."\r\n";
			$body .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";
			$body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
			$body .= $mensaje."\r\n\r\n";

			foreach($adjuntos as $adjunto){
				$path = $adjunto['path'] ?? '';
				if($path === '' || !is_file($path)){
					throw new \RuntimeException('No se encontro el adjunto de la factura.');
				}
				$filename = $this->nombreAdjuntoSeguro($adjunto['filename'] ?? basename($path));
				$contentType = $this->limpiarHeaderCorreo($adjunto['content_type'] ?? 'application/octet-stream');
				$contenido = chunk_split(base64_encode(file_get_contents($path)));
				$body .= "--".$uid."\r\n";
				$body .= "Content-Type: ".$contentType."; name=\"".$filename."\"\r\n";
				$body .= "Content-Transfer-Encoding: base64\r\n";
				$body .= "Content-Disposition: attachment; filename=\"".$filename."\"\r\n\r\n";
				$body .= $contenido."\r\n\r\n";
			}

			$body .= "--".$uid."--";

			$warningMail = '';
			set_error_handler(function($errno, $errstr) use (&$warningMail){
				$warningMail = trim((string)$errstr);
				return true;
			});
			try{
				$enviado = mail($destinatario, $asunto, $body, $headers);
			}finally{
				restore_error_handler();
			}

			if(!$enviado){
				$detalle = $warningMail !== '' ? ' Detalle tecnico: '.$warningMail : '';
				throw new \RuntimeException('El servidor no confirmo el envio del correo (mail()). Verifique la configuracion del correo saliente del servidor.'.$detalle);
			}

			return true;
		}

		private function enviarCorreoFacturaSriLocal($facturaId){
			$config = $this->sriConfig();
			$conexion = $this->conectar();
			$factura = $this->obtenerFacturaElectronicaPorId($conexion, $facturaId);
			if(($factura['estado_sri'] ?? '') !== 'AUTORIZADO'){
				throw new \RuntimeException('Solo se pueden enviar facturas autorizadas por el SRI.');
			}

			$email = trim((string)($factura['cliente_email'] ?? ''));
			if(!filter_var($email, FILTER_VALIDATE_EMAIL)){
				throw new \RuntimeException('El correo del cliente no es valido.');
			}

			$xmlPath = $factura['xml_autorizado'] ?? '';
			if($xmlPath === '' || !is_file($xmlPath)){
				throw new \RuntimeException('No se encontro el XML autorizado para adjuntar.');
			}

			$ridePath = $this->actualizarRideFacturaSri($conexion, $factura, $config);

			$numero = $factura['establecimiento'].'-'.$factura['punto_emision'].'-'.$factura['secuencial'];

			// RIDE en PDF (legible) para adjuntar al correo
			$detallesRide = $this->obtenerDetallesRideFactura($conexion, $factura['id']);
			$ridePdfPath = rtrim($config['storage']['ride'], '/\\').DIRECTORY_SEPARATOR.$factura['clave_acceso'].'.pdf';
			$this->crearRidePdf([
				'numero'=>$numero,
				'alumno_id'=>$factura['alumno_id'] ?? null,
				'clave_acceso'=>$factura['clave_acceso'],
				'estado_sri'=>$factura['estado_sri'],
				'ambiente'=>$factura['ambiente'],
				'cliente_razon_social'=>$factura['cliente_razon_social'],
				'cliente_identificacion'=>$factura['cliente_identificacion'],
				'cliente_direccion'=>$factura['cliente_direccion'],
				'cliente_email'=>$factura['cliente_email'],
				'numero_autorizacion'=>$factura['numero_autorizacion'] ?? '',
				'fecha_autorizacion'=>$factura['fecha_autorizacion'] ?? '',
				'subtotal_iva'=>$factura['subtotal_iva'],
				'subtotal_0'=>$factura['subtotal_0'],
				'iva'=>$factura['iva'],
				'total'=>$factura['total'],
			], $detallesRide, $config, $ridePdfPath);

			$emisor = $config['emisor'] ?? [];
			$nombreEmisor = trim((string)($emisor['nombre_comercial'] ?? ''));
			if($nombreEmisor === ''){
				$nombreEmisor = trim((string)($emisor['razon_social'] ?? ds_nombre_organizacion()));
			}
			$from = $config['correo']['from'] ?? (getenv('SENDERO_FACTURACION_FROM') ?: ds_email_organizacion());
			$cliente = $this->limpiarTextoFactura($factura['cliente_razon_social'] ?? 'cliente');
			$mensaje = "Estimado/a ".$cliente.",\n\n";
			$mensaje .= "Adjuntamos la factura electronica ".$numero." autorizada por el SRI.\n\n";
			$mensaje .= "Clave de acceso: ".$factura['clave_acceso']."\n";
			$mensaje .= "Numero de autorizacion: ".($factura['numero_autorizacion'] ?? $factura['clave_acceso'])."\n";
			$mensaje .= "Valor total: $".number_format((float)$factura['total'], 2, '.', '')."\n\n";
			$mensaje .= $nombreEmisor;

			$adjuntos = [
				[
					'path'=>$ridePdfPath,
					'filename'=>'RIDE-'.$numero.'.pdf',
					'content_type'=>'application/pdf'
				],
				[
					'path'=>$xmlPath,
					'filename'=>$factura['clave_acceso'].'.xml',
					'content_type'=>'application/xml; charset=UTF-8'
				]
			];

			if(!$this->enviarCorreoConAdjuntos($email, 'Factura electronica '.$numero.' - '.$nombreEmisor, $mensaje, $from, $adjuntos)){
				throw new \RuntimeException('El servidor no confirmo el envio del correo. Revise la configuracion mail() de PHP o SMTP local.');
			}

			return ['email'=>$email, 'numero'=>$numero];
		}

		public function enviarFacturaElectronicaCorreo(){
			$facturaId = (int)($_POST['factura_id'] ?? 0);
			if($facturaId <= 0){
				return $this->respuestaJson('simple', 'Factura no valida', 'No fue posible identificar la factura a enviar.', 'error');
			}
			try{
				$resultado = $this->enviarCorreoFacturaSriLocal($facturaId);
				return $this->respuestaJson('simple', 'Factura enviada', 'Se envio la factura '.$resultado['numero'].' a '.$resultado['email'].'.', 'success');
			}catch(\Throwable $e){
				return $this->respuestaJson('simple', 'No fue posible enviar', $e->getMessage(), 'error');
			}
		}

		public function regenerarFacturaElectronicaDevuelta(){
			$facturaId = (int)($_POST['factura_id'] ?? 0);
			if($facturaId <= 0){
				return $this->respuestaJson('simple', 'Factura no valida', 'No fue posible identificar la factura devuelta.', 'error');
			}
			try{
				$conexion = $this->conectar();
				$factura = $this->obtenerFacturaElectronicaPorId($conexion, $facturaId);
				$estado = (string)($factura['estado_sri'] ?? '');
				$mensaje = (string)($factura['mensaje_error'] ?? '');
				if(!in_array($estado, ['DEVUELTA','NO_AUTORIZADO'], true) && !$this->esErrorSecuencialRegistrado($mensaje)){
					return $this->respuestaJson('simple', 'Factura no regenerable', 'Solo se puede generar un nuevo secuencial para facturas devueltas, no autorizadas o con error de secuencial registrado.', 'warning');
				}
				$stmt = $conexion->prepare("SELECT pago_id FROM facturas_electronicas_detalle WHERE factura_electronica_id = :id AND pago_id IS NOT NULL ORDER BY id");
				$stmt->execute([':id'=>$facturaId]);
				$pagosIds = array_values(array_filter(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN))));
				if(empty($pagosIds)){
					return $this->respuestaJson('simple', 'Sin pagos asociados', 'La factura devuelta no tiene pagos asociados para generar un nuevo secuencial.', 'warning');
				}
				$pagoStmt = $conexion->prepare("SELECT forma_pago FROM facturas_electronicas_pagos WHERE factura_electronica_id = :id ORDER BY id LIMIT 1");
				$pagoStmt->execute([':id'=>$facturaId]);
				$formaPago = (string)($pagoStmt->fetchColumn() ?: '');
				$_POST['alumno'] = (int)$factura['alumno_id'];
				$_POST['pagos'] = $pagosIds;
				$_POST['forma_pago'] = $formaPago;
				return $this->generarFacturaElectronica();
			}catch(\Throwable $e){
				return $this->respuestaJson('simple', 'No fue posible generar nuevo secuencial', $e->getMessage(), 'error');
			}
		}

		public function generarFacturaElectronica(){
			if(!$this->facturaElectronicaInstalada()){
				return $this->respuestaJson('simple', 'Facturacion no configurada', 'Ejecute la migracion de facturacion electronica antes de generar comprobantes.', 'error');
			}
			$config = $this->sriConfig();
			$emisor = $config['emisor'] ?? [];
			$alumnoid = (int)($_POST['alumno'] ?? 0);
			$formasPagoSri = $config['formas_pago'] ?? [];
			$formaPago = (string)($_POST['forma_pago'] ?? '');
			if($formaPago === '' || !isset($formasPagoSri[$formaPago])){
				$formaPago = (string)($config['forma_pago_default'] ?? '20');
			}
			$pagosIds = $_POST['pagos'] ?? ($_POST['pagos_seleccionados'] ?? []);
			if(!is_array($pagosIds)){ $pagosIds = [$pagosIds]; }
			if($alumnoid <= 0){ return $this->respuestaJson('simple', 'Alumno no valido', 'No fue posible identificar el alumno para la factura.', 'error'); }
			if(empty($pagosIds)){ return $this->respuestaJson('simple', 'Seleccione pagos', 'Seleccione al menos un pago pendiente para generar la factura.', 'warning'); }
			$ruc = preg_replace('/\D+/', '', (string)($emisor['ruc'] ?? ''));
			if(strlen($ruc) !== 13){ return $this->respuestaJson('simple', 'RUC emisor no valido', 'Configure un RUC de 13 digitos en config/sri.php o en las variables SENDERO_SRI_*.', 'error'); }
			$conexion = $this->conectar();
			try{
				$conexion->beginTransaction();
				$representante = $this->obtenerRepresentanteFactura($conexion, $alumnoid);
				$tipoIdentificacion = $this->codigoSriTipoIdentificacion($representante['repre_tipoidentificacion'], $representante['repre_identificacion']);
				if(!$this->validarIdentificacionSri($representante['repre_identificacion'], $representante['repre_tipoidentificacion'])){ throw new \RuntimeException('La identificacion del representante no es valida para facturacion SRI.'); }
				if(!filter_var($representante['repre_correo'], FILTER_VALIDATE_EMAIL)){ throw new \RuntimeException('El correo del representante no es valido.'); }
				$pagos = $this->obtenerPagosFacturaElectronica($conexion, $representante['repre_id'], $pagosIds);
				$service = new FacturaElectronicaService($config);
				$tipoComprobante = '01';
				$establecimiento = str_pad((string)($emisor['codigo_establecimiento'] ?? '001'), 3, '0', STR_PAD_LEFT);
				$puntoEmision = str_pad((string)($emisor['punto_emision'] ?? '001'), 3, '0', STR_PAD_LEFT);
				$secuencial = $this->reservarSecuencialFactura($conexion, $tipoComprobante, $establecimiento, $puntoEmision, (int)($config['secuencial_inicio'] ?? 1));
				$codigoNumerico = $service->generarCodigoNumerico();
				$fechaEmision = date('Y-m-d');
				$claveAcceso = $service->generarClaveAcceso(date('dmY'), $tipoComprobante, $ruc, (string)($config['ambiente'] ?? '1'), $establecimiento.$puntoEmision, $secuencial, $codigoNumerico, (string)($config['tipo_emision'] ?? '1'));
				$ivaInfo = $this->tarifaIvaSri((float)($config['iva_tarifa_default'] ?? 0));
				$valoresIncluyenIva = !empty($config['valores_incluyen_iva']);
				$detalles = [];
				$subtotalIva = 0.00; $subtotal0 = 0.00; $ivaTotal = 0.00; $total = 0.00;
				foreach($pagos as $pago){
					$totalLinea = round((float)$pago['pago_valor'], 2);
					if($ivaInfo['porcentaje'] > 0){
						if($valoresIncluyenIva){ $base = round($totalLinea / (1 + ($ivaInfo['porcentaje'] / 100)), 2); $ivaLinea = round($totalLinea - $base, 2); }
						else{ $base = $totalLinea; $ivaLinea = round($base * ($ivaInfo['porcentaje'] / 100), 2); $totalLinea = round($base + $ivaLinea, 2); }
						$subtotalIva += $base;
					}else{
						$base = $totalLinea; $ivaLinea = 0.00; $subtotal0 += $base;
					}
					$total += $totalLinea; $ivaTotal += $ivaLinea;
					$detalles[] = [
						'pago_id'=>(int)$pago['pago_id'], 'codigo'=>(string)$pago['codigo'],
						'descripcion'=>$this->normalizarTextoSri('Pago '.$pago['detalle'].' del alumno '.$pago['alumno'], 300),
						'cantidad'=>1.000000, 'precio_unitario'=>$base, 'descuento'=>0.00,
						'precio_total_sin_impuesto'=>$base, 'iva_tarifa'=>$ivaInfo['porcentaje'],
						'iva_codigo_porcentaje'=>$ivaInfo['codigo_porcentaje'], 'iva_valor'=>$ivaLinea,
						'impuestos'=>[['codigo'=>$ivaInfo['codigo'], 'codigo_porcentaje'=>$ivaInfo['codigo_porcentaje'], 'tarifa'=>$ivaInfo['porcentaje'], 'base_imponible'=>$base, 'valor'=>$ivaLinea]]
					];
				}
				$subtotal = round($subtotalIva + $subtotal0, 2); $ivaTotal = round($ivaTotal, 2); $total = round($total, 2);
				$datosFactura = [
					'clave_acceso'=>$claveAcceso, 'establecimiento'=>$establecimiento, 'punto_emision'=>$puntoEmision, 'secuencial'=>$secuencial, 'fecha_emision'=>date('d/m/Y'),
					'cliente'=>['tipo_identificacion'=>$tipoIdentificacion, 'identificacion'=>$this->normalizarTextoSri($representante['repre_identificacion'], 20), 'razon_social'=>$this->normalizarTextoSri($representante['representante'], 300), 'direccion'=>$this->normalizarTextoSri($representante['repre_direccion'], 300)],
					'totales'=>['subtotal'=>$subtotal, 'descuento'=>0.00, 'total'=>$total, 'impuestos'=>[['codigo'=>$ivaInfo['codigo'], 'codigo_porcentaje'=>$ivaInfo['codigo_porcentaje'], 'base_imponible'=>$subtotal, 'tarifa'=>$ivaInfo['porcentaje'], 'valor'=>$ivaTotal]]],
					'detalles'=>$detalles, 'pagos'=>[['forma_pago'=>$formaPago, 'total'=>$total]],
					'info_adicional'=>['Email'=>$representante['repre_correo'], 'Telefono'=>$representante['repre_celular'], 'Generado por'=>$_SESSION['usuario'] ?? 'Sistema']
				];
				$xml = $service->generarXMLFactura($datosFactura);
				$validacionXml = $service->validarXML($xml);
				if(empty($validacionXml['valido'])){ throw new \RuntimeException('El XML generado no es valido: '.implode('; ', $validacionXml['errores'] ?? [])); }
				$xmlPath = $service->guardarXML($xml, $claveAcceso, 'generados');
				$numero = $establecimiento.'-'.$puntoEmision.'-'.$secuencial;
				$insertFactura = $conexion->prepare("INSERT INTO facturas_electronicas (alumno_id, representante_id, clave_acceso, tipo_comprobante, establecimiento, punto_emision, secuencial, fecha_emision, ambiente, tipo_emision, cliente_tipo_identificacion, cliente_identificacion, cliente_razon_social, cliente_direccion, cliente_email, cliente_telefono, subtotal_iva, subtotal_0, subtotal_no_objeto, subtotal_exento, subtotal, descuento, iva, total, estado_sri, xml_generado, created_by) VALUES (:alumno_id, :representante_id, :clave_acceso, :tipo_comprobante, :establecimiento, :punto_emision, :secuencial, :fecha_emision, :ambiente, :tipo_emision, :cliente_tipo_identificacion, :cliente_identificacion, :cliente_razon_social, :cliente_direccion, :cliente_email, :cliente_telefono, :subtotal_iva, :subtotal_0, 0, 0, :subtotal, 0, :iva, :total, 'GENERADA', :xml_generado, :created_by)");
				$insertFactura->execute([':alumno_id'=>$alumnoid, ':representante_id'=>(int)$representante['repre_id'], ':clave_acceso'=>$claveAcceso, ':tipo_comprobante'=>$tipoComprobante, ':establecimiento'=>$establecimiento, ':punto_emision'=>$puntoEmision, ':secuencial'=>$secuencial, ':fecha_emision'=>$fechaEmision, ':ambiente'=>(string)($config['ambiente'] ?? '1'), ':tipo_emision'=>(string)($config['tipo_emision'] ?? '1'), ':cliente_tipo_identificacion'=>$tipoIdentificacion, ':cliente_identificacion'=>$this->normalizarTextoSri($representante['repre_identificacion'], 20), ':cliente_razon_social'=>$this->normalizarTextoSri($representante['representante'], 300), ':cliente_direccion'=>$this->normalizarTextoSri($representante['repre_direccion'], 300), ':cliente_email'=>$representante['repre_correo'], ':cliente_telefono'=>$representante['repre_celular'], ':subtotal_iva'=>round($subtotalIva, 2), ':subtotal_0'=>round($subtotal0, 2), ':subtotal'=>$subtotal, ':iva'=>$ivaTotal, ':total'=>$total, ':xml_generado'=>$xmlPath, ':created_by'=>$_SESSION['id'] ?? null]);
				$facturaId = (int)$conexion->lastInsertId();
				$insertDetalle = $conexion->prepare("INSERT INTO facturas_electronicas_detalle (factura_electronica_id, pago_id, codigo_principal, descripcion, cantidad, precio_unitario, descuento, precio_total_sin_impuesto, iva_tarifa, iva_codigo_porcentaje, iva_valor) VALUES (:factura_id, :pago_id, :codigo, :descripcion, :cantidad, :precio_unitario, :descuento, :precio_total_sin_impuesto, :iva_tarifa, :iva_codigo_porcentaje, :iva_valor)");
				foreach($detalles as $detalle){
					$insertDetalle->execute([':factura_id'=>$facturaId, ':pago_id'=>$detalle['pago_id'], ':codigo'=>$detalle['codigo'], ':descripcion'=>$detalle['descripcion'], ':cantidad'=>$detalle['cantidad'], ':precio_unitario'=>$detalle['precio_unitario'], ':descuento'=>$detalle['descuento'], ':precio_total_sin_impuesto'=>$detalle['precio_total_sin_impuesto'], ':iva_tarifa'=>$detalle['iva_tarifa'], ':iva_codigo_porcentaje'=>$detalle['iva_codigo_porcentaje'], ':iva_valor'=>$detalle['iva_valor']]);
				}
				$insertPago = $conexion->prepare("INSERT INTO facturas_electronicas_pagos (factura_electronica_id, forma_pago, total) VALUES (:factura_id, :forma_pago, :total)");
				$insertPago->execute([':factura_id'=>$facturaId, ':forma_pago'=>$formaPago, ':total'=>$total]);
				$ridePath = rtrim($config['storage']['ride'], '/\\').DIRECTORY_SEPARATOR.$claveAcceso.'.html';
				$ridePath = $this->crearRideHtml(['numero'=>$numero, 'alumno_id'=>$alumnoid, 'clave_acceso'=>$claveAcceso, 'estado_sri'=>'GENERADA', 'ambiente'=>(string)($config['ambiente'] ?? '1'), 'cliente_razon_social'=>$this->normalizarTextoSri($representante['representante'], 300), 'cliente_identificacion'=>$this->normalizarTextoSri($representante['repre_identificacion'], 20), 'cliente_direccion'=>$this->normalizarTextoSri($representante['repre_direccion'], 300), 'cliente_email'=>$representante['repre_correo'], 'subtotal_iva'=>round($subtotalIva, 2), 'subtotal_0'=>round($subtotal0, 2), 'iva'=>$ivaTotal, 'total'=>$total], $detalles, $config, $ridePath);
				$updateRide = $conexion->prepare("UPDATE facturas_electronicas SET ride_html = :ride WHERE id = :id");
				$updateRide->execute([':ride'=>$ridePath, ':id'=>$facturaId]);
				$conexion->commit();
				try{
					$emision = $this->emitirFacturaSriLocal($facturaId, false);
					$tipoRespuesta = !empty($emision['exito']) ? (($emision['estado'] === 'AUTORIZADO') ? 'factura_autorizada' : 'factura_enviada') : 'factura_error_sri';
					$icono = !empty($emision['exito']) ? 'success' : 'error';
					return $this->respuestaJson($tipoRespuesta, $emision['titulo'], $emision['mensaje'], $icono, ['factura_id'=>$facturaId, 'numero'=>$numero, 'clave_acceso'=>$claveAcceso, 'estado_sri'=>$emision['estado'], 'numero_autorizacion'=>$emision['factura']['numero_autorizacion'] ?? '', 'xml_url'=>APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=DESCARGAR_XML&factura_id='.$facturaId, 'ride_url'=>APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=VER_RIDE&factura_id='.$facturaId]);
				}catch(\Throwable $emisionError){
					$this->actualizarFacturaSri($this->conectar(), $facturaId, ['estado_sri'=>'ERROR', 'mensaje_error'=>$emisionError->getMessage()]);
					return $this->respuestaJson('factura_error_sri', 'Factura generada sin autorizar', 'La factura '.$numero.' fue generada, pero no se pudo completar la emision SRI: '.$emisionError->getMessage(), 'error', ['factura_id'=>$facturaId, 'numero'=>$numero, 'clave_acceso'=>$claveAcceso, 'estado_sri'=>'ERROR', 'xml_url'=>APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=DESCARGAR_XML&factura_id='.$facturaId, 'ride_url'=>APP_URL.'app/ajax/facturasAjax.php?modulo_facturas=VER_RIDE&factura_id='.$facturaId]);
				}
			}catch(\Throwable $e){
				if($conexion->inTransaction()){ $conexion->rollBack(); }
				return $this->respuestaJson('simple', 'No fue posible generar la factura', $e->getMessage(), 'error');
			}
		}

		public function obtenerArchivoFacturaElectronica($facturaId, $tipo='xml'){
			$config = $this->sriConfig();
			$facturaId = (int)$facturaId;
			$stmt = $this->ejecutarConsulta("SELECT id, clave_acceso, xml_generado, xml_firmado, xml_autorizado, ride_html FROM facturas_electronicas WHERE id = :factura", [':factura' => $facturaId]);
			if($stmt->rowCount()<=0){ return null; }
			$factura = $stmt->fetch();
			$tipo = strtolower((string)$tipo);
			$archivo = '';
			$storageKey = 'xml_generados';
			if($tipo === 'ride'){
				$archivo = $factura['ride_html'] ?? '';
				$storageKey = 'ride';
			}elseif($tipo === 'autorizado'){
				$archivo = $factura['xml_autorizado'] ?? '';
				$storageKey = 'xml_autorizados';
			}elseif($tipo === 'firmado'){
				$archivo = $factura['xml_firmado'] ?? '';
				$storageKey = 'xml_firmados';
			}elseif($tipo === 'generado'){
				$archivo = $factura['xml_generado'] ?? '';
				$storageKey = 'xml_generados';
			}else{
				if(!empty($factura['xml_autorizado'])){ $archivo = $factura['xml_autorizado']; $storageKey = 'xml_autorizados'; }
				elseif(!empty($factura['xml_firmado'])){ $archivo = $factura['xml_firmado']; $storageKey = 'xml_firmados'; }
				else{ $archivo = $factura['xml_generado'] ?? ''; $storageKey = 'xml_generados'; }
			}
			if($archivo==='' || !is_file($archivo)){ return null; }
			$raiz = realpath($config['storage'][$storageKey] ?? dirname($archivo));
			$real = realpath($archivo);
			if(!$real || !$raiz || strpos($real, $raiz) !== 0){ return null; }
			return ['path'=>$real, 'filename'=>$factura['clave_acceso'].($tipo === 'ride' ? '.html' : '.xml'), 'content_type'=>$tipo === 'ride' ? 'text/html; charset=UTF-8' : 'application/xml; charset=UTF-8'];
		}


		/*  fin funciones para el manejo de facturas */


		/*----------  Controlador registrar alumno  ----------*/
		/*----------  Matriz de alumnos con opciones Ver, Actualizar, Eliminar  ----------*/


		/*----------  Obtener el tipo de documento guardado  ----------*/
		/*----------  Obtener la nacionalidad guardada  ----------*/
		/*----------  Obtener la sede guardada  ----------*/
		/*----------  Obtener la posiciÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â³n de juego guardada  ----------*/
		/*----------  Controlador eliminar alumno  ----------*/
		/*----------  Controlador eliminar alumno  ----------*/
		/*----------  Controlador actualizar alumno  ----------*/
		/*----------  Controlador eliminar foto alumno  ----------*/
		/*----------  Controlador actualizar foto alumno  ----------*/
		# Consultar datos del representante para la vista vincular alumno
		/* ==================================== Roles ==================================== */

		//horarios

	}
