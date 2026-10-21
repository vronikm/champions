<?php
/*
|--------------------------------------------------------------------------
| Cierre del armazón común de los módulos · AdminLTE 4
|--------------------------------------------------------------------------
| Pareja de layout-modulo.php.
|
| $moduloPie: texto del pie. Si no se pasa, sólo va la marca.
*/
$moduloPie = $moduloPie ?? '';
?>
            </div>
        </div>
    </main>

    <footer class="app-footer">
        <div class="float-end d-none d-sm-inline"><b><?php echo DS_HUB_NAME; ?></b></div>
        <?php echo $moduloPie; ?>
    </footer>

</div><!-- /.app-wrapper -->

<?php
/*
| ORDEN DE CARGA, QUE AQUI IMPORTA
|
|   1. OverlayScrollbars   AdminLTE lo usa para la barra del menu lateral.
|                          Si falta, el menu no desplaza en pantallas bajas
|                          y no avisa de nada.
|   2. Bootstrap 5         El paquete «bundle» ya trae Popper dentro, asi
|                          que no hace falta cargarlo aparte.
|   3. AdminLTE 4          Se apoya en los dos anteriores.
|
| AQUI NO SE CARGA jQUERY, Y ESO ES DELIBERADO
|
| Durante la migracion a AdminLTE 4 se dejo puesto «por si acaso», con la
| explicacion de que lo necesitaban ajax.js y core.js. Al ir a retirarlo se
| midio, y la explicacion era falsa: core.js no tiene ni una llamada a
| jQuery, y las cinco de ajax.js estaban DENTRO DE UN COMENTARIO —restos de
| la demo de AdminLTE, con su «Lorem ipsum» y todo—. Ademas ajax.js no se
| carga desde aqui.
|
| Se reviso una por una: las 41 vistas de Arena, League y Core que pasan
| por este pie no hacen ni una llamada. Se descargaban 87 KB en cada una
| para no usarlos.
|
| Si alguna vista llegara a necesitar un plugin de los que dependen de
| jQuery, que lo cargue ella, delante del plugin. Volver a ponerlo aqui
| significa devolverselo a las cuarenta y una.
*/
?>
<script src="<?php echo DS_OVERLAYSCROLL_URL; ?>js/overlayscrollbars.browser.es6.min.js"></script>
<script src="<?php echo DS_BOOTSTRAP5_URL; ?>js/bootstrap.bundle.min.js"></script>
<script src="<?php echo DS_ADMINLTE4_URL; ?>js/adminlte.min.js"></script>

<script src="<?php echo DS_VENDOR_URL; ?>js/sweetalert2.all.min.js"></script>
<script src="<?php echo ds_recurso('ds_core/admin/assets/core.js'); ?>"></script>
</body>
</html>
