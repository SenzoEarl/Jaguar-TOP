@extends('layouts.public')

@section('content')
<section class="container py-5">
  <div class="row g-5">
    <div class="col-lg-5">
      <h1 class="h2 fw-bold mb-3">Contact Reservations &amp; Guest Care</h1>
      <p class="text-muted mb-4">
        Planning a family retreat, group charter, or multi-camp itinerary? Reach our desk directly or send an inquiry below.
      </p>
      <div class="card wc-card p-4">
        <dl class="mb-0 small">
          <dt class="text-muted">Reservations Email</dt>
          <dd class="mb-3 fw-semibold">{{ $contactEmail }}</dd>
          <dt class="text-muted">Direct Telephone</dt>
          <dd class="mb-3 fw-semibold">{{ $contactPhone }}</dd>
          <dt class="text-muted">Operating Region</dt>
          <dd class="mb-0 fw-semibold">{{ $businessLocation }}</dd>
        </dl>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card wc-card p-4">
        <h2 class="h5 fw-bold mb-3">Send an Inquiry</h2>
        <form method="post" action="{{ app_url('/contact', $basePath) }}" novalidate>
          @csrf
          <div class="mb-3">
            <label for="contactName" class="form-label">Full Name</label>
            <input
              id="contactName"
              type="text"
              name="name"
              class="form-control"
              required
              value="{{ $oldInput['name'] ?? '' }}"
            >
          </div>
          <div class="mb-3">
            <label for="contactEmail" class="form-label">Email Address</label>
            <input
              id="contactEmail"
              type="email"
              name="email"
              class="form-control"
              required
              value="{{ $oldInput['email'] ?? '' }}"
            >
          </div>
          <div class="mb-3">
            <label for="contactSubject" class="form-label">Subject</label>
            <input
              id="contactSubject"
              type="text"
              name="subject"
              class="form-control"
              required
              value="{{ $oldInput['subject'] ?? '' }}"
            >
          </div>
          <div class="mb-3">
            <label for="contactMessage" class="form-label">Message</label>
            <textarea
              id="contactMessage"
              name="message"
              rows="4"
              class="form-control"
              required
            >{{ $oldInput['message'] ?? '' }}</textarea>
          </div>
          <button type="submit" class="btn wc-btn-primary">Submit Inquiry</button>
        </form>
      </div>
    </div>
  </div>
</section>
@endsection
