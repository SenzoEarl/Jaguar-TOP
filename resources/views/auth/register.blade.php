@php($robotsNoIndex = true)
@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-6">
      <div class="card safari-card p-4 p-md-5">
        <h1 class="h3 fw-bold mb-2">Create Guest Account</h1>
        <p class="text-muted small mb-4">
          Register for direct lodge reservations, booking confirmations, and stay history.
        </p>

        <form method="post" action="{{ app_url('/account/register', $basePath) }}" novalidate>
          @csrf
          <div class="mb-3">
            <label for="regFullName" class="form-label">Full Name</label>
            <input
              id="regFullName"
              type="text"
              name="full_name"
              class="form-control"
              autocomplete="name"
              required
              value="{{ $oldInput['full_name'] ?? '' }}"
            >
          </div>
          <div class="row g-3 mb-3">
            <div class="col-sm-6">
              <label for="regUsername" class="form-label">Username</label>
              <input
                id="regUsername"
                type="text"
                name="username"
                class="form-control"
                autocomplete="username"
                required
                value="{{ $oldInput['username'] ?? '' }}"
              >
              <div class="form-text">3-50 letters, numbers, or underscores.</div>
            </div>
            <div class="col-sm-6">
              <label for="regEmail" class="form-label">Email Address</label>
              <input
                id="regEmail"
                type="email"
                name="email"
                class="form-control"
                autocomplete="email"
                required
                value="{{ $oldInput['email'] ?? '' }}"
              >
            </div>
          </div>
          <div class="row g-3 mb-4">
            <div class="col-sm-6">
              <label for="regPassword" class="form-label">Password</label>
              <input
                id="regPassword"
                type="password"
                name="password"
                class="form-control"
                autocomplete="new-password"
                required
              >
              <div class="form-text">Minimum 8 characters.</div>
            </div>
            <div class="col-sm-6">
              <label for="regPasswordConfirm" class="form-label">Confirm Password</label>
              <input
                id="regPasswordConfirm"
                type="password"
                name="password_confirmation"
                class="form-control"
                autocomplete="new-password"
                required
              >
            </div>
          </div>
          <button type="submit" class="btn safari-btn-primary w-100 mb-3">Create Account</button>
        </form>

        <div class="text-center small text-muted">
          Already registered? <a href="{{ app_url('/account/login', $basePath) }}">Sign in here</a>
        </div>
      </div>
    </div>
  </div>
</section>
@endsection
