/**
 * Enhances catalog search and capacity filtering controls without page-script clutter.
 */
export default class CatalogFilterController {
  /**
   * Bind automatic filter submission and reset button behavior.
   *
   * @param {HTMLFormElement} form Filter form element (`[data-catalog-filter-form]`).
   */
  constructor(form) {
    this.form = form;
    this.guestsSelect = form.querySelector('select[name="guests"]');
    this.searchInput = form.querySelector('input[name="q"]');
    this.resetButton = form.querySelector('[data-reset-filters]');

    this.bindEvents();
  }

  /**
   * Attach change listener on guest selector and click handler on reset button.
   */
  bindEvents() {
    if (this.guestsSelect) {
      this.guestsSelect.addEventListener('change', () => {
        this.form.submit();
      });
    }

    if (this.resetButton) {
      this.resetButton.addEventListener('click', () => {
        if (this.searchInput) {
          this.searchInput.value = '';
        }
        if (this.guestsSelect) {
          this.guestsSelect.value = '';
        }
        this.form.submit();
      });
    }
  }
}
