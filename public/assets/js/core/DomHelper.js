/**
 * Safe DOM manipulation helper utilities.
 *
 * // [SECURITY:XSS] All dynamic user or server-returned strings MUST be inserted via
 * textContent / replaceChildren, never innerHTML or insertAdjacentHTML.
 */
export default class DomHelper {
  /**
   * Read the trimmed value of a named `<meta>` tag from `document.head`.
   *
   * @param {string} name Meta tag `name` attribute.
   * @param {string} fallback Default value when absent.
   * @returns {string} Meta content value or fallback.
   */
  static getMeta(name, fallback = '') {
    const el = document.querySelector(`meta[name="${name}"]`);
    if (!el) {
      return fallback;
    }

    const content = el.getAttribute('content');
    return content !== null ? content.trim() : fallback;
  }

  /**
   * Create a DOM element safely using `textContent` and explicit attributes.
   *
   * @param {string} tag HTML tag name.
   * @param {string} className CSS classes string.
   * @param {string} text Optional plain text assigned via textContent.
   * @param {Record<string, string>} attributes Optional element attributes.
   * @returns {HTMLElement} Constructed element.
   */
  static createElement(tag, className = '', text = '', attributes = {}) {
    const element = document.createElement(tag);

    if (className !== '') {
      element.className = className;
    }

    if (text !== '') {
      // // [SECURITY:XSS] Always assign dynamic text with textContent.
      element.textContent = text;
    }

    Object.entries(attributes).forEach(([key, value]) => {
      element.setAttribute(key, String(value));
    });

    return element;
  }

  /**
   * Update a Bootstrap alert container safely without touching HTML markup.
   *
   * @param {HTMLElement|null} container Target alert box.
   * @param {string} variant Bootstrap alert variant (`success`, `danger`, `warning`, `info`).
   * @param {string} message Plain text status message.
   */
  static setStatusAlert(container, variant, message) {
    if (!container) {
      return;
    }

    const target = container;
    target.className = `alert alert-${variant} mt-3 mb-0`;
    target.setAttribute('role', 'alert');
    // // [SECURITY:XSS] Plain text only; never parse untrusted strings as HTML.
    target.textContent = message;
    target.classList.remove('d-none');
  }

  /**
   * Hide and clear an alert container.
   *
   * @param {HTMLElement|null} container Target alert box.
   */
  static clearStatusAlert(container) {
    if (!container) {
      return;
    }

    const target = container;
    target.textContent = '';
    target.classList.add('d-none');
  }
}
