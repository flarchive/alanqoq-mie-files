<?php

namespace Mie\FlarumFiles;

final class CategoryDefaults
{
    public const FILE_DOWNLOAD = 'file_download';
    public const IMAGE_DOWNLOAD = 'image_download';
    public const IMAGE_INLINE = 'image_inline';
    public const URL_ONLY = 'url_only';
    public const MARKDOWN_IMAGE = 'markdown_image';
    public const BBCODE_IMAGE = 'bbcode_image';

    public const INSERT_TEMPLATES = [
        self::FILE_DOWNLOAD, self::IMAGE_DOWNLOAD, self::IMAGE_INLINE,
        self::URL_ONLY, self::MARKDOWN_IMAGE, self::BBCODE_IMAGE,
    ];

    public const TEMPLATES = [
        'images' => [
            'slug' => 'images', 'name' => 'Images', 'permissionName' => 'images',
            'maxSizeMb' => 10, 'storageName' => 'local', 'insertTemplate' => self::MARKDOWN_IMAGE,
            'rules' => [
                ['extension' => 'jpg', 'mime' => 'image/jpeg'],
                ['extension' => 'jpeg', 'mime' => 'image/jpeg'],
                ['extension' => 'png', 'mime' => 'image/png'],
                ['extension' => 'gif', 'mime' => 'image/gif'],
                ['extension' => 'webp', 'mime' => 'image/webp'],
                ['extension' => 'avif', 'mime' => 'image/avif'],
            ],
        ],
        'pdf' => [
            'slug' => 'pdf', 'name' => 'PDF', 'permissionName' => 'pdf',
            'maxSizeMb' => 20, 'storageName' => 'local', 'insertTemplate' => self::FILE_DOWNLOAD,
            'rules' => [['extension' => 'pdf', 'mime' => 'application/pdf']],
        ],
        'word' => [
            'slug' => 'word', 'name' => 'Word documents', 'permissionName' => 'word',
            'maxSizeMb' => 20, 'storageName' => 'local', 'insertTemplate' => self::FILE_DOWNLOAD,
            'rules' => [
                ['extension' => 'doc', 'mime' => 'application/msword'],
                ['extension' => 'docx', 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                ['extension' => 'odt', 'mime' => 'application/vnd.oasis.opendocument.text'],
            ],
        ],
        'spreadsheets' => [
            'slug' => 'spreadsheets', 'name' => 'Spreadsheets', 'permissionName' => 'spreadsheets',
            'maxSizeMb' => 20, 'storageName' => 'local', 'insertTemplate' => self::FILE_DOWNLOAD,
            'rules' => [
                ['extension' => 'xls', 'mime' => 'application/vnd.ms-excel'],
                ['extension' => 'xlsx', 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                ['extension' => 'ods', 'mime' => 'application/vnd.oasis.opendocument.spreadsheet'],
            ],
        ],
        'archives' => [
            'slug' => 'archives', 'name' => 'Archives', 'permissionName' => 'archives',
            'maxSizeMb' => 50, 'storageName' => 'local', 'insertTemplate' => self::FILE_DOWNLOAD,
            'rules' => [
                ['extension' => 'zip', 'mime' => 'application/zip'],
                ['extension' => '7z', 'mime' => 'application/x-7z-compressed'],
                ['extension' => 'tar', 'mime' => 'application/x-tar'],
                ['extension' => 'gz', 'mime' => 'application/gzip'],
            ],
        ],
        'audio' => [
            'slug' => 'audio', 'name' => 'Audio', 'permissionName' => 'audio',
            'maxSizeMb' => 50, 'storageName' => 'local', 'insertTemplate' => self::FILE_DOWNLOAD,
            'rules' => [
                ['extension' => 'mp3', 'mime' => 'audio/mpeg'],
                ['extension' => 'ogg', 'mime' => 'audio/ogg'],
                ['extension' => 'wav', 'mime' => 'audio/wav'],
                ['extension' => 'webm', 'mime' => 'audio/webm'],
            ],
        ],
        'video' => [
            'slug' => 'video', 'name' => 'Video', 'permissionName' => 'video',
            'maxSizeMb' => 100, 'storageName' => 'local', 'insertTemplate' => self::FILE_DOWNLOAD,
            'rules' => [
                ['extension' => 'mp4', 'mime' => 'video/mp4'],
                ['extension' => 'webm', 'mime' => 'video/webm'],
                ['extension' => 'ogg', 'mime' => 'video/ogg'],
            ],
        ],
    ];
}
