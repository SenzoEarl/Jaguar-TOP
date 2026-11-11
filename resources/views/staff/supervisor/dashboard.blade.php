@extends('layouts.staff')

@section('content')
<div class="mb-4">
  <span class="badge bg-secondary mb-1">Level 2 &middot; Supervisor</span>
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

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="card safari-card p-4 h-100">
      <h2 class="h5 fw-bold mb-2">Supervised Level-1 Department Feeds</h2>
      <p class="small text-muted mb-3">
        Inspect queues and vaults across General Admin, Sales, and Guest Care (read-only view per RBAC map).
      </p>
      <div class="d-flex flex-wrap gap-2">
        <a href="{{ app_url('/staff/general-admin/dashboard', $basePath) }}" class="btn btn-sm btn-outline-dark">General Admin</a>
        <a href="{{ app_url('/staff/sales/dashboard', $basePath) }}" class="btn btn-sm btn-outline-dark">Sales Desk</a>
        <a href="{{ app_url('/staff/care/dashboard', $basePath) }}" class="btn btn-sm btn-outline-dark">Guest Care</a>
        <a href="{{ app_url('/staff/files/general-admin', $basePath) }}" class="btn btn-sm btn-outline-secondary">L1 File Vaults</a>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card safari-card p-4 h-100">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h5 fw-bold mb-0">Weekend Duty Roster</h2>
        <span class="badge bg-warning text-dark">Placeholder Feed</span>
      </div>
      <ul class="list-unstyled small mb-0">
        <li class="mb-1">&bull; Lodge Reception Shift A: 06:00 - 14:00 (Covered)</li>
        <li class="mb-1">&bull; Savanna Ranger Escort Shift B: 13:00 - 21:00 (Covered)</li>
        <li>&bull; Night Security &amp; Solar Telemetry: 21:00 - 06:00 (Covered)</li>
      </ul>
    </div>
  </div>
</div>

@include('staff._partials.task-widget')
@include('staff._partials.file-widget')
@endsection
