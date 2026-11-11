@extends('layouts.staff')

@section('content')
<div class="mb-4">
  <span class="badge bg-success mb-1">Level 3 &middot; Manager</span>
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
    <div class="card wc-card h-100">
      <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h2 class="h5 fw-bold mb-0">30-Day Portfolio Pacing</h2>
        <span class="badge bg-warning text-dark">Placeholder Forecast</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>Category</th>
              <th>Target Pace</th>
              <th>Current Pace</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Wilderness Lodges</td>
              <td>75%</td>
              <td class="fw-bold text-success">81%</td>
            </tr>
            <tr>
              <td>Tented Bush Camps</td>
              <td>70%</td>
              <td class="fw-bold text-success">74%</td>
            </tr>
            <tr>
              <td>Cabins &amp; Suites</td>
              <td>72%</td>
              <td class="fw-bold text-secondary">69%</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card wc-card h-100">
      <div class="card-header bg-white py-3">
        <h2 class="h5 fw-bold mb-0">Latest Live Reservations</h2>
      </div>
      <div class="table-responsive">
        <table class="table table-sm mb-0">
          <thead class="table-light">
            <tr>
              <th>Reference</th>
              <th>Property</th>
              <th>Total</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @foreach($summary->recentBookings as $booking)
              <tr>
                <td><code>{{ $booking->getBookingReference() }}</code></td>
                <td class="small">{{ $booking->getProductName() }}</td>
                <td class="small fw-semibold">{{ format_money($booking->getTotalPrice(), $currency) }}</td>
                <td><span class="badge {{ $booking->getStatus()->badgeClass() }}">{{ $booking->getStatus()->label() }}</span></td>
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
