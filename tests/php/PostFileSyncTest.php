<?php

namespace Mie\FlarumFiles\Tests;

use Mie\FlarumFiles\Service\PostFileSync;
use PHPUnit\Framework\TestCase;

final class PostFileSyncTest extends TestCase
{
    public function testDirectObjectKeySuffixesAreParsedFromPublicUrls(): void
    {
        $key = '2030/01/'.str_repeat('a', 48).'.png';
        $content = "![image](https://cdn.example/{$key})\n[img]https://cdn.example/{$key}?v=1[/img]";

        self::assertSame([$key], PostFileSync::directObjectKeySuffixes($content));
    }

    public function testDirectObjectKeySuffixIsFoundBelowAConfiguredPathPrefix(): void
    {
        $suffix = '2030/01/'.str_repeat('c', 48).'.png';
        $content = "![image](https://cdn.example/flarum/uploads/{$suffix})";

        self::assertSame([$suffix], PostFileSync::directObjectKeySuffixes($content));
    }

    public function testADeletedLegacyMarkerDoesNotProvideAReference(): void
    {
        self::assertSame([], PostFileSync::directObjectKeySuffixes('<!-- mie-file:'.str_repeat('b', 48).' -->'));
    }
}
