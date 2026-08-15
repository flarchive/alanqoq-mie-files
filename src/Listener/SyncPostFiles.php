<?php

namespace Mie\FlarumFiles\Listener;

use Flarum\Post\Event\Deleted;
use Flarum\Post\Event\Hidden;
use Flarum\Post\Event\Posted;
use Flarum\Post\Event\Restored;
use Flarum\Post\Event\Revised;
use Mie\FlarumFiles\Service\PostFileSync;

final class SyncPostFiles
{
    public function __construct(private PostFileSync $sync) {}

    public function handle(Posted|Revised|Deleted|Hidden|Restored $event): void
    {
        if ($event instanceof Deleted || $event instanceof Hidden) {
            $this->sync->detach($event->post);
            return;
        }
        $this->sync->sync($event->post, (string) $event->post->content);
    }
}
