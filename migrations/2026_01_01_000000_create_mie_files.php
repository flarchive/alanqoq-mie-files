<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

return Migration::createTable('mie_files', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('user_id');
    $table->unsignedInteger('category_id');
    $table->string('original_name', 255);
    $table->string('extension', 16);
    $table->string('mime_type', 128);
    $table->unsignedBigInteger('size');
    $table->string('storage_name', 64)->default('local');
    $table->string('object_key', 512);
    $table->string('public_token', 64)->unique();
    $table->string('status', 24)->default('success');
    $table->unsignedInteger('downloads')->default(0);
    $table->text('last_error')->nullable();
    $table->timestamps();
    $table->index(['user_id', 'status']);
    $table->index(['category_id', 'extension', 'mime_type']);
});
