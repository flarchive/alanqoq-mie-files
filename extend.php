<?php

use Flarum\Extend;
use Flarum\Post\Post;
use Flarum\User\User;
use Flarum\Post\Event\Deleted;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Revised;
use Flarum\Post\Event\Hidden;
use Flarum\Post\Event\Restored;
use Mie\FlarumFiles\Api\Controller\FileController;
use Mie\FlarumFiles\Api\Controller\ProxyController;
use Mie\FlarumFiles\Api\Controller\CategoryController;
use Mie\FlarumFiles\Api\Controller\MimeController;
use Mie\FlarumFiles\Api\Controller\StorageController;
use Mie\FlarumFiles\Api\Controller\TemplateController;
use Mie\FlarumFiles\Api\Controller\ThumbnailController;
use Mie\FlarumFiles\Listener\SyncPostFiles;
use Mie\FlarumFiles\Console\CleanOrphansCommand;

return [
    (new Extend\Frontend('forum'))->js(__DIR__.'/js/dist/forum.js')->css(__DIR__.'/less/forum.less'),
    (new Extend\Frontend('admin'))->js(__DIR__.'/js/dist/admin.js')->css(__DIR__.'/less/admin.less'),
    (new Extend\Locales(__DIR__.'/locale')),
    // Keep legacy posts readable until their stored marker is removed by the migration.
    (new Extend\Formatter())->render(function (\s9e\TextFormatter\Renderer $renderer, mixed $context, string $xml): string {
        return preg_replace('~(?:<br\s*/?>\s*)?&lt;!--\s*mie-file:[a-f0-9]{48}\s*--&gt;~', '', $xml) ?? $xml;
    }),
    (new Extend\Routes('api'))
        ->get('/mie/files', 'mie-files.index', FileController::class)
        ->post('/mie/files', 'mie-files.upload', FileController::class)
        ->delete('/mie/files/{id}', 'mie-files.delete', FileController::class)
        ->get('/mie/files/{id}/proxy', 'mie-files.proxy', ProxyController::class)
        ->get('/mie/files/{id}/thumbnail', 'mie-files.thumbnail', ThumbnailController::class)
        ->post('/mie/files/{id}/template', 'mie-files.template', TemplateController::class)
        ->get('/mie/categories', 'mie-files.categories', CategoryController::class)
        ->put('/mie/categories', 'mie-files.categories.replace', CategoryController::class)
        ->post('/mie/categories', 'mie-files.categories.create', CategoryController::class)
        ->patch('/mie/categories/{id}', 'mie-files.categories.update', CategoryController::class)
        ->delete('/mie/categories/{id}', 'mie-files.categories.delete', CategoryController::class)
        ->post('/mie/mime-detect', 'mie-files.mime-detect', MimeController::class)
        ->get('/mie/storage', 'mie-files.storage', StorageController::class)
        ->post('/mie/storage', 'mie-files.storage.create', StorageController::class)
        ->patch('/mie/storage/{id}', 'mie-files.storage.update', StorageController::class)
        ->delete('/mie/storage/{id}', 'mie-files.storage.delete', StorageController::class),
    (new Extend\Model(User::class))->hasMany('mieFiles', \Mie\FlarumFiles\Model\File::class, 'user_id'),
    (new Extend\Model(Post::class))->belongsToMany('mieFiles', \Mie\FlarumFiles\Model\File::class, 'mie_file_post', 'post_id', 'file_id'),
    (new Extend\Event())
        ->listen(Posted::class, SyncPostFiles::class)
        ->listen(Revised::class, SyncPostFiles::class)
        ->listen(Deleted::class, SyncPostFiles::class)
        ->listen(Hidden::class, SyncPostFiles::class)
        ->listen(Restored::class, SyncPostFiles::class),
    (new Extend\Console())
        ->command(CleanOrphansCommand::class)
        ->schedule(CleanOrphansCommand::class, function ($event) { $event->daily(); }),
];
