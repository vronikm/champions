-- =====================================================================
-- 057 · La tarjeta de cumpleanos entra en el menu de Core
-- =====================================================================
-- La pantalla ya existe y esta registrada en la lista blanca del enrutador,
-- pero sin entrada de menu no la encuentra nadie, y sin permiso no entra
-- nadie: Core corre con el mismo modelo de tres niveles que el resto.
--
-- Se coloca junto a «Carnets», que es la otra pantalla que configura como se
-- imprime algo. Quien busca una, busca la otra.
--
-- El permiso NO se concede aqui a ningun rol. Se hace en Permisos, con la
-- pantalla, que es donde la escuela decide quien administra que. Sembrar un
-- permiso desde una migracion es abrir un acceso que nadie pidio.
-- =====================================================================

SET NAMES utf8mb4;

INSERT INTO seguridad_menu
    (menu_modulo, menu_nombre, menu_orden, menu_padreid, menu_hijo,
     menu_vista, menu_icono, menu_estado)
SELECT 'core', 'Tarjeta de cumpleaños', 12, 0, 'N',
       'tarjetaCumple', 'nav-icon fas fa-cake-candles', 'A'
 WHERE NOT EXISTS (
       SELECT 1 FROM (SELECT * FROM seguridad_menu) m
        WHERE m.menu_modulo = 'core' AND m.menu_vista = 'tarjetaCumple');
