<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperación de contraseña</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1e293b; padding: 32px 40px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; letter-spacing: 1px; }
        .body { padding: 40px; color: #374151; line-height: 1.6; }
        .body p { margin: 0 0 16px; }
        .btn { display: inline-block; margin: 24px 0; padding: 14px 32px; background: #2563eb; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px; }
        .token-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; font-family: monospace; font-size: 13px; word-break: break-all; color: #1e293b; }
        .footer { padding: 24px 40px; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CyberPass</h1>
        </div>
        <div class="body">
            <p>Hola, <strong>{{ $user->name }}</strong>.</p>
            <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta. El enlace expira en <strong>60 minutos</strong>.</p>

            @php
                $frontendUrl = rtrim(env('FRONTEND_URL', config('app.url', 'http://localhost')), '/');
                $resetUrl    = "{$frontendUrl}/auth/reset-password?token={$token}&email=" . urlencode($user->email);
            @endphp

            <a href="{{ $resetUrl }}" class="btn">Restablecer contraseña</a>

            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
            <div class="token-box">{{ $resetUrl }}</div>

            <p style="margin-top:24px;">Si no solicitaste este cambio, ignora este correo. Tu contraseña actual permanece sin cambios.</p>
        </div>
        <div class="footer">
            Este mensaje fue enviado automáticamente. Por favor no respondas a este correo.
        </div>
    </div>
</body>
</html>
