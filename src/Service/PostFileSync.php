<?php

namespace Mie\FlarumFiles\Service;

use Flarum\Post\Post;
use Mie\FlarumFiles\Model\File;

final class PostFileSync
{
    public function sync(Post $post, string $content): void
    {
        preg_match_all('#/mie/files/(\d+)/(?:proxy|download)#', $content, $matches);
        $ids = array_unique(array_map('intval', $matches[1]));
        preg_match_all('/<!--\s*mie-file:([a-f0-9]{48})\s*-->/', $content, $tokenMatches);
        $tokenIds = File::query()->where('user_id', $post->user_id)->whereIn('public_token', array_unique($tokenMatches[1]))->pluck('id')->all();
        $valid = File::query()->where('user_id', $post->user_id)->whereIn('id', array_unique(array_merge($ids, $tokenIds)))->pluck('id')->all();
        $post->belongsToMany(File::class, 'mie_file_post', 'post_id', 'file_id')->sync($valid);
    }
    public function detach(Post $post): void { $post->belongsToMany(File::class, 'mie_file_post', 'post_id', 'file_id')->detach(); }
}
