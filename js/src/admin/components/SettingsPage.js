import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import StorageModal from './StorageModal';
import MimeModal from './MimeModal';
import { clonePreset, INSERT_TEMPLATES, PRESETS, validateCategories } from '../categoryUtils';
import { registerPermissions } from '../permissions';

export default class SettingsPage extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.activeTab = 'files';
    this.categories = [];
    this.storages = [];
    this.errors = {};
    this.loading = true;
    this.saving = false;
    this.preset = 'images';
    this.imageSettings = {
      hotlinkProtection: String(app.data.settings?.['mie-files.hotlink-protection'] ?? '1') !== '0',
      thumbnailWidth: Number(app.data.settings?.['mie-files.thumbnail-width'] || 480),
      imageQuality: Number(app.data.settings?.['mie-files.image-quality'] || 85),
    };
    this.pluginSettings = {
      orphanRetentionDays: Number(app.data.settings?.['mie-files.orphan-retention-days'] || 30),
    };
    this.load();
  }

  view() {
    const tabs = [
      ['files', 'fas fa-folder-tree', 'files_tab'],
      ['storage', 'fas fa-database', 'storage_tab'],
      ['images', 'fas fa-image', 'images_tab'],
      ['plugin', 'fas fa-sliders-h', 'plugin_tab'],
    ];
    return (
      <div className="MieFiles-settings">
        <nav className="MieFiles-tabs" aria-label={app.translator.trans('mie-files.admin.settings')}>
          {tabs.map(([key, icon, label]) => (
            <Button className={`Button MieFiles-tab ${this.activeTab === key ? 'is-active' : ''}`} icon={icon} onclick={() => { this.activeTab = key; }}>
              {app.translator.trans(`mie-files.admin.${label}`)}
            </Button>
          ))}
        </nav>
        {this.loading ? <LoadingIndicator /> : (
          <div className="MieFiles-tabPanel">
            {this.globalError && <div className="MieFiles-adminError">{this.globalError}</div>}
            {this.activeTab === 'files' && this.fileManagement()}
            {this.activeTab === 'storage' && this.storageManagement()}
            {this.activeTab === 'images' && this.imageManagement()}
            {this.activeTab === 'plugin' && this.pluginManagement()}
          </div>
        )}
      </div>
    );
  }

  fileManagement() {
    return (
      <section>
        <div className="MieFiles-sectionHeader">
          <div>
            <h3>{app.translator.trans('mie-files.admin.file_categories')}</h3>
            <p>{app.translator.trans('mie-files.admin.file_categories_help')}</p>
          </div>
          <div className="MieFiles-headerActions">
            <select className="FormControl" value={this.preset} onchange={(event) => { this.preset = event.target.value; }}>
              {Object.keys(PRESETS).map((key) => <option value={key}>{app.translator.trans(`mie-files.admin.preset_${key}`)}</option>)}
            </select>
            <Button className="Button" icon="fas fa-plus" onclick={this.addPreset.bind(this)}>{app.translator.trans('mie-files.admin.add_preset')}</Button>
            <Button className="Button" icon="fas fa-microscope" onclick={() => app.modal.show(MimeModal)}>{app.translator.trans('mie-files.admin.mime_title')}</Button>
          </div>
        </div>

        <div className="MieFiles-categoryList">
          {this.categories.map((category, index) => this.categoryModule(category, index))}
        </div>

        <div className="MieFiles-globalSave">
          <Button className="Button Button--primary" icon="fas fa-save" loading={this.saving} onclick={this.saveCategories.bind(this)}>
            {app.translator.trans('mie-files.admin.save_changes')}
          </Button>
        </div>
      </section>
    );
  }

  categoryModule(category, index) {
    return (
      <section className="MieFiles-category" data-category-index={index}>
        <div className="MieFiles-categoryHeader">
          <h4>{category.name || app.translator.trans('mie-files.admin.unnamed_category')}</h4>
          <Button className="Button Button--icon" icon="fas fa-trash" title={app.translator.trans('mie-files.admin.remove_category')} onclick={() => { this.categories.splice(index, 1); this.errors = validateCategories(this.categories); }} />
        </div>
        <div className="MieFiles-fieldGrid">
          {this.categoryInput(index, 'name', 'text')}
          {this.categoryInput(index, 'slug', 'text')}
          {this.categoryInput(index, 'permissionName', 'text')}
          {this.categoryInput(index, 'maxSizeMb', 'number')}
          <div className="Form-group">
            <label>{app.translator.trans('mie-files.admin.storage_name')}</label>
            <select className={this.fieldClass(index, 'storageName')} value={category.storageName} onchange={(event) => this.update(index, 'storageName', event.target.value)}>
              {this.storages.filter((storage) => storage.enabled).map((storage) => <option value={storage.name}>{storage.name}</option>)}
            </select>
            {this.errorText(index, 'storageName')}
          </div>
          <div className="Form-group">
            <label>{app.translator.trans('mie-files.admin.insert_template')}</label>
            <select className={this.fieldClass(index, 'insertTemplate')} value={category.insertTemplate} onchange={(event) => this.update(index, 'insertTemplate', event.target.value)}>
              {INSERT_TEMPLATES.map((template) => <option value={template}>{app.translator.trans(`mie-files.admin.template_${template}`)}</option>)}
            </select>
            {this.errorText(index, 'insertTemplate')}
          </div>
        </div>
        <div className="MieFiles-rules">
          <div className="MieFiles-ruleHeader">
            <strong>{app.translator.trans('mie-files.admin.type_rules')}</strong>
            <Button className="Button Button--icon" icon="fas fa-plus" title={app.translator.trans('mie-files.admin.add_rule')} onclick={() => { category.rules.push({ extension: '', mime: '' }); }} />
          </div>
          {(category.rules || []).map((rule, ruleIndex) => (
            <div className="MieFiles-ruleRow">
              <div>
                <input className={this.ruleClass(index, ruleIndex, 'extension')} value={rule.extension} placeholder="docx" oninput={(event) => { rule.extension = event.target.value; }} />
                {this.ruleError(index, ruleIndex, 'extension')}
              </div>
              <div>
                <input className={this.ruleClass(index, ruleIndex, 'mime')} value={rule.mime} placeholder="application/..." oninput={(event) => { rule.mime = event.target.value; }} />
                {this.ruleError(index, ruleIndex, 'mime')}
              </div>
              <Button className="Button Button--icon" icon="fas fa-times" title={app.translator.trans('mie-files.admin.remove_rule')} onclick={() => { category.rules.splice(ruleIndex, 1); }} />
            </div>
          ))}
          {this.errorText(index, 'rules')}
        </div>
        <div className="MieFiles-categoryFooter">
          <Button className="Button" icon="fas fa-save" disabled={this.categoryHasErrors(index)} onclick={this.saveCategories.bind(this)}>{app.translator.trans('mie-files.admin.save_category')}</Button>
        </div>
      </section>
    );
  }

  storageManagement() {
    return (
      <section>
        <div className="MieFiles-sectionHeader">
          <div><h3>{app.translator.trans('mie-files.admin.storage_methods')}</h3><p>{app.translator.trans('mie-files.admin.storage_help')}</p></div>
          <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => this.openStorage()}>{app.translator.trans('mie-files.admin.add_storage')}</Button>
        </div>
        <div className="MieFiles-storageList">
          {this.storages.map((storage) => (
            <div className="MieFiles-storageRow">
              <div><strong>{storage.name}</strong><span>{storage.driver} · {app.translator.trans(`mie-files.admin.delivery_${storage.deliveryMode}`)}</span></div>
              {storage.id !== 'local' && <Button className="Button Button--icon" icon="fas fa-pen" title={app.translator.trans('mie-files.admin.edit')} onclick={() => this.openStorage(storage)} />}
            </div>
          ))}
        </div>
      </section>
    );
  }

  imageManagement() {
    return (
      <section>
        <h3>{app.translator.trans('mie-files.admin.image_settings')}</h3>
        <div className="Form-group">
          <label className="Checkbox"><input type="checkbox" checked={this.imageSettings.hotlinkProtection} onchange={(event) => { this.imageSettings.hotlinkProtection = event.target.checked; }} />{app.translator.trans('mie-files.admin.hotlink_protection')}</label>
        </div>
        {this.settingNumber(this.imageSettings, 'thumbnailWidth', 32, 4096)}
        {this.settingNumber(this.imageSettings, 'imageQuality', 1, 100)}
        <Button className="Button Button--primary" icon="fas fa-save" onclick={() => this.saveSettings({
          'mie-files.hotlink-protection': this.imageSettings.hotlinkProtection ? '1' : '0',
          'mie-files.thumbnail-width': String(this.imageSettings.thumbnailWidth),
          'mie-files.image-quality': String(this.imageSettings.imageQuality),
        })}>{app.translator.trans('mie-files.admin.save_changes')}</Button>
      </section>
    );
  }

  pluginManagement() {
    return (
      <section>
        <h3>{app.translator.trans('mie-files.admin.plugin_settings')}</h3>
        {this.settingNumber(this.pluginSettings, 'orphanRetentionDays', 1, 3650)}
        <Button className="Button Button--primary" icon="fas fa-save" onclick={() => this.saveSettings({ 'mie-files.orphan-retention-days': String(this.pluginSettings.orphanRetentionDays) })}>{app.translator.trans('mie-files.admin.save_changes')}</Button>
      </section>
    );
  }

  categoryInput(index, field, type) {
    return (
      <div className="Form-group">
        <label>{app.translator.trans(`mie-files.admin.${field}`)}</label>
        <input className={this.fieldClass(index, field)} type={type} min={type === 'number' ? 1 : undefined} value={this.categories[index][field]} oninput={(event) => this.update(index, field, type === 'number' ? Number(event.target.value) : event.target.value)} />
        {this.errorText(index, field)}
      </div>
    );
  }

  settingNumber(target, field, min, max) {
    return <div className="Form-group"><label>{app.translator.trans(`mie-files.admin.${field}`)}</label><input className="FormControl" type="number" min={min} max={max} value={target[field]} oninput={(event) => { target[field] = Number(event.target.value); }} /></div>;
  }

  fieldClass(index, field) { return `FormControl ${this.errors[`${index}.${field}`] ? 'MieFiles-invalid' : ''}`; }
  ruleClass(index, ruleIndex, field) { return `FormControl ${this.errors[`${index}.rules.${ruleIndex}.${field}`] ? 'MieFiles-invalid' : ''}`; }
  errorText(index, field) { return this.errors[`${index}.${field}`] ? <span className="MieFiles-fieldError">{app.translator.trans('mie-files.admin.field_required')}</span> : null; }
  ruleError(index, ruleIndex, field) { return this.errors[`${index}.rules.${ruleIndex}.${field}`] ? <span className="MieFiles-fieldError">{app.translator.trans('mie-files.admin.field_invalid')}</span> : null; }
  categoryHasErrors(index) { return Object.keys(this.errors).some((key) => key.startsWith(`${index}.`)); }
  update(index, field, value) { this.categories[index][field] = value; delete this.errors[`${index}.${field}`]; }

  load() {
    Promise.all([
      app.request({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/mie/categories` }),
      app.request({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/mie/storage` }),
    ]).then(([categories, storages]) => {
      this.categories = categories.data || [];
      this.storages = storages.data || [];
      registerPermissions(this.categories);
    }).catch((error) => { this.globalError = error?.response?.errors?.[0]?.detail || error.message; })
      .finally(() => { this.loading = false; m.redraw(); });
  }

  addPreset() {
    const category = clonePreset(this.preset);
    if (!category) return;
    if (this.categories.some((item) => item.slug === category.slug)) category.slug = `${category.slug}-${this.categories.length + 1}`;
    if (this.categories.some((item) => item.permissionName === category.permissionName)) category.permissionName = `${category.permissionName}-${this.categories.length + 1}`;
    this.categories.push(category);
    this.errors = validateCategories(this.categories);
  }

  saveCategories() {
    this.errors = validateCategories(this.categories);
    if (Object.keys(this.errors).length) {
      this.activeTab = 'files';
      m.redraw.sync();
      document.querySelector('.MieFiles-invalid')?.focus();
      return;
    }
    this.saving = true;
    this.globalError = '';
    app.request({ method: 'PUT', url: `${app.forum.attribute('apiUrl')}/mie/categories`, body: { categories: this.categories } })
      .then((response) => {
        this.categories = response.data || [];
        registerPermissions(this.categories);
        app.alerts.show({ type: 'success' }, app.translator.trans('mie-files.admin.saved'));
      }).catch((error) => { this.globalError = error?.response?.errors?.[0]?.detail || error.message; })
      .finally(() => { this.saving = false; m.redraw(); });
  }

  openStorage(storage = null) {
    app.modal.show(StorageModal, { storage, onsaved: () => this.reloadStorages() });
  }

  reloadStorages() {
    return app.request({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/mie/storage` }).then((response) => { this.storages = response.data || []; m.redraw(); });
  }

  saveSettings(body) {
    return app.request({ method: 'POST', url: `${app.forum.attribute('apiUrl')}/settings`, body })
      .then(() => app.alerts.show({ type: 'success' }, app.translator.trans('mie-files.admin.saved')))
      .catch((error) => { this.globalError = error?.response?.errors?.[0]?.detail || error.message; m.redraw(); });
  }
}
