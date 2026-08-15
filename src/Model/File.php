<?php

namespace Mie\FlarumFiles\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property string $original_name
 * @property string $extension
 * @property string $mime_type
 * @property int $size
 * @property string $storage_name
 * @property string $object_key
 * @property string $public_token
 * @property string $status
 * @property int $downloads
 * @property string|null $last_error
 * @property \Carbon\Carbon|null $created_at
 * @property-read Category $category
 */
class File extends AbstractModel
{
    protected $table = 'mie_files';
    protected $guarded = [];
    protected $casts = ['size' => 'integer', 'downloads' => 'integer'];
    protected $dates = ['created_at', 'updated_at'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function category() { return $this->belongsTo(Category::class, 'category_id'); }
    public function posts() { return $this->belongsToMany(\Flarum\Post\Post::class, 'mie_file_post', 'file_id', 'post_id'); }
}
