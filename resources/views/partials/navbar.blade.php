<nav class="navbar navbar-expand-lg navbar-dark safari-navbar sticky-top shadow-sm">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="{{ app_url('/', $basePath) }}">
      <img src="{{ app_url('/assets/img/favicon.svg', $basePath) }}" alt="{{ $appName }}" width="28" height="28">
      <span>{{ $appName }}</span>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link" href="{{ app_url('/lodges', $basePath) }}">Lodges</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ app_url('/camps', $basePath) }}">Camps</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ app_url('/accommodation', $basePath) }}">Accommodation</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ app_url('/stays', $basePath) }}">All Stays</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ app_url('/about', $basePath) }}">About</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="{{ app_url('/contact', $basePath) }}">Contact</a>
        </li>
      </ul>

      <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
        <li class="nav-item">
          <a class="btn btn-sm safari-btn-gold" href="{{ app_url('/bookings', $basePath) }}">Book a Stay</a>
        </li>
        @if($currentUser !== null)
          @can('staff.portal.access')
            <li class="nav-item">
              <a class="btn btn-sm btn-outline-light" href="{{ app_url('/staff/dashboard', $basePath) }}">Staff Portal</a>
            </li>
          @endcan
          <li class="nav-item">
            <a class="nav-link" href="{{ app_url('/account/dashboard', $basePath) }}">My Account ({{ $currentUser->getUsername() }})</a>
          </li>
          <li class="nav-item">
            <form method="post" action="{{ app_url('/account/logout', $basePath) }}" class="d-inline">
              @csrf
              <button type="submit" class="btn btn-sm btn-outline-warning">Sign Out</button>
            </form>
          </li>
        @else
          <li class="nav-item">
            <a class="nav-link" href="{{ app_url('/account/login', $basePath) }}">Sign In</a>
          </li>
          <li class="nav-item">
            <a class="btn btn-sm btn-outline-light" href="{{ app_url('/account/register', $basePath) }}">Register</a>
          </li>
        @endif
      </ul>
    </div>
  </div>
</nav>
