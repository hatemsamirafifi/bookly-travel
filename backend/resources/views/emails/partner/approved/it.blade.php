@extends('emails.layout')
@section('title', 'Richiesta approvata')
@section('content')
    <h2>Congratulazioni, {{ $businessName }}! 🎉</h2>
    <p>La tua richiesta di partner è stata approvata. Ora puoi accedere al tuo Dashboard Partner per iniziare a creare tour.</p>
    <p style="text-align: center;">
        <a href="{{ $dashboardUrl }}" class="cta-button">Vai al Dashboard Partner</a>
    </p>
    <p>Ecco cosa puoi fare ora:</p>
    <ul style="color: #425466; font-size: 15px; line-height: 1.8;">
        <li>Crea il tuo primo tour</li>
        <li>Imposta prezzi e disponibilità</li>
        <li>Carica foto per attirare viaggiatori</li>
        <li>Completa il tuo profilo partner</li>
    </ul>
    <p>Benvenuto nella community di partner Bookly!</p>
@endsection