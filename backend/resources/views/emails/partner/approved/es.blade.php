@extends('emails.layout')
@section('title', 'Solicitud aprobada')
@section('content')
    <h2>¡Felicidades, {{ $businessName }}! 🎉</h2>
    <p>Tu solicitud de partner ha sido aprobada. Ya puedes acceder a tu Panel de Partner para empezar a crear tours.</p>
    <p style="text-align: center;">
        <a href="{{ $dashboardUrl }}" class="cta-button">Ir al Panel de Partner</a>
    </p>
    <p>Esto es lo que puedes hacer a continuación:</p>
    <ul style="color: #425466; font-size: 15px; line-height: 1.8;">
        <li>Crea tu primer tour</li>
        <li>Configura precios y disponibilidad</li>
        <li>Sube fotos para atraer viajeros</li>
        <li>Completa tu perfil de partner</li>
    </ul>
    <p>¡Bienvenido a la comunidad de partners de Bookly!</p>
@endsection