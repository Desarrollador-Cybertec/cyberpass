<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Invitación CyberPass</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 8px; padding: 32px; }
        h1 { color: #1a1a2e; font-size: 22px; }
        p { color: #444; line-height: 1.6; }
        .btn { display: inline-block; margin-top: 24px; padding: 12px 28px; background: #4f46e5; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Hola, {{ $inviteeName }}</h1>
        <p>Has sido invitado a unirte a la organización <strong>{{ $organizationName }}</strong> en CyberPass.</p>
        <p>Haz clic en el botón para aceptar la invitación y configurar tu contraseña. El enlace expira en <strong>{{ $expiresInDays }} días</strong>.</p>
        <a href="{{ $acceptUrl }}" class="btn">Aceptar invitación</a>
        <p class="footer">Si no esperabas esta invitación, puedes ignorar este correo. El enlace dejará de funcionar automáticamente.</p>
    </div>
</body>
</html>
