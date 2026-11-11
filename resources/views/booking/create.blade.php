@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="card wc-card p-4 p-md-5">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
          <div>
            <h1 class="h2 fw-bold mb-1">Reserve Your Stay</h1>
            <p class="text-muted small mb-0">
              Real-time row-locked overlap verification &amp; server-authoritative pricing.
            </p>
          </div>
          <a href="{{ app_url('/stays', $basePath) }}" class="btn btn-sm btn-outline-secondary">Compare Properties</a>
        </div>

        @php($chosenProductId = (int) ($oldInput['product_id'] ?? ($selectedProductId ?? 0)))

        <form
          method="post"
          action="{{ app_url('/bookings', $basePath) }}"
          data-booking-form="true"
          data-currency="{{ $currency }}"
          novalidate
        >
          @csrf
          <input type="hidden" name="idempotency_key" value="{{ $oldInput['idempotency_key'] ?? $idempotencyKey }}">

          <div class="mb-3">
            <label for="bookingProduct" class="form-label fw-semibold">Select Lodge, Camp or Accommodation</label>
            <select id="bookingProduct" name="product_id" class="form-select" required>
              <option value="">Choose a property...</option>
              @foreach($products as $product)
                <option
                  value="{{ $product->getId() }}"
                  data-price="{{ number_format($product->getPricePerNight(), 2, '.', '') }}"
                  data-capacity="{{ $product->getMaxGuests() }}"
                  {{ $chosenProductId === $product->getId() ? 'selected' : '' }}
                >
                  {{ $product->getName() }} ({{ $product->getType()->label() }} &middot; {{ format_money($product->getPricePerNight(), $currency) }}/night &middot; Max {{ $product->getMaxGuests() }})
                </option>
              @endforeach
            </select>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label for="bookingCheckIn" class="form-label fw-semibold">Check-In Date</label>
              <input
                id="bookingCheckIn"
                type="date"
                name="check_in_date"
                class="form-control"
                required
                value="{{ $oldInput['check_in_date'] ?? '' }}"
              >
            </div>
            <div class="col-md-4">
              <label for="bookingCheckOut" class="form-label fw-semibold">Check-Out Date</label>
              <input
                id="bookingCheckOut"
                type="date"
                name="check_out_date"
                class="form-control"
                required
                value="{{ $oldInput['check_out_date'] ?? '' }}"
              >
            </div>
            <div class="col-md-4">
              <label for="bookingGuests" class="form-label fw-semibold">Guests</label>
              <input
                id="bookingGuests"
                type="number"
                name="guests_count"
                min="1"
                class="form-control"
                required
                value="{{ $oldInput['guests_count'] ?? '2' }}"
              >
            </div>
          </div>

          <div class="p-3 bg-light rounded border small mb-3" data-price-estimate="true">
            Select a property and dates to preview estimated stay total.
          </div>

          <div class="mb-4">
            <label for="bookingRequests" class="form-label fw-semibold">Special Requests (optional)</label>
            <textarea
              id="bookingRequests"
              name="special_requests"
              rows="3"
              class="form-control"
              placeholder="Dietary requirements, airport transfer timing, or accessible suite requests..."
            >{{ $oldInput['special_requests'] ?? '' }}</textarea>
          </div>

          <div class="d-flex flex-column flex-sm-row gap-2">
            <button type="button" class="btn btn-outline-success" data-check-availability="true">
              Check Live Availability
            </button>
            <button type="submit" class="btn wc-btn-primary flex-grow-1">
              @if($currentUser !== null)
                Confirm Reservation
              @else
                Sign In to Confirm Reservation
              @endif
            </button>
          </div>

          <div class="d-none" data-availability-status="true" aria-live="polite"></div>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection
