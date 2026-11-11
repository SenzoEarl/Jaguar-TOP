{{-- [SECURITY:XSS] Staff layout uses noindex and strict BladeOne HTML escaping. --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $pageTitle ?? ('Staff Portal | ' . $appName) }}</title>
  <meta name="robots" content="noindex, nofollow">

  {{-- // [SECURITY:CSRF] Runtime CSRF metadata for staff AJAX modules. --}}
  <meta name="csrf-token" content="{{ $csrfToken }}">
  <meta name="csrf-header" content="{{ $csrfHeaderName }}">
  <meta name="app-base-path" content="{{ $basePath }}">

  <link rel="icon" type="image/svg+xml" href="{{ app_url('/assets/img/favicon.svg', $basePath) }}">
  <link rel="stylesheet" href="{{ app_url('/assets/vendor/bootstrap/bootstrap.min.css', $basePath) }}">
  <link rel="stylesheet" href="{{ app_url('/assets/css/app.css', $basePath) }}">
</head>
<body>
  <header class="navbar navbar-expand-lg navbar-dark wc-navbar border-bottom border-secondary">
    <div class="container-fluid px-3">
      <a class="navbar-brand d-flex align-items-center gap-2" href="{{ app_url('/staff/dashboard', $basePath) }}">
        <img src="{{ app_url('/assets/img/favicon.svg', $basePath) }}" alt="{{ $appName }}" width="26" height="26">
        <span>{{ $appName }} — Staff Portal</span>
      </a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#staffTopNav" aria-controls="staffTopNav" aria-expanded="false" aria-label="Toggle staff navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="staffTopNav">
        <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
          <li class="nav-item">
            <a class="nav-link" href="{{ app_url('/', $basePath) }}">Public Site</a>
          </li>
          @if($currentUser !== null)
            <li class="nav-item">
              <span class="navbar-text text-light small px-2">
                {{ $currentUser->getFullName() }}
                <span class="badge bg-warning text-dark ms-1">
                  {{ $currentUser->getRole()->label() }} (L{{ $currentUser->getRole()->value }})
                </span>
              </span>
            </li>
            <li class="nav-item">
              <form method="post" action="{{ app_url('/account/logout', $basePath) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-warning">Sign Out</button>
              </form>
            </li>
          @endif
        </ul>
      </div>
    </div>
  </header>

  <div class="container-fluid">
    <div class="row wc-staff-shell">
      <aside class="col-lg-3 col-xl-2 py-4 px-3 wc-staff-sidebar">
        @include('partials.staff-sidebar')
      </aside>
      <main class="col-lg-9 col-xl-10 py-4 px-4">
        @include('partials.flash')
        @yield('content')
      </main>
    </div>
  </div>

  <script src="{{ app_url('/assets/vendor/bootstrap/bootstrap.bundle.min.js', $basePath) }}"></script>
  <script type="module" src="{{ app_url('/assets/js/main.js', $basePath) }}"></script>
</body>
</html>
