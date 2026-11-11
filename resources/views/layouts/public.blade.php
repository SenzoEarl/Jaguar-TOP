{{-- [SECURITY:XSS] BladeOne default auto-escaping applies to every variable below. --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{ $pageTitle ?? $appName }}</title>
  <meta name="description" content="{{ $metaDescription ?? 'Luxury wilderness lodges, tented camps, and tranquil forest accommodation by ' . $appName }}">
  @if(!empty($robotsNoIndex))
    <meta name="robots" content="noindex, nofollow">
  @else
    <meta name="robots" content="index, follow">
  @endif
  <link rel="canonical" href="{{ rtrim($appUrl, '/') . app_url($canonicalPath ?? '/', '') }}">

  <meta property="og:title" content="{{ $pageTitle ?? $appName }}">
  <meta property="og:description" content="{{ $metaDescription ?? 'Luxury wilderness lodges, tented camps, and serene accommodation.' }}">
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="{{ $appName }}">
  <meta property="og:url" content="{{ rtrim($appUrl, '/') . app_url($canonicalPath ?? '/', '') }}">
  <meta property="og:image" content="{{ rtrim($appUrl, '/') . app_url($ogImage ?? '/assets/img/hero-jaguar-safari.svg', '') }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $pageTitle ?? $appName }}">
  <meta name="twitter:description" content="{{ $metaDescription ?? 'Book luxury lodges, canvas camps, and wilderness stays.' }}">

  {{-- // [SECURITY:CSRF] Meta tags read safely by ES module ApiClient.js (zero inline scripts). --}}
  <meta name="csrf-token" content="{{ $csrfToken }}">
  <meta name="csrf-header" content="{{ $csrfHeaderName }}">
  <meta name="app-base-path" content="{{ $basePath }}">

  <link rel="icon" type="image/svg+xml" href="{{ app_url('/assets/img/favicon.svg', $basePath) }}">
  <link rel="stylesheet" href="{{ app_url('/assets/vendor/bootstrap/bootstrap.min.css', $basePath) }}">
  <link rel="stylesheet" href="{{ app_url('/assets/css/app.css', $basePath) }}">
  @yield('head_extra')
</head>
<body>
  @include('partials.navbar')

  <main>
    <div class="container pt-3">
      @include('partials.flash')
    </div>
    @yield('content')
  </main>

  @include('partials.footer')

  <script src="{{ app_url('/assets/vendor/bootstrap/bootstrap.bundle.min.js', $basePath) }}"></script>
  <script type="module" src="{{ app_url('/assets/js/main.js', $basePath) }}"></script>
</body>
</html>
