-- =====================================================================
-- 055 · Las cuatro tablas de Insights, al cotejamiento de la casa
-- =====================================================================
-- QUE PASO
--
-- Las cuatro tablas que Insights creo declaran un cotejamiento que NO es el
-- de la base:
--
--     insights_cartera_snapshot   utf8mb4_unicode_ci    (migracion 046)
--     insights_favorito           utf8mb4_unicode_ci    (migracion 050)
--     insights_auditoria          utf8mb4_unicode_ci    (migracion 051)
--     insights_umbral             utf8mb4_general_ci    (migracion 054)
--
-- La base entera es utf8mb4_0900_ai_ci.
--
--
-- POR QUE IMPORTA, SI TODO FUNCIONA
--
-- Porque funciona por casualidad. Ninguna consulta de hoy une esas columnas
-- de texto con las de otra tabla, y comparar contra un PARAMETRO no da
-- problema: el parametro se adapta.
--
-- En cuanto una consulta las una, MySQL responde:
--
--     1267 - Illegal mix of collations
--
-- Comprobado, no supuesto: unir insights_favorito.favorito_reporte con
-- seguridad_menu.menu_vista falla ahora mismo.
--
--
-- ES EXACTAMENTE EL PROBLEMA QUE LA 041 VINO A CERRAR
--
-- «041_unificar_cotejamiento» existe porque este mismo desajuste rompio
-- WHERE origen_modulo LIKE ? sobre v_comprobante_emitido, y costo encontrarlo:
-- el sintoma era una consulta que fallaba, no un esquema que se viera mal.
--
-- Se reintrodujo cuatro veces seguidas al crear Insights. La migracion
-- arregla la base; los cuatro archivos de origen se corrigieron tambien, para
-- que una instalacion nueva no vuelva a nacer torcida. Y el barrido lo vigila
-- ahora en qa_utf8mb4.php: cualquier tabla que se salga del cotejamiento de
-- la base sale en rojo.
--
--
-- CONVERT TO Y NO SOLO DEFAULT COLLATE
--
-- ALTER TABLE ... DEFAULT COLLATE cambia lo que heredaran las columnas
-- FUTURAS y deja las actuales como estan, que es justo la mitad que no sirve.
-- CONVERT TO CHARACTER SET convierte tambien las columnas existentes, que son
-- las que participan en las comparaciones.
--
-- Los datos no se tocan: los tres cotejamientos son utf8mb4, asi que los
-- bytes ya son los correctos. Lo que cambia es la regla de comparacion.
-- =====================================================================

SET NAMES utf8mb4;

ALTER TABLE insights_cartera_snapshot
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

ALTER TABLE insights_favorito
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

ALTER TABLE insights_auditoria
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;

ALTER TABLE insights_umbral
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
