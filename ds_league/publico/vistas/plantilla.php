<?php
/*
| Plantilla pública de un equipo.
|
| AQUI SE VE LA DECISION DE PRIVACIDAD
|
| Nombre, dorsal y rol. Ni cedula ni fecha de nacimiento: el controlador
| no las selecciona siquiera, asi que no hay nada que filtrar aqui.
|
| La foto sale solo si hay consentimiento registrado. Cuando no lo hay se
| pintan las INICIALES en vez de dejar un hueco: un hueco parece un fallo
| y empuja a "arreglarlo" publicando la foto.
*/
$plantilla = $datos['plantilla'] ?? [];

$equipo = '';
$titulo = 'Plantilla';
$descripcion = 'Plantilla del equipo.';

$roles = ['J' => 'Jugador', 'E' => 'Entrenador', 'A' => 'Asistente', 'D' => 'Delegado'];

require __DIR__ . '/_marco.php';

/* Iniciales del nombre, para cuando no hay foto autorizada. */
$iniciales = static function (string $nombres, string $apellidos): string {
    $a = mb_substr(trim($apellidos), 0, 1, 'UTF-8');
    $n = mb_substr(trim($nombres), 0, 1, 'UTF-8');
    return mb_strtoupper($a . $n, 'UTF-8');
};

/* Jugadores primero; el cuerpo tecnico despues. */
$porRol = [];
foreach ($plantilla as $p) { $porRol[$p['plantilla_rol']][] = $p; }
?>

<?php foreach (['J', 'E', 'A', 'D'] as $rol): ?>
    <?php if (empty($porRol[$rol])) { continue; } ?>
    <div class="caja">
        <p class="caja__t">
            <?php echo $h($roles[$rol] ?? ''); ?><?php echo $rol === 'J' ? 'es' : ''; ?>
        </p>
        <?php foreach ($porRol[$rol] as $j): ?>
            <div class="fila">
                <?php /* El dorsal y el retrato son cosas distintas y van las dos.
                         Poner el dorsal EN LUGAR del retrato dejaba sin efecto el
                         consentimiento de imagen justo para los jugadores, que son
                         los únicos que llevan dorsal. */ ?>
                <?php if ($rol === 'J' && $j['plantilla_dorsal'] !== null): ?>
                    <span class="dorsal"><?php echo (int)$j['plantilla_dorsal']; ?></span>
                <?php endif; ?>

                <?php if ($j['foto']): ?>
                    <img class="foto" alt=""
                         src="<?php echo APP_URL . 'assets/img/personas/' . rawurlencode($j['foto']); ?>">
                <?php else: ?>
                    <?php /* Sin autorización de imagen: iniciales. Un hueco vacío
                             parecería un fallo y empujaría a «arreglarlo». */ ?>
                    <span class="ini"><?php
                        echo $h($iniciales($j['persona_nombres'], $j['persona_apellidos'])); ?></span>
                <?php endif; ?>

                <span class="fila__n">
                    <b><?php echo $h($j['persona_apellidos'] . ' ' . $j['persona_nombres']); ?></b>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>

<?php if (!$plantilla): ?>
<div class="caja"><div class="vacio">Este equipo no tiene jugadores habilitados.</div></div>
<?php endif; ?>

<?php require __DIR__ . '/_pie.php'; ?>
