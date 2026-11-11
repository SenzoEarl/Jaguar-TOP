@extends('layouts.staff')

@section('content')
<div class="mb-4">
  <span class="badge bg-dark mb-1">Protected File Storage</span>
  <h1 class="h2 fw-bold mb-1">{{ $department->label() }} File Vault</h1>
  <p class="text-muted mb-0">
    Authorized controller-streamed downloads only. Non-web storage directory cannot be accessed directly via URL.
  </p>
</div>

<div class="mb-4 d-flex flex-wrap gap-2">
  @foreach($visibleDepartments as $deptOption)
    <a
      href="{{ app_url('/staff/files/' . $deptOption['slug'], $basePath) }}"
      class="btn btn-sm {{ $deptOption['active'] ? 'btn-dark' : 'btn-outline-secondary' }}"
    >
      {{ $deptOption['label'] }}
      @if($deptOption['read_only'])
        <span class="badge bg-warning text-dark ms-1">Read</span>
      @endif
    </a>
  @endforeach
</div>

@include('staff._partials.readonly-banner')
@include('staff._partials.file-widget')
@endsection
