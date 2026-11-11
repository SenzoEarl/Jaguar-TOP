import ApiClient from './core/ApiClient.js';
import BookingFormController from './modules/BookingFormController.js';
import CatalogFilterController from './modules/CatalogFilterController.js';
import FileManagerController from './modules/FileManagerController.js';
import TaskManagerController from './modules/TaskManagerController.js';

/**
 * Application JS entrypoint bootstrapping feature controllers conditionally
 * based on server-rendered DOM markers. Zero global variables exposed.
 */
document.addEventListener('DOMContentLoaded', () => {
  const apiClient = new ApiClient();
  const controllers = [];

  const bookingForm = document.querySelector('[data-booking-form]');
  if (bookingForm instanceof HTMLFormElement) {
    controllers.push(new BookingFormController(bookingForm, apiClient));
  }

  const catalogFilterForm = document.querySelector('[data-catalog-filter-form]');
  if (catalogFilterForm instanceof HTMLFormElement) {
    controllers.push(new CatalogFilterController(catalogFilterForm));
  }

  document.querySelectorAll('[data-task-widget]').forEach((widget) => {
    if (widget instanceof HTMLElement) {
      controllers.push(new TaskManagerController(widget, apiClient));
    }
  });

  document.querySelectorAll('[data-file-upload-widget]').forEach((widget) => {
    if (widget instanceof HTMLElement) {
      controllers.push(new FileManagerController(widget, apiClient));
    }
  });

  return controllers;
});
