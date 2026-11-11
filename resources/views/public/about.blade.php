@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row g-5 align-items-center">
    <div class="col-lg-7">
      <span class="badge wc-badge-type mb-2">Hospitality &amp; Stewardship</span>
      <h1 class="display-6 fw-bold mb-3">About {{ $appName }}</h1>
      <p class="lead text-muted mb-4">
        Operating across {{ $businessLocation }}, we partner with local conservation trusts and skilled craftspeople to run timber lodges, canvas camps, and forest retreats built for quiet restoration.
      </p>
      <p class="text-secondary">
        Every property in our collection runs on transparent direct reservations, real-time room locking to eliminate double-bookings, and coordinated cross-departmental guest care from arrival permit preparation to guided twilight drives.
      </p>
      <div class="d-flex gap-2 mt-4">
        <a href="{{ app_url('/stays', $basePath) }}" class="btn wc-btn-primary">Browse Properties</a>
        <a href="{{ app_url('/contact', $basePath) }}" class="btn btn-outline-secondary">Speak With Concierge</a>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="card wc-card p-4">
        <h2 class="h5 fw-bold mb-3">Guest Promise</h2>
        <ul class="list-unstyled mb-0 small">
          <li class="mb-2">&check; Authoritative rate calculation with zero hidden resort surcharges</li>
          <li class="mb-2">&check; Row-locked reservation guarantees preventing date collisions</li>
          <li class="mb-2">&check; Dedicated General Admin, Sales &amp; Guest Care coordination</li>
          <li>&check; Direct support via {{ $contactEmail }} or {{ $contactPhone }}</li>
        </ul>
      </div>
    </div>
  </div>
</section>
@endsection
