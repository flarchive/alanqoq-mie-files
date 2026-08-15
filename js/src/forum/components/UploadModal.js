import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { apiUrl, errorMessage, request } from '../api';

export default class UploadModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    this.categories = [];
    this.categoryId = '';
    this.queue = [];
    this.loadingCategories = true;
    this.dragging = false;
    request({ method: 'GET', url: apiUrl('/categories') })
      .then((response) => {
        this.categories = response.data || [];
        this.categoryId = this.categories[0]?.id || '';
      })
      .catch((error) => {
        this.categoryError = errorMessage(error);
      })
      .finally(() => {
        this.loadingCategories = false;
        m.redraw();
      });
  }

  className() {
    return 'Modal--large MieFiles-uploadModal';
  }

  title() {
    return app.translator.trans('mie-files.forum.upload_title');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form-group">
          <label>{app.translator.trans('mie-files.forum.category')}</label>
          {this.loadingCategories ? <LoadingIndicator size="small" /> : (
            <select className="FormControl" value={this.categoryId} onchange={(event) => { this.categoryId = event.target.value; }}>
              {this.categories.map((category) => (
                <option value={category.id}>{category.name} ({category.maxSizeMb} MB)</option>
              ))}
            </select>
          )}
          {this.categoryError && <div className="MieFiles-errorText">{this.categoryError}</div>}
        </div>

        <div
          className={`MieFiles-dropzone ${this.dragging ? 'is-dragging' : ''}`}
          role="button"
          tabindex="0"
          onclick={() => this.fileInput?.click()}
          onkeydown={(event) => { if (event.key === 'Enter' || event.key === ' ') this.fileInput?.click(); }}
          ondragenter={(event) => { event.preventDefault(); this.dragging = true; }}
          ondragover={(event) => event.preventDefault()}
          ondragleave={() => { this.dragging = false; }}
          ondrop={(event) => {
            event.preventDefault();
            this.dragging = false;
            this.enqueue(event.dataTransfer.files);
          }}
        >
          <i className="fas fa-cloud-upload-alt" aria-hidden="true" />
          <strong>{app.translator.trans('mie-files.forum.drop_files')}</strong>
          <span>{app.translator.trans('mie-files.forum.choose_files')}</span>
          <input
            type="file"
            multiple
            hidden
            oncreate={(vnode) => { this.fileInput = vnode.dom; }}
            onchange={(event) => {
              this.enqueue(event.target.files);
              event.target.value = '';
            }}
          />
        </div>

        <div className="MieFiles-uploadQueue" aria-live="polite">
          {this.queue.map((item) => (
            <div className={`MieFiles-uploadRow is-${item.status}`}>
              <div className="MieFiles-uploadMeta">
                <strong title={item.file.name}>{item.file.name}</strong>
                <span>{item.message || app.translator.trans(`mie-files.forum.upload_status_${item.status}`)}</span>
              </div>
              <div className="MieFiles-progress"><span style={{ width: `${item.progress}%` }} /></div>
            </div>
          ))}
        </div>

        <div className="Form-group MieFiles-modalActions">
          <Button className="Button" onclick={this.hide.bind(this)}>{app.translator.trans('mie-files.forum.close')}</Button>
        </div>
      </div>
    );
  }

  enqueue(fileList) {
    if (!fileList?.length || !this.categoryId) {
      this.categoryError = app.translator.trans('mie-files.forum.choose_category');
      m.redraw();
      return;
    }
    const items = Array.from(fileList).map((file) => ({ file, progress: 0, status: 'queued', message: '' }));
    this.queue.push(...items);
    items.forEach((item) => this.uploadOne(item));
    m.redraw();
  }

  uploadOne(item) {
    item.status = 'uploading';
    return new Promise((resolve) => {
      const body = new FormData();
      body.append('file', item.file);
      body.append('categoryId', this.categoryId);
      const xhr = new XMLHttpRequest();
      xhr.open('POST', apiUrl('/files'));
      xhr.withCredentials = true;
      xhr.setRequestHeader('Accept', 'application/json');
      xhr.setRequestHeader('X-CSRF-Token', app.session.csrfToken);
      xhr.upload.onprogress = (event) => {
        if (event.lengthComputable) item.progress = Math.round((event.loaded / event.total) * 100);
        m.redraw();
      };
      xhr.onload = () => {
        const token = xhr.getResponseHeader('X-CSRF-Token');
        if (token) app.session.csrfToken = token;
        let response = {};
        try { response = JSON.parse(xhr.responseText || '{}'); } catch (_) { response = {}; }
        if (xhr.status >= 200 && xhr.status < 300) {
          item.status = 'success';
          item.progress = 100;
        } else {
          item.status = 'failed';
          item.message = response.errors?.[0]?.detail || `${xhr.status} ${xhr.statusText}`;
        }
        m.redraw();
        resolve();
      };
      xhr.onerror = () => {
        item.status = 'failed';
        item.message = app.translator.trans('mie-files.forum.network_error');
        m.redraw();
        resolve();
      };
      xhr.send(body);
    });
  }
}
