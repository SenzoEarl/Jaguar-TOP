@foreach($sidebarGroups ?? [] as $group)
  <div class="mb-4">
    <div class="text-uppercase text-muted small fw-bold px-2 mb-2">
      {{ $group['heading'] }}
    </div>
    <ul class="nav flex-column">
      @foreach($group['items'] as $item)
        <li class="nav-item">
          <a
            href="{{ $item['url'] }}"
            class="nav-link d-flex justify-content-between align-items-center {{ $item['active'] ? 'active' : '' }}"
          >
            <span>{{ $item['label'] }}</span>
            @if(!empty($item['read_only']))
              <span class="badge bg-warning text-dark">Read</span>
            @elseif(!empty($item['badge_text']))
              <span class="badge bg-secondary">{{ $item['badge_text'] }}</span>
            @endif
          </a>
        </li>
      @endforeach
    </ul>
  </div>
@endforeach
