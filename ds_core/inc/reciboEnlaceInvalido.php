<?php
/*
| Lo que ve quien abre un recibo compartido cuando el enlace no sirve
| (Fútbol y Arena):
| caducó, está mal copiado o el pago se anuló. No distingue entre esos
| casos a propósito: decir «este pago existe pero caducó» ya sería
| revelar algo a quien prueba enlaces al azar.
*/
http_response_code(410);
$escuela = function_exists('ds_nombre_organizacion') ? ds_nombre_organizacion() : APP_NAME;
$logo    = function_exists('ds_logo_url') ? ds_logo_url(0) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($escuela, ENT_QUOTES, 'UTF-8'); ?> | Recibo no disponible</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
            font-family: "Source Sans Pro", -apple-system, "Segoe UI", Roboto, sans-serif;
            background: #06102a; color: #f8fafc;
        }
        .caja {
            max-width: 420px; width: 100%; text-align: center; padding: 32px 28px;
            background: #0d1a3a; border: 1px solid rgba(255, 255, 255, .1); border-radius: 18px;
        }
        .caja img { width: 72px; height: 72px; object-fit: contain; background: #fff; border-radius: 12px; padding: 4px; }
        h1 { font-size: 1.3rem; margin: 18px 0 8px; }
        p  { color: #cbd5e1; line-height: 1.55; margin: 0; }
        .regla { width: 46px; height: 3px; background: #ffd60a; border-radius: 2px; margin: 18px auto 0; }
    </style>
</head>
<body>
    <div class="caja">
        <?php if ($logo !== ''): ?>
            <img src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="">
        <?php endif; ?>
        <h1>Este enlace ya no está disponible</h1>
        <p>El enlace del recibo caducó o no es válido. Solicite a <strong><?php echo htmlspecialchars($escuela, ENT_QUOTES, 'UTF-8'); ?></strong> que se lo envíe de nuevo.</p>
        <div class="regla"></div>
    </div>
</body>
</html>
