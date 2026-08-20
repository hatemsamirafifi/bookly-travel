<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tu cuenta de partner de Bookly ha sido restablecida</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #16a34a;">Tu cuenta de partner ha sido restablecida</h2>
    <p>Estimado/a {{ $businessName }}:</p>
    <p>Nos complace informarle de que su cuenta de partner en Bookly ha sido restablecida por nuestro equipo de administración.</p>
    <div style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 15px; margin: 20px 0;">
        <strong style="color: #166534;">Aviso importante sobre sus tours:</strong>
        <p style="margin: 8px 0 0 0; color: #14532d;">Por motivos de seguridad, sus tours permanecen en estado borrador / no publicados. Revise la información de sus tours y vuelva a enviarlos a revisión o publíquelos desde su panel de partner.</p>
    </div>
    <p style="margin: 25px 0;">
        <a href="{{ $dashboardUrl }}" style="background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;">Ir al panel de partner</a>
    </p>
    <p>Si tiene alguna pregunta, contáctenos en <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
    <p>Atentamente,<br>El equipo de Bookly</p>
</body>
</html>
