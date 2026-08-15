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
    translator: { trans: (key) => key },
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
    expect(state.permissions.map((item) => item.extension)).toEqual([EXTENSION_ID, EXTENSION_ID]);

    state.app.forum = { attribute: (name) => (name === 'apiUrl' ? '/api' : undefined) };
    state.beforeMount.forEach((callback) => callback());
    await Promise.resolve();

    expect(state.requests).toEqual([{ method: 'GET', url: '/api/mie/categories' }]);
    expect(state.permissions).toHaveLength(5);
  });
});
