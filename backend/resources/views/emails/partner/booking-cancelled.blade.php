@extends('emails.layout')
@section('title', 'Booking Cancelled')
@section('content')
    <h2>Booking Cancelled</h2>
    <p>A traveler has cancelled their booking for your tour.</p>
    <table class="detail-table">
        <tr><td>Reference</td><td>{{ $booking->reference }}</td></tr>
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Date</td><td>{{ $booking->tour_date->format('l, F j, Y') }}</td></tr>
        <tr><td>Amount</td><td>{{ $formattedTotal }}</td></tr>
    </table>
    <p>The availability slot has been released and is now open for new bookings.</p>
@endsection
