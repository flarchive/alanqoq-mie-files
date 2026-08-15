import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import DeleteFilesModal from './DeleteFilesModal';
import { apiUrl, errorMessage, humanSize, request } from '../api';

export default class FileLibraryModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    this.files = [];
    this.selectedIds = new Set();
    this.activeFile = null;
    this.loading = true;
    this.busy = false;
    this.load();
  }

  className() {
    return 'Modal--large MieFiles-libraryModal';
  }

  title() {
    return app.translator.trans('mie-files.forum.library_title');
  }

  content() {
    return (
      <div className="Modal-body MieFiles-libraryBody">
        {this.error && <div className="MieFiles-errorText">{this.error}</div>}
        {this.loading ? <LoadingIndicator /> : (
          <div className="MieFiles-libraryLayout">
            <div className="MieFiles-fileGrid">
              {this.files.length === 0 && <p className="MieFiles-empty">{app.translator.trans('mie-files.forum.library_empty')}</p>}
              {this.files.map((file) => this.fileTile(file))}
            </div>
            <aside className="MieFiles-fileDetails">
              {this.activeFile ? this.details(this.activeFile) : <span>{app.translator.trans('mie-files.forum.select_file')}</span>}
            </aside>
          </div>
        )}
        <div className="MieFiles-modalActions">
          <Button className="Button Button--danger" disabled={this.selectedIds.size === 0 || this.busy} onclick={this.confirmDelete.bind(this)}>
            {app.translator.trans('mie-files.forum.delete_selected', { count: this.selectedIds.size })}
          </Button>
          <Button className="Button" onclick={this.hide.bind(this)}>{app.translator.trans('mie-files.forum.close')}</Button>
        </div>
      </div>
    );
  }

  fileTile(file) {
    const image = /^image\//.test(file.mimeType);
    const selected = this.selectedIds.has(file.id);
    return (
      <article
        className={`MieFiles-fileTile ${this.activeFile?.id === file.id ? 'is-active' : ''}`}
        onclick={() => { this.activeFile = file; }}
        ondblclick={() => this.insert(file)}
      >
        <label className="MieFiles-selectBox" onclick={(event) => event.stopPropagation()}>
          <input type="checkbox" checked={selected} onchange={() => this.toggle(file.id)} />
        </label>
        <div className="MieFiles-fileVisual">
          {image ? <i className="fas fa-image" aria-hidden="true" /> : <i className="fas fa-file" aria-hidden="true" />}
        </div>
        <strong title={file.originalName}>{file.originalName}</strong>
        <span>{humanSize(file.size)}</span>
      </article>
    );
  }

  details(file) {
    return (
      <dl>
        <dt>{app.translator.trans('mie-files.forum.file_name')}</dt><dd>{file.originalName}</dd>
        <dt>{app.translator.trans('mie-files.forum.file_size')}</dt><dd>{humanSize(file.size)}</dd>
        <dt>{app.translator.trans('mie-files.forum.file_type')}</dt><dd>{file.mimeType}</dd>
        <dt>{app.translator.trans('mie-files.forum.uploaded_at')}</dt><dd>{file.createdAt ? new Date(file.createdAt).toLocaleString() : '-'}</dd>
        <dt>{app.translator.trans('mie-files.forum.category')}</dt><dd>{file.categoryName || '-'}</dd>
      </dl>
    );
  }

  load() {
    this.loading = true;
    return request({ method: 'GET', url: apiUrl('/files') })
      .then((response) => { this.files = response.data || []; })
      .catch((error) => { this.error = errorMessage(error); })
      .finally(() => { this.loading = false; m.redraw(); });
  }

  toggle(id) {
    if (this.selectedIds.has(id)) this.selectedIds.delete(id);
    else this.selectedIds.add(id);
  }

  insert(file) {
    if (this.busy) return;
    this.busy = true;
    request({ method: 'POST', url: apiUrl(`/files/${encodeURIComponent(file.id)}/template`) })
      .then((response) => {
        const markup = response.data?.markup;
        if (!markup || !this.attrs.editor) throw new Error(app.translator.trans('mie-files.forum.insert_failed'));
        this.attrs.editor.insertAtCursor(`${markup}\n`, false);
        this.hide();
      })
      .catch((error) => { this.error = errorMessage(error); })
      .finally(() => { this.busy = false; m.redraw(); });
  }

  confirmDelete() {
    app.modal.show(DeleteFilesModal, {
      count: this.selectedIds.size,
      onconfirm: () => {
        app.modal.close();
        this.deleteSelected();
      },
    });
  }

  deleteSelected() {
    const ids = Array.from(this.selectedIds);
    this.busy = true;
    Promise.all(ids.map((id) => request({ method: 'DELETE', url: apiUrl(`/files/${encodeURIComponent(id)}`) })
      .then(() => ({ id, ok: true }))
      .catch((error) => ({ id, ok: false, error: errorMessage(error) }))))
      .then((results) => {
        const failed = results.filter((result) => !result.ok);
        const removed = new Set(results.filter((result) => result.ok).map((result) => result.id));
        this.files = this.files.filter((file) => !removed.has(file.id));
        this.selectedIds = new Set(failed.map((result) => result.id));
        this.activeFile = this.activeFile && !removed.has(this.activeFile.id) ? this.activeFile : null;
        this.error = failed.map((result) => result.error).filter(Boolean).join(' ');
      })
      .finally(() => { this.busy = false; m.redraw(); });
  }
}
