<?php

namespace Mie\FlarumFiles\Tests;

use Mie\FlarumFiles\CategoryDefaults;
use Mie\FlarumFiles\Validator\CategoryValidator;
use PHPUnit\Framework\TestCase;

final class CategoryValidatorTest extends TestCase
{
    public function testOggCanBelongToAudioAndVideoOnlyWithDifferentFullMime(): void
    {
        $audio = CategoryValidator::validate(CategoryDefaults::TEMPLATES['audio'], null, []);
        $video = CategoryValidator::validate(CategoryDefaults::TEMPLATES['video'], null, [$audio]);
        self::assertSame('video', $video['slug']);
    }

    public function testRegularExtensionCannotBeDuplicatedAcrossCategories(): void
    {
        $image = CategoryValidator::validate(CategoryDefaults::TEMPLATES['images'], null, []);
        $copy = CategoryDefaults::TEMPLATES['images'];
        $copy['slug'] = 'duplicate-images';
        $copy['permissionName'] = 'duplicate-images';
        $this->expectException(\InvalidArgumentException::class);
        CategoryValidator::validate($copy, null, [$image]);
    }

    public function testImageTemplateRequiresImageMime(): void
    {
        $category = CategoryDefaults::TEMPLATES['pdf'];
        $category['insertTemplate'] = CategoryDefaults::MARKDOWN_IMAGE;
        $this->expectException(\InvalidArgumentException::class);
        CategoryValidator::validate($category, null, []);
    }
}
