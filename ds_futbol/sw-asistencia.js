/*
|--------------------------------------------------------------------------
| Service worker de la toma de asistencia (PWA sin conexión)
|--------------------------------------------------------------------------
| Se registra desde app/views/inc/pwa-asistencia.php con alcance
| «…/ds_futbol/asistencia»: por prefijo cubre asistencia/ y
| asistenciaAlumno/N/, y no se mete en el resto del sistema.
|
| PÁGINAS (asistencia/ y asistenciaAlumno/N/): primero la red, con un
| tiempo límite para la señal débil; si no responde, la copia guardada.
| Sólo se guardan respuestas 200 en HTML (nunca el login). Si la sesión
| caducó y el servidor redirige al login, se sirve la copia guardada: el
| profesor sigue marcando, las marcas quedan en el celular y la página le
| pide entrar para enviarlas.
|
| RECURSOS estáticos del mismo origen (css, js, fuentes, imágenes):
| la copia guardada al instante y se refresca por detrás.
|
| Nunca: app/ajax/, peticiones que no sean GET, PDF ni otras pantallas.
|
| ENVÍO EN SEGUNDO PLANO: el evento «sync» (Chrome/Android) envía la cola
| aunque la app esté cerrada. En iPhone no existe: se envía al abrirla.
|
| Al cambiar este archivo, subir VERSION: así se descartan las cachés viejas.
*/
'use strict';

var VERSION  = 'v1';
var PAGINAS  = 'ds-asis-paginas-' + VERSION;
var RECURSOS = 'ds-asis-recursos-' + VERSION;
var ESPERA_RED = 6000;   /* ms antes de usar la copia guardada con señal débil */

var BASE = new URL('./', self.location).href;               /* …/ds_futbol/ */

importScripts(BASE + 'app/views/dist/js/asistencia-offline.js');
self.DSAsistencia.configurar({ url: BASE + 'app/ajax/asistenciaOfflineAjax.php' });

/** ¿Es una página de la toma de asistencia? Devuelve su clave de caché (sin consulta) o ''. */
function clavePagina(u) {
	if (u.href.indexOf(BASE) !== 0) { return ''; }
	var ruta = u.href.slice(BASE.length).split(/[?#]/)[0];
	if (/^asistencia\/?$/.test(ruta)) { return BASE + 'asistencia/'; }
	var m = ruta.match(/^asistenciaAlumno\/(\d+)\/?$/);
	return m ? BASE + 'asistenciaAlumno/' + m[1] + '/' : '';
}

function esRecurso(u) {
	return u.origin === self.location.origin
		&& /\.(css|js|woff2?|ttf|otf|eot|png|jpe?g|gif|svg|webp|ico)$/i.test(u.pathname)
		&& u.pathname.indexOf('/app/ajax/') === -1
		&& !/sw-asistencia\.js$/.test(u.pathname);
}

/** Una respuesta que vale la pena guardar: 200, HTML y no el login. */
function paginaValida(r) {
	return r && r.ok && r.type === 'basic' && !r.redirected
		&& (r.headers.get('content-type') || '').indexOf('text/html') !== -1
		&& !/\/login\/?(\?|$)/.test(r.url);
}

function conTiempo(promesa, ms) {
	return new Promise(function (ok, mal) {
		var t = setTimeout(function () { mal(new Error('tiempo')); }, ms);
		promesa.then(function (r) { clearTimeout(t); ok(r); }, function (e) { clearTimeout(t); mal(e); });
	});
}

/* Los css, js e imágenes que una página pide, para guardarlos con ella. */
function recursosDe(html, pagina) {
	var urls = [], re = /(?:href|src)\s*=\s*"([^"]+)"/gi, m;
	while ((m = re.exec(html))) {
		try {
			var u = new URL(m[1].replace(/&amp;/g, '&'), pagina);
			if (esRecurso(u)) { urls.push(u.href); }
		} catch (e) {}
	}
	return urls;
}

function guardarRecursos(urls) {
	return caches.open(RECURSOS).then(function (c) {
		return Promise.all(urls.map(function (u) {
			return c.match(u).then(function (ya) {
				if (ya) { return; }
				return fetch(u, { credentials: 'same-origin' }).then(function (r) { if (r.ok) { return c.put(u, r); } }).catch(function () {});
			});
		}));
	});
}

