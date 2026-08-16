# Architecture

## Runtime entry points

`extend.php` is the extension composition root. It registers the forum and
admin bundles, API routes, model relationships, post lifecycle listeners, and
the orphan-cleaning console command.

## Source layout

| Path | Responsibility |
| --- | --- |
| `src/Api` | HTTP controllers, JSON responses, and API serializers. |
| `src/Model` | Eloquent models for files, categories, storage configurations, and delivery records. |
| `src/Service` | Uploading, storage access, delivery authorization, template rendering, and post-file synchronization. |
| `src/Validator` | File category and MIME validation. |
| `src/Listener` and `src/Console` | Flarum event and scheduled-command integration. |
| `migrations` | Database schema for the extension-owned tables. |
| `js/src/admin` | Storage and category administration UI. |
| `js/src/forum` | Composer upload and file-library UI. |
| `js/src/common` | Shared browser API client and types. |
| `less` | Admin and forum styling. |
| `tests/php` and `js/tests` | PHP and browser-side unit/integration tests. |

## Primary flows

### Upload

`UploadModal` calls the files API, which routes to `FileController` and then
`FileService`. `FileService` validates the category permission, size, filename,
and detected MIME type before writing the object through `StorageFactory`.
The database record is marked `pending`, then `success` or `failed` after the
storage operation completes.

### Insertion and delivery

`FileLibraryModal` requests an insertion template from `TemplateController`.
`TemplateService` delegates URL generation to `DeliveryService`, which checks
availability and category permission before returning either an authenticated
proxy URL or a configured direct object-storage URL. `ProxyController` uses the
same service to stream proxied content and record tracked deliveries.

### Post associations and cleanup

Flarum post events invoke `SyncPostFiles`, which delegates association updates
to `PostFileSync`. `CleanOrphansCommand` runs daily and uses `OrphanCleaner` to
remove successful, unreferenced files after the configured retention period.

## Local code map

Graphify output is generated locally in `graphify-out/`. Refresh it after
structural changes and regenerate the interactive tree when needed:

```bash
graphify update . --no-cluster
graphify tree --root . --label "Mie Files for Flarum"
```

The current extraction contains 395 nodes and 808 relationships. The main
architectural hubs are `SettingsPage`, `JsonResponder`, `File`,
`DeliveryService`, and `FileService`.
