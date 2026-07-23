@extends('emails.layout')
@section('title', 'Nueva reserva')
@section('content')
    <h2>¡Nueva reserva recibida! 📋</h2>
    <p>Buenas noticias: se ha confirmado una nueva reserva para tu tour.</p>
    <table class="detail-table">
        <tr><td>Referencia</td><td>{{ $booking->reference }}</td></tr>
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'es')?->title ?? $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Fecha</td><td>{{ $tourDate }}</td></tr>
        <tr><td>Participantes</td><td>{{ $participantCount }}</td></tr>
        <tr><td>Ingresos</td><td>{{ $formattedTotal }}</td></tr>
        <tr><td>Viajero</td><td>{{ $traveler?->name ?? 'Invitado' }}</td></tr>
    </table>
    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/partner/bookings" class="cta-button">Ver en el panel</a>
    </p>
@endsection