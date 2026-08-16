<?php

namespace Mie\FlarumFiles\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\CallbackStream;
use Laminas\Diactoros\Response;
use Mie\FlarumFiles\Api\JsonResponder;
use Mie\FlarumFiles\Model\File;
use Mie\FlarumFiles\Service\ThumbnailService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ThumbnailController implements RequestHandlerInterface
{
    public function __construct(private ThumbnailService $thumbnails) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $file = File::query()->with('category')->find($request->getQueryParams()['id'] ?? null);
        if (!$file) {
            return JsonResponder::error('File not found.', 404, 'not_found');
        }
        try {
            [$stream, $headers, $temporaryPath] = $this->thumbnails->make($file, RequestUtil::getActor($request), $request);
        } catch (\Throwable $exception) {
            return JsonResponder::error($exception->getMessage(), str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422);
        }
        $body = new CallbackStream(function () use ($stream, $temporaryPath): void {
            while (!feof($stream)) {
                echo fread($stream, 8192);
            }
            fclose($stream);
            @unlink($temporaryPath);
        });
        $response = (new Response())->withBody($body);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }
}
