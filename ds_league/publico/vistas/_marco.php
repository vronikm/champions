<?php
/*
| Marco común del portal público.
|
| MÓVIL PRIMERO, Y NO COMO ADORNO
|
| Esto se abre de pie en la cancha, con una mano, para ver un resultado o
| a qué hora juega el equipo del hijo. El diseño parte de esa pantalla y
| crece hacia el escritorio, no al revés.
|
| No carga AdminLTE ni jQuery: el panel de administración pesa más de
| medio megabyte en CSS y JS que aquí no hacen ninguna falta, y quien
| consulta un resultado desde datos móviles lo nota. Todo el estilo va en
| línea, en una hoja pequeña.
|
| Espera $titulo, $descripcion y opcionalmente $volver.
*/

$h = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

$titulo      = $titulo      ?? 'Resultados';
$descripcion = $descripcion ?? 'Resultados, posiciones y calendario.';

$base = APP_URL . 'publico/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo $h($titulo); ?> · <?php echo $h(DS_HUB_NAME); ?> League</title>

<?php /* Para que al compartir el enlace en mensajería salga una tarjeta
         legible en vez de la URL desnuda: es la mitad de para qué existe
         un portal público. */ ?>
<meta name="description" content="<?php echo $h($descripcion); ?>">
<meta property="og:title" content="<?php echo $h($titulo); ?>">
<meta property="og:description" content="<?php echo $h($descripcion); ?>">
<meta property="og:type" content="website">
<meta name="theme-color" content="#a78bfa">

<style>
/* ==========================================================================
   Paleta: el violeta de League, el mismo que tiene asignado en
   digisports.css. Aquí se escribe explícito porque el portal no carga la
   hoja del ecosistema —no la necesita— y un token sin definir daría
   texto invisible.
   ========================================================================== */
:root{
  --tinta:#171923; --tinta-2:#4a4f63; --suave:#767c92;
  --fondo:#f7f7fa; --papel:#fff; --linea:#e6e7ee;
  --acento:#6d28d9; --acento-claro:#f2ecfd;
  --ok:#15803d; --aviso:#b45309; --mal:#b91c1c;
}
@media (prefers-color-scheme:dark){
  :root{
    --tinta:#eceaf6; --tinta-2:#b6b9c9; --suave:#8b90a6;
    --fondo:#0b1020; --papel:#151b30; --linea:#262d47;
    --acento:#a78bfa; --acento-claro:#1e1b3a;
    --ok:#4ade80; --aviso:#fbbf24; --mal:#f87171;
  }
}

*{box-sizing:border-box;}
body{
  margin:0; background:var(--fondo); color:var(--tinta);
  font:16px/1.55 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
  -webkit-text-size-adjust:100%;
}
a{color:var(--acento);text-decoration:none;}
a:hover{text-decoration:underline;}

/* ----------  Cabecera fija  ----------
   Se queda arriba porque en una lista larga de partidos el enlace de
   volver es lo que más se busca. */
