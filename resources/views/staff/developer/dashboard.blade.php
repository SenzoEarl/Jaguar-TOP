@extends('layouts.staff')

@section('content')
<div class="mb-4">
  <span class="badge bg-danger mb-1">Level 6 &middot; Developer / Root</span>
  <h1 class="h2 fw-bold mb-1">{{ $summary->headline }}</h1>
  <p class="text-muted mb-0">{{ $summary->subtitle }}</p>
</div>

@include('staff._partials.readonly-banner')

<div class="row g-3 mb-4">
  @foreach($summary->metrics as $metric)
    <div class="col-sm-6 col-xl-3">
      <div class="card safari-card safari-metric-card p-3 h-100">
        <span class="small text-muted fw-semibold">{{ $metric['title'] }}</span>
        <div class="fs-4 fw-bold mt-2">{{ $metric['value'] }}</div>
        <div class="small text-muted mt-1">{{ $metric['detail'] }}</div>
      </div>
    </div>
  @endforeach
</div>

@can('system.diagnostics.view')
  <div class="row g-4 mb-4">
    <div class="col-lg-5">
      <div class="card safari-card p-4 h-100">
        <h2 class="h5 fw-bold mb-3">Runtime &amp; Seam Inventory</h2>
        <dl class="row small mb-0">
          <dt class="col-sm-5 text-muted">Environment</dt>
          <dd class="col-sm-7"><code>{{ $summary->systemDiagnostics['app_env'] ?? 'local' }}</code></dd>
          <dt class="col-sm-5 text-muted">PHP Version</dt>
          <dd class="col-sm-7"><code>{{ $summary->systemDiagnostics['php_version'] ?? PHP_VERSION }}</code></dd>
          <dt class="col-sm-5 text-muted">Active Modules</dt>
          <dd class="col-sm-7">
            @foreach(($summary->systemDiagnostics['modules'] ?? []) as $modName)
              <span class="badge bg-light text-dark border mb-1">{{ $modName }}</span>
            @endforeach
          </dd>
          <dt class="col-sm-5 text-muted">RBAC Map</dt>
          <dd class="col-sm-7">Explicit levels L0..L6 + L1 cross-read via <code>AccessControl</code></dd>
        </dl>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card safari-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 fw-bold mb-0">Sanitized Application Log Tail (Placeholder Viewer)</h2>
          <span class="badge bg-dark">Last 12 Lines</span>
        </div>
        @if(empty($summary->systemDiagnostics['log_lines']))
          <p class="small text-muted mb-0">No log entries recorded yet in <code>storage/logs/</code>.</p>
        @else
          <div class="safari-log-tail">@foreach($summary->systemDiagnostics['log_lines'] as $logLine){{ $logLine . "\n" }}@endforeach</div>
        @endif
      </div>
    </div>
  </div>
@endcan

@include('staff._partials.task-widget')
@include('staff._partials.file-widget')
@endsection
