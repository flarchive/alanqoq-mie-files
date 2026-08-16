<?php

namespace Mie\FlarumFiles\Api\Controller;

use Flarum\Http\RequestUtil;
use Laminas\Diactoros\CallbackStream;
use Laminas\Diactoros\Response;
use Mie\FlarumFiles\Api\JsonResponder;
use Mie\FlarumFiles\Model\File;
use Mie\FlarumFiles\Service\DeliveryService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ProxyController implements RequestHandlerInterface
{
    public function __construct(private DeliveryService $delivery) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $file = File::query()->with('category')->find($request->getQueryParams()['id'] ?? null);
        if (!$file) {
            return JsonResponder::error('File not found.', 404, 'not_found');
        }
        $mode = (string) ($request->getQueryParams()['mode'] ?? $this->delivery->modeFor($file));
        try {
            [$stream, $headers] = $this->delivery->open($file, RequestUtil::getActor($request), $mode, $request);
        } catch (\Throwable $exception) {
            return JsonResponder::error($exception->getMessage(), str_contains(strtolower($exception->getMessage()), 'permission') ? 403 : 422);
        }

        $body = new CallbackStream(function () use ($stream): void {
            if (is_resource($stream)) {
                while (!feof($stream)) {
                    echo fread($stream, 8192);
                }
                fclose($stream);
                return;
            }
            while (!$stream->eof()) {
                echo $stream->read(8192);
            }
            $stream->close();
        });
        $response = (new Response())->withBody($body);
        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }
        return $response;
    }
}
