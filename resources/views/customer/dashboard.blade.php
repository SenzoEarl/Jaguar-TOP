@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h1 class="h2 fw-bold mb-1">My Reservations &amp; Account</h1>
      <p class="text-muted mb-0">
        Signed in as <strong>{{ $currentUser->getFullName() }}</strong> ({{ $currentUser->getEmail() }})
      </p>
    </div>
    <div class="d-flex gap-2">
      @can('staff.portal.access')
        <a href="{{ app_url('/staff/dashboard', $basePath) }}" class="btn btn-outline-dark">Open Staff Portal</a>
      @endcan
      <a href="{{ app_url('/bookings', $basePath) }}" class="btn wc-btn-primary">New Reservation</a>
    </div>
  </div>

  <div class="card wc-card">
    <div class="card-header bg-white py-3">
      <h2 class="h5 fw-bold mb-0">Booking History</h2>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Reference</th>
            <th>Property</th>
            <th>Stay Dates</th>
            <th>Guests</th>
            <th>Total</th>
            <th>Status</th>
            <th class="text-end">Confirmation</th>
          </tr>
        </thead>
        <tbody>
          @if(empty($bookings))
            <tr>
              <td colspan="7" class="text-center py-4 text-muted">
                You have not made any reservations yet.
                <a href="{{ app_url('/stays', $basePath) }}" class="ms-1">Browse properties</a>
              </td>
            </tr>
          @else
            @foreach($bookings as $booking)
              <tr>
                <td><code>{{ $booking->getBookingReference() }}</code></td>
                <td>
                  <a href="{{ app_url($booking->productDetailPath(), $basePath) }}" class="fw-semibold text-decoration-none">
                    {{ $booking->getProductName() }}
                  </a>
                  <div class="small text-muted">{{ $booking->getProductLocation() }}</div>
                </td>
                <td class="small">
                  {{ $booking->getCheckInDate() }} &rarr; {{ $booking->getCheckOutDate() }}
                  <div class="text-muted">{{ $booking->getNightsCount() }} night(s)</div>
                </td>
                <td>{{ $booking->getGuestsCount() }}</td>
                <td class="fw-semibold">{{ format_money($booking->getTotalPrice(), $currency) }}</td>
                <td>
                  <span class="badge {{ $booking->getStatus()->badgeClass() }}">
                    {{ $booking->getStatus()->label() }}
                  </span>
                </td>
                <td class="text-end">
                  <a href="{{ app_url('/bookings/' . $booking->getId() . '/confirmation', $basePath) }}" class="btn btn-sm btn-outline-secondary">
                    View
                  </a>
                </td>
              </tr>
            @endforeach
          @endif
        </tbody>
      </table>
    </div>
  </div>
</section>
@endsection
