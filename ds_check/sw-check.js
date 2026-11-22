/*
|--------------------------------------------------------------------------
| DigiCheck — service worker (la pantalla de escaneo sin conexión)
|--------------------------------------------------------------------------
| Alcance: ds_check/. Lo registra assets/js/digicheck.js.
|
| PÁGINA (escanear/): primero la red, con tiempo límite para la señal
| débil de la cancha; si no responde, la copia guardada. Sólo se guardan
| respuestas 200 en HTML que no sean el login. Si la sesión caducó, se
| sirve la copia: se sigue escaneando y la página pide entrar para enviar.
|
| RECURSOS del mismo origen (css, js, fuentes, imágenes): la copia al
| instante y se refresca por detrás. Incluye los de ds_core y ds_futbol que
| la pantalla usa (la cola asistencia-offline.js, jsQR, AdminLTE…): el
| worker atiende las peticiones de SUS páginas aunque vayan a otra carpeta.
|
| Nunca: ajax/ (la lista y el envío van siempre a la red), POST, PDF.
|
| ENVÍO EN SEGUNDO PLANO: «sync» con la etiqueta de la cola de asistencia
| (Chrome/Android) envía lo pendiente aunque la app esté cerrada.
|
| Al cambiar este archivo, subir VERSION: así se descartan las cachés viejas.
*/
'use strict';

var VERSION  = 'v2';
var PAGINAS  = 'ds-check-paginas-' + VERSION;
var RECURSOS = 'ds-check-recursos-' + VERSION;
var ESPERA_RED = 6000;
var BASE = new URL('./', self.location).href;                 /* …/ds_check/ */
var HUB  = new URL('../', self.location).href;                /* …/ (raíz del ecosistema) */

importScripts(HUB + 'ds_futbol/app/views/dist/js/asistencia-offline.js');
self.DSAsistencia.configurar({ url: HUB + 'ds_futbol/app/ajax/asistenciaOfflineAjax.php' });

/** La clave de caché de una página de DigiCheck que se guarda ('' si no es una). */
function clavePagina(u) {
	if (u.href.indexOf(BASE) !== 0) { return ''; }
	var ruta = u.href.slice(BASE.length).split(/[?#]/)[0];
	return /^(escanear\/?)?$/.test(ruta) ? BASE + 'escanear/' : '';
}

function esRecurso(u) {
	return u.origin === self.location.origin
		&& /\.(css|js|json|woff2?|ttf|otf|eot|png|jpe?g|gif|svg|webp|ico)$/i.test(u.pathname)
		&& u.pathname.indexOf('/ajax/') === -1
		&& !/sw-[a-z]+\.js$/.test(u.pathname);
}

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

/* Guarda los recursos y, de cada hoja de estilos, las fuentes e imágenes
   que pide con url(...): sin ellas, sin conexión los iconos (Font Awesome)
   y la tipografía no aparecen. */
function guardarRecursos(urls, vistos) {
	vistos = vistos || {};
	return caches.open(RECURSOS).then(function (c) {
		return Promise.all(urls.filter(function (u) { if (vistos[u]) { return false; } vistos[u] = true; return true; }).map(function (u) {
			return fetch(u, { credentials: 'same-origin' }).then(function (r) {
				if (!r.ok) { return; }
				var css = /\.css(\?|$)/i.test(u) || (r.headers.get('content-type') || '').indexOf('text/css') !== -1;
				var guardar = c.put(u, r.clone());
				if (!css) { return guardar; }
				return Promise.all([guardar, r.text().then(function (t) {
					var dentro = [], re = /url\(\s*['"]?([^'")]+)['"]?\s*\)/gi, m;
					while ((m = re.exec(t))) {
						if (/^data:/i.test(m[1])) { continue; }
						try { var x = new URL(m[1], u); if (esRecurso(x)) { dentro.push(x.href.split('#')[0]); } } catch (e) {}
					}
					return guardarRecursos(dentro, vistos);
				})]);
			}).catch(function () {});
		}));
	});
}

/** Guarda la pantalla y sus recursos (lo pide la página al cargar con conexión). */
function precargar(paginas, recursos) {
	return caches.open(PAGINAS).then(function (c) {
		return Promise.all((paginas || []).map(function (p) {
			var k = clavePagina(new URL(p, BASE));
			if (!k) { return null; }
			return fetch(k, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
				if (paginaValida(r)) { return c.put(k, r); }
			}).catch(function () {});
		}));
	}).then(function () {
		return guardarRecursos((recursos || []).filter(function (u) {
			try { return esRecurso(new URL(u)); } catch (e) { return false; }
		}));
	});
}

self.addEventListener('install', function () { self.skipWaiting(); });

self.addEventListener('activate', function (e) {
	e.waitUntil(caches.keys().then(function (claves) {
		return Promise.all(claves.filter(function (k) {
			return /^ds-check-/.test(k) && k !== PAGINAS && k !== RECURSOS;
		}).map(function (k) { return caches.delete(k); }));
	}).then(function () { return self.clients.claim(); }));
});

self.addEventListener('message', function (e) {
	var d = e.data || {};
	if (d.tipo === 'precargar') { e.waitUntil(precargar(d.paginas, d.recursos)); }
});

self.addEventListener('fetch', function (e) {
	var req = e.request;
	if (req.method !== 'GET') { return; }
	var u = new URL(req.url);

	var clave = clavePagina(u);
	if (clave && req.mode === 'navigate') {
		e.respondWith(
			conTiempo(fetch(req), ESPERA_RED).then(function (r) {
				if (paginaValida(r)) {
					var copia = r.clone();
					caches.open(PAGINAS).then(function (c) { return c.put(clave, copia); });
					return r;
				}
				return caches.match(clave, { cacheName: PAGINAS }).then(function (g) { return g || r; });
			}).catch(function () {
				return caches.match(clave, { cacheName: PAGINAS }).then(function (g) {
					return g || new Response(
						'<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
						+ '<title>DigiCheck sin conexión</title><body style="font-family:system-ui,sans-serif;padding:2rem;max-width:32rem;margin:auto;line-height:1.5">'
						+ '<h1 style="font-size:1.3rem">Sin conexión</h1><p>DigiCheck todavía no se guardó en este dispositivo. Ábralo una vez con Internet '
						+ 'y descargue la lista: después funciona sin señal.</p></body></html>',
						{ status: 503, headers: { 'Content-Type': 'text/html; charset=utf-8' } });
				});
			})
		);
		return;
	}

	if (esRecurso(u)) {
		e.respondWith(caches.open(RECURSOS).then(function (c) {
			return c.match(req, { ignoreSearch: false }).then(function (g) {
				var red = fetch(req).then(function (r) { if (r.ok) { c.put(req, r.clone()); } return r; });
				if (g) { red.catch(function () {}); return g; }
				return red.catch(function () {
					/* Sin red y sin esa versión exacta (?v=): la misma sin consulta. */
					return c.match(req, { ignoreSearch: true }).then(function (g2) { return g2 || Response.error(); });
				});
			});
		}));
	}
});

/* Envío en segundo plano (Chrome/Android). */
self.addEventListener('sync', function (e) {
	if (e.tag === 'ds-asistencia') {
		e.waitUntil(self.DSAsistencia.sincronizar().then(function (r) {
			if (r.estado === 'sin-red' || r.estado === 'error') { throw new Error('reintentar'); }
		}));
	}
});
