<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invito Partner</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 8px;">
        <h2 style="color: #2b6cb0;">Benvenuto su Bookly, {{ $companyName }}!</h2>
        <p>Sei stato invitato da {{ $adminName }} a unirti a Bookly come partner approvato.</p>
        <p>Per accettare questo invito e completare la configurazione del tuo account, fai clic sul pulsante sottostante:</p>
        <div style="text-align: center; margin: 30px 0;">
            <a href="{{ $inviteUrl }}" style="background-color: #3182ce; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; display: inline-block;">Completa Registrazione Partner</a>
        </div>
        <p style="font-size: 0.9em; color: #666;">Questo invito è valido fino al {{ $expiresAt }}. Se non ti aspettavi questo invito, puoi ignorare questa email.</p>
    </div>
</body>
</html>
