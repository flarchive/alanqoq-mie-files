<?php

namespace Mie\FlarumFiles\Api\Controller;

use Flarum\Http\RequestUtil;
use Mie\FlarumFiles\Api\JsonResponder;
use Mie\FlarumFiles\CategoryDefaults;
use Mie\FlarumFiles\Validator\MimeValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class MimeController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();
        $upload = $request->getUploadedFiles()['file'] ?? null;
        if ($upload && $upload->getError() === UPLOAD_ERR_OK) {
            $path = tempnam(sys_get_temp_dir(), 'mie-mime-');
            if ($path === false) {
                return JsonResponder::error('Unable to create a temporary file.', 500);
            }
            try {
                $source = $upload->getStream();
                $source->rewind();
                $destination = fopen($path, 'wb');
                if ($destination === false) {
                    throw new \RuntimeException('Unable to write a temporary file.');
                }
                while (!$source->eof()) {
                    fwrite($destination, $source->read(8192));
                }
                fclose($destination);
                return JsonResponder::data([
                    'source' => 'php-finfo',
                    'extension' => MimeValidator::extension((string) $upload->getClientFilename()),
                    'mime' => MimeValidator::detect($path),
                ]);
            } finally {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }

        $extension = strtolower(ltrim(trim((string) (((array) $request->getParsedBody())['extension'] ?? '')), '.'));
        if (!preg_match('/^[a-z0-9]{1,16}$/', $extension)) {
            return JsonResponder::error('Upload a file or enter a valid extension.');
        }
        $mimes = [];
        foreach (CategoryDefaults::TEMPLATES as $category) {
            foreach ($category['rules'] as $rule) {
                if ($rule['extension'] === $extension) {
                    $mimes[] = $rule['mime'];
                }
            }
        }
        return JsonResponder::data(['source' => 'preset-map', 'extension' => $extension, 'mimes' => array_values(array_unique($mimes))]);
    }
}
