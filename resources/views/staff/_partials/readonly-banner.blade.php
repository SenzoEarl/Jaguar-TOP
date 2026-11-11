@php($readOnlyFlag = $isReadOnly ?? ($summary->isReadOnly ?? false))
@if($readOnlyFlag)
  <div class="safari-readonly-banner p-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2" role="status">
    <div>
      <strong>Read-Only Cross-Department View:</strong>
      @if($currentUser !== null && $currentUser->getDepartment() !== null && $currentUser->getDepartment()->isLevelOne())
        As a Level-1 {{ $currentUser->getDepartment()->label() }} member, you can inspect fellow Level-1 teams' queues and documents, while write modifications stay restricted to your own department.
      @else
        Your current oversight level allows reviewing this department's workspace without write modifications here.
      @endif
    </div>
    <span class="badge bg-warning text-dark">Read Access</span>
  </div>
@endif
