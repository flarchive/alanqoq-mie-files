<?php

namespace Mie\FlarumFiles\Model;

use Flarum\Database\AbstractModel;

/** @property int $id */
class FileDelivery extends AbstractModel
{
    protected $table = 'mie_file_deliveries';
    protected $guarded = [];
}
