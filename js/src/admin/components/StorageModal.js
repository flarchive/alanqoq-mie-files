import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

export default class StorageModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    const storage = vnode.attrs.storage || {};
    this.form = {
      name: storage.name || '',
      driver: 'dogecloud',
      accessKey: '',
      secretKey: '',
      bucket: storage.bucket || '',
      endpoint: storage.endpoint || '',
      region: storage.region || 'auto',
      publicBaseUrl: storage.publicBaseUrl || '',
      directDeliveryConfirmed: Boolean(storage.directDeliveryConfirmed),
      enabled: storage.enabled !== false,
    };
    this.saving = false;
  }

  className() { return 'Modal--small MieFiles-storageModal'; }
  title() { return app.translator.trans('mie-files.admin.storage_modal_title'); }

  content() {
    const direct = Boolean(this.form.publicBaseUrl.trim());
    return (
      <div className="Modal-body">
        {this.error && <div className="MieFiles-adminError">{this.error}</div>}
        {this.field('name', 'text', true)}
        {this.field('accessKey', 'text', !this.attrs.storage)}
        {this.field('secretKey', 'password', !this.attrs.storage)}
        {this.field('bucket', 'text', !this.attrs.storage)}
        {this.field('endpoint', 'url', !this.attrs.storage)}
        {this.field('region', 'text', false)}
        {this.field('publicBaseUrl', 'url', false)}
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
          <Button className="Button" onclick={this.hide.bind(this)}>{app.translator.trans('mie-files.admin.cancel')}</Button>
          <Button className="Button Button--primary" loading={this.saving} disabled={direct && !this.form.directDeliveryConfirmed} onclick={this.save.bind(this)}>
            {app.translator.trans('mie-files.admin.save')}
          </Button>
        </div>
      </div>
    );
  }

  field(name, type, required) {
    return (
      <div className="Form-group">
        <label>{app.translator.trans(name === 'name' ? 'mie-files.admin.storage_config_name' : `mie-files.admin.storage_${name}`)}{required ? ' *' : ''}</label>
        <input className="FormControl" type={type} value={this.form[name]} oninput={(event) => { this.form[name] = event.target.value; }} autocomplete="off" />
        {this.attrs.storage && ['secretKey', 'endpoint'].includes(name) && <p className="helpText">{app.translator.trans(name === 'secretKey' ? 'mie-files.admin.secret_unchanged' : 'mie-files.admin.endpoint_unchanged')}</p>}
      </div>
    );
  }

  save() {
    this.saving = true;
    this.error = '';
    const existing = this.attrs.storage;
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
