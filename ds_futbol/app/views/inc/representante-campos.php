<?php
/*
|--------------------------------------------------------------------------
| Los campos del representante: alta (representanteNew) y edición
| (representanteUpdate)
|--------------------------------------------------------------------------
| Las dos pantallas pedían lo mismo con dos marcados que se iban separando:
| la edición seguía con pestañas, inputmask y radios marcados por defecto
| cuando el dato faltaba. Aquí está una sola vez.
|
| Recibe:
|   $insRepre   el representanteController (para los catálogos)
|   $valores    campo => valor tal como está en la base (escapado por
|               limpiarHtml); vacío en el alta
|
| NADA SE MARCA POR DEFECTO SI EL DATO NO ESTÁ. La edición antigua marcaba
| «Femenino» cuando no había sexo y «No» cuando no constaba la factura (407
| de 815 representantes), y al guardar lo grababa sin que nadie lo hubiera
| elegido. Ahora queda sin marcar y, como es obligatorio, se pide.
|
| El cónyuge, opcional, va plegado; abierto si ya tiene datos.
*/

$valores = $valores ?? [];
$v = static fn(string $campo) => htmlspecialchars(html_entity_decode((string)($valores[$campo] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8');
$marca = static fn(string $campo, string $valor) => (string)($valores[$campo] ?? '') === $valor ? 'checked' : '';
$hayConyuge = trim(($valores['conyuge_primernombre'] ?? '') . ($valores['conyuge_apellidopaterno'] ?? '') . ($valores['conyuge_identificacion'] ?? '')) !== '';
$CELULAR = 'maxlength="10" pattern="0[0-9]{9}" title="diez cifras empezando por 0, por ejemplo 0991234567" placeholder="09…" data-ds-digitos';
/* El correo es obligatorio en el servidor, y hay padres sin correo: se
   registran con «S/N» (14 en la base, también como «SN»). Por eso no es
   type="email", que lo rechazaría: un correo con dominio, o S/N. */
$CORREO = 'pattern="[^@\s]+@[^@\s]+\.[^@\s]+|[Ss]/?[Nn]" title="un correo como nombre@dominio.com, o S/N si no tiene"';
?>
				<div class="row g-3">

					<!-- Quién es -->
					<div class="col-12 col-xl-7">
						<div class="card h-100">
							<div class="card-header"><h3 class="card-title"><i class="fas fa-user me-2" aria-hidden="true"></i>Datos del representante</h3></div>
							<div class="card-body">
								<div class="row g-3">
									<div class="col-12 col-sm-5">
										<label class="form-label" for="repre_tipoidentificacion">Tipo de identificación</label>
										<select id="repre_tipoidentificacion" class="form-select" name="repre_tipoidentificacion">
											<?php echo $insRepre->listarOptionTipoIdentificacion($valores['repre_tipoidentificacion'] ?? ''); ?>
										</select>
									</div>
									<div class="col-12 col-sm-7">
										<label class="form-label ds-req" for="repre_identificacion">Identificación</label>
										<input type="text" class="form-control" id="repre_identificacion" name="repre_identificacion" value="<?php echo $v('repre_identificacion'); ?>" inputmode="numeric" placeholder="Número de cédula o pasaporte" required>
										<div id="repre_ident_aviso" class="ds-repre__aviso" aria-live="polite"></div>
									</div>
									<div class="col-12 col-sm-6">
										<label class="form-label ds-req" for="repre_primernombre">Primer nombre</label>
										<input type="text" class="form-control" id="repre_primernombre" name="repre_primernombre" value="<?php echo $v('repre_primernombre'); ?>" autocapitalize="words" autocomplete="given-name" required>
									</div>
									<div class="col-12 col-sm-6">
										<label class="form-label" for="repre_segundonombre">Segundo nombre</label>
										<input type="text" class="form-control" id="repre_segundonombre" name="repre_segundonombre" value="<?php echo $v('repre_segundonombre'); ?>" autocapitalize="words">
									</div>
									<div class="col-12 col-sm-6">
										<label class="form-label ds-req" for="repre_apellidopaterno">Apellido paterno</label>
										<input type="text" class="form-control" id="repre_apellidopaterno" name="repre_apellidopaterno" value="<?php echo $v('repre_apellidopaterno'); ?>" autocapitalize="words" autocomplete="family-name" required>
									</div>
									<div class="col-12 col-sm-6">
										<label class="form-label" for="repre_apellidomaterno">Apellido materno</label>
										<input type="text" class="form-control" id="repre_apellidomaterno" name="repre_apellidomaterno" value="<?php echo $v('repre_apellidomaterno'); ?>" autocapitalize="words">
									</div>
									<div class="col-12 col-sm-6">
										<label class="form-label" for="repre_parentesco">Parentesco con el alumno</label>
										<select class="form-select" id="repre_parentesco" name="repre_parentesco">
											<?php echo $insRepre->listarCatalogoParentesco($valores['repre_parentesco'] ?? ''); ?>
										</select>
									</div>
									<div class="col-12 col-sm-6">
										<span class="form-label d-block ds-req" id="rotulo_repre_sexo">Sexo</span>
										<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_repre_sexo">
											<label class="ds-opcion"><input type="radio" id="repre_sexoM" name="repre_sexo" value="M" <?php echo $marca('repre_sexo', 'M'); ?> required> Masculino</label>
											<label class="ds-opcion"><input type="radio" id="repre_sexoF" name="repre_sexo" value="F" <?php echo $marca('repre_sexo', 'F'); ?>> Femenino</label>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Cómo localizarlo -->
					<div class="col-12 col-xl-5">
						<div class="card h-100">
							<div class="card-header"><h3 class="card-title"><i class="fas fa-address-book me-2" aria-hidden="true"></i>Contacto y facturación</h3></div>
							<div class="card-body">
								<div class="row g-3">
									<div class="col-12 col-sm-6 col-xl-12">
										<label class="form-label ds-req" for="repre_celular">Celular</label>
										<input type="tel" class="form-control" id="repre_celular" name="repre_celular" value="<?php echo $v('repre_celular'); ?>" inputmode="numeric" autocomplete="tel-national" <?php echo $CELULAR; ?> required>
									</div>
									<div class="col-12 col-sm-6 col-xl-12">
										<label class="form-label ds-req" for="repre_correo">Correo</label>
										<input type="text" class="form-control" id="repre_correo" name="repre_correo" value="<?php echo $v('repre_correo'); ?>" inputmode="email" autocomplete="email" autocapitalize="off" spellcheck="false"
										       <?php echo $CORREO; ?> placeholder="nombre@correo.com" required>
										<p class="form-text mb-0">Si no tiene correo, escriba S/N.</p>
									</div>
									<div class="col-12">
										<label class="form-label ds-req" for="repre_direccion">Dirección</label>
										<input type="text" class="form-control" id="repre_direccion" name="repre_direccion" value="<?php echo $v('repre_direccion'); ?>" autocomplete="street-address" placeholder="Barrio, calle principal, número, calle secundaria" required>
									</div>
									<div class="col-12">
										<span class="form-label d-block ds-req" id="rotulo_repre_factura">¿Requiere factura?</span>
										<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_repre_factura">
											<label class="ds-opcion"><input type="radio" id="repre_facturaS" name="repre_factura" value="S" <?php echo $marca('repre_factura', 'S'); ?> required> Sí</label>
											<label class="ds-opcion"><input type="radio" id="repre_facturaN" name="repre_factura" value="N" <?php echo $marca('repre_factura', 'N'); ?>> No</label>
										</div>
										<p class="form-text mb-0">Con «Sí», los pagos se facturan a su nombre y a este correo.</p>
									</div>
								</div>
							</div>
						</div>
					</div>

					<!-- Cónyuge: opcional; plegado salvo que ya tenga datos -->
					<div class="col-12">
						<div class="card ds-repre__conyuge">
							<div class="card-header">
								<button type="button" class="ds-repre__plegable<?php echo $hayConyuge ? '' : ' collapsed'; ?>" data-bs-toggle="collapse" data-bs-target="#secConyuge" aria-expanded="<?php echo $hayConyuge ? 'true' : 'false'; ?>" aria-controls="secConyuge">
									<span><i class="fas fa-user-plus me-2" aria-hidden="true"></i>Cónyuge <span class="text-body-secondary fw-normal">(opcional)</span></span>
									<i class="fas fa-chevron-down ds-repre__flecha" aria-hidden="true"></i>
								</button>
							</div>
							<div class="collapse<?php echo $hayConyuge ? ' show' : ''; ?>" id="secConyuge">
								<div class="card-body">
									<div class="row g-3">
										<div class="col-12 col-sm-6 col-xl-3">
											<label class="form-label" for="conyuge_tipoidentificacion">Tipo de identificación</label>
											<select id="conyuge_tipoidentificacion" class="form-select" name="conyuge_tipoidentificacion">
												<?php echo $insRepre->listarOptionTipoIdentificacion($valores['conyuge_tipoidentificacion'] ?? ''); ?>
											</select>
										</div>
										<div class="col-12 col-sm-6 col-xl-3">
											<label class="form-label" for="conyuge_identificacion">Identificación</label>
											<input type="text" class="form-control" id="conyuge_identificacion" name="conyuge_identificacion" value="<?php echo $v('conyuge_identificacion'); ?>" inputmode="numeric">
										</div>
										<div class="col-12 col-sm-6 col-xl-3">
											<label class="form-label" for="conyuge_primernombre">Primer nombre</label>
											<input type="text" class="form-control" id="conyuge_primernombre" name="conyuge_primernombre" value="<?php echo $v('conyuge_primernombre'); ?>" autocapitalize="words">
										</div>
										<div class="col-12 col-sm-6 col-xl-3">
											<label class="form-label" for="conyuge_segundonombre">Segundo nombre</label>
											<input type="text" class="form-control" id="conyuge_segundonombre" name="conyuge_segundonombre" value="<?php echo $v('conyuge_segundonombre'); ?>" autocapitalize="words">
										</div>
										<div class="col-12 col-sm-6 col-xl-3">
											<label class="form-label" for="conyuge_apellidopaterno">Apellido paterno</label>
											<input type="text" class="form-control" id="conyuge_apellidopaterno" name="conyuge_apellidopaterno" value="<?php echo $v('conyuge_apellidopaterno'); ?>" autocapitalize="words">
										</div>
										<div class="col-12 col-sm-6 col-xl-3">
											<label class="form-label" for="conyuge_apellidomaterno">Apellido materno</label>
											<input type="text" class="form-control" id="conyuge_apellidomaterno" name="conyuge_apellidomaterno" value="<?php echo $v('conyuge_apellidomaterno'); ?>" autocapitalize="words">
										</div>
										<div class="col-12 col-sm-6 col-xl-3">
											<label class="form-label" for="conyuge_celular">Celular</label>
											<input type="tel" class="form-control" id="conyuge_celular" name="conyuge_celular" value="<?php echo $v('conyuge_celular'); ?>" inputmode="numeric" <?php echo $CELULAR; ?>>
										</div>
										<div class="col-12 col-sm-6 col-xl-3">
											<label class="form-label" for="conyuge_correo">Correo</label>
											<input type="text" class="form-control" id="conyuge_correo" name="conyuge_correo" value="<?php echo $v('conyuge_correo'); ?>" inputmode="email" autocapitalize="off" spellcheck="false" <?php echo $CORREO; ?>>
										</div>
										<div class="col-12 col-xl-6">
											<label class="form-label" for="conyuge_direccion">Dirección</label>
											<input type="text" class="form-control" id="conyuge_direccion" name="conyuge_direccion" value="<?php echo $v('conyuge_direccion'); ?>" placeholder="Barrio, calle principal, número, calle secundaria">
											<button type="button" class="btn btn-link btn-sm px-0 ds-alta__copiar" id="copiarDireccion" data-ds-copiar="conyuge_direccion" data-valor="">
												<i class="fas fa-copy me-1" aria-hidden="true"></i>La misma del representante
											</button>
										</div>
										<div class="col-12 col-sm-6 col-xl-3">
											<span class="form-label d-block" id="rotulo_conyuge_sexo">Sexo</span>
											<div class="ds-opciones" role="radiogroup" aria-labelledby="rotulo_conyuge_sexo">
												<label class="ds-opcion"><input type="radio" id="conyuge_sexoM" name="conyuge_sexo" value="M" <?php echo $marca('conyuge_sexo', 'M'); ?>> Masculino</label>
												<label class="ds-opcion"><input type="radio" id="conyuge_sexoF" name="conyuge_sexo" value="F" <?php echo $marca('conyuge_sexo', 'F'); ?>> Femenino</label>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
