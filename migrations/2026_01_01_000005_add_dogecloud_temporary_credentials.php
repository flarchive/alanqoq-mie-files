<?php

use Flarum\Database\Migration;

return Migration::addColumns('mie_file_storages', [
    'doge_temporary_credentials_ciphertext' => ['text', 'nullable' => true],
    'doge_temporary_credentials_expires_at' => ['integer', 'unsigned' => true, 'nullable' => true],
]);
