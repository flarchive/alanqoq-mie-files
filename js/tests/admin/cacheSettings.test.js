import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { describe, expect, it, vi } from 'vitest';

const require = createRequire(import.meta.url);
const m = (tag, attrs, ...children) => ({ tag, attrs, children });
m.redraw = vi.fn();
m.redraw.sync = vi.fn();
globalThis.m = m;
const app = {
  data: { settings: {} },
  forum: { attribute: () => '/api' },
  translator: { trans: (key) => key },
  alerts: { show: vi.fn() },
  request: vi.fn(),
  registry: { for: vi.fn(() => app.registry), registerPermission: vi.fn() },
};

function loadSettingsPage() {
  const source = readFileSync(new URL('../../src/admin/components/SettingsPage.js', import.meta.url), 'utf8');
  const { code } = require('@babel/core').transformSync(source, {
    plugins: [[require('@babel/plugin-transform-react-jsx'), { pragma: 'm' }], require('@babel/plugin-transform-modules-commonjs')],
  });
  const module = { exports: {} };
  const dependencies = {
    'flarum/admin/app': { default: app, __esModule: true },
    'flarum/common/Component': { default: class Component { oninit() {} }, __esModule: true },
    'flarum/common/components/Button': { default: class Button {}, __esModule: true },
    'flarum/common/components/LoadingIndicator': { default: class LoadingIndicator {}, __esModule: true },
    'flarum/common/components/Switch': { default: class Switch {}, __esModule: true },
    './StorageModal': { default: class StorageModal {}, __esModule: true },
    './MimeModal': { default: class MimeModal {}, __esModule: true },
    '../storageDrivers': { storageDriver: () => null, __esModule: true },
    '../categoryUtils': { clonePreset: () => null, INSERT_TEMPLATES: [], PRESETS: {}, validateCategories: () => ({}), __esModule: true },
    '../permissions': { registerPermissions: () => undefined, __esModule: true },
  };
  new Function('require', 'module', 'exports', code)((id) => dependencies[id], module, module.exports);
  return module.exports.default;
}

const SettingsPage = loadSettingsPage();

function pageWith(settings) {
  const page = Object.create(SettingsPage.prototype);
  page.cacheSettings = settings;
  page.cacheErrors = {};
  page.categories = [];
  page.errors = {};
  page.saving = false;
  return page;
}

describe('file cache settings', () => {
  it('uses the requested defaults and honors disabled boolean settings', () => {
    app.data.settings = {
      'mie-files.cache-enabled': 'false',
      'mie-files.cache-thumbnails': 'off',
    };
    const page = Object.create(SettingsPage.prototype);
    page.load = vi.fn();

    page.oninit({});

    expect(page.cacheSettings).toEqual({
      enabled: false,
      retentionDays: 30,
      maxMb: 2048,
      maxFileMb: 256,
      thumbnails: false,
      thumbnailMaxMb: 2048,
    });
    expect(page.imageSettings.thumbnailConvertWebp).toBe(false);
    app.data.settings = {};
  });

  it('loads and saves the thumbnail WebP conversion setting', async () => {
    app.data.settings = { 'mie-files.thumbnail-convert-webp': '1' };
    const page = Object.create(SettingsPage.prototype);
    page.load = vi.fn();
    page.oninit({});
    expect(page.imageSettings.thumbnailConvertWebp).toBe(true);
    app.request.mockResolvedValue({});
    page.saveSettings({ 'mie-files.thumbnail-convert-webp': page.imageSettings.thumbnailConvertWebp ? '1' : '0' });
    await Promise.resolve();
    expect(app.request).toHaveBeenLastCalledWith(expect.objectContaining({
      method: 'POST',
      body: { 'mie-files.thumbnail-convert-webp': '1' },
    }));
    app.data.settings = {};
  });

  it('builds the six backend settings with string values', () => {
    const page = pageWith({
      enabled: false,
      retentionDays: 45,
      maxMb: 4096,
      maxFileMb: 512,
      thumbnails: true,
      thumbnailMaxMb: 1024,
    });

    expect(page.cacheSettingsBody()).toEqual({
      'mie-files.cache-enabled': '0',
      'mie-files.cache-retention-days': '45',
      'mie-files.cache-max-mb': '4096',
      'mie-files.cache-max-file-mb': '512',
      'mie-files.cache-thumbnails': '1',
      'mie-files.cache-thumbnail-max-mb': '1024',
    });
  });

  it('rejects non-integers and values outside backend limits', () => {
    const page = pageWith({
      enabled: true,
      retentionDays: 0,
      maxMb: 1.5,
      maxFileMb: 1048577,
      thumbnails: true,
      thumbnailMaxMb: '',
    });

    expect(page.validateCacheSettings()).toEqual({
      retentionDays: true,
      maxMb: true,
      maxFileMb: true,
      thumbnailMaxMb: true,
    });
  });

  it('renders an invalid cache field in red with validation text', () => {
    const page = pageWith({
      retentionDays: 0,
      maxMb: 2048,
      maxFileMb: 256,
      thumbnailMaxMb: 2048,
    });
    page.cacheErrors.retentionDays = true;

    const rendered = page.cacheNumber('retentionDays', 1, 3650, 'cache_retention_days_help');

    expect(JSON.stringify(rendered)).toContain('MieFiles-invalid');
    expect(JSON.stringify(rendered)).toContain('MieFiles-fieldError');
    expect(JSON.stringify(rendered)).toContain('cache_value_invalid');
  });

  it('posts cache settings together with the category save', async () => {
    app.request.mockImplementation(({ method }) => Promise.resolve(method === 'PUT' ? { data: [] } : {}));
    const page = pageWith({
      enabled: true,
      retentionDays: 30,
      maxMb: 2048,
      maxFileMb: 256,
      thumbnails: true,
      thumbnailMaxMb: 2048,
    });

    await page.saveCategories();

    const settingsRequest = app.request.mock.calls.find(([request]) => request.method === 'POST' && request.body['mie-files.cache-enabled'] !== undefined);
    expect(settingsRequest[0].body).toEqual(page.cacheSettingsBody());
    expect(Object.keys(settingsRequest[0].body)).toHaveLength(6);
  });

  it('keeps all cache labels and validation text in both locales', () => {
    const keys = [
      'cache_settings',
      'cache_settings_help',
      'cache_behavior_help',
      'cache_path_help',
      'cache_enabled',
      'cache_enabled_help',
      'cache_retentionDays',
      'cache_retention_days_help',
      'cache_maxMb',
      'cache_max_mb_help',
      'cache_maxFileMb',
      'cache_max_file_mb_help',
      'cache_thumbnails',
      'cache_thumbnails_help',
      'cache_thumbnailMaxMb',
      'cache_thumbnail_max_mb_help',
      'cache_value_invalid',
      'thumbnail_settings',
      'thumbnail_convert_webp',
      'thumbnail_convert_webp_help',
    ];

    for (const locale of ['en', 'zh']) {
      const source = readFileSync(new URL(`../../../locale/${locale}.yml`, import.meta.url), 'utf8');
      for (const key of keys) expect(source).toMatch(new RegExp(`^\\s+${key}:\\s+\\S`, 'm'));
    }
  });
});
