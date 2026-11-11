/**
 * Client-side form validation utility providing instant UX feedback
 * while mirroring authoritative server-side validation rules.
 */
export default class FormValidator {
  /**
   * Validate an ISO `YYYY-MM-DD` date string against today or a comparison date.
   *
   * @param {string} dateValue ISO date string.
   * @returns {boolean} True when format is valid `YYYY-MM-DD`.
   */
  static isIsoDate(dateValue) {
    return /^\d{4}-\d{2}-\d{2}$/.test(dateValue.trim());
  }

  /**
   * Validate a booking check-in / check-out / guest payload.
   *
   * @param {object} values Form field values.
   * @param {string} values.productId Selected property ID string.
   * @param {string} values.checkInDate ISO check-in date string.
   * @param {string} values.checkOutDate ISO check-out date string.
   * @param {string} values.guestsCount Guest count string.
   * @param {number|null} [maxGuests] Optional property maximum guest limit.
   * @returns {string|null} First validation error message, or null when valid.
   */
  static validateBookingInputs(values, maxGuests = null) {
    const productId = Number.parseInt(String(values.productId || '0'), 10);
    if (!Number.isInteger(productId) || productId < 1) {
      return 'Please select a lodge, camp, or accommodation first.';
    }

    if (!FormValidator.isIsoDate(values.checkInDate)) {
      return 'Please enter a valid check-in date.';
    }

    if (!FormValidator.isIsoDate(values.checkOutDate)) {
      return 'Please enter a valid check-out date.';
    }

    const today = new Date();
    const todayIso = [
      today.getFullYear(),
      String(today.getMonth() + 1).padStart(2, '0'),
      String(today.getDate()).padStart(2, '0'),
    ].join('-');

    if (values.checkInDate < todayIso) {
      return 'Check-in date cannot be in the past.';
    }

    if (values.checkOutDate <= values.checkInDate) {
      return 'Check-out date must be at least one night after check-in.';
    }

    const guests = Number.parseInt(String(values.guestsCount || '0'), 10);
    if (!Number.isInteger(guests) || guests < 1) {
      return 'Guest count must be at least 1.';
    }

    if (maxGuests !== null && guests > maxGuests) {
      return `Selected property supports at most ${maxGuests} guest(s).`;
    }

    return null;
  }

  /**
   * Validate a client-side file selection against extension and size constraints.
   *
   * @param {File|null} file Selected browser File object.
   * @param {number} maxBytes Maximum permitted byte length.
   * @param {string[]} allowedExtensions Lowercase extensions without dots.
   * @returns {string|null} Error message or null when valid.
   */
  static validateFileSelection(file, maxBytes, allowedExtensions) {
    if (!file) {
      return 'Please choose a document file to upload.';
    }

    if (file.size <= 0) {
      return 'Selected file is empty.';
    }

    if (maxBytes > 0 && file.size > maxBytes) {
      const maxMb = (maxBytes / (1024 * 1024)).toFixed(1);
      return `File exceeds the maximum allowed limit of ${maxMb} MB.`;
    }

    const dotIndex = file.name.lastIndexOf('.');
    const ext = dotIndex >= 0 ? file.name.slice(dotIndex + 1).toLowerCase() : '';
    if (allowedExtensions.length > 0 && !allowedExtensions.includes(ext)) {
      return `Unsupported file type. Allowed: ${allowedExtensions.join(', ')}.`;
    }

    return null;
  }
}