/** Descarga y guarda páginas (la lista de horarios y cada toma) con sus recursos. */
function precargar(paginas, recursos) {
	var guardadas = 0, PARALELO = 4;   /* de a pocas: cada página es PHP con sesión en el hosting */
	return caches.open(PAGINAS).then(function (c) {
		var claves = [];
		(paginas || []).forEach(function (p) {
			var k = clavePagina(new URL(p, BASE));
			if (k && claves.indexOf(k) === -1) { claves.push(k); }
		});
		var una = function (clave) {
			return fetch(clave, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
				if (!paginaValida(r)) { return null; }
				return r.clone().text().then(function (html) {
					guardadas++;
					return Promise.all([c.put(clave, r), guardarRecursos(recursosDe(html, clave))]);
				});
			}).catch(function () { return null; });
		};
		var grupos = [];
		for (var i = 0; i < claves.length; i += PARALELO) { grupos.push(claves.slice(i, i + PARALELO)); }
		return grupos.reduce(function (cadena, g) {
			return cadena.then(function () { return Promise.all(g.map(una)); });
		}, Promise.resolve());
	}).then(function () {
		return guardarRecursos((recursos || []).filter(function (u) { try { return esRecurso(new URL(u)); } catch (e) { return false; } }));
	}).then(function () { return guardadas; });
}

self.addEventListener('install', function () { self.skipWaiting(); });

self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (claves) {
		return Promise.all(claves.filter(function (k) {
			return /^ds-asis-/.test(k) && k !== PAGINAS && k !== RECURSOS;
		}).map(function (k) { return caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});

self.addEventListener('fetch', function (e) {
	var req = e.request;
	if (req.method !== 'GET') { return; }
	var u = new URL(req.url);

	var clave = clavePagina(u);
	if (clave) {
		/* Otra fecha (administración, ?fecha=): siempre de la red, sin guardar. */
		if (u.searchParams.has('fecha')) { return; }
		e.respondWith(
			conTiempo(fetch(req), ESPERA_RED).then(function (r) {
				if (paginaValida(r)) {
					var copia = r.clone();
					caches.open(PAGINAS).then(function (c) { return c.put(clave, copia); });
					return r;
				}
				/* Sesión vencida (redirección al login) u otro error: la copia, si la hay. */
				return caches.match(clave, { cacheName: PAGINAS }).then(function (g) { return g || r; });
			}).catch(function () {
				return caches.match(clave, { cacheName: PAGINAS }).then(function (g) {
					return g || new Response(
						'<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' +
						'<title>Sin conexión</title><body style="font-family:system-ui,sans-serif;padding:2rem;max-width:32rem;margin:auto;line-height:1.5">' +
						'<h1 style="font-size:1.3rem">Sin conexión</h1><p>Esta lista no se descargó en el celular. Ábrala una vez con Internet desde ' +
						'<a href="' + BASE + 'asistencia/">Registrar asistencia</a> para poder usarla sin señal.</p></body></html>',
						{ status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
				});
			})
		);
		return;
	}

	if (esRecurso(u)) {
		e.respondWith(caches.open(RECURSOS).then(function (c) {
			return c.match(req).then(function (g) {
				var red = fetch(req).then(function (r) { if (r.ok) { c.put(req, r.clone()); } return r; });
				if (g) { red.catch(function () {}); return g; }
				return red;
			});
		}));
	}
	/* Todo lo demás pasa directo a la red. */
});

self.addEventListener('message', function (e) {
	var d = e.data || {};
	var responder = function (r) { if (e.ports && e.ports[0]) { e.ports[0].postMessage(r); } };
	if (d.tipo === 'precargar') {
		e.waitUntil(precargar(d.paginas, d.recursos).then(function (n) { responder({ ok: true, guardadas: n }); }, function () { responder({ ok: false }); }));
	} else if (d.tipo === 'limpiar') {
		e.waitUntil(caches.delete(PAGINAS).then(function () { responder({ ok: true }); }));
	} else if (d.tipo === 'sincronizar') {
		e.waitUntil(self.DSAsistencia.sincronizar().then(responder));
	}
});

/* Envío en segundo plano: si no hay red, se rechaza para que el navegador reintente. */
self.addEventListener('sync', function (e) {
	if (e.tag !== 'ds-asistencia') { return; }
	e.waitUntil(self.DSAsistencia.sincronizar().then(function (r) {
		if (r.estado === 'sin-red') { throw new Error('sin red'); }
	}));
});
