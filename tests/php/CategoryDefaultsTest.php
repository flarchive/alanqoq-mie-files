<?php

namespace Mie\FlarumFiles\Tests;

use Mie\FlarumFiles\CategoryDefaults;
use PHPUnit\Framework\TestCase;

final class CategoryDefaultsTest extends TestCase
{
    public function testPresetsUseRealExtensionsAndMimes(): void
    {
        self::assertCount(7, CategoryDefaults::TEMPLATES);
        self::assertContains(['extension' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'], CategoryDefaults::TEMPLATES['word']['rules']);
        self::assertContains(['extension' => 'ogg', 'mime' => 'audio/ogg'], CategoryDefaults::TEMPLATES['audio']['rules']);
        self::assertContains(['extension' => 'ogg', 'mime' => 'video/ogg'], CategoryDefaults::TEMPLATES['video']['rules']);
    }
}
