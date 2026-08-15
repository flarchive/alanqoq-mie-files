import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import TextEditor from 'flarum/common/components/TextEditor';
import TextEditorButton from 'flarum/common/components/TextEditorButton';
import UploadModal from './components/UploadModal';
import FileLibraryModal from './components/FileLibraryModal';

app.initializers.add('mie-flarum-files', () => {
  extend(TextEditor.prototype, 'controlItems', function (items) {
    if (!app.session.user) return;

    items.add('mie-files-upload', <TextEditorButton className="Button Button--icon mie-files-upload-button" icon="fas fa-file-upload" title={app.translator.trans('mie-files.forum.upload')} onclick={() => {
      app.modal.show(UploadModal, { editor: this.attrs.composer.editor });
    }} />);

    items.add('mie-files-library', <TextEditorButton className="Button Button--icon mie-files-library-button" icon="fas fa-photo-video" title={app.translator.trans('mie-files.forum.library')} onclick={() => {
      app.modal.show(FileLibraryModal, { editor: this.attrs.composer.editor });
    }} />);
  });
});
