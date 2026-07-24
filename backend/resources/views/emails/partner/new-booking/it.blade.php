@extends('emails.layout')
@section('title', 'Nuova prenotazione')
@section('content')
    <h2>Nuova prenotazione ricevuta! 📋</h2>
    <p>Buone notizie: è stata confermata una nuova prenotazione per il tuo tour.</p>
    <table class="detail-table">
        <tr><td>Riferimento</td><td>{{ $booking->reference }}</td></tr>
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'it')?->title ?? $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Data</td><td>{{ $tourDate }}</td></tr>
        <tr><td>Partecipanti</td><td>{{ $participantCount }}</td></tr>
        <tr><td>Ricavo</td><td>{{ $formattedTotal }}</td></tr>
        <tr><td>Viaggiatore</td><td>{{ $traveler?->name ?? 'Ospite' }}</td></tr>
    </table>
    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/partner/bookings" class="cta-button">Vedi nel dashboard</a>
    </p>
@endsection