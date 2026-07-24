@extends('emails.layout')
@section('title', 'Stato della richiesta')
@section('content')
    <h2>Aggiornamento sulla tua richiesta di partner</h2>
    <p>Gentile {{ $businessName }},</p>
    <p>Grazie per il tuo interesse a diventare partner di Bookly. Dopo un'attenta revisione, non possiamo approvare la tua richiesta in questo momento.</p>
    <div class="highlight-box" style="background: #fef2f2; border-color: #fecaca;">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Motivo</p>
        <p style="margin: 8px 0 0; font-size: 15px; color: #0A2540;">{{ $reason }}</p>
    </div>
    <p>Se ritieni che questa decisione sia un errore, o se desideri fornire informazioni aggiuntive, contatta il nostro team:</p>
    <p style="text-align: center;">
        <a href="mailto:{{ $supportEmail }}" class="cta-button">Contatta l'assistenza</a>
    </p>
@endsection