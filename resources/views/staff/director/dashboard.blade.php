@extends('layouts.staff')

@section('content')
<div class="mb-4">
  <span class="badge bg-warning text-dark mb-1">Level 5 &middot; Executive Director</span>
  <h1 class="h2 fw-bold mb-1">{{ $summary->headline }}</h1>
  <p class="text-muted mb-0">{{ $summary->subtitle }}</p>
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
  <div class="col-lg-6">
    <div class="card wc-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h5 fw-bold mb-0">Portfolio Governance Oversight</h2>
        <span class="badge bg-warning text-dark">Placeholder Feed</span>
      </div>
      <p class="small text-muted">
        Direct cross-department inspection across operations, sales velocity, concierge quality, and capital stewardship.
      </p>
      <div class="d-flex flex-wrap gap-2">
        <a href="{{ app_url('/staff/senior-manager/dashboard', $basePath) }}" class="btn btn-sm btn-outline-dark">Senior Manager</a>
        <a href="{{ app_url('/staff/manager/dashboard', $basePath) }}" class="btn btn-sm btn-outline-dark">Manager</a>
        <a href="{{ app_url('/staff/supervisor/dashboard', $basePath) }}" class="btn btn-sm btn-outline-dark">Supervisor</a>
        <a href="{{ app_url('/staff/sales/dashboard', $basePath) }}" class="btn btn-sm btn-outline-secondary">Sales</a>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card wc-card h-100">
      <div class="card-header bg-white py-3">
        <h2 class="h5 fw-bold mb-0">High-Value Recent Reservations</h2>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>Ref</th>
              <th>Property</th>
              <th>Nights</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            @foreach($summary->recentBookings as $booking)
              <tr>
                <td><code>{{ $booking->getBookingReference() }}</code></td>
                <td class="small">{{ $booking->getProductName() }}</td>
                <td class="small">{{ $booking->getNightsCount() }}</td>
                <td class="small fw-bold">{{ format_money($booking->getTotalPrice(), $currency) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

@include('staff._partials.task-widget')
@include('staff._partials.file-widget')
@endsection
