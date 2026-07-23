@extends('emails.layout')
@section('title', 'Prenotazione cancellata')
@section('content')
    <h2>Prenotazione cancellata</h2>
    <p>Un viaggiatore ha cancellato la propria prenotazione per il tuo tour.</p>
    <table class="detail-table">
        <tr><td>Riferimento</td><td>{{ $booking->reference }}</td></tr>
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'it')?->title ?? $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Data</td><td>{{ $booking->tour_date->format('l, F j, Y') }}</td></tr>
        <tr><td>Importo</td><td>{{ $formattedTotal }}</td></tr>
    </table>
    <p>Lo slot di disponibilità è stato rilasciato ed è ora aperto a nuove prenotazioni.</p>
@endsection