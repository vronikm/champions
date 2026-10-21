<?php

/**
 * Textos de los consentimientos LOPDP.
 *
 * Viven acá y no incrustados en la vista porque de cada aceptación se guarda
 * el SHA-256 del texto exacto que se mostró (alumno_consentimiento.consent_textohash).
 * Así, si mañana cambia la redacción, sigue siendo demostrable a qué consintió
 * cada representante.
 *
 * IMPORTANTE: cualquier edición de estos textos cambia el hash. Eso es
 * deliberado — es lo que permite distinguir versiones. No los edite para
 * corregir espacios o tildes sin entender que las aceptaciones anteriores
 * quedarán asociadas al hash viejo (que es justamente lo correcto).
 */

return [

    'DATOS' => [
        'titulo' => 'Consentimiento para el Tratamiento de Datos Personales',
        'texto'  => <<<HTML
<p>De conformidad con la <strong>Ley Orgánica de Protección de Datos Personales del Ecuador (LOPDP)</strong>,
publicada en el Registro Oficial Suplemento No. 459 del 26 de mayo de 2021, y su Reglamento General,
yo, en mi calidad de representante legal del alumno/a inscrito, declaro que:</p>

<p><strong>1. Información recopilada:</strong> Autorizo al club de baloncesto
<em>{ESCUELA}</em> a recopilar, almacenar y tratar los datos personales
proporcionados en este formulario, incluyendo: nombres, apellidos, número de cédula/identificación,
dirección, correo electrónico, número de celular, fecha de nacimiento, género y fotografía
del representante y del alumno/a.</p>

<p><strong>2. Finalidad del tratamiento:</strong> Los datos serán utilizados exclusivamente para:</p>
<ul>
    <li>Gestión administrativa de la inscripción y matrícula del alumno/a.</li>
    <li>Comunicación entre la escuela y el representante legal.</li>
    <li>Generación de carnets, credenciales y documentos internos.</li>
    <li>Control de asistencia y seguimiento deportivo.</li>
    <li>Cumplimiento de obligaciones legales y normativas aplicables.</li>
</ul>

<p><strong>3. Derechos del titular:</strong> Conforme a los artículos 17 al 24 de la LOPDP,
el titular de los datos tiene derecho a: acceder, rectificar, actualizar, eliminar, oponerse
al tratamiento de sus datos personales, así como a la portabilidad de los mismos.
Para ejercer estos derechos puede comunicarse al correo electrónico de la sede correspondiente.</p>

<p><strong>4. Tiempo de conservación:</strong> Los datos personales serán conservados mientras
el alumno/a permanezca inscrito en la escuela y hasta un máximo de 2 años posteriores a su retiro,
salvo que exista una obligación legal que requiera mayor tiempo de conservación.</p>

<p><strong>5. Seguridad:</strong> La escuela implementa medidas técnicas y organizativas
adecuadas para proteger los datos personales contra el acceso no autorizado, pérdida,
destrucción o alteración.</p>
HTML,
        'casilla' => 'He leído y acepto el tratamiento de datos personales conforme a la LOPDP.',
    ],

    'IMAGEN' => [
        'titulo' => 'Autorización de Uso de Imagen del Menor',
        'texto'  => <<<HTML
<p>En concordancia con el <strong>artículo 52 del Código de la Niñez y Adolescencia del Ecuador</strong>
y la <strong>Ley Orgánica de Protección de Datos Personales (LOPDP)</strong>, yo, en mi calidad de
representante legal, <strong>AUTORIZO</strong> de manera libre, voluntaria, expresa e informada a la
club de baloncesto <em>{ESCUELA}</em> para que:</p>

<p><strong>1.</strong> Capture, utilice, reproduzca y publique <strong>fotografías, videos
y material audiovisual</strong> en los que aparezca el alumno/a inscrito, tomados durante
entrenamientos, partidos, campeonatos, eventos deportivos y actividades organizadas
por la escuela.</p>

<p><strong>2.</strong> Dicho material podrá ser utilizado en:</p>
<ul>
    <li>Redes sociales oficiales de la escuela (Facebook, Instagram, TikTok, YouTube, etc.).</li>
    <li>Página web institucional.</li>
    <li>Material publicitario impreso y digital (afiches, banners, folletos).</li>
    <li>Carnets, credenciales y documentos internos.</li>
    <li>Reportajes y notas de prensa relacionadas con la escuela.</li>
    <li>Presentaciones y eventos promocionales de la escuela.</li>
</ul>

<p><strong>3.</strong> La presente autorización se otorga de manera <strong>gratuita</strong>,
sin que genere derecho a compensación económica alguna, y estará vigente durante
todo el período de inscripción del alumno/a en la escuela.</p>

<p><strong>4.</strong> La escuela se compromete a que el uso de la imagen será siempre
respetuoso, digno y en el contexto de las actividades deportivas y educativas de
la institución, velando por el interés superior del menor conforme al artículo 11
del Código de la Niñez y Adolescencia.</p>

<p><strong>5. Revocatoria:</strong> Esta autorización puede ser revocada en cualquier
momento mediante solicitud escrita dirigida a la dirección de la escuela, sin que
ello afecte la licitud del tratamiento realizado con anterioridad.</p>
HTML,
        'casilla' => 'Autorizo el uso de la imagen del alumno/a en fotos, videos y material de la escuela.',
    ],

];
