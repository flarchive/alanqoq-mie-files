<?php

namespace Mie\FlarumFiles\Api\Controller;

use Flarum\Http\RequestUtil;
use Mie\FlarumFiles\Api\JsonResponder;
use Mie\FlarumFiles\Model\Category;
use Mie\FlarumFiles\Model\File;
use Mie\FlarumFiles\Model\StorageConfig;
use Mie\FlarumFiles\Service\StorageConfigService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class StorageController implements RequestHandlerInterface
{
    public function __construct(private StorageConfigService $storages) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();
        return match ($request->getMethod()) {
            'GET' => $this->index(),
            'POST' => $this->save($request),
            'PATCH' => $this->save($request, StorageConfig::query()->find($request->getQueryParams()['id'] ?? null)),
            'DELETE' => $this->delete($request),
            default => JsonResponder::error('Method not allowed.', 405),
        };
    }

    private function index(): ResponseInterface
    {
        $items = [[
            'id' => 'local', 'name' => 'local', 'driver' => 'local', 'enabled' => true,
            'deliveryMode' => 'proxy', 'hasCredentials' => true,
        ]];
        /** @var \Illuminate\Database\Eloquent\Collection<int, StorageConfig> $storages */
        $storages = StorageConfig::query()->orderBy('name')->get();
        foreach ($storages as $storage) {
            $items[] = self::attributes($storage);
        }
        return JsonResponder::data($items);
    }

    private function save(ServerRequestInterface $request, ?StorageConfig $storage = null): ResponseInterface
    {
        if ($request->getMethod() === 'PATCH' && !$storage) {
            return JsonResponder::error('Storage configuration not found.', 404, 'not_found');
        }
        try {
            return JsonResponder::data(self::attributes($this->storages->save((array) $request->getParsedBody(), $storage)), $storage ? 200 : 201);
        } catch (\Throwable $exception) {
            return JsonResponder::error($exception->getMessage());
        }
    }

    private function delete(ServerRequestInterface $request): ResponseInterface
    {
        $storage = StorageConfig::query()->find($request->getQueryParams()['id'] ?? null);
        if (!$storage) {
            return JsonResponder::error('Storage configuration not found.', 404, 'not_found');
        }
        if (Category::query()->where('storage_name', $storage->name)->exists() || File::query()->where('storage_name', $storage->name)->exists()) {
            return JsonResponder::error('Storage is still assigned to a category.', 409);
        }
        $storage->delete();
        return new \Laminas\Diactoros\Response\EmptyResponse(204);
    }

    /** @return array<string,mixed> */
    private static function attributes(StorageConfig $storage): array
    {
        return [
            'id' => (string) $storage->id,
            'name' => $storage->name,
            'driver' => $storage->driver,
            'enabled' => (bool) $storage->enabled,
            'bucket' => $storage->bucket,
            'region' => $storage->region,
            'publicBaseUrl' => $storage->public_base_url,
            'deliveryMode' => $storage->public_base_url ? 'direct' : 'proxy',
            'hasCredentials' => (bool) ($storage->access_key_ciphertext && $storage->secret_key_ciphertext),
            'endpointConfigured' => (bool) $storage->endpoint,
            'directDeliveryConfirmed' => (bool) $storage->direct_delivery_confirmed,
        ];
    }
}
