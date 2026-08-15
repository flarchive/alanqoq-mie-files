<?php

namespace Mie\FlarumFiles\Api\Controller;

use Flarum\Http\RequestUtil;
use Mie\FlarumFiles\Api\JsonResponder;
use Mie\FlarumFiles\Model\File;
use Mie\FlarumFiles\Service\TemplateService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class TemplateController implements RequestHandlerInterface
{
    public function __construct(private TemplateService $templates) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();
        $file = File::query()->with('category')->find($request->getQueryParams()['id'] ?? null);
        if (!$file) {
            return JsonResponder::error('File not found.', 404, 'not_found');
        }
        try {
            return JsonResponder::data($this->templates->render($file, $actor));
        } catch (\Throwable $exception) {
            return JsonResponder::error($exception->getMessage(), str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422);
        }
    }
}
