import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { describe, expect, it } from 'vitest';

const EXTENSION_ID = JSON.parse(readFileSync(new URL('../../../composer.json', import.meta.url), 'utf8')).name
  .replace('/flarum-ext-', '-')
  .replace('/flarum-', '-')
  .replace('/', '-');

function bootAdminBundle() {
  const initializers = new Map();
  const beforeMount = [];
  const settings = [];
  const permissions = [];
  const requests = [];
  const registry = {
    activeExtension: null,
    for(extension) {
      this.activeExtension = extension;
      return this;
    },
    registerSetting(setting, priority) {
      settings.push({ extension: this.activeExtension, setting, priority });
      return this;
    },
    registerPermission(permission, type, priority) {
      permissions.push({ extension: this.activeExtension, permission, type, priority });
      return this;
    },
  };
  const app = {
    data: { settings: {} },
    initializers: { add: (name, initializer) => initializers.set(name, initializer) },
    beforeMount: (callback) => beforeMount.push(callback),
    registry,
    request: (options) => {
      requests.push(options);
      return Promise.resolve({ data: [{ permissionName: 'images' }] });
    },
    translator: {
      trans: (key, params = {}) => {
        const labels = {
          'mie-files.admin.permissions.view_other': 'View other users\' file libraries',
          'mie-files.admin.permissions.delete_other': 'Delete files in other users\' libraries',
          'mie-files.admin.permissions.category_view': `File category-${params.name}-view`,
          'mie-files.admin.permissions.category_download': `File category-${params.name}-download`,
          'mie-files.admin.permissions.category_upload': `File category-${params.name}-upload`,
        };
        return labels[key] || key;
      },
    },
  };
  const Component = class {};
  const Modal = class {};
  const modules = {
    'admin/app': app,
    'common/Component': Component,
    'common/components/Button': class {},
    'common/components/LoadingIndicator': class {},
    'common/components/Modal': Modal,
  };
  const mithril = () => null;
  mithril.redraw = () => {};
  mithril.redraw.sync = () => {};

  runInNewContext(readFileSync(new URL('../../dist/admin.js', import.meta.url), 'utf8'), {
    flarum: {
      reg: {
        add: () => undefined,
        get: (_core, name) => modules[name],
      },
    },
    m: mithril,
    module: { exports: {} },
  });

  return { app, beforeMount, initializers, permissions, requests, settings };
}

describe('admin initializer', () => {
  it('does not access the forum before Flarum boots it', async () => {
    const state = bootAdminBundle();

    expect(EXTENSION_ID).toBe('alanqoq-mie-files');
    expect(() => state.initializers.get(EXTENSION_ID)()).not.toThrow();
    expect(state.settings.map((item) => item.extension)).toEqual([EXTENSION_ID]);
    expect(state.permissions).toHaveLength(0);

    state.app.forum = { attribute: (name) => (name === 'apiUrl' ? '/api' : undefined) };
    state.beforeMount.forEach((callback) => callback());
    expect(state.permissions).toHaveLength(2);
    await Promise.resolve();

    expect(state.requests).toEqual([{ method: 'GET', url: '/api/mie/categories' }]);
    expect(state.permissions).toHaveLength(5);
    expect(state.permissions.map(({ permission }) => permission.label)).toEqual([
      'View other users\' file libraries',
      'Delete files in other users\' libraries',
      'File category-images-view',
      'File category-images-download',
      'File category-images-upload',
    ]);
  });
});
