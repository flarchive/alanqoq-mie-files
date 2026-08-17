import app from 'flarum/forum/app';
export { humanSize } from './format';

export function apiUrl(path = '') {
  return `${app.forum.attribute('apiUrl')}/mie${path}`;
}

export function errorMessage(error) {
  return error?.response?.errors?.[0]?.detail || error?.message || 'Request failed.';
}

export function request(options) {
  return app.request(options);
}
