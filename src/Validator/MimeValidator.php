<?php

namespace Mie\FlarumFiles\Validator;

final class MimeValidator
{
    public static function extension(string $name): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!preg_match('/^[a-z0-9]{1,16}$/', $extension)) {
            throw new \InvalidArgumentException('Invalid file extension.');
        }
        return $extension;
    }

    public static function detect(string $path): string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || $mime === '') {
            throw new \InvalidArgumentException('Unable to detect the PHP MIME type.');
        }
        return strtolower($mime);
    }

    /** @return array{string,string} */
    public static function validate(string $name, string $path, array $rules): array
    {
        $extension = self::extension($name);
        $mime = self::detect($path);
        foreach ($rules as $rule) {
            if (($rule['extension'] ?? null) === $extension && ($rule['mime'] ?? null) === $mime) {
                return [$extension, $mime];
            }
        }
        throw new \InvalidArgumentException("The detected type {$extension} / {$mime} is not allowed in this category.");
    }
}
