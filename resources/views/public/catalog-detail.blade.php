@extends('layouts.public')

@section('head_extra')
{{--
  // [SECURITY:XSS] Safe raw output exemption: $jsonLd is server-serialized via
  json_encode(..., JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR)
  inside Product::toJsonLd(), preventing script breakouts inside ld+json.
--}}
<script type="application/ld+json">{!! $jsonLd !!}</script>
@endsection

@section('content')
<section class="container py-5">
  <nav aria-label="Breadcrumb" class="mb-4">
    <ol class="breadcrumb small">
      <li class="breadcrumb-item"><a href="{{ app_url('/', $basePath) }}">Home</a></li>
      <li class="breadcrumb-item">
        <a href="{{ app_url('/' . $product->getType()->urlSegment(), $basePath) }}">
          {{ $product->getType()->pluralLabel() }}
        </a>
      </li>
      <li class="breadcrumb-item active" aria-current="page">{{ $product->getName() }}</li>
    </ol>
  </nav>

  <div class="row g-5">
    <div class="col-lg-7">
      <img
        src="{{ app_url($product->getImagePath(), $basePath) }}"
        alt="{{ $product->getImageAlt() }}"
        class="img-fluid rounded-3 shadow-sm mb-4 w-100"
      >
      <h2 class="h4 fw-bold mb-3">About {{ $product->getName() }}</h2>
      <p class="text-secondary lh-lg">{{ $product->getDescription() }}</p>

      @if(!empty($product->getAmenities()))
        <h3 class="h5 fw-bold mt-4 mb-3">Included Amenities</h3>
        <div class="row g-2">
          @foreach($product->getAmenities() as $amenity)
            <div class="col-sm-6">
              <div class="p-2 bg-white border rounded small">
                &check; {{ $amenity }}
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </div>

    <div class="col-lg-5">
      <div class="card wc-card p-4 sticky-top wc-sticky-card">
        <span class="badge wc-badge-type align-self-start mb-2">{{ $product->getType()->label() }}</span>
        <h1 class="h3 fw-bold mb-1">{{ $product->getName() }}</h1>
        <p class="text-muted small mb-3">{{ $product->getLocation() }}</p>

        <div class="display-6 fw-bold text-success mb-3">
          {{ format_money($product->getPricePerNight(), $currency) }}
          <span class="fs-6 text-muted fw-normal">per night</span>
        </div>

        <ul class="list-group list-group-flush small mb-4">
          <li class="list-group-item d-flex justify-content-between px-0">
            <span class="text-muted">Guest Capacity</span>
            <strong>Up to {{ $product->getMaxGuests() }} guests</strong>
          </li>
          <li class="list-group-item d-flex justify-content-between px-0">
            <span class="text-muted">Sleeping Quarters</span>
            <strong>{{ $product->getBedrooms() }} bedroom suite(s)</strong>
          </li>
          <li class="list-group-item d-flex justify-content-between px-0">
            <span class="text-muted">Reservation Confirmation</span>
            <strong>Instant Overlap Check</strong>
          </li>
        </ul>

        <a
          href="{{ app_url('/bookings?product=' . urlencode($product->getSlug()), $basePath) }}"
          class="btn btn-lg wc-btn-primary w-100 mb-2"
        >
          Check Dates &amp; Book Stay
        </a>
        <a
          href="{{ app_url('/' . $product->getType()->urlSegment(), $basePath) }}"
          class="btn btn-outline-secondary w-100"
        >
          Back to {{ $product->getType()->pluralLabel() }}
        </a>
      </div>
    </div>
  </div>
</section>
@endsection
