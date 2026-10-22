#!/bin/bash
# =====================================================================
# Convierte una copia de la base de SoccerEasy en una base DigiSports.
#
#   ./aplicar_migracion.sh BASE              aplica la cadena sobre BASE
#   ./aplicar_migracion.sh --generar ARCHIVO escribe la cadena en UN .sql
#
# BASE es una copia restaurada de SoccerEasy (ver LEEME.md, pasos 1 y 2).
# Aplica, en orden y deteniéndose en el primer error, todo lo que hay que
# aplicarle. No toca ninguna otra base.
#
# --generar es para el hosting, donde no hay consola: el archivo se importa
# entero desde phpMyAdmin sobre la base con la copia de SoccerEasy. Lleva
# exactamente los mismos archivos y en el mismo orden.
#
# Cliente y credenciales por variables de entorno:
#   MYSQL       ruta al cliente         (por omisión: mysql)
#   MYSQL_OPTS  opciones de conexión    (por omisión: -u root)
# =====================================================================
set -u
AQUI="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(cd "$AQUI/.." && pwd)"     # ds_core/database

# --- El orden ---------------------------------------------------------
# 014, 015 y 041 se sustituyen por su versión de esta carpeta (LEEME.md).
ARCHIVOS=("$AQUI/pre_retirar_residuos_qa.sql" "$AQUI/000_previo_champions.sql")
for f in "$RAIZ"/0[0-6][0-9]_*.sql; do
    n=$(basename "$f" | cut -c1-3)
    case "$n" in
        014) ARCHIVOS+=("$AQUI/014_permisos_opciones_nuevas.champions.sql") ;;
        015) ARCHIVOS+=("$AQUI/015_dashboard_operativo.champions.sql") ;;
        041) ARCHIVOS+=("$AQUI/041_unificar_cotejamiento.champions.sql") ;;
        *)   ARCHIVOS+=("$f") ;;
    esac
done
for f in "$AQUI"/06[5-9]_*.sql "$AQUI"/0[7-9][0-9]_*.sql; do
    [ -f "$f" ] && ARCHIVOS+=("$f")
done

# --- Un solo archivo para phpMyAdmin ----------------------------------
# Sin BOM ni saltos de línea de Windows: cada archivo se limpia al unirlo.
if [ "${1:-}" = "--generar" ]; then
    SALIDA="${2:?Uso: $0 --generar ARCHIVO.sql}"
    {
        printf -- "-- =====================================================================\n"
        printf -- "-- Champions CF: migración SoccerEasy -> DigiSports, en un solo archivo\n"
        printf -- "-- Generado %s con aplicar_migracion.sh --generar (%d archivos).\n" "$(date '+%Y-%m-%d %H:%M')" "${#ARCHIVOS[@]}"
        printf -- "--\n-- Importar con phpMyAdmin SOBRE LA BASE QUE CONTIENE LA COPIA DE\n"
        printf -- "-- SOCCEREASY, después de respaldarla. No repetir sobre una base ya migrada.\n"
        printf -- "-- =====================================================================\nSET NAMES utf8mb4;\n"
        for f in "${ARCHIVOS[@]}"; do
            printf "\n-- ---------------------------------------------------------------------\n-- >>> %s\n" "$(basename "$f")"
            printf -- "-- ---------------------------------------------------------------------\n"
            tr -d '\r' < "$f" | sed '1s/^\xEF\xBB\xBF//'
            printf "\n"
        done
    } > "$SALIDA"
    echo "Escrito $SALIDA con ${#ARCHIVOS[@]} archivos."
    exit 0
fi

BASE="${1:?Uso: $0 BASE   |   $0 --generar ARCHIVO.sql}"
MYSQL="${MYSQL:-mysql}"
MYSQL_OPTS="${MYSQL_OPTS:--u root}"
sql() { $MYSQL $MYSQL_OPTS --default-character-set=utf8mb4 -N -B "$BASE" -e "$1"; }

# --- La base tiene que ser SoccerEasy SIN migrar ----------------------
# Varias migraciones no se pueden repetir (ver DESPLIEGUE / estado_migraciones):
# aplicarlas sobre una base ya convertida duplicaría menús o abortaría a medias.
if [ "$(sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sujeto_alumno'")" != "1" ]; then
    echo "DETENIDO: $BASE no parece una copia de SoccerEasy (no tiene sujeto_alumno)."; exit 1
fi
if [ "$(sql "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'facturas_electronicas'")" != "0" ]; then
    echo "DETENIDO: $BASE ya está migrada (tiene facturas_electronicas). No se aplica nada."; exit 1
fi

# --- Aplicar ----------------------------------------------------------
echo "Base: $BASE   ·   ${#ARCHIVOS[@]} archivos"
i=0
for f in "${ARCHIVOS[@]}"; do
    i=$((i + 1))
    printf "  %2d/%d  %-48s " "$i" "${#ARCHIVOS[@]}" "$(basename "$f")"
    if salida=$($MYSQL $MYSQL_OPTS --default-character-set=utf8mb4 "$BASE" < "$f" 2>&1); then
        echo "ok"
    else
        echo "ERROR"; echo "$salida" | sed 's/^/        /'
        echo "DETENIDO en $(basename "$f"). Lo aplicado antes queda aplicado: restaure la copia y corrija antes de repetir."
        exit 1
    fi
done
echo "Terminado. Compruebe con:  php ds_core/database/estado_migraciones.php"
