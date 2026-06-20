@extends('emails.layout')
@section('title', 'Il Tuo Voucher')
@section('content')
    <h2>Il Tuo Voucher di Prenotazione 🎫</h2>
    <p>Il tuo voucher PDF per <strong>{{ $tour->translations->firstWhere('locale', 'it')?->title ?? $tour->slug }}</strong> è allegato a questa email.</p>
    <p>Presenta questo voucher (stampato o sul telefono) al punto di incontro il giorno del tour.</p>
    <div class="highlight-box">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Riferimento</p>
        <p class="ref">{{ $booking->reference }}</p>
    </div>
@endsection
