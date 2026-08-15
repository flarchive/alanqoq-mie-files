import app from 'flarum/admin/app';

const registered = new Set();

function add(permission, label, type, icon = 'far fa-file') {
  if (registered.has(permission)) return;
  app.registry.registerPermission({ icon, label, permission }, type, 50);
  registered.add(permission);
}

export function registerPermissions(categories = []) {
  app.registry.for('mie-files');
  add('mie-files.view-other', app.translator.trans('mie-files.permissions.view_other'), 'moderate', 'fas fa-folder-open');
  add('mie-files.delete-other', app.translator.trans('mie-files.permissions.delete_other'), 'moderate', 'fas fa-trash');
  categories.forEach((category) => {
    const name = category.permissionName;
    if (!name) return;
    add(`mie-files.category.${name}.view`, app.translator.trans('mie-files.permissions.category_view', { name }), 'view');
    add(`mie-files.category.${name}.download`, app.translator.trans('mie-files.permissions.category_download', { name }), 'view');
    add(`mie-files.category.${name}.upload`, app.translator.trans('mie-files.permissions.category_upload', { name }), 'start', 'fas fa-file-upload');
  });
}
