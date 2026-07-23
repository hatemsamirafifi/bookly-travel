@extends('emails.layout')
@section('title', 'Reserva cancelada')
@section('content')
    <h2>Reserva cancelada</h2>
    <p>Un viajero ha cancelado su reserva para tu tour.</p>
    <table class="detail-table">
        <tr><td>Referencia</td><td>{{ $booking->reference }}</td></tr>
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'es')?->title ?? $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Fecha</td><td>{{ $booking->tour_date->format('l, F j, Y') }}</td></tr>
        <tr><td>Importe</td><td>{{ $formattedTotal }}</td></tr>
    </table>
    <p>El hueco de disponibilidad se ha liberado y queda abierto para nuevas reservas.</p>
@endsection