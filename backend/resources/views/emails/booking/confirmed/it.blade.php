@extends('emails.layout')

@section('title', 'Prenotazione Confermata')

@section('content')
    <h2>La tua prenotazione è confermata! 🎉</h2>

    <div class="highlight-box">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Riferimento Prenotazione</p>
        <p class="ref">{{ $booking->reference }}</p>
    </div>

    <p>Grazie per aver prenotato con Bookly! Il tuo pagamento è stato ricevuto e il tuo posto è assicurato.</p>

    <table class="detail-table">
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'it')?->title ?? $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Data</td><td>{{ $tourDate }}</td></tr>
        <tr><td>Partecipanti</td><td>{{ $booking->participant_count }}</td></tr>
        <tr><td>Totale Pagato</td><td>{{ $formattedTotal }}</td></tr>
        @if($tour->meeting_point)
        <tr><td>Punto di Incontro</td><td>{{ $tour->meeting_point }}</td></tr>
        @endif
    </table>

    <p>Riceverai un voucher PDF con i dettagli della prenotazione e un codice QR in un'email separata. Presentalo il giorno del tour.</p>

    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/it/my-bookings/{{ $booking->reference }}" class="cta-button">
            Visualizza Dettagli Prenotazione
        </a>
    </p>
@endsection
