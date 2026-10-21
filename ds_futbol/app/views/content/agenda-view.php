<?php
	date_default_timezone_set("America/Guayaquil");

	use app\controllers\agendaController;
	$insAgenda = new agendaController();
	
?>
<!DOCTYPE html>
<html lang="es">
  <head>
    <meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo APP_NAME; ?>| Eventos</title>
	<?php require DS_HUB_PATH . "ds_core/inc/csrf-cabecera.php"; ?>
	<!-- Google Font: Source Sans Pro -->
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/fuentes.css'); ?>">
	<!-- Font Awesome -->
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/fontawesome6/css/all.min.css">
	  <!-- fullCalendar -->
	  <link rel="stylesheet" href="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/main.css">
	<!-- Theme style -->
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/overlayscrollbars/css/overlayscrollbars.min.css">
	<link rel="stylesheet" href="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/css/adminlte.min.css">
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_core/assets/css/core.css'); ?>">
	<link rel="stylesheet" href="<?php echo ds_recurso('ds_futbol/app/views/dist/css/futbol.css'); ?>">
	<link rel="stylesheet" href="<?php echo APP_URL; ?>app/views/dist/css/sweetalert2.min.css">
    
	<?php require DS_HUB_PATH . "ds_core/inc/tema-init.php"; ?>
  </head>
  <body class="layout-fixed sidebar-expand-lg bg-body-tertiary">
    <div class="app-wrapper ds-core">
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
						<h1 class="m-0">Registro de eventos</h1>
					</div><!-- /.col -->
					<div class="col-sm-6">
						<ol class="breadcrumb float-sm-end">
							<li class="breadcrumb-item"><a href="#">Nuevo</a></li>
							<li class="breadcrumb-item active">Dashboard v1</li>
						</ol>
					</div><!-- /.col -->
				</div><!-- /.row -->
			</div><!-- /.container-fluid -->
		</div>
		<!-- /.content-header -->

		<!-- Section listado de alumnos -->
		<section class="app-content">						
			<!-- card-body -->                
			<div class="card-body">
				<div class="row g-0"><?php /* g-0: sin el, los margenes negativos de .row sobresalian 4px del viewport en telefono. La columna unica no necesita canal. */ ?>
					<div class="col-md-12">
						<div class="card card-primary card-outline">							
							<div class="card-body p-0">
								<!-- THE CALENDAR -->
								<div id="calendar"></div>
							</div>
							<!-- /.card-body -->
						</div>
					<!-- /.card -->
					</div>
					<!-- /.col -->
				</div>	
			</div>
		</section>

		<div class="modal fade" id="modal-agenda">
			<form class="FormularioAjax" action="<?php echo APP_URL; ?>app/ajax/agendaAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data">		
				<div class="modal-dialog">
					<div class="modal-content">
						<div class="modal-header">
							<h4 class="modal-title" id="modal-title">Registrar Evento</h4> <!-- Cambiará dinámicamente -->
							<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
						</div>
						<div class="modal-body">
							<div class="mb-3">
								<input type="hidden" name="modulo_agenda" id="modulo_agenda" value="registrar"> <!-- Cambiado a dinámico -->
								<input type="hidden" name="agenda_id" id="agenda_id" value=""> <!-- Campo oculto para el ID -->
							</div>
								<div class="mb-3">
								<label for="agenda_title">Título del Evento:</label>
								<input type="text" id="agenda_title" name="agenda_title" class="form-control" required>
							</div>								
							<div class="mb-3">
								<label for="agenda_detail">Detalle del Evento:</label>
								<textarea id="agenda_detail" name="agenda_detail" class="form-control"></textarea>
							</div>
							<div class="mb-3">
								<label for="agenda_start">Fecha y Hora de Inicio:</label>
								<input type="datetime-local" id="agenda_start" name="agenda_start" class="form-control" required>
							</div>
							<div class="mb-3">
								<label for="agenda_end">Fecha y Hora de Fin:</label>
								<input type="datetime-local" id="agenda_end" name="agenda_end" class="form-control" required>
							</div>
							<div class="mb-3">
								<label for="agenda_color">Color del Evento:</label>
								<input type="color" id="agenda_color" name="agenda_color" class="form-control">
							</div>
						</div>
						<div class="modal-footer justify-content-between">
							<button type="button" class="btn btn-default" data-bs-dismiss="modal">Cerrar</button>
							<button type="submit" class="btn btn-primary" id="btn-submit">Guardar</button>
						</div>
					</div>
				</div>
			</form>
		</div>
      <!-- /.modal -->
		<!-- /.section -->      
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
	<!-- jQuery UI -->
	<!-- AdminLTE App -->
	<script src="<?php echo APP_URL; ?>app/views/dist/js/sweetalert2.all.min.js" ></script>
	<script src="<?php echo DS_HUB_URL; ?>ds_core/assets/vendor/adminlte4/js/adminlte.min.js"></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/ajax.js'); ?>" ></script>
	<script src="<?php echo ds_recurso('ds_futbol/app/views/dist/js/main.js'); ?>" ></script>	
	<!-- fullCalendar 2.2.5 -->
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/main.js"></script>
	<script src="<?php echo APP_URL; ?>app/views/dist/plugins/fullcalendar/locales/es.js"></script>
	
     <!-- Page specific script -->
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var calendarEl = document.getElementById('calendar');
			var calendar = new FullCalendar.Calendar(calendarEl, {
				initialView: 'dayGridMonth',
				locale: 'es',
				aspectRatio: 2.14,
				events: <?php echo $insAgenda->obtenerEventos(); ?>,
				eventTimeFormat: { // Configura el formato de la hora
					hour: '2-digit',
					minute: '2-digit',
					hour12: false // Usa formato 24 horas
				},
				dateClick: function (info) {
					// Obtener la fecha seleccionada en el calendario (en UTC)
					const selectedDate = new Date(info.dateStr);

					// Añadir 1 día si el calendario presenta un día menos
					selectedDate.setDate(selectedDate.getDate() + 1);

					// Establecer la hora local del sistema
					const currentTime = new Date();

					// Establecer las horas y minutos de la fecha seleccionada con la hora local del sistema
					selectedDate.setHours(currentTime.getHours());
					selectedDate.setMinutes(currentTime.getMinutes());
					selectedDate.setSeconds(0); // Opcional: reseteamos los segundos

					// Convertir a la fecha local en el formato 'datetime-local' (YYYY-MM-DDTHH:MM)
					function formatDateToLocalInput(date) {
						const year = date.getFullYear();
						const month = String(date.getMonth() + 1).padStart(2, '0'); // Mes comienza desde 0
						const day = String(date.getDate()).padStart(2, '0');
						const hours = String(date.getHours()).padStart(2, '0');
						const minutes = String(date.getMinutes()).padStart(2, '0');
						return `${year}-${month}-${day}T${hours}:${minutes}`;
					}

					// Asignar la fecha y hora local al campo de inicio
					document.getElementById('agenda_start').value = formatDateToLocalInput(selectedDate);

					// Configurar la fecha de finalización como 1 hora después de la fecha de inicio
					const endDate = new Date(selectedDate.getTime() + 60 * 60 * 1000); // +1 hora
					document.getElementById('agenda_end').value = formatDateToLocalInput(endDate);

					// Limpiar los campos del formulario
					document.getElementById('agenda_id').value = "";
					document.getElementById('agenda_title').value = "";
					document.getElementById('agenda_detail').value = "";

					// Mostrar el modal
					$('#modal-agenda').modal('show');
				},

				eventClick: function (info) {
					Swal.fire({
						title: '¿Qué acción desea realizar?',
						showDenyButton: true,
						showCancelButton: true,
						confirmButtonText: 'Editar',
						denyButtonText: 'Eliminar',
						cancelButtonText: 'Cancelar'
					}).then((result) => {
						if (result.isConfirmed) {
							// Llenar el formulario del modal con los datos del evento seleccionado
							document.getElementById('agenda_id').value = info.event.id;
							document.getElementById('agenda_title').value = info.event.title;
							document.getElementById('agenda_detail').value = info.event.extendedProps.detail;
							
							// Ajustar el formato de las fechas para datetime-local (YYYY-MM-DDTHH:mm)
							 // Convertir fechas de UTC a la zona horaria local
							const start = new Date(info.event.start);
							const end = new Date(info.event.end);

							// Función para formatear una fecha en el formato requerido por datetime-local
							function formatDateToLocalInput(date) {
								const year = date.getFullYear();
								const month = String(date.getMonth() + 1).padStart(2, '0'); // Mes comienza desde 0
								const day = String(date.getDate()).padStart(2, '0');
								const hours = String(date.getHours()).padStart(2, '0');
								const minutes = String(date.getMinutes()).padStart(2, '0');
								return `${year}-${month}-${day}T${hours}:${minutes}`;
							}

							// Asignar las fechas ajustadas al modal
							document.getElementById('modulo_agenda').value = "editar";
							document.getElementById('modal-title').textContent = "Editar Evento";
							document.getElementById('agenda_start').value = formatDateToLocalInput(start);
							document.getElementById('agenda_end').value = formatDateToLocalInput(end);
							document.getElementById('agenda_color').value = info.event.backgroundColor;
							$('#modal-agenda').modal('show');
						} else if (result.isDenied) {
							// Eliminar evento
							eliminarEvento(info.event.id);
						}
					});
				}
			});
			calendar.render();
		});

		function eliminarEvento(agenda_id) {
			Swal.fire({
				title: '¿Está seguro?',
				text: "¡No podrá revertir esta acción!",
				icon: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#3085d6',
				cancelButtonColor: '#d33',
				confirmButtonText: 'Sí, eliminarlo'
			}).then((result) => {
				if (result.isConfirmed) {
					$.ajax({
						url: "<?php echo APP_URL; ?>app/ajax/agendaAjax.php",
						type: "POST",
						data: {
							modulo_agenda: "eliminar",
							agenda_id: agenda_id
						},
						success: function (response) {
							let res = JSON.parse(response);
							Swal.fire(
								res.titulo,
								res.texto,
								res.icono
							).then(() => {
								if (res.tipo === "recargar") {
									location.reload();
								}
							});
						}
					});
				}
			});
		}
	</script>	
  </body>
</html>








