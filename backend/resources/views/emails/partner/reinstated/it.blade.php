<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Il tuo account partner Bookly è stato ripristinato</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #16a34a;">Il tuo account partner è stato ripristinato</h2>
    <p>Gentile {{ $businessName }},</p>
    <p>Siamo lieti di informarti che il tuo account partner Bookly è stato ripristinato dal nostro team di amministrazione.</p>
    <div style="background: #f0fdf4; border-left: 4px solid #16a34a; padding: 15px; margin: 20px 0;">
        <strong style="color: #166534;">Avviso importante sui tuoi tour:</strong>
        <p style="margin: 8px 0 0 0; color: #14532d;">Per motivi di sicurezza, i tuoi tour rimangono in stato di bozza / non pubblicati. Ti invitiamo a verificare i dettagli dei tour e inviarli nuovamente per la revisione o pubblicarli dal pannello partner.</p>
    </div>
    <p style="margin: 25px 0;">
        <a href="{{ $dashboardUrl }}" style="background-color: #2563eb; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: bold;">Vai al pannello partner</a>
    </p>
    <p>Per qualsiasi domanda, contatta il nostro supporto all'indirizzo <a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>.</p>
    <p>Cordiali saluti,<br>Il team Bookly</p>
</body>
</html>
