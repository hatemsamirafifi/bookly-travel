@extends('emails.layout')
@section('title', 'Reserva Cancelada')
@section('content')
    <h2>Reserva Cancelada</h2>
    <div class="highlight-box" style="background: #fef2f2; border-color: #fecaca;">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Referencia de Reserva</p>
        <p class="ref">{{ $booking->reference }}</p>
    </div>
    <p>Tu reserva ha sido cancelada según lo solicitado.</p>
    <table class="detail-table">
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'es')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Fecha Original</td><td>{{ $booking->tour_date->format('l, F j, Y') }}</td></tr>
        <tr><td>Monto</td><td>{{ $formattedTotal }}</td></tr>
    </table>
    <p>Si corresponde un reembolso según la política de cancelación, se procesará en 5–10 días hábiles.</p>
@endsection
