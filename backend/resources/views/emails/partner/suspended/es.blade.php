<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tu cuenta de partner de Bookly ha sido suspendida</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #dc2626;">Tu cuenta de partner ha sido suspendida</h2>
    <p>Estimado/a {{ $businessName }}:</p>
    <p>Le escribimos para informarle de que su cuenta de partner en Bookly ha sido suspendida por nuestro equipo de administración.</p>
    <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;">
        <strong style="color: #991b1b;">Motivo de la suspensión:</strong>
        <p style="margin: 8px 0 0 0; color: #7f1d1d;">{{ $reason }}</p>
    </div>
    <p>Como consecuencia, sus tours publicados han sido despublicados y están ocultos para las reservas. Si tiene reservas confirmadas pendientes, nuestro equipo de soporte o los clientes podrán contactarle.</p>
    <p>Si considera que esta decisión se ha tomado por error o desea apelar, póngase en contacto con nosotros en <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
    <p>Atentamente,<br>El equipo de Bookly</p>
</body>
</html>
