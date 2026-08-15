import app from 'flarum/admin/app';
import SettingsPage from './components/SettingsPage';
import { registerPermissions } from './permissions';

app.initializers.add('alanqoq-mie-files', () => {
  app.registry.for('alanqoq-mie-files').registerSetting(() => SettingsPage.component(), 100);
  registerPermissions();
  app.beforeMount(() => {
    app.request({ method: 'GET', url: `${app.forum.attribute('apiUrl')}/mie/categories` })
      .then((response) => {
        registerPermissions(response.data || []);
        m.redraw();
      })
      .catch(() => undefined);
  });
});
