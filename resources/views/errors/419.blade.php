@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="card wc-card p-4 p-md-5 text-center">
        <span class="badge bg-warning text-dark mx-auto mb-3">HTTP {{ $statusCode ?? 419 }}</span>
        <h1 class="h2 fw-bold mb-3">Security Token Expired</h1>
        <p class="text-muted mb-4">
          {{ $message ?? 'Your form security token expired or did not match your session. Please reload and submit again.' }}
        </p>
        @if(!empty($requestId))
          <p class="small text-muted mb-4">Support Reference: <code>{{ $requestId }}</code></p>
        @endif
        <div class="d-flex justify-content-center gap-2">
          <a href="{{ app_url('/', $basePath) }}" class="btn wc-btn-primary">Return Home</a>
          <a href="{{ app_url('/account/login', $basePath) }}" class="btn btn-outline-secondary">Sign In</a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
