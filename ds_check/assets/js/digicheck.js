/*
|--------------------------------------------------------------------------
| DigiCheck — la pantalla de escaneo
|--------------------------------------------------------------------------
| 1. LISTA. Con Internet se descarga (ajax/listaAjax.php) y se guarda en el
|    dispositivo (IndexedDB, por usuario). En la cancha se trabaja con ella:
|    el QR se busca por coincidencia exacta de su código (DSC1:<id>:<firma>).
|
| 2. ESCANEO CONTINUO. La cámara trasera; el lector nativo del navegador
|    (BarcodeDetector, Android) o, si no hay, jsQR (iPhone y el resto). Cada
|    lectura: reconoce al alumno, guarda la marca y la CONFIRMA con color,
|    icono, sonido y vibración. El mismo QR no se vuelve a leer en 3 s, y un
|    alumno ya registrado hoy no se registra dos veces.
|
| 3. MARCAS. Con la cola de la asistencia sin conexión de Fútbol
|    (DSAsistencia, asistencia-offline.js): se guardan antes de enviarse y
|    se envían solas al volver la señal (al abrir, al conectarse, cada 30 s
|    y en segundo plano en Android). El servidor aplica sus reglas: plazo de
|    3 días, permisos y, si alguien registró otra cosa, conserva la suya.
*/
(function () {
	'use strict';

	var C = window.DIGICHECK || {};
	var Q = window.DSAsistencia;
	var $ = function (id) { return document.getElementById(id); };
	var raiz = $('dc');
	if (!raiz) { return; }
	if (!Q) {
		aviso('No se pudo cargar el almacenamiento de asistencias. Recargue la página con Internet.', 'error');
		return;
	}
	Q.configurar({ url: C.urlEnvio });

	var K_LISTA = 'check_lista_' + C.usuario;     /* la lista descargada (IndexedDB, almacén «meta») */
	var K_HOY   = 'check_hoy_' + C.usuario;       /* lo registrado hoy en este dispositivo */
	var NOMBRE  = { P: 'Presente', A: 'Atraso', F: 'Falta', J: 'Justificado' };
	var DIAS    = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

	var st = {
		lista: null, porCodigo: {}, porId: {}, modo: 'P',
		hoy: { fecha: Q.hoy(), items: [] },
		stream: null, track: null, detector: null, jsqr: null, lienzo: null,
		escaneando: false, pausado: false, bloqueo: 0, ultimo: { codigo: '', t: 0 },
		camaras: [], camaraIdx: 0, linterna: false, wake: null, audio: null,
		instalar: null, envioProgramado: null
	};

	/*==============  Utilidades  ==============*/
	function aviso(texto, tono) {
		var a = $('dcAviso');
		if (!a) { return; }
		if (!texto) { a.hidden = true; return; }
		a.hidden = false;
		a.setAttribute('data-tono', tono || 'aviso');
		a.innerHTML = texto;
	}
	function esc(t) { return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
	function horaAhora() { var d = new Date(); return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); }
	function normal(t) { return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
	function nombreDe(a) { return (a.nombres + ' ' + a.apellidos).trim(); }
	function iniciales(a) { return ((a.nombres || ' ')[0] + (a.apellidos || ' ')[0]).toUpperCase(); }
	function cuando(iso) {
		var d = new Date(iso);
		if (isNaN(d)) { return ''; }
		var hh = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
		var dias = Math.floor((new Date().setHours(0, 0, 0, 0) - new Date(d).setHours(0, 0, 0, 0)) / 864e5);
		return dias === 0 ? 'hoy a las ' + hh : dias === 1 ? 'ayer a las ' + hh
			: 'el ' + String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + ' a las ' + hh;
	}

	/*==============  La lista  ==============*/
	function usarLista(l) {
		st.lista = l;
		st.porCodigo = {}; st.porId = {};
		(l.alumnos || []).forEach(function (a) { st.porCodigo[a.codigo] = a; st.porId[a.id] = a; });
	}

	function pintarLista() {
		var l = st.lista, hay = !!(l && l.alumnos && l.alumnos.length);
		$('dcSinLista').hidden = hay;
		$('dcEscaner').hidden = !hay;
		$('dcHoy').hidden = !hay;
		var boton = $('dcDescargar').querySelector('span');
		if (!l) {
			$('dcListaTexto').textContent = 'Sin lista en este dispositivo';
			boton.textContent = 'Descargar lista';
			return;
		}
		var n = (l.alumnos || []).length, h = (l.horarios || []).length;
		$('dcListaTexto').textContent = n + (n === 1 ? ' alumno' : ' alumnos') + ' de ' + h + (h === 1 ? ' horario' : ' horarios')
			+ ' · descargada ' + cuando(l.generada);
		boton.textContent = 'Actualizar';
		if (l && !n) {
			$('dcSinLista').hidden = false;
			$('dcSinLista').querySelector('h2').textContent = 'No tiene alumnos en sus horarios';
			$('dcSinLista').querySelector('p').textContent = 'La lista llegó vacía: no tiene horarios asignados o no tienen alumnos activos. Consulte con administración.';
		}
		var dias = (Date.now() - new Date(l.generada).getTime()) / 864e5;
		if (n && dias > 7) {
			aviso('La lista tiene más de una semana. Actualícela con Internet para incluir a los alumnos nuevos.', 'aviso');
		}
	}

	function descargarLista() {
		var b = $('dcDescargar');
		b.disabled = true;
		b.querySelector('span').textContent = 'Descargando…';
		return fetch(C.urlLista, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
			.then(function (r) { return r.json().then(function (j) { return { status: r.status, j: j }; }, function () { return { status: r.status, j: null }; }); })
			.then(function (res) {
				var j = res.j;
				if (res.status === 401 || (j && j.sinSesion) || !j) {
					aviso('Su sesión expiró. <a href="' + esc(C.urlLogin) + '">Inicie sesión</a> y vuelva para descargar la lista.', 'error');
					return;
				}
				if (!j.ok) { aviso(esc(j.texto || 'No se pudo descargar la lista.'), 'error'); return; }
				if (Number(j.usuario) !== Number(C.usuario)) {
					aviso('La sesión es de otro usuario. Recargue la página.', 'error');
					return;
				}
				return Q.ponerMeta(K_LISTA, j).then(function () {
					usarLista(j);
					aviso('Lista descargada: ' + j.alumnos.length + ' alumnos. Ya puede escanear sin Internet.', 'ok');
				});
			})
			.catch(function () { aviso('Sin conexión. La lista se descarga con Internet; mientras tanto se usa la guardada.', 'aviso'); })
			.then(function () { b.disabled = false; pintarLista(); });
	}

	/*==============  Lo registrado hoy  ==============*/
	function cargarHoy() {
		return Q.leerMeta(K_HOY).then(function (h) {
			st.hoy = (h && h.fecha === Q.hoy()) ? h : { fecha: Q.hoy(), items: [] };
		});
	}
	function guardarHoy() { return Q.ponerMeta(K_HOY, st.hoy).catch(function () {}); }

	function pintarHoy() {
		var lista = $('dcHoyLista');
		$('dcHoyTotal').textContent = st.hoy.items.length;
		if (!st.hoy.items.length) {
			lista.innerHTML = '<li class="text-body-secondary" style="display:block">Todavía nadie. Escanee el primer carnet.</li>';
			return Promise.resolve();
		}
		return Promise.all([Q.pendientes({ usuario: C.usuario, fecha: st.hoy.fecha }), Q.avisos({ usuario: C.usuario, fecha: st.hoy.fecha })])
			.then(function (r) {
				var pend = {}, avs = {};
				r[0].forEach(function (m) { pend[m.alumno] = true; });
				r[1].forEach(function (m) { avs[m.alumno] = m; });
				lista.innerHTML = st.hoy.items.map(function (it) {
					var envio = avs[it.alumno] ? 'aviso' : pend[it.alumno] ? 'pendiente' : 'enviado';
					var txt = envio === 'aviso' ? '<i class="fas fa-triangle-exclamation"></i>' + esc(avs[it.alumno].texto || 'No se registró')
						: envio === 'pendiente' ? '<i class="fas fa-mobile-screen"></i>En el dispositivo'
						: '<i class="fas fa-cloud"></i>Enviado';
					return '<li><span class="dc-hoy__ini" aria-hidden="true">' + esc(it.ini) + '</span>'
						+ '<span class="dc-hoy__nombre">' + esc(it.nombre) + '<small>' + esc(it.hora) + ' · ' + esc(NOMBRE[it.estado] || it.estado)
						+ (it.horario ? ' · ' + esc(it.horario) : '') + '</small></span>'
						+ '<span class="dc-hoy__estado" data-envio="' + envio + '">' + txt + '</span></li>';
				}).join('');
			}).catch(function () {});
	}

	/*==============  Conexión y envío  ==============*/
	function pintarRed() {
		var p = $('dcRed'), en = navigator.onLine;
		p.setAttribute('data-red', en ? 'si' : 'no');
		p.querySelector('i').className = 'fas ' + (en ? 'fa-wifi' : 'fa-plane');
		p.querySelector('span').textContent = en ? 'En línea' : 'Sin conexión';
	}

	function pintarPendientes() {
		return Q.pendientes({ usuario: C.usuario }).then(function (l) {
			var n = l.length, p = $('dcPendientes');
			p.hidden = !n;
			$('dcEnviar').hidden = !n || !navigator.onLine;
			p.querySelector('span').textContent = n + (n === 1 ? ' por enviar' : ' por enviar');
			return n;
		}).catch(function () { return 0; });
	}

	function sincronizar(manual) {
		if (!navigator.onLine) {
			if (manual) { aviso('Sin conexión: las asistencias quedan guardadas en el dispositivo y se envían solas al volver la señal.', 'aviso'); }
			return Promise.resolve();
		}
		return Q.sincronizar().then(function (r) {
			if (r.estado === 'sin-sesion') {
				aviso('Hay asistencias guardadas en el dispositivo. <a href="' + esc(C.urlLogin) + '">Inicie sesión</a> para enviarlas.', 'error');
			} else if (r.conflictos || r.rechazadas) {
				aviso((r.conflictos ? r.conflictos + ' ya estaban registradas con otra marca (se conservó la del sistema). ' : '')
					+ (r.rechazadas ? r.rechazadas + ' no se aceptaron (fuera de plazo o el alumno ya no está en el horario). ' : '')
					+ 'Revise la lista de hoy.', 'aviso');
			} else if (manual && r.estado === 'ok' && r.enviadas) {
				aviso(r.enviadas + (r.enviadas === 1 ? ' asistencia enviada.' : ' asistencias enviadas.'), 'ok');
			} else if (manual && r.estado === 'sin-red') {
				aviso('No se pudo conectar. Se reintentará sola.', 'aviso');
			}
		}).then(pintarPendientes).then(pintarHoy);
	}

	/* Tras registrar, se envía al rato (agrupando varias lecturas seguidas). */
	function programarEnvio() {
		clearTimeout(st.envioProgramado);
		st.envioProgramado = setTimeout(function () { sincronizar(false); }, 4000);
		Q.pedirEnvioEnSegundoPlano();
	}

	/*==============  Sonido y confirmación  ==============*/
	function audio() {
		if (!st.audio) {
			try { st.audio = new (window.AudioContext || window.webkitAudioContext)(); } catch (e) { st.audio = null; }
		}
		if (st.audio && st.audio.state === 'suspended') { st.audio.resume().catch(function () {}); }
		return st.audio;
	}
	function tono(frec, inicio, dur, tipo) {
		var a = audio();
		if (!a) { return; }
		var o = a.createOscillator(), g = a.createGain(), t = a.currentTime + inicio;
		o.type = tipo || 'sine';
		o.frequency.setValueAtTime(frec, t);
		g.gain.setValueAtTime(0.0001, t);
		g.gain.exponentialRampToValueAtTime(0.35, t + 0.015);
		g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
		o.connect(g); g.connect(a.destination);
		o.start(t); o.stop(t + dur + 0.02);
	}
	function sonar(tipo) {
		if (tipo === 'ok')      { tono(880, 0, 0.12); tono(1320, 0.13, 0.18); }
		else if (tipo === 'ya') { tono(660, 0, 0.16); tono(660, 0.22, 0.16); }
		else                    { tono(196, 0, 0.42, 'square'); }
		try { if (navigator.vibrate) { navigator.vibrate(tipo === 'ok' ? 60 : tipo === 'ya' ? [60, 80, 60] : [180, 80, 180]); } } catch (e) {}
	}

	var ICONO = { ok: 'fa-circle-check', ya: 'fa-circle-info', error: 'fa-circle-xmark' };
	var cierre = null;
	function confirmar(tipo, titulo, nombre, detalle) {
		var r = $('dcResultado');
		r.setAttribute('data-tipo', tipo);
		$('dcResIcono').className = 'fas ' + ICONO[tipo] + ' dc-resultado__icono';
		$('dcResTitulo').textContent = titulo;
		$('dcResNombre').textContent = nombre || '';
		$('dcResDetalle').textContent = detalle || '';
		r.hidden = false;
		sonar(tipo);
		var ms = tipo === 'ok' ? 1400 : 2200;
		st.bloqueo = Date.now() + ms;          /* no leer otro QR mientras se muestra */
		clearTimeout(cierre);
		cierre = setTimeout(function () { r.hidden = true; }, ms);
	}

	/*==============  Registrar  ==============*/
	/* El horario de la marca: el del alumno que entrena hoy (y, entre varios,
	   el más cercano a esta hora); si hoy no entrena, el primero. El horario
	   sólo sirve al servidor para comprobar el permiso: la asistencia es del
	   alumno y del día. */
	function elegirHorario(a) {
		var dia = new Date().getDay() || 7, ahora = horaAhora();
		var hs = (a.horarios || []).map(function (id) {
			return (st.lista.horarios || []).filter(function (h) { return h.id === id; })[0];
		}).filter(Boolean);
		var hoy = hs.map(function (h) {
			var d = (h.dias || []).filter(function (x) { return x.dia === dia; })
				.sort(function (x, y) { return Math.abs(minutos(x.inicio) - minutos(ahora)) - Math.abs(minutos(y.inicio) - minutos(ahora)); })[0];
			return d ? { h: h, d: d } : null;
		}).filter(Boolean).sort(function (x, y) { return Math.abs(minutos(x.d.inicio) - minutos(ahora)) - Math.abs(minutos(y.d.inicio) - minutos(ahora)); });
		if (hoy.length) { return { id: hoy[0].h.id, nombre: hoy[0].h.nombre, hoy: true }; }
		return hs.length ? { id: hs[0].id, nombre: hs[0].nombre, hoy: false } : null;
	}
	function minutos(hhmm) { var p = String(hhmm || '00:00').split(':'); return Number(p[0]) * 60 + Number(p[1] || 0); }

	function registrar(a) {
		var fecha = Q.hoy();
		if (st.hoy.fecha !== fecha) { st.hoy = { fecha: fecha, items: [] }; }
		var ya = st.hoy.items.filter(function (i) { return i.alumno === a.id; })[0];
		if (ya) {
			confirmar('ya', 'Ya registrado', nombreDe(a), 'Hoy a las ' + ya.hora + ' · ' + (NOMBRE[ya.estado] || ya.estado));
			return Promise.resolve();
		}
		var marcaServidor = st.lista.fecha === fecha ? (a.marcaHoy || '') : '';
		if (marcaServidor === 'P' || marcaServidor === 'A') {
			confirmar('ya', 'Ya estaba registrado', nombreDe(a), 'En el sistema como ' + NOMBRE[marcaServidor].toLowerCase());
			return Promise.resolve();
		}
		var h = elegirHorario(a);
		if (!h) {
			confirmar('error', 'Sin horario', nombreDe(a), 'No está en ninguno de sus horarios. Actualice la lista.');
			return Promise.resolve();
		}
		var estado = st.modo;
		return Q.guardar({ usuario: C.usuario, horario: h.id, alumno: a.id, fecha: fecha, estado: estado, anterior: marcaServidor })
			.then(function () {
				st.hoy.items.unshift({ alumno: a.id, nombre: nombreDe(a), ini: iniciales(a), hora: horaAhora(), estado: estado, horario: h.nombre });
				guardarHoy();
				confirmar('ok', estado === 'A' ? 'Atraso registrado' : 'Presente', nombreDe(a),
					h.nombre + (h.hoy ? '' : ' · hoy no es su día de entrenamiento') + (a.categoria ? ' · Cat. ' + a.categoria : ''));
				pintarHoy(); pintarPendientes(); programarEnvio();
			})
			.catch(function () { confirmar('error', 'No se guardó', nombreDe(a), 'Intente de nuevo.'); });
	}

	function procesar(texto) {
		texto = String(texto || '').trim();
		if (!texto || Date.now() < st.bloqueo) { return; }
		if (texto === st.ultimo.codigo && Date.now() - st.ultimo.t < 3000) { return; }   /* el mismo carnet frente a la cámara */
		st.ultimo = { codigo: texto, t: Date.now() };
		var a = st.porCodigo[texto];
		if (a) { registrar(a); return; }
		if (/^DSC\d+:\d+:/.test(texto)) {
			confirmar('error', 'No está en su lista', 'Carnet de DigiCheck', 'Es nuevo, cambió de horario o está inactivo. Actualice la lista con Internet.');
		} else {
			confirmar('error', 'QR no reconocido', '', 'No es un carnet de DigiCheck.');
		}
	}

	/*==============  Cámara y lectura  ==============*/
	function cargarJsQR() {
		if (window.jsQR) { return Promise.resolve(window.jsQR); }
		return new Promise(function (ok, mal) {
			var s = document.createElement('script');
			s.src = C.urlJsQR; s.async = true;
			s.onload = function () { window.jsQR ? ok(window.jsQR) : mal(new Error('jsQR')); };
			s.onerror = mal;
			document.head.appendChild(s);
		});
	}

	function prepararLector() {
		if (st.detector || st.jsqr) { return Promise.resolve(); }
		var nativo = ('BarcodeDetector' in window) && window.BarcodeDetector.getSupportedFormats
			? window.BarcodeDetector.getSupportedFormats().then(function (f) { return f.indexOf('qr_code') !== -1; }).catch(function () { return false; })
			: Promise.resolve(false);
		return nativo.then(function (si) {
			if (si) { st.detector = new window.BarcodeDetector({ formats: ['qr_code'] }); return; }
			return cargarJsQR().then(function (f) { st.jsqr = f; st.lienzo = document.createElement('canvas'); });
		});
	}

	function leer() {
		var v = $('dcVideo');
		if (!st.escaneando || st.pausado || document.hidden || v.readyState < 2 || Date.now() < st.bloqueo) { return Promise.resolve(); }
		if (st.detector) {
			return st.detector.detect(v).then(function (c) { if (c && c.length) { procesar(c[0].rawValue); } }).catch(function () {});
		}
		/* jsQR: el cuadrado central (donde está el visor), reducido a 480 px. */
		var w = v.videoWidth, h = v.videoHeight, lado = Math.min(w, h) * 0.8, n = Math.min(480, Math.round(lado));
		var cv = st.lienzo; cv.width = n; cv.height = n;
		var cx = cv.getContext('2d', { willReadFrequently: true });
		cx.drawImage(v, (w - lado) / 2, (h - lado) / 2, lado, lado, 0, 0, n, n);
		var r = st.jsqr(cx.getImageData(0, 0, n, n).data, n, n, { inversionAttempts: 'dontInvert' });
		if (r && r.data) { procesar(r.data); }
		return Promise.resolve();
	}

	function bucle() {
		if (!st.escaneando) { return; }
		leer().then(function () { setTimeout(bucle, st.detector ? 120 : 180); });
	}

	function abrirCamara() {
		var cam = st.camaras[st.camaraIdx];
		var video = cam ? { deviceId: { exact: cam.deviceId } } : { facingMode: { ideal: 'environment' } };
		video.width = { ideal: 1280 }; video.height = { ideal: 720 };
		return navigator.mediaDevices.getUserMedia({ video: video, audio: false }).then(function (s) {
			cerrarCamara();
			st.stream = s;
			st.track = s.getVideoTracks()[0];
			var v = $('dcVideo');
			v.srcObject = s;
			return v.play().catch(function () {});
		}).then(function () {
			var cap = st.track && st.track.getCapabilities ? st.track.getCapabilities() : {};
			$('dcLinterna').hidden = !cap.torch;
			st.linterna = false;
			$('dcLinterna').setAttribute('aria-pressed', 'false');
		});
	}
	function cerrarCamara() {
		if (st.stream) { st.stream.getTracks().forEach(function (t) { t.stop(); }); }
		st.stream = null; st.track = null;
	}

	function iniciar() {
		audio();                                   /* el sonido necesita un toque del usuario (iPhone) */
		$('dcCamaraError').hidden = true;
		if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
			mostrarErrorCamara('Este navegador no permite usar la cámara aquí. Ábralo con HTTPS en Chrome o Safari, o registre sin QR más abajo.');
			return;
		}
		$('dcBotonIniciar').disabled = true;
		prepararLector().then(abrirCamara).then(function () {
			return navigator.mediaDevices.enumerateDevices().then(function (d) {
				st.camaras = d.filter(function (x) { return x.kind === 'videoinput'; });
				$('dcCambiar').hidden = st.camaras.length < 2;
			}).catch(function () {});
		}).then(function () {
			$('dcIniciar').hidden = true;
			$('dcHerr').hidden = false;
			st.escaneando = true; st.pausado = false;
			raiz.setAttribute('data-estado', 'escaneando');
			pantallaEncendida(true);
			bucle();
		}).catch(function (e) {
			var nombre = e && e.name;
			mostrarErrorCamara(nombre === 'NotAllowedError' || nombre === 'SecurityError'
				? 'No hay permiso para usar la cámara. Permítalo en el candado de la barra de direcciones y vuelva a intentar.'
				: nombre === 'NotFoundError' ? 'Este dispositivo no tiene cámara. Registre sin QR más abajo.'
				: 'No se pudo abrir la cámara. Cierre otras aplicaciones que la usen y vuelva a intentar.');
		}).then(function () { $('dcBotonIniciar').disabled = false; });
	}
	function mostrarErrorCamara(t) { var e = $('dcCamaraError'); e.textContent = t; e.hidden = false; }

	function pausar(si) {
		st.pausado = si;
		var b = $('dcPausar');
		b.innerHTML = '<i class="fas ' + (si ? 'fa-play' : 'fa-pause') + '" aria-hidden="true"></i>';
		b.setAttribute('aria-label', si ? 'Reanudar' : 'Pausar');
		b.title = si ? 'Reanudar' : 'Pausar';
		raiz.setAttribute('data-estado', si ? 'pausado' : 'escaneando');
	}

	/* La pantalla no se apaga mientras se escanea (tabletas en la cancha). */
	function pantallaEncendida(si) {
		if (!('wakeLock' in navigator)) { return; }
		if (si && !st.wake) {
			navigator.wakeLock.request('screen').then(function (w) { st.wake = w; w.addEventListener('release', function () { st.wake = null; }); }).catch(function () {});
		} else if (!si && st.wake) { st.wake.release().catch(function () {}); st.wake = null; }
	}

	/*==============  Registro manual  ==============*/
	function buscar() {
		var q = normal($('dcBuscar').value).trim(), ul = $('dcBuscarLista');
		if (q.length < 2 || !st.lista) { ul.innerHTML = ''; return; }
		var palabras = q.split(/\s+/);
		var res = (st.lista.alumnos || []).filter(function (a) {
			var t = normal(a.nombres + ' ' + a.apellidos + ' ' + a.cedula);
			return palabras.every(function (p) { return t.indexOf(p) !== -1; });
		}).slice(0, 30);
		ul.innerHTML = res.length ? res.map(function (a) {
			return '<li><button type="button" data-id="' + a.id + '"><span>' + esc(nombreDe(a)) + '<br><small>' + esc(a.cedula) + (a.categoria ? ' · Cat. ' + esc(a.categoria) : '')
				+ '</small></span><i class="fas fa-plus" aria-hidden="true"></i></button></li>';
		}).join('') : '<li class="text-body-secondary">Nadie coincide en su lista.</li>';
	}

	/*==============  PWA: service worker e instalación  ==============*/
	function registrarSW() {
		if (!('serviceWorker' in navigator)) { return; }
		navigator.serviceWorker.register(C.urlSW, { scope: C.alcanceSW }).then(function () {
			return navigator.serviceWorker.ready;
		}).then(function (reg) {
			/* Guarda esta pantalla y todo lo que carga, para abrirla sin señal. */
			var recursos = [].slice.call(document.querySelectorAll('link[rel="stylesheet"][href], script[src], link[rel="manifest"], link[rel="apple-touch-icon"]'))
				.map(function (n) { return n.href || n.src; }).concat([C.urlJsQR]);
			if (reg.active) { reg.active.postMessage({ tipo: 'precargar', paginas: [location.href.split(/[?#]/)[0]], recursos: recursos }); }
		}).catch(function () {});
	}

	function prepararInstalacion() {
		var ios = /iphone|ipad|ipod/i.test(navigator.userAgent) && !window.MSStream;
		var instalada = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone;
		if (instalada) { return; }
		window.addEventListener('beforeinstallprompt', function (e) {
			e.preventDefault();
			st.instalar = e;
			$('dcInstalar').hidden = false;
		});
		if (ios) {
			$('dcInstalar').hidden = false;
			$('dcInstalarTexto').textContent = 'En iPhone o iPad: toque Compartir y luego «Añadir a pantalla de inicio».';
			$('dcInstalarBoton').hidden = true;
		}
	}

	/*==============  Eventos  ==============*/
	$('dcDescargar').addEventListener('click', descargarLista);
	$('dcEnviar').addEventListener('click', function () { sincronizar(true); });
	$('dcBotonIniciar').addEventListener('click', iniciar);
	$('dcPausar').addEventListener('click', function () { pausar(!st.pausado); });
	$('dcLinterna').addEventListener('click', function () {
		if (!st.track) { return; }
		st.linterna = !st.linterna;
		st.track.applyConstraints({ advanced: [{ torch: st.linterna }] }).catch(function () {});
		this.setAttribute('aria-pressed', st.linterna ? 'true' : 'false');
	});
	$('dcCambiar').addEventListener('click', function () {
		if (st.camaras.length < 2) { return; }
		st.camaraIdx = (st.camaraIdx + 1) % st.camaras.length;
		abrirCamara().catch(function () { mostrarErrorCamara('No se pudo cambiar de cámara.'); });
	});
	[].forEach.call(document.querySelectorAll('.dc-modo__op'), function (b) {
		b.addEventListener('click', function () {
			st.modo = b.getAttribute('data-modo');
			[].forEach.call(document.querySelectorAll('.dc-modo__op'), function (x) { x.setAttribute('aria-checked', x === b ? 'true' : 'false'); });
		});
	});
	$('dcBuscar').addEventListener('input', buscar);
	$('dcBuscarLista').addEventListener('click', function (e) {
		var b = e.target.closest('button[data-id]');
		if (!b) { return; }
		var a = st.porId[Number(b.getAttribute('data-id'))];
		if (a) { audio(); registrar(a); $('dcBuscar').value = ''; buscar(); }
	});
	$('dcInstalarBoton').addEventListener('click', function () {
		if (!st.instalar) { return; }
		st.instalar.prompt();
		st.instalar.userChoice.finally(function () { st.instalar = null; $('dcInstalar').hidden = true; });
	});
	window.addEventListener('online', function () { pintarRed(); pintarPendientes(); sincronizar(false); });
	window.addEventListener('offline', function () { pintarRed(); pintarPendientes(); });
	document.addEventListener('visibilitychange', function () {
		if (document.hidden) { pantallaEncendida(false); }
		else if (st.escaneando) { pantallaEncendida(true); }
	});
	window.addEventListener('pagehide', function () { cerrarCamara(); });
	Q.escuchar(function (m) { if (m && (m.tipo === 'sincronizado' || m.tipo === 'cambio')) { pintarPendientes(); pintarHoy(); } });

	/*==============  Arranque  ==============*/
	pintarRed();
	prepararInstalacion();
	registrarSW();
	Q.listo.then(function (persistente) {
		if (!persistente) {
			aviso('Este navegador no deja guardar datos (¿modo incógnito?). Úselo con Internet o en una ventana normal: sin eso, lo registrado se pierde al cerrar.', 'error');
		}
		return Promise.all([Q.leerMeta(K_LISTA), cargarHoy()]);
	}).then(function (r) {
		if (r[0]) { usarLista(r[0]); }
		pintarLista();
		pintarHoy();
		pintarPendientes();
		/* Con Internet y sin lista (o de otro día), se descarga sola. */
		if (navigator.onLine && (!st.lista || st.lista.fecha !== Q.hoy())) { descargarLista(); }
		sincronizar(false);
	}).catch(function () { pintarLista(); });
	setInterval(function () {
		pintarPendientes().then(function (n) { if (n && navigator.onLine) { sincronizar(false); } });
	}, 30000);
})();
