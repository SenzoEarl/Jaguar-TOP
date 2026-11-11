@php($deptSlug = $summary->department->value)
@php($readOnlyFlag = $summary->isReadOnly)

<div class="card wc-card mb-4" data-task-widget="true">
  <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 py-3">
    <div>
      <h2 class="h5 fw-bold mb-0">{{ $summary->department->label() }} Operational Tasks</h2>
      <div class="small text-muted">Tracked live in MariaDB <code>tasks</code> table</div>
    </div>
    <div class="btn-group btn-group-sm" role="group" aria-label="Task scope filter">
      <a
        href="{{ app_url('/staff/' . $deptSlug . '/dashboard', $basePath) }}"
        class="btn {{ empty($onlyMine) ? 'btn-dark' : 'btn-outline-secondary' }}"
      >
        Department Queue
      </a>
      <a
        href="{{ app_url('/staff/' . $deptSlug . '/dashboard?filter=mine', $basePath) }}"
        class="btn {{ !empty($onlyMine) ? 'btn-dark' : 'btn-outline-secondary' }}"
      >
        Assigned to Me
      </a>
    </div>
  </div>

  <div class="card-body">
    @if(!$readOnlyFlag)
      <form
        method="post"
        action="{{ app_url('/staff/' . $deptSlug . '/tasks', $basePath) }}"
        data-task-create-form="true"
        data-endpoint="/staff/{{ $deptSlug }}/tasks"
        class="row g-2 align-items-end mb-4 pb-3 border-bottom"
      >
        @csrf
        <div class="col-md-4">
          <label for="taskTitleInput" class="form-label small fw-semibold mb-1">Task Title</label>
          <input
            id="taskTitleInput"
            type="text"
            name="title"
            class="form-control form-control-sm"
            required
            placeholder="e.g. Confirm airport pickup permit..."
          >
        </div>
        <div class="col-md-2">
          <label for="taskPriorityInput" class="form-label small fw-semibold mb-1">Priority</label>
          <select id="taskPriorityInput" name="priority" class="form-select form-select-sm">
            <option value="low">Low</option>
            <option value="medium" selected>Medium</option>
            <option value="high">High</option>
          </select>
        </div>
        <div class="col-md-3">
          <label for="taskAssigneeInput" class="form-label small fw-semibold mb-1">Assign To</label>
          <select id="taskAssigneeInput" name="assigned_to_user_id" class="form-select form-select-sm">
            @foreach($summary->staffMembers as $member)
              <option value="{{ $member->getId() }}" {{ ($currentUser !== null && $currentUser->getId() === $member->getId()) ? 'selected' : '' }}>
                {{ $member->getFullName() }} ({{ $member->getUsername() }})
              </option>
            @endforeach
          </select>
        </div>
        <div class="col-md-2">
          <label for="taskDueInput" class="form-label small fw-semibold mb-1">Due Date</label>
          <input id="taskDueInput" type="date" name="due_date" class="form-control form-control-sm">
        </div>
        <div class="col-md-1 d-grid">
          <button type="submit" class="btn btn-sm wc-btn-primary">Add</button>
        </div>
        <div class="col-12">
          <input
            type="text"
            name="description"
            class="form-control form-control-sm"
            placeholder="Optional operational notes or checklist detail..."
          >
        </div>
      </form>
    @endif

    <div class="d-none" data-task-feedback="true" aria-live="polite"></div>

    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Task</th>
            <th>Assignee</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Due Date</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody data-task-table-body="true">
          @if(empty($summary->tasks))
            <tr data-empty-tasks-row="true">
              <td colspan="6" class="text-center text-muted py-3">
                No tasks match this filter in {{ $summary->department->label() }}.
              </td>
            </tr>
          @else
            @foreach($summary->tasks as $task)
              <tr>
                <td>
                  <strong>{{ $task->getTitle() }}</strong>
                  @if($task->getDescription() !== null)
                    <div class="small text-muted">{{ $task->getDescription() }}</div>
                  @endif
                </td>
                <td class="small text-muted">{{ $task->getAssignedToName() }}</td>
                <td><span class="badge bg-light text-dark">{{ strtoupper($task->getPriority()) }}</span></td>
                <td>
                  <span class="badge {{ $task->getStatus()->badgeClass() }}" data-task-badge="true">
                    {{ $task->getStatus()->label() }}
                  </span>
                </td>
                <td class="small">{{ $task->getDueDate() ?? 'No due date' }}</td>
                <td>
                  @if(!$readOnlyFlag)
                    <select
                      class="form-select form-select-sm"
                      aria-label="Update status for {{ $task->getTitle() }}"
                      data-task-status-select="true"
                      data-endpoint="/staff/tasks/{{ $task->getId() }}/status"
                      data-previous-status="{{ $task->getStatus()->value }}"
                    >
                      @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'blocked' => 'Blocked', 'done' => 'Done'] as $val => $label)
                        <option value="{{ $val }}" {{ $task->getStatus()->value === $val ? 'selected' : '' }}>
                          {{ $label }}
                        </option>
                      @endforeach
                    </select>
                  @else
                    <span class="small text-muted">Read-Only</span>
                  @endif
                </td>
              </tr>
            @endforeach
          @endif
        </tbody>
      </table>
    </div>
  </div>
</div>
