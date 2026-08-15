<?php

namespace Mie\FlarumFiles\Tests;

use Mie\FlarumFiles\Validator\MimeValidator;
use PHPUnit\Framework\TestCase;

final class MimeValidatorTest extends TestCase
{
    public function testSpoofedExtensionIsRejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mie'); file_put_contents($path, '%PDF-1.7');
        $this->expectException(\InvalidArgumentException::class);
        MimeValidator::validate('x.jpg', $path, [['extension' => 'jpg', 'mime' => 'image/jpeg']]);
        @unlink($path);
    }
    public function testExtensionIsNormalized(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mie');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLQ0QAAAABJRU5ErkJggg=='));
        [$extension] = MimeValidator::validate('x.PNG', $path, [['extension' => 'png', 'mime' => 'image/png']]);
        self::assertSame('png', $extension); @unlink($path);
    }
}
