import DomHelper from '../core/DomHelper.js';

/**
 * Handles staff operational task creation and status transitions via AJAX
 * on writable department dashboards.
 *
 * // [SECURITY:XSS] Renders dynamically appended task rows with textContent only.
 */
export default class TaskManagerController {
  /**
   * Initialize task board DOM hooks.
   *
   * @param {HTMLElement} root Container matching `[data-task-widget]`.
   * @param {import('../core/ApiClient.js').default} apiClient Shared API client.
   */
  constructor(root, apiClient) {
    this.root = root;
    this.apiClient = apiClient;
    this.createForm = root.querySelector('[data-task-create-form]');
    this.tableBody = root.querySelector('[data-task-table-body]');
    this.feedbackBox = root.querySelector('[data-task-feedback]');

    this.bindEvents();
  }

  /**
   * Attach event delegation for status selectors and task creation submission.
   */
  bindEvents() {
    if (this.createForm) {
      this.createForm.addEventListener('submit', (event) => {
        this.handleCreateSubmit(event);
      });
    }

    this.root.addEventListener('change', (event) => {
      const { target } = event;
      if (target instanceof HTMLSelectElement && target.matches('[data-task-status-select]')) {
        this.handleStatusChange(target);
      }
    });
  }

  /**
   * Create a new task asynchronously and insert it into the task table.
   *
   * @param {SubmitEvent} event Native submit event.
   * @returns {Promise<void>} Completion promise.
   */
  async handleCreateSubmit(event) {
    event.preventDefault();
    if (!this.createForm) {
      return;
    }

    const endpoint = this.createForm.dataset.endpoint || '';
    if (endpoint === '') {
      this.createForm.submit();
      return;
    }

    const formData = new FormData(this.createForm);
    const payload = {
      title: String(formData.get('title') || '').trim(),
      description: String(formData.get('description') || '').trim(),
      priority: String(formData.get('priority') || 'medium'),
      assigned_to_user_id: String(formData.get('assigned_to_user_id') || ''),
      due_date: String(formData.get('due_date') || ''),
    };

    if (payload.title.length < 3) {
      DomHelper.setStatusAlert(
        this.feedbackBox,
        'warning',
        'Task title must contain at least 3 characters.',
      );
      return;
    }

    const submitBtn = this.createForm.querySelector('button[type="submit"]');
    if (submitBtn instanceof HTMLButtonElement) {
      submitBtn.disabled = true;
    }

    try {
      const response = await this.apiClient.post(endpoint, payload);
      if (response.task) {
        this.prependTaskRow(response.task);
        this.createForm.reset();
        DomHelper.setStatusAlert(
          this.feedbackBox,
          'success',
          `Created task "${response.task.title}".`,
        );
      }
    } catch (error) {
      DomHelper.setStatusAlert(this.feedbackBox, 'danger', error.message);
    } finally {
      if (submitBtn instanceof HTMLButtonElement) {
        submitBtn.disabled = false;
      }
    }
  }

  /**
   * Persist task status change via POST and update badge styling safely.
   *
   * @param {HTMLSelectElement} select Status select element.
   * @returns {Promise<void>} Completion promise.
   */
  async handleStatusChange(select) {
    const endpoint = select.dataset.endpoint || '';
    const previousStatus = select.dataset.previousStatus || 'todo';
    const nextStatus = select.value;
    const selectRef = select;

    if (endpoint === '') {
      return;
    }

    selectRef.disabled = true;

    try {
      const response = await this.apiClient.post(endpoint, { status: nextStatus });
      const updated = response.task || {};
      selectRef.dataset.previousStatus = updated.status || nextStatus;

      const row = selectRef.closest('tr');
      const badge = row ? row.querySelector('[data-task-badge]') : null;
      if (badge) {
        const badgeClass = updated.statusBadge || 'bg-secondary';
        badge.className = `badge ${badgeClass}`;
        badge.textContent = updated.statusLabel || nextStatus;
      }

      DomHelper.setStatusAlert(
        this.feedbackBox,
        'success',
        `Updated task status to ${updated.statusLabel || nextStatus}.`,
      );
    } catch (error) {
      selectRef.value = previousStatus;
      DomHelper.setStatusAlert(this.feedbackBox, 'danger', error.message);
    } finally {
      selectRef.disabled = false;
    }
  }

  /**
   * Construct and prepend a task row into `[data-task-table-body]` using safe DOM APIs.
   *
   * @param {Record<string, any>} task Task DTO returned by server.
   */
  prependTaskRow(task) {
    if (!this.tableBody) {
      return;
    }

    const emptyRow = this.tableBody.querySelector('[data-empty-tasks-row]');
    if (emptyRow) {
      emptyRow.remove();
    }

    const row = DomHelper.createElement('tr');
    const titleCell = DomHelper.createElement('td');
    const titleStrong = DomHelper.createElement('strong', '', String(task.title || ''));
    titleCell.appendChild(titleStrong);

    const assigneeCell = DomHelper.createElement(
      'td',
      'small text-muted',
      String(task.assignedToName || 'Staff'),
    );

    const priorityText = String(task.priority || 'medium').toUpperCase();
    const priorityCell = DomHelper.createElement('td');
    priorityCell.appendChild(
      DomHelper.createElement('span', 'badge bg-light text-dark', priorityText),
    );

    const badgeClass = String(task.statusBadge || 'bg-secondary');
    const statusCell = DomHelper.createElement('td');
    statusCell.appendChild(
      DomHelper.createElement(
        'span',
        `badge ${badgeClass}`,
        String(task.statusLabel || 'To Do'),
        { 'data-task-badge': 'true' },
      ),
    );

    const dueCell = DomHelper.createElement(
      'td',
      'small',
      task.dueDate ? String(task.dueDate) : 'No due date',
    );

    const actionCell = DomHelper.createElement('td');
    const select = DomHelper.createElement('select', 'form-select form-select-sm', '', {
      'aria-label': 'Task status',
      'data-task-status-select': 'true',
      'data-endpoint': `/staff/tasks/${task.id}/status`,
      'data-previous-status': String(task.status || 'todo'),
    });

    const statuses = [
      ['todo', 'To Do'],
      ['in_progress', 'In Progress'],
      ['blocked', 'Blocked'],
      ['done', 'Done'],
    ];

    statuses.forEach(([value, label]) => {
      const option = DomHelper.createElement('option', '', label, { value });
      if (value === task.status) {
        option.setAttribute('selected', 'selected');
      }
      select.appendChild(option);
    });

    actionCell.appendChild(select);
    row.append(titleCell, assigneeCell, priorityCell, statusCell, dueCell, actionCell);
    this.tableBody.prepend(row);
  }
}
