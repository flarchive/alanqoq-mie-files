<?php

namespace Mie\FlarumFiles\Api;

use Laminas\Diactoros\Response\JsonResponse;

final class JsonResponder
{
    public static function data(mixed $data, int $status = 200): JsonResponse
    {
        return new JsonResponse(['data' => $data], $status);
    }

    public static function error(string $detail, int $status = 422, string $code = 'mie_files_error'): JsonResponse
    {
        return new JsonResponse(['errors' => [[
            'status' => (string) $status,
            'code' => $code,
            'detail' => $detail,
        ]]], $status);
    }
}
