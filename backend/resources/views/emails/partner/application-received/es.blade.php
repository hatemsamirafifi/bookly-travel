@extends('emails.layout')
@section('title', 'Solicitud recibida')
@section('content')
    <h2>¡Gracias por registrarte, {{ $businessName }}!</h2>
    <p>Estimado/a {{ $contactPerson }}:</p>
    <p>Hemos recibido tu solicitud para convertirte en partner de Bookly. Nuestro equipo está revisando la información de tu empresa.</p>
    <div class="highlight-box">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Plazo de revisión</p>
        <p style="margin: 8px 0 0; font-size: 15px; color: #0A2540;">Las solicitudes se revisan normalmente en un plazo de 2 a 3 días hábiles. Te notificaremos por correo electrónico en cuanto tomemos una decisión.</p>
    </div>
    <p>Mientras tanto, puedes consultar el estado de tu solicitud en cualquier momento:</p>
    <p style="text-align: center;">
        <a href="{{ $onboardingUrl }}" class="cta-button">Ver estado de la solicitud</a>
    </p>
    <p>¡Gracias por elegir Bookly!</p>
@endsection
