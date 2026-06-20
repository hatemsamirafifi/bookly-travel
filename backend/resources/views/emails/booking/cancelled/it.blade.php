@extends('emails.layout')
@section('title', 'Prenotazione Annullata')
@section('content')
    <h2>Prenotazione Annullata</h2>
    <div class="highlight-box" style="background: #fef2f2; border-color: #fecaca;">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Riferimento Prenotazione</p>
        <p class="ref">{{ $booking->reference }}</p>
    </div>
    <p>La tua prenotazione è stata annullata come richiesto.</p>
    <table class="detail-table">
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'it')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Data Originale</td><td>{{ $booking->tour_date->format('l, F j, Y') }}</td></tr>
        <tr><td>Importo</td><td>{{ $formattedTotal }}</td></tr>
    </table>
    <p>Se un rimborso è applicabile, verrà elaborato entro 5–10 giorni lavorativi.</p>
@endsection
