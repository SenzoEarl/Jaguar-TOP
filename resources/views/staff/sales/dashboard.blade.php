@extends('layouts.staff')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
  <div>
    <span class="badge bg-primary mb-1">Level 1 &middot; Sales</span>
    <h1 class="h2 fw-bold mb-1">{{ $summary->headline }}</h1>
    <p class="text-muted mb-0">{{ $summary->subtitle }}</p>
  </div>
  <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Level-1 cross-department links">
    <a href="{{ app_url('/staff/general-admin/dashboard', $basePath) }}" class="btn btn-outline-secondary">General Admin (Read)</a>
    <a href="{{ app_url('/staff/sales/dashboard', $basePath) }}" class="btn btn-dark">Sales</a>
    <a href="{{ app_url('/staff/care/dashboard', $basePath) }}" class="btn btn-outline-secondary">Care (Read)</a>
  </div>
</div>

@include('staff._partials.readonly-banner')

<div class="row g-3 mb-4">
  @foreach($summary->metrics as $metric)
    <div class="col-sm-6 col-xl-3">
      <div class="card wc-card wc-metric-card {{ $metric['is_placeholder'] ? 'wc-metric-card--placeholder' : '' }} p-3 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <span class="small text-muted fw-semibold">{{ $metric['title'] }}</span>
          @if($metric['is_placeholder'])
            <span class="badge bg-warning text-dark">Sample</span>
          @endif
        </div>
        <div class="fs-4 fw-bold mt-2">{{ $metric['value'] }}</div>
        <div class="small text-muted mt-1">{{ $metric['detail'] }}</div>
      </div>
    </div>
  @endforeach
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="card wc-card h-100">
      <div class="card-header bg-white py-3">
        <h2 class="h5 fw-bold mb-0">Recent Live Reservations Ledger</h2>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Ref</th>
              <th>Property</th>
              <th>Dates</th>
              <th>Value</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach($summary->recentBookings as $booking)
              <tr>
                <td><code>{{ $booking->getBookingReference() }}</code></td>
                <td class="small fw-semibold">{{ $booking->getProductName() }}</td>
                <td class="small">{{ $booking->getCheckInDate() }} &rarr; {{ $booking->getCheckOutDate() }}</td>
                <td class="small fw-semibold">{{ format_money($booking->getTotalPrice(), $currency) }}</td>
                <td><span class="badge {{ $booking->getStatus()->badgeClass() }}">{{ $booking->getStatus()->label() }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card wc-card h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h2 class="h5 fw-bold mb-0">Charter &amp; Agency Quotes</h2>
        <span class="badge bg-warning text-dark">Placeholder Feed</span>
      </div>
      <div class="card-body">
        <ul class="list-group list-group-flush small">
          <li class="list-group-item d-flex justify-content-between px-0">
            <span>Horizon Safaris 6-Guest Ridge Buyout</span>
            <strong>$9,600</strong>
          </li>
          <li class="list-group-item d-flex justify-content-between px-0">
            <span>Alpine Photo Guild Tented Workshop</span>
            <strong>$5,400</strong>
          </li>
          <li class="list-group-item d-flex justify-content-between px-0">
            <span>Lodge &amp; Lakeview Extended Anniversary</span>
            <strong>$3,400</strong>
          </li>
        </ul>
      </div>
    </div>
  </div>
</div>

@include('staff._partials.task-widget')
@include('staff._partials.file-widget')
@endsection
