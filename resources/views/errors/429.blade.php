@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card safari-card p-4 p-md-5 text-center">
        <span class="badge bg-warning text-dark mx-auto mb-3">HTTP {{ $statusCode ?? 429 }}</span>
        <h1 class="h2 fw-bold mb-3">Too Many Attempts</h1>
        {{-- [SECURITY:BRUTE-FORCE] Safe backoff message without account-existence leakage. --}}
        <p class="text-muted mb-4">
          {{ $message ?? 'Too many rapid authentication attempts were detected. Please wait a few minutes before retrying.' }}
        </p>
        @if(!empty($requestId))
          <p class="small text-muted mb-4">Support Reference: <code>{{ $requestId }}</code></p>
        @endif
        <div>
          <a href="{{ app_url('/', $basePath) }}" class="btn safari-btn-primary">Return Home</a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
