@extends('emails.layout')
@section('title', 'Application Received')
@section('content')
    <h2>Thank You for Applying, {{ $businessName }}!</h2>
    <p>Dear {{ $contactPerson }},</p>
    <p>We have received your application to become a Bookly partner tour operator. Our team is currently reviewing your business information.</p>
    <div class="highlight-box">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Review Timeline</p>
        <p style="margin: 8px 0 0; font-size: 15px; color: #0A2540;">Applications are typically reviewed within 2–3 business days. We will notify you by email as soon as a decision has been made.</p>
    </div>
    <p>In the meantime, you can check the status of your application at any time:</p>
    <p style="text-align: center;">
        <a href="{{ $onboardingUrl }}" class="cta-button">View Application Status</a>
    </p>
    <p>Thank you for choosing Bookly!</p>
@endsection
