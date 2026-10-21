<?php
/*
|--------------------------------------------------------------------------
| La conexión de Insights sólo lee
|--------------------------------------------------------------------------
| El encargo es explícito: Insights consulta, consolida y visualiza, pero no
| modifica datos de Basketball, Arena ni League. Con la conexión compartida
| —seguridad_conexion(), la que usan los demás módulos— eso sería sólo una
| intención: el usuario de MySQL tiene escritura sobre las 93 tablas y un
| UPDATE mal puesto funcionaría sin protestar.
|
| Esta clase convierte la intención en un mecanismo: rechaza cualquier
| sentencia que escriba, salvo sobre las tablas propias de Insights.
|
|
| DÓNDE ESTÁ EL LÍMITE DE ESTA PROTECCIÓN, Y HAY QUE DECIRLO
|
| Esto inspecciona el TEXTO de la sentencia. Es una barrera real —hace que
| un descuido falle en la primera prueba en vez de corromper datos— pero no
| es el motor quien la impone, así que no es inviolable.
|
| La garantía fuerte es un usuario de MySQL con SELECT y nada más sobre las
| tablas ajenas, y escritura sólo sobre insights_*. Es UNA sentencia GRANT y
| no cambia una línea de código: esta clase seguiría funcionando igual.
| Queda como recomendación para producción, no como algo que este archivo
| pueda dar por sí solo.
|
|
| POR QUÉ NO SE REUTILIZA seguridad_conexion()
|
| Devuelve un PDO ya construido y compartido por todo el ecosistema. Si
| Insights lo envolviera, la envoltura no alcanzaría al objeto que los demás
| módulos ya tienen en la mano. Se abre una conexión propia, con las mismas
| credenciales y la misma codificación, para que el candado esté en el
| objeto que Insights usa y en ninguno más.
*/

if (!class_exists('InsightsConexion')) {

    class InsightsSoloLectura extends RuntimeException {}

    final class InsightsConexion extends PDO
    {
        /** Prefijo de las tablas que Insights sí puede escribir. */
        private const PROPIAS = 'insights_';

        /**
         * Sentencias que sólo leen. Se compara el primer verbo.
         *
         * WITH entra aquí porque una CTE puede terminar en SELECT, pero se
         * comprueba aparte que no acabe en INSERT/UPDATE/DELETE, que MySQL
         * también admite tras un WITH.
         */
        private const LECTURA = ['select', 'show', 'explain', 'describe', 'desc', 'with'];

        /** Verbos que escriben, y por tanto exigen que la tabla sea propia. */
        private const ESCRITURA = ['insert', 'update', 'delete', 'replace', 'truncate',
                                   'create', 'alter', 'drop', 'rename', 'grant', 'revoke',
                                   'load', 'call', 'set'];

        public static function abrir(): self
        {
            $opciones = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            if (defined('DS_DB_INIT_COMANDO')) {
                $opciones[PDO::MYSQL_ATTR_INIT_COMMAND] = DS_DB_INIT_COMANDO;
            }

            /* utf8mb4 y no utf8: en MySQL «utf8» es utf8mb3, tres bytes, y
               pierde por el camino lo que necesite cuatro. Mismo criterio
               que seguridad_conexion(). */
            return new self(
                'mysql:host=' . DB_SERVER . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER, DB_PASS, $opciones
            );
        }

        /*==============  El candado  ==============*/

        /**
         * Deja pasar la sentencia o lanza. No devuelve nada: su valor está
         * en no volver cuando algo va mal.
         */
        private function vigilar(string $sql): void
        {
            /* Fuera comentarios y cadenas antes de mirar el verbo: un
               «-- update» o un literal 'DELETE' no son sentencias, y
               tomarlos por tales bloquearía consultas legítimas. */
            $limpia = preg_replace([
                '~/\*.*?\*/~s',        /* comentarios de bloque */
                '~--[^\r\n]*~',        /* comentarios de linea  */
                '~#[^\r\n]*~',
                "~'(?:[^'\\\\]|\\\\.)*'~",  /* literales entre comillas */
                '~"(?:[^"\\\\]|\\\\.)*"~',
            ], ' ', $sql) ?? $sql;

            $limpia = trim($limpia);
            if ($limpia === '') { return; }

            preg_match('~^\(*\s*([a-z]+)~i', $limpia, $m);
            $verbo = strtolower($m[1] ?? '');

            if (in_array($verbo, self::LECTURA, true)) {
                /* Un WITH puede acabar escribiendo. Se comprueba. */
                if ($verbo === 'with' && preg_match('~\b(insert|update|delete|replace)\b~i', $limpia)) {
                    throw new InsightsSoloLectura(
                        'Insights no escribe: la CTE termina en una sentencia de escritura.');
                }
                return;
            }

            if (!in_array($verbo, self::ESCRITURA, true)) {
                throw new InsightsSoloLectura(
                    "Insights no reconoce la sentencia «$verbo» y por prudencia la rechaza.");
            }

            /* Escribe. Sólo se admite si TODAS las tablas nombradas son
               propias de Insights. Basta que una no lo sea para negarla.

               Antes hay que quitar «ON DUPLICATE KEY UPDATE …»: ese UPDATE
               no introduce una tabla sino una lista de columnas, y sin
               quitarlo el candado tomaba «UPDATE snapshot_valor» por una
               tabla ajena y rechazaba la propia escritura de Insights. Se
               descubrió en el primer uso real. */
            $limpia = preg_replace('~\bon\s+duplicate\s+key\s+update\b.*$~is', ' ', $limpia) ?? $limpia;

            preg_match_all(
                '~\b(?:into|update|from|table|join)\s+`?([a-z_][a-z0-9_]*)`?~i',
                $limpia, $tablas);

            $ajenas = array_values(array_filter(
                array_unique(array_map('strtolower', $tablas[1] ?? [])),
                static fn(string $t): bool => !str_starts_with($t, self::PROPIAS)
            ));

            if ($ajenas !== [] || ($tablas[1] ?? []) === []) {
                throw new InsightsSoloLectura(
                    'Insights sólo escribe en sus propias tablas (' . self::PROPIAS . '*). '
                    . 'Sentencia rechazada'
                    . ($ajenas !== [] ? ' por: ' . implode(', ', $ajenas) : ' por no nombrar tabla')
                    . '.');
            }
        }

        #[\ReturnTypeWillChange]
        public function prepare(string $query, array $options = [])
        {
            $this->vigilar($query);
            return parent::prepare($query, $options);
        }

        #[\ReturnTypeWillChange]
        public function query(string $query, ?int $fetchMode = null, ...$fetchModeArgs)
        {
            $this->vigilar($query);
            return $fetchMode === null
                ? parent::query($query)
                : parent::query($query, $fetchMode, ...$fetchModeArgs);
        }

        #[\ReturnTypeWillChange]
        public function exec(string $statement)
        {
            $this->vigilar($statement);
            return parent::exec($statement);
        }
    }
}
