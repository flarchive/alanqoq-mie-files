<?php

namespace Mie\FlarumFiles\Model;

use Flarum\Database\AbstractModel;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $permission_name
 * @property int $max_size
 * @property string $storage_name
 * @property string $insert_template
 * @property array $extensions
 * @property array $mimes
 * @property array $rules
 * @property bool $enabled
 */
class Category extends AbstractModel
{
    protected $table = 'mie_file_categories';
    protected $guarded = [];
    protected $casts = [
        'extensions' => 'array',
        'mimes' => 'array',
        'rules' => 'array',
        'max_size' => 'integer',
        'enabled' => 'boolean',
    ];

    public function files()
    {
        return $this->hasMany(File::class, 'category_id');
    }
}
