/*
|--------------------------------------------------------------------------
| Asistencia sin conexión — cola de marcas en el celular y su envío
|--------------------------------------------------------------------------
| La usan la toma de asistencia (asistenciaAlumno), la lista de horarios
| (asistencia) y el service worker (sw-asistencia.js, para Background Sync).
| Por eso no toca el DOM: sólo IndexedDB y fetch.
|
| CADA MARCA se guarda en IndexedDB ANTES de enviarse, con su clave
| usuario|horario|alumno|fecha. Así sobrevive a cerrar el navegador, a que
| el teléfono mate la pestaña o a pasar horas sin señal. Sin IndexedDB
| (navegación privada) se guarda en memoria y `persistente` es false: la
| página avisa antes de salir con marcas sin enviar.
|
| «anterior» es lo que el servidor tenía según el celular al marcar. Si el
| profesor cambia la marca varias veces sin señal, se conserva la PRIMERA
| «anterior»: es la que el servidor compara para decidir si alguien más
| registró otra cosa mientras tanto (conflicto → se conserva la del
| servidor; asistenciaController::registrarMarcasLote).
|
| ENVÍO: primero GET ?accion=sesion (¿hay sesión? ¿de quién? testigo CSRF
| fresco: el de una página guardada era de otra sesión) y luego POST por
| lotes SÓLO de las marcas de ese usuario. En un celular compartido, las
| de otro profesor esperan a que él entre.
|
| Respuestas por marca: ok → se borra; conflicto o rechazada → se borra y
| queda un AVISO para mostrarlo en la ficha del alumno; error → sigue
| pendiente y se reintenta.
*/
(function (raiz) {
	'use strict';

	var BD = 'ds-asistencia', VERSION = 1, LOTE = 100;
	var CANAL = 'ds-asistencia';
	var url = '';            /* asistenciaOfflineAjax.php, lo fija configurar() */
	var enCurso = null;      /* un solo envío a la vez en este contexto */

	/*----------  Almacén: IndexedDB o, sin él, memoria  ----------*/
	var conIDB = (function () {
		try { return typeof raiz.indexedDB !== 'undefined' && raiz.indexedDB !== null; } catch (e) { return false; }
	})();

	var conexion = null;
	function abrir() {
		if (conexion) { return conexion; }
		conexion = new Promise(function (ok, mal) {
			var p = raiz.indexedDB.open(BD, VERSION);
			p.onupgradeneeded = function () {
				var db = p.result;
				if (!db.objectStoreNames.contains('marcas')) { db.createObjectStore('marcas', { keyPath: 'clave' }); }
				if (!db.objectStoreNames.contains('avisos')) { db.createObjectStore('avisos', { keyPath: 'clave' }); }
				if (!db.objectStoreNames.contains('meta'))   { db.createObjectStore('meta', { keyPath: 'k' }); }
			};
			p.onsuccess = function () { ok(p.result); };
			p.onerror = function () { conexion = null; mal(p.error); };
		});
		return conexion;
	}
	function req(r) { return new Promise(function (ok, mal) { r.onsuccess = function () { ok(r.result); }; r.onerror = function () { mal(r.error); }; }); }

	var memoria = { marcas: {}, avisos: {}, meta: {} };
	var clavePor = { marcas: 'clave', avisos: 'clave', meta: 'k' };

	/* Cuatro operaciones bastan: leer uno, todos, guardar y borrar. */
	var almacen = {
		get: function (s, k) {
			if (!conIDB) { return Promise.resolve(memoria[s][k] ? JSON.parse(JSON.stringify(memoria[s][k])) : undefined); }
			return abrir().then(function (db) { return req(db.transaction(s).objectStore(s).get(k)); });
		},
		all: function (s) {
			if (!conIDB) { return Promise.resolve(Object.keys(memoria[s]).map(function (k) { return JSON.parse(JSON.stringify(memoria[s][k])); })); }
			return abrir().then(function (db) { return req(db.transaction(s).objectStore(s).getAll()); });
		},
		put: function (s, v) {
			if (!conIDB) { memoria[s][v[clavePor[s]]] = JSON.parse(JSON.stringify(v)); return Promise.resolve(); }
			return abrir().then(function (db) { return req(db.transaction(s, 'readwrite').objectStore(s).put(v)); });
		},
		del: function (s, k) {
			if (!conIDB) { delete memoria[s][k]; return Promise.resolve(); }
			return abrir().then(function (db) { return req(db.transaction(s, 'readwrite').objectStore(s).delete(k)); });
		}
	};

	/* Si IndexedDB existe pero falla al abrir (algunos modos privados), a memoria. */
	var listo = conIDB ? abrir().then(function () { return true; }, function () { conIDB = false; return false; }) : Promise.resolve(false);

	/*----------  Avisar a las páginas abiertas (también desde el service worker)  ----------*/
	var canal = null;
	try { canal = new BroadcastChannel(CANAL); } catch (e) {}
	function anunciar(datos) { try { if (canal) { canal.postMessage(datos); } } catch (e) {} }

	/** Fecha local del dispositivo como Y-m-d (no toISOString: ésa es UTC). */
	function hoy() {
		var d = new Date();
		return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
	}

	function clave(m) { return [m.usuario, m.horario, m.alumno, m.fecha].join('|'); }

	function filtrar(lista, f) {
		f = f || {};
		return lista.filter(function (m) {
			return (f.usuario == null || m.usuario === Number(f.usuario))
				&& (f.horario == null || m.horario === Number(f.horario))
				&& (f.fecha == null || m.fecha === f.fecha);
		});
	}

	/** Guarda (o corrige) una marca pendiente. Conserva la primera «anterior». */
	function guardar(m) {
		var reg = {
			usuario: Number(m.usuario) || 0, horario: Number(m.horario), alumno: Number(m.alumno),
			fecha: m.fecha, estado: m.estado, anterior: m.anterior || '', tomada_en: Date.now()
		};
		reg.clave = clave(reg);
		return listo.then(function () { return almacen.get('marcas', reg.clave); }).then(function (previa) {
			if (previa) { reg.anterior = previa.anterior; }
			/* Volvió a la marca que el servidor ya tiene: no hay nada que enviar. */
			var vuelve = reg.anterior !== '' && reg.anterior === reg.estado;
			return (vuelve ? almacen.del('marcas', reg.clave) : almacen.put('marcas', reg)).then(function () { reg.pendiente = !vuelve; });
		}).then(function () {
			return almacen.del('avisos', reg.clave);   /* al volver a marcar, el aviso viejo deja de aplicar */
		}).then(function () { anunciar({ tipo: 'cambio' }); return reg; });
	}

	function pendientes(f) { return listo.then(function () { return almacen.all('marcas'); }).then(function (l) { return filtrar(l, f); }); }
	function avisos(f)     { return listo.then(function () { return almacen.all('avisos'); }).then(function (l) { return filtrar(l, f); }); }
	function borrarAviso(c) { return almacen.del('avisos', c); }
	function leerMeta(k)    { return listo.then(function () { return almacen.get('meta', k); }).then(function (r) { return r ? r.v : null; }); }
	function ponerMeta(k, v){ return listo.then(function () { return almacen.put('meta', { k: k, v: v }); }); }

	/*----------  Envío  ----------*/
	function sesion() {
		return fetch(url + '?accion=sesion', { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
			.then(function (r) {
				if (r.status === 401 || r.status === 403) { return { sinSesion: true }; }
				return r.json().then(function (j) { return j && j.ok ? j : { sinSesion: true }; }, function () { return { sinSesion: true }; });
			});
	}

	function enviarLote(lista, csrf) {
		var datos = new FormData();
		datos.append('modulo_asistencia', 'asistencia_lote');
		datos.append('ds_csrf', csrf);
		datos.append('marcas', JSON.stringify(lista.map(function (m) {
			return { clave: m.clave, horario: m.horario, alumno: m.alumno, fecha: m.fecha, estado: m.estado, anterior: m.anterior };
		})));
		return fetch(url, { method: 'POST', body: datos, credentials: 'same-origin', cache: 'no-store' })
			.then(function (r) {
				if (r.status === 401) { return { sinSesion: true }; }
				return r.json().catch(function () { return { sinSesion: true }; });   /* HTML del login: la sesión se fue */
			});
	}

	/* Se borra sólo si, mientras se enviaba, el profesor no la volvió a cambiar. */
	function quitarSiIgual(m) {
		return almacen.get('marcas', m.clave).then(function (g) {
			if (!g) { return; }
			if (g.estado === m.estado) { return almacen.del('marcas', m.clave); }
			g.anterior = m.estado;   /* lo enviado ya es lo del servidor */
			return almacen.put('marcas', g);
		});
	}

	/**
	 * Envía lo pendiente del usuario con sesión. Resuelve con
	 * {estado: 'ok'|'nada'|'sin-red'|'sin-sesion'|'error', enviadas, conflictos, rechazadas, pendientes, usuario}.
	 */
	function sincronizar() {
		if (!url) { return Promise.resolve({ estado: 'nada', enviadas: 0, conflictos: 0, rechazadas: 0, pendientes: 0 }); }
		if (enCurso) { return enCurso; }
		var res = { estado: 'ok', enviadas: 0, conflictos: 0, rechazadas: 0, pendientes: 0, usuario: null };

		enCurso = pendientes().then(function (todas) {
			if (!todas.length) { res.estado = 'nada'; return res; }
			anunciar({ tipo: 'enviando' });
			return sesion().then(function (s) {
				if (s.sinSesion) { res.estado = 'sin-sesion'; res.pendientes = todas.length; return res; }
				res.usuario = Number(s.usuario);
				var mias = todas.filter(function (m) { return m.usuario === res.usuario; });
				res.pendientes = todas.length - mias.length;   /* las de otro usuario esperan */
				var trozos = [];
				for (var i = 0; i < mias.length; i += LOTE) { trozos.push(mias.slice(i, i + LOTE)); }

				return trozos.reduce(function (cadena, trozo) {
					return cadena.then(function () {
						if (res.estado !== 'ok') { res.pendientes += trozo.length; return; }
						return enviarLote(trozo, s.csrf).then(function (j) {
							if (j.sinSesion) { res.estado = 'sin-sesion'; res.pendientes += trozo.length; return; }
							if (!j.ok || !Array.isArray(j.resultados)) { res.estado = 'error'; res.pendientes += trozo.length; return; }
							var porClave = {};
							trozo.forEach(function (m) { porClave[m.clave] = m; });
							return j.resultados.reduce(function (c2, x) {
								return c2.then(function () {
									var m = porClave[x.clave];
									if (!m) { return; }
									if (x.resultado === 'ok') { res.enviadas++; return quitarSiIgual(m); }
									if (x.resultado === 'conflicto' || x.resultado === 'rechazada') {
										res[x.resultado === 'conflicto' ? 'conflictos' : 'rechazadas']++;
										return quitarSiIgual(m).then(function () {
											return almacen.put('avisos', { clave: m.clave, usuario: m.usuario, horario: m.horario, alumno: m.alumno,
												fecha: m.fecha, estado: m.estado, actual: x.actual || '', resultado: x.resultado, texto: x.texto || '' });
										});
									}
									res.pendientes++;   /* error del servidor al guardar: se reintenta */
								});
							}, Promise.resolve());
						});
					});
				}, Promise.resolve()).then(function () { return res; });
			});
		}).catch(function () {
			/* Sin red (fetch rechaza) o fallo del almacén. */
			res.estado = 'sin-red';
			return pendientes().then(function (t) { res.pendientes = t.length; return res; }, function () { return res; });
		}).then(function (r) {
			enCurso = null;
			if (r.enviadas || r.conflictos || r.rechazadas) { ponerMeta('ultimo_envio', Date.now()).catch(function () {}); }
			anunciar({ tipo: 'sincronizado', resultado: r });
			return r;
		});
		return enCurso;
	}

	/** Pide al navegador un envío en segundo plano (Chrome/Android), si lo permite. */
	function pedirEnvioEnSegundoPlano() {
		if (!raiz.navigator || !('serviceWorker' in raiz.navigator)) { return Promise.resolve(false); }
		return raiz.navigator.serviceWorker.ready.then(function (reg) {
			if (!reg.sync) { return false; }
			return reg.sync.register('ds-asistencia').then(function () { return true; });
		}).catch(function () { return false; });
	}

	raiz.DSAsistencia = {
		listo: listo,
		get persistente() { return conIDB; },
		configurar: function (o) { url = (o && o.url) || url; },
		hoy: hoy,
		guardar: guardar,
		pendientes: pendientes,
		avisos: avisos,
		borrarAviso: borrarAviso,
		leerMeta: leerMeta,
		ponerMeta: ponerMeta,
		sincronizar: sincronizar,
		pedirEnvioEnSegundoPlano: pedirEnvioEnSegundoPlano,
		escuchar: function (fn) { try { var c = new BroadcastChannel(CANAL); c.onmessage = function (e) { fn(e.data); }; } catch (e) {} }
	};
})(typeof self !== 'undefined' ? self : window);
