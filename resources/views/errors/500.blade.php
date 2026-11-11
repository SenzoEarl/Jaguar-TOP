@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card safari-card p-4 p-md-5 text-center">
        <span class="badge bg-danger mx-auto mb-3">HTTP {{ $statusCode ?? 500 }}</span>
        <h1 class="h2 fw-bold mb-3">Unexpected System Error</h1>
        {{-- [SECURITY:ERRORS] Never expose SQL queries, file paths, or stack traces in HTML. --}}
        <p class="text-muted mb-4">
          {{ $message ?? 'Something went wrong while completing your request. Our team has logged this incident.' }}
        </p>
        @if(!empty($requestId))
          <p class="small text-muted mb-4">Incident Reference: <code>{{ $requestId }}</code></p>
        @endif
        <div>
          <a href="{{ app_url('/', $basePath) }}" class="btn safari-btn-primary">Return Home</a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
