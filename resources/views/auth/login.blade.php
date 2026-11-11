@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card wc-card p-4 p-md-5">
        <h1 class="h3 fw-bold mb-2">Sign In</h1>
        <p class="text-muted small mb-4">
          Sign in with your username or email address to manage bookings or enter the staff portal.
        </p>

        <form method="post" action="{{ app_url('/account/login', $basePath) }}" novalidate>
          @csrf
          <div class="mb-3">
            <label for="loginIdentifier" class="form-label">Username or Email</label>
            <input
              id="loginIdentifier"
              type="text"
              name="identifier"
              class="form-control"
              autocomplete="username"
              required
              value="{{ $oldInput['identifier'] ?? '' }}"
            >
          </div>
          <div class="mb-4">
            <label for="loginPassword" class="form-label">Password</label>
            <input
              id="loginPassword"
              type="password"
              name="password"
              class="form-control"
              autocomplete="current-password"
              required
            >
          </div>
          <button type="submit" class="btn wc-btn-primary w-100 mb-3">Sign In</button>
        </form>

        <div class="text-center small text-muted">
          New guest? <a href="{{ app_url('/account/register', $basePath) }}">Create a customer account</a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
