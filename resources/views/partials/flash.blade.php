@if(!empty($flashSuccess))
  <div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ $flashSuccess }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(!empty($flashWarning))
  <div class="alert alert-warning alert-dismissible fade show" role="alert">
    {{ $flashWarning }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(!empty($flashError))
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ $flashError }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
@endif

@if(!empty($validationErrors) && is_array($validationErrors))
  <div class="alert alert-danger" role="alert">
    <strong>Please review the highlighted fields:</strong>
    <ul class="mb-0 mt-1">
      @foreach($validationErrors as $fieldMessages)
        @foreach((array) $fieldMessages as $message)
          <li>{{ $message }}</li>
        @endforeach
      @endforeach
    </ul>
  </div>
@endif
