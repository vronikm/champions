<!-- Navbar -->
<nav class="app-header navbar navbar-expand bg-body ds-core__navbar" data-bs-theme="dark">
    <!-- Left navbar links -->
    <ul class="navbar-nav">
        <li class="nav-item">
        <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>
        <?php /* Accesos rápidos: sólo se ofrecen si el rol puede usarlos. */ ?>
        <?php if(puede_crear('alumnoList')): ?>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo APP_URL."alumnoNew/";?>" class="nav-link">Nuevo alumno</a>
            </li>
        <?php endif; ?>

        <?php if(usuario_tiene_permiso('alumnoList')): ?>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo APP_URL."alumnoList/";?>" class="nav-link">Buscar alumno</a>
            </li>
        <?php endif; ?>

        <?php if(puede_crear('pagosList')): ?>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="<?php echo APP_URL."pagosList/";?>" class="nav-link">Registrar pago</a>
            </li>
        <?php endif; ?>

    </ul>

    <!-- Right navbar links -->
    <ul class="navbar-nav ms-auto">

        <!-- Buscador de alumnos: se apoya en la búsqueda de alumnoList -->

        <!-- Selector de aplicaciones del ecosistema DigiSports -->
        <?php require_once __DIR__ . "/../../../../ds_core/inc/app-launcher.php"; ?>

        <!-- Tema: claro, oscuro o el del sistema -->
        <?php require_once __DIR__ . "/../../../../ds_core/inc/tema-control.php"; ?>

        <!-- Notifications Dropdown Menu -->
        <li class="nav-item dropdown">
            <a class="nav-link" data-bs-toggle="dropdown" href="#">
                 <!--i class="nav-icon far fa-futbol text-info"></i-->
                <div class="d-flex align-items-center pb-2" style="gap:.5rem;">                   
                    <?php
                        /* Un usuario sin ficha de empleado no tiene foto: la
                           clave puede no existir en la sesion. */
                        $fotoUsuario = (string)($_SESSION['foto'] ?? '');

                        /* EL TAMANO VA AQUI, EXPLICITO.
                           En AdminLTE 3 lo daba .user-panel .img-circle, y esa
                           regla no existe en la 4. Al quitarla, la imagen se
                           dibujo a su tamano natural —512x512— y tapo la
                           pagina entera. Una clase de tema no es sitio para
                           que viva algo de lo que depende el diseno. */
                        $avatar = 'class="rounded-circle shadow-sm" '
                                . 'style="width:32px;height:32px;object-fit:cover;" '
                                . 'alt="Foto del usuario"';

                        $src = ($fotoUsuario !== ''
                                && is_file("app/views/imagenes/fotos/empleado/" . $fotoUsuario))
                             ? media_url('empleado', $fotoUsuario)
                             : ds_recurso('ds_futbol/app/views/dist/img/default.png');

                        echo '<img ' . $avatar . ' src="' . $src . '">';
                    ?>
                    <span ><?php echo htmlspecialchars(ds_nombre_usuario(), ENT_QUOTES, 'UTF-8');?></span>
                </div>
            </a>
            <div class="dropdown-menu dropdown-menu-xs dropdown-menu-end">              
                <!--a href="#" class="dropdown-item">
                    <i class="fas fa-envelope me-2"></i> 4 new messages                    
                </a>
                <div class="dropdown-divider"></div-->
                <a href="#" class="dropdown-item" data-bs-target="#modal-default" data-bs-toggle="modal">
                    <i class="fas fa-key me-2"></i> Cambiar contraseña                   
                </a>
                <div class="dropdown-divider"></div>
                <a href=<?php echo APP_URL."logOut/";?> class="dropdown-item js-salir">
                    <i class="fas fa-times me-2"></i> Salir                  
                </a>                
            </div>
        </li>     
    </ul>   

</nav>

<div class="modal fade" id="modal-default">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form class="FormularioAjax" id="formCambiarClave" action="<?php echo APP_URL; ?>app/ajax/usuarioAjax.php" method="POST" autocomplete="off" enctype="multipart/form-data" >
                <input type="hidden" name="modulo_usuario" value="CAMBIAR_CLAVE">
                <input type="hidden" name="usuario" value="<?php echo $_SESSION['usuario']; ?>">
                <input type="hidden" name="usuario_id" value="<?php echo $_SESSION['usuarioid']; ?>">              
                <div class="modal-header">
                    <h6 class="modal-title">Cambiar contraseña</h6>
                    <?php /* btn-close ya dibuja el aspa con una imagen de
                             fondo: dejar el &times; dentro pintaba dos. */ ?>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="usuario_clave">Contraseña Actual</label>
                        <input type="password" class="form-control" id="usuario_clave" name="usuario_clave" required utocomplete="off">	
                    </div>
                    <div class="mb-3">
                        <label for="usuario_clave_nueva">Nueva Contraseña</label>
                        <input type="password" class="form-control" id="usuario_clave_nueva" name="usuario_clave_nueva"
                               minlength="<?php echo clave_longitud_minima(); ?>"
                               maxlength="<?php echo clave_longitud_maxima(); ?>"
                               required autocomplete="new-password">
                        <small class="text-muted"><?php echo clave_regla_texto(); ?></small>
                    </div>
                    <div class="mb-3">
                        <label for="usuario_clave_confirmar">Confirmar Nueva Contraseña</label>
                        <input type="password" class="form-control" id="usuario_clave_confirmar" name="usuario_clave_confirmar"
                               minlength="<?php echo clave_longitud_minima(); ?>"
                               maxlength="<?php echo clave_longitud_maxima(); ?>"
                               required autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>                 
                    <button type="submit" class="btn btn-success btn-sm">Guardar</button>
                </div>
            </form>
        </div>
        <!-- /.modal-content -->
    </div>
<!-- /.modal-dialog -->
</div>

<script>
    document.getElementById('usuario_clave_confirmar').addEventListener('input', function() {
        let claveNueva = document.getElementById('usuario_clave_nueva').value.trim();
        let claveConfirmar = this.value.trim();

        if (claveNueva !== claveConfirmar) {
            this.setCustomValidity("Las contraseñas no coinciden");
        } else {
            this.setCustomValidity("");
        }
    });
</script>
<!-- /.navbar -->