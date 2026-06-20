@extends('emails.layout')
@section('title', 'Tu Voucher')
@section('content')
    <h2>Tu Voucher de Reserva 🎫</h2>
    <p>Tu voucher PDF para <strong>{{ $tour->translations->firstWhere('locale', 'es')?->title ?? $tour->slug }}</strong> está adjunto a este correo.</p>
    <p>Por favor, presenta este voucher (impreso o en tu teléfono) al llegar al punto de encuentro el día del tour.</p>
    <div class="highlight-box">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Referencia</p>
        <p class="ref">{{ $booking->reference }}</p>
    </div>
@endsection
