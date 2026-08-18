<?php

namespace Mie\FlarumFiles\Service;

use Flarum\User\User;
use Mie\FlarumFiles\Model\Category;
use Mie\FlarumFiles\Model\File;
use Mie\FlarumFiles\Validator\MimeValidator;
use Psr\Http\Message\UploadedFileInterface;

final class FileService
{
    public function __construct(private StorageFactory $storages, private FileCache $cache) {}

    public function upload(User $actor, Category $category, UploadedFileInterface $upload): File
    {
        if (!PermissionService::can($actor, $category->permission_name, 'upload')) {
            throw new \RuntimeException('Upload permission denied.');
        }
        if ($upload->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('The upload did not complete successfully.');
        }

        $temporaryPath = $this->temporaryPath($upload);
        try {
            $size = filesize($temporaryPath);
            if ($size === false || $size < 1 || $size > (int) $category->max_size) {
                throw new \InvalidArgumentException('The file exceeds this category maximum size.');
            }
            [$extension, $mime] = MimeValidator::validate((string) $upload->getClientFilename(), $temporaryPath, (array) $category->rules);
            $prefix = $this->storages->pathPrefix($category->storage_name);
            $objectKey = ($prefix === '' ? '' : $prefix.'/').date('Y/m').'/'.bin2hex(random_bytes(24)).'.'.$extension;
            $file = new File();
            $file->fill([
                'user_id' => $actor->id,
                'category_id' => $category->id,
                'original_name' => $this->safeName((string) $upload->getClientFilename()),
                'extension' => $extension,
                'mime_type' => $mime,
                'size' => $size,
                'storage_name' => $category->storage_name,
                'object_key' => $objectKey,
                'public_token' => bin2hex(random_bytes(24)),
                'status' => 'pending',
            ])->save();
            try {
                $this->storages->make($category->storage_name)->put($objectKey, $temporaryPath);
                $file->forceFill(['status' => 'success', 'last_error' => null])->save();
            } catch (\Throwable $exception) {
                $file->forceFill(['status' => 'failed', 'last_error' => $exception->getMessage()])->save();
                throw $exception;
            }
            return $file->fresh(['category']);
        } finally {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    public function remove(File $file): void
    {
        try {
            $this->storages->make($file->storage_name)->delete($file->object_key);
            $this->cache->forgetFile($file->storage_name, $file->object_key);
            $file->posts()->detach();
            $file->delete();
        } catch (\Throwable $exception) {
            $file->forceFill(['status' => 'delete_failed', 'last_error' => $exception->getMessage()])->save();
            throw $exception;
        }
    }

    private function temporaryPath(UploadedFileInterface $upload): string
    {
        $stream = $upload->getStream();
        $path = tempnam(sys_get_temp_dir(), 'mie-upload-');
        if ($path === false) {
            throw new \RuntimeException('Cannot create an upload temporary file.');
        }
        $destination = fopen($path, 'wb');
        if ($destination === false) {
            throw new \RuntimeException('Cannot write the upload temporary file.');
        }
        $stream->rewind();
        while (!$stream->eof()) {
            fwrite($destination, $stream->read(8192));
        }
        fclose($destination);
        return $path;
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim(str_replace(["\r", "\n", "\0"], '', $name));
        return $name === '' ? 'file' : mb_substr($name, 0, 255);
    }
}
