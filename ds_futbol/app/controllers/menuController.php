<?php

	namespace app\controllers;
	use app\models\mainModel;

	class menuController extends mainModel{
		

  

  

		/**
		 * Menú del usuario para un módulo concreto.
		 *
		 * El Super Administrador no tiene filas en seguridad_permiso —pasa
		 * por encima del control de acceso—, así que para él se devuelven
		 * todos los menús activos del módulo. El resto de roles reciben
		 * únicamente aquellos sobre los que tienen permiso de lectura.
		 *
		 * En ambos casos se descartan las filas que son cabecera de grupo.
		 * Sólo existen para dar nombre al grupo (ConstruirMenu lo toma del
		 * JOIN con el padre) y su menu_vista es un relleno: si se dejaban
		 * pasar, el menú del superadministrador mostraba cada grupo dos
		 * veces, la primera como enlace muerto a «No/».
		 *
		 * Se descarta por dos vías: la marca menu_hijo que escribe Core al
		 * crear un grupo —necesaria para que un grupo recién creado y aún
		 * vacío no aparezca como enlace suelto— y, por si acaso, la
		 * estructura, para filas antiguas que no lleven la marca.
		 */
		public function ObtenerMenu($usuario, $modulo = 'basketball'){

			$sinCabeceras = "AND M.menu_hijo <> 'S'
							 AND NOT EXISTS (SELECT 1 FROM seguridad_menu H
											  WHERE H.menu_padreid = M.menu_id
												AND H.menu_estado  = 'A')";

			if(es_superadministrador()){
				$consulta_menu="SELECT M.*, A.menu_nombre AS padre
								FROM seguridad_menu M
								LEFT JOIN seguridad_menu A ON A.menu_id = M.menu_padreid
								WHERE M.menu_estado = 'A'
								  AND M.menu_modulo = :modulo
								  $sinCabeceras
								ORDER BY M.menu_padreid, M.menu_orden";

				$menu = $this->ejecutarConsulta($consulta_menu, [':modulo' => $modulo]);

			}else{
				/* Se resuelve por el rol de la sesión, no por el nombre de
				   usuario: es el dato que gobierna el permiso y evita un
				   JOIN innecesario contra seguridad_usuario. */
				$consulta_menu="SELECT P.permiso_menuid, M.*, A.menu_nombre AS padre
								FROM seguridad_permiso P
								JOIN seguridad_menu    M ON M.menu_id = P.permiso_menuid
								LEFT JOIN seguridad_menu A ON A.menu_id = M.menu_padreid
								WHERE M.menu_estado    = 'A'
								  AND M.menu_modulo    = :modulo
								  AND P.permiso_estado = 'A'
								  AND P.permiso_ver    = 'S'
								  AND P.permiso_rolid  = :rol
								  $sinCabeceras
								ORDER BY M.menu_padreid, M.menu_orden";

				$menu = $this->ejecutarConsulta($consulta_menu, [
					':modulo' => $modulo,
					':rol'    => rol_actual(),
				]);
			}

			$menus = [];
			while ($row = $menu->fetch()) {
				$menus[] = $row;
			}
			return $menus;
		}

		/**
		 * @param array $contadores  vista => numero. Se pinta una insignia en
		 *                           las entradas que aparezcan aqui con un
		 *                           numero mayor que cero.
		 */
		public function ConstruirMenu($menus, $vistaActual = '', array $contadores = []){
			/* La insignia de una entrada del menu, si tiene algo que contar. */
			$insignia = function ($vista) use ($contadores) {
				$v = trim((string) $vista, "/ \t\n\r\0\x0B");
				$n = (int) ($contadores[$v] ?? 0);
				if ($n <= 0) { return ''; }
				/* aria-label porque un numero suelto no dice de que es. */
				return ' <span class="nav-badge badge text-bg-warning ms-auto"'
				     . ' aria-label="' . $n . ' pendientes">' . $n . '</span>';
			};

			$html = '';
			$padreActual = null; // Variable para rastrear el padre actual
            $vistaActual = trim((string) $vistaActual, "/ \t\n\r\0\x0B");
            $vistaActiva = function ($vista) use ($vistaActual) {
                return trim((string) $vista, "/ \t\n\r\0\x0B") === $vistaActual;
            };
            $grupoActivo = function ($padreId) use ($menus, $vistaActiva) {
                foreach ($menus as $item) {
                    if ((int) $item['menu_padreid'] === (int) $padreId && $vistaActiva($item['menu_vista'])) {
                        return true;
                    }
                }

                return false;
            };
		
			if (count($menus) > 0) {
				foreach ($menus as $menu) {
					if ($menu['menu_padreid'] == 0 && $menu['menu_hijo'] == 'N') {
						// Si había un bloque de padre abierto, ciérralo antes
						if (!is_null($padreActual)) {
							$html .= '</ul>';
							$html .= '</li>';
							$padreActual = null; // Reinicia el rastreador de padre
						}
		
						// Menú principal sin hijos
						$html .= '<li class="nav-item">';
						$html .= '<a href="' . APP_URL . $menu['menu_vista'] . '/" class="nav-link' . ($vistaActiva($menu['menu_vista']) ? ' active' : '') . '">';
						$html .= '<i class="'.$menu['menu_icono'].'"></i> <p>' . $menu['menu_nombre'] . $insignia($menu['menu_vista']) . '</p>';
						$html .= '</a>';
						$html .= '</li>';
					} else {
						// Menú con padre y posiblemente hijos
						if ($padreActual !== $menu['padre']) {
							// Si hay un padre diferente, cierra el bloque anterior
							if (!is_null($padreActual)) {
								$html .= '</ul>';
								$html .= '</li>';
							}
		
							/*
							| El nombre del grupo va UNA vez, no dos.
							|
							| Antes se pintaba primero como <li class="nav-header"> y
							| justo debajo otra vez dentro del elemento plegable. En
							| pantalla se leia «Asistencia alumnos» y debajo
							| «Asistencia alumnos» con su flecha. Ocho grupos, ocho
							| repeticiones: ocho filas de ruido que empujaban el resto
							| del menu fuera de la pantalla y no aportaban nada, porque
							| el elemento plegable ya lleva el nombre y el icono.
							*/
							$html .= '<li class="nav-item' . ($grupoActivo($menu['menu_padreid']) ? ' menu-open' : '') . '">';
                            $html .= '<a href="#" class="nav-link' . ($grupoActivo($menu['menu_padreid']) ? ' active' : '') . '">';
							$html .= '<i class="'.$menu['menu_icono'].'"></i>';
							/* nav-arrow, no «right»: la clase que rota la flecha al
							   abrir el grupo se llama asi en AdminLTE 4, y «right»
							   ya no existe. Sin ella la flecha se queda quieta y
							   nada indica si la rama esta abierta o cerrada. */
							$html .= '<p>' . $menu['padre'] . '<i class="fas fa-angle-left nav-arrow"></i></p>';
							$html .= '</a>';
							$html .= '<ul class="nav nav-treeview">';
							$padreActual = $menu['padre']; // Actualiza el rastreador de padre actual
						}
		
						// Agrega los hijos al menú
						$html .= '<li class="nav-item">';
						$html .= '<a href="' . APP_URL . $menu['menu_vista'] . '/" class="nav-link' . ($vistaActiva($menu['menu_vista']) ? ' active' : '') . '">';
						$html .= '<i class="nav-icon far fa-circle text-info"></i>';
						$html .= '<p>' . $menu['menu_nombre'] . $insignia($menu['menu_vista']) . '</p>';
						$html .= '</a>';
						$html .= '</li>';
					}
				}
		
				// Cierra cualquier bloque abierto al final
				if (!is_null($padreActual)) {
					$html .= '</ul>';
					$html .= '</li>';
				}				
			}

			/* «Salir» tambien se repetia: titulo y elemento con el mismo texto. */
						$html .= '	<li class="nav-item">';
			$html .= '	  <a href="'.APP_URL.'logOut/" class="nav-link js-salir">';
			$html .= '		<i class="nav-icon far fa-circle text-danger"></i>';
			$html .= '		<p class="text">Salir</p>';
			$html .= '	  </a>';
			$html .= '	</li>';
			
		
			return $html;
		}
		
		
		
    }