@php($vaultDepartment = $department ?? $summary->department)
@php($vaultFiles = $files ?? $summary->files)
@php($vaultReadOnly = $isReadOnly ?? ($summary->isReadOnly ?? false))

<div class="card wc-card mb-4" data-file-upload-widget="true">
  <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 py-3">
    <div>
      <h2 class="h5 fw-bold mb-0">{{ $vaultDepartment->label() }} Protected Document Vault</h2>
      <div class="small text-muted">
        Stored outside webroot in <code>storage/departments/{{ $vaultDepartment->value }}/</code>
      </div>
    </div>
    <a href="{{ app_url('/staff/files/' . $vaultDepartment->value, $basePath) }}" class="btn btn-sm btn-outline-secondary">
      Open Dedicated Vault
    </a>
  </div>

  <div class="card-body">
    @if(!$vaultReadOnly)
      <form
        method="post"
        enctype="multipart/form-data"
        action="{{ app_url('/staff/files/' . $vaultDepartment->value . '/upload', $basePath) }}"
        data-file-upload-form="true"
        data-endpoint="/staff/files/{{ $vaultDepartment->value }}/upload"
        data-max-bytes="{{ $maxUploadBytes }}"
        data-allowed-extensions="{{ implode(',', $allowedExtensions ?? []) }}"
        class="row g-2 align-items-end mb-4 pb-3 border-bottom"
      >
        @csrf
        <div class="col-md-5">
          <label for="vaultFileInput" class="form-label small fw-semibold mb-1">Select Document</label>
          <input id="vaultFileInput" type="file" name="document" class="form-control form-control-sm" required>
        </div>
        <div class="col-md-5">
          <label for="vaultFileDesc" class="form-label small fw-semibold mb-1">Description (optional)</label>
          <input
            id="vaultFileDesc"
            type="text"
            name="description"
            class="form-control form-control-sm"
            placeholder="Inspection certificate, rate sheet, brief..."
          >
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-sm wc-btn-primary">Upload</button>
        </div>
        <div class="col-12 small text-muted">
          Allowed: {{ implode(', ', $allowedExtensions ?? []) }} &middot; Max {{ number_format(($maxUploadBytes ?? 5242880) / 1048576, 1) }} MB (server checks real MIME via finfo)
        </div>
      </form>
    @endif

    <div class="d-none" data-file-feedback="true" aria-live="polite"></div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Original Filename</th>
            <th>MIME Type</th>
            <th>Size</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody data-file-table-body="true">
          @if(empty($vaultFiles))
            <tr data-empty-files-row="true">
              <td colspan="4" class="text-center text-muted py-3">
                No documents uploaded to {{ $vaultDepartment->label() }} yet.
              </td>
            </tr>
          @else
            @foreach($vaultFiles as $file)
              <tr>
                <td>
                  <span class="fw-semibold">{{ $file->getOriginalName() }}</span>
                  @if($file->getDescription() !== null)
                    <div class="small text-muted">{{ $file->getDescription() }}</div>
                  @endif
                </td>
                <td class="small text-muted">{{ $file->getMimeType() }}</td>
                <td class="small">{{ $file->formattedSize() }}</td>
                <td class="text-end">
                  <a href="{{ app_url('/staff/files/download/' . $file->getId(), $basePath) }}" class="btn btn-sm btn-outline-primary">
                    Download
                  </a>
                </td>
              </tr>
            @endforeach
          @endif
        </tbody>
      </table>
    </div>
  </div>
</div>
