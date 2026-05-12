<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Recuperación de contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background: #f4f4f4; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="560" cellpadding="0" cellspacing="0" style="background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); max-width: 560px; width: 100%;">

                    {{-- Header --}}
                    <tr>
                        <td style="background: #1e293b; padding: 32px 40px; text-align: center;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 22px; letter-spacing: 1px; font-family: Arial, sans-serif;">CyberPass</h1>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding: 40px; color: #374151; font-family: Arial, sans-serif; font-size: 15px; line-height: 1.6;">
                            <p style="margin: 0 0 16px;">Hola, <strong>{{ $user->name }}</strong>.</p>
                            <p style="margin: 0 0 16px;">Recibimos una solicitud para restablecer la contraseña de tu cuenta. El enlace expira en <strong>60 minutos</strong>.</p>

                            @php
                                $frontendUrl = rtrim(env('FRONTEND_URL', config('app.url', 'http://localhost')), '/');
                                $resetUrl    = "{$frontendUrl}/auth/reset-password?token={$token}&email=" . urlencode($user->email);
                            @endphp

                            {{-- CTA Button — table-based for Outlook/Gmail compatibility --}}
                            <table cellpadding="0" cellspacing="0" style="margin: 28px 0;">
                                <tr>
                                    <td align="center" style="background: #2563eb; border-radius: 6px;">
                                        <a href="{{ $resetUrl }}"
                                           target="_blank"
                                           style="display: inline-block; padding: 14px 32px; background: #2563eb; color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 15px; font-family: Arial, sans-serif; mso-padding-alt: 14px 32px;">
                                            Restablecer contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 8px;">Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                            <p style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 16px; font-family: monospace; font-size: 13px; word-break: break-all; color: #1e293b; margin: 0 0 24px;">{{ $resetUrl }}</p>

                            <p style="margin: 0; color: #6b7280; font-size: 13px;">Si no solicitaste este cambio, ignora este correo. Tu contraseña actual permanece sin cambios.</p>
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 40px; font-size: 12px; color: #9ca3af; border-top: 1px solid #f3f4f6; font-family: Arial, sans-serif;">
                            Este mensaje fue enviado automáticamente. Por favor no respondas a este correo.
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
