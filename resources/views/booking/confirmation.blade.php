@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card safari-card p-4 p-md-5">
        <span class="badge {{ $booking->getStatus()->badgeClass() }} align-self-start mb-2">
          Reservation {{ $booking->getStatus()->label() }}
        </span>
        <h1 class="h2 fw-bold mb-1">Booking Confirmation</h1>
        <p class="text-muted mb-4">
          Reference Code: <strong class="text-dark"><code>{{ $booking->getBookingReference() }}</code></strong>
        </p>

        <dl class="row small mb-4">
          <dt class="col-sm-4 text-muted">Property</dt>
          <dd class="col-sm-8 fw-semibold">{{ $booking->getProductName() }} ({{ $booking->getProductLocation() }})</dd>

          <dt class="col-sm-4 text-muted">Check-In</dt>
          <dd class="col-sm-8">{{ $booking->getCheckInDate() }}</dd>

          <dt class="col-sm-4 text-muted">Check-Out</dt>
          <dd class="col-sm-8">{{ $booking->getCheckOutDate() }} ({{ $booking->getNightsCount() }} nights)</dd>

          <dt class="col-sm-4 text-muted">Guests</dt>
          <dd class="col-sm-8">{{ $booking->getGuestsCount() }}</dd>

          <dt class="col-sm-4 text-muted">Total Amount</dt>
          <dd class="col-sm-8 fw-bold text-success">{{ format_money($booking->getTotalPrice(), $currency) }}</dd>

          @if($booking->getSpecialRequests() !== null)
            <dt class="col-sm-4 text-muted">Special Requests</dt>
            <dd class="col-sm-8">{{ $booking->getSpecialRequests() }}</dd>
          @endif
        </dl>

        <div class="d-flex flex-wrap gap-2">
          <a href="{{ app_url('/account/dashboard', $basePath) }}" class="btn safari-btn-primary">View All My Bookings</a>
          <a href="{{ app_url('/stays', $basePath) }}" class="btn btn-outline-secondary">Explore More Stays</a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
