@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card safari-card p-4 p-md-5 text-center">
        <span class="badge bg-danger mx-auto mb-3">HTTP {{ $statusCode ?? 403 }}</span>
        <h1 class="h2 fw-bold mb-3">Access Denied</h1>
        {{-- [SECURITY:ERRORS] Safe generic message; full diagnostics recorded only in logs. --}}
        <p class="text-muted mb-4">
          {{ $message ?? 'You do not have permission to access this resource or department section.' }}
        </p>
        @if(!empty($requestId))
          <p class="small text-muted mb-4">Support Reference: <code>{{ $requestId }}</code></p>
        @endif
        <div class="d-flex justify-content-center gap-2 flex-wrap">
          <a href="{{ app_url('/', $basePath) }}" class="btn safari-btn-primary">Return Home</a>
          @can('staff.portal.access')
            <a href="{{ app_url('/staff/dashboard', $basePath) }}" class="btn btn-outline-secondary">My Staff Workspace</a>
          @endcan
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
