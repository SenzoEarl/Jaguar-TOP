@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card safari-card p-4 p-md-5 text-center">
        <span class="badge bg-secondary mx-auto mb-3">HTTP {{ $statusCode ?? 404 }}</span>
        <h1 class="h2 fw-bold mb-3">Page Not Found</h1>
        <p class="text-muted mb-4">
          {{ $message ?? 'The lodge, camp, or page you requested could not be found.' }}
        </p>
        @if(!empty($requestId))
          <p class="small text-muted mb-4">Support Reference: <code>{{ $requestId }}</code></p>
        @endif
        <div class="d-flex justify-content-center gap-2 flex-wrap">
          <a href="{{ app_url('/', $basePath) }}" class="btn safari-btn-primary">Return Home</a>
          <a href="{{ app_url('/stays', $basePath) }}" class="btn btn-outline-secondary">Browse All Stays</a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
