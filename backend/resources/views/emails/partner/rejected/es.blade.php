@extends('emails.layout')
@section('title', 'Estado de la solicitud')
@section('content')
    <h2>Actualización de tu solicitud de partner</h2>
    <p>Estimado/a {{ $businessName }},</p>
    <p>Gracias por tu interés en convertirte en partner de Bookly. Tras una revisión detallada, no podemos aprobar tu solicitud en este momento.</p>
    <div class="highlight-box" style="background: #fef2f2; border-color: #fecaca;">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Motivo</p>
        <p style="margin: 8px 0 0; font-size: 15px; color: #0A2540;">{{ $reason }}</p>
    </div>
    <p>Si crees que esta decisión es un error, o si deseas aportar información adicional, contacta con nuestro equipo:</p>
    <p style="text-align: center;">
        <a href="mailto:{{ $supportEmail }}" class="cta-button">Contactar con soporte</a>
    </p>
@endsection