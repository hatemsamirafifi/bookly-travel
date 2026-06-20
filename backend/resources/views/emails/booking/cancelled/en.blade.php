@extends('emails.layout')
@section('title', 'Booking Cancelled')
@section('content')
    <h2>Booking Cancelled</h2>
    <div class="highlight-box" style="background: #fef2f2; border-color: #fecaca;">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Booking Reference</p>
        <p class="ref">{{ $booking->reference }}</p>
    </div>
    <p>Your booking has been cancelled as requested.</p>
    <table class="detail-table">
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Original Date</td><td>{{ $booking->tour_date->format('l, F j, Y') }}</td></tr>
        <tr><td>Amount</td><td>{{ $formattedTotal }}</td></tr>
        <tr><td>Status</td><td>Cancelled</td></tr>
    </table>
    <p>If a refund is applicable under the cancellation policy, it will be processed to your original payment method within 5–10 business days.</p>
    <p>We hope to see you on a future tour!</p>
@endsection
