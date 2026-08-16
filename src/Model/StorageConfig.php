<?php

namespace Mie\FlarumFiles\Model;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $name
 * @property string $driver
 * @property bool $enabled
 * @property string $endpoint
 * @property string $bucket
 * @property string|null $region
 * @property string|null $access_key_ciphertext
 * @property string|null $secret_key_ciphertext
 * @property string|null $public_base_url
 * @property bool $direct_delivery_confirmed
 */
class StorageConfig extends AbstractModel
{
    protected $table = 'mie_file_storages';
    protected $guarded = [];
    protected $casts = [
        'enabled' => 'boolean',
        'direct_delivery_confirmed' => 'boolean',
    ];
    protected $hidden = ['access_key_ciphertext', 'secret_key_ciphertext'];
}
