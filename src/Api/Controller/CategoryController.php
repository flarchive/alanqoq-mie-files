<?php

namespace Mie\FlarumFiles\Api\Controller;

use Flarum\Http\RequestUtil;
use Mie\FlarumFiles\Api\JsonResponder;
use Mie\FlarumFiles\Api\Serializer\CategorySerializer;
use Mie\FlarumFiles\Model\Category;
use Mie\FlarumFiles\Service\CategoryService;
use Mie\FlarumFiles\Service\PermissionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CategoryController implements RequestHandlerInterface
{
    public function __construct(private CategoryService $categories) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return match ($request->getMethod()) {
            'GET' => $this->index($request),
            'POST' => $this->create($request),
            'PUT' => $this->replace($request),
            'PATCH' => $this->update($request),
            'DELETE' => $this->delete($request),
            default => JsonResponder::error('Method not allowed.', 405),
        };
    }

    private function index(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        /** @var \Illuminate\Database\Eloquent\Collection<int, Category> $categories */
        $categories = Category::query()->orderBy('name')->get();
        $items = $categories->filter(function (Category $category) use ($actor): bool {
            return $actor->isAdmin() || ($category->enabled && (
                PermissionService::can($actor, $category->permission_name, 'view') ||
                PermissionService::can($actor, $category->permission_name, 'upload')
            ));
        })->map(fn (Category $category) => CategorySerializer::attributes($category))->values()->all();
        return JsonResponder::data($items);
    }

    private function create(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();
        try {
            return JsonResponder::data(CategorySerializer::attributes($this->categories->create((array) $request->getParsedBody())), 201);
        } catch (\Throwable $exception) {
            return JsonResponder::error($exception->getMessage());
        }
    }

    private function update(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();
        $category = Category::query()->find($request->getQueryParams()['id'] ?? null);
        if (!$category) {
            return JsonResponder::error('Category not found.', 404, 'not_found');
        }
        try {
            return JsonResponder::data(CategorySerializer::attributes($this->categories->update($category, (array) $request->getParsedBody())));
        } catch (\Throwable $exception) {
            return JsonResponder::error($exception->getMessage());
        }
    }

    private function replace(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();
        try {
            $body = (array) $request->getParsedBody();
            $items = $body['categories'] ?? [];
            return JsonResponder::data(array_map(fn (Category $category) => CategorySerializer::attributes($category), $this->categories->replaceAll((array) $items)));
        } catch (\Throwable $exception) {
            return JsonResponder::error($exception->getMessage());
        }
    }

    private function delete(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();
        $category = Category::query()->find($request->getQueryParams()['id'] ?? null);
        if (!$category) {
            return JsonResponder::error('Category not found.', 404, 'not_found');
        }
        if ($category->files()->exists()) {
            return JsonResponder::error('A category containing files cannot be removed.', 409);
        }
        $category->delete();
        return new \Laminas\Diactoros\Response\EmptyResponse(204);
    }
}
