<?php

use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema): void {
        $connection = $schema->getConnection();
        $connection->table('posts')
            ->select(['id', 'content'])
            ->where('content', 'like', '%&lt;!-- mie-file:%')
            ->orderBy('id')
            ->chunkById(100, function ($posts) use ($connection): void {
                foreach ($posts as $post) {
                    $content = preg_replace(
                        '~(?:<br\s*/?>\s*)?&lt;!--\s*mie-file:[a-f0-9]{48}\s*--&gt;~',
                        '',
                        (string) $post->content
                    );
                    if ($content !== null && $content !== $post->content) {
                        $connection->table('posts')->where('id', $post->id)->update(['content' => $content]);
                    }
                }
            });
    },

    'down' => function (Builder $schema): void {},
];
