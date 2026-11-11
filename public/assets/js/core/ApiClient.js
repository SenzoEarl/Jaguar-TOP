import DomHelper from './DomHelper.js';

/**
 * Central HTTP/AJAX client wrapping Fetch API with CSRF header injection
 * and normalized error propagation for all frontend controllers.
 *
 * // [SECURITY:CSRF] Automatically sends the session CSRF header on mutating calls.
 */
export default class ApiClient {
  /**
   * Construct an API client configured from document metadata.
   *
   * @param {object} [options] Optional configuration overrides.
   * @param {string} [options.basePath] Application URL subpath prefix.
   * @param {string} [options.csrfHeader] Header name expected by backend middleware.
   * @param {string} [options.csrfToken] Active session CSRF token.
   */
  constructor(options = {}) {
    this.basePath = (options.basePath ?? DomHelper.getMeta('app-base-path', ''))
      .replace(/\/+$/, '');
    this.csrfHeader = options.csrfHeader ?? DomHelper.getMeta('csrf-header', 'X-CSRF-Token');
    this.csrfToken = options.csrfToken ?? DomHelper.getMeta('csrf-token', '');
  }

  /**
   * Resolve a root-relative path against any configured subfolder `basePath`.
   *
   * @param {string} path Route path starting with `/`.
   * @returns {string} Prefixed request URL.
   */
  buildUrl(path) {
    const normalized = path.startsWith('/') ? path : `/${path}`;
    return `${this.basePath}${normalized}`;
  }

  /**
   * Execute an HTTP request expecting JSON and throw normalized errors on failure.
   *
   * @param {string} method HTTP verb (`GET`, `POST`, `PATCH`, `DELETE`).
   * @param {string} path Route path relative to application root.
   * @param {object|FormData|null} [body] JSON payload or multipart FormData.
   * @returns {Promise<Record<string, any>>} Parsed JSON object.
   */
  async request(method, path, body = null) {
    const upperMethod = method.toUpperCase();
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
    };

    if (upperMethod !== 'GET' && upperMethod !== 'HEAD' && this.csrfToken !== '') {
      headers[this.csrfHeader] = this.csrfToken;
    }

    const fetchOptions = {
      method: upperMethod,
      headers,
      credentials: 'same-origin',
    };

    if (body instanceof FormData) {
      fetchOptions.body = body;
    } else if (body !== null) {
      headers['Content-Type'] = 'application/json';
      fetchOptions.body = JSON.stringify(body);
    }

    let response;
    try {
      response = await fetch(this.buildUrl(path), fetchOptions);
    } catch (networkError) {
      const error = new Error(
        'Network connection error. Please check your connection and try again.',
      );
      error.status = 0;
      error.fieldErrors = {};
      error.cause = networkError;
      throw error;
    }

    let payload = null;
    try {
      payload = await response.json();
    } catch {
      payload = null;
    }

    if (!response.ok) {
      const message = ApiClient.extractErrorMessage(response.status, payload);
      const error = new Error(message);
      error.status = response.status;
      error.fieldErrors = payload && payload.errors ? payload.errors : {};
      error.payload = payload;
      throw error;
    }

    return payload || {};
  }

  /**
   * Execute an HTTP GET request expecting JSON.
   *
   * @param {string} path Route path.
   * @returns {Promise<Record<string, any>>} Parsed JSON object.
   */
  get(path) {
    return this.request('GET', path);
  }

  /**
   * Execute an HTTP POST request with JSON or FormData payload.
   *
   * @param {string} path Route path.
   * @param {object|FormData} body Payload body.
   * @returns {Promise<Record<string, any>>} Parsed JSON object.
   */
  post(path, body) {
    return this.request('POST', path, body);
  }

  /**
   * Translate HTTP error responses into safe user-facing messages.
   *
   * @param {number} status HTTP status code.
   * @param {Record<string, any>|null} payload Optional parsed error body.
   * @returns {string} Friendly error text.
   */
  static extractErrorMessage(status, payload) {
    if (payload && payload.errors && typeof payload.errors === 'object') {
      const firstGroup = Object.values(payload.errors)[0];
      if (Array.isArray(firstGroup) && typeof firstGroup[0] === 'string' && firstGroup[0] !== '') {
        return firstGroup[0];
      }
    }

    if (payload && typeof payload.error === 'string' && payload.error.trim() !== '') {
      return payload.error;
    }

    if (payload && typeof payload.message === 'string' && payload.message.trim() !== '') {
      return payload.message;
    }

    if (status === 419) {
      return 'Your security token expired. Please reload the page and try again.';
    }
    if (status === 429) {
      return 'Too many requests. Please wait briefly before retrying.';
    }
    if (status === 403) {
      return 'You do not have permission to perform this action.';
    }
    if (status === 401) {
      return 'Your session has expired. Please sign in again.';
    }

    return `Request failed with status ${status || 'unknown'}.`;
  }
}
