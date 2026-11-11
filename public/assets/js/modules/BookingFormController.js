import DomHelper from '../core/DomHelper.js';
import FormValidator from '../core/FormValidator.js';

/**
 * Manages interactive availability checks, live rate previews, and
 * validated reservation submission on the booking page.
 */
export default class BookingFormController {
  /**
   * Bind DOM listeners for the booking form container.
   *
   * @param {HTMLFormElement} form Target booking `<form data-booking-form>`.
   * @param {import('../core/ApiClient.js').default} apiClient Central API client.
   */
  constructor(form, apiClient) {
    this.form = form;
    this.apiClient = apiClient;
    this.productSelect = form.querySelector('select[name="product_id"]');
    this.checkInInput = form.querySelector('input[name="check_in_date"]');
    this.checkOutInput = form.querySelector('input[name="check_out_date"]');
    this.guestsInput = form.querySelector('input[name="guests_count"]');
    this.estimateBox = document.querySelector('[data-price-estimate]');
    this.statusBox = document.querySelector('[data-availability-status]');
    this.checkButton = form.querySelector('[data-check-availability]');
    this.submitButton = form.querySelector('button[type="submit"]');
    this.currency = form.dataset.currency || 'USD';

    this.bindEvents();
    this.updateEstimate();
  }

  /**
   * Attach change, availability-check, and submit listeners.
   */
  bindEvents() {
    const inputs = [
      this.productSelect,
      this.checkInInput,
      this.checkOutInput,
      this.guestsInput,
    ];

    inputs.forEach((input) => {
      if (input) {
        input.addEventListener('change', () => {
          this.syncMaxGuestsHint();
          this.updateEstimate();
          DomHelper.clearStatusAlert(this.statusBox);
        });
      }
    });

    if (this.checkButton) {
      this.checkButton.addEventListener('click', () => {
        this.handleAvailabilityCheck();
      });
    }

    this.form.addEventListener('submit', (event) => {
      this.handleSubmit(event);
    });
  }

  /**
   * Read currently selected product option price and guest capacity.
   *
   * @returns {{ productId: string, price: number, maxGuests: number|null }} Selected details.
   */
  selectedProductMeta() {
    if (!this.productSelect || this.productSelect.selectedIndex < 0) {
      return { productId: '', price: 0, maxGuests: null };
    }

    const option = this.productSelect.options[this.productSelect.selectedIndex];
    const price = Number.parseFloat(option.dataset.price || '0');
    const maxGuestsRaw = Number.parseInt(option.dataset.capacity || '', 10);

    return {
      productId: option.value,
      price: Number.isFinite(price) ? price : 0,
      maxGuests: Number.isInteger(maxGuestsRaw) ? maxGuestsRaw : null,
    };
  }

  /**
   * Synchronize guests input `max` attribute with selected unit capacity.
   */
  syncMaxGuestsHint() {
    const meta = this.selectedProductMeta();
    if (this.guestsInput && meta.maxGuests !== null) {
      this.guestsInput.max = String(meta.maxGuests);
    }
  }

  /**
   * Recompute client-side estimated nights and stay total for quick feedback.
   */
  updateEstimate() {
    if (!this.estimateBox) {
      return;
    }

    const meta = this.selectedProductMeta();
    const checkIn = this.checkInInput ? this.checkInInput.value : '';
    const checkOut = this.checkOutInput ? this.checkOutInput.value : '';

    if (
      !meta.productId
      || !FormValidator.isIsoDate(checkIn)
      || !FormValidator.isIsoDate(checkOut)
      || checkOut <= checkIn
    ) {
      this.estimateBox.textContent = 'Select a property and dates to preview estimated stay total.';
      return;
    }

    const startMs = Date.parse(`${checkIn}T00:00:00Z`);
    const endMs = Date.parse(`${checkOut}T00:00:00Z`);
    const nights = Math.round((endMs - startMs) / 86400000);

    if (!Number.isInteger(nights) || nights <= 0 || meta.price <= 0) {
      this.estimateBox.textContent = 'Enter valid dates to preview rate.';
      return;
    }

    const estimated = (nights * meta.price).toFixed(2);
    this.estimateBox.textContent = (
      `Estimated ${nights} night(s) at ${this.currency} ${meta.price.toFixed(2)}/night`
      + ` = ${this.currency} ${estimated} (authoritative total verified on submit).`
    );
  }

  /**
   * Request live date availability from `/bookings/availability` via AJAX.
   *
   * @returns {Promise<void>} Completion promise.
   */
  async handleAvailabilityCheck() {
    const meta = this.selectedProductMeta();
    const values = {
      productId: meta.productId,
      checkInDate: this.checkInInput ? this.checkInInput.value : '',
      checkOutDate: this.checkOutInput ? this.checkOutInput.value : '',
      guestsCount: this.guestsInput ? this.guestsInput.value : '1',
    };

    const clientError = FormValidator.validateBookingInputs(values, meta.maxGuests);
    if (clientError) {
      DomHelper.setStatusAlert(this.statusBox, 'warning', clientError);
      return;
    }

    if (this.checkButton) {
      this.checkButton.disabled = true;
    }
    DomHelper.setStatusAlert(this.statusBox, 'info', 'Checking real-time availability...');

    try {
      const response = await this.apiClient.post('/bookings/availability', {
        product_id: values.productId,
        check_in_date: values.checkInDate,
        check_out_date: values.checkOutDate,
        guests_count: values.guestsCount,
      });

      const quote = response.data || {};
      const variant = quote.available ? 'success' : 'danger';
      DomHelper.setStatusAlert(
        this.statusBox,
        variant,
        quote.message || 'Availability status retrieved.',
      );
    } catch (error) {
      DomHelper.setStatusAlert(this.statusBox, 'danger', error.message);
    } finally {
      if (this.checkButton) {
        this.checkButton.disabled = false;
      }
    }
  }

  /**
   * Validate inputs on submit and prevent duplicate double-clicks.
   *
   * @param {SubmitEvent} event Native submit event.
   */
  handleSubmit(event) {
    const meta = this.selectedProductMeta();
    const values = {
      productId: meta.productId,
      checkInDate: this.checkInInput ? this.checkInInput.value : '',
      checkOutDate: this.checkOutInput ? this.checkOutInput.value : '',
      guestsCount: this.guestsInput ? this.guestsInput.value : '1',
    };

    const clientError = FormValidator.validateBookingInputs(values, meta.maxGuests);
    if (clientError) {
      event.preventDefault();
      DomHelper.setStatusAlert(this.statusBox, 'danger', clientError);
      return;
    }

    if (this.submitButton) {
      this.submitButton.disabled = true;
      this.submitButton.textContent = 'Submitting reservation...';
    }
  }
}
