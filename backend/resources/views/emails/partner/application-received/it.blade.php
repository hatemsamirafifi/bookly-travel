@extends('emails.layout')
@section('title', 'Richiesta ricevuta')
@section('content')
    <h2>Grazie per la registrazione, {{ $businessName }}!</h2>
    <p>Gentile {{ $contactPerson }},</p>
    <p>Abbiamo ricevuto la tua richiesta per diventare partner di Bookly. Il nostro team sta esaminando i dati della tua attività.</p>
    <div class="highlight-box">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Tempi di revisione</p>
        <p style="margin: 8px 0 0; font-size: 15px; color: #0A2540;">Le richieste vengono solitamente esaminate entro 2-3 giorni lavorativi. Ti informeremo via email non appena sarà presa una decisione.</p>
    </div>
    <p>Nel frattempo, puoi verificare lo stato della tua richiesta in qualsiasi momento:</p>
    <p style="text-align: center;">
        <a href="{{ $onboardingUrl }}" class="cta-button">Visualizza stato richiesta</a>
    </p>
    <p>Grazie per aver scelto Bookly!</p>
@endsection
