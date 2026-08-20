<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Il tuo account partner Bookly è stato sospeso</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #dc2626;">Il tuo account partner è stato sospeso</h2>
    <p>Gentile {{ $businessName }},</p>
    <p>Ti informiamo che il tuo account partner Bookly è stato sospeso dal nostro team di amministrazione.</p>
    <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 15px; margin: 20px 0;">
        <strong style="color: #991b1b;">Motivo della sospensione:</strong>
        <p style="margin: 8px 0 0 0; color: #7f1d1d;">{{ $reason }}</p>
    </div>
    <p>Di conseguenza, i tuoi tour pubblicati sono stati rimossi e non sono più prenotabili. Se hai prenotazioni attive confermate, il nostro team o i viaggiatori potrebbero contattarti.</p>
    <p>Se ritieni che questa decisione sia avvenuta per errore o desideri presentare ricorso, contatta il nostro supporto all'indirizzo <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
    <p>Cordiali saluti,<br>Il team Bookly</p>
</body>
</html>
