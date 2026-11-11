@if(($totalPages ?? 1) > 1)
  <nav aria-label="Catalog pagination" class="mt-4">
    <ul class="pagination justify-content-center">
      @for($page = 1; $page <= $totalPages; $page++)
        @php
          $queryParams = array_filter([
              'q' => $searchQuery ?? '',
              'guests' => $minGuests ?? '',
              'page' => $page > 1 ? $page : null,
          ], static fn ($v): bool => $v !== '' && $v !== null);
          $querySuffix = $queryParams !== [] ? '?' . http_build_query($queryParams) : '';
        @endphp
        <li class="page-item {{ $page === ($currentPage ?? 1) ? 'active' : '' }}">
          <a class="page-link" href="{{ app_url($canonicalPath, $basePath) . $querySuffix }}">
            {{ $page }}
          </a>
        </li>
      @endfor
    </ul>
  </nav>
@endif
