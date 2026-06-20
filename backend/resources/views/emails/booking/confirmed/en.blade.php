@extends('emails.layout')

@section('title', 'Booking Confirmed')

@section('content')
    <h2>Your Booking is Confirmed! 🎉</h2>

    <div class="highlight-box">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Booking Reference</p>
        <p class="ref">{{ $booking->reference }}</p>
    </div>

    <p>Thank you for booking with Bookly! Your payment has been received and your spot is secured.</p>

    <table class="detail-table">
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Date</td><td>{{ $tourDate }}</td></tr>
        <tr><td>Participants</td><td>{{ $booking->participant_count }}</td></tr>
        <tr><td>Total Paid</td><td>{{ $formattedTotal }}</td></tr>
        @if($tour->meeting_point)
        <tr><td>Meeting Point</td><td>{{ $tour->meeting_point }}</td></tr>
        @endif
    </table>

    <p>A PDF voucher with your booking details and QR code will be sent in a separate email. Please present it on the day of your tour.</p>

    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/en/my-bookings/{{ $booking->reference }}" class="cta-button">
            View Booking Details
        </a>
    </p>
@endsection
