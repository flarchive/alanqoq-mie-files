<?php

namespace Mie\FlarumFiles\Tests;

use Mie\FlarumFiles\Service\PostFileSync;
use PHPUnit\Framework\TestCase;

final class PostFileSyncTest extends TestCase
{
    public function testDirectObjectKeysAreParsedFromPublicUrls(): void
    {
        $key = '2030/01/'.str_repeat('a', 48).'.png';
        $content = "![image](https://cdn.example/{$key})\n[img]https://cdn.example/{$key}?v=1[/img]";

        self::assertSame([$key], PostFileSync::directObjectKeys($content));
    }

    public function testADeletedLegacyMarkerDoesNotProvideAReference(): void
    {
        self::assertSame([], PostFileSync::directObjectKeys('<!-- mie-file:'.str_repeat('b', 48).' -->'));
    }
}
