<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invitación para Socios</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;">
        <h2 style="color: #2b6cb0;">¡Bienvenido a Bookly, {{ $companyName }}!</h2>
        <p>Ha sido invitado por {{ $adminName }} para unirse a Bookly como socio aprobado.</p>
        <p>Para aceptar esta invitación y completar la configuración de su cuenta, haga clic en el siguiente botón:</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $inviteUrl }}" style="background-color: #3182ce; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Completar Registro de Socio</a>
        </div>
        <p style="font-size: 0.9em; color: #666;">Esta invitación es válida hasta el {{ $expiresAt }}. Si no esperaba esta invitación, puede ignorar este mensaje.</p>
    </div>
</body>
</html>
