/*
| El formulario del alumno: alta (alumnoNew) y edición (alumnoUpdate).
|
| Las dos pantallas tienen el mismo formulario —secciones en tarjetas, sin
| pestañas— y este archivo hace lo que comparten:
|
|   · la edad y el tiempo en la escuela, al lado de cada fecha;
|   · el horario elegido, día por día (cargarHorarioDias, JSON);
|   · tras un alta correcta ajax.js vacía el formulario (form.reset()), y
|     aquí se deja también limpio lo que reset() no toca: las vistas previas
|     de las imágenes, el buscador de horarios y su lista.
|
| Necesita jQuery y select2 (el buscador de horarios). Va después de
| form-secciones.js, que lleva los obligatorios y el formato.
| El formulario es #formAlumno; su action es alumnoAjax.php.
*/
(function () {
    'use strict';

    var form = document.getElementById('formAlumno');
    if (!form) { return; }
    var DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];

    /* --- Edad y tiempo en la escuela ------------------------------------ */
    function tiempoDesde(valor) {
        if (!valor) { return ''; }
        var p = valor.split('-'), d = new Date(+p[0], +p[1] - 1, +p[2]);
        if (isNaN(d)) { return ''; }
        var hoy = new Date();
        var meses = (hoy.getFullYear() - d.getFullYear()) * 12 + hoy.getMonth() - d.getMonth() - (hoy.getDate() < d.getDate() ? 1 : 0);
        if (meses <= 0) { return ''; }      /* «0 meses» junto a la fecha de hoy no dice nada */
        if (meses >= 12) { var a = Math.floor(meses / 12); return a + (a === 1 ? ' año' : ' años'); }
        return meses + (meses === 1 ? ' mes' : ' meses');
    }
    var calculos = [['alumno_fechanacimiento', 'edad_texto'], ['alumno_fechaingreso', 'ingreso_texto']]
        .map(function (par) {
            var campo = document.getElementById(par[0]), salida = document.getElementById(par[1]);
            if (!campo || !salida) { return function () {}; }
            function poner() { salida.textContent = tiempoDesde(campo.value) || '--'; }
            campo.addEventListener('input', poner);
            poner();
            return poner;
        });

    /* --- Horario: buscador y vista previa día por día ------------------- */
    function pintarHorario(h) {
        var lista = document.getElementById('horario_dias');
        if (!lista) { return; }
        lista.textContent = '';
        if (!h || !h.dias || !h.dias.length) {
            var li = document.createElement('li');
            li.className = 'ds-editar__vacio';
            li.textContent = h ? 'El horario no tiene días cargados.' : 'Sin horario asignado.';
            lista.appendChild(li);
            return;
        }
        h.dias.forEach(function (d) {
            var li = document.createElement('li');
            var dia = document.createElement('span'); dia.className = 'ds-perfil__dia'; dia.textContent = DIAS[d.dia] || ('Día ' + d.dia);
            var hora = document.createElement('span'); hora.className = 'ds-perfil__hora'; hora.textContent = [d.inicio, d.fin].filter(Boolean).join(' – ');
            var meta = document.createElement('span'); meta.className = 'ds-perfil__lugar';
            [['fa-location-dot', d.lugar], ['fa-user', d.profesor]].forEach(function (x) {
                if (!x[1]) { return; }
                var s = document.createElement('span'), i = document.createElement('i');
                i.className = 'fas ' + x[0] + ' me-1'; i.setAttribute('aria-hidden', 'true');
                s.appendChild(i); s.appendChild(document.createTextNode(x[1]));
                meta.appendChild(s);
            });
            li.append(dia, hora, meta);
            lista.appendChild(li);
        });
    }
    function cargarHorario(id) {
        if (!id) { pintarHorario(null); return; }
        var datos = new FormData();
        datos.append('modulo_alumno', 'cargarHorarioDias');
        datos.append('horarioid', id);
        fetch(form.getAttribute('action'), { method: 'POST', body: datos })
            .then(function (r) { return r.json(); })
            .then(pintarHorario)
            .catch(function () { pintarHorario(null); });
    }
    /* select2 avisa del cambio con un evento de jQuery: el nativo no lo oye. */
    var $horario = window.jQuery ? window.jQuery('#horarioid') : null;
    if ($horario && $horario.length) {
        $horario.select2({ width: '100%' }).on('change', function () { cargarHorario(this.value); });
    }

    /* Los obligatorios, el formato y los botones de copiar los lleva
       form-secciones.js, común a todos los formularios .ds-editar. */

    /* --- Tras el reset de un alta correcta -------------------------------
       reset() devuelve los campos a su valor inicial pero no sabe de lo que
       pinta JavaScript: se ponía a cero el <input type=file> y seguía viéndose
       la foto del alumno anterior. */
    form.addEventListener('reset', function () {
        setTimeout(function () {
            form.querySelectorAll('.fileinput.fileinput-exists [data-bs-dismiss="fileinput"]').forEach(function (a) { a.click(); });
            if ($horario && $horario.length) { $horario.trigger('change'); }
            calculos.forEach(function (poner) { poner(); });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }, 0);
    });
})();
