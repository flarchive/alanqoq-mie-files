import app from 'flarum/forum/app';
import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

export default class DeleteFilesModal extends Modal {
  className() {
    return 'Modal--small MieFiles-confirmModal';
  }

  title() {
    return app.translator.trans('mie-files.forum.delete_confirm_title');
  }

  content() {
    return (
      <div className="Modal-body">
        <p>{app.translator.trans('mie-files.forum.delete_confirm_text', { count: this.attrs.count })}</p>
        <div className="Form-group MieFiles-confirmActions">
          <Button className="Button" onclick={this.hide.bind(this)}>{app.translator.trans('mie-files.forum.cancel')}</Button>
          <Button className="Button Button--danger" onclick={() => this.attrs.onconfirm()}>{app.translator.trans('mie-files.forum.delete')}</Button>
        </div>
      </div>
    );
  }
}
