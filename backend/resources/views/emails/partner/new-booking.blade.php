@extends('emails.layout')
@section('title', 'New Booking')
@section('content')
    <h2>New Booking Received! 📋</h2>
    <p>Great news — a new booking has been confirmed for your tour.</p>
    <table class="detail-table">
        <tr><td>Reference</td><td>{{ $booking->reference }}</td></tr>
        <tr><td>Tour</td><td>{{ $tour->translations->firstWhere('locale', 'en')?->title ?? $tour->slug }}</td></tr>
        <tr><td>Date</td><td>{{ $tourDate }}</td></tr>
        <tr><td>Participants</td><td>{{ $participantCount }}</td></tr>
        <tr><td>Revenue</td><td>{{ $formattedTotal }}</td></tr>
        <tr><td>Traveler</td><td>{{ $traveler?->name ?? 'Guest' }}</td></tr>
    </table>
    <p style="text-align: center;">
        <a href="{{ config('app.url') }}/partner/bookings" class="cta-button">View in Dashboard</a>
    </p>
@endsection
