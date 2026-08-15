<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mie_file_post', function (Blueprint $table) {
    $table->unsignedInteger('file_id');
    $table->unsignedInteger('post_id');
    $table->primary(['file_id', 'post_id']);
    $table->index('post_id');
});
