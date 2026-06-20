@extends('emails.layout')
@section('title', 'Application Approved')
@section('content')
    <h2>Congratulations, {{ $businessName }}! 🎉</h2>
    <p>Your partner application has been approved. You can now access your Partner Dashboard to start creating tours.</p>
    <p style="text-align: center;">
        <a href="{{ $dashboardUrl }}" class="cta-button">Go to Partner Dashboard</a>
    </p>
    <p>Here's what you can do next:</p>
    <ul style="color: #425466; font-size: 15px; line-height: 1.8;">
        <li>Create your first tour listing</li>
        <li>Set up pricing and availability</li>
        <li>Upload photos to attract travelers</li>
        <li>Complete your partner profile</li>
    </ul>
    <p>Welcome to the Bookly partner community!</p>
@endsection
