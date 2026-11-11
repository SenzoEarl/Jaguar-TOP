@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
      <h1 class="h2 fw-bold mb-1">{{ $heading }}</h1>
      <p class="text-muted mb-0">{{ $totalCount }} active property option(s) available for online reservation.</p>
    </div>
    <div class="btn-group flex-wrap" role="group" aria-label="Filter by accommodation type">
      <a href="{{ app_url('/stays', $basePath) }}" class="btn btn-sm {{ $activeType === null ? 'safari-btn-primary' : 'btn-outline-secondary' }}">All</a>
      <a href="{{ app_url('/lodges', $basePath) }}" class="btn btn-sm {{ ($activeType !== null && $activeType->value === 'lodge') ? 'safari-btn-primary' : 'btn-outline-secondary' }}">Lodges</a>
      <a href="{{ app_url('/camps', $basePath) }}" class="btn btn-sm {{ ($activeType !== null && $activeType->value === 'camp') ? 'safari-btn-primary' : 'btn-outline-secondary' }}">Camps</a>
      <a href="{{ app_url('/accommodation', $basePath) }}" class="btn btn-sm {{ ($activeType !== null && $activeType->value === 'accommodation') ? 'safari-btn-primary' : 'btn-outline-secondary' }}">Accommodation</a>
    </div>
  </div>

  <form
    method="get"
    action="{{ app_url($canonicalPath, $basePath) }}"
    class="card safari-card p-3 mb-4"
    data-catalog-filter-form="true"
  >
    <div class="row g-2 align-items-end">
      <div class="col-md-5">
        <label for="catalogSearch" class="form-label small fw-semibold mb-1">Search by name or reserve</label>
        <input
          id="catalogSearch"
          type="search"
          name="q"
          class="form-control"
          placeholder="e.g. Riverbend, Savanna, plunge pool..."
          value="{{ $searchQuery ?? '' }}"
        >
      </div>
      <div class="col-md-4">
        <label for="catalogGuests" class="form-label small fw-semibold mb-1">Minimum Guest Capacity</label>
        <select id="catalogGuests" name="guests" class="form-select">
          <option value="">Any guest capacity</option>
          @foreach([2, 4, 6, 8] as $cap)
            <option value="{{ $cap }}" {{ ((int) ($minGuests ?? 0)) === $cap ? 'selected' : '' }}>
              At least {{ $cap }} guests
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn safari-btn-primary flex-grow-1">Apply Filter</button>
        <button type="button" class="btn btn-outline-secondary" data-reset-filters="true">Reset</button>
      </div>
    </div>
  </form>

  @if(empty($products))
    <div class="card safari-card p-5 text-center">
      <h2 class="h5 fw-bold mb-2">No matching properties found</h2>
      <p class="text-muted mb-3">Try lowering the minimum guest requirement or clearing your search keywords.</p>
      <div>
        <a href="{{ app_url($canonicalPath, $basePath) }}" class="btn safari-btn-primary">Show All Properties</a>
      </div>
    </div>
  @else
    <div class="row g-4">
      @foreach($products as $product)
        <article class="col-md-6 col-lg-4">
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
                <span class="small text-muted">{{ $product->getBedrooms() }} bedroom(s) &middot; Max {{ $product->getMaxGuests() }}</span>
              </div>
              <h2 class="h5 card-title fw-bold mb-1">{{ $product->getName() }}</h2>
              <p class="small text-muted mb-2">{{ $product->getLocation() }}</p>
              <p class="card-text small flex-grow-1">{{ $product->getShortSummary() }}</p>
              <div class="d-flex justify-content-between align-items-center pt-3 border-top mt-3">
                <span class="fw-bold text-success">
                  {{ format_money($product->getPricePerNight(), $currency) }}
                  <small class="text-muted fw-normal">/night</small>
                </span>
                <div class="d-flex gap-2">
                  <a href="{{ app_url($product->detailPath(), $basePath) }}" class="btn btn-sm btn-outline-secondary">Explore</a>
                  <a href="{{ app_url('/bookings?product=' . urlencode($product->getSlug()), $basePath) }}" class="btn btn-sm safari-btn-primary">Book</a>
                </div>
              </div>
            </div>
          </div>
        </article>
      @endforeach
    </div>

    @include('partials.pagination')
  @endif
</section>
@endsection
