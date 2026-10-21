-- =====================================================================
-- 050 · Reportes favoritos de cada usuario
-- =====================================================================
-- QUE RESUELVE
--
-- El catalogo de reportes crece; el §28 del encargo pide favoritos para que
-- cada quien llegue rapido a los suyos. Es preferencia personal, no
-- configuracion del sistema: se guarda por usuario.
--
--
-- ES LA PRIMERA TABLA QUE INSIGHTS ESCRIBE DE VERDAD
--
-- Hasta ahora solo escribia insights_cartera_snapshot, y desde la linea de
-- comandos. Esta se escribe desde la interfaz, con el usuario delante, asi
-- que atraviesa InsightsConexion: si el candado estuviera mal puesto,
-- marcar un favorito fallaria. Es una prueba viva de que el candado permite
-- lo que debe permitir, que es la mitad que suele romperse.
--
--
-- LA CLAVE ES EL PAR, NO UN AUTONUMERICO
--
-- Un usuario no puede tener dos veces el mismo favorito. Con clave primaria
-- compuesta, marcarlo dos veces es imposible por definicion en vez de
-- depender de que el codigo compruebe antes.
--
-- El identificador del reporte es una cadena corta —su clave en el catalogo
-- del controlador— y no una foranea: el catalogo vive en codigo porque cada
-- entrada apunta a una vista concreta, y una tabla de reportes que hubiera
-- que mantener en paralelo se desincronizaria.
-- =====================================================================

CREATE TABLE IF NOT EXISTS insights_favorito (
    favorito_usuarioid INT         NOT NULL,
    favorito_reporte   VARCHAR(40) NOT NULL
        COMMENT 'Clave del reporte en el catalogo de insightsController',
    favorito_fecha     DATETIME    NOT NULL,

    PRIMARY KEY (favorito_usuarioid, favorito_reporte),
    KEY ix_favorito_usuario (favorito_usuarioid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
