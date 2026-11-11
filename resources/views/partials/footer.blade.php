<footer class="safari-footer py-5 mt-5">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-5">
        <h5 class="text-white mb-2">{{ $appName }}</h5>
        <p class="small mb-2">
          Thoughtfully sited wilderness lodges, canvas expedition camps, and private cedar cabins across {{ $businessLocation }}.
        </p>
        <p class="small mb-0">
          Reservations Desk: <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a> &middot; {{ $contactPhone }}
        </p>
      </div>
      <div class="col-md-3">
        <h6 class="text-white text-uppercase small fw-bold mb-2">Explore Stays</h6>
        <ul class="list-unstyled small mb-0">
          <li class="mb-1"><a href="{{ app_url('/lodges', $basePath) }}">Wilderness Lodges</a></li>
          <li class="mb-1"><a href="{{ app_url('/camps', $basePath) }}">Tented Bush Camps</a></li>
          <li class="mb-1"><a href="{{ app_url('/accommodation', $basePath) }}">Cabins &amp; Suites</a></li>
          <li><a href="{{ app_url('/bookings', $basePath) }}">Check Availability</a></li>
        </ul>
      </div>
      <div class="col-md-4">
        <h6 class="text-white text-uppercase small fw-bold mb-2">Guest &amp; Staff Portals</h6>
        <ul class="list-unstyled small mb-0">
          <li class="mb-1"><a href="{{ app_url('/about', $basePath) }}">Our Conservation Ethos</a></li>
          <li class="mb-1"><a href="{{ app_url('/contact', $basePath) }}">Contact Concierge</a></li>
          <li class="mb-1"><a href="{{ app_url('/account/login', $basePath) }}">Guest &amp; Staff Sign In</a></li>
          <li><a href="{{ app_url('/sitemap.xml', $basePath) }}">XML Sitemap</a></li>
        </ul>
      </div>
    </div>
    <hr class="border-secondary my-4">
    <div class="d-flex flex-column flex-sm-row justify-content-between small opacity-75">
      <span>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</span>
      <span>Built on strict PDO prepared queries, Argon2id &amp; RBAC access controls.</span>
    </div>
  </div>
</footer>
