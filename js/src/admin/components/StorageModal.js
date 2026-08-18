import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import { STORAGE_DRIVERS, storageDriver } from '../storageDrivers';

export default class StorageModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    this.storage = vnode.attrs.storage || null;
    const storage = this.storage || {};
    this.form = {
      name: storage.name || '',
      driver: storage.driver || '',
      directDeliveryConfirmed: Boolean(storage.directDeliveryConfirmed),
      enabled: storage.enabled !== false,
    };
    STORAGE_DRIVERS.flatMap((driver) => driver.fields).forEach((field) => {
      this.form[field.name] = storage[field.name] || '';
    });
    this.step = this.storage ? 'configure' : 'driver';
    this.saving = false;
  }

  className() { return 'Modal--small MieFiles-storageModal'; }
  title() {
    if (this.step === 'driver') return app.translator.trans('mie-files.admin.storage_driver_select_title');
    return app.translator.trans('mie-files.admin.storage_modal_title', { driver: this.driverLabel(this.form.driver) });
  }

  content() {
    return this.step === 'driver' ? this.driverSelection() : this.configuration();
  }

  driverSelection() {
    return (
      <div className="Modal-body">
        <div className="Form-group">
          <label>{app.translator.trans('mie-files.admin.storage_driver')}</label>
          <select className="FormControl" value={this.form.driver} onchange={(event) => { this.form.driver = event.target.value; this.error = ''; }}>
            <option value="">{app.translator.trans('mie-files.admin.storage_driver_select_placeholder')}</option>
            {STORAGE_DRIVERS.map((driver) => <option value={driver.id}>{this.driverLabel(driver.id)}</option>)}
          </select>
        </div>
        {this.error && <div className="MieFiles-adminError">{this.error}</div>}
        <div className="MieFiles-adminActions">
          <Button className="Button" onclick={this.hide.bind(this)}>{app.translator.trans('mie-files.admin.cancel')}</Button>
          <Button className="Button Button--primary" icon="fas fa-arrow-right" disabled={!this.driverDefinition()} onclick={this.showConfiguration.bind(this)}>
            {app.translator.trans('mie-files.admin.storage_next')}
          </Button>
        </div>
      </div>
    );
  }

  configuration() {
    const driver = this.driverDefinition();
    if (!driver) return this.driverSelection();
    const direct = driver.fields.some((field) => field.name === 'publicBaseUrl') && Boolean(this.form.publicBaseUrl.trim());
    return (
      <div className="Modal-body">
        <div className="MieFiles-storageDriverSummary"><i className={driver.icon} aria-hidden="true" />{this.driverLabel(driver.id)}</div>
        {this.error && <div className="MieFiles-adminError">{this.error}</div>}
        {this.field({ name: 'name', type: 'text', required: true })}
        {driver.fields.map((field) => this.field(field))}
        {direct && (
          <div className="MieFiles-directWarning">
            <strong>{app.translator.trans('mie-files.admin.direct_warning_title')}</strong>
            <p>{app.translator.trans('mie-files.admin.direct_link_warning')}</p>
            <label className="Checkbox">
              <input type="checkbox" checked={this.form.directDeliveryConfirmed} onchange={(event) => { this.form.directDeliveryConfirmed = event.target.checked; }} />
              {app.translator.trans('mie-files.admin.direct_confirm')}
            </label>
          </div>
        )}
        <div className="MieFiles-adminActions">
          {!this.storage && <Button className="Button Button--icon" icon="fas fa-arrow-left" title={app.translator.trans('mie-files.admin.storage_back')} onclick={() => { this.step = 'driver'; this.error = ''; }} />}
          <Button className="Button" onclick={this.hide.bind(this)}>{app.translator.trans('mie-files.admin.cancel')}</Button>
          <Button className="Button Button--primary" loading={this.saving} disabled={direct && !this.form.directDeliveryConfirmed} onclick={this.save.bind(this)}>
            {app.translator.trans('mie-files.admin.save')}
          </Button>
        </div>
      </div>
    );
  }

  field(field) {
    const { name, type, requiredOnCreate } = field;
    const required = field.required || (requiredOnCreate && !this.storage);
    return (
      <div className="Form-group">
        <label>{app.translator.trans(name === 'name' ? 'mie-files.admin.storage_config_name' : `mie-files.admin.storage_${name}`)}{required ? ' *' : ''}</label>
        <input className="FormControl" type={type} value={this.form[name]} oninput={(event) => { this.form[name] = event.target.value; }} autocomplete="off" />
        {this.storage && ['accessKey', 'secretKey', 'endpoint'].includes(name) && <p className="helpText">{app.translator.trans(name === 'endpoint' ? 'mie-files.admin.endpoint_unchanged' : 'mie-files.admin.credential_unchanged')}</p>}
        {name === 'pathPrefix' && <p className="helpText">{app.translator.trans('mie-files.admin.storage_pathPrefix_help')}</p>}
      </div>
    );
  }

  driverDefinition() { return storageDriver(this.form.driver); }
  driverLabel(driver) {
    const definition = storageDriver(driver);
    if (definition) return app.translator.trans(definition.label);
    return driver === 'local' ? app.translator.trans('mie-files.admin.storage_driver_local') : driver;
  }
  showConfiguration() { if (this.driverDefinition()) this.step = 'configure'; }

  save() {
    this.saving = true;
    this.error = '';
    const existing = this.storage;
    app.request({
      method: existing ? 'PATCH' : 'POST',
      url: `${app.forum.attribute('apiUrl')}/mie/storage${existing ? `/${encodeURIComponent(existing.id)}` : ''}`,
      body: this.form,
    }).then((response) => {
      this.attrs.onsaved?.(response.data);
      this.hide();
    }).catch((error) => {
      this.error = error?.response?.errors?.[0]?.detail || error.message;
    }).finally(() => { this.saving = false; m.redraw(); });
  }
}
