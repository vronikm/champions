/*
|--------------------------------------------------------------------------
| DigiCheck — «Guardar imagen» del carnet
|--------------------------------------------------------------------------
| Dibuja el carnet en un lienzo a 300 ppp (1012 × 638 px, CR80) con los
| datos del que está en pantalla y lo comparte (WhatsApp, Galería…) o, si
| el dispositivo no comparte archivos, lo descarga. Así el representante
| puede llevarlo en el celular y mostrarlo en la cancha.
|
| Todo es del mismo origen (logo, foto, el QR en SVG): el lienzo no queda
| «contaminado» y se puede exportar.
*/
(function () {
	'use strict';
	var boton = document.getElementById('dccImagen');
	var carnet = document.querySelector('.dcc-uno .dcc');
	if (!boton || !carnet) { return; }

	var W = 1012, H = 638, NARANJA = '#c2410c';

	function cargar(src) {
		return new Promise(function (ok) {
			if (!src) { ok(null); return; }
			var i = new Image();
			i.onload = function () { ok(i); };
			i.onerror = function () { ok(null); };
			i.src = src;
		});
	}
	function qrComoImagen() {
		var svg = carnet.querySelector('.dcc__qr svg');
		if (!svg) { return Promise.resolve(null); }
		var texto = new XMLSerializer().serializeToString(svg);
		if (!/xmlns=/.test(texto)) { texto = texto.replace('<svg', '<svg xmlns="http://www.w3.org/2000/svg"'); }
		return cargar('data:image/svg+xml;charset=utf-8,' + encodeURIComponent(texto));
	}
	function redondeado(c, x, y, w, h, r) {
		c.beginPath();
		c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r); c.arcTo(x + w, y + h, x, y + h, r);
		c.arcTo(x, y + h, x, y, r); c.arcTo(x, y, x + w, y, r); c.closePath();
	}
	function texto(c, t, x, y, maxW, fuente, color) {
		c.font = fuente; c.fillStyle = color;
		while (c.measureText(t).width > maxW && t.length > 3) { t = t.slice(0, -2) + '…'; }
		c.fillText(t, x, y);
	}
	/* Texto en varias líneas (hasta maxL); la última se recorta si no cabe.
	   Devuelve la «y» de la línea siguiente. */
	function parrafo(c, t, x, y, maxW, alto, maxL, fuente, color) {
		c.font = fuente; c.fillStyle = color;
		var palabras = String(t).split(/\s+/), lineas = [], actual = '';
		palabras.forEach(function (p) {
			var prueba = actual ? actual + ' ' + p : p;
			if (c.measureText(prueba).width > maxW && actual) { lineas.push(actual); actual = p; } else { actual = prueba; }
		});
		if (actual) { lineas.push(actual); }
		if (lineas.length > maxL) { lineas = lineas.slice(0, maxL - 1).concat([lineas.slice(maxL - 1).join(' ')]); }
		lineas.forEach(function (l, i) { texto(c, l, x, y + i * alto, maxW, fuente, color); });
		return y + lineas.length * alto;
	}
	var leer = function (sel) { var n = carnet.querySelector(sel); return n ? n.textContent.trim() : ''; };

	function dibujar() {
		var logoN = carnet.querySelector('.dcc__logo'), fotoN = carnet.querySelector('.dcc__foto img');
		return Promise.all([cargar(logoN && logoN.src), cargar(fotoN && fotoN.src), qrComoImagen()]).then(function (img) {
			var cv = document.createElement('canvas'); cv.width = W; cv.height = H;
			var c = cv.getContext('2d');
			c.save(); redondeado(c, 0, 0, W, H, 42); c.clip();
			c.fillStyle = '#fff'; c.fillRect(0, 0, W, H);
			/* Cabecera */
			var g = c.createLinearGradient(0, 0, W, 0);
			g.addColorStop(0, '#9a3412'); g.addColorStop(.55, NARANJA); g.addColorStop(1, '#ea580c');
			c.fillStyle = g; c.fillRect(0, 0, W, 121);
			var x = 40;
			if (img[0]) {
				c.fillStyle = '#fff'; redondeado(c, x, 16, 90, 90, 14); c.fill();
				var e = Math.min(80 / img[0].width, 80 / img[0].height);
				c.drawImage(img[0], x + 45 - img[0].width * e / 2, 61 - img[0].height * e / 2, img[0].width * e, img[0].height * e);
				x += 110;
			}
			var escuela = leer('.dcc__escuela').toUpperCase();
			c.font = '700 32px "Source Sans Pro", system-ui, sans-serif';
			var dosLineas = c.measureText(escuela).width > W - x - 230;
			parrafo(c, escuela, x, dosLineas ? 54 : 74, W - x - 230, 36, 2, '700 32px "Source Sans Pro", system-ui, sans-serif', '#fff');
			texto(c, 'DigiCheck', W - 200, 74, 170, '700 28px "Source Sans Pro", system-ui, sans-serif', '#fff');
			/* Foto o iniciales */
			var fx = 40, fy = 150, fw = 232, fh = 310;
			c.fillStyle = '#ffedd5'; redondeado(c, fx, fy, fw, fh, 24); c.fill();
			if (img[1]) {
				c.save(); redondeado(c, fx, fy, fw, fh, 24); c.clip();
				var s = Math.max(fw / img[1].width, fh / img[1].height);
				c.drawImage(img[1], fx + fw / 2 - img[1].width * s / 2, fy + fh / 2 - img[1].height * s / 2, img[1].width * s, img[1].height * s);
				c.restore();
			} else {
				c.font = '700 96px "Source Sans Pro", system-ui, sans-serif'; c.fillStyle = '#9a3412'; c.textAlign = 'center';
				c.fillText(leer('.dcc__foto span'), fx + fw / 2, fy + fh / 2 + 34); c.textAlign = 'left';
			}
			/* Datos */
			var dx = 300, dw = 345, y = 190;
			y = parrafo(c, leer('.dcc__nombres'), dx, y, dw, 44, 2, '700 40px "Source Sans Pro", system-ui, sans-serif', '#111827');
			y = parrafo(c, leer('.dcc__apellidos'), dx, y, dw, 44, 2, '700 40px "Source Sans Pro", system-ui, sans-serif', '#111827') + 18;
			[].slice.call(carnet.querySelectorAll('.dcc__datos > span')).forEach(function (n, i) {
				texto(c, n.textContent.trim(), dx, y + i * 40, dw, '400 30px "Source Sans Pro", system-ui, sans-serif', '#4b5563');
			});
			/* QR, sobre blanco */
			if (img[2]) { c.imageSmoothingEnabled = false; c.drawImage(img[2], W - 40 - 330, 140, 330, 330); }
			/* Pie */
			c.fillStyle = '#fff7ed'; c.fillRect(0, H - 64, W, 64);
			c.fillStyle = '#fed7aa'; c.fillRect(0, H - 64, W, 4);
			c.font = 'italic 400 28px "Source Sans Pro", system-ui, sans-serif'; c.fillStyle = '#9a3412'; c.textAlign = 'center';
			c.fillText('Porque cada entrenamiento cuenta', W / 2, H - 22);
			c.restore();
			return new Promise(function (ok) { cv.toBlob(ok, 'image/png'); });
		});
	}

	boton.addEventListener('click', function () {
		boton.disabled = true;
		var nombre = 'carnet-' + (leer('.dcc__nombres') + '-' + leer('.dcc__apellidos')).toLowerCase()
			.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.png';
		var descargar = function (blob) {
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob); a.download = nombre;
			document.body.appendChild(a); a.click(); a.remove();
			setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
		};
		dibujar().then(function (blob) {
			if (!blob) { throw new Error('lienzo'); }
			var archivo = new File([blob], nombre, { type: 'image/png' });
			/* Compartir en el teléfono o la tableta; en la computadora, descargar. */
			var tactil = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
			if (tactil && navigator.canShare && navigator.canShare({ files: [archivo] })) {
				/* Si compartir falla (no porque el usuario lo cancele), se descarga. */
				return navigator.share({ files: [archivo], title: 'Carnet DigiCheck' }).catch(function (e) {
					if (!e || e.name !== 'AbortError') { descargar(blob); }
				});
			}
			descargar(blob);
		}).catch(function () {
			alert('No se pudo generar la imagen en este navegador. Use «Imprimir» o una captura de pantalla.');
		}).then(function () { boton.disabled = false; });
	});
})();
