import DomHelper from '../core/DomHelper.js';
import FormValidator from '../core/FormValidator.js';

/**
 * Validates and uploads department vault documents asynchronously while
 * keeping newly uploaded items cleanly rendered without `innerHTML`.
 *
 * // [SECURITY:XSS] Appends uploaded file rows exclusively with textContent.
 */
export default class FileManagerController {
  /**
   * Bind listeners to a file vault widget container.
   *
   * @param {HTMLElement} root Container matching `[data-file-upload-widget]`.
   * @param {import('../core/ApiClient.js').default} apiClient Central API client.
   */
  constructor(root, apiClient) {
    this.root = root;
    this.apiClient = apiClient;
    this.form = root.querySelector('[data-file-upload-form]');
    this.fileInput = root.querySelector('input[type="file"][name="document"]');
    this.tableBody = root.querySelector('[data-file-table-body]');
    this.feedbackBox = root.querySelector('[data-file-feedback]');

    this.maxBytes = this.form
      ? Number.parseInt(this.form.dataset.maxBytes || '0', 10)
      : 0;
    this.allowedExtensions = this.form && this.form.dataset.allowedExtensions
      ? this.form.dataset.allowedExtensions
        .split(',')
        .map((item) => item.trim().toLowerCase())
        .filter((item) => item !== '')
      : [];

    this.bindEvents();
  }

  /**
   * Attach file selection validation and AJAX upload handlers.
   */
  bindEvents() {
    if (this.fileInput) {
      this.fileInput.addEventListener('change', () => {
        const file = this.fileInput && this.fileInput.files ? this.fileInput.files[0] : null;
        const validationError = FormValidator.validateFileSelection(
          file,
          this.maxBytes,
          this.allowedExtensions,
        );
        if (validationError) {
          DomHelper.setStatusAlert(this.feedbackBox, 'warning', validationError);
        } else {
          DomHelper.clearStatusAlert(this.feedbackBox);
        }
      });
    }

    if (this.form) {
      this.form.addEventListener('submit', (event) => {
        this.handleUploadSubmit(event);
      });
    }
  }

  /**
   * Validate and upload selected document via FormData POST.
   *
   * @param {SubmitEvent} event Native submit event.
   * @returns {Promise<void>} Completion promise.
   */
  async handleUploadSubmit(event) {
    event.preventDefault();
    if (!this.form || !this.fileInput) {
      return;
    }

    const file = this.fileInput.files ? this.fileInput.files[0] : null;
    const validationError = FormValidator.validateFileSelection(
      file,
      this.maxBytes,
      this.allowedExtensions,
    );

    if (validationError) {
      DomHelper.setStatusAlert(this.feedbackBox, 'danger', validationError);
      return;
    }

    const endpoint = this.form.dataset.endpoint || '';
    if (endpoint === '') {
      this.form.submit();
      return;
    }

    const formData = new FormData(this.form);
    const submitButton = this.form.querySelector('button[type="submit"]');
    if (submitButton instanceof HTMLButtonElement) {
      submitButton.disabled = true;
    }
    DomHelper.setStatusAlert(this.feedbackBox, 'info', 'Scanning and storing document...');

    try {
      const response = await this.apiClient.post(endpoint, formData);
      if (response.file) {
        this.prependFileRow(response.file);
        this.form.reset();
        DomHelper.setStatusAlert(
          this.feedbackBox,
          'success',
          `Stored "${response.file.originalName}" securely.`,
        );
      }
    } catch (error) {
      DomHelper.setStatusAlert(this.feedbackBox, 'danger', error.message);
    } finally {
      if (submitButton instanceof HTMLButtonElement) {
        submitButton.disabled = false;
      }
    }
  }

  /**
   * Prepend a newly uploaded document row into `[data-file-table-body]`.
   *
   * @param {Record<string, any>} file Uploaded file metadata DTO.
   */
  prependFileRow(file) {
    if (!this.tableBody) {
      return;
    }

    const emptyRow = this.tableBody.querySelector('[data-empty-files-row]');
    if (emptyRow) {
      emptyRow.remove();
    }

    const row = DomHelper.createElement('tr');
    const nameCell = DomHelper.createElement('td');
    nameCell.appendChild(
      DomHelper.createElement('span', 'fw-semibold', String(file.originalName || '')),
    );

    const mimeCell = DomHelper.createElement(
      'td',
      'small text-muted',
      String(file.mimeType || ''),
    );
    const sizeCell = DomHelper.createElement(
      'td',
      'small',
      String(file.sizeFormatted || ''),
    );

    const actionCell = DomHelper.createElement('td', 'text-end');
    const downloadLink = DomHelper.createElement(
      'a',
      'btn btn-sm btn-outline-primary',
      'Download',
      {
        href: this.apiClient.buildUrl(`/staff/files/download/${file.id}`),
      },
    );
    actionCell.appendChild(downloadLink);

    row.append(nameCell, mimeCell, sizeCell, actionCell);
    this.tableBody.prepend(row);
  }
}
