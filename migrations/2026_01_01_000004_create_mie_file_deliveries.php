<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mie_file_deliveries', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('file_id');
    $table->unsignedInteger('actor_id')->nullable();
    $table->string('mode', 16);
    $table->string('referer_host', 255)->nullable();
    $table->timestamps();
    $table->index(['file_id', 'created_at']);
});
