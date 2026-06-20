@extends('emails.layout')
@section('title', 'Application Status')
@section('content')
    <h2>Partner Application Update</h2>
    <p>Dear {{ $businessName }},</p>
    <p>Thank you for your interest in becoming a Bookly partner. After careful review, we are unable to approve your application at this time.</p>
    <div class="highlight-box" style="background: #fef2f2; border-color: #fecaca;">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Reason</p>
        <p style="margin: 8px 0 0; font-size: 15px; color: #0A2540;">{{ $reason }}</p>
    </div>
    <p>If you believe this decision was made in error, or if you'd like to provide additional information, please contact our team:</p>
    <p style="text-align: center;">
        <a href="mailto:{{ $supportEmail }}" class="cta-button">Contact Support</a>
    </p>
@endsection
