export const INSERT_TEMPLATES = [
  'file_download', 'image_download', 'image_inline', 'url_only', 'markdown_image', 'bbcode_image',
];

export const PRESETS = {
  images: { slug: 'images', name: 'Images', permissionName: 'images', maxSizeMb: 10, storageName: 'local', insertTemplate: 'markdown_image', rules: [['jpg', 'image/jpeg'], ['jpeg', 'image/jpeg'], ['png', 'image/png'], ['gif', 'image/gif'], ['webp', 'image/webp'], ['avif', 'image/avif']] },
  pdf: { slug: 'pdf', name: 'PDF', permissionName: 'pdf', maxSizeMb: 20, storageName: 'local', insertTemplate: 'file_download', rules: [['pdf', 'application/pdf']] },
  word: { slug: 'word', name: 'Word documents', permissionName: 'word', maxSizeMb: 20, storageName: 'local', insertTemplate: 'file_download', rules: [['doc', 'application/msword'], ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], ['odt', 'application/vnd.oasis.opendocument.text']] },
  spreadsheets: { slug: 'spreadsheets', name: 'Spreadsheets', permissionName: 'spreadsheets', maxSizeMb: 20, storageName: 'local', insertTemplate: 'file_download', rules: [['xls', 'application/vnd.ms-excel'], ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], ['ods', 'application/vnd.oasis.opendocument.spreadsheet']] },
  archives: { slug: 'archives', name: 'Archives', permissionName: 'archives', maxSizeMb: 50, storageName: 'local', insertTemplate: 'file_download', rules: [['zip', 'application/zip'], ['7z', 'application/x-7z-compressed'], ['tar', 'application/x-tar'], ['gz', 'application/gzip']] },
  audio: { slug: 'audio', name: 'Audio', permissionName: 'audio', maxSizeMb: 50, storageName: 'local', insertTemplate: 'file_download', rules: [['mp3', 'audio/mpeg'], ['ogg', 'audio/ogg'], ['wav', 'audio/wav'], ['webm', 'audio/webm']] },
  video: { slug: 'video', name: 'Video', permissionName: 'video', maxSizeMb: 100, storageName: 'local', insertTemplate: 'file_download', rules: [['mp4', 'video/mp4'], ['webm', 'video/webm'], ['ogg', 'video/ogg']] },
};

export function clonePreset(key) {
  const preset = PRESETS[key];
  if (!preset) return null;
  return {
    ...preset,
    id: undefined,
    enabled: true,
    rules: preset.rules.map(([extension, mime]) => ({ extension, mime })),
  };
}

export function validateCategories(categories) {
  const errors = {};
  const extensions = new Map();
  const slugs = new Map();
  const permissions = new Map();
  categories.forEach((category, index) => {
    for (const field of ['slug', 'name', 'permissionName', 'storageName', 'insertTemplate']) {
      if (!String(category[field] || '').trim()) errors[`${index}.${field}`] = 'required';
    }
    if (!Number.isInteger(Number(category.maxSizeMb)) || Number(category.maxSizeMb) < 1) errors[`${index}.maxSizeMb`] = 'invalid';
    if (!Array.isArray(category.rules) || category.rules.length === 0) errors[`${index}.rules`] = 'required';
    if (slugs.has(category.slug)) errors[`${index}.slug`] = 'duplicate';
    if (permissions.has(category.permissionName)) errors[`${index}.permissionName`] = 'duplicate';
    slugs.set(category.slug, index);
    permissions.set(category.permissionName, index);
    (category.rules || []).forEach((rule, ruleIndex) => {
      const extension = String(rule.extension || '').toLowerCase().replace(/^\./, '');
      const mime = String(rule.mime || '').toLowerCase();
      if (!/^[a-z0-9]{1,16}$/.test(extension)) errors[`${index}.rules.${ruleIndex}.extension`] = 'invalid';
      if (!/^[a-z0-9][a-z0-9.+-]*\/[a-z0-9][a-z0-9.+-]*$/.test(mime)) errors[`${index}.rules.${ruleIndex}.mime`] = 'invalid';
      const prior = extensions.get(extension) || [];
      if (prior.some((entry) => !['ogg', 'webm'].includes(extension) || entry.mime === mime)) {
        errors[`${index}.rules.${ruleIndex}.extension`] = 'duplicate';
      }
      prior.push({ mime, index, ruleIndex });
      extensions.set(extension, prior);
    });
  });
  return errors;
}
