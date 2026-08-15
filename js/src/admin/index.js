import app from 'flarum/admin/app';
import SettingsPage from './components/SettingsPage';
import { registerPermissions } from './permissions';

app.initializers.add('mie-flarum-files', () => {
  app.registry.for('mie-files').registerSetting(() => SettingsPage.component(), 100);
  registerPermissions();
  app.request({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/mie/categories` })
    .then((response) => registerPermissions(response.data || []))
    .catch(() => undefined);
});
