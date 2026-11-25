/*
| Excel (.xlsx) de verdad, armado en el navegador (2026-11-24).
|
| POR QUÉ NO EL BOTÓN «EXCEL» DE DATATABLES
|
| Ese botón copia lo que se ve en la tabla, y las tablas de reportes juntan
| varios datos en una celda (la forma de pago bajo el rubro, la fecha de
| registro bajo la de pago) para caber en el teléfono. En Excel salían
| pegados y los importes como texto («$30.00»), que no se pueden sumar.
|
| Aquí la vista entrega las filas ya separadas y este archivo escribe el
| .xlsx: números como números con formato de dólares, fechas como fechas
| (se ordenan y filtran), títulos, fila de encabezado fija con filtros y una
| fila de totales. No hace falta ninguna librería en el servidor (que
| puede no tener PhpSpreadsheet ni zip): sólo JSZip, que ya está en el proyecto y
| se descarga la primera vez que se exporta.
|
| USO
|
|   dsExcel.descargar('Pagos 2026-11.xlsx', {
|       hoja: 'Pagos',
|       titulo: ['Pagos realizados', 'Todas las sedes · 01/11/2026 – 30/11/2026'],
|       columnas: [{ t: 'Alumno', ancho: 32 }, { t: 'Fecha', tipo: 'fecha' }, { t: 'Pagado', tipo: 'dinero', total: true }],
|       filas: [['Ana Pérez', '2026-11-05', 30], ...]
|   });
|
|   tipo: 'texto' (por omisión), 'numero', 'dinero', 'fecha' (aaaa-mm-dd).
|   total: true suma la columna en la última fila.
*/
(function () {
    'use strict';

    var RAIZ = (function () {
        var s = document.currentScript;
        if (s && s.src) { return s.src.replace(/ds_core\/assets\/js\/excel\.js.*$/, ''); }
        return location.pathname.replace(/ds_[a-z]+\/.*$/, '');
    })();
    var JSZIP = RAIZ + 'ds_futbol/app/views/dist/plugins/jszip/jszip.min.js';
    var cargando = null;

    function cargarJSZip() {
        if (window.JSZip) { return Promise.resolve(); }
        if (cargando) { return cargando; }
        cargando = new Promise(function (ok, mal) {
            var s = document.createElement('script');
            s.src = JSZIP; s.onload = ok;
            s.onerror = function () { cargando = null; mal(new Error('No se pudo cargar el generador de Excel.')); };
            document.head.appendChild(s);
        });
        return cargando;
    }

    function xml(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
        }).replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '');
    }
    /* A1, B1… AA1 */
    function letra(n) { var s = ''; n++; while (n > 0) { var m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; }
    /* Fecha aaaa-mm-dd → número de serie de Excel. */
    function serie(f) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(f || ''));
        if (!m) { return null; }
        return (Date.UTC(+m[1], +m[2] - 1, +m[3]) - Date.UTC(1899, 11, 30)) / 86400000;
    }

    /* Estilos: 0 normal, 1 negrita, 2 dinero, 3 fecha, 4 encabezado, 5 título,
       6 dinero en negrita (totales), 7 subtítulo gris, 8 número. */
    var ESTILOS =
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
        '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
        '<numFmts count="2"><numFmt numFmtId="164" formatCode="&quot;$&quot;#,##0.00"/><numFmt numFmtId="165" formatCode="dd/mm/yyyy"/></numFmts>' +
        '<fonts count="5"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font>' +
        '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font>' +
        '<font><sz val="10"/><color rgb="FF6C757D"/><name val="Calibri"/></font></fonts>' +
        '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>' +
        '<fill><patternFill patternType="solid"><fgColor rgb="FF0E7490"/><bgColor indexed="64"/></patternFill></fill>' +
        '<fill><patternFill patternType="solid"><fgColor rgb="FFE9ECEF"/><bgColor indexed="64"/></patternFill></fill></fills>' +
        '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' +
        '<border><left/><right/><top style="thin"><color rgb="FF6C757D"/></top><bottom/><diagonal/></border></borders>' +
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' +
        '<cellXfs count="9">' +
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' +
        '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>' +
        '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' +
        '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>' +
        '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' +
        '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>' +
        '<xf numFmtId="164" fontId="1" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1"/>' +
        '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>' +
        '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' +
        '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

    function celdaTexto(ref, v, s) { return '<c r="' + ref + '" t="inlineStr"' + (s ? ' s="' + s + '"' : '') + '><is><t xml:space="preserve">' + xml(v) + '</t></is></c>'; }
    function celdaNumero(ref, v, s) { return '<c r="' + ref + '"' + (s ? ' s="' + s + '"' : '') + '><v>' + v + '</v></c>'; }

    function hojaXml(def) {
        var cols = def.columnas, filas = def.filas, titulo = def.titulo || [];
        var r = 0, out = [];
        titulo.forEach(function (t, i) { r++; out.push('<row r="' + r + '">' + celdaTexto('A' + r, t, i === 0 ? 5 : 7) + '</row>'); });
        if (titulo.length) { r++; }                                   /* una fila en blanco */
        var filaEnc = ++r;
        out.push('<row r="' + r + '">' + cols.map(function (c, i) { return celdaTexto(letra(i) + r, c.t, 4); }).join('') + '</row>');
        var desde = r + 1;
        filas.forEach(function (f) {
            r++;
            out.push('<row r="' + r + '">' + cols.map(function (c, i) {
                var v = f[i], ref = letra(i) + r;
                if (v === null || v === undefined || v === '') { return ''; }
                if (c.tipo === 'dinero' || c.tipo === 'numero') {
                    var n = Number(v); return isFinite(n) ? celdaNumero(ref, Math.round(n * 100) / 100, c.tipo === 'dinero' ? 2 : 8) : celdaTexto(ref, v);
                }
                if (c.tipo === 'fecha') { var sr = serie(v); return sr !== null ? celdaNumero(ref, sr, 3) : celdaTexto(ref, v); }
                return celdaTexto(ref, v);
            }).join('') + '</row>');
        });
        var hasta = r;
        if (cols.some(function (c) { return c.total; }) && filas.length) {
            r++;
            out.push('<row r="' + r + '">' + cols.map(function (c, i) {
                var ref = letra(i) + r;
                if (i === 0) { return celdaTexto(ref, def.etiquetaTotal || 'TOTAL', 1); }
                if (!c.total) { return '<c r="' + ref + '" s="1"/>'; }
                var suma = filas.reduce(function (a, f) { return a + (Number(f[i]) || 0); }, 0);
                var col = letra(i);
                return '<c r="' + ref + '" s="6"><f>SUM(' + col + desde + ':' + col + hasta + ')</f><v>' + Math.round(suma * 100) / 100 + '</v></c>';
            }).join('') + '</row>');
        }
        var anchos = '<cols>' + cols.map(function (c, i) {
            var w = c.ancho || (c.tipo === 'dinero' ? 13 : c.tipo === 'fecha' ? 12 : Math.min(45, Math.max(10, String(c.t).length + 4)));
            return '<col min="' + (i + 1) + '" max="' + (i + 1) + '" width="' + w + '" customWidth="1"/>';
        }).join('') + '</cols>';
        var ultima = letra(cols.length - 1);
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
            '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' + filaEnc + '" topLeftCell="A' + (filaEnc + 1) + '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' +
            anchos + '<sheetData>' + out.join('') + '</sheetData>' +
            (filas.length ? '<autoFilter ref="A' + filaEnc + ':' + ultima + hasta + '"/>' : '') +
            '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>' +
            '<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    function libro(def) {
        var hoja = xml((def.hoja || 'Hoja1').replace(/[\\\/\?\*\[\]:]/g, ' ').slice(0, 31));
        var z = new window.JSZip();
        z.file('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' +
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' +
            '<Default Extension="xml" ContentType="application/xml"/>' +
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' +
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' +
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        z.file('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        z.file('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
            '<sheets><sheet name="' + hoja + '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        z.file('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' +
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' +
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        z.file('xl/styles.xml', ESTILOS);
        z.file('xl/worksheets/sheet1.xml', hojaXml(def));
        return z.generateAsync({ type: 'blob', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', compression: 'DEFLATE' });
    }

    function bajar(nombre, blob) {
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = nombre;
        document.body.appendChild(a); a.click();
        setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1500);
    }

    window.dsExcel = {
        /* Devuelve una promesa con el Blob (útil para probar). */
        generar: function (def) { return cargarJSZip().then(function () { return libro(def); }); },
        descargar: function (nombre, def) {
            return this.generar(def).then(function (blob) { bajar(nombre, blob); return blob; }).catch(function (e) {
                if (window.Swal) { Swal.fire({ icon: 'error', title: 'No se pudo exportar', text: e.message, confirmButtonText: 'Aceptar' }); }
                else { alert(e.message); }
                throw e;
            });
        }
    };
})();
