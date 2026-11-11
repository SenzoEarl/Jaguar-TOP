@extends('layouts.staff')

@section('content')
<div class="mb-4">
  <span class="badge bg-dark mb-1">Level 4 &middot; Senior Manager</span>
  <h1 class="h2 fw-bold mb-1">{{ $summary->headline }}</h1>
  <p class="text-muted mb-0">{{ $summary->subtitle }}</p>
</div>

@include('staff._partials.readonly-banner')

<div class="row g-3 mb-4">
  @foreach($summary->metrics as $metric)
    <div class="col-sm-6 col-xl-3">
      <div class="card safari-card safari-metric-card {{ $metric['is_placeholder'] ? 'safari-metric-card--placeholder' : '' }} p-3 h-100">
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

<div class="card safari-card mb-4">
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
    <h2 class="h5 fw-bold mb-0">Regional Infrastructure &amp; Vendor Tenders</h2>
    <span class="badge bg-warning text-dark">Placeholder Feed</span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Tender ID</th>
          <th>Initiative</th>
          <th>Budget Envelope</th>
          <th>Stage</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><code>TND-SOLAR-22</code></td>
          <td>Starlight Camp Battery Storage Expansion</td>
          <td>$42,000</td>
          <td><span class="badge bg-info text-dark">Technical Review</span></td>
        </tr>
        <tr>
          <td><code>TND-ROOF-07</code></td>
          <td>Riverbend Lodge Cedar Thatch Renewal</td>
          <td>$18,500</td>
          <td><span class="badge bg-success">Approved</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

@include('staff._partials.task-widget')
@include('staff._partials.file-widget')
@endsection
