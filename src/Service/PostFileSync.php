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
        $objectKeyIds = File::query()
            ->where('user_id', $post->user_id)
            ->whereIn('object_key', self::directObjectKeys($content))
            ->pluck('id')
            ->all();
        $valid = File::query()->where('user_id', $post->user_id)->whereIn('id', array_unique(array_merge($ids, $objectKeyIds)))->pluck('id')->all();
        $post->belongsToMany(File::class, 'mie_file_post', 'post_id', 'file_id')->sync($valid);
    }

    /** @return list<string> */
    public static function directObjectKeys(string $content): array
    {
        preg_match_all(
            '~https?://[^\s<>"\'()\[\]]*/([0-9]{4}/[0-9]{2}/[a-f0-9]{48}\.[a-z0-9]{1,16})(?=\z|[?&#\s<>"\'()\[\]])~',
            $content,
            $matches
        );

        return array_values(array_unique($matches[1] ?? []));
    }

    public function detach(Post $post): void { $post->belongsToMany(File::class, 'mie_file_post', 'post_id', 'file_id')->detach(); }
}
