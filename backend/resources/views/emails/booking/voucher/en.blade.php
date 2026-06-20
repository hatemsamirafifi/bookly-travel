@extends('emails.layout')
@section('title', 'Your Voucher')
@section('content')
    <h2>Your Booking Voucher 🎫</h2>
    <p>Your PDF voucher for <strong>{{ $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</strong> is attached to this email.</p>
    <p>Please present this voucher (printed or on your phone) when you arrive at the meeting point on the day of your tour.</p>
    <div class="highlight-box">
        <p style="margin: 0; font-size: 13px; color: #8792a2;">Booking Reference</p>
        <p class="ref">{{ $booking->reference }}</p>
    </div>
    <p>Have a wonderful experience! 🌍</p>
@endsection