.cab{
  position:sticky;top:0;z-index:10;
  background:var(--acento);color:#fff;
  padding:.85rem 1rem; padding-top:max(.85rem, env(safe-area-inset-top));
  display:flex;align-items:center;gap:.75rem;
  box-shadow:0 1px 8px rgba(0,0,0,.12);
}
.cab a{color:#fff;}
.cab__volver{
  font-size:1.35rem;line-height:1;flex:0 0 auto;
  width:2rem;height:2rem;display:flex;align-items:center;justify-content:center;
  border-radius:50%;
}
.cab__volver:hover{background:rgba(255,255,255,.15);text-decoration:none;}
.cab__t{font-size:1.05rem;font-weight:600;margin:0;min-width:0;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.cab__s{font-size:.78rem;opacity:.85;display:block;font-weight:400;}

.env{max-width:52rem;margin:0 auto;padding:1rem;
     padding-bottom:max(2rem, env(safe-area-inset-bottom));}

/* ----------  Pestañas  ---------- */
.tabs{display:flex;gap:.4rem;overflow-x:auto;padding:.75rem 1rem 0;
      background:var(--acento);scrollbar-width:none;}
.tabs::-webkit-scrollbar{display:none;}
.tab{
  flex:0 0 auto;padding:.5rem .9rem;border-radius:.5rem .5rem 0 0;
  background:rgba(255,255,255,.16);color:#fff;font-size:.88rem;white-space:nowrap;
}
.tab--on{background:var(--fondo);color:var(--acento);font-weight:600;}
.tab:hover{text-decoration:none;}

/* ----------  Tarjetas  ---------- */
.caja{background:var(--papel);border:1px solid var(--linea);
      border-radius:.75rem;overflow:hidden;margin-bottom:1rem;}
.caja__t{font-size:.72rem;letter-spacing:.09em;text-transform:uppercase;
         font-weight:700;color:var(--suave);
         padding:.75rem 1rem .5rem;margin:0;}

/* ----------  Partido  ----------
   Los nombres se truncan y el marcador nunca: en 320 px, lo que la gente
   busca es el número. */
.pt{display:flex;align-items:center;gap:.6rem;
    padding:.7rem 1rem;border-top:1px solid var(--linea);}
.caja .pt:first-of-type{border-top:0;}
.pt__eq{flex:1 1 0;min-width:0;display:flex;align-items:center;gap:.45rem;}
.pt__eq--v{justify-content:flex-end;text-align:right;}
.pt__n{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:.94rem;}
.pt__n--gana{font-weight:700;}
.pt__esc{width:26px;height:26px;object-fit:contain;flex:0 0 auto;border-radius:.25rem;}
.pt__m{flex:0 0 auto;font-weight:700;font-size:1.05rem;
       font-variant-numeric:tabular-nums;min-width:4rem;text-align:center;}
.pt__h{flex:0 0 auto;font-size:.8rem;color:var(--suave);min-width:4rem;text-align:center;
       font-variant-numeric:tabular-nums;}
.pt__pie{padding:0 1rem .6rem;font-size:.76rem;color:var(--suave);
         display:flex;gap:.5rem;flex-wrap:wrap;}

/* ----------  Tabla de posiciones  ----------
   En móvil se ocultan las columnas accesorias: PJ, DIF y PTS es lo que
   se mira; PF y PC sólo cuando hay sitio. */
table{width:100%;border-collapse:collapse;font-variant-numeric:tabular-nums;}
th,td{padding:.5rem .55rem;text-align:right;border-top:1px solid var(--linea);
      font-size:.88rem;}
th{font-size:.68rem;letter-spacing:.05em;text-transform:uppercase;
   color:var(--suave);font-weight:700;border-top:0;}
th:nth-child(2),td:nth-child(2){text-align:left;width:99%;}
td:nth-child(2){white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:0;}
.pos{color:var(--suave);width:1.6rem;text-align:center;}
.pts{font-weight:700;}
.opc{display:none;}
@media (min-width:30rem){ .opc{display:table-cell;} }

/* ----------  Listas  ---------- */
.fila{display:flex;align-items:center;gap:.7rem;padding:.75rem 1rem;
      border-top:1px solid var(--linea);}
.caja .fila:first-of-type{border-top:0;}
.fila__n{flex:1 1 auto;min-width:0;}
.fila__n b{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.fila__n small{color:var(--suave);font-size:.8rem;}
.dorsal{flex:0 0 auto;width:2rem;height:2rem;border-radius:50%;
        background:var(--acento-claro);color:var(--acento);
        display:flex;align-items:center;justify-content:center;
        font-weight:700;font-size:.85rem;}
.ini{flex:0 0 auto;width:2.1rem;height:2.1rem;border-radius:50%;
     background:var(--linea);color:var(--tinta-2);
     display:flex;align-items:center;justify-content:center;
     font-weight:600;font-size:.78rem;}
.foto{width:2.1rem;height:2.1rem;border-radius:50%;object-fit:cover;flex:0 0 auto;}

/* ----------  Etiquetas de estado  ---------- */
.et{font-size:.68rem;padding:.15rem .45rem;border-radius:.3rem;font-weight:600;
    text-transform:uppercase;letter-spacing:.03em;}
.et--ok{background:color-mix(in srgb,var(--ok) 15%,transparent);color:var(--ok);}
.et--av{background:color-mix(in srgb,var(--aviso) 15%,transparent);color:var(--aviso);}
.et--ma{background:color-mix(in srgb,var(--mal) 15%,transparent);color:var(--mal);}
.et--ne{background:var(--linea);color:var(--tinta-2);}

.vacio{padding:2.5rem 1rem;text-align:center;color:var(--suave);font-size:.92rem;}
.pie{text-align:center;color:var(--suave);font-size:.78rem;padding:1.5rem 1rem;}
.pie a{color:var(--suave);}
:focus-visible{outline:2px solid var(--acento);outline-offset:2px;border-radius:.25rem;}
@media (prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important;}}
</style>
</head>
<body>

<header class="cab">
    <?php if (!empty($volver)): ?>
        <a href="<?php echo $h($volver); ?>" class="cab__volver" aria-label="Volver">&#8592;</a>
    <?php endif; ?>
    <h1 class="cab__t">
        <?php echo $h($titulo); ?>
        <?php if (!empty($subtitulo)): ?>
            <span class="cab__s"><?php echo $h($subtitulo); ?></span>
        <?php endif; ?>
    </h1>
</header>

<?php if (!empty($pestanas)): ?>
<nav class="tabs">
    <?php foreach ($pestanas as $t): ?>
        <a href="<?php echo $h($t['url']); ?>"
           class="tab<?php echo !empty($t['activa']) ? ' tab--on' : ''; ?>">
            <?php echo $h($t['texto']); ?>
        </a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<main class="env">
