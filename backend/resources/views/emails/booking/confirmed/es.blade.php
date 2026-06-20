@extends('emails.layout')

@section('title', 'Reserva Confirmada')

@section('content')
    <h2>¡Tu reserva está confirmada! 🎉</h2>

    <div class="highlight-box">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Referencia de Reserva</p>
        <p class="ref">{{ $booking->reference }}</p>
    </div>

    <p>¡Gracias por reservar con Bookly! Tu pago ha sido recibido y tu plaza está asegurada.</p>

    <table class="detail-table">
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'es')?->title ?? $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Fecha</td><td>{{ $tourDate }}</td></tr>
        <tr><td>Participantes</td><td>{{ $booking->participant_count }}</td></tr>
        <tr><td>Total Pagado</td><td>{{ $formattedTotal }}</td></tr>
        @if($tour->meeting_point)
        <tr><td>Punto de Encuentro</td><td>{{ $tour->meeting_point }}</td></tr>
        @endif
    </table>

    <p>Recibirás un voucher PDF con los detalles de tu reserva y un código QR en un correo separado. Por favor, preséntalo el día del tour.</p>

    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/es/my-bookings/{{ $booking->reference }}" class="cta-button">
            Ver Detalles de Reserva
        </a>
    </p>
@endsection
