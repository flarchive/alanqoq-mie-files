import app from 'flarum/admin/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

export default class MimeModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    this.extension = '';
    this.file = null;
    this.result = null;
    this.loading = false;
  }

  className() { return 'Modal--small MieFiles-mimeModal'; }
  title() { return app.translator.trans('mie-files.admin.mime_title'); }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form-group">
          <label>{app.translator.trans('mie-files.admin.mime_file')}</label>
          <input type="file" onchange={(event) => { this.file = event.target.files?.[0] || null; }} />
        </div>
        <div className="Form-group">
          <label>{app.translator.trans('mie-files.admin.mime_extension')}</label>
          <input className="FormControl" value={this.extension} oninput={(event) => { this.extension = event.target.value; }} placeholder="docx" />
        </div>
        {this.error && <div className="MieFiles-adminError">{this.error}</div>}
        {this.result && (
          <dl className="MieFiles-mimeResult">
            <dt>{app.translator.trans('mie-files.admin.mime_source')}</dt><dd>{this.result.source}</dd>
            <dt>{app.translator.trans('mie-files.admin.mime_extension')}</dt><dd><code>{this.result.extension}</code></dd>
            <dt>{app.translator.trans('mie-files.admin.mime_detected')}</dt><dd><code>{this.result.mime || (this.result.mimes || []).join(', ') || '-'}</code></dd>
          </dl>
        )}
        <div className="MieFiles-adminActions">
          <Button className="Button" onclick={this.hide.bind(this)}>{app.translator.trans('mie-files.admin.cancel')}</Button>
          <Button className="Button Button--primary" loading={this.loading} onclick={this.inspect.bind(this)}>{app.translator.trans('mie-files.admin.mime_confirm')}</Button>
        </div>
      </div>
    );
  }

  inspect() {
    this.loading = true;
    this.error = '';
    const body = new FormData();
    if (this.file) body.append('file', this.file);
    else body.append('extension', this.extension);
    app.request({
      method: 'POST',
      url: `${app.forum.attribute('apiUrl')}/mie/mime-detect`,
      serialize: (raw) => raw,
      body,
    }).then((response) => { this.result = response.data; })
      .catch((error) => { this.error = error?.response?.errors?.[0]?.detail || error.message; })
      .finally(() => { this.loading = false; m.redraw(); });
  }
}
