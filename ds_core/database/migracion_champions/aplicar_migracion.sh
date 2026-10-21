#!/bin/bash
# =====================================================================
# Convierte una copia de la base de SoccerEasy en una base DigiSports.
#
#   ./aplicar_migracion.sh BASE
#
# BASE es una copia restaurada de SoccerEasy (ver LEEME.md, pasos 1 y 2).
# Aplica, en orden y deteniéndose en el primer error, todo lo que hay que
# aplicarle. No toca ninguna otra base.
#
# Cliente y credenciales por variables de entorno:
#   MYSQL       ruta al cliente         (por omisión: mysql)
#   MYSQL_OPTS  opciones de conexión    (por omisión: -u root)
# =====================================================================
set -u
BASE="${1:?Uso: $0 BASE}"
MYSQL="${MYSQL:-mysql}"
MYSQL_OPTS="${MYSQL_OPTS:--u root}"
AQUI="$(cd "$(dirname "$0")" && pwd)"
RAIZ="$(cd "$AQUI/.." && pwd)"     # ds_core/database

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
