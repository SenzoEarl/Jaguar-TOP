@extends('layouts.public')

@section('content')
<section class="safari-hero py-5">
  <div class="container py-3">
    <div class="row align-items-center g-4">
      <div class="col-lg-6">
        <span class="badge bg-warning text-dark mb-3">Sanctuaries, Bush Camps &amp; Suites</span>
        <h1 class="display-5 fw-bold mb-3">Wake Up Inside Unspoiled Wilderness</h1>
        <p class="lead safari-hero-lead mb-4">
          Discover private ridge-top timber lodges, star-lit tented camps, and quiet forest cabins managed with care across {{ $businessLocation }}.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <a href="{{ app_url('/stays', $basePath) }}" class="btn btn-lg safari-btn-gold">Explore All Stays</a>
          <a href="{{ app_url('/bookings', $basePath) }}" class="btn btn-lg btn-outline-light">Book Now</a>
        </div>
      </div>
      <div class="col-lg-6">
        <img
          src="{{ app_url('/assets/img/hero-jaguar-safari.svg', $basePath) }}"
          alt="{{ $appName }} sanctuary overlooking the reserve at twilight dusk"
          class="img-fluid safari-hero-image"
        >
      </div>
    </div>
  </div>
</section>

<section class="container py-5">
  <div class="row g-4 mb-5 text-center">
    <div class="col-md-4">
      <div class="card safari-card h-100 p-4">
        <h2 class="h5 fw-bold mb-2">Wilderness Lodges</h2>
        <p class="text-muted small mb-3">Timber and stone sanctuaries with panoramic decks, private plunge pools, and guided drives.</p>
        <a href="{{ app_url('/lodges', $basePath) }}" class="stretched-link fw-semibold text-success">Browse Lodges &rarr;</a>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card safari-card h-100 p-4">
        <h2 class="h5 fw-bold mb-2">Tented Bush Camps</h2>
        <p class="text-muted small mb-3">Low-impact solar-powered canvas camps along seasonal waterholes and stargazing plains.</p>
        <a href="{{ app_url('/camps', $basePath) }}" class="stretched-link fw-semibold text-success">Browse Camps &rarr;</a>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card safari-card h-100 p-4">
        <h2 class="h5 fw-bold mb-2">Cabins &amp; Manor Suites</h2>
        <p class="text-muted small mb-3">Family woodland cabins and lakeside suites crafted for multi-day restoration.</p>
        <a href="{{ app_url('/accommodation', $basePath) }}" class="stretched-link fw-semibold text-success">Browse Accommodation &rarr;</a>
      </div>
    </div>
  </div>

  <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-2 mb-4">
    <div>
      <h2 class="h3 fw-bold mb-1">Featured Properties</h2>
      <p class="text-muted mb-0">Handpicked stays ready for instant date verification and booking.</p>
    </div>
    <a href="{{ app_url('/stays', $basePath) }}" class="btn btn-outline-success btn-sm">View Full Catalog</a>
  </div>

  <div class="row g-4">
    @foreach($featuredProducts as $product)
      <article class="col-md-6 col-lg-3">
        <div class="card safari-card h-100">
          <img
            src="{{ app_url($product->getImagePath(), $basePath) }}"
            alt="{{ $product->getImageAlt() }}"
            class="card-img-top safari-card-img"
            loading="lazy"
          >
          <div class="card-body d-flex flex-column">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="badge safari-badge-type">{{ $product->getType()->label() }}</span>
              <span class="small text-muted">Up to {{ $product->getMaxGuests() }} guests</span>
            </div>
            <h3 class="h5 card-title fw-bold mb-1">{{ $product->getName() }}</h3>
            <p class="small text-muted mb-2">{{ $product->getLocation() }}</p>
            <p class="card-text small flex-grow-1">{{ $product->getShortSummary() }}</p>
            <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-2">
              <span class="fw-bold text-success">{{ format_money($product->getPricePerNight(), $currency) }}<small class="text-muted fw-normal">/night</small></span>
              <a href="{{ app_url($product->detailPath(), $basePath) }}" class="btn btn-sm safari-btn-primary">Details</a>
            </div>
          </div>
        </div>
      </article>
    @endforeach
  </div>
</section>
@endsection
