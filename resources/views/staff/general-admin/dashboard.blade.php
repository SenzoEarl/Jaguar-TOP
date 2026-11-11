@extends('layouts.staff')

@section('content')
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
  <div>
    <span class="badge bg-success mb-1">Level 1 &middot; General Admin</span>
    <h1 class="h2 fw-bold mb-1">{{ $summary->headline }}</h1>
    <p class="text-muted mb-0">{{ $summary->subtitle }}</p>
  </div>
  <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Level-1 cross-department links">
    <a href="{{ app_url('/staff/general-admin/dashboard', $basePath) }}" class="btn btn-dark">General Admin</a>
    <a href="{{ app_url('/staff/sales/dashboard', $basePath) }}" class="btn btn-outline-secondary">Sales (Read)</a>
    <a href="{{ app_url('/staff/care/dashboard', $basePath) }}" class="btn btn-outline-secondary">Care (Read)</a>
  </div>
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
    <h2 class="h5 fw-bold mb-0">Park Permits &amp; Regulatory Register</h2>
    <span class="badge bg-warning text-dark">Placeholder Feed</span>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>Sanctuary Zone</th>
          <th>Document Reference</th>
          <th>Renewal Date</th>
          <th>Compliance State</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Northern Ridge Reserve</td>
          <td><code>PRM-2026-NR-04</code></td>
          <td>2026-12-31</td>
          <td><span class="badge bg-success">Verified</span></td>
        </tr>
        <tr>
          <td>Savanna Conservancy Corridor</td>
          <td><code>ECO-LIC-118</code></td>
          <td>2026-11-28</td>
          <td><span class="badge bg-warning text-dark">Renewal Queued</span></td>
        </tr>
        <tr>
          <td>Riverbend Fire &amp; Safety Audit</td>
          <td><code>FSA-2026-RB-09</code></td>
          <td>2027-03-15</td>
          <td><span class="badge bg-success">Verified</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

@include('staff._partials.task-widget')
@include('staff._partials.file-widget')
@endsection
