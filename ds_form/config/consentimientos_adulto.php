<?php

/**
 * Textos de los consentimientos LOPDP para ALUMNOS ADULTOS (migración 086).
 *
 * Los acepta el propio alumno, mayor de edad, en ds_form/adulto.php: van en
 * primera persona y sin «representante» ni «menor». Se guardan con los
 * mismos tipos DATOS e IMAGEN que los de menores —así los reconocen
 * consentimientos, pendientes y reportes— y con el SHA-256 de ESTE texto en
 * consent_textohash, de modo que cada aceptación sigue ligada a la
 * redacción que se mostró. consent_observacion = 'ADULTO' los distingue.
 *
 * IMPORTANTE: editar un texto cambia su hash. Es deliberado: las
 * aceptaciones anteriores quedan ligadas a la versión que vieron.
 *
 * Marcador: {ESCUELA}.
 */

return [

    'DATOS' => [
        'titulo' => 'Consentimiento para el Tratamiento de mis Datos Personales',
        'texto'  => <<<HTML
<p>De conformidad con la <strong>Ley Orgánica de Protección de Datos Personales del Ecuador (LOPDP)</strong>,
publicada en el Registro Oficial Suplemento No. 459 del 26 de mayo de 2021, y su Reglamento General,
yo, mayor de edad y titular de los datos, declaro que:</p>

<p><strong>1. Información recopilada:</strong> Autorizo a <em>{ESCUELA}</em> a recopilar, almacenar
y tratar los datos personales que proporciono en este formulario: nombres, apellidos, número de
cédula o pasaporte, dirección, correo electrónico, número de celular, fecha de nacimiento, sexo
y fotografía.</p>

<p><strong>2. Finalidad del tratamiento:</strong> Mis datos serán utilizados exclusivamente para:</p>
<ul>
    <li>Gestión administrativa de mi inscripción y matrícula.</li>
    <li>Emisión de recibos de pago y facturas electrónicas a mi nombre.</li>
    <li>Comunicación entre la escuela y yo.</li>
    <li>Generación de carnets, credenciales y documentos internos.</li>
    <li>Control de asistencia y seguimiento deportivo.</li>
    <li>Cumplimiento de obligaciones legales y tributarias aplicables.</li>
</ul>

<p><strong>3. Mis derechos:</strong> Conforme a los artículos 17 al 24 de la LOPDP, tengo derecho a
acceder, rectificar, actualizar, eliminar y oponerme al tratamiento de mis datos personales, así
como a su portabilidad. Para ejercerlos puedo comunicarme al correo electrónico de la sede
correspondiente.</p>

<p><strong>4. Tiempo de conservación:</strong> Mis datos se conservarán mientras permanezca inscrito
en la escuela y hasta un máximo de 2 años posteriores a mi retiro, salvo que una obligación legal
(por ejemplo, tributaria) requiera un plazo mayor.</p>

<p><strong>5. Seguridad:</strong> La escuela implementa medidas técnicas y organizativas adecuadas
para proteger mis datos contra el acceso no autorizado, pérdida, destrucción o alteración.</p>
HTML,
        'casilla' => 'He leído y acepto el tratamiento de mis datos personales conforme a la LOPDP.',
    ],

    'IMAGEN' => [
        'titulo' => 'Autorización de Uso de mi Imagen',
        'texto'  => <<<HTML
<p>De conformidad con la <strong>Ley Orgánica de Protección de Datos Personales (LOPDP)</strong>, yo,
mayor de edad, <strong>AUTORIZO</strong> de manera libre, voluntaria, expresa e informada a
<em>{ESCUELA}</em> para que:</p>

<p><strong>1.</strong> Capture, utilice, reproduzca y publique <strong>fotografías, videos y material
audiovisual</strong> en los que yo aparezca, tomados durante entrenamientos, partidos, campeonatos,
eventos deportivos y actividades organizadas por la escuela.</p>

<p><strong>2.</strong> Dicho material podrá ser utilizado en:</p>
<ul>
    <li>Redes sociales oficiales de la escuela (Facebook, Instagram, TikTok, YouTube, etc.).</li>
    <li>Página web institucional.</li>
    <li>Material publicitario impreso y digital (afiches, banners, folletos).</li>
    <li>Carnets, credenciales y documentos internos.</li>
    <li>Reportajes y notas de prensa relacionadas con la escuela.</li>
</ul>

<p><strong>3.</strong> La presente autorización se otorga de manera <strong>gratuita</strong>, sin que
genere derecho a compensación económica alguna, y estará vigente durante todo el período de mi
inscripción en la escuela.</p>

<p><strong>4.</strong> La escuela se compromete a que el uso de mi imagen será siempre respetuoso,
digno y en el contexto de sus actividades deportivas.</p>

<p><strong>5. Revocatoria:</strong> Puedo revocar esta autorización en cualquier momento mediante
solicitud escrita dirigida a la escuela, sin que ello afecte la licitud del uso realizado con
anterioridad.</p>
HTML,
        'casilla' => 'Autorizo el uso de mi imagen en fotos, videos y material de la escuela.',
    ],

];
