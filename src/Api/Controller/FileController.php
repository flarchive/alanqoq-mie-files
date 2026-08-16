<?php

namespace Mie\FlarumFiles\Api\Controller;

use Flarum\Http\RequestUtil;
use Mie\FlarumFiles\Api\JsonResponder;
use Mie\FlarumFiles\Api\Serializer\FileSerializer;
use Mie\FlarumFiles\Model\Category;
use Mie\FlarumFiles\Model\File;
use Mie\FlarumFiles\Service\FileService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class FileController implements RequestHandlerInterface
{
    public function __construct(private FileService $files) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return match ($request->getMethod()) {
            'GET' => $this->index($request),
            'POST' => $this->upload($request),
            'DELETE' => $this->delete($request),
            default => JsonResponder::error('Method not allowed.', 405),
        };
    }

    private function index(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $all = (bool) ($request->getQueryParams()['all'] ?? false);
        $query = File::query()->with('category')->where('status', 'success')->latest();
        if (!$all || !$actor->can('mie-files.view-other')) {
            $query->where('user_id', $actor->id);
        }
        return JsonResponder::data($query->get()->map(fn (File $file) => FileSerializer::attributes($file))->values()->all());
    }

    private function upload(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $body = (array) $request->getParsedBody();
        $categoryInput = $body['category'] ?? $body['categoryId'] ?? null;
        $categoryId = is_array($categoryInput) ? ($categoryInput['id'] ?? $categoryInput['slug'] ?? null) : $categoryInput;
        $category = is_numeric($categoryId) ? Category::query()->find($categoryId) : Category::query()->where('slug', (string) $categoryId)->first();
        $upload = $request->getUploadedFiles()['file'] ?? null;
        if (!$category || !$category->enabled || !$upload) {
            return JsonResponder::error('A valid enabled category and file are required.');
        }
        try {
            $file = $this->files->upload($actor, $category, $upload);
            return JsonResponder::data(FileSerializer::attributes($file), 201);
        } catch (\Throwable $exception) {
            return JsonResponder::error($exception->getMessage(), str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422);
        }
    }

    private function delete(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $file = File::query()->find($request->getQueryParams()['id'] ?? null);
        if (!$file) {
            return JsonResponder::error('File not found.', 404, 'not_found');
        }
        if ((int) $file->user_id !== (int) $actor->id && !$actor->can('mie-files.delete-other')) {
            return JsonResponder::error('You cannot delete this file.', 403, 'permission_denied');
        }
        try {
            $this->files->remove($file);
            return new \Laminas\Diactoros\Response\EmptyResponse(204);
        } catch (\Throwable $exception) {
            return JsonResponder::error($exception->getMessage(), 409);
        }
    }
}
