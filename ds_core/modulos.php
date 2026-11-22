<?php
/*
|--------------------------------------------------------------------------
| Registro de modulos del ecosistema DigiSports
|--------------------------------------------------------------------------
| Fuente unica de verdad sobre que aplicaciones existen. La consumen tanto
| el Hub (que ademas les anade metricas) como el selector de aplicaciones
| que aparece dentro de cada modulo.
|
| Para incorporar un modulo nuevo basta con anadir su entrada aqui y poner
| 'activo' => true cuando su carpeta ds_* ya tenga aplicacion.
|
| Deliberadamente NO consulta la base de datos: es un catalogo estatico y
| barato, para que el launcher no pague el coste de las metricas del Hub.
*/

if (!function_exists('ds_modulos')) {

    function ds_modulos(): array
    {
        return [
            'core' => [
                'nombre'  => 'Core',
                'tagline' => 'Usuarios, Roles &amp; Permisos',
                'icono'   => 'fas fa-shield-alt',
                'acento'  => 'var(--ds-core)',
                'url'     => DS_HUB_URL . 'ds_core/admin/',
                'activo'  => true,
                'accesos' => [
                    ['texto' => 'Usuarios', 'icono' => 'fas fa-users',       'ruta' => 'usuarioList/'],
                    ['texto' => 'Roles',    'icono' => 'fas fa-user-shield', 'ruta' => 'rolList/'],
                    ['texto' => 'Permisos', 'icono' => 'fas fa-key',         'ruta' => 'permisoRol/'],
                ],
            ],
            'basketball' => [
                'nombre'  => 'Fútbol',
                'tagline' => 'Escuela &amp; Formación',
                'icono'   => 'fas fa-futbol',
                'acento'  => 'var(--ds-basketball)',
                'url'     => DS_FUTBOL_URL,
                'activo'  => true,
                'accesos' => [
                    ['texto' => 'Alumnos',       'icono' => 'fas fa-user-plus',        'ruta' => 'alumnoList/'],
                    ['texto' => 'Asistencia',    'icono' => 'fas fa-clipboard-check',  'ruta' => 'asistencia/'],
                    ['texto' => 'Mensualidades', 'icono' => 'fas fa-dollar-sign',      'ruta' => 'cobranzaPension/'],
                ],
            ],
            /* DigiCheck: la asistencia con el QR del carnet. Escanea en la
               cancha sin Internet (la lista se descarga antes y las marcas se
               envían después) y emite los carnets. Ver ds_check/. */
            'check' => [
                'nombre'  => 'DigiCheck',
                'tagline' => 'Control inteligente de asistencia',
                'lema'    => 'Porque cada entrenamiento cuenta',
                'icono'   => 'fas fa-qrcode',
                'acento'  => 'var(--ds-check)',
                'url'     => DS_CHECK_URL,
                'activo'  => true,
                'accesos' => [
                    ['texto' => 'Escanear', 'icono' => 'fas fa-camera',  'ruta' => 'escanear/'],
                    ['texto' => 'Carnets',  'icono' => 'fas fa-id-card', 'ruta' => 'carnets/'],
                ],
            ],
            'arena' => [
                'nombre'  => 'Arena',
                'tagline' => 'Instalaciones &amp; Reservas',
                'icono'   => 'fas fa-warehouse',
                'acento'  => 'var(--ds-arena)',
                'url'     => DS_ARENA_URL,
                'activo'  => true,
                'accesos' => [
                    ['texto' => 'Instalaciones', 'icono' => 'fas fa-warehouse',      'ruta' => 'instalacionList/'],
                    ['texto' => 'Reservas',      'icono' => 'fas fa-calendar-check', 'ruta' => 'reservaList/'],
                    ['texto' => 'Monedero',      'icono' => 'fas fa-wallet',         'ruta' => 'monederoList/'],
                ],
            ],
            'league' => [
                'nombre'  => 'League',
                'tagline' => 'Torneos &amp; Competición',
                'icono'   => 'fas fa-trophy',
                'acento'  => 'var(--ds-league)',
                'url'     => DS_LEAGUE_URL,
                'activo'  => true,
                'accesos' => [
                    ['texto' => 'Panel', 'icono' => 'fas fa-trophy', 'ruta' => 'panel/'],
                ],
            ],
            'insights' => [
                'nombre'  => 'Insights',
                'tagline' => 'Business Intelligence',
                'icono'   => 'fas fa-chart-line',
                'acento'  => 'var(--ds-insights)',
                'url'     => DS_INSIGHTS_URL,
                'activo'  => true,
                'accesos' => [
                    ['texto' => 'Panel',      'icono' => 'fas fa-chart-line',  'ruta' => 'dashboard/'],
                    ['texto' => 'Financiero', 'icono' => 'fas fa-dollar-sign', 'ruta' => 'financiero/'],
                    ['texto' => 'Reportes',   'icono' => 'fas fa-file-alt',    'ruta' => 'reporteList/'],
                ],
                ],
        ];
    }
}
